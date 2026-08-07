# 4 — What We Built: Stages C & D (the public-site revamp + speed)

> Continues [`03-what-we-built.md`](03-what-we-built.md). Stages A & B made the site
> **secure** and **organized**. Stages C & D make the public pages **clean** and **fast**.
> Read docs #1–#3 first.

---

## Stage C — one clean, modern front-end

**The problem.** Every page was built from little template files, and *each template carried
its own complete web page* (`<!DOCTYPE>`, `<head>`, `<body>`…). When a page pulled in 11
templates, the browser received **11 nested mini-documents** — invalid HTML that browsers
only tolerate. Worse, the site loaded **four different versions of Bootstrap** (a popular
CSS/JS toolkit — 4.3.1, 4.5.3, 5.0.2, 5.3.3) plus **jQuery**, all from the internet (a
"CDN"). That's slow, fragile, and messy.

**The goal of Stage C:** every page = **one** valid HTML document, using **one** version of
Bootstrap (5.3.3), hosted by us (not a CDN), with shared colours in one place.

### What is Bootstrap, and what's a "dialect"?

**Bootstrap** is a ready-made kit of styles and interactive widgets (navigation bars,
image carousels, dropdown menus, collapsible panels). You add special classes and
attributes to your HTML and Bootstrap makes them work.

Between Bootstrap **4** and **5**, the attribute names changed. A carousel that auto-plays
was written `data-ride="carousel"` in v4; in v5 it's `data-bs-ride="carousel"` (they added
`bs-`). Same idea, different spelling — we call this the **dialect**. Our whole site spoke
the **v4 dialect**, so moving to v5 meant **translating every one of those attributes**:

```html
<!-- Bootstrap 4 (old) -->
<div data-ride="carousel" data-interval="2000"> … </div>
<a data-toggle="collapse" data-target="#panel"> … </a>

<!-- Bootstrap 5 (new) -->
<div data-bs-ride="carousel" data-bs-interval="2000"> … </div>
<a data-bs-toggle="collapse" data-bs-target="#panel"> … </a>
```

We also renamed a few CSS classes that Bootstrap changed (e.g. `text-left` → `text-start`),
and dropped **jQuery** entirely — Bootstrap 5 doesn't need it.

### Phase R1a — the foundation + the 8 main pages

1. **Self-hosted Bootstrap 5.3.3** — we downloaded Bootstrap once into
   `public_html/assets/vendor/bootstrap-5.3.3/`. Now it loads from *our* server, one
   version, no internet CDN.
2. **`css/tokens.css`** — one file that defines the brand colours and fonts as **variables**
   (`--sj-navy`, `--sj-gold`…). Change a colour in one place, it changes everywhere.
3. **Clean "partials"** (`views/partials/`) — we rebuilt the shared pieces (navbar, footer,
   preloader, scroll-to-top button, admissions banner) as **fragments**: just the piece,
   with **no** `<!DOCTYPE>`/`<head>`/`<body>` wrapper. They also switched to the v5 dialect.
4. **The shell** (`views/shell.php`) — the single outer document. It loads Bootstrap, Font
   Awesome, the fonts, the tokens, and the page's own CSS **once**, then drops in: navbar →
   the page's content → footer. This is what makes each page **one** clean document.
5. **The 8 "hub" pages** (about, staffs, academics, achievements, co-curriculum, sports,
   infrastructure, gallery) were converted: each `.php` file became a tiny **controller**
   (like we did for Home in Stage B) that hands its content to the shell. The content moved
   into `views/pages/<page>.php`, translated to the v5 dialect.

**Bugs fixed along the way** (from the known-issues list):
- The navbar's two dropdown links pointed at a page that doesn't exist (`curriculum.php`) —
  fixed.
- Several pages had **duplicate** `</body>` tags — gone, because each page is now one
  document.
- **Sports** reused the same `id="accordion"` for all 9 expandable cards (HTML ids must be
  unique) — each now has its own id.
- **Infrastructure** had an "off-by-one" bug: its quick-jump menu buttons each jumped to the
  *wrong* facility, and the last facility was unreachable. Fixed — button *N* now jumps to
  facility *N*. Its 15 image carousels also got unique ids.

**How we verified it:** for every converted page we checked (in a real browser) that there's
exactly **one** document, only our self-hosted Bootstrap loads (no CDN, no jQuery), and the
interactive bits actually work — the mobile menu opens, dropdowns drop, carousels slide,
the sports accordions expand — with **zero errors** in the browser console.

### Phase R1b — the academy + section pages (22 pages)

With the recipe proven on the 8 hub pages, we applied it to the two remaining
"template-shaped" families.

**The 18 academy pages** (Tamil, Maths, Science, … plus Band, NCC, Art-and-Expo)
were *identical* to each other apart from four things: the hero image, the title,
the three carousel photos, and the paragraph text. In the old code the same ~180
lines of CSS were **copy-pasted into all 18 files**. We extracted that shared block
**once** into `css/academy.css` (+ a shared `js/academy.js` for the scroll
animation) and let each page keep only its one unique line — the background image.
That's a real optimisation (the browser downloads and caches the shared file once
instead of re-reading it inside 18 pages) and, because the CSS is byte-for-byte the
same, **nothing looks different**. We also removed `academics.css` from these pages
after checking it styled nothing they actually use.

**The 4 section pages** (KG, Primary, High School, Higher-Secondary) are richer —
each has *two* carousels, a collapsible "timeline" accordion, and the admissions
banner. Those needed a couple more dialect translations (the accordion's
`data-toggle`/`data-target`/`data-parent` → `data-bs-*`), but the principle was the
same: keep every word, date, and photo exactly as it was, only modernise the
plumbing.

### Phase R1c — the gallery pages (11 pages)

The `gal-*` gallery pages were the easiest: they were *already* Bootstrap 5 with a
hand-written photo "lightbox" (click a photo → it opens big). So there was no
dialect to translate at all — we just moved each page into the shell and deleted
the duplicate Bootstrap/Popper downloads it no longer needed. We deliberately
**kept the quirks** the freeze rule protects — e.g. one gallery page (`gal-sciexpo`)
reuses another's CSS class names; we left that exactly as shipped.

**How we proved fidelity on all 33 pages.** For every page we compared the new
version against the original (git commit `6bd0d11`) three ways: (1) the **visible
text** must be identical; (2) the counts of `<br>`, `<span>`, `<p>`, carousel
slides, and images must match (so nothing was dropped); (3) a live browser check
that carousels slide, the accordion expands, and the lightbox opens — with **zero**
errors in the browser console. One page (`sportsacademy`) had a stray invisible
character a converter had trimmed; we restored it so the page is byte-faithful.

### Phase R1d — the home page (the last one)

The home page was saved for last because it is the **busiest**: a rotating photo
banner, the motto cards, animated counters, a news ticker, a second "New Updates"
carousel, the toppers marquee, testimonials, and a working contact form — each one
a separate old template with its own nested mini-document.

We turned all eight of those section-templates into clean **fragments** (like the
navbar in R1a), with one new idea: they are **parametrized** — the fragment no
longer fetches its own data from the database; the page's controller fetches
everything and *hands it in*. That keeps all database work in one visible place
(and let us trim the home page from 15 queries down to exactly our 12-query budget).

**The detective story.** The old home page loaded Bootstrap **three times**, and
the *last* copy (inside the footer template) silently won every styling "tie" on
the page — it's why the testimonial cards had 4px corners even though the page's
own CSS asked for 10px. Bootstrap 5 doesn't have those old rules, so simply
converting would have subtly changed corners, paddings and button sizes. The fix:
a small file, `css/home-bs4-remnants.css`, containing **exactly the old Bootstrap-4
fragments that final copy contributed**, loaded in **the same last position**. Same
cascade, same pixels — verified by measuring 16 computed styles in a real browser
against the original and getting identical numbers on every one.

With home converted (and `highsec`'s two borrowed sections switched to the same
clean fragments), **all 42 pages** are now single-document Bootstrap 5, and jQuery
is gone from the entire site.

---

## Stage D — make it fast, make it deployable

### Phase F1 — delivery basics (speed)

Remember the F0 baseline: the live home page was **10 MB and took ~14 seconds**. Two quick,
high-impact fixes:

1. **Stop re-downloading unchanged files.** The old code tagged every stylesheet with
   `?v=<current time>`, so the URL was different on *every* request — the browser could
   never reuse its cached copy. We replaced it with **one version number**, `SJ_ASSET_VER`,
   that we bump only when we deploy. Now the browser caches CSS/JS/images for a year and
   re-fetches only after a real change.
2. **Compress and cache** (in `public_html/.htaccess`):
   - **gzip** shrinks text as it's sent. Real numbers we measured: the Bootstrap CSS went
     from **227 KB to 31 KB** (86% smaller); a page's HTML from 17.6 KB to 5.9 KB.
   - **Cache-Control** tells browsers to keep assets for a year (they're versioned, so this
     is safe) but never cache the HTML (so content edits show up immediately).
3. **OPcache on** — PHP compiles your code every request unless OPcache is enabled to
   remember the compiled version. We turned it on (in the Dockerfile for dev; mPanel has it
   in prod).

### Phase X1 — deploy safely

1. **`admin/health.php`** — a "is everything OK?" endpoint. Visit it (with a secret token)
   and it returns a checklist: PHP version, required extensions, database reachable, tables
   present, uploads folder writable, OPcache on, HTTPS. Green across the board = safe to
   serve. (This check immediately earned its keep — it caught that a permissions change had
   broken the uploads folder in dev, which we then fixed.)
2. **`DEPLOY.md`** — the step-by-step runbook for putting the site on MilesWeb: what to set
   up once (PHP 8.3, database, secrets kept *above* the web folder, HTTPS, file
   permissions), how to ship each release (bump the version, upload only the right folders,
   apply database migrations, run the health check), what to **never** upload (the database
   folder, `.git`, secrets, planning docs), and how to roll back.

---

## Where the project stands

| Stage | Status |
|---|---|
| A — Security (S1–S4, F0) | ✅ done |
| B — Platform (P1–P4) | ✅ done |
| **C — Front-end revamp** | **✅ COMPLETE — R1a (8 hub pages + foundation) + R1b (22 academy/section) + R1c (11 gallery) + R1d (home) = all 42 pages on single-document Bootstrap 5; zero CDN Bootstrap/jQuery site-wide** |
| **D — Speed & deploy** | **F1 ✅, X1 ✅** |
| E–H (content in the DB, live editing, images, SEO, backups) | upcoming — see [`PHASES.md`](../../PHASES.md) |

### Try it yourself
1. `./run.sh`, open `http://localhost:8090/about.php` — view source: **one** `<!doctype>`,
   Bootstrap loading from `/assets/vendor/...` (not the internet).
2. Open `http://localhost:8090/sports.php`, click a "Read More" — the accordion expands
   (that's Bootstrap 5 working after the dialect translation).
3. Open `http://localhost:8090/index.php` (the home page, converted last in R1d) —
   view source: **one** `<!doctype>` for the whole busy page, self-hosted Bootstrap,
   no jQuery anywhere. Press the → arrow key: the hero banner advances (that's the
   keyboard feature, rewritten from jQuery to plain Bootstrap 5).
