<?php
// THE hero/content carousel — Stage J (PUBLIC_UI_DESIGN.md §5). One partial
// replaces the six hand-rolled copies (J3 home, J4/J5 the rest). Geometry,
// scrim and Ken Burns live in site.css (.sj-hero — carries the N6 uniform
// 16:9 guarantee). All ed_* edit-overlay hooks preserved verbatim.
//
// Expects $sjHero = [
//   'id'          => unique carousel DOM id (one per page),
//   'slides'      => hero_slide rows (id, image, caption_title, caption_text,
//                    button_label, button_url, is_active) — or bare image rows
//                    for the 'strip' variant,
//   'variant'     => 'hero' (veil + caption + Ken Burns) | 'strip' (plain),
//   'preset'      => image preset key (default hero_16x7),
//   'interval'    => autoplay ms (default 2000),
//   'page_id'     => int|null — enables the admin "add slide" hook,
//   'keyboardNav' => bool — ←/→ drive this carousel (site.js; one per page),
// ];
$sjH        = $sjHero;
$sjHSlides  = $sjH['slides'] ?? [];
$sjHVariant = ($sjH['variant'] ?? 'hero') === 'strip' ? 'strip' : 'hero';
$sjHPreset  = $sjH['preset'] ?? 'hero_16x7';
$sjHId      = $sjH['id'] ?? 'sjHero';
?>
<section class="sj-hero sj-hero--<?= $sjHVariant ?><?= $sjHVariant === 'hero' ? ' sj-hero--motion' : '' ?> sj-reveal">
  <div id="<?= e($sjHId) ?>" class="carousel slide carousel-fade" data-bs-ride="carousel"
       data-bs-interval="<?= (int)($sjH['interval'] ?? 2000) ?>"<?= !empty($sjH['keyboardNav']) ? ' data-sj-kbnav' : '' ?>>
    <div class="carousel-indicators">
      <?php foreach ($sjHSlides as $i => $s): ?>
      <button type="button" data-bs-target="#<?= e($sjHId) ?>" data-bs-slide-to="<?= $i ?>"<?= $i === 0 ? ' class="active" aria-current="true"' : '' ?> aria-label="Slide <?= $i + 1 ?>"></button>
      <?php endforeach; ?>
    </div>
    <div class="carousel-inner"<?= !empty($sjH['page_id']) ? ed_add('hero_slide', ['page_id' => (int)$sjH['page_id']], 'Add slide') : '' ?>>
      <?php foreach ($sjHSlides as $i => $s): ?>
      <div class="carousel-item<?= $i === 0 ? ' active' : '' ?><?= isset($s['is_active']) && empty($s['is_active']) ? ' sj-inactive' : '' ?>"<?= isset($s['id']) ? ed_item('hero_slide', $s['id'], 'Hero slide') : '' ?>>
        <?= img_tag($s['image'], $sjHPreset, [
            'class' => 'd-block',
            'alt'   => ($s['caption_title'] ?? '') !== '' ? $s['caption_title'] : 'Slide ' . ($i + 1),
            'eager' => $i === 0,
            'extra' => isset($s['id']) ? trim(ed_img('hero_slide', $s['id'])) : '',
        ]) ?>
        <?php if ($sjHVariant === 'hero'): ?>
        <div class="carousel-caption sj-hero-cap">
          <h5 class="sj-hero-title"<?= ed_field('hero_slide', $s['id'], 'caption_title') ?>><?= e($s['caption_title']) ?></h5>
          <p class="sj-hero-flourish"<?= ed_field('hero_slide', $s['id'], 'caption_text') ?>><?= e($s['caption_text']) ?></p>
          <?php if (!empty($s['button_label'])): ?>
          <a href="<?= e($s['button_url'] ?: '#') ?>" class="sj-hero-btnwrap">
            <span class="sj-btn sj-btn--gold"<?= ed_field('hero_slide', $s['id'], 'button_label') ?>><?= e($s['button_label']) ?></span>
          </a>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if (count($sjHSlides) > 1): ?>
    <button class="carousel-control-prev" type="button" data-bs-target="#<?= e($sjHId) ?>" data-bs-slide="prev">
      <span class="carousel-control-prev-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#<?= e($sjHId) ?>" data-bs-slide="next">
      <span class="carousel-control-next-icon" aria-hidden="true"></span>
      <span class="visually-hidden">Next</span>
    </button>
    <?php endif; ?>
  </div>
</section>
