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

// Initialize search and sort parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$searchDateFrom = isset($_GET['searchDateFrom']) ? $_GET['searchDateFrom'] : '';
$searchDateTo = isset($_GET['searchDateTo']) ? $_GET['searchDateTo'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'PaymentDate';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';
$studentName = isset($_GET['studentName']) ? $_GET['studentName'] : '';
$semesterID = isset($_GET['semesterID']) ? $_GET['semesterID'] : '';

// Get all payment methods for dropdown
$paymentMethods = ['TNG', 'ONLINE BANKING', 'DEBIT/CREDIT CARD'];

// Get all payment statuses for dropdown
$paymentStatuses = ['PENDING', 'COMPLETED', 'REJECTED'];

// Get all payment IDs for dropdown
$paymentIDs = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT p.PymtID 
                           FROM PAYMENT p
                           JOIN BOOKING b ON p.BookID = b.BookID
                           WHERE p.EmpID = ? OR p.EmpID IS NULL
                           ORDER BY p.PymtID DESC");
    $stmt->bind_param("s", $_SESSION['empId']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $paymentIDs[] = $row['PymtID'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting payment IDs: " . $e->getMessage());
}

// Get all booking IDs for dropdown
$bookingIDs = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT b.BookID 
                           FROM BOOKING b
                           JOIN PAYMENT p ON b.BookID = p.BookID
                           WHERE p.EmpID = ? OR p.EmpID IS NULL
                           ORDER BY b.BookID DESC");
    $stmt->bind_param("s", $_SESSION['empId']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookingIDs[] = $row['BookID'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting booking IDs: " . $e->getMessage());
}

// Get all semesters for dropdown
$semesters = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT s.SemID, CONCAT('Year ', s.AcademicYear, ' Semester ', s.Semester) as SemesterInfo
                           FROM SEMESTER s
                           JOIN BOOKING b ON s.SemID = b.SemID
                           JOIN PAYMENT p ON b.BookID = p.BookID
                           WHERE p.EmpID = ? OR p.EmpID IS NULL
                           ORDER BY s.AcademicYear DESC, s.Semester DESC");
    $stmt->bind_param("s", $_SESSION['empId']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $semesters[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting semesters: " . $e->getMessage());
}

// Add debug logging for search parameters
error_log("Search Criteria: " . $searchCriteria);
error_log("Search Value: " . $searchValue);
error_log("Student Name: " . $studentName);
error_log("Semester ID: " . $semesterID);
error_log("Date From: " . $searchDateFrom);
error_log("Date To: " . $searchDateTo);

// Find bookings without payment records (students who haven't started payment process)
$unpaidBookings = [];
try {
    // Using a LEFT JOIN approach to find bookings without payments
    // Only get the latest booking per student per semester
    $stmt = $conn->prepare("
        SELECT b.BookID, b.BookingDate, b.Status, s.FullName as StudentName, s.StudID, 
               CONCAT(r.RoomNo, ' (', h.Name, ')') as RoomInfo,
               CONCAT('Year ', sem.AcademicYear, ' Semester ', sem.Semester) as SemesterInfo,
               sem.SemID, sem.HostelFee as Fee
        FROM BOOKING b
        JOIN STUDENT s ON b.StudID = s.StudID
        JOIN ROOM r ON b.RoomID = r.RoomID
        JOIN HOSTEL h ON r.HostID = h.HostID
        JOIN SEMESTER sem ON b.SemID = sem.SemID
        LEFT JOIN PAYMENT p ON b.BookID = p.BookID
        WHERE p.PymtID IS NULL 
        AND b.Status = 'APPROVED'
        AND b.BookID IN (
            SELECT MAX(b2.BookID)
            FROM BOOKING b2
            WHERE b2.StudID = b.StudID AND b2.SemID = b.SemID
            GROUP BY b2.StudID, b2.SemID
        )
        ORDER BY b.BookingDate DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $unpaidBookings[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting unpaid bookings: " . $e->getMessage());
}

// Build the SQL query based on search criteria
$sql = "SELECT p.PymtID, p.AmountPaid, p.Balance, p.Status, p.PaymentDate, 
               p.BookID, p.EmpID, s.FullName as StudentName, b.StudID,
               CONCAT(r.RoomNo, ' (', h.Name, ')') as RoomInfo,
               CONCAT('Year ', sem.AcademicYear, ' Semester ', sem.Semester) as SemesterInfo,
               sem.SemID
        FROM PAYMENT p
        JOIN BOOKING b ON p.BookID = b.BookID
        JOIN STUDENT s ON b.StudID = s.StudID
        JOIN ROOM r ON b.RoomID = r.RoomID
        JOIN HOSTEL h ON r.HostID = h.HostID
        JOIN SEMESTER sem ON b.SemID = sem.SemID
        WHERE 1=1";

$params = [];
$types = "";

// Add search conditions
if ($searchCriteria === 'Status' && !empty($searchValue)) {
    $sql .= " AND p.Status = ?";
    $params[] = $searchValue;
    $types .= "s";
    error_log("Adding Status condition: " . $searchValue);
} elseif ($searchCriteria === 'BookID' && !empty($searchValue)) {
    $sql .= " AND p.BookID = ?";
    $params[] = $searchValue;
    $types .= "s";
    error_log("Adding BookID condition: " . $searchValue);
} elseif ($searchCriteria === 'StudentName' && !empty($studentName)) {
    $sql .= " AND UPPER(s.FullName) LIKE UPPER(?)";
    $params[] = "%" . $studentName . "%";
    $types .= "s";
    error_log("Adding StudentName condition: " . $studentName);
} elseif ($searchCriteria === 'SemesterID' && !empty($semesterID)) {
    $sql .= " AND sem.SemID = ?";
    $params[] = $semesterID;
    $types .= "s";
    error_log("Adding SemesterID condition: " . $semesterID);
} elseif ($searchCriteria === 'Date' && !empty($searchDateFrom) && !empty($searchDateTo)) {
    $sql .= " AND p.PaymentDate BETWEEN ? AND ?";
    $params[] = $searchDateFrom;
    $params[] = $searchDateTo;
    $types .= "ss";
    error_log("Adding Date condition: " . $searchDateFrom . " to " . $searchDateTo);
}

// Add sorting
$sql .= " ORDER BY " . $sortBy . " " . $sortOrder;

// Execute the query
$payments = [];
try {
    $stmt = $conn->prepare($sql);
    
    // Only bind parameters if there are any
    if (!empty($params) && !empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting payments: " . $e->getMessage());
}

// Process payment update (when form is submitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment'])) {
    $pymtID = $_POST['pymtID'];
    $status = $_POST['status'];
    $amountPaid = $_POST['amountPaid'];
    $balance = $_POST['balance'];
    $empID = $_SESSION['empId'];
    
    try {
        $stmt = $conn->prepare("UPDATE PAYMENT SET Status = ?, AmountPaid = ?, Balance = ?, EmpID = ? WHERE PymtID = ?");
        $stmt->bind_param("sddss", $status, $amountPaid, $balance, $empID, $pymtID);
        $stmt->execute();
        
        // Redirect to refresh the page
        header("Location: hsPymtMgmt.php?success=Payment updated successfully");
        exit();
    } catch (Exception $e) {
        error_log("Error updating payment: " . $e->getMessage());
        header("Location: hsPymtMgmt.php?error=Failed to update payment: " . $e->getMessage());
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/hsNav.css">
    <style>
        /* Add this at the beginning of the style section */
        * {
            font-family: Arial, sans-serif !important;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f8ff;
        }

        .navbar {
            font-family: Arial, sans-serif;
        }
        
        :root {
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --secondary: #6c757d;
        }
        
        .main-content {
            padding: 30px 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .page-header h2 {
            color: var(--primary-color);
            font-size: 28px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
            font-family: Arial, sans-serif;
        }
        
        /* Search Section */
        .search-section {
            background-color: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .section-title {
            color: var(--primary-color);
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
            font-family: Arial, sans-serif;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            align-items: end;
        }
        
        .search-form .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: bold;
            font-family: Arial, sans-serif;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 15px;
            font-family: Arial, sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(44, 157, 255, 0.1);
        }
        
        /* Table Styles */
        .table-container {
            margin-top: 30px;
            overflow-x: auto;
            max-width: 100%;
            white-space: nowrap;
        }

        table {
            width: 100%;
            min-width: 1500px;
            background-color: white;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            font-family: Arial, sans-serif;
            font-size: 16px;
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #ddd;
            text-align: left;
            word-wrap: break-word;
            white-space: normal;
            line-height: 1.4;
        }

        /* Column Widths */
        th:nth-child(1), td:nth-child(1) { min-width: 100px; } /* PymtID */
        th:nth-child(2), td:nth-child(2) { min-width: 150px; } /* Amount Paid */
        th:nth-child(3), td:nth-child(3) { min-width: 120px; } /* Balance */
        th:nth-child(4), td:nth-child(4) { min-width: 120px; } /* Status */
        th:nth-child(5), td:nth-child(5) { min-width: 120px; } /* Payment Date */
        th:nth-child(6), td:nth-child(6) { min-width: 100px; } /* BookID */
        th:nth-child(7), td:nth-child(7) { min-width: 180px; } /* Room Info */
        th:nth-child(8), td:nth-child(8) { min-width: 200px; } /* Student Name */
        th:nth-child(9), td:nth-child(9) { min-width: 180px; } /* Semester */
        th:nth-child(10), td:nth-child(10) { min-width: 100px; } /* Action */

        th {
            background-color: #25408f;
            color: white;
            font-weight: bold;
            height: auto;
            min-height: 45px;
            padding: 15px;
        }

        td {
            height: auto;
            min-height: 40px;
            padding: 15px;
        }

        tr {
            transition: background-color 0.3s ease;
        }

        tr:hover {
            background-color: #edf3ff !important;
            cursor: pointer;
            box-shadow: 0 3px 5px rgba(0,0,0,0.1);
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 6px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            width: 100px;
        }

        .status-pending {
            background-color: var(--warning);
            color: #000;
        }
        
        .status-completed {
            background-color: var(--success);
            color: #fff;
        }
        
        .status-rejected {
            background-color: var(--danger);
            color: #fff;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-buttons .btn {
            padding: 6px 10px;
            font-size: 16px;
            height: 35px;
            width: 90px;
            white-space: nowrap;
            margin-bottom: 5px;
        }

        /* Button Styles */
        .btn {
            font-family: Arial, sans-serif;
            font-size: 16px;
            transition: background-color 0.3s ease;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            padding: 8px 20px;
            height: 38px;
        }

        .btn-primary {
            background-color: #25408f;
            color: white;
        }

        .btn-primary:hover {
            background-color: #3883ce;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .btn-gold {
            background-color: #ffc107 !important;
            color: black !important;
        }

        .btn-gold:hover {
            background-color: #e0a800 !important;
            color: black !important;
        }

        .search-btn-group {
            display: flex;
            gap: 10px;
            align-items: center;
            white-space: nowrap;
        }

        .search-btn-group .btn {
            min-width: 150px;
        }
        
        .search-btn-group .btn-gold {
            min-width: 180px;
        }

        /* Date Range Inputs */
        .date-range-container {
            grid-column: span 2;
            display: flex;
            gap: 10px;
            margin-right: 20px;
        }

        .date-range-container .form-group {
            flex: 1;
            margin-right: 10px;
        }

        .date-range-container .form-group:last-child {
            margin-right: 0;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-title {
            color: var(--primary-color);
            font-size: 20px;
            margin: 0;
            font-family: Arial, sans-serif;
            font-weight: bold;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-body .form-group {
            margin-bottom: 15px;
        }

        .modal-body label {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            color: var(--text-dark);
            font-family: Arial, sans-serif;
        }

        .modal-body input,
        .modal-body textarea,
        .modal-body select {
            width: 100%;
            box-sizing: border-box;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 15px;
            font-family: Arial, sans-serif;
        }

        .modal-body textarea {
            resize: vertical;
            min-height: 100px;
        }

        .modal-body small {
            color: var(--text-light);
            font-size: 13px;
            margin-top: 4px;
            display: block;
            font-family: Arial, sans-serif;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 0 0;
            border-top: 1px solid var(--border-color);
        }
        
        .modal-footer .btn {
            width: auto;
            min-width: 100px;
            margin: 0;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* No Results Message */
        .no-results {
            text-align: center;
            padding: 20px;
            color: var(--text-dark);
            font-size: 16px;
            background-color: var(--light-bg);
            border-radius: 8px;
            font-family: Arial, sans-serif;
            margin: 20px 0;
        }
        
        /* Payment Proof Image */
        .img-fluid {
            max-width: 100%;
            height: auto;
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .modal-img-container {
            text-align: center;
            margin: 20px 0;
            overflow: hidden;
        }
        
        /* Image Modal for zoomed view */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            padding-top: 50px;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.9);
        }

        .image-modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
        }

        .image-modal .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
        }

        .image-modal .close:hover,
        .image-modal .close:focus {
            color: #bbb;
            text-decoration: none;
            cursor: pointer;
        }

        /* Results Count */
        .results-count {
            margin: 0 0 15px 0;
            font-size: 18px;
            font-weight: bold;
            font-family: Arial, sans-serif;
            color: #25408f
        }
        
        /* Pending Payments Summary */
        .pending-payments-summary {
            background-color: #fff8e1;
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            border-left: 5px solid #ffc107;
        }
        
        .pending-summary {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 20px;
        }
        
        .pending-stat {
            flex: 1;
            min-width: 250px;
            padding: 12px 15px;
            background-color: white;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .pending-label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
            color: #555;
            font-family: Arial, sans-serif;
        }
        
        .pending-value {
            display: block;
            font-weight: bold;
            font-size: 22px;
            color: #dc3545;
            font-family: Arial, sans-serif;
        }

        .pending-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* Unpaid Bookings Section */
        .unpaid-bookings-section {
            margin-top: 30px;
            padding: 20px;
            background-color: #fff8e1;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .unpaid-summary {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 20px;
        }
        
        .unpaid-stat {
            flex: 1;
            min-width: 250px;
            padding: 12px 15px;
            background-color: white;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .unpaid-label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
            color: #555;
            font-family: Arial, sans-serif;
        }
        
        .unpaid-value {
            display: block;
            font-weight: bold;
            font-size: 22px;
            color: #dc3545;
            font-family: Arial, sans-serif;
        }

        .unpaid-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .unpaid-info-note {
            background-color: #e7f3ff;
            border-left: 4px solid #0d6efd;
            padding: 12px 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #333;
            font-family: Arial, sans-serif;
            border-radius: 4px;
        }
        
        .unpaid-info-note i {
            color: #0d6efd;
            margin-right: 5px;
        }
        
        /* Payment Dashboard */
        .payment-dashboard {
            margin-bottom: 30px;
            background-color: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .dashboard-card {
            background-color: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
        }
        
        .total-card::before {
            background-color: #25408f;
        }
        
        .unpaid-card::before {
            background-color: #dc3545;
        }
        
        .pending-card::before {
            background-color: #ffc107;
        }
        
        .completed-card::before {
            background-color: #28a745;
        }
        
        .rejected-card::before {
            background-color: #6c757d;
        }
        
        .dashboard-card h4 {
            margin: 0;
            font-size: 16px;
            color: #555;
            font-family: Arial, sans-serif;
            margin-bottom: 10px;
        }
        
        .card-value {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
            color: #25408f;
            font-family: Arial, sans-serif;
        }
        
        .card-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
            font-family: Arial, sans-serif;
        }
        
        .btn-dashboard {
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            color: #333;
            padding: 4px 10px;
            font-size: 12px;
        }
        
        .btn-dashboard:hover {
            background-color: #e9ecef;
        }
    </style>
</head>
<body>
    <?php include 'includes/hsNav.php'; ?>

    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h2>Payment Management</h2>
            </div>

            <!-- Success and Error Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Payment Dashboard -->
            <?php
            // Count bookings and payments by status
            $totalBookings = count($unpaidBookings);
            $pendingPayments = 0;
            $totalPendingBalance = 0;
            
            foreach ($payments as $payment) {
                if (strtolower($payment['Status']) === 'pending') {
                    $pendingPayments++;
                    $totalPendingBalance += $payment['Balance'];
                }
            }
            
            // Calculate unpaid fees
            $totalUnpaidFees = 0;
            foreach ($unpaidBookings as $booking) {
                $totalUnpaidFees += $booking['Fee'];
            }
            ?>
            
            <section class="payment-dashboard">
                <h3 class="section-title">Payment Overview Dashboard</h3>
                <div class="dashboard-grid">
                    <div class="dashboard-card unpaid-card">
                        <h4>Unpaid Bookings</h4>
                        <div class="card-value"><?php echo $totalBookings; ?></div>
                        <div class="card-info">RM <?php echo number_format($totalUnpaidFees, 2); ?> outstanding</div>
                        <button class="btn btn-sm btn-dashboard" onclick="openUnpaidModal()">View Details</button>
                    </div>
                    
                    <div class="dashboard-card pending-card">
                        <h4>Pending Payments</h4>
                        <div class="card-value"><?php echo $pendingPayments; ?></div>
                        <div class="card-info">RM <?php echo number_format($totalPendingBalance, 2); ?> balance due</div>
                        <button class="btn btn-sm btn-dashboard" onclick="filterPendingPayments()">View Details</button>
                    </div>
                </div>
            </section>

            <!-- Search Section -->
            <div class="search-section">
                <h3 class="section-title">Search Payments</h3>
                <form action="hsPymtMgmt.php" method="GET" class="search-form">
                    <div class="form-group">
                        <label for="searchCriteria">Search By</label>
                        <select id="searchCriteria" name="searchCriteria" class="form-control" onchange="updateSearchField()">
                            <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All Records</option>
                            <option value="Status" <?php echo $searchCriteria === 'Status' ? 'selected' : ''; ?>>Status</option>
                            <option value="BookID" <?php echo $searchCriteria === 'BookID' ? 'selected' : ''; ?>>Booking ID</option>
                            <option value="StudentName" <?php echo $searchCriteria === 'StudentName' ? 'selected' : ''; ?>>Student Name</option>
                            <option value="SemesterID" <?php echo $searchCriteria === 'SemesterID' ? 'selected' : ''; ?>>Semester</option>
                            <option value="Date" <?php echo $searchCriteria === 'Date' ? 'selected' : ''; ?>>Date Range</option>
                        </select>
                    </div>

                    <!-- Search Value Field - Will be updated by JavaScript -->
                    <div id="searchValueField">
                        <!-- This will be dynamically updated based on the selected criteria -->
                    </div>

                    <div class="form-group">
                        <label for="sortBy">Sort By</label>
                        <select id="sortBy" name="sortBy" class="form-control">
                            <option value="PaymentDate" <?php echo $sortBy === 'PaymentDate' ? 'selected' : ''; ?>>Payment Date</option>
                            <option value="PymtID" <?php echo $sortBy === 'PymtID' ? 'selected' : ''; ?>>Payment ID</option>
                            <option value="BookID" <?php echo $sortBy === 'BookID' ? 'selected' : ''; ?>>Booking ID</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sortOrder">Sort Order</label>
                        <select id="sortOrder" name="sortOrder" class="form-control">
                            <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>

                    <div class="form-group search-btn-group">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <button type="button" class="btn btn-gold" onclick="printReport()">Generate Report</button>
                    </div>
                </form>
            </div>

            <!-- Unpaid Bookings Section Moved to Modal -->

            <!-- Payment Management Table -->
            <div class="table-container">
                <?php if (empty($payments)): ?>
                    <div class="no-results">
                        No payment records found. Please try a different search criteria.
                    </div>
                <?php else: ?>
                    <p class="results-count">Total Results: <?php echo count($payments); ?></p>
                    <table>
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Amount Paid (RM)</th>
                                <th>Balance (RM)</th>
                                <th>Status</th>
                                <th>Payment Date</th>
                                <th>Booking ID</th>
                                <th>Room</th>
                                <th>Student Name</th>
                                <th>Semester</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['PymtID']); ?></td>
                                    <td><?php echo number_format($payment['AmountPaid'], 2); ?></td>
                                    <td><?php echo number_format($payment['Balance'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo strtolower($payment['Status']) === 'pending' ? 'status-pending' : (strtolower($payment['Status']) === 'completed' ? 'status-completed' : 'status-rejected'); ?>">
                                            <?php echo htmlspecialchars($payment['Status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['PaymentDate']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['BookID']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['RoomInfo']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['StudentName']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['SemesterInfo']); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if (strtolower($payment['Status']) !== 'pending'): ?>
                                            <button type="button" class="btn btn-primary" 
                                                    onclick="viewPaymentProof('<?php echo htmlspecialchars($payment['PymtID']); ?>')">
                                                View
                                            </button>
                                            <?php endif; ?>
                                            
                                            <?php if (strtolower($payment['Status']) === 'pending'): ?>
                                            <button type="button" class="btn btn-primary" 
                                                    onclick="editPayment('<?php echo htmlspecialchars($payment['PymtID']); ?>', 
                                                                         '<?php echo htmlspecialchars($payment['Status']); ?>', 
                                                                         <?php echo $payment['AmountPaid']; ?>, 
                                                                         <?php echo $payment['Balance']; ?>, 
                                                                         '<?php echo htmlspecialchars($payment['Status']); ?>')">
                                                Edit
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- View Payment Proof Modal -->
    <div id="viewProofModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeProofModal()">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Payment Details</h3>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Payment ID</label>
                    <input type="text" id="viewPymtID" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Booking ID</label>
                    <input type="text" id="viewBookID" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Student Name</label>
                    <input type="text" id="viewStudentName" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Room</label>
                    <input type="text" id="viewRoomInfo" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Semester</label>
                    <input type="text" id="viewSemesterInfo" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Amount Paid (RM)</label>
                    <input type="text" id="viewAmountPaid" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Balance (RM)</label>
                    <input type="text" id="viewBalance" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <input type="text" id="viewStatus" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Payment Date</label>
                    <input type="text" id="viewPaymentDate" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label>Payment Proof</label>
                </div>
                <div id="proofImageContainer"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeProofModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Payment Modal -->
    <div id="editPaymentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Edit Payment</h3>
            </div>
            <div class="modal-body">
                <form id="editPaymentForm" action="hsPymtMgmt.php" method="POST">
                    <input type="hidden" id="pymtID" name="pymtID">
                    
                    <div class="form-group">
                        <label for="editPymtID">Payment ID</label>
                        <input type="text" id="editPymtID" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="editBookID">Booking ID</label>
                        <input type="text" id="editBookID" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="editStudentName">Student Name</label>
                        <input type="text" id="editStudentName" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="editRoomInfo">Room</label>
                        <input type="text" id="editRoomInfo" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="editSemesterInfo">Semester</label>
                        <input type="text" id="editSemesterInfo" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="amountPaid">Amount Paid (RM)</label>
                        <input type="number" id="amountPaid" name="amountPaid" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="balance">Balance (RM)</label>
                        <input type="number" id="balance" name="balance" class="form-control" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <?php foreach ($paymentStatuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>">
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="editPaymentDate">Payment Date</label>
                        <input type="text" id="editPaymentDate" class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Payment Proof</label>
                    </div>
                    <div id="currentProofContainer"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" form="editPaymentForm" name="update_payment" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Image Modal for Zoomed View -->
    <div id="imageModal" class="image-modal">
        <span class="close" onclick="closeImageModal()">&times;</span>
        <img class="image-modal-content" id="zoomedImage">
    </div>

    <!-- Unpaid Bookings Modal -->
    <div id="unpaidBookingsModal" class="modal">
        <div class="modal-content" style="max-width: 90%; width: 1200px;">
            <span class="close" onclick="closeUnpaidModal()">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Students Without Payment Records</h3>
            </div>
            <div class="modal-body" style="padding: 0;">
                <div class="unpaid-info-note" style="margin: 0 0 20px 0;">
                    <i class="fas fa-info-circle"></i> This section shows students who have booked accommodation but have not initiated any payment for their semester. Each student should make one payment per semester.
                </div>
                
                <div class="unpaid-summary" style="margin-bottom: 20px;">
                    <div class="unpaid-stat">
                        <span class="unpaid-label">Number of Unpaid Bookings:</span>
                        <span class="unpaid-value"><?php echo count($unpaidBookings); ?></span>
                    </div>
                    <div class="unpaid-stat">
                        <span class="unpaid-label">Total Outstanding Fees:</span>
                        <span class="unpaid-value">RM <?php
                            $totalFees = 0;
                            foreach ($unpaidBookings as $booking) {
                                $totalFees += $booking['Fee'];
                            }
                            echo number_format($totalFees, 2);
                        ?></span>
                    </div>
                </div>
                
                <?php if (!empty($unpaidBookings)): ?>
                <table id="unpaidTable" style="min-width: 100%;">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Semester</th>
                            <th>Room</th>
                            <th>Booking Date</th>
                            <th>Fee (RM)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unpaidBookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['BookID']); ?></td>
                            <td><?php echo htmlspecialchars($booking['StudID']); ?></td>
                            <td><?php echo htmlspecialchars($booking['StudentName']); ?></td>
                            <td><strong><?php echo htmlspecialchars($booking['SemesterInfo']); ?></strong></td>
                            <td><?php echo htmlspecialchars($booking['RoomInfo']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($booking['BookingDate'])); ?></td>
                            <td><?php echo number_format($booking['Fee'], 2); ?></td>
                            <td>
                                <button class="btn btn-primary" style="height: auto; padding: 8px 12px;" onclick="sendPaymentReminder('<?php echo htmlspecialchars($booking['StudID']); ?>', '<?php echo htmlspecialchars($booking['StudentName']); ?>', '<?php echo htmlspecialchars($booking['BookID']); ?>')">
                                    Send Reminder
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="no-results">
                    No unpaid bookings found.
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUnpaidModal()">Close</button>
                <button type="button" class="btn btn-gold" onclick="printUnpaidReport()">Generate Report</button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Show/hide search fields based on search criteria
        function updateSearchField() {
            const searchCriteria = document.getElementById('searchCriteria').value;
            const searchValueField = document.getElementById('searchValueField');
            
            // Clear previous search fields
            searchValueField.innerHTML = '';
            
            switch (searchCriteria) {
                case 'All':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div style="padding: 8px 0; font-family: Arial, sans-serif;">No search value needed</div>
                        </div>
                    `;
                    break;
                    
                case 'Status':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label for="searchValue">Status</label>
                            <select id="statusSelect" name="searchValue" class="form-control">
                                <option value="">Select Status</option>
                                <?php foreach ($paymentStatuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status); ?>" <?php echo ($searchCriteria === 'Status' && $searchValue === $status) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($status); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'BookID':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label for="searchValue">Booking ID</label>
                            <select id="bookingSelect" name="searchValue" class="form-control">
                                <option value="">Select Booking ID</option>
                                <?php foreach ($bookingIDs as $bookingID): ?>
                                <option value="<?php echo htmlspecialchars($bookingID); ?>" <?php echo ($searchCriteria === 'BookID' && $searchValue === $bookingID) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($bookingID); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'StudentName':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label for="studentName">Student Name</label>
                            <input type="text" id="studentName" name="studentName" class="form-control" value="<?php echo htmlspecialchars($studentName); ?>">
                        </div>
                    `;
                    break;
                    
                case 'SemesterID':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label for="semesterID">Semester</label>
                            <select id="semesterID" name="semesterID" class="form-control">
                                <option value="">Select Semester</option>
                                <?php foreach ($semesters as $semester): ?>
                                <option value="<?php echo htmlspecialchars($semester['SemID']); ?>" <?php echo ($searchCriteria === 'SemesterID' && $semesterID === $semester['SemID']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($semester['SemesterInfo']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'Date':
                    console.log('Creating date range fields');
                    searchValueField.innerHTML = `
                        <div class="date-range-container">
                            <div class="form-group">
                                <label for="searchDateFrom">From Date</label>
                                <input type="date" id="searchDateFrom" name="searchDateFrom" class="form-control" value="<?php echo htmlspecialchars($searchDateFrom); ?>" tabindex="1">
                            </div>
                            <div class="form-group">
                                <label for="searchDateTo">To Date</label>
                                <input type="date" id="searchDateTo" name="searchDateTo" class="form-control" value="<?php echo htmlspecialchars($searchDateTo); ?>" tabindex="2" onchange="validateDateRange()">
                            </div>
                        </div>
                    `;
                    break;
            }
        }

        // Initialize search fields on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSearchField();
            
            // Add event listener for search criteria changes
            document.getElementById('searchCriteria').addEventListener('change', function() {
                updateSearchField();
            });
            
            // Add event listener for form submission
            document.querySelector('form.search-form').addEventListener('submit', function(e) {
                const searchCriteria = document.getElementById('searchCriteria').value;
                
                // Debug: Log form data
                console.log('Search form submitted');
                console.log('Search Criteria:', searchCriteria);
                
                if (searchCriteria === 'Status') {
                    console.log('Status:', document.getElementById('statusSelect')?.value);
                } else if (searchCriteria === 'BookID') {
                    console.log('Booking ID:', document.getElementById('bookingSelect')?.value);
                } else if (searchCriteria === 'StudentName') {
                    console.log('Student Name:', document.getElementById('studentName')?.value);
                } else if (searchCriteria === 'SemesterID') {
                    console.log('Semester ID:', document.getElementById('semesterID')?.value);
                } else if (searchCriteria === 'Date') {
                    console.log('From Date:', document.querySelector('input[name="searchDateFrom"]')?.value);
                    console.log('To Date:', document.querySelector('input[name="searchDateTo"]')?.value);
                }
                
                // Special handling for date range
                if (searchCriteria === 'Date') {
                    const fromDate = document.querySelector('input[name="searchDateFrom"]').value;
                    const toDate = document.querySelector('input[name="searchDateTo"]').value;
                    
                    if (!fromDate || !toDate) {
                        alert('Please select both From Date and To Date');
                        e.preventDefault();
                        return false;
                    }
                    
                    if (fromDate && toDate && toDate < fromDate) {
                        alert('To Date cannot be earlier than From Date');
                        e.preventDefault();
                        return false;
                    }
                }
                
                // Special handling for method
                if (searchCriteria === 'OnlinePymtMethod' && document.getElementById('methodSelect')) {
                    const methodValue = document.getElementById('methodSelect').value;
                    if (!methodValue) {
                        alert('Please select a Payment Method');
                        e.preventDefault();
                        return false;
                    }
                }
                
                // Special handling for status
                if (searchCriteria === 'Status' && document.getElementById('statusSelect')) {
                    const statusValue = document.getElementById('statusSelect').value;
                    if (!statusValue) {
                        alert('Please select a Status');
                        e.preventDefault();
                        return false;
                    }
                }
                
                // Special handling for booking ID
                if (searchCriteria === 'BookID' && document.getElementById('bookingSelect')) {
                    const bookingValue = document.getElementById('bookingSelect').value;
                    if (!bookingValue) {
                        alert('Please select a Booking ID');
                        e.preventDefault();
                        return false;
                    }
                }
                
                // Special handling for student name
                if (searchCriteria === 'StudentName' && document.getElementById('studentName')) {
                    const studentNameValue = document.getElementById('studentName').value;
                    if (!studentNameValue) {
                        alert('Please enter a Student Name');
                        e.preventDefault();
                        return false;
                    }
                }
                
                // Special handling for semester
                if (searchCriteria === 'SemesterID' && document.getElementById('semesterID')) {
                    const semesterValue = document.getElementById('semesterID').value;
                    if (!semesterValue) {
                        alert('Please select a Semester');
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });

        // Validate date range
        function validateDateRange() {
            const fromDate = document.querySelector('input[name="searchDateFrom"]').value;
            const toDate = document.querySelector('input[name="searchDateTo"]').value;
            
            if (fromDate && toDate && toDate < fromDate) {
                alert('To Date cannot be earlier than From Date');
                document.querySelector('input[name="searchDateTo"]').value = '';
                return false;
            }
            return true;
        }

        // Update results count text
        document.addEventListener('DOMContentLoaded', function() {
            const resultsCount = document.querySelector('.results-count');
            if (resultsCount) {
                const count = <?php echo !empty($payments) ? count($payments) : 0; ?>;
                resultsCount.innerHTML = `Total Results: ${count}`;
            }
        });

        // Generate Report Function
        function printReport() {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Create the print content
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Payment Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .report-header {
                            text-align: center;
                            margin-bottom: 30px;
                        }
                        .report-header h1 {
                            color: #25408f;
                            font-size: 28px;
                            margin-bottom: 10px;
                            text-align: center;
                        }
                        .search-results-container {
                            text-align: left;
                            margin-left: 20px;
                        }
                        .search-results {
                            font-weight: bold;
                            color: #333;
                            font-size: 16px;
                            margin-bottom: 5px;
                        }
                        .results-count {
                            font-weight: normal;
                            color: #25408f;
                            font-size: 16px;
                            margin-bottom: 20px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 20px;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 15px;
                            text-align: left;
                            font-size: 16px;
                            font-family: Arial, sans-serif;
                            line-height: 1.4;
                        }
                        th {
                            background-color: #25408f;
                            color: white;
                            font-weight: bold;
                            height: auto;
                            min-height: 45px;
                        }
                        tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .status-badge {
                            display: inline-block;
                            padding: 6px 12px;
                            border-radius: 4px;
                            font-weight: bold;
                            text-align: center;
                        }
                        .status-pending {
                            background-color: #ffc107;
                            color: #000;
                        }
                        .status-completed {
                            background-color: #28a745;
                            color: white;
                        }
                        .status-rejected {
                            background-color: #dc3545;
                            color: white;
                        }
                        .report-footer {
                            margin-top: 30px;
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                            border-top: 1px solid #ddd;
                            padding-top: 20px;
                        }
                        @media print {
                            @page {
                                size: landscape;
                                margin: 2cm;
                            }
                            body {
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Payment Report</h1>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                    </div>
                    <div class="search-results-container">
                        <div class="search-results">Search Results</div>
                    `;

            // Add total results right after the header
            const totalResults = document.querySelector('.no-results');
            if (totalResults) {
                printContent += `<div class="results-count">${totalResults.textContent}</div></div>`;
            } else {
                const resultsCount = document.querySelector('.results-count');
                if (resultsCount) {
                    printContent += `<div class="results-count">${resultsCount.textContent}</div></div>`;
                } else {
                    const count = document.querySelectorAll('table tbody tr').length;
                    printContent += `<div class="results-count">Total Results: ${count}</div></div>`;
                }
            }
            
            // Get the table data
            const table = document.querySelector('table');
            if (table) {
                const rows = Array.from(table.rows);
                
                // Start the table
                printContent += '<table>';
                
                // Add header row
                const headerRow = rows[0];
                printContent += '<thead><tr>';
                Array.from(headerRow.cells).forEach((cell, index) => {
                    if (index !== headerRow.cells.length - 1) { // Skip the Actions column
                        printContent += `<th>${cell.textContent.trim()}</th>`;
                    }
                });
                printContent += '</tr></thead>';
                
                // Add body rows
                printContent += '<tbody>';
                rows.slice(1).forEach(row => {
                    printContent += '<tr>';
                    Array.from(row.cells).forEach((cell, index) => {
                        if (index !== row.cells.length - 1) { // Skip the Actions column
                            if (index === 4) { // Status column
                                const status = cell.textContent.trim();
                                printContent += `<td><span class="status-badge status-${status.toLowerCase()}">${status}</span></td>`;
                            } else {
                                printContent += `<td>${cell.innerHTML.trim()}</td>`;
                            }
                        }
                    });
                    printContent += '</tr>';
                });
                printContent += '</tbody>';
                printContent += '</table>';
            }
            
            // Add footer
            printContent += `
                    <div class="report-footer">
                        <p>Smart Hostel Management System &copy; ${new Date().getFullYear()}</p>
                    </div>
                </body>
                </html>
            `;
            
            // Write to the print window and print
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Wait for images to load before printing
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }

        // View Payment Proof Modal Functions
        const viewProofModal = document.getElementById('viewProofModal');
        
        function viewPaymentProof(pymtID) {
            fetch('hsGetPaymentProof.php?pymtID=' + pymtID)  // Use hostel staff endpoint
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('viewPymtID').value = data.payment.PymtID;
                        document.getElementById('viewBookID').value = data.payment.BookID;
                        document.getElementById('viewStudentName').value = data.payment.StudentName;
                        document.getElementById('viewRoomInfo').value = data.payment.RoomInfo;
                        document.getElementById('viewSemesterInfo').value = data.payment.SemesterInfo;
                        document.getElementById('viewAmountPaid').value = parseFloat(data.payment.AmountPaid).toFixed(2);
                        document.getElementById('viewBalance').value = parseFloat(data.payment.Balance).toFixed(2);
                        document.getElementById('viewStatus').value = data.payment.Status;
                        document.getElementById('viewPaymentDate').value = data.payment.PaymentDate;
                        
                        // Set the image source
                        const proofImageContainer = document.getElementById('proofImageContainer');
                        const proofValue = data.proofImage;

                        if (proofValue && proofValue.startsWith('http')) {
                            // Show as a clickable link
                            proofImageContainer.innerHTML = `<a href="${proofValue}" target="_blank" style="display:block;margin:10px 0;">
                                <i class="fa fa-external-link-alt"></i> View Stripe Receipt
                            </a>`;
                        } else if (proofValue) {
                            // Show as image
                            proofImageContainer.innerHTML = `<img class="img-fluid" src="${proofValue}" alt="Payment Proof" style="max-width:100%;height:auto;">`;
                        } else {
                            proofImageContainer.innerHTML = `<span style="color:#888;">No payment proof available.</span>`;
                        }
                        
                        // Show the modal
                        viewProofModal.style.display = 'block';
                        
                        // Set up image zoom functionality
                        document.getElementById('proofImage').onclick = function() {
                            imageModal.style.display = 'block';
                            zoomedImage.src = this.src;
                        };
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Only show alert if modal is not already displayed
                    if (viewProofModal.style.display !== 'block') {
                        alert('An error occurred while fetching the payment proof.');
                    }
                });
        }
        
        function closeProofModal() {
            viewProofModal.style.display = 'none';
        }

        // Edit Payment Modal Functions
        const editPaymentModal = document.getElementById('editPaymentModal');
        
        function editPayment(pymtID, status, amountPaid, balance) {
            // Fetch payment details first to populate all fields
            fetch('hsGetPaymentDetails.php?pymtID=' + pymtID)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Set form data
                        document.getElementById('pymtID').value = pymtID;
                        document.getElementById('editPymtID').value = pymtID;
                        document.getElementById('editBookID').value = data.payment.BookID;
                        document.getElementById('editStudentName').value = data.payment.StudentName;
                        document.getElementById('editRoomInfo').value = data.payment.RoomInfo;
                        document.getElementById('editSemesterInfo').value = data.payment.SemesterInfo;
                        document.getElementById('amountPaid').value = parseFloat(data.payment.AmountPaid).toFixed(2);
                        document.getElementById('balance').value = parseFloat(data.payment.Balance).toFixed(2);
                        document.getElementById('editPaymentDate').value = data.payment.PaymentDate;

                        // Set the status in dropdown
                        const statusDropdown = document.getElementById('status');
                        for (let i = 0; i < statusDropdown.options.length; i++) {
                            if (statusDropdown.options[i].value === data.payment.Status) {
                                statusDropdown.selectedIndex = i;
                                break;
                            }
                        }
                        
                        // Set payment proof image
                        fetch('hsGetPaymentProof.php?pymtID=' + pymtID)
                            .then(response => response.json())
                            .then(proofData => {
                                if (proofData.success) {
                                    const currentProofContainer = document.getElementById('currentProofContainer');
                                    const proofValue = proofData.proofImage;

                                    if (proofValue && (proofValue.startsWith('http://') || proofValue.startsWith('https://'))) {
                                        currentProofContainer.innerHTML = `<a href="${proofValue}" target="_blank" style="display:block;margin:10px 0;">
                                            <i class="fa fa-external-link-alt"></i> View Stripe Receipt
                                        </a>`;
                                    } else if (proofValue) {
                                        currentProofContainer.innerHTML = `<img class="img-fluid" src="${proofValue}" alt="Current Payment Proof">`;
                                    } else {
                                        currentProofContainer.innerHTML = `<span style="color:#888;">No payment proof available.</span>`;
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error fetching proof image:', error);
                            });
                        
                        // Show the edit modal
                        editPaymentModal.style.display = 'block';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Fallback to the old method if fetch fails
                    document.getElementById('pymtID').value = pymtID;
                    document.getElementById('editPymtID').value = pymtID;
                    document.getElementById('status').value = status;
                    document.getElementById('amountPaid').value = amountPaid;
                    document.getElementById('balance').value = balance;
                    
                    // Set the status in dropdown
                    const statusDropdown = document.getElementById('status');
                    for (let i = 0; i < statusDropdown.options.length; i++) {
                        if (statusDropdown.options[i].value === status) {
                            statusDropdown.selectedIndex = i;
                            break;
                        }
                    }
                    
                    // Show the edit modal
                    editPaymentModal.style.display = 'block';
                });
        }
        
        function closeEditModal() {
            editPaymentModal.style.display = 'none';
        }

        // Close modals when clicking outside of them
        window.onclick = function(event) {
            if (event.target === viewProofModal) {
                closeProofModal();
            } else if (event.target === editPaymentModal) {
                closeEditModal();
            } else if (event.target === imageModal) {
                closeImageModal();
            } else if (event.target === document.getElementById('unpaidBookingsModal')) {
                closeUnpaidModal();
            }
        }
        
        // Image zoom functionality
        const imageModal = document.getElementById('imageModal');
        const zoomedImage = document.getElementById('zoomedImage');
        
        // Close image modal
        function closeImageModal() {
            imageModal.style.display = 'none';
        }
        
        // Toggle display of unpaid bookings table
        function toggleUnpaidTable() {
            console.log('Toggle unpaid table function called');
            
            // Check if the unpaid card exists
            const unpaidCard = document.querySelector('.dashboard-card.unpaid-card');
            if (!unpaidCard) {
                console.error('Unpaid bookings card not found in the DOM');
            }
            
            const container = document.getElementById('unpaidTableContainer');
            if (container) {
                console.log('Toggling unpaid table display');
                container.style.display = container.style.display === 'none' ? 'block' : 'none';
                console.log('New display state:', container.style.display);
                
                // Scroll to the table if it's now visible
                if (container.style.display === 'block') {
                    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            } else {
                console.error('Unpaid table container not found!');
                alert('The unpaid bookings table could not be found. Please refresh the page and try again.');
            }
        }
        
        // Open unpaid bookings modal
        function openUnpaidModal() {
            console.log('Opening unpaid bookings modal');
            const modal = document.getElementById('unpaidBookingsModal');
            if (modal) {
                modal.style.display = 'block';
            } else {
                console.error('Unpaid bookings modal not found!');
            }
        }
        
        // Close unpaid bookings modal
        function closeUnpaidModal() {
            console.log('Closing unpaid bookings modal');
            const modal = document.getElementById('unpaidBookingsModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }
        
        // Send payment reminder to student
        function sendPaymentReminder(studId, studentName, bookId) {
            if (confirm(`Send payment reminder to ${studentName} (${studId}) for booking ${bookId}?`)) {
                // Show loading message
                const loadingMsg = document.createElement('div');
                loadingMsg.id = 'loading-message';
                loadingMsg.style.position = 'fixed';
                loadingMsg.style.top = '50%';
                loadingMsg.style.left = '50%';
                loadingMsg.style.transform = 'translate(-50%, -50%)';
                loadingMsg.style.padding = '20px';
                loadingMsg.style.background = 'rgba(255, 255, 255, 0.9)';
                loadingMsg.style.border = '1px solid #ddd';
                loadingMsg.style.borderRadius = '5px';
                loadingMsg.style.boxShadow = '0 0 10px rgba(0,0,0,0.2)';
                loadingMsg.style.zIndex = '9999';
                loadingMsg.innerHTML = '<p style="text-align: center;"><i class="fas fa-spinner fa-spin"></i><br>Sending payment reminder...</p>';
                document.body.appendChild(loadingMsg);
                
                // Send AJAX request to the backend
                fetch('hsSendPaymentReminder.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `studId=${encodeURIComponent(studId)}&bookId=${encodeURIComponent(bookId)}`
                })
                .then(response => response.json())
                .then(data => {
                    // Remove loading message
                    document.body.removeChild(loadingMsg);
                    
                    if (data.success) {
                        // Show success message
                        alert(`Success: ${data.message}`);
                    } else {
                        // Show error message
                        alert(`Error: ${data.message}`);
                    }
                })
                .catch(error => {
                    // Remove loading message
                    if (document.getElementById('loading-message')) {
                        document.body.removeChild(loadingMsg);
                    }
                    
                    console.error('Error:', error);
                    alert('An error occurred while sending the payment reminder. Please try again later.');
                });
            }
        }
        
        // Generate report for unpaid bookings
        function printUnpaidReport() {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Create the print content
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Unpaid Bookings Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .report-header {
                            text-align: center;
                            margin-bottom: 30px;
                        }
                        .report-header h1 {
                            color: #25408f;
                            font-size: 28px;
                            margin-bottom: 10px;
                            text-align: center;
                        }
                        .summary-container {
                            margin-bottom: 30px;
                            padding: 15px;
                            background-color: #fff8e1;
                            border-radius: 8px;
                            border-left: 5px solid #ffc107;
                        }
                        .summary-heading {
                            font-size: 18px;
                            font-weight: bold;
                            margin-bottom: 15px;
                            color: #333;
                        }
                        .summary-stat {
                            margin-bottom: 10px;
                        }
                        .summary-label {
                            font-weight: 600;
                            display: inline-block;
                            min-width: 200px;
                        }
                        .summary-value {
                            font-weight: bold;
                            color: #dc3545;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 20px;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 15px;
                            text-align: left;
                            font-size: 16px;
                            font-family: Arial, sans-serif;
                            line-height: 1.4;
                        }
                        th {
                            background-color: #25408f;
                            color: white;
                            font-weight: bold;
                            height: auto;
                            min-height: 45px;
                        }
                        tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .report-footer {
                            margin-top: 30px;
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                            border-top: 1px solid #ddd;
                            padding-top: 20px;
                        }
                        .warning-notice {
                            background-color: #f8d7da;
                            color: #721c24;
                            padding: 15px;
                            border-radius: 6px;
                            margin-bottom: 20px;
                            font-weight: bold;
                        }
                        @media print {
                            @page {
                                size: landscape;
                                margin: 2cm;
                            }
                            body {
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Unpaid Bookings Report</h1>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                    </div>
                    
                    <div class="warning-notice">
                        ATTENTION: The students listed below have booked accommodation but have NOT INITIATED the payment process.
                    </div>
            `;
            
            // Get unpaid bookings summary data
            const unpaidCount = document.querySelector('.unpaid-value').textContent;
            const unpaidTotal = document.querySelectorAll('.unpaid-stat .unpaid-value')[1].textContent;
            
            // Add summary section
            printContent += `
                <div class="summary-container">
                    <div class="summary-heading">Unpaid Booking Summary</div>
                    <div class="summary-stat">
                        <span class="summary-label">Number of Unpaid Bookings:</span>
                        <span class="summary-value">${unpaidCount}</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-label">Total Outstanding Fees:</span>
                        <span class="summary-value">${unpaidTotal}</span>
                    </div>
                </div>
            `;
            
            // Add the unpaid bookings table
            const unpaidTable = document.getElementById('unpaidTable');
            if (unpaidTable) {
                const rows = Array.from(unpaidTable.rows);
                
                printContent += '<table>';
                
                // Add header row (excluding the Action column)
                const headerRow = rows[0];
                printContent += '<thead><tr>';
                Array.from(headerRow.cells).forEach((cell, index) => {
                    if (index !== headerRow.cells.length - 1) { // Skip the Actions column
                        printContent += `<th>${cell.textContent.trim()}</th>`;
                    }
                });
                printContent += '</tr></thead>';
                
                // Add data rows (excluding the Action column)
                printContent += '<tbody>';
                rows.slice(1).forEach(row => {
                    printContent += '<tr>';
                    Array.from(row.cells).forEach((cell, index) => {
                        if (index !== row.cells.length - 1) { // Skip the Actions column
                            printContent += `<td>${cell.innerHTML.trim()}</td>`;
                        }
                    });
                    printContent += '</tr>';
                });
                printContent += '</tbody></table>';
            } else {
                printContent += '<p>No unpaid bookings found.</p>';
            }
            
            // Add footer
            printContent += `
                <div class="report-footer">
                    <p>Smart Hostel Management System &copy; ${new Date().getFullYear()}</p>
                </div>
            </body>
            </html>
            `;
            
            // Write to the print window and print
            try {
                printWindow.document.write(printContent);
                printWindow.document.close();
                
                // Wait for content to load before printing
                printWindow.onload = function() {
                    printWindow.print();
                    printWindow.close();
                };
            } catch (error) {
                console.error('Error generating report:', error);
                alert('An error occurred while generating the report. Please try again.');
            }
        }

        // Filter pending payments
        function filterPendingPayments() {
            console.log('Filtering pending payments');
            
            // Check if the pending payments card exists
            const pendingCard = document.querySelector('.dashboard-card.pending-card');
            if (!pendingCard) {
                console.error('Pending payments card not found in the DOM');
            }
            
            // Set the search form values
            document.getElementById('searchCriteria').value = 'Status';
            updateSearchField(); // Update the form fields
            
            // Wait for search field to update with Status dropdown
            setTimeout(() => {
                const statusSelect = document.getElementById('statusSelect');
                if (statusSelect) {
                    console.log('Setting status select to PENDING');
                    statusSelect.value = 'PENDING';
                    
                    // Submit the form
                    console.log('Submitting form');
                    document.querySelector('form.search-form').submit();
                } else {
                    console.error('Status select element not found!');
                }
            }, 100);
        }
        
        // Filter completed payments
        function filterCompletedPayments() {
            // Set the search form values
            document.getElementById('searchCriteria').value = 'Status';
            updateSearchField(); // Update the form fields
            
            // Wait for search field to update with Status dropdown
            setTimeout(() => {
                const statusSelect = document.getElementById('statusSelect');
                if (statusSelect) {
                    statusSelect.value = 'COMPLETED';
                    
                    // Submit the form
                    document.querySelector('form.search-form').submit();
                }
            }, 100);
        }
        
        // Filter rejected payments
        function filterRejectedPayments() {
            // Set the search form values
            document.getElementById('searchCriteria').value = 'Status';
            updateSearchField(); // Update the form fields
            
            // Wait for search field to update with Status dropdown
            setTimeout(() => {
                const statusSelect = document.getElementById('statusSelect');
                if (statusSelect) {
                    statusSelect.value = 'REJECTED';
                    
                    // Submit the form
                    document.querySelector('form.search-form').submit();
                }
            }, 100);
        }
        
        // Generate Pending Report Function
        function printPendingReport() {
            console.log('Generating pending payments report');
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Create the print content
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Pending Payment Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .report-header {
                            text-align: center;
                            margin-bottom: 30px;
                        }
                        .report-header h1 {
                            color: #25408f;
                            font-size: 28px;
                            margin-bottom: 10px;
                            text-align: center;
                        }
                        .summary-container {
                            margin-bottom: 30px;
                            padding: 15px;
                            background-color: #fff8e1;
                            border-radius: 8px;
                            border-left: 5px solid #ffc107;
                        }
                        .summary-heading {
                            font-size: 18px;
                            font-weight: bold;
                            margin-bottom: 15px;
                            color: #333;
                        }
                        .summary-stat {
                            margin-bottom: 10px;
                        }
                        .summary-label {
                            font-weight: 600;
                            display: inline-block;
                            min-width: 200px;
                        }
                        .summary-value {
                            font-weight: bold;
                            color: #dc3545;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 20px;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 15px;
                            text-align: left;
                            font-size: 16px;
                            font-family: Arial, sans-serif;
                            line-height: 1.4;
                        }
                        th {
                            background-color: #25408f;
                            color: white;
                            font-weight: bold;
                            height: auto;
                            min-height: 45px;
                        }
                        tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .status-badge {
                            display: inline-block;
                            padding: 6px 12px;
                            border-radius: 4px;
                            font-weight: bold;
                            text-align: center;
                        }
                        .status-pending {
                            background-color: #ffc107;
                            color: #000;
                        }
                        .report-footer {
                            margin-top: 30px;
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                            border-top: 1px solid #ddd;
                            padding-top: 20px;
                        }
                        @media print {
                            @page {
                                size: landscape;
                                margin: 2cm;
                            }
                            body {
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Pending Payment Report</h1>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                    </div>
            `;
            
            // Add Pending Summary Section
            // Try to get values from the dashboard card, or use defaults if not found
            let pendingCount = 0;
            let pendingBalance = 0;
            
            try {
                const pendingCardValue = document.querySelector('.dashboard-card.pending-card .card-value');
                if (pendingCardValue) {
                    pendingCount = pendingCardValue.textContent.trim();
                    
                    const pendingBalanceText = document.querySelector('.dashboard-card.pending-card .card-info').textContent.trim();
                    pendingBalance = parseFloat(pendingBalanceText.replace('RM ', '').replace(/,/g, '').split(' ')[0]) || 0;
                } else {
                    console.error('Pending card value element not found');
                    
                    // Count pending payments from the table
                    const table = document.querySelector('table');
                    if (table) {
                        const rows = Array.from(table.rows).slice(1); // Skip header row
                        pendingCount = rows.filter(row => {
                            const statusCell = row.cells[4]; // Status is in the 5th column (index 4)
                            return statusCell && statusCell.textContent.trim() === 'PENDING';
                        }).length;
                    }
                }
            } catch (error) {
                console.error('Error getting pending payment data:', error);
            }
            
            printContent += `
                <div class="summary-container">
                    <div class="summary-heading">Pending Payment Summary</div>
                    <div class="summary-stat">
                        <span class="summary-label">Number of Pending Payments:</span>
                        <span class="summary-value">${pendingCount}</span>
                    </div>
                    <div class="summary-stat">
                        <span class="summary-label">Total Outstanding Balance:</span>
                        <span class="summary-value">RM ${pendingBalance.toFixed(2)}</span>
                    </div>
                </div>
            `;
            
            // Get only pending payment rows from the table
            const table = document.querySelector('table');
            if (table) {
                const rows = Array.from(table.rows);
                
                // Start the table
                printContent += '<table>';
                
                // Add header row
                const headerRow = rows[0];
                printContent += '<thead><tr>';
                Array.from(headerRow.cells).forEach((cell, index) => {
                    if (index !== headerRow.cells.length - 1) { // Skip the Actions column
                        printContent += `<th>${cell.textContent.trim()}</th>`;
                    }
                });
                printContent += '</tr></thead>';
                
                // Filter for pending payment rows only
                printContent += '<tbody>';
                let pendingRows = 0;
                rows.slice(1).forEach(row => {
                    // Check if the status column (index 4) contains "PENDING"
                    const statusCell = row.cells[4];
                    if (statusCell && statusCell.textContent.trim() === 'PENDING') {
                        pendingRows++;
                        printContent += '<tr>';
                        Array.from(row.cells).forEach((cell, index) => {
                            if (index !== row.cells.length - 1) { // Skip the Actions column
                                if (index === 4) { // Status column
                                    printContent += `<td><span class="status-badge status-pending">PENDING</span></td>`;
                                } else {
                                    printContent += `<td>${cell.innerHTML.trim()}</td>`;
                                }
                            }
                        });
                        printContent += '</tr>';
                    }
                });
                printContent += '</tbody>';
                printContent += '</table>';
                
                if (pendingRows === 0) {
                    printContent += '<p style="text-align: center; font-style: italic;">No pending payments found.</p>';
                }
            }
            
            // Add footer
            printContent += `
                    <div class="report-footer">
                        <p>Smart Hostel Management System &copy; ${new Date().getFullYear()}</p>
                    </div>
                </body>
                </html>
            `;
            
            // Write to the print window and print
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Wait for images to load before printing
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }
    </script>
</body>
</html> 