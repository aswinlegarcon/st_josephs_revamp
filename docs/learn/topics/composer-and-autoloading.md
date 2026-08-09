# Composer, Namespaces and Autoloading

> **What you'll learn:** why nobody writes `require` for a class here; what a class is (from zero) and what `static`, `final`, `::` and `private static $cache` mean, using *our* classes as the examples; what a namespace is and why our code writes `\header()` with a leading backslash; how PSR-4 maps `SJ\Media\Html` to `src/Media/Html.php`; every key in `composer.json`; and the single line in `public_html/bootstrap.php` that switches it all on.
>
> **Prerequisites:** basic PHP only — variables, `if`, `foreach`, functions, arrays, and `include`/`require`. You do **not** need to have written a class.
>
> **Where it lives in our code:** `composer.json` (the contract), `public_html/bootstrap.php:21` (the switch), `vendor/composer/*` (the generated loader), `src/**` (the 12 classes), `src/helpers.php` (the global functions).

---

## 1. The one-paragraph version

Normally, every PHP file that uses code from another file must `require` it first, by hand, in the right order. We do not. `composer.json` declares one rule — *"a class whose name starts with `SJ\` lives under `src/`"* — and a generated file, `vendor/autoload.php`, teaches PHP to follow that rule automatically. `public_html/bootstrap.php:21` loads that one file at the top of every request. From then on, the first time any code *mentions* a class like `\SJ\View\Layout`, PHP pauses, finds `src/View/Layout.php`, loads it, and carries on. Classes load **on demand**. Separately, `composer.json` lists `src/helpers.php` under `files`, which loads **always and immediately** — that is why global functions like `e()` and `db()` exist before a single line of page code runs.

## 2. The problem this solves

Here is the old way. Imagine the top of a page:

```php
<?php
require '_libs/config.php';
require '_libs/db.php';       // needs config.php loaded first
require '_libs/registry.php'; // needs sanitize.php
require '_libs/media.php';    // needs db.php AND config.php
// … twenty more lines, in exactly this order, on all 42 pages
```

Four things go wrong, always:

| Problem | What it feels like |
|---|---|
| **Order matters** | Move one line and you get `Fatal error: Class not found`. |
| **You load what you don't need** | A page showing one photo still parses all 20 files. |
| **Adding a file means editing 42 pages** | Miss one, and that page breaks — in production. |
| **Duplicate loads** | Two files both `require` a third → `Cannot redeclare function`. |

This project really had that directory. `docs/learn/07-stage-g.md` records its end: *"`_libs/` (9 files) was ported to proper classes"*. Today `public_html/bootstrap.php:2-4` says what replaced it:

```php
// Site bootstrap — required at the top of every page, admin endpoint and the
// CLI seeder. Replaces _libs/load.php (R2): constants, Composer autoload
// (SJ\ classes + the global helpers in src/helpers.php), lazy session boot,
```

Count the callers yourself — every entry point now requires exactly one file: `grep -rln "require.*bootstrap.php" public_html/ | wc -l` → **59**.

## 3. How it works in general

### 3.1 A class, from zero

A **class** is a named box holding functions (and sometimes data). That is all. Our smallest is `src/Admin/Csrf.php:10-21`:

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

Read it as: *"there is a box called `Csrf`; inside it is a function called `token`."* You call it with `Csrf::token()`. Four keywords:

| Keyword | Meaning | Why it is here |
|---|---|---|
| `class Csrf` | Declares the box. | Groups CSRF code in one place. |
| `final` | Nobody may build a modified version of this. | Security code must not be silently overridden. |
| `public` | Any file may call it. | It is meant to be called. |
| `static` | Belongs to the *class*, not to an object. | One CSRF token per session — no object needed. |

### 3.2 `::` versus `->`

`Csrf::token()` calls a **static** function on the class itself — two colons. `$pdo->prepare(...)` calls a function on an **object you are holding** — an arrow. Nearly all our classes are static, so you will mostly see `::`. The arrow shows up when we hold a real object: `src/Admin/Audit.php:14` writes `\db()->prepare(...)`, because `db()` hands back a PDO *object*.

### 3.3 `private static $cache` — remembering an answer

`src/Core/Config.php:11-19`:

```php
final class Config
{
    private static ?array $cfg = null;

    public static function all(): array
    {
        if (self::$cfg !== null) {
            return self::$cfg;
        }
```

`private` = only code inside `Config` may touch it. `static` = one copy shared for the whole request. `?array` = an array, or `null`. `self::$cfg` = "the `$cfg` belonging to this class". The logic reads: *if I already worked out the answer, hand back the stored copy.* The method's last line, `src/Core/Config.php:37`, stores and returns in one go:

```php
return self::$cfg = $cfg;
```

`src/Core/Db.php:38-43` uses the identical trick for the database connection, so a page opens **one** MySQL connection however many times `db()` is called. That pattern is called a **singleton**.

### 3.4 Autoloading

PHP lets you register a callback that fires whenever a class name is used that PHP has never seen. Composer registers one — `vendor/composer/autoload_real.php:33` calls `$loader->register(true);`. From then on, naming `Layout` triggers a lookup, a `require`, and the call proceeds. If nothing on a page ever names `SJ\Media\Pipeline`, that file is never read from disk.

## 4. How we use it — every place in this codebase

### 4.1 Namespaces: the problem and the syntax

Two different classes could both want the name `Config` — ours, and one from some library. A **namespace** is a prefix keeping them apart. It is the first statement in the file, e.g. `src/Core/Config.php:3`:

```php
namespace SJ\Core;
```

The class's real full name is now `SJ\Core\Config`. `SJ` is our project prefix (St. Joseph's). To reach a class in a *different* namespace you have two options, and our code shows both — plus the case where you need neither:

| Style | Real example | When |
|---|---|---|
| `use` at the top | `src/View/EditAttrs.php:5-6` — `use SJ\Admin\Auth;` then just `Auth::isEdit()` | You call it often. |
| Full name inline | `src/Admin/Auth.php:27` — `$cfg = \SJ\Core\Config::all();` | One call; not worth an import. |
| Nothing at all | `src/Core/Db.php:46` — `Config::all()` | `Db` is **already in** `SJ\Core`; same namespace needs no import. |

You can also rename on import — `src/helpers.php:15` writes `use SJ\Media\Html as MediaHtml;`, because a bare `Html` would be a confusing name in a file full of helpers.

### 4.2 The leading backslash — the rule that looks strange

Inside a namespaced file our code writes `\header(...)`, `\sprintf(...)`, `\dirname(...)`. That backslash means **"start from the global namespace"**. Why bother? Because PHP has two *different* resolution rules:

| What you name | Unqualified, inside `namespace SJ\Core;` | Result |
|---|---|---|
| A **class** | `PDO` | Means `SJ\Core\PDO`. **No fallback.** Fatal error. |
| A **function** | `header()` | Tries `SJ\Core\header()`; if missing, **falls back** to global. Works. |
| A **constant** | `PHP_SAPI` | Same fallback as functions. Works. |

So classes *must* be imported or fully qualified — which is exactly why `src/Core/Db.php:5-7` has to say:

```php
use PDO;
use PDOException;
use PDOStatement;
```

Without those, `new PDO(...)` on line 61 would look for `SJ\Core\PDO` and die. `src/Media/Pipeline.php:5-6` does the same for `RuntimeException` and `Throwable`; `src/Admin/Audit.php:25` instead writes `\Throwable` inline.

Functions are the opposite: the fallback makes the backslash **optional**, and our code is not consistent — compare `src/Admin/Audit.php:14` (`\db()->prepare(`) with `src/Content/Repo.php:27` (`db()->query(`). Both work. We prefer the backslash because it is explicit (a reader knows instantly this is PHP's `header()`, not a local one) and fractionally faster (the fallback costs a failed lookup).

> **Beginner rule of thumb:** in a namespaced file, put `\` on built-in functions, and import classes with `use`.

### 4.3 PSR-4: the naming contract

PSR-4 is a community standard mapping a namespace prefix to a folder. Ours is `composer.json:11-14`:

```json
"autoload": {
    "psr-4": {
        "SJ\\": "src/"
    },
```

(`\\` is just an escaped backslash inside a JSON string.) The rule: **strip the prefix, turn the remaining `\` into `/`, add `.php`.** Checked against the real tree — every one of these files exists:

| Class name | Strip `SJ\` → | File that must exist |
|---|---|---|
| `SJ\Core\Config` | `Core\Config` | `src/Core/Config.php` |
| `SJ\Admin\Auth` | `Admin\Auth` | `src/Admin/Auth.php` |
| `SJ\Media\Html` | `Media\Html` | `src/Media/Html.php` |
| `SJ\View\Layout` | `View\Layout` | `src/View/Layout.php` |

Notice there is **no list of files anywhere**. Add `src/Core/Mailer.php` containing `namespace SJ\Core;` and `class Mailer`, and `SJ\Core\Mailer` is found. That is the whole point.

### 4.4 `composer.json`, key by key

The file is 22 lines. Every key:

| Line(s) | Key | What it does |
|---|---|---|
| 2-5 | `name`, `description`, `type`, `license` | Metadata. `"license": "proprietary"` marks it not open-source. |
| 6-10 | `require` | What the project needs **to run**: `"php": ">=8.1"`, `"ext-pdo": "*"`, `"ext-gd": "*"`. |
| 11-14 | `autoload.psr-4` | The `SJ\` → `src/` map (§4.3). |
| 15-17 | `autoload.files` | `src/helpers.php` — loaded **every request, unconditionally**. |
| 19-21 | `config.optimize-autoloader` | `true` — build the fast classmap (§4.7). |

`ext-pdo` and `ext-gd` are **PHP extensions**, not downloadable packages — PDO for the database, GD for image resizing (`src/Media/Pipeline.php`). And the list is *enforced*: Composer generated `vendor/composer/platform_check.php`, which starts the request with `if (!(PHP_VERSION_ID >= 80100)) {` and throws a `RuntimeException` if the host's PHP is too old. That is a real guard on shared hosting, where you don't control the PHP version.

### 4.5 `psr-4` vs `files` — on demand vs always

| | `autoload.psr-4` | `autoload.files` |
|---|---|---|
| Loads | Classes | Plain `.php` files |
| When | The first time the class is **named** | Immediately, at `require vendor/autoload.php` |
| Cost | Only what the page touches | Always paid |
| Our entry | `SJ\` → `src/` | `src/helpers.php` |

`src/helpers.php` **cannot** be autoloaded on demand, because it declares functions, not classes — and PHP's autoloader only fires for class names. So it must be eager. Its own header, `src/helpers.php:1-5`, says exactly that:

```php
// Global helper functions — the historic names every view, controller and admin
// endpoint calls. Loaded via Composer's "files" autoload (composer.json), so
// they exist before any page code runs. Each delegates to its SJ\ class; the
// behaviour is identical to the pre-R2 _libs implementations.
```

That is why a template can call `e($name)` (`src/helpers.php:40-43`) with no imports and no requires.

### 4.6 The one line that switches it on

`public_html/bootstrap.php:18-21`:

```php
// Composer autoloader: SJ\* classes plus src/helpers.php (the historic global
// function names — e(), db(), repo_*(), img_tag(), ed_*(), …). vendor/ is
// committed, so prod needs no Composer run.
require_once dirname(__DIR__) . '/vendor/autoload.php';
```

`__DIR__` is `.../public_html`, so `dirname(__DIR__)` is the repo root. Now trace one real page — `public_html/index.php:19` calls `\SJ\View\Layout::render('home', [ … ]);`:

1. `public_html/index.php:5` requires `bootstrap.php`; `bootstrap.php:21` requires `vendor/autoload.php`, which requires `vendor/composer/autoload_real.php`.
2. `autoload_real.php:24` runs `platform_check.php` (PHP ≥ 8.1 — pass).
3. `autoload_real.php:33` calls `$loader->register(true)` — PHP now knows who to ask about unknown class names.
4. `autoload_real.php:36-46` loops the `files` list and `require`s `src/helpers.php`. **`e()`, `db()`, `repo_page()` now exist.** A `$GLOBALS['__composer_autoload_files']` guard makes it once-only.
5. The page calls `repo_page('index')` etc. — plain global functions, already loaded.
6. PHP hits `\SJ\View\Layout` — a name it has never seen. It asks the loader, which finds the entry and `require`s `src/View/Layout.php`.
7. `Layout::render()` runs (`src/View/Layout.php:30`), checks the page name against its hardcoded whitelist, and includes the view files.

Step 6 happens once, silently, with no `require` written by us.

### 4.7 What Composer generates, and where it lives

`vendor/` here contains no libraries at all — only Composer's loader. Exactly ten files are tracked (`git ls-files vendor/ | wc -l` → 10):

| Generated file | Contents |
|---|---|
| `vendor/autoload.php` | The public entry point; hands off to `autoload_real.php`. |
| `vendor/composer/autoload_psr4.php` | `'SJ\\' => array($baseDir . '/src')` — our one rule. |
| `vendor/composer/autoload_files.php` | One entry: `$baseDir . '/src/helpers.php'`. |
| `vendor/composer/autoload_classmap.php` | A pre-built name → path table for all 12 `SJ\` classes. |
| `vendor/composer/ClassLoader.php` | The engine that does the lookup. |
| `vendor/composer/platform_check.php` | The PHP ≥ 8.1 guard. |

The classmap is what `-o` (`--optimize`) produces: instead of computing a path and hitting the filesystem, the loader does one array lookup. `vendor/composer/ClassLoader.php:442-450` shows the order — classmap first, then the PSR-4 search. Importantly `classMapAuthoritative` defaults to `false` (`ClassLoader.php:88`) and we never turn it on, so a class missing from the map is still found the slow way.

### 4.8 The class inventory

Every file under `src/`, described from its own header comment:

| File | Class | Namespace | Responsibility | Lines |
|---|---|---|---|---|
| `src/Core/Config.php` | `Config` | `SJ\Core` | Config loader: env vars → `config/config.php` → sample. No credentials in the class. | 44 |
| `src/Core/Db.php` | `Db` (+ `CountingPdo`, `CountingStatement`) | `SJ\Core` | PDO singleton — exceptions on, real prepares, assoc fetches, utf8mb4; dev query counters. | 74 |
| `src/Admin/Auth.php` | `Auth` | `SJ\Admin` | Admin session lifecycle: lazy boot, timeouts, id rotation, teardown, security headers. | 104 |
| `src/Admin/Csrf.php` | `Csrf` | `SJ\Admin` | CSRF token bound to the admin session. | 22 |
| `src/Admin/Audit.php` | `Audit` | `SJ\Admin` | Admin audit trail; best-effort, never records secrets. | 29 |
| `src/Content/Registry.php` | `Registry` | `SJ\Content` | THE entity registry — the only authority for tables, columns and types. | 236 |
| `src/Content/Repo.php` | `Repo` | `SJ\Content` | Flat read-path query helpers; image joins single-query, no N+1. | 345 |
| `src/Content/Sanitizer.php` | `Sanitizer` | `SJ\Content` | Server-side HTML whitelist for `*_html` fields. Frozen list. | 44 |
| `src/Media/Pipeline.php` | `Pipeline` | `SJ\Media` | Image pipeline (GD): auto-crop, downscale, progressive JPEG + WebP. | 290 |
| `src/Media/Html.php` | `Html` | `SJ\Media` | Rendering helpers for image rows — URL, `<img>`/`<picture>`, bg style. | 110 |
| `src/View/Layout.php` | `Layout` | `SJ\View` | Page layout engine; page names only from a hardcoded whitelist. | 51 |
| `src/View/EditAttrs.php` | `EditAttrs` | `SJ\View` | `data-edit-*` attribute emitters; returns `''` for public visitors. | 96 |
| `src/helpers.php` | *(no class)* | global | The historic global function names, each delegating to a class above. | 326 |

### 4.9 Why `vendor/` is committed

Normally `vendor/` is git-ignored and rebuilt on the server by `composer install`. We do the opposite, on purpose. `.gitignore` explains it:

```
# NOTE: vendor/ is intentionally committed for shared-hosting deploy
# (no Composer on MilesWeb). Do NOT ignore it once it exists.
```

Production is MilesWeb shared hosting — file upload only, no SSH, no Composer. Whatever is in git *is* what runs. This is only tolerable because **we have zero third-party packages**: `composer.json:6-10` requires PHP and two built-in extensions and nothing else, so `vendor/` is ten small generated files, not fifty thousand. There is also no `composer.lock` in the repo — with no packages, there is nothing to lock. The `Dockerfile` states the split plainly:

```
# Composer for dev only (regenerate the autoloader with `composer dump-autoload`).
# vendor/ is committed, so production never needs Composer.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
```

### 4.10 `composer dump-autoload -o`

Run `docker compose exec web composer dump-autoload -o` whenever you **add, move, rename or delete a file under `src/`**. You do *not* need it after editing the inside of an existing class. It regenerates the classmap; skip it and your new class still works in dev via the PSR-4 fallback, but the committed `vendor/` no longer matches `src/` — a confusing diff waiting to happen. `./run.sh` already does it on every start (`run.sh:41`):

```bash
docker compose exec -T web composer dump-autoload -o --no-interaction 2>/dev/null || true
```

Never run Composer on your host: `CLAUDE.md` notes there is no PHP on the host, and `docker-compose.yml` mounts `./src`, `./vendor` and `./composer.json` into the `web` container, so the container writes straight back into your working tree.

## 5. Why this is the right approach here

Four options were on the table. The constraints decided it.

| Option | Why not / why yes |
|---|---|
| **Keep manual `require`s** | Needs no tooling. But every new class means editing 59 entry points, and the order-dependency bugs of §2 never go away. |
| **A framework's autoloader** (Laravel, Symfony) | Ruled out at the architecture level — `PHASES.md:9`: *"modern structured PHP 8.3 — Composer PSR-4 (`SJ\` → `src/`) … **No framework.**"* A framework also brings hundreds of megabytes of `vendor/` to upload by hand, plus an upgrade treadmill, for a 42-page brochure site. |
| **Bundle the code as a PHAR archive** | One file to upload — attractive with no SSH. But it must be rebuilt for every one-line change, cannot be edited on the server in an emergency, and some shared hosts disable PHAR. |
| **Composer PSR-4, `vendor/` committed** ✅ | Zero runtime dependencies, ten generated files, no build step to deploy, and a new developer adds a class by *creating a file in the right folder* — no loader list to update, nowhere to forget. |

That last point decides it. With no registration list, there is no way to add a class *wrongly* except by misnaming the file — and PSR-4 makes that mistake obvious immediately.

## 6. How this scales

**10× the classes (12 → 120).** Startup cost does not move. PSR-4 is O(1) per class: one array lookup in the classmap (`vendor/composer/ClassLoader.php:444-446`), or one string-to-path computation on a miss. Nothing scans the `src/` tree at runtime, and only the classes a page actually names are read from disk — so a small page stays a small page.

**When the classmap earns its keep.** With 12 classes, `-o` saves microseconds. At a few hundred classes on a shared host with a cold filesystem cache, avoiding the `file_exists()` probes is measurable. The stricter `classmap-authoritative` mode (never touch the filesystem at all) becomes tempting then — but it turns "forgot to `dump-autoload`" from a non-event into a fatal error, so it should arrive with a scripted deploy, not before.

**If real third-party packages are ever added,** three things change at once. (1) **`composer.lock` becomes mandatory** — it pins exact versions so dev and prod agree; it must be committed, and `composer install`, not `update`, is what you run. (2) **Security updates become your job** — someone else's bug is now your CVE, which means watching advisories and re-uploading `vendor/`, remembering there is no SSH on prod. (3) **A committed `vendor/` becomes a burden** — ten files is nothing, but a real dependency tree is thousands, and every update becomes a giant unreviewable diff and a slow manual upload. At that point the honest move is to build `vendor/` locally and ship it as a deploy artifact, out of git.

## 7. Gotchas and mistakes to avoid

**1. Filename or namespace case mismatch.** `src/core/Config.php` (lowercase `c`) with `namespace SJ\Core;` works fine on macOS or Windows — their filesystems ignore case. The Linux server does not, and you get `Class "SJ\Core\Config" not found` **in production only**. The folder must match the namespace segment character for character: `SJ\Media\Html` → `src/Media/Html.php`, capital M, capital H.

**2. Forgetting `dump-autoload` after adding a class.** In dev the PSR-4 fallback usually saves you, so the mistake hides. What it really breaks is the committed `vendor/composer/autoload_classmap.php`, which now disagrees with `src/`. Run it, and commit the regenerated files alongside your new class.

**3. Missing leading backslash on a global function.** For *functions* the fallback means both forms work (§4.2) — style, not a bug. The version that **is** a real bug is the class case: `new PDO(...)` inside `namespace SJ\Core;` without `use PDO;` looks for `SJ\Core\PDO` and fatals. `src/Core/Db.php:5-7` is the fix, and it is not optional.

**4. Editing anything inside `vendor/` by hand.** Every file there says `@generated by Composer` at the top. The next `dump-autoload` silently overwrites your change, and the bug it introduces reappears with no trace of where it came from. Fix the cause in `composer.json` or `src/` instead.

**5. Committing a `vendor/` built by a different PHP version.** Today this is mild: `platform_check.php` derives its floor from `composer.json`'s `"php": ">=8.1"`, so it reads `PHP_VERSION_ID >= 80100` whoever generates it. It stops being mild the moment real packages exist — Composer then resolves package versions against *the PHP you are running*, so a `vendor/` built on a newer PHP can contain code the shared host cannot run, and `platform_check.php` inherits the higher floor. The site would then 500 on *every* request before a line of our code executes. Always regenerate inside the container (`php:8.3-apache`, per the `Dockerfile`).

**6. Assuming a classmap entry means the file exists.** Ours currently lists `Composer\InstalledVersions` pointing at `vendor/composer/InstalledVersions.php`, which is not present in the tree. It is harmless — nothing in this codebase references that class, so the entry is never used. But if the loader ever reports a missing file, check that the classmap and the tree agree.

## 8. Try it yourself

The goal: prove that a class you never registered anywhere becomes callable.

**Step 1 — start the stack.**

```bash
cd /home/aswin-25449/newDrive/Stjosephs_Website
./run.sh
```

**Step 2 — create `src/Core/Scratch.php`** in your editor (not with `cat >` or any shell redirection — see the file-writing rule in `CLAUDE.md`):

```php
<?php

namespace SJ\Core;

final class Scratch
{
    public static function hello(): string
    {
        return 'autoloaded from ' . \basename(__FILE__);
    }
}
```

**Step 3 — call it *before* regenerating anything.** PSR-4 should already find it:

```bash
docker compose exec -T web php -r 'require "/var/www/vendor/autoload.php"; echo \SJ\Core\Scratch::hello(), PHP_EOL;'
```

Expected: `autoloaded from Scratch.php`. Note what you did **not** write: any `require` for `Scratch.php`.

**Step 4 — regenerate the classmap and confirm the entry appears.**

```bash
docker compose exec -T web composer dump-autoload -o --no-interaction
grep -n "Scratch" vendor/composer/autoload_classmap.php
```

**Step 5 — break it on purpose.** In your editor, change the namespace inside the file to `SJ\Kore`, then re-run Step 3. You should get `Error: Class "SJ\Core\Scratch" not found` — the file still exists, but the contract between its path and its namespace is broken. Change it back.

**Step 6 — clean up.** Delete `src/Core/Scratch.php` in your editor, re-run the `dump-autoload` command from Step 4, then check `git status --short` is clean — nothing from this exercise gets committed.

## 9. Where to read more

| Document | What it adds |
|---|---|
| [`../01-fundamentals.md`](../01-fundamentals.md) | Parts D-F: OOP, namespaces and Composer from zero, in the wider PHP context. |
| [`../03-what-we-built.md`](../03-what-we-built.md) | Phase **P1** (Composer added, `SJ\Core\Config` + `Db` created, old `_libs` files became shims) and **P2\*** (Sanitizer / Registry / Audit ported). |
| [`../07-stage-g.md`](../07-stage-g.md) | Phase **R2**: `_libs/` deleted, the remaining classes ported, `src/helpers.php` created. |
| [`../../../PHASES.md`](../../../PHASES.md) | Line 9 (the no-framework decision) and the P1 / P2\* / R2 rows in the phase table. |
| [`../../../CLAUDE.md`](../../../CLAUDE.md) | The PSR-4 convention, the file-writing rule, and the deploy cautions. |

**The history, and why the shim strategy is worth stealing.** P1 added Composer and moved config + DB into classes but did **not** touch the callers: the old `_libs/config.php` and `_libs/db.php` became two-line **shims** that called the new class. P2\* did the same for Sanitizer, Registry and Audit, and deliberately left the risky files (media pipeline, repo, session code) alone. R2 finally ported those too and deleted `_libs/`, replacing all nine files with `src/helpers.php` — whose global functions have the *same names and signatures* as before. The payoff, from `docs/learn/07-stage-g.md`: *"not one view or admin endpoint changed its calls."*

That is the technique: **keep the old interface alive while you replace what is behind it.** It turns one terrifying 50-file change into a series of small ones, each independently verifiable and revertible on its own. `src/helpers.php` is now a shim layer you can delete function by function, at whatever pace you like — and until you do, nothing is broken.

**External:**

- [getcomposer.org — Basic usage](https://getcomposer.org/doc/01-basic-usage.md)
- [getcomposer.org — Schema: `autoload`, `files`, optimization](https://getcomposer.org/doc/04-schema.md#autoload)
- [PSR-4: Autoloader specification](https://www.php-fig.org/psr/psr-4/)
- [php.net — Namespaces: the function/constant fallback rule](https://www.php.net/manual/en/language.namespaces.fallback.php)
