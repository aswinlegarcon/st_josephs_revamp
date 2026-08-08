<?php
// Home page body — rendered inside views/shell.php by SJ\View\Layout::render('home').
// R1d: single-document BS5. Chrome (preloader/navbar/scroll-up/footer) comes from
// the shell; the content sections below use clean partials (no nested documents).
// Variables come from public_html/index.php (the controller): $sj_page,
// $sj_principal, $sj_features, $sj_home_page, $sj_hero_slides, $sj_ticker,
// $sj_updates, $sj_marks_years.
$__pp = dirname(__DIR__) . '/partials';
?>
<?php include $__pp . '/carousel.php'; ?>


<!-- about-strrt -->

<div class="home-text">
    <h2 <?= $sj_page ? ed_field('page', $sj_page['id'], 'heading_html') : '' ?>><?= $sj_page['heading_html'] ?? '' ?></h2>
</div>
<div class="containers">

<?php if ($sj_principal): ?>
<div class="about-section">
            <div class="about-image">
                <?= img_tag($sj_principal['image'], 'portrait_4x5', ['alt' => 'Profile Image 2', 'extra' => trim(ed_img('profile', $sj_principal['id']))]) ?>
            </div>
            <div class="about-content">
                <h3<?= ed_field('profile', $sj_principal['id'], 'heading') ?>><?= e($sj_principal['heading']) ?></h3>
                <h2 class="ab-1"<?= ed_field('profile', $sj_principal['id'], 'person_name') ?>><?= e($sj_principal['person_name']) ?></h2>
                <?php ed_rich('profile', $sj_principal['id'], 'message_html', $sj_principal['message_html']); ?>
                  <a class="btn btn-primary btn-lg" href="about.php" role="button">Read more</a>
                </div>
        </div>
<?php endif; ?>
</div>
<!-- about end -->
<?php include $__pp . '/card.php'; ?>


<div class="fun-facts overlay" data-stellar-background-ratio="0.5">
        <div class="container">
            <div class="single-fact">
                <i class="fas fa-user"></i>
                <div class="number"><span class="counter" data-target="80">0</span>+</div>
                <p>Faculties</p>
            </div>
            <div class="single-fact">
                <i class="fas fa-graduation-cap"></i>
                <div class="number"><span class="counter" data-target="2200">0</span>+</div>
                <p>Our Students</p>
            </div>
            <div class="single-fact">
                <i class="fas fa-chart-line"></i>
                <div class="number"><span class="counter" data-target="100">0</span>%</div>
                <p>Board Results</p>
            </div>
            <div class="single-fact">
                <i class="fas fa-award"></i>
                <div class="number"><span class="counter" data-target="50">0</span>+</div>
                <p>Win Awards</p>
            </div>
        </div>
    </div>



<!-- new template -->
<section class="newtemp-body">
  <div class="new-temp-text">
    <h3>What's Unique?</h3>
  </div>
<div class="newtemp-about-container"<?= ed_add('unique_feature', [], "Add \"What's Unique\" block") ?>>
        <?php foreach ($sj_features as $fi => $f): ?>
        <div class="newtemp-about-section<?= empty($f['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('unique_feature', $f['id'], 'Unique block') ?>>
            <?php if ($fi % 2 === 0): ?>
            <?= img_tag($f['image'], 'feature_4x3', ['alt' => 'Left Image', 'class' => 'newtemp-about-image', 'extra' => trim(ed_img('unique_feature', $f['id']))]) ?>
            <div class="newtemp-about-content">
                <h2 class="infrastructure-text-reveal"<?= ed_field('unique_feature', $f['id'], 'title') ?>><?= e($f['title']) ?></h2>
                <?php ed_rich('unique_feature', $f['id'], 'body_html', $f['body_html']); ?>
            </div>
            <?php else: ?>
            <div class="newtemp-about-content">
                <h2 class="infrastructure-text-reveal"<?= ed_field('unique_feature', $f['id'], 'title') ?>><?= e($f['title']) ?></h2>
                <?php ed_rich('unique_feature', $f['id'], 'body_html', $f['body_html']); ?>
            </div>
            <?= img_tag($f['image'], 'feature_4x3', ['alt' => 'Right Image', 'class' => 'newtemp-about-image', 'extra' => trim(ed_img('unique_feature', $f['id']))]) ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

</div>
</section>
    <!-- template end -->
    <?php include $__pp . '/update-scroll.php'; ?>
    <?php include $__pp . '/new-updates.php'; ?>
    <?php include $__pp . '/marks-scroll.php'; ?>
    <?php include $__pp . '/testimonial.php'; ?>
    <?php include $__pp . '/contact.php'; ?>

<?php /* CASCADE-CRITICAL (visual-freeze): on the baseline the LAST stylesheet
   was the footer template's Bootstrap 4.3.1, loaded in the body flow AFTER
   every section's inline <style>. home-bs4-remnants.css reproduces exactly
   the BS4 fragments that final slot contributed, so it must be linked HERE —
   after the section partials — not in the <head>. Do not move it. */ ?>
<link rel="stylesheet" href="/css/home-bs4-remnants.css?v=<?php echo SJ_ASSET_VER; ?>">

<script>

// Home reveals — shipped selectors/threshold, now through the shared helper in
// /js/site.js (R2). The counters below are unique to Home and stay verbatim.
window.addEventListener('DOMContentLoaded', function () {
    sjReveal('.ab-1, .span-reveal, .infrastructure-text-reveal, .new-temp-text', 100, true);
});

window.addEventListener('scroll', function() {
            incrementCounters();
        });

        function incrementCounters() {
            const counters = document.querySelectorAll('.counter');
            const duration = 2000; // duration in milliseconds

            counters.forEach(counter => {
                const target = +counter.getAttribute('data-target');
                const increment = target / (duration / 10); // Calculate increment based on duration and target

                let count = 0;
                const updateCount = () => {
                    count += increment;
                    if (count < target) {
                        counter.innerText = Math.ceil(count);
                        setTimeout(updateCount, 10); // Update every 10 milliseconds
                    } else {
                        counter.innerText = target; // Ensure the final value is the target
                    }
                };

                const startCounting = () => {
                    if (!counter.classList.contains('counted')) {
                        counter.classList.add('counted');
                        updateCount();
                    }
                };

                var counterTop = counter.getBoundingClientRect().top;
                var windowHeight = window.innerHeight;
                if (counterTop < windowHeight) {
                    startCounting();
                }

            });
        }

        // Initial check to handle counters already in view on page load
        // (the reveal initial check is sjReveal's initNow above)
        incrementCounters();
</script>
