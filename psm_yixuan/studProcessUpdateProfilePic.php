<?php
// Enable error handling but don't display to users
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start a session
session_start();

// Check if user is logged in
if (!isset($_SESSION['studID'])) {
    header("Location: studMainPage.php");
    exit();
}

// Include database connection
include 'dbConnection.php';

// Initialize response
$response = array(
    'success' => false,
    'message' => ''
);

// Check if it's a POST request and has the expected data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['imageData'])) {
    try {
        // Get the image data
        $imageData = $_POST['imageData'];
        
        // Remove the "data:image/jpeg;base64," part
        $imageData = substr($imageData, strpos($imageData, ",") + 1);
        
        // Convert to binary data
        $imageData = base64_decode($imageData);
        
        // Check if chronic issue fields are provided
        $chronicIssueLevel = isset($_POST['chronicIssueLevel']) ? $_POST['chronicIssueLevel'] : null;
        $chronicIssueName = isset($_POST['chronicIssueName']) ? strtoupper($_POST['chronicIssueName']) : null;
        
        // Update the profile picture in the database
        if ($chronicIssueLevel !== null && $chronicIssueName !== null) {
            // Update profile picture and chronic issue fields
            $stmt = $conn->prepare("UPDATE STUDENT SET ProfilePic = ?, ChronicIssueLevel = ?, ChronicIssueName = ? WHERE StudID = ?");
            $stmt->bind_param("ssss", $imageData, $chronicIssueLevel, $chronicIssueName, $_SESSION['studID']);
        } else {
            // Update only profile picture
            $stmt = $conn->prepare("UPDATE STUDENT SET ProfilePic = ? WHERE StudID = ?");
            $stmt->bind_param("ss", $imageData, $_SESSION['studID']);
        }
        
        $result = $stmt->execute();
        
        if ($result) {
            $response['success'] = true;
            $response['message'] = 'Profile picture updated successfully!';
        } else {
            $response['message'] = 'Failed to update profile picture.';
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error updating profile picture: " . $e->getMessage());
        $response['message'] = 'An error occurred while updating your profile picture.';
    }
} else {
    $response['message'] = 'Invalid request.';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?> 