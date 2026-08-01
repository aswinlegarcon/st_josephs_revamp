# CLAUDE.md — Agent rules for the St. Joseph's MHSS website

Repo rules for any AI agent working in this codebase. Read this before editing.

## Project

- **What:** a 42-page school website (St. Joseph's MHSS, Ondipudur) being migrated from flat PHP to a MySQL-backed CMS with an admin panel. Same visual design, cleaner code, future-proof.
- **Stack:** PHP 8.3, MySQL 8, PDO. **No framework** — structured plain PHP with Composer PSR-4 (`SJ\` → `src/`). GD for images. Bootstrap 5.3.3 self-hosted (target state).
- **Dev workflow:** Docker. `./run.sh` builds/starts the stack, waits for DB, runs the seeder, and prints URLs (public `:8090/`, admin `:8090/admin/`). `./run.sh stop` / `./run.sh reset`. There is **no PHP/MySQL on the host** — always work through Docker.
- **Prod:** MilesWeb shared hosting (mPanel, no cPanel/SSH). Deploy = file upload; `vendor/` is committed. Docker never runs in prod.

## Canonical documents (keep them true)

| Doc | Role |
|---|---|
| `WEBSITE_CONTEXT.md` | Site inventory — every page, section, image, and the 15 known bugs. **The reference. Update it whenever page structure/content changes.** |
| `PHASES.md` | The roadmap — architecture + the ordered, shippable phases. Follow it; update phase status as you go. |
| `SECURITY.md` | **Normative** security catalog. Its §4 checklist is mandatory (see below). |
| `ADMIN_UI_DESIGN.md` | Admin UI design system + image-generation briefs. |
| `DYNAMIC_MIGRATION_PLAN.md`, `FEATURES_PLAN.md` | Legacy reference (schema DDL, image pipeline, seed inventory). Their "locked decisions" are **superseded** by `PHASES.md` §0. |

## MANDATORY security rule

Before you mark **any** change complete that touches `public_html/admin/**`, `public_html/_libs/**`, `src/**`, `database/**`, or any page that renders DB content:

1. Walk the relevant `SEC-*` items in `SECURITY.md` and apply their mitigations.
2. Affirm **every line** of `SECURITY.md` §4 (the 15-item pre-ship checklist).
3. In your completion summary, write **`SECURITY.md §4 checklist: PASS`** — or list each item marked N/A with a one-line reason.

Never skip this because a change "looks small". A one-line echo or a new endpoint is exactly where XSS/CSRF/SQLi land.

## Coding conventions (non-negotiable)

- **SQL:** values only via PDO **placeholders**; table/column **identifiers only from the registry** (`SJ\Content\Registry` / `_libs/registry.php`) or code literals. Never build SQL from request data.
- **Output:** every echoed dynamic value goes through `e()` (htmlspecialchars, ENT_QUOTES, UTF-8). The only exception is columns ending `_html`, which must have passed `sj_sanitize_html()` on write. Never raw-echo anything else.
- **Rich text:** new rich fields are typed `html` in the registry so the write path sanitizes them; the whitelist is frozen (`b, strong, i, em, br, p, span.hl-gold`).
- **Mutations:** state changes are **POST + CSRF** only; no mutating GET. Every new admin API file `require`s `admin/api/_bootstrap.php` first (auth + CSRF + JSON).
- **Registry:** any new editable field/entity must be registered; `admin_users` and `settings` are **never** registered.
- **Includes/paths:** no request-derived string ever reaches `include`/`require`/a filesystem path/`header('Location')`.
- **URLs:** every legacy URL keeps working (stubs/thin controllers). Don't break `tamilacademy.php`, `gal-annual.php`, `kg.php`, etc.
- **Performance budget:** each converted page ≤ 12 SQL queries; cache `settings`/`pages` per request; no N+1 in loops (repos return image data pre-joined). Check with `config['debug']=true` → the `<!-- sj-queries: N -->` comment (`db_query_count()`). Baseline in `docs/perf-baseline.md`.
- **Secrets:** real secrets live only in `config/config.php` **above the webroot** (gitignored). Never commit them; never hardcode credentials or API keys in client-served code.

## Theme tokens (match, don't invent)

- Navy `#2b4b8a` / `#1a355d`; gold `#ffd700`. **Never gold text/icons on white** — use `#8a6d00` (`--gold-ink`).
- Fonts: public — Fjalla One (headings), Dancing Script (accent words), League Spartan (body); admin — Segoe UI, with Fjalla One only for big stat numbers.
- Full admin token/component spec: `ADMIN_UI_DESIGN.md`.

## Verification habits

- Run the change in the real app via `./run.sh`; exercise the edited area in the browser (create → edit → reorder → delete for content; upload for media).
- Seeder must stay **idempotent** — running it twice changes nothing.
- No console 404s; no duplicate element IDs; visual diff vs. before = none unless the change is intentionally visual.
- For anything security-relevant, run the SEC item's "Agent verification" step.

## Deploy cautions

- Never deploy to `public_html` on prod: `database/`, `.git/`, `*.md` plan docs, docker files, dev `config/config.php`, dumps/archives.
- Migrations are **additive-only** (no destructive DDL); apply via mPanel's DB tool.
- After first prod ship, **prod DB is the source of truth** — pull dumps down, never push a dev DB up.
