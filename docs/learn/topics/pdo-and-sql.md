# PDO — Talking to MySQL Safely

> **What you'll learn:** what a database *driver* is; what **PDO** is; how a **SQL injection** attack really works and why **prepared statements** kill it; the four PDO settings we turn on; why table names cannot be placeholders and what we do instead; our single shared connection; the dev query counter; and where we use transactions.
>
> **Prerequisites:** basic PHP only — `echo`, variables, `if`/`else`, functions, `include`, arrays. No classes, namespaces, Composer, SQL or security jargon needed. Every term is explained the first time it appears.
>
> **Where it lives:** `src/Core/Db.php` (the connection), `src/Core/Config.php` + `config/config.sample.php` (credentials), `src/Content/Repo.php` (all reading), `public_html/admin/api/*.php` (all writing), `src/Content/Registry.php` (the allowed table/column names).

## 1. The one-paragraph version

Our site keeps its content — hero slides, profiles, gallery photos, mark lists — in a **database** called MySQL: an organised filing cabinet living inside another program. PHP cannot open that cabinet alone; it needs a **driver**, a small piece of software that speaks the database's private language over a network socket. We use **PDO** ("PHP Data Objects"), PHP's built-in, uniform way to talk to any SQL database. The important part: PDO lets us send a question and its values through **two separate channels**. MySQL compiles the question ("find the user with this username") *first*, and only afterwards do the visitor's actual letters arrive as data. That separation is what makes typed-in SQL harmless. Every database call in this repo goes through `SJ\Core\Db::pdo()` in `src/Core/Db.php`, which returns one shared, pre-configured connection.

## 2. The problem this solves

### 2a. PHP cannot reach MySQL by itself

PHP is one program; MySQL is a different one (in dev, a different Docker container — `docker-compose.yml:3`, `image: mysql:8.0`). They talk over a binary protocol nobody wants to write by hand; the driver does it for us. The old way, still in ancient tutorials, was `mysql_connect()` / `mysql_query()` — deleted from PHP in version 7. Its replacement, `mysqli`, still works but is MySQL-only and its prepared-statement API is clumsy (`bind_param('ss', $a, $b)` with type-letter strings). PDO gives the same safety with far less ceremony.

### 2b. The dangerous problem: SQL injection

Imagine our admin login written the naive way — gluing the visitor's text into the middle of the SQL:

```php
// NEVER WRITE THIS. Illustration of the bug only.
$u   = $_POST['username'];
$p   = $_POST['password'];
$sql = "SELECT * FROM admin_users WHERE username = '$u' AND password = '$p'";
```

An attacker types this into the username box:

```
' OR '1'='1
```

PHP is not clever. It pastes the letters in. **The string MySQL actually receives is:**

```sql
SELECT * FROM admin_users WHERE username = '' OR '1'='1' AND password = ''
```

Read what MySQL sees. The attacker's leading `'` **closed our quote early**. Everything after it stopped being *data* and became *SQL keywords*. `'1'='1'` is always true, so the `WHERE` matches and rows come back.

The analogy: you dictate a letter to a typist — "write: Dear Sir". The attacker's text is "Dear Sir. STOP. New instruction: post this to my house." The typist cannot tell your instructions from the words you asked her to write down, because both arrived through the same channel: your voice.

It gets worse than logging in. Controlling SQL keywords means you can append `UNION SELECT password_hash FROM admin_users` or `; DROP TABLE admin_users`. This is item **SEC-01** in our catalog (`SECURITY.md:16`), OWASP category A03. **The fix in one sentence:** send the sentence and the words down two different channels — which is exactly what a prepared statement does.

## 3. How it works in general

### 3a. Connecting: the DSN

**DSN** = Data Source Name: one string saying *which* database to open. Ours, `src/Core/Db.php:48`:

```php
$dsn = \sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['name']);
```

`mysql:` picks the driver; `host`/`port` say where; `dbname` picks the database; `charset=utf8mb4` sets text encoding for the whole connection. MySQL's confusingly named `utf8` only stores 3-byte characters; `utf8mb4` stores the full 4-byte range, which Tamil text, the `—` dash and emoji need. Setting it in the DSN applies it from the first byte exchanged. Note what is **absent**: no username, no password — those arrive separately from config (4b).

### 3b. The three verbs

```php
$st  = db()->prepare('SELECT * FROM pages WHERE slug = ?');  // 1. send the QUESTION
$st->execute([$slug]);                                        // 2. send the VALUES
$row = $st->fetch();                                          // 3. read a row back
```

That is real code — `src/Content/Repo.php:96-98`.

| Call | What it does |
|---|---|
| `prepare(...)` | Sends the SQL **text**. MySQL parses and plans it, then keeps it ready. Returns a *statement* object. |
| `execute([...])` | Sends the **values** as a separate typed payload. MySQL drops them into the pre-planned holes. |
| `fetch()` | Returns **one** row as an array, or `false` when there are no more. |
| `fetchAll()` | Returns **every** remaining row: an array of arrays. |
| `fetchColumn()` | Returns the **first column of the first row** as one scalar. Ideal for `SELECT COUNT(*)`. |

The `?` is a **placeholder** (or "bind parameter") — a hole in the sentence.

### 3c. Why the attack dies

Replay section 2b's attack against a prepared statement:

```php
$st = db()->prepare('SELECT * FROM admin_users WHERE username = ?');
$st->execute([$username]);   // $username is still "' OR '1'='1"
```

MySQL parsed the SQL **before it had ever seen the attacker's text**. The plan is frozen: "look up one row by username". When the value arrives it is an opaque blob of characters, so MySQL searches for a user literally named `' OR '1'='1`. No such user. Zero rows. The attack is not *escaped* or *filtered* — it is structurally impossible, because the value never went through the parser.

Back to the typist: she now has a pre-printed form with a blank box labelled "name". Whatever the attacker writes in the box stays in the box, because the instructions were printed first.

### 3d. Two placeholder styles

```php
$st = db()->prepare('SELECT * FROM images WHERE id = ?');    // positional
$st->execute([$id]);
$st = db()->prepare('SELECT * FROM images WHERE id = :id');  // named
$st->execute([':id' => $id]);
```

**This codebase uses positional `?` everywhere.** Grepping `src/`, `public_html/` and `database/` for named placeholders (`= :name`, `VALUES (:`) and for `bindValue`/`bindParam` returns nothing. One style, no surprises.

### 3e. The four options we set

```php
$opts  = [   // src/Core/Db.php:49-53
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
```

…plus `charset=utf8mb4` in the DSN (line 48). Four settings, four reasons:

| Setting | Without it | Why we set it |
|---|---|---|
| `ATTR_ERRMODE => ERRMODE_EXCEPTION` | A failed query silently returns `false` | Errors **throw** and stop the request loudly, pointing at the real line instead of crashing three lines later. |
| `ATTR_EMULATE_PREPARES => false` | PHP fakes prepared statements inside PHP | MySQL does the real prepare. See below — this is the important one. |
| `ATTR_DEFAULT_FETCH_MODE => FETCH_ASSOC` | Every row arrives **twice**: keyed by name *and* by number | Clean `['slug' => 'home', …]` rows, half the memory, no duplicate keys. |
| `charset=utf8mb4` (DSN) | latin1 or server default | Full Unicode survives intact. |

**Why emulation is weaker.** With emulation **on** (PHP's MySQL default), `prepare()` sends nothing to the server: PHP holds the SQL in memory, and at `execute()` time PHP itself quotes each value and *glues the final string together*, then sends one ordinary query. The value and the SQL end up in the same channel after all. It usually works, because PHP's quoting is competent — but "usually" now depends on a PHP function agreeing with MySQL about the connection charset, and history has produced real CVEs where they disagreed and a quote slipped through. Emulation **off** means the value travels as a typed parameter over the wire and is never concatenated into SQL text by anyone. A structural guarantee, not a promise about string handling. `SECURITY.md:18` records this as the SEC-01 mitigation.

### 3f. The one thing placeholders cannot do

A placeholder is a hole for a **value**. Table and column names are **identifiers** — part of the query's grammar, not its data. MySQL must know them at `prepare()` time to plan the query at all. So these do not work:

```php
db()->prepare('SELECT * FROM ? WHERE id = ?');   // ERROR — a table is not a value
db()->prepare('SELECT * FROM pages ORDER BY ?'); // "runs" but sorts by a constant string
```

Which raises the question our admin panel must answer: the browser sends `{"entity":"hero_slide","field":"caption_title"}` and we must build an `UPDATE` from it. Where does the table name come from? Section 4d.

## 4. How we use it — every place in this codebase

### 4a. One connection per request: the singleton

`src/Core/Db.php:36-44`:

```php
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        …
```

A **singleton** means "there is exactly one of these and everybody shares it". `static` makes the value belong to the class rather than any object, so it survives the whole request. The first caller opens a TCP connection and logs in — real milliseconds. Every later caller gets the same object instantly; reconnecting per query would be like hanging up and re-dialling between sentences. Page code never calls `Db::pdo()` directly, but the global helper `db()` (`src/helpers.php:28-31`), a one-line forwarder. We do **not** use persistent connections — `PDO::ATTR_PERSISTENT` appears nowhere in `src/` or `public_html/`.

### 4b. Credentials never live in code

`src/Core/Db.php:46` reads `Config::all()['db']`. `src/Core/Config.php:25-35` resolves it in priority order: (1) environment variables `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASS`, which win (lines 30-35); (2) `config/config.php` — gitignored and **above** the webroot, so no browser can fetch it; (3) `config/config.sample.php`, the committed placeholder whose password is literally `'CHANGE_ME'` (`config/config.sample.php:22`). In Docker the env vars come from `docker-compose.yml:27` (`DB_HOST: db`) and `.env`. Nothing secret is ever committed — SEC-22, and the last bullet of `CLAUDE.md`.

### 4c. Reading: `src/Content/Repo.php`

Every public page reads through this one class. Four patterns:

**A value from outside → always prepared** (`src/Content/Repo.php:35-40`). The `?: null` matters: `fetch()` returns `false` when there is no row.

```php
public static function seo(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM seo_meta WHERE slug = ?');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}
```

**No outside value at all → plain `query()`**, which sends and runs SQL in one step. Safe *only* because the string is a code literal — `foreach (db()->query('SELECT skey, svalue FROM settings') as $r) {` at `src/Content/Repo.php:27`, inside `setting()`, which caches the whole table in a `static $cache` (line 24) so the second and hundredth call this request cost zero queries.

**A `LIMIT` count → cast to int, then interpolated** (`src/Content/Repo.php:315-316`). `$limit` is typed `?int` and wrapped in `max(1, …)`, so only a whole number ≥ 1 can land there. Gotcha 2 explains why `LIMIT ?` was avoided.

```php
$sql = 'SELECT * FROM mark_years' . ($includeInactive ? '' : ' WHERE is_active = 1')
     . ' ORDER BY year DESC LIMIT ' . \max(1, $limit);
```

**A variable-length `IN (…)` list → one `?` per id, generated** (`src/Content/Repo.php:60-62`). `$in` becomes `?,?,?`: the *count* of ids shapes the SQL; not one id value touches the SQL text.

```php
$in = \implode(',', \array_fill(0, \count($ids), '?'));
$st = db()->prepare("SELECT * FROM images WHERE id IN ($in)");
$st->execute(\array_keys($ids));
```

### 4d. Writing: the admin API and the identifier whitelist

`public_html/admin/api/field.php` receives `{entity, id, field, value}` from the browser and must update one column (`:10-18`):

```php
$reg = api_entity($entity);
$def = $reg['fields'][$field] ?? null;
if ($def === null || $id <= 0) {
    api_fail('Unknown field');
}
$value = api_validate_field($entity, $field, $def, $in['value'] ?? null);

$st = db()->prepare("UPDATE {$reg['table']} SET `$field` = ? WHERE id = ?");
$st->execute([$value, $id]);
```

Line 17 interpolates into SQL, which normally screams "bug". It is safe, precisely because:

- `api_entity()` (`public_html/admin/api/_bootstrap.php:121-129`) forwards to `Registry::entity()` (`src/Content/Registry.php:214-217`), which is just `self::all()[$entity] ?? null` — a **lookup in a hardcoded PHP array**. An unknown key returns `null` and the request dies with "Unknown entity".
- So `$reg['table']` is never the attacker's string; it is *our* string from `src/Content/Registry.php` (e.g. `'table' => 'hero_slides'`, line 33).
- `$field` must be a key of `$reg['fields']` — again our array. Otherwise `$def === null` → rejected.

The **value** still goes through a `?`; only identifiers are interpolated, and only after surviving the whitelist. That is the `CLAUDE.md` invariant — *identifiers only from the registry or code literals; never build SQL from request data* — and `SECURITY.md:20` supplies the probe: POST ``{"entity":"hero_slide","field":"id`; --"}`` must answer `Unknown field`. `item.php` does the same for whole rows, assembling the `INSERT` column list from registry keys only (`public_html/admin/api/item.php:80-82`):

```php
$names = array_keys($cols);
$sql = "INSERT INTO `$table` (`" . implode('`,`', $names) . "`) VALUES (" . implode(',', array_fill(0, count($names), '?')) . ")";
$pdo->prepare($sql)->execute(array_values($cols));
```

`$cols` was filled by looping `$reg['fields']` (lines 54-60), so a payload containing `password_hash` is simply ignored — it is not a registry field, so it never becomes a column. That is mass-assignment protection, SEC-09.

`link.php` needs the same trick for a different map: `Registry::ownerTypes()` (`src/Content/Registry.php:225-235`) whitelists `'facility' => 'facilities'` and friends.

`settings.php` uses no registry at all — `settings` is deliberately **never** registered. It carries its own hardcoded key list (`public_html/admin/api/settings.php:9-25`) and one prepared upsert reused per key (lines 55-58), so MySQL plans the statement once:

```php
$st = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
foreach ($clean as $key => $value) { $st->execute([$key, $value]); }
```

Login is section 2b's query written correctly (`public_html/admin/login.php:19-21`). The password is not in the query at all — it is checked in PHP with `password_verify()` against the stored hash (line 25).

### 4e. Where every call site lives

From `grep -rn "prepare(" src public_html --include='*.php'` and the same for `->query(`:

| File | `prepare()` | `->query()` | Purpose |
|---|---|---|---|
| `src/Content/Repo.php` | 17 | 11 | All public-page reads |
| `public_html/admin/api/link.php` | 8 | 0 | Attach/detach/reorder gallery images |
| `src/Media/Pipeline.php` | 5 | 2 | Upload + rendition bookkeeping |
| `public_html/admin/api/item.php` | 5 | 1 | Create/read/update/delete a row |
| `public_html/admin/api/image.php` | 5 | 0 | Single-image operations |
| `public_html/api/contact.php` | 3 | 0 | Rate-limit check + store enquiry |
| `public_html/admin/login.php` | 3 | 0 | Auth + lockout counters |
| `public_html/admin/password.php` | 2 | 0 | Forced password change |
| `public_html/admin/api/recrop.php` | 2 | 0 | Re-crop an image |
| `public_html/admin/api/images.php` | 2 | 0 | Media-library picker |
| `public_html/admin/api/field.php` | 2 | 0 | Save one field |
| `public_html/admin/section.php` | 1 | 2 | Section editor screen |
| `src/Admin/Audit.php`, `api/settings.php`, `api/order.php` | 1 each | 0 | Audit log, settings upsert, reorder |
| `public_html/admin/index.php` | 0 | 19 | Dashboard `COUNT(*)` tiles |
| `public_html/admin/health.php`, `admin/_layout.php` | 0 | 3, 1 | Health strip, layout chrome |

**58 prepared statements, 39 plain queries.** Every one of those 39 is a code-literal string — e.g. `db()->query('SELECT COUNT(*) FROM hero_slides')->fetchColumn()` (`public_html/admin/index.php:6`). None concatenates request data.

### 4f. The dev query counter

`CLAUDE.md:69` sets a budget: **each converted page runs at most 12 SQL queries.** To see the number, `src/Core/Db.php:13-31` defines two tiny counting subclasses:

```php
class CountingStatement extends PDOStatement
{
    public function execute(?array $params = null): bool
    {
        $GLOBALS['__sj_qcount'] = ($GLOBALS['__sj_qcount'] ?? 0) + 1;
        return parent::execute($params);
    }
}   // CountingPdo does the same for ->query() (lines 22-31)
```

They are wired in **only when `debug` is on** (`src/Core/Db.php:54-61`), so production pays nothing. `db_query_count()` (`src/helpers.php:34-37`) reads the counter and `public_html/bootstrap.php:29-38` appends `<!-- sj-queries: N -->` to the HTML — while refusing to do so on JSON responses (lines 30-34), so it can never corrupt an API reply. Baselines: `docs/perf-baseline.md:26`.

### 4g. Transactions — where we actually use them

A **transaction** means "all of these writes, or none". You open it, write several times, then `commit()`. If anything throws, `rollBack()` undoes everything as if it never happened.

We use transactions in exactly **two** files, both for multi-row writes (`public_html/admin/api/order.php:14-24`):

```php
$pdo->beginTransaction();
try {
    $st = $pdo->prepare("UPDATE `{$reg['table']}` SET position = ? WHERE id = ?");
    foreach (array_values($ids) as $pos => $id) {
        $st->execute([$pos, (int)$id]);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    api_fail('Reorder failed', 500);
}
```

Reordering ten slides is ten `UPDATE`s; without a transaction, a crash after the fourth leaves the gallery half-sorted. `public_html/admin/api/link.php:67-81` (attach) and `:109-119` (reorder) do the same; the attach block even reads the MySQL error code — `if ((int)$e->errorInfo[1] === 1062)`, where 1062 means "duplicate key" — and turns it into "That photo is already in this collection". Everywhere else we deliberately do **not**: `Repo.php` only reads, and `field.php` writes a single row (one statement is already atomic in InnoDB). The one honest gap is in our own risk register — `SECURITY.md:164` lists as a **Low** finding that `item.php` create "computes position outside a transaction", so two simultaneous creates could claim the same position. With one admin editing it has never happened; with several editors it would need fixing.

### 4h. When the database is down

`src/Core/Db.php:62-70`:

```php
} catch (PDOException $e) {
    if (\PHP_SAPI === 'cli') {
        \fwrite(\STDERR, 'Database connection failed: ' . $e->getMessage() . "\n");
        exit(1);
    }
    \http_response_code(503);
    \header('Content-Type: text/plain; charset=utf-8');
    exit("Database is not reachable. Start the stack with ./run.sh and try again.\n");
}
```

Two audiences, two behaviours. On the **command line** (`PHP_SAPI === 'cli'`, i.e. the seeder) we print the driver's real message to stderr and exit non-zero, because a developer is watching. In a **browser** we send HTTP **503 Service Unavailable** and one plain sentence; the exception text is withheld on purpose, because `$e->getMessage()` would leak the host, database name and username to anyone visiting while MySQL restarts. 503 also tells search engines "temporary", not "broken".

## 5. Why this is the right approach here

| Option | What it is | Why not here |
|---|---|---|
| **Eloquent** (Laravel's ORM) | An **ORM** — Object-Relational Mapper — turns rows into objects: `HeroSlide::where('is_active',1)->get()`. | Large dependency tree; its lazy loading is the classic N+1 generator, fighting our 12-query budget. `CLAUDE.md` says **no framework**. |
| **Doctrine ORM** | A heavier, more formal ORM with entity classes and its own query language. | Same weight, plus a real learning curve, on a site with one part-time maintainer. |
| **A query builder** (e.g. Doctrine DBAL) | Compose SQL in PHP: `->select('*')->from('pages')->where('slug = ?')`. | Genuinely nice, but a layer to learn and deploy in exchange for SQL we already write correctly in 97 places. |
| **Raw `mysqli`** | PHP's other MySQL driver. | Works, but `bind_param('sss', …)` is error-prone, results are clumsier, and it locks us to MySQL. |
| **PDO + thin repository** (our choice) | Plain PDO, all reads funnelled through `src/Content/Repo.php`. | Zero dependencies; SQL you can paste straight into the MySQL client; joins written by hand so N+1 cannot sneak in. |

The deciding constraints, all real for this project:

1. **No framework** — an explicit rule in `CLAUDE.md`. PDO ships with PHP; nothing to add.
2. **Shared hosting, no SSH, no Composer at deploy time.** Production is MilesWeb via mPanel; deploying means uploading files, which is exactly why `vendor/` is committed (`public_html/bootstrap.php:19-21`). Every megabyte of ORM is a megabyte someone uploads by hand.
3. **One small team.** The next maintainer may know basic PHP and no framework. `SELECT * FROM pages WHERE slug = ?` needs no documentation.
4. **The ≤12-queries-per-page budget** (`CLAUDE.md:69`). Hand-written SQL makes the count obvious — `Repo::facilities()` is deliberately two queries and says so (`src/Content/Repo.php:191`). ORM magic hides that number.
5. **`SECURITY.md` needs an auditable invariant.** SEC-01's verification step is "grep the diff for `->query(`, string interpolation inside SQL, and confirm every interpolated identifier traces to the registry or a code literal". That audit takes minutes because the SQL is right there in the file.

## 6. How this scales

Today: 27 tables (`database/schema.sql`) and a few hundred rows. What about **10x rows** and **10x traffic**?

**10x rows — indexes carry it.** An **index** is a sorted lookup structure the database keeps beside your data, like the index at the back of a book: instead of reading all 300 pages for "photosynthesis" you jump to page 214. Without one, MySQL does a *full table scan*. Our schema already indexes what we filter and sort on:

| Index | Line | Serves |
|---|---|---|
| `UNIQUE KEY uq_seo_slug (slug)` | `database/schema.sql:66` | `Repo::seo()` slug lookup |
| `KEY idx_page (page_id, position)` | `database/schema.sql:143` | `Repo::heroSlides()` — filter *and* sort from one index |
| `KEY idx_owner (owner_type, owner_id, role, position)` | `database/schema.sql:116` | Every gallery/carousel query |
| `KEY idx_type (type, position)` | `database/schema.sql:244` | `Repo::achievements()` |
| `KEY idx_ip_time (ip, created_at)` | `database/schema.sql:52` | Contact-form rate-limit `COUNT(*)` |

Column order in a composite index matters: `(page_id, position)` lets one index satisfy `WHERE page_id = ? ORDER BY position`. Index lookups grow logarithmically, so 10x rows is nearly a non-event for reads. The tables that will actually grow — `audit_log`, `contact_submissions` — are already indexed on `created_at`.

**10x traffic — connections are the real ceiling.** Shared hosting caps `max_connections` per account, and every concurrent PHP request holds one connection for its whole life. Our singleton already makes that *one per request* instead of one per query, the biggest win available. We avoid persistent connections; on shared hosting they hoard slots rather than save time.

**When a cache or replica would matter**, in order: (1) **per-request caching** — already partly done, since `Repo::setting()` caches the whole `settings` table in a `static` (`src/Content/Repo.php:22-32`), while `Repo::page()` does **not** memoize yet, so calling it twice costs two queries; (2) **a cross-request cache** (APCu, or a generated PHP/JSON file) for `settings`, `pages` and `seo_meta` — data that changes when an admin clicks Save, not per visitor — turning three queries per page into zero; (3) **a read replica**, a second MySQL serving only `SELECT`s, which matters only when one server is CPU-bound, is not offered on shared hosting, and a school site will not need. **MySQL's old query cache does not exist** in MySQL 8.0 (`docker-compose.yml:3`) — it was removed; do not plan around it.

**Our actual next step:** memoize `Repo::page()`; watch `<!-- sj-queries: N -->` on every converted page and keep it under 12; run `EXPLAIN` on any new query filtering an unindexed column; only then consider a cache layer.

## 7. Gotchas and mistakes to avoid

**1. Identifiers cannot be bound.** `prepare('SELECT * FROM ?')` fails — the table must be known when MySQL plans the query. Never solve this by pasting the request string in. Resolve it through `Registry` (4d) or use a code literal. The sneakiest version is a "harmless" sort: `ORDER BY {$_GET['sort']}` is full SQL injection, because MySQL allows subqueries there.

**2. `LIMIT ?` with emulation off.** With `ATTR_EMULATE_PREPARES => false` a bound parameter is sent as a **string** by default, and MySQL rejects `LIMIT '5'` as a syntax error. That is exactly why `src/Content/Repo.php:315-316` writes `LIMIT ' . \max(1, $limit)` — a value already forced to a positive integer by PHP's type system, then interpolated. `public_html/admin/api/images.php:16` does the same:

```php
$sql .= ' ORDER BY id DESC LIMIT ' . ($per + 1) . ' OFFSET ' . ($page * $per);
```

`$per` is the literal `24` (line 8) and `$page` was cast with `max(0, (int)$_GET['page'])` (line 6). The `LIKE` search terms on that same page **are** bound (lines 13-14): values go through `?`, arithmetic does not. The alternative fix is `bindValue($n, $v, PDO::PARAM_INT)`; we chose the cast.

**3. A row might not exist.** `fetch()` returns `false`, not an empty array, when there is nothing — and `$row['title']` on `false` is a fatal error in PHP 8. Our code always checks: `return $row ? self::foldImage($row) : null;` (`src/Content/Repo.php:124-125`), and `?: null` at `src/Content/Repo.php:39`.

**4. `rowCount()` does not mean "found".** MySQL reports 0 affected rows when an `UPDATE` sets a column to the value it already had, so 0 is ambiguous: missing row, or unchanged row? `public_html/admin/api/field.php:19-26` resolves it with a second query, paid only in that rare case:

```php
if (!$st->rowCount()) {
    // Row may exist with an identical value; confirm it exists at all.
    $chk = db()->prepare("SELECT COUNT(*) FROM {$reg['table']} WHERE id = ?");
    $chk->execute([$id]);
    if (!$chk->fetchColumn()) {
        api_fail('Row not found', 404);
    }
}
```

**5. N+1 queries in loops.** "N+1" means 1 query to fetch a list, then N more inside a loop — one per item. Ten facilities become eleven queries; a hundred become a hundred and one. The tempting shape:

```php
// DON'T
foreach (db()->query('SELECT * FROM facilities')->fetchAll() as &$f) {
    $st = db()->prepare('SELECT * FROM images WHERE id = ?');   // one query per row
    $st->execute([$f['bg_image_id']]);
    $f['image'] = $st->fetch();
}
```

Our repositories avoid it two ways. *Pre-join the image:* `Repo::IMG_SELECT` (`src/Content/Repo.php:15-20`) is a `SELECT` fragment aliasing every `images` column as `img_*`; one `LEFT JOIN` brings row and image back together, and `foldImage()` (lines 74-92) splits the `img_*` keys back into a nested `['image' => …]` array. `Repo::heroSlides()` (lines 104-108) is one query for slides *and* their images. *Batch the children with one `IN()`:* `Repo::facilities()` fetches all facilities, collects their ids, and pulls every carousel photo in a **single** second query (lines 191-200), then groups them in PHP. Line 191 says it out loud: `// one batched query for every facility carousel (no N+1)`. `Repo::album()` (lines 244-255) and `Repo::marksBoard()` (lines 322-328) use the same shape — two queries regardless of row count.

**6. Transactions share the connection.** Because `db()` returns one shared PDO object, `beginTransaction()` affects *everything* until commit. Never open one without a `rollBack()` in the `catch` — `order.php:21-24` is the pattern to copy. MySQL also cannot nest transactions.

**7. `query()` is not evil — concatenation is.** `db()->query('SELECT COUNT(*) FROM sports')` is perfectly safe. The rule is not "always prepare"; it is **never let request data into SQL text**. If a variable appears in the string, it needs a placeholder or a registry-resolved identifier.

## 8. Try it yourself

Start the stack from the repo root with `./run.sh`. The credentials below are the throwaway dev ones from `.env.example:10-13` (`DB_NAME=stjosephs`, `DB_USER=stjosephs`, `DB_PASS=stjosephs_pw`); `db` is the service name in `docker-compose.yml`.

**1. List the tables, then inspect one with its indexes.** Find `KEY idx_page (page_id, position)` in the second output and match it to `database/schema.sql:143`.

```bash
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e 'SHOW TABLES;'
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e 'SHOW CREATE TABLE hero_slides\G'
```

**2. Ask MySQL whether it uses the index.** In the output, `key: idx_page` means it did; `NULL` would mean a full scan.

```bash
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs \
  -e "EXPLAIN SELECT * FROM hero_slides WHERE page_id = 1 ORDER BY position;"
```

**3. Turn on the query counter.** Edit `config/config.php` (created for you by `run.sh` from the sample), set `'debug' => true`, then compare the number against the ≤12 budget in `CLAUDE.md:69` and set `debug` back to `false`.

```bash
curl -s http://localhost:8090/ | grep -oE 'sj-queries: [0-9]+'
```

**4. Try the injection probe from `SECURITY.md:20`.** Log in at `http://localhost:8090/admin/`, open devtools, and POST an impossible field name to `/admin/api/field.php`. Because `$reg['fields'][$field]` is a plain array lookup, the answer is `{"ok":false,"error":"Unknown field"}` — the string never reaches SQL.

**5. Watch the 503 path.** You should see `HTTP/1.1 503` and the sentence from `src/Core/Db.php:69`, with no host, user or database name in the output.

```bash
docker compose stop db
curl -i http://localhost:8090/ | head -3
docker compose start db
```

## 9. Where to read more

**In this repo**

- [`../01-fundamentals.md`](../01-fundamentals.md) — the from-zero tour: the web, PHP, databases, SQL, PDO, sessions, Composer, Docker. Read it first if anything above was new.
- [`../02-security.md`](../02-security.md) — every attack we defend against, in plain words, with our own code as the example.
- [`../../../SECURITY.md`](../../../SECURITY.md) — the normative catalog. **SEC-01** is SQL injection, **SEC-09** is mass assignment via the registry, §4 is the 15-item pre-ship checklist.
- [`../../../CLAUDE.md`](../../../CLAUDE.md) — the conventions this document explains: placeholders for values, registry for identifiers, ≤12 queries per page.
- [`../../perf-baseline.md`](../../perf-baseline.md) — where query-count measurements are recorded.

**Outside**

- [PHP manual: PDO](https://www.php.net/manual/en/book.pdo.php) — the reference for every method used above; [PDO::prepare](https://www.php.net/manual/en/pdo.prepare.php) explains officially why parameters resist injection.
- [OWASP: SQL Injection](https://owasp.org/www-community/attacks/SQL_Injection) — the attack, catalogued.
- [OWASP: SQL Injection Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html) — including the "allow-list the identifier" rule our `Registry` implements.
- [MySQL 8.0: Optimization and Indexes](https://dev.mysql.com/doc/refman/8.0/en/optimization-indexes.html) — background for section 6.
