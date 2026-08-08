<?php

namespace SJ\Media;

/**
 * Rendering helpers for image rows (URL, <img>/<picture> markup, bg style).
 * Ported verbatim from _libs/media.php in R2; the global names img_url(),
 * img_tag() and bg_style() (src/helpers.php) delegate here.
 */
final class Html
{
    /** Public URL for an image row in a given slot preset. */
    public static function url(?array $img, string $presetKey): string
    {
        if (!$img) {
            return '';
        }
        if (!empty($img['legacy_path'])) {
            return $img['legacy_path'];
        }
        $id = (int)$img['id'];
        if (Pipeline::ensureRendition($img, $presetKey)) {
            return '/media/' . $id . '/' . $presetKey . '.jpg?v=' . (int)($img['version'] ?? 1);
        }
        return '/media/' . $id . '/original.' . Pipeline::extForMime((string)$img['mime']);
    }

    /** <img>/<picture> markup for an image row in a slot. $attrs: class, alt, eager, style, extra. */
    public static function tag(?array $img, string $presetKey, array $attrs = []): string
    {
        if (!$img) {
            return '';
        }
        $class = isset($attrs['class']) ? ' class="' . e($attrs['class']) . '"' : '';
        $style = isset($attrs['style']) ? ' style="' . e($attrs['style']) . '"' : '';
        $alt   = ' alt="' . e($attrs['alt'] ?? ($img['alt_text'] ?? '')) . '"';
        $lazy  = empty($attrs['eager']) ? ' loading="lazy"' : '';
        $extra = $attrs['extra'] ?? '';

        if (!empty($img['legacy_path'])) {
            return '<img src="' . e($img['legacy_path']) . '"' . $class . $style . $alt . $lazy . ($extra ? ' ' . $extra : '') . '>';
        }

        $id   = (int)$img['id'];
        $v    = (int)($img['version'] ?? 1);
        $url  = self::url($img, $presetKey);
        $dims = '';
        $rend = Pipeline::renditions($id, $presetKey);
        if (isset($rend['jpeg'])) {
            $dims = ' width="' . (int)$rend['jpeg']['width'] . '" height="' . (int)$rend['jpeg']['height'] . '"';
        }
        $imgTag = '<img src="' . e($url) . '"' . $dims . $class . $style . $alt . $lazy . ($extra ? ' ' . $extra : '') . '>';
        if (isset($rend['webp']) && \is_file(Pipeline::dir() . "/$id/$presetKey.webp")) {
            return '<picture><source type="image/webp" srcset="' . e("/media/$id/$presetKey.webp?v=$v") . '">' . $imgTag . '</picture>';
        }
        return $imgTag;
    }

    /** Inline background-image style for CSS-background slots. */
    public static function bgStyle(?array $img, string $presetKey): string
    {
        if (!$img) {
            return '';
        }
        return "background-image:url('" . e(self::url($img, $presetKey)) . "');";
    }
}
