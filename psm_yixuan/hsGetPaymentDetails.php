<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not hostel staff
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOSTEL STAFF') {
    header("Location: staffMainPage.php");
    exit();
}

// Get payment ID from GET request
if (!isset($_GET['pymtID'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
    exit();
}

$pymtID = $_GET['pymtID'];

try {
    // Get the payment details
    $stmt = $conn->prepare("
        SELECT p.PymtID, p.AmountPaid, p.Balance, p.Status, p.PaymentDate, 
               p.BookID, e.FullName as EmpName, e.EmpID, b.StudID, s.SemID, st.FullName as StudentName,
               CONCAT(r.RoomNo, ' (', h.Name, ')') as RoomInfo,
               CONCAT('Year ', s.AcademicYear, ' Semester ', s.Semester) as SemesterInfo,
               s.HostelFee
        FROM PAYMENT p
        JOIN BOOKING b ON p.BookID = b.BookID
        JOIN STUDENT st ON b.StudID = st.StudID
        JOIN ROOM r ON b.RoomID = r.RoomID
        JOIN HOSTEL h ON r.HostID = h.HostID
        JOIN SEMESTER s ON b.SemID = s.SemID
        LEFT JOIN EMPLOYEE e ON p.EmpID = e.EmpID
        WHERE p.PymtID = ?
    ");
    $stmt->bind_param("s", $pymtID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Payment not found']);
        exit();
    }
    
    // Fetch payment data
    $payment = $result->fetch_assoc();
    
    // Return the payment details
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'payment' => $payment
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit();
}
?> 