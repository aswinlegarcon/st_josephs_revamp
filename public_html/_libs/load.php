<?php
// Site bootstrap — included at the top of every page (and by the admin panel / CLI seeder).
// Keeps the historic get_templates() include mechanism and adds the dynamic-site layer:
// config, PDO, repositories, media helpers, edit-mode helpers, sanitizer, registry.

if (!defined('SJ_PUBLIC_ROOT')) {
    define('SJ_PUBLIC_ROOT', dirname(__DIR__)); // …/public_html
}

// Static-asset cache-busting version. Bump this ONE line per deploy instead of
// the old `?v=time()` (which re-downloaded every asset on every request).
// Combined with the long-cache .htaccess rules, repeat visits re-fetch nothing.
if (!defined('SJ_ASSET_VER')) {
    define('SJ_ASSET_VER', '20260808.2');
}

// Composer autoloader first — makes SJ\* classes available to the libs below.
require_once __DIR__ . '/../bootstrap.php';

function sj_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/config.php';
    }
    return $cfg;
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sanitize.php';
require_once __DIR__ . '/registry.php';
require_once __DIR__ . '/edit.php';
require_once __DIR__ . '/audit.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/repo.php';

// Historic template include helper — unchanged call sites across all pages.
function get_templates($name)
{
    include SJ_PUBLIC_ROOT . "/_templates/{$name}.php";
}

// Public visitors pay no session cost; the session boots only when the admin
// cookie is present (edit.php). Admin pages force-boot their own session.
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
