<?php // Full-screen loading overlay — clean fragment. ?>
<style>
  #preloader { background: #fff url(/photos/Typing.gif) no-repeat center center;
    height: 100vh; width: 100%; position: fixed; z-index: 100; }
</style>
<div id="preloader"></div>
<script>
  var sjLoader = document.getElementById("preloader");
  window.addEventListener("load", function () { if (sjLoader) sjLoader.style.display = "none"; });
</script>
