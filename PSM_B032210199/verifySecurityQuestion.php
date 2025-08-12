<?php

date_default_timezone_set('Asia/Kuala_Lumpur'); 

session_start(); 

include 'dbConnection.php';  

$UserID = $_SESSION['UserID']; 

$maxAttempts = 3; 
$lockDuration = 1; 

// Fetch user data
$sql = "SELECT USecurityQuestion, USecurityAnswer, UFailedAttempts, ULastFailedAttempt, UIsLocked FROM USER WHERE UserID = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $UserID);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$currentTime = new DateTime();
$lockTime = new DateTime($user['ULastFailedAttempt']);
$lockTime->modify("+$lockDuration minutes");

// Check if account is locked
if ($user['UIsLocked'] && $currentTime < $lockTime) {
    $remainingMinutes = $lockTime->diff($currentTime)->i;
    $errorMsg = "Your account is locked for 30 minutes. If you need immediate access, please contact the system administrator.";
} else {
    // Unlock account if lock time has passed
    if ($user['UIsLocked'] && $currentTime >= $lockTime) {
        $sql = "UPDATE USER SET UFailedAttempts = 0, ULastFailedAttempt = NULL, UIsLocked = FALSE WHERE UserID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $UserID);
        $stmt->execute();
        $stmt->close();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $userAnswer = strtoupper($_POST['USecurityAnswer']);
        $correctAnswer = strtoupper($user['USecurityAnswer']);

        if ($userAnswer === $correctAnswer) {
            // Reset failed attempts on success
            $sql = "UPDATE USER SET UFailedAttempts = 0, ULastFailedAttempt = NULL, UIsLocked = FALSE WHERE UserID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $UserID);
            $stmt->execute();
            $stmt->close();

            header("Location: resetPasswordAccount.php");
            exit();
        } else {
            // Increment failed attempts
            $newAttempts = $user['UFailedAttempts'] + 1;

            if ($newAttempts >= $maxAttempts) {
                $lockTime = (new DateTime())->format('Y-m-d H:i:s');
                $sql = "UPDATE USER SET UFailedAttempts = ?, ULastFailedAttempt = ?, UIsLocked = TRUE WHERE UserID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $newAttempts, $lockTime, $UserID);
                $errorMsg = "Your account is locked for 30 minutes. If you need immediate access, please contact the system administrator.";
            } else {
                $lockTime = (new DateTime())->format('Y-m-d H:i:s');
                $sql = "UPDATE USER SET UFailedAttempts = ?, ULastFailedAttempt = ? WHERE UserID = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("iss", $newAttempts, $lockTime, $UserID);
                $errorMsg = "Incorrect answer. Attempt $newAttempts of $maxAttempts.";
            }

            $stmt->execute();
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
    <title>Verify - Data Quality Monitoring</title>
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
            
            font-size: 14px;
            width: 78%;
            max-width: 320px;
            display: flex;

            align-items: center;
            justify-content: flex-start;
            text-align: left;
            margin: 0 auto 15px auto;
            opacity: 1;
            transition: opacity 0.3s ease-in-out;

            text-align: justify;
            text-justify: inter-word;
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
            width: 350px;
            height: 230px;
            min-height: 270px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            overflow: hidden; /* Prevents overflow */
            transition: min-height 0.3s ease-in-out;
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
            margin-bottom: 30px;
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
            margin-bottom: 80px;
        }
        .security-question {
            font-size: 18px; 
            font-weight: bold;
            color:rgb(248, 246, 243); 
            margin-bottom: 20px;
            text-align: justify;
            text-justify: inter-word;
            width: 78%; /* Match input width */
            display: block;
            margin: 0 auto 20px auto; /* Center align */
            word-wrap: break-word;
        }
        .input-container, .reset-form button {
            width: 78%;
            display: block;
            margin: 10px auto; 
            text-align: center;
        }
        .input-container {
            position: relative;
            display: flex;
            align-items: center;
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
            padding: 6px;
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
            <a href="findResetPasswordAccount.php" class="back-arrow">←</a>
            <div class="reset-title">Verify Security Question</div> 
            <form action="verifySecurityQuestion.php" method="POST">

                <?php if (!empty($errorMsg)): ?>
                    <div class="error-box" id="errorBox">
                        <span class="error-icon">✖</span>
                        <p class="error-text"><?php echo $errorMsg; ?></p>
                    </div>
                <?php endif; ?>

                <p class="security-question"><?php echo htmlspecialchars($user['USecurityQuestion'], ENT_QUOTES, 'UTF-8'); ?></p>


                <div class="input-container">
                    <img src="sa.png" alt="key Icon">
                    <input type="text" id="securityQuestion" name="USecurityAnswer" placeholder="Enter your answer" required>
                </div>
                
                <button type="submit" name="verify">Verify</button>
            </form>
            </div>
        </div>
    </div>

</body>
</html>
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const errorBox = document.getElementById("errorBox");
        const resetForm = document.querySelector(".reset-form");

        if (errorBox) {
            // Increase form height when error box appears
            resetForm.style.minHeight = "320px";

            setTimeout(() => {
                errorBox.style.opacity = "0"; // Fade out
                setTimeout(() => {
                    errorBox.style.display = "none"; // Hide completely
                    resetForm.style.minHeight = "270px"; // Restore height
                }, 500);
            }, 3000);
        }
    });

</script>