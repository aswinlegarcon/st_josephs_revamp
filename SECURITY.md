# SECURITY.md — St. Joseph's MHSS Admin Panel & Site Security Catalog

> **Normative & enforced.** Before shipping any change to `public_html/admin/**`, `public_html/_libs/**`, `src/**`, `database/**`, or any page that renders DB content, walk the relevant SEC items below and run their **Agent Verification** steps. The **§4 pre-ship checklist is mandatory** — `CLAUDE.md` requires every agent to affirm it in the completion summary ("SECURITY.md §4 checklist: PASS", or itemized N/A with reasons). This file is grounded in the actual code; when the code moves during the `src/` refactor, update the file references here.

## 1. Threat model

**Assets:** admin credentials/session; DB content integrity (defacement = reputational damage to a school); uploaded media (children's photos — privacy-sensitive); DB credentials in config; the server account on MilesWeb shared hosting.
**Actors:** anonymous internet users (defacement, SEO spam, phishing injection); automated scanners/bots (default creds, `.git`, backup files, upload endpoints); a compromised admin browser (CSRF, XSS pivot); co-tenants on shared hosting (world-readable files, shared `/tmp` sessions); supply chain (CDN scripts, Composer packages).
**Entry points:** `/admin/login.php`; `/admin/api/*` (field, item, order, upload, images) and the future `link.php` + overlay API; every public page rendering DB HTML; static serving of `/media` and `/photos`; `.htaccess`-dependent path protections; FTP/mPanel deploy artifacts.
**Trust boundary rule:** everything in a request (JSON body, headers, `$_FILES`, query strings, cookies) is untrusted; the **registry** (`_libs/registry.php` → `SJ\Content\Registry`) is the only authority for tables/columns/types; the **sanitizer** (`_libs/sanitize.php` → `SJ\Content\Sanitizer`) is the only gate through which HTML enters the DB.

## 2. Vulnerability catalog

Status legend: ✅ protected · 🟡 partial · 🔴 vulnerable/missing

### SEC-01 SQL Injection — OWASP A03
**Where:** all of `_libs/repo.php`, `admin/api/field.php`, `item.php`, `order.php`, `images.php`.
**Status: ✅** Every value is bound via PDO prepared statements with `ATTR_EMULATE_PREPARES=false` (`_libs/db.php`). Identifiers (`{$reg['table']}`, `` `$field` ``) are interpolated only after whitelist resolution against the registry. `images.php` LIMIT/OFFSET are cast ints; LIKE args are bound.
**Mitigation (invariant):** identifiers may only ever come from `sj_registry()`; values only via placeholders. Never build SQL from request data, even "just an ORDER BY".
**Agent verification:** grep the diff for `->query(`, string interpolation inside SQL, and confirm every interpolated identifier traces to the registry or a code literal. Test: POST `{"entity":"hero_slide","field":"id`; --"}` → must return "Unknown field".

### SEC-02 Stored XSS via `*_html` fields / sanitizer bypass — A03
**Where:** `_libs/sanitize.php`; write path `api/_bootstrap.php::api_validate_field` (`html` type → `sj_sanitize_html`, `text` → `strip_tags`); raw-echo sites `admin/section.php`, `_libs/edit.php::ed_rich`, templates echoing `*_html`.
**Status: 🟡** Sanitizer rewrites every allowed opening tag to a fixed literal (attributes dropped; `span` only survives as literal `<span class="hl-gold">`), so `onclick`, `style`, `href` cannot survive. Regex-based, not DOM-based: does not guarantee well-formed nesting, and safety depends on *every* HTML write path calling it.
**Mitigation:** keep the tag whitelist frozen (`b, strong, i, em, br, p, span.hl-gold`). Any new rich field MUST be typed `html` in the registry so `api_validate_field` routes it through the sanitizer. Raw-echo a column ONLY when it ends `_html`; everything else through `e()`. In the `src/` refactor, sanitize in exactly one repository/service layer; consider a `DOMDocument`-based sanitizer.
**Agent verification:** for any new/changed field: (a) confirm its registry type; (b) confirm the render site uses `e()` unless the column ends `_html`; (c) run bypass probes and confirm the stored value is inert: `<img src=x onerror=alert(1)>`, `<span class="hl-gold" onmouseover=alert(1)>`, `<p style=...>`, `<a href=javascript:...>`, `<sCrIpT>`, `<span data-x="hl-gold" onclick=alert(1)>`.

### SEC-03 Reflected XSS — A03
**Where:** `admin/login.php` (error via `e()`), `admin/section.php` (`$_GET['s']` whitelisted then redirects), public pages (currently zero `$_GET` usage).
**Status: ✅** today. Risk returns when gallery/marks pages gain query params (`?album=`, `?year=`) during conversion.
**Mitigation:** every echoed request-derived value goes through `e()`; prefer whitelist/int-cast before use.
**Agent verification:** grep new code for `$_GET|$_REQUEST|$_SERVER['REQUEST_URI'|PHP_SELF|QUERY_STRING]`; each occurrence must be cast, whitelisted, or `e()`-wrapped. Test with `?param=<svg onload=alert(1)>`.

### SEC-04 DOM XSS in admin JS — A03
**Where:** `admin/assets/panel.js` (`innerHTML` with API data via local `esc()`); `_libs/edit.php::ed_add` / `_layout.php::panel_add_attr` (JSON in a single-quoted attribute).
**Status: 🟡** API-sourced strings are `esc()`d; `data-panel-add` JSON is code-authored (registry labels), so currently safe — but escaping is minimal and breaks if labels ever come from the DB.
**Mitigation:** in panel.js and the future public `admin.js`, use `textContent` unless the value is server-sanitized `*_html`; keep `esc()` for all API strings; keep registry labels code-only (or HTML-encode the whole JSON payload if that changes).
**Agent verification:** grep JS diff for `innerHTML|insertAdjacentHTML|outerHTML|document.write|eval|new Function` — each hit must use `esc()`/sanitized data. Create a slide titled `"><img src=x onerror=alert(1)>` and open every panel screen + edit modal.

### SEC-05 CSRF — A01
**Where:** `api/_bootstrap.php` (X-CSRF-Token on all non-GET, `hash_equals`), `login.php` (token on login), `_libs/edit.php::csrf_token` (32-byte per-session), cookie `SameSite=Lax`.
**Status: ✅** for existing endpoints; 🟡 overall: `logout.php` destroys the session on a plain GET (forced-logout CSRF, minor); not-yet-built `link.php`/overlay endpoints must inherit the guard.
**Mitigation:** every new API file starts with `require __DIR__.'/_bootstrap.php'`; convert logout to POST+token (S4); never exempt an endpoint from the token check.
**Agent verification:** curl any new endpoint without `X-CSRF-Token` → expect 403; with a stale token → 403. Confirm the file requires `_bootstrap.php` before any logic.

### SEC-06 Authentication brute force & username enumeration — A07
**Where:** `login.php`.
**Status: 🟡** Per-account lockout (5 fails → 15 min), `sleep(1)` on failure, `password_verify` (bcrypt). Gaps: (a) the fail counter resets to 0 when locking → 5 fresh attempts each 15-min window forever; (b) "Account temporarily locked" confirms a username exists; (c) no IP throttling/logging; (d) `sleep(1)` ties up a PHP worker → cheap login-flood DoS on shared hosting.
**Mitigation (S3):** persist the counter through lockout (reset only on success); one generic error for all failure modes; log every attempt (SEC-19); optional IP counter table. Keep bcrypt via `PASSWORD_DEFAULT`.
**Agent verification:** script 20 wrong-password attempts → lockout persists past the first window and responses are byte-identical for "wrong user" vs "wrong password" vs "locked".

### SEC-07 Session fixation / hijack / timeouts — A07
**Where:** `_libs/edit.php::sj_session_boot` (httponly, SameSite=Lax, `secure` only when HTTPS detected), `login.php` (`session_regenerate_id(true)`), `logout.php`.
**Status: 🟡** Fixation handled. **Missing: idle and absolute timeouts** — a stolen cookie is valid until browser close. `secure` depends on `$_SERVER['HTTPS']` (unset behind some proxies). Shared `/tmp` session storage risks co-tenant reads.
**Mitigation (S3):** store `last_seen` + `login_at` in the session; enforce 30-min idle / 12-h absolute, destroy + redirect on expiry; regenerate id periodically; on prod set `session.save_path` to a private dir (chmod 700) and `session.cookie_secure=1` once HTTPS is enforced.
**Agent verification:** confirm timeout logic runs on API requests too; an expired session returns 401 JSON from `api/*` and a login redirect from panel pages; `Set-Cookie` shows `HttpOnly; SameSite=Lax; Secure` on the prod path.

### SEC-08 Default credentials / forced password change — A07
**Where:** `database/seed.php` seeds `admin/admin123`; no change-password UI; `admin_users` has no `must_change_password`/`password_changed_at`/`role`.
**Status: 🔴 Highest-priority gap.** Shipping this seed to MilesWeb = instant compromise by any scanner.
**Mitigation (S2):** add `must_change_password TINYINT DEFAULT 1` + `password_changed_at` + `role VARCHAR(24) DEFAULT 'owner'`; build `/admin/password.php` (current + new ≥12 chars, `password_verify` gate, session regen after change); `_layout.php` redirects to it while `must_change_password=1`; the production seeder generates a random password printed once to CLI, never `admin123`.
**Agent verification:** fresh seed → login → forced redirect to password change, APIs also 403 until changed; `admin123` appears in nothing destined for prod.

### SEC-09 IDOR / mass assignment via registry — A01
**Where:** `api/item.php` (create builds columns strictly from `$reg['fields']`; update rejects unknown fields), `api/field.php`, `_libs/registry.php`.
**Status: ✅** for mass assignment — payloads cannot name tables/columns; `creatable/deletable/orderable` flags enforced. 🟡 residual: any authenticated admin can write any row id (fine single-user; becomes IDOR with roles); create accepts any positive `parent` id without existence check (DB FK is the only backstop); `order.php` doesn't verify ids share one parent scope.
**Mitigation:** keep the registry as sole authority as it grows to ~30 entities; validate parent ids exist (`SELECT 1 FROM parent WHERE id=?`); with roles, add per-entity permission flags checked in `_bootstrap.php`.
**Agent verification:** each new entity: create with extra field `{"data":{"password_hash":"x"}}` → "Unknown field"; create with `parent_id:999999` → clean failure (no 500); confirm `admin_users`/`settings` are NEVER registered.

### SEC-10 File upload attacks — A03/A04
**Where:** `api/upload.php`, `_libs/media.php::media_process_upload`, `media/.htaccess`.
**Status: ✅ strong / 🟡 verify-on-prod.** Protections: `is_uploaded_file`; finfo MIME + `getimagesize` MIME must match AND be in {jpeg,png,webp} (blocks MIME spoof, SVG, HTML polyglots); ≤10 MB; ≤8000×8000 (bounds decompression bombs); stored path is `media/{autoincrement_id}/original.{ext-from-MIME}` — user filename never touches disk (no traversal, no `.htaccess` upload); renditions are full GD re-encodes (strip embedded PHP/EXIF); `media/.htaccess` sets `php_flag engine off` + denies `.php/.phtml/.phar`. Residual: the **original** is stored verbatim (JPEG/PHP polyglot harmless only while `.htaccess` is honored); 8000×8000 truecolor ≈ 256 MB GD memory → possible DoS; SVG/GIF must stay excluded.
**Mitigation:** on MilesWeb (LiteSpeed) verify `php_flag` is honored — add belt-and-braces `RemoveHandler .php .phtml .phar` + `RemoveType` to `media/.htaccess` and `photos/.htaccess`; confirm `memory_limit` covers worst-case decode or lower the dimension cap (e.g. 6000); keep the MIME whitelist frozen.
**Agent verification:** upload and confirm rejection of: `shell.php` renamed `.jpg`, an SVG, a 12000px image, an 11 MB file. Upload a real JPEG with `<?php` in its EXIF comment → then request `/media/{id}/original.jpg` on the deployed target and confirm it returns image bytes, NOT executed PHP. Confirm the stored filename derives from the DB id, never `$_FILES['file']['name']`.

### SEC-11 LFI / RFI — A03
**Where:** `_libs/load.php::get_templates` — `include SJ_PUBLIC_ROOT."/_templates/{$name}.php"`.
**Status: ✅** today (every call site passes a literal). A loaded gun: one `get_templates($_GET['page'])` during conversion = LFI.
**Mitigation:** in the router/layout system resolve templates from a hardcoded map, never from request data; keep `allow_url_include=Off`.
**Agent verification:** grep for `include|require` with any variable path; each must resolve from a code-literal whitelist. Test the router with `/?page=../../config/config`.

### SEC-12 Direct access to `_libs/`, `database/`, `.git`, config — A05
**Where:** `_libs/.htaccess` (`Require all denied`); `database/` lives OUTSIDE public_html and `seed.php` has a CLI-only guard; **no root `public_html/.htaccess`**; `_templates/` unprotected (fragment HTML leak).
**Status: 🟡** All protection is `.htaccess`-dependent (needs `AllowOverride` — standard on MilesWeb Apache/LiteSpeed, confirm) and there is no root-level dotfile/backup deny.
**Mitigation (S4):** add root `public_html/.htaccess`: deny dotfiles (`RedirectMatch 404 /\.`), deny `\.(sql|bak|old|zip|tar|gz|log|md|lock|swp)$`, plus SEC-13 headers. Never deploy `.git/`, `*.md`, dev config, docker files. Keep `database/` outside the webroot.
**Agent verification:** on the deployed target request `/_libs/config.php`, `/_libs/db.php`, `/.git/HEAD`, `/database/seed.php`, `/composer.json`, `/PHASES.md` → all 403/404.

### SEC-13 Missing security headers — A05
**Where:** nowhere are `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `CSP`, or `HSTS` set (only Content-Type/Cache-Control in `api/_bootstrap.php`).
**Status: 🔴**
**Mitigation (S4):** root `.htaccess`: `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (camera/mic/geo off); after HTTPS: `Strict-Transport-Security: max-age=31536000`. For `/admin` (PHP-emitted): `X-Frame-Options: DENY` + `Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'`. Public pages get a report-only CSP first (inline/CDN usage — tighten as R2 extracts inline styles).
**Agent verification:** `curl -sI` the login page, a panel page, an API endpoint, and the homepage; confirm the header set for each context; confirm the admin cannot be iframed.

### SEC-14 Clickjacking on admin — A05
Subsumed by SEC-13: no `X-Frame-Options`/`frame-ancestors` → admin is framable today (🔴). The edit overlay makes it worse: public pages in edit mode must also send `X-Frame-Options: DENY` when `is_edit()`.
**Agent verification:** embed login + panel + an edit-mode public page in a local iframe → all must refuse to render.

### SEC-15 Mutating GET endpoints — A01/A04
**Status: 🟡** All content APIs are POST (the only GET is read-only `images.php`). Exception: `logout.php` mutates on GET (fixed in S4). Invariant for all new code (link.php, edit-mode toggle, password change): **state changes only via POST + CSRF token.**
**Agent verification:** list every new route; assert none mutates on GET; confirm the edit-mode toggle is POST.

### SEC-16 Verbose error disclosure — A05
**Where:** `db.php` prints a dev-flavored message on connection failure; API endpoints lack a global try/catch, so an unexpected `PDOException` becomes a fatal — safe only if `display_errors=Off` (set in the Dockerfile only; prod mPanel php.ini is unconfigured).
**Status: 🟡**
**Mitigation:** prod-neutral DB error text; top-level `set_exception_handler`/try-catch in `_bootstrap.php` returning generic 500 JSON and logging the real error privately; enforce `display_errors=Off`, `log_errors=On`, private `error_log` via mPanel.
**Agent verification:** force a DB error in a test branch → response contains no path/SQL/stack trace; the real error lands in the private log.

### SEC-17 Direct file serving from /media — A01/A05
**Where:** `media.php` stores `original.{ext}` alongside renditions; anything under `/media/{id}/` is world-readable and ids are sequential (enumerable).
**Status: 🟡** Renditions are re-encoded (EXIF stripped), but **originals keep full EXIF — including GPS** from staff phone photos of children. No hotlink protection, no cache headers.
**Mitigation:** strip EXIF from stored originals (re-save via GD on ingest) or move originals outside the webroot behind a serving script; accept enumerability for public school content but document it; add long-lived `Cache-Control` for `/media` + `/photos` (F1).
**Agent verification:** upload a phone photo with GPS EXIF; fetch `/media/{id}/original.jpg` and run `exif_read_data` — must contain no GPS/serial metadata after the fix; until fixed, flag any feature that exposes original URLs.

### SEC-18 Race conditions in ordering — A04
**Where:** `api/order.php` (full rewrite inside a transaction — partial failure rolls back); `api/item.php` (MAX(position)+1 read then INSERT, **not** transactional → duplicate positions under concurrent creates).
**Status: 🟡** Harmless single-admin (tie broken by `ORDER BY position, id`), but fix cheaply.
**Mitigation:** wrap create's position-read + insert in one transaction, or compute position in the INSERT (`INSERT ... SELECT COALESCE(MAX(position)+1,0) ...`).
**Agent verification:** any new orderable entity keeps the transaction in order.php; renderers always order by `position, id`.

### SEC-19 Audit logging — A09
**Status: 🔴** absent entirely (no login log, no change history, no upload log).
**Mitigation (S4):** add `audit_log` (id, admin_id NULLABLE, action, entity, entity_id, ip, ua, created_at) + one `SJ\Admin\Audit::log()` called from login success/failure, every `_bootstrap.php`-guarded mutation, and password changes. No PII beyond IP; prune >90 days.
**Agent verification:** every new mutating endpoint calls `Audit::log()`; a failed login writes a row with null admin_id.

### SEC-20 Backup exposure — A05
**Status: 🔴** no guard. Typical shared-hosting incidents: `stjosephs.sql`, `public_html.zip`, `config.php.bak`, editor `~`/`.swp` files left in the web root.
**Mitigation (S4):** root `.htaccess` extension denylist; deploy checklist item "no archives/dumps in web root"; store mPanel backups outside `public_html` (X2).
**Agent verification:** before deploy sign-off, `find public_html -name '*.sql' -o -name '*.zip' -o -name '*.bak' -o -name '*~'` on the artifact → empty.

### SEC-21 Dependency & supply chain — A06
**Where:** CDN Bootstrap 4.5.3 in `_templates/navbar.php` (4 versions site-wide); planned Cropper.js; committed Composer `vendor/`.
**Status: 🟡** no SRI policy, mixed CDN versions.
**Mitigation:** consolidate to self-hosted Bootstrap 5.3.3 (R1a — better for perf too); self-host Cropper.js at a pinned version; run `composer audit` before each deploy; commit `composer.lock`; no packages beyond vetted needs. Any remaining off-origin asset carries `integrity` + `crossorigin`.
**Agent verification:** every `<script src=|<link href=` pointing off-origin carries SRI; `composer audit` clean.

### SEC-22 Shared-hosting specifics — A05
**Where/observed:** repo files are `0666`, `media/` is `0777`; `config.php` carries fallback creds and mPanel has no docker env, so a naive prod config would ship real creds as fallbacks; PHP version, OPcache, session path are mPanel-managed.
**Status: 🔴** for permissions and the prod-config plan.
**Mitigation:** real secrets live ONLY in `config/config.php` above the webroot (aligned with PHASES S1); deploy with dirs 755, PHP files 644, `config/config.php` 600, `media/` 755 (PHP runs as the account user under suPHP/LiteSpeed — 777 is never needed); keep `_libs/.htaccess` deny; select PHP 8.3 in mPanel and re-check EOL annually; enable OPcache; set private `session.save_path` (SEC-07) and `error_log` (SEC-16); enforce HTTPS + free SSL before go-live.
**Agent verification:** deploy checklist asserts permission bits; `php -v` on host is 8.3.x; `/media/` works at 755.

### SEC-23 Contact-form / EmailJS abuse — A05/A04
**Where:** `public_html/js/contact.js` and `_templates/contact.php` hardcode the EmailJS public key `psMv9kF5kawkjc1ve` plus service ID `service_jh0ghjn` and template `template_4j0k0ib`; reCAPTCHA sitekey is also client-side.
**Status: ✅ mitigated (C1, 2026-08-08).** The EmailJS SDK, public key, service ID and template ID are removed from all client code. The form posts to the public server endpoint `public_html/api/contact.php`: POST-only, same-origin pinned (Origin/Referer), hidden honeypot (`website` field → fake success, nothing stored), rate limit 5/hour/IP via the `contact_submissions` table, server-side reCAPTCHA verification when `config['recaptcha_secret']` is set, every accepted enquiry stored, optional relay via `mail()` when `config['contact']['mail_to']` is set. Verified: grep finds no EmailJS key in served code; 6th submit in an hour → 429; honeypot → dropped; cross-origin → 403. **Remaining user action:** rotate/delete the old EmailJS key in the dashboard (it lives in git history and old cached bundles).
**Mitigation (C1):** preferred — route submissions through a server-side endpoint (`admin/api/contact.php` style, but public + unauthenticated) that verifies reCAPTCHA server-side, rate-limits (≤ 5/hour/IP), enforces a hidden honeypot field, and sends mail from the server (keys never reach the client). Minimum — restrict the EmailJS public key to the site's domain in the EmailJS dashboard, rotate it, and keep server-side reCAPTCHA verification + honeypot + rate limit.
**Agent verification:** confirm no sending secret is present in any client bundle (grep the deployed JS), or that the EmailJS key is domain-locked; submit the form 10× rapidly from one IP → later attempts rejected; submit with the honeypot filled → silently dropped; submit with an invalid reCAPTCHA token → rejected server-side.

## 3. Current gaps found (prioritized)

| # | Severity | Finding (evidence) | SEC |
|---|----------|--------------------|-----|
| 1 | **Critical (at deploy)** | Default `admin/admin123` seeded, no forced password change, no change-password page, no `must_change_password` column | SEC-08 |
| 2 | **High** | No session idle/absolute timeout — cookie valid until browser close | SEC-07 |
| 3 | **High** | Zero security headers anywhere; admin framable (clickjacking) | SEC-13/14 |
| 4 | **High (at deploy)** | No root `.htaccess`: no dotfile/backup/dump denial, no header injection point | SEC-12/20 |
| 5 | **High** | Contact form's EmailJS keys hardcoded client-side → quota-drain/spam abuse | SEC-23 |
| 6 | **Medium** | Uploaded **originals keep EXIF/GPS** and are enumerable at `/media/{id}/original.*` — child-photo privacy | SEC-17 |
| 7 | **Medium** | File permissions 666/777 in the tree — copied to prod by FTP | SEC-22 |
| 8 | **Medium** | No audit logging of logins or content changes | SEC-19 |
| 9 | **Medium** | Fallback DB creds committed in config; a naive prod deploy relies on them | SEC-22 |
| 10 | **Medium** | Lockout counter resets to 0 when locking → endless 5-try windows; "locked" message enables enumeration | SEC-06 |
| 11 | **Low** | `logout.php` mutates session on GET without CSRF token | SEC-05/15 |
| 12 | **Low** | `db.php` leaks a dev hint on DB outage; no global exception handler in API | SEC-16 |
| 13 | **Low** | `item.php` create: parent FK accepted without existence check; position computed outside a transaction | SEC-09/18 |
| 14 | **Low** | 8000×8000 GD decode ≈ 256 MB → upload DoS if `memory_limit` is low; `sleep(1)` ties up workers | SEC-10/06 |
| 15 | **Low** | No SRI on CDN assets; 4 Bootstrap versions in circulation | SEC-21 |

## 4. Mandatory pre-ship checklist (enforced repo rule)

Before marking ANY backend/admin change complete, the agent must affirm each line (N/A allowed only with a stated reason):

1. All SQL values bound via prepared statements; every interpolated identifier traces to `sj_registry()` or a code literal.
2. No request-derived string reaches `include/require`, an unvalidated `header('Location')`, or a filesystem path.
3. Every echoed dynamic value passes `e()`, except columns ending `_html` which provably passed `sj_sanitize_html()` at write time.
4. New/changed fields are declared in the registry with correct type/max; `html` type used for anything rich.
5. New endpoint `require`s `api/_bootstrap.php` first (auth + CSRF + JSON); no mutation is reachable via GET.
6. Ran the SEC-02 XSS probe set against any touched write path; payloads stored inert.
7. No new JS uses `innerHTML`/`insertAdjacentHTML` with unescaped API or DOM-sourced data.
8. Upload paths unchanged OR re-verified: finfo+getimagesize whitelist, size/dimension caps, id-derived filenames, `media/.htaccess` intact.
9. `admin_users` and `settings` are not in the registry; no endpoint exposes password hashes or config values.
10. Session behavior unchanged OR: regenerate-on-privilege-change kept, timeouts enforced, cookie flags (HttpOnly/SameSite/Secure) intact.
11. Errors: no path/SQL/trace in any response; failures return generic JSON + a private log entry.
12. Mutations write an `audit_log` row (once SEC-19/S4 lands; until then, note the gap in the PR).
13. No secrets, `admin123`, dumps, archives, `.git`, or `*.md` plans in anything destined for `public_html` on prod.
14. `.htaccess` files (`_libs`, `media`, root) present and unmodified — or changes reviewed against SEC-12/13.
15. `composer audit` clean and all off-origin `<script>/<link>` tags carry SRI (when touching dependencies/templates).

---

### Critical files
- `public_html/admin/api/_bootstrap.php` — central guard (auth, CSRF, validation; where timeouts, audit hook, exception handler land)
- `public_html/_libs/edit.php` — session boot, cookie flags, `csrf_token`, `e()` (timeout logic goes here)
- `public_html/admin/login.php` — lockout fix, enumeration fix, forced-password-change redirect
- `public_html/_libs/media.php` — upload pipeline (EXIF strip for originals, dimension/memory caps)
- `public_html/js/contact.js` + `public_html/_templates/contact.php` — EmailJS abuse (SEC-23)
- `database/schema.sql` — `admin_users` (must_change_password/role); `audit_log` table
