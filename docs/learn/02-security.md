# 2 — Security: The Attacks & How We Defend

> Goal: understand, in plain language, the ways websites get attacked — and the exact
> defenses we built into this project. Read doc #1 first (especially the 🔒 boxes).
>
> The full, formal catalog is in [`SECURITY.md`](../../SECURITY.md) at the repo root. This
> file is the **friendly, learn-it version**. Every attack here has a real fix in our code.

---

## The one idea behind all security

> **Never trust anything that comes from the user's side.**

The browser, the URL, form fields, cookies, uploaded files, request headers — a normal
visitor sends normal things, but an attacker can send **anything at all**. So every piece
of incoming data is treated as *possibly hostile* until we've checked or cleaned it.

Everything below is just this idea applied to a specific situation.

---

## 1. SQL Injection — tricking the database

### What it is
The database runs SQL commands. If we build a SQL command by gluing user text into it, a
clever user can make their "text" become **commands**.

Imagine a login that builds SQL like this (the **wrong** way):

```php
$sql = "SELECT * FROM admin_users WHERE username = '$username'";
```

A normal user types `admin`, giving `... WHERE username = 'admin'`. Fine.

An attacker types `' OR '1'='1` as the username. Now the SQL becomes:

```sql
SELECT * FROM admin_users WHERE username = '' OR '1'='1'
```

`'1'='1'` is always true, so it returns **every** user — the attacker is logged in as the
first admin. In worse cases they can delete tables or read passwords.

### How we defend
We **never** glue values into SQL. We use **prepared statements with `?` placeholders**
(doc #1, Part H.4). The value is sent to the database *separately*, as pure data that can
never become a command:

```php
// public_html/admin/login.php — the real, safe version
$st = db()->prepare('SELECT * FROM admin_users WHERE username = ?');
$st->execute([$username]);       // even '  OR 1=1  is treated as literal text
```

**And one more layer:** in the admin panel, the *table and column names* can never come
from the user either. They come only from a hardcoded list called the **Registry**
(`src/Content/Registry.php`). So a request can't even name a table it shouldn't touch.

> ✅ **Your rule as a developer:** a value in SQL must **always** be a `?` placeholder.
> A table or column name must **always** come from the Registry or be written literally in
> the code — never from `$_GET`/`$_POST`.

---

## 2. XSS (Cross-Site Scripting) — injecting JavaScript into the page

### What it is
If we take user text and drop it into the HTML page as-is, a user can include a
`<script>` that runs in **other people's browsers**.

Say a visitor's name is shown on a page, and someone sets their name to:

```html
<script>steal(document.cookie)</script>
```

If we print that name raw, every visitor who sees it runs the attacker's script — which
could steal their session cookie and impersonate them.

### How we defend
**Escaping.** Before printing any dynamic value, we convert dangerous characters into
harmless display versions: `<` becomes `&lt;`, `>` becomes `&gt;`, `"` becomes `&quot;`.
The browser then *shows* `<script>` as literal text instead of *running* it.

We have a tiny helper `e()` (in `public_html/_libs/edit.php`) and we wrap **every** printed
value in it:

```php
<?php
function e($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Usage — safe:
echo '<h2>' . e($principal['person_name']) . '</h2>';
```

If the name were `<script>...</script>`, the page shows the text, harmlessly.

### The special case: rich text
Some fields (a principal's message) are *meant* to contain a little formatting — bold,
italics. We can't fully escape those. Instead we **sanitize** them on the way *in*: we
allow only a tiny, frozen set of safe tags (`b, strong, i, em, br, p, span.hl-gold`) and
throw away everything else — all `<script>`, all `onclick=...`, all `style=...`. That's
`src/Content/Sanitizer.php`. So even our "HTML allowed" fields can't carry an attack.

> ✅ **Your rule:** every echoed value goes through `e()`. The *only* exception is a field
> that was cleaned by the Sanitizer on the way in (its column name ends in `_html`).

---

## 3. CSRF (Cross-Site Request Forgery) — making your browser act without you

### What it is
Say you're logged into our admin panel in one tab. In another tab you visit a random evil
website. That site secretly submits a form to **our** admin "delete everything" URL. Your
browser *helpfully attaches your admin cookie* — so the request looks like it came from
you, and it works. You never clicked anything.

### How we defend
A secret **CSRF token**: a big random string that we put into every one of our own forms
and remember in the session. When a form is submitted, we check that the token matches.
The evil site can't know your random token, so its forged request is rejected.

```php
// Every admin form includes the token:
<input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

// The server checks it before doing anything (hash_equals = safe comparison):
if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
    // reject — this wasn't our form
}
```

For the JSON API, the same token is sent in a header (`X-CSRF-Token`) and checked in
`public_html/admin/api/_bootstrap.php`.

**Related rule — no state changes via GET.** Anything that *changes* data (save, delete,
logout) must be a **POST** with a token, never a plain link. A link can be triggered by
just visiting a URL; a token-protected POST cannot be forged. That's why we even changed
**logout** to a POST form.

---

## 4. Passwords & Login attacks

### 4a. Storing passwords — hashing
We **never** store real passwords. If our database leaked, plain passwords would be a
disaster. Instead we store a **hash** — a scrambled, one-way fingerprint. You can check a
password against a hash, but you can't turn a hash back into the password.

```php
// When setting a password:
$hash = password_hash($plainPassword, PASSWORD_DEFAULT);   // store $hash, not the password

// When logging in:
if (password_verify($typedPassword, $hash)) {
    // correct!
}
```

`password_hash` uses a strong, slow algorithm (bcrypt) on purpose — slowness makes mass
guessing impractical.

### 4b. Brute force — guessing passwords repeatedly
An attacker could try thousands of passwords. We **lock the account** after 5 wrong tries
for 15 minutes, and pause 1 second on every failure. We also give the **same** error
message whether the username exists or not — so an attacker can't discover valid usernames.
(See `public_html/admin/login.php`.)

> Fun fact: the original code had a bug where the failure counter reset to zero the moment
> it locked, so the lock never really held. We fixed that in phase **S3**.

### 4c. Default passwords — forcing a change
Software often ships with a default like `admin` / `admin123`. Attackers scan the internet
for exactly these. Leaving it is like leaving a key under the doormat.

Our fix (phase **S2**): the account is created but **flagged** `must_change_password`. On
first login you are **forced** to set a strong (12+ character) password before you can do
anything else. The default is never usable in the real world. See
`public_html/admin/password.php`.

---

## 5. Session security — protecting "logged in" state

Once you're logged in, that session is valuable — stealing it = becoming you. We protect it
several ways (phase **S3**, in `public_html/_libs/edit.php`):

- **HttpOnly cookie** — JavaScript cannot read the session cookie, so even a stray XSS
  can't steal it.
- **SameSite=Lax** — the browser won't send the cookie on sketchy cross-site requests
  (extra CSRF protection).
- **Secure** (in production) — the cookie is only sent over HTTPS, never plain HTTP.
- **Idle timeout (30 min)** — inactive sessions die, so a walked-away-from laptop isn't a
  free pass.
- **Absolute timeout (12 h)** — even active sessions expire daily.
- **Regeneration** — the session ID is rotated after login and periodically, so an old
  stolen ID becomes useless (defeats "session fixation").

---

## 6. File upload attacks — a photo that's really a virus

### What it is
The admin can upload photos. But a file's name lies — `cat.jpg` could actually be PHP code.
If an attacker uploads `shell.jpg` that's really a program, and can then run it, they own
the server.

### How we defend (in `public_html/_libs/media.php`)
1. **Check the real type**, not the name — we sniff the actual file contents (`finfo` +
   `getimagesize`) and both must agree it's a real JPEG/PNG/WebP.
2. **Re-encode every image** — we open the picture and save a fresh copy with PHP's image
   library. Any hidden code inside the original is discarded in the process.
3. **We name the stored file ourselves** using a database id (`/media/42/original.jpg`) —
   the attacker's filename never touches the disk, so they can't do path tricks like
   `../../evil.php`.
4. **The upload folder can't run code** — a `.htaccess` file in `/media` turns off PHP
   execution there, so even if something slipped through, it can't run.
5. **Size and dimension limits** — stops "zip bomb" style huge files that exhaust memory.

---

## 7. Keeping secrets & files out of reach

### 7a. Secrets (phase S1)
The database password is a **secret**. It must **never** be in the code we commit to
GitHub. We keep it in a file **above the website folder** (`config/config.php`) that is
(a) not reachable by the web and (b) never committed to git (`.gitignore` blocks it). The
code that reads it, `src/Core/Config.php`, contains **no** actual passwords — only the
logic to load them.

### 7b. Blocking direct access (phase S4)
Our internal code (`_libs/`, config, the database folder) should never be openable in a
browser. A **`.htaccess`** file (a config file the web server reads) enforces this:

```apache
# public_html/.htaccess (simplified)
Options -Indexes                          # don't list folder contents
RedirectMatch 404 "^/\."                  # hide dotfiles like .git, .env
<FilesMatch "\.(sql|bak|zip|log|md)$">    # block backups, dumps, docs
  Require all denied
</FilesMatch>
```

Try it: open `http://localhost:8090/_libs/config.php` — you get **403 Forbidden**. Good.

### 7c. What we never upload to the real server
`database/`, `.git/`, the `*.md` plan docs, and the real `config/config.php` all stay off
production. If someone found `stjosephs.sql` in the web root, they'd have the whole
database. The `.htaccess` rules above are the safety net.

---

## 8. Security headers — instructions to the browser

Modern browsers can enforce protections **if** the server asks them to, via special
response **headers**. We send these (phase **S4**):

| Header | Plain meaning |
|---|---|
| `X-Content-Type-Options: nosniff` | "Don't guess file types" (stops some tricks). |
| `X-Frame-Options: DENY` (admin) | "Don't allow this admin page inside an `<iframe>`" — stops **clickjacking** (tricking you into clicking hidden buttons). |
| `Content-Security-Policy` (admin) | "Only run scripts from *our own* site" — a strong second wall against XSS. |
| `Referrer-Policy` | "Don't leak our URLs to other sites." |

Check them yourself: `curl -I http://localhost:8090/admin/login.php` and look at the header
lines.

---

## 9. The Registry — one list that enforces safety

The admin API is **generic**: one piece of code can edit slides, ticker items, marks, etc.
That's powerful but risky — what stops a crafted request from editing the `admin_users`
table (to change a password hash)?

The **Registry** (`src/Content/Registry.php`) is a hardcoded list of *exactly* what's
editable and which columns each thing has. The API resolves **everything** through it:

- Ask to edit an unknown thing (`admin_users`) → the Registry returns "unknown" → rejected.
- Send an extra field that isn't in the Registry → ignored/rejected. (This blocks
  **mass assignment**, where an attacker sneaks in an unexpected field like `role=owner`.)

So `admin_users` and `settings` are **deliberately never** in the Registry — they can't be
reached by the generic API at all.

---

## 10. Audit logging — the security camera

If something goes wrong, we need to know *what happened*. Every login (success and
failure), password change, and content change writes a row to the `audit_log` table (phase
**S4**, via `sj_audit()` / `SJ\Admin\Audit`). It records the action, who, and their IP —
but **never** secrets like passwords. It's the CCTV of the app.

---

## The pre-ship checklist (memorize the spirit)

Before finishing **any** change to admin code, database code, or a page that shows database
content, we verify the full checklist in [`SECURITY.md` §4](../../SECURITY.md). In plain
words, the big ones:

1. Every SQL value is a `?` placeholder; table/column names come from the Registry.
2. No user input reaches `include`, a file path, or a redirect.
3. Every printed value goes through `e()` (except sanitized `_html` fields).
4. New editable fields are added to the Registry.
5. New API endpoints go through the shared guard (auth + CSRF); no data changes via GET.
6. Uploads keep their checks (real-type sniff, re-encode, id-based names).
7. `admin_users` / `settings` never exposed; no passwords in any response.
8. No secrets, no `admin123`, no dumps committed or uploaded.

> When we finish a secure change, we write **"SECURITY.md §4 checklist: PASS"** in the
> commit — you'll see that in our git history. It's a habit that keeps the whole team
> honest.

---

## How to practice this

1. Open `public_html/admin/login.php` and find: the prepared statement, the CSRF check,
   `password_verify`, and the lockout logic. You now understand every line.
2. Open `public_html/admin/api/_bootstrap.php` — this is the "security gate" every API
   call passes through. Identify the auth check, the CSRF check, and the headers.
3. Try the attacks (safely, on your local copy): open `/_libs/config.php` (blocked),
   submit the login form 6 times wrong (locked out), look at a page's headers with
   `curl -I`.

Next: **[`03-what-we-built.md`](03-what-we-built.md)** — a guided tour of every change we
made, phase by phase, with the repo's structure.
