<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

// Get customer name
$sql = "SELECT CUST_NAME FROM customer WHERE CUST_ID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$cust_name = $row['CUST_NAME'] ?? '';

// Check for unread notifications (for navbar badge)
$badgeQuery = $conn->prepare("SELECT COUNT(*) AS unread FROM notification WHERE CUST_ID = ? AND NOTIF_IS_READ = 0");
$badgeQuery->bind_param("s", $customer_id);
$badgeQuery->execute();
$badgeResult = $badgeQuery->get_result();
$badgeRow = $badgeResult->fetch_assoc();
$unread_count = $badgeRow['unread'];

// Mark all notifications as read (when viewing this page)
$updateRead = $conn->prepare("UPDATE notification SET NOTIF_IS_READ = 1 WHERE CUST_ID = ?");
$updateRead->bind_param("s", $customer_id);
$updateRead->execute();

// Get all notifications
$stmt = $conn->prepare("SELECT NOTIF_MESSAGE, NOTIF_DATE FROM notification WHERE CUST_ID = ? ORDER BY NOTIF_DATE DESC");
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Notifications</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: pink;
        }
        .notification-card {
            background-color: #fffbe6;
            border-left: 5px solid #ffc107;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .navbar-custom {
            background-color: #4b1c1c !important;
        }
        .badge-notif {
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 3px 8px;
            font-size: 12px;
            vertical-align: top;
            margin-left: 5px;
        }
    </style>
</head>
<body>

<!-- Navigation bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="#">RY's Tasty Creation</a>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="product.php">Products</a></li>
        <li class="nav-item"><a class="nav-link" href="cart.php">Cart</a></li>
        <li class="nav-item"><a class="nav-link" href="customerorder.php">My Orders</a></li>
        <li class="nav-item">
            <a class="nav-link active" href="notifications.php">
                Notifications
                <?php if ($unread_count > 0): ?>
                    <span class="badge-notif"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item"><a class="nav-link" href="profile.php">Profile</a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Notification section -->
<div class="container mt-5">
    <h2 class="mb-4">📢 Notifications for <?php echo htmlspecialchars($cust_name); ?></h2>

    <?php if (count($notifications) > 0): ?>
        <?php foreach ($notifications as $notif): ?>
            <div class="notification-card">
                <strong><?php echo htmlspecialchars($notif['NOTIF_MESSAGE']); ?></strong><br>
                <small class="text-muted"><?php echo date("d M Y, h:i A", strtotime($notif['NOTIF_DATE'])); ?></small>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="text-muted">You have no notifications at the moment.</p>
    <?php endif; ?>

    <div class="mt-4">
        <a href="home_customer.php" class="btn btn-primary">← Back to Homepage</a>
    </div>
</div>

<!-- Footer -->
<footer class="bg-dark text-white text-center py-3 mt-5">
    &copy; <?php echo date('Y'); ?> RY's Tasty Creation. All rights reserved.
</footer>

</body>
</html>
