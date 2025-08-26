<?php
session_start();
require 'db_connection.php';

// If a user is already logged in, redirect them away
if (isset($_SESSION['customer_id']) || isset($_SESSION['seller_id'])) {
    header("Location: home_customer.php"); // Or a general index page
    exit();
}

$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Retrieve and sanitize form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // 2. Basic Validation
    if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($password)) {
        $error_msg = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error_msg = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Invalid email format.";
    } else {
        // 3. Check if email already exists in EITHER customer or seller table
        // Check in 'customer' table
        $stmt = $conn->prepare("SELECT CUST_EMAIL FROM customer WHERE CUST_EMAIL = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $email_exists_customer = $stmt->num_rows > 0;
        $stmt->close();

        // Check in 'seller' table
        $stmt = $conn->prepare("SELECT SELLER_EMAIL FROM seller WHERE SELLER_EMAIL = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $email_exists_seller = $stmt->num_rows > 0;
        $stmt->close();

        if ($email_exists_customer || $email_exists_seller) {
            $error_msg = "This email address is already registered.";
        } else {
            // 4. All checks passed, proceed to insert the new seller
            
            // Hash the password for security
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate a unique Seller ID
            $seller_id = 'SELL' . strtoupper(uniqid()); // e.g., SELL65FBAA123BC

            $stmt = $conn->prepare("INSERT INTO seller (SELLER_ID, SELLER_NAME, SELLER_EMAIL, SELLER_PHONE, SELLER_ADDRESS, SELLER_PASSWORD) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssss", $seller_id, $name, $email, $phone, $address, $hashed_password);

            if ($stmt->execute()) {
                // Registration successful
                // Set a success message in the session and redirect to the login page
                $_SESSION['success'] = "Seller account created successfully! Please log in.";
                header("Location: login.php");
                exit();
            } else {
                $error_msg = "Error: Could not register the account. Please try again.";
            }
            $stmt->close();
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller Registration - RY's Tasty Creations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url("bg.jpg");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
        }
        .register-container {
            max-width: 550px;
            margin-top: 3rem;
            margin-bottom: 3rem;
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

<div class="container register-container">
    <div class="card shadow-lg">
        <div class="card-body p-4 p-md-5">
            <h2 class="card-title text-center fw-bold mb-4">Create a Seller Account</h2>

            <!-- Display Error Message -->
            <?php if (!empty($error_msg)): ?>
                <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <form method="POST" id="sellerRegisterForm">
                <div class="mb-3">
                    <label for="name" class="form-label">Business/Seller Name</label>
                    <input type="text" class="form-control" id="name" name="name" required>
                </div>
                 <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" required>
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Business Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Register as Seller</button>
                </div>
            </form>

            <p class="text-center mt-3">
                Already have an account? <a href="login.php">Login here</a>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>