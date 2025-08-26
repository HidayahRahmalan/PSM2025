<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

// Fetch customer order history
$query = $conn->prepare("
    SELECT co.ORDER_ID, ps.PRODUCT_NAME, co.ORDER_QTTY, co.ORDER_DATE, 
           co.ORDER_PAYMENT_STATUS, co.TOTAL_AMOUNT, co.ORDER_STATUS
    FROM customer_order co
    JOIN products_sell ps ON co.PRODUCT_ID = ps.PRODUCT_ID
    WHERE co.CUST_ID = ?
    ORDER BY co.ORDER_DATE DESC
");
$query->bind_param("s", $customer_id);
$query->execute();
$result = $query->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders - RY's Tasty Creations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fffaf0; font-family: 'Segoe UI', sans-serif; }
        .container { padding-top: 40px; }
        h2 { color: #b33c86; font-weight: bold; }
        table { background-color: #fff; border-radius: 10px; overflow: hidden; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        th, td { padding: 12px 15px; vertical-align: middle; }
        th { background-color: #ffdee9; color: #6a1b4d; }
    </style>
</head>
<body>

<div class="container">
    <h2 class="text-center mb-4">📦 My Order History</h2>

    <?php if ($result->num_rows > 0): ?>
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Product</th>
                        <th>Qty</th>
                        <th>Order Date</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Total (RM)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['ORDER_ID']); ?></td>
                            <td><?= htmlspecialchars($row['PRODUCT_NAME']); ?></td>
                            <td><?= htmlspecialchars($row['ORDER_QTTY']); ?></td>
                            <td><?= htmlspecialchars($row['ORDER_DATE']); ?></td>
                            <td><span class="badge bg-primary"><?= htmlspecialchars($row['ORDER_STATUS']); ?></span></td>
                            <td><span class="badge bg-success"><?= htmlspecialchars($row['ORDER_PAYMENT_STATUS']); ?></span></td>
                            <td><?= number_format($row['TOTAL_AMOUNT'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-warning text-center">You have no past orders.</div>
    <?php endif; ?>
</div>

<footer class="text-center mt-5 text-muted">
    &copy; <?= date('Y'); ?> RY's Tasty Creations
</footer>

</body>
</html>
