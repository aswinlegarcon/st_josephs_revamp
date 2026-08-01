<head>

<style>
.jumbotron
{
   background: linear-gradient(to top, #f0f0f0, #d9d9d9)!important;
    margin-bottom:0 !important;
}
/* animation */
/* .jumbotron-reveal
{
    transition: 2s;
    transform: translateY(150px);
    opacity: 0;
}
.jumbotron-reveal.active {
    opacity: 1;
    transform: translateY(0);
} */
.jumbotron .container h1
{
    font-family: "Fjalla One", sans-serif!important;
    color:black!important;
    font-weight: 700;
}
.jumbotron .container p
{
    font-family: "LeagueSpartan", sans-serif!important;
    color: firebrick!important;
    font-weight: 500;
    font-style: italic;
}
.jumbotron .container .btn{
    font-family: "LeagueSpartan", sans-serif!important;
    border: none!important;
    background: linear-gradient(to left, #2b4b8a, #1a355d)!important;
}
.jumbotron .container .btn:hover
{
    background: firebrick!important;
}
@media(max-width: 900px)
{
    .jumbotron .container h1
    {
        font-size:45px;
    }
   
}
@media(max-width: 768px)
{
    .jumbotron .container h1
    {
        font-size:35px;
    }
   
}
@media(max-width: 500px)
{
    .jumbotron .container h1
    {
        font-size:25px;
    }
    .jumbotron .container p
    {
        font-size:14px;
    }
   
}
</style>
</head>
<div class="jumbotron jumbotron-fluid jumbotron-reveal">
  <div class="container text-center">
    <h1 class="display-4">Explore a holistic education at St.Joseph's</h1>
    <p class="lead">Click Here for Admissions</p>
   <a class="btn btn-primary btn-lg" href="/index.php#contact" role="button">Learn more</a>
    <script>
        window.addEventListener('scroll', function() {
        var reveals = document.querySelectorAll('.jumbotron-reveal');
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
</div>
</div>