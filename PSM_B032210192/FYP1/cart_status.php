<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "rytcms";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all carts with their items and customer info
$carts_query = "SELECT c.*, cust.CUST_NAME, p.PRODUCT_NAME 
                FROM cart c 
                JOIN customer cust ON c.CUST_ID = cust.CUST_ID
                JOIN products_sell p ON c.PRODUCT_ID = p.PRODUCT_ID
                ORDER BY c.CART_ADDED_DATE DESC";
$carts_result = $conn->query($carts_query);

// Update cart status (if action requested)
if (isset($_GET['action']) )
{
    $cart_id = $_GET['id'];
    $action = $_GET['action'];
    
    // In a real application, you would update a status field in the cart table
    // Since your schema doesn't have a status field, we'll just demonstrate
    // You should add a STATUS field to your cart table for proper implementation
    $message = "<div class='alert alert-success'>Cart $cart_id marked as $action</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cart Status</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url("background.png");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
        }
        .sidebar {
            min-height: 100vh;
            background-color: #4b1c1c;
            color: white;
            position: fixed;
            width: 16.666667%;
        }
        .sidebar h4 {
            text-align: center;
            padding-top: 20px;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 12px 20px;
        }
        .sidebar a:hover {
            background-color: #5e2e2e;
        }
        .main-content {
            margin-left: 16.666667%;
            padding: 20px;
            width: 83.333333%;
        }
        .card {
            background-color: rgba(255, 255, 255, 0.92);
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            padding: 30px;
            margin-bottom: 30px;
        }
        .table-container {
            background-color: rgba(255, 255, 255, 0.92);
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            padding: 20px;
        }
        .status-pending {
            color: #ffc107;
            font-weight: bold;
        }
        .status-completed {
            color: #28a745;
            font-weight: bold;
        }
        .status-cancelled {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Fixed Sidebar -->
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

        <!-- Main Content Area -->
        <div class="main-content">
            <div class="card">
                <h2 class="mb-4">Cart Status</h2>
                
                <?php if (isset($message)) echo $message; ?>
                
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Cart ID</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Added Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($cart = $carts_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $cart['CART_ID'] ?></td>
                                    <td><?= $cart['CUST_NAME'] ?></td>
                                    <td><?= $cart['PRODUCT_NAME'] ?></td>
                                    <td><?= $cart['CART_QTTY'] ?></td>
                                    <td><?= $cart['CART_ADDED_DATE'] ?></td>
                                    <td>
                                        <a href="view_cart.php?id=<?= $cart['CART_ID'] ?>" class="btn btn-primary btn-sm">View</a>
                                        <a href="?action=process&id=<?= $cart['CART_ID'] ?>" class="btn btn-success btn-sm">Process</a>
                                        <a href="?action=cancel&id=<?= $cart['CART_ID'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this cart item?')">Cancel</a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>