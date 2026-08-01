<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Testimonials</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <style>
        .testimonial-body {
  font-family: Arial, sans-serif;

  color: white;
  margin: 0;
  padding: 0;
  text-align: center;
}

.testimonial-container {
  background-color: #fff5f5 !important;
  padding: 40px;
}

.testimonial-container h1 {
  font-family: "Fjalla One", sans-serif !important;
  font-weight: 700;
  font-size: 46px;
  margin-bottom: 30px;
  text-align: center;
}
/* animation */
.testimonial-reveal {
  transition: 1.2s;
  transform: translateY(50px);
  opacity: 0;
}
.testimonial-reveal.active {
  opacity: 1;
  transform: translateY(0);
}
.testimonial {
  display: flex;
  justify-content: space-around;
  flex-wrap: wrap;
}

.card {
  background-size: cover;
  border-radius: 10px;
  padding: 20px;
  margin: 10px;
  width: 30%;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
  text-align: left;
  position: relative;
}
.card:hover {
  box-shadow: 0px 0px 30px 5px rgba(0, 0, 0, 0.2);
}
.card1 {
  background: linear-gradient(rgba(43, 75, 138, 0.7), rgba(26, 53, 93, 0.7)),
    url("../photos/testimonial1.png");
    background-size:100% 100%;
}
.card2 {
  background: linear-gradient(rgba(43, 75, 138, 0.7), rgba(26, 53, 93, 0.7)),
    url("../photos/testimonial2.png");
    background-size:100% 100%;
}
.card3 {
  background: linear-gradient(rgba(43, 75, 138, 0.7), rgba(26, 53, 93, 0.7)),
    url("../photos/testimonial3.png");
    background-size:100% 100%;
    
}

.card h3 {
  font-family: "Fjalla One", sans-serif !important;
  border-bottom: 2px solid #fff;
  padding-bottom: 10px;
  margin-bottom: 20px;
  margin-top: 70px;
  color: gold;
}

.card p {
  font-family: "LeagueSpartan", sans-serif !important;
  color: white;
  line-height: 1.6;
  margin-bottom: 10px;
  text-align: justify;
}

.quote-icon {
  position: absolute;
  top: 20px;
  right: 48%;
  width: 50px;
  height: 50px;
}

@media (max-width: 768px) {
  .card {
    width: 80%;
  }
}

    </style>
</head>
<body class="testimonial-body">
    <div class="testimonial-container">
        <h1 class="testimonial-reveal">Students Testimonial</h1>
        <div class="testimonial">
            <div class="card card1">
            <img class="quote-icon" src="/photos/quote.png" alt="quote icon">
                <h3>Kishore N.E, <br>Alumni</h3>
                
                <p>Attending St.Joseph's MHSS was a transformative experience, particularly due to its outstanding sports coaching. The dedicated coaches and Ashok sir 
                  provided unwavering support and pushed me to excel, while also emphasizing the importance of resilience, and sportsmanship.
                  Throughout my high school years, I was fortunate to win numerous prizes in athletics. These achievements were a direct result of the rigorous training, 
                  strategic guidance, and motivational leadership from my mentors. The school's facilities and resources were instrumental in helping me set records.
                </p>
                <p>Balancing academics and athletics at SJMHSS shaped me into a disciplined, goal-oriented individual. The lessons I learned on the field and in the
                   classroom have had a lasting impact on my life.
                    I am immensely grateful for the support and opportunities provided by SJMHSS, and I highly recommend it to any aspiring student-athlete.</p>
               
               
            </div>
            <div class="card card2">
            <img class="quote-icon" src="/photos/quote.png" alt="quote icon">
                <h3>Santhosh R.D, <br>Alumni</h3>
                <p>As an alumnus of St. Joseph's Matric Higher Secondary School, my experience was truly transformative. 
                  The rigorous academics challenged me to exceed my own expectations, while the dedicated faculty provided
                   invaluable support and mentorship. Beyond the classroom, the vibrant community fostered lasting friendships
                    and essential professional connections.
                </p>
                <p> Participating in diverse extracurricular activities allowed me to explore my interests and develop new passions. 
                  Being involved in clubs and sports taught me critical skills like leadership, teamwork, and perseverance. The holistic 
                  education I received at St. Joseph's Matric Higher Secondary School equipped me with the knowledge and confidence to succeed
                   in my career and personal life. I am incredibly grateful for the opportunities and experiences that shaped me during my time at St. Joseph's.</p>
                
            </div>
            <div class="card card3">
            <img class="quote-icon" src="/photos/quote.png" alt="quote icon">
                <h3>Aswin K, <br>Alumni<br></h3>
                <p>Scoring 591 in the board exams is an achievement I am incredibly proud of, and it would not have been possible without the unwavering support 
                  of the management and teachers at St.Joseph's MHSS.The dedicated faculty members went above and beyond to ensure that we had a thorough understanding 
                  of the subjects. Their passion for teaching and commitment to our success were evident in every lesson, extra class, and individual guidance session. 
                  They always encouraged us to aim high and provided the tools and support needed to reach our goals.
                </p>
                <p>The school's management also played a crucial role in our academic journey. By fostering a conducive learning environment, providing excellent resources,
                   and organizing various academic programs, they ensured that we were well-prepared for the exams.
                    I am deeply grateful to my school for their support and dedication. This achievement is a testament to the hard work of both the 
                    students and the staff, and I highly recommend SJMHSS to anyone seeking a nurturing and high-quality educational experience.</p>
                
               
            </div>
        </div>
    </div>
    <script>
        window.addEventListener('scroll', function() {
        var reveals = document.querySelectorAll('.testimonial-reveal');
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
</body>
</html>
