<?php
// Home — thin controller. Gathers data, then renders through the layout engine.
// R1d: home renders in the universal BS5 shell; the content partials are
// parametrized, so ALL queries happen here (none inside views).
require __DIR__ . '/bootstrap.php';

$sj_page      = repo_page('index');
$sj_principal = repo_profile('principal');
$sj_features  = repo_unique_features(is_edit());

// Section data (previously self-queried by the nested templates).
$sj_home_page   = $sj_page;
$sj_hero_slides = $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [];
$sj_ticker        = repo_ticker(is_edit());
$sj_updates       = repo_update_slides(is_edit());
$sj_marks_years   = repo_marks_board(null, is_edit());
$sj_testimonials  = repo_testimonials(is_edit());

\SJ\View\Layout::render('home', [
    'title'          => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'      => 'index',
    'sjNavOverlay'   => true, // Stage J: transparent nav over the full-bleed hero
    'styles'         => ['home'],
    'sj_page'        => $sj_page,
    'sj_principal'   => $sj_principal,
    'sj_features'    => $sj_features,
    'sj_home_page'   => $sj_home_page,
    'sj_hero_slides' => $sj_hero_slides,
    'sj_ticker'        => $sj_ticker,
    'sj_updates'       => $sj_updates,
    'sj_marks_years'   => $sj_marks_years,
    'sj_testimonials'  => $sj_testimonials,
]);
