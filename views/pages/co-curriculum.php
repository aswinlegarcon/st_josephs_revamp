<?php // Co-curriculum page body (static content; academy cards). Bootstrap 5 dialect. ?>
<!-- top carousel -->
<section class="abt-carousel ">
<div id="coCurriculumHeroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img class="d-block w-100" src="/photos/band1.jpg" alt="First slide">
      <div class="carousel-caption text-start">
          <h5 class="abt-carousel-reveal">Our Co-Curriculum</h5>
          <p class="abt-carousel-reveal">About our co-curriculum</p>

      </div>
    </div>
    <div class="carousel-item">
      <img class="d-block w-100" src="photos/vocaca1.jpg" alt="Second slide">
      <div class="carousel-caption text-start">
      <h5>Our Co-Curriculum</h5>
          <p>About our co-curriculum</p>
      </div>
    </div>
    <div class="carousel-item">
      <img class="d-block w-100" src="photos/ncc1.jpg" alt="Third slide">
      <div class="carousel-caption text-start">
      <h5>Our Co-Curriculum</h5>
          <p>About our co-curriculum</p>
      </div>
    </div>
  </div>
</div>
</section>

<!-- co-curriculumment cards start -->

<div class="home-text">
    <h2  class="span-reveal">The <span>Co Curriculum </span> of St.Joseph's</h2>
</div>

<section class="co-curriculum-all">
<?php
// C5: the 18 academy cards render from the `academies` table (grid order =
// admin order). Three cards per reveal-section; the reveal-class suffixes
// reproduce the shipped sequence ('', 2, 3, 4, 4, 5 — the duplicate 4 is a
// shipped quirk kept on purpose). $sj_academies from the controller.
$sj_suffixes = ['', '2', '3', '4', '4', '5'];
foreach (array_chunk($sj_academies, 3) as $ri => $chunk): ?>
    <section class="co-curriculum-card co-curriculum-card-reveal<?= $sj_suffixes[min($ri, 5)] ?>">
    <div class="row mt-5">
    <?php foreach ($chunk as $ac): ?>
        <div class="col-md-4">
          <div class="card<?= empty($ac['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('academy', $ac['id'], $ac['card_title']) ?>>
            <?= img_tag($ac['image'], 'card_4x3', ['class' => 'card-img-top', 'alt' => '...', 'extra' => trim(ed_img('academy', $ac['id'], 'card_image_id'))]) ?>
            <div class="card-body">
            <h3 class="card-title"<?= ed_field('academy', $ac['id'], 'card_title') ?>><?= e($ac['card_title']) ?></h3>
            <p class="card-text"<?= ed_field('academy', $ac['id'], 'card_subtitle') ?>><?= e($ac['card_subtitle']) ?></p>
            <a class="btn btn-primary btn-lg" href="<?= e(academy_url($ac['slug'])) ?>" role="button" aria-label="Read more about <?= e($ac['name']) ?>">Read more</a>
            </div>
          </div>
        </div>
    <?php endforeach; ?>
    </div>
    </section>
<?php endforeach; ?>
    </section>

<script src="/js/co-curriculum.js"></script>
<script>
// Shipped selectors/threshold, now through the shared helper in /js/site.js
// (R2); site.js loads at the end of the body, hence the DOMContentLoaded wrap.
window.addEventListener('DOMContentLoaded', function () {
  sjReveal('.co-curriculum-card-reveal,.co-curriculum-text-reveal,.abt-carousel-reveal', 50, true);
});
</script>
