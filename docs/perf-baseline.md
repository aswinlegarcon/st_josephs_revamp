# Performance Baseline (F0)

**Captured:** 2026-08-02, at commit after S4. **Live host:** MilesWeb shared (mPanel), https://stjosephsondipudur.com/. **MilesWeb health score at capture: 48% (user-reported).**

This is the "before" snapshot. Re-run the same measurements after **F1** (cache/gzip/kill `?v=time()`), **F2** (WebP rendition backfill + lazy loading) and **F4** (audit) to prove the gains. Commands are in the appendix so numbers stay comparable.

## Live site (production — the real baseline)

Full-page metrics captured via the browser Performance API (cold-ish loads; sub-resource totals include images/CSS/JS). TTFB and HTML size from `curl` (median of 4).

| Page | HTTP | TTFB | HTML KB | **Total KB** | Requests | Images | Image KB | Full load |
|---|---|---|---|---|---|---|---|---|
| `/` (home) | 200 | 0.11 s | 58 | **10,344** | 57 | 25 | 9,834 (95%) | **14.4 s** |
| `/infrastructure.php` | 200 | 0.20 s | 44 | **18,380** | 71 | 50 | 17,888 (97%) | 2.9 s* |
| `/gal-annual.php` | 200 | 0.11 s | 22 | **5,529** | 50 | 30 | 4,997 (90%) | 1.4 s* |
| `/about.php` | 200 | 0.21 s | 22 | — | — | — | — | — |
| `/gallery.php` | 200 | 0.13 s | 20 | — | — | — | — | — |
| `/mathsacademy.php` | **404** | — | — | — | — | — | — | — |

\* Full-load times vary with connection warmth; **total page weight is the stable, decisive metric**. `/mathsacademy.php` 404s on the live host (older deploy); the dynamic rebuild restores it (C5). `about`/`gallery` captured by curl only (TTFB + HTML); browser totals to be filled on the F1 re-run.

Largest single assets on the home page (KB): `kggreen.jpg` 2,669 · `testimonial3.png` 940 · `testimonial1.png` 924 · `testimonial2.png` 897 · `kgwelcome.jpg` 706 · `asemb1.jpg` 622 · `carosel1.jpg` 595 · `german.jpg` 479 · `kg-boys.jpg` 306 · `sports.jpeg` 272.

## Local Docker (dev — for relative F1/F2 comparison)

Same commit, `./run.sh`. TTFB is localhost (not comparable to prod); **SQL query count** is the useful dev metric (needs `config['debug'] = true`; the count is emitted as an HTML comment `<!-- sj-queries: N -->`).

| Page | SQL queries | Notes |
|---|---|---|
| `/` (home) | **16** | Dynamic. **Over the ≤12 budget** — needs `settings`/`pages` cached per request + pre-joined images (address during the repo work). |
| `/about.php`, `/infrastructure.php`, `/gallery.php`, `/gal-annual.php` | 0 | Still static HTML (not yet converted); will incur queries as they move to the DB in C-phases — must each stay ≤12. |

## Key findings (what makes it "48% / slow")

1. **Images are the whole problem — 90–97% of every page's weight.** No WebP, no lazy-loading, no responsive/retina sizing; multi-MB originals served as-is (a single 2.7 MB hero image on the home page). → **F2** (rendition backfill to WebP+JPEG, `loading="lazy"`, width/height) is the highest-impact fix.
2. **Cold home load ≈ 14 s.** Directly caused by (1) plus request count.
3. **`?v=<timestamp>` on every stylesheet** (`index.css?v=1785613550`, `carousel.css`, `card.css`, `contact.css`, `footer.css` — all the same epoch) **defeats browser caching**: every visit re-downloads all CSS. → **F1** replaces it with a per-deploy `SJ_ASSET_VER`.
4. **Server is not the bottleneck.** TTFB 0.1–0.2 s is healthy; the fix is payload + caching, not backend speed.
5. **High request counts** (50–71/page) from many separate images + 8 CSS + 13 JS. → one self-hosted Bootstrap (R1) + fewer, cached assets.
6. **Home already exceeds the SQL budget** (16 > 12) before any other page is even dynamic.

## Targets (verified at F4)

- Mobile Lighthouse: **Perf ≥ 80, SEO ≥ 95, Best-Practices ≥ 90, A11y ≥ 90** on home, infrastructure, an album, an academy.
- **Home: < 3 MB total, < 25 requests** (from 10.3 MB / 57).
- **Every converted page ≤ 12 SQL queries.**
- Expected F2 impact: home image payload 9.8 MB → **< 1.5 MB** (>70% cut); full load 14 s → **< 3 s**.

## Appendix — how measured (keep identical on re-runs)

```bash
# TTFB (median of 4) + HTML size, per page:
curl -s -o /dev/null -w '%{http_code} %{time_starttransfer} %{size_download}' --max-time 30 "https://stjosephsondipudur.com/<page>"

# Full page weight + request count (browser devtools / Performance API):
#   performance.getEntriesByType('resource') → sum transferSize; count entries.

# Local SQL query count (needs config['debug']=true):
curl -s "http://localhost:8090/<page>" | grep -oE 'sj-queries: [0-9]+'
```

---

# F4 results (2026-08-09, after F1 delivery + F2 renditions + F3 SEO)

Local Docker, same Performance-API method as F0. Home page, cold load:

| Metric | F0 (live) | F4 (now) | Target | Verdict |
|---|---|---|---|---|
| Total transferred | 10,344 KB | **1,906 KB** | < 3 MB | ✅ (−82%) |
| Image payload | 9,834 KB | **1,108 KB** | ↓ ≥ 70% | ✅ (−89%) |
| Requests | 57 | 47 | < 25 | ❌ (see below) |
| SQL queries (home) | 16 | **12** | ≤ 12 | ✅ |
| Largest image | 2,732 KB | 270 KB | no original > 300 KB | ✅ |

Mobile Lighthouse (throttled 4G simulation, headless Chromium, dev box —
no HTTP/2, no CDN):

| Page | Perf | A11y | Best-Pr. | SEO | LCP | CLS |
|---|---|---|---|---|---|---|
| academy (tamil) | **96** | 100 | 96 | **100** | 2.6 s | 0.019 |
| album (gal-annual) | 72 | 100 | 96 | **100** | 9.1 s | 0.004 |
| home | 64 | 96 | 96 | 92 | 11.3 s | 0.048 |
| infrastructure | 65 | 100 | 96 | 92 | 17.1 s | 0.001 |

**Read of the results.** A11y/Best-Practices ≥ 96 everywhere ✅. SEO 100 on
albums/academies; the two 92s lose points ONLY for the literal words
"Read more"/"Learn more" on two buttons — reword them in Site Settings for
100 (content decision, not code). CLS ≤ 0.05 everywhere ✅ (the
"zero image-driven CLS" goal in practice). Throttled-mobile Performance
passes on academies; the three image-heavy pages (home hero stack, infra's
15 full-screen CSS backgrounds, album photo grids) sit at 64–72 under
simulated slow 4G.

**Remaining levers (the "stragglers" list), in impact order:**
1. **Responsive `srcset`** — serve ~768 px variants to phones (renditions are
   single-size today; the pipeline can grow a second width per preset).
2. **Album grids** — serve the existing `gallery_tile` preset in the grid and
   keep `gallery_full` for the lightbox (markup + lightbox change).
3. **Defer the icon font + Google Fonts CSS** (render-blocking chain; needs a
   FOUC-tolerance decision — visual-freeze relevant).
4. **Request count** (47): the biggest groups are per-partial CSS (11 files,
   deliberately split for cacheability/validity in R3) and font files. HTTP/2
   on the real host makes the count largely moot; merging would trade
   maintainability for a metric.
5. **Production realities** dev can't show: LiteSpeed HTTP/2, Brotli, and the
   optional **Cloudflare** front (DEPLOY.md) — all lift LCP directly.

One deliberate exception ships in the codebase: legacy photos render as plain
`<img>` (no `<picture>`/WebP, no width/height attributes) because the
verifier measured both changing shipped layouts (flex-item swap; attribute
height pinning). New uploads get the full `<picture>` + dimensions treatment.
