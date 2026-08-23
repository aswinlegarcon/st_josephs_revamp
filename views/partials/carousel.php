<?php // Home hero — thin adapter onto the shared hero partial (Stage J).
// The controller contract is unchanged ($sj_home_page + $sj_hero_slides);
// keyboard ←/→ nav is wired by site.js via data-sj-kbnav (one per page).
// The shipped carousel id is kept for deep links and tests.
$sjHero = [
    'id'          => 'carouselExampleIndicators',
    'slides'      => $sj_hero_slides,
    'variant'     => 'hero',
    'preset'      => 'hero_16x7',
    'interval'    => 2000,
    'page_id'     => $sj_home_page ? (int)$sj_home_page['id'] : null,
    'keyboardNav' => true,
];
include __DIR__ . '/hero.php';
