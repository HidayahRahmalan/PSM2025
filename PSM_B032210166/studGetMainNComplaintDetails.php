<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['studID'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Check if request ID is provided
if (!isset($_GET['reqID']) || empty($_GET['reqID'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Request ID is required']);
    exit();
}

$reqID = $_GET['reqID'];

try {
    // Prepare SQL to get request details
    $stmt = $conn->prepare("
        SELECT r.ReqID, r.Type, r.Description, r.Status, 
               DATE_FORMAT(r.RequestedDate, '%d/%m/%Y') as RequestedDate, 
               r.BookID, r.RoomID, 
               CONCAT(rm.RoomNo, ' (', h.Name, ')') as RoomInfo,
               COALESCE(e.FullName, 'Haven''t any staff to incharge') as EmpName,
               COALESCE(e.Role, '-') as EmpRole
        FROM REQUEST r
        LEFT JOIN ROOM rm ON r.RoomID = rm.RoomID
        LEFT JOIN HOSTEL h ON rm.HostID = h.HostID
        LEFT JOIN EMPLOYEE e ON r.EmpID = e.EmpID
        WHERE r.ReqID = ? AND r.StudID = ? AND r.Type IN ('MAINTENANCE', 'COMPLAINT')
    ");
    
    $stmt->bind_param("ss", $reqID, $_SESSION['studID']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $request = $result->fetch_assoc();
        header('Content-Type: application/json');
        echo json_encode($request);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Request not found or not authorized']);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
?> 