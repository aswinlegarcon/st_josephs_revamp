<?php
/**
 * Configuration LOADER. Contains no credentials.
 *
 * Resolves settings in priority order:
 *   1. Environment variables (DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS) — always win
 *      (docker-compose / the host provide these).
 *   2. <repo>/config/config.php   — real secrets, ABOVE the webroot, gitignored.
 *   3. <repo>/config/config.sample.php — committed dev safety net.
 *
 * See SECURITY.md SEC-22 and PHASES.md S1.
 */

$root   = dirname(SJ_PUBLIC_ROOT);              // repo root (parent of public_html)
$secret = $root . '/config/config.php';
$sample = $root . '/config/config.sample.php';

$cfg = is_file($secret) ? require $secret
     : (is_file($sample) ? require $sample
     : ['db' => ['host' => '127.0.0.1', 'port' => '3306', 'name' => 'stjosephs', 'user' => 'stjosephs', 'pass' => ''],
        'upload_max_bytes' => 10 * 1024 * 1024]);

// Environment variables override the file (Docker and host deployments).
foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'name' => 'DB_NAME', 'user' => 'DB_USER', 'pass' => 'DB_PASS'] as $key => $env) {
    $val = getenv($env);
    if ($val !== false && $val !== '') {
        $cfg['db'][$key] = $val;
    }
}

return $cfg;
