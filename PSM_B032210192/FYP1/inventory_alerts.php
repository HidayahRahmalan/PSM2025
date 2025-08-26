<?php
session_start();
require 'db_connection.php';

// --- SECURITY CHECK ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'seller') {
    header("Location: login.php");
    exit();
}

// --- CONFIGURABLE ALERT THRESHOLDS ---
$low_product_threshold = 10;      // Alert if finished product stock is below 10 packs.
$low_ingredient_threshold_g = 500; // Alert if ingredient stock (in grams) is below 500g.
$low_ingredient_threshold_ml = 500;// Alert if ingredient stock (in ml) is below 500ml.
$low_ingredient_threshold_pcs = 10;// Alert if ingredient stock (in pcs) is below 10 pieces.
$expiry_threshold_days = 14;     // Alert for ingredients expiring in the next 14 days.

// --- DATA FETCHING LOGIC ---

// 1. Get Low Stock Ingredients (based on fixed thresholds)
$low_ingredients_stmt = $conn->prepare("
    SELECT ITEM_ID, ITEM_NAME, INVENTORY_STOCK, INVENTORY_UNIT 
    FROM item_ingredient 
    WHERE 
        (INVENTORY_UNIT = 'g' AND INVENTORY_STOCK < ?) OR
        (INVENTORY_UNIT = 'ml' AND INVENTORY_STOCK < ?) OR
        (INVENTORY_UNIT = 'pcs' AND INVENTORY_STOCK < ?)
    ORDER BY ITEM_NAME
");
$low_ingredients_stmt->bind_param("iii", $low_ingredient_threshold_g, $low_ingredient_threshold_ml, $low_ingredient_threshold_pcs);
$low_ingredients_stmt->execute();
$low_ingredients = $low_ingredients_stmt->get_result();

// 2. Get Ingredients Expiring Soon
$expiring_stmt = $conn->prepare("
    SELECT ITEM_ID, ITEM_NAME, ITEM_EXPIRED_DATE, INVENTORY_STOCK, INVENTORY_UNIT 
    FROM item_ingredient 
    WHERE ITEM_EXPIRED_DATE BETWEEN CURDATE() AND CURDATE() + INTERVAL ? DAY 
    ORDER BY ITEM_EXPIRED_DATE ASC
");
$expiring_stmt->bind_param("i", $expiry_threshold_days);
$expiring_stmt->execute();
$expiring_ingredients = $expiring_stmt->get_result();

// 3. Get Low Stock Finished Products
$low_products_stmt = $conn->prepare("SELECT PRODUCT_ID, PRODUCT_NAME, PRODUCT_QTTY FROM products_sell WHERE PRODUCT_QTTY < ? ORDER BY PRODUCT_QTTY ASC");
$low_products_stmt->bind_param("i", $low_product_threshold);
$low_products_stmt->execute();
$low_products = $low_products_stmt->get_result();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory Alerts - RY's Tasty Creations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --primary-dark: #4b1c1c; --primary-accent: #ffc107; }
    body { background-color: #f8f9fa; display: flex; min-height: 100vh; }
    .sidebar { width: 380px; background-color: var(--primary-dark); color: white; flex-shrink: 0; }
    .sidebar .nav-link { color: #e9ecef; padding: 0.8rem 1.5rem; font-size: 1.05rem; border-left: 4px solid transparent; }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); color: white; }
    .sidebar .nav-link.active { border-left-color: var(--primary-accent); }
    .sidebar .nav-link .bi { margin-right: 0.75rem; }
    .sidebar-header { padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .main-content { flex-grow: 1; padding: 2.5rem; overflow-y: auto; }
    .alert-card { background: white; border-radius: 0.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 2rem; }
    .alert-card .card-header { font-size: 1.2rem; font-weight: 600; }
  </style>
</head>
<body>
  <!-- Sidebar Navigation -->
  <div class="sidebar d-flex flex-column p-0">
    <div class="sidebar-header text-center"><h4 class="fw-bold mb-1">RY's Tasty Creations</h4><p class="text-white-50 mb-0">Owner Panel</p></div>
    <ul class="nav flex-column my-4">
        <li class="nav-item"><a href="owner_dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
        <li class="nav-item"><a href="view_orders.php" class="nav-link"><i class="bi bi-list-check"></i> Order Management</a></li>
        <li class="nav-item"><a href="product_management.php" class="nav-link"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a href="produce_stock.php" class="nav-link active"><i class="bi bi-hammer"></i> Produce Stock</a></li>
        <li class="nav-item"><a href="ingredient_management.php" class="nav-link"><i class="bi bi-droplet"></i> Ingredients</a></li>
        <li class="nav-item"><a href="inventory_alerts.php" class="nav-link"><i class="bi bi-clipboard-data"></i> Inventory Alerts</a></li>
        <li class="nav-item"><a href="sales_reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Sales Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link"><i class="bi bi-people"></i> Customers</a></li>
    </ul>
    <div class="mt-auto p-3"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
  </div>

  <!-- Main Content -->
  <main class="main-content">
    <div class="container-fluid">
      <div class="mb-4">
        <h1 class="h2 fw-bold">Inventory Alerts</h1>
        <p class="text-muted">Manage stock levels and prevent waste. Items shown here require your attention.</p>
      </div>
      <div class="row">
        <div class="col-lg-6">
          <div class="alert-card">
            <div class="card-header bg-warning-subtle"><i class="bi bi-box-seam me-2"></i> Low Stock Ingredients</div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                  <thead><tr><th>Ingredient</th><th class="text-end">Stock Left</th><th></th></tr></thead>
                  <tbody>
                    <?php if ($low_ingredients->num_rows > 0): ?>
                      <?php while($item = $low_ingredients->fetch_assoc()): ?>
                        <tr>
                          <td><?= htmlspecialchars($item['ITEM_NAME']); ?></td>
                          <td class="text-end fw-bold text-warning"><?= number_format($item['INVENTORY_STOCK'], 2) . ' ' . htmlspecialchars($item['INVENTORY_UNIT']); ?></td>
                          <td class="text-end"><a href="ingredient_management.php" class="btn btn-sm btn-outline-secondary">Restock</a></td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr><td colspan="3" class="text-center text-muted p-4"><i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>All ingredient stock levels are good!</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          <div class="alert-card">
            <div class="card-header bg-danger-subtle"><i class="bi bi-hourglass-split me-2"></i> Ingredients Expiring Soon</div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                  <thead><tr><th>Ingredient</th><th class="text-center">Expires On</th><th class="text-end">Days Left</th></tr></thead>
                  <tbody>
                    <?php if ($expiring_ingredients->num_rows > 0): $today = new DateTime(); ?>
                      <?php while($item = $expiring_ingredients->fetch_assoc()): 
                        $expiry_date = new DateTime($item['ITEM_EXPIRED_DATE']);
                        $days_left = (int)$today->diff($expiry_date)->format('%r%a');
                      ?>
                        <tr>
                          <td><?= htmlspecialchars($item['ITEM_NAME']); ?></td>
                          <td class="text-center"><?= $expiry_date->format('d M, Y'); ?></td>
                          <td class="text-end fw-bold text-danger"><?= $days_left <= 0 ? 'Expired' : $days_left . ' Day(s)'; ?></td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr><td colspan="3" class="text-center text-muted p-4"><i class="bi bi-calendar2-check-fill text-success fs-3 d-block mb-2"></i>No ingredients expiring soon.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6">
          <div class="alert-card">
            <div class="card-header bg-info-subtle"><i class="bi bi-bag-dash me-2"></i> Low Stock Products</div>
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                  <thead><tr><th>Product Name</th><th class="text-end">Packs in Stock</th><th></th></tr></thead>
                  <tbody>
                    <?php if ($low_products->num_rows > 0): ?>
                      <?php while($product = $low_products->fetch_assoc()): ?>
                        <tr>
                          <td><?= htmlspecialchars($product['PRODUCT_NAME']); ?></td>
                          <td class="text-end fw-bold text-info"><?= htmlspecialchars($product['PRODUCT_QTTY']); ?></td>
                          <td class="text-end"><a href="produce_stock.php?product_id=<?= htmlspecialchars($product['PRODUCT_ID']) ?>" class="btn btn-sm btn-outline-secondary">Produce</a></td>
                        </tr>
                      <?php endwhile; ?>
                    <?php else: ?>
                      <tr><td colspan="3" class="text-center text-muted p-4"><i class="bi bi-emoji-sunglasses-fill text-success fs-3 d-block mb-2"></i>All products are well-stocked!</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>