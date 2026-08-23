<?php // "New Updates" carousel — clean Bootstrap 5 fragment (no nested document).
// Parametrized: $sj_updates comes from the page controller.
// Styles live VERBATIM in /css/partials/new-updates.css (body link = valid
// HTML, same cascade position — R3). Only the carousel dialect is translated
// (data-bs-*, indicator buttons, visually-hidden), a code change, not visual.
// R3 validation: the slide link's inner <button> became <span class="btn btn-1">
// (a button may not sit inside an <a>); Bootstrap's .btn styling is class-based
// and its reboot gives button/span identical inherited fonts, so it renders
// the same — the anchor itself stays the clickable element, as before. ?>
<link rel="stylesheet" href="/css/partials/new-updates.css?v=<?php echo SJ_ASSET_VER; ?>">

<!-- Carousel Starts -->
 <section class="update ">
 <div class="update-text sj-reveal">
    <h3>
        New Updates
    </h3>
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
          <h4<?= ed_field('update_slide', $u['id'], 'title') ?>><?= e($u['title']) ?></h4>
          <p<?= ed_field('update_slide', $u['id'], 'subtitle') ?>><?= e($u['subtitle']) ?></p>
          <?php if (!empty($u['link_url'])): ?>
          <a href="<?= e($u['link_url']) ?>" target="_blank" class="slider-btn update-slider-btn">
            <span class="btn btn-1"<?= ed_field('update_slide', $u['id'], 'link_label') ?>><?= e($u['link_label']) ?></span>
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
<?php /* R3: the shipped markup never closed .update-carousel — the parser
         recovered by closing it here at </section>, so this explicit close
         reproduces the exact same DOM (controls stay inside .carousel-inner,
         as the browser always parsed them). */ ?>
</div>
</section>

