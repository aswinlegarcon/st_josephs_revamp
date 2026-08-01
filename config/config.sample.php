<?php
/**
 * SAMPLE configuration — copy to config/config.php and set real values.
 *
 *   config/config.php lives ABOVE the webroot and is GITIGNORED. It is the only
 *   place production secrets ever live (see SECURITY.md SEC-22, PHASES.md S1).
 *
 * Loading priority (see public_html/_libs/config.php):
 *   1. Environment variables (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS) — win.
 *   2. config/config.php  (this file's real sibling — gitignored)
 *   3. config/config.sample.php (this file — committed dev safety net)
 *
 * In Docker the env vars from .env override the db values below, so the sample
 * placeholders are never actually used for the DB connection there.
 */
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'name' => 'stjosephs',
        'user' => 'stjosephs',
        'pass' => 'CHANGE_ME', // ← real DB password goes in config/config.php on the server
    ],

    'upload_max_bytes' => 10 * 1024 * 1024, // 10 MB

    // Production hardening (consumed from S3 onward; safe defaults for dev).
    'force_secure_cookies' => false, // set true once the site is HTTPS-only
    'session_save_path'    => null,  // e.g. '/home/<account>/tmp/sessions' (chmod 700) in prod
    'debug'                => false, // true → emit a per-page SQL query count (dev only)
];
