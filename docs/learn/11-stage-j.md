# Stage J — the "Global Campus" public redesign (J0–J6)

The owner commissioned a full visual redesign of the public site on 2026-08-23
("theme, fonts, cards — everything looks old; make it a world-class school
website"), which formally retired the CLAUDE.md visual freeze. Direction,
depth, elements and rollout were chosen WITH the owner (style tiles +
questions): **A — Global Campus**, all pages re-skinned + Home/shell
rearchitected, ALL signature elements, staged shell-first.

## What shipped, phase by phase

- **J0** — `PUBLIC_UI_DESIGN.md` (the new rendering authority) and the
  CLAUDE.md **visual-fidelity rule** that replaced the freeze.
- **J1** — foundations on every page: Manrope replaced League Spartan (which
  mostly never rendered — the `"LeagueSpartan"` typo fell back to system
  sans), tokens.css v2 (paper/gold-soft palette, `--sj-veil`, type scale,
  radii/shadows/motion tokens), the new `css/site.css` component layer
  (`.sj-btn`, `.sj-card`, `.sj-sec-head` gold bar, section bands, hero
  geometry) and the motion runtime in site.js (`sjRevealIO` one-time
  IntersectionObserver reveals, `sjCounters`, `sjNavScroll`, `sjMegaHover`,
  `sjHeroKeys`) — plus the site's FIRST `prefers-reduced-motion` kill-switch.
- **J2** — the chrome: fixed navy-brand navbar with grouped mega panels
  (static hrefs, zero queries) + offcanvas drawer, transparent-over-hero via
  `$sjNavOverlay`, re-skinned footer/admissions band, the floating
  Admissions + WhatsApp quick actions (new `whatsapp_number` setting — blank
  hides the button), back-to-top moved bottom-left.
- **J3** — Home rearchitected around the new **`views/partials/hero.php`**
  (one parameterized carousel: N6 16:9 geometry, brand veil, Ken Burns,
  Fjalla title + Dancing Script gold flourish captions, ed_* hooks intact);
  the stat band became settings-driven (`home_stat1..4_value/_label`); the
  testimonial veil was de-triplicated into one CSS recipe over `--sj-tm-bg`;
  admin panel previews updated in lockstep. Also: the dead-IO safety valve
  (broken IntersectionObserver must never leave content invisible).
- **J4** — the three generic templates (sections / academies / albums,
  ~34 URLs) adopted hero.php and token sheets. Inner `content_slide`
  carousels deliberately kept their own markup: they render image_links
  rows, not hero_slide entities, so the shared partial would emit wrong
  edit hooks.
- **J5** — the eight bespoke hub pages restyled; blur-siblings hover retired;
  gallery's cross-fade + data-bg lazy slider kept.
- **J6** — dead code removed (home-bs4-remnants.css, partials/navbar.css,
  jumbotron.css, four per-page reveal js files, the legacy sjReveal/
  sjBlurCards helpers, 19 controllers' `'scripts'` entries), straggler
  partial sheets tokened (groups, gallery-slider, marks-scroll), docs made
  true.

## The five lessons worth remembering

1. **Design within the data.** The redesign never changed an image preset or
   an `ed_*` hook — that is what let 42 pages change look without a single
   admin-panel or content change.
2. **One recipe, one place.** The veil, the gold bar, the button and the card
   each live once (tokens/site.css). The old sheets had the navy hardcoded
   54 times; a brand change is now a one-line token edit.
3. **Entities decide markup sharing.** hero.php could absorb six hero_slide
   carousels but NOT the image_links strips — sharing markup across different
   entities would have corrupted the edit-overlay contract.
4. **Progressive enhancement needs a floor.** IntersectionObserver "always
   works" — until a webview never composites. The 4s safety valve costs
   nothing in real browsers and prevents invisible content everywhere else.
5. **The asset-version rule bites every stage.** Six bumps in one stage;
   the one time an edit landed after a bump, the rule forced a seventh.
