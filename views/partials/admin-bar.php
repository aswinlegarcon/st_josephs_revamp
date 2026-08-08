<?php // On-page admin bar (O1) — rendered ONLY for logged-in admins (the shell
// gates on is_admin()), so visitors receive zero admin markup or script bytes.
// The Edit toggle is a POST + CSRF form (never a mutating GET).
$sjCurrentPath = strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?') ?: '/';
?>
<div class="sjov-bar<?= is_edit() ? ' editing' : '' ?>" id="sjov-bar">
  <span class="sjov-brand">SJ Admin</span>
  <span class="sjov-state"><?= is_edit() ? '✏️ Edit mode — click any outlined text or photo' : 'Viewing as a visitor' ?></span>
  <span class="sjov-actions">
    <a class="sjov-link" href="/admin/">Dashboard</a>
    <form method="post" action="/admin/editmode.php" class="sjov-form">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="return" value="<?= e($sjCurrentPath) ?>">
      <input type="hidden" name="on" value="<?= is_edit() ? '0' : '1' ?>">
      <button type="submit" class="sjov-btn<?= is_edit() ? ' on' : '' ?>">
        <?= is_edit() ? '✔ Done editing' : '✏️ Edit this page' ?>
      </button>
    </form>
  </span>
</div>
