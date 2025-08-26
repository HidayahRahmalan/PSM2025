<?php
session_start();
require 'db_connection.php';

// Security: Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['message'] = "You must be logged in to make a payment.";
    $_SESSION['message_type'] = "danger";
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = intval($_POST['order_id']);
    $amount = floatval($_POST['total_amount']);
    
    // Detect payment method (if passed from payment.php tabs)
    $method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Card';

    // ✅ Generate unique receipt number (e.g. RCPT202508200001)
    $receipt_no = 'RCPT' . date('YmdHis') . rand(100, 999);

    // Insert into payment table
    $stmt = $conn->prepare("
        INSERT INTO payment (ORDER_ID, PAYMENT_METHOD, PAYMENT_TOTAL, PAYMENT_STATUS, RECEIPT_NO)
        VALUES (?, ?, ?, 'Paid', ?)
    ");
    $stmt->bind_param("isds", $order_id, $method, $amount, $receipt_no);
    
    if ($stmt->execute()) {
        $payment_id = $stmt->insert_id;
        $stmt->close();

        // ✅ Update order status to Paid
        $update = $conn->prepare("UPDATE customer_order SET ORDER_PAYMENT_STATUS = 'Paid' WHERE ORDER_ID = ?");
        $update->bind_param("i", $order_id);
        $update->execute();
        $update->close();

        // ✅ Redirect to receipt page
        header("Location: receipt.php?payment_id=" . $payment_id);
        exit();
    } else {
        $_SESSION['message'] = "Payment failed. Please try again.";
        $_SESSION['message_type'] = "danger";
        header("Location: orders.php");
        exit();
    }
} else {
    $_SESSION['message'] = "Invalid request.";
    $_SESSION['message_type'] = "warning";
    header("Location: orders.php");
    exit();
}
