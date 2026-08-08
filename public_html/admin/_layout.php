<?php
// Shared layout for the standalone admin panel: auth guard, sidebar navigation,
// header/footer render helpers, and the Add-button metadata helper.

require dirname(__DIR__) . '/bootstrap.php';
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
        'aboutpage' => ['📖', 'About Page'],
        'staffspage'   => ['🧑‍🏫', 'Staffs Page'],
        'testimonials' => ['💬', 'Testimonials'],
        'sections'     => ['🏫', 'School Sections'],
        'academies'    => ['🎓', 'Academies'],
        'sports'       => ['🏅', 'Sports'],
        'facilities'   => ['🏗️', 'Infrastructure'],
        'achievements' => ['🏆', 'Achievements'],
        'gallery'      => ['🖼️', 'Gallery Albums'],
        'unique'    => ['✨', "What's Unique"],
        'ticker'    => ['📣', 'News Ticker'],
        'updates'   => ['📺', 'New Updates'],
        'marks'     => ['🏆', 'Top Marks'],
        'media'     => ['🖼️', 'Media Library'],
        'seo'       => ['🔍', 'SEO'],
        'settings'  => ['⚙️', 'Site Settings'],
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
            'multiline' => !empty($def['multiline']),
        ];
    }
    $payload = ['entity' => $entity, 'preset' => $preset, 'label' => $label, 'fields' => $fields];
    return " data-panel-add='" . str_replace("'", '&#39;', json_encode($payload, JSON_UNESCAPED_SLASHES)) . "'";
}

/** data-panel-photos attribute: opens the M2 "Manage photos" modal (link API). */
function panel_photos_attr(string $ownerType, int $ownerId, string $role, string $preset, string $label = 'Manage photos'): string
{
    if (!isset(\SJ\Content\Registry::ownerTypes()[$ownerType])) {
        return ''; // never emit a button for a non-whitelisted owner
    }
    $payload = ['owner_type' => $ownerType, 'owner_id' => $ownerId, 'role' => $role, 'preset' => $preset, 'label' => $label];
    return " data-panel-photos='" . str_replace("'", '&#39;', json_encode($payload, JSON_UNESCAPED_SLASHES)) . "'";
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
<link rel="stylesheet" href="/admin/assets/panel.css?v=<?= SJ_ASSET_VER ?>">
<link rel="stylesheet" href="/admin/assets/cropper/cropper.min.css?v=<?= SJ_ASSET_VER ?>">
</head>
<?php // data-sj-presets: preset metadata for panel.js (CSP-safe — no inline scripts).
$__presets = db()->query('SELECT preset_key, label, max_w, max_h, aspect_w, aspect_h, mode FROM image_presets ORDER BY preset_key')->fetchAll(); ?>
<body class="sj-panel" data-sj-presets='<?= str_replace("'", "&#39;", json_encode($__presets, JSON_UNESCAPED_SLASHES)) ?>'>
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
<script src="/admin/assets/cropper/cropper.min.js?v=<?= SJ_ASSET_VER ?>" defer></script>
<script src="/admin/assets/sj-ui.js?v=<?= SJ_ASSET_VER ?>" defer></script>
<script src="/admin/assets/panel.js?v=<?= SJ_ASSET_VER ?>" defer></script>
</body>
</html>
    <?php
}
