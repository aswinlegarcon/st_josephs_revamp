<?php
// Co-curriculum — thin controller (academy cards DB-driven since C5).
require __DIR__ . '/_libs/load.php';

\SJ\View\Layout::render('co-curriculum', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'co-curriculum',
    'styles'        => ['co-curriculum'],
    'showJumbotron' => true,
    'sj_academies'  => repo_academies(is_edit()),
]);
