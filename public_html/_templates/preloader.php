<!DOCTYPE html>
<html lang="en">
<head>
<style>
   #preloader{
    background:#fff url(../photos/Typing.gif)no-repeat center center ;
    height:100vh;
    width:100%;
    position:fixed;
    z-index:100;
}
</style>
</head>


<div id="preloader"></div>

<script>
   var loader = document.getElementById("preloader");
   window.addEventListener("load", function(){
    loader.style.display = "none";
   })
</script>