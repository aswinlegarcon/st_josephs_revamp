# PUBLIC_UI_DESIGN.md — the public website design system ("Global Campus", Stage J)

Canonical spec for how the PUBLIC site looks and moves, from Stage J onward. Peer of
`ADMIN_UI_DESIGN.md` (which governs the admin panel). Owner-approved 2026-08-23 —
direction "A — Global Campus", depth "restyle all + rearchitect Home/shell", ALL
signature elements, staged shell-first rollout. This document supersedes the
visual-freeze baseline (git `6bd0d11`) as the rendering authority; see CLAUDE.md.

## 0. Principles

1. **International-school standard.** The references are SSVM World School / Reeds
   World School / global-school patterns: sticky nav with an admissions CTA,
   full-bleed heroes, stat counters, soft-shadow cards, generous alternating
   section bands, conservative one-time scroll reveals. Professional and calm —
   never gimmicky.
2. **Same school, new clothes.** The palette IS the school's: navy + gold on white.
   Fjalla One stays the display voice (shared with the admin "Prospectus" theme).
   Dancing Script survives only as a sparse accent (one flourish line per hero,
   nothing else).
3. **Design within the data.** Image preset shapes are immutable (§6). Components
   are recipes over the existing DB content — captions, cards, albums — so the
   admin panel keeps editing everything without change.
4. **Motion is felt, not seen.** One-time staggered reveals, slow Ken Burns, eased
   counters, a navbar that settles on scroll. Every animation dies under
   `prefers-reduced-motion: reduce` (global kill-switch in site.css).
5. **Every ms matters.** The redesign must not regress the F-series wins: lazy
   images, renditions, ≤12 queries/page, zero-CLS geometry, immutable-cache asset
   versioning.

## 1. Palette (tokens.css v2)

| Token | Value | Role |
|---|---|---|
| `--sj-navy` | `#2b4b8a` | Primary brand; buttons, links, nav settle color |
| `--sj-navy-dark` | `#1a355d` | Depth end of gradients; footer canvas; hero scrim end |
| `--sj-gold` | `#ffd700` | Precision accent ON DARK ONLY: section-head bar, chips, CTA fill, hover ticks |
| `--sj-gold-ink` | `#8a6d00` | Gold-MEANING text on white (WCAG). Never `--sj-gold` text on white |
| `--sj-gold-soft` | `#fdf3cf` | Pale gold chip/tint background |
| `--sj-paper` | `#faf8f4` | Alternating warm section band (matches admin Prospectus canvas) |
| `--sj-ink` | `#26303f` | Body text |
| `--sj-ink-soft` | `#5f6b80` | Secondary text |
| `--sj-line` | `#e9e4d9` | Hairlines on paper; `#e5e9f1` (`--sj-line-cool`) on white |
| `--sj-veil` | `linear-gradient(rgba(43,75,138,.55), rgba(26,53,93,.75))` | THE scrim — heroes, testimonial cards, photo overlays. Single source; admin `--veil` mirrors it |
| `--sj-grad-brand` | `linear-gradient(to right, navy, navy-dark)` | Band/button gradient (kept from v1) |

Section rhythm: sections alternate `#fff` and `--sj-paper`. Navy-dark bands
(footer, admissions band, stat band) anchor the page. `--sj-maroon` (firebrick)
is legacy-only and dies with the last old sheet in J6.

## 2. Typography (v1.1 — Stage K "bold & clean")

Loaded via one Google Fonts request (public pages have no CSP restriction;
the admin self-hosts separately): **Fjalla One** (400) and **Manrope**
(400/500/600/700/800). NOTHING ELSE — the owner retired the Dancing Script
accents in Stage K ("looks childish"); emphasis is now gold Fjalla at heading
size (`.home-text span`) or uppercase letterspaced Manrope (hero sub-lines,
banner subtitles, nav links).

| Var | Family | Use |
|---|---|---|
| `--sj-font-head` | Fjalla One | h1–h6, display numbers, nav brand, gold in-heading emphasis |
| `--sj-font-body` | Manrope | Everything else — body, nav links, caption sub-lines, forms |

Scale (fluid, `clamp()`): display `clamp(34px, 5vw, 56px)`; h2 section titles
`clamp(26px, 3.2vw, 38px)`; h3 `22px`; lead `18px`; body `16px`/1.7; small `14px`;
label/eyebrow `12px` uppercase +2px letter-spacing Manrope 700 (`--sj-gold-ink`
on light, `--sj-gold` on dark).

## 3. Space, radius, elevation

- Section padding `--sj-sec-pad: clamp(64px, 9vw, 120px)` top/bottom.
- Container: Bootstrap `.container` (max 1320px at xxl); text-measure blocks cap at `72ch`.
- Radius: `--sj-r-card: 16px` (cards, media), `--sj-r-btn: 10px`, `--sj-r-chip: 999px`.
- Shadows: `--sj-shadow-1: 0 6px 18px rgba(18,35,63,.08)` (rest),
  `--sj-shadow-2: 0 16px 40px rgba(18,35,63,.16)` (hover lift). No harsh glows;
  the old `0 0 10px firebrick` recipes are retired.

## 4. Motion

All in site.css/site.js; every rule inside `@media (prefers-reduced-motion: no-preference)`
or gated by `sjMotionOK()`. A global `reduce` block zeroes animation/transition
durations and scroll-behavior.

| Pattern | Spec |
|---|---|
| Scroll reveal | `.sj-reveal` starts `opacity:0 translateY(26px)`; IntersectionObserver adds `.sj-in` ONCE (unobserve after fire — no re-animation, unlike old sjReveal); 0.7s `cubic-bezier(.2,.7,.2,1)`; stagger via `data-sj-delay` (80ms steps) |
| Ken Burns | `.carousel-item.active img` only: `sj-kenburns` 7s ease-out forwards, scale 1→1.06 with a slight translate; paused on non-active slides |
| Counters | `sjCounters()`: IO-triggered rAF count-up ~1.6s ease-out; parses leading integer, re-appends `+`/`%` suffix; reduced-motion → final value instantly |
| Navbar | transparent→solid at 40px scroll (rAF-throttled `sjNavScroll`); 0.25s background/shadow ease |
| Cards | hover: lift `translateY(-6px)` + `--sj-shadow-2`, media `img` scale 1.06 0.5s; focus-visible gets the same affordance via outline |
| Buttons | 0.2s ease background/transform; press `scale(.98)` |
| Smooth scroll | `html { scroll-behavior: smooth }` (no-preference only) |

## 5. Components (site.css)

- **`.sj-btn`** — the ONE button system (retires the four legacy recipes).
  Base: Manrope 600 15px, `--sj-r-btn`, 12px 26px. Variants: `.sj-btn--navy`
  (navy gradient fill, white text, hover brighten+lift), `.sj-btn--gold` (gold
  fill, navy-dark text — the CTA; hover deepen), `.sj-btn--ghost` (1.5px border,
  context color; on dark = white/gold border).
- **`.sj-sec-head`** — section header: optional uppercase eyebrow, Fjalla title,
  44×4px gold bar (`::after`), optional lead. `.sj-sec-head--center` variant.
  Mirrors the admin `.sj-page-title` bar — one brand across both surfaces.
- **`.sj-card`** — white card, `--sj-r-card`, `--sj-shadow-1`, hairline border;
  `.sj-card-media` aspect-box (`--4x3`/`--16x9`) with `object-fit:cover` img zoom
  on hover; `.sj-card-body` (Fjalla title 20px navy-dark, Manrope sub). Grids via
  Bootstrap columns; blur-siblings hover is retired.
- **Hero (`views/partials/hero.php`)** — the single carousel partial, THREE
  variants since Stage K (owner UX decision: full-screen carousels hid the
  content):
  - `hero` (HOME only): box CAPPED at `min(56.25vw, 62vh)` so the next section
    always peeks; Ken Burns on the active slide; a bouncing scroll cue
    (`.sj-hero-cue`, reduced-motion aware); caption = Fjalla white title
    `clamp(30px,4.5vw,56px)` + a clean Manrope sub-line + `.sj-btn--gold`.
  - `banner` (ALL inner pages): slim `clamp(240px, 36vh, 400px)` page-title
    strip (300px fixed ≤768px) — every admin slide still cross-fades, but no
    Ken Burns, no indicators/controls. Heading style is DISTINCT from Home
    (K5): uppercase letterspaced Fjalla `clamp(22px,3vw,36px)` over a small
    centered gold rule + an uppercase Manrope sub-label. Because the overlay
    nav covers the banner top, the caption centers in the VISIBLE area:
    `top: calc(50% + 55px)` desktop / `+ 32px` mobile (half the nav height) —
    keep these in sync if the nav heights ever change.
  - `strip`: plain mid-page content carousel (no veil/caption).
  Geometry uniformity (the N6 guarantee) holds in every variant: one shared
  box per carousel, `object-fit:cover`, photos never grow the page. Scrim
  `::before` = `--sj-veil` z-index:1; captions z-index:10 (tokens.css patch).
- **Navbar (`views/partials/navbar.php`)** — two-tier international (Stage K):
  a solid navy-dark utility strip (36px: phone · email from settings · gold
  uppercase ADMISSIONS ENQUIRY chip; hidden <768px) above the white main bar —
  crest + Fjalla wordmark left, UPPERCASE Manrope 13px/700/1.2px links right.
  No hover-underline animation: hover is a gold-ink color shift; ONLY the
  active page carries a thin gold underline (server-side SCRIPT_NAME match;
  groups light for their children, mega items highlight gold-soft). Mega
  panels: Bootstrap Dropdown restyled (white, 14px radius, shadow-2),
  hover-intent ≥992px. Mobile <992px: Offcanvas drawer with accordion groups
  + gold CTA. The fixed element is the `.sj-navhead` wrapper; `$sjNavOverlay`
  pages render the MAIN bar transparent (strip stays navy) until 40px scroll.
- **Stat band** — navy-dark full-bleed strip on Home: 4 items, Fjalla
  `clamp(34px,4vw,52px)` gold numbers (`.counter[data-target]`), Manrope white
  labels, hairline separators. Values/labels from settings `home_stat{1..4}_value/_label`.
- **Floating CTA (`views/partials/floating-cta.php`)** — bottom-right stack on
  every page: "Admissions" pill (`.sj-btn--gold`, → `/index.php#contact`) +
  WhatsApp circle (only when setting `whatsapp_number` is non-empty; link
  `https://wa.me/<digits>`). 44px+ targets, aria-labels, hidden on print.
  Scroll-up button moves bottom-LEFT.
- **Admissions band (jumbotron v2)** — navy gradient panel with gold top bar,
  Fjalla heading, Manrope sub, `.sj-btn--gold`; same three settings keys.
- **Footer** — navy-dark canvas, gold hairline top, crest + one-line mission,
  the same four columns + settings keys, white/40% meta bottom bar.
- **Testimonials** — the site recipe (navy veil + gold Fjalla name + white rule +
  quote + white justified body) kept, modernized: 16px radius, shadow-1, soft
  lift on hover. Background = `background: var(--sj-veil), var(--sj-tm-bg) center/cover`;
  static defaults set `--sj-tm-bg` per card class; a chosen photo sets only
  `style="--sj-tm-bg:url(…)"` (de-triplication — one recipe, three consumers).
- **Ticker / marks marquee / updates carousel / gallery slider** — re-skinned in
  place (tokens, radius, veil); geometry and the data-bg lazy pattern unchanged.

## 6. Hard constraints (verbatim from the codebase — do not violate)

1. **Image presets are immutable.** hero_16x7 (KEY misnamed — actually 16:9
   1920×1080), update_16x9, card_4x3, feature_4x3, portrait_4x5, bg_wide,
   content_slide/gallery_full (fit). Changing an aspect means re-cropping all
   content — never do it inside Stage J.
2. **`img_tag()` contract** (src/Media/Html.php): legacy images render as bare
   `<img>` (no `<picture>`, no width/height); non-legacy get `<picture>`+dims.
   First hero slide `eager`.
3. **Query budget** ≤12/page (Home exactly 12). Settings are one cached query —
   new keys are free; the mega menu uses static hrefs only.
4. **ed_* edit-overlay hooks** on hero slides/testimonials/cards must survive
   markup changes (admins live-edit through them).
5. **`SJ_ASSET_VER`** bump on every css/js change; re-bump if assets change after
   a bump (immutable caching).
6. **Admin lockstep:** panel.css preview recipes (`--veil`, `.sj-prev-cap-*`,
   `.sj-prev-tm*`) mirror public recipes — update them in the SAME phase the
   public recipe changes.
7. **Legacy URLs** all keep working; controllers/data flow untouched by Stage J.
8. **Section caps (Stage K):** entities with a fixed layout capacity carry
   `max_count` in the registry (testimonial = 3). Both Add affordances hide at
   the cap and `item.php` create refuses authoritatively.

## 7. Breakpoints & mobile (K13)

New code uses the Bootstrap ladder: 576 / 768 / 992 / 1200. The single
load-bearing exception is the hero geometry boundary at **901px** (≥901
aspect-ratio, <901 fixed heights) — it lives ONLY in the shared hero rules in
site.css. Legacy sheets' ad-hoc ladders die as each sheet is rewritten.

**7.1 The ≥992 guarantee.** Desktop (≥992px) is the pixel-identical contract:
mobile work must never change it. K13's landing-page mobile redesign lands
entirely at **768 / 576 (+ a 400 micro-tier)**; `clamp()` covers 360–430
fluidly between them. Existing ad-hoc points (700/800/900/991) survive until
each sheet is rewritten — new work does NOT consolidate them, because the 901
hero boundary and the shipped 769–900 tablet band are already tuned.

**7.2 Mobile type ramp (360–430 sweet spot, continuous at 769).** Maxes equal
the shipped desktop floors so nothing jumps crossing 768↔769: hero title
`clamp(26px,7vw,40px)`; section titles `clamp(24px,4.5vw,30px)`; sub-heads
`clamp(20px,5.2vw,24px)`; stat number `clamp(30px,5.5vw,34px)`; welcome
`clamp(22px,5.5vw,28px)`; body ~15px/1.7; small 12.5–13.5px.

**7.3 Ergonomics.** Tap targets ≥44px (nav toggler, FAB pill, carousel
buttons, testimonial arrows, submit). Carousel indicators get a real 32px-tall
hit area via `border-block: 14px solid transparent` + **`background-clip:
padding-box`** — the base indicator rule's `background:` shorthand (and
`.active`'s) resets the clip to border-box, so any block that adds the
transparent border MUST re-declare `padding-box` AND stay last in its sheet
(equal specificity → source order decides). Viewport-height units always ship
a `vh` fallback line immediately before the `svh` line.

**7.4 Home mobile recipes (K13).**
- **Hero** ≤768: `height: min(560px, 62svh)` (vh fallback first); caption
  centered in the visible area `top: calc(50% + 32px)` (half the 64px mobile
  nav — keep in sync if nav heights change, same rule as the banner caption);
  arrows + scroll cue hidden (swipe is native); 32px indicator hit areas.
- **Campus cards** ≤991: `.card-deck .card-img-top { aspect-ratio: 3/4;
  height: auto; }` — `height:auto` is mandatory (it defeats both the desktop
  `height:100%` and the legacy `width=200 height=500` attrs; without it
  `aspect-ratio` is ignored). ≤768 each card `width: min(420px,100%)`.
- **Stat band** ≤991: `.fun-facts .container` → `display:grid;
  grid-template-columns:1fr 1fr` (2×2) with `rgba(255,255,255,.14)` hairline
  dividers (row-1 `border-bottom`, odd cells `border-right`).
- **What's-Unique** ≤768: image always above text via `order:-1` on
  **both** `> .newtemp-about-image` and `> picture` (uploads are
  `<picture>`-wrapped); image pinned `aspect-ratio:4/3` (= the `feature_4x3`
  preset).
- **Updates carousel** ≤768: `height: min(380px, 56svh)`, caption centered,
  ≥44px button + indicator hit areas (420px stays for 769–900).
- **Testimonials** ≤576: full-width card, arrows become a centered 44px pair
  BELOW the card (`top:auto; bottom:0; left/right: calc(50% ∓ 56px)`; `:hover`
  transform reset). Side arrows stay ≥577. JS untouched (901-gated `visible()`).
- **Contact** ≤991: `#contact { scroll-margin-top: calc(var(--sj-nav-h)+12px) }`.
  ≤576: reCAPTCHA layout box pinned `234×60` (the inline `scale(.77)` shrinks
  the visual to ~180 but not the layout box — pin it so it stops overflowing);
  submit full-width min-48px; ≤400 card padding shaved for 320px screens.
- **Rhythm** ≤576: `body.index { --sj-sec-pad: clamp(44px,11vw,64px) }`
  (home-only; `body.index` scope keeps it off other pages).

**7.5 Admin lockstep.** The `.sj-prev-*` preview cards render at fixed widths;
K13 is viewport-scoped geometry/spacing only (no veil/font/aspect/colour recipe
changes) → **outside the lockstep contract; panel.css unchanged.**

## 8. Rollout map (PHASES.md Stage J)

J0 docs → J1 foundation (fonts/tokens/site.css/site.js — all pages, type-only
visible change) → J2 chrome (nav/footer/band/FAB) → J3 Home (+hero partial,
stat band, admin lockstep) → J4 generic families (~34 URLs) → J5 bespoke hubs →
J6 sweep/audits/docs. Un-migrated pages between phases may show the J1
foundation type change only — no other drift.
