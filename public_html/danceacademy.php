<?php
// Dance Academy — thin controller (single-document BS5 layout).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('danceacademy', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => 'academics',
    'styles'    => ['academy'],
    'scripts'   => ['academy'],
]);
