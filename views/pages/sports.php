<?php
// Sports page body — DB-driven since C6. Variables from public_html/sports.php:
// $sj_page, $sj_hero_slides, $sj_sports, $sj_suffixes (reveal-class sequence).
// Every card keeps a unique accordion id (accordion-N — the R1a bug-11 fix);
// collapse ids reuse the shipped word forms (collapseOne…collapseNine).
$sj_words = ['One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen'];
?>
<!-- top carousel -->
<section class="abt-carousel">
<div id="carouselExampleSlidesOnly" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
  <div class="carousel-inner"<?= $sj_page ? ed_add('hero_slide', ['page_id' => (int)$sj_page['id']], 'Add sports slide') : '' ?>>
    <?php foreach ($sj_hero_slides as $i => $s): ?>
    <div class="carousel-item<?= $i === 0 ? ' active' : '' ?><?= empty($s['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('hero_slide', $s['id'], 'Sports slide') ?>>
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

<!-- sportsment cards start -->
<div class="home-text">
    <h2  class="span-reveal">The <span>Sports </span> in St.Joseph's</h2>
</div>
<section class="sports-all"<?= ed_add('sport', [], 'Add sport') ?>>
<?php foreach (array_chunk($sj_sports, 3) as $ri => $chunk): $sfx = $sj_suffixes[min($ri, count($sj_suffixes) - 1)] ?? ''; ?>
<section class="sports-card sports-card-reveal<?= e($sfx) ?>">
    <div class="row mt-5">
    <?php foreach ($chunk as $ci => $S): $n = $ri * 3 + $ci; $word = $sj_words[$n] ?? (string)($n + 1); ?>
        <div class="col-md-4">
          <div class="card<?= empty($S['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('sport', $S['id'], $S['name']) ?>>
            <?= img_tag($S['image'], 'card_4x3', ['class' => 'card-img-top', 'alt' => '...', 'extra' => trim(ed_img('sport', $S['id']))]) ?>
            <div class="card-body">
              <h3 class="card-title"<?= ed_field('sport', $S['id'], 'name') ?>><?= e($S['name']) ?></h3>
              <p class="card-text"<?= ed_field('sport', $S['id'], 'training_time') ?>><?= e($S['training_time']) ?></p>
<!-- accordion st -->
              <div class="accordion-main" id="accordion-<?= $n + 1 ?>">
                <div class="accordion-card">
                <div class="card-header" id="heading<?= e($word) ?>">
                <h5 class="mb-0">
                    <button class="btn btn-link" data-bs-toggle="collapse" data-bs-target="#collapse<?= e($word) ?>" aria-expanded="true" aria-controls="collapse<?= e($word) ?>">
                    Read More
                    </button>
                </h5>
                </div>

                <div id="collapse<?= e($word) ?>" class="collapse" aria-labelledby="heading<?= e($word) ?>" data-bs-parent="#accordion-<?= $n + 1 ?>">
                <div class="card-body">
                <?php ed_rich('sport', $S['id'], 'details_html', $S['details_html']); ?>

                </div>
                </div>
                </div>
                </div>
                <!-- accordion end -->

            </div>
          </div>
        </div>
    <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>
</section>

<script src="/js/sports.js"></script>
<script>


    window.addEventListener('DOMContentLoaded', reveal);
    window.addEventListener('scroll', reveal);
    function reveal() {
      var reveals = document.querySelectorAll('.sports-card-reveal,.sports-text-reveal,.abt-carousel-reveal');
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
