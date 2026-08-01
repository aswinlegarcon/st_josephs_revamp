
  window.addEventListener('scroll', reveal);
  window.addEventListener('DOMContentLoaded', reveal);

  function reveal() {
    var reveals = document.querySelectorAll('.reveal-carousel');
    var windowHeight = window.innerHeight;
    var revealPoint = 150;

    reveals.forEach(function(revealElement) {
      var revealTop = revealElement.getBoundingClientRect().top;

      if (revealTop < windowHeight - revealPoint) {
        revealElement.classList.add('active');
      } else {
        revealElement.classList.remove('active');
      }
    });
  }
