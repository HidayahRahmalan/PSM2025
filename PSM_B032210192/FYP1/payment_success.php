<?php
session_start();
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Success</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="card shadow-lg p-4">
        <h2 class="text-success text-center">🎉 Payment Successful!</h2>
        <p class="text-center mt-3">Thank you for your purchase. Your order has been placed and is being processed.</p>
        <div class="text-center mt-4">
            <a href="home_customer.php" class="btn btn-primary">Return to Homepage</a>
            <a href="customerorder.php" class="btn btn-secondary">View My Orders</a>
        </div>
    </div>
</div>

</body>
</html>
