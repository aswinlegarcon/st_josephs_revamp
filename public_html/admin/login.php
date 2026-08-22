<?php
require dirname(__DIR__) . '/bootstrap.php';
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
            $_SESSION['admin_role']     = (string)($user['role'] ?? 'owner'); // N7
            $_SESSION['edit_mode']      = 0;
            $_SESSION['must_change_pw'] = (int)($user['must_change_password'] ?? 0);
            $_SESSION['login_at']       = time();
            $_SESSION['last_seen']      = time();
            $_SESSION['last_regen']     = time();
            unset($_SESSION['csrf'], $_SESSION['sj_lf']);
            csrf_token(); // fresh token post-login
            if (function_exists('sj_audit')) { sj_audit('login.ok'); } // S4
            header('Location: ' . (!empty($_SESSION['must_change_pw']) ? '/admin/password.php' : '/admin/'));
            exit;
        } else {
            // N1: the lockout is now VISIBLE (owner decision — SECURITY.md
            // SEC-06/SEC-24). When the account is locked (or this failure locks
            // it) the message says so, with the minutes remaining, so the real
            // owner is never left guessing after exhausting the 5 tries. A
            // session-scoped SHADOW counter shows the exact same message for
            // unknown usernames after 5 tries, so within a session the lock
            // text cannot be used to confirm a username exists. Wrong-password
            // and unknown-user still share one generic message; DB counter
            // semantics are unchanged from S3 (persisted, reset on success).
            $lockedUntilTs = null;
            if ($user) {
                if ($locked) {
                    $lockedUntilTs = strtotime($user['locked_until']);
                } else {
                    $fails = (int)$user['failed_logins'] + 1;
                    $lock  = $fails >= 5 ? date('Y-m-d H:i:s', time() + 15 * 60) : null;
                    db()->prepare('UPDATE admin_users SET failed_logins = ?, locked_until = ? WHERE id = ?')
                        ->execute([$fails, $lock, $user['id']]);
                    if ($lock !== null) {
                        $lockedUntilTs = time() + 15 * 60;
                    }
                }
            }
            // Shadow counter: per session, per (last-tried) username string.
            $lf = $_SESSION['sj_lf'] ?? ['u' => '', 'n' => 0, 'until' => 0];
            if (!hash_equals($lf['u'], $username)) {
                $lf = ['u' => $username, 'n' => 0, 'until' => 0];
            }
            $lf['n']++;
            if ($lf['n'] >= 5 && !$lf['until']) {
                $lf['until'] = time() + 15 * 60;
            }
            $_SESSION['sj_lf'] = $lf;
            if ($lockedUntilTs === null && $lf['until'] > time()) {
                $lockedUntilTs = $lf['until'];
            }
            if (function_exists('sj_audit')) { sj_audit('login.fail', null, null, mb_substr($username, 0, 50)); } // S4
            sleep(1); // uniform delay on all failure paths (also masks bcrypt timing)
            if ($lockedUntilTs !== null && $lockedUntilTs > time()) {
                $mins  = max(1, (int)ceil(($lockedUntilTs - time()) / 60));
                $error = 'Too many failed sign-in attempts — this account is temporarily locked. '
                       . 'Please wait about ' . $mins . ' minute' . ($mins === 1 ? '' : 's') . ' and try again.';
            } else {
                $error = 'Invalid username or password.';
            }
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
  /* N8 "Prospectus" split-panel — brand fonts self-hosted (CSP: same-origin only) */
  @font-face { font-family:'Fjalla One'; src:url('/assets/fonts/fjalla-one.woff2') format('woff2');
               font-weight:400; font-style:normal; font-display:swap; }
  @font-face { font-family:'Dancing Script'; src:url('/assets/fonts/dancing-script.woff2') format('woff2');
               font-weight:400 700; font-style:normal; font-display:swap; }
  :root { --blue:#2b4b8a; --dark:#1a355d; --gold:#ffd700; --gold-ink:#8a6d00; --paper:#faf8f4; }
  * { box-sizing:border-box; margin:0; padding:0; font-family:'Segoe UI',Arial,sans-serif; }
  body { min-height:100vh; display:flex; background:var(--paper); padding:18px; }
  .auth { display:flex; width:min(880px,100%); margin:auto; border-radius:18px; overflow:hidden;
          box-shadow:0 24px 64px rgba(18,35,63,.30); animation:in .45s cubic-bezier(.2,.7,.3,1); }
  @keyframes in { from { opacity:0; transform:translateY(10px); } }
  .auth-brand { flex:1 1 46%; color:#fff; padding:48px 36px; text-align:center;
                display:flex; flex-direction:column; justify-content:center; align-items:center; gap:6px;
                background:radial-gradient(120% 60% at 50% 0%, rgba(43,75,138,.6), transparent 60%),
                           linear-gradient(160deg,var(--blue),var(--dark)); }
  .auth-brand img { width:96px; filter:drop-shadow(0 4px 14px rgba(0,0,0,.4)); margin-bottom:10px; }
  .auth-brand h2 { font-family:'Fjalla One',sans-serif; font-weight:400; font-size:24px; letter-spacing:.5px; }
  .auth-brand .script { font-family:'Dancing Script',cursive; color:var(--gold); font-size:26px; font-weight:600; }
  .auth-brand p { color:#c3cee4; font-size:12.5px; letter-spacing:1.4px; text-transform:uppercase; margin-top:8px; }
  .auth-form { flex:1 1 54%; background:#fff; padding:46px 38px; display:flex; flex-direction:column; justify-content:center; }
  h1 { font-family:'Fjalla One',sans-serif; font-weight:400; font-size:24px; color:var(--dark); }
  h1::after { content:""; display:block; width:44px; height:4px; border-radius:4px; background:var(--gold); margin:7px 0 4px; }
  .sub { color:#8a94a6; font-size:13px; margin-bottom:18px; }
  label { display:block; font-size:11px; font-weight:700; letter-spacing:.7px; text-transform:uppercase;
          color:var(--gold-ink); margin:14px 0 5px; }
  input { width:100%; padding:11px 12px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:15px;
          transition:border-color .14s ease, box-shadow .14s ease; }
  input:focus { outline:none; border-color:var(--blue); box-shadow:0 0 0 3px rgba(43,75,138,.22); }
  button { width:100%; margin-top:22px; padding:12px; border:0; border-radius:9px; cursor:pointer;
           background:linear-gradient(135deg,var(--blue),var(--dark));
           color:#fff; font-size:15px; font-weight:600;
           transition:transform .14s ease, box-shadow .14s ease, filter .14s ease; }
  button:hover { filter:brightness(1.08); transform:translateY(-1px); box-shadow:0 10px 28px rgba(26,32,44,.18); }
  button:active { transform:translateY(0) scale(.98); }
  .err { background:#fdecea; color:#b3261e; border-radius:8px; padding:10px 12px; font-size:13px; margin-bottom:6px; }
  .back { display:inline-block; margin-top:18px; font-size:13px; color:var(--blue); text-decoration:none; }
  .back:hover { color:var(--gold-ink); }
  @media (max-width:760px) { .auth { flex-direction:column; } .auth-brand { padding:30px 24px; } .auth-form { padding:32px 24px; } }
  @media (prefers-reduced-motion: reduce) { * { animation:none !important; transition:none !important; } }
</style>
</head>
<body>
  <div class="auth">
    <div class="auth-brand">
      <img src="/photos/logo-main.png" alt="St.Joseph's">
      <span class="script">Discipline &amp; Knowledge</span>
      <h2>St.Joseph's Matric. Hr. Sec. School</h2>
      <p>Ondipudur · Coimbatore</p>
    </div>
    <form class="auth-form" method="post" autocomplete="off">
      <h1>Admin Panel</h1>
      <div class="sub">Sign in to manage the website</div>
      <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <label for="username">Username</label>
      <input id="username" name="username" required autofocus>
      <label for="password">Password</label>
      <input id="password" name="password" type="password" required>
      <button type="submit">Sign in</button>
      <div>
        <a class="back" href="/index.php">← Back to website</a>
        <?php if (is_file(dirname(SJ_PUBLIC_ROOT) . '/config/recovery-token.txt')): // N1: shown only while recovery is armed ?>
        <a class="back" style="margin-left:14px" href="/admin/recover.php">Forgot password?</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
</body>
</html>
