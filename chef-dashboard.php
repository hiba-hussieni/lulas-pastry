<?php
session_start();

if (
    !isset($_SESSION["chef_logged_in"]) ||
    $_SESSION["chef_logged_in"] !== true
) {
    header("Location: chef-login.php?reset=1");
    exit();
}

require_once "db.php";

$statusLabels = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'preparing' => 'Preparing',
    'baking' => 'Baking',
    'ready' => 'Ready',
    'delivered' => 'Delivered',
    'cancelled' => 'Cancelled',
];

$transitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['preparing', 'cancelled'],
    'preparing' => ['baking', 'cancelled'],
    'baking' => ['ready', 'cancelled'],
    'ready' => ['delivered', 'cancelled'],
    'delivered' => [],
    'cancelled' => [],
];

if (empty($_SESSION['order_status_csrf'])) {
    $_SESSION['order_status_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['order_status_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;

    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        http_response_code(403);
        exit('Your session token is invalid. Reload the dashboard and try again.');
    }

    if (($_POST['action'] ?? '') === 'reorder_dish') {
        $dishId = filter_var(
            $_POST['dish_id'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $direction = $_POST['direction'] ?? '';
        $expectedOrder = $_POST['order_hash'] ?? null;
        $moveSuccess = false;
        $moveMessage = 'Invalid product or direction.';

        if (
            $dishId &&
            in_array($direction, ['up', 'down'], true) &&
            is_string($expectedOrder)
        ) {
            try {
                $pdo->beginTransaction();

                $rows = $pdo->query(
                    'SELECT id FROM dishes WHERE is_deleted = 0
                     ORDER BY sort_order ASC, id ASC FOR UPDATE'
                )->fetchAll(PDO::FETCH_ASSOC);

                $ids = array_map('intval', array_column($rows, 'id'));
                $position = array_search($dishId, $ids, true);
                $currentHash = hash('sha256', implode(',', $ids));

                if (!hash_equals($currentHash, $expectedOrder)) {
                    $moveMessage = 'The product list changed. Please try again.';
                    $pdo->rollBack();
                } elseif ($position === false) {
                    $moveMessage = 'This product is no longer available in the list.';
                    $pdo->rollBack();
                } else {
                    $neighbor = $position + ($direction === 'up' ? -1 : 1);

                    if ($neighbor < 0 || $neighbor >= count($ids)) {
                        $moveMessage = 'This product is already at the edge of the list.';
                        $pdo->rollBack();
                    } else {
                        [$ids[$position], $ids[$neighbor]] = [
                            $ids[$neighbor],
                            $ids[$position]
                        ];

                        $savePosition = $pdo->prepare(
                            'UPDATE dishes SET sort_order = :position
                             WHERE id = :id AND is_deleted = 0'
                        );

                        foreach ($ids as $index => $id) {
                            $savePosition->execute([
                                ':position' => $index + 1,
                                ':id' => $id
                            ]);
                        }

                        $pdo->commit();

                        $moveSuccess = true;
                        $moveMessage = 'Product order saved.';
                    }
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Product reorder failed: ' . $e->getMessage());
                $moveMessage = 'Could not save the order. Please try again.';
            }
        }

        $_SESSION['dish_order_flash'] = [
            'success' => $moveSuccess,
            'message' => $moveMessage
        ];

        header('Location: chef-dashboard.php#manage-dishes', true, 303);
        exit();
    }

    $orderId = filter_var(
        $_POST['order_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $targetStatus = $_POST['status'] ?? null;
    $expectedStatus = $_POST['expected_status'] ?? null;
    $message = 'Invalid order or status.';
    $success = false;

    if (
        $orderId !== false &&
        $orderId !== null &&
        is_string($targetStatus) &&
        is_string($expectedStatus) &&
        isset($statusLabels[$targetStatus])
    ) {
        try {
            $pdo->beginTransaction();

            // Lock the order so stock cannot be returned twice.
            $find = $pdo->prepare(
                'SELECT status, stock_deducted FROM orders
                 WHERE id = :id FOR UPDATE'
            );

            $find->execute([':id' => $orderId]);
            $currentOrder = $find->fetch(PDO::FETCH_ASSOC);

            if (!$currentOrder) {
                $message = 'Order not found.';
            } else {
                $currentStatus = (string) ($currentOrder['status'] ?? '');

                $allowed = $transitions[$currentStatus]
                    ?? ['pending', 'cancelled'];

                if ($expectedStatus !== $currentStatus) {
                    $message = 'This order changed. Review its latest status and try again.';
                } elseif (!in_array($targetStatus, $allowed, true)) {
                    $message = 'This status change is not allowed.';
                } else {
                    $returnStock = $targetStatus === 'cancelled'
                        && (int) $currentOrder['stock_deducted'] === 1;

                    if ($returnStock) {
                        $itemsStmt = $pdo->prepare(
                            'SELECT dish_id, SUM(quantity) AS quantity
                             FROM order_items
                             WHERE order_id = :id
                             GROUP BY dish_id
                             ORDER BY dish_id ASC'
                        );

                        $itemsStmt->execute([':id' => $orderId]);
                        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!$items) {
                            throw new RuntimeException('Missing order items.');
                        }

                        $restoreStock = $pdo->prepare(
                            'UPDATE dishes
                             SET stock_quantity = stock_quantity + :quantity
                             WHERE id = :id'
                        );

                        foreach ($items as $item) {
                            $quantity = (int) $item['quantity'];

                            if ($quantity < 1) {
                                throw new RuntimeException('Invalid order quantity.');
                            }

                            // This does not make hidden or deleted products visible.
                            $restoreStock->execute([
                                ':quantity' => $quantity,
                                ':id' => (int) $item['dish_id'],
                            ]);

                            if ($restoreStock->rowCount() !== 1) {
                                throw new RuntimeException('Product no longer exists.');
                            }
                        }
                    }

                    $update = $pdo->prepare(
                        'UPDATE orders
                         SET status = :next, stock_deducted = :deducted
                         WHERE id = :id AND status <=> :previous'
                    );

                    $update->execute([
                        ':next' => $targetStatus,
                        ':deducted' => $returnStock
                            ? 0
                            : (int) $currentOrder['stock_deducted'],
                        ':id' => $orderId,
                        ':previous' => $currentOrder['status'],
                    ]);

                    if ($update->rowCount() !== 1) {
                        throw new RuntimeException(
                            'Order status could not be updated.'
                        );
                    }

                    $pdo->commit();

                    $success = true;
                    $message = 'Order #' . $orderId . ' updated to '
                        . $statusLabels[$targetStatus] . '.'
                        . ($returnStock ? ' Stock returned.' : '');
                }
            }

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $success = false;

            error_log('Order status update failed: ' . $e->getMessage());
            $message = 'Could not update the order. Please try again.';
        }
    }

    $_SESSION['order_status_flash'] = [
        'message' => $message,
        'success' => $success,
    ];

    header('Location: chef-dashboard.php', true, 303);
    exit();
}

$flash = $_SESSION['order_status_flash'] ?? null;
unset($_SESSION['order_status_flash']);

$sql = "SELECT orders.*, users.full_name
        FROM orders
        LEFT JOIN users ON orders.user_id = users.id
        ORDER BY orders.order_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$dishSql = "SELECT id, name, price, image_url, available
            FROM dishes
            WHERE is_deleted = 0
            ORDER BY sort_order ASC, id ASC";

$dishStmt = $pdo->prepare($dishSql);
$dishStmt->execute();
$dishes = $dishStmt->fetchAll(PDO::FETCH_ASSOC);

$dishOrderHash = hash(
    'sha256',
    implode(',', array_map('intval', array_column($dishes, 'id')))
);

$dishFlash = $_SESSION['dish_order_flash'] ?? null;
unset($_SESSION['dish_order_flash']);

$productStats = $pdo->query(
    "SELECT COUNT(*) AS total,
            SUM(CASE WHEN available = 1 THEN 1 ELSE 0 END) AS available_count
     FROM dishes
     WHERE is_deleted = 0"
)->fetch(PDO::FETCH_ASSOC);

$totalProducts = (int) ($productStats['total'] ?? 0);
$availableProducts = (int) ($productStats['available_count'] ?? 0);
$totalOrders = count($orders);

$paymentStats = $pdo->query(
    "SELECT payment_status,
            COUNT(*) AS order_count,
            COALESCE(SUM(total_amount), 0) AS total_sales
     FROM orders
     WHERE status != 'cancelled'
     GROUP BY payment_status"
)->fetchAll(PDO::FETCH_ASSOC);

$paidCount = 0;
$paidSales = 0.0;
$pendingCount = 0;
$pendingSales = 0.0;

foreach ($paymentStats as $row) {
    $status = $row['payment_status'] ?? 'pending';

    if ($status === 'paid') {
        $paidCount = (int) $row['order_count'];
        $paidSales = (float) $row['total_sales'];
    } else {
        $pendingCount += (int) $row['order_count'];
        $pendingSales += (float) $row['total_sales'];
    }
}

$chefName = $_SESSION["chef_name"] ?? "Chef";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chef Dashboard | Lula's Pastry</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        * { margin:0; padding:0; box-sizing:border-box; }

        body {
            font-family:'Montserrat',sans-serif;
            background:#F8E6DE;
            color:#5D4037;
        }

        .header {
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

        .logo-area { display:flex; align-items:center; gap:15px; }

        .logo {
            width:70px;
            height:70px;
            object-fit:contain;
            border-radius:50%;
            border:2px solid #C58B78;
            padding:6px;
            background:white;
        }

        .brand h1 {
            font-family:'Playfair Display',serif;
            color:#C58B78;
            font-size:28px;
        }

        .brand p { color:#9B6B5A; font-size:13px; }
        .header-right { display:flex; gap:20px; align-items:center; }
        .welcome-text { font-weight:700; color:#5D4037; text-align:right; }

        .btn-link {
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

        .btn-link:hover { background:#9B6B5A; }
        .content { padding:40px; }

        .title {
            font-family:'Playfair Display',serif;
            font-size:34px;
            text-align:center;
            margin-bottom:25px;
        }

        .top-actions {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:25px;
            flex-wrap:wrap;
            gap:15px;
        }

        .btn-action {
            text-decoration:none;
            padding:12px 20px;
            border-radius:25px;
            font-weight:700;
            display:inline-flex;
            align-items:center;
            gap:10px;
            background:#C58B78;
            color:white;
            transition:0.3s;
        }

        .btn-action:hover { background:#9B6B5A; }

        .stats-grid {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
            gap:18px;
            margin-bottom:30px;
        }

        .stat-card {
            background:#FFFDFB;
            border-radius:18px;
            padding:20px 22px;
            box-shadow:0 8px 20px rgba(93,64,55,0.06);
            border:1px solid rgba(197,139,120,0.2);
            display:flex;
            align-items:center;
            gap:16px;
        }

        .stat-icon {
            width:44px;
            height:44px;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:17px;
            flex-shrink:0;
            background:#F8E6DE;
            color:#C58B78;
        }

        .stat-icon.paid { background:#9B6B5A; color:#FFFDFB; }
        .stat-icon.pending { background:#EED6CB; color:#69483D; }

        .stat-number {
            font-family:'Playfair Display',serif;
            font-size:24px;
            font-weight:700;
            line-height:1.15;
        }

        .stat-label {
            font-size:12px;
            font-weight:700;
            color:#9B6B5A;
            text-transform:uppercase;
            letter-spacing:0.4px;
        }

        .stat-sub { font-size:12px; color:#9B6B5A; margin-top:2px; }

        .table-box {
            background:#FFFDFB;
            border-radius:20px;
            padding:25px;
            box-shadow:0 10px 25px rgba(0,0,0,0.05);
            border:1px solid rgba(197,139,120,0.2);
            overflow-x:auto;
            margin-bottom:35px;
        }

        table {
            width:100%;
            border-collapse:collapse;
            min-width:900px;
        }

        th,td {
            padding:14px;
            text-align:left;
            border-bottom:1px solid rgba(197,139,120,0.2);
            font-size:14px;
        }

        th { font-weight:800; background:#F8E6DE; }

        .status {
            font-weight:800;
            padding:6px 12px;
            border-radius:20px;
            font-size:13px;
            display:inline-block;
            text-transform:capitalize;
        }

        .pending { background:#F8E6DE; color:#795548; }
        .confirmed { background:#EED6CB; color:#69483D; }
        .preparing,.baking { background:#E7C9BB; color:#684638; }
        .ready { background:#DDB5A2; color:#57382D; }
        .delivered { background:#9B6B5A; color:#FFFDFB; }
        .cancelled { background:#EADBD4; color:#704F45; }
        .other { background:#F1E8E2; color:#756055; }

        .action-btn {
            text-decoration:none;
            padding:8px 14px;
            border-radius:20px;
            font-weight:700;
            font-size:13px;
            display:inline-block;
            margin:2px;
            transition:0.3s;
        }

        button.action-btn { font-family:inherit; cursor:pointer; }

        .order-actions {
            display:flex;
            align-items:center;
            gap:8px;
            white-space:nowrap;
        }

        .order-actions .action-btn { margin:0; }

        .details-btn {
            border:1px solid #C58B78;
            background:#FFFDFB;
            color:#795548;
        }

        .details-btn:hover { background:#F8E6DE; }

        .update-btn {
            border:1px solid #9B6B5A;
            background:#9B6B5A;
            color:#FFFDFB;
        }

        .update-btn:hover {
            background:#795548;
            border-color:#795548;
        }

        .dish-img {
            width:70px;
            height:50px;
            border-radius:10px;
            object-fit:cover;
            border:2px solid #F8E6DE;
        }

        .delete-btn { border:1px solid #704F45; color:#704F45; }
        .delete-btn:hover { background:#704F45; color:white; }

        .status-dialog {
            width:min(440px,calc(100% - 32px));
            max-height:calc(100vh - 40px);
            overflow-y:auto;
            margin:auto;
            padding:26px;
            border:1px solid #E7C9BB;
            border-radius:20px;
            background:#FFFDFB;
            color:#5D4037;
            box-shadow:0 24px 70px rgba(93,64,55,0.22);
            font-family:'Montserrat',sans-serif;
        }

        .status-dialog::backdrop { background:rgba(55,36,29,0.45); }

        .dialog-header {
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:16px;
        }

        .dialog-header h3 {
            font-family:'Playfair Display',serif;
            font-size:25px;
        }

        .dialog-close {
            width:36px;
            height:36px;
            flex-shrink:0;
            border:1px solid #E7C9BB;
            border-radius:50%;
            background:#F8E6DE;
            color:#5D4037;
            font-size:25px;
            cursor:pointer;
        }

        .dialog-summary {
            margin:14px 0 22px;
            font-size:14px;
            line-height:1.6;
        }

        .status-form { display:flex; flex-direction:column; gap:10px; }
        .status-form label { font-size:14px; font-weight:600; }

        .status-form select {
            width:100%;
            padding:12px;
            border:1px solid #C58B78;
            border-radius:10px;
            background:#FFFDFB;
            color:#5D4037;
            font:inherit;
        }

        .dialog-footer {
            display:flex;
            justify-content:flex-end;
            flex-wrap:wrap;
            gap:8px;
            margin-top:14px;
        }

        .dialog-footer .action-btn { margin:0; padding:10px 16px; }

        .action-btn:focus-visible,
        .dialog-close:focus-visible,
        .status-form select:focus-visible {
            outline:2px solid #795548;
            outline-offset:3px;
        }

        .flash {
            padding:14px 18px;
            margin-bottom:20px;
            border-radius:10px;
        }

        .flash-success { background:#EED6CB; color:#69483D; }
        .flash-error { background:#EADBD4; color:#704F45; }
        .review-note { margin:0 0 16px; line-height:1.6; }
        #manage-dishes { scroll-margin-top:150px; }

        .sort-note {
            text-align:center;
            margin:-12px 0 22px;
            color:#795548;
            font-size:13px;
        }

        .sort-controls { display:flex; align-items:center; gap:6px; }
        .sort-position { min-width:24px; font-weight:600; }

        .sort-btn {
            width:38px;
            height:38px;
            border:1px solid #C58B78;
            border-radius:10px;
            background:#FFFDFB;
            color:#795548;
            font:inherit;
            font-size:20px;
            cursor:pointer;
        }

        .sort-btn:hover:not(:disabled) { background:#F8E6DE; }
        .sort-btn:disabled { opacity:.35; cursor:not-allowed; }
        .sort-btn:focus-visible { outline:2px solid #795548; outline-offset:3px; }

        @media(max-width:768px) {
            .header { flex-direction:column; gap:15px; }
            .content { padding:20px; }
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
                Welcome Chef,
                <?php echo htmlspecialchars($chefName); ?> 👨‍🍳
            </div>

            <a href="logout-chef.php" class="btn-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <div class="content">
        <h2 class="title">Chef Dashboard</h2>

        <?php if ($flash): ?>
            <div
                role="status"
                class="flash <?php echo $flash['success'] ? 'flash-success' : 'flash-error'; ?>"
            >
                <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="top-actions">
            <a href="uploads/add-dish.php" class="btn-action">
                <i class="fas fa-plus"></i> Add Dish
            </a>

            <a href="chef-products.php" class="btn-action">
                <i class="fas fa-store"></i> View Products
            </a>

            <a href="chef-stock.php" class="btn-action">
                <i class="fas fa-boxes-stacked" aria-hidden="true"></i>
                Manage Stock
            </a>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-cookie-bite"></i>
                </div>
                <div>
                    <div class="stat-number"><?php echo $totalProducts; ?></div>
                    <div class="stat-label">Products</div>
                    <div class="stat-sub"><?php echo $availableProducts; ?> available</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div>
                    <div class="stat-number"><?php echo $totalOrders; ?></div>
                    <div class="stat-label">Orders</div>
                    <div class="stat-sub">All time</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon paid">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="stat-number">
                        $<?php echo number_format($paidSales, 2); ?>
                    </div>
                    <div class="stat-label">Paid sales</div>
                    <div class="stat-sub"><?php echo $paidCount; ?> orders paid</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon pending">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div>
                    <div class="stat-number">
                        $<?php echo number_format($pendingSales, 2); ?>
                    </div>
                    <div class="stat-label">Pending payment</div>
                    <div class="stat-sub"><?php echo $pendingCount; ?> orders unpaid</div>
                </div>
            </div>
        </div>

        <?php if (
            count(array_filter(
                $orders,
                static fn($row) => !isset($statusLabels[$row['status'] ?? ''])
            )) > 0
        ): ?>
            <p class="review-note">
                Some imported orders need review. Check their details before
                choosing Pending or Cancelled. No status has been assigned
                automatically.
            </p>
        <?php endif; ?>

        <h2 class="title" style="font-size:28px;">Orders</h2>

        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total</th>
                        <th>Order Date</th>
                        <th>Delivery Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($orders) === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:30px;">
                                No orders yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $currentStatus = (string) ($order['status'] ?? '');
                            $knownStatus = isset($statusLabels[$currentStatus]);

                            $statusClass = $knownStatus
                                ? $currentStatus
                                : 'other';

                            $statusLabel = $statusLabels[$currentStatus]
                                ?? 'Needs review';

                            $allowedStatuses = $transitions[$currentStatus]
                                ?? ['pending', 'cancelled'];

                            $customerNameRow = !empty($order["full_name"])
                                ? $order["full_name"]
                                : "Unknown Customer";
                            ?>

                            <tr>
                                <td>#<?php echo (int) $order["id"]; ?></td>

                                <td>
                                    <?php echo htmlspecialchars($customerNameRow); ?>
                                </td>

                                <td>
                                    $<?php echo number_format($order["total_amount"], 2); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($order["order_date"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($order["delivery_date"] ?? "—"); ?>
                                </td>

                                <td>
                                    <span class="status <?php echo $statusClass; ?>">
                                        <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="order-actions">
                                        <a
                                            href="chef-order-details.php?id=<?php echo (int) $order['id']; ?>"
                                            class="action-btn details-btn"
                                        >
                                            <i class="fas fa-eye" aria-hidden="true"></i>
                                            Details
                                        </a>

                                        <?php if ($allowedStatuses): ?>
                                            <button
                                                type="button"
                                                class="action-btn update-btn"
                                                data-open-dialog="order-dialog-<?php echo (int) $order['id']; ?>"
                                                aria-haspopup="dialog"
                                            >
                                                <i class="fas fa-pen" aria-hidden="true"></i>
                                                Update status
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($allowedStatuses): ?>
                                        <dialog
                                            id="order-dialog-<?php echo (int) $order['id']; ?>"
                                            class="status-dialog"
                                            aria-labelledby="dialog-title-<?php echo (int) $order['id']; ?>"
                                        >
                                            <div class="dialog-header">
                                                <h3 id="dialog-title-<?php echo (int) $order['id']; ?>">
                                                    Update order #<?php echo (int) $order['id']; ?>
                                                </h3>

                                                <button
                                                    type="button"
                                                    class="dialog-close"
                                                    data-close-dialog
                                                    aria-label="Close"
                                                >
                                                    &times;
                                                </button>
                                            </div>

                                            <p class="dialog-summary">
                                                Current status:
                                                <strong>
                                                    <?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                </strong>
                                            </p>

                                            <form
                                                method="post"
                                                action="chef-dashboard.php"
                                                class="status-form"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="csrf_token"
                                                    value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="order_id"
                                                    value="<?php echo (int) $order['id']; ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="expected_status"
                                                    value="<?php echo htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8'); ?>"
                                                >

                                                <label for="status-<?php echo (int) $order['id']; ?>">
                                                    New status
                                                </label>

                                                <select
                                                    name="status"
                                                    id="status-<?php echo (int) $order['id']; ?>"
                                                    required
                                                >
                                                    <option value="">Choose status</option>

                                                    <?php foreach ($allowedStatuses as $nextStatus): ?>
                                                        <option value="<?php echo $nextStatus; ?>">
                                                            <?php echo $statusLabels[$nextStatus]; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>

                                                <div class="dialog-footer">
                                                    <button
                                                        type="button"
                                                        class="action-btn details-btn"
                                                        data-close-dialog
                                                    >
                                                        Cancel
                                                    </button>

                                                    <button
                                                        type="submit"
                                                        class="action-btn update-btn"
                                                    >
                                                        Save changes
                                                    </button>
                                                </div>
                                            </form>
                                        </dialog>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h2 id="manage-dishes" class="title" style="font-size:28px;">
            Manage Dishes
        </h2>

        <p class="sort-note">
            Use the arrows to move a product. Each move saves automatically.
        </p>

        <?php if ($dishFlash): ?>
            <div
                role="status"
                class="flash <?php echo $dishFlash['success'] ? 'flash-success' : 'flash-error'; ?>"
            >
                <?php echo htmlspecialchars($dishFlash['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Photo</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($dishes) === 0): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:30px;">
                                No dishes found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($dishes as $dishIndex => $dish): ?>
                            <?php
                            $img = !empty($dish["image_url"])
                                ? $dish["image_url"]
                                : "asssets/logo.png";
                            ?>

                            <tr>
                                <td>
                                    <form
                                        method="post"
                                        action="chef-dashboard.php#manage-dishes"
                                        class="sort-controls"
                                    >
                                        <input type="hidden" name="action" value="reorder_dish">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="dish_id"
                                            value="<?php echo (int) $dish['id']; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="order_hash"
                                            value="<?php echo $dishOrderHash; ?>"
                                        >

                                        <span class="sort-position">
                                            <?php echo $dishIndex + 1; ?>
                                        </span>

                                        <button
                                            type="submit"
                                            name="direction"
                                            value="up"
                                            class="sort-btn"
                                            aria-label="Move <?php echo htmlspecialchars($dish['name'], ENT_QUOTES, 'UTF-8'); ?> up"
                                            <?php echo $dishIndex === 0 ? 'disabled' : ''; ?>
                                        >↑</button>

                                        <button
                                            type="submit"
                                            name="direction"
                                            value="down"
                                            class="sort-btn"
                                            aria-label="Move <?php echo htmlspecialchars($dish['name'], ENT_QUOTES, 'UTF-8'); ?> down"
                                            <?php echo $dishIndex === count($dishes) - 1 ? 'disabled' : ''; ?>
                                        >↓</button>
                                    </form>
                                </td>

                                <td>
                                    <img
                                        src="<?php echo htmlspecialchars($img); ?>"
                                        class="dish-img"
                                    >
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($dish["name"]); ?>
                                </td>

                                <td>
                                    $<?php echo number_format($dish["price"], 2); ?>
                                </td>

                                <td>
                                    <a
                                        href="uploads/edit-dish.php?id=<?php echo (int) $dish['id']; ?>"
                                        class="action-btn details-btn"
                                    >
                                        <i class="fas fa-pen" aria-hidden="true"></i>
                                        Edit
                                    </a>

                                    <a
                                        href="uploads/delete-dish.php?id=<?php echo (int) $dish['id']; ?>"
                                        class="action-btn delete-btn"
                                    >
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-open-dialog]').forEach(function (button) {
            button.addEventListener('click', function () {
                const dialog = document.getElementById(button.dataset.openDialog);

                dialog.querySelector('form').reset();
                dialog.showModal();
                dialog.querySelector('select').focus();
            });
        });

        document.querySelectorAll('[data-close-dialog]').forEach(function (button) {
            button.addEventListener('click', function () {
                button.closest('dialog').close();
            });
        });
    </script>
</body>
</html>