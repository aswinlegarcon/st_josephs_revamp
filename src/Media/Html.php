<?php

namespace SJ\Media;

/**
 * Rendering helpers for image rows (URL, <img>/<picture> markup, bg style).
 * Ported verbatim from _libs/media.php in R2; the global names img_url(),
 * img_tag() and bg_style() (src/helpers.php) delegate here.
 */
final class Html
{
    /**
     * F2 escape hatch: legacy presets that should keep serving the original
     * /photos file. Currently empty — the layout verifier proved every slot
     * renders identically with fit renditions in a plain <img> (the ONLY
     * measured breaker was the <picture> wrapper, see tag() below).
     */
    private const LEGACY_KEEP_ORIGINAL = [];

    /** Public URL for an image row in a given slot preset. */
    public static function url(?array $img, string $presetKey): string
    {
        if (!$img) {
            return '';
        }
        $id = (int)$img['id'];
        if (!empty($img['legacy_path'])) {
            // F2: prefer the backfilled rendition (small, sized to the slot).
            // Legacy renditions are made ONLY by database/backfill.php in
            // Docker — never lazily on the shared host — so a missing pair
            // simply falls back to the original /photos file, as before.
            if (!\in_array($presetKey, self::LEGACY_KEEP_ORIGINAL, true)
                && Pipeline::renditions($id, $presetKey)) {
                return '/media/' . $id . '/' . $presetKey . '.jpg?v=' . (int)($img['version'] ?? 1);
            }
            return $img['legacy_path'];
        }
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
        // Eager slots are the above-the-fold/LCP candidates (first hero slide):
        // tell the browser to fetch them first (F4).
        $lazy  = empty($attrs['eager']) ? ' loading="lazy"' : ' fetchpriority="high"';
        $extra = $attrs['extra'] ?? '';

        // F2: a legacy image WITHOUT backfilled renditions keeps today's plain
        // tag (original /photos file). With renditions it falls through to the
        // standard path — smaller JPEG + WebP <picture>.
        //
        // Legacy geometry contract: renditions are fit-mode (original aspect),
        // and the tag carries the ORIGINAL's width/height plus the sj-fit
        // class (tokens.css: img.sj-fit { height: auto }). Net effect per slot:
        //   · CSS-width slots  → height computes from the (unchanged) ratio;
        //   · natural-size slots → the attributes reproduce the original box;
        //   · CSS-sized slots  → their own rules still win (cascade order).
        // So every slot renders exactly the baseline box, with space reserved
        // before the image loads (zero layout shift).
        // F2 LEGACY RULE (measured, not theorised — see docs/learn/08-stage-h.md):
        //   · rendition present → plain <img> pointing at the fit-mode JPEG.
        //     NO <picture> wrapper: in the shipped flex rows the wrapper —
        //     not the styled img — becomes the flex item and the row wraps
        //     (this was the single real layout breaker the verifier caught).
        //     NO width/height attributes: with height attrs, slots styled
        //     "width only" pin the attr height and squash the photo.
        //   · no rendition → the original /photos file, exactly as before.
        // The .webp variants stay on disk for future redesigned slots.
        $isLegacy = !empty($img['legacy_path']);
        if ($isLegacy) {
            $url = \in_array($presetKey, self::LEGACY_KEEP_ORIGINAL, true)
                ? $img['legacy_path']
                : self::url($img, $presetKey); // rendition, else legacy_path
            return '<img src="' . e($url) . '"' . $class . $style . $alt . $lazy . ($extra ? ' ' . $extra : '') . '>';
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
