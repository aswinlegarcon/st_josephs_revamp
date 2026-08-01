<?php
// GET ?q=&page= → paginated media library for the picker (legacy photos + uploads).
require __DIR__ . '/_bootstrap.php';

$q    = trim((string)($_GET['q'] ?? ''));
$page = max(0, (int)($_GET['page'] ?? 0));
$per  = 24;

$sql  = 'SELECT id, legacy_path, original_name, alt_text, mime, preset_key, version FROM images';
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
    $items[] = ['id' => (int)$r['id'], 'thumb' => $thumb, 'label' => $label];
}
api_out(['items' => $items, 'hasMore' => $hasMore]);
