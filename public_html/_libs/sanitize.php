<?php
// Server-side HTML whitelist for *_html fields (DYNAMIC_MIGRATION_PLAN.md §5.5).
// Allowed: b, strong, i, em, br, p, span (class="hl-gold" only). Everything else is
// stripped; all attributes are dropped except the hl-gold class on span.

function sj_sanitize_html(string $html): string
{
    // Normalise contenteditable output: <div> blocks become paragraphs, &nbsp; becomes space.
    $html = preg_replace('/<\s*div\b[^>]*>/i', '<p>', $html);
    $html = preg_replace('/<\s*\/\s*div\s*>/i', '</p>', $html);
    $html = str_ireplace('&nbsp;', ' ', $html);
    $html = preg_replace('/<p>\s*<\/p>/i', '', $html); // drop empty paragraphs

    $html = strip_tags($html, '<b><strong><i><em><br><p><span>');

    // Drop every attribute; keep class="hl-gold" on span when it was present.
    $html = preg_replace_callback(
        '/<\s*(b|strong|i|em|p|br|span)\b([^>]*)>/i',
        function ($m) {
            $tag = strtolower($m[1]);
            if ($tag === 'span' && stripos($m[2], 'hl-gold') !== false) {
                return '<span class="hl-gold">';
            }
            if ($tag === 'br') {
                return '<br>';
            }
            return '<' . $tag . '>';
        },
        $html
    );

    // Normalise closing tags (strip stray attributes/whitespace).
    $html = preg_replace('/<\s*\/\s*(b|strong|i|em|p|span)\s*>/i', '</$1>', $html);

    return trim($html);
}
