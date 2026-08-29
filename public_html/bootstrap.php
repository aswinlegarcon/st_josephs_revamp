<?php
// Site bootstrap — required at the top of every page, admin endpoint and the
// CLI seeder. Replaces _libs/load.php (R2): constants, Composer autoload
// (SJ\ classes + the global helpers in src/helpers.php), lazy session boot,
// and the dev-only SQL query counter.

if (!defined('SJ_PUBLIC_ROOT')) {
    define('SJ_PUBLIC_ROOT', __DIR__); // …/public_html
}

// K6: the school lives in IST — every date()/greeting renders in
// Asia/Kolkata regardless of the server's zone (the container is UTC; the
// DB session is pinned to +05:30 in Db.php so timestamps stay consistent).
date_default_timezone_set('Asia/Kolkata');

// Static-asset cache-busting version. Bump this ONE line per deploy instead of
// the old `?v=time()` (which re-downloaded every asset on every request).
// Combined with the long-cache .htaccess rules, repeat visits re-fetch nothing.
if (!defined('SJ_ASSET_VER')) {
    define('SJ_ASSET_VER', '20260825.7'); // K10: brand lockup (bigger crest + address line) + testimonial top-crop
}

// Composer autoloader: SJ\* classes plus src/helpers.php (the historic global
// function names — e(), db(), repo_*(), img_tag(), ed_*(), …). vendor/ is
// committed, so prod needs no Composer run.
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Public visitors pay no session cost; the session boots only when the admin
// cookie is present. Admin pages force-boot their own session with (true).
sj_session_boot(false);

// Dev-only: append the per-request SQL query count to HTML responses so the
// ≤12/page budget (CLAUDE.md) is visible. Skips JSON APIs and CLI.
if (!empty(sj_config()['debug']) && PHP_SAPI !== 'cli') {
    register_shutdown_function(function () {
        foreach (headers_list() as $h) {
            if (stripos($h, 'content-type: application/json') === 0) {
                return; // never corrupt an API response
            }
        }
        echo "\n<!-- sj-queries: " . db_query_count() . " -->";
    });
}
