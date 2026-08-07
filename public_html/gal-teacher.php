<?php
// gal-teacher gallery — thin controller (single-document BS5 layout).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('gal-teacher', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => 'gal-teacher',
    'styles'    => [],
]);
