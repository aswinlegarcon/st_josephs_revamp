<?php
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('academics', [
    'sj_sections'   => repo_sections(),
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'academics',
    'styles'        => ['academics'],
    'showJumbotron' => false,
]);
