<?php
session_start();
require 'db_connection.php';

// This page's job is to SHOW an order's details, not create one.
// Its logic is correct for that purpose.

// Security: Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Security: Check if an order_id is provided in the URL
if (!isset($_GET['order_id'])) {
    header("Location: customerorder.php"); // Corrected: Redirect to the main orders list
    exit();
}

$customer_id = $_SESSION['customer_id'];
$order_id = $_GET['order_id'];

// Fetch the specific order, ensuring it belongs to the logged-in customer
$stmt = $conn->prepare("
    SELECT 
        co.ORDER_ID, co.ORDER_QTTY, co.ORDER_DATE, co.ORDER_WANTED, co.ORDER_WANTED_TIME,
        co.TOTAL_AMOUNT, co.ORDER_PAYMENT_STATUS, co.ORDER_STATUS,
        ps.PRODUCT_ID, ps.PRODUCT_NAME, ps.PRODUCT_IMAGE, ps.PRODUCT_PRICE
    FROM customer_order co
    JOIN products_sell ps ON co.PRODUCT_ID = ps.PRODUCT_ID
    WHERE co.ORDER_ID = ? AND co.CUST_ID = ?
");
$stmt->bind_param("is", $order_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();

// Security: If no order is found, redirect with a message
if ($result->num_rows === 0) {
    $_SESSION['message'] = "Order not found or you do not have permission to view it.";
    $_SESSION['message_type'] = 'danger';
    header("Location: customerorder.php"); // Corrected: Redirect to the main orders list
    exit();
}

$order = $result->fetch_assoc();

// Handle session messages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Detail #<?= htmlspecialchars($order['ORDER_ID']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .detail-product-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: .5rem;
        }
    </style>
</head>
<body>

<main class="container py-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <!-- Corrected: Link points to the new customerorder.php list page -->
            <li class="breadcrumb-item"><a href="customerorder.php">My Orders</a></li>
            <li class="breadcrumb-item active" aria-current="page">Order #<?= htmlspecialchars($order['ORDER_ID']) ?></li>
        </ol>
    </nav>
    <h1 class="mb-4">Order Detail</h1>

    <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span>Order #<?= htmlspecialchars($order['ORDER_ID']) ?></span>
                    <span>Placed on: <?= date("d M Y", strtotime($order['ORDER_DATE'])) ?></span>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <img src="data:image/jpeg;base64,<?= base64_encode($order['PRODUCT_IMAGE']) ?>" class="detail-product-image me-4" alt="<?= htmlspecialchars($order['PRODUCT_NAME']) ?>">
                        <div>
                            <h4 class="card-title"><?= htmlspecialchars($order['PRODUCT_NAME']) ?></h4>
                            <p class="card-text text-muted mb-1">
                                Price: RM <?= number_format($order['PRODUCT_PRICE'], 2) ?>
                            </p>
                            <p class="card-text text-muted">
                                Quantity: <?= htmlspecialchars($order['ORDER_QTTY']) ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Summary</h5>
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item"><strong>Pickup Date:</strong><br><?= $order['ORDER_WANTED'] ? date("l, d F Y", strtotime($order['ORDER_WANTED'])) : 'N/A' ?></li>
                    <li class="list-group-item"><strong>Pickup Time:</strong><br><?= $order['ORDER_WANTED_TIME'] ? date("h:i A", strtotime($order['ORDER_WANTED_TIME'])) : 'N/A' ?></li>
                    <li class="list-group-item"><strong>Order Status:</strong><br><span class="badge bg-info text-dark"><?= htmlspecialchars($order['ORDER_STATUS']) ?></span></li>
                    <li class="list-group-item"><strong>Payment Status:</strong><br><span class="badge bg-warning text-dark"><?= htmlspecialchars($order['ORDER_PAYMENT_STATUS']) ?></span></li>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <strong class="fs-5">Total</strong>
                        <span class="fs-5 fw-bold text-success">RM <?= number_format($order['TOTAL_AMOUNT'], 2) ?></span>
                    </li>
                </ul>
                <div class="card-body">
                    <!-- Conditional Action Buttons -->
                    <?php if (strtolower($order['ORDER_PAYMENT_STATUS']) == 'unpaid' && strtolower($order['ORDER_STATUS']) != 'cancelled' && strtolower($order['ORDER_STATUS']) != 'pending'): ?>
                        <a href="payment.php?order_id=<?= $order['ORDER_ID'] ?>" class="btn btn-success w-100 mb-2">
                            <i class="bi bi-credit-card-fill"></i> Pay Now
                        </a>
                    <?php endif; ?>
                    
                    <?php if (strtolower($order['ORDER_STATUS']) == 'pending'): ?>
                        <form action="cancel_order.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                            <input type="hidden" name="order_id" value="<?= $order['ORDER_ID'] ?>">
                            <button type="submit" class="btn btn-outline-danger w-100">
                                <i class="bi bi-x-circle"></i> Cancel Order
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>