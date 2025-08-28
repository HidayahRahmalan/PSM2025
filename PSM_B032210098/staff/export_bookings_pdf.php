<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';
require_once '../fpdf186/fpdf.php';

// Get filter parameters
$date_filter = $_GET['date'] ?? '';
$status_filter = $_GET['status'] ?? '';
$customer_filter = $_GET['customer'] ?? '';

// Build query with filters
$query = 'SELECT r.*, c.customer_full_name, co.console_name FROM rentals r JOIN customers c ON r.customer_ID = c.customer_ID JOIN consoles co ON r.console_ID = co.console_ID WHERE 1';
$params = [];

if ($date_filter) {
    $query .= ' AND DATE(r.booking_start_time) = ?';
    $params[] = $date_filter;
}
if ($status_filter) {
    $query .= ' AND r.rental_status = ?';
    $params[] = $status_filter;
}
if ($customer_filter) {
    $query .= ' AND c.customer_full_name LIKE ?';
    $params[] = '%' . $customer_filter . '%';
}

$query .= ' ORDER BY r.booking_start_time DESC';

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Create PDF
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Title
$pdf->Cell(0, 10, 'PS4 Rental System - Bookings Report', 0, 1, 'C');
$pdf->Ln(5);

// Filters info
$pdf->SetFont('Arial', '', 10);
if ($date_filter) $pdf->Cell(0, 5, 'Date: ' . $date_filter, 0, 1);
if ($status_filter) $pdf->Cell(0, 5, 'Status: ' . $status_filter, 0, 1);
if ($customer_filter) $pdf->Cell(0, 5, 'Customer: ' . $customer_filter, 0, 1);
$pdf->Ln(5);

// Table header
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(30, 8, 'Rental ID', 1);
$pdf->Cell(40, 8, 'Customer', 1);
$pdf->Cell(30, 8, 'Console', 1);
$pdf->Cell(30, 8, 'Start Time', 1);
$pdf->Cell(30, 8, 'Status', 1);
$pdf->Cell(30, 8, 'Amount', 1);
$pdf->Ln();

// Table data
$pdf->SetFont('Arial', '', 8);
foreach ($bookings as $booking) {
    // Get games for this booking
    $stmt2 = $pdo->prepare('SELECT g.game_title FROM rental_games rg JOIN games g ON rg.game_ID = g.game_ID WHERE rg.rental_ID = ?');
    $stmt2->execute([$booking['rental_ID']]);
    $games = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    
    $pdf->Cell(30, 8, $booking['rental_ID'], 1);
    $pdf->Cell(40, 8, substr($booking['customer_full_name'], 0, 15), 1);
    $pdf->Cell(30, 8, substr($booking['console_name'], 0, 12), 1);
    $pdf->Cell(30, 8, date('M d H:i', strtotime($booking['booking_start_time'])), 1);
    $pdf->Cell(30, 8, $booking['rental_status'], 1);
    $pdf->Cell(30, 8, $booking['total_amount'] ? 'RM ' . number_format($booking['total_amount'], 2) : '-', 1);
    $pdf->Ln();
    
    // Add games info on next line if needed
    if (!empty($games)) {
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->Cell(0, 4, 'Games: ' . implode(', ', $games), 0, 1);
        $pdf->SetFont('Arial', '', 8);
    }
}

// Output PDF
$pdf->Output('bookings_report.pdf', 'D');
?> 