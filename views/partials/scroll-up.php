<?php // Back-to-top button — clean fragment (Font Awesome loaded once by the layout).
// Styles in /css/partials/ (body link = valid HTML, same cascade position — R3). ?>
<link rel="stylesheet" href="/css/partials/scroll-up.css?v=<?php echo SJ_ASSET_VER; ?>">
<div class="scroll-btn reveal-btn">
  <a class="go-top-btn" href="#"><i class="fa-solid fa-arrow-up"></i></a>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var scrollButton = document.querySelector('.scroll-btn');
    window.addEventListener('scroll', function () {
      if (window.scrollY > 600) scrollButton.classList.add('active');
      else scrollButton.classList.remove('active');
    });
  });
</script>
