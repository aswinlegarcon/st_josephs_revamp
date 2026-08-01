<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Responsive Design</title>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@100..900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fjalla+One&family=Raleway:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
  <style>
    .groups{
      font-family: Arial, sans-serif;
      margin: 0;
      padding: 0;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
    }
    .groups-container {
        border: 4px solid #2b4b8a;
        background:  linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),url('../photos/highsec1.jpg');
    background-size: cover;
      width: 90%;
      max-width: 1200px;
      padding:50px;
      margin: 0 auto;
      margin-bottom:20px;
      text-align: center;
    }
    .groups-container .header {
      font-family: "Fjalla One", sans-serif!important;
      font-size: 3em;
      font-weight: bold;
      margin-bottom: 20px;
      color: #ffd700;
      
    }
    .groups-container .card {
      background:linear-gradient(to left, #2b4b8a, #1a355d);
      border-radius: 10px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
      margin: 10px 0;
      padding: 20px;
      display: flex;
      align-items: center;
    }
   
    .groups-container .card .card-icon {
      background-color: #ffd700;
      border-radius: 50%;
      color: black;
      font-size: 1.5em;
      width: 50px;
      height: 50px;
      display: flex;
      justify-content: center;
      align-items: center;
      margin-right: 20px;
    }
    .groups-container .card .card-content {
        margin-top:10px;
        font-size:20px;
      text-align: center;
      color:#fff;
    }
    .groups-container .card .card-content span{
      font-family: "Fjalla One", sans-serif!important;
      color:#ffd700;
    }
    .groups-container .card .card-content p{
      font-family: "LeagueSpartan", sans-serif!important;
       
    }
    @media (max-width: 600px) {
      .card {
        flex-direction: column;
        text-align: center;
      }
      .card-icon {
        margin-bottom: 10px;
      }
      .card-content {
        text-align: center;
      }
    }
  </style>
</head>
<body class="groups">
  <div class="groups-container">
    <div class="header">Groups Offered</div>
    <div class="card">
      <div class="card-icon">01</div>
      <div class="card-content">
        <p> <span>Group-1 [BIOLOGY-MATHS] : </span>  English, Tamil/French, Physics, Chemistry, Maths, Biology  </p>
      </div>
    </div>
    <div class="card">
      <div class="card-icon">02</div>
      <div class="card-content">
        <p> <span>Group-2 [COMPUTER-MATHS] : </span> English, Tamil/French, Physics, Chemistry, Maths, Computer Science</p>
      </div>
    </div>
    <div class="card">
      <div class="card-icon">03</div>
      <div class="card-content">
        <p> <span>Group-3 [BIOLOGY-COMPUTER] : </span> English, Tamil/French, Physics, Chemistry, Computer Science, Biology </p>
      </div>
    </div>
    <div class="card">
      <div class="card-icon">04</div>
      <div class="card-content">
        <p> <span>Group-4 [ARTS-COMPUTER] : </span> English, Tamil/French, Accountancy, Commerce, Economics, Computer Applications </p>
      </div>
    </div>
    <div class="card">
      <div class="card-icon">05</div>
      <div class="card-content">
        <p> <span>Group-5 [ARTS-BUSINESS MATHS] : </span> English, Tamil/French, Accountancy, Commerce, Economics, Business Maths</p>
      </div>
    </div>
  </div>
</body>
</html>
