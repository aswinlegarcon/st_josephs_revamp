<?php // Art Academy — page body (single-document BS5 layout). Shared look via academy.css/js. ?>
<style>
  /* Per-page hero background for the .infra-new band (the only per-page difference). */
  .bg-1 { background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('/photos/artaca1.jpg') no-repeat; background-size: cover; }
</style>

<section class="back-bar">
  <div class="back-text back-bar-reveal">
    <h4>Academy of Art and Crafts</h4>
    <p>Creative Thinking</p>
  </div>
</section>

<!-- content -->
<section class="infra-new bg-1">
  <section class="infra-new-carousel">
    <div id="artacademyCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <div class="carousel-indicators">
        <button type="button" data-bs-target="#artacademyCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#artacademyCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#artacademyCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
      </div>
      <div class="carousel-inner">
        <div class="carousel-item active">
          <img class="d-block w-100" src="/photos/artaca2.jpg" alt="First slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/artaca3.jpg" alt="Second slide">
        </div>
        <div class="carousel-item">
          <img class="d-block w-100" src="/photos/artaca4.jpg" alt="Third slide">
        </div>
      </div>
      <a class="carousel-control-prev" href="#artacademyCarousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#artacademyCarousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="infra-new-text">
    <h4 class="infra-new-reveal">Academy of Art and Crafts</h4>
    <p>Art and crafting encompass a wide range of creative activities, from painting and drawing to sculpting, knitting, and more.
        It's a wonderful way to  <span style="color:#ffd700;font-size:25px;font-weight:bolder;">express creativity, relax, and create something unique</span>. What specific aspect of art or crafting are students interested in?
        As a milestone Art and craft expo was conducted  every year participating in an art and crafting expo can be a fantastic experience! Students were
        showcasing their creativity and demonstrations.
        It's a great opportunity to discover new techniques, get inspired by different styles,</p>
  </div>
</section>
