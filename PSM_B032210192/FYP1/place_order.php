<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['customer_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

// Fetch all items from the user's cart
$stmt = $conn->prepare("
    SELECT c.PRODUCT_ID, c.CART_QTTY, p.PRODUCT_NAME, p.PRODUCT_PRICE, p.PRODUCT_QTTY as stock_qtty
    FROM cart c 
    JOIN products_sell p ON c.PRODUCT_ID = p.PRODUCT_ID 
    WHERE c.CUST_ID = ?
");
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($cart_items)) {
    header("Location: cart.php"); // Cart is empty, do nothing
    exit();
}

// Start a transaction for safety
$conn->begin_transaction();

try {
    // --- THIS IS THE CORRECTED PART ---
    // The query now uses the correct column name: ORDER_PAYMENT_STATUS
    $order_stmt = $conn->prepare(
        "INSERT INTO customer_order (CUST_ID, PRODUCT_ID, ORDER_QTTY, ORDER_WANTED, ORDER_WANTED_TIME, TOTAL_AMOUNT, ORDER_PAYMENT_STATUS, ORDER_STATUS) 
         VALUES (?, ?, ?, ?, ?, ?, 'Unpaid', 'Pending')"
    );
    // --- END OF CORRECTION ---

    $stock_stmt = $conn->prepare("UPDATE products_sell SET PRODUCT_QTTY = PRODUCT_QTTY - ? WHERE PRODUCT_ID = ?");
    
    foreach ($cart_items as $item) {
        // --- 1. Final Stock Check (important for concurrency) ---
        if ($item['CART_QTTY'] > $item['stock_qtty']) {
            throw new Exception("Not enough stock for " . htmlspecialchars($item['PRODUCT_NAME']) . ". Please adjust your cart.");
        }

        // --- 2. Reduce Stock in products_sell table ---
        $stock_stmt->bind_param("is", $item['CART_QTTY'], $item['PRODUCT_ID']);
        $stock_stmt->execute();

        // --- 3. Insert into customer_order table ---
        $details = $_SESSION['cart_details'][$item['PRODUCT_ID']];
        $total_amount_item = $item['CART_QTTY'] * $item['PRODUCT_PRICE'];
        
        $order_stmt->bind_param(
            "ssisss", // The number of placeholders now matches the VALUES clause
            $customer_id,
            $item['PRODUCT_ID'],
            $item['CART_QTTY'],
            $details['wanted_date'],
            $details['wanted_time'],
            $total_amount_item
        );
        $order_stmt->execute();
    }

    // --- 4. If all went well, clear the user's cart ---
    $clear_cart_stmt = $conn->prepare("DELETE FROM cart WHERE CUST_ID = ?");
    $clear_cart_stmt->bind_param("s", $customer_id);
    $clear_cart_stmt->execute();

    // --- 5. Commit the transaction ---
    $conn->commit();

    // --- 6. Clean up session and redirect with success message ---
    unset($_SESSION['cart_details']);
    $_SESSION['message'] = "Your order has been placed successfully!";
    $_SESSION['message_type'] = "success";
    header("Location: orders.php"); // Redirect to a 'My Orders' page
    exit();

} catch (Exception $e) {
    // --- If anything failed, roll back all database changes ---
    $conn->rollback();

    // The original error message was "An error occurred during payment processing: "
    // which is a bit confusing. Let's make it clearer.
    $_SESSION['message'] = "Could not place order: " . $e->getMessage();
    $_SESSION['message_type'] = "danger";
    header("Location: cart.php");
    exit();
}