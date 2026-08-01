<?php
// db() shim → SJ\Core\Db (src/Core/Db.php). The dev query-counter classes stay
// here in the global namespace; Db::pdo() references them when config['debug'] is on.

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
    return \SJ\Core\Db::pdo();
}
