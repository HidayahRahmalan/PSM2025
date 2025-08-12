<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['studID'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get student ID from session
$studID = $_SESSION['studID'];

// Get payment ID from GET request
if (!isset($_GET['pymtID'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Payment ID is required']);
    exit();
}

$pymtID = $_GET['pymtID'];

try {
    // First, get the current payment details
    $stmt = $conn->prepare("
        SELECT p.PymtID, p.PymtProof, p.AmountPaid, p.Balance, p.Status, p.PaymentDate, 
               p.BookID, e.FullName as EmpName, b.StudID, s.SemID,
               CONCAT(r.RoomNo, ' (', h.Name, ')') as RoomInfo,
               CONCAT('Year ', s.AcademicYear, ' Semester ', s.Semester) as SemesterInfo,
               s.HostelFee
        FROM PAYMENT p
        JOIN BOOKING b ON p.BookID = b.BookID
        JOIN ROOM r ON b.RoomID = r.RoomID
        JOIN HOSTEL h ON r.HostID = h.HostID
        JOIN SEMESTER s ON b.SemID = s.SemID
        LEFT JOIN EMPLOYEE e ON p.EmpID = e.EmpID
        WHERE p.PymtID = ? AND b.StudID = ?
    ");
    $stmt->bind_param("ss", $pymtID, $studID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Payment not found or not accessible']);
        exit();
    }
    
    // Fetch payment data
    $payment = $result->fetch_assoc();
    $semID = $payment['SemID'];
    
    // Now get all payments for this semester to determine previous payment
    $stmtAll = $conn->prepare("
        SELECT p.PymtID, p.AmountPaid, p.Balance, p.PaymentDate, p.Status
        FROM PAYMENT p
        JOIN BOOKING b ON p.BookID = b.BookID
        WHERE b.StudID = ? AND b.SemID = ? AND p.Status != 'REJECTED'
        ORDER BY p.PaymentDate ASC
    ");
    $stmtAll->bind_param("ss", $studID, $semID);
    $stmtAll->execute();
    $resultAll = $stmtAll->get_result();
    
    // Get all valid payments (non-rejected) for this semester
    $semesterPayments = [];
    while ($row = $resultAll->fetch_assoc()) {
        $semesterPayments[] = $row;
    }
    
    // Find the current payment's position among valid payments
    $currentPosition = -1;
    if ($payment['Status'] != 'REJECTED') {
        foreach ($semesterPayments as $index => $p) {
            if ($p['PymtID'] === $pymtID) {
                $currentPosition = $index;
                break;
            }
        }
    } else {
        // For rejected payments, we need a different approach
        // Get the most recent non-rejected payment before this one
        $stmtPrev = $conn->prepare("
            SELECT p.PymtID, p.AmountPaid, p.Balance
            FROM PAYMENT p
            JOIN BOOKING b ON p.BookID = b.BookID
            WHERE b.StudID = ? AND b.SemID = ? AND p.Status != 'REJECTED' AND p.PaymentDate < ?
            ORDER BY p.PaymentDate DESC
            LIMIT 1
        ");
        $stmtPrev->bind_param("sss", $studID, $semID, $payment['PaymentDate']);
        $stmtPrev->execute();
        $resultPrev = $stmtPrev->get_result();
        
        if ($resultPrev->num_rows > 0) {
            $prevPayment = $resultPrev->fetch_assoc();
            $payment['PreviousAmountPaid'] = $prevPayment['AmountPaid'];
            $payment['PreviousBalance'] = $prevPayment['Balance'];
            $payment['HasPreviousPayment'] = 1;
            
            // Debug log
            error_log("REJECTED Payment: {$pymtID}, Previous: {$prevPayment['PymtID']}, PrevAmount: {$prevPayment['AmountPaid']}, PrevBalance: {$prevPayment['Balance']}");
        } else {
            $payment['PreviousAmountPaid'] = 0;
            $payment['PreviousBalance'] = 0;
            $payment['HasPreviousPayment'] = 0;
            
            // Debug log
            error_log("REJECTED Payment: {$pymtID}, No previous valid payment found");
        }
        
        // Set payment rank
        $payment['PaymentRank'] = 0; // Special value for rejected payments
    }
    
    // Add payment rank and previous payment info for non-rejected payments
    if ($payment['Status'] != 'REJECTED') {
        $payment['PaymentRank'] = $currentPosition + 1;
        
        if ($currentPosition > 0) {
            $prevPayment = $semesterPayments[$currentPosition - 1];
            $payment['PreviousAmountPaid'] = $prevPayment['AmountPaid'];
            $payment['PreviousBalance'] = $prevPayment['Balance'];
            $payment['HasPreviousPayment'] = 1;
            
            // Debug log
            error_log("Payment: {$pymtID}, Previous: {$prevPayment['PymtID']}, PrevAmount: {$prevPayment['AmountPaid']}, PrevBalance: {$prevPayment['Balance']}");
        } else {
            $payment['PreviousAmountPaid'] = 0;
            $payment['PreviousBalance'] = 0;
            $payment['HasPreviousPayment'] = 0;
            
            // Debug log
            error_log("Payment: {$pymtID}, No previous payment found");
        }
    }
    
    // Convert binary image data to base64 for display
    $imageBase64 = base64_encode($payment['PymtProof']);
    $imageType = 'image/jpeg'; // Assuming it's a JPEG image; you can add logic to detect the image type
    
    // Remove the binary data from the payment array before sending to client
    unset($payment['PymtProof']);
    unset($payment['StudID']); // Remove sensitive data
    
    // Return the payment details and image
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'payment' => $payment,
        'proofImage' => 'data:' . $imageType . ';base64,' . $imageBase64
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit();
}
?> 