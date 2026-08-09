# Caching and Cache-Busting

> **What you'll learn:** what a *cache* is, why a browser keeps copies of our files, and the two opposite ways
> caching goes wrong. You will learn to read the `Cache-Control` header, understand what `max-age`, `no-cache`,
> `no-store` and `immutable` actually do, and explain the one-line constant (`SJ_ASSET_VER`) that lets us cache
> our CSS for a *year* and still ship a fix the same afternoon.
>
> **Prerequisites:** [`../01-fundamentals.md`](../01-fundamentals.md) (what a request and a response are) and
> [`how-php-serves-a-page.md`](how-php-serves-a-page.md) (the trip from URL to HTML). You need only `echo`,
> constants and `include` from PHP. Every HTTP term is explained the first time it appears.
>
> **Where it lives in our code:** `public_html/bootstrap.php` (the version constant), `public_html/.htaccess`
> (the server rules), `views/shell.php` and `public_html/admin/_layout.php` (where the version is stamped onto
> asset URLs), `public_html/admin/api/_bootstrap.php` (`no-store`), `src/Media/Html.php` (image URL versioning),
> `Dockerfile` (OPcache).

## 1. The one-paragraph version

A **cache** is a saved copy of something, kept close by so you do not have to fetch it again. Your browser has
one. When it downloads `tokens.css` it can keep that file on disk and reuse it on the next page instead of
asking our server again. That is free speed — *if* the browser knows how long the copy stays good. We tell it
with a response header called `Cache-Control`. Our rule has two halves. Files that never change under a given
URL (CSS, JS, images) are cached for **one year**. HTML pages, which change whenever an admin edits content, are
**never** cached. To make "never changes under a given URL" true for CSS and JS, we glue a version number onto
the URL — `tokens.css?v=20260809.2` — and bump it by hand on every deploy. New number, new URL, fresh download.
Old number, cached copy, zero bytes over the wire.

## 2. The problem this solves

There are exactly two ways to get caching wrong, and they are opposites. **Failure 1 — caching too little:** the
browser re-downloads everything on every visit, so pages feel slow, especially on a phone on mobile data.
**Failure 2 — caching too much:** you deploy a fixed stylesheet, reload, and the page is exactly as broken as
before, because the browser is serving yesterday's copy from disk. You cannot fix that from your side; the
visitor has a stale file and no reason to ask for a new one. Caching is the art of choosing which failure you
get, per file. Get it right and you get neither.

### The bug we inherited

The pre-revamp site tried to solve Failure 2 and walked straight into Failure 1. Every stylesheet link looked
like this (baseline commit `6bd0d11`, `public_html/index.php:14`):

```php
<link rel="stylesheet" href="/css/index.css?v=<?php echo time(); ?>">
```

`time()` returns the current time as a number of seconds. Read that line slowly. **PHP runs on every request.**
A visitor at 10:00:00 gets `index.css?v=1785613550`; one second later they get `index.css?v=1785613551`. To a
browser those are **two different URLs**. A cache is a lookup table keyed by URL, so nothing ever hits. The
browser dutifully saves each copy and then never asks for that URL again in its life. Every page view
re-downloaded every stylesheet. The performance baseline caught it in writing — `docs/perf-baseline.md:37`:

> **`?v=<timestamp>` on every stylesheet** … **defeats browser caching**

It was not one file: `time()` appeared in 30+ page files at that commit. The home page was **10,344 KB and took
~14 seconds** to load (`docs/perf-baseline.md:13`).

## 3. How it works in general

When our server sends a file it also sends **headers** — short `Name: value` lines the browser reads but never
shows. Caching is decided almost entirely by these.

### `Cache-Control`

The main one. It carries comma-separated *directives*:

| Directive | Plain English |
|---|---|
| `max-age=31536000` | "Good for 31,536,000 seconds (365 days). Reuse it freely until then." |
| `no-cache` | **Not** "don't cache". It means "you may store it, but ask me before every reuse." |
| `no-store` | "Do not write this to disk or memory at all. Ever." The strict one. |
| `public` | Any cache may store it, including shared ones (a proxy, a CDN). |
| `private` | Only this one user's browser may store it. Never a shared cache. |
| `immutable` | "While it is fresh, this file will never change — do not even revalidate on reload." |

The `no-cache` / `no-store` confusion trips up nearly everyone. `no-cache` still keeps a copy; it just forces a
check first. `no-store` keeps nothing.

### `Expires`

The older header. It gives an absolute date instead of a duration: `Expires: Mon, 09 Aug 2027 04:11:16 GMT`. If
both are present, `Cache-Control: max-age` wins in any modern browser. We send both because it costs nothing and
old proxies understand `Expires`.

### `ETag`, `Last-Modified` and the 304

When a cached copy goes stale the browser can ask politely instead of re-downloading blindly. **`Last-Modified`**
is a timestamp of the file's last change. **`ETag`** (entity tag) is a short fingerprint of the file's current
contents; Apache builds ours from size and modification time, e.g. `"391-65891921263bd"`. The browser sends that
back in a **conditional request** (`If-None-Match:` / `If-Modified-Since:`). If nothing changed, the server
replies **`304 Not Modified`** — a header-only response with an empty body. That saves the file's bytes but
still costs a full network round-trip; forty round-trips of 304s is still a visibly slow page on mobile. That is
the key insight behind our whole approach:

> **A cache hit with zero requests beats a 304 on every request.**
> So: make the file *immutable*, and change the *URL* when the content changes.

Apache generates `ETag` and `Last-Modified` automatically for static files. We never write them ourselves; we
only set `Cache-Control`.

## 4. How we use it — every place in this codebase

### 4.1 `SJ_ASSET_VER` — one constant, bumped per deploy

`public_html/bootstrap.php:11-16`:

```php
// Static-asset cache-busting version. Bump this ONE line per deploy instead of
// the old `?v=time()` (which re-downloaded every asset on every request).
// Combined with the long-cache .htaccess rules, repeat visits re-fetch nothing.
if (!defined('SJ_ASSET_VER')) {
    define('SJ_ASSET_VER', '20260809.2');
}
```

`define()` creates a **constant** — a value fixed for the whole request. `bootstrap.php` is required by every
page, every admin screen and the CLI seeder, so it exists everywhere. The format is date-plus-counter:
`20260809.2` is the second bump on 9 Aug 2026. It is stamped onto every asset URL, e.g. in `views/shell.php`:

```php
<link rel="stylesheet" href="/css/tokens.css?v=<?php echo SJ_ASSET_VER; ?>">   // :53
<script src="/js/site.js?v=<?php echo SJ_ASSET_VER; ?>"></script>              // :88
```

Also on Bootstrap (`views/shell.php:44`, `:86`), the per-page CSS loop (`:56`), the admin overlay assets
(`:68`, `:69`, `:94`–`:96`), a partial's own stylesheet (`views/partials/testimonial.php:9`), and the whole
standalone admin panel (`public_html/admin/_layout.php:89`, `:90`, `:131`–`:133`). The pattern has a name worth
remembering: **immutable file + versioned URL**. We are not asking "has this changed?" 47 times per page. We
declare "this URL's bytes are frozen forever", and when we want different bytes we ship a different URL.

### 4.2 The server rules — `public_html/.htaccess`

`.htaccess` is a per-directory Apache config file, re-read on each request, so changes apply with no restart.
Every block is wrapped in `<IfModule>` so a host missing that module degrades quietly instead of returning a 500.

**Compression** (`public_html/.htaccess:27-31`):

```apache
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/css text/xml \
    application/javascript application/x-javascript text/javascript \
    application/json image/svg+xml application/xml
</IfModule>
```

`mod_deflate` gzips text on the way out. Gzip is a compression format; text shrinks a lot because it repeats
itself. Measured at F1 (commit `54d545f`): Bootstrap's CSS went **227 KB → 31 KB** (86% smaller), a page's HTML
**17.6 KB → 5.9 KB**. Note the list is by **MIME type** — the content-type label Apache attaches to a response —
not by file extension. That subtlety cost us. `text/javascript` was **missing** until the F4 audit: Apache
labels `.js` files `text/javascript`, not `application/javascript`, so the 78 KB Bootstrap bundle shipped
**uncompressed to every visitor** for several stages. Commit `6b077ed` fixed it in one word; reproduce the
numbers yourself in §8.

**Freshness lifetimes** (`public_html/.htaccess:35-46`) — `mod_expires` emits `Expires` and a matching `max-age`.
CSS, JS, JPEG, PNG, WebP, GIF, ICO and WOFF2 fonts each get `"access plus 1 year"`; `text/html` gets `"access
plus 0 seconds"`.

**The explicit `Cache-Control`** (`public_html/.htaccess:47-56`):

```apache
<IfModule mod_headers.c>
  <FilesMatch "\.(css|js|jpg|jpeg|png|webp|gif|ico|woff2|svg)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
  <FilesMatch "\.php$">
    Header set Cache-Control "no-cache"
  </FilesMatch>
</IfModule>
```

Read both halves as one sentence: *static assets for a year and don't even ask; PHP output never reused without
checking.* The second half is why an admin's edit appears on the public page the moment they hit save — the file
says so at `public_html/.htaccess:52`: "Never cache HTML (PHP output) — edits must show immediately." We chose
`no-cache`, not `no-store`, for HTML: the page may sit in the browser cache, the browser just has to revalidate,
and with Apache's `ETag` an unchanged page returns a cheap 304.

### 4.3 Images carry their own version

CSS is versioned globally. Images are versioned **individually**, because an admin can re-crop one photo without
a deploy. The `images` table has a counter column (`database/schema.sql:80`):
`version SMALLINT UNSIGNED NOT NULL DEFAULT 1`. `SJ\Media\Html` reads it straight into the URL
(`src/Media/Html.php:34` and `:39`):

```php
return '/media/' . $id . '/' . $presetKey . '.jpg?v=' . (int)($img['version'] ?? 1);
```

The WebP variant inside `<picture>` gets the same treatment (`src/Media/Html.php:88`, `:97`). When an admin
re-crops, one statement increments the counter (`public_html/admin/api/recrop.php:40`):

```php
db()->prepare('UPDATE images SET crop_rect = ?, version = version + 1 WHERE id = ?')
```

`?v=1` becomes `?v=2`. New URL. Every browser refetches that one photo, and only that photo. Re-running the legacy
rendition backfill has the same effect (`DEPLOY.md:99-102`).

### 4.4 `no-store` on the admin APIs

`public_html/admin/api/_bootstrap.php:6-8` — the shared guard every admin endpoint requires first:

```php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
sj_admin_headers(); // XFO/nosniff/CSP/Referrer-Policy (SEC-13/14)
```

Why the strictest directive? These are **authenticated JSON** responses — data returned only because *this*
logged-in admin asked for it. Three reasons not to store them: **shared machines** (the school office computer
has several users, and a cached JSON body sits on disk after logout); **correctness** (admin screens must show
the current database, not a snapshot that still lists a deleted item); and **blast radius** (a CSRF token or
private field could land in a cache file, or in a shared proxy cache on a school network). Rule of thumb: **if a
response depends on who you are, it is `no-store`.**

### 4.5 OPcache — a cache the browser cannot see

Everything above caches *responses*. OPcache caches something else entirely. Normally PHP re-reads and
re-compiles your `.php` files into bytecode on **every request** — the same parsing work, thousands of times a
day. OPcache keeps the compiled bytecode in memory and skips the recompile. It is enabled at `Dockerfile:7` and
configured at `Dockerfile:26-29` with `opcache.enable=1`, `opcache.enable_cli=0`,
`opcache.validate_timestamps=1` and `opcache.revalidate_freq=0` — that last pair means "check the file's
modification time on every request", so editing a file in dev takes effect instantly. In production mPanel
provides OPcache, and our health endpoint verifies it (`public_html/admin/health.php:41`):

```php
$check('opcache', (bool) ini_get('opcache.enable'));
```

OPcache **sends no headers** and changes no URL. It is invisible to the browser. It makes the server do less
work per request; it does not stop the request happening. Different problem, different layer — do not confuse
the two.

### 4.6 Per-request PHP caches (a different thing entirely)

Several classes hold a `static` variable so a value is computed once **per request**:

| Where | What it holds |
|---|---|
| `src/Core/Config.php:13` | `private static ?array $cfg = null;` — the config file, read once |
| `src/Core/Db.php:38` | `private static ?PDO $pdo = null;` — one DB connection per request |
| `src/Content/Repo.php:22-32` | the whole `settings` table in one query, then served from memory |
| `src/Media/Pipeline.php:40-59` and `:22-32` | every `image_renditions` row in **one** query; all `image_presets` rows |
| `src/Content/Registry.php:17` | the editable-field registry map |

The renditions cache exists for a concrete reason (`src/Media/Pipeline.php:36-38`): an album page renders ~250
photos, and one query per photo would blow the ≤12-query budget in `CLAUDE.md`. **These are not HTTP caches.** A
PHP process starts, serves one request, and exits. Every `static` resets to `null`. Nothing survives to the next
visitor. There is no staleness risk at all — which is why we use them freely and why they need no invalidation
logic.

## 5. Why this is the right approach here

Three real alternatives exist. Each was ruled out by a real constraint.

| Alternative | What it gives | Why not here |
|---|---|---|
| **Content-hash filenames** (`app.a1b2c3.css`, from Webpack/Vite) | Perfect per-file busting: edit one file, only that file refetches | Needs a **build step** and a Node toolchain. Production is MilesWeb shared hosting — no SSH, no Docker, no Composer on the server (`DEPLOY.md:3-5`); deploy is *upload the files*. `CLAUDE.md` fixes the stack as plain PHP, no framework. |
| **ETag-only revalidation** (drop `max-age`, let 304s do the work) | Always fresh, never stale; no version discipline needed | Costs one round-trip **per asset, per page**. A page loads 47 resources (`docs/perf-baseline.md:72`). On slow 4G that is the whole latency budget spent asking "still the same?" 47 times. |
| **A full-page cache** (store rendered HTML, serve it without running PHP) | Biggest raw win — PHP barely runs | The site has **one editor** who must see her change immediately after clicking save. A page cache means an invalidation layer, and every invalidation bug looks like "the website is broken". TTFB is already 0.1–0.2 s and `docs/perf-baseline.md:38` states plainly: "**Server is not the bottleneck.**" |

There is also no Redis or Memcached here (in-memory data caches that run as separate server processes), for the
same shared-hosting reason: we cannot run background processes. Our approach needs **no tooling, no build, no
invalidation logic and no new dependency** — one constant and eleven lines of `.htaccess` — and buys the same
repeat-visit result as content hashing. Its single weakness: it relies on a human remembering to bump. See §7.

## 6. How this scales

At 10× traffic nothing above breaks: repeat visitors already cost us zero asset bytes. The pressure moves to
first-time visitors and raw origin bandwidth.

1. **A CDN in front.** A *Content Delivery Network* is a network of servers worldwide that keep copies of your
   files near the visitor. Cloudflare's free tier is described as a deploy-day option
   (`docs/learn/08-stage-h.md:338-340`, `PHASES.md:144` and `:161`) needing a DNS change the school makes at
   launch. Our headers are already CDN-ready: `public, max-age=31536000, immutable` is exactly what a CDN wants,
   and `no-cache` on `.php` keeps dynamic pages off the edge. The one setting to get right is a **bypass rule
   for `/admin*`** (`PHASES.md:161`) so nothing authenticated is cached at the edge.
2. **Brotli and HTTP/2 on the real host.** Brotli is a newer compression format that beats gzip on text. HTTP/2
   sends many files down one connection, which makes our 47-request count "largely moot"
   (`docs/perf-baseline.md:104`). Both exist on the production LiteSpeed host and cannot be demonstrated on the
   dev box.
3. **When one global version stops being enough.** Today a bump expires *every* asset — a one-word CSS fix makes
   every visitor refetch all CSS and JS. At our size that is a few hundred KB, once, per deploy. If assets grew
   to megabytes, or deploys became daily, a **per-file content hash** would pay for itself: change one file,
   refetch one file. That only beats the tooling cost once the waste is large. It is not yet.

## 7. Gotchas and mistakes to avoid

**1. Forgetting the bump. The big one.** Edit a CSS or JS file *after* a version bump and the URL does not
change — so nobody downloads your fix. `docs/learn/08-stage-h.md:110-111` records it as a repeat offence:

> a lesson this stage taught us *three* times: a stylesheet edited after its
> version bump is a stylesheet nobody downloads

It bit us live in Stage E too (`docs/learn/05-stage-e.md:112-115`): changed admin JavaScript "**didn't load** at
first — the browser was still using its year-long cached copy, exactly as we told it to in F1". The git history
shows the discipline it forced: `20260807` → `20260808` → `.2` → `.3` → `.4` → `.5` → `.8` → `20260809.2`, one
bump per phase that touched an asset. **Rule: any CSS/JS edit made after a bump needs another bump.**

**2. An unversioned asset tag.** Compare `views/shell.php:88` with `:90`:

```php
<script src="/js/site.js?v=<?php echo SJ_ASSET_VER; ?>"></script>   // :88 — versioned
<script src="/js/<?= e($js) ?>.js"></script>                       // :90 — NOT versioned
```

Per-page scripts go out with **no `?v=`** while still matching the `\.(css|js|…)$` rule and receiving `max-age=31536000, immutable` — pinned in every visitor's browser for a year with no way to bust them short of renaming the file. Whenever you add an asset tag, check that `?v=<?php echo SJ_ASSET_VER; ?>` is on it.

**3. Caching an HTML page that contains a CSRF token.** Our pages embed one (`views/shell.php:67`,
`public_html/admin/_layout.php:88`): `<meta name="sj-csrf" content="<?= e(csrf_token()) ?>">`. A CSRF token is
per-session. If HTML were cacheable, a stale page would carry a dead token and every save would fail with
"Invalid CSRF token" — or worse, a shared cache would hand one user's token to another. That is a second,
independent reason for `no-cache` on `.php`, and the reason you must never "optimise" it to `max-age`.

**4. `no-cache` vs `no-store`.** Say them out loud until they stick. `no-cache` = *store it, but revalidate
before every reuse*. `no-store` = *never write it down*. We use `no-cache` for public HTML (revalidation is
cheap and a 304 is a win) and `no-store` for authenticated admin JSON
(`public_html/admin/api/_bootstrap.php:7`). Swapping them silently either wastes bandwidth or leaves private
data on a shared disk.

**5. Testing with devtools "Disable cache" ticked.** That checkbox makes the browser act as if it has no cache:
every reload refetches. Leave it on and you will conclude caching is broken; forget it is off and you may
conclude a stale file is a code bug. Know which mode you are in **before** drawing a conclusion.

**6. A one-year `max-age` on something you intend to hot-fix.** `immutable` means what it says: once a URL is
served with it you cannot recall it. Only apply it to a URL you can change — for us, one carrying `?v=`. Never
add a new file type to the `<FilesMatch>` list at `public_html/.htaccess:49` unless every URL of that type is
versioned.

## 8. Try it yourself

Start the stack with `./run.sh`, then run these read-only commands. `curl -I` fetches headers only.

**a) A versioned CSS file — the long cache.** `curl -sS -I http://localhost:8090/css/tokens.css` — real output
from this box on 2026-08-09 (your `ETag` and dates will differ):

```
HTTP/1.1 200 OK
Last-Modified: Sat, 08 Aug 2026 23:35:04 GMT
ETag: "391-65891921263bd"
Cache-Control: public, max-age=31536000, immutable
Expires: Mon, 09 Aug 2027 04:11:16 GMT
Vary: Accept-Encoding
```

`Expires` is one year out. `Vary: Accept-Encoding` tells caches to keep the gzipped and plain copies apart.

**b) An HTML page — never cached.** `curl -sS -I http://localhost:8090/about.php` → `Cache-Control: no-cache`, and an `Expires` in the same second as the request.

**c) Prove the conditional request.** Paste the `ETag` from (a) back in:

```bash
curl -sS -I -H 'If-None-Match: "391-65891921263bd"' http://localhost:8090/css/tokens.css
```

→ `HTTP/1.1 304 Not Modified`, with no body. That is a saved download.

**d) Prove the gzip fix from §4.2.** Fetch the same file twice:

```bash
curl -sS -I http://localhost:8090/assets/vendor/bootstrap-5.3.3/js/bootstrap.bundle.min.js
curl -sS -I -H 'Accept-Encoding: gzip' \
  http://localhost:8090/assets/vendor/bootstrap-5.3.3/js/bootstrap.bundle.min.js
```

`Content-Length: 80721` becomes `Content-Length: 23799` with `Content-Encoding: gzip`. Note
`Content-Type: text/javascript` — the exact label that had to be added to the `mod_deflate` list.

**e) See the cache work in the browser.** Open `http://localhost:8090/index.php`, open devtools → **Network**,
and make sure **"Disable cache" is unticked**. Reload once, then reload again. In the Size column the CSS and JS
rows now read **"(disk cache)"** — no bytes transferred.

**f) Watch a bump take effect.** In `public_html/bootstrap.php:15`, change `'20260809.2'` to `'20260809.3'` (use
your editor, never the shell — see `CLAUDE.md`). Reload and view source: every `?v=` in the page changed, and
the Network panel shows real downloads again instead of "(disk cache)". Change it back when you are done.

## 9. Where to read more

**In this repo**

- [`../04-stage-c-and-d.md`](../04-stage-c-and-d.md) — the F1 section (lines 150-167): the three delivery fixes
  as shipped, with the measured gzip numbers.
- [`../08-stage-h.md`](../08-stage-h.md) — image renditions and `images.version` as a cache-buster (lines
  106-111, including the "three times" warning); the CDN note at 334-340.
- [`../../perf-baseline.md`](../../perf-baseline.md) — the F0 "before" numbers, the `?v=<timestamp>` finding
  (line 37), and the F4 "after" table with the remaining levers.
- [`../../../DEPLOY.md`](../../../DEPLOY.md) — §1 "Every release" step 2 is the bump; §6 covers rendition
  versioning. *(Heads-up: step 2 still names `public_html/_libs/load.php`; that file was removed in R2 and the
  constant now lives at `public_html/bootstrap.php:14-16`.)*
- [`the-repository-pattern.md`](the-repository-pattern.md) — more on the per-request `static` caches from §4.6.

**Outside**

- [MDN — HTTP caching](https://developer.mozilla.org/en-US/docs/Web/HTTP/Caching) and
  [MDN — `Cache-Control`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Cache-Control) — the
  reference for every directive in §3, and the full directive list.
- [web.dev — HTTP cache](https://web.dev/articles/http-cache) — the "immutable file + versioned URL" pattern
  explained with pictures.
- [Apache `mod_expires`](https://httpd.apache.org/docs/2.4/mod/mod_expires.html) and
  [`mod_deflate`](https://httpd.apache.org/docs/2.4/mod/mod_deflate.html) — the two modules behind
  `public_html/.htaccess`.
