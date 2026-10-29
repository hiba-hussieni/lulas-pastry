<?php
session_start();

if (!isset($_SESSION["chef_logged_in"]) || $_SESSION["chef_logged_in"] !== true) {
    header("Location: ../chef-login.php?reset=1");
    exit();
}

require_once "../db.php";

if (empty($_SESSION['add_dish_csrf'])) {
    $_SESSION['add_dish_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['add_dish_csrf'];

$success = "";
$error = "";

// Form values
$name = "";
$description = "";
$price = "";
$categoryId = "";
$ingredients = "";

// Categories for the dropdown, in the chef's chosen order
$categories = $pdo->query(
    "SELECT id, name FROM categories ORDER BY sort_order ASC"
)->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST['csrf_token'] ?? null;

    if (!is_string($token) || !hash_equals($csrfToken, $token)) {
        http_response_code(403);
        exit('Your session token is invalid. Reload the page and try again.');
    }

    $name = isset($_POST["name"]) ? trim($_POST["name"]) : "";
    $description = isset($_POST["description"]) ? trim($_POST["description"]) : "";
    $price = isset($_POST["price"]) ? trim($_POST["price"]) : "";
    $categoryId = isset($_POST["category_id"]) ? trim($_POST["category_id"]) : "";
    $ingredients = isset($_POST["ingredients"]) ? trim($_POST["ingredients"]) : "";

    $categoryIds = array_column($categories, 'id');

    if ($name === "" || $price === "") {
        $error = "Please fill required fields (Name and Price).";
    } elseif ($categoryId === "" || !in_array((int) $categoryId, $categoryIds, true)) {
        $error = "Please choose a valid category.";
    } else {

        $imagePath = null;

        // ✅ Upload photo if exists
        if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] === 0) {

            $allowed = array("jpg", "jpeg", "png", "gif", "webp");
            $allowedMime = array(
                "image/jpeg" => true,
                "image/png"  => true,
                "image/gif"  => true,
                "image/webp" => true,
            );

            $fileName = $_FILES["photo"]["name"];
            $fileTmp = $_FILES["photo"]["tmp_name"];

            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            // Trust the file's real content, not just its name/extension.
            $realMime = false;
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $realMime = finfo_file($finfo, $fileTmp);
                finfo_close($finfo);
            }

            if (!in_array($ext, $allowed) || !isset($allowedMime[$realMime])) {
                $error = "Invalid image file. Allowed types: jpg, jpeg, png, gif, webp.";
            } else {

                $newFileName = time() . "_" . rand(1000, 9999) . "." . $ext;

                // ✅ Save into assets/products folder
                $targetFolder = "../assets/products/";
                $targetFile = $targetFolder . $newFileName;

                if (!is_dir($targetFolder)) {
                    mkdir($targetFolder, 0777, true);
                }

                if (move_uploaded_file($fileTmp, $targetFile)) {
                    $imagePath = "assets/products/" . $newFileName;
                } else {
                    $error = "Image upload failed.";
                }
            }
        }

        // ✅ If upload not failed, insert dish
        if ($error === "") {

            $chefId = isset($_SESSION["chef_id"]) ? $_SESSION["chef_id"] : null;

            // Legacy text column kept in sync with the chosen category,
            // until the customer-facing pages move to category_id too.
            $categoryName = "";
            foreach ($categories as $cat) {
                if ((int) $cat['id'] === (int) $categoryId) {
                    $categoryName = $cat['name'];
                    break;
                }
            }

            // Next position in this category's sort order.
            $maxOrder = $pdo->prepare(
                "SELECT COALESCE(MAX(sort_order), 0) FROM dishes"
            );
            $maxOrder->execute();
            $nextOrder = (int) $maxOrder->fetchColumn() + 1;

            $sql = "INSERT INTO dishes
                    (name, description, price, category, category_id, sort_order, image_url, available, ingredients, chef_id, created_at)
                    VALUES
                    (:name, :description, :price, :category, :category_id, :sort_order, :image_url, 1, :ingredients, :chef_id, NOW())";

            $stmt = $pdo->prepare($sql);
            $stmt->execute(array(
                ":name" => $name,
                ":description" => $description,
                ":price" => $price,
                ":category" => $categoryName,
                ":category_id" => $categoryId,
                ":sort_order" => $nextOrder,
                ":image_url" => $imagePath,
                ":ingredients" => $ingredients,
                ":chef_id" => $chefId
            ));

            $success = "Dish added successfully! ✅";

            // clear form after success
            $name = "";
            $description = "";
            $price = "";
            $categoryId = "";
            $ingredients = "";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Dish | Lula's Pastry</title>

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
      padding:40px 15px;
    }

    .box{
      background:#FFFDFB;
      max-width:650px;
      width:100%;
      border-radius:22px;
      padding:40px;
      box-shadow:0 15px 40px rgba(0,0,0,0.08);
    }

    h1{
      font-family:'Playfair Display',serif;
      font-size:34px;
      color:#C58B78;
      text-align:center;
      margin-bottom:25px;
    }

    label{
      font-weight:700;
      color:#5D4037;
      display:block;
      margin:15px 0 8px;
    }

    input, textarea, select{
      width:100%;
      padding:12px 14px;
      border-radius:12px;
      border:2px solid #F8E6DE;
      outline:none;
      font-size:15px;
      font-family:'Montserrat',sans-serif;
      background:white;
      color:#5D4037;
    }

    textarea{min-height:90px;resize:none;}

    .btn{
      margin-top:25px;
      width:100%;
      padding:14px;
      background:linear-gradient(135deg,#C58B78 0%,#E4B7A0 100%);
      border:none;
      color:white;
      font-weight:800;
      font-size:16px;
      border-radius:14px;
      cursor:pointer;
      transition:0.3s;
    }

    .btn:hover{
      transform:translateY(-2px);
      box-shadow:0 10px 22px rgba(197,139,120,0.35);
    }

    .msg-success{
      background:#d7ffd9;
      color:#1f7a29;
      padding:12px;
      border-radius:12px;
      margin-bottom:15px;
      font-weight:700;
      text-align:center;
    }

    .msg-error{
      background:#ffd6d6;
      color:#7a1d1d;
      padding:12px;
      border-radius:12px;
      margin-bottom:15px;
      font-weight:700;
      text-align:center;
    }

    .no-categories{
      background:#ffe8cc;
      color:#a15c00;
      padding:12px;
      border-radius:12px;
      margin-bottom:15px;
      font-weight:700;
      text-align:center;
    }

    .back{
      margin-top:18px;
      text-align:center;
    }
    .back a{
      text-decoration:none;
      color:#C58B78;
      font-weight:800;
    }
  </style>
</head>
<body>

<div class="box">
  <h1>Add New Dish</h1>

  <?php if ($success !== ""): ?>
    <div class="msg-success"><?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <?php if ($error !== ""): ?>
    <div class="msg-error"><?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <?php if (count($categories) === 0): ?>
    <div class="no-categories">
      No categories exist yet. Go to Manage Categories and add at least one before adding a dish.
    </div>
  <?php endif; ?>

  <form method="POST" action="add-dish.php" enctype="multipart/form-data">

    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <label>Dish Name *</label>
    <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>" required>

    <label>Description</label>
    <textarea name="description"><?php echo htmlspecialchars($description); ?></textarea>

    <label>Price *</label>
    <input type="number" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($price); ?>" required>

    <label>Category *</label>
    <select name="category_id" required <?php echo count($categories) === 0 ? 'disabled' : ''; ?>>
      <option value="">Choose a category</option>
      <?php foreach ($categories as $cat): ?>
        <option
          value="<?php echo (int) $cat['id']; ?>"
          <?php echo ((string) $categoryId === (string) $cat['id']) ? 'selected' : ''; ?>
        >
          <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label>Ingredients</label>
    <textarea name="ingredients" placeholder="Example: flour, sugar, butter, chocolate..."><?php echo htmlspecialchars($ingredients); ?></textarea>

    <label>Dish Photo</label>
    <input type="file" name="photo">

    <button type="submit" class="btn" <?php echo count($categories) === 0 ? 'disabled' : ''; ?>>Add Dish</button>
  </form>

  <div class="back">
    <a href="../chef-dashboard.php">⬅ Back to Dashboard</a>
  </div>
</div>

</body>
</html>