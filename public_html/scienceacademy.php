<?php
// scienceacademy — thin controller (shared academy template, DB-driven since C5).
require __DIR__ . '/bootstrap.php';

$sj_academy = repo_academy('scienceacademy');
if (!$sj_academy) {
    http_response_code(503);
    exit('Academy content not seeded.');
}

\SJ\View\Layout::render('academy', [
    'title'       => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'   => 'academics',
    'styles'      => ['academy'],
    'sj_slug'     => 'scienceacademy',
    'sj_academy'  => $sj_academy,
    'sj_carousel' => repo_linked_images('academy', (int)$sj_academy['id'], 'carousel'),
]);
