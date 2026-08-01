<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/css/contact.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <script type="text/javascript"
        src="https://cdn.jsdelivr.net/npm/@emailjs/browser@4/dist/email.min.js">
        </script>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
        <script type="text/javascript">
          (function(){
              emailjs.init({
                publicKey: "psMv9kF5kawkjc1ve",
              });
          })();
</script>

<script src="/js/contact.js"></script>

</head>

<!-- Contact strt -->
<div class="contactus contact-section " id="contact">
    <div class="title">
      <h2 class="reveal-contact">Make an Enquiry</h2>
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
            <p class="info">St.Joseph's, Ondipudur, Coimbatore-16<br>TamilNadu </p>
          </div>
          <div>
            <span>
              <ion-icon name="mail"></ion-icon>
            </span>
            <a class="mail" href="mailto:aswinkirubanantham@gmail.com">cbec_susaiappar@yahoo.co.in</a>
          </div>
          <div>
            <span>
              <ion-icon name="call"></ion-icon>
            </span>
            <a href="tel: 0422 2271367"> + 0422-2271367</a>
          </div>
       
       
          <!-- social media links -->
          <ul class="sci">
            <li><a href="https://www.facebook.com/stjosephsschoolondipudur">
                <ion-icon name="logo-facebook"></ion-icon>
              </a></li>           
          
          </ul>
        </div>
      </div>

      <!-- Map -->
      <div class="contact map">
        <iframe
          src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2328.7393590070997!2d77.04439135621108!3d11.00428621166088!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ba8571a9c31ce7d%3A0xc5cc135a182aa90!2sSt.%20Joseph&#39;s%20Matriculation%20Higher%20Secondary%20School!5e0!3m2!1sen!2sin!4v1664903316653!5m2!1sen!2sin"
          style="border:0;" allowfullscreen="" loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"></iframe>
      </div>
    </div>
  </div>
  <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
  <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>

<!-- contact end -->


