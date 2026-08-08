<?php
// gal-expressionz — thin controller (shared album template, DB-driven since C9).
require __DIR__ . '/_libs/load.php';

$sj_album = repo_album('gal-expressionz', is_edit());
if (!$sj_album) {
    http_response_code(503);
    exit('Album content not seeded.');
}

\SJ\View\Layout::render('album', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => 'gal-expressionz',
    'styles'    => ['albums/gal-expressionz'],
    'sj_slug'   => 'gal-expressionz',
    'sj_album'  => $sj_album,
]);
