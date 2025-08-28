<?php
session_start();
require_once '../db_connection.php';

$error = '';
$success = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: login.php?error=invalid_reset_link');
    exit();
}

// Verify token and check if it's not expired
$stmt = $pdo->prepare('SELECT customer_ID, customer_username FROM customers WHERE reset_token = ? AND reset_expires > NOW()');
$stmt->execute([$token]);
$customer = $stmt->fetch();

if (!$customer) {
    header('Location: login.php?error=invalid_or_expired_token');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        if (empty($password)) {
            $error = 'Password is required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif (!preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password) ||
            !preg_match('/[\W]/', $password)) {
            $error = 'Password must contain uppercase, lowercase, number, and special character.';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE customers SET customer_password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE customer_ID = ?');
            if ($stmt->execute([$password_hash, $customer['customer_ID']])) {
                session_regenerate_id(true);
                $success = 'Password has been reset successfully. You can now login with your new password.';
            } else {
                $error = 'Failed to reset password. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - PS4 Rental System</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <div class="auth-form">
            <h2>Reset Password</h2>
            <p>Hello <?php echo htmlspecialchars($customer['customer_username']); ?>, please enter your new password.</p>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <br><br>
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <div class="auth-links">
                <a href="login.php">Back to Login</a>
                <a href="forgot_password.php">Request New Reset Link</a>
            </div>
        </div>
    </div>
</body>
</html> 