<?php
// Home page body — rendered inside views/layout.php by SJ\View\Layout::render('home').
// Shared fragments still come via get_templates() (their nested <head>/<body> are
// consolidated in R1a). Variables come from public_html/index.php (the controller):
//   $sj_page, $sj_principal, $sj_features.
?>

<?php  get_templates('preloader');?>
<?php  get_templates('navbar');?>
<?php  get_templates('scroll-up');?>
<?php  get_templates('carousel');?>


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
<?php  get_templates('card');?>


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
    <?php  get_templates('update-scroll');?>
    <?php  get_templates('new-updates');?>
    <?php get_templates('marks-scroll');?>
    <?php  get_templates('testimonial');?>
    <?php  get_templates('contact');?>

    <?php  get_templates('footer');?>
<script>

window.addEventListener('scroll', function() {
            reveal();
            incrementCounters();
        });

        function reveal() {
            var reveals = document.querySelectorAll('.ab-1, .span-reveal, .infrastructure-text-reveal, .new-temp-text');
            var windowHeight = window.innerHeight;

            reveals.forEach(reveal => {
                var revealTop = reveal.getBoundingClientRect().top;
                var revealPoint = 100; // Adjust this value if needed

                if (revealTop < windowHeight - revealPoint) {
                    reveal.classList.add('active');
                } else {
                    reveal.classList.remove('active');
                }
            });
        }

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

        // Initial checks to handle elements already in view on page load
        reveal();
        incrementCounters();
</script>


