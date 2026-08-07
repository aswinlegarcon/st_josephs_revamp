<?php // News ticker bar — clean fragment (no nested document).
// Parametrized: $sj_ticker comes from the page controller.
// The <style> is VERBATIM from _templates/update-scroll.php — do not tidy.
// (The original's <body class="update-scroll"> carried no matching CSS rule and
// was discarded by the parser when nested; no wrapper is added here.) ?>
<style>
    /* Top bar */
.top-bar-update {
  background: linear-gradient(to right, #2b4b8a, #1a355d);
  overflow: hidden;
  white-space: nowrap;
  position: relative;
  padding: 5px 0;
  text-align: center;
}

.sliding-text-update {
  display: inline-block;
  white-space: nowrap;
  animation: slide 20s linear infinite;
}

.update-link {
  display: inline-block;
  margin-right: 30px; /* Space between updates */
  color: white;
  font-size: 16px;
}

.update-link a {
  color: white;
  text-decoration: none;
}

.update-link a:hover {
  color: #ffd700; /* Gold color on hover */
  transition: color 0.3s;
}

.new-update-blinker {
  color: #ffd700; /* Red color for the blinker */
  margin-right: 10px; /* Space between blinker and link */
  animation: blinker 1s linear infinite;
  font-size: 24px; /* Increase the size of the dot */
  line-height: 16px; /* Adjust to vertically center the dot */
}

@keyframes slide {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

@keyframes blinker {
  50% {
    opacity: 0;
  }
}

.sliding-text-update:hover {
  animation-play-state: paused; /* Pause the animation on hover */
}

@media (max-width: 700px) {
  .sliding-text-update {
    animation: slide 15s linear infinite;
  }
  @keyframes slide {
    0% {
      transform: translateX(-50%);
    }
    100% {
      transform: translateX(50%);
    }
  }
}

</style>
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
