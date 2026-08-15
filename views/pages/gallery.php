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
<?php // N4: slides come from image_links (panel-managed). N5: only the first
      // slide carries its background inline — the shipped page force-loaded
      // all 15 photos (~3.7 MB) before anything was shown; the rest now sit in
      // data-bg and are applied by the script two fades ahead, so the visible
      // slideshow is identical while the initial payload is one image. ?>
<?php foreach ($sj_slider_urls as $si => $u): ?>
    <div class="slide"<?= $si === 0
        ? ' style="background-image: url(' . e($u) . '); opacity: 1;"'
        : ' style="opacity: 0;" data-bg="' . e($u) . '"' ?>></div>
<?php endforeach; ?>
</div>

<script>
    const slides = document.querySelectorAll('.slide');
    let currentSlide = 0;
    const slideInterval = 2000; // 3 seconds

    // N5: lazy backgrounds — apply a slide's photo (from data-bg) just ahead
    // of its turn instead of downloading all of them up front.
    function ensureBg(i) {
        const s = slides[i % slides.length];
        if (s && !s.style.backgroundImage && s.dataset.bg) {
            s.style.backgroundImage = 'url(' + s.dataset.bg + ')';
        }
    }
    ensureBg(1); // the first fade's target is ready before the timer fires
    ensureBg(2);

    function showNextSlide() {
        slides[currentSlide].style.opacity = 0;
        currentSlide = (currentSlide + 1) % slides.length;
        ensureBg(currentSlide);
        ensureBg(currentSlide + 1); // stay one fade ahead
        slides[currentSlide].style.opacity = 1;
    }

    let slideTimer = setInterval(showNextSlide, slideInterval);

    document.querySelector('.container-slider').addEventListener('mouseover', () => {
        clearInterval(slideTimer);
    });

    document.querySelector('.container-slider').addEventListener('mouseout', () => {
        slideTimer = setInterval(showNextSlide, slideInterval);
    });

    // N5: the shipped eager preload of every photo is gone — ensureBg() above
    // fetches each slide one fade ahead instead.
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
