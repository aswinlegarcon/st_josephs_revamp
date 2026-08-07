// Shared scroll-reveal for the academy-family pages. Extracted verbatim from the
// original per-page inline script (identical across all of them). Toggles the
// .active class on .back-bar-reveal / .infra-new-reveal as they enter the viewport.
window.addEventListener('DOMContentLoaded', reveal);
window.addEventListener('scroll', reveal);
function reveal() {
  var reveals = document.querySelectorAll('.back-bar-reveal,.infra-new-reveal');
  var windowHeight = window.innerHeight;
  var revealPoint = 150;
  reveals.forEach(function (revealElement) {
    var revealTop = revealElement.getBoundingClientRect().top;
    if (revealTop < windowHeight - revealPoint) {
      revealElement.classList.add('active');
    } else {
      revealElement.classList.remove('active');
    }
  });
}
