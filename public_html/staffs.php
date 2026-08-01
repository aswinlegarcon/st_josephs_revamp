

<?php include "_libs/load.php" ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>St.Joseph's MHSS, Ondipudur</title>
    <link rel="icon" href="/photos/logo-main.png" type="image/x-icon">
    <link rel="stylesheet" href="/css/staffs.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
   
</head>

<body class="infrastructure">
    
<?php  get_templates('preloader');?>
<?php get_templates('navbar');?>
<?php get_templates('scroll-up');?>

<!-- top carousel -->
<section class="abt-carousel ">
<div id="carouselExampleSlidesOnly" class="carousel slide carousel-fade" data-ride="carousel" data-interval="2000">
  <div class="carousel-inner">
    <div class="carousel-item active">
      <img class="d-block w-100" src="photos/staff1.jpg" alt="First slide">
      <div class="carousel-caption text-left abt-carousel-reveal">
          <h5>Our Staffs</h5>
          <p>About Our Staffs</p>

      </div>
    </div>
    <div class="carousel-item">
      <img class="d-block w-100" src="photos/teachertour.jpg" alt="Second slide">
      <div class="carousel-caption text-left">
      <h5>Our Staffs</h5>
          <p>About Our Staffs</p>
      </div>
    </div>
  
  </div>
</div>
</section>

<!-- infrastructurement cards start -->
<div class="infrastructure-container">
    <img src="/photos/staff1.jpg" alt="Left Image" class="infrastructure-image">
    <div class="infrastructure-text">
        <h4 class="infrastructure-text-reveal">We Love our Staffs</h4>
        <p>We are fortunate to have a dedicated and passionate team of 63 enthusiastic
           and committed teachers, supported by a diligent group of 28 non-teaching 
           staff members. Our educators recognize that staying updated is crucial 
           for success in the ever-evolving field of education. To ensure they are 
           well-equipped with the latest teaching methodologies and pedagogical 
           strategies, our teachers actively participate in various professional 
           development opportunities. These include seminars, training sessions,
            orientation classes, and workshops that cover a broad spectrum of 
            topics relevant to modern education..</p>
        <p>Additionally, recognizing the importance of maintaining high levels of 
          motivation and energy, we organize an annual tour for our teachers. This 
          tour serves as an opportunity for them to relax, refresh, and recharge away 
          from the routine of school life. It fosters camaraderie and team spirit, 
          providing a perfect blend of professional development and personal 
          rejuvenation. By investing in our teachers' continuous growth and well-being
          , we ensure that they remain inspired and capable of delivering the highest
           quality education to our students.</p>
    </div>
    <img src="/photos/teachertour.jpg" alt="Right Image" class="infrastructure-image">
</div>




<!-- new template -->
<section class="newtemp-body">
<div class="about-container">
        <div class="about-section">
            <img src="/photos/staff1.jpg" alt="Left Image" class="about-image">
            <div class="about-content">
                <h2 class="infrastructure-text-reveal">The Staffs</h2>
                <p>We are fortunate to have a dedicated and passionate team of 63 enthusiastic
           and committed teachers, supported by a diligent group of 28 non-teaching 
           staff members. Our educators recognize that staying updated is crucial 
           for success in the ever-evolving field of education.</p>

              </div>
              
        </div>
        <div class="about-section">
            <div class="about-content">
                <h2 class="infrastructure-text-reveal">The Staff Tour</h2>
                <p>The school staff recently embarked on a rejuvenating trip to Coorg, 
                  a picturesque hill station in Karnataka known for its lush greenery and 
                  serene landscapes. The journey provided a much-needed break from their daily
                   routines,
                   allowing them to unwind and recharge amidst nature’s beauty.
                     This trip not only refreshed their minds and bodies but also strengthened
                      their camaraderie, making it a memorable and enriching experience for everyone
                       involved</p>
            </div>
            <img src="/photos/teachertour.jpg" alt="Right Image" class="about-image">
        </div>
       
    </div>
    </section>
    <!-- template end -->
    <?php  get_templates('footer');?>

<script>
    

    window.addEventListener('DOMContentLoaded', reveal);
    window.addEventListener('scroll', reveal);
    function reveal() {
      var reveals = document.querySelectorAll('.infra-new-reveal,.infrastructure-text-reveal,.abt-carousel-reveal');
      var windowHeight = window.innerHeight;
      var revealPoint = 100;
    
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


























