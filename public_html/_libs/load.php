<?php
// Site bootstrap — included at the top of every page (and by the admin panel / CLI seeder).
// Keeps the historic get_templates() include mechanism and adds the dynamic-site layer:
// config, PDO, repositories, media helpers, edit-mode helpers, sanitizer, registry.

if (!defined('SJ_PUBLIC_ROOT')) {
    define('SJ_PUBLIC_ROOT', dirname(__DIR__)); // …/public_html
}

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
