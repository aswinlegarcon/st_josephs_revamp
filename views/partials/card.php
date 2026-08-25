<?php // Home "Our Motto" + "Our Campus" cards — Stage J restyle (classes kept,
// rules rewritten in home.css; reveals are the one-time .sj-reveal system). ?>

<?php // K7: the whole motto block is settings-driven now (Site Settings →
      // "Home motto block"); fallbacks = the shipped strings. ?>
<section class="motto">
    <h1 class="sj-reveal"><?= e(repo_setting('motto_heading', 'Our Motto')) ?></h1>
    <p class="sj-reveal"><?= e(repo_setting('motto_sub', "Motto of our School is ''DISCIPLINE AND KNOWLEDGE''")) ?></p>

    <div class="motto-row">
        <div class="motto-col sj-reveal">
            <h2><?= e(repo_setting('motto1_title', 'Discipline')) ?></h2>
            <p class="dp"><?= e(repo_setting('motto1_body', 'Discipline is systematic instruction intended to train a person activity, exercise, or a regimen that develops or improves a skill.')) ?></p>
        </div>
        <div class="motto-col sj-reveal" data-sj-delay="120">
            <h2><?= e(repo_setting('motto2_title', 'Knowledge')) ?></h2>
            <p><?= e(repo_setting('motto2_body', 'Knowledge is facts, information, and skills acquired through experience or education; the theoretical or practical understanding of a subject')) ?></p>
        </div>
    </div>
</section>

  <!-- Campus cards -->
  <div class="products">
    <h1 class="sj-reveal">Our Campus</h1>
    <p>Welcome to our campus, a vibrant and nurturing environment designed to foster academic excellence, personal growth, and a sense of community. <br>Our campus is not just a place of learning, but a space where students are encouraged to explore their passions, develop lifelong skills, and build meaningful relationships.</p>

<div class="card-deck">
    <div class="card1 sj-reveal">
        <img class="card-img-top" src="/photos/card1.jpg" width=200 height=500 alt="Card image cap">
        <div class="card-body">
            <a href="gallery.php" class="btn btn-dark">EXPLORE</a>
        </div>
    </div>
    <div class="card2 sj-reveal" data-sj-delay="100">
        <img class="card-img-top" src="/photos/card2.jpg" width=200 height=500 alt="Card image cap">
        <div class="card-body">
            <a href="co-curriculum.php" class="btn btn-dark">EXPLORE</a>
        </div>
    </div>
    <div class="card3 sj-reveal" data-sj-delay="200">
        <img class="card-img-top" src="/photos/card3.jpg" width=200 height=500 alt="Card image cap">
        <div class="card-body">
            <a href="infrastructure.php" class="btn btn-dark">EXPLORE</a>
        </div>
    </div>
</div>
</div>
