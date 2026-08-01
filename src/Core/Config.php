<?php

namespace SJ\Core;

/**
 * Configuration loader. No credentials live here.
 *
 * Priority: environment variables (DB_*) → <repo>/config/config.php (gitignored,
 * above the webroot) → <repo>/config/config.sample.php. See SECURITY.md SEC-22.
 */
final class Config
{
    private static ?array $cfg = null;

    public static function all(): array
    {
        if (self::$cfg !== null) {
            return self::$cfg;
        }

        $root   = \dirname(SJ_PUBLIC_ROOT);            // repo root (parent of public_html)
        $secret = $root . '/config/config.php';
        $sample = $root . '/config/config.sample.php';

        $cfg = \is_file($secret) ? require $secret
             : (\is_file($sample) ? require $sample
             : ['db' => ['host' => '127.0.0.1', 'port' => '3306', 'name' => 'stjosephs', 'user' => 'stjosephs', 'pass' => ''],
                'upload_max_bytes' => 10 * 1024 * 1024]);

        foreach (['host' => 'DB_HOST', 'port' => 'DB_PORT', 'name' => 'DB_NAME', 'user' => 'DB_USER', 'pass' => 'DB_PASS'] as $key => $env) {
            $val = \getenv($env);
            if ($val !== false && $val !== '') {
                $cfg['db'][$key] = $val;
            }
        }

        return self::$cfg = $cfg;
    }

    public static function get(string $key, $default = null)
    {
        return self::all()[$key] ?? $default;
    }
}
