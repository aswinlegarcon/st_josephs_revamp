<?php
// Achievements page body — DB-driven since C8. Variables from the controller:
// $sj_page, $sj_hero_slides, $sj_achievements, $sj_awards.
// Items pair up 2-per .achieve-container; the second of each pair is
// class="item reverse" (photo on the right) — the shipped zig-zag.

/** Render one typed list in the shipped paired zig-zag markup. */
function sj_achieve_list(array $rows): void
{
    foreach (array_chunk($rows, 2) as $pair) {
        echo '<div class="achieve-container">' . "\n";
        foreach ($pair as $k => $A) {
            $rev = $k === 1;
            ?>
        <div class="item sj-reveal<?= $rev ? ' reverse' : '' ?><?= empty($A['is_active']) ? ' sj-inactive' : '' ?>"<?= $rev ? ' data-sj-delay="120"' : '' ?><?= ed_item('achievement', $A['id'], $A['title']) ?>>
            <?php if (!$rev): ?>
            <?= img_tag($A['image'], 'feature_4x3', ['alt' => 'Section Image', 'extra' => trim(ed_img('achievement', $A['id']))]) ?>
            <div class="icon">
            <img src="/photos/trophy.png" alt="trophy">
            </div>
            <h3<?= ed_field('achievement', $A['id'], 'title') ?>><?= e($A['title']) ?></h3>
            <p<?= ed_field('achievement', $A['id'], 'subtext') ?>><?= e($A['subtext']) ?></p>
            <?php else: ?>
            <div class="icon">
            <img src="/photos/trophy.png" alt="trophy">
            </div>
            <h3<?= ed_field('achievement', $A['id'], 'title') ?>><?= e($A['title']) ?></h3>
            <p<?= ed_field('achievement', $A['id'], 'subtext') ?>><?= e($A['subtext']) ?></p>
            <?= img_tag($A['image'], 'feature_4x3', ['alt' => 'Section Image', 'extra' => trim(ed_img('achievement', $A['id']))]) ?>
            <?php endif; ?>
        </div>
            <?php
        }
        echo "    </div>\n";
    }
}
?>

<!-- top carousel — Stage J: the shared hero partial (shipped id kept) -->
<?php
$sjHero = [
    'id'          => 'achievementsHeroCarousel',
    'slides'      => $sj_hero_slides,
    'variant'     => 'hero',
    'preset'      => 'hero_16x7',
    'interval'    => 2000,
    'page_id'     => $sj_page ? (int)$sj_page['id'] : null,
    'keyboardNav' => true,
];
include dirname(__DIR__) . '/partials/hero.php';
?>

<!-- Achievement cards start -->
<div class="home-text">
    <h2  class="span-reveal">The <span>Achievements </span> of St.Joseph's</h2>
</div>
<?php sj_achieve_list($sj_achievements); ?>

    <div class="home-text">
    <h2  class="span-reveal">The <span>Awards given by </span> St.Joseph's</h2>
</div>
<?php sj_achieve_list($sj_awards); ?>

