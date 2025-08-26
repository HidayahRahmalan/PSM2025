<?php
session_start();
require 'db_connection.php';

if (isset($_POST['register'])) {
    // --- Get all required form data ---
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $password = $_POST['password'];

    // --- Password validation ---
    if (strlen($password) < 8) {
        $_SESSION['error'] = "Password must be at least 8 characters long.";
        header("Location: register.php");
        exit();
    }
    if (!preg_match('/[A-Za-z]/', $password)) {
        $_SESSION['error'] = "Password must contain at least one letter.";
        header("Location: register.php");
        exit();
    }
    if (!preg_match('/[0-9]/', $password)) {
        $_SESSION['error'] = "Password must contain at least one number.";
        header("Location: register.php");
        exit();
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $_SESSION['error'] = "Password must contain at least one special character.";
        header("Location: register.php");
        exit();
    }

    // Hash the password only after validation passes
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // --- Pre-check if email already exists ---
    $check_email_stmt = $conn->prepare("SELECT CUST_ID FROM customer WHERE CUST_EMAIL = ?");
    $check_email_stmt->bind_param("s", $email);
    $check_email_stmt->execute();
    $email_result = $check_email_stmt->get_result();
    if ($email_result->num_rows > 0) {
        $_SESSION['error'] = "Registration failed. An account with this email already exists.";
        header("Location: register.php");
        exit();
    }
    $check_email_stmt->close();

    // --- Pre-check if phone number already exists ---
    $check_phone_stmt = $conn->prepare("SELECT CUST_ID FROM customer WHERE CUST_PHONE = ?");
    $check_phone_stmt->bind_param("s", $phone);
    $check_phone_stmt->execute();
    $phone_result = $check_phone_stmt->get_result();
    if ($phone_result->num_rows > 0) {
        $_SESSION['error'] = "Registration failed. This phone number is already registered.";
        header("Location: register.php");
        exit();
    }
    $check_phone_stmt->close();

    // --- Generate a new CUST_ID ---
    $id_query = $conn->query("SELECT CUST_ID FROM customer ORDER BY CUST_ID DESC LIMIT 1");
    if ($id_query->num_rows > 0) {
        $last_row = $id_query->fetch_assoc();
        $last_id_num = (int) substr($last_row['CUST_ID'], 4);
        $new_id_num = $last_id_num + 1;
    } else {
        $new_id_num = 1;
    }
    $new_cust_id = 'CUST' . str_pad($new_id_num, 3, '0', STR_PAD_LEFT);
    $default_seller_id = 'S0001'; // Default seller assigned to all new customers


    // --- Prepare and execute the final INSERT statement ---
$stmt = $conn->prepare("INSERT INTO customer (CUST_ID, CUST_NAME, CUST_EMAIL, CUST_PASSWORD, CUST_PHONE, SELLER_ID) VALUES (?, ?, ?, ?, ?, ?)");

    
    if ($stmt) {
        $stmt->bind_param("ssssss", $new_cust_id, $name, $email, $password_hash, $phone, $default_seller_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Registration successful! You can now log in.";
            header("Location: Login.php");
            exit();
        } else {
            $_SESSION['error'] = "Registration failed due to a database error.";
            header("Location: register.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "An error occurred during registration preparation.";
        header("Location: register.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-image: url("bg.jpg");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh;
        }
        .register-container {
            max-width: 480px;
            margin-top: 4rem;
            margin-bottom: 4rem;
        }
        .card {
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(5px);
            border: none;
            border-radius: 15px;
        }
        .password-requirements {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.25rem;
        }
        .requirement-icon {
            margin-right: 0.5rem;
            width: 1rem;
            text-align: center;
        }
        .valid {
            color: #28a745;
        }
        .invalid {
            color: #dc3545;
        }
        #phone-feedback {
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>

<div class="container register-container">
    <div class="card shadow-lg">
        <div class="card-body p-4 p-md-5">
            <h2 class="card-title text-center fw-bold mb-4">Create Your Account</h2>

            <!-- Display Error Message if redirected back -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger text-center">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <form method="post" id="registrationForm">
                <!-- Full Name Input -->
                <div class="mb-3">
                    <label for="name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="name" name="name" placeholder="Enter your full name" required>
                </div>

                <!-- Email Input -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                </div>

                <!-- Phone Number Input -->
                <div class="mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="tel" class="form-control" id="phone" name="phone" placeholder="e.g., 012-3456789" required>
                    <div id="phone-feedback" class="text-muted">We'll check if this number is available when you submit the form</div>
                </div>

                <!-- Password Input -->
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Create a strong password" required>
                    <div class="password-requirements">
                        <div class="requirement">
                            <span class="requirement-icon" id="length-icon">✗</span>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="requirement">
                            <span class="requirement-icon" id="letter-icon">✗</span>
                            <span>At least one letter</span>
                        </div>
                        <div class="requirement">
                            <span class="requirement-icon" id="number-icon">✗</span>
                            <span>At least one number</span>
                        </div>
                        <div class="requirement">
                            <span class="requirement-icon" id="special-icon">✗</span>
                            <span>At least one special character</span>
                        </div>
                    </div>
                </div>

                <!-- Register Button -->
                <div class="d-grid mt-4">
                    <button type="submit" name="register" class="btn btn-primary btn-lg" id="registerButton">Register</button>
                </div>
            </form>

            <p class="text-center mt-3">
                Already have an account? <a href="login.php">Login here</a>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('password');
        const registerButton = document.getElementById('registerButton');
        
        // Icons for password requirements
        const lengthIcon = document.getElementById('length-icon');
        const letterIcon = document.getElementById('letter-icon');
        const numberIcon = document.getElementById('number-icon');
        const specialIcon = document.getElementById('special-icon');
        
        // Validate password function
        function validatePassword() {
            const password = passwordInput.value;
            let isValid = true;
            
            // Check length
            if (password.length >= 8) {
                lengthIcon.textContent = '✓';
                lengthIcon.classList.add('valid');
                lengthIcon.classList.remove('invalid');
            } else {
                lengthIcon.textContent = '✗';
                lengthIcon.classList.add('invalid');
                lengthIcon.classList.remove('valid');
                isValid = false;
            }
            
            // Check for letter
            if (/[A-Za-z]/.test(password)) {
                letterIcon.textContent = '✓';
                letterIcon.classList.add('valid');
                letterIcon.classList.remove('invalid');
            } else {
                letterIcon.textContent = '✗';
                letterIcon.classList.add('invalid');
                letterIcon.classList.remove('valid');
                isValid = false;
            }
            
            // Check for number
            if (/[0-9]/.test(password)) {
                numberIcon.textContent = '✓';
                numberIcon.classList.add('valid');
                numberIcon.classList.remove('invalid');
            } else {
                numberIcon.textContent = '✗';
                numberIcon.classList.add('invalid');
                numberIcon.classList.remove('valid');
                isValid = false;
            }
            
            // Check for special character
            if (/[^A-Za-z0-9]/.test(password)) {
                specialIcon.textContent = '✓';
                specialIcon.classList.add('valid');
                specialIcon.classList.remove('invalid');
            } else {
                specialIcon.textContent = '✗';
                specialIcon.classList.add('invalid');
                specialIcon.classList.remove('valid');
                isValid = false;
            }
            
            return isValid;
        }
        
        // Validate password on input
        passwordInput.addEventListener('input', validatePassword);
        
        // Basic phone format validation
        function validatePhoneFormat(phone) {
            // Simple validation - at least 8 digits, allowing hyphens
            const digits = phone.replace(/\D/g, '');
            return digits.length >= 8;
        }
        
        // Validate form on submit
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            if (!validatePassword()) {
                e.preventDefault();
                alert('Please ensure your password meets all the requirements.');
                return;
            }
            
            // Validate phone format
            const phone = document.getElementById('phone').value;
            if (!validatePhoneFormat(phone)) {
                e.preventDefault();
                alert('Please enter a valid phone number (at least 8 digits).');
                return;
            }
        });
    });
</script>

</body>
</html>