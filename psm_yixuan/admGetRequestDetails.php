<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Check if request ID is provided
if (!isset($_GET['reqID'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Request ID is required']);
    exit();
}

$reqID = $_GET['reqID'];

try {
    // Get request details
    $stmt = $conn->prepare("SELECT r.ReqID, r.Type, r.Description, r.Status, r.RequestedDate, r.BookID, r.RoomID, r.StudID,
                           CONCAT(rm.RoomNo, ' (', h.Name, ')') as RoomInfo,
                           s.FullName as StudentName
                           FROM REQUEST r
                           LEFT JOIN ROOM rm ON r.RoomID = rm.RoomID
                           LEFT JOIN HOSTEL h ON rm.HostID = h.HostID
                           LEFT JOIN STUDENT s ON r.StudID = s.StudID
                           WHERE r.ReqID = ? AND r.Type = 'ROOM CHANGE'");
    $stmt->bind_param("s", $reqID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Request not found']);
        exit();
    }
    
    $request = $result->fetch_assoc();
    
    // Format the date
    $request['RequestedDate'] = date('d/m/Y', strtotime($request['RequestedDate']));
    
    // Return the data as JSON
    header('Content-Type: application/json');
    echo json_encode($request);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 