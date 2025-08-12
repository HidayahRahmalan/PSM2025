<?php
// test_ml_generation.php
// Simple test script to check ML data generation functionality

session_start();

// Include database connection
include 'dbConnection.php';

// Set JSON header
header('Content-Type: application/json');

echo json_encode([
    'success' => true,
    'message' => 'Test script working',
    'session_data' => isset($_SESSION['uploaded_ml_data']) ? $_SESSION['uploaded_ml_data'] : 'No uploaded data',
    'database_connected' => isset($conn) ? 'Yes' : 'No',
    'current_directory' => getcwd(),
    'directory_writable' => is_writable('.') ? 'Yes' : 'No',
    'uploads_directory' => file_exists('uploads/') ? 'Exists' : 'Does not exist',
    'uploads_writable' => file_exists('uploads/') && is_writable('uploads/') ? 'Yes' : 'No'
]);
?> 