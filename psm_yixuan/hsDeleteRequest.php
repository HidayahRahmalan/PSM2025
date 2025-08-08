<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not hostel staff
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOSTEL STAFF') {
    header("Location: staffMainPage.php");
    exit();
}

// Check if reqID is provided
if (!isset($_GET['reqID'])) {
    $_SESSION['error'] = "Request ID is required.";
    header("Location: hsManageRoomChange.php");
    exit();
}

$reqID = $_GET['reqID'];

try {
    // First, check if the request exists and has PENDING status
    $stmt = $conn->prepare("SELECT Status FROM REQUEST WHERE ReqID = ? AND Type = 'ROOM CHANGE'");
    $stmt->bind_param("s", $reqID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['error'] = "Request not found.";
        header("Location: hsManageRoomChange.php");
        exit();
    }
    
    $row = $result->fetch_assoc();
    if ($row['Status'] !== 'PENDING') {
        $_SESSION['error'] = "Only pending requests can be deleted.";
        header("Location: hsManageRoomChange.php");
        exit();
    }
    
    // Delete the request
    $stmt = $conn->prepare("DELETE FROM REQUEST WHERE ReqID = ?");
    $stmt->bind_param("s", $reqID);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $_SESSION['success'] = "Request has been deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete the request.";
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
}

header("Location: hsManageRoomChange.php");
exit();
?> 