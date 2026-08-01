<?php
// CLI seeder — idempotent and NON-DESTRUCTIVE:
//   • keyed rows (pages, profiles, mark_years, settings, admin user) → insert only if missing
//   • list content (hero slides, ticker, updates, features, mark entries) → seeded only when
//     the list is EMPTY, so admin edits are never overwritten by a re-run.
// Usage: php database/seed.php   (run.sh does this on every start)

if (PHP_SAPI !== 'cli') {
    exit("CLI only\n");
}

foreach ([dirname(__DIR__) . '/public_html', '/var/www/html'] as $root) {
    if (is_file($root . '/_libs/load.php')) {
        define('SJ_PUBLIC_ROOT', $root);
        require $root . '/_libs/load.php';
        break;
    }
}
if (!defined('SJ_PUBLIC_ROOT')) {
    fwrite(STDERR, "Cannot locate public_html/_libs/load.php\n");
    exit(1);
}

$pdo = db();
$out = [];

/* ---------- 1. Register every photos/ file as a legacy image ---------- */
$photoDir = SJ_PUBLIC_ROOT . '/photos';
$ins = $pdo->prepare('INSERT IGNORE INTO images (legacy_path, original_name, mime, width, height) VALUES (?,?,?,?,?)');
$registered = 0;
foreach (scandir($photoDir) as $f) {
    $path = $photoDir . '/' . $f;
    if ($f[0] === '.' || !is_file($path)) {
        continue;
    }
    $info = @getimagesize($path);
    $ins->execute(['/photos/' . $f, $f, $info['mime'] ?? null, $info[0] ?? null, $info[1] ?? null]);
    $registered += $ins->rowCount();
}
$out[] = "images: +$registered newly registered";

function img_id_by_file(string $file): int
{
    static $st = null;
    $st = $st ?: db()->prepare('SELECT id FROM images WHERE legacy_path = ?');
    $st->execute(['/photos/' . $file]);
    $id = $st->fetchColumn();
    if (!$id) {
        fwrite(STDERR, "MISSING PHOTO: photos/$file — seed aborted (existence check per plan §6.1)\n");
        exit(1);
    }
    return (int)$id;
}

/* ---------- 2. Admin account (admin / admin123) ---------- */
$exists = $pdo->query("SELECT COUNT(*) FROM admin_users WHERE username = 'admin'")->fetchColumn();
if (!$exists) {
    // Password source: SEED_ADMIN_PASS env → random (with --prod) → dev default.
    // Whatever it is, must_change_password=1 forces a rotation on first login,
    // so the seeded credential is never usable long-term (SECURITY.md SEC-08).
    $envPass = getenv('SEED_ADMIN_PASS');
    $prod    = in_array('--prod', $argv, true);
    if ($envPass !== false && $envPass !== '') {
        $pass = $envPass;
    } elseif ($prod) {
        $pass = bin2hex(random_bytes(9)); // 18 hex chars, shown once below
    } else {
        $pass = 'admin123';               // local dev only
    }
    $pdo->prepare('INSERT INTO admin_users (username, password_hash, display_name, must_change_password) VALUES (?,?,?,1)')
        ->execute(['admin', password_hash($pass, PASSWORD_DEFAULT), 'Administrator']);
    $out[] = "admin user created (admin / $pass) — MUST change password on first login";
} else {
    $out[] = 'admin user already present';
}

/* ---------- 3. Home page row ---------- */
$pdo->prepare('INSERT IGNORE INTO pages (slug, title, heading_html) VALUES (?,?,?)')->execute([
    'index',
    "St.Joseph's MHSS, Ondipudur",
    "Welcome to <span>St.Joseph's</span> Matric Higher Secondary School",
]);
$pageId = (int)$pdo->query("SELECT id FROM pages WHERE slug = 'index'")->fetchColumn();

/* ---------- 4. Hero slides (7, from _templates/carousel.php) ---------- */
$count = $pdo->query("SELECT COUNT(*) FROM hero_slides WHERE page_id = $pageId")->fetchColumn();
if (!$count) {
    $slides = ['sportsday20.jpg', 'sports.jpeg', 'ann3.jpg', 'expressday1.jpg', 'sportsday40.jpg', 'carosel1.jpg', 'kg-boys.jpg'];
    $st = $pdo->prepare('INSERT INTO hero_slides (page_id, image_id, caption_title, caption_text, button_label, button_url, position) VALUES (?,?,?,?,?,?,?)');
    foreach ($slides as $i => $file) {
        $st->execute([$pageId, img_id_by_file($file), "St.Joseph's",
            'Matric Higher Secondary School, Ondipudur, Coimbatore - 641016', 'Explore', 'about.php', $i]);
    }
    $out[] = 'hero_slides: seeded ' . count($slides);
}

/* ---------- 5. Principal profile (index.php about block) ---------- */
$principalMsg = "<p>My dear Students, When a celebrated personality such as Nelson Mandela vouches for it, can anyone on the face of the earth disagree with it? As a matter of fact, education does play a vital role in every individual's life . Therefore your future life greatly depends on the kind of education you receive. You should be grateful to God and to your parents for entrusting you to this prestigious school . Now it is your turn to prove your worth. At this juncture, it is appropriate to reflect on the type of education that your are pursuing. What is true education? Is it just about getting high marks , good grades and recognized degrees? Or is it more than these? Great educationists are of the opinion that true education is gaining of greater knowledge and seasoned wisdom . In simple words, Knowledge is piling up of facts and wisdom is simplifying it. Knowledge is information. It is potential power and wisdom is real power . So you should try to acquire this kind of knowledge and not just scoring high marks.</p>";
$pdo->prepare('INSERT IGNORE INTO profiles (role_key, heading, person_name, message_html, image_id) VALUES (?,?,?,?,?)')
    ->execute(['principal', 'Principal', 'Rev.Fr.Kirubakaranathan', $principalMsg, img_id_by_file('princ1.jpg')]);

/* ---------- 6. What's Unique blocks ---------- */
if (!$pdo->query('SELECT COUNT(*) FROM unique_features')->fetchColumn()) {
    $esc = "<p>Launched in the 2023-24 academic year, this program supports working parents by providing additional coaching and activities for their children. The schedule is as follows: <br></p>"
         . "<p>- 3:30 PM - 5:15 PM: Extra-curricular activities <br> - 5:15 PM - 5:30 PM: Snack break <br> - 5:30 PM - 7:30 PM: Study hours <br></p>"
         . "<p><strong>Objectives</strong><br> - Improve independent study habits <br> - Enhance academic achievement and test scores <br></p>"
         . "<p><strong>Parental Involvement</strong><br> - Daily Tutor Meetings: Parents receive daily feedback on their child's progress. <br> - Monthly Principal Meetings: Parents provide suggestions for program improvement. <br></p>";
    $lang = "<p>Focusing foreign language Understanding the Global needs of foreign languages, we inculcated foreign languages and transforming power to students. <strong>French, Spanish and German languages</strong> are Designed for 6,7,and 8th grades . it is to thrive to enhances the cognitive developments. We aim to foster a love for languages and Our Mentors employ innovative techniques to ensure every students to gain proficiency and confidence in their chosen language.Thus St Joseph's ward/Students are focused in holistic growth for their bright future.</p>";
    $st = $pdo->prepare('INSERT INTO unique_features (title, body_html, image_id, position) VALUES (?,?,?,?)');
    $st->execute(['Extended School Concept(ESC)', $esc, img_id_by_file('esc1.jpg'), 0]);
    $st->execute(['The Language Academies', $lang, img_id_by_file('german.jpg'), 1]);
    $out[] = 'unique_features: seeded 2';
}

/* ---------- 7. Ticker items ---------- */
if (!$pdo->query('SELECT COUNT(*) FROM ticker_items')->fetchColumn()) {
    $items = [
        ['Mini Auditorium Inauguration is live..!!', 'https://www.youtube.com/live/ohCs3-Li6Xg?si=f4gybUy_de6_kerW'],
        ['Tamil Academy Video out now..!!',          'https://www.youtube.com/live/HWzLZbisbqo?si=91MYSwh82hXn5llW'],
        ['Maths Academy Video out now..!!',          'https://www.youtube.com/live/dfNVVWw73NQ?si=9vu4X4N3QiJb1G4D'],
    ];
    $st = $pdo->prepare('INSERT INTO ticker_items (label, url, position) VALUES (?,?,?)');
    foreach ($items as $i => [$label, $url]) {
        $st->execute([$label, $url, $i]);
    }
    $out[] = 'ticker_items: seeded 3';
}

/* ---------- 8. New-Updates slides (3 active + the commented 4th as inactive) ---------- */
if (!$pdo->query('SELECT COUNT(*) FROM update_slides')->fetchColumn()) {
    $slides = [
        ['EXPRESSIONZ 2026', 'ExpressionZ', 'https://www.youtube.com/watch?v=9sOJS-swj58', 'exp.jpg', 1],
        ['KG Welcome', 'LKG First day @ School 2026', 'https://www.youtube.com/watch?v=avGA0JiqvAM', 'kgwelcome.jpg', 1],
        ['Celebrations', 'KinderGarten GreenDay Celebrations 2026', 'https://www.youtube.com/watch?v=v8526xbfNnY', 'kggreen.jpg', 1],
        ['Co-Curriculum', 'Fancy dress competition has been conducted today', 'about.php', 'upd-1.jpg', 0],
    ];
    $st = $pdo->prepare('INSERT INTO update_slides (title, subtitle, link_url, image_id, position, is_active) VALUES (?,?,?,?,?,?)');
    foreach ($slides as $i => [$t, $s, $u, $img, $active]) {
        $st->execute([$t, $s, $u, img_id_by_file($img), $i, $active]);
    }
    $out[] = 'update_slides: seeded 4 (1 inactive)';
}

/* ---------- 9. Top marks (2024 / 2025 / 2026, from _templates/marks-scroll.php) ---------- */
$marks = [
    2024 => [
        ['12', 'I', 'AbiyaSilvista', 593, 600], ['12', 'II', 'Danya', 583, 600], ['12', 'III', 'JacobJebaraj', 580, 600],
        ['10', 'I', 'Madhumitha', 495, 500], ['10', 'II', 'Dhanusha', 493, 500],
        ['10', 'III', 'PhilipGnanaraj', 492, 500], ['10', 'III', 'Harina', 492, 500],
    ],
    2025 => [
        ['12', 'I', 'Rovena Sheril.R', 574, 600], ['12', 'I', 'Dharshan.G', 574, 600],
        ['12', 'II', 'Thangaraj.E', 572, 600], ['12', 'III', 'Rithika.C', 570, 600],
        ['10', 'I', 'Shobhika.S', 497, 500], ['10', 'II', 'Richi Remalin.G', 496, 500],
        ['10', 'II', 'Mano Santhosh.Y', 496, 500], ['10', 'III', 'Jonatha.T.G', 495, 500],
    ],
    2026 => [
        ['12', 'I', 'Roshini.S', 591, 600], ['12', 'II', 'Vipin.V', 590, 600], ['12', 'III', 'Yazhini.C', 589, 600],
        ['10', 'I', 'Harshini.R.S', 495, 500], ['10', 'II', 'Hare Pranav.V', 493, 500], ['10', 'III', 'Yogadarshan.A', 491, 500],
    ],
];
$insYear  = $pdo->prepare('INSERT IGNORE INTO mark_years (year) VALUES (?)');
$getYear  = $pdo->prepare('SELECT id FROM mark_years WHERE year = ?');
$cntEnt   = $pdo->prepare('SELECT COUNT(*) FROM mark_entries WHERE year_id = ?');
$insEnt   = $pdo->prepare('INSERT INTO mark_entries (year_id, standard, rank_label, student_name, marks_scored, marks_total, position) VALUES (?,?,?,?,?,?,?)');
foreach ($marks as $year => $entries) {
    $insYear->execute([$year]);
    $getYear->execute([$year]);
    $yid = (int)$getYear->fetchColumn();
    $cntEnt->execute([$yid]);
    if (!$cntEnt->fetchColumn()) {
        foreach ($entries as $i => [$std, $rank, $name, $scored, $total]) {
            $insEnt->execute([$yid, $std, $rank, $name, $scored, $total, $i]);
        }
        $out[] = "mark_entries $year: seeded " . count($entries);
    }
}

/* ---------- Summary ---------- */
$counts = [];
foreach (['images', 'pages', 'hero_slides', 'profiles', 'unique_features', 'ticker_items', 'update_slides', 'mark_years', 'mark_entries', 'admin_users'] as $t) {
    $counts[] = "$t=" . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
}
echo "Seed OK\n  " . implode("\n  ", $out) . "\nTotals: " . implode(' · ', $counts) . "\n";
