<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $console_name = $_POST['console_name'] ?? '';
    $console_model = $_POST['console_model'] ?? '';
    $location_description = $_POST['location_description'] ?? '';
    $max_controllers = $_POST['max_controllers'] ?? 4;
    $consoles_status = $_POST['consoles_status'] ?? 'available';
    $hourly_rate = $_POST['hourly_rate'] ?? 0;
    $notes = $_POST['notes'] ?? '';

    // Validate required fields
    if (empty($console_name)) {
        header('Location: add_console.php?error=name_required');
        exit();
    }

    // Generate unique console ID
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM consoles');
    $stmt->execute();
    $count = $stmt->fetchColumn();
    $console_ID = 'CON' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

    // Insert console into database
    $stmt = $pdo->prepare('INSERT INTO consoles (console_ID, console_name, console_model, location_description, max_controllers, consoles_status, hourly_rate, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    
    if ($stmt->execute([$console_ID, $console_name, $console_model, $location_description, $max_controllers, $consoles_status, $hourly_rate, $notes])) {
        header('Location: inventory_management.php?success=console_added');
    } else {
        header('Location: add_console.php?error=insert_failed');
    }
    exit();
} else {
    header('Location: add_console.php');
    exit();
}
?> 