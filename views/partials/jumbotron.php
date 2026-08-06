<?php // Admissions call-to-action band — clean fragment.
// Bootstrap 5 removed the .jumbotron component, so its padding is defined here. ?>
<style>
  .jumbotron { background: linear-gradient(to top, #f0f0f0, #d9d9d9) !important;
    margin-bottom: 0 !important; padding: 4rem 2rem !important; }   /* padding replaces BS4 .jumbotron */
  .jumbotron .container h1 { font-family: "Fjalla One", sans-serif !important; color: black !important; font-weight: 700; }
  .jumbotron .container p { font-family: "League Spartan", sans-serif !important; color: firebrick !important;
    font-weight: 500; font-style: italic; }
  .jumbotron .container .btn { font-family: "League Spartan", sans-serif !important; border: none !important;
    color: #fff; background: linear-gradient(to left, #2b4b8a, #1a355d) !important; }
  .jumbotron .container .btn:hover { background: firebrick !important; }
  @media (max-width: 900px) { .jumbotron .container h1 { font-size: 45px; } }
  @media (max-width: 768px) { .jumbotron .container h1 { font-size: 35px; } }
  @media (max-width: 500px) { .jumbotron .container h1 { font-size: 25px; } .jumbotron .container p { font-size: 14px; } }
</style>
<div class="jumbotron jumbotron-fluid jumbotron-reveal">
  <div class="container text-center">
    <h1 class="display-4">Explore a holistic education at St.Joseph's</h1>
    <p class="lead">Click Here for Admissions</p>
    <a class="btn btn-primary btn-lg" href="/index.php#contact" role="button">Learn more</a>
  </div>
</div>
<script>
  window.addEventListener('scroll', function () {
    document.querySelectorAll('.jumbotron-reveal').forEach(function (el) {
      var revealTop = el.getBoundingClientRect().top;
      if (revealTop < window.innerHeight - 150) el.classList.add('active');
      else el.classList.remove('active');
    });
  });
</script>
