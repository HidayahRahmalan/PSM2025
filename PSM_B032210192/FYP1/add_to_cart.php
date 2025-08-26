<?php
session_start(); // MUST be at the very top
require 'db_connection.php';

// Security: Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Check that ALL required fields are submitted from product_detail.php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['product_id'], $_POST['quantity'], $_POST['wanted_date'], $_POST['wanted_time'], $_POST['action'])) {
    
    $customer_id = $_SESSION['customer_id'];
    $product_id = $_POST['product_id'];
    $quantity = (int)$_POST['quantity'];
    $wanted_date = $_POST['wanted_date']; // Reading the correct 'wanted_date'
    $wanted_time = $_POST['wanted_time']; // Reading the correct 'wanted_time'
    $action = $_POST['action'];

    // Validation for quantity and date/time
    if ($quantity <= 0 || empty($wanted_date) || empty($wanted_time)) {
        $_SESSION['message'] = "Please provide a valid quantity and select a pickup date and time.";
        $_SESSION['message_type'] = "warning";
        header("Location: product_detail.php?product_id=" . $product_id);
        exit();
    }

    // 1. Check product stock (This part of your code is good)
    $prod_stmt = $conn->prepare("SELECT PRODUCT_NAME, PRODUCT_QTTY FROM products_sell WHERE PRODUCT_ID = ?");
    $prod_stmt->bind_param("s", $product_id);
    $prod_stmt->execute();
    $product_result = $prod_stmt->get_result();
    
    if ($product_result->num_rows == 0) {
        $_SESSION['message'] = "Product not found.";
        $_SESSION['message_type'] = "danger";
        header("Location: product.php");
        exit();
    }
    
    $product = $product_result->fetch_assoc();
    if ($quantity > $product['PRODUCT_QTTY']) {
        $_SESSION['message'] = "Sorry, not enough stock available for " . htmlspecialchars($product['PRODUCT_NAME']);
        $_SESSION['message_type'] = "warning";
        header("Location: product_detail.php?product_id=" . $product_id);
        exit();
    }

    // 2. Insert or Update the cart table (This part of your code is good)
    $cart_stmt = $conn->prepare("SELECT CART_ID, CART_QTTY FROM cart WHERE CUST_ID = ? AND PRODUCT_ID = ?");
    $cart_stmt->bind_param("ss", $customer_id, $product_id);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->get_result();

    if ($cart_result->num_rows > 0) {
        $existing_cart_item = $cart_result->fetch_assoc();
        $new_quantity = $existing_cart_item['CART_QTTY'] + $quantity;
        
        $update_stmt = $conn->prepare("UPDATE cart SET CART_QTTY = ? WHERE CART_ID = ?");
        $update_stmt->bind_param("ii", $new_quantity, $existing_cart_item['CART_ID']);
        $update_stmt->execute();
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO cart (CUST_ID, PRODUCT_ID, CART_QTTY) VALUES (?, ?, ?)");
        $insert_stmt->bind_param("ssi", $customer_id, $product_id, $quantity);
        $insert_stmt->execute();
    }
    
    // ===============================================================
    // THIS IS THE MOST IMPORTANT PART THAT FIXES THE ERROR
    // It saves the pickup details into the user's session.
    // ===============================================================
    if (!isset($_SESSION['cart_details'])) {
        $_SESSION['cart_details'] = [];
    }
    
    $_SESSION['cart_details'][$product_id] = [
        'wanted_date' => $wanted_date,
        'wanted_time' => $wanted_time
    ];
    // ===============================================================

    // 4. Set success message and redirect
    $_SESSION['message'] = htmlspecialchars($product['PRODUCT_NAME']) . " has been added to your cart.";
    $_SESSION['message_type'] = "success";

    if ($action === 'buy_now') {
        header("Location: cart.php");
    } else {
        header("Location: product_detail.php?product_id=" . $product_id);
    }
    exit();

} else {
    // If the form was not submitted correctly, send them back.
    $_SESSION['message'] = "An error occurred. Please try again.";
    $_SESSION['message_type'] = "danger";
    // Go back to the previous page if possible, otherwise to the products page
    header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'product.php'));
    exit();
}