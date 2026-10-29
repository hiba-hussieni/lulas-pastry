<?php
session_start();

if (!isset($_SESSION["customer_logged_in"]) || $_SESSION["customer_logged_in"] !== true) {
    header("Location: customer-login.php?reset=1");
    exit();
}

require_once "db.php";

$categories = $pdo->query(
    "SELECT id, name FROM categories ORDER BY sort_order ASC, id ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$selectedCategory = 0;
$selectedCategoryName = 'All';

$requestedCategory = filter_var(
    $_GET['category'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

foreach ($categories as $category) {
    if (
        $requestedCategory !== false &&
        $requestedCategory !== null &&
        (int) $category['id'] === $requestedCategory
    ) {
        $selectedCategory = $requestedCategory;
        $selectedCategoryName = $category['name'];
        break;
    }
}

$sql = "SELECT id, name, description, price, category, image_url, available, stock_quantity
        FROM dishes
        WHERE available = 1 AND is_deleted = 0";

$params = [];

if ($selectedCategory > 0) {
    $sql .= " AND category_id = :category_id";
    $params[':category_id'] = $selectedCategory;
}

$sql .= " ORDER BY sort_order ASC, id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$customerName = isset($_SESSION["customer_name"])
    ? $_SESSION["customer_name"]
    : "Customer";
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Products | Lula's Pastry</title>

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
    }

    .cart-btn, .logout-btn {
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

    .cart-btn:hover, .logout-btn:hover {
      background: #9B6B5A;
    }

    .products-section { padding: 40px; }

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

    .grid-container {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      gap: 30px;
    }

    .pastry-card {
      background: #FFFDFB;
      border-radius: 20px;
      overflow: hidden;
      border: 1px solid rgba(197, 139, 120, 0.2);
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
      transition: 0.3s ease;
    }

    .pastry-card:hover {
      transform: translateY(-8px);
      box-shadow: 0 20px 40px rgba(155, 107, 90, 0.15);
    }

    .pastry-image {
      width: 100%;
      height: 200px;
      object-fit: cover;
      display: block;
    }

    .pastry-info { padding: 20px; }

    .pastry-name {
      font-size: 20px;
      font-weight: 700;
      color: #5D4037;
      margin-bottom: 8px;
    }

    .pastry-price {
      color: #C58B78;
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 10px;
    }

    .pastry-desc {
      font-size: 14px;
      color: #9B6B5A;
      margin-bottom: 15px;
      line-height: 1.5;
    }

    .qty-area {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 15px;
    }

    .qty-input {
      width: 80px;
      padding: 10px;
      border-radius: 10px;
      border: 2px solid #F8E6DE;
      outline: none;
      font-size: 14px;
    }

    .btn-primary {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #C58B78 0%, #E4B7A0 100%);
      border: none;
      color: white;
      border-radius: 12px;
      font-weight: 700;
      cursor: pointer;
      transition: 0.3s;
    }

    .btn-primary:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(197, 139, 120, 0.3);
    }

    .stock-status {
      display: inline-block;
      margin-bottom: 15px;
      padding: 6px 10px;
      border-radius: 20px;
      background: #F8E6DE;
      color: #5D4037;
      font-size: 13px;
      font-weight: 600;
    }

    .stock-status.sold-out {
      background: #EADBD4;
      color: #704F45;
    }

    .btn-primary:disabled {
      background: #EADBD4;
      color: #704F45;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    .qty-input:disabled {
      background: #F1E8E2;
      color: #795548;
      cursor: not-allowed;
    }

    .category-filters {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 18px;
    }

    .category-link {
      padding: 10px 18px;
      border: 1px solid #C58B78;
      border-radius: 25px;
      background: #FFFDFB;
      color: #5D4037;
      font-size: 14px;
      font-weight: 600;
      text-decoration: none;
    }

    .category-link:hover {
      background: #EED6CB;
    }

    .category-link.active {
      background: #9B6B5A;
      color: #FFFDFB;
    }

    .category-link:focus-visible {
      outline: 2px solid #5D4037;
      outline-offset: 3px;
    }

    .results-note {
      text-align: center;
      color: #795548;
      margin-bottom: 25px;
      font-size: 14px;
    }

    .empty-state {
      grid-column: 1 / -1;
      text-align: center;
      padding: 40px 20px;
      background: #FFFDFB;
      border-radius: 20px;
    }

    .empty-state p {
      margin-bottom: 20px;
    }

    @media(max-width:768px){
      .header { flex-direction: column; gap: 15px; }
      .products-section { padding: 20px; }
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

      <a href="cart.php" class="cart-btn">
        <i class="fas fa-shopping-cart"></i> Cart
      </a>

      <a href="logout-customer.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
      </a>
    </div>
  </div>

  <section class="products-section">
    <h2 class="section-title">Our Sweet Selection</h2>

    <nav class="category-filters" aria-label="Product categories">
      <a
        href="products.php"
        class="category-link<?php echo $selectedCategory === 0 ? ' active' : ''; ?>"
        <?php echo $selectedCategory === 0 ? 'aria-current="page"' : ''; ?>
      >All</a>

      <?php foreach ($categories as $category): ?>
        <a
          href="products.php?category=<?php echo (int) $category['id']; ?>"
          class="category-link<?php echo $selectedCategory === (int) $category['id'] ? ' active' : ''; ?>"
          <?php echo $selectedCategory === (int) $category['id'] ? 'aria-current="page"' : ''; ?>
        >
          <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <p class="results-note">
      <?php echo htmlspecialchars($selectedCategoryName, ENT_QUOTES, 'UTF-8'); ?>
      · <?php echo count($dishes); ?>
      <?php echo count($dishes) === 1 ? 'product' : 'products'; ?>
    </p>

    <div class="grid-container">
      <?php if (!$dishes): ?>
        <div class="empty-state">
          <p>
            <?php echo $selectedCategory > 0
              ? 'No products in this category yet.'
              : 'No products available right now.'; ?>
          </p>

          <?php if ($selectedCategory > 0): ?>
            <a href="products.php" class="category-link">
              View all products
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php foreach ($dishes as $dish): ?>
        <?php
          $stock = max(0, (int) $dish["stock_quantity"]);
          $soldOut = $stock === 0;
          $img = !empty($dish["image_url"])
            ? $dish["image_url"]
            : "assets/logo.png";
        ?>

        <div class="pastry-card">
          <img
            src="<?php echo htmlspecialchars($img); ?>"
            class="pastry-image"
            alt="<?php echo htmlspecialchars($dish["name"]); ?>"
          >

          <div class="pastry-info">
            <div class="pastry-name">
              <?php echo htmlspecialchars($dish["name"]); ?>
            </div>

            <div class="pastry-price">
              $<?php echo number_format($dish["price"], 2); ?>
            </div>

            <div class="pastry-desc">
              <?php echo htmlspecialchars($dish["description"]); ?>
            </div>

            <div class="stock-status<?php echo $soldOut ? ' sold-out' : ''; ?>">
              <?php echo $soldOut
                ? 'Sold out'
                : 'In stock: ' . $stock . ($stock === 1 ? ' unit' : ' units'); ?>
            </div>

            <form method="POST" action="cart.php">
              <input
                type="hidden"
                name="dish_id"
                value="<?php echo $dish["id"]; ?>"
              >

              <div class="qty-area">
                <label for="quantity-<?php echo (int) $dish["id"]; ?>">
                  <strong>Qty:</strong>
                </label>

                <input
                  type="number"
                  id="quantity-<?php echo (int) $dish["id"]; ?>"
                  name="quantity"
                  value="<?php echo $soldOut ? 0 : 1; ?>"
                  min="<?php echo $soldOut ? 0 : 1; ?>"
                  max="<?php echo $stock; ?>"
                  step="1"
                  class="qty-input"
                  required
                  <?php echo $soldOut ? 'disabled' : ''; ?>
                >
              </div>

              <button
                type="submit"
                class="btn-primary"
                <?php echo $soldOut ? 'disabled' : ''; ?>
              >
                <?php echo $soldOut ? 'Sold out' : 'Add to Cart'; ?>
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</body>
</html>