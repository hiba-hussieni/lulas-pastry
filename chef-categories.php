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

if (empty($_SESSION['category_csrf'])) {
    $_SESSION['category_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['category_csrf'];
$flash = null;

/*  Handle add / rename / delete / reorder */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? null;

    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        http_response_code(403);
        exit('Your session token is invalid. Reload the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $message = '';
    $success = false;

    try {
        if ($action === 'add') {
            $name = trim((string) ($_POST['name'] ?? ''));

            if ($name === '') {
                $message = 'Category name cannot be empty.';
            } elseif (mb_strlen($name) > 50) {
                $message = 'Category name is too long (max 50 characters).';
            } else {
                $exists = $pdo->prepare(
                    'SELECT id FROM categories WHERE name = :name'
                );
                $exists->execute([':name' => $name]);

                if ($exists->fetch()) {
                    $message = 'A category with this name already exists.';
                } else {
                    $maxOrder = (int) $pdo->query(
                        'SELECT COALESCE(MAX(sort_order), 0) FROM categories'
                    )->fetchColumn();

                    $insert = $pdo->prepare(
                        'INSERT INTO categories (name, sort_order)
                         VALUES (:name, :sort_order)'
                    );
                    $insert->execute([
                        ':name' => $name,
                        ':sort_order' => $maxOrder + 1,
                    ]);

                    $message = 'Category "' . $name . '" added.';
                    $success = true;
                }
            }
        } elseif ($action === 'rename') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $name = trim((string) ($_POST['name'] ?? ''));

            if ($id === false || $id === null) {
                $message = 'Invalid category.';
            } elseif ($name === '') {
                $message = 'Category name cannot be empty.';
            } elseif (mb_strlen($name) > 50) {
                $message = 'Category name is too long (max 50 characters).';
            } else {
                $exists = $pdo->prepare(
                    'SELECT id FROM categories WHERE name = :name AND id != :id'
                );
                $exists->execute([':name' => $name, ':id' => $id]);

                if ($exists->fetch()) {
                    $message = 'A category with this name already exists.';
                } else {
                    $update = $pdo->prepare(
                        'UPDATE categories SET name = :name WHERE id = :id'
                    );
                    $update->execute([':name' => $name, ':id' => $id]);

                    $message = 'Category renamed to "' . $name . '".';
                    $success = true;
                }
            }
        } elseif ($action === 'delete') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );

            if ($id === false || $id === null) {
                $message = 'Invalid category.';
            } else {
                $count = $pdo->prepare(
                    'SELECT COUNT(*) FROM dishes WHERE category_id = :id'
                );
                $count->execute([':id' => $id]);
                $dishCount = (int) $count->fetchColumn();

                $delete = $pdo->prepare(
                    'DELETE FROM categories WHERE id = :id'
                );
                $delete->execute([':id' => $id]);

                $message = $dishCount > 0
                    ? 'Category deleted. ' . $dishCount
                        . ' dish(es) are now uncategorized — assign them a new category from Manage Dishes.'
                    : 'Category deleted.';
                $success = true;
            }
        } elseif ($action === 'move') {
            $id = filter_var(
                $_POST['id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $direction = $_POST['direction'] ?? '';

            if ($id === false || $id === null || !in_array($direction, ['up', 'down'], true)) {
                $message = 'Invalid move.';
            } else {
                $current = $pdo->prepare(
                    'SELECT id, sort_order FROM categories WHERE id = :id'
                );
                $current->execute([':id' => $id]);
                $currentRow = $current->fetch(PDO::FETCH_ASSOC);

                if (!$currentRow) {
                    $message = 'Category not found.';
                } else {
                    $comparison = $direction === 'up' ? '<' : '>';
                    $order = $direction === 'up' ? 'DESC' : 'ASC';

                    $neighborStmt = $pdo->prepare(
                        "SELECT id, sort_order FROM categories
                         WHERE sort_order $comparison :sort_order
                         ORDER BY sort_order $order
                         LIMIT 1"
                    );
                    $neighborStmt->execute([':sort_order' => $currentRow['sort_order']]);
                    $neighbor = $neighborStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$neighbor) {
                        $message = 'Already at the ' . ($direction === 'up' ? 'top' : 'bottom') . '.';
                    } else {
                        $pdo->beginTransaction();

                        $swap = $pdo->prepare(
                            'UPDATE categories SET sort_order = :sort_order WHERE id = :id'
                        );
                        $swap->execute([
                            ':sort_order' => $neighbor['sort_order'],
                            ':id' => $currentRow['id'],
                        ]);
                        $swap->execute([
                            ':sort_order' => $currentRow['sort_order'],
                            ':id' => $neighbor['id'],
                        ]);

                        $pdo->commit();

                        $message = 'Order updated.';
                        $success = true;
                    }
                }
            }
        } else {
            $message = 'Unknown action.';
        }
    } catch (PDOException $e) {
        error_log('Category action failed: ' . $e->getMessage());
        $message = 'Something went wrong. Please try again.';
    }

    $_SESSION['category_flash'] = [
        'message' => $message,
        'success' => $success,
    ];

    header('Location: chef-categories.php', true, 303);
    exit();
}

$flash = $_SESSION['category_flash'] ?? null;
unset($_SESSION['category_flash']);

/* ✅ Categories with dish counts */
$categories = $pdo->query(
    "SELECT categories.id, categories.name, categories.sort_order,
            COUNT(dishes.id) AS dish_count
     FROM categories
     LEFT JOIN dishes ON dishes.category_id = categories.id
     GROUP BY categories.id, categories.name, categories.sort_order
     ORDER BY categories.sort_order ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$chefName = $_SESSION["chef_name"] ?? "Chef";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories | Lula's Pastry</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500;600&display=swap"
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
        }

        .brand p {
            color: #9B6B5A;
            font-size: 13px;
        }

        .header-right {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .welcome-text {
            font-weight: 700;
            color: #5D4037;
            text-align: right;
        }

        .btn-link {
            text-decoration: none;
            background: #C58B78;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 700;
            transition: 0.3s;
            display: inline-flex;
            gap: 8px;
            align-items: center;
        }

        .btn-link:hover {
            background: #9B6B5A;
        }

        .content {
            padding: 40px;
            max-width: 900px;
            margin: 0 auto;
        }

        .title {
            font-family: 'Playfair Display', serif;
            font-size: 34px;
            text-align: center;
            margin-bottom: 25px;
        }

        .top-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-action {
            text-decoration: none;
            padding: 12px 20px;
            border-radius: 25px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: #C58B78;
            color: white;
            transition: 0.3s;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
        }

        .btn-action:hover {
            background: #9B6B5A;
        }

        .add-box {
            background: #FFFDFB;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 8px 20px rgba(93, 64, 55, 0.06);
            border: 1px solid rgba(197, 139, 120, 0.2);
            margin-bottom: 30px;
        }

        .add-box h3 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            margin-bottom: 14px;
        }

        .add-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .add-form input[type="text"] {
            flex: 1;
            min-width: 200px;
            padding: 12px;
            border: 1px solid #C58B78;
            border-radius: 10px;
            background: #FFFDFB;
            color: #5D4037;
            font: inherit;
        }

        .table-box {
            background: #FFFDFB;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(197, 139, 120, 0.2);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid rgba(197, 139, 120, 0.2);
            font-size: 14px;
        }

        th {
            font-weight: 800;
            background: #F8E6DE;
        }

        .move-btns {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .move-btn {
            border: 1px solid #C58B78;
            background: #FFFDFB;
            color: #795548;
            border-radius: 6px;
            width: 26px;
            height: 22px;
            font-size: 11px;
            cursor: pointer;
        }

        .move-btn:hover {
            background: #F8E6DE;
        }

        .move-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .dish-count {
            background: #EED6CB;
            color: #69483D;
            font-weight: 700;
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 15px;
            display: inline-block;
        }

        .row-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .row-actions form {
            display: inline;
        }

        .action-btn {
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            display: inline-block;
            border: none;
            cursor: pointer;
            font-family: inherit;
            transition: 0.3s;
        }

        .rename-btn {
            border: 1px solid #9B6B5A;
            background: #9B6B5A;
            color: #FFFDFB;
        }

        .rename-btn:hover {
            background: #795548;
        }

        .delete-btn {
            border: 1px solid #704F45;
            background: #FFFDFB;
            color: #704F45;
        }

        .delete-btn:hover {
            background: #704F45;
            color: white;
        }

        .rename-dialog {
            width: min(400px, calc(100% - 32px));
            margin: auto;
            padding: 26px;
            border: 1px solid #E7C9BB;
            border-radius: 20px;
            background: #FFFDFB;
            color: #5D4037;
            box-shadow: 0 24px 70px rgba(93, 64, 55, 0.22);
            font-family: 'Montserrat', sans-serif;
        }

        .rename-dialog::backdrop {
            background: rgba(55, 36, 29, 0.45);
        }

        .rename-dialog h3 {
            font-family: 'Playfair Display', serif;
            font-size: 22px;
            margin-bottom: 16px;
        }

        .rename-dialog input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #C58B78;
            border-radius: 10px;
            background: #FFFDFB;
            color: #5D4037;
            font: inherit;
            margin-bottom: 16px;
        }

        .dialog-footer {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .flash {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 10px;
        }

        .flash-success {
            background: #EED6CB;
            color: #69483D;
        }

        .flash-error {
            background: #EADBD4;
            color: #704F45;
        }

        .empty-note {
            text-align: center;
            padding: 30px;
            color: #9B6B5A;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
            }

            .content {
                padding: 20px;
            }
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
        <h2 class="title">Manage Categories</h2>

        <?php if ($flash): ?>
            <div
                role="status"
                class="flash <?php echo $flash['success'] ? 'flash-success' : 'flash-error'; ?>"
            >
                <?php echo htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="top-actions">
            <a href="chef-dashboard.php" class="btn-action">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>

        <div class="add-box">
            <h3>Add a new category</h3>

            <form method="post" action="chef-categories.php" class="add-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="add">

                <input
                    type="text"
                    name="name"
                    placeholder="e.g. Cakes, Bread, Pastry"
                    maxlength="50"
                    required
                >

                <button type="submit" class="btn-action">
                    <i class="fas fa-plus"></i> Add
                </button>
            </form>
        </div>

        <div class="table-box">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Name</th>
                        <th>Dishes</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (count($categories) === 0): ?>
                        <tr>
                            <td colspan="4" class="empty-note">
                                No categories yet. Add one above.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($categories as $index => $category): ?>
                            <tr>
                                <td>
                                    <div class="move-btns">
                                        <form method="post" action="chef-categories.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="move">
                                            <input type="hidden" name="direction" value="up">
                                            <input type="hidden" name="id" value="<?php echo (int) $category['id']; ?>">

                                            <button
                                                type="submit"
                                                class="move-btn"
                                                <?php echo $index === 0 ? 'disabled' : ''; ?>
                                                aria-label="Move up"
                                            >
                                                <i class="fas fa-chevron-up"></i>
                                            </button>
                                        </form>

                                        <form method="post" action="chef-categories.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="move">
                                            <input type="hidden" name="direction" value="down">
                                            <input type="hidden" name="id" value="<?php echo (int) $category['id']; ?>">

                                            <button
                                                type="submit"
                                                class="move-btn"
                                                <?php echo $index === count($categories) - 1 ? 'disabled' : ''; ?>
                                                aria-label="Move down"
                                            >
                                                <i class="fas fa-chevron-down"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>

                                <td>
                                    <span class="dish-count">
                                        <?php echo (int) $category['dish_count']; ?> dish(es)
                                    </span>
                                </td>

                                <td>
                                    <div class="row-actions">
                                        <button
                                            type="button"
                                            class="action-btn rename-btn"
                                            data-open-dialog="rename-dialog-<?php echo (int) $category['id']; ?>"
                                        >
                                            <i class="fas fa-pen"></i> Rename
                                        </button>

                                        <form
                                            method="post"
                                            action="chef-categories.php"
                                            onsubmit="return confirm('Delete this category? Dishes using it will become uncategorized.');"
                                        >
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo (int) $category['id']; ?>">

                                            <button type="submit" class="action-btn delete-btn">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </form>
                                    </div>

                                    <dialog
                                        id="rename-dialog-<?php echo (int) $category['id']; ?>"
                                        class="rename-dialog"
                                    >
                                        <h3>Rename category</h3>

                                        <form method="post" action="chef-categories.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="action" value="rename">
                                            <input type="hidden" name="id" value="<?php echo (int) $category['id']; ?>">

                                            <input
                                                type="text"
                                                name="name"
                                                value="<?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                maxlength="50"
                                                required
                                            >

                                            <div class="dialog-footer">
                                                <button type="button" class="action-btn delete-btn" data-close-dialog>
                                                    Cancel
                                                </button>

                                                <button type="submit" class="action-btn rename-btn">
                                                    Save
                                                </button>
                                            </div>
                                        </form>
                                    </dialog>
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
                document.getElementById(button.dataset.openDialog).showModal();
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