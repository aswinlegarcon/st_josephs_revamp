<?php
// Infrastructure page body — DB-driven since C7. Variables from the controller:
// $sj_page, $sj_hero_slides, $sj_facilities (each row carries 'image' = bg +
// 'carousel' = linked images). Section N keeps its #bg-N anchor (the R1a bug-6
// fix: nav button N scrolls to facility N) and its unique carousel id
// (infraCarouselN — bug 11). Layout variants alternate infra-new/infra-new1
// exactly like the shipped page. The #bg-N background styles are emitted here
// from each facility's photo (they lived in css/infrastructure.css before C7,
// with these exact declarations).
?>
<!-- top carousel — Stage J: the shared hero partial (shipped id kept) -->
<?php
$sjHero = [
    'id'          => 'infraHeroCarousel',
    'slides'      => $sj_hero_slides,
    'variant'     => 'banner', // K1: slim page-title strip (owner UX decision)
    'preset'      => 'hero_16x7',
    'interval'    => 2000,
    'page_id'     => $sj_page ? (int)$sj_page['id'] : null,
    'keyboardNav' => true,
];
include dirname(__DIR__) . '/partials/hero.php';
?>

<!-- infrastructurement cards start -->
<!-- navigation -->

<?php
// Per-facility section backgrounds (values match the pre-C7 css rules).
// Rendered by the shell as a HEAD <style> — valid HTML where the old body
// <style> was not (R3); nothing else styles #bg-N, so the position change
// cannot alter the cascade.
$sjHeadCss = '';
foreach ($sj_facilities as $fi => $F) {
    // Stage J: the brand veil (--sj-veil values) replaces the old black scrim.
    $sjHeadCss .= '  #bg-' . ($fi + 1) . " {\n  background: linear-gradient(rgba(43, 75, 138, .55), rgba(26, 53, 93, .75)),\n    url(\""
        . e($F['image'] ? img_url($F['image'], 'bg_wide') : '')
        . "\") no-repeat center center;\n  background-size: cover;\n}\n";
}
?>

<nav class="navbar navbar-expand-lg sj-subnav">
    <h3 class="inside-nav-text">Infrastructure</h3>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
        <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
            <?php foreach ($sj_facilities as $fi => $F): ?>
            <a href="#bg-<?= $fi + 1 ?>">
                <span class="btn btn-outline-primary"><?= e($F['name']) ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<?php foreach ($sj_facilities as $fi => $F): $n = $fi + 1; $alt = $fi % 2 === 1; ?>
<!-- sec-<?= $n ?> of inf -->
<section id="bg-<?= $n ?>" class="<?= $alt ? 'infra-new1' : 'infra-new' ?> bg-<?= $n ?><?= empty($F['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('facility', $F['id'], $F['name']) ?>>
  <section class="<?= $alt ? 'infra-new-carousel1' : 'infra-new-carousel' ?>">
    <div id="infraCarousel<?= $n ?>" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <ol class="carousel-indicators">
        <?php foreach ($F['carousel'] as $ci => $cimg): ?>
        <li data-bs-target="#infraCarousel<?= $n ?>" data-bs-slide-to="<?= $ci ?>"<?= $ci === 0 ? ' class="active"' : '' ?>></li>
        <?php endforeach; ?>
      </ol>
      <div class="carousel-inner">
        <?php foreach ($F['carousel'] as $ci => $cimg): ?>
        <div class="carousel-item<?= $ci === 0 ? ' active' : '' ?>">
          <?= img_tag($cimg, 'content_slide', ['class' => 'd-block w-100', 'alt' => ($ci === 0 ? 'First' : ($ci === 1 ? 'Second' : 'Third')) . ' slide']) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <a class="carousel-control-prev" href="#infraCarousel<?= $n ?>" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#infraCarousel<?= $n ?>" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="<?= $alt ? 'infra-new-text1' : 'infra-new-text' ?>">
    <h4 class="sj-reveal"<?= ed_field('facility', $F['id'], 'name') ?>><?= e($F['name']) ?></h4>
    <?php ed_rich('facility', $F['id'], 'description_html', $F['description_html']); ?>
  </div>
</section>

<?php endforeach; ?>

