<?php // Floating quick actions — Stage J (PUBLIC_UI_DESIGN.md §5). Included by
// the shell on every page. The Admissions pill always shows; the WhatsApp
// button only when the `whatsapp_number` setting is non-empty (digits with
// country code — a dedicated key, since the school landline can't take wa.me).
$sjWa = preg_replace('/\D+/', '', repo_setting('whatsapp_number', ''));
?>
<div class="sj-fab" role="navigation" aria-label="Quick actions">
  <?php if ($sjWa !== ''): ?>
  <a class="sj-fab-wa" href="https://wa.me/<?= e($sjWa) ?>" target="_blank" rel="noopener"
     aria-label="Chat with the school on WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
  <?php endif; ?>
  <a class="sj-fab-cta sj-btn sj-btn--gold sj-btn--sm" href="/index.php#contact"><i
     class="fa-solid fa-graduation-cap"></i>Admissions</a>
</div>
