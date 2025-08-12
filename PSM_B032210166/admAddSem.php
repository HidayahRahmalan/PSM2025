<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: staffMainPage.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $academicYear = $_POST['academicYear'];
    $semester = $_POST['semester'];
    $checkInDate = $_POST['checkInDate'];
    $checkOutDate = $_POST['checkOutDate'];
    $hostelFee = $_POST['hostelFee'];
    
    // Validate academic year format (XXXX/XXXX)
    if (!preg_match('/^\d{4}\/\d{4}$/', $academicYear)) {
        echo json_encode(['success' => false, 'error' => 'Invalid academic year format. Use XXXX/XXXX']);
        exit();
    }
    
    // Validate dates
    if (strtotime($checkOutDate) <= strtotime($checkInDate)) {
        echo json_encode(['success' => false, 'error' => 'Check-out date must be after check-in date']);
        exit();
    }
    
    // Validate hostel fee
    if (!is_numeric($hostelFee) || $hostelFee <= 0) {
        echo json_encode(['success' => false, 'error' => 'Hostel fee must be greater than 0']);
        exit();
    }
    
    // Check for duplicate semester in academic year
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM SEMESTER WHERE AcademicYear = ? AND Semester = ?");
    $stmt->bind_param("si", $academicYear, $semester);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'This semester already exists for the selected academic year']);
        exit();
    }
    
    // Insert new semester
    try {
        $stmt = $conn->prepare("INSERT INTO SEMESTER (AcademicYear, Semester, CheckInDate, CheckOutDate, HostelFee) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sissd", $academicYear, $semester, $checkInDate, $checkOutDate, $hostelFee);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to add semester']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit();
}

// Return error if not POST request
echo json_encode(['success' => false, 'error' => 'Invalid request method']);
?> 