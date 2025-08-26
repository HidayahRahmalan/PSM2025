<?php
session_start();
require 'db_connection.php';

// --- 1. SECURITY CHECK ---
// If the customer is not logged in, redirect them to the login page.
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['error'] = "You must be logged in to view this page.";
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

// --- 2. HANDLE FORM SUBMISSION (UPDATE LOGIC) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    // Validation: Check if the new email is already taken by ANOTHER user
    $stmt = $conn->prepare("SELECT CUST_ID FROM customer WHERE CUST_EMAIL = ? AND CUST_ID != ?");
    $stmt->bind_param("ss", $email, $customer_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Update failed. This email address is already in use by another account.";
    } else {
        // Email is available, proceed with the update
        $update_stmt = $conn->prepare("UPDATE customer SET CUST_NAME = ?, CUST_EMAIL = ?, CUST_PHONE = ? WHERE CUST_ID = ?");
        $update_stmt->bind_param("ssss", $name, $email, $phone, $customer_id);

        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Profile updated successfully!";
            $_SESSION['customer_name'] = $name; // Update session name to reflect immediately in navbar
        } else {
            $_SESSION['error'] = "An error occurred. Profile could not be updated.";
        }
        $update_stmt->close();
    }
    $stmt->close();

    // Redirect back to the same page to show the message and prevent form resubmission
    header("Location: edit_profile.php");
    exit();
}


// --- 3. FETCH DATA FOR THE PAGE ---
// Fetch current user data to pre-fill the form
$stmt = $conn->prepare("SELECT CUST_NAME, CUST_EMAIL, CUST_PHONE FROM customer WHERE CUST_ID = ?");
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    // This case is unlikely if session is valid, but good for robustness
    session_destroy();
    header("Location: login.php");
    exit();
}
$stmt->close();

// Get info for the navbar
$cust_name = $_SESSION['customer_name']; // Use session name for consistency
$cart_item_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - RY's Tasty Creation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Using the exact same styles from profile.php for consistency */
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .profile-card {
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<!-- Consistent Navigation Bar (Copied from profile.php) -->
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand" href="home_customer.php" style="color: #b33c86; font-weight: bold;">RY's Tasty Creation</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="home_customer.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="product.php">Products</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="cart.php">
                        <i class="bi bi-cart-fill"></i> Cart
                        <?php if ($cart_item_count > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $cart_item_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-current="page">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($cust_name) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="customerorder.php">My Orders</a></li>
                        <li><a class="dropdown-item active" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Edit Profile Content -->
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card profile-card border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="card-title text-center mb-4">Edit Profile Information</h2>

                    <!-- Display Success or Error Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success text-center">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger text-center">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- The Edit Form -->
                    <form method="post">
                        <div class="mb-3">
                            <label for="name" class="form-label fw-bold">Full Name</label>
                            <input type="text" class="form-control form-control-lg" id="name" name="name" value="<?php echo htmlspecialchars($user['CUST_NAME']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label fw-bold">Email Address</label>
                            <input type="email" class="form-control form-control-lg" id="email" name="email" value="<?php echo htmlspecialchars($user['CUST_EMAIL']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label fw-bold">Phone Number</label>
                            <input type="tel" class="form-control form-control-lg" id="phone" name="phone" value="<?php echo htmlspecialchars($user['CUST_PHONE']); ?>" required>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check-circle"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="text-center mt-4">
                <a href="profile.php" class="btn btn-link text-secondary">
                    <i class="bi bi-x-circle"></i> Cancel and Go Back
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Footer (Copied from profile.php) -->
<footer class="text-center py-4 text-muted border-top mt-4">
    © <?php echo date('Y'); ?> RY's Tasty Creation
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>