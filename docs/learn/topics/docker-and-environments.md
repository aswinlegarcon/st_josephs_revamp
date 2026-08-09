# Docker and Environments

> **What you'll learn:** why "it works on my machine" is a real engineering problem, what a container actually is, and then — line by line — what our `Dockerfile`, `docker-compose.yml`, `.env` and `run.sh` do. Plus where our configuration comes from, and an honest list of ways our dev box differs from the real server.
>
> **Prerequisites:** you can read basic PHP (`echo`, `require`, arrays) and open a terminal. **No Docker knowledge assumed** — every term is defined the first time it appears.
>
> **Where it lives in our code:** `Dockerfile`, `docker-compose.yml`, `run.sh`, `.env.example` at the repo root; the config loader at `src/Core/Config.php:15-38`; `config/config.sample.php`; deploy rules in `DEPLOY.md`.

---

## 1. The one-paragraph version

Our site needs PHP 8.3 with specific extensions, MySQL 8, Apache, and one very particular Apache setting. Making every developer install all that by hand is slow and produces slightly different machines. So we ship a **recipe** — the `Dockerfile` — that builds a small, disposable, pre-configured mini-computer with exactly that software inside. A second file, `docker-compose.yml`, says "run two of those, one for PHP and one for MySQL, and wire them together". A shell script, `run.sh`, does it in one command. Your source code is **not** copied inside; it is *mounted* from your real folder, so editing a file changes the running site instantly. Production is different — rented shared hosting with no Docker at all — so this doc ends by being honest about where the two diverge.

---

## 2. The problem this solves

### 2.1 "It works on my machine"

Three people on one project. You install PHP 8.1 from your Linux package manager. A teammate installs PHP 8.4 with Homebrew on a Mac. The server runs PHP 8.3. Your code uses a function whose behaviour changed in 8.2: it works for you, breaks for your teammate, and does a third thing on the server. Nobody wrote a bug — the *machines* differ. Now multiply by MySQL version, image libraries, Apache settings and `php.ini` values.

That is "it works on my machine": a fault that lives in the **environment**, not the code. It is expensive because you cannot reproduce it, and you cannot fix what you cannot reproduce. Our `composer.json` does record a requirement (`"php": ">=8.1"` plus `ext-pdo` and `ext-gd`), but a `composer.json` only *checks* — it cannot *install* PHP for you.

### 2.2 What a container is

> A **container** is a single program, plus the exact files and libraries it needs, running in its own sealed-off box on your computer.

The analogy: your laptop is an office building.

- A **virtual machine (VM)** is building a *whole second building* in the car park — own foundations, plumbing, electrics. That is a complete second operating system booting its own kernel: gigabytes of disk, a minute to start, RAM reserved up front.
- A **container** is a *locked room inside the existing building*. Own furniture (files), own door number (network address), own name plate (hostname) — but it uses the building's plumbing and electrics, i.e. your computer's kernel. Hence tens of megabytes and about a second to start.

Both give isolation; the container gets it far more cheaply because it does not duplicate the OS underneath. For us: MySQL 8 runs on your laptop **without being installed on your laptop**. Uninstalling is deleting the container — nothing is left behind in `/usr/bin` or your system services.

### 2.3 Our project's rule

`CLAUDE.md` states it flatly: there is **no PHP/MySQL on the host** — always work through Docker. That is why every PHP command in our docs is written `docker compose exec ... php ...`. If you type `php -v` in your own terminal and get "command not found", nothing is broken. That is the design.

---

## 3. How it works in general

Five words do almost all the work.

| Term | Plain definition | In our project |
|---|---|---|
| **Image** | A frozen, read-only template: a filesystem snapshot plus the command to run. Like a class definition. | `php:8.3-apache` and `mysql:8.0`, both from Docker Hub (the public image library) |
| **Container** | A *running instance* of an image — like an object made from a class. Delete it and anything written inside that was not saved elsewhere is discarded. | `stjosephs_website-web-1` and `stjosephs_website-db-1` |
| **Volume** | Storage that lives **outside** the container so it survives deletion. A *named volume* is managed by Docker; a *bind mount* projects a real folder from your disk into the container. | Named volume `stjosephs_website_dbdata` holds MySQL's data; bind mounts project our source in |
| **Port mapping** | A container has its own private network. A mapping punches a hole: traffic on *this* port of my laptop goes to *that* port inside. | `127.0.0.1:8090` on your laptop → port `80` in the web container |
| **Network** | A private virtual LAN Compose creates so containers reach each other **by service name**, with nothing exposed outside. | `stjosephs_website_default`; the web container finds MySQL at the hostname `db` |

**Docker Compose** (`docker compose`) is the tool that reads one YAML file describing several containers and manages them as a group. A **service** is one named container definition in that file.

---

## 4. How we use it — every place in this codebase

### 4.1 The `Dockerfile` — our recipe for the web container

A `Dockerfile` builds an **image**; each line is one step. Ours is 31 lines in four blocks.

**Block 1 — the starting point.** `FROM php:8.3-apache` (`Dockerfile:1`) picks the base image we build on: an official image with Debian Linux, PHP 8.3 and Apache already wired together. We start from that and add what is missing.

**Block 2 — PHP extensions** (`Dockerfile:3-8`):

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
        libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql exif \
    && docker-php-ext-enable opcache \
    && rm -rf /var/lib/apt/lists/*
```

`RUN` executes a command while the image is being built. The `lib*-dev` packages are the C libraries for JPEG, PNG, WebP and fonts; PHP's image extension needs them present *before* it compiles. `docker-php-ext-configure gd` turns those formats on — without the flags, GD compiles but silently cannot open a JPEG. Then three extensions are installed:

| Extension | Why we need it | Proof in our code |
|---|---|---|
| `gd` | Resizes and re-encodes uploaded photos into small renditions | `src/Media/Pipeline.php:168-170` calls `imagecreatefromjpeg` / `imagecreatefrompng` / `imagecreatefromwebp` |
| `pdo_mysql` | The database driver. PDO is PHP's database interface; `pdo_mysql` is the MySQL-speaking half. Without it every page dies at connect time | the whole CMS — see [`pdo-and-sql.md`](pdo-and-sql.md) |
| `exif` | Reads the camera orientation tag so sideways photos are rotated upright | `src/Media/Pipeline.php:274-277` — `if (!\function_exists('exif_read_data'))`, then `@\exif_read_data($path)` |

`-j"$(nproc)"` compiles using all CPU cores. `docker-php-ext-enable opcache` switches on PHP's bytecode cache (each file is compiled once and reused instead of re-parsed per request). `rm -rf /var/lib/apt/lists/*` deletes the package index, which is dead weight at runtime. `DEPLOY.md:9-11` requires the same three extensions in mPanel on production — the Dockerfile is a machine-readable copy of that requirement.

**Block 3 — the landmine this defuses** (`Dockerfile:10-14`):

```dockerfile
# Make .htaccess actually apply in dev (Debian ships AllowOverride None and the
# header/expires modules disabled). Prod hosts honour .htaccess already; this
# just brings the dev container in line so S4/F1 rules can be tested locally.
RUN a2enmod headers expires rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
```

An **`.htaccess`** is a per-directory Apache config file. Ours holds a lot of our security and speed, e.g. `Header always set X-Content-Type-Options "nosniff"` (`public_html/.htaccess:18`). Two problems in the stock image:

1. **`AllowOverride None`.** Apache reads `.htaccess` only if the main config permits it. Debian's default means *ignore every `.htaccess` completely* — not warn, not error, ignore.
2. **Modules disabled.** `Header` comes from `mod_headers`; `ExpiresByType` (our caching rules at `public_html/.htaccess:35-46`) comes from `mod_expires`. Neither is on by default. `a2enmod` ("Apache2 enable module") turns them on.

Now the landmine. Every block in our `.htaccess` is wrapped in `<IfModule mod_headers.c>` … `</IfModule>`, deliberately — `public_html/.htaccess:1-3` says it is "so a host lacking a module degrades safely rather than 500-ing". But that also means a missing module produces **silence**:

> Before this Dockerfile line, every security header and every caching rule was **silently inert in development**. The page loaded fine. Nothing logged an error. You could "test" a security header, watch the page render, and conclude it worked — while Apache had never opened the file.

Verify the fix is live:

```
$ docker compose exec web grep -n "AllowOverride" /etc/apache2/apache2.conf
161:	AllowOverride All
$ docker compose exec web apache2ctl -M | grep -E "headers|expires"
 expires_module (shared)
 headers_module (shared)
```

(`mod_deflate`, used by our gzip rules at `public_html/.htaccess:27-31`, is already on in the base image — which is why it is not in the `a2enmod` list.) `rewrite` is enabled in the same line for URL-rewriting.

**Block 4 — Composer and PHP settings.** `COPY --from=composer:2 /usr/bin/composer /usr/bin/composer` (`Dockerfile:18`) is a *multi-stage copy*: reach into another published image and take one file. **Composer** is PHP's dependency manager; we mainly use it to generate the **autoloader** (the file that finds a class like `SJ\Core\Config` on demand). `Dockerfile:16-17` records why it is dev-only: `vendor/` is committed to git, so production never runs Composer.

`Dockerfile:20-30` then writes `/usr/local/etc/php/conf.d/stjosephs.ini`:

```ini
upload_max_filesize=12M     post_max_size=13M      memory_limit=512M
display_errors=Off          log_errors=On          opcache.enable=1
opcache.enable_cli=0        opcache.validate_timestamps=1
opcache.revalidate_freq=0
```

The upload sizes must exceed the largest photo an editor uploads; `memory_limit=512M` gives GD room to decode a big image; `display_errors=Off` matches production so you never learn to read errors off the page. The last two lines matter daily: `validate_timestamps=1` with `revalidate_freq=0` means "check the file's modification time every request", which is what makes your edits appear instantly despite the bytecode cache. Production usually turns that *off* for speed (§5).

### 4.2 `docker-compose.yml` — the two services

**The `db` service** (`docker-compose.yml:2-19`):

```yaml
  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASS}
    volumes:
      - dbdata:/var/lib/mysql
      - ./database/schema.sql:/docker-entrypoint-initdb.d/01-schema.sql:ro
    ports:
      - "127.0.0.1:3307:3306"
    healthcheck:
      test: ["CMD-SHELL", "mysqladmin ping -h127.0.0.1 -uroot -p${MYSQL_ROOT_PASSWORD} --silent"]
```

- `image:` — nothing to build; pull the official MySQL 8.0 image as-is.
- `environment:` — MySQL reads these on first start to create the database and user. `${DB_NAME}` is filled in from `.env` (§4.3).
- `dbdata:/var/lib/mysql` — a **named volume** over MySQL's data directory. This is what makes your content survive `./run.sh stop`. Declared at `docker-compose.yml:43-44`; visible on your machine as `stjosephs_website_dbdata`.
- The `schema.sql` line is a **bind mount of one file**, read-only (`:ro`). Anything in `/docker-entrypoint-initdb.d/` runs **only when the data directory is empty** — the very first start only. See gotcha #5.
- `ports: "127.0.0.1:3307:3306"` — MySQL listens on `3306` inside; we expose `3307` on the host so it cannot clash with a locally installed MySQL. The `127.0.0.1:` prefix binds it to *your machine only*, not the office network.
- A **healthcheck** is a command Docker runs periodically to decide whether a container is merely *running* or actually *ready*. MySQL takes seconds to initialise, and a container that is "up" is not yet a database you can query.

**The `web` service** (`docker-compose.yml:21-41`):

```yaml
  web:
    build: .
    depends_on:
      db:
        condition: service_healthy
    environment:
      DB_HOST: db
      DB_NAME: ${DB_NAME}
      DB_USER: ${DB_USER}
      DB_PASS: ${DB_PASS}
    volumes:
      - ./public_html:/var/www/html
      - ./database:/var/www/database
      - ./config:/var/www/config
      - ./src:/var/www/src
      - ./views:/var/www/views
      - ./vendor:/var/www/vendor
      - ./composer.json:/var/www/composer.json
      - ./backups:/var/www/backups
    ports:
      - "127.0.0.1:8090:80"
```

`build: .` means build from the `Dockerfile` here rather than pulling a ready image. `depends_on … condition: service_healthy` means do not start until the healthcheck passes — which is why you never see "connection refused" on a cold start. `DB_HOST: db` is **the network in action**: `db` is not a hostname you configured anywhere, it is the *service name*, and Compose's private network resolves it to the database container.

Then eight **bind mounts**. A bind mount means *this folder on your real disk appears at that path inside the container; there is only one copy of the file*. Save in your editor, refresh the browser, see the change — no rebuild, no copy step. This is the single most important thing to understand about our setup.

| Host folder | Inside container | What it is |
|---|---|---|
| `./public_html` | `/var/www/html` | The **webroot** — the only folder Apache serves |
| `./database` | `/var/www/database` | `schema.sql`, `seed.php`, migrations, `backup.sh` |
| `./config` | `/var/www/config` | Configuration — a **sibling** of the webroot, not inside it |
| `./src` | `/var/www/src` | Our PHP classes (the `SJ\` namespace) |
| `./views` | `/var/www/views` | Templates; found via `\dirname(SJ_PUBLIC_ROOT) . '/views'` (`src/View/Layout.php:37`) |
| `./vendor` | `/var/www/vendor` | The Composer autoloader, loaded at `public_html/bootstrap.php:21` |
| `./composer.json` | `/var/www/composer.json` | So `composer dump-autoload` can run inside |
| `./backups` | `/var/www/backups` | Where `database/backup.sh` writes dumps — deliberately **outside** the webroot |

That layout is not arbitrary. `public_html/bootstrap.php:7-9` sets `SJ_PUBLIC_ROOT` to `/var/www/html`, so `dirname()` of it is `/var/www` — where `config/`, `src/`, `views/`, `vendor/` and `backups/` all sit, *next to* the webroot rather than inside it. The container mirrors the production account layout in `DEPLOY.md:12-14`.

`ports: "127.0.0.1:8090:80"` — **host 8090 → container 80**, which `docker compose ps` confirms live (§8, step 2).

### 4.3 `.env` and `.env.example`

An **environment variable** is a named value handed to a process by whatever started it — the standard way to pass configuration, especially secrets, without writing it into a committed file. Compose automatically reads a file called `.env` in the same directory and substitutes the `${NAME}` placeholders in the YAML. Our committed template (`.env.example:10-13`):

```
MYSQL_ROOT_PASSWORD=rootpw
DB_NAME=stjosephs
DB_USER=stjosephs
DB_PASS=stjosephs_pw
```

Its own header is explicit (`.env.example:3-5`): "THROWAWAY dev credentials for a MySQL container bound to 127.0.0.1 — **NOT** production secrets." The real `.env` is gitignored:

```
.env
.env.*
!.env.example
```

Read those three lines carefully: ignore `.env`, ignore anything starting `.env.`, but `!` **re-includes** `.env.example`. So the *template* is shared and the *actual* file — which on someone's machine might hold a real password — never reaches the repository. That is the whole convention: commit the shape, never the values.

### 4.4 `run.sh` — one command

55 lines of shell. It opens with `set -euo pipefail` (`run.sh:6`) — stop at the first error, treat undefined variables as errors — and `cd "$(dirname "$0")"` (`run.sh:7`) so it works from anywhere.

| Command | What happens |
|---|---|
| `./run.sh stop` | `docker compose down` — stops and removes the containers. The `dbdata` volume is **untouched**, so content survives (`run.sh:10-13`) |
| `./run.sh reset` | `docker compose down -v` — the `-v` also **deletes the volume**; prints "Database volume removed. Next ./run.sh starts fresh." (`run.sh:14-18`) |
| `./run.sh` | The full startup below (`run.sh:9` defaults to `up`) |

**1. Create the two gitignored local files from their committed samples** (`run.sh:22-23`):

```bash
[ -f .env ] || { cp .env.example .env; echo "==> Created .env from .env.example (dev credentials)."; }
[ -f config/config.php ] || { mkdir -p config; cp config/config.sample.php config/config.php; echo "==> Created config/config.php from sample."; }
```

`[ -f X ] || { … }` means "if X does not exist, do this" — idempotent, so a second run skips both. This is why a fresh clone needs no manual setup.

**2. Make the upload folder writable** (`run.sh:25-29`): `mkdir -p public_html/media` then `chmod 777 public_html/media`. `777` means anyone may read, write and execute — normally bad practice, and the comment above it says so. The Apache container runs PHP as `www-data` (uid 33), which is not you, so it needs write access to save uploads. In production PHP runs as the account's own user, so `DEPLOY.md:31-32` requires `755` there — **never** `777`.

**3. Build and start** (`run.sh:32`): `docker compose up -d --build`. `-d` = detached (background); `--build` = rebuild the web image if the `Dockerfile` changed.

**4. Wait for the database** (`run.sh:34-38`):

```bash
for i in $(seq 1 90); do
  if docker compose exec -T db mysqladmin ping -h127.0.0.1 -uroot -prootpw --silent >/dev/null 2>&1; then break; fi
  sleep 1
done
```

Up to 90 attempts, one second apart, of "are you answering yet?"; `break` exits the moment MySQL replies. Belt-and-braces on top of the compose healthcheck. Note `-prootpw` is written literally — gotcha #6.

**5. Refresh the autoloader, then seed** (`run.sh:41,44`):

```bash
docker compose exec -T web composer dump-autoload -o --no-interaction 2>/dev/null || true
docker compose exec -T web php /var/www/database/seed.php
```

`docker compose exec <service> <command>` runs a command **inside** a running container; `-T` disables terminal allocation, needed in scripts. Seeding loads the site's content into the database and is **idempotent** — running it twice changes nothing. See [`migrations-and-seeding.md`](migrations-and-seeding.md).

**6. Print the URLs** (`run.sh:48-51`): `http://localhost:8090/` and `http://localhost:8090/admin/` with `user: admin · password: admin123`. That password is a throwaway; the first login forces a change.

### 4.5 Configuration precedence — `src/Core/Config.php`

Three sources, fixed order (`src/Core/Config.php:21-35`):

```php
$root   = \dirname(SJ_PUBLIC_ROOT);            // repo root (parent of public_html)
$secret = $root . '/config/config.php';
$sample = $root . '/config/config.sample.php';

$cfg = \is_file($secret) ? require $secret
     : (\is_file($sample) ? require $sample
     : [ /* hard-coded last-resort defaults */ ]);

foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'name' => 'DB_NAME', 'user' => 'DB_USER', 'pass' => 'DB_PASS'] as $key => $env) {
    $val = \getenv($env);
    if ($val !== false && $val !== '') {
        $cfg['db'][$key] = $val;
    }
}
```

Read the order off the code: a file is loaded first (real config, else sample, else built-in defaults), and then the environment variables **overwrite whatever the file said**. Effective precedence:

> **environment variables → `config/config.php` → `config/config.sample.php`**

In Docker that is why the site connects at all: the web service is given `DB_HOST: db` (`docker-compose.yml:27`), which beats the `'host' => '127.0.0.1'` in `config/config.sample.php:19`. Inside a container `127.0.0.1` means *this container*, where no MySQL runs. `self::$cfg` is a static cache (`src/Core/Config.php:13,17-19`), so the file is read at most once per request.

**Why the config file lives *above* the webroot.** `config/config.php` sits at `/var/www/config/config.php` — a sibling of `/var/www/html`, not inside it. Apache serves **only** `/var/www/html`. A path Apache cannot see cannot be requested; there is no URL that reaches it.

Suppose someone moved it to `public_html/config/config.php`. The normal case looks safe: request it, PHP executes it, and since the file only `return`s an array the browser gets an empty response. The danger is every *abnormal* case:

- **PHP stops handling `.php` for a moment** — a bad deploy, a panel change, a PHP-version switch that drops the handler. Apache falls back to serving the file as plain text and the database password is on screen.
- **A leftover copy.** Editors and panels create `config.php.bak`, `config.php~`, `config.php.save`. Those are not PHP, so they are *always* served as text. Our root `.htaccess` denies `.bak` and `.old` (`public_html/.htaccess:12-14`) — a tilde-suffixed file is not on that list.
- **`.htaccess` not honoured.** As §4.1 showed, one `AllowOverride None` makes every protective rule vanish silently.

Each of those is a *configuration accident*. Keeping the file outside the webroot means no configuration accident can expose it, because the file is not in the served tree at all. `DEPLOY.md:12-14` makes this a required setup step; `SECURITY.md` records it as SEC-22.

---

## 5. Why this is the right approach here

| Option | What it means | Why not |
|---|---|---|
| **XAMPP / WAMP** | A bundled Apache+PHP+MySQL installer | One global PHP and MySQL for every project; version is whatever the bundle ships. Configuring `AllowOverride`, `mod_expires` and three extensions becomes a manual click-hunt done differently by each person — exactly the drift we are avoiding |
| **Install PHP + MySQL natively** | `apt install php mysql-server` | Same drift, plus this is a **corporate, EDR-monitored laptop** (see the CrowdStrike incident recorded in `CLAUDE.md`). Adding system-wide services and daemons to a managed work machine is not a casual act, and IT may simply block it |
| **A full virtual machine** | VirtualBox/VMware running a whole Linux guest | Perfect isolation, but gigabytes of disk, minutes of boot, reserved RAM — and sharing your source folder in so live editing works is fiddlier than a bind mount. Heavy tool for a two-service problem |
| **A shared remote dev server** | Everyone SSHes into one box | One person's broken migration breaks everyone; needs network access to work at all; needs an administrator; and it is a *third* environment to keep in sync |
| **Docker Compose (ours)** | Two containers, one command | Fits all three constraints below |

1. **A corporate laptop where installing services is unwelcome.** Docker is one approved tool; everything else lives inside it and leaves no system services behind.
2. **A team that must onboard in one command.** From a clean clone: `./run.sh` — which even creates `.env` and `config/config.php` for you (`run.sh:22-23`). No setup wiki page to go stale.
3. **A production host we cannot replicate exactly.** We can never run MilesWeb's shared hosting locally, so the goal is not a perfect copy — it is **matching the things that change behaviour**: PHP 8.3, the same three extensions, `.htaccess` actually honoured, config above the webroot. The Dockerfile encodes exactly the checklist in `DEPLOY.md:9-11`.

### Dev vs prod parity — the honest list

| | Docker (dev) | MilesWeb (production) |
|---|---|---|
| Web server | Apache 2.4 | Shared hosting via mPanel; `SECURITY.md` calls it "Apache/LiteSpeed". LiteSpeed reads `.htaccess` but is not byte-for-byte Apache |
| Shell | `docker compose exec` gives a root shell | **No SSH at all.** `DEPLOY.md:3-5`: "no SSH, no Docker, no Composer on the server. Deploy = upload files" |
| Composer | Installed (`Dockerfile:18`) | Unavailable — precisely why `vendor/` is committed to git (`.gitignore` says so explicitly; `public_html/bootstrap.php:19-21` relies on it) |
| HTTP version | HTTP/1.1, plain HTTP on `localhost:8090` | HTTP/2 over TLS. HTTP/2 multiplexes requests, so our 47-request home page is far cheaper there than local numbers suggest — `PHASES.md` F4 says exactly that |
| Compression | gzip via `mod_deflate` (`public_html/.htaccess:27-31`) | May offer **Brotli**, a newer algorithm that compresses text better; `PHASES.md` F4 lists Cloudflare + Brotli as a deploy-day option. Our `<IfModule mod_deflate.c>` block simply does nothing if the host uses something else |
| Timezone | **UTC** — `docker compose exec web date` → `Sun Aug 9 04:14:22 UTC 2026` | The school is in **IST (UTC+5:30)**; the host clock reads `09:44` at that same instant |
| File ownership | Apache runs as `www-data` (uid 33); `media/` is `777` (`run.sh:29`) | PHP runs as the account's own user, so `media/` must be `755` — `DEPLOY.md:31-32`, SEC-22 |
| OPcache | `validate_timestamps=1`, `revalidate_freq=0` (`Dockerfile:28-29`) — edits appear instantly | mPanel-managed; typically longer revalidation, so **a freshly uploaded file may keep serving its old bytecode for a while**. `public_html/admin/health.php:41` checks `opcache.enable` is on |
| HTTPS | No | Required; the health endpoint reports `https` as `false` on the dev box by design (`DEPLOY.md:81`) |

None of this is hidden. It is why `DEPLOY.md:46-48` has a smoke-check step instead of "it worked locally, ship it".

---

## 6. How this scales

**More developers.** Compose needs no changes — it is the same two files. What starts to hurt around ten people is **build time**: everyone compiles the web image locally, and `Dockerfile:3-8` takes minutes. The fix is a **container registry** (a server that stores built images): build once in CI, push, and change `build: .` to `image: registry.example/stjosephs-web:<tag>`. Everyone then *downloads* the image instead of compiling it.

**CI (continuous integration).** A CI service can run this same compose file on every push — start the stack, seed, hit a few URLs, check the health endpoint. Because the environment is a file in the repo, CI and your laptop are genuinely the same machine. Biggest single win available, and it needs no new infrastructure decisions.

**More services.** Compose handles a handful comfortably. Redis for sessions (see [`sessions-and-cookies.md`](sessions-and-cookies.md)) or a search service would each be another block in the same file. Past roughly five or six interdependent services with real uptime requirements, orchestrators like Kubernetes begin to earn their complexity — a 42-page school site never gets there.

**A staging environment that mirrors prod.** This would pay off soonest, and Docker cannot provide it. Every row in the parity table is a difference only a second MilesWeb-style account can eliminate: LiteSpeed, HTTP/2, production OPcache, real file ownership. Deploy there first, smoke-check, then promote.

**The exact next step:** publish the built web image from CI to a registry and pin it by tag in `docker-compose.yml`. It removes per-developer build time, makes "which image were you running?" answerable, and is the prerequisite for both CI runs and a staging deploy. Nothing else in this document has to change.

---

## 7. Gotchas and mistakes to avoid

**1. Editing files inside the container that are not on a mount.** Only the eight paths at `docker-compose.yml:31-39` are shared with your disk. Write to `/etc/apache2/…`, `/tmp/…` or `/var/www/docs/…` and it lives only in that container — gone the next time it is recreated. If a change must survive, it belongs in the `Dockerfile` or in a mounted folder.

**2. Root-owned files created by container processes.** `docker compose exec` runs as **root** inside the container (`docker compose exec web whoami` → `root`), so anything it writes through a bind mount lands on your real disk owned by `root`. Not hypothetical — true in this repo right now:

```
$ ls -ld vendor
drwxr-xr-x 1 root        root        40 Aug  2 01:24 vendor
$ ls -ld src
drwxrwxr-x 1 aswin-25449 aswin-25449 72 Aug  9 04:59 src
```

`vendor/` is root-owned because `run.sh:41` runs `composer dump-autoload` as root through the mount. The recorded incident was the same mechanism hitting `database/seed-data/`: a generator running in the container created the directory as root, and the next attempt to edit those files from the editor failed with a permission error. The fingerprint is still visible — those generated files are `644` inside a `755` directory, while hand-authored siblings in `database/` are `664` inside `775`. Symptom to recognise: "permission denied" on a file whose folder you certainly own. Fix on the host: `sudo chown -R "$USER:$USER" <path>` — and watch `git status` for stray mode changes.

**3. Port 8090 already in use.** `docker compose up` fails with `bind: address already in use`. Change only the **host** side — `"127.0.0.1:8091:80"` — never the container side, because `80` is where Apache listens. Note the URLs printed at `run.sh:50-51` are hard-coded to `8090` and will then be wrong.

**4. `./run.sh reset` deletes the database.** `docker compose down -v` (`run.sh:15`) removes the `stjosephs_website_dbdata` volume. Every edit made through the admin panel since the last seed is gone, with no confirmation prompt. `./run.sh stop` is the safe one. If you have content worth keeping, take a dump first (`DEPLOY.md:117-121` shows the format).

**5. Editing `schema.sql` and expecting it to apply.** The mount at `docker-compose.yml:11` puts it in `/docker-entrypoint-initdb.d/`, which the MySQL image runs **only when its data directory is empty** — the first ever start. Change `schema.sql` on an existing volume and *nothing happens*. Either `./run.sh reset` (destructive) or write an additive migration in `database/migrations/`, which is what `DEPLOY.md:44-45` requires for production anyway.

**6. Changing `MYSQL_ROOT_PASSWORD` in `.env`.** The wait loop at `run.sh:36` has `-prootpw` written literally, matching `.env.example:10`. Change the password and that ping can never succeed — the loop silently burns all 90 seconds, then carries on to seeding regardless. You get a slow start and a confusing seeder error, with nothing pointing at the password.

**7. Assuming behaviour that only exists because of our dev Apache setup.** The inverse of the §4.1 landmine. Locally `mod_headers`, `mod_expires`, `mod_deflate` and `AllowOverride All` are guaranteed because `Dockerfile:13-14` guarantees them. Production is LiteSpeed, and `SECURITY.md` still flags `AllowOverride` there as "standard on MilesWeb Apache/LiteSpeed, **confirm**". Because every block is wrapped in `<IfModule>`, a missing module is silent in *both* directions. Never conclude a header ships because it works locally — check the deployed site's real response headers with `curl -I`.

**8. UTC vs IST.** The container is UTC; you are 5½ hours ahead. Already documented at [`sessions-and-cookies.md:489-500`](sessions-and-cookies.md), and worth repeating: the *arithmetic* is fine, because `time()` returns a Unix timestamp which has no timezone. What misleads you is *reading* — an `ls -la` timestamp or a `date()` string from inside the container looks 5½ hours stale. A log line that appears five hours old is very likely thirty seconds old. Do not "fix" this by adding offsets in application code.

---

## 8. Try it yourself

All commands are read from the real files in this repo; run them from the repo root.

**1. Start from a clean clone** — `./run.sh`. Watch the output: it creates `.env` and `config/config.php` if missing (`run.sh:22-23`), builds, waits for MySQL, refreshes the autoloader, seeds, and prints the URLs. Open <http://localhost:8090/> and <http://localhost:8090/admin/>. The first run takes minutes — it downloads `php:8.3-apache` and `mysql:8.0` and compiles the extensions.

**2. See what is running** — `docker compose ps`. Expect two containers and these exact port columns:

```
NAME                      IMAGE                   SERVICE   PORTS
stjosephs_website-db-1    mysql:8.0               db        33060/tcp, 127.0.0.1:3307->3306/tcp
stjosephs_website-web-1   stjosephs_website-web   web       127.0.0.1:8090->80/tcp
```

**3. Read the web server's log** — `docker compose logs web` (add `-f` to follow it live). PHP errors and Apache request lines appear here. First place to look when a page 500s.

**4. Run a command inside the container** — `docker compose exec web php -v`. Expect `PHP 8.3.33` and a line mentioning `Zend OPcache`: proof that `Dockerfile:1` and `Dockerfile:7` did what they claim. Then try:

```bash
docker compose exec web php -m | tr '\n' ' '        # expect gd, pdo_mysql, exif
docker compose exec web apache2ctl -M | grep -E "headers|expires"
docker compose exec web date                        # UTC
date                                                # your clock, IST
```

**5. Prove the bind mount is live.** Open any file under `views/`, change one word, save, refresh the browser. No rebuild. Change it back.

**6. Stop without losing anything** — `./run.sh stop`, then `./run.sh` again. Your content is still there, because the `dbdata` volume was untouched.

**7. Only when you want a truly fresh database** — `./run.sh reset`. It prints "Database volume removed. Next ./run.sh starts fresh." Read gotcha #4 first.

---

## 9. Where to read more

**In this repo:**

- [`../01-fundamentals.md`](../01-fundamentals.md) — Part J is the short Docker introduction; Part F covers Composer and autoloading, which §4.4 touches.
- [`../03-what-we-built.md`](../03-what-we-built.md) — how the code running inside these containers is organised.
- [`../../../DEPLOY.md`](../../../DEPLOY.md) — the production side of §5: mPanel setup, the exact upload set, and the never-upload list (`DEPLOY.md:51-56` explicitly excludes `Dockerfile`, `docker-compose.yml`, `run.sh` and `.env`).
- [`../../../CLAUDE.md`](../../../CLAUDE.md) — the dev-workflow and file-writing rules, including "there is no PHP/MySQL on the host".
- [`migrations-and-seeding.md`](migrations-and-seeding.md) — what `database/seed.php` does in step 5 of `run.sh`.
- [`security-headers-and-htaccess.md`](security-headers-and-htaccess.md) — the `.htaccess` rules that `Dockerfile:13` makes testable.

**External:**

- [Docker: get started](https://docs.docker.com/get-started/) — the official introduction to images and containers.
- [Compose file reference](https://docs.docker.com/reference/compose-file/) — every key in `docker-compose.yml`, including `depends_on`, `healthcheck` and the volume syntax.
- [Docker: manage data with volumes](https://docs.docker.com/engine/storage/volumes/) — named volumes vs bind mounts, the distinction §3 draws.
- [The official `php` image](https://hub.docker.com/_/php) — documents `docker-php-ext-install`, `docker-php-ext-configure` and the `-apache` variant we build on.
