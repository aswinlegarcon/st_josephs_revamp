<?php

namespace SJ\Core;

use PDO;
use PDOException;

/**
 * PDO singleton. Exceptions on, real prepares, assoc fetches, utf8mb4.
 * When config['debug'] is on, uses the global counting PDO/statement classes
 * (defined in _libs/db.php) so db_query_count() works.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $cfg   = Config::all()['db'];
        $debug = !empty(Config::all()['debug']);
        $dsn   = \sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['name']);
        $opts  = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
        if ($debug) {
            $opts[PDO::ATTR_STATEMENT_CLASS] = [\SjCountingStatement::class];
        }

        try {
            self::$pdo = $debug
                ? new \SjCountingPdo($dsn, $cfg['user'], $cfg['pass'], $opts)
                : new PDO($dsn, $cfg['user'], $cfg['pass'], $opts);
        } catch (PDOException $e) {
            if (\PHP_SAPI === 'cli') {
                \fwrite(\STDERR, 'Database connection failed: ' . $e->getMessage() . "\n");
                exit(1);
            }
            \http_response_code(503);
            \header('Content-Type: text/plain; charset=utf-8');
            exit("Database is not reachable. Start the stack with ./run.sh and try again.\n");
        }

        return self::$pdo;
    }
}
