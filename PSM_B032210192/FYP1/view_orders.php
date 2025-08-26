<?php
session_start();
require 'db_connection.php';

// --- SECURITY CHECK & SESSION SETUP ---
if (!isset($_SESSION['seller_id'])) {
    $_SESSION['error'] = "You must be logged in as an owner to access this page.";
    header("Location: login.php");
    exit();
}
$owner_name = isset($_SESSION['seller_name']) ? htmlspecialchars($_SESSION['seller_name'] ?? '') : 'Owner';

// For displaying success/error messages after actions
$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- ACTION HANDLING (POST REQUEST) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $order_id_to_update = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    $new_payment_status = $_POST['new_payment_status'];
    $new_prefer_date = $_POST['new_prefer_date'] ?? null;
    $new_prefer_time = $_POST['new_prefer_time'] ?? null;

    $stmt = $conn->prepare("UPDATE customer_order SET ORDER_STATUS = ?, ORDER_PAYMENT_STATUS = ?, ORDER_PREFER_DATE = ?, ORDER_PREFER_TIME = ? WHERE ORDER_ID = ?");
    $stmt->bind_param("ssssi", $new_status, $new_payment_status, $new_prefer_date, $new_prefer_time, $order_id_to_update);

    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>Order #{$order_id_to_update} has been updated.</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Error updating order: " . $stmt->error . "</div>";
    }
    $stmt->close();

    $redirect_params = ['status' => $new_status];
    if (isset($_POST['search_query']) && !empty($_POST['search_query'])) $redirect_params['search_query'] = $_POST['search_query'];
    if (isset($_POST['cust_id']) && !empty($_POST['cust_id'])) $redirect_params['cust_id'] = $_POST['cust_id'];
    header("Location: view_orders.php?" . http_build_query($redirect_params));
    exit();
}

// --- URL PARAMETER HANDLING (GET REQUEST) ---
$valid_statuses = ['Pending', 'Confirmed', 'Processing', 'Ready to Pickup', 'Completed', 'Cancelled'];
$current_status = isset($_GET['status']) && in_array($_GET['status'], $valid_statuses) ? $_GET['status'] : 'Pending';
$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';

// --- CUSTOMER FILTER ---
$page_title = "Order Management";
$is_customer_filtered = false;
$customer_id_filter = '';
$customer_name_filter = '';

if (!empty($_GET['cust_id'])) {
    $is_customer_filtered = true;
    $customer_id_filter = $_GET['cust_id'];
    $cust_stmt = $conn->prepare("SELECT CUST_NAME FROM customer WHERE CUST_ID = ?");
    $cust_stmt->bind_param("s", $customer_id_filter);
    $cust_stmt->execute();
    $cust_result = $cust_stmt->get_result();
    if ($cust_data = $cust_result->fetch_assoc()) {
        $customer_name_filter = $cust_data['CUST_NAME'];
        $page_title = "Orders for " . htmlspecialchars($customer_name_filter ?? '');
    }
    $cust_stmt->close();
}

// --- SORTING LOGIC ---
$valid_sort_columns = [
    'customer' => 'c.CUST_NAME',
    'product' => 'p.PRODUCT_NAME',
    'pickup_date' => 'o.ORDER_WANTED',
    'order_date' => 'o.ORDER_DATE',
    'payment' => 'o.ORDER_PAYMENT_STATUS'
];
$sort_by = isset($_GET['sort_by']) && array_key_exists($_GET['sort_by'], $valid_sort_columns) ? $_GET['sort_by'] : 'pickup_date';
$sort_dir = isset($_GET['sort_dir']) && in_array(strtolower($_GET['sort_dir']), ['asc', 'desc']) ? strtolower($_GET['sort_dir']) : 'asc';
$order_by_clause = $valid_sort_columns[$sort_by] . ' ' . strtoupper($sort_dir);
if ($sort_by === 'pickup_date') $order_by_clause .= ', o.ORDER_WANTED_TIME ' . strtoupper($sort_dir);
$order_by_clause .= ', o.ORDER_ID DESC';

// --- DYNAMIC SQL QUERY ---
$sql_base = "SELECT
            o.ORDER_ID, o.ORDER_QTTY, o.ORDER_DATE, o.ORDER_WANTED, o.ORDER_WANTED_TIME, o.ORDER_PREFER_DATE, o.ORDER_PREFER_TIME, o.TOTAL_AMOUNT, o.ORDER_STATUS, o.ORDER_PAYMENT_STATUS,
            c.CUST_NAME, c.CUST_PHONE, c.CUST_ID,
            p.PRODUCT_NAME
        FROM customer_order AS o
        JOIN customer AS c ON o.CUST_ID = c.CUST_ID
        JOIN products_sell AS p ON o.PRODUCT_ID = p.PRODUCT_ID";

$where_clauses = ["o.ORDER_STATUS = ?"];
$params = [$current_status];
$param_types = 's';

if ($is_customer_filtered) {
    $where_clauses[] = "o.CUST_ID = ?";
    $params[] = $customer_id_filter;
    $param_types .= 's';
}
if (!empty($search_query)) {
    $where_clauses[] = "(c.CUST_NAME LIKE ? OR p.PRODUCT_NAME LIKE ? OR o.ORDER_ID = ?)";
    $search_term_like = "%" . $search_query . "%";
    $params[] = $search_term_like;
    $params[] = $search_term_like;
    $params[] = $search_query;
    $param_types .= 'ssi';
}

$sql = $sql_base . " WHERE " . implode(' AND ', $where_clauses) . " ORDER BY " . $order_by_clause;

$stmt = $conn->prepare($sql);
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$orders_result = $stmt->get_result();

// --- HELPER FUNCTION ---
function generateSortLink($displayText, $columnKey, $currentSort, $currentDir, $currentStatus, $currentSearch, $currentCustId) {
    $newDir = ($currentSort === $columnKey && $currentDir === 'asc') ? 'desc' : 'asc';
    $icon = '';
    if ($currentSort === $columnKey) $icon = $currentDir === 'asc' ? ' <i class="bi bi-sort-up"></i>' : ' <i class="bi bi-sort-down"></i>';
    $queryParams = ['status' => $currentStatus, 'sort_by' => $columnKey, 'sort_dir' => $newDir];
    if (!empty($currentSearch)) $queryParams['search_query'] = $currentSearch;
    if (!empty($currentCustId)) $queryParams['cust_id'] = $currentCustId;
    $url = '?' . http_build_query($queryParams);
    return '<a href="' . $url . '" class="text-decoration-none text-dark">' . $displayText . $icon . '</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $page_title ?> - RY's Tasty Creations</title>
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
    .table-card { background: white; border-radius: 0.75rem; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .nav-tabs .nav-link { color: #6c757d; }
    .nav-tabs .nav-link.active { color: var(--primary-dark); font-weight: bold; border-color: #dee2e6 #dee2e6 #fff; }
    thead a { text-decoration: none; color: inherit; }
    thead a:hover { color: var(--primary-dark); }
</style>
</head>
<body>
  <!-- Sidebar   -->
  <div class="sidebar d-flex flex-column p-0">
    <div class="sidebar-header text-center"><h4 class="fw-bold mb-1">RY's Tasty Creations</h4><p class="text-white-50 mb-0">Owner Panel</p></div>
    <ul class="nav flex-column my-4 flex-grow-1">
        <li class="nav-item"><a href="owner_dashboard.php" class="nav-link "><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="nav-item"><a href="view_orders.php" class="nav-link active"><i class="bi bi-receipt"></i> Order Management</a></li>
        <li class="nav-item"><a href="produce_stock.php" class="nav-link"><i class="bi bi-hammer"></i> Production</a></li>
        <li class="nav-item"><a href="production_ordered.php" class="nav-link "><i class="bi bi-bell-fill"></i> Production Auto</a></li>
        <li class="nav-item"><a href="product_management.php" class="nav-link"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a href="ingredient_management.php" class="nav-link"><i class="bi bi-droplet"></i> Ingredients</a></li>
        <li class="nav-item"><a href="sales_reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link"><i class="bi bi-people"></i> Customers</a></li>
    </ul>
  </div>

<main class="main-content d-flex flex-column">
<header class="main-header">
<h1 class="h3 mb-0 fw-bold text-dark"><?= $page_title ?></h1>
<div class="user-menu">
<span class="welcome-text d-none d-sm-inline">Welcome, <strong><?= $owner_name ?></strong>!</span>
<a href="owner_profile.php" class="btn btn-outline-secondary me-2"><i class="bi bi-person-fill me-1"></i>Profile</a>
<a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
</div>
</header>

<div class="content-wrapper">
<?= $message ?>

<?php if ($is_customer_filtered): ?>
<div class="alert alert-info d-flex justify-content-between align-items-center mb-4">
<span>Showing orders for <strong><?= htmlspecialchars($customer_name_filter ?? '') ?></strong> only.</span>
<a href="view_orders.php" class="btn btn-sm btn-outline-info"><i class="bi bi-x-circle me-1"></i> Clear Filter & Show All</a>
</div>
<?php endif; ?>

<div class="table-card">
<ul class="nav nav-tabs px-3 pt-3">
<?php foreach ($valid_statuses as $status):
$tab_params = ['status' => $status];
if (!empty($search_query)) $tab_params['search_query'] = $search_query;
if ($is_customer_filtered) $tab_params['cust_id'] = $customer_id_filter;
?>
<li class="nav-item">
<a class="nav-link <?= $current_status == $status ? 'active' : '' ?>" href="?<?= http_build_query($tab_params) ?>"><?= $status ?></a>
</li>
<?php endforeach; ?>
</ul>

<div class="p-3">
<form action="view_orders.php" method="GET" class="mb-3">
<input type="hidden" name="status" value="<?= htmlspecialchars($current_status) ?>">
<?php if ($is_customer_filtered): ?>
<input type="hidden" name="cust_id" value="<?= htmlspecialchars($customer_id_filter ?? '') ?>">
<?php endif; ?>
<div class="input-group">
<input type="text" name="search_query" class="form-control" placeholder="Search by Order ID, Customer, or Product Name..." value="<?= htmlspecialchars($search_query ?? '') ?>">
<button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> Search</button>
<?php if (!empty($search_query)):
$clear_search_params = ['status' => $current_status];
if ($is_customer_filtered) $clear_search_params['cust_id'] = $customer_id_filter;
?>
<a href="view_orders.php?<?= http_build_query($clear_search_params) ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i> Clear</a>
<?php endif; ?>
</div>
</form>

<div class="table-responsive">
<table class="table table-hover align-middle">
<thead class="table-light">
<tr>
<th>Order ID</th>
<?php if (!$is_customer_filtered): ?>
<th><?= generateSortLink('Customer', 'customer', $sort_by, $sort_dir, $current_status, $search_query, $customer_id_filter) ?></th>
<?php endif; ?>
<th><?= generateSortLink('Product Details', 'product', $sort_by, $sort_dir, $current_status, $search_query, $customer_id_filter) ?></th>
<th><?= generateSortLink('Pickup Date', 'pickup_date', $sort_by, $sort_dir, $current_status, $search_query, $customer_id_filter) ?></th>
<th>Preferred Pickup (Seller)</th>
<th><?= generateSortLink('Order Date', 'order_date', $sort_by, $sort_dir, $current_status, $search_query, $customer_id_filter) ?></th>
<th>Total</th>
<th><?= generateSortLink('Payment', 'payment', $sort_by, $sort_dir, $current_status, $search_query, $customer_id_filter) ?></th>
<th>Status</th>
<th class="text-end">Actions</th>
</tr>
</thead>
<tbody>
<?php if ($orders_result->num_rows > 0): ?>
<?php while ($order = $orders_result->fetch_assoc()): ?>
<tr>
<td class="fw-bold">#<?= htmlspecialchars($order['ORDER_ID'] ?? '') ?></td>
<?php if (!$is_customer_filtered): ?>
<td>
<div><?= htmlspecialchars($order['CUST_NAME'] ?? '') ?></div>
<small class="text-muted"><?= htmlspecialchars($order['CUST_PHONE'] ?? '') ?></small>
</td>
<?php endif; ?>
<td>
<div><?= htmlspecialchars($order['PRODUCT_NAME'] ?? '') ?></div>
<small class="text-muted">Qty: <?= htmlspecialchars($order['ORDER_QTTY'] ?? '') ?></small>
</td>
<td>
<div><?= !empty($order['ORDER_WANTED']) ? date('D, M j, Y', strtotime($order['ORDER_WANTED'])) : 'N/A' ?></div>
<small class="text-muted"><?= !empty($order['ORDER_WANTED_TIME']) ? date('g:i A', strtotime($order['ORDER_WANTED_TIME'])) : '' ?></small>
</td>
<td>
<div><?= !empty($order['ORDER_PREFER_DATE']) ? date('D, M j, Y', strtotime($order['ORDER_PREFER_DATE'])) : '' ?></div>
<small class="text-muted"><?= !empty($order['ORDER_PREFER_TIME']) ? date('g:i A', strtotime($order['ORDER_PREFER_TIME'])) : '' ?></small>
</td>
<td><?= !empty($order['ORDER_DATE']) ? date('D, M j, Y', strtotime($order['ORDER_DATE'])) : '' ?></td>
<td class="fw-bold">RM <?= number_format($order['TOTAL_AMOUNT'] ?? 0, 2) ?></td>
<td><span class="badge rounded-pill <?= ($order['ORDER_PAYMENT_STATUS'] ?? '') == 'Paid' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= htmlspecialchars($order['ORDER_PAYMENT_STATUS'] ?? '') ?></span></td>
<td><span class="badge rounded-pill text-bg-primary"><?= htmlspecialchars($order['ORDER_STATUS'] ?? '') ?></span></td>
<td class="text-end">
<?php if (($order['ORDER_STATUS'] ?? '') == 'Completed' || ($order['ORDER_STATUS'] ?? '') == 'Cancelled'): ?>
<button class="btn btn-secondary btn-sm" disabled title="This order is finalized and cannot be updated."><i class="bi bi-lock-fill me-1"></i> Finalized</button>
<?php else: ?>
<button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#updateStatusModal<?= $order['ORDER_ID'] ?>"><i class="bi bi-pencil-square me-1"></i> Update</button>
<?php endif; ?>
</td>
</tr>

<!-- Update Status Modal -->
<div class="modal fade" id="updateStatusModal<?= $order['ORDER_ID'] ?>" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<form method="POST" action="view_orders.php">
<input type="hidden" name="order_id" value="<?= $order['ORDER_ID'] ?>">
<input type="hidden" name="search_query" value="<?= htmlspecialchars($search_query ?? '') ?>">
<?php if ($is_customer_filtered): ?>
<input type="hidden" name="cust_id" value="<?= htmlspecialchars($customer_id_filter ?? '') ?>">
<?php endif; ?>
<div class="modal-header">
<h5 class="modal-title">Update Order #<?= htmlspecialchars($order['ORDER_ID'] ?? '') ?></h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<div class="mb-3">
<label for="new_status" class="form-label">Order Status</label>
<select name="new_status" class="form-select">
<?php foreach ($valid_statuses as $status): ?>
<option value="<?= $status ?>" <?= ($order['ORDER_STATUS'] ?? '') == $status ? 'selected' : '' ?>><?= $status ?></option>
<?php endforeach; ?>
</select>
</div>
<div class="mb-3">
<label for="new_payment_status" class="form-label">Payment Status</label>
<select name="new_payment_status" class="form-select">
<option value="Unpaid" <?= ($order['ORDER_PAYMENT_STATUS'] ?? '') == 'Unpaid' ? 'selected' : '' ?>>Unpaid</option>
<option value="Paid" <?= ($order['ORDER_PAYMENT_STATUS'] ?? '') == 'Paid' ? 'selected' : '' ?>>Paid</option>
</select>
</div>
<div class="mb-3">
<label for="new_prefer_date" class="form-label">Preferred Pickup Date (Seller)</label>
<input type="date" name="new_prefer_date" class="form-control" value="<?= htmlspecialchars($order['ORDER_PREFER_DATE'] ?? '') ?>">
</div>
<div class="mb-3">
<label for="new_prefer_time" class="form-label">Preferred Pickup Time (Seller)</label>
<input type="time" name="new_prefer_time" class="form-control" value="<?= htmlspecialchars($order['ORDER_PREFER_TIME'] ?? '') ?>">
</div>
</div>
<div class="modal-footer">
<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
<button type="submit" name="update_status" class="btn btn-primary" style="background-color: var(--primary-dark); border-color: var(--primary-dark);">Save Changes</button>
</div>
</form>
</div>
</div>
</div>

<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="10" class="text-center p-5">
<i class="bi bi-search fs-1 text-muted"></i>
<p class="mt-2">No orders found matching your criteria.</p>
</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
