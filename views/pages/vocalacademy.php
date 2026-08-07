<?php // Vocal Academy — page body (single-document BS5 layout). Shared look via academy.css/js. ?>
<style>
  /* Per-page hero background for the .infra-new band (the only per-page difference). */
  .bg-1 { background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('/photos/vocaca1.jpg') no-repeat; background-size: cover; }
</style>

<section class="back-bar">
  <div class="back-text back-bar-reveal">
    <h4>Vocal Academy</h4>
    <p>Chorus Singing</p>
  </div>
</section>

<!-- content -->
<section class="infra-new bg-1">
  <section class="infra-new-carousel">
    <div id="vocalacademyCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#vocalacademyCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#vocalacademyCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#vocalacademyCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
      </div>
      <div class="carousel-inner">
        <div class="carousel-item active">
          <img class="d-block w-100" src="/photos/vocaca2.jpg" alt="First slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/vocaca3.jpg" alt="Second slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/vocaca1.jpg" alt="Third slide">
        </div>
      </div>
      <a class="carousel-control-prev" href="#vocalacademyCarousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#vocalacademyCarousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="infra-new-text">
    <h4 class="infra-new-reveal">Vocal Academy</h4>
      <p>

      The students of our vocal academy receive training in the context of musicality and technicality in the field of music. The structure of the lesson
       plans for the vocal academy has been formulated in a way to provide the in-depth understanding of music to all age groups of the academy. Over the
       training sessions the students gain knowledge on the theoretical and technical aspects with <span style="color:#ffd700;font-size:24px;font-weight:bolder;">Practical</span> examples. The vocal academy not only aims at
       uplifting the student in their vocal skills but also aims at developing the student’s
      potential as a performer which gets applied to every other field in the longer run.
      <p>
      The academy also provides awareness about international music and also gets
      the students used to various fields of music such as the musical theatre, arts
      and drama. St.Joseph’s Vocal Academy is a standalone academy which has its unique
       framework to train the students of all age groups. The academy not only creates talented musicians but also creates students with unique personality and charisma.
      </p>

    </p>
  </div>
</section>
