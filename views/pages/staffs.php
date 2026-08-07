<?php
// Staffs page body — DB-driven since C3 (hero slides + three profile blocks).
// Variables from public_html/staffs.php: $sj_page, $sj_hero_slides, $sj_blocks
// (profiles keyed staff_love / staff_team / staff_tour).
// The "We Love our Staffs" block is flanked by two photos; they are the
// staff_team and staff_tour images (one image per profile row).
$sj_love = $sj_blocks['staff_love'] ?? null;
$sj_team = $sj_blocks['staff_team'] ?? null;
$sj_tour = $sj_blocks['staff_tour'] ?? null;
?>
<!-- top carousel -->
<section class="abt-carousel ">
<div id="carouselExampleSlidesOnly" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
  <div class="carousel-inner"<?= $sj_page ? ed_add('hero_slide', ['page_id' => (int)$sj_page['id']], 'Add staffs slide') : '' ?>>
    <?php foreach ($sj_hero_slides as $i => $s): ?>
    <div class="carousel-item<?= $i === 0 ? ' active' : '' ?><?= empty($s['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('hero_slide', $s['id'], 'Staffs slide') ?>>
      <?= img_tag($s['image'], 'hero_16x7', ['class' => 'd-block w-100', 'alt' => ($i === 0 ? 'First' : 'Second') . ' slide', 'eager' => $i === 0, 'extra' => trim(ed_img('hero_slide', $s['id']))]) ?>
      <div class="carousel-caption text-start<?= $i === 0 ? ' abt-carousel-reveal' : '' ?>">
          <h5<?= ed_field('hero_slide', $s['id'], 'caption_title') ?>><?= e($s['caption_title']) ?></h5>
          <p<?= ed_field('hero_slide', $s['id'], 'caption_text') ?>><?= e($s['caption_text']) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</section>

<!-- infrastructurement cards start -->
<?php if ($sj_love): ?>
<div class="infrastructure-container">
    <?= img_tag($sj_team['image'] ?? null, 'feature_4x3', ['alt' => 'Left Image', 'class' => 'infrastructure-image']) ?>
    <div class="infrastructure-text">
        <h4 class="infrastructure-text-reveal"<?= ed_field('profile', $sj_love['id'], 'person_name') ?>><?= e($sj_love['person_name']) ?></h4>
        <?php ed_rich('profile', $sj_love['id'], 'message_html', $sj_love['message_html']); ?>
    </div>
    <?= img_tag($sj_tour['image'] ?? null, 'feature_4x3', ['alt' => 'Right Image', 'class' => 'infrastructure-image']) ?>
</div>
<?php endif; ?>




<!-- new template -->
<section class="newtemp-body">
<div class="about-container">
        <?php if ($sj_team): ?>
        <div class="about-section">
            <?= img_tag($sj_team['image'], 'feature_4x3', ['alt' => 'Left Image', 'class' => 'about-image', 'extra' => trim(ed_img('profile', $sj_team['id']))]) ?>
            <div class="about-content">
                <h2 class="infrastructure-text-reveal"<?= ed_field('profile', $sj_team['id'], 'person_name') ?>><?= e($sj_team['person_name']) ?></h2>
                <?php ed_rich('profile', $sj_team['id'], 'message_html', $sj_team['message_html']); ?>

              </div>

        </div>
        <?php endif; ?>
        <?php if ($sj_tour): ?>
        <div class="about-section">
            <div class="about-content">
                <h2 class="infrastructure-text-reveal"<?= ed_field('profile', $sj_tour['id'], 'person_name') ?>><?= e($sj_tour['person_name']) ?></h2>
                <?php ed_rich('profile', $sj_tour['id'], 'message_html', $sj_tour['message_html']); ?>
            </div>
            <?= img_tag($sj_tour['image'], 'feature_4x3', ['alt' => 'Right Image', 'class' => 'about-image', 'extra' => trim(ed_img('profile', $sj_tour['id']))]) ?>
        </div>
        <?php endif; ?>

    </div>
    </section>
    <!-- template end -->

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
