# 8 — What We Built: Stage H (fast, findable, and safe to run)

> Continues [`07-stage-g.md`](07-stage-g.md). The site is built; Stage H is
> about **operating** it well: images that load fast (F2), a site Google can
> find and present properly (F3 — SEO), proof of the speed gains (F4), and the
> unglamorous safety nets every real site needs — backups (X2) and monitoring
> (X3).
>
> This chapter is deliberately more of a **textbook** than the earlier ones —
> each topic starts with how the thing works in general, then shows what we
> did in this repo.

---

## Part 1 — Images: where almost all the weight was

### The problem, in numbers

When we measured the original live site (F0), the Home page transferred
**10.3 MB, of which 9.8 MB was images** — 95%. Nothing else we could ever
optimise would matter until the images were fixed. This is true of most
websites: images are nearly always the heaviest thing on a page.

Why were they so heavy? Three separate mistakes, all common:

1. **Full-size originals in small slots.** The hero banner displayed a photo
   at ~1265 px wide, but the file was a 2.7 MB camera original thousands of
   pixels wide. The browser downloaded everything and threw most of it away.
2. **The wrong format.** Photographs saved as **PNG** (the testimonial
   backgrounds were ~920 KB each). PNG is *lossless* — perfect for logos,
   screenshots and flat-colour graphics, terrible for photos. A photographic
   PNG is typically 5–15× bigger than a visually identical JPEG.
3. **One file for every screen.** A phone on mobile data got the same bytes
   as a desktop on fibre.

### The fix: renditions

A **rendition** is a pre-generated, resized, recompressed copy of an image
for a particular slot. Our pipeline (built in Stage E for uploads) stores the
original once, then generates per-slot copies:

```
media/340/original.jpg      ← untouched original (the source of truth)
media/340/hero_16x7.jpg     ← sized for the hero slot, progressive JPEG
media/340/hero_16x7.webp    ← same, in WebP (~25–35% smaller again)
```

**F2 backfilled this for all 478 legacy photos** — `database/backfill.php`
walks every place the site actually uses an image (the same foreign-key map
the views use), and generates exactly the (image, preset) pairs the pages
request: 494 rendition sets. It runs **in Docker only** — image crunching on
a cheap shared host would starve the real visitors — and the output ships as
ordinary files with the release.

### JPEG vs WebP vs PNG (30-second version)

| Format | What it's for | Why |
|---|---|---|
| **JPEG** | Photographs | Lossy compression tuned for natural images; universally supported. "Progressive" JPEGs render blurry-to-sharp instead of top-to-bottom. |
| **WebP** | Photographs, modern browsers | Same quality at ~25–35% fewer bytes than JPEG. Every current browser supports it. |
| **PNG** | Logos, icons, screenshots, transparency | Lossless — pixel-perfect, but photographic content balloons. |

The `<picture>` element lets a page serve both safely — the browser picks the
first source it supports (old browsers ignore the `<source>` and use the JPEG):

```html
<picture>
  <source type="image/webp" srcset="/media/999/card_4x3.webp?v=1">
  <img src="/media/999/card_4x3.jpg?v=1" alt="…" loading="lazy">
</picture>
```

New uploads render exactly like that. The **legacy** photos, however, render
as a plain `<img>` pointing at the JPEG rendition — because of the best
debugging lesson of this stage:

> **The `<picture>` wrapper changed the page layout.** Several shipped
> sections are flex rows (`display: flex`) whose CSS styles the *img*. Wrap
> the img in `<picture>` and suddenly the flex item is the unstyled wrapper,
> not the img — and rows that used to fit started wrapping. Our element-level
> layout verifier caught rows jumping hundreds of pixels; we chased image
> dimensions, aspect ratios and `width/height` attributes for hours before
> isolating the wrapper itself as the only real culprit. Moral: *measure,
> don't reason* — layout bugs rarely live where the theory says.

(The `.webp` files are still generated and stored — future redesigned slots
can use them; the shipped slots get the JPEG, which is still 3–10× smaller
than the originals they replace.)

### Lazy loading

`loading="lazy"` on an `<img>` tells the browser: *don't download this until
the user scrolls near it.* A visitor who only reads the top of the Home page
never pays for the 245 gallery photos further down. We had this since Stage
E; F2 makes each lazy image far smaller when it does load.

### The lesson we hit: crop changes layout

Our presets normally **cover-crop** (e.g. `hero_16x7` cuts the photo to a
16:7 rectangle — what you want for *new* uploads, where the admin sees a crop
tool). The first backfill did that to the legacy photos too — and the
verifier immediately showed pages changing shape. Why? The shipped CSS sizes
many slots from the photo's **own aspect ratio** (width 100%, height auto):
crop the photo and the *page layout* changes with it.

So legacy backfill uses **fit mode**: downscale only, never crop. Same
framing, same layout, same look — only the bytes shrink. And when renditions
regenerate, the image's `version` bumps so the URL changes (`?v=3`) and every
browser refetches — the same cache-busting idea as our CSS `?v=` parameter
(a lesson this stage taught us *three* times: a stylesheet edited after its
version bump is a stylesheet nobody downloads).

### The static stragglers

Ten design assets referenced straight from CSS (testimonial backgrounds,
section backgrounds, the assembly photo…) never pass through `img_tag()`, so
the backfill also writes optimised copies into `media/static/` and the
stylesheets point there. The three 920 KB testimonial PNGs became **78 KB
JPEGs** — they sit under a 70% navy gradient, so the recompression is
invisible.

**Result: Home's image payload went from 9,834 KB to ~1,050 KB (−89%), the
whole page from 10.3 MB to under 2 MB.**

---

## Part 2 — SEO: how search actually works (the deep dive)

SEO — *search engine optimisation* — is everything you do so that when
someone searches "school in Ondipudur" or "St Joseph's Coimbatore
admissions", **your page appears, looks right, and gets the click**. To
influence it you first need to know what a search engine actually does.

### 2.1 The pipeline: discover → crawl → render → index → rank → serve

**1. Discovery.** Google finds URLs three ways: links from pages it already
knows, sitemaps you submit, and URLs it has seen before. A brand-new page
that nothing links to and no sitemap lists is invisible.

**2. Crawling.** *Googlebot* (an automated fetcher) downloads your pages —
politely, at a rate your server can handle, and only where allowed. Before it
fetches anything, it reads **`/robots.txt`**:

```
User-agent: *
Disallow: /admin/
Disallow: /api/

Sitemap: https://stjosephsondipudur.com/sitemap.xml
```

`Disallow` is a *request not to crawl* — good manners for pages that would
waste crawl budget or that shouldn't appear (our admin login). Note it is
**not security**: a disallowed URL is still reachable by anyone who types it
— our admin is protected by the login + session work of Stage A, not by
robots.txt.

**3. Rendering.** Modern Googlebot runs a real Chromium browser, executes
your JavaScript and sees the page roughly as users do. Sites that only work
after heavy JS get indexed later and less reliably — our pages are
server-rendered PHP, so what Googlebot fetches *is* the content. That's a
genuine SEO advantage of this architecture.

**4. Indexing.** The page's text, headings, images (via `alt` text), and
metadata get stored in Google's index — think of it as the world's largest
inverted phone book: for every word, the list of pages containing it. At this
step Google also picks the page's **canonical** — see §2.4.

**5. Ranking.** When someone searches, Google scores candidate pages with
hundreds of signals. The ones that matter most, in plain words:

- **Relevance** — do the query words (and their synonyms) appear in the
  places that matter: the `<title>`, the URL, headings (`h1`–`h2`), early
  body text, image alt text? This is why heading *structure* (fixed in R3)
  isn't just accessibility — it tells the engine what the page is about.
- **Authority** — how many *other* sites link to yours, and how reputable
  they are. This is the famous **PageRank** idea: every link is a vote, and
  votes from trusted pages weigh more. It's the signal you can least fake
  and the one that most separates page one from page five.
- **User experience** — mobile-friendliness, HTTPS, and the **Core Web
  Vitals**: LCP (how fast the main content appears), CLS (does the layout
  jump around), INP (does it respond to taps). Stage H's image work directly
  moves LCP; the R3 markup work feeds the a11y/BP side.
- **Freshness & consistency** — pages that are maintained, don't 404, don't
  contradict themselves (one canonical URL per piece of content).
- **E-E-A-T** (Experience, Expertise, Authoritativeness, Trust) — Google's
  quality-rater rubric. For a school: real address and phone number that
  match everywhere, a working contact form, named people, HTTPS.

**6. Serving.** The result page (the *SERP*) shows for each hit a **title
link** (from your `<title>`, sometimes rewritten), a **snippet** (usually
your `meta description`, sometimes text Google picks itself), and the URL.
Two pages with equal rank can have wildly different click-through — the one
with the compelling, accurate title/description wins the click.

### 2.2 What we control directly (on-page SEO) — and what we shipped

**Title** — the single most important on-page element. Rules of thumb:
~60 characters visible, front-load the distinctive words, one per page,
every page different:

```html
<title>Kindergarten | St.Joseph's MHSS, Ondipudur</title>
```

**Meta description** — doesn't affect *rank*, strongly affects *clicks*.
~155 characters visible; write it like the one-sentence ad for that page:

```html
<meta name="description" content="The Kindergarten of St.Joseph's MHSS,
Ondipudur — a joyful, safe start to school life with play-based learning…">
```

F3 added a `seo_meta` table with **one row per public URL** (41 rows, seeded
with hand-written titles/descriptions), a new **SEO screen in the admin
panel** to edit them, and the shell renders them on every page. Nothing is
hard-coded — the school can rewrite its own snippets forever.

**Canonical** — tells Google which URL is *the* address for this content, so
`http://` vs `https://`, `?utm=...` junk parameters, or a future `www.`
variant don't split your page into competing duplicates:

```html
<link rel="canonical" href="https://stjosephsondipudur.com/kg.php">
```

**Open Graph (OG) tags** — not for Google, for *sharing*: when a parent
pastes the link into WhatsApp or Facebook, the preview card's title, text and
image come from `og:title` / `og:description` / `og:image`. A school's links
get shared in parent groups constantly — this is disproportionately valuable
here.

**Sitemap** — `sitemap.xml` lists all 41 public URLs so nothing depends on
Google stumbling across links. Referenced from robots.txt and submitted in
Search Console (below).

**The quiet on-page work already done in earlier stages** that SEO textbooks
list as prerequisites: valid HTML (0 errors, R3), correct heading order (R3),
alt attributes (R3), one `<h1>`-equivalent topic per page, crawlable `<a>`
links everywhere, fast pages (F1/F2), mobile-responsive layout, and unique
IDs. Lighthouse's SEO audit now scores **100** on the pages we ran.

### 2.3 What you influence *off* the page (the part no code can do)

This is the "how to influence through the web" part — for a local school
it's arguably *more* important than anything in the HTML:

1. **Google Business Profile** (free, do this first): claim the school on
   Google Maps, set the exact name/address/phone, hours, photos. Local
   searches ("schools near me", "matric school ondipudur") are served mostly
   from this, not from web pages. Keep the phone/address IDENTICAL to the
   website footer — consistency is a trust signal (called *NAP consistency*).
2. **Backlinks you can legitimately get**: the diocese/R.C. Mission site,
   school-directory sites (JustDial, Sulekha, school-listing portals), local
   news covering school events, alumni pages, the education department's
   listings. A handful of real, relevant links beats hundreds of junk ones.
3. **Reviews** — parents' Google reviews on the Business Profile feed local
   rank and, more importantly, human trust.
4. **Content that earns searches**: the site already has pages people search
   for (admissions info in the jumbotron, academies, results). Over time,
   posting each year's toppers, admission dates and event photos gives Google
   fresh, query-matching content — and the CMS makes that a 2-minute job.
5. **What NOT to do**: keyword stuffing ("best school best school best…"),
   buying links, copying other sites' text, hidden text, doorway pages.
   Google's spam systems catch these and the penalty is worse than the gain.

### 2.4 Measuring it: Google Search Console (post-launch checklist)

Search Console is Google's free dashboard for *your* site's search presence.
After the site goes live:

1. Verify the domain (DNS record or an HTML file upload — mPanel can do
   either).
2. Submit `sitemap.xml` → watch **Coverage**: 41 submitted, 41 indexed is
   the goal; it lists exactly which pages aren't and why.
3. **Performance report**: which queries showed the site, position, clicks.
   This is where you learn what parents actually type.
4. **Page experience / Core Web Vitals**: field data from real Chrome users —
   the ultimate check on the F2/F4 work.
5. `site:stjosephsondipudur.com` in Google is the quick sanity check that
   indexing happened at all.

Expect *weeks*, not hours — crawling and ranking are gradual. SEO is a
gardening job, not a deploy.

---

## Part 3 — Performance: proving it (F4)

### How a page actually loads

`DNS lookup → TCP connect → TLS handshake → HTML download → parse → download
CSS/JS/fonts/images → layout → paint`. Each stage adds latency; optimisation
is mostly *sending fewer, smaller things* and *not blocking the paint*.
Everything this project did maps onto that:

| Lever | Where we did it |
|---|---|
| Fewer files | R2 merged 34 CSS files → 15; one Bootstrap instead of three |
| Smaller files | gzip (F1), image renditions + WebP (F2) |
| Don't re-download | 1-year immutable cache + `?v=` versioning (F1) |
| Don't block | `defer` on admin js, lazy images, fonts preconnect |
| Less server work | OPcache, ≤12 SQL queries/page, one batched renditions query |

### The metrics Lighthouse scores

- **LCP** (Largest Contentful Paint) — seconds until the biggest visible
  thing (our hero image) is painted. *Good ≤ 2.5 s.* Image work moves this.
- **CLS** (Cumulative Layout Shift) — how much the page jumps while loading.
  *Good ≤ 0.1.* Reserved image space and stable fonts move this.
- **TBT** (Total Blocking Time) — how long JS hogs the main thread. Our JS
  is tiny, so this was never a problem.

### The numbers

Home page, same measurement method as the F0 baseline (browser Performance
API, cold load):

| Metric | F0 (live original) | Now (Stage H) |
|---|---|---|
| Total transferred | **10,344 KB** | **1,906 KB (−82%)** |
| … of which images | 9,834 KB | 1,108 KB (−89%) |
| Largest single image | 2,732 KB (kggreen.jpg) | 270 KB (hero rendition) |
| Requests | 57 | 47 |
| SQL queries | 16 | 12 (within budget) |

Mobile Lighthouse (throttled 4G simulation, dev box): academy pages score
**Perf 96 / A11y 100 / BP 96 / SEO 100**; the image-heavy pages (home,
infrastructure with its 15 full-screen backgrounds, albums with their photo
grids) score 64–72 on throttled-mobile Performance with 96–100 on everything
else. The remaining levers are documented in `docs/perf-baseline.md`
(responsive `srcset` per viewport, tile-size renditions for album grids,
deferring the icon font) — plus production realities dev can't show:
HTTP/2 on the real host and the optional Cloudflare front. The two SEO-92
pages lose points only for the literal words "Read more"/"Learn more" on two
buttons — content the school can reword in Site Settings any time.

*(Cloudflare — putting a free CDN/proxy in front of the site — remains a
deploy-time option documented in DEPLOY.md; it needs a DNS change the school
makes when going live, and mainly buys TLS/HTTP-2/edge caching wins on top of
what's here.)*

---

## Part 4 — Backups (X2): the feature you hope never runs

### The theory in one paragraph

The classic rule is **3-2-1**: three copies of your data, on two different
kinds of storage, one of them somewhere else. And the rule that actually
matters in practice: **a backup you have never restored is a hope, not a
backup.** Backups must be automatic (humans forget), retained over time (you
often discover corruption days later — last night's backup of a broken
database is worthless), and stored where the web server cannot serve them
(a `backup.sql` inside the webroot is a public download of your entire
database — a classic breach).

### What this site has

Two kinds of state exist: the **database** (all content, tiny) and
**`media/` uploads** (photos, large, changes rarely). Hence:

- `database/backup.sh` — nightly gzipped `mysqldump` + weekly `media/`
  archive into `~/backups/` (**above the webroot**), 14-day retention,
  credentials read from `config/config.php` so nothing secret sits in the
  crontab. One mPanel cron line runs it (DEPLOY.md §7).
- **Restore drill, actually performed** (2026-08-09, in Docker): dump →
  restore into a scratch database → row counts compared — `images 478/478,
  seo_meta 41/41, image_renditions 988/988` — then the scratch dropped.
- The **admin dashboard** now shows a health strip with the last backup's
  age and size. Status only, on purpose: per SECURITY.md SEC-20 there is
  **no download-a-backup endpoint** — a leaked admin session must not be
  able to exfiltrate the whole database in one click.
- The photos in `photos/` and the code are already off-machine (git,
  releases) — the DB dump + media archive complete the set.

---

## Part 5 — Monitoring (X3): knowing before the parents do

A site that's down teaches you nothing until someone complains. Monitoring
is three cheap layers:

1. **An outside robot checking you're up.** UptimeRobot (free) fetches two
   URLs every 5 minutes: the homepage (keyword check — "St.Joseph" must
   appear, catching both downtime *and* a blank/error page) and
   `/admin/health.php?token=…`. Down → e-mail within minutes.
2. **A health endpoint that knows what "healthy" means.** Ours (X1, extended
   now) returns JSON checks — PHP version, extensions, DB reachable, schema
   present, media writable, **disk free (gates below 200 MB)**, image count,
   sitemap present, last-backup age — and answers **HTTP 503 when a gating
   check fails**, which is exactly what monitoring robots understand. We
   drilled it: stopped the database container → 503; started it → 200.
3. **Eyes on the inside.** The dashboard health strip (disk/images/backup
   age) makes the daily glance effortless, and PHP's `error_log` (mPanel →
   Error Log) is where runtime warnings surface.

---

## Where the project stands

| Stage | Status |
|---|---|
| A — Security · B — Platform · C — Front-end · D — Speed & deploy · E — Content · F — Overlay · G — Consolidation/validation | ✅ done |
| **H — F2 renditions · F3 SEO · F4 audit · X2 backups · X3 monitoring** | **✅ COMPLETE** |

The roadmap's built phases are done. What remains is *going live*: the
DEPLOY.md runbook, DNS/Search Console/UptimeRobot/Business Profile setup on
launch day, and the gardening (content, reviews, links) that no codebase can
do for you.

### Try it yourself
1. View source on any page — every page now has its own `<title>`, meta
   description, canonical and OG tags. Change one in **Admin → SEO** and
   refresh.
2. `curl -s localhost:8090/robots.txt` and `/sitemap.xml`.
3. `docker compose exec -T web php /var/www/database/backfill.php` — watch
   it skip everything (idempotent).
4. Open the admin dashboard — the health strip shows disk, images and the
   last backup. Stop the db container and hit the health URL: 503.
5. Paste a page URL into PageSpeed Insights after go-live — compare with
   `docs/perf-baseline.md`.
