<?php
session_start();
require 'db_connection.php';

// Security: Ensure user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Security: Ensure this is a POST request from the cart
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cart.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

// (The code for consolidating pickup details remains the same)
if (isset($_POST['wanted_date']) && isset($_POST['wanted_time'])) {
    $wanted_dates = $_POST['wanted_date'];
    $wanted_times = $_POST['wanted_time'];
    foreach ($wanted_dates as $product_id => $date) {
        if (isset($wanted_times[$product_id])) {
            $_SESSION['cart_details'][$product_id] = [
                'wanted_date' => $date,
                'wanted_time' => $wanted_times[$product_id]
            ];
        }
    }
}

// Fetch Fresh Cart Data from DB
$stmt = $conn->prepare("
    SELECT c.PRODUCT_ID, c.CART_QTTY, p.PRODUCT_PRICE, p.PRODUCT_QTTY as stock_qtty
    FROM cart c
    JOIN products_sell p ON c.PRODUCT_ID = p.PRODUCT_ID
    WHERE c.CUST_ID = ?
");
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$cart_items_result = $stmt->get_result();
$cart_items = $cart_items_result->fetch_all(MYSQLI_ASSOC);

if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

// Process the Order (Database Transaction)
$conn->begin_transaction();
try {
    // Prepare the order insertion statement
    $insert_stmt = $conn->prepare("
        INSERT INTO customer_order 
        (CUST_ID, PRODUCT_ID, ORDER_QTTY, TOTAL_AMOUNT, ORDER_DATE, ORDER_WANTED, ORDER_WANTED_TIME, ORDER_PAYMENT_STATUS, ORDER_STATUS)
        VALUES (?, ?, ?, ?, NOW(), ?, ?, 'Unpaid', 'Pending')
    ");

    // =================================================================
    // NEW: Prepare the stock deduction statement
    // =================================================================
    $update_stock_stmt = $conn->prepare("
        UPDATE products_sell SET PRODUCT_QTTY = PRODUCT_QTTY - ? WHERE PRODUCT_ID = ?
    ");


    foreach ($cart_items as $item) {
        $product_id = $item['PRODUCT_ID'];
        $quantity = $item['CART_QTTY'];
        $total_amount = $item['PRODUCT_PRICE'] * $quantity;
        
        // --- Pre-order Stock Check ---
        // This is a failsafe in case two people order the last item at the same time.
        if ($quantity > $item['stock_qtty']) {
            throw new Exception("Sorry, an item in your cart is no longer in stock. Please review your cart.");
        }

        $details = $_SESSION['cart_details'][$product_id] ?? null;
        if (!$details || empty($details['wanted_date']) || empty($details['wanted_time'])) {
            throw new Exception("Pickup details are missing for an item. Please return to your cart.");
        }
        
        $wanted_date = $details['wanted_date'];
        $wanted_time = $details['wanted_time'];

        // 1. Insert the order
       $insert_stmt->bind_param("ssidss", $customer_id, $product_id, $quantity, $total_amount, $wanted_date, $wanted_time);
$insert_stmt->execute();

// ✅ Capture the last inserted order_id (we'll use this later)
$order_id = $conn->insert_id;

        
        // =================================================================
        // NEW: 2. Execute the stock deduction
        // =================================================================
        $update_stock_stmt->bind_param("is", $quantity, $product_id);
        $update_stock_stmt->execute();
    }
    
    // 3. Clear the user's cart from the database
    $delete_stmt = $conn->prepare("DELETE FROM cart WHERE CUST_ID = ?");
    $delete_stmt->bind_param("s", $customer_id);
    $delete_stmt->execute();
    
    // If all steps succeeded, commit the changes
    $conn->commit();
    
    unset($_SESSION['cart_details']);

   $_SESSION['message'] = 'Your order has been placed successfully!';
$_SESSION['message_type'] = 'success';
header("Location: payment.php?order_id=$order_id"); // ✅ Redirect to payment page instead

exit();


} catch (Exception $e) {
    // If any step failed, roll back ALL changes
    $conn->rollback();
    
    $_SESSION['message'] = "Error placing order: " . $e->getMessage();
    $_SESSION['message_type'] = 'danger';
    header("Location: cart.php");
    exit();
}
?>