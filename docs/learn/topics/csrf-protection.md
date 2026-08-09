# CSRF — Cross-Site Request Forgery

> **What you'll learn:** what a CSRF attack is, why your own browser is the accomplice, why "just use POST" and
> "just check the Referer" do not fix it, and exactly how every state-changing request in this repo is protected.
>
> **Prerequisites:** basic PHP (`if`, `$_POST`, `$_SESSION`, `echo`). Nothing else — every security word is
> defined the first time it appears.
>
> **Where it lives in our code:** the token is minted in `src/Admin/Csrf.php`, exposed to PHP as the global
> `csrf_token()` (`src/helpers.php:69`), checked centrally in `public_html/admin/api/_bootstrap.php:31`, and
> handed to the browser through a `<meta name="sj-csrf">` tag in `public_html/admin/_layout.php:88` and
> `views/shell.php:67`.

## 1. The one-paragraph version

When you log into our admin panel the server gives your browser a **cookie** — a small piece of text the browser
stores and then **automatically re-sends on every future request to our site**. That automatic re-sending is the
whole problem: *any* page on the internet can make your browser fire a request at our server, and the cookie
rides along, so the server sees a request that is genuinely yours. Our defence is a **CSRF token**: a 64-character
random string stored in your server-side session and also printed into our own pages. Our forms and JavaScript
send it back; a page on `evil.example` cannot read it, because the browser forbids one site from reading another
site's HTML. No token, no action — and that check lives in one shared file, so no endpoint can skip it.

## 2. The problem this solves

### 2.1 The story

The principal logs into `http://localhost:8090/admin/`. On success, `public_html/admin/login.php:28-29` runs
`session_regenerate_id(true);` and sets `$_SESSION['admin_id']`. A **session** is a box of data the *server*
keeps for one visitor; the **session cookie** is the ticket number the browser holds so the server knows which
box is hers. Ours is named `SJADMIN` (`src/Admin/Auth.php:35`).

She leaves that tab open and, in a second tab, opens a link a parent emailed her. The page looks like a photo
gallery. Hidden inside it is:

```html
<form id="f" method="POST" action="http://localhost:8090/admin/api/index.php?r=item">…</form>
<script>document.getElementById('f').submit();</script>
```

Her browser submits it. And here is the ugly part:

> The browser attaches the `SJADMIN` cookie **because the request is going to our domain**. It does not know or
> care which page started it.

So `public_html/admin/api/_bootstrap.php` runs, calls `is_admin()`, and the answer is **true**. She really is
logged in. Nothing is forged. The delete would go through — if we had no other defence. She clicked nothing and
saw nothing. That is **CSRF**: *Cross-Site* (a different site started the request) *Request Forgery* (it looks
like one she made).

### 2.2 Why cookies are the vulnerability — "ambient authority"

**Ambient authority** means the permission is in the air around you, not in the thing you are holding.

| Style | How it travels | Who can trigger it |
|---|---|---|
| Cookie (`SJADMIN`) | Browser attaches it **automatically** to every request to our host | **Any** page on the internet, by pointing a form or an image at our URL |
| A token you must read and attach yourself | Only if the page's own code puts it in the request | Only code that can **read** our page |

Cookies are ambient. The convenience — never logging in again on the next click — *is* the hole. The attacker
never steals the cookie; he gets your browser to spend it. CSRF is therefore not an authentication bug:
authentication works perfectly. CSRF abuses the fact that authentication is *too* automatic.

### 2.3 Why "check the Referer header" is not enough

The **Referer header** is a line the browser *may* add saying which page a request came from. Tempting: reject
anything not from our own site. It fails four ways. (1) **It is optional** — privacy settings, extensions and
proxies strip it; reject empty referrers and you break real editors, allow them and the attacker arranges for it
to be absent. (2) **Privacy defaults keep shrinking it** — our own admin pages send
`Referrer-Policy: strict-origin-when-cross-origin` (`src/Admin/Auth.php:97`). (3) **Substring checks get
bypassed** — `str_contains($ref, 'stjosephsondipudur.com')` happily accepts
`https://stjosephsondipudur.com.evil.example/`. (4) **HTTP→HTTPS transitions drop it**, so dev and prod differ.

We use an origin check in exactly one file, and it explains itself: `public_html/api/contact.php:5-8` calls it a
same-origin check because "this is an anonymous form, so there is no session to CSRF; origin pinning + the layers
below are the guard". With **no session** there is no ambient authority to steal, so origin pinning there is spam
control, stacked with a honeypot, a rate limit and reCAPTCHA (`public_html/api/contact.php:9-14`).

### 2.4 Why "use POST instead of GET" is not enough

POST is *better* — you cannot trigger one with an `<img src>` or a plain link — but an attacker's page can
auto-submit a form, as in §2.1. HTML forms may post across origins; that is how the web has always worked. So
POST alone is not a defence. **GET-only-for-reads is still a hard rule here**, because a mutating GET is
trivially triggered by an `<img>` buried in an email. `CLAUDE.md:65`: "state changes are **POST + CSRF** only; no
mutating GET."

## 3. How it works in general

The standard fix is the **synchroniser token pattern**:

1. **Mint.** Generate a long unguessable random string once per session; store it in the session box on the server.
2. **Publish.** Print it into every page *we* serve — a hidden form field, or a `<meta>` tag our JavaScript reads.
3. **Verify.** On every state-changing request, compare what the request sent against what the session holds.
   Mismatch or missing → refuse.

Why can't the attacker read our token? The **same-origin policy** — the browser rule that JavaScript on
`evil.example` may not read the *content* of a page from our domain. He can make your browser *send* requests to
us all day; he cannot **read the responses**. That asymmetry is the entire trick: he can cause a request and have
the cookie ride along, but he can never learn a 256-bit secret he is not allowed to see.

## 4. How we use it — every place in this codebase

### 4.1 Minting the token — `src/Admin/Csrf.php`

The whole generator is ten lines:

```php
final class Csrf
{
    public static function token(): string
    {
        if (\session_status() !== \PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = \bin2hex(\random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
}
```
— `src/Admin/Csrf.php:10-22`

**`random_bytes(32)`** asks the operating system for 32 bytes from its **cryptographically secure random number
generator** — "cryptographically secure" means the output is unpredictable even to someone who watched every
previous output. This matters enormously: `rand()`/`mt_rand()` are *statistically* random but **seeded**, so an
attacker who sees a few outputs can compute the internal state and predict the rest. A predictable CSRF token is
no token at all. `bin2hex()` turns 32 raw bytes into 64 hex characters so the value survives being printed into
HTML. 256 bits — brute force is off the table.

**The `''` return when there is no session** is deliberate; the docblock says "Sessionless callers get `''` — a
POST with an empty token can never match a real one" (`src/Admin/Csrf.php:6-7`). Think it through: a stranger with
no session gets `''` and sends nothing, so his side is `''` too — wouldn't `'' === ''` pass? **No**, because every
call site tests emptiness first (`public_html/admin/api/_bootstrap.php:34`, `public_html/admin/editmode.php:13`).
Returning `''` rather than starting a session also keeps public pages cheap — visitors without an `SJADMIN` cookie
get "zero session cost" (`src/Admin/Auth.php:24-26`).

### 4.2 `hash_equals()` — and why `==` is a bug

Normal string comparison is **short-circuiting**: it stops at the first differing character. Comparing `"aaaa…"`
to the real token returns fractionally faster than comparing `"f9aa…"` if the token starts with `f`. Microseconds
— but an attacker who sends millions of requests and measures response times can recover the token **one
character at a time**, turning an impossible 2^256 guess into about a thousand tries. That is a **timing attack**.
`hash_equals(string $known, string $user)` always compares every byte, taking the same time wherever the mismatch
is. Use it for any secret comparison; we also use it for the health-check token (`public_html/admin/health.php:14`).

### 4.3 The central guard — `public_html/admin/api/_bootstrap.php`

Every admin API endpoint starts with `require __DIR__ . '/_bootstrap.php';`. That file runs three gates in order
— logged in, password changed, CSRF:

```php
if (!is_admin()) {
    api_fail('Not authenticated', 401);
}
// Block all content APIs until the forced password change is done (SEC-08).
if (!empty($_SESSION['must_change_pw'])) { … }

// CSRF on every mutating request (all endpoints except GET images.php).
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        api_fail('Invalid CSRF token', 403);
    }
}
```
— `public_html/admin/api/_bootstrap.php:22-37`

`$_SERVER['HTTP_X_CSRF_TOKEN']` is how PHP exposes the request header `X-CSRF-Token`: uppercased, dashes to
underscores, `HTTP_` prefix. Note the shape: it is **not** a list of protected endpoints, it is "every method that
is not GET". A new endpoint is protected the moment it `require`s this file — you cannot forget to add it to a
list, because there is no list. `public_html/admin/api/index.php` is the single front door (route map at lines
7-17) and records the invariant at lines 4-5.

### 4.4 Every check, in one table (paths relative to `public_html/`)

| Where | Transport | Check | Failure |
|---|---|---|---|
| `admin/api/_bootstrap.php:32-37` | `X-CSRF-Token` header, all non-GET | `$token === '' \|\| !hash_equals(csrf_token(), $token)` | `403 {"ok":false,"error":"Invalid CSRF token"}` |
| `admin/login.php:13-15` | `$_POST['csrf']` hidden field | `!hash_equals(csrf_token(), $token)` | "Session expired — please try again." |
| `admin/logout.php:6-9` | `$_POST['csrf']` hidden field | POST **and** `hash_equals(csrf_token(), $_POST['csrf'] ?? '')` | Redirect to `/admin/`; no logout |
| `admin/password.php:19-20` | `$_POST['csrf']` hidden field | `!hash_equals(csrf_token(), $_POST['csrf'] ?? '')` | "Session expired — please try again." |
| `admin/editmode.php:8-16` | `$_POST['csrf']` hidden field | POST + `is_admin()`, then `$token === '' \|\| !hash_equals(...)` | `403` + `Bad CSRF token.` |
| `api/contact.php:40-45` | — (anonymous, no session) | Origin/Referer host pinning + honeypot + rate limit | `403`/`429` |

**Why POST-only logout was worth fixing.** Logout used to be a plain link — a GET. `SECURITY.md:162` lists it as
finding 11: "`logout.php` mutates session on GET without CSRF token". Two reasons it mattered. First, **any**
`<img src="/admin/logout.php">` on any page or email would silently log the principal out mid-edit. Second, and
bigger, it broke an invariant: once one GET may change state, "GET never changes state" stops being something you
can rely on when reviewing new code. Line 2 of the file now reads "Logout — POST + CSRF only (no mutating GET;
SECURITY.md SEC-05/15)."

### 4.5 How the token reaches the browser

Two hops: **PHP prints it into a meta tag**, then **JavaScript reads that tag**.

```php
<meta name="sj-csrf" content="<?= e(csrf_token()) ?>">
```
— `public_html/admin/_layout.php:88`, and `views/shell.php:67` for the public site, where the tag sits inside
`if (is_admin()):` so "visitors get zero admin bytes" (`views/shell.php:66`).

`e()` is `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` (`src/helpers.php:40-43`); the token is hex so it needs no
escaping, but the rule is "everything echoed goes through `e()`". Classic forms use a hidden `csrf` field instead:
`public_html/admin/login.php:94` (sign in), `public_html/admin/password.php:105` (change password),
`public_html/admin/_layout.php:109` (sidebar Log out) and `views/partials/admin-bar.php:12` (the on-page "Edit
this page" toggle). There is exactly **one** place that reads the meta tag:

```js
var CSRF = (document.querySelector('meta[name="sj-csrf"]') || {}).content || '';
```
— `public_html/admin/assets/sj-ui.js:9`

It attaches the value to the shared JSON helper — `headers: { 'Content-Type': 'application/json', 'X-CSRF-Token':
CSRF }` at `public_html/admin/assets/sj-ui.js:33` — and to the multipart upload, which cannot use that helper
(`sj-ui.js:251`). It then re-exports it on `window.SJUI` (`sj-ui.js:369`) so both consumers inherit it rather than
re-reading the DOM: `public_html/admin/assets/panel.js:8` (`var CSRF = SJUI.CSRF, …`) and
`public_html/js/admin.js:20` (`var api = SJUI.api, …`). One older screen reads the tag directly —
`public_html/admin/assets/settings.js:10-11`, used in its `fetch` at line 27.

### 4.6 Why `images.php` is a CSRF-free GET

`images.php` is the only admin API reachable by GET (`public_html/admin/api/images.php:2-3`). It still requires a
session — `_bootstrap.php` line 22 runs first — it just skips the token check, because the guard is scoped to
non-GET methods. Safe? Yes, **because it changes nothing**: its whole body is `SELECT`s and pagination, so forging
it achieves what a normal page load achieves — nothing — and the attacker still cannot read the response (§3).
Hence the general rule: **GET must never change state.** Add a `DELETE FROM` to a GET handler and the token check
silently stops applying. `SECURITY.md:176` makes it a pre-ship checklist item: "New endpoint `require`s
`api/_bootstrap.php` first (auth + CSRF + JSON); no mutation is reachable via GET."

### 4.7 `SameSite=Lax` — defence in depth

```php
        \session_name('SJADMIN');
        \session_set_cookie_params([
            'lifetime' => 0,   'path' => '/',   'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $secure,
        ]);
```
— `src/Admin/Auth.php:35-42` (reformatted onto fewer lines; values verbatim)

**`samesite` tells the browser when it may attach the cookie to a cross-site request.** `Lax` means: attach it on
top-level navigations you can see (clicking a link to us), but **not** on cross-site POSTs, form auto-submits,
`<img>` or `fetch`. That kills the §2.1 attack in the browser, before our code runs. So why keep tokens? Because
`SameSite` is a **second** lock. It **covers** cross-site POSTs and `fetch`es from `evil.example`,
`<img>`/`<iframe>`-triggered requests, and auto-submitted cross-site forms. It does **not** cover an attack from
**our own** origin (an XSS hole on our site), old browsers that ignore the attribute, a future subdomain or proxy
that makes the attacker "same-site", or a config change that drops the attribute altogether. Lose it and, with
tokens, you are still safe. That is "defence in depth": no single line is load-bearing. Its neighbours matter too
— `httponly` stops JavaScript reading the cookie; `secure` (`src/Admin/Auth.php:32-34`) stops it travelling over
plain HTTP. The admin **Content-Security-Policy** adds `form-action 'self'; frame-ancestors 'none'`
(`src/Admin/Auth.php:101`), stopping an injected form on *our* page from posting elsewhere and stopping the panel
being framed.

### 4.8 Token rotation on login

```php
            unset($_SESSION['csrf']);
            csrf_token(); // fresh token post-login
```
— `public_html/admin/login.php:36-37`

Before login you already had a session — the login form itself needs a token — and that pre-login token may have
been observed. Deleting the key forces `Csrf::token()` into the `empty()` branch and mints a brand-new value
(`src/Admin/Csrf.php:17-19`), so **the token guarding privileged actions was never visible before the privilege
existed**. It sits beside `session_regenerate_id(true)` (`public_html/admin/login.php:28`), which does the same for
the session id. One principle: **when authority changes, rotate every secret tied to it** — same pairing after a
password change (`public_html/admin/password.php:42`).

## 5. Why this is the right approach here

Constraints: plain PHP 8.3, no framework, a single origin, one non-technical editor who must not be logged out
constantly. Judge the alternatives against *those*.

| Alternative | What it is | Why not here |
|---|---|---|
| **Double-submit cookie** | Put the token in a second cookie *and* in the request; compare the two. No server-side storage. | It exists to avoid server state — but we already boot a session on every admin request, so storage is free. And it is weaker: any subdomain, or a MITM on plain HTTP, can set a cookie for our domain and forge both halves. More attack surface, zero benefit. |
| **`SameSite=Strict` only** | Browser refuses the cookie on *all* cross-site requests, including link clicks. | We already ship `Lax` as a second layer (§4.7). Making it the only layer bets everything on one browser attribute, gives nothing against a same-origin XSS pivot — which `SECURITY.md:8` lists in the threat model — and `Strict` breaks the ordinary flow of clicking a link into `/admin/`. |
| **Re-authenticate per action** | Ask for the password before each save. | Unusable: the editor changes dozens of fields per session through the live-edit overlay, and abandoned admin panels are how stale credentials appear. We already re-authenticate where it counts — `public_html/admin/password.php:30` demands the current password. |
| **Origin-header check only** | Compare `Origin`/`Referer` to our host. | See §2.3: optional header, easy to strip, easy to get wrong. Used once, on the sessionless contact form, with four other layers stacked on it. |

The synchroniser token wins because it needs no framework, no extra table, no extra request and no user friction — and it still holds when one of the other layers is misconfigured.

## 6. How this scales

**Today: ten routes, one guard.** `public_html/admin/api/index.php:7-17` maps ten route names to ten files, each
beginning `require __DIR__ . '/_bootstrap.php';`. Now imagine a hundred endpoints. If each file did its own check
you would be relying on a hundred developers remembering a four-line idiom. That is not a control; it is a hope.
Centralising buys three things: **secure by default** (a new file is protected because it *loaded* the guard, not
because someone added it to a list); **one place to audit** — "is CSRF enforced?" is six lines
(`public_html/admin/api/_bootstrap.php:32-37`), not a hundred-file grep; and **one place to change** — per-request
tokens, an `Origin` check, rate limiting all land in one file and every endpoint inherits them. `SECURITY.md:44`
turns this into a mechanical review step:

> **Agent verification:** curl any new endpoint without `X-CSRF-Token` → expect 403; with a stale token → 403.
> Confirm the file requires `_bootstrap.php` before any logic.

**If the site ever gains a second origin or a mobile app**, cookie-session CSRF protection stops being the right
model — a phone app has no cookie jar and no same-origin policy to lean on. Then you want **short-lived bearer
tokens** (a signed token with an expiry, sent in an `Authorization` header): because the client must
*deliberately* attach it, ambient authority disappears and CSRF vanishes as a category. Or a **per-app API key**
scoped to the few operations that app needs. A second *browser* origin additionally needs an explicit CORS
allowlist **and** keeps the tokens — CORS controls who may *read* replies, not who may forge a write. Do not bolt
any of this on speculatively; today's design matches today's single-origin site.

## 7. Gotchas and mistakes to avoid

**1. A new endpoint that forgets `require _bootstrap.php`.** The big one. A file dropped into
`public_html/admin/api/` that goes straight into `$_POST` handling has **no auth check and no CSRF check** — a
public, world-writable endpoint. The require must be the **first** statement, before any logic; compare
`public_html/admin/api/field.php:3` and `public_html/admin/api/order.php:3`. Also add the route to the map in
`public_html/admin/api/index.php:7-17`; an unmapped route falls through to `api_fail('Unknown route', 404)` at
line 22, which still loads the guard first.

**2. Putting the token in a URL query string.** Never `?csrf=abc123`. URLs leak where bodies and headers do not:
server access logs, proxy logs, browser history, bookmarks, "share this page", and the `Referer` sent to every
third-party resource the page loads. Ours travels in a POST body or the `X-CSRF-Token` header
(`public_html/admin/assets/sj-ui.js:33`); our admin URLs carry only a route name (`?r=item`), never a secret.

**3. A stale tab whose token expired.** Sessions have real limits:

```php
    public const SESSION_IDLE_MAX = 1800;   // 30 min of inactivity
    public const SESSION_ABS_MAX  = 43200;  // 12 h hard cap
```
— `src/Admin/Auth.php:13-14`

A tab left open over lunch holds a token whose session is gone; the next save gets `401 Not authenticated`,
because the auth gate fires before the CSRF gate. Note that the 15-minute `session_regenerate_id(true)`
(`src/Admin/Auth.php:58-60`) does **not** invalidate the token — it changes the cookie's id while
`$_SESSION['csrf']` stays put, so long editing sessions keep working. When a token check does fail, the honest
message is "your session expired" — exactly what `public_html/admin/login.php:15` and
`public_html/admin/password.php:20` say. Never "fix" this by weakening the check.

**4. Caching a page that contains a token.** If a proxy or CDN caches a page carrying `<meta name="sj-csrf">`, it
serves **your** token to the next visitor — handing an attacker the one thing he could not otherwise get. Every
admin response sets `header('Cache-Control: no-store');` (`public_html/admin/api/_bootstrap.php:7`). Keep it, and
never put a page cache in front of `/admin/`. On the public site the tag is inside `if (is_admin())`
(`views/shell.php:66`), so a cached visitor page holds no token at all.

**5. Two smaller traps.** Do not swap `hash_equals()` for `==` because it reads nicer (§4.2), and do not
"simplify" `Csrf::token()`'s `''` return away — the empty string is load-bearing (§4.1).

## 8. Try it yourself

Run `./run.sh`, open `http://localhost:8090/admin/`, log in, press **F12**, open the **Console** tab. We use route
`?r=item` with `action:'get'` — a POST that only **reads** a row (`public_html/admin/api/item.php:13-20`).

**Step 1 — forge a request with no token (expect 403).**

```js
fetch('/admin/api/index.php?r=item', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ action: 'get', entity: 'hero_slide', id: 1 })
}).then(r => r.json().then(j => console.log(r.status, j)));
```

Expected: `403 {ok: false, error: "Invalid CSRF token"}`. Your session cookie **was** attached — the browser did
that automatically (§2.2) — and `is_admin()` returned true. The request still died at line 35 of `_bootstrap.php`.

**Step 2 — read the token and retry (expect 200).**

```js
const CSRF = document.querySelector('meta[name="sj-csrf"]').content;
console.log('token length:', CSRF.length);   // 64 — that's bin2hex(32 bytes)

fetch('/admin/api/index.php?r=item', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
  body: JSON.stringify({ action: 'get', entity: 'hero_slide', id: 1 })
}).then(r => r.json().then(j => console.log(r.status, j)));
```

Now `200`, with `values`, `fields` and `thumbs` (`public_html/admin/api/item.php:21-40`).

**Step 3 — a wrong token of the right shape (expect 403).** Re-run step 2 with `'X-CSRF-Token': 'f'.repeat(64)`.
Still `403` — length and format are irrelevant; only `hash_equals` against the session value matters. **Step 4 —
the GET exemption.** `fetch('/admin/api/index.php?r=images&page=0').then(r => r.json().then(j =>
console.log(r.status, j)));` returns `200` with no token: it is a GET and it changes nothing (§4.6).

**Step 5 — the key insight.** All of that ran **on our own origin**, where reading the meta tag is allowed. A real
attacker's page runs on *his* origin, where step 2 is impossible: the same-origin policy refuses to let his script
read our HTML. He is permanently stuck at step 1.

## 9. Where to read more

| Document | Why |
|---|---|
| [`../02-security.md`](../02-security.md) | §3 is the plain-English CSRF intro; neighbouring sections cover XSS and SQL injection, which share the mindset |
| [`../03-what-we-built.md`](../03-what-we-built.md) | Stage A/B narrative — the logout-as-POST fix (line 155) and the admin request path (line 273) |
| [`../06-stage-f.md`](../06-stage-f.md) | How the live-edit overlay reuses the *same* CSRF-guarded API instead of inventing endpoints (lines 36, 47-48, 66) |
| [`../../../SECURITY.md`](../../../SECURITY.md) | Normative catalog: **SEC-05** (CSRF, line 40), **SEC-15** (mutating GET, line 98), **SEC-07** (session/cookie flags, line 52), §4 pre-ship checklist (line 168) |
| [`../../../CLAUDE.md`](../../../CLAUDE.md) | Line 65 — "POST + CSRF only; no mutating GET" and "every new admin API file `require`s `_bootstrap.php`" |

External: OWASP's [*CSRF Prevention Cheat Sheet*](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html);
MDN on [*Set-Cookie* / `SameSite`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Set-Cookie)
and the [*same-origin policy*](https://developer.mozilla.org/en-US/docs/Web/Security/Same-origin_policy); the PHP
manual on [`hash_equals()`](https://www.php.net/manual/en/function.hash-equals.php).
