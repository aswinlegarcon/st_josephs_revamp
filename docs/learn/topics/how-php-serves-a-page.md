# How PHP Serves a Web Page

> **What you'll learn:** the complete journey of one page view — from a visitor typing our address, through DNS, TCP, TLS, HTTP and Apache, into a PHP file in `public_html/`, and back out as HTML — and exactly where each step lives in this repo.
>
> **Prerequisites:** you can read `echo`, variables, `if`/`else`, functions and `include`. Nothing else. For more depth afterwards see [Composer and Autoloading](composer-and-autoloading.md), [Sessions and Cookies](sessions-and-cookies.md) and [Project Architecture — Layers, Controllers and Views](architecture-and-layers.md) (sibling docs).
>
> **Where it lives in our code:**
> - `public_html/index.php` — the home page controller, and the shape every page copies
> - `public_html/bootstrap.php` — the first thing every page runs
> - `src/View/Layout.php` — the layout engine (output buffering happens here)
> - `views/shell.php` — the one HTML document every public page is poured into
> - `public_html/.htaccess` — the web server's instructions for our folder
> - `Dockerfile`, `docker-compose.yml`, `run.sh` — the development web server
> - `DEPLOY.md` — the production web server (a completely different machine)

## 1. The one-paragraph version

A visitor's browser opens a network connection to our server and sends a short text message called an **HTTP request** ("GET me `/about.php`"). Apache — the **web server** program listening on that machine — maps the path `/about.php` to the real file `public_html/about.php`, sees the `.php` ending, and hands it to PHP instead of sending it as-is. PHP reads that file top to bottom, running the code and printing whatever it prints. In our project that file is 22 lines: it loads `bootstrap.php`, fetches rows from MySQL, and calls `\SJ\View\Layout::render()`, which builds the page HTML in memory and prints it. PHP finishes, hands the printed text to Apache, and Apache mails it to the browser as an **HTTP response**. Then **PHP throws everything away** — every variable, the database connection, all of it. The next visitor starts from absolute zero.

That last sentence is the most important idea in this document.

## 2. The problem this solves

Imagine the site were 42 hand-typed `.html` files. That is what it used to be. The principal's welcome appears on both the home page and About — changing one word means editing two files. The navbar appears on all 42; adding a menu item means 42 edits, and you will miss one. Worse, the school office cannot do any of it; every typo becomes a developer ticket.

PHP fixes this by making the page **a program run fresh, per visitor**, instead of a frozen document. Because it is computed at request time it can pull current text from a database (an editor changes it once, every page updates), share one copy of the navbar/footer/skeleton across all 42 URLs, and behave differently for different people — a logged-in admin sees an edit toolbar, a visitor does not, from the same file.

The cost: something must run on *every* request. Section 6 is about what happens when that becomes a lot of requests.

## 3. How it works in general

### 3.1 Client, server, DNS, TCP, TLS

- **Client** — the visitor's browser. It asks for things. **Server** — a computer always on, waiting to answer. Ours is a rented slice of a machine at MilesWeb.
- **DNS** (Domain Name System) is the global phone book translating `stjosephsondipudur.com` into a number (an **IP address**). Browsers cache the answer, which is why domain changes take hours to spread.
- **TCP** is the rule set for opening a reliable two-way pipe to that IP on a **port** (a numbered door — 80 plain, 443 encrypted). "Reliable" means everything arrives, in order, or you get an error. HTTP rides on TCP like a conversation rides on a phone call.
- **TLS** (the "S" in HTTPS) wraps the pipe in encryption and proves we are who we claim, using a **certificate**. `DEPLOY.md:27` makes this a one-time setup step: enable the free SSL certificate in mPanel and force HTTPS.

TLS touches our code in one visible place — the admin cookie is marked `secure` (only ever sent over HTTPS) when the request arrived encrypted, at `src/Admin/Auth.php:32-34`:

```php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || !empty($cfg['force_secure_cookies']);
```

### 3.2 The HTTP request

Once the pipe is open the browser sends plain text:

```http
GET /about.php HTTP/1.1
Host: stjosephsondipudur.com
Cookie: SJADMIN=8f21c…
Accept: text/html
```

| Part | What it is | Example |
|---|---|---|
| **Method** | the verb — what kind of action | `GET`, `POST` |
| **Path** | which resource | `/about.php`, `/admin/api/?r=field` |
| **Headers** | `Name: value` lines of extra info | `Host:`, `Cookie:`, `Content-Type:` |
| **Body** | optional payload, after a blank line | form fields, JSON, an uploaded file |

We use two methods. **GET** means "give me this" and must never change anything — a GET can be bookmarked, prefetched by the browser and crawled by Google, so a GET that deletes a row will eventually delete itself. That is why `CLAUDE.md` forbids mutating GETs. **POST** means "here is some data, do something with it"; it carries a body and is used for logins, forms, uploads and every content edit.

### 3.3 The HTTP response

The server answers in the same shape:

```http
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8
Cache-Control: no-cache

<!DOCTYPE html>
<html lang="en">…
```

A **status code** summarises the outcome in three digits: `2xx` worked, `3xx` go elsewhere, `4xx` you erred, `5xx` we erred. **Headers** describe the content (type, caching, cookies). The **body** is the actual HTML, image bytes or JSON. Codes this codebase really sends:

| Code | Meaning | Where |
|---|---|---|
| `200 OK` | fine | default for every page |
| `301 Moved Permanently` | retired URL, use that one forever | `public_html/gal-sciexpo.php:4` |
| `401 Unauthorized` | not logged in | `public_html/admin/api/_bootstrap.php:23` |
| `403 Forbidden` | logged in, still not allowed | `public_html/admin/editmode.php:14` |
| `404 Not Found` | no such thing | `public_html/admin/health.php:16` (deliberately, to hide the endpoint) |
| `405 Method Not Allowed` | right URL, wrong verb | `public_html/api/contact.php:36-37` |
| `500 Internal Server Error` | our bug | `src/View/Layout.php:33` |
| `503 Service Unavailable` | temporarily broken | `public_html/kg.php:9`, `src/Core/Db.php:67` |

### 3.4 The web server, the document root, and URL → file

**Apache** owns the port, parses the request and decides what to do. Its key setting is the **document root** — the single folder it may serve. Ours is `public_html/`, which is why the repo is split as it is:

```
Stjosephs_Website/
├── public_html/   ← document root: reachable from the internet
├── src/           ← NOT reachable — PHP loads it from disk
├── views/         ← NOT reachable
├── vendor/        ← NOT reachable
├── config/        ← NOT reachable (holds the real DB password)
└── database/      ← NOT reachable
```

Mapping is boringly literal — the URL path is appended to the document root:

| URL | File on disk |
|---|---|
| `/about.php` | `public_html/about.php` |
| `/css/home.css` | `public_html/css/home.css` |
| `/admin/api/?r=field` | `public_html/admin/api/index.php` (directory → its index file) |
| `/` | `public_html/index.php` |
| `/src/Core/Db.php` | **nothing** — `src/` is outside the document root |

That last row is the whole point of the split. `DEPLOY.md:13-26` insists the real `config/config.php` sits *beside* `public_html`, never inside it, so no URL can reach the database password.

`public_html/.htaccess` is a per-folder config file Apache reads to adjust behaviour for our directory. Ours disables directory listings (`:6`), 404s dotfiles like `.git`/`.env` (`:9`), and refuses source-ish extensions (`:12-14`):

```apache
<FilesMatch "\.(sql|bak|old|zip|tar|gz|tgz|log|md|lock|swp|dump|ini|sh)$">
  Require all denied
</FilesMatch>
```

### 3.5 How PHP actually runs — the part everyone gets wrong

Apache sees `.php` and does **not** send the file; it hands it to the PHP engine. The glue layer between web server and PHP is a **SAPI** (Server API); the classic one is **mod_php**, PHP compiled into Apache itself, which is what our `php:8.3-apache` image uses (`Dockerfile:1`). PHP also has a `cli` SAPI for the command line — our seeder runs under that, which is why code checks `PHP_SAPI !== 'cli'` at `public_html/bootstrap.php:29` and `src/Admin/Auth.php:20`.

Then: PHP opens the file at line 1; text outside `<?php … ?>` is printed verbatim and code inside is executed; anything `echo`d or `<?= … ?>`d is appended to an output buffer; when the file ends (or hits `exit`) the buffer goes to Apache; and **PHP destroys the entire process state** — variables, objects, the open MySQL connection, all freed.

The analogy: PHP is not a shop assistant who remembers you. It is a brand-new assistant, hired for your one question, fired the moment they answer. Everything they need must be re-read from a filing cabinet (the database), a note you carry (a cookie), or a locker keyed by that note (a session file).

This is why "store it in a global so the next page can use it" never works, and why all 42 pages begin by loading the same bootstrap: there is nothing left over.

### 3.6 Static files never touch PHP

If the path ends in `.css`, `.js`, `.png`, `.webp` or `.woff2`, Apache reads the bytes off disk and sends them. PHP never starts; there is no database query. That is why `.htaccess:47-51` can tell browsers to cache those for a year:

```apache
<FilesMatch "\.(css|js|jpg|jpeg|png|webp|gif|ico|woff2|svg)$">
  Header set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
```

…while `.php` output is `no-cache` (`.htaccess:53-55`) so an edit shows immediately. A page load that looks like "one request" is really **one PHP request plus dozens of static ones** — `docs/perf-baseline.md:72` measures 47 for our home page.

## 4. How we use it — every place in this codebase

### 4.1 Our URL surface is 42 files

There is no router and no rewrite rule (grep `public_html/.htaccess` for `RewriteRule` — there isn't one). Every URL is a real file:

| Family | Count | Files |
|---|---|---|
| Hub pages | 9 | `index.php`, `about.php`, `staffs.php`, `academics.php`, `achievements.php`, `co-curriculum.php`, `sports.php`, `infrastructure.php`, `gallery.php` |
| Academies | 18 | `tamilacademy.php`, `mathsacademy.php`, … |
| Gallery albums | 10 | `gal-annual.php`, `gal-alumni.php`, … |
| School sections | 4 | `kg.php`, `primary.php`, `highschl.php`, `highsec.php` |
| Pure redirect | 1 | `gal-sciexpo.php` |

41 call `\SJ\View\Layout::render()`; the 42nd redirects. (`bootstrap.php` also lives there, making 43 `.php` files, but it is a library, not a page.) Keeping the filenames is deliberate — the old URLs are printed on notices and indexed by Google, and `CLAUDE.md` requires every legacy URL keeps working.

### 4.2 Line 1 of every page: `require`

`include` and `require` both paste another file in at that point and run it. The difference is failure: `include` warns and carries on, `require` stops everything. For a file the page cannot function without, `require` is correct — you want a loud crash, not a half-rendered page missing a security check.

Every page starts identically — `require __DIR__ . '/bootstrap.php';` (`public_html/about.php:3`). `__DIR__` is a magic constant holding the folder of the *current* file, so the path is right regardless of the requested URL or working directory.

`public_html/bootstrap.php` is 38 lines and does four jobs:

1. **Defines paths and the asset version** (`:7-16`) — `SJ_PUBLIC_ROOT` is how `src/` finds `views/` later; `SJ_ASSET_VER` is the `?v=…` appended to every CSS/JS URL so a deploy busts the one-year cache.
2. **Loads the autoloader** (`:21`) — `require_once dirname(__DIR__) . '/vendor/autoload.php';` Composer's file makes every `SJ\…` class loadable on demand and eagerly loads `src/helpers.php`, which defines the plain function names views call (`e()`, `db()`, `repo_page()`, `img_tag()`, …). See `composer.json:11-18`.
3. **Boots the session lazily** (`:25`) — `sj_session_boot(false);`
4. **Installs the dev query counter** (`:29-38`).

That last one demonstrates "PHP runs and dies". `register_shutdown_function()` registers a callback PHP runs at the very end of the request, whatever happens:

```php
register_shutdown_function(function () {
    foreach (headers_list() as $h) {
        if (stripos($h, 'content-type: application/json') === 0) {
            return; // never corrupt an API response
        }
    }
    echo "\n<!-- sj-queries: " . db_query_count() . " -->";
});
```

View source on any dev page and the last line reports how many SQL queries it took. Budget: 12.

### 4.3 The controller: gather data, then render

Because PHP starts empty, a page's job is to *assemble* everything then hand it over. `public_html/tamilacademy.php` is the whole pattern in 19 lines:

```php
require __DIR__ . '/bootstrap.php';

$sj_academy = repo_academy('tamilacademy');
if (!$sj_academy) {
    http_response_code(503);
    exit('Academy content not seeded.');
}

\SJ\View\Layout::render('academy', [
    'title'       => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'   => 'academics',
    'styles'      => ['academy'],
    'scripts'     => ['academy'],
    'sj_slug'     => 'tamilacademy',
    'sj_academy'  => $sj_academy,
    'sj_carousel' => repo_linked_images('academy', (int)$sj_academy['id'], 'carousel'),
]);
```

All 18 academy pages are this file with a different slug; all 10 albums share `views/pages/album.php`. `public_html/index.php:7-17` is the same shape with more rows, and its comment (`index.php:3-4`) states the rule: **all queries happen in the controller, none inside views.** That is how the ≤12-query budget stays checkable by reading one file.

### 4.4 Output buffering — how the layout engine works

`src/View/Layout.php` is 51 lines; the interesting part is `:45-49`:

```php
\ob_start();
include $views . '/pages/' . $page . '.php';
$content = \ob_get_clean();

include $views . '/shell.php';
```

- `ob_start()` turns **output buffering** on: from now on anything printed is captured into a hidden string instead of going to the browser.
- The `include` runs the view, which prints a lot of HTML — all into the buffer.
- `ob_get_clean()` returns the buffer as a string and turns buffering off.
- The final `include` runs the shell, which prints `<!DOCTYPE html>`, `<head>`, the navbar, then at `views/shell.php:80` prints `<?= $content ?>`, then the footer and scripts.

So the body is rendered **first** and the wrapper **second**, even though the wrapper comes first in the output. Without buffering you would have to print `<head>` before knowing what the page needed in it. This is the only `ob_*` usage in the whole codebase.

Two supporting details: `extract($data, EXTR_SKIP)` at `Layout.php:38` turns the controller's array keys into real variables (`$sj_academy`, `$styles`, …) for the view and shell, and never overwrites an existing one. And `Layout.php:32-35` checks the page name against a hardcoded whitelist (`:17-28`), 500ing otherwise — request data can never pick a file to include.

### 4.5 Superglobals — PHP's copy of the request

PHP hands you the parsed request in **superglobals**: arrays available in every scope without being passed in. Six matter.

| Superglobal | Holds | Set by | Used in our code |
|---|---|---|---|
| `$_GET` | query-string values (`?r=field`) | the URL | `admin/api/index.php:19`, `admin/health.php:13`, `admin/section.php:5` |
| `$_POST` | form fields from a POST body | the request body | `admin/login.php:17-18`, `admin/editmode.php:12` |
| `$_SERVER` | request metadata + server info | Apache/PHP | `views/shell.php:16`, `views/partials/admin-bar.php:4`, `src/Admin/Auth.php:32-33` |
| `$_COOKIE` | cookies the browser sent back | the `Cookie:` header | `src/Admin/Auth.php:24` |
| `$_SESSION` | server-side per-visitor storage | `session_start()` | `src/Admin/Auth.php:49-61`, `admin/login.php:29-36` |
| `$_FILES` | uploaded files (multipart POST) | the request body | `admin/api/upload.php:7,15` |

**`$_SERVER` is how a page knows its own name.** `views/shell.php:16` derives the SEO slug from the script Apache chose to run:

```php
$sjSlug  = \basename($_SERVER['SCRIPT_NAME'] ?? 'index.php', '.php') ?: 'index';
```

`SCRIPT_NAME` is set by the *server*, not typed by the visitor, so it is safe to look up in the database — the comment above it says exactly that.

**`$_SESSION` is a locker, not a bag.** The browser holds only a random ID in a cookie; the data sits in a file on the server. `src/Admin/Auth.php:35-43` configures ours before starting it — `httponly` (JavaScript cannot read the cookie), `samesite=Lax`, `secure` on HTTPS. And `Auth.php:24` is a small, clever optimisation:

```php
if (!$force && empty($_COOKIE['SJADMIN'])) {
    return; // public visitor without an admin cookie: zero session cost
}
```

A normal visitor never starts a session at all. Admin pages force one with `sj_session_boot(true)` (`admin/_layout.php:6`).

**Raw bodies bypass `$_POST`.** `$_POST` is only filled for form-style bodies. Our admin API sends JSON, so `admin/api/_bootstrap.php:44` reads the raw stream instead: `$raw = file_get_contents('php://input');`

### 4.6 Headers must come before the body

Headers travel *before* the body. Once PHP has sent one byte of body the headers are gone, and `header()` after that is too late (PHP warns "headers already sent"). Consequences visible in our code:

- `Layout.php:41-43` guards with `!\headers_sent()` before setting `X-Frame-Options`; `src/Admin/Auth.php:92-93` returns early for the same reason.
- `admin/api/_bootstrap.php:6-8` sets every header at the very top, before any endpoint can echo.
- A stray blank line *after* a closing `?>` counts as output — which is why our PHP-only files end without `?>`. Look at the end of `src/helpers.php` or `public_html/index.php`.

**Redirects must `exit`.** A `Location:` header only *suggests* a new URL; PHP keeps running and would send the old page's HTML too. `public_html/gal-sciexpo.php` is the minimal correct form, all of it:

```php
http_response_code(301);
header('Location: /gal-spach.php');
exit;
```

Same pattern at `admin/_layout.php:8-11` (not logged in → login) and `admin/login.php:39-40` (logged in → dashboard). Forget the `exit` and you leak the protected page to someone you meant to redirect away.

### 4.7 Static assets in this repo

`views/shell.php:44-56` emits the stylesheet links — plain paths under the document root with a version query:

```php
<link rel="stylesheet" href="/css/<?= e($css) ?>.css?v=<?php echo SJ_ASSET_VER; ?>">
```

Those go straight to Apache, as do `/media/…` (uploads) and `/photos/…` (legacy images). `public_html/media/.htaccess` hardens that folder specifically, because it is the one place visitors' bytes land on our disk:

```apache
php_flag engine off
<FilesMatch "\.(php|phtml|phar)$">
  Require all denied
</FilesMatch>
```

Someone who sneaks a `.php` past the upload validator still cannot execute it — PHP is off for that directory.

### 4.8 Dev versus production

| | Development | Production |
|---|---|---|
| Web server | Apache 2.4 in Docker (`Dockerfile:1`, `php:8.3-apache`) | LiteSpeed on MilesWeb shared hosting |
| PHP | 8.3, mod_php | 8.3, selected in mPanel (`DEPLOY.md:9`) |
| Document root | `/var/www/html`, bind-mounted from `./public_html` (`docker-compose.yml:32`) | the account's `public_html/` |
| URL | `http://localhost:8090/` (`docker-compose.yml:41` maps host 8090 → container 80) | `https://stjosephsondipudur.com/` |
| Database | MySQL 8 container (`docker-compose.yml:2-3`) | MySQL created in mPanel |
| Credentials | env vars from `.env` (`docker-compose.yml:26-30`, read at `src/Core/Config.php:30-35`) | `config/config.php` above the webroot |
| Composer | in the image (`Dockerfile:18`) | never runs — `vendor/` is committed |
| Access | `docker compose exec` | no SSH at all; deploy = file upload |

One `Dockerfile` line exists purely to make dev honest (`Dockerfile:13-14`):

```dockerfile
RUN a2enmod headers expires rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
```

Debian's Apache ignores `.htaccess` by default. Without this, every caching and security rule we wrote would silently do nothing locally and only take effect in production — the worst place to discover a mistake.

`./run.sh` ties it together: creates `.env` and `config/config.php` from samples if missing (`run.sh:22-23`), starts the containers, waits for MySQL, refreshes the autoloader, runs the idempotent seeder (`run.sh:44`), and prints both URLs.

## 5. Why this is the right approach here

"One PHP file per URL, no framework, no router" is unusual in 2026. It is right for *this* project because of five hard constraints.

1. **Production is MilesWeb shared hosting with no SSH.** Deploying is dragging files into mPanel's File Manager (`DEPLOY.md:43`). We cannot run `composer install`, cannot run a build step, cannot restart a process, cannot install an extension. Anything whose deploy story is "run a command on the server" is disqualified before we start.
2. **No framework.** Laravel or Symfony each add thousands of files, and their front-controller design routes every URL through `index.php`, needing `mod_rewrite` plus a writable cache directory that survives an upload-over-the-top deploy. Both are fragile here.
3. **One non-technical editor.** The value of this project is the admin panel, not the routing layer. Every hour on a router is an hour not spent making editing safe.
4. **The pixel-freeze.** `CLAUDE.md` forbids any visual change during migration. The safest refactor keeps the URL → file mapping *exactly* as it was so each page compares one-for-one against the old one. A router would change all 42 mappings at once and any drift becomes invisible.
5. **≤12 SQL queries per page.** With the controller doing all fetching in one readable file, the budget is verifiable by reading twenty lines and measurable via the `<!-- sj-queries: N -->` comment.

The alternatives, weighed honestly:

| Option | Why not |
|---|---|
| Static HTML (the old site) | The problem we are solving. No editing without a developer. |
| A PHP framework | Needs Composer/build/rewrite on a host offering none; enormous surface for 42 pages. |
| A front controller + `mod_rewrite` | Genuinely tempting, and section 6 calls it the right *next* step. But it changes all 42 URL mappings mid-pixel-freeze and depends on a module we would rather not require. |
| WordPress or another off-the-shelf CMS | Throws away the exact existing design (pixel-freeze fails on day one) and adds a plugin/update treadmill nobody at the school can maintain. |

Note what we *did* adopt from framework practice where it cost nothing: PSR-4 autoloading via Composer, a repository layer, a whitelist-driven layout engine, and a front controller for the admin API (`admin/api/index.php:7-25` — a hardcoded route map, because that surface is new and has no legacy URLs to preserve). The design is "conventional where it is free, plain where the host demands it".

## 6. How this scales

### 10× pages — 42 becomes 420

**Still fine:** rendering. The shell, partials, layout engine and per-page CSS list do not care how many pages exist.

**Breaks first: the file-per-URL model itself.** We already have 18 near-identical academy controllers and 10 near-identical album controllers. At 420 pages that is hundreds of files differing by one string, and every cross-cutting change (a new `Layout::render` option, say) means editing all of them.

**Exact next step:** add a front controller. `public_html/.htaccess` gets a `RewriteRule` sending unmatched paths to a single `index.php`, which looks the slug up in a `pages` table and picks a template — precisely the shape `admin/api/index.php` already uses for the API. Keep thin stubs for the legacy 42 URLs so nothing 404s. Do this *after* the pixel-freeze lifts.

### 10× traffic

**Still fine:** static assets, which are most of the bytes. Apache serves them without PHP, and `.htaccess:47-51` gives them a one-year immutable cache so repeat visitors fetch almost nothing. **opcache** helps invisibly too: `Dockerfile:26-29` enables it, so PHP compiles each file to bytecode once and reuses it across requests. Compilation is not repeated per request — only *execution* is.

**Breaks first: the database connection, then the origin.** Every request calls `Db::pdo()` (`src/Core/Db.php:40-73`), opening a fresh MySQL connection, because nothing persists between requests. Shared hosting caps concurrent connections aggressively and that ceiling arrives well before CPU does. Right behind it: `.htaccess:53-55` marks all PHP output `no-cache`, so every page view hits PHP and MySQL — there is no cache in front.

**Exact next step:** cache the rendered HTML. The layout engine already holds the whole page body as a string at `src/View/Layout.php:47`, so writing it to a file keyed by slug and serving that while it is newer than the last content edit is a small, contained change. Beyond that, `docs/perf-baseline.md:106-107` names the production levers we cannot test locally — LiteSpeed HTTP/2, Brotli, and putting Cloudflare in front so most requests never reach us.

### 10× editors — 1 becomes 10

**Still fine:** the session machinery. Sessions are per-user files; idle and absolute timeouts and ID rotation are already enforced (`src/Admin/Auth.php:49-61`); visitors still pay zero session cost thanks to `Auth.php:24`.

**Breaks first: no roles, and no concurrent-edit protection.** `is_admin()` (`Auth.php:76-79`) is one boolean — every logged-in account can edit everything, including site settings. And `admin/api/field.php:17-18` is a bare `UPDATE … SET field = ? WHERE id = ?`: if two editors open the same paragraph, the second save silently destroys the first, with no warning and no recovery.

**Exact next step:** the schema is already prepared — `database/schema.sql:11` carries a `role` column defaulting to `'owner'`. Add a role check to `_bootstrap.php` beside the existing auth check, then add optimistic locking: send the row's `updated_at` with the edit and have `field.php` refuse the write if it no longer matches. The audit log (`sj_audit('field.save', …)`, `field.php:27`) already records who changed what, so recovery becomes possible once conflicts are detected.

## 7. Gotchas and mistakes to avoid

1. **Expecting anything to survive between requests.** A variable set on page A does not exist on page B. If it must persist: the database (content), `$_SESSION` (per-user state), or a cookie (a small hint). Nothing else.
2. **Output before `header()`.** One stray space before `<?php`, one blank line after `?>`, one debug `echo` — and every later `header()` silently fails. Omit the closing `?>` in pure-PHP files, as our `src/` files do.
3. **`header('Location: …')` without `exit`.** PHP keeps running and sends the body anyway. The redirect works, but you have already leaked the page you meant to protect.
4. **Trusting `$_GET`/`$_POST`.** Everything in them is a string the visitor typed, including parts you never showed them. It reaches SQL only as a PDO placeholder value, reaches HTML only through `e()` (`src/helpers.php:40-43`), and never reaches `include`, a file path, or a `Location:` header. `admin/editmode.php:22-24` shows the redirect case: the return path must start with a single `/` and contain no newlines, or it is discarded.
5. **Confusing `SCRIPT_NAME` with `REQUEST_URI`.** `SCRIPT_NAME` is the file the server resolved — trustworthy. `REQUEST_URI` is the raw path the visitor sent — attacker-controlled. Note `views/partials/admin-bar.php:4` uses `REQUEST_URI` and passes it through `e()` before printing it into a form field.
6. **Assuming `.htaccess` is doing something.** It is only read if the server allows overrides; `Dockerfile:14` is what makes it apply in dev. Every block in it is wrapped in `<IfModule>` so a host without the module degrades instead of 500ing.
7. **Adding a mutating GET.** `?action=delete` in a link will eventually be triggered by a prefetcher or a crawler. State changes are POST + CSRF, no exceptions.
8. **Querying the database inside a view.** It works, and it quietly blows the 12-query budget because the loop is invisible from the controller. `public_html/index.php:3-4` records that this was a real bug: home was at 16 queries (`docs/perf-baseline.md:30`) and is now at 12 (`:73`).
9. **Writing a new page from scratch.** Copy `public_html/tamilacademy.php` — it has the `require`, the not-seeded guard and the render call in the right order.

## 8. Try it yourself

Start the stack with `./run.sh`.

**1. Watch a request arrive.** Open http://localhost:8090/about.php, then in a second terminal run `docker compose logs -f web` and reload. You will see one Apache log line for `about.php` and one for *each* CSS file, image and font — proof that static assets are separate requests.

**2. See the raw HTTP response, and a 301 with no body.** `-D -` prints headers, `-o /dev/null` discards the body:

```bash
curl -s -D - -o /dev/null http://localhost:8090/about.php      # 200, text/html, Cache-Control: no-cache
curl -s -D - -o /dev/null http://localhost:8090/gal-sciexpo.php # 301, Location: /gal-spach.php, empty body
```

The `no-cache` came from `.htaccess:53-55`. Add `-L` to the second and curl follows the redirect.

**3. Count the SQL queries.** Set `'debug' => true` in `config/config.php`, then:

```bash
curl -s http://localhost:8090/ | grep -oE 'sj-queries: [0-9]+'
```

That number comes from the shutdown function at `public_html/bootstrap.php:29-38`. Budget: 12.

**4. Prove PHP dies.** Add `$counter = ($counter ?? 0) + 1;` and `echo "<!-- counter: $counter -->";` just before the `Layout::render` call in `public_html/about.php`. Reload ten times. It says `1` every time. Remove it afterwards.

**5. See the pattern, and confirm the document-root boundary.** Neither URL below is inside `public_html/`, and the second extension is blocked by `.htaccess:12-14` even if a copy ever landed there:

```bash
grep -c "Layout::render(" public_html/*.php | grep -v ':0'   # every page: the same three moves
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8090/../config/config.php
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8090/CLAUDE.md
```

## 9. Where to read more

**In this repo:**

- [`../01-fundamentals.md`](../01-fundamentals.md) — the web, PHP A–Z, SQL, PDO, sessions, Composer and Docker from zero. The natural next read.
- [`../02-security.md`](../02-security.md) — the attacks that arrive *through* the request described above, and how each is blocked.
- [`../03-what-we-built.md`](../03-what-we-built.md) — Stages A & B: the security work and the `src/` structure `bootstrap.php` loads.
- [`../04-stage-c-and-d.md`](../04-stage-c-and-d.md) — Stages C & D: the Bootstrap-5 front end and the deploy/speed work.
- [`../05-stage-e.md`](../05-stage-e.md) — Stage E: content moves into the database, which is why controllers now call `repo_*()`.
- [`../06-stage-f.md`](../06-stage-f.md) — Stage F: the live-edit overlay, and why the shell branches on `is_admin()`.
- [`../07-stage-g.md`](../07-stage-g.md) — Stage G: CSS consolidation and the single-document shell from section 4.4.
- [`../08-stage-h.md`](../08-stage-h.md) — Stage H: image renditions, SEO (the `$sjSlug` logic at `views/shell.php:16`), performance proof, backups and monitoring.
- [`../../../PHASES.md`](../../../PHASES.md) — the roadmap and what every stage letter means.
- [`../../../SECURITY.md`](../../../SECURITY.md) — the normative `SEC-*` catalog referenced throughout the code comments.
- [`../../../CLAUDE.md`](../../../CLAUDE.md) — the repo rules: no mutating GET, everything through `e()`, ≤12 queries, pixel-freeze.
- [`../../../DEPLOY.md`](../../../DEPLOY.md) — the production side of section 4.8, step by step.

**Outside:**

- [MDN — An overview of HTTP](https://developer.mozilla.org/en-US/docs/Web/HTTP/Overview) — the clearest free explanation of requests, responses, methods and status codes.
- [PHP Manual — Variables from external sources](https://www.php.net/manual/en/language.variables.external.php) — the official reference for `$_GET`, `$_POST` and `$_FILES`.
- [PHP Manual — Output Control](https://www.php.net/manual/en/book.outcontrol.php) — `ob_start()` and friends, the mechanism behind `src/View/Layout.php:45-47`.
- [Apache — .htaccess tutorial](https://httpd.apache.org/docs/2.4/howto/htaccess.html) — what per-directory config can and cannot do, and why `AllowOverride` matters.
