<?php // Art and Crafts Expo — page body (single-document BS5 layout). Shared look via academy.css/js. ?>
<style>
  /* Per-page hero background for the .infra-new band (the only per-page difference). */
  .bg-1 { background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('/photos/artexpo0.jpg') no-repeat; background-size: cover; }
</style>

<section class="back-bar">
  <div class="back-text back-bar-reveal">
    <h4>Art and Crafts Expo</h4>
    <p>Innovation</p>
  </div>
</section>

<!-- content -->
<section class="infra-new bg-1">
  <section class="infra-new-carousel">
    <div id="artandexpoCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#artandexpoCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#artandexpoCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#artandexpoCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
      </div>
      <div class="carousel-inner">
        <div class="carousel-item active">
          <img class="d-block w-100" src="/photos/artexpo1.jpg" alt="First slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/artexpo2.jpg" alt="Second slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/artexpo3.jpg" alt="Third slide">
        </div>
      </div>
      <a class="carousel-control-prev" href="#artandexpoCarousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#artandexpoCarousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="infra-new-text">
    <h4 class="infra-new-reveal">Art and Crafts Expo</h4>
      <p>

      "Unlock your child's creative potential with St.Joseph Matric HR.Sec School!
   Our teachers motivate the students by,boosting: <br>

   <span style="color:#ffd700;font-size:24px;font-weight:bolder;">- Creativness. <br>
- Community engagement.<br>
- Cultural exchange.<br> </span>

    </p>
  </div>
</section>
