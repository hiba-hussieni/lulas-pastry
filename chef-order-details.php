<?php
session_start();

if (!isset($_SESSION["chef_logged_in"]) || $_SESSION["chef_logged_in"] !== true) {
    header("Location: chef-login.php?reset=1");
    exit();
}

require_once "db.php";

/*  Check order id */
if (!isset($_GET["id"])) {
    header("Location: chef-dashboard.php");
    exit();
}

$order_id = intval($_GET["id"]);

/* Get order + customer name */
$sql = "SELECT orders.*, users.full_name, users.phone AS account_phone
        FROM orders
        JOIN users ON orders.user_id = users.id
        WHERE orders.id = :id
        LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute(array(":id" => $order_id));
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: chef-dashboard.php");
    exit();
}

/* Get order items */
$itemSql = "SELECT order_items.*, dishes.name 
            FROM order_items
            JOIN dishes ON order_items.dish_id = dishes.id
            WHERE order_items.order_id = :order_id";

$itemStmt = $pdo->prepare($itemSql);
$itemStmt->execute(array(":order_id" => $order_id));
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

$chefName = isset($_SESSION["chef_name"]) ? $_SESSION["chef_name"] : "Chef";

/* status label class — matches every status in the orders.status enum */
$knownStatusClasses = [
    "pending"   => "pending",
    "confirmed" => "confirmed",
    "preparing" => "preparing",
    "baking"    => "baking",
    "ready"     => "ready",
    "delivered" => "delivered",
    "cancelled" => "cancelled",
];

$statusClass = $knownStatusClasses[$order["status"]] ?? "other";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Details | Lula's Pastry</title>

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
      background: linear-gradient(135deg, #FFFDFB 0%, #F8E6DE 100%);
      padding:20px 40px;
      display:flex;
      justify-content:space-between;
      align-items:center;
      border-bottom:3px solid #C58B78;
      position:sticky;
      top:0;
      z-index:100;
    }

    .logo-area{
      display:flex;
      align-items:center;
      gap:15px;
    }

    .logo{
      width:70px;height:70px;
      object-fit:contain;
      border-radius:50%;
      border:2px solid #C58B78;
      padding:6px;background:white;
    }

    .brand h1{
      font-family:'Playfair Display',serif;
      color:#C58B78;
      font-size:28px;
      letter-spacing:1px;
    }
    .brand p{color:#9B6B5A;font-size:13px;}

    .header-right{
      display:flex;
      gap:20px;
      align-items:center;
    }

    .welcome-text{
      font-weight:700;
      color:#5D4037;
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
      margin-bottom:25px;
    }

    .details-box{
      max-width:1000px;
      margin:0 auto;
      background:#FFFDFB;
      border-radius:20px;
      padding:25px;
      box-shadow:0 10px 25px rgba(0,0,0,0.05);
      border:1px solid rgba(197,139,120,0.2);
    }

    .info{
      display:grid;
      grid-template-columns: repeat(auto-fit,minmax(250px,1fr));
      gap:12px;
      margin-bottom:20px;
      font-size:15px;
      color:#5D4037;
    }

    .status{
      font-weight:800;
      padding:6px 14px;
      border-radius:20px;
      font-size:13px;
      display:inline-block;
      text-transform:capitalize;
    }

    .pending{background:#F8E6DE;color:#795548;}
    .confirmed{background:#EED6CB;color:#69483D;}
    .preparing,.baking{background:#E7C9BB;color:#684638;}
    .ready{background:#DDB5A2;color:#57382D;}
    .delivered{background:#9B6B5A;color:#FFFDFB;}
    .cancelled{background:#EADBD4;color:#704F45;}
    .other{background:#F1E8E2;color:#756055;}

    .notes-box{
      margin-top:15px;
      padding:16px;
      background:#F8E6DE;
      border-radius:14px;
      font-size:14px;
      line-height:1.6;
      white-space:pre-wrap;
    }

    table{
      width:100%;
      border-collapse:collapse;
      margin-top:15px;
      min-width:700px;
    }

    th,td{
      padding:14px;
      text-align:left;
      border-bottom:1px solid rgba(197,139,120,0.2);
      font-size:14px;
    }

    th{
      background:#F8E6DE;
      font-weight:800;
    }

    tfoot td{
      font-weight:800;
    }

    .back-btn{
      margin-top:22px;
      text-align:center;
    }

    .back-btn a{
      text-decoration:none;
      background:linear-gradient(135deg,#C58B78 0%, #E4B7A0 100%);
      color:white;
      padding:12px 25px;
      border-radius:12px;
      font-weight:700;
      display:inline-block;
      transition:0.3s;
    }
    .back-btn a:hover{
      transform:translateY(-2px);
      box-shadow:0 8px 20px rgba(197,139,120,0.3);
    }

    .footer{
      text-align:center;
      margin-top:18px;
      font-size:16px;
      font-weight:700;
      color:#9B6B5A;
    }

    @media(max-width:768px){
      .header{flex-direction:column;gap:15px;}
      .content{padding:20px;}
      table{min-width:600px;}
    }
  </style>
</head>
<body>

<div class="header">
  <div class="logo-area">
    <img src="asssets/logo.png" class="logo" alt="Logo">
    <div class="brand">
      <h1>LULA'S PASTRY</h1>
      <p>Chef Panel</p>
    </div>
  </div>

  <div class="header-right">
    <div class="welcome-text">
      Welcome Chef, <?php echo htmlspecialchars($chefName); ?> 👨‍🍳
    </div>

    <a href="logout-chef.php" class="btn-link">
      <i class="fas fa-sign-out-alt"></i> Logout
    </a>
  </div>
</div>

<div class="content">
  <h2 class="title">Order #<?php echo $order["id"]; ?> Details</h2>

  <div class="details-box">

    <div class="info">
      <div><strong>Customer:</strong> <?php echo htmlspecialchars($order["full_name"]); ?></div>
      <div><strong>Total:</strong> $<?php echo number_format($order["total_amount"], 2); ?></div>
      <div><strong>Status:</strong> <span class="status <?php echo $statusClass; ?>"><?php echo htmlspecialchars($order["status"]); ?></span></div>

      <div><strong>Order Date:</strong> <?php echo htmlspecialchars($order["order_date"]); ?></div>

      <div><strong>Delivery Date:</strong>
        <?php
        if (isset($order["delivery_date"]) && $order["delivery_date"] != "") {
            echo htmlspecialchars($order["delivery_date"]);
        } else {
            echo "-";
        }
        ?>
      </div>

      <div><strong>Payment:</strong>
        <?php
        if (isset($order["payment_method"]) && $order["payment_method"] != "") {
            echo htmlspecialchars($order["payment_method"]);
        } else {
            echo "-";
        }
        ?>
        <?php if (isset($order["payment_status"]) && $order["payment_status"] != ""): ?>
          (<?php echo htmlspecialchars($order["payment_status"]); ?>)
        <?php endif; ?>
      </div>

      <div><strong>Delivery Address:</strong>
        <?php
        if (isset($order["delivery_address"]) && $order["delivery_address"] != "") {
            echo htmlspecialchars($order["delivery_address"]);
        } else {
            echo "-";
        }
        ?>
      </div>

      <div><strong>Customer Phone:</strong>
        <?php
        if (isset($order["customer_phone"]) && $order["customer_phone"] != "") {
            echo htmlspecialchars($order["customer_phone"]);
        } elseif (isset($order["account_phone"]) && $order["account_phone"] != "") {
            echo htmlspecialchars($order["account_phone"]);
        } else {
            echo "-";
        }
        ?>
      </div>

      <div><strong>Delivery Fee:</strong>
        $<?php echo number_format($order["delivery_cost"] ?? 0, 2); ?>
      </div>
    </div>

    <?php if (isset($order["special_instructions"]) && trim($order["special_instructions"]) !== ""): ?>
      <h3 style="margin-top:15px;">Special Instructions</h3>
      <div class="notes-box">
        <?php echo nl2br(htmlspecialchars($order["special_instructions"])); ?>
      </div>
    <?php endif; ?>

    <h3 style="margin-top:15px;">Items Ordered</h3>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Total</th>
          </tr>
        </thead>
        <tbody>

        <?php if (count($items) === 0): ?>
          <tr>
            <td colspan="4" style="text-align:center; padding:25px;">No items found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?php echo htmlspecialchars($it["name"]); ?></td>
              <td><?php echo htmlspecialchars($it["quantity"]); ?></td>
              <td>$<?php echo number_format($it["price_at_time"], 2); ?></td>
              <td>$<?php echo number_format($it["price_at_time"] * $it["quantity"], 2); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>

        </tbody>
        <?php if (count($items) > 0): ?>
        <tfoot>
          <tr>
            <td colspan="3" style="text-align:right;">Delivery Fee</td>
            <td>$<?php echo number_format($order["delivery_cost"] ?? 0, 2); ?></td>
          </tr>
          <tr>
            <td colspan="3" style="text-align:right;">Order Total</td>
            <td>$<?php echo number_format($order["total_amount"], 2); ?></td>
          </tr>
        </tfoot>
        <?php endif; ?>
      </table>
    </div>

    <div class="back-btn">
      <a href="chef-dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

  </div>

  <p class="footer">Stay Sweet 🍰</p>

</div>

</body>
</html>