# Valid HTML and Accessibility

> **What you'll learn:** what an HTML *validator* checks, why "browsers are forgiving" is the problem and not
> the solution, what accessibility means from first principles, and every accessibility practice this codebase
> applies — with the real lines that do it.
>
> **Prerequisites:** you can read basic HTML (`<div>`, `<a>`, `<img>`) and basic PHP (`<?= ... ?>`, `foreach`).
> Nothing else. Every term is defined the first time it appears.
>
> **Where it lives in our code:** the page skeleton is `views/shell.php`; reusable chunks are
> `views/partials/*.php`; page bodies are `views/pages/*.php`; colour rules are `public_html/css/tokens.css`.
> The work itself was stage **R3** — [`../07-stage-g.md`](../07-stage-g.md) and row 31 of `PHASES.md`.

---

## 1. The one-paragraph version

HTML has an actual specification — a rulebook saying which tag may sit inside which, which attributes exist,
and that an `id` must be unique on a page. A **validator** is a program that reads your page and reports every
place you broke that rulebook. Browsers report nothing: they *guess* and carry on. Two browsers can guess
differently, so invalid HTML makes your page a coin-flip. **Accessibility** (abbreviated **a11y** — "a", 11
letters, "y") is the separate-but-overlapping question of whether people who don't use a mouse, can't see the
screen, or can't tell two colours apart can still use the site. Valid HTML is most of the foundation, because
assistive software reads the same structure the validator checks. In stage R3 we ran the W3C validator over
all 42 pages and went from **368 errors to 0** (`docs/learn/07-stage-g.md:88`), added the missing
accessibility attributes — and not one pixel moved.

## 2. The problem this solves

### 2.1 "The browser fixes it" is the bug, not the feature

Forget a `</div>` and Chrome shows no error; it silently invents a closing tag where it thinks one belongs.
That feels generous. It is the worst possible behaviour, for three reasons. **Different guesses:** recovery
rules are only partly standardised, so older browsers, in-app webviews, screen readers and crawlers do not all
recover identically — your page renders one way for you and another for a parent on an old Android phone.
**Silent failure:** a bug that never announces itself ships. Our home page's update carousel had an unclosed
`<div>` for the entire life of the original site; the browser auto-closed it at `</section>` every time, so
nobody noticed (`docs/learn/07-stage-g.md:119-122`). **It hides real breakage behind fake success:** the page
"works", so nobody looks.

### 2.2 What actually breaks, concretely

| Invalid thing | Validator says | What really goes wrong |
|---|---|---|
| Two elements, same `id` | "Duplicate ID" | `getElementById()` returns only the **first**; `<a href="#thing">` jumps to the first; `<label for="x">` points at the wrong input |
| `<style>` inside `<body>` | "Element style not allowed as child of body" | Legal in `<head>`, illegal in body — and inline styles can't be cached, so every page re-downloads them |
| `<a><button>…</button></a>` | "button not allowed as descendant of a" | Two nested things both want the click; keyboard and screen-reader users get an ambiguous control |
| `<p><div>…</div></p>` | "div not allowed as child of p" | The parser **closes the `<p>` early**; your `p > div` CSS now matches nothing |
| Unclosed `<div>` | "End tag section seen, but there were open elements" | The DOM tree is not the tree you wrote |
| Heading jump `h1` → `h4` | flagged as a structure problem | A screen-reader user navigating by headings thinks they skipped a section |

### 2.3 Who accessibility is for

Not a niche. **Screen-reader users** run software (NVDA, JAWS, VoiceOver) that reads the page aloud — it reads
*the markup*: headings, links, `alt` text, labels, so an icon with no text is silence. **Keyboard-only users**
(motor impairment, RSI, or a broken trackpad) press `Tab`; if your "button" is a `<div>` with an `onclick`,
`Tab` never reaches it. **Low-vision users** zoom to 200% or use high-contrast mode, and pale-on-pale text
disappears. **Colour-blind users** are roughly 1 in 12 men, so "the red field is the error one" conveys
nothing. And **everyone else, sometimes**: a parent reading the fee page on a bright bus is temporarily
low-vision; someone holding a baby is temporarily one-handed. For a **school** this is an inclusion
obligation, not just craft — the site is how a parent with a disability finds admissions dates, the phone
number, the exam timetable. If they can't, the school has excluded them from information everyone else got.

## 3. How it works in general

A validator parses your HTML against the spec's grammar and reports violations. The one everybody means is the
**W3C Nu HTML Checker**, <https://validator.w3.org/nu/> — paste HTML (or give a public URL) and it lists every
error with line and column. Two things about its scope. **It checks structure, not meaning:** it confirms
every `<img>` *has* an `alt`, but cannot tell you whether the alt text is any good. **Some a11y rules are
mechanically checkable and some aren't:** unique `id`s and `alt` presence *are*, which is why our stage doc
notes "the Nu checker also enforces unique `id`s and `alt` attributes, so those acceptance items are proven by
the same run" (`docs/learn/07-stage-g.md:90-92`). Alongside it sit **Lighthouse** (in Chrome DevTools — its
Accessibility audit runs automated checks and scores 0–100) and **your own keyboard**, which finds problems no
tool can.

| Term | Plain meaning |
|---|---|
| **Semantic HTML** | Using the tag that means the thing: `<button>` for a button, `<nav>` for navigation, `<h2>` for a subheading. The tag *is* the announcement to assistive tech. |
| **ARIA** | "Accessible Rich Internet Applications" — extra `aria-*` attributes adding meaning HTML can't express. A patch, not a substitute for the right tag. |
| **`alt` text** | The text an `<img>` falls back to: read aloud by screen readers, shown if the image 404s. |
| **Landmark** | A region tag (`<header>`, `<nav>`, `<main>`, `<footer>`) a screen reader can jump straight to. |
| **Focus** | Which element currently receives keyboard input. The *focus ring* is the outline showing it. |
| **Contrast ratio** | How different two colours are in brightness: 1:1 (identical, invisible) to 21:1 (black on white). |
| **WCAG** | Web Content Accessibility Guidelines, the international standard. Level **AA** is the normal target. |

**Contrast ratio in plain words:** take the text colour and the colour directly behind it, measure how much
light each reflects, divide brighter by darker. Identical colours give 1:1 and are invisible; black on white
gives 21:1. WCAG AA asks for **at least 4.5:1** for body text and 3:1 for large bold text (≥18.66px bold).
That is a measurement, not an aesthetic opinion.

## 4. How we use it — every place in this codebase

### 4.1 The headline result

> "## R3 — the W3C validator: 368 errors → 0" — `docs/learn/07-stage-g.md:88`

All 42 documents validate clean. `PHASES.md:136` records the same number plus the acceptance criterion:
*"Validator: 0 errors on all 42 URLs; grep finds no duplicate ids, no `../photos`."* §4.10 breaks the 368
down by cause.

### 4.2 `alt` text — and when `alt=""` is right

Rule: **every `<img>` needs an `alt` attribute.** What goes *in* it depends on whether the image carries
information. An **informative** image describes what it conveys; a **decorative** one gets `alt=""`,
deliberately empty, telling the screen reader "skip me". An *empty* alt is correct; a *missing* alt is not,
because the reader then falls back to announcing the filename. Most images here come from the database, so the
safety net lives in one place — `src/Media/Html.php:52`, the last line below.

```php
<img class="img-1" src="/photos/logo-main.png" alt="St.Joseph's logo">  <!-- navbar.php:12 — informative -->

<div class="image year-<?= e($Y['year_label']) ?>"><?= img_tag($ph, 'gallery_full', ['alt' => '']) ?></div>
<!-- views/pages/album.php:24 — decorative: 30 holiday photos in a lightbox grid; announcing 30 filenames
     would be pure noise -->

$alt   = ' alt="' . e($attrs['alt'] ?? ($img['alt_text'] ?? '')) . '"';   // src/Media/Html.php:52
```

Read that last line carefully: the caller's `alt`, else the `alt_text` column stored with the image, else
empty string. **The attribute is never absent.** That one line is why `alt` presence passes on every
DB-driven image on the site.

### 4.3 `aria-label` on icon-only links

An icon font renders a glyph with no text node, so a screen reader announces "link" and stops. `aria-label`
supplies the missing name (`views/partials/footer.php:42-43`):

```html
<a href="<?= e($sj_f_yt) ?>" target="_blank" rel="noopener" class="social-icon" aria-label="YouTube channel"><i class="fab fa-youtube"></i></a>
<a href="<?= e($sj_f_fb) ?>" target="_blank" rel="noopener" class="social-icon" aria-label="Facebook page"><i class="fab fa-facebook-f"></i></a>
```

### 4.4 Descriptive names for "Read more" / "Learn more"

Screen readers can list every link on a page out of context. Twenty links all called "Read more" are twenty
identical, useless entries. `aria-label` overrides the *announced* name while the visible text stays exactly
as designed — which is what made this legal under the visual freeze.

```php
<a class="btn btn-primary btn-lg" href="about.php" role="button" aria-label="Read more about the school">Read more</a>
<!-- views/pages/home.php:29 -->

<a class="btn btn-primary btn-lg" href="<?= e($ac['slug']) ?>.php" role="button" aria-label="Read more about <?= e($ac['name']) ?>">Read more</a>
<!-- views/pages/co-curriculum.php:55 — inside a foreach over 18 academies, so each card gets a DIFFERENT
     label built from its own name: one template line, 18 correct labels -->

<a class="btn btn-primary btn-lg" href="/index.php#contact" role="button" aria-label="Admissions — contact the school"><?= e(repo_setting('jumbotron_btn', 'Learn more')) ?></a>
<!-- views/partials/jumbotron.php:10 -->
```

### 4.5 The map `<iframe>` and the `tel:` link

An `<iframe>` with no `title` is announced as an unlabelled frame. The phone link is accessibility and
correctness meeting: the displayed number is human-formatted (`0422-2271367`) but a `tel:` href must be
diallable digits, so we strip non-digits at render time from the *same* setting — the two can never disagree.
`preg_replace('/[^0-9]/', '', ...)` means "replace every character that is not 0–9 with nothing". R3 lists
this as the `tel:` normalisation (`docs/learn/07-stage-g.md:126`).

```php
<iframe
  title="Map: St.Joseph's Matriculation Higher Secondary School, Ondipudur"  <!-- contact.php:105 -->

<a href="tel:<?= e(preg_replace('/[^0-9]/', '', $sj_c_phone)) ?>"> + <?= e($sj_c_phone) ?></a>
<!-- views/partials/contact.php:88 -->
```

### 4.6 Unique `id`s

An `id` must be unique per document. The album template's header comment records the fix, which closed two
bugs at once, and the ids that remain are generated *from the data*, so a loop cannot collide. `for=` matching
`id=` is what makes the label clickable and what makes a reader say "2019, radio button" instead of just
"radio button".

```php
// C9 fixes baked in: year-toggle labels' for= now always match their input ids
// (bug 7 — labels are clickable); the unused duplicate image-N ids are gone
// (bug 11); ...                                            // views/pages/album.php:7-9

<input type="radio" class="btn-check" name="btnradio" id="btnradio<?= e($Y['year_label']) ?>"<?= $yi === 0 ? ' checked' : '' ?>>
<label class="btn btn-outline-primary" for="btnradio<?= e($Y['year_label']) ?>"><?= e($Y['year_label']) ?></label>
<!-- views/pages/album.php:17-18 -->
```

### 4.7 Heading order and landmarks

Headings are an outline, not a font-size picker: `h1` → `h2` → `h3`, no skipping. 32 of the 368 errors were
skipped levels. The catch: heading tags carry default sizes, so re-levelling would normally *change the
design*. The fix pins the old size onto the new tag — `public_html/css/footer.css:33` opens
`.footer-column h2 { /* was h4 — R3 heading-order fix */`, with the size formula and the ≥1200px cap just
beneath (`:37`, `:41`). The stage doc notes the first attempt was **0.24px** too tall and the cap fixed it
(`docs/learn/07-stage-g.md:108-113`). That is the standard of care here. Landmarks come from the shell and
partials: `views/shell.php:24` emits `<html lang="en">` (so the reader knows which language to pronounce),
`views/partials/navbar.php` supplies the `<nav>`, and `views/partials/footer.php:13` opens
`<footer class="footer">`.

### 4.8 Keyboard navigation and visible focus

Two rules, both mostly about *not* doing things. **Use real interactive elements** — a real `<button>` or
`<a href>` is focusable and works with `Enter`/`Space` for free. Our carousel controls are real buttons with
real text for the reader, hidden visually by Bootstrap's `visually-hidden`, which moves text off-screen for
sighted users while leaving it in the accessibility tree. **Don't delete the focus ring** — grep this repo's
public CSS for `:focus` and you get one result (`public_html/css/home.css:800`); nobody has stripped outlines
site-wide. Where something must be hidden from *everyone*, say so explicitly: the anti-spam honeypot field is
invisible, unreachable by `Tab` (`tabindex="-1"`), and hidden from assistive tech (`aria-hidden="true"`) all
at once.

```html
<span class="visually-hidden">Previous</span>   <!-- views/partials/carousel.php:36 -->

<input type="text" id="website" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
<!-- views/partials/contact.php:56 -->
```

### 4.9 Colour contrast — the "never gold on white" rule

Brand gold `#ffd700` on white measures about **1.4:1**. The AA floor is 4.5:1. Gold on white is, measurably,
nearly invisible. Hence the hard project rule at `CLAUDE.md:74`: *"Navy `#2b4b8a` / `#1a355d`; gold `#ffd700`.
**Never gold text/icons on white** — use `#8a6d00` (`--gold-ink`)."* The measurements behind it are in
`ADMIN_UI_DESIGN.md:187`: `#ffd700` on `#1a355d` ≈ 8.7:1 (AAA), on `#2b4b8a` ≈ 6.0:1 (AA), **on white ≈ 1.4:1
— FAIL**; the ink-safe `#8a6d00` ≈ 4.9:1 (AA). Gold is fine *on navy*; only white backgrounds kill it.

```css
  --sj-gold-ink:  #8a6d00;   /* gold-MEANING text on white — WCAG-safe (never use --sj-gold on white) */
.hl-gold { color: var(--sj-gold-ink); font-weight: 700; }
/* public_html/css/tokens.css:8 and :26 */
```

`.hl-gold` is the *only* span class the rich-text sanitizer allows through (`CLAUDE.md:64`), so when an editor
clicks "Gold" in the admin toolbar they get the accessible gold and cannot get the invisible one.

> ⚠️ **Naming mismatch to know about.** `CLAUDE.md:74` and `ADMIN_UI_DESIGN.md:17` call the token
> `--gold-ink`; the public stylesheet defines `--sj-gold-ink` (`public_html/css/tokens.css:8`). Same colour,
> two spellings. Use the name your file's context uses; don't assume the docs' spelling compiles.

### 4.10 The 368 errors, and how each fix avoided moving pixels

| Error class | Count | Fix | Why pixel-neutral |
|---|---|---|---|
| `<style>` in `<body>` | 156 | Moved verbatim to `/css/partials/<name>.css`, linked at the identical spot | A `<link>` in the body *is* valid; same rules, same cascade position |
| Invalid `background: linear(…)` | 41 | Commented out, full story kept in place | The browser already ignored it — that's *why* the navbar is grey |
| Heading-level skips | 32 | Re-levelled, old size pinned in CSS | Verified rect-identical to 0.1px |
| `<button>`/`<label>` inside `<a>` | 25 | Became `<span class="btn …">` | Bootstrap's `.btn` styling is class-based |
| Unclosed `<div>` | 2 | Closed exactly where the parser already recovered | DOM unchanged |

(All from `docs/learn/07-stage-g.md:94-127`.) Read the navbar comment in full at
`public_html/css/partials/navbar.css:13-26` — the clearest statement in the repo of "reproduce the rendered
result, not your idea of correct code."

### 4.11 The visual-freeze tension, honestly

`CLAUDE.md` freezes the design: *"Until the full code migration and the admin-panel workflow are complete, do
not change how any page looks."* Yet R3 shipped a pile of accessibility work. Both are true because of one
property: **almost every fix here is an attribute, not a style.** `aria-label`, `alt`, `title`, `for`, a
unique `id` — none paint a pixel. That is precisely why they were allowed through a freeze. Where a fix
*would* have moved pixels we paid to keep them still: heading re-levelling got compensating CSS, and
`<button>` → `<span class="btn">` was checked against a 52-capture computed-style and rectangle harness
(`docs/learn/07-stage-g.md:19-25`). Be honest about the cost, though: **the freeze blocks a whole category of
accessibility work.** If a shipped colour pair fails contrast in the existing design, we cannot just fix it —
that is a visual change needing explicit sign-off. The token rule constrains *new* and *rich-text* content,
not every legacy pixel. Such fixes are deferred, not denied.

### 4.12 How this connects to SEO and Lighthouse

A search crawler is, functionally, a very fast blind user. It reads headings, `alt` text, link text and `lang`
— the same signals a screen reader does. `docs/learn/08-stage-h.md:174-175` puts it directly: heading
structure "isn't just accessibility — it tells the engine what the page is about"; `:237-241` lists valid
HTML, heading order, alt attributes and unique IDs as the quiet on-page work SEO textbooks assume you already
did. The measured payoff, from `docs/perf-baseline.md:79-84`:

| Page | Perf | **A11y** | Best-Pr. | SEO |
|---|---|---|---|---|
| academy (tamil) | 96 | **100** | 96 | 100 |
| album (gal-annual) | 72 | **100** | 96 | 100 |
| home | 64 | **96** | 96 | 92 |
| infrastructure | 65 | **100** | 96 | 92 |

Accessibility 96–100 on all four. `PHASES.md:144` records what moved the needle in that pass: *"iframe title +
social-link/Read-more aria-labels (a11y 89→96+)"* — three attribute changes, seven points. There is an irony
in the SEO column: `docs/perf-baseline.md:86-89` notes the two 92s lose points **only** for the literal words
"Read more"/"Learn more", the same weakness `aria-label` patched for screen readers. The real cure is better
button copy, editable in Site Settings.

## 5. Why this is the right approach here

Alternatives, weighed against the actual constraints — a frozen visual design, 42 hand-written pages, no build
pipeline, one maintainer:

| Option | What it gives | Why not (here) |
|---|---|---|
| **Automated a11y CI gate** (axe/pa11y per push) | Catches regressions forever, no human discipline needed | Needs CI. Prod is MilesWeb shared hosting, no SSH (`CLAUDE.md`), and there is no build step to hang it off. The right *future* move, not the right first one. |
| **Adopt a component library** with semantics built in | Correct markup by construction | The design is frozen pixel-for-pixel against commit `6bd0d11`. Swapping in components changes rendering by definition — the one forbidden thing. |
| **Ignore accessibility** | Zero effort | It's a school. And it isn't even free: the same markup drives SEO, and Lighthouse scores it whether you care or not. |
| **What we did:** one-off validator sweep + attribute fixes in shared templates | 0 errors, A11y 96–100, zero pixels moved, no new tooling | Doesn't *prevent* future regressions on its own. Accepted, with the CI gate named as the follow-up. |

The decisive constraint is the freeze. It rules out anything touching rendering, which rules out almost every
off-the-shelf answer, and leaves exactly the attribute-level work R3 did. The second constraint — **one
maintainer** — is why the fixes went into `views/partials/` and `views/shell.php` rather than into 42 pages.
The footer's two `aria-label`s are written once and correct site-wide.

## 6. How this scales

Today: 42 pages. The design goal was always more. **Templates make correctness cheap:** `views/shell.php`
renders the `<html lang>`, the head, the navbar, the footer and the scripts for *every* page, so fixing an
accessibility bug in a partial fixes it site-wide instantly. At 100 pages the number of *places* to get right
does not grow — a new page is a new `views/pages/*.php` body inheriting correct chrome. Three habits keep that
true:

1. **New editable field → registry.** Content is data, so it flows through helpers that already emit `alt`
   (`src/Media/Html.php:52`) and escape output.
2. **Ids derived from data, never hard-coded in a loop.** `id="btnradio<?= e($Y['year_label']) ?>"` scales to
   any number of years without colliding.
3. **Script the validator when it stops being free.** The R3 sweep was manual, correct at 42 pages, once. It
   stops being correct when more than one person edits templates, or content editors can create pages from the
   admin panel — then invalid markup can appear with no developer commit. At that point put the checker in CI:
   run it over every URL in `sitemap.xml` and fail the build on a non-zero error count. The trigger is *"a
   page can change without a developer looking at it"*, not a page count.

## 7. Gotchas and mistakes to avoid

**1. `alt` that repeats the filename, or starts with "image of".** The reader already said "image";
`alt="image of kggreen.jpg"` is announced as "image, image of kggreen dot jay peg". Describe the *content*.
Two real examples of the weak pattern survive here from the original site: `views/partials/card.php:32` ships
`alt="Card image cap"`, and `views/pages/co-curriculum.php:51` passes `'alt' => '...'`. Both pass the
validator — presence is all it checks — and tell a blind user nothing. Honest proof of §3's point that
validators check structure, not meaning.

**2. `aria-label` on something that already has a visible label.** It **replaces** the announced name. Put one
on a button that already reads "Send" and the reader stops saying "Send" — and a voice-control user who says
"click Send" now can't, because the accessible name no longer matches the visible text. Use it for icon-only
controls and for disambiguating repeated link text.

**3. ARIA decorating a plain `<div>`.** R3 deleted `aria-labelledby` from plain `<div>`s because it is inert
there — Bootstrap itself dropped the pattern (`docs/learn/07-stage-g.md:123-124`). ARIA on an element with no
role does nothing. **No ARIA beats wrong ARIA**, because wrong ARIA actively lies.

**4. Duplicate ids from a copy-pasted partial in a loop.** The single most common source. Write
`id="accordion"` inside a `foreach` and you get one per row. `WEBSITE_CONTEXT.md:394` records exactly this on
the original site: *"Accordions reuse `id="accordion"` per row (duplicate ids)."* Anchors and
`getElementById` then silently resolve to the first only. Derive the id from the row's own data.

**5. Div-soup instead of `<button>`.** `<div onclick="...">Send</div>` is not focusable, ignores `Enter` and
`Space`, and is announced as plain text. A `<button>` gives all three free. Faking it needs `role`,
`tabindex="0"` and keyboard handlers — more code than the `<button>` you avoided.

**6. Removing focus outlines because they look ugly.** `outline: none` on `:focus` makes a keyboard user's
cursor invisible; they are now navigating blind. If the default ring clashes, *restyle* it — never remove it.

**7. Assuming the automated checker caught everything.** Automated tools find roughly a third of real
accessibility problems. They cannot judge whether `alt="Card image cap"` is useful, whether a heading
describes its section, whether tab order matches visual order, or whether an error message is announced. A
green Lighthouse score is a floor, not a ceiling. **Tab through the page yourself.**

**8. Fixing invalid-but-shipped CSS "while you're in there".** Our navbar's `background: linear(…)` is
invalid, and *that is why the navbar is grey*. "Correcting" it to `linear-gradient()` silently redesigns the
site. `public_html/css/partials/navbar.css:13-26` preserves it as a comment: validator happy, render
identical, nobody fixes it by accident.

## 8. Try it yourself

**A. Validate a page (no install needed).** Run `./run.sh`, open <http://localhost:8090/>, view source
(`Ctrl+U`), copy all of it into the *Direct input* tab of <https://validator.w3.org/nu/> and press Check.
Expect **0 errors** — the stage doc's own instruction (`docs/learn/07-stage-g.md:172`). Then break it on
purpose: in `views/partials/footer.php`, temporarily add a second element carrying an `id` that already exists
on the page, reload, re-paste, watch "Duplicate ID" appear — **then undo it.**

> The Nu checker also ships as a standalone `vnu` jar you can run offline over many URLs at once — that is how
> you would wire it into CI (§6). This is a general property of the tool: **there is no `vnu` invocation
> recorded anywhere in this repo**; the documented workflow is the online checker.

**B. Run Lighthouse's accessibility audit.** Chrome → `F12` → **Lighthouse** tab → tick *Accessibility* only,
choose *Mobile*, click **Analyze page load**. Compare against `docs/perf-baseline.md:79-84` (home 96, the rest
100), then expand "Passed audits" too — that list *is* the checklist.

**C. Put the mouse down.** Open the home page, click once in the address bar, then press `Tab` only. Track
three things: can you always *see* where you are; does the order match the visual layout; can you reach every
control. Use `Enter` on a link and `Space` on a button; reach the footer's social icons and confirm the status
bar shows a real destination. Bonus: turn on your OS screen reader (Windows `Ctrl+Win+Enter`, macOS `Cmd+F5`)
and listen to the footer — you will hear "YouTube channel, link", which is `views/partials/footer.php:42`
doing its job.

## 9. Where to read more

**In this repo**

| Doc | Why |
|---|---|
| [`../07-stage-g.md`](../07-stage-g.md) | The primary source. §"R3 — the W3C validator: 368 errors → 0" is the whole story. |
| [`../08-stage-h.md`](../08-stage-h.md) | Part 2 explains SEO end-to-end and where the R3 markup work feeds it. |
| [`../../../CLAUDE.md`](../../../CLAUDE.md) | The visual-freeze rule and the theme tokens, including "never gold on white". |
| [`../../perf-baseline.md`](../../perf-baseline.md) | The F4 Lighthouse table with the A11y scores. |
| `../../ADMIN_UI_DESIGN.md` | Line 187 has the measured contrast ratios for every brand colour pair. |
| `../../PHASES.md` | Row 31 (R3) is the full acceptance record for this work. |

**Outside**

- **WCAG 2 Quick Reference** — <https://www.w3.org/WAI/WCAG22/quickref/> — the standard as an actionable,
  filterable checklist. Start at Level AA.
- **MDN Accessibility** — <https://developer.mozilla.org/en-US/docs/Web/Accessibility> — the best
  plain-English explanations of ARIA, focus and semantic HTML.
- **W3C Nu HTML Checker** — <https://validator.w3.org/nu/> — the validator itself.
- **WebAIM Contrast Checker** — <https://webaim.org/resources/contrastchecker/> — paste two hex colours, get
  the ratio and a pass/fail. Try `#ffd700` on `#ffffff` and watch it fail, then `#8a6d00`.
