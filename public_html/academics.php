<?php  include "_libs/load.php" ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>St.Joseph's MHSS, Ondipudur</title>
    <link rel="icon" href="/photos/logo-main.png" type="image/x-icon">
    <link rel="stylesheet" href="/css/academics.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Open+Sans:400,300,300italic,400italic,600,600italic,700,700italic" rel='stylesheet' type='text/css'>
    <link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
</head>

<body class="academics">
    
<?php  get_templates('preloader');?>
<?php  get_templates('navbar');?>
<?php  get_templates('scroll-up');?>

<section class="academy-carousel ">
<div id="carouselExampleSlidesOnly" class="carousel slide carousel-fade" data-ride="carousel" data-interval="2000">
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img class="d-block w-100" src="photos/kg1.jpg" alt="First slide">
      <div class="carousel-caption text-left highsec-carousel-reveal">
          <h5>Academics</h5>
          <p>Embrace the challenges of learning, for they are stepping stones towards your academic success.</p>
      </div>
    </div>
    <div class="carousel-item">
      <img class="d-block w-100" src="photos/high1.jpg" alt="Second slide">
      <div class="carousel-caption text-left">
      <h5>Academics</h5>
      <p>Embrace the challenges of learning, for they are stepping stones towards your academic success.</p>
      </div>
    </div>
    <div class="carousel-item">
      <img class="d-block w-100" src="photos/highsec1.jpg" alt="Third slide">
      <div class="carousel-caption text-left">
      <h5>Academics</h5>
      <p>Embrace the challenges of learning, for they are stepping stones towards your academic success.</p>
      </div>
    </div>
  </div>
</div>
</section>
<div class="home-text">
    <h2  class="span-reveal">The <span>Academics </span> in St.Joseph's</h2>
</div>
<section class="academy-card academy-card-reveal">
<div class="row mt-4">
        <div class="col-md-3">
          <div class="card">
            <img src="/photos/kg1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Kinder Garten(KG)</h3>
              <p class="card-text">LKG-UKG</p>
              <a href="kg.php" class="btn btn-primary">Explore</a>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card">
            <img src="/photos/primary4.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Primary School</h3>
              <p class="card-text">1st - 5th</p>
              <a href="primary.php" class="btn btn-primary">Explore</a>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card">
            <img src="/photos/high1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">High School</h3>
              <p class="card-text">6th - 10th</p>
              <a href="highschl.php" class="btn btn-primary">Explore</a>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card">
            <img src="/photos/highsec1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Higher Secondary</h3>
              <p class="card-text">11th - 12th</p>
              <a href="highsec.php" class="btn btn-primary">Explore</a>
            </div>
          </div>
        </div>
        </section>
    <script>
    

    window.addEventListener('DOMContentLoaded', reveal);
    window.addEventListener('scroll', reveal);
    function reveal() {
      var reveals = document.querySelectorAll('.back-bar-reveal,.academy-card-reveal,.academy-text-reveal');
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
<?php  get_templates('footer');?>
</body>
</html>