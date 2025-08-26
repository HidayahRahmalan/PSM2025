<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['seller_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $order_id = $_POST['order_id'];
    $status = $_POST['order_status'];
    $prefer_date = !empty($_POST['order_prefer_date']) ? $_POST['order_prefer_date'] : NULL;
    $prefer_time = !empty($_POST['order_prefer_time']) ? $_POST['order_prefer_time'] : NULL;

    $sql = "UPDATE customer_order 
            SET ORDER_STATUS = ?, 
                ORDER_PREFER_DATE = ?, 
                ORDER_PREFER_TIME = ? 
            WHERE ORDER_ID = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $status, $prefer_date, $prefer_time, $order_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = "Order updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update order.";
    }

    header("Location: view_orders.php");
    exit();
}
?>
