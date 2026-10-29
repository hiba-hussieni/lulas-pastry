<?php
session_start();

if (($_SESSION['chef_logged_in'] ?? false) !== true) {
    header('Location: ../chef-login.php?reset=1');
    exit();
}

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['GET', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, POST');
    exit('Method not allowed.');
}

if (empty($_SESSION['delete_dish_csrf'])) {
    $_SESSION['delete_dish_csrf'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['delete_dish_csrf'];

$id = filter_var(
    $method === 'POST' ? ($_POST['id'] ?? null) : ($_GET['id'] ?? null),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$id) {
    http_response_code(400);
    exit('Invalid product ID.');
}

$error = '';

if ($method === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;

    if (!is_string($submittedToken) || !hash_equals($token, $submittedToken)) {
        http_response_code(403);
        exit('Invalid session token. Reload the confirmation page.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'SELECT id FROM dishes WHERE id = :id FOR UPDATE'
        );
        $stmt->execute([':id' => $id]);

        if (!$stmt->fetchColumn()) {
            $pdo->rollBack();
            http_response_code(404);
            exit('Product not found.');
        }

        // Keep the product and its image for historical orders.
        $stmt = $pdo->prepare(
            'UPDATE dishes
             SET is_deleted = 1,
                 available = 0,
                 deleted_at = COALESCE(deleted_at, NOW())
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        // Remove this unavailable product from existing carts.
        $stmt = $pdo->prepare(
            'DELETE FROM cart WHERE dish_id = :id'
        );
        $stmt->execute([':id' => $id]);

        $pdo->commit();

        header('Location: ../chef-dashboard.php', true, 303);
        exit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Product archive failed: ' . $e->getMessage());
        $error = 'The product could not be removed. Please try again.';
    }
}

$stmt = $pdo->prepare(
    'SELECT name, is_deleted FROM dishes WHERE id = :id'
);
$stmt->execute([':id' => $id]);

$dish = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dish) {
    http_response_code(404);
    exit('Product not found.');
}

$alreadyDeleted = (bool) $dish['is_deleted'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Remove product | Lula's Pastry</title>

    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Montserrat:wght@400;600&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #F8E6DE;
            color: #5D4037;
            font-family: 'Montserrat', sans-serif;
        }

        main {
            width: 100%;
            max-width: 500px;
            padding: 32px;
            border: 1px solid #EED6CB;
            border-radius: 20px;
            background: #FFFDFB;
        }

        h1 {
            margin-top: 0;
            font-family: 'Playfair Display', serif;
        }

        p {
            line-height: 1.7;
            overflow-wrap: anywhere;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
        }

        button,
        .button {
            padding: 12px 20px;
            border: 1px solid #9B6B5A;
            border-radius: 24px;
            font: inherit;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        button {
            background: #9B6B5A;
            color: #FFFDFB;
        }

        .button {
            background: #FFFDFB;
            color: #704F45;
        }

        :focus-visible {
            outline: 3px solid #C58B78;
            outline-offset: 3px;
        }

        .error {
            padding: 12px;
            background: #EADBD4;
            border-radius: 10px;
        }
    </style>
</head>

<body>
<main>
    <h1>
        <?php echo $alreadyDeleted ? 'Product removed' : 'Remove product?'; ?>
    </h1>

    <p>
        <strong>
            <?php echo htmlspecialchars($dish['name'], ENT_QUOTES, 'UTF-8'); ?>
        </strong>
    </p>

    <?php if ($error !== ''): ?>
        <p class="error" role="alert">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </p>
    <?php endif; ?>

    <?php if ($alreadyDeleted): ?>
        <p>This product has already been marked as removed.</p>

        <a class="button" href="../chef-dashboard.php">
            Back to dashboard
        </a>
    <?php else: ?>
        <p>
            This product will no longer be available to order and will be
            removed from existing carts. Previous order items and the
            product image will be kept.
        </p>

        <form method="post" action="delete-dish.php">
            <input
                type="hidden"
                name="id"
                value="<?php echo (int) $id; ?>"
            >

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>"
            >

            <div class="actions">
                <a class="button" href="../chef-dashboard.php">
                    Cancel
                </a>

                <button type="submit">
                    Remove product
                </button>
            </div>
        </form>
    <?php endif; ?>
</main>
</body>
</html>