<?php

namespace SJ\View;

/**
 * Public page layout engine. Renders views/pages/<page>.php inside
 * views/layout.php. Page names come ONLY from a hardcoded whitelist — request
 * data can never select a template path (SECURITY.md SEC-11).
 *
 * P4 introduced the engine + thin-controller pattern; R1a–R1d completed the
 * single-document consolidation — every page (home included) renders inside
 * the universal BS5 shell (views/shell.php).
 */
final class Layout
{
    /** Pages this engine may render (hardcoded whitelist — SEC-11). Extend as pages migrate. */
    private const PAGES = [
        'home',                                                   // P4, moved to BS5 shell in R1d
        'about', 'staffs', 'academics', 'achievements',          // R1a hub pages
        'co-curriculum', 'sports', 'infrastructure', 'gallery',  // (BS5 shell)
        // C5 — the 18 academy-family pages share views/pages/academy.php
        'academy',
        // C4 — the four section pages share views/pages/section.php
        'section',
        // R1c — gallery pages (11)
        'gal-alumni', 'gal-annual', 'gal-children', 'gal-expo', 'gal-expressionz',
        'gal-grad', 'gal-independence', 'gal-sciexpo', 'gal-spach', 'gal-sports', 'gal-teacher',
    ];

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

        include $views . '/shell.php';
    }
}
