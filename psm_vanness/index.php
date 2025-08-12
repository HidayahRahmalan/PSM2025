<?php
date_default_timezone_set('Asia/Kuala_Lumpur'); 

session_start();  

include 'dbConnection.php'; 

$error_msg = '';
if (isset($_GET['Error']) && $_GET['Error'] === 'session_timeout') {
    $error = 'Your session already expired. Please log in again.';
}

$max_attempts = 3;  
$lock_time = 1;     // Lock duration in minutes

$error = "";

$_SESSION['securityAnswer'] = ""; // Clear previous security answer
$_SESSION['securityQuestion'] = ""; // Clear previous security question

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // Fetch current failed attempts
    $sql = "SELECT UserID, USessionToken, UFailedAttempts, UIsLocked, ULastFailedAttempt FROM `USER` WHERE BINARY UEmail = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    $failedAttempts = 0;
    $isLocked = 0;
    $lastFailedAttempt = 0;

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $UserID = $user['UserID'];
        $sessionToken = $user['USessionToken'];
        $failedAttempts = $user['UFailedAttempts'];
        $isLocked = $user['UIsLocked'];
        $lastFailedAttempt = strtotime($user['ULastFailedAttempt']);

        $currentTime = time();
        $time_difference = ($currentTime - $lastFailedAttempt); // in seconds
        $remaining_lock_time = max(0, ($lock_time * 60) - $time_difference);
        $remaining_minutes = ceil($remaining_lock_time / 60);

        // Check if account is locked
        if ($isLocked == 1) {
            if ($time_difference > ($lock_time * 60)) {
                // Unlock account if the lock time has passed
                $stmt = $conn->prepare("UPDATE `USER` SET UIsLocked = 0, UFailedAttempts = 0 WHERE UEmail = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
            } else {
                $error = "Account is still locked for $remaining_minutes minutes.";
            }
        }
        
        if (isset($sessionToken) && $sessionToken !== '') {
            $error = "You are already logged in from another device or browser.";

            $stmt = $conn->prepare("INSERT INTO audit_logs (UserID, action, session_token, created_at)
            VALUES (?, 'Session Conflict', ?, NOW())");
            $stmt->bind_param("ss",$UserID, $sessionToken);
            $stmt->execute();
        }        

        // Show error and stop login if account is still locked
        if (empty($error)) {
            
            // Proceed with normal login process
            $sql = "SELECT * FROM `USER` WHERE BINARY UEmail = ? AND BINARY UPassword = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $email, $password);
            $stmt->execute();
            $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();

                    // Reset failed attempts after Successful login
                    $stmt = $conn->prepare("UPDATE `USER` SET UFailedAttempts = 0 WHERE UEmail = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();

                    // Store the user data in the session
                    $_SESSION['UserID'] = $user['UserID'];
                    $_SESSION['UEmail'] = $user['UEmail'];
                    $_SESSION['URole'] = $user['URole'];
                    $_SESSION['UName'] = $user['UName'];

                    // Check if it's the first login
                    if ($user['UFirstLogin'] == 1 && $user['URole'] == 'ADMIN') {
                        header("Location: resetPassword.html");
                        exit();
                    } else {
                        $session_token = bin2hex(random_bytes(32)); // Generate a random session token
                        $_SESSION['session_token'] = $session_token; // Store in PHP session

                        // Update last login timestamp and session token (this triggers audit log)
                        $stmt = $conn->prepare("UPDATE USER SET ULastLogin = NOW(), ULastActive = NOW(), USessionToken = ? WHERE UserID = ?");
                        $stmt->bind_param("ss", $session_token, $user['UserID']);
                        $stmt->execute();

                        if($user['URole'] == 'ADMIN'){
                            header("Location: adminMainPage.php");
                            exit();
                        }else if ($user['URole'] == 'USER'){
                            header("Location: userMainPage.php");
                            exit();
                        }
                        
                    }
                } else {
                    // Wrong password
                    $stmt = $conn->prepare("UPDATE `USER` SET UFailedAttempts = UFailedAttempts + 1, ULastFailedAttempt = NOW() WHERE UEmail = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();

                    // Fetch updated failedAttempts
                    $stmt = $conn->prepare("SELECT UFailedAttempts FROM `USER` WHERE BINARY UEmail = ?");
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $user = $result->fetch_assoc();
                    $failedAttempts = $user['UFailedAttempts'];

                    if ($failedAttempts >= $max_attempts) {
                        // Lock the account
                        $stmt = $conn->prepare("UPDATE `USER` SET UIsLocked = 1 WHERE UEmail = ?");
                        $stmt->bind_param("s", $email);
                        $stmt->execute();
                        $error = "Failed attempts already 3 times. Your account is locked for $lock_time minutes.";
                    } else {
                        $error = "Login failed. Attempt $failedAttempts of $max_attempts.";
                    }

                }
                $stmt->close();
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Data Quality Monitoring</title>
    <style>
        body {
            margin: 0;
            display: flex;
            height: 100vh;
            font-family: Arial, sans-serif;
            background: url('loginBackgroundPic2.png') no-repeat center center/cover;
        }
        .left-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .title-container {
            position: absolute;
            text-align: center;
            opacity: 0;
            animation: fadeIn 1.5s ease-in-out forwards;
            color: white;
        }
        .right-section {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 20px;
            flex-direction: column;
        }
        .error-box {
            background-color: rgba(9, 0, 0, 0.69);
            color:  #ff9800;
            padding: 5px;
            border: 1px solid grey;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
            width: 95%;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }
        .error-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            background-color: #ff9800;
            color: white;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            line-height: 16px;
            border-radius: 50%; /* Circle shape */
            margin-right: 6px;
            min-width: 16px; 
            min-height: 16px;
            flex-shrink: 0; /* Prevents resizing when the error box expands */
        }
        .error-text {
            font-size: 13px; 
            line-height: 1.4; /* Increase line spacing for better readability */
            margin-left: 6px; /* Space between icon and text */
            flex-grow: 1; /* Ensure text takes up available space */
            text-align: left;
        }
        .login-form {
            background: rgba(8, 0, 0, 0.541);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            width: 300px;
        }
        .login-form .error {
            color: red;
            font-size: 14px;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        .input-container {
            position: relative;
            width: 90%;
        }
        .input-container input {
            width: 85%;
            padding: 10px;
            padding-left: 35px;
            padding-right: 35px;
            margin: 10px 0;
            border: none;
            border-radius: 4px;
        }
        .input-container img {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
        }
        .eye-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .login-form button {
            width: 100%;
            padding: 10px;
            background: #ff9800;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            margin: 10px 0;
            font-size: 19px;
        }
        .login-form button:hover {
            background: #e68900;
        }
        .forgot-password-link {
            display: block;
            margin-top: 10px;
            color: #fffffe;
            text-decoration: none;
            font-size: 14px;
        }
        .forgot-password-link:hover {
            text-decoration: underline;
        }
        .create-account-container {
            background: rgba(8, 0, 0, 0.541);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            width: 300px;
            margin-top: 20px;
            margin-top: 10px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .create-account-container a {
            color: #ff9800;
            text-decoration: none;
            font-size: 16px;
        }
        .create-account-container a:hover {
            text-decoration: underline;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
    </style>
    <script>
        function togglePassword() {
            var passwordField = document.getElementById("password");
            var eyeIcon = document.getElementById("eye-icon");
            if (passwordField.type === "password") {
                passwordField.type = "text";
                eyeIcon.src = "eye-on.png"; 
            } else {
                passwordField.type = "password";
                eyeIcon.src = "eye-off.png"; 
            }
        }
    </script>
</head>
<body>
    <div class="left-section">
        <div class="title-container">
            <h1>Data Quality Monitoring System</h1>
        </div>
    </div>
    <div class="right-section">
        <div class="login-form">
            <!--<form action="login.php" method="POST">-->
            <form action="index.php" method="POST">

                <?php if (!empty($error)): ?>
                    <div class="error-box">
                        <span class="error-icon">✖</span>
                        <!--<p><?php echo $error; ?></p>-->
                        <p class="error-text"><?php echo $error; ?></p>
                    </div>
                <?php endif; ?>

                <div class="input-container">
                    <img src="email.png" alt="Email Icon">
                    <input type="text" name="email" placeholder="Email address" required>
                </div>
                
                <div class="input-container">
                    <input type="password" id="password" name="password" placeholder="Password" required>
                    <img src="eye-off.png" alt="Eye Icon" id="eye-icon" class="eye-icon" onclick="togglePassword()">
                </div>

                <button type="submit" name="login">Log in</button>
            </form>
        
            <a href="findResetPasswordAccount.php" class="forgot-password-link">Reset password</a>
            </div>
        
            <div class="create-account-container">
                <p>No account? <a href="signUp.php" class="create-account-link">Create an account</a></p>
        </div>
    </div>
    <script>
        setTimeout(function () {
            var errorBox = document.querySelector(".error-box");
            if (errorBox) {
                errorBox.style.display = "none";
            }
        }, 5000); // Hide after 2 seconds (2000ms)
    </script>

</body>
</html>
