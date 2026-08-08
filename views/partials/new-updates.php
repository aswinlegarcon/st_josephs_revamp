<?php // "New Updates" carousel — clean Bootstrap 5 fragment (no nested document).
// Parametrized: $sj_updates comes from the page controller.
// The <style> and the reveal <script> are VERBATIM from _templates/new-updates.php
// — do not tidy. Only the carousel dialect is translated (data-bs-*, indicator
// buttons, visually-hidden), which is a code change, not a visual one. ?>
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

<!-- Carousel Starts -->
 <section class="update ">
 <div class="update-text">
    <h5 class="reveal-update">
        New Updates
    </h5>
 </div>
 <div class="update-carousel">

  <div id="carouselExampleIndicators2" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="3000">
    <div class="carousel-indicators">
      <?php foreach ($sj_updates as $i => $u): ?>
      <button type="button" data-bs-target="#carouselExampleIndicators2" data-bs-slide-to="<?= $i ?>"<?= $i === 0 ? ' class="active" aria-current="true"' : '' ?> aria-label="Slide <?= $i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>

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

    <button class="carousel-control-prev" type="button" data-bs-target="#carouselExampleIndicators2" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carouselExampleIndicators2" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Next</span>
    </button>
  </div>

</div>
</section>

<!-- Custom JavaScript -->
<script>
// Shipped reveal (scroll-only), now via the shared helper in /js/site.js (R2).
window.addEventListener('DOMContentLoaded', function () {
    sjReveal('.reveal-update', 150);
});
</script>
