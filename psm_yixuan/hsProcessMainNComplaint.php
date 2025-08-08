<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not hostel staff
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOSTEL_STAFF') {
    header("Location: staffHomePage.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: hsMainNComplaint.php");
    exit();
}

// Get the form data
$reqID = isset($_POST['reqID']) ? $_POST['reqID'] : '';
$status = isset($_POST['status']) ? $_POST['status'] : '';
$description = isset($_POST['description']) ? $_POST['description'] : '';

// Validate the data
if (empty($reqID) || empty($status) || empty($description)) {
    $_SESSION['error'] = "All fields are required.";
    header("Location: hsMainNComplaint.php");
    exit();
}

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // First check if the request exists
    $stmt = $conn->prepare("SELECT * FROM REQUEST WHERE ReqID = ? AND Type IN ('MAINTENANCE', 'COMPLAINT')");
    $stmt->bind_param("s", $reqID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Request doesn't exist
        $_SESSION['error'] = "Request not found.";
        $conn->rollback();
        header("Location: hsMainNComplaint.php");
        exit();
    }
    
    $request = $result->fetch_assoc();
    $studID = $request['StudID'];
    $reqType = $request['Type'];
    
    // Update the request
    $stmt = $conn->prepare("UPDATE REQUEST SET Status = ?, Description = ? WHERE ReqID = ?");
    $stmt->bind_param("sss", $status, $description, $reqID);
    $success = $stmt->execute();
    
    if (!$success) {
        // Update failed
        $_SESSION['error'] = "Failed to update the request. Please try again.";
        $conn->rollback();
    } else {
        // Update successful
        $conn->commit();
        
        // Send email notification if status is APPROVED, REJECTED, IN PROGRESS, or RESOLVED
        if (in_array($status, ['APPROVED', 'REJECTED', 'IN PROGRESS', 'RESOLVED'])) {
            try {
                // Include the email sending functionality
                require_once 'hsSendRequestStatusEmail.php';
                
                // Send the email notification
                $emailResult = sendRequestStatusEmail($studID, $reqID, $reqType, $status);
                
                // Add email status to session message and set alert flag
                if ($emailResult['success']) {
                    $_SESSION['success'] = "Request updated successfully. Email notification sent to student.";
                    $_SESSION['email_sent'] = true; // Flag to trigger alert
                } else {
                    $_SESSION['success'] = "Request updated successfully, but email notification failed: " . $emailResult['message'];
                    $_SESSION['email_sent'] = false;
                }
            } catch (Exception $e) {
                // Log the error but don't stop the process
                error_log("Email sending error: " . $e->getMessage());
                $_SESSION['success'] = "Request updated successfully, but email notification failed.";
            }
        } else {
            $_SESSION['success'] = "Request updated successfully.";
        }
    }
    
    $stmt->close();
} catch (Exception $e) {
    // Handle any exceptions
    $conn->rollback();
    $_SESSION['error'] = "An error occurred: " . $e->getMessage();
    error_log("Error updating request: " . $e->getMessage());
} finally {
    // Redirect back to the main page
    header("Location: hsMainNComplaint.php");
    exit();
}
?> 