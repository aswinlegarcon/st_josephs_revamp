// site.js — the public site's ONE shared behavior file (Stage J).
// Motion runtime (one-time IO reveals, suffix-aware counters, navbar settle,
// mega-menu hover intent, hero keyboard nav) — all reduced-motion-aware,
// auto-wired at the bottom. Loaded by the shell at the end of every body.

/* ================== Stage J motion runtime (PUBLIC_UI_DESIGN.md §4) ==================
   (The pre-Stage-J sjReveal/sjBlurCards helpers were deleted in J6 once the
   last caller was migrated to the one-time .sj-reveal system below.)
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
  var fired = false;
  var io = new IntersectionObserver(function (entries) {
    fired = true;
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
  // Safety valve: if the observer NEVER reports (broken IO in odd webviews —
  // something always intersects at load in a working one), content must not
  // stay invisible. One check, then reveal everything.
  setTimeout(function () {
    if (fired) return;
    io.disconnect();
    els.forEach(function (el) { el.classList.add('sj-in'); });
  }, 4000);
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
  var fired = false;
  var io = new IntersectionObserver(function (entries) {
    fired = true;
    entries.forEach(function (en) {
      if (!en.isIntersecting) return;
      io.unobserve(en.target);
      animate(en.target);
    });
  }, { threshold: 0.4 });
  els.forEach(function (el) { io.observe(el); });
  // Safety valve (same rationale as sjRevealIO): broken IO must not leave the
  // stat band stuck at 0 — set the final values without animation.
  setTimeout(function () {
    if (fired) return;
    io.disconnect();
    els.forEach(function (el) {
      var m = String(el.getAttribute('data-target') || '').trim().match(/^(\d+)(.*)$/);
      if (m) put(el, parseInt(m[1], 10), m[2] || '');
    });
  }, 4000);
}

// Navbar settle: toggles .sj-scrolled on the .sj-nav navbar past 40px scroll
// (rAF-throttled, passive). Wired up by the J2 navbar; no-op until it exists.
function sjNavScroll() {
  var nav = document.querySelector('.sj-navhead') || document.querySelector('.navbar.sj-nav');
  var cue = document.querySelector('.sj-hero-cue');
  if (!nav && !cue) return;
  var ticking = false;
  function apply() {
    if (nav) nav.classList.toggle('sj-scrolled', window.scrollY > 40);
    // K4: the hero scroll cue has done its job once the visitor scrolls.
    if (cue) cue.classList.toggle('hide', window.scrollY > 60);
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
  // K4: track every dropdown so entering one INSTANTLY closes the others —
  // fast pointer sweeps across the menu can never overlap two open panels.
  var entries = [];
  document.querySelectorAll('.sj-nav .nav-item.dropdown').forEach(function (item) {
    var toggle = item.querySelector('[data-bs-toggle="dropdown"]');
    if (!toggle) return;
    var entry = { dd: bootstrap.Dropdown.getOrCreateInstance(toggle), timer: null };
    entries.push(entry);
    item.addEventListener('pointerenter', function () {
      entries.forEach(function (o) {
        if (o === entry) return;
        clearTimeout(o.timer);
        o.dd.hide();
      });
      clearTimeout(entry.timer);
      entry.dd.show();
      toggle.blur(); // no stray focus ring from programmatic opens
    });
    item.addEventListener('pointerleave', function () {
      entry.timer = setTimeout(function () { entry.dd.hide(); }, 140);
    });
  });
}

// Keyboard ←/→ for THE page's main carousel — the one marked data-sj-kbnav
// (hero.php sets it; one per page). Same guards as the shipped home-hero
// implementation: no modifiers, not while typing, not in admin edit mode.
function sjHeroKeys() {
  var host = document.querySelector('.carousel[data-sj-kbnav]');
  if (!host) return;
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
    if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
    var t = e.target;
    if (t && t.closest && t.closest('input, textarea, select, [contenteditable]')) return;
    if (document.body.classList.contains('sj-edit-mode')) return;
    if (!window.bootstrap) return;
    var c = window.bootstrap.Carousel.getOrCreateInstance(host);
    if (e.key === 'ArrowRight') { c.next(); } else { c.prev(); }
  });
}

// Testimonial slider (K9): three cards visible ≥901px, one below; the track
// moves ONE card per step (never a whole page of three), wraps around, and
// auto-advances every 3s. Auto-play pauses on hover/focus, skips hidden tabs,
// respects reduced motion, and stays OFF in admin edit mode (an auto-moving
// track under a contenteditable would fight the editor). Arrows hide
// themselves whenever every card already fits in the viewport.
function sjTestimonialSlider() {
  var slider = document.querySelector('.tm-slider');
  if (!slider) return;
  var track = slider.querySelector('.tm-track');
  var prev = slider.querySelector('.tm-nav--prev');
  var next = slider.querySelector('.tm-nav--next');
  if (!track || !prev || !next) return;
  var cards = track.querySelectorAll('.card');
  if (cards.length === 0) { prev.hidden = true; next.hidden = true; return; }

  var index = 0;
  var timer = null;

  function visible() {
    return window.matchMedia('(min-width: 901px)').matches ? 3 : 1;
  }
  function stepPx() { // card width + track gap, measured from the real layout
    return cards.length > 1 ? (cards[1].offsetLeft - cards[0].offsetLeft) : 0;
  }
  function maxIndex() { return Math.max(0, cards.length - visible()); }

  function apply() {
    var m = maxIndex();
    if (index > m) index = m;
    track.style.transform = 'translateX(' + (-index * stepPx()) + 'px)';
    prev.hidden = m === 0;
    next.hidden = m === 0;
  }
  function go(delta) {
    var m = maxIndex();
    if (m === 0) return;
    index = (index + delta + m + 1) % (m + 1); // one step, wrapping both ways
    apply();
  }

  function stop() { if (timer) { clearInterval(timer); timer = null; } }
  function start() {
    stop();
    if (!sjMotionOK()) return;
    if (document.body.classList.contains('sj-edit-mode')) return;
    if (maxIndex() === 0) return;
    timer = setInterval(function () {
      if (!document.hidden) go(1);
    }, 3000);
  }

  prev.addEventListener('click', function () { go(-1); start(); });
  next.addEventListener('click', function () { go(1); start(); });
  slider.addEventListener('pointerenter', stop);
  slider.addEventListener('pointerleave', start);
  slider.addEventListener('focusin', stop);
  slider.addEventListener('focusout', start);
  window.addEventListener('resize', function () { apply(); start(); });
  // Breakpoint flips can happen WITHOUT a resize event (webview viewport
  // settling, zoom, orientation races) — re-evaluate on the media query too.
  var mq = window.matchMedia('(min-width: 901px)');
  if (mq.addEventListener) {
    mq.addEventListener('change', function () { apply(); start(); });
  }

  apply();
  start();
}

// Auto-wire the Stage J pieces present on the page. Counters are scoped to
// [data-sj-counters] containers (J3+ markup) so the legacy inline counter
// script on the un-migrated Home page is never double-driven.
// K9: readyState-safe boot — a DOMContentLoaded listener registered AFTER the
// document is already interactive never fires (late-executed scripts, webview
// re-navigation), which would silently skip every wire below.
// Footer credit (K13): the developer's address is NEVER written into the page
// source — the local part and the REVERSED domain sit in data-* attributes with
// no "@" anywhere, so email harvesters scraping the static HTML find nothing to
// match. The real address is assembled only at click/Enter time and handed to
// Gmail's compose window. No-JS clients simply get an inert credit label.
function sjCreditMail() {
  document.querySelectorAll('.sj-credit-mail').forEach(function (el) {
    function open() {
      var u = el.getAttribute('data-u') || '';
      var d = (el.getAttribute('data-d') || '').split('').reverse().join('');
      if (!u || !d) return;
      var url = 'https://mail.google.com/mail/?view=cm&fs=1&to=' + encodeURIComponent(u + '@' + d);
      window.open(url, '_blank', 'noopener');
    }
    el.addEventListener('click', open);
    el.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
    });
    el.style.cursor = 'pointer';
  });
}
function sjBoot() {
  sjRevealIO('.sj-reveal');
  sjCounters('[data-sj-counters] .counter[data-target]');
  sjNavScroll();
  sjMegaHover();
  sjHeroKeys();
  sjTestimonialSlider();
  sjCreditMail();
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', sjBoot);
} else {
  sjBoot();
}
