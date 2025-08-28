<?php
session_start();

// Auto-login with remember_me cookie if not already logged in
if (!isset($_SESSION['customer_ID']) && isset($_COOKIE['remember_me'])) {
    require_once '../db_connection.php';
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE remember_token = ?');
    $stmt->execute([$_COOKIE['remember_me']]);
    $customer = $stmt->fetch();
    if ($customer) {
        $_SESSION['customer_ID'] = $customer['customer_ID'];
        $_SESSION['customer_username'] = $customer['customer_username'];
        $_SESSION['customer_full_name'] = $customer['customer_full_name'];
        $_SESSION['user_type'] = 'customer';
    }
}

if (!isset($_SESSION['customer_ID'])) {
    header('Location: login.php');
    exit();
}

require_once '../db_connection.php';

$customer_ID = $_SESSION['customer_ID'];

// Get customer's recent bookings
$stmt = $pdo->prepare('
    SELECT r.*, co.console_name 
    FROM rentals r 
    JOIN consoles co ON r.console_ID = co.console_ID 
    WHERE r.customer_ID = ? 
    ORDER BY r.booking_start_time DESC 
    LIMIT 5
');
$stmt->execute([$customer_ID]);
$recent_bookings = $stmt->fetchAll();

// Get available games count
$stmt = $pdo->prepare('SELECT COUNT(*) FROM games WHERE is_available = 1');
$stmt->execute();
$available_games = $stmt->fetchColumn();

// Get available consoles count
$stmt = $pdo->prepare('SELECT COUNT(*) FROM consoles WHERE consoles_status = "available"');
$stmt->execute();
$available_consoles = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Home - PS4 Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section bg-purple text-white py-5 mb-4 rounded">
        <div class="container text-center">
            <h1 class="display-4">Welcome back, <?php echo htmlspecialchars($_SESSION['customer_full_name']); ?>!</h1>
            <p class="lead">Ready for some gaming? Check out our available consoles and games, or make a new booking now!</p>
            <a href="new_booking.php" class="btn btn-outline-purple btn-lg mt-3 fw-bold">Book Now</a>
        </div>
    </section>

    <div class="container">
        <div class="row">
            <div class="col-md-8">
                <!-- Quick Stats -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card bg-purple text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Available Games</h5>
                                        <h3><?php echo $available_games; ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-gamepad fa-2x"></i>
                                    </div>
                                </div>
                                <a href="games.php" class="btn btn-outline-purple btn-sm mt-2">
                                    <i class="fas fa-eye"></i> Browse Games
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card bg-purple text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title">Available Consoles</h5>
                                        <h3><?php echo $available_consoles; ?></h3>
                                    </div>
                                    <div>
                                        <i class="fas fa-tv fa-2x"></i>
                                    </div>
                                </div>
                                <a href="new_booking.php" class="btn btn-outline-purple btn-sm mt-2">
                                    <i class="fas fa-calendar-plus"></i> Make Booking
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Bookings -->
                <div class="card mb-4">
                    <div class="card-header bg-purple text-white">
                        <h5><i class="fas fa-history"></i> Recent Bookings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_bookings)): ?>
                            <p class="text-muted">No bookings yet. <a href="new_booking.php">Make your first booking!</a></p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Booking ID</th>
                                            <th>Console</th>
                                            <th>Date & Time</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_bookings as $booking): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($booking['rental_ID']); ?></td>
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
                                <a href="booking_history.php" class="btn btn-outline-purple text-white">
                                    <i class="fas fa-list"></i> View All Bookings
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <div class="card mb-4 bg-purple text-white quick-actions">
                    <div class="card-header bg-purple text-white">
                        <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="new_booking.php" class="btn btn-outline-purple">New Booking</a>
                            <a href="games.php" class="btn btn-outline-purple">Browse Games</a>
                            <a href="booking_history.php" class="btn btn-outline-purple">Booking History</a>
                            <a href="settings.php" class="btn btn-outline-purple">Settings</a>
                        </div>
                    </div>
                </div>

                <!-- System Info -->
                <div class="card">
                    <div class="card-header bg-purple text-white">
                        <h5><i class="fas fa-info-circle"></i> System Info</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Available Games:</strong> <?php echo $available_games; ?></p>
                        <p><strong>Available Consoles:</strong> <?php echo $available_consoles; ?></p>
                        <p><strong>Your Bookings:</strong> <?php echo count($recent_bookings); ?> recent</p>
                        <hr>
                        <small class="text-muted">
                            <i class="fas fa-clock"></i> 
                            Current time: <?php echo date('M d, Y H:i'); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 