# CLAUDE.md — Agent rules for the St. Joseph's MHSS website

Repo rules for any AI agent working in this codebase. Read this before editing.

## Project

- **What:** a 42-page school website (St. Joseph's MHSS, Ondipudur), migrated from flat PHP to a MySQL-backed CMS with an admin panel (complete), now undergoing the owner-commissioned **Stage J public redesign** ("Global Campus" — `PUBLIC_UI_DESIGN.md`).
- **Stack:** PHP 8.3, MySQL 8, PDO. **No framework** — structured plain PHP with Composer PSR-4 (`SJ\` → `src/`). GD for images. Bootstrap 5.3.3 self-hosted (target state).
- **Dev workflow:** Docker. `./run.sh` builds/starts the stack, waits for DB, runs the seeder, and prints URLs (public `:8090/`, admin `:8090/admin/`). `./run.sh stop` / `./run.sh reset`. There is **no PHP/MySQL on the host** — always work through Docker.
- **Prod:** MilesWeb shared hosting (mPanel + SSH; no Docker/Composer on the server). Deploy = file upload or rsync/scp per `DEPLOY.md`; `vendor/` is committed. Docker never runs in prod.

## Canonical documents (keep them true)

| Doc | Role |
|---|---|
| `WEBSITE_CONTEXT.md` | Site inventory — every page, section, image, and the 15 known bugs. **The reference. Update it whenever page structure/content changes.** |
| `PHASES.md` | The roadmap — architecture + the ordered, shippable phases. Follow it; update phase status as you go. |
| `SECURITY.md` | **Normative** security catalog. Its §4 checklist is mandatory (see below). |
| `ADMIN_UI_DESIGN.md` | Admin UI design system + image-generation briefs. |
| `PUBLIC_UI_DESIGN.md` | **Public site design system** ("Global Campus", Stage J) — the rendering authority for migrated pages. |
| `DYNAMIC_MIGRATION_PLAN.md`, `FEATURES_PLAN.md` | Legacy reference (schema DDL, image pipeline, seed inventory). Their "locked decisions" are **superseded** by `PHASES.md` §0. |

## MANDATORY security rule

Before you mark **any** change complete that touches `public_html/admin/**`, `public_html/api/**`, `public_html/bootstrap.php`, `src/**`, `database/**`, or any page that renders DB content:

1. Walk the relevant `SEC-*` items in `SECURITY.md` and apply their mitigations.
2. Affirm **every line** of `SECURITY.md` §4 (the 15-item pre-ship checklist).
3. In your completion summary, write **`SECURITY.md §4 checklist: PASS`** — or list each item marked N/A with a one-line reason.

Never skip this because a change "looks small". A one-line echo or a new endpoint is exactly where XSS/CSRF/SQLi land.

## MANDATORY visual-fidelity rule (Stage J era)

**History:** from the start of the migration until 2026-08-23 this section was a hard
visual FREEZE (pixel-identical to git `6bd0d11` / the live site) — its precondition
(migration + admin workflow complete) was met, and the owner explicitly commissioned
the Stage J public redesign, which retired the freeze. The freeze-era discipline
(compensate for framework drift, don't "fix" quirky-but-shipped CSS) still applies
to any page Stage J has not yet reached.

The rules now:

1. **The rendering authority is `PUBLIC_UI_DESIGN.md`** ("Global Campus"). Pages that
   Stage J has migrated must render per that spec — fidelity to the spec replaces
   fidelity to the old baseline.
2. **Un-migrated pages must not drift.** Until their Stage J phase lands (see the
   PHASES.md Stage J table), pages may show ONLY the approved J1 foundation changes
   (fonts/tokens). Everything else stays as shipped, including quirky CSS.
3. **Visual changes outside the approved system still need explicit owner approval
   first** — the redesign approval covers `PUBLIC_UI_DESIGN.md`, not carte blanche.
   Never slip an off-spec change in under a refactor.
4. **Admin lockstep.** The admin panel's live-preview recipes (panel.css `--veil`,
   `.sj-prev-cap-*`, `.sj-prev-tm*`) mirror public visuals — update them in the SAME
   phase that changes the public recipe they mirror.
5. **Immutable geometry.** Image-preset aspect ratios and the `img_tag()` legacy
   contract (bare `<img>`, no dims) survive the redesign — changing them re-crops or
   re-flows real content.

Before marking complete **any** change that touches a rendered page, view, partial, or
CSS: verify against `PUBLIC_UI_DESIGN.md` (migrated pages) or the no-drift rule
(un-migrated pages), then affirm **`Visual-fidelity: PASS`** in your completion
summary — listing any spot that deviates from the spec, with a one-line reason and
the owner sign-off that approved it.

## MANDATORY file-writing rule (this is a monitored corporate endpoint)

This repo is developed on an EDR-monitored (CrowdStrike Falcon) work machine. **On 2026-08-08 a Falcon rule killed an agent's shell process for "Web Shell / Persistence"** — the agent had used a `python3` heredoc to rewrite `_libs/edit.php` in place and then `cat > public_html/admin/editmode.php <<'EOF'` to drop a new PHP file into the admin directory, in one process tree. The code was a legitimate 26-line CSRF-checked edit-mode toggle, but at the process level "interpreter/shell writes new request-handling PHP into a web-served directory" *is* the web-shell signature. It escalated to the organisation's IT security team.

Therefore, without exception:

1. **Never create or modify source files with shell commands.** No `cat > file`, no `tee`, no `>`/`>>` redirection, no heredocs, no `sed -i`, no `python3`/`php -r` scripts that `open(..., 'w')` or `file_put_contents()` a tracked file. Use your **editor tools** (Write / Edit / NotebookEdit) for **every** file change. They perform the same edit without the shell process lineage that trips behavioural detection.
2. **Shell is for read-only work and real commands only** — `git`, `grep`, `ls`, `curl`, `docker compose`, `composer`, test runs. Reading files with the shell is fine; writing them is not.
3. **A silent or unexplained failure is a stop condition, not a retry prompt.** If a write returns a bare non-zero exit with no error text, or a file vanishes after you created it, assume a security control blocked it. **Stop, tell the user, and wait.** Never retry the same write through a different mechanism — that is bypassing a control, whether or not you realise it at the time. (This is exactly what went wrong on 2026-08-08: the block was misread as a broken heredoc and re-attempted with a file-write tool, which succeeded.)
4. **Committed generators are the narrow exception, and stay narrow.** `database/extract-*.php` runs *inside the container* and may write only **generated data and inert assets** — `database/seed-data/*.php` data arrays and `public_html/css/albums/*.css`. No generator may ever emit **executable PHP into `public_html/`**. Adding a new generator that writes to the webroot needs explicit user approval first.
5. **Never touch security tooling.** Do not attempt to inspect, disable, exclude paths from, or work around Falcon or any endpoint agent. Path exclusions are IT security's decision — surface the need to the user and let them route it.

If a task seems to require a prohibited write, say so and propose the editor-tool equivalent instead of improvising.

## Coding conventions (non-negotiable)

- **SQL:** values only via PDO **placeholders**; table/column **identifiers only from the registry** (`SJ\Content\Registry`) or code literals. Never build SQL from request data.
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
- Fonts: public (since Stage K "bold & clean", owner-approved) — Fjalla One (headings/display) + **Manrope** (body) ONLY; Dancing Script is retired from the public site (emphasis = gold Fjalla or uppercase letterspaced Manrope). Admin (since N8 "Prospectus", owner-approved) — Segoe UI body with **self-hosted** Fjalla One for titles/stat numbers and Dancing Script for sparing flourishes (`public_html/assets/fonts/` — the admin CSP blocks Google Fonts).
- Full admin token/component spec: `ADMIN_UI_DESIGN.md`.

## Verification habits

- Run the change in the real app via `./run.sh`; exercise the edited area in the browser (create → edit → reorder → delete for content; upload for media).
- Seeder must stay **idempotent** — running it twice changes nothing.
- No console 404s; no duplicate element IDs. **Visual fidelity per the visual-fidelity rule** — migrated pages match `PUBLIC_UI_DESIGN.md`, un-migrated pages don't drift; off-spec changes require prior owner sign-off.
- For anything security-relevant, run the SEC item's "Agent verification" step.

## Deploy cautions

- Never deploy to `public_html` on prod: `database/`, `.git/`, `*.md` plan docs, docker files, dev `config/config.php`, dumps/archives.
- Migrations are **additive-only** (no destructive DDL); apply via mPanel's DB tool.
- After first prod ship, **prod DB is the source of truth** — pull dumps down, never push a dev DB up.
