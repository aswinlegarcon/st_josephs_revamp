<?php // Back-to-top button — clean fragment (Font Awesome loaded once by the layout). ?>
<style>
  html { scroll-behavior: smooth; }
  .go-top-btn { position: fixed; width: 50px; background: #2b4b8a; bottom: 40px; right: 50px;
    text-decoration: none; text-align: center; line-height: 50px; color: white; font-size: 18px;
    border-radius: 50%; z-index: 700; }
  .go-top-btn:hover { background: #FFD700; color: black; }
  .reveal-btn { transition: 1.3s; opacity: 0; visibility: hidden; }
  .reveal-btn.active { opacity: 1; visibility: visible; }
  @media (max-width: 768px) { .go-top-btn { bottom: 30px; right: 30px; } }
</style>
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
