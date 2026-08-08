<?php
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('gallery', [
    'sj_albums'     => repo_albums(is_edit()),
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'gallery',
    'styles'        => ['gallery'],
    'showPreloader' => false,
]);
