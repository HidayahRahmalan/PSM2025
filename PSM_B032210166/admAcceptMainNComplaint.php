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
    // Redirect to the main page if no reqID is provided
    header("Location: admMainNComplaint.php");
    exit();
}

$reqID = $_GET['reqID'];
$empID = $_SESSION['empId'];

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // First check if the request exists and has no assigned employee
    $stmt = $conn->prepare("SELECT * FROM REQUEST WHERE ReqID = ? AND EmpID IS NULL AND Type IN ('MAINTENANCE', 'COMPLAINT')");
    $stmt->bind_param("s", $reqID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Request doesn't exist or already has an assigned employee
        $_SESSION['error'] = "Request not available or already assigned to another employee.";
        $conn->rollback();
        header("Location: admMainNComplaint.php");
        exit();
    }
    
    // Update the request to assign the current employee
    $stmt = $conn->prepare("UPDATE REQUEST SET EmpID = ? WHERE ReqID = ?");
    $stmt->bind_param("ss", $empID, $reqID);
    $success = $stmt->execute();
    
    if (!$success) {
        // Update failed
        $_SESSION['error'] = "Failed to accept the request. Please try again.";
        $conn->rollback();
    } else {
        // Update successful
        $_SESSION['success'] = "Request accepted successfully.";
        $conn->commit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    // Handle any exceptions
    $conn->rollback();
    $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    error_log("Error accepting request: " . $e->getMessage());
} finally {
    // Redirect back to the main page
    header("Location: admMainNComplaint.php");
    exit();
}
?> 