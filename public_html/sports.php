<?php
// Sports — thin controller (DB-driven since C6).
require __DIR__ . '/_libs/load.php';

$sj_page       = repo_page('sports');
$sportsData    = ['suffixes' => ['', '2', '3']]; // shipped reveal-class sequence

\SJ\View\Layout::render('sports', [
    'title'          => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'      => 'sports',
    'styles'         => ['sports'],
    'showJumbotron'  => true,
    'sj_page'        => $sj_page,
    'sj_hero_slides' => $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [],
    'sj_sports'      => repo_sports(is_edit()),
    'sj_suffixes'    => $sportsData['suffixes'],
]);
