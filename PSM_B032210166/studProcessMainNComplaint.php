<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['studID'])) {
    header("Location: studMainPage.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reqID'], $_POST['description'])) {
    try {
        // Get form data
        $reqID = $_POST['reqID'];
        $description = $_POST['description'];
        
        // Validate description
        if (empty($description)) {
            throw new Exception("Description cannot be empty");
        }
        
        // Prepare SQL to update the request
        $stmt = $conn->prepare("
            UPDATE REQUEST 
            SET Description = ?, 
                RequestedDate = CURDATE()
            WHERE ReqID = ? 
            AND StudID = ? 
            AND Status = 'PENDING'
            AND Type IN ('MAINTENANCE', 'COMPLAINT')
        ");
        
        $stmt->bind_param("sss", $description, $reqID, $_SESSION['studID']);
        $stmt->execute();
        
        // Check if the update was successful
        if ($stmt->affected_rows > 0) {
            // Redirect with success message
            header("Location: studMainNComplaint.php?status=success&message=Request+updated+successfully");
            exit();
        } else {
            // Redirect with error message
            header("Location: studMainNComplaint.php?status=error&message=Unable+to+update+request+or+not+authorized");
            exit();
        }
    } catch (Exception $e) {
        // Redirect with error message
        header("Location: studMainNComplaint.php?status=error&message=" . urlencode($e->getMessage()));
        exit();
    }
} else {
    // Redirect if accessed directly without POST data
    header("Location: studMainNComplaint.php");
    exit();
}
?> 