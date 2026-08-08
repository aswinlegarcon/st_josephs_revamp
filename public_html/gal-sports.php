<?php
// gal-sports — thin controller (shared album template, DB-driven since C9).
require __DIR__ . '/_libs/load.php';

$sj_album = repo_album('gal-sports', is_edit());
if (!$sj_album) {
    http_response_code(503);
    exit('Album content not seeded.');
}

\SJ\View\Layout::render('album', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => 'gal-sports',
    'styles'    => ['albums/gal-sports'],
    'sj_slug'   => 'gal-sports',
    'sj_album'  => $sj_album,
]);
