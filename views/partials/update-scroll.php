<?php // News ticker bar — clean fragment (no nested document).
// Parametrized: $sj_ticker comes from the page controller.
// Styles live VERBATIM in /css/partials/update-scroll.css (body link = valid
// HTML, same cascade position — R3).
// (The original's <body class="update-scroll"> carried no matching CSS rule and
// was discarded by the parser when nested; no wrapper is added here.) ?>
<link rel="stylesheet" href="/css/partials/update-scroll.css?v=<?php echo SJ_ASSET_VER; ?>">
    <div class="top-bar-update"<?= ed_add('ticker_item', [], 'Add ticker item') ?>>
        <div class="sliding-text-update">
            <?php foreach ($sj_ticker as $t): ?>
            <div class="update-link<?= empty($t['is_active']) ? ' sj-inactive' : '' ?>"<?= ed_item('ticker_item', $t['id'], 'Ticker item') ?>>
                <span class="new-update-blinker">•</span>
                <a href="<?= e($t['url']) ?>" target="_blank"<?= ed_field('ticker_item', $t['id'], 'label') ?>><?= e($t['label']) ?></a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
