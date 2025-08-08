<?php
session_start();

include 'dbConnection.php'; 

$UserID = $_SESSION['UserID'];

// Clear the session token
$stmt = $conn->prepare("UPDATE `USER` SET USessionToken = NULL WHERE UserID = ?");
if ($stmt) {
    $stmt->bind_param("s", $UserID);
    $stmt->execute();
    $stmt->close();

} else {
    error_log("Failed to prepare statement for session token removal: " . $conn->error);
}

// Clear the session data
session_unset();
session_destroy();

// Redirect to login page or homepage
header("Location: login.php");
exit();

?>
