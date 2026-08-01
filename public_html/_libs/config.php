<?php
// Site configuration. Values come from environment variables (set by docker-compose)
// with local-dev fallbacks. No secrets beyond the local demo credentials.
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'stjosephs',
        'user' => getenv('DB_USER') ?: 'stjosephs',
        'pass' => getenv('DB_PASS') ?: 'stjosephs_pw',
    ],
    'upload_max_bytes' => 10 * 1024 * 1024, // 10 MB
];
