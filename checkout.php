<?php
session_start();

if (
    ($_SESSION['customer_logged_in'] ?? false) !== true ||
    empty($_SESSION['customer_id'])
) {
    header('Location: customer-login.php?reset=1');
    exit();
}

require_once __DIR__ . '/db.php';

$customerId = $_SESSION['customer_id'];
$deliveryCost = 4.00;
$error = '';
$timezone = new DateTimeZone('Asia/Beirut');
$today = new DateTimeImmutable('today', $timezone);
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if (empty($_SESSION['checkout_csrf'])) {
    $_SESSION['checkout_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['checkout_csrf'];

if ($isPost) {
    $token = $_POST['csrf_token'] ?? null;

    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        http_response_code(403);
        exit('Invalid session token. Reload checkout and try again.');
    }
}

$cartItems = [];
$subtotal = 0;
$total = $deliveryCost;

try {
    if ($isPost) {
        $pdo->beginTransaction();
    }

    // Lock cart and product rows while placing the order.
    $sql = 'SELECT c.id, c.dish_id, c.quantity, d.name, d.price,
                   d.is_deleted, d.available, d.stock_quantity
            FROM cart c
            JOIN dishes d ON c.dish_id = d.id
            WHERE c.user_id = :user_id
            ORDER BY c.dish_id, c.id';

    if ($isPost) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $customerId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $available = count($cartItems) > 0;

    foreach ($cartItems as $item) {
        if (
            (int) $item['is_deleted'] !== 0 ||
            (int) $item['available'] !== 1 ||
            (int) $item['quantity'] < 1 ||
            (int) $item['quantity'] > (int) $item['stock_quantity']
        ) {
            $available = false;
        }

        $subtotal += (float) $item['price'] * (int) $item['quantity'];
    }

    $total = round($subtotal + $deliveryCost, 2);

    if (!$available) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $_SESSION['cart_notice'] =
            'Your cart is empty, contains unavailable products, or exceeds current stock. Please review it.';

        header('Location: cart.php', true, 303);
        exit();
    }

    // Detect cart and price changes since checkout was opened.
    $hashItems = $cartItems;

    foreach ($hashItems as &$hashItem) {
        unset($hashItem['stock_quantity']);
    }

    unset($hashItem);

    $cartHash = hash('sha256', serialize($hashItems));

    if (!$isPost) {
        $_SESSION['checkout_cart_hash'] = $cartHash;
    } elseif (
        !hash_equals(
            (string) ($_SESSION['checkout_cart_hash'] ?? ''),
            $cartHash
        )
    ) {
        $pdo->rollBack();

        $_SESSION['cart_notice'] =
            'Your cart or product prices changed. Please review your cart before checking out again.';

        header('Location: cart.php', true, 303);
        exit();
    }

    if ($isPost) {
        $readText = static function ($key) {
            return is_string($_POST[$key] ?? null)
                ? trim($_POST[$key])
                : '';
        };

        $deliveryDate = $readText('delivery_date');
        $paymentMethod = $readText('payment_method');
        $instructions = $readText('instructions');
        $address = $readText('address');
        $phone = $readText('phone');

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $deliveryDate,
            $timezone
        );

        if (
            $deliveryDate === '' ||
            $address === '' ||
            $phone === '' ||
            $paymentMethod === ''
        ) {
            $error = 'Please fill all required fields (Delivery Date, Address, Phone, Payment Method).';
        } elseif (
            !$date ||
            $date->format('Y-m-d') !== $deliveryDate
        ) {
            $error = 'Please enter a valid delivery date.';
        } elseif ($date < $today) {
            $error = 'Delivery date cannot be in the past.';
        } elseif (
            !in_array($paymentMethod, ['cash', 'card'], true)
        ) {
            $error = 'Please choose a valid payment method.';
        } elseif (
            strlen($address) > 255 ||
            strlen($phone) > 30 ||
            strlen($instructions) > 5000
        ) {
            $error = 'Please shorten your address, phone number or instructions.';
        } elseif ($total > 99999999.99) {
            $error = 'This order is too large. Please reduce the quantities in your cart.';
        }

        if ($error !== '') {
            $pdo->rollBack();
        } else {
            $orderNumber = 'ORD-' . strtoupper(bin2hex(random_bytes(8)));

            $orderStmt = $pdo->prepare(
                "INSERT INTO orders
                 (order_number, user_id, total_amount, status, payment_method,
                  payment_status, order_date, delivery_date, special_instructions,
                  delivery_address, customer_phone, delivery_cost, stock_deducted)
                 VALUES (:number, :user, :total, 'pending', :payment,
                         'pending', NOW(), :date, :instructions,
                         :address, :phone, :delivery, 1)"
            );

            $orderStmt->execute([
                ':number' => $orderNumber,
                ':user' => $customerId,
                ':total' => $total,
                ':payment' => $paymentMethod,
                ':date' => $deliveryDate,
                ':instructions' => $instructions,
                ':address' => $address,
                ':phone' => $phone,
                ':delivery' => $deliveryCost,
            ]);

            $orderId = $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items
                 (order_id, dish_id, quantity, price_at_time)
                 VALUES (:order_id, :dish_id, :quantity, :price)'
            );

            $clearStmt = $pdo->prepare(
                'DELETE FROM cart WHERE id = :id AND user_id = :user'
            );

            $stockStmt = $pdo->prepare(
                'UPDATE dishes
                 SET stock_quantity = stock_quantity - :quantity
                 WHERE id = :id
                   AND is_deleted = 0
                   AND available = 1
                   AND stock_quantity >= :required'
            );

            foreach ($cartItems as $item) {
                $stockStmt->execute([
                    ':quantity' => (int) $item['quantity'],
                    ':id' => (int) $item['dish_id'],
                    ':required' => (int) $item['quantity'],
                ]);

                if ($stockStmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Stock changed. Review your cart and try again.'
                    );
                }

                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':dish_id' => $item['dish_id'],
                    ':quantity' => $item['quantity'],
                    ':price' => $item['price'],
                ]);

                // Clear only the rows included in this order.
                $clearStmt->execute([
                    ':id' => $item['id'],
                    ':user' => $customerId,
                ]);
            }

            $pdo->commit();

            unset($_SESSION['checkout_cart_hash']);
            $_SESSION['order_success'] = true;
            $_SESSION['last_order_id'] = $orderId;

            header('Location: order-success.php', true, 303);
            exit();
        }
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Checkout failed: ' . $e->getMessage());

    $error = 'Could not complete checkout. Please reload the page and try again.';
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $_SESSION['cart_notice'] = $e->getMessage();

    header('Location: cart.php', true, 303);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Lula's Pastry</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;600&display=swap"
        rel="stylesheet"
    >

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background: #F8E6DE;
            color: #5D4037;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 15px;
        }

        .checkout-box {
            width: 100%;
            max-width: 950px;
            background: #FFFDFB;
            border-radius: 22px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
            padding: 40px;
            display: grid;
            grid-template-columns: 1.3fr 0.7fr;
            gap: 35px;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 38px;
            color: #C58B78;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #9B6B5A;
            margin-bottom: 25px;
            font-weight: 600;
        }

        label {
            font-weight: 700;
            color: #5D4037;
            display: block;
            margin-bottom: 8px;
            margin-top: 18px;
        }

        input,
        select,
        textarea {
            width: 100%;
            border: 2px solid #F8E6DE;
            border-radius: 12px;
            padding: 12px 14px;
            outline: none;
            font-size: 15px;
            font-family: 'Montserrat', sans-serif;
            transition: 0.3s;
        }

        input:focus,
        select:focus,
        textarea:focus {
            border-color: #C58B78;
            box-shadow: 0 0 8px rgba(197, 139, 120, 0.3);
        }

        textarea {
            min-height: 90px;
            resize: none;
        }

        .btn-primary {
            margin-top: 25px;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #C58B78 0%, #E4B7A0 100%);
            border: none;
            border-radius: 14px;
            color: white;
            font-weight: 800;
            font-size: 16px;
            cursor: pointer;
            transition: 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px rgba(197, 139, 120, 0.35);
        }

        .order-summary {
            background: #F8E6DE;
            border-radius: 18px;
            padding: 25px;
            height: fit-content;
        }

        .order-summary h2 {
            font-family: 'Playfair Display', serif;
            color: #5D4037;
            font-size: 24px;
            margin-bottom: 15px;
            text-align: center;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-weight: 700;
            color: #5D4037;
        }

        .summary-row span {
            color: #9B6B5A;
            font-weight: 600;
        }

        .summary-total {
            border-top: 2px solid rgba(197, 139, 120, 0.4);
            margin-top: 15px;
            padding-top: 15px;
            font-size: 18px;
        }

        .error-box {
            background: #ffd6d6;
            color: #7a1d1d;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            margin-bottom: 15px;
            text-align: center;
        }

        .summary-items {
            list-style: none;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(197, 139, 120, 0.4);
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            padding: 14px 0;
        }

        .summary-item + .summary-item {
            border-top: 1px solid rgba(197, 139, 120, 0.25);
        }

        .item-info {
            min-width: 0;
        }

        .item-name {
            display: block;
            font-size: 14px;
            overflow-wrap: anywhere;
        }

        .item-details {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #795548;
        }

        .item-total {
            font-size: 14px;
            white-space: nowrap;
        }

        .back-cart {
            display: block;
            margin-top: 18px;
            padding: 12px 16px;
            border: 1px solid #C58B78;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            font-weight: 600;
            color: #5D4037;
            background: #FFFDFB;
        }

        .back-cart:hover {
            background: #F8E6DE;
        }

        .back-cart:focus-visible {
            outline: 2px solid #795548;
            outline-offset: 3px;
        }

        @media (max-width: 850px) {
            .checkout-box {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="checkout-box">
    <div>
        <h1>Checkout</h1>

        <div class="subtitle">
            Complete your order and we’ll bake your day 🍰
        </div>

        <?php if ($error !== ''): ?>
            <div class="error-box">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="checkout.php">
            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
            >

            <label>Delivery Date *</label>
            <input
                type="date"
                name="delivery_date"
                min="<?php echo $today->format('Y-m-d'); ?>"
                required
            >

            <label>Address *</label>
            <input
                type="text"
                name="address"
                placeholder="Enter your full address..."
                required
            >

            <label>Phone Number *</label>
            <input
                type="text"
                name="phone"
                placeholder="+961..."
                required
            >

            <label>Payment Method *</label>
            <select name="payment_method" required>
                <option value="cash">Cash</option>
                <option value="card">Card</option>
            </select>

            <label>Special Instructions</label>
            <textarea
                name="instructions"
                placeholder="Example: no nuts, add extra chocolate..."
            ></textarea>

            <button type="submit" class="btn-primary">
                Place Order
            </button>
        </form>

        <a href="cart.php" class="back-cart">← Back to Cart</a>
    </div>

    <div class="order-summary">
        <h2>Order Summary</h2>

        <ul class="summary-items" aria-label="Items in your order">
            <?php foreach ($cartItems as $item): ?>
                <li class="summary-item">
                    <div class="item-info">
                        <strong class="item-name">
                            <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </strong>

                        <span class="item-details">
                            Qty: <?php echo (int) $item['quantity']; ?>
                            × $<?php echo number_format((float) $item['price'], 2); ?> each
                        </span>
                    </div>

                    <strong class="item-total">
                        $<?php echo number_format((float) $item['price'] * (int) $item['quantity'], 2); ?>
                    </strong>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="summary-row">
            <span>Subtotal</span>
            <div>$<?php echo number_format($subtotal, 2); ?></div>
        </div>

        <div class="summary-row">
            <span>Delivery (Fixed)</span>
            <div>$<?php echo number_format($deliveryCost, 2); ?></div>
        </div>

        <div class="summary-row summary-total">
            <span>Total</span>
            <div>
                <strong>$<?php echo number_format($total, 2); ?></strong>
            </div>
        </div>

        <p style="text-align:center;margin-top:20px;font-size:14px;color:#9B6B5A;font-weight:600;">
            Count the memories, not the calories ✨
        </p>
    </div>
</div>
</body>
</html>