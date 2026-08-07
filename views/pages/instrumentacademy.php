<?php // Instrumental Academy — page body (single-document BS5 layout). Shared look via academy.css/js. ?>
<style>
  /* Per-page hero background for the .infra-new band (the only per-page difference). */
  .bg-1 { background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('/photos/insaca2.jpg') no-repeat; background-size: cover; }
</style>

<section class="back-bar">
  <div class="back-text back-bar-reveal">
    <h4>Instrumental Academy</h4>
    <p>Musical Instruments</p>
  </div>
</section>

<!-- content -->
<section class="infra-new bg-1">
  <section class="infra-new-carousel">
    <div id="instrumentacademyCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#instrumentacademyCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#instrumentacademyCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#instrumentacademyCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
      </div>
      <div class="carousel-inner">
        <div class="carousel-item active">
          <img class="d-block w-100" src="/photos/insaca1.jpg" alt="First slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/insaca3.jpg" alt="Second slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/insaca2.jpg" alt="Third slide">
        </div>
      </div>
      <a class="carousel-control-prev" href="#instrumentacademyCarousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#instrumentacademyCarousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="infra-new-text">
    <h4 class="infra-new-reveal">Instrumental Academy</h4>
    <p> A musical instrument academy is an educational club dedicated to teaching students how to play various <span style="color:#ffd700;font-size:25px;font-weight:bolder;">Musical Instruments</span>.
        These academies cater to a wide range of skill levels, from beginners to intermediate musicians, and offer instruction in an electronic instruments like keyboard and more. A key feature of a musical instrument academy is its staff of qualified instructors. These instructors often have extensive backgrounds
        in music education, performance, and pedagogy, ensuring that students receive high-quality instruction. Academies typically offer a diverse curriculum that includes individual lessons, group classes, music theory, ear training, and performance opportunities. Many academies emphasize the importance of performance
        by organizing recitals, concerts, and other events where students can showcase their talents. Attending an academy provides a structured learning environment, which can be more effective than self-study or informal lessons. With professional instructors, a diverse curriculum, and ample performance opportunities,
        these academies play a crucial role in nurturing the next generation of musicians.
    </p>
  </div>
</section>
