<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['customer_id']) || !isset($_GET['product_id'])) {
    header("Location: cart.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$product_id = $_GET['product_id'];

// Remove the item from the database cart
$stmt = $conn->prepare("DELETE FROM cart WHERE CUST_ID = ? AND PRODUCT_ID = ?");
$stmt->bind_param("ss", $customer_id, $product_id);
$stmt->execute();

// Also remove its details from the session
unset($_SESSION['cart_details'][$product_id]);

$_SESSION['message'] = "Item removed from cart.";
$_SESSION['message_type'] = "info";

header("Location: cart.php");
exit();