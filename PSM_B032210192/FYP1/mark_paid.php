<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "rytcms"; // change if needed

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $order_id = $_POST['order_id'];

    $stmt = $conn->prepare("UPDATE customer_order SET ORDER_PAYMENT_STATUS = 'Paid' WHERE ORDER_ID = ?");
    $stmt->bind_param("i", $order_id);

    if ($stmt->execute()) {
        echo "<script>alert('Order marked as Paid.'); window.location.href='view_orders.php';</script>";
    } else {
        echo "<script>alert('Error updating order.'); window.history.back();</script>";
    }

    $stmt->close();
}

$conn->close();
?>
