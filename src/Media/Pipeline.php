<?php

namespace SJ\Media;

use RuntimeException;
use Throwable;

/**
 * Image pipeline (GD). Behaviour per FEATURES_PLAN.md §2: auto center-crop to
 * the slot preset's aspect, downscale only, progressive JPEG (~q82) + WebP when
 * GD supports it, original kept. Legacy photos/ files are served as-is via
 * images.legacy_path and are never modified. Ported verbatim from
 * _libs/media.php in R2; the media_* global names delegate here.
 */
final class Pipeline
{
    public static function dir(): string
    {
        return SJ_PUBLIC_ROOT . '/media';
    }

    public static function preset(string $key): ?array
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            foreach (db()->query('SELECT * FROM image_presets') as $p) {
                $cache[$p['preset_key']] = $p;
            }
        }
        return $cache[$key] ?? null;
    }

    /** Rendition rows for one image+preset, keyed by format. */
    public static function renditions(int $imageId, string $presetKey, bool $refresh = false): array
    {
        static $cache = [];
        $k = $imageId . ':' . $presetKey;
        if ($refresh) {
            unset($cache[$k]);
        }
        if (!\array_key_exists($k, $cache)) {
            $st = db()->prepare('SELECT * FROM image_renditions WHERE image_id = ? AND preset_key = ?');
            $st->execute([$imageId, $presetKey]);
            $out = [];
            foreach ($st->fetchAll() as $r) {
                $out[$r['format']] = $r;
            }
            $cache[$k] = $out;
        }
        return $cache[$k];
    }

    public static function extForMime(string $mime): string
    {
        return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? 'bin';
    }

    /**
     * Validate + process an uploaded file into a new immutable images row with
     * renditions for $presetKey. Returns the fresh images row.
     * @throws RuntimeException on validation/processing failure (message is user-safe)
     */
    public static function processUpload(array $file, string $presetKey, string $alt = '', ?string $cropRect = null): array
    {
        $maxBytes = sj_config()['upload_max_bytes'];
        if (!isset($file['error']) || $file['error'] !== \UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed (error code ' . ($file['error'] ?? '?') . ').');
        }
        if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
            throw new RuntimeException('File too large (max ' . \round($maxBytes / 1048576) . ' MB).');
        }
        $tmp = $file['tmp_name'];
        if (\PHP_SAPI !== 'cli' && !\is_uploaded_file($tmp)) {
            throw new RuntimeException('Invalid upload.');
        }

        $finfoMime = (new \finfo(\FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        $info = @\getimagesize($tmp);
        if ($info === false) {
            throw new RuntimeException('File is not a valid image.');
        }
        $gisMime = $info['mime'] ?? '';
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!\in_array($finfoMime, $allowed, true) || $finfoMime !== $gisMime) {
            throw new RuntimeException('Only JPEG, PNG or WebP images are allowed.');
        }
        [$w, $h] = $info;
        if ($w > 8000 || $h > 8000) {
            throw new RuntimeException('Image dimensions too large (max 8000×8000).');
        }

        $preset = self::preset($presetKey);
        if ($preset === null) {
            throw new RuntimeException('Unknown image preset.');
        }

        $origName = \preg_replace('/[^A-Za-z0-9._ -]/', '', (string)($file['name'] ?? 'upload'));
        $pdo = db();
        $pdo->prepare(
            'INSERT INTO images (original_name, alt_text, mime, width, height, preset_key, crop_rect)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([$origName, $alt, $finfoMime, $w, $h, $presetKey, $cropRect]);
        $id = (int)$pdo->lastInsertId();

        $dir = self::dir() . '/' . $id;
        try {
            if (!\is_dir($dir) && !\mkdir($dir, 0775, true)) {
                throw new RuntimeException('Cannot create media directory.');
            }
            $origPath = $dir . '/original.' . self::extForMime($finfoMime);
            $moved = (\PHP_SAPI === 'cli') ? \copy($tmp, $origPath) : \move_uploaded_file($tmp, $origPath);
            if (!$moved) {
                throw new RuntimeException('Cannot store the uploaded file.');
            }
            @\chmod($origPath, 0644);
            self::generate($origPath, $id, $preset, self::parseCrop($cropRect, $w, $h));
        } catch (Throwable $ex) {
            // rollback: remove files + row
            if (\is_dir($dir)) {
                foreach (\glob($dir . '/*') ?: [] as $f) {
                    @\unlink($f);
                }
                @\rmdir($dir);
            }
            $pdo->prepare('DELETE FROM images WHERE id = ?')->execute([$id]);
            throw $ex instanceof RuntimeException ? $ex : new RuntimeException('Image processing failed.');
        }

        $row = db()->prepare('SELECT * FROM images WHERE id = ?');
        $row->execute([$id]);
        return $row->fetch();
    }

    /** Parse "x,y,w,h" into a bounds-checked crop rect (or null). */
    public static function parseCrop(?string $rect, int $srcW, int $srcH): ?array
    {
        if (!$rect) {
            return null;
        }
        $p = \array_map('intval', \explode(',', $rect));
        if (\count($p) !== 4) {
            return null;
        }
        [$x, $y, $w, $h] = $p;
        if ($x < 0 || $y < 0 || $w < 8 || $h < 8 || $x + $w > $srcW || $y + $h > $srcH) {
            return null;
        }
        return ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
    }

    /** Generate JPEG (+WebP) renditions of $src for $preset into media/{id}/. */
    public static function generate(string $src, int $imageId, array $preset, ?array $crop = null): void
    {
        $info = \getimagesize($src);
        if ($info === false) {
            throw new RuntimeException('Cannot read source image.');
        }
        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg': $gd = \imagecreatefromjpeg($src); break;
            case 'image/png':  $gd = \imagecreatefrompng($src);  break;
            case 'image/webp': $gd = \imagecreatefromwebp($src); break;
            default: throw new RuntimeException('Unsupported image type.');
        }
        if (!$gd) {
            throw new RuntimeException('Cannot decode image.');
        }
        if ($mime === 'image/jpeg') {
            $gd = self::fixOrientation($gd, $src);
        }
        $w = \imagesx($gd);
        $h = \imagesy($gd);

        // Crop rectangle: manual (validated) or automatic largest centered rect at preset aspect.
        if ($preset['mode'] === 'cover' && $preset['aspect_w'] && $preset['aspect_h']) {
            if ($crop === null) {
                $r = $preset['aspect_w'] / $preset['aspect_h'];
                if ($w / $h > $r) {
                    $ch = $h;
                    $cw = (int)\round($h * $r);
                } else {
                    $cw = $w;
                    $ch = (int)\round($w / $r);
                }
                $crop = ['x' => (int)\floor(($w - $cw) / 2), 'y' => (int)\floor(($h - $ch) / 2), 'w' => $cw, 'h' => $ch];
            }
        } else {
            $crop = null; // fit mode: no cropping
        }
        $sx = $crop['x'] ?? 0;
        $sy = $crop['y'] ?? 0;
        $sw = $crop['w'] ?? $w;
        $sh = $crop['h'] ?? $h;

        // Destination size: downscale only, never upscale.
        $scale = \min(1, $preset['max_w'] / $sw, $preset['max_h'] / $sh);
        $dw = \max(1, (int)\round($sw * $scale));
        $dh = \max(1, (int)\round($sh * $scale));

        $dst = \imagecreatetruecolor($dw, $dh);
        // Flatten transparency onto white (output formats here are photo-oriented).
        \imagefill($dst, 0, 0, \imagecolorallocate($dst, 255, 255, 255));
        \imagecopyresampled($dst, $gd, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh);
        \imagedestroy($gd);

        $dir = self::dir() . '/' . $imageId;
        if (!\is_dir($dir) && !\mkdir($dir, 0775, true)) {
            throw new RuntimeException('Cannot create media directory.');
        }
        $q = (int)$preset['quality'];
        $jpgPath = $dir . '/' . $preset['preset_key'] . '.jpg';
        \imageinterlace($dst, true);
        if (!\imagejpeg($dst, $jpgPath, $q)) {
            throw new RuntimeException('Cannot write JPEG rendition.');
        }
        @\chmod($jpgPath, 0644);
        $rows = [['jpeg', $jpgPath]];

        if (\function_exists('imagewebp')) {
            $webpPath = $dir . '/' . $preset['preset_key'] . '.webp';
            if (\imagewebp($dst, $webpPath, \max(1, $q - 2))) {
                @\chmod($webpPath, 0644);
                $rows[] = ['webp', $webpPath];
            }
        }
        \imagedestroy($dst);

        $st = db()->prepare(
            'REPLACE INTO image_renditions (image_id, preset_key, format, width, height, bytes) VALUES (?,?,?,?,?,?)'
        );
        foreach ($rows as [$fmt, $path]) {
            $st->execute([$imageId, $preset['preset_key'], $fmt, $dw, $dh, (int)\filesize($path)]);
        }
    }

    /** Make sure an uploaded image has renditions for $presetKey (lazy generation). */
    public static function ensureRendition(array $img, string $presetKey): bool
    {
        if (!empty($img['legacy_path'])) {
            return false; // legacy files are served as-is
        }
        $id = (int)$img['id'];
        if (self::renditions($id, $presetKey)) {
            return true;
        }
        $preset = self::preset($presetKey);
        if ($preset === null) {
            return false;
        }
        $orig = self::dir() . '/' . $id . '/original.' . self::extForMime((string)$img['mime']);
        if (!\is_file($orig)) {
            return false;
        }
        try {
            self::generate($orig, $id, $preset, null);
        } catch (Throwable $ex) {
            return false;
        }
        self::renditions($id, $presetKey, true); // refresh the per-request cache
        return true;
    }

    /** @param resource|\GdImage $gd */
    public static function fixOrientation($gd, string $path)
    {
        if (!\function_exists('exif_read_data')) {
            return $gd;
        }
        $exif = @\exif_read_data($path);
        switch ((int)($exif['Orientation'] ?? 1)) {
            case 3: $r = \imagerotate($gd, 180, 0); break;
            case 6: $r = \imagerotate($gd, -90, 0); break;
            case 8: $r = \imagerotate($gd, 90, 0);  break;
            default: return $gd;
        }
        if ($r) {
            \imagedestroy($gd);
            return $r;
        }
        return $gd;
    }
}
