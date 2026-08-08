<?php // Students Testimonial cards — clean fragment (no nested document).
// C3: DB-driven — $sj_testimonials comes from the page controller. name_html/
// body_html are sanitized rich fields (write-path whitelist), echoed raw by
// design; the card1/2/3 background classes cycle by position.
// The <style> and <script> are VERBATIM from _templates/testimonial.php — do
// not tidy (incl. the .testimonial-body rule, which matched nothing on the
// baseline because the nested <body class="testimonial-body"> was discarded by
// the parser; no wrapper is added here so it keeps matching nothing).
// The cards use class "card card1..3": Bootstrap-4's .card contribution
// (radius/border/background) is re-supplied by /css/home-bs4-remnants.css. ?>
<style>
        .testimonial-body {
  font-family: Arial, sans-serif;

  color: white;
  margin: 0;
  padding: 0;
  text-align: center;
}

.testimonial-container {
  background-color: #fff5f5 !important;
  padding: 40px;
}

.testimonial-container h1 {
  font-family: "Fjalla One", sans-serif !important;
  font-weight: 700;
  font-size: 46px;
  margin-bottom: 30px;
  text-align: center;
}
/* animation */
.testimonial-reveal {
  transition: 1.2s;
  transform: translateY(50px);
  opacity: 0;
}
.testimonial-reveal.active {
  opacity: 1;
  transform: translateY(0);
}
.testimonial {
  display: flex;
  justify-content: space-around;
  flex-wrap: wrap;
}

.card {
  background-size: cover;
  border-radius: 10px;
  padding: 20px;
  margin: 10px;
  width: 30%;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  text-align: left;
  position: relative;
}
.card:hover {
  box-shadow: 0px 0px 30px 5px rgba(0, 0, 0, 0.2);
}
.card1 {
  background: linear-gradient(rgba(43, 75, 138, 0.7), rgba(26, 53, 93, 0.7)),
    url("../photos/testimonial1.png");
    background-size:100% 100%;
}
.card2 {
  background: linear-gradient(rgba(43, 75, 138, 0.7), rgba(26, 53, 93, 0.7)),
    url("../photos/testimonial2.png");
    background-size:100% 100%;
}
.card3 {
  background: linear-gradient(rgba(43, 75, 138, 0.7), rgba(26, 53, 93, 0.7)),
    url("../photos/testimonial3.png");
    background-size:100% 100%;

}

.card h3 {
  font-family: "Fjalla One", sans-serif !important;
  border-bottom: 2px solid #fff;
  padding-bottom: 10px;
  margin-bottom: 20px;
  margin-top: 70px;
  color: gold;
}

.card p {
  font-family: "LeagueSpartan", sans-serif !important;
  color: white;
  line-height: 1.6;
  margin-bottom: 10px;
  text-align: justify;
}

.quote-icon {
  position: absolute;
  top: 20px;
  right: 48%;
  width: 50px;
  height: 50px;
}

@media (max-width: 768px) {
  .card {
    width: 80%;
  }
}

    </style>
    <div class="testimonial-container">
        <h1 class="testimonial-reveal">Students Testimonial</h1>
        <div class="testimonial"<?= ed_add('testimonial', [], 'Add testimonial') ?>>
            <?php foreach ($sj_testimonials as $ti => $t): ?>
            <div class="card card<?= ($ti % 3) + 1 ?><?= empty($t['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('testimonial', $t['id'], 'Testimonial') ?>>
            <img class="quote-icon" src="/photos/quote.png" alt="quote icon">
                <h3<?= ed_field('testimonial', $t['id'], 'name_html') ?>><?= $t['name_html'] ?></h3>
                <?php ed_rich('testimonial', $t['id'], 'body_html', $t['body_html']); ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
// Shipped reveal (scroll-only), now via the shared helper in /js/site.js (R2).
window.addEventListener('DOMContentLoaded', function () {
    sjReveal('.testimonial-reveal', 150);
});
</script>
