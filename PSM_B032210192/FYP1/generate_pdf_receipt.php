<?php
session_start();
require 'db_connection.php';
require('fpdf.php'); // Include the FPDF library

if (!isset($_SESSION['customer_id'], $_GET['payment_id'])) {
    die("Access denied.");
}

// Fetch all the same data as receipt.php
$customer_id = $_SESSION['customer_id'];
$payment_id = $_GET['payment_id'];
// ... (You would copy the same database query logic from receipt.php here to get $payment_details and $order_items) ...
// For brevity, I'll assume the data is fetched.

// --- Create PDF ---
$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);

// Header
$pdf->Cell(0, 10, 'RY\'s Tasty Creation', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, 'Official Receipt', 0, 1, 'C');
$pdf->Ln(10);

// Customer and Receipt Info
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(40, 7, 'Billed To:');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 7, $payment_details['CUST_NAME']);
$pdf->Ln();

// ... Add more details like email, receipt #, date ...

$pdf->Ln(10); // Line break

// Table Header
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(95, 8, 'Item', 1);
$pdf->Cell(25, 8, 'Quantity', 1, 0, 'C');
$pdf->Cell(35, 8, 'Pickup Date', 1, 0, 'C');
$pdf->Cell(35, 8, 'Subtotal', 1, 1, 'R');

// Table Rows
$pdf->SetFont('Arial', '', 12);
$order_items->data_seek(0); // Reset result pointer
while ($item = $order_items->fetch_assoc()) {
    $pdf->Cell(95, 8, $item['PRODUCT_NAME'], 1);
    $pdf->Cell(25, 8, $item['ORDER_QTTY'], 1, 0, 'C');
    $pdf->Cell(35, 8, date("M d, Y", strtotime($item['ORDER_WANTED'])), 1, 0, 'C');
    $pdf->Cell(35, 8, 'RM ' . number_format($item['TOTAL_AMOUNT'], 2), 1, 1, 'R');
}

// Total
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(155, 10, 'Grand Total', 1, 0, 'R');
$pdf->Cell(35, 10, 'RM ' . number_format($payment_details['PAYMENT_TOTAL'], 2), 1, 1, 'R');

// Output the PDF
$pdf->Output('D', 'Receipt_PAY-' . str_pad($payment_id, 6, '0', STR_PAD_LEFT) . '.pdf');
?>