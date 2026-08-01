<!DOCTYPE html>
<html lang="en">
<head>
<title>St.Josephs</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        html
{
    scroll-behavior:smooth;
}
.go-top-btn
{
    position: fixed;
    width: 50px;
    background: #2b4b8a;
    bottom: 40px;
    right: 50px;
    text-decoration: none;
    text-align: center;
    line-height: 50px;
    color: white;
    font-size: 18px;
    border-radius: 50%;
    z-index: 700;
}
.go-top-btn:hover
{
    background: #FFD700;
    color: black;
}
/* animation  */

.reveal-btn
{
    transition: 1.3s;
    opacity: 0;
    visibility: hidden;
}


.reveal-btn.active 
{
    opacity: 1;
    visibility: visible;
}
@media(max-width:768px)
{
    .go-top-btn
    {
        bottom: 30px;
        right: 30px;
    }
}
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <body>
        <div class="scroll-btn reveal-btn">
        <a class="go-top-btn "href="#"><i class="fa-solid fa-arrow-up"></i></a>
        </div>
        
        <script>
            document.addEventListener('DOMContentLoaded', function() {
    const scrollButton = document.querySelector('.scroll-btn');

    window.addEventListener('scroll', function() {
        if (window.scrollY > 600) { // Show button after scrolling down 100px
            scrollButton.classList.add('active');
        } else {
            scrollButton.classList.remove('active');
        }
    });
});
        </script>
    </body>


