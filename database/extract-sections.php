<?php
// DEV-ONLY (C4). One-time extractor: parses the four static section views
// (views/pages/{kg,primary,highschl,highsec}.php as of R1b) and generates
// database/seed-data/sections.php — the byte-faithful content the seeder
// loads. Committed for provenance; re-run only if the static views change.
// Usage: php database/extract-sections.php

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

$root  = dirname(__DIR__);
$pages = [
    // slug => [section name (events-heading <span>), events-heading prefix]
    'kg'       => ['Kinder Garten',    'Events of'],
    'primary'  => ['Primary School',   'Exams and Events of'],
    'highschl' => ['High School',      'Exams and Events of'],
    'highsec'  => ['Higher Secondary', 'Exams and Events of'],
];
// Grade cards shown on academics.php (C8) — same rows, extracted by eye from
// views/pages/academics.php (static, four cards).
$cards = [
    'kg'       => ['Kinder Garten(KG)', 'LKG-UKG',     'kg1.jpg'],
    'primary'  => ['Primary School',    '1st - 5th',   'primary4.jpg'],
    'highschl' => ['High School',       '6th - 10th',  'high1.jpg'],
    'highsec'  => ['Higher Secondary',  '11th - 12th', 'highsec1.jpg'],
];

function inner_html(DOMNode $n): string
{
    $out = '';
    foreach ($n->childNodes as $c) {
        $out .= $n->ownerDocument->saveHTML($c);
    }
    return $out;
}

function txt(DOMNode $n): string
{
    return trim($n->textContent);
}

$data = [];
foreach ($pages as $slug => [$name, $headingPrefix]) {
    $raw = file_get_contents("$root/views/pages/$slug.php");
    $raw = preg_replace('/<\?php.*?\?>/s', '', $raw); // strip the PHP include lines (highsec)
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="__root">' . $raw . '</div>');
    libxml_clear_errors();
    $xp = new DOMXPath($doc);

    // 1. hero slides (first carousel on the page)
    $hero = [];
    $heroInner = $xp->query('//div[contains(@id,"HeroCarousel")]//div[contains(@class,"carousel-item")]');
    foreach ($heroInner as $item) {
        $img = $xp->query('.//img', $item)->item(0);
        $h5  = $xp->query('.//h5', $item)->item(0);
        $p   = $xp->query('.//p', $item)->item(0);
        $src = $img->getAttribute('src');
        $hero[] = [basename($src), $h5 ? txt($h5) : '', $p ? txt($p) : ''];
    }

    // 2. inner carousel images (the {slug}Carousel inside .infra-new)
    $carousel = [];
    foreach ($xp->query('//div[@id="' . $slug . 'Carousel"]//div[contains(@class,"carousel-item")]//img') as $img) {
        $carousel[] = basename($img->getAttribute('src'));
    }

    // 3. intro: .infra-new-text h4 + its <p> siblings before the accordion
    $h4 = $xp->query('//div[contains(@class,"infra-new-text")]/h4')->item(0);
    $introHeading = $h4 ? txt($h4) : $name;
    $introParas = [];
    foreach ($xp->query('//div[contains(@class,"infra-new-text")]/p') as $p) {
        $introParas[] = '<p>' . inner_html($p) . '</p>';
    }
    $introHtml = implode("\n", $introParas);

    // 4. timeline entries
    $timeline = [];
    foreach ($xp->query('//ul[contains(@class,"timeline")]/li') as $li) {
        $flag = $xp->query('.//span[@class="flag"]', $li)->item(0);
        $time = $xp->query('.//span[@class="time"]', $li)->item(0);
        $desc = $xp->query('.//div[@class="desc"]', $li)->item(0);
        if (!$flag || !$desc) {
            continue;
        }
        // desc lines are separated by <br>; keep each line's inner text verbatim
        $lines = [];
        foreach (preg_split('/<br\s*\/?>/i', inner_html($desc)) as $line) {
            $line = trim(html_entity_decode(strip_tags($line), ENT_QUOTES, 'UTF-8'));
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        $timeline[] = [txt($flag), $time ? txt($time) : '2024 - present', implode("\n", $lines)];
    }

    // 5. events (about-sections inside .newtemp-body)
    $events = [];
    foreach ($xp->query('//section[contains(@class,"newtemp-body")]//div[contains(@class,"about-section")]') as $sec) {
        $h2  = $xp->query('.//h2', $sec)->item(0);
        $img = $xp->query('.//img', $sec)->item(0);
        $paras = [];
        foreach ($xp->query('.//div[contains(@class,"about-content")]/p', $sec) as $p) {
            $paras[] = '<p>' . inner_html($p) . '</p>';
        }
        $events[] = [$h2 ? txt($h2) : '', implode("\n", $paras), $img ? basename($img->getAttribute('src')) : null];
    }

    // 6. timeline accordion label
    $btn = $xp->query('//div[contains(@class,"accordion-main")]//button')->item(0);
    $timelineHeading = $btn ? txt($btn) : 'Timeline - 2024';

    $data[$slug] = [
        'name'             => $name,
        'events_heading'   => $headingPrefix,
        'intro_heading'    => $introHeading,
        'intro_html'       => $introHtml,
        'timeline_heading' => $timelineHeading,
        'card_title'       => $cards[$slug][0],
        'card_range'       => $cards[$slug][1],
        'card_image'       => $cards[$slug][2],
        'hero'             => $hero,
        'carousel'         => $carousel,
        'timeline'         => $timeline,
        'events'           => $events,
    ];
    fwrite(STDERR, sprintf(
        "%-9s hero=%d carousel=%d intro_paras=%d timeline=%d events=%d\n",
        $slug, count($hero), count($carousel), count($introParas), count($timeline), count($events)
    ));
}

@mkdir("$root/database/seed-data", 0775, true);
file_put_contents(
    "$root/database/seed-data/sections.php",
    "<?php\n// GENERATED by database/extract-sections.php (C4) from the static R1b views.\n// Byte-faithful shipped content — edit via the admin panel, not here.\nreturn " . var_export($data, true) . ";\n"
);
echo "written: database/seed-data/sections.php\n";
