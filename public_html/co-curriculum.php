<?php
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('co-curriculum', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'co-curriculum',
    'styles'        => ['co-curriculum'],
    'showJumbotron' => true,
]);
