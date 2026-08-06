<?php
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('gallery', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'gallery',
    'styles'        => ['gallery'],
    'showPreloader' => false,
]);
