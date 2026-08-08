<?php
// School-section family template — kg / primary / highschl / highsec (C4).
// One template, four pages. Variables from the thin controllers:
//   $sj_slug, $sj_page, $sj_hero_slides, $sj_section, $sj_carousel (linked
//   images), $sj_timeline, $sj_events, $sj_show_groups_marks (highsec),
//   $sj_marks_years (highsec).
// Per-page bits preserved from the static R1b views: {slug}HeroCarousel /
// {slug}Carousel ids, {slug}-carousel-reveal class, .infra-new bg-6.
$__pp = dirname(__DIR__) . '/partials';
$sid  = (int)$sj_section['id'];
?>
<!-- top carousel -->
<section class="<?= $sj_slug === 'kg' ? 'kg-carousel ' : 'abt-carousel ' ?>">
<div id="<?= e($sj_slug) ?>HeroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
  <div class="carousel-inner"<?= $sj_page ? ed_add('hero_slide', ['page_id' => (int)$sj_page['id']], 'Add slide') : '' ?>>
    <?php foreach ($sj_hero_slides as $i => $s): ?>
    <div class="carousel-item<?= $i === 0 ? ' active' : '' ?><?= empty($s['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('hero_slide', $s['id'], 'Slide') ?>>
      <?= img_tag($s['image'], 'hero_16x7', ['class' => 'd-block w-100', 'alt' => 'Slide', 'eager' => $i === 0, 'extra' => trim(ed_img('hero_slide', $s['id']))]) ?>
      <div class="carousel-caption text-start<?= $i === 0 ? ' ' . e($sj_slug) . '-carousel-reveal' : '' ?>">
          <h5<?= ed_field('hero_slide', $s['id'], 'caption_title') ?>><?= e($s['caption_title']) ?></h5>
          <p<?= ed_field('hero_slide', $s['id'], 'caption_text') ?>><?= e($s['caption_text']) ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
</section>
<!-- main strt -->

 <section class="infra-new bg-6">
  <section class="infra-new-carousel">
    <div id="<?= e($sj_slug) ?>Carousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
      <div class="carousel-indicators">
        <?php foreach ($sj_carousel as $i => $img): ?>
        <button type="button" data-bs-target="#<?= e($sj_slug) ?>Carousel" data-bs-slide-to="<?= $i ?>"<?= $i === 0 ? ' class="active" aria-current="true"' : '' ?> aria-label="Slide <?= $i + 1 ?>"></button>
        <?php endforeach; ?>
      </div>
      <div class="carousel-inner">
        <?php foreach ($sj_carousel as $i => $img): ?>
        <div class="carousel-item<?= $i === 0 ? ' active' : '' ?>">
          <?= img_tag($img, 'content_slide', ['class' => 'd-block w-100', 'alt' => ($i === 0 ? 'First' : ($i === 1 ? 'Second' : 'Third')) . ' slide']) ?>
        </div>
        <?php endforeach; ?>
      </div>
      <a class="carousel-control-prev" href="#<?= e($sj_slug) ?>Carousel" role="button" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
      </a>
      <a class="carousel-control-next" href="#<?= e($sj_slug) ?>Carousel" role="button" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
      </a>
    </div>
  </section>
  <!-- carousel end -->

  <div class="infra-new-text">
    <h4 class="infra-new-reveal"<?= ed_field('school_section', $sid, 'intro_heading') ?>><?= e($sj_section['intro_heading']) ?></h4>
    <?php ed_rich('school_section', $sid, 'intro_html', $sj_section['intro_html']); ?>

<div class="accordion-main" id="accordion">
            <div class="card">
                <div class="card-header" id="headingOne">
                <h5 class="mb-0">
                    <button class="btn btn-link" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne"<?= ed_field('school_section', $sid, 'timeline_heading') ?>>
                    <?= e($sj_section['timeline_heading']) ?>
                    </button>
                </h5>
                </div>

                <div id="collapseOne" class="collapse" data-bs-parent="#accordion">
                <div class="card-body">
                <ul class="timeline"<?= ed_add('timeline_entry', ['section_id' => $sid], 'Add timeline month') ?>>
                    <?php foreach ($sj_timeline as $ti => $t): ?>
                                <li<?= ed_item('timeline_entry', $t['id'], 'Timeline: ' . $t['month_label']) ?>>
                                    <div class="<?= $ti % 2 === 0 ? 'direction-r' : 'direction-l' ?>">
                                        <div class="flag-wrapper">
                                            <span class="flag"<?= ed_field('timeline_entry', $t['id'], 'month_label') ?>><?= e($t['month_label']) ?></span>
                                            <span class="time-wrapper"><span class="time"<?= ed_field('timeline_entry', $t['id'], 'time_label') ?>><?= e($t['time_label']) ?></span></span>
                                        </div>
                                        <div class="desc">
                                            <?= implode("<br>\n                                            ", array_map('e', preg_split('/\r\n|\r|\n/', $t['events_text']))) ?>
                                        </div>
                                    </div>
                                </li>
                    <?php endforeach; ?>
                </ul>
                </div>
                </div>
            </div>
        </div>

  </div>
</section>


<!-- main  end -->
<div class="home-text">
    <h2  class="span-reveal"><?= e($sj_section['events_heading']) ?><span> <?= e($sj_section['name']) ?></span></h2>
</div>
<!-- new template -->
<section class="newtemp-body">
<div class="about-container"<?= ed_add('section_event', ['section_id' => $sid], 'Add event block') ?>>
        <?php foreach ($sj_events as $ei => $ev): ?>
        <div class="about-section"<?= ed_item('section_event', $ev['id'], 'Event: ' . $ev['title']) ?>>
            <?php if ($ei % 2 === 0): ?>
            <?= img_tag($ev['image'], 'feature_4x3', ['alt' => 'Left Image', 'class' => 'about-image', 'extra' => trim(ed_img('section_event', $ev['id']))]) ?>
            <div class="about-content">
                <h2 class="infrastructure-text-reveal"<?= ed_field('section_event', $ev['id'], 'title') ?>><?= e($ev['title']) ?></h2>
                <?php ed_rich('section_event', $ev['id'], 'body_html', $ev['body_html']); ?>

              </div>

        </div>
            <?php else: ?>
            <div class="about-content">
                <h2 class="infrastructure-text-reveal"<?= ed_field('section_event', $ev['id'], 'title') ?>><?= e($ev['title']) ?></h2>
                <?php ed_rich('section_event', $ev['id'], 'body_html', $ev['body_html']); ?>
            </div>
            <?= img_tag($ev['image'], 'feature_4x3', ['alt' => 'Right Image', 'class' => 'about-image', 'extra' => trim(ed_img('section_event', $ev['id']))]) ?>
        </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    </section>
    <!-- template end -->

  <script>
// Shipped per-section reveal (threshold 120), now through the shared helper
// in /js/site.js (R2); site.js loads later in the body, hence the wrapper.
window.addEventListener('DOMContentLoaded', function () {
  sjReveal('.infra-new-reveal,.<?= e($sj_slug) ?>-carousel-reveal,.infrastructure-text-reveal', 120, true);
});
  </script>
<?php if (!empty($sj_show_groups_marks)): ?>
    <?php include $__pp . '/groups.php'; ?>
    <?php include $__pp . '/marks-scroll.php'; ?>
<?php endif; ?>
