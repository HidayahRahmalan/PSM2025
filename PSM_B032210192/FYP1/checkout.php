<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$cust_id = $_SESSION['customer_id'];
$order_date = date("Y-m-d H:i:s");

// Get customer inputs
$order_wanted = $_POST['wanted_date'];
$order_wanted_time = $_POST['wanted_time'];

// Start transaction
$conn->begin_transaction();

try {
    // Get cart items
    $cart_sql = "SELECT c.PRODUCT_ID, c.QUANTITY, p.PRODUCT_PRICE 
                 FROM cart c 
                 JOIN products_sell p ON c.PRODUCT_ID = p.PRODUCT_ID 
                 WHERE c.CUST_ID = ?";
    $stmt = $conn->prepare($cart_sql);
    $stmt->bind_param("s", $cust_id);
    $stmt->execute();
    $cart_items = $stmt->get_result();

    $total_amount = 0;

    while ($item = $cart_items->fetch_assoc()) {
        $product_id = $item['PRODUCT_ID'];
        $qty = $item['QUANTITY'];
        $price = $item['PRODUCT_PRICE'];
        $subtotal = $qty * $price;
        $total_amount += $subtotal;

        // Insert order (seller prefer fields NULL by default)
        $stmt_order = $conn->prepare("INSERT INTO customer_order 
            (CUST_ID, PRODUCT_ID, ORDER_QTTY, ORDER_DATE, ORDER_WANTED, ORDER_WANTED_TIME, ORDER_PAYMENT_STATUS, TOTAL_AMOUNT, ORDER_STATUS, ORDER_PREFER_DATE, ORDER_PREFER_TIME)
            VALUES (?, ?, ?, ?, ?, ?, 'Unpaid', ?, 'Pending', NULL, NULL)");
        $stmt_order->bind_param("ssisssd", $cust_id, $product_id, $qty, $order_date, $order_wanted, $order_wanted_time, $subtotal);
        $stmt_order->execute();

        // Deduct ingredients
        $ingredient_sql = "SELECT INGREDIENT_ID, REQUIRED_QTY FROM product_ingredient WHERE PRODUCT_ID = ?";
        $stmt_ing = $conn->prepare($ingredient_sql);
        $stmt_ing->bind_param("s", $product_id);
        $stmt_ing->execute();
        $ingredients = $stmt_ing->get_result();

        while ($ing = $ingredients->fetch_assoc()) {
            $needed_qty = $ing['REQUIRED_QTY'] * $qty;

            $update_stock = "UPDATE item_ingredient 
                             SET INGREDIENT_STOCK = INGREDIENT_STOCK - ? 
                             WHERE INGREDIENT_ID = ?";
            $stmt_update = $conn->prepare($update_stock);
            $stmt_update->bind_param("is", $needed_qty, $ing['INGREDIENT_ID']);
            $stmt_update->execute();
        }
    }

    // Clear cart
    $clear_cart = $conn->prepare("DELETE FROM cart WHERE CUST_ID = ?");
    $clear_cart->bind_param("s", $cust_id);
    $clear_cart->execute();

    $conn->commit();

    header("Location: payment.php?amount=$total_amount");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    echo "Error processing checkout: " . $e->getMessage();
}
?>
