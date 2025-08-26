<?php
session_start();
require 'db_connection.php';

// --- SECURITY CHECK & SESSION SETUP ---
if (!isset($_SESSION['seller_id'])) { // Using seller_id is more robust
    $_SESSION['error'] = "You must be logged in as an owner to access this page.";
    header("Location: login.php");
    exit();
}
$owner_name = isset($_SESSION['seller_name']) ? htmlspecialchars($_SESSION['seller_name']) : 'Owner';

// --- DATE FILTERING LOGIC ---
$start_date_input = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date_input = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

try {
    $dt_start = new DateTime($start_date_input);
    $dt_end = new DateTime($end_date_input);
} catch (Exception $e) {
    // Fallback to default if dates are invalid
    $dt_start = new DateTime(date('Y-m-01'));
    $dt_end = new DateTime(date('Y-m-t'));
}
$sql_start_date = $dt_start->format('Y-m-d');
$sql_end_date = $dt_end->format('Y-m-d');

// --- PREVIOUS PERIOD CALCULATION FOR COMPARISON ---
$interval = $dt_start->diff($dt_end);
$days_diff = $interval->days + 1;
$prev_dt_end = clone $dt_start;
$prev_dt_end->modify('-1 day');
$prev_dt_start = clone $prev_dt_end;
$prev_dt_start->modify('-' . ($days_diff - 1) . ' days');
$sql_prev_start_date = $prev_dt_start->format('Y-m-d');
$sql_prev_end_date = $prev_dt_end->format('Y-m-d');

// --- HELPER FUNCTION FOR KPI QUERIES ---
function getKpiData($conn, $startDate, $endDate) {
    $sql = "SELECT 
                SUM(TOTAL_AMOUNT) as total_revenue, 
                COUNT(DISTINCT ORDER_ID) as total_orders
            FROM customer_order
            WHERE ORDER_STATUS = 'Completed' 
              AND ORDER_PAYMENT_STATUS = 'Paid'
              AND DATE(ORDER_DATE) BETWEEN ? AND ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $result['avg_order_value'] = ($result['total_orders'] > 0) ? $result['total_revenue'] / $result['total_orders'] : 0;
    return $result;
}

// --- DATA FETCHING ---

// 1. KPI DATA (Current and Previous Period)
$current_kpi = getKpiData($conn, $sql_start_date, $sql_end_date);
$previous_kpi = getKpiData($conn, $sql_prev_start_date, $sql_prev_end_date);

// 2. CHART DATA
$stmt_chart_db = $conn->prepare("SELECT DATE(ORDER_DATE) as sale_date, SUM(TOTAL_AMOUNT) as daily_total FROM customer_order WHERE ORDER_STATUS = 'Completed' AND ORDER_PAYMENT_STATUS = 'Paid' AND DATE(ORDER_DATE) BETWEEN ? AND ? GROUP BY DATE(ORDER_DATE) ORDER BY sale_date ASC");
$stmt_chart_db->bind_param("ss", $sql_start_date, $sql_end_date);
$stmt_chart_db->execute();
$chart_db_result = $stmt_chart_db->get_result();
$sales_data_from_db = [];
while ($row = $chart_db_result->fetch_assoc()) {
    $sales_data_from_db[$row['sale_date']] = (float)$row['daily_total'];
}
$chart_labels = [];
$chart_data = [];
$current_date_loop = clone $dt_start;
$end_date_loop_limit = clone $dt_end;
while ($current_date_loop <= $end_date_loop_limit) {
    $date_key = $current_date_loop->format('Y-m-d');
    $chart_labels[] = $current_date_loop->format('M j');
    $chart_data[] = $sales_data_from_db[$date_key] ?? 0;
    $current_date_loop->modify('+1 day');
}

// 3. TOP SELLING PRODUCTS
$stmt_top_products = $conn->prepare("SELECT p.PRODUCT_NAME, SUM(co.ORDER_QTTY) as total_sold, SUM(co.TOTAL_AMOUNT) as product_revenue FROM customer_order co JOIN products_sell p ON co.PRODUCT_ID = p.PRODUCT_ID WHERE co.ORDER_STATUS = 'Completed' AND co.ORDER_PAYMENT_STATUS = 'Paid' AND DATE(co.ORDER_DATE) BETWEEN ? AND ? GROUP BY co.PRODUCT_ID, p.PRODUCT_NAME ORDER BY product_revenue DESC LIMIT 5");
$stmt_top_products->bind_param("ss", $sql_start_date, $sql_end_date);
$stmt_top_products->execute();
$top_products_result = $stmt_top_products->get_result();

// 4. DETAILED SALES HISTORY (with PAGINATION)
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 15;
$offset = ($page - 1) * $records_per_page;

$stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM customer_order co WHERE co.ORDER_STATUS = 'Completed' AND co.ORDER_PAYMENT_STATUS = 'Paid' AND DATE(co.ORDER_DATE) BETWEEN ? AND ?");
$stmt_count->bind_param("ss", $sql_start_date, $sql_end_date);
$stmt_count->execute();
$total_records = $stmt_count->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $records_per_page);

$stmt_history = $conn->prepare("SELECT co.ORDER_ID, c.CUST_NAME, co.ORDER_DATE, co.TOTAL_AMOUNT FROM customer_order co JOIN customer c ON co.CUST_ID = c.CUST_ID WHERE co.ORDER_STATUS = 'Completed' AND co.ORDER_PAYMENT_STATUS = 'Paid' AND DATE(co.ORDER_DATE) BETWEEN ? AND ? ORDER BY co.ORDER_DATE DESC LIMIT ? OFFSET ?");
$stmt_history->bind_param("ssii", $sql_start_date, $sql_end_date, $records_per_page, $offset);
$stmt_history->execute();
$history_result = $stmt_history->get_result();

// --- CSV EXPORT LOGIC ---
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $stmt_export = $conn->prepare("SELECT co.ORDER_ID, c.CUST_NAME, co.ORDER_DATE, p.PRODUCT_NAME, co.ORDER_QTTY, co.TOTAL_AMOUNT, co.ORDER_PAYMENT_STATUS FROM customer_order co JOIN customer c ON co.CUST_ID = c.CUST_ID JOIN products_sell p ON co.PRODUCT_ID = p.PRODUCT_ID WHERE co.ORDER_STATUS = 'Completed' AND co.ORDER_PAYMENT_STATUS = 'Paid' AND DATE(co.ORDER_DATE) BETWEEN ? AND ? ORDER BY co.ORDER_DATE DESC");
    $stmt_export->bind_param("ss", $sql_start_date, $sql_end_date);
    $stmt_export->execute();
    $export_result = $stmt_export->get_result();
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sales_report_'.$sql_start_date.'_to_'.$sql_end_date.'.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Customer Name', 'Order Date', 'Product Name', 'Quantity', 'Total Amount (RM)', 'Payment Status']);
    
    while ($row = $export_result->fetch_assoc()) {
        fputcsv($output, [$row['ORDER_ID'], $row['CUST_NAME'], date('Y-m-d H:i:s', strtotime($row['ORDER_DATE'])), $row['PRODUCT_NAME'], $row['ORDER_QTTY'], number_format($row['TOTAL_AMOUNT'], 2), $row['ORDER_PAYMENT_STATUS']]);
    }
    fclose($output);
    exit();
}

// --- HELPER FUNCTION FOR DISPLAYING KPI CHANGES ---
function displayChange($current, $previous) {
    if ($previous == 0) { return $current > 0 ? '<span class="text-success small"><i class="bi bi-arrow-up"></i> New Activity</span>' : ''; }
    $change = (($current - $previous) / $previous) * 100;
    if ($change > 0) { return sprintf('<span class="text-success small"><i class="bi bi-arrow-up"></i> %.1f%%</span>', $change); } 
    elseif ($change < 0) { return sprintf('<span class="text-danger small"><i class="bi bi-arrow-down"></i> %.1f%%</span>', abs($change)); } 
    else { return '<span class="text-muted small">No Change</span>'; }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales Reports - RY's Tasty Creations</title>
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
    .report-card { background: white; border-radius: 0.75rem; box-shadow: 0 4px 25px rgba(0,0,0,0.05); border: 1px solid #e9ecef; }
    .kpi-card { padding: 1.5rem; }
    .kpi-card .kpi-icon { font-size: 2rem; color: var(--primary-dark); opacity: 0.7; }
    .kpi-card .kpi-value { font-size: 2.2rem; font-weight: 700; color: #343a40; }
    .kpi-card .kpi-label { font-size: 0.9rem; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; }
    .kpi-card .kpi-change { margin-top: 0.5rem; }
    .chart-container { position: relative; height: 380px; width: 100%; }
    .progress-bar { background-color: var(--primary-dark); }
    .pagination .page-link { color: var(--primary-dark); }
    .pagination .page-item.active .page-link { background-color: var(--primary-dark); border-color: var(--primary-dark); }
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
        <li class="nav-item"><a href="sales_reports.php" class="nav-link active"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link"><i class="bi bi-people"></i> Customers</a></li>
    </ul>
  </div>

  <!-- Main Content -->
  <main class="main-content d-flex flex-column">
    
    <!-- Added Top Header Bar for consistency -->
    <header class="main-header">
      <h1 class="h3 mb-0 fw-bold text-dark">Sales Reports</h1>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <p class="text-muted mb-0">An overview of your business performance.</p>
            <a href="?<?= http_build_query(array_merge($_GET, ['action' => 'export'])) ?>" class="btn btn-outline-success"><i class="bi bi-download me-2"></i>Export as CSV</a>
        </div>

      <!-- Filter Form -->
      <div class="report-card p-3 mb-4">
        <form method="GET" class="row g-3 align-items-center">
            <div class="col-md-3"><label for="start_date" class="form-label fw-bold">Start Date</label><input type="date" id="start_date" name="start_date" class="form-control" value="<?= htmlspecialchars($dt_start->format('Y-m-d')) ?>"></div>
            <div class="col-md-3"><label for="end_date" class="form-label fw-bold">End Date</label><input type="date" id="end_date" name="end_date" class="form-control" value="<?= htmlspecialchars($dt_end->format('Y-m-d')) ?>"></div>
            <div class="col-md-auto align-self-end"><button type="submit" class="btn btn-primary" style="background-color:var(--primary-dark);border-color:var(--primary-dark);"><i class="bi bi-funnel-fill me-1"></i>Filter</button></div>
            <div class="col-md-auto align-self-end">
                <div class="btn-group">
                    <a href="?start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary">Today</a>
                    <a href="?start_date=<?= date('Y-m-d', strtotime('monday this week')) ?>&end_date=<?= date('Y-m-d', strtotime('sunday this week')) ?>" class="btn btn-outline-secondary">This Week</a>
                    <a href="?start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-t') ?>" class="btn btn-outline-secondary">This Month</a>
                </div>
            </div>
        </form>
      </div>

      <!-- KPI Cards -->
      <div class="row g-4 mb-4">
          <div class="col-md-4"><div class="report-card kpi-card"><div class="d-flex justify-content-between align-items-start"><div class="kpi-icon"><i class="bi bi-cash-stack"></i></div><div><div class="kpi-value text-end">RM <?= number_format($current_kpi['total_revenue'] ?? 0, 2) ?></div><div class="kpi-label text-end">Total Revenue</div><div class="kpi-change text-end"><?= displayChange($current_kpi['total_revenue'], $previous_kpi['total_revenue']) ?></div></div></div></div></div>
          <div class="col-md-4"><div class="report-card kpi-card"><div class="d-flex justify-content-between align-items-start"><div class="kpi-icon"><i class="bi bi-receipt-cutoff"></i></div><div><div class="kpi-value text-end"><?= $current_kpi['total_orders'] ?? 0 ?></div><div class="kpi-label text-end">Completed Orders</div><div class="kpi-change text-end"><?= displayChange($current_kpi['total_orders'], $previous_kpi['total_orders']) ?></div></div></div></div></div>
          <div class="col-md-4"><div class="report-card kpi-card"><div class="d-flex justify-content-between align-items-start"><div class="kpi-icon"><i class="bi bi-graph-up-arrow"></i></div><div><div class="kpi-value text-end">RM <?= number_format($current_kpi['avg_order_value'] ?? 0, 2) ?></div><div class="kpi-label text-end">Avg. Order Value</div><div class="kpi-change text-end"><?= displayChange($current_kpi['avg_order_value'], $previous_kpi['avg_order_value']) ?></div></div></div></div></div>
      </div>

      <!-- Chart and Top Products -->
      <div class="row g-4 mb-4">
          <div class="col-lg-7">
              <div class="report-card p-4 h-100">
                <h5 class="mb-3 fw-bold">Sales Over Time</h5>
                <div class="chart-container"><canvas id="salesChart"></canvas></div>
              </div>
          </div>
          <div class="col-lg-5">
              <div class="report-card p-4 h-100">
                  <h5 class="mb-4 fw-bold">Top Selling Products</h5>
                  <?php if($top_products_result->num_rows > 0): $top_products_data = $top_products_result->fetch_all(MYSQLI_ASSOC); $max_revenue = $top_products_data[0]['product_revenue']; ?>
                      <?php foreach($top_products_data as $product): $progress_width = ($max_revenue > 0) ? ($product['product_revenue'] / $max_revenue) * 100 : 0; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="fw-semibold small"><?= htmlspecialchars($product['PRODUCT_NAME']) ?></span>
                                <span class="fw-bold small">RM <?= number_format($product['product_revenue'], 2) ?></span>
                            </div>
                            <div class="progress" style="height: 6px;"><div class="progress-bar" role="progressbar" style="width: <?= $progress_width ?>%;" aria-valuenow="<?= $progress_width ?>" aria-valuemin="0" aria-valuemax="100"></div></div>
                            <small class="text-muted"><?= htmlspecialchars($product['total_sold']) ?> units sold</small>
                        </div>
                      <?php endforeach; ?>
                  <?php else: ?>
                      <div class="text-center p-5 text-muted"><i class="bi bi-x-circle d-block fs-3 mb-2"></i>No product sales in this period.</div>
                  <?php endif; ?>
              </div>
          </div>
      </div>

      <!-- Detailed History Table -->
      <div class="report-card p-4">
          <h5 class="mb-3 fw-bold">Detailed Sales History</h5>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Order ID</th><th>Customer</th><th>Date</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    <?php if($history_result->num_rows > 0): ?>
                        <?php while($sale = $history_result->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold">#<?= htmlspecialchars($sale['ORDER_ID']) ?></td>
                                <td><?= htmlspecialchars($sale['CUST_NAME']) ?></td>
                                <td><?= date('M j, Y, g:i A', strtotime($sale['ORDER_DATE'])) ?></td>
                                <td class="text-end fw-semibold">RM <?= number_format($sale['TOTAL_AMOUNT'], 2) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center p-5"><div class="text-muted"><i class="bi bi-search d-block fs-3 mb-2"></i>No sales found for the selected date range.</div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
          </div>
          <!-- Pagination Controls -->
          <?php if($total_pages > 1): ?>
          <nav aria-label="Page navigation" class="mt-4">
              <ul class="pagination justify-content-center">
                  <?php $query_params = "start_date=$sql_start_date&end_date=$sql_end_date"; ?>
                  <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page - 1 ?>&<?= $query_params ?>">Previous</a></li>
                  <?php for($i = 1; $i <= $total_pages; $i++): ?>
                      <li class="page-item <?= ($page == $i) ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&<?= $query_params ?>"><?= $i ?></a></li>
                  <?php endfor; ?>
                  <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>"><a class="page-link" href="?page=<?= $page + 1 ?>&<?= $query_params ?>">Next</a></li>
              </ul>
          </nav>
          <?php endif; ?>
      </div>

    </div>
  </main>

  <script>
    const chartCtx = document.getElementById('salesChart');
    if (chartCtx) {
        new Chart(chartCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Daily Sales',
                    data: <?= json_encode($chart_data) ?>,
                    backgroundColor: 'rgba(75, 28, 28, 0.1)',
                    borderColor: 'rgba(75, 28, 28, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(75, 28, 28, 1)',
                    pointRadius: 4,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                scales: { 
                    y: { beginAtZero: true, ticks: { callback: value => 'RM ' + value.toLocaleString() } },
                    x: { grid: { display: false } }
                },
                responsive: true,
                maintainAspectRatio: false,
                plugins: { 
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#333',
                        titleFont: { size: 14, weight: 'bold' },
                        bodyFont: { size: 12 },
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) { label += ': '; }
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('en-MY', { style: 'currency', currency: 'MYR' }).format(context.parsed.y);
                                }
                                return label;
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });
    }
  </script>
</body>
</html>