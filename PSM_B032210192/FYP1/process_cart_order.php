<?php
session_start();
require 'db_connection.php';

// Ensure customer is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

// Start a transaction to ensure all or nothing is saved
$conn->begin_transaction();

try {
    // 1. Fetch all items from the user's cart
    $stmt_cart = $conn->prepare("
        SELECT 
            c.PRODUCT_ID, 
            c.CART_QTTY, 
            p.PRODUCT_PRICE,
            p.PRODUCT_QTTY as stock_qtty
        FROM cart c
        JOIN products_sell p ON c.PRODUCT_ID = p.PRODUCT_ID
        WHERE c.CUST_ID = ?
    ");
    $stmt_cart->bind_param("s", $customer_id);
    $stmt_cart->execute();
    $cart_items_result = $stmt_cart->get_result();
    $cart_items = $cart_items_result->fetch_all(MYSQLI_ASSOC);

    if (empty($cart_items)) {
        throw new Exception("Your cart is empty.");
    }

    // Prepare the statement for inserting into customer_order
    $stmt_order = $conn->prepare("
        INSERT INTO customer_order 
        (CUST_ID, PRODUCT_ID, ORDER_QTTY, TOTAL_AMOUNT, ORDER_WANTED, ORDER_WANTED_TIME, ORDER_STATUS) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    // Prepare the statement for updating product stock
    $stmt_stock = $conn->prepare("
        UPDATE products_sell SET PRODUCT_QTTY = PRODUCT_QTTY - ? WHERE PRODUCT_ID = ?
    ");

    // 2. Loop through each cart item and create an order record for it
    foreach ($cart_items as $item) {
        // Check for sufficient stock
        if ($item['CART_QTTY'] > $item['stock_qtty']) {
            throw new Exception("Not enough stock for product ID " . $item['PRODUCT_ID']);
        }

        // Calculate total amount for this line item
        $total_amount = $item['CART_QTTY'] * $item['PRODUCT_PRICE'];

        // Get the pickup details from the session
        $details = $_SESSION['cart_details'][$item['PRODUCT_ID']] ?? ['wanted_date' => null, 'wanted_time' => null];
        $wanted_date = $details['wanted_date'];
        $wanted_time = $details['wanted_time'];
        $order_status = 'Pending'; // Set a default status

        // Bind and execute the insert statement for the order
        $stmt_order->bind_param(
            "ssidsis", 
            $customer_id, 
            $item['PRODUCT_ID'], 
            $item['CART_QTTY'], 
            $total_amount, 
            $wanted_date, 
            $wanted_time,
            $order_status
        );
        $stmt_order->execute();

        // Bind and execute the update statement for stock
        $stmt_stock->bind_param("is", $item['CART_QTTY'], $item['PRODUCT_ID']);
        $stmt_stock->execute();
    }

    // 3. Clear the user's cart from the database
    $stmt_clear_cart = $conn->prepare("DELETE FROM cart WHERE CUST_ID = ?");
    $stmt_clear_cart->bind_param("s", $customer_id);
    $stmt_clear_cart->execute();
    
    // 4. Clear the cart details from the session
    unset($_SESSION['cart_details']);

    // If all queries were successful, commit the transaction
    $conn->commit();

    // 5. Set a success message and redirect to the orders page
    $_SESSION['message'] = 'Your order has been placed successfully!';
    $_SESSION['message_type'] = 'success';
    header("Location: orders.php");
    exit();

} catch (Exception $e) {
    // If any query fails, roll back the transaction
    $conn->rollback();

    // Set an error message and redirect back to the cart
    $_SESSION['message'] = 'Failed to place order: ' . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    header("Location: cart.php");
    exit();
}
?>