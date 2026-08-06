<?php

namespace SJ\View;

/**
 * Public page layout engine. Renders views/pages/<page>.php inside
 * views/layout.php. Page names come ONLY from a hardcoded whitelist — request
 * data can never select a template path (SECURITY.md SEC-11).
 *
 * P4 introduces the engine + thin-controller pattern; the full single-document
 * consolidation (stripping the shared fragments' nested <head>/<body>) lands in
 * R1a alongside the Bootstrap unification.
 */
final class Layout
{
    /** Pages this engine may render (hardcoded whitelist — SEC-11). Extend as pages migrate. */
    private const PAGES = [
        'home',                                                   // P4 (legacy shell)
        'about', 'staffs', 'academics', 'achievements',          // R1a hub pages
        'co-curriculum', 'sports', 'infrastructure', 'gallery',  // (BS5 shell)
    ];

    /** 'home' keeps its original P4 shell; every converted page uses the BS5 shell. */
    private const LEGACY_SHELL = ['home'];

    public static function render(string $page, array $data = []): void
    {
        if (!\in_array($page, self::PAGES, true)) {
            \http_response_code(500);
            exit('Unknown view.');
        }

        $views = \dirname(SJ_PUBLIC_ROOT) . '/views';
        \extract($data, EXTR_SKIP);

        \ob_start();
        include $views . '/pages/' . $page . '.php';
        $content = \ob_get_clean();

        include $views . '/' . (\in_array($page, self::LEGACY_SHELL, true) ? 'layout.php' : 'shell.php');
    }
}
