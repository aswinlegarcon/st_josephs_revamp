

<?php  include "_libs/load.php" ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>St.Joseph's MHSS, Ondipudur</title>
    <link rel="icon" href="/photos/logo-main.png" type="image/x-icon">
    <link rel="stylesheet" href="/css/co-curriculum.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet"
    integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
   
</head>

<body class="co-curriculum">
    
<?php  get_templates('preloader');?>
<?php  get_templates('navbar');?>
<?php  get_templates('scroll-up');?>

<!-- top carousel -->
<section class="abt-carousel ">
<div id="carouselExampleSlidesOnly" class="carousel slide carousel-fade" data-ride="carousel" data-interval="2000">
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img class="d-block w-100" src="/photos/band1.jpg" alt="First slide">
      <div class="carousel-caption text-left">
          <h5 class="abt-carousel-reveal">Our Co-Curriculum</h5>
          <p class="abt-carousel-reveal">About our co-curriculum</p>

      </div>
    </div>
    <div class="carousel-item">
      <img class="d-block w-100" src="photos/vocaca1.jpg" alt="Second slide">
      <div class="carousel-caption text-left">
      <h5>Our Co-Curriculum</h5>
          <p>About our co-curriculum</p>
      </div>
    </div>
    <div class="carousel-item">
      <img class="d-block w-100" src="photos/ncc1.jpg" alt="Third slide">
      <div class="carousel-caption text-left">
      <h5>Our Co-Curriculum</h5>
          <p>About our co-curriculum</p>
      </div>
    </div>
  </div>
</div>
</section>

<!-- co-curriculumment cards start -->

<div class="home-text">
    <h2  class="span-reveal">The <span>Co Curriculum </span> of St.Joseph's</h2>
</div>

<section class="co-curriculum-all">
<section class="co-curriculum-card co-curriculum-card-reveal">
    <div class="row mt-5">
        <div class="col-md-4">
          <div class="card">
            <img src="photos/tamaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Academy of Tamil</h3>
              <p class="card-text">Valanar Ilakkiya Mandram</p>
              <a class="btn btn-primary btn-lg" href="tamilacademy.php" role="button">Read more</a>
              
            </div>
          </div>
        </div>
  
        <div class="col-md-4">
          <div class="card">
            <img src="photos/engaca2.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Academy of English</h3>
            <p class="card-text">Excel English Academy</p>
            <a class="btn btn-primary btn-lg" href="englishacademy.php" role="button">Read more</a>
            </div>
          </div>
        </div>
   
      <div class="col-md-4">
          <div class="card">
            <img src="photos/mataca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Academy of Maths</h3>
            <p class="card-text">Math Magician Academy</p>
            <a class="btn btn-primary btn-lg" href="mathsacademy.php" role="button">Read more</a>
            </div>
            </div>
    </section>

    <!-- sec-2 -->

    <section class="co-curriculum-card co-curriculum-card-reveal2">
    <div class="row mt-5">
        <div class="col-md-4">
          <div class="card">
            <img src="photos/sciaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Academy of Science</h3>
              <p class="card-text">Masterminds Academy</p>
              <a class="btn btn-primary btn-lg" href="scienceacademy.php" role="button">Read more</a>
              
            </div>
          </div>
        </div>
  
        <div class="col-md-4">
          <div class="card">
            <img src="photos/sstaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Academy of Social Science</h3>
            <p class="card-text">Patriotic Panthers Academy</p>
            <a class="btn btn-primary btn-lg" href="socialacademy.php" role="button">Read more</a>
            </div>
          </div>
        </div>
   
      <div class="col-md-4">
          <div class="card">
            <img src="photos/langaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Academy of Foreign Languages</h3>
            <p class="card-text">Spanish - German - French</p>
            <a class="btn btn-primary btn-lg" href="langacademy.php" role="button">Read more</a>
            </div>
            </div>
    </section>

    <!-- sec-3 -->
    <section class="co-curriculum-card co-curriculum-card-reveal3">
    <div class="row mt-5">
        <div class="col-md-4">
          <div class="card">
            <img src="photos/insaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Instrumental Academy</h3>
              <p class="card-text">Musical Instruments</p>
              <a class="btn btn-primary btn-lg" href="instrumentacademy.php" role="button">Read more</a>
              
            </div>
          </div>
        </div>
  
        <div class="col-md-4">
          <div class="card">
            <img src="photos/karaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Academy of Martial Arts</h3>
            <p class="card-text">Karate</p>
            <a class="btn btn-primary btn-lg" href="martialacademy.php" role="button">Read more</a>
            </div>
          </div>
        </div>
   
      <div class="col-md-4">
          <div class="card">
            <img src="photos/comeaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Academy of Communicative English</h3>
            <p class="card-text">Effective Communication</p>
            <a class="btn btn-primary btn-lg" href="communicativeacademy.php" role="button">Read more</a>
            </div>
            </div>
    </section>
    <!-- sec-4 -->

    <section class="co-curriculum-card co-curriculum-card-reveal4">
    <div class="row mt-5">
        <div class="col-md-4">
          <div class="card">
            <img src="photos/spoaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Academy of Athletes and Sports</h3>
              <p class="card-text">Sports</p>
              <a class="btn btn-primary btn-lg" href="sportsacademy.php" role="button">Read more</a>
              
            </div>
          </div>
        </div>
  
        <div class="col-md-4">
          <div class="card">
            <img src="photos/vocaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Vocal Academy</h3>
            <p class="card-text">Chorus Singing</p>
            <a class="btn btn-primary btn-lg" href="vocalacademy.php" role="button">Read more</a>
            </div>
          </div>
        </div>
   
      <div class="col-md-4">
          <div class="card">
            <img src="photos/yogaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Academy of Yoga</h3>
            <p class="card-text">Yoga </p>
            <a class="btn btn-primary btn-lg" href="yogaacademy.php" role="button">Read more</a>
            </div>
            </div>
    </section>
    <!-- sec-5 -->
     
    <section class="co-curriculum-card co-curriculum-card-reveal4">
    <div class="row mt-5">
    <div class="col-md-4">
          <div class="card">
            <img src="photos/danceaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Academy of Classical Dance</h3>
            <p class="card-text">Bharatanatyam</p>
            <a class="btn btn-primary btn-lg" href="danceacademy.php" role="button">Read more</a>
            </div>
            </div>
</div>
        <div class="col-md-4">
          <div class="card">
            <img src="photos/band1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
              <h3 class="card-title">Band</h3>
              <p class="card-text">St. Joseph’s Tradition of Excellence</p>
              <a class="btn btn-primary btn-lg" href="band.php" role="button">Read more</a>
              
            </div>
          </div>
        </div>
   
      <div class="col-md-4">
          <div class="card">
            <img src="photos/ncc1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">NCC</h3>
            <p class="card-text">National Cadet Corps</p>
            <a class="btn btn-primary btn-lg" href="ncc.php" role="button">Read more</a>
            </div>
            </div>

         
    </section>
    </section>
    <!-- sec-6 -->

    <section class="co-curriculum-card co-curriculum-card-reveal5">
    <div class="row mt-5">
    <div class="col-md-4">
          <div class="card">
            <img src="photos/abacaca1.jpeg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Academy of Abacus</h3>
            <p class="card-text">Saibodhi Abacus Academy</p>
            <a class="btn btn-primary btn-lg" href="abacusacademy.php" role="button">Read more</a>
            </div>
            </div> 
</div> 

    <div class="col-md-4">
          <div class="card">
            <img src="photos/artexpo0.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Art and Crafts Expo</h3>
            <p class="card-text">Innovation</p>
            <a class="btn btn-primary btn-lg" href="artandexpo.php" role="button">Read more</a>
            </div>
            </div> 
            </div>

            <div class="col-md-4">
          <div class="card">
            <img src="photos/artaca1.jpg" class="card-img-top" alt="...">
            <div class="card-body">
            <h3 class="card-title">Academy of Art and Crafts</h3>
            <p class="card-text">Creative Thinking</p>
            <a class="btn btn-primary btn-lg" href="artacademy.php" role="button">Read more</a>
            </div>
            </div>  
            
    </section>
    </section>

   
    <?php  get_templates('jumbotron');?>
    <?php  get_templates('footer');?>
<script src="/js/co-curriculum.js"></script>
<script>
    

    window.addEventListener('DOMContentLoaded', reveal);
    window.addEventListener('scroll', reveal);
    function reveal() {
      var reveals = document.querySelectorAll('.co-curriculum-card-reveal,.co-curriculum-text-reveal,.abt-carousel-reveal');
      var windowHeight = window.innerHeight;
      var revealPoint = 50;
    
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

<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" 
integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

  
   
</body>
</html>


</body>
</html>