<?php
session_start();

/* Only allow page if order was just placed */
if (!isset($_SESSION["order_success"]) || $_SESSION["order_success"] !== true) {
    header("Location: products.php");
    exit();
}

/*  Get last order id */
$lastOrderId = isset($_SESSION["last_order_id"]) ? $_SESSION["last_order_id"] : null;

/* reset success so it doesn't repeat forever */
$_SESSION["order_success"] = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Success | Lula's Pastry</title>

  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap" rel="stylesheet">

  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    body{
      font-family:'Montserrat',sans-serif;
      background:#F8E6DE;
      display:flex;
      justify-content:center;
      align-items:center;
      min-height:100vh;
      padding:20px;
      color:#5D4037;
    }

    .success-box{
      background:#FFFDFB;
      width:100%;
      max-width:650px;
      border-radius:25px;
      padding:55px 40px;
      text-align:center;
      box-shadow:0 15px 40px rgba(0,0,0,0.08);
    }

    h1{
      font-family:'Playfair Display',serif;
      font-size:42px;
      color:#C58B78;
      margin-bottom:18px;
    }

    p{
      font-size:16px;
      line-height:1.8;
      color:#9B6B5A;
      margin-bottom:18px;
      font-weight:600;
    }

    .order-id{
      margin-top:10px;
      display:inline-block;
      padding:10px 18px;
      border-radius:14px;
      background:#F8E6DE;
      color:#5D4037;
      font-weight:800;
      font-size:15px;
    }

    .btn{
      margin-top:30px;
      display:inline-block;
      padding:14px 26px;
      text-decoration:none;
      background:linear-gradient(135deg,#C58B78 0%,#E4B7A0 100%);
      color:white;
      font-weight:800;
      border-radius:14px;
      transition:0.3s;
    }

    .btn:hover{
      transform:translateY(-2px);
      box-shadow:0 10px 22px rgba(197,139,120,0.35);
    }
  </style>
</head>
<body>

<div class="success-box">
  <h1>Order Placed Successfully!</h1>

  <p>Thank you for choosing Lula's Pastry 💕</p>
  <p>We bake your day! 🍰✨</p>
  <p>Your order is now being prepared and will be confirmed soon.</p>

  <?php if($lastOrderId != null): ?>
    <div class="order-id">✅ Your Order ID: #<?php echo htmlspecialchars($lastOrderId); ?></div>
  <?php endif; ?>

  <br>

  <a href="products.php" class="btn">⬅ Back to Products</a>
</div>

</body>
</html>
