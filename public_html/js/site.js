// site.js — the ONE shared scroll-reveal + card-blur implementation (R2).
// The original site shipped 23 copies of the same two patterns (16 inline
// <script> blocks and 7 per-page js files, differing only in selector list,
// reveal threshold, and whether the first check runs before any scroll).
// Each page now registers its shipped parameters through these two helpers,
// so the behavior — including the per-page thresholds — is unchanged.
//
// Loaded by the shell BEFORE the per-page $scripts files; inline calls from
// page content run inside a DOMContentLoaded listener, so this file is
// always defined first.

// Adds/removes .active on every element matching `selector` as it enters or
// leaves the viewport. `revealPoint` is the shipped per-page threshold (px
// from the bottom edge). Pass initNow=true where the original also ran the
// check on DOMContentLoaded (not just on the first scroll).
function sjReveal(selector, revealPoint, initNow) {
  function tick() {
    var els = document.querySelectorAll(selector);
    var windowHeight = window.innerHeight;
    els.forEach(function (el) {
      if (el.getBoundingClientRect().top < windowHeight - revealPoint) {
        el.classList.add('active');
      } else {
        el.classList.remove('active');
      }
    });
  }
  window.addEventListener('scroll', tick);
  if (initNow) {
    if (document.readyState === 'loading') {
      window.addEventListener('DOMContentLoaded', tick);
    } else {
      tick();
    }
  }
}

// Hovering one card blurs its siblings (sports / co-curriculum / achievements
// grids). `cardSelector` matches the individual card columns.
// LEGACY (pre-Stage-J pages only) — retired page by page in J4/J5, deleted in J6.
function sjBlurCards(cardSelector) {
  var cards = document.querySelectorAll(cardSelector);
  cards.forEach(function (card) {
    card.addEventListener('mouseover', function () {
      cards.forEach(function (sibling) {
        if (sibling !== card) sibling.classList.add('blur');
      });
    });
    card.addEventListener('mouseout', function () {
      cards.forEach(function (sibling) {
        sibling.classList.remove('blur');
      });
    });
  });
}

/* ================== Stage J motion runtime (PUBLIC_UI_DESIGN.md §4) ==================
   All animation entry points respect prefers-reduced-motion; the CSS side has
   a matching global kill-switch in site.css. */

// True when the visitor has NOT asked for reduced motion.
function sjMotionOK() {
  return !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
}

// One-time staggered scroll reveals. Marks each matched element with .sj-in
// the first time ~15% of it enters the viewport, then unobserves — unlike the
// legacy sjReveal, elements never re-animate on re-entry. Stagger with
// data-sj-delay="80" (ms). No IO support / reduced motion → shown immediately.
function sjRevealIO(selector) {
  var els = document.querySelectorAll(selector || '.sj-reveal');
  if (!els.length) return;
  if (!('IntersectionObserver' in window) || !sjMotionOK()) {
    els.forEach(function (el) { el.classList.add('sj-in'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (!en.isIntersecting) return;
      var el = en.target;
      var delay = parseInt(el.getAttribute('data-sj-delay') || '0', 10);
      if (delay > 0) el.style.transitionDelay = (delay / 1000) + 's';
      el.classList.add('sj-in');
      io.unobserve(el);
    });
  }, { threshold: 0.15 });
  els.forEach(function (el) { io.observe(el); });
}

// Count-up stats: <span class="counter" data-target="2200+"></span>.
// Parses the leading integer and re-appends any suffix (+, %, …); eased rAF
// count over ~1.6s, fired once when 40% visible. Reduced motion or no IO →
// the final value is set instantly.
function sjCounters(selector) {
  var els = document.querySelectorAll(selector || '.counter[data-target]');
  if (!els.length) return;
  function put(el, n, suffix) { el.textContent = n.toLocaleString('en-IN') + suffix; }
  function animate(el) {
    var raw = String(el.getAttribute('data-target') || '').trim();
    var m = raw.match(/^(\d+)(.*)$/);
    if (!m) { el.textContent = raw; return; }
    var target = parseInt(m[1], 10);
    var suffix = m[2] || '';
    if (!sjMotionOK() || !target) { put(el, target, suffix); return; }
    var t0 = null, dur = 1600;
    function step(ts) {
      if (t0 === null) t0 = ts;
      var p = Math.min((ts - t0) / dur, 1);
      var eased = 1 - Math.pow(1 - p, 3); // ease-out cubic
      put(el, Math.round(target * eased), suffix);
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }
  if (!('IntersectionObserver' in window)) { els.forEach(animate); return; }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (!en.isIntersecting) return;
      io.unobserve(en.target);
      animate(en.target);
    });
  }, { threshold: 0.4 });
  els.forEach(function (el) { io.observe(el); });
}

// Navbar settle: toggles .sj-scrolled on the .sj-nav navbar past 40px scroll
// (rAF-throttled, passive). Wired up by the J2 navbar; no-op until it exists.
function sjNavScroll() {
  var nav = document.querySelector('.navbar.sj-nav');
  if (!nav) return;
  var ticking = false;
  function apply() {
    nav.classList.toggle('sj-scrolled', window.scrollY > 40);
    ticking = false;
  }
  window.addEventListener('scroll', function () {
    if (!ticking) { ticking = true; requestAnimationFrame(apply); }
  }, { passive: true });
  apply();
}

// Desktop hover-intent for the mega menu (≥992px, fine pointers only):
// pointerenter opens the Bootstrap dropdown, pointerleave closes it after a
// short grace period. Click/Esc/keyboard (Bootstrap's own handling) stays the
// baseline, so touch and keyboard users lose nothing.
function sjMegaHover() {
  if (!window.bootstrap) return;
  if (!window.matchMedia('(min-width: 992px) and (pointer: fine)').matches) return;
  document.querySelectorAll('.sj-nav .nav-item.dropdown').forEach(function (item) {
    var toggle = item.querySelector('[data-bs-toggle="dropdown"]');
    if (!toggle) return;
    var dd = bootstrap.Dropdown.getOrCreateInstance(toggle);
    var timer = null;
    item.addEventListener('pointerenter', function () { clearTimeout(timer); dd.show(); });
    item.addEventListener('pointerleave', function () {
      timer = setTimeout(function () { dd.hide(); }, 140);
    });
  });
}

// Auto-wire the Stage J pieces present on the page. Counters are scoped to
// [data-sj-counters] containers (J3+ markup) so the legacy inline counter
// script on the un-migrated Home page is never double-driven.
document.addEventListener('DOMContentLoaded', function () {
  sjRevealIO('.sj-reveal');
  sjCounters('[data-sj-counters] .counter[data-target]');
  sjNavScroll();
  sjMegaHover();
});
