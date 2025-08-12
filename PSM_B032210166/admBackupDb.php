<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: staffMainPage.php");
    exit();
}

// Execute backup batch file
$output = [];
$return_var = 0;
$backup_file = "C:\\backupFold\\backup_db_fyp_shms.bat";

// Check if backup file exists
if (!file_exists($backup_file)) {
    $_SESSION['error'] = "Backup file not found at: " . $backup_file;
    header("Location: admUserMgmt.php");
    exit();
}

// Execute the backup batch file
exec($backup_file, $output, $return_var);

// Check if backup was successful
if ($return_var === 0) {
    // Log the backup action in audit trail
    try {
        $stmt = $conn->prepare("
            INSERT INTO AUDIT_TRAIL (
                TableName, 
                RecordID, 
                Action, 
                UserID, 
                OldData, 
                NewData, 
                Timestamp
            ) VALUES (
                'DATABASE',
                'BACKUP',
                'BACKUP',
                ?,
                NULL,
                JSON_OBJECT('action', 'Database backup', 'status', 'success'),
                NOW()
            )
        ");
        $stmt->bind_param("s", $_SESSION['empId']);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error logging backup: " . $e->getMessage());
    }

    $_SESSION['success'] = "Database backup completed successfully!";
} else {
    // Log failed backup attempt
    try {
        $stmt = $conn->prepare("
            INSERT INTO AUDIT_TRAIL (
                TableName, 
                RecordID, 
                Action, 
                UserID, 
                OldData, 
                NewData, 
                Timestamp
            ) VALUES (
                'DATABASE',
                'BACKUP',
                'BACKUP_FAILED',
                ?,
                NULL,
                JSON_OBJECT('action', 'Database backup', 'status', 'failed', 'error', ?),
                NOW()
            )
        ");
        $error_msg = implode("\n", $output);
        $stmt->bind_param("ss", $_SESSION['empId'], $error_msg);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error logging failed backup: " . $e->getMessage());
    }

    $_SESSION['error'] = "Database backup failed. Please check the system logs.";
}

// Redirect back to user management page
header("Location: admUserMgmt.php");
exit();
?> 