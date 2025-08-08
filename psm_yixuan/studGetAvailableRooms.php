<?php
session_start();
include 'dbConnection.php';

// Check if user is logged in
if (!isset($_SESSION['studID'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get bookID from query parameters
$bookID = isset($_GET['bookID']) ? $_GET['bookID'] : '';

if (empty($bookID)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing bookID parameter']);
    exit();
}

try {
    // First get the current booking's semester and hostel information
    $stmt = $conn->prepare("
        SELECT b.SemID, r.HostID, r.Type, r.RoomID
        FROM BOOKING b
        JOIN ROOM r ON b.RoomID = r.RoomID
        WHERE b.BookID = ? AND b.StudID = ?
    ");
    $stmt->bind_param("ss", $bookID, $_SESSION['studID']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Booking not found']);
        exit();
    }
    
    $bookingInfo = $result->fetch_assoc();
    
    // Get available rooms in the same hostel (allowing all types)
    $stmt = $conn->prepare("
        SELECT DISTINCT r.RoomID, r.RoomNo, h.Name as HostelName, r.Type as RoomType
        FROM ROOM r
        JOIN HOSTEL h ON r.HostID = h.HostID
        WHERE r.HostID = ? 
        AND r.Status = 'ACTIVE'
        AND r.Availability = 'AVAILABLE'
        AND r.RoomID != ?
        AND r.CurrentOccupancy < r.Capacity
        AND NOT EXISTS (
            SELECT 1 
            FROM REQUEST req 
            WHERE req.RoomID = r.RoomID 
            AND req.Status = 'APPROVED'
            AND req.Type = 'ROOM CHANGE'
        )
        ORDER BY r.Type, r.RoomNo
    ");
    
    $stmt->bind_param("ss", $bookingInfo['HostID'], $bookingInfo['RoomID']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $availableRooms = [];
    while ($row = $result->fetch_assoc()) {
        $availableRooms[] = $row;
    }
    
    echo json_encode($availableRooms);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
    error_log("Error in studGetAvailableRooms.php: " . $e->getMessage());
}
?> 