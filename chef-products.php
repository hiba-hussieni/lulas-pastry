<?php
session_start();

if (!isset($_SESSION["chef_logged_in"]) || $_SESSION["chef_logged_in"] !== true) {
    header("Location: chef-login.php?reset=1");
    exit();
}

require_once "db.php";

$sql = "SELECT id, name, description, price, category, image_url, ingredients
        FROM dishes
        WHERE is_deleted = 0
        ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chef Products | Lula's Pastry</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">

  <style>
    *{margin:0;padding:0;box-sizing:border-box;}

    body{
      font-family:'Montserrat',sans-serif;
      background:#F8E6DE;
      color:#5D4037;
    }

    .header{
      background:linear-gradient(135deg,#FFFDFB 0%,#F8E6DE 100%);
      padding:20px 40px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      border-bottom:3px solid #C58B78;
      position:sticky;
      top:0;
      z-index:100;
    }

    .btn-link{
      text-decoration:none;
      background:#C58B78;
      color:white;
      padding:10px 20px;
      border-radius:25px;
      font-weight:700;
      transition:0.3s;
      display:inline-flex;
      gap:8px;
      align-items:center;
    }

    .btn-link:hover{background:#9B6B5A;}

    .content{padding:40px;}

    .title{
      font-family:'Playfair Display',serif;
      font-size:34px;
      text-align:center;
      margin-bottom:30px;
    }

    .grid{
      display:grid;
      grid-template-columns:repeat(auto-fill,minmax(280px,1fr));
      gap:25px;
    }

    .card{
      background:#FFFDFB;
      border-radius:18px;
      overflow:hidden;
      box-shadow:0 10px 25px rgba(0,0,0,0.05);
      border:1px solid rgba(197,139,120,0.2);
    }

    .card img{
      width:100%;
      height:180px;
      object-fit:cover;
      background:white;
    }

    .info{padding:18px;}

    .info h3{
      font-size:18px;
      font-weight:800;
      margin-bottom:6px;
    }

    .price{
      font-weight:800;
      color:#C58B78;
      margin-bottom:8px;
    }

    .desc{
      font-size:13px;
      color:#9B6B5A;
      line-height:1.6;
      margin-bottom:10px;
    }

    .ing{
      font-size:13px;
      color:#5D4037;
      font-weight:700;
    }
  </style>
</head>
<body>

<div class="header">
  <a href="chef-dashboard.php" class="btn-link">
    <i class="fas fa-arrow-left"></i> Back
  </a>

  <a href="logout-chef.php" class="btn-link">
    <i class="fas fa-sign-out-alt"></i> Logout
  </a>
</div>

<div class="content">
  <h2 class="title">All Products (Chef View)</h2>

  <div class="grid">
    <?php foreach ($dishes as $dish): ?>
      <?php
        $img = (!empty($dish["image_url"]))
            ? $dish["image_url"]
            : "asssets/logo.png";
      ?>

      <div class="card">
        <img
          src="<?php echo htmlspecialchars($img); ?>"
          alt="<?php echo htmlspecialchars($dish["name"]); ?>"
        >

        <div class="info">
          <h3><?php echo htmlspecialchars($dish["name"]); ?></h3>

          <div class="price">
            $<?php echo number_format($dish["price"], 2); ?>
          </div>

          <div class="desc">
            <?php echo htmlspecialchars($dish["description"]); ?>
          </div>

          <div class="ing">
            Ingredients: <?php echo htmlspecialchars($dish["ingredients"]); ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

</body>
</html>