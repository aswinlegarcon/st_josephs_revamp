<?php
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('staffs', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'infrastructure',
    'styles'        => ['staffs'],
    'showJumbotron' => false,
]);
