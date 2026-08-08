<?php
// Per-section editing screens of the standalone admin panel.
require __DIR__ . '/_layout.php';

$s = (string)($_GET['s'] ?? '');
$sections = panel_sections();
if (!isset($sections[$s]) || $s === 'dashboard') {
    header('Location: /admin/');
    exit;
}
[$icon, $label] = $sections[$s];

/** One list row with actions. $opts: entity,id,thumb,title,sub,active(bool|null),canMove */
function panel_row(array $o): void
{
    ?>
    <div class="sj-row<?= isset($o['active']) && !$o['active'] ? ' off' : '' ?>" data-row="<?= e($o['entity']) ?>:<?= (int)$o['id'] ?>">
      <?php if (array_key_exists('thumb', $o)): ?>
        <?php if ($o['thumb']): ?><img class="sj-row-thumb" src="<?= e($o['thumb']) ?>" alt="">
        <?php else: ?><div class="sj-row-thumb noimg">no image</div><?php endif; ?>
      <?php endif; ?>
      <div class="sj-row-main">
        <b><?= e($o['title']) ?></b>
        <?php if (!empty($o['sub'])): ?><span><?= e($o['sub']) ?></span><?php endif; ?>
      </div>
      <?php if (isset($o['active']) && !$o['active']): ?><span class="sj-badge">Hidden</span><?php endif; ?>
      <div class="sj-row-actions">
        <button class="sj-ico" title="Edit" data-act="edit">✏️</button>
        <?php if (!empty($o['canMove'])): ?>
          <button class="sj-ico" title="Move up" data-act="move" data-dir="-1">↑</button>
          <button class="sj-ico" title="Move down" data-act="move" data-dir="1">↓</button>
        <?php endif; ?>
        <?php if (isset($o['active'])): ?>
          <button class="sj-ico" title="<?= $o['active'] ? 'Hide from site' : 'Show on site' ?>" data-act="toggle" data-active="<?= $o['active'] ? 1 : 0 ?>"><?= $o['active'] ? '👁️' : '🚫' ?></button>
        <?php endif; ?>
        <button class="sj-ico danger" title="Delete" data-act="del">🗑️</button>
      </div>
    </div>
    <?php
}

panel_header($s, $icon . ' ' . $label);

switch ($s) {

/* ================= HERO CAROUSEL ================= */
case 'hero':
    $page = repo_page('index');
    $slides = repo_hero_slides((int)$page['id'], true);
    ?>
    <div class="sj-section-head">
      <p class="sj-lead">Slides of the big banner at the top of the Home page. Order here = order on the site.
         Images are auto-cropped to the banner shape (16:7) — use photos at least 1600px wide for best quality.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('hero_slide', ['page_id' => (int)$page['id']], 'Add hero slide') ?>>＋ Add hero slide</button>
    </div>
    <div class="sj-list" data-list="hero_slide">
      <?php foreach ($slides as $sl) {
          panel_row([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'thumb'  => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'title'  => $sl['caption_title'] ?: '(no caption)',
              'sub'    => $sl['caption_text'],
              'active' => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>
    <?php
    break;

/* ================= PRINCIPAL ================= */
case 'principal':
    $p = repo_profile('principal');
    ?>
    <p class="sj-lead">This block appears on the Home page (and the same data powers the About page after
       the full migration). Edit and press <b>Save changes</b>.</p>
    <div class="sj-form-card" id="sj-principal" data-entity="profile" data-id="<?= (int)$p['id'] ?>">
      <div class="sj-form-grid">
        <div class="sj-form-side">
          <label>Portrait photo</label>
          <img id="sj-principal-thumb" class="sj-portrait" src="<?= e($p['image'] ? img_url($p['image'], 'portrait_4x5') : '') ?>" alt="">
          <input type="hidden" id="sj-principal-img" value="<?= (int)($p['image_id'] ?? 0) ?>">
          <button class="sj-btn sj-btn-ghost" data-act="pick-principal-photo" data-preset="portrait_4x5">📷 Change photo</button>
        </div>
        <div class="sj-form-fields">
          <label>Role heading</label>
          <input type="text" id="sj-principal-heading" value="<?= e($p['heading']) ?>">
          <label>Name</label>
          <input type="text" id="sj-principal-name" value="<?= e($p['person_name']) ?>">
          <label>Welcome message</label>
          <div class="sj-richwrap">
            <div class="sj-richbar" data-for="sj-principal-msg"></div>
            <div class="sj-rich" id="sj-principal-msg" contenteditable="true"><?= $p['message_html'] ?></div>
          </div>
          <p class="sj-hint">Write and format the text exactly as it should look on the website — select text and use
             <b>B</b> for bold or <b>Gold</b> for the gold highlight. Formatting is stored properly in the background.</p>
        </div>
      </div>
      <div class="sj-form-foot">
        <button class="sj-btn sj-btn-primary" data-act="save-principal">💾 Save changes</button>
        <span class="sj-savemsg" id="sj-principal-msg-state"></span>
      </div>
    </div>
    <?php
    break;

/* ================= WHAT'S UNIQUE ================= */
case 'unique':
    $rows = repo_unique_features(true);
    ?>
    <div class="sj-section-head">
      <p class="sj-lead">The feature blocks in the "What's Unique?" section of the Home page.
         Blocks alternate image-left / image-right automatically.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('unique_feature', [], 'Add block') ?>>＋ Add block</button>
    </div>
    <div class="sj-list" data-list="unique_feature">
      <?php foreach ($rows as $r) {
          panel_row([
              'entity' => 'unique_feature', 'id' => $r['id'],
              'thumb'  => $r['image'] ? img_url($r['image'], 'feature_4x3') : null,
              'title'  => $r['title'],
              'sub'    => mb_substr(trim(strip_tags($r['body_html'])), 0, 90) . '…',
              'active' => (bool)$r['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>
    <?php
    break;

/* ================= NEWS TICKER ================= */
case 'ticker':
    $rows = repo_ticker(true);
    ?>
    <div class="sj-section-head">
      <p class="sj-lead">The scrolling announcement bar. Each item is a short text that links to a video or page.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('ticker_item', [], 'Add announcement') ?>>＋ Add announcement</button>
    </div>
    <div class="sj-list" data-list="ticker_item">
      <?php foreach ($rows as $r) {
          panel_row([
              'entity' => 'ticker_item', 'id' => $r['id'],
              'title'  => $r['label'],
              'sub'    => $r['url'],
              'active' => (bool)$r['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>
    <?php
    break;

/* ================= NEW UPDATES ================= */
case 'updates':
    $rows = repo_update_slides(true);
    ?>
    <div class="sj-section-head">
      <p class="sj-lead">Slides of the "New Updates" video carousel. Images are auto-cropped to 16:9.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('update_slide', [], 'Add update slide') ?>>＋ Add update slide</button>
    </div>
    <div class="sj-list" data-list="update_slide">
      <?php foreach ($rows as $r) {
          panel_row([
              'entity' => 'update_slide', 'id' => $r['id'],
              'thumb'  => $r['image'] ? img_url($r['image'], 'update_16x9') : null,
              'title'  => $r['title'],
              'sub'    => $r['subtitle'],
              'active' => (bool)$r['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>
    <?php
    break;

/* ================= TOP MARKS ================= */
case 'marks':
    $years = db()->query('SELECT * FROM mark_years ORDER BY year DESC')->fetchAll();
    $entSt = db()->prepare('SELECT * FROM mark_entries WHERE year_id = ? ORDER BY FIELD(standard,"12","11","10"), position, id');
    ?>
    <div class="sj-section-head">
      <p class="sj-lead">Board-exam toppers. The website shows the <b>latest 3 visible years</b>.
         Each year holds 10th / 11th / 12th standard entries.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('mark_year', [], 'Add year') ?>>＋ Add year</button>
    </div>
    <?php foreach ($years as $y):
        $entSt->execute([$y['id']]);
        $entries = $entSt->fetchAll();
    ?>
    <div class="sj-yearcard<?= $y['is_active'] ? '' : ' off' ?>" data-row="mark_year:<?= (int)$y['id'] ?>">
      <div class="sj-yearhead">
        <b>🗓️ <?= e($y['year']) ?></b>
        <?php if (!$y['is_active']): ?><span class="sj-badge">Hidden</span><?php endif; ?>
        <div class="sj-row-actions">
          <button class="sj-btn sj-btn-ghost sj-btn-sm" <?= panel_add_attr('mark_entry', ['year_id' => (int)$y['id']], 'Add topper — ' . $y['year']) ?>>＋ Add topper</button>
          <button class="sj-ico" title="<?= $y['is_active'] ? 'Hide year' : 'Show year' ?>" data-act="toggle" data-active="<?= $y['is_active'] ? 1 : 0 ?>"><?= $y['is_active'] ? '👁️' : '🚫' ?></button>
          <button class="sj-ico danger" title="Delete year (removes all its toppers)" data-act="del" data-confirm="Delete year <?= e($y['year']) ?> and ALL its toppers?">🗑️</button>
        </div>
      </div>
      <?php if ($entries): ?>
      <table class="sj-table">
        <thead><tr><th>Std</th><th>Rank</th><th>Student</th><th>Marks</th><th></th></tr></thead>
        <tbody data-list="mark_entry">
        <?php foreach ($entries as $en): ?>
          <tr data-row="mark_entry:<?= (int)$en['id'] ?>">
            <td><span class="sj-chip"><?= e($en['standard']) ?>th</span></td>
            <td><?= e($en['rank_label']) ?></td>
            <td><b><?= e($en['student_name']) ?></b></td>
            <td><?= (int)$en['marks_scored'] ?> / <?= (int)$en['marks_total'] ?></td>
            <td class="sj-row-actions">
              <button class="sj-ico" title="Edit" data-act="edit">✏️</button>
              <button class="sj-ico" title="Move up" data-act="move" data-dir="-1">↑</button>
              <button class="sj-ico" title="Move down" data-act="move" data-dir="1">↓</button>
              <button class="sj-ico danger" title="Delete" data-act="del">🗑️</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><p class="sj-hint" style="padding:0 18px 16px">No toppers yet — use “＋ Add topper”.</p><?php endif; ?>
    </div>
    <?php endforeach;
    break;

/* ================= MEDIA LIBRARY ================= */
case 'media':
    ?>
    <div class="sj-section-head">
      <p class="sj-lead">Every image available to the website — the original photo collection plus your uploads.
         Uploads are auto-cropped to the shape you pick and compressed automatically.</p>
      <button class="sj-btn sj-btn-primary" id="sj-media-upload">⬆️ Upload image</button>
    </div>
    <div class="sj-media-bar">
      <input type="text" id="sj-media-search" class="sj-search" placeholder="Search images by file name…">
      <label class="sj-check sj-orphan-toggle"><input type="checkbox" id="sj-media-orphans"> Unused only</label>
    </div>
    <div class="sj-grid" id="sj-media-grid"></div>
    <button class="sj-btn sj-btn-ghost sj-more" id="sj-media-more">Load more</button>
    <?php
    break;

/* ================= ABOUT PAGE (C2) ================= */
case 'aboutpage':
    $aboutPage  = repo_page('about');
    $aboutSlides = $aboutPage ? repo_hero_slides((int)$aboutPage['id'], true) : [];
    ?>
    <p class="sj-lead">Everything on the <b>About</b> page: the top photo carousel and the four content
       blocks. The <b>Principal</b> block is shared with the Home page — edit it in its own
       <a href="/admin/section.php?s=principal">Principal</a> section (one edit updates both pages).</p>

    <h3 class="sj-form-legend">Top carousel</h3>
    <div class="sj-section-head">
      <p class="sj-lead">Photos rotating at the top of the About page (auto-cropped to the banner shape).</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('hero_slide', ['page_id' => (int)$aboutPage['id']], 'Add about slide') ?>>＋ Add slide</button>
    </div>
    <div class="sj-list" data-list="hero_slide">
      <?php foreach ($aboutSlides as $sl) {
          panel_row([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'thumb'  => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'title'  => $sl['caption_title'] ?: '(no caption)',
              'sub'    => $sl['caption_text'],
              'active' => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <h3 class="sj-form-legend">Content blocks</h3>
    <div class="sj-list">
      <?php foreach (['president' => 'President', 'history' => 'School History', 'rules' => 'Rules and Regulations'] as $roleKey => $label) {
          $b = repo_profile($roleKey);
          if (!$b) { continue; }
          panel_row([
              'entity' => 'profile', 'id' => $b['id'],
              'thumb'  => $b['image'] ? img_url($b['image'], 'portrait_4x5') : null,
              'title'  => $label,
              'sub'    => $b['person_name'],
          ]);
      } ?>
    </div>
    <p class="sj-hint">The school-timings table and the diary download under "Rules and Regulations"
       are fixed layout — text around them is editable here.</p>
    <?php
    break;

/* ================= STAFFS PAGE (C3) ================= */
case 'staffspage':
    $staffsPage   = repo_page('staffs');
    $staffsSlides = $staffsPage ? repo_hero_slides((int)$staffsPage['id'], true) : [];
    ?>
    <p class="sj-lead">Everything on the <b>Staffs</b> page: the top photo carousel and the three text blocks.</p>

    <h3 class="sj-form-legend">Top carousel</h3>
    <div class="sj-section-head">
      <p class="sj-lead">Photos rotating at the top of the Staffs page.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('hero_slide', ['page_id' => (int)$staffsPage['id']], 'Add staffs slide') ?>>＋ Add slide</button>
    </div>
    <div class="sj-list" data-list="hero_slide">
      <?php foreach ($staffsSlides as $sl) {
          panel_row([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'thumb'  => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'title'  => $sl['caption_title'] ?: '(no caption)',
              'sub'    => $sl['caption_text'],
              'active' => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <h3 class="sj-form-legend">Content blocks</h3>
    <div class="sj-list">
      <?php foreach (['staff_love' => 'We Love our Staffs (top block)', 'staff_team' => 'The Staffs', 'staff_tour' => 'The Staff Tour'] as $roleKey => $label) {
          $b = repo_profile($roleKey);
          if (!$b) { continue; }
          panel_row([
              'entity' => 'profile', 'id' => $b['id'],
              'thumb'  => $b['image'] ? img_url($b['image'], 'feature_4x3') : null,
              'title'  => $label,
              'sub'    => $b['person_name'],
          ]);
      } ?>
    </div>
    <p class="sj-hint">The photos beside the top block are the images of "The Staffs" and
       "The Staff Tour" — change those blocks' images to change all of them.</p>
    <?php
    break;

/* ================= TESTIMONIALS (C3) ================= */
case 'testimonials':
    $rows = repo_testimonials(true);
    ?>
    <div class="sj-section-head">
      <p class="sj-lead">The "Students Testimonial" cards on the Home page. Order here = order on the site;
         the three background styles repeat automatically.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('testimonial', [], 'Add testimonial') ?>>＋ Add testimonial</button>
    </div>
    <div class="sj-list" data-list="testimonial">
      <?php foreach ($rows as $t) {
          panel_row([
              'entity' => 'testimonial', 'id' => $t['id'],
              'title'  => trim(strip_tags($t['name_html'])) ?: '(unnamed)',
              'sub'    => mb_substr(trim(strip_tags($t['body_html'])), 0, 90) . '…',
              'active' => (bool)$t['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>
    <?php
    break;

/* ================= SCHOOL SECTIONS (C4) ================= */
case 'sections':
    $allSections = repo_sections();
    $secSlugs = array_column($allSections, 'slug');
    $cur = (string)($_GET['sec'] ?? 'kg');
    if (!in_array($cur, $secSlugs, true)) { $cur = $secSlugs[0] ?? 'kg'; }
    $S = repo_section($cur);
    $secPage   = repo_page($cur);
    $secSlides = $secPage ? repo_hero_slides((int)$secPage['id'], true) : [];
    ?>
    <div class="sj-tabs">
      <?php foreach ($allSections as $sRow): ?>
      <a class="sj-btn <?= $sRow['slug'] === $cur ? 'sj-btn-primary' : 'sj-btn-ghost' ?>"
         href="/admin/section.php?s=sections&sec=<?= e($sRow['slug']) ?>"><?= e($sRow['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <h3 class="sj-form-legend">Top carousel</h3>
    <div class="sj-section-head">
      <p class="sj-lead">Photos rotating at the top of the <?= e($S['name']) ?> page.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('hero_slide', ['page_id' => (int)$secPage['id']], 'Add slide') ?>>＋ Add slide</button>
    </div>
    <div class="sj-list" data-list="hero_slide">
      <?php foreach ($secSlides as $sl) {
          panel_row([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'thumb'  => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'title'  => $sl['caption_title'] ?: '(no caption)',
              'sub'    => $sl['caption_text'],
              'active' => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <h3 class="sj-form-legend">Intro & grade card</h3>
    <div class="sj-section-head">
      <p class="sj-lead">The intro text beside the photo carousel, plus the grade card shown on the
         Academics page. The carousel photos have their own manager.</p>
      <button class="sj-btn sj-btn-ghost" <?= panel_photos_attr('section', (int)$S['id'], 'carousel', 'content_slide', $S['name'] . ' — carousel photos') ?>>🖼️ Manage carousel photos</button>
    </div>
    <div class="sj-list">
      <?php panel_row([
          'entity' => 'school_section', 'id' => (int)$S['id'],
          'thumb'  => $S['image'] ? img_url($S['image'], 'card_4x3') : null,
          'title'  => $S['intro_heading'],
          'sub'    => 'Intro, timeline label, events heading, grade card',
      ]); ?>
    </div>

    <h3 class="sj-form-legend">Timeline (<?= e($S['timeline_heading']) ?>)</h3>
    <div class="sj-section-head">
      <p class="sj-lead">One row per month; put each event on its own line in the editor.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('timeline_entry', ['section_id' => (int)$S['id']], 'Add timeline month') ?>>＋ Add month</button>
    </div>
    <div class="sj-list" data-list="timeline_entry">
      <?php foreach (repo_timeline((int)$S['id']) as $t) {
          panel_row([
              'entity' => 'timeline_entry', 'id' => $t['id'],
              'title'  => $t['month_label'],
              'sub'    => mb_substr(str_replace("\n", ' · ', $t['events_text']), 0, 90),
              'canMove' => true,
          ]);
      } ?>
    </div>

    <h3 class="sj-form-legend">Event blocks</h3>
    <div class="sj-section-head">
      <p class="sj-lead">The photo + text blocks at the bottom of the page (photos alternate left/right automatically).</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('section_event', ['section_id' => (int)$S['id']], 'Add event block') ?>>＋ Add event</button>
    </div>
    <div class="sj-list" data-list="section_event">
      <?php foreach (repo_section_events((int)$S['id']) as $ev) {
          panel_row([
              'entity' => 'section_event', 'id' => $ev['id'],
              'thumb'  => $ev['image'] ? img_url($ev['image'], 'feature_4x3') : null,
              'title'  => $ev['title'],
              'sub'    => mb_substr(trim(strip_tags($ev['body_html'])), 0, 90),
              'canMove' => true,
          ]);
      } ?>
    </div>
    <?php
    break;

/* ================= ACADEMIES (C5) ================= */
case 'academies':
    $rows = repo_academies(true);
    ?>
    <div class="sj-section-head">
      <p class="sj-lead">The 18 academy pages (Tamil, Maths, … Band, NCC, Art&nbsp;Expo) and their cards on the
         Co-Curriculum page. Order here = card order on that page. Each academy's photo carousel has its
         own manager (🖼️). Adding a brand-new academy needs a developer (its page needs a URL).</p>
    </div>
    <div class="sj-list" data-list="academy">
      <?php foreach ($rows as $ac) { ?>
        <div class="sj-row<?= $ac['is_active'] ? '' : ' off' ?>" data-row="academy:<?= (int)$ac['id'] ?>">
          <?php if ($ac['image']): ?><img class="sj-row-thumb" src="<?= e(img_url($ac['image'], 'card_4x3')) ?>" alt="">
          <?php else: ?><div class="sj-row-thumb noimg">no image</div><?php endif; ?>
          <div class="sj-row-main">
            <b><?= e($ac['card_title']) ?></b>
            <span><?= e($ac['card_subtitle']) ?> · /<?= e($ac['slug']) ?>.php</span>
          </div>
          <?php if (!$ac['is_active']): ?><span class="sj-badge">Hidden</span><?php endif; ?>
          <div class="sj-row-actions">
            <button class="sj-ico" title="Carousel photos" <?= panel_photos_attr('academy', (int)$ac['id'], 'carousel', 'content_slide', $ac['card_title'] . ' — carousel photos') ?>>🖼️</button>
            <button class="sj-ico" title="Edit" data-act="edit">✏️</button>
            <button class="sj-ico" title="Move up" data-act="move" data-dir="-1">↑</button>
            <button class="sj-ico" title="Move down" data-act="move" data-dir="1">↓</button>
            <button class="sj-ico" title="<?= $ac['is_active'] ? 'Hide from co-curriculum grid' : 'Show on co-curriculum grid' ?>" data-act="toggle" data-active="<?= $ac['is_active'] ? 1 : 0 ?>"><?= $ac['is_active'] ? '👁️' : '🚫' ?></button>
          </div>
        </div>
      <?php } ?>
    </div>
    <?php
    break;

/* ================= SPORTS (C6) ================= */
case 'sports':
    $sportsPage = repo_page('sports');
    $rows = repo_sports(true);
    ?>
    <h3 class="sj-form-legend">Top carousel</h3>
    <div class="sj-section-head">
      <p class="sj-lead">Photos rotating at the top of the Sports page.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('hero_slide', ['page_id' => (int)$sportsPage['id']], 'Add sports slide') ?>>＋ Add slide</button>
    </div>
    <div class="sj-list" data-list="hero_slide">
      <?php foreach (repo_hero_slides((int)$sportsPage['id'], true) as $sl) {
          panel_row([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'thumb'  => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'title'  => $sl['caption_title'] ?: '(no caption)',
              'sub'    => $sl['caption_text'],
              'active' => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <h3 class="sj-form-legend">Sport cards</h3>
    <div class="sj-section-head">
      <p class="sj-lead">The nine sport cards with their "Read More" panels. Order here = order on the site.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('sport', [], 'Add sport') ?>>＋ Add sport</button>
    </div>
    <div class="sj-list" data-list="sport">
      <?php foreach ($rows as $S) {
          panel_row([
              'entity' => 'sport', 'id' => $S['id'],
              'thumb'  => $S['image'] ? img_url($S['image'], 'card_4x3') : null,
              'title'  => $S['name'],
              'sub'    => $S['training_time'],
              'active' => (bool)$S['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>
    <?php
    break;

/* ================= INFRASTRUCTURE (C7) ================= */
case 'facilities':
    $infraPage = repo_page('infrastructure');
    ?>
    <h3 class="sj-form-legend">Top carousel</h3>
    <div class="sj-section-head">
      <p class="sj-lead">Photos rotating at the top of the Infrastructure page.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('hero_slide', ['page_id' => (int)$infraPage['id']], 'Add slide') ?>>＋ Add slide</button>
    </div>
    <div class="sj-list" data-list="hero_slide">
      <?php foreach (repo_hero_slides((int)$infraPage['id'], true) as $sl) {
          panel_row([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'thumb'  => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'title'  => $sl['caption_title'] ?: '(no caption)',
              'sub'    => $sl['caption_text'],
              'active' => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <h3 class="sj-form-legend">Facility showcases</h3>
    <div class="sj-section-head">
      <p class="sj-lead">The 15 facility sections. Order here = order (and quick-jump buttons) on the page.
         Each facility's photo carousel has its own manager (🖼️).</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('facility', [], 'Add facility') ?>>＋ Add facility</button>
    </div>
    <div class="sj-list" data-list="facility">
      <?php foreach (repo_facilities(true) as $F) { ?>
        <div class="sj-row<?= $F['is_active'] ? '' : ' off' ?>" data-row="facility:<?= (int)$F['id'] ?>">
          <?php if ($F['image']): ?><img class="sj-row-thumb" src="<?= e(img_url($F['image'], 'bg_wide')) ?>" alt="">
          <?php else: ?><div class="sj-row-thumb noimg">no image</div><?php endif; ?>
          <div class="sj-row-main">
            <b><?= e($F['name']) ?></b>
            <span><?= count($F['carousel']) ?> carousel photos</span>
          </div>
          <?php if (!$F['is_active']): ?><span class="sj-badge">Hidden</span><?php endif; ?>
          <div class="sj-row-actions">
            <button class="sj-ico" title="Carousel photos" <?= panel_photos_attr('facility', (int)$F['id'], 'carousel', 'content_slide', $F['name'] . ' — carousel photos') ?>>🖼️</button>
            <button class="sj-ico" title="Edit" data-act="edit">✏️</button>
            <button class="sj-ico" title="Move up" data-act="move" data-dir="-1">↑</button>
            <button class="sj-ico" title="Move down" data-act="move" data-dir="1">↓</button>
            <button class="sj-ico" title="<?= $F['is_active'] ? 'Hide from site' : 'Show on site' ?>" data-act="toggle" data-active="<?= $F['is_active'] ? 1 : 0 ?>"><?= $F['is_active'] ? '👁️' : '🚫' ?></button>
            <button class="sj-ico danger" title="Delete" data-act="del">🗑️</button>
          </div>
        </div>
      <?php } ?>
    </div>
    <?php
    break;

/* ================= ACHIEVEMENTS (C8) ================= */
case 'achievements':
    $achPage = repo_page('achievements');
    ?>
    <h3 class="sj-form-legend">Top carousel</h3>
    <div class="sj-section-head">
      <p class="sj-lead">Photos rotating at the top of the Achievements page.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('hero_slide', ['page_id' => (int)$achPage['id']], 'Add slide') ?>>＋ Add slide</button>
    </div>
    <div class="sj-list" data-list="hero_slide">
      <?php foreach (repo_hero_slides((int)$achPage['id'], true) as $sl) {
          panel_row([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'thumb'  => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'title'  => $sl['caption_title'] ?: '(no caption)',
              'sub'    => $sl['caption_text'],
              'active' => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <?php foreach (['achievement' => 'Achievements list', 'award' => 'Awards list ("given by St.Joseph\'s")'] as $atype => $alabel): ?>
    <h3 class="sj-form-legend"><?= e($alabel) ?></h3>
    <div class="sj-section-head">
      <p class="sj-lead">Photos alternate left/right automatically (zig-zag).</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('achievement', ['type' => $atype], 'Add to ' . $alabel) ?>>＋ Add</button>
    </div>
    <div class="sj-list" data-list="achievement">
      <?php foreach (repo_achievements($atype, true) as $A) {
          panel_row([
              'entity' => 'achievement', 'id' => $A['id'],
              'thumb'  => $A['image'] ? img_url($A['image'], 'feature_4x3') : null,
              'title'  => $A['title'],
              'sub'    => $A['subtext'],
              'active' => (bool)$A['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>
    <?php endforeach; ?>
    <?php
    break;

/* ================= SITE SETTINGS (C1) ================= */
case 'settings':
    // Whitelisted keys only — mirrors admin/api/settings.php. `settings` is
    // NOT a registry entity (CLAUDE.md); this screen + its endpoint are the
    // only write path. Groups: [legend => [key => [label, hint]]].
    $groups = [
        'Contact details (footer + contact section)' => [
            'contact_email'         => ['School email', 'Shown on the contact section and the footer; also used as the mailto: link.'],
            'contact_phone'         => ['Phone number', 'e.g. 0422-2271367'],
            'contact_address_line1' => ['Address — line 1', ''],
            'contact_address_line2' => ['Address — line 2', ''],
        ],
        'School timings (footer)' => [
            'timing_morning'   => ['Morning', ''],
            'timing_lunch'     => ['Lunch', ''],
            'timing_afternoon' => ['Afternoon', ''],
        ],
        'Admissions band (bottom of most pages)' => [
            'jumbotron_heading' => ['Heading', ''],
            'jumbotron_sub'     => ['Sub-line', ''],
            'jumbotron_btn'     => ['Button label', ''],
        ],
        'Social & misc' => [
            'facebook_url'      => ['Facebook URL', 'Must start with https://'],
            'youtube_url'       => ['YouTube URL', 'Must start with https://'],
            'footer_copyright'  => ['Footer copyright line', ''],
            'marks_years_shown' => ['Top-Marks years shown', 'How many recent years the home/highsec toppers board shows (1–10).'],
        ],
    ];
    ?>
    <p class="sj-lead">Site-wide text used across pages (contact info, school timings, the admissions
       band, social links). Edit and press <b>Save settings</b> — the site updates immediately.</p>
    <form class="sj-form-card" id="sj-settings" data-api="settings">
      <?php foreach ($groups as $legend => $keys): ?>
        <h3 class="sj-form-legend"><?= e($legend) ?></h3>
        <div class="sj-form-grid2">
        <?php foreach ($keys as $key => [$label, $hint]): ?>
          <label class="sj-field">
            <span><?= e($label) ?></span>
            <input type="text" name="<?= e($key) ?>" value="<?= e(repo_setting($key, '')) ?>">
            <?php if ($hint): ?><small><?= e($hint) ?></small><?php endif; ?>
          </label>
        <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <div class="sj-form-actions">
        <button type="submit" class="sj-btn sj-btn-primary">💾 Save settings</button>
        <span class="sj-save-note" id="sj-settings-note"></span>
      </div>
    </form>
    <script src="/admin/assets/settings.js?v=<?= SJ_ASSET_VER ?>" defer></script>
    <?php
    break;
}

panel_footer();
