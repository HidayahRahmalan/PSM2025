<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $console_ID = $_POST['console_ID'] ?? '';
    if (!$console_ID) {
        header('Location: inventory_management.php?error=invalid_console');
        exit();
    }

    // Get form data
    $console_name = $_POST['console_name'] ?? '';
    $console_model = $_POST['console_model'] ?? '';
    $location_description = $_POST['location_description'] ?? '';
    $max_controllers = $_POST['max_controllers'] ?? 4;
    $consoles_status = $_POST['consoles_status'] ?? 'available';
    $hourly_rate = $_POST['hourly_rate'] ?? 0;
    $notes = $_POST['notes'] ?? '';

    // Update console in database
    $stmt = $pdo->prepare('UPDATE consoles SET console_name=?, console_model=?, location_description=?, max_controllers=?, consoles_status=?, hourly_rate=?, notes=? WHERE console_ID=?');
    if ($stmt->execute([$console_name, $console_model, $location_description, $max_controllers, $consoles_status, $hourly_rate, $notes, $console_ID])) {
        header('Location: inventory_management.php?success=console_updated');
    } else {
        header('Location: edit_console.php?console_ID=' . $console_ID . '&error=update_failed');
    }
    exit();
} else {
    header('Location: inventory_management.php');
    exit();
}
?> 