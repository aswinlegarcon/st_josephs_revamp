<?php
// About — thin controller (single-document BS5 layout).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('about', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'history',
    'styles'        => ['about'],
    'showJumbotron' => true,
]);
