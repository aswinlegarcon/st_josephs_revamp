<?php // Home "Our Motto" + "Our Campus" cards — clean fragment (no nested document).
// Markup kept VERBATIM from _templates/card.php (classes card-deck/card-img-top/
// card-body are Bootstrap-4-era; /css/home-bs4-remnants.css re-supplies the BS4
// rules BS5 dropped so the render stays identical). card.css via shell $styles. ?>

<section class="motto ">
    <h1 class="reveal-motto">Our Motto</h1>
    <p class="reveal-motto">Motto of our School is ''DISCIPLINE AND KNOWLEDGE''</p>

    <div class="motto-row">
        <div class="motto-col">
            <h3>Discipline </h3>
            <p class="dp">Discipline is systematic instruction intended to train a person activity, exercise, or a regimen that develops or improves a skill.</p>
        </div>
        <div class="motto-col">
            <h3>Knowledge</h3>
            <p>Knowledge is facts, information, and skills acquired through experience or education; the theoretical or practical understanding of a subject</p>
        </div>
    </div>
</section>



  <!-- Cards Starts -->

  <div class="products ">
    <h1 class="reveal-text">Our Campus</h1>
    <p>Welcome to our campus, a vibrant and nurturing environment designed to foster academic excellence, personal growth, and a sense of community. <br>Our campus is not just a place of learning, but a space where students are encouraged to explore their passions, develop lifelong skills, and build meaningful relationships.</p>

<div class="card-deck reveal-card">
    <div class="card1">
        <img class="card-img-top" src="/photos/card1.jpg" width=200 height=500 alt="Card image cap">
        <div class="card-body">
            <a href="gallery.php" class="btn btn-dark">EXPLORE</a>
        </div>
    </div>
    <div class="card2">
        <img class="card-img-top" src="/photos/card2.jpg" width=200 height=500 alt="Card image cap">
        <div class="card-body">
            <a href="co-curriculum.php" class="btn btn-dark">EXPLORE</a>
        </div>
    </div>
    <div class="card3">
        <img class="card-img-top" src="/photos/card3.jpg" width=200 height=500 alt="Card image cap">
        <div class="card-body">
            <a href="infrastructure.php" class="btn btn-dark">EXPLORE</a>
        </div>
    </div>
</div>
</div>

<script src="/js/card.js"></script>

  <!-- Cards Ends -->
