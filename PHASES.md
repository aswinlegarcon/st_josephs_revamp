# PHASES.md — Master Implementation Plan: St. Joseph's MHSS Future-Proof CMS

> **This is the roadmap.** Companion documents: `WEBSITE_CONTEXT.md` (canonical site inventory — update when pages change), `SECURITY.md` (normative security catalog — its §4 checklist is mandatory for every phase, enforced by `CLAUDE.md`), `ADMIN_UI_DESIGN.md` (admin UI spec + image-generation briefs), `DYNAMIC_MIGRATION_PLAN.md` / `FEATURES_PLAN.md` (legacy reference for schema, pipeline, seed inventory — their "locked decisions" are superseded by §0 below).

---

## 0. Locked decisions (supersede all earlier docs)

1. **Backend:** modern structured PHP 8.3 — Composer PSR-4 (`SJ\` → `src/`), thin layers (controllers / repositories / services), PDO. **No framework.** Evolve the existing working engine (registry, media pipeline, schema, admin panel); never discard it.
2. **Admin:** hybrid — a professional standalone dashboard (extends the existing `sj-` panel) **plus** an on-page WYSIWYG overlay for live editing.
3. **Public revamp:** same look, clean code — one self-hosted Bootstrap 5.3.3, valid single-document HTML via a layout system, consolidated CSS with `:root` design tokens, all 15 documented bugs (WEBSITE_CONTEXT §10) fixed.
4. **Hosting:** MilesWeb shared hosting (mPanel, 10 GB, no cPanel/SSH). Deploy = file upload; `vendor/` committed; Docker is dev-only. The site is currently reported "48% healthy, very slow" → the performance track (F0–F4) is first-class work, not polish.
5. **Users:** single admin account now; schema and registry are multi-user-ready (`role` column lands in S2; per-entity permission flags come with roles later).

---

## 1. Target Architecture

**Principle: evolve, don't rewrite.** The working engine (registry concept, GD media pipeline, 24-table schema, admin panel + sj- design system) stays. What changes is *packaging*: PSR-4 namespaces, secrets out of the webroot, a front controller for the admin API, and a real layout system for public pages. Everything runs on stock PHP 8.3 + GD + PDO — no server-side build step; `vendor/` is committed.

**Directory layout** (repo root maps to the hosting account home; only `public_html/` is web-served):

```
composer.json                  # PSR-4: "SJ\\" => "src/"
config/
  config.php                   # real secrets — gitignored, NEVER committed (canonical location)
  config.sample.php            # committed template
src/
  Core/    Db.php, Config.php                    ← _libs/db.php, config.php loader
  Content/ Registry.php, Sanitizer.php,          ← _libs/registry.php, sanitize.php
           Repo/HomeRepo.php … Repo/GalleryRepo.php   ← _libs/repo.php split per area
  Media/   Pipeline.php, Html.php                ← _libs/media.php (GD pipeline; img_tag/bg_style)
  Admin/   Auth.php, Csrf.php, Audit.php,        ← _libs/edit.php (auth half) + new audit helper
           Controllers/{Field,Item,Order,Upload,Images,Link}Controller.php  ← admin/api/*.php bodies
  View/    Layout.php, EditAttrs.php             ← _libs/load.php + edit.php (data-edit-* emitters)
  helpers.php                  # e(), img_tag(), bg_style() — composer "files" autoload
views/
  layout.php                   # ONE <!DOCTYPE>/<head>/<body> master document
  partials/  navbar, footer, preloader, jumbotron, scroll-up, contact …
  pages/     home.php, about.php, section.php, academy.php, album.php …
vendor/                        # committed
database/                      # schema.sql, seed.php, migrations/, backfill.php — dev-only, never uploaded
public_html/
  bootstrap.php                # require ../vendor/autoload.php + ../config/config.php
  index.php, about.php, …      # same URLs; each becomes a ~15-line thin controller
  admin/  index.php, login.php, section.php, media.php, password.php
  admin/api/index.php          # single front controller: ?r=field.save|item.create|link.reorder…
                               #   (query routing — no rewrite dependency on mPanel)
  assets/vendor/bootstrap-5.3.3/   # the ONE self-hosted Bootstrap; css/tokens.css with :root vars
```

**Migration mechanics:** during P1–P4, each `_libs/*.php` becomes a 2-line shim (`require bootstrap; thin function wrappers`) so all 42 pages keep working mid-restructure; shims are deleted in R2. Admin API endpoint files (`field.php` etc.) likewise become forwards to the front controller until the overlay ships, then are removed. Local Docker mounts the repo root so the "one level above webroot" layout is identical in dev and prod.

**Stays:** schema.sql (plus additive migrations), registry-driven generic admin/API, media pipeline behavior, admin panel UI, all public URLs, the visual design.
**Changes:** flat `_libs/` → namespaced `src/`; committed demo creds → gitignored `config/config.php` above webroot; nested-document templates → single-document layout; 4 CDN Bootstraps → 1 self-hosted; `?v=time()` → static `SJ_ASSET_VER` constant.

---

## 2. Phase Breakdown (execution order)

Rules for every phase: it is small (0.5–2 days), independently shippable, leaves the site fully working, and closes with the `SECURITY.md` §4 checklist affirmed. Risk: L = low, M = medium, H = high.

### Stage A — Security first (Seq 1–5)

| Seq | ID | Name | Goal | Files touched | Depends | Acceptance | Risk |
|---|---|---|---|---|---|---|---|
| 1 | ✅ S1 | Secrets out of repo | Move config to `config/config.php` **above the webroot** (the single canonical secrets location); gitignore it; commit `config.sample.php`; rotate DB creds. **Verify mPanel gives a writable home dir above `public_html`** (if not: fall back to `public_html/../` equivalent per mPanel layout and record the result here). | `config/config.php` (new, ignored), `config.sample.php`, `_libs/config.php`→loader, `.gitignore`, `docker-compose.yml` | — | `git grep` finds no credentials; site boots from `config/config.php`; sample committed; mPanel layout confirmed in writing. | L |
| 2 | ✅ S2 | Kill default admin | Migration adds `role`, `must_change_password`, `password_changed_at` to `admin_users`; new `admin/password.php`; forced change on first login; prod seeder generates a random password printed once to CLI (never `admin123`). | `database/migrations/001.sql`, `database/seed.php`, `admin/password.php`, `admin/_layout.php`, `admin/login.php` | S1 | Fresh seed → login → forced to set a strong password before any admin page or API works; `role` column exists (multi-user ready); `admin123` appears in nothing deployable. Covers SEC-08. | L |
| 3 | ✅ S3 | Session hardening | Idle timeout 30 min, absolute 12 h, cookie `Secure/HttpOnly/SameSite=Lax`, `session_regenerate_id` on login and periodically; private `session.save_path` on prod. Fix login lockout counter persistence + generic error message (SEC-06). | `_libs/edit.php` (session half), `admin/login.php` | S2 | Idle 31 min → API returns 401 JSON, panel redirects to login; cookie flags visible in devtools; 20-wrong-password script shows persistent lockout and byte-identical error responses. Covers SEC-06/07. | L |
| 4 | S4 | .htaccess hardening + security headers + audit log | Root `public_html/.htaccess`: deny dotfiles (`.git`, `.env`), deny `\.(sql|bak|old|zip|tar|gz|log|md|lock|swp)$`, `Options -Indexes`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`. Admin pages/API emit `X-Frame-Options: DENY` + CSP. New `audit_log` table + `SJ\Admin\Audit::log()` called from login success/failure and every mutating endpoint. `logout.php` → POST + CSRF. | `public_html/.htaccess` (new), `admin/api/_bootstrap.php`, `admin/_layout.php`, `admin/login.php`, `admin/logout.php`, `database/migrations/002.sql` | S3 | `curl -sI` shows the right header set per context; admin refuses to iframe; `/.git/HEAD`, `/config.php.bak` style probes → 404/403; every login attempt + mutation writes an audit row. Covers SEC-05/12/13/14/15/19/20. | L |
| 5 | F0 | Performance baseline | Capture the "before" numbers so F1–F4 improvements are provable: mobile Lighthouse + WebPageTest on 6 representative pages (Home, about, infrastructure, one academy, gallery hub, one album); record MilesWeb health score. Adopt the **query budget rule**: every converted page ≤ 12 SQL queries; `settings` cached per request. | `docs/perf-baseline.md` (new) | — | Baseline table committed (scores, payload sizes, request counts, TTFB); budget rule written into CLAUDE.md conventions. | L |

### Stage B — Platform restructure (Seq 6–9)

| Seq | ID | Name | Goal | Files touched | Depends | Acceptance | Risk |
|---|---|---|---|---|---|---|---|
| 6 | P1 | Composer skeleton | `composer.json`, autoload, `SJ\Core\{Db,Config}`; `_libs/db.php` + `config.php` become shims. | `composer.json`, `src/Core/*`, `public_html/bootstrap.php`, shims | S1 | All pages + admin work unchanged; `vendor/` committed; classes autoload. | L |
| 7 | P2 | Port libraries | media/sanitize/registry/edit → `SJ\Media`, `SJ\Content`, `SJ\Admin\{Auth,Csrf}`, `src/helpers.php`; shims kept. | `src/Media/*`, `src/Content/*`, `src/Admin/*`, shims | P1 | Upload→renditions works; Home renders identically; admin login works. | M |
| 8 | P3 | Admin API front controller | `admin/api/index.php?r=…` router → `SJ\Admin\Controllers\*`; old endpoints forward. | `admin/api/index.php`, `src/Admin/Controllers/*`, `admin/assets/panel.js` (base URL) | P2 | Every existing panel operation (field save, item CRUD, reorder, upload) works through the router; forwards return identical JSON. | M |
| 9 | P4 | Public layout system | `views/layout.php` single document + `SJ\View\Layout::render()`; convert **Home only** as proof; parametrized partials for navbar/footer/preloader. | `views/layout.php`, `views/partials/*`, `views/pages/home.php`, `index.php` | P2 | Home output is valid single-document HTML (W3C validator: no nested doctypes), pixel-identical visually. | M |

### Stage C — Public shell, per family (Seq 10–12)

Self-hosted Bootstrap 5.3.3 + `css/tokens.css` (`:root` navy/gold/font vars) land in R1a and are reused by R1b/R1c. Inner page markup is untouched in this stage — only the document shell changes.

| Seq | ID | Name | Goal | Files touched | Depends | Acceptance | Risk |
|---|---|---|---|---|---|---|---|
| 10 | R1a | Layout wrap — hub pages | Wrap about, staffs, academics, achievements, co-curriculum, sports, infrastructure, gallery in the layout; install `assets/vendor/bootstrap-5.3.3/` + `tokens.css`; fix dead `curriculum.php` navbar link (bug 5) and duplicate `</body></html>` (bug 10 part). | 8 page controllers + `views/pages/*`, `assets/vendor/bootstrap-5.3.3/`, `css/tokens.css`, navbar partial | P4 | These 8 pages: zero CDN Bootstrap requests, single-document HTML, visual diff = none, dropdowns work. | M |
| 11 | R1b | Layout wrap — academies + sections | Same wrap for the 18 academy-family pages and 4 section pages (family templates begin here: shared `views/pages/academy-static.php` / `section-static.php` shells). | 22 controllers, 2 family views | R1a | All 22 URLs single-document, no CDN Bootstrap, visual diff = none. | M |
| 12 | R1c | Layout wrap — gallery family | Same wrap for gallery's 11 `gal-*` pages (BS 5.3.3 already their dialect); site-wide check that no CDN Bootstrap/jQuery remains except where a page still needs jQuery for BS4-dialect markup (temporary, removed per-area in Stage E). | 11 controllers, family view | R1a | Whole site: `grep` finds no `cdn.jsdelivr|stackpath|maxcdn` Bootstrap CSS/JS; every page single-document. | M |

### Stage D — Fast, deployable shell (Seq 13–14)

| Seq | ID | Name | Goal | Files touched | Depends | Acceptance | Risk |
|---|---|---|---|---|---|---|---|
| 13 | F1 | Delivery basics | `.htaccess` `mod_deflate` + `mod_expires`/`Cache-Control` (images + versioned css/js `max-age=31536000, immutable`; HTML `no-cache`); kill `?v=time()` → `SJ_ASSET_VER` constant bumped per deploy; defer non-critical JS; verify OPcache. | `public_html/.htaccess`, layout/partials asset tags | R1a–c | Repeat page view makes 0 asset re-downloads; PageSpeed TTFB < 0.8 s; text assets served gzipped. | L |
| 14 | X1 | Deploy runbook + first prod ship | `DEPLOY.md` (release-zip whitelist: `public_html/ src/ views/ vendor/ config/config.sample.php`; never-upload list: `database/ .git *.md docker files dev config`); mPanel DB provisioning; `admin/health.php` smoke-check (DB, GD, writable media/, PHP ver, OPcache, last backup age) behind a secret token; HTTPS + free SSL enforced in mPanel; file perms 755/644, config 600. **First production deployment happens here** — a secure, restructured, faster shell with Home dynamic. Ship to prod after every phase from now on. | `DEPLOY.md`, `admin/health.php` | S1–S4, P1–P4, R1a–c, F1 | Fresh mPanel deploy following the runbook alone passes health check; rollback = re-extract previous zip; SEC-12/20/22 probes pass on prod. | M |

### Stage E — Content areas end-to-end (Seq 15–27)

Each phase = migration/seed + repo class + registry entries + public render from DB + admin panel section, so the area is **fully editable when the phase closes**. Photo tooling (M1–M3) is interleaved exactly before the photo-heavy areas need it.

| Seq | ID | Name | Goal / bugs fixed | Files touched | Depends | Acceptance | Risk |
|---|---|---|---|---|---|---|---|
| 15 | C1 | Settings, contact, footer, jumbotron | `settings`-driven site-wide strings; fixes email mismatch (bug 12), ticker typo (bug 14 part). **Contact-form abuse hardening (SEC-23):** replace client-side EmailJS send with a rate-limited server-side endpoint (or, minimum: EmailJS domain-allowlist + key rotation) + honeypot field; reCAPTCHA kept. | seed.php, `Repo/SettingsRepo`, Registry, `views/partials/{contact,footer,jumbotron}`, `js/contact.js`, admin section | X1 | Change phone/email in admin → footer+contact update; mailto matches displayed address; contact submits ≤ 5/hour/IP; EmailJS private usage not spammable from other origins. | L |
| 16 | C2 | About page | `pages`+`profiles` (president/principal messages, history, rules, timings, diary file); fixes "Infrastrucutre" typo (bug 14 part). | seed, `Repo/ProfilesRepo`, `views/pages/about.php`, `about.php`, Registry, admin | C1 | Edit principal message in admin → about.php AND home update (shared row); sanitizer allows only the whitelist. | L |
| 17 | C3 | Staffs + testimonials | `profiles` rows for staff blocks + home testimonials become editable lists. | seed, ProfilesRepo, `views/pages/staffs.php`, home partial, admin (screen 16 of ADMIN_UI_DESIGN) | C2 | Add/edit/delete/reorder a testimonial end-to-end. | L |
| 18 | M1 | Link API | `LinkController` (`link.list/attach/detach/reorder`, transactional) + collection repo helpers for `image_links`. | `src/Admin/Controllers/LinkController.php`, router, repos | P3 | curl-level: attach/detach/reorder persists with correct sort order; CSRF + registry-whitelisted owner_types enforced. | L |
| 19 | M2 | Manage-photos UI | Panel "Manage photos (n)" modal: grid of linked images, add-from-library / upload, drag reorder, detach; wired to hero carousel + update slides first. | `admin/assets/panel.js`, `panel.css`, `admin/section.php` | M1 | Reorder hero slides by drag → Home carousel order changes on reload. | M |
| 20 | C4 | School sections family | kg/primary/highschl/highsec on ONE template: `school_sections`, `timeline_entries`, `section_events`; inner carousels via image_links; fixes "Higer Secondary" (bug 14 part). | seed, `Repo/SectionsRepo`, `views/pages/section.php`, 4 thin controllers, admin | C1, M2 | All 4 URLs render from DB; add a timeline entry to KG in admin → appears; groups/marks-scroll still on highsec. | M |
| 21 | C5 | Academies family | 18 pages (15 academies + band/ncc/artandexpo) on one template from `academies` + carousel links; fixes .jpg/.jpeg 404s (bugs 1, 2) and "Creativness" (bug 14 part). | seed, `Repo/AcademiesRepo`, `views/pages/academy.php`, 18 thin controllers, co-curriculum cards, admin | C4 | Every academy URL renders; Art-Expo images load; edit write-up in admin → live; co-curriculum grid comes from the same rows. | M |
| 22 | C6 | Sports | `sports` table; unique accordion ids (bug 11 part). | seed, `Repo/SportsRepo`, `views/pages/sports.php`, admin | C1 | 9 sports editable; each accordion expands independently. | L |
| 23 | M3 | Crop UI | Vendored Cropper.js on upload/replace, locked to preset aspect; server re-renders renditions from stored `crop_rect`; "Re-crop" reopens with the same rect and bumps `?v=`. | panel.js, `UploadController`, `Media/Pipeline`, `admin/assets/cropper/` | M2 | Upload portrait photo into 16:9 slot → choose crop → rendition matches selection, no distortion; re-crop changes the URL version. | M |
| 24 | C7 | Infrastructure | `facilities` (15 showcases, admin can add more); fixes anchor off-by-one (bug 6), malformed Conference-Hall carousel + double bundle (bug 10 part), duplicate carousel ids (bug 11 part), missing infrastructure.js ref (bug 4). | seed, `Repo/FacilitiesRepo`, `views/pages/infrastructure.php`, admin | C1, M2 | Nav button N scrolls to facility N; 15 carousels independent; no 404s in console; new facility added in admin appears with nav anchor. | M |
| 25 | C8 | Achievements + academics | `achievements` zig-zag + certificates (two typed loops); academics 4 grade cards from `school_sections`; fixes schname.JPG case (bug 9), "Sports Achivements" (bug 14 part). | seed, `Repo/AchievementsRepo`, views, admin | C4 | Add an achievement in admin → renders in correct zig-zag alternation; grade cards match section data. | L |
| 26 | M4 | Media library page | `admin/media.php`: browse/search all images (uploads + 478 legacy), usage counts (where linked), alt-text edit, delete only when unlinked, orphan filter, detail drawer. | `admin/media.php`, `ImagesController` | M2 | Deleting a linked image is blocked with "used in X" message; orphans deletable; legacy photos searchable. | L |
| 27 | C9 | Gallery family | Hub + 11 albums on one template from `gallery_albums`/`album_years`/`image_links`; fixes gal-sciexpo orphan → 301 (bug 3), year-toggle mismatches (bug 7), lightbox default (bug 8), duplicate ids (bug 11 part). **Keyboard features (FEATURES_PLAN §3–4):** home hero ←/→; lightbox ←/→/Esc navigating only the active year, with typing/modifier/`sj-edit-mode` guards. | seed, `Repo/GalleryRepo`, `views/pages/{gallery,album}.php`, 11 stubs, gal-slider partial, carousel partial script, admin | M2, M4 | Year filter labels match shown photos on every album; attach a photo to an album+year in admin → grid + lightbox show it; keyboard nav works per FEATURES_PLAN §5 acceptance list. | M |

### Stage F — Live-edit overlay (Seq 28–29)

| Seq | ID | Name | Goal | Files touched | Depends | Acceptance | Risk |
|---|---|---|---|---|---|---|---|
| 28 | O1 | Admin bar + inline text | `views/partials/admin-bar.php` (rendered only when `is_admin()`), `js/admin.js`: activate `data-edit-*` — inline editing + Bold/Gold mini-toolbar, save via field API; edit-mode toggle is POST+CSRF; edit-mode pages send `X-Frame-Options: DENY`. | admin-bar partial, `public_html/js/admin.js`, `SJ\View\EditAttrs`, layout.php | P3, C1–C9 (progressively) | Logged-in admin on any converted page: click text → edit → save → persists; logged-out visitors get zero admin markup/JS bytes. | M |
| 29 | O2 | Overlay item + image ops | Add/delete/reorder items in place; click image → picker/crop modal (reuses panel modals, `sjov-` CSS namespace). | admin.js, shared modal JS extracted from panel.js | O1, M3 | Admin adds a topper card from the live Home page; swaps a hero image without opening the panel. | M |

### Stage G — Revamp completion (Seq 30–31)

| Seq | ID | Name | Goal | Files touched | Depends | Acceptance | Risk |
|---|---|---|---|---|---|---|---|
| 30 | R2 | CSS consolidation | Merge 20 CSS files → `tokens.css` + per-family sheets; delete dead rules/unlinked files (bug 13); one shared `reveal()` in `js/site.js`; delete `_libs` shims + `_templates/`; drop remaining jQuery once no BS4 dialect remains. | `css/*`, `js/site.js`, views | C1–C9 | No unused stylesheet requests; visual diff none; total CSS size ↓ ≥ 40%; `_libs/`+`_templates/` gone. | M |
| 31 | R3 | Validation + a11y sweep | Unique IDs everywhere (bug 11 done), remaining typos (bug 14 done), absolute `/photos/...` paths only (bug 15), alt text on all images, W3C-valid pages. | views, seed content | R2 | Validator: 0 errors on all 42 URLs; grep finds no duplicate ids, no `../photos`. | L |

### Stage H — Performance, SEO, ops (Seq 32–36)

| Seq | ID | Name | Goal | Files touched | Depends | Acceptance | Risk |
|---|---|---|---|---|---|---|---|
| 32 | F2 | Legacy rendition backfill | Batch-generate WebP/JPEG renditions for all 478 legacy photos **in Docker** (`database/backfill.php`) — never on the shared host; upload the `media/` output + SQL delta; route all page images through `img_tag()` (`<picture>`, `srcset`, lazy loading below the fold, explicit width/height). | `database/backfill.php`, views | C1–C9, X1 | No >300 KB original photo served on any page; Home image payload ↓ ≥ 70% vs F0 baseline; zero image-driven CLS. | M |
| 33 | F3 | SEO pack | Unique `<title>` + meta description per page (columns on `pages`, editable in admin SEO screen), canonical + OG tags, generated `sitemap.xml`, `robots.txt` (`Disallow: /admin/`). | migrations, layout.php, `admin` SEO screen, sitemap script | C1 | Sitemap lists all public URLs; Lighthouse SEO ≥ 95; every page has a distinct title/description. | L |
| 34 | F4 | Perf audit + Cloudflare | Lighthouse/WebPageTest re-run vs the F0 baseline; fix stragglers; optionally front with Cloudflare free (DNS move, `/admin*` bypass rule, Brotli). | — | F1–F3 | Mobile Lighthouse on Home/infrastructure/an album/an academy: **Perf ≥ 80, SEO ≥ 95, Best-Practices ≥ 90, A11y ≥ 90**; fully-loaded < 3 MB / < 25 requests on Home; MilesWeb health re-check documented. | M |
| 35 | X2 | Backups | mPanel cron: nightly `mysqldump` to a non-web dir + weekly `media/` archive, 14-day retention; documented restore drill (into Docker). Admin "Backups" screen is **status-only** (last-run time, sizes) — no download endpoint (see SEC-20). | cron config, health widget, `admin` backups screen | X1 | Restore drill from a prod dump succeeds in Docker; backup age visible on dashboard. | L |
| 36 | X3 | Monitoring | UptimeRobot on `/` and `/admin/health.php?token=…`; PHP `error_log` path documented; dashboard health widget (disk, image count, last backup age). | `admin/health.php`, dashboard | X2 | Simulated DB outage → alert within 5 min; widget shows last-backup age. | L |

---

## 3. Performance Plan (Stage D/H detail)

The "48% healthy, very slow" report is dominated by: unoptimized full-size photos (478 files, many >1 MB), `?v=time()` defeating all CSS caching, 4 Bootstrap CDN versions, nested-document HTML, and no compression headers. Measured order of attack:

1. **F0 baseline first** — no optimization without a before-number. Re-measure at F1, F2, F4.
2. **Kill `?v=time()` (F1, biggest cache win).** Replace with `?v=SJ_ASSET_VER` (constant bumped per deploy). Repeat-visit CSS/JS requests drop to zero.
3. **.htaccess (F1):** `mod_deflate` on text (~70% transfer cut); `mod_expires`/`Cache-Control` immutable for images/versioned assets, `no-cache` for HTML; `Options -Indexes`.
4. **One Bootstrap (R1a–c):** self-hosted 5.3.3 replaces 4 CDN versions → 3 fewer render-blocking downloads, 2 fewer DNS connections; fonts via a single `display=swap` request with preconnect (or self-hosted).
5. **Rendition backfill (F2), the heavy lifter:** generate in Docker, upload results. Expected: Home image payload from the ~8–12 MB class to <1.5 MB; mobile LCP from >8 s to <3 s.
6. **Query budget (F0 rule):** ≤ 12 queries/page; `settings` and `pages` row cached per request; no N+1 in loops (repos return image data pre-joined).
7. **OPcache (F1/X1):** confirm via `admin/health.php`; set `opcache.validate_timestamps=1, revalidate_freq=60` if mPanel exposes PHP settings.
8. **SEO (F3)** and **Cloudflare (F4, optional):** free tier absorbs the shared host's slow static serving; verify `/admin*` bypass.
9. **Targets (F4):** mobile Lighthouse Perf ≥ 80 / SEO ≥ 95 / BP ≥ 90 / A11y ≥ 90; Home fully-loaded < 3 MB, < 25 requests.

---

## 4. Deploy Story (Docker dev → mPanel prod)

- **Parity:** Docker mounts the repo root; `public_html/` is the docroot in both. Same PHP 8.3, GD, PDO. `config/config.php` differs per environment and is created by hand once in each — never transferred through git or bundled in the zip.
- **Release procedure:** (1) tag; (2) build a release zip locally from the whitelist: `public_html/`, `src/`, `views/`, `vendor/`, `config/config.sample.php`; (3) upload via mPanel file manager / FTP, extract over the previous release; (4) apply `database/migrations/*.sql` via mPanel's DB tool (**additive-only** — no destructive DDL); (5) bump `SJ_ASSET_VER`; (6) open `/admin/health.php` → all green; (7) smoke list: Home, one page per family, admin login, one edit round-trip.
- **Never upload:** `database/` (schema/seed/backfill are dev-only; seed prints credentials), `.git/`, `docker-compose.yml`/`Dockerfile`/`run.sh`, dev `config/config.php`, `*.md` plans, dumps/archives (root `.htaccess` denies them anyway — SEC-12/20).
- **Data direction rule:** content flows dev→prod only until X1; after that **prod DB is the source of truth** — pull nightly dumps (X2) into dev for testing, never push a dev DB over prod.
- **Rollback:** keep the two previous release zips in a non-web `releases/` dir; rollback = extract previous zip (additive migrations mean old code runs on new schema).

---

## 5. Working agreements

- Ship to prod after every phase from X1 (Seq 14) onward.
- Every phase closes with: acceptance criteria demonstrated, `SECURITY.md` §4 checklist affirmed (per `CLAUDE.md`), `WEBSITE_CONTEXT.md` updated if page structure changed.
- Multi-user/roles: `role` exists from S2; when a second user is actually needed, add registry permission flags + per-entity checks in `_bootstrap.php` (SEC-09) and enable the "Users & roles" card (ADMIN_UI_DESIGN screen 22).
