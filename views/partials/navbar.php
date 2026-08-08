<?php // Site navigation — clean Bootstrap 5 fragment (no nested document).
// STYLING RULE (CLAUDE.md): the styles live VERBATIM in /css/partials/navbar.css
// (a body <link rel=stylesheet> is valid HTML where a body <style> is not — R3;
// same document position, so the cascade is unchanged). The famous invalid
// `background: linear(…)` quirk is preserved there as a comment — see the note.
// R3 validation: the dropdowns' aria-labelledby was dropped (invalid on a plain
// <div>, and inert for assistive tech there — Bootstrap 5.2+ dropped it too). ?>
<link rel="stylesheet" href="/css/partials/navbar.css?v=<?php echo SJ_ASSET_VER; ?>">

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
        <div class="dropdown-menu">
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
        <div class="dropdown-menu">
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
