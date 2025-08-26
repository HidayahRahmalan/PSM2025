<?php
session_start();
require 'db_connection.php';

// 1. Security Check: Ensure the user is a logged-in customer
if (!isset($_SESSION['customer_id'])) {
    $_SESSION['message'] = "You must be logged in to request an order.";
    $_SESSION['message_type'] = "danger";
    header("Location: login.php");
    exit();
}

// 2. Process the form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 3. Get and validate form data
    $cust_id = $_SESSION['customer_id'];
    $product_id = $_POST['product_id'] ?? null;
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $wanted_date = $_POST['wanted_date'] ?? null;
    $wanted_time = $_POST['wanted_time'] ?? null;

    // Basic validation
    if (empty($product_id) || $quantity <= 0 || empty($wanted_date) || empty($wanted_time)) {
        $_SESSION['message'] = "Invalid request. Please fill out all fields correctly.";
        $_SESSION['message_type'] = "danger";
        header("Location: product.php");
        exit();
    }

    // Start a database transaction for data integrity
    $conn->begin_transaction();

    try {
        // 4. Fetch the product's price to calculate the total
        $price_stmt = $conn->prepare("SELECT PRODUCT_PRICE FROM products_sell WHERE PRODUCT_ID = ?");
        $price_stmt->bind_param("s", $product_id);
        $price_stmt->execute();
        $price_result = $price_stmt->get_result();

        if ($price_result->num_rows === 0) {
            throw new Exception("Product not found.");
        }
        $product = $price_result->fetch_assoc();
        $total_price = $product['PRODUCT_PRICE'] * $quantity;
        $price_stmt->close();
        
        // 5. Insert the request into the customer_order table
        $insert_stmt = $conn->prepare(
            "INSERT INTO customer_order 
            (CUST_ID, PRODUCT_ID, ORDER_QTTY, TOTAL_AMOUNT, ORDER_STATUS, ORDER_DATE, ORDER_PREFER_DATE, ORDER_PREFER_TIME, ORDER_WANTED, ORDER_WANTED_TIME, ORDER_PAYMENT_STATUS) 
            VALUES (?, ?, ?, ?, 'Pending', NOW(), ?, ?, ?, ?, 'Unpaid')"
        );
        $insert_stmt->bind_param("ssisssss", 
            $cust_id, 
            $product_id, 
            $quantity, 
            $total_price, 
            $wanted_date, 
            $wanted_time,
            $wanted_date,   // ORDER_WANTED (same as wanted_date)
            $wanted_time    // ORDER_WANTED_TIME (same as wanted_time)
        );
        
        if (!$insert_stmt->execute()) {
             throw new Exception("Failed to submit your order request. Please try again.");
        }
        $insert_stmt->close();
        
        // If everything is successful, commit the transaction
        $conn->commit();

        // 6. Set a success message and redirect the user
        $_SESSION['message'] = "Your request has been submitted! We will notify you when production begins.";
        $_SESSION['message_type'] = "success";

    } catch (Exception $e) {
        // If any error occurs, roll back the transaction
        $conn->rollback();
        $_SESSION['message'] = "Error: " . $e->getMessage();
        $_SESSION['message_type'] = "danger";
    }

    $conn->close();
    header("Location: product.php");
    exit();

} else {
    // Redirect if accessed directly without POST method
    header("Location: product.php");
    exit();
}
?>
