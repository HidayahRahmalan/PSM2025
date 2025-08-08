<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Check if request ID is provided
if (!isset($_GET['reqID'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit();
}

$reqID = $_GET['reqID'];

try {
    // Get the request details
    $stmt = $conn->prepare("
        SELECT r.ReqID, r.Type, r.Description, r.Status, r.RequestedDate, r.BookID, r.RoomID, r.StudID,
               CONCAT(rm.RoomNo, ' (', h.Name, ')') as RoomInfo,
               s.FullName as StudentName,
               COALESCE(e.FullName, 'Haven''t any staff to incharge') as EmpName,
               COALESCE(e.Role, '-') as EmpRole
        FROM REQUEST r
        LEFT JOIN ROOM rm ON r.RoomID = rm.RoomID
        LEFT JOIN HOSTEL h ON rm.HostID = h.HostID
        LEFT JOIN EMPLOYEE e ON r.EmpID = e.EmpID
        LEFT JOIN STUDENT s ON r.StudID = s.StudID
        WHERE r.ReqID = ? AND r.Type IN ('MAINTENANCE', 'COMPLAINT')
    ");
    
    $stmt->bind_param("s", $reqID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit();
    }
    
    $request = $result->fetch_assoc();
    
    // Format the date
    $request['RequestedDate'] = date('d/m/Y', strtotime($request['RequestedDate']));
    
    // Return the result as JSON
    header('Content-Type: application/json');
    echo json_encode($request);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    error_log("Error getting request details: " . $e->getMessage());
}
?> 