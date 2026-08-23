<?php

namespace SJ\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Dev-only query counters (SJ\Core namespace since R2 — _libs/db.php is gone).
 * Db::pdo() wires them in when config['debug'] is on so db_query_count() works.
 */
class CountingStatement extends PDOStatement
{
    public function execute(?array $params = null): bool
    {
        $GLOBALS['__sj_qcount'] = ($GLOBALS['__sj_qcount'] ?? 0) + 1;
        return parent::execute($params);
    }
}

class CountingPdo extends PDO
{
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $GLOBALS['__sj_qcount'] = ($GLOBALS['__sj_qcount'] ?? 0) + 1;
        return $fetchMode === null
            ? parent::query($query)
            : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

/**
 * PDO singleton. Exceptions on, real prepares, assoc fetches, utf8mb4.
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
            $opts[PDO::ATTR_STATEMENT_CLASS] = [CountingStatement::class];
        }

        try {
            self::$pdo = $debug
                ? new CountingPdo($dsn, $cfg['user'], $cfg['pass'], $opts)
                : new PDO($dsn, $cfg['user'], $cfg['pass'], $opts);
            // K6: the school runs on IST — pin the MySQL session so NOW()/
            // timestamps match PHP's Asia/Kolkata default (bootstrap.php).
            // Keeping both on one zone is what keeps the dashboard's
            // "N min ago" feed arithmetic honest.
            self::$pdo->exec("SET time_zone = '+05:30'");
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
