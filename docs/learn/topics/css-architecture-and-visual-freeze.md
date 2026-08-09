# CSS Architecture and the Visual Freeze

> **What you'll learn:** how CSS decides which rule wins (cascade, specificity, inheritance, box
> model), how *this* project organises its stylesheets — one tokens file, one sheet per page family,
> ten partial sheets, plus CSS generated from the database — and why the hardest rule in the repo is
> that **nothing may change how a page looks** until the migration is finished.
>
> **Prerequisites:** you can read an HTML tag and a rule like `.thing { color: red; }`. Every other
> term is explained on first use. [`../01-fundamentals.md`](../01-fundamentals.md) helps but is not
> required.
>
> **Where it lives in our code:** `public_html/css/tokens.css`, `public_html/css/*.css` (16 files),
> `public_html/css/partials/*.css` (10 files), `views/shell.php` (the `<head>`),
> `views/pages/*.php`, `public_html/bootstrap.php` (cache-busting), and the rule text in `CLAUDE.md`.

---

## 1. The one-paragraph version

Every public page is rendered by one file, `views/shell.php`. It writes the `<head>` and loads
stylesheets **in a fixed order**: one self-hosted Bootstrap, one icon font, the web fonts, then
`tokens.css` (brand colours and fonts as named variables), then `footer.css`, then the sheet that
page asked for, then — only if the page built one — a block of CSS generated from the database, and
last, only for a logged-in admin, the admin overlay sheet. Order matters because CSS breaks ties
with "whoever comes last wins". On top sits the strictest rule here: during the migration the site
must render **pixel-identical** to the old one — so when a framework upgrade would move something we
add *compensating CSS* to move it back, and when the original shipped a broken rule the browser
ignored, we keep the broken rule.

---

## 2. The problem this solves

The original site was 42 hand-written PHP files, each carrying its own `<style>` blocks and copied
stylesheets. Three things went wrong, all still visible in git.

**Copy-paste drift.** Four school-section pages had four near-identical sheets.
`public_html/css/sections.css:2-3` records what the merge found: *"The shipped
highschl/highsec/primary/kg.css were the same file cloned with a find-and-replace; this is their
union, verified line-by-line."* Someone had run that replace over a clone and hit a word *inside*
another word: those sheets contained `position: kgsolute` and `highschlsolute` instead of `absolute`
([`../07-stage-g.md`](../07-stage-g.md), "R2"). The browser ignored the lines for years.

**Nobody knew which rule was winning.** The home page loaded Bootstrap **three times**, and the last
copy silently won every tie ([`../04-stage-c-and-d.md`](../04-stage-c-and-d.md), "The detective
story") — which is why testimonial cards had 4 px corners while the page's own CSS asked for 10 px.
Nobody chose that; it was an accident of load order.

**Colours typed by hand.** The same navy `#2b4b8a` appears in dozens of files. Change the brand and
you are grepping a hex string.

The architecture below fixes ownership (each sheet has one owner), order (one file decides it) and
duplication (one token file) — while the freeze guarantees none of it moved a pixel.

---

## 3. How it works in general

### 3.1 The cascade

**Cascade** = how the browser picks one value when several rules set the same property on the same
element. Simplified, in order: (1) a declaration marked `!important` wins; (2) otherwise the **more
specific** selector wins (§3.2); (3) still tied — **whichever appears later wins**. Step 3 is why
load order is not decoration: two files can both define `.hl-gold { color: … }` and the second one
loaded decides the colour.

### 3.2 Specificity, as three numbers

Every selector scores a triple **(ids, classes, elements)**:

| Selector | Triple | Why |
|---|---|---|
| `img` | (0,0,1) | one element name |
| `.newtemp-about-image` | (0,1,0) | one class |
| `img.sj-fit` | (0,1,1) | one class + one element |
| `.jumbotron .container h1` | (0,2,1) | two classes + one element |
| `#bg-1` | (1,0,0) | one id |

Compare **left to right**, like version numbers. `(0,1,1)` beats `(0,1,0)`: the class column ties,
the element column breaks it. One id beats any number of classes.

That comparison shaped a real decision here. When Stage F2 swapped legacy photos for smaller
"renditions", the plan was to tag each `<img>` with a helper class so its height stayed automatic.
`src/Media/Html.php:63-64` still says so:

```php
// and the tag carries the ORIGINAL's width/height plus the sj-fit
// class (tokens.css: img.sj-fit { height: auto }). Net effect per slot:
```

`img.sj-fit` is `(0,1,1)`; the shipped home rule it had to outrank, `public_html/css/home.css:271-275`,
is `(0,1,0)`:

```css
.newtemp-about-image { border: 4px solid black; width: 50%; object-fit: cover; }
```

So the helper *would* have won the height. **But read `tokens.css` today and there is no
`img.sj-fit` rule** — the measured outcome (§4.6) was simpler: a plain `<img>` with no width/height
attributes at all, per the newer comment at `src/Media/Html.php:70-78`. Take the old comment as a
worked specificity example, and take the general lesson: **trust the CSS file over a comment about
the CSS file.**

### 3.3 Inheritance

Some properties pass from parent to child automatically (`color`, `font-family`, `font-size`);
others never do (`border`, `padding`, `background`, `width`). That is how
`public_html/css/partials/navbar.css:9` sets the font for the whole site in one line —
`* { font-family: "League Spartan", sans-serif; font-weight: 400; }` — kept because "the original
navbar carried this global rule, so it applied to every page" (`navbar.css:7-8`).

### 3.4 The box model

Every element is a box: content, then `padding`, then `border`, then `margin`. The thing to
remember: does `width: 50%` measure content alone, or content + padding + border? Bootstrap's reboot
settles it globally — the vendored `public_html/assets/vendor/bootstrap-5.3.3/css/bootstrap.min.css`
contains `*,::after,::before{box-sizing:border-box}`. `border-box` means `width` *includes* padding
and border, so `.newtemp-about-image` above is exactly half its row **including** the 4 px border.
Under the other mode it would be 50% + 8 px and the row would overflow.

---

## 4. How we use it — every place in this codebase

### 4.1 The token file

`public_html/css/tokens.css` loads on every public page and exists only to name the brand
(`tokens.css:3-21`):

```css
:root {
  --sj-navy:      #2b4b8a;
  --sj-navy-dark: #1a355d;
  --sj-gold:      #ffd700;
  --sj-gold-ink:  #8a6d00;   /* gold-MEANING text on white — WCAG-safe (never use --sj-gold on white) */
  --sj-font-head:   "Fjalla One", sans-serif;
  --sj-font-accent: "Dancing Script", cursive;
  --sj-font-body:   "League Spartan", sans-serif;
```

A name starting with `--` is a **CSS custom property** (a *design token*): defined once on `:root`
(the `<html>` element), read anywhere as `var(--sj-navy)`. Because custom properties inherit (§3.3),
one definition reaches every element. The fonts match `CLAUDE.md:75` — Fjalla One for headings,
Dancing Script for accent words, League Spartan for body.

**Why `--sj-gold-ink` exists.** `CLAUDE.md:74` says: *"Navy `#2b4b8a` / `#1a355d`; gold `#ffd700`.
**Never gold text/icons on white** — use `#8a6d00` (`--gold-ink`)."* Bright gold on white is
unreadable and fails contrast; `#8a6d00` is the same hue, dark enough to pass. The token stores a
*decision*, not just a colour — that is the real argument for a token file. A hex code scattered
across 16 files carries no reasoning with it.

**Honest status:** `var(--sj-…)` appears **only inside `tokens.css` itself**. The legacy sheets
still declare their own — `public_html/css/home.css:6-13` has `--primaryblue: #2b4b8a`,
`--secondaryblue: #1a355d`, `--gold: #ffd700`. Same colours, different names. Renaming risks moving
pixels, which the freeze forbids, so two vocabularies coexist on purpose; unifying them is
post-freeze work.

### 4.2 The load order in `views/shell.php`

`views/shell.php:43-70` is the one place that decides which CSS a visitor gets:

| # | What | Line | Why here |
|---|---|---|---|
| 1 | Bootstrap 5.3.3, self-hosted | `:44` | Framework defaults first, so our rules win ties by order — not `!important`. |
| 2 | Font Awesome (icons) | `:46` | Independent of everything else. |
| 3 | Google Fonts, 4 families, one request | `:48-50` | `preconnect` first so DNS/TLS overlaps parsing. |
| 4 | `tokens.css` | `:53` | Variables must exist before a sheet reads them. |
| 5 | `footer.css` | `:54` | The footer is on every page. |
| 6 | the page's own sheets (`$styles`) | `:55-57` | Page CSS must beat framework CSS on ties. |
| 7 | generated `<style>` from the DB | `:58-65` | Editor-chosen backgrounds — §4.4. |
| 8 | admin overlay CSS | `:66-70` | Inside `if (is_admin())`; visitors download zero admin bytes. |

Step 6 is a `foreach` over `$styles`, which the page's controller supplies: `public_html/index.php:25`
passes `'styles' => ['home']`, with the comment (`:22-24`) *"Baseline head-cascade order: the page's
own CSS first, then the section sheets in template order."*

**Order proving itself.** Two files define the same class at the same specificity `(0,1,0)`:

| File:line | Rule |
|---|---|
| `public_html/css/tokens.css:26` | `.hl-gold { color: var(--sj-gold-ink); font-weight: 700; }` |
| `public_html/css/home.css:351-354` | `.hl-gold { color: var(--gold, #ffd700); font-weight: bolder; }` |

Tokens loads at shell line 53, `home.css` at line 56 — same specificity, later wins. So
gold-highlight text is `#ffd700` on the **home** page and `#8a6d00` everywhere else. Not a bug to
fix: it is the shipped rendering the freeze protects, and step 3 of the cascade in front of you.

### 4.3 One sheet per page family

`public_html/css/` holds **16** files, `public_html/css/partials/` holds **10**. The mapping is
visible via `grep -h "'styles'" public_html/*.php`:

| Sheet | Requested by | Pages |
|---|---|---|
| `academy.css` | the academy family (`tamilacademy.php`, `ncc.php`, …) | 18 |
| `album.css` | the gallery albums (`gal-annual.php`, …) | 10 |
| `sections.css` | `kg.php`, `primary.php`, `highschl.php`, `highsec.php` | 4 |
| `about`, `academics`, `achievements`, `co-curriculum`, `gallery`, `home`, `infrastructure`, `sports`, `staffs` | one page each | 9 |
| `tokens.css`, `footer.css` | the shell | all |
| `home-bs4-remnants.css` | home only, body-linked (§4.5) | 1 |
| `admin.css` | logged-in admins only | — |

`partials/` holds the shared fragments' sheets: `navbar.css`, `preloader.css`, `scroll-up.css`,
`jumbotron.css`, `testimonial.css`, `marks-scroll.css`, `new-updates.css`, `update-scroll.css`,
`groups.css`, `gallery-slider.css`. Each is linked by its own partial, e.g.
`views/partials/navbar.php:8` — and those links sit **in the body**, deliberately: a `<style>` block
inside `<body>` is invalid HTML, but a body `<link rel="stylesheet">` is valid, so moving each block
to a file at *exactly the same spot* fixed 156 validator errors and left the cascade position
untouched ([`../07-stage-g.md`](../07-stage-g.md), "R3").

**The deliberate trade.** More files = more requests. `docs/perf-baseline.md:72` records **47
requests** on home against a target under 25, and `:102-105` defends it: *"the biggest groups are
per-partial CSS (11 files, deliberately split for cacheability/validity in R3) and font files.
HTTP/2 on the real host makes the count largely moot; merging would trade maintainability for a
metric."* What we buy: a visitor who loads three pages downloads `navbar.css` **once**, because
`public_html/.htaccess:49-51` sets `Cache-Control "public, max-age=31536000, immutable"` on every
`.css` — plus clear ownership, since a navbar bug lives in `navbar.css`, full stop.

### 4.4 Cache-busting, and CSS generated from the database

Because CSS is cached for a year, a changed file would never reach returning visitors. Every
`<link>` ends `?v=<?php echo SJ_ASSET_VER; ?>`, and that constant is one line,
`public_html/bootstrap.php:15`: `define('SJ_ASSET_VER', '20260809.2');`. Change the string and every
CSS/JS URL changes, so every browser refetches. Its comment (`:11-13`) notes it replaced an old
`?v=time()` that "re-downloaded every asset on every request" — caching that never caches is worse
than none.

Some backgrounds are photos an *editor* picks in the admin panel, so their CSS cannot be
hand-written. The shell exposes one hook (`views/shell.php:58-65`): if the page view set
`$sjHeadCss`, the shell prints it inside a head `<style>`. The 18 academy pages set one rule
(`views/pages/academy.php:13-15`):

```php
$sjHeadCss = "  .bg-1 { background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('"
    . e($sj_academy['image'] ? img_url($sj_academy['image'], 'bg_wide') : '')
    . "') no-repeat; background-size: cover; }";
```

and `views/pages/infrastructure.php:36-41` loops its facilities to build `#bg-1`, `#bg-2`, …. Note
`e(…)` around the URL — the project-wide escaping helper, required because the value comes from the
database. Both comments make the same cascade promise: *"nothing else styles `.bg-1`, so the
position change cannot alter the cascade"* (`views/pages/academy.php:11-12`). Moving CSS is only
safe when you can name what else touches the same selector.

A second, historical form existed: `database/extract-gallery.php:28` scraped each old album page's
`<style>` into `public_html/css/albums/{slug}.css`. Those ten files were **deleted** in commit
`a6473bc` when one `album.css` replaced the clones — which is why `css/albums/` is absent from your
checkout and every album controller passes `'styles' => ['album']`. The comment at
`views/pages/album.php:4-5` still points at the old path; like the `sj-fit` comment, it is stale.
`CLAUDE.md:55` still names that directory as the one place a generator may write inert assets into
the webroot.

### 4.5 The visual freeze

Verbatim from `CLAUDE.md:38-40`:

> 1. **Pixel-identical.** Every converted or refactored page must render **exactly** like the
>    pre-revamp production site (baseline: git commit `6bd0d11` and live
>    `https://stjosephsondipudur.com/`). Fonts, colours, spacing, backgrounds, sizes, borders,
>    hover states — all unchanged.
> 2. **Compensate for framework drift.** When a code change would otherwise move the pixels (e.g.
>    the Bootstrap 4→5 migration drops `.jumbotron`, renames classes, or changes default paddings),
>    you **must add compensating CSS so the rendered result stays identical.**
> 3. **Don't "fix" quirky-but-shipped CSS.** … Reproduce the original's *rendered result*, not your
>    idea of correct code.

**Compensation 1 — the dropped component.** Bootstrap 5 deleted `.jumbotron`, which our admissions
band uses. `public_html/css/partials/jumbotron.css:10-14` re-creates *only* its padding:

```css
/* Compensation only: re-create BS4's .jumbotron / .jumbotron-fluid padding
   (BS5 dropped the component). Everything else below is the original CSS. */
.jumbotron { padding: 2rem 1rem; }
@media (min-width: 576px) { .jumbotron { padding: 4rem 2rem; } }
```

**Compensation 2 — the winner that disappeared.** The old home page's *last* stylesheet was
Bootstrap 4.3.1 inside the footer template; being last it won every same-specificity tie.
`public_html/css/home-bs4-remnants.css:1-12` is verbatim BS4 fragments reinstated in the same slot —
*"it (a) supplied BS4-only rules that Bootstrap 5 dropped … and (b) WON every same-specificity tie
against the page's own CSS … DO NOT tidy, merge, or 'fix' values here."* It even caps a breakpoint
tier BS4 never had (`:91-93`): `@media (min-width: 1400px) { .container { max-width: 1140px; } }`.
It is linked from the page **body**, with a warning at `views/pages/home.php:98-102` ending *"it
must be linked HERE — after the section partials — not in the `<head>`. Do not move it."*

**The quirk you must not fix.** `public_html/css/partials/navbar.css:13-22`:

```css
.navbar {
  /* NOTE: the original shipped `background: linear( #2b4b8a 20%, #ffffff 70%)`
     — an INVALID CSS function the browser IGNORED, so the navbar keeps
     Bootstrap's .bg-light (light grey). That grey is the intended production
     look. … Do NOT "correct" it to linear-gradient() — that introduces a navy
     gradient and changes the design.
  background: linear( #2b4b8a 20%, #ffffff 70%);
  */
```

`linear()` is not a CSS function. Browsers drop declarations they cannot parse, so the navbar falls
back to `.bg-light` grey. Every visitor for years has seen grey; grey **is** the design. "Correcting"
the typo would silently ship a navy gradient nobody approved. That is the point of rule 3: **the
rendered result is the specification, not the source code.**

### 4.6 How pixel identity was actually verified

Eyeballing cannot catch 0.24 px, so Stage G built the instrument before touching anything
([`../07-stage-g.md`](../07-stage-g.md), "The safety net came first"):

- For **26 representative pages × 2 viewports** (desktop + mobile) it recorded **every element's
  layout rectangle** — via `getBoundingClientRect()`, the browser API returning exact position and
  size in pixels — **and its full computed style**, including `::before`/`::after` pseudo-elements.
- Non-determinism was removed first: **animations frozen, carousels pinned to slide 1, counters
  forced to final values**, and (for the image work) **lazy images force-loaded**. Otherwise the
  "difference" you measure is just the page being alive.
- The 52-capture baseline was stored, the work done, the capture re-run: *"Anything that moved by
  even a tenth of a pixel shows up."*

It paid immediately, catching *"a regex that ate half the gallery page, a heading pinned 0.24px too
large"*. The headline result for the image migration is in `PHASES.md:142`: **"16/16 pages, zero
elements moved >2 px"**. It also overruled two confident theories, both in
[`../08-stage-h.md`](../08-stage-h.md): wrapping images in `<picture>` made the *wrapper*, not the
styled `img`, the flex item — so shipped rows wrapped; and cover-cropping changed pages whose slots
size themselves from the photo's own aspect ratio. Verdicts: plain `<img>`, downscale-only
renditions. The doc's own summary — *"measure, don't reason — layout bugs rarely live where the
theory says"* — is what `src/Media/Html.php:70-78` encodes, and why the `sj-fit` idea was dropped.

### 4.7 What R2 consolidated

Stage G's R2 cut public CSS from **34 files to 15, 170,671 → 98,495 bytes (−42.3%)**, and killed the
JavaScript twin of the same duplication: the scroll-reveal effect existed in **23 copies** (16
inline `<script>` blocks and 7 js files). They became two functions in `public_html/js/site.js` —
`sjReveal()` at `:16` and `sjBlurCards()` at `:40` — loaded by `views/shell.php:88` before any
per-page script, with each page passing *its own shipped threshold*
(`function sjReveal(selector, revealPoint, initNow)`), so no animation changed timing. The last
legacy directories, `_libs/` and `_templates/`, were deleted in the same stage;
`public_html/bootstrap.php` replaced `_libs/load.php`, and the historic helper names (`repo_*()`,
`img_tag()`, `e()`, `db()`) live on in `src/helpers.php` so **no view changed a single call**.

---

## 5. Why this is the right approach here

"Why not modern CSS tooling?" Because the constraints are tight: production is **MilesWeb shared
hosting with no SSH**, deploy is **a file upload**, `vendor/` is committed so the host never builds,
the design is **frozen**, and there is **one maintainer** plus a legacy stylesheet nobody may
improve yet.

| Alternative | What it buys | Why not here |
|---|---|---|
| **Sass / PostCSS** (compile `.scss` → `.css`) | Nesting, mixins, variables | Needs Node on the build machine and build-then-upload discipline. Deploy is drag-and-drop into mPanel; a forgotten compile ships stale CSS with no error. |
| **Tailwind** (utility classes in HTML) | Tiny output, no naming debates | It *is* a redesign — you rewrite every class in every template. The freeze forbids exactly that, and there is no legacy CSS for it to wrap. |
| **CSS-in-JS** | Component-scoped styles | The site is server-rendered PHP with almost no JS. Styling would start depending on JS running — worse for speed, SEO and no-JS visitors. |
| **One giant stylesheet** | Exactly 1 request | Every page downloads every page's CSS, one bad edit breaks 42 pages, and ownership disappears — the failure mode we just escaped. |
| **What we do** | Plain CSS, per-family sheets, one token file, order fixed in one place | No toolchain to forget, readable by anyone who knows CSS, one owner per file. |

Custom properties earn their place precisely because they are **plain CSS with no build step** — the
one modern feature available for free. A Sass variable is resolved at compile time and vanishes;
`var(--sj-navy)` is live in the browser. And the freeze is not bureaucracy: it is what makes a large
refactor *reviewable*. When "does it look the same?" is the only acceptance question, a 34-file CSS
merge, a Bootstrap major-version jump and 368 validator fixes can all ship without anyone arguing
about taste.

---

## 6. How this scales

**At 10× the pages (~400)**, the shell's ordered `<link>` list, the token file and the per-family
sheet all still work — the model already scales by *family*, not page: 18 academies share one
`academy.css`, 10 albums share one `album.css`. The 19th academy costs zero CSS. What starts to hurt:

- **Request count.** On **HTTP/1.1** a browser opens ~6 connections per host and files queue, so 26
  sheets genuinely cost round-trips. On **HTTP/2** (which the production LiteSpeed host speaks) they
  share one connection and stream in parallel — which is why `docs/perf-baseline.md:104` calls the
  47-request count "largely moot" there. Judge the number against the protocol you actually serve.
- **Ordering by hand.** A dozen `<link>` tags whose sequence carries meaning is fine; a hundred is
  not. Two exits: a **bundler** (a build step concatenating sheets in a declared order — worth it
  only once a build step exists in the deploy at all), or **CSS cascade layers**
  (`@layer base, components, page;`), which declare precedence *explicitly* instead of relying on
  file order. Layers need no tooling, so they are the natural first step — but they change which
  rule wins, so they can only land **after** the freeze lifts.
- **Token adoption.** Same moment: migrate `--primaryblue` → `--sj-navy` post-freeze, one page
  family at a time, each verified with the §4.6 harness.

A good trigger to revisit: when a new page needs a sheet that is neither page-specific nor shared by
a family. That is the signal the two-bucket model has run out and a third bucket — genuinely shared
components — is due.

---

## 7. Gotchas and mistakes to avoid

**1. Specificity wars and `!important`.** When your rule loses, the instinct is `!important`. It
works once; then the next person needs it too and nobody can override anything.
`public_html/css/partials/jumbotron.css:16-22` carries seven `!important`s in seven lines, inherited
from the original. Prefer: put your sheet later in the order, or add one element name — `.thing` →
`img.thing` turns `(0,1,0)` into `(0,1,1)`.

**2. Editing CSS after bumping `SJ_ASSET_VER`.** With `max-age=31536000, immutable`, a URL the
browser already fetched is *never* re-requested. Bump the version, then edit the sheet, and your
browser keeps serving the copy it grabbed at the moment of the bump — you debug CSS that is not the
CSS on disk. This bit the project repeatedly; [`../08-stage-h.md`](../08-stage-h.md) records the same
bug shape for image versions: *"a stylesheet edited after its version bump is a stylesheet nobody
downloads."* Rule: **edit first, bump last**, and hard-reload while developing.

**3. Editing a shared partial moves a page you never opened.** `navbar.css` is on all 41 public
pages, `academy.css` on 18, `sections.css` on 4. Grep who requests a sheet
(`grep -h "'styles'" public_html/*.php`) and check *those* pages too. And remember `navbar.css:9`
sets `font-family` on `*` — a "small navbar tweak" can restyle the whole site.

**4. `aspect-ratio` and flex interactions.** A flex item's final size comes from
`flex-grow`/`flex-shrink`/`flex-basis`, not just `width` — and inserting a wrapper changes *which
element is the flex item*. That is exactly how `<picture>` broke shipped rows (§4.6). Likewise, when
a slot is sized `width: 100%; height: auto`, the image's own proportions are load-bearing layout
input: change the crop and you change the page.

**5. Assuming a "harmless" tidy-up is invisible.** It usually is not. Deleting a "dead" rule
something still matches, normalising `.375rem` to `6px`, merging selectors that had different
specificity, reordering declarations around an `!important` — each can move pixels. Under this
repo's rules that is not a taste disagreement but a rule violation: `CLAUDE.md:44` requires you to
affirm **`Visual-freeze: PASS`** (or list every intentional change *with user sign-off*) before
marking any CSS change complete.

**6. Believing comments over files.** Two live examples above: `src/Media/Html.php:63-64` describes
an `img.sj-fit` rule `tokens.css` does not contain, and `views/pages/album.php:4-5` points at a
`css/albums/` directory deleted in commit `a6473bc`. Always open the CSS.

---

## 8. Try it yourself

Everything runs in Docker; nothing installs on your machine: `./run.sh`, then open
<http://localhost:8090/>.

**A. Watch the cascade resolve a tie.** Press **F12** for devtools, right-click one of the two
side-by-side about images → **Inspect**. The **Styles** panel shows `.newtemp-about-image` (from
`home.css:271`); rules that *lost* appear **struck through**, and hovering one tells you which file
and line won. Switch to the **Computed** tab: that is the final value after cascade + inheritance,
with an arrow back to the winning rule.

**B. See the load order for real.** View source (`Ctrl+U`) on the home page and read the `<link>`
tags top to bottom: Bootstrap → Font Awesome → fonts → `tokens.css` → `footer.css` → `home.css`.
Then scroll to the bottom of the body and find `home-bs4-remnants.css` — the file that must stay
last (§4.5).

**C. Change a token, then put it back.** (1) Edit `public_html/css/tokens.css`:
`--sj-navy: #b00020;`. (2) Edit `public_html/bootstrap.php:15`, change `SJ_ASSET_VER` to a new
string. (3) Reload and notice how *little* moves — proof of §4.1: the legacy sheets read
`--primaryblue`, not `--sj-navy`. (4) **Revert both**
(`git checkout -- public_html/css/tokens.css public_html/bootstrap.php`).

> ⚠️ **Step 4 is not optional.** A change to how any page looks must never be committed without
> explicit sign-off — `CLAUDE.md:42`: *"Intentional visual changes need explicit user approval first
> — even improvements."* Experiment freely; commit nothing.

**D. Read the most instructive three lines in the repo.** Open
`public_html/css/partials/navbar.css` and read the preserved `linear(…)` story. Then ask what would
have happened if a well-meaning reviewer had "fixed" it.

---

## 9. Where to read more

| Document | What it adds |
|---|---|
| [`../04-stage-c-and-d.md`](../04-stage-c-and-d.md) | The Bootstrap 4→5 migration: the `data-` dialect, `tokens.css` arriving, the "three Bootstraps" detective story. |
| [`../07-stage-g.md`](../07-stage-g.md) | R2/R3 in full: 34 → 15 CSS files, the 52-capture layout harness, 368 → 0 validator errors, `_libs`/`_templates` deleted. |
| [`../08-stage-h.md`](../08-stage-h.md) | Where CSS meets images: why `<picture>` broke flex rows, why crops move layouts, the version-bump caching lesson. |
| [`../../../CLAUDE.md`](../../../CLAUDE.md) | The normative rules — the visual-freeze section and the theme tokens at lines 72-75. Read before any CSS edit. |
| [`../../../ADMIN_UI_DESIGN.md`](../../../ADMIN_UI_DESIGN.md) | The admin panel's own token/component system — *not* frozen, and Segoe UI rather than the public fonts. |
| [`../../perf-baseline.md`](../../perf-baseline.md) | The request-count and payload numbers quoted in §4.3 and §6. |

**Outside**

- MDN, [Handling conflicts / the cascade](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_cascade/Cascade)
- MDN, [Specificity](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_cascade/Specificity) — source of the three-number notation in §3.2
- MDN, [Using CSS custom properties](https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_cascading_variables/Using_CSS_custom_properties)
- Bootstrap, [Migrating to v5](https://getbootstrap.com/docs/5.3/migration/) — the official list of what v5 dropped and renamed, including `.jumbotron`
