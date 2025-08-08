<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Staff</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f8ff;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 30px 0;
        }

        .back-arrow {
            align-self: flex-start;
            font-size: 22px;
            margin-left: 20px;
            cursor: pointer;
            text-decoration: none;
            color: #25408f;
            font-weight: bold;
        }

        .title {
            color: #25408f;
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }

        .container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 400px;
            text-align: left;
        }

        .step-title {
            font-size: 20px;
            color: #25408f;
            margin-bottom: 30px;
            text-align: center;
        }

        label {
            display: block;
            color: black;
            font-size: 16px;
            margin-top: 10px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 5px 0 15px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 15px;
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        .password-container input {
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #25408f;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
            font-size: 16px;
        }

        button:hover {
            background-color: #3883ce;
        }
        
        .error-message {
            color: red;
            font-size: 14px;
            margin-top: -10px;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .success-message {
            color: green;
            font-size: 14px;
            margin-top: -10px;
            margin-bottom: 10px;
            text-align: center;
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }

        .verification-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>
    <a href="staffMainPage.php" class="back-arrow">&#8592; Back</a>
    <div class="title">Reset Password</div>

    <div class="container">
        <!-- Step 1: Email verification -->
        <div id="step1" class="step active">
            <div class="step-title">Enter Your Email Address</div>
            <div id="step1-message" class="error-message"></div>
            <form id="emailForm">
                <label for="email">Staff Email:</label>
                <input type="email" id="email" name="email" placeholder="example@utem.edu.my" 
                       required pattern="[a-zA-Z0-9._%+-]+@utem\.edu\.my" autocomplete="off">
                
                <button type="button" id="sendVerificationBtn">Send Verification Code</button>
            </form>
        </div>

        <!-- Step 2: Verification code -->
        <div id="step2" class="step">
            <div class="step-title">Enter Verification Code</div>
            <div class="verification-info">
                A verification code has been sent to your email. Please check your inbox and enter the code below.
            </div>
            <div id="step2-message" class="error-message"></div>
            <form id="verificationForm">
                <label for="verificationCode">Verification Code:</label>
                <input type="text" id="verificationCode" name="verificationCode" 
                       placeholder="Enter 6-digit code" required maxlength="6" autocomplete="off">
                
                <button type="button" id="verifyCodeBtn">Verify Code</button>
            </form>
        </div>

        <!-- Step 3: Reset password -->
        <div id="step3" class="step">
            <div class="step-title">Create New Password</div>
            <div id="step3-message" class="error-message"></div>
            <form id="resetPasswordForm">
                <label for="newPassword">New Password:</label>
                <div class="password-container">
                    <input type="password" id="newPassword" name="newPassword" 
                           placeholder="Create new password" required>
                    <span class="toggle-password" onclick="togglePassword('newPassword')">👁️</span>
                </div>
                
                <label for="confirmPassword">Confirm Password:</label>
                <div class="password-container">
                    <input type="password" id="confirmPassword" name="confirmPassword" 
                           placeholder="Confirm new password" required>
                    <span class="toggle-password" onclick="togglePassword('confirmPassword')">👁️</span>
                </div>
                
                <button type="button" id="resetPasswordBtn">Reset Password</button>
            </form>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(inputId) {
            const passwordField = document.getElementById(inputId);
            passwordField.type = passwordField.type === "password" ? "text" : "password";
        }
        
        // Variables to store data between steps
        let staffEmail = '';
        let verificationToken = '';
        
        // Event listener for sending verification code
        document.getElementById('sendVerificationBtn').addEventListener('click', function() {
            const email = document.getElementById('email').value.trim();
            const messageDiv = document.getElementById('step1-message');
            
            // Clear previous messages
            messageDiv.textContent = '';
            
            // Validate email format - UTeM staff email pattern
            const emailPattern = /^[a-zA-Z0-9._%+-]+@utem\.edu\.my$/i;
            if (!email || !emailPattern.test(email)) {
                messageDiv.textContent = 'Please enter a valid UTeM staff email.';
                return;
            }
            
            // Show loading message
            messageDiv.textContent = 'Checking email...';
            messageDiv.className = 'success-message';
            
            // Create form data to send
            const formData = new FormData();
            formData.append('email', email);
            formData.append('action', 'checkEmail');
            
            fetch('staffProcessForgotPwd.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Email exists, save email and show verification step
                    staffEmail = email;
                    messageDiv.textContent = '';
                    
                    // Show success message temporarily
                    messageDiv.textContent = 'Verification code sent to your email.';
                    messageDiv.className = 'success-message';
                    
                    // Move to step 2 after a short delay
                    setTimeout(function() {
                        document.getElementById('step1').classList.remove('active');
                        document.getElementById('step2').classList.add('active');
                        document.getElementById('step2-message').textContent = '';
                    }, 3000);
                } else {
                    // Email doesn't exist
                    messageDiv.textContent = data.message || 'Email not found. Please check and try again.';
                    messageDiv.className = 'error-message';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.textContent = 'An error occurred. Please try again later.';
                messageDiv.className = 'error-message';
            });
        });
        
        // Event listener for verifying code
        document.getElementById('verifyCodeBtn').addEventListener('click', function() {
            const verificationCode = document.getElementById('verificationCode').value.trim();
            const messageDiv = document.getElementById('step2-message');
            
            // Clear previous messages
            messageDiv.textContent = '';
            
            // Basic validation
            if (!verificationCode || verificationCode.length !== 6) {
                messageDiv.textContent = 'Please enter the 6-digit verification code.';
                messageDiv.className = 'error-message';
                return;
            }
            
            // Show loading message
            messageDiv.textContent = 'Verifying code...';
            messageDiv.className = 'success-message';
            
            // Create form data to send
            const formData = new FormData();
            formData.append('email', staffEmail);
            formData.append('verificationCode', verificationCode);
            formData.append('action', 'verifyCode');
            
            fetch('staffProcessForgotPwd.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Verification successful, save token and proceed to password reset
                    verificationToken = data.token;
                    messageDiv.textContent = '';
                    
                    // Move to step 3
                    document.getElementById('step2').classList.remove('active');
                    document.getElementById('step3').classList.add('active');
                    document.getElementById('step3-message').textContent = '';
                } else {
                    // Invalid verification code
                    messageDiv.textContent = data.message || 'Invalid verification code. Please try again.';
                    messageDiv.className = 'error-message';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.textContent = 'An error occurred. Please try again later.';
                messageDiv.className = 'error-message';
            });
        });
        
        // Event listener for resetting password
        document.getElementById('resetPasswordBtn').addEventListener('click', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const messageDiv = document.getElementById('step3-message');
            
            // Clear previous messages
            messageDiv.textContent = '';
            
            // Validate passwords
            if (!newPassword || !confirmPassword) {
                messageDiv.textContent = 'Please enter and confirm your new password.';
                messageDiv.className = 'error-message';
                return;
            }
            
            if (newPassword !== confirmPassword) {
                messageDiv.textContent = 'Passwords do not match. Please try again.';
                messageDiv.className = 'error-message';
                return;
            }
            
            // Password complexity validation
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.+-/_#])[A-Za-z\d@$!%*?&.+-/_#]{8,}$/;
            if (!passwordRegex.test(newPassword)) {
                messageDiv.textContent = "Password must be at least 8 characters, contain at least one lowercase, one uppercase, one special character(@$!%*?&.+-/_#), and one number.";
                messageDiv.className = 'error-message';
                return;
            }
            
            // Show loading message
            messageDiv.textContent = 'Updating password...';
            messageDiv.className = 'success-message';
            
            // Create form data to send
            const formData = new FormData();
            formData.append('email', staffEmail);
            formData.append('newPassword', newPassword);
            formData.append('token', verificationToken);
            formData.append('action', 'resetPassword');
            
            fetch('staffProcessForgotPwd.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Password reset successful
                    messageDiv.textContent = 'Password reset successful! Redirecting to login page...';
                    messageDiv.className = 'success-message';
                    
                    // Redirect to login page after a delay
                    setTimeout(function() {
                        window.location.href = 'staffMainPage.php';
                    }, 3000);
                } else {
                    // Password reset failed
                    messageDiv.textContent = data.message || 'Failed to reset password. Please try again.';
                    messageDiv.className = 'error-message';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.textContent = 'An error occurred. Please try again later.';
                messageDiv.className = 'error-message';
            });
        });
    </script>
</body>
</html> 