<?php // Students Testimonial cards — clean fragment (no nested document).
// C3: DB-driven — $sj_testimonials comes from the page controller. name_html/
// body_html are sanitized rich fields (write-path whitelist), echoed raw by
// design; the card1/2/3 background classes cycle by position.
// Styles live VERBATIM in /css/partials/testimonial.css (body link = valid
// HTML, same cascade position — R3). The cards use class "card card1..3":
// Bootstrap-4's .card contribution (radius/border/background) is re-supplied
// by /css/home-bs4-remnants.css. ?>
<link rel="stylesheet" href="/css/partials/testimonial.css?v=<?php echo SJ_ASSET_VER; ?>">
    <div class="testimonial-container">
        <h1 class="testimonial-reveal">Students Testimonial</h1>
        <div class="testimonial"<?= ed_add('testimonial', [], 'Add testimonial') ?>>
            <?php foreach ($sj_testimonials as $ti => $t): ?>
            <div class="card card<?= ($ti % 3) + 1 ?><?= empty($t['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('testimonial', $t['id'], 'Testimonial') ?>>
            <img class="quote-icon" src="/photos/quote.png" alt="quote icon">
                <h2<?= ed_field('testimonial', $t['id'], 'name_html') ?>><?= $t['name_html'] ?></h2>
                <?php ed_rich('testimonial', $t['id'], 'body_html', $t['body_html']); ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
// Shipped reveal (scroll-only), now via the shared helper in /js/site.js (R2).
window.addEventListener('DOMContentLoaded', function () {
    sjReveal('.testimonial-reveal', 150);
});
</script>
