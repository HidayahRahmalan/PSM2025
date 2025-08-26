<?php
session_start();
require 'db_connection.php';

// 1. Security & Validation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: product.php"); // Not a valid request
    exit();
}
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$is_cart_checkout = isset($_POST['cart_items']);

$conn->begin_transaction(); // Start a transaction for safety

try {
    // 2. Prepare the SQL statement for inserting an order
    $stmt = $conn->prepare("
        INSERT INTO customer_order 
        (CUST_ID, PRODUCT_ID, ORDER_QTTY, TOTAL_AMOUNT, ORDER_DATE, ORDER_WANTED, ORDER_WANTED_TIME, ORDER_PAYMENT_STATUS, ORDER_STATUS) 
        VALUES (?, ?, ?, ?, CURDATE(), ?, ?, 'Unpaid', 'Pending')
    ");

    if ($is_cart_checkout) {
        // --- PROCESS A FULL CART ORDER ---
        $wanted_date = $_POST['wanted_date'];
        $wanted_time = $_POST['wanted_time'];
        
        // Get product prices from the database to ensure accuracy
        $product_ids = array_keys($_POST['cart_items']);
        $id_placeholders = implode(',', array_fill(0, count($product_ids), '?'));
        $stmt_prices = $conn->prepare("SELECT PRODUCT_ID, PRODUCT_PRICE FROM products_sell WHERE PRODUCT_ID IN ($id_placeholders)");
        $stmt_prices->bind_param(str_repeat('s', count($product_ids)), ...$product_ids);
        $stmt_prices->execute();
        $prices_result = $stmt_prices->get_result();
        $prices = array_column($prices_result->fetch_all(MYSQLI_ASSOC), 'PRODUCT_PRICE', 'PRODUCT_ID');
        
        foreach ($_POST['cart_items'] as $product_id => $details) {
            $quantity = $details['quantity'];
            $price = $prices[$product_id];
            $total_amount = $price * $quantity;
            
            $stmt->bind_param("siidss", $customer_id, $product_id, $quantity, $total_amount, $wanted_date, $wanted_time);
            $stmt->execute();
        }
        
        // Clear the user's cart from the database
        $stmt_clear = $conn->prepare("DELETE FROM cart WHERE CUST_ID = ?");
        $stmt_clear->bind_param("s", $customer_id);
        $stmt_clear->execute();

    } else {
        // --- PROCESS A 'BUY NOW' ORDER ---
        $product_id = $_POST['product_id'];
        $quantity = $_POST['quantity'];
        $wanted_date = $_POST['wanted_date'];
        $wanted_time = $_POST['wanted_time'];
        $total_amount = $_POST['total_price']; // Already calculated

        $stmt->bind_param("siidss", $customer_id, $product_id, $quantity, $total_amount, $wanted_date, $wanted_time);
        $stmt->execute();
    }
    
    $stmt->close();
    $conn->commit(); // If all went well, save the changes

} catch (Exception $e) {
    $conn->rollback(); // If something failed, undo all changes
    // Log the error and show a generic message
    error_log($e->getMessage());
    $_SESSION['message'] = "An error occurred while placing your order. Please try again.";
    $_SESSION['message_type'] = 'danger';
    header("Location: checkout.php");
    exit();
}

// 4. Set a success message and redirect
$_SESSION['message'] = "Your order has been placed successfully!";
$_SESSION['message_type'] = 'success';

header("Location: orders.php");
exit();
?>