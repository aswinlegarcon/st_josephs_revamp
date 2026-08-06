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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? "St.Joseph's MHSS, Ondipudur") ?></title>
  <link rel="icon" href="/photos/logo-main.png" type="image/x-icon">

  <!-- ONE self-hosted Bootstrap 5.3.3 -->
  <link rel="stylesheet" href="/assets/vendor/bootstrap-5.3.3/css/bootstrap.min.css?v=<?php echo SJ_ASSET_VER; ?>">
  <!-- ONE Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <!-- Fonts (single request) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&family=Fjalla+One&family=League+Spartan:wght@100..900&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap">

  <!-- Design tokens, then always-on footer styles, then this page's CSS -->
  <link rel="stylesheet" href="/css/tokens.css?v=<?php echo SJ_ASSET_VER; ?>">
  <link rel="stylesheet" href="/css/footer.css?v=<?php echo SJ_ASSET_VER; ?>">
  <?php foreach ($styles as $css): ?>
  <link rel="stylesheet" href="/css/<?= e($css) ?>.css?v=<?php echo SJ_ASSET_VER; ?>">
  <?php endforeach; ?>
</head>
<body class="<?= e($bodyClass) ?>">

<?php if ($showPreloader) include $__p . '/preloader.php'; ?>
<?php include $__p . '/navbar.php'; ?>
<?php include $__p . '/scroll-up.php'; ?>

<?= $content ?>

<?php if ($showJumbotron) include $__p . '/jumbotron.php'; ?>
<?php include $__p . '/footer.php'; ?>

<!-- ONE self-hosted Bootstrap 5.3.3 bundle (includes Popper) -->
<script src="/assets/vendor/bootstrap-5.3.3/js/bootstrap.bundle.min.js?v=<?php echo SJ_ASSET_VER; ?>"></script>
<?php foreach ($scripts as $js): ?>
<script src="/js/<?= e($js) ?>.js"></script>
<?php endforeach; ?>
</body>
</html>
