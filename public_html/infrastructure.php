<?php
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('infrastructure', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'infrastructure',
    'styles'        => ['infrastructure'],
    'showJumbotron' => true,
]);
