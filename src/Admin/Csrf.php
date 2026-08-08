<?php

namespace SJ\Admin;

/**
 * CSRF token bound to the admin session. Sessionless callers get '' — a POST
 * with an empty token can never match a real one. SECURITY.md SEC-05.
 * Global name csrf_token() (src/helpers.php) delegates here.
 */
final class Csrf
{
    public static function token(): string
    {
        if (\session_status() !== \PHP_SESSION_ACTIVE) {
            return '';
        }
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = \bin2hex(\random_bytes(32));
        }
        return $_SESSION['csrf'];
    }
}
