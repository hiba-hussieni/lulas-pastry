<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <!-- UTF-8 allows all symbols + emojis

viewport makes the website responsive on mobile
-->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lula's Pastry</title>

  <!--  Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">
  <!-- we are using google fonts palyfair and montserrat

  <!-- Font Awesome for Instagram Icon!-->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <style>
    *{margin:0;padding:0;box-sizing:border-box;}

    body{
      font-family:'Montserrat',sans-serif;
      background:#FFFDFB;
      color:#5D4037;
      scroll-behavior:smooth;
    }

    /*  HEADER / NAVBAR */
    header{
      background:#FFFDFB;
      padding:25px 60px;
      text-align:center;
      border-bottom:3px solid #C58B78;
      position:sticky;
      top:0;
      z-index:1000;
    }

    header img{
      width:90px;
      height:90px;
      object-fit:contain;
    }

    header h1{
      font-family:'Playfair Display',serif;
      font-size:38px;
      color:#C58B78;
      margin-top:10px;
      letter-spacing:2px;
    }

    nav{
      margin-top:15px;
    }

    nav a{
      text-decoration:none;
      color:#9B6B5A;
      font-weight:600;
      margin:0 18px;
      font-size:16px;
      transition:0.3s;
    }

    nav a:hover{
      color:#C58B78;
    }

    /*  HERO SECTION */
    .hero{
      padding:80px 20px;
      background:#F8E6DE;
      text-align:center;
    }

    .hero h2{
      font-family:'Playfair Display',serif;
      font-size:52px;
      color:#C58B78;
      margin-bottom:20px;
    }

    .hero p{
      max-width:720px;
      margin:0 auto;
      font-size:18px;
      line-height:1.7;
      color:#9B6B5A;
    }

    /*  BUTTONS (same size) */
    .btn-area{
      margin-top:40px;
      display:flex;
      justify-content:center;
      gap:18px;
      flex-wrap:wrap;
    }

    .btn-area a{
      text-decoration:none;
      background:#C58B78;
      color:white;
      padding:14px 0;
      width:200px;
      text-align:center;
      border-radius:35px;
      font-weight:700;
      font-size:16px;
      transition:0.3s;
      display:inline-block;
    }

    .btn-area a:hover{
      background:#9B6B5A;
      transform:translateY(-2px);
    }

    /*  SECTIONS */
    section{
      padding:80px 60px;
      text-align:center;
    }

    section h2{
      font-family:'Playfair Display',serif;
      font-size:42px;
      color:#C58B78;
      margin-bottom:20px;
    }

    section p{
      max-width:800px;
      margin:0 auto;
      font-size:17px;
      line-height:1.7;
      color:#9B6B5A;
    }

    .section-bg{
      background:#FFFDFB;
    }

    .section-alt{
      background:#F8E6DE;
    }

    /*  Instagram link style */
    .insta-link{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:10px;
      margin-top:18px;
      text-decoration:none;
      font-weight:800;
      font-size:17px;
      color:#C58B78;
      transition:0.3s;
    }

    .insta-link i{
      font-size:22px;
    }

    .insta-link:hover{
      color:#9B6B5A;
      transform:scale(1.05);
    }

    footer{
      background:#FFFDFB;
      padding:25px;
      text-align:center;
      font-size:15px;
      color:#9B6B5A;
      border-top:3px solid #C58B78;
    }
// its used to enno hyda website ytghyar aala 7asab size of screen mtl tablet,mobile...,laptop
    @media(max-width:768px){
      header{padding:20px;}
      section{padding:60px 20px;}
      .hero h2{font-size:38px;}
    }
  </style>
</head>

<body>

<!--  HEADER -->
<header>
   <!--  our logo -->
  <img src="asssets/logo.png" alt="Logo">
  <h1>LULA'S PASTRY</h1>
  <!--  our navigation bar what happens when we press its scroll within the same home page since href=home,about,menu... -->
  <nav>
    <a href="#home">Home</a> <!-- btekhdni aal home section id la ela home ana m3rfta w 3tyta id -->
    <a href="#about">About</a>
    <a href="#menu">Menu</a>
    <a href="#contact">Contact</a>
  </nav>
</header>

<!-- ✅ HOME SECTION -->
<!--  First devision includes title w 3mltla design -->
<div class="hero" id="home">
  <h2>Sweet Moments Start Here</h2>
  <p>
    At Lula’s Pastry, we create freshly baked desserts with love, passion, and the finest
    ingredients to make every moment sweeter.
  </p>

  <div class="btn-area">
    <a href="customer-login.php?reset=1">Customer Login</a>
    <a href="chef-login.php?reset=1">Chef Login</a>
  </div>
</div>

<!--  ABOUT SECTION -->
<section id="about" class="section-bg">
  <h2>About Us</h2>
  <p>
    Lula’s Pastry is all about handmade desserts baked with love.  
    From croissants to cakes, every bite is crafted to bring happiness to your day.
  </p>
</section>

<!--  MENU SECTION -->
<section id="menu" class="section-alt">
  <h2>Our Menu</h2>
  <p>
    Explore our sweet selection of pastries, cakes, pies, and more.  
    Freshly baked daily and ready to make you smile.
  </p>
  <p style="margin-top:15px; font-weight:600;">
    🍰 Tip: Login as a customer to see the full product menu.
  </p>
</section>

<!--  CONTACT SECTION -->
<section id="contact" class="section-bg">
  <h2>Contact Us</h2>
  <p>
    Have a question or want a custom order?  
    We’d love to hear from you!
  </p>

  <p style="margin-top:15px; font-weight:700;">
    📞 Phone: +961 70 493168 <br>
    📧 Email: info@lulaspastry.com <br>
    📍 Location: Bekaa, Lebanon 🇱🇧
  </p>

  <!--  Instagram Link ( REAL PAGE) -->
  <a class="insta-link" href="https://www.instagram.com/lula_pastry/" target="_blank">
    <i class="fa-brands fa-instagram"></i> Visit Our Instagram (@lula_pastry)
  </a>

</section>

<!--  FOOTER -->
  <!-- Footer -->
    <footer>
        © 2025 Lula’s Pastry | Made with ♥
    </footer>

</body>
</html>
