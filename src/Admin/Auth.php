<?php

namespace SJ\Admin;

/**
 * Admin session lifecycle: lazy boot, timeouts, id rotation, teardown, and the
 * admin security headers. Ported verbatim from _libs/edit.php in R2; the global
 * names (sj_session_boot(), is_admin(), is_edit(), sj_admin_headers()) live in
 * src/helpers.php and delegate here. SECURITY.md SEC-07/13/14.
 */
final class Auth
{
    public const SESSION_IDLE_MAX = 1800;   // 30 min of inactivity
    public const SESSION_ABS_MAX  = 43200;  // 12 h hard cap
    public const SESSION_REGEN    = 900;    // rotate the session id every 15 min

    public static function boot(bool $force = false): void
    {
        static $booted = false;
        if ($booted || \PHP_SAPI === 'cli' || \session_status() === \PHP_SESSION_ACTIVE) {
            $booted = true;
            return;
        }
        if (!$force && empty($_COOKIE['SJADMIN'])) {
            return; // public visitor without an admin cookie: zero session cost
        }
        $cfg = \SJ\Core\Config::all();
        if (!empty($cfg['session_save_path'])) {
            \session_save_path($cfg['session_save_path']); // private dir on prod (SEC-07/22)
        }
        // Secure cookies over real HTTPS, behind an HTTPS-terminating proxy, or when forced by config.
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || !empty($cfg['force_secure_cookies']);
        \session_name('SJADMIN');
        \session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $secure,
        ]);
        \session_start();
        $booted = true;

        // Enforce idle + absolute timeouts and periodic id rotation for logged-in admins.
        // On expiry the session is destroyed so is_admin() becomes false and each caller's
        // existing behaviour fires (panel → login redirect, API → 401 JSON, public → visitor).
        if (!empty($_SESSION['admin_id'])) {
            $now   = \time();
            $last  = $_SESSION['last_seen'] ?? $now;
            $start = $_SESSION['login_at']  ?? $now;
            if (($now - $last) > self::SESSION_IDLE_MAX || ($now - $start) > self::SESSION_ABS_MAX) {
                self::kill();
                return;
            }
            $_SESSION['last_seen'] = $now;
            if (($now - ($_SESSION['last_regen'] ?? 0)) > self::SESSION_REGEN) {
                \session_regenerate_id(true);
                $_SESSION['last_regen'] = $now;
            }
        }
    }

    /** Fully tear down the current session (data, file, cookie). */
    public static function kill(): void
    {
        $_SESSION = [];
        if (\ini_get('session.use_cookies')) {
            $p = \session_get_cookie_params();
            \setcookie(\session_name(), '', \time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        \session_destroy();
    }

    public static function isAdmin(): bool
    {
        return \session_status() === \PHP_SESSION_ACTIVE && !empty($_SESSION['admin_id']);
    }

    public static function isEdit(): bool
    {
        return self::isAdmin() && !empty($_SESSION['edit_mode']);
    }

    /**
     * Hardening headers for admin pages and API endpoints (SECURITY.md SEC-13/14).
     * PHP-emitted so it works regardless of Apache/LiteSpeed module availability.
     */
    public static function headers(): void
    {
        if (\headers_sent()) {
            return;
        }
        \header('X-Frame-Options: DENY');
        \header('X-Content-Type-Options: nosniff');
        \header('Referrer-Policy: strict-origin-when-cross-origin');
        \header(
            "Content-Security-Policy: default-src 'self'; " .
            "img-src 'self' data:; style-src 'self' 'unsafe-inline'; " .
            "script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'"
        );
    }
}
