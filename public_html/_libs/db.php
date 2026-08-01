<?php
// PDO singleton. Per DYNAMIC_MIGRATION_PLAN.md §4.1: exceptions on, real prepares,
// assoc fetches, utf8mb4 in the DSN.
//
// When config['debug'] is on (dev only), queries are counted so the per-page
// budget (≤ 12, see CLAUDE.md) can be checked. Zero overhead when debug is off.

class SjCountingStatement extends PDOStatement
{
    public function execute(?array $params = null): bool
    {
        $GLOBALS['__sj_qcount'] = ($GLOBALS['__sj_qcount'] ?? 0) + 1;
        return parent::execute($params);
    }
}

class SjCountingPdo extends PDO
{
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $GLOBALS['__sj_qcount'] = ($GLOBALS['__sj_qcount'] ?? 0) + 1;
        return $fetchMode === null
            ? parent::query($query)
            : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
}

/** Number of SQL statements executed this request (0 unless debug is on). */
function db_query_count(): int
{
    return $GLOBALS['__sj_qcount'] ?? 0;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $cfg   = sj_config()['db'];
    $debug = !empty(sj_config()['debug']);
    $dsn   = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $cfg['host'], $cfg['port'], $cfg['name']);
    $opts  = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    if ($debug) {
        $opts[PDO::ATTR_STATEMENT_CLASS] = [SjCountingStatement::class];
    }
    try {
        $pdo = $debug
            ? new SjCountingPdo($dsn, $cfg['user'], $cfg['pass'], $opts)
            : new PDO($dsn, $cfg['user'], $cfg['pass'], $opts);
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
