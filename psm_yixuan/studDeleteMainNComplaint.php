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

// Check if request ID is provided
if (!isset($_GET['reqID']) || empty($_GET['reqID'])) {
    header("Location: studMainNComplaint.php?status=error&message=Request+ID+is+required");
    exit();
}

$reqID = $_GET['reqID'];

try {
    // First, verify that this is a maintenance or complaint request in PENDING status
    $stmt = $conn->prepare("
        SELECT Type 
        FROM REQUEST 
        WHERE ReqID = ? AND StudID = ? AND Status = 'PENDING' AND Type IN ('MAINTENANCE', 'COMPLAINT')
    ");
    
    $stmt->bind_param("ss", $reqID, $_SESSION['studID']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Request not found or not in PENDING status or not maintenance/complaint
        header("Location: studMainNComplaint.php?status=error&message=Request+not+found+or+not+eligible+for+deletion");
        exit();
    }
    
    // Prepare SQL to delete the request
    $stmt = $conn->prepare("
        DELETE FROM REQUEST 
        WHERE ReqID = ? 
        AND StudID = ? 
        AND Status = 'PENDING'
        AND Type IN ('MAINTENANCE', 'COMPLAINT')
    ");
    
    $stmt->bind_param("ss", $reqID, $_SESSION['studID']);
    $stmt->execute();
    
    // Check if the deletion was successful
    if ($stmt->affected_rows > 0) {
        header("Location: studMainNComplaint.php?status=success&message=Request+deleted+successfully");
    } else {
        header("Location: studMainNComplaint.php?status=error&message=Unable+to+delete+request");
    }
    exit();
} catch (Exception $e) {
    // Error occurred
    header("Location: studMainNComplaint.php?status=error&message=" . urlencode($e->getMessage()));
    exit();
}
?> 