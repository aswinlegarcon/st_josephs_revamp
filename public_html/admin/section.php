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
        <button class="sj-ico" title="Edit" aria-label="Edit" data-act="edit"><?= sj_icon('edit', 16) ?></button>
        <?php if (!empty($o['canMove'])): ?>
          <button class="sj-ico" title="Move up" aria-label="Move up" data-act="move" data-dir="-1"><?= sj_icon('up', 16) ?></button>
          <button class="sj-ico" title="Move down" aria-label="Move down" data-act="move" data-dir="1"><?= sj_icon('down', 16) ?></button>
        <?php endif; ?>
        <?php if (isset($o['active'])): ?>
          <button class="sj-ico" title="<?= $o['active'] ? 'Hide from site' : 'Show on site' ?>" aria-label="<?= $o['active'] ? 'Hide from site' : 'Show on site' ?>" data-act="toggle" data-active="<?= $o['active'] ? 1 : 0 ?>"><?= sj_icon($o['active'] ? 'eye' : 'eye-off', 16) ?></button>
        <?php endif; ?>
        <?php if ($o['canDelete'] ?? true): ?>
        <button class="sj-ico danger" title="Delete" aria-label="Delete" data-act="del"><?= sj_icon('trash', 16) ?></button>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

/**
 * N8: branded page header band. $stats = [[count, label], …] chips;
 * $actionsHtml = pre-rendered buttons (built from code literals + escaped attrs).
 */
function panel_page_head(string $icon, string $title, string $desc, array $stats = [], string $actionsHtml = ''): void
{
    ?>
    <div class="sj-page-head">
      <div class="sj-page-head-icon"><?= sj_icon($icon, 24) ?></div>
      <div>
        <div class="sj-page-title"><?= e($title) ?></div>
        <?php if ($desc !== ''): ?><div class="sj-page-desc"><?= e($desc) ?></div><?php endif; ?>
        <?php if ($stats): ?>
        <div class="sj-page-stats">
          <?php foreach ($stats as [$n, $lab]): ?>
          <span class="sj-stat"><b><?= e($n) ?></b> <?= e($lab) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($actionsHtml !== ''): ?><div class="sj-page-head-actions"><?= $actionsHtml ?></div><?php endif; ?>
    </div>
    <?php
}

/**
 * N8: live-preview editor card — the visual sibling of panel_row(). SAME
 * data contract (data-row on the card, identical data-act buttons, direct
 * child of the data-list container) so panel.js edit/move/toggle/del and
 * drag-reorder work untouched. $o keys: entity,id,active,canMove,canDelete
 * (like panel_row) + variant('hero'|'tm'|'card'), aspect('16x9'|'4x3'|'4x5'),
 * img(url|null), cap_title/cap_text (hero), tm_name_html/tm_body_html
 * (testimonial — PRE-SANITIZED _html columns, echoed raw exactly like the
 * public partial), title/sub (foot line), extraActionsHtml (photo-manager
 * buttons / delete guards, pre-rendered).
 */
function panel_preview_card(array $o): void
{
    $variant = $o['variant'] ?? 'card';
    $aspect  = $o['aspect'] ?? '16x9';
    ?>
    <div class="sj-row sj-prev sj-prev--<?= e($variant) ?><?= isset($o['active']) && !$o['active'] ? ' off' : '' ?>" data-row="<?= e($o['entity']) ?>:<?= (int)$o['id'] ?>">
      <figure class="sj-prev-media sj-prev-<?= e($aspect) ?>">
        <?php if (!empty($o['img'])): ?><img src="<?= e($o['img']) ?>" alt="" loading="lazy">
        <?php elseif ($variant !== 'tm'): ?><span class="sj-prev-noimg">no image yet</span><?php endif; ?>
        <?php if ($variant === 'hero' || $variant === 'tm'): ?><span class="sj-prev-veil"></span><?php endif; ?>
        <?php if ($variant === 'hero'): ?>
        <figcaption class="sj-prev-cap">
          <span class="sj-prev-cap-title"><?= e($o['cap_title'] ?? '') ?></span>
          <?php if (($o['cap_text'] ?? '') !== ''): ?><span class="sj-prev-cap-text"><?= e($o['cap_text']) ?></span><?php endif; ?>
        </figcaption>
        <?php elseif ($variant === 'tm'): ?>
        <img class="sj-prev-quote" src="/photos/quote.png" alt="">
        <div class="sj-prev-tm">
          <h4 class="sj-prev-tmname"><?= $o['tm_name_html'] ?? '' ?></h4>
          <div class="sj-prev-tmbody"><?= $o['tm_body_html'] ?? '' ?></div>
        </div>
        <?php endif; ?>
        <?php if (isset($o['active']) && !$o['active']): ?><span class="sj-badge">Hidden</span><?php endif; ?>
      </figure>
      <div class="sj-prev-foot">
        <div class="sj-row-main">
          <b><?= e($o['title'] ?? '') ?></b>
          <?php if (($o['sub'] ?? '') !== ''): ?><span><?= e($o['sub']) ?></span><?php endif; ?>
        </div>
        <div class="sj-row-actions">
          <?= $o['extraActionsHtml'] ?? '' ?>
          <button class="sj-ico" title="Edit" aria-label="Edit" data-act="edit"><?= sj_icon('edit', 16) ?></button>
          <?php if (!empty($o['canMove'])): ?>
            <button class="sj-ico" title="Move up" aria-label="Move up" data-act="move" data-dir="-1"><?= sj_icon('up', 16) ?></button>
            <button class="sj-ico" title="Move down" aria-label="Move down" data-act="move" data-dir="1"><?= sj_icon('down', 16) ?></button>
          <?php endif; ?>
          <?php if (isset($o['active'])): ?>
            <button class="sj-ico" title="<?= $o['active'] ? 'Hide from site' : 'Show on site' ?>" aria-label="<?= $o['active'] ? 'Hide from site' : 'Show on site' ?>" data-act="toggle" data-active="<?= $o['active'] ? 1 : 0 ?>"><?= sj_icon($o['active'] ? 'eye' : 'eye-off', 16) ?></button>
          <?php endif; ?>
          <?php if ($o['canDelete'] ?? true): ?>
          <button class="sj-ico danger" title="Delete" aria-label="Delete" data-act="del"><?= sj_icon('trash', 16) ?></button>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php
}

/** N8: dashed empty state with a Dancing Script line. */
function panel_empty(string $msg, string $icon = 'image'): void
{
    ?>
    <div class="sj-empty"><?= sj_icon($icon, 34) ?><i>Nothing here yet</i><?= e($msg) ?></div>
    <?php
}

panel_header($s, $label); // N7: $icon is an sj_icon() name now, not a printable prefix

switch ($s) {

/* ================= HERO CAROUSEL ================= */
case 'hero':
    $page = repo_page('index');
    $slides = repo_hero_slides((int)$page['id'], true);
    $live = count(array_filter($slides, static fn ($x) => !empty($x['is_active'])));
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('hero_slide', ['page_id' => (int)$page['id']], 'Add hero slide', null, $slides ? end($slides) : null) ?>><?= sj_icon('plus', 15) ?> Add hero slide</button>
    <?php
    panel_page_head('image', 'Hero Carousel',
        'Each card below is a live miniature of its slide — the caption sits exactly where visitors see it. '
        . 'Drag cards (or use the arrows) to reorder; photos are auto-cropped to the banner shape (16:9), so use images at least 1600px wide.',
        [[count($slides), 'slides'], [$live, 'live on the site']], ob_get_clean());
    ?>
    <div class="sj-list sj-cardgrid" data-list="hero_slide">
      <?php foreach ($slides as $sl) {
          panel_preview_card([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'variant' => 'hero', 'aspect' => '16x9',
              'img'       => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'cap_title' => $sl['caption_title'],
              'cap_text'  => $sl['caption_text'],
              'title'     => $sl['caption_title'] ?: '(no caption)',
              'sub'       => $sl['button_label'] ? 'Button: ' . $sl['button_label'] : '',
              'active'    => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      }
      if (!$slides) { panel_empty('Add the first slide of the home banner.'); } ?>
    </div>
    <?php
    break;

/* ================= PRINCIPAL ================= */
case 'principal':
    $p = repo_profile('principal');
    ?>
    <?php panel_page_head('user', 'Principal',
        'This block appears on the Home page and powers the About page too — one edit updates both. Edit below and press Save changes.',
        [[1, 'shared profile']]); ?>
    <div class="sj-form-card" id="sj-principal" data-entity="profile" data-id="<?= (int)$p['id'] ?>">
      <div class="sj-form-grid">
        <div class="sj-form-side">
          <label>Portrait photo</label>
          <img id="sj-principal-thumb" class="sj-portrait" src="<?= e($p['image'] ? img_url($p['image'], 'portrait_4x5') : '') ?>" alt="">
          <input type="hidden" id="sj-principal-img" value="<?= (int)($p['image_id'] ?? 0) ?>">
          <button class="sj-btn sj-btn-ghost" data-act="pick-principal-photo" data-preset="portrait_4x5"><?= sj_icon('camera', 15) ?> Change photo</button>
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
        <button class="sj-btn sj-btn-primary" data-act="save-principal"><?= sj_icon('check', 15) ?> Save changes</button>
        <span class="sj-savemsg" id="sj-principal-msg-state"></span>
      </div>
    </div>
    <?php
    break;

/* ================= WHAT'S UNIQUE ================= */
case 'unique':
    $rows = repo_unique_features(true);
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('unique_feature', [], 'Add block') ?>><?= sj_icon('plus', 15) ?> Add block</button>
    <?php
    panel_page_head('star', "What's Unique", 'The feature blocks in the "What\'s Unique?" section of the Home page. Blocks alternate image-left / image-right automatically.',
        [[count($rows), 'blocks']], ob_get_clean());
    ?>
    <div class="sj-list sj-cardgrid" data-list="unique_feature">
      <?php foreach ($rows as $r) {
          panel_preview_card([
              'entity' => 'unique_feature', 'id' => $r['id'],
              'variant' => 'card', 'aspect' => '4x3',
              'img'    => $r['image'] ? img_url($r['image'], 'feature_4x3') : null,
              'title'  => $r['title'],
              'sub'    => mb_substr(trim(strip_tags($r['body_html'])), 0, 90) . '…',
              'active' => (bool)$r['is_active'], 'canMove' => true,
          ]);
      }
      if (!$rows) { panel_empty('Add the first feature block.', 'star'); } ?>
    </div>
    <?php
    break;

/* ================= NEWS TICKER ================= */
case 'ticker':
    $rows = repo_ticker(true);
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('ticker_item', [], 'Add announcement') ?>><?= sj_icon('plus', 15) ?> Add announcement</button>
    <?php
    panel_page_head('bell', 'News Ticker', 'The scrolling announcement bar. Each item is a short text that links to a video or page.',
        [[count($rows), 'items'], [count(array_filter($rows, static fn ($x) => !empty($x['is_active']))), 'live']], ob_get_clean());
    ?>
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
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('update_slide', [], 'Add update slide', null, $rows ? end($rows) : null) ?>><?= sj_icon('plus', 15) ?> Add update slide</button>
    <?php
    panel_page_head('monitor', 'New Updates', 'Slides of the "New Updates" video carousel on the Home page. Images are auto-cropped to 16:9 — each card below previews its slide.',
        [[count($rows), 'slides']], ob_get_clean());
    ?>
    <div class="sj-list sj-cardgrid" data-list="update_slide">
      <?php foreach ($rows as $r) {
          panel_preview_card([
              'entity' => 'update_slide', 'id' => $r['id'],
              'variant' => 'card', 'aspect' => '16x9',
              'img'    => $r['image'] ? img_url($r['image'], 'update_16x9') : null,
              'title'  => $r['title'],
              'sub'    => $r['subtitle'],
              'active' => (bool)$r['is_active'], 'canMove' => true,
          ]);
      }
      if (!$rows) { panel_empty('Add the first update slide.', 'monitor'); } ?>
    </div>
    <?php
    break;

/* ================= TOP MARKS ================= */
case 'marks':
    $years = db()->query('SELECT * FROM mark_years ORDER BY year DESC')->fetchAll();
    $entSt = db()->prepare('SELECT * FROM mark_entries WHERE year_id = ? ORDER BY FIELD(standard,"12","11","10"), position, id');
    ?>
    <?php ob_start(); ?>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('mark_year', [], 'Add year') ?>><?= sj_icon('plus', 15) ?> Add year</button>
    <?php
    panel_page_head('chart', 'Top Marks', 'Board-exam toppers. The website shows the latest 3 visible years; each year holds 10th / 11th / 12th standard entries.',
        [[count($years), 'years']], ob_get_clean());
    ?>
    <?php foreach ($years as $y):
        $entSt->execute([$y['id']]);
        $entries = $entSt->fetchAll();
    ?>
    <div class="sj-yearcard<?= $y['is_active'] ? '' : ' off' ?>" data-row="mark_year:<?= (int)$y['id'] ?>">
      <div class="sj-yearhead">
        <b><?= sj_icon('calendar', 16) ?> <?= e($y['year']) ?></b>
        <?php if (!$y['is_active']): ?><span class="sj-badge">Hidden</span><?php endif; ?>
        <div class="sj-row-actions">
          <button class="sj-btn sj-btn-ghost sj-btn-sm" <?= panel_add_attr('mark_entry', ['year_id' => (int)$y['id']], 'Add topper — ' . $y['year'], null, $entries ? end($entries) : null) ?>><?= sj_icon('plus', 15) ?> Add topper</button>
          <button class="sj-ico" title="<?= $y['is_active'] ? 'Hide year' : 'Show year' ?>" data-act="toggle" data-active="<?= $y['is_active'] ? 1 : 0 ?>"><?= $y['is_active'] ? sj_icon('eye', 16) : sj_icon('eye-off', 16) ?></button>
          <button class="sj-ico danger" title="Delete year (removes all its toppers)" data-act="del" data-confirm="Delete year <?= e($y['year']) ?> and ALL its toppers?"><?= sj_icon('trash', 16) ?></button>
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
              <button class="sj-ico" title="Edit" data-act="edit" aria-label="Edit"><?= sj_icon('edit', 16) ?></button>
              <button class="sj-ico" title="Move up" data-act="move" data-dir="-1" aria-label="Move up"><?= sj_icon('up', 16) ?></button>
              <button class="sj-ico" title="Move down" data-act="move" data-dir="1" aria-label="Move down"><?= sj_icon('down', 16) ?></button>
              <button class="sj-ico danger" title="Delete" data-act="del"><?= sj_icon('trash', 16) ?></button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><p class="sj-hint" style="padding:0 18px 16px">No toppers yet — use “Add topper”.</p><?php endif; ?>
    </div>
    <?php endforeach;
    break;

/* ================= MEDIA LIBRARY ================= */
case 'media':
    ?>
    <?php ob_start(); ?>
      <button class="sj-btn sj-btn-primary" id="sj-media-upload"><?= sj_icon('upload', 15) ?> Upload image</button>
    <?php
    panel_page_head('folder', 'Media Library',
        'Every image available to the website — the original photo collection plus your uploads. Uploads are auto-cropped to the shape you pick and compressed automatically.',
        [[(int)db()->query('SELECT COUNT(*) FROM images')->fetchColumn(), 'images']], ob_get_clean());
    ?>
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
    <?php
    panel_page_head('book', 'About Page',
        'Everything on the About page: the top photo carousel and the content blocks. The Principal block is shared with the Home page — edit it in its own Principal section (one edit updates both pages).',
        [[count($aboutSlides), 'banner slides'], [3, 'content blocks']]);
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('hero_slide', ['page_id' => (int)$aboutPage['id']], 'Add about slide') ?>><?= sj_icon('plus', 14) ?> Add slide</button>
    <?php $addBtn = ob_get_clean(); ?>
    <h3 class="sj-form-legend">Top carousel <?= $addBtn ?></h3>
    <div class="sj-list sj-cardgrid" data-list="hero_slide">
      <?php foreach ($aboutSlides as $sl) {
          panel_preview_card([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'variant' => 'hero', 'aspect' => '16x9',
              'img'       => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'cap_title' => $sl['caption_title'], 'cap_text' => $sl['caption_text'],
              'title'     => $sl['caption_title'] ?: '(no caption)',
              'active'    => (bool)$sl['is_active'], 'canMove' => true,
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
    <?php ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('rules_timing', [], 'Add timings row', null, ($rtRows = repo_rules_timings()) ? end($rtRows) : null) ?>><?= sj_icon('plus', 14) ?> Add row</button>
    <?php $addBtn = ob_get_clean(); ?>
    <h3 class="sj-form-legend">School timings (Rules block) <?= $addBtn ?></h3>
    <div class="sj-list" data-list="rules_timing">
      <?php foreach (repo_rules_timings() as $rt) {
          panel_row([
              'entity' => 'rules_timing', 'id' => $rt['id'],
              'title'  => $rt['timing'],
              'sub'    => $rt['activity'],
              'canMove' => true,
          ]);
      } ?>
    </div>
    <p class="sj-hint">The diary line and its download link live in Site Settings → "About page — rules &amp; diary".</p>
    <?php
    break;

/* ================= STAFFS PAGE (C3) ================= */
case 'staffspage':
    $staffsPage   = repo_page('staffs');
    $staffsSlides = $staffsPage ? repo_hero_slides((int)$staffsPage['id'], true) : [];
    ?>
    <?php
    panel_page_head('users', 'Staffs Page',
        'Everything on the Staffs page: the top photo carousel and the three text blocks.',
        [[count($staffsSlides), 'banner slides'], [3, 'content blocks']]);
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('hero_slide', ['page_id' => (int)$staffsPage['id']], 'Add staffs slide') ?>><?= sj_icon('plus', 14) ?> Add slide</button>
    <?php $addBtn = ob_get_clean(); ?>
    <h3 class="sj-form-legend">Top carousel <?= $addBtn ?></h3>
    <div class="sj-list sj-cardgrid" data-list="hero_slide">
      <?php foreach ($staffsSlides as $sl) {
          panel_preview_card([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'variant' => 'hero', 'aspect' => '16x9',
              'img'       => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'cap_title' => $sl['caption_title'], 'cap_text' => $sl['caption_text'],
              'title'     => $sl['caption_title'] ?: '(no caption)',
              'active'    => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <h3 class="sj-form-legend">Top block</h3>
    <div class="sj-list">
      <?php $b = repo_profile('staff_love');
      if ($b) {
          panel_row([
              'entity' => 'profile', 'id' => $b['id'],
              'thumb'  => $b['image'] ? img_url($b['image'], 'feature_4x3') : null,
              'title'  => 'We Love our Staffs (top block)',
              'sub'    => $b['person_name'],
          ]);
      } ?>
    </div>

    <?php // K7: the photo+text blocks are a creatable list now — new cards
          // alternate image left/right automatically on the site.
    $staffBlocks = repo_staff_blocks(true);
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('staff_block', [], 'Add staff block', null, $staffBlocks ? end($staffBlocks) : null) ?>><?= sj_icon('plus', 14) ?> Add block</button>
    <?php $addBlk = ob_get_clean(); ?>
    <h3 class="sj-form-legend">Staff blocks <?= $addBlk ?></h3>
    <div class="sj-list sj-cardgrid" data-list="staff_block">
      <?php foreach ($staffBlocks as $B) {
          panel_preview_card([
              'entity' => 'staff_block', 'id' => $B['id'],
              'variant' => 'card', 'aspect' => '4x3',
              'img'    => $B['image'] ? img_url($B['image'], 'feature_4x3') : null,
              'title'  => $B['title'],
              'sub'    => mb_substr(trim(strip_tags($B['body_html'])), 0, 90) . '…',
              'active' => (bool)$B['is_active'], 'canMove' => true,
          ]);
      }
      if (!$staffBlocks) { panel_empty('Add the first staff block.', 'users'); } ?>
    </div>
    <p class="sj-hint">Blocks alternate photo-left / photo-right automatically. The two photos
       beside the top block are the first two blocks' photos.</p>
    <?php
    break;

/* ================= TESTIMONIALS (C3) ================= */
case 'testimonials':
    $rows = repo_testimonials(true);
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('testimonial', [], 'Add testimonial') ?>><?= sj_icon('plus', 15) ?> Add testimonial</button>
    <?php
    panel_page_head('quote', 'Testimonials',
        'Each card below is rendered exactly like the site — navy veil, gold name, white rule. The site shows them as a slider (three at a time, auto-advancing), so add as many as you like; order here = order on the site.',
        [[count($rows), 'cards']], ob_get_clean());
    ?>
    <div class="sj-list sj-cardgrid" data-list="testimonial">
      <?php foreach ($rows as $t) {
          panel_preview_card([
              'entity' => 'testimonial', 'id' => $t['id'],
              'variant' => 'tm', 'aspect' => '4x3',
              'img'  => $t['image'] ? img_url($t['image'], 'feature_4x3') : null, // N2 bg (same preset the site uses)
              'tm_name_html' => $t['name_html'],   // pre-sanitized _html — echoed raw like the public partial
              'tm_body_html' => $t['body_html'],
              'title'  => trim(strip_tags($t['name_html'])) ?: '(unnamed)',
              'sub'    => $t['image'] ? 'Custom background' : 'Default background',
              'active' => (bool)$t['is_active'], 'canMove' => true,
          ]);
      }
      if (!$rows) { panel_empty('Add the first student testimonial.', 'quote'); } ?>
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
    <?php
    panel_page_head('layers', 'School Sections',
        'KG, Primary, High School and Higher Secondary — each tab edits that page\'s banner, intro, timeline and event blocks.',
        [[count($allSections), 'sections'], [count($secSlides), $S['name'] . ' slides']]);
    ?>
    <?php // K7 (owner request): the Academics PAGE's own banner is edited here,
          // above the four section tabs — one common edit area for that page.
    $acadPage   = repo_page('academics');
    $acadSlides = $acadPage ? repo_hero_slides((int)$acadPage['id'], true) : [];
    if ($acadPage):
        ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('hero_slide', ['page_id' => (int)$acadPage['id']], 'Add academics slide', null, $acadSlides ? end($acadSlides) : null) ?>><?= sj_icon('plus', 14) ?> Add slide</button>
    <?php $acadAdd = ob_get_clean(); ?>
    <h3 class="sj-form-legend">Academics page — top banner <?= $acadAdd ?></h3>
    <div class="sj-list sj-cardgrid" data-list="hero_slide">
      <?php foreach ($acadSlides as $sl) {
          panel_preview_card([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'variant' => 'hero', 'aspect' => '16x9',
              'img'       => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'cap_title' => $sl['caption_title'], 'cap_text' => $sl['caption_text'],
              'title'     => $sl['caption_title'] ?: '(no caption)',
              'active'    => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      }
      if (!$acadSlides) { panel_empty('Add the first Academics banner slide.'); } ?>
    </div>
    <?php endif; ?>

    <h3 class="sj-form-legend">The four school sections</h3>
    <div class="sj-tabs">
      <?php foreach ($allSections as $sRow): ?>
      <a class="sj-btn <?= $sRow['slug'] === $cur ? 'sj-btn-primary' : 'sj-btn-ghost' ?>"
         href="/admin/section.php?s=sections&sec=<?= e($sRow['slug']) ?>"><?= e($sRow['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <?php ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('hero_slide', ['page_id' => (int)$secPage['id']], 'Add slide') ?>><?= sj_icon('plus', 14) ?> Add slide</button>
    <?php $addBtn = ob_get_clean(); ?>
    <h3 class="sj-form-legend">Top carousel <?= $addBtn ?></h3>
    <div class="sj-list sj-cardgrid" data-list="hero_slide">
      <?php foreach ($secSlides as $sl) {
          panel_preview_card([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'variant' => 'hero', 'aspect' => '16x9',
              'img'       => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'cap_title' => $sl['caption_title'], 'cap_text' => $sl['caption_text'],
              'title'     => $sl['caption_title'] ?: '(no caption)',
              'active'    => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <h3 class="sj-form-legend">Intro & grade card</h3>
    <div class="sj-section-head">
      <p class="sj-lead">The intro text beside the photo carousel, plus the grade card shown on the
         Academics page. The carousel photos have their own manager.</p>
      <button class="sj-btn sj-btn-ghost" <?= panel_photos_attr('section', (int)$S['id'], 'carousel', 'content_slide', $S['name'] . ' — carousel photos') ?>><?= sj_icon('image', 15) ?>Manage carousel photos</button>
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
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('timeline_entry', ['section_id' => (int)$S['id']], 'Add timeline month') ?>><?= sj_icon('plus', 15) ?> Add month</button>
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
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('section_event', ['section_id' => (int)$S['id']], 'Add event block') ?>><?= sj_icon('plus', 15) ?> Add event</button>
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
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('academy', [], 'Add academy') ?>><?= sj_icon('plus', 15) ?> Add academy</button>
    <?php
    panel_page_head('award', 'Academies',
        'The academy pages and their cards on the Co-Curriculum page — each card below previews the grid card. '
        . 'Order here = card order on the site. New academies get their page automatically at the URL key you choose; '
        . 'the 18 original ones keep their fixed pages and can only be hidden, not deleted.',
        [[count($rows), 'academies'], [count(array_filter($rows, static fn ($x) => !empty($x['is_active']))), 'on the grid']],
        ob_get_clean());
    ?>
    <div class="sj-list sj-cardgrid" data-list="academy">
      <?php foreach ($rows as $ac) {
          ob_start(); ?>
            <button class="sj-ico" title="Carousel photos" aria-label="Carousel photos" <?= panel_photos_attr('academy', (int)$ac['id'], 'carousel', 'content_slide', $ac['card_title'] . ' — carousel photos') ?>><?= sj_icon('image', 16) ?></button>
          <?php
          panel_preview_card([
              'entity' => 'academy', 'id' => $ac['id'],
              'variant' => 'card', 'aspect' => '4x3',
              'img'    => $ac['image'] ? img_url($ac['image'], 'card_4x3') : null,
              'title'  => $ac['card_title'],
              'sub'    => $ac['card_subtitle'] . ' · ' . academy_url($ac['slug']),
              'active' => (bool)$ac['is_active'], 'canMove' => true,
              // N3 guard: the 18 shipped academies keep their fixed pages
              'canDelete' => !in_array($ac['slug'], \SJ\Content\Registry::legacySlugs()['academy'], true),
              'extraActionsHtml' => ob_get_clean(),
          ]);
      } ?>
    </div>
    <?php
    break;

/* ================= SPORTS (C6) ================= */
case 'sports':
    $sportsPage = repo_page('sports');
    $rows = repo_sports(true);
    $heroSlides = repo_hero_slides((int)$sportsPage['id'], true);
    panel_page_head('flag', 'Sports',
        'The Sports page — its rotating top banner and the sport cards with their "Read More" panels. Order here = order on the site.',
        [[count($heroSlides), 'banner slides'], [count($rows), 'sport cards']]);
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('hero_slide', ['page_id' => (int)$sportsPage['id']], 'Add sports slide') ?>><?= sj_icon('plus', 14) ?> Add slide</button>
    <?php $addSlideBtn = ob_get_clean(); ?>
    <h3 class="sj-form-legend">Top carousel <?= $addSlideBtn ?></h3>
    <div class="sj-list sj-cardgrid" data-list="hero_slide">
      <?php foreach ($heroSlides as $sl) {
          panel_preview_card([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'variant' => 'hero', 'aspect' => '16x9',
              'img'       => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'cap_title' => $sl['caption_title'], 'cap_text' => $sl['caption_text'],
              'title'     => $sl['caption_title'] ?: '(no caption)',
              'active'    => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <?php ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('sport', [], 'Add sport') ?>><?= sj_icon('plus', 14) ?> Add sport</button>
    <?php $addSportBtn = ob_get_clean(); ?>
    <h3 class="sj-form-legend">Sport cards <?= $addSportBtn ?></h3>
    <div class="sj-list sj-cardgrid" data-list="sport">
      <?php foreach ($rows as $S) {
          panel_preview_card([
              'entity' => 'sport', 'id' => $S['id'],
              'variant' => 'card', 'aspect' => '4x3',
              'img'    => $S['image'] ? img_url($S['image'], 'card_4x3') : null,
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
    $heroSlides = repo_hero_slides((int)$infraPage['id'], true);
    $facilities = repo_facilities(true);
    panel_page_head('building', 'Infrastructure',
        'The Infrastructure page — its rotating top banner and the facility showcases. Order here = order (and the quick-jump buttons) on the page.',
        [[count($heroSlides), 'banner slides'], [count($facilities), 'facilities']]);
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('hero_slide', ['page_id' => (int)$infraPage['id']], 'Add slide') ?>><?= sj_icon('plus', 14) ?> Add slide</button>
    <?php $addBtn = ob_get_clean(); ?>
    <h3 class="sj-form-legend">Top carousel <?= $addBtn ?></h3>
    <div class="sj-list sj-cardgrid" data-list="hero_slide">
      <?php foreach ($heroSlides as $sl) {
          panel_preview_card([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'variant' => 'hero', 'aspect' => '16x9',
              'img'       => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'cap_title' => $sl['caption_title'], 'cap_text' => $sl['caption_text'],
              'title'     => $sl['caption_title'] ?: '(no caption)',
              'active'    => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <?php ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('facility', [], 'Add facility') ?>><?= sj_icon('plus', 14) ?> Add facility</button>
    <?php $addBtn = ob_get_clean(); ?>
    <h3 class="sj-form-legend">Facility showcases <?= $addBtn ?></h3>
    <div class="sj-list sj-cardgrid" data-list="facility">
      <?php foreach ($facilities as $F) {
          ob_start(); ?>
            <button class="sj-ico" title="Carousel photos" aria-label="Carousel photos" <?= panel_photos_attr('facility', (int)$F['id'], 'carousel', 'content_slide', $F['name'] . ' — carousel photos') ?>><?= sj_icon('image', 16) ?></button>
          <?php
          panel_preview_card([
              'entity' => 'facility', 'id' => $F['id'],
              'variant' => 'card', 'aspect' => '16x9',
              'img'    => $F['image'] ? img_url($F['image'], 'bg_wide') : null,
              'title'  => $F['name'],
              'sub'    => count($F['carousel']) . ' carousel photos',
              'active' => (bool)$F['is_active'], 'canMove' => true,
              'extraActionsHtml' => ob_get_clean(),
          ]);
      } ?>
    </div>
    <?php
    break;

/* ================= ACHIEVEMENTS (C8) ================= */
case 'achievements':
    $achPage = repo_page('achievements');
    $heroSlides = repo_hero_slides((int)$achPage['id'], true);
    panel_page_head('trophy', 'Achievements',
        'The Achievements page — its rotating top banner plus the two zig-zag lists (achievements and awards). Photos alternate left/right automatically.',
        [[count($heroSlides), 'banner slides']]);
    ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('hero_slide', ['page_id' => (int)$achPage['id']], 'Add slide') ?>><?= sj_icon('plus', 14) ?> Add slide</button>
    <?php $addBtn = ob_get_clean(); ?>
    <h3 class="sj-form-legend">Top carousel <?= $addBtn ?></h3>
    <div class="sj-list sj-cardgrid" data-list="hero_slide">
      <?php foreach ($heroSlides as $sl) {
          panel_preview_card([
              'entity' => 'hero_slide', 'id' => $sl['id'],
              'variant' => 'hero', 'aspect' => '16x9',
              'img'       => $sl['image'] ? img_url($sl['image'], 'hero_16x7') : null,
              'cap_title' => $sl['caption_title'], 'cap_text' => $sl['caption_text'],
              'title'     => $sl['caption_title'] ?: '(no caption)',
              'active'    => (bool)$sl['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>

    <?php foreach (['achievement' => 'Achievements list', 'award' => 'Awards list ("given by St.Joseph\'s")'] as $atype => $alabel): ?>
    <?php ob_start(); ?>
      <button class="sj-btn sj-btn-primary sj-btn-sm" <?= panel_add_attr('achievement', ['type' => $atype], 'Add to ' . $alabel) ?>><?= sj_icon('plus', 14) ?> Add</button>
    <?php $addBtn = ob_get_clean(); ?>
    <h3 class="sj-form-legend"><?= e($alabel) ?> <?= $addBtn ?></h3>
    <div class="sj-list sj-cardgrid" data-list="achievement">
      <?php foreach (repo_achievements($atype, true) as $A) {
          panel_preview_card([
              'entity' => 'achievement', 'id' => $A['id'],
              'variant' => 'card', 'aspect' => '4x3',
              'img'    => $A['image'] ? img_url($A['image'], 'feature_4x3') : null,
              'title'  => $A['title'],
              'sub'    => $A['subtext'],
              'active' => (bool)$A['is_active'], 'canMove' => true,
          ]);
      } ?>
    </div>
    <?php endforeach; ?>
    <?php
    break;

/* ================= GALLERY ALBUMS (C9) ================= */
case 'gallery':
    $albums = repo_albums(true);
    $curAlb = (int)($_GET['album'] ?? ($albums[0]['id'] ?? 0));
    $galleryPage = repo_page('gallery');
    ?>
    <?php ob_start(); ?>
      <?php if ($galleryPage): // N4: the hub's cross-fade slideshow ?>
      <button class="sj-btn sj-btn-ghost" <?= panel_photos_attr('page', (int)$galleryPage['id'], 'slider', 'bg_wide', 'Gallery hub slideshow photos') ?>><?= sj_icon('image', 15) ?> Hub slideshow</button>
      <?php endif; ?>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('gallery_album', [], 'Add album') ?>><?= sj_icon('plus', 15) ?> Add album</button>
    <?php
    panel_page_head('images', 'Gallery Albums',
        'The Gallery hub page: its centre slideshow, the album cards, and each album\'s years & photos. New albums get their page automatically at the URL key you choose; the 10 original ones keep their fixed pages and can only be hidden, not deleted.',
        [[count($albums), 'albums']], ob_get_clean());
    ?>
    <div class="sj-tabs">
      <?php foreach ($albums as $al): ?>
      <a class="sj-btn <?= (int)$al['id'] === $curAlb ? 'sj-btn-primary' : 'sj-btn-ghost' ?>"
         href="/admin/section.php?s=gallery&album=<?= (int)$al['id'] ?>"><?= e($al['title']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php foreach ($albums as $al): if ((int)$al['id'] !== $curAlb) { continue; } ?>
    <h3 class="sj-form-legend">Album card (gallery hub)</h3>
    <div class="sj-list sj-cardgrid">
      <?php panel_preview_card([
          'entity' => 'gallery_album', 'id' => (int)$al['id'],
          'variant' => 'card', 'aspect' => '4x3',
          'img'    => $al['image'] ? img_url($al['image'], 'card_4x3') : null,
          'title'  => $al['title'],
          'sub'    => $al['card_sub'] . ' · ' . album_url($al['slug']),
          'active' => (bool)$al['is_active'],
          // N4: only admin-created albums are deletable (legacy have fixed pages)
          'canDelete' => !in_array($al['slug'], \SJ\Content\Registry::legacySlugs()['gallery_album'], true),
      ]); ?>
    </div>

    <h3 class="sj-form-legend">Years & photos</h3>
    <div class="sj-section-head">
      <p class="sj-lead">Each year has its own photo set (managed with the image button). The first year is the default filter.</p>
      <button class="sj-btn sj-btn-primary" <?= panel_add_attr('album_year', ['album_id' => (int)$al['id']], 'Add year') ?>><?= sj_icon('plus', 15) ?> Add year</button>
    </div>
    <div class="sj-list" data-list="album_year">
      <?php
      $full = repo_album($al['slug'], true);
      foreach ($full['years'] as $Y) { ?>
        <div class="sj-row<?= $Y['is_active'] ? '' : ' off' ?>" data-row="album_year:<?= (int)$Y['id'] ?>">
          <div class="sj-row-main">
            <b><?= e($Y['year_label']) ?></b>
            <span><?= count($Y['photos']) ?> photos</span>
          </div>
          <?php if (!$Y['is_active']): ?><span class="sj-badge">Hidden</span><?php endif; ?>
          <div class="sj-row-actions">
            <button class="sj-ico" title="Photos" <?= panel_photos_attr('album_year', (int)$Y['id'], 'photos', 'gallery_full', $al['title'] . ' — ' . $Y['year_label'] . ' photos') ?>><?= sj_icon('image', 16) ?></button>
            <button class="sj-ico" title="Edit" data-act="edit" aria-label="Edit"><?= sj_icon('edit', 16) ?></button>
            <button class="sj-ico" title="Move up" data-act="move" data-dir="-1" aria-label="Move up"><?= sj_icon('up', 16) ?></button>
            <button class="sj-ico" title="Move down" data-act="move" data-dir="1" aria-label="Move down"><?= sj_icon('down', 16) ?></button>
            <button class="sj-ico" title="<?= $Y['is_active'] ? 'Hide' : 'Show' ?>" data-act="toggle" data-active="<?= $Y['is_active'] ? 1 : 0 ?>"><?= $Y['is_active'] ? sj_icon('eye', 16) : sj_icon('eye-off', 16) ?></button>
            <button class="sj-ico danger" title="Delete" data-act="del" data-confirm="Delete this year AND its photo list? The photos stay in the library."><?= sj_icon('trash', 16) ?></button>
          </div>
        </div>
      <?php } ?>
    </div>
    <?php endforeach; ?>
    <?php
    break;

/* ================= SITE SETTINGS (C1) ================= */
/* ================= SEO (F3) ================= */
case 'seo':
    $rows = db()->query('SELECT * FROM seo_meta ORDER BY id')->fetchAll();
    ?>
    <?php panel_page_head('search', 'SEO',
        'The browser-tab title and the search-result description for every public page. Google shows roughly the first 60 characters of a title and 155 of a description — front-load what matters. One row per URL; rows can\'t be added or removed.',
        [[count($rows), 'pages']]); ?>
    <div class="sj-list" data-list="seo_meta">
      <?php foreach ($rows as $r) {
          panel_row([
              'entity' => 'seo_meta', 'id' => $r['id'],
              'title'  => '/' . ($r['slug'] === 'index' ? '' : $r['slug'] . '.php') . ' — ' . $r['title'],
              'sub'    => $r['description'],
              'canMove' => false, 'canDelete' => false,
          ]);
      } ?>
    </div>
    <?php
    break;

/* ================= ADMIN ACCOUNTS (N7 — owners only) ================= */
case 'admins':
    ?>
    <?php ob_start(); ?>
      <button class="sj-btn sj-btn-primary" id="sj-admin-new"><?= sj_icon('plus', 15) ?> New account</button>
    <?php
    panel_page_head('shield', 'Admin Accounts',
        'Panel accounts. New accounts get a one-time temporary password (shown once — copy it and hand it over) and must set their own password at first sign-in. Owners manage accounts; editors edit content only. You can never delete yourself or the last owner.',
        [[(int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn(), 'accounts']], ob_get_clean());
    ?>
    <div class="sj-list" id="sj-admins" data-me="<?= (int)$_SESSION['admin_id'] ?>">
      <div class="sj-row"><div class="sj-row-main"><span class="sj-skel">loading accounts…</span></div></div>
    </div>
    <?php
    break;

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
        'Home motto block' => [
            'motto_heading' => ['Heading', ''],
            'motto_sub'     => ['Sub-line', ''],
            'motto1_title'  => ['Card 1 — title', ''],
            'motto1_body'   => ['Card 1 — text', ''],
            'motto2_title'  => ['Card 2 — title', ''],
            'motto2_body'   => ['Card 2 — text', ''],
        ],
        'About page — rules & diary' => [
            'diary_text' => ['Diary line', 'The sentence before the Download link in the Rules block.'],
            'diary_url'  => ['Diary file', 'Press "Upload diary (PDF)" and pick the file — this link fills in and goes live by itself. (Advanced: you can also paste a link here and press Save.)'],
        ],
        'Home stat band (the four animated counters)' => [
            'home_stat1_value' => ['Stat 1 — value', 'A number plus optional suffix, e.g. 80+ or 100%'],
            'home_stat1_label' => ['Stat 1 — label', ''],
            'home_stat2_value' => ['Stat 2 — value', ''],
            'home_stat2_label' => ['Stat 2 — label', ''],
            'home_stat3_value' => ['Stat 3 — value', ''],
            'home_stat3_label' => ['Stat 3 — label', ''],
            'home_stat4_value' => ['Stat 4 — value', ''],
            'home_stat4_label' => ['Stat 4 — label', ''],
        ],
        'Social & misc' => [
            'facebook_url'      => ['Facebook URL', 'Must start with https://'],
            'youtube_url'       => ['YouTube URL', 'Must start with https://'],
            'whatsapp_number'   => ['WhatsApp number', 'Digits with country code (e.g. 919876543210) for the floating WhatsApp button on the website. Leave blank to hide the button.'],
            'footer_copyright'  => ['Footer copyright line', ''],
            'marks_years_shown' => ['Top-Marks years shown', 'How many recent years the home/highsec toppers board shows (1–10).'],
        ],
    ];
    ?>
    <?php panel_page_head('sliders', 'Site Settings',
        'Site-wide text used across pages — contact details, school timings, the admissions band and social links. Press Save at the bottom when done.',
        [[count($groups, COUNT_RECURSIVE) - count($groups), 'settings']]); ?>
    <form class="sj-form-card" id="sj-settings" data-api="settings">
      <?php foreach ($groups as $legend => $keys): ?>
        <h3 class="sj-form-legend"><?= e($legend) ?></h3>
        <div class="sj-form-grid2">
        <?php foreach ($keys as $key => [$label, $hint]): ?>
          <label class="sj-field">
            <span><?= e($label) ?></span>
            <input type="text" name="<?= e($key) ?>" value="<?= e(repo_setting($key, '')) ?>">
            <?php if ($key === 'diary_url'): // K11: upload a PDF instead of typing a path ?>
              <input type="file" id="sj-diary-file" accept="application/pdf,.pdf" hidden>
              <button type="button" class="sj-btn" id="sj-diary-btn"><?= sj_icon('upload', 14) ?> Upload diary (PDF)</button>
              <small class="sj-hint">Choose a PDF (name it clearly first, e.g. <b>school-diary-2026.pdf</b>). Visitors always download it as “SchoolDiary.pdf”.</small>
            <?php endif; ?>
            <?php if ($hint): ?><small><?= e($hint) ?></small><?php endif; ?>
          </label>
        <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
      <div class="sj-form-actions">
        <button type="submit" class="sj-btn sj-btn-primary"><?= sj_icon('check', 15) ?> Save settings</button>
        <span class="sj-save-note" id="sj-settings-note"></span>
      </div>
    </form>
    <script src="/admin/assets/settings.js?v=<?= SJ_ASSET_VER ?>" defer></script>
    <?php
    break;
}

panel_footer();
