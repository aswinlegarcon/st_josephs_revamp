   <style>
    .gal-annual {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}
.gal-annual-container {
  position: relative;
  min-height: 90vh;
  background: #ddd;
}
.gal-annual-container h1 {
  font-family: "Fjalla One", sans-serif !important;
  font-size: 60px;
  font-weight: bolder;
  padding: 35px;
  padding-top: 50px;
  color: #2b4b8a;
  text-align: center;
  text-transform: capitalize;
}
.gal-annual-container .image-container {
  display: flex;
  flex-wrap: wrap;
  gap: 15px;
  justify-content: center;
  padding: 10px;
}
.gal-annual-container .image-container .image {
  height: 250px;
  width: 350px;
  border: 5px solid #2b4b8a;
  box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
  overflow: hidden;
  cursor: pointer;
}
.gal-annual-container .image-container .image img {
  height: 100%;
  width: 100%;
  object-fit: cover;
  transition: 0.2s linear;
}
.gal-annual-container .image-container .image:hover img {
  transform: scale(1.1);
}

.gal-annual-container .popup-image {
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
.gal-annual-container .popup-image span {
  position: absolute;
  top: 0;
  right: 10px;
  font-size: 60px;
  font-weight: bolder;
  color: #fff;
  cursor: pointer;
  z-index: 2000;
}
.gal-annual-container .popup-image span:hover {
  color: #ffd700;
}
.gal-annual-container .btn-group {
  margin-left: 20px;
}
.gal-annual-container .btn {
  font-family: "LeagueSpartan", sans-serif !important;
  background-color: #2b4b8a !important;
  border: 2px solid #fff !important;
  color: #fff !important;
  text-decoration: none;
}
.gal-annual-container .btn:hover {
  background-color: #ffd700 !important;
  color: #333 !important;
}
.gal-annual-container .popup-image img {
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

.gal-annual-container .navigation-buttons {
  position: absolute;
  top: 50%;
  padding: 20px;
  width: 100%;
  display: flex;
  justify-content: space-between;
  transform: translateY(-50%);
  z-index: 2000;
}
.gal-annual-container .navigation-buttons button {
  background: none !important;
  color: white;
  border: none !important; /* Remove the border */
  font-size: 60px;
  padding: 10px 20px;
  cursor: pointer;
}

.gal-annual-container .navigation-buttons button:hover {
  color: #ffd700;
}
@media (max-width: 768px) {
  .gal-annual-container .popup-image img {
    max-width: 80%; /* Adjusts the width for mobile view */
    max-height: 80%;
  }
}

   </style>

<div class="gal-annual-container">
    <h1>annual Day</h1>
    <div class="btn-group" role="group" aria-label="Basic radio toggle button group">
        <input type="radio" class="btn-check" name="btnradio" id="btnradio2023" autocomplete="off" checked>
        <label class="btn btn-outline-primary" for="btnradio2023-2024">2023</label>
<input type="radio" class="btn-check" name="btnradio" id="btnradio2024" autocomplete="off">
        <label class="btn btn-outline-primary" for="btnradio2024">2024</label> 
        <!-- <input type="radio" class="btn-check" name="btnradio" id="btnradio2022" autocomplete="off">
        <label class="btn btn-outline-primary" for="btnradio2022">2022</label> -->
    </div>
    <div class="image-container">
        <!-- 2023-2024 images -->
        <div class="image year-2023"><img id="image-0" src="/photos/annualday1.jpg" alt=""></div>  
        <div class="image year-2023"><img id="image-1" src="/photos/annualday2.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-2" src="/photos/annualday3.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday4.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday5.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-2" src="/photos/annualday6.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday7.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday8.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday9.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday10.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday11.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday12.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday13.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday14.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday15.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday16.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday17.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday18.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday19.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday22.jpg" alt=""></div>
        <div class="image year-2023"><img id="image-3" src="/photos/annualday23.jpg" alt=""></div>
        
               
        
        <!-- 2022 images
        <div class="image year-2022"><img id="image-4" src="/photos/2.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-5" src="/photos/3.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-6" src="/photos/3.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-7" src="/photos/3.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-5" src="/photos/3.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-6" src="/photos/3.jpg" alt=""></div>
        <div class="image year-2022"><img id="image-7" src="/photos/3.jpg" alt=""></div> -->
    
     <!-- 2024 images -->
        <div class="image year-2024"><img id="image-4" src="/photos/ann1.jpg" alt=""></div>
        <div class="image year-2024"><img id="image-5" src="/photos/annualday19.jpg" alt=""></div>
        <div class="image year-2024"><img id="image-6" src="/photos/annualday22.jpg" alt=""></div>
        <div class="image year-2024"><img id="image-7" src="/photos/annualday23.jpg" alt=""></div>
        <div class="image year-2024"><img id="image-5" src="/photos/ann2.jpg" alt=""></div>
        <div class="image year-2024"><img id="image-6" src="/photos/ann3.jpg" alt=""></div>
        <div class="image year-2024"><img id="image-7" src="/photos/ann4.jpg" alt=""></div>
        <div class="image year-2024"><img id="image-7" src="/photos/ann5.jpg" alt=""></div> 
    
    </div>
    <div class="popup-image">
        <span>&times;</span>
        <img src="/photos/annualday1.jpg" alt="">
        <div class="navigation-buttons">
            <button class="prev">&lt;</button>
            <button class="next">&gt;</button>
        </div>
    </div>
</div>

<script>
const images = document.querySelectorAll('.gal-annual-container .image-container img');
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
