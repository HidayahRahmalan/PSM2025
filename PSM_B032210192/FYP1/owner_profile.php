<?php
session_start();
require 'db_connection.php';

// --- SECURITY CHECK & SESSION SETUP ---
if (!isset($_SESSION['seller_id'])) {
    $_SESSION['error'] = "You must be logged in to access this page.";
    header("Location: login.php");
    exit();
}
$seller_id = $_SESSION['seller_id'];
$owner_name = isset($_SESSION['seller_name']) ? htmlspecialchars($_SESSION['seller_name']) : 'Owner';
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- FORM HANDLING LOGIC (omitted for brevity, no changes here) ---
// ... (All your PHP logic for updating profile and password remains the same)
// 1. HANDLE PROFILE DETAILS UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $new_name = $_POST['seller_name'];
    $new_email = $_POST['seller_email'];
    $new_phone = $_POST['seller_phone'];
    $new_address = $_POST['seller_address'];
    $stmt = $conn->prepare("SELECT SELLER_ID FROM seller WHERE SELLER_EMAIL = ? AND SELLER_ID != ?");
    $stmt->bind_param("ss", $new_email, $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['message'] = "<div class='alert alert-danger'>Error: This email address is already in use by another account.</div>";
    } else {
        $stmt = $conn->prepare("UPDATE seller SET SELLER_NAME = ?, SELLER_EMAIL = ?, SELLER_PHONE = ?, SELLER_ADDRESS = ? WHERE SELLER_ID = ?");
        $stmt->bind_param("sssss", $new_name, $new_email, $new_phone, $new_address, $seller_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>Profile details updated successfully.</div>";
            $_SESSION['seller_name'] = $new_name;
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Error updating profile: " . $stmt->error . "</div>";
        }
    }
    $stmt->close();
    header("Location: owner_profile.php");
    exit();
}
// 2. HANDLE PASSWORD UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $stmt = $conn->prepare("SELECT SELLER_PASSWORD FROM seller WHERE SELLER_ID = ?");
    $stmt->bind_param("s", $seller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    if ($user && password_verify($current_password, $user['SELLER_PASSWORD'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) < 8) {
                 $_SESSION['message'] = "<div class='alert alert-danger'>Password must be at least 8 characters long.</div>";
            } else {
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE seller SET SELLER_PASSWORD = ? WHERE SELLER_ID = ?");
                $update_stmt->bind_param("ss", $new_hashed_password, $seller_id);
                if ($update_stmt->execute()) {
                    $_SESSION['message'] = "<div class='alert alert-success'>Password changed successfully.</div>";
                } else {
                    $_SESSION['message'] = "<div class='alert alert-danger'>Error changing password.</div>";
                }
                $update_stmt->close();
            }
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>New passwords do not match.</div>";
        }
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Incorrect current password.</div>";
    }
    $stmt->close();
    header("Location: owner_profile.php");
    exit();
}
// --- DATA FETCHING FOR DISPLAY (omitted for brevity, no changes here) ---
$stmt = $conn->prepare("SELECT SELLER_NAME, SELLER_EMAIL, SELLER_PHONE, SELLER_ADDRESS FROM seller WHERE SELLER_ID = ?");
$stmt->bind_param("s", $seller_id);
$stmt->execute();
$result = $stmt->get_result();
$owner = $result->fetch_assoc();
$stmt->close();
if (!$owner) {
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Owner Profile - RY's Tasty Creations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --primary-dark: #4b1c1c; --primary-accent: #ffc107; --border-color: #dee2e6; }
    body { background-color: #f4f7f6; display: flex; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
    .sidebar { width: 280px; background-color: var(--primary-dark); color: white; flex-shrink: 0; }
    .sidebar .nav-link { color: #e9ecef; padding: 0.8rem 1.5rem; font-size: 1.05rem; border-left: 4px solid transparent; transition: background-color 0.2s, color 0.2s; }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); color: white; }
    .sidebar .nav-link.active { border-left-color: var(--primary-accent); font-weight: 600; }
    .sidebar .nav-link .bi { margin-right: 0.8rem; font-size: 1.2rem; vertical-align: middle; }
    .sidebar-header { padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .main-content { flex-grow: 1; padding: 0; }
    .main-header { background-color: #fff; padding: 1rem 2.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .user-menu { display: flex; align-items: center; }
    .user-menu .welcome-text { margin-right: 1rem; color: #6c757d; }
    .content-wrapper { padding: 2.5rem; overflow-y: auto; }
    .form-card { background: white; border-radius: 0.75rem; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
  </style>
</head>
<body>
  <!-- Sidebar Navigation -->
  <div class="sidebar d-flex flex-column p-0">
    <div class="sidebar-header text-center"><h4 class="fw-bold mb-1">RY's Tasty Creations</h4><p class="text-white-50 mb-0">Owner Panel</p></div>
    <ul class="nav flex-column my-4 flex-grow-1">
        <li class="nav-item"><a href="owner_dashboard.php" class="nav-link "><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="nav-item"><a href="view_orders.php" class="nav-link"><i class="bi bi-receipt"></i> Order Management</a></li>
        <li class="nav-item"><a href="produce_stock.php" class="nav-link"><i class="bi bi-hammer"></i> Production</a></li>
        <li class="nav-item"><a href="production_ordered.php" class="nav-link "><i class="bi bi-bell-fill"></i> Production Auto</a></li>
        <li class="nav-item"><a href="product_management.php" class="nav-link"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a href="ingredient_management.php" class="nav-link"><i class="bi bi-droplet"></i> Ingredients</a></li>
        <li class="nav-item"><a href="sales_reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link"><i class="bi bi-people"></i> Customers</a></li>
        <!-- The old profile link that was here has been REMOVED -->
    </ul>
  </div>
  
  <!-- Main Content -->
  <main class="main-content d-flex flex-column">
    <header class="main-header">
      <h1 class="h3 mb-0 fw-bold text-dark">Owner Profile</h1>
      <!-- MODIFIED HEADER -->
      <div class="user-menu">
        <span class="welcome-text d-none d-sm-inline">Welcome, <strong><?= $owner_name ?></strong>!</span>
        <a href="owner_profile.php" class="btn btn-outline-secondary me-2">
            <i class="bi bi-person-fill me-1"></i>Profile
        </a>
        <a href="logout.php" class="btn btn-outline-danger">
          <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
      </div>
    </header>

    <div class="content-wrapper">
        <?= $message ?>
        <div class="row">
            <!-- Profile Details Form -->
            <div class="col-lg-7">
                <div class="form-card p-4">
                    <h5 class="mb-4">Profile Information</h5>
                    <form method="POST" action="owner_profile.php">
                        <div class="mb-3"><label for="seller_name" class="form-label">Full Name</label><input type="text" class="form-control" id="seller_name" name="seller_name" value="<?= htmlspecialchars($owner['SELLER_NAME']) ?>" required></div>
                        <div class="mb-3"><label for="seller_email" class="form-label">Email Address</label><input type="email" class="form-control" id="seller_email" name="seller_email" value="<?= htmlspecialchars($owner['SELLER_EMAIL']) ?>" required></div>
                        <div class="mb-3"><label for="seller_phone" class="form-label">Phone Number</label><input type="tel" class="form-control" id="seller_phone" name="seller_phone" value="<?= htmlspecialchars($owner['SELLER_PHONE']) ?>" required></div>
                        <div class="mb-3"><label for="seller_address" class="form-label">Address</label><textarea class="form-control" id="seller_address" name="seller_address" rows="3" required><?= htmlspecialchars($owner['SELLER_ADDRESS']) ?></textarea></div>
                        <div class="text-end"><button type="submit" name="update_profile" class="btn btn-primary" style="background-color: var(--primary-dark); border-color: var(--primary-dark);">Save Changes</button></div>
                    </form>
                </div>
            </div>
            <!-- Change Password Form -->
            <div class="col-lg-5">
                 <div class="form-card p-4">
                    <h5 class="mb-4">Change Password</h5>
                    <form method="POST" action="owner_profile.php">
                        <div class="mb-3"><label for="current_password" class="form-label">Current Password</label><input type="password" class="form-control" id="current_password" name="current_password" required></div>
                        <div class="mb-3"><label for="new_password" class="form-label">New Password</label><input type="password" class="form-control" id="new_password" name="new_password" required minlength="8"></div>
                        <div class="mb-3"><label for="confirm_password" class="form-label">Confirm New Password</label><input type="password" class="form-control" id="confirm_password" name="confirm_password" required></div>
                        <div class="text-end"><button type="submit" name="update_password" class="btn btn-warning">Update Password</button></div>
                    </form>
                 </div>
            </div>
        </div>
    </div>
  </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>