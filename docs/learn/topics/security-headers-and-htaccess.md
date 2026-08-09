# Security Headers, CSP and .htaccess

> **What you'll learn:** what an HTTP response header is, what each security header we send actually
> stops, how to read our Content-Security-Policy directive by directive, what an `.htaccess` file is,
> and every rule in our two `.htaccess` files.
>
> **Prerequisites:** you can read basic PHP (`function`, `if`, `header()`), and you know a browser asks
> a server for a page. Nothing else — every security term is explained the first time it appears.
>
> **Where it lives in our code:** `src/Admin/Auth.php` (the `headers()` method), `src/helpers.php`
> (the `sj_admin_headers()` wrapper), `src/View/Layout.php`, `public_html/.htaccess`,
> `public_html/media/.htaccess`, `Dockerfile`.

---

## 1. The one-paragraph version

When a browser asks our server for a page, the server sends back two things: a short list of
**headers** (instructions *about* the page) and the **body** (the actual HTML). We use a handful of
headers to tell the browser "refuse to do these dangerous things". Admin pages get theirs from PHP, in
`Auth::headers()` (`src/Admin/Auth.php:90`), because production is shared hosting where we cannot edit
the web-server configuration. Everything else — public pages, images, CSS — gets its headers plus some
access rules from a file called `.htaccess`, which Apache reads out of the folder itself. Headers are
**defence in depth**: an extra wall behind the real controls (escaping, CSRF tokens, login checks),
never a replacement for them.

---

## 2. The problem this solves

Open `http://localhost:8090/admin/login.php` and the server replies with something like:

```
HTTP/1.1 200 OK                                    ← status line
Content-Type: text/html; charset=UTF-8             ← header
X-Frame-Options: DENY                              ← header
X-Content-Type-Options: nosniff                    ← header
                                                   ← ONE blank line
<!DOCTYPE html>                                    ← the body starts here
```

Above the blank line is the **headers**; below it is the **body**. The body is the content you see. A
header is metadata — an instruction the browser reads *before* it looks at the content. That ordering
is the whole point: by the time the browser draws the page, it already knows the rules. It is also why
headers must be sent **before** any output (§7, gotcha 1).

Why that matters: a browser is very obedient. By default it will happily put our admin panel inside an
invisible frame on someone else's site, guess that a `.txt` file is really JavaScript and run it, tell
a third-party site the full URL a visitor came from, and execute a script from any domain. None of
that is a bug — it is the default behaviour of the web. Security headers are how we **opt out**, one
dangerous default at a time.

---

## 3. How it works in general

Headers can be set in two places:

| Where | How | Applies to |
|---|---|---|
| Web-server config / `.htaccess` | `Header always set X-Content-Type-Options "nosniff"` | Every file in that folder — images, CSS, PHP, everything |
| PHP code | `header('X-Frame-Options: DENY');` | Only the PHP script that runs that line |

Both produce identical bytes on the wire; the browser cannot tell which made them. One hard PHP rule:
**once any output has been sent, headers can no longer be added.** A single stray space before `<?php`
counts as output. PHP offers `headers_sent()` to check, and we use it (§4.7).

---

## 4. How we use it — every place in this codebase

### 4.1 `X-Frame-Options: DENY` — stops clickjacking

`src/Admin/Auth.php:95` — `\header('X-Frame-Options: DENY');`
**The attack, as a story.** An `<iframe>` embeds one page inside another. An attacker builds
`free-prizes.example` with a big "Click here to win!" button. Behind it — invisible, at `opacity: 0` —
they load *our* admin panel in an iframe, positioned so our "Delete album" button sits exactly under
their button. You are already logged in, so your browser sends your session cookie and the real panel
loads. You click "win". You actually clicked "Delete album". That is **clickjacking**: your click is
hijacked. `X-Frame-Options: DENY` tells the browser never to render this page in a frame, on any site,
including our own. The iframe comes up blank and the attack dies.

### 4.2 `X-Content-Type-Options: nosniff` — stops MIME sniffing

Sent from `src/Admin/Auth.php:96` and `public_html/.htaccess:18`.
A **MIME type** is the label saying what a file is — `text/html`, `image/jpeg`,
`application/javascript` — sent in the `Content-Type` header. Historically browsers did not trust that
label: if a file was declared `text/plain` but its bytes looked like HTML, the browser would "sniff"
the content and treat it as HTML anyway. Helpful, right up until an attacker uploads a file we serve
as a harmless type and the browser decides on its own to execute it. `nosniff` means *believe the
`Content-Type` header, never guess*. We send it from both PHP (admin) and `.htaccess` (everything
else), so it covers uploaded media too.

### 4.3 `Referrer-Policy: strict-origin-when-cross-origin` — stops URL leakage

Sent from `src/Admin/Auth.php:97` and `public_html/.htaccess:19`.
Click a link from page A to page B and your browser tells site B where you came from, in a header
called `Referer` (yes, misspelled — it shipped that way in 1996). By default that is the **full URL**,
path and query string included. A URL like `/admin/section.php?entity=staff&id=42` tells an outside
site that we have an admin panel, what our internal entity names are, and which record was open.

| Situation | What gets sent |
|---|---|
| Link inside our own site | Full URL |
| Link to a different site | Only the origin — `https://stjosephsondipudur.com` |
| HTTPS page → HTTP site | Nothing at all (a downgrade, so send zero) |

### 4.4 `Content-Security-Policy` — the strongest one

`src/Admin/Auth.php:98-102`, verbatim:

```php
\header(
    "Content-Security-Policy: default-src 'self'; " .
    "img-src 'self' data:; style-src 'self' 'unsafe-inline'; " .
    "script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
);
```

A **Content-Security-Policy** (CSP) is a whitelist of where the page may load things from, written as
`directive value; directive value; …`. `'self'` means "our own origin only — same scheme, same domain,
same port".

| Directive | Our value | What it means |
|---|---|---|
| `default-src` | `'self'` | Fallback for every resource type not listed below. Everything must come from our own site. |
| `img-src` | `'self' data:` | Images from our site, **plus** `data:` URIs (a whole image encoded into the URL string). |
| `style-src` | `'self' 'unsafe-inline'` | CSS files from our site, **plus** CSS written directly in the HTML. |
| `script-src` | `'self'` | JavaScript **only** from our own `.js` files. No inline `<script>`, no CDN, no `eval()`. |
| `frame-ancestors` | `'none'` | Nobody may iframe this page — the modern replacement for `X-Frame-Options`. We send both, because old browsers only understand the old one. |
| `base-uri` | `'self'` | Blocks an injected `<base href="https://evil.example/">`, which would silently repoint every relative URL on the page. |
| `form-action` | `'self'` | Forms may only submit to our own site — an injected form cannot post your data elsewhere. |

**Why `img-src` needs `data:`.** The image cropper's stylesheet embeds its checkerboard background as
base64 — `public_html/admin/assets/cropper/cropper.min.css:9` contains
`background-image:url("data:image/png;base64,iVBORw0…")`. Without `data:`, the cropper renders with a
missing background.

**Why `style-src` has `'unsafe-inline'`, honestly.** Two admin pages carry a real `<style>` block in
their HTML: `public_html/admin/login.php:67` and `public_html/admin/password.php:57`. Both are
standalone pages that deliberately skip the shared panel CSS, so dropping `'unsafe-inline'` today
would leave the login page unstyled. Is that dangerous? Much less than the same allowance for scripts.
Injected **script** is total takeover: it reads the DOM, steals the CSRF token, calls our APIs as you,
exfiltrates everything — no ceiling on the damage. Injected **style** is layout manipulation:
realistically cosmetic clickjacking (moving or hiding a button) plus some historic
attribute-selector tricks for reading form values, most of which modern browsers closed. It cannot
execute code or call our API. So `style-src 'unsafe-inline'` is a known, bounded compromise;
`script-src 'unsafe-inline'` would be a hole, and we do not have it.

**Why `script-src 'self'` was even possible.** Because the admin panel has *zero* inline `<script>`
blocks. Every script is an external file — `public_html/admin/_layout.php:131-133` loads
`cropper.min.js`, `sj-ui.js` and `panel.js` as `<script src="/admin/assets/…?v=<?= SJ_ASSET_VER ?>"
defer>`, and `public_html/admin/password.php:123` and `public_html/admin/section.php:723` follow the
same pattern. That discipline is what buys the strict policy.

**Why public pages do not get this CSP.** They still have inline scripts (e.g.
`views/pages/home.php:105`) and off-origin ones — `views/partials/contact.php:14` loads Google
reCAPTCHA, lines 112-113 load Ionicons from unpkg. A strict CSP would break them today.
`SECURITY.md:91` records the plan: report-only CSP first, tighten as inline code is extracted.

### 4.5 `Permissions-Policy` — turns off device APIs

`public_html/.htaccess:20` —
`Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"`.

Browsers let pages ask for the camera, microphone, or the visitor's location. A school website needs
none of them. Empty parentheses `()` mean "allowed for nobody" — not even our own page. So if script
is ever injected, it cannot even raise the "allow camera?" prompt. This lives in `.htaccess` because
it is correct for the whole site.

### 4.6 `Cache-Control: no-store` on API responses

`public_html/admin/api/_bootstrap.php:6-7` — `header('Content-Type: application/json; charset=utf-8');`
then `header('Cache-Control: no-store');`. Also `public_html/admin/health.php:9` and
`public_html/api/contact.php:27`. A **cache** is anything that keeps a copy of a response to reuse:
the browser's disk cache, a proxy, a CDN. `no-store` is the strictest instruction — *do not write this
anywhere*. API responses carry admin data, and the contact endpoint carries visitor-submitted data; a
copy left on a shared library computer would be recoverable. This is stronger than `no-cache`, which
permits storing a copy as long as it is revalidated before reuse — `.htaccess:54` uses `no-cache` for
HTML, where the goal is freshness, not secrecy.

### 4.7 Why we emit headers from PHP, not only from Apache

The method's own docblock answers it — `src/Admin/Auth.php:88` reads "PHP-emitted so it works
regardless of Apache/LiteSpeed module availability." Its first statement,
`if (\headers_sent()) { return; }` at `src/Admin/Auth.php:92-94`, is the second half of the answer.

1. **Portability.** Setting headers from Apache config needs the `mod_headers` module loaded.
   Production is MilesWeb shared hosting on **mPanel with no SSH** (`DEPLOY.md:3`), running
   **LiteSpeed** — an Apache-compatible server that *mostly* honours `.htaccess` but is not obliged
   to. We cannot log in and enable a module. PHP's `header()` has no such dependency: if PHP runs at
   all, the header goes out. That is why security-critical admin headers live in PHP and the
   nice-to-haves live in `.htaccess`.
2. **The `headers_sent()` guard.** If output has already begun, `header()` raises a PHP warning that
   gets printed into the page — ugly, and on a misconfigured host it leaks the file path. Returning
   early makes the function safe to call from anywhere.

### 4.8 Who calls it — every call site

`src/helpers.php:74-77` is a thin global wrapper — `function sj_admin_headers(): void { Auth::headers(); }`
— so page files need no namespace. Every caller in the repo:

| File:line | Context | Notes |
|---|---|---|
| `public_html/admin/login.php:4` | Login form | Unguarded call — the file loads helpers first |
| `public_html/admin/_layout.php:7` | Every panel screen | Wrapped in `function_exists()` |
| `public_html/admin/password.php:7` | Forced password change | Standalone page, not via `_layout.php` |
| `public_html/admin/health.php:7` | Deploy smoke-check JSON | Also sends `Cache-Control: no-store` |
| `public_html/admin/api/_bootstrap.php:8` | **Every** admin API endpoint | One include covers all of them |

`public_html/api/contact.php` is the deliberate exception: it is a *public* endpoint, so it sets
`nosniff` and `no-store` by hand (lines 26-27) and skips the admin CSP.

### 4.9 The public-page `X-Frame-Options`, only in edit mode

`src/View/Layout.php:40-43`:

```php
// Live-edit pages must never be framed (O1 / SEC-14).
if (\function_exists('is_edit') && is_edit() && !\headers_sent()) {
    \header('X-Frame-Options: DENY');
}
```

**Edit mode** is our live-editing overlay: a logged-in admin toggles it on and the normal public page
becomes clickable-to-edit. In that state an ordinary public URL carries admin powers — click a
heading, type, it saves to the database. That makes it exactly as clickjackable as the panel, so it
gets the same frame ban. A plain visitor gets **no** `X-Frame-Options` on public pages, which is
correct: `SECURITY.md:95` scopes the requirement to edit-mode pages.

### 4.10 `public_html/.htaccess`, block by block

An `.htaccess` file is a per-directory configuration file. Apache reads it on **every request** for a
file in that folder, and inherits it into subfolders. Its rules apply to that subtree only.

| Lines | Rule | What it does |
|---|---|---|
| 6 | `Options -Indexes` | With indexes on, requesting a folder with no `index.php` returns a **file listing**. Off means 403. Stops casual browsing of `/media/` and friends. |
| 9 | `RedirectMatch 404 "^/\.(?!well-known/)"` | Any URL path starting with a dot → 404: `.git`, `.env`, `.htpasswd`. `(?!well-known/)` is a **negative lookahead** — "dot-something, but not `.well-known/`", which SSL certificate issuance needs. |
| 12-14 | `<FilesMatch "\.(sql\|bak\|old\|zip\|tar\|gz\|tgz\|log\|md\|lock\|swp\|dump\|ini\|sh)$">` → `Require all denied` | Extension denylist. If a database dump, a `config.php.bak`, or `SECURITY.md` is ever uploaded by accident, it is not readable over the web. This is SEC-20 (`SECURITY.md:125-128`) — backup exposure is a classic shared-hosting breach. |
| 17-23 | `<IfModule mod_headers.c>` | The site-wide header block: `nosniff`, `Referrer-Policy`, `Permissions-Policy`, and a commented-out HSTS line (§6). |
| 27-31 | `<IfModule mod_deflate.c>` | **Compression** — `AddOutputFilterByType DEFLATE …` gzips text responses before sending, roughly 70% smaller HTML/CSS/JS/SVG/JSON. |
| 35-46 | `<IfModule mod_expires.c>` | Expiry per MIME type: one year for CSS, JS, images, `woff2`; **zero seconds** for `text/html`, so content edits appear at once. |
| 47-56 | Second `mod_headers` block | `Cache-Control: public, max-age=31536000, immutable` for versioned static files; `Cache-Control: no-cache` for `.php`. |

Every block is wrapped in `<IfModule>` so a host missing a module degrades quietly instead of
returning 500 — `public_html/.htaccess:2-3` says exactly that.

**The `text/javascript` story (F4 audit).** The `mod_deflate` list originally named only
`application/javascript` and `application/x-javascript`. Apache actually labels `.js` files
`text/javascript`, so the filter never matched and our 78 KB Bootstrap bundle went out
**uncompressed**. Commit `6b077ed` added `text/javascript` to line 29 — a one-word bug that cost real
bandwidth, invisible until someone measured. The year-long caching is safe only because assets are
versioned: `?v=SJ_ASSET_VER` changes each deploy, so a stale cache can never serve old code, because
the URL itself is new.

### 4.11 Uploads: no PHP execution in the media directory

`public_html/media/.htaccess`, all four lines:

```apache
php_flag engine off
<FilesMatch "\.(php|phtml|phar)$">
  Require all denied
</FilesMatch>
```

**The attack it stops.** If an attacker can upload a file *and* get the server to execute it, they
have a **web shell** — a page that runs arbitrary commands on the server. Uploaded photos land under
`/media/{id}/`, inside the webroot, so this folder is the prime target. Two independent defences:
`php_flag engine off` turns the PHP interpreter off for this subtree, so a `.php` file here is served
as bytes rather than run (only works under `mod_php` — see §7 gotcha 3); and `<FilesMatch>` +
`Require all denied` refuses to serve `.php`, `.phtml`, `.phar` at all, regardless of handler. Belt
*and* braces, because each alone has a host where it does not apply. Upstream there are stronger
controls: `SECURITY.md:72` records that the stored path is
`media/{autoincrement_id}/original.{ext-from-MIME}` — the visitor's filename never touches disk, so
nobody can upload a file *called* `.htaccess` to overwrite these rules. Note `public_html/photos/`
(the legacy image folder) has no `.htaccess` of its own; `SECURITY.md:73` lists adding one as an open
item.

### 4.12 A header is not a control

The most important idea on this page — compare the two columns:

| Real control | Header |
|---|---|
| `e()` escaping on every echoed value — the injected script never reaches the HTML | CSP — the script reached the HTML, and we ask the browser to please not run it |
| CSRF token checked with `hash_equals()` (`admin/api/_bootstrap.php:32-37`) | `form-action 'self'` — narrows where a forged form may post |
| `is_admin()` guard returning 401 (`admin/api/_bootstrap.php:22-24`) | `X-Frame-Options` — stops one delivery trick, not the request |

A real control fails **closed**: the request is rejected, server-side, every time. A header only asks
the client to cooperate, and an attacker's client need not. Headers exist to catch the mistake you did
not know you made. Second wall, never the first.

---

## 5. Why this is the right approach here

| Alternative | Why not, for us |
|---|---|
| Headers only in the main Apache config (`httpd.conf` / a vhost) | Cleanest and fastest — read once at startup. But it needs SSH or a config editor, and `DEPLOY.md:3` states production is mPanel shared hosting with **no SSH**. We cannot reach that file. |
| A dedicated security module (`mod_security`, a hardening extension) | Same problem, worse: installing a server module needs root, and it adds a dependency our host may drop at any upgrade. |
| A CDN or WAF in front (Cloudflare) | Genuinely good — `docs/perf-baseline.md:105` lists Cloudflare as a production option. But it is an external dependency, applies only while traffic flows through it, and a direct-to-origin request bypasses it. A supplement, not a base layer. |

Our split — **security-critical headers from PHP, site-wide niceties from `.htaccess`** — needs no
server access, no module, no third party, and survives a host migration. The strict `script-src
'self'` deserves its own note: it was achievable *only* because the admin panel was built with no
inline JavaScript (§4.4). Had one `onclick="…"` attribute been left in, the policy would have needed
`'unsafe-inline'` and CSP would have gone from a real defence to decoration. Design choices upstream
decide which headers you can afford downstream.

---

## 6. How this scales

- **A CDN in front.** With Cloudflare terminating requests, headers can be added at the edge for
  *every* response, including static files, without touching the host. Our PHP-emitted headers keep
  working underneath as the origin-level safety net.
- **Adding `Strict-Transport-Security`.** HSTS tells a browser "for the next N seconds, only ever
  reach this domain over HTTPS" — killing the downgrade window where an attacker on the same Wi-Fi
  intercepts the first plain-HTTP request. It is already written and commented out at
  `public_html/.htaccess:21-22`. It stays commented because HSTS is **hard to undo**: browsers
  remember it for the full `max-age` (a year, in the value we drafted). Turn it on only once HTTPS is
  confirmed stable, ideally with a short `max-age` first.
- **CSP reporting.** A CSP can carry `report-uri` / `report-to`, making the browser POST a JSON report
  whenever it blocks something. Paired with `Content-Security-Policy-Report-Only` — which reports
  without blocking — you can roll a policy out to public pages and *watch* what would break before
  enforcing. That is the path `SECURITY.md:91` sets out for our public pages.
- **Nonce-based CSP.** The eventual fix for `'unsafe-inline'` on styles: generate a random token per
  request, put it in the header (`style-src 'nonce-abc123'`) and on every legitimate
  `<style nonce="abc123">`. Only tags carrying that request's nonce run. It needs each inline block
  rendered by PHP — which is why moving `login.php` and `password.php` to real CSS files is the
  cheaper first step.

---

## 7. Gotchas and mistakes to avoid

**1. Setting a header after output has started.** The classic: a blank line after `?>` in an included
file. PHP sends the body, then `header()` fails with "headers already sent". Our `headers_sent()`
guard (`src/Admin/Auth.php:92-94`) makes the call harmless — but the header is silently *missing*,
which is worse than a crash because nothing looks wrong. Verify with `curl -I` (§8), never by
eyeballing the page.

**2. `.htaccess` silently ignored — our real dev landmine.** Apache only obeys `.htaccess` if its main
config says `AllowOverride All` for that directory. The stock `php:8.3-apache` Docker image ships
Debian's default, `AllowOverride None`, plus `mod_headers` and `mod_expires` disabled. So for a while
**every rule in our `.htaccess` did nothing in dev** — no error, no warning, no log line. The file was
there, it looked right, and it was inert. `Dockerfile:13-14` fixes it — the comment above it at
`Dockerfile:10-12` explains that prod hosts honour `.htaccess` already and this only brings the dev
container in line:

```dockerfile
RUN a2enmod headers expires rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf
```

The lesson generalises: **a security control you have not observed working is not a control.** Test
it, don't assume it.

**3. `php_flag` directives that 500 on non-mod_php hosts.** `php_flag` and `php_value` exist only when
PHP runs as an Apache module (`mod_php`). On a host using PHP-FPM or LiteSpeed's own SAPI, an
unguarded `php_flag` line is an unknown directive and Apache answers **500 Internal Server Error** for
the whole directory. `public_html/media/.htaccess:1` has exactly such a line, `php_flag engine off`,
followed by a handler-independent fallback (lines 2-4) so the protection survives where `php_flag` is
ignored — but the line itself is a portability risk, and `SECURITY.md:73` flags verifying it on
LiteSpeed as an open item.

**4. A CSP that blocks your own CDN fonts and icons.** Ship `default-src 'self'`, then someone adds a
Google Font or an icon set from a CDN and it silently fails to load. Nothing errors server-side; the
page just looks wrong, and the only clue is a console message. Our public pages already load
off-origin assets (`views/partials/contact.php:14` and `:112-113`) — anyone extending the CSP to
public pages must account for them first.

**5. `.htaccess` does not exist on nginx.** It is an Apache feature (honoured by Apache-compatible
servers like LiteSpeed). nginx has no per-directory override file at all — every rule must live in the
server config, so a move to nginx means translating both files by hand. The PHP-emitted headers move
with the code. Another point for §4.7.

---

## 8. Try it yourself

Start the stack with `./run.sh`; it publishes on port 8090 (`docker-compose.yml:41`). `curl -I` sends
a HEAD request — headers only, no body. Run these three and compare the output:

```bash
curl -I http://localhost:8090/                        # public page
curl -I http://localhost:8090/admin/login.php         # admin page
curl -I http://localhost:8090/admin/api/images.php    # API endpoint
```

| Request | What you should see |
|---|---|
| `/` | `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cache-Control: no-cache` — all from `.htaccess`. **No** `Content-Security-Policy` and **no** `X-Frame-Options` (§4.9 — you are not in edit mode). |
| `/admin/login.php` | Everything above **plus** `X-Frame-Options: DENY` and the whole CSP string, from `sj_admin_headers()`. |
| `/admin/api/images.php` | `Content-Type: application/json`, `Cache-Control: no-store`, plus the admin set — on a **`401`** status, because you are not logged in. That is `admin/api/_bootstrap.php:22-24` working, and it proves headers go out even on a rejected request. |

Then prove the `.htaccess` rules actually bite:

```bash
curl -I http://localhost:8090/.git/HEAD          # expect 404  (.htaccess:9)
curl -I http://localhost:8090/media/             # expect 403  (Options -Indexes)
curl -sI -H 'Accept-Encoding: gzip' \
     http://localhost:8090/js/site.js | grep -i content-encoding   # expect gzip
```

**In the browser.** Open DevTools (F12) → **Network** → reload → click the first request → **Headers**
→ *Response Headers*. Same list, easier to read. The **Console** tab is where CSP violations surface:
temporarily add `<script>alert(1)</script>` to an admin page in your working copy and the console
names the exact directive that blocked it. Undo the edit afterwards.

---

## 9. Where to read more

**In this repo**

- [`../02-security.md`](../02-security.md) — every attack class in plain words; header table near line 264.
- [`../03-what-we-built.md`](../03-what-we-built.md) — Stage S4, where these headers were introduced (around line 151).
- [`../../../SECURITY.md`](../../../SECURITY.md) — the normative catalog. **SEC-12** (direct file access),
  **SEC-13** (missing headers), **SEC-14** (clickjacking), **SEC-10** (upload attacks) and **SEC-20**
  (backup exposure) all feed this page.
- [`../../../DEPLOY.md`](../../../DEPLOY.md) — the mPanel constraints that shaped the design, and the
  never-upload list that pairs with the `.htaccess` denylist.

**Outside**

- MDN, *Content-Security-Policy* — <https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy>
- OWASP Secure Headers Project — <https://owasp.org/www-project-secure-headers/>
- securityheaders.com — paste a live URL, get a graded report. Useful right after deploy.
- Apache, *.htaccess howto* — <https://httpd.apache.org/docs/current/howto/htaccess.html>
