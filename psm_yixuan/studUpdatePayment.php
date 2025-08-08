<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['studID'])) {
    $_SESSION['error'] = "Unauthorized access. Please log in.";
    header("Location: studMainPage.php");
    exit();
}

// Get student ID from session
$studID = $_SESSION['studID'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: studPayment.php?tab=history");
    exit();
}

// Get form data
$pymtID = $_POST['pymtID'];
$paymentMethod = $_POST['paymentMethod'];

try {
    // Begin transaction
    $conn->begin_transaction();
    
    // First, verify that the payment belongs to the current student and is in PENDING status
    $stmt = $conn->prepare("
        SELECT p.PymtID, p.Status, b.StudID
        FROM PAYMENT p
        JOIN BOOKING b ON p.BookID = b.BookID
        WHERE p.PymtID = ? AND b.StudID = ? AND p.Status = 'PENDING'
    ");
    $stmt->bind_param("ss", $pymtID, $studID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Payment not found, not owned by current student, or not in PENDING status
        $conn->rollback();
        $_SESSION['error'] = "Payment not found or cannot be edited.";
        header("Location: studPayment.php?tab=history");
        exit();
    }
    
    // Update payment method
    $stmt = $conn->prepare("
        UPDATE PAYMENT 
        SET PaymentMethod = ?
        WHERE PymtID = ?
    ");
    $stmt->bind_param("ss", $paymentMethod, $pymtID);
    $stmt->execute();
    
    // Check if a new payment proof was uploaded
    if (isset($_FILES['paymentProof']) && $_FILES['paymentProof']['error'] === UPLOAD_ERR_OK) {
        // Read the new file content
        $paymentProof = file_get_contents($_FILES['paymentProof']['tmp_name']);
        
        // Update payment proof
        $stmt = $conn->prepare("
            UPDATE PAYMENT 
            SET PymtProof = ?
            WHERE PymtID = ?
        ");
        $stmt->bind_param("ss", $paymentProof, $pymtID);
        $stmt->execute();
    }
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success'] = "Payment updated successfully.";
    header("Location: studPayment.php?tab=history");
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error'] = "Error updating payment: " . $e->getMessage();
    error_log("Error updating payment: " . $e->getMessage());
    header("Location: studPayment.php?tab=history");
    exit();
}
?> 