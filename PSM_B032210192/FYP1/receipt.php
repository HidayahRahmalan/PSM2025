<?php
session_start();
require 'db_connection.php';

// Security: Check if logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['payment_id'])) {
    $_SESSION['message'] = "No receipt selected.";
    $_SESSION['message_type'] = "warning";
    header("Location: orders.php");
    exit();
}

$payment_id = (int)$_GET['payment_id'];

// Fetch payment & order details
$stmt = $conn->prepare("
    SELECT p.RECEIPT_NO, p.PAYMENT_METHOD, p.PAYMENT_TOTAL, p.PAYMENT_DATE,
           o.ORDER_ID, o.ORDER_DATE, c.CUST_NAME, c.CUST_EMAIL
    FROM payment p
    JOIN customer_order o ON p.ORDER_ID = o.ORDER_ID
    JOIN customer c ON o.CUST_ID = c.CUST_ID
    WHERE p.PAYMENT_ID = ?
");
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Receipt not found.";
    $_SESSION['message_type'] = "danger";
    header("Location: orders.php");
    exit();
}

$receipt = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt - RY's Tasty Creations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .receipt { background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,.1); }
        .receipt h2 { margin-bottom: 20px; }
        .receipt-footer { margin-top: 30px; font-size: 0.9em; color: #6c757d; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="receipt mx-auto col-md-8">
        <div class="text-center mb-4">
            <h2>Receipt</h2>
            <p class="text-muted">Thank you for your payment!</p>
        </div>
        <table class="table">
            <tr><th>Receipt No:</th><td><?= $receipt['RECEIPT_NO'] ?></td></tr>
            <tr><th>Customer:</th><td><?= htmlspecialchars($receipt['CUST_NAME']) ?> (<?= $receipt['CUST_EMAIL'] ?>)</td></tr>
            <tr><th>Order ID:</th><td>#<?= $receipt['ORDER_ID'] ?></td></tr>
            <tr><th>Order Date:</th><td><?= $receipt['ORDER_DATE'] ?></td></tr>
            <tr><th>Payment Method:</th><td><?= $receipt['PAYMENT_METHOD'] ?></td></tr>
            <tr><th>Payment Amount:</th><td>RM <?= number_format($receipt['PAYMENT_TOTAL'], 2) ?></td></tr>
            <tr><th>Payment Date:</th><td><?= $receipt['PAYMENT_DATE'] ?></td></tr>
        </table>

        <div class="d-flex justify-content-between mt-4">
            <a href="orders.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Orders</a>
            <button class="btn btn-success" onclick="window.print();"><i class="bi bi-printer"></i> Print / Save PDF</button>
        </div>

        <div class="receipt-footer text-center mt-4">
            <p>&copy; <?= date("Y") ?> RY's Tasty Creations. All rights reserved.</p>
        </div>
    </div>
</div>
</body>
</html>
