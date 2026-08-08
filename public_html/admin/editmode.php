<?php
// POST {csrf, on, return} → toggle the on-page live-edit mode (O1).
// Admin-only, CSRF-checked; the return target must be a same-site path
// (never a full URL — no open redirect).
require __DIR__ . '/../bootstrap.php';
sj_session_boot(true);

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_admin()) {
    header('Location: /admin/login.php');
    exit;
}
$token = (string)($_POST['csrf'] ?? '');
if ($token === '' || !hash_equals(csrf_token(), $token)) {
    http_response_code(403);
    exit('Bad CSRF token.');
}

$_SESSION['edit_mode'] = ((string)($_POST['on'] ?? '0') === '1') ? 1 : 0;
sj_audit($_SESSION['edit_mode'] ? 'editmode.on' : 'editmode.off');

$return = (string)($_POST['return'] ?? '/');
if ($return === '' || $return[0] !== '/' || str_starts_with($return, '//') || str_contains($return, "\n") || str_contains($return, "\r")) {
    $return = '/';
}
header('Location: ' . $return);
exit;
