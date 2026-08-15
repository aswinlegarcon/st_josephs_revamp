<?php // Gallery page body (static; BS5 single-document layout). No preloader. The gal-slider is a custom cross-fade slideshow (its own CSS/JS, NOT Bootstrap) inlined below with the document wrapper stripped and image paths made absolute. ?>

<!-- gallery starts -->
<div class="gallery-text gallery-text-reveal">
    <h4>School Gallery</h4>
    <p>Tons of Memories carry our Gallery</p>
</div>

<!-- gal-slider (custom 15-image cross-fade slideshow; not Bootstrap).
     Styles live VERBATIM in /css/partials/gallery-slider.css (body link =
     valid HTML, same cascade position — R3). -->
<link rel="stylesheet" href="/css/partials/gallery-slider.css?v=<?php echo SJ_ASSET_VER; ?>">
<div class="container-slider">
<?php // N4: slides come from image_links (panel-managed); the shipped markup —
      // one bg div per photo, first at opacity 1 — is reproduced exactly. ?>
<?php foreach ($sj_slider_urls as $si => $u): ?>
    <div class="slide" style="background-image: url(<?= e($u) ?>); opacity: <?= $si === 0 ? '1' : '0' ?>;"></div>
<?php endforeach; ?>
</div>

<script>
    const slides = document.querySelectorAll('.slide');
    let currentSlide = 0;
    const slideInterval = 2000; // 3 seconds

    function showNextSlide() {
        slides[currentSlide].style.opacity = 0;
        currentSlide = (currentSlide + 1) % slides.length;
        slides[currentSlide].style.opacity = 1;
    }

    let slideTimer = setInterval(showNextSlide, slideInterval);

    document.querySelector('.container-slider').addEventListener('mouseover', () => {
        clearInterval(slideTimer);
    });

    document.querySelector('.container-slider').addEventListener('mouseout', () => {
        slideTimer = setInterval(showNextSlide, slideInterval);
    });

    // Preload images (N4: same rows as the slides above)
    const images = <?= json_encode(array_values($sj_slider_urls), JSON_UNESCAPED_SLASHES) ?>;

    images.forEach((image) => {
        const img = new Image();
        img.src = image;
    });
</script>

<!-- album card grid -->
<?php
// C9: the album cards render from gallery_albums (order = admin order).
// Three cards per reveal-section, like the shipped page.
foreach (array_chunk($sj_albums, 3) as $ri => $chunk): ?>
<section class="gallery-card gallery-card-reveal">
  <div class="row mt-5">
    <?php foreach ($chunk as $al): ?>
    <div class="col-md-4">
      <div class="card<?= empty($al['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('gallery_album', $al['id'], $al['card_title'] ?? $al['title']) ?>>
        <?= img_tag($al['image'], 'card_4x3', ['class' => 'card-img-top', 'alt' => '...', 'extra' => trim(ed_img('gallery_album', $al['id'], 'card_image_id'))]) ?>
        <div class="card-body">
          <h3 class="card-title"<?= ed_field('gallery_album', $al['id'], 'title') ?>><?= e($al['title']) ?></h3>
          <p class="card-text"<?= ed_field('gallery_album', $al['id'], 'card_sub') ?>><?= e($al['card_sub']) ?></p>
          <a href="<?= e(album_url($al['slug'])) ?>" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>
<?php endforeach; ?>

<script>
// Shipped selectors/threshold, now through the shared helper in /js/site.js
// (R2); site.js loads at the end of the body, hence the DOMContentLoaded wrap.
window.addEventListener('DOMContentLoaded', function () {
  sjReveal('.gallery-carousel-reveal,.gallery-card-reveal,.gallery-text-reveal,.gallery-card-reveal2,.gallery-card-reveal3,.gallery-card-reveal4,.gallery-card-reveal5', 150, true);
});
</script>
