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
    'variant'     => 'banner', // K1: slim page-title strip (owner UX decision)
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
      <?php // K4: rows come from rules_timings (registry entity — editable,
            // orderable, creatable in the panel AND via the site overlay). ?>
      <table class="school-timings">
        <tr><th>Timing</th><th>  Activity</th></tr>
        <tbody<?= ed_add('rules_timing', [], 'Add timings row') ?>>
        <?php foreach ($sj_rules_timings ?? [] as $rt): ?>
        <tr<?= ed_item('rules_timing', $rt['id'], 'Timings row') ?>><td<?= ed_field('rules_timing', $rt['id'], 'timing') ?>><?= e($rt['timing']) ?></td><td<?= ed_field('rules_timing', $rt['id'], 'activity') ?>><?= e($rt['activity']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php // K4: the diary line + download target are settings now. ?>
      <p class="diary-line"><?= e(repo_setting('diary_text', 'To see more about our rules and regulations, then click on Download --')) ?>  <a href="<?= e(repo_setting('diary_url', '/files/diary.pdf')) ?>" download="SchoolDiary.pdf" class="download-btn">Download</a></p>
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

