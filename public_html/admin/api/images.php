<?php
// GET ?q=&page= → paginated media library for the picker (legacy photos + uploads).
require __DIR__ . '/_bootstrap.php';

$q      = trim((string)($_GET['q'] ?? ''));
$page   = max(0, (int)($_GET['page'] ?? 0));
$orphan = (string)($_GET['filter'] ?? '') === 'orphan';
$per    = 24;

$sql  = 'SELECT id, legacy_path, original_name, alt_text, mime, preset_key, crop_rect, width, height, version FROM images';
$args = [];
if ($q !== '') {
    $sql .= ' WHERE legacy_path LIKE ? OR original_name LIKE ?';
    $args = ["%$q%", "%$q%"];
}
$sql .= ' ORDER BY id DESC LIMIT ' . ($per + 1) . ' OFFSET ' . ($page * $per);
$st = db()->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$hasMore = count($rows) > $per;
$rows = array_slice($rows, 0, $per);

// usage counts for this page of images — ONE UNION query (M4)
$useByImage = [];
if ($rows) {
    $ids = array_column($rows, 'id');
    $in  = implode(',', array_fill(0, count($ids), '?'));
    $refs = [
        ['image_links', 'image_id'], ['hero_slides', 'image_id'], ['profiles', 'image_id'],
        ['unique_features', 'image_id'], ['update_slides', 'image_id'], ['sports', 'image_id'],
        ['achievements', 'image_id'], ['facilities', 'bg_image_id'],
        ['academies', 'card_image_id'], ['academies', 'bg_image_id'], ['school_sections', 'card_image_id'],
        // keep in sync with SJ_IMAGE_REFS in image.php (F2 gap fix)
        ['section_events', 'image_id'], ['gallery_albums', 'card_image_id'],
        ['testimonials', 'bg_image_id'], // N2
    ];
    $parts = [];
    $args  = [];
    foreach ($refs as [$t, $c]) {
        $parts[] = "SELECT `$c` AS iid FROM `$t` WHERE `$c` IN ($in)";
        array_push($args, ...$ids);
    }
    $st = db()->prepare(implode(' UNION ALL ', $parts));
    $st->execute($args);
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $iid) {
        $useByImage[(int)$iid] = ($useByImage[(int)$iid] ?? 0) + 1;
    }
}
if ($orphan) {
    $rows = array_values(array_filter($rows, static fn ($r) => empty($useByImage[(int)$r['id']])));
}

$items = [];
foreach ($rows as $r) {
    if (!empty($r['legacy_path'])) {
        $thumb = $r['legacy_path'];
        $label = basename($r['legacy_path']);
    } else {
        $thumb = '/media/' . $r['id'] . '/' . ($r['preset_key'] ?? '') . '.jpg';
        // fall back to original when the preset jpg is unknown/missing
        $presetJpg = SJ_PUBLIC_ROOT . $thumb;
        if (empty($r['preset_key']) || !is_file($presetJpg)) {
            $thumb = '/media/' . $r['id'] . '/original.' . sj_ext_for_mime((string)$r['mime']);
        }
        $label = $r['original_name'] ?: ('upload #' . $r['id']);
    }
    $isLegacy = !empty($r['legacy_path']);
    $items[] = [
        'id'         => (int)$r['id'],
        'thumb'      => $thumb . ($isLegacy ? '' : '?v=' . (int)$r['version']),
        'label'      => $label,
        'legacy'     => $isLegacy,
        'preset_key' => $r['preset_key'],
        'crop_rect'  => $r['crop_rect'],
        'w'          => (int)$r['width'],
        'h'          => (int)$r['height'],
        'orig'       => $isLegacy ? null : '/media/' . $r['id'] . '/original.' . sj_ext_for_mime((string)$r['mime']),
        'alt'        => $r['alt_text'],
        'used'       => $useByImage[(int)$r['id']] ?? 0,
    ];
}
api_out(['items' => $items, 'hasMore' => $hasMore]);
