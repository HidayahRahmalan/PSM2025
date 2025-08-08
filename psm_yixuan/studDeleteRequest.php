<?php
session_start();
include 'dbConnection.php';

// Check if user is logged in
if (!isset($_SESSION['studID'])) {
    header("Location: studMainPage.php");
    exit();
}

// Check if request ID is provided
if (!isset($_GET['reqID'])) {
    $_SESSION['error'] = "Request ID is required.";
    header("Location: studRoomChange.php");
    exit();
}

$reqID = $_GET['reqID'];
$studID = $_SESSION['studID'];

try {
    // First check if the request exists and is still pending
    $stmt = $conn->prepare("
        SELECT Status 
        FROM REQUEST 
        WHERE ReqID = ? AND StudID = ? AND Type = 'ROOM CHANGE'
    ");
    $stmt->bind_param("ss", $reqID, $studID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        if ($row['Status'] !== 'PENDING') {
            $_SESSION['error'] = "Only pending requests can be deleted.";
            header("Location: studRoomChange.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Request not found.";
        header("Location: studRoomChange.php");
        exit();
    }
    $stmt->close();
    
    // Delete the request
    $stmt = $conn->prepare("
        DELETE FROM REQUEST 
        WHERE ReqID = ? AND StudID = ? AND Type = 'ROOM CHANGE' AND Status = 'PENDING'
    ");
    $stmt->bind_param("ss", $reqID, $studID);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Request deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete request.";
    }
    
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error'] = "An error occurred while processing your request.";
    error_log("Error in studDeleteRequest.php: " . $e->getMessage());
}

header("Location: studRoomChange.php");
exit();
?> 