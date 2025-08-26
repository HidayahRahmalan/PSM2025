<?php
session_start();
require 'db_connection.php';

// CHANGED: Check for GET variables instead of POST method.
if (!isset($_SESSION['customer_id']) || !isset($_GET['product_id']) || !isset($_GET['quantity'])) {
    header("Location: cart.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$product_id = $_GET['product_id']; // CHANGED: from $_POST
$new_quantity = (int)$_GET['quantity']; // CHANGED: from $_POST

if ($new_quantity <= 0) {
    // If quantity is 0 or less, treat it as a removal (This is great logic!)
    header("Location: remove_from_cart.php?product_id=" . urlencode($product_id));
    exit();
}

// Check available stock (This is great logic!)
$stmt = $conn->prepare("SELECT PRODUCT_QTTY FROM products_sell WHERE PRODUCT_ID = ?");
$stmt->bind_param("s", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if ($product && $new_quantity > $product['PRODUCT_QTTY']) {
    $_SESSION['message'] = "Cannot update quantity. Only {$product['PRODUCT_QTTY']} items in stock.";
    $_SESSION['message_type'] = "warning";
} else {
    // Update the quantity in the cart table
    $update_stmt = $conn->prepare("UPDATE cart SET CART_QTTY = ? WHERE CUST_ID = ? AND PRODUCT_ID = ?");
    $update_stmt->bind_param("isi", $new_quantity, $customer_id, $product_id); // CHANGED: parameter types to 'isi'
    $update_stmt->execute();
    $_SESSION['message'] = "Cart updated successfully.";
    $_SESSION['message_type'] = "success";
}

header("Location: cart.php");
exit();