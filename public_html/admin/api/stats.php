<?php
// N7: GET ?r=stats — live dashboard vitals. Admin-only (auth via _bootstrap;
// GET like images.php so no CSRF needed). Returns sizes and counters ONLY —
// no filesystem paths, no software versions beyond PHP's, no secrets (SEC-20
// spirit: status, never content). Polled by admin/assets/dashboard.js.
//
// PASSIVE: this is a background heartbeat, not human activity. Session
// timeouts are still enforced on it, but it must not refresh `last_seen` —
// otherwise an open dashboard tab would keep a session alive indefinitely
// (SECURITY.md SEC-07). Must be defined BEFORE the bootstrap boots the session.
define('SJ_PASSIVE_REQUEST', true);
require __DIR__ . '/_bootstrap.php';

$t0 = microtime(true);

/** Directory size in bytes (bounded: our media/photos trees are ~1k files). */
function sj_dir_bytes(string $dir): int
{
    if (!is_dir($dir)) {
        return 0;
    }
    $sum = 0;
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        $sum += $f->getSize();
    }
    return $sum;
}

// Database size (data + indexes) for OUR schema only.
$dbMb = (float)db()->query(
    'SELECT ROUND(SUM(data_length + index_length) / 1048576, 1)
     FROM information_schema.tables WHERE table_schema = DATABASE()'
)->fetchColumn();

// Disk.
$free  = @disk_free_space(SJ_PUBLIC_ROOT);
$total = @disk_total_space(SJ_PUBLIC_ROOT);

// Server memory — /proc/meminfo when readable (Linux); nulls elsewhere.
$mem = null;
if (is_readable('/proc/meminfo')) {
    $mi = (string)@file_get_contents('/proc/meminfo');
    if (preg_match('/MemTotal:\s+(\d+)/', $mi, $mt) && preg_match('/MemAvailable:\s+(\d+)/', $mi, $ma)) {
        $mem = [
            'total_mb'     => (int)round($mt[1] / 1024),
            'available_mb' => (int)round($ma[1] / 1024),
        ];
    }
}

// OPcache (guarded — may be disabled or restricted on shared hosting).
$op = null;
if (function_exists('opcache_get_status')) {
    $st = @opcache_get_status(false);
    if (is_array($st) && !empty($st['opcache_enabled'])) {
        $op = [
            'hit_rate' => round((float)($st['opcache_statistics']['opcache_hit_rate'] ?? 0), 1),
            'used_mb'  => (int)round(($st['memory_usage']['used_memory'] ?? 0) / 1048576),
            'free_mb'  => (int)round(($st['memory_usage']['free_memory'] ?? 0) / 1048576),
        ];
    }
}

// Backups: age only (SEC-20 — never a download path).
$bdir = sj_config()['backup_dir'] ?? (dirname(SJ_PUBLIC_ROOT) . '/backups');
$newest = 0;
$bsize = 0;
foreach (glob($bdir . '/db-*.sql.gz') ?: [] as $f) {
    if ((int)filemtime($f) > $newest) {
        $newest = (int)filemtime($f);
        $bsize  = (int)filesize($f);
    }
}

api_out([
    'php'   => PHP_VERSION,
    'db_mb' => $dbMb,
    'disk'  => [
        'free_gb'  => $free !== false ? round($free / 1073741824, 1) : null,
        'total_gb' => $total ? round($total / 1073741824, 1) : null,
        'used_pct' => ($free !== false && $total) ? (int)round(100 - $free / $total * 100) : null,
    ],
    'media_mb'  => (int)round(sj_dir_bytes(SJ_PUBLIC_ROOT . '/media') / 1048576),
    'photos_mb' => (int)round(sj_dir_bytes(SJ_PUBLIC_ROOT . '/photos') / 1048576),
    'mem'       => $mem,
    'php_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
    'opcache'   => $op,
    'counts'    => [
        'images'     => (int)db()->query('SELECT COUNT(*) FROM images')->fetchColumn(),
        'edits_24h'  => (int)db()->query('SELECT COUNT(*) FROM audit_log WHERE created_at > NOW() - INTERVAL 1 DAY')->fetchColumn(),
        'admins'     => (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn(),
    ],
    'backup' => $newest ? ['age_h' => round((time() - $newest) / 3600, 1), 'size_kb' => (int)round($bsize / 1024)] : null,
    'gen_ms' => (int)round((microtime(true) - $t0) * 1000),
]);
