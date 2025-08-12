<?php

session_start();  

include 'dbConnection.php'; 
include 'activityTracker.php'; 

$UserID = $_SESSION['UserID'];

$passwordError = $emailError = $error = $updateMessage = $profileError = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = strtoupper($_POST['name']);
    $email = strtolower($_POST['email']);
    $password = $_POST['password'];
    $securityQuestion = $_POST['securityQuestion'];
    $securityAnswer = strtoupper($_POST['securityAnswer']);

    $hasError = false;

    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*(),.?":{}|<>]).{6,12}$/', $password)) {
        $passwordError = "Password must be between 6 and 12 characters, including uppercase, lowercase, number, and special character.";
        $hasError = true;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $emailError = "Please enter a valid email address.";
        $hasError = true;
    }    

    if (!$hasError) {
        $sql = "UPDATE `USER` SET UName = ?, UEmail = ?, UPassword = ?, USecurityQuestion = ?, USecurityAnswer = ? WHERE UserID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssss", $name, $email, $password, $securityQuestion, $securityAnswer, $UserID);
        
        try {
            $stmt->execute();
            $updateMessage = "Profile updated successfully.";
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) { 
                $emailExistError = "The email is already registered. Please use another.";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
        $stmt->close();
    }
}

$sql = "SELECT * FROM `USER` WHERE UserID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $UserID);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 1) {
    $profile = $result->fetch_assoc();
} else {
    $profileError = "Profile not found.";
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: rgb(248, 248, 248);
            color: white;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: 30px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            color: black;
        }
        
        .error-box {
            background-color: white;
            color:  #ff9800;
            padding: 5px;
            border: 1px solid grey;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            min-height: 40px;
        }
        .error-text {
            font-size: 15px; 
            line-height: 1.4; /* Increase line spacing for better readability */
            margin-left: 6px; /* Space between icon and text */
            flex-grow: 1; /* Ensure text takes up available space */
            text-align: left;
            margin-bottom: 1px;
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
        .success-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            background-color:rgb(177, 146, 100);
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
        .password-container {
            position: relative;
        }
        .password-container input {
            width: 100%;
            padding-right: 35px;
        }
        .eye-icon {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            width: 20px;
        }
        .form-group label {
            font-weight: bold;
        }
        .btn-primary {
            background-color: #ff9800;
            border: none;
        }
        .btn-primary:hover {
            background-color: #e68900;
        }
    </style>
</head>
<body>

<div class="container">
    <h2 class="text-center">Edit Profile</h2>

    <form method="POST" action="">

                <!-- Error Box -->
                <div id="errorBox" class="error-box" style="display: none;">
                    <span class="error-icon">✖</span>
                    <p id="errorText" class="error-text"></p>
                </div>

                <div id="emailExistErrorBox" class="error-box" style="display: <?php echo !empty($emailExistError) ? 'flex' : 'none'; ?>;">
                    <span class="error-icon">✖</span>
                    <p id="emailExistErrorText" class="error-text"><?php echo $emailExistError; ?></p>
                </div>

                <div id="updateMessageBox" class="error-box" style="display: <?php echo !empty($updateMessage) ? 'flex' : 'none'; ?>;">
                    <span class="success-icon">✔</span>
                    <p id="updateMessageText" class="error-text"><?php echo $updateMessage; ?></p>
                </div>

                <div id="noChangeErrorBox" class="error-box" style="display: none;">
                    <span class="error-icon">✖</span>
                    <p id="noChangeErrorText" class="error-text">You didn't change anything.</p>
                </div>

                <!-- Error Message for Other Validation Errors -->
                <div id="validationErrorBox" class="error-box" style="display: none;">
                    <span class="error-icon">✖</span>
                    <p id="validationErrorText" class="error-text">Please correct errors before submitting.</p>
                </div>

        <div class="form-group">
            <label for="name">Full Name:</label>
            <input type="text" class="form-control" id="name" name="name" value="<?php echo $profile['UName']; ?>" required>
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" class="form-control" id="email" name="email" value="<?php echo $profile['UEmail']; ?>" required>
        </div>

        <div class="form-group">
            <label for="password">Password:</label>
            <div class="password-container">
                <input type="password" class="form-control" id="password" name="password" value="<?php echo $profile['UPassword']; ?>" required>
                <img src="eye-off.png" alt="Toggle Password" id="eye-icon" class="eye-icon" onclick="togglePassword()">
            </div>
        </div>

        <div class="form-group">
            <label for="securityQuestion">Security Question:</label>
            <select class="form-control" id="securityQuestion" name="securityQuestion">
                <option value="What was the first programming language you learned?" <?php echo ($profile['USecurityQuestion'] == "What was the first programming language you learned?" ? "selected" : ""); ?>>What was the first programming language you learned?</option>
                <option value="What tool did you use in your first data analysis job?" <?php echo ($profile['USecurityQuestion'] == "What tool did you use in your first data analysis job?" ? "selected" : ""); ?>>What tool did you use in your first data analysis job?</option>
                <option value="What is the name of the first dataset you worked on?" <?php echo ($profile['USecurityQuestion'] == "What is the name of the first dataset you worked on?" ? "selected" : ""); ?>>What is the name of the first dataset you worked on?</option>
            </select>
        </div>

        <div class="form-group">
            <label for="securityAnswer">Security Answer:</label>
            <input type="text" class="form-control" id="securityAnswer" name="securityAnswer" value="<?php echo $profile['USecurityAnswer']; ?>" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block">Update Profile</button>

        <a href="adminMainPage.php" class="btn btn-secondary btn-block mt-2">Back to Admin Main Page</a>

    </form>
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
                }, 5000); // Hide after 2 seconds
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

        document.addEventListener("DOMContentLoaded", function () {
            // Store initial form values
            let originalValues = {
                name: document.getElementById("name").value,
                email: document.getElementById("email").value,
                password: document.getElementById("password").value,
                securityQuestion: document.getElementById("securityQuestion").value,
                securityAnswer: document.getElementById("securityAnswer").value
            };

            document.querySelector("form").addEventListener("submit", function (event) {
                let currentValues = {
                    name: document.getElementById("name").value,
                    email: document.getElementById("email").value,
                    password: document.getElementById("password").value,
                    securityQuestion: document.getElementById("securityQuestion").value,
                    securityAnswer: document.getElementById("securityAnswer").value
                };

                let hasErrors = document.querySelectorAll(".error-box[style*='display: flex']").length > 0;

                // Check if form is unchanged
                if (JSON.stringify(originalValues) === JSON.stringify(currentValues)) {
                    event.preventDefault(); // Stop form submission
                    showError("noChangeErrorBox");
                    return;
                }

                // Check for validation errors
                if (hasErrors) {
                    event.preventDefault(); // Stop form submission
                    showError("validationErrorBox");
                }
            });

            function showError(errorBoxId) {
                let errorBox = document.getElementById(errorBoxId);
                errorBox.style.display = "flex"; // Show error message

                // Hide the error message after 3 seconds
                setTimeout(function () {
                    errorBox.style.display = "none";
                }, 5000);
            }
        });

        //let updateMessage disappear after 3s
        document.addEventListener("DOMContentLoaded", function () {
            let updateMessageBox = document.getElementById("updateMessageBox");

            if (updateMessageBox.style.display === "flex") {
                setTimeout(function () {
                    updateMessageBox.style.display = "none";
                }, 3000); // Disappear after 3 seconds
            }
        });

        document.addEventListener("DOMContentLoaded", function () {
            const savedTheme = localStorage.getItem("theme") || "dark"; // Default to dark mode
            setTheme(savedTheme);
        });

        function setTheme(theme) {
            const body = document.body;
            const container = document.querySelector(".container"); // Select the form
            const errorBoxes = document.querySelectorAll(".error-box"); // Select all error boxes
            const allTextElements = document.querySelectorAll(
                "body, .password-container, .container"
            );

            if (theme === "light") {
                // Light Mode - Keep background white and add border to form
                body.style.backgroundColor = "rgb(245, 245, 245)";
                container.style.backgroundColor = "white";
                container.style.color = "black";
                container.style.border = "1px solid rgb(200, 200, 200)"; // Light border
                container.style.boxShadow = "0px 2px 10px rgba(0, 0, 0, 0.1)"; // Soft shadow

                allTextElements.forEach(element => {
                    element.style.color = "black";
                });

            } else {
                // Dark Mode - Change form background to dark and text to white
                body.style.backgroundColor = "rgb(43, 45, 46)";
                container.style.backgroundColor = "rgb(100, 100, 100)"; // Dark background
                container.style.color = "white";
                container.style.border = "1px solid rgb(100, 100, 100)"; // Darker border
                container.style.boxShadow = "0px 2px 10px rgba(255, 255, 255, 0.1)"; // Soft white glow

                errorBoxes.forEach(box => {
                    box.style.backgroundColor = "rgb(10, 10, 10)";
                    box.style.color = "#ff9800";
                    box.style.border = "1px solid rgb(200, 200, 200)";
                });

                allTextElements.forEach(element => {
                    element.style.color = "white";
                });
            }
        }


</script>

</body>
</html>
