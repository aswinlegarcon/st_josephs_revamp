# Database Schema Design

> **What you'll learn:** what a database table actually is, how to read every line of
> `database/schema.sql`, why our 27 tables are shaped the way they are, and how to add one
> without breaking the site.
> **Prerequisites:** very basic PHP (you know what `$row['title']` means). **No SQL needed** —
> every SQL word is explained the first time it appears.
> **Where it lives in our code:** `database/schema.sql` (the source of truth),
> `database/migrations/*.sql` (post-launch changes), `src/Content/Registry.php` (which tables
> the admin panel may edit), `src/Content/Repo.php` (how pages read them).

## 1. The one-paragraph version

A **database** is a set of spreadsheets a program can search instantly. Each spreadsheet is a
**table**. The **schema** is the written plan for those tables — their names, their columns,
what kind of value each column holds, and how they point at each other. Our whole plan lives in
one file, `database/schema.sql`, which creates **27 tables**. The 42 public pages read from
them; the admin panel writes to them. Nothing in this project matters more: a wrong column type
is one bug on one page, but a wrong *shape* is inherited by every page and every admin screen.

## 2. The problem this solves

Before this migration the site was 42 hand-written PHP files, with the principal's message typed
*inside* `index.php` and photo filenames typed inside `gal-annual.php`. That costs four things:
**only a programmer can change anything**; **the same fact is stored many times** (the principal's
photo appeared on the home page *and* the about page — change one, forget the other, they
disagree); **you cannot ask questions** ("which pages use this photo?" is unanswerable when the
answer is raw text spread across 42 files); and **order and visibility are hard-coded** (moving the
third sports card to first place means cutting and pasting HTML).

A schema fixes all four by giving every piece of content **one home, with a name and a shape**. The
principal's message becomes one *row* in `profiles`; both pages read that row; the admin panel edits
that row; reordering becomes changing a number. Analogy: the old site was a filing cabinet where
every document was a photocopy in a random drawer; the schema is labelled trays, one document per
tray, plus a card index saying which tray holds what.

## 3. How it works in general

### 3.1 Tables, rows, columns

A **table** is one kind of thing (`sports` holds sports, `images` holds images). A **column** is one
fact about that kind of thing, with a fixed name and fixed type (`sports` has `name` and
`training_time`). A **row** is one actual thing: one sport, with a value in every column.
Table = a form; columns = the blanks on it; rows = filled-in copies. A real table from our schema,
complete (`database/schema.sql:173`):

```sql
CREATE TABLE IF NOT EXISTS ticker_items (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label      VARCHAR(200) NOT NULL,
  url        VARCHAR(255) NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`CREATE TABLE IF NOT EXISTS` means "make this, but do nothing if it already exists" — that is what
makes the file safe to run twice. `NOT NULL` means "this blank may never be left empty".
`DEFAULT 0` means "if nobody says otherwise, use 0".

### 3.2 The data types we actually use

A **type** is the promise about what a column may contain; MySQL rejects anything else, which is
a free bug-catcher. These are the only types this project uses:

| Type | Holds | Where we use it | When to pick it |
|---|---|---|---|
| `INT UNSIGNED` | whole number, 0 … 4,294,967,295 | almost every `id` (`schema.sql:71`) | default for ids and counts that can never be negative |
| `SMALLINT UNSIGNED` | whole number, 0 … 65,535 | `pages.id` (`:122`), every `position`, image `width`/`height` | when you *know* the value stays small |
| `TINYINT UNSIGNED` | whole number, 0 … 255 | `admin_users.id` (`:7`), `school_sections.id` (`:249`), `quality` (`:93`) | tiny fixed sets — there will never be 256 admins |
| `TINYINT(1)` | 0 or 1, used as yes/no | every `is_active`, `must_change_password`, `sent` | booleans (MySQL has no real `BOOLEAN`; see §7.3) |
| `VARCHAR(n)` | short text, at most `n` characters | titles, names, slugs, URLs | any single-line text; pick `n` a little above the longest realistic value |
| `TEXT` | long text (~65 KB max) | `settings.svalue` (`:23`), `timeline_entries.events_text` (`:272`) | multi-line text you never index or sort on |
| `MEDIUMTEXT` | very long text (~16 MB max) | `profiles.message_html` (`:154`), `academies.body_html` (`:302`) | full rich-text page bodies |
| `DATETIME` | a date **and** a time | every `created_at` / `updated_at` | timestamps (read §7.5 before trusting one) |
| `ENUM('a','b')` | one value from a fixed short list | `achievements.type` (`:236`), `mark_entries.standard` (`:221`), `image_presets.mode` (`:92`), `image_renditions.format` (`:99`) | a list that will genuinely never change (see §7.4) |

`UNSIGNED` means "no negative numbers" — it doubles the useful range and documents intent.
**How to pick one:** number or text? If number, how big can it get — that chooses `TINYINT` /
`SMALLINT` / `INT`. If text, one line (`VARCHAR`) or a paragraph (`TEXT`/`MEDIUMTEXT`)? Then: is
the set of allowed values fixed *and* tiny? That is the only case for `ENUM`.

### 3.3 Primary keys and `AUTO_INCREMENT`

A **primary key** is the column that uniquely names a row — no duplicates, never empty. It is the
row's permanent address; everything else about the row may change, the key does not.
`AUTO_INCREMENT` tells MySQL to fill it in for you (1, then 2, …), so you never supply an `id`.
Most tables use `id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY`. Two do something else, both
deliberately: `settings` (`schema.sql:21`) uses `skey VARCHAR(64) PRIMARY KEY` — the key *is* the
setting name (`site_name`, `contact_phone`), because there is nothing else to identify a setting
by; and `image_renditions` (`:103`) uses a **composite** key, `PRIMARY KEY (image_id, preset_key,
format)`, three columns forming one address. It says "there is exactly one WebP-at-`hero_16x7`
version of image 42, and never two".

### 3.4 Foreign keys, and what `ON DELETE …` means

A **foreign key** (FK) is a column holding another table's primary key — a pointer.
`hero_slides.page_id` holds a `pages.id`, which is how a slide knows its page. MySQL then *enforces*
the pointer: you cannot store a `page_id` that does not exist, and it decides what happens when the
pointed-at row is deleted. Three behaviours exist; **our schema uses two.**

| Clause | When the parent row is deleted | In our schema? |
|---|---|---|
| `ON DELETE CASCADE` | delete the children too | **yes** — 5 places |
| `ON DELETE RESTRICT` | refuse the delete while children exist | **yes** — 13 places (and the default when no clause is written) |
| `ON DELETE SET NULL` | keep the child, blank its FK column | **no** — zero occurrences (`grep -c "SET NULL" database/schema.sql` → 0) |

Our five `CASCADE` rules — "the child is meaningless without the parent":

```sql
  CONSTRAINT fk_rend_img   FOREIGN KEY (image_id)   REFERENCES images(id)          ON DELETE CASCADE  -- :104
  CONSTRAINT fk_hero_page  FOREIGN KEY (page_id)    REFERENCES pages(id)           ON DELETE CASCADE  -- :144
  CONSTRAINT fk_entry_year FOREIGN KEY (year_id)    REFERENCES mark_years(id)      ON DELETE CASCADE  -- :230
  CONSTRAINT fk_tl_sec     FOREIGN KEY (section_id) REFERENCES school_sections(id) ON DELETE CASCADE  -- :277
  CONSTRAINT fk_ay_album   FOREIGN KEY (album_id)   REFERENCES gallery_albums(id)  ON DELETE CASCADE  -- :362
```

Every **image** FK is `RESTRICT` instead — e.g. `fk_hero_img … REFERENCES images(id) ON DELETE
RESTRICT` (`schema.sql:145`) — because an image is *shared*, and deleting one while tidying the
media library must never silently blank a hero slide on the live site. The admin panel checks first
so the editor gets a friendly message instead of a raw error; that list is `SJ_IMAGE_REFS`
(`public_html/admin/api/image.php:20`), kept in step with the FKs by hand. Note
`school_sections.page_id` (`schema.sql:263`) has **no** `ON DELETE` clause — not an omission, since
no clause means `RESTRICT`, which is what we want.

### 3.5 Indexes

Finding a row normally means reading the whole table top to bottom. An **index** is a pre-sorted
lookup list for one or more columns — a book's index versus flipping every page. Indexes make reads
fast and writes slightly slower, so you add them where you actually search. Three flavours appear
here: `PRIMARY KEY` (indexed automatically); `KEY name (cols)`, a plain index; and `UNIQUE`, an
index that *also* forbids duplicates.

**Why `UNIQUE` on `slug`.** A **slug** is the short URL-safe name of a thing: `kg`, `gal-annual`,
`tamilacademy`. Pages find their content by slug — `Repo::page()` runs `SELECT * FROM pages WHERE
slug = ?` (`src/Content/Repo.php:96`). If two `pages` rows had slug `kg`, the site would show
whichever MySQL happened to return first, and that could change between requests. `UNIQUE` makes
that state impossible to save: a correctness rule that also happens to be a speed feature. Our
`UNIQUE` columns are `admin_users.username`, `images.legacy_path`, `pages.slug`, `profiles.role_key`,
`mark_years.year`, `school_sections.slug`, `academies.slug`, `facilities.slug`,
`gallery_albums.slug` and `seo_meta.slug`, plus two **composite** ones —
`album_years (album_id, year_label)` (`:361`) and
`image_links (owner_type, owner_id, role, image_id)` (`:115`), where the *combination* must be
unique: the same year label may appear in two albums, but not twice in one album. Our plain indexes,
and the query each exists for:

| Index | Table | Serves |
|---|---|---|
| `idx_admin_when (admin_id, created_at)` | `audit_log` | "what did this admin do, newest first" |
| `idx_when (created_at)` | `audit_log` | the dashboard's recent-activity list |
| `idx_ip_time (ip, created_at)` | `contact_submissions` | the rate limit at `public_html/api/contact.php:87` |
| `idx_owner (owner_type, owner_id, role, position)` | `image_links` | fetching one carousel in order |
| `idx_page (page_id, position)` | `hero_slides` | `Repo::heroSlides()` |
| `idx_pos (position)` | `testimonials` | `Repo::testimonials()` |
| `idx_year_std (year_id, standard, position)` | `mark_entries` | `Repo::marksBoard()` |
| `idx_type (type, position)` | `achievements` | `Repo::achievements('award')` |
| `idx_sec (section_id, position)` | `timeline_entries`, `section_events` | one section's timeline / events |

InnoDB also indexes every foreign-key column automatically, so `image_id` lookups are covered
without us writing anything.

### 3.6 Normalisation, in plain words

**Normalisation** means: store each fact once, and point at it from everywhere else. The old site
stored image *filenames* as text, everywhere — `photos/annual/img12.jpg` appeared in several
files. Our schema has one `images` table, and every other table stores an **`image_id`** pointer.
That buys four things: alt text is written once (`images.alt_text`, `:74`); re-cropping works,
because the pipeline regenerates the file and bumps `images.version` (`:80`) so every page's URL
changes at once and browser caches update (`src/Media/Html.php:34`); "where is this used?" is
answerable (`image_usage()`, `public_html/admin/api/image.php:39`); and deleting is safe, thanks
to the `RESTRICT` rules above. None of that is possible when the truth is a string typed into 42
files.

## 4. How we use it — every place in this codebase

### 4.1 Our recurring column patterns

Six patterns repeat across the schema. Learn them once and most tables read themselves.

| Pattern | Looks like | What it buys us |
|---|---|---|
| `slug` | `slug VARCHAR(40) NOT NULL UNIQUE` (`:123`) | a stable, human-readable, URL-safe key. Pages look themselves up by slug, so a title can be re-worded without changing any URL. |
| `position` | `position SMALLINT UNSIGNED NOT NULL DEFAULT 0` | editor-controlled display order. Every list query ends `ORDER BY position, id` (e.g. `src/Content/Repo.php:113`), so `id` breaks ties and order is never random. **Our column is `position`, not `sort_order`** — `sort_order` appears nowhere in the repo. |
| `is_active` | `is_active TINYINT(1) NOT NULL DEFAULT 1` | hide something from the public site without deleting it. Public queries add `WHERE is_active = 1`; the admin passes `includeInactive = true` and views grey the row out instead (`views/pages/home.php:72`). |
| `image_id` | `image_id INT UNSIGNED NULL` + an FK | the pointer from §3.6. Rows with two pictures name them: `academies.card_image_id` and `academies.bg_image_id` (`:303`–`:304`). |
| `created_at` / `updated_at` | `DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, plus `ON UPDATE CURRENT_TIMESTAMP` | free bookkeeping — MySQL re-stamps `updated_at` on every change with no PHP involved. |
| `*_html` | `body_html MEDIUMTEXT NOT NULL` | **a naming contract, not a type.** A column ending `_html` holds rich text already cleaned on the way in by `sj_sanitize_html()`, so it may be echoed raw; everything else must go through `e()`. Allowed tags are frozen: `b, strong, i, em, br, p, span.hl-gold` (`src/Content/Sanitizer.php:21`). `testimonials.name_html` exists purely because shipped headings contain `<br>` (`database/migrations/004_c3_testimonials.sql:2`). |

### 4.2 Every table in `database/schema.sql`

All 27, in file order. "Used by" names the public page and/or admin screen
(`/admin/section.php?s=…`, listed at `public_html/admin/_layout.php:19`). UQ = unique.

| # | Table | What it stores | Key columns | Used by |
|---|---|---|---|---|
| 1 | `admin_users` | login accounts | `username` UQ, `password_hash`, `role`, `must_change_password`, `failed_logins`, `locked_until` | `/admin/login.php`, `/admin/password.php`, `health.php` |
| 2 | `settings` | site-wide single strings | `skey` PK, `svalue` | footer / contact / admissions partials via `repo_setting()`; admin **Site Settings** |
| 3 | `audit_log` | trail of admin actions | `admin_id`, `action`, `entity`, `entity_id`, `detail`, `ip`, `created_at` | written by `SJ\Admin\Audit`; admin dashboard |
| 4 | `contact_submissions` | contact-form enquiries | `email`, `message`, `ip`, `sent`, `created_at` | `public_html/api/contact.php` |
| 5 | `seo_meta` | per-URL `<title>` + meta description | `slug` UQ, `title`, `description` | `views/shell.php:17`; admin **SEO** |
| 6 | `images` | one row per image | `legacy_path` UQ, `alt_text`, `width`/`height`, `preset_key`, `crop_rect`, `version` | everything; admin **Media Library** |
| 7 | `image_presets` | the named crop/resize recipes | `preset_key` PK, `max_w`/`max_h`, `aspect_w`/`aspect_h`, `mode`, `quality` | `SJ\Media\Pipeline::preset()`; seeded at `schema.sql:366` |
| 8 | `image_renditions` | which resized files exist | PK `(image_id, preset_key, format)`, `bytes` | `Pipeline::renditions()`, `database/backfill.php` |
| 9 | `image_links` | many-to-many photo collections | `owner_type`, `owner_id`, `role`, `image_id`, `position` | facility / section / academy carousels, album photos |
| 10 | `pages` | one row per page shell | `slug` UQ, `title`, `heading_html`, `heading2_html` | `repo_page()` in `index.php`, `about.php`, `kg.php`, … |
| 11 | `hero_slides` | top banner slides | `page_id` FK, `image_id` FK, `caption_*`, `button_*`, `position`, `is_active` | every page with a banner; admin **Hero Carousel** |
| 12 | `profiles` | person / message blocks | `role_key` UQ, `heading`, `person_name`, `message_html`, `image_id` | home (`principal`), `about.php` (`president`/`principal`/`history`/`rules`), `staffs.php` (`staff_*`) |
| 13 | `unique_features` | "What's Unique" blocks | `title`, `body_html`, `image_id`, `position`, `is_active` | home; admin **What's Unique** |
| 14 | `ticker_items` | scrolling news links | `label`, `url`, `position`, `is_active` | home; admin **News Ticker** |
| 15 | `update_slides` | "New Updates" carousel | `title`, `subtitle`, `link_url`, `image_id`, `position` | home; admin **New Updates** |
| 16 | `testimonials` | student quotes | `name_html`, `body_html`, `position`, `is_active` | home; admin **Testimonials** |
| 17 | `mark_years` | which years the toppers board shows | `year` UQ, `is_active` | home + `highsec.php`; admin **Top Marks** |
| 18 | `mark_entries` | one topper | `year_id` FK, `standard` ENUM, `rank_label`, `student_name`, `marks_scored`, `marks_total` | same as above |
| 19 | `achievements` | achievements **and** awards | `type` ENUM, `title`, `subtext`, `image_id`, `position` | `achievements.php`; admin **Achievements** |
| 20 | `school_sections` | KG / primary / high / higher-sec | `slug` UQ, `page_id` FK, `intro_html`, `timeline_heading`, `events_heading`, `card_*` | `kg.php`, `primary.php`, `highschl.php`, `highsec.php`, `academics.php` |
| 21 | `timeline_entries` | month rows of a section timeline | `section_id` FK, `month_label`, `time_label`, `events_text`, `position` | the four section pages |
| 22 | `section_events` | event blocks of a section | `section_id` FK, `title`, `body_html`, `image_id`, `position` | the four section pages |
| 23 | `academies` | academy pages + co-curriculum cards | `slug` UQ, `banner_*`, `card_*`, `body_html`, `card_image_id`, `bg_image_id` | `tamilacademy.php` etc. + `co-curriculum.php`; admin **Academies** |
| 24 | `sports` | sport cards | `name`, `training_time`, `details_html`, `image_id`, `position` | `sports.php`; admin **Sports** |
| 25 | `facilities` | infrastructure blocks | `slug` UQ, `name`, `description_html`, `bg_image_id`, `position` | `infrastructure.php`; admin **Infrastructure** |
| 26 | `gallery_albums` | album hub cards | `slug` UQ, `title`, `heading`, `card_sub`, `card_image_id` | `gallery.php` + every `gal-*.php`; admin **Gallery Albums** |
| 27 | `album_years` | one year tab inside an album | `album_id` FK, `year_label`, `position`, UQ `(album_id, year_label)` | the `gal-*.php` pages |

### 4.3 The media tables as a set

Four tables cooperate. **`images`** holds the *fact* that an image exists — alt text, real
dimensions, `version`; `legacy_path` is `NULL` for uploads and holds the old `photos/…` path for
photos inherited from the original site, which are served untouched (`src/Media/Html.php:79`).
**`image_presets`** holds the *recipes*, seeded at `schema.sql:366`–`375`: nine named slots such as
`hero_16x7` (1920×840, cover, quality 80) and `gallery_tile` (500×700) — a slot's dimensions are
data, not a PHP constant. **`image_renditions`** holds the *results*: for image 42 at preset
`hero_16x7` there is a JPEG and a WebP, of these sizes and this many bytes. **`image_links`** is the
many-to-many table.

**Why `image_links` must exist.** A single `image_id` column can say "this facility has one
background photo". It cannot say "this album year has 40 photos, in this order" — that would need 40
columns (absurd) or 40 rows in `album_years` (wrong). So `image_links` stores one row per
*membership* (`database/schema.sql:107`):

```sql
  owner_type VARCHAR(24) NOT NULL,
  owner_id   INT UNSIGNED NOT NULL,
  role       VARCHAR(24) NOT NULL DEFAULT 'carousel',
  image_id   INT UNSIGNED NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
```

`owner_type` + `owner_id` is a *generic* pointer, so one table serves facility, section and academy
carousels plus album photos; `role` separates several collections owned by the same row; `position`
orders them. The safety catch: `owner_type` is a plain string that could name anything, so it never
reaches SQL directly — the allowed values are a fixed whitelist in `Registry::ownerTypes()`
(`src/Content/Registry.php:225`): `page`, `section`, `academy`, `facility`, `album`, `album_year`.
Reading is batched, never one query per owner — see the single `WHERE … owner_id IN (…)` at
`src/Content/Repo.php:194`.

### 4.4 The operational tables

Five tables are not "content" — they keep the system running, and four are invisible to the
public site. **`admin_users`**: one row per person who can log in; note the columns that are *not*
about identity — `failed_logins` and `locked_until` implement lock-out after repeated bad
passwords (`public_html/admin/login.php:49`), and `must_change_password` forces rotation of the
seeded password. **`audit_log`**: an append-only trail of who did what, from which IP; `admin_id`
is deliberately nullable because a *failed* login has no known user
(`database/migrations/002_audit_log.sql:7`), and its comment states that no secrets are ever
written there. **`settings`**: the key/value bag for one-off strings (phone number, footer
copyright, `marks_years_shown`), read through a per-request cache that loads the whole table once
(`src/Content/Repo.php:27`) and written only by an upsert against a hard-coded key whitelist
(`public_html/admin/api/settings.php:55`). **`seo_meta`**: one row per public URL, keyed by slug
rather than by entity, because the 42 URLs span `pages`, `academies` and `gallery_albums` — one
flat table covers them all (`database/migrations/007.sql:1`). **`contact_submissions`**: every
enquiry, plus `sent` recording whether the relay email went out, plus the `ip`+`created_at` index
powering "5 per hour per IP".

### 4.5 InnoDB, utf8mb4, and Tamil

Every table ends with `) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;`.

**`ENGINE=InnoDB`** picks the machinery underneath the table: it is the engine that supports
**foreign keys** and **transactions** (all-or-nothing groups of writes). The older MyISAM engine
silently *ignores* foreign-key clauses, so every guarantee in §3.4 would quietly evaporate. A
requirement, not a preference.

**`CHARSET=utf8mb4`** is the character set — which characters are storable at all. MySQL's
confusingly named `utf8` is a crippled 3-byte version that cannot store any character needing 4
bytes; `utf8mb4` is real, complete UTF-8. Why that matters here: this is a school in Tamil Nadu,
with a Tamil Academy page, and content is typed by a human into a browser. Tamil letters (உ, ங, க)
live outside ASCII; emoji need four bytes. Under plain `utf8` an emoji pasted into a testimonial
either errors or truncates the rest of the string; under `utf8mb4` it just works. *(Today's seeded
content is entirely English — `grep` finds no Tamil codepoints in the repo — so this protects what
the editor types next, rather than describing current data.)* **`COLLATE=utf8mb4_unicode_ci`** is
the comparison rule; `ci` = case-insensitive, so `WHERE slug = 'KG'` still finds `kg`. All three
layers must agree or text is mangled in between: `database/schema.sql:3` opens with
`SET NAMES utf8mb4`, and the PHP connection string matches — `charset=utf8mb4`, `src/Core/Db.php:48`.

### 4.6 The registry rule: `admin_users` and `settings` are never registered

`src/Content/Registry.php` is the single list of what the admin panel may edit; each entry names a
table and its editable fields. A browser request sends an *entity name* like `sport`, and the
server looks it up there to find the table and column — **a request can never name a table or
column itself.** `CLAUDE.md:66` states the rule: "any new editable field/entity must be
registered; `admin_users` and `settings` are **never** registered", and the registry's own header
repeats it (`src/Content/Registry.php:9`).

The reason is blast radius. The generic field/item API can write to any registered table. If
`admin_users` were registered, one bug or one forged request in that generic endpoint could
rewrite a `password_hash` or clear `must_change_password` — turning a content bug into an account
takeover. If `settings` were registered, the same endpoint could write arbitrary keys. So both get
**dedicated, narrower code paths**: passwords only via `/admin/password.php`, settings only via
`/admin/api/settings.php`, which validates against a hard-coded list of allowed keys before its
upsert. Narrow doors are easier to guard than one wide one.

## 5. Why this is the right approach here

"Many purpose-built tables" is not the only option. Three real alternatives:

**A — one big key/value `content` table**, rows like `('home.principal.message', '…')`. Tempting:
you never write DDL again. It fails four ways. No type safety (everything is `TEXT`). No ordering
or grouping, so "the sports cards, in order, only the visible ones" becomes string parsing in PHP.
No foreign keys, so nothing stops a dangling image reference. And rendering one page becomes many
lookups or one giant fetch-and-filter — straight against our **≤ 12 SQL queries per page** budget
(`CLAUDE.md:69`). Our content is heterogeneous: a topper row (`year`, `standard`, `rank_label`,
`marks_scored`, `marks_total`) has nothing in common with a hero slide; a key/value table pretends
they are the same and pushes the real structure into PHP.

**B — a document store (MongoDB and friends).** Genuinely a good fit for heterogeneous content, and
ruled out by the hosting: production is **MilesWeb shared hosting, mPanel, no SSH** (`CLAUDE.md`).
We get MySQL 8 and a web-based DB tool; we cannot install a database server. That decides it alone.

**C — flat files or JSON on disk.** No database; the admin panel edits JSON. This *almost* works for
one editor, then breaks: two concurrent saves can lose one another (no transactions), no index means
"which pages use image 42" reads every file, backups become a directory diff, and the write path
becomes "PHP writes files into the webroot" — a deployment headache and exactly the pattern our
security rules push back on.

| Deciding constraint | Consequence |
|---|---|
| MySQL 8 on shared hosting, no SSH | relational or nothing; migrations must be pasteable into a web DB tool |
| One non-technical editor | the shape must produce *understandable* admin screens — a list of sports, not a list of key/value pairs |
| 42 pages of very heterogeneous content | per-entity tables; a generic bag pushes all structure into PHP |
| ≤ 12 SQL queries per page | ordering, filtering and joining must happen in SQL, which needs real columns and indexes |
| Additive-only migrations | a shape you extend by adding columns, not by rewriting rows |

That last one shows in every migration shipped so far: `005` and `006` are single
`ALTER TABLE … ADD COLUMN` statements; `002`, `003`, `004` and `007` are
`CREATE TABLE IF NOT EXISTS`. Nothing has ever been dropped or renamed — which is what lets us
roll code back without rolling the database back (`PHASES.md:172`).

## 6. How this scales

Suppose the school grows 10×: ~110 albums, ~5,000 photos, ~400 pages.

**Which tables grow, and which indexes matter.** Row counts scale with photos, not pages: `images`,
`image_links`, and `image_renditions` (two rows — JPEG + WebP — per image per preset; today 988 rows
from 494 rendition sets, `PHASES.md:142`). `gallery_albums` and `album_years` grow modestly; the
content tables barely move; `audit_log` and `contact_submissions` grow with *time*, independent of
content. `image_links.idx_owner (owner_type, owner_id, role, position)` carries the load — it turns
"the photos of album-year 88, in order" into an index range scan instead of a scan over 5,000 rows.
`contact_submissions.idx_ip_time` keeps the rate limit cheap; `audit_log.idx_when` keeps the
dashboard cheap.

**What breaks first — and it is not an index.** `Pipeline::renditions()` loads the *entire*
`image_renditions` table once per request: `foreach (db()->query('SELECT * FROM image_renditions')
as $r) {` — `src/Media/Pipeline.php:45`. Its own comment says "a few hundred small rows", true
today. At 10× that is ~10,000 rows pulled into PHP memory on **every page view**, including pages
showing three images. It stays inside the query *budget* (it is one query) while quietly becoming
the slowest thing on the site — a good lesson that a query count is a proxy, not the goal.

**The exact next step**, when photo count passes roughly 2,000: bound that load. Collect the image
ids the page actually needs, then fetch `SELECT * FROM image_renditions WHERE image_id IN (…)` —
still one query, but proportional to the page. No schema change is required: the composite primary
key `(image_id, preset_key, format)` already makes that an index lookup, and the per-image refresh
path at `src/Media/Pipeline.php:52` shows the query shape.

**After that, in order.** (1) *Pagination* — album pages already partition photos by year
(`album_years`), so load one year at a time rather than inventing page numbers. (2) *Archiving* —
`audit_log` and `contact_submissions` are the only ever-growing tables, both indexed on
`created_at`, so archiving is a dated `INSERT … SELECT` into an archive table plus a `DELETE`:
additive, and it fits the deploy rules. (3) *Object storage* — only when disk or bandwidth becomes
the limit. The schema is ready: image URLs are built in exactly one place (`src/Media/Html.php:34`),
so moving to a CDN changes that helper and nothing else, because `images` never stores a full URL.

## 7. Gotchas and mistakes to avoid

**7.1 Nullable FK vs required — pick one, then say it twice.** `hero_slides.image_id` is `NOT NULL`
(`schema.sql:134`); `profiles.image_id` is `NULL` (`:155`). Both are right: a slide *is* a picture,
a profile block can exist without a portrait. The trap is that the database rule and the admin rule
live in **two different files**, and nothing checks they agree. `Registry.php:40` marks the hero
image `'required' => true`; `Registry.php:50` leaves the profile portrait optional. Make a column
`NOT NULL` and forget the registry flag, and the panel will happily submit an Add form with no
image — and the insert dies with a raw SQL error. Change one, change the other.

**7.2 Forgetting `position` on a new orderable table.** Set `'orderable' => true` in the registry
and the admin list grows ↑/↓ buttons and the reorder API starts writing a `position` column. If
your new table has no `position` column, that write fails. And even with the column, if your read
query forgets `ORDER BY position, id` the buttons appear to do nothing — the row moves in the
database and the page renders in whatever order MySQL felt like. **Note the `, id`**: without a
tie-break, rows sharing `position 0` (the default for everything freshly inserted) come back in an
unstable order. Every list method in `Repo.php` ends `ORDER BY position, id`. Copy that.

**7.3 `TINYINT(1)` is not a real boolean.** MySQL has no boolean type. `TINYINT(1)` is a one-byte
integer and the `(1)` is only a display hint — it does **not** limit the value.
`UPDATE sports SET is_active = 2` is accepted, and every public query says `WHERE is_active = 1`,
so the row silently vanishes from the site with no error anywhere. What protects us is the write
path, which forces 0 or 1 before SQL sees it — `public_html/admin/api/_bootstrap.php:109`:

```php
        case 'bool':
            return !empty($value) && $value !== '0' ? 1 : 0;
```

On the read side, views test with `empty($f['is_active'])` (`views/pages/home.php:72`) rather than
`=== 1`, so they behave correctly whether the driver returns the integer `0` or the string `"0"`.
Keep both habits.

**7.4 `ENUM` is cheap to create and painful to extend.** `mark_entries.standard ENUM('10','11','12')`
is a good fit — Indian school standards will not be renamed. But if the school ever publishes
9th-standard toppers, adding `'9'` touches **four** places, and missing any one is a silent bug:
(1) `database/schema.sql:221`, for fresh installs; (2) a new `ALTER TABLE mark_entries MODIFY
standard ENUM('9','10','11','12')` migration for the live database; (3) `src/Content/Registry.php:204`,
the admin dropdown's `'values'` list; (4) `src/Content/Repo.php:324` **and** `:337` — the ordering
`FIELD(standard, "12","11","10")` and the display loop `foreach (['12', '11', '10'] as $std)`, where
a row with standard `9` would be saved, stored, and then **never rendered**. The general lesson:
`ENUM` bakes a list into the table definition *and* leaks copies of it into the code. If a set is
likely to grow, use a small lookup table with a foreign key instead — which is exactly why
`mark_years` is a table and not an enum.

**7.5 `DATETIME` has no time zone, and our containers run UTC.** `DATETIME` stores wall-clock digits
with **no** zone attached, and `DEFAULT CURRENT_TIMESTAMP` means "whatever time the MySQL server
thinks it is". Nothing in this repo sets a time zone: no `date_default_timezone_set()` anywhere in
PHP, no `TZ` in `Dockerfile` or `docker-compose.yml`. So both containers run on their default, UTC —
while the school is in IST, **UTC+5:30**. Comparisons *inside* SQL are safe because both sides share
one clock, so the rate limit `created_at > NOW() - INTERVAL 1 HOUR`
(`public_html/api/contact.php:87`) is correct; but anything **shown to a human** is 5½ hours behind
local time — an enquiry submitted at 9:00 am in Coimbatore is stamped `03:30`. Do not "fix" that by
shifting the stored value; convert on display, once. Production is a different machine with its own
setting, so check rather than assume: `SELECT @@global.time_zone, @@session.time_zone, NOW();`

**7.6 Two smaller ones.** The comment at `database/schema.sql:2` says the file is loaded by "the
MariaDB container"; it is actually `mysql:8.0` (`docker-compose.yml:3`) — harmless, but not evidence
about which server you are on. And `CREATE TABLE IF NOT EXISTS` makes the schema safe to re-run,
but `ALTER TABLE … ADD COLUMN` has **no** `IF NOT EXISTS` in MySQL 8, which is why migration `001`
tells you to skip the statement if it errors (`database/migrations/001_admin_users_roles.sql:7`).

## 8. Try it yourself

Start the stack — everything runs in Docker, so there is no PHP or MySQL on your machine.

```bash
cd /home/aswin-25449/newDrive/Stjosephs_Website
./run.sh
```

`run.sh` builds the containers, waits for the database, refreshes the autoloader, runs the seeder,
and prints the URLs (public `http://localhost:8090/`, admin `http://localhost:8090/admin/`). The
database service is called **`db`** (`docker-compose.yml:2`) and the dev credentials come from
`.env.example`: user `stjosephs`, password `stjosephs_pw`, database `stjosephs` — that file states
plainly they are throwaway values for a container bound to `127.0.0.1`, not production secrets.

Set `M="docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e"` to save typing, or
paste the full command each time.

**1. Inspect the shapes.** `DESCRIBE` prints the live column list, types, nullability and keys;
`SHOW CREATE TABLE` also spells out indexes and foreign keys. Compare both with `schema.sql`.

```bash
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e "SHOW TABLES;"
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e "DESCRIBE images;"
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e "DESCRIBE hero_slides;"
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e "SHOW CREATE TABLE image_links\G"
```

**2. Read real rows, and follow a foreign key by hand** — which is what a JOIN does: read the
pointer, then read the row it points at. `SELECT <columns> FROM <table>` is the read command and
`LIMIT n` caps the rows. The second query prints the nine recipes seeded at `schema.sql:366`; take
an `image_id` from the third and look it up with the fourth.

```bash
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e "SELECT id, slug, title FROM pages ORDER BY id LIMIT 10;"
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e "SELECT preset_key, label, max_w, max_h, mode, quality FROM image_presets;"
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e "SELECT id, page_id, image_id, caption_title, position, is_active FROM hero_slides ORDER BY page_id, position LIMIT 8;"
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e "SELECT id, legacy_path, alt_text, width, height, version FROM images WHERE id = 1;"
```

**3. Watch `RESTRICT` protect you**, and check the time-zone claim from §7.5. The `DELETE` is
refused with a foreign-key constraint error — that refusal *is* §3.4 working, and nothing changes,
so there is nothing to undo.

```bash
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e "DELETE FROM images WHERE id = 1;"
docker compose exec db mysql -ustjosephs -pstjosephs_pw stjosephs -e "SELECT @@global.time_zone, NOW();"
```

Stop with `./run.sh stop`, or `./run.sh reset` to wipe the database volume and start clean.

> Everything above only **reads** (except step 3, which is refused). Never change content by typing
> SQL — go through the admin panel, so the sanitiser, the audit log and the image guards all run.

## 9. Where to read more

**In this repo, in this order:**

- [`../01-fundamentals.md`](../01-fundamentals.md) — the ground floor: the web, PHP, SQL basics,
  PDO. Read first if `SELECT` is still new.
- [`../05-stage-e.md`](../05-stage-e.md) — Stage E: how content actually moved into these tables and
  how the admin panel learned to edit them.
- [`../../../PHASES.md`](../../../PHASES.md) — the roadmap; the query-budget rule at line 159, the
  release/migration procedure at line 169.
- [`../../../CLAUDE.md`](../../../CLAUDE.md) — the binding rules: registry (line 66), performance
  budget (line 69), additive-only migrations (line 88).
- [`../../../DYNAMIC_MIGRATION_PLAN.md`](../../../DYNAMIC_MIGRATION_PLAN.md) — the design document
  this schema was built from (§3 DDL, §4 read path, §5.4 registry).
- `database/schema.sql` itself — 380 commented lines. Read it end to end once; you will recognise
  every construct now.

**Outside:**

- [MySQL 8.0 Data Types](https://dev.mysql.com/doc/refman/8.0/en/data-types.html) — what each type
  can hold.
- [MySQL 8.0 FOREIGN KEY Constraints](https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html)
  — exact semantics of `CASCADE`, `RESTRICT`, `SET NULL`.
- [MySQL 8.0 Optimization and Indexes](https://dev.mysql.com/doc/refman/8.0/en/optimization-indexes.html)
  — how indexes are chosen, and how to read `EXPLAIN`.
- [Database Normalization (Wikipedia)](https://en.wikipedia.org/wiki/Database_normalization) — the
  formal version of §3.6.
