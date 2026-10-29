<?php
session_start();

/* when user clicks from HOME, reset session so login page appears */
if (isset($_GET["reset"]) && $_GET["reset"] == 1) {
    session_unset();
    session_destroy();
    session_start();
}

require_once "db.php";

$error = "";

// If chef already logged in, go dashboard
if (isset($_SESSION["chef_logged_in"]) && $_SESSION["chef_logged_in"] === true) {
    header("Location: chef-dashboard.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = isset($_POST["email"]) ? trim($_POST["email"]) : "";
    $password = isset($_POST["password"]) ? trim($_POST["password"]) : "";

    // One generic message for every failure case, so a wrong guess
    // never reveals whether the email itself exists.
    $genericError = "Invalid email or password!";

    if ($email === "" || $password === "") {
        $error = $genericError;
    } else {
        $sql = "SELECT id, username, email, password, user_type, full_name
                FROM users
                WHERE email = :email AND user_type = 'chef'
                LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(":email" => $email));

        $chef = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$chef) {
            $error = $genericError;
        } else {
            $dbPassword = $chef["password"];
            $passwordOk = false;
            $needsRehash = false;

            if (password_verify($password, $dbPassword)) {
                $passwordOk = true;
            } elseif ($password === $dbPassword) {
                // Legacy plain-text password: accept it once, then
                // immediately upgrade it to a proper hash.
                $passwordOk = true;
                $needsRehash = true;
            }

            if ($passwordOk) {

                if ($needsRehash) {
                    $newHash = password_hash($password, PASSWORD_DEFAULT);
                    $rehash = $pdo->prepare(
                        "UPDATE users SET password = :password WHERE id = :id"
                    );
                    $rehash->execute([
                        ":password" => $newHash,
                        ":id" => $chef["id"],
                    ]);
                }

                // Prevent session fixation: start a fresh session id
                // now that the chef is authenticated.
                session_regenerate_id(true);

                $_SESSION["chef_logged_in"] = true;
                $_SESSION["chef_id"] = $chef["id"];
                $_SESSION["chef_name"] = $chef["full_name"];
                $_SESSION["chef_email"] = $chef["email"];

                header("Location: chef-dashboard.php");
                exit();
            } else {
                $error = $genericError;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Chef Login | Lula's Pastry</title>

  <!-- ✅ Keep your main CSS External CSS styling-->
  <link rel="stylesheet" href="css/style.css">

  <!-- ✅ Font Awesome (required so icon appears) -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- ✅ ONLY icon styling (does not affect anything else) -->
  <style>
    .chef-icon{
      text-align: center;
      margin-bottom: 15px;
    }
    .chef-icon i{
      font-size: 140px;     /* BIG */
      color: #C58B78;       /* SAME brown as cake */
    }

    /* Password show/hide toggle — self-contained, doesn't rely on style.css */
    .password-field-wrap{
      position: relative;
    }
    .password-field-wrap input{
      padding-right: 42px;
    }
    .toggle-password-btn{
      position: absolute;
      right: 12px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: #C58B78;
      font-size: 16px;
      padding: 4px;
      line-height: 1;
    }
    .toggle-password-btn:hover{
      color: #9B6B5A;
    }
  </style>
</head>

<body>

  <div class="background-pattern"></div>

  <div class="logo-watermark">
    LULA'S<span>PASTRY</span>
  </div>

  <div class="login-container">

    <div class="welcome-side">

      <!-- ✅ Chef tools icon instead of logo -->
      <div class="chef-icon">
        <i class="fa-solid fa-utensils"></i>
      </div>

      <h1>Welcome Chef!</h1>
      <p>Sign in to manage products, orders, and confirm customer requests.</p>

      <div class="feature-list">
        <div class="feature-item">✔ Add / Edit / Delete products</div>
        <div class="feature-item">✔ Confirm customer orders</div>
        <div class="feature-item">✔ Track baking & delivery dates</div>
        <div class="feature-item">✔ Update order status</div>
      </div>
    </div>

    <div class="login-side">
      <div class="login-header">
        <h2>Chef Sign In</h2>
        <p>Access your dashboard to manage Lula’s Pastry</p>
      </div>
         <!-- if error message exists show this message-->
      <?php if($error != ""): ?>
        <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="POST" action="chef-login.php">

        <div class="input-box">
          <label>Email</label>
          <input type="email" name="email" placeholder="ahmed@lulas.com" required><!--required means cannot sent empty-->
        </div>

        <div class="input-box">
          <label>Password</label>
          <div class="password-field-wrap">
            <input type="password" name="password" id="chefPassword" placeholder="12345" required>
            <button type="button" class="toggle-password-btn" data-target="chefPassword" aria-label="Show password">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <button type="submit" class="btn-primary">Sign In</button>
      </form>
      <!--it return us to home page esmo backlink-->
      <div class="back-link">
        <a href="index.php">⬅ Back to Home</a>
      </div>

    </div>

  </div>

  <script>
    document.querySelectorAll('.toggle-password-btn').forEach(function (button) {
      button.addEventListener('click', function () {
        const targetInput = document.getElementById(button.dataset.target);
        const icon = button.querySelector('i');

        if (targetInput.type === 'password') {
          targetInput.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
          button.setAttribute('aria-label', 'Hide password');
        } else {
          targetInput.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
          button.setAttribute('aria-label', 'Show password');
        }
      });
    });
  </script>

</body>
</html>