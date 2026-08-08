<?php
// Achievements — thin controller (DB-driven since C8).
require __DIR__ . '/_libs/load.php';

$sj_page = repo_page('achievements');

\SJ\View\Layout::render('achievements', [
    'title'           => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'       => 'achievements',
    'styles'          => ['achievements'],
    'showJumbotron'   => false,
    'sj_page'         => $sj_page,
    'sj_hero_slides'  => $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [],
    'sj_achievements' => repo_achievements('achievement', is_edit()),
    'sj_awards'       => repo_achievements('award', is_edit()),
]);
