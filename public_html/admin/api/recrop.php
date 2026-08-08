<?php
// POST {image_id, crop_rect:"x,y,w,h"} → re-render an UPLOADED image's
// renditions from its stored original with a new crop, then bump `version`
// so every rendition URL changes (M3).
require __DIR__ . '/_bootstrap.php';

$in      = api_input();
$imageId = (int)($in['image_id'] ?? 0);
$rectStr = (string)($in['crop_rect'] ?? '');

$st = db()->prepare('SELECT * FROM images WHERE id = ?');
$st->execute([$imageId]);
$img = $st->fetch();
if (!$img) {
    api_fail('Image not found', 404);
}
if (!empty($img['legacy_path']) || empty($img['preset_key'])) {
    api_fail('Only uploaded images can be re-cropped');
}

$preset = media_preset((string)$img['preset_key']);
if ($preset === null) {
    api_fail('Unknown image preset');
}
if (($preset['mode'] ?? '') !== 'cover') {
    api_fail('This image type has no crop (fit mode)');
}

$crop = media_parse_crop($rectStr, (int)$img['width'], (int)$img['height']);
if ($crop === null) {
    api_fail('Invalid crop rectangle');
}

$orig = sj_media_dir() . '/' . $imageId . '/original.' . sj_ext_for_mime((string)$img['mime']);
if (!is_file($orig)) {
    api_fail('Original file is missing', 404);
}

media_generate($orig, $imageId, $preset, $crop);
db()->prepare('UPDATE images SET crop_rect = ?, version = version + 1 WHERE id = ?')
    ->execute([$rectStr, $imageId]);

sj_audit('image.recrop', 'image', $imageId, $rectStr);
$v = (int)$img['version'] + 1;
api_out([
    'version' => $v,
    'thumb'   => '/media/' . $imageId . '/' . $img['preset_key'] . '.jpg?v=' . $v,
]);
