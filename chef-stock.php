<?php
session_start();

if (($_SESSION['chef_logged_in'] ?? false) !== true) {
    header('Location: chef-login.php?reset=1');
    exit();
}

require_once __DIR__ . '/db.php';

function stockEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['stock_csrf'])) {
    $_SESSION['stock_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['stock_csrf'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;

    if (!is_string($token) || !hash_equals($csrf, $token)) {
        http_response_code(403);
        exit('Reload the page and try again.');
    }

    $id = filter_var(
        $_POST['dish_id'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    $quantity = filter_var(
        $_POST['quantity'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 1000000]]
    );

    $expected = filter_var(
        $_POST['expected_quantity'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 0, 'max_range' => 4294967295]]
    );

    $success = false;
    $message = 'Enter a whole number between 0 and 1,000,000.';

    if (
        $id &&
        $quantity !== false &&
        $quantity !== null &&
        $expected !== false &&
        $expected !== null
    ) {
        try {
            $pdo->beginTransaction();

            $find = $pdo->prepare(
                'SELECT stock_quantity
                 FROM dishes
                 WHERE id = :id AND is_deleted = 0
                 FOR UPDATE'
            );

            $find->execute([':id' => $id]);
            $dish = $find->fetch(PDO::FETCH_ASSOC);

            if (!$dish) {
                $message = 'Product not found.';
                $pdo->rollBack();
            } elseif ((int) $dish['stock_quantity'] !== $expected) {
                $message = 'Stock changed since you opened this page. Review the current quantity and try again.';
                $pdo->rollBack();
            } else {
                $update = $pdo->prepare(
                    'UPDATE dishes
                     SET stock_quantity = :quantity
                     WHERE id = :id AND is_deleted = 0'
                );

                $update->execute([
                    ':quantity' => $quantity,
                    ':id' => $id,
                ]);

                $pdo->commit();

                $success = true;
                $message = 'Stock quantity saved.';
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Stock update failed: ' . $e->getMessage());
            $message = 'Could not save stock. Please try again.';
        }
    }

    $_SESSION['stock_flash'] = [
        'success' => $success,
        'message' => $message,
    ];

    header('Location: chef-stock.php', true, 303);
    exit();
}

$flash = $_SESSION['stock_flash'] ?? null;
unset($_SESSION['stock_flash']);

$dishes = $pdo->query(
    'SELECT id, name, image_url, stock_quantity, available
     FROM dishes
     WHERE is_deleted = 0
     ORDER BY sort_order ASC, id ASC'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Manage Stock | Lula's Pastry</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #F8E6DE;
            color: #5D4037;
            font-family: 'Montserrat', sans-serif;
        }

        header {
            background: #FFFDFB;
            border-bottom: 1px solid #E7C9BB;
        }

        .bar {
            max-width: 1100px;
            margin: auto;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .brand {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
        }

        a {
            color: #795548;
            text-underline-offset: 4px;
        }

        main {
            max-width: 1100px;
            margin: auto;
            padding: 32px 24px;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 34px;
            margin: 0 0 10px;
        }

        .intro {
            font-size: 14px;
            line-height: 1.7;
            margin: 0 0 25px;
        }

        .panel {
            background: #FFFDFB;
            border: 1px solid #E7C9BB;
            border-radius: 18px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        th,
        td {
            padding: 18px;
            text-align: left;
            border-bottom: 1px solid #EED6CB;
            font-size: 14px;
        }

        th {
            background: #FCF4EF;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .product {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product img {
            width: 62px;
            height: 52px;
            border-radius: 9px;
            object-fit: cover;
        }

        .badge {
            display: inline-block;
            border-radius: 20px;
            padding: 7px 11px;
            background: #F8E6DE;
            color: #795548;
            font-size: 12px;
        }

        .empty {
            background: #EADBD4;
            color: #704F45;
        }

        .hidden-note {
            display: block;
            font-size: 11px;
            margin-top: 7px;
            color: #795548;
        }

        form {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        input[type=number] {
            width: 110px;
            padding: 10px;
            border: 1px solid #C58B78;
            border-radius: 9px;
            font: inherit;
            color: #5D4037;
            background: #FFFDFB;
        }

        button {
            padding: 11px 17px;
            background: #9B6B5A;
            color: #FFFDFB;
            border: 0;
            border-radius: 9px;
            cursor: pointer;
            font: inherit;
            font-weight: 600;
        }

        button:hover {
            background: #795548;
        }

        input:focus-visible,
        button:focus-visible,
        a:focus-visible {
            outline: 2px solid #795548;
            outline-offset: 3px;
        }

        .flash {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .success {
            background: #EED6CB;
        }

        .error {
            background: #EADBD4;
        }

        @media(max-width: 600px) {
            main {
                padding: 24px 14px;
            }

            .bar {
                padding: 18px 14px;
            }

            .brand {
                font-size: 19px;
            }

            .bar a {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>
    <header>
        <div class="bar">
            <strong class="brand">LULA'S PASTRY</strong>
            <a href="chef-dashboard.php">← Dashboard</a>
        </div>
    </header>

    <main>
        <h1>Manage stock</h1>

        <p class="intro">
            Enter the total units currently in stock, then save each product.
            Enter 0 when no units remain.
        </p>

        <?php if ($flash): ?>
            <div
                role="status"
                class="flash <?php echo $flash['success'] ? 'success' : 'error'; ?>"
            >
                <?php echo stockEscape($flash['message']); ?>
            </div>
        <?php endif; ?>

        <div class="panel">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Current stock</th>
                        <th>Set quantity</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!$dishes): ?>
                        <tr>
                            <td colspan="3">No products found.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($dishes as $dish): ?>
                        <tr>
                            <td>
                                <div class="product">
                                    <img
                                        src="<?php echo stockEscape($dish['image_url'] ?: 'asssets/logo.png'); ?>"
                                        alt=""
                                    >

                                    <strong>
                                        <?php echo stockEscape($dish['name']); ?>
                                    </strong>
                                </div>
                            </td>

                            <td>
                                <span class="badge <?php echo (int) $dish['stock_quantity'] === 0 ? 'empty' : ''; ?>">
                                    <?php echo (int) $dish['stock_quantity']; ?> units
                                </span>

                                <?php if (!(int) $dish['available']): ?>
                                    <span class="hidden-note">
                                        Currently hidden from the menu
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <form method="post" action="chef-stock.php">
                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?php echo stockEscape($csrf); ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="dish_id"
                                        value="<?php echo (int) $dish['id']; ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="expected_quantity"
                                        value="<?php echo (int) $dish['stock_quantity']; ?>"
                                    >

                                    <input
                                        type="number"
                                        name="quantity"
                                        min="0"
                                        max="1000000"
                                        step="1"
                                        required
                                        aria-label="Stock quantity for <?php echo stockEscape($dish['name']); ?>"
                                        value="<?php echo (int) $dish['stock_quantity']; ?>"
                                    >

                                    <button type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</body>
</html>