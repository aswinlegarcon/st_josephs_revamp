<?php // Admissions call-to-action band — Stage J v2 (PUBLIC_UI_DESIGN.md §5).
// Same three settings keys as C1 (fallbacks = the seeded strings); styles in
// site.css (.sj-band); reveal via the shared .sj-reveal one-time observer. ?>
<div class="sj-band sj-reveal">
  <div class="container text-center">
    <h2><?= e(repo_setting('jumbotron_heading', "Explore a holistic education at St.Joseph's")) ?></h2>
    <p><?= e(repo_setting('jumbotron_sub', 'Click Here for Admissions')) ?></p>
    <a class="sj-btn sj-btn--gold" href="/index.php#contact" role="button"
       aria-label="Admissions — contact the school"><?= e(repo_setting('jumbotron_btn', 'Learn more')) ?></a>
  </div>
</div>
