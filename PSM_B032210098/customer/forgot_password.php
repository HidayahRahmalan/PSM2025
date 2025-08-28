<?php
session_start();
require_once '../db_connection.php';
require_once '../PHPMailer/PHPMailer.php';
require_once '../PHPMailer/SMTP.php';
require_once '../PHPMailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    // Rate limiting: prevent more than 1 request per minute per session
    if (isset($_SESSION['last_forgot_pw']) && time() - $_SESSION['last_forgot_pw'] < 60) {
        $message = 'If your email is registered, you will receive an email with your login details soon.';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'If your email is registered, you will receive an email with your login details soon.';
    } else {
        // Check if email exists in database
        $stmt = $pdo->prepare('SELECT customer_ID, customer_username FROM customers WHERE customer_email = ?');
        $stmt->execute([$email]);
        $customer = $stmt->fetch();
        if ($customer) {
            // Generate a temporary password
            $temp_password = bin2hex(random_bytes(4)); // 8-character temp password
            $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
            // Update the password in the database
            $stmt = $pdo->prepare('UPDATE customers SET customer_password_hash = ? WHERE customer_ID = ?');
            $stmt->execute([$password_hash, $customer['customer_ID']]);
            // Send email with username and temp password
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'rayn2309@gmail.com'; // Your Gmail address
                $mail->Password = 'rswkomrtxirvjjrc'; // Your Gmail app password
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->setFrom('rayn2309@gmail.com', 'PS4 Rental System');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Your PS4 Rental System Login Details';
                $mail->Body    = "<p>Your username: <strong>{$customer['customer_username']}</strong></p>"
                    . "<p>Your temporary password: <strong>{$temp_password}</strong></p>"
                    . "<p>Please log in and change your password immediately for security.</p>";
                $mail->send();
            } catch (Exception $e) {
                // Optionally log $mail->ErrorInfo
            }
        }
        $_SESSION['last_forgot_pw'] = time();
        $message = 'If your email is registered, you will receive an email with your login details soon.';
    }
}
?>

<?php require_once '../partials/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-purple text-white text-center py-3">
                    <h3 class="mb-0"><i class="fas fa-key"></i> Forgot Password</h3>
                </div>
                <div class="card-body p-4">
                    <p>Enter your email address and we'll send you your username and a temporary password.</p>
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                    <?php endif; ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <button type="submit" class="btn btn-purple w-100">Send Reset Link</button>
                    </form>
                    <div class="text-center mt-3">
                        <a href="login.php">Back to Login</a> |
                        <a href="register.php">Create New Account</a> |
                        <a href="forgot_username.php">Forgot Username?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?> 