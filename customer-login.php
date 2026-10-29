<?php
session_start();//store user login info as name, email,pass

require_once "db.php";//database connection

$error = "";
$success = "";

// If already logged in as customer
if (isset($_SESSION["customer_logged_in"]) && $_SESSION["customer_logged_in"] === true) { //if cuatomer is loged in 
    header("Location: products.php"); //take us to product.php page
    exit();// lesh exit la edman enno tekhdni aal products.php and not completing code execution
}

// Default tab
$activeTab = "login"; //bt7dd ayya tab bado ybayen login or register wl default hwe login

// Handle POST
if ($_SERVER["REQUEST_METHOD"] === "POST") { //if user clicks on submit orcreate account hyda lcode byshtghl otherwise no
    /*Get the action (login or register)So $action tells PHP:
✅  which form user submitted*/
    $action = isset($_POST["action"]) ? $_POST["action"] : "login";

    // =========================
    // ✅ LOGIN
    // =========================
    if ($action === "login") {

        $activeTab = "login";

        $email_or_username = isset($_POST["email_or_username"]) ? trim($_POST["email_or_username"]) : "";
        $password = isset($_POST["password"]) ? trim($_POST["password"]) : "";

        // One generic message for every failure, so guessing usernames
        // or emails against this form reveals nothing.
        $genericError = "Invalid username/email or password!";

        if ($email_or_username === "" || $password === "") {
            $error = $genericError;
        } else {
            $sql = "SELECT id, username, email, password, full_name
                    FROM users
                    WHERE (email = :val OR username = :val)
                      AND user_type = 'customer'
                    LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([":val" => $email_or_username]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                $error = $genericError;
            } else {
                $dbPassword = $customer["password"];
                $passwordOk = false;
                $needsRehash = false;

                if (password_verify($password, $dbPassword)) {
                    $passwordOk = true;
                } elseif ($password === $dbPassword) {
                    // Legacy plain-text password: accept once, then upgrade.
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
                            ":id" => $customer["id"],
                        ]);
                    }

                    session_regenerate_id(true);

                    $_SESSION["customer_logged_in"] = true;
                    $_SESSION["customer_id"] = $customer["id"];
                    $_SESSION["customer_name"] = $customer["full_name"];
                    $_SESSION["customer_email"] = $customer["email"];

                    header("Location: products.php");
                    exit();

                } else {
                    $error = $genericError;
                }
            }
        }
    }

    // =========================
    //  REGISTER
    // =========================
    if ($action === "register") {

        $activeTab = "register";

        $username = isset($_POST["regUsername"]) ? trim($_POST["regUsername"]) : "";//read entered input
        $email = isset($_POST["regEmail"]) ? trim($_POST["regEmail"]) : "";
        $password = isset($_POST["regPassword"]) ? trim($_POST["regPassword"]) : "";
        $confirmPassword = isset($_POST["confirmPassword"]) ? trim($_POST["confirmPassword"]) : "";
        
        if ($username === "" || $email === "" || $password === "" || $confirmPassword === "") {
            $error = "Please fill all fields!";
        } elseif ($password !== $confirmPassword) {
            $error = "Passwords do not match!";
        } else {

            // Check if username or email exists
            $checkSql = "SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([
                ":username" => $username,
                ":email" => $email
            ]);

            if ($checkStmt->fetch()) {
                $error = "Username or Email already exists!";
            } else {
                //makes password secure before storing it in data base.
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                // Insert new customer in database
                $insertSql = "INSERT INTO users (username, email, password, user_type, full_name)
                              VALUES (:username, :email, :password, 'customer', :full_name)";

                $insertStmt = $pdo->prepare($insertSql);
                $insertStmt->execute([
                    ":username" => $username,
                    ":email" => $email,
                    ":password" => $hashedPassword,
                    ":full_name" => $username
                ]);
                //success message enno acount created it return default tab to login
                $success = "Account created successfully! Please login.";
                $activeTab = "login";
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
    <title>Customer Login | Lula's Pastry</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet">

    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
 body {
    font-family: 'Montserrat', sans-serif;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    background-color: #F8E6DE;
    position: relative;
    overflow-y: auto;
    padding: 30px 0;
}
        .background-pattern {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image:
                radial-gradient(#C58B78 2px, transparent 2px),
                radial-gradient(#E4B7A0 1.5px, transparent 1.5px);
            background-size: 60px 60px;
            background-position: 0 0, 30px 30px;
            opacity: 0.1;
            z-index: 1;
        }
        .logo-watermark {
            position: absolute;
            top: 30px;
            left: 30px;
            font-family: 'Playfair Display', serif;
            font-size: 24px;
            color: rgba(197, 139, 120, 0.2);
            z-index: 1;
        }
        .logo-watermark span { color: rgba(155, 107, 90, 0.2); }
        .container {
            display: flex;
            width: 90%;
            max-width: 1000px;
            background: #FFFDFB;
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(155, 107, 90, 0.15);
            z-index: 2;
        }
        .welcome-side {
            flex: 1;
            background: linear-gradient(135deg, #F8E6DE 0%, #FFFDFB 100%);
            padding: 60px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            border-right: 1px solid rgba(197, 139, 120, 0.1);
        }
        .pastry-icon {
            font-size: 80px;
            color: #C58B78;
            margin-bottom: 20px;
        }
        .welcome-side h1 {
            font-family: 'Playfair Display', serif;
            font-size: 32px;
            color: #5D4037;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .welcome-side p {
            color: #9B6B5A;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
            max-width: 300px;
        }
        .feature-list { text-align: left; margin-top: 20px; }
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: #9B6B5A;
        }
        .feature-item i {
            color: #C58B78;
            margin-right: 10px;
            font-size: 14px;
        }

        .login-side {
            flex: 1;
            padding: 60px 50px;
            background: #FFFDFB;
        }
        .login-header { text-align: center; margin-bottom: 30px; }
        .login-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            color: #5D4037;
            margin-bottom: 10px;
        }
        .login-header p { color: #9B6B5A; font-size: 14px; }

        .selection {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            background: #F8E6DE;
            padding: 5px;
            border-radius: 12px;
        }
        .option-box {
            flex: 1;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            color: #9B6B5A;
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        .option-box.active {
            background: #FFFDFB;
            color: #C58B78;
            box-shadow: 0 5px 15px rgba(197, 139, 120, 0.1);
            border-color: #C58B78;
        }
        .form-section { display: none; }
        .form-section.active { display: block; }

        .form-group { margin-bottom: 20px; position: relative; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #5D4037;
            font-weight: 500;
            font-size: 14px;
        }
        .input-with-icon { position: relative; }
        .input-with-icon input {
            width: 100%;
            padding: 15px 45px 15px 20px;
            border: 2px solid #F8E6DE;
            border-radius: 12px;
            background: #FFFDFB;
            color: #5D4037;
            font-size: 15px;
        }
        .input-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #C58B78;
            font-size: 18px;
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #C58B78;
            font-size: 18px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
        }
        .toggle-password:hover {
            color: #9B6B5A;
        }
        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #C58B78 0%, #E4B7A0 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .error-box {
            background:#ffd6d6;
            color:#7a1d1d;
            padding:12px;
            border-radius:12px;
            font-weight:700;
            margin-bottom:15px;
            text-align:center;
        }
        .success-box {
            background:#d7ffd9;
            color:#1f7a29;
            padding:12px;
            border-radius:12px;
            font-weight:700;
            margin-bottom:15px;
            text-align:center;
        }
        .back-link { text-align: center; margin-top: 18px; }
        .back-link a { color:#C58B78; font-weight:700; text-decoration:none; }
        .back-link a:hover { text-decoration:underline; }

        @media (max-width: 768px) {
            .container { flex-direction: column; }
            .welcome-side { border-right: none; border-bottom: 1px solid rgba(197, 139, 120, 0.1); }
        }
    </style>
</head>
<body>

<div class="background-pattern"></div>

<div class="logo-watermark">
    LULA'S<span>PASTRY</span>
</div>

<div class="container">
    <!--  BROWN CAKE ICON -->
    <div class="welcome-side">
        <div class="pastry-icon"><i class="fas fa-birthday-cake"></i></div>
        <h1>Welcome Back!</h1>
        <p>Sign in to access your sweet account and continue your pastry journey with us.</p>

        <div class="feature-list">
            <div class="feature-item"><i class="fas fa-check-circle"></i> Track your orders</div>
            <div class="feature-item"><i class="fas fa-check-circle"></i> Save favorite pastries</div>
            <div class="feature-item"><i class="fas fa-check-circle"></i> Exclusive member offers</div>
            <div class="feature-item"><i class="fas fa-check-circle"></i> Fast checkout</div>
        </div>
    </div>

    <div class="login-side">
        <div class="login-header">
            <h2>Sweet Sign In</h2>
            <p>Access your account to order delicious pastries</p>
        </div>
       <!-- If error message exists, show it.-->
        <?php if($error != ""): ?>
            <div class="error-box"><?php echo htmlspecialchars($error); ?></div> <!--htmlspecialchars() prevents XSS attacks.-->
        <?php endif; ?>

        <?php if($success != ""): ?>S
            <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
<!-- when user clicks on login it shows login tab same for register-->
        <div class="selection">
            <div class="option-box <?php echo ($activeTab=="login") ? "active" : ""; ?>" id="loginTab" onclick="showForm('login')">
                Login
            </div>
            <div class="option-box <?php echo ($activeTab=="register") ? "active" : ""; ?>" id="registerTab" onclick="showForm('register')">
                Register
            </div>
        </div>

        <!-- ✅ Login Form -->
         <!-- Sends post request and action login -->
        <div id="loginForm" class="form-section <?php echo ($activeTab=="login") ? "active" : ""; ?>">
            <form method="POST" action="customer-login.php">
                <input type="hidden" name="action" value="login">

                <div class="form-group">
                    <label>Username or Email</label>
                    <div class="input-with-icon">
                        <input type="text" name="email_or_username" placeholder="Enter username or email" required>
                        <div class="input-icon"><i class="fas fa-user"></i></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-with-icon">
                        <input type="password" name="password" id="loginPassword" placeholder="Enter password" required>
                        <button type="button" class="toggle-password" data-target="loginPassword" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Sign In</button>
            </form>
        </div>

        <!-- ✅ Register Form sends post action and action register -->
        <div id="registerForm" class="form-section <?php echo ($activeTab=="register") ? "active" : ""; ?>">
            <form method="POST" action="customer-login.php">
                <input type="hidden" name="action" value="register">

                <div class="form-group">
                    <label>Username</label>
                    <div class="input-with-icon">
                        <input type="text" name="regUsername" placeholder="Choose username" required>
                        <div class="input-icon"><i class="fas fa-user"></i></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <div class="input-with-icon">
                        <input type="email" name="regEmail" placeholder="Enter email" required>
                        <div class="input-icon"><i class="fas fa-envelope"></i></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <div class="input-with-icon">
                        <input type="password" name="regPassword" id="regPassword" placeholder="Create password" required>
                        <button type="button" class="toggle-password" data-target="regPassword" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <div class="input-with-icon">
                        <input type="password" name="confirmPassword" id="confirmPassword" placeholder="Confirm password" required>
                        <button type="button" class="toggle-password" data-target="confirmPassword" aria-label="Show password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Create Account</button>
            </form>
        </div>

        <div class="back-link">
            <a href="index.php">⬅ Back to Home</a>
        </div>

    </div>

</div>
 <!-- JavaScript tab switching-->
<script>
    function showForm(type) {
        document.getElementById("loginTab").classList.remove("active");
        document.getElementById("registerTab").classList.remove("active");
        document.getElementById(type + "Tab").classList.add("active");

        document.getElementById("loginForm").classList.remove("active");
        document.getElementById("registerForm").classList.remove("active");
        document.getElementById(type + "Form").classList.add("active");
    }

    // Toggle password visibility so the customer can check what they typed,
    // especially useful in Register where they must match two fields.
    document.querySelectorAll('.toggle-password').forEach(function (button) {
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