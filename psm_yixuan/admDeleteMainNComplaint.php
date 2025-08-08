<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: staffHomePage.php");
    exit();
}

// Check if request ID is provided
if (!isset($_GET['reqID'])) {
    header("Location: admMainNComplaint.php");
    exit();
}

$reqID = $_GET['reqID'];

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // First check if the request exists and is in PENDING status
    $stmt = $conn->prepare("SELECT * FROM REQUEST WHERE ReqID = ? AND Status = 'PENDING' AND Type IN ('MAINTENANCE', 'COMPLAINT')");
    $stmt->bind_param("s", $reqID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Request doesn't exist or not in PENDING status
        $_SESSION['error'] = "Request not found or cannot be deleted (only PENDING requests can be deleted).";
        $conn->rollback();
        header("Location: admMainNComplaint.php");
        exit();
    }
    
    // Delete the request
    $stmt = $conn->prepare("DELETE FROM REQUEST WHERE ReqID = ?");
    $stmt->bind_param("s", $reqID);
    $success = $stmt->execute();
    
    if (!$success) {
        // Delete failed
        $_SESSION['error'] = "Failed to delete the request. Please try again.";
        $conn->rollback();
    } else {
        // Delete successful
        $_SESSION['success'] = "Request deleted successfully.";
        $conn->commit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    // Handle any exceptions
    $conn->rollback();
    $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    error_log("Error deleting request: " . $e->getMessage());
} finally {
    // Redirect back to the main page
    header("Location: admMainNComplaint.php");
    exit();
}
?> 