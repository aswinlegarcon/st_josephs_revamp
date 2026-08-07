<?php
// High School — thin controller (single-document BS5 layout).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('highschl', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'highschl',
    'styles'        => ['highschl'],
    'showJumbotron' => true,
]);
