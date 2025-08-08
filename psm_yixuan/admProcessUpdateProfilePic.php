<?php
// Enable error handling but don't display to users
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start a session
session_start();

// Check if user is logged in
if (!isset($_SESSION['empId']) || !isset($_SESSION['role'])) {
    header("Location: staffMainPage.php");
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
        
        // Update the profile picture in the database
        $stmt = $conn->prepare("UPDATE EMPLOYEE SET ProfilePic = ? WHERE EmpID = ?");
        $stmt->bind_param("ss", $imageData, $_SESSION['empId']);
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