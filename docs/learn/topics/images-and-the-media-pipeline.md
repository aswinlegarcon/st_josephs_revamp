# File Uploads and the Image Pipeline

> **What you'll learn:** why letting someone put a file on your server is the most dangerous feature
> a website can have, and every layer we built to make it safe. Then: image formats from zero, what
> a *rendition* is, how our four media tables fit together, how a photo reaches a visitor's browser,
> and the one layout bug that changed how we render every legacy photo.
>
> **Prerequisites:** [`../01-fundamentals.md`](../01-fundamentals.md) — what a web server is, what
> PHP does, what a database table is. You need `$_POST`, arrays, functions and `if`. Nothing about
> images or uploads is assumed.
>
> **Where it lives in our code:** `src/Media/Pipeline.php` (processing), `src/Media/Html.php`
> (rendering), `public_html/admin/api/{upload,recrop,image,images}.php` (admin endpoints),
> `database/schema.sql` (the four tables), `database/backfill.php` (the one-off job),
> `public_html/media/` (the files) and `public_html/media/.htaccess` (the guard).

---

## 1. The one-paragraph version

An admin picks a photo and presses Upload. The browser POSTs the bytes to
`public_html/admin/api/upload.php`, which hands them to `SJ\Media\Pipeline::processUpload()`. That
function checks the file is small enough, is really an image (not a `.php` script wearing a `.jpg`
costume), and is not absurdly large in pixels. It inserts a row into the `images` table to get an
**id**, saves the untouched original at `media/{id}/original.jpg`, then generates resized,
recompressed copies — **renditions** — sized for the exact slot on the page
(`media/{id}/card_4x3.jpg` plus `.webp`), each recorded in `image_renditions`. When a page renders,
`SJ\Media\Html::tag()` turns the database row into an `<img>` pointing at the small rendition, never
the huge original. That is the pipeline: **validate → store original → generate per-slot copies →
serve the copy.**

---

## 2. The problem this solves

**Security.** Our server runs PHP: if a file ending in `.php` sits inside `public_html/` and someone
requests its URL, **the server executes it**. It does not ask who put it there. So an upload form
that saves whatever it is given, under whatever name it was given, is a catastrophe — upload
`shell.php`, then visit `https://…/media/shell.php?cmd=cat+/etc/passwd`. That single file is a
**remote shell**, a way to run arbitrary commands through a browser; from there the attacker reads
the database password out of `config/config.php`, deletes things, or serves spam from the school's
domain. It is the most common way small PHP sites get taken over, and several tricks get past a
naive check:

| Trick | What it looks like | Why the naive check fails |
|---|---|---|
| Rename the extension | `shell.php` renamed `photo.jpg` | You checked the *name*, not the bytes. |
| Lie about the type | Browser sends `Content-Type: image/jpeg` | That header is written by the *client*. |
| Polyglot file | A valid JPEG whose comment holds `<?php …` | It really is an image. If executed, the PHP runs. |
| Path traversal | Filename `../../index.php` | You joined a user string onto a directory path. |
| Decompression bomb | A 40 KB PNG that decodes to 30,000×30,000 | Tiny on disk, gigabytes in memory. |

**Performance.** Measured on the original live site
([`docs/perf-baseline.md:13`](../../perf-baseline.md)), the Home page transferred **10,344 KB, of
which 9,834 KB — 95% — was images**; one hero photo alone was 2,669 KB. Photos went straight from a
camera to the server, so a slot ~1265 px wide was fed a 4000 px file. The browser downloaded all of
it and threw most of it away.

---

## 3. How it works in general

**`$_FILES`.** With `enctype="multipart/form-data"`, PHP writes the uploaded bytes to a temporary
file and describes it in `$_FILES`. For a field named `file`:

| Key | What it is | Trust it? |
|---|---|---|
| `name` | Filename the **browser** sent | **No** — attacker-controlled |
| `type` | MIME type the **browser** claims | **No** — attacker-controlled |
| `tmp_name` | Path to the temp file PHP wrote | Yes |
| `size` | Bytes actually received | Yes |
| `error` | `0` (`UPLOAD_ERR_OK`) or a failure code | Yes |

A **MIME type** is a short label for what kind of data a file holds (`image/jpeg`, `text/html`). The
lesson is the whole game: name and type are strings the client typed. The only trustworthy way to
know what a file is, is to **open it and read the bytes**. Two PHP tools do that:
`finfo(FILEINFO_MIME_TYPE)->file($path)` reads the leading "magic number" bytes, and
`getimagesize($path)` tries to *parse* the file as an image, returning width, height and MIME — or
`false`. We run both and require them to agree.

**Image formats, from zero.** An image file stores a grid of coloured dots (pixels). **Lossless**
compression can rebuild those pixels exactly, like a ZIP; **lossy** compression throws away detail
your eye will not miss, and you can never get it back — but the file is far smaller.

| Format | Compression | Transparency | Best for | Worst for |
|---|---|---|---|---|
| **JPEG** | Lossy | No | Photographs | Sharp text and logos — it smears edges |
| **PNG** | Lossless | Yes | Logos, icons, screenshots, see-through backgrounds | Photographs — many times bigger than an identical-looking JPEG |
| **WebP** | Both (we use lossy) | Yes | Everything, in modern browsers — ~25–35% fewer bytes than JPEG at the same quality | Very old browsers |

Two of the heaviest files on the old Home page were photographic **PNGs** (`testimonial1.png`
924 KB, `testimonial3.png` 940 KB — `docs/perf-baseline.md:22`); that mistake cost ~900 KB each. A
**progressive JPEG** is stored so the browser paints a blurry whole image immediately and sharpens
it, instead of drawing strips from the top — we enable it on every rendition.

**Renditions, presets, fit vs cover.** A **rendition** is a pre-generated copy of one image for one
slot; a **preset** is that slot's recipe — how big, what shape, how hard to compress. In **`fit`**
mode the picture shrinks until it fits inside a `max_w × max_h` box, keeping its proportions, and
**nothing is cut off**. In **`cover`** mode a rectangle of the requested shape (say 4:3) is cut out
of the photo first and then shrunk: **parts are discarded**, but every output has the same shape, so
a card row looks tidy. Use `cover` when uniformity matters (cards, heroes); `fit` when the whole
picture matters (lightbox, contained carousel slides).

**EXIF orientation — why phone photos arrive sideways.** **EXIF** is metadata cameras embed inside
JPEGs: date, model, sometimes GPS — and an `Orientation` number. Rotate your phone for a landscape
shot and the sensor does not rotate; the phone stores the pixels sideways and writes
`Orientation: 6`, meaning "display rotated 90°". Photo apps obey it. Naive image code does not — and
once you re-encode, the tag is gone and the sideways result is permanent. So you must read
`Orientation` and physically rotate the pixels **first**.

---

## 4. How we use it — every place in this codebase

### 4.1 The four media tables

All in [`database/schema.sql:70-118`](../../../database/schema.sql).

| Table | One row = | Key columns |
|---|---|---|
| `images` | A picture that exists, uploaded or legacy | `id`, `legacy_path`, `original_name`, `alt_text`, `mime`, `width`, `height`, `preset_key`, `crop_rect`, `version` |
| `image_presets` | One slot recipe | `preset_key` (PK), `max_w`, `max_h`, `aspect_w`, `aspect_h`, `mode` (`cover`/`fit`), `quality` |
| `image_renditions` | One generated file on disk | PK `(image_id, preset_key, format)`, plus `width`, `height`, `bytes` |
| `image_links` | "This photo belongs to that collection, in this position" | `owner_type`, `owner_id`, `role`, `image_id`, `position` |

**`legacy_path` splits the world in two.** The seeder registers all 478 files in
`public_html/photos/` as `images` rows with `legacy_path` set
([`database/seed.php:27-40`](../../../database/seed.php)). A row *with* it is an old photo still
living at its old URL; a row *without* it is an upload under `media/{id}/`. Nearly every branch in
the media code asks `if (!empty($img['legacy_path']))`. **`image_links` is a many-to-many join
table:** a plain foreign key (`hero_slides.image_id`) says "one slide, one image", but an album year
has *many* photos and one photo may appear in several collections. `role` separates collections on
the same owner (`photos` vs `carousel`); `position` gives the drag-to-reorder order; `owner_type`
resolves only through a hardcoded whitelist
([`src/Content/Registry.php:225-238`](../../../src/Content/Registry.php)), so a request can never
name a table.

### 4.2 The nine presets

Seeded at [`database/schema.sql:366-375`](../../../database/schema.sql). `bg_wide` uses quality 74
because those photos sit under a 70% dark gradient — the extra compression is invisible there.

| preset_key | Box | Aspect | Mode | Quality | Used for |
|---|---|---|---|---|---|
| `hero_16x7` | 1920×840 | 16:7 | cover | 80 | page-top hero slides |
| `gallery_tile` | 500×700 | 5:7 | cover | 82 | album grid tiles |
| `gallery_full` | 1600×1200 | — | **fit** | 82 | lightbox large view |
| `card_4x3` | 800×600 | 4:3 | cover | 82 | academy/sport/album/grade cards |
| `content_slide` | 1200×900 | — | **fit** | 82 | inner carousel slides |
| `portrait_4x5` | 800×1000 | 4:5 | cover | 82 | principal portrait |
| `feature_4x3` | 1000×750 | 4:3 | cover | 82 | feature blocks, achievements |
| `update_16x9` | 1280×720 | 16:9 | cover | 82 | update slides |
| `bg_wide` | 1920×1080 | 16:9 | cover | 74 | section backgrounds |

### 4.3 Validation, layer by layer

[`src/Media/Pipeline.php:71-140`](../../../src/Media/Pipeline.php), in order. Each check closes one
attack from §2:

| Line | Check | Closes |
|---|---|---|
| 73-79 | `$file['error'] !== UPLOAD_ERR_OK` → reject; `size` must be `> 0` and `<= $maxBytes` | oversize / partial uploads |
| 81-83 | `!\is_uploaded_file($tmp)` → reject (skipped only when `PHP_SAPI === 'cli'`) | processing an arbitrary server file |
| 85-94 | `finfo` MIME **and** `getimagesize()` MIME must match **and** be in `['image/jpeg','image/png','image/webp']` | MIME spoofing, SVG, HTML polyglots |
| 95-98 | `$w > 8000 || $h > 8000` → reject | decompression bombs |
| 100-103 | `preset` must exist in `image_presets` | unknown/forged slot |
| 105-118 | path built from `lastInsertId()`, extension from the **sniffed** MIME | path traversal, `.php` upload |
| 125-135 | any throw → delete the files **and** the row | half-created records |

The size limit is **10 MB**, set once as `10 * 1024 * 1024` in
[`config/config.php:25`](../../../config/config.sample.php), with the same fallback in
[`src/Core/Config.php:28`](../../../src/Core/Config.php). The MIME check is the heart of it:

```php
$finfoMime = (new \finfo(\FILEINFO_MIME_TYPE))->file($tmp) ?: '';
$info = @\getimagesize($tmp);
if ($info === false) { throw new RuntimeException('File is not a valid image.'); }
$gisMime = $info['mime'] ?? '';
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!\in_array($finfoMime, $allowed, true) || $finfoMime !== $gisMime) { … }
```

**Two independent detectors must agree, and the answer must be on the whitelist.** Notice what is
never consulted — `$file['name']` and `$file['type']` — and what is not allowed: **SVG**, which is
XML that can contain `<script>`, a stored-XSS vector. **The filename never touches the disk path:**
`extForMime()` (lines 61-64) maps the sniffed MIME to exactly three extensions
(`['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'bin'`) and the
directory comes from the auto-increment id, so `../../evil.php` becomes `media/512/original.jpg`.
The submitted name survives only as a display label in `original_name`, stripped by
`preg_replace('/[^A-Za-z0-9._ -]/', '', …)` (line 105). Finally, line 119 —
`$moved = (\PHP_SAPI === 'cli') ? \copy(…) : \move_uploaded_file($tmp, $origPath);` —
`move_uploaded_file()` is `rename()` **plus** the same "really uploaded this request?" check. Always
use it in a web request; `copy()` is the CLI-only path for the backfill.

### 4.4 The directory rules that make execution impossible

Validation is defence in depth; the last line is server config.
[`public_html/media/.htaccess`](../../../public_html/media/.htaccess) is four lines:

```apache
php_flag engine off
<FilesMatch "\.(php|phtml|phar)$">
  Require all denied
</FilesMatch>
```

`php_flag engine off` turns the interpreter **off for that whole directory tree** — a `.php` file
there is served as bytes, not executed — and the `FilesMatch` block then refuses to serve those
extensions at all, so even a perfect polyglot is inert. This is the recorded mitigation for
**SEC-10** ([`SECURITY.md:70`](../../../SECURITY.md)). Files are written `0644`
(`src/Media/Pipeline.php:123`, and again at 224 and 230), directories `0775` (line 115). **777 is
never correct** — PHP runs as the account user and never needs it.

### 4.5 Generating the renditions

[`src/Media/Pipeline.php:160-242`](../../../src/Media/Pipeline.php). **GD** is the image library
built into PHP: it decodes an image into memory and encodes it back out.

1. **Decode by real MIME** — `imagecreatefromjpeg` / `…frompng` / `…fromwebp` (lines 167-172).
   Because we always decode and re-encode, any PHP or EXIF payload hidden in the source does not
   survive into the rendition.
2. **Fix rotation** for JPEGs (line 177): `fixOrientation()` (lines 272-289) reads
   `exif_read_data()` and calls `imagerotate()` for orientations 3, 6 and 8.
3. **Decide the crop** (lines 183-197): `cover` with no manual rectangle computes the largest
   centred rectangle at the preset aspect; `fit` sets `$crop = null; // fit mode: no cropping`.
4. **Scale down, never up** (line 204) — the `1` is the whole rule:
   `$scale = \min(1, $preset['max_w'] / $sw, $preset['max_h'] / $sh);`
5. **Resample** onto a fresh canvas filled white (lines 208-211). The white fill matters: a
   transparent PNG has nothing behind it and JPEG has no transparency, so those areas would come
   out black.
6. **Encode** a progressive JPEG (`imageinterlace($dst, true)`) at the preset quality, then WebP at
   `quality - 2` — but only if the installed GD can: `if (\function_exists('imagewebp'))` (line
   227). That is **feature detection**: ask, don't assume. Shared hosts vary.
7. **Record** each file with `REPLACE INTO image_renditions …` (lines 236-241), so regenerating
   overwrites the row instead of erroring on the primary key.

`parseCrop()` (lines 143-157) turns the browser's `"x,y,w,h"` string into a bounds-checked array,
returning `null` — "fall back to automatic" — if the numbers are negative, too small, or reach
outside the source; never trust client coordinates either. `ensureRendition()` (lines 245-269) is
the lazy generator: if an *uploaded* image is requested in a preset it has never been rendered in,
generate it now. It refuses immediately for legacy images
(`if (!empty($img['legacy_path'])) return false;`), which are only ever generated offline.

### 4.6 Serving — `src/Media/Html.php`

**`url($img, $presetKey)`** (lines 21-42) returns a path — for an upload,
`/media/{id}/{preset}.jpg?v={version}`; for a legacy row it prefers the backfilled rendition and
otherwise falls back to `legacy_path`, the original `/photos/…` file. That fallback is the safety
net: a missing rendition degrades to yesterday's behaviour, not a broken image.
**`bgStyle($img, $presetKey)`** (lines 103-109) wraps the same URL in `background-image:url('…')`
for CSS-background slots, which cannot use `<picture>` and so always get the JPEG.
**`tag($img, $presetKey, $attrs)`** (lines 45-100) emits the element, and line 55 decides the
loading strategy:

```php
$lazy  = empty($attrs['eager']) ? ' loading="lazy"' : ' fetchpriority="high"';
```

`loading="lazy"` means *don't download this until the user scrolls near it* — a visitor who reads
only the top never pays for the photos below. The first hero slide is the opposite case: it is
visible instantly and is usually the **LCP** element (Largest Contentful Paint — "when did the main
thing appear?"), so lazy-loading it would hurt. Views pass `'eager' => $i === 0` (e.g.
`views/partials/carousel.php:19`) and it gets `fetchpriority="high"`. For an upload with a WebP
rendition on disk, lines 96-98 emit:

```html
<picture>
  <source type="image/webp" srcset="/media/999/card_4x3.webp?v=1">
  <img src="/media/999/card_4x3.jpg?v=1" width="800" height="600" alt="…" loading="lazy">
</picture>
```

`<picture>` is a chooser: the browser uses the first `<source>` type it understands, and one that
has never heard of WebP ignores it and uses the `<img>` — which is not optional, it is the real
image and the fallback. `width`/`height` come from the recorded rendition size (lines 92-94) so the
browser reserves the right box before bytes arrive, preventing **CLS** (Cumulative Layout Shift —
text jumping as images pop in).

### 4.7 The hard-won legacy rule — a debugging story

Everything above describes **uploads**. Legacy photos render differently, and this is the most
valuable lesson in the file; the rule is written as a comment at
[`src/Media/Html.php:70-78`](../../../src/Media/Html.php) precisely because it was *measured*. When
F2 backfilled renditions for the 478 legacy photos, we put legacy slots on the new `<picture>`
markup. A per-element layout verifier — a script that records the on-screen box of every element on
all 42 pages and diffs before/after — lit up immediately: rows had moved **hundreds of pixels**. The
visual-freeze rule demands pixel-identical, so this was a hard stop. Three causes were isolated one
at a time:

1. **Cover-cropping moved things.** Many shipped slots are styled `width: 100%; height: auto`, so
   the rendered height derives from *the photo's own aspect ratio* — crop to 4:3 and you have
   changed the layout. Fix: the backfill overrides the preset, `$fitPreset['mode'] = 'fit';` at
   [`database/backfill.php:114`](../../../database/backfill.php), under a comment beginning
   `// LEGACY RULE: generate in FIT mode (downscale only, never crop)`.
2. **The `<picture>` wrapper was the real breaker.** Several shipped sections are flex rows
   (`display: flex`) whose CSS targets the `img`. Wrap that img and the **flex item** becomes the
   unstyled wrapper, not the styled image — the sizing rules stop applying and rows that used to fit
   start wrapping onto a second line. We chased dimensions and aspect ratios for hours before
   isolating the wrapper itself.
3. **`width`/`height` attributes pinned the wrong dimension.** In slots styled with a width but no
   height, an attribute `height` wins and squashes the photo.

Hence lines 79-85:

```php
$isLegacy = !empty($img['legacy_path']);
if ($isLegacy) {
    $url = \in_array($presetKey, self::LEGACY_KEEP_ORIGINAL, true)
        ? $img['legacy_path']
        : self::url($img, $presetKey); // rendition, else legacy_path
    return '<img src="' . e($url) . '"' . $class . $style . $alt . $lazy . … . '>';
}
```

**A legacy photo renders as a plain `<img>` — no `<picture>` wrapper, no `width`/`height`
attributes.** The `.webp` files are still generated and kept for future redesigned slots; they are
simply not referenced yet. **New uploads keep the full treatment.** The final sweep was 16/16 pages
with zero elements moved more than 2 px. The moral generalises far beyond images: **measure, don't
reason** — layout bugs are rarely where the theory says.

### 4.8 Cache-busting with `images.version`

`public_html/.htaccess:47-51` sets `Cache-Control: public, max-age=31536000, immutable` on every
image, script and stylesheet. One year, and `immutable` means *this will never change; do not even
ask again* — perfect for speed, and exactly the problem when you re-crop, because the file changes
but the URL does not. So we change the **URL**. `images.version` is a counter defaulting to 1
(`database/schema.sql:80`), and re-cropping bumps it
([`public_html/admin/api/recrop.php:39-41`](../../../public_html/admin/api/recrop.php)):

```php
media_generate($orig, $imageId, $preset, $crop);
db()->prepare('UPDATE images SET crop_rect = ?, version = version + 1 WHERE id = ?')
    ->execute([$rectStr, $imageId]);
```

Every URL carries `?v=` from that column (`src/Media/Html.php:34`, `:39`, `:97`), so
`card_4x3.jpg?v=1` becomes `?v=2` — a different URL, a cache miss, fresh bytes.

### 4.9 The admin endpoints

All are reached through the hardcoded route map in `public_html/admin/api/index.php`, and all
`require` `_bootstrap.php` first, which checks the session, the forced-password gate and the CSRF
token on every non-GET request (`_bootstrap.php:22-37`).

| Endpoint | Method | What it does |
|---|---|---|
| `upload.php` | POST multipart | Reads `file`, `preset`, `alt`, `crop_rect`; calls `media_process_upload()`; audits; returns `{image_id, url, name}` |
| `recrop.php` | POST JSON | Re-renders an **uploaded** image from its stored original with a new crop; bumps `version` |
| `images.php` | GET | Paginated media library (24/page) with search, usage counts and an "orphan" filter |
| `image.php` | POST JSON | `meta` (edit alt text), `usage` (where used), `delete` (guarded) |

`recrop.php` refuses two cases up front (lines 17-27): legacy images (no stored original to re-cut)
and `fit`-mode presets (`'This image type has no crop (fit mode)'`). The browser side uses the
vendored **Cropper.js** (`public_html/admin/assets/cropper/`, no CDN), locked to the preset aspect
and primed with the stored rectangle (`public_html/admin/assets/panel.js:249-282`). **The delete
guard** is worth studying: deleting an image a page still points at would leave a broken slot, so
`image.php` enumerates every place an image can be referenced — `const SJ_IMAGE_REFS` at
`public_html/admin/api/image.php:20-36`, thirteen `[table, column, label]` triples — counts them,
and refuses readably (lines 68-75):

```php
$usage = image_usage($imageId);
if ($usage) {
    $parts = [];
    foreach ($usage as $label => $n) { $parts[] = "$n × $label"; }
    api_fail('Cannot delete — used in: ' . implode(', ', $parts));
}
```

Read the comment just above the list: `section_events` and `gallery_albums` were **missing**
originally, so deleting an image used there would have silently broken the slot. Hand-maintained
lists like this are a hazard — the parallel copy in `images.php:29-36` even carries
`// keep in sync with SJ_IMAGE_REFS in image.php`. Deletion also skips file removal for legacy rows
(`image.php:79-85`); those files belong to `/photos/` and old URLs must keep working.

### 4.10 The one-off backfill

[`database/backfill.php`](../../../database/backfill.php) produced the 89% win; read its header
comment (lines 2-18) first. Four decisions. **CLI + Docker only** — lines 20-22,
`if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }` — because crunching 478 photos through GD would
starve a cheap shared host, so renditions are generated on a developer machine and **deployed as
files** plus an SQL delta. **Only the pairs the site actually uses:** `$fkMap` (lines 43-57) lists
every foreign-key column and the preset its view passes to `img_tag()`, and `$linkRoleMap` (58-61)
maps link roles (`photos` → `gallery_full`, `carousel` → `content_slide`); `profiles` appears twice
with a `WHERE` clause because staff blocks render 4:3 while portrait blocks render 4:5. Result: 494
rendition sets (988 rows), not 478 × 9. **Idempotent** — running it twice does the same as once,
via lines 97-100, `if (media_renditions($imageId, $presetKey)) { $skipped++; continue; }`, so adding
ten photos and re-running processes only those ten. **It fixes the CSS-referenced stragglers too:**
ten design assets are referenced straight from stylesheets and never pass through `img_tag()`, so
the preset pipeline cannot reach them; lines 132-146 list them with a max edge and a quality and
write **full-frame, never cropped** copies into `media/static/`. The three ~920 KB testimonial PNGs
became ~80 KB JPEGs, and the stylesheets now point there (e.g. `public_html/css/home.css:688`).

### 4.11 The measured result

From [`docs/perf-baseline.md:64-74`](../../perf-baseline.md), Home page, same method before/after:

| Metric | Before (F0, live) | After (F4) | Verdict |
|---|---|---|---|
| Total transferred | 10,344 KB | **1,906 KB** | −82% |
| **Image payload** | **9,834 KB** | **1,108 KB** | **−89%** |
| Largest single image | 2,732 KB | 270 KB | nothing over 300 KB |
| SQL queries (home) | 16 | 12 | within budget |
| CLS | — | 0.001–0.048 | effectively zero layout shift |

---

## 5. Why this is the right approach here

| Alternative | What it gives you | Why not here |
|---|---|---|
| **Image CDN** (Cloudinary, imgix) | Resize/crop/format by URL, global edge cache, automatic AVIF | A recurring bill on a school budget, an external dependency for every photo, and third-party hosting of children's photographs. Our host is MilesWeb shared with no SSH. |
| **ImageMagick / Imagick** | Better resampling, more formats, real EXIF handling | Not reliably installed on shared hosting, and we cannot install extensions without SSH. GD ships with PHP everywhere — we feature-detect even `imagewebp()` (`src/Media/Pipeline.php:227`) because we cannot assume. |
| **Generate on the fly, per request** | No backfill job, never a stale rendition | Every cache miss costs CPU on a host with modest limits — an album page renders ~250 photos. Our budget is ≤12 SQL queries and a fast response. Pre-generating moves the cost off the visitor's request entirely. |
| **Store bytes in MySQL** (BLOBs) | One backup covers everything; no orphan files | Every image becomes a PHP + DB round trip, losing Apache's static-file path, `Cache-Control: immutable` and byte ranges. Backups balloon. We store *metadata* in MySQL and *bytes* on disk — the normal split. |

One constraint no CDN would have helped with: the **visual-freeze rule**. The site must render
pixel-identical to the pre-revamp baseline, and a CDN's "smart crop" would have produced exactly the
drift in §4.7. Owning the pipeline is what let us change fit/cover, markup shape and attributes
independently until the verifier's diff was zero.

---

## 6. How this scales

Suppose the school uploads ten times as many photos — roughly 5,000. **Disk:** renditions are cheap
(our 478 legacy photos produced 496 JPEG and 496 WebP files); the expensive item is the
**originals**, kept forever at `media/{id}/original.*` at full camera size. At 10× that is a few GB,
and shared-hosting quota is the first thing to hit — the admin dashboard health strip already gates
on free disk below 200 MB. **The backfill cost** is linear and idempotent, so a re-run after 4,500
new uploads processes only the new ones, and it is offline, so a slow run costs a coffee break, not
a page load. **Where the design bends first,** in impact order, from the "stragglers" list at
[`docs/perf-baseline.md:95-105`](../../perf-baseline.md):

1. **Responsive `srcset`** — each preset has exactly one width today, so a phone downloads the
   desktop rendition. Adding a second ~768 px width per preset and emitting `srcset`/`sizes` lets
   the browser choose. No schema change needed: `image_renditions` is keyed on
   `(image_id, preset_key, format)`, so a key like `card_4x3_768` slots straight in.
2. **Use `gallery_tile` in album grids**, keeping `gallery_full` for the lightbox. The preset already
   exists and is already generated; only the markup and lightbox change.
3. **An object store or CDN** (or the optional Cloudflare front in `DEPLOY.md`) becomes worth the
   money when originals outgrow the quota or visitors spread geographically. `Html::url()` is the
   single place a path is built, so swapping the origin is a one-function change.

**The exact next step:** implement item 1 for `hero_16x7` and `card_4x3` only, re-measure Home and an
album with the Performance-API method in the perf-baseline appendix, then re-run the layout verifier
— `srcset` changes which file loads, so §4.7 applies.

---

## 7. Gotchas and mistakes to avoid

1. **Never trust `$_FILES['file']['type']` or `['name']`.** Both are client strings. Use `finfo`
   *and* `getimagesize()` and require agreement (`src/Media/Pipeline.php:85-94`); build the stored
   path from the database id, never the name.
2. **Use `move_uploaded_file()`, not `copy()` or `rename()`, in a web request.** It re-checks the
   source really is this request's upload; our `copy()` branch exists only for
   `PHP_SAPI === 'cli'` (line 119).
3. **Handle EXIF orientation before measuring anything.** Skip `fixOrientation()` (lines 272-289)
   and every landscape phone photo arrives rotated — permanently, once re-encoded.
4. **Regenerating without bumping the version is invisible. This actually bit us.** With
   `immutable, max-age=31536000` a browser holding the old file **will not even send a request**:
   you re-crop, see the new image in a private window, and the owner swears nothing changed. Always
   pair regeneration with `version = version + 1` (`recrop.php:40`). The same trap bit us on
   stylesheets — a CSS file edited *after* its version bump is a file nobody downloads.
5. **Changing a crop can change the page layout** — wherever CSS derives one dimension from the
   image's aspect ratio, which is most of this site. Before touching a preset's `mode` or aspect,
   run the layout verifier. See §4.7.
6. **Watch PHP's `memory_limit`.** GD decodes to about `width × height × 4` bytes, so the 8000×8000
   cap already implies ~256 MB; if a host's limit is lower, a legitimate large upload crashes the
   request — lower the dimension cap rather than raising the limit blindly.
7. **Permissions: 0644 files, 0755/0775 directories. Never 777** — 777 means *anyone on the machine
   can write here*, and on shared hosting that includes other tenants.
8. **Keep the format whitelist frozen.** SVG re-opens stored XSS; GIF adds an animation-frame decode
   path nothing here handles.
9. **Uploaded originals still carry EXIF, including GPS.** Logged as **SEC-17** in `SECURITY.md` and
   a real privacy concern for photos of children: renditions are re-encoded and clean, but
   `/media/{id}/original.jpg` is public and ids are sequential. Do not build a feature that surfaces
   original URLs until that is addressed.
10. **Do not lazy-load your hero.** `loading="lazy"` on the LCP image delays the one thing the
    visitor is waiting for; pass `'eager' => true` for the first slide.

---

## 8. Try it yourself

Everything runs in Docker; there is no PHP or MySQL on the host. `./run.sh` builds the stack, waits
for the database, runs the seeder and prints the URLs — public site `http://localhost:8090/`, admin
`http://localhost:8090/admin/` (user `admin`, password `admin123`).

```bash
cd /home/aswin-25449/newDrive/Stjosephs_Website && ./run.sh
```

**1. Upload a photo.** Open any editable image slot (or the Media page) and choose Upload; use a
phone JPEG if you have one. For a `cover` preset, Cropper.js locks the crop box to the slot aspect.

**2. Watch the files appear.** The media root is `SJ_PUBLIC_ROOT . '/media'`
(`src/Media/Pipeline.php:17-20`), which inside the container is `/var/www/html/media`. You should see
`original.jpg`, `<preset>.jpg` and `<preset>.webp` — compare the sizes, then check the database
agrees:

```bash
docker compose exec web ls -la /var/www/html/media | tail -5      # find the newest id
docker compose exec web ls -la /var/www/html/media/<id>
docker compose exec db mysql -uroot -prootpw stjosephs \
  -e "SELECT image_id, preset_key, format, width, height, bytes FROM image_renditions ORDER BY image_id DESC LIMIT 4;"
```

**3. Re-crop and watch the version.** In the Media library press ✂️, drag the box elsewhere, save,
then view the rendered page source: that image's `?v=` has gone from `1` to `2`. Re-crop again, `3`.

**4. Prove the guard works.** Rename a small `.php` file to `evil.jpg` and upload it — you should get
*"File is not a valid image."* Then try an SVG: *"Only JPEG, PNG or WebP images are allowed."*

**5. See the legacy split.** On the Home page, View Source and compare a legacy hero photo (plain
`<img>`, no `width`/`height`) with an uploaded image (wrapped in `<picture>`, with dimensions) —
that difference is §4.7 in the wild.

---

## 9. Where to read more

**In this repo**

- [`../05-stage-e.md`](../05-stage-e.md) — the stage that built the media library, the crop UI and
  the delete guard, with the test that proved cropping works.
- [`../08-stage-h.md`](../08-stage-h.md) — the rendition backfill, the `<picture>` layout story and
  the performance numbers, alongside SEO in depth.
- [`../02-security.md`](../02-security.md) — the beginner walkthrough of the threat model this
  pipeline sits inside.
- [`../../../SECURITY.md`](../../../SECURITY.md) — normative. **SEC-10** (file upload attacks) and
  **SEC-17** (direct file serving from `/media`) govern this code, each with mitigations and an
  "Agent verification" recipe; its §4 checklist is mandatory before shipping any change here.
- [`../../../FEATURES_PLAN.md`](../../../FEATURES_PLAN.md) §2 — the original design: the step-by-step flow,
  the preset table with its reasoning, and the validation checklist the code implements.

**Outside**

- MDN, *Responsive images* — `srcset`, `sizes` and `<picture>`:
  <https://developer.mozilla.org/en-US/docs/Learn/HTML/Multimedia_and_embedding/Responsive_images>
- PHP manual, *Handling file uploads* — `$_FILES`, `is_uploaded_file()`, `move_uploaded_file()`:
  <https://www.php.net/manual/en/features.file-upload.php>
- OWASP, *File Upload Cheat Sheet* — the canonical checklist this pipeline follows:
  <https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html>
- web.dev, *Optimize Largest Contentful Paint* — why the hero gets `fetchpriority="high"` and
  everything else `loading="lazy"`: <https://web.dev/articles/optimize-lcp>
