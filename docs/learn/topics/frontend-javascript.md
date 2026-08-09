# Front-End JavaScript — No Build Step, No Framework

> **What you'll learn:** what the browser does *after* PHP has finished; what the DOM is; how `querySelector`, event listeners and `classList` work; what `fetch()` and JSON are; every JavaScript file this site ships and which pages load it; how the live-edit overlay turns PHP-emitted `data-edit-*` attributes into a working CMS; and why we deliberately have no React, no npm and no build step.
>
> **Prerequisites:** you can read basic PHP (`echo`, `if`, functions), and you have read [`how-php-serves-a-page.md`](how-php-serves-a-page.md) and [`csrf-protection.md`](csrf-protection.md). **No JavaScript knowledge is assumed** — every term is explained the first time it appears.
>
> **Where it lives in our code:** `public_html/js/` (7 files, 389 lines) and `public_html/admin/assets/` (4 files, 816 lines), plus two vendored libraries: `public_html/assets/vendor/bootstrap-5.3.3/js/bootstrap.bundle.min.js` and `public_html/admin/assets/cropper/cropper.min.js`. The `<script>` tags that load them are in `views/shell.php:85-97` and `public_html/admin/_layout.php:131-133`.

---

## 1. The one-paragraph version

PHP runs on the **server**. It reads the database, builds one long string of HTML, sends it down the wire, and then the PHP process **dies** — it has no memory of the page and no way to touch it again. What arrives in the visitor's browser is dead text. **JavaScript** is the only language the browser itself can run, so anything that has to happen *after* the page has arrived — a fade-in as you scroll, a carousel sliding, a click that saves a heading without reloading — must be JavaScript. Ours is deliberately small and old-fashioned: plain functions in plain `.js` files, loaded with plain `<script src="…">` tags. No compiler, no bundler, no `npm install`, no framework. The proof is that this repository has **no `package.json` and no `node_modules` directory at all** — only `composer.json`, which lists PHP extensions and nothing else.

---

## 2. The problem this solves

A purely server-side site handles every interaction the same way: browser asks for a whole new page, server rebuilds it, browser repaints. That is fine for "go to the About page". It fails at three things this site actually needs.

**1. Effects that depend on scroll position.** The server has no idea where your scrollbar is. Our cards slide up and fade in as they enter the screen (`public_html/css/home.css:382-391`):

```css
.reveal-carousel {
  transition: 1.5s;
  transform: translateY(50px);
  opacity: 0;
}

.reveal-carousel.active {
  opacity: 1;
  transform: translateY(0);
}
```

CSS does the animation, but *somebody* has to add the class `active` at the right moment, and only JavaScript can watch the scroll.

**2. Saving one small thing without a reload.** When an admin clicks a heading, retypes a word and clicks away, we want to send just that field and stay exactly where we are. A normal form submit would reload and lose the scroll position.

**3. Talking to the server in the background.** The contact form sends the enquiry and shows the result without navigating away (`public_html/js/contact.js:47-59`).

Everything else — the text, images, menus and all 42 content pages — is built by PHP and needs no JavaScript.

---

## 3. How it works in general

### The DOM, in plain words

When the browser receives your HTML text, it parses it into a **tree of objects** in memory. A `<div>` containing an `<h3>` becomes an object with a child object. That live tree is the **DOM** — the *Document Object Model*: "document" = the page, "object model" = it is exposed to JavaScript as objects whose properties you can read and change.

This matters: **JavaScript never edits your HTML text.** It edits the tree the browser built from it. Change the tree and the browser repaints instantly. Reload the page and every change is gone, because the tree is rebuilt from the server's HTML again.

### Finding and changing things

You find elements with **CSS selectors** — the same syntax you already use in a stylesheet.

| Call | What it gives you | Used at |
|---|---|---|
| `document.querySelector('.card')` | the **first** match, or `null` | `sj-ui.js:9` |
| `document.querySelectorAll('.card')` | **all** matches, as a list | `site.js:18` |
| `document.getElementById('new')` | the one element with that `id` | `password.js:5` |
| `element.closest('[data-edit-field]')` | walk **upward** to the nearest matching ancestor | `admin.js:113` |

`element.classList` is a small helper for the element's `class` attribute: `.add('active')`, `.remove('active')`, `.contains('x')`, `.toggle('ok', flag)` (that last form is `password.js:24`). Note the division of labour this creates: **JavaScript adds a class, CSS does the animation.** Keeping styling out of JavaScript is a large part of why the visual-freeze rule survives.

### Event listeners

An **event** is something that happens: a click, a key press, a scroll. You register a function to run when it does:

```js
window.addEventListener('scroll', tick);
```

That is `public_html/js/site.js:28`. Read it as: "browser, whenever the page scrolls, call my function `tick`." The function is a **handler** (or **callback**) — you hand it over and the browser calls it later, maybe hundreds of times, maybe never.

Two events appear constantly. **`DOMContentLoaded`** fires once, when the HTML has been fully parsed into the DOM; before it, elements further down the page do not exist yet. **`load`** fires later, once images and stylesheets have downloaded too (`views/partials/preloader.php:7` uses it).

### Why our `<script>` tags sit at the end of `<body>`

`views/shell.php:85-97`:

```php
<!-- ONE self-hosted Bootstrap 5.3.3 bundle (includes Popper) -->
<script src="/assets/vendor/bootstrap-5.3.3/js/bootstrap.bundle.min.js?v=<?php echo SJ_ASSET_VER; ?>"></script>
<!-- shared reveal/blur helpers (R2) — loaded before the per-page scripts -->
<script src="/js/site.js?v=<?php echo SJ_ASSET_VER; ?>"></script>
<?php foreach ($scripts as $js): ?>
<script src="/js/<?= e($js) ?>.js"></script>
<?php endforeach; ?>
<?php if (is_admin()): // O1/O2 live-edit overlay ?>
<?php include $__p . '/admin-bar.php'; ?>
<script src="/admin/assets/cropper/cropper.min.js?v=<?php echo SJ_ASSET_VER; ?>" defer></script>
<script src="/admin/assets/sj-ui.js?v=<?php echo SJ_ASSET_VER; ?>" defer></script>
<script src="/js/admin.js?v=<?php echo SJ_ASSET_VER; ?>" defer></script>
<?php endif; ?>
```

**Position.** A plain `<script src="…">` **blocks** the parser: the browser stops building the DOM, downloads the file, runs it, then continues. In `<head>` that means the visitor stares at a blank screen. At the end of `<body>` the page is already parsed and painted first.

**`defer`.** The `defer` attribute (lines 94–96) says: *download in parallel with parsing, but do not run until the DOM is complete — and run deferred files in source order.* We use it on the admin files for two reasons. `admin.js` reads the finished DOM the moment it runs — `document.querySelectorAll('[data-edit-item]')` at `admin.js:148` is at top level with no `DOMContentLoaded` wrapper, and `defer` is what makes that safe. And order matters: `sj-ui.js` must define `window.SJUI` before `admin.js` uses it.

### `fetch()`, JSON and AJAX

**AJAX** is an old acronym for a simple idea: *JavaScript asks the server for something in the background and updates part of the page — no reload.* The modern browser function for it is **`fetch()`**. **JSON** (JavaScript Object Notation) is the text format both sides use for structured data: `{"ok":true,"value":"Principal"}`. PHP writes it with `json_encode()` (`admin/api/_bootstrap.php:13`); JavaScript reads it with `response.json()`.

Here is our entire API client — `public_html/admin/assets/sj-ui.js:28-39`, twelve lines that every admin write goes through:

```js
  function api(path, payload) {
    // Route through the front controller: 'item.php' -> index.php?r=item
    var route = path.replace(/\.php$/, '');
    return fetch('/admin/api/index.php?r=' + route, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
      body: JSON.stringify(payload || {})
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) throw new Error(j.error || 'Request failed');
      return j;
    });
  }
```

- `fetch(url, options)` sends an HTTP request and returns a **Promise** — an object meaning "the answer is not here yet". You attach `.then(fn)` for the answer and `.catch(fn)` for failure.
- `method: 'POST'` — a write, never a GET (`SECURITY.md` forbids mutating GETs).
- `headers` are extra lines on the request. `Content-Type` tells PHP the body is JSON; **`X-CSRF-Token` is our anti-forgery proof**.
- `JSON.stringify` turns a JavaScript object into JSON text; `r.json()` parses the reply back.
- `if (!j.ok) throw …` is our own convention: every endpoint answers `{"ok":true,…}` or `{"ok":false,"error":"…"}`.

Where does `CSRF` come from? The first line of the file, `sj-ui.js:9`:

```js
  var CSRF = (document.querySelector('meta[name="sj-csrf"]') || {}).content || '';
```

PHP printed that `<meta>` tag for logged-in admins only (`views/shell.php:67`, `admin/_layout.php:88`). JavaScript reads it out of the DOM and echoes it back on every write; the server compares the two (`admin/api/_bootstrap.php:32-37`):

```php
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        api_fail('Invalid CSRF token', 403);
    }
}
```

Why that stops an attack: [`csrf-protection.md`](csrf-protection.md).

You will meet a newer spelling of the same idea elsewhere — `const r = await fetch(url); const j = await r.json();`. `await` is just nicer syntax for `.then()`. **We do not use it**: our files stay in the older style so they run everywhere with no transpiling step.

---

## 4. How we use it — every place in this codebase

| File | Lines | What it does | Who loads it |
|---|---|---|---|
| `public_html/js/site.js` | 54 | The **only** scroll-reveal + hover-blur implementation: `sjReveal()`, `sjBlurCards()` | `views/shell.php:88` — **every public page** |
| `public_html/js/academy.js` | 3 | One call: `sjReveal('.back-bar-reveal,.infra-new-reveal', 150, true)` | the 18 academy pages, via `'scripts' => ['academy']` (e.g. `public_html/tamilacademy.php:15`) |
| `public_html/js/sports.js` | 7 | Card blur + 4 numbered reveals, threshold 100 | `views/pages/sports.php:70` |
| `public_html/js/co-curriculum.js` | 7 | Same pattern, co-curriculum selectors | `views/pages/co-curriculum.php:65` |
| `public_html/js/achievements.js` | 8 | Same pattern, threshold 150 | `views/pages/achievements.php:67` |
| `public_html/js/contact.js` | 79 | Contact-form validation + `fetch('/api/contact.php')` | `views/partials/contact.php:16` |
| `public_html/js/admin.js` | 231 | **The live-edit overlay**: inline editing, item chips, add buttons, photo swap | `views/shell.php:96` — **admins only** |
| `public_html/admin/assets/sj-ui.js` | 384 | Shared admin core: `api()`, `toast()`, modals, image picker, rich-text toolbar, registry-driven forms. Exposes `window.SJUI` | `views/shell.php:95` and `admin/_layout.php:132` |
| `public_html/admin/assets/panel.js` | 360 | Standalone panel engine: row actions, drag-reorder, "Manage photos", media library, re-crop | `admin/_layout.php:133` — every panel page |
| `public_html/admin/assets/settings.js` | 38 | Site Settings screen: collect inputs → `POST ?r=settings` | `public_html/admin/section.php:723` |
| `public_html/admin/assets/password.js` | 34 | Password strength meter + live rule checklist | `public_html/admin/password.php:123` |
| `assets/vendor/bootstrap-5.3.3/js/bootstrap.bundle.min.js` | vendored | Navbar collapse, carousels, modals (includes Popper) | `views/shell.php:86` — every public page |
| `admin/assets/cropper/cropper.min.js` | vendored, 37 KB | Cropper.js — the drag-to-crop box on upload | `views/shell.php:94`, `admin/_layout.php:131` — admins only |

That is the **entire** client side: 1,205 lines of our own JavaScript for a 42-page site with a full CMS.

### The one shared helper (R2)

Before Stage G the scroll-reveal effect existed as **23 copies** — 16 inline `<script>` blocks and 7 per-page files, differing only in selector list and threshold ([`../07-stage-g.md`](../07-stage-g.md)). It is now one function, `public_html/js/site.js:16-36`:

```js
function sjReveal(selector, revealPoint, initNow) {
  function tick() {
    var els = document.querySelectorAll(selector);
    var windowHeight = window.innerHeight;
    els.forEach(function (el) {
      if (el.getBoundingClientRect().top < windowHeight - revealPoint) {
        el.classList.add('active');
      } else {
        el.classList.remove('active');
      }
    });
  }
  window.addEventListener('scroll', tick);
  if (initNow) { … }
}
```

`getBoundingClientRect().top` is the element's distance from the top of the *visible window*. If that is less than "window height minus the threshold", the element has scrolled far enough in — add `active`, and CSS fades it up. Each page passes **its own original numbers**, so behaviour is unchanged per page: the visual freeze enforced at the parameter level. `sjBlurCards()` (`site.js:40-54`) is the second pattern — hover one card and its siblings get class `blur`, which is `filter: blur(5px)` (`public_html/css/sports.css:324-325`).

Some pages still call these from a small inline block instead of a file, e.g. `views/partials/card.php:52-58`:

```html
<script>
window.addEventListener('DOMContentLoaded', function () {
    sjReveal('.reveal-motto,.reveal-card,.reveal-text', 150);
});
</script>
```

The `DOMContentLoaded` wrapper is essential: this markup sits mid-body, but `site.js` is not loaded until the bottom, so `sjReveal` does not exist yet when the block runs. Deferring the call until the DOM is complete guarantees the function is defined.

### The live-edit overlay: PHP writes the instructions, JS reads them

PHP cannot hand a JavaScript variable to the browser safely. What it *can* do is add attributes to the HTML it prints. `src/View/EditAttrs.php` does exactly that — and returns an **empty string** for anyone not in edit mode (`EditAttrs.php:19-21`, repeated in every method):

| PHP helper | Emits | Meaning |
|---|---|---|
| `ed_field($entity,$id,$field)` | `data-edit-field="profile:1:heading" data-edit-type="text"` | this text is one editable DB column |
| `ed_item($entity,$id,$label)` | `data-edit-item="hero_slide:4" data-edit-flags="od"` | a repeating row; `o`=orderable, `d`=deletable |
| `ed_add($entity,$preset,$label)` | `data-edit-add='{"entity":…,"fields":[…]}'` | this container can gain new rows |
| `ed_img($entity,$id,$field)` | `data-edit-img="hero_slide:4:image_id:hero_16x7"` | this image slot can be swapped |

Read the value like an address: **entity : row id : column**. It is built at `EditAttrs.php:26`:

```php
$attrs = \sprintf(' data-edit-field="%s:%d:%s" data-edit-type="%s"', e($entity), (int)$id, e($field), e($def['type']));
```

Note `(int)$id` and `e()` on every part — an attribute is still untrusted output ([`output-escaping-and-xss.md`](output-escaping-and-xss.md)).

### One full round trip: click → edit → fetch → API → database → page

1. **PHP renders.** The admin is in edit mode, so `EditAttrs::field()` prints `data-edit-field="profile:1:heading"` on the `<h3>`. The shell also prints `<meta name="sj-csrf" …>` (`shell.php:67`) and puts `sj-edit-mode` on the `<body>` (`shell.php:72`).
2. **`admin.js` wakes up.** Its first act is a guard (`admin.js:16-18`):

   ```js
   if (!document.body.classList.contains('sj-edit-mode') || !window.SJUI) {
     return; // viewing as a visitor — no editing affordances
   }
   ```

3. **A click happens anywhere.** Rather than a handler on every editable element, one handler on `document` catches all clicks and asks "did this come from inside an editable?" — a technique called **event delegation** (`admin.js:112-118`):

   ```js
   document.addEventListener('click', function (e) {
     var field = e.target.closest ? e.target.closest('[data-edit-field]') : null;
     if (field && field.getAttribute('data-edit-type') !== 'image') {
       …
       startEditing(field);
   ```

4. **The element becomes typeable.** `startEditing()` sets `contenteditable` (`admin.js:93`) — a browser attribute that makes any element editable in place. Plain fields get `plaintext-only`, so a paste cannot smuggle HTML in; rich fields also get a floating **B** / *I* / Gold toolbar.
5. **You click away or press Enter** (`admin.js:127-131`). `finishEditing(true)` reads the new text and, only if it changed, calls `admin.js:46`:

   ```js
   api('field.php', { entity: a.entity, id: a.id, field: a.field, value: value })
   ```

6. **The request leaves**: `POST /admin/api/index.php?r=field`, header `X-CSRF-Token: …`, JSON body.
7. **The front controller routes it.** `admin/api/index.php:7-25` maps `r=field` to `field.php` from a **hardcoded** table — the request can never name a file. Unknown route → 404.
8. **The bootstrap guards it** (`admin/api/_bootstrap.php:22-37`): logged in? forced password change done? CSRF token matching? Any "no" → JSON error, stop.
9. **The endpoint validates and writes** (`admin/api/field.php:15-18`):

   ```php
   $value = api_validate_field($entity, $field, $def, $in['value'] ?? null);

   $st = db()->prepare("UPDATE {$reg['table']} SET `$field` = ? WHERE id = ?");
   $st->execute([$value, $id]);
   ```

   Table and column come from the **registry**, never from the request; the value goes through a PDO placeholder. Rich text is re-sanitised server-side (`_bootstrap.php:79-84`). Then `sj_audit('field.save', …)` records who did it and `api_out(['value' => $value])` replies with the *cleaned* value.
10. **The page updates itself** (`admin.js:47-53`): a "Saved ✔" toast appears, and for rich fields the server's sanitised HTML is written back into the element — so what you see is what got stored. No reload. On failure (`admin.js:54-57`) the original text is restored and the error shown.

Deletion, reordering and photo swaps take the same path through `item.php`, `order.php` and `field.php`, then call `location.reload()` because the whole list changed.

### The admin-only rule

`views/shell.php:66-70` and `92-97` are both wrapped in `if (is_admin())`. The consequence is absolute: a logged-out visitor's HTML contains **no** CSRF meta tag, **no** `admin.css`, **no** `cropper.min.css`, **no** `data-sj-presets`, **no** `sj-ui.js`, **no** `admin.js`, **no** Cropper — and, because `EditAttrs` returns `''`, **no** `data-edit-*` attributes either.

That is ~64 KB of JavaScript (`admin.js` 10.4 KB + `sj-ui.js` 16.9 KB + `cropper.min.js` 37.4 KB) plus two stylesheets a visitor never downloads. **Performance:** the public page stays tiny. **Security:** there is no admin code path in a visitor's browser to probe and no token to steal — [`../06-stage-f.md`](../06-stage-f.md) records the grep proof of zero overlay bytes for logged-out requests.

Edit mode is a second gate. `Auth::isEdit()` (`src/Admin/Auth.php:81-84`) requires `$_SESSION['edit_mode']`, which is only ever set by a **POST with a CSRF token** (`public_html/admin/editmode.php:12-18`) submitted from the admin-bar form (`views/partials/admin-bar.php:11-18`). A link could never turn it on.

### CSP and inline scripts

Admin pages and every API response send this header (`src/Admin/Auth.php:98-102`):

```php
\header(
    "Content-Security-Policy: default-src 'self'; " .
    "img-src 'self' data:; style-src 'self' 'unsafe-inline'; " .
    "script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
);
```

A **Content-Security-Policy (CSP)** is a browser-enforced allow-list. `script-src 'self'` means *run JavaScript only from files served by this same origin*, which makes two things impossible on admin pages: an inline `<script> … </script>` block, and an inline handler attribute like `onclick="doThing()"`. Both are refused with a console error. That is the point — the commonest way an XSS hole becomes a breach is an injected inline script, and under this policy the browser will not run one even if the attacker gets it into the HTML. It is also why `settings.js:1-3` and `password.js:1-2` state in their own comments that they exist as external files precisely so the CSP can stay strict.

So how does PHP pass *data* to JavaScript with no inline script? **Through DOM attributes**, which are data, not code:

```php
<meta name="sj-csrf" content="<?= e(csrf_token()) ?>">      <!-- shell.php:67 -->
```

```php
data-sj-presets='<?= str_replace("'", '&#39;', json_encode($__presets, JSON_UNESCAPED_SLASHES)) ?>'
```

(`views/shell.php:73-74`, and the same on the panel's `<body>` at `admin/_layout.php:92-94`.) JavaScript reads them back at `sj-ui.js:9` and `sj-ui.js:43`:

```js
  try { SJ_PRESETS = JSON.parse(document.body.getAttribute('data-sj-presets') || '[]'); } catch (e) {}
```

**Honest caveat:** the CSP comes from `sj_admin_headers()`, called by the admin layout, login, password, health and API bootstrap — **public pages send no CSP today.** That is why a few public views still carry inline blocks (`views/partials/card.php:52`, `views/pages/home.php:105`, `views/partials/preloader.php:5`). Follow the external-file discipline anyway: the day a public CSP is added, those blocks are what will break.

### Progressive enhancement — what actually needs JS

**Progressive enhancement** means the page works without JavaScript and JavaScript makes it nicer. Measured honestly against our code:

| Feature | Needs JS? |
|---|---|
| All page text, headings, tables, links, images | **No** — PHP prints them |
| Navigation between pages, footer, contact details, SEO meta | **No** — ordinary `<a href>` and server-rendered markup |
| Navbar mobile collapse, carousels, modals | **Yes** — Bootstrap bundle |
| Scroll-reveal fade-ins | **Yes** — and the CSS starts at `opacity: 0`, so elements would stay invisible |
| Contact form submit | **Yes** — `sendMail()` calls `preventDefault()` then `fetch` |
| Live-edit overlay and admin panel | **Yes**, entirely |
| The full-screen preloader | **Yes — and this is the sharp edge** |

That last row is the warning. `views/partials/preloader.php` renders a fixed full-screen white overlay (`#preloader { … height: 100vh; width: 100%; position: fixed; z-index: 100; }`) hidden **only** from JavaScript:

```js
  var sjLoader = document.getElementById("preloader");
  window.addEventListener("load", function () { if (sjLoader) sjLoader.style.display = "none"; });
```

If JavaScript never runs, that overlay never lifts. The truthful statement is: the markup and content are fully server-rendered and would be perfectly readable, but the shipped page is not currently usable with JavaScript disabled. That is inherited behaviour from the original site, frozen by the visual-freeze rule — not a decision made during the revamp.

---

## 5. Why this is the right approach here

| Option | Why not here |
|---|---|
| **React / Vue** | Both need a build step (JSX/SFC → plain JS) and therefore Node.js. Production is MilesWeb shared hosting with mPanel, **no SSH**; deployment is a file upload. There is nowhere to run a build. They also move rendering into the browser — the one thing the visual freeze forbids. |
| **A bundler (Vite / webpack)** | Same Node problem, plus it hides which file the browser actually runs. Our whole client side is 1,205 lines; the tooling would outweigh the code. |
| **jQuery** | It exists to paper over 2010-era browser differences. `querySelectorAll`, `classList`, `closest` and `fetch` are all native now. Adding ~30 KB to every page for syntax sugar fails the performance budget. |
| **Plain browser JS (what we do)** | Edit a `.js` file, upload it, done. Any PHP developer can read it. Nothing to install, nothing to keep updated, nothing that breaks when a toolchain version drifts. |

The deciding constraints, stated plainly: **no Node toolchain in production**, **deploy is a file upload**, **a frozen visual design**, **a strict CSP on the admin**, **one maintainer**, and **a site that is 95% content pages**. Under those six, plain JavaScript is not a compromise — it is the fit.

---

## 6. How this scales

The public site does not feel this at all: add ten more content pages and you add zero JavaScript. The pressure point is the **admin panel**. `panel.js` is 360 lines and `sj-ui.js` 384, each a single IIFE (an **immediately invoked function expression** — the `(function () { … })();` wrapper that keeps variables out of the global namespace), talking through one shared global, `window.SJUI`.

At 10× the admin features, three things bite:

1. **Discovery.** With 3,000 lines you cannot remember what `SJUI` exposes. Today the answer is one readable object literal at `sj-ui.js:368-383`; at 10× it would not be.
2. **Accidental coupling.** Every function on `SJUI` is reachable from every file. Nothing enforces boundaries.
3. **Re-rendering by hand.** Notice how many overlay actions end in `location.reload()` (`admin.js:79`, `:167`, `:180`, `:204`, `:223`). That is the cheap, correct answer for a handful of operations; on a busy editing screen it would feel slow, and hand-writing DOM updates instead is exactly the work a framework does for you.

The staged answer, in order:

- **First, native ES modules — still no build step.** Change the tags to `<script type="module" src="…">` and use `import`/`export` instead of a global. Every browser we support does this natively; nothing is compiled. Real boundaries, for free.
- **Then, if one screen becomes genuinely app-like** (a drag-and-drop page builder, a live dashboard): introduce a small framework **on the admin panel only**, self-hosted, behind the existing `is_admin()` gate. Because the panel already has its own layout, CSP and assets, that stays a contained decision.
- **The public site stays dependency-free, permanently.** It is content. Its own budget is `site.js` (54 lines), a few per-page one-liners and the Bootstrap bundle. A framework there would trade visitor performance and the pixel freeze for a convenience only admins would notice.

**The exact next step, when the panel grows:** convert `sj-ui.js` and `panel.js` to ES modules — `export` the `SJUI` members individually, `import` them in `panel.js` and `admin.js`, and keep the `window.SJUI` assignment for one release so nothing breaks mid-migration. No new tooling, no new dependency, fully reversible.

---

## 7. Gotchas and mistakes to avoid

**1. Inline handlers break under the CSP.** `<button onclick="save()">` on an admin page gives you a button that does nothing — the browser refuses to run the attribute because of `script-src 'self'` (`src/Admin/Auth.php:101`). The fix always has the shape of `settings.js:14`: give the element an `id` or a `data-` attribute and attach the handler from an external file with `addEventListener`.

**2. Forgetting the CSRF header.** A `fetch()` without `X-CSRF-Token` gets `403 {"ok":false,"error":"Invalid CSRF token"}` from `_bootstrap.php:34-36`. Call `SJUI.api()` rather than writing a raw `fetch` — it adds the header for you. The two places that legitimately call `fetch` directly show the rule: the multipart upload still sends the header manually (`sj-ui.js:251`), and only the read-only GET image list is exempt (`sj-ui.js:167`).

**3. Running a script before the DOM exists.** `document.querySelector('.card')` returns `null` if the script runs before `.card` is parsed, and the next line throws `Cannot read properties of null`. Three correct fixes, all in our code: put the tag at the end of `<body>` (`shell.php:86-90`), add `defer` (`shell.php:94-96`), or wrap the work in `DOMContentLoaded` (`public_html/js/sports.js:4`).

**4. Duplicate `id`s break `querySelector`.** `getElementById` and `querySelector('#x')` return only the **first** match, so a second element with the same `id` is silently unreachable — and the bug looks like "my handler works on one card but not the other". Stage G's W3C run enforces unique `id`s across all 42 documents ([`../07-stage-g.md`](../07-stage-g.md)). Keep it that way.

**5. Editing a JS file without bumping `SJ_ASSET_VER`.** `public_html/bootstrap.php:14-15` defines the version string appended as `?v=…` to asset URLs, and `.htaccess:47-51` tells browsers to cache anything matching `.js`/`.css` for **a year**:

```apache
  <FilesMatch "\.(css|js|jpg|jpeg|png|webp|gif|ico|woff2|svg)$">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </FilesMatch>
```

Change a file, forget the bump, and returning visitors keep the old copy — for up to a year, with no way to ask them to refresh. **One real trap: the per-page scripts are the exception.** `views/shell.php:90` emits `<script src="/js/<?= e($js) ?>.js"></script>` with **no `?v=`**, so `academy.js`, `sports.js`, `co-curriculum.js` and `achievements.js` live at URLs that never change. Editing one of those needs its own cache-busting plan.

**6. Assuming a failed `fetch()` throws.** It does not. `fetch` rejects only when the request could not be made at all (network down, DNS failure). **A 403, 404 or 500 is a perfectly successful `fetch`** with `response.ok === false`. That is exactly why our client checks the *body*, not the transport (`sj-ui.js:36`):

```js
      if (!j.ok) throw new Error(j.error || 'Request failed');
```

Write a naive `fetch(...).then(r => r.json()).then(showSuccess)` and a 403 gets reported to the user as a success.

**7. Trusting rich text because JavaScript produced it.** The overlay's toolbar builds `<b>`/`<i>`/`span.hl-gold` in the browser, but the server sanitises the HTML again on save (`_bootstrap.php:79-84`) and the overlay swaps the cleaned value back in (`admin.js:50`). Anything a browser sends is attacker-controllable; a client-side toolbar is a convenience, never a control.

---

## 8. Try it yourself

Run `./run.sh`, open `http://localhost:8090/`, and press **F12** for DevTools.

1. **Watch the reveal run.** In the **Elements** tab find an element with class `reveal-carousel` or `reveal-motto` and scroll slowly. Watch `class="reveal-carousel active"` appear and disappear — that is `site.js:22` and `site.js:24` toggling one class while CSS does the fade.
2. **Prove the admin-only rule.** Logged out, press **Ctrl+U** (view source) and search for `sj-csrf`, `admin.js` and `data-edit`. Zero hits. Now log in at `http://localhost:8090/admin/`, return to the home page and search again — the meta tag and three admin scripts are there.
3. **Turn on edit mode.** Press **✏️ Edit this page** in the navy bar at the bottom. In DevTools confirm `<body class="… sj-edit-mode" data-sj-presets='[…]'>`.
4. **Watch a save on the wire.** Open the **Network** tab, click the Principal's heading, change a word, click away. A request to `index.php?r=field` appears. Click it:
   - **Headers** → Request Headers → `X-CSRF-Token: …` (it matches the meta tag).
   - **Payload** → `{"entity":"profile","id":1,"field":"heading","value":"…"}`.
   - **Response** → `{"ok":true,"value":"…"}` — the server's cleaned value.
5. **Break it on purpose.** In the **Console** tab paste this, noting the missing header:

   ```js
   fetch('/admin/api/index.php?r=field', {
     method: 'POST',
     headers: { 'Content-Type': 'application/json' },
     body: JSON.stringify({ entity: 'profile', id: 1, field: 'heading', value: 'HACKED' })
   }).then(r => { console.log('HTTP status:', r.status); return r.json(); })
     .then(j => console.log('body:', j));
   ```

   Expected: `HTTP status: 403` and `body: {ok: false, error: "Invalid CSRF token"}`. Two lessons in one paste — the guard works, **and** the `fetch` did not throw despite the 403 (gotcha 6).
6. **Compare with the correct call.** Still in the Console, run `SJUI.api('field.php', { entity:'profile', id:1, field:'heading', value:'Principal' })` and watch it succeed, because `SJUI.api` supplies the header.
7. **Count the bytes.** In the Network tab filter by **JS** and reload the home page once logged out, once logged in. That difference is what visitors never pay for.

---

## 9. Where to read more

**In this repo**

- [`../06-stage-f.md`](../06-stage-f.md) — the live-edit overlay story: attribute grammar, O1 inline editing, O2 items and photos. Primary source for §4.
- [`../07-stage-g.md`](../07-stage-g.md) — the R2 consolidation (23 reveal copies → one `site.js`) and the W3C sweep that enforced unique `id`s.
- [`csrf-protection.md`](csrf-protection.md) — why `X-CSRF-Token` exists and what it stops.
- [`security-headers-and-htaccess.md`](security-headers-and-htaccess.md) — the CSP and the caching rules quoted above.
- [`output-escaping-and-xss.md`](output-escaping-and-xss.md) — why `e()` wraps even a `data-` attribute value.
- [`the-registry-pattern.md`](the-registry-pattern.md) — where `data-edit-add`'s field list comes from.
- [`../../../SECURITY.md`](../../../SECURITY.md) — the normative catalog; §4 is the mandatory pre-ship checklist.

**Outside**

- MDN, *Introduction to the DOM* — <https://developer.mozilla.org/en-US/docs/Web/API/Document_Object_Model/Introduction>
- MDN, *Using the Fetch API* — <https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API/Using_Fetch>
- MDN, `Element.classList` — <https://developer.mozilla.org/en-US/docs/Web/API/Element/classList>
- MDN, *Content Security Policy* — <https://developer.mozilla.org/en-US/docs/Web/HTTP/Guides/CSP>
