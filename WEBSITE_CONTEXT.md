# WEBSITE_CONTEXT.md — St. Joseph's MHSS, Ondipudur

> Complete structural context for the whole website: every page, every section/div, the content and images each one shows, and what each block is for. Verified against source on 2026-07-26. Use this file as the reference when editing any page.

---

## 1. Site Overview

**Identity:** St. Joseph's Matric Higher Secondary School (SJMHSS), Ondipudur, Coimbatore – 641016. Founded 1986 by the R.C. Diocese of Coimbatore; ~2,217 students, ~82 staff. Motto: **"DISCIPLINE AND KNOWLEDGE"**.

**Tech stack:** Flat PHP site — no framework, no build step, no database. Bootstrap 4.3.1 / 4.5.3 / 5.0.2 / 5.3.3 from CDN (version varies per page/template), jQuery slim (navbar), Font Awesome, Google Fonts, EmailJS + Google reCAPTCHA (contact form only). Every page is a standalone `.php` file that stitches in shared template fragments.

**Directory layout:**

```
public_html/
├── index.php + 41 other page .php files (all pages live flat in the web root)
├── _libs/load.php          ← get_templates() include helper (only active code)
├── _templates/*.php        ← 14 reusable page fragments
├── css/*.css               ← 20 stylesheets (page-specific + component)
├── js/*.js                 ← 6 scripts (reveal/hover/contact behaviours)
├── photos/                 ← 478 images (all site imagery)
└── files/diary.pdf         ← downloadable school diary (linked from about.php)
```

**Brand palette** (defined in `css/index.css` `:root`): `--primaryblue #2b4b8a`, `--secondaryblue #1a355d`, `--gold #ffd700`, `--maroon firebrick`, white. **Fonts:** Fjalla One (headings), League Spartan / Raleway (body), Dancing Script (accent words inside headings, e.g. the italic word in "The *Academics* in St.Joseph's").

**Every page** sets `<title>St.Joseph's MHSS, Ondipudur</title>` and favicon `/photos/logo-main.png`.

### Page inventory (42 pages)

> **Post-revamp additions (Stage I, 2026-08-15).** The 42 shipped URLs below are
> unchanged. On top of them the CMS now serves: **`/academy.php?slug=…`** and
> **`/album.php?slug=…`** — generic controllers for academies/albums *created in the
> admin panel* (the shipped ones keep their own files; slugs are create-only and the
> shipped rows are delete-protected; `sitemap.xml` regenerates automatically);
> **`/admin/recover.php`** — password recovery, a 404 unless armed by a token file above
> the webroot (DEPLOY.md §9). Two formerly hardcoded areas are now DB-driven with
> byte-identical defaults: the gallery hub's 15-slide cross-fade slider (image_links on
> the `gallery` pages row, lazy-loaded) and the home testimonial card backgrounds
> (`testimonials.bg_image_id`, NULL = the shipped CSS statics).

| Page | Purpose |
|---|---|
| `index.php` | Home page — hero carousel, principal welcome, motto/campus cards, counters, uniqueness blocks, updates, toppers, testimonials, contact |
| `about.php` | Our School — president/principal messages, history, rules + timings, diary download |
| `staffs.php` | Our Staffs — staff intro and staff-tour blocks |
| `infrastructure.php` | 15 facility showcases (smart classrooms … mini auditorium), each with its own carousel |
| `academics.php` | Grade-level directory — 4 cards linking KG/Primary/High/Higher-Sec pages |
| `co-curriculum.php` | Master directory of 18 co-curricular academies/activities |
| `sports.php` | 9 sport cards with training times + expandable details |
| `achievements.php` | Sports/academic achievements zig-zag list + certificates awarded |
| `gallery.php` | Gallery hub — highlight slideshow + 10 album cards |
| `kg.php`, `primary.php`, `highschl.php`, `highsec.php` | School-section family (hero + intro + timeline accordion + event cards) |
| 15 × `*academy.php` (see §7) | Academy family (banner + carousel + write-up), one per academy |
| `band.php`, `ncc.php`, `artandexpo.php` | Same banner+carousel layout as academies, for Band / NCC / Art & Crafts Expo |
| 11 × `gal-*.php` (see §8) | Gallery-detail family (photo grid + lightbox), one per event |

---

## 2. Page Assembly Mechanism

> **Migration status (R1a–R1d done):** ALL 42 pages are converted to a
> **single-document Bootstrap-5 layout** — a thin controller (`require _libs/load.php` →
> `SJ\View\Layout::render('<page>', [...])`) renders `views/pages/<page>.php` inside the
> universal `views/shell.php` (one self-hosted Bootstrap 5.3.3, one Font Awesome, fonts,
> `tokens.css`, then the page's CSS; chrome via clean `views/partials/*`). The academy family
> shares `css/academy.css` + `js/academy.js`. R1d converted the 8 shared content-section
> templates into clean **parametrized partials** (`views/partials/{carousel,card,update-scroll,
> new-updates,marks-scroll,testimonial,contact,groups}.php` — controllers pass the data; no
> view queries) and moved home onto the shell; home also links `css/home-bs4-remnants.css`
> **last in its body** (verbatim BS4.3.1 fragments reproducing the baseline's final-stylesheet
> cascade slot — see the file header before touching it). **The `get_templates()` mechanism
> described below is now legacy** — nothing renders through it; `_templates/*` are deleted in R2.

- [`_libs/load.php`](public_html/_libs/load.php) defines **one** function: `get_templates($name)` → `include $_SERVER['DOCUMENT_ROOT']."/_templates/$name.php"`. Every page starts with `<?php include "_libs/load.php" ?>` and calls e.g. `<?php get_templates('navbar'); ?>`.
- **No parameterization.** Templates take no arguments; all content (topper marks, testimonials, ticker items…) is hard-coded inside the template file itself. To change such content you edit the template.
- A JSON config system (`get_config()` reading `schoolconfig.json`) exists in load.php but is **entirely commented out** — dead code.
- **Self-contained templates:** each template carries its own `<head>`, inline `<style>`, and `<script>`. The rendered page therefore contains nested/duplicate `<!DOCTYPE>`, `<head>`, `<body>` tags — non-standard HTML that browsers tolerate.
- **Cache busting:** page/component CSS is linked as `/css/xxx.css?v=<?php echo time(); ?>` (index, carousel, card, contact, footer), forcing a re-fetch on every load.
- **Image path conventions are mixed:** most references are absolute `/photos/…`; some are relative `photos/…` or `../photos/…` (gal-slider, preloader, groups, marks-scroll, several CSS backgrounds). All resolve to the same `public_html/photos/` folder when pages sit in the web root.
- **Universal scroll-reveal pattern:** nearly every page defines an inline `reveal()` on `window.scroll` that toggles an `.active` class on elements whose class ends in `-reveal` (e.g. `.abt-carousel-reveal`, `.infra-new-reveal`, `.span-reveal`) once they enter the viewport — driving CSS translate/opacity entrance animations.
- **Standard page skeleton:** `preloader → navbar → scroll-up → (page content) → [jumbotron] → footer`. Exceptions: `gallery.php` has **no preloader**; only 8 pages include the jumbotron (about, co-curriculum, infrastructure, sports, kg, primary, highschl, highsec); `highsec.php` additionally includes `groups` + `marks-scroll` before the jumbotron.

---

## 3. Shared Templates (`_templates/`, 14 files)

### 3.1 `preloader.php` — full-screen loading overlay
- **Used by:** every page except `gallery.php`.
- **Structure:** single `<div id="preloader">` — fixed, full-viewport, white background with `../photos/Typing.gif` centered as background image.
- **Behaviour:** inline script hides it on `window load`.
- **Context:** this is the page-load spinner shown before content appears.

### 3.2 `navbar.php` — sticky top navigation bar
- **Used by:** every page.
- **Structure:** `<nav class="navbar navbar-expand-lg navbar-light bg-light">` containing:
  - `.navbar-brand` → link to `index.php` with logo img `.img-1` = `/photos/logo-main.png` (school crest).
  - `.logo1.mr-auto` → img `/photos/st.png` (school name lettering, 210×40) + `.logo-text` "Ondipudur, Coimbatore - 641016".
  - `.navbar-toggler` hamburger (collapses into `#navbarNavDropdown` on mobile).
  - `ul.navbar-nav.ml-auto` menu:

| Menu item | Type | Links to |
|---|---|---|
| Home | link | `index.php` |
| About | dropdown (toggle href `curriculum.php` — dead, see §10) | Our School → `about.php`, Our Staffs → `staffs.php` |
| Infrastructure | link | `infrastructure.php` |
| Curriculum | dropdown (toggle href `curriculum.php` — dead) | Academics → `academics.php`, Co-Curriculum → `co-curriculum.php`, Sports → `sports.php` |
| Achievements | link | `achievements.php` |
| Gallery | link | `gallery.php` |

- **Behaviour:** JS adds `.sticky` to the navbar when `scrollY > 0` (stays pinned with shadow). Loads Bootstrap 4.5.3 + jQuery slim from CDN.
- **Note:** CSS for a `.top-bar` / `.sliding-text` announcement marquee exists in this template, but there is **no corresponding HTML** — the announcement bar styles are dead code. `css/navbar.css` also exists but is **not linked** (styles are inline).
- **Context:** the site-wide sticky header with dropdown navigation.

### 3.3 `scroll-up.php` — back-to-top button
- **Used by:** every page.
- **Structure:** `.scroll-btn.reveal-btn > a.go-top-btn[href="#"]` with a Font Awesome `fa-arrow-up` icon.
- **Behaviour:** becomes visible (`.active`) after `scrollY > 600`; `html {scroll-behavior: smooth}`. Font Awesome 6.5.2 CDN.
- **Context:** fixed circular scroll-to-top button, bottom corner of every page.

### 3.4 `carousel.php` — home-page hero carousel
- **Used by:** `index.php` only.
- **Structure:** `<section class="carousel-main reveal-carousel">` → `#carouselExampleIndicators.carousel.slide.carousel-fade` (`data-ride="carousel"`, auto-advance every **2000 ms**), 7 indicator dots, 7 `.carousel-item` slides, prev/next arrows.
- **Each slide:** full-width img + `.carousel-caption` — `<h5>St.Joseph's</h5>`, `<p>Matric Higher Secondary School, Ondipudur, Coimbatore - 641016</p>`, and an **Explore** button (`.btn.btn-1`) → `about.php`.
- **Slide images in order:** `/photos/sportsday20.jpg`, `/photos/sports.jpeg`, `/photos/ann3.jpg`, `/photos/expressday1.jpg`, `/photos/sportsday40.jpg`, `/photos/carosel1.jpg`, `/photos/kg-boys.jpg`.
- **Assets:** `/css/carousel.css`, `/js/carousel.js` (reveals `.reveal-carousel`), Bootstrap 4.3.1.
- **Context:** this is the home page's full-width auto-rotating hero banner.

### 3.5 `card.php` — "Our Motto" + "Our Campus" section
- **Used by:** `index.php` only.
- **Structure:**
  - `<section class="motto">` — `<h1 class="reveal-motto">Our Motto</h1>`, `<p>` "Motto of our School is ''DISCIPLINE AND KNOWLEDGE''", then `.motto-row` with two `.motto-col` text cards: **Discipline** ("Discipline is systematic instruction intended to train a person…") and **Knowledge** ("Knowledge is facts, information, and skills acquired through experience or education…").
  - `.products` — `<h1 class="reveal-text">Our Campus</h1>`, intro paragraph ("Welcome to our campus, a vibrant and nurturing environment…"), then `.card-deck.reveal-card` with three Bootstrap cards, each an image + **EXPLORE** button (`.btn.btn-dark`):

| Card | Image | Links to |
|---|---|---|
| `.card1` | `/photos/card1.jpg` | `gallery.php` |
| `.card2` | `/photos/card2.jpg` | `co-curriculum.php` |
| `.card3` | `/photos/card3.jpg` | `infrastructure.php` |

- **Assets:** `/css/card.css` (section background uses `../photos/schname.jpg`), `/js/card.js`.
- **Context:** home-page motto statement plus a 3-card campus navigation strip.

### 3.6 `update-scroll.php` — scrolling announcements ticker
- **Used by:** `index.php` only.
- **Structure:** `.top-bar-update > .sliding-text-update` — a CSS-marquee row of three `.update-link` anchors (each prefixed by a blinking gold `•` `.new-update-blinker`, `target="_blank"`):
  1. "Mini Auditorium Inaguration is live..!!" → `https://www.youtube.com/live/ohCs3-Li6Xg`
  2. "Tamil Academy Video out now..!!" → `https://www.youtube.com/live/HWzLZbisbqo`
  3. "Maths Academy Video out now..!!" → `https://www.youtube.com/live/dfNVVWw73NQ`
- **Behaviour:** infinite `slide` keyframe animation, pauses on hover. Fully inline styles; no images.
- **Context:** the thin news ticker bar on the home page announcing new YouTube videos/livestreams.

### 3.7 `new-updates.php` — "New Updates" featured-events carousel
- **Used by:** `index.php` only.
- **Structure:** `<section class="update">` — `.update-text > h5.reveal-update` "New Updates", then `.update-carousel > #carouselExampleIndicators2.carousel.slide.carousel-fade` (interval 3000 ms), 3 indicators, 3 `.carousel-item.update-carousel-item`. Each slide: image + `.carousel-caption.update-carousel-caption` (h5 title, p subtitle, **View More** button → YouTube):

| Slide | Image | Title / subtitle | View More link |
|---|---|---|---|
| 1 | `/photos/exp.jpg` | EXPRESSIONZ 2026 / ExpressionZ | `youtube.com/watch?v=9sOJS-swj58` |
| 2 | `/photos/kgwelcome.jpg` | KG Welcome / LKG First day @ School 2026 | `youtube.com/watch?v=avGA0JiqvAM` |
| 3 (active) | `/photos/kggreen.jpg` | Celebrations / KinderGarten GreenDay Celebrations 2026 | `youtube.com/watch?v=v8526xbfNnY` |

- A 4th slide ("Co-Curriculum", `/photos/upd-1.jpg`) is **commented out**. `css/new-updates.css` exists but is unused (styles inline).
- **Context:** home-page carousel highlighting recent school event videos.

### 3.8 `marks-scroll.php` — "Watch Out" board-exam toppers
- **Used by:** `index.php` and `highsec.php`.
- **Structure:** `<section class="sec-marks-scroll">` (background image `photos/schname.jpg` with blue tint) — `<h2 class="reveal-marks-scroll">Watch Out</h2>`, then `.container.marks-container` holding **three `.carousel-marks` columns** that auto-scroll vertically (infinite `scroll` keyframe, 20 s loop). Each column: heading + `.marks-carousel-inner` of `.marks-carousel-item` rows ("Toppers - YYYY", "12TH STD", ranked names, "10TH STD", ranked names):

| Column | 12th STD | 10th STD |
|---|---|---|
| **Top Marks(2024)** | I AbiyaSilvista 593/600 · II Danya 583/600 · III JacobJebaraj 580/600 | I Madhumitha 495/500 · II Dhanusha 493/500 · III PhilipGnanaraj 492/500 · III Harina 492/500 |
| **Top Marks(2025)** | I Rovena Sheril.R 574/600 · I Dharshan.G 574/600 · II Thangaraj.E 572/600 · III Rithika.C 570/600 | I Shobhika.S 497/500 · II Richi Remalin.G 496/500 · II Mano Santhosh.Y 496/500 · III Jonatha.T.G 495/500 |
| **Top Marks(2026)** | I ROSHINI.S 591/600 · II VIPIN.V 590/600 · III YAZHINI.C 589/600 | I HARSHINI.R.S 495/500 · II HARE PRANAV.V 493/500 · III YOGADARSHAN.A 491/500 |

- **Context:** the auto-scrolling academic-toppers showcase ("Watch Out" section).

### 3.9 `testimonial.php` — alumni testimonials
- **Used by:** `index.php` only.
- **Structure:** `.testimonial-body > .testimonial-container` — `<h1 class="testimonial-reveal">Students Testimonial</h1>`, then `.testimonial` flex row of three `.card`s (`.card1/.card2/.card3`, each with a CSS background portrait + dark gradient overlay). Each card: quote icon img `/photos/quote.png`, `<h3>` name + "Alumni", two testimonial paragraphs.

| Card | Background image | Person | Testimonial theme |
|---|---|---|---|
| card1 | `../photos/testimonial1.png` | Kishore N.E, Alumni | Sports coaching — "Attending St.Joseph's MHSS was a transformative experience…", athletics prizes, coach "Ashok sir" |
| card2 | `../photos/testimonial2.png` | Santhosh R.D, Alumni | Academics & community — "As an alumnus… my experience was truly transformative", clubs, leadership |
| card3 | `../photos/testimonial3.png` | Aswin K, Alumni | "Scoring 591 in the board exams…", faculty & management support |

- `css/testimonial.css` exists but is unused (styles inline).
- **Context:** the 3-column student/alumni testimonial section on the home page.

### 3.10 `contact.php` — "Make an Enquiry" section
- **Used by:** `index.php` only (anchor target `#contact`, also the jumbotron's destination).
- **Structure:** `.contactus.contact-section#contact` → `.title > h2.reveal-contact` "Make an Enquiry", then `.box` with three blocks:
  1. `.contact.form` — `<h3>Send a Message</h3>`, `<form id="contact_form" onsubmit="sendMail(event)">`: First Name + Last Name (`.row50`), Email + Mobile (`.row50`), Message textarea (`.row100`), Google **reCAPTCHA** widget (sitekey `6LdF1RsqAAAAAGNSgy7EX8V9KWajLCwo_poN9_PL`), Send submit button.
  2. `.contact.info` — `<h3>Contact Info</h3>`: address "St.Joseph's, Ondipudur, Coimbatore-16, TamilNadu"; email link **displays** `cbec_susaiappar@yahoo.co.in` but its `mailto:` points to `aswinkirubanantham@gmail.com`; phone `+ 0422-2271367`; `.sci` social icons list (Facebook). Ion-icons for location/mail/call.
  3. `.contact.map` — Google Maps `<iframe>` embed of the school location.
- **Assets:** `/css/contact.css`, `/js/contact.js` — validates the form and sends via **EmailJS** (`emailjs.send("service_jh0ghjn", "template_4j0k0ib", …)`, public key `psMv9kF5kawkjc1ve`) after checking reCAPTCHA.
- **Context:** the admissions/enquiry contact section at the bottom of the home page (form + contact info + map).

### 3.11 `footer.php` — site footer
- **Used by:** every page (always the last include).
- **Structure:** `<footer class="footer"> > .container > .footer-sections` with four `.footer-column`s, then `.footer-bottom`:

| Column | Contents |
|---|---|
| Useful Links | Home → `index.php`, About → `about.php`, Gallery → `gallery.php` |
| School Timings | Morning 8.30 AM–12.00 PM · Lunch 12.00–12.30 PM · Afternoon 12.30–3.20 PM |
| Contact Us | ☎ `0422-2271367`, ✉ `cbec_susaiappar@yahoo.co.in` (Font Awesome icons) |
| Stay Connected | YouTube (`youtube.com/@sjproductions1427`) + Facebook (`facebook.com/stjosephsschoolondipudur`) icons, `target="_blank"` |

- `.footer-bottom`: "Crafted by **Aswin Kirubanantham**" (Instagram link) + "© 2024 St.Joseph's MHSS, Ondipudur. All Rights Reserved."
- **Assets:** `/css/footer.css` (background `../photos/schname.jpg` tinted blue), Bootstrap 4.3.1.
- **Context:** the site-wide 4-column footer with timings, contact and socials.

### 3.12 `jumbotron.php` — admissions call-to-action band
- **Used by:** `about.php`, `co-curriculum.php`, `infrastructure.php`, `sports.php`, `kg.php`, `primary.php`, `highschl.php`, `highsec.php` (always right before the footer).
- **Structure:** `.jumbotron.jumbotron-fluid.jumbotron-reveal > .container.text-center` — `<h1 class="display-4">Explore a holistic education at St.Joseph's</h1>`, `<p class="lead">Click Here for Admissions</p>`, **Learn more** button → `/index.php#contact` (scrolls to the home-page enquiry form).
- **Context:** grey gradient admissions CTA banner reused across content pages.

### 3.13 `groups.php` — "Groups Offered" (11th/12th streams)
- **Used by:** `highsec.php` only.
- **Structure:** `.groups > .groups-container` (background `../photos/highsec1.jpg`, dark overlay) — `.header` "Groups Offered", then five `.card`s, each `.card-icon` (numbers 01–05) + `.card-content` text:
  1. Group-1 [BIOLOGY-MATHS]: English, Tamil/French, Physics, Chemistry, Maths, Biology
  2. Group-2 [COMPUTER-MATHS]: English, Tamil/French, Physics, Chemistry, Maths, Computer Science
  3. Group-3 [BIOLOGY-COMPUTER]: English, Tamil/French, Physics, Chemistry, Computer Science, Biology
  4. Group-4 [ARTS-COMPUTER]: English, Tamil/French, Accountancy, Commerce, Economics, Computer Applications
  5. Group-5 [ARTS-BUSINESS MATHS]: English, Tamil/French, Accountancy, Commerce, Economics, Business Maths
- **Context:** the higher-secondary subject-stream listing shown on the Higher Secondary page.

### 3.14 `gal-slider.php` — gallery highlight slideshow
- **Used by:** `gallery.php` only.
- **Structure:** `.container-slider` (60 % width, blue border + glow) containing **15 `.slide` divs**, each a CSS background image, cross-fading via opacity every **2 s**, pausing on hover; JS preloads all images.
- **Slide backgrounds (all `../photos/`):** `sportsday1.jpg`, `sportsday10.jpg`, `indday1.jpg`, `indday12.jpg`, `childday1.jpg`, `childday4.jpg`, `teachday1.jpg`, `teachday9.jpg`, `expressday1.jpg`, `expressday13.jpg`, `expo1.jpg`, `expo18.jpg`, `gradday1.jpg`, `gradday11.jpg`, `spach1.jpg`.
- **Context:** the auto-fading "best of all albums" hero slideshow at the top of the Gallery hub.

---

## 4. Home Page — `index.php`

`<body class="index">`, stylesheet `/css/index.css`. Exact top-to-bottom order:

1. `preloader` → 2. `navbar` → 3. `scroll-up` → 4. **`carousel`** (hero, §3.4)
5. **Welcome heading** — `div.home-text > h2` : "Welcome to **St.Joseph's** Matric Higher Secondary School" (the school name is a gold Dancing-Script `<span>`). *Context: page welcome banner under the hero.*
6. **Principal about-section** — `.containers > .about-section` = `.about-image` (img `/photos/princ1.jpg`, the principal's portrait) + `.about-content`: `<h3>Principal</h3>`, `<h2 class="ab-1">Rev.Fr.Kirubakaranathan</h2>`, two long paragraphs of the principal's message ("My dear Students, When a celebrated personality such as Nelson Mandela vouches for it…" — on true education, knowledge vs wisdom), and a **Read more** button (`.btn.btn-primary.btn-lg`) → `about.php`. *Context: principal's welcome message block with portrait.*
7. **`card`** template (Our Motto + Our Campus, §3.5)
8. **Fun-facts counters** — `section.fun-facts.overlay > .container` with four `.single-fact` blocks, each a Font Awesome icon + `.counter[data-target]` + label. JS `incrementCounters()` animates 0 → target on scroll-into-view:

| Icon | Counter | Label |
|---|---|---|
| `fa-user` | 80 + | Faculties |
| `fa-graduation-cap` | 2200 + | Our Students |
| `fa-chart-line` | 100 % | Board Results |
| `fa-award` | 50 + | Win Awards |

   *Context: animated statistics band (dark overlay strip).*
9. **"What's Unique?" section** — `section.newtemp-body` (full-viewport tinted background `../photos/schname.jpg` via index.css) with `.new-temp-text` heading "What's Unique?" and `.newtemp-about-container` holding two `.newtemp-about-section` blocks:
   - **Extended School Concept (ESC)** — img `/photos/esc1.jpg` + `<h2 class="infrastructure-text-reveal">Extended School Concept(ESC)</h2>` + text: launched 2023-24 for working parents; schedule 3:30–5:15 PM extra-curriculars, 5:15–5:30 snack break, 5:30–7:30 study hours; objectives (independent study habits, achievement); parental involvement (daily tutor meetings, monthly principal meetings).
   - **The Language Academies** — `<h2>The Language Academies</h2>` + text: French, Spanish and German designed for grades 6–8, cognitive development, mentor-led proficiency + img `/photos/german.jpg`.
   *Context: two feature blocks highlighting the school's differentiators.*
10. `update-scroll` (ticker, §3.6) → 11. `new-updates` (video carousel, §3.7) → 12. `marks-scroll` (toppers, §3.8) → 13. `testimonial` (§3.9) → 14. `contact` (§3.10) → 15. `footer` (§3.11)
16. Inline `<script>`: `reveal()` for `.ab-1`, `.span-reveal`, `.infrastructure-text-reveal`, `.new-temp-text` + `incrementCounters()` for `.counter`.

---

## 5. Main Content Pages

### 5.1 `about.php` — Our School
`<body class="history">`, `/css/about.css`. Order: preloader → navbar → scroll-up → content → jumbotron → footer.

1. **Hero carousel** — `section.abt-carousel > #carouselExampleSlidesOnly.carousel-fade` (2 s auto), 3 slides: `/photos/s-3.jpg`, `/photos/father-c.jpg`, `/photos/s-2.jpg`. Each `.carousel-caption`: h5 "Our School" + p "About our history and our pillars". *Context: page hero banner.*
2. **`div.containers`** with four `.about-section` blocks (image 40 % / text 60 %, alternating left-right via CSS `nth-child` ordering, blue gradient card background):
   - **President** — img `/photos/bishop1.jpg`; h3 "President", h2 "Rev.Dr.L.Thomas Aquinas"; message paragraphs ("Greetings in the name of Jesus Christ. The modern world is called 'Computer World'…", institution's growth, blessings). *Context: president's (bishop's) message with portrait.*
   - **Principal** — img `/photos/princ1.jpg`; h3 "Principal", h2 "Rev.Fr.Kirubakaranathan"; same principal's message as the home page. *Context: principal's message.*
   - **School History** — img `/photos/schhistory.jpg`; h3 "School History", h2 "38 Years of Excellence"; history text: founded 1986 by R.C Mission of Coimbatore Diocese, upgraded to Higher Secondary Oct 1999, started 1 May 1986 with Rev.Fr.Mark Manthara (Correspondent) & Rev.Sr.Alvarus Mary (principal), inaugurated by Bishop Most Rev.Dr.M.Ambrose, began with 150 students & 3 teachers, today 2217 students & ~82 staff, first X-STD batch 1997. *Context: school history block.*
   - **Rules and Regulations** — img `/photos/schdiary.jpg`; h3 "Rules and Regulations", h2 "Important"; motto text; **`table.school-timings`** (Timing / Activity): 8.30 AM–12.00 Noon Instructional Hours · 10 Minutes Interval · 12.00 Noon–12.30 P.M Lunch Break · 12.30–3.20 P.M Instructional Hours · 10 Minutes Interval; plus a **Download** link to `/files/diary.pdf` (`download="SchoolDiary.pdf"`). *Context: rules block with school timings table and diary download.*
3. **Teaser cards** — `div.abt-card-container.reveal-abt-card` with two full-width image-overlay `section.section` cards (zoom on hover): **Achievements** (bg `/photos/achbg.jpg`, blurb "Our school has consistently excelled…", Explore → `achievements.php`) and **"Infrastrucutre"** [sic] (bg `/photos/schname.jpg`, blurb about smart classrooms, Explore → `infrastructure.php`). *Context: cross-navigation teasers at page bottom.*

### 5.2 `academics.php` — grade-level directory
`<body class="academics">`, `/css/academics.css`. preloader → navbar → scroll-up → content → footer (no jumbotron).

1. **Hero carousel** — `section.academy-carousel`, 3 slides `photos/kg1.jpg`, `photos/high1.jpg`, `photos/highsec1.jpg`; captions "Academics" / "Embrace the challenges of learning, for they are stepping stones towards your academic success."
2. **Heading band** — `div.home-text > h2.span-reveal` "The **Academics** in St.Joseph's" (blue band, accent word in Dancing Script).
3. **Card grid** — `section.academy-card.academy-card-reveal > .row` with four `col-md-3` Bootstrap cards (image, title, grade range, **Explore** button; blue 4 px border, rounded 35 px):

| Image | Title | Range | Links to |
|---|---|---|---|
| `/photos/kg1.jpg` | Kinder Garten(KG) | LKG-UKG | `kg.php` |
| `/photos/primary4.jpg` | Primary School | 1st - 5th | `primary.php` |
| `/photos/high1.jpg` | High School | 6th - 10th | `highschl.php` |
| `/photos/highsec1.jpg` | Higher Secondary | 11th - 12th | `highsec.php` |

*Context: the "choose your grade level" navigation grid.*

### 5.3 `achievements.php` — achievements & awards
`<body class="achievements">`, `/css/achievements.css` (page background: fixed-attachment parallax `../photos/white-bg1.jpg`; item strips tinted with `../photos/schname.JPG` — case bug, see §10). Includes `/js/achievements.js`.

1. **Hero carousel** — `section.abt-carousel`, 3 slides `photos/achbg.jpg`, `/photos/achoverall.jpg`, `/photos/achcric.jpg`; captions "Our Achievements" / "About our achievements".
2. **Heading band** — `div.home-text` "The **Achievements** of St.Joseph's".
3. **Achievement zig-zag list** — five `div.achieve-container` blocks (classes `achieve-card-reveal`…`reveal5`), each holding two `.item`s (second one `.item.reverse` flips image/text order → zig-zag). Every `.item`: `.icon` with `/photos/trophy.png`, `<h3>` title, `<p>` caption, and a photo. All 10 items:

| # | Photo | Title | Caption |
|---|---|---|---|
| 1 | `/photos/achoverall.jpg` | Zonal Level Athletics Meet | Overall Winner - 2023 |
| 2 | `/photos/achdist.jpg` | District Level Athletics Meet | Venue: Karamadai VidhyaVikash — Overall Runners - 2023 |
| 3 | `/photos/achnatyoga.jpg` | National Level Yoga | Medal Winners |
| 4 | `/photos/achstate.jpg` | State Level Medal Winners | Appreciate by Commissioner of Police |
| 5 | `/photos/achsai.jpg` | Selected for SAI Camp | Banglore - 2024 |
| 6 | `/photos/achguj.jpg` | National Level Athletic Meet | Gujarat - 2024 |
| 7 | `/photos/achyog.jpg` | TamilNadu Sports Yoga Competition | Participated and Won Prizes - 2023 |
| 8 | `/photos/achcric.jpg` | Sri Shakthi College Cricket Trophy | Third Place(3) |
| 9 | `/photos/achmedal.jpg` | District Level Winners | Participated in State 2023 |
| 10 | `/photos/achtt.jpg` | Table Tennis Zonals | Winners - All Category - 2023 |

   *Context: showcase of the school's sports achievements.*
4. **Second heading band** — "The **Awards given by** St.Joseph's".
5. **Certificates list** — two more `.achieve-container` blocks (4 items, same structure, trophy icons):

| Photo | Title | Caption |
|---|---|---|
| `/photos/certificate1.jpg` | Certificate of Distinction | For Securing 80% in all Subjects |
| `/photos/certificate2.jpg` | Certificate of Merit | For Securing 60% in all Subjects |
| `/photos/certificate3.jpg` | Certificate of Achievement | For Intramural Winners |
| `/photos/certificate4.jpg` | Certificate of Achievement | For all other Achievements |

   *Context: the award certificates the school confers on students.*
- **Behaviour:** `achievements.js` blurs sibling cards when hovering one (`.achieve-card .col-md-6`) and reveals `reveal2…5` on scroll.

### 5.4 `co-curriculum.php` — academies directory
`<body class="co-curriculum">`, `/css/co-curriculum.css`, `/js/co-curriculum.js`. preloader → navbar → scroll-up → content → jumbotron → footer.

1. **Hero carousel** — `section.abt-carousel`, 3 slides `/photos/band1.jpg`, `photos/vocaca1.jpg`, `photos/ncc1.jpg`; captions "Our Co-Curriculum" / "About our co-curriculum".
2. **Heading band** — "The **Co Curriculum** of St.Joseph's".
3. **Academy cards** — `section.co-curriculum-all` wrapping six `section.co-curriculum-card` rows (`reveal`, `reveal2`…`reveal5`), each `.row` of three `col-md-4` Bootstrap cards: image + `.card-title` + subtitle + **Read more** button. Cards have a 4 px **firebrick** border/glow and `scale(1.14)` hover zoom while siblings blur (via co-curriculum.js). All 18 cards in display order:

| Row | Card title | Subtitle | Image | Links to |
|---|---|---|---|---|
| 1 | Academy of Tamil | Valanar Ilakkiya Mandram | `photos/tamaca1.jpg` | `tamilacademy.php` |
| 1 | Academy of English | Excel English Academy | `photos/engaca2.jpg` | `englishacademy.php` |
| 1 | Academy of Maths | Math Magician Academy | `photos/mataca1.jpg` | `mathsacademy.php` |
| 2 | Academy of Science | Masterminds Academy | `photos/sciaca1.jpg` | `scienceacademy.php` |
| 2 | Academy of Social Science | Patriotic Panthers Academy | `photos/sstaca1.jpg` | `socialacademy.php` |
| 2 | Academy of Foreign Languages | Spanish - German - French | `photos/langaca1.jpg` | `langacademy.php` |
| 3 | Instrumental Academy | Musical Instruments | `photos/insaca1.jpg` | `instrumentacademy.php` |
| 3 | Academy of Martial Arts | Karate | `photos/karaca1.jpg` | `martialacademy.php` |
| 3 | Academy of Communicative English | Effective Communication | `photos/comeaca1.jpg` | `communicativeacademy.php` |
| 4 | Academy of Athletes and Sports | Sports | `photos/spoaca1.jpg` | `sportsacademy.php` |
| 4 | Vocal Academy | Chorus Singing | `photos/vocaca1.jpg` | `vocalacademy.php` |
| 4 | Academy of Yoga | Yoga | `photos/yogaca1.jpg` | `yogaacademy.php` |
| 5 | Academy of Classical Dance | Bharatanatyam | `photos/danceaca1.jpg` | `danceacademy.php` |
| 5 | Band | St. Joseph's Tradition of Excellence | `photos/band1.jpg` | `band.php` |
| 5 | NCC | National Cadet Corps | `photos/ncc1.jpg` | `ncc.php` |
| 6 | Academy of Abacus | Saibodhi Abacus Academy | `photos/abacaca1.jpeg` | `abacusacademy.php` |
| 6 | Art and Crafts Expo | Innovation | `photos/artexpo0.jpg` ⚠ broken, file is `.jpeg` | `artandexpo.php` |
| 6 | Academy of Art and Crafts | Creative Thinking | `photos/artaca1.jpg` | `artacademy.php` |

*Context: the master directory of all co-curricular academies/activities — the hub the 18 detail pages hang off.*

### 5.5 `infrastructure.php` — facilities showcase (largest page)
`<body class="infrastructure">`, `/css/infrastructure.css`. preloader → navbar → scroll-up → content → jumbotron → footer. References `/js/infrastructure.js` which **does not exist** (see §10).

1. **Hero carousel** — `section.abt-carousel`, 3 slides `photos/s-3.jpg`, `photos/oaud1.jpg`, `photos/smclass1.jpg`; captions "Our Infrastructure" with rotating taglines: "Innovative Learning Spaces" / "Future-Ready Facilities" / "Dynamic Learning Environments".
2. **In-page anchor nav** — a secondary `nav.navbar` with h3 "Infrastructure" and a `.btn-group` of **15 anchor buttons** (Smart Classrooms, Indoor Auditorium, Outdoor Auditorium, Conference Hall, KG Play Area, Lab Facilities, Parking Facility, Sports Room, Transport Facility, Chapel, E-Library, Knowledge Center, Food Court, PlayGround, Mini Auditorium) that jump to section ids `#bg-1`…`#bg-14`. ⚠ The hrefs are **off by one** from the second button onward (see §10). *Context: quick-jump filter bar for the facility sections.*
3. **15 facility sections** — alternating `section.infra-new` / `section.infra-new1` (`id="bg-N"`, class `bg-N` = dark gradient over a facility photo as section background). Each contains its own Bootstrap indicator **carousel** (`.infra-new-carousel`, rounded 35 px images with shadow) on one side and `.infra-new-text` (h4 `.infra-new-reveal` + descriptive paragraph) on the other; `infra-new1` mirrors the order (text left, images right). All sections:

| id | Facility (h4) | Carousel images (`/photos/`) | Description gist |
|---|---|---|---|
| bg-1 | Smart Classrooms | smclass1, smclass2, smclass4 | smart-board digital classrooms, interactive learning |
| bg-2 | Indoor Auditorium | aud1, aud3, aud4 | enclosed hall for assemblies/performances |
| bg-3 | Outdoor Auditorium | oaud2, oaud3, oaud4 | open-air venue for assemblies/events |
| bg-4 | Conference Hall | conh2.jpeg, conh3.jpeg (2 slides) | meetings/presentations, AV-equipped (⚠ malformed carousel markup) |
| bg-5 | KG Play Area | kgplay2, kgplay3, kgplay4 | safe kindergarten play space, motor/social skills |
| bg-6 | Lab Facilities | clab1, clab2, clab3, lab2, lab4 (5 slides) | hands-on labs; extra spans: Science Laboratory / Physics Lab / Chemistry Lab / Biology Lab blurbs |
| bg-7 | Parking Facility | park1, park2 | organized, secure parking |
| bg-8 | Sports Room | sproom2, sproom3 | equipment storage/changing room |
| bg-9 | Transport Facility | bus1, bus2 | school bus fleet, safe routes |
| bg-10 | Chapel | chapel1, chapel2 | serene space for reflection/spiritual growth |
| bg-11 | E-Library | elib1, elib2, elib3 | digital books/journals access |
| bg-12 | Knowledge Center | knowcent1, knowcent2 | learning & research hub |
| bg-13 | Food Court | canteen1, canteen2 | nutritious meals; used by grades 10–12 during special classes |
| bg-14 | PlayGround | playg1, playg2, playg3 | outdoor activity/sports ground |
| bg-15 | Mini Auditorium | miniaud1, miniaud2, miniaud3 | seminars, conferences, orientation classes |

*Context: the full facilities tour — one photo-carousel + description block per facility. Layout collapses to single column below ~1520 px / 768 px.*

### 5.6 `sports.php` — sports catalogue
`<body class="sports">`, `/css/sports.css`, `/js/sports.js`. preloader → navbar → scroll-up → content → jumbotron → footer.

1. **Hero carousel** — `section.abt-carousel`, 3 slides `/photos/spoathlet.jpg`, `/photos/spofencing.jpg`, `photos/spohandb.jpg`; captions "Our Sports" / "About our Sports".
2. **Heading band** — "The **Sports** in St.Joseph's".
3. **Sport cards** — `section.sports-all` with three `section.sports-card` rows (`reveal`/`reveal2`/`reveal3`), each `.row` of three `col-md-4` cards (blue border + glow, hover zoom + sibling blur). Each card: image + h3 + training-time line + **Read More** Bootstrap collapse accordion revealing a detail paragraph:

| Row | Sport | Image | Training time | Collapse detail gist |
|---|---|---|---|---|
| 1 | Athletics | `/photos/spoathlet.jpg` | 3.30–5.00 p.m | dedication/role-model text about athletes |
| 1 | Badminton | `photos/spobadminton.jpg` | 3.30–5.00 p.m | daily training after classes; Zonal winners, District participated |
| 1 | Hand Ball | `photos/spohandb.jpg` | 3.30–5.00 p.m | champions congrats; U14 B&G Zonal winners, U17 Boys Zonal winners, U19 Girls Zonal runner, U14 Girls Revenue District IV place |
| 2 | Fencing | `/photos/spofencing.jpg` | (Wednesday) 3.30–5.00 p.m | newly introduced; foil/epee/sabre explainer; Wednesday evenings |
| 2 | Skating | `photos/sposkating.jpg` | (MON-TUE-WED) 6.00–7.00 p.m | newly introduced with professional training |
| 2 | Table Tennis | `photos/spotablet.jpg` | 6.30–8.00 p.m | zonal winners, district winners, state-level SGFI selection |
| 3 | Tennikoit | `photos/spotennikoit.jpg` | 3.30–5.30 p.m | zonal winners, district participation |
| 3 | Volley Ball | `photos/spovolley.jpg` | 3.30–5.30 p.m | participated in Zonal level |
| 3 | Throw Ball | `photos/spothrow.jpg` | 3.30–5.30 p.m | participated in Zonal level |

- A fourth "sec-4" row of placeholder cards (using `photos/s-2.jpg` ×3) is **commented out**. Accordions reuse `id="accordion"` per row (duplicate ids).
*Context: the sports program catalogue with expandable result details.*

### 5.7 `staffs.php` — Our Staffs
`<body class="infrastructure">` (reuses the infrastructure theme), `/css/staffs.css`. preloader → navbar → scroll-up → content → footer (no jumbotron).

1. **Hero carousel** — `section.abt-carousel`, **2 slides**: `photos/staff1.jpg` (group staff photo), `photos/teachertour.jpg`; captions "Our Staffs" / "About Our Staffs".
2. **Staff intro strip** — `div.infrastructure-container`: img `/photos/staff1.jpg` (left) + `.infrastructure-text` (h4 "We Love our Staffs" + two paragraphs: 63 teachers + 28 non-teaching staff, professional development via seminars/workshops, annual teachers' tour) + img `/photos/teachertour.jpg` (right). *Context: three-column intro — text flanked by two photos.*
3. **Narrative cards** — `section.newtemp-body` (full-viewport tinted `staff1.jpg` background) > `div.about-container` with two `.about-section`s:
   - **The Staffs** — img `/photos/staff1.jpg` + h2 + paragraph (dedicated team of 63 teachers…).
   - **The Staff Tour** — h2 + paragraph (recent trip to Coorg, Karnataka — relaxation, camaraderie) + img `/photos/teachertour.jpg`.
   *Context: staff-team and staff-tour story cards.*

### 5.8 `gallery.php` — gallery hub
`<body class="gallery">`, `/css/gallery.css` (fixed parallax background `../photos/white-bg1.jpg`). **No preloader.** navbar → scroll-up → content → footer.

1. **Heading** — `div.gallery-text.gallery-text-reveal`: h4 "School Gallery" + p "Tons of Memories carry our Gallery".
2. **`gal-slider` template** — 15-image cross-fade slideshow (§3.14). *Context: album highlights reel.*
3. **Album cards** — four `section.gallery-card` rows of `col-md-4` cards (image + title + year(s) + **More** button; blue border, rounded):

| Card | Image | Years | Links to |
|---|---|---|---|
| Annual Day | `/photos/annualday12.jpg` | 2023 & 2024 | `gal-annual.php` |
| Sports Day | `photos/sports.jpeg` | 2023 & 2024 | `gal-sports.php` |
| Independence Day | `photos/indday1.jpg` | 2023 & 2024 | `gal-independence.php` |
| Children's Day | `photos/childday1.jpg` | 2023 | `gal-children.php` |
| Teachers Day | `photos/teachday1.jpg` | 2023 | `gal-teacher.php` |
| Expressionz Day | `photos/expressday20.jpg` | 2023 | `gal-expressionz.php` |
| Science Expo | `photos/expo1.jpg` | 2023 & 2024 | `gal-expo.php` |
| KG Graduation | `photos/gradday1.jpg` | 2023 | `gal-grad.php` |
| Alumni | `photos/alumni1.png` | 2023 | `gal-alumni.php` |
| Sports Achivements [sic] | `photos/spach14.jpg` | 2023 | `gal-spach.php` |

*Context: the album navigation grid. Note: `gal-sciexpo.php` is NOT linked from here (orphan page, see §10).*

### 5.9 `band.php`, `ncc.php`, `artandexpo.php` — activity feature trio
These three pages **share one skeleton** (also shared with the 15 academy pages, §7): `<body class="academics">`, `/css/academics.css` + a large inline `<style>`; preloader → navbar → scroll-up → `back-bar` banner → `infra-new bg-1` block → footer. No jumbotron.

- **`section.back-bar` > `.back-text.back-bar-reveal`** — h4 page title + italic subtitle on a blue gradient band. *Context: page title banner.*
- **`section.infra-new.bg-1`** — dark-overlay background photo; left `section.infra-new-carousel` = 3-slide Bootstrap indicator carousel; right `div.infra-new-text` = h4 + description paragraphs (key phrases highlighted in gold `<span>`s). *Context: the feature block — photos + write-up.*

| Page | Banner h4 / subtitle | bg-1 background | Carousel slides | Write-up gist |
|---|---|---|---|---|
| `band.php` | Band / St. Joseph's Tradition of Excellence | `../photos/band1.jpg` | `/photos/band2.jpeg`, `/photos/band3.jpg`, `/photos/band1.jpg` | school band's musical heritage, teamwork/discipline/creativity, performances at assemblies & competitions |
| `ncc.php` | NCC / National Cadet Corps | `../photos/ncc1.jpg` | `/photos/ncc2.jpg`, `/photos/ncc3.jpg`, `/photos/ncc1.jpg` | NCC develops character, discipline, leadership; Ministry of Defence; **Community service:** blood donation/cleanliness drives; **Competitions:** drill, shooting, cultural |
| `artandexpo.php` | Art and Crafts Expo / Innovation | `../photos/artexpo0.jpg` ⚠ | `/photos/artexpo1.jpg`, `/photos/artexpo2.jpg`, `/photos/artexpo3.jpg` ⚠ | "Unlock your child's creative potential…" — boosts Creativness [sic], Community engagement, Cultural exchange |

⚠ All four `artexpo*` references are broken — the actual files are `.jpeg` (see §10).

---

## 6. Page Family: School Sections

**Pages sharing this skeleton (4):** `kg.php`, `primary.php`, `highschl.php`, `highsec.php`. Each has its own `<body class="{page}">` and stylesheet `/css/{page}.css`. This is the richest page template on the site.

**Shared skeleton, top to bottom:**
1. `preloader` → `navbar` → `scroll-up`.
2. **Hero carousel** — `section.{page}-carousel > #carouselExampleSlidesOnly.carousel-fade` (2 s auto), 3 full-width slides, each `.carousel-caption.text-left`: h5 section name + p tagline "Where students explore how to educate". *Context: section hero banner.*
3. **Intro + timeline block** — `section.infra-new.bg-6` (dark tinted background):
   - `section.infra-new-carousel` — `#carouselExampleIndicators` 3-slide indicator carousel of section photos.
   - `div.infra-new-text` — h4 section name, ~3 intro paragraphs, then **`div.accordion-main#accordion`** containing one Bootstrap collapse card: header button **"Timeline - 2024"** expanding `ul.timeline` — a vertical month-by-month event timeline of `<li>`s alternating `.direction-r`/`.direction-l`, each with `.flag-wrapper` (`span.flag` = MONTH, `span.time` = "2024 - present") and `.desc` (the events). *Context: section description + collapsible academic-year event calendar.*
4. **Heading band** — `div.home-text > h2.span-reveal` "(Exams and) Events of {Section}".
5. **Event cards** — `section.newtemp-body > div.about-container` with N `div.about-section` blocks (alternating image-left/text-right): img `.about-image` + `.about-content` (h2 `.infrastructure-text-reveal` + paragraph). *Context: featured events/activities of that section.*
6. `jumbotron` → `footer` (+ inline reveal script, Bootstrap 5.0.2).

**Per-page content:**

| | `kg.php` | `primary.php` | `highschl.php` | `highsec.php` |
|---|---|---|---|---|
| Hero caption | "Kinder Garten" | "Primary School" | "High School" | "**Higer Secondary**" [sic] |
| Hero slides | kg4, kg3, kg2 | primary4, primary2, primary1 | high4, high2, high1 | highsec4, highsec3, highsec2 |
| Inner carousel | kg2, kg3, kg4 | primary2, primary3, primary4 | high2, high3, high4 | highsec3, highsec4, highsec2 |
| Intro (h4 + text) | "Kinder Garten" — joyful learning, activities & songs, Tuesday assemblies, list of celebration days (Colours Day, Expressionz Day, Nature Conservation Day, … Music Day) | "Primary School" — grades 1–5, worksheets (Maths/English/Tamil), French/German/Spanish, proficiency tests, late-bloomer classes, Olympiads, spoken English/music/handwriting classes | "High School" — subject clubs: 1. Valanarilakkiamandram (Tamil), 2. Excel English Academy, 3. Math Magicians, 4. Master Minds (Science), 5. Patriotic Panthers (Social); exposure visits | "Higher Secondary" — foundation courses, unit/monthly tests, centum aim, counseling for slow learners, career guidance, celebrations, board-exam support (transport, prayers), French option for Tamil-strugglers |
| Timeline months | JUNE–MARCH (10 entries; KG activity days e.g. "(12-MONDAY) LKG - WELCOME DAY", Sports/Independence/Fruits day, Doodles trip, Diwali, Puppet show, Christmas, Pongal, Blue Day, Cultural Day…) | JUNE–MARCH (10 entries; Music Day, Friendship Day, EXPRESSIONZ, Terminal exams, Annual/Children's Day, Bharat English Proficiency Test, Maths Olympiad, SPELLATHON, Feast Day…) | JUNE–MARCH (10 entries; worksheets 1–10, proficiency tests, Science expo, Exprezions '24, subject-department days — Bharathiar/Ramanujan birthdays, SST expo, PTA, Annual exam…) | MAY–MARCH (11 entries; seminars, Foundation Course, elections/Investiture, monthly & unit tests, terminal exams, practicals, revision volumes, Model Practical, Board Exam…) |
| Accordion ids | headingOne/collapseOne | headingOne/collapseOne | **headingSix/collapseSix** (unique) | headingOne/collapseOne |
| Heading band | "**Events of** Kinder Garten" | "Exams and Events of Primary School" | "Exams and Events of High School" | "Exams and Events of Higher Secondary" |
| Event cards | **4 cards:** Orange Day (`/photos/kg-orange.jpeg`), Nature Conservation Day (`/photos/kg-natcon.jpg`), Fruit and Vegetables Day (`/photos/kg-veg.jpeg`), Graduation Day (`/photos/kg-grad.jpg`) | 2 cards: The Expressionz Day (`/photos/primary-expression.jpg`), The Music Classes (`/photos/vocaca2.jpg`) | 2 cards: The Unit Tests (`/photos/high3.jpg`), The Expressionz Day (`/photos/high-expression.jpg`) | 2 cards: The Unit Tests (`/photos/highsec-unit.jpg`), The Alumni Awards (`/photos/highsec-alumni.png`) |
| Extra templates | — | — | — | **`groups` (§3.13) + `marks-scroll` (§3.8)** inserted before jumbotron |

---

## 7. Page Family: Academies (15 pages)

**Pages sharing this skeleton (byte-identical structure, only strings/images differ):** `abacusacademy.php`, `artacademy.php`, `communicativeacademy.php`, `danceacademy.php`, `englishacademy.php`, `instrumentacademy.php`, `langacademy.php`, `martialacademy.php`, `mathsacademy.php`, `scienceacademy.php`, `socialacademy.php`, `sportsacademy.php`, `tamilacademy.php`, `vocalacademy.php`, `yogaacademy.php`. (Also shared by `band.php` / `ncc.php` / `artandexpo.php`, §5.9.)

**Shared skeleton:** `<body class="academics">` (line 196 in all 15), `/css/academics.css` + ~180-line inline `<style>`:
1. `preloader` → `navbar` → `scroll-up`.
2. **`section.back-bar` > `div.back-text.back-bar-reveal`** — `<h4>` academy title + `<p>` italic subtitle, full-width blue-gradient banner. *Context: academy page title banner.*
3. **`section.infra-new.bg-1`** — the academy's photo as a dark-overlay section background; inside: `section.infra-new-carousel` (`#carouselExampleIndicators`, 3 slides + indicators + prev/next) and `div.infra-new-text` (`h4.infra-new-reveal` academy name + description paragraph(s)). *Context: the academy's photo carousel + write-up, two-column (stacks below ~1320 px).*
4. Inline reveal script → `footer`.

**Per-academy content** (bg = section background image; carousel = the 3 visible slides):

| Page | Banner h4 / subtitle | Text h4 | bg image | Carousel images | Description gist |
|---|---|---|---|---|---|
| `abacusacademy.php` | Academy of Abacus / Saibodhi Abacus Academy | Academy of Abacus | `../photos/abacaca1.jpg` ⚠ (file is `.jpeg`) | abacaca2/3/4.jpg | Saibodhi partnership — speed & accuracy, concentration, memory, visualization, mental math, left/right-brain development, acupuncture-style holistic method |
| `artacademy.php` | Academy of Art and Crafts / Creative Thinking | Academy of Art and Crafts | `../photos/artaca1.jpg` | artaca2/3/4.jpg | painting, drawing, sculpting, knitting; yearly Art & Craft expo where students showcase creativity |
| `communicativeacademy.php` | Academy of Communicative english / Effective Communication | Academy of Communicative english | `../photos/comeaca1.jpg` | comeaca2/3/4.jpg | speaking, listening, reading, writing skills; grammar & vocabulary; cultural awareness; body language |
| `danceacademy.php` | Academy of Classical Dance / Bharatanatyam | Academy of Classical Dance | `../photos/danceaca1.jpg` | danceaca2/3/4.jpg | Bharatanatyam program — 3rd std: basic adavus & hasta mudras in a year; evening classes: advanced adavus with thalam, exam & Salangai Poojah prep |
| `englishacademy.php` | Excel English Academy / English Academy | English Academy | `../photos/engaca2.jpg` | engaca3, engaca4, engaca1.jpg | June grand inauguration with chief guest; dance/drama/mime/mono-act/oration showcases; Friday sessions 8.30–9 am for classes 6–8; February valedictory |
| `instrumentacademy.php` | Instrumental Academy / Musical Instruments | Instrumental Academy | `../photos/insaca2.jpg` | insaca1, insaca3, insaca2.jpg | keyboard/electronic instruments; qualified instructors; individual + group lessons, theory, ear training, recitals |
| `langacademy.php` | Academy of Foreign Languages / Spanish - German - French | Academy of Foreign Languages | `../photos/langaca1.jpg` | langaca2/3/4.jpg | French/Spanish/German for grades 6–8; cognitive development; same text as the home "Language Academies" block |
| `martialacademy.php` | Martial Arts Academy / Karate | Martial Arts Academy | `../photos/karaca1.jpg` | karaca2/3/4.jpg | karate for confidence & self-defence; grade-5 classes Wednesdays; special coaching grades 1–9 Mon–Wed 3:30–4:30 pm |
| `mathsacademy.php` | Math Magician Academy / Mathematics Academy | Mathematics Academy | `../photos/mataca1.jpg` | mataca2/3/4.jpg | same June-inauguration boilerplate as English academy |
| `scienceacademy.php` | Masterminds Academy / Science Academy | Science Academy | `../photos/sciaca1.jpg` | sciaca2/3/4.jpg | same June-inauguration boilerplate |
| `socialacademy.php` | Patriotic Panthers Academy / Social Academy | Social Academy | `../photos/sstaca1.jpg` | sstaca2/3/4.jpg | same June-inauguration boilerplate |
| `sportsacademy.php` | Academy of Athletes and Sports / Sports | Academy of Athletes and Sports | `../photos/spoaca1.jpg` | spoaca2/3/4.jpg | Badminton, Handball, TT, Tennikoit, Volleyball, Throwball, Kabbadi; evening training till 7.30; zonal/district/state winners; one student SAI-sponsored; Bruce Jenner quote |
| `tamilacademy.php` | Valanar Ilakkiya Mandram / Tamil Academy | Tamil Academy | `../photos/tamaca1.jpg` | tamaca2/3/4.jpg | same June-inauguration boilerplate |
| `vocalacademy.php` | Vocal Academy / Chorus Singing | Vocal Academy | `../photos/vocaca1.jpg` | vocaca2, vocaca3, vocaca1.jpg | musicality + technicality training for all ages; theory with practical examples; musical theatre/arts/drama exposure |
| `yogaacademy.php` | Academy of Yoga / Yoga | Academy of Yoga | `../photos/yogaca1.jpg` | yogaca1, **yogaaca2, yogaaca3**.jpg (note double-a prefix) | asanas + pranayama; concentration, memory, stress reduction, flexibility |

**Notes:** five academies (english, maths, science, social, tamil) share one boilerplate paragraph with only the academy name swapped. Several academies use a *different* branded name in the banner vs. the plain name in the text block (e.g. "Masterminds Academy" → "Science Academy"). Image prefixes are inconsistent: `karaca` (martial/karate), `sstaca` (social), `comeaca` (communicative), `spoaca` (sports), mixed `yogaca`/`yogaaca` (yoga).

---

## 8. Page Family: Gallery Detail (11 pages)

**Pages sharing this skeleton:** `gal-alumni.php`, `gal-annual.php`, `gal-children.php`, `gal-expo.php`, `gal-expressionz.php`, `gal-grad.php`, `gal-independence.php`, `gal-sciexpo.php`, `gal-spach.php`, `gal-sports.php`, `gal-teacher.php`. No external CSS — each page carries a ~140-line inline `<style>`; Bootstrap 5.3.3 CDN.

**Shared skeleton:** `<body class="gal-X">`:
1. `preloader` → `navbar` → `scroll-up`.
2. **`div.gal-X-container`** (a div, not a section):
   - `<h1>` album title. *Context: gallery page title.*
   - **`div.btn-group`** — radio-button year filter: `input.btn-check#btnradioYYYY` + `label.btn.btn-outline-primary`. The lightbox JS shows/hides `.image` divs whose class `year-YYYY` matches the selected radio's **id suffix** (not its label!). Most pages keep a second "2022" radio **commented out**. *Context: year filter tabs.*
   - **`div.image-container`** — responsive photo grid of `div.image.year-YYYY > img#image-K` tiles (250×350, blue border, hover zoom). *Context: the photo grid.*
   - **`div.popup-image`** — hidden full-screen lightbox: close `span` (×), an `<img>` (with a default src), and `.navigation-buttons` (`button.prev` / `button.next`). JS: click a tile to open, ×/prev/next controls, **touch-swipe** support. *Context: the image lightbox viewer.*
3. `footer` → Popper + Bootstrap 5.3.3 JS → inline lightbox + year-filter script.

**Per-page content:**

| Page | h1 title | Container class | Year-1 grid (class `year-2023`) | Second active year set | Toggle labels (⚠ id vs label quirks) | Lightbox default img |
|---|---|---|---|---|---|---|
| `gal-alumni.php` | Our Alumni | gal-alumni | `alumni1–10.png` (10) | — (commented placeholder) | "2023" | `/photos/alumni1.jpg` ⚠ missing (only .png exists) |
| `gal-annual.php` | annual Day [sic] | gal-annual | `annualday1–19, 22, 23.jpg` (21) | **`year-2024` active:** ann1–5.jpg + annualday19/22/23.jpg (8) | "2023" (⚠ label `for="btnradio2023-2024"` broken) + "2024" | annualday1.jpg |
| `gal-children.php` | Children's Day | gal-children | child1.jpg, child2.jpg, childday3–5.jpg, childday6–13.jpeg (13) | — | "**2024**" (⚠ id is btnradio2023) | childday1.jpg |
| `gal-expo.php` | Science Expo | gal-expo | `expo1–34.jpg` (34) | **`year-2022` active:** s1–5.jpeg (5) | "2023" + "**2024**" (⚠ id is btnradio2022) | expo1.jpg |
| `gal-expressionz.php` | Expressionz Day | gal-expressionz | `expressday1–44.jpg` (44) | — | "2023" | expressday1.jpg |
| `gal-grad.php` | KG Graduation Day | gal-grad | gradday1, 3–8, 10–13.jpg + kggrad1–4.jpg (15; gradday2/9 exist on disk but unused) | — | "2023" | gradday1.jpg |
| `gal-independence.php` | Independence Day | gal-independence | `indday1–16.jpg` (16) | **`year-2022` active:** in1–7.jpeg (7) | "2023" + "**2024**" (⚠ id is btnradio2022) | indday1.jpg |
| `gal-sciexpo.php` ⚠ | **Sports Achievements** (mislabeled copy — see §10) | **gal-spach** | s1–5.jpeg (5) | — | "**2024**" (⚠ id is btnradio2023) | spach1.jpg |
| `gal-spach.php` | Sports Achievements | gal-spach | `spach1–16.jpg` (16) | — | "2023" | spach1.jpg |
| `gal-sports.php` | Sports Day | gal-sports | `sportsday1–42.jpg` (42) | **`year-2022` active:** sp1–6.jpeg (6) | "2023" + "**2024**" (⚠ id is btnradio2022) | sportsday1.jpg |
| `gal-teacher.php` | Teacher's Day | gal-teacher | `teachday1–10.jpg` (10) | — | "2023" | teachday1.jpg |

**Two-year galleries (both toggles live):** gal-annual, gal-expo, gal-independence, gal-sports. All others are effectively single-year (their second toggle + `2.jpg`/`3.jpg` placeholder grid are commented out; those placeholder files don't exist anyway).

---

## 9. Assets Reference

### CSS (`css/`, 20 files)

| File | Used by | Notes |
|---|---|---|
| `index.css` | index.php | defines `:root` palette; `.about-section`, `.fun-facts`, `.newtemp-body` (bg `schname.jpg`) |
| `about.css` | about.php | `.containers` 80 % width; alternating 40/60 about-sections; teaser `.section` cards |
| `academics.css` | academics.php + all 15 academies + band/ncc/artandexpo | `.abt/academy-carousel` hero, `.home-text` band, `.academy-card` grid |
| `achievements.css` | achievements.php | fixed parallax `white-bg1.jpg`; ⚠ references `schname.JPG` (wrong case) |
| `co-curriculum.css` | co-curriculum.php | firebrick card borders, hover zoom + blur |
| `sports.css` | sports.php | blue card borders, hover zoom + blur |
| `infrastructure.css` | infrastructure.php | `.bg-1…15` background classes; mirrored `infra-new`/`infra-new1` flex layouts |
| `staffs.css` | staffs.php | `.infrastructure-container` 3-col strip; `.newtemp-body` tinted staff photo |
| `gallery.css` | gallery.php | fixed parallax `white-bg1.jpg`; album card grid |
| `kg.css` / `primary.css` / `highschl.css` / `highsec.css` | respective section page | hero carousel, timeline, about-container styles per section |
| `card.css` | card template | motto + campus cards (bg `schname.jpg`) |
| `carousel.css` | carousel template | hero carousel sizing/captions |
| `contact.css` | contact template | form/info/map 3-block layout |
| `footer.css` | footer template | 4-column footer (bg `schname.jpg`) |
| `navbar.css` | **UNUSED** | navbar styles are inline in the template |
| `new-updates.css` | **UNUSED** | template styles are inline |
| `testimonial.css` | **UNUSED** | template styles are inline |

### JS (`js/`, 6 files)

| File | Used by | Behaviour |
|---|---|---|
| `carousel.js` | carousel template | scroll-reveal for `.reveal-carousel` |
| `card.js` | card template | scroll-reveal for `.reveal-motto`, `.reveal-card`, `.reveal-text` |
| `contact.js` | contact template | `validateForm()` + EmailJS send + reCAPTCHA check; reveal `.reveal-contact` |
| `achievements.js` | achievements.php | hover-blur of sibling achievement cards; reveal `reveal2…5` |
| `co-curriculum.js` | co-curriculum.php | hover-blur of sibling academy cards; reveal `reveal2…5` |
| `sports.js` | sports.php | hover-blur of sibling sport cards; reveal `reveal2…5` |
| `infrastructure.js` | referenced by infrastructure.php | ⚠ **file does not exist** |

Everything else (carousels, collapses, dropdowns) is Bootstrap CDN behaviour; each gal-* page and gal-slider/marks-scroll/update-scroll/preloader/scroll-up/navbar carry their own inline scripts.

### Images & files
- `photos/` holds **478 files**. Naming conventions: event albums (`sportsdayN`, `inddayN`, `childdayN`, `teachdayN`, `expressdayN`, `expoN`, `graddayN`/`kggradN`, `spachN`, `annualdayN`/`annN`, `alumniN`), academies (`tamacaN`, `engacaN`, `matacaN`, `sciacaN`, `sstacaN`, `langacaN`, `insacaN`, `karacaN`, `comeacaN`, `spoacaN`, `vocacaN`, `yogacaN`/`yogaacaN`, `danceacaN`, `abacacaN`, `artacaN`, `artexpoN`, `bandN`, `nccN`), facilities (`smclassN`, `audN`, `oaudN`, `conhN`, `kgplayN`, `clabN`/`labN`, `parkN`, `sproomN`, `busN`, `chapelN`, `elibN`, `knowcentN`, `canteenN`, `playgN`, `miniaudN`), sections (`kgN`, `primaryN`, `highN`, `highsecN`), achievements (`ach*`, `certificateN`), people/branding (`princ1`, `bishop1`, `staff1`, `teachertour`, `logo-main.png`, `st.png`, `schname`, `schhistory`, `schdiary`), misc (`cardN`, `testimonialN.png`, `quote.png`, `trophy.png`, `Typing.gif`, `white-bg1`, `esc1`, `german`, `carosel1` [sic], `s-2`, `s-3`, `father-c`, `kg-boys`, `exp`, `kgwelcome`, `kggreen`, `upd-1`, `sN.jpeg`, `inN.jpeg`, `spN.jpeg`).
- `files/diary.pdf` — school diary, downloadable from about.php.

---

## 10. Appendix: Known Issues (verified against source)

> **Status after R1a–R1c (single-document BS5 migration):**
> - **Fixed** (functional bugs corrected during conversion, appearance unchanged): **#4**
>   (removed the missing `infrastructure.js` ref + the double BS bundle), **#5** (dead
>   `curriculum.php` navbar link → `#`), **#6** (infrastructure anchor off-by-one → `#bg-1..15`),
>   **#10** (nested/duplicate documents — every converted page is now one document; only home
>   remains nested), **#11 in part** (carousel/accordion ids now unique on all converted pages;
>   sports `accordion-1..9`, infrastructure's carousels all unique).
> - **Intentionally preserved** (content-level quirks kept byte-verbatim under the visual-freeze
>   rule — do NOT "fix" during migration; slate them for the CMS/content phase): ~~#1, #2~~
>   (`.jpeg` vs `.jpg` image paths — FIXED in C5: seeds resolve to the real files), **#3** (`gal-sciexpo` mislabeled orphan / `gal-spach`
>   classes), **#7** (gallery year-toggle id/label mismatches), **#8** (gal-alumni lightbox
>   default), **#9** (`schname.JPG` case), ~~#12~~ (contact email mismatch — FIXED in C1: both come from the `contact_email` setting), **#14**
>   (visible-text typos), **#15** (mixed image-path styles), and gallery's duplicate `id="image-3"`
>   tiles (the #11 remainder).
> - `.top-bar`/`.sliding-text` dead CSS (**#13**) was dropped from the new navbar partial; the
>   unused standalone `navbar.css`/`new-updates.css`/`testimonial.css` files still exist.

1. **Broken Art-Expo images (.jpg vs .jpeg):** `artandexpo.php` background `../photos/artexpo0.jpg` and carousel slides `artexpo1/2/3.jpg`, plus the co-curriculum card `photos/artexpo0.jpg`, all 404 — the actual files are `artexpo0–3.jpeg`.
2. **Broken Abacus banner background:** `abacusacademy.php` uses `../photos/abacaca1.jpg`; the file is `abacaca1.jpeg`. (The co-curriculum card correctly uses `.jpeg`.)
3. **`gal-sciexpo.php` is a mislabeled orphan copy of `gal-spach.php`:** its `<h1>` says "Sports Achievements", body/container classes are `gal-spach`, grid shows `s1–5.jpeg` labeled "2024" (input id `btnradio2023`), lightbox default `spach1.jpg`. The real Science Expo gallery is `gal-expo.php`, and **no page links to gal-sciexpo.php**.
4. **Missing `/js/infrastructure.js`:** referenced by `infrastructure.php` but the file doesn't exist (404; page still works via Bootstrap).
5. **Dead `curriculum.php` link:** both navbar dropdown toggles (`About`, `Curriculum`) use `href="curriculum.php"`, which doesn't exist. Bootstrap intercepts the click so users normally don't hit it, but it's a dead link for crawlers/no-JS.
6. **Infrastructure anchor nav off-by-one:** buttons 2–15 each point one section too high (Indoor Auditorium → `#bg-1` = Smart Classrooms, …, Mini Auditorium → `#bg-14` = PlayGround). `#bg-1` is targeted twice; section `#bg-15` (Mini Auditorium) is unreachable from the nav.
7. **Gallery year-toggle id/label mismatches:** the filter JS matches images by the radio **id** suffix, so labels lie in places — `gal-children` and `gal-sciexpo` label "2024" but filter `year-2023` images; `gal-expo`/`gal-independence`/`gal-sports` second button is `id="btnradio2022"` labeled "2024" showing `year-2022`-classed images; `gal-annual`'s "2023" label has `for="btnradio2023-2024"` (nonexistent id), so clicking that label does nothing (the radio is only pre-checked).
8. **Broken lightbox default in gal-alumni:** popup default src `/photos/alumni1.jpg` doesn't exist (only `alumni1.png`). Cosmetic — JS overwrites src on click.
9. **Case-sensitive background bug:** `css/achievements.css` line 220 uses `../photos/schname.JPG`; the file on disk is `schname.jpg` — background 404s on case-sensitive (Linux) hosting.
10. **Duplicate/nested document markup:** templates embed their own `<!DOCTYPE>/<head>/<body>`, so rendered pages nest them; `about/co-curriculum/sports/infrastructure` also have duplicate trailing `</body></html>`, and `infrastructure.php` loads the Bootstrap 5 bundle twice (once after `</html>`). `infrastructure.php` bg-4 (Conference Hall) has malformed carousel markup (unclosed `.carousel-inner`, controls nested inside).
11. **Duplicate element ids:** sports.php reuses `id="accordion"` for all three rows (`data-parent` collisions); gal pages repeat `id="image-3"` etc. on many grid tiles; several pages reuse `id="carouselExampleIndicators"` / `#carouselExampleSlidesOnly` for multiple carousels on one page (infrastructure has 15).
12. **Contact email mismatch:** the contact section displays `cbec_susaiappar@yahoo.co.in` but the `mailto:` href sends to `aswinkirubanantham@gmail.com`.
13. **Dead CSS / unused assets:** navbar's `.top-bar`/`.sliding-text` announcement-bar CSS has no HTML; `navbar.css`, `new-updates.css`, `testimonial.css` exist but are never linked; some photos exist but are unreferenced (e.g. `gradday2.jpg`, `gradday9.jpg`, `childday2.jpg`, `upd-1.jpg` only in a commented slide).
14. **Typos in visible text:** "Higer Secondary" (highsec hero caption), "Infrastrucutre" (about.php teaser card), "annual Day" lowercase (gal-annual h1), "Sports Achivements" (gallery.php card), "Inaguration" (update ticker), "Creativness" (artandexpo), filename `carosel1.jpg`.
15. **Path inconsistencies:** mixed `/photos/…`, `photos/…`, `../photos/…` references (all currently resolve, but relative ones would break if pages moved into subfolders).
