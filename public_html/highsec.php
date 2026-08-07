<?php
// Higher Secondary — thin controller (single-document BS5 layout).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('highsec', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'highsec',
    'styles'        => ['highsec'],
    'showJumbotron' => true,
]);
