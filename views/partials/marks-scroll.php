<?php // "Watch Out" toppers marquee — clean fragment (no nested document).
// Used by home AND highsec. Parametrized: $sj_marks_years from the controller.
// The <style> and <script> are VERBATIM from _templates/marks-scroll.php — do
// not tidy (incl. the .marks-scroll body-class rule, which matched nothing on
// the baseline because the nested <body class="marks-scroll"> was discarded by
// the parser; no wrapper is added here so it keeps matching nothing). ?>

<!-- new mark scroll -->

    <style>
        :root {
    --primaryblue: #2b4b8a;
    --secondaryblue: #1a355d;
    --gold : #ffd700;
    --white : white;
    --black : black;
    --maroon : firebrick;
  }

        .marks-scroll{
            font-family: Arial, sans-serif;

            color: white;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;

        }
        .sec-marks-scroll
        {
            background: linear-gradient(rgba(43, 75, 138, 0.8), rgba(26, 53, 93, 0.8) ), url('photos/schname.jpg');
         background-size:cover;
            padding:25px
        }
        .sec-marks-scroll h2{
            font-family: "Fjalla One", sans-serif!important;
            color:#ffd700;
            text-align:center;
            font-size:40px;
            font-weight:700;
            padding:15px;
        }
        /* animation */
        .reveal-marks-scroll
            {
                transition: 1s;
                transform: translateY(50px);
                opacity: 0;
            }
            .reveal-marks-scroll.active {
                opacity: 1;
                transform: translateY(0px);
            }
        .marks-container {
            display: flex;
            width: 100%;
            margin-bottom:30px;
            padding:25px;
            background-color: white;

        }

        .carousel-marks {
            width: 80%;
            height: 450px;
            overflow: hidden;
            position: relative;
            margin: 0 10px;

            float: left;
        }

        .carousel-marks h2 {
            font-family: "Fjalla One", sans-serif!important;
            color:white;
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 28px;
            margin: 0;
            z-index: 1;
        }

        .carousel-marks .marks-carousel-inner {
            display: flex;
            flex-direction: column;
            position: absolute;
            top: 50px;
            /* list is rendered twice; -50% + per-column duration = seamless loop at any
               item count (DYNAMIC_MIGRATION_PLAN.md §8.4) */
            animation: scroll50 20s linear infinite;
        }

        .carousel-marks .carousel-inner-container {
            position: absolute;
            top: 100px; /* Adjust the top to be below the heading */
            left: 0;
            right: 0;
            bottom: 0;
            overflow: hidden;
        }

        .carousel-marks .marks-carousel-item {
            color:#ffd700;
            height: 60px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 18px;
            padding: 10px;
            box-sizing: border-box;
        }
        .carousel-marks .marks-carousel-item span{
            color:#fff;
            font-size:20px;
            font-weight:bolder;
        }
        .carousel-left {
            background-image: linear-gradient(to left, #2b4b8a, #1a355d);
        }

        .carousel-right {

            background-image: linear-gradient(to left, #2b4b8a, #1a355d);
        }

        @keyframes scroll50 {
            0% {
                transform: translateY(0);
            }
            100% {
                transform: translateY(-50%);
            }
        }
        @media(max-width:768px)
        {
            .carousel-marks .marks-carousel-item {

            font-size: 13px;
            padding: 5px;
            text-align:center;

        }
        .carousel-marks .marks-carousel-item span{

            font-size:13px;

        }
    }
        @media(max-width:450px)
        {
            .carousel-marks h2{
                font-size:20px;
            }
            .carousel-marks .marks-carousel-item {

            font-size: 11px;
            padding: 0px;
            text-align:center;

        }
        .carousel-marks .marks-carousel-item span{

            font-size:11px;


        }
        }
    </style>
    <section class="sec-marks-scroll">
        <h2 class="reveal-marks-scroll">Watch Out</h2>

    <div class="container marks-container"<?= ed_add('mark_year', [], 'Add year') ?>>
        <?php foreach ($sj_marks_years as $yi => $y):
            // Build the display rows once: generated headers + stored entries.
            $rows = [['head' => 'Toppers - ' . $y['year']]];
            foreach ($y['standards'] as $std => $entries) {
                $rows[] = ['head' => $std . 'TH STD'];
                foreach ($entries as $en) {
                    $rows[] = ['entry' => $en];
                }
            }
            $itemCount = count($rows);
            $duration  = max(10, (int)ceil($itemCount * 1.8));
            $copies    = is_edit() ? 1 : 2; // edit mode: single static list
        ?>
        <div class="carousel-marks <?= $yi % 2 === 0 ? 'carousel-left' : 'carousel-right' ?><?= empty($y['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('mark_year', $y['id'], 'Year ' . $y['year']) ?>>
            <h2>Top Marks(<?php if (is_edit()): ?><span style="display:contents"<?= ed_field('mark_year', $y['id'], 'year') ?>><?= e($y['year']) ?></span><?php else: ?><?= e($y['year']) ?><?php endif; ?>)</h2>
            <div class="carousel-inner-container">
            <div class="carousel-inner marks-carousel-inner" style="animation-duration: <?= $duration ?>s;"<?= ed_add('mark_entry', ['year_id' => (int)$y['id']], 'Add topper (' . $y['year'] . ')') ?>>
            <?php for ($c = 0; $c < $copies; $c++): foreach ($rows as $row): ?>
                <?php if (isset($row['head'])): ?>
                <div class="marks-carousel-item"><span><?= e($row['head']) ?></span></div>
                <?php else: $en = $row['entry']; ?>
                <div class="marks-carousel-item"<?= $c === 0 ? ed_item('mark_entry', $en['id'], 'Topper row') : '' ?>><?php if (is_edit() && $c === 0): ?><em style="display:contents;font-style:normal"<?= ed_field('mark_entry', $en['id'], 'rank_label') ?>><?= e($en['rank_label']) ?></em><?php else: ?><?= e($en['rank_label']) ?><?php endif; ?> : <span<?= $c === 0 ? ed_field('mark_entry', $en['id'], 'student_name') : '' ?>> <?= e($en['student_name']) ?> </span> - <?= (int)$en['marks_scored'] ?>/<?= (int)$en['marks_total'] ?></div>
                <?php endif; ?>
            <?php endforeach; endfor; ?>
            </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    </section>
    <script>
        window.addEventListener('scroll', function() {
        var reveals = document.querySelectorAll('.reveal-marks-scroll');
        for (var i = 0; i < reveals.length; i++) {
            var windowHeight = window.innerHeight;
            var revealTop = reveals[i].getBoundingClientRect().top;
            var revealPoint = 150;

            if (revealTop < windowHeight - revealPoint) {
                reveals[i].classList.add('active');
            } else {
                reveals[i].classList.remove('active');
            }
        }
    });
    </script>
