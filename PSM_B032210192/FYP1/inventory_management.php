<?php
include("db_connection.php");
session_start();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Inventory Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background-image: url("background.png");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .sidebar {
            min-height: 100vh;
            background-color: #4b1c1c;
            color: white;
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

        .card {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.25);
            padding: 30px;
            margin-bottom: 30px;
        }

        .section-title {
            font-weight: bold;
            color: #4b1c1c;
            margin-bottom: 25px;
        }

        .table thead {
            background-color: #4b1c1c;
            color: #ffffff;
        }

        .stock-In {
            color: green;
            font-weight: bold;
        }

        .stock-Low {
            color: orange;
            font-weight: bold;
        }

        .stock-Out {
            color: red;
            font-weight: bold;
        }

        img.ingredient-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
        }

        footer {
            background-color: #4b1c1c;
            color: white;
            text-align: center;
            padding: 15px;
            margin-top: 20px;
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
        <div class="col-md-10 p-4">
            <!-- INGREDIENT INVENTORY -->
            <div class="card">
                <h2 class="section-title">Ingredient Inventory Status</h2>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Ingredient</th>
                                <th>Brand</th>
                                <th>Total Qty</th>
                                <th>Used</th>
                                <th>Remaining</th>
                                <th>Unit</th>
                                <th>Buy Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                                <th>Image</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "
                                SELECT 
                                    ITEM_ID, ITEM_NAME, ITEM_BRAND, ITEM_TOTAL_QTTY, ITEM_USED, 
                                    ITEM_REMAINING, ITEM_PURCHASE_UNIT, ITEM_BUY_DATE, ITEM_EXPIRED_DATE, ITEM_IMAGE,
                                    CASE 
                                        WHEN ITEM_REMAINING <= 0 THEN 'Out of Stock'
                                        WHEN ITEM_REMAINING <= 5 THEN 'Low Stock'
                                        ELSE 'In Stock'
                                    END AS STOCK_STATUS
                                FROM item_ingredient
                                ORDER BY ITEM_EXPIRED_DATE ASC
                            ";
                            $result = $conn->query($sql);

                            while ($row = $result->fetch_assoc()) {
                                $status_class = "stock-";
                                if ($row['STOCK_STATUS'] == 'Out of Stock') {
                                    $status_class .= "Out";
                                } elseif ($row['STOCK_STATUS'] == 'Low Stock') {
                                    $status_class .= "Low";
                                } else {
                                    $status_class .= "In";
                                }

                                echo "<tr>
                                    <td>{$row['ITEM_NAME']}</td>
                                    <td>{$row['ITEM_BRAND']}</td>
                                    <td>{$row['ITEM_TOTAL_QTTY']}</td>
                                    <td>{$row['ITEM_USED']}</td>
                                    <td>{$row['ITEM_REMAINING']}</td>
                                    <td>{$row['ITEM_PURCHASE_UNIT']}</td>
                                    <td>{$row['ITEM_BUY_DATE']}</td>
                                    <td>{$row['ITEM_EXPIRED_DATE']}</td>
                                    <td class='{$status_class}'>{$row['STOCK_STATUS']}</td>
                                    <td><img class='ingredient-img' src='data:image/jpeg;base64," . base64_encode($row['ITEM_IMAGE']) . "'></td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PRODUCT STATUS -->
            <div class="card">
                <h2 class="section-title">Product Production Status</h2>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Pieces per Pack</th>
                                <th>Total Produced</th>
                                <th>Total Remaining</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $sql = "
                                SELECT 
                                    ps.PRODUCT_ID, ps.PRODUCT_NAME, ps.PRODUCT_PCS_PER_PACK,
                                    COALESCE(SUM(pp.PP_QTTY), 0) AS TOTAL_PRODUCED,
                                    COALESCE(SUM(pp.PP_QTTY_REMAINING), 0) AS TOTAL_REMAINING
                                FROM products_sell ps
                                LEFT JOIN product_produce pp ON ps.PRODUCT_ID = pp.PRODUCT_ID
                                GROUP BY ps.PRODUCT_ID, ps.PRODUCT_NAME, ps.PRODUCT_PCS_PER_PACK
                            ";
                            $result = $conn->query($sql);

                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>
                                    <td>{$row['PRODUCT_NAME']}</td>
                                    <td>{$row['PRODUCT_PCS_PER_PACK']}</td>
                                    <td>{$row['TOTAL_PRODUCED']}</td>
                                    <td>{$row['TOTAL_REMAINING']}</td>
                                </tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <footer>
                &copy; <?= date("Y") ?> RY’s Tasty Creations. All rights reserved.
            </footer>
        </div>
    </div>
</div>

</body>
</html>
