<?php
// Forced / self-service password change (PHASES.md S2, SECURITY.md SEC-08).
// Standalone page (NOT via _layout.php) so the must_change_password guard there
// cannot cause a redirect loop.
require dirname(__DIR__) . '/bootstrap.php';
sj_session_boot(true);
if (function_exists('sj_admin_headers')) { sj_admin_headers(); } // security headers (added in S4)

if (!is_admin()) {
    header('Location: /admin/login.php');
    exit;
}

$forced = !empty($_SESSION['must_change_pw']);
$error  = '';
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        $error = 'Session expired — please try again.';
    } else {
        $current = (string)($_POST['current'] ?? '');
        $new     = (string)($_POST['new'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');

        $st = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $st->execute([(int)$_SESSION['admin_id']]);
        $hash = $st->fetchColumn();

        if (!$hash || !password_verify($current, $hash)) {
            $error = 'Your current password is incorrect.';
        } elseif (mb_strlen($new) < 12) {
            $error = 'New password must be at least 12 characters.';
        } elseif ($new === $current) {
            $error = 'New password must be different from the current one.';
        } elseif ($new !== $confirm) {
            $error = 'New password and confirmation do not match.';
        } else {
            db()->prepare('UPDATE admin_users SET password_hash = ?, must_change_password = 0, password_changed_at = NOW() WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), (int)$_SESSION['admin_id']]);
            if (function_exists('sj_audit')) { sj_audit('password.change'); } // S4
            session_regenerate_id(true);
            unset($_SESSION['must_change_pw']);
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
<title>Change password — SJ Admin</title>
<link rel="icon" href="/photos/logo-main.png" type="image/x-icon">
<style>
  /* N8 "Prospectus" — brand fonts self-hosted (CSP: same-origin only) */
  @font-face { font-family:'Fjalla One'; src:url('/assets/fonts/fjalla-one.woff2') format('woff2');
               font-weight:400; font-style:normal; font-display:swap; }
  :root { --blue:#2b4b8a; --dark:#1a355d; --gold:#ffd700; --gold-ink:#8a6d00; --red:#c62828; --green:#2e7d32; --warn:#b26a00; }
  * { box-sizing:border-box; margin:0; padding:0; font-family:'Segoe UI',Arial,sans-serif; }
  body { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:16px;
         background:#faf8f4; }
  .card { background:#fff; width:min(440px,96vw); border-radius:16px; padding:34px 30px;
          border-top:3px solid var(--gold); box-shadow:0 24px 64px rgba(18,35,63,.22); }
  .brand { text-align:center; margin-bottom:20px; }
  .brand img { width:64px; margin-bottom:8px; }
  h1 { font-family:'Fjalla One',sans-serif; font-weight:400; font-size:21px; color:var(--dark); }
  .sub { color:#7a8598; font-size:13px; margin-top:2px; }
  label { display:block; font-size:11.5px; font-weight:700; letter-spacing:.4px; text-transform:uppercase;
          color:#5f6b80; margin:14px 0 5px; }
  input { width:100%; padding:11px 12px; border:1.5px solid #d5d9e2; border-radius:9px; font-size:15px; }
  input:focus { outline:2px solid var(--blue); outline-offset:1px; border-color:var(--blue); }
  button { width:100%; margin-top:22px; padding:12px; border:0; border-radius:9px; cursor:pointer;
           background:linear-gradient(to right,var(--blue),var(--dark)); color:#fff; font-size:15px; font-weight:600; }
  button:hover { filter:brightness(1.12); }
  .err { background:#fdecea; color:#b3261e; border-radius:9px; padding:10px 12px; font-size:13px; margin-bottom:6px; }
  .ok  { background:#e6f4ea; color:var(--green); border-radius:9px; padding:12px; font-size:14px; text-align:center; }
  .note { background:#fff8dc; border:1px solid #f0e2a0; color:#6d5a0f; border-radius:9px;
          padding:10px 12px; font-size:12.5px; margin-bottom:6px; }
  /* strength meter */
  .meter { display:flex; gap:5px; margin-top:9px; }
  .meter span { flex:1; height:5px; border-radius:3px; background:#e2e7f0; transition:background .18s; }
  .checks { list-style:none; margin-top:10px; font-size:12.5px; color:#7a8598; }
  .checks li { margin:3px 0; }
  .checks li.ok { color:var(--green); }
  .checks li::before { content:"○ "; }
  .checks li.ok::before { content:"✓ "; }
  .actions-link { display:block; text-align:center; margin-top:16px; font-size:13px; color:var(--blue); text-decoration:none; }
</style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <img src="/photos/logo-main.png" alt="St.Joseph's">
      <h1>Set a new password</h1>
      <div class="sub">St.Joseph's MHSS, Ondipudur</div>
    </div>

    <?php if ($done): ?>
      <div class="ok">Your password has been changed.</div>
      <a class="actions-link" href="/admin/">Continue to dashboard →</a>
    <?php else: ?>
      <?php if ($forced): ?><div class="note">For security, you must change the default password before continuing.</div><?php endif; ?>
      <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <label for="current">Current password</label>
        <input id="current" name="current" type="password" required autofocus>
        <label for="new">New password</label>
        <input id="new" name="new" type="password" required minlength="12">
        <div class="meter" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
        <ul class="checks">
          <li data-rule="len">At least 12 characters</li>
          <li data-rule="case">Upper &amp; lowercase letters</li>
          <li data-rule="num">A number</li>
        </ul>
        <label for="confirm">Confirm new password</label>
        <input id="confirm" name="confirm" type="password" required minlength="12">
        <button type="submit">Save &amp; continue</button>
      </form>
      <?php if (!$forced): ?><a class="actions-link" href="/admin/">← Back to dashboard</a><?php endif; ?>
    <?php endif; ?>
  </div>
  <script src="/admin/assets/password.js?v=<?= SJ_ASSET_VER ?>" defer></script>
</body>
</html>
