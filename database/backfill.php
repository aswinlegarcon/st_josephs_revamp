<?php
// F2 — legacy rendition backfill (PHASES.md Stage H). CLI + Docker/dev ONLY:
// GD-crunching 478 photos would starve the shared host, so renditions are
// generated here and DEPLOYED as files + an image_renditions SQL delta
// (see DEPLOY.md). Idempotent: (image, preset) pairs that already have
// renditions are skipped, so a re-run after adding photos is cheap.
//
// What it does, per legacy image (images.legacy_path set):
//   source = public_html{legacy_path}  (the original stays where it is —
//   legacy URLs keep working; nothing is moved or modified)
//   for each preset the site actually renders that image with (derived from
//   the same FK map the views/registry use), generate media/{id}/{preset}.jpg
//   (+ .webp when GD supports it) via the standard SJ\Media\Pipeline.
// The render path (SJ\Media\Html) prefers these renditions when they exist
// and falls back to legacy_path when they don't — so a missed pair degrades
// gracefully to today's behaviour instead of breaking. Legacy tags render as
// plain <img> (no <picture>) and carry no width/height attributes — both were
// measured to move the shipped layouts (see docs/learn/08-stage-h.md).

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

foreach ([dirname(__DIR__) . '/public_html', '/var/www/html'] as $root) {
    if (is_file($root . '/bootstrap.php')) {
        define('SJ_PUBLIC_ROOT', $root);
        require $root . '/bootstrap.php';
        break;
    }
}
if (!defined('SJ_PUBLIC_ROOT')) {
    fwrite(STDERR, "Cannot locate public_html/bootstrap.php\n");
    exit(1);
}

$pdo = db();

/* ---- which (image, preset) pairs do the pages actually request? ----
   FK columns → the preset the corresponding view passes to img_tag()/img_url()
   (enumerated from views/; profiles split by role because the staff blocks
   render 4:3 while the portrait blocks render 4:5). image_links roles map to
   the presets their consumers use. */
$fkMap = [
    ['hero_slides',     'image_id',      'hero_16x7',   null],
    ['unique_features', 'image_id',      'feature_4x3', null],
    ['update_slides',   'image_id',      'update_16x9', null],
    ['sports',          'image_id',      'card_4x3',    null],
    ['achievements',    'image_id',      'feature_4x3', null],
    ['section_events',  'image_id',      'feature_4x3', null],
    ['facilities',      'bg_image_id',   'bg_wide',     null],
    ['academies',       'card_image_id', 'card_4x3',    null],
    ['academies',       'bg_image_id',   'bg_wide',     null],
    ['school_sections', 'card_image_id', 'card_4x3',    null],
    ['gallery_albums',  'card_image_id', 'card_4x3',    null],
    ['profiles',        'image_id',      'portrait_4x5', "role_key NOT IN ('staff_team','staff_tour')"],
    ['profiles',        'image_id',      'feature_4x3',  "role_key IN ('staff_team','staff_tour')"],
];
$linkRoleMap = [
    'photos'   => 'gallery_full',
    'carousel' => 'content_slide',
];

$pairs = []; // image_id => set of preset keys
foreach ($fkMap as [$table, $col, $preset, $where]) {
    $sql = "SELECT DISTINCT `$col` AS id FROM `$table` WHERE `$col` IS NOT NULL" . ($where ? " AND $where" : '');
    foreach ($pdo->query($sql) as $r) {
        $pairs[(int)$r['id']][$preset] = true;
    }
}
foreach ($linkRoleMap as $role => $preset) {
    $st = $pdo->prepare('SELECT DISTINCT image_id FROM image_links WHERE role = ?');
    $st->execute([$role]);
    foreach ($st->fetchAll() as $r) {
        $pairs[(int)$r['image_id']][$preset] = true;
    }
}

/* ---- generate ---- */
$made = $skipped = $notLegacy = $missing = $failed = 0;
$byPreset = [];
foreach ($pairs as $imageId => $presetSet) {
    $img = repo_image($imageId);
    if (!$img) {
        continue;
    }
    if (empty($img['legacy_path'])) {
        $notLegacy++; // uploaded images: the pipeline already made their renditions
        continue;
    }
    $src = SJ_PUBLIC_ROOT . $img['legacy_path'];
    if (!is_file($src)) {
        fwrite(STDERR, "missing source: {$img['legacy_path']} (image #$imageId)\n");
        $missing++;
        continue;
    }
    foreach (array_keys($presetSet) as $presetKey) {
        if (media_renditions($imageId, $presetKey)) {
            $skipped++;
            continue;
        }
        $preset = media_preset($presetKey);
        if ($preset === null) {
            fwrite(STDERR, "unknown preset $presetKey\n");
            $failed++;
            continue;
        }
        // LEGACY RULE: generate in FIT mode (downscale only, never crop), even
        // for cover presets. The shipped pages size these slots from each
        // photo's own aspect ratio; a cover-crop would change both the layout
        // and the framing. Fit keeps the geometry pixel-equal to the original
        // — only the file gets smaller. (Cover-cropping stays the behaviour
        // for NEW uploads, where the admin sees the crop UI.)
        $fitPreset = $preset;
        $fitPreset['mode'] = 'fit';
        try {
            media_generate($src, $imageId, $fitPreset, null);
            $made++;
            $byPreset[$presetKey] = ($byPreset[$presetKey] ?? 0) + 1;
        } catch (Throwable $ex) {
            fwrite(STDERR, "FAIL image #$imageId $presetKey: {$ex->getMessage()}\n");
            $failed++;
        }
    }
}

/* ---- static design assets (CSS backgrounds + the four static card images) --
   These are referenced by stylesheets, not by DB rows, so the preset pipeline
   can't reach them. Every source >300 KB gets a downscaled/recompressed JPEG
   in media/static/ — full frame, NO cropping, so the CSS `cover` framing is
   pixel-equal to the original; only the compression differs. The stylesheets
   point at these copies; the originals in /photos stay untouched. */
$statics = [
    // [source under public_html, output basename, max edge px, jpeg quality]
    // the three big section backgrounds render under a 70% dark gradient, so
    // the lower quality is imperceptible — and it keeps them under 300 KB
    ['/photos/kg1.jpg',           'kg1.jpg',           1600, 60],
    ['/photos/highsec1.jpg',      'highsec1.jpg',      1920, 62],
    ['/photos/primary1.jpg',      'primary1.jpg',      1920, 62],
    ['/photos/high1.jpg',         'high1.jpg',         1920, 74],
    ['/photos/asemb1.jpg',        'asemb1.jpg',        1920, 74],
    ['/photos/achbg.jpg',         'achbg.jpg',         1920, 74],
    ['/photos/staff1.jpg',        'staff1.jpg',        1920, 74],
    ['/photos/testimonial1.png',  'testimonial1.jpg',  1000, 80],
    ['/photos/testimonial2.png',  'testimonial2.jpg',  1000, 80],
    ['/photos/testimonial3.png',  'testimonial3.jpg',  1000, 80],
];
$sMade = $sSkipped = 0;
$sDir = SJ_PUBLIC_ROOT . '/media/static';
if (!is_dir($sDir) && !mkdir($sDir, 0775, true)) {
    fwrite(STDERR, "cannot create $sDir\n");
    exit(1);
}
foreach ($statics as [$rel, $out, $maxEdge, $q]) {
    $src = SJ_PUBLIC_ROOT . $rel;
    $dst = $sDir . '/' . $out;
    if (is_file($dst)) {
        $sSkipped++;
        continue;
    }
    if (!is_file($src)) {
        fwrite(STDERR, "missing static source: $rel\n");
        $missing++;
        continue;
    }
    $info = getimagesize($src);
    $gd = $info['mime'] === 'image/png' ? imagecreatefrompng($src) : imagecreatefromjpeg($src);
    if (!$gd) {
        fwrite(STDERR, "cannot decode $rel\n");
        $failed++;
        continue;
    }
    $w = imagesx($gd);
    $h = imagesy($gd);
    $scale = min(1, $maxEdge / max($w, $h)); // downscale only, keep full frame
    $dw = max(1, (int)round($w * $scale));
    $dh = max(1, (int)round($h * $scale));
    $dstGd = imagecreatetruecolor($dw, $dh);
    imagefill($dstGd, 0, 0, imagecolorallocate($dstGd, 255, 255, 255));
    imagecopyresampled($dstGd, $gd, 0, 0, 0, 0, $dw, $dh, $w, $h);
    imagedestroy($gd);
    imageinterlace($dstGd, true);
    if (!imagejpeg($dstGd, $dst, $q)) {
        fwrite(STDERR, "cannot write $out\n");
        $failed++;
    } else {
        @chmod($dst, 0644);
        $sMade++;
        echo '  static: ' . $out . ' ' . round(filesize($src) / 1024) . 'K -> ' . round(filesize($dst) / 1024) . "K\n";
    }
    imagedestroy($dstGd);
}

echo "Backfill OK\n";
echo "  generated: $made rendition sets" . ($byPreset ? ' (' . implode(', ', array_map(fn ($k, $v) => "$k=$v", array_keys($byPreset), $byPreset)) . ')' : '') . "\n";
echo "  static assets: $sMade generated, $sSkipped already present\n";
echo "  skipped (already present): $skipped · non-legacy: $notLegacy · missing sources: $missing · failed: $failed\n";
$tot = $pdo->query('SELECT COUNT(*) FROM image_renditions')->fetchColumn();
echo "  image_renditions rows now: $tot\n";
exit($failed || $missing ? 1 : 0);
