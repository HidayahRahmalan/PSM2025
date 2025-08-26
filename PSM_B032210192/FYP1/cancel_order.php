<?php
session_start();
require 'db_connection.php';

// Security: Must be logged in and a POST request
if (!isset($_SESSION['customer_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: orders.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$order_id = $_POST['order_id'];

// Use a transaction for safety. If one step fails, all are rolled back.
$conn->begin_transaction();

try {
    // 1. Get order details to ensure it's valid and to get product/quantity info.
    // Lock the row to prevent other processes from modifying it at the same time.
    $stmt = $conn->prepare("SELECT PRODUCT_ID, ORDER_QTTY, ORDER_STATUS FROM customer_order WHERE ORDER_ID = ? AND CUST_ID = ? FOR UPDATE");
    $stmt->bind_param("is", $order_id, $customer_id);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    
    // Check if the order exists and can be cancelled
    if (!$order) {
        throw new Exception("Order not found or you do not have permission to modify it.");
    }
    if (strtolower($order['ORDER_STATUS']) !== 'pending') {
        throw new Exception("This order cannot be cancelled as it is no longer pending.");
    }

    // 2. Update the order status to 'Cancelled'
    $update_order_stmt = $conn->prepare("UPDATE customer_order SET ORDER_STATUS = 'Cancelled' WHERE ORDER_ID = ?");
    $update_order_stmt->bind_param("i", $order_id);
    $update_order_stmt->execute();

    // 3. IMPORTANT: Return the quantity back to the product stock
    $update_stock_stmt = $conn->prepare("UPDATE products_sell SET PRODUCT_QTTY = PRODUCT_QTTY + ? WHERE PRODUCT_ID = ?");
    $update_stock_stmt->bind_param("is", $order['ORDER_QTTY'], $order['PRODUCT_ID']);
    $update_stock_stmt->execute();
    
    // 4. If all successful, commit the transaction
    $conn->commit();

    $_SESSION['message'] = "Order #{$order_id} has been successfully cancelled.";
    $_SESSION['message_type'] = 'success';

} catch (Exception $e) {
    // 5. If anything failed, roll back all changes
    $conn->rollback();
    $_SESSION['message'] = "Error: " . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
}

// Redirect back to the order detail page to show the result
header("Location: order_detail.php?order_id=" . $order_id);
exit();