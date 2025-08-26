<?php
session_start();
require 'db_connection.php';

// Redirect if not logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];

// --- Get user info & cart count for navbar ---
$stmt_cust = $conn->prepare("SELECT CUST_NAME FROM customer WHERE CUST_ID = ?");
$stmt_cust->bind_param("s", $customer_id);
$stmt_cust->execute();
$customer = $stmt_cust->get_result()->fetch_assoc();
$cust_name = $customer ? $customer['CUST_NAME'] : 'Customer';
$cart_item_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

// --- Get summary data ---
$stmt_summary = $conn->prepare("SELECT COUNT(*) as total_orders, SUM(TOTAL_AMOUNT) as grand_total FROM customer_order WHERE CUST_ID = ?");
$stmt_summary->bind_param("s", $customer_id);
$stmt_summary->execute();
$summary_result = $stmt_summary->get_result()->fetch_assoc();

$total_orders = $summary_result['total_orders'] ?? 0;
$grand_total = $summary_result['grand_total'] ?? 0;

// --- Search and Sort Logic ---
$sort_option = $_GET['sort'] ?? 'desc';
$sort_order = ($sort_option == 'asc') ? 'ASC' : 'DESC';
$search_keyword = trim($_GET['search'] ?? '');

// --- Build the SQL Query ---
$sql = "SELECT co.ORDER_ID, co.PRODUCT_ID, ps.PRODUCT_NAME, co.ORDER_QTTY, co.TOTAL_AMOUNT, co.ORDER_DATE, co.ORDER_WANTED, co.ORDER_PAYMENT_STATUS, co.ORDER_STATUS 
        FROM customer_order co 
        JOIN products_sell ps ON co.PRODUCT_ID = ps.PRODUCT_ID 
        WHERE co.CUST_ID = ?";
if (!empty($search_keyword)) {
    $sql .= " AND ps.PRODUCT_NAME LIKE ?";
}
$sql .= " ORDER BY co.ORDER_ID $sort_order";

$stmt = $conn->prepare($sql);
if (!empty($search_keyword)) {
    $like_keyword = '%' . $search_keyword . '%';
    $stmt->bind_param("ss", $customer_id, $like_keyword);
} else {
    $stmt->bind_param("s", $customer_id);
}
$stmt->execute();
$order_result = $stmt->get_result();

function getPaymentStatusBadge($status) {
    return match (strtolower(trim($status))) {
        'paid' => 'bg-success',
        'unpaid', 'pending' => 'bg-warning text-dark',
        'cancelled', 'failed' => 'bg-danger',
        default => 'bg-secondary'
    };
}

function getFulfillmentStatusBadge($status) {
    return match (strtolower(trim($status))) {
        'pending' => 'bg-info text-dark',
        'processing' => 'bg-primary',
        'ready for pickup', 'completed' => 'bg-success',
        'cancelled' => 'bg-danger',
        default => 'bg-secondary'
    };
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Orders - RY's Tasty Creation</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
    .navbar { box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
    .order-table th { font-weight: 600; white-space: nowrap; }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand" href="home_customer.php" style="color: #b33c86; font-weight: bold;">RY's Tasty Creation</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="home_customer.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="product.php">Products</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="cart.php">
                        <i class="bi bi-cart-fill"></i> Cart
                        <?php if ($cart_item_count > 0): ?><span class="badge bg-danger rounded-pill"><?= $cart_item_count ?></span><?php endif; ?>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" href="#" role="button" data-bs-toggle="dropdown" aria-current="page">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($cust_name) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item active" href="customerorder.php">My Orders</a></li>
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container py-5">
    <h1 class="mb-4">My Order History</h1>

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="p-3 bg-primary bg-opacity-10 rounded-3 me-3">
                        <i class="bi bi-wallet2 fs-2 text-primary"></i>
                    </div>
                    <div>
                        <h6 class="card-subtitle mb-1 text-muted">Total Spent</h6>
                        <h4 class="card-title mb-0 fw-bold">RM <?= number_format($grand_total, 2) ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="p-3 bg-success bg-opacity-10 rounded-3 me-3">
                        <i class="bi bi-box-seam fs-2 text-success"></i>
                    </div>
                    <div>
                        <h6 class="card-subtitle mb-1 text-muted">Total Orders</h6>
                        <h4 class="card-title mb-0 fw-bold"><?= $total_orders ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <form method="GET" action="customerorder.php" class="row g-3 align-items-center">
                <div class="col-md-6 col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by product name..." value="<?= htmlspecialchars($search_keyword) ?>">
                    </div>
                </div>
                <div class="col-md-3 col-lg-3">
                    <select name="sort" class="form-select">
                        <option value="desc" <?= ($sort_option == 'desc') ? 'selected' : '' ?>>Newest First</option>
                        <option value="asc" <?= ($sort_option == 'asc') ? 'selected' : '' ?>>Oldest First</option>
                    </select>
                </div>
                <div class="col-md-3 col-lg-4 d-grid d-md-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                    <a href="customerorder.php" class="btn btn-outline-secondary flex-grow-1">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle order-table mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" class="ps-3">Order</th>
                        <th scope="col">Product</th>
                        <th scope="col" class="text-end">Total</th>
                        <th scope="col">Date</th>
                        <th scope="col" class="text-center">Payment</th>
                        <th scope="col" class="text-center">Status</th>
                        <th scope="col" class="text-center pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($order_result->num_rows > 0): ?>
                        <?php while ($order = $order_result->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-3 fw-bold">#<?= htmlspecialchars($order['ORDER_ID']) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($order['PRODUCT_NAME']) ?></div>
                                    <small class="text-muted">Qty: <?= htmlspecialchars($order['ORDER_QTTY']) ?></small>
                                </td>
                                <td class="text-end fw-semibold">RM <?= number_format($order['TOTAL_AMOUNT'], 2) ?></td>
                                <td>
                                    <div><?= date("M d, Y", strtotime($order['ORDER_DATE'])) ?></div>
                                    <small class="text-muted">Pickup: <?= date("M d, Y", strtotime($order['ORDER_WANTED'])) ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill <?= getPaymentStatusBadge($order['ORDER_PAYMENT_STATUS']) ?>">
                                        <?= htmlspecialchars(ucfirst($order['ORDER_PAYMENT_STATUS'])) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge rounded-pill <?= getFulfillmentStatusBadge($order['ORDER_STATUS']) ?>">
                                        <?= htmlspecialchars(ucfirst($order['ORDER_STATUS'])) ?>
                                    </span>
                                </td>
                                <td class="text-center pe-3">
                                    <a href="order_detail.php?order_id=<?= $order['ORDER_ID'] ?>" class="btn btn-sm btn-outline-secondary">Details</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center p-5">No Orders Found</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<footer class="text-center py-4 text-muted border-top mt-5">
    © <?= date('Y') ?> RY's Tasty Creation
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
