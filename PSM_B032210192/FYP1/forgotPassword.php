<?php
session_start();
require 'db_connection.php';

// Initialize messages
$message = '';
$message_type = ''; // Will be 'success' or 'danger' for Bootstrap alerts

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'];

    // SECURITY: Use a prepared statement to check if the customer exists
    $stmt = $conn->prepare("SELECT CUST_ID FROM customer WHERE CUST_EMAIL = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // --- User exists, so we proceed with token generation ---

        // Generate a cryptographically secure token
        $token = bin2hex(random_bytes(50));
        // Set the token to expire in 1 hour
        $expiration = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // SECURITY: Use a prepared statement to save the token and expiration date
        $update_stmt = $conn->prepare("UPDATE customer SET reset_token = ?, token_expiration = ? WHERE CUST_EMAIL = ?");
        $update_stmt->bind_param("sss", $token, $expiration, $email);
        $update_stmt->execute();

        // --- Send the reset email ---
        // IMPORTANT: Replace 'yourdomain.com' with your actual domain name.
        $reset_link = "http://yourdomain.com/reset_password.php?token=$token";
        
        $subject = "Password Reset Request for RY's Tasty Creations";
        $body = "
        <p>Hello,</p>
        <p>We received a request to reset the password for your account.</p>
        <p>Please click the link below to set a new password. This link is valid for 1 hour.</p>
        <p><a href='$reset_link'>Reset Your Password</a></p>
        <p>If you did not request a password reset, please ignore this email.</p>
        <p>Thanks,<br>The Team at RY's Tasty Creations</p>
        ";
        
        // To send HTML mail, the Content-type header must be set
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: no-reply@yourdomain.com" . "\r\n";

        // The mail() function is notoriously unreliable on many servers.
        // For a real application, consider using a library like PHPMailer.
        mail($email, $subject, $body, $headers);
    }
    
    // SECURITY BEST PRACTICE:
    // Always show a generic message to prevent attackers from discovering valid emails.
    $message = "If an account with that email exists, a password reset link has been sent.";
    $message_type = 'success';
    
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - RY's Tasty Creations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* This style block matches your other pages for a consistent look */
        body {
            background-image: url("bg.jpg");
        }
        .forgot-password-container {
            max-width: 450px;
            margin-top: 5rem;
        }
    </style>
</head>
<body>

<div class="container forgot-password-container">
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <h2 class="card-title text-center mb-2">Forgot Your Password?</h2>
            <p class="text-center text-muted mb-4">No problem. Enter your email below and we'll send you a reset link.</p>

            <!-- Display Success/Info Message -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> text-center">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <!-- Email Input -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your registered email" required>
                </div>

                <!-- Submit Button -->
                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Send Reset Link</button>
                </div>
            </form>

            <p class="text-center mt-3">
                Remembered your password? <a href="login.php">Back to Login</a>
            </p>
        </div>
    </div>
</div>

</body>
</html>