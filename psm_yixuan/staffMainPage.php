<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login SHMS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f8ff;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 30px 0; /* Balanced vertical padding */
        }

        .header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
            max-width: 90%;
        }

        .header img {
            height: 50px;
        }

        .title {
            color: #25408f;
            font-size: 30px;
            font-weight: bold;
            white-space: nowrap;
        }

        .container {
            display: flex;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 75%;
            height: 65vh;
            margin: 20px auto;
        }

        .image-container {
            flex: 1.73;
            background-image: url('utemsatria.jpg');
            background-size: cover;
            background-position: -1% center;
        }

        .login-container {
            flex: 1.2;
            padding: 40px 30px;
            text-align: left;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-container h2 {
            margin-bottom: 30px;
            color: #25408f;
            text-align: center;
        }

        label {
            color: black;
            font-size: 16px;
        }

        .login-container input {
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

        .login-container button {
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

        .login-container button:hover {
            background-color: #3883ce;
        }

        .links-container {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .links-container :hover {
            text-decoration: underline;
        }

        .links-container a {
            color: #25408f;
            text-decoration: none;
            padding: 0 10px;
            position: relative;
        }

        .links-container a:not(:last-child)::after {
            content: "|";
            position: absolute;
            right: -10px;
            color: #ccc;
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
    </style>
</head>
<body>
    <div class="header">
        <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/LogoUTeM-2016.jpg" alt="UTeM Logo">
        <div class="title">Smart Hostel Management System (SHMS)</div>
    </div>

    <div class="container">
        <div class="image-container"></div>
        <div class="login-container">
            <h2>Welcome UTeM Staff</h2>
            
            <form id="loginForm">
                <label for="email">Staff Email:</label>
                <input type="email" id="email" name="email" placeholder="example@utem.edu.my" required pattern="^[a-zA-Z0-9][a-zA-Z0-9._%+-]+@utem\.edu\.my$" autocomplete="off">
                
                <label for="password">Password:</label>
                <div class="password-container">
                    <input type="password" id="password" name="password" placeholder="Enter your password" required maxlength="16">
                    <span class="toggle-password" onclick="togglePassword()">👁️</span>
                </div>
                
                <div id="message" class="error-message"></div>
                
                <button type="button" id="loginBtn">Login</button>

                <div class="links-container">
                    <a href="staffForgotPwd.php">Forgot Password?</a>
                    <a href="staffReg.php">Create New Account</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordField = document.getElementById("password");
            passwordField.type = passwordField.type === "password" ? "text" : "password";
        }
        
        document.getElementById('loginBtn').addEventListener('click', function() {
            // Get form data
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const messageDiv = document.getElementById('message');
            
            // Clear previous messages
            messageDiv.textContent = '';
            messageDiv.className = 'error-message';
            
            // Validate fields
            if (!email || !password) {
                messageDiv.textContent = 'Please enter both email and password.';
                return;
            }
            
            // Show loading message
            messageDiv.textContent = 'Logging in...';
            
            // Create form data to send
            const formData = new FormData();
            formData.append('email', email);
            formData.append('password', password);
            
            fetch('staffProcessLogin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Log raw response for debugging
                response.clone().text().then(text => {
                    console.log('Raw server response:', text);
                });
                
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Login successful
                    messageDiv.textContent = 'Login successful! Redirecting...';
                    messageDiv.className = 'success-message';
                    
                    // Redirect to home page with empId and role
                    setTimeout(function() {
                        window.location.href = 'staffHomePage.php';
                    }, 1500);
                } else {
                    // Login failed
                    messageDiv.textContent = data.message;
                    messageDiv.className = 'error-message';
                }
            })
            .catch(error => {
                console.error('Error details:', error);
                messageDiv.textContent = 'An error occurred during login. Please try again.';
                messageDiv.className = 'error-message';
                
                // Add debugging info in development
                const debugInfo = document.createElement('div');
                debugInfo.style.fontSize = '12px';
                debugInfo.style.marginTop = '5px';
                debugInfo.style.color = '#666';
                debugInfo.textContent = 'Error: ' + error.message;
                messageDiv.appendChild(debugInfo);
            });
        });
    </script>
</body>
</html>
