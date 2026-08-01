<?php  include "_libs/load.php" ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>St.Joseph's MHSS, Ondipudur</title>
    <link rel="icon" href="/photos/logo-main.png" type="image/x-icon">
    <link rel="stylesheet" href="/css/gallery.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
   
</head>

<body class="gallery">
    

<?php  get_templates('navbar');?>
<?php  get_templates('scroll-up');?>

<!-- gallery starts -->
<div class="gallery-text gallery-text-reveal">
    <h4>School Gallery</h4>
    <p>Tons of Memories carry our Gallery</p>
</div>
<?php  get_templates('gal-slider');?>

<section class="gallery-card gallery-card-reveal">
<div class="row mt-5">
        <div class="col-md-4">
          <div class="card">
            <img src="/photos/annualday12.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Annual Day</h3>
              <p class="card-text">2023 & 2024</p>
              <a href="gal-annual.php" class="btn btn-primary">More</a>
            </div>
          </div>
        </div>
        
       
        <div class="col-md-4">
          <div class="card">
            <img src="photos/sports.jpeg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Sports Day</h3>
              <p class="card-text">2023 & 2024 </p>
              <a href="gal-sports.php" class="btn btn-primary">More</a>
            </div>
          </div>
        </div>
        
        <div class="col-md-4">
          <div class="card">
            <img src="photos/indday1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Independence Day</h3>
              <p class="card-text">2023 & 2024</p>
              <a href="gal-independence.php" class="btn btn-primary">More</a>
            </div>
          </div>
        </div>
        </section>
   
        <!-- sec -2 -->
      
        <section class="gallery-card gallery-card-reveal2">
<div class="row mt-5">
        <div class="col-md-4">
          <div class="card">
            <img src="photos/childday1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Children's Day</h3>
              <p class="card-text">2023</p>
              <a href="gal-children.php" class="btn btn-primary">More</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <img src="photos/teachday1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Teachers Day</h3>
              <p class="card-text">2023</p>
              <a href="gal-teacher.php" class="btn btn-primary">More</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <img src="photos/expressday20.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Expressionz Day</h3>
              <p class="card-text">2023</p>
              <a href="gal-expressionz.php" class="btn btn-primary">More</a>
            </div>
          </div>
        </div>
        </section>

     
        <!-- sec-3 -->

        <section class="gallery-card gallery-card-reveal3">
<div class="row mt-5">
        <div class="col-md-4">
          <div class="card">
            <img src="photos/expo1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Science Expo</h3>
              <p class="card-text">2023 & 2024</p>
              <a href="gal-expo.php" class="btn btn-primary">More</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <img src="photos/gradday1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">KG Graduation</h3>
              <p class="card-text">2023</p>
              <a href="gal-grad.php" class="btn btn-primary">More</a>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="card">
            <img src="photos/alumni1.png" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Alumni</h3>
              <p class="card-text">2023</p>
              <a href="gal-alumni.php" class="btn btn-primary">More</a>
            </div>
          </div>
        </div>
        </section>
        <section class="gallery-card gallery-card-reveal3">
<div class="row mt-5">
        <div class="col-md-4">
          <div class="card">
            <img src="photos/spach14.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Sports Achivements</h3>
              <p class="card-text">2023</p>
              <a href="gal-spach.php" class="btn btn-primary">More</a>
            </div>
          </div>
        </div>
        
         
        </section>

    


        <?php  get_templates('footer');?>

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
</body>
</html>