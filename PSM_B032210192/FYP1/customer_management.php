<?php
session_start();
require 'db_connection.php';

// --- SECURITY CHECK & SESSION SETUP ---
if (!isset($_SESSION['seller_id'])) { // More robust to check for a specific ID
    $_SESSION['error'] = "You must be logged in as an owner to access this page.";
    header("Location: login.php");
    exit();
}
$owner_name = isset($_SESSION['seller_name']) ? htmlspecialchars($_SESSION['seller_name']) : 'Owner';

// --- DATA FETCHING ---
// Fetch all customers, showing the newest ones first.
$customers_result = $conn->query("SELECT CUST_ID, CUST_NAME, CUST_EMAIL, CUST_PHONE, CUST_CREATED_DATE FROM customer ORDER BY CUST_CREATED_DATE DESC");

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer Management - RY's Tasty Creations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    /* STYLES COPIED FROM DASHBOARD FOR CONSISTENCY */
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
    .table-card { background: white; border-radius: 0.75rem; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .table-hover tbody tr:hover { background-color: #f8f9fa; }
    /* Style for the clickable links */
    .contact-link { text-decoration: none; }
    .contact-link:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <!-- Sidebar Navigation -->
  <div class="sidebar d-flex flex-column p-0">
    <div class="sidebar-header text-center">
      <h4 class="fw-bold mb-1">RY's Tasty Creations</h4>
      <p class="text-white-50 mb-0">Owner Panel</p>
    </div>
    <ul class="nav flex-column my-4 flex-grow-1">
        <li class="nav-item"><a href="owner_dashboard.php" class="nav-link "><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="nav-item"><a href="view_orders.php" class="nav-link"><i class="bi bi-receipt"></i> Order Management</a></li>
        <li class="nav-item"><a href="produce_stock.php" class="nav-link"><i class="bi bi-hammer"></i> Production</a></li>
        <li class="nav-item"><a href="production_ordered.php" class="nav-link "><i class="bi bi-bell-fill"></i> Production Auto</a></li>
        <li class="nav-item"><a href="product_management.php" class="nav-link"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a href="ingredient_management.php" class="nav-link"><i class="bi bi-droplet"></i> Ingredients</a></li>
        <li class="nav-item"><a href="sales_reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link active"><i class="bi bi-people"></i> Customers</a></li>
    </ul>  
  </div>

  <!-- Main Content -->
  <main class="main-content d-flex flex-column">

    <!-- Added Top Header Bar for consistency -->
    <header class="main-header">
      <h1 class="h3 mb-0 fw-bold text-dark">Customer Management</h1>
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

    <!-- Content Wrapper for scrolling and padding -->
    <div class="content-wrapper">
      <p class="text-muted mb-4">View a list of all registered customers and access their order history.</p>

      <div class="table-card p-4">
        <h5 class="mb-3">Customer List</h5>
        <div class="table-responsive">
          <table class="table table-hover table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>Customer ID</th>
                    <th>Name</th>
                    <th>Contact Info</th>
                    <th>Date Joined</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($customers_result->num_rows > 0): ?>
                    <?php while ($row = $customers_result->fetch_assoc()): ?>
                    <?php
                        // --- NEW: Prepare interactive links for each customer ---
                        $email_link = 'mailto:' . htmlspecialchars($row['CUST_EMAIL']);
                        
                        $whatsapp_link = '#'; // Default value
                        $phone = $row['CUST_PHONE'];
                        if (!empty($phone)) {
                            $whatsapp_number = preg_replace('/[^\d]/', '', $phone);
                            if (substr($whatsapp_number, 0, 1) === '0') {
                                $whatsapp_number = '6' . $whatsapp_number; // Assuming Malaysian numbers
                            }
                            $whatsapp_link = 'https://wa.me/' . $whatsapp_number;
                        }
                    ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($row['CUST_ID']) ?></td>
                        <td><?= htmlspecialchars($row['CUST_NAME']) ?></td>
                        
                        <!-- MODIFIED: Contact Info with interactive links -->
                        <td>
                            <div>
                                <i class="bi bi-envelope-fill me-2 text-primary"></i>
                                <a href="<?= $email_link ?>" class="contact-link text-dark"><?= htmlspecialchars($row['CUST_EMAIL']) ?></a>
                            </div>
                            <?php if ($whatsapp_link !== '#'): ?>
                            <div class="mt-1">
                                <i class="bi bi-whatsapp me-2 text-success"></i>
                                <a href="<?= $whatsapp_link ?>" target="_blank" rel="noopener noreferrer" class="contact-link text-dark">
                                    <?= htmlspecialchars($row['CUST_PHONE']) ?>
                                </a>
                            </div>
                            <?php else: ?>
                            <div class="mt-1">
                                <i class="bi bi-telephone-fill me-2 text-muted"></i>
                                <span class="text-muted"><?= htmlspecialchars($row['CUST_PHONE'] ?: 'N/A') ?></span>
                            </div>
                            <?php endif; ?>
                        </td>
                        
                        <td><?= date('M j, Y', strtotime($row['CUST_CREATED_DATE'])) ?></td>
                        <td class="text-end">
                            <a href="view_orders.php?cust_id=<?= htmlspecialchars($row['CUST_ID']) ?>" class="btn btn-info btn-sm" title="View All Orders for this Customer">
                                <i class="bi bi-receipt"></i> View Orders
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="text-center p-4">No customers have registered yet.</td></tr>
                <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</body>
</html>