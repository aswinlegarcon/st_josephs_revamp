# Migrations and Seeding

> **What you'll learn:** how the shape of our database (tables and columns) is described in files, how we change that shape safely on a live site, and how the site's starting content gets loaded every time without ever duplicating itself.
>
> **Prerequisites:** basic PHP (`$variable`, `if`, `foreach`, functions). No SQL, no Docker, no database knowledge needed — every term is explained on first use.
>
> **Where it lives:** the `database/` folder — `schema.sql`, `migrations/*.sql`, `seed.php`, `seed-data/*.php`, `extract-*.php`, `backfill.php` — plus the launcher `run.sh` and the deploy runbook `DEPLOY.md`.

---

## 1. The one-paragraph version

A **database** stores our text and photo records in **tables** — think of one table as one spreadsheet, with named columns and numbered rows. Our site has 27 of them (`grep -c 'CREATE TABLE' database/schema.sql` → 27). Two files describe them. `database/schema.sql` is the **fresh-install** truth: run it on an empty database and you get every table at once. `database/migrations/00N_*.sql` are the **incremental** steps — seven small files, each recording one change made at one moment in the project's history, so a database that *already exists* (production) can catch up without being wiped. Tables start empty, so `database/seed.php` fills them with the site's shipped content: the seven home-page carousel slides, the school's phone number, 41 SEO descriptions. `run.sh` runs the seeder on **every** start, which is only safe because the seeder is **idempotent** — running it again changes nothing.

---

## 2. The problem this solves

Suppose there were no files. You want a column for a page's SEO description, so you open the database admin tool and click "add column". Done — on **your** machine.

Now count the copies of this database: your Docker one, a new intern's after their first `git clone`, and production, serving real visitors. Your click changed one of the three. The other two now run code asking for a column that isn't there; MySQL errors; the page dies. Nobody can trace it, because the change left **no record in git** — it was a click, not a commit.

A migration is the fix: **write the change down as a file, commit it, apply that same file everywhere.** "Migration" just means "a recorded step that moves a database from one shape to the next".

Our hosting sharpens this. Production is MilesWeb shared hosting with **no SSH** — no way to open a terminal on the server (`DEPLOY.md:3`). Deploying is uploading files through a web File Manager. There is no command you can run there to "sync" anything. If a schema change is not in a file you can paste into mPanel's database tool, it does not happen.

---

## 3. How it works in general

Keep two ideas separate. **Schema** is the *shape*: tables, columns, types, indexes, no content. **Seed** is the *starting content*: actual rows.

There are two ways to deliver the shape, and we use both:

1. **The snapshot** — one big file listing every table as it should look today. Run once on an empty database. The only sane way to boot a brand-new copy. That is `database/schema.sql`.
2. **The steps** — many small files, each one edit, applied in order to a database that already holds data. You cannot re-run the snapshot on a live site without destroying what's in it. That is `database/migrations/`.

SQL that changes shape is **DDL** (Data Definition Language): `CREATE TABLE`, `ALTER TABLE`, `DROP TABLE`. SQL that changes content is **DML**: `INSERT`, `UPDATE`, `DELETE`. Migrations are mostly DDL; seeds are DML.

One golden rule: **never edit a migration after it has been applied anywhere.** Its job is to describe a moment in history. Edit it, and the copy that ran the old version and the copy that runs the new one diverge silently. Need a different result? Write the next numbered file.

---

## 4. How we use it — every place in this codebase

### 4.1 The two-file rule

Every schema change lands in **two** files:

| File | Runs when | Runs where |
|---|---|---|
| `database/schema.sql` | first boot of an empty database | your Docker DB; production's one-time setup |
| `database/migrations/00N_*.sql` | once, by hand, after a deploy | production and any long-lived DB |

Docker wires up the first. `docker-compose.yml:11` mounts the schema into a magic directory inside the database container:

```yaml
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql:ro
```

The MySQL image runs every `.sql` file found there **the first time** it initialises its data directory, and only then. `dbdata` (`docker-compose.yml:10,43-44`) is a named **volume** — disk that survives restarts — so a second `./run.sh` finds data already present and skips initialisation. (`schema.sql:2` says "MariaDB container"; that comment is stale, `docker-compose.yml:3` pins `image: mysql:8.0`.) Production gets the same file by hand, once: "Import `database/schema.sql` via mPanel's DB tool (phpMyAdmin). This creates every table." (`DEPLOY.md:11-12`).

**Both files must be updated for every change.** What breaks otherwise:

- **Migration written, `schema.sql` not updated.** Production is fine. Then someone clones the repo and runs `./run.sh`; their fresh database is built from `schema.sql` alone (migrations are never auto-applied), so the column is missing and their local site 500s on a page that works live.
- **`schema.sql` updated, migration not written.** Every developer is fine. Then you deploy. Production's tables were created long ago and nothing adds the column — the school's live website breaks for real visitors.

The pairing is visible in the code. `database/migrations/005_c4_sections_events_heading.sql:5-6`:

```sql
ALTER TABLE school_sections
  ADD COLUMN events_heading VARCHAR(60) NOT NULL DEFAULT 'Exams and Events of' AFTER timeline_heading;
```

and its mirror inside the snapshot, `database/schema.sql:256`:

```sql
  events_heading   VARCHAR(60) NOT NULL DEFAULT 'Exams and Events of',
```

Same column, same type, same default, two files.

### 4.2 All seven migrations

| # | File | What it added | Kind | Phase |
|---|---|---|---|---|
| 001 | `001_admin_users_roles.sql` | `role`, `must_change_password`, `password_changed_at` on `admin_users`, plus an `UPDATE` forcing the existing admin to rotate their password | `ALTER` + `UPDATE` | S2 "Kill default admin" (`PHASES.md:68`) |
| 002 | `002_audit_log.sql` | `audit_log` — who logged in, who edited what, from which IP | `CREATE TABLE IF NOT EXISTS` | S4 (`PHASES.md:70`) |
| 003 | `003_c1_contact_submissions.sql` | `contact_submissions`; its `KEY idx_ip_time (ip, created_at)` powers the 5-per-hour-per-IP rate limit | `CREATE TABLE IF NOT EXISTS` | C1 (`PHASES.md:110`) |
| 004 | `004_c3_testimonials.sql` | `testimonials` — home-page student quotes become an editable list | `CREATE TABLE IF NOT EXISTS` | C3 (`PHASES.md:112`) |
| 005 | `005_c4_sections_events_heading.sql` | `school_sections.events_heading` — KG ships "Events of", the other three "Exams and Events of" | `ALTER` | C4 (`PHASES.md:115`) |
| 006 | `006_c9_album_columns.sql` | `gallery_albums.card_sub` + `.heading` — the hub card and the album page ship *different* title strings | `ALTER` | C9 (`PHASES.md:122`) |
| 007 | `007.sql` | `seo_meta` — one row per URL slug, holding `title` and `description` | `CREATE TABLE IF NOT EXISTS` | F3 SEO pack (`PHASES.md:143`) |

**Four are naturally re-runnable, three are not.** `CREATE TABLE IF NOT EXISTS` (002, 003, 004, 007) does nothing when the table exists. `ALTER TABLE … ADD COLUMN` (001, 005, 006) has no such escape hatch, and 001 says so at `001_admin_users_roles.sql:7-8`: "MySQL 8 has no `ADD COLUMN IF NOT EXISTS`; if a column already exists the statement errors harmlessly — skip it."

**Migration 001 also carries a data change** (`001_admin_users_roles.sql:16`):

```sql
UPDATE admin_users SET must_change_password = 1 WHERE password_changed_at IS NULL;
```

The `WHERE` makes it safe to run twice — after the first run those rows stop matching. That is idempotency inside a migration; §4.5 is the same trick at scale.

### 4.3 The additive-only rule

`CLAUDE.md`: "Migrations are **additive-only** (no destructive DDL)". `DEPLOY.md:45` repeats it. All seven only add — no `DROP TABLE`, no `DROP COLUMN`, no narrowed types (`git grep -n 'DROP' database/migrations/` finds nothing).

The justification is what rollback looks like here (`DEPLOY.md:64-68`):

> To roll back: re-extract the previous zip over `public_html/`. Because migrations are additive-only, the older code still runs against the newer schema. Do **not** roll back the database.

Rollback means putting **old code files** back. There is no tool to un-apply a schema change and no SSH to run one if there were. So the rule keeps old code working against the new schema: a column the old code never mentions is simply ignored by it. A *dropped* column makes the old code's `SELECT` fail immediately — now your rollback has broken the site worse than the bug you were fleeing. And a dropped column takes its data with it; recovery here means last night's backup (`DEPLOY.md:104-115`) and losing every edit since.

### 4.4 Seeding — `database/seed.php`

`schema.sql` leaves the tables empty. `database/seed.php` (622 lines) fills them with the content the static site used to hard-code in HTML. It is **CLI-only**, never reachable over the web (`seed.php:8-10`), and `run.sh` calls it on every start (`run.sh:43-44`):

```bash
echo "==> Seeding content (idempotent — safe to re-run)…"
docker compose exec -T web php /var/www/database/seed.php
```

`docker compose exec -T web` means "run this inside the already-running container named `web`"; the path is `/var/www/database/…` because `docker-compose.yml:33` mounts the host's `./database` there.

In order the seeder: registers every file in `public_html/photos/` as an `images` row (`seed.php:27-40`); creates the admin account (`:55-75`); seeds the home page and its 7 hero slides (`:77-95`); the principal's message, "What's Unique" blocks, ticker, update slides, three years of top marks; then the About, Staffs, section, academy, sports, infrastructure, achievement and gallery families; then 14 site settings (`:503-518`) and 41 SEO rows (`:529-571`).

**Seed data is not user data** — the distinction that governs everything else:

| | Seed data | User data |
|---|---|---|
| Comes from | files in git | a human typing in the admin panel, or a visitor |
| Examples | the shipped principal's message; the 41 default SEO descriptions | that message *after* the school rewrites it; an uploaded photo; a contact-form enquiry |
| Regenerable | yes, from git | **no — it exists nowhere else** |

The seeder's design follows from the right-hand column: it may create missing things and may never overwrite existing ones. `seed.php:502` puts it in four words: "INSERT IGNORE = admin edits win."

### 4.5 Idempotency — the property that matters most

**Idempotent** means doing it once and doing it five times give the same result. A light switch is not (each flip toggles); a "set brightness to 50%" button is.

`run.sh` seeds on every start. Without idempotency the home page would gain seven more carousel slides each time anyone typed `./run.sh` — seventy after ten starts. The header comment names the two main techniques (`seed.php:2-5`):

```php
// CLI seeder — idempotent and NON-DESTRUCTIVE:
//   • keyed rows (pages, profiles, mark_years, settings, admin user) → insert only if missing
//   • list content (hero slides, ticker, updates, features, mark entries) → seeded only when
//     the list is EMPTY, so admin edits are never overwritten by a re-run.
```

**Technique 1 — `INSERT IGNORE` on a keyed row.** A **UNIQUE key** promises no two rows share a value in that column; `pages.slug` is `NOT NULL UNIQUE` at `schema.sql:123`. A plain `INSERT` of a duplicate slug is an error that stops the script. `INSERT IGNORE` turns that error into a silent no-op (`seed.php:78`):

```php
$pdo->prepare('INSERT IGNORE INTO pages (slug, title, heading_html) VALUES (?,?,?)')->execute([
    'index', …
]);
```

First run creates the row; every later run sees `slug='index'` already there and skips — *including* when the school has since rewritten the title. Their edit wins. The same three words guard `images` (`:29`), `profiles` (`:99`), `mark_years` (`:162`), `school_sections` (`:314`), `academies` (`:376`), `facilities` (`:434`), `settings` (`:519`) and `seo_meta` (`:572`). `rowCount()` then reports how many actually landed (`seed.php:519-525`), which is why a second run prints `settings: +0 newly seeded`.

**Technique 2 — "only seed when the list is empty".** A list of carousel slides has no unique key to hang `INSERT IGNORE` on — two identical slides are legal — so the seeder counts first (`seed.php:86-88`):

```php
$count = $pdo->query("SELECT COUNT(*) FROM hero_slides WHERE page_id = $pageId")->fetchColumn();
if (!$count) {
    $slides = ['sportsday20.jpg', 'sports.jpeg', 'ann3.jpg', …];
```

Zero rows → seed. One or more → leave the whole list alone. That is stronger than it looks: if an admin *deletes* five of seven slides the count is 2, not zero, so the seeder does not "helpfully" restore them. The same guard protects `unique_features` (`:103`), `ticker_items` (`:116`), `update_slides` (`:130`), per-year `mark_entries` (`:170-171`), `testimonials` (`:274`), each section's `timeline_entries` (`:349`) and `section_events` (`:359`), and each carousel's `image_links` (`:340`, `:388`, `:439`, `:485`).

**Technique 3 — REPLACE-style fixups, idempotent by construction.** Some shipped typos had to be corrected in databases that already existed (the seed-data files only feed empty ones). Those are `UPDATE`s whose `WHERE` stops matching once the fix is in (`seed.php:580-590`):

```php
/* ---------- R3 fixups: bug-14 typos + the carosel1.jpg filename ----------
   Idempotent by construction: REPLACE() only changes rows still carrying the
   old text, so a re-run is a no-op. … */
$fixups = [
    ["UPDATE hero_slides SET caption_title = REPLACE(caption_title, 'Higer Secondary', 'Higher Secondary') WHERE caption_title LIKE '%Higer Secondary%'"],
    ["UPDATE gallery_albums SET title = 'Annual Day' WHERE slug = 'gal-annual' AND title = 'annual Day'"],
```

`REPLACE()` here is MySQL's *string function* (find-and-replace inside a value). Do not confuse it with the `REPLACE INTO` statement, which deletes and re-inserts a row — we never use that, because it would throw away admin edits. The trickiest fixup (`seed.php:596-614`) repairs the `carosel1.jpg` → `carousel1.jpg` filename: the photo scan may already have inserted a row for the correctly-spelled file, so the code deletes that new row *only if nothing references it*, then renames the original — keeping every foreign key pointed at a live row.

**The admin-user block** does three jobs at once (`seed.php:56-71`, abridged):

```php
$exists = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE username = 'admin'")->fetchColumn();
if (!$exists) {
    …
    } elseif ($prod) {
        $pass = bin2hex(random_bytes(9)); // 18 hex chars, shown once below
    } else {
        $pass = 'admin123';               // local dev only
    }
    $pdo->prepare('INSERT INTO admin_users (username, password_hash, display_name, must_change_password) VALUES (?,?,?,1)')
        ->execute(['admin', password_hash($pass, PASSWORD_DEFAULT), 'Administrator']);
```

It is idempotent (created only when absent, so a re-run never resets a changed password); it stores only `password_hash(...)`, never the password; and it hard-codes `must_change_password` to `1` — the column migration 001 added — so even `admin123` cannot survive the first login.

One deliberately **non**-idempotent piece: `img_id_by_file()` aborts the whole seed when a referenced photo is missing (`seed.php:48-51`). Failing loudly beats seeding a half-built page.

### 4.6 The extractors and `database/seed-data/`

The 42 pages originally had their text typed straight into HTML. Roughly 100 KB of it had to move into the database *without retyping a word* — retyping risks changing the rendered page, which `CLAUDE.md`'s visual-freeze rule forbids. So we wrote six **extractors**:

| Extractor | Produces | Phase |
|---|---|---|
| `database/extract-sections.php` | `seed-data/sections.php` | C4 |
| `database/extract-academies.php` | `seed-data/academies.php` | C5 |
| `database/extract-sports.php` | `seed-data/sports.php` | C6 |
| `database/extract-infrastructure.php` | `seed-data/facilities.php` | C7 |
| `database/extract-achievements.php` | `seed-data/achievements.php` | C8 |
| `database/extract-gallery.php` | `seed-data/gallery.php` + `public_html/css/albums/*.css` | C9 |

Each loads an old view file, parses it with `DOMDocument`, queries it with XPath (`extract-academies.php:62-80`), and writes a PHP array using `var_export()` — a function that prints a PHP value as valid PHP source (`extract-academies.php:142-145`). The seeder then simply `require`s the array (`seed.php:374`):

```php
$academies = require __DIR__ . '/seed-data/academies.php';
```

Every generated file opens with a `GENERATED by …` banner and "edit via the admin panel, not here". Take that literally: a hand edit desyncs the file from its extractor and would not survive regeneration. Extractors were allowed to fix genuinely broken references while copying — `extract-academies.php:30-51` resolves six image names where the HTML said `.jpg` but the file on disk was `.jpeg`.

**The rule constraining all of this** is in `CLAUDE.md`: generators "may write only **generated data and inert assets** — `database/seed-data/*.php` data arrays and `public_html/css/albums/*.css`. No generator may ever emit **executable PHP into `public_html/`**." A script writing runnable PHP into the web-served directory is, at the operating-system level, indistinguishable from a **web shell** — an attacker's backdoor. This repo is developed on an EDR-monitored machine that has already killed a process for exactly that pattern. A generator that writes to the webroot needs explicit human approval.

### 4.7 `database/backfill.php` — a one-off that stayed re-runnable

`backfill.php` (199 lines) generates smaller copies — **renditions** — of the 478 legacy photos, so pages download about 1 MB instead of 10 MB. It is neither migration nor seed, but it is held to the same standard (`backfill.php:4-6`): "Idempotent: (image, preset) pairs that already have renditions are skipped, so a re-run after adding photos is cheap."

Two skip-guards do it — `if (media_renditions($imageId, $presetKey))` for database-driven renditions (`:97-100`) and `if (is_file($dst))` for the static design assets (`:156-159`). Both are "does the output already exist? then don't redo it" — the seeder's empty-list check, applied to files instead of rows. It ends by printing counts and exiting non-zero if anything failed (`:193-199`).

It runs **only in Docker**, never on the shared host — `docker compose exec -T web php /var/www/database/backfill.php` (`DEPLOY.md:90-95`). The generated files under `public_html/media/` then ship with the release and the matching `image_renditions` rows are exported from dev separately. A missing rendition falls back to the original photo — degraded, not broken (`backfill.php:13-16`).

### 4.8 Applying a migration in production

No SSH, no command line. The whole procedure is `DEPLOY.md:44-45`:

> **Apply migrations** (if any new `database/migrations/NNN_*.sql`): paste each into the mPanel DB tool, in order. Migrations are **additive-only** — never destructive.

Log into mPanel, open phpMyAdmin, select the database, open the SQL tab, paste the file, run it. One file at a time, lowest number first. Then confirm via `/admin/health.php?token=<health_token>`, whose `schema` check verifies the tables exist (`DEPLOY.md:46-48`, `:78`).

The seeder is **not** part of this. `DEPLOY.md:28-30` is explicit — run it once locally against a dump, or paste the generated INSERTs; "do **not** upload `database/seed.php` to run on prod". Nothing in `database/` is uploaded at all (`DEPLOY.md:52-54`).

### 4.9 The admin-edits-win rule

`DEPLOY.md:58-62`:

> Content flows **dev → prod only for the very first ship**. After that, **production is the source of truth**. Pull nightly dumps down into dev for testing (X2); never push a dev database over prod (it would wipe real edits).

Once the school starts editing, their database holds things that exist nowhere else. Pushing your local database "to make them match" destroys all of it, and git cannot bring it back. Data moves **downward only**: production dump → your laptop. This is also why the seeder's `INSERT IGNORE` habit is not a style preference — the seeder runs against real databases, and one unguarded `INSERT`/`UPDATE` would silently revert the school's work the next time anyone ran it over a restored dump.

---

## 5. Why this is the right approach here

There are grown-up tools for this. Here is why we use plain `.sql` files instead.

| Approach | What it needs | Why it fails here |
|---|---|---|
| **Phinx / Doctrine Migrations** (PHP migration frameworks with version tracking, up/down, a `migrate` command) | a command line on the server, Composer installed there | `DEPLOY.md:3` — "no SSH, no Docker, no Composer on the server". You could not run `phinx migrate` if you wanted to |
| **ORM auto-sync** (a library that diffs PHP models against the live DB and issues the DDL itself) | a CLI, plus trust | Same CLI problem, and worse: a diff tool decides on its own to `DROP` a column it doesn't recognise — exactly the destructive DDL we forbid. We also have no ORM; `CLAUDE.md` says "**No framework** — structured plain PHP" |
| **Hand-run SQL, nothing committed** | nothing | The §2 problem in full: no record, no reproducibility, no way for a second person to catch up |
| **Snapshot only, no migrations** | nothing | Fine until production holds real data. You cannot re-run `schema.sql` on a live site to pick up one column |
| **Migrations only, no snapshot** | nothing | A fresh clone would have to replay seven files in order before it could boot; `./run.sh` must work in one command |

Our constraints: file-upload deploys, no server CLI, a single maintainer, and a hard requirement that `git clone` + `./run.sh` yields a running site. Plain `.sql` files satisfy all four — they paste into phpMyAdmin, read as documentation, diff in review, and `docker-compose.yml:11` turns the snapshot into a zero-effort fresh install. The honest trade: a framework would track which migrations have run. We track that in our heads. §6 is what that costs.

---

## 6. How this scales

**Today:** seven migrations, one maintainer, two environments. The manual process fits in one line of `DEPLOY.md`.

**At ~70 migrations, with a staging site and two developers,** four specific pains appear: *which ones have run on this database?* (with seven you remember; with seventy nobody does); *did anyone skip one?* (sequential numbers are a convention, not a check); *did two branches both write `008`?* (git merges both files happily); *is `schema.sql` still equal to `schema.sql` + all migrations?* (nothing verifies this, and drift stays invisible until a fresh clone breaks).

The standard fix is a **`schema_migrations` tracking table** — a table whose only job is to record which migration filenames have been applied, with timestamps. A runner reads the folder, subtracts what the table lists, and applies only the difference, making "apply migrations" itself idempotent and unskippable.

**We do not have one.** `grep -rn "schema_migrations" .` finds nothing outside `vendor/`. That is a deliberate gap, and this manual discipline stands in for it:

- **Numbered filenames applied in order** (`DEPLOY.md:45`), so the sequence is legible.
- **`CREATE TABLE IF NOT EXISTS` wherever possible**, making 4 of our 7 safe to double-run.
- **Additive-only**, so an accidental double-apply is at worst a duplicate-column error, never data loss.
- **Each migration states its own rules in comments** — `001:3-5`: "Apply ONCE against an existing production database via mPanel's DB tool. Fresh installs get these columns from schema.sql instead."
- **The health endpoint's `schema` check** (`DEPLOY.md:78`) catches a missing table after deploy.
- **One maintainer**, which removes the merge-collision mode entirely.

The tipping point is *a second person applying migrations*, or *a staging environment*. At that point add a `schema_migrations` table, a `database/migrate.php` CLI that applies the missing files inside Docker, and — since production still has no CLI — have it *print* the pending SQL for pasting. `schema.sql` stays, because fresh installs must keep booting in one command. Seeding scales more comfortably; the two idempotency techniques don't change with content volume. The one thing that ages badly is seed runtime — the seeder rescans every file in `public_html/photos/` on each run (`seed.php:31-39`, 478 files today).

---

## 7. Gotchas and mistakes to avoid

**1. Updating one file of the pair.** The number-one mistake (§4.1). Add a column to `schema.sql` and ship, and nothing happens — production's tables were created long ago and are never rebuilt from that file. Write only the migration, and the next fresh clone lacks the column. *Habit: touching `schema.sql` → your next action is creating `database/migrations/008_*.sql`, and vice versa.*

**2. A non-idempotent seed.** Drop the `if (!$count)` guard at `seed.php:87` and every `./run.sh` appends seven more hero slides. Locally you notice fast; the same mistake as an unguarded `INSERT` against a restored production dump silently overwrites the school's edits. *Test by running the seeder twice and comparing the `Totals:` line (§8).*

**3. `ALTER TABLE` locking a big table.** Adding a column can require rewriting the whole table, during which writes queue. MySQL 8 does many `ADD COLUMN`s instantly — but not all, and phpMyAdmin will happily time out mid-`ALTER` on a shared host with no progress indicator. This has not bitten us; our altered tables are tiny (`school_sections` has 4 rows, `gallery_albums` 10). The day a migration touches `image_links` (247+ photo rows) or `images` (478), check the operation against MySQL's online-DDL table first and run it at low traffic.

**4. Charset mismatch on a new table or column.** Our content holds Tamil names and typographic quotes — see the `’` characters at `seed.php:202`. Every `CREATE TABLE` in our migrations ends with an explicit `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` (`002:17`, `003:17`, `004:15`, `007:14`), and `schema.sql:3` opens with `SET NAMES utf8mb4;`. Omit that clause in migration `008` and MySQL silently uses the *server's* default, which on shared hosting may be `latin1`; text stores as mojibake and nothing errors until the school reports garbled names. The `ALTER` migrations (001, 005, 006) correctly carry no charset clause — a new column inherits its table's. *Copy the full `ENGINE=…CHARSET=…COLLATE=…` line from an existing migration.*

**5. Root-owned files created inside the container.** Docker mounts host folders into the container (`docker-compose.yml:31-39`), and files a container process creates land on your host owned by *the container's* user, so your editor cannot save them. That is why `run.sh:25-29` prepares the upload directory before the container touches it, with a loud comment that the `777` is a localhost-dev-only concession and production must be `755`. `backfill.php:149` and `:186` set explicit modes for the same reason. *If a generated file becomes un-editable, that is what happened — fix ownership on the host, don't `sudo` your editor.*

**6. Editing a generated file by hand.** Every `database/seed-data/*.php` says "edit via the admin panel, not here". Hand edits diverge from the extractor and vanish on regeneration.

**7. Re-running an extractor after go-live.** They parse the *old static views*, many of which no longer exist (C5 deleted 18 of them — `PHASES.md:116`). They are one-time tools; `extract-academies.php:2` labels itself "DEV-ONLY (C5). One-time extractor". Their output is committed precisely so nobody needs to run them again.

**8. Editing a migration that already shipped.** Production ran the old text; your edit only affects *future* databases. Write the next number instead.

---

## 8. Try it yourself

Everything runs in Docker. Service names come from `docker-compose.yml` (`db`, `web`) and the dev credentials from `.env.example:10-13` (`MYSQL_ROOT_PASSWORD=rootpw`, `DB_NAME=stjosephs`) — throwaway values for a container bound to `127.0.0.1`, never production secrets.

**A. Watch a fresh install happen.**

```bash
./run.sh reset     # docker compose down -v — deletes the dbdata volume (run.sh:14-18)
./run.sh           # empty DB → MySQL runs schema.sql → run.sh seeds it
```

The reset is what makes the next boot run `schema.sql` again; without `-v` the volume survives and initialisation is skipped.

**B. Prove the seeder is idempotent.**

```bash
docker compose exec -T web php /var/www/database/seed.php
docker compose exec -T web php /var/www/database/seed.php
```

The first run prints lines like `images: +478 newly registered` and `hero_slides: seeded 7`. The second prints `images: +0 newly registered`, `settings: +0 newly seeded`, and **no** `seeded` lines at all — every empty-list guard saw a non-empty list. The `Totals:` line (`seed.php:617-622`) is identical both times. That comparison *is* the test.

**C. Break it on purpose.** Delete two hero slides in the admin panel (`http://localhost:8090/admin/`), re-run the seeder, reload the home page: five slides, not seven — the seeder respected your deletion because the count wasn't zero. Now delete *all* of them and re-run: the list is empty, so all seven return.

**D. Apply a migration by hand, the way production does.** Migrations are mounted into the `web` container (`docker-compose.yml:33`) but not `db`, so pipe from the host:

```bash
docker compose exec -T db mysql -uroot -prootpw stjosephs < database/migrations/005_c4_sections_events_heading.sql
```

Expect `Duplicate column name 'events_heading'`. That is the correct, safe outcome — your Docker database was built from `schema.sql`, which already has the column at line 256, exactly as `001_admin_users_roles.sql:7-8` predicts for `ALTER` migrations. Now try a `CREATE TABLE IF NOT EXISTS` one:

```bash
docker compose exec -T db mysql -uroot -prootpw stjosephs < database/migrations/007.sql
```

Silence — no output, no error. That is what "re-runnable" looks like.

**E. Look at the shape directly.**

```bash
docker compose exec -T db mysql -uroot -prootpw -e "SHOW TABLES" stjosephs
docker compose exec -T db mysql -uroot -prootpw -e "SHOW COLUMNS FROM school_sections" stjosephs
docker compose exec -T db mysql -uroot -prootpw -e "SELECT slug, title FROM seo_meta LIMIT 5" stjosephs
```

27 tables; `events_heading` sitting right after `timeline_heading`, exactly as migration 005's `AFTER` clause specifies; five of the 41 seeded SEO rows.

**F. Read a generated file next to its source.** Open `database/seed-data/academies.php`, then `database/extract-academies.php`, then the page it feeds at `http://localhost:8090/tamilacademy.php`. Following one paragraph through all three is the fastest way to make this topic concrete.

---

## 9. Where to read more

**In this repo**

| Doc | Read it for |
|---|---|
| [`../01-fundamentals.md`](../01-fundamentals.md) | what a database, table, SQL and PDO actually are, from zero — start here if §3 felt fast |
| [`../05-stage-e.md`](../05-stage-e.md) | Stage E, where migrations 003–006 and most seed data were written |
| [`../../../PHASES.md`](../../../PHASES.md) | the phase table — S2, S4, C1, C3, C4, C9 and F3 each name the migration they added |
| [`../../../DEPLOY.md`](../../../DEPLOY.md) | §0.2 first-time schema import, §1.5 applying migrations, §3 the data-direction rule, §4 rollback, §6 renditions |
| [`../../../CLAUDE.md`](../../../CLAUDE.md) | the additive-only rule, the generator restriction, the file-writing rule |

**Outside**

- [MySQL 8.0 — `INSERT ... IGNORE`](https://dev.mysql.com/doc/refman/8.0/en/insert.html) — exact semantics of the modifier our seeder leans on, and which errors it downgrades.
- [MySQL 8.0 — Online DDL operations](https://dev.mysql.com/doc/refman/8.0/en/innodb-online-ddl-operations.html) — which `ALTER TABLE` operations are instant, which rewrite the table, which block writes. Check before altering a large table (gotcha 3).
- [Docker `mysql` image — "Initializing a fresh instance"](https://hub.docker.com/_/mysql) — the `/docker-entrypoint-initdb.d/` mechanism `docker-compose.yml:11` uses, and the "first start only" rule that makes `./run.sh reset` necessary.
- [Martin Fowler — Evolutionary Database Design](https://martinfowler.com/articles/evodb.html) — the original argument for treating schema changes as versioned, ordered, committed artefacts.
