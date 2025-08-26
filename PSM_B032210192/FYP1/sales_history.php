<?php
// Database connection
$host = "localhost";
$user = "root";
$password = "";
$db = "rytcms"; // Replace with your actual DB name

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Default sort column and order
$sort_column = isset($_GET['sort_column']) ? $_GET['sort_column'] : 'HISTORY_SOLD_DATE';
$sort_order = isset($_GET['sort_order']) ? $_GET['sort_order'] : 'DESC';

// Fetch sales history data
$sql = "SELECT sh.HISTORY_ID, ps.PRODUCT_NAME, sh.HISTORY_SOLD_DATE, sh.HISTORY_SOLD_QTTY, sh.HISTORY_TOTAL_REVENUE 
        FROM sell_history sh
        JOIN products_sell ps ON sh.PRODUCT_ID = ps.PRODUCT_ID
        ORDER BY $sort_column $sort_order";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url("background.png");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            height: 100vh;
        }

        .sidebar {
            min-height: 100vh;
            background-color: #4b1c1c;
            color: white;
        }

        .sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 15px;
        }

        .sidebar a:hover {
            background-color: #6c757d;
        }

        .sales-history-card {
            backdrop-filter: blur(10px);
            background-color: rgba(255, 255, 255, 0.92);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            width: 100%;
            max-width: 1600px;
        }

        .form-label {
            font-weight: 500;
        }

        h4, h3 {
            font-weight: bold;
        }

        table {
            font-size: 1.1rem;
        }

        footer {
            text-align: center;
            color: white;
            padding: 15px 0;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
<h4 class="text-center mb-4">Owner Panel</h4>
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

        <!-- Main Content -->
        <div class="col-md-10 d-flex justify-content-center align-items-center" style="min-height: 100vh;">
            <div class="sales-history-card text-center">
                <h3 class="mb-4">Sales History</h3>
                
                <!-- Sort Dropdown -->
                <form method="GET" class="mb-4">
                    <div class="row justify-content-center">
                        <div class="col-md-4 mb-2">
                            <label for="sort_by" class="form-label">Sort By</label>
                            <select name="sort_column" id="sort_by" class="form-select" onchange="this.form.submit()">
                                <option value="HISTORY_SOLD_DATE" <?= $sort_column == 'HISTORY_SOLD_DATE' ? 'selected' : '' ?>>Sold Date</option>
                                <option value="PRODUCT_NAME" <?= $sort_column == 'PRODUCT_NAME' ? 'selected' : '' ?>>Product Name</option>
                                <option value="HISTORY_SOLD_QTTY" <?= $sort_column == 'HISTORY_SOLD_QTTY' ? 'selected' : '' ?>>Quantity Sold</option>
                                <option value="HISTORY_TOTAL_REVENUE" <?= $sort_column == 'HISTORY_TOTAL_REVENUE' ? 'selected' : '' ?>>Total Revenue</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label for="sort_order" class="form-label">Order</label>
                            <select name="sort_order" id="sort_order" class="form-select" onchange="this.form.submit()">
                                <option value="ASC" <?= $sort_order == 'ASC' ? 'selected' : '' ?>>Ascending</option>
                                <option value="DESC" <?= $sort_order == 'DESC' ? 'selected' : '' ?>>Descending</option>
                            </select>
                        </div>
                    </div>
                </form>

                <!-- Sales History Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Product Name</th>
                                <th>Sold Date</th>
                                <th>Quantity Sold</th>
                                <th>Total Revenue (RM)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Display sales history
                            if ($result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    echo "<tr>
                                            <td>" . htmlspecialchars($row['PRODUCT_NAME']) . "</td>
                                            <td>" . htmlspecialchars($row['HISTORY_SOLD_DATE']) . "</td>
                                            <td>" . htmlspecialchars($row['HISTORY_SOLD_QTTY']) . "</td>
                                            <td>" . htmlspecialchars($row['HISTORY_TOTAL_REVENUE']) . "</td>
                                          </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='4'>No sales history available.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<footer>
    &copy; 2025 RY's Tasty Creation. All rights reserved.
</footer>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>
