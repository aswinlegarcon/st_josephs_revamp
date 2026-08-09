# SEO — Search Engine Optimisation

> **What you'll learn:** what a search engine does with our pages, and then — the real point of this
> document — **every line of this codebase that exists because of search engines**: the tags in the
> page `<head>`, the table behind them, the admin screen that edits them, the two plain files at the
> webroot, and the one config value that makes it correct in production.
>
> **Prerequisites:** you can read variables, `if`, functions and `include` in PHP, and you have
> skimmed [How PHP Serves a Web Page](how-php-serves-a-page.md) so you know what "the shell" is. You
> need to know nothing about search engines — every term is explained the first time it appears.
>
> **Where it lives in our code:**
> - `views/shell.php` lines 12–41 — the only place SEO tags are printed
> - `src/helpers.php:208` → `src/Content/Repo.php:34` — the one query that fetches them
> - `database/migrations/007.sql` + `database/schema.sql:58` — the `seo_meta` table
> - `database/seed.php:529` — the 41 hand-written rows
> - `src/Content/Registry.php:186` — what the admin may change
> - `public_html/admin/section.php:652`, `public_html/admin/_layout.php:39` — the SEO screen
> - `public_html/sitemap.xml`, `public_html/robots.txt` — the two crawler files
> - `config/config.sample.php:28` — `base_url`
>
> The **deep conceptual chapter** is [`../08-stage-h.md`](../08-stage-h.md) Part 2. This is the
> *engineering* view; a compact version of the theory is below so you can read this alone.

---

## 1. The one-paragraph version

**SEO** stands for *search engine optimisation*: everything you do so that when a parent types
"school in Ondipudur" into Google, our page appears, looks right in the results, and gets the click.
A search engine is a robot that downloads pages, reads them, and files them away. It cannot see
design — it reads **HTML tags**. Four matter most: `<title>` (the blue clickable line in results),
`<meta name="description">` (the grey sentence under it), `<link rel="canonical">` ("this URL is the
real address of this content"), and the **Open Graph** block (what WhatsApp and Facebook show when
someone pastes the link). In this project all four are printed in exactly one file —
`views/shell.php` — and the words inside them live in a database table, `seo_meta`, that the school's
own staff edit from the admin panel. Nothing is hard-coded into any page.

---

## 2. The problem this solves

The old site had one `<title>` copied into all 42 pages. Three separate costs:

1. **Every result looks identical.** If the Annual Day gallery and the Kindergarten page both say
   "St.Joseph's MHSS", a parent searching "kg admission ondipudur" has no reason to click. The title
   is the strongest on-page signal about what a page is *about*.
2. **Google writes your snippet for you.** With no description tag it grabs an arbitrary sentence off
   the page. Sometimes it picks the navigation menu.
3. **Only a developer could fix it.** The words that sell the school were buried inside PHP files.

A fourth problem is specific to *our* architecture: we have 42 URLs but only **six** page templates —
all 18 academy pages render `views/pages/academy.php`, all 10 gallery albums render
`views/pages/album.php` (whitelist at `src/View/Layout.php:17-28`). So "put the tags in the template"
would give all 18 academies the same title again. The metadata must be keyed to the **URL**, not the
template.

---

## 3. How it works in general

The compact pipeline; the full one, with worked examples, is in
[`../08-stage-h.md`](../08-stage-h.md) §2.1.

| Step | What happens | What we influence |
|---|---|---|
| **Discover** | The engine learns a URL exists — from a link on a page it knows, or from a sitemap you gave it. | `sitemap.xml`, internal links |
| **Crawl** | *Googlebot* (the automated downloader) fetches the page. First it reads `/robots.txt` to see where it may go. | `robots.txt`, server speed |
| **Render** | It runs the page in a real Chromium browser. Our pages are server-rendered PHP, so what it fetches **is** the content — a genuine advantage. | architecture |
| **Index** | Text, headings, image `alt` text and metadata get filed; the page's canonical is chosen here. | headings, alt text, `canonical` |
| **Rank** | Pages are scored: relevance (do the words appear in title/headings/body?), authority (who links to you), experience (speed, mobile, HTTPS). | title, content, speed |
| **Serve** | The results page shows a **title link**, a **snippet**, and the URL. | `<title>`, `description` |

Two consequences drive everything below. **The engine reads text, not pixels** — our visual-freeze
rule (`CLAUDE.md`) forbids changing how a page *looks*, but almost all SEO work lives in the `<head>`,
which renders nothing, so F3 shipped a full SEO pack without moving a pixel. And **ranking is mostly
not code** — titles and descriptions are table stakes; what decides whether a local school ranks is
off-page work (§4.10).

---

## 4. How we use it — every place in this codebase

### 4.1 One block, one file

Every public page is poured into the same HTML document, `views/shell.php`, so there is exactly one
place SEO tags are printed. The whole computation, verbatim from `views/shell.php:13-21`:

```php
// F3 SEO: per-URL title/description/canonical/OG. The slug comes from the
// entry script's own name (server-set, never request-derived); rows live in
// seo_meta (admin-editable). Fallbacks keep pages working with no row.
$sjSlug  = \basename($_SERVER['SCRIPT_NAME'] ?? 'index.php', '.php') ?: 'index';
$sjSeo   = repo_seo($sjSlug) ?: [];
$sjBase  = rtrim(sj_config()['base_url'] ?? 'https://stjosephsondipudur.com', '/');
$sjCanon = $sjBase . ($sjSlug === 'index' ? '/' : '/' . $sjSlug . '.php');
$sjTitle = ($sjSeo['title'] ?? '') !== '' ? $sjSeo['title'] : ($title ?? "St.Joseph's MHSS, Ondipudur");
$sjDesc  = $sjSeo['description'] ?? '';
```

Read it as: *which page am I → look up its row → what is this site's absolute address → build my own
canonical URL → pick a title, falling back if there is no row.*

### 4.2 The slug, and why it comes from the script name

A **slug** is a short id for a URL. Ours is the entry file's name without `.php`: `/about.php` →
`about`, `/gal-annual.php` → `gal-annual`, `/` → `index`. `$_SERVER['SCRIPT_NAME']` is set by **the
web server**, not the visitor: it is the path of the PHP file Apache actually decided to run, and a
visitor cannot type a URL that makes it say something else, because a request for a file that does
not exist 404s before any PHP runs. `basename()` then strips any directory part, so even an odd value
cannot contain a `/`.

Compare the tempting alternative, `$_GET['page']`. That **is** attacker-controlled, and in a codebase
full of `include` calls it is a loaded gun. `SECURITY.md` catalogues it as **SEC-11 (LFI/RFI)**, whose
mitigation reads *"resolve templates from a hardcoded map, never from request data"*
(`SECURITY.md:79`). Our layout engine already obeys that — page names are checked against a constant
array before any `include` (`src/View/Layout.php:16-28`). The SEO slug follows the same discipline
for the same reason: **a request-derived string must never end up near a template name, a filesystem
path, or a redirect target.** Here the slug only reaches a PDO placeholder, but keeping the rule
absolute is what makes it enforceable — hence the comment on `views/shell.php:13-14`, placed where
the next person to edit the file cannot miss it.

### 4.3 `repo_seo()` — the lookup

`repo_seo()` is a thin free function so views never type a class name (`src/helpers.php:208-211`).
It forwards to `src/Content/Repo.php:34-40`:

```php
/** SEO row (title/description) for one URL slug — F3. */
public static function seo(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM seo_meta WHERE slug = ?');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}
```

- **`prepare` + `?`** — the slug goes in as a PDO **placeholder**, never glued into SQL. Project rule
  (`CLAUDE.md`: "values only via PDO placeholders"); it makes injection structurally impossible here.
- **`?: null`** — a page with no row is not an error; the shell falls back (§6).
- **One query per page view.** Our budget is ≤ 12 per page, and `src/Content/Repo.php:289-291` records
  that the home page's features query was rewritten as a JOIN *"now that F2 renditions and F3 seo each
  cost a query per request"*. SEO was not free; the budget was rebalanced to pay for it.

### 4.4 The tags, one at a time

From `views/shell.php:28-40` (the `og:description` guard, `og:url` and `og:image` follow the same
shape and are covered in the table):

```php
<title><?= e($sjTitle) ?></title>
<?php if ($sjDesc !== ''): ?>
<meta name="description" content="<?= e($sjDesc) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= e($sjCanon) ?>">
<meta property="og:site_name" content="St.Joseph's MHSS, Ondipudur">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($sjTitle) ?>">
```

| Tag | Who reads it | What it does | Limit |
|---|---|---|---|
| `<title>` | Search engines, browser tab | The clickable line in results; strongest on-page signal. | ~60 chars visible; column `VARCHAR(160)` |
| `meta description` | Search engines | The grey sentence under it. Does **not** affect rank; strongly affects clicks. | ~155 chars visible; column `VARCHAR(300)` |
| `link canonical` | Search engines | "The one true URL for this content" — stops `http`/`https`, `?utm=…` and a future `www.` splitting one page into rivals. | must be **absolute** |
| `og:site_name`, `og:type` | WhatsApp, Facebook, Slack | Fixed strings: the school's name; "this is a website". | — |
| `og:title`, `og:description` | Chat apps | The preview card's headline and body — the same two values, so the school edits one thing. | — |
| `og:url`, `og:image` | Chat apps | Where the card points (`$sjCanon`) and its thumbnail (`$sjBase . '/photos/logo-main.png'`). | image must be absolute |

**Open Graph** is a small tag standard Facebook invented that every chat app now uses. For a school
it is disproportionately valuable — links get pasted into parent WhatsApp groups constantly, and a
card with a title, a sentence and the crest reads as legitimate where a bare URL does not.

Two details worth copying. **Everything goes through `e()`** — our `htmlspecialchars(ENT_QUOTES,
UTF-8)` wrapper (`src/helpers.php:39-43`); these values come from a database an admin types into, and
a `"` in a title would otherwise break out of `content="…"` and let script in. **An empty description
prints no tag at all** — the `if ($sjDesc !== '')` guards matter, because an empty tag is worse than
none: it asserts that the page's description is nothing. (Line 41, `<link rel="icon">`, is the
favicon — not SEO, but it ends the block.)

### 4.5 Where the text lives: `seo_meta`

`database/migrations/007.sql`, mirrored into `database/schema.sql:58-67`:

```sql
CREATE TABLE IF NOT EXISTS seo_meta (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(64)  NOT NULL,
  title       VARCHAR(160) NOT NULL DEFAULT '',
  description VARCHAR(300) NOT NULL DEFAULT '',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_seo_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

The migration's own comment explains the shape (`database/migrations/007.sql:1-4`): keyed by slug
*"because the 42 public URLs span several entities (pages, academies, gallery_albums) — one flat
table covers them all."* Columns on three tables would have meant three code paths in the shell and
three admin screens. `UNIQUE KEY uq_seo_slug` is load-bearing: the database itself guarantees one row
per URL, so `Repo::seo()` can never get two answers.

The **41 seeded rows** are a literal array at `database/seed.php:529-571`, e.g. line 542:

```php
['kg', "Kindergarten | St.Joseph's MHSS, Ondipudur", "The Kindergarten of St.Joseph's MHSS, Ondipudur — a joyful, safe start to school life with play-based learning, celebrations and little milestones."],
```

Note the pattern — specific words first, school name after the pipe. Google truncates around 60
characters, so front-loading is not style; it decides what a parent actually sees. The insert
(`database/seed.php:572`) is `INSERT IGNORE`, which with the unique key makes the seeder
**idempotent** (running it twice changes nothing) and, more importantly, means **an admin's edits
always win** — re-seeding a live database never clobbers the school's words.

Why 41 rows for a "42-page" site? `public_html/` holds 43 `.php` files; `bootstrap.php` is not a page
and `gal-sciexpo.php` is a 6-line permanent redirect to `gal-spach.php`
(`public_html/gal-sciexpo.php:4-5`). That leaves 41 indexable URLs — the same 41 as the sitemap.

### 4.6 The admin screen, and why rows are editable but not creatable

The nav entry is `'seo' => ['🔍', 'SEO'],` (`public_html/admin/_layout.php:39`). The screen
(`public_html/admin/section.php:652-672`) runs `SELECT * FROM seo_meta ORDER BY id` and labels each
row with its URL — `'/' . ($r['slug'] === 'index' ? '' : $r['slug'] . '.php') . ' — ' . $r['title']`
— so a non-technical editor knows what they are editing. Its intro text teaches the craft in one
sentence: *"Google shows roughly the first 60 characters of a title and 155 of a description —
front-load what matters"* (`public_html/admin/section.php:657-659`). A dashboard card links to it
with the row count (`public_html/admin/index.php:39-40`).

What the admin may do is declared once, in `src/Content/Registry.php:184-192`. The registry is the
single authority the admin API uses to resolve tables and columns, so a browser request can never
name a table itself:

```php
// F3: per-URL search snippet. The row set is fixed (one per public
// URL, seeded) — only the two text fields are editable.
'seo_meta' => [
    'table' => 'seo_meta', 'orderable' => false, 'creatable' => false, 'deletable' => false,
    'fields' => [
        'title'       => ['type' => 'text', 'max' => 160, 'label' => 'Browser/search title'],
        'description' => ['type' => 'text', 'max' => 300, 'label' => 'Meta description', 'multiline' => true],
    ],
],
```

Those three `false`s are enforced server-side, not merely hidden in the UI:
`public_html/admin/api/item.php:46` refuses a create, `:111` refuses a delete, and
`public_html/admin/api/order.php:10-11` refuses a reorder. The only write path left is
`public_html/admin/api/field.php`, which updates one registry-validated field of one row and audits it.

**Why that is the right call.** A `seo_meta` row is meaningful only if a URL with that slug exists.
Creating a row for `/prospectus.php` when no such file exists produces nothing; deleting the `kg` row
silently degrades a live page to the generic title; order is meaningless, because nothing iterates
the table in sequence. The row set is a property of the **code** (which URLs exist); only the words
are content. The registry encodes exactly that split.

### 4.7 The two crawler files

A **sitemap** is a machine-readable list of your URLs, so the engine never has to stumble across a
page by luck. Ours is a static file, `public_html/sitemap.xml`, with 41 `<url>` entries:

```xml
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://stjosephsondipudur.com/</loc></url>
  <url><loc>https://stjosephsondipudur.com/about.php</loc></url>
```

Its own comment states the trade-off — *"The URL set is fixed (41 pages); hand-maintained, checked by
admin/health.php"* (`public_html/sitemap.xml:2-3`) — and that check is real:
`public_html/admin/health.php:54` asserts the file exists, and `DEPLOY.md:83` lists it as a
content-sanity check. A sitemap is a *hint*, not an instruction: listing a URL does not force
indexing, and a listed page linked from nowhere still ranks badly.

`robots.txt` is a plain text file at the site root that every well-behaved crawler reads before
fetching anything. Ours is the whole file:

```
# St.Joseph's MHSS, Ondipudur — crawler policy (F3).
# Everything public is crawlable; the admin panel and the API are not pages.
User-agent: *
Disallow: /admin/
Disallow: /api/

Sitemap: https://stjosephsondipudur.com/sitemap.xml
```

`User-agent: *` means "these rules apply to every crawler". `Disallow` means "please don't fetch
this" — we exclude the admin panel and the JSON API because they are not pages and crawling them
wastes **crawl budget** (the finite attention an engine gives your site). The `Sitemap:` line is how
a crawler finds the sitemap unprompted.

> **`robots.txt` is not a security control.** It is a **public file** listing the paths you would
> rather people not visit — an excellent map for anyone hostile. `Disallow` is a request polite
> robots honour and impolite ones ignore, and any human can type the URL. Our admin panel is
> protected by the login and session work of Stage A, **not** by this file. Never put a secret path
> in `robots.txt` and think it hidden.

### 4.8 `base_url` — why canonicals must be absolute

`config/config.sample.php:27-28`:

```php
// F3 SEO: absolute origin used for canonical/OG URLs and the sitemap.
'base_url' => 'https://stjosephsondipudur.com',
```

A **relative** URL (`/kg.php`) only has meaning next to the page it appeared on. The canonical tag's
entire job is to say *"whatever address you reached me by, this is my one true address"* — so it must
be **absolute**, scheme and host included. Otherwise `http://` and `https://`, `example.com` and
`www.example.com`, and every `?utm_source=…` tracking copy become separate competing pages, and the
ranking signal splits. The same applies to `og:image`: a chat app fetching the preview has no page
context, so a relative path simply fails. `rtrim($…, '/')` on `views/shell.php:18` strips a trailing
slash so a misconfigured `'https://site.com/'` cannot produce `https://site.com//about.php`. The real
value lives in `config/config.php` above the webroot (`DEPLOY.md` §0.3); the sample is the dev
fallback.

### 4.9 Speed and accessibility are SEO — and the honest score

Two ranking inputs are covered by sibling docs. **Core Web Vitals** — LCP (how fast the biggest
visible thing paints), CLS (how much the layout jumps), INP (how fast it responds to a tap) — were
moved directly by Stage H's image work; see `docs/perf-baseline.md` and
[`../08-stage-h.md`](../08-stage-h.md) Parts 1 and 3. **Structure the robot can read** — `alt` text
(the only way an engine knows what a photo shows), headings in order without skipping, real crawlable
`<a href>` links — came from the R3 accessibility work. That is the usual pattern: accessibility work
and SEO work are the same work.

Measured scores, from `docs/perf-baseline.md:79-89` (mobile Lighthouse, throttled 4G, dev box):

| Page | SEO score |
|---|---|
| academy (tamil) | **100** |
| album (gal-annual) | **100** |
| home | 92 |
| infrastructure | 92 |

The two 92s lose points for exactly one thing: *"the two 92s lose points ONLY for the literal words
'Read more'/'Learn more' on two buttons — reword them in Site Settings for 100 (content decision, not
code)"* (`docs/perf-baseline.md:87-89`). Why does that cost points? A link whose text is "Read more"
tells an engine — and a screen-reader user tabbing through links — nothing about the destination. It
is a one-field admin edit, deliberately left to the school: changing visible page text is a content
decision, and the visual-freeze rule forbids slipping it in under a refactor.

### 4.10 The part no code can do

Everything above is *on-page* SEO. For a local school the *off-page* work matters more, and it
belongs to the school, not the codebase: claim the **Google Business Profile** with exact
name/address/phone (local "schools near me" searches are answered mostly from this); keep that
**NAP** — Name, Address, Phone — identical on the profile, the site footer and every directory; earn
**real backlinks** (the diocese site, school directories, local news covering events); collect
**parent reviews**; and set up **Google Search Console** to submit the sitemap and see which queries
actually showed the site. The full playbook and launch-day checklist are in
[`../08-stage-h.md`](../08-stage-h.md) §2.3–§2.4.

---

## 5. Why this is the right approach here

Three designs were possible. The constraints chose between them.

| Approach | What it means | Why not here |
|---|---|---|
| **Hard-code tags per page** | Write `<title>` into each of the 42 controllers or templates. | 18 academies share **one** template (`src/View/Layout.php:17-28`), so per-template tags give 18 identical titles. Per-controller tags mean 42 places to keep in sync and a developer for every typo. |
| **An SEO plugin** | Drop in the WordPress/Yoast-style module everyone knows. | There is no framework and no plugin system — structured plain PHP with Composer PSR-4 (`CLAUDE.md`). A plugin also brings an admin UI, an update channel and an attack surface, to solve a problem that is two text columns. |
| **A field per page in a CMS entity** | Add `title`/`description` columns to `pages`, `academies` and `gallery_albums`. | Three tables, three code paths in the shell, three admin screens — and no home for a URL with no content row at all. Rejected explicitly in `database/migrations/007.sql:2-4`. |

What we built instead — one flat slug-keyed table, one query, one printing site, one admin screen —
satisfies every constraint at once: **42 URLs across 6 templates** (keying on the URL slug is the only
thing that gives every URL its own words); **a non-technical editor** (rows behind a login, so fixing
a description takes a minute and no developer); **no framework** (about 25 lines of PHP, one table,
zero dependencies to update or be vulnerable); **a request must never choose a template** (the slug
comes from `SCRIPT_NAME`, which the server sets — the same discipline as SEC-11 and `Layout::PAGES`);
**visual freeze** (it all lives in `<head>` and renders nothing); and the **query budget** (one extra
`SELECT`, paid for by turning another query into a JOIN).

---

## 6. How this scales

Suppose the school doubles to 100 pages and adds a gallery album every month.

**What already scales.** The shell is untouched — it looks up whatever slug it is on. The table is
keyed by a unique indexed `VARCHAR(64)`, so a lookup is one index hit at 100 rows or 100,000. The
admin screen is a `foreach`; at ~200 rows it wants a search box, but nothing breaks.

**What breaks first: the sitemap.** It is hand-maintained (`public_html/sitemap.xml:2-3`) — honest
and correct for a fixed set of 41 URLs that changes only when a developer adds a file. It must become
**generated** the moment either is true: (1) page or album creation moves into the admin panel, so a
non-developer can mint a URL, because a human-maintained list goes stale the first time; or (2) the
list crosses roughly 100 entries, where hand-editing reliably drops or duplicates a line. The
generated version is small — a `sitemap.php` that selects the public slugs, sends the right
`Content-Type`, and derives every `<loc>` from `base_url` exactly as `views/shell.php:19` already
does. Respect the project rule while doing it: *"No generator may ever emit executable PHP into
`public_html/`"* (`CLAUDE.md`). Emitting XML *from* a PHP page is fine; writing a PHP file is not.

**How new URLs get rows.** The same way the first 41 did: a slug/title/description triple appended to
`$seoRows` (`database/seed.php:529`), or an additive migration; `INSERT IGNORE` makes it safe against
a live database. If the admin panel ever creates URLs, the same transaction that creates the page
must create its `seo_meta` row — at that point `'creatable' => false` becomes wrong, and row creation
belongs to the page-creation endpoint, not to a human clicking "Add".

**What happens meanwhile — the fallback.** Nothing crashes. `views/shell.php:20-21`:

```php
$sjTitle = ($sjSeo['title'] ?? '') !== '' ? $sjSeo['title'] : ($title ?? "St.Joseph's MHSS, Ondipudur");
$sjDesc  = $sjSeo['description'] ?? '';
```

With no row, `$sjSeo` is `[]`, so the title falls back to the controller's `$title` — and every
controller passes the same generic string (`public_html/about.php:14`:
`'title' => "St.Joseph's MHSS, Ondipudur",`). A missing row therefore degrades that page to the old
duplicated title and prints no description at all. The page works perfectly; it just competes badly.
That is the right failure mode — degrade, never break — but it is **silent**, which is why the fix
must be mechanical generation rather than discipline.

---

## 7. Gotchas and mistakes to avoid

**Duplicate titles across pages.** The failure the whole feature exists to prevent, and it returns
free of charge whenever a new page ships without a `seo_meta` row (§6). One query checks it:
`SELECT COUNT(*), COUNT(DISTINCT title) FROM seo_meta;` — the two numbers must match.

**A canonical pointing at the wrong URL.** The most dangerous tag on the page, because mistakes are
invisible and silent. Point all 41 canonicals at the homepage and you have told Google that 41 pages
*are* the homepage — they drop out of the index. Ours is computed from the slug so it cannot be
mistyped, but two ways to get it wrong remain: leaving `base_url` at the sample value on a
differently-named host, and serving the site on `www.` while the canonical says the bare domain.
Verify after any domain change.

**Blocking a page in `robots.txt`, then wondering why it has no description.** `Disallow` means
"don't *fetch*", not "don't *list*". If another site links to a blocked page, Google can still index
the URL — but it has never read the page, so it shows a bare URL and "No information is available for
this page." To keep a page out of results, let the crawler in and use
`<meta name="robots" content="noindex">` — which it can only see if it is allowed to fetch the page.
The two mechanisms are opposites and must not be combined.

**Changing URLs without redirects.** Renaming `kg.php` to `kindergarten.php` throws away every link,
bookmark and accumulated ranking signal pointing at the old one. Our rule is absolute — *"every
legacy URL keeps working"* (`CLAUDE.md`) — and `public_html/gal-sciexpo.php` is the pattern to copy:

```php
http_response_code(301);
header('Location: /gal-spach.php');
exit;
```

**301** means *moved permanently* and is the code that transfers ranking signal; a 302 (temporary)
does not. If you rename a page you also rename its `seo_meta` slug and its sitemap line — three
edits, always together.

**Keyword stuffing.** "best school best CBSE school best matric school Coimbatore" in a title looks
like it should help; it does the opposite. Spam systems detect it, the penalty outweighs the gain,
and a human reading the result clicks elsewhere. Write for the parent, not the robot.

**Relying on the sitemap instead of internal links.** A sitemap is a hint; links are the structure. A
page reachable only from the sitemap reads as "even its own site doesn't think this matters".
Everything should be reachable by clicking from the homepage in a few hops — which our navbar and
gallery index already achieve.

**Two smaller ones.** Don't fill the columns just because they allow 160 and 300 characters — Google
shows ~60 and ~155, and the rest is invisible. And don't put anything in these fields you wouldn't
print on a billboard: they are the most public text on the site, and they get copied into other
people's search results and chat apps.

---

## 8. Try it yourself

Start the stack with `./run.sh`, then:

**1. See what a page actually emits.** You should get the title, the description, the canonical link
and six `og:` tags. Repeat for `/kg.php` and `/gal-annual.php` and confirm every title differs.

```bash
curl -s http://localhost:8090/about.php | grep -iE '<title>|description|canonical|og:'
```

**2. Read the two crawler files** (plain static files — a browser works too):

```bash
curl -s http://localhost:8090/robots.txt
curl -s http://localhost:8090/sitemap.xml | head -12
```

**3. Change a description and watch it move.** Open `http://localhost:8090/admin/`, log in, click
**🔍 SEO** in the sidebar, find the `/about.php` row, edit its description, save. Re-run step 1: the
new sentence appears in **both** `meta name="description"` and `og:description` — one edit, two tags,
no deploy. That is the whole point of the feature.

**4. See the fallback.** In the same screen, blank the `/about.php` **title**, re-run the curl, and
watch it become the generic `St.Joseph's MHSS, Ondipudur` with no description tag at all — exactly
what a page with no row gets (§6). Put your text back afterwards.

**5. Count the queries.** Set `'debug' => true` in `config/config.php`, reload a page and find the
`<!-- sj-queries: N -->` comment in the source. `Repo::seo()` is one of them; the budget is 12.

---

## 9. Where to read more

**In this repo**

- [`../08-stage-h.md`](../08-stage-h.md) **Part 2** — the deep conceptual chapter: the full
  crawl/index/rank pipeline, PageRank and authority, E-E-A-T, the off-page playbook and the Search
  Console checklist. Read it after this one.
- [`../../../DEPLOY.md`](../../../DEPLOY.md) — production setup: §0.3 is where `base_url` and the rest of
  the real config go (above the webroot), §5 documents the `sitemap` health check, §2 lists what must
  never be uploaded.
- [`../../../PHASES.md`](../../../PHASES.md) — row 33 (**F3**) is this feature's specification and its
  recorded acceptance results.
- [`how-php-serves-a-page.md`](how-php-serves-a-page.md) and
  [`architecture-and-layers.md`](architecture-and-layers.md) — the shell, the layout engine and the
  repository layer this document assumes. `SECURITY.md` **SEC-11** covers why request data never
  selects a template or a path; `docs/perf-baseline.md` holds the scores quoted in §4.9.

**Outside**

- **Google Search Central** — <https://developers.google.com/search/docs> — the primary source. Start
  with the SEO Starter Guide, then the pages on titles, snippets, canonical URLs, `robots.txt` and
  sitemaps.
- **Google Search Console** — <https://search.google.com/search-console/about> — the free dashboard
  for a site you own: submit the sitemap, see indexing coverage and the real queries people typed.
- **web.dev — Core Web Vitals** — <https://web.dev/articles/vitals> — LCP, CLS and INP defined, with
  the thresholds Google grades against.
- **Schema.org** — <https://schema.org/School> — structured data. Not used in this codebase today;
  the obvious next step if the school wants address, phone and hours shown directly in Google's
  results.
