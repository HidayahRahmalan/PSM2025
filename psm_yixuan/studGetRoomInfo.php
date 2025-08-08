<?php
// Start session and include database connection
session_start();
include 'dbConnection.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['studID'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Get room ID from request
$roomId = isset($_GET['roomId']) ? $_GET['roomId'] : '';

if (empty($roomId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Room ID is required']);
    exit();
}

try {
    // Prepare SQL query to get room information including hostel name
    $stmt = $conn->prepare("
        SELECT r.*, h.Name as HostelName
        FROM ROOM r
        JOIN HOSTEL h ON r.HostID = h.HostID
        WHERE r.RoomID = ?
    ");
    
    $stmt->bind_param("s", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $roomInfo = $result->fetch_assoc();
        echo json_encode($roomInfo);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Room not found']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error in getRoomInfo.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

$conn->close();
?> 