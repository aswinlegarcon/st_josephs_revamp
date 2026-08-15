<?php
// album — GENERIC thin controller for admin-created gallery albums (N4).
// The 10 shipped gal-* albums keep their own stub files; this route serves
// any album row whose slug is not one of those. The slug is used ONLY as a
// parameterized DB lookup — never a filesystem path (SEC-11) — and the
// template receives the DB row's slug, not the raw request value.
require __DIR__ . '/bootstrap.php';

$slugParam = strtolower(trim((string)($_GET['slug'] ?? '')));
$sj_album  = preg_match('/^[a-z0-9][a-z0-9-]{0,39}$/', $slugParam) ? repo_album($slugParam, is_edit()) : null;
if (!$sj_album) {
    http_response_code(404);
    exit('Not found.');
}
$sj_slug = (string)$sj_album['slug']; // DB value from here on

\SJ\View\Layout::render('album', [
    'title'     => "St.Joseph's MHSS, Ondipudur",
    'bodyClass' => $sj_slug,
    'styles'    => ['album'],
    'sj_slug'   => $sj_slug,
    'sj_album'  => $sj_album,
    // F3 tags: per-album snippet row + exact canonical for this URL.
    'sjSeoSlug'       => $sj_slug,
    'sjCanonicalPath' => '/album.php?slug=' . rawurlencode($sj_slug),
]);
