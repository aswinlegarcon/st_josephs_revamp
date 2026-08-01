<?php
require dirname(__DIR__) . '/_libs/load.php';
sj_session_boot(true);
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: /admin/login.php');
exit;
