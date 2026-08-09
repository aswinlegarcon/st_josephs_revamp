# How a Request Is Served — The Whole Journey

> **Read this after** [`01-fundamentals.md`](01-fundamentals.md).
> **You need:** very basic PHP (`echo`, variables, `if`, functions, `include`). Nothing else.
> **You will be able to:** point at any line of any page and say *why it runs, when it runs, and what runs next.*

This document follows **one single request** — a visitor typing our address into a
browser — from the first keystroke to the last pixel. Then it does the same for the
four other kinds of request the site handles. Nothing is skipped and nothing is
hand-waved. If a step happens, it is named here with the file and line that does it.

Keep the repo open beside you. Every path below is real.

---

## 0. The mental model (read this twice)

Three sentences that explain almost everything about PHP:

1. **A request arrives, PHP starts a brand-new program, that program prints text, PHP dies.**
2. **Nothing survives between requests** except what you deliberately wrote down — in the
   database, in a file, or in a session file.
3. The "text" PHP prints happens to be HTML. The browser is what turns it into a page.

A useful analogy: PHP is not a shop that stays open. It is a **chef who is hired,
cooks exactly one dish, serves it, and is fired** — 40 times a second if needed. The chef
remembers nothing about the last customer. The recipe book (your code) and the pantry
(the database) are the only things that persist.

If you hold that model, the rest of this document is detail.

---

## 1. The journey of `GET /` — step by step

A parent opens their phone and goes to `stjosephsondipudur.com`.

### Step 1 — The browser finds the server (DNS)

The browser does not know what `stjosephsondipudur.com` means. It asks a **DNS**
(Domain Name System) server, which is the phone book of the internet, and gets back an
**IP address** — a number like `103.x.x.x` identifying our machine at MilesWeb.

*Nothing of ours runs yet.*

### Step 2 — The browser opens a connection and asks

The browser opens a **TCP** connection to that IP on port 443 (HTTPS), does a **TLS
handshake** (agrees on encryption so nobody on the café Wi-Fi can read the traffic), and
sends a **request**:

```http
GET / HTTP/1.1
Host: stjosephsondipudur.com
User-Agent: Mozilla/5.0 (Android …)
Accept: text/html,…
Cookie: (usually nothing — a visitor has no cookies from us)
```

Three things to notice:

- `GET` is the **method**. It means "give me something", and it must **never change
  data**. That is a rule, not a suggestion — see [`the CSRF topic doc`](topics/csrf-protection.md).
- `/` is the **path**.
- Headers are metadata; the body is empty for a GET.

### Step 3 — The web server maps the URL to a file

A **web server** (Apache in our Docker development stack, LiteSpeed on MilesWeb) is
listening. It has a **document root** — one directory that the whole internet is allowed
to look at. Ours is:

```
public_html/
```

The server turns the URL path into a file path underneath that directory:

| URL | File on disk |
|---|---|
| `/` | `public_html/index.php` (the directory index) |
| `/about.php` | `public_html/about.php` |
| `/tamilacademy.php` | `public_html/tamilacademy.php` |
| `/css/home.css` | `public_html/css/home.css` |
| `/photos/logo-main.png` | `public_html/photos/logo-main.png` |

**This is our entire routing system.** There is no router class, no URL-rewriting table.
One URL = one file. That is why `public_html/` contains 42 `.php` files with names that
look exactly like the site's menu.

> **Why the document root matters so much.** Everything *outside* `public_html/` — `src/`,
> `views/`, `database/`, `vendor/`, and critically `config/config.php` with the database
> password — has **no URL at all**. Nobody can request it. That is not a permission
> setting we can forget to apply; those files are simply not reachable over HTTP. This is
> the single most important layout decision in the project.

### Step 4 — Apache reads `.htaccess` before doing anything else

Before serving, Apache reads [`public_html/.htaccess`](../../public_html/.htaccess) —
a per-directory configuration file. Ours does five jobs
([`.htaccess:1-56`](../../public_html/.htaccess)):

```apache
Options -Indexes                                   # no directory listings
RedirectMatch 404 "^/\.(?!well-known/)"            # hide .git, .env, dotfiles
<FilesMatch "\.(sql|bak|old|zip|…|sh)$">           # never serve dumps/scripts
  Require all denied
</FilesMatch>
<IfModule mod_headers.c> … security headers … </IfModule>
<IfModule mod_deflate.c> … gzip compression … </IfModule>
<IfModule mod_expires.c> … caching rules … </IfModule>
```

Two lessons hidden in that file:

- Every block is wrapped in `<IfModule>`, so if the production host lacks a module the
  site **degrades instead of returning 500**. On shared hosting you cannot assume modules.
- For a long time in development **none of this ran**. The stock `php:8.3-apache` image
  ships `AllowOverride None`, which makes Apache ignore `.htaccess` entirely. Our
  `Dockerfile` had to flip it to `AllowOverride All` and enable `mod_headers`/`mod_expires`.
  A security rule that is silently inert is worse than no rule, because you believe you
  are protected.

### Step 5 — Static files stop here

If the URL had been `/css/home.css`, Apache would read the file off disk, apply the gzip
and cache headers, and send it. **PHP is never involved.** This is why images and
stylesheets are fast and why they are the bulk of a page's weight — see
[`topics/caching-and-cache-busting.md`](topics/caching-and-cache-busting.md).

Our URL is `/`, which maps to a `.php` file, so Apache hands it to PHP.

### Step 6 — PHP starts and fills the superglobals

PHP starts a fresh process for this request and fills in the **superglobals** — special
arrays that exist everywhere without being passed around:

| Superglobal | Holds | Example in our code |
|---|---|---|
| `$_GET` | query-string values (`?s=hero`) | `public_html/admin/section.php` reads `?s=` |
| `$_POST` | form fields from a POST body | `public_html/admin/login.php:17-18` |
| `$_SERVER` | request metadata set by the server | `views/shell.php:16` reads `SCRIPT_NAME` |
| `$_COOKIE` | cookies the browser sent | `src/Admin/Auth.php:24` checks for `SJADMIN` |
| `$_SESSION` | server-side per-visitor storage | `$_SESSION['admin_id']` after login |
| `$_FILES` | uploaded files | the upload endpoint |

Then it executes `public_html/index.php` from **line 1, top to bottom**.

### Step 7 — `index.php`: the thin controller

Here is the *entire* home page controller
([`public_html/index.php`](../../public_html/index.php)):

```php
<?php
require __DIR__ . '/bootstrap.php';                 // line 5

$sj_page      = repo_page('index');                 // line 7
$sj_principal = repo_profile('principal');
$sj_features  = repo_unique_features(is_edit());

$sj_hero_slides  = $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [];
$sj_ticker       = repo_ticker(is_edit());
$sj_updates      = repo_update_slides(is_edit());
$sj_marks_years  = repo_marks_board(null, is_edit());
$sj_testimonials = repo_testimonials(is_edit());

\SJ\View\Layout::render('home', [ … all of the above … ]);   // line 19
```

That is the whole file — 35 lines. This shape has a name: a **thin controller**. Its only
jobs are *fetch the data* and *hand it to the renderer*. It contains no HTML and no SQL.

**The rule this enforces:** every database query for a page happens here, in one visible
place. You can count them by reading one short file. That is what makes the "≤ 12 SQL
queries per page" budget in `CLAUDE.md` checkable rather than aspirational.

### Step 8 — `bootstrap.php`: the four things every request needs

[`public_html/bootstrap.php`](../../public_html/bootstrap.php) is `require`d as line 1 of
*every* page, every admin screen, and the command-line seeder. It is 39 lines and does
exactly four things:

```php
define('SJ_PUBLIC_ROOT', __DIR__);                     // where is public_html?   (line 8)
define('SJ_ASSET_VER', '20260809.2');                  // cache-busting version   (line 15)
require_once dirname(__DIR__) . '/vendor/autoload.php';// load classes + helpers  (line 21)
sj_session_boot(false);                                // maybe start a session   (line 25)
```

Each deserves a sentence:

- **`SJ_PUBLIC_ROOT`** is the anchor for every other path. `dirname(SJ_PUBLIC_ROOT)` is the
  repo root, which is how `src/Core/Config.php` finds `config/config.php` *above* the webroot.
- **`SJ_ASSET_VER`** is appended to every CSS/JS URL. Bump it on deploy and every browser
  fetches fresh files; leave it and they use their cache. One line replaces a whole build tool.
- **`vendor/autoload.php`** is Composer's **autoloader**. After this line, writing
  `\SJ\View\Layout::render()` silently loads `src/View/Layout.php` the first time it is
  needed. It also loads `src/helpers.php` immediately, which is why global functions like
  `e()` and `repo_page()` exist before your code runs.
  See [`topics/composer-and-autoloading.md`](topics/composer-and-autoloading.md).
- **`sj_session_boot(false)`** — note the `false`. It means *"start a session only if this
  visitor already has our admin cookie."* A parent browsing the site never touches the
  session system at all: no file written, no cookie set, no cost. Only admins pay for it.

There is a fifth, development-only block: when `config['debug']` is on, a shutdown
function appends `<!-- sj-queries: N -->` to the HTML so you can see how many SQL
statements the page ran ([`bootstrap.php:29-38`](../../public_html/bootstrap.php)).

### Step 9 — The repository fetches the data

`repo_page('index')` is a plain global function in
[`src/helpers.php:228-231`](../../src/helpers.php) that delegates to a class:

```php
function repo_page(string $slug): ?array
{
    return Repo::page($slug);
}
```

`SJ\Content\Repo` is the **only** place public-page SQL lives. Two rules make this
valuable:

- **Views never query.** A template cannot secretly cost you a database round trip.
- **Images arrive pre-joined.** Repository methods select the row *and* its image in one
  query, instead of looping over rows and fetching each image separately (the classic
  "N+1" mistake). See [`topics/the-repository-pattern.md`](topics/the-repository-pattern.md).

Under the hood, `db()` returns a single shared PDO connection from
[`src/Core/Db.php:40-73`](../../src/Core/Db.php), configured to throw exceptions on error,
use **real** prepared statements, return associative arrays, and speak `utf8mb4`. If the
database is unreachable, that same file answers **503** with a plain-text message rather
than a stack trace.

Notice `is_edit()` being passed around. It is `true` only for a logged-in admin who has
switched on edit mode, and it makes the repositories include rows marked
`is_active = 0`. Same code path, two audiences: the admin sees hidden items, the public
does not.

### Step 10 — `Layout::render()` assembles the page

[`src/View/Layout.php:30-50`](../../src/View/Layout.php):

```php
public static function render(string $page, array $data = []): void
{
    if (!\in_array($page, self::PAGES, true)) {      // hardcoded whitelist
        \http_response_code(500);
        exit('Unknown view.');
    }

    $views = \dirname(SJ_PUBLIC_ROOT) . '/views';
    \extract($data, EXTR_SKIP);                       // array keys → variables

    if (\function_exists('is_edit') && is_edit() && !\headers_sent()) {
        \header('X-Frame-Options: DENY');             // edit mode is never framed
    }

    \ob_start();
    include $views . '/pages/' . $page . '.php';      // render the CONTENT
    $content = \ob_get_clean();                       // capture it into a string

    include $views . '/shell.php';                    // render the CHROME around it
}
```

Three techniques worth learning from those 20 lines:

1. **The whitelist.** `self::PAGES` ([lines 17-28](../../src/View/Layout.php)) is a fixed
   array of allowed template names. A request can never choose a file path. If it could,
   `?page=../../config/config` would be a catastrophe. This pattern — *identifiers come
   from code, never from the request* — repeats everywhere in this codebase.
2. **`extract()`.** Turns `['sj_page' => …]` into a `$sj_page` variable so templates can
   use short names. `EXTR_SKIP` means it will never overwrite an existing variable.
3. **Output buffering.** `ob_start()` says "don't send output to the browser yet, collect
   it in memory". The page content is rendered *first*, captured into `$content`, and only
   then is the shell rendered with `<?= $content ?>` in the middle. This is how one shell
   file can wrap every page without every page having to `include` a header and a footer.

### Step 11 — `shell.php`: one document, every page

[`views/shell.php`](../../views/shell.php) is the single HTML skeleton for all 42 pages. In order, it:

1. **Computes SEO tags** ([lines 12-22](../../views/shell.php)). The page's slug comes from
   `basename($_SERVER['SCRIPT_NAME'], '.php')` — the *server's* name for the running script,
   never anything the visitor typed. That value looks up a row in `seo_meta` and produces
   the `<title>`, `<meta name="description">`, `<link rel="canonical">` and Open Graph tags.
2. **Links assets in a deliberate order** ([43-57](../../views/shell.php)): one self-hosted
   Bootstrap 5.3.3 → Font Awesome → fonts → `tokens.css` → `footer.css` → this page's
   stylesheets. CSS order is not decoration; later rules win ties.
3. **Injects database-driven CSS** if the page set `$sjHeadCss` ([58-65](../../views/shell.php)) —
   this is how an editor's chosen background image becomes a real CSS rule.
4. **Adds admin-only assets** behind `if (is_admin())` ([66-70](../../views/shell.php)) —
   including the CSRF token in a `<meta>` tag. A normal visitor is sent **zero** bytes of
   admin code.
5. **Includes the chrome partials** — preloader, navbar, scroll-up — then prints
   `<?= $content ?>`, then the footer ([76-83](../../views/shell.php)).
6. **Puts scripts at the end** ([85-97](../../views/shell.php)) so the page renders before
   JavaScript runs.

### Step 12 — PHP finishes and the response goes out

The script reaches the end. PHP sends the response:

```http
HTTP/1.1 200 OK
Content-Type: text/html; charset=UTF-8
Cache-Control: no-cache          ← from .htaccess: HTML is never cached
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Content-Encoding: gzip
(… the HTML …)
```

Then **the process dies.** Every variable, the PDO connection, everything — gone. The
next request starts from nothing.

### Step 13 — The browser builds the page

The browser now does its own work: parses the HTML, discovers `<link rel="stylesheet">`
and blocks rendering until the CSS arrives, discovers `<img>` tags and fetches them
(lazily, for anything below the fold), then runs the scripts at the bottom. That second
half is where LCP and CLS live — see
[`topics/performance-measurement.md`](topics/performance-measurement.md).

### The whole trip on one page

```
Browser  ──DNS──▶ IP  ──TCP+TLS──▶  Apache / LiteSpeed
                                     │
                                     ├─ reads public_html/.htaccess  (headers, gzip, cache)
                                     ├─ static file?  ─▶ send it, done. PHP never runs.
                                     └─ *.php  ─▶  PHP
                                                    │
      public_html/index.php  ──require──▶  bootstrap.php
        │                                    ├─ SJ_PUBLIC_ROOT, SJ_ASSET_VER
        │                                    ├─ vendor/autoload.php  (SJ\ classes + helpers)
        │                                    └─ sj_session_boot(false)   ← only if admin cookie
        │
        ├─ repo_*()  ─▶  SJ\Content\Repo  ─▶  SJ\Core\Db (PDO)  ─▶  MySQL
        │
        └─ SJ\View\Layout::render('home', $data)
             ├─ whitelist check
             ├─ extract($data)
             ├─ ob_start(); include views/pages/home.php; $content = ob_get_clean();
             └─ include views/shell.php
                  ├─ SEO tags from seo_meta
                  ├─ CSS in cascade order (?v=SJ_ASSET_VER)
                  ├─ partials: preloader, navbar, scroll-up
                  ├─ <?= $content ?>
                  ├─ footer
                  └─ scripts  (+ admin overlay only if is_admin())
                                                    │
                                          HTML ─────┘ ─▶ browser ─▶ CSS ─▶ images ─▶ JS
```

---

## 2. Variant A — a page in a family (`GET /tamilacademy.php`)

Eighteen academy pages, four school-section pages and ten gallery albums do **not** have
eighteen, four and ten templates. They share one each. Here is the entire
`tamilacademy.php` ([`public_html/tamilacademy.php`](../../public_html/tamilacademy.php)):

```php
<?php
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

The **slug is hardcoded in the file** — `repo_academy('tamilacademy')`. It is not read
from the URL. Every one of the eighteen files is this same shape with a different literal.

Why not one `academy.php?slug=…` page with rewriting? Because of a project rule: *every
legacy URL keeps working exactly as it was*. The old site had `tamilacademy.php`, so
`tamilacademy.php` still exists, and parents' bookmarks and Google's index still resolve.
The cost is eighteen nearly-identical 19-line files; the benefit is zero broken links and
zero dependence on URL-rewriting support that a shared host might not give us.

---

## 3. Variant B — a static asset (`GET /css/home.css`)

Apache finds the file, applies from `.htaccess`:

```apache
<FilesMatch "\.(css|js|jpg|jpeg|png|webp|gif|ico|woff2|svg)$">
  Header set Cache-Control "public, max-age=31536000, immutable"
</FilesMatch>
```

`max-age=31536000` is one year and `immutable` means "do not even ask me if it changed".
That would normally be reckless — how do you ship a CSS fix? — except the URL is
`/css/home.css?v=20260809.2`. Change `SJ_ASSET_VER` and it becomes a **different URL**,
which the browser has never seen and must fetch. Same file, new name, instant update.

The corollary bit this project three times: **edit a CSS or JS file after bumping the
version and you must bump it again**, or every browser keeps serving the old copy while
you stare at unchanged pixels and doubt your sanity.

---

## 4. Variant C — an admin page (`GET /admin/section.php?s=hero`)

Same first five steps. Then the differences begin, and they are all guards.
[`public_html/admin/_layout.php:5-16`](../../public_html/admin/_layout.php):

```php
require dirname(__DIR__) . '/bootstrap.php';
sj_session_boot(true);                  // TRUE: always start a session here
sj_admin_headers();                     // XFO / CSP / nosniff / Referrer-Policy
if (!is_admin()) {                      // guard 1: logged in?
    header('Location: /admin/login.php');
    exit;
}
if (!empty($_SESSION['must_change_pw'])) {   // guard 2: password change pending?
    header('Location: /admin/password.php');
    exit;
}
```

Note the order — **authenticate, then check the password gate, then render**. Also note
`exit` after every `header('Location: …')`. Without it PHP would keep executing and print
the page body *underneath* the redirect. Some browsers would render it. That is a real
information leak, and it is why `exit` follows every redirect in this codebase.

The full story of what happens after these guards — the session, the panel, the database
writes — is [`10-admin-panel-internals.md`](10-admin-panel-internals.md).

---

## 5. Variant D — an API write (`POST /admin/api/index.php?r=field`)

This is the most heavily guarded path in the project, so follow it closely. It runs when
an editor changes a heading in the admin panel.

**1. The front controller** ([`public_html/admin/api/index.php`](../../public_html/admin/api/index.php)):

```php
$routes = [
    'field'    => 'field.php',
    'item'     => 'item.php',
    'order'    => 'order.php',
    'upload'   => 'upload.php',
    'images'   => 'images.php',
    'settings' => 'settings.php',
    'link'     => 'link.php',
    'recrop'   => 'recrop.php',
    'image'    => 'image.php',
];

$r = (string)($_GET['r'] ?? '');
if (!isset($routes[$r])) { … api_fail('Unknown route', 404); }
require __DIR__ . '/' . $routes[$r];
```

The request supplies `r=field`. It is used as an **array key**, never as a filename. If
someone sends `r=../../../etc/passwd`, `isset($routes[$r])` is false and they get a 404.
The request can only pick from nine values that a programmer wrote down.

**2. The shared guard** — every endpoint's first line is
`require __DIR__ . '/_bootstrap.php'`, and
[`_bootstrap.php`](../../public_html/admin/api/_bootstrap.php) runs four checks in this
exact order:

```php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');            // authenticated JSON is never cached
sj_admin_headers();

if (!is_admin()) { api_fail('Not authenticated', 401); }                 // ① logged in
if (!empty($_SESSION['must_change_pw'])) { api_fail('…', 403); }         // ② password gate
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {                              // ③ CSRF
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        api_fail('Invalid CSRF token', 403);
    }
}
```

Putting the guard in **one shared file** rather than repeating it nine times means a new
endpoint cannot forget it — the `require` is how you get the `api_out()` and `api_fail()`
functions you need to answer at all.

**3. Validation against the registry**
([`public_html/admin/api/field.php`](../../public_html/admin/api/field.php)):

```php
$reg = api_entity($entity);              // entity name → registry definition, or fail
$def = $reg['fields'][$field] ?? null;   // field must be declared in the registry
if ($def === null || $id <= 0) { api_fail('Unknown field'); }

$value = api_validate_field($entity, $field, $def, $in['value'] ?? null);

$st = db()->prepare("UPDATE {$reg['table']} SET `$field` = ? WHERE id = ?");
$st->execute([$value, $id]);
```

Look carefully at that `UPDATE`. The table name and the column name **are interpolated
into the SQL string** — which is normally the definition of a SQL-injection bug. It is safe
here for one reason and one reason only: `$reg['table']` and `$field` did not come from
the request. They came from `src/Content/Registry.php`, a PHP array written by a
programmer. The *value* — the only thing the user controls — travels as a `?` placeholder.

That distinction is the heart of the design. Database identifiers **cannot** be
placeholders in SQL, so they must come from a whitelist. See
[`topics/the-registry-pattern.md`](topics/the-registry-pattern.md) and
[`topics/pdo-and-sql.md`](topics/pdo-and-sql.md).

**4. Type validation.** `api_validate_field()`
([`_bootstrap.php:57-119`](../../public_html/admin/api/_bootstrap.php)) switches on the
registry's declared type: `text` is `strip_tags()`-ed and length-checked, `html` goes
through `sj_sanitize_html()` (a frozen tag whitelist), `url` rejects `javascript:` and
`data:`, `int` is range-checked, `enum` must match a listed value, `image` must be an id
that actually exists.

**5. Audit, then answer.** `sj_audit('field.save', $entity, $id, $field)` writes a row to
`audit_log`, then `api_out(['value' => $value])` prints JSON and exits.

**The full chain for one keystroke:**

```
route whitelist → logged in? → password gate → CSRF token → entity in registry?
   → field declared? → type-validated & sanitised → identifiers from code, value bound
   → audit row → JSON reply
```

Seven gates. Any one of them alone would be inadequate; together they are why an admin
API that can edit twenty-odd tables is not a liability.

---

## 6. How this shape was built — the stages

The architecture above did not arrive at once. It was built in ordered phases (see
[`PHASES.md`](../../PHASES.md)), and knowing the order explains many "why is it like
this?" questions.

| Stage | Phases | What changed in the request path |
|---|---|---|
| **A — Security first** | S1–S4, F0 | Secrets moved to `config/config.php` above the webroot; the default admin password was killed and a forced change added; sessions got timeouts and id rotation; `.htaccess` + security headers arrived; every admin action began writing to `audit_log`. Also `docs/perf-baseline.md` — measure *before* optimising. |
| **B — Structure** | P1–P4 | Composer + PSR-4 autoloading replaced a pile of `require`s; the old `_libs/*.php` became `SJ\` classes; the admin API gained its **front controller**; `SJ\View\Layout` + `views/` introduced the thin-controller/template split, proven on the home page. |
| **C/D — Front end + delivery** | R1a–R1d, F1, X1 | Every page moved onto **one** Bootstrap 5.3.3 shell — before this a page was ~9 nested included documents, some loading Bootstrap 4.5.3 and others 4.3.1. `?v=time()` (which defeated caching on every request) became `SJ_ASSET_VER`. Gzip and long-cache rules landed. |
| **E — Content into the database** | C1–C9, M1–M4 | Page after page stopped being hardcoded HTML and started coming from MySQL through repositories, with the registry-driven admin panel editing it. The media pipeline (presets, renditions, crops, the link table) arrived here. |
| **F — Edit in place** | O1, O2 | The live-edit overlay: `data-edit-*` attributes emitted by `src/View/EditAttrs.php`, read by JavaScript, posted to the same guarded API. Admin-only bytes. |
| **G — Cleanup** | R2, R3 | CSS consolidated, `_libs/` and `_templates/` deleted for good, and all 42 URLs taken from 368 W3C validation errors to zero, with accessibility fixes that change no pixels. |
| **H — Speed, findability, safety nets** | F2–F4, X2, X3 | Image renditions (home page 10.3 MB → 1.9 MB), the SEO tags described in Step 11, the measured audit, backups with a real restore drill, and health monitoring. |

Each stage's own changelog is [`03-what-we-built.md`](03-what-we-built.md) through
[`08-stage-h.md`](08-stage-h.md).

---

## 7. Try it yourself

Start the stack:

```bash
./run.sh
```

**See the routing.** Open <http://localhost:8090/> then <http://localhost:8090/about.php>.
Now rename nothing — just note that `public_html/about.php` exists and
`public_html/nonexistent.php` does not, and try the second URL. That 404 is Apache, not PHP.

**Watch the whole response, headers included:**

```bash
curl -sD - -o /dev/null http://localhost:8090/
```

**Count the database queries for one page.** Set `'debug' => true` in `config/config.php`, then:

```bash
curl -s http://localhost:8090/ | grep -o 'sj-queries: [0-9]*'
```

Set it back to `false` afterwards — that comment should never appear in production.

**Prove that views do not query.** Open [`views/pages/home.php`](../../views/pages/home.php)
and search it for `db()` or `SELECT`. There are none. Every value it prints arrived in the
`$data` array from `public_html/index.php`.

**Prove the whitelist works.** In `src/View/Layout.php`, the `PAGES` constant lists the
allowed templates. Temporarily call `Layout::render('nope', [])` from a scratch page and
watch it answer `500 Unknown view.` rather than trying to include a file.

**Watch an API call.** Log into <http://localhost:8090/admin/>, open devtools → Network,
change any field, and inspect the request: `POST /admin/api/index.php?r=field`, with an
`X-CSRF-Token` header and a small JSON body. Then re-send the same request without the
header (right-click → Copy as fetch, delete the header, run it in the console) and watch
it come back **403**.

---

## 8. Where to go next

| Question | Document |
|---|---|
| How does login, the session and saving to the database actually work? | [`10-admin-panel-internals.md`](10-admin-panel-internals.md) |
| I want the language and tooling from zero. | [`01-fundamentals.md`](01-fundamentals.md) |
| What attacks are we defending against? | [`02-security.md`](02-security.md) and [`../../SECURITY.md`](../../SECURITY.md) |
| Explain one topic properly. | [`topics/`](topics/) — one document per subject |
| What was built when, and why? | [`../../PHASES.md`](../../PHASES.md), and the stage docs 03–08 |
| What are the rules I must not break? | [`../../CLAUDE.md`](../../CLAUDE.md) |
