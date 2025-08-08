<?php
session_start();
include 'dbConnection.php';

// Check if user is logged in
if (!isset($_SESSION['studID'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get roomId from query parameters
$roomId = isset($_GET['roomId']) ? $_GET['roomId'] : '';

if (empty($roomId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing roomId parameter']);
    exit();
}

try {
    // Get room information
    $stmt = $conn->prepare("
        SELECT r.*, h.Name as HostelName
        FROM ROOM r
        JOIN HOSTEL h ON r.HostID = h.HostID
        WHERE r.RoomID = ?
    ");
    $stmt->bind_param("s", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Room not found']);
        exit();
    }
    
    $roomInfo = $result->fetch_assoc();
    
    // Get current occupants with their room sharing style
    $stmt = $conn->prepare("
        SELECT s.StudID, s.FullName, s.RoomSharingStyle
        FROM BOOKING b
        JOIN STUDENT s ON b.StudID = s.StudID
        JOIN SEMESTER sem ON b.SemID = sem.SemID
        WHERE b.RoomID = ?
        AND b.Status = 'APPROVED'
        AND CURDATE() BETWEEN sem.CheckInDate AND sem.CheckOutDate
    ");
    
    $stmt->bind_param("s", $roomId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $occupants = [];
    while ($row = $result->fetch_assoc()) {
        $occupants[] = $row;
    }
    
    // Return both room info and occupants
    echo json_encode([
        'room' => $roomInfo,
        'occupants' => $occupants
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    error_log("Error in studGetRoomDetailedInfo.php: " . $e->getMessage());
}
?> 