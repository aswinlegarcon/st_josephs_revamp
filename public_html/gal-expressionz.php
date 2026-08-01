<?php include "_libs/load.php" ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>St.Joseph's MHSS, Ondipudur</title>
    <link rel="icon" href="/photos/logo-main.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" 
    integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        .gal-expressionz {
  scroll-behavior: smooth;
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
.gal-expressionz-container {
  position: relative;
  min-height: 90vh;
  background: #ddd;
}
.gal-expressionz-container h1 {
  font-family: "Fjalla One", sans-serif !important;
  font-size: 60px;
  font-weight: bolder;
  padding: 35px;
  padding-top: 40px;
  color: #2b4b8a;
  text-align: center;
  text-transform: capitalize;
}
.gal-expressionz-container .image-container {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  justify-content: center;
  padding: 10px;
}
.gal-expressionz-container .image-container .image {
  height: 250px;
  width: 350px;
  border: 5px solid #2b4b8a;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  cursor: pointer;
}
.gal-expressionz-container .image-container .image img {
  height: 100%;
  width: 100%;
  object-fit: cover;
  transition: 0.2s linear;
}
.gal-expressionz-container .image-container .image:hover img {
  transform: scale(1.1);
}

.gal-expressionz-container .popup-image {
  position: fixed;
  top: 0;
  left: 0;
  background: rgba(0, 0, 0, 0.9);
  height: 100%;
  width: 100%;
  z-index: 2000;
  display: none;
  animation: fadeIn 0.5s ease-in-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}
.gal-expressionz-container .popup-image span {
  position: absolute;
  top: 0;
  right: 10px;
  font-size: 60px;
  font-weight: bolder;
  color: #fff;
  cursor: pointer;
  z-index: 2000;
}
.gal-expressionz-container .popup-image span:hover {
  color: #ffd700;
}
.gal-expressionz-container .btn-group {
  margin-left: 20px;
}
.gal-expressionz-container .btn {
  background-color: #2b4b8a !important;
  border: 2px solid #fff !important;
  color: #fff !important;
  text-decoration: none;
}
.gal-expressionz-container .btn:hover {
  background-color: #ffd700 !important;
  color: #333 !important;
}
.gal-expressionz-container .popup-image img {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  border: 5px solid #ffd700;
  border-radius: 15px;
  max-width: 90%; /* Adjusts the width for desktop view */
  max-height: 90%; /* Adjusts the height for desktop view */
  object-fit: contain;
  animation: scaleIn 0.5s ease-in-out;
}

@keyframes scaleIn {
  from {
    transform: translate(-50%, -50%) scale(0.8);
  }
  to {
    transform: translate(-50%, -50%) scale(1);
  }
}

.gal-expressionz-container .navigation-buttons {
  position: absolute;
  top: 50%;
  padding: 20px;
  width: 100%;
  display: flex;
  justify-content: space-between;
  transform: translateY(-50%);
  z-index: 2000;
}
.gal-expressionz-container .navigation-buttons button {
  background: none !important;
  color: white;
  border: none !important; /* Remove the border */
  font-size: 60px;
  padding: 10px 20px;
  cursor: pointer;
}

.gal-expressionz-container .navigation-buttons button:hover {
  color: #ffd700;
}
@media (max-width: 768px) {
  .gal-expressionz-container .popup-image img {
    max-width: 80%; /* Adjusts the width for mobile view */
    max-height: 80%;
  }
}

    </style>
</head>

<body class="gal-expressionz">
<?php  get_templates('preloader');?>
<?php  get_templates('navbar');?>
<?php  get_templates('scroll-up');?>

<div class="gal-expressionz-container">
    <h1>Expressionz Day</h1>
    <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
        <input type="radio" class="btn-check" name="btnradio" id="btnradio2023" autocomplete="off" checked>
        <label class="btn btn-outline-primary" for="btnradio2023">2023</label>

        <!-- <input type="radio" class="btn-check" name="btnradio" id="btnradio2022" autocomplete="off">
        <label class="btn btn-outline-primary" for="btnradio2022">2022</label> -->
    </div>
    <div class="image-container">
        <!-- 2023 images -->
        <div class="image year-2023"><img id="image-0" src="/photos/expressday1.jpg" alt=""></div>  
        <div class="image year-2023"><img id="image-1" src="/photos/expressday2.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-2" src="/photos/expressday3.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday4.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday5.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-2" src="/photos/expressday6.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday7.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday8.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday9.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday10.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday11.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday12.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday13.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday14.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday15.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday16.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday17.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday18.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday19.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday20.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday21.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday22.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday23.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday24.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday25.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday26.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday27.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday28.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday29.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday30.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday31.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday32.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday33.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday34.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday35.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday36.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday37.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday38.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday39.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday40.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday41.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday42.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday43.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/expressday44.jpg" alt=""></div>

        
        
        <!-- 2022 images -->
        <!-- <div class="image year-2022"><img id="image-4" src="/photos/2.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-5" src="/photos/3.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-6" src="/photos/3.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-7" src="/photos/3.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-5" src="/photos/3.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-6" src="/photos/3.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-7" src="/photos/3.jpg" alt=""></div> -->
    </div>
    <div class="popup-image">
        <span>&times;</span>
        <img src="/photos/expressday1.jpg" alt="">
        <div class="navigation-buttons">
            <button class="prev">&lt;</button>
            <button class="next">&gt;</button>
        </div>
    </div>
</div>

<?php  get_templates('footer');?>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js" integrity="sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy" crossorigin="anonymous"></script>
<script>
const images = document.querySelectorAll('.gal-expressionz-container .image-container img');
let currentImageIndex = 0;

images.forEach((image, index) => {
    image.onclick = () => {
        const popupImage = document.querySelector('.popup-image');
        popupImage.style.display = 'flex';
        document.querySelector('.popup-image img').src = image.getAttribute('src');
        currentImageIndex = index;
        setTimeout(() => {
            popupImage.classList.add('visible');
        }, 10); // Add a slight delay to ensure the display change is registered before adding the class
    }
});

document.querySelector('.popup-image span').onclick = () => {
    const popupImage = document.querySelector('.popup-image');
    popupImage.classList.remove('visible');
    setTimeout(() => {
        popupImage.style.display = 'none';
    }, 500); // Match this duration with your fadeOut animation duration
}

const updatePopupImage = () => {
    document.querySelector('.popup-image img').src = images[currentImageIndex].getAttribute('src');
};

document.querySelector('.prev').onclick = () => {
    currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
    updatePopupImage();
};

document.querySelector('.next').onclick = () => {
    currentImageIndex = (currentImageIndex + 1) % images.length;
    updatePopupImage();
};

let startX;

document.querySelector('.popup-image img').addEventListener('touchstart', (e) => {
    startX = e.touches[0].clientX;
});

document.querySelector('.popup-image img').addEventListener('touchmove', (e) => {
    if (!startX) return;

    const touch = e.touches[0];
    const change = startX - touch.clientX;

    if (change > 50) {
        currentImageIndex = (currentImageIndex + 1) % images.length;
        updatePopupImage();
        startX = null;
    } else if (change < -50) {
        currentImageIndex = (currentImageIndex - 1 + images.length) % images.length;
        updatePopupImage();
        startX = null;
    }
});

// Show/hide images based on the selected year
const radioButtons = document.querySelectorAll('input[name="btnradio"]');
const imageContainers = document.querySelectorAll('.image');

radioButtons.forEach(radio => {
    radio.addEventListener('change', () => {
        const selectedYear = radio.id.replace('btnradio', '');
        imageContainers.forEach(container => {
            if (container.classList.contains(`year-${selectedYear}`)) {
                container.style.display = 'block';
            } else {
                container.style.display = 'none';
            }
        });
    });
});

// Trigger change event to display initial set of images
document.querySelector('input[name="btnradio"]:checked').dispatchEvent(new Event('change'));

</script>
</body>
</html>
