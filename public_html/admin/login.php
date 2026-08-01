<?php
require dirname(__DIR__) . '/_libs/load.php';
sj_session_boot(true);
sj_admin_headers(); // XFO/CSP/nosniff (SEC-13/14)

if (is_admin()) {
    header('Location: /admin/');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        $error = 'Session expired — please try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $st = db()->prepare('SELECT * FROM admin_users WHERE username = ?');
        $st->execute([$username]);
        $user = $st->fetch();

        $locked = $user && $user['locked_until'] !== null && strtotime($user['locked_until']) > time();

        if (!$locked && $user && password_verify($password, $user['password_hash'])) {
            db()->prepare('UPDATE admin_users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')
                ->execute([$user['id']]);
            session_regenerate_id(true);
            $_SESSION['admin_id']       = (int)$user['id'];
            $_SESSION['admin_name']     = $user['display_name'];
            $_SESSION['edit_mode']      = 0;
            $_SESSION['must_change_pw'] = (int)($user['must_change_password'] ?? 0);
            $_SESSION['login_at']       = time();
            $_SESSION['last_seen']      = time();
            $_SESSION['last_regen']     = time();
            unset($_SESSION['csrf']);
            csrf_token(); // fresh token post-login
            if (function_exists('sj_audit')) { sj_audit('login.ok'); } // S4
            header('Location: ' . (!empty($_SESSION['must_change_pw']) ? '/admin/password.php' : '/admin/'));
            exit;
        } else {
            // Every failure (unknown user, wrong password, OR locked) responds
            // identically — no username enumeration, no "locked" oracle (SEC-06).
            // Increment the counter only for a real, not-yet-locked account, and
            // PERSIST it (never reset to 0 on lock) so lockouts actually hold.
            if ($user && !$locked) {
                $fails = (int)$user['failed_logins'] + 1;
                $lock  = $fails >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
                db()->prepare('UPDATE admin_users SET failed_logins = ?, locked_until = ? WHERE id = ?')
                    ->execute([$fails, $lock, $user['id']]);
            }
            if (function_exists('sj_audit')) { sj_audit('login.fail', null, null, mb_substr($username, 0, 50)); } // S4
            sleep(1); // uniform delay on all failure paths (also masks bcrypt timing)
            $error = 'Invalid username or password.';
        }
    }
}
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — St.Joseph's MHSS</title>
<link rel="icon" href="/photos/logo-main.png" type="image/x-icon">
<style>
  :root { --primaryblue:#2b4b8a; --secondaryblue:#1a355d; --gold:#ffd700; }
  * { box-sizing:border-box; margin:0; padding:0; font-family:'Segoe UI',Arial,sans-serif; }
  body { min-height:100vh; display:flex; align-items:center; justify-content:center;
         background:linear-gradient(135deg,var(--primaryblue),var(--secondaryblue)); }
  .card { background:#fff; width:min(400px,92vw); border-radius:14px; padding:36px 32px;
          box-shadow:0 20px 60px rgba(0,0,0,.35); text-align:center; }
  .card img { width:76px; margin-bottom:10px; }
  h1 { font-size:20px; color:var(--secondaryblue); margin-bottom:2px; }
  .sub { color:#888; font-size:13px; margin-bottom:22px; }
  label { display:block; text-align:left; font-size:12px; font-weight:600; color:#555; margin:12px 0 4px; }
  input { width:100%; padding:11px 12px; border:1.5px solid #d5d9e2; border-radius:8px; font-size:15px; }
  input:focus { outline:none; border-color:var(--primaryblue); }
  button { width:100%; margin-top:22px; padding:12px; border:0; border-radius:8px; cursor:pointer;
           background:linear-gradient(to right,var(--primaryblue),var(--secondaryblue));
           color:#fff; font-size:15px; font-weight:600; }
  button:hover { filter:brightness(1.1); }
  .err { background:#fdecea; color:#b3261e; border-radius:8px; padding:10px; font-size:13px; margin-bottom:6px; }
  .back { display:inline-block; margin-top:16px; font-size:13px; color:var(--primaryblue); text-decoration:none; }
</style>
</head>
<body>
  <form class="card" method="post" autocomplete="off">
    <img src="/photos/logo-main.png" alt="St.Joseph's">
    <h1>Admin Panel</h1>
    <div class="sub">St.Joseph's MHSS, Ondipudur</div>
    <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label for="username">Username</label>
    <input id="username" name="username" required autofocus>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" required>
    <button type="submit">Sign in</button>
    <a class="back" href="/index.php">← Back to website</a>
  </form>
</body>
</html>
