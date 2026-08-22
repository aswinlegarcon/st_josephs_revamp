<?php // Site navigation — Stage J "Global Campus" (PUBLIC_UI_DESIGN.md §5).
// Fixed-top brand nav with grouped dropdown panels. STATIC hrefs only — zero
// DB queries, so every page keeps its ≤12-query budget; admin-created
// academies/albums surface on the co-curriculum grid / gallery hub instead.
// Engine: Bootstrap Dropdown (click/Esc/aria) + Offcanvas (<992px drawer);
// hover-intent open and the scrolled state live in js/site.js
// (sjMegaHover / sjNavScroll). Pages with a full-bleed hero set $sjNavOverlay
// (Layout data) → transparent start, solid after 40px; all other pages render
// solid with the .sj-nav-spacer standing in for the old in-flow navbar height.
$sjNavGroups = [
    'About' => [
        ['/about.php',          'Our School'],
        ['/staffs.php',         'Our Staffs'],
        ['/infrastructure.php', 'Infrastructure'],
        ['/achievements.php',   'Achievements'],
    ],
    'Academics' => [
        ['/academics.php', 'Academics Overview'],
        ['/kg.php',        'Kindergarten'],
        ['/primary.php',   'Primary School'],
        ['/highschl.php',  'High School'],
        ['/highsec.php',   'Higher Secondary'],
    ],
    'Student Life' => [
        ['/co-curriculum.php', 'Co-Curriculum & Academies'],
        ['/sports.php',        'Sports'],
    ],
    // The 10 legacy albums are delete-protected (Registry::legacySlugs), so
    // these static links can never break; new albums appear on the hub page.
    'Gallery' => [
        ['/gallery.php',          'Gallery Hub'],
        ['/gal-annual.php',       'Annual Day'],
        ['/gal-sports.php',       'Sports Day'],
        ['/gal-independence.php', 'Independence Day'],
        ['/gal-children.php',     "Children's Day"],
        ['/gal-teacher.php',      "Teacher's Day"],
        ['/gal-expressionz.php',  'Expressionz Day'],
        ['/gal-expo.php',         'Science Expo'],
        ['/gal-grad.php',         'KG Graduation Day'],
        ['/gal-alumni.php',       'Our Alumni'],
        ['/gal-spach.php',        'Sports Achievements'],
    ],
];
$sjNavOverlayOn = !empty($sjNavOverlay);
?>
<nav class="navbar navbar-expand-lg fixed-top sj-nav<?= $sjNavOverlayOn ? ' sj-nav--overlay' : '' ?>">
  <div class="container-fluid sj-nav-inner">
    <a class="navbar-brand sj-nav-brand" href="/index.php">
      <img src="/photos/logo-main.png" alt="St.Joseph's crest">
      <span class="sj-nav-name">
        <b>St.Joseph's MHSS</b>
        <small>Ondipudur, Coimbatore &ndash; 641016</small>
      </span>
    </a>

    <div class="sj-nav-desk d-none d-lg-flex">
      <ul class="navbar-nav">
        <li class="nav-item"><a class="nav-link" href="/index.php">Home</a></li>
        <?php foreach ($sjNavGroups as $sjNavGroup => $sjNavLinks): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button"
             data-bs-toggle="dropdown" aria-expanded="false"><?= e($sjNavGroup) ?></a>
          <div class="dropdown-menu sj-mega<?= count($sjNavLinks) > 6 ? ' sj-mega--2col' : '' ?>">
            <?php foreach ($sjNavLinks as [$sjNavHref, $sjNavLabel]): ?>
            <a class="dropdown-item" href="<?= e($sjNavHref) ?>"><?= e($sjNavLabel) ?></a>
            <?php endforeach; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
      <a class="sj-btn sj-btn--gold sj-btn--sm sj-nav-cta" href="/index.php#contact">Admissions</a>
    </div>

    <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#sjNavDrawer" aria-controls="sjNavDrawer" aria-label="Open menu">
      <span class="navbar-toggler-icon"></span>
    </button>
  </div>
</nav>
<?php if (!$sjNavOverlayOn): ?><div class="sj-nav-spacer" aria-hidden="true"></div><?php endif; ?>

<div class="offcanvas offcanvas-end sj-drawer" tabindex="-1" id="sjNavDrawer" aria-label="Site menu">
  <div class="offcanvas-header">
    <span class="sj-nav-brand">
      <img src="/photos/logo-main.png" alt="">
      <span class="sj-nav-name"><b>St.Joseph's MHSS</b><small>Ondipudur, Coimbatore</small></span>
    </span>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
  </div>
  <div class="offcanvas-body">
    <a class="sj-drawer-link" href="/index.php">Home</a>
    <?php $sjNavGi = 0; foreach ($sjNavGroups as $sjNavGroup => $sjNavLinks): $sjNavGi++; ?>
    <button class="sj-drawer-group" type="button" data-bs-toggle="collapse"
            data-bs-target="#sjNavG<?= $sjNavGi ?>" aria-expanded="false" aria-controls="sjNavG<?= $sjNavGi ?>">
      <?= e($sjNavGroup) ?><span class="sj-drawer-caret" aria-hidden="true"></span>
    </button>
    <div class="collapse" id="sjNavG<?= $sjNavGi ?>">
      <?php foreach ($sjNavLinks as [$sjNavHref, $sjNavLabel]): ?>
      <a class="sj-drawer-sublink" href="<?= e($sjNavHref) ?>"><?= e($sjNavLabel) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
    <a class="sj-btn sj-btn--gold sj-drawer-cta" href="/index.php#contact">Admissions Enquiry</a>
  </div>
</div>
