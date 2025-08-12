<?php
// admMLDataUpload.php
// Handles CSV file uploads for ML data import

session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: staffMainPage.php");
    exit();
}

// Check if file was uploaded
if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    $error = "No file uploaded or upload error occurred.";
    header("Location: admViewReport.php?tab=mlData&error=" . urlencode($error));
    exit();
}

$uploadedFile = $_FILES['csvFile'];

// Validate file type
$fileType = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
if (strtolower($fileType) !== 'csv') {
    $error = "Only CSV files are allowed.";
    header("Location: admViewReport.php?tab=mlData&error=" . urlencode($error));
    exit();
}

// Validate file size (5MB max)
if ($uploadedFile['size'] > 5 * 1024 * 1024) {
    $error = "File size must be less than 5MB.";
    header("Location: admViewReport.php?tab=mlData&error=" . urlencode($error));
    exit();
}

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Generate unique filename
$timestamp = date('Y-m-d_H-i-s');
$filename = 'ml_historical_data_' . $timestamp . '.csv';
$filepath = $uploadDir . $filename;

// Move uploaded file
if (!move_uploaded_file($uploadedFile['tmp_name'], $filepath)) {
    $error = "Failed to save uploaded file.";
    header("Location: admViewReport.php?tab=mlData&error=" . urlencode($error));
    exit();
}

// Validate CSV format
$handle = fopen($filepath, 'r');
if (!$handle) {
    $error = "Failed to read uploaded file.";
    header("Location: admViewReport.php?tab=mlData&error=" . urlencode($error));
    exit();
}

    // Read header row
    $header = fgetcsv($handle);
    $expectedHeaders = [
        'Semester', 'Year', 'Hostel', 'Total_Severe_Chronic_Students',
        'Booked_Severe_Chronic_Students', 'Room_Full_Rejections',
        'Unbooked_Severe_Chronic_Students', 'Graduating_Students',
        'Current_Occupancy', 'Actual_Demand'
    ];

    // Make header comparison case-insensitive
    $headerLower = array_map('strtolower', $header);
    $expectedHeadersLower = array_map('strtolower', $expectedHeaders);

    if ($headerLower !== $expectedHeadersLower) {
        fclose($handle);
        unlink($filepath); // Delete invalid file
        $error = "Invalid CSV format. Please use the template provided. Expected headers: " . implode(', ', $expectedHeaders);
        header("Location: admViewReport.php?tab=mlData&error=" . urlencode($error));
        exit();
    }

    // Count rows and validate data
    $rowCount = 1; // Start from 1 since we already read the header
    $errors = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rowCount++;
        
        // Skip empty rows
        if (empty(array_filter($row))) {
            continue;
        }
        
        // Skip example row validation but keep row number for error messages
        if (isset($row[0]) && strpos($row[0], 'EXAMPLE') !== false) {
            continue; // Skip validation but rowCount is already incremented
        }
        
        // Validate row has correct number of columns
        if (count($row) !== count($expectedHeaders)) {
            $errors[] = "Row $rowCount: Incorrect number of columns";
            continue;
        }
        
        // Validate data types
        if (!is_numeric($row[0]) || $row[0] < 1 || $row[0] > 3) { // Semester
            $errors[] = "Row $rowCount: Invalid semester (must be 1, 2, or 3)";
        }
    
    // Validate year format (cohort format like 2024/2025)
    if (!preg_match('/^\d{4}\/\d{4}$/', $row[1])) { // Year
        $errors[] = "Row $rowCount: Invalid year format (must be in cohort format like 2024/2025)";
    } else {
        $years = explode('/', $row[1]);
        $startYear = intval($years[0]);
        $endYear = intval($years[1]);
        if ($startYear < 2000 || $startYear > 2030 || $endYear !== $startYear + 1) {
            $errors[] = "Row $rowCount: Invalid year range (must be consecutive years like 2024/2025)";
        }
    }
    
    if (empty($row[2])) { // Hostel
        $errors[] = "Row $rowCount: Hostel name cannot be empty";
    } else {
        // Validate hostel name includes gender in brackets
        if (!preg_match('/\(MALE\)|\(FEMALE\)/i', $row[2])) {
            $errors[] = "Row $rowCount: Hostel name must include gender in brackets (MALE) or (FEMALE)";
        }
        
        // Convert hostel name to uppercase for consistency
        $row[2] = strtoupper(trim($row[2]));
    }
    
    // Validate numeric fields
    for ($i = 3; $i < count($row); $i++) {
        if (!is_numeric($row[$i]) || $row[$i] < 0) {
            $errors[] = "Row $rowCount: Column " . ($i + 1) . " must be a non-negative number";
        }
    }
}

fclose($handle);

    // If there are validation errors, delete file and show errors
    if (!empty($errors)) {
        unlink($filepath);
        $error = "Validation errors found:<br>" . implode("<br>", $errors);
        header("Location: admViewReport.php?tab=mlData&error=" . urlencode($error));
        exit();
    }

// Store file information in session for later use
$_SESSION['uploaded_ml_data'] = [
    'filename' => $filename,
    'filepath' => $filepath,
    'row_count' => $rowCount,
    'upload_time' => date('Y-m-d H:i:s')
];

// Success message
$success = "File uploaded successfully! $rowCount rows of data validated. You can now generate the combined dataset.";
header("Location: admViewReport.php?tab=mlData&success=" . urlencode($success));
exit();
?> 