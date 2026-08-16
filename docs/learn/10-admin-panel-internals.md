# Inside the Admin Panel — Login, Sessions, and How Edits Reach the Database

> **Read this after** [`09-request-lifecycle.md`](09-request-lifecycle.md).
> **You need:** very basic PHP. Every security term is explained where it first appears.
> **You will be able to:** explain how the school's staff log in, what a session physically
> *is* and where to find it on disk, and trace a single keystroke in the admin panel all
> the way into a MySQL row.

The public site is read-only: it fetches content and prints it. The admin panel is the
half that **writes**, and writing is where every serious mistake in web development lives.
This document is the guided tour.

---

## 0. The two ways to edit this site

There are two front ends onto the same machinery:

| | **The panel** | **The live-edit overlay** |
|---|---|---|
| Where | `http://localhost:8090/admin/` | the public site itself, while logged in |
| Looks like | a dashboard with a sidebar | the real page, with editable bits outlined |
| Built in phases | S2–S4, P3, C1–C9, M1–M4 | O1–O2 (see [`06-stage-f.md`](06-stage-f.md)) |
| Entry files | `public_html/admin/*.php` | `views/partials/admin-bar.php` + `public_html/js/admin.js` |

They look completely different and share **everything underneath**: the same session, the
same CSRF token, the same API, the same registry, the same validation, the same audit log.
There is exactly one write path into this database from the web, and both doors open onto it.

---

## 1. Authentication — how someone proves who they are

**Authentication** = *who are you?* **Authorisation** = *what are you allowed to do?*
They are different questions and this codebase answers them in different places.

### 1.1 Where the user record lives

[`database/schema.sql:6-19`](../../database/schema.sql):

```sql
CREATE TABLE IF NOT EXISTS admin_users (
  id            TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(100) NOT NULL DEFAULT 'Administrator',
  role                 VARCHAR(20) NOT NULL DEFAULT 'owner',
  must_change_password TINYINT(1)  NOT NULL DEFAULT 0,
  password_changed_at  DATETIME NULL,
  failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until  DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Read the column list as a list of decisions:

- **`password_hash`, not `password`.** The name is deliberate. There is nowhere in this
  schema to put a real password, because a real password is never stored. More below.
- **`failed_logins` + `locked_until`** — brute-force defence, §1.4.
- **`must_change_password`** — the forced-rotation gate, §1.5.
- **`role`** exists and defaults to `'owner'`, but nothing reads it yet. It was added in
  phase S2 so that a future "editor vs owner" split needs no migration. Be honest about
  this when you read the code: today `is_admin()` is a single yes/no.
- **This table is deliberately *not* in the content registry** (`CLAUDE.md`). The admin API
  can edit twenty-odd tables; it can never touch this one or `settings`. That is a hard
  boundary, not a convention — see §5.

### 1.2 Passwords are never stored

Three words that are constantly confused:

| Word | What it is | Reversible? | Right for passwords? |
|---|---|---|---|
| **Encoding** (base64) | a different way to write the same bytes | yes, trivially | no — it is not security at all |
| **Encryption** | scrambled with a key | yes, with the key | no — if the key leaks, every password leaks |
| **Hashing** | a one-way fingerprint | **no** | **yes** |

A hash turns `Sunflower$2026` into a fixed-length fingerprint. You cannot run it backwards.
To check a login you hash what was typed and compare fingerprints.

Two more ideas make it actually safe:

- **Salt** — a random value mixed in, different for every user. Without it, two people with
  the same password get the same fingerprint, and an attacker can pre-compute a giant
  table of `fingerprint → password` (a *rainbow table*) once and crack everyone. With a
  salt, that table is worthless.
- **Slowness on purpose** — MD5 and SHA-1 are *fast*, which is a catastrophe here: a
  modern GPU tries billions per second. bcrypt is designed to be slow and to have a
  tunable **cost**. If a hash takes 50 ms, a billion guesses take a year and a half.

PHP does all of this for you. Hashing, in
[`public_html/admin/password.php:40`](../../public_html/admin/password.php):

```php
password_hash($new, PASSWORD_DEFAULT)
```

Verifying, in [`public_html/admin/login.php:25`](../../public_html/admin/login.php):

```php
password_verify($password, $user['password_hash'])
```

`password_hash()` generates the salt itself and stores it *inside* the output string. A
stored hash looks like this — four fields separated by `$`:

```
$2y$ 10 $ N9qo8uLOickgx2ZMRZoMye        IjZAgcfl7p92ldGxad68LJZdL17lhWy
└┬─┘ └┬┘  └────────┬────────────┘        └──────────────┬──────────────┘
 │    │            │                                    │
 │    │            └ 22-char salt                       └ the fingerprint
 │    └ cost — 2^10 rounds (PHP 8.3's PASSWORD_DEFAULT)
 └ algorithm: bcrypt
```

So you never need a separate salt column, and `password_verify()` knows how to re-derive
everything from the stored string. **Never compare hashes with `==`** — always use
`password_verify()`.

### 1.3 The login flow, line by line

[`public_html/admin/login.php`](../../public_html/admin/login.php) is 103 lines and worth
reading in full. The logic:

```php
require dirname(__DIR__) . '/bootstrap.php';
sj_session_boot(true);          // (1) force a session — we may be about to create one
sj_admin_headers();             // (2) CSP / X-Frame-Options / nosniff

if (is_admin()) {               // (3) already logged in? don't show a login form
    header('Location: /admin/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {   // (4) CSRF
        $error = 'Session expired — please try again.';
    } else {
        $st = db()->prepare('SELECT * FROM admin_users WHERE username = ?');
        $st->execute([$username]);                            // (5) parameterised lookup
        $user = $st->fetch();

        $locked = $user && $user['locked_until'] !== null
                        && strtotime($user['locked_until']) > time();   // (6)

        if (!$locked && $user && password_verify($password, $user['password_hash'])) {
            …success…
        } else {
            …failure…
        }
    }
}
```

**The success branch** ([lines 26-40](../../public_html/admin/login.php)) does six things,
and the order matters:

```php
db()->prepare('UPDATE admin_users SET failed_logins = 0, locked_until = NULL,
               last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

session_regenerate_id(true);           // ← new session id. See §2.4 (session fixation)
$_SESSION['admin_id']       = (int)$user['id'];
$_SESSION['admin_name']     = $user['display_name'];
$_SESSION['edit_mode']      = 0;
$_SESSION['must_change_pw'] = (int)($user['must_change_password'] ?? 0);
$_SESSION['login_at']       = time();
$_SESSION['last_seen']      = time();
$_SESSION['last_regen']     = time();
unset($_SESSION['csrf']);
csrf_token();                          // ← mint a fresh CSRF token for the new session
sj_audit('login.ok');
header('Location: ' . (!empty($_SESSION['must_change_pw']) ? '/admin/password.php' : '/admin/'));
exit;
```

Notice: **the counters are reset only on success**, the session id is replaced *before*
anything is written into the session, and the CSRF token is thrown away and re-minted so
that a token an attacker might have planted pre-login is worthless.

### 1.4 The failure branch is the interesting one

[`login.php:41-55`](../../public_html/admin/login.php):

```php
} else {
    // Every failure (unknown user, wrong password, OR locked) responds
    // identically — no username enumeration, no "locked" oracle (SEC-06).
    // Increment the counter only for a real, not-yet-locked account, and
    // PERSIST it (never reset to 0 on lock) so lockouts actually hold.
    if ($user && !$locked) {
        $fails = (int)$user['failed_logins'] + 1;
        $lock  = $fails >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
        db()->prepare('UPDATE admin_users SET failed_logins = ?, locked_until = ? WHERE id = ?')
            ->execute([$fails, $lock, $user['id']]);
    }
    sj_audit('login.fail', null, null, mb_substr($username, 0, 50));
    sleep(1);   // uniform delay on all failure paths (also masks bcrypt timing)
    $error = 'Invalid username or password.';
}
```

Four separate defences are packed into fifteen lines:

1. **One message below the lock threshold.** If a wrong username said "no such user" and a
   wrong password said "wrong password", an attacker could harvest valid usernames without
   ever guessing a password. That is **username enumeration**. Below 5 failures, our
   message never distinguishes.
2. **The lock is visible — carefully.** (Revised in N1, an owner decision: the original
   silent lock left the real owner guessing, which actually happened.) After the 5th
   failure — or any attempt while locked, correct password included — the page says
   *"temporarily locked… wait about N minutes"*. To keep that text from becoming a cheap
   username oracle, a **session-scoped shadow counter** (`$_SESSION['sj_lf']`) shows the
   *identical* message for ANY username string after 5 tries in that session, real or not.
   The accepted residual (a cross-session probe of an already-locked account) is
   documented in `SECURITY.md` SEC-06.
3. **`sleep(1)` on every failure path.** Two purposes. It slows down automated guessing,
   and it flattens timing: `password_verify()` on a real hash takes measurable time while
   "user not found" returns instantly, so without the sleep an attacker could tell the two
   apart with a stopwatch. This is a **timing side channel**, and the fix is to make every
   path take the same visible time.
4. **The counter is persisted, not reset.** This one is a fixed bug, and it is a good
   lesson. The original code wrote `$fails >= 5 ? 0 : $fails` — it zeroed the counter at
   the moment it set the lock. Fifteen minutes later the lock expired *and* the counter was
   0, so the attacker got another five free guesses, forever. The lockout looked correct in
   testing and did nothing in practice. Phase S3 fixed it. **Security code that is never
   adversarially tested is decoration.**

The password never appears in the audit log — only a truncated username
(`mb_substr($username, 0, 50)`).

### 1.5 Forced password change

A CMS that ships with `admin` / `admin123` is a CMS that gets defaced. Phase S2 made that
impossible to leave in place:

- [`database/seed.php`](../../database/seed.php) creates the first user with
  `must_change_password = 1`. In dev the password is a known default; with `--prod` it
  generates a random one and prints it once.
- On login, that flag is copied into `$_SESSION['must_change_pw']`.
- **Two guards then block everything else** until it is cleared:

  ```php
  // public_html/admin/_layout.php:13-16  — every panel screen
  if (!empty($_SESSION['must_change_pw'])) { header('Location: /admin/password.php'); exit; }

  // public_html/admin/api/_bootstrap.php:27-29 — every API endpoint
  if (!empty($_SESSION['must_change_pw'])) { api_fail('Password change required', 403); }
  ```

  Blocking the *screens* alone would be theatre — someone could still call the API
  directly. Both doors are gated.

- [`public_html/admin/password.php`](../../public_html/admin/password.php) is deliberately
  **standalone**: it does its own bootstrap and guards rather than using `_layout.php`.
  If it used the shared layout, the layout's own "must change password → redirect to
  password.php" rule would redirect password.php to itself, forever. A redirect loop is the
  classic bug in forced-rotation flows, and the comment at the top of the file says so.

Its rules ([lines 30-45](../../public_html/admin/password.php)): the **current** password
must be re-verified (so a hijacked open session cannot silently change the password), the
new one must be ≥ 12 characters, must differ from the current one, and must match the
confirmation. On success it writes the new hash, clears the flag, stamps
`password_changed_at`, calls `sj_audit('password.change')`, and regenerates the session id
again.

> **There is no "forgot password" e-mail flow** — shared hosting gives no reliable
> outbound mail, and a broken reset flow is a bigger hole than none. Instead (since N1)
> recovery is proven by **filesystem control**: creating `config/recovery-token.txt` above
> the webroot (mPanel file manager) arms a one-time `/admin/recover.php` page that sets a
> new password, clears any lockout and deletes itself. A 404 otherwise. Dev shortcut:
> `database/reset-admin-password.php` (CLI). Runbook: `DEPLOY.md` §9; threat notes: SEC-24.

---

## 2. Sessions — the part everyone gets wrong

### 2.1 The problem

HTTP has no memory. Each request arrives as a total stranger. So after the principal logs
in, how does the *next* request know it is still them?

The answer is a **cookie**: on login the server sends a small value, the browser stores it,
and the browser attaches it to every later request to that site automatically.

But you must never put "logged in as admin" *in* the cookie — the browser owns the cookie
and its user can edit it. So instead the cookie holds only a long random **session id**,
and the real data sits on the server, indexed by that id. That is a **session**.

Analogy: the cookie is a **cloakroom ticket**. It is a meaningless number. The coat is in
the cloakroom. Anyone holding the ticket gets the coat — which is exactly why the ticket
must be unguessable, why it must travel only over HTTPS, and why it must be replaced when
your status changes.

### 2.2 Creating one — `Auth::boot()`

Everything lives in [`src/Admin/Auth.php:17-63`](../../src/Admin/Auth.php).

```php
public static function boot(bool $force = false): void
{
    static $booted = false;
    if ($booted || \PHP_SAPI === 'cli' || \session_status() === \PHP_SESSION_ACTIVE) {
        $booted = true;
        return;
    }
    if (!$force && empty($_COOKIE['SJADMIN'])) {
        return; // public visitor without an admin cookie: zero session cost
    }
    …
}
```

**The lazy boot is the first design decision.** `public_html/bootstrap.php:25` calls
`sj_session_boot(false)` on *every* public page. With `false`, a visitor who has no
`SJADMIN` cookie never starts a session at all: no file written to disk, no `Set-Cookie`
header, no cost. Admin pages call `sj_session_boot(true)` because they are about to need
one. A school site serving thousands of parents does not need thousands of session files.

Then the cookie is configured **before** the session starts — this order is mandatory,
because `session_start()` is what emits the `Set-Cookie` header:

```php
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    || !empty($cfg['force_secure_cookies']);
\session_name('SJADMIN');
\session_set_cookie_params([
    'lifetime' => 0,      // dies when the browser closes
    'path'     => '/',
    'httponly' => true,   // JavaScript cannot read it
    'samesite' => 'Lax',  // not sent on cross-site POSTs
    'secure'   => $secure,// HTTPS only
]);
\session_start();
```

Each flag earns its place:

| Setting | What it does | What it stops |
|---|---|---|
| `session_name('SJADMIN')` | names our cookie instead of the default `PHPSESSID` | small: it stops advertising "this is stock PHP" |
| `lifetime => 0` | session cookie — gone when the browser closes | a forgotten login on a shared school computer |
| `httponly => true` | JavaScript **cannot** read `document.cookie` for it | an XSS bug turning into full session theft |
| `samesite => 'Lax'` | not attached to cross-site POST requests | a large class of CSRF attacks (defence in depth — we still check tokens) |
| `secure => …` | only sent over HTTPS | someone on the same Wi-Fi reading the cookie in plain text |

The `$secure` expression handles three realities: real HTTPS (`$_SERVER['HTTPS']`), sitting
behind a proxy or CDN that terminates TLS and forwards plain HTTP (`X-Forwarded-Proto`),
and a config override for hosts that report neither. Get this wrong in the "too strict"
direction and nobody can log in over plain HTTP in dev; get it wrong in the "too loose"
direction and the cookie leaks. Hence all three.

### 2.3 What we actually store

Set at login, plus two more added later:

| Key | Type | Set where | Purpose |
|---|---|---|---|
| `admin_id` | int | `login.php:29` | **the** flag — `is_admin()` is "is this non-empty?" |
| `admin_name` | string | `login.php:30` | shown in the panel top bar |
| `edit_mode` | 0/1 | `login.php:31`, toggled in `admin/editmode.php:18` | is the live-edit overlay on? |
| `must_change_pw` | 0/1 | `login.php:32` | the forced-rotation gate |
| `login_at` | timestamp | `login.php:33` | absolute-timeout clock |
| `last_seen` | timestamp | `login.php:34`, refreshed in `Auth::boot()` | idle-timeout clock |
| `last_regen` | timestamp | `login.php:35` | when the id was last rotated |
| `csrf` | 64 hex chars | `Csrf::token()` on first use | the anti-CSRF secret |

`is_admin()` is deliberately tiny
([`src/Admin/Auth.php:76-79`](../../src/Admin/Auth.php)):

```php
public static function isAdmin(): bool
{
    return \session_status() === \PHP_SESSION_ACTIVE && !empty($_SESSION['admin_id']);
}
```

and `isEdit()` is `isAdmin()` **and** `edit_mode`. So an admin who has not switched edit
mode on browses the site exactly like a visitor.

### 2.4 How long a session lives

Three constants ([`src/Admin/Auth.php:13-15`](../../src/Admin/Auth.php)):

```php
public const SESSION_IDLE_MAX = 1800;   // 30 min of inactivity
public const SESSION_ABS_MAX  = 43200;  // 12 h hard cap
public const SESSION_REGEN    = 900;    // rotate the session id every 15 min
```

And the enforcement, which runs on **every request** that has a logged-in session
([lines 49-62](../../src/Admin/Auth.php)):

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

Two different clocks, for two different risks:

- **Idle timeout (30 min)** — the office computer left unlocked while everyone goes to
  assembly. Resets on every request.
- **Absolute timeout (12 h)** — never resets. Even someone actively working is logged out
  after twelve hours. This bounds the damage window of a stolen session id: it cannot be
  useful forever just because the thief keeps it warm.

**Session id rotation (every 15 min)** defends against **session fixation**. That attack
runs backwards from what you would expect: the attacker does not steal your id, they
*give* you one — plant a known session id in your browser, wait for you to log in with it,
then use that same id, now authenticated. `session_regenerate_id(true)` at login makes the
planted id worthless; rotating periodically shrinks the value of any id captured later.
The `true` argument deletes the old session file rather than leaving it usable.

**One elegant detail:** when a session expires, `Auth::kill()` runs and nothing else
happens — no exception, no redirect from inside the session layer. The session is simply
gone, so `is_admin()` returns `false`, and every caller's *existing* behaviour fires
naturally: `_layout.php` redirects to login, the API returns `401` JSON, a public page
renders as a visitor. Three correct outcomes, zero special-case code.

**A trap worth remembering — "activity" is not the same as "a request".** The dashboard
polls a vitals endpoint every 20 seconds. Each poll ran the code above, which set
`last_seen = now`, so an open dashboard tab kept renewing the session and the 30-minute
idle timeout could *never* fire — the owner was still signed in after ten hours. The fix:
a background heartbeat defines `SJ_PASSIVE_REQUEST` before the bootstrap, and
`Auth::boot()` then still **enforces** both timeouts but does **not** treat the request as
activity. If you ever add a polling endpoint, declare that constant — otherwise you switch
the idle timeout off for everyone without touching a single line of the timeout code.

### 2.5 Destroying one

[`src/Admin/Auth.php:66-74`](../../src/Admin/Auth.php):

```php
public static function kill(): void
{
    $_SESSION = [];                                    // 1. clear the data
    if (\ini_get('session.use_cookies')) {             // 2. expire the cookie
        $p = \session_get_cookie_params();
        \setcookie(\session_name(), '', \time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    \session_destroy();                                // 3. delete the server-side file
}
```

All three steps are needed. Clearing `$_SESSION` alone leaves the file and the cookie.
`session_destroy()` alone leaves the browser cheerfully presenting a dead ticket on every
request. Setting the expiry to a **past** time is how you tell a browser to delete a cookie.

And logout itself ([`public_html/admin/logout.php`](../../public_html/admin/logout.php)):

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
    header('Location: /admin/');
    exit;
}
if (is_admin()) { sj_audit('logout'); }
sj_session_kill();
header('Location: /admin/login.php');
```

Logout is a **POST with a CSRF token**, not a link. It used to be a plain `<a href>`, which
meant any page on the internet could log our admin out by embedding
`<img src="…/logout.php">`. That is only a nuisance rather than a breach — but it violates
the rule *GET must never change state*, and rules with exceptions stop being rules. The
sidebar now renders a small form ([`_layout.php:108-111`](../../public_html/admin/_layout.php)).

### 2.6 **Where to actually see all this**

This is the part people never get shown.

**The cookie** — in the browser: devtools → **Application** → Storage → **Cookies** →
`http://localhost:8090`. You will see one row named `SJADMIN` with a 26-character random
value and the `HttpOnly` box ticked. Try reading it from the console:

```js
document.cookie      // SJADMIN is NOT in this list — that is HttpOnly working
```

**The session data** — inside the container. This dev stack leaves
`session.save_path` empty, which means PHP uses the system temp directory:

```bash
docker compose exec -T web php -r 'echo ini_get("session.save_path") ?: "(default: /tmp)", "\n";'
docker compose exec -T web ls -la /tmp
```

You will see files like:

```
-rw-------  1 www-data www-data  77 Aug  9 04:13 sess_3bce8dcfe95c3b7af890a584de9b3a67
```

The filename after `sess_` **is the value in your cookie**. Mode `600` means only the web
server user can read it. The contents are PHP's session serialisation — `key|type:value;`
pairs, e.g.:

```
admin_id|i:1;admin_name|s:13:"Administrator";edit_mode|i:0;login_at|i:1786000000;csrf|s:64:"…";
```

Read one (on your dev stack only) with:

```bash
docker compose exec -T web sh -c 'cat /tmp/sess_<paste-your-id-here>'
```

Do that once. Seeing `admin_id|i:1;` sitting in a file on disk makes the whole model
concrete in a way no diagram does — and it also makes obvious why the save path matters on
a **shared** host, where other tenants might read a world-readable temp directory. That is
why `config/config.php` supports a `session_save_path` pointing at a private
`~/tmp/sessions` directory in production ([`Auth::boot()` lines 27-30](../../src/Admin/Auth.php)).

**The audit trail** — everything above is transient. What persists is in the database:

```bash
docker compose exec -T db mysql -ustjosephs -pstjosephs_pw stjosephs \
  -e "SELECT id, admin_id, action, entity, entity_id, detail, ip, created_at
      FROM audit_log ORDER BY id DESC LIMIT 20;"
```

*(Credentials come from `.env.example` / `docker-compose.yml` — dev values only.)*

---

## 3. Authorisation — the guards, and where they live

Three entry points into admin functionality, three guards, all in the first ten lines of
their file:

| Entry point | File | Guard |
|---|---|---|
| A panel screen | [`admin/_layout.php:8-16`](../../public_html/admin/_layout.php) | not admin → redirect to login; password pending → redirect to password.php |
| An API call | [`admin/api/_bootstrap.php:22-37`](../../public_html/admin/api/_bootstrap.php) | not admin → `401` JSON; password pending → `403`; non-GET without a valid CSRF token → `403` |
| The edit-mode toggle | [`admin/editmode.php:8-16`](../../public_html/admin/editmode.php) | must be POST + admin + valid CSRF |

`editmode.php` is worth a second look because it handles a subtle risk — it redirects
somewhere after toggling, and the "somewhere" comes from the request:

```php
$return = (string)($_POST['return'] ?? '/');
if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//')
    || str_contains($return, "\n") || str_contains($return, "\r")) {
    $return = '/';
}
header('Location: ' . $return);
```

Four checks, four attacks. It must start with `/` (so `https://evil.example` cannot be
injected — an **open redirect**, the classic phishing assist). It must not start with `//`
(which browsers read as protocol-relative, i.e. another site). And it must contain no
newline or carriage return (**header injection** — a `\r\n` in a header value lets an
attacker append headers or a whole response body). Anything suspicious falls back to `/`.

The general rule this file illustrates, from `CLAUDE.md`: *no request-derived string ever
reaches `include`, `require`, a filesystem path, or `header('Location:')`.*

---

## 4. How an edit reaches the database

Now the second question: **how does the DB storage happen?** Follow one field.

An editor is on **Admin → Hero Carousel**, changes a slide's caption to `Annual Day 2026`,
and tabs away.

### Step 1 — The browser sends one small request

The panel's JavaScript reads the token from
`<meta name="sj-csrf">` ([`_layout.php:88`](../../public_html/admin/_layout.php)) and posts:

```http
POST /admin/api/index.php?r=field
X-CSRF-Token: 9f2c…  (64 hex chars)
Content-Type: application/json

{"entity":"hero_slide","id":3,"field":"caption_title","value":"Annual Day 2026"}
```

Note what is **not** in that body: no table name, no column name in SQL terms, no
credentials. Just an *entity* name, a row id, a *field* name, and the value.

### Step 2 — Route whitelist

[`admin/api/index.php`](../../public_html/admin/api/index.php) looks `field` up in a
hardcoded nine-entry array and `require`s `field.php`. An unknown `r` gets a 404. The
request never names a file.

### Step 3 — The four guards

`field.php`'s first line is `require __DIR__ . '/_bootstrap.php'`, which sets JSON headers
and `Cache-Control: no-store`, then checks: logged in → password gate → CSRF token. Any
failure exits with JSON and a status code before a single line of business logic runs.

### Step 4 — The registry resolves names

[`admin/api/field.php:10-15`](../../public_html/admin/api/field.php):

```php
$reg = api_entity($entity);              // 'hero_slide' → definition, or fail
$def = $reg['fields'][$field] ?? null;   // 'caption_title' must be declared
if ($def === null || $id <= 0) { api_fail('Unknown field'); }
$value = api_validate_field($entity, $field, $def, $in['value'] ?? null);
```

`api_entity()` looks the name up in
[`src/Content/Registry.php`](../../src/Content/Registry.php), a single PHP array that maps
a safe entity name to a real table and a typed field list:

```php
'hero_slide' => [
    'table' => 'hero_slides', 'orderable' => true, 'creatable' => true, 'deletable' => true,
    'parent' => 'page_id',
    'fields' => [
        'caption_title' => ['type' => 'text', 'max' => 120, 'label' => 'Caption title'],
        'caption_text'  => ['type' => 'text', 'max' => 255, 'label' => 'Caption text'],
        'button_label'  => ['type' => 'text', 'max' => 40,  'label' => 'Button label', 'nullable' => true],
        'button_url'    => ['type' => 'url',  'max' => 255, 'label' => 'Button link',  'nullable' => true],
        'image_id'      => ['type' => 'image', 'preset' => 'hero_16x7', 'label' => 'Slide image', 'required' => true],
        'is_active'     => ['type' => 'bool', 'label' => 'Visible on site'],
    ],
],
```

If the request had said `"entity":"admin_users"`, `api_entity()` would fail with *Unknown
entity* — because `admin_users` is not in this array and, per `CLAUDE.md`, never will be.
If it had said `"field":"password_hash"`, the field lookup would fail. **The set of things
the web can write is a list a human wrote down.**

### Step 5 — Validation by declared type

`api_validate_field()` ([`_bootstrap.php:57-119`](../../public_html/admin/api/_bootstrap.php))
switches on the type from the registry:

| Type | What happens on save |
|---|---|
| `text` | `trim(strip_tags(...))`, then the `max` length check |
| `html` | `sj_sanitize_html()` — a **frozen** whitelist: `b, strong, i, em, br, p, span.hl-gold`. Everything else is stripped. |
| `url` | rejects `javascript:`, `data:`, `vbscript:` and control characters |
| `int` | numeric check plus `min`/`max` |
| `enum` | must be one of the listed values |
| `bool` | coerced to `0`/`1` |
| `image` | must be an id that exists in `images` |

Note where sanitising happens: **on write, once**, not on every page render. A `_html`
column is trusted at output time precisely *because* nothing untrusted can get into it.
That is the only exception to the "everything goes through `e()`" rule — see
[`topics/output-escaping-and-xss.md`](topics/output-escaping-and-xss.md).

### Step 6 — The SQL

```php
$st = db()->prepare("UPDATE {$reg['table']} SET `$field` = ? WHERE id = ?");
$st->execute([$value, $id]);
```

Study this line, because it looks like the thing you have been told never to do. The table
and column **are** string-interpolated. It is safe because of a distinction worth
memorising:

- **Values** can be `?` placeholders. The database receives the query and the data
  separately, so data can never be read as SQL. Both `$value` and `$id` are bound.
- **Identifiers** (table and column names) *cannot* be placeholders — SQL simply does not
  allow it. So they must come from somewhere trustworthy. Here they come from the registry,
  which is PHP source code. Nothing a user typed is ever interpolated.

Then a nicety: if `rowCount()` is 0, that might mean "no such row" *or* "the value was
already identical", so it runs a `SELECT COUNT(*)` to tell them apart and only 404s if the
row genuinely does not exist.

### Step 7 — Audit, then reply

```php
sj_audit('field.save', $entity, $id, $field);
api_out(['value' => $value]);
```

[`src/Admin/Audit.php`](../../src/Admin/Audit.php) inserts one row into `audit_log` with
the admin id from the session, the action, the entity and id, a truncated detail, and
`REMOTE_ADDR`. Two deliberate choices in that tiny class: it records the **field name, not
the value** (so the log never becomes a copy of your content or a place secrets leak), and
it wraps everything in `try { … } catch (\Throwable $e) { }` — **logging must never break
the action being logged.**

### Step 8 — The page shows it

The panel updates the field in place. The public page shows the new caption on its next
render, because `repo_hero_slides()` reads the same row. There is no cache to purge —
`.htaccess` sets `Cache-Control: no-cache` on `.php` responses precisely so that content
edits appear immediately.

### The same path, four more shapes

| Route | Endpoint | What it does |
|---|---|---|
| `?r=field` | `field.php` | update one field (the trace above) |
| `?r=item` | `item.php` | `get` / `create` / `update` / `delete` a whole row. `create` respects `creatable`, fills the `parent` foreign key from the creation preset, and appends `position = MAX(position)+1`; `delete` respects `deletable`. |
| `?r=order` | `order.php` | save a drag-and-drop reorder (an array of ids → `position` values) |
| `?r=upload` / `?r=recrop` / `?r=image` / `?r=images` | media endpoints | upload with GD renditions, re-crop, metadata/usage/**guarded delete**, and the media-library listing |
| `?r=link` | `link.php` | attach/detach/reorder photos in a gallery or carousel (`image_links`) |
| `?r=settings` | `settings.php` | site settings — see §5 |

---

## 5. The two tables the API can never touch

`CLAUDE.md` states it flatly: **`admin_users` and `settings` are never registered.**

For `admin_users` the reason is obvious: a generic "update any field of any entity"
endpoint that could reach `password_hash` would turn any small bug into full account
takeover.

`settings` is subtler. It is a key/value table — exactly the shape that tempts you to
expose it generically. But settings decide things like the contact e-mail and the footer
address, and a key/value table has no per-key type information for the registry to
validate against. So settings get their **own** endpoint,
[`admin/api/settings.php`](../../public_html/admin/api/settings.php), with its own
explicit whitelist of editable keys.

The pattern to take away: **when something is powerful, make its surface a list, not a
rule.** Generic mechanisms are wonderful right up to the point where they can reach
something they should not.

---

## 6. The live-edit overlay, briefly

Phases O1/O2 added editing on the real page. The mechanism is small:

1. PHP emits marker attributes next to editable content. `src/View/EditAttrs.php` provides
   `ed_field()`, `ed_item()`, `ed_add()`, `ed_img()`, which templates call to render
   `data-edit-*` attributes.
2. Those attributes, the CSRF `<meta>` tag, the overlay CSS and `js/admin.js` are emitted
   **only** inside `if (is_admin())` blocks in
   [`views/shell.php:66-70` and `92-97`](../../views/shell.php). A visitor receives none of it.
3. Clicking an outlined element opens an inline editor; saving posts to the **same**
   `/admin/api/index.php` with the **same** guards and the **same** registry validation.

So the overlay adds a user interface, not a second write path. When you audit "how can
this database be written to from the web?", the answer stays: *one front controller, nine
routes, four guards.*

The full story is [`06-stage-f.md`](06-stage-f.md).

---

## 7. Operating it

**Reset a forgotten password (dev).** Generate the hash *in PHP*, then update:

```bash
docker compose exec -T web php -r 'echo password_hash("a-long-new-password", PASSWORD_DEFAULT), "\n";'
```

Then run an `UPDATE admin_users SET password_hash = '<paste>', must_change_password = 1 WHERE username = 'admin';`
through the database client.

> **Trap:** do **not** try to generate and apply this in one shell pipeline through
> `mysql -e`. A bcrypt hash starts `$2y$` and shells eat `$…` sequences, so you will store
> a mangled hash and lock yourself out with a confusing "wrong password". Generate it,
> read it with your eyes, paste it.

**In production** there is no shell. The same operation goes through mPanel's database
tool, with the hash generated somewhere you trust.

> **Never reset the live site owner's password to test something.** If the owner has
> already set their password, it is theirs.

**Unlock an account** — `UPDATE admin_users SET failed_logins = 0, locked_until = NULL WHERE username = '…';`
or simply wait fifteen minutes.

**Read the trail** — the `audit_log` query in §2.6. This is how you answer "who changed the
homepage photo?".

---

## 8. Honest gaps

Good documentation names what is missing. Today:

| Gap | Impact | Where it would go |
|---|---|---|
| ~~No self-service password reset~~ **closed by N1** | token-file recovery flow (`admin/recover.php`) + CLI reset; no e-mail dependency | — |
| `role` column is unused; `is_admin()` is one boolean | fine for 1–3 trusted staff; no "editor who cannot delete" | the column already exists — the check would go in `_layout.php` and `_bootstrap.php` |
| No admin screen for `audit_log` | you need database access to read the trail | a read-only panel section |
| No optimistic locking on writes | two people editing the same field at the same time: last write wins, silently | a `version`/`updated_at` check in `field.php` and `item.php` |
| No rate limit on the API | lockout covers login only | per-session throttling in `_bootstrap.php` |

None of these is urgent for one school with a handful of editors. All of them become real
the moment the site has ten. Knowing which is which is most of engineering judgement.

---

## 9. Try it yourself

```bash
./run.sh
```

1. **Fail a login five times** at <http://localhost:8090/admin/login.php>. The first four
   answers are identical; the fifth says "temporarily locked — wait about 15 minutes"
   (N1). Try five failures with a made-up username too — same lock message, thanks to the
   shadow counter. Then look at the row:
   `SELECT username, failed_logins, locked_until FROM admin_users;`
2. **Log in** and open devtools → Application → Cookies. Find `SJADMIN`. Confirm
   `document.cookie` in the console does **not** show it.
3. **Find your session file** with the `ls -la /tmp` command from §2.6 and match the
   filename to your cookie value. Read it.
4. **Edit something** with devtools → Network open. Inspect the `POST` to
   `/admin/api/index.php?r=field`: the `X-CSRF-Token` header, the tiny JSON body, the JSON
   reply.
5. **Break it on purpose.** Copy that request as fetch, delete the `X-CSRF-Token` header,
   run it in the console → `403 Invalid CSRF token`. Then try
   `{"entity":"admin_users","id":1,"field":"password_hash","value":"x"}` → *Unknown entity*.
6. **Read the audit log** and find the rows your last three steps just wrote.
7. **Watch a timeout.** Log in, then in the database set your session aside and simply wait
   — or temporarily lower `SESSION_IDLE_MAX` in `src/Admin/Auth.php`, reload after it
   passes, and watch the redirect to login. Put the constant back.

---

## 10. Where to read more

| Topic | Document |
|---|---|
| How any request is served | [`09-request-lifecycle.md`](09-request-lifecycle.md) |
| Sessions and cookies in depth | [`topics/sessions-and-cookies.md`](topics/sessions-and-cookies.md) |
| Password storage in depth | [`topics/passwords-and-authentication.md`](topics/passwords-and-authentication.md) |
| CSRF in depth | [`topics/csrf-protection.md`](topics/csrf-protection.md) |
| The registry | [`topics/the-registry-pattern.md`](topics/the-registry-pattern.md) |
| Escaping and sanitising | [`topics/output-escaping-and-xss.md`](topics/output-escaping-and-xss.md) |
| The audit log | [`topics/audit-logging.md`](topics/audit-logging.md) |
| The complete security catalogue | [`../../SECURITY.md`](../../SECURITY.md) — normative; its §4 checklist is mandatory before shipping |
| Rules for working in this repo | [`../../CLAUDE.md`](../../CLAUDE.md) |
