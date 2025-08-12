<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SHMS - Student Hostel Management System</title>
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
            overflow: visible;
            width: 75%;
            height: auto;
            margin: 20px auto;
        }

        .image-container {
            flex: 1.73;
            background-image: url('school-hostel-management-erp-system-software-online.jpg');
            background-size: contain;
            background-position: center;
            background-repeat: no-repeat;
        }

        .selection-container {
            flex: 1.2;
            padding: 40px 30px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
        }

        .selection-container h2 {
            margin-bottom: 30px;
            color: #25408f;
            text-align: center;
        }

        .user-options {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-top: 20px;
        }

        .user-card {
            background-color: #ffffff;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 30px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }

        .user-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .user-card.student {
            border-color: #28a745;
        }

        .user-card.student:hover {
            border-color: #218838;
            background-color: #f8fff9;
        }

        .user-card.staff {
            border-color: #25408f;
        }

        .user-card.staff:hover {
            border-color: #1a2f6b;
            background-color: #f8f9ff;
        }

        .card-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .card-title.student {
            color: #28a745;
        }

        .card-title.staff {
            color: #25408f;
        }

        .card-description {
            font-size: 14px;
            color: #666;
            line-height: 1.4;
        }

        .card-icon {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .welcome-text {
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .password-container {
            margin-top: 15px;
            display: none !important;
        }

        .password-container.show {
            display: block !important;
        }

        .password-input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .login-btn {
            background-color: #25408f;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .login-btn:hover {
            background-color: #1a2f6b;
        }

        .login-btn:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                height: auto;
                width: 90%;
            }
            
            .image-container {
                height: 200px;
            }
            
            .selection-container {
                padding: 30px 20px;
            }
            
            .title {
                font-size: 24px;
            }

            .user-card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <img src="https://portal.utem.edu.my/iclm/logoUTeM.png" alt="UTEM Logo">
        <div class="title">Student Hostel Management System</div>
    </div>

    <div class="container">
        <div class="image-container"></div>
        <div class="selection-container">
            <h2>Welcome to SHMS</h2>
            <p class="welcome-text">
                Please select your user type to proceed to the appropriate login page.
            </p>
            <div class="user-options">
                <a href="studMainPage.php" class="user-card student">
                    <div class="card-icon">👨‍🎓</div>
                    <div class="card-title student">Student Login</div>
                    <div class="card-description">Access your student portal for hostel applications, complaints, and more.</div>
                </a>
                <div class="user-card staff" onclick="showPasswordField()">
                    <div class="card-icon">👨‍💼</div>
                    <div class="card-title staff">Staff/Admin Login</div>
                    <div class="card-description">Manage hostel operations, applications, and administrative tasks.</div>
                    <div class="password-container" id="passwordContainer">
                        <input type="password" class="password-input" id="passwordInput" placeholder="Enter password" onkeypress="handlePasswordEnter(event)">
                        <button class="login-btn" onclick="checkPassword()">Login</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function showPasswordField() {
            const passwordContainer = document.getElementById('passwordContainer');
            passwordContainer.classList.add('show');
            document.getElementById('passwordInput').focus();
        }

        function handlePasswordEnter(event) {
            if (event.key === 'Enter') {
                checkPassword();
            }
        }

        function checkPassword() {
            const password = document.getElementById('passwordInput').value;
            if (password === 'UTEM2025') {
                window.location.href = 'staffMainPage.php';
            } else {
                alert('Incorrect password! Please try again.');
                document.getElementById('passwordInput').value = '';
                document.getElementById('passwordInput').focus();
            }
        }
    </script>
</body>
</html>
