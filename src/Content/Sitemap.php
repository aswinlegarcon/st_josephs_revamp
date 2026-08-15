<?php

namespace SJ\Content;

/**
 * N3/N4: sitemap.xml regeneration. F3 shipped a hand-written static file for
 * the 41 shipped URLs; now that admins can CREATE academies and albums, the
 * file is rebuilt whenever the public URL set changes (item create/delete,
 * is_active toggles on academy/gallery_album).
 *
 * The 41 shipped paths are a code literal (same order as the F3 file); rows
 * whose slug is NOT a legacy slug are appended as /academy.php?slug=… and
 * /album.php?slug=… (active rows only). Slugs are DB values that passed the
 * strict 'slug' validation on write, and are XML-escaped again on output.
 *
 * Failure is non-fatal by design: on shared hosting PHP runs as the account
 * user and can write the webroot; in Docker the bind mount may refuse
 * (uid mismatch) — the boolean result is surfaced to the panel, never an
 * exception into the save that triggered it.
 */
final class Sitemap
{
    /** The shipped URLs, exactly as F3 listed them. */
    private const STATIC_PATHS = [
        '/', '/about.php', '/staffs.php', '/academics.php', '/co-curriculum.php',
        '/sports.php', '/infrastructure.php', '/achievements.php', '/gallery.php',
        '/highschl.php', '/highsec.php', '/primary.php', '/kg.php',
        '/tamilacademy.php', '/mathsacademy.php', '/scienceacademy.php',
        '/englishacademy.php', '/socialacademy.php', '/langacademy.php',
        '/communicativeacademy.php', '/abacusacademy.php', '/vocalacademy.php',
        '/instrumentacademy.php', '/danceacademy.php', '/artacademy.php',
        '/martialacademy.php', '/yogaacademy.php', '/sportsacademy.php',
        '/band.php', '/ncc.php', '/artandexpo.php',
        '/gal-annual.php', '/gal-sports.php', '/gal-children.php', '/gal-expo.php',
        '/gal-independence.php', '/gal-teacher.php', '/gal-grad.php',
        '/gal-alumni.php', '/gal-expressionz.php', '/gal-spach.php',
    ];

    public static function regenerate(): bool
    {
        $base   = \rtrim(\sj_config()['base_url'] ?? 'https://stjosephsondipudur.com', '/');
        $paths  = self::STATIC_PATHS;
        $legacy = Registry::legacySlugs();

        try {
            foreach (\db()->query('SELECT slug FROM academies WHERE is_active = 1 ORDER BY position, id')->fetchAll(\PDO::FETCH_COLUMN) as $slug) {
                if (!\in_array($slug, $legacy['academy'], true)) {
                    $paths[] = '/academy.php?slug=' . \rawurlencode($slug);
                }
            }
            foreach (\db()->query('SELECT slug FROM gallery_albums WHERE is_active = 1 ORDER BY position, id')->fetchAll(\PDO::FETCH_COLUMN) as $slug) {
                if (!\in_array($slug, $legacy['gallery_album'], true)) {
                    $paths[] = '/album.php?slug=' . \rawurlencode($slug);
                }
            }
        } catch (\Throwable $e) {
            return false; // no DB → keep the existing file untouched
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
             . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($paths as $p) {
            $xml .= '  <url><loc>' . \htmlspecialchars($base . $p, \ENT_XML1 | \ENT_QUOTES, 'UTF-8') . "</loc></url>\n";
        }
        $xml .= "</urlset>\n";

        $target = SJ_PUBLIC_ROOT . '/sitemap.xml';
        // Prefer an atomic tmp+rename; fall back to a direct overwrite when the
        // directory refuses new files but the existing file is writable.
        $tmp = $target . '.tmp';
        if (@\file_put_contents($tmp, $xml) !== false) {
            if (@\rename($tmp, $target)) {
                return true;
            }
            @\unlink($tmp);
        }
        return @\file_put_contents($target, $xml) !== false;
    }
}
