# 7 — What We Built: Stage G (paying off the tech debt)

> Continues [`06-stage-f.md`](06-stage-f.md). Stages A–F built the CMS.
> **Stage G is the cleanup stage the plan promised all along**: merge the
> copy-pasted CSS, delete the last legacy directories, fix the long-deferred
> typos, and get every page through the W3C validator — all without moving a
> single pixel.
>
> Status: **✅ COMPLETE** — R2 (CSS consolidation + `_libs`/`_templates`
> removal) and R3 (validation + accessibility sweep).

---

## The safety net came first

Everything in this stage is the kind of change that *quietly* breaks a page.
So before touching anything, we built a measuring instrument:

- For **26 representative pages × 2 viewports** (desktop + mobile) we recorded,
  for **every element on every page**, its layout rectangle and its full
  computed style (plus `::before`/`::after` pseudo-elements) — with animations
  frozen, carousels pinned to slide 1, and counters forced to their final
  values so the snapshot is deterministic.
- That 52-capture baseline was stored, the work was done, and the capture was
  re-run. **Anything that moved by even a tenth of a pixel shows up.**

This caught real mistakes during the stage (a regex that ate half the gallery
page, a heading pinned 0.24px too large) that eyeballing would have missed.

## R2 — one CSS file per family, not per page

The original site cloned whole stylesheets with find-and-replace. The proof is
in the files: all four school-section sheets contain `position: kgsolute` /
`highschlsolute` — someone replaced a word *inside* the word `absolute`. The
browser has silently ignored that invalid line since day one.

What the consolidation did (public CSS: **34 files → 15, 170,671 → 98,495
bytes, −42.3%**):

- **`sections.css`** replaces highschl/highsec/primary/kg.css (4 × ~13.8 KB).
  The four files were diffed line-by-line after normalising the cloned name;
  the *real* differences — each page's hero photo, kg's extra mobile rule, one
  font line kg alone lacked — are a handful of rules scoped with the body
  classes the pages already had. The cloned hero-carousel rules for three of
  the pages matched nothing at all (their wrapper is `.abt-carousel`) and were
  dropped as dead code.
- **`album.css`** replaces the ten `gal-*.css` clones. The shared album
  template now adds one canonical `album-container` class beside the per-album
  one; the three real per-album differences (annual's padding + font line,
  independence's `#333` heading) sit at the end, scoped by the original
  class names.
- **`home.css`** is the four home-only sheets (index/carousel/card/contact)
  concatenated in their original link order — same cascade, three fewer
  requests. `home-bs4-remnants.css` deliberately stays a separate file because
  it must remain the *last* stylesheet in Home's body.
- **Bug 13 closed:** `navbar.css`, `new-updates.css`, `testimonial.css` were
  requested by *no* page — deleted.

### One `reveal()` instead of twenty-three

The scroll-reveal effect existed as **23 copies**: 16 inline `<script>` blocks
and 7 js files, each differing only in selector list, threshold (50–150 px)
and whether the first check runs before scrolling. They are now one
implementation — `sjReveal()` in `js/site.js` (plus `sjBlurCards()` for the
three hover-blur grids) — and every page passes in *its own shipped
parameters*, so the behavior is unchanged per page. The harness plus a
listener-level test (move an element into view → class appears; move it out →
class removed) proved the machinery.

### `_libs/` and `_templates/` are gone

The last two legacy directories were deleted:

- `_templates/` had **zero callers** left after R1d — removed outright.
- `_libs/` (9 files) was ported to proper classes: `SJ\Admin\Auth` + `Csrf`,
  `SJ\View\EditAttrs`, `SJ\Media\Pipeline` + `Html`, `SJ\Content\Repo`, and
  the dev query-counter into `SJ\Core\Db`. The historic function names every
  view calls (`repo_*()`, `img_tag()`, `ed_*()`, `e()`, `db()`…) live on in
  `src/helpers.php`, loaded by Composer's `files` autoload, delegating to the
  classes — so **not one view or admin endpoint changed its calls**.
  `public_html/bootstrap.php` replaced `_libs/load.php`; all fifty
  `require` lines were flipped; the seeder found its new bootstrap.

Regression after the port: all 41 public pages 200 and hash-identical, admin
login → field-save → upload → guarded-delete all green, health endpoint OK,
seeder idempotent twice.

## R3 — the W3C validator: 368 errors → 0

Every one of the 42 documents now validates clean (the Nu checker also
enforces unique `id`s and `alt` attributes, so those acceptance items are
proven by the same run). The fixes, and why none of them moved pixels:

- **Body `<style>` blocks (156 errors).** A `<style>` inside `<body>` is
  invalid HTML — but a `<link rel="stylesheet">` in the body is *valid*. So
  each partial's verbatim styles moved to `/css/partials/<name>.css`, linked
  **at the exact same spot** in the document: same rules, same cascade
  position, byte-identical rendering, and the chrome styles are now cacheable
  across pages instead of re-sent inline. The two *dynamic* blocks (academy /
  facility backgrounds built from the DB) render as a head `<style>` via the
  layout's new `$sjHeadCss` hook — also a valid position.
- **The famous grey navbar (41 errors).** The original ships
  `background: linear(…)` — an invalid function the browser ignores, which is
  *why* the navbar is grey. That quirk is sacred (CLAUDE.md names it). It is
  now preserved as a **comment** in navbar.css with the full story: the
  render is exactly the same, the validator stops counting it, and nobody can
  "fix" it into a navy gradient by accident.
- **Heading order (32 errors).** Skipped levels (h1 → h4) are real
  accessibility errors, but heading tags carry default sizes — so every
  re-level pins the *old* tag's exact size. Example: the footer's four
  `<h4>` became `<h2>` with Bootstrap's h4 sizing formula written into
  footer.css **including the ≥1200px cap** (`1.5rem`) — the harness caught the
  first attempt being 0.24px tall and the cap fixed it to rect-identical.
- **Buttons and labels inside links (25 errors).** `<a><button>` is invalid;
  the inner elements became `<span class="btn …">`. Bootstrap's `.btn` styling
  is entirely class-based and its reboot gives buttons inherited fonts, so
  the computed styles match — the harness shows identical rectangles and the
  only differing property is the non-rendering `appearance`.
- **The unclosed div (2 errors).** Home's update carousel never closed its
  wrapper — the browser has been auto-closing it at `</section>` forever. The
  explicit `</div>` was added **exactly where the parser recovered**, so the
  DOM is unchanged.
- **Assorted:** `aria-labelledby` removed from plain `<div>`s (inert for
  assistive tech there — Bootstrap itself dropped the pattern),
  `autocomplete` off the album year radios, the `tel:` link normalised to a
  diallable `tel:04222271367`, and the diary note's invalid `<span><p>`
  unwrapped (the gold color moved onto the `<p>`; same computed color).

### The deferred typos finally died (bug 14)

Visible-text fixes were frozen until R3. Now: **Higher** Secondary (hero ×3),
**Annual** Day, Sports **Achievements**, **Creativeness**, and the photo file
`carosel1.jpg` → `carousel1.jpg`. Fixed in the committed seed-data *and*
applied to existing databases by new idempotent fix-ups in the seeder
(re-running reports `0 rows corrected`). Two suspects — `Inaguration`,
`Infrastrucutre` — turned out already fixed. Bonus find: the achievements
page's background referenced `schname.JPG`, which never existed — the photo
layer had silently 404'd since the original ship. That's bug 9 for real
(everyone had only checked the views); one lowercase extension later the
photo renders under its gradient.

### Paths (bug 15)

All 12 `../photos/…` and bare `photos/…` URLs in CSS became site-absolute
`/photos/…` — the browser resolves both to the same file today, but only the
absolute form survives files moving around (and it was mandatory anyway when
the inline styles moved into `/css/partials/`, where `../photos/` would have
pointed at the wrong place).

---

## A first: the owner edited the site while we worked

Mid-verification, the harness flagged home's news ticker as "changed". The
audit log told the story: a real login, the forced password change, a
settings save, **edit mode on, and a ticker reorder through the Stage-F
overlay chips** — the site owner was using the CMS live, minutes after it was
built. The "regression" was their edit. (Admin edits win; nothing was
reverted, and the test credentials were never touched again.)

## Where the project stands

| Stage | Status |
|---|---|
| A — Security · B — Platform · C — Front-end · D — Speed & deploy · E — Content · F — Live-edit overlay | ✅ done |
| **G — Revamp completion (R2 consolidation + R3 validation/a11y)** | **✅ COMPLETE** |
| H — Performance, SEO, ops (image backfill, sitemap, backups, monitoring) | next |

### Try it yourself
1. `./run.sh` → open any section page and view source: one `sections.css`
   serves all four; the page still looks exactly the same.
2. Paste any page into <https://validator.w3.org/nu/> — 0 errors.
3. Open `public_html/css/partials/navbar.css` and read the preserved
   `linear(…)` story — the most instructive three lines in the repo.
4. `php database/seed.php` twice — the R3 fix-ups report `0 rows corrected`
   the second time. Idempotency is a habit now.
