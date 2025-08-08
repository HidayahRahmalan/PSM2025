<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not hostel staff
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOSTEL_STAFF') {
    header("Location: staffMainPage.php");
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    
    // Get form data
    $reqID = isset($_POST['reqID']) ? $_POST['reqID'] : '';
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $description = isset($_POST['description']) ? $_POST['description'] : '';
    
    // Validate required fields
    if (empty($reqID) || empty($status) || empty($description)) {
        $_SESSION['error'] = "All required fields must be filled out.";
        header("Location: hsManageRoomChange.php");
        exit();
    }
    
    try {
        // Get the student ID before updating
        $stmt = $conn->prepare("SELECT StudID, Type FROM REQUEST WHERE ReqID = ?");
        $stmt->bind_param("s", $reqID);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $studID = $request['StudID'];
        $reqType = $request['Type'];
        
        // Update the request - the trigger will handle the rest when status is APPROVED
        $stmt = $conn->prepare("UPDATE REQUEST SET Status = ?, Description = ?, EmpID = ? WHERE ReqID = ?");
        $stmt->bind_param("ssss", $status, $description, $_SESSION['empId'], $reqID);
        $stmt->execute();
        
        // Send email notification if status is APPROVED or REJECTED
        if ($status === 'APPROVED' || $status === 'REJECTED') {
            try {
                // Include the email sending functionality
                require_once 'hsSendRequestStatusEmail.php';
                
                // Send the email notification
                $emailResult = sendRequestStatusEmail($studID, $reqID, $reqType, $status);
                
                // Add email status to session message and set alert flag
                if ($emailResult['success']) {
                    $_SESSION['success'] = "Room change request has been updated successfully. Email notification sent to student.";
                    $_SESSION['email_sent'] = true; // Flag to trigger alert
                } else {
                    $_SESSION['success'] = "Room change request has been updated successfully, but email notification failed: " . $emailResult['message'];
                    $_SESSION['email_sent'] = false;
                }
            } catch (Exception $e) {
                // Log the error but don't stop the process
                error_log("Email sending error: " . $e->getMessage());
                $_SESSION['success'] = "Room change request has been updated successfully, but email notification failed.";
            }
        } else {
            $_SESSION['success'] = "Room change request has been updated successfully.";
        }
        
        header("Location: hsManageRoomChange.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating request: " . $e->getMessage();
        header("Location: hsManageRoomChange.php");
        exit();
    }
    
} else {
    // Not a POST request
    header("Location: hsManageRoomChange.php");
    exit();
}
?> 