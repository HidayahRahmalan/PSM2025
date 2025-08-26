<?php
session_start();
require 'db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

// --- Fetch customer info for the page content ---
// The SELECT * will get all columns from your new table structure
$stmt = $conn->prepare("SELECT * FROM customer WHERE CUST_ID = ?");
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
    // Graceful error handling
    $_SESSION['message'] = "Could not find your profile. Please contact support.";
    $_SESSION['message_type'] = 'danger';
    header("Location: home_customer.php");
    exit();
}

// --- Get info for the navbar (for consistency) ---
$cust_name = $customer['CUST_NAME'];
$cart_item_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - RY's Tasty Creation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
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
        .list-group-item {
            font-size: 1.1rem;
        }
        .list-group-item .icon {
            color: #b33c86; /* Brand color for icons */
            width: 24px;
        }
    </style>
</head>
<body>

<!-- Consistent Navigation Bar -->
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

<!-- Profile Content -->
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card profile-card border-0 rounded-4">
                <div class="card-body p-4 p-md-5">
                    <h2 class="card-title text-center mb-5">Account Details</h2>

                    <!-- Updated Profile Information reflecting the new DB schema -->
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex align-items-center py-3">
                            <i class="bi bi-person-fill icon me-3"></i>
                            <span class="fw-bold me-auto">Name</span>
                            <span><?= htmlspecialchars($customer['CUST_NAME']) ?></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center py-3">
                            <i class="bi bi-envelope-fill icon me-3"></i>
                            <span class="fw-bold me-auto">Email</span>
                            <span><?= htmlspecialchars($customer['CUST_EMAIL']) ?></span>
                        </li>
                        <li class="list-group-item d-flex align-items-center py-3">
                            <i class="bi bi-telephone-fill icon me-3"></i>
                            <span class="fw-bold me-auto">Phone</span>
                            <span><?= htmlspecialchars($customer['CUST_PHONE']) ?></span>
                        </li>
                        <!-- Address field removed -->
                        <!-- New "Member Since" field added -->
                        <li class="list-group-item d-flex align-items-center py-3">
                             <i class="bi bi-calendar-check-fill icon me-3"></i>
                             <span class="fw-bold me-auto">Member Since</span>
                             <span class="text-end"><?= date("F j, Y", strtotime($customer['CUST_CREATED_DATE'])) ?></span>
                        </li>
                    </ul>

                    <!-- Action Buttons -->
                    <div class="text-center mt-5">
                        <a href="edit_profile.php" class="btn btn-primary btn-lg px-5">
                            <i class="bi bi-pencil-square"></i> Edit Profile
                        </a>
                    </div>
                </div>
            </div>
             <div class="text-center mt-4">
                <a href="home_customer.php" class="btn btn-link text-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer class="text-center py-4 text-muted border-top mt-4">
    © <?php echo date('Y'); ?> RY's Tasty Creation
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>