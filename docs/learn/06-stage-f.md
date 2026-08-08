# 6 — What We Built: Stage F (editing the site *on* the site)

> Continues [`05-stage-e.md`](05-stage-e.md). Stage E put every piece of content
> into the database with an admin panel to edit it. **Stage F adds the second half
> of the "hybrid admin" promise: a live-edit overlay** — log in, open any page of
> the real website, click the text you want to change, and change it right there.
>
> Status: **✅ COMPLETE** — O1 (admin bar + inline text editing) and O2 (add /
> delete / reorder items and swap photos, all from the live page).

---

## The idea in one sentence

Every page has been quietly emitting invisible markers since Stage E — but **only
when an admin is in edit mode**. Stage F ships the JavaScript that turns those
markers into an editing UI, on top of the exact same secure API the admin panel
already uses.

## How a page knows what's editable

When (and only when) an admin has switched on edit mode, the server decorates the
page with data attributes:

```html
<h3 data-edit-field="profile:1:heading" data-edit-type="text">Principal</h3>
<div data-edit-item="hero_slide:4" data-edit-flags="od">…a slide…</div>
<div data-edit-add='{"entity":"ticker_item", …fields…}'>…a list…</div>
<img … data-edit-img="hero_slide:4:image_id:hero_16x7">
```

Read them like an address: *entity : row id : column*. The overlay JavaScript
(`js/admin.js`) walks the page, finds these, and attaches the editing behaviors.
For a **visitor** none of this exists — we grep-verified that a logged-out request
contains **zero** overlay bytes: no attributes, no admin script, no admin CSS, no
CSRF token. There is nothing to discover, let alone attack.

## O1 — the admin bar and inline text editing

A slim navy bar sits fixed at the bottom of the screen for logged-in admins (it's
`position:fixed`, so it changes nothing about the page layout — the visual freeze
holds). It shows one of two states:

- **"Viewing as a visitor"** — the page is exactly what the public sees.
- **"✏️ Edit mode"** — outlined text and photos are clickable.

The toggle is a **POST form with a CSRF token** (never a link — links that change
state are how CSRF attacks happen), it's written to the audit log, and the
"return to this page" redirect only accepts same-site paths (no open-redirect
hole). While edit mode is on, the page also sends `X-Frame-Options: DENY` so an
editing session can never be loaded inside someone else's iframe.

Editing itself:

- **Plain text** (a heading, a caption): click → the element becomes editable
  in place (`contenteditable=plaintext-only` — pasting can't smuggle HTML in).
  `Enter` or clicking away saves; `Esc` restores the original.
- **Rich text** (a message with bold/gold): click → a small floating toolbar
  appears — **B**, *I*, Gold highlight, clear formatting, Save. Behind the scenes
  it produces the same whitelisted HTML the panel does, and the server sanitizes
  it *again* on save — then the overlay swaps in the server's cleaned version, so
  what you see is what actually got stored.
- **Choice fields** (like a topper's standard) become a dropdown.

Every save goes through the **same front controller** as the panel: session,
CSRF header, registry validation, sanitizer, audit log. The overlay adds zero new
write paths.

## O2 — items and photos, without leaving the page

First, a refactor the plan called for: the panel's modal system (the image
picker, the add/edit form builder, the rich editor, the toast, the API client)
was **extracted into one shared file** — `admin/assets/sj-ui.js` — used by *both*
the panel and the overlay. One implementation, two consumers; we re-tested the
whole panel afterwards to prove nothing broke.

With that shared core, the overlay gains:

- **Hover chips on repeating items** (slides, testimonials, timeline months,
  toppers…): the item's name plus ↑ ↓ to reorder and 🗑 to delete. Reordering is
  scoped to the item's own list — the toppers of 2023 can't get tangled with the
  toppers of 2024.
- **＋ Add buttons on list containers**: opens the same form the panel uses
  (fields come from the registry, so they're always in sync), creates the row,
  reloads.
- **📷 badges on photos**: click → the library/upload picker (with the M3 crop
  step) → the photo is swapped.

**Acceptance, tested on the real pages:** we added a board-exam topper from the
live Home page's ＋ button and watched it appear in the marquee; we swapped the
hero banner's first photo via 📷 without ever opening the panel; we deleted the
test topper from its hover chip. All three worked first try — because they reuse
machinery that was already battle-tested in Stages E/M.

## The bug sweep that came first

Before building, we swept the whole site: every asset URL on nine representative
pages (160 unique files — all exist), the PHP error log (clean), the browser
console (clean). One real gap surfaced and was fixed: the overlay's "Add" metadata
was missing the *multiline* flag the panel gained in C4 — without it, adding a
timeline month from the overlay would have squeezed multi-line events into a
single-line box. Parity restored.

---

## Where the project stands

| Stage | Status |
|---|---|
| A — Security · B — Platform · C — Front-end · D — Speed & deploy · E — Content | ✅ done |
| **F — Live-edit overlay** | **✅ COMPLETE — O1 (bar + inline text) + O2 (items & photos)** |
| G — Revamp completion (CSS consolidation, validation, the deferred typos) | next |
| H — Performance, SEO, ops (image backfill, sitemap, backups, monitoring) | upcoming |

### Try it yourself
1. `./run.sh`, log in at `http://localhost:8090/admin/`, then open
   `http://localhost:8090/` — notice the navy bar at the bottom.
2. Press **✏️ Edit this page**. Click the Principal's name, type, click away —
   refresh in another (logged-out) browser window to see it live for visitors.
3. Hover a testimonial card → use the chip's ↑ to move it first.
4. Click the 📷 on the hero banner and pick any library photo. (Then put
   everything back — or edit it again, it's your CMS now.)
