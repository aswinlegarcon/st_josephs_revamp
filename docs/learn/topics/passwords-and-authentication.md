# Passwords and Authentication

> **What you'll learn:** why we never store a password, how hashing differs from encryption and
> encoding, and every line of our real login, lockout and password-change code.
>
> **Prerequisites:** basic PHP (`if`, `$variables`, functions) and the idea that a server produces a
> page when a browser asks for a URL. No security background — every term is explained on first use.
>
> **Where it lives in our code:**
>
> | File | Role |
> |---|---|
> | `public_html/admin/login.php` | The whole login flow |
> | `public_html/admin/password.php` + `assets/password.js` | The "set a new password" screen and its strength meter |
> | `public_html/admin/_layout.php`, `admin/api/_bootstrap.php` | Guards on every admin page / API endpoint |
> | `src/Admin/Auth.php`, `src/Admin/Audit.php` | Session timeouts + headers; audit rows |
> | `database/schema.sql`, `migrations/001_admin_users_roles.sql` | The `admin_users` and `audit_log` tables |
> | `database/seed.php` | Creates the very first admin account |

---

## 1. The one-paragraph version

When a staff member types a username and password at `http://localhost:8090/admin/`, we do **not**
compare their password with a stored password — we never stored one. We stored a **hash**: a one-way
fingerprint made by PHP's `password_hash()`. On login, `password_verify()` re-scrambles what they
typed and checks it produces that same fingerprint. Around that single comparison sit five defences:
a CSRF token so the form must be ours, a 15-minute lockout after 5 wrong tries (announced with a
"wait about N minutes" message once it engages — N1), one identical error message for every failure
below that threshold, a one-second pause on every failure, and a brand-new session ID
once you are in. If the account is flagged `must_change_password`, every admin page *and* every API
call refuses to work until you set a real password. Every success and failure is written to
`audit_log` — the password never is.

---

## 2. The problem this solves

**Authentication** is proving *identity*: is the person at this keyboard really the account holder? A
username proves nothing — usernames are public. So we demand a **secret** only the real person should
know. If it matches we believe them, and record that belief in a **session** (server-side data tied
to a browser cookie) so we needn't ask again on every page. All of `login.php` is authentication.

**Authorisation** is the *next* question, asked only after authentication succeeds: now that we know
who you are, what may you do? An editor might change gallery captions but not delete users. Ours is
currently very simple — logged in means you can do everything; the check is just `is_admin()`
(`public_html/admin/_layout.php:8-11`, `public_html/admin/api/_bootstrap.php:22-24`). Groundwork for
something finer exists as a `role` column (`database/schema.sql:11`) that nothing reads yet.

> Think of a building. Authentication is the guard checking your face against your ID at the door.
> Authorisation is which floors your keycard opens once you're inside.

**Why passwords are never stored.** Databases leak — backups get copied to laptops, a bug dumps a
table, an old hosting account is breached. Real passwords in `admin_users` would hand an attacker the
school's admin account instantly, and (because people reuse passwords) probably that person's e-mail
too. The rule is absolute: **the server must verify a password without ever being able to read one.**

---

## 3. How it works in general

| | **Encoding** | **Encryption** | **Hashing** |
|---|---|---|---|
| Purpose | Change format for transport | Hide data from anyone without the key | One-way fingerprint |
| Reversible? | **Yes, by anyone** | **Yes, with the key** | **No, by anyone, ever** |
| Needs a secret? | No | Yes (the key) | No |
| Examples | Base64, URL-encoding | AES, HTTPS in transit | bcrypt, Argon2, SHA-256 |
| For passwords? | **Never** | **No** | **Yes** |

**Encoding** is not security: `YWRtaW4xMjM=` looks scrambled but is just Base64 for `admin123`, and
any website decodes it in a second. **Encryption** *is* security but the wrong shape — it is a locked
box, so the server must hold a key that turns every stored password back into plain text, and
stealing key plus database yields every password. Encryption suits data you must read again (an
account number you display); a password is never read back. **Hashing** is a one-way mincer: feed in
a password, get a fixed-size fingerprint, with no "un-mince". To check a login you mince the typed
password again and compare fingerprints — the server verifies without ever knowing.

**Salts and rainbow tables.** Hashing alone has a hole: it is *deterministic*, so the same input
always gives the same output. An attacker can pre-compute hashes for the ten million commonest
passwords once, store them in a lookup table (a **rainbow table**), steal your database and look each
hash up. Two users who chose the same password would also have identical hashes — itself a clue. A
**salt** kills both: random data, different for every password, mixed in before hashing. Identical
passwords now hash differently, and the pre-computed table is worthless because the attacker would
have to rebuild it per user. The salt is **not secret** — it is stored beside the hash, and its only
job is to make bulk pre-computation impossible. `password_hash()` generates and embeds it for you.

**"Slow by design."** MD5, SHA-1 and SHA-256 were built to be *fast* — great for checksumming a
download, a catastrophe for passwords. A modern graphics card computes **billions** of SHA-1 hashes
per second, so a stolen table falls in hours; MD5 and SHA-1 also have known mathematical weaknesses.
**Never use them for passwords.** **bcrypt** and **Argon2** are deliberately, tunably slow. bcrypt
takes a **cost**: cost 10 means the mixing loop runs 2¹⁰ = 1,024 times. A real login pays that once
— tens of milliseconds, unnoticed. An attacker pays it a billion times, and the attack goes from
hours to centuries. When hardware improves you raise the cost by one and they are back to square one.
The slowness *is* the feature.

**The two functions.** `password_hash($plain, PASSWORD_DEFAULT)` when setting a password;
`password_verify($typed, $hash)` at login. `PASSWORD_DEFAULT` means "whatever PHP currently considers
best" — on our PHP 8.3 (`Dockerfile:1`, `FROM php:8.3-apache`) that is bcrypt at cost 10. Here is a
**real** hash generated inside our container for the throwaway string `demo-password-123`:

```
$2y$10$dnQR9qMRBZYkxdo.hR14p.tKgN2jWM1Ni1WQ2X6Jv7TdbIxgaHyhO
└┬─┘└┬┘└──────────┬─────────┘└──────────────┬──────────────┘
 │   │            │                          └─ digest, 31 chars — the fingerprint
 │   │            └─ salt, 22 chars — random, unique to this hash
 │   └─ cost 10 → 2^10 rounds
 └─ algorithm marker: $2y$ = bcrypt
```

Exactly **60 characters** — which is why the column is `password_hash VARCHAR(255) NOT NULL`
(`database/schema.sql:9`), roomy enough for a longer Argon2 hash later. Algorithm, cost and salt all
travel *inside* the string; that is how `password_verify()` recreates the fingerprint without being
told anything.

---

## 4. How we use it — every place in this codebase

### 4.1 Where the account lives

```sql
CREATE TABLE IF NOT EXISTS admin_users (
  …
  password_hash VARCHAR(255) NOT NULL,
  role                 VARCHAR(20) NOT NULL DEFAULT 'owner',   -- multi-user readiness (SEC-08/09)
  must_change_password TINYINT(1)  NOT NULL DEFAULT 0,         -- forced rotation on first login
  password_changed_at  DATETIME NULL,
  failed_logins TINYINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until  DATETIME NULL,  …
```
— `database/schema.sql:6-19`

`failed_logins` counts consecutive wrong tries; `locked_until` is when the freeze expires;
`must_change_password` is the forced-rotation flag; `password_changed_at` records the last real
change. Those three plus `role` were added later, so a live site needs a migration rather than a
fresh `schema.sql` — `database/migrations/001_admin_users_roles.sql:10-16` runs the `ALTER TABLE`
then `UPDATE admin_users SET must_change_password = 1 WHERE password_changed_at IS NULL;`. That
`UPDATE` is the important half: adding a column is useless unless the existing account is flagged.

### 4.2 The login flow, step by step

| # | Step | Line | Why |
|---|---|---|---|
| 1 | Already logged in → dashboard | `login.php:6-9` | No point showing a form to someone signed in |
| 2 | CSRF token check | `login.php:14` | The POST must come from *our* form |
| 3 | Look the username up | `login.php:19-21` | Prepared statement — no SQL injection |
| 4 | Is the account locked *now*? | `login.php:23` | Computed before any password work |
| 5 | `password_verify()` | `login.php:25` | The actual identity check |
| 6 | Reset counters, stamp `last_login_at` | `login.php:26-27` | Success clears the failure history |
| 7 | `session_regenerate_id(true)` | `login.php:28` | Kills session fixation |
| 8 | Write the session keys | `login.php:29-35` | This is what "being logged in" *is* |
| 9 | Fresh CSRF token | `login.php:36-37` | The pre-login token is discarded |
| 10 | Audit `login.ok` | `login.php:38` | The CCTV recording |
| 11 | Redirect | `login.php:39` | `password.php` if forced, else the dashboard |

**Step 2 — CSRF.** *Cross-Site Request Forgery* is an attack where a page you are visiting silently
submits a form to *our* site using your browser. We block it with a random token kept in the session
and printed into the form: `if (!hash_equals(csrf_token(), $token))` (`login.php:14`).
`hash_equals()` compares in **constant time** — the same duration however many characters matched.
Plain `==` bails out at the first difference, and an attacker can measure those microseconds to guess
a token character by character. The token is 32 random bytes from `random_bytes()`
(`src/Admin/Csrf.php`).

**Step 3.** `db()->prepare('SELECT * FROM admin_users WHERE username = ?')` (`login.php:19`). The `?`
is a **placeholder**: the username travels to MySQL separately from the query text, so `' OR 1=1 --`
is looked up as literal text and finds nobody.

**Steps 4-5.** `$locked = $user && $user['locked_until'] !== null && strtotime($user['locked_until'])
> time();` (`login.php:23`) — user exists, a lock was set, and it is still in the future. There is no
unlock job; an expired lock is simply a `locked_until` in the past. The verify condition is then
`!$locked && $user && password_verify(…)` (`login.php:25`), and because PHP's `&&` short-circuits
left to right a locked account never even reaches `password_verify()`.

**Steps 6-9 — becoming logged in.**

```php
db()->prepare('UPDATE admin_users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')
    ->execute([$user['id']]);
session_regenerate_id(true);
$_SESSION['admin_id']       = (int)$user['id'];
$_SESSION['must_change_pw'] = (int)($user['must_change_password'] ?? 0);
$_SESSION['login_at']       = time();   // …plus last_seen, last_regen
unset($_SESSION['csrf']);
csrf_token(); // fresh token post-login
```
— `login.php:26-37`

`session_regenerate_id(true)` discards the old session ID, issues a new one, and — thanks to the
`true` — deletes the old session file. That defeats **session fixation**: an attacker who planted a
known session ID in your browser *before* login now holds a dead ID. The three timestamps feed the
lifetime rules in `src/Admin/Auth.php:13-15` — 30 minutes idle, 12 hours absolute, ID rotation every
15 minutes.

### 4.3 The failure path, and why every line is deliberate

> **Revised in N1 (2026-08-15):** the failure branch below has since grown lockout
> *visibility* — once an account locks (or a 5th failure locks it), the message becomes
> "temporarily locked — wait about N minutes", with a session-scoped shadow counter
> (`$_SESSION['sj_lf']`) showing the identical text for unknown usernames so the lock
> message is not a cheap in-session username oracle. Below the threshold everything
> here still holds. Read the current `login.php` alongside; residual documented in SEC-06.

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
    if (function_exists('sj_audit')) { sj_audit('login.fail', null, null, mb_substr($username, 0, 50)); } // S4
    sleep(1); // uniform delay on all failure paths (also masks bcrypt timing)
    $error = 'Invalid username or password.';
}
```
— `login.php:41-55`

**One message for three failures.** Unknown username, wrong password and locked account all produce
`'Invalid username or password.'` Saying "no such user" would let an attacker feed us a name list and
learn which exist — **username enumeration**, which halves their work. Saying "account locked" would
build a **lock oracle**: free confirmation that a username is real *and* under attack. Both help the
attacker and help a legitimate typist not at all, so we say neither.

**`sleep(1)` on every failure.** It caps guessing at roughly one try per second, and makes all
failures take the *same* time. Without it a wrong password costs ~50 ms (bcrypt actually ran) while
an unknown username costs ~1 ms (no bcrypt at all), so an attacker with a stopwatch could enumerate
usernames by response time. That is a **timing side channel**, and a flat one-second floor drowns it.

**The counter is persisted — and this was a real bug.** `SECURITY.md` SEC-06 records the original
defect: *"the fail counter resets to 0 when locking → 5 fresh attempts each 15-min window forever"*.
The old code zeroed `failed_logins` at the moment it set `locked_until`, so once 15 minutes expired
the attacker was back at zero with five more free guesses, forever, with no escalation — the lockout
looked present and did essentially nothing. Phase **S3** (`docs/learn/03-what-we-built.md:132-143`)
fixed it: the counter keeps climbing and only a *successful* login clears it, at `login.php:26`. So
the sixth, seventh and eightieth wrong attempt each re-lock the account for another 15 minutes.

**What is deliberately *not* logged.** The audit call records the attempted **username**, truncated
to 50 characters. It never records the password — not a fragment, not a hash of it.
`src/Admin/Audit.php:6-7` states the rule outright. Passwords in a log are plain-text passwords, and
logs get shipped around far more casually than databases.

### 4.4 Forced password change

A new account has a password somebody *else* chose — the seeder, or a colleague setting you up —
possibly sent by e-mail or chat. So it is flagged, and the flag is enforced in **three** places:
loaded into the session at `login.php:32`, then checked on every page and every API call.

```php
// public_html/admin/_layout.php:12-16 — Force the password change before any admin screen is reachable (SEC-08).
if (!empty($_SESSION['must_change_pw'])) { header('Location: /admin/password.php'); exit; }

// public_html/admin/api/_bootstrap.php:26-29 — Block all content APIs until the forced password change is done (SEC-08).
if (!empty($_SESSION['must_change_pw'])) { api_fail('Password change required', 403); }
```

The API guard matters as much as the page guard. The page guard only protects things a browser loads;
without the API guard someone could skip the UI and POST straight to `/admin/api/field.php`. **A
guard on the screen is not a guard on the system.**

**Why `password.php` must not use `_layout.php`.** Every other admin screen includes `_layout.php`
for the sidebar; `password.php` deliberately does not, and says why: *"Standalone page (NOT via
_layout.php) so the must_change_password guard there cannot cause a redirect loop."*
(`public_html/admin/password.php:3-4`). Trace it: if it included `_layout.php`, loading it would run
the guard, the guard would see the flag still set, and redirect to… `password.php`. Which runs the
guard again. The browser spins until `ERR_TOO_MANY_REDIRECTS` and the user can never reach the one
page that clears the flag. **A page that fixes a condition can never sit behind the guard for that
condition.** It still runs its own auth check (`password.php:9-12`) — standalone means "outside the
layout", not "unprotected".

### 4.5 The rules `password.php` enforces

```php
if (!$hash || !password_verify($current, $hash)) { $error = 'Your current password is incorrect.'; }
elseif (mb_strlen($new) < 12)  { $error = 'New password must be at least 12 characters.'; }
elseif ($new === $current)     { $error = 'New password must be different from the current one.'; }
elseif ($new !== $confirm)     { $error = 'New password and confirmation do not match.'; }
else {
    db()->prepare('UPDATE admin_users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?')
        ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['admin_id']]);
    if (function_exists('sj_audit')) { sj_audit('password.change'); }   // S4
    session_regenerate_id(true);  unset($_SESSION['must_change_pw']);
```
— `password.php:30-43` (branches reformatted onto single lines for space)

1. **Current password required**, even though you are logged in — stops a passer-by taking over an
   unlocked laptop.
2. **At least 12 characters** (`mb_strlen`, so an accented character counts as one character, not its
   byte count). Length beats punctuation: a long passphrase outlasts `P@ssw0rd!`.
3. **Must differ from the current one** — otherwise "change your password" is satisfied by retyping
   the compromised one.
4. **Must match the confirmation** — usability, not security. A typo in a masked field would lock you
   out with a password you can never reproduce.

On success the flag clears, `password_changed_at` is stamped, the session ID is regenerated again
(the credential changed, so the ID should too), and an audit row records *that* a change happened —
never what it changed to. The `minlength="12"` on the inputs (`password.php:109`, `:117`) is
convenience only; anyone can edit HTML or POST directly. **The server check at `password.php:32` is
the real one.** Client-side validation is a courtesy; server-side validation is the rule.

### 4.6 The strength meter

`public_html/admin/assets/password.js` draws four bars and ticks a live checklist. Its opening comment
explains why it is a file, not an inline `<script>`: *"External (not inline) so the admin CSP can stay
script-src 'self'."* (`password.js:2`). **CSP** (Content Security Policy) is a header telling the
browser which scripts it may run; ours (`src/Admin/Auth.php:98-102`) includes `script-src 'self'` —
our own domain only. Inline `<script>` blocks are the main vehicle for XSS, so allowing them would
blunt the policy.

```js
var rules = { len: v.length >= 12, case: /[a-z]/.test(v) && /[A-Z]/.test(v), num: /[0-9]/.test(v) };
if (v.length >= 16 && rules.len && rules.case && rules.num) score = 4; // long + complete → full
```
— `password.js:17-27`

Three rules cap you at 3 of 4 bars; the fourth only lights at 16+ characters — a deliberate nudge
toward length. Note that this file only *colours bars*. It rejects nothing; only `password.php` does.

### 4.7 How the first admin is created

```php
// Password source: SEED_ADMIN_PASS env → random (with --prod) → dev default.
// Whatever it is, must_change_password=1 forces a rotation on first login,
// so the seeded credential is never usable long-term (SECURITY.md SEC-08).
$envPass = getenv('SEED_ADMIN_PASS');   $prod = in_array('--prod', $argv, true);
if ($envPass !== false && $envPass !== '') { $pass = $envPass; }
elseif ($prod) { $pass = bin2hex(random_bytes(9)); } // 18 hex chars, shown once below
else           { $pass = 'admin123'; }               // local dev only
$pdo->prepare('INSERT INTO admin_users (username, password_hash, display_name, must_change_password) VALUES (?,?,?,1)')
    ->execute(['admin', password_hash($pass, PASSWORD_DEFAULT), 'Administrator']);
```
— `database/seed.php:58-71` (branches compacted)

Three sources in priority order: the `SEED_ADMIN_PASS` environment variable; else a random
18-hex-character password when run with `--prod`, printed once to the terminal; else the well-known
`admin123`, for local Docker only. `random_bytes()` is **cryptographically secure** — unlike `rand()`,
its output cannot be predicted from earlier values. Whichever branch runs, the `INSERT` hard-codes
`must_change_password` to `1`, so even `admin123` is a single-use key that gets you exactly as far as
`password.php`. The surrounding `if (!$exists)` (`seed.php:56-57`) makes the seeder **idempotent** —
running it twice does not overwrite the password you just set.

### 4.8 The audit trail

`audit_log` (`database/schema.sql:27-38`) holds `admin_id`, `action`, `entity`, `entity_id`, `detail`,
`ip`, `created_at`.

| Action | Written at | `admin_id` | `detail` |
|---|---|---|---|
| `login.ok` | `login.php:38` | the user's id | empty |
| `login.fail` | `login.php:52` | `NULL` — nobody is logged in | attempted username, ≤50 chars |
| `password.change` | `password.php:41` | the user's id | empty |
| `logout` | `logout.php:12` | the user's id | empty |

`admin_id` is nullable precisely so a *failed* login can be recorded (`schema.sql:29`). And
`src/Admin/Audit.php:25-27` wraps the insert in a `try`/`catch` that swallows errors — *"auditing must
never surface an error or abort the action."* If the table is missing you lose a log line, not the
login.

---

## 5. Why this is the right approach here

Honest comparison against our real constraints: MilesWeb shared hosting, no SSH, no guaranteed
outbound e-mail deliverability, one to three staff users, a school budget.

| Option | What it gives | Why not here |
|---|---|---|
| **Identity provider** (Auth0, Okta, Cognito) | Managed everything, 2FA, resets | A monthly bill and a vendor dependency for three users; still needs callbacks and secret storage on shared hosting |
| **OAuth / "Sign in with Google"** | No passwords, Google's own 2FA | Ties admin access to whichever personal Gmail a staff member holds today; a leaver keeps their identity; needs a Cloud project someone owns for the school's lifetime |
| **HTTP Basic auth** (`.htpasswd`) | Trivial to set up | Unbranded browser dialog; no lockout, no audit log, no timeout, no forced change, no logout button |
| **2FA / TOTP** | Genuinely stronger | Needs enrolment UI, recovery codes and a "my phone died" story. Real value — but an *addition*, not a substitute for the fundamentals |
| **What we built** | bcrypt, lockout, forced change, timeouts, audit, zero cost | One honest gap, below |

**The deliberate trade-off: no E-MAIL reset — recovery is proven another way (N1).** A classic
reset flow needs reliable outbound e-mail, and on shared hosting mail from a school domain lands in
spam often enough that the feature would fail exactly when someone is locked out and panicking; a
half-working reset flow is also a *new attack surface* (a reset link is a temporary password sent to
whichever mailbox is weakest). Instead, recovery is proven by **filesystem control**: creating
`config/recovery-token.txt` above the webroot (via the hosting file manager — anyone who can do that
already owns the site) arms a one-time `public_html/admin/recover.php` page that sets a new password,
clears any lockout, audits itself and deletes the token file. While the file is absent the page is a
404 — zero standing attack surface. The login page also now names the lockout ("wait about N
minutes") instead of leaving the owner guessing — SEC-06's revision documents the small, accepted
oracle that trade creates. Dev shortcut: `database/reset-admin-password.php` (CLI, one-time temp
password, forced change). Runbook: `DEPLOY.md` §9; threat notes: `SECURITY.md` SEC-24.

---

## 6. How this scales

Say the school grows to 20-30 admin users, one per department. Build in this order.

**1. Per-user permissions — first.** The `role` column already exists (`schema.sql:11`, default
`'owner'`) and nothing reads it. At 30 users "logged in means everything" is wrong: a sports
coordinator needs no settings page. The work is a permission map (role → allowed entities) checked in
`api/_bootstrap.php` beside the existing `is_admin()`, plus filtering the sidebar list in
`_layout.php:19-42`. No schema change needed.

**2. 2FA / TOTP — second.** TOTP is the six-digit code from an authenticator app. Add `totp_secret`
and `totp_enabled` columns, a QR-code enrolment screen, a second step in `login.php` after
`password_verify()` succeeds, and single-use recovery codes. It comes *after* permissions, because 2FA
on an account that can do everything still means one compromise loses everything.

**3. E-mail reset with signed expiring tokens — third.** Unavoidable at 30 users; phoning a human does
not scale. Do it properly: a `password_resets` table storing a **hash** of the token (never the token
— that table is a database like any other), 30-60 minute expiry, single use, invalidated when the
password changes, rate-limited per account and per IP, and the same "if that account exists we've sent
a mail" message either way. Last, because it needs a real transactional mail service — which means
leaving pure shared hosting, and a budget line.

Queue two smaller items alongside: **IP-based throttling** (`SECURITY.md` SEC-06 mentions an optional
IP counter table) so an attacker cannot spray one guess each across 30 accounts without tripping any
per-account lock; and **an audit-log viewer** in the panel, since a log nobody reads is not a control.

---

## 7. Gotchas and mistakes to avoid

### The `$2y$` shell-mangling trap

A bcrypt hash starts `$2y$10$` and is full of `$`. In `bash`, inside **double quotes**, `$2` and `$10`
are variable expansions — the shell replaces them with nothing before your command runs.

```bash
# ✗ BROKEN — bash eats $2, $10 and more. Silently stores a mangled, unusable hash.
mysql -e "UPDATE admin_users SET password_hash='$2y$10$dnQR...' WHERE id=1"
```

Nothing errors. The row updates. Login then fails forever with the correct password and you lose an
afternoon. **The right method is to never put a hash on a shell command line — hash and update in one
PHP process inside the container:**

```bash
docker compose exec -T web php -r '
  $pdo = new PDO("mysql:host=db;dbname=".getenv("DB_NAME"), getenv("DB_USER"), getenv("DB_PASS"));
  $pdo->prepare("UPDATE admin_users SET password_hash=?, must_change_password=1 WHERE username=?")
      ->execute([password_hash("a-long-temporary-passphrase", PASSWORD_DEFAULT), "admin"]);
  echo "done\n";
'
```

The snippet is in **single quotes**, which bash does not expand at all, and the hash never leaves the
PHP process; `must_change_password = 1` makes the temporary passphrase single-use. **In production
there is no SSH**, so you cannot run that at all: generate the hash on a machine you control
(`php -r 'echo password_hash("…", PASSWORD_DEFAULT);'`), copy the 60-character string, and **paste**
it into mPanel's database tool as a normal `UPDATE` — paste, never echo through a shell — setting
`must_change_password = 1` in the same statement.

### Comparing hashes with `==`

Use `password_verify($typed, $storedHash)`, never `$storedHash == something`. PHP's `==` is a loose
comparison, and historically two hashes both beginning `0e` followed by digits were both read as the
number zero and compared **equal** — the classic "magic hash" bug. It is also not constant-time,
leaking position information via timing. For other secret comparisons (like the CSRF token at
`login.php:14`) use `hash_equals()`.

### Rehashing on algorithm upgrade

`password_needs_rehash($hash, PASSWORD_DEFAULT)` returns `true` when a stored hash used an older
algorithm or lower cost than the current default. The usual pattern is: right after a successful
`password_verify()`, if it needs a rehash, re-hash the plain password you still hold and store it —
users upgrade silently as they log in. **We do not do this.** A grep across the repo (excluding
`vendor/`) finds zero calls to `password_needs_rehash`. Nothing is broken today, because
`PASSWORD_DEFAULT` is bcrypt cost 10 on PHP 8.3 and every hash we hold is current. But the day someone
raises the cost or PHP changes its default, old hashes will silently stay weak. The natural home for
the fix is `login.php` immediately after line 25, inside the success branch — the only moment the
plain password exists in memory.

### Locking out the only admin

There is one account. Five wrong guesses lock it for 15 minutes and, because S3 made the counter
persist, further guesses re-lock it. There is no self-service reset and no second admin to let you back
in. So: never test lockout against the live site, keep the password in a password manager, and as soon
as there is more than one staff member create a second `owner`-role account as a break-glass path.

### Never let a password reach a log or an error

Do not add the password to the audit `detail` field "for debugging" — `login.php:52` deliberately logs
only the username, and `src/Admin/Audit.php:6-7` states the rule. And do not let a password reach an
exception message: an unhandled stack trace from a DB error can print bound parameters. That is one
more reason `SECURITY.md` SEC-16 requires generic errors, with detail going only to a private log.

---

## 8. Try it yourself

Start with `./run.sh`, then open **http://localhost:8090/admin/**.

**Experiment 1 — the forced change.** Log in with `admin` / `admin123`. You land on
`/admin/password.php`, not the dashboard. Now type `/admin/` in the address bar: `_layout.php:13-16`
bounces you straight back. Type a new password slowly and watch the checklist tick and the bars change
colour — that is `password.js` running.

**Experiment 2 — failure messages.** Log out, then try (1) a username that does not exist,
(2) the real username with a wrong password — both say exactly `Invalid username or password.`
Then (3) fail five times and try the **correct** password: since N1 both the 5th failure and the
locked-with-correct-password case say "temporarily locked — wait about N minutes" (the lock is real
*and* announced). Try five failures with a made-up username too — the shadow counter shows the same
lock message. Notice the one-second pause every time.

**Experiment 3 — read the columns (read-only).** Credentials come from `.env.example`
(`DB_USER=stjosephs`, `DB_PASS=stjosephs_pw`, `DB_NAME=stjosephs`); the service name `db` is from
`docker-compose.yml`.

```bash
docker compose exec -T db mysql -ustjosephs -pstjosephs_pw stjosephs \
  -e "SELECT id, username, LEFT(password_hash,7) AS algo, failed_logins, locked_until, must_change_password FROM admin_users;"

docker compose exec -T db mysql -ustjosephs -pstjosephs_pw stjosephs \
  -e "SELECT id, admin_id, action, detail, ip, created_at FROM audit_log ORDER BY id DESC LIMIT 10;"
```

`LEFT(password_hash,7)` shows `$2y$10$` — algorithm and cost — without printing the whole hash to your
terminal or shell history. Run the first query after each failure in experiment 2 and watch
`failed_logins` climb 1, 2, 3, 4, 5 and `locked_until` fill in. Wait 15 minutes, try one more wrong
password, and confirm `failed_logins` continues from 5 rather than restarting — that is the S3 fix,
visible. The second query shows `login.fail` rows with `admin_id` NULL and the attempted username in
`detail`, and a `login.ok` row with a real `admin_id`. Confirm for yourself that **no password appears
anywhere**.

> ⚠️ **Local Docker only.** Never run the lockout experiment against the live site — the school has one
> admin account and no self-service reset, so you would lock the owner out of their own website.
> Likewise, never run a password-reset `UPDATE` against production unless the owner has asked and is
> standing by to set a new one. Read-only `SELECT`s are fine anywhere; writes belong in dev.

---

## 9. Where to read more

- [`../02-security.md`](../02-security.md) — section 4 covers passwords and login attacks inside the
  wider set of attacks we defend against. Read it next.
- [`../03-what-we-built.md`](../03-what-we-built.md) — phases **S2** (kill the default password) and
  **S3** (harden the login session), in the project's own words.
- [`../../../SECURITY.md`](../../../SECURITY.md) — the normative catalog: **SEC-06** (brute force and
  enumeration), **SEC-07** (sessions), **SEC-08** (default credentials / forced change), **SEC-19**
  (audit logging). Its section 4 is the 15-item checklist you must affirm before shipping anything
  that touches this code.
- [php.net — `password_hash()`](https://www.php.net/manual/en/function.password-hash.php) and
  [`password_verify()`](https://www.php.net/manual/en/function.password-verify.php) — short and
  authoritative; read both in full.
- [OWASP Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)
  — the industry reference on algorithm and cost.
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
  — lockout policy, generic error messages, session handling.
