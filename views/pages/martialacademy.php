<?php // Martial Arts Academy — page body (single-document BS5 layout). Shared look via academy.css/js. ?>
<style>
  /* Per-page hero background for the .infra-new band (the only per-page difference). */
  .bg-1 { background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('/photos/karaca1.jpg') no-repeat; background-size: cover; }
</style>

<section class="back-bar">
  <div class="back-text back-bar-reveal">
    <h4>Martial Arts Academy</h4>
    <p>Karate</p>
  </div>
</section>

<!-- content -->
<section class="infra-new bg-1">
  <section class="infra-new-carousel">
    <div id="martialacademyCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#martialacademyCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#martialacademyCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#martialacademyCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
      </div>
      <div class="carousel-inner">
        <div class="carousel-item active">
          <img class="d-block w-100" src="/photos/karaca2.jpg" alt="First slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/karaca3.jpg" alt="Second slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/karaca4.jpg" alt="Third slide">
        </div>
      </div>
      <a class="carousel-control-prev" href="#martialacademyCarousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#martialacademyCarousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="infra-new-text">
    <h4 class="infra-new-reveal">Martial Arts Academy</h4>
    <p> Karate is maritial art which equip the children with
        confidence and self defence skills. This self confidence
        navigate them to tackle the problemsin the world. it strengthens
        the entire body, improves the stamina, builts the coordination and
        trains the character to the integrity. we offer karate classes to the
        5 grade students on every wednesday. In  addition to that Monday to
        wednesday  a special coaching offered for the classes of 1st to 9th grade
          in the evening 3:30 p.m. to 4:30 p.m.
    </p>
  </div>
</section>
