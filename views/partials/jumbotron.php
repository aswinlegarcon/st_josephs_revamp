<?php // Admissions call-to-action band — clean fragment.
// STYLING (visual-freeze): the verbatim original styles live in
// /css/partials/jumbotron.css (body link = valid HTML, same cascade position — R3). ?>
<link rel="stylesheet" href="/css/partials/jumbotron.css?v=<?php echo SJ_ASSET_VER; ?>">
<?php // C1: strings come from `settings` (fallbacks = the exact original values). ?>
<div class="jumbotron jumbotron-fluid jumbotron-reveal">
  <div class="container text-center">
    <h1 class="display-4"><?= e(repo_setting('jumbotron_heading', "Explore a holistic education at St.Joseph's")) ?></h1>
    <p class="lead"><?= e(repo_setting('jumbotron_sub', 'Click Here for Admissions')) ?></p>
    <a class="btn btn-primary btn-lg" href="/index.php#contact" role="button"><?= e(repo_setting('jumbotron_btn', 'Learn more')) ?></a>
  </div>
</div>
<script>
// Shipped reveal (scroll-only), now via the shared helper in /js/site.js (R2/R3).
window.addEventListener('DOMContentLoaded', function () {
    sjReveal('.jumbotron-reveal', 150);
});
</script>
