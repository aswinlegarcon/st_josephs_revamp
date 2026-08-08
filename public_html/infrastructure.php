<?php
// Infrastructure — thin controller (DB-driven since C7).
require __DIR__ . '/_libs/load.php';

$sj_page = repo_page('infrastructure');

\SJ\View\Layout::render('infrastructure', [
    'title'          => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'      => 'infrastructure',
    'styles'         => ['infrastructure'],
    'showJumbotron'  => true,
    'sj_page'        => $sj_page,
    'sj_hero_slides' => $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [],
    'sj_facilities'  => repo_facilities(is_edit()),
]);
