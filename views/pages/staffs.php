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
<!-- top carousel — Stage J: the shared hero partial (shipped id kept) -->
<?php
$sjHero = [
    'id'          => 'carouselExampleSlidesOnly',
    'slides'      => $sj_hero_slides,
    'variant'     => 'hero',
    'preset'      => 'hero_16x7',
    'interval'    => 2000,
    'page_id'     => $sj_page ? (int)$sj_page['id'] : null,
    'keyboardNav' => true,
];
include dirname(__DIR__) . '/partials/hero.php';
?>

<!-- infrastructurement cards start -->
<?php if ($sj_love): ?>
<div class="infrastructure-container">
    <?= img_tag($sj_team['image'] ?? null, 'feature_4x3', ['alt' => 'Left Image', 'class' => 'infrastructure-image']) ?>
    <div class="infrastructure-text">
        <h4 class="sj-reveal"<?= ed_field('profile', $sj_love['id'], 'person_name') ?>><?= e($sj_love['person_name']) ?></h4>
        <?php ed_rich('profile', $sj_love['id'], 'message_html', $sj_love['message_html']); ?>
    </div>
    <?= img_tag($sj_tour['image'] ?? null, 'feature_4x3', ['alt' => 'Right Image', 'class' => 'infrastructure-image']) ?>
</div>
<?php endif; ?>




<!-- new template -->
<section class="newtemp-body">
<div class="about-container">
        <?php if ($sj_team): ?>
        <div class="about-section sj-reveal">
            <?= img_tag($sj_team['image'], 'feature_4x3', ['alt' => 'Left Image', 'class' => 'about-image', 'extra' => trim(ed_img('profile', $sj_team['id']))]) ?>
            <div class="about-content">
                <h2<?= ed_field('profile', $sj_team['id'], 'person_name') ?>><?= e($sj_team['person_name']) ?></h2>
                <?php ed_rich('profile', $sj_team['id'], 'message_html', $sj_team['message_html']); ?>

              </div>

        </div>
        <?php endif; ?>
        <?php if ($sj_tour): ?>
        <div class="about-section sj-reveal">
            <div class="about-content">
                <h2<?= ed_field('profile', $sj_tour['id'], 'person_name') ?>><?= e($sj_tour['person_name']) ?></h2>
                <?php ed_rich('profile', $sj_tour['id'], 'message_html', $sj_tour['message_html']); ?>
            </div>
            <?= img_tag($sj_tour['image'], 'feature_4x3', ['alt' => 'Right Image', 'class' => 'about-image', 'extra' => trim(ed_img('profile', $sj_tour['id']))]) ?>
        </div>
        <?php endif; ?>

    </div>
    </section>
    <!-- template end -->

