<?php
// Shared layout for the standalone admin panel: auth guard, sidebar navigation,
// header/footer render helpers, and the Add-button metadata helper.

require dirname(__DIR__) . '/bootstrap.php';
sj_session_boot(true);
if (function_exists('sj_admin_headers')) { sj_admin_headers(); } // security headers (added in S4)
// N7: sj_admin_role() also re-verifies the account still exists — a deleted
// admin's surviving session dies here on its next request.
if (!is_admin() || sj_admin_role() === '') {
    header('Location: /admin/login.php');
    exit;
}
// Force the password change before any admin screen is reachable (SEC-08).
if (!empty($_SESSION['must_change_pw'])) {
    header('Location: /admin/password.php');
    exit;
}

/** Sections shown in the sidebar (slug => [icon name, label]) — N7: SVG icons via sj_icon(). */
function panel_sections(): array
{
    $s = [
        'dashboard' => ['home', 'Dashboard'],
        'hero'      => ['image', 'Hero Carousel'],
        'principal' => ['user', 'Principal'],
        'aboutpage' => ['book', 'About Page'],
        'staffspage'   => ['users', 'Staffs Page'],
        'testimonials' => ['quote', 'Testimonials'],
        'sections'     => ['layers', 'School Sections'],
        'academies'    => ['award', 'Academies'],
        'sports'       => ['flag', 'Sports'],
        'facilities'   => ['building', 'Infrastructure'],
        'achievements' => ['trophy', 'Achievements'],
        'gallery'      => ['images', 'Gallery Albums'],
        'unique'    => ['star', "What's Unique"],
        'ticker'    => ['bell', 'News Ticker'],
        'updates'   => ['monitor', 'New Updates'],
        'marks'     => ['chart', 'Top Marks'],
        'media'     => ['folder', 'Media Library'],
        'seo'       => ['search', 'SEO'],
        'settings'  => ['sliders', 'Site Settings'],
    ];
    if (sj_admin_role() === 'owner') {
        $s['admins'] = ['shield', 'Admin Accounts']; // N7 — owners only
    }
    return $s;
}

/** data-panel-add attribute: registry-driven field metadata for the Add modal.
 *  $currentCount (K2): pass the screen's row count so entities with a registry
 *  max_count lose the Add button once the section is full.
 *  $last (K7): pass the LAST existing row — its text/url/enum values pre-fill
 *  the Add form as an editable recommendation (new items usually repeat most
 *  of the previous one). Images/bools/slugs are never copied. */
function panel_add_attr(string $entity, array $preset = [], string $label = 'Add', ?int $currentCount = null, ?array $last = null): string
{
    $reg = sj_registry_entity($entity);
    if ($reg === null || empty($reg['creatable'])) {
        return '';
    }
    if ($currentCount !== null && !empty($reg['max_count']) && $currentCount >= (int)$reg['max_count']) {
        return ''; // section is full — item.php enforces the same cap server-side
    }
    $fields = [];
    $lastOut = [];
    foreach ($reg['fields'] as $name => $def) {
        $fields[] = [
            'name'     => $name,
            'label'    => $def['label'] ?? ucfirst(str_replace('_', ' ', $name)),
            'type'     => $def['type'],
            // K12: 'pagelink' ships the real page list as grouped {value,label}
            // options so the Add form renders a dropdown, not an empty select.
            // (Mirrors EditAttrs::addAttr and api/item.php — keep all three in step.)
            'options'  => $def['type'] === 'pagelink' ? sj_page_link_options() : ($def['values'] ?? null),
            'preset'   => $def['preset'] ?? null,
            'required' => !empty($def['required']),
            'multiline' => !empty($def['multiline']),
        ];
        if ($last !== null && isset($last[$name]) && $last[$name] !== ''
            && in_array($def['type'], ['text', 'url', 'enum', 'pagelink'], true) && empty($def['create_only'])) {
            $lastOut[$name] = (string)$last[$name];
        }
    }
    $payload = ['entity' => $entity, 'preset' => $preset, 'label' => $label, 'fields' => $fields];
    if ($lastOut) {
        $payload['last'] = $lastOut;
    }
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
      <a class="<?= $slug === $active ? 'on' : '' ?>" href="<?= e($href) ?>" title="<?= e($label) ?>"><span class="i"><?= sj_icon($icon) ?></span><span class="t"><?= e($label) ?></span></a>
    <?php endforeach; ?>
  </nav>
  <div class="sj-side-foot">
    <a href="/index.php" target="_blank" title="View website"><?= sj_icon('globe', 16) ?><span>View website</span></a>
    <form method="post" action="/admin/logout.php">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit" class="sj-logout" title="Log out"><?= sj_icon('logout', 16) ?><span>Log out</span></button>
    </form>
  </div>
</aside>
<main class="sj-main">
  <header class="sj-topbar">
    <h1><?= e($title) ?></h1>
    <div class="sj-topbar-right">
      <a class="sj-btn sj-btn-ghost sj-btn-sm" href="/index.php" target="_blank"><?= sj_icon('external', 15) ?> Preview site</a>
      <span class="sj-user"><span class="sj-avatar"><?= e(mb_strtoupper(mb_substr($_SESSION['admin_name'] ?? 'A', 0, 1))) ?></span><?= e($_SESSION['admin_name'] ?? 'admin') ?></span>
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
