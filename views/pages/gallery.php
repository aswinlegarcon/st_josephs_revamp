<?php // Gallery page body (static; BS5 single-document layout). No preloader. The gal-slider is a custom cross-fade slideshow (its own CSS/JS, NOT Bootstrap) inlined below with the document wrapper stripped and image paths made absolute. ?>

<!-- gallery starts -->
<div class="gallery-text gallery-text-reveal">
    <h4>School Gallery</h4>
    <p>Tons of Memories carry our Gallery</p>
</div>

<!-- gal-slider (inlined custom 15-image cross-fade slideshow; not Bootstrap) -->
<style>
    .gal-slider {
        margin: 0;
        padding: 0;
        height: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: #f0f0f0;
        font-family: Arial, sans-serif;
        overflow: hidden;
    }

    .container-slider {
        position: relative;
        width: 60%;
        margin:auto;
        height: 500px;
        border-radius: 10px;
        border: 5px solid #2b4b8a;
        box-shadow: 0px 0px 25px #2b4b8a;
        overflow: hidden;
    }

    .slide {
        position: absolute;
        width: 100%;
        height: 100%;
        background-size: cover;
        background-position: center;
        transition: opacity 1s ease-in-out;
    }

    @media (max-width: 768px) {
        .container-slider {
            width: 100%;
            height: 350px;
        }
    }
</style>
<div class="container-slider">
    <div class="slide" style="background-image: url(/photos/sportsday1.jpg); opacity: 1;"></div>
    <div class="slide" style="background-image: url(/photos/sportsday10.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/indday1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/indday12.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/childday1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/childday4.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/teachday1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/teachday9.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/expressday1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/expressday13.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/expo1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/expo18.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/gradday1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/gradday11.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(/photos/spach1.jpg); opacity: 0;"></div>
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

    // Preload images
    const images = [
        '/photos/sportsday1.jpg',
        '/photos/sportsday10.jpg',
        '/photos/indday1.jpg',
        '/photos/indday12.jpg',
        '/photos/childday1.jpg',
        '/photos/childday4.jpg',
        '/photos/teachday1.jpg',
        '/photos/teachday9.jpg',
        '/photos/expressday1.jpg',
        '/photos/expressday13.jpg',
        '/photos/expo1.jpg',
        '/photos/expo18.jpg',
        '/photos/gradday1.jpg',
        '/photos/gradday11.jpg',
        '/photos/spach1.jpg'
    ];

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
          <a href="/<?= e($al['slug']) ?>.php" class="btn btn-primary">More</a>
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
