<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responsive Navigation Bar</title>
    <style>
      

*{font-family: "League Spartan", sans-serif;
font-weight: 400;}

body {
    margin: 0;
    font-family: Arial, sans-serif;
}

/* Top bar */
.top-bar {
    background: linear-gradient(to right, #2b4b8a, #1a355d);
    overflow: hidden;
    white-space: nowrap;
    position: relative;
    padding: 5px 0;
    text-align: center;
}

.sliding-text {
    display: inline-block;
    color: white;
    font-size: 14px;
    animation: slide 10s linear infinite;
    text-decoration: none;
}

.sliding-text:hover {
    text-decoration: none;
    color: #FFD700; /* Fading into another color (Tomato) */
    transition: color 0.3s;
}

@keyframes slide {
    0% {
        transform: translateX(100%);
    }
    50% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

@media (max-width: 700px) {
    .sliding-text {
        animation: slide 5s linear infinite;
    }
    @keyframes slide {
        0% {
            transform: translateX(50%);
        }
        50% {
            transform: translateX(-50%);
        }
        100% {
            transform: translateX(50%);
        }
    }
}
.navbar{
    background: linear( #2b4b8a 20%, #ffffff 70%);
    
    border-bottom: 3px solid transparent;
    border-image: linear-gradient(90deg, rgba(2,0,36,0) 0%, #2a4ac6 50%, rgba(0,212,255,0) 100%);
    border-image-slice: 1;




}

.sticky {
        position: fixed !important;
        top: 0 !important;
        width: 100% !important;
        z-index: 1000 !important;
        /* Other necessary styles */
        background-color: white; /* Example background color */
        /*-------K box-shadow: 15px 15px 35px rgba(0, 0, 0, 0.037); /*Example box shadow */
}

.nav-item a
{
    
    font-size: 19px;
    word-spacing: 10px;
    color: black !important;
}

.nav-item a:hover
{
    color: #2b4b8a !important;
}


.navbar-brand .img-1
{
    width:95px; 
    height:114px;
}

@media(max-width: 460px)
{
    
    .navbar-brand .img-1
    {
        width: 85px;
        height: 100px;
    }
    .logo1 img
    {
        width: 180px;
        height: 40px;
    }
    .logo-text
    {
        font-size: 14px;
    }
    .navbar-nav
    {
        font-size: 17px;
    }
    .navbar-nav .dropdown-menu
    {
        font-size: 16px;
    }
}
@media(max-width: 400px)
{
    .navbar-brand .img-1
    {
        width: 70px;
        height: 85px;
    }
    .logo1 img
    {
        width: 150px;
        height: 30px;
    }
    .logo-text
    {
        font-size: 11px;
    }
    .navbar-nav
    {
        font-size: 15px;
    }
    .navbar-nav .dropdown-menu
    {
        font-size: 14px;
    }
}



    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css"
    integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">

    <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@100..900&display=swap" rel="stylesheet">
</head>


    <nav class="navbar navbar-expand-lg navbar-light bg-light">
  <a class="navbar-brand" href="index.php">
  <img class="img-1" src="/photos/logo-main.png"  alt="Logo 1">
  </a>
  <div class="logo1 mr-auto">
                <img src="/photos/st.png" width="210px" height="40px" alt="Logo 2">    
                <div class="logo-text">Ondipudur, Coimbatore - 641016</div>
            </div>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarNavDropdown">
    <ul class="navbar-nav ml-auto">
      <li class="nav-item">
        <a class="nav-link" href="index.php">Home <span class="sr-only">(current)</span></a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="curriculum.php" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        About
        </a>
        <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
          <a class="dropdown-item" href="about.php">Our School</a>
          <a class="dropdown-item" href="staffs.php">Our Staffs</a>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="infrastructure.php">Infrastructure</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="curriculum.php" id="navbarDropdownMenuLink" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        Curriculum
        </a>
        <div class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
          <a class="dropdown-item" href="academics.php">Academics</a>
          <a class="dropdown-item" href="co-curriculum.php">Co-Curriculum</a>
          <a class="dropdown-item" href="sports.php">Sports</a>
        </div>
      </li>
      <li class="nav-item">
      <a class="nav-link" href="achievements.php">Achievements</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="gallery.php">Gallery</a>
      </li>

  </div>
</nav>


<script defer src="https://code.jquery.com/jquery-3.5.1.slim.min.js" 
integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js" 
integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx" crossorigin="anonymous"></script>


    <script type="text/javascript">
   document.addEventListener("DOMContentLoaded", function() {
    window.addEventListener("scroll", function() {
        var header = document.querySelector(".navbar");
        if (header) {
            header.classList.toggle("sticky", window.scrollY > 0);
        }
    });
});

</script>

 
</body>
</html>
