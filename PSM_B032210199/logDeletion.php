<?php
// logDeletion.php
header('Content-Type: application/json');

// Include DB connection
require_once 'dbConnection.php';

// Read incoming JSON data
$data = json_decode(file_get_contents('php://input'), true);

$datasetID = $data['datasetID'] ?? null;
$userID = $data['userID'] ?? null;

if (!$datasetID || !$userID) {
    echo json_encode(['success' => false, 'error' => 'Missing datasetID or userID']);
    exit;
}

// Prepare the audit message
$operation = "Deleted dataset ID: " . $datasetID;

// Insert into audit_trail
$stmt = $conn->prepare("INSERT INTO audit_trail (Userid, operation) VALUES (?, ?)");
$stmt->bind_param("ss", $userID, $operation);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to insert audit log']);
}

$stmt->close();
$conn->close();
?>
