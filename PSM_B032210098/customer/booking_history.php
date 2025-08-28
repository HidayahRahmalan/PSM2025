<?php
session_start();
if (!isset($_SESSION['customer_ID'])) {
    header('Location: login.php');
    exit();
}

require_once '../db_connection.php';

$customer_ID = $_SESSION['customer_ID'];

// Handle booking cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_rental_ID'])) {
    $rental_ID = $_POST['cancel_rental_ID'];
    
    // Verify the booking belongs to this customer and can be cancelled
    $stmt = $pdo->prepare("SELECT * FROM rentals WHERE rental_ID = ? AND customer_ID = ? AND booking_start_time > NOW() AND rental_status IN ('pending_payment','confirmed')");
    $stmt->execute([$rental_ID, $customer_ID]);
    $booking = $stmt->fetch();
    
    if ($booking) {
        $stmt = $pdo->prepare("UPDATE rentals SET rental_status = 'cancelled' WHERE rental_ID = ?");
        $stmt->execute([$rental_ID]);
        // Check if customer is now banned
        $stmt = $pdo->prepare("SELECT status FROM customers WHERE customer_ID = ?");
        $stmt->execute([$customer_ID]);
        $status = $stmt->fetchColumn();
        if ($status === 'banned') {
            $success_message = "Booking cancelled successfully. <b>Warning:</b> You have been banned due to excessive cancellations this month.";
        } else {
            $success_message = "Booking cancelled successfully.";
        }
    } else {
        $error_message = "Unable to cancel this booking.";
    }
}

// Get customer's booking history
$stmt = $pdo->prepare('
    SELECT r.*, co.console_name 
    FROM rentals r 
    JOIN consoles co ON r.console_ID = co.console_ID 
    WHERE r.customer_ID = ? 
    ORDER BY r.created_at DESC
');
$stmt->execute([$customer_ID]);
$bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - PS4 Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <div class="container mt-4">
        <h2><i class="fas fa-history"></i> Booking History</h2>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> You haven't made any bookings yet. 
                <a href="new_booking.php" class="alert-link">Make your first booking!</a>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list"></i> All Your Bookings</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Console</th>
                                    <th>Date & Time</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($booking['rental_ID']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['console_name']); ?></td>
                                    <td>
                                        <div>
                                            <strong>Start:</strong> <?php echo date('M d, Y H:i', strtotime($booking['booking_start_time'])); ?>
                                        </div>
                                        <div>
                                            <strong>End:</strong> <?php echo date('M d, Y H:i', strtotime($booking['booking_end_time'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $duration = (strtotime($booking['booking_end_time']) - strtotime($booking['booking_start_time'])) / 3600;
                                        echo $duration . ' hour' . ($duration > 1 ? 's' : '');
                                        ?>
                                    </td>
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
                                        <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $booking['rental_ID']; ?>">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                        <?php if (in_array($booking['rental_status'], ['pending_payment', 'confirmed']) && strtotime($booking['booking_start_time']) > time()): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                                                <input type="hidden" name="cancel_rental_ID" value="<?php echo $booking['rental_ID']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($booking['rental_status'] === 'pending_payment' && empty($booking['payment_ID']) && strtotime($booking['booking_start_time']) > time()): ?>
                                            <a href="payment.php?rental_ID=<?php echo urlencode($booking['rental_ID']); ?>" class="btn btn-sm btn-success">Pay Now</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
                            <p><strong>Console:</strong> <?php echo htmlspecialchars($booking['console_name']); ?></p>
                            <p><strong>Start Time:</strong> <?php echo date('M d, Y H:i', strtotime($booking['booking_start_time'])); ?></p>
                            <p><strong>End Time:</strong> <?php echo date('M d, Y H:i', strtotime($booking['booking_end_time'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> <?php echo ucfirst(str_replace('_', ' ', $booking['rental_status'])); ?></p>
                            <p><strong>Players:</strong> <?php echo $booking['number_of_players']; ?></p>
                            <p><strong>Amount:</strong> <?php echo $booking['total_amount'] ? 'RM ' . number_format($booking['total_amount'], 2) : '-'; ?></p>
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
                        <?php if (empty($games)): ?>
                            <p class="text-muted">No games selected for this booking.</p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($games as $game): ?>
                                <li><?php echo htmlspecialchars($game['game_title']); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
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
                    
                    <?php if (!empty($booking['notes'])): ?>
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