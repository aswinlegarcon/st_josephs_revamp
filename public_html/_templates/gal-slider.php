<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>St.Joseph's MHSS, Ondipudur</title>
    <link rel="icon" href="/photos/logo-main.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
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
</head>
<body class="gal-slider">
<div class="container-slider">
    <div class="slide" style="background-image: url(../photos/sportsday1.jpg); opacity: 1;"></div>
    <div class="slide" style="background-image: url(../photos/sportsday10.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/indday1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/indday12.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/childday1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/childday4.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/teachday1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/teachday9.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/expressday1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/expressday13.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/expo1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/expo18.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/gradday1.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/gradday11.jpg); opacity: 0;"></div>
    <div class="slide" style="background-image: url(../photos/spach1.jpg); opacity: 0;"></div>
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
        '../photos/sportsday1.jpg',
        '../photos/sportsday10.jpg',
        '../photos/indday1.jpg',
        '../photos/indday12.jpg',
        '../photos/childday1.jpg',
        '../photos/childday4.jpg',
        '../photos/teachday1.jpg',
        '../photos/teachday9.jpg',
        '../photos/expressday1.jpg',
        '../photos/expressday13.jpg',
        '../photos/expo1.jpg',
        '../photos/expo18.jpg',
        '../photos/gradday1.jpg',
        '../photos/gradday11.jpg',
        '../photos/spach1.jpg'
    ];

    images.forEach((image) => {
        const img = new Image();
        img.src = image;
    });
</script>
</body>
</html>
