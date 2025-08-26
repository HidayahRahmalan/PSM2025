<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit;
}

$customer_id = $_SESSION['customer_id'];
$product_id = $_POST['product_id'];

// Check if the item is already in the cart
$check = $conn->query("SELECT * FROM cart WHERE customer_id=$customer_id AND product_id=$product_id");

if ($check->num_rows > 0) {
    // If it exists, just increase the quantity
    $conn->query("UPDATE cart SET quantity = quantity + 1 WHERE customer_id=$customer_id AND product_id=$product_id");
} else {
    // Otherwise insert as a new cart item
    $conn->query("INSERT INTO cart (customer_id, product_id, quantity) VALUES ($customer_id, $product_id, 1)");
}

header("Location: cart.php");  // Redirect to the cart page after adding
exit;
?>
