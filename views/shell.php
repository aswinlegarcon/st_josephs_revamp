<?php
// Universal single-document shell for BS5-converted public pages (PHASES.md R1a).
// ONE self-hosted Bootstrap 5.3.3, ONE Font Awesome, fonts, tokens, then per-page CSS.
// Chrome (preloader/navbar/scroll-up/footer) comes from clean partials — no nested docs.
$__p = __DIR__ . '/partials';
$styles     = $styles     ?? [];   // e.g. ['about'] → /css/about.css
$scripts    = $scripts    ?? [];   // e.g. ['sports'] → /js/sports.js
$bodyClass  = $bodyClass  ?? '';
$showPreloader = $showPreloader ?? true;
$showJumbotron = $showJumbotron ?? false;
?>
<?php
// F3 SEO: per-URL title/description/canonical/OG. The slug comes from the
// entry script's own name (server-set, never request-derived); rows live in
// seo_meta (admin-editable). Fallbacks keep pages working with no row.
// N3/N4: generic controllers (academy.php/album.php) pass $sjSeoSlug (the DB
// row's slug, not the raw request) + $sjCanonicalPath so each dynamic page
// keeps its own snippet row and exact canonical URL.
$sjSlug  = (isset($sjSeoSlug) && $sjSeoSlug !== '')
    ? $sjSeoSlug
    : (\basename($_SERVER['SCRIPT_NAME'] ?? 'index.php', '.php') ?: 'index');
$sjSeo   = repo_seo($sjSlug) ?: [];
$sjBase  = rtrim(sj_config()['base_url'] ?? 'https://stjosephsondipudur.com', '/');
$sjCanon = $sjBase . ($sjCanonicalPath ?? ($sjSlug === 'index' ? '/' : '/' . $sjSlug . '.php'));
$sjTitle = ($sjSeo['title'] ?? '') !== '' ? $sjSeo['title'] : ($title ?? "St.Joseph's MHSS, Ondipudur");
$sjDesc  = $sjSeo['description'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($sjTitle) ?></title>
  <?php if ($sjDesc !== ''): ?>
  <meta name="description" content="<?= e($sjDesc) ?>">
  <?php endif; ?>
  <link rel="canonical" href="<?= e($sjCanon) ?>">
  <meta property="og:site_name" content="St.Joseph's MHSS, Ondipudur">
  <meta property="og:type" content="website">
  <meta property="og:title" content="<?= e($sjTitle) ?>">
  <?php if ($sjDesc !== ''): ?>
  <meta property="og:description" content="<?= e($sjDesc) ?>">
  <?php endif; ?>
  <meta property="og:url" content="<?= e($sjCanon) ?>">
  <meta property="og:image" content="<?= e($sjBase) ?>/photos/logo-main.png">
  <link rel="icon" href="/photos/logo-main.png" type="image/x-icon">

  <!-- ONE self-hosted Bootstrap 5.3.3 -->
  <link rel="stylesheet" href="/assets/vendor/bootstrap-5.3.3/css/bootstrap.min.css?v=<?php echo SJ_ASSET_VER; ?>">
  <!-- ONE Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <!-- Fonts (single request) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <?php // Stage K fonts: Fjalla One (display) + Manrope (body) ONLY — the owner
        // retired the Dancing Script accents ("bold & clean"). PUBLIC_UI_DESIGN.md §2. ?>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Manrope:wght@400;500;600;700;800&display=swap">

  <!-- Design tokens, the Stage J shared component layer, always-on footer styles, then this page's CSS -->
  <link rel="stylesheet" href="/css/tokens.css?v=<?php echo SJ_ASSET_VER; ?>">
  <link rel="stylesheet" href="/css/site.css?v=<?php echo SJ_ASSET_VER; ?>">
  <link rel="stylesheet" href="/css/footer.css?v=<?php echo SJ_ASSET_VER; ?>">
  <?php foreach ($styles as $css): ?>
  <link rel="stylesheet" href="/css/<?= e($css) ?>.css?v=<?php echo SJ_ASSET_VER; ?>">
  <?php endforeach; ?>
  <?php // Dynamic per-page CSS built from DB data (academy/facility backgrounds).
        // The page view sets $sjHeadCss (Layout shares its scope with this shell);
        // a head <style> is valid HTML where the old body <style> was not (R3).
        if (!empty($sjHeadCss)): ?>
  <style>
<?= $sjHeadCss ?>
  </style>
  <?php endif; ?>
  <?php if (is_admin()): // O1 overlay — admins only; visitors get zero admin bytes ?>
  <meta name="sj-csrf" content="<?= e(csrf_token()) ?>">
  <link rel="stylesheet" href="/css/admin.css?v=<?php echo SJ_ASSET_VER; ?>">
  <link rel="stylesheet" href="/admin/assets/cropper/cropper.min.css?v=<?php echo SJ_ASSET_VER; ?>">
  <?php endif; ?>
</head>
<body class="<?= e($bodyClass) ?><?= is_edit() ? ' sj-edit-mode' : '' ?>"<?php if (is_admin()):
    $__presets = db()->query('SELECT preset_key, label, max_w, max_h, aspect_w, aspect_h, mode FROM image_presets ORDER BY preset_key')->fetchAll();
    ?> data-sj-presets='<?= str_replace("'", '&#39;', json_encode($__presets, JSON_UNESCAPED_SLASHES)) ?>'<?php endif; ?>>

<?php if ($showPreloader) include $__p . '/preloader.php'; ?>
<?php include $__p . '/navbar.php'; ?>
<?php include $__p . '/scroll-up.php'; ?>

<?= $content ?>

<?php if ($showJumbotron) include $__p . '/jumbotron.php'; ?>
<?php include $__p . '/floating-cta.php'; // Stage J: admissions + WhatsApp quick actions ?>
<?php include $__p . '/footer.php'; ?>

<!-- ONE self-hosted Bootstrap 5.3.3 bundle (includes Popper) -->
<script src="/assets/vendor/bootstrap-5.3.3/js/bootstrap.bundle.min.js?v=<?php echo SJ_ASSET_VER; ?>"></script>
<!-- shared reveal/blur helpers (R2) — loaded before the per-page scripts -->
<script src="/js/site.js?v=<?php echo SJ_ASSET_VER; ?>"></script>
<?php foreach ($scripts as $js): ?>
<script src="/js/<?= e($js) ?>.js"></script>
<?php endforeach; ?>
<?php if (is_admin()): // O1/O2 live-edit overlay ?>
<?php include $__p . '/admin-bar.php'; ?>
<script src="/admin/assets/cropper/cropper.min.js?v=<?php echo SJ_ASSET_VER; ?>" defer></script>
<script src="/admin/assets/sj-ui.js?v=<?php echo SJ_ASSET_VER; ?>" defer></script>
<script src="/js/admin.js?v=<?php echo SJ_ASSET_VER; ?>" defer></script>
<?php endif; ?>
</body>
</html>
