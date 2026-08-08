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
