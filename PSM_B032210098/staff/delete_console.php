<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

$console_ID = $_GET['console_ID'] ?? '';
if (!$console_ID) {
    header('Location: inventory_management.php?error=invalid_console');
    exit();
}

// Check if console is currently in use
$stmt = $pdo->prepare('SELECT COUNT(*) FROM rentals WHERE console_ID = ? AND rental_status IN ("confirmed", "in_progress")');
$stmt->execute([$console_ID]);
$in_use = $stmt->fetchColumn() > 0;

if ($in_use) {
    header('Location: inventory_management.php?error=console_in_use');
    exit();
}

// Delete console from database
$stmt = $pdo->prepare('DELETE FROM consoles WHERE console_ID = ?');
if ($stmt->execute([$console_ID])) {
    header('Location: inventory_management.php?success=console_deleted');
} else {
    header('Location: inventory_management.php?error=delete_failed');
}
exit();
?> 