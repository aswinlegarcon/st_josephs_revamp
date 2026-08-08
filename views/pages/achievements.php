<?php
// Achievements page body — DB-driven since C8. Variables from the controller:
// $sj_page, $sj_hero_slides, $sj_achievements, $sj_awards.
// Items pair up 2-per .achieve-container; the second of each pair is
// class="item reverse" (photo on the right) — the shipped zig-zag.

/** Render one typed list in the shipped paired zig-zag markup. */
function sj_achieve_list(array $rows): void
{
    foreach (array_chunk($rows, 2) as $pair) {
        echo '<div class="achieve-container">' . "\n";
        foreach ($pair as $k => $A) {
            $rev = $k === 1;
            ?>
        <div class="item<?= $rev ? ' reverse' : '' ?><?= empty($A['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('achievement', $A['id'], $A['title']) ?>>
            <?php if (!$rev): ?>
            <?= img_tag($A['image'], 'feature_4x3', ['alt' => 'Section Image', 'extra' => trim(ed_img('achievement', $A['id']))]) ?>
            <div class="icon">
            <img src="/photos/trophy.png" alt="trophy">
            </div>
            <h3<?= ed_field('achievement', $A['id'], 'title') ?>><?= e($A['title']) ?></h3>
            <p<?= ed_field('achievement', $A['id'], 'subtext') ?>><?= e($A['subtext']) ?></p>
            <?php else: ?>
            <div class="icon">
            <img src="/photos/trophy.png" alt="trophy">
            </div>
            <h3<?= ed_field('achievement', $A['id'], 'title') ?>><?= e($A['title']) ?></h3>
            <p<?= ed_field('achievement', $A['id'], 'subtext') ?>><?= e($A['subtext']) ?></p>
            <?= img_tag($A['image'], 'feature_4x3', ['alt' => 'Section Image', 'extra' => trim(ed_img('achievement', $A['id']))]) ?>
            <?php endif; ?>
        </div>
            <?php
        }
        echo "    </div>\n";
    }
}
?>

<!-- top carousel -->
<section class="abt-carousel">
<div id="achievementsHeroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
  <div class="carousel-inner"<?= $sj_page ? ed_add('hero_slide', ['page_id' => (int)$sj_page['id']], 'Add achievements slide') : '' ?>>
    <?php foreach ($sj_hero_slides as $i => $s): ?>
    <div class="carousel-item<?= $i === 0 ? ' active' : '' ?><?= empty($s['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('hero_slide', $s['id'], 'Achievements slide') ?>>
      <?= img_tag($s['image'], 'hero_16x7', ['class' => 'd-block w-100', 'alt' => ($i === 0 ? 'First' : ($i === 1 ? 'Second' : 'Third')) . ' slide', 'eager' => $i === 0, 'extra' => trim(ed_img('hero_slide', $s['id']))]) ?>
      <div class="carousel-caption text-start">
          <h5<?= $i === 0 ? ' class="abt-carousel-reveal"' : '' ?><?= ed_field('hero_slide', $s['id'], 'caption_title') ?>><?= e($s['caption_title']) ?></h5>
          <p<?= $i === 0 ? ' class="abt-carousel-reveal"' : '' ?><?= ed_field('hero_slide', $s['id'], 'caption_text') ?>><?= e($s['caption_text']) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</section>

<!-- Achievement cards start -->
<div class="home-text">
    <h2  class="span-reveal">The <span>Achievements </span> of St.Joseph's</h2>
</div>
<?php sj_achieve_list($sj_achievements); ?>

    <div class="home-text">
    <h2  class="span-reveal">The <span>Awards given by </span> St.Joseph's</h2>
</div>
<?php sj_achieve_list($sj_awards); ?>

<script src="/js/achievements.js"></script>
<script>


    window.addEventListener('DOMContentLoaded', reveal);
    window.addEventListener('scroll', reveal);
    function reveal() {
      var reveals = document.querySelectorAll('.achieve-card-reveal,.achieve-text-reveal,.abt-carousel-reveal');
      var windowHeight = window.innerHeight;
      var revealPoint = 150;

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
