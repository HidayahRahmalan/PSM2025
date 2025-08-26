<?php
session_start();
require 'db_connection.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    $result = $conn->query("SELECT * FROM customer WHERE reset_token='$token' AND token_expiration > NOW()");
    
    if ($result->num_rows == 0) {
        $msg = "Invalid or expired token.";
    } else {
        // Show password reset form
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $new_password = password_hash($_POST['password'], PASSWORD_BCRYPT);
            // Update the password
            $conn->query("UPDATE customer SET CUST_PASSWORD='$new_password', reset_token=NULL, token_expiration=NULL WHERE reset_token='$token'");

            $msg = "Password reset successfully!";
        }
    }
} else {
    $msg = "No reset token provided.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password - RY's Tasty Creations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5">
    <h2 class="text-center">Reset Password</h2>

    <?php if (isset($msg)) echo "<div class='alert alert-info text-center'>$msg</div>"; ?>
    
    <!-- Password Reset Form -->
    <form method="POST" class="mx-auto mt-4" style="max-width: 400px;">
        <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
    </form>
</div>
</body>
</html>
