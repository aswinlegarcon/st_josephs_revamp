<?php
// POST multipart: file, preset, [alt], [crop_rect "x,y,w,h"]
// Full pipeline per FEATURES_PLAN.md §2: validate → EXIF fix → auto center-crop to the
// preset aspect (or the manual rect) → downscale → progressive JPEG + WebP.
require __DIR__ . '/_bootstrap.php';

if (empty($_FILES['file'])) {
    api_fail('No file uploaded');
}
$preset = (string)($_POST['preset'] ?? '');
$alt    = trim(strip_tags((string)($_POST['alt'] ?? '')));
$crop   = isset($_POST['crop_rect']) ? (string)$_POST['crop_rect'] : null;

try {
    $img = media_process_upload($_FILES['file'], $preset, mb_substr($alt, 0, 255), $crop);
} catch (RuntimeException $ex) {
    api_fail($ex->getMessage());
}

sj_audit('upload', 'image', (int)$img['id']);
api_out([
    'image_id' => (int)$img['id'],
    'url'      => img_url($img, $preset),
    'name'     => $img['original_name'],
]);
