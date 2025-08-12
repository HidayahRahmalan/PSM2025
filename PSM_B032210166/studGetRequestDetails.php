<?php
session_start();
include 'dbConnection.php';

// Check if user is logged in
if (!isset($_SESSION['studID'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Check if request ID is provided
if (!isset($_GET['reqID'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Request ID is required']);
    exit();
}

$reqID = $_GET['reqID'];
$studID = $_SESSION['studID'];

try {
    // Prepare and execute query
    $stmt = $conn->prepare("
        SELECT r.ReqID, r.Description, r.Status, r.RequestedDate, r.BookID, r.RoomID,
               CONCAT(rm.RoomNo, ' (', h.Name, ')') as RoomInfo,
               COALESCE(e.FullName, 'Haven''t any staff to incharge') as EmpName,
               COALESCE(e.Role, '-') as EmpRole
        FROM REQUEST r
        LEFT JOIN ROOM rm ON r.RoomID = rm.RoomID
        LEFT JOIN HOSTEL h ON rm.HostID = h.HostID
        LEFT JOIN EMPLOYEE e ON r.EmpID = e.EmpID
        WHERE r.ReqID = ? AND r.StudID = ? AND r.Type = 'ROOM CHANGE'
    ");
    
    $stmt->bind_param("ss", $reqID, $studID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Format the date
        $row['RequestedDate'] = date('Y-m-d', strtotime($row['RequestedDate']));
        echo json_encode($row);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Request not found']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    error_log("Error in studGetRequestDetails.php: " . $e->getMessage());
}

$conn->close();
?> 