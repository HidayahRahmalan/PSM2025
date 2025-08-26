<?php
session_start();
require 'db_connection.php'; // Ensure you have your database connection here

// --- SECURITY CHECK ---
if (!isset($_SESSION['seller_id'])) { // Using a specific ID is more secure than a role string
    $_SESSION['message'] = "<div class='alert alert-danger'>You must be logged in as an owner to access this page.</div>";
    header("Location: login.php");
    exit();
}

$owner_name = isset($_SESSION['seller_name']) ? htmlspecialchars($_SESSION['seller_name']) : 'Owner';

// =================================================================
// BUSINESS RULES & THRESHOLDS (No Database Changes Needed)
// Adjust these values to match your business needs.
// =================================================================

// Rule 1: Production Threshold for finished goods.
// If a product's stock falls below this number, it appears in the "Production Queue".
$product_stock_threshold = 5; // e.g., Trigger alert when less than 5 packs are left.

// Rule 2: Reorder Point for raw ingredients.
// If an ingredient's stock falls below these values, it counts as a "Low Ingredient".
$low_ingredient_thresholds = [
    'g'   => 1000.0, // Alert if less than 1000g (1kg)
    'ml'  => 500.0,  // Alert if less than 500ml
    'pcs' => 20      // Alert if less than 20 pieces
];


// =================================================================
// PHP LOGIC TO FETCH ALL DASHBOARD DATA
// =================================================================

// --- 1. KPIs (Key Performance Indicators) ---

// KPI: Revenue Today (from orders that are paid and not cancelled)
$stmt = $conn->prepare("SELECT SUM(TOTAL_AMOUNT) as total FROM customer_order WHERE ORDER_PAYMENT_STATUS = 'Paid' AND ORDER_STATUS != 'Cancelled' AND DATE(ORDER_DATE) = CURDATE()");
$stmt->execute();
$revenue_today = $stmt->get_result()->fetch_assoc()['total'] ?? 0;

// KPI: Pending Orders (Orders that need action: 'Pending' or 'Confirmed')
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM customer_order WHERE ORDER_STATUS IN ('Pending', 'Confirmed')");
$stmt->execute();
$pending_orders_count = $stmt->get_result()->fetch_assoc()['count'];

// KPI: Products to Produce (Uses the hard-coded threshold from above)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products_sell WHERE PRODUCT_QTTY < ?");
$stmt->bind_param("i", $product_stock_threshold);
$stmt->execute();
$products_to_produce_count = $stmt->get_result()->fetch_assoc()['count'];

// KPI: Low Ingredients (Uses the hard-coded threshold array from above)
$stmt = $conn->prepare("
    SELECT COUNT(*) as count FROM item_ingredient WHERE
    (INVENTORY_UNIT = 'g' AND INVENTORY_STOCK < ?) OR
    (INVENTORY_UNIT = 'ml' AND INVENTORY_STOCK < ?) OR
    (INVENTORY_UNIT = 'pcs' AND INVENTORY_STOCK < ?)
");
$stmt->bind_param("ddi", $low_ingredient_thresholds['g'], $low_ingredient_thresholds['ml'], $low_ingredient_thresholds['pcs']);
$stmt->execute();
$low_ingredients_count = $stmt->get_result()->fetch_assoc()['count'];


// --- 2. ACTION CENTER DATA ---

// Action: Fetch recent orders needing action
$stmt = $conn->prepare("SELECT o.ORDER_ID, c.CUST_NAME, o.TOTAL_AMOUNT, o.ORDER_STATUS, o.ORDER_DATE FROM customer_order o JOIN customer c ON o.CUST_ID = c.CUST_ID WHERE o.ORDER_STATUS IN ('Pending', 'Confirmed') ORDER BY o.ORDER_DATE DESC LIMIT 5");
$stmt->execute();
$recent_orders = $stmt->get_result();

// Action: Fetch products that need to be produced
$stmt = $conn->prepare("SELECT PRODUCT_ID, PRODUCT_NAME, PRODUCT_QTTY FROM products_sell WHERE PRODUCT_QTTY < ? ORDER BY PRODUCT_QTTY ASC LIMIT 5");
$stmt->bind_param("i", $product_stock_threshold);
$stmt->execute();
$production_queue = $stmt->get_result();


// --- 3. BUSINESS INSIGHTS DATA ---

// Chart: Sales for the last 7 Days
$chart_labels = [];
$sales_per_day_map = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D, M j', strtotime($date));
    $sales_per_day_map[$date] = 0;
}
$stmt = $conn->prepare("SELECT DATE(ORDER_DATE) as sale_date, SUM(TOTAL_AMOUNT) as daily_total FROM customer_order WHERE ORDER_PAYMENT_STATUS = 'Paid' AND ORDER_STATUS != 'Cancelled' AND ORDER_DATE >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(ORDER_DATE)");
$stmt->execute();
$sales_result = $stmt->get_result();
if ($sales_result) {
    while($row = $sales_result->fetch_assoc()) {
        $sales_per_day_map[$row['sale_date']] = $row['daily_total'];
    }
}
$chart_data_sales = array_values($sales_per_day_map);

// Insight: Top 5 Selling Products (Last 30 days)
$stmt = $conn->prepare("
    SELECT p.PRODUCT_NAME, SUM(o.ORDER_QTTY) as total_sold
    FROM customer_order o JOIN products_sell p ON o.PRODUCT_ID = p.PRODUCT_ID
    WHERE o.ORDER_STATUS = 'Completed' AND o.ORDER_DATE >= CURDATE() - INTERVAL 30 DAY
    GROUP BY p.PRODUCT_NAME ORDER BY total_sold DESC LIMIT 5
");
$stmt->execute();
$top_products = $stmt->get_result();

// Insight: Ingredients Expiring Soon (Next 14 days)
$stmt = $conn->prepare("SELECT ITEM_NAME, ITEM_EXPIRED_DATE, INVENTORY_STOCK, INVENTORY_UNIT FROM item_ingredient WHERE ITEM_EXPIRED_DATE BETWEEN CURDATE() AND CURDATE() + INTERVAL 14 DAY ORDER BY ITEM_EXPIRED_DATE ASC");
$stmt->execute();
$expiring_ingredients = $stmt->get_result();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Owner Dashboard - RY's Tasty Creations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
    .kpi-card { border: none; border-radius: 0.75rem; color: white; box-shadow: 0 5px 20px rgba(0,0,0,0.07); transition: transform 0.2s, box-shadow 0.2s; }
    .kpi-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
    .kpi-card .kpi-icon { font-size: 2.5rem; opacity: 0.6; }
    .kpi-card .card-title { font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .kpi-card .kpi-value { font-size: 2.25rem; font-weight: 700; }
    .bg-c-blue { background: linear-gradient(45deg, #4099ff, #73b4ff); }
    .bg-c-green { background: linear-gradient(45deg, #2ed8b6, #59e0c5); }
    .bg-c-yellow { background: linear-gradient(45deg, #FFB64D, #ffcb80); }
    .bg-c-red { background: linear-gradient(45deg, #FF5370, #ff869a); }
    .content-card { background: white; border-radius: 0.75rem; box-shadow: 0 5px 20px rgba(0,0,0,0.05); padding: 1.5rem; margin-bottom: 2rem; }
    .list-group-item-action:hover { background-color: #f8f9fa; }
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
        <li class="nav-item"><a href="owner_dashboard.php" class="nav-link active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="nav-item"><a href="view_orders.php" class="nav-link"><i class="bi bi-receipt"></i> Order Management</a></li>
        <li class="nav-item"><a href="produce_stock.php" class="nav-link"><i class="bi bi-hammer"></i> Production</a></li>
         <li class="nav-item"><a href="production_ordered.php" class="nav-link "><i class="bi bi-bell-fill"></i> Production Auto</a></li>
        <li class="nav-item"><a href="product_management.php" class="nav-link"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a href="ingredient_management.php" class="nav-link"><i class="bi bi-droplet"></i> Ingredients</a></li>
        <li class="nav-item"><a href="sales_reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link"><i class="bi bi-people"></i> Customers</a></li>
    </ul>
  </div>

  <!-- Main Content -->
  <main class="main-content d-flex flex-column">
    
    <!-- Top Header Bar -->
    <header class="main-header">
      <h1 class="h3 mb-0 fw-bold text-dark">Dashboard</h1>
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

    <!-- Content Wrapper for scrolling and padding -->
    <div class="content-wrapper">
      
        <!-- Section 1: KPIs -->
        <div class="row g-4 mb-4">
          <div class="col-md-6 col-xl-3"><a href="sales_reports.php" class="text-decoration-none"><div class="kpi-card bg-c-green p-3"><div class="d-flex align-items-center"><div class="flex-grow-1"><h6 class="card-title text-white">Today's Revenue</h6><p class="kpi-value mb-0">RM <?= number_format($revenue_today, 2); ?></p></div><i class="bi bi-cash-coin kpi-icon"></i></div></div></a></div>
          <div class="col-md-6 col-xl-3"><a href="view_orders.php" class="text-decoration-none"><div class="kpi-card bg-c-blue p-3"><div class="d-flex align-items-center"><div class="flex-grow-1"><h6 class="card-title text-white">Pending Orders</h6><p class="kpi-value mb-0"><?= $pending_orders_count; ?></p></div><i class="bi bi-cart3 kpi-icon"></i></div></div></a></div>
          <div class="col-md-6 col-xl-3"><a href="produce_stock.php" class="text-decoration-none"><div class="kpi-card bg-c-yellow p-3"><div class="d-flex align-items-center"><div class="flex-grow-1"><h6 class="card-title text-dark">To Be Produced</h6><p class="kpi-value mb-0"><?= $products_to_produce_count; ?></p></div><i class="bi bi-hammer kpi-icon"></i></div></div></a></div>
          <div class="col-md-6 col-xl-3"><a href="ingredient_management.php" class="text-decoration-none"><div class="kpi-card bg-c-red p-3"><div class="d-flex align-items-center"><div class="flex-grow-1"><h6 class="card-title text-white">Low Ingredients</h6><p class="kpi-value mb-0"><?= $low_ingredients_count; ?></p></div><i class="bi bi-droplet kpi-icon"></i></div></div></a></div>
        </div>

        <!-- Section 2: Action Center -->
        <div class="row g-4">
          <div class="col-lg-7">
            <div class="content-card h-100">
              <h5 class="fw-bold mb-3"><i class="bi bi-receipt text-primary me-2"></i>New Orders Awaiting Confirmation</h5>
              <div class="list-group list-group-flush">
                
                <?php if ($recent_orders->num_rows > 0): ?>
                  <?php foreach ($recent_orders as $order): ?>
                  <a href="view_orders.php?.php?id=<?= $order['ORDER_ID']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                      <span class="fw-bold">#<?= $order['ORDER_ID']; ?> - <?= htmlspecialchars($order['CUST_NAME']); ?></span>
                      <small class="d-block text-muted"><?= date('M j, Y g:i A', strtotime($order['ORDER_DATE'])); ?></small>
                    </div>
                    <div class="text-end">
                      <span class="fw-bold d-block">RM <?= number_format($order['TOTAL_AMOUNT'], 2); ?></span>
                      <span class="badge rounded-pill text-bg-primary"><?= htmlspecialchars($order['ORDER_STATUS']); ?></span>
                    </div>
                  </a>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="text-center p-4 text-muted"><i class="bi bi-check-circle fs-3 d-block mb-2"></i> All caught up! No pending orders.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <div class="col-lg-5">
            <div class="content-card h-100">
              <h5 class="fw-bold mb-3"><i class="bi bi-hammer text-warning me-2"></i>Production Needed</h5>
              <div class="list-group list-group-flush">
                <?php if ($production_queue->num_rows > 0): ?>
                  <?php foreach ($production_queue as $product): ?>
                  <a href="produce_stock.php?product_id=<?= $product['PRODUCT_ID']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <span><?= htmlspecialchars($product['PRODUCT_NAME']); ?></span>
                    <span class="badge text-bg-warning">In Stock: <?= $product['PRODUCT_QTTY']; ?> (Min: <?= $product_stock_threshold; ?>)</span>
                  </a>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="text-center p-4 text-muted"><i class="bi bi-emoji-sunglasses fs-3 d-block mb-2"></i> All product stocks are healthy.</div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 3: Business Insights -->
        <div class="row g-4 mt-1">
          <div class="col-lg-7">
              <div class="content-card">
                  <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart-line text-success me-2"></i>Weekly Sales Performance</h5>
                  <div style="height: 300px;"><canvas id="salesChart"></canvas></div>
              </div>
          </div>
          <div class="col-lg-5">
              <div class="content-card">
                  <h5 class="fw-bold mb-3"><i class="bi bi-trophy text-info me-2"></i>Top Selling Products (30 Days)</h5>
                  <ul class="list-group list-group-flush">
                  <?php if ($top_products->num_rows > 0): ?>
                      <?php foreach($top_products as $index => $product): ?>
                      <li class="list-group-item d-flex justify-content-between align-items-center">
                          <div><span class="fw-bold me-2"><?= $index + 1; ?>.</span> <?= htmlspecialchars($product['PRODUCT_NAME']); ?></div>
                          <span class="badge text-bg-info rounded-pill"><?= $product['total_sold']; ?> sold</span>
                      </li>
                      <?php endforeach; ?>
                  <?php else: ?>
                      <li class="list-group-item text-muted text-center">Not enough sales data yet.</li>
                  <?php endif; ?>
                  </ul>
              </div>
          </div>
        </div>

        <!-- Section 4: Alerts -->
        <div class="row g-4 mt-1">
          <div class="col-12">
              <div class="content-card">
                  <h5 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>Inventory Alerts: Expiring Soon</h5>
                  <?php if ($expiring_ingredients->num_rows > 0): ?>
                  <div class="table-responsive">
                      <table class="table table-hover table-sm align-middle">
                          <thead><tr><th>Ingredient Name</th><th>Expires On</th><th>Stock Remaining</th></tr></thead>
                          <tbody>
                          <?php foreach($expiring_ingredients as $item): 
                              $expiry_date = new DateTime($item['ITEM_EXPIRED_DATE']);
                              $today = new DateTime();
                              $diff = $today->diff($expiry_date)->days;
                              if ($expiry_date < $today) { $diff = 0; }
                              $alert_class = $diff <= 3 ? 'table-danger' : 'table-warning';
                          ?>
                          <tr class="<?= $alert_class ?>">
                              <td><?= htmlspecialchars($item['ITEM_NAME']); ?></td>
                              <td><?= $expiry_date->format('M j, Y'); ?> <span class="fw-bold">(in <?= $diff ?> days)</span></td>
                              <td><?= htmlspecialchars($item['INVENTORY_STOCK']) . ' ' . htmlspecialchars($item['INVENTORY_UNIT']); ?></td>
                          </tr>
                          <?php endforeach; ?>
                          </tbody>
                      </table>
                  </div>
                  <?php else: ?>
                      <div class="text-center p-3 text-muted"><i class="bi bi-shield-check fs-3 d-block mb-2"></i> No ingredients expiring in the next 14 days. Great!</div>
                  <?php endif; ?>
              </div>
          </div>
        </div>

    </div>
  </main>
  
  <script>
    // Sales Chart
    const ctxSales = document.getElementById('salesChart').getContext('2d');
    new Chart(ctxSales, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Sales',
                data: <?= json_encode($chart_data_sales); ?>,
                backgroundColor: 'rgba(75, 28, 28, 0.7)',
                borderColor: 'rgb(75, 28, 28)',
                borderWidth: 1, borderRadius: 5, barPercentage: 0.6,
            }]
        },
        options: {
            scales: { y: { beginAtZero: true, ticks: { callback: value => 'RM ' + value } } },
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: ctx => `RM ${ctx.parsed.y.toFixed(2)}` } }
            }
        }
    });
  </script>
</body>
</html>