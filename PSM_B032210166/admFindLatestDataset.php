<?php
// admFindLatestDataset.php
// Helper script to find the latest combined dataset

session_start();

// Include database connection
include 'dbConnection.php';

// Set JSON header
header('Content-Type: application/json');

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    // Look for combined dataset files in the current directory
    $files = glob('combined_ml_dataset_*.csv');
    
    if (empty($files)) {
        echo json_encode(['success' => false, 'message' => 'No combined dataset found. Please generate a combined dataset first.']);
        exit();
    }
    
    // Sort files by modification time (newest first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $latestFile = $files[0];
    
    // Check if file exists and is readable
    if (!file_exists($latestFile) || !is_readable($latestFile)) {
        echo json_encode(['success' => false, 'message' => 'Latest dataset file is not accessible.']);
        exit();
    }
    
    // Get file info
    $fileInfo = [
        'path' => $latestFile,
        'size' => filesize($latestFile),
        'modified' => date('Y-m-d H:i:s', filemtime($latestFile)),
        'rows' => 0
    ];
    
    // Count rows in the file
    $handle = fopen($latestFile, 'r');
    if ($handle) {
        // Skip header
        fgetcsv($handle);
        
        $rowCount = 0;
        while (fgetcsv($handle) !== false) {
            $rowCount++;
        }
        fclose($handle);
        
        $fileInfo['rows'] = $rowCount;
    }
    
    echo json_encode([
        'success' => true,
        'dataset_path' => $latestFile,
        'file_info' => $fileInfo
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error finding dataset: ' . $e->getMessage()
    ]);
}
?> 