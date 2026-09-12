<?php // Site footer — clean fragment (footer.css loaded by the layout).
// C1: contact/timing strings come from `settings` (fallbacks = the exact
// original values, so rendering is byte-identical before seeding).
$sj_f_phone   = repo_setting('contact_phone', '0422-2271367');
$sj_f_email   = repo_setting('contact_email', 'cbec_susaiappar@yahoo.co.in');
$sj_f_yt      = repo_setting('youtube_url', 'https://youtube.com/@sjproductions1427');
$sj_f_fb      = repo_setting('facebook_url', 'https://www.facebook.com/stjosephsschoolondipudur');
$sj_f_morning = repo_setting('timing_morning', '8.30 AM to 12.00 PM');
$sj_f_lunch   = repo_setting('timing_lunch', '12.00 PM to 12.30 PM');
$sj_f_noon    = repo_setting('timing_afternoon', '12.30 PM to 3.20 PM');
$sj_f_copy    = repo_setting('footer_copyright', "© 2024 St.Joseph's MHSS, Ondipudur. All Rights Reserved.");
?>
<footer class="footer">
  <div class="container">
    <div class="footer-brand">
      <img src="/photos/logo-main.png" alt="">
      <div>
        <b>St.Joseph's Matric. Hr. Sec. School</b>
        <span>Ondipudur, Coimbatore &ndash; 641016</span>
      </div>
    </div>
    <div class="footer-sections">
      <div class="footer-column">
        <h2>Useful Links</h2>
        <ul>
          <li><a href="/index.php">Home</a></li>
          <li><a href="/about.php">About</a></li>
          <li><a href="/academics.php">Academics</a></li>
          <li><a href="/gallery.php">Gallery</a></li>
          <li><a href="/index.php#contact">Contact</a></li>
        </ul>
      </div>
      <div class="footer-column">
        <h2>School Timings</h2>
        <ul>
          <li>Morning : <?= e($sj_f_morning) ?></li>
          <li>Lunch : <?= e($sj_f_lunch) ?></li>
          <li>Afternoon : <?= e($sj_f_noon) ?></li>
        </ul>
      </div>
      <div class="footer-column">
        <h2>Contact Us</h2>
        <ul>
          <li><i class="fa-solid fa-phone"></i>   <?= e($sj_f_phone) ?></li>
          <li class="mail"><i class="fa-solid fa-envelope"></i>   <?= e($sj_f_email) ?></li>
        </ul>
      </div>
      <div class="footer-column social-media">
        <h2>Stay Connected</h2>
        <div class="social-media-icons">
          <a href="<?= e($sj_f_yt) ?>" target="_blank" rel="noopener" class="social-icon" aria-label="YouTube channel"><i class="fab fa-youtube"></i></a>
          <a href="<?= e($sj_f_fb) ?>" target="_blank" rel="noopener" class="social-icon" aria-label="Facebook page"><i class="fab fa-facebook-f"></i></a>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <p class="left-p">Crafted by</p>
      <a class="footer-logo sj-credit-mail" role="button" tabindex="0" aria-label="Email Aswin Kirubanantham" data-u="aswinkirubanantham" data-d="moc.liamg">
        <p class="left">Aswin Kirubanantham</p>
      </a>
      <p class="right"><?= e($sj_f_copy) ?></p>
    </div>
  </div>
</footer>
