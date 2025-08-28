<?php
require_once '../db_connection.php';
require_once '../partials/header.php';
$token = $_GET['token'] ?? '';
$success = '';
$error = '';
if ($token) {
    // Check if it's a customer verification token
    $stmt = $pdo->prepare('SELECT customer_ID FROM customers WHERE verification_token = ? AND status = "pending_verification"');
    $stmt->execute([$token]);
    $customer = $stmt->fetch();
    
    if ($customer) {
        // Verify customer account
        $stmt = $pdo->prepare('UPDATE customers SET status = "active", verification_token = NULL WHERE customer_ID = ?');
        $stmt->execute([$customer['customer_ID']]);
        $success = 'Your email has been verified! You can now log in as a customer.';
    } else {
        // Check if it's a staff verification token
        $stmt = $pdo->prepare('SELECT staff_ID FROM staffs WHERE verification_token = ? AND status = "pending_verification"');
        $stmt->execute([$token]);
        $staff = $stmt->fetch();
        
        if ($staff) {
            // Verify staff account
            $stmt = $pdo->prepare('UPDATE staffs SET status = "active", verification_token = NULL WHERE staff_ID = ?');
            $stmt->execute([$staff['staff_ID']]);
            $success = 'Your email has been verified! You can now log in as staff.';
        } else {
            $error = 'Invalid or expired verification link.';
        }
    }
} else {
    $error = 'Invalid verification link.';
}
?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-purple text-white text-center py-3">
                    <h3 class="mb-0"><i class="fas fa-envelope"></i> Email Verification</h3>
                </div>
                <div class="card-body p-4">
                    <?php if ($success): ?>
                        <div class="alert alert-success text-center"><?php echo $success; ?></div>
                        <div class="text-center mt-3">
                            <?php if (strpos($success, 'staff') !== false): ?>
                                <a href="../staff/staff_login.php" class="btn btn-purple">Go to Staff Login</a>
                            <?php else: ?>
                                <a href="login.php" class="btn btn-purple">Go to Customer Login</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger text-center"><?php echo $error; ?></div>
                        <div class="text-center mt-3">
                            <a href="register.php" class="btn btn-outline-purple">Register</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../partials/footer.php'; ?> 