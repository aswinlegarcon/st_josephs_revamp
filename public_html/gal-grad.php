<?php
// gal-grad — thin controller (shared album template, DB-driven since C9).
require __DIR__ . '/bootstrap.php';

$sj_album = repo_album('gal-grad', is_edit());
if (!$sj_album) {
    http_response_code(503);
    exit('Album content not seeded.');
}

\SJ\View\Layout::render('album', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => 'gal-grad',
    'styles'    => ['album'],
    'sj_slug'   => 'gal-grad',
    'sj_album'  => $sj_album,
]);
