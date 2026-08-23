<?php
// About — thin controller (single-document BS5 layout, DB-driven since C2).
require __DIR__ . '/bootstrap.php';

$sj_page        = repo_page('about');
$sj_hero_slides = $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [];

// The four content blocks; 'principal' is the same row the home page renders.
$sj_blocks = [];
foreach (['president', 'principal', 'history', 'rules'] as $roleKey) {
    $sj_blocks[$roleKey] = repo_profile($roleKey);
}

$sj_rules_timings = repo_rules_timings(); // K4: the timings table rows

\SJ\View\Layout::render('about', [
    'sj_rules_timings' => $sj_rules_timings,
    'title'          => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'      => 'history',
    'sjNavOverlay'   => true, // Stage J: transparent nav over the hero
    'styles'         => ['about'],
    'showJumbotron'  => true,
    'sj_page'        => $sj_page,
    'sj_hero_slides' => $sj_hero_slides,
    'sj_blocks'      => $sj_blocks,
]);
