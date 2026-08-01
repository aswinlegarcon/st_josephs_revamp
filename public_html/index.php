<?php
// Home — thin controller. Gathers data, then renders through the layout engine.
require __DIR__ . '/_libs/load.php';

$sj_page      = repo_page('index');
$sj_principal = repo_profile('principal');
$sj_features  = repo_unique_features(is_edit());

\SJ\View\Layout::render('home', [
    'title'        => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'    => 'index',
    'sj_page'      => $sj_page,
    'sj_principal' => $sj_principal,
    'sj_features'  => $sj_features,
]);
