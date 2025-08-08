<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['studID'])) {
    header("Location: studMainPage.php");
    exit();
}

// Get student ID from session
$studID = $_SESSION['studID'];

// Initialize search and sort parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$searchDateFrom = isset($_GET['searchDateFrom']) ? $_GET['searchDateFrom'] : '';
$searchDateTo = isset($_GET['searchDateTo']) ? $_GET['searchDateTo'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'PaymentDate';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';

// Get all bookings that need payment (approved bookings without completed payments)
$bookingsNeedingPayment = [];
try {
    // Modified query to avoid duplicate payments for same semester
    // Only get the latest approved booking per semester
    $stmt = $conn->prepare("
        SELECT b.BookID, r.RoomNo, h.Name as HostelName, s.AcademicYear, s.Semester, s.HostelFee,
               COALESCE((SELECT SUM(AmountPaid) 
                        FROM PAYMENT p 
                        JOIN BOOKING b2 ON p.BookID = b2.BookID 
                        WHERE p.Status = 'COMPLETED' AND b2.SemID = b.SemID AND b2.StudID = b.StudID), 0) as TotalPaid,
               COALESCE((SELECT SUM(AmountPaid) 
                        FROM PAYMENT p 
                        JOIN BOOKING b2 ON p.BookID = b2.BookID 
                        WHERE p.Status = 'PENDING' AND b2.SemID = b.SemID AND b2.StudID = b.StudID), 0) as TotalPending,
               s.HostelFee - COALESCE((SELECT SUM(AmountPaid) 
                                      FROM PAYMENT p 
                                      JOIN BOOKING b2 ON p.BookID = b2.BookID 
                                      WHERE (p.Status = 'COMPLETED' OR p.Status = 'PENDING') AND b2.SemID = b.SemID AND b2.StudID = b.StudID), 0) as RemainingBalance
        FROM BOOKING b
        JOIN ROOM r ON b.RoomID = r.RoomID
        JOIN HOSTEL h ON r.HostID = h.HostID
        JOIN SEMESTER s ON b.SemID = s.SemID
        WHERE b.StudID = ? 
        AND b.Status = 'APPROVED'
        AND b.BookID IN (
            SELECT MAX(b2.BookID)
            FROM BOOKING b2
            WHERE b2.StudID = b.StudID AND b2.SemID = b.SemID AND b2.Status = 'APPROVED'
            GROUP BY b2.StudID, b2.SemID
        )
        GROUP BY s.SemID
        HAVING RemainingBalance > 0
    ");
    $stmt->bind_param("s", $studID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $bookingsNeedingPayment[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting bookings needing payment: " . $e->getMessage());
}

$payments = [];
try {
    // Simple query to get all payments
    $sql = "
        SELECT 
            p.PymtID, 
            p.AmountPaid, 
            p.Balance, 
            p.Status, 
            p.PaymentDate,
            b.BookID, 
            CONCAT(r.RoomNo, ' (', h.Name, ')') as RoomInfo,
            CONCAT('Year ', s.AcademicYear, ' Semester ', s.Semester) as SemesterInfo,
            s.HostelFee, 
            s.SemID,
            COALESCE(e.FullName, 'Pending verification') as EmpName
        FROM 
            PAYMENT p
            JOIN BOOKING b ON p.BookID = b.BookID
            JOIN ROOM r ON b.RoomID = r.RoomID
            JOIN HOSTEL h ON r.HostID = h.HostID
            JOIN SEMESTER s ON b.SemID = s.SemID
            LEFT JOIN EMPLOYEE e ON p.EmpID = e.EmpID
        WHERE 
            b.StudID = ?
    ";

    $params = [$studID];
    $types = "s";

    // Add search conditions
    if ($searchCriteria === 'PymtID' && !empty($searchValue)) {
        $sql .= " AND p.PymtID = ?";
        $params[] = $searchValue;
        $types .= "s";
    } elseif ($searchCriteria === 'BookID' && !empty($searchValue)) {
        $sql .= " AND b.BookID = ?";
        $params[] = $searchValue;
        $types .= "s";
    } elseif ($searchCriteria === 'Date' && !empty($searchDateFrom) && !empty($searchDateTo)) {
        $sql .= " AND p.PaymentDate BETWEEN ? AND ?";
        $params[] = $searchDateFrom;
        $params[] = $searchDateTo;
        $types .= "ss";
    } elseif ($searchCriteria === 'Status' && !empty($searchValue)) {
        $sql .= " AND p.Status = ?";
        $params[] = $searchValue;
        $types .= "s";
    }

    // Execute query
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get all payments
    $allPayments = [];
    while ($row = $result->fetch_assoc()) {
        $allPayments[] = $row;
    }
    $stmt->close();
    
    // Create a lookup array for payments by ID
    $paymentsById = [];
    foreach ($allPayments as $payment) {
        $paymentsById[$payment['PymtID']] = $payment;
    }
    
    // Create a lookup array for payments by semester
    $paymentsBySemester = [];
    foreach ($allPayments as $payment) {
        $semID = $payment['SemID'];
        if (!isset($paymentsBySemester[$semID])) {
            $paymentsBySemester[$semID] = [];
        }
        $paymentsBySemester[$semID][] = $payment;
    }
    
    // Sort payments within each semester by date
    foreach ($paymentsBySemester as $semID => &$semPayments) {
        usort($semPayments, function($a, $b) {
            return strtotime($a['PaymentDate']) - strtotime($b['PaymentDate']);
        });
    }
    
    // Process all payments to add previous payment info
    foreach ($allPayments as $payment) {
        $pymtID = $payment['PymtID'];
        $semID = $payment['SemID'];
        
        // Find this payment's position in its semester
        $position = -1;
        foreach ($paymentsBySemester[$semID] as $index => $p) {
            if ($p['PymtID'] === $pymtID) {
                $position = $index;
                break;
            }
        }
        
        // Add payment rank
        $payment['PaymentRank'] = $position + 1;
        
        // If not the first payment in the semester, get previous payment info
        if ($position > 0) {
            $prevPayment = $paymentsBySemester[$semID][$position - 1];
            $payment['PreviousAmountPaid'] = $prevPayment['AmountPaid'];
            $payment['PreviousBalance'] = $prevPayment['Balance'];
            $payment['HasPreviousPayment'] = 1;
            
            // Debug log for this specific case
            if ($pymtID === 'P00002') {
                error_log("P00002 previous payment: " . $prevPayment['PymtID'] . 
                          ", Amount: " . $prevPayment['AmountPaid'] . 
                          ", Balance: " . $prevPayment['Balance']);
            }
        } else {
            $payment['PreviousAmountPaid'] = 0;
            $payment['PreviousBalance'] = 0;
            $payment['HasPreviousPayment'] = 0;
        }
        
        $payments[] = $payment;
    }
    
    // Sort final results according to requested sort
    if ($sortBy === 'PaymentDate') {
        usort($payments, function($a, $b) use ($sortOrder) {
            if ($sortOrder === 'ASC') {
                return strtotime($a['PaymentDate']) - strtotime($b['PaymentDate']);
            } else {
                return strtotime($b['PaymentDate']) - strtotime($a['PaymentDate']);
            }
        });
    } else if ($sortBy === 'PymtID') {
        usort($payments, function($a, $b) use ($sortOrder) {
            if ($sortOrder === 'ASC') {
                return strcmp($a['PymtID'], $b['PymtID']);
            } else {
                return strcmp($b['PymtID'], $a['PymtID']);
            }
        });
    } else if ($sortBy === 'AmountPaid') {
        usort($payments, function($a, $b) use ($sortOrder) {
            if ($sortOrder === 'ASC') {
                return $a['AmountPaid'] - $b['AmountPaid'];
            } else {
                return $b['AmountPaid'] - $a['AmountPaid'];
            }
        });
    }
    
    error_log("Final payments count: " . count($payments));
    foreach ($payments as $p) {
        error_log("Payment: " . $p['PymtID'] . 
                  ", Previous Amount: " . ($p['PreviousAmountPaid'] ?? 'NULL') . 
                  ", Previous Balance: " . ($p['PreviousBalance'] ?? 'NULL'));
    }
    
} catch (Exception $e) {
    error_log("Error getting payments: " . $e->getMessage());
}


// Initialize tab parameter
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// Get all payment IDs, booking IDs for dropdowns
$pymtIDs = [];
$bookIDs = [];
foreach ($payments as $payment) {
    if (!in_array($payment['PymtID'], $pymtIDs)) {
        $pymtIDs[] = $payment['PymtID'];
    }
    if (!in_array($payment['BookID'], $bookIDs)) {
        $bookIDs[] = $payment['BookID'];
    }
}

// Generate a new payment ID (format: P00001, P00002, etc.)
function generatePaymentID($conn) {
    $stmt = $conn->prepare("SELECT MAX(SUBSTRING(PymtID, 2)) as MaxID FROM PAYMENT");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $maxID = intval($row['MaxID'] ?? 0);
    $newID = $maxID + 1;
    
    return 'P' . str_pad($newID, 5, '0', STR_PAD_LEFT);
}

// Handle form submission for new payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $bookID = $_POST['bookID'];
    $paymentMethod = $_POST['paymentMethod'];
    $amountPaid = $_POST['amountPaid'];
    
    // Validate file upload
    if (!isset($_FILES['paymentProof']) || $_FILES['paymentProof']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "Payment proof is required.";
        header("Location: studPayment.php");
        exit();
    }
    
    // Validate amount
    if (!is_numeric($amountPaid) || $amountPaid <= 0) {
        $_SESSION['error'] = "Amount paid must be a positive number.";
        header("Location: studPayment.php");
        exit();
    }
    
    // Get remaining balance for the booking
    // Make sure we're using the correct booking (latest approved for that semester)
    $stmt = $conn->prepare("
        SELECT s.HostelFee,
               COALESCE((SELECT SUM(AmountPaid) 
                         FROM PAYMENT p 
                         JOIN BOOKING b2 ON p.BookID = b2.BookID 
                         WHERE p.Status = 'COMPLETED' AND b2.SemID = b.SemID AND b2.StudID = b.StudID), 0) as TotalPaid,
               s.SemID,
               (SELECT MAX(b3.BookID) 
                FROM BOOKING b3 
                WHERE b3.SemID = b.SemID AND b3.StudID = b.StudID AND b3.Status = 'APPROVED') as LatestBookID
        FROM BOOKING b
        JOIN SEMESTER s ON b.SemID = s.SemID
        WHERE b.BookID = ?
    ");
    $stmt->bind_param("s", $bookID);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookingData = $result->fetch_assoc();
    
    if (!$bookingData) {
        $_SESSION['error'] = "Invalid booking ID.";
        header("Location: studPayment.php");
        exit();
    }
    
    $hostelFee = $bookingData['HostelFee'];
    $totalPaid = $bookingData['TotalPaid'];
    $remainingBalance = $hostelFee - $totalPaid;
    $semID = $bookingData['SemID'];
    $latestBookID = $bookingData['LatestBookID'];
    
    // If the booking ID provided is not the latest for this semester, use the latest one
    if ($bookID != $latestBookID) {
        error_log("Using latest booking ID ($latestBookID) instead of provided ID ($bookID)");
        $bookID = $latestBookID;
    }
    
    // Check if amount paid is greater than remaining balance
    if ($amountPaid > $remainingBalance) {
        $_SESSION['error'] = "Amount paid cannot exceed the remaining balance of RM" . number_format($remainingBalance, 2);
        header("Location: studPayment.php");
        exit();
    }
    
    // Get total paid (COMPLETED + PENDING) before this payment
    $stmt = $conn->prepare("
        SELECT SUM(p.AmountPaid) as TotalPaid
        FROM PAYMENT p
        JOIN BOOKING b ON p.BookID = b.BookID
        WHERE b.SemID = ? AND b.StudID = ? AND (p.Status = 'COMPLETED' OR p.Status = 'PENDING')
    ");
    $stmt->bind_param("ss", $semID, $studID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $allPaidBefore = $row['TotalPaid'] ?? 0;

    // Calculate new balance after this payment
    $newBalance = $hostelFee - ($allPaidBefore + $amountPaid);
    
    // Read the file content
    $paymentProof = file_get_contents($_FILES['paymentProof']['tmp_name']);
    
    // Generate payment ID
    $pymtID = generatePaymentID($conn);
    
                    // Current date
    $paymentDate = date('Y-m-d');
    
    // Update the payments array to reflect the new payment
    $payments[] = [
        'PymtID' => $pymtID,
        'AmountPaid' => $amountPaid,
        'Balance' => $newBalance,
        'Status' => 'PENDING',
        'PaymentDate' => $paymentDate,
        'BookID' => $bookID,
        'RoomInfo' => '',
        'SemesterInfo' => '',
        'EmpName' => 'Pending verification'
    ];
    
    try {
        // Begin transaction
        $conn->begin_transaction();
        
        // Insert payment record
        //$stmt = $conn->prepare("INSERT INTO PAYMENT (PymtProof, AmountPaid, Balance, Status, PaymentDate, BookID) VALUES (?, ?, ?, ?, ?, ?)");
        //$stmt->bind_param("sdssss", $paymentProof, $amountPaid, $newBalance, 'PENDING', $paymentDate, $bookID);
        //$stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Payment submitted successfully and is pending verification.";
        // Redirect to ensure all data is refreshed
        header("Location: studPayment.php?tab=history");
        exit();
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['error'] = "Error submitting payment: " . $e->getMessage();
        error_log("Error submitting payment: " . $e->getMessage());
        header("Location: studPayment.php");
        exit();
    }
}

// Calculate payment summary statistics
$totalPaid = 0;
$totalPending = 0;
$totalRejected = 0;
$totalRemaining = 0;

// Get total hostel fees for all approved bookings
$totalFees = 0;
try {
    // Modified query to avoid counting the same semester multiple times
    $stmt = $conn->prepare("
        SELECT SUM(s.HostelFee) as TotalFees
        FROM (
            SELECT DISTINCT b.SemID 
            FROM BOOKING b 
            WHERE b.StudID = ? AND b.Status = 'APPROVED'
        ) as unique_semesters
        JOIN SEMESTER s ON unique_semesters.SemID = s.SemID
    ");
    $stmt->bind_param("s", $studID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalFees = $row['TotalFees'];
    }
    $stmt->close();
    
    // Get the correct amounts by status
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN p.Status = 'COMPLETED' THEN p.AmountPaid ELSE 0 END) as TotalCompleted,
            SUM(CASE WHEN p.Status = 'PENDING' THEN p.AmountPaid ELSE 0 END) as TotalPending,
            SUM(CASE WHEN p.Status = 'REJECTED' THEN p.AmountPaid ELSE 0 END) as TotalRejected
        FROM PAYMENT p
        JOIN BOOKING b ON p.BookID = b.BookID
        WHERE b.StudID = ?
    ");
    $stmt->bind_param("s", $studID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalPaid = $row['TotalCompleted'];
        $totalPending = $row['TotalPending'];
        $totalRejected = $row['TotalRejected'];
    }
    $stmt->close();
    
    // Debug log to check values
    error_log("Total Fees: " . $totalFees);
    error_log("Total Completed: " . $totalPaid);
    error_log("Total Pending: " . $totalPending);
    error_log("Total Rejected: " . $totalRejected);
    
} catch (Exception $e) {
    error_log("Error getting total fees: " . $e->getMessage());
}

// Calculate remaining balance as total fees minus completed payments only
$totalRemaining = $totalFees - $totalPaid;
// Keep the debug logs to help diagnose values
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/studentNav.css">
    <style>
        :root {
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --secondary: #6c757d;
        }
        
        /* Main Content */
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
            font-weight: 600;
            margin-bottom: 10px;
            font-family: Arial, sans-serif;
            text-align: center;
            margin-top: 0px;
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
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
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
        th:nth-child(3), td:nth-child(3) { min-width: 120px; } /* Amount Paid */
        th:nth-child(4), td:nth-child(4) { min-width: 120px; } /* Balance */
        th:nth-child(5), td:nth-child(5) { min-width: 120px; } /* Status */
        th:nth-child(6), td:nth-child(6) { min-width: 120px; } /* Payment Date */
        th:nth-child(7), td:nth-child(7) { min-width: 100px; } /* BookID */
        th:nth-child(8), td:nth-child(8) { min-width: 180px; } /* Room Info */
        th:nth-child(9), td:nth-child(9) { min-width: 180px; } /* Semester */
        th:nth-child(10), td:nth-child(10) { min-width: 160px; } /* Verified By */
        th:nth-child(11), td:nth-child(11) { min-width: 100px; } /* Action */

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
            white-space: nowrap;
            margin: 0 auto;
        }

        /* Center status badge */
        td:nth-child(5) {
            text-align: center;
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
        }

        /* Button Styles */
        .btn {
            font-family: Arial, sans-serif;
            font-size: 16px;
            transition: background-color 0.3s ease;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            padding: 8px 12px;
            font-weight: 600;
            text-decoration: none;
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

        .search-btn-group {
            display: flex;
            gap: 10px;
            align-items: center;
            white-space: nowrap;
        }

        .search-btn-group .btn {
            min-width: 150px;
            background-color: #25408f;
        }

        .search-btn-group .btn:hover {
            background-color: #3883ce;
        }
        
        .search-btn-group .btn-secondary {
            background-color: #6c757d;
            text-align: center;
            color: white;
        }
        
        .search-btn-group .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* Date Range Inputs */
        .date-range-container {
            grid-column: span 2;
            display: flex;
            gap: 30px;
            margin-right: 20px;
        }

        .date-range-container .form-group {
            flex: 1;
        }

        .date-range-container input[type="date"] {
            height: 38px;
            padding: 8px 12px;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }

        /* No Results Message */
        .no-results {
            text-align: center;
            padding: 20px;
            color: var(--text-dark);
            font-family: Arial, sans-serif;
            font-size: 16px;
            background-color: var(--light-bg);
            border-radius: 8px;
            margin: 20px 0;
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
            font-family: Arial, sans-serif;
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
        }
        
        .modal-body {
            margin-bottom: 20px;
            padding: 20px;
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
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
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
            font-family: Arial, sans-serif;
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

        /* Payment Form Section */
        .payment-form-section {
            background-color: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .payment-form {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .file-input-container {
            margin-top: 10px;
        }

        .file-input-container input[type="file"] {
            padding: 8px 0;
        }

        /* Payment Card Styles */
        .payment-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .payment-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--shadow);
            padding: 20px;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .payment-card-header {
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 10px;
        }

        .payment-card-title {
            font-size: 18px;
            font-weight: bold;
            color: var(--primary-color);
            margin: 0;
            font-family: Arial, sans-serif;
        }

        .payment-card-body {
            flex-grow: 1;
        }

        .payment-card-item {
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }

        .payment-card-label {
            font-weight: bold;
            color: var(--text-dark);
            font-family: Arial, sans-serif;
        }

        .payment-card-value {
            color: var(--text-dark);
            text-align: right;
            font-family: Arial, sans-serif;
        }

        .payment-card-footer {
            margin-top: 15px;
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
            text-align: center;
        }

        .btn-block {
            display: block;
            width: 100%;
        }

        /* View Payment Proof Modal */
        .img-fluid {
            max-width: 100%;
            height: auto;
        }

        .modal-img-container {
            text-align: center;
            margin: 20px 0;
        }
        
        /* Quick Filter Buttons */
        .quick-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            background-color: #f0f0f0;
            color: #333;
            border-radius: 4px;
            text-decoration: none;
            font-family: Arial, sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 1px solid #ddd;
        }
        
        .filter-btn:hover {
            background-color: #e0e0e0;
        }
        
        .filter-btn.active {
            background-color: #25408f;
            color: white;
            border-color: #25408f;
        }
        
        .filter-btn.pending {
            border-color: var(--warning);
        }
        
        .filter-btn.pending.active {
            background-color: var(--warning);
            color: #000;
            border-color: var(--warning);
        }
        
        .filter-btn.completed {
            border-color: var(--success);
        }
        
        .filter-btn.completed.active {
            background-color: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .filter-btn.rejected {
            border-color: var(--danger);
        }
        
        .filter-btn.rejected.active {
            background-color: var(--danger);
            color: white;
            border-color: var(--danger);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .modal-content {
                margin: 20px;
                width: auto;
            }
        }

        /* Tab Navigation */
        .payment-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-btn {
            padding: 10px 20px;
            background-color: #f0f0f0;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-family: Arial, sans-serif;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
            max-width: 250px;
        }
        
        .tab-btn:hover {
            background-color: #e0e0e0;
        }
        
        .tab-btn.active {
            background-color: #25408f;
            color: white;
        }
        
        .tab-content {
            display: block;
        }
        
        .view-history-btn-container {
            text-align: center;
            margin: 30px 0;
        }

        /* Global Styles */
        body {
            background-color: #f0f8ff;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        /* Payment Summary Section */
        .payment-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            padding: 20px;
            text-align: center;
            border-left: 5px solid #25408f;
        }
        
        .summary-card.paid {
            border-left-color: var(--success);
        }
        
        .summary-card.pending {
            border-left-color: var(--warning);
        }
        
        .summary-card.rejected {
            border-left-color: var(--danger);
        }
        
        .summary-card.remaining {
            border-left-color: var(--danger);
        }
        
        .summary-title {
            font-size: 16px;
            color: #666;
            margin-bottom: 10px;
            font-family: Arial, sans-serif;
        }
        
        .summary-value {
            font-size: 26px;
            font-weight: bold;
            color: #333;
            font-family: Arial, sans-serif;
        }
        
        /* Enhanced Payment Card */
        .payment-card {
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .payment-card:hover {
            transform: translateY(-5px);
        }
        
        .payment-status-tag {
            position: absolute;
            top: 0;
            right: 0;
            padding: 5px 15px;
            font-size: 12px;
            font-weight: bold;
            color: white;
            background-color: var(--warning);
            border-bottom-left-radius: 8px;
        }
        
        .payment-card-divider {
            width: 100%;
            height: 1px;
            background-color: #eee;
            margin: 10px 0;
        }
        
        .payment-progress {
            margin-top: 15px;
            height: 10px;
            background-color: #f0f0f0;
            border-radius: 5px;
            overflow: hidden;
        }
        
        .payment-progress-bar {
            height: 100%;
            background-color: var(--success);
        }
        
        .payment-progress-info {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-top: 5px;
            color: #666;
        }
    </style>
</head>
<body>
    <?php include 'includes/studentNav.php'; ?>

    <div class="main-content">
        <div class="container">
            <!-- Page Header -->
            <div class="page-header">
                <h2>Hostel Fee Payment</h2>
            </div>

            <!-- Tab Navigation -->
            <div class="payment-tabs">
                <button class="tab-btn <?php echo $activeTab === 'pending' ? 'active' : ''; ?>" id="pendingTab" onclick="showTab('pending')">Pending Payments</button>
                <button class="tab-btn <?php echo $activeTab === 'history' ? 'active' : ''; ?>" id="historyTab" onclick="showTab('history')">Payment History</button>
            </div>

            <!-- Success and Error Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Payment Summary Section -->
            <div class="payment-summary">
                <div class="summary-card paid">
                    <div class="summary-title">Total Paid</div>
                    <div class="summary-value">RM <?php echo number_format($totalPaid, 2); ?></div>
                </div>
                <div class="summary-card pending">
                    <div class="summary-title">Pending Verification</div>
                    <div class="summary-value">RM <?php echo number_format($totalPending, 2); ?></div>
                </div>
                <div class="summary-card rejected">
                    <div class="summary-title">Rejected Payments</div>
                    <div class="summary-value">RM <?php echo number_format($totalRejected, 2); ?></div>
                </div>
                <div class="summary-card remaining">
                    <div class="summary-title">Pending Balance</div>
                    <div class="summary-value">RM <?php echo number_format($totalRemaining, 2); ?></div>
                </div>
            </div>
            
            <!-- Semester Payment Info -->
            <div class="alert alert-info semester-note">
                <i class="fas fa-info-circle"></i> <strong>Note:</strong> You only need to pay the hostel fee once per semester. If you change rooms within the same semester, no additional payment is required.
            </div>

            <!-- Pending Payments Section -->
            <div id="pending-section" class="tab-content" style="display: <?php echo $activeTab === 'pending' ? 'block' : 'none'; ?>">
                <?php if (!empty($bookingsNeedingPayment)): ?>
                    <div class="payment-form-section">
                        <h3 class="section-title">Pending Payments</h3>
                        <div class="payment-cards">
                            <?php foreach ($bookingsNeedingPayment as $booking): ?>
                                <?php 
                                    // Calculate payment percentage including both completed and pending payments
                                    $totalPaid = $booking['TotalPaid'] + $booking['TotalPending'];
                                    $paymentPercentage = ($totalPaid / $booking['HostelFee']) * 100;
                                ?>
                                <div class="payment-card">
                                    <div class="payment-status-tag">Pending</div>
                                    <div class="payment-card-header">
                                        <h4 class="payment-card-title">Booking: <?php echo htmlspecialchars($booking['BookID']); ?></h4>
                                    </div>
                                    <div class="payment-card-body">
                                        <div class="payment-card-item">
                                            <span class="payment-card-label">Room:</span>
                                            <span class="payment-card-value"><?php echo htmlspecialchars($booking['RoomNo']); ?></span>
                                        </div>
                                        <div class="payment-card-item">
                                            <span class="payment-card-label">Hostel:</span>
                                            <span class="payment-card-value"><?php echo htmlspecialchars($booking['HostelName']); ?></span>
                                        </div>
                                        <div class="payment-card-item">
                                            <span class="payment-card-label">Semester:</span>
                                            <span class="payment-card-value">Year <?php echo htmlspecialchars($booking['AcademicYear']); ?> Sem <?php echo htmlspecialchars($booking['Semester']); ?></span>
                                        </div>
                                        <div class="payment-card-divider"></div>
                                        <div class="payment-card-item">
                                            <span class="payment-card-label">Total Fee:</span>
                                            <span class="payment-card-value">RM <?php echo number_format($booking['HostelFee'], 2); ?></span>
                                        </div>
                                        <div class="payment-card-item">
                                            <span class="payment-card-label">Paid (Completed):</span>
                                            <span class="payment-card-value">RM <?php echo number_format($booking['TotalPaid'], 2); ?></span>
                                        </div>
                                        <div class="payment-card-item">
                                            <span class="payment-card-label">Paid (Pending):</span>
                                            <span class="payment-card-value">RM <?php echo number_format($booking['TotalPending'], 2); ?></span>
                                        </div>
                                        <div class="payment-card-item">
                                            <span class="payment-card-label">Remaining:</span>
                                            <span class="payment-card-value">RM <?php echo number_format($booking['RemainingBalance'], 2); ?></span>
                                        </div>
                                        <div class="payment-progress">
                                            <div class="payment-progress-bar" style="width: <?php echo $paymentPercentage; ?>%"></div>
                                        </div>
                                        <div class="payment-progress-info">
                                            <span>Payment Progress</span>
                                            <span><?php echo number_format($paymentPercentage, 1); ?>%</span>
                                        </div>
                                    </div>
                                    <div class="payment-card-footer">
                                        <button type="button" class="btn btn-primary" 
                                                onclick="openPaymentModal('<?php echo htmlspecialchars($booking['BookID']); ?>', 
                                                                          <?php echo $booking['RemainingBalance']; ?>)">
                                            Make Payment
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        No pending payments required at this time.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Payment History Section -->
            <div id="history-section" class="tab-content" style="display: <?php echo $activeTab === 'history' ? 'block' : 'none'; ?>">
                <!-- Search Section -->
                <div class="search-section">
                    <h3 class="section-title">Search Payment History</h3>
                    <form action="studPayment.php" method="GET" class="search-form">
                        <input type="hidden" name="tab" value="history">
                        <div class="form-group">
                            <label for="searchCriteria">Search By</label>
                            <select id="searchCriteria" name="searchCriteria" class="form-control">
                                <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All Records</option>
                                <option value="PymtID" <?php echo $searchCriteria === 'PymtID' ? 'selected' : ''; ?>>Payment ID</option>
                                <option value="BookID" <?php echo $searchCriteria === 'BookID' ? 'selected' : ''; ?>>Booking ID</option>
                                <option value="Date" <?php echo $searchCriteria === 'Date' ? 'selected' : ''; ?>>Date Range</option>
                                <option value="Status" <?php echo $searchCriteria === 'Status' ? 'selected' : ''; ?>>Status</option>
                            </select>
                        </div>

                        <!-- Payment ID Dropdown -->
                        <div id="PymtIDContainer" class="form-group" style="display: <?php echo $searchCriteria === 'PymtID' ? 'block' : 'none'; ?>">
                            <label for="searchValue">Payment ID</label>
                            <select id="pymtIDSelect" name="searchValue" class="form-control">
                                <option value="">Select Payment ID</option>
                                <?php 
                                foreach ($pymtIDs as $pymtID): ?>
                                    <option value="<?php echo htmlspecialchars($pymtID); ?>" <?php echo ($searchCriteria === 'PymtID' && $searchValue === $pymtID) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($pymtID); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Booking ID Dropdown -->
                        <div id="BookIDContainer" class="form-group" style="display: <?php echo $searchCriteria === 'BookID' ? 'block' : 'none'; ?>">
                            <label for="searchValue">Booking ID</label>
                            <select id="bookIDSelect" name="searchValue" class="form-control">
                                <option value="">Select Booking ID</option>
                                <?php 
                                foreach ($bookIDs as $bookID): ?>
                                    <option value="<?php echo htmlspecialchars($bookID); ?>" <?php echo ($searchCriteria === 'BookID' && $searchValue === $bookID) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($bookID); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Status Dropdown -->
                        <div id="StatusContainer" class="form-group" style="display: <?php echo $searchCriteria === 'Status' ? 'block' : 'none'; ?>">
                            <label for="searchStatus">Status</label>
                            <select id="searchStatus" name="searchValue" class="form-control">
                                <option value="">Select Status</option>
                                <option value="PENDING" <?php echo ($searchCriteria === 'Status' && $searchValue === 'PENDING') ? 'selected' : ''; ?>>Pending</option>
                                <option value="COMPLETED" <?php echo ($searchCriteria === 'Status' && $searchValue === 'COMPLETED') ? 'selected' : ''; ?>>Completed</option>
                                <option value="REJECTED" <?php echo ($searchCriteria === 'Status' && $searchValue === 'REJECTED') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>

                        <div id="dateRangeContainer" class="date-range-container" style="display: <?php echo $searchCriteria === 'Date' ? 'flex' : 'none'; ?>">
                            <div class="form-group">
                                <label for="searchDateFrom">From Date</label>
                                <input type="date" id="searchDateFrom" name="searchDateFrom" class="form-control" value="<?php echo htmlspecialchars($searchDateFrom); ?>">
                            </div>
                            <div class="form-group">
                                <label for="searchDateTo">To Date</label>
                                <input type="date" id="searchDateTo" name="searchDateTo" class="form-control" value="<?php echo htmlspecialchars($searchDateTo); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="sortBy">Sort By</label>
                            <select id="sortBy" name="sortBy" class="form-control">
                                <option value="PaymentDate" <?php echo $sortBy === 'PaymentDate' ? 'selected' : ''; ?>>Payment Date</option>
                                <option value="PymtID" <?php echo $sortBy === 'PymtID' ? 'selected' : ''; ?>>Payment ID</option>
                                <option value="AmountPaid" <?php echo $sortBy === 'AmountPaid' ? 'selected' : ''; ?>>Amount Paid</option>
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
                            <button type="submit" class="btn btn-primary">
                                Search
                            </button>
                            <a href="studPayment.php?tab=history" class="btn btn-secondary" style="text-align: center;">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Payment History Table -->
                <div class="table-section">
                    <h3 class="section-title">Payment History</h3>
                    
                    <div class="table-container">
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
                                    <th>Semester</th>
                                    <th>Verified By</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($payments)): ?>
                                    <tr>
                                        <td colspan="11" class="no-results">No payment records found.</td>
                                    </tr>
                                <?php else: ?>
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
                                            <td><?php echo htmlspecialchars($payment['SemesterInfo']); ?></td>
                                            <td><?php echo htmlspecialchars($payment['EmpName']); ?></td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button type="button" class="btn btn-primary" 
                                                            onclick="viewPaymentProof('<?php echo htmlspecialchars($payment['PymtID']); ?>')">
                                                        View
                                                    </button>
                                                    <?php if (strtolower($payment['Status']) === 'completed'): ?>
                                                    <button type="button" class="btn btn-success" 
                                                            onclick="generateReceipt('<?php echo htmlspecialchars($payment['PymtID']); ?>')">
                                                        Receipt
                                                    </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closePaymentModal()">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Make Payment</h3>
            </div>
            <div class="modal-body">
                <form id="paymentForm" action="studStripeCheckout.php" method="POST">
                    <input type="hidden" name="bookID" id="bookID">
                    <input type="number" name="amountPaid" id="amountPaid" step="0.01" min="0.01" required>
                    <small style="display:block; margin-bottom: 16px;">Remaining balance: RM <span id="remainingBalance">0.00</span></small>
                    <button type="submit" class="btn btn-primary">Proceed to Payment</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
            </div>
        </div>
    </div>

    <!-- View Payment Proof Modal -->
    <div id="proofModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeProofModal()">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Payment Proof</h3>
            </div>
            <div class="modal-body">
                <div id="paymentDetails"></div>
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
                <form id="editPaymentForm" action="studUpdatePayment.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="editPymtID" name="pymtID">
                    <div id="editPaymentDetails"></div>
                    <div class="form-group">
                        <label for="editAmountPaid">Amount Paid (RM)</label>
                        <input type="number" id="editAmountPaid" name="amountPaid" class="form-control" 
                               step="0.01" min="0.01" required>
                        <small style="display:block; margin-bottom: 16px;">Remaining balance: RM <span id="editRemainingBalance">0.00</span></small>
                    </div>
                    <div class="form-group">
                        <label for="editPaymentProof">New Payment Proof (Optional)</label>
                        <div class="file-input-container">
                            <input type="file" id="editPaymentProof" name="paymentProof" 
                                   accept="image/jpeg,image/png,image/jpg">
                        </div>
                        <small>Upload a new screenshot or photo if you want to change the payment proof</small>
                    </div>
                    <div class="modal-img-container">
                        <p><strong>Current Payment Proof:</strong></p>
                        <img id="currentProofImage" class="img-fluid" src="" alt="Current Payment Proof">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" form="editPaymentForm" class="btn btn-primary">Update Payment</button>
            </div>
        </div>
    </div>

    <!-- JavaScript for Modal Functionality -->
    <script>
        // Tab navigation
        function showTab(tabName) {
            // Hide all tab content
            document.getElementById('pending-section').style.display = 'none';
            document.getElementById('history-section').style.display = 'none';
            
            // Remove active class from all tabs
            document.getElementById('pendingTab').classList.remove('active');
            document.getElementById('historyTab').classList.remove('active');
            
            // Show selected tab content and add active class to selected tab
            document.getElementById(tabName + '-section').style.display = 'block';
            document.getElementById(tabName + 'Tab').classList.add('active');
            
            // If navigating from UI, update the URL without refreshing
            if (history.pushState) {
                var url = new URL(window.location);
                url.searchParams.set('tab', tabName);
                window.history.pushState({path:url.href}, '', url.href);
            }
        }

        // Show/hide search fields based on search criteria
        document.getElementById('searchCriteria').addEventListener('change', function() {
            const searchCriteria = this.value;
            
            // Hide all search value containers
            document.getElementById('PymtIDContainer').style.display = 'none';
            document.getElementById('BookIDContainer').style.display = 'none';
            document.getElementById('StatusContainer').style.display = 'none';
            document.getElementById('dateRangeContainer').style.display = 'none';
            
            // Show selected container
            if (searchCriteria === 'PymtID') {
                document.getElementById('PymtIDContainer').style.display = 'block';
            } else if (searchCriteria === 'BookID') {
                document.getElementById('BookIDContainer').style.display = 'block';
            } else if (searchCriteria === 'Status') {
                document.getElementById('StatusContainer').style.display = 'block';
            } else if (searchCriteria === 'Date') {
                document.getElementById('dateRangeContainer').style.display = 'flex';
            }
        });

        // Payment Modal Functions
        const paymentModal = document.getElementById('paymentModal');
        
        function openPaymentModal(bookID, remainingBalance) {
            document.getElementById('bookID').value = bookID;
            document.getElementById('remainingBalance').textContent = remainingBalance.toFixed(2);
            
            // Set the max amount to the remaining balance
            document.getElementById('amountPaid').max = remainingBalance;
            document.getElementById('amountPaid').value = remainingBalance;
            
            paymentModal.style.display = 'block';
        }
        
        function closePaymentModal() {
            paymentModal.style.display = 'none';
        }

        // View Payment Proof Modal Functions
        const proofModal = document.getElementById('proofModal');
        
        function viewPaymentProof(pymtID) {
            // Fetch payment proof and details via AJAX
            fetch('studGetPaymentProof.php?pymtID=' + pymtID)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('paymentDetails').innerHTML = `
                            <p><strong>Payment ID:</strong> ${data.payment.PymtID}</p>
                            <p><strong>Booking ID:</strong> ${data.payment.BookID}</p>
                            <p><strong>Room:</strong> ${data.payment.RoomInfo}</p>
                            <p><strong>Semester:</strong> ${data.payment.SemesterInfo}</p>
                            <div class="payment-card-divider"></div>
                            <p><strong>Hostel Fee:</strong> RM ${parseFloat(data.payment.HostelFee).toFixed(2)}</p>
                            <p><strong>Amount Paid:</strong> RM ${parseFloat(data.payment.AmountPaid).toFixed(2)}</p>
                            <p><strong>Balance:</strong> RM ${parseFloat(data.payment.Balance).toFixed(2)}</p>
                            <div class="payment-card-divider"></div>
                            <p><strong>Date:</strong> ${data.payment.PaymentDate}</p>
                            <p><strong>Status:</strong> ${data.payment.Status}</p>
                        `;
                        
                        const proofImageContainer = document.getElementById('proofImageContainer');
                        const proofValue = data.proofImage;
                        console.log("proofValue:", proofValue);

                        if (proofValue && (proofValue.startsWith('http://') || proofValue.startsWith('https://'))) {
                            proofImageContainer.innerHTML = `<a href="${proofValue}" target="_blank" style="display:block;margin:10px 0;">
                                <i class="fa fa-external-link-alt"></i> View Stripe Receipt
                            </a>`;
                        } else if (proofValue) {
                            proofImageContainer.innerHTML = `<img class="img-fluid" src="${proofValue}" alt="Payment Proof">`;
                        } else {
                            proofImageContainer.innerHTML = `<span style="color:#888;">No payment proof available.</span>`;
                        }
                        
                        // Show the modal
                        proofModal.style.display = 'block';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching the payment proof.');
                });
        }
        
        function closeProofModal() {
            proofModal.style.display = 'none';
        }

        // Edit Payment Function
        function editPayment(pymtID) {
            // Fetch payment details via AJAX
            fetch('studGetPaymentDetails.php?pymtID=' + pymtID)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate the edit form with payment details
                        document.getElementById('editPymtID').value = pymtID;
                        document.getElementById('editPaymentDetails').innerHTML = `
                            <p><strong>Payment ID:</strong> ${data.payment.PymtID}</p>
                            <p><strong>Booking ID:</strong> ${data.payment.BookID}</p>
                            <p><strong>Room:</strong> ${data.payment.RoomInfo}</p>
                            <p><strong>Semester:</strong> ${data.payment.SemesterInfo}</p>
                            <div class="payment-card-divider"></div>
                            <p><strong>Hostel Fee:</strong> RM ${parseFloat(data.payment.HostelFee).toFixed(2)}</p>
                            <p><strong>Amount Paid:</strong> RM ${parseFloat(data.payment.AmountPaid).toFixed(2)}</p>
                            <p><strong>Balance:</strong> RM ${parseFloat(data.payment.Balance).toFixed(2)}</p>
                            <div class="payment-card-divider"></div>
                            <p><strong>Date:</strong> ${data.payment.PaymentDate}</p>
                            <p><strong>Status:</strong> ${data.payment.Status}</p>
                        `;
                        
                        // Set the current amount paid and remaining balance
                        const amountPaidInput = document.getElementById('editAmountPaid');
                        amountPaidInput.value = data.payment.AmountPaid;
                        
                        // Set max amount to the current balance
                        amountPaidInput.max = data.payment.Balance;
                        
                        // Update remaining balance display
                        document.getElementById('editRemainingBalance').textContent = data.payment.Balance.toFixed(2);
                        
                        // Add event listener for amount paid changes
                        amountPaidInput.addEventListener('input', function() {
                            const newAmount = parseFloat(this.value) || 0;
                            const baseBalance = parseFloat(data.payment.Balance);
                            const newBalance = baseBalance - newAmount;
                            document.getElementById('editRemainingBalance').textContent = newBalance.toFixed(2);
                        });
                        
                        // Set the current payment proof image
                        document.getElementById('currentProofImage').src = data.proofImage;
                        
                        // Show the edit modal
                        document.getElementById('editPaymentModal').style.display = 'block';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while fetching the payment details.');
                });
        }

        // Close edit modal
        function closeEditModal() {
            document.getElementById('editPaymentModal').style.display = 'none';
        }

        // Generate Payment Receipt
        function generateReceipt(pymtID) {
            // Open a new window for the receipt
            const receiptWindow = window.open('', '_blank', 'width=800,height=600');
            
            // Get the payment data
            fetch('studGetPaymentProof.php?pymtID=' + pymtID)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Create receipt HTML
                        const receiptHTML = `
                            <!DOCTYPE html>
                            <html lang="en">
                            <head>
                                <meta charset="UTF-8">
                                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                                <title>Payment Receipt - ${data.payment.PymtID}</title>
                                <style>
                                    body {
                                        font-family: Arial, sans-serif;
                                        margin: 0;
                                        padding: 20px;
                                        color: #333;
                                    }
                                    .receipt-container {
                                        max-width: 800px;
                                        margin: 0 auto;
                                        border: 1px solid #ddd;
                                        padding: 20px;
                                    }
                                    .receipt-header {
                                        text-align: center;
                                        margin-bottom: 20px;
                                        border-bottom: 2px solid #25408f;
                                        padding-bottom: 20px;
                                    }
                                    .receipt-title {
                                        font-size: 24px;
                                        color: #25408f;
                                        margin-bottom: 5px;
                                    }
                                    .receipt-subtitle {
                                        font-size: 14px;
                                        color: #666;
                                    }
                                    .receipt-info {
                                        display: flex;
                                        justify-content: space-between;
                                        margin-bottom: 20px;
                                    }
                                    .receipt-info-column {
                                        flex: 1;
                                    }
                                    .receipt-label {
                                        font-weight: bold;
                                        margin-bottom: 5px;
                                    }
                                    .receipt-value {
                                        margin-bottom: 15px;
                                    }
                                    .receipt-amount {
                                        font-size: 18px;
                                        font-weight: bold;
                                        color: #25408f;
                                        text-align: right;
                                        margin-top: 20px;
                                    }
                                    .receipt-status {
                                        display: inline-block;
                                        padding: 6px 10px;
                                        border-radius: 4px;
                                        font-size: 14px;
                                        color: white;
                                        background-color: ${data.payment.Status === 'COMPLETED' ? '#28a745' : '#ffc107'};
                                    }
                                    .receipt-footer {
                                        margin-top: 40px;
                                        border-top: 1px solid #ddd;
                                        padding-top: 20px;
                                        text-align: center;
                                        font-size: 12px;
                                        color: #666;
                                    }
                                    .receipt-proof {
                                        margin-top: 20px;
                                        text-align: center;
                                    }
                                    .receipt-proof img {
                                        max-width: 100%;
                                        max-height: 200px;
                                        border: 1px solid #ddd;
                                    }
                                    @media print {
                                        .no-print {
                                            display: none;
                                        }
                                    }
                                </style>
                            </head>
                            <body>
                                <div class="receipt-container">
                                    <div class="receipt-header">
                                        <div class="receipt-title">Payment Receipt</div>
                                        <div class="receipt-subtitle">Smart Hostel Management System</div>
                                    </div>
                                    
                                    <div class="receipt-info">
                                        <div class="receipt-info-column">
                                            <div class="receipt-label">Payment ID:</div>
                                            <div class="receipt-value">${data.payment.PymtID}</div>
                                            
                                            <div class="receipt-label">Payment Date:</div>
                                            <div class="receipt-value">${data.payment.PaymentDate}</div>
                                            
                                        </div>
                                        
                                        <div class="receipt-info-column">
                                            <div class="receipt-label">Booking ID:</div>
                                            <div class="receipt-value">${data.payment.BookID}</div>
                                            
                                            <div class="receipt-label">Room:</div>
                                            <div class="receipt-value">${data.payment.RoomInfo}</div>
                                            
                                            <div class="receipt-label">Semester:</div>
                                            <div class="receipt-value">${data.payment.SemesterInfo}</div>
                                        </div>
                                    </div>
                                    
                                    <div class="receipt-info">
                                        <div class="receipt-info-column">
                                            <div class="receipt-label">Status:</div>
                                            <div class="receipt-value">
                                                <span class="receipt-status">${data.payment.Status}</span>
                                            </div>
                                        </div>
                                        
                                        <div class="receipt-info-column">
                                            <div class="receipt-label">Verified By:</div>
                                            <div class="receipt-value">${data.payment.Status === 'COMPLETED' ? data.payment.EmpName : 'Pending verification'}</div>
                                        </div>
                                    </div>
                                    
                                    <div class="receipt-proof">
                                        <div class="receipt-label">Payment Proof:</div>
                                        <img src="${data.proofImage}" alt="Payment Proof">
                                    </div>
                                    
                                    <div class="receipt-amount">
                                        <div class="receipt-label">Amount Paid:</div>
                                        RM ${parseFloat(data.payment.AmountPaid).toFixed(2)}
                                        <div class="receipt-label">Remaining Balance:</div>
                                        RM ${parseFloat(data.payment.Balance).toFixed(2)}
                                    </div>
                                    
                                    <div class="receipt-footer">
                                        <p>This is a computer-generated receipt and does not require a signature.</p>
                                        <p>For any inquiries, please contact the hostel management office.</p>
                                    </div>
                                    
                                    <div class="no-print" style="text-align: center; margin-top: 20px;">
                                        <button onclick="window.print()" style="padding: 8px 16px; background: #25408f; color: white; border: none; cursor: pointer;">Print Receipt</button>
                                    </div>
                                </div>
                            </body>
                            </html>
                        `;
                        
                        // Write the receipt HTML to the new window
                        receiptWindow.document.write(receiptHTML);
                        receiptWindow.document.close();
                    } else {
                        receiptWindow.close();
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    receiptWindow.close();
                    console.error('Error:', error);
                    alert('An error occurred while generating the receipt.');
                });
        }

        // Close modals when clicking outside of them
        window.onclick = function(event) {
            if (event.target === paymentModal) {
                closePaymentModal();
            } else if (event.target === proofModal) {
                closeProofModal();
            } else if (event.target === editPaymentModal) {
                closeEditModal();
            }
        }

        // Date range validation for search form
        document.querySelector('form.search-form').addEventListener('submit', function(e) {
            const searchCriteria = document.getElementById('searchCriteria').value;
            if (searchCriteria === 'Date') {
                const fromDate = document.getElementById('searchDateFrom').value;
                const toDate = document.getElementById('searchDateTo').value;
                if (fromDate && toDate && toDate < fromDate) {
                    alert('The "To Date" cannot be earlier than the "From Date".');
                    e.preventDefault();
                }
            }
        });
    </script>
</body>
</html>