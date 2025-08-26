<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "rytcms";

// Create connection
$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Handle fulfillment action
if (isset($_GET['fulfill'])) {
    $order_id = $_GET['fulfill'];
    $stmt = $conn->prepare("UPDATE customer_order SET ORDER_PAYMENT_STATUS='Fulfilled' WHERE ORDER_ID=?");
    $stmt->bind_param("i", $order_id);
    if ($stmt->execute()) {
        $msg = "Order ID $order_id marked as fulfilled.";
    } else {
        $msg = "Failed to fulfill order ID $order_id.";
    }
    $stmt->close();
}

// Fetch pending orders
$sql = "SELECT co.ORDER_ID, co.CUST_ID, co.PRODUCT_ID, co.ORDER_QTTY, co.ORDER_DATE, co.ORDER_WANTED, co.ORDER_PAYMENT_STATUS,
               c.CUST_NAME, p.PRODUCT_NAME 
        FROM customer_order co
        JOIN customer c ON co.CUST_ID = c.CUST_ID
        JOIN products_sell p ON co.PRODUCT_ID = p.PRODUCT_ID
        WHERE co.ORDER_PAYMENT_STATUS != 'Fulfilled'";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Order Fulfillment - RY's Tasty Creations</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background-image: url("background.png"); background-size: cover; background-position: center; background-repeat: no-repeat; }
    .sidebar { min-height: 100vh; background-color: #4b1c1c; color: white; }
    .sidebar h4 { text-align: center; padding: 20px 0; }
    .sidebar a { color: white; text-decoration: none; display: block; padding: 12px 20px; }
    .sidebar a:hover { background-color: #5e2e2e; }
    .card { background-color: rgba(255, 255, 255, 0.92); border-radius: 20px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25); padding: 30px; margin-bottom: 30px; }
    footer { background-color: #4b1c1c; color: white; text-align: center; padding: 15px; margin-top: 20px; }
</style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar">
            <h4>Owner Panel</h4>
            <a href="product_management.php" class="btn btn-outline-light text-start">Product Management</a>
            <a href="ingredient_management.php" class="btn btn-outline-light text-start">Ingredient Management</a>
            <a href="view_orders.php" class="btn btn-outline-light text-start">View Orders</a>
            <a href="sales_history.php" class="btn btn-outline-light text-start">View Sales History</a>
            <a href="inventory_management.php" class="btn btn-outline-light text-start">Inventory Management</a>
            <a href="order_fulfillment.php" class="btn btn-outline-light text-start">Order Fulfillment</a>
            <a href="cart_status.php" class="btn btn-outline-light text-start">View Cart Status</a>
            <a href="order_notification.php" class="btn btn-outline-light text-start">Order Notification</a>
            <a href="logout.php" class="btn btn-danger mt-auto text-start">Logout</a>
        </div>

        <div class="col-md-10 p-4">
            <h2 class="mb-4">Order Fulfillment</h2>
            <?php if (isset($msg)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <h4 class="mb-3">Pending Orders</h4>
                <table class="table table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Order Date</th>
                            <th>Wanted Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['ORDER_ID']) ?></td>
                            <td><?= htmlspecialchars($row['CUST_NAME']) ?></td>
                            <td><?= htmlspecialchars($row['PRODUCT_NAME']) ?></td>
                            <td><?= htmlspecialchars($row['ORDER_QTTY']) ?></td>
                            <td><?= htmlspecialchars($row['ORDER_DATE']) ?></td>
                            <td><?= htmlspecialchars($row['ORDER_WANTED']) ?></td>
                            <td><?= htmlspecialchars($row['ORDER_PAYMENT_STATUS']) ?></td>
                            <td>
                                <a href="?fulfill=<?= $row['ORDER_ID'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark as fulfilled?')">Mark as Fulfilled</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="text-center">No pending orders.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<footer>
    &copy; <?= date("Y") ?> RY's Tasty Creations
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>
