<?php
require_once '../db_connection.php';
session_start();
require_once '../partials/header.php';

$rental_ID = isset($_GET['rental_ID']) ? $_GET['rental_ID'] : '';
$customer_ID = $_SESSION['customer_ID'];

// Fetch booking
$stmt = $pdo->prepare('SELECT * FROM rentals WHERE rental_ID = ? AND customer_ID = ?');
$stmt->execute([$rental_ID, $customer_ID]);
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Booking not found.</div></div>';
    require_once '../partials/footer.php';
    exit;
}

// Handle payment proof upload and "Confirm Payment"
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    try {
        $pdo->beginTransaction();
        
        $proof_path = null;
        if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION);
            $proof_path = 'uploads/payment_proofs/' . $rental_ID . '_' . time() . '.' . $ext;
            if (!is_dir('../uploads/payment_proofs')) {
                mkdir('../uploads/payment_proofs', 0777, true);
            }
            move_uploaded_file($_FILES['payment_proof']['tmp_name'], '../' . $proof_path);
        }
        
        // Generate unique payment ID (improved to avoid conflicts)
        do {
            $payment_ID = 'PAY' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM payments WHERE payment_ID = ?');
            $stmt->execute([$payment_ID]);
            $exists = $stmt->fetchColumn() > 0;
        } while ($exists);
        
        // Create payment record
        $stmt = $pdo->prepare('INSERT INTO payments (payment_ID, staff_ID, amount, payment_method, payment_proof, payment_status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $payment_ID,
            null, // staff_ID is null for customer payments
            $booking['total_amount'],
            'FPX',
            $proof_path,
            'completed'
        ]);
        
        // Update rental with payment_ID and set status to confirmed
        $stmt = $pdo->prepare('UPDATE rentals SET payment_ID = ?, rental_status = "confirmed" WHERE rental_ID = ?');
        $stmt->execute([$payment_ID, $rental_ID]);
        
        $pdo->commit();
        $success = 'Payment submitted! Your booking is now confirmed.';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        // Enhanced error reporting for debugging
        $error = 'Payment submission failed: ' . $e->getMessage() . ' (Error Code: ' . $e->getCode() . ')';
    }
}
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-lg border-0 rounded-4 p-4">
                <h2 class="mb-4 text-center fw-bold text-purple">Booking Payment</h2>
                <?php if ($error): ?>
                    <div class="alert alert-danger text-center fs-5 fw-semibold"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success text-center fs-5 fw-semibold"><?php echo htmlspecialchars($success); ?></div>
                    <div class="text-center mt-4">
                        <a href="home.php" class="btn btn-outline-purple btn-lg px-5 py-2 rounded-pill">Back to Home</a>
                    </div>
                <?php else: ?>
                    <div class="mb-4 text-center">
                        <strong>Booking ID:</strong> <?php echo htmlspecialchars($booking['rental_ID']); ?><br>
                        <strong>Date & Time:</strong> <?php echo htmlspecialchars($booking['booking_start_time']); ?><br>
                        <strong>Duration:</strong> <?php echo (strtotime($booking['booking_end_time']) - strtotime($booking['booking_start_time']))/3600; ?> hour(s)<br>
                        <strong>Total Amount:</strong> RM <?php echo number_format($booking['total_amount'], 2); ?><br>
                    </div>
                    <div class="mb-4 text-center">
                        <img src="https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=PAYMENT_PLACEHOLDER_<?php echo urlencode($booking['rental_ID']); ?>" alt="QR Code" class="mb-2" />
                        <div class="text-muted">Scan this QR code to pay</div>
                    </div>
                    <form method="post" enctype="multipart/form-data" id="paymentForm">
                        <div class="mb-3">
                            <label for="payment_proof" class="form-label">Upload Payment Proof (optional)</label>
                            <input type="file" class="form-control" id="payment_proof" name="payment_proof" accept="image/*,application/pdf">
                            <div class="form-text">Upload a screenshot or photo of your payment receipt</div>
                        </div>
                        
                        <!-- Warning Notice -->
                        <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div>
                                <strong>Important:</strong> Once payment is confirmed, it cannot be refunded. Please ensure you have completed the payment before confirming.
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-outline-purple btn-lg px-5 py-2 rounded-pill" onclick="showConfirmationModal()">
                                <i class="fas fa-credit-card me-2"></i>Confirm Payment
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Payment Confirmation Modal -->
<div class="modal fade" id="paymentConfirmationModal" tabindex="-1" aria-labelledby="paymentConfirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="paymentConfirmationModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Payment Confirmation Required
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-credit-card fa-3x text-warning mb-3"></i>
                    <h4 class="text-dark">Are you sure you want to confirm this payment?</h4>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-info">
                            <div class="card-header bg-info text-white text-center">
                                <h6 class="mb-0">Booking Details</h6>
                            </div>
                            <div class="card-body">
                                <p class="mb-1"><strong>Booking ID:</strong> <?php echo htmlspecialchars($booking['rental_ID']); ?></p>
                                <p class="mb-1"><strong>Total Amount:</strong> <span class="text-success fs-5 fw-bold">RM <?php echo number_format($booking['total_amount'], 2); ?></span></p>
                                <p class="mb-0"><strong>Duration:</strong> <?php echo (strtotime($booking['booking_end_time']) - strtotime($booking['booking_start_time']))/3600; ?> hour(s)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-danger">
                            <div class="card-header bg-danger text-white text-center">
                                <h6 class="mb-0">Important Notice</h6>
                            </div>
                            <div class="card-body">
                                <div class="text-danger">
                                    <p class="mb-2"><i class="fas fa-ban me-2"></i><strong>No Refunds Policy</strong></p>
                                    <p class="mb-1">• Payment cannot be refunded once confirmed</p>
                                    <p class="mb-1">• Booking becomes final and binding</p>
                                    <p class="mb-0">• Cancellation may result in account restrictions</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle me-2 fs-4"></i>
                        <div>
                            <strong>Please confirm that:</strong>
                            <ul class="mb-0 mt-2">
                                <li>You have successfully completed the payment of <strong>RM <?php echo number_format($booking['total_amount'], 2); ?></strong></li>
                                <li>You understand that this payment cannot be refunded</li>
                                <li>You agree to the terms and conditions of the rental service</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-lg" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-success btn-lg" onclick="confirmPayment()">
                    <i class="fas fa-check-circle me-2"></i>Yes, Confirm Payment
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function showConfirmationModal() {
    // Show the confirmation modal
    const modal = new bootstrap.Modal(document.getElementById('paymentConfirmationModal'));
    modal.show();
}

function confirmPayment() {
    // Create a hidden input for the mark_paid field
    const form = document.getElementById('paymentForm');
    const hiddenInput = document.createElement('input');
    hiddenInput.type = 'hidden';
    hiddenInput.name = 'mark_paid';
    hiddenInput.value = '1';
    form.appendChild(hiddenInput);
    
    // Hide the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('paymentConfirmationModal'));
    modal.hide();
    
    // Show loading state
    const confirmBtn = document.querySelector('button[onclick="confirmPayment()"]');
    const originalContent = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
    confirmBtn.disabled = true;
    
    // Submit the form
    form.submit();
}

// Add animation to warning alert
document.addEventListener('DOMContentLoaded', function() {
    const warningAlert = document.querySelector('.alert-warning');
    if (warningAlert) {
        warningAlert.style.animation = 'pulse 2s infinite';
    }
});
</script>

<style>
@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
    70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
}

.modal-content {
    border-radius: 15px;
    overflow: hidden;
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}
</style>

<?php require_once '../partials/footer.php'; ?> 