<?php
session_start();
require 'db_connection.php';


// Count unread notifications
$unread_stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM notification WHERE CUST_ID = ? AND NOTIF_IS_READ = 0");
$unread_stmt->bind_param("s", $cust_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_data = $unread_result->fetch_assoc();
$unread_count = $unread_data['unread_count'] ?? 0;
$unread_stmt->close();


// --- SECURITY CHECK ---
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$cust_id = $_SESSION['customer_id'];

// Mark notifications as read if requested
if (isset($_GET['mark_read']) && $_GET['mark_read'] == '1') {
    $update_stmt = $conn->prepare("UPDATE notification SET NOTIF_IS_READ = 1 WHERE CUST_ID = ?");
    $update_stmt->bind_param("s", $cust_id);
    $update_stmt->execute();
    $update_stmt->close();
}

// Fetch notifications
$stmt = $conn->prepare("SELECT * FROM notification WHERE CUST_ID = ? ORDER BY NOTIF_DATE DESC");
$stmt->bind_param("s", $cust_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Notifications - RY's Tasty Creations</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
.container { max-width: 800px; margin-top: 50px; }
.unread { background-color: #e9f7ef; }
.nav-link .badge {
    font-size: 0.75rem;}
</style>
</head>
<body>
<div class="container">
<h3>Notifications</h3>
<a href="?mark_read=1" class="btn btn-sm btn-success mb-3">Mark All as Read</a>
<?php if ($result->num_rows > 0): ?>
<ul class="list-group">
<?php while ($notif = $result->fetch_assoc()): ?>
<li class="list-group-item <?= $notif['NOTIF_IS_READ'] == 0 ? 'unread' : '' ?>">
<strong><?= date('d M Y H:i', strtotime($notif['NOTIF_DATE'])) ?></strong><br>
<?= htmlspecialchars($notif['NOTIF_MESSAGE'] ?? '') ?>
</li>
<li class="nav-item">
    <a href="customer_notifications.php" class="nav-link position-relative">
        <i class="bi bi-bell-fill"></i> Notifications
        <?php if ($unread_count > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                <?= $unread_count ?>
                <span class="visually-hidden">unread messages</span>
            </span>
        <?php endif; ?>
    </a>
</li>

<?php endwhile; ?>
</ul>
<?php else: ?>
<div class="alert alert-info">No notifications yet.</div>
<?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
