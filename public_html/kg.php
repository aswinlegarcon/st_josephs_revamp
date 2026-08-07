<?php
// Kinder Garten — thin controller (single-document BS5 layout).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('kg', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'kg',
    'styles'        => ['kg'],            // loads /css/kg.css
    'showJumbotron' => true,              // admissions band (was get_templates('jumbotron'))
]);
