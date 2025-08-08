
<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not hostel staff
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOSTEL STAFF') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if room ID is provided
if (!isset($_GET['roomID'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Room ID is required']);
    exit();
}

$roomID = $_GET['roomID'];

try {
    // Get room information
    $stmt = $conn->prepare("
        SELECT r.RoomID, r.RoomNo, r.Capacity, r.CurrentOccupancy, r.Status, r.Type, r.Availability,
               h.Name as HostelName
        FROM ROOM r
        JOIN HOSTEL h ON r.HostID = h.HostID
        WHERE r.RoomID = ?
    ");
    $stmt->bind_param("s", $roomID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Room not found']);
        exit();
    }
    
    $roomInfo = $result->fetch_assoc();
    
    // Extract base room number without type suffix
    $baseRoomNo = preg_replace('/[A-E]$/', '', $roomInfo['RoomNo']);
    
    // Get all room types for this base room number
    $stmt = $conn->prepare("
        SELECT r.RoomID, r.RoomNo, r.Type, r.Capacity, r.CurrentOccupancy, r.Availability, r.Status
        FROM ROOM r
        JOIN HOSTEL h ON r.HostID = h.HostID
        WHERE r.RoomNo LIKE CONCAT(?, '%') AND h.Name = ?
        ORDER BY r.Type
    ");
    $stmt->bind_param("ss", $baseRoomNo, $roomInfo['HostelName']);
    $stmt->execute();
    $roomTypesResult = $stmt->get_result();
    
    $roomTypes = [];
    while ($row = $roomTypesResult->fetch_assoc()) {
        $roomTypes[] = $row;
    }
    
    // Get current occupants
    $stmt = $conn->prepare("
        SELECT s.FullName, s.RoomSharingStyle, r.RoomNo
        FROM BOOKING b
        JOIN STUDENT s ON b.StudID = s.StudID
        JOIN ROOM r ON b.RoomID = r.RoomID
        WHERE r.RoomNo LIKE CONCAT(?, '%') AND b.Status = 'APPROVED'
        AND r.HostID = (SELECT HostID FROM HOSTEL WHERE Name = ?)
    ");
    $stmt->bind_param("ss", $baseRoomNo, $roomInfo['HostelName']);
    $stmt->execute();
    $occupantsResult = $stmt->get_result();
    
    $occupants = [];
    while ($row = $occupantsResult->fetch_assoc()) {
        $occupants[] = $row;
    }
    
    // Return the data as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'RoomID' => $roomInfo['RoomID'],
            'RoomNo' => $roomInfo['RoomNo'],
            'Capacity' => $roomInfo['Capacity'],
            'CurrentOccupancy' => $roomInfo['CurrentOccupancy'],
            'Status' => $roomInfo['Status'],
            'Type' => $roomInfo['Type'],
            'Availability' => $roomInfo['Availability'],
            'HostelName' => $roomInfo['HostelName'],
            'roomTypes' => $roomTypes,
            'occupants' => $occupants
        ]
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 