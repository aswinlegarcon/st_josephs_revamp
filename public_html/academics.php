<?php
require __DIR__ . '/bootstrap.php';

\SJ\View\Layout::render('academics', [
    'sj_sections'   => repo_sections(),
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'academics',
    'sjNavOverlay'  => true, // Stage J: transparent nav over the hero
    'styles'        => ['academics'],
    'showJumbotron' => false,
]);
