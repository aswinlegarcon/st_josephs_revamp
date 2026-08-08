<?php
// gal-teacher — thin controller (shared album template, DB-driven since C9).
require __DIR__ . '/bootstrap.php';

$sj_album = repo_album('gal-teacher', is_edit());
if (!$sj_album) {
    http_response_code(503);
    exit('Album content not seeded.');
}

\SJ\View\Layout::render('album', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => 'gal-teacher',
    'styles'    => ['album'],
    'sj_slug'   => 'gal-teacher',
    'sj_album'  => $sj_album,
]);
