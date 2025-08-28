<?php
session_start();
require_once '../db_connection.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare('SELECT customer_username FROM customers WHERE customer_email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $message = "If your email is registered, you will receive an email with your username.";
        if ($user) {
            // In production, send email with username here
            // mail($email, "Your Username", "Your username is: " . $user['customer_username']);
        }
    } else {
        $message = "If your email is registered, you will receive an email with your username.";
    }
}
?>

<?php require_once '../partials/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-purple text-white text-center py-3">
                    <h3 class="mb-0"><i class="fas fa-user"></i> Forgot Username</h3>
                </div>
                <div class="card-body p-4">
                    <p>Enter your email address and we'll send you your username if it is registered.</p>
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-purple w-100">Send Username</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="login.php">Back to Login</a> |
                        <a href="register.php">Create New Account</a> |
                        <a href="forgot_password.php">Forgot Password?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?> 