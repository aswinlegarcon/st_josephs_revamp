<?php
// PDO singleton. Per DYNAMIC_MIGRATION_PLAN.md §4.1: exceptions on, real prepares,
// assoc fetches, utf8mb4 in the DSN.

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $cfg = sj_config()['db'];
    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['name']);
    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
            exit(1);
        }
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Database is not reachable. Start the stack with ./run.sh and try again.\n");
    }
    return $pdo;
}
