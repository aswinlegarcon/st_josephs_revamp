<?php
// Deployment smoke-check (PHASES.md X1). Token-gated so it isn't a public info leak.
//   /admin/health.php?token=<config health_token>
// Returns JSON; HTTP 200 when everything is green, 503 otherwise.
require dirname(__DIR__) . '/bootstrap.php';
sj_session_boot(true);
if (function_exists('sj_admin_headers')) { sj_admin_headers(); }
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// Access: a matching token, OR an authenticated admin.
$token = (string)(sj_config()['health_token'] ?? '');
$given = (string)($_GET['token'] ?? '');
$allowed = (is_admin()) || ($token !== '' && hash_equals($token, $given));
if (!$allowed) {
    http_response_code(404); // don't reveal the endpoint to strangers
    exit;
}

$checks = [];
$check = function (string $name, bool $ok, string $value = '') use (&$checks) {
    $checks[$name] = ['ok' => $ok] + ($value !== '' ? ['value' => $value] : []);
};

$check('php_version',  version_compare(PHP_VERSION, '8.1', '>='), PHP_VERSION);
$check('ext_gd',       extension_loaded('gd'));
$check('ext_pdo_mysql',extension_loaded('pdo_mysql'));
$check('ext_exif',     extension_loaded('exif'));

try { db()->query('SELECT 1'); $check('db_connect', true); }
catch (\Throwable $e) { $check('db_connect', false, 'unreachable'); }

try {
    $n = (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    $check('schema', $n > 0, "admin_users=$n");
} catch (\Throwable $e) { $check('schema', false, 'missing tables'); }

$mediaDir = SJ_PUBLIC_ROOT . '/media';
$check('media_writable', is_dir($mediaDir) && is_writable($mediaDir));

$check('opcache', (bool) ini_get('opcache.enable'));
// https is environment-dependent (false on the http dev box) — reported, not gating.
$check('https',   (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                  || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'));

$gating = $checks;
unset($gating['https']);
$allOk = array_reduce($gating, static fn($c, $x) => $c && $x['ok'], true);
http_response_code($allOk ? 200 : 503);
echo json_encode([
    'ok'        => $allOk,
    'asset_ver' => defined('SJ_ASSET_VER') ? SJ_ASSET_VER : null,
    'checks'    => $checks,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
