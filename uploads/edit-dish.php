<?php
session_start();

if (!isset($_SESSION["chef_logged_in"]) || $_SESSION["chef_logged_in"] !== true) {
    header("Location: ../chef-login.php?reset=1");
    exit();
}

require_once "../db.php";

if (empty($_SESSION['edit_dish_csrf'])) {
    $_SESSION['edit_dish_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['edit_dish_csrf'];

$dishId = filter_var(
    $_GET["id"] ?? ($_POST["id"] ?? null),
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($dishId === false || $dishId === null) {
    header("Location: ../chef-dashboard.php");
    exit();
}

// Load categories.
$categories = $pdo->query(
    "SELECT id, name FROM categories ORDER BY sort_order ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$success = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST['csrf_token'] ?? null;

    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        http_response_code(403);
        exit('Your session token is invalid. Reload the page and try again.');
    }

    $name = trim((string) ($_POST["name"] ?? ""));
    $description = trim((string) ($_POST["description"] ?? ""));
    $price = trim((string) ($_POST["price"] ?? ""));
    $categoryId = trim((string) ($_POST["category_id"] ?? ""));
    $ingredients = trim((string) ($_POST["ingredients"] ?? ""));

    $categoryIds = array_column($categories, 'id');

    if ($name === "" || $price === "") {
        $error = "Please fill required fields (Name and Price).";
    } elseif ($categoryId === "" || !in_array((int) $categoryId, $categoryIds, true)) {
        $error = "Please choose a valid category.";
    } elseif (!is_numeric($price) || (float) $price < 0) {
        $error = "Please enter a valid price.";
    } else {
        // Read the existing image.
        $current = $pdo->prepare(
            "SELECT image_url FROM dishes WHERE id = :id"
        );
        $current->execute([":id" => $dishId]);
        $currentDish = $current->fetch(PDO::FETCH_ASSOC);

        if (!$currentDish) {
            $error = "Dish not found.";
        } else {
            $imagePath = $currentDish["image_url"];
            $oldImagePath = null;

            // Replace the photo only when a new file is selected.
            if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === 0) {
                $allowed = array("jpg", "jpeg", "png", "gif", "webp");
                $fileName = $_FILES["photo"]["name"];
                $fileTmp = $_FILES["photo"]["tmp_name"];

                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    $error = "Invalid image type. Allowed: jpg, jpeg, png, gif, webp";
                } else {
                    $newFileName = time() . "_" . rand(1000, 9999) . "." . $ext;
                    $targetFolder = "../assets/products/";
                    $targetFile = $targetFolder . $newFileName;

                    if (!is_dir($targetFolder)) {
                        mkdir($targetFolder, 0777, true);
                    }

                    if (move_uploaded_file($fileTmp, $targetFile)) {
                        $oldImagePath = $imagePath;
                        $imagePath = "assets/products/" . $newFileName;
                    } else {
                        $error = "Image upload failed.";
                    }
                }
            }

            if ($error === "") {
                $categoryName = "";

                foreach ($categories as $cat) {
                    if ((int) $cat['id'] === (int) $categoryId) {
                        $categoryName = $cat['name'];
                        break;
                    }
                }

                $update = $pdo->prepare(
                    "UPDATE dishes SET
                        name = :name,
                        description = :description,
                        price = :price,
                        category = :category,
                        category_id = :category_id,
                        ingredients = :ingredients,
                        image_url = :image_url
                     WHERE id = :id"
                );

                $update->execute([
                    ":name" => $name,
                    ":description" => $description,
                    ":price" => $price,
                    ":category" => $categoryName,
                    ":category_id" => $categoryId,
                    ":ingredients" => $ingredients,
                    ":image_url" => $imagePath,
                    ":id" => $dishId,
                ]);

                // Delete the old image after saving the replacement.
                if ($oldImagePath !== null && $oldImagePath !== "") {
                    $oldFilePath = "../" . $oldImagePath;

                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $success = "Dish updated successfully! ✅";
            }
        }
    }
}

// Reload the saved product.
$stmt = $pdo->prepare(
    "SELECT id, name, description, price, category_id, ingredients, image_url
     FROM dishes WHERE id = :id"
);
$stmt->execute([":id" => $dishId]);
$dish = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dish) {
    header("Location: ../chef-dashboard.php");
    exit();
}

$photoUrl = '../' . (
    !empty($dish['image_url'])
        ? $dish['image_url']
        : 'asssets/logo.png'
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Edit Product | Lula's Pastry</title>

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

        a {
            color: inherit;
        }

        .topbar {
            background: #FFFDFB;
            border-bottom: 1px solid #E7C9BB;
        }

        .topbar-inner {
            max-width: 1120px;
            margin: auto;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand img {
            width: 48px;
            height: 48px;
            object-fit: contain;
            border: 1px solid #E7C9BB;
            border-radius: 50%;
        }

        .brand strong {
            font-family: 'Playfair Display', serif;
            font-size: 21px;
        }

        .brand small {
            display: block;
            font-size: 11px;
            color: #9B6B5A;
            margin-top: 3px;
        }

        .back {
            font-size: 13px;
            text-decoration: none;
            font-weight: 600;
        }

        main {
            max-width: 1120px;
            margin: auto;
            padding: 32px 24px 48px;
        }

        .breadcrumb {
            font-size: 12px;
            color: #9B6B5A;
            margin-bottom: 18px;
        }

        .breadcrumb a {
            text-underline-offset: 3px;
        }

        .page-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 26px;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 38px;
            margin: 0 0 8px;
        }

        .page-heading p {
            margin: 0;
            color: #9B6B5A;
            font-size: 14px;
            line-height: 1.7;
        }

        .id-tag {
            border: 1px solid #DDB5A2;
            border-radius: 30px;
            padding: 9px 14px;
            font-size: 12px;
            white-space: nowrap;
        }

        .editor-grid {
            display: grid;
            grid-template-columns: 310px minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }

        .panel {
            background: #FFFDFB;
            border: 1px solid #E7D4CA;
            border-radius: 18px;
            overflow: hidden;
        }

        .photo-panel {
            padding: 22px;
        }

        .section-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            color: #9B6B5A;
            font-weight: 700;
            margin: 0 0 16px;
        }

        .preview {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            display: block;
            background: #F8E6DE;
            border-radius: 12px;
        }

        .photo-panel h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            margin: 18px 0 8px;
            overflow-wrap: anywhere;
        }

        .saved-price {
            font-size: 16px;
            font-weight: 600;
            margin: 0 0 20px;
        }

        .photo-field {
            border-top: 1px solid #EED6CB;
            padding-top: 18px;
        }

        .photo-field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        input[type=file] {
            width: 100%;
            font-size: 11px;
            color: #795548;
        }

        input[type=file]::file-selector-button {
            border: 1px solid #C58B78;
            border-radius: 8px;
            padding: 9px 10px;
            background: #F8E6DE;
            color: #5D4037;
            cursor: pointer;
            margin-right: 7px;
            font-family: inherit;
        }

        .hint {
            font-size: 12px;
            color: #9B6B5A;
            line-height: 1.7;
            margin: 12px 0 0;
        }

        .panel-heading {
            padding: 22px 26px;
            border-bottom: 1px solid #EED6CB;
        }

        .panel-heading h2 {
            font-size: 17px;
            margin: 0 0 6px;
        }

        .panel-heading p {
            font-size: 12px;
            color: #9B6B5A;
            margin: 0;
            line-height: 1.6;
        }

        .fields {
            padding: 24px 26px;
            display: grid;
            gap: 20px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 9px;
        }

        .field input,
        .field textarea,
        .field select {
            width: 100%;
            min-width: 0;
            border: 1px solid #DEC9BF;
            border-radius: 9px;
            background: #FFFDFB;
            color: #5D4037;
            padding: 12px;
            font: inherit;
            font-size: 14px;
        }

        .field textarea {
            min-height: 95px;
            resize: vertical;
            line-height: 1.6;
        }

        .field input:focus,
        .field textarea:focus,
        .field select:focus {
            outline: 2px solid #C58B78;
            outline-offset: 2px;
        }

        .pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 20px 26px;
            background: #FCF4EF;
            border-top: 1px solid #EED6CB;
        }

        .action-note {
            font-size: 12px;
            color: #795548;
            line-height: 1.6;
            max-width: 210px;
        }

        .buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #C58B78;
            border-radius: 9px;
            padding: 12px 16px;
            font: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-secondary {
            background: #FFFDFB;
            color: #795548;
        }

        .btn-primary {
            background: #9B6B5A;
            color: #FFFDFB;
            border-color: #9B6B5A;
        }

        .btn-primary:hover {
            background: #795548;
        }

        .btn-secondary:hover {
            background: #F8E6DE;
        }

        a:focus-visible,
        button:focus-visible,
        input[type=file]:focus-visible {
            outline: 2px solid #795548;
            outline-offset: 4px;
        }

        .message {
            padding: 15px 18px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 14px;
            line-height: 1.6;
        }

        .success {
            background: #EED6CB;
            border: 1px solid #C58B78;
            color: #5D4037;
        }

        .error {
            background: #EADBD4;
            border: 1px solid #9B6B5A;
            color: #704F45;
        }

        @media (max-width: 850px) {
            .editor-grid {
                grid-template-columns: 240px minmax(0, 1fr);
                gap: 16px;
            }

            .actions {
                flex-direction: column;
                align-items: stretch;
            }

            .action-note {
                max-width: none;
            }

            .buttons {
                justify-content: flex-end;
            }

            .pair {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 620px) {
            .editor-grid {
                grid-template-columns: 1fr;
            }

            main {
                padding: 24px 16px;
            }

            .topbar-inner {
                padding: 14px 16px;
            }

            .brand strong {
                font-size: 17px;
            }

            .back {
                font-size: 11px;
            }

            h1 {
                font-size: 31px;
            }

            .page-heading {
                flex-wrap: wrap;
                gap: 10px;
            }

            .preview {
                max-height: 260px;
                aspect-ratio: 4 / 3;
            }

            .fields,
            .panel-heading,
            .actions {
                padding: 20px;
            }

            .buttons .btn {
                flex: 1;
            }

            .id-tag {
                padding: 7px 11px;
            }
        }
    </style>
</head>

<body>
    <header class="topbar">
        <div class="topbar-inner">
            <a href="../chef-dashboard.php" class="brand">
                <img src="../asssets/logo.png" alt="">

                <div>
                    <strong>LULA'S PASTRY</strong>
                    <small>Chef workspace</small>
                </div>
            </a>

            <a class="back" href="../chef-dashboard.php">
                ← Dashboard
            </a>
        </div>
    </header>

    <main>
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="../chef-dashboard.php">Dashboard</a>
            / Edit product
        </nav>

        <div class="page-heading">
            <div>
                <h1>Edit product</h1>
                <p>Update the details of an existing item on your menu.</p>
            </div>

            <span class="id-tag">
                Product #<?php echo (int) $dish['id']; ?>
            </span>
        </div>

        <?php if ($success !== ''): ?>
            <div class="message success" role="status">
                <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="message error" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form
            method="post"
            action="edit-dish.php?id=<?php echo (int) $dish['id']; ?>"
            enctype="multipart/form-data"
        >
            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?php echo (int) $dish['id']; ?>"
            >

            <div class="editor-grid">
                <aside class="panel photo-panel" aria-label="Product photo">
                    <p class="section-label">Current product</p>

                    <img
                        id="photo-preview"
                        class="preview"
                        src="<?php echo htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                        alt="<?php echo htmlspecialchars($dish['name'], ENT_QUOTES, 'UTF-8'); ?>"
                    >

                    <h2>
                        <?php echo htmlspecialchars($dish['name'], ENT_QUOTES, 'UTF-8'); ?>
                    </h2>

                    <p class="saved-price">
                        $<?php echo number_format((float) $dish['price'], 2); ?>
                    </p>

                    <div class="photo-field">
                        <label for="photo">Replace photo</label>

                        <input
                            type="file"
                            id="photo"
                            name="photo"
                            accept="image/jpeg,image/png,image/gif,image/webp"
                        >

                        <p class="hint" id="photo-note" aria-live="polite">
                            Keep this field empty to use the current photo.
                        </p>
                    </div>
                </aside>

                <section class="panel" aria-labelledby="details-title">
                    <div class="panel-heading">
                        <h2 id="details-title">Product details</h2>
                        <p>Fields marked * are required.</p>
                    </div>

                    <div class="fields">
                        <div class="field">
                            <label for="name">Product name *</label>

                            <input
                                id="name"
                                type="text"
                                name="name"
                                value="<?php echo htmlspecialchars($dish['name'], ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>

                        <div class="pair">
                            <div class="field">
                                <label for="price">Price (USD) *</label>

                                <input
                                    id="price"
                                    type="number"
                                    name="price"
                                    step="0.01"
                                    min="0"
                                    value="<?php echo htmlspecialchars($dish['price'], ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                            </div>

                            <div class="field">
                                <label for="category">Category *</label>

                                <select id="category" name="category_id" required>
                                    <option value="">Choose a category</option>

                                    <?php foreach ($categories as $cat): ?>
                                        <option
                                            value="<?php echo (int) $cat['id']; ?>"
                                            <?php echo (int) $dish['category_id'] === (int) $cat['id'] ? 'selected' : ''; ?>
                                        >
                                            <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label for="description">Description</label>

                            <textarea
                                id="description"
                                name="description"
                            ><?php echo htmlspecialchars($dish['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>

                        <div class="field">
                            <label for="ingredients">Ingredients</label>

                            <textarea
                                id="ingredients"
                                name="ingredients"
                                placeholder="Flour, sugar, butter, chocolate..."
                            ><?php echo htmlspecialchars($dish['ingredients'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>

                    <footer class="actions">
                        <span class="action-note">
                            Changes apply when you save.
                        </span>

                        <div class="buttons">
                            <a
                                class="btn btn-secondary"
                                href="../chef-dashboard.php"
                            >
                                Cancel
                            </a>

                            <button class="btn btn-primary" type="submit">
                                Save changes
                            </button>
                        </div>
                    </footer>
                </section>
            </div>
        </form>
    </main>

    <script>
        const photoInput = document.getElementById('photo');
        const preview = document.getElementById('photo-preview');
        const note = document.getElementById('photo-note');
        const originalPhoto = preview.getAttribute('src');

        let previewUrl = null;

        photoInput.addEventListener('change', function () {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }

            previewUrl = null;

            const file = photoInput.files[0];

            if (!file) {
                preview.src = originalPhoto;
                note.textContent = 'Keep this field empty to use the current photo.';
                return;
            }

            const allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp'
            ];

            if (!allowedTypes.includes(file.type)) {
                photoInput.value = '';
                preview.src = originalPhoto;
                note.textContent = 'Choose a JPG, PNG, GIF or WebP image.';
                return;
            }

            previewUrl = URL.createObjectURL(file);
            preview.src = previewUrl;
            note.textContent = 'New photo selected. Save changes to apply it.';
        });
    </script>
</body>
</html>