<?php
session_start();
// Include the database connection file
include 'DBConnection.php';

// Create a new instance of the DBConnection class
$db = new DBConnection();
$conn = $db->getConnection();

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the form data
    $fullName = $_POST['fullName'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];

    // Hash the password for security
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Create a new instance of the DBConnection class
    $db = new DBConnection();
    $conn = $db->getConnection();

    // Check if the username already exists
    $stmt = $conn->prepare("SELECT * FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username is already taken.']);
        exit;
    }

    // Check if the email already exists
    $stmt = $conn->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email is already registered.']);
        exit;
    }

    // Prepare and bind the SQL statement for inserting the new user
    $stmt = $conn->prepare("INSERT INTO user (fullName, username, email, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $fullName, $username, $email, $hashedPassword);

    // Execute the statement and check for success
    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        $_SESSION['username'] = $username;
        $_SESSION['id'] = $userId;

        echo json_encode(['success' => true, 'message' => 'User  registered successfully.']);
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
