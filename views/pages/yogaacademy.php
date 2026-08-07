<?php // Yoga Academy — page body (single-document BS5 layout). Shared look via academy.css/js. ?>
<style>
  /* Per-page hero background for the .infra-new band (the only per-page difference). */
  .bg-1 { background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('/photos/yogaca1.jpg') no-repeat; background-size: cover; }
</style>

<section class="back-bar">
  <div class="back-text back-bar-reveal">
    <h4>Academy of Yoga</h4>
    <p>Yoga</p>
  </div>
</section>

<!-- content -->
<section class="infra-new bg-1">
  <section class="infra-new-carousel">
    <div id="yogaacademyCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#yogaacademyCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#yogaacademyCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#yogaacademyCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
      </div>
      <div class="carousel-inner">
        <div class="carousel-item active">
          <img class="d-block w-100" src="/photos/yogaca1.jpg" alt="First slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/yogaaca2.jpg" alt="Second slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/yogaaca3.jpg" alt="Third slide">
        </div>
      </div>
      <a class="carousel-control-prev" href="#yogaacademyCarousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#yogaacademyCarousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="infra-new-text">
    <h4 class="infra-new-reveal">Academy of Yoga</h4>
      <p>

      Yoga classes are offered to the students to awake their individual consciousness by the unison of mind and body. Yoga consists of engaging exercises called
      asanas or postures and pranayama which includes breathing exercises.Yoga is an invaluable practice introduced in schools to enhance students’ overall well-being.
      By incorporating yoga into the daily routine, students can experience numerous benefits, including improved concentration, better memory retention, and reduced stress levels.
       The physical postures (asanas) help in developing flexibility, strength, and balance, while the breathing exercises (pranayama) promote relaxation and mental clarity.

    </p>
  </div>
</section>
