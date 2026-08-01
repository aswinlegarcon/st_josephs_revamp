<?php
$sj_home_page   = repo_page('index');
$sj_hero_slides = $sj_home_page ? repo_hero_slides((int)$sj_home_page['id'], is_edit()) : [];
?>
<!doctype html>
<html lang="en">
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS links -->

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css"
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">

  <!-- Custom CSS -->
  <link rel="stylesheet" href="/css/carousel.css?v=<?php echo time(); ?>">
</head>
<body>

<!-- Carousel Starts -->
<section class="carousel-main reveal-carousel">
  <div id="carouselExampleIndicators" class="carousel slide carousel-fade" data-ride="carousel" data-interval="2000">
    <ol class="carousel-indicators">
      <?php foreach ($sj_hero_slides as $i => $s): ?>
      <li data-target="#carouselExampleIndicators" data-slide-to="<?= $i ?>"<?= $i === 0 ? ' class="active"' : '' ?>></li>
      <?php endforeach; ?>
    </ol>
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
    <a class="carousel-control-prev" href="#carouselExampleIndicators" role="button" data-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="sr-only">Previous</span>
    </a>
    <a class="carousel-control-next" href="#carouselExampleIndicators" role="button" data-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="sr-only">Next</span>
    </a>
    <?php endif; ?>
  </div>
</section>


<script  src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.min.js"
        integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

<!-- Custom JavaScript -->
<script src="/js/carousel.js"></script>
<script>
// Keyboard navigation for the HOME HERO carousel only (FEATURES_PLAN.md §3).
// index.php has two carousels — .carousel-main is unique to the hero.
document.addEventListener('keydown', function (e) {
  if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
  if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) return;
  var t = e.target;
  if (t && t.closest && t.closest('input, textarea, select, [contenteditable]')) return;
  if (document.body.classList.contains('sj-edit-mode')) return; // admin overlay needs arrow keys
  if (!window.jQuery) return;
  var $hero = window.jQuery('.carousel-main .carousel');
  if (!$hero.length) return;
  $hero.carousel(e.key === 'ArrowRight' ? 'next' : 'prev');
});
</script>
</body>
</html>
