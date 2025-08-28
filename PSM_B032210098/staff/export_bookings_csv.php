<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

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

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="bookings_report_' . date('Y-m-d_H-i-s') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV header
fputcsv($output, [
    'Rental ID',
    'Customer Name',
    'Console Name',
    'Start Time',
    'End Time',
    'Status',
    'Number of Players',
    'Total Amount',
    'Games',
    'Notes',
    'Created At'
]);

// CSV data
foreach ($bookings as $booking) {
    // Get games for this booking
    $stmt2 = $pdo->prepare('SELECT g.game_title FROM rental_games rg JOIN games g ON rg.game_ID = g.game_ID WHERE rg.rental_ID = ?');
    $stmt2->execute([$booking['rental_ID']]);
    $games = $stmt2->fetchAll(PDO::FETCH_COLUMN);
    
    fputcsv($output, [
        $booking['rental_ID'],
        $booking['customer_full_name'],
        $booking['console_name'],
        $booking['booking_start_time'],
        $booking['booking_end_time'],
        $booking['rental_status'],
        $booking['number_of_players'],
        $booking['total_amount'] ? 'RM ' . number_format($booking['total_amount'], 2) : '',
        implode(', ', $games),
        $booking['notes'] ?? '',
        $booking['created_at']
    ]);
}

fclose($output);
?> 