<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

// Get dashboard statistics
$stmt = $pdo->prepare('SELECT COUNT(*) FROM rentals WHERE rental_status = "pending_payment"');
$stmt->execute();
$pending_bookings = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM rentals WHERE rental_status = "in_progress"');
$stmt->execute();
$active_bookings = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM games WHERE is_available = 1');
$stmt->execute();
$available_games = $stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM consoles WHERE consoles_status = "available"');
$stmt->execute();
$available_consoles = $stmt->fetchColumn();

// Get recent bookings
$stmt = $pdo->prepare('
    SELECT r.*, c.customer_full_name, co.console_name 
    FROM rentals r 
    JOIN customers c ON r.customer_ID = c.customer_ID 
    JOIN consoles co ON r.console_ID = co.console_ID 
    ORDER BY r.created_at DESC 
    LIMIT 5
');
$stmt->execute();
$recent_bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - PS4 Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <div class="container mt-4">
        <h2><i class="fas fa-tachometer-alt"></i> Staff Dashboard</h2>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Pending Bookings</h5>
                                <h3><?php echo $pending_bookings; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-clock fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Active Bookings</h5>
                                <h3><?php echo $active_bookings; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-play fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Available Games</h5>
                                <h3><?php echo $available_games; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-gamepad fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Available Consoles</h5>
                                <h3><?php echo $available_consoles; ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tv fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <a href="bookings_management.php" class="btn btn-primary w-100">
                                    <i class="fas fa-calendar-check"></i> Manage Bookings
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="inventory_management.php" class="btn btn-success w-100">
                                    <i class="fas fa-boxes"></i> Inventory Management
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="reports.php" class="btn btn-info w-100">
                                    <i class="fas fa-chart-bar"></i> View Reports
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
                                <a href="staff_settings.php" class="btn btn-secondary w-100">
                                    <i class="fas fa-cog"></i> Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-history"></i> Recent Bookings</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Rental ID</th>
                                        <th>Customer</th>
                                        <th>Console</th>
                                        <th>Start Time</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_bookings as $booking): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($booking['rental_ID']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['customer_full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['console_name']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($booking['booking_start_time'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $booking['rental_status'] === 'completed' ? 'success' : 
                                                    ($booking['rental_status'] === 'in_progress' ? 'warning' : 
                                                    ($booking['rental_status'] === 'pending_payment' ? 'info' : 
                                                    ($booking['rental_status'] === 'cancelled' ? 'danger' : 'secondary'))); 
                                            ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $booking['rental_status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $booking['total_amount'] ? 'RM ' . number_format($booking['total_amount'], 2) : '-'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="bookings_management.php" class="btn btn-primary">
                                <i class="fas fa-list"></i> View All Bookings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 