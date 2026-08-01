<?php
// Shared layout for the standalone admin panel: auth guard, sidebar navigation,
// header/footer render helpers, and the Add-button metadata helper.

require dirname(__DIR__) . '/_libs/load.php';
sj_session_boot(true);
if (function_exists('sj_admin_headers')) { sj_admin_headers(); } // security headers (added in S4)
if (!is_admin()) {
    header('Location: /admin/login.php');
    exit;
}
// Force the password change before any admin screen is reachable (SEC-08).
if (!empty($_SESSION['must_change_pw'])) {
    header('Location: /admin/password.php');
    exit;
}

/** Sections shown in the sidebar (slug => [icon, label]). */
function panel_sections(): array
{
    return [
        'dashboard' => ['🏠', 'Dashboard'],
        'hero'      => ['🎠', 'Hero Carousel'],
        'principal' => ['👤', 'Principal'],
        'unique'    => ['✨', "What's Unique"],
        'ticker'    => ['📣', 'News Ticker'],
        'updates'   => ['📺', 'New Updates'],
        'marks'     => ['🏆', 'Top Marks'],
        'media'     => ['🖼️', 'Media Library'],
    ];
}

/** data-panel-add attribute: registry-driven field metadata for the Add modal. */
function panel_add_attr(string $entity, array $preset = [], string $label = 'Add'): string
{
    $reg = sj_registry_entity($entity);
    if ($reg === null || empty($reg['creatable'])) {
        return '';
    }
    $fields = [];
    foreach ($reg['fields'] as $name => $def) {
        $fields[] = [
            'name'     => $name,
            'label'    => $def['label'] ?? ucfirst(str_replace('_', ' ', $name)),
            'type'     => $def['type'],
            'options'  => $def['values'] ?? null,
            'preset'   => $def['preset'] ?? null,
            'required' => !empty($def['required']),
        ];
    }
    $payload = ['entity' => $entity, 'preset' => $preset, 'label' => $label, 'fields' => $fields];
    return " data-panel-add='" . str_replace("'", '&#39;', json_encode($payload, JSON_UNESCAPED_SLASHES)) . "'";
}

function panel_header(string $active, string $title): void
{
    $sections = panel_sections();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title) ?> — SJ Admin</title>
<link rel="icon" href="/photos/logo-main.png" type="image/x-icon">
<meta name="sj-csrf" content="<?= e(csrf_token()) ?>">
<link rel="stylesheet" href="/admin/assets/panel.css?v=<?= time() ?>">
</head>
<body class="sj-panel">
<aside class="sj-side">
  <div class="sj-side-brand">
    <img src="/photos/logo-main.png" alt="">
    <div><b>St.Joseph's</b><span>Admin Panel</span></div>
  </div>
  <nav>
    <?php foreach ($sections as $slug => [$icon, $label]):
        $href = $slug === 'dashboard' ? '/admin/' : '/admin/section.php?s=' . $slug; ?>
      <a class="<?= $slug === $active ? 'on' : '' ?>" href="<?= e($href) ?>"><span class="i"><?= $icon ?></span><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="sj-side-foot">
    <a href="/index.php" target="_blank">🌐 View website</a>
    <form method="post" action="/admin/logout.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="sj-logout">🚪 Log out</button>
    </form>
  </div>
</aside>
<main class="sj-main">
  <header class="sj-topbar">
    <h1><?= e($title) ?></h1>
    <div class="sj-topbar-right">
      <a class="sj-btn sj-btn-ghost" href="/index.php" target="_blank">Preview site ↗</a>
      <span class="sj-user">👤 <?= e($_SESSION['admin_name'] ?? 'admin') ?></span>
    </div>
  </header>
  <div class="sj-content">
    <?php
}

function panel_footer(): void
{
    ?>
  </div>
</main>
<script src="/admin/assets/panel.js?v=<?= time() ?>" defer></script>
</body>
</html>
    <?php
}
