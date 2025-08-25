<?php
session_start();
header('Content-Type: application/json');

// Show errors for debugging (remove in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

$username = $_SESSION['username'];

// Database connection
include 'db_connect.php'; // Make sure this connects correctly

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validate required fields
    if (!isset($_POST['preferences'], $_POST['allergies'], $_POST['foodPreferences'], $_POST['allergiesAnswer'], $_POST['dietaryDiseases'], $_POST['dietaryRestrictions'])) {
        echo json_encode(['success' => false, 'message' => 'Missing form data']);
        exit();
    }

    // Extract and sanitize form data
    $preferences = json_decode($_POST['preferences'], true);
    $allergies = json_decode($_POST['allergies'], true);
    $foodPreferences = $_POST['foodPreferences'];
    $allergiesAnswer = $_POST['allergiesAnswer'];
    $dietaryDiseases = $_POST['dietaryDiseases'];
    $dietaryRestrictions = $_POST['dietaryRestrictions'];

    // Safely implode arrays into strings
    $preferencesStr = is_array($preferences) ? implode(', ', $preferences) : '';
    $allergiesStr = is_array($allergies) ? implode(', ', $allergies) : '';

    // Update user preferences in the user table
    $sql = "UPDATE user SET 
                food_preferences = ?, 
                dietary_diseases = ?, 
                dietary_restrictions = ?, 
                allergies = ?, 
                modified_at = CURRENT_TIMESTAMP
            WHERE username = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param(
        "sssss",
        $preferencesStr,
        $dietaryDiseases,
        $dietaryRestrictions,
        $allergiesStr,
        $username
    );

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Preferences saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error saving preferences: ' . $stmt->error]);
    }

    $stmt->close();
    $conn->close();

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
