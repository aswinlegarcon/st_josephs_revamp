<?php // "Watch Out" toppers marquee — clean fragment (no nested document).
// Used by home AND highsec. Parametrized: $sj_marks_years from the controller.
// Styles live VERBATIM in /css/partials/marks-scroll.css (body link = valid
// HTML, same cascade position — R3). ?>

<!-- new mark scroll -->

<link rel="stylesheet" href="/css/partials/marks-scroll.css?v=<?php echo SJ_ASSET_VER; ?>">
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
// Shipped reveal (scroll-only), now via the shared helper in /js/site.js (R2).
window.addEventListener('DOMContentLoaded', function () {
    sjReveal('.reveal-marks-scroll', 150);
});
</script>
