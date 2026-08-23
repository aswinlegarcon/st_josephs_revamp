<?php
// Academy-family template — the 15 academies + band/ncc/artandexpo (C5).
// One template, 18 pages. Variables from the thin controllers:
//   $sj_slug, $sj_academy (row + bg 'image'), $sj_carousel (linked images).
// Per-page bits preserved from the static R1b views: the .bg-1 background
// (the only per-page CSS) and the {slug}Carousel id.
?>
<?php
// Per-page hero background for the .infra-new band (the only per-page
// difference). Rendered by the shell as a HEAD <style> — valid HTML where the
// old body <style> was not (R3); nothing else styles .bg-1, so the position
// change cannot alter the cascade.
// Stage J: the brand veil (--sj-veil values) replaces the old black scrim.
$sjHeadCss = "  .bg-1 { background: linear-gradient(rgba(43, 75, 138, .55), rgba(26, 53, 93, .75)), url('"
    . e($sj_academy['image'] ? img_url($sj_academy['image'], 'bg_wide') : '')
    . "') no-repeat center; background-size: cover; }";
?>

<section class="back-bar">
  <div class="back-text sj-reveal">
    <h4<?= ed_field('academy', $sj_academy['id'], 'banner_title') ?>><?= e($sj_academy['banner_title']) ?></h4>
    <p<?= ed_field('academy', $sj_academy['id'], 'banner_subtitle') ?>><?= e($sj_academy['banner_subtitle']) ?></p>
  </div>
</section>

<!-- content -->
<section class="infra-new bg-1">
  <section class="infra-new-carousel">
    <div id="<?= e($sj_slug) ?>Carousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <div class="carousel-indicators">
        <?php foreach ($sj_carousel as $i => $img): ?>
        <button type="button" data-bs-target="#<?= e($sj_slug) ?>Carousel" data-bs-slide-to="<?= $i ?>"<?= $i === 0 ? ' class="active" aria-current="true"' : '' ?> aria-label="Slide <?= $i + 1 ?>"></button>
        <?php endforeach; ?>
      </div>
      <div class="carousel-inner">
        <?php foreach ($sj_carousel as $i => $img): ?>
        <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>">
          <?= img_tag($img, 'content_slide', ['class' => 'd-block w-100', 'alt' => ($i === 0 ? 'First' : ($i === 1 ? 'Second' : 'Third')) . ' slide']) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <a class="carousel-control-prev" href="#<?= e($sj_slug) ?>Carousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#<?= e($sj_slug) ?>Carousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="infra-new-text">
    <h4 class="sj-reveal"<?= ed_field('academy', $sj_academy['id'], 'content_heading') ?>><?= e($sj_academy['content_heading']) ?></h4>
    <?php ed_rich('academy', $sj_academy['id'], 'body_html', $sj_academy['body_html']); ?>
  </div>
</section>
