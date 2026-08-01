<?php
// Logout — POST + CSRF only (no mutating GET; SECURITY.md SEC-05/15).
require dirname(__DIR__) . '/_libs/load.php';
sj_session_boot(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
    header('Location: /admin/');
    exit;
}

if (is_admin()) {
    sj_audit('logout');
}
sj_session_kill();
header('Location: /admin/login.php');
exit;
