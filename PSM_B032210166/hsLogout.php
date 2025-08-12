<?php
// Enable error handling but don't display to users
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start a session
session_start();

// Include database connection
include 'dbConnection.php';

// Check if user is logged in
if (isset($_SESSION['empId']) && isset($_SESSION['role'])) {
    $empID = $_SESSION['empId'];
    
    // Get device info and IP for audit log
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $deviceInfo = $_SERVER['HTTP_USER_AGENT'];
    
    // Add logout action to audit log
    try {
        $logStmt = $conn->prepare("CALL add_audit_log(?, ?, ?, ?, ?, ?)");
        $p_UserID = $empID;
        $p_UserRole = 'HOSTEL STAFF'; 
        
        // If role is stored in session, use that
        if (isset($_SESSION['role'])) {
            $p_UserRole = $_SESSION['role'];
        }
        
        $p_Action = 'LOGOUT';
        $p_Status = 'SUCCESS';
        $p_IPAddress = $ipAddress;
        $p_DeviceInfo = $deviceInfo;
        
        $logStmt->bind_param("ssssss", 
            $p_UserID, 
            $p_UserRole, 
            $p_Action, 
            $p_Status, 
            $p_IPAddress, 
            $p_DeviceInfo
        );
        $logStmt->execute();
        $logStmt->close();
    } catch (Exception $logError) {
        error_log("Audit log error during logout: " . $logError->getMessage());
    }
}

// Destroy all session data
$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to main page
header("Location: staffMainPage.php");
exit();
?> 