<?php
// gal-independence — thin controller (shared album template, DB-driven since C9).
require __DIR__ . '/_libs/load.php';

$sj_album = repo_album('gal-independence', is_edit());
if (!$sj_album) {
    http_response_code(503);
    exit('Album content not seeded.');
}

\SJ\View\Layout::render('album', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => 'gal-independence',
    'styles'    => ['albums/gal-independence'],
    'sj_slug'   => 'gal-independence',
    'sj_album'  => $sj_album,
]);
