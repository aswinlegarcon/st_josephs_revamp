<?php
// Staffs — thin controller (single-document BS5 layout, DB-driven since C3).
require __DIR__ . '/bootstrap.php';

$sj_page        = repo_page('staffs');
$sj_hero_slides = $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [];

$sj_blocks = [];
foreach (['staff_love', 'staff_team', 'staff_tour'] as $roleKey) {
    $sj_blocks[$roleKey] = repo_profile($roleKey);
}

\SJ\View\Layout::render('staffs', [
    'title'          => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'      => 'infrastructure',
    'sjNavOverlay'   => true, // Stage J: transparent nav over the hero
    'styles'         => ['staffs'],
    'showJumbotron'  => false,
    'sj_page'        => $sj_page,
    'sj_hero_slides' => $sj_hero_slides,
    'sj_blocks'      => $sj_blocks,
]);
