<?php // Academics page body (static content; becomes DB-driven later). Bootstrap 5 dialect. ?>
<!-- top carousel -->
<section class="academy-carousel">
  <div id="academicsHeroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
    <div class="carousel-inner">
      <div class="carousel-item active">
        <img class="d-block w-100" src="/photos/kg1.jpg" alt="First slide">
        <div class="carousel-caption text-start highsec-carousel-reveal">
          <h5>Academics</h5>
          <p>Embrace the challenges of learning, for they are stepping stones towards your academic success.</p>
        </div>
      </div>
      <div class="carousel-item">
        <img class="d-block w-100" src="/photos/high1.jpg" alt="Second slide">
        <div class="carousel-caption text-start">
          <h5>Academics</h5>
          <p>Embrace the challenges of learning, for they are stepping stones towards your academic success.</p>
        </div>
      </div>
      <div class="carousel-item">
        <img class="d-block w-100" src="/photos/highsec1.jpg" alt="Third slide">
        <div class="carousel-caption text-start">
          <h5>Academics</h5>
          <p>Embrace the challenges of learning, for they are stepping stones towards your academic success.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- heading band -->
<div class="home-text">
  <h2 class="span-reveal">The <span>Academics </span> in St.Joseph's</h2>
</div>

<!-- grade-level cards -->
<section class="academy-card academy-card-reveal">
  <div class="row mt-4">
    <?php foreach ($sj_sections as $sec): ?>
    <div class="col-md-3">
      <div class="card">
        <?= img_tag($sec['image'], 'card_4x3', ['class' => 'card-img-top', 'alt' => '...', 'extra' => trim(ed_img('school_section', $sec['id'], 'card_image_id'))]) ?>
        <div class="card-body">
          <h3 class="card-title"<?= ed_field('school_section', $sec['id'], 'card_title') ?>><?= e($sec['card_title']) ?></h3>
          <p class="card-text"<?= ed_field('school_section', $sec['id'], 'card_range') ?>><?= e($sec['card_range']) ?></p>
          <a href="<?= e($sec['slug']) ?>.php" class="btn btn-primary">Explore</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<script>
  window.addEventListener('DOMContentLoaded', reveal);
  window.addEventListener('scroll', reveal);
  function reveal() {
    var reveals = document.querySelectorAll('.back-bar-reveal,.academy-card-reveal,.academy-text-reveal');
    var windowHeight = window.innerHeight;
    var revealPoint = 150;

    reveals.forEach(function (revealElement) {
      var revealTop = revealElement.getBoundingClientRect().top;

      if (revealTop < windowHeight - revealPoint) {
        revealElement.classList.add('active');
      } else {
        revealElement.classList.remove('active');
      }
    });
  }
</script>
