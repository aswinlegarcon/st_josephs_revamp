<?php
// N1: Development/ops password reset — command line ONLY:
//
//   docker compose exec web php database/reset-admin-password.php [username]
//
// Prints a random temporary password ONCE, clears any lockout, and forces a
// password change at the next sign-in. Production has no SSH — use the
// recovery-token flow instead (public_html/admin/recover.php, DEPLOY.md §9).
// NEVER pipe the hash through `mysql -e`: the shell eats the `$2y$…` sequences
// and stores a mangled hash. This script avoids that whole class of mistake.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
// Locate the webroot both on the host layout and inside the container mount
// (same probe as database/seed.php).
foreach ([dirname(__DIR__) . '/public_html', '/var/www/html'] as $root) {
    if (is_file($root . '/bootstrap.php')) {
        require $root . '/bootstrap.php';
        break;
    }
}
if (!defined('SJ_PUBLIC_ROOT')) {
    fwrite(STDERR, "Cannot locate public_html/bootstrap.php\n");
    exit(1);
}

$username = $argv[1] ?? 'admin';
$st = db()->prepare('SELECT id FROM admin_users WHERE username = ?');
$st->execute([$username]);
$id = (int)$st->fetchColumn();
if ($id <= 0) {
    fwrite(STDERR, "No admin user '{$username}'.\n");
    exit(1);
}

$temp = bin2hex(random_bytes(9)); // 18 chars, ~72 bits
db()->prepare(
    'UPDATE admin_users SET password_hash = ?, must_change_password = 1,
     failed_logins = 0, locked_until = NULL WHERE id = ?'
)->execute([password_hash($temp, PASSWORD_DEFAULT), $id]);
sj_audit('recover.cli', null, $id, $username);

echo "Temporary password for '{$username}': {$temp}\n";
echo "Lockout cleared. The password MUST be changed at the next sign-in.\n";
