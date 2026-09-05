# DEPLOY.md — Shipping to MilesWeb (mPanel)

> Production is **MilesWeb shared hosting (mPanel + SSH)** — no Docker, no Composer on
> the server. Deploy = upload files (File Manager, FTP, or `rsync`/`scp` over SSH).
> `vendor/` is committed so the server never runs Composer. This runbook is the whole
> procedure. (PHASES.md X1.)

## 0. One-time production setup

1. **PHP version:** in mPanel, select **PHP 8.3**. Confirm extensions **gd**, **pdo_mysql**,
   **exif** are enabled.
2. **Database:** create the MySQL database + user in mPanel. Import `database/schema.sql`
   via mPanel's DB tool (phpMyAdmin). This creates every table.
3. **Secrets — `config/config.php` ABOVE the webroot:** the account home looks like
   `~/ (home) → public_html/ (webroot)`. Create `~/config/config.php` (a **sibling** of
   `public_html`, NOT inside it) by copying `config/config.sample.php` and filling in the
   real DB credentials. Also set:
   - `'force_secure_cookies' => true` (once SSL is on)
   - `'session_save_path' => '/home/<account>/tmp/sessions'` (create it, `chmod 700`)
   - `'health_token' => '<a long random string>'`
   - `'debug' => false`
   - `'recaptcha_secret' => '<Google reCAPTCHA v2 SECRET key>'` (enables server-side robot checks on the contact form — C1)
   - `'contact' => ['mail_to' => '<school office email>']` (contact-form enquiries are relayed here via `mail()`; they are also always stored in the `contact_submissions` table)
   Also **rotate/delete the old EmailJS public key** in the EmailJS dashboard — it shipped in the old client code (SEC-23) and is dead weight now.
   **reCAPTCHA site key:** the v2 SITE key is hardcoded in `views/partials/contact.php`
   (`data-sitekey`). It must be registered for the live domain in the school's Google
   reCAPTCHA admin console — if you create fresh keys, swap the site key there and put
   the matching SECRET in `config.php`. Until the secret is set, the form still stores
   enquiries (server-side verification just stays off).
   Verify the loader finds it: `dirname(public_html)` must contain `config/config.php`.
   If mPanel does not allow a sibling dir above `public_html`, place it at the highest
   non-web-served level available and confirm `/config/config.php` returns **404** over HTTP.
4. **HTTPS:** enable the free SSL certificate in mPanel and force HTTPS redirect.
5. **Seed content (optional, first ship only):** run the seeder **once** locally against a
   dump, or paste the generated INSERTs — do **not** upload `database/seed.php` to run on
   prod. The production admin password is set by the forced-change flow on first login.
6. **Permissions:** directories `755`, PHP files `644`, `config/config.php` `600`,
   `public_html/media/` `755` (prod PHP runs as the account user — **never 777**).

## 1. Every release

1. **Tag** the commit you're shipping.
2. **Bump the asset version:** edit `SJ_ASSET_VER` in `public_html/bootstrap.php` (e.g. the
   date). This makes browsers fetch the new CSS/JS past the 1-year cache. (Not needed on
   the very first ship — no browser has cached anything yet.)
3. **Build the upload set** — ONLY these paths:
   ```
   public_html/    src/    views/    vendor/    config/config.sample.php
   ```
4. **Upload** via mPanel File Manager (or FTP), extracting over the previous release.
5. **Apply migrations** (if any new `database/migrations/NNN_*.sql`): paste each into the
   mPanel DB tool, in order. Migrations are **additive-only** — never destructive.
6. **Smoke-check:** open `https://<site>/admin/health.php?token=<health_token>` → every
   gating check `true` (`ok: true`). Then click through: home, one page per family, admin
   login + one edit.

## 2. NEVER upload to production

`database/` · `.git/` · `docker-compose.yml` · `Dockerfile` · `run.sh` · `.env` /
`.env.example` · the dev `config/config.php` · `*.md` docs (`PHASES.md`, `SECURITY.md`,
`DEPLOY.md`, `docs/`) · the `Admin Panel UI.html` prototype · any `.sql`/dump/zip/backup.
The root `public_html/.htaccess` already blocks most of these if they slip in, but keep them
out of the upload set entirely.

## 3. Data direction rule

Content flows **dev → prod only for the very first ship**. After that, **production is the
source of truth**. Pull nightly dumps down into dev for testing (X2); never push a dev
database over prod (it would wipe real edits).

## 4. Rollback

Keep the previous release zip in a non-web folder on the host (e.g. `~/releases/`). To roll
back: re-extract the previous zip over `public_html/`. Because migrations are additive-only,
the older code still runs against the newer schema. Do **not** roll back the database.

## 5. Health endpoint reference

`/admin/health.php?token=<health_token>` (or while logged in as admin) returns JSON:

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

`ok: true` (HTTP 200) means safe to serve. Point **UptimeRobot** (X3) at this URL.

## 6. Image renditions (F2)

Renditions for the 478 legacy photos are generated **in Docker only** — never on
the shared host:

```
docker compose exec -T web php /var/www/database/backfill.php
```

Idempotent (existing pairs are skipped). To ship them: upload `public_html/media/`
with the release **and** apply the `image_renditions` rows (export from dev:
`mysqldump --no-tablespaces stjosephs image_renditions | gzip`). If a rendition
regenerates (recrop/backfill re-run), the image's `version` bumps and the URLs
change — browsers refetch automatically. A legacy photo with no rendition simply
serves its original from `/photos/` — nothing breaks.

## 7. Backups (X2)

Copy the one permitted file from `database/` up to the host first —
`database/backup.sh` → `~/database/backup.sh` (**above** the webroot; the rest of
`database/` stays off prod per §2). Then mPanel → **Cron Jobs** → one nightly
entry (e.g. 01:30):

```
/bin/sh /home/<account>/database/backup.sh
```

Nightly gzipped DB dump + weekly (Sunday) `media/` archive into `~/backups/`
(NON-web), 14-day retention. Credentials are read from `config/config.php` by
the script — nothing secret in the crontab. The admin dashboard shows the last
backup's age and size (status only — **no download endpoint**, SEC-20).

**Restore drill** (do this quarterly, in Docker):
```
gunzip -c backups/db-YYYYmmdd-HHMM.sql.gz | docker compose exec -T db \
  sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot drill_restore'
```
(create `drill_restore` first, compare row counts, then drop it — verified
2026-08-09: images 478/478, seo_meta 41/41, image_renditions 988/988.)

## 8. Monitoring (X3)

Two **UptimeRobot** monitors (free tier, 5-min interval):

1. `https://stjosephsondipudur.com/` — keyword monitor, expect `St.Joseph`.
2. `https://stjosephsondipudur.com/admin/health.php?token=<health_token>` —
   HTTP monitor; any non-200 (the endpoint returns 503 when a gating check
   fails) triggers the alert e-mail.

Outage behaviour verified in dev: DB stopped → health returned 503 within one
request; DB restarted → 200. PHP errors land in the host's `error_log` (mPanel
→ Error Log); the admin dashboard's health strip mirrors disk/backup status.

## 9. Admin password recovery (N1)

Two situations, two tools. Both clear any lockout; both are written to
`audit_log`. (A locked account also announces itself now: after 5 failed
tries the login page says "temporarily locked — wait about N minutes".)

**Forgot the password on PRODUCTION (no SSH):**

1. mPanel → **File Manager** → go to the `config/` directory **above**
   `public_html/` (the one that holds `config.php`).
2. Create a file named exactly **`recovery-token.txt`** containing one line:
   a random token of **24+ characters** (mash the keyboard or use a password
   generator — it is used once and thrown away).
3. Open `https://stjosephsondipudur.com/admin/recover.php` (a "Forgot
   password?" link also appears on the login page while the file exists).
4. Enter the admin username, the exact token, and a new password (≥ 12 chars).
5. On success the page deletes the token file itself — **verify in File
   Manager that `recovery-token.txt` is gone** (the page warns in red if it
   could not delete it). The page then 404s again; recovery is disarmed.

Notes: the endpoint is a 404 whenever the file is absent or shorter than
16 chars, so it is zero attack surface in normal operation. Never commit the
token file (gitignored). Full threat notes: SECURITY.md **SEC-24**.

**Forgot the password in DEV (Docker):**

```bash
docker compose exec web php database/reset-admin-password.php admin
```

Prints a one-time temporary password (lockout cleared,
`must_change_password = 1` — the next sign-in forces a proper change).
Never reset the live owner's password for testing.

## 10. Locking the admin panel down (first ship)

The app already layers its own defences: bcrypt password hashes, a 5-strike
timed login lockout, 12 h absolute + idle session caps, Secure/HttpOnly session
cookies, CSRF tokens on every mutation, an `audit_log` of admin actions,
recovery only via a server-side token file (SEC-24, §9), a token-gated health
endpoint, and `robots.txt` disallowing `/admin/`. Production adds two more
steps on day one:

**a) A second gate: HTTP Basic Auth in front of `/admin/`.** Both files live
**only on the server** — deliberately not in the repo, so dev Docker is
unaffected and a release upload (which extracts over `public_html/` without
deleting extra files) leaves them in place. Never sync `public_html/` with a
`--delete` flag — it would remove this gate *and* the `media/` uploads.

1. Generate the hash locally: `openssl passwd -apr1 'THE-GATE-PASSWORD'`.
2. Create `~/.htpasswd-admin` (above the webroot), one line:
   `sjgate:<the $apr1$… hash>`.
3. Create `~/public_html/admin/.htaccess`:

   ```apache
   AuthType Basic
   AuthName "Restricted"
   AuthUserFile /home/<account>/.htpasswd-admin
   Require valid-user

   # UptimeRobot must keep reaching the health endpoint (X3) — it has its
   # own gate (the health_token), so exempt it from Basic Auth:
   <Files "health.php">
     Require all granted
   </Files>

   # Belt and braces: admin responses are never indexed.
   <IfModule mod_headers.c>
     Header set X-Robots-Tag "noindex, nofollow"
   </IfModule>
   ```

Result: anyone hitting `/admin/` gets a browser password box before the
login page even loads — bots and strangers never touch the PHP login at all.
Give the gate password only to people who should *see* the door; each person
still needs their own admin account behind it.

**b) Rotate the imported dev logins IMMEDIATELY after the first DB import.**
The dev dump ships with `admin`/`admin123` (and any second dev user). With the
Basic Auth gate already up (do step **a** first), run:

1. Locally: `docker compose exec -T web php -r "echo password_hash('NEW-STRONG-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"`
2. In the mPanel DB tool:

   ```sql
   UPDATE admin_users
      SET username = '<new-owner-name>', password_hash = '<paste the hash>',
          failed_logins = 0, locked_until = NULL, must_change_password = 0
    WHERE username = 'admin';
   DELETE FROM admin_users WHERE username <> '<new-owner-name>';
   ```

3. Log in once to confirm, then add any additional editors from the admin
   panel's user management (role `editor`, not `owner`).

**c) Optional — IP allowlist.** Only if the office has a *static* IP:
replace `Require valid-user` with `Require ip <that-ip>`. Most Indian
broadband rotates IPs, so this usually locks the owner out too — skip it
unless the IP is genuinely fixed.
