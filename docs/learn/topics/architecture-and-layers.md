# Project Architecture — Layers, Controllers and Views

> **What you'll learn:** what a "layer" is, and why our code is split into an entry script, a
> bootstrap, a repository, a layout engine, a page view and a shell. By the end you should be
> able to open any `.php` file in `public_html/` and say what it may do — and what it must not.
>
> **Prerequisites:** [`../01-fundamentals.md`](../01-fundamentals.md) (the web, PHP basics, what
> a database is) and its companion [How PHP Serves a Web Page](how-php-serves-a-page.md). You
> only need `echo`, variables, `if`, functions and `include`; the rest is explained here.
>
> **Where it lives:** `public_html/*.php`, `public_html/bootstrap.php`, `src/View/Layout.php`,
> `views/shell.php`, `views/pages/*.php`, `views/partials/*.php`, `src/Content/Repo.php`,
> `src/helpers.php`, `public_html/admin/**`.

## 1. The one-paragraph version

A visitor asks for `https://…/tamilacademy.php`. Apache runs the real file
`public_html/tamilacademy.php` — 19 lines. That file **loads the engine**
(`require __DIR__ . '/bootstrap.php'`), **fetches data** (`repo_academy('tamilacademy')`), and
**hands it to a renderer** (`\SJ\View\Layout::render('academy', [...])`). The renderer picks a
*page view* from a hardcoded list, runs it to produce the middle of the page, then drops that
middle into a *shell* holding the `<head>`, navbar and footer. Seventeen other URLs do the same
with a different slug and reuse the **same** page view. That is the whole architecture: **each
URL is a real file, each file is thin, and the HTML lives in one place.**

## 2. The problem this solves

Before the revamp, one URL was one big file that did everything. The original is still in git —
`git show 6bd0d11:public_html/tamilacademy.php`. It was **275 lines**: `include "_libs/load.php"`,
then its own `<!DOCTYPE html>`, its own `<head>`, ~150 lines of CSS pasted inline in a `<style>`
tag, then content, then a footer include. Markup, styling, data access and page logic in one file.
Today's `public_html/tamilacademy.php` is **19 lines** with no HTML at all.

Shared pieces lived in `public_html/_templates/`, pulled in by a helper in the old bootstrap
(`git show 6bd0d11:public_html/_libs/load.php`):

```php
function get_templates($name)
{
    include SJ_PUBLIC_ROOT . "/_templates/{$name}.php";
}
```

The catch: **each template was itself a complete web page.** `_templates/navbar.php` began
`<!DOCTYPE html><html lang="en"><head>…<title>Responsive Navigation Bar</title>`. A page that
included eleven templates shipped **eleven nested mini-documents** to the browser — see
[`../04-stage-c-and-d.md`](../04-stage-c-and-d.md) lines 11–16. Browsers tolerate that; they
should not have to. It also meant four Bootstrap versions on one page.

| Symptom | Cause |
|---|---|
| A navbar fix means editing 42 files | No single place owns the chrome |
| Two pages drift apart visually | Each carried its own copy of the CSS |
| You can't tell which page hits the DB | Queries scattered inside markup |
| One typo breaks the `<head>` of every page | Nested documents |
| A 19th academy page = copy-paste 275 lines | No shared template |

**A "layer" is just a rule about what a file is allowed to do.** Layers add no features. They
make each file small enough to hold in your head, and make "where do I change X?" have exactly
one answer.

## 3. How it works in general

**1. Thin controller.** *Controller* is the traditional name for the file the URL points at.
"Thin" means no HTML and no business logic — it gathers data and delegates. Like a waiter:
takes your order, fetches from the kitchen, puts the plate down. Does not cook.

**2. Template + data.** A *view* (or *template*) is mostly HTML with small `<?= … ?>` holes. It
receives ready-made variables and prints them; it never asks the database a question. Same
template + different data = different page.

**3. Layout / shell.** Every page shares an outer frame: `<head>`, navbar, footer. Written
**once** in a *shell*; the page-specific middle is injected into it. The PHP technique behind
that is **output buffering** — instead of sending text to the browser, hold it in memory,
capture it as a string, and place the string where you want:

```php
ob_start();                // start recording output instead of sending it
include 'some-view.php';   // this file's output goes into the buffer
$content = ob_get_clean(); // stop recording; hand back the tape as a string
```

## 4. How we use it — every place in this codebase

### 4.1 The layers, in order

```
Browser  →  public_html/<page>.php      (1) entry script / thin controller
              ├─ require bootstrap.php  (2) constants + autoload + session
              ├─ repo_*() functions     (3) data layer → src/Content/Repo.php → MySQL
              └─ SJ\View\Layout::render (4) layout engine → src/View/Layout.php
                    ├─ views/pages/<page>.php    (5) the page's content (captured)
                    └─ views/shell.php           (6) the outer document
                          └─ views/partials/*.php (7) navbar, footer, preloader, …
```

### 4.2 Layer 1 — the entry script (thin controller)

Every public URL is a **real file on disk**. There is no URL rewriting: `public_html/.htaccess`
holds hardening, compression and caching rules but **no `RewriteRule`**. `/kg.php` runs
`public_html/kg.php`, full stop.

`public_html/tamilacademy.php` is the whole file — all 19 lines:

```php
require __DIR__ . '/bootstrap.php';                                   // :3

$sj_academy = repo_academy('tamilacademy');                           // :5
if (!$sj_academy) { http_response_code(503); exit('Academy content not seeded.'); } // :6-9

\SJ\View\Layout::render('academy', [                                  // :11
    'bodyClass'   => 'academics',
    'styles'      => ['academy'],   'scripts' => ['academy'],
    'sj_slug'     => 'tamilacademy',                                  // :16
    'sj_academy'  => $sj_academy,
    'sj_carousel' => repo_linked_images('academy', (int)$sj_academy['id'], 'carousel'),
]);
```

Same shape everywhere. `public_html/index.php` (35 lines) queries seven things at
`index.php:7-17` and renders `'home'` at `:19`; its comment at `index.php:3-4` states the rule:
*"the content partials are parametrized, so ALL queries happen here (none inside views)."*
`public_html/about.php:10` loops `['president','principal','history','rules']` through
`repo_profile()`; `public_html/gal-annual.php` (17 lines) fetches one album; `public_html/kg.php`
is the busiest at 25 lines and still gathers carousel, timeline and events (`kg.php:22-24`)
*before* rendering.

**Rule:** a controller may query. A view may not. (One honest exception in §4.7.)

### 4.3 Layer 2 — `bootstrap.php`

`public_html/bootstrap.php` is 38 lines, `require`d first by every page, every admin screen and
the CLI seeder. Four jobs:

| Lines | What | Plain meaning |
|---|---|---|
| `:7-9` | `define('SJ_PUBLIC_ROOT', __DIR__)` | remember where the webroot is |
| `:14-16` | `define('SJ_ASSET_VER', '20260809.2')` | one string appended to CSS/JS URLs, so browsers re-download only after a deploy |
| `:21` | `require_once … '/vendor/autoload.php'` | turn on **autoloading** |
| `:25` | `sj_session_boot(false)` | start a login session only if an admin cookie is present |

**Autoloading**: when PHP meets a class it has never seen, it asks a registered function to find
the file. Composer writes that function; our rule is `composer.json:11-18` —
`"psr-4": { "SJ\\": "src/" }` plus `"files": [ "src/helpers.php" ]`. The first line means class
`SJ\View\Layout` lives at `src/View/Layout.php`: name maps to path, mechanically. That convention
is **PSR-4**. `SJ\` is a **namespace** — a prefix keeping our `Layout` from colliding with anyone
else's, the way a folder keeps two `notes.txt` apart. `vendor/` is committed
(`bootstrap.php:19-20`), so production never runs Composer.

### 4.4 Layer 3 — the data layer (repositories)

A **repository** is a class whose only job is "get me rows". Ours is `src/Content/Repo.php`.
Every method is small and uses a **prepared statement** — SQL with a `?` hole the database fills
in safely:

```php
$st = db()->prepare('SELECT * FROM pages WHERE slug = ?');   // Repo::page(), Repo.php:94-99
$st->execute([$slug]);
return $st->fetch() ?: null;
```

Repos pre-join images so views never loop-and-query (the "N+1" trap): `Repo::heroSlides`
(`src/Content/Repo.php:101-108`) fetches slides **and** their image rows in one statement, using
the shared `IMG_SELECT` fragment declared at `src/Content/Repo.php:14-20`.

### 4.5 Layer 4 — the layout engine

`src/View/Layout.php` is 51 lines and is the only thing that decides which template runs.

```php
private const PAGES = [                                    // src/View/Layout.php:17
    'home', 'about', 'staffs', 'academics', 'achievements',
    'co-curriculum', 'sports', 'infrastructure', 'gallery',
    'academy', 'section', 'album',
];                                                         // :28

public static function render(string $page, array $data = []): void   // :30
{
    if (!\in_array($page, self::PAGES, true)) {            // :32
        \http_response_code(500); exit('Unknown view.');
    }
    $views = \dirname(SJ_PUBLIC_ROOT) . '/views';          // :37
    \extract($data, EXTR_SKIP);                            // :38
    …
    \ob_start();                                           // :45
    include $views . '/pages/' . $page . '.php';           // :46
    $content = \ob_get_clean();                            // :47
    include $views . '/shell.php';                         // :49
}
```

**(a) The hardcoded whitelist (`:17-28`).** The template name is checked against a list written
in the source, so nothing from the request can reach line 46. The comment at
`src/View/Layout.php:6-8` cites `SECURITY.md` **SEC-11**, and `SECURITY.md:76-80` spells out the
danger: if request data ever chose an include path, a visitor could ask for
`?page=../../config/config` and read your database password. That attack class is **LFI** (Local
File Inclusion). The defence is one line: `in_array($page, self::PAGES, true)`.

**(b) Output buffering (`:45-47`).** The page view renders **first** into `$content`, then the
shell is included. That order lets a page view set a variable the `<head>` needs (see §4.6).

**(c) `extract($data, EXTR_SKIP)` (`:38`).** `extract()` turns array keys into local variables:
`['sj_slug' => 'kg']` becomes `$sj_slug = 'kg';`. That is why a view just writes `$sj_academy` —
plain variables, no objects to learn. `EXTR_SKIP` means "if that variable already exists, leave it
alone", so a data key can never clobber `$page`, `$views` or `$content`. Because `include`
inherits the calling scope, both the page view **and** the shell see these variables.

**(d) One clickjacking guard (`:41-43`).** In live-edit mode it sends `X-Frame-Options: DENY` so
the editable page cannot be embedded in someone else's site.

### 4.6 Layers 5–7 — page view, shell, partials

`views/shell.php` (99 lines) is the single outer document. It fills in defaults for anything the
controller omitted — `$styles`, `$scripts`, `$bodyClass` at `views/shell.php:6-8` (so `['about']`
becomes `/css/about.css`) — emits `<head>` (`:23-71`), then the chrome around the content:

```php
<?php include $__p . '/navbar.php'; ?>                          <!-- shell.php:77 -->
<?= $content ?>                                                 <!-- :80 -->
<?php if ($showJumbotron) include $__p . '/jumbotron.php'; ?>   <!-- :82 -->
<?php include $__p . '/footer.php'; ?>                          <!-- :83 -->
```

Line 80 is the seam; everything above and below is written once for all 42 URLs. The
**buffer-first order** pays off in `views/pages/academy.php:13-15`, where the view builds a
`$sjHeadCss` string (the per-page `.bg-1` background image) that `views/shell.php:61-65` prints
inside `<head>` — possible only because `extract()` put both files in the same variable scope.

**Partials** (`views/partials/`) are the shared chrome, rebuilt as *fragments*: no `<!DOCTYPE>`,
no `<head>`, no `<body>`. `views/partials/navbar.php` starts straight at
`<link rel="stylesheet" …>` then `<nav class="navbar …">` (`views/partials/navbar.php:8-10`),
where the same file at `6bd0d11` opened with a full `<!DOCTYPE html>` and
`<title>Responsive Navigation Bar</title>`. That change collapsed eleven nested documents into one.

### 4.7 One template, many URLs

Counted from the controllers themselves:

| Template | URLs served | How the controller distinguishes them |
|---|---|---|
| `views/pages/academy.php` | **18** (`tamilacademy.php`, `englishacademy.php`, `mathsacademy.php`, `band.php`, `ncc.php`, `artandexpo.php`, …) | `'sj_slug' => 'tamilacademy'` + the row from `repo_academy($slug)` |
| `views/pages/section.php` | **4** (`kg.php`, `primary.php`, `highschl.php`, `highsec.php`) | `'sj_slug' => 'kg'` + `repo_section($slug)` |
| `views/pages/album.php` | **10** (`gal-annual.php`, `gal-sports.php`, `gal-teacher.php`, …) | `'sj_slug' => 'gal-annual'` + `repo_album($slug, is_edit())` |

The **slug** (a short URL-safe name like `tamilacademy`) is a **code literal typed into the
controller**, never read from `$_GET`. The view uses it for per-page details — unique element ids
and per-page classes:

```php
<div id="<?= e($sj_slug) ?>Carousel" class="carousel slide carousel-fade" …>  <!-- academy.php:28 -->
<section class="<?= $sj_slug === 'kg' ? 'kg-carousel ' : 'abt-carousel ' ?>">  <!-- section.php:13 -->
```

Thirty-two URLs, three template files. Fixing the academy carousel is one edit.

**The honest exception to "no queries in views":** the *chrome* reads site-wide settings directly
— `views/partials/footer.php:4-11` and `views/partials/contact.php:8-12` call `repo_setting(...)`,
and `views/shell.php:17` calls `repo_seo($sjSlug)`. `repo_setting` is free after the first call:
`Repo::setting()` loads the whole `settings` table into a `static $cache` once per request
(`src/Content/Repo.php:22-32`). The *content* rule holds — a page's data comes from its controller.

### 4.8 The write side — the admin API front controller

The public site has no front controller. The admin **API** does, using the same whitelist idea.
`public_html/admin/api/index.php` is 26 lines:

```php
$routes = [                                                  // admin/api/index.php:7
    'field' => 'field.php',  'item'   => 'item.php',   'order'  => 'order.php',
    'upload'=> 'upload.php', 'images' => 'images.php', 'settings' => 'settings.php',
    'link'  => 'link.php',   'recrop' => 'recrop.php', 'image'  => 'image.php',
];                                                           // :17

$r = (string)($_GET['r'] ?? '');                             // :19
if (!isset($routes[$r])) { require __DIR__ . '/_bootstrap.php'; api_fail('Unknown route', 404); }
require __DIR__ . '/' . $routes[$r];                         // :25
```

`$_GET['r']` is untrusted, so it is only ever an **array key lookup**. A miss is a 404; a hit
yields a filename a programmer typed. The request never spells a path.

Each impl file starts by requiring `public_html/admin/api/_bootstrap.php`, which enforces, in
order: session (`:4`), JSON + `no-store` headers (`:6-8`), logged in (`:22-24`), forced password
change done (`:27-29`), and **CSRF** on every non-GET request (`:32-37`):

```php
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals(csrf_token(), $token)) { api_fail('Invalid CSRF token', 403); }
}
```

*CSRF* = another site tricking your logged-in browser into submitting a request. The token is a
secret only our own pages know; `hash_equals` compares it without leaking timing.

### 4.9 `src/helpers.php` — the bridge layer

`src/helpers.php` defines ~60 plain global functions, each a one-liner delegating to a class:

```php
function db(): PDO { return Db::pdo(); }                                             // :28
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } // :40
function repo_page(string $slug): ?array { return Repo::page($slug); }               // :228
```

Why keep both? History. The header at `src/helpers.php:1-5` says it plainly: these are *"the
historic names every view, controller and admin endpoint calls."* This code used to live in
`public_html/_libs/` as loose functions. Moving it into `src/` classes *and* editing every call
site across 42 pages, 12 views and 20 partials in one commit would have been huge, un-reviewable
and pixel-risky. Instead the classes moved and the old names became a **thin adapter** — a plug
adapter, not new wiring. `e()` in a view also reads better than `\SJ\Core\Escape::html()`, and
Composer's `"files"` entry guarantees the names exist before any page code runs.

### 4.10 The admin panel is a separate application

`public_html/admin/` has its own entry points (`index.php`, `section.php`, `login.php`,
`logout.php`, `password.php`, `health.php`, `editmode.php`) and its **own** layout,
`public_html/admin/_layout.php` — not `views/shell.php`. It shares only `bootstrap.php`, the
helpers and the repos. That layout starts with a guard, before any HTML exists:

```php
require dirname(__DIR__) . '/bootstrap.php';  sj_session_boot(true);   // admin/_layout.php:5-6
if (function_exists('sj_admin_headers')) { sj_admin_headers(); }       // :7
if (!is_admin()) { header('Location: /admin/login.php'); exit; }       // :8-11
if (!empty($_SESSION['must_change_pw'])) { header('Location: /admin/password.php'); exit; } // :13-16
```

Instead of `Layout::render()`, it exposes `panel_header()` (`admin/_layout.php:77`) and
`panel_footer()` (`:126`), which an admin screen calls around its own markup. The sidebar comes
from a hardcoded map `panel_sections()` (`:20-42`), and `public_html/admin/section.php:5-10`
repeats the whitelist pattern: `if (!isset($sections[$s]) || $s === 'dashboard')` → redirect.

**Why separate?** Different audience (one editor vs. the public), different security posture
(session forced on, strict headers, DENY framing), different look (`/admin/assets/panel.css`),
and one hard rule the public site keeps: **visitors get zero admin bytes.** The public shell
emits admin CSS/JS only inside `if (is_admin())` (`views/shell.php:66-70`, `:92-97`), and the
`ed_*()` helpers return `''` for visitors, so public HTML is byte-identical to a static render
(`src/View/EditAttrs.php:9-11`).

## 5. Why this is the right approach here

The design was not chosen on taste. Six constraints eliminated the alternatives.

| Constraint | Source |
|---|---|
| Shared hosting on MilesWeb, **no SSH**, deploy = upload files via mPanel | `CLAUDE.md` → Prod |
| **"No framework"** is a project rule | `CLAUDE.md` → Stack |
| **Every legacy URL must keep working** | `CLAUDE.md` → URLs |
| **One non-technical editor**, 42 pages | `CLAUDE.md` → Project |
| **Pixel-freeze** — no page may change appearance | `CLAUDE.md` → visual-freeze rule |
| **≤ 12 SQL queries per page** | `CLAUDE.md` → Performance budget |

**Laravel or Symfony.** Real benefits (routing, ORM, migrations, a template language). But
installation assumes a command line on the server; the webroot is a `public/` subfolder you often
cannot point the domain at on mPanel; and `composer install` needs SSH. Its routing model also
replaces `/tamilacademy.php` with `/academy/tamil` — every existing URL, inbound link and search
result breaks unless you hand-write 42 redirects. The learning curve then lands on a maintainer
whose job is a school website, not PHP. Ruled out by the first three constraints alone.

**CodeIgniter** is lighter and would deploy by upload, but still brings its own routing, base
classes and conventions — a second vocabulary on top of PHP — for a site with three genuinely
distinct templates. The cost is real; the benefit here is small.

**A single front controller with URL rewriting** (one `index.php` plus `.htaccess` `RewriteRule`s
mapping the old URLs onto it) is the standard modern shape and was genuinely on the table. Two
things killed it: it makes **every** URL depend on `mod_rewrite` and `AllowOverride` being enabled
on a host we do not control — note how `public_html/.htaccess` wraps every directive in
`<IfModule>` precisely because the host's modules are uncertain — and it turns "file missing" bugs
into "silent 404 from the router", which with **no SSH** is very hard to debug. Real files are
self-evident: the URL *is* the path.

**Keeping flat PHP** was the true baseline, and §2 is the argument against it: 275-line files,
nested documents, no shared template, and no way for a non-programmer to change a sentence.

**What we chose** keeps flat PHP's one good property — one URL, one real file, zero server config
— and adds only the seams that pay for themselves. URLs survive untouched, so the pixel-freeze is
checkable page-by-page against the live site. 18 academy URLs share one template, so a refactor's
visual risk is one file, not 18. Queries sit in the controller where you can count them against the
12-query budget — `public_html/bootstrap.php:29-38` prints `<!-- sj-queries: N -->` in debug
(`docs/perf-baseline.md` records home at **16 queries**, over budget, before the repo work brought
it to 12). And the whitelist plus the `$routes` map give a reviewer two short lists instead of a
routing table — none of it framework knowledge, just `include`, arrays and functions.

## 6. How this scales

**10× the pages (42 → 420).** The template layer holds: a new academy is a 19-line controller plus
a DB row, and the shared view is untouched. What breaks first is `public_html/` itself — 420 sibling
`.php` files with no folder structure, plus a manual whitelist (`src/View/Layout.php:17-28`) a human
keeps in sync. **Next step:** move family URLs into folders (`/academy/tamil.php`) or generate the
thin controllers from the DB at deploy time, and derive the whitelist from `views/pages/*.php` on
disk instead of retyping it.

**10× the traffic.** PHP re-runs everything per request, but the work is small: a few indexed
`SELECT`s and an `include`. Shared hosting hits its **CPU/process cap** long before MySQL struggles,
and images already dominate — `docs/perf-baseline.md` shows them at **90–97 % of page weight**.
**Next step, in order:** confirm OPcache is on; add a full-page HTML cache for anonymous visitors
(write the rendered `$content` to a file, serve it while fresh, bypass entirely when `is_admin()`);
then move images to a CDN. Only then does the hosting plan need to change.

**10× the editors (1 → 10).** This breaks first and worst — nothing here is concurrency-aware.
`admin/api/field.php` writes a field with no "did someone else edit this row?" check, so two editors
on one paragraph = last-writer-wins, silently; and there is one shared admin workflow rather than
per-person accounts with roles. **Next step:** per-user accounts plus roles (the audit trail exists
already — `sj_audit()` at `src/helpers.php:98-101`), then optimistic locking: send the row's
`updated_at` with each save and reject the write if it changed. Neither touches the layers.

**What holds at all three scales:** the seams themselves. Controller/view/shell separation, the
whitelists and the repository layer never become *wrong* — they just acquire more entries.

## 7. Gotchas and mistakes to avoid

1. **Never put a query in a page view.** It hides work from the query budget and makes the view
   impossible to reuse. `views/pages/home.php:5-7` lists its inputs in a comment — that is a
   contract. Add data to the controller's array instead.
2. **Never let request data pick a file path.** Not a template, not an include, not a redirect.
   The two safe patterns are already here: `in_array($page, self::PAGES, true)`
   (`src/View/Layout.php:32`) and `isset($routes[$r])` (`public_html/admin/api/index.php:20`).
3. **A new page view without a whitelist entry** gives a blank page and `Unknown view.` with a
   500. Nothing is broken — you skipped `src/View/Layout.php:17-28`.
4. **Do not echo a variable without `e()`** (`src/helpers.php:40-43`). The only exception is a
   column ending `_html`, already cleaned by `sj_sanitize_html()` on write.
5. **Do not "fix" odd-looking CSS or markup in a view.** The visual-freeze rule in `CLAUDE.md`
   means the *rendered result* must match the baseline, quirks included — `views/pages/home.php:98-102`
   explains why one stylesheet is linked in the body and ends *"Do not move it."*
6. **Order matters in `views/shell.php`.** The `$styles` loop (`:55-57`) runs after the tokens and
   footer sheets; moving a `<link>` changes the cascade and therefore the pixels.
7. **`extract()` is not a free-for-all.** `EXTR_SKIP` (`src/View/Layout.php:38`) means a data key
   named `content`, `page` or `views` is silently ignored. Prefix keys `sj_` like everything else.
8. **`gal-sciexpo.php` is not a page.** It is a 6-line 301 redirect to `/gal-spach.php`
   (`public_html/gal-sciexpo.php:4-6`). Do not "restore" it.
9. **Don't add a second shell.** If a page needs different chrome, add a flag the shell reads
   (`$showJumbotron`, `$showPreloader` — `views/shell.php:9-10`), not a parallel layout.

## 8. Try it yourself

Start the app (Docker only; there is no PHP or MySQL on your machine):

```bash
./run.sh          # → http://localhost:8090/  and  http://localhost:8090/admin/
```

**A. Read one page end to end.** `public_html/tamilacademy.php` (19 lines) →
`views/pages/academy.php` (57 lines) → `views/shell.php`. Trace where `$sj_carousel` comes from
and where it is printed.

**B. Prove "one template, many URLs".**

```bash
grep -l "Layout::render('academy'" public_html/*.php | wc -l   # → 18
grep -l "Layout::render('section'" public_html/*.php | wc -l   # → 4
grep -l "Layout::render('album'"   public_html/*.php | wc -l   # → 10
```

Then open `/tamilacademy.php` and `/yogaacademy.php` — different text, different background,
identical structure; one file produced both.

**C. Feel the whitelist.** In a scratch copy of a controller, change `Layout::render('academy', …)`
to `Layout::render('academyy', …)`: you get a 500 and `Unknown view.` — `src/View/Layout.php:32`
refused. Undo it; do not commit.

**D. See the seam.** View source on any page and count `<!DOCTYPE`: exactly one. Then run
`git show 6bd0d11:public_html/tamilacademy.php | grep -c DOCTYPE` and compare.

**E. Count the queries.** Set `debug` to `true` in `config/config.php`, load the home page, and read
the last line of the source: `<!-- sj-queries: N -->` (from `bootstrap.php:29-38`). Budget: 12.

**F. Compare the two apps.** Open `http://localhost:8090/admin/` (user `admin`, password
`admin123`): different CSS, different chrome, its own layout. Then log out and search the public
page source for `admin.js` — absent, because of `if (is_admin())` at `views/shell.php:92`.

## 9. Where to read more

**In this repo:**

- [`../01-fundamentals.md`](../01-fundamentals.md) — the web, PHP A–Z, SQL, PDO, sessions,
  Composer, Docker. Read first if any word above was new.
- [`../03-what-we-built.md`](../03-what-we-built.md) — Stages A & B: how `_libs/` became `src/`.
- [`../04-stage-c-and-d.md`](../04-stage-c-and-d.md) — Stage C: the nested-documents problem and
  the R1a–R1d conversion. The "before" picture in §2 comes from lines 11–16.
- [`../07-stage-g.md`](../07-stage-g.md) — CSS consolidation; the last legacy directories deleted.
- [`../../../PHASES.md`](../../../PHASES.md) — the roadmap. Rows P4\*, R1a–R1d produced this architecture.
- [`../../../CLAUDE.md`](../../../CLAUDE.md) — the non-negotiable rules: no framework, visual freeze,
  the 12-query budget, SQL and output conventions.

**Outside:**

- [PHP: `include`/`require`](https://www.php.net/manual/en/function.include.php) — what variable
  scope an included file inherits (the basis of §4.5(c)).
- [PHP: Output Control (`ob_start`)](https://www.php.net/manual/en/book.outcontrol.php) — the
  buffering trick behind `Layout::render()`.
- [PSR-4 autoloading standard](https://www.php-fig.org/psr/psr-4/) — the namespace→folder rule in
  `composer.json`.
- [OWASP: Testing for Local File Inclusion](https://owasp.org/www-project-web-security-testing-guide/latest/4-Web_Application_Security_Testing/07-Input_Validation_Testing/11.1-Testing_for_Local_File_Inclusion)
  — the attack the hardcoded whitelist prevents.
