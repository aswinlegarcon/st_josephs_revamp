# 3 — What We Built: A Guided Tour (Stages A & B)

> Goal: understand every change we've made so far, why we made it, and where it lives in
> the repo. Read docs #1 and #2 first — this file assumes you know the terms.
>
> The formal roadmap is [`PHASES.md`](../../PHASES.md). This is the plain-English tour.

---

## Where we started

The website was **42 flat PHP pages**. All text and images were typed directly into the
code. Problems:

- Only a programmer could change content.
- The same code was copy-pasted everywhere (hard to fix, easy to break).
- Serious security gaps (a default password, secrets in the code, no protections).
- Very slow (huge unoptimized images) — the host rated it "48% healthy."

The plan: turn it into a **database-driven CMS with an admin panel**, cleanly organized,
secure, and fast — **without changing how the site looks**. We work in small, safe
**phases**. Each phase is one focused change that we build, test in the running app, and
commit to git separately, so nothing ever breaks badly.

We group phases into **stages**. This tour covers the first two:

- **Stage A — Security** (phases S1–S4, F0): make it safe.
- **Stage B — Platform** (phases P1–P4): make it modern & organized.

---

## The repo structure (your map)

```
Stjosephs_Website/
│
├── public_html/            ← THE ONLY folder the web can see. The website itself.
│   ├── index.php           ← the home page (now a tiny "controller" — see P4)
│   ├── about.php, ...       ← the other 41 pages
│   ├── .htaccess           ← web-server security rules (S4)
│   ├── bootstrap.php       ← loads the autoloader (P1)
│   ├── _libs/              ← shared PHP helpers (the "engine")
│   │   ├── load.php        ← startup: pulls everything together
│   │   ├── config.php      ← reads settings (now just a thin loader — S1/P1)
│   │   ├── db.php          ← the database connection, db()
│   │   ├── edit.php        ← sessions, login helpers, e(), security headers
│   │   ├── audit.php       ← the activity-log helper
│   │   ├── sanitize.php    ← clean rich-text (thin shim → SJ\Content\Sanitizer)
│   │   ├── registry.php    ← the "what's editable" list (shim → SJ\Content\Registry)
│   │   ├── media.php       ← the image upload/resize pipeline
│   │   └── repo.php        ← functions that read the database ("repositories")
│   ├── admin/              ← the admin panel (login-protected)
│   │   ├── login.php, logout.php, password.php
│   │   ├── _layout.php     ← the admin panel's shared frame (sidebar, header)
│   │   ├── section.php     ← the edit screens for each content area
│   │   ├── assets/         ← the panel's CSS & JavaScript
│   │   └── api/            ← the backend the panel talks to
│   │       ├── index.php   ← the "front controller" — one entrance (P3)
│   │       ├── _bootstrap.php ← the security gate every API call passes through
│   │       └── field.php, item.php, order.php, upload.php, images.php
│   ├── css/, js/, photos/, media/   ← styles, scripts, images
│
├── src/                    ← NEW modern code, organized into classes (P1/P2)
│   ├── Core/   Config.php, Db.php
│   ├── Content/ Sanitizer.php, Registry.php
│   ├── Admin/  Audit.php
│   └── View/   Layout.php
│
├── views/                  ← NEW page templates for the layout engine (P4)
│   ├── layout.php          ← the outer HTML shell (<head>, <body>)
│   └── pages/home.php      ← the home page's body content
│
├── config/                 ← settings; config.php here holds SECRETS (never committed)
│   └── config.sample.php   ← a safe template (committed)
│
├── database/               ← NOT web-visible. The database blueprint & tools.
│   ├── schema.sql          ← all table definitions
│   ├── seed.php            ← fills the database with the site's real content
│   └── migrations/         ← small ordered updates to apply to an existing database
│
├── vendor/                 ← Composer's autoloader (auto-generated, committed)
├── composer.json           ← declares "SJ\ namespace = src/ folder"
├── docker-compose.yml, Dockerfile, run.sh   ← the local dev environment
├── PHASES.md, SECURITY.md, ADMIN_UI_DESIGN.md, CLAUDE.md   ← planning/rules docs
└── docs/learn/             ← YOU ARE HERE (these learning docs)
```

**The single most important rule:** only `public_html/` is reachable from the internet.
Everything else — `src/`, `config/`, `database/`, `vendor/` — sits *outside* it, so secrets
and internal code can't be opened in a browser.

---

# Stage A — Security

Goal: make the site safe to put on the internet. Each phase closed a specific gap from
[`SECURITY.md`](../../SECURITY.md).

## S1 — Get secrets out of the code
*(commit `[S1]`)*

**Problem:** the database password was written inside a file that gets committed to
GitHub. Anyone with repo access (or a leak) would have it.

**What we did:**
- Moved the real settings to `config/config.php`, which lives **above** `public_html/`
  (unreachable by the web) and is listed in `.gitignore` (never committed).
- Turned `public_html/_libs/config.php` into a **loader** with no passwords in it — it just
  knows *where* to find the real settings. The class doing this is `src/Core/Config.php`.
- For local development, Docker provides the password through an `.env` file (also
  gitignored); a committed `.env.example` shows the format.

**See it:** `git grep stjosephs_pw` finds the password only in `.env.example` (a throwaway
dev value), never in real code.

## S2 — Kill the default password
*(commit `[S2]`)*

**Problem:** the app shipped with `admin` / `admin123`. Bots scan the web for exactly this.

**What we did:**
- Added a `must_change_password` flag to the `admin_users` table (and a `role` column, so
  we can support multiple staff logins later).
- Built `public_html/admin/password.php` — a proper "set a new password" screen (with a
  live strength meter). On first login you are **forced** here and can't reach anything else
  until you set a strong 12+ character password.
- Guards were added so even the API refuses to work until the password is changed.

**See it:** run `./run.sh`, log in with `admin`/`admin123` — you're immediately sent to the
password screen.

## S3 — Harden the login session
*(commit `[S3]`)*

**Problem:** logged-in sessions never expired, the cookie wasn't fully protected, and the
account-lockout had a bug that made it useless.

**What we did (in `public_html/_libs/edit.php` + `login.php`):**
- Sessions now expire after **30 minutes idle** or **12 hours** total.
- The session cookie is `HttpOnly` (JS can't read it) and `SameSite=Lax`; the session ID is
  rotated regularly.
- Fixed the lockout counter so 5 wrong passwords **actually** locks the account for 15
  minutes, and made all failure messages identical (so attackers can't discover usernames).

## S4 — Walls, cameras, and headers
*(commit `[S4]`)* — the biggest security phase.

**What we did:**
- Added `public_html/.htaccess` to block folder listings, dotfiles (`.git`, `.env`), and
  backup/dump files, and to send safe **security headers** to the browser.
- Added admin-only headers `X-Frame-Options: DENY` and a `Content-Security-Policy` (blocks
  clickjacking and adds an XSS wall).
- Created the **audit log**: an `audit_log` table + `sj_audit()` helper that records every
  login, password change, and content edit (the "CCTV").
- Changed **logout** to a POST form with a CSRF token (no data-changing links anywhere).
- Also fixed the local Docker so `.htaccess` rules actually apply during development.

**See it:** `curl -I http://localhost:8090/admin/login.php` shows the headers; opening
`/_libs/config.php` gives 403.

## F0 — Measure the "slow" before fixing it
*(commit `[F0]`)*

**Why:** you can't prove you made something faster without a "before" number.

**What we did:** measured the live site and recorded it in
[`docs/perf-baseline.md`](../../docs/perf-baseline.md). The finding: the **home page is
10 MB and takes ~14 seconds** to fully load — and **95% of that is unoptimized images**.
The server itself is fast; the images are the problem. This tells the future performance
phases exactly what to fix. We also added a small **SQL query counter** for developers (turn
on `debug` and each page prints `<!-- sj-queries: N -->`) to keep pages efficient.

---

# Stage B — Platform (modernizing the code)

Goal: reorganize the code to be clean, modern, and easy to grow — **without changing what
any page looks like**. Every phase here kept the site pixel-identical.

## P1 — Add Composer & the autoloader
*(commit `[P1]`)*

**What we did:**
- Added `composer.json` declaring **`SJ\` = `src/`** and generated the autoloader in
  `vendor/`.
- Created the first two modern classes: `SJ\Core\Config` (settings) and `SJ\Core\Db` (the
  database connection).
- `public_html/bootstrap.php` loads the autoloader at startup, so any `SJ\...` class is
  available everywhere without a single `require`.
- The old `_libs/config.php` and `_libs/db.php` became **shims** — 2-line files that just
  call the new classes. This is the key trick: **old code keeps working while we modernize
  underneath it.**

## P2* — Move the clean libraries into classes
*(commit `[P2*]`)*

**What we did:** ported the self-contained, security-critical helpers into proper classes,
each with a shim so old names still work:

- `SJ\Content\Sanitizer` — the rich-text cleaner (from `sanitize.php`).
- `SJ\Content\Registry` — the "what's editable" list (from `registry.php`).
- `SJ\Admin\Audit` — the activity logger (from `audit.php`).

**Why the `*`?** We *deliberately* left the big, tangled, well-tested files (the image
pipeline `media.php`, the database readers `repo.php`, and the just-hardened session code in
`edit.php`) as they are, to be moved later in a dedicated cleanup phase (**R2**). Rewriting
battle-tested security code just to reorganize it is a needless risk. We only moved what was
clean and safe to move. This "scoped" honesty is why the commit is `P2*`.

## P3 — One front door for the admin API
*(commit `[P3]`)*

**Problem:** the panel called many separate API files directly (`field.php`, `item.php`…).

**What we did:** created `public_html/admin/api/index.php` — a **front controller**. Now the
panel makes every request to `index.php?r=field` (or `?r=item`, etc.), and this one file
routes to the right handler using a **hardcoded list** (the request can never name a file
directly — a security win). All the existing security checks still run. The panel's
JavaScript was updated to use the new address.

**See it:** in the panel, reordering a list makes a request to
`.../api/index.php?r=order` (visible in the browser's Network tab).

## P4* — A real layout engine
*(commit `[P4*]`)*

**Problem:** the home page mixed "get the data" and "build the HTML" in one long file, and
the page structure was copy-pasted everywhere.

**What we did:** introduced a **layout engine**:

- `src/View/Layout.php` — `Layout::render('home', $data)` renders a page's content inside a
  shared shell.
- `views/layout.php` — the outer HTML (`<head>`, fonts, `<body>`).
- `views/pages/home.php` — just the home page's body content.
- `public_html/index.php` — now a tiny **controller**: get the data, then call the layout
  engine (see doc #1, Part K for the full trace).

The home page's HTML output is **byte-for-byte identical** to before — we verified with a
diff and a screenshot. This is the pattern all pages will use as we convert them.

**Why the `*`?** The pages still pull in shared pieces (navbar, footer…) the old way, so the
final "one clean HTML document" cleanup is bundled into a later phase (**R1a**) where it
naturally pairs with unifying the site's mixed Bootstrap versions. We built the *engine*
now; the full page rewrite comes later.

> **What do `P2*` and `P4*` teach you?** Good engineering isn't doing everything at once —
> it's doing the *safe, valuable* part now and clearly labeling what's deferred and why.
> Never break working code for tidiness alone.

---

## How it all connects now

```
A visitor opens the home page:
  public_html/index.php  (controller)
        │  asks for data
        ▼
  _libs/repo.php  →  db()  →  SJ\Core\Db  →  MySQL     (read content safely)
        │  gives data back
        ▼
  SJ\View\Layout::render('home', data)
        │  builds HTML from views/pages/home.php + views/layout.php
        │  using e() and img_tag() to stay safe
        ▼
  Finished HTML → browser


An admin edits a slide:
  panel JavaScript  →  POST /admin/api/index.php?r=field   (front controller, P3)
        ▼
  api/_bootstrap.php  (the security gate: logged in? CSRF ok? password changed?)
        ▼
  field.php  →  checks the Registry  →  db()->prepare(...)->execute([...])   (safe write)
        ▼
  sj_audit('field.save', ...)   (records it in audit_log)
        ▼
  JSON reply → panel updates on screen
```

Every box is something you learned in docs #1 and #2.

---

## What's next (so you know the direction)

The remaining phases (in [`PHASES.md`](../../PHASES.md)) will:

- **R1a–c** — clean each page into one valid HTML document, unify to a single Bootstrap 5.3,
  and add CSS design tokens.
- **C1–C9** — convert each content area (About, Sports, Gallery, etc.) to the database with
  its own admin editor.
- **M1–M4** — the photo-collection manager, image cropping, and media library.
- **O1–O2** — an on-page "edit live" overlay.
- **F1–F4** — the performance work (WebP images, caching) that fixes the 10 MB home page.
- **X1–X3** — deployment, backups, monitoring.

---

## How to explore on your own (a first-day checklist)

1. `./run.sh` and open both the site and the admin panel. Click around.
2. Read `public_html/index.php` (tiny) → follow it into `views/pages/home.php`.
3. Read `public_html/_libs/repo.php` — see how data is read with prepared statements.
4. Read `public_html/admin/login.php` — find the prepared statement, CSRF check, and
   `password_verify`.
5. Read `public_html/admin/api/_bootstrap.php` — the security gate.
6. Look at `git log --oneline` — every phase (`S1`, `S2`, … `P4*`) is one commit with an
   explanation. Read a few commit messages; they document *why* each change was made.

Welcome to the team. When in doubt, re-read docs #1 and #2 — the fundamentals and the
security rules are 90% of the job. 🎓
