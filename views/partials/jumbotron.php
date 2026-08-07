<?php // Admissions call-to-action band — clean fragment.
// STYLING (visual-freeze): verbatim reproduction of the original
// _templates/jumbotron.php. BS5 removed the .jumbotron component, so ONLY its
// BS4 padding box-model is re-created here as compensation. Do NOT "tidy" the
// font names: the original uses "LeagueSpartan" (no space) for <p> and the
// button, which does NOT match the loaded "League Spartan" font and therefore
// falls back to sans-serif — that fallback is the intended production look. ?>
<style>
  /* Compensation only: re-create BS4's .jumbotron / .jumbotron-fluid padding
     (BS5 dropped the component). Everything else below is the original CSS. */
  .jumbotron { padding: 2rem 1rem; }
  @media (min-width: 576px) { .jumbotron { padding: 4rem 2rem; } }
  .jumbotron-fluid { padding-right: 0; padding-left: 0; }

  .jumbotron { background: linear-gradient(to top, #f0f0f0, #d9d9d9) !important; margin-bottom: 0 !important; }
  .jumbotron .container h1 { font-family: "Fjalla One", sans-serif !important; color: black !important; font-weight: 700; }
  .jumbotron .container p { font-family: "LeagueSpartan", sans-serif !important; color: firebrick !important;
    font-weight: 500; font-style: italic; }
  .jumbotron .container .btn { font-family: "LeagueSpartan", sans-serif !important; border: none !important;
    background: linear-gradient(to left, #2b4b8a, #1a355d) !important; }
  .jumbotron .container .btn:hover { background: firebrick !important; }
  @media (max-width: 900px) { .jumbotron .container h1 { font-size: 45px; } }
  @media (max-width: 768px) { .jumbotron .container h1 { font-size: 35px; } }
  @media (max-width: 500px) { .jumbotron .container h1 { font-size: 25px; } .jumbotron .container p { font-size: 14px; } }
</style>
<?php // C1: strings come from `settings` (fallbacks = the exact original values). ?>
<div class="jumbotron jumbotron-fluid jumbotron-reveal">
  <div class="container text-center">
    <h1 class="display-4"><?= e(repo_setting('jumbotron_heading', "Explore a holistic education at St.Joseph's")) ?></h1>
    <p class="lead"><?= e(repo_setting('jumbotron_sub', 'Click Here for Admissions')) ?></p>
    <a class="btn btn-primary btn-lg" href="/index.php#contact" role="button"><?= e(repo_setting('jumbotron_btn', 'Learn more')) ?></a>
  </div>
</div>
<script>
  window.addEventListener('scroll', function () {
    document.querySelectorAll('.jumbotron-reveal').forEach(function (el) {
      var revealTop = el.getBoundingClientRect().top;
      if (revealTop < window.innerHeight - 150) el.classList.add('active');
      else el.classList.remove('active');
    });
  });
</script>
