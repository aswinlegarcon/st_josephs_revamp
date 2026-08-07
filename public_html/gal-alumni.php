<?php
// gal-alumni gallery — thin controller (single-document BS5 layout).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('gal-alumni', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => 'gal-alumni',
    'styles'    => [],
]);
