<?php
// About page body — DB-driven since C2 (hero slides + the four profile blocks).
// Variables from public_html/about.php: $sj_page, $sj_hero_slides, $sj_blocks
// ($sj_blocks = profiles keyed president/principal/history/rules; principal is
// the SAME row the home page renders — one edit updates both pages).
// The timings table + diary download are structural markup and stay in the view.
?>
<!-- top carousel — Stage J: the shared hero partial (shipped id kept) -->
<?php
$sjHero = [
    'id'          => 'aboutHeroCarousel',
    'slides'      => $sj_hero_slides,
    'variant'     => 'hero',
    'preset'      => 'hero_16x7',
    'interval'    => 2000,
    'page_id'     => $sj_page ? (int)$sj_page['id'] : null,
    'keyboardNav' => true,
];
include dirname(__DIR__) . '/partials/hero.php';
?>

<!-- about start -->
<div class="containers">
  <?php foreach (['president', 'principal', 'history', 'rules'] as $roleKey): $b = $sj_blocks[$roleKey] ?? null; if (!$b) continue; ?>
  <div class="about-section sj-reveal">
    <div class="about-image">
      <?= img_tag($b['image'], 'portrait_4x5', ['alt' => $b['heading'], 'extra' => trim(ed_img('profile', $b['id']))]) ?>
    </div>
    <div class="about-content">
      <h3<?= ed_field('profile', $b['id'], 'heading') ?>><?= e($b['heading']) ?></h3>
      <h2 class="ab-1"<?= ed_field('profile', $b['id'], 'person_name') ?>><?= e($b['person_name']) ?></h2>
      <?php ed_rich('profile', $b['id'], 'message_html', $b['message_html']); ?>
      <?php if ($roleKey === 'rules'): ?>
      <h3 class="school-timings-heading">SCHOOL TIMINGS</h3>
      <table class="school-timings">
        <tr><th>Timing</th><th>  Activity</th></tr>
        <tr><td>8.30 AM to 12.00 Noon</td><td> - Instructional Hours</td></tr>
        <tr><td>10 Minutes </td><td>- Interval</td></tr>
        <tr><td>12.00 Noon to 12.30 P.M </td><td>- Lunch Break</td></tr>
        <tr><td>12.30 P.M to 3.20 P.M </td><td>- Instructional Hours</td></tr>
        <tr><td>10 Minutes </td><td>- Interval</td></tr>
      </table>
      <?php /* R3: was <span style><p>…</p></span> — a <p> may not sit inside a
               <span>. The gold color now lives on the <p> itself; the text and
               the link inherit exactly the same computed color as before. */ ?>
      <p class="diary-line">To see more about our rules and regulations, then click on Download --  <a href="/files/diary.pdf" download="SchoolDiary.pdf" class="download-btn">Download</a></p>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- cross-navigation teasers -->
<div class="abt-card-container sj-reveal">
  <section class="section">
    <?php /* F2: /media/static/achbg.jpg is a full-frame recompression of
             /photos/achbg.jpg (541K -> 164K); same image, same framing. */ ?>
    <img src="/media/static/achbg.jpg" alt="Achievements">
    <div class="section-content cont-reveal-1">
      <h2>Achievements</h2>
      <p>Our school has consistently excelled in academics, sports, and extracurricular activities, earning numerous awards and accolades.</p>
      <a href="/achievements.php" class="btn btn-primary">Explore</a>
    </div>
  </section>
  <section class="section">
    <img src="/media/static/schname.jpg" alt="Infrastructure"><?php /* N5: optimized copy of the 5536px scan; same frame */ ?>
    <div class="section-content cont-reveal-1">
      <h2>Infrastructure</h2>
      <p>Our classrooms are aesthetically designed and integrated with smart boards and well-furnished desks with an aim to provide quality education with modern facilities.</p>
      <a href="/infrastructure.php" class="btn btn-primary">Explore</a>
    </div>
  </section>
</div>

