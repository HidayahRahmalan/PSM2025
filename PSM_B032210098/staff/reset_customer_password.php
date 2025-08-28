<?php
session_start();
require_once '../db_connection.php';

// Check if staff is logged in
if (!isset($_SESSION['staff_ID']) || $_SESSION['user_type'] !== 'staff') {
    header('Location: staff_login.php');
    exit();
}

$message = '';
$error = '';
$customers = [];

// Get all customers for dropdown
$stmt = $pdo->prepare('SELECT customer_ID, customer_username, customer_full_name, customer_email FROM customers ORDER BY customer_username');
$stmt->execute();
$customers = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer_id = $_POST['customer_id'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($customer_id)) {
        $error = 'Please select a customer.';
    } elseif (empty($new_password)) {
        $error = 'New password is required.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Hash new password
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password and clear any existing reset tokens
        $stmt = $pdo->prepare('UPDATE customers SET customer_password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE customer_ID = ?');
        if ($stmt->execute([$password_hash, $customer_id])) {
            $message = 'Customer password has been reset successfully.';
        } else {
            $error = 'Failed to reset password. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Customer Password - Staff Dashboard</title>
    <link rel="stylesheet" href="../css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-key"></i> Reset Customer Password</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-success"><?php echo $message; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="customer_id" class="form-label">Select Customer</label>
                                <select class="form-select" id="customer_id" name="customer_id" required>
                                    <option value="">Choose a customer...</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo htmlspecialchars($customer['customer_ID']); ?>">
                                            <?php echo htmlspecialchars($customer['customer_username']); ?> 
                                            (<?php echo htmlspecialchars($customer['customer_full_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                <div class="form-text">Password must be at least 6 characters long.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Reset Password
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 