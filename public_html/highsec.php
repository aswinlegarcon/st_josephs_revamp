<?php
// Higher Secondary — thin controller (single-document BS5 layout).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('highsec', [
    'title'          => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'      => 'highsec',
    'styles'         => ['highsec'],
    'showJumbotron'  => true,
    // R1d: the marks-scroll partial is parametrized (was a self-querying template).
    'sj_marks_years' => repo_marks_board(null, is_edit()),
]);
