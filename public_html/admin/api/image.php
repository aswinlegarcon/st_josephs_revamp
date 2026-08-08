<?php
// POST {action:'meta'|'delete', image_id, …} — media-library operations (M4).
//  meta:   update alt_text.
//  delete: allowed ONLY when the image is used nowhere (all FK columns +
//          image_links checked); removes renditions, files and the row.
require __DIR__ . '/_bootstrap.php';

$in      = api_input();
$act     = (string)($in['action'] ?? '');
$imageId = (int)($in['image_id'] ?? 0);

$st = db()->prepare('SELECT * FROM images WHERE id = ?');
$st->execute([$imageId]);
$img = $st->fetch();
if (!$img) {
    api_fail('Image not found', 404);
}

/** Every place an image can be referenced: [table, column, human label]. */
const SJ_IMAGE_REFS = [
    ['image_links',     'image_id',      'photo collection'],
    ['hero_slides',     'image_id',      'hero slide'],
    ['profiles',        'image_id',      'profile block'],
    ['unique_features', 'image_id',      "What's-Unique block"],
    ['update_slides',   'image_id',      'update slide'],
    ['sports',          'image_id',      'sport card'],
    ['achievements',    'image_id',      'achievement'],
    ['facilities',      'bg_image_id',   'facility background'],
    ['academies',       'card_image_id', 'academy card'],
    ['academies',       'bg_image_id',   'academy background'],
    ['school_sections', 'card_image_id', 'grade card'],
];

/** Usage summary for one image: [label => count]. */
function image_usage(int $id): array
{
    $usage = [];
    foreach (SJ_IMAGE_REFS as [$table, $col, $label]) {
        $st = db()->prepare("SELECT COUNT(*) FROM `$table` WHERE `$col` = ?");
        $st->execute([$id]);
        $n = (int)$st->fetchColumn();
        if ($n > 0) {
            $usage[$label] = ($usage[$label] ?? 0) + $n;
        }
    }
    return $usage;
}

switch ($act) {

case 'meta':
    $alt = trim((string)($in['alt_text'] ?? ''));
    if (mb_strlen($alt) > 255) {
        api_fail('Description too long (max 255)');
    }
    db()->prepare('UPDATE images SET alt_text = ? WHERE id = ?')->execute([$alt, $imageId]);
    sj_audit('image.meta', 'image', $imageId, 'alt_text');
    api_out();

case 'usage':
    api_out(['usage' => image_usage($imageId)]);

case 'delete':
    $usage = image_usage($imageId);
    if ($usage) {
        $parts = [];
        foreach ($usage as $label => $n) {
            $parts[] = "$n × $label";
        }
        api_fail('Cannot delete — used in: ' . implode(', ', $parts));
    }
    db()->prepare('DELETE FROM image_renditions WHERE image_id = ?')->execute([$imageId]);
    db()->prepare('DELETE FROM images WHERE id = ?')->execute([$imageId]);
    // uploaded files live in media/<id>/ — legacy photos stay on disk untouched
    if (empty($img['legacy_path'])) {
        $dir = sj_media_dir() . '/' . $imageId;
        foreach (glob($dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
    sj_audit('image.delete', 'image', $imageId, (string)($img['legacy_path'] ?: $img['original_name']));
    api_out();

default:
    api_fail('Unknown action');
}
