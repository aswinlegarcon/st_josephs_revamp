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
<section class="gallery-card gallery-card-reveal">
  <div class="row mt-5">
    <div class="col-md-4">
      <div class="card">
        <img src="/photos/annualday12.jpg" class="card-img-top" alt="...">
        <div class="card-body">
          <h3 class="card-title">Annual Day</h3>
          <p class="card-text">2023 &amp; 2024</p>
          <a href="/gal-annual.php" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <img src="/photos/sports.jpeg" class="card-img-top" alt="...">
        <div class="card-body">
          <h3 class="card-title">Sports Day</h3>
          <p class="card-text">2023 &amp; 2024 </p>
          <a href="/gal-sports.php" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <img src="/photos/indday1.jpg" class="card-img-top" alt="...">
        <div class="card-body">
          <h3 class="card-title">Independence Day</h3>
          <p class="card-text">2023 &amp; 2024</p>
          <a href="/gal-independence.php" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
</section>

<!-- sec -2 -->
<section class="gallery-card gallery-card-reveal2">
  <div class="row mt-5">
    <div class="col-md-4">
      <div class="card">
        <img src="/photos/childday1.jpg" class="card-img-top" alt="...">
        <div class="card-body">
          <h3 class="card-title">Children's Day</h3>
          <p class="card-text">2023</p>
          <a href="/gal-children.php" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <img src="/photos/teachday1.jpg" class="card-img-top" alt="...">
        <div class="card-body">
          <h3 class="card-title">Teachers Day</h3>
          <p class="card-text">2023</p>
          <a href="/gal-teacher.php" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <img src="/photos/expressday20.jpg" class="card-img-top" alt="...">
        <div class="card-body">
          <h3 class="card-title">Expressionz Day</h3>
          <p class="card-text">2023</p>
          <a href="/gal-expressionz.php" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
</section>

<!-- sec-3 -->
<section class="gallery-card gallery-card-reveal3">
  <div class="row mt-5">
    <div class="col-md-4">
      <div class="card">
        <img src="/photos/expo1.jpg" class="card-img-top" alt="...">
        <div class="card-body">
          <h3 class="card-title">Science Expo</h3>
          <p class="card-text">2023 &amp; 2024</p>
          <a href="/gal-expo.php" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <img src="/photos/gradday1.jpg" class="card-img-top" alt="...">
        <div class="card-body">
          <h3 class="card-title">KG Graduation</h3>
          <p class="card-text">2023</p>
          <a href="/gal-grad.php" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card">
        <img src="/photos/alumni1.png" class="card-img-top" alt="...">
        <div class="card-body">
          <h3 class="card-title">Alumni</h3>
          <p class="card-text">2023</p>
          <a href="/gal-alumni.php" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
</section>

<section class="gallery-card gallery-card-reveal3">
  <div class="row mt-5">
    <div class="col-md-4">
      <div class="card">
        <img src="/photos/spach14.jpg" class="card-img-top" alt="...">
        <div class="card-body">
          <h3 class="card-title">Sports Achivements</h3>
          <p class="card-text">2023</p>
          <a href="/gal-spach.php" class="btn btn-primary">More</a>
        </div>
      </div>
    </div>
</section>

<script>
    window.addEventListener('DOMContentLoaded', reveal);
    window.addEventListener('scroll', reveal);
    function reveal() {
      var reveals = document.querySelectorAll('.gallery-carousel-reveal,.gallery-card-reveal,.gallery-text-reveal,.gallery-card-reveal2,.gallery-card-reveal3,.gallery-card-reveal4,.gallery-card-reveal5');
      var windowHeight = window.innerHeight;
      var revealPoint = 150;

      reveals.forEach(function(revealElement) {
        var revealTop = revealElement.getBoundingClientRect().top;

        if (revealTop < windowHeight - revealPoint) {
          revealElement.classList.add('active');
        } else {
          revealElement.classList.remove('active');
        }
      });
    }
</script>
