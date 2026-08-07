<?php // NCC — page body (single-document BS5 layout). Shared look via academy.css/js. ?>
<style>
  /* Per-page hero background for the .infra-new band (the only per-page difference). */
  .bg-1 { background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('/photos/ncc1.jpg') no-repeat; background-size: cover; }
</style>

<section class="back-bar">
  <div class="back-text back-bar-reveal">
    <h4>NCC</h4>
    <p> National Cadet Corps</p>
  </div>
</section>

<!-- content -->
<section class="infra-new bg-1">
  <section class="infra-new-carousel">
    <div id="nccCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#nccCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#nccCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#nccCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
      </div>
      <div class="carousel-inner">
        <div class="carousel-item active">
          <img class="d-block w-100" src="/photos/ncc2.jpg" alt="First slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/ncc3.jpg" alt="Second slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/ncc1.jpg" alt="Third slide">
        </div>
      </div>
      <a class="carousel-control-prev" href="#nccCarousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#nccCarousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="infra-new-text">
    <h4 class="infra-new-reveal">NCC</h4>
    <p>
    The National Cadet Corps(NCC) is a organization in our school that aims to develop character Comradeship,
     Discipline, Leadership Secular Outlook, Spirit of Adventure and Ideals of Selfless Service among young citizens.
      It operates under the Ministry of Defence.raining camp are conducted to impart
      Military training, physical fitness and leadership skills.
    </p>
    <p>
      <span style="color:#ffd700;font-size:24px;font-weight:bolder;">  Community service: </span> Involvement in Social service activities, Blood donations, cleanliness drives and disaster relief. <br>
      <span style="color:#ffd700;font-size:24px;font-weight:bolder;">Competitions: </span>Inter unit, Inter group and National level competitions in drill shooting and cultural activities and events.
    </p>
  </div>
</section>
