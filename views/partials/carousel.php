<?php // Home hero carousel — clean Bootstrap 5 fragment (no nested document).
// Parametrized (P4 plan): $sj_home_page + $sj_hero_slides come from the page
// controller instead of self-querying. Markup/behavior is a faithful BS5
// translation of _templates/carousel.php (data-bs-*, indicator buttons,
// visually-hidden); carousel.css loads via the shell's $styles list.
// STYLING RULE (CLAUDE.md): render must stay pixel-identical to baseline. ?>

<!-- Carousel Starts -->
<section class="carousel-main reveal-carousel">
  <div id="carouselExampleIndicators" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
    <div class="carousel-indicators">
      <?php foreach ($sj_hero_slides as $i => $s): ?>
      <button type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide-to="<?= $i ?>"<?= $i === 0 ? ' class="active" aria-current="true"' : '' ?> aria-label="Slide <?= $i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
    <div class="carousel-inner"<?= $sj_home_page ? ed_add('hero_slide', ['page_id' => (int)$sj_home_page['id']], 'Add hero slide') : '' ?>>
      <?php foreach ($sj_hero_slides as $i => $s): ?>
      <div class="carousel-item<?= $i === 0 ? ' active' : '' ?><?= empty($s['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('hero_slide', $s['id'], 'Hero slide') ?>>
        <?= img_tag($s['image'], 'hero_16x7', ['class' => 'd-block', 'alt' => 'Slide', 'eager' => $i === 0, 'extra' => trim(ed_img('hero_slide', $s['id']))]) ?>
        <div class="carousel-caption">

        <h5<?= ed_field('hero_slide', $s['id'], 'caption_title') ?>><?= e($s['caption_title']) ?></h5>
          <p<?= ed_field('hero_slide', $s['id'], 'caption_text') ?>><?= e($s['caption_text']) ?></p>
          <?php if (!empty($s['button_label'])): ?>
          <a href="<?= e($s['button_url'] ?: '#') ?>" class="slider-btn">
            <button class="btn btn-1"<?= ed_field('hero_slide', $s['id'], 'button_label') ?>><?= e($s['button_label']) ?></button>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if (count($sj_hero_slides) > 1): ?>
    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Next</span>
    </button>
    <?php endif; ?>
  </div>
</section>

<!-- Custom JavaScript -->
<script src="/js/carousel.js"></script>
<script>
// Keyboard navigation for the HOME HERO carousel only (FEATURES_PLAN.md §3).
// index.php has two carousels — .carousel-main is unique to the hero.
// BS5 rewrite of the original jQuery call — same guards, same behavior.
document.addEventListener('keydown', function (e) {
  if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
  if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
  var t = e.target;
  if (t && t.closest && t.closest('input, textarea, select, [contenteditable]')) return;
  if (document.body.classList.contains('sj-edit-mode')) return; // admin overlay needs arrow keys
  if (!window.bootstrap) return;
  var hero = document.querySelector('.carousel-main .carousel');
  if (!hero) return;
  var c = window.bootstrap.Carousel.getOrCreateInstance(hero);
  if (e.key === 'ArrowRight') { c.next(); } else { c.prev(); }
});
</script>
