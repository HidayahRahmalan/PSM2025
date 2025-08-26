<?php
session_start();
require 'db_connection.php';

// If a user is already logged in, redirect them away
if (isset($_SESSION['customer_id'])) {
    header("Location: home_customer.php");
    exit();
}
if (isset($_SESSION['seller_id'])) {
    header("Location: owner_dashboard.php");
    exit();
}

// --- Message Handling ---
$success_msg = '';
if (isset($_SESSION['success'])) {
    $success_msg = $_SESSION['success'];
    unset($_SESSION['success']);
}
$error_msg = '';

// --- Multi-Role Login Logic ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];
    $user_found = false;

    // --- 1. Attempt to log in as a Customer ---
    $stmt = $conn->prepare("SELECT CUST_ID, CUST_NAME, CUST_PASSWORD FROM customer WHERE CUST_EMAIL = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['CUST_PASSWORD'])) {
            // Customer login successful
            $_SESSION['user_role'] = 'customer';
            $_SESSION['customer_id'] = $user['CUST_ID'];
            $_SESSION['customer_name'] = $user['CUST_NAME'];
            $user_found = true;
            header("Location: home_customer.php");
            exit();
        }
    }


    // --- 2. If not a customer, attempt to log in as a Seller (Owner) ---
    if (!$user_found) {
        $stmt = $conn->prepare("SELECT SELLER_ID, SELLER_NAME, SELLER_PASSWORD FROM seller WHERE SELLER_EMAIL = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['SELLER_PASSWORD'])) {
                // Seller login successful
                $_SESSION['user_role'] = 'seller';
                $_SESSION['seller_id'] = $user['SELLER_ID'];
                $_SESSION['seller_name'] = $user['SELLER_NAME'];
                $user_found = true;
                header("Location: owner_dashboard.php");
                exit();
            }
        }
      
    }

    // --- 3. If login failed for both roles ---
    $error_msg = "Invalid email or password.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RY's Tasty Creations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url("bg.jpg");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
        }
        .login-container {
            max-width: 450px;
            margin-top: 5rem;
            margin-bottom: 5rem;
        }
        .card {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border: none;
            border-radius: 15px;
        }
    </style>
</head>
<body>

<div class="container login-container">
    <div class="card shadow-lg">
        <div class="card-body p-4 p-md-5">
            <h2 class="card-title text-center fw-bold mb-4">Login</h2>

            <!-- Display Success Message (e.g., after registration) -->
            <?php if (!empty($success_msg)): ?>
                <div class="alert alert-success text-center"><?php echo htmlspecialchars($success_msg); ?></div>
            <?php endif; ?>

            <!-- Display Login Error Message -->
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Login</button>
                </div>
            </form>

            <p class="text-center mt-3">
                Don't have an account? <a href="register.php">Register here</a>
            </p>
            <p class="text-center mt-1">
                <a href="forgotPassword.php">Forgot Password?</a>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>