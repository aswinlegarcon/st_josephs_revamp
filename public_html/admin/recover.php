<?php
// N1: Admin password recovery — enabled ONLY while a one-time token file
// exists ABOVE the webroot at config/recovery-token.txt (SECURITY.md SEC-24).
//
// Rationale: production is shared hosting with no SSH and no reliable e-mail,
// so "forgot password" must be provable some other way. Whoever can create
// that file (mPanel file manager / FTP) already fully owns the site, so this
// page grants no new power — it just turns "I can edit files" into "I can set
// a new password" without a terminal. The file is deleted on success (single
// use) and the page answers 404 whenever the file is absent or its token is
// too short. Full runbook: DEPLOY.md §9. Dev shortcut:
// database/reset-admin-password.php (CLI).
require dirname(__DIR__) . '/bootstrap.php';
sj_session_boot(true);
sj_admin_headers(); // XFO/CSP/nosniff (SEC-13/14)

$recoveryFile = dirname(SJ_PUBLIC_ROOT) . '/config/recovery-token.txt';
$fileToken    = is_file($recoveryFile) ? trim((string)@file_get_contents($recoveryFile)) : '';
if (mb_strlen($fileToken) < 16) {
    // Not armed (file missing, empty, or token too weak to accept): the page
    // pretends not to exist, so it is zero attack surface in normal operation.
    http_response_code(404);
    exit('Not found.');
}

$error      = '';
$done       = false;
$warnUnlink = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        $error = 'Session expired — please try again.';
    } else {
        $username = trim((string)($_POST['username'] ?? ''));
        $token    = trim((string)($_POST['token'] ?? ''));
        $new      = (string)($_POST['new'] ?? '');
        $confirm  = (string)($_POST['confirm'] ?? '');

        $st = db()->prepare('SELECT id FROM admin_users WHERE username = ?');
        $st->execute([$username]);
        $uid = (int)$st->fetchColumn();

        // Token and username fail with ONE message (no username oracle even
        // during a recovery window); password-quality errors only appear after
        // the token has proven authority.
        if (!hash_equals($fileToken, $token) || $uid <= 0) {
            sj_audit('recover.fail', null, null, mb_substr($username, 0, 50));
            sleep(1); // uniform pacing, like login failures (SEC-06)
            $error = 'Recovery failed — check the token and the username.';
        } elseif (mb_strlen($new) < 12) {
            $error = 'New password must be at least 12 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New password and confirmation do not match.';
        } else {
            db()->prepare(
                'UPDATE admin_users SET password_hash = ?, must_change_password = 0,
                 password_changed_at = NOW(), failed_logins = 0, locked_until = NULL WHERE id = ?'
            )->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            sj_audit('recover.ok', null, $uid, mb_substr($username, 0, 50)); // never the token/password
            // Single use: delete the token file so this page 404s again.
            $warnUnlink = !@unlink($recoveryFile);
            session_regenerate_id(true);
            unset($_SESSION['sj_lf']);
            $done = true;
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
<title>Recover admin access — St.Joseph's MHSS</title>
<link rel="icon" href="/photos/logo-main.png" type="image/x-icon">
<style>
  /* N8 "Prospectus" — brand fonts self-hosted (CSP: same-origin only) */
  @font-face { font-family:'Fjalla One'; src:url('/assets/fonts/fjalla-one.woff2') format('woff2');
               font-weight:400; font-style:normal; font-display:swap; }
  :root { --primaryblue:#2b4b8a; --secondaryblue:#1a355d; --gold:#ffd700; --gold-ink:#8a6d00; }
  * { box-sizing:border-box; margin:0; padding:0; font-family:'Segoe UI',Arial,sans-serif; }
  body { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px;
         background:#faf8f4; }
  .card { background:#fff; width:min(440px,96vw); border-radius:16px; padding:34px 30px;
          border-top:3px solid var(--gold); box-shadow:0 24px 64px rgba(18,35,63,.22); text-align:center; }
  .card img { width:64px; margin-bottom:8px; }
  h1 { font-family:'Fjalla One',sans-serif; font-weight:400; font-size:21px; color:var(--secondaryblue); margin-bottom:2px; }
  .sub { color:#888; font-size:13px; margin-bottom:18px; }
  .note { background:#fff8dc; border:1px solid #f0e2a0; color:#6d5a0f; border-radius:9px;
          padding:10px 12px; font-size:12.5px; margin-bottom:8px; text-align:left; }
  label { display:block; text-align:left; font-size:12px; font-weight:600; color:#555; margin:12px 0 4px; }
  input { width:100%; padding:11px 12px; border:1.5px solid #d5d9e2; border-radius:8px; font-size:15px; }
  input:focus { outline:none; border-color:var(--primaryblue); }
  button { width:100%; margin-top:22px; padding:12px; border:0; border-radius:8px; cursor:pointer;
           background:linear-gradient(to right,var(--primaryblue),var(--secondaryblue));
           color:#fff; font-size:15px; font-weight:600; }
  button:hover { filter:brightness(1.1); }
  .err { background:#fdecea; color:#b3261e; border-radius:8px; padding:10px; font-size:13px; margin-bottom:6px; }
  .ok  { background:#e6f4ea; color:#2e7d32; border-radius:8px; padding:12px; font-size:14px; }
  .warn { background:#fdecea; color:#b3261e; border-radius:8px; padding:12px; font-size:13px; margin-top:10px; text-align:left; }
  .back { display:inline-block; margin-top:16px; font-size:13px; color:var(--primaryblue); text-decoration:none; }
</style>
</head>
<body>
  <?php if ($done): ?>
  <div class="card">
    <img src="/photos/logo-main.png" alt="St.Joseph's">
    <h1>Password updated ✔</h1>
    <div class="sub">St.Joseph's MHSS — admin recovery</div>
    <div class="ok">The password has been changed and the account is unlocked.</div>
    <?php if ($warnUnlink): ?>
    <div class="warn"><b>Action needed:</b> the token file could not be deleted automatically.
      Remove <code>config/recovery-token.txt</code> via the file manager <b>now</b> — recovery
      stays enabled until it is gone.</div>
    <?php endif; ?>
    <a class="back" href="/admin/login.php">→ Sign in</a>
  </div>
  <?php else: ?>
  <form class="card" method="post" autocomplete="off">
    <img src="/photos/logo-main.png" alt="St.Joseph's">
    <h1>Recover admin access</h1>
    <div class="sub">St.Joseph's MHSS, Ondipudur</div>
    <div class="note">This page only works while <code>config/recovery-token.txt</code> exists
      (created via the hosting file manager). Enter the exact token from that file. The file is
      deleted automatically once the password is set, and any lockout is cleared.</div>
    <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label for="username">Admin username</label>
    <input id="username" name="username" required autofocus>
    <label for="token">Recovery token (from the file)</label>
    <input id="token" name="token" required>
    <label for="new">New password (min 12 characters)</label>
    <input id="new" name="new" type="password" minlength="12" required>
    <label for="confirm">Confirm new password</label>
    <input id="confirm" name="confirm" type="password" minlength="12" required>
    <button type="submit">Set new password</button>
    <a class="back" href="/admin/login.php">← Back to sign in</a>
  </form>
  <?php endif; ?>
</body>
</html>
