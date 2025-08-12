<?php
// Enable error handling but don't display to users
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start a session
session_start();

// Database connection
include 'dbConnection.php';

// Initialize response
$response = array(
    'success' => false,
    'message' => '',
    'studId' => ''
);

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $email = isset($_POST['email']) ? strtoupper($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Basic validation
    if (empty($email) || empty($password)) {
        $response['message'] = 'Please enter both email and password.';
        echo json_encode($response);
        exit;
    }
    
    try {
        // First, check if the email exists in the database
        $stmt = $conn->prepare("SELECT StudID, StudEmail, Password, Status FROM STUDENT WHERE StudEmail = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Get device info and IP for audit log
        $ipAddress = $_SERVER['REMOTE_ADDR'];
        $deviceInfo = $_SERVER['HTTP_USER_AGENT'];
        
        if ($result->num_rows > 0) {
            // Email exists, get student details
            $student = $result->fetch_assoc();
            $studId = $student['StudID'];
            $hashedPassword = $student['Password'];
            $status = $student['Status'];
            
            // Verify the password
            if (password_verify($password, $hashedPassword)) {
                // Password is correct, check status
                if ($status === 'ACTIVE') {
                    // Account is active, login successful
                    $response['success'] = true;
                    $response['message'] = 'Login successful!';
                    $response['studId'] = $studId;

                    // Set session variables
                    $_SESSION['studID'] = $studId;
                    // logged in set is because we need to check if the user is logged in or not
                    $_SESSION['loggedIn'] = true;
                    
                    // Add successful login to audit log
                    try {
                        $logStmt = $conn->prepare("CALL add_audit_log(?, ?, ?, ?, ?, ?)");
                        // Use the correct parameter names for the stored procedure
                        $p_UserID = $studId;
                        $p_UserRole = 'STUDENT';
                        $p_Action = 'LOGIN';
                        $p_Status = 'SUCCESS';
                        $p_IPAddress = $ipAddress;
                        $p_DeviceInfo = $deviceInfo;
                        
                        $logStmt->bind_param("ssssss", $p_UserID, $p_UserRole, $p_Action, $p_Status, $p_IPAddress, $p_DeviceInfo);
                        $logStmt->execute();
                        $logStmt->close();
                    } catch (Exception $logError) {
                        // Log the error but continue with login
                        error_log("Audit log error: " . $logError->getMessage());
                    }
                } else {
                    // Account is inactive
                    $response['message'] = 'Your account has been deactivated.';
                    
                    // Add failed login to audit log - account deactivated
                    try {
                        $logStmt = $conn->prepare("CALL add_audit_log(?, ?, ?, ?, ?, ?)");
                        // Use the correct parameter names for the stored procedure
                        $p_UserID = $studId;
                        $p_UserRole = 'STUDENT';
                        $p_Action = 'LOGIN';
                        $p_Status = 'FAILED - ACCOUNT DEACTIVATED';
                        $p_IPAddress = $ipAddress;
                        $p_DeviceInfo = $deviceInfo;
                        
                        $logStmt->bind_param("ssssss", $p_UserID, $p_UserRole, $p_Action, $p_Status, $p_IPAddress, $p_DeviceInfo);
                        $logStmt->execute();
                        $logStmt->close();
                    } catch (Exception $logError) {
                        // Log the error but continue
                        error_log("Audit log error: " . $logError->getMessage());
                    }
                }
            } else {
                // Password is incorrect
                $response['message'] = 'Incorrect password.';
                
                // Add failed login to audit log - wrong password
                try {
                    $logStmt = $conn->prepare("CALL add_audit_log(?, ?, ?, ?, ?, ?)");
                    // Use the correct parameter names for the stored procedure
                    $p_UserID = $studId;
                    $p_UserRole = 'STUDENT';
                    $p_Action = 'LOGIN';
                    $p_Status = 'FAILED - LOGIN CREDENTIALS';
                    $p_IPAddress = $ipAddress;
                    $p_DeviceInfo = $deviceInfo;
                    
                    $logStmt->bind_param("ssssss", $p_UserID, $p_UserRole, $p_Action, $p_Status, $p_IPAddress, $p_DeviceInfo);
                    $logStmt->execute();
                    $logStmt->close();
                } catch (Exception $logError) {
                    // Log the error but continue
                    error_log("Audit log error: " . $logError->getMessage());
                }
            }
        } else {
            // Email does not exist
            $response['message'] = 'Incorrect student email or password.';
            
            // Add failed login to audit log - email not found
            try {
                $logStmt = $conn->prepare("CALL add_audit_log(?, ?, ?, ?, ?, ?)");
                // Use the correct parameter names for the stored procedure
                $p_UserID = 'N/A';
                $p_UserRole = 'STUDENT';
                $p_Action = 'LOGIN';
                $p_Status = 'FAILED - LOGIN CREDENTIALS';
                $p_IPAddress = $ipAddress;
                $p_DeviceInfo = $deviceInfo;
                
                $logStmt->bind_param("ssssss", $p_UserID, $p_UserRole, $p_Action, $p_Status, $p_IPAddress, $p_DeviceInfo);
                $logStmt->execute();
                $logStmt->close();
            } catch (Exception $logError) {
                // Log the error but continue
                error_log("Audit log error: " . $logError->getMessage());
            }
        }
        
        // Close the first statement
        $stmt->close();
        
    } catch (Exception $e) {
        // Log the detailed error
        error_log("Database error in studProcessLogin.php: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        
        // Database error - don't expose details to user
        $response['message'] = 'Database error occurred. Please try again later.';
        
        // Make sure we close any open statements
        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
        if (isset($logStmt) && $logStmt) {
            $logStmt->close();
        }
    }
}

// Return response as JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 