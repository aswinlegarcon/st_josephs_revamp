<?php
require __DIR__ . '/bootstrap.php';

// K7: the academics hero is hero_slide-backed now (page row + slides seeded
// from the shipped statics); the view keeps a static fallback for DBs that
// predate migration 010.
$sj_page        = repo_page('academics');
$sj_hero_slides = $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [];

\SJ\View\Layout::render('academics', [
    'sj_page'        => $sj_page,
    'sj_hero_slides' => $sj_hero_slides,
    'sj_sections'   => repo_sections(),
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'academics',
    'sjNavOverlay'  => true, // Stage J: transparent nav over the hero
    'styles'        => ['academics'],
    'showJumbotron' => false,
]);
