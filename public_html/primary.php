<?php
// Primary School — thin controller (single-document BS5 layout).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('primary', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'primary',
    'styles'        => ['primary'],       // loads /css/primary.css
    'showJumbotron' => true,              // admissions band (was get_templates('jumbotron'))
]);
