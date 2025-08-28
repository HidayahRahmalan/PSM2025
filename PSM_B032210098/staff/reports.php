<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

// Get game rental statistics
$stmt = $pdo->prepare('
    SELECT g.game_title, COUNT(rg.game_ID) as rental_count
    FROM rental_games rg
    JOIN games g ON rg.game_ID = g.game_ID
    GROUP BY rg.game_ID
    ORDER BY rental_count DESC
');
$stmt->execute();
$game_stats = $stmt->fetchAll();

// Get customer booking statistics
$stmt = $pdo->prepare('
    SELECT c.customer_ID, c.customer_full_name, c.customer_email, 
           COUNT(r.customer_ID) as booking_count,
           SUM(CASE WHEN r.rental_status = "completed" THEN r.total_amount ELSE 0 END) as total_spent,
           MAX(r.booking_start_time) as last_booking_date
    FROM rentals r
    JOIN customers c ON r.customer_ID = c.customer_ID
    GROUP BY r.customer_ID, c.customer_full_name, c.customer_email
    ORDER BY booking_count DESC
    LIMIT 5
');
$stmt->execute();
$customer_stats = $stmt->fetchAll();

// Get overall statistics
$stmt = $pdo->prepare('
    SELECT 
        COUNT(r.rental_ID) as total_bookings,
        SUM(CASE WHEN r.rental_status = "completed" THEN 1 ELSE 0 END) as completed_bookings,
        SUM(CASE WHEN r.rental_status = "in_progress" THEN 1 ELSE 0 END) as active_bookings,
        SUM(r.total_amount) as total_revenue
    FROM rentals r
');
$stmt->execute();
$overall_stats = $stmt->fetch();

// Get payment statistics
$stmt = $pdo->prepare('
    SELECT 
        COUNT(p.payment_ID) as total_payments,
        SUM(p.amount) as total_payment_amount,
        SUM(CASE WHEN p.payment_status = "completed" THEN p.amount ELSE 0 END) as completed_payments,
        SUM(CASE WHEN p.payment_status = "refunded" THEN p.amount ELSE 0 END) as refunded_amount
    FROM payments p
');
$stmt->execute();
$payment_stats = $stmt->fetch();

// Get payment method statistics
$stmt = $pdo->prepare('
    SELECT 
        payment_method,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM payments 
    WHERE payment_status = "completed"
    GROUP BY payment_method
    ORDER BY total_amount DESC
');
$stmt->execute();
$payment_methods = $stmt->fetchAll();

// Handle revenue period selection
$revenue_period = $_GET['revenue_period'] ?? '30days';

switch ($revenue_period) {
    case 'weekly':
        // Weekly revenue for last 8 weeks
        $stmt = $pdo->prepare('
            SELECT 
                YEARWEEK(booking_start_time, 1) as week,
                DATE(DATE_SUB(booking_start_time, INTERVAL WEEKDAY(booking_start_time) DAY)) as week_start,
                SUM(total_amount) as revenue
            FROM rentals
            WHERE rental_status IN ("confirmed", "in_progress", "completed") AND booking_start_time >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
            GROUP BY week, week_start
            ORDER BY week
        ');
        $stmt->execute();
        $revenue_data = $stmt->fetchAll();
        
        // Create complete dataset for last 8 weeks
        $revenue_labels = [];
        $revenue_values = [];
        $data_map = [];
        
        // Map existing data
        foreach ($revenue_data as $row) {
            $data_map[$row['week']] = (float)$row['revenue'];
        }
        
        // Generate last 8 weeks with actual data or zeros
        for ($i = 7; $i >= 0; $i--) {
            $week_start = date('Y-m-d', strtotime("-$i weeks monday"));
            $week_number = date('oW', strtotime($week_start));
            $revenue_labels[] = 'Week ' . date('M d', strtotime($week_start));
            $revenue_values[] = isset($data_map[$week_number]) ? $data_map[$week_number] : 0;
        }
        $revenue_title = 'Revenue Trend (Weekly, Last 8 Weeks)';
        break;
        
    case 'monthly_3':
        // Monthly revenue for last 3 months
        $stmt = $pdo->prepare('
            SELECT DATE_FORMAT(booking_start_time, "%Y-%m") as month, SUM(total_amount) as revenue
            FROM rentals
            WHERE rental_status IN ("confirmed", "in_progress", "completed") AND booking_start_time >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
            GROUP BY month
            ORDER BY month
        ');
        $stmt->execute();
        $revenue_data = $stmt->fetchAll();
        
        // Create complete dataset for last 3 months
        $revenue_labels = [];
        $revenue_values = [];
        $data_map = [];
        
        // Map existing data
        foreach ($revenue_data as $row) {
            $data_map[$row['month']] = (float)$row['revenue'];
        }
        
        // Generate last 3 months with actual data or zeros
        for ($i = 2; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $revenue_labels[] = date('M Y', strtotime($month . '-01'));
            $revenue_values[] = isset($data_map[$month]) ? $data_map[$month] : 0;
        }
        $revenue_title = 'Revenue Trend (Monthly, Last 3 Months)';
        break;
        
    case 'monthly_6':
        // Monthly revenue for last 6 months
        $stmt = $pdo->prepare('
            SELECT DATE_FORMAT(booking_start_time, "%Y-%m") as month, SUM(total_amount) as revenue
            FROM rentals
            WHERE rental_status IN ("confirmed", "in_progress", "completed") AND booking_start_time >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY month
            ORDER BY month
        ');
        $stmt->execute();
        $revenue_data = $stmt->fetchAll();
        
        // Create complete dataset for last 6 months
        $revenue_labels = [];
        $revenue_values = [];
        $data_map = [];
        
        // Map existing data
        foreach ($revenue_data as $row) {
            $data_map[$row['month']] = (float)$row['revenue'];
        }
        
        // Generate last 6 months with actual data or zeros
        for ($i = 5; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $revenue_labels[] = date('M Y', strtotime($month . '-01'));
            $revenue_values[] = isset($data_map[$month]) ? $data_map[$month] : 0;
        }
        $revenue_title = 'Revenue Trend (Monthly, Last 6 Months)';
        break;
        
    case 'monthly_12':
        // Monthly revenue for last 12 months
        $stmt = $pdo->prepare('
            SELECT DATE_FORMAT(booking_start_time, "%Y-%m") as month, SUM(total_amount) as revenue
            FROM rentals
            WHERE rental_status IN ("confirmed", "in_progress", "completed") AND booking_start_time >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
            GROUP BY month
            ORDER BY month
        ');
        $stmt->execute();
        $revenue_data = $stmt->fetchAll();
        
        // Create complete dataset for last 12 months
        $revenue_labels = [];
        $revenue_values = [];
        $data_map = [];
        
        // Map existing data
        foreach ($revenue_data as $row) {
            $data_map[$row['month']] = (float)$row['revenue'];
        }
        
        // Generate last 12 months with actual data or zeros
        for ($i = 11; $i >= 0; $i--) {
            $month = date('Y-m', strtotime("-$i months"));
            $revenue_labels[] = date('M Y', strtotime($month . '-01'));
            $revenue_values[] = isset($data_map[$month]) ? $data_map[$month] : 0;
        }
        $revenue_title = 'Revenue Trend (Monthly, Last 12 Months)';
        break;
        
    case '7days':
        // Daily revenue for last 7 days
        $stmt = $pdo->prepare('
            SELECT DATE(booking_start_time) as day, SUM(total_amount) as revenue
            FROM rentals
            WHERE rental_status IN ("confirmed", "in_progress", "completed") AND booking_start_time >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY day
            ORDER BY day
        ');
        $stmt->execute();
        $revenue_data = $stmt->fetchAll();
        
        // Create complete dataset for last 7 days
        $revenue_labels = [];
        $revenue_values = [];
        $data_map = [];
        
        // Map existing data
        foreach ($revenue_data as $row) {
            $data_map[$row['day']] = (float)$row['revenue'];
        }
        
        // Generate last 7 days with actual data or zeros
        for ($i = 6; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $revenue_labels[] = date('M d', strtotime($day));
            $revenue_values[] = isset($data_map[$day]) ? $data_map[$day] : 0;
        }
        $revenue_title = 'Revenue Trend (Daily, Last 7 Days)';
        break;
        
    default: // '30days'
        // Daily revenue for last 30 days
        $stmt = $pdo->prepare('
            SELECT DATE(booking_start_time) as day, SUM(total_amount) as revenue
            FROM rentals
            WHERE rental_status IN ("confirmed", "in_progress", "completed") AND booking_start_time >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY day
            ORDER BY day
        ');
        $stmt->execute();
        $revenue_data = $stmt->fetchAll();
        
        // Create complete dataset for last 30 days
        $revenue_labels = [];
        $revenue_values = [];
        $data_map = [];
        
        // Map existing data
        foreach ($revenue_data as $row) {
            $data_map[$row['day']] = (float)$row['revenue'];
        }
        
        // Generate last 30 days with actual data or zeros
        for ($i = 29; $i >= 0; $i--) {
            $day = date('Y-m-d', strtotime("-$i days"));
            $revenue_labels[] = date('M d', strtotime($day));
            $revenue_values[] = isset($data_map[$day]) ? $data_map[$day] : 0;
        }
        $revenue_title = 'Revenue Trend (Daily, Last 30 Days)';
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - PS4 Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <div class="container mt-4">
        <button class="btn btn-outline-dark mb-3" id="downloadAllPDF">
            <i class="fas fa-file-pdf"></i> Download All Reports as PDF
        </button>
        <div class="mb-3 d-flex gap-2">
            <a href="export_bookings_csv.php" class="btn btn-outline-primary">
                <i class="fas fa-file-csv"></i> Export Bookings (CSV)
            </a>
            <a href="export_bookings_pdf.php" class="btn btn-outline-danger">
                <i class="fas fa-file-pdf"></i> Export Bookings (PDF)
            </a>
        </div>
        <div id="reportContent">
            <h2><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>

            <!-- Overall Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Bookings</h5>
                            <h3><?php echo $overall_stats['total_bookings']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Completed</h5>
                            <h3><?php echo $overall_stats['completed_bookings']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title">Active</h5>
                            <h3><?php echo $overall_stats['active_bookings']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Revenue</h5>
                            <h3>RM <?php echo number_format($overall_stats['total_revenue'], 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-purple text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Payments</h5>
                            <h3><?php echo $payment_stats['total_payments']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">Completed Payments</h5>
                            <h3>RM <?php echo number_format($payment_stats['completed_payments'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">Total Payment Amount</h5>
                            <h3>RM <?php echo number_format($payment_stats['total_payment_amount'], 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Trend Chart -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-gradient text-dark d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-chart-line"></i> <?php echo $revenue_title; ?></h5>
                            <form method="get" class="d-inline-block ms-3">
                                <select name="revenue_period" class="form-select form-select-sm" onchange="this.form.submit()">
                                    <optgroup label="Daily Views">
                                        <option value="7days" <?php if ($revenue_period === '7days') echo 'selected'; ?>>Last 7 Days</option>
                                        <option value="30days" <?php if ($revenue_period === '30days') echo 'selected'; ?>>Last 30 Days</option>
                                    </optgroup>
                                    <optgroup label="Weekly Views">
                                        <option value="weekly" <?php if ($revenue_period === 'weekly') echo 'selected'; ?>>Weekly (8 Weeks)</option>
                                    </optgroup>
                                    <optgroup label="Monthly Views">
                                        <option value="monthly_3" <?php if ($revenue_period === 'monthly_3') echo 'selected'; ?>>Monthly (3 Months)</option>
                                        <option value="monthly_6" <?php if ($revenue_period === 'monthly_6') echo 'selected'; ?>>Monthly (6 Months)</option>
                                        <option value="monthly_12" <?php if ($revenue_period === 'monthly_12') echo 'selected'; ?>>Monthly (12 Months)</option>
                                    </optgroup>
                                </select>
                            </form>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="80"></canvas>
                            <?php if (empty($revenue_data)): ?>
                            <div class="alert alert-info mt-3">
                                <i class="fas fa-info-circle"></i> 
                                No completed bookings found in the selected period. Revenue data will be shown once you have completed rentals.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Top Games -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-gamepad"></i> Most Popular Games</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="gamesChart" height="180"></canvas>
                            <div class="table-responsive mt-4">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Game</th>
                                            <th>Rental Count</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($game_stats as $game): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($game['game_title']); ?></td>
                                            <td><?php echo $game['rental_count']; ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top 5 Customers -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-crown text-warning"></i> Top 5 Customers</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="customersChart" height="180"></canvas>
                            <div class="mt-4">
                                <?php foreach ($customer_stats as $index => $customer): ?>
                                <div class="customer-card mb-3 p-3 border rounded-3 position-relative overflow-hidden" 
                                     style="cursor: pointer; transition: all 0.3s ease; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);"
                                     onmouseover="this.style.transform='scale(1.02)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)'"
                                     onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='0 2px 10px rgba(0,0,0,0.1)'"
                                     onclick="showCustomerDetails('<?php echo htmlspecialchars($customer['customer_ID']); ?>', '<?php echo htmlspecialchars($customer['customer_full_name']); ?>')">
                                    
                                    <!-- Rank Badge -->
                                    <div class="position-absolute top-0 start-0 bg-<?php 
                                        echo $index === 0 ? 'warning' : ($index === 1 ? 'secondary' : ($index === 2 ? 'info' : 'primary')); 
                                    ?> text-white px-2 py-1 rounded-end-3">
                                        <small class="fw-bold">#<?php echo $index + 1; ?></small>
                                    </div>
                                    
                                    <!-- Trophy for #1 -->
                                    <?php if ($index === 0): ?>
                                    <div class="position-absolute top-0 end-0 p-2">
                                        <i class="fas fa-trophy text-warning fs-4"></i>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="row align-items-center">
                                        <div class="col-md-8">
                                            <div class="d-flex align-items-center mb-2">
                                                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center me-3" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="fas fa-user text-white"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0 fw-bold text-dark"><?php echo htmlspecialchars($customer['customer_full_name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($customer['customer_email']); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <div class="mb-1">
                                                <span class="badge bg-success fs-6 px-3 py-2 rounded-pill">
                                                    <?php echo $customer['booking_count']; ?> Bookings
                                                </span>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-dollar-sign"></i> RM <?php echo number_format($customer['total_spent'], 2); ?>
                                            </div>
                                            <div class="text-muted small">
                                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($customer['last_booking_date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Progress Bar -->
                                    <div class="mt-2">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <small class="text-muted">Activity Level</small>
                                            <small class="text-muted"><?php echo round(($customer['booking_count'] / max(array_column($customer_stats, 'booking_count'))) * 100); ?>%</small>
                                        </div>
                                        <div class="progress" style="height: 4px;">
                                            <div class="progress-bar bg-<?php 
                                                echo $index === 0 ? 'warning' : ($index === 1 ? 'success' : ($index === 2 ? 'info' : 'primary')); 
                                            ?>" 
                                                 style="width: <?php echo round(($customer['booking_count'] / max(array_column($customer_stats, 'booking_count'))) * 100); ?>%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Details Modal -->
    <div class="modal fade" id="customerDetailsModal" tabindex="-1" aria-labelledby="customerDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="customerDetailsModalLabel">
                        <i class="fas fa-user-circle me-2"></i>Customer Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="customerDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading customer details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
    // Chart for Most Popular Games (Horizontal Bar Chart)
    const gamesChart = document.getElementById('gamesChart').getContext('2d');
    new Chart(gamesChart, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($game_stats, 'game_title')); ?>,
            datasets: [{
                label: 'Rental Count',
                data: <?php echo json_encode(array_column($game_stats, 'rental_count')); ?>,
                backgroundColor: [
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)',
                    'rgba(199, 199, 199, 0.7)',
                    'rgba(255, 205, 86, 0.7)',
                    'rgba(201, 203, 207, 0.7)',
                    'rgba(100, 181, 246, 0.7)'
                ],
                borderColor: [
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(199, 199, 199, 1)',
                    'rgba(255, 205, 86, 1)',
                    'rgba(201, 203, 207, 1)',
                    'rgba(100, 181, 246, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: { enabled: true }
            },
            scales: {
                x: { 
                    beginAtZero: true, 
                    ticks: { precision: 0 } 
                },
                y: {
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
    // Chart for Top 5 Customers (Interactive Doughnut)
    const customersChart = document.getElementById('customersChart').getContext('2d');
    const customerChart = new Chart(customersChart, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode(array_column($customer_stats, 'customer_full_name')); ?>,
            datasets: [{
                label: 'Bookings',
                data: <?php echo json_encode(array_column($customer_stats, 'booking_count')); ?>,
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',   // Gold for #1
                    'rgba(108, 117, 125, 0.8)', // Silver for #2
                    'rgba(23, 162, 184, 0.8)',  // Bronze for #3
                    'rgba(40, 167, 69, 0.8)',   // Green for #4
                    'rgba(220, 53, 69, 0.8)'    // Red for #5
                ],
                borderColor: [
                    'rgba(255, 193, 7, 1)',
                    'rgba(108, 117, 125, 1)',
                    'rgba(23, 162, 184, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 2,
                hoverOffset: 15
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { 
                    display: true, 
                    position: 'bottom',
                    labels: {
                        generateLabels: function(chart) {
                            const data = chart.data;
                            if (data.labels.length && data.datasets.length) {
                                return data.labels.map((label, i) => {
                                    const value = data.datasets[0].data[i];
                                    return {
                                        text: `#${i+1} ${label} (${value})`,
                                        fillStyle: data.datasets[0].backgroundColor[i],
                                        strokeStyle: data.datasets[0].borderColor[i],
                                        lineWidth: data.datasets[0].borderWidth,
                                        hidden: isNaN(value),
                                        index: i
                                    };
                                });
                            }
                            return [];
                        }
                    }
                },
                tooltip: { 
                    callbacks: {
                        label: function(context) {
                            const customerData = <?php echo json_encode($customer_stats); ?>;
                            const customer = customerData[context.dataIndex];
                            return [
                                `Rank: #${context.dataIndex + 1}`,
                                `Bookings: ${context.parsed}`,
                                `Total Spent: RM ${parseFloat(customer.total_spent).toFixed(2)}`,
                                `Last Booking: ${new Date(customer.last_booking_date).toLocaleDateString()}`
                            ];
                        }
                    }
                }
            },
            onClick: (event, elements) => {
                if (elements.length > 0) {
                    const customerData = <?php echo json_encode($customer_stats); ?>;
                    const clickedIndex = elements[0].index;
                    const customer = customerData[clickedIndex];
                    showCustomerDetails(customer.customer_ID, customer.customer_full_name);
                }
            },
            onHover: (event, elements) => {
                event.native.target.style.cursor = elements.length > 0 ? 'pointer' : 'default';
            }
        }
    });

    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    
    // Debug: Log the data being passed to the chart
    console.log('Revenue Labels:', <?php echo json_encode($revenue_labels); ?>);
    console.log('Revenue Values:', <?php echo json_encode($revenue_values); ?>);
    console.log('Revenue Data Count:', <?php echo count($revenue_data); ?>);
    
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($revenue_labels); ?>,
            datasets: [{
                label: 'Revenue (RM)',
                data: <?php echo json_encode($revenue_values); ?>,
                borderColor: 'purple',
                backgroundColor: 'rgba(128,0,128,0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                title: { display: false }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: value => 'RM ' + value }
                }
            }
        }
    });

    document.getElementById('downloadAllPDF').onclick = function() {
        const reportContent = document.getElementById('reportContent');
        html2canvas(reportContent, {scale:2}).then(canvas => {
            const imgData = canvas.toDataURL('image/png');
            const pdf = new jspdf.jsPDF('p', 'mm', 'a4');
            const pageWidth = pdf.internal.pageSize.getWidth();
            const pageHeight = pdf.internal.pageSize.getHeight();
            // Calculate image dimensions to fit A4
            const imgProps = pdf.getImageProperties(imgData);
            let pdfWidth = pageWidth;
            let pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
            if (pdfHeight > pageHeight) {
                pdfHeight = pageHeight;
                pdfWidth = (imgProps.width * pdfHeight) / imgProps.height;
            }
            pdf.addImage(imgData, 'PNG', 0, 0, pdfWidth, pdfHeight);
            pdf.save('ps4_rental_reports.pdf');
        });
    };

    // Function to show customer details in modal
    function showCustomerDetails(customerID, customerName) {
        // Update modal title
        document.getElementById('customerDetailsModalLabel').innerHTML = 
            '<i class="fas fa-user-circle me-2"></i>' + customerName + ' - Details';
        
        // Show loading state
        document.getElementById('customerDetailsContent').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading customer details...</p>
            </div>
        `;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('customerDetailsModal'));
        modal.show();
        
        // Fetch customer details (you can implement AJAX call here)
        // For now, we'll show customer info from the existing data
        const customerData = <?php echo json_encode($customer_stats); ?>;
        const customer = customerData.find(c => c.customer_ID === customerID);
        
        if (customer) {
            setTimeout(() => {
                document.getElementById('customerDetailsContent').innerHTML = `
                    <div class="row">
                        <div class="col-md-4 text-center mb-4">
                            <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center mb-3" 
                                 style="width: 80px; height: 80px;">
                                <i class="fas fa-user text-white fa-2x"></i>
                            </div>
                            <h5 class="fw-bold">${customer.customer_full_name}</h5>
                            <p class="text-muted mb-0">${customer.customer_email}</p>
                            <small class="text-muted">Customer ID: ${customer.customer_ID}</small>
                        </div>
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="card bg-success text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                                            <h4 class="mb-0">${customer.booking_count}</h4>
                                            <small>Total Bookings</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-info text-white h-100">
                                        <div class="card-body text-center">
                                            <i class="fas fa-dollar-sign fa-2x mb-2"></i>
                                            <h4 class="mb-0">RM ${parseFloat(customer.total_spent).toFixed(2)}</h4>
                                            <small>Total Spent</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="card bg-warning text-dark">
                                        <div class="card-body text-center">
                                            <i class="fas fa-clock fa-2x mb-2"></i>
                                            <h6 class="mb-0">Last Booking</h6>
                                            <p class="mb-0">${new Date(customer.last_booking_date).toLocaleDateString('en-US', { 
                                                year: 'numeric', 
                                                month: 'long', 
                                                day: 'numeric' 
                                            })}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <div class="text-center">
                        <h6 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Customer Activity</h6>
                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar bg-gradient progress-bar-striped progress-bar-animated" 
                                 style="width: ${Math.round((customer.booking_count / Math.max(...customerData.map(c => c.booking_count))) * 100)}%">
                                ${Math.round((customer.booking_count / Math.max(...customerData.map(c => c.booking_count))) * 100)}% Activity Level
                            </div>
                        </div>
                        <small class="text-muted">Compared to most active customer</small>
                    </div>
                `;
            }, 500);
        }
    }

    // Add click animation to customer cards
    document.querySelectorAll('.customer-card').forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = 'scale(1.02)';
            }, 100);
        });
    });
    </script>
</body>
</html> 