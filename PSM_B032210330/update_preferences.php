<?php
session_start(); // Start the session to access user ID

// Include the database connection file
include 'DBConnection.php';

// Assuming the user ID is stored in the session
$userId = $_SESSION['user_id']; // Replace with your actual session variable

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the form data
    $preferences = json_decode($_POST['preferences'], true);
    $diseases = json_decode($_POST['diseases'], true);
    $restrictions = json_decode($_POST['restrictions'], true);
    $allergies = json_decode($_POST['allergies'], true);
    $foodPreferences = $_POST['foodPreferences'];
    $dietaryDiseases = $_POST['dietaryDiseases'];
    $dietaryRestrictions = $_POST['dietaryRestrictions'];
    $allergiesInput = $_POST['allergies'];

    // Create a new instance of the DBConnection class
    $db = new DBConnection();
    $conn = $db->getConnection();

    // Prepare and bind the SQL statement to update user preferences
    $stmt = $conn->prepare("UPDATE user SET food_preferences = ?, dietary_diseases = ?, dietary_restrictions = ?, allergies = ? WHERE id = ?");
    $stmt->bind_param("ssssi", implode(', ', $preferences), implode(', ', $diseases), implode(', ', $restrictions), implode(', ', $allergies), $userId);

    // Execute the statement and check for success
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Preferences updated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $stmt->error]);
    }

    // Close the statement and connection
    $stmt->close();
    $db->closeConnection();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
