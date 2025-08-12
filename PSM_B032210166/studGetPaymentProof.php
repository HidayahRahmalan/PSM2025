<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['studID']) && !isset($_SESSION['empId'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
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
    // Check if it's a student or employee access
    if (isset($_SESSION['studID'])) {
        $studID = $_SESSION['studID'];
        
        // Query for student access
        $stmt = $conn->prepare("
            SELECT p.PymtID, p.PymtProof, p.AmountPaid, p.Balance, p.Status, p.PaymentDate, 
                   p.BookID, e.FullName as EmpName, b.StudID, s.SemID, st.FullName as StudentName,
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
            WHERE p.PymtID = ? AND b.StudID = ?
        ");
        $stmt->bind_param("ss", $pymtID, $studID);
    } else {
        // Employee access
        $empId = $_SESSION['empId'];
        
        // Query for employee access
        $stmt = $conn->prepare("
            SELECT p.PymtID, p.PymtProof, p.AmountPaid, p.Balance, p.Status, p.PaymentDate, 
                   p.BookID, e.FullName as EmpName, b.StudID, s.SemID, st.FullName as StudentName,
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
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Payment not found or not accessible']);
        exit();
    }
    
    // Fetch payment data
    $payment = $result->fetch_assoc();
    
    $proof = $payment['PymtProof'];
    $proofImage = '';
    if ($proof && (strpos($proof, 'http://') === 0 || strpos($proof, 'https://') === 0)) {
        // It's a URL, return as is
        $proofImage = $proof;
    } else if ($proof) {
        // It's image data (binary), encode as base64
        $imageBase64 = base64_encode($proof);
        $imageType = 'image/jpeg'; // Assuming it's a JPEG image; you can add logic to detect the image type
        $proofImage = 'data:' . $imageType . ';base64,' . $imageBase64;
    }
    // Remove the binary data from the payment array before sending to client
    unset($payment['PymtProof']);
    
    // Return the payment details and image or URL
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'payment' => $payment,
        'proofImage' => $proofImage
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit();
}
?> 