<?php
// Co-curriculum — thin controller (academy cards DB-driven since C5).
require __DIR__ . '/bootstrap.php';

\SJ\View\Layout::render('co-curriculum', [
    'title'         => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'     => 'co-curriculum',
    'sjNavOverlay'  => true, // Stage J: transparent nav over the hero
    'styles'        => ['co-curriculum'],
    'showJumbotron' => true,
    'sj_academies'  => repo_academies(is_edit()),
]);
