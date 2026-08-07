<?php // Site navigation — clean Bootstrap 5 fragment (no nested document).
// STYLING RULE (CLAUDE.md): this <style> is a VERBATIM copy of the original
// _templates/navbar.php styling. Do not "improve", tidy, or re-value any rule
// here — the page must render pixel-identical to the pre-revamp site. Only the
// HTML dialect (data-bs-*, ms-auto/me-auto) and the dead-link fix are allowed to
// differ, because those are functional/code changes, not visual ones. ?>
<style>
  /* Site-wide base font — the original navbar carried this global rule, so it
     applied to every page. Restored here so converted pages keep League Spartan. */
  * { font-family: "League Spartan", sans-serif; font-weight: 400; }

  body { margin: 0; font-family: Arial, sans-serif; }

  /* NOTE: the original value is `linear( ... )` — an INVALID CSS function. The
     browser therefore IGNORES this declaration and the navbar keeps Bootstrap's
     .bg-light (light grey). That grey is the intended production look. Do NOT
     "correct" this to linear-gradient() — doing so introduces a navy gradient and
     changes the design. Kept verbatim from the original site on purpose. */
  .navbar {
    background: linear( #2b4b8a 20%, #ffffff 70%);
    border-bottom: 3px solid transparent;
    border-image: linear-gradient(90deg, rgba(2,0,36,0) 0%, #2a4ac6 50%, rgba(0,212,255,0) 100%);
    border-image-slice: 1;
  }

  .sticky {
    position: fixed !important;
    top: 0 !important;
    width: 100% !important;
    z-index: 1000 !important;
    background-color: white;
  }

  .nav-item a { font-size: 19px; word-spacing: 10px; color: black !important; }
  .nav-item a:hover { color: #2b4b8a !important; }

  .navbar-brand .img-1 { width: 95px; height: 114px; }

  @media (max-width: 460px) {
    .navbar-brand .img-1 { width: 85px; height: 100px; }
    .logo1 img { width: 180px; height: 40px; }
    .logo-text { font-size: 14px; }
    .navbar-nav { font-size: 17px; }
    .navbar-nav .dropdown-menu { font-size: 16px; }
  }
  @media (max-width: 400px) {
    .navbar-brand .img-1 { width: 70px; height: 85px; }
    .logo1 img { width: 150px; height: 30px; }
    .logo-text { font-size: 11px; }
    .navbar-nav { font-size: 15px; }
    .navbar-nav .dropdown-menu { font-size: 14px; }
  }
</style>

<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <a class="navbar-brand" href="/index.php">
    <img class="img-1" src="/photos/logo-main.png" alt="St.Joseph's logo">
  </a>
  <div class="logo1 me-auto">
    <img src="/photos/st.png" width="210" height="40" alt="St.Joseph's">
    <div class="logo-text">Ondipudur, Coimbatore - 641016</div>
  </div>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown"
          aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarNavDropdown">
    <ul class="navbar-nav ms-auto">
      <li class="nav-item">
        <a class="nav-link" href="/index.php">Home</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="navAboutDropdown" role="button"
           data-bs-toggle="dropdown" aria-expanded="false">About</a>
        <div class="dropdown-menu" aria-labelledby="navAboutDropdown">
          <a class="dropdown-item" href="/about.php">Our School</a>
          <a class="dropdown-item" href="/staffs.php">Our Staffs</a>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/infrastructure.php">Infrastructure</a>
      </li>
      <li class="nav-item dropdown">
        <a class="nav-link dropdown-toggle" href="#" id="navCurriculumDropdown" role="button"
           data-bs-toggle="dropdown" aria-expanded="false">Curriculum</a>
        <div class="dropdown-menu" aria-labelledby="navCurriculumDropdown">
          <a class="dropdown-item" href="/academics.php">Academics</a>
          <a class="dropdown-item" href="/co-curriculum.php">Co-Curriculum</a>
          <a class="dropdown-item" href="/sports.php">Sports</a>
        </div>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/achievements.php">Achievements</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/gallery.php">Gallery</a>
      </li>
    </ul>
  </div>
</nav>

<script>
  document.addEventListener("DOMContentLoaded", function () {
    window.addEventListener("scroll", function () {
      var header = document.querySelector(".navbar");
      if (header) header.classList.toggle("sticky", window.scrollY > 0);
    });
  });
</script>
