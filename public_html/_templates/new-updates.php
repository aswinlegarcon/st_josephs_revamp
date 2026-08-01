<?php $sj_updates = repo_update_slides(is_edit()); ?>
<!doctype html>
<html lang="en">
<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS links -->
  <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/css/bootstrap.min.css" 
        integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous"> -->
        <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <!-- Custom CSS -->
  <style>
    :root {
  --primaryblue: #2b4b8a;
  --secondaryblue: #1a355d;
  --gold: #ffd700;
  --white: white;
  --black: black;
  --maroon: firebrick;
}

.update {
  margin: auto;
  padding-bottom: 100px;
}

.update-text h5 {
  font-family: "Fjalla One", sans-serif !important;
  text-align: center;
  font-size: 55px;
  font-weight: 600;
  margin: 50px;
}

.update-carousel {
  box-sizing: border-box;
  height: 70%;
  width: 90%;
  align-items: center;
  margin: auto;

  border-radius: 60px;
  overflow: hidden;
}

.update-carousel-item::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(
    to bottom,
    rgba(0, 0, 0, 0.5),
    rgba(0, 0, 0, 0.5)
  );
  z-index: 1;
}
/* animation */
.reveal-update {
  transition: 1.2s;
  transform: translateY(50px);
  opacity: 0;
}

.reveal-update.active {
  opacity: 1;
  transform: translateY(0);
}

.update-carousel-item img {
  height: 100%;
  width: 100%;
  position: relative;
  z-index: 0;
}
.upd .update-carousel-caption {
  top: 38%;
}

.update-carousel-caption h5 {
  font-family: "Fjalla One", sans-serif !important;
  color: #ffd700;
  font-size: 80px;
  font-weight: 700;
}

.update-carousel-caption p {
  font-family: "LeagueSpartan", sans-serif !important;

  font-size: 18px;
  top: 2rem;
}

.update-slider-btn {
  margin-top: 30px;
}

.update-slider-btn .btn {
  background-color: none;
  color: #fff;

  font-size: 20px;
  transition: 0.5s;
}

.update-slider-btn .btn:hover {
  color: #fff;
  background: linear-gradient(to left, #2b4b8a, #1a355d) !important;
  color: var(--white);
}

@media (max-width: 1200px) {
  .update-carousel {
    width: 90%;
    height: 90%;
  }

  .update-carousel-item img {
    height: 600px;
    width: 1100px;
  }

  .update-carousel-caption h5 {
    font-size: 70px;
    font-weight: 700;
  }

  .update-carousel-caption p {
    font-size: 15px;
    top: 2rem;
  }

  .update-slider-btn {
    margin-top: 30px;
  }

  .update-slider-btn .btn {
    padding: 0.5rem 2rem;
    font-size: 17px;
  }
}

@media (max-width: 768px) {
  .update-carousel {
    width: 90%;
    height: 90%;
  }

  .update-carousel-caption {
    top: 40%;
  }

  .update-carousel-caption h5 {
    font-size: 55px;
    font-weight: 700;
  }

  .update-carousel-caption p {
    font-size: 12px;
    top: 2rem;
  }

  .update-slider-btn {
    margin-top: 15px;
  }

  .update-slider-btn .btn {
    padding: 0.5rem 2rem;
    font-size: 13px;
  }
}

@media (max-width: 600px) {
  .update-carousel-caption {
    top: 42%;
  }

  .update-carousel-caption h5 {
    font-size: 60px;
    font-weight: 700;
  }

  .update-carousel-caption p {
    font-size: 12px;
    top: 1rem;
  }

  .update-slider-btn {
    margin-top: 10px;
  }

  .update-slider-btn .btn {
    padding: 0.3rem 2rem;
    font-size: 13px;
  }
}

@media (max-width: 500px) {
  .update-text h5 {
    font-size: 35px;
  }
  .update-carousel-caption {
    top: 40%;
  }

  .update-carousel-caption h5 {
    font-size: 35px;
    font-weight: 700;
  }

  .update-carousel-caption p {
    font-size: 11px;
    top: 1rem;
  }

  .update-slider-btn {
    margin-top: 8px;
  }

  .update-slider-btn .btn {
    padding: 0.4rem 2rem;
    font-size: 12px;
  }
}

@media (max-width: 370px) {
  .update-carousel-caption h5 {
    font-size: 30px;
    font-weight: 700;
  }

  .update-slider-btn {
    margin-top: 1px;
  }

  .update-carousel-caption p {
    top: 0.5rem;
  }
}

  </style>
</head>
<body>
  
<!-- Carousel Starts -->
 <section class="update ">
 <div class="update-text">
    <h5 class="reveal-update">
        New Updates
    </h5>
 </div>
 <div class="update-carousel">

  <div id="carouselExampleIndicators2" class="carousel slide carousel-fade" data-ride="carousel" data-interval="3000">
    <ol class="carousel-indicators">
      <?php foreach ($sj_updates as $i => $u): ?>
      <li data-target="#carouselExampleIndicators2" data-slide-to="<?= $i ?>"<?= $i === 0 ? ' class="active"' : '' ?>></li>
      <?php endforeach; ?>
    </ol>

    <div class="carousel-inner"<?= ed_add('update_slide', [], 'Add update slide') ?>>

      <?php foreach ($sj_updates as $i => $u): ?>
      <div class="carousel-item update-carousel-item<?= $i === 0 ? ' active' : '' ?><?= empty($u['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('update_slide', $u['id'], 'Update slide') ?>>
        <?= img_tag($u['image'], 'update_16x9', ['class' => 'd-block', 'alt' => 'Update slide', 'extra' => trim(ed_img('update_slide', $u['id']))]) ?>
        <div class="carousel-caption update-carousel-caption">
          <h5<?= ed_field('update_slide', $u['id'], 'title') ?>><?= e($u['title']) ?></h5>
          <p<?= ed_field('update_slide', $u['id'], 'subtitle') ?>><?= e($u['subtitle']) ?></p>
          <?php if (!empty($u['link_url'])): ?>
          <a href="<?= e($u['link_url']) ?>" target="_blank" class="slider-btn update-slider-btn">
            <button class="btn btn-1"<?= ed_field('update_slide', $u['id'], 'link_label') ?>><?= e($u['link_label']) ?></button>
          </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>

    <a class="carousel-control-prev" href="#carouselExampleIndicators2" role="button" data-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="sr-only">Previous</span>
    </a>
    <a class="carousel-control-next" href="#carouselExampleIndicators2" role="button" data-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="sr-only">Next</span>
    </a>
  </div>

</div>
</section>

<!-- <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
<script defer src="https://cdn.jsdelivr.net/npm/bootstrap@4.3.1/dist/js/bootstrap.min.js" 
        integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script> -->

<!-- Custom JavaScript -->
<script>
    window.addEventListener('scroll', reveal);
function reveal()
{
    var reveals = document.querySelectorAll('.reveal-update');
    for(var i=0; i< reveals.length;i++)
        {
            var windowheight = window.innerHeight;
            var revealtop = reveals[i].getBoundingClientRect().top;
            var revealpoint = 150;

            if(revealtop < windowheight-revealpoint)
                {
                    reveals[i].classList.add('active');
                }
                else
                {
                    reveals[i].classList.remove('active');
                }
        }
}
</script>

</body>
</html>
