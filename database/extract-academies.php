<?php
// DEV-ONLY (C5). One-time extractor: parses the 18 static academy-family views
// (R1b state) + the co-curriculum card grid and generates
// database/seed-data/academies.php. Byte-faithful EXCEPT the six broken image
// references (bugs 1/2): views said .jpg where the file on disk is .jpeg —
// resolved here to the existing file so the photos actually load (PHASES C5).
// Usage: php database/extract-academies.php

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$root  = dirname(__DIR__);
$slugs = [
    'tamilacademy', 'englishacademy', 'mathsacademy', 'scienceacademy', 'socialacademy',
    'langacademy', 'communicativeacademy', 'abacusacademy', 'vocalacademy', 'instrumentacademy',
    'danceacademy', 'artacademy', 'martialacademy', 'yogaacademy', 'sportsacademy',
    'band', 'ncc', 'artandexpo',
];

function inner_html(DOMNode $n): string
{
    $out = '';
    foreach ($n->childNodes as $c) {
        $out .= $n->ownerDocument->saveHTML($c);
    }
    return $out;
}

/** Resolve an image basename to a file that EXISTS (bugs 1/2: .jpg ↔ .jpeg). */
function resolve_img(string $root, string $file): string
{
    // webroot is public_html/ on the host, html/ inside the Docker container
    $dirs = ["$root/public_html/photos", "$root/html/photos"];
    foreach ($dirs as $d) {
        if (is_file("$d/$file")) {
            return $file;
        }
    }
    $alt = preg_match('/\.jpg$/i', $file)
        ? preg_replace('/\.jpg$/i', '.jpeg', $file)
        : preg_replace('/\.jpeg$/i', '.jpg', $file);
    foreach ($dirs as $d) {
        if (is_file("$d/$alt")) {
            fwrite(STDERR, "  bug-1/2 fix: $file -> $alt\n");
            return $alt;
        }
    }
    fwrite(STDERR, "  UNRESOLVED image: $file\n");
    return $file;
}

$data = [];
foreach ($slugs as $slug) {
    $raw = file_get_contents("$root/views/pages/$slug.php");
    $raw = preg_replace('/<\?php.*?\?>/s', '', $raw);

    // bg image from the per-page .bg-1 style
    preg_match("#\.bg-1\s*\{[^}]*url\('/photos/([^']+)'\)#", $raw, $m);
    $bg = resolve_img($root, $m[1] ?? '');

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $raw . '</div>');
    libxml_clear_errors();
    $xp = new DOMXPath($doc);

    $bt = $xp->query('//section[contains(@class,"back-bar")]//h4')->item(0);
    $bs = $xp->query('//section[contains(@class,"back-bar")]//p')->item(0);
    $ch = $xp->query('//div[contains(@class,"infra-new-text")]/h4')->item(0);

    $paras = [];
    foreach ($xp->query('//div[contains(@class,"infra-new-text")]/p') as $p) {
        $paras[] = '<p' . ($p->hasAttribute('class') ? ' class="' . $p->getAttribute('class') . '"' : '') . '>' . inner_html($p) . '</p>';
    }

    $carousel = [];
    foreach ($xp->query('//div[contains(@class,"carousel-inner")]//img') as $img) {
        $carousel[] = resolve_img($root, basename($img->getAttribute('src')));
    }

    $data[$slug] = [
        'banner_title'    => $bt ? trim($bt->textContent) : '',
        'banner_subtitle' => $bs ? trim($bs->textContent) : '',
        'content_heading' => $ch ? trim($ch->textContent) : '',
        'body_html'       => implode("\n    ", $paras),
        'bg_image'        => $bg,
        'carousel'        => $carousel,
        // card_* filled from the co-curriculum grid below; banner values as fallback
        'card_title'      => $bt ? trim($bt->textContent) : '',
        'card_subtitle'   => $bs ? trim($bs->textContent) : '',
        'card_image'      => $bg,
    ];
    fwrite(STDERR, sprintf("%-22s carousel=%d paras=%d bg=%s\n", $slug, count($carousel), count($paras), $bg));
}

// co-curriculum grid: card image/title/subtitle per academy (keyed by href)
$raw = preg_replace('/<\?php.*?\?>/s', '', file_get_contents("$root/views/pages/co-curriculum.php"));
$doc = new DOMDocument();
libxml_use_internal_errors(true);
$doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $raw . '</div>');
libxml_clear_errors();
$xp = new DOMXPath($doc);
$gridOrder = [];
foreach ($xp->query('//div[contains(@class,"card")][.//a[contains(@href,"academy") or contains(@href,"band") or contains(@href,"ncc") or contains(@href,"artandexpo")]]') as $card) {
    $a = $xp->query('.//a[@href]', $card)->item(0);
    if (!$a) {
        continue;
    }
    $slug = preg_replace('/\.php$/', '', basename($a->getAttribute('href')));
    if (!isset($data[$slug]) || in_array($slug, $gridOrder, true)) {
        continue;
    }
    $img = $xp->query('.//img', $card)->item(0);
    $t   = $xp->query('.//h3', $card)->item(0);
    $sub = $xp->query('.//p[contains(@class,"card-text")]', $card)->item(0);
    if ($img) {
        $data[$slug]['card_image'] = resolve_img($root, basename($img->getAttribute('src')));
    }
    if ($t) {
        $data[$slug]['card_title'] = trim($t->textContent);
    }
    if ($sub) {
        $data[$slug]['card_subtitle'] = trim($sub->textContent);
    }
    $gridOrder[] = $slug;
}
fwrite(STDERR, 'co-curriculum grid order: ' . implode(', ', $gridOrder) . "\n");

// display order = co-curriculum grid order, then any academy not in the grid
$ordered = [];
foreach ($gridOrder as $slug) {
    $ordered[$slug] = $data[$slug];
}
foreach ($data as $slug => $row) {
    if (!isset($ordered[$slug])) {
        $ordered[$slug] = $row;
    }
}

@mkdir("$root/database/seed-data", 0775, true);
file_put_contents(
    "$root/database/seed-data/academies.php",
    "<?php\n// GENERATED by database/extract-academies.php (C5) from the static R1b views\n// + the co-curriculum grid. Byte-faithful except the six .jpg->.jpeg bug-1/2\n// fixes. Edit via the admin panel, not here.\nreturn " . var_export($ordered, true) . ";\n"
);
echo "written: database/seed-data/academies.php (" . count($ordered) . " academies)\n";
