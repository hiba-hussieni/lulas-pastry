<?php
session_start();

if (
    ($_SESSION["customer_logged_in"] ?? false) !== true ||
    empty($_SESSION["customer_id"])
) {
    header("Location: customer-login.php?reset=1");
    exit();
}

require_once "db.php";

$user_id = $_SESSION["customer_id"];
$customerName = isset($_SESSION["customer_name"])
    ? $_SESSION["customer_name"]
    : "Customer";

/* ADD TO CART */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["dish_id"])) {
    $dish_id = filter_var(
        $_POST["dish_id"],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $quantity = filter_var(
        $_POST["quantity"] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
    );

    if (!$dish_id || !$quantity) {
        $_SESSION['cart_notice'] = 'Please choose a valid product and quantity.';
        header('Location: cart.php', true, 303);
        exit();
    }

    try {
        $pdo->beginTransaction();

        $productStmt = $pdo->prepare(
            'SELECT id, stock_quantity
             FROM dishes
             WHERE id = :id AND is_deleted = 0 AND available = 1
             FOR UPDATE'
        );

        $productStmt->execute([':id' => $dish_id]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $pdo->rollBack();
            $_SESSION['cart_notice'] = 'This product is no longer available.';
        } else {
            $checkStmt = $pdo->prepare(
                'SELECT id, quantity FROM cart
                 WHERE user_id = :user_id AND dish_id = :dish_id
                 FOR UPDATE'
            );

            $checkStmt->execute([
                ':user_id' => $user_id,
                ':dish_id' => $dish_id,
            ]);

            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            $newQty = (int) ($existing['quantity'] ?? 0) + $quantity;

            if ($newQty > 2147483647) {
                $pdo->rollBack();
                $_SESSION['cart_notice'] = 'The requested quantity is too large.';
            } elseif ($newQty > (int) $product['stock_quantity']) {
                $pdo->rollBack();

                $_SESSION['cart_notice'] =
                    'Only ' . (int) $product['stock_quantity']
                    . ' units are in stock. This limit includes the units already in your cart.';
            } else {
                if ($existing) {
                    $stmt = $pdo->prepare(
                        'UPDATE cart SET quantity = :qty
                         WHERE id = :id AND user_id = :user_id'
                    );

                    $stmt->execute([
                        ':qty' => $newQty,
                        ':id' => $existing['id'],
                        ':user_id' => $user_id,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO cart (user_id, dish_id, quantity)
                         VALUES (:user_id, :dish_id, :qty)'
                    );

                    $stmt->execute([
                        ':user_id' => $user_id,
                        ':dish_id' => $dish_id,
                        ':qty' => $quantity,
                    ]);
                }

                $pdo->commit();
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Add to cart failed: ' . $e->getMessage());
        $_SESSION['cart_notice'] = 'Could not update your cart. Please try again.';
    }

    header('Location: cart.php', true, 303);
    exit();
}

/* SAVE QUANTITIES BEFORE UPDATE OR CHECKOUT */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['update_cart'])
) {
    $quantities = $_POST['quantities'] ?? null;
    $goToCheckout = ($_POST['proceed_checkout'] ?? '') === '1';
    $saved = false;

    try {
        if (!is_array($quantities) || !$quantities) {
            throw new InvalidArgumentException('Please enter valid quantities.');
        }

        $requested = [];

        foreach ($quantities as $cartId => $qty) {
            $cartId = filter_var(
                $cartId,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            $qty = filter_var(
                $qty,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
            );

            if (!$cartId || !$qty) {
                throw new InvalidArgumentException(
                    'Quantities must be positive whole numbers.'
                );
            }

            $requested[$cartId] = $qty;
        }

        $pdo->beginTransaction();

        $lock = $pdo->prepare(
            'SELECT cart.id AS cart_id,
                    dishes.name,
                    dishes.stock_quantity,
                    dishes.available,
                    dishes.is_deleted
             FROM cart
             JOIN dishes ON cart.dish_id = dishes.id
             WHERE cart.user_id = :user_id
             ORDER BY dishes.id ASC, cart.id ASC
             FOR UPDATE'
        );

        $lock->execute([':user_id' => $user_id]);
        $rows = $lock->fetchAll(PDO::FETCH_ASSOC);
        $owned = [];

        foreach ($rows as $row) {
            $owned[(int) $row['cart_id']] = $row;
        }

        // Require the same cart that the customer reviewed.
        if (count($requested) !== count($owned)) {
            throw new InvalidArgumentException(
                'Your cart changed. Review it and try again.'
            );
        }

        // Validate every item before saving any changes.
        foreach ($requested as $cartId => $qty) {
            if (!isset($owned[$cartId])) {
                throw new InvalidArgumentException(
                    'Your cart changed. Reload it and try again.'
                );
            }

            $row = $owned[$cartId];

            if (
                (int) $row['is_deleted'] !== 0 ||
                (int) $row['available'] !== 1
            ) {
                throw new InvalidArgumentException(
                    $row['name'] . ' is unavailable. Remove it from your cart.'
                );
            }

            if ($qty > (int) $row['stock_quantity']) {
                throw new InvalidArgumentException(
                    $row['name'] . ': only ' . (int) $row['stock_quantity']
                    . ' units are in stock. Reduce the quantity or remove the item.'
                );
            }
        }

        $update = $pdo->prepare(
            'UPDATE cart
             SET quantity = :qty
             WHERE id = :id AND user_id = :user_id'
        );

        foreach ($requested as $cartId => $qty) {
            $update->execute([
                ':qty' => $qty,
                ':id' => $cartId,
                ':user_id' => $user_id,
            ]);
        }

        $pdo->commit();
        $saved = true;

        unset($_SESSION['checkout_cart_hash']);

        if ($goToCheckout) {
            unset($_SESSION['cart_notice']);
        } else {
            $_SESSION['cart_notice'] = 'Cart quantities updated.';
        }
    } catch (InvalidArgumentException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['cart_notice'] = $e->getMessage();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Cart quantity update failed: ' . $e->getMessage());
        $_SESSION['cart_notice'] = 'Could not update your cart. Please try again.';
    }

    if ($saved && $goToCheckout) {
        header('Location: checkout.php', true, 303);
    } else {
        header('Location: cart.php', true, 303);
    }

    exit();
}

/* REMOVE ITEM */
if (isset($_GET["remove"])) {
    $remove_id = intval($_GET["remove"]);

    $deleteSql = "DELETE FROM cart WHERE id = :id AND user_id = :user_id";
    $deleteStmt = $pdo->prepare($deleteSql);

    $deleteStmt->execute([
        ":id" => $remove_id,
        ":user_id" => $user_id
    ]);

    header("Location: cart.php");
    exit();
}

/* GET CART ITEMS */
$sql = "SELECT cart.id AS cart_id, cart.quantity,
               dishes.name, dishes.description, dishes.price, dishes.image_url,
               dishes.stock_quantity, dishes.available, dishes.is_deleted
        FROM cart
        JOIN dishes ON cart.dish_id = dishes.id
        WHERE cart.user_id = :user_id
        ORDER BY cart.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([":user_id" => $user_id]);
$cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cartNotice = $_SESSION['cart_notice'] ?? '';
unset($_SESSION['cart_notice']);

$total = 0;
$hasStockIssue = false;

foreach ($cartItems as $item) {
    if (
        (int) $item['is_deleted'] !== 0 ||
        (int) $item['available'] !== 1 ||
        (int) $item['quantity'] < 1 ||
        (int) $item['quantity'] > (int) $item['stock_quantity']
    ) {
        $hasStockIssue = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cart | Lula's Pastry</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">

  <style>
    * { margin:0; padding:0; box-sizing:border-box; }

    body {
      font-family: 'Montserrat', sans-serif;
      background-color: #F8E6DE;
      color:#5D4037;
    }

    .header {
      background: linear-gradient(135deg, #FFFDFB 0%, #F8E6DE 100%);
      padding: 20px 40px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 3px solid #C58B78;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .logo-area {
      display: flex;
      align-items: center;
      gap: 15px;
    }

    .logo {
      width: 70px;
      height: 70px;
      object-fit: contain;
      border-radius: 50%;
      border: 2px solid #C58B78;
      padding: 6px;
      background: white;
    }

    .brand h1 {
      font-family: 'Playfair Display', serif;
      color: #C58B78;
      font-size: 28px;
      letter-spacing: 1px;
    }

    .brand p {
      color: #9B6B5A;
      font-size: 13px;
      letter-spacing: 1px;
    }

    .header-right {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .welcome-text {
      font-weight: 600;
      color: #5D4037;
      text-align: right;
    }

    .btn-link {
      text-decoration: none;
      background: #C58B78;
      color: white;
      padding: 10px 20px;
      border-radius: 25px;
      font-weight: 600;
      transition: 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .btn-link:hover { background: #9B6B5A; }

    .cart-section { padding: 40px; }

    .section-title {
      font-family: 'Playfair Display', serif;
      font-size: 34px;
      text-align: center;
      color: #5D4037;
      margin-bottom: 40px;
      position: relative;
    }

    .section-title::after {
      content: "";
      display: block;
      margin: 12px auto 0;
      width: 80px;
      height: 3px;
      background: #C58B78;
      border-radius: 2px;
    }

    .cart-box {
      background: #FFFDFB;
      border-radius: 20px;
      padding: 30px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
      border: 1px solid rgba(197, 139, 120, 0.2);
    }

    .cart-item {
      display: flex;
      gap: 20px;
      border-bottom: 1px solid rgba(197,139,120,0.2);
      padding: 20px 0;
      align-items: center;
    }

    .cart-item:last-child { border-bottom: none; }

    .cart-img {
      width: 120px;
      height: 90px;
      border-radius: 12px;
      object-fit: cover;
      border: 2px solid #F8E6DE;
      background: white;
    }

    .cart-info { flex: 1; }

    .cart-info h3 {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 6px;
    }

    .cart-info p {
      font-size: 13px;
      color: #9B6B5A;
      margin-bottom: 8px;
    }

    .cart-price {
      font-weight: 700;
      color: #C58B78;
      font-size: 16px;
    }

    .qty-input {
      width: 70px;
      padding: 8px;
      border-radius: 10px;
      border: 2px solid #F8E6DE;
      outline: none;
      font-size: 14px;
      text-align: center;
    }

    .remove-btn {
      text-decoration: none;
      background: none;
      color: #C58B78;
      font-weight: 700;
      border: 1px solid #C58B78;
      padding: 8px 14px;
      border-radius: 20px;
      transition: 0.3s;
    }

    .remove-btn:hover {
      background: #C58B78;
      color: white;
    }

    .total-box {
      text-align: right;
      margin-top: 25px;
      font-size: 20px;
      font-weight: 800;
      color: #5D4037;
    }

    .cart-actions {
      display: flex;
      gap: 15px;
      margin-top: 25px;
      justify-content: space-between;
      flex-wrap: wrap;
    }

    .btn-primary {
      padding: 14px 24px;
      background: linear-gradient(135deg, #C58B78 0%, #E4B7A0 100%);
      border: none;
      color: white;
      border-radius: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: 0.3s;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(197, 139, 120, 0.3);
    }

    .btn-secondary {
      padding: 14px 24px;
      background: #F8E6DE;
      border: 2px solid #C58B78;
      color: #5D4037;
      border-radius: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: 0.3s;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }

    .btn-secondary:hover {
      background: #C58B78;
      color: white;
      transform: translateY(-2px);
    }

    .empty-cart {
      text-align: center;
      padding: 50px 20px;
      color: #9B6B5A;
      font-size: 16px;
    }

    @media(max-width:768px) {
      .header { flex-direction: column; gap: 15px; }
      .cart-section { padding: 20px; }
      .cart-item { flex-direction: column; align-items: flex-start; }
      .total-box { text-align: left; }
    }
  </style>
</head>

<body>
  <div class="header">
    <div class="logo-area">
      <img src="asssets/logo.png" class="logo" alt="Logo">

      <div class="brand">
        <h1>LULA'S PASTRY</h1>
        <p>Freshly Baked Happiness</p>
      </div>
    </div>

    <div class="header-right">
      <div class="welcome-text">
        Welcome, <?php echo htmlspecialchars($customerName); ?>! <br>

        <span style="font-size:13px; color:#9B6B5A;">
          Count the memories, not the calories 🍰
        </span>
      </div>

      <a href="products.php" class="btn-link">
        <i class="fas fa-store"></i> Products
      </a>

      <a href="logout-customer.php" class="btn-link">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </div>
  </div>

  <section class="cart-section">
    <h2 class="section-title">Your Cart</h2>

    <div class="cart-box">
      <?php if ($cartNotice !== ''): ?>
        <p
          role="alert"
          style="padding:14px; margin-bottom:20px; border-radius:10px; background:#F8E6DE; color:#5D4037;"
        >
          <?php echo htmlspecialchars($cartNotice, ENT_QUOTES, 'UTF-8'); ?>
        </p>
      <?php endif; ?>

      <?php if (count($cartItems) === 0): ?>
        <div class="empty-cart">
          🛒 Your cart is empty! <br><br>
          <a href="products.php" class="btn-primary">Browse Products</a>
        </div>
      <?php else: ?>
        <form method="POST" action="cart.php">
          <input type="hidden" name="update_cart" value="1">

          <?php foreach ($cartItems as $item): ?>
            <?php
              $img = !empty($item["image_url"])
                  ? $item["image_url"]
                  : "asssets/logo.png";

              $subtotal = $item["price"] * $item["quantity"];
              $total += $subtotal;
            ?>

            <div class="cart-item">
              <img
                src="<?php echo htmlspecialchars($img); ?>"
                class="cart-img"
                alt="<?php echo htmlspecialchars($item["name"]); ?>"
              >

              <div class="cart-info">
                <h3><?php echo htmlspecialchars($item["name"]); ?></h3>
                <p><?php echo htmlspecialchars($item["description"]); ?></p>

                <div class="cart-price">
                  $<?php echo number_format($item["price"], 2); ?>
                </div>

                <p style="margin-top:10px;">
                  <?php if (
                    (int) $item['is_deleted'] !== 0 ||
                    (int) $item['available'] !== 1
                  ): ?>
                    This product is unavailable. Please remove it.
                  <?php else: ?>
                    In stock: <?php echo (int) $item['stock_quantity']; ?> units.

                    <?php if (
                      (int) $item['quantity'] > (int) $item['stock_quantity']
                    ): ?>
                      Reduce the quantity or remove this item before checkout.
                    <?php endif; ?>
                  <?php endif; ?>
                </p>

                <div style="margin-top:10px;">
                  Qty:
                  <input
                    type="number"
                    name="quantities[<?php echo (int) $item["cart_id"]; ?>]"
                    value="<?php echo (int) $item["quantity"]; ?>"
                    min="1"
                    step="1"
                    max="<?php echo max(1, (int) $item['stock_quantity']); ?>"
                    required
                    aria-label="Quantity for <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>"
                    class="qty-input"
                  >
                </div>
              </div>

              <a
                href="cart.php?remove=<?php echo (int) $item["cart_id"]; ?>"
                class="remove-btn"
              >
                <i class="fas fa-trash"></i> Remove
              </a>
            </div>
          <?php endforeach; ?>

          <div class="total-box">
            Total: $<?php echo number_format($total, 2); ?>
          </div>

          <div class="cart-actions">
            <button type="submit" class="btn-secondary">
              <i class="fas fa-sync"></i> Update Cart
            </button>

            <?php if ($hasStockIssue): ?>
              <span class="btn-secondary" role="status">
                Resolve stock issues to continue
              </span>
            <?php else: ?>
              <button
                type="submit"
                name="proceed_checkout"
                value="1"
                class="btn-primary"
              >
                <i class="fas fa-credit-card"></i> Proceed to Checkout
              </button>
            <?php endif; ?>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>
</body>
</html>