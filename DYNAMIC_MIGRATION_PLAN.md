# DYNAMIC_MIGRATION_PLAN.md — Converting St. Joseph's MHSS to a database-driven site

> ⚠️ **Superseded decisions (2026-08-01).** The roadmap, architecture, and locked decisions now live in **`PHASES.md`** (see also `SECURITY.md`, `ADMIN_UI_DESIGN.md`, `CLAUDE.md`). This file remains the **reference for the DB schema (§3), file layout intent, and seed inventory (§6)** — still accurate — but its "Locked decisions" below are replaced by `PHASES.md` §0: (1) modern **structured PHP 8.3 + Composer PSR-4** (not flat PHP); (2) **hybrid admin** — standalone dashboard **plus** on-page overlay (not overlay-only); (3) **same look, clean code** — one Bootstrap 5.3.3, valid single-document HTML, consolidated CSS, all 15 bugs fixed (not byte-for-byte preservation); (4) hosting is **MilesWeb shared mPanel** with a dedicated performance/site-health workstream; (5) schema is **multi-user-ready** (`role` column) though a single admin is used now. Read `PHASES.md` first.
>
> Companion documents: `WEBSITE_CONTEXT.md` (complete inventory of the current static site — the data source for seeding) and `FEATURES_PLAN.md` (upload auto-crop/compress pipeline + keyboard features).
>
> **Locked decisions (SUPERSEDED — see the note above):** MySQL/MariaDB via PDO · keep flat PHP, no framework · convert the 13 listed areas + every page-top hero carousel (everything else stays static for now, but the schema/admin are built so any future area is "one table + one registry entry + one template loop") · single admin account with session auth.

---

## 1. Goals & Scope

### 1.1 What "dynamic" means here

- Public pages render the **same HTML as today** (same classes, same Bootstrap dialect per page family) but the repeated/editable parts come from MySQL instead of hard-coded markup.
- **All legacy URLs keep working** (`tamilacademy.php`, `gal-annual.php`, `kg.php` … are preserved as stubs).
- An **admin panel** lets the admin open any page *looking exactly like the public site*, with an editing overlay to add/remove/edit/reorder images and edit texts in place. Saving writes to the DB; the public site shows the change on the next refresh (there is no cache layer in between).

### 1.2 The 13 dynamic areas mapped to today's code

| # | Area (user requirement) | Current location | New table(s) |
|---|---|---|---|
| 1 | Principal image + data | `index.php` principal block + `about.php` principal block | `profiles` (one shared row — editing once updates both pages) |
| 2 | What's Unique | `index.php` `.newtemp-body` (ESC + Language Academies blocks) | `unique_features` |
| 3 | New Updates + scrolling ticker | `_templates/new-updates.php` (3 video slides) + `_templates/update-scroll.php` (3 marquee links) | `update_slides`, `ticker_items` |
| 4 | Top Marks (3 years shown, updatable; year → 10th/11th/12th → marks) | `_templates/marks-scroll.php` (used by index + highsec) | `mark_years`, `mark_entries` (standard ENUM supports 10/11/12; site shows the **latest 3 active years**) |
| 5 | Achievements — image, text, subtext | `achievements.php` zig-zag list (10 items) | `achievements` with `type='achievement'` |
| 6 | Awards given by St.Joseph's — image, text, subtext | `achievements.php` certificates (4 items) | `achievements` with `type='award'` |
| 7 | Academics + timeline + events of KG / Primary / High School / Higher Secondary | `academics.php` 4 grade cards + `kg.php`/`primary.php`/`highschl.php`/`highsec.php` (intro, Timeline-2024 accordion, event cards) | `school_sections`, `timeline_entries`, `section_events` (+ inner carousels via `image_links`) |
| 8 | Co-curriculum academies — heading, content (with bold/gold highlights), carousel images | `co-curriculum.php` 18 cards + 18 detail pages (15 academies + band/ncc/artandexpo) | `academies` + `image_links('academy', …, 'carousel')` |
| 9 | Sports — heading + card image + text | `sports.php` (9 cards with collapse details) | `sports` (+ page heading in `pages.heading_html`) |
| 10 | Gallery — card (thumbnail + title) → years (2023 / 2024 separate) → images | `gallery.php` 10 album cards + 11 `gal-*.php` pages (photo grid + lightbox + year toggle) | `gallery_albums` → `album_years` → `image_links('album_year', …, 'photos')` |
| 11 | Gallery central carousel images | `_templates/gal-slider.php` (15 cross-fade slides) | `image_links('page', pages['gallery'], 'gal_slider')` |
| 12 | Home page carousel | `_templates/carousel.php` (7 slides + captions + Explore button) | `pages['index']` + `hero_slides` |
| 13 | Infrastructure — add/edit facility cards (text, subtext, carousel images) | `infrastructure.php` (15 `bg-N` sections + anchor nav) | `facilities` + `image_links('facility', …, 'carousel')` — **admin can add new facilities**; nav is generated (fixes the off-by-one bug) |
| — | **Every page's top hero carousel** — images AND caption title/tagline | `.abt-carousel`/`.academy-carousel`/`.{page}-carousel` on about, academics, achievements, co-curriculum, infrastructure, sports, staffs (2 slides), kg, primary, highschl, highsec | `pages` + `hero_slides` (per-slide caption/tagline; per-page rows) |

### 1.3 Explicitly out of scope (stays static, addable later)

Testimonials, fun-fact counters, motto/campus cards, about-page President/History/Rules blocks, Groups Offered, footer/contact info, jumbotron, navbar. Each would later need only: one table (or rows in an existing one), one registry entry, one template loop.

---

## 2. Architecture

### 2.1 Read path (public site)

```
page.php ──include──▶ _libs/load.php (bootstrap: config, db, repo, media, edit helpers)
   │
   ├─ repo_*() functions (_libs/repo.php) ──PDO──▶ MySQL
   │        return plain arrays
   └─ templates/renderers loop over arrays and emit the CURRENT markup verbatim
             images via img_tag()/bg_style() (_libs/media.php)
```

- The current HTML is the canonical baseline. Conversion = copy the existing markup, replace each repeated item with a `foreach` over a repo array. Carousel indicators, `active` classes, zig-zag `reverse` classes, left/right alternation are all derived from the loop index — exactly the patterns the static files already follow.
- `get_templates()` and every template filename stay unchanged.

### 2.2 Write path (admin)

```
Admin browses the REAL page URL (e.g. /infrastructure.php)
   │  footer.php includes _templates/admin-bar.php when is_admin()
   ▼
Edit-mode ON  ⇒ templates emit data-edit-* attributes (empty string for public visitors)
   ▼
admin/assets/admin.js decorates the live DOM: ✏ on texts, 📷 on image slots,
"Manage photos (n)" on collections, ＋ on lists, ✕ / ↑ / ↓ on items
   ▼
fetch() → admin/api/*.php  (session + CSRF guarded, registry-validated)
   ▼
MySQL updated → text edits patch the DOM in place; structural changes reload the page
              → public site shows the change on its next refresh
```

**Why this satisfies the "admin sees the page same as the actual webpage" requirement by construction:** the admin is literally viewing the public PHP output — same templates, same CSS — with an overlay on top. There is no separate admin re-implementation of the pages that could drift.

### 2.3 Image model — immutable images

- One `images` row per physical file. The **478 existing files stay in `photos/` untouched**, referenced via `images.legacy_path`. New uploads live at `/media/{image_id}/{preset}.{jpg|webp}` plus the retained original.
- **Replacing** a slot's photo = upload creates a *new* row → new URL → browser caches invalidate automatically.
- **Re-cropping** an existing upload regenerates renditions in place and bumps `images.version`; `img_url()` appends `?v={version}`.
- Deleting an image that is still used anywhere is blocked at the DB level (FK `RESTRICT`).

---

## 3. Database Schema (MySQL DDL)

Conventions: InnoDB, `utf8mb4_unicode_ci`. Every table also has `created_at DATETIME DEFAULT CURRENT_TIMESTAMP` and `updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP` (omitted below for brevity). `position SMALLINT UNSIGNED NOT NULL DEFAULT 0` is the ordering column everywhere; reordering rewrites 0..n-1.

### 3.1 Design decision — one polymorphic `image_links` table

**Rule:** collections whose items are *just an image* (academy/facility/section carousels, album-year photo grids, the gallery highlight slider) all live in **one** polymorphic table `image_links(owner_type, owner_id, role, image_id, position)`. Collections whose items carry their **own text/url fields** (hero slides, update slides, achievements, sports, event cards, unique features, profiles) are **entity tables with a direct `image_id` FK column**.

Why: the site has ~40+ plain image collections across 5 owner kinds; per-entity join tables would mean five structurally identical tables and 5× duplicated attach/detach/reorder code. One table gives one repo function, one API endpoint, and one admin "Manage photos" modal for all of them — and adding a future image collection needs zero schema change. The cost (no DB-level FK on `owner_id`) is contained: `owner_type` values are whitelisted in the PHP registry, owner deletion goes through repo helpers that also delete links, `image_id` keeps a real `RESTRICT` FK, and `database/integrity-check.php` reports orphans.

**Owner-type map:** `page` → `pages.id` (role `gal_slider`) · `academy` → `academies.id` (`carousel`) · `facility` → `facilities.id` (`carousel`) · `section` → `school_sections.id` (`carousel`) · `album_year` → `album_years.id` (`photos`).

### 3.2 Auth, settings, media

```sql
CREATE TABLE admin_users (
  id            TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,              -- password_hash() (bcrypt/argon2id)
  display_name  VARCHAR(100) NOT NULL DEFAULT 'Administrator',
  failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until  DATETIME NULL,
  last_login_at DATETIME NULL
);

CREATE TABLE settings (
  skey   VARCHAR(64) PRIMARY KEY,   -- 'marks_years_shown' = '3', 'force_password_change' = '1', …
  svalue TEXT NOT NULL
);

CREATE TABLE images (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  legacy_path   VARCHAR(255) NULL UNIQUE,   -- '/photos/tamaca2.jpg' for existing files; NULL for uploads
  original_name VARCHAR(255) NULL,          -- sanitized uploaded filename (admin display only)
  alt_text      VARCHAR(255) NOT NULL DEFAULT '',
  mime          VARCHAR(32)  NULL,
  width         SMALLINT UNSIGNED NULL,
  height        SMALLINT UNSIGNED NULL,
  preset_key    VARCHAR(40)  NULL,          -- preset it was uploaded for
  crop_rect     VARCHAR(64)  NULL,          -- 'x,y,w,h' used (enables later re-crop)
  version       SMALLINT UNSIGNED NOT NULL DEFAULT 1   -- bumped on re-crop => ?v= cache-bust
);

CREATE TABLE image_presets (                 -- full preset list + pipeline: FEATURES_PLAN.md §2
  preset_key VARCHAR(40) PRIMARY KEY,
  label      VARCHAR(80)  NOT NULL,
  max_w      SMALLINT UNSIGNED NOT NULL,
  max_h      SMALLINT UNSIGNED NOT NULL,
  aspect_w   TINYINT UNSIGNED NULL,          -- NULL = free aspect
  aspect_h   TINYINT UNSIGNED NULL,
  mode       ENUM('cover','fit') NOT NULL DEFAULT 'cover',  -- cover = center-crop, fit = contain
  quality    TINYINT UNSIGNED NOT NULL DEFAULT 82
);

CREATE TABLE image_renditions (              -- generated files (uploads only)
  image_id   INT UNSIGNED NOT NULL,
  preset_key VARCHAR(40)  NOT NULL,
  format     ENUM('jpeg','webp') NOT NULL,
  width      SMALLINT UNSIGNED NOT NULL,
  height     SMALLINT UNSIGNED NOT NULL,
  bytes      INT UNSIGNED NOT NULL,
  PRIMARY KEY (image_id, preset_key, format),
  FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE
);

CREATE TABLE image_links (                   -- ALL plain ordered image collections
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_type VARCHAR(24) NOT NULL,           -- 'page'|'academy'|'facility'|'section'|'album_year'
  owner_id   INT UNSIGNED NOT NULL,
  role       VARCHAR(24) NOT NULL DEFAULT 'carousel',  -- 'carousel'|'photos'|'gal_slider'
  image_id   INT UNSIGNED NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_member (owner_type, owner_id, role, image_id),
  KEY idx_owner (owner_type, owner_id, role, position),
  FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
);
```

### 3.3 Pages & hero carousels (areas 12 + "all pages")

```sql
CREATE TABLE pages (
  id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(40) NOT NULL UNIQUE,
  -- 'index','about','academics','achievements','co-curriculum','infrastructure',
  -- 'sports','staffs','gallery','kg','primary','highschl','highsec'
  title         VARCHAR(120) NOT NULL DEFAULT 'St.Joseph''s MHSS, Ondipudur',
  heading_html  VARCHAR(255) NULL,   -- the .home-text band, e.g. 'The <span>Sports</span> in St.Joseph''s'
  heading2_html VARCHAR(255) NULL    -- achievements' second band: 'The <span>Awards given by</span> St.Joseph''s'
);

CREATE TABLE hero_slides (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_id       SMALLINT UNSIGNED NOT NULL,
  image_id      INT UNSIGNED NOT NULL,
  caption_title VARCHAR(120) NOT NULL DEFAULT '',  -- h5: 'St.Joseph''s' / 'Our Infrastructure' / 'Kinder Garten'
  caption_text  VARCHAR(255) NOT NULL DEFAULT '',  -- p tagline (per-slide: infrastructure rotates 3 taglines)
  button_label  VARCHAR(40)  NULL,                 -- 'Explore' (home hero only; NULL hides the button)
  button_url    VARCHAR(255) NULL,                 -- 'about.php'
  position      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_page (page_id, position),
  FOREIGN KEY (page_id)  REFERENCES pages(id)  ON DELETE CASCADE,
  FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
);
```

Covers the home hero (7 slides with captions + Explore button) and every other page-top carousel — about/academics/achievements/co-curriculum/infrastructure/sports (3 slides each), staffs (2 slides), kg/primary/highschl/highsec (3 each). `gallery` has no hero (heading text only → `pages.heading_html`).

### 3.4 Content tables (areas 1–10, 13)

```sql
-- Area 1: principal (and president, for later reuse)
CREATE TABLE profiles (
  id           TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_key     VARCHAR(32) NOT NULL UNIQUE,   -- 'principal' (seeded), 'president' (future)
  heading      VARCHAR(60)  NOT NULL,         -- h3 'Principal'
  person_name  VARCHAR(120) NOT NULL,         -- h2 'Rev.Fr.Kirubakaranathan'
  message_html MEDIUMTEXT   NOT NULL,         -- paragraphs (sanitized subset, see §5.5)
  image_id     INT UNSIGNED NULL,
  FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
);

-- Area 2: What's Unique?
CREATE TABLE unique_features (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title     VARCHAR(150) NOT NULL,            -- 'Extended School Concept(ESC)'
  body_html MEDIUMTEXT   NOT NULL,            -- supports line breaks / bullet-style lines
  image_id  INT UNSIGNED NULL,
  position  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
);
-- image side alternates by position parity (even = image left, odd = image right) as in today's DOM

-- Area 3: ticker + updates carousel
CREATE TABLE ticker_items (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label     VARCHAR(200) NOT NULL,            -- 'Mini Auditorium Inauguration is live..!!'
  url       VARCHAR(255) NOT NULL,
  position  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE update_slides (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title      VARCHAR(120) NOT NULL,           -- 'EXPRESSIONZ 2026'
  subtitle   VARCHAR(200) NOT NULL,           -- 'ExpressionZ'
  link_url   VARCHAR(255) NULL,               -- YouTube link
  link_label VARCHAR(40)  NOT NULL DEFAULT 'View More',
  image_id   INT UNSIGNED NOT NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
);

-- Area 4: Top Marks
CREATE TABLE mark_years (
  id        SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year      SMALLINT UNSIGNED NOT NULL UNIQUE,       -- 2024, 2025, 2026, …
  is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE mark_entries (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year_id      SMALLINT UNSIGNED NOT NULL,
  standard     ENUM('10','11','12') NOT NULL,        -- 11th supported per requirement
  rank_label   VARCHAR(8)   NOT NULL,                -- 'I','II','III' (duplicates allowed — 2024 has two IIIs)
  student_name VARCHAR(120) NOT NULL,
  marks_scored SMALLINT UNSIGNED NOT NULL,           -- 593
  marks_total  SMALLINT UNSIGNED NOT NULL,           -- 600 / 500
  position     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_year_std (year_id, standard, position),
  FOREIGN KEY (year_id) REFERENCES mark_years(id) ON DELETE CASCADE
);
-- Display query: latest N active years (N = settings.marks_years_shown, default 3)
-- ORDER BY year DESC LIMIT N, rendered oldest→newest as columns. Within a column,
-- standards render in fixed order 12, 11, 10 (a standard with no entries is skipped).
-- 'Toppers - YYYY' / 'NTH STD' header rows are generated, not stored.

-- Areas 5 + 6: achievements & awards (identical shape, one table)
CREATE TABLE achievements (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  type      ENUM('achievement','award') NOT NULL,    -- award = 'Awards given by St.Joseph''s'
  title     VARCHAR(150) NOT NULL,
  subtext   VARCHAR(255) NOT NULL DEFAULT '',
  image_id  INT UNSIGNED NULL,
  position  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  KEY idx_type (type, position),
  FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
);
-- Renderer chunks rows into .achieve-container pairs; odd index gets .reverse
-- => the zig-zag layout survives any item count.

-- Area 7: school sections
CREATE TABLE school_sections (
  id               TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug             VARCHAR(20) NOT NULL UNIQUE,      -- 'kg','primary','highschl','highsec' (= legacy filenames)
  page_id          SMALLINT UNSIGNED NOT NULL,       -- hero slides + heading band live on the page row
  name             VARCHAR(80) NOT NULL,             -- 'Kinder Garten'
  intro_heading    VARCHAR(80) NOT NULL,             -- h4 in infra-new-text
  intro_html       MEDIUMTEXT  NOT NULL,
  timeline_heading VARCHAR(60) NOT NULL DEFAULT 'Timeline - 2024',
  card_title       VARCHAR(80) NOT NULL,             -- academics.php card: 'Kinder Garten(KG)'
  card_range       VARCHAR(40) NOT NULL,             -- 'LKG-UKG'
  card_image_id    INT UNSIGNED NULL,
  position         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (page_id) REFERENCES pages(id),
  FOREIGN KEY (card_image_id) REFERENCES images(id) ON DELETE RESTRICT
);
-- academics.php's 4 grade cards render from these rows; inner carousel images:
-- image_links('section', id, 'carousel'); the 'Events of …' band = pages.heading_html.

CREATE TABLE timeline_entries (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id  TINYINT UNSIGNED NOT NULL,
  month_label VARCHAR(20) NOT NULL,                  -- 'JUNE'
  time_label  VARCHAR(30) NOT NULL DEFAULT '2024 - present',
  events_text TEXT NOT NULL,                         -- ONE EVENT PER LINE; renderer joins with <br>
  position    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_sec (section_id, position),
  FOREIGN KEY (section_id) REFERENCES school_sections(id) ON DELETE CASCADE
);
-- direction-r / direction-l alternates by position parity, exactly as today.

CREATE TABLE section_events (                        -- the event cards below the timeline
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_id TINYINT UNSIGNED NOT NULL,
  title      VARCHAR(120) NOT NULL,                  -- 'Orange Day'
  body_html  TEXT NOT NULL,
  image_id   INT UNSIGNED NULL,
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_sec (section_id, position),
  FOREIGN KEY (section_id) REFERENCES school_sections(id) ON DELETE CASCADE,
  FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
);

-- Area 8: academies (18 rows: 15 academies + band + ncc + artandexpo)
CREATE TABLE academies (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug            VARCHAR(40) NOT NULL UNIQUE,   -- EXACT legacy basename: 'tamilacademy','band','ncc','artandexpo',…
  card_title      VARCHAR(100) NOT NULL,         -- co-curriculum card: 'Academy of Tamil'
  card_subtitle   VARCHAR(120) NOT NULL,         -- 'Valanar Ilakkiya Mandram'
  banner_title    VARCHAR(100) NOT NULL,         -- back-bar h4 (may differ from card_title)
  banner_subtitle VARCHAR(120) NOT NULL,         -- back-bar italic p
  content_heading VARCHAR(100) NOT NULL,         -- infra-new-text h4 (differs again, e.g. 'Science Academy')
  body_html       MEDIUMTEXT   NOT NULL,         -- rich content; gold/bold highlights as <span class="hl-gold">/<strong>
  card_image_id   INT UNSIGNED NULL,
  bg_image_id     INT UNSIGNED NULL,             -- dark-overlay section background photo
  position        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active       TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (card_image_id) REFERENCES images(id) ON DELETE RESTRICT,
  FOREIGN KEY (bg_image_id)   REFERENCES images(id) ON DELETE RESTRICT
);
-- 3+ carousel images per academy: image_links('academy', id, 'carousel').
-- co-curriculum.php chunks active rows into .row groups of 3.

-- Area 9: sports
CREATE TABLE sports (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(80)  NOT NULL,
  training_time VARCHAR(80)  NOT NULL,           -- 'Training Time 3.30-5.00p.m' / '(MON-TUE-WED) 6.00-7.00p.m'
  details_html  TEXT         NOT NULL,           -- the collapse ('Read More') content
  image_id      INT UNSIGNED NULL,
  position      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE RESTRICT
);
-- Renderer chunks into rows of 3 and generates UNIQUE accordion ids (accordion-{id})
-- — fixes today's duplicated id="accordion".

-- Area 13: infrastructure facilities
CREATE TABLE facilities (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug             VARCHAR(40) NOT NULL UNIQUE,  -- 'smart-classrooms' → anchor id 'fac-smart-classrooms'
  name             VARCHAR(80) NOT NULL,         -- nav button label + section h4
  description_html TEXT NOT NULL,
  bg_image_id      INT UNSIGNED NULL,            -- replaces the static css .bg-N background
  position         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (bg_image_id) REFERENCES images(id) ON DELETE RESTRICT
);
-- Carousel: image_links('facility', id, 'carousel'). Anchor nav AND the sections are
-- emitted from the SAME ordered loop with slug anchors — the off-by-one bug becomes
-- structurally impossible. infra-new / infra-new1 mirroring alternates by parity.
-- Admin can ADD new facility cards (creatable entity).

-- Areas 10 + 11: gallery
CREATE TABLE gallery_albums (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(40) NOT NULL UNIQUE,     -- 'annual','sports','independence','children',
                                                 -- 'teacher','expressionz','expo','grad','alumni','spach'
  title         VARCHAR(100) NOT NULL,           -- card title + detail-page h1 ('Annual Day')
  card_image_id INT UNSIGNED NULL,               -- thumbnail on gallery.php
  position      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (card_image_id) REFERENCES images(id) ON DELETE RESTRICT
);

CREATE TABLE album_years (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  album_id   INT UNSIGNED NOT NULL,
  year_label VARCHAR(20) NOT NULL,               -- '2023', '2024' — the label IS the filter key,
                                                 -- so label/id mismatch bugs cannot exist
  position   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_album_year (album_id, year_label),
  FOREIGN KEY (album_id) REFERENCES gallery_albums(id) ON DELETE CASCADE
);
-- Photos per year: image_links('album_year', id, 'photos').
-- The card's '2023 & 2024' line is DERIVED from the album's active years.
-- gallery.php central slider: image_links('page', pages['gallery'].id, 'gal_slider').
```

---

## 4. PHP File Layout & Data Flow

### 4.1 New/changed files

```
public_html/
├── _libs/
│   ├── .htaccess              # Require all denied (block direct HTTP access)
│   ├── load.php               # KEPT as the universal bootstrap. Now also requires config/db/
│   │                          #   repo/media/edit/sanitize/registry. Starts the PHP session ONLY
│   │                          #   when the admin cookie ('SJADMIN') is present — zero cost publicly.
│   ├── config.php             # ['db'=>['dsn','user','pass'], 'media_dir', …] — git-ignored
│   ├── config.sample.php      # committed template of the above
│   ├── db.php                 # db(): PDO singleton (ERRMODE_EXCEPTION, emulated prepares off,
│   │                          #   FETCH_ASSOC, charset=utf8mb4 in the DSN)
│   ├── repo.php               # flat query helpers (§4.2)
│   ├── media.php              # img_url($img,$preset), img_tag() → <picture> webp+jpeg fallback,
│   │                          #   width/height attrs, alt, loading="lazy"; bg_style() for inline
│   │                          #   CSS background slots (academy/facility backgrounds)
│   ├── render.php             # shared fragment renderers: render_hero($page), render_heading_band(),
│   │                          #   render_timeline(), render_event_cards(), render_infra_block()
│   ├── edit.php               # is_admin(), is_edit(), csrf_token(), and the data-edit attribute
│   │                          #   emitters: ed_field(), ed_item(), ed_add(), ed_img(), ed_collection()
│   │                          #   — all return '' for public visitors
│   ├── sanitize.php           # server-side HTML whitelist for *_html fields (§5.5)
│   └── registry.php           # THE entity registry: entity → table, fields (type/maxlen/enum/preset),
│                              #   orderable/creatable/deletable flags, allowed owner_types (§5.4)
├── _renderers/                # full-page renderers for the three clone families
│   ├── academy.php            # expects $academy_slug  (18 pages)
│   ├── gallery-album.php      # expects $album_slug    (10 pages)
│   └── school-section.php     # expects $section_slug  (4 pages)
├── _templates/
│   ├── carousel.php           # → loops hero_slides for page 'index'
│   ├── update-scroll.php      # → loops ticker_items
│   ├── new-updates.php        # → loops update_slides
│   ├── marks-scroll.php       # → loops latest-3 mark_years + entries (animation fix §8.4)
│   ├── gal-slider.php         # → loops image_links('page', gallery, 'gal_slider')
│   ├── admin-bar.php          # NEW: floating admin toolbar + CSRF <meta> + admin asset tags;
│   │                          #   footer.php includes it inside `if (is_admin())`
│   └── (navbar, footer, preloader, scroll-up, card, testimonial, contact,
│        jumbotron, groups — unchanged apart from footer's one-line admin-bar include)
├── admin/                     # §5
├── media/                     # NEW uploads: /media/{image_id}/{preset}.{jpg|webp} + original.*
│   └── .htaccess              # php_flag engine off + deny *.php (defence in depth)
└── photos/                    # untouched — referenced via images.legacy_path

database/                      # SIBLING of public_html — outside the webroot
├── schema.sql                 # full DDL (§3) + preset seed + admin user + settings defaults
├── seed.php                   # CLI content seeder (§6)
└── integrity-check.php        # orphaned image_links / dangling refs report
```

### 4.2 Repo helpers (`_libs/repo.php`)

Flat functions, all PDO-prepared, returning plain arrays (image FK columns come back pre-joined with the image row where needed):

`repo_page($slug)` · `repo_hero_slides($pageId)` · `repo_profile($roleKey)` · `repo_unique_features()` · `repo_ticker()` · `repo_update_slides()` · `repo_mark_years($limit)` · `repo_mark_entries($yearId)` · `repo_achievements($type)` · `repo_sections()` · `repo_section($slug)` · `repo_timeline($sectionId)` · `repo_section_events($sectionId)` · `repo_academies()` · `repo_academy($slug)` · `repo_sports()` · `repo_facilities()` · `repo_albums()` · `repo_album($slug)` · `repo_album_years($albumId)` · `repo_images($ownerType,$ownerId,$role)` · `repo_image($id)`.

### 4.3 Template conversion pattern

Markup around each loop is copied byte-for-byte from the current file; only the repeated item becomes a loop. Example — `_templates/new-updates.php` slide loop:

```php
<?php foreach (repo_update_slides() as $i => $s): ?>
  <div class="carousel-item update-carousel-item <?= $i === 0 ? 'active' : '' ?>"
       <?= ed_item('update_slide', $s['id']) ?>>
    <?= img_tag($s['image'], 'update_16x9', ['class' => 'd-block w-100']) ?>
    <div class="carousel-caption update-carousel-caption">
      <h5 <?= ed_field('update_slide', $s['id'], 'title') ?>><?= e($s['title']) ?></h5>
      <p  <?= ed_field('update_slide', $s['id'], 'subtitle') ?>><?= e($s['subtitle']) ?></p>
      <a class="btn btn-primary" target="_blank" href="<?= e($s['link_url']) ?>"><?= e($s['link_label']) ?></a>
    </div>
  </div>
<?php endforeach; ?>
```

Carousel indicators (`data-slide-to`), `active` flags, zig-zag `.reverse`, image-left/right alternation, and row chunking (groups of 3 for cards) are all derived from the loop index.

### 4.4 Legacy URL preservation — the stub pattern

Slugs equal legacy file basenames, so every stub in a family is identical:

```php
<?php // tamilacademy.php … band.php, ncc.php, artandexpo.php  (18 files)
$academy_slug = basename(__FILE__, '.php');
require __DIR__ . '/_renderers/academy.php';
```

```php
<?php // gal-annual.php … gal-teacher.php  (10 files — strips the 'gal-' prefix)
$album_slug = substr(basename(__FILE__, '.php'), 4);
require __DIR__ . '/_renderers/gallery-album.php';
```

```php
<?php // kg.php, primary.php, highschl.php, highsec.php  (4 files)
$section_slug = basename(__FILE__, '.php');
require __DIR__ . '/_renderers/school-section.php';
```

- `gal-sciexpo.php` (the documented mislabeled orphan duplicate of gal-spach — nothing links to it) becomes `header('Location: /gal-expo.php', true, 301); exit;`.
- The school-section renderer keeps highsec's extras (`groups` + `marks-scroll` before the jumbotron) via `$section_slug === 'highsec'`.
- Each renderer emits one internally well-formed document of its own while still including the self-contained shared templates exactly as today.

**Pages converted in place (not stubbed):** `index.php` (principal block + unique-features loop; includes the 5 now-DB-driven templates), `about.php` (hero + principal from `profiles` — same row as index), `academics.php` (hero + 4 grade cards from `school_sections`), `achievements.php` (hero + two typed loops), `co-curriculum.php` (hero + academy-card loop), `sports.php` (hero + loop), `infrastructure.php` (**biggest rewrite** — anchor nav + facility sections from one `facilities` loop, unique carousel ids, `bg_style()` inline backgrounds), `gallery.php` (heading + album-card loop; gal-slider template), `staffs.php` (hero only).

---

## 5. Admin Panel

### 5.1 Folder layout

```
public_html/admin/
├── login.php            # form + POST; rate-limited via admin_users.failed_logins/locked_until
├── logout.php           # session destroy
├── index.php            # dashboard: editable pages/areas list → "Open & edit" links,
│                        #   media library link, change-password form
├── media.php            # media library browser (search, usage counts, edit alt text,
│                        #   download original, delete-if-unused)
├── assets/
│   ├── admin.css        # overlay outlines, toolbar/FAB, modals, reorder handles (mobile-first)
│   ├── admin.js         # overlay engine: scans data-edit-*, renders controls, fetch()es the API
│   └── cropper/         # vendored Cropper.js + CSS (no CDN dependency for the admin)
└── api/
    ├── _bootstrap.php   # auth guard + CSRF check + JSON I/O + registry load (required by all)
    ├── field.php        # POST — update one text/html/url field
    ├── item.php         # POST — create/delete a row
    ├── order.php        # POST — reorder rows or collection links
    ├── link.php         # POST — attach/detach collection images
    ├── upload.php       # POST multipart — full pipeline (FEATURES_PLAN.md §2)
    └── images.php       # GET  — paginated media library for the picker
```

### 5.2 The WYSIWYG edit flow (core requirement)

1. Admin signs in at `/admin/login.php` → dashboard lists every editable page.
2. Admin opens any **real public URL** (e.g. `/infrastructure.php`). `footer.php` includes `admin-bar.php` because `is_admin()` — a floating toolbar appears: **Edit mode ON/OFF**, Dashboard, Logout.
3. Edit mode ON (`$_SESSION['edit_mode']=1`) → templates emit `data-edit-*` attributes → `admin.js` decorates the live page: ✏ pencils on texts, 📷 on single-image slots, "Manage photos (n)" buttons on carousels/grids, ＋ on list containers, ✕/↑/↓ on items. The page underneath **is** the public rendering — parity guaranteed by construction.
4. Saves: text → `api/field.php` and the DOM is patched in place; anything structural (add/delete/reorder/image change) → API call then `location.reload()` so the server re-render remains the single source of truth. The public site reflects changes on its next refresh.

### 5.3 `data-edit` grammar

| Attribute (emitted only in edit mode) | Placed on | Overlay control |
|---|---|---|
| `data-edit-field="entity:id:field"` | text node wrapper (h1–h5, p, span, a) | ✏ → inline input, or modal textarea for `*_html` fields with a mini toolbar: **Bold**, **Gold highlight**, line break (covers "Bold for Bolder texts"); sanitized server-side |
| `data-edit-item="entity:id"` | repeating block root (slide, card, row, timeline month) | ✕ delete (confirm) + ↑/↓ reorder |
| `data-edit-add="entity:{preset json}"` | list container | ＋ Add → modal form generated from the registry field list (e.g. `achievements:{"type":"award"}` pins the type) |
| `data-edit-img="entity:id:field:preset"` | single image slot (principal portrait, card thumb, bg image) | 📷 → media modal: upload (with crop, aspect locked to preset) or pick from library; edit alt text |
| `data-edit-collection="owner_type:owner_id:role:preset"` | carousel / photo-grid root | "Manage photos" → sortable thumbnail modal (add via upload/library, remove, drag or ↑/↓ reorder) |

### 5.4 API endpoints

All under `/admin/api/`, all require session + `X-CSRF-Token` header, all return `{ok:bool, error?, data?}`. No mutating GETs.

| Endpoint | Method | Payload | Action |
|---|---|---|---|
| `field.php` | POST | `{entity, id, field, value}` | Update one field. Registry validates entity/field/type/maxlen/enum; `*_html` values pass the sanitizer; `*_image_id` fields must reference an existing image. |
| `item.php` | POST | `{action:'create', entity, data:{…}}` / `{action:'delete', entity, id}` | Insert at end position / delete (plus its `image_links` via registry hook). Only entities flagged creatable/deletable — `profiles` is neither; `hero_slides, unique_features, ticker_items, update_slides, mark_years, mark_entries, achievements, timeline_entries, section_events, academies, sports, facilities, gallery_albums, album_years` are both. |
| `order.php` | POST | `{entity, ids:[…]}` or `{owner_type, owner_id, role, link_ids:[…]}` | Rewrite `position` 0..n-1 in one transaction. |
| `link.php` | POST | `{action:'attach'/'detach', owner_type, owner_id, role, image_id / link_id}` | Manage `image_links`; attach lazily generates any missing rendition for the slot's preset. |
| `upload.php` | POST multipart | `file`, `preset`, optional `crop_rect`, `alt` | Full pipeline (FEATURES_PLAN.md) → `{image_id, url, thumb}`. |
| `images.php` | GET | `?q=&page=&preset=` | Paginated library (uploads + legacy rows), usage counts included. |

**The registry is the security keystone:** `_libs/registry.php` is a hardcoded map `entity → [table, fields{name → type/max/enum/preset}, flags, owner_types]`. Every API write resolves table and column names **only** through it — request payloads can never name a table or column. Values are always bound parameters.

### 5.5 Security checklist

- [ ] Passwords: `password_hash(PASSWORD_DEFAULT)` / `password_verify`; seeded admin forced to change password on first login (`settings.force_password_change`).
- [ ] Login rate limit: 5 failures → `locked_until = NOW() + 15 min`, plus `sleep(1)` on failure.
- [ ] Session: `session_name('SJADMIN')`; cookies `httponly`, `samesite=Lax`, `secure` when HTTPS; `session_regenerate_id(true)` at login; 30-min idle timeout + 12-h absolute lifetime enforced in `_bootstrap.php`.
- [ ] CSRF: 32-byte session token exposed via `<meta name="csrf">` (admin-bar); every POST checked with `hash_equals`.
- [ ] Escaping: `e()` = `htmlspecialchars(ENT_QUOTES, 'UTF-8')` around every echoed DB value. `*_html` fields sanitized **on write** to the whitelist `b, strong, i, em, br, p, span[class=hl-gold]` and echoed raw on read (one `.hl-gold` CSS rule added to `academics.css`/`index.css` replaces today's inline gold styles).
- [ ] PDO: exceptions on, emulated prepares off, bound values only.
- [ ] `_libs/` denied over HTTP; `database/` outside the webroot; `config.php` git-ignored; `media/.htaccess` disables PHP execution.
- [ ] No mutating GET endpoints; API responses `Content-Type: application/json` + `Cache-Control: no-store`.
- [ ] Upload validation per FEATURES_PLAN.md §2.3 (finfo + getimagesize agreement, re-encode always, size/dimension caps).
- [ ] Ops: nightly `mysqldump` + rsync of `media/` (content now lives only in DB + media).

---

## 6. Seed & Migration Strategy

### 6.1 Artifacts

- **`database/schema.sql`** — full DDL (§3) + `image_presets` rows (FEATURES_PLAN.md §2.2) + one `admin_users` row (placeholder hash) + `settings` defaults (`marks_years_shown = 3`, `force_password_change = 1`).
- **`database/seed.php`** — CLI (`php database/seed.php`), **idempotent** (upserts keyed by slug / role_key / year / natural keys; safe to re-run):
  1. **Register images:** scan `public_html/photos/` → one `images` row per file with `legacy_path`, dimensions via `getimagesize()`. All 478 files become visible in the media library on day one. No files copied, moved, or modified.
  2. **Insert content:** every hard-coded string currently in the pages — fully enumerated in `WEBSITE_CONTEXT.md` §3–§8:

| Entity | Seed inventory (from WEBSITE_CONTEXT.md) |
|---|---|
| `pages` | 13 rows (index, about, academics, achievements, co-curriculum, infrastructure, sports, staffs, gallery, kg, primary, highschl, highsec) with heading bands |
| `hero_slides` | index 7 · about/academics/achievements/co-curriculum/infrastructure/sports 3 each · staffs 2 · kg/primary/highschl/highsec 3 each (captions + infra's 3 rotating taglines + home's Explore button) |
| `profiles` | 1 row: principal (Rev.Fr.Kirubakaranathan, message, `princ1.jpg`) |
| `unique_features` | 2 rows: ESC (`esc1.jpg`), Language Academies (`german.jpg`) |
| `ticker_items` / `update_slides` | 3 ticker links · 3 update slides (+ the commented 4th "Co-Curriculum"/`upd-1.jpg` slide seeded with `is_active = 0`) |
| `mark_years` + `mark_entries` | 2024 (7 entries), 2025 (8), 2026 (6) — names/scores exactly as documented |
| `achievements` | 10 type=achievement + 4 type=award, images + captions as documented |
| `school_sections` (+children) | 4 sections · 41 timeline_entries (kg 10, primary 10, highschl 10, highsec 11) · 10 section_events (kg 4, others 2 each) · inner-carousel links · academics card fields |
| `academies` | 18 rows (banner/card/content headings, body_html with `hl-gold` spans, bg + card images) + 3 carousel links each |
| `sports` | 9 rows (name, training time, details) |
| `facilities` | 15 rows in current order + carousel links (smart classrooms 3 … labs 5 … mini auditorium 3) |
| `gallery_albums` (+children) | 10 albums · 14 album_years (annual/expo/independence/sports get two years; others one) · ~240 photo links · 15 `gal_slider` links |

  3. **Verify:** after seeding, print any referenced filename that does not exist on disk — the same class of check that would have caught the artexpo/abacaca typos originally.

### 6.2 Documented bugs this migration inherently fixes

| Bug (WEBSITE_CONTEXT.md §10) | How it's fixed |
|---|---|
| #1/#2 `artexpo0–3.jpg` / `abacaca1.jpg` 404s (files are `.jpeg`) | Seed links rows to the **real** files; renderers emit whatever `images.legacy_path` says; the seeder's existence check makes dangling refs impossible to reintroduce |
| #3 `gal-sciexpo.php` mislabeled orphan | Not seeded as an album; the file becomes a 301 → `gal-expo.php` |
| #6 infrastructure anchor-nav off-by-one (+ unreachable `#bg-15`) | Nav buttons and sections come from one ordered `facilities` loop with slug anchors (`#fac-…`); positional numbering no longer exists |
| #7 gallery year-toggle id/label mismatches (incl. gal-annual's dead `for=`) | Radio `id`, `label[for]`, and the filter class are all generated from the same `album_years.year_label` value |
| #8 gal-alumni broken lightbox default (`alumni1.jpg`) | Lightbox default = first photo of the active year, from the DB |
| #11 duplicate element ids (sports `#accordion`, infra carousels, gal `image-3`) | Renderers generate unique ids from row ids: `accordion-{id}`, `fac-carousel-{id}`, `img-{link_id}` |
| #14 typos in converted areas ("annual Day", "Sports Achivements", "Higer Secondary", "Inaguration", "Creativness") | Seed data carries corrected strings (and everything is admin-editable thereafter) |
| Conference Hall's malformed 2-slide carousel (§10 #10 part) | Rebuilt by the loop — indicators/controls generated correctly for any slide count |
| Companion one-line fixes shipped alongside (not DB-related) | #9 `schname.JPG` → `.jpg` in achievements.css · #4 drop the dead `/js/infrastructure.js` script tag · #5 navbar dropdown toggles `curriculum.php` → `href="#"` |

---

## 7. Rollout Phases (each independently shippable)

| Phase | Contents | Risk |
|---|---|---|
| **0 — Infrastructure** | `config/db/registry` libs, `schema.sql`, `seed.php` run. Site untouched — DB sits alongside. | None (no public change) |
| **1 — Read path** | Convert pages/templates to DB reads, lowest-risk first: ticker + updates + marks → achievements → sports → co-curriculum + academy stubs → gallery + album stubs → section stubs → infrastructure → all heroes → principal + unique + academics cards. Visually diff each page against pre-conversion screenshots before moving on. | Medium (mitigated by per-page rollout + diffing) |
| **2 — Admin core** | Login/session/CSRF, dashboard, admin-bar, overlay JS, **text field editing** (`field.php`, `item.php`, `order.php` for text entities). | Low (admin-only surface) |
| **3 — Images** | Upload pipeline + presets (FEATURES_PLAN.md), media library, `link.php`/`upload.php`/`images.php`, image slots + collection modals, Cropper.js. | Medium |
| **4 — Features & polish** | Keyboard controls (FEATURES_PLAN.md §3–4), lazy-loading pass, integrity-check script, backups cron, password-change flow. | Low |

---

## 8. Risks & Decisions

1. **Mixed Bootstrap versions (4.3.1 / 4.5.3 / 5.0.2 / 5.3.3).** `navbar.php` loads jQuery + BS 4.5.3 on *every* page — that is what actually powers the BS4-dialect `data-ride`/`data-slide` markup even on pages whose own CDN link is BS5. **Decision:** renderers preserve each family's existing CDN set and attribute dialect verbatim (gal pages keep BS 5.3.3 `data-bs-*`; everything else keeps the BS4 dialect). Version unification is explicitly out of scope.
2. **Nested `<head>`/`<body>` from self-contained templates.** **Decision: keep as-is.** Browsers tolerate it today; the admin overlay keys off `data-edit-*` attributes, not document validity; consolidating would force a Bootstrap unification (risk 1) and full retest of 42 pages for cosmetic gain. The three new `_renderers/*` are written internally well-formed, shrinking the problem without a global refactor.
3. **Image caching after replacement.** Solved structurally by immutable images (§2.3): replacement = new URL; re-crop bumps `?v={version}`; `bg_style()` backgrounds go through the same helper. The existing `?v=time()` on CSS links is left alone.
4. **marks-scroll infinite animation vs. variable item counts.** Today a single copy of the items animates to `translateY(-100%)` over a fixed 20 s — already glitchy, worse when counts change. **Decision:** render each column's list **twice** and animate to `translateY(-50%)` with inline `animation-duration: max(10, 1.8 × item_count)s` — a seamless loop whose speed feels consistent for a 4-entry year or a 12-entry year. Pause-on-hover kept.
5. **gal-slider count changes:** its JS already iterates `querySelectorAll('.slide')`, so DB-driven counts just work (2 s interval + preloading kept).
6. **Admin on mobile:** ≥ 44 px touch targets; reorder always available as ↑/↓ buttons (drag is a desktop enhancement); modals full-screen < 576 px; Cropper.js is touch-capable; toolbar collapses to a FAB. Accepted limitation: the infrastructure page is long — the dashboard therefore also deep-links each facility anchor.
7. **Hosting/runtime requirements:** PHP ≥ 7.4 with PDO_MySQL and GD (WebP support feature-detected — pipeline silently skips `.webp` if `imagewebp` is missing), `media/` writable by the web user, `upload_max_filesize`/`post_max_size ≥ 10M`. `load.php`'s `$_SERVER['DOCUMENT_ROOT']` usage is unchanged. Local dev needs PHP installed (not currently present on the dev machine) — e.g. `php -S localhost:8080 -t public_html` plus a local MySQL.
8. **Data-integrity edges:** deleting a `mark_year` cascades its entries (the confirm dialog says so); images in use are RESTRICT-protected (admin sees "in use on N slots"); an emptied collection renders a valid empty state (carousel controls hidden when < 2 slides); `integrity-check.php` reports orphaned links after manual DB surgery.

---

## 9. Verification (per phase)

- **Phase 1:** for each converted page, byte-compare rendered HTML (minus session-varying bits) against the pre-conversion output, or at minimum overlay screenshots; click through every legacy URL (18 academy + 10 gallery + 4 section stubs + gal-sciexpo redirect).
- **Phase 2/3:** for every entity in the registry, exercise create → edit → reorder → delete from the overlay and confirm the public page (fresh session) reflects it; attempt API calls without session/CSRF (must 401/403); attempt payloads naming unknown entities/fields (must 400).
- **Phase 3:** upload oversized/wrong-type/spoofed-extension files (must be rejected); confirm renditions exist for the slot preset and `<picture>` markup serves webp+jpeg; replace an image and confirm the URL changes (cache-bust proof).
- **Cross-cutting:** run `database/integrity-check.php` (zero orphans); run the seeder twice (idempotency); Lighthouse before/after on index + gallery album (image payload should drop, not rise).
