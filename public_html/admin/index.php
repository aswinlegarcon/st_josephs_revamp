<?php
require __DIR__ . '/_layout.php';

$cards = [
    ['hero',      '🎠', 'Hero Carousel',  'The big rotating banner at the top of the Home page.',
        (int)db()->query('SELECT COUNT(*) FROM hero_slides')->fetchColumn() . ' slides'],
    ['principal', '👤', 'Principal',      'Photo, name and welcome message shown on Home & About.',
        '1 profile'],
    ['aboutpage', '📖', 'About Page',     'Top carousel + President, History and Rules blocks.',
        (int)db()->query('SELECT COUNT(*) FROM profiles')->fetchColumn() . ' blocks'],
    ['staffspage', '🧑‍🏫', 'Staffs Page',  'Top carousel + the three staff text blocks.',
        '3 blocks'],
    ['testimonials', '💬', 'Testimonials', 'The student testimonial cards on the Home page.',
        (int)db()->query('SELECT COUNT(*) FROM testimonials')->fetchColumn() . ' cards'],
    ['sections',  '🏫', 'School Sections', 'KG, Primary, High School & Higher Secondary pages — intros, timelines, events.',
        (int)db()->query('SELECT COUNT(*) FROM timeline_entries')->fetchColumn() . ' timeline months'],
    ['academies', '🎓', 'Academies', 'The 18 academy pages + their co-curriculum cards.',
        (int)db()->query('SELECT COUNT(*) FROM academies')->fetchColumn() . ' academies'],
    ['sports',    '🏅', 'Sports', 'The Sports page — hero photos and the nine sport cards.',
        (int)db()->query('SELECT COUNT(*) FROM sports')->fetchColumn() . ' sports'],
    ['unique',    '✨', "What's Unique",  'The ESC / Language Academies feature blocks.',
        (int)db()->query('SELECT COUNT(*) FROM unique_features')->fetchColumn() . ' blocks'],
    ['ticker',    '📣', 'News Ticker',    'The scrolling announcement bar with links.',
        (int)db()->query('SELECT COUNT(*) FROM ticker_items')->fetchColumn() . ' items'],
    ['updates',   '📺', 'New Updates',    'The video-highlights carousel (EXPRESSIONZ, KG events…).',
        (int)db()->query('SELECT COUNT(*) FROM update_slides')->fetchColumn() . ' slides'],
    ['marks',     '🏆', 'Top Marks',      'Board-exam toppers, by year and standard (10th/11th/12th).',
        (int)db()->query('SELECT COUNT(*) FROM mark_years')->fetchColumn() . ' years · ' .
        (int)db()->query('SELECT COUNT(*) FROM mark_entries')->fetchColumn() . ' toppers'],
    ['media',     '🖼️', 'Media Library',  'All site images — browse, search and upload.',
        (int)db()->query('SELECT COUNT(*) FROM images')->fetchColumn() . ' images'],
    ['settings',  '⚙️', 'Site Settings',  'Contact details, school timings, admissions band, social links.',
        (int)db()->query('SELECT COUNT(*) FROM settings')->fetchColumn() . ' settings'],
];

panel_header('dashboard', 'Dashboard');
?>
<p class="sj-lead">Welcome! Pick a section to edit the Home page content. Changes are saved to the
database immediately — visitors see them as soon as they refresh the site.</p>

<div class="sj-cards">
  <?php foreach ($cards as [$slug, $icon, $title, $desc, $count]): ?>
  <a class="sj-card" href="/admin/section.php?s=<?= e($slug) ?>">
    <div class="sj-card-icon"><?= $icon ?></div>
    <div class="sj-card-body">
      <b><?= e($title) ?></b>
      <p><?= e($desc) ?></p>
      <span class="sj-chip"><?= e($count) ?></span>
    </div>
    <div class="sj-card-go">›</div>
  </a>
  <?php endforeach; ?>
</div>

<div class="sj-note">
  💡 <b>Tip:</b> image uploads are automatically cropped to the exact shape each slot needs and
  lightly compressed (JPEG + WebP), so slides and cards always stay uniform and fast.
</div>
<?php panel_footer(); ?>
