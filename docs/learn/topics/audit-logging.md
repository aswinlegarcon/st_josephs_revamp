# Audit Logging

> **What you'll learn:** what an *audit log* is, how it differs from the two other kinds of logging you will meet, why a school CMS needs one, the exact table we use, the one class that writes to it, every place in this codebase that calls it, what we deliberately refuse to record, and how to read the trail.
>
> **Prerequisites:** you can read a PHP function, you know that `?` in a SQL string is a placeholder, and you know that `$_SESSION` remembers who is logged in. (If not, read [`../01-fundamentals.md`](../01-fundamentals.md) first.)
>
> **Where it lives in our code:** `src/Admin/Audit.php` (the writer), `src/helpers.php:98` (the shortcut function), `database/schema.sql:27` and `database/migrations/002_audit_log.sql` (the table), and 18 call sites across `public_html/admin/`.

## 1. The one-paragraph version

An **audit log** is a permanent, append-only record of *who changed what, when, and from where*. Here it is a single MySQL table called `audit_log`. Every time an administrator logs in, fails to log in, changes their password, turns on edit mode, edits a field, uploads a photo, reorders a list, or deletes something, one row is appended. The row is written by exactly one function — `SJ\Admin\Audit::log()` in `src/Admin/Audit.php` — which you normally reach through the short wrapper `sj_audit()`. Nothing in the app ever reads it; you read it by hand with SQL when you need to answer "who changed the homepage photo on Tuesday?"

## 2. The problem this solves

The school office is a real place with real people, and two situations will actually happen.

**The ordinary one.** A parent phones: "the exam date on your website is wrong." Someone edited it. Without a record, the only honest answer is "we don't know when it changed or who changed it." With a record you run one query and see: `field.save`, entity `ticker`, id 3, by admin 1, at 14:02 Tuesday, from IP 49.207.x.x.

**The bad one.** A password leaks. You now need **incident response**: a precise answer to "what did the attacker touch?" The audit log is the only thing that can tell you *which rows to restore* from the backup. Without it you either restore the whole site to yesterday (losing real work) or leave hidden damage in place.

`SECURITY.md` tracks this as **SEC-19 Audit logging**, mapped to OWASP category **A09 (Security Logging and Monitoring Failures)**. Its original status line was blunt — `SECURITY.md:121` reads "**Status: 🔴** absent entirely (no login log, no change history, no upload log)." Phase **S4** in `PHASES.md:70` closed it.

## 3. How it works in general

The word "log" is overloaded. Three different things are called logging, with different owners and different readers. This doc is only about the third.

| Kind | Who writes it | Who reads it | Typical content | In our project |
|---|---|---|---|---|
| **Application log** | your PHP code, or the PHP runtime | the *developer*, while debugging | exceptions, stack traces, warnings | PHP's `error_log` (`SECURITY.md` SEC-16) |
| **Access log** | the web server (Apache), automatically | ops, for traffic and abuse patterns | one line per HTTP request: IP, path, status, bytes | Apache's own file in the `web` container; we don't manage it |
| **Audit log** | your application, *deliberately*, at meaningful moments | the site owner, and an investigator after an incident | "admin 1 deleted ticker item 3 at 14:02 from 49.207.x.x" | the `audit_log` table — **this document** |

Three distinctions worth memorising:

1. **An application log records failures; an audit log records successes.** A successful delete is invisible in the error log — it worked. That is exactly the event you want.
2. **An access log records requests; an audit log records intent.** The access log knows `POST /admin/api/index.php?r=field` returned 200. It cannot know *which field on which page* changed, because that was in the JSON body.
3. **An audit log is evidence, so it is never edited.** Code only ever `INSERT`s. There is no `UPDATE audit_log` anywhere — check with `grep -rn "audit_log" --include="*.php" .`

## 4. How we use it — every place in this codebase

### 4.1 The table

Two files define it, deliberately. `database/schema.sql` is what a **fresh** install gets; `database/migrations/002_audit_log.sql` is what you paste into mPanel's database tool to add it to an **existing** production database. Both create the same table; the migration carries the comments:

```sql
CREATE TABLE IF NOT EXISTS audit_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id   TINYINT UNSIGNED NULL,                 -- NULL for failed logins (unknown user)
  action     VARCHAR(40)  NOT NULL,                 -- login.ok | login.fail | logout | password.change
                                                    -- | field.save | item.create|update|delete | order.save | upload
  entity     VARCHAR(40)  NULL,
  entity_id  INT UNSIGNED NULL,
  detail     VARCHAR(500) NOT NULL DEFAULT '',
  ip         VARCHAR(45)  NULL,                     -- IPv4/IPv6; never any secret
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_admin_when (admin_id, created_at),
  KEY idx_when (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
— `database/migrations/002_audit_log.sql:5`

| Column | Why it exists |
|---|---|
| `id` | Auto-incrementing row number. Gives every event a stable name you can quote ("audit log id 89") and orders events that share a second. |
| `admin_id` | **Who** — the `admin_users.id`. Nullable on purpose: a failed login has no known user, so there is nobody to point at. `TINYINT UNSIGNED` (max 255) because this school will never have 255 admins. |
| `action` | **What kind of thing happened**, as a short dotted string like `field.save`. Short strings mean `WHERE action = 'login.fail'` needs no join. |
| `entity` | **What kind of thing was touched** — `ticker`, `hero`, `image`, `settings`. Nullable, because `logout` touches nothing. |
| `entity_id` | **Which one** — the row id. With `entity` it identifies exactly one record. |
| `detail` | A short free-text note, capped at 500 chars, for what the columns above can't hold: a field *name*, a crop rectangle, a count. |
| `ip` | **From where.** 45 chars is the maximum length of a text IPv6 address, so it fits both v4 and v6. |
| `created_at` | **When.** `DEFAULT CURRENT_TIMESTAMP` means *MySQL* stamps it — PHP never sends a time, so a wrong clock in the app can't produce a wrong timestamp. |

The two indexes match the only two questions anyone asks. `idx_when (created_at)` — "what happened recently?" `idx_admin_when (admin_id, created_at)` — "what did *this* admin do recently?" Column order matters: the second index also serves a plain `WHERE admin_id = 1`, but it cannot serve a bare `WHERE created_at > …`, which is why the first one exists. Note what is **not** there: no `ua` (user-agent) column. `SECURITY.md:122` proposed one; the shipped table dropped it. Don't read plan documents as if they were code.

### 4.2 The writer

The whole implementation is nineteen lines:

```php
public static function log(string $action, ?string $entity = null, ?int $entityId = null, string $detail = ''): void
{
    try {
        $st = \db()->prepare(
            'INSERT INTO audit_log (admin_id, action, entity, entity_id, detail, ip) VALUES (?,?,?,?,?,?)'
        );
        $st->execute([
            !empty($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            $action,
            $entity,
            $entityId,
            \mb_substr($detail, 0, 500),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (\Throwable $e) {
        // Swallow — auditing must never surface an error or abort the action.
    }
}
```
— `src/Admin/Audit.php:11`

Four deliberate decisions:

1. **The caller never passes "who" or "from where".** `admin_id` is read from `$_SESSION['admin_id']` — the value `login.php:29` set at sign-in — and the IP from `$_SERVER['REMOTE_ADDR']`, which the *web server* fills in. A caller cannot forge either, and no forgetful call site can produce an anonymous row.
2. **`?` placeholders for every value**, same rule as everywhere else in this repo. An action string containing a quote is data, not syntax.
3. **`mb_substr($detail, 0, 500)`** truncates in PHP to match `VARCHAR(500)`. Without it an over-long detail would make a *logging* problem look like an *editing* problem.
4. **`catch (\Throwable $e) { }` — the empty catch.** This looks like the classic beginner mistake; here it is the point. `Throwable` is PHP's base type for every error and exception. Catching it and doing nothing means *if the audit insert fails, the user's actual edit still succeeds.* Logging is a passenger, never the driver (`src/Admin/Audit.php:26`).

**History.** This code was born in phase **S4** (commit `bd28b96`) as a plain function in `public_html/_libs/audit.php`, whose header read "Procedural for now; ported to `SJ\Admin\Audit::log()` in P2." The port happened in commit `853e73b` ("[P2\*] Port clean libraries to SJ\ classes"), and `_libs/` is now gone entirely. The function *body* did not change — only where it lives. That is what a clean refactor looks like.

### 4.3 The wrapper

Call sites write `sj_audit(...)`, not `\SJ\Admin\Audit::log(...)`:

```php
function sj_audit(string $action, ?string $entity = null, ?int $entityId = null, string $detail = ''): void
{
    Audit::log($action, $entity, $entityId, $detail);
}
```
— `src/helpers.php:98`

It exists so the S4-era procedural call sites kept working unchanged after the P2 port. Use `sj_audit()` in page and API code; the class is the implementation.

### 4.4 Every call site

There are **18** calls to `sj_audit()`, producing **19** distinct action strings. This table is complete — regenerate it with `grep -rn "sj_audit(" --include="*.php" .` (all paths are under `public_html/`).

| File:line | `action` | `entity` / `entity_id` | `detail` | What triggers it |
|---|---|---|---|---|
| `admin/login.php:38` | `login.ok` | — | — | Correct password accepted, session started |
| `admin/login.php:52` | `login.fail` | `null` / `null` | first 50 chars of the typed username | *Any* failed sign-in — wrong user, wrong password, or locked account |
| `admin/logout.php:12` | `logout` | — | — | The POST+CSRF logout form, while actually logged in |
| `admin/password.php:41` | `password.change` | — | — | A password change succeeded (forced or self-service) |
| `admin/editmode.php:19` | `editmode.on` / `editmode.off` | — | — | The admin bar's edit-mode toggle |
| `admin/api/field.php:27` | `field.save` | entity / row id | the **field name** | One field saved, from the panel or the on-page overlay |
| `admin/api/item.php:84` | `item.create` | entity / new id | — | A new row added (a slide, a ticker item…) |
| `admin/api/item.php:106` | `item.update` | entity / row id | — | A multi-field row form saved |
| `admin/api/item.php:116` | `item.delete` | entity / row id | — | A row deleted |
| `admin/api/order.php:25` | `order.save` | entity / — | — | A drag-and-drop reorder committed |
| `admin/api/upload.php:20` | `upload` | `image` / new image id | — | A photo finished the upload pipeline |
| `admin/api/link.php:83` | `link.attach` | owner type / owner id | `role=… image=…` | A photo attached to an album or carousel |
| `admin/api/link.php:93` | `link.detach` | owner type / owner id | `role=… link=…` | A photo removed from a collection |
| `admin/api/link.php:120` | `link.reorder` | owner type / owner id | `role=… n=<count>` | Photos within a collection reordered |
| `admin/api/recrop.php:43` | `image.recrop` | `image` / image id | the crop rectangle `x,y,w,h` | Re-crop, renditions regenerated |
| `admin/api/image.php:61` | `image.meta` | `image` / image id | `alt_text` | Alt text edited in the media library |
| `admin/api/image.php:86` | `image.delete` | `image` / image id | legacy path or original filename | A photo deleted from the media library |
| `admin/api/settings.php:60` | `settings.save` | `settings` / `null` | the comma-joined **key names** saved | Site settings (phone, email, timings…) saved |

The pattern is exact: **every mutation is audited; no read is.** `api/images.php` (the GET media browser), `item.php`'s `get` case (`item.php:13`) and `image.php`'s `usage` case (`image.php:64`) write nothing. Reads are not accountability events, and logging them would bury the ones that are. `SECURITY.md:123` states the rule for future work: "every new mutating endpoint calls `Audit::log()`".

### 4.5 What we deliberately do NOT log

| Never recorded | Why |
|---|---|
| **Passwords** — old, new, or hashed | The point of `password_hash()` is that the plaintext exists for microseconds. Copying it into a table nobody watches would undo that. `password.change` records *that* it happened, never *what* it became. |
| **Session ids / CSRF tokens** | A session id *is* the login. Anyone who could read the table could impersonate an admin. There is no session column at all. |
| **The full field value** | `field.save` records the field's **name**, not its old or new text (`field.php:27` passes `$field`). A log holding every value would quietly become a second, unprotected copy of the whole website. |
| **The full username on a failed login** | `login.php:52` writes `mb_substr($username, 0, 50)`. The login box is a public form, so anyone on the internet can write into `detail`; capping the length bounds what a bot can stuff in. The truncated value is kept because repeated `login.fail` rows against a *real* username mean something very different from ones against `root`. |

The class docblock states the policy at `src/Admin/Audit.php:6`: "secrets (passwords, tokens, hashes) are never recorded."

### 4.6 How to read it

There is **no screen for this**. You read the table with SQL.

```sql
-- the last 20 things that happened
SELECT id, admin_id, action, entity, entity_id, detail, ip, created_at
FROM audit_log ORDER BY id DESC LIMIT 20;

-- only failed logins in the last week (the alarm query)
SELECT created_at, ip, detail FROM audit_log
WHERE action = 'login.fail' AND created_at > NOW() - INTERVAL 7 DAY ORDER BY id DESC;

-- everything one admin did on one day
SELECT * FROM audit_log
WHERE admin_id = 1 AND created_at >= '2026-08-08' AND created_at < '2026-08-09' ORDER BY id;
```

**A normal day:** one `login.ok`, a handful of `field.save` / `item.update` rows clustered within a few minutes, maybe an `upload`, then a `logout` — all from one IP. **Abnormal:** `login.fail` rows from an IP you don't recognise; a `login.ok` at 3 a.m.; an `item.delete` you can't account for; `field.save` rows from two IPs interleaved.

**A real example from this project.** During R3 verification the pixel-comparison harness reported that the home page's news ticker had changed — impossible under the visual freeze. The audit log settled it in one query: a genuine `login.ok`, the forced `password.change`, a `settings.save`, `editmode.on`, and then a ticker reorder made through the Stage-F live-edit overlay. The site's owner was using the CMS for real, minutes after it was built. `PHASES.md:136` records the row that proved it: "the owner's own live ticker reorder made through the O2 overlay mid-session (**audit log id 89**)." A supposed regression was correctly identified as a legitimate edit, and nothing was reverted.

### 4.7 Retention — an honest gap

**We do not prune the table today.** Nothing deletes from `audit_log` anywhere in this codebase. `SECURITY.md:122` prescribes "prune >90 days" and that is not implemented. The one-line policy to add, as a monthly cron entry alongside the nightly `database/backup.sh`:

```sql
DELETE FROM audit_log WHERE created_at < NOW() - INTERVAL 90 DAY;
```

Ninety days balances two things: long enough that a compromise found weeks late is still investigable, short enough that IP addresses (personal data in many jurisdictions) are not kept forever.

### 4.8 How this relates to backups and monitoring

**Backups.** A trail saying "admin 1 deleted ticker item 3" is only useful if you can get item 3 back. `database/backup.sh` takes a nightly `mysqldump` plus a weekly `media/` archive into a **non-web** directory with 14-day retention. The two are a pair: the audit log tells you *what to restore*, the backup provides *the bytes*. Note the tension — backups keep 14 days, proposed audit retention is 90. That is intentional: knowing an incident happened outlives the ability to undo it, and knowing is still worth having.

**Monitoring.** `/admin/health.php` and the dashboard's health strip (`public_html/admin/index.php:45`) answer "is the site healthy *right now*" — disk free, DB reachable, last backup age. The audit log answers "what happened *earlier*". Live signal versus history; neither substitutes for the other.

## 5. Why this is the right approach here

| Alternative | Why not, here |
|---|---|
| **A text file log** (`audit.log` on disk) | On MilesWeb shared hosting there is no SSH — reading a file means FTP or the mPanel file browser, and *filtering* it means downloading the whole thing. Any log file inside `public_html` is also an exposure risk (`SECURITY.md` SEC-20). The DB is already there, already backed up, already queryable. |
| **A hosted log service** (Datadog, Papertrail…) | Needs log shipping over the network, an API key in the app, a paid account, and an outbound dependency on every admin action. There is no log-shipping infrastructure on this host and no budget for one. |
| **Database triggers** (`AFTER UPDATE` on content tables) | Triggers catch *rows*, not *people*. MySQL has no idea which admin's session made the change, so `admin_id` — the single most valuable column — would be unavailable. Triggers are also invisible to anyone reading the PHP. |
| **Full row-versioning / undo** | Genuinely better, and much bigger: a shadow table per entity or a generic diff store, a UI, and a restore path that respects foreign keys. For **one editor** on a 42-page brochure site, the honest trade is "know what changed, restore from the nightly backup if needed". Nothing blocks adding it later. |

The constraints that decided it: **shared hosting** (no SSH, no log agents), **no log shipping**, **one editor** (volume and contention are trivial), and the clincher — **the trail must be readable from mPanel's database tool**, the only query interface the site owner will ever have.

## 6. How this scales

Suppose editing goes up 10× — a real editorial team instead of one person.

**Table growth.** Each row is tiny: a few integers, two short strings, at most 500 bytes of `detail`. Even at 200 rows a day that is ~70,000 rows a year and single-digit megabytes. MySQL does not notice. `INSERT` cost stays flat regardless of table size.

**Query speed.** `idx_when` keeps "last 50 events" instant no matter how big the table gets; `idx_admin_when` does the same per admin. Neither index needs changing.

The pressure shows up elsewhere: **nobody can read it.** Today the only way to see the trail is to open a database tool and type SQL. A site owner will not do that, so in practice the log is written and never read — evidence after an incident, but no day-to-day value.

**The exact next step: build a read-only audit viewer in the admin panel.** There is no audit UI today; that is the honest gap. Concretely, add `public_html/admin/audit.php` following the other panel pages — `require __DIR__ . '/_layout.php'`, `panel_header(...)`, one paginated `SELECT … ORDER BY id DESC LIMIT ? OFFSET ?` joined to `admin_users` for the display name, every column printed through `e()`, filters for action and date. Read-only: no delete or edit path. Add the 90-day prune from §4.7 at the same time, so the viewer never pages through years.

## 7. Gotchas and mistakes to avoid

1. **Never log a secret.** The tempting version of `field.save` records old and new values "for undo". Do that, and the day an editor pastes a phone number, a password, or exam results into a text field, your log is the least protected copy of the most sensitive data on the site. Log *identifiers*, not *contents*.
2. **Don't log everything.** A log that records page views and every API read is a log nobody reads, and the one `item.delete` that mattered is buried under ten thousand rows. Our rule — mutations only — keeps a day's activity scannable by a human.
3. **Never let logging break the request.** If `Audit::log()` re-threw, a full disk or a locked table would turn "save my edit" into a 500, and an edit that already succeeded would look like it failed. The empty `catch (\Throwable $e)` at `src/Admin/Audit.php:25` is deliberate — keep it. The accepted trade-off is real: a logging outage is silent.
4. **Don't trust the client IP blindly.** `$_SERVER['REMOTE_ADDR']` is the address of whatever machine opened the TCP connection — trustworthy, because the web server sets it, not the browser. But behind a proxy or CDN it is *the proxy's* address, and the visitor's real IP sits in the `X-Forwarded-For` **header**, which any client can set to anything. So: `REMOTE_ADDR` is honest but may point at the proxy; `X-Forwarded-For` may name the real client but is forgeable unless you trust the proxy and take only the hop *it* appended. We log `REMOTE_ADDR` and accept the limitation. Put a CDN in front of this site and `ip` starts recording edge nodes — that needs a deliberate fix, not a quiet header swap.
5. **A log row proves an action, not an intent.** `admin_id = 1` means "a request arrived carrying admin 1's session cookie." It does not prove admin 1 was at the keyboard — a shared password, a borrowed laptop, or a stolen cookie produce identical rows. Treat the log as evidence to investigate, never as a verdict about a person.
6. **Add the audit call to new endpoints — nothing enforces it.** `_bootstrap.php` enforces auth and CSRF automatically; it does *not* write an audit row. That is a per-endpoint responsibility, which is exactly why it gets forgotten (`SECURITY.md:123`).

## 8. Try it yourself

Run `./run.sh` (it builds, waits for the DB, and seeds sample data), then open <http://localhost:8090/admin/> and sign in — the dev credentials are printed in [`../README.md`](../README.md). Now, deliberately:

1. Type a **wrong** password once → `login.fail`.
2. Sign in properly → `login.ok`.
3. Open any section from the dashboard and change one piece of text → `field.save` or `item.update`.
4. Sign out with the panel's logout button → `logout`.

Read the trail. The database service is `db` in `docker-compose.yml`, and the dev credentials come from `.env.example` (`DB_USER=stjosephs`, `DB_PASS=stjosephs_pw`, `DB_NAME=stjosephs` — throwaway values for a container bound to `127.0.0.1`, never production secrets):

```bash
docker compose exec -T db mysql -ustjosephs -pstjosephs_pw stjosephs \
  -e "SELECT id, admin_id, action, entity, entity_id, detail, ip, created_at
      FROM audit_log ORDER BY id DESC LIMIT 10;"
```

Check, in order:

- The `login.fail` row has **`admin_id` = NULL** and your typed username in `detail`. Nobody was logged in, so there is no id to record.
- The `login.ok` row right after it **does** have an `admin_id`.
- No row contains a password. Prove it — run `SELECT COUNT(*) FROM audit_log WHERE detail LIKE '%$2y$%';` → `0` (`$2y$` prefixes every bcrypt hash this app produces).
- Your `field.save` row names the **field**, not the text you typed.
- `ip` shows a Docker-internal address like `172.x.x.x` — `REMOTE_ADDR` working exactly as described in gotcha 4.

Then feel §4.2 for yourself. Temporarily `RENAME TABLE audit_log TO audit_log_x;`, make another edit in the panel, and watch it **succeed normally** — no error, no 500, just a silently missing row. Rename it back when you're done. That is the empty `catch` doing its job.

## 9. Where to read more

**In this repo:**

- [`../03-what-we-built.md`](../03-what-we-built.md) — the S4 section ("Walls, cameras, and headers"), where the audit log was introduced alongside `.htaccess` hardening and the POST-only logout.
- [`../06-stage-f.md`](../06-stage-f.md) — the live-edit overlay; why every overlay save goes through the same front controller: "session, CSRF header, registry validation, sanitizer, audit log."
- [`../07-stage-g.md`](../07-stage-g.md) — "A first: the owner edited the site while we worked", the narrative behind audit id 89.
- [`../../../SECURITY.md`](../../../SECURITY.md) — **SEC-19** is the normative item; §4's 15-point checklist is mandatory before shipping any admin change.
- [`../02-security.md`](../02-security.md) §10 — the one-paragraph version in the context of the other defences.

**Outside:**

- OWASP Logging Cheat Sheet — <https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html> (what to log, what never to log, how to keep logs usable).
- OWASP Top 10 **A09:2021 — Security Logging and Monitoring Failures** — <https://owasp.org/Top10/A09_2021-Security_Logging_and_Monitoring_Failures/> (the category SEC-19 maps to).
- MySQL manual, multiple-column indexes — <https://dev.mysql.com/doc/refman/8.0/en/multiple-column-indexes.html> (why `idx_admin_when (admin_id, created_at)` serves some queries and not others).
