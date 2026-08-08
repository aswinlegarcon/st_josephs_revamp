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
    if (is_file($root . '/bootstrap.php')) {
        define('SJ_PUBLIC_ROOT', $root);
        require $root . '/bootstrap.php';
        break;
    }
}
if (!defined('SJ_PUBLIC_ROOT')) {
    fwrite(STDERR, "Cannot locate public_html/bootstrap.php\n");
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

/* ---------- About page (C2) ----------
 * pages row + hero slides + the three about-only profile blocks. Text is the
 * EXACT shipped copy (visual-freeze). The principal profile already exists
 * (seeded above) and is SHARED between home and about by design. */
$pdo->prepare('INSERT IGNORE INTO pages (slug, title, heading_html) VALUES (?,?,?)')->execute([
    'about',
    "St.Joseph's MHSS, Ondipudur",
    '',
]);
$aboutPageId = (int)$pdo->query("SELECT id FROM pages WHERE slug = 'about'")->fetchColumn();

$cnt = $pdo->prepare('SELECT COUNT(*) FROM hero_slides WHERE page_id = ?');
$cnt->execute([$aboutPageId]);
if (!$cnt->fetchColumn()) {
    $st = $pdo->prepare('INSERT INTO hero_slides (page_id, image_id, caption_title, caption_text, position) VALUES (?,?,?,?,?)');
    foreach ([['s-3.jpg', 0], ['father-c.jpg', 1], ['s-2.jpg', 2]] as [$img, $pos]) {
        $st->execute([$aboutPageId, img_id_by_file($img), 'Our School', 'About our history and our pillars', $pos]);
    }
    $out[] = 'about hero_slides: seeded 3';
}

$insProfile = $pdo->prepare('INSERT IGNORE INTO profiles (role_key, heading, person_name, message_html, image_id) VALUES (?,?,?,?,?)');
$insProfile->execute(['president', 'President', 'Rev.Dr.L.Thomas Aquinas',
    '<p>Greetings in the name of Jesus Christ. The modern world is called ‘Computer World’ where the end of the earth is clearly seen from the place where you sit and see. The whole world has become a small village because of the arrival of the computer and the invention of the Internet.
      The fusion of education and morality remains the secret of the Institution’s success. Though the Institution had a humble beginning,
      within a period of a 29 years, it had grown in all spheres of technical education. I gladly invite all of you, dear guest viewers, to our website to learn more about the Institution and
      together with you, we would like to thank the Almighty, the giver of all gifts, for all He had done to the Institution to excel in the field of technical education. We appreciate your interest in knowing about the Institution.</p>
      <p>I also take this chance to invoke God’s abundant blessings on the Correspondent &amp; Principal, the members of the teaching and non teaching staff and all the students of the Institution. Wishing
      \'St. Joseph\'s Matriculation Higher Secondary School\' all the best, prosperous growth and success to take the Technical Education to the frontiers of the world in the years that lie ahead.</p>',
    img_id_by_file('bishop1.jpg')]);
$insProfile->execute(['history', 'School History', '38 Years of Excellence',
    '<p>St.Joseph\'s Matriculation Higher Secondary School was founded in the year 1986. R.C Mission of Coimbatore Diocese runs it. The Government since July 1986 recognized the school. It was upgraded as Higher Secondary School in October 1999. We have completed Silver Jubilee ERA.
      A new star of education blazed across the horizon on 1st May 1986 when St.Joseph\'s English school was started by the R.C Diocese of Coimbatore with Rev.Fr.Mark Manthara as Correspondent and Rev.Sr.Alvarus Mary as the principal.
      The Historic first step of the journey, towards excellence was taken up with 150 students and 3 teachers, was inaugurated by the Most Rev.Dr.M.Ambrose D.D.D.C.L the Bishop of Coimbatore. Today, the school has 2217 students and around 82 staff members.
      We started to follow the Matriculation system a prestigious unit of Madras University and our first batch of X STD came out with flying colours in 1997.Many classrooms were made more secure through wooden cupboards, cameras installed in classrooms, railing on the first and second floor metal grid along the staircase.</p>',
    img_id_by_file('schhistory.jpg')]);
$insProfile->execute(['rules', 'Rules and Regulations', 'Important',
    '<p>
        Rules and Regulations of our School is “DISCIPLINE AND KNOWLEDGE”, where Discipline is systematic instruction intended to train a person, activity, exercise, or a regimen that develops or improves a skill.
      </p>',
    img_id_by_file('schdiary.jpg')]);

/* ---------- Staffs page + home testimonials (C3) ----------
 * Same rules: EXACT shipped copy; keyed rows INSERT IGNORE; lists only when empty. */
$pdo->prepare('INSERT IGNORE INTO pages (slug, title, heading_html) VALUES (?,?,?)')->execute([
    'staffs',
    "St.Joseph's MHSS, Ondipudur",
    '',
]);
$staffsPageId = (int)$pdo->query("SELECT id FROM pages WHERE slug = 'staffs'")->fetchColumn();
$cnt->execute([$staffsPageId]);
if (!$cnt->fetchColumn()) {
    $st = $pdo->prepare('INSERT INTO hero_slides (page_id, image_id, caption_title, caption_text, position) VALUES (?,?,?,?,?)');
    foreach ([['staff1.jpg', 0], ['teachertour.jpg', 1]] as [$img, $pos]) {
        $st->execute([$staffsPageId, img_id_by_file($img), 'Our Staffs', 'About Our Staffs', $pos]);
    }
    $out[] = 'staffs hero_slides: seeded 2';
}

$insProfile->execute(['staff_love', 'Staffs', 'We Love our Staffs',
    '<p>We are fortunate to have a dedicated and passionate team of 63 enthusiastic
           and committed teachers, supported by a diligent group of 28 non-teaching
           staff members. Our educators recognize that staying updated is crucial
           for success in the ever-evolving field of education. To ensure they are
           well-equipped with the latest teaching methodologies and pedagogical
           strategies, our teachers actively participate in various professional
           development opportunities. These include seminars, training sessions,
            orientation classes, and workshops that cover a broad spectrum of
            topics relevant to modern education..</p>
        <p>Additionally, recognizing the importance of maintaining high levels of
          motivation and energy, we organize an annual tour for our teachers. This
          tour serves as an opportunity for them to relax, refresh, and recharge away
          from the routine of school life. It fosters camaraderie and team spirit,
          providing a perfect blend of professional development and personal
          rejuvenation. By investing in our teachers\' continuous growth and well-being
          , we ensure that they remain inspired and capable of delivering the highest
           quality education to our students.</p>',
    img_id_by_file('staff1.jpg')]);
$insProfile->execute(['staff_team', 'Staffs', 'The Staffs',
    '<p>We are fortunate to have a dedicated and passionate team of 63 enthusiastic
           and committed teachers, supported by a diligent group of 28 non-teaching
           staff members. Our educators recognize that staying updated is crucial
           for success in the ever-evolving field of education.</p>',
    img_id_by_file('staff1.jpg')]);
$insProfile->execute(['staff_tour', 'Staffs', 'The Staff Tour',
    '<p>The school staff recently embarked on a rejuvenating trip to Coorg,
                  a picturesque hill station in Karnataka known for its lush greenery and
                  serene landscapes. The journey provided a much-needed break from their daily
                   routines,
                   allowing them to unwind and recharge amidst nature’s beauty.
                     This trip not only refreshed their minds and bodies but also strengthened
                      their camaraderie, making it a memorable and enriching experience for everyone
                       involved</p>',
    img_id_by_file('teachertour.jpg')]);

if (!$pdo->query('SELECT COUNT(*) FROM testimonials')->fetchColumn()) {
    $st = $pdo->prepare('INSERT INTO testimonials (name_html, body_html, position) VALUES (?,?,?)');
    $st->execute(['Kishore N.E, <br>Alumni',
        '<p>Attending St.Joseph\'s MHSS was a transformative experience, particularly due to its outstanding sports coaching. The dedicated coaches and Ashok sir
                  provided unwavering support and pushed me to excel, while also emphasizing the importance of resilience, and sportsmanship.
                  Throughout my high school years, I was fortunate to win numerous prizes in athletics. These achievements were a direct result of the rigorous training,
                  strategic guidance, and motivational leadership from my mentors. The school\'s facilities and resources were instrumental in helping me set records.
                </p>
                <p>Balancing academics and athletics at SJMHSS shaped me into a disciplined, goal-oriented individual. The lessons I learned on the field and in the
                   classroom have had a lasting impact on my life.
                    I am immensely grateful for the support and opportunities provided by SJMHSS, and I highly recommend it to any aspiring student-athlete.</p>', 0]);
    $st->execute(['Santhosh R.D, <br>Alumni',
        '<p>As an alumnus of St. Joseph\'s Matric Higher Secondary School, my experience was truly transformative.
                  The rigorous academics challenged me to exceed my own expectations, while the dedicated faculty provided
                   invaluable support and mentorship. Beyond the classroom, the vibrant community fostered lasting friendships
                    and essential professional connections.
                </p>
                <p> Participating in diverse extracurricular activities allowed me to explore my interests and develop new passions.
                  Being involved in clubs and sports taught me critical skills like leadership, teamwork, and perseverance. The holistic
                  education I received at St. Joseph\'s Matric Higher Secondary School equipped me with the knowledge and confidence to succeed
                   in my career and personal life. I am incredibly grateful for the opportunities and experiences that shaped me during my time at St. Joseph\'s.</p>', 1]);
    $st->execute(['Aswin K, <br>Alumni<br>',
        '<p>Scoring 591 in the board exams is an achievement I am incredibly proud of, and it would not have been possible without the unwavering support
                  of the management and teachers at St.Joseph\'s MHSS.The dedicated faculty members went above and beyond to ensure that we had a thorough understanding
                  of the subjects. Their passion for teaching and commitment to our success were evident in every lesson, extra class, and individual guidance session.
                  They always encouraged us to aim high and provided the tools and support needed to reach our goals.
                </p>
                <p>The school\'s management also played a crucial role in our academic journey. By fostering a conducive learning environment, providing excellent resources,
                   and organizing various academic programs, they ensured that we were well-prepared for the exams.
                    I am deeply grateful to my school for their support and dedication. This achievement is a testament to the hard work of both the
                    students and the staff, and I highly recommend SJMHSS to anyone seeking a nurturing and high-quality educational experience.</p>', 2]);
    $out[] = 'testimonials: seeded 3';
}

/* ---------- School sections family (C4) ----------
 * Content comes from database/seed-data/sections.php (generated byte-faithfully
 * from the static R1b views by extract-sections.php). Keyed rows INSERT IGNORE;
 * per-section lists seeded only when empty. */
$sections = require __DIR__ . '/seed-data/sections.php';
$insSection = $pdo->prepare(
    'INSERT IGNORE INTO school_sections
       (slug, page_id, name, intro_heading, intro_html, timeline_heading, events_heading, card_title, card_range, card_image_id, position)
     VALUES (?,?,?,?,?,?,?,?,?,?,?)'
);
$secPos = 0;
foreach ($sections as $slug => $S) {
    // page row + hero slides (page-scoped, like about/staffs)
    $pdo->prepare('INSERT IGNORE INTO pages (slug, title, heading_html) VALUES (?,?,?)')
        ->execute([$slug, "St.Joseph's MHSS, Ondipudur", '']);
    $pid = (int)$pdo->query("SELECT id FROM pages WHERE slug = " . $pdo->quote($slug))->fetchColumn();
    $cnt->execute([$pid]);
    if (!$cnt->fetchColumn()) {
        $st = $pdo->prepare('INSERT INTO hero_slides (page_id, image_id, caption_title, caption_text, position) VALUES (?,?,?,?,?)');
        foreach ($S['hero'] as $i => [$img, $t, $x]) {
            $st->execute([$pid, img_id_by_file($img), $t, $x, $i]);
        }
    }

    $insSection->execute([
        $slug, $pid, $S['name'], $S['intro_heading'], $S['intro_html'],
        $S['timeline_heading'], $S['events_heading'],
        $S['card_title'], $S['card_range'], img_id_by_file($S['card_image']), $secPos++,
    ]);
    $sid = (int)$pdo->query("SELECT id FROM school_sections WHERE slug = " . $pdo->quote($slug))->fetchColumn();

    // inner carousel → image_links (owner 'section', role 'carousel')
    $c = $pdo->prepare("SELECT COUNT(*) FROM image_links WHERE owner_type = 'section' AND owner_id = ? AND role = 'carousel'");
    $c->execute([$sid]);
    if (!$c->fetchColumn()) {
        $st = $pdo->prepare("INSERT INTO image_links (owner_type, owner_id, role, image_id, position) VALUES ('section', ?, 'carousel', ?, ?)");
        foreach ($S['carousel'] as $i => $img) {
            $st->execute([$sid, img_id_by_file($img), $i]);
        }
    }

    $c = $pdo->prepare('SELECT COUNT(*) FROM timeline_entries WHERE section_id = ?');
    $c->execute([$sid]);
    if (!$c->fetchColumn()) {
        $st = $pdo->prepare('INSERT INTO timeline_entries (section_id, month_label, time_label, events_text, position) VALUES (?,?,?,?,?)');
        foreach ($S['timeline'] as $i => [$month, $time, $events]) {
            $st->execute([$sid, $month, $time, $events, $i]);
        }
        $out[] = "$slug timeline: seeded " . count($S['timeline']);
    }

    $c = $pdo->prepare('SELECT COUNT(*) FROM section_events WHERE section_id = ?');
    $c->execute([$sid]);
    if (!$c->fetchColumn()) {
        $st = $pdo->prepare('INSERT INTO section_events (section_id, title, body_html, image_id, position) VALUES (?,?,?,?,?)');
        foreach ($S['events'] as $i => [$title, $body, $img]) {
            $st->execute([$sid, $title, $body, $img ? img_id_by_file($img) : null, $i]);
        }
        $out[] = "$slug events: seeded " . count($S['events']);
    }
}

/* ---------- Academies family (C5) ----------
 * Content from database/seed-data/academies.php (generated by
 * extract-academies.php; byte-faithful except the six .jpg→.jpeg bug-1/2
 * fixes). Keyed rows INSERT IGNORE; carousels only when empty. */
$academies = require __DIR__ . '/seed-data/academies.php';
$insAcademy = $pdo->prepare(
    'INSERT IGNORE INTO academies
       (slug, card_title, card_subtitle, banner_title, banner_subtitle, content_heading, body_html, card_image_id, bg_image_id, position)
     VALUES (?,?,?,?,?,?,?,?,?,?)'
);
$acPos = 0;
foreach ($academies as $slug => $A) {
    $insAcademy->execute([
        $slug, $A['card_title'], $A['card_subtitle'], $A['banner_title'], $A['banner_subtitle'],
        $A['content_heading'], $A['body_html'],
        img_id_by_file($A['card_image']), img_id_by_file($A['bg_image']), $acPos++,
    ]);
    $aid = (int)$pdo->query('SELECT id FROM academies WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
    $c = $pdo->prepare("SELECT COUNT(*) FROM image_links WHERE owner_type = 'academy' AND owner_id = ? AND role = 'carousel'");
    $c->execute([$aid]);
    if (!$c->fetchColumn()) {
        $st = $pdo->prepare("INSERT INTO image_links (owner_type, owner_id, role, image_id, position) VALUES ('academy', ?, 'carousel', ?, ?)");
        foreach ($A['carousel'] as $i => $img) {
            $st->execute([$aid, img_id_by_file($img), $i]);
        }
    }
}
$out[] = 'academies: ' . $pdo->query('SELECT COUNT(*) FROM academies')->fetchColumn() . ' rows';

/* ---------- Sports (C6) ----------
 * Content from database/seed-data/sports.php (extract-sports.php). */
$sportsData = require __DIR__ . '/seed-data/sports.php';
$pdo->prepare('INSERT IGNORE INTO pages (slug, title, heading_html) VALUES (?,?,?)')
    ->execute(['sports', "St.Joseph's MHSS, Ondipudur", '']);
$sportsPageId = (int)$pdo->query("SELECT id FROM pages WHERE slug = 'sports'")->fetchColumn();
$cnt->execute([$sportsPageId]);
if (!$cnt->fetchColumn()) {
    $st = $pdo->prepare('INSERT INTO hero_slides (page_id, image_id, caption_title, caption_text, position) VALUES (?,?,?,?,?)');
    foreach ($sportsData['hero'] as $i => [$img, $t, $x]) {
        $st->execute([$sportsPageId, img_id_by_file($img), $t, $x, $i]);
    }
    $out[] = 'sports hero_slides: seeded ' . count($sportsData['hero']);
}
if (!$pdo->query('SELECT COUNT(*) FROM sports')->fetchColumn()) {
    $st = $pdo->prepare('INSERT INTO sports (name, training_time, details_html, image_id, position) VALUES (?,?,?,?,?)');
    foreach ($sportsData['sports'] as $i => $S) {
        $st->execute([$S['name'], $S['training_time'], $S['details_html'], $S['image'] ? img_id_by_file($S['image']) : null, $i]);
    }
    $out[] = 'sports: seeded ' . count($sportsData['sports']);
}

/* ---------- Infrastructure facilities (C7) ---------- */
$facData = require __DIR__ . '/seed-data/facilities.php';
$pdo->prepare('INSERT IGNORE INTO pages (slug, title, heading_html) VALUES (?,?,?)')
    ->execute(['infrastructure', "St.Joseph's MHSS, Ondipudur", '']);
$infraPageId = (int)$pdo->query("SELECT id FROM pages WHERE slug = 'infrastructure'")->fetchColumn();
$cnt->execute([$infraPageId]);
if (!$cnt->fetchColumn()) {
    $st = $pdo->prepare('INSERT INTO hero_slides (page_id, image_id, caption_title, caption_text, position) VALUES (?,?,?,?,?)');
    foreach ($facData['hero'] as $i => [$img, $t, $x]) {
        $st->execute([$infraPageId, img_id_by_file($img), $t, $x, $i]);
    }
    $out[] = 'infrastructure hero_slides: seeded ' . count($facData['hero']);
}
$insFac = $pdo->prepare('INSERT IGNORE INTO facilities (slug, name, description_html, bg_image_id, position) VALUES (?,?,?,?,?)');
foreach ($facData['facilities'] as $i => $F) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim($F['name'])));
    $insFac->execute([$slug, $F['name'], $F['description_html'], $F['bg_image'] ? img_id_by_file($F['bg_image']) : null, $i]);
    $fid = (int)$pdo->query('SELECT id FROM facilities WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
    $c = $pdo->prepare("SELECT COUNT(*) FROM image_links WHERE owner_type = 'facility' AND owner_id = ? AND role = 'carousel'");
    $c->execute([$fid]);
    if (!$c->fetchColumn()) {
        $st = $pdo->prepare("INSERT INTO image_links (owner_type, owner_id, role, image_id, position) VALUES ('facility', ?, 'carousel', ?, ?)");
        foreach ($F['carousel'] as $j => $img) {
            $st->execute([$fid, img_id_by_file($img), $j]);
        }
    }
}
$out[] = 'facilities: ' . $pdo->query('SELECT COUNT(*) FROM facilities')->fetchColumn() . ' rows';

/* ---------- Achievements + awards (C8) ---------- */
$achData = require __DIR__ . '/seed-data/achievements.php';
$pdo->prepare('INSERT IGNORE INTO pages (slug, title, heading_html) VALUES (?,?,?)')
    ->execute(['achievements', "St.Joseph's MHSS, Ondipudur", '']);
$achPageId = (int)$pdo->query("SELECT id FROM pages WHERE slug = 'achievements'")->fetchColumn();
$cnt->execute([$achPageId]);
if (!$cnt->fetchColumn()) {
    $st = $pdo->prepare('INSERT INTO hero_slides (page_id, image_id, caption_title, caption_text, position) VALUES (?,?,?,?,?)');
    foreach ($achData['hero'] as $i => [$img, $t, $x]) {
        $st->execute([$achPageId, img_id_by_file($img), $t, $x, $i]);
    }
    $out[] = 'achievements hero_slides: seeded ' . count($achData['hero']);
}
if (!$pdo->query('SELECT COUNT(*) FROM achievements')->fetchColumn()) {
    $st = $pdo->prepare('INSERT INTO achievements (type, title, subtext, image_id, position) VALUES (?,?,?,?,?)');
    $posByType = [];
    foreach ($achData['rows'] as $R) {
        $posByType[$R['type']] = ($posByType[$R['type']] ?? -1) + 1;
        $st->execute([$R['type'], $R['title'], $R['subtext'], $R['image'] ? img_id_by_file($R['image']) : null, $posByType[$R['type']]]);
    }
    $out[] = 'achievements: seeded ' . count($achData['rows']);
}

/* ---------- Gallery albums (C9) ---------- */
$galData = require __DIR__ . '/seed-data/gallery.php';
uasort($galData, static fn ($a, $b) => ($a['hub_pos'] ?? 99) <=> ($b['hub_pos'] ?? 99));
$insAlbum = $pdo->prepare('INSERT IGNORE INTO gallery_albums (slug, title, heading, card_sub, card_image_id, position) VALUES (?,?,?,?,?,?)');
$albPos = 0;
foreach ($galData as $slug => $G) {
    $insAlbum->execute([$slug, $G['card_title'], $G['title'], $G['card_sub'], $G['card_image'] ? img_id_by_file($G['card_image']) : null, $albPos++]);
    $aid = (int)$pdo->query('SELECT id FROM gallery_albums WHERE slug = ' . $pdo->quote($slug))->fetchColumn();
    foreach ($G['years'] as $yi => $Y) {
        $pdo->prepare('INSERT IGNORE INTO album_years (album_id, year_label, position) VALUES (?,?,?)')
            ->execute([$aid, $Y['label'], $yi]);
        $yid = (int)$pdo->query("SELECT id FROM album_years WHERE album_id = $aid AND year_label = " . $pdo->quote($Y['label']))->fetchColumn();
        $c = $pdo->prepare("SELECT COUNT(*) FROM image_links WHERE owner_type = 'album_year' AND owner_id = ? AND role = 'photos'");
        $c->execute([$yid]);
        if (!$c->fetchColumn() && !empty($G['photos'][$Y['label']])) {
            $st = $pdo->prepare("INSERT IGNORE INTO image_links (owner_type, owner_id, role, image_id, position) VALUES ('album_year', ?, 'photos', ?, ?)");
            foreach ($G['photos'][$Y['label']] as $i => $img) {
                $st->execute([$yid, img_id_by_file($img), $i]);
            }
        }
    }
}
$out[] = 'gallery_albums: ' . $pdo->query('SELECT COUNT(*) FROM gallery_albums')->fetchColumn()
       . ' albums, ' . $pdo->query('SELECT COUNT(*) FROM album_years')->fetchColumn() . ' years, '
       . $pdo->query("SELECT COUNT(*) FROM image_links WHERE owner_type='album_year'")->fetchColumn() . ' photos';

/* ---------- Site settings (C1) ----------
 * Values are the EXACT strings shipped in the static pages (visual-freeze):
 * seeding them keeps the rendered output byte-identical while making the
 * strings editable in the admin panel. INSERT IGNORE = admin edits win. */
$settings = [
    'contact_email'         => 'cbec_susaiappar@yahoo.co.in',
    'contact_phone'         => '0422-2271367',
    'contact_address_line1' => "St.Joseph's, Ondipudur, Coimbatore-16",
    'contact_address_line2' => 'TamilNadu',
    'facebook_url'          => 'https://www.facebook.com/stjosephsschoolondipudur',
    'youtube_url'           => 'https://youtube.com/@sjproductions1427',
    'timing_morning'        => '8.30 AM to 12.00 PM',
    'timing_lunch'          => '12.00 PM to 12.30 PM',
    'timing_afternoon'      => '12.30 PM to 3.20 PM',
    'jumbotron_heading'     => "Explore a holistic education at St.Joseph's",
    'jumbotron_sub'         => 'Click Here for Admissions',
    'jumbotron_btn'         => 'Learn more',
    'footer_copyright'      => "© 2024 St.Joseph's MHSS, Ondipudur. All Rights Reserved.",
    'marks_years_shown'     => '3',
];
$insSet = $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?,?)');
$newSet = 0;
foreach ($settings as $k => $v) {
    $insSet->execute([$k, $v]);
    $newSet += $insSet->rowCount();
}
$out[] = "settings: +$newSet newly seeded";

/* ---------- Summary ---------- */
$counts = [];
foreach (['images', 'pages', 'hero_slides', 'profiles', 'unique_features', 'ticker_items', 'update_slides', 'mark_years', 'mark_entries', 'testimonials', 'settings', 'admin_users'] as $t) {
    $counts[] = "$t=" . $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
}
echo "Seed OK\n  " . implode("\n  ", $out) . "\nTotals: " . implode(' · ', $counts) . "\n";
