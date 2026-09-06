<?php
// POST multipart: file — replaces the school-diary PDF that the About page's
// Rules block links to (K11). Deliberately narrow (SEC-05/06): PDF only,
// content-sniffed (magic bytes + finfo), stored under a FIXED server-side name
// so no user-controlled string ever becomes a path, and media/.htaccess keeps
// the whole tree non-executable. On success the `diary_url` setting is updated
// in the same request, so the public Download link goes live with no extra Save.
require __DIR__ . '/_bootstrap.php';

if (empty($_FILES['file'])) {
    api_fail('No file uploaded');
}
$f = $_FILES['file'];
if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'] ?? '')) {
    api_fail('Upload failed — please try again');
}
$max = (int)(sj_config()['upload_max_bytes'] ?? 10 * 1024 * 1024);
if ((int)$f['size'] > $max) {
    api_fail('File too large (max ' . round($max / 1048576) . ' MB)');
}

// Trust the CONTENT, never the filename: both the leading magic bytes and
// finfo's MIME sniff must agree it is a PDF.
$head = (string)@file_get_contents($f['tmp_name'], false, null, 0, 5);
$mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
if ($head !== '%PDF-' || $mime !== 'application/pdf') {
    api_fail('The diary must be a PDF file');
}

$dir = SJ_PUBLIC_ROOT . '/media/files';
if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
    api_fail('Server could not create media/files', 500);
}
$dest = $dir . '/diary.pdf'; // fixed name: uploading again replaces the diary
if (!@move_uploaded_file($f['tmp_name'], $dest)) {
    api_fail('Server could not store the file', 500);
}
@chmod($dest, 0644);

// Point the About-page Download link here; ?v= (mtime) busts the 1-year
// static cache when a new diary replaces the old one at the same path.
$url = '/media/files/diary.pdf?v=' . (int)@filemtime($dest);
db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)')
    ->execute(['diary_url', $url]);

sj_audit('upload', 'diary', null, mb_substr((string)($f['name'] ?? 'diary.pdf'), 0, 120));
api_out(['url' => $url]);
