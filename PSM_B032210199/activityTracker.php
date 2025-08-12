<?php

include 'dbConnection.php';

$UserID = isset($_SESSION['UserID']) ? $_SESSION['UserID'] : null;
$inactive_duration = 1800; // 30 minutes inactivity timeout in seconds

// Fetch last activity time if UserID is set
if ($UserID) {
    $stmt = $conn->prepare("SELECT ULastActive FROM `USER` WHERE UserID = ?");
    $stmt->bind_param("s", $UserID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $last_activity = strtotime($row['ULastActive']); // Convert to timestamp

        // Check if the last activity timestamp exceeds the inactivity duration
        if (time() - $last_activity > $inactive_duration) {

            // Clear session token
            $stmt = $conn->prepare("UPDATE `USER` SET USessionToken = NULL WHERE UserID = ?");
            if ($stmt) {
                $stmt->bind_param("s", $UserID);
                $stmt->execute();
                $stmt->close();
            } else {
                error_log("Failed to prepare statement for session token removal: " . $conn->error);
            }

            session_unset();
            session_destroy();

            // Redirect to login page
            //header("Location: login.php?Error=session_timeout");
            header("Location: index.php?Error=session_timeout");
            exit();
        } else {
            // Update last activity if still within the timeout duration
            $stmt = $conn->prepare("UPDATE `USER` SET ULastActive = NOW() WHERE UserID = ?");
            $stmt->bind_param("s", $UserID);
            $stmt->execute();
        }
    }
}
?>


<script>
    // Add JavaScript for inactivity detection
    let inactivityTime = 1800000; // 30 minutes in milliseconds
    let logoutTimer;

    function resetTimer() {
        // Clear the existing timer
        clearTimeout(logoutTimer);

        // Start a new timer
        logoutTimer = setTimeout(() => {
            // Log the user out after inactivity
            //alert("You have been logged out due to inactivity.");
            showAlert("warning", "You have been logged out due to inactivity.");
            window.location.href = "logout.php"; // Redirect to logout
        }, inactivityTime);
    }

    // Reset the timer on any user interaction
    document.addEventListener("mousemove", resetTimer);
    document.addEventListener("keydown", resetTimer);
    document.addEventListener("click", resetTimer);

    // Start the timer when the page loads
    window.onload = resetTimer;

    function showAlert(icon, title, text) {
        const theme = localStorage.getItem("theme") || "dark"; // Get current theme

        Swal.fire({
            icon: icon,
            title: title,
            text: text,
            confirmButtonColor: "#ff9800",
            background: theme === "dark" ? "#333" : "#fff",
            color: theme === "dark" ? "#fff" : "#000",
            width: "380px",
            padding: "12px",
            timer: 5000, // Display for 5 seconds 
            timerProgressBar: true, // ✅ Shows a visual countdown
            customClass: {
                popup: "custom-alert"
            }
        }).then(() => {
            window.location.href = "logout.php"; // Redirect AFTER the alert disappears
        });
    }

</script>