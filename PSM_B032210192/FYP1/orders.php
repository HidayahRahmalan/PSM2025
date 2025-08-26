<?php
session_start();
require 'db_connection.php';

// Check login status
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$customer_name = $_SESSION['customer_name'] ?? 'Customer';

// --- Fetch order history for the logged-in customer ---
$stmt = $conn->prepare("
    SELECT 
        o.ORDER_ID,
        o.ORDER_DATE,
        o.ORDER_WANTED,
        o.ORDER_WANTED_TIME,
        o.ORDER_QTTY,
        o.TOTAL_AMOUNT,
        o.ORDER_STATUS,
        o.ORDER_PAYMENT_STATUS,
        p.PRODUCT_NAME,
        p.PRODUCT_IMAGE
    FROM customer_order o
    JOIN products_sell p ON o.PRODUCT_ID = p.PRODUCT_ID
    WHERE o.CUST_ID = ?
    ORDER BY o.ORDER_DATE DESC
");
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$orders = $orders_result->fetch_all(MYSQLI_ASSOC);

// Handle any feedback messages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);

// Helper function for dynamic status badges
function getStatusBadge($status) {
    $status = strtolower($status);
    switch ($status) {
        case 'pending':
            return 'bg-warning text-dark';
        case 'processing':
            return 'bg-info text-dark';
        case 'ready for pickup':
            return 'bg-primary';
        case 'completed':
            return 'bg-success';
        case 'cancelled':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - RY's Tasty Creation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .order-card {
            transition: box-shadow .2s ease-in-out, transform .2s ease-in-out;
        }
        .order-card:hover {
            box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important;
            transform: translateY(-3px);
        }
        .order-product-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: .5rem;
        }
        /* Ensure buttons are clickable over the stretched link */
        .order-card .btn {
            position: relative;
            z-index: 2;
        }
    </style>
</head>
<body>

<!-- Navigation Bar (Unchanged but included for completeness) -->
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="home_customer.php" style="color: #b33c86; font-weight: bold;">RY's Tasty Creation</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="home_customer.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="product.php">Products</a></li>
                <li class="nav-item"><a class="nav-link" href="cart.php"><i class="bi bi-cart"></i> Cart</a></li>
                 <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($customer_name) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item active" href="orders.php">My Orders</a></li>
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">My Order List</h1>
        <a href="product.php" class="btn btn-primary d-none d-md-inline-flex"><i class="bi bi-plus-lg me-2"></i>Place a New Order</a>
    </div>
    

    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($orders)): ?>
        <div class="card text-center py-5 shadow-sm border-0">
            <div class="card-body">
                <i class="bi bi-bag-x" style="font-size: 4rem; color: #6c757d;"></i>
                <h3 class="card-title mt-3">You have not placed any orders yet.</h3>
                <p class="card-text text-muted">Time to find some tasty creations!</p>
                <a href="product.php" class="btn btn-primary mt-2">Start Shopping</a>
            </div>
        </div>
    <?php else: ?>
        <!-- FIXED CARD-BASED LAYOUT -->
        <div class="list-group">
            <?php foreach ($orders as $order): ?>
                <!-- Changed from <a> to <div> and added position-relative for stretched-link -->
                <div class="list-group-item list-group-item-action order-card p-3 mb-3 shadow-sm border-0 rounded-3 position-relative">
                    <div class="row align-items-center g-3">
                        <!-- Product Image & Info -->
                        <div class="col-md-4 d-flex align-items-center">
                            <img src="data:image/jpeg;base64,<?= base64_encode($order['PRODUCT_IMAGE']) ?>" alt="<?= htmlspecialchars($order['PRODUCT_NAME']) ?>" class="order-product-img me-3">
                            <div>
                                <h6 class="mb-0 fw-bold">
                                    <!-- This stretched-link makes the card clickable, directing to order details -->
                                    <a href="order_detail.php?order_id=<?= $order['ORDER_ID'] ?>" class="text-decoration-none text-dark stretched-link">
                                        <?= htmlspecialchars($order['PRODUCT_NAME']) ?>
                                    </a>
                                </h6>
                                <small class="text-muted">Order #<?= $order['ORDER_ID'] ?></small>
                            </div>
                        </div>

                        <!-- Order & Pickup Date -->
                        <div class="col-md-4">
                            <div class="d-flex flex-column">
                                <div><small class="text-muted">Ordered:</small> <?= date("d M Y, h:i A", strtotime($order['ORDER_DATE'])) ?></div>
                                <div><small class="text-muted">Pickup:</small> <?= date("d M Y", strtotime($order['ORDER_WANTED'])) ?></div>
                            </div>
                        </div>

                        <!-- Status & Total -->
                        <div class="col-md-2 text-md-center">
                             <span class="badge fs-6 <?= getStatusBadge($order['ORDER_STATUS']) ?>"><?= htmlspecialchars(ucfirst($order['ORDER_STATUS'])) ?></span>
                             <div class="fw-bold mt-1">RM <?= number_format($order['TOTAL_AMOUNT'], 2) ?></div>
                        </div>

                        <!-- Action Button -->
                        <div class="col-md-2 text-md-end">
                            <?php if (strtolower($order['ORDER_PAYMENT_STATUS']) === 'unpaid' && strtolower($order['ORDER_STATUS']) !== 'cancelled'): ?>
                                <!-- FIX: Changed <button> to <a> tag linking to payment.php -->
                                <a href="payment.php?order_id=<?= $order['ORDER_ID'] ?>" class="btn btn-success btn-sm">Pay Now</a>
                            <?php else: ?>
                                 <!-- FIX: Changed <button> to <a> tag for consistency -->
                                 <a href="order_detail.php?order_id=<?= $order['ORDER_ID'] ?>" class="btn btn-outline-secondary btn-sm">View Details</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>