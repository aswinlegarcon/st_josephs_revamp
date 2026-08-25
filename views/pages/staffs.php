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
    'variant'     => 'banner', // K1: slim page-title strip (owner UX decision)
    'preset'      => 'hero_16x7',
    'interval'    => 2000,
    'page_id'     => $sj_page ? (int)$sj_page['id'] : null,
    'keyboardNav' => true,
];
include dirname(__DIR__) . '/partials/hero.php';
?>

<!-- flanked intro: photos = the first two staff blocks (fallback: the old profiles) -->
<?php
$sj_flankL = $sj_staff_blocks[0]['image'] ?? ($sj_team['image'] ?? null);
$sj_flankR = $sj_staff_blocks[1]['image'] ?? ($sj_tour['image'] ?? null);
?>
<?php if ($sj_love): ?>
<div class="infrastructure-container">
    <?= img_tag($sj_flankL, 'feature_4x3', ['alt' => 'Left Image', 'class' => 'infrastructure-image']) ?>
    <div class="infrastructure-text">
        <h4 class="sj-reveal"<?= ed_field('profile', $sj_love['id'], 'person_name') ?>><?= e($sj_love['person_name']) ?></h4>
        <?php ed_rich('profile', $sj_love['id'], 'message_html', $sj_love['message_html']); ?>
    </div>
    <?= img_tag($sj_flankR, 'feature_4x3', ['alt' => 'Right Image', 'class' => 'infrastructure-image']) ?>
</div>
<?php endif; ?>

<!-- staff blocks — K7: creatable list; photos alternate left/right automatically -->
<section class="newtemp-body">
<div class="about-container"<?= ed_add('staff_block', [], 'Add staff block', null, $sj_staff_blocks ? end($sj_staff_blocks) : null) ?>>
        <?php foreach ($sj_staff_blocks as $bi => $B): ?>
        <div class="about-section sj-reveal<?= empty($B['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('staff_block', $B['id'], $B['title']) ?>>
            <?php if ($bi % 2 === 0): ?>
            <?= img_tag($B['image'], 'feature_4x3', ['alt' => 'Left Image', 'class' => 'about-image', 'extra' => trim(ed_img('staff_block', $B['id']))]) ?>
            <div class="about-content">
                <h2<?= ed_field('staff_block', $B['id'], 'title') ?>><?= e($B['title']) ?></h2>
                <?php ed_rich('staff_block', $B['id'], 'body_html', $B['body_html']); ?>
            </div>
            <?php else: ?>
            <div class="about-content">
                <h2<?= ed_field('staff_block', $B['id'], 'title') ?>><?= e($B['title']) ?></h2>
                <?php ed_rich('staff_block', $B['id'], 'body_html', $B['body_html']); ?>
            </div>
            <?= img_tag($B['image'], 'feature_4x3', ['alt' => 'Right Image', 'class' => 'about-image', 'extra' => trim(ed_img('staff_block', $B['id']))]) ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    </section>
    <!-- template end -->

