# The Repository Pattern — Where the Queries Live

> **What you'll learn:** why SQL is banned from page templates here, what
> `SJ\Content\Repo` is, what each of its 25 methods returns and who calls it, what
> the "N+1 problem" is and how a JOIN kills it, how per-request caching works, and
> how the "≤ 12 SQL queries per page" budget is measured.
>
> **Prerequisites:** plain PHP only — variables, `if`, `foreach`, functions,
> arrays. Classes and SQL joins are explained the first time they appear.
>
> **Where it lives in our code:** `src/Content/Repo.php` (the class),
> `src/helpers.php:196-326` (the `repo_*()` shortcuts), `public_html/*.php` (the
> callers), `views/**` (the templates that must *not* query).

## 1. The one-paragraph version

A **repository** is one file that owns all the "fetch rows from the database" code
for the public website. Here that file is `src/Content/Repo.php`. Nothing else on
the public side writes SQL. A page like `public_html/index.php` is a **thin
controller** — thin because it holds almost no logic. It calls a few repository
functions, puts the results in an array, and hands that array to the layout
engine. Templates under `views/` then only loop over data that is *already in
memory*. Because every query sits in one file, we can count them, cache them, and
rewrite slow ones without touching a line of HTML.

## 2. The problem this solves

The original site was flat PHP: a page opened a connection, wrote SQL in the
middle of its HTML, and echoed the result inline. That works for one page. At 42
pages it does not:

| Problem | What it feels like |
|---|---|
| The same query written 10 times, slightly differently | You fix a bug in one copy; the other nine keep it |
| Queries hidden inside `foreach` loops in templates | Nobody can tell how many round-trips a page makes |
| Escaping mixed into layout | One forgotten `htmlspecialchars` is an XSS hole |
| Renaming a table means grepping the whole site | Risky, slow, easy to miss a spot |

`CLAUDE.md` states the rule that replaced it: *"each converted page ≤ 12 SQL
queries; cache `settings`/`pages` per request; no N+1 in loops (repos return image
data pre-joined)."* The repository's own header agrees (`src/Content/Repo.php:5-11`):
*"Flat read-path query helpers … Every method returns plain arrays; rows with an
image FK get an `image` sub-array. Query budget: image joins are single-query
(IMG_SELECT + foldImage) and list children are batched IN() lookups — no N+1
loops."* "Read-path" means reading data for a visitor; writing (the admin panel)
takes a different route entirely — section 4.8.

## 3. How it works in general

```
browser  →  public_html/index.php   →  SJ\Content\Repo   →  MySQL
             (thin controller)          (the repository)
                     ↓
             views/pages/home.php   ← plain arrays, no DB access
```

Vocabulary you will meet constantly:

- **Class** — a named box grouping functions; `Repo` is one. A **static method** is
  called on the class itself: `Repo::page('index')`. `::` means "reach inside the
  box"; you never create a `Repo` object.
- **PDO** — PHP's database driver; `db()` (`src/helpers.php:28`) returns the one
  shared connection. A **prepared statement** uses a **placeholder**, the `?` in
  `'… WHERE slug = ?'`: SQL and value travel separately, so user text can never
  become SQL. A **row** is one record as a PHP array, `['id' => 3, …]`.
- **JOIN** — one query that reads two tables and glues matching rows side by side.
  A **foreign key (FK)** is a column holding another table's `id`, e.g.
  `unique_features.image_id`.

Every `Repo` method returns **plain PHP arrays** — no objects, no magic — so a
template only ever needs `$row['title']`.

## 4. How we use it — every place in this codebase

### 4.1 A thin controller, start to finish

`public_html/index.php` is the whole home-page controller, 35 lines:

```php
// Home — thin controller. … ALL queries happen here (none inside views).
require __DIR__ . '/bootstrap.php';
$sj_page      = repo_page('index');
$sj_principal = repo_profile('principal');
$sj_features  = repo_unique_features(is_edit());
$sj_hero_slides = $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [];
…
\SJ\View\Layout::render('home', ['sj_features' => $sj_features, …]);
```

Read the shape, not the detail: **fetch, fetch, fetch, then render.** Every public
page follows it. `public_html/about.php:9-12` loops its fetches
(`foreach (['president', 'principal', 'history', 'rules'] as $roleKey)`).
`public_html/tamilacademy.php:5-9` adds the guard-clause pattern — if
`repo_academy('tamilacademy')` returns nothing it sends `http_response_code(503)`
and exits before rendering. `public_html/gal-annual.php` is 17 lines and fetches
exactly one thing: `repo_album('gal-annual', is_edit())`.

### 4.2 Proof that views don't query

`views/pages/home.php:5-7` says where its data comes from: *"Variables come from
public_html/index.php (the controller): `$sj_page`, `$sj_principal`,
`$sj_features`, …"*. Its busiest block just walks an array it was handed
(`views/pages/home.php:71`): `<?php foreach ($sj_features as $fi => $f): ?>`. Grep
the whole `views/` tree for a database call and you find exactly **one** raw query,
`views/shell.php:73` — wrapped in `if (is_admin())` on line 72, so a visitor never
runs it; it loads the image-preset list for the admin cropper.

**The honest nuance:** two *repository* calls are made from views.

| Call | Where | Why it is allowed |
|---|---|---|
| `repo_seo($sjSlug)` | `views/shell.php:17` | One row, once, for `<title>`/`<meta description>`; only the shell knows the URL slug |
| `repo_setting(...)` | `views/partials/footer.php:4-11`, `contact.php:8-12`, `jumbotron.php:8-10` | Costs **zero** extra queries — the whole `settings` table is cached (4.6) |

The real rule is not "no calls in views"; it is **"a view must never cause an
unbudgeted query"**.

### 4.3 Every public method of `Repo`

25 methods. "Called by" was found by grepping the `repo_*` names across
`public_html/` and `views/`.

| Method (`Repo::`) | Returns | Called by |
|---|---|---|
| `setting($key, $default)` | One string from `settings`, or the default | `views/partials/footer.php`, `contact.php`, `jumbotron.php`; `admin/section.php:712`; `marksBoard()` itself |
| `seo($slug)` | One `seo_meta` row or `null` | `views/shell.php:17` — every page |
| `image($id)` | One `images` row or `null` | `admin/api/item.php:27`, `admin/api/_bootstrap.php:113`, `database/backfill.php:82` |
| `attachImages($rows, $fk)` | Same rows, each given an `image` sub-array, via **one** `IN()` query | No caller left today (see 4.4) |
| `foldImage($row)` | One row with its `img_*` columns folded into `image` | Most methods below; `admin/api/link.php:42` |
| `page($slug)` | One `pages` row or `null` | `index`, `about`, `staffs`, `achievements`, `infrastructure`, `sports`, `kg`, `primary`, `highschl`, `highsec` |
| `heroSlides($pageId, $includeInactive)` | Carousel slides, each with `image` — 1 query | the 10 pages above |
| `testimonials($includeInactive)` | Testimonial rows in order | `index.php:17` |
| `section($slug)` | One `school_sections` row + card image | `kg`, `primary`, `highschl`, `highsec` |
| `sections()` | All school sections + card images | `academics.php:5` |
| `timeline($sectionId)` | `timeline_entries` in order | the 4 section pages |
| `sectionEvents($sectionId)` | `section_events`, each with `image` | the 4 section pages |
| `academy($slug)` | One `academies` row + bg image | the 18 academy pages (`tamilacademy.php`, `ncc.php`, …) |
| `academies($includeInactive)` | All academies + card images | `co-curriculum.php:10` |
| `sports($includeInactive)` | All sports + images | `sports.php:15` |
| `facilities($includeInactive)` | Facilities + bg image **plus a `carousel` array each** — 2 queries | `infrastructure.php:14` |
| `achievements($type, $includeInactive)` | Achievements of one type + images | `achievements.php:14` (`'achievement'`), `:15` (`'award'`) |
| `albums($includeInactive)` | Gallery albums + card images | `gallery.php:5` |
| `album($slug, $includeInactive)` | One album with `years`, each year with `photos` — 3 queries | the 10 `gal-*.php` pages |
| `linkedImages($ownerType, $ownerId, $role)` | One carousel's images, in order | the 18 academy + 4 section pages |
| `profile($roleKey)` | One `profiles` row + portrait | `index.php:8`, `about.php:11`, `staffs.php:10` |
| `uniqueFeatures($includeInactive)` | "What's Unique" blocks + images | `index.php:9` |
| `ticker($includeInactive)` | Scrolling ticker items | `index.php:14` |
| `updateSlides($includeInactive)` | "New updates" slides + images | `index.php:15` |
| `marksBoard($limit, $includeInactive)` | Latest N years, pre-grouped by standard 12/11/10 | `index.php:16`, `highsec.php:27` |

Every entry in the right column is a controller or an admin screen. Not one is a
`views/pages/*.php` body.

### 4.4 The `repo_*()` wrappers in `src/helpers.php`

25 global functions that do nothing but forward the call
(`src/helpers.php:233-236`):

```php
function repo_hero_slides(int $pageId, bool $includeInactive = false): array
{
    return Repo::heroSlides($pageId, $includeInactive);
}
```

Why bother? The file header says (`src/helpers.php:2-5`): *"Global helper functions
— the historic names every view, controller and admin endpoint calls. Loaded via
Composer's `files` autoload … Each delegates to its `SJ\` class."* So: **history**
(these names predate the classes, so the R2 refactor did not have to touch dozens
of page files), **ergonomics** (`repo_page('index')` needs no `use` line and no
namespace knowledge), and **one place to change** if `Repo` moves. Line 201 also
exports the image-column list for the admin API (`admin/api/link.php:36`):
`const SJ_IMG_SELECT = Repo::IMG_SELECT;`. **A real leftover:**
`Repo::attachImages()` (`src/Content/Repo.php:50`) and `repo_attach_images()` have
**zero callers** outside `src/helpers.php` today — their last two users became
JOINs (4.6). They stay because they are still the right tool for rows you did not
fetch yourself.

### 4.5 N+1, taught from scratch

The naive way to render 8 feature blocks with their photos:

```php
$features = query('SELECT * FROM unique_features');   // 1 query
foreach ($features as $f) {
    $f['image'] = query('SELECT * FROM images WHERE id = ' . $f['image_id']); // 1 EACH
}
```

That is **1 + N queries**. Eight features → 9. Two hundred fifty album photos →
251. This is the **N+1 problem**: cost grows with content, and it is invisible
because the extra queries hide inside a loop.

**Cure 1 — the JOIN, for "each row has one image".** A JOIN glues the matching
`images` row onto each content row inside the same query. Both tables have an `id`
column, so we rename every image column once, in a constant
(`src/Content/Repo.php:14-20`):

```php
/** SELECT fragment aliasing every images column as img_* (for one-query joins). */
public const IMG_SELECT = 'i.id AS img_id, i.legacy_path AS img_legacy_path,
    i.original_name AS img_original_name, … i.updated_at AS img_updated_at';
```

`AS img_id` means "call this column `img_id` in the result". One flat row now
carries both. `foldImage()` unflattens it (`src/Content/Repo.php:73-92`) — pure
array work, **no database access, so it is free**:

```php
foreach ($row as $k => $v) {
    if (\strncmp($k, 'img_', 4) === 0) { $image[\substr($k, 4)] = $v; }
}
… $row['image'] = $image;  return $row;
```

`strncmp($k, 'img_', 4) === 0` asks "does this key start with `img_`"; `substr($k, 4)`
strips the prefix. The finished pattern (`src/Content/Repo.php:104-108`):
`LEFT JOIN` = "attach the image if there is one, keep the slide anyway if there
isn't", and `array_map` runs `foldImage` over every row, so **N+1 → 1**:

```php
$sql = 'SELECT h.*, ' . self::IMG_SELECT . ' FROM hero_slides h LEFT JOIN images i ON i.id = h.image_id
        WHERE h.page_id = ?' . ($includeInactive ? '' : ' AND h.is_active = 1') . ' ORDER BY h.position, h.id';
return \array_map([self::class, 'foldImage'], $st->fetchAll());
```

**Cure 2 — the batched `IN()`, for "each row has many children".** A JOIN cannot
help when one facility owns ten carousel photos, so we collect the parent ids and
ask for all children at once (`src/Content/Repo.php:60-62`): `$in = implode(',',
array_fill(0, count($ids), '?'));` builds `?,?,?` — one placeholder per id, so
values still travel separately from the SQL — and a single
`SELECT … WHERE id IN ($in)` fetches the lot. Same trick in `facilities()`
(`:191-208`, comment *"one batched query for every facility carousel (no N+1)"*),
`album()` (`:243-259`) and `marksBoard()` (`:320-329`).

### 4.6 Per-request caching, and the ≤ 12 budget

`Repo::setting()` is called from the footer, contact block, jumbotron and
`marksBoard()`, so the first call reads the **whole table** and keeps it
(`src/Content/Repo.php:22-32`):

```php
static $cache = null;
if ($cache === null) {
    $cache = [];
    foreach (db()->query('SELECT skey, svalue FROM settings') as $r) {
        $cache[$r['skey']] = $r['svalue'];
    }
}
return $cache[$key] ?? $default;
```

`static $cache` means **this variable survives between calls, inside this one PHP
process** — so the `if` body runs exactly once. `SJ\Media\Pipeline` does the same
twice: `preset()` caches `image_presets` (`src/Media/Pipeline.php:22-32`), and
`renditions()` (`src/Media/Pipeline.php:34-48`) caches the rendition table with the
identical `static $all = null;` shape. Its doc comment gives the reason: *"F2: the
whole table is loaded ONCE per request (a few hundred small rows) instead of one
query per image — an album page renders ~250 photos and would otherwise blow the
≤12-query budget."* That is an N+1 fix by caching instead of joining.

**Why it is safe.** A PHP request is one short-lived process. Browser asks → PHP
starts → PHP runs → PHP prints HTML → **PHP exits and every variable, including
every `static`, disappears.** The next visitor gets an empty cache. These caches
can never serve stale content and never need invalidating. They are *not* a
cross-user cache like Redis. One request, one process, cache dies at the end.

**Measuring the budget.** `CLAUDE.md` says to check with `config['debug']=true` →
the `<!-- sj-queries: N -->` comment (`db_query_count()`). Three pieces:
`src/Core/Db.php:13-31` swaps PDO for `CountingPdo`/`CountingStatement` when debug
is on — subclasses whose only job is
`$GLOBALS['__sj_qcount'] = ($GLOBALS['__sj_qcount'] ?? 0) + 1;` before the real
query; `src/helpers.php:33-37` reads the number back; and
`public_html/bootstrap.php:29-38` appends
`echo "\n<!-- sj-queries: " . db_query_count() . " -->";` at shutdown, returning
early for JSON so API output is never corrupted. With debug off, plain `PDO` is
used and the counter stays 0 — production pays nothing.

**The real story.** `docs/perf-baseline.md:30` recorded the start: home at **16**,
"Over the ≤12 budget". Repository work (caching `settings`/`pages`, pre-joined
images) got it to 12. Then two features each added one permanent query per
request: **F2** preloads `image_renditions`, **F3** reads one `seo_meta` row in the
shell. 12 + 2 = **14**, over budget again. Commit `a973580` fixed it: *"Repo:
uniqueFeatures/updateSlides image via JOIN — reclaims the two queries the
renditions preload + seo lookup cost; home back to exactly 12."* Both had used
`attachImages()` — a list query **plus** an image query, 2 each; as JOINs they cost
1 each, and the note survives at `src/Content/Repo.php:290-291`. `docs/perf-baseline.md:73`
and `docs/learn/08-stage-h.md:325` both record **16 → 12**. Counted by hand from
the code, home's twelve are: `pages`, `profiles`+image, `unique_features`+image,
`hero_slides`+image, `ticker_items`, `update_slides`+image, `settings`,
`mark_years`, `mark_entries`, `testimonials`, `seo_meta`, `image_renditions`.

### 4.7 `includeInactive` — one flag, two audiences

The home controller passes `is_edit()` everywhere (`public_html/index.php:9`):
`$sj_features = repo_unique_features(is_edit());`. Inside the method the flag only
decides whether the `is_active = 1` filter is added (`src/Content/Repo.php:292-293`):

```php
$sql = 'SELECT u.*, ' . self::IMG_SELECT . ' FROM unique_features u LEFT JOIN images i ON i.id = u.image_id'
     . ($includeInactive ? '' : ' WHERE u.is_active = 1') . ' ORDER BY u.position, u.id';
```

`is_edit()` (`src/helpers.php:64`) is true only for a logged-in admin who switched
edit mode on. A **visitor** gets `false` — hidden rows are never fetched, so they
cannot leak. An **admin in edit mode** gets `true` — hidden rows come back, the view
marks them (`views/pages/home.php:72` emits `sj-inactive`) and one CSS rule fades
them (`public_html/css/admin.css:89`:
`.sj-edit-mode .sj-inactive { opacity: .45; }`). That is how an admin sees a hidden
slide and can switch it back on. The flag is **not** universal: `sections()`,
`timeline()`, `sectionEvents()`, `section()`, `academy()`, `profile()`, `page()`,
`linkedImages()` and `seo()` take no such parameter — those tables have no
`is_active` column.

### 4.8 The admin side does *not* use the repository

Reading is one shape; writing is another. The admin writes through a
**registry-driven API**. `src/Content/Registry.php:5-11`: *"THE entity registry —
the single authority the admin API uses to resolve tables, columns and types …
Request payloads can never name a table or column directly. `admin_users` and
`settings` are NEVER registered."* A save sends `{entity, id, field, value}` —
names like `unique_feature`, never `unique_features`. The endpoint resolves the
entity via `api_entity($entity)`, validates with `api_validate_field(...)`, and only
then runs `db()->prepare("UPDATE {$reg['table']} SET `$field` = ? WHERE id = ?")`
(`public_html/admin/api/field.php:10-17`) — table and column names from the
registry (code we wrote), never the request. Every endpoint first requires
`admin/api/_bootstrap.php`, which enforces login (`:23-25`) and a CSRF token on
every non-GET (`:31-36`).

Why not route this through `Repo`? **Different shape** — `Repo` methods are
hand-written per page section, while the admin must edit any registered field of
any registered entity (generic, table-driven code). **Different rules** — writes
need CSRF, auth, per-type validation, HTML sanitising and an audit log; none of
that belongs in a read helper. **Different budget** — the 12-query budget protects
public page loads; saving one field is a single small POST. The admin *does* reuse
the repository for read-only listing: `admin/section.php` calls
`repo_unique_features(true)`, `repo_albums(true)`, `repo_facilities(true)` and
friends, always with `includeInactive = true`.

## 5. Why this is the right approach here

| Option | What it is | Why not here |
|---|---|---|
| **SQL inline in templates** (the old site) | Query wherever you need data | Query count becomes unknowable and unbudgetable; the same SELECT gets copy-pasted; escaping and layout tangle |
| **An ORM** (Eloquent, Doctrine) | A library turning rows into objects and writing SQL for you | `PHASES.md` locks in "no framework"; a large dependency to hand-upload to shared hosting; lazy-loaded relations *create* N+1 by default — the exact bug we budget against; and it demands OOP knowledge first |
| **A query builder** (`$q->table('sports')->where(…)`) | Fluent SQL construction, no objects | Real value, but these queries are short and hand-tuned to the budget; it would add a dependency and hide the JOINs we specifically want visible |

Against the actual constraints: **no framework** (`CLAUDE.md`: "structured plain
PHP with Composer PSR-4"); **≤ 12 queries per page** — only a design where all
queries sit in one file makes that number auditable; **pixel-freeze** — converted
pages must render identically to the baseline, and moving queries out of templates
changes *zero* output bytes, which is exactly why it was safe during the freeze;
**one editor** — a handful of admins, so per-request `static` caches suffice and no
Redis is needed; **shared hosting** (MilesWeb, no SSH) where `vendor/` is committed
and uploaded by file transfer, so every dependency is megabytes you upload by hand.
The repository pattern is the smallest thing that satisfies all five.

## 6. How this scales

**At 10× the content**, most of the model does not move. "One query per page
section" is bounded by the *number of sections*, not the number of rows: twelve
sections stay twelve queries whether each holds 5 rows or 500.

What breaks first: **album pages.** `Repo::album()` fetches *every* year and
*every* photo in 3 queries (`src/Content/Repo.php:231-262`). The query count stays
3; the payload does not — `src/Media/Pipeline.php:37` already notes "an album page
renders ~250 photos". At 2,500 the HTML and image requests, not the SQL, are the
problem, and `docs/perf-baseline.md:97-99` already lists album grids as a known
straggler. Second is the **renditions preload** — one query for the whole table is
a great trade at a few hundred rows, wasted memory at tens of thousands.
`marksBoard()` is already self-limiting: its `LIMIT` comes from the
`marks_years_shown` setting (`src/Content/Repo.php:314`).

The next steps, in order. **Pagination first** — give `album()` a `$limit`/`$offset`
(or per-year lazy loading) so one page renders one year; cheapest change, biggest
win. **Then an index** — a lookup structure MySQL keeps so it can find rows without
scanning; the hot one is `image_links(owner_type, owner_id, role, position)`, and
since migrations are additive-only (`CLAUDE.md`), adding an index is additive.
**Then a cached fragment** — render the album grid HTML once and store it, rebuilt
when an editor saves; that adds an invalidation problem, which is why it is last.

## 7. Gotchas and mistakes to avoid

**1. Querying inside a view or partial.** It works, so nothing shouts at you — but
it breaks the budget silently and hides the cost from whoever reads the
controller. If a partial needs data, the controller fetches it and passes it in;
that is what `public_html/index.php:11` means by *"Section data (previously
self-queried by the nested templates)"*. If you must read from a view, it had
better be cached like `repo_setting()`.

**2. Forgetting `includeInactive`.** Passing nothing on a page an admin edits →
the admin cannot see or re-enable hidden rows. Passing bare `true` on a page a
visitor can reach → **hidden content leaks into public HTML.** Always pass
`is_edit()` on a public page, never a literal `true`.

**3. Returning rows without their image data.** If a new method returns bare rows
and the template then calls `repo_image($row['image_id'])` in a loop, you have
written an N+1. Use `IMG_SELECT` + `foldImage()` for one-image-per-row, or
`attachImages()` / a batched `IN()` for many.

**4. `static` caches leaking between tests.** A `static` lives for the whole PHP
process — one page in a web request, so it is safe there. In a CLI script or test
runner doing several "requests" in one process, the first run's `settings` and
`image_renditions` are still there for the second, so a test that updates a
setting and re-reads it sees the **old** value. Only `Pipeline::renditions()` has
an escape hatch, its `$refresh` parameter (`src/Media/Pipeline.php:50-57`);
`Repo::setting()` and `Pipeline::preset()` have none. One process per scenario.

**5. Building SQL from request data.** Never. Values go in as `?` placeholders;
table and column names come only from the registry or code literals (`CLAUDE.md`).

## 8. Try it yourself

Start the stack with `./run.sh` (public site on `http://localhost:8090/`).
**Step 1 — turn the counter on:** in `config/config.php` (above the webroot,
gitignored) set `'debug' => true,`. **Step 2 — count a page**, with the command
from `docs/perf-baseline.md:58-59`:

```bash
curl -s "http://localhost:8090/index.php" | grep -oE 'sj-queries: [0-9]+'   # expect 12
for p in index about tamilacademy gal-annual infrastructure; do
  printf '%-16s ' "$p"; curl -s "http://localhost:8090/$p.php" | grep -oE 'sj-queries: [0-9]+'
done
```

**Step 3 — find where a page's queries come from.** Read the controller top to
bottom; each `repo_*()` call maps to a row in the 4.3 table. `gal-annual.php` makes
one call, `repo_album('gal-annual', is_edit())`, which 4.3 says costs 3 queries.
Add the shell's `repo_seo`, the `settings` read and the `image_renditions` preload
to predict the total, then check your prediction. For a bonus, run
`git show a973580 -- src/Content/Repo.php` — the exact diff where
`uniqueFeatures()` moved from `attachImages()` (2 queries) to a JOIN (1).

**Step 4 — turn debug back off.** Set `'debug' => false` again. Leaving it on adds
a comment to every response and keeps the counting PDO subclasses in play.

## 9. Where to read more

- `../01-fundamentals.md` — §H covers PHP + PDO from zero; §H.5 walks a repository
  function line by line.
- `../05-stage-e.md` — the live-edit overlay, where `is_edit()` and
  `includeInactive` meet.
- `../08-stage-h.md` — performance: LCP/CLS, renditions, and the 16 → 12 query
  table at line 325.
- `../../../CLAUDE.md` — query budget, SQL/output conventions, visual freeze.
- `../../../PHASES.md` — the architecture and phase order that produced this design.
- `src/Content/Repo.php` — 345 lines. Read it all once; best hour you can spend here.

External: PHP prepared statements
<https://www.php.net/manual/en/pdo.prepared-statements.php> · MySQL JOIN syntax
<https://dev.mysql.com/doc/refman/8.0/en/join.html> · Martin Fowler, *Repository*
<https://martinfowler.com/eaaCatalog/repository.html>
