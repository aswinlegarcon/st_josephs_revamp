<?php // Full-screen loading overlay — clean fragment. Styles in /css/partials/
// (a body <link rel=stylesheet> is valid HTML; a body <style> is not — R3). ?>
<link rel="stylesheet" href="/css/partials/preloader.css?v=<?php echo SJ_ASSET_VER; ?>">
<div id="preloader"></div>
<script>
  var sjLoader = document.getElementById("preloader");
  window.addEventListener("load", function () { if (sjLoader) sjLoader.style.display = "none"; });
</script>
