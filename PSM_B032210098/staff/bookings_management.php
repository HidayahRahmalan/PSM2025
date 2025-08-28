<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['rental_ID'])) {
    $rental_ID = $_POST['rental_ID'];
    $new_status = $_POST['new_status'];
    
    // Update rental status
    $stmt = $pdo->prepare('UPDATE rentals SET rental_status = ? WHERE rental_ID = ?');
    $stmt->execute([$new_status, $rental_ID]);
    
    header('Location: bookings_management.php?success=status_updated');
    exit();
}

// Get bookings with filters
$query = 'SELECT r.*, c.customer_full_name, co.console_name FROM rentals r JOIN customers c ON r.customer_ID = c.customer_ID JOIN consoles co ON r.console_ID = co.console_ID WHERE 1';

$params = [];

// Apply filters
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $query .= ' AND r.rental_status = ?';
    $params[] = $_GET['status'];
}

if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
    $query .= ' AND DATE(r.booking_start_time) >= ?';
    $params[] = $_GET['date_from'];
}

if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
    $query .= ' AND DATE(r.booking_start_time) <= ?';
    $params[] = $_GET['date_to'];
}

$query .= ' ORDER BY r.booking_start_time DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management - PS4 Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-calendar-check"></i> Bookings Management</h2>
            <div>
                <a href="export_bookings_csv.php" class="btn btn-success me-2">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <a href="export_bookings_pdf.php" class="btn btn-danger">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-filter"></i> Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="pending_payment" <?php echo isset($_GET['status']) && $_GET['status'] === 'pending_payment' ? 'selected' : ''; ?>>Pending Payment</option>
                            <option value="confirmed" <?php echo isset($_GET['status']) && $_GET['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="in_progress" <?php echo isset($_GET['status']) && $_GET['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="completed" <?php echo isset($_GET['status']) && $_GET['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo isset($_GET['status']) && $_GET['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $_GET['date_from'] ?? ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $_GET['date_to'] ?? ''; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                            <a href="bookings_management.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> All Bookings</h5>
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
                                <th>End Time</th>
                                <th>Status</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['rental_ID']); ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_full_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['console_name']); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($booking['booking_start_time'])); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($booking['booking_end_time'])); ?></td>
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
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="rental_ID" value="<?php echo $booking['rental_ID']; ?>">
                                        <select name="new_status" class="form-select form-select-sm" style="width: auto; display: inline-block;">
                                            <option value="pending_payment" <?php echo $booking['rental_status'] === 'pending_payment' ? 'selected' : ''; ?>>Pending Payment</option>
                                            <option value="confirmed" <?php echo $booking['rental_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="in_progress" <?php echo $booking['rental_status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="completed" <?php echo $booking['rental_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $booking['rental_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-sm btn-primary">Update</button>
                                    </form>
                                    <a href="#" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $booking['rental_ID']; ?>">View</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modals -->
    <?php foreach ($bookings as $booking): ?>
    <div class="modal fade" id="detailsModal<?php echo $booking['rental_ID']; ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?php echo $booking['rental_ID']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailsModalLabel<?php echo $booking['rental_ID']; ?>">Booking Details (<?php echo htmlspecialchars($booking['rental_ID']); ?>)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($booking['customer_full_name']); ?></p>
                            <p><strong>Console:</strong> <?php echo htmlspecialchars($booking['console_name']); ?></p>
                            <p><strong>Start Time:</strong> <?php echo date('M d, Y H:i', strtotime($booking['booking_start_time'])); ?></p>
                            <p><strong>End Time:</strong> <?php echo date('M d, Y H:i', strtotime($booking['booking_end_time'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['rental_status'])); ?></p>
                            <p><strong>Players:</strong> <?php echo $booking['number_of_players']; ?></p>
                            <p><strong>Amount:</strong> <?php echo $booking['total_amount'] ? 'RM ' . number_format($booking['total_amount'], 2) : '-'; ?></p>
                            <p><strong>Created:</strong> <?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?></p>
                        </div>
                    </div>
                    
                    <!-- Games -->
                    <div class="mt-3">
                        <h6>Selected Games:</h6>
                        <?php
                        $stmt2 = $pdo->prepare('SELECT g.game_title FROM rental_games rg JOIN games g ON rg.game_ID = g.game_ID WHERE rg.rental_ID = ?');
                        $stmt2->execute([$booking['rental_ID']]);
                        $games = $stmt2->fetchAll();
                        ?>
                        <ul>
                            <?php foreach ($games as $game): ?>
                            <li><?php echo htmlspecialchars($game['game_title']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    
                    <!-- Payment Information -->
                    <div class="mt-3">
                        <h6>Payment Information:</h6>
                        <?php
                        if (!empty($booking['payment_ID'])) {
                            $stmt3 = $pdo->prepare('SELECT * FROM payments WHERE payment_ID = ?');
                            $stmt3->execute([$booking['payment_ID']]);
                            $payment = $stmt3->fetch();
                        } else {
                            $payment = null;
                        }
                        ?>
                        <?php if ($payment): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Payment ID:</strong> <?php echo htmlspecialchars($payment['payment_ID']); ?></p>
                                    <p><strong>Amount:</strong> RM <?php echo number_format($payment['amount'], 2); ?></p>
                                    <p><strong>Method:</strong> <?php echo htmlspecialchars($payment['payment_method']); ?></p>
                                    <p><strong>Status:</strong> 
                                        <span class="badge bg-<?php echo $payment['payment_status'] === 'completed' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Payment Date:</strong> <?php echo date('M d, Y H:i', strtotime($payment['payment_date'])); ?></p>
                                    <?php if ($payment['transaction_reference']): ?>
                                        <p><strong>Transaction Ref:</strong> <?php echo htmlspecialchars($payment['transaction_reference']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($payment['payment_proof']): ?>
                                        <p><strong>Payment Proof:</strong> 
                                            <a href="../<?php echo htmlspecialchars($payment['payment_proof']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye"></i> View Proof
                                            </a>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">No payment record found.</p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($booking['notes']): ?>
                    <div class="mt-3">
                        <h6>Notes:</h6>
                        <p><?php echo htmlspecialchars($booking['notes']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 