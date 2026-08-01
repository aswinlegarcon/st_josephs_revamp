<?php
// Composer autoloader bootstrap. Required by _libs/load.php before anything else,
// so SJ\* classes are available everywhere. vendor/ is committed (no Composer on prod).

$autoload = \dirname(__DIR__) . '/vendor/autoload.php';
if (\is_file($autoload)) {
    require_once $autoload;
}
