# ADMIN_UI_DESIGN.md — St. Joseph's MHSS Hybrid Admin UI

> **Purpose:** the single source of truth for the admin panel's look, and a self-sufficient brief pack for **image-generation agents** — §4 has a master prompt plus a scene brief for **every** screen in §3, so the whole panel can be mocked. Extends the existing `sj-` design system in `public_html/admin/assets/panel.css`. Nothing here changes the public site's look; the admin is a professional navy/gold Segoe UI product. Theme tokens are shared with `PHASES.md` and `CLAUDE.md`.

---

## 1. Design Language

### 1.1 Color tokens

| Token | Hex | Use |
|---|---|---|
| `--blue` | `#2b4b8a` | Primary brand, active nav, links, focus borders |
| `--dark` | `#1a355d` | Headings, gradient end, sidebar top |
| `--sidebar-end` | `#12233f` | Sidebar gradient bottom |
| `--gold` | `#ffd700` | Accents **on navy only** (active rail, brand sub-line, cropper handles) |
| `--gold-ink` | `#8a6d00` | Gold-meaning text on white (WCAG-safe replacement for `--gold`) |
| `--gold-soft` | `#fff8dc` | Gold-tinted note backgrounds (border `#f0e2a0`, text `#6d5a0f`) |
| `--bg` | `#eef1f7` | App canvas |
| `--card` | `#ffffff` | Surfaces |
| `--text` | `#26303f` | Body text |
| `--mut` | `#7a8598` | Muted text ≥14px, placeholders, decorative |
| `--mut-strong` | `#5f6b80` | Muted text <14px (labels, table headers) — AA safe |
| `--line` | `#e2e7f0` | Borders, dividers |
| `--field-line` | `#d5d9e2` | Input borders |
| `--hover-fill` | `#e8ecf5` | Ghost buttons, chips, hover fills (`#dde3f0` on hover) |
| `--red` | `#c62828` | Danger text/actions; error bg `#fdecea`, error text `#b3261e` |
| `--green` | `#2e7d32` | Success; toast bg `#16321d`, toast text `#d6f5de` |
| `--warn` | `#b26a00` | Warnings; bg `#fff4e5` |
| `--info` | `#0b6bcb` | Info pills; bg `#e7f1fc` |
| `--focus-ring` | `#2b4b8a` on light / `#ffd700` on navy | 2px outline, 2px offset |
| `--disabled-fg` | `#aab3c2` | Disabled text; disabled fill `#f2f4f8` |
| `--scrim` | `rgba(10,18,33,.6)` | Modal overlay |
| `--overlay-outline` | `rgba(43,75,138,.85)` | Live-edit dashed outlines on public pages |

Navy gradient recipes: **sidebar** `linear-gradient(180deg,#1a355d,#12233f)`; **primary button / modal header** `linear-gradient(to right,#2b4b8a,#1a355d)`; **login backdrop** `linear-gradient(135deg,#2b4b8a,#1a355d)`.

### 1.2 Typography (`'Segoe UI', Arial, sans-serif`)

| Role | Size / weight / line-height |
|---|---|
| Page title (topbar h1) | 19px / 700 / 1.3, `--dark` |
| Section heading | 15.5px / 700 / 1.3, `--dark` |
| Body / inputs | 14px / 400 / 1.5 |
| Nav items, buttons | 13.5px / 600 |
| Secondary / hints | 12.5px / 400, `--mut` |
| Field labels, table headers | 11.5px / 700 / 1, UPPERCASE, letter-spacing .4px, `--mut-strong` |
| Badges / chips | 10.5–11.5px / 700 |
| Big stat numbers (dashboard) | **Fjalla One**, 34px / 400, `--dark` — the only brand-font moment in the admin |
| Brand sub-line ("Admin Panel") | 11px, `--gold` on navy |

### 1.3 Scales

- **Spacing:** 4 / 6 / 8 / 10 / 12 / 14 / 16 / 18 / 22 / 26 px (content pad 24×26; card pad 18–22; gaps 10–14).
- **Radii:** 6 (toolbar btns) · 7 (icon btns) · 8 (inputs sm, tabs) · **9 (buttons, inputs, thumbs)** · 10 (notes, portraits) · 12 (list rows) · **13 (cards)** · 14 (modals) · 999 (pills).
- **Shadows:** card-hover `0 8px 26px rgba(26,53,93,.14)`; modal `0 24px 80px rgba(0,0,0,.45)`; toast `0 8px 30px rgba(0,0,0,.35)`; FAB `0 6px 22px rgba(26,53,93,.35)`.
- **Icons:** standardize on inline SVG (Bootstrap Icons, 18px, `currentColor`, 22px slot); keep emoji as fallback. No icon fonts.
- **Motion:** micro 120ms; standard 150–180ms; modal-in 180ms `ease-out` (fade + 8px rise); drag settle 200ms `cubic-bezier(.2,.8,.2,1)`; skeleton shimmer 1.2s linear infinite. Respect `prefers-reduced-motion`.

---

## 2. Component Library

- **Sidebar** — 232px fixed, sticky full-height, sidebar gradient. Brand block: 40px logo + "St.Joseph's" 15px bold white + "Admin Panel" 11px gold; bottom border `rgba(255,255,255,.12)`. Nav links: 11×12px pad, radius 9, 13.5px, `#c8d3e8`; hover `rgba(255,255,255,.08)` white; **active: `--blue` fill, white 600, 3px gold left rail inset**. Group labels (CONTENT / MEDIA / SETTINGS) 10.5px uppercase `#8fa0c0`. Collapsible sub-groups (Content → 13 items) with chevron. Footer: "View website", "Log out". <860px: horizontal scroll strip; <576px: hamburger → slide-in drawer over scrim.
- **Topbar** — white, 1px `--line` bottom, 14×26px pad, sticky. Left: h1 + breadcrumb (12.5px `--mut`, "Content / Gallery / Annual Day"). Right: "Preview site ↗" ghost button, 32px avatar circle (navy bg, white initial) → dropdown (Change password / Log out).
- **Buttons** — base: inline-flex, radius 9, pad 10×18, 13.5px/600; sm 7×12/12.5px. *Primary*: navy gradient, white; hover `brightness(1.12)`; active translateY(1px). *Ghost*: `--hover-fill` fill, `--dark` text. *Danger*: white bg, 1.5px `--red` border, `--red` text; hover `#fdecea`; destructive-confirm variant solid `--red`. *Icon button* (`sj-ico`): 32×32 min (44×44 touch on mobile), `#eef1f7`, radius 7. Disabled: `--disabled-fg` on `#f2f4f8`. Loading: label → 16px white spinner.
- **Cards** — white, 1px `--line`, radius 13, pad 18; hover lift −1px + card shadow (nav cards only). *Stat card*: Fjalla One number + 11.5px uppercase label + chip.
- **Data table** — full-width; header per label spec; cells 9×18px pad, 13.5px, row top-border `--line`; hover `#f7f9fd`; last column right-aligned action icons on hover (always visible on touch). Optional leading drag-handle column (⋮⋮, `--mut`, cursor grab).
- **List row** (`sj-row`) — white, radius 12, pad 10×14, gap 14: [drag handle] [110×62 thumb radius 8] [title bold `--dark` + sub 12.5px `--mut`, ellipsized] [badges] [actions: edit ✏, ↑ ↓, visibility 👁/🚫, delete 🗑]. Hidden rows: 55% opacity + red "Hidden" badge. Drag state: shadow lift, 2px `--blue` border, drop slot = dashed `--blue` outline on `--hover-fill`.
- **Forms** — inputs 10×12px pad, 1.5px `--field-line`, radius 9; focus `--blue` border + ring. *Error*: `--red` border + 12.5px red message with ⚠. *Success*: `--green` border. Help text 12.5px `--mut`. Toggle switch: 40×22, off `#c3c9d6`, on `--blue`, 18px white knob.
- **Modal** — scrim; panel white radius 14, `min(600px,96vw)` (picker/cropper 860px), max-height 92vh; sticky gradient header, white 700 title + ✕ (34px); body 18×20; footer right-aligned [Ghost Cancel][Primary Confirm]. Fullscreen <860px. Esc/scrim-click close (blocked while dirty → confirm).
- **Confirmation dialog** — 400px modal, ⚠ 28px in `#fdecea` circle, bold question, consequence 12.5px `--mut`, [Cancel][red Delete]. Type-to-confirm for cascading deletes ("Delete year 2024…").
- **Toast** — bottom-center; green success / red error variants; 1.8s / 3.8s; `aria-live="polite"`.
- **Tabs** — pill tabs (equal-width, radius 8, `#edf0f7`, active `--blue` white) inside modals; underline tabs (2px `--blue` bottom, gold on navy) for page-level tabs.
- **Badges / status pills** — radius 999, pad 3×10, 11.5px/700: Live `#e6f4ea`/`--green` · Hidden `--red` solid white · Draft `--hover-fill`/`--dark` · Info `#e7f1fc`/`--info` · Warning `#fff4e5`/`--warn` · count chip `--hover-fill`/`--dark`.
- **Empty state** — centered in card: 40px muted icon, 15px bold `--dark` line, 12.5px `--mut` sub-line, primary CTA. E.g. "No slides yet / Add the first hero slide."
- **Skeletons** — grey `#e6eaf2` blocks matching row/card geometry (thumb rect + two text bars), shimmer; 3–5 per list.
- **Photo grid** — `repeat(auto-fill,minmax(140px,1fr))`, gap 10; tiles radius 9, img 4:3 cover, filename strip 10.5px; hover gold 2px border; selected: 2px `--blue` + check disc top-right; corner order-number disc (20px navy, white 11px). Drag-reorder with placeholder slot; ↑↓ fallback on touch.
- **Upload dropzone** — 2px dashed `#c3c9d6`, radius 13, min-height 170px, `#f7f9fd`; centered ⬆ + "Drag photos here or **browse**" + "JPEG/PNG/WebP · max 10 MB" 12.5px `--mut`. Dragover: `--blue` dashed + `--hover-fill`. Per-file progress rows: thumb, name, 4px `--blue` bar, state icon.
- **Image cropper modal** (Cropper.js) — 860px; dark `#12233f` canvas (~420px tall), aspect-locked crop box: white 1.5px border, **gold corner handles**, rule-of-thirds grid; below: preset pill ("Hero 16:7"), zoom slider, rotate ±90°, reset; footer [Cancel][Crop & Save]. Aspect from `image_presets` (16:7, 5:7, 4:3, 4:5, 16:9; free for "fit" presets).
- **Mini WYSIWYG** — wrapper 1.5px `--field-line` radius 9; toolbar `#f4f6fb` with 36×30 buttons: **B**, *I*, **Gold** (renders `--gold-ink`), ↵ line-break, ✕ clear-format; editable area min-height 130px, `.hl-gold` shown as `#b8860b` bold. Active-format button: `--blue` bg white glyph.
- **Search input** — 420px max, magnifier left, radius 9, 300ms debounce, ✕ clear.
- **Pagination** — "Load more" ghost button (grids) or numbered pager (32px squares, active navy) for the audit log.

---

## 3. Screen Inventory

**Shared "List Manager" pattern** (most content screens): topbar title → section head (lead paragraph left, primary "＋ Add …" right) → `sj-list` of drag-reorderable rows → row actions (edit modal from registry fields, toggle, delete-confirm). States: skeleton rows loading; empty state; error banner `#fdecea` with Retry. Mobile: thumbs 76×48, actions collapse to ⋯, drag → ↑↓.

Numbering below is stable — image-gen briefs in §4 reference these numbers.

### Auth
1. **Login** (`/admin/login.php`) — full-viewport 135° navy gradient; centered white card 400px, radius 14, pad 36×32: 76px crest, "Admin Panel" 20px `--dark`, "St.Joseph's MHSS, Ondipudur" 13px `--mut`, error banner, Username + Password (show/hide eye), full-width gradient "Sign in", "← Back to website". Lockout state: warning banner + countdown. Mobile: card 92vw.
2. **Forced password change** (`/admin/password.php`, also Settings → Security) — same backdrop/card; "Set a new password"; current + new + confirm; live 4-segment strength bar (red→`--warn`→`--green`) + checklist (≥10 chars, mixed case, number); primary "Save & continue"; no skip.

### Dashboard
3. **Dashboard** (`/admin/`) — greeting lead; **stat strip** (4 stat cards: Images, Pages, Albums, Last edit); nav-card grid (one card per content area with count chip); right rail (≥1100px): "Site health" card (storage bar, largest images, missing-alt count, last-backup age) + "Recent changes" list (last 8 audit entries). Empty: onboarding checklist card. Mobile: single column, rail below.

### Content
4. **Pages list** — table: Page title, URL slug, SEO status pill (Complete/Missing meta), Last updated, actions [Edit SEO][Open ↗][Live-edit ✏]. Search on top. Hub to every public page + its meta.
5. **Hero slides** — List Manager; rows show 16:7 thumb + caption title/sub; edit modal: image field (pick/upload→crop `hero_16x7`), caption title, caption text, button label/url (home only), active toggle.
6. **Principal & profiles** — form card: left 190×237 portrait + "Change photo" (`portrait_4x5` crop); right heading, name, message WYSIWYG; sticky save bar when dirty ("Unsaved changes · [Discard][Save]"). Tabs across the top if more profiles (President, Correspondent) exist.
7. **What's Unique** — List Manager (`feature_4x3` thumbs); lead note explains auto image-left/right alternation by position parity.
8. **News ticker** — List Manager, no thumbs; fields label + URL (validated); inline URL in the sub-line; blink accent preview.
9. **Update slides** — List Manager, `update_16x9` thumbs; fields title, subtitle, link, link label, image, active.
10. **Top marks** — year cards (`sj-yearcard`): grey header strip `#f7f9fd` with 🗓 year, Hidden badge, [＋ Add topper][toggle][delete-cascade confirm]; body = table grouped 12th→11th→10th: Std chip, Rank, Student, Marks x/y, actions incl. ↑↓ within standard. "＋ Add year" primary top-right. Empty year: hint row.
11. **Achievements & awards** — underline tabs "Achievements | Awards"; each a List Manager (rows: `feature_4x3` thumb, title, subtext).
12. **Academies** — grid of 18 academy cards (image, name, Live pill) → detail screen: banner/card/content headings, hero+bg image fields, intro/body WYSIWYG (Bold/Gold), photo-collection manager ("Manage photos (n)" → photo-grid modal, `content_slide` preset).
13. **Sports** — List Manager (9 rows: name, training time, details WYSIWYG, `card_4x3` image, active); accordion-id note.
14. **School sections** (KG/Primary/High/Higher-Sec) — tabs per section; within: hero/bg image field, intro form, **Timeline editor** (vertical month rows: month pill, time label, events-per-line textarea, drag-reorder, add-month) and **Event cards** List Manager (`feature_4x3`). Higher-Sec extra note: groups + marks shown.
15. **Infrastructure facilities** — List Manager of facility rows (name, blurb, photo-collection count chip, active); "＋ Add facility" (creatable); row edit → detail modal with WYSIWYG + Manage photos (`content_slide`) + bg image (`bg_wide`). Note: nav anchors generated from slug order.
16. **Gallery** — 3 levels. *Albums*: card grid (cover 4:3, title, years count, photo count, Live pill) + "＋ Album". *Album → years*: breadcrumb, year rows (cover thumb, year label, count, reorder). *Year → photos*: **Photo manager** — toolbar (＋ Add photos, Select mode, count), photo grid with drag-reorder + order discs; Select mode: checkboxes, bottom bulk bar (navy, "4 selected · [Set cover][Delete][Cancel]"). Add → dropzone modal → multi-crop queue (`gallery_tile`). Empty: dropzone fills the grid.
16b. **Staffs & testimonials** — two stacked List Managers on one screen: *Staff blocks* (intro strip + the two story cards, `feature_4x3` images, WYSIWYG) and *Home testimonials* (rows: portrait thumb, name, "Alumni", two-paragraph quote WYSIWYG, reorder, active). Serves PHASES C3.

### Media Library
17. **Library grid** (full page, `/admin/media.php`) — toolbar: search, preset-filter dropdown, "Unused only" toggle, ⬆ Upload primary; photo grid (filename strips); Load more. Click tile → **detail drawer** from the right (380px, white, shadow): large preview, filename, dimensions, size, renditions list (WebP/JPEG per preset with sizes), **"Used in" usage list** (linked entity + page), alt-text field, [Replace][Delete — disabled with "used in X" tooltip if linked]. Mobile: drawer = fullscreen modal.
18. **Upload + crop flow** — modal: dropzone → per-file crop step (preset select first if ambiguous) → progress → success toast + grid prepend.

### Settings
19. **Site settings** — form card: school name, address, phone, email (mailto must match display — SEC/bug 12), social URLs, footer text. Sticky save bar.
20. **SEO / meta per page** — from Pages list; form: title tag (char counter 60), meta description (counter 160, turns `--warn`/`--red` over limit), OG image picker; Google-style SERP live preview.
21. **Security** — embedded change-password (screen 2), active session info, login history table (time, IP, result pill — from `audit_log`), future-ready "Users & roles" card showing the single admin row with an Owner pill and a disabled "＋ Invite user" (tooltip "Available in a future update").
22. **Backups (status-only)** — cards: last DB export time, last media-archive time, media folder size, retention note; **no download button** (backups run via mPanel cron to a non-web dir — SEC-20); gold-soft instructions note for restore drill.
23. **Audit log** — table: timestamp, user, action pill (create `--green` / update `--info` / delete `--red`), entity, summary; date filter + pagination.

### Live-edit overlay (public pages)
24. **Admin bar / FAB** — logged-in admin on any public page sees a bottom-right **FAB** (56px navy gradient circle, white ✏, FAB shadow). Tap → pill toolbar (radius 999, navy, white): **Edit-mode toggle** (gold when ON) · "Dashboard" · "Log out". Edit OFF = page pixel-identical to public; logged-out visitors get zero admin markup/JS.
25. **Edit mode ON** — editable text blocks get 1.5px dashed `--overlay-outline` on hover/focus + a 26px white ✏ disc (navy glyph) top-right; single-image slots get a 📷 disc; list containers get a floating "＋ Add" pill; repeating items get a mini cluster (↑ ↓ ✕) top-right on hover. Carousels/grids get a solid navy "Manage photos (n)" pill bottom-right → the same photo-grid modal as screen 16. Text ✏ → inline input matching the site's own font/size, or modal WYSIWYG for `*_html`. All overlay modals live in a self-contained `sjov-` CSS namespace so site CSS can't bleed. Saving flashes a 2px `--green` outline + toast. Mobile: FAB persists; discs always visible (no hover) at 44px; reorder via ↑↓ only.

---

## 4. Image-Generation Briefs

### Master prompt template
> High-fidelity UI design mockup of **[SCREEN]** for a school website admin panel, rendered as a crisp flat screenshot, no device frame, no hands. Style: clean modern SaaS dashboard, light theme, generous whitespace, subtle 1px borders `#e2e7f0`, soft rounded corners (12–14px cards, 9px buttons). Palette: royal navy `#2b4b8a`, deep navy `#1a355d`, gold accent `#ffd700` (only on navy surfaces), app background `#eef1f7`, white cards, charcoal text `#26303f`, muted grey-blue `#7a8598`. Typography: Segoe UI–like humanist sans, 14px body, bold 19px page titles, uppercase 11px field labels. Primary buttons use a left-to-right navy gradient `#2b4b8a→#1a355d` with white bold labels. Left sidebar 232px wide with a vertical navy gradient `#1a355d→#12233f`, light blue-grey nav text, active item filled `#2b4b8a` with a thin gold left rail, school crest at top with "St.Joseph's / Admin Panel" (Admin Panel in gold). Canvas **1920×1200** desktop *(or 390×844 mobile: sidebar behind a hamburger, content full-width, 44px touch targets)*. Content: **[SCREEN-SPECIFIC SCENE]**. Realistic Indian school content (student names, events like "Annual Day 2025", "EXPRESSIONZ"). No lorem ipsum, no watermark, sharp text.

### Per-screen scenes
Drop each line into the master template's `[SCREEN-SPECIFIC SCENE]` (and `[SCREEN]` = the bold name). Screens 1, 3, 16, 17, 10 and 25 also have expanded example paragraphs below.

- **1 · Login** — see expanded brief A.
- **2 · Forced password change** — the same navy-gradient backdrop and white 400px card as login, titled "Set a new password", with three stacked labelled fields (Current / New / Confirm password), a 4-segment strength bar half-filled amber, a small checklist with two green ticks and one grey dot ("At least 10 characters ✓ / Upper &amp; lowercase ✓ / A number ·"), and a full-width navy-gradient "Save & continue" button; no cancel link.
- **3 · Dashboard** — see expanded brief B.
- **4 · Pages list** — sidebar active "Pages"; a white data table filling the content area with columns PAGE / URL / SEO / UPDATED and a right actions column; rows like "Home /index.php · green 'Complete' pill", "Infrastructure /infrastructure.php · amber 'Missing meta' pill", "Gallery /gallery.php"; each row ends with small ghost icon buttons (SEO, open-in-new, pencil); a search field sits above the table right-aligned.
- **5 · Hero slides** — sidebar active "Hero Carousel"; a "＋ Add slide" navy button top-right; a vertical list of 7 draggable rows, each with a 16:7 thumbnail of a school photo, a bold caption title ("St.Joseph's") and grey subtitle, a green "Live" pill, a 6-dot drag handle on the left and pencil/up/down/eye/trash icons on the right; one row is lifted mid-drag with a blue border and a dashed placeholder gap behind it.
- **6 · Principal & profiles** — sidebar active "Principal"; a wide white form card split into a left column with a 190×237 portrait of a priest-principal and a "Change photo" ghost button, and a right column with labelled fields "HEADING (Principal)", "NAME (Rev.Fr.Kirubakaranathan)" and a rich-text "MESSAGE" area showing a mini toolbar (B, I, gold-A, line-break) over two paragraphs; a sticky bottom bar reads "Unsaved changes · Discard · Save".
- **7 · What's Unique** — sidebar active "What's Unique"; a two-row List Manager, each row a `feature_4x3` thumbnail (a classroom photo, a language-class photo), titles "Extended School Concept (ESC)" and "The Language Academies", a grey note at the top explaining "Images alternate left/right automatically", drag handles and edit/eye/trash actions.
- **8 · News ticker** — sidebar active "News Ticker"; a compact List Manager with no thumbnails, three rows each showing a bold label ("Mini Auditorium Inauguration is live..!!") and a grey YouTube URL beneath, a blinking gold dot icon at the left of each label, "＋ Add link" top-right.
- **9 · Update slides** — sidebar active "Update Slides"; three rows with 16:9 thumbnails (event videos), titles "EXPRESSIONZ 2026 / ExpressionZ", "KG Welcome", "Celebrations", a grey "View More →" link preview, reorder handles and actions.
- **10 · Top marks** — see expanded brief E.
- **11 · Achievements & awards** — sidebar active "Achievements"; two underline tabs "Achievements | Awards" (Achievements active, gold underline on the tab); a List Manager below with trophy-photo thumbnails and rows like "Zonal Level Athletics Meet · Overall Winner - 2023", "District Level Athletics Meet · Overall Runners - 2023"; "＋ Add achievement" top-right.
- **12 · Academies (grid + detail)** — sidebar active "Academies"; a responsive grid of 18 white academy cards, each a photo, a bold name ("Academy of Tamil", "Academy of Science", "Band", "NCC") and a small green "Live" pill; one card is hovered showing a faint lift; a breadcrumb "Content / Academies" sits in the topbar. (Detail variant: a single academy edit screen with hero image field, an intro rich-text area with a gold-highlighted phrase, and a "Manage photos (3)" navy pill opening onto a small carousel thumbnail row.)
- **13 · Sports** — sidebar active "Sports"; a List Manager of 9 rows, each with a sport photo thumbnail, bold name ("Athletics", "Badminton", "Hand Ball"), a grey training-time line ("Training Time 3.30–5.00 p.m"), and an expandable "details" hint; reorder and edit actions; "＋ Add sport" top-right.
- **14 · School sections** — sidebar active "School Sections"; four pill tabs "KG · Primary · High · Higher-Sec" (KG active); below, an intro form and a **timeline editor**: a vertical list of month rows each with a navy month pill ("JUNE"), a grey time label ("2024 - present"), a multi-line events textarea, and a drag handle; an "＋ Add month" ghost button; further down an "Event cards" List Manager with 4-3 thumbnails ("Orange Day", "Graduation Day").
- **15 · Infrastructure facilities** — sidebar active "Infrastructure"; a List Manager of 15 facility rows (name + one-line blurb + a "Photos (3)" count chip), "＋ Add facility" top-right; one row's edit modal is partially visible showing a rich-text description and a "Manage photos" grid; a grey note reads "Nav anchors are generated from order".
- **16 · Gallery photo manager** — see expanded brief C.
- **16b · Staffs & testimonials** — sidebar active "Staffs"; two stacked List-Manager sections with headings "Staff blocks" and "Home testimonials"; the testimonials section shows three rows, each a small round portrait, a bold alumnus name ("Kishore N.E — Alumni"), a two-line grey quote preview, reorder handles and edit/eye/trash actions; "＋ Add testimonial" on that section's header.
- **17 · Media library** — see expanded brief D.
- **18 · Upload + crop flow** — a modal over a dimmed library: left half a dark `#12233f` crop canvas with a school photo and an aspect-locked crop box (white border, gold corner handles, thirds grid), right half a small preview and a "Preset: Card 4:3" pill, a zoom slider and rotate buttons; footer "Cancel · Crop & Save"; behind it, a faint progress row list.
- **19 · Site settings** — sidebar active "Site Settings"; a white form card with labelled fields School name, Address, Phone, Email, Facebook URL, YouTube URL, Footer text, each filled with realistic school values; a sticky "Save changes" bar at the bottom.
- **20 · SEO / meta per page** — a form for "Home" page SEO: a "Title tag" input with a "52 / 60" counter, a "Meta description" textarea with a "138 / 160" counter, an "OG image" thumbnail picker, and below a Google-style search-result preview card showing the blue title, green URL and grey description.
- **21 · Security** — sidebar active "Security"; an embedded change-password form card, an "Active session" info line, a "Login history" table (timestamp, IP, green "Success"/red "Failed" pills), and a muted "Users & roles" card showing one row "admin — Owner" with a disabled "＋ Invite user" button.
- **22 · Backups (status-only)** — sidebar active "Backups"; three stat-style cards "Last DB backup — 2h ago", "Last media archive — 6 days ago", "Media folder — 1.8 GB", and a gold-soft note box explaining backups run automatically via the host and how to request a restore; deliberately no download button.
- **23 · Audit log** — sidebar active "Audit Log"; a dense table with columns TIME / USER / ACTION / ENTITY / SUMMARY, rows with coloured action pills ("create" green, "update" blue, "delete" red) like "update · hero_slide · Slide #2 caption", a date-range filter above and a numbered pager below.
- **24 · Admin FAB (public page)** — the real public school home page (navy hero carousel, gold-accent headline, white sections) with only a single bottom-right floating navy circular button bearing a white pencil icon; nothing else changed — the page looks exactly like the live site.
- **25 · Live-edit overlay** — see expanded brief F.

### Expanded example briefs

**A · Login** — Full-screen 135° diagonal gradient from royal navy `#2b4b8a` to deep navy `#1a355d`; centered white card 400px wide, 14px radius, deep soft shadow, containing a circular school crest (gold and navy emblem), the title "Admin Panel" in dark navy, subtitle "St.Joseph's MHSS, Ondipudur" in small grey, two labelled inputs "USERNAME" and "PASSWORD" with 11px uppercase grey labels and light-bordered rounded fields, a full-width navy-gradient "Sign in" button, and a small "← Back to website" link in royal blue beneath; the password field shows a small eye icon; serene, institutional, premium. 1920×1200.

**B · Dashboard** — Standard sidebar (active "Dashboard", blue fill + gold rail) beside a white topbar reading "Dashboard" with a "Preview site ↗" ghost pill and a navy circular avatar "A"; content on `#eef1f7`: a welcome sentence in muted grey, four white stat cards with large Fjalla-One navy numbers ("512 Images", "42 Pages", "12 Albums", "Edited 2h ago") and small uppercase labels; a 3-column grid of white navigation cards each with an icon, bold title ("Hero Carousel", "Top Marks", "Gallery", "News Ticker"…), one-line grey description and a rounded count chip ("5 slides"); a right rail holds a "Site health" card (storage bar) and a "Recent changes" list of timestamped edits. 1920×1200.

**C · Gallery photo manager** — Sidebar (active "Gallery"), topbar breadcrumb "Content / Gallery / Annual Day / 2025"; a toolbar row with a navy-gradient "＋ Add photos" button, a "Select" ghost button and grey "34 photos"; a responsive grid of ~20 school-event photo tiles (stage performances, award ceremonies, kids in uniform) with 9px corners, thin grey filename strips, small navy order-number discs top-left; one tile is mid-drag, lifted with a shadow and blue border, leaving a dashed blue placeholder; a navy bulk-action bar docked at the bottom reads "4 selected · Set cover · Delete · Cancel" (Delete in soft red). 1920×1200.

**D · Media library with detail drawer** — Sidebar (active "Media Library"); toolbar with a rounded "Search images…" field, a "Preset: All shapes" dropdown, an "Unused only" toggle and a navy-gradient "⬆ Upload image" button; a dense photo grid of mixed school imagery fills the left two-thirds; a 380px white drawer is open at the right with a large image preview, filename "annual-day-chief-guest.jpg", metadata "1920×1280 · 412 KB", a small renditions table ("hero_16x7 · WebP · 96 KB"), a boxed "Used in" list ("Hero slide #2 — Home", "Album: Annual Day 2025"), an "ALT TEXT" input, and a ghost "Replace" plus a disabled red-outline "Delete" with tooltip "In use on 2 pages". 1920×1200.

**E · Top Marks editor** — Sidebar (active "Top Marks"); a lead sentence and a navy-gradient "＋ Add year" button top-right; two stacked white year cards (13px radius): each with a pale header strip, calendar icon, bold "2024–2025", a small "＋ Add topper" light button and eye/trash icons; inside, a clean table with uppercase grey headers STD / RANK / STUDENT / MARKS and rows like a rounded "12th" chip, "Rank 1", bold "Harini S", "594 / 600" with right-aligned pencil/up/down/trash icons; the second year card is faded to 60% opacity with a red "Hidden" pill in its header. 1920×1200.

**F · Live-edit overlay on public Home** — The actual public school home page (navy hero carousel with a school photo and a gold-highlighted headline, white content sections, condensed display headings) shown in edit mode: editable headline and paragraph blocks outlined with thin dashed royal-blue borders and small white circular pencil badges at their top-right corners; the principal's portrait has a white circular camera badge; the hero carousel shows a solid navy pill "Manage photos (5)" bottom-right; a floating navy pill toolbar bottom-right has a gold-highlighted toggle "Edit mode ON" plus "Dashboard" and "Log out" in white; otherwise the page looks exactly like a real school website. 1920×1200.

---

## 5. Accessibility

**Contrast rulings (WCAG AA, computed):**
- `#ffd700` on `#1a355d` ≈ **8.7:1** (AAA), on `#2b4b8a` ≈ **6.0:1** (AA). **On white ≈ 1.4:1 — FAIL: never use gold text/icons on white**; use `--gold-ink #8a6d00` (≈4.9:1, AA). `.hl-gold` display color `#b8860b` (3.3:1) only for bold ≥18.66px text or admin preview contexts.
- White on `#2b4b8a` ≈ 8.5:1, on `#1a355d` ≈ 12.3:1 — pass. Sidebar link `#c8d3e8` on the gradient ≈ 8:1 — pass.
- `--mut #7a8598` on white ≈ 3.7:1 — **fails <14px text**; all sub-14px labels/table headers use `--mut-strong #5f6b80` (≈5.4:1).
- `--red #c62828` on white ≈ 5.9:1, `--green #2e7d32` ≈ 5.1:1 — pass.

**Focus:** visible `outline: 2px solid` (`--blue` on light, `--gold` on navy) with `outline-offset: 2px` on every interactive element; never `outline:none` without a replacement. **Touch:** targets ≥44×44px on <860px (padding, not size illusion). **Keyboard:** modals trap focus, Esc closes, focus returns to opener; lists support ↑↓ reorder buttons (drag is enhancement only); WYSIWYG Ctrl+B; tables/grids roving tabindex; toasts `aria-live="polite"`, errors `role="alert"`; overlay pencil/camera discs are real `<button>`s with `aria-label` ("Edit welcome message"). Drag handles `aria-hidden` with adjacent accessible move buttons.

---

### Critical files
- `public_html/admin/assets/panel.css` — the `sj-` design system to extend
- `public_html/admin/assets/panel.js` — existing modal/WYSIWYG/picker behaviors
- `public_html/admin/_layout.php` — sidebar/topbar shell
- `public_html/admin/section.php` — per-section editors
- `public_html/_libs/edit.php` — overlay `data-edit-*` emitters

---

# v2 — "Professional" revamp (N7, 2026-08-15) — THE CURRENT SPEC

Owner brief: high-class feel, real transitions and button effects, perfect
editing controls, **no emojis — professional icons**, a live view of site
availability/speed/memory, and an area to create admin accounts.

## 1. Principles
1. **Same brand, higher finish.** Navy `#2b4b8a`/`#1a355d`, gold accents,
   Segoe UI (+ Fjalla One only for big numbers) stay per CLAUDE.md. What
   changes is craft: spacing rhythm, borders, depth, motion.
2. **Motion is feedback, not decoration.** 140–220 ms ease-out; things move
   ≤4 px. Buttons lift on hover and press on click; modals fade+scale from
   .96; toasts slide; rows raise; numbers count up. Everything honours
   `prefers-reduced-motion` (all transitions collapse to none).
3. **Icons, not emoji.** A single inline-SVG icon set (Feather-style, 24-box,
   `stroke: currentColor`, width 2, round caps) emitted by `sj_icon()` (PHP)
   and `SJUI.icon()` (JS). Inline SVG over PNG on purpose: crisp at every
   DPI, inherits color (states for free), zero extra requests, CSP-clean.
   Every icon-only control carries `aria-label` + `title`.
4. **No hook renames.** All `sj-*` classes and `data-*` contracts that
   panel.js/sj-ui.js rely on keep their names — v2 restyles, never rewires.

## 2. Tokens (authoritative)
```
--blue #2b4b8a  --dark #1a355d  --navy-ink #12233f  --gold #ffd700
--bg #eef1f7    --card #fff     --text #26303f      --mut #64748b
--line #e2e7f0  --line-strong #cbd5e1
--ok #2e7d32    --warn #b26a00  --red #c62828
--ring 0 0 0 3px rgba(43,75,138,.25)          (focus ring, every control)
--shadow-1 0 1px 2px rgba(18,35,63,.06)       (resting cards)
--shadow-2 0 8px 24px rgba(18,35,63,.12)      (hover / modals)
--r-sm 8px  --r-md 12px  --r-lg 16px          (radii)
--t-fast 140ms  --t-med 200ms  ease           (motion)
```

## 3. Components
- **Buttons** `.sj-btn`: primary = navy gradient, hover `translateY(-1px)` +
  shadow-2, active `translateY(0) scale(.98)`; ghost = white w/ border;
  danger = red fill on confirm surfaces. Icon buttons `.sj-ico`: 34px round
  square, transparent → tinted hover, icon inherits color; danger hover red.
- **Inputs** (text/textarea/select): 40 px, `--line-strong` border, white bg,
  focus = navy border + `--ring`; labels 11 px uppercase `--mut`; invalid =
  red border + hint. Bool fields render as a **toggle switch** (the checkbox
  stays the real input — pure CSS skin).
- **List rows** `.sj-row`: white card rows, 12 px radius, hover = raise 1 px +
  shadow + action buttons fade from 55%→100% opacity; drag = tilt 1° and
  shadow-2; hidden rows keep the "Hidden" badge (slate).
- **Modals**: backdrop `rgba(18,35,63,.55)` + 4 px blur; panel `--r-lg`,
  shadow-2, enters fade+scale(.96→1) 180 ms; titles are plain text + icon.
- **Toasts**: bottom-right stack, icon by type (check/alert), slide-up in,
  auto-dismiss 2.6 s, reduced-motion = opacity only.
- **Image picker/upload**: tile grid, hover = zoom 1.03 + navy overlay +
  check; dropzone with dashed border that ignites on dragover; crop stage
  unchanged (Cropper.js) inside the v2 modal.
- **Tabs** `.sj-tabs`: pill buttons; active = navy fill. Sidebar: active item
  = navy pill + 3 px gold left bar; icons 18 px at 70% → 100% on hover.

## 4. Screens
- **Dashboard** = 4 zones: (1) greeting header (name, date, quick actions);
  (2) **Site vitals** — live tiles fed by `?r=stats` polled every 20 s by
  `dashboard.js`: Availability (endpoint reachability + HTTP status), Speed
  (measured round-trip ms + 20-sample sparkline), Server memory (used/total
  bar, from `/proc/meminfo` when readable), PHP peak, Disk (conic donut),
  Database + media size, OPcache hit-rate, last backup age. Server-rendered
  initial values; skeleton shimmer while polling; count-up on change.
  (3) content cards (existing links, now icon + count-up); (4) **Recent
  activity** — last 8 `audit_log` rows as a friendly feed.
- **Admin accounts** (new section `admins`, owners only): list rows (avatar
  initial, username, role chip, last login, locked badge) + actions —
  create (modal: username, display name, role; server generates a one-time
  temp password shown ONCE with a copy button, `must_change_password=1`),
  reset password, unlock, change role, delete. Guards: never yourself,
  never the last owner. Editors don't see the section and the API 403s.
- **Login / password / recover**: same card language — focus rings, lifted
  button, no emoji.

## 5. Security posture (unchanged, extended)
`admin_users` stays OUT of the registry; accounts go through a dedicated
`?r=admins` endpoint (auth + CSRF via `_bootstrap`, plus `role='owner'`
gate). Passwords are always server-generated temporaries — never chosen or
echoed after first display, never audited. Every action audited
(`admin.create/reset/unlock/role/delete`, detail = target username).
`?r=stats` is GET, admin-only, returns sizes/counters only — no paths, no
versions of anything an attacker could map, no secrets. It is also a
**passive** endpoint (`SJ_PASSIVE_REQUEST`): its poll must never renew the
session, or an unattended open dashboard would stay signed in forever
(SECURITY.md SEC-07 regression note). Any future polling endpoint must
declare the same constant. Deleted admins are
cut off on their NEXT request: `_bootstrap`/`_layout` re-verify the session's
admin row each request (also live-updates role changes).
