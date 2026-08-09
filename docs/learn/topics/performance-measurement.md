# Measuring Web Performance

> **What you'll learn:** what "slow" means in numbers, how a browser loads a page step by step,
> what each famous metric (TTFB, LCP, CLS, TBT/INP) really measures, and the four instruments
> this repo uses — a SQL query counter we built ourselves, the browser Performance API, `curl`,
> and Lighthouse. By the end you can reproduce every number in `docs/perf-baseline.md`.
>
> **Prerequisites:** you can read `echo`, variables, `if`, functions and `require` in PHP, and you
> have run `./run.sh` once and seen the site at `http://localhost:8090/` (`run.sh:50-51`). No
> JavaScript knowledge is assumed.
>
> **Where it lives in our code:**
> - `docs/perf-baseline.md` — the measurement record; primary source for this document
> - `src/Core/Db.php:13-31` — `CountingStatement` / `CountingPdo`, our query counter
> - `src/helpers.php:33-37` — `db_query_count()`, which reads the counter
> - `public_html/bootstrap.php:29-38` — prints `<!-- sj-queries: N -->` when debug is on
> - `CLAUDE.md:69` — the ≤ 12 SQL queries per page budget
> - `public_html/.htaccess:25-56` — the gzip/caching rules whose effect we measure
> - `src/Media/Html.php:45-99` — the image markup that moves LCP and CLS

---

## 1. The one-paragraph version

Performance work has one rule: **measure before you optimise, and measure the same way every
time.** Without a "before" number you cannot prove a change helped; change the method between runs
and you are comparing two different things while calling it progress. So this project captured a
baseline first — `docs/perf-baseline.md` — and wrote the exact commands used into an **"Appendix —
how measured (keep identical on re-runs)"** (`docs/perf-baseline.md:49-60`), so every later run is
comparable. We track what a visitor feels: how long the server takes to answer (TTFB), how long
until the main content is visible (LCP), whether the page jumps while loading (CLS), how many
kilobytes and files it pulls down, and — server side — how many SQL queries one page view costs.
Those numbers took the home page from **10,344 KB to 1,906 KB** and from **16 SQL queries to 12**
(`docs/perf-baseline.md:68-74`).

---

## 2. The problem this solves

Here is the trap. You look at a slow site, you have a hunch ("the database must be slow"), you
spend two days tuning queries, you deploy, and it feels the same. You cannot tell whether you
helped, hurt, or did nothing, because you never wrote down what it was like before.

Not hypothetical here. When this site was first measured the obvious suspects were wrong
(`docs/perf-baseline.md:35-40`): **images were 90–97% of every page's weight** (one hero photo was
2.7 MB), and **the server was not the bottleneck at all** — TTFB was a healthy 0.1–0.2 s. Follow
the hunch and you optimise the one part that was already fine.

The other half is **comparability**. Six months from now somebody re-runs the numbers. With a
different tool, network, or a warm browser cache, their "1,200 KB" and our "1,906 KB" mean nothing
side by side. The appendix is what makes the numbers *numbers* instead of anecdotes.

---

## 3. How it works in general

### 3.1 What a browser does when you press Enter

Every page load is the same sequence, and each step can only start after the one before it.

| # | Step | What happens |
|---|---|---|
| 1 | **DNS → TCP → TLS** | Turn the domain into an IP, open a connection, agree encryption keys. Two to four network round-trips before a single byte of page is requested. |
| 2 | **Request → first byte** | Browser sends `GET /`; server runs PHP, queries MySQL, builds HTML. **This is TTFB.** |
| 3 | **HTML download** | The HTML arrives, usually gzipped (our home HTML is ~58 KB raw, `docs/perf-baseline.md:13`). |
| 4 | **Discover sub-resources** | Parsing finds `<link>`, `<script>`, `<img>`. Each is a *new* request — back to step 1. |
| 5 | **Render-blocking CSS** | The browser refuses to paint until stylesheets arrive. |
| 6 | **Images** | Usually the heaviest part — 95% of our F0 home page. |
| 7 | **JavaScript** | Runs on the *main thread*, the single lane that also does layout and painting. |

**Render-blocking, in plain words.** A stylesheet is render-blocking. The browser has your page
text in memory and *could* draw it, but deliberately shows a blank white screen until the CSS
finishes downloading — painting first and styling second would flash ugly unstyled text, then jump.
It picks "blank" over "flash". The consequence: **a slow stylesheet delays the whole page, even the
parts that do not use it.** That is why "defer the icon font and Google Fonts CSS" is still on our
list (`docs/perf-baseline.md:100-101`).

### 3.2 The metrics, one at a time

| Metric | Plain-English definition | What makes it bad | Good is |
|---|---|---|---|
| **TTFB** (Time To First Byte) | Time from sending the request to the first byte coming back — network plus your server | Slow query, OPcache off (PHP recompiling every request), distant server | under ~0.5 s; ours was 0.11–0.21 s live (`docs/perf-baseline.md:11-17`) |
| **LCP** (Largest Contentful Paint) | Seconds until the *biggest visible thing* is painted — our hero photo. The honest answer to "does this look ready to a human yet?" | A huge hero image, render-blocking CSS in front of it, slow server | **≤ 2.5 s** (`docs/learn/08-stage-h.md:307-308`); our academy page 2.6 s, infrastructure 17.1 s |
| **CLS** (Cumulative Layout Shift) | A score, not a time: how much content *moves* after it was first drawn | Images with no reserved space; late-loading fonts | **≤ 0.1**; we measured 0.001–0.048 (`docs/perf-baseline.md:81-84`) |
| **TBT / INP** | Whether the page *responds*. TBT = milliseconds the main thread was blocked during load (lab); INP = you tap, how long until the screen reacts (real users) | Heavy frameworks, big loops, many third-party tags | our JS is tiny, so this was never our problem |
| **Page weight** | Total kilobytes transferred | Unoptimised images, uncompressed text | home F0: **10,344 KB** (`docs/perf-baseline.md:13`) |
| **Request count** | How many separate files were fetched; each costs at least one round-trip | Many small CSS/JS/font files | home F0: **57** |

**Why CLS deserves its own paragraph.** Picture it: you open a page on your phone, see a button,
reach for it, and as your thumb lands an image above finishes loading, pushes everything down, and
you tap an advert instead. Why images? An `<img>` with no declared size occupies **zero height**
until its bytes arrive; the browser lays out around a hole of nothing, then the photo turns up,
claims 400 px, and shoves everything below it down. The fix is to **reserve the space in advance** —
exactly what `src/Media/Html.php:92-94` does, reading `width`/`height` from the rendition record
into the tag.

**And TBT/INP.** JavaScript runs on the **main thread**, the same single lane the browser uses to
handle taps and redraw the screen. While a script runs, nothing else in that lane happens — your
click is queued, the page is frozen (`docs/learn/08-stage-h.md:180-183`, `:311-313`).

### 3.3 Lab data vs field data — always say which you have

| | **Lab data** | **Field data** |
|---|---|---|
| Source | A tool you run on demand | Real visitors' browsers, reported back |
| Conditions | Simulated: a pretend slow phone on a pretend 4G link | Whatever the visitor actually has |
| Repeatable? | Yes — the whole point | No, it is a distribution |
| Good for | Comparing before/after a change | Knowing what users truly experience |
| Examples | Lighthouse, WebPageTest | Chrome UX Report, Search Console Core Web Vitals |

**Everything in `docs/perf-baseline.md` §F4 is lab data**, and the doc says so: *"throttled 4G
simulation, headless Chromium, dev box — no HTTP/2, no CDN"* (`docs/perf-baseline.md:76-77`). That
line is an admission with teeth. **Dev box:** a Docker container on a laptop, not MilesWeb shared
hosting. **No HTTP/2:** the protocol letting one connection carry many files at once — production
(LiteSpeed) has it, our dev box does not, so our 47-request count hurts the lab score more than it
will hurt real visitors (`:102-105`). **No CDN:** a content delivery network serves files from a
machine near the visitor; Cloudflare is a deploy-day option dev cannot show (`:106-107`). So our
scores are a **fair before/after of our own work** and a **pessimistic estimate of the real
thing**. Both halves must survive into any report you write.

---

## 4. How we use it — every place in this codebase

### 4.1 Instrument one: the SQL query counter (ours, hand-built)

The only instrument we wrote ourselves. It exists because a page running one query is fast, while a
page running one query *per row in a loop* — the **N+1 problem** — gets slower as content grows,
and you never notice on a dev database with ten rows.

PDO lets you substitute your own statement class; ours adds one line before doing the normal thing
(`src/Core/Db.php:13-20`):

```php
class CountingStatement extends PDOStatement {
    public function execute(?array $params = null): bool {
        $GLOBALS['__sj_qcount'] = ($GLOBALS['__sj_qcount'] ?? 0) + 1;
        return parent::execute($params);
    }
}
```

`CountingPdo` (`src/Core/Db.php:22-31`) does the same for `->query()`, which bypasses prepared
statements. **Both are wired in only when the config flag `debug` is true** (`:54-61`) — production
gets a plain `PDO`, no overhead, no leaked internals. `db_query_count()` (`src/helpers.php:33-37`)
returns `$GLOBALS['__sj_qcount'] ?? 0`, and `public_html/bootstrap.php:29-38` registers a shutdown
function that echoes `"\n<!-- sj-queries: " . db_query_count() . " -->"` at the end of the
response — after checking `headers_list()` for `content-type: application/json`, so it can never
corrupt an API reply.

**The budget.** `CLAUDE.md:69` makes it a rule: *each converted page ≤ 12 SQL queries*. A budget
turns a vague wish into a pass/fail check anyone can run. The baseline caught home at **16 — over
budget before any other page was even dynamic** (`docs/perf-baseline.md:30`, `:40`). Two fixes
brought it to 12: `settings` is fetched once into a `static` variable (`src/Content/Repo.php:24-31`),
and all image renditions load in **one** query instead of one per image
(`src/Media/Pipeline.php:42-48`).

### 4.2 Instrument two: the browser Performance API (weight and request count)

Browsers record timing and size for every file fetched and expose it to JavaScript. The appendix
records the method (`docs/perf-baseline.md:55-56`): sum `transferSize` over
`performance.getEntriesByType('resource')`, and count the entries. `transferSize` is bytes actually
sent over the network — so it accounts for gzip, and it reads `0` for anything served from cache.
That last part is a trap; see §7.

### 4.3 Instrument three: `curl -w` for TTFB

`curl` is a command-line HTTP client; `-w` (write-out) prints named timing variables after the
request. The committed command (`docs/perf-baseline.md:52-53`):

```bash
curl -s -o /dev/null -w '%{http_code} %{time_starttransfer} %{size_download}' \
  --max-time 30 "https://stjosephsondipudur.com/<page>"
```

`-s` silences the progress bar; `-o /dev/null` throws the body away because we only want timings;
`%{time_starttransfer}` **is** TTFB; `%{size_download}` is the HTML size; `%{http_code}` catches the
case where you have been carefully measuring a 404 — which happened, `/mathsacademy.php` was a 404
on the live host (`:18`). These are a **median of four runs** (`:9`), because one network
measurement is noise.

### 4.4 Instrument four: Lighthouse

Lighthouse is an auditing tool built into Chrome DevTools and runnable from a terminal. It loads
your page in a controlled, throttled environment and scores four categories out of 100:
**Performance, Accessibility, Best Practices, SEO**. It simulates a mid-range phone on slow 4G —
harsher than your laptop, so problems surface. The F4 run used a **headless Chromium** (Chrome with
no visible window) with **throttled 4G simulation** on the dev box (`docs/perf-baseline.md:76-77`):

```bash
npx lighthouse http://localhost:8090/ --form-factor=mobile \
  --chrome-flags="--headless --no-sandbox" --output=html --output-path=./lh-home.html
```

> **Honesty note:** the exact Lighthouse command is **not** in the appendix — only a prose
> description of the settings — and this repo ships no `package.json`, so the line above is the
> standard recipe rather than something recorded in git. By the golden rule of §1 it *should* sit in
> the appendix next to the `curl` line. Treat that as a real gap.

### 4.5 A tool that is *not* a performance tool

`public_html/admin/health.php` is a JSON smoke-check — PHP version, extensions, DB reachable, schema
present, media writable, disk free, sitemap, last backup age — returning **HTTP 503 when a gating
check fails** (`public_html/admin/health.php:63-71`), which is what an uptime robot understands. It
tells you the site is *alive*, not that it is *fast*. Keep the ideas separate.

### 4.6 The results: F0 → F4

Home page, cold load, same Performance-API method both times (`docs/perf-baseline.md:66-74`):

| Metric | F0 (live) | F4 (now) | Target | Verdict |
|---|---|---|---|---|
| Total transferred | 10,344 KB | **1,906 KB** | < 3 MB | ✅ (−82%) |
| Image payload | 9,834 KB | **1,108 KB** | ↓ ≥ 70% | ✅ (−89%) |
| Requests | 57 | 47 | < 25 | ❌ (see §4.7) |
| SQL queries (home) | 16 | **12** | ≤ 12 | ✅ |
| Largest image | 2,732 KB | 270 KB | no original > 300 KB | ✅ |

Mobile Lighthouse — throttled 4G simulation, headless Chromium, dev box, no HTTP/2, no CDN
(`docs/perf-baseline.md:76-84`):

| Page | Perf | A11y | Best-Pr. | SEO | LCP | CLS |
|---|---|---|---|---|---|---|
| academy (tamil) | **96** | 100 | 96 | **100** | 2.6 s | 0.019 |
| album (gal-annual) | 72 | 100 | 96 | **100** | 9.1 s | 0.004 |
| home | 64 | 96 | 96 | 92 | 11.3 s | 0.048 |
| infrastructure | 65 | 100 | 96 | 92 | 17.1 s | 0.001 |

*(One inconsistency left visible on purpose: the F0 top-assets list records `kggreen.jpg` at
2,669 KB (`:22`) while the F4 row records the F0 largest image as 2,732 KB (`:74`). Two captures,
two numbers — the exact drift the "same way every time" rule prevents.)*

### 4.7 Honest reporting: what we missed, written down

Look again at the requests row: **47 against a target of under 25 — a red ❌ left in the table**.
Three pages score 64–72 on throttled-mobile Performance against a target of 80 (`:81-84`). Nobody
made us print that. Leaving the failure in is worth more than hiding it:

1. **It is a to-do list.** A miss with a reason tells the next maintainer what to do next; a missing
   row tells them nothing.
2. **It defends the trade-off.** The count is high because CSS is deliberately split into 11
   per-partial files for cacheability and HTML validity (a phase R3 decision), and HTTP/2 on the
   real host makes the count largely moot — *"merging would trade maintainability for a metric"*
   (`:102-105`). That reads as a considered engineering choice only because the number sits next
   to it.
3. **It makes the ✅ rows believable.** A report where everything passed is a report nobody checks.

The **"stragglers" list**, in the doc's own impact order (`docs/perf-baseline.md:95-107`):

1. **Responsive `srcset`** — serve ~768 px variants to phones; renditions are single-size today.
2. **Album grids** — use the existing `gallery_tile` preset in the grid, keep `gallery_full` for the
   lightbox.
3. **Defer the icon font and Google Fonts CSS** — a render-blocking chain; needs a decision about
   tolerating a flash of unstyled text, which touches the visual-freeze rule.
4. **Request count (47)** — per-partial CSS and font files; the trade-off above.
5. **Production realities dev cannot show** — LiteSpeed HTTP/2, Brotli, optional Cloudflare.

One deliberate exception is recorded at `:109-112`: legacy photos render as a plain `<img>` with no
`<picture>` wrapper and no `width`/`height`, because measurement showed both changing shipped
layouts (`src/Media/Html.php:70-77`); new uploads get the full treatment (`:87-99`). The cost is
that those legacy images give up the CLS protection from §3.2 — a trade made with eyes open, and
written down.

---

## 5. Why this is the right approach here

Four instruments, all free, all runnable from a terminal. Why not something better? Because "better"
has to survive this project's actual constraints.

| Alternative | What it gives you | Why not here |
|---|---|---|
| **Paid RUM** (Real User Monitoring — SpeedCurve, Datadog, New Relic) | Continuous field data from every visitor; regression alerts | A monthly bill against a **school budget**, plus a JavaScript tag on every page — more weight on the pages we are lightening |
| **WebPageTest** | Excellent lab data from real devices in real cities; filmstrips, waterfalls | Needs a **publicly reachable URL**; our dev box is `localhost:8090`. Genuinely useful *after* go-live, and still on the list (`PHASES.md:71`) |
| **Synthetic monitoring** (scheduled outside checks) | Catches regressions automatically over time | Infrastructure and budget. The cheap slice — *is the site up?* — we already have, via `admin/health.php` plus a free uptime robot |
| **No measurement at all** | Nothing | You cannot tell optimisation from superstition. This is the option that produces two days of database tuning on a site whose database was never slow |

Against those, our set wins on the constraint that actually decides it: **a maintainer must be able
to reproduce these numbers in five minutes, years from now, with no account, no subscription, and no
vendor still needing to be in business.** `curl` will exist. Chrome DevTools will exist. The query
counter is thirty lines of PHP inside this repo. That matters when the next person to touch this
site may arrive in 2031 with a handover document and no budget.

Shared hosting sharpens the point. On MilesWeb (mPanel, no SSH) you cannot install an agent, run a
profiler, or read server-side traces. Whatever you measure, you measure from outside with a browser
and an HTTP client — so building the habit on tools that work that way is not a compromise, it is a
match.

---

## 6. How this scales

Everything above is lab measurement of a site that is not busy yet. Live traffic changes it three
ways.

**1. Field data replaces guesswork.** Google collects Core Web Vitals (LCP, CLS, INP) from real
Chrome users and reports them in **Search Console → Page experience / Core Web Vitals**
(`docs/learn/08-stage-h.md:277-279`) — measured on the phones parents actually own, on the networks
they actually have, in Ondipudur. Expect disagreement with our lab numbers in both directions:
better, because production has HTTP/2 and Brotli; worse, because some visitors are on bad 3G.

**2. Percentiles, not averages.** One visitor's LCP is a fact; ten thousand visitors' LCP is a
distribution. The average hides the tail, and the tail is where people give up. The standard is the
**75th percentile** — "three out of four visitors got at least this good an experience".

**3. Sampling.** RUM tools record a fraction of page views (say 1 in 10) and extrapolate. Fine for
trends — remember it when a rare page shows a scary number based on four samples.

**When a CDN changes the picture.** Put Cloudflare in front and several numbers stop meaning what
they meant (`docs/perf-baseline.md:106-107`, `docs/learn/08-stage-h.md:338-341`): **TTFB drops** for
cached responses because the edge answers and your PHP never runs; **request count matters less**
because HTTP/2 multiplexes; **bytes drop again** because Brotli usually beats gzip; and **a cache
hit and a cache miss are different pages** — measure both, label which you got.

One scaling factor has nothing to do with traffic: **content growth**. The ≤ 12 query budget is
per-page precisely because a page running one query per gallery photo looks fine with 10 photos and
falls over at 500.

---

## 7. Gotchas and mistakes to avoid

**Warm load vs cold load.** A *cold* load is a first-time visitor: empty cache. A *warm* load reuses
cached files. Our `.htaccess` caches static assets for a year (`public_html/.htaccess:47-51`), and
`transferSize` reports `0` for cached files — so a warm reload reports our 1,900 KB home page as a
few dozen KB. `docs/perf-baseline.md:66` says **"cold load"** for exactly this reason. In DevTools
tick **Disable cache** on the Network tab (it applies only while DevTools is open), or use incognito.

**DevTools itself costs time.** Having it open — especially recording on Network or Performance —
measurably slows the page. Fine for finding *what* is slow; not for quoting a headline number.

**localhost has no network.** TTFB on `http://localhost:8090/` is essentially pure PHP time: zero
DNS, zero latency, unlimited bandwidth. It looks wonderful and means nothing about the real world —
*"TTFB is localhost (not comparable to prod)"* (`docs/perf-baseline.md:26`). Locally trust the
**query count**; measure TTFB against the live host.

**One Lighthouse run is not a measurement.** Scores wobble by several points between runs on an
unchanged page, because the throttling simulation and your machine's background load both vary. Run
three to five times and take the **median** — the `curl` numbers were already a median of four
(`:9`).

**Optimising something that is not the bottleneck.** Images were 95% of our page weight; shaving
2 KB off a stylesheet while a 2.7 MB photo loads is pure motion. Sort your problems by size first —
that is what the largest-assets list (`:22`) is for.

**Forgetting to turn `debug` back off.** The counter only exists when `debug` is true
(`src/Core/Db.php:54-61`), so you must switch it on to count — and that has costs: every response
gains an HTML comment exposing internals, and PDO does extra work. Config ships with
`'debug' => false` (`config/config.php:30`, `config/config.sample.php:33`). **Turn it back off in
the same sitting** — make it the last step of the task, not something for tomorrow.

**Changing the method and the code in the same run.** Switch tools and optimise at once and you
cannot attribute the difference to either. Change one thing at a time.

---

## 8. Try it yourself

### Exercise A — count the SQL queries on the home page

```bash
# 1. Start the stack (prints the URLs; see run.sh:50-51)
./run.sh

# 2. Open config/config.php in your EDITOR and change line 30 to 'debug' => true,
#    (Open the file and edit it. Do not script this change.)

# 3. Ask the page for its query count
curl -s http://localhost:8090/ | grep -o 'sj-queries: [0-9]*'

# 4. Put it back:  'debug' => false,   in config/config.php
```

Expected `sj-queries: 12`, matching `docs/perf-baseline.md:73`. Try other pages — every converted
page must stay at or under 12 (`CLAUDE.md:69`). **Step 4 is not optional.**

### Exercise B — measure TTFB with the committed command

```bash
curl -s -o /dev/null -w '%{http_code} %{time_starttransfer} %{size_download}' \
  --max-time 30 "https://stjosephsondipudur.com/"
```

That is the appendix one-liner (`docs/perf-baseline.md:52-53`). Run it four times, take the median,
then run it against `http://localhost:8090/`. The local number will be far smaller and completely
meaningless as a production estimate — that is the lesson, not a bug.

### Exercise C — page weight and request count in the browser

Open `http://localhost:8090/` in Chrome. DevTools (F12) → **Network** → tick **Disable cache** →
reload with `Ctrl+Shift+R` for a cold load. Then in the **Console** tab paste the appendix's method
(`docs/perf-baseline.md:55-56`):

```js
const r = performance.getEntriesByType('resource');
const kb = Math.round(r.reduce((sum, e) => sum + (e.transferSize || 0), 0) / 1024);
const imgs = r.filter(e => e.initiatorType === 'img');
const imgKb = Math.round(imgs.reduce((s, e) => s + (e.transferSize || 0), 0) / 1024);
console.log({ requests: r.length, totalKB: kb, images: imgs.length, imageKB: imgKb });
```

Compare against the F4 row — 1,906 KB total, 1,108 KB images, 47 requests (`:70-72`). Then reload
*without* disabling the cache and watch the totals collapse: the warm-vs-cold trap from §7, live.

### Exercise D — see CLS, then run Lighthouse

Find a `<picture>`-rendered image in the page source (`src/Media/Html.php:87-99`) and note its
`width`/`height`. Throttle the network to "Slow 3G" and reload: the space is reserved, nothing jumps.
Compare with a legacy `<img>` (`:79-85`), which by design carries no dimensions. Then open the
**Lighthouse** tab, choose **Mobile** + **Performance**, and analyse the home page three times. Note
how far the score moves between identical runs — that is why §7 says take the median. Compare with
the home row: Perf 64, LCP 11.3 s, CLS 0.048 (`docs/perf-baseline.md:83`), remembering your machine
is not the machine that produced those.

---

## 9. Where to read more

**In this repo:**

- [`../../perf-baseline.md`](../../perf-baseline.md) — **read this first.** The F0 baseline, the F4
  results, the targets, the straggler list, and the "how measured" appendix.
- [`../08-stage-h.md`](../08-stage-h.md) — Stage H. Part 1 is the image work behind the −89% image
  payload; **Part 3** is the performance proof this document expands on.
- [`../04-stage-c-and-d.md`](../04-stage-c-and-d.md) — Stage D, phase **F1**: killing `?v=time()`,
  gzip (Bootstrap CSS 227 KB → 31 KB), year-long caching and OPcache — the delivery basics whose
  effect the numbers here measure.
- [`../../../CLAUDE.md`](../../../CLAUDE.md) — the repo rules, including the ≤ 12 query budget at line 69.
- [`../../../PHASES.md`](../../../PHASES.md) — phase **F0** (`PHASES.md:71`) defines the baseline; phase
  **F4** (`PHASES.md:144`) records the audit and every fix shipped in it.
- [`how-php-serves-a-page.md`](how-php-serves-a-page.md) — the server half of §3.1, in detail.

**Outside:**

- [web.dev — Core Web Vitals](https://web.dev/articles/vitals) — Google's own definitions of LCP,
  CLS and INP, with the current "good" thresholds.
- [web.dev — Optimize Cumulative Layout Shift](https://web.dev/articles/optimize-cls) — the clearest
  explanation of why images need reserved space.
- [Lighthouse documentation](https://developer.chrome.com/docs/lighthouse/overview) — what each audit
  checks and how the Performance score is weighted.
- [MDN — Performance API](https://developer.mozilla.org/en-US/docs/Web/API/Performance_API) — the
  reference for `getEntriesByType('resource')` and `transferSize` used in Exercise C.
