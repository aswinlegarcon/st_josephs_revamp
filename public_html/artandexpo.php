<?php
// Art and Crafts Expo — thin controller (single-document BS5 layout).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('artandexpo', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => 'academics',
    'styles'    => ['academy'],
    'scripts'   => ['academy'],
]);
