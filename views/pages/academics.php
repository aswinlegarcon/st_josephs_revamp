<?php // Academics page body — Stage J. The top carousel is static content
// (three optimized section photos, no hero_slide rows) so it keeps its own
// markup but wears the shared .sj-hero classes for identical geometry/veil. ?>
<!-- top carousel -->
<section class="sj-hero sj-hero--banner sj-reveal">
  <div id="academicsHeroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000" data-sj-kbnav>
    <div class="carousel-inner">
      <div class="carousel-item active">
        <img class="d-block w-100" src="/media/static/kg1.jpg" alt="First slide" fetchpriority="high"><?php /* N5: optimized copy (1.3 MB → 236 KB); same frame, aspect preserved */ ?>
        <div class="carousel-caption sj-hero-cap">
          <h5 class="sj-hero-title">Academics</h5>
          <p class="sj-hero-flourish">Embrace the challenges of learning, for they are stepping stones towards your academic success.</p>
        </div>
      </div>
      <div class="carousel-item">
        <img class="d-block w-100" src="/media/static/high1.jpg" alt="Second slide" loading="lazy">
        <div class="carousel-caption sj-hero-cap">
          <h5 class="sj-hero-title">Academics</h5>
          <p class="sj-hero-flourish">Embrace the challenges of learning, for they are stepping stones towards your academic success.</p>
        </div>
      </div>
      <div class="carousel-item">
        <img class="d-block w-100" src="/media/static/highsec1.jpg" alt="Third slide" loading="lazy">
        <div class="carousel-caption sj-hero-cap">
          <h5 class="sj-hero-title">Academics</h5>
          <p class="sj-hero-flourish">Embrace the challenges of learning, for they are stepping stones towards your academic success.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- heading band -->
<div class="home-text">
  <h2>The <span>Academics </span> in St.Joseph's</h2>
</div>

<!-- grade-level cards -->
<section class="academy-card">
  <div class="row mt-4">
    <?php foreach ($sj_sections as $si => $sec): ?>
    <div class="col-md-3">
      <div class="card sj-reveal"<?= $si > 0 ? ' data-sj-delay="' . ($si * 80) . '"' : '' ?>>
        <?= img_tag($sec['image'], 'card_4x3', ['class' => 'card-img-top', 'alt' => '...', 'extra' => trim(ed_img('school_section', $sec['id'], 'card_image_id'))]) ?>
        <div class="card-body">
          <h3 class="card-title"<?= ed_field('school_section', $sec['id'], 'card_title') ?>><?= e($sec['card_title']) ?></h3>
          <p class="card-text"<?= ed_field('school_section', $sec['id'], 'card_range') ?>><?= e($sec['card_range']) ?></p>
          <a href="<?= e($sec['slug']) ?>.php" class="sj-btn sj-btn--navy sj-btn--sm">Explore</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
