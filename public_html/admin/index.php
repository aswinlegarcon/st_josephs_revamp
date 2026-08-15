<?php
require __DIR__ . '/_layout.php';

// Content cards: slug, icon name (sj_icon), title, description, count line.
$cards = [
    ['hero',      'image', 'Hero Carousel',  'The big rotating banner at the top of the Home page.',
        (int)db()->query('SELECT COUNT(*) FROM hero_slides')->fetchColumn() . ' slides'],
    ['principal', 'user', 'Principal',      'Photo, name and welcome message shown on Home & About.',
        '1 profile'],
    ['aboutpage', 'book', 'About Page',     'Top carousel + President, History and Rules blocks.',
        (int)db()->query('SELECT COUNT(*) FROM profiles')->fetchColumn() . ' blocks'],
    ['staffspage', 'users', 'Staffs Page',  'Top carousel + the three staff text blocks.',
        '3 blocks'],
    ['testimonials', 'quote', 'Testimonials', 'The student testimonial cards on the Home page.',
        (int)db()->query('SELECT COUNT(*) FROM testimonials')->fetchColumn() . ' cards'],
    ['sections',  'layers', 'School Sections', 'KG, Primary, High School & Higher Secondary pages.',
        (int)db()->query('SELECT COUNT(*) FROM timeline_entries')->fetchColumn() . ' timeline months'],
    ['academies', 'award', 'Academies', 'The academy pages + their co-curriculum cards.',
        (int)db()->query('SELECT COUNT(*) FROM academies')->fetchColumn() . ' academies'],
    ['sports',    'flag', 'Sports', 'The Sports page — hero photos and the sport cards.',
        (int)db()->query('SELECT COUNT(*) FROM sports')->fetchColumn() . ' sports'],
    ['facilities', 'building', 'Infrastructure', 'The facility showcases with quick-jump nav and carousels.',
        (int)db()->query('SELECT COUNT(*) FROM facilities')->fetchColumn() . ' facilities'],
    ['achievements', 'trophy', 'Achievements', 'The zig-zag achievement and award lists.',
        (int)db()->query('SELECT COUNT(*) FROM achievements')->fetchColumn() . ' items'],
    ['gallery', 'images', 'Gallery Albums', 'The photo albums — years, photo sets and hub cards.',
        (int)db()->query('SELECT COUNT(*) FROM gallery_albums')->fetchColumn() . ' albums · ' .
        (int)db()->query("SELECT COUNT(*) FROM image_links WHERE owner_type='album_year'")->fetchColumn() . ' photos'],
    ['unique',    'star', "What's Unique",  'The ESC / Language Academies feature blocks.',
        (int)db()->query('SELECT COUNT(*) FROM unique_features')->fetchColumn() . ' blocks'],
    ['ticker',    'bell', 'News Ticker',    'The scrolling announcement bar with links.',
        (int)db()->query('SELECT COUNT(*) FROM ticker_items')->fetchColumn() . ' items'],
    ['updates',   'monitor', 'New Updates', 'The video-highlights carousel on the Home page.',
        (int)db()->query('SELECT COUNT(*) FROM update_slides')->fetchColumn() . ' slides'],
    ['marks',     'chart', 'Top Marks',     'Board-exam toppers, by year and standard.',
        (int)db()->query('SELECT COUNT(*) FROM mark_years')->fetchColumn() . ' years · ' .
        (int)db()->query('SELECT COUNT(*) FROM mark_entries')->fetchColumn() . ' toppers'],
    ['media',     'folder', 'Media Library', 'All site images — browse, search and upload.',
        (int)db()->query('SELECT COUNT(*) FROM images')->fetchColumn() . ' images'],
    ['seo',       'search', 'SEO',          'Browser-tab titles and search-result descriptions.',
        (int)db()->query('SELECT COUNT(*) FROM seo_meta')->fetchColumn() . ' pages'],
    ['settings',  'sliders', 'Site Settings', 'Contact details, timings, admissions band, links.',
        (int)db()->query('SELECT COUNT(*) FROM settings')->fetchColumn() . ' settings'],
];
if (sj_admin_role() === 'owner') {
    $cards[] = ['admins', 'shield', 'Admin Accounts', 'Create, unlock or remove panel accounts (owners only).',
        (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() . ' accounts'];
}

// Server-rendered vitals seed (dashboard.js keeps them live via ?r=stats).
$free  = @disk_free_space(SJ_PUBLIC_ROOT);
$total = @disk_total_space(SJ_PUBLIC_ROOT);
$bdir  = sj_config()['backup_dir'] ?? (dirname(SJ_PUBLIC_ROOT) . '/backups');
$newest = 0;
foreach (glob($bdir . '/db-*.sql.gz') ?: [] as $f) {
    $newest = max($newest, (int)filemtime($f));
}
$diskPct = ($free !== false && $total) ? (int)round(100 - $free / $total * 100) : 0;

// Recent activity: the last 8 audit rows, humanised (SEC-20: status only).
$sjFeed = db()->query(
    'SELECT a.action, a.entity, a.detail, a.created_at, u.display_name
     FROM audit_log a LEFT JOIN admin_users u ON u.id = a.admin_id
     ORDER BY a.id DESC LIMIT 8'
)->fetchAll();
$actIcon = static function (string $a): string {
    if (str_starts_with($a, 'login') || str_starts_with($a, 'recover')) return 'key';
    if (str_starts_with($a, 'admin.')) return 'shield';
    if ($a === 'item.delete' || $a === 'image.delete') return 'trash';
    if (str_starts_with($a, 'upload') || $a === 'recrop') return 'camera';
    if ($a === 'order.save') return 'refresh';
    return 'edit';
};
$ago = static function (string $ts): string {
    $d = time() - (int)strtotime($ts);
    if ($d < 90) return 'just now';
    if ($d < 5400) return (int)round($d / 60) . ' min ago';
    if ($d < 129600) return (int)round($d / 3600) . ' h ago';
    return (int)round($d / 86400) . ' d ago';
};
$hour = (int)date('G');
$greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

panel_header('dashboard', 'Dashboard');
?>
<div class="sj-hello">
  <div>
    <h2><?= e($greet) ?>, <?= e($_SESSION['admin_name'] ?? 'admin') ?></h2>
    <p><?= e(date('l, j F Y')) ?> — changes save to the database immediately; visitors see them on their next refresh.</p>
  </div>
  <div class="sj-hello-actions">
    <a class="sj-btn sj-btn-ghost sj-btn-sm" href="/admin/section.php?s=media"><?= sj_icon('upload', 15) ?> Upload photos</a>
    <a class="sj-btn sj-btn-primary sj-btn-sm" href="/index.php" target="_blank"><?= sj_icon('external', 15) ?> View site</a>
  </div>
</div>

<h3 class="sj-form-legend">Site vitals — live</h3>
<div class="sj-vitals" id="sj-vitals">
  <div class="sj-vital">
    <div class="sj-vital-top"><?= sj_icon('activity', 15) ?> Availability</div>
    <div class="sj-vital-num" id="v-avail"><span class="sj-skel">checking…</span></div>
    <div class="sj-vital-sub" id="v-avail-sub">site + database reachability</div>
  </div>
  <div class="sj-vital">
    <div class="sj-vital-top"><?= sj_icon('zap', 15) ?> Speed</div>
    <div class="sj-vital-num" id="v-speed"><span class="sj-skel">…</span></div>
    <canvas class="sj-spark" id="v-spark" width="220" height="34" aria-hidden="true"></canvas>
  </div>
  <div class="sj-vital">
    <div class="sj-vital-top"><?= sj_icon('cpu', 15) ?> Server memory</div>
    <div class="sj-vital-num" id="v-mem"><span class="sj-skel">…</span></div>
    <div class="sj-bar"><i id="v-mem-bar"></i></div>
    <div class="sj-vital-sub" id="v-mem-sub">PHP peak this request: —</div>
  </div>
  <div class="sj-vital">
    <div class="sj-vital-row">
      <div class="sj-donut" id="v-disk-donut" style="--p: <?= (int)$diskPct ?>"></div>
      <div>
        <div class="sj-vital-top"><?= sj_icon('disk', 15) ?> Disk</div>
        <div class="sj-vital-num" id="v-disk"><?= $free !== false ? round($free / 1073741824, 1) . '<small> GB free</small>' : '—' ?></div>
        <div class="sj-vital-sub" id="v-disk-sub"><?= (int)$diskPct ?>% used</div>
      </div>
    </div>
  </div>
  <div class="sj-vital">
    <div class="sj-vital-top"><?= sj_icon('database', 15) ?> Storage</div>
    <div class="sj-vital-num" id="v-db"><span class="sj-skel">…</span></div>
    <div class="sj-vital-sub" id="v-db-sub">database · media library</div>
  </div>
  <div class="sj-vital">
    <div class="sj-vital-top"><?= sj_icon('clock', 15) ?> Last backup</div>
    <div class="sj-vital-num" id="v-backup"><?= $newest ? round((time() - $newest) / 3600, 1) . '<small> h ago</small>' : 'none yet' ?></div>
    <div class="sj-vital-sub" id="v-backup-sub">nightly database dump</div>
  </div>
  <div class="sj-vital">
    <div class="sj-vital-top"><?= sj_icon('refresh', 15) ?> PHP cache</div>
    <div class="sj-vital-num" id="v-opcache"><span class="sj-skel">…</span></div>
    <div class="sj-vital-sub" id="v-opcache-sub">OPcache hit rate</div>
  </div>
</div>

<h3 class="sj-form-legend">Content</h3>
<div class="sj-cards">
  <?php foreach ($cards as [$slug, $icon, $title, $desc, $count]): ?>
  <a class="sj-card" href="/admin/section.php?s=<?= e($slug) ?>">
    <div class="sj-card-icon"><?= sj_icon($icon, 20) ?></div>
    <div class="sj-card-body">
      <b><?= e($title) ?></b>
      <span><?= e($desc) ?></span>
    </div>
    <div class="sj-card-go"><?= e($count) ?> ›</div>
  </a>
  <?php endforeach; ?>
</div>

<div class="sj-feed">
  <h3>Recent activity</h3>
  <ul>
    <?php if (!$sjFeed): ?><li>No activity recorded yet.</li><?php endif; ?>
    <?php foreach ($sjFeed as $f2): ?>
    <li>
      <span class="i"><?= sj_icon($actIcon($f2['action']), 15) ?></span>
      <span><b><?= e($f2['display_name'] ?: 'system') ?></b> — <?= e($f2['action']) ?><?= $f2['entity'] ? ' · ' . e($f2['entity']) : '' ?><?= $f2['detail'] !== '' ? ' · ' . e(mb_substr($f2['detail'], 0, 40)) : '' ?></span>
      <time><?= e($ago($f2['created_at'])) ?></time>
    </li>
    <?php endforeach; ?>
  </ul>
</div>

<div class="sj-note">
  <b>Tip:</b> image uploads are automatically cropped to the exact shape each slot needs and
  lightly compressed (JPEG + WebP), so slides and cards always stay uniform and fast.
</div>
<script src="/admin/assets/dashboard.js?v=<?= SJ_ASSET_VER ?>" defer></script>
<?php panel_footer(); ?>
