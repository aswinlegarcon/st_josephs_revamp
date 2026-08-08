<?php
// gal-annual — thin controller (shared album template, DB-driven since C9).
require __DIR__ . '/_libs/load.php';

$sj_album = repo_album('gal-annual', is_edit());
if (!$sj_album) {
    http_response_code(503);
    exit('Album content not seeded.');
}

\SJ\View\Layout::render('album', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => 'gal-annual',
    'styles'    => ['albums/gal-annual'],
    'sj_slug'   => 'gal-annual',
    'sj_album'  => $sj_album,
]);
