<?php
// FILE: checkout_confirmation.php
session_start();
require 'db_connection.php';

// Security: Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Security: Check if this page is accessed only after the checkout process
if (!isset($_SESSION['new_order_ids']) || empty($_SESSION['new_order_ids'])) {
    header("Location: home_customer.php"); // Redirect if accessed directly
    exit();
}

$order_ids = $_SESSION['new_order_ids'];

// Prepare the SQL IN clause dynamically based on the number of new orders
$placeholders = implode(',', array_fill(0, count($order_ids), '?'));
$types = str_repeat('i', count($order_ids));

// Fetch details of all the newly created orders
$stmt = $conn->prepare("
    SELECT o.TOTAL_AMOUNT, p.PRODUCT_NAME, p.PRODUCT_IMAGE, o.ORDER_QTTY
    FROM customer_order o
    JOIN products_sell p ON o.PRODUCT_ID = p.PRODUCT_ID
    WHERE o.ORDER_ID IN ($placeholders)
");
$stmt->bind_param($types, ...$order_ids);
$stmt->execute();
$order_items_result = $stmt->get_result();

$grand_total = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Your Order - RY's Tasty Creations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white text-center py-4">
                    <i class="bi bi-receipt-cutoff text-success" style="font-size: 3rem;"></i>
                    <h2 class="mt-2">Order Confirmation</h2>
                    <p class="text-muted">Your order has been placed successfully! Please review the details and proceed to payment.</p>
                </div>
                <div class="card-body p-4">
                    <h5 class="mb-3">Order Summary</h5>
                    <ul class="list-group list-group-flush">
                        <?php while ($item = $order_items_result->fetch_assoc()): ?>
                            <?php $grand_total += $item['TOTAL_AMOUNT']; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <div class="d-flex align-items-center">
                                    <img src="data:image/jpeg;base64,<?= base64_encode($item['PRODUCT_IMAGE']) ?>" width="60" class="rounded me-3">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($item['PRODUCT_NAME']) ?></h6>
                                        <small class="text-muted">Quantity: <?= $item['ORDER_QTTY'] ?></small>
                                    </div>
                                </div>
                                <span class="fw-bold">RM <?= number_format($item['TOTAL_AMOUNT'], 2) ?></span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center fw-bold fs-5 px-0">
                        <span>Total Amount</span>
                        <span>RM <?= number_format($grand_total, 2) ?></span>
                    </div>
                </div>
                <div class="card-footer bg-white p-4">
                    <form action="payment.php" method="POST">
                        <button type="submit" class="btn btn-success btn-lg w-100">
                           <i class="bi bi-credit-card-fill me-2"></i> Confirm & Proceed to Payment
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>