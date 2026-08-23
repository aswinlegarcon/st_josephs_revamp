<?php
// Home page body — rendered inside views/shell.php by SJ\View\Layout::render('home').
// Stage J: sections restyled per PUBLIC_UI_DESIGN.md; the stat band is
// settings-driven (home_stat1..4_value/_label) and counted by site.js
// (sjCounters via data-sj-counters); reveals are the one-time .sj-reveal
// system. All ed_* edit-overlay hooks preserved. Variables from
// public_html/index.php: $sj_page, $sj_principal, $sj_features, $sj_home_page,
// $sj_hero_slides, $sj_ticker, $sj_updates, $sj_marks_years, $sj_testimonials.
$__pp = dirname(__DIR__) . '/partials';
?>
<?php include $__pp . '/carousel.php'; ?>

<!-- welcome band -->
<div class="home-text">
    <h2 <?= $sj_page ? ed_field('page', $sj_page['id'], 'heading_html') : '' ?>><?= $sj_page['heading_html'] ?? '' ?></h2>
</div>

<!-- principal welcome -->
<div class="containers">
<?php if ($sj_principal): ?>
<div class="about-section sj-reveal">
            <div class="about-image">
                <?= img_tag($sj_principal['image'], 'portrait_4x5', ['alt' => 'Profile Image 2', 'extra' => trim(ed_img('profile', $sj_principal['id']))]) ?>
            </div>
            <div class="about-content">
                <h3<?= ed_field('profile', $sj_principal['id'], 'heading') ?>><?= e($sj_principal['heading']) ?></h3>
                <h2 class="ab-1"<?= ed_field('profile', $sj_principal['id'], 'person_name') ?>><?= e($sj_principal['person_name']) ?></h2>
                <?php ed_rich('profile', $sj_principal['id'], 'message_html', $sj_principal['message_html']); ?>
                  <a class="sj-btn sj-btn--gold" href="about.php" role="button" aria-label="Read more about the school">Read more</a>
                </div>
        </div>
<?php endif; ?>
</div>

<?php include $__pp . '/card.php'; ?>

<!-- stat counter band (Stage J: values/labels editable in Site Settings) -->
<div class="fun-facts" data-sj-counters>
        <div class="container">
            <div class="single-fact sj-reveal">
                <i class="fas fa-user" aria-hidden="true"></i>
                <div class="number"><span class="counter" data-target="<?= e(repo_setting('home_stat1_value', '80+')) ?>">0</span></div>
                <p><?= e(repo_setting('home_stat1_label', 'Faculties')) ?></p>
            </div>
            <div class="single-fact sj-reveal" data-sj-delay="80">
                <i class="fas fa-graduation-cap" aria-hidden="true"></i>
                <div class="number"><span class="counter" data-target="<?= e(repo_setting('home_stat2_value', '2200+')) ?>">0</span></div>
                <p><?= e(repo_setting('home_stat2_label', 'Our Students')) ?></p>
            </div>
            <div class="single-fact sj-reveal" data-sj-delay="160">
                <i class="fas fa-chart-line" aria-hidden="true"></i>
                <div class="number"><span class="counter" data-target="<?= e(repo_setting('home_stat3_value', '100%')) ?>">0</span></div>
                <p><?= e(repo_setting('home_stat3_label', 'Board Results')) ?></p>
            </div>
            <div class="single-fact sj-reveal" data-sj-delay="240">
                <i class="fas fa-award" aria-hidden="true"></i>
                <div class="number"><span class="counter" data-target="<?= e(repo_setting('home_stat4_value', '50+')) ?>">0</span></div>
                <p><?= e(repo_setting('home_stat4_label', 'Win Awards')) ?></p>
            </div>
        </div>
    </div>

<!-- What's Unique -->
<section class="newtemp-body">
  <div class="new-temp-text sj-reveal">
    <h2>What's Unique?</h2>
  </div>
<div class="newtemp-about-container"<?= ed_add('unique_feature', [], "Add \"What's Unique\" block") ?>>
        <?php foreach ($sj_features as $fi => $f): ?>
        <div class="newtemp-about-section sj-reveal<?= empty($f['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('unique_feature', $f['id'], 'Unique block') ?>>
            <?php if ($fi % 2 === 0): ?>
            <?= img_tag($f['image'], 'feature_4x3', ['alt' => 'Left Image', 'class' => 'newtemp-about-image', 'extra' => trim(ed_img('unique_feature', $f['id']))]) ?>
            <div class="newtemp-about-content">
                <h2<?= ed_field('unique_feature', $f['id'], 'title') ?>><?= e($f['title']) ?></h2>
                <?php ed_rich('unique_feature', $f['id'], 'body_html', $f['body_html']); ?>
            </div>
            <?php else: ?>
            <div class="newtemp-about-content">
                <h2<?= ed_field('unique_feature', $f['id'], 'title') ?>><?= e($f['title']) ?></h2>
                <?php ed_rich('unique_feature', $f['id'], 'body_html', $f['body_html']); ?>
            </div>
            <?= img_tag($f['image'], 'feature_4x3', ['alt' => 'Right Image', 'class' => 'newtemp-about-image', 'extra' => trim(ed_img('unique_feature', $f['id']))]) ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

</div>
</section>

    <?php include $__pp . '/update-scroll.php'; ?>
    <?php include $__pp . '/new-updates.php'; ?>
    <?php include $__pp . '/marks-scroll.php'; ?>
    <?php include $__pp . '/testimonial.php'; ?>
    <?php include $__pp . '/contact.php'; ?>
