# St. Joseph's MHSS — Website & CMS

Website for **St. Joseph's Matriculation Higher Secondary School**, Ondipudur, Coimbatore, currently being migrated from a flat-PHP static site to a MySQL-backed CMS with an admin panel. Same visual design, cleaner code, future-proof architecture.

## Stack

- **PHP 8.3** · **MySQL 8** · **PDO** — structured plain PHP with Composer PSR-4 (`SJ\` → `src/`), no framework
- **GD** image pipeline (auto-crop, WebP + progressive JPEG renditions)
- **Bootstrap 5.3.3** (self-hosted, target state)
- **Docker** for local development

## Local development

The host has no PHP/MySQL — everything runs through Docker.

```bash
./run.sh          # build + start stack, seed the DB, print URLs
./run.sh stop     # stop the stack
./run.sh reset    # stop and drop the DB volume (fresh start)
```

- Public site: `http://localhost:8090/`
- Admin panel: `http://localhost:8090/admin/`

The first admin login forces a password change (the seeded default is for local dev only).

## Configuration

`public_html/_libs/config.php` reads DB settings from environment variables (set by
`docker-compose.yml`) with **local-dev fallbacks only** — it contains no production
secrets. Real production credentials live in `/config/config.php` **above the webroot**
and are gitignored (see `SECURITY.md`).

## Documentation

| Doc | What it is |
|---|---|
| [`PHASES.md`](PHASES.md) | The roadmap — target architecture + ordered, shippable phases |
| [`SECURITY.md`](SECURITY.md) | Normative security catalog + mandatory pre-ship checklist |
| [`ADMIN_UI_DESIGN.md`](ADMIN_UI_DESIGN.md) | Admin UI design system + image-generation briefs |
| [`WEBSITE_CONTEXT.md`](WEBSITE_CONTEXT.md) | Full site inventory (every page, section, image, known bugs) |
| [`CLAUDE.md`](CLAUDE.md) | Contributor/agent rules (security checklist is mandatory) |
| `DYNAMIC_MIGRATION_PLAN.md`, `FEATURES_PLAN.md` | Legacy reference (DB schema, image pipeline) — decisions superseded by `PHASES.md` |

## Deployment

Production is **MilesWeb shared hosting (mPanel)** — deploy is by file upload; `vendor/` is
committed; `database/`, `.git/`, and `*.md` docs are never uploaded. See `PHASES.md` §4.

---

© St. Joseph's MHSS, Ondipudur. All rights reserved. Not licensed for redistribution.
