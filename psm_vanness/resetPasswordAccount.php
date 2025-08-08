<?php
session_start(); 

include 'dbConnection.php';

$UserID = $_SESSION['UserID']; 

$PasswordError = ""; // Error message for password validation
$updateMessage = ""; // Success or error message for updating
$confirmPasswordError = ""; // Error message for password confirmation

$sql = "SELECT UPassword FROM `USER` WHERE UserID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $UserID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    $originalPassword = $user['UPassword'];
} else {
    die("Error: Unable to fetch original password for user ID.");
}

$stmt->close();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userPassword = $_POST['password']; // First password entry
    $confirmPassword = $_POST['confirmPassword']; // Confirmed password entry

    $hasError = false;

    // Validation: Check if the new password matches the old password
    if ($userPassword === $originalPassword) {
        $PasswordError = "The new password cannot be the same as the current password.";
        $hasError = true;
    }

    // Validation: Check if the two entered passwords match
    if ($userPassword !== $confirmPassword) {
        $confirmPasswordError = "The passwords do not match. Please try again.";
        $hasError = true;
    }

    // Validation: Check password strength
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>]).{6,12}$/', $userPassword)) {
        $passwordError = "Password must be between 6 and 12 characters long, and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
        $hasError = true;
    }

    if (!$hasError) {
        // Update the password in the database
        $sql = "UPDATE `USER` SET UPassword = ? WHERE UserID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $userPassword, $UserID);

        if ($stmt->execute()) {
            echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    Swal.fire({
                        icon: "success",
                        title: "Reset Successful!",
                        text: "Redirecting to login...",
                        timer: 5000,
                        showConfirmButton: false,
                        background: "#222", // Dark popup background
                        color: "#fff", // White text
                        customClass: {
                            popup: "dark-popup",
                            backdrop: "blurred-backdrop"
                        },
                        backdrop: "url(\'loginBackgroundPic2.png\') center center / cover no-repeat" 
                    }).then(() => {
                        window.location.href = "login.php";
                    });
                });
            </script>';
            exit();
        } else {
            $updateMessage = "Error updating password: " . $conn->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Data Quality Monitoring</title>
    
    <style>
        body {
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            font-family: Arial, sans-serif;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('loginBackgroundPic2.png') no-repeat center center/cover;
            filter: blur(4px); /* Adjust blur intensity */
            z-index: -1; /* Keeps it behind content */
        }
        .title-container {
            position: absolute;
            text-align: center;
            opacity: 0;
            animation: fadeIn 1.5s ease-in-out forwards;
            color: white;
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
            min-height: 40px;
        }
        .error-text {
            font-size: 13px; 
            line-height: 1.4; /* Increase line spacing for better readability */
            margin-left: 6px; /* Space between icon and text */
            flex-grow: 1; /* Ensure text takes up available space */
            text-align: left;
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
            margin-right: 10px;
            min-width: 16px; 
            min-height: 16px;
            flex-shrink: 0; /* Prevents resizing when the error box expands */
        }
        .reset-form {
            background: rgba(8, 0, 0, 0.541);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            width: 300px;
            position: relative;
        }
        .back-arrow {
            position: absolute;
            top: 15px;
            left: 20px;
            font-size: 25px;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        .back-arrow:hover {
            color: #ff9800;
        }
        .reset-title {
            font-size: 20px;
            font-weight: bold;
            color: white;
            margin-bottom: 20px;
        }
        .reset-form .error {
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
        .reset-form button {
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
        .reset-form button:hover {
            background: #e68900;
        }
        .reset-container {
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
        .reset-container a {
            color: #ff9800;
            text-decoration: none;
            font-size: 16px;
        }
        .reset-container a:hover {
            text-decoration: underline;
        }
        /* Custom popup styling */
        .dark-popup {
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>
        <div class="title-container">
            <h1>Data Quality Monitoring System</h1>
        </div>
    </div>
        <div class="reset-form">
            <a href="verifySecurityQuestion.php" class="back-arrow">←</a>
            <div class="reset-title">Reset Password</div> 
            <form action="resetPasswordAccount.php" method="POST" onsubmit="return validateForm()">

                <div id="errorBox" class="error-box" style="display: none;">
                    <span class="error-icon">✖</span>
                    <p id="errorText" class="error-text"></p>
                </div>

                <div id="passwordErrorBox" class="error-box" style="display: <?php echo !empty($PasswordError) ? 'flex' : 'none'; ?>;">
                    <span class="error-icon">✖</span>
                    <p id="passwordErrorText" class="error-text"><?php echo $PasswordError; ?></p>
                </div>
                
                <label for="userPassword"></label>
                <div class="input-container">
                    <input type="password" id="password" name="password" placeholder="Password*" required>
                    <img src="eye-off.png" alt="Eye Icon" id="eye-icon" class="eye-icon" onclick="togglePassword()">
                    <span id="passwordError" class="error-message"></span>
                </div>

                <label for="confirmPassword"></label>
                <div class="input-container">
                    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password*" required>
                    <img src="eye-off.png" alt="Eye Icon" id="confirm-eye-icon" class="eye-icon" onclick="toggleConfirmPassword()">
                    <span id="confirmPasswordError" class="error-message"></span>
                </div>

                <button type="submit" name="submit">Submit</button>
            </form>
        </div>
    </div>

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
        function toggleConfirmPassword() {
            var confirmPasswordField = document.getElementById("confirmPassword");
            var eyeIcon = document.getElementById("confirm-eye-icon");
            if (confirmPasswordField.type === "password") {
                confirmPasswordField.type = "text";
                eyeIcon.src = "eye-on.png"; 
            } else {
                confirmPasswordField.type = "password";
                eyeIcon.src = "eye-off.png"; 
            }
        }

        document.addEventListener("DOMContentLoaded", function () {
            const passwordInput = document.querySelector("input[name='password']");
            const confirmPasswordInput = document.querySelector("input[name='confirmPassword']");
            const errorBox = document.getElementById("errorBox");
            const errorText = document.getElementById("errorText");
            const passwordErrorBox = document.getElementById("passwordErrorBox");

            if (passwordErrorBox && passwordErrorBox.style.display === "flex") {
                setTimeout(() => {
                    passwordErrorBox.style.opacity = "0";  // Fade out
                    setTimeout(() => {
                        passwordErrorBox.style.display = "none"; // Hide completely
                    }, 500); // Wait for fade-out effect
                }, 2000); // Hide after 2 seconds
            }

            let errorMessage = "";

            function showError(message) {
                errorText.textContent = message;
                errorBox.style.display = "flex"; // Show error box
            }

            function clearError() {
                errorText.textContent = "";
                errorBox.style.display = "none"; // Hide error box if no error
            }

            passwordInput.addEventListener("input", function () {
                const password = passwordInput.value;
                if (password === "") {
                    clearError(); // Clear error if input is empty
                } else if (!password.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>]).{6,12}$/)) {
                    showError("Password must be 6-12 characters, include uppercase, lowercase, number, and special character.");
                } else {
                    clearError();
                }
            });

            confirmPasswordInput.addEventListener("input", function () {
                if (confirmPasswordInput.value === "") {
                    clearError(); // Clear error if input is empty
                } else if (confirmPasswordInput.value !== passwordInput.value) {
                    showError("Passwords do not match.");
                } else {
                    clearError();
                }
            });

            // If there's an error, display it and prevent form submission
            if (errorMessage !== "") {
                errorText.textContent = errorMessage;
                errorBox.style.display = "flex";
                return false; // Prevent form submission
            } else {
                errorBox.style.display = "none"; // Hide error box if no error
                return true; // Allow form submission
            }
        });

        function validateForm() {
            const password = document.getElementById("password").value;
            const confirmPassword = document.getElementById("confirmPassword").value;
            const errorBox = document.getElementById("errorBox");
            const errorText = document.getElementById("errorText");

            let errorMessage = "";

            // Password Validation
            if (!password.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>]).{6,12}$/)) {
                errorMessage = "Password must be 6-12 characters, include uppercase, lowercase, number, and special character.";
            }

            // Confirm Password Validation
            else if (password !== confirmPassword) {
                errorMessage = "Passwords do not match.";
            }

            // If there's an error, show message and prevent form submission
            if (errorMessage !== "") {
                errorText.textContent = errorMessage;
                errorBox.style.display = "flex"; // Show error box
                return false; // Prevent form submission
            } else {
                errorBox.style.display = "none"; // Hide error box if no error
                return true; // Allow form submission
            }
        }


</script>
</body>
</html>