<?php
include 'dbConnection.php';  

// Initialize error variables
$emailError = $passwordError = $confirmPasswordError = $emailExistError ="";

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = strtoupper($_POST['name']);
    $email = strtolower($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];
    $securityQuestion = $_POST['securityQuestion'];
    $securityAnswer = strtoupper($_POST['securityAnswer']);

    // Validate password (min 6, max 12 characters, with at least 1 uppercase, 1 lowercase, 1 number, 1 special character)
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>]).{6,12}$/', $password)) {
        $passwordError = "Password must be between 6 and 12 characters long, and contain at least one uppercase letter, one lowercase letter, one number, and one special character.";
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailError = "Please enter a valid email address.";
    }

    if ($password !== $confirmPassword) {
        $confirmPasswordError = "Passwords do not match.";
    }

    // If no errors, insert data into the database
    if (!$passwordError && !$emailError && !$confirmPasswordError) {

       
            $firstLoginFlag = 0; 
            $role = 'USER';

            $stmt = $conn->prepare("CALL InsertUser(?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssi", $name, $email, $role, $password, $securityQuestion, $securityAnswer, $firstLoginFlag);

            try{
                $stmt->execute();
                    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
                    echo '<script>
                        document.addEventListener("DOMContentLoaded", function() {
                            Swal.fire({
                                icon: "success",
                                title: "Sign Up Successful!",
                                text: "Redirecting to login...",
                                timer: 2300,
                                showConfirmButton: false,
                                background: "#222", // Dark popup background
                                color: "#fff", // White text
                                customClass: {
                                    popup: "dark-popup",
                                    backdrop: "blurred-backdrop"
                                },
                                backdrop: "url(\'loginBackgroundPic2.png\') center center / cover no-repeat" 
                            }).then(() => {
                                window.location.href = "index.php";
                            });
                        });
                    </script>';
                    exit();
            }catch (mysqli_sql_exception $e) {
                if ($e->getCode() == 1062) { // Duplicate entry error code
                    $emailExistError = "The email is already registered. Please change to another.";
                } else {
                    // Handle other errors if necessary
                    $error = "Error: " . $e->getMessage();
                }
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
    <title>Sign Up - Data Quality Monitoring</title>
    
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
        .signup-form {
            background: rgba(8, 0, 0, 0.541);
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            width: 300px;
        }
        .signup-form .error {
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
        /* Style for select fields inside .select-container */
        .select-container {
            position: relative;
            width: 100%;
        }
        .select-container select {
            width: 100%;
            padding: 10px;
            padding-left: 35px;
            border: none;
            border-radius: 4px;
            background-color: white;
            font-size: 13px;
            appearance: none;  /* Remove default dropdown arrow */
            -webkit-appearance: none;
            -moz-appearance: none;
            margin: 10px 0;
            cursor: pointer;
        }
        /* Left-side icon inside select field */
        .select-container .select-icon {
            position: absolute;
            left: 9px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
        }
        /* Custom dropdown arrow */
        .select-container::after {
            content: "▼"; 
            font-size: 14px;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none; /* Prevents interfering with clicks */
            color: #555;
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
        .signup-form button {
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
        .signup-form button:hover {
            background: #e68900;
        }
        .login-container {
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
        .login-container a {
            color: #ff9800;
            text-decoration: none;
            font-size: 16px;
        }
        .login-container a:hover {
            text-decoration: underline;
        }
        /* Custom popup styling */
        .dark-popup {
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.2);
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
</head>
<body>
    <div class="left-section">
        <div class="title-container">
            <h1>Data Quality Monitoring System</h1>
        </div>
    </div>
    <div class="right-section">
        <div class="signup-form">
        <form action="signUp.php" method="POST" onsubmit="return validateForm()">

                <!-- Error Box -->
                <div id="errorBox" class="error-box" style="display: none;">
                    <span class="error-icon">✖</span>
                    <p id="errorText" class="error-text"></p>
                </div>

                <div id="emailExistErrorBox" class="error-box" style="display: <?php echo !empty($emailExistError) ? 'flex' : 'none'; ?>;">
                    <span class="error-icon">✖</span>
                    <p id="emailExistErrorText" class="error-text"><?php echo $emailExistError; ?></p>
                </div>

                <div class="input-container">
                    <img src="name1.png" alt="User Icon">
                    <input type="text" name="name" placeholder="Full Name*" required>
                </div>

                <div class="input-container">
                    <img src="email.png" alt="Email Icon">
                    <input type="text" name="email" placeholder="Email address*" required>
                    <span id="emailError" class="error-message"></span>
                </div>
                
                <div class="input-container">
                    <input type="password" id="password" name="password" placeholder="Password*" required>
                    <img src="eye-off.png" alt="Eye Icon" id="eye-icon" class="eye-icon" onclick="togglePassword()">
                    <span id="passwordError" class="error-message"></span>
                </div>

                <div class="input-container">
                    <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password*" required>
                    <img src="eye-off.png" alt="Eye Icon" id="confirm-eye-icon" class="eye-icon" onclick="toggleConfirmPassword()">
                    <span id="confirmPasswordError" class="error-message"></span>
                </div>
                
                <div class="input-container select-container">
                <img src="sq1.png" alt="Security Icon" class="select-icon">
                    <select name="securityQuestion" required>
                        <option value="" disabled selected>Select Security Question*</option>
                        <option value="What was the first programming language you learned?">What was the first programming language you learned?</option>
                        <option value="What tool did you use in your first data analysis job?">What tool did you use in your first data analysis job?</option>
                        <option value="What is the name of the first dataset you worked on?">What is the name of the first dataset you worked on?</option>
                    </select>
                </div>

                <div class="input-container">
                    <img src="sa.png" alt="Email Icon">
                    <input type="text" name="securityAnswer" placeholder="Security Answer*" required>
                </div>

                <button type="submit" name="signup">Sign Up</button>
            </form>
        </div>

            <div class="login-container">
                <!--<p>Already have an account? <a href="login.php" class="login-link">Log in</a></p>-->
                <p>Already have an account? <a href="index.php" class="login-link">Log in</a></p>
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
            const emailInput = document.querySelector("input[name='email']");
            const passwordInput = document.querySelector("input[name='password']");
            const confirmPasswordInput = document.querySelector("input[name='confirmPassword']");
            const securityAnswerInput = document.querySelector("input[name='securityAnswer']");
            const securityQuestionSelect = document.querySelector("select[name='securityQuestion']");
            const errorBox = document.getElementById("errorBox");
            const errorText = document.getElementById("errorText");
            const emailExistErrorBox = document.getElementById("emailExistErrorBox");

            if (emailExistErrorBox && emailExistErrorBox.style.display === "flex") {
                setTimeout(() => {
                    emailExistErrorBox.style.opacity = "0";  // Fade out
                    setTimeout(() => {
                        emailExistErrorBox.style.display = "none"; // Hide completely
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

            emailInput.addEventListener("input", function () {
                const email = emailInput.value.trim();
                if (email === "") {
                    clearError(); // Clear error if input is empty
                } else if (!email.match(/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/)) {
                    showError("Please enter a valid email address.");
                } else {
                    clearError();
                }
            });

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

            securityQuestionSelect.addEventListener("input", function () {
                if (securityQuestionSelect.value === "") {
                    showError("Please select a security question.");
                    errorMessage = "Please select a security question.";
                } else if (securityQuestionSelect.value !== ""){
                    clearError();
                }
            });

            securityAnswerInput.addEventListener("input", function () {
                if (securityAnswerInput.value === "") {
                    showError("Security answer cannot be empty.");
                    errorMessage = "Security answer cannot be empty.";
                } else if (securityAnswerInput.value !== ""){
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
            const email = document.querySelector("input[name='email']").value.trim();
            const password = document.getElementById("password").value;
            const confirmPassword = document.getElementById("confirmPassword").value;
            const securityQuestion = document.querySelector("select[name='securityQuestion']").value;
            const securityAnswer = document.querySelector("input[name='securityAnswer']").value.trim();
            const errorBox = document.getElementById("errorBox");
            const errorText = document.getElementById("errorText");

            let errorMessage = "";

            // Email Validation
            if (!email.match(/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/)) {
                errorMessage = "Please enter a valid email address.";
            }

            // Password Validation
            else if (!password.match(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>]).{6,12}$/)) {
                errorMessage = "Password must be 6-12 characters, include uppercase, lowercase, number, and special character.";
            }

            // Confirm Password Validation
            else if (password !== confirmPassword) {
                errorMessage = "Passwords do not match.";
            }

            // Security Question Validation
            else if (securityQuestion === "") {
                errorMessage = "Please select a security question.";
            }

            // Security Answer Validation
            else if (securityAnswer === "") {
                errorMessage = "Security answer cannot be empty.";
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