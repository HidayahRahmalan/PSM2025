<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "rytcms";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch notifications from the notification table
$notifications_query = "SELECT n.*, cust.CUST_NAME 
                       FROM notification n 
                       JOIN customer cust ON n.CUST_ID = cust.CUST_ID
                       WHERE n.NOTIF_IS_READ = FALSE
                       ORDER BY n.NOTIF_DATE DESC";
$notifications_result = $conn->query($notifications_query);

// Mark notification as read
if (isset($_GET['mark_read'])) {
    $notif_id = $_GET['mark_read'];
    $conn->query("UPDATE notification SET NOTIF_IS_READ = TRUE WHERE NOTIF_ID = $notif_id");
    header("Location: order_notification.php");
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notification SET NOTIF_IS_READ = TRUE WHERE NOTIF_IS_READ = FALSE");
    header("Location: order_notification.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order Notifications</title>
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
        .notification-item {
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin-bottom: 15px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 5px;
        }
        .notification-item.unread {
            background-color: rgba(255, 248, 225, 0.9);
            border-left: 4px solid #dc3545;
        }
        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .badge-danger {
            background-color: #dc3545;
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Order Notifications</h2>
                    <a href="?mark_all_read" class="btn btn-secondary">Mark All as Read</a>
                </div>
                
                <?php if ($notifications_result->num_rows > 0): ?>
                    <div class="notification-list">
                        <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                        <div class="notification-item unread">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5>Notification for <?= $notification['CUST_NAME'] ?></h5>
                                    <p class="mb-1"><?= $notification['NOTIF_MESSAGE'] ?></p>
                                    <p class="notification-time mb-0"><?= $notification['NOTIF_DATE'] ?></p>
                                </div>
                                <div>
                                    <span class="badge badge-danger mr-2">New</span>
                                    <a href="?mark_read=<?= $notification['NOTIF_ID'] ?>" class="btn btn-outline-secondary btn-sm">Mark Read</a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">No unread notifications at this time.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
</body>
</html>