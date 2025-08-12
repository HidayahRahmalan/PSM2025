<?php

session_start(); 

include 'dbConnection.php';  

$errorMsg = $emailError = "";

//clear for newly logged-in user
$_SESSION['securityAnswer'] = ""; // Clear previous security answer
$_SESSION['securityQuestion'] = ""; // Clear previous security question

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email']; // Get user input

    // Validate email and phone number formats
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = strtolower($email);
    } else {
        $errorMsg = "Please enter a valid email address.";
    }

    if (empty($errorMsg)) {
        $sql = "SELECT * FROM `USER` WHERE UEmail = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();

            session_start();
            $_SESSION['UserID'] = $user['UserID'];
            $_SESSION['email'] = $user['UEmail'];

            header("Location: verifySecurityQuestion.php");
            exit();
        } else {
            $errorMsg = "No account found with the provided email. Please try again with other email.";
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
    <title>Find Account - Data Quality Monitoring</title>
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
            text-align: left;
        }
        .error-icon {
            display: inline-block;
            width: 16px;
            height: 16px;
            min-width: 16px; /* Ensures it's always a circle */
            min-height: 16px;
            background-color: #ff9800;
            color: white;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            line-height: 16px;
            border-radius: 50%; /* Circle shape */
            margin-right: 6px;
        }
        .reset-form {
            background: rgba(8, 0, 0, 0.541);
            padding: 20px;
            border-radius: 8px;
            width: 320px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .back-arrow {
            position: absolute;
            top: 20px;
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
        .title-container {
            text-align: center;
            color: white;
            margin-bottom: 20px;
        }
        .input-container, .reset-form button {
            width: 100%;
            display: block;
            margin: 10px auto; 
            text-align: center;
        }
        .input-container {
            position: relative;
        }
        .input-container input {
            width: 100%;
            padding: 10px;
            padding-left: 35px;
            border: none;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .input-container img {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            width: 22px;
            height: 22px;
        }
        .reset-form button { 
            padding: 10px;
            background: #ff9800;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            font-size: 19px;
            box-sizing: border-box;
        }
        .reset-form button:hover {
            background: #e68900;
        }
    </style>
</head>
<body>
        <div class="title-container">
            <h1>Data Quality Monitoring System</h1>
        </div>
    </div>
        <div class="reset-form">
            <!--<a href="login.php" class="back-arrow">←</a>-->
            <a href="index.php" class="back-arrow">←</a>
            <div class="reset-title">Find Account</div> 
            <form action="findResetPasswordAccount.php" method="POST">

                <?php if (!empty($errorMsg)): ?>
                    <div class="error-box" id="errorBox">
                        <span class="error-icon">✖</span>
                        <p class="error-text"><?php echo $errorMsg; ?></p>
                    </div>
                <?php endif; ?>

                <div class="input-container">
                    <img src="email.png" alt="Email Icon">
                    <input type="text" name="email" placeholder="Email address" required>
                </div>

                <button type="submit" name="next">Next</button>
            </form>
            </div>
        </div>
    </div>

</body>
</html>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const errorBox = document.getElementById("errorBox");

        if (errorBox) { // Check if the error box exists
            setTimeout(() => {
                errorBox.style.opacity = "0";  // Fade out
                setTimeout(() => {
                    errorBox.style.display = "none"; // Hide completely
                }, 500); // Wait for fade-out effect
            }, 3000); // Hide after 3 seconds
        }
    });

</script>