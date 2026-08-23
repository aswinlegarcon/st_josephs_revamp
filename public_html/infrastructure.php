<?php
// Infrastructure — thin controller (DB-driven since C7).
require __DIR__ . '/bootstrap.php';

$sj_page = repo_page('infrastructure');

\SJ\View\Layout::render('infrastructure', [
    'title'          => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'      => 'infrastructure',
    'sjNavOverlay'   => true, // Stage J: transparent nav over the hero
    'styles'         => ['infrastructure'],
    'showJumbotron'  => true,
    'sj_page'        => $sj_page,
    'sj_hero_slides' => $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [],
    'sj_facilities'  => repo_facilities(is_edit()),
]);
