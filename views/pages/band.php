<?php // Band — page body (single-document BS5 layout). Shared look via academy.css/js. ?>
<style>
  /* Per-page hero background for the .infra-new band (the only per-page difference). */
  .bg-1 { background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('/photos/band1.jpg') no-repeat; background-size: cover; }
</style>

<section class="back-bar">
  <div class="back-text back-bar-reveal">
    <h4>Band</h4>
    <p>St. Joseph’s Tradition of Excellence</p>
  </div>
</section>

<!-- content -->
<section class="infra-new bg-1">
  <section class="infra-new-carousel">
    <div id="bandCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#bandCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#bandCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#bandCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
      </div>
      <div class="carousel-inner">
        <div class="carousel-item active">
          <img class="d-block w-100" src="/photos/band2.jpeg" alt="First slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/band3.jpg" alt="Second slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/band1.jpg" alt="Third slide">
        </div>
      </div>
      <a class="carousel-control-prev" href="#bandCarousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#bandCarousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="infra-new-text">
    <h4 class="infra-new-reveal">Band</h4>
    <p>
    St. Joseph’s Tradition of Excellence

The St. Joseph’s Band stands as a testament to our   <span style="color:#ffd700;font-size:24px;font-weight:bolder;">School's rich musical heritage.</span>
 Comprising talented students from diverse backgrounds and grade levels, the band
  epitomizes teamwork, discipline, and creativity. Their repertoire spans genres and
  styles, showcasing their instrumental proficiency and performance skills. Whether
  at school assemblies, community events, or prestigious competitions, our musicians
  consistently captivate audiences with their energy, enthusiasm, and dedication to
   musical excellence. We proudly celebrate their achievements and eagerly anticipate
   many more inspiring performances.
    </p>
      <p>

      St.Joseph’s Band is rich in Musical Heritage. It embodies the spirit of
      teamwork, discipline and creativity. The musicians impress the audiences
       with energy and enthusiasm in taking part in Assemblies, Interschool
       competitions and excel in Musical Excellence.

    </p>
  </div>
</section>
