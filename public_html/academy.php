<?php
// academy — GENERIC thin controller for admin-created academies (N3).
// The 18 shipped academies keep their own stub files (tamilacademy.php, …);
// this route serves any academy row whose slug is not one of those. The slug
// is used ONLY as a parameterized DB lookup — never a filesystem path
// (SEC-11) — and everything rendered comes from the DB row (the template
// receives the row's own slug, not the raw request value).
require __DIR__ . '/bootstrap.php';

$slugParam  = strtolower(trim((string)($_GET['slug'] ?? '')));
$sj_academy = preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $slugParam) ? repo_academy($slugParam) : null;
if (!$sj_academy) {
    http_response_code(404);
    exit('Not found.');
}
$sj_slug = (string)$sj_academy['slug']; // DB value from here on

\SJ\View\Layout::render('academy', [
    'title'       => "St.Joseph's MHSS, Ondipudur",
    'bodyClass'   => 'academics',
    'styles'      => ['academy'],
    'sj_slug'     => $sj_slug,
    'sj_academy'  => $sj_academy,
    'sj_carousel' => repo_linked_images('academy', (int)$sj_academy['id'], 'carousel'),
    // F3 tags: reuse the seo_meta row keyed by this academy's slug, and point
    // the canonical at this exact URL (the script-name default would collapse
    // every dynamic academy onto plain /academy.php).
    'sjSeoSlug'        => $sj_slug,
    'sjCanonicalPath'  => '/academy.php?slug=' . rawurlencode($sj_slug),
]);
