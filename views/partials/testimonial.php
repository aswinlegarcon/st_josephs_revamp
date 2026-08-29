<?php // Students Testimonial cards — clean fragment (no nested document).
// C3: DB-driven — $sj_testimonials comes from the page controller. name_html/
// body_html are sanitized rich fields (write-path whitelist), echoed raw by
// design; the card1/2/3 background classes cycle by position.
// Styles live in /css/partials/testimonial.css (Stage J: fully self-contained
// — the old home-bs4-remnants.css dependency is gone). ?>
<link rel="stylesheet" href="/css/partials/testimonial.css?v=<?php echo SJ_ASSET_VER; ?>">
    <div class="testimonial-container">
        <h1 class="sj-reveal">Students Testimonial</h1>
        <?php // K9: one-by-one slider — three cards visible on desktop, one on
              // phones; arrows + a 3s auto-advance live in site.js
              // (sjTestimonialSlider). The card recipe is untouched: the
              // .testimonial class stays on the TRACK so every existing
              // `.testimonial .card` rule keeps applying. ?>
        <div class="tm-slider"<?= ed_add('testimonial', [], 'Add testimonial') ?>>
          <button type="button" class="tm-nav tm-nav--prev" aria-label="Previous testimonial"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
          <div class="tm-viewport">
            <div class="testimonial tm-track">
            <?php foreach ($sj_testimonials as $ti => $t): ?>
            <?php // N2/J3: a chosen photo only sets the --sj-tm-bg custom property —
                  // the veil + sizing recipe lives ONCE in testimonial.css (the
                  // card1/2/3 statics set the same property). De-triplicated. ?>
            <div class="card card<?= ($ti % 3) + 1 ?> sj-reveal<?= empty($t['is_active']) ? ' sj-inactive' : '' ?>"<?=
                $t['image'] ? ' style="--sj-tm-bg: url(' . e(img_url($t['image'], 'feature_4x3')) . ');"' : ''
            ?><?= $ti > 0 ? ' data-sj-delay="' . ($ti % 3) * 100 . '"' : '' ?><?= ed_item('testimonial', $t['id'], 'Testimonial') ?><?= ed_img('testimonial', $t['id'], 'bg_image_id') ?>>
            <img class="quote-icon" src="/photos/quote.png" alt="quote icon">
                <h2<?= ed_field('testimonial', $t['id'], 'name_html') ?>><?= $t['name_html'] ?></h2>
                <?php ed_rich('testimonial', $t['id'], 'body_html', $t['body_html']); ?>
            </div>
            <?php endforeach; ?>
            </div>
          </div>
          <button type="button" class="tm-nav tm-nav--next" aria-label="Next testimonial"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
        </div>
    </div>
