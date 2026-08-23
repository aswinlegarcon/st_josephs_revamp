<?php // Co-curriculum page body — Stage J. The top carousel is static content
// (three shipped photos, no hero_slide rows) so it keeps its own markup but
// wears the shared .sj-hero classes for identical geometry/veil/caption. ?>
<!-- top carousel -->
<section class="sj-hero sj-hero--banner sj-reveal">
<div id="coCurriculumHeroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000" data-sj-kbnav>
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img class="d-block w-100" src="/photos/band1.jpg" alt="First slide" fetchpriority="high"><?php /* N5 audit: original kept — every recompression came out LARGER (193→200+ KB) */ ?>
      <div class="carousel-caption sj-hero-cap">
          <h5 class="sj-hero-title">Our Co-Curriculum</h5>
          <p class="sj-hero-flourish">About our co-curriculum</p>
      </div>
    </div>
    <div class="carousel-item">
      <img class="d-block w-100" src="photos/vocaca1.jpg" alt="Second slide" loading="lazy">
      <div class="carousel-caption sj-hero-cap">
      <h5 class="sj-hero-title">Our Co-Curriculum</h5>
          <p class="sj-hero-flourish">About our co-curriculum</p>
      </div>
    </div>
    <div class="carousel-item">
      <img class="d-block w-100" src="photos/ncc1.jpg" alt="Third slide" loading="lazy">
      <div class="carousel-caption sj-hero-cap">
      <h5 class="sj-hero-title">Our Co-Curriculum</h5>
          <p class="sj-hero-flourish">About our co-curriculum</p>
      </div>
    </div>
  </div>
</div>
</section>

<!-- academy cards -->
<div class="home-text">
    <h2>The <span>Co Curriculum </span> of St.Joseph's</h2>
</div>

<section class="co-curriculum-all">
<?php
// C5: the 18+ academy cards render from the `academies` table (grid order =
// admin order), three per row. $sj_academies from the controller.
foreach (array_chunk($sj_academies, 3) as $ri => $chunk): ?>
    <section class="co-curriculum-card sj-reveal">
    <div class="row mt-5">
    <?php foreach ($chunk as $ac): ?>
        <div class="col-md-4">
          <div class="card<?= empty($ac['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('academy', $ac['id'], $ac['card_title']) ?>>
            <?= img_tag($ac['image'], 'card_4x3', ['class' => 'card-img-top', 'alt' => '...', 'extra' => trim(ed_img('academy', $ac['id'], 'card_image_id'))]) ?>
            <div class="card-body">
            <h3 class="card-title"<?= ed_field('academy', $ac['id'], 'card_title') ?>><?= e($ac['card_title']) ?></h3>
            <p class="card-text"<?= ed_field('academy', $ac['id'], 'card_subtitle') ?>><?= e($ac['card_subtitle']) ?></p>
            <a class="sj-btn sj-btn--navy sj-btn--sm" href="<?= e(academy_url($ac['slug'])) ?>" role="button" aria-label="Read more about <?= e($ac['card_title']) ?>">Read more</a>
            </div>
          </div>
        </div>
    <?php endforeach; ?>
    </div>
    </section>
<?php endforeach; ?>
    </section>
