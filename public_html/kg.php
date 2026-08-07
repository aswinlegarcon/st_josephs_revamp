<?php
// kg — thin controller (shared section template, DB-driven since C4).
require __DIR__ . '/_libs/load.php';

$sj_page        = repo_page('kg');
$sj_hero_slides = $sj_page ? repo_hero_slides((int)$sj_page['id'], is_edit()) : [];
$sj_section     = repo_section('kg');
if (!$sj_section) {
    http_response_code(503);
    exit('Section content not seeded.');
}

\SJ\View\Layout::render('section', [
    'title'          => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'      => 'kg',
    'styles'         => ['kg'],
    'showJumbotron'  => true,
    'sj_slug'        => 'kg',
    'sj_page'        => $sj_page,
    'sj_hero_slides' => $sj_hero_slides,
    'sj_section'     => $sj_section,
    'sj_carousel'    => repo_linked_images('section', (int)$sj_section['id'], 'carousel'),
    'sj_timeline'    => repo_timeline((int)$sj_section['id']),
    'sj_events'      => repo_section_events((int)$sj_section['id']),
]);
