# Deploying to Shared Hosting

> **What you'll learn:** what "deploying" means, the three families of hosting, what *shared
> hosting* is and what it takes away, why our host (MilesWeb + mPanel, no SSH) forced a dozen
> decisions in this codebase, the exact release runbook, what permissions like `644` mean,
> what DNS and HTTPS do, and how you roll back with no terminal.
>
> **Prerequisites:** you can read basic PHP (`require`, arrays) and you know a browser asks a
> server for a page. You do **not** need to have deployed anything before. Every term is
> explained the first time it appears.
>
> **Where it lives in our code:** `DEPLOY.md` (the runbook — primary source for this
> document), `CLAUDE.md:85-89`, `.gitignore`, `composer.json`, `src/Core/Config.php`,
> `public_html/bootstrap.php`, `public_html/.htaccess`, `public_html/media/.htaccess`,
> `public_html/admin/health.php`, `database/backup.sh`, `config/config.sample.php`.

---

## 1. The one-paragraph version

**Deploying** means taking code that works on your laptop and putting it on a computer the
public can reach. Ours is **MilesWeb shared hosting**, driven through a web control panel
called **mPanel**. There is no command line on it. `DEPLOY.md:3-5`:

> Production is **MilesWeb shared hosting (mPanel)** — no SSH, no Docker, no Composer on
> the server. Deploy = upload files. `vendor/` is committed so the server never runs
> Composer. This runbook is the whole procedure.

A release is literally: build a list of folders, upload them over the previous release, paste
any new database changes into a web form, open one URL to check health. That constraint — *no
shell* — is not a detail. It explains why this repo commits its dependencies, why database
changes are plain `.sql` files, and why there is no build step anywhere.

---

## 2. The problem this solves

Locally the site runs because Docker starts a web server, PHP and MySQL for you (`./run.sh` →
`http://localhost:8090/`). Nobody else can open that URL. Deployment reproduces that
arrangement on a publicly addressable machine and keeps the two in step. Three things must
arrive and agree; if any one is stale the site breaks — usually loudly (blank page), sometimes
quietly (a page that renders but saves nothing).

| Thing | On your laptop | In production |
|---|---|---|
| **Code** | the git working tree | files uploaded into `public_html/`, `src/`, `views/`, `vendor/` |
| **Database** | the MySQL container | a MySQL database created in mPanel |
| **Secrets** (DB password, tokens) | `config/config.php`, made by `run.sh` from the sample | `~/config/config.php`, typed by hand, once |

**The three families of hosting.** *Shared hosting:* one physical server runs hundreds of
customers' sites at once. You get a folder, a database, and a **control panel** — a website
with buttons for "upload a file", "create a database", "add a cron job". No command line, you
are not `root` (the all-powerful admin account), you cannot install software or run background
programs. Cheapest, least flexible. This is us. *VPS / cloud:* a **VPS** (Virtual Private
Server) is a whole simulated computer rented to you alone — EC2, DigitalOcean, Hetzner. You
get SSH (a remote command line), you are `root`, and you install and configure everything: web
server, PHP, MySQL, TLS, firewall, backups. Total freedom, total responsibility. *PaaS
(Platform as a Service):* Heroku, Render, Fly.io — you hand it your git repo, it builds and
runs the app and gives you a URL. You never touch a server, but it wants the app shaped its
way (no writing files to local disk, environment variables instead of config files, a build
step it controls) and the bill grows with traffic.

**What shared hosting takes away, and what we did about it.** Every row is a live decision in
this repo:

| Limit | Our consequence | Evidence |
|---|---|---|
| No shell → **Composer cannot run** on the server | `vendor/` (the downloaded PHP libraries) is **committed to git** and uploaded with the release | `.gitignore:17-19` — "vendor/ is intentionally committed for shared-hosting deploy (no Composer on MilesWeb). Do NOT ignore it once it exists." |
| No shell → no migration tool | Database changes are plain `.sql` files **pasted into the panel's DB tool** | `DEPLOY.md:44-45` |
| No shell → no crontab | The nightly backup is one line typed into **mPanel → Cron Jobs** | `DEPLOY.md:106-110` |
| No Node/build server | **No build step at all** — no bundler, no Sass, no minifier; CSS/JS ship as written | Bootstrap self-hosted verbatim under `public_html/assets/vendor/` |
| Manual uploads | Cache-busting is one constant bumped by hand, not a content hash | `public_html/bootstrap.php:16` |
| PHP runs as *your account user* | `media/` is `755` in production, never `777` | `DEPLOY.md:32`, `run.sh:26-29` |

**Composer** is PHP's dependency manager. Normally you run `composer install` on the server and
it downloads what `composer.json` lists. We cannot, so we ship the result. `composer.json:11-18`
mostly wires up **PSR-4 autoloading** — a naming rule mapping the class `SJ\Core\Config` to the
file `src/Core/Config.php`, so PHP finds classes without a `require` each.
`public_html/bootstrap.php:22` loads it, under a comment saying exactly why: "vendor/ is
committed, so prod needs no Composer run."

---

## 3. How it works in general

**The webroot, and the folder above it.** The **webroot** (or "document root") is the one folder
the web server will serve over HTTP. On MilesWeb the account looks like this (`DEPLOY.md:13-15`):

```
/home/<account>/          ← the account home. NOT web-served.
├── config/config.php     ← real secrets live here
├── backups/              ← nightly dumps (database/backup.sh:19)
├── releases/             ← previous release zips, for rollback (DEPLOY.md:66)
└── public_html/          ← THE WEBROOT. Everything here is on the internet.
    └── index.php  admin/  media/  .htaccess
```

Memorise the rule: **a URL can only ever name something inside the webroot.**
`https://site/index.php` maps to `public_html/index.php`. No URL reaches
`/home/<account>/config/config.php`, because a request path starts at `public_html/` and cannot
climb above it. That is the whole security argument, and `DEPLOY.md:24-26` even gives the
fallback: "If mPanel does not allow a sibling dir above `public_html`, place it at the highest
non-web-served level available and confirm `/config/config.php` returns **404** over HTTP."

**What would leak if it were inside.** That file holds the database password, `health_token`,
`recaptcha_secret` and the office e-mail. PHP files are *executed*, not shown — and a config
file only `return`s an array, so executing it reveals nothing… *until the day PHP stops
running.* A mistyped `.htaccess`, a reset panel setting, an upgrade that disables the handler:
the server then serves the file as plain text and your DB password is a public download. A file
above the webroot cannot have that bad day. Our loader looks in exactly that place —
`src/Core/Config.php:21-23` computes `$root = dirname(SJ_PUBLIC_ROOT)` (the parent of
`public_html`) and reads `$root . '/config/config.php'`. Priority is environment variables →
`config/config.php` → the committed `config.sample.php`. Docker uses env vars; production uses
the real file; the sample ships `'pass' => 'CHANGE_ME'` (`config/config.sample.php:22`) so it
can never be a working credential.

**Defence in depth: `.htaccess`.** This is a per-folder config file Apache reads at request
time. Ours blocks what must never be served (`public_html/.htaccess:8-14`):

```apache
RedirectMatch 404 "^/\.(?!well-known/)"     # dotfiles: .git, .env, .htpasswd…
<FilesMatch "\.(sql|bak|old|zip|tar|gz|tgz|log|md|lock|swp|dump|ini|sh)$">
  Require all denied                        # source/backup/dump/archive artifacts
</FilesMatch>
```

Read it as a **safety net, not a plan**. `DEPLOY.md:55-56`: the root `.htaccess` "already blocks
most of these if they slip in, but keep them out of the upload set entirely."

---

## 4. How we use it — every place in this codebase

### 4.1 One-time setup (`DEPLOY.md:7-32`)

1. **PHP version** — select **PHP 8.3** in mPanel; confirm extensions `gd` (image resizing),
   `pdo_mysql` (database), `exif` (photo rotation data).
2. **Database** — create the MySQL database + user in mPanel, then import `database/schema.sql`
   through the panel's DB tool (phpMyAdmin). That one import creates every table.
3. **Secrets** — create `~/config/config.php` from `config/config.sample.php`; fill in DB
   credentials plus `'force_secure_cookies' => true`, `'session_save_path'`, `'health_token'`,
   `'debug' => false`, `'recaptcha_secret'`, `'contact' => ['mail_to' => …]`.
4. **HTTPS** — enable the free SSL certificate in mPanel and force the redirect.
5. **Seed content** (first ship only) — run the seeder locally against a dump or paste the
   INSERTs. `DEPLOY.md:29-30` is blunt: do **not** upload `database/seed.php` to run on prod.
6. **Permissions** — directories `755`, PHP files `644`, `config/config.php` `600`,
   `public_html/media/` `755`. Never `777`.

### 4.2 Every release (`DEPLOY.md:34-48`)

1. **Tag** the commit you are shipping (a git label, so you can find this exact code again).
2. **Bump the asset version** — one constant decides whether browsers re-fetch your CSS
   (`public_html/bootstrap.php:16`, currently `define('SJ_ASSET_VER', '20260809.2')`). Skip it
   and returning visitors keep the *old* stylesheet for up to a year, because
   `public_html/.htaccess:50` caches assets `max-age=31536000, immutable`.
3. **Build the upload set — ONLY these paths** (`DEPLOY.md:39-42`, verbatim):

   ```
   public_html/    src/    views/    vendor/    config/config.sample.php
   ```
4. **Upload** via the mPanel File Manager (or FTP), extracting over the previous release.
5. **Apply migrations** — each new `database/migrations/NNN_*.sql` pasted into the DB tool,
   **in order**. They are **additive-only**, never destructive.
6. **Smoke-check** — open `https://<site>/admin/health.php?token=<health_token>` and confirm
   every gating check is `true`. Then click through: home, one page per family, admin login
   plus one edit.

### 4.3 What must never be uploaded (`DEPLOY.md:50-56`)

| Excluded | The real risk |
|---|---|
| `database/` | `schema.sql`, `seed.php`, the extract/backfill generators. The seeder prints credentials; the generators write files. Dev-only, all of it. |
| `.git/` | The full history — every file ever committed, including things you deleted later. |
| `docker-compose.yml`, `Dockerfile`, `run.sh` | Dev credentials and infrastructure detail. `run.sh:29` even does `chmod 777` — scoped to dev, disastrous as a hint on prod. |
| `.env` / `.env.example` | Env vars **win** over the config file (`src/Core/Config.php:30-35`), so a stray `.env` can silently point prod at the wrong database. |
| the dev `config/config.php` | Wrong credentials at best; **overwrites the real production secrets** at worst (§7). |
| `*.md` docs (`PHASES.md`, `SECURITY.md`, `DEPLOY.md`, `docs/`) | `SECURITY.md` is a written list of this site's weak points. Handing it over is handing over the map. |
| `Admin Panel UI.html` prototype | A stray unauthenticated page in the webroot. |
| any `.sql` / dump / zip / backup | A dump inside the webroot is a one-click public download of all content **and** the admin password hashes. |

`CLAUDE.md:87` states the same rule; `.gitignore:22-31` keeps dumps and archives out of git in
the first place.

### 4.4 The health endpoint

`public_html/admin/health.php` answers "is this deployment actually working?" It is token-gated
and returns HTTP **404** — not 403 — to strangers so its existence is not advertised
(`health.php:15-18`). `DEPLOY.md:74-84`:

| Check | Meaning |
|---|---|
| `php_version` | PHP ≥ 8.1 (target 8.3) |
| `ext_gd` / `ext_pdo_mysql` / `ext_exif` | required extensions present |
| `db_connect` / `schema` | database reachable + tables exist |
| `media_writable` | uploads will work |
| `opcache` | bytecode cache on (speed) |
| `https` | informational — should be `true` in prod, is `false` on the dev http box |
| `disk_free` | gates below 200 MB free (uploads/backups would start failing) |
| `images` / `sitemap` | content sanity: images table populated, sitemap.xml present |
| `backup_age` | informational — hours since the newest `db-*.sql.gz` (X2) |

`https` and `backup_age` are deliberately excluded from pass/fail (`health.php:63-64`);
everything else gates. A failing gate returns **HTTP 503**, exactly what an uptime robot
understands.

### 4.5 Permissions, in plain words

A Unix permission is three digits — **owner**, **group**, **everyone else** — each summing
`read = 4`, `write = 2`, `execute = 1`. On a **directory**, "execute" means *may enter*, which
is why folders are `755` and not `644`.

| Mode | Owner | Group | Others | Used for |
|---|---|---|---|---|
| `644` | read+write | read | read | every PHP/CSS/image file |
| `755` | read+write+execute | read+execute | read+execute | every directory, and `media/` in prod |
| `775` | read+write+execute | read+write+execute | read+execute | directories the image pipeline creates at runtime |
| `600` | read+write | — | — | `config/config.php` only |
| `777` | everything | everything | everything | **never** |

**`600` for `config/config.php`** — only the account user may read it. On a shared machine other
customers' processes are other users; `600` is the difference between "my neighbour cannot read
my DB password" and "they can". **`755`, not `777`, for `media/`** — `SECURITY.md` SEC-22
explains that on mPanel "PHP runs as the account user under suPHP/LiteSpeed — 777 is never
needed". The owner *is* PHP, so the owner's `7` already grants write; `777` adds write for
everyone else and buys nothing. `run.sh:29` does `chmod 777` locally and says why right above it
— the Docker container runs PHP as `www-data`, a *different* user from you: "In PRODUCTION
(mPanel/LiteSpeed) PHP runs as the account user, so deploy media/ as 755 — never 777 (SEC-22).
This 777 is scoped to the localhost dev box."

Files written by the upload pipeline are locked down in code: `src/Media/Pipeline.php:115`
creates directories `0775`, and lines 123/224/230 `chmod` each written image to `0644`. `0644`
means **not executable** — even if an attacker smuggled PHP inside a `.jpg`, it cannot run. Belt
and braces, `public_html/media/.htaccess` turns the engine off for the whole folder with
`php_flag engine off` plus `Require all denied` on `\.(php|phtml|phar)$`.

### 4.6 DNS and HTTPS

**DNS** (Domain Name System) is the internet's phone book. "Pointing" `stjosephsondipudur.com`
at the host means creating a record mapping the name to the host's IP address. Browsers do that
lookup first — literally step one of a page load. Nothing about the server changes; only the
name resolves somewhere new. Propagation takes minutes to hours.

**HTTPS** is HTTP inside an encrypted tunnel. It needs a **certificate**: a file signed by a
trusted authority proving this server really is `stjosephsondipudur.com`. mPanel issues a free
one (`DEPLOY.md:27`). Three knock-on effects: (1) **force the redirect** — every `http://`
request answers `301` (permanent redirect) to the `https://` URL, so one canonical address
exists; (2) **turn on the strict-transport header** — `public_html/.htaccess:21-22` already
carries it commented out, "Enable once the site is HTTPS-only"; (3) **secure cookies** — set
`'force_secure_cookies' => true` (`DEPLOY.md:18`) so the admin session cookie is never sent over
plain HTTP. The SEO reason to care is duplication: `http://` vs `https://`, `www.` vs bare — four
addresses to a search engine unless you pick one. Our pages emit a **canonical** URL built from
`base_url` (`config/config.sample.php:28` → `views/shell.php:18`), already
`https://stjosephsondipudur.com`, so the redirect must agree with it or you are telling Google
two stories.

### 4.7 Rollback with no shell

`DEPLOY.md:64-68`, in full:

> Keep the previous release zip in a non-web folder on the host (e.g. `~/releases/`). To roll
> back: re-extract the previous zip over `public_html/`. Because migrations are additive-only,
> the older code still runs against the newer schema. Do **not** roll back the database.

That last sentence is load-bearing. **Additive-only** means a migration may add a table or column
but never drop or rename one. So yesterday's code, which does not know about today's new column,
still runs — it just ignores it. If migrations were destructive, code and schema would have to
move in lockstep and a rollback would mean restoring the database too, losing every edit since
the deploy. On data direction, `DEPLOY.md:58-62` / `CLAUDE.md:89`: content flows dev → prod
**only for the very first ship**; after that **production is the source of truth** — pull nightly
dumps down for testing, never push a dev database up.

### 4.8 Launch-day items that are not code

| Item | Source | What it is |
|---|---|---|
| **Google Search Console** | `docs/learn/08-stage-h.md:267-281` | Verify the domain (DNS record or an HTML upload — mPanel does either), submit `sitemap.xml`, watch Coverage for 41 submitted / 41 indexed. |
| **UptimeRobot × 2** | `DEPLOY.md:125-132` | A keyword monitor on `https://stjosephsondipudur.com/` expecting `St.Joseph`, and an HTTP monitor on `/admin/health.php?token=…`. Any non-200 mails you. |
| **mPanel backup cron** | `DEPLOY.md:106-110` | One nightly entry, e.g. 01:30: `/bin/sh /home/<account>/database/backup.sh` — gzipped DB dump nightly, `media/` archive weekly, into `~/backups/` (non-web), 14-day retention. |
| **Google Business Profile** | `docs/learn/08-stage-h.md:248-252` | Claim the school on Maps; keep name/address/phone identical to the site footer. Local searches are answered from this, not from web pages. |
| **Cloudflare (optional)** | `PHASES.md:161`, `docs/learn/08-stage-h.md:338-341` | A free CDN/proxy in front of the site. Needs a DNS change; verify an `/admin*` bypass rule. Deploy-day option, not a requirement. |

Note the cron path: `/home/<account>/database/backup.sh` lives **above** the webroot, and
`database/backup.sh:23-25` refuses to run if `BACKUP_DIR` ends up inside `public_html`.

---

## 5. Why this is the right approach here

Honestly: this is not the best deployment story available. It is the best one *under these
constraints*.

| Alternative | Why not, here |
|---|---|
| **A VPS with git deploy** | Technically better in every way — SSH, `composer install`, real migrations, CI. But somebody must then own OS patching, TLS renewal, firewall rules and MySQL tuning, forever. A school has no on-call engineer. |
| **A PaaS (Heroku / Render)** | Elegant, but it wants the app its way. Ours writes uploaded photos to `public_html/media/` on local disk; PaaS filesystems are ephemeral, so that alone means adding object storage. Plus a recurring bill. |
| **Static hosting (Netlify / Pages)** | Free and fast, and cannot run PHP at all. The whole point here is an **admin panel** the school edits — that needs a server and a database. |
| **Git-based deploy on the same host** | mPanel gives no shell, so no `git pull` on the server. Dead end. |

Against them, the actual constraints: a **school budget** (hosting comes out of a fixed operating
budget, and the MilesWeb account is already paid for); a **non-technical owner** who must never
need a terminal (mPanel's file manager and database tool are things a school office can be shown
once and use); **every legacy URL must keep working** — `CLAUDE.md` requires it, and
`tamilacademy.php`, `gal-annual.php`, `kg.php` are printed on materials and indexed by Google, so
a file-per-URL layout in a plain webroot is the easiest way to guarantee it; and **low, local
traffic** — one school's parents in one city, exactly what shared hosting is sized for. `vendor/`
committed, migrations as pasteable SQL, no build step, one asset constant: each is slightly
clumsy alone, and each removes a requirement the host cannot meet.

---

## 6. How this scales

In the order things will start to hurt:

1. **A staging site — the first thing you will actually want.** Today the only place to see the
   real thing is production. A second mPanel subdomain with its own database, receiving the
   upload set first, costs almost nothing and removes the deploy-and-pray feeling.
2. **A smarter upload.** Full-tree uploads over FTP get slow; `vendor/` alone is hundreds of
   files. A checksum-based sync (rsync, or an FTP client with "only newer files") uploads the
   delta. Note the risk it creates: partial uploads, and stale `media/` — see §7.
3. **Migrations that record themselves.** Pasting SQL by hand means the only record of "which
   migrations has prod had?" is your memory. A tiny `schema_migrations` table plus an admin-run
   applier makes it auditable and still needs no shell.
4. **Then, and only then, a VPS with git deploy and CI.** **CI** (Continuous Integration) is a
   robot that on every push runs your checks and, if they pass, deploys. That is the endpoint —
   but it *requires* someone to own a server. Do not move until there is a person, not a plan.

**The first thing that will hurt:** uploads outgrowing the release. `media/` already holds the
renditions for 478 legacy photos, and `DEPLOY.md:97-99` says to upload `public_html/media/` with
the release *and* apply the `image_renditions` rows. As the school adds galleries, re-uploading
that folder each release becomes untenable — and re-uploading a *stale* copy becomes a real way
to delete photos. The fix is cheap: stop shipping `media/` with releases and treat it as
production-owned data covered by the backup.

---

## 7. Gotchas and mistakes to avoid

1. **Uploading your dev `config/config.php` over the live one.** The worst mistake here and the
   easiest to make — same filename in both places. Best case the site instantly cannot reach the
   database. Worst case you have *overwritten* the production secrets: real DB password,
   `health_token`, reCAPTCHA secret. They exist nowhere else (gitignored, `.gitignore:4`) and
   must be recreated by hand. This is why `DEPLOY.md:41` lists `config/config.sample.php` and
   never `config/config.php`.
2. **Leaving `database/` or a `.sql` dump in the webroot.** `schema.sql` reveals the data model;
   a dump reveals the content *and* the admin password hashes. `public_html/.htaccess:12-14`
   denies `.sql` — but a rule you rely on is a rule that can be switched off by a panel setting
   you did not make.
3. **Forgetting a migration.** New code plus old schema equals `Column not found` on whichever
   page nobody visits until Monday. Migrations go **in order** — `007` may assume `006` ran.
4. **Mixed content after enabling HTTPS.** If an HTTPS page pulls a script or stylesheet over
   plain `http://`, the browser blocks it — usually silently, and usually only the styling
   breaks. Grep the served output for `http://` before you flip the switch.
5. **Case-sensitive filenames.** The production server is Linux and treats `Logo.PNG` and
   `logo.png` as different files. Many dev machines (macOS, Windows) do not, so a link that works
   locally can 404 in production for no visible reason. If an image is missing after a deploy,
   check the case *first*.
6. **Overwriting `media/` with a stale local copy.** Photos uploaded through the admin panel
   exist only in production; extracting an older local `media/` over it deletes them.
7. **Pushing a dev database over the live one.** `CLAUDE.md:89` and `DEPLOY.md:58-62`: after the
   first ship, **prod is the source of truth**. Every edit the school made since launch lives
   only there. Importing your dev dump wipes it, and there is no undo except a restore.
8. **Rolling back the database.** `DEPLOY.md:68` says do **not**. Code rolls back; data does not.
9. **Forgetting to bump `SJ_ASSET_VER`.** Nothing errors. The site just serves the old CSS to
   everyone who has visited before, and you spend an hour debugging a change that shipped fine.

---

## 8. Try it yourself

Everything below is **read-only** — nothing writes a file, deliberately. (This repo forbids
creating files via shell redirection; see `CLAUDE.md`. To keep a list, copy it out of your
terminal.)

**1. Build the exact upload set and see what is excluded.** Read the exclusion list against §4.3:
every line should be something you can give a reason for — `database/`, the docker files, the
`*.md` plans, `Admin Panel UI.html`. A file you cannot explain is either a mistake or a gap in
the docs.

```bash
git ls-files | wc -l                    # 688 tracked files in total
git ls-files | grep -cE '^(public_html|src|views|vendor)/|^config/config\.sample\.php$'
# 639 — this is the upload set from DEPLOY.md:41
git ls-files | grep -vE '^(public_html|src|views|vendor)/|^config/config\.sample\.php$'
# the 49 files that must NEVER ship
git ls-files | grep -E '^config/'
# config/config.sample.php only — the real config/config.php is gitignored (.gitignore:4)
```

**2. Run the health endpoint against dev.** `./run.sh`, put a value in `health_token` in your
local `config/config.php`, then `curl` it. You get the JSON from `DEPLOY.md:74-84`. Two checks
differ from production on purpose: `https` is `false` on the plain-HTTP dev box, and `backup_age`
reports `none found` until you have run `database/backup.sh` — neither gates (`health.php:63-64`).
A wrong or missing token gives an empty **404**: that is the endpoint hiding, not a bug.

**3. Prove the deny rules work before relying on them in production** — these exercise
`public_html/.htaccess:5-14`, the net that catches a file which slipped into the upload set. Then
read the permission bits you are about to ship and compare against `DEPLOY.md:31-32` (directories
`755`, PHP files `644`, `media/` `755` in prod). Your local `media/` reads `777` — that is
`run.sh:29` doing the dev-only thing it documents in the comment right above it.

```bash
curl -s "http://localhost:8090/admin/health.php?token=<your-token>"
curl -I http://localhost:8090/.git/HEAD       # expect 404 (.htaccess:9, dotfile block)
curl -I http://localhost:8090/media/          # expect 403 (Options -Indexes, .htaccess:5)
curl -I http://localhost:8090/admin/login.php # inspect the security headers
ls -ld public_html public_html/media && ls -l public_html/index.php
```

---

## 9. Where to read more

**In this repo:**

- [`../../../DEPLOY.md`](../../../DEPLOY.md) — the runbook itself and the source for most of this
  document. Read §0 (setup), §1 (every release), §2 (never upload), §4 (rollback) before your
  first deploy.
- [`../../../CLAUDE.md`](../../../CLAUDE.md) — the agent rules; "Deploy cautions" (lines 85-89) is the
  four-line summary of everything above.
- [`../../../SECURITY.md`](../../../SECURITY.md) — SEC-22 covers shared-hosting specifics (permissions,
  the prod-config plan); SEC-20 explains why there is no download-a-backup endpoint.
- [`../04-stage-c-and-d.md`](../04-stage-c-and-d.md) — the Phase X1 section explains why
  `admin/health.php` and `DEPLOY.md` exist, in the same beginner register as this document.
- [`../08-stage-h.md`](../08-stage-h.md) — Parts 2, 4 and 5: Search Console and off-page SEO,
  backups (X2) with the performed restore drill, and monitoring (X3).

**Outside:**

- Apache, *"Apache HTTP Server Tutorial: .htaccess files"* —
  <https://httpd.apache.org/docs/current/howto/htaccess.html>
- Let's Encrypt, *"How It Works"* — what a TLS certificate actually proves:
  <https://letsencrypt.org/how-it-works/>
- MDN, *"Mixed content"* — the HTTPS gotcha from §7:
  <https://developer.mozilla.org/en-US/docs/Web/Security/Mixed_content>
