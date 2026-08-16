# Sessions and Cookies

> **What you'll learn:** why the web forgets you between clicks, what a cookie really is, how a PHP session keeps your
> data on the *server* while the browser carries only a random id — then, line by line, how **our** admin session
> works: lazy boot, cookie flags, two timeouts, id rotation, teardown.
>
> **Prerequisites:** basic PHP (`if`, `function`, `$_POST`); you may have written `$_SESSION['x'] = 1` once. No
> security background needed — every term is explained on first use.
>
> **Where it lives:** `src/Admin/Auth.php` is the whole session engine; `public_html/bootstrap.php:25` runs it for
> everyone and seven admin entry points force it on. `src/Admin/Csrf.php` and `public_html/admin/login.php` are what
> mainly *put* data in it.

---

## 1. The one-paragraph version

HTTP has **no memory** — each request arrives as a total stranger. A **cookie** is a small piece of text the
server hands the browser, which the browser hands back on every later request to that site: a name tag. PHP
**sessions** build on that. The cookie holds *only* a long random **session id**; the actual data (who you are,
what you may do) sits in a file on the server keyed by that id. Here the cookie is `SJADMIN`, the data lives in
`sess_<id>` files, and the session dies after 30 minutes idle or 12 hours total, whichever comes first.

---

## 2. The problem this solves

Imagine a receptionist with total amnesia — every time you walk up, she has never seen you. That is HTTP. The technical
word is **stateless**: the protocol carries no memory between requests. Using our admin panel means many requests:

```
GET  /admin/login.php     ← show me the login form
POST /admin/login.php     ← here is my username and password
GET  /admin/              ← show me the dashboard
GET  /admin/hero.php      ← show me the carousel editor
```

Request two is where you prove who you are. Requests three and four are brand-new connections. Nothing in a plain
`GET /admin/hero.php` says "this is the person who typed the correct password 40 seconds ago". Without extra
machinery the server would demand your password on *every page*. So we need a way to say **"this request belongs to
the same person as that earlier request."** That is the whole job of cookies and sessions.

A second, less obvious problem: whatever marks "same person" becomes a **credential** — anyone who copies it becomes
you. So it must be unguessable, hard to steal, and short-lived. Most of `src/Admin/Auth.php` exists for that second
problem, not the first.

---

## 3. How it works in general

### 3.1 Cookies: the name tag

The server sets a cookie with a response header; the browser sends it back thereafter:

```
Set-Cookie: SJADMIN=7f3a91c4...; Path=/; HttpOnly; SameSite=Lax     (server → browser, once)
Cookie: SJADMIN=7f3a91c4...                                          (browser → server, always)
```

That is the whole mechanism. A cookie has a **name**, a **value**, and **attributes** controlling when the
browser may send it. The attributes matter more than beginners expect:

| Attribute | What it means | What it blocks |
|---|---|---|
| `Expires` / `Max-Age` | Delete at this time. Omit both → a **session cookie**, deleted when the browser closes. | Cookies lingering on a shared computer |
| `Path=/` | Only send for URLs under this path. | Nothing security-critical — scoping, not protection |
| `Domain` | Which host(s) get it. `Domain=example.com` sends it to **all** subdomains. | Leaking your cookie to `blog.example.com` |
| `HttpOnly` | JavaScript cannot read it (`document.cookie` won't show it). | An XSS bug stealing the login cookie |
| `Secure` | Only send over HTTPS, never plain HTTP. | Someone on cafe Wi-Fi reading it off the wire |
| `SameSite` | Whether it rides along on requests started by *another* site. `Strict` = never, `Lax` = only top-level navigations, `None` = always. | CSRF — an evil page making authenticated requests as you |

> **XSS** = cross-site scripting: an attacker gets their JavaScript running on your page. **CSRF** = cross-site
> request forgery: an attacker's page makes *your logged-in browser* submit a request to our site. Both are
> covered in `../02-security.md`.

### 3.2 Sessions: the data stays home

Putting real data in a cookie would be a disaster — the user can read and edit it. PHP splits it:

```
BROWSER                                   SERVER
Cookie: SJADMIN=7f3a91c4...   ────────>   open file  sess_7f3a91c4...
                                          read it into $_SESSION
                                          $_SESSION['admin_id'] === 1  → logged in
```

**The browser never sees the session contents** — only the id, which is large and random, so it cannot be
guessed. Where do the files live? PHP has a setting, `session.save_path`. If empty, PHP falls back to the system
temp directory. Both are visible in our container:

```
$ docker compose exec web php -r 'echo "[".session_save_path()."] ".sys_get_temp_dir();'
[] /tmp

$ docker compose exec web ls -la /tmp
-rw------- 1 www-data www-data  77 Aug  8 23:48 sess_1fa2875493d38072df0b0e474d722dc4
-rw------- 1 www-data www-data   0 Aug  8 23:06 sess_46393cf3afd6d8e72206303ba162b631
```

Note `-rw-------`: readable only by `www-data`, the user PHP runs as. The filename is `sess_` plus the exact id
from the cookie. Contents use PHP's **serialized** format, `key|type:length:"value";`:

```
$ docker compose exec web cat /tmp/sess_1fa2875493d38072df0b0e474d722dc4
csrf|s:64:"2d4484cacc6b8b41…310285dd";
```

Key `csrf`, a string (`s`) of 64 characters. On each request PHP parses this file into `$_SESSION`; when the
request ends it writes `$_SESSION` back out. That is all a session is — an array loaded and saved for you, found
via a cookie.

---

## 4. How we use it — every place in this codebase

### 4.1 Who starts a session, and when

Exactly two ways in:

| Caller | Call | Why |
|---|---|---|
| `public_html/bootstrap.php:25` | `sj_session_boot(false)` | Runs on **every** page. "Boot only if an admin cookie is already present." |
| 7 admin entry points | `sj_session_boot(true)` | "Boot regardless" — login and CSRF must be able to *create* a session. |

The seven forced callers: `admin/login.php:3`, `admin/logout.php:4`, `admin/_layout.php:6`, `admin/password.php:6`,
`admin/editmode.php:6`, `admin/health.php:6`, `admin/api/_bootstrap.php:4`. `_layout.php` covers every panel screen
and `api/_bootstrap.php` every JSON endpoint, so those two lines protect most of the admin surface.
`sj_session_boot()` is a thin wrapper (`src/helpers.php:49-52`) around `Auth::boot()`.

### 4.2 `Auth::boot()` line by line

All snippets below are from `src/Admin/Auth.php`. It opens with a **re-entry guard** (lines 19-23): a `static
$booted` flag plus checks for `\PHP_SAPI === 'cli'` and an already-active session. Calling it twice must be
harmless, since `bootstrap.php` already called it before `admin/login.php` calls it again — and the CLI check
skips sessions entirely for the command-line seeder, which has no browser and no cookies.

**The lazy boot** (lines 24-26) — the clever bit:

```php
if (!$force && empty($_COOKIE['SJADMIN'])) {
    return; // public visitor without an admin cookie: zero session cost
}
```

A parent browsing the school website has no `SJADMIN` cookie, so this returns immediately: no file opened, no lock
taken, no `Set-Cookie` sent. Only visitors already carrying the cookie — or pages passing `$force = true` — pay the
cost. Otherwise every anonymous visitor would leave a junk file in `/tmp`.

**The save path** (lines 27-30):

```php
$cfg = \SJ\Core\Config::all();
if (!empty($cfg['session_save_path'])) {
    \session_save_path($cfg['session_save_path']); // private dir on prod (SEC-07/22)
}
```

`Config::all()` (`src/Core/Config.php:15-38`) loads `config/config.php` if present, else `config/config.sample.php`,
where the key ships as `'session_save_path' => null,` commented `// e.g. '/home/<account>/tmp/sessions' (chmod 700)
in prod` (`config/config.sample.php:32`). In dev nothing is overridden, so PHP's `/tmp` default applies.

Why does production need its own directory? We deploy to **shared hosting**, where other customers' sites run on the
same machine. `SECURITY.md` SEC-07 states it plainly: "Shared `/tmp` session storage risks co-tenant reads." A
neighbour who can list `/tmp` could read session files — and a session file may hold a valid CSRF token. Hence a
private directory the account owns, `chmod 700`.

**HTTPS detection** (lines 32-34):

```php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || !empty($cfg['force_secure_cookies']);
```

Three ways to conclude "this is HTTPS", because one is not enough:

1. `$_SERVER['HTTPS']` — set by the web server on a direct TLS connection. The `!== 'off'` check exists because some
   servers set the literal string `"off"`, which is not empty.
2. `HTTP_X_FORWARDED_PROTO` — when a **reverse proxy** terminates HTTPS and speaks plain HTTP to PHP,
   `$_SERVER['HTTPS']` is unset and PHP would wrongly think the site insecure; the proxy adds this header to report
   what the *user's* connection actually was.
3. `force_secure_cookies` from config (`config/config.sample.php:31`, default `false`, commented "set true once the
   site is HTTPS-only") — a manual override.

Wrong in the *unsafe* direction (thinking HTTP when it is HTTPS) means no `Secure` flag and a stealable cookie. Wrong
the *safe* way on plain HTTP means the browser never sends the cookie and you cannot log in at all — hence the local
default of `false`.

**Naming and cookie flags** (lines 35-43):

```php
\session_name('SJADMIN');
\session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $secure,
]);
\session_start();
```

- `session_name('SJADMIN')` renames the cookie from PHP's default `PHPSESSID` — this is what makes the lazy-boot
  check on line 24 possible, since we know which cookie to look for.
- `lifetime => 0` means **no `Expires` attribute**: a session cookie, gone when the browser closes. Real expiry is
  enforced server-side (§4.4), which is stronger, since cookie expiry is only a polite request to the browser.
- `path => '/'` — site-wide, because public pages need it too, so the live-edit overlay can tell an admin from a visitor.
- `httponly => true` — JavaScript cannot read `SJADMIN`; if an XSS bug ever lands, the attacker's script still cannot
  copy the login cookie.
- `samesite => 'Lax'` — a foreign site's `fetch()` or auto-submitted form will not carry our cookie, killing the
  simplest CSRF attacks. Defence in depth, not our main CSRF defence; that is the token in `src/Admin/Csrf.php`.

PHP's own defaults are weaker — our container reports `session.cookie_httponly => Off` and
`session.cookie_samesite => no value`. We set them **at runtime, per request**, so we never depend on ini files we do
not control on shared hosting.

### 4.3 What we store in the session

`$_SESSION` in this repo holds exactly eight keys:

| Key | Set at | Purpose |
|---|---|---|
| `admin_id` | `admin/login.php:29` | The `admin_users.id` row. **This key alone means "logged in"** — `Auth::isAdmin()` tests it. Also stamped on audit rows (`src/Admin/Audit.php:18`). |
| `admin_name` | `admin/login.php:30` | Display name for the sidebar (`admin/_layout.php:119`). Convenience only — avoids a DB query per page. |
| `edit_mode` | `admin/login.php:31` (as `0`), toggled at `admin/editmode.php:18` | Is the on-page live-edit overlay on? Read by `Auth::isEdit()`. |
| `must_change_pw` | `admin/login.php:32` | Copied from `admin_users.must_change_password`. While truthy, `_layout.php:13` redirects to `password.php` and `api/_bootstrap.php:27` returns 403. Cleared at `admin/password.php:43`. |
| `login_at` | `admin/login.php:33` | Session start — drives the **absolute** timeout. |
| `last_seen` | `admin/login.php:34`, refreshed `Auth.php:57` | Last request time — drives the **idle** timeout. |
| `last_regen` | `admin/login.php:35`, refreshed `Auth.php:60` | When the id was last rotated. |
| `csrf` | `src/Admin/Csrf.php:18` | 32 random bytes, `bin2hex`'d to 64 characters, that every mutating request must echo back. |

Notice what is **not** there: no password, no hash, no permissions, no database rows. Sessions hold identity and
small flags; anything authoritative is re-read from the database — see `admin/password.php:26-28`, which re-fetches
the hash rather than trusting the session.

### 4.4 Lifetime: two clocks and a rotation

Three constants (`src/Admin/Auth.php:13-15`):

```php
public const SESSION_IDLE_MAX = 1800;   // 30 min of inactivity
public const SESSION_ABS_MAX  = 43200;  // 12 h hard cap
public const SESSION_REGEN    = 900;    // rotate the session id every 15 min
```

- **Idle timeout (1800 s)** — measured from your *last request*. Keep clicking and it never fires; walk away from
  an unlocked laptop for half an hour and you are logged out.
- **Absolute timeout (43200 s)** — measured from *login*, and it fires even if you were active throughout. This is
  the backstop for a stolen cookie: an attacker keeping a hijacked session warm still loses it within 12 hours.
- **Rotation (900 s)** — not an expiry. The session survives; its id changes.

The enforcement block (lines 49-62):

```php
if (!empty($_SESSION['admin_id'])) {
    $now   = \time();
    $last  = $_SESSION['last_seen'] ?? $now;
    $start = $_SESSION['login_at']  ?? $now;
    if (($now - $last) > self::SESSION_IDLE_MAX || ($now - $start) > self::SESSION_ABS_MAX) {
        self::kill();
        return;
    }
    $_SESSION['last_seen'] = $now;
    if (($now - ($_SESSION['last_regen'] ?? 0)) > self::SESSION_REGEN) {
        \session_regenerate_id(true);
        $_SESSION['last_regen'] = $now;
    }
}
```

The guard `!empty($_SESSION['admin_id'])` means anonymous sessions — one holding just a `csrf` token for the login
form — are never timed out; there is nothing to protect yet. Then either clock over budget → `kill()` → return.
Otherwise the visit is recorded, and after 15 minutes the id rotates.

The comment above the block (lines 46-48) explains why expiry needs no code elsewhere: after `kill()`, `is_admin()` is
false, so **each caller's existing behaviour just happens** — `_layout.php:8-11` redirects to login,
`api/_bootstrap.php:22-24` returns `401 {"ok":false,"error":"Not authenticated"}`, and a public page renders for a
visitor. One central check, no duplication.

### 4.5 Session fixation, and why we rotate ids

**Session fixation** is an attack where the attacker chooses your session id *before* you log in. They obtain an id
from the site, trick you into using that same id, then wait. You log in, the server upgrades *that id* to "logged in"
— and the attacker already knows it. They are now you, without ever seeing your password.

The fix is one line: **when privileges change, throw the old id away.** `public_html/admin/login.php:28`, immediately
after `password_verify` succeeds and before a single `$_SESSION` key is written:

```php
session_regenerate_id(true);
$_SESSION['admin_id']       = (int)$user['id'];
```

The `true` means "delete the old session file too", so the pre-login id is destroyed, not merely abandoned. The same
call appears at `public_html/admin/password.php:42`, right after a password change — another privilege event. The
15-minute rotation extends the idea: a cookie stolen mid-session has a limited useful life, because the id it
carries keeps being retired.

### 4.6 Teardown: `Auth::kill()`

Logging out is three steps, not one (lines 66-74):

```php
public static function kill(): void
{
    $_SESSION = [];
    if (\ini_get('session.use_cookies')) {
        $p = \session_get_cookie_params();
        \setcookie(\session_name(), '', \time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    \session_destroy();
}
```

1. **`$_SESSION = []`** empties the in-memory array so the rest of *this* request already sees a logged-out user. Alone
   it is not enough: the disk file is untouched until the request ends, and the browser still holds the cookie.
2. **Expire the cookie.** `setcookie()` with an empty value and a past timestamp (`time() - 42000`) tells the browser
   to delete it; skip this and the browser presents a dead id forever. The params are copied from
   `session_get_cookie_params()` because a cookie is only replaced when **name, path and domain all match**.
3. **`session_destroy()`** deletes the server-side file. This is what actually revokes access, because the file is
   where the truth lives. Even with a copied cookie, there is no file left for that id to open.

`kill()` is reached two ways: automatically on timeout (`Auth.php:54`), and deliberately via `sj_session_kill()` in
`public_html/admin/logout.php:14`. Logout is **POST + CSRF only** (`admin/logout.php:6`) and the panel's control is a
real form, not a link (`admin/_layout.php:108-111`) — a `GET` logout link would let any web page log you out with an
embedded `<img>`.

---

## 5. Why this is the right approach here

Our constraints decide the answer: **MilesWeb shared hosting** (no SSH, no root, no background processes, no Redis);
**plain PHP, no framework** (anything we adopt, we maintain); **a very small admin group** on one server;
**deploy = uploading files**. Server-side file sessions are PHP's built-in default and fit all four: zero
dependencies, zero configuration beyond a save path, and the secret never leaves the server. The alternatives:

| Option | Why not here |
|---|---|
| **JWT in `localStorage`** | A JWT is a signed token holding the claims themselves. `localStorage` is readable by **any** JavaScript on the page, so `HttpOnly` — our single best defence against XSS stealing the login — becomes impossible. Worse, a JWT is *stateless by design*: with no server record to delete, `kill()`, the idle timeout and the rotation would each need a revocation list. At which point you have re-invented sessions, plus a crypto library to keep patched. |
| **Database-backed sessions** | Genuinely useful — the standard fix for multiple servers. But we have one server, so it buys nothing today while costing queries on every request against a documented ≤12-per-page budget. The right *next* step, not the right *first* step. |
| **"Remember me" tokens** | A long-lived cookie surviving browser close. It directly contradicts why the absolute timeout exists, and the assets here include children's photos and the public face of a school. For a few staff logging in occasionally, typing a password is a fair price. |

The deeper principle: with server-side sessions the browser holds a **reference**, not a **fact**. A reference is
revoked instantly by deleting one file. A fact, once signed and handed out, stays true until it expires — and you
cannot take it back.

---

## 6. How this scales

**10x more editors (30 staff instead of 3).** Nothing changes. Each active session is one small file — the one above
was 77 bytes. A hundred concurrent editors is a few kilobytes and a handful of file opens per request. File-based
sessions on one server will not become the bottleneck at this size.

**What actually breaks: a second server.** With two web servers behind a load balancer, you log in on server A, your
next request lands on server B, which has no `sess_<id>` file, and you appear randomly logged out. Two standard fixes:

- **Sticky sessions** — the load balancer pins each visitor to one server. Cheapest, but a server restart logs out
  everyone on it.
- **A shared store** — move sessions into MySQL (a custom `SessionHandlerInterface`) or Redis so every server reads
  the same data. More robust, and a natural fit since we already run MySQL.

**Disk cleanup.** Session files are not deleted on expiry; PHP runs **garbage collection** probabilistically. Our
container reports `session.gc_probability => 1` with `session.gc_divisor => 100` (roughly 1-in-100 requests) and
`session.gc_maxlifetime => 1440` (24 minutes). Note 1440 s is *shorter* than our 30-minute idle window, so on a quiet
site a file can be swept before our own timeout fires — the user just sees a login page, the same safe outcome. On a
private `session.save_path`, check GC, because some hosts run a system cleaner that only knows the default directory.

**The exact next step** if we outgrow one server: implement a `SessionHandlerInterface` backed by a `sessions` table
and register it inside `Auth::boot()` just before `session_start()`. Every timeout, rotation and teardown rule above
keeps working unchanged, because they all operate on `$_SESSION` and PHP's session API, not on files.

---

## 7. Gotchas and mistakes to avoid

**`header()` after output.** Cookies are HTTP headers, and headers must precede any body bytes. One stray space before
`<?php` and `session_start()` fails with "headers already sent" — no cookie, no session, no login. Hence
`sj_session_boot()` sits at the very top of every entry point, and `Auth::headers()` checks
`if (\headers_sent()) { return; }` (`src/Admin/Auth.php:92-94`).

**Forgetting `session_regenerate_id()` at login.** The most common session bug, and it fails silently forever: the
code works perfectly, you log in, you see the dashboard — and you are wide open to fixation (§4.5). Any new
privilege-changing flow must rotate the id, as `login.php:28` and `password.php:42` do.

**`session_destroy()` without expiring the cookie.** The browser keeps presenting a dead id, and you chase phantom
bugs where "logged out" users still send a session cookie. `Auth::kill()` does both, in order.

**Sessions leaking across subdomains.** We never set `Domain`, so `SJADMIN` is bound to the exact host that set it. If
someone later adds `Domain=stjosephsondipudur.com` to "make it work on www", the cookie then goes to **every**
subdomain — including any future `blog.` or `test.` host, possibly running software we do not control. Don't.

**UTC vs IST when reading timestamps.** The container runs UTC; the school is IST (UTC+5:30):

```
$ docker compose exec web date   → Sun Aug  9 04:08:55 UTC 2026
$ date                           → Sun Aug  9 09:38:55 AM IST 2026
```

The timeout *maths* is unaffected — `time()` returns a Unix timestamp, which has no timezone — so never "fix" this by
adding offsets in `Auth::boot()`. What bites you is *reading*: a session file's `ls -la` time and any `date()` output
run 5.5 hours behind your wall clock, so a file that looks 5 hours stale is probably 30 seconds old.

**Testing "logged out" without clearing the cookie.** Delete only the `sess_` file and your browser still sends the
old id, so PHP starts a *fresh empty* session under that same id — you see a login page without exercising the path
you meant to test. To hit the lazy-boot branch at `Auth.php:24` you must delete the **cookie** in devtools, because
that branch reads `$_COOKIE['SJADMIN']` and nothing else.

---

## 8. Try it yourself

Everything below is read-only apart from logging in.

**1. Start the stack** with `./run.sh` and log in at <http://localhost:8090/admin/>. The first login forces a
password change — that is `must_change_pw` doing its job (§4.3).

**2. Look at the cookie.** F12 → **Application** tab (Firefox: **Storage**) → **Cookies** → `http://localhost:8090`.
Find `SJADMIN` and confirm: the value is a long random string (the entire session id); **HttpOnly** ticked;
**SameSite** `Lax`; **Secure** *not* ticked (correct — plain HTTP locally, §4.2); **Expires** says `Session` (that is
`lifetime => 0`). Then type `document.cookie` in the **Console**: `SJADMIN` is **absent** — `HttpOnly` working, and
exactly what stops an XSS bug from stealing your login.

**3. Prove the public site pays nothing.** In a **private/incognito** window open <http://localhost:8090/> and check
its cookies. No `SJADMIN`. That is the lazy boot at `src/Admin/Auth.php:24` returning before `session_start()`.

**4. Find your session file, then read it.** You should see `sess_<id>` files owned by `www-data`, mode `-rw-------`;
one matches your `SJADMIN` cookie value exactly. Reading it shows the keys from §4.3 — `admin_id`, `admin_name`,
`login_at`, `last_seen`, `csrf`. Notice that this data was **never** sent to your browser, and that `admin_id|i:1;`
is what `is_admin()` actually tests.

```bash
docker compose exec web php -r 'echo "[".session_save_path()."] ".sys_get_temp_dir()."\n";'
docker compose exec web ls -la /tmp
docker compose exec web cat /tmp/sess_<paste-your-SJADMIN-value-here>
```

**5. Watch a session end.** Note your file with `ls -la /tmp`, click **Log out**, then re-run it: your `sess_` file is
**gone** — `session_destroy()` inside `Auth::kill()`, step 3 of §4.6. Reloading `/admin/` now bounces you to
`/admin/login.php` via `_layout.php:8-11`. For the cookie-side lesson, log in again, delete only the `SJADMIN` cookie
in devtools and reload: same redirect, but this time the lazy boot never opened a session at all.

**6. Force the real idle timeout.** Log in, leave the tab untouched for 31 minutes, reload. Same login redirect — now
triggered by the clock check in `Auth::boot()` rather than a missing cookie. There is no shortcut that avoids editing
code; the comparison uses live `time()` against `last_seen`. (Faster route on the dev stack: edit the session file's
`last_seen` directly — `docker compose exec -u www-data web php -r '…'` — then make one request.)

> **Careful — "leave the tab untouched" is not the same as "make no requests".** If the page is polling in the
> background, every poll used to count as activity and the idle timeout never fired; an open dashboard stayed signed in
> for ten hours. Background endpoints now declare `SJ_PASSIVE_REQUEST` so their requests are *checked* against the
> timeouts but never *renew* them. See `SECURITY.md` SEC-07.

**7. Watch the API disagree politely.** While logged out, `curl -i http://localhost:8090/admin/api/images.php` returns
`401` with `{"ok":false,"error":"Not authenticated"}` — the JSON branch at `api/_bootstrap.php:22-24`. Same missing
session, different response shape, because APIs must not redirect.

---

## 9. Where to read more

**In this repo**

- [`../01-fundamentals.md`](../01-fundamentals.md) — Part I, "Sessions & Cookies": the gentler first pass.
- [`../02-security.md`](../02-security.md) — XSS, CSRF and the other attacks these cookie flags defend against.
- [`../03-what-we-built.md`](../03-what-we-built.md) — the **S3** section is the changelog entry for these timeouts.
- [`../../../SECURITY.md`](../../../SECURITY.md) — **SEC-07** (fixation / hijack / timeouts) is the normative
  requirement this code implements; **SEC-05** covers CSRF and **SEC-22** the private save path.

**External**

- [PHP manual — Session Security](https://www.php.net/manual/en/session.security.php) — including why
  `session_regenerate_id()` matters.
- [PHP manual — `session_set_cookie_params()`](https://www.php.net/manual/en/function.session-set-cookie-params.php)
- [MDN — Using HTTP cookies](https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies) — the clearest explanation of
  `Set-Cookie`, `SameSite`, `HttpOnly` and `Secure`.
- [OWASP — Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
  — the industry checklist; read it beside `src/Admin/Auth.php` and tick items off.
