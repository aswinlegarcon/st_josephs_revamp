<?php
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('achievements', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'achievements',
    'styles'        => ['achievements'],
    'showJumbotron' => false,
]);
