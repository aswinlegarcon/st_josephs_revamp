# FEATURES_PLAN.md — New features: image upload pipeline + keyboard controls

> ⚠️ **Superseded scheduling (2026-08-01).** The master roadmap is now **`PHASES.md`** (with `SECURITY.md`, `ADMIN_UI_DESIGN.md`, `CLAUDE.md`). This file is still the accurate **technical spec for the image pipeline (§2), size presets, and keyboard features (§3–4)**, but scheduling has moved: the upload/crop pipeline lands across phases M2/M3 and the legacy backfill in F2; the hero (←/→) and gallery-lightbox (←/→/Esc) keyboard features land in phase **C9**, not a generic "phase 4". Contact-form/EmailJS handling is now hardened server-side (see `SECURITY.md` SEC-23 / `PHASES.md` C1). Read `PHASES.md` first.
>
> Companion documents: `WEBSITE_CONTEXT.md` (site inventory) and `DYNAMIC_MIGRATION_PLAN.md` (DB schema, admin panel, rollout). The upload pipeline below is invoked by the admin panel's `admin/api/upload.php` endpoint and uses the `images` / `image_presets` / `image_renditions` tables defined there (§3.2). The keyboard features land in phase 4 of the rollout.

---

## 1. Feature list

| # | Feature | Where it lands |
|---|---|---|
| 1 | Keyboard navigation for the **home hero carousel** (← / →) | `_templates/carousel.php` script |
| 2 | Keyboard navigation for the **gallery lightbox** (← / → / Esc) | `_renderers/gallery-album.php` lightbox script (one place serves all 10 album pages) |
| 3 | **Auto-crop + auto-compress** of every uploaded image to the exact size its slot needs | `admin/api/upload.php` + `_libs/media.php` (GD) |

Feature 3 directly solves the reported problem: *"some images that are not cropped in a size are making some card larger and some smaller."* After migration, every image slot has a **preset** (fixed pixel box + aspect ratio), and every upload is normalized to it — so cards, tiles, and slides are always uniform, whether or not the admin crops manually.

---

## 2. Image upload pipeline (server-side, PHP GD)

### 2.1 Flow — `admin/api/upload.php`

```
multipart POST: file, preset, [crop_rect "x,y,w,h"], [alt]
   │
   1. VALIDATE (checklist §2.3) ── reject → {ok:false, error}
   2. EXIF orientation fix (JPEG): exif_read_data() → imagerotate/flip
      (phone photos arrive upright before any measuring/cropping)
   3. INSERT images row → {id};  save untouched original →
      /media/{id}/original.{ext}   (kept forever; admin-only "download original")
   4. CROP DECISION
      • preset.mode = 'cover':
          – no crop_rect  → AUTO: largest centered rectangle matching
            preset aspect_w:aspect_h  (center-crop)
          – crop_rect sent → MANUAL: use it (bounds-validated), store in
            images.crop_rect so a later "re-crop" reopens Cropper.js
            with the same rect
      • preset.mode = 'fit': no cropping — resize within the box,
        aspect preserved (for object-fit:contain slots)
   5. RESIZE down to preset.max_w × max_h (imagecopyresampled; NEVER upscale)
   6. ENCODE  → /media/{id}/{preset}.jpg   progressive JPEG, preset.quality (≈82)
              → /media/{id}/{preset}.webp  quality 80  (skipped silently when
                GD lacks imagewebp — feature-detected)
      record both in image_renditions
   7. RETURN {image_id, url, thumb}
```

- **Minimal compression, as requested:** quality 82 JPEG / 80 WebP is visually lossless for photos while typically cutting file size 60–80 % versus camera originals; progressive encoding + `loading="lazy"` improve perceived load further.
- **Re-encoding always happens** (step 6) even if the source is already small — this is also the security measure that strips embedded payloads/metadata (see §2.3).
- **Lazy rendition generation:** attaching an existing library image (or one of the 478 legacy `photos/` images) to a slot whose preset has no rendition yet makes `admin/api/link.php` generate it on the spot from `original.*` (or from the `legacy_path` file). Legacy files themselves are **never modified** — by default legacy images keep serving as-is via `legacy_path` until the admin replaces or re-slots them.
- **Re-crop:** the media library and every image slot offer "Re-crop" — reopens Cropper.js with the stored `crop_rect`, regenerates renditions in place, bumps `images.version` → all URLs get a fresh `?v=` (cache-bust).

### 2.2 Size presets (`image_presets` seed)

Derived from the documented CSS of the current site (`WEBSITE_CONTEXT.md` §9): gallery tiles render at 250×350, heroes are full-width ~550–600 px tall bands, content carousels use `object-fit`-style contained images ~500 px tall, cards are ~4:3.

| preset_key | Box (px) | Aspect | Mode | Quality | Used by (slot) |
|---|---|---|---|---|---|
| `hero_16x7` | 1920×840 | 16:7 | cover | 80 | every page-top hero slide (home + all `.abt-carousel` heroes) |
| `gallery_tile` | 500×700 | 5:7 | cover | 82 | album photo-grid tiles (2× retina of the 250×350 display size) |
| `gallery_full` | 1600×1200 | free | fit | 82 | lightbox large view (generated alongside `gallery_tile` for every gallery upload) |
| `card_4x3` | 800×600 | 4:3 | cover | 82 | academy cards, sport cards, album thumbnails, grade-level cards |
| `content_slide` | 1200×900 | free | **fit** | 82 | academy / facility / section inner-carousel slides (contained images — cropping would defeat them) |
| `portrait_4x5` | 800×1000 | 4:5 | cover | 82 | principal portrait (and future president) |
| `feature_4x3` | 1000×750 | 4:3 | cover | 82 | What's-Unique images, section event cards, achievement/award photos |
| `update_16x9` | 1280×720 | 16:9 | cover | 82 | New-Updates slides; gal-slider highlight tiles |
| `bg_wide` | 1920×1080 | 16:9 | cover | 74 | academy/facility section background photos (heavily overlaid — lower quality is invisible) |

The entity **registry** (`_libs/registry.php`) maps every editable image slot → its preset, so the upload modal and Cropper.js always know the target shape, and the API can reject a mismatched preset key.

### 2.3 Upload validation checklist (server-side, before any processing)

- [ ] `is_uploaded_file()` + `UPLOAD_ERR_OK`; size ≤ 10 MB.
- [ ] MIME sniffed via `finfo` **and** confirmed by `getimagesize()` — both must agree; whitelist `image/jpeg`, `image/png`, `image/webp` (client filename/extension ignored).
- [ ] Dimensions ≤ 8000×8000 and a GD memory estimate guard (`w × h × 4 bytes` vs `memory_limit`) before decoding.
- [ ] Storage paths derive **only** from `images.id` (`/media/{id}/…`); the client filename is stored (sanitized) in `original_name` purely for admin display.
- [ ] Always re-encode through GD — uploaded bytes are never served; EXIF applied (orientation) then discarded.
- [ ] `crop_rect` numeric, non-negative, within source bounds; `preset` must exist in `image_presets`.
- [ ] `media/.htaccess`: `php_flag engine off` + deny `*.php` (defence in depth).
- [ ] Rendition files written 0644; any failure rolls back the `images` row and deletes partial files.

### 2.4 Client side — upload modal with optional manual crop

- Vendored **Cropper.js** (`admin/assets/cropper/`, no CDN) inside the upload modal.
- The crop box aspect is **locked to the slot's preset** (free for `fit`-mode presets, which skip cropping).
- "**Auto (center)**" is preselected — admins who don't care get the automatic center-crop; dragging the box switches to manual and posts `crop_rect`.
- Live preview at the slot's real display size, so the admin sees exactly what the card/tile will show.
- Alt-text field on the same modal (stored in `images.alt_text`).

### 2.5 Serving markup

- `img_tag($img, $preset, $attrs)` emits for uploads:
  ```html
  <picture>
    <source type="image/webp" srcset="/media/{id}/{preset}.webp?v={version}">
    <img src="/media/{id}/{preset}.jpg?v={version}" width="…" height="…"
         alt="…" loading="lazy" class="…">
  </picture>
  ```
  and for legacy rows a plain `<img src="{legacy_path}" loading="lazy" …>`. Explicit `width`/`height` prevent layout shift; `loading="lazy"` is applied everywhere except the first hero slide (kept eager so the banner paints immediately).
- `bg_style($img, $preset)` returns an inline `style="background-image:url(…jpg?v=…)"` for the academy/facility background slots (CSS backgrounds can't use `<picture>`, so they use the JPEG rendition).

---

## 3. Keyboard events — home hero carousel

**Requirement:** ← previous slide, → next slide on `index.php`.

**Implementation** (appended to the script in `_templates/carousel.php`):

```js
document.addEventListener('keydown', function (e) {
  if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
  if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;           // modifiers held
  if (e.target.closest('input, textarea, select, [contenteditable]')) return; // typing somewhere
  if (document.body.classList.contains('sj-edit-mode')) return;          // admin edit mode needs arrows
  var $hero = $('.carousel-main .carousel');                             // scoped — see note
  if (!$hero.length) return;
  $hero.carousel(e.key === 'ArrowRight' ? 'next' : 'prev');
});
```

Design notes:

- **Scoping is the critical detail:** `index.php` has **two** carousels — the hero (`#carouselExampleIndicators` inside the unique `.carousel-main` wrapper) and the New-Updates carousel (`#carouselExampleIndicators2` inside `.update-carousel`). Selecting via `.carousel-main .carousel` guarantees the arrows only ever drive the hero.
- Driven through the **jQuery/Bootstrap-4 API** (`.carousel('next')`), which is guaranteed available because `navbar.php` loads jQuery + Bootstrap 4.5.3 on every page.
- Guards: no hijacking while the user types in the enquiry form, while a modifier chord is pressed, or while the admin overlay is active (the overlay sets `sj-edit-mode` on `<body>` and needs arrow keys for text inputs).
- The carousel keeps auto-advancing (2 s interval) exactly as today; a keyboard action triggers Bootstrap's normal slide behaviour.

---

## 4. Keyboard events — gallery lightbox

**Requirement:** after clicking an image — → next, ← previous, Esc close.

**Implementation** (in the shared lightbox script inside `_renderers/gallery-album.php` — one change covers all 10 album pages):

```js
document.addEventListener('keydown', function (e) {
  if (!popup.classList.contains('open')) return;   // only while the lightbox is visible
  switch (e.key) {
    case 'ArrowRight': e.preventDefault(); showNext(); break;   // same fn as the ▶ button
    case 'ArrowLeft':  e.preventDefault(); showPrev(); break;   // same fn as the ◀ button
    case 'Escape':                          closePopup(); break; // same fn as the × button
  }
});
```

Design notes:

- Reuses the **exact code paths** of the existing on-screen buttons (next/prev/close), so keyboard and click behaviour can never diverge.
- `preventDefault()` on the arrows stops the page behind the lightbox from scrolling horizontally/vertically while navigating.
- **Bonus fix while touching this code:** today's prev/next walk a flat index across *all* grid images, including the ones hidden by the year filter — so on two-year albums the lightbox can show photos from the non-selected year. The rebuilt navigation iterates **only the active year's image list** (the DB-driven renderer knows the year → images mapping directly).
- Existing **touch-swipe** support is kept unchanged; keyboard is additive.
- The `Esc` handler is registered alongside, not replacing, the × button and backdrop-click close.

---

## 5. Acceptance criteria / verification

**Pipeline**
- Upload a 6000×4000 8 MB phone photo to a `card_4x3` slot → stored rendition is exactly 800×600, JPEG < ~150 KB, WebP smaller still, original preserved under `/media/{id}/original.jpg`; the card renders the same height as every other card (the original complaint is gone).
- Upload a portrait photo to `gallery_tile` without cropping → automatic center-crop; with a manual Cropper.js rect → that rect is honored and stored; "Re-crop" reopens with the same rect and the public URL gains a new `?v=`.
- Upload a `.php` renamed to `.jpg`, a 60 MB file, and a 12000-px image → all rejected with clear errors; nothing written to disk or DB.
- On a host without `imagewebp` → upload still succeeds with JPEG only; `<picture>` degrades gracefully (source simply absent).

**Home hero keyboard**
- On `index.php`: → advances, ← goes back; keys pressed while focused in the enquiry form's inputs do nothing; the New-Updates carousel never moves on arrow keys; with the admin edit overlay active, arrows type normally in fields.

**Gallery lightbox keyboard**
- Open any image on a two-year album (e.g. `gal-sports.php`): → / ← navigate only within the selected year's photos, Esc closes; background page does not scroll while navigating; swipe still works on touch devices; on-screen ◀ ▶ × buttons behave identically to their key equivalents.
