<?php
session_start();
include 'dbConnection.php';

// Check if user is logged in
if (!isset($_SESSION['studID'])) {
    header("Location: studMainPage.php");
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reqID = $_POST['reqID'];
    $description = trim($_POST['description']);
    $currentDate = date('Y-m-d'); // Store only the date
    $studID = $_SESSION['studID'];
    
    // Validate input
    if (empty($reqID) || empty($description)) {
        $_SESSION['error'] = "All fields are required.";
        header("Location: studRoomChange.php");
        exit();
    }
    
    if (strlen($description) > 250) {
        $_SESSION['error'] = "Description cannot exceed 250 characters.";
        header("Location: studRoomChange.php");
        exit();
    }
    
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
                $_SESSION['error'] = "Only pending requests can be edited.";
                header("Location: studRoomChange.php");
                exit();
            }
        } else {
            $_SESSION['error'] = "Request not found.";
            header("Location: studRoomChange.php");
            exit();
        }
        $stmt->close();
        
        // Update the request with current date
        $stmt = $conn->prepare("
            UPDATE REQUEST 
            SET Description = ?, 
                RequestedDate = CURDATE() 
            WHERE ReqID = ? AND StudID = ? AND Type = 'ROOM CHANGE' AND Status = 'PENDING'
        ");
        $stmt->bind_param("sss", $description, $reqID, $studID);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Request updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to update request.";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred while processing your request.";
        error_log("Error in studProcessRoomChange.php: " . $e->getMessage());
    }
    
    header("Location: studRoomChange.php");
    exit();
}

// If not POST request, redirect to room change page
header("Location: studRoomChange.php");
exit();
?> 