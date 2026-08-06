<?php
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('sports', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'sports',
    'styles'        => ['sports'],
    'showJumbotron' => true,
]);
