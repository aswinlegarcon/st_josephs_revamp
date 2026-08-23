<?php // "Make an Enquiry" contact section — clean fragment (no nested document).
// C1: contact strings come from `settings` (fallbacks = the exact original
// values, so rendering is byte-identical before seeding). The mailto: now uses
// the SAME setting as the displayed address — bug 12 fixed by construction.
// SEC-23: the client-side EmailJS SDK + public key are REMOVED — the form
// posts to /api/contact.php (rate-limited, honeypot, server-side reCAPTCHA).
// reCAPTCHA widget kept. Visual output is unchanged.
$sj_c_email = repo_setting('contact_email', 'cbec_susaiappar@yahoo.co.in');
$sj_c_phone = repo_setting('contact_phone', '0422-2271367');
$sj_c_addr1 = repo_setting('contact_address_line1', "St.Joseph's, Ondipudur, Coimbatore-16");
$sj_c_addr2 = repo_setting('contact_address_line2', 'TamilNadu');
$sj_c_fb    = repo_setting('facebook_url', 'https://www.facebook.com/stjosephsschoolondipudur');
?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

<script src="/js/contact.js"></script>

<!-- Contact strt -->
<div class="contactus contact-section " id="contact">
    <div class="title">
      <h2 class="sj-reveal">Make an Enquiry</h2>
    </div>
    <div class="box">
      <!-- Form -->
      <div class="contact form">
        <h3>Send a Message</h3>
        <form method="POST" id="contact_form" onsubmit="sendMail(event)">
        <div class="formbox">
            <div class="row50">
                <div class="inputBox">
                    <span>First Name</span>
                    <input type="text" id="first_name" name="first_name" placeholder="Your First Name" required>
                </div>
                <div class="inputBox">
                    <span>Last Name</span>
                    <input type="text" id="last_name" name="last_name" placeholder="Your Last Name" required>
                </div>
            </div>
            <div class="row50">
                <div class="inputBox">
                    <span>Email</span>
                    <input type="email" id="email" name="email" placeholder="Your Email" required>
                </div>
                <div class="inputBox">
                    <span>Mobile</span>
                    <input type="text" id="mobile" name="mobile" placeholder="Your Number" required>
                </div>
            </div>
            <div class="row100">
                <div class="inputBox">
                    <span>Message</span>
                    <textarea name="description" id="message" placeholder="Write Your Message Here..."></textarea>
                </div>
            </div>
            <?php /* SEC-23 honeypot — invisible to people (display:none), bots fill it. */ ?>
            <input type="text" id="website" name="website" value="" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="g-recaptcha" style="transform: scale(0.77); transform-origin: 0 0;" data-sitekey="6LdF1RsqAAAAAGNSgy7EX8V9KWajLCwo_poN9_PL"></div><br>
            <div class="row100">
                <div class="inputBox">
                    <input type="submit" value="Send">
                </div>
            </div>
        </div>
    </form>
      </div>

      <!-- Info box-->

      <div class="contact info">
        <h3>Contact Info</h3>
        <div class="infoBox">
          <div>
            <span>
              <ion-icon name="location"></ion-icon>
            </span>
            <p class="info"><?= e($sj_c_addr1) ?><br><?= e($sj_c_addr2) ?> </p>
          </div>
          <div>
            <span>
              <ion-icon name="mail"></ion-icon>
            </span>
            <a class="mail" href="mailto:<?= e($sj_c_email) ?>"><?= e($sj_c_email) ?></a>
          </div>
          <div>
            <span>
              <ion-icon name="call"></ion-icon>
            </span>
            <a href="tel:<?= e(preg_replace('/[^0-9]/', '', $sj_c_phone)) ?>"> + <?= e($sj_c_phone) ?></a>
          </div>


          <!-- social media links -->
          <ul class="sci">
            <li><a href="<?= e($sj_c_fb) ?>">
                <ion-icon name="logo-facebook"></ion-icon>
              </a></li>

          </ul>
        </div>
      </div>

      <!-- Map -->
      <div class="contact map">
        <iframe
          title="Map: St.Joseph's Matriculation Higher Secondary School, Ondipudur"
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2328.7393590070997!2d77.04439135621108!3d11.00428621166088!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ba8571a9c31ce7d%3A0xc5cc135a182aa90!2sSt.%20Joseph&#39;s%20Matriculation%20Higher%20Secondary%20School!5e0!3m2!1sen!2sin!4v1664903316653!5m2!1sen!2sin"
          style="border:0;" allowfullscreen="" loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"></iframe>
      </div>
    </div>
  </div>
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

<!-- contact end -->
