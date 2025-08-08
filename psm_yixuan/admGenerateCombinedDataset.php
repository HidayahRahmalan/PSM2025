<?php
// admGenerateCombinedDataset.php
// Generates combined dataset from system data and uploaded historical data

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
    // Check database connection
    if (!isset($conn) || $conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed. Please check your database configuration.']);
        exit();
    }
    
    // Check if uploaded data exists
    if (!isset($_SESSION['uploaded_ml_data'])) {
        echo json_encode(['success' => false, 'message' => 'No uploaded data found. Please upload a CSV file first.']);
        exit();
    }

    $uploadedData = $_SESSION['uploaded_ml_data'];
    $uploadedFilepath = $uploadedData['filepath'];

    if (!file_exists($uploadedFilepath)) {
        echo json_encode(['success' => false, 'message' => 'Uploaded file not found. Please upload again.']);
        exit();
    }
    
    // Check if uploads directory exists and is writable
    $uploadsDir = 'uploads/';
    if (!file_exists($uploadsDir)) {
        if (!mkdir($uploadsDir, 0755, true)) {
            echo json_encode(['success' => false, 'message' => 'Cannot create uploads directory. Please check permissions.']);
            exit();
        }
    }
    
    if (!is_writable($uploadsDir)) {
        echo json_encode(['success' => false, 'message' => 'Uploads directory is not writable. Please check permissions.']);
        exit();
    }

    // Generate system data using the existing export function
    $systemDataFile = 'system_ml_data_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Capture output to prevent echo statements from interfering with JSON
    ob_start();
    
    // Include the export function logic but suppress output
    try {
        include 'admExportRoomDemandData.php';
    } catch (Exception $e) {
        ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error in export function: ' . $e->getMessage()]);
        exit();
    }
    
    // Clear any output that might have been generated
    ob_end_clean();
    
    // Rename the generated file
    if (file_exists('admRoomDemandData.csv')) {
        if (!rename('admRoomDemandData.csv', $systemDataFile)) {
            echo json_encode(['success' => false, 'message' => 'Failed to rename system data file. Please check permissions.']);
            exit();
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to generate system data. Please check database connection and permissions.']);
        exit();
    }

    // Check if current directory is writable
    if (!is_writable('.')) {
        echo json_encode(['success' => false, 'message' => 'Current directory is not writable. Please check permissions.']);
        exit();
    }
    
    // Create combined dataset
    $combinedFile = 'combined_ml_dataset_' . date('Y-m-d_H-i-s') . '.csv';
    $combinedHandle = fopen($combinedFile, 'w');

    if (!$combinedHandle) {
        echo json_encode(['success' => false, 'message' => 'Failed to create combined dataset file. Please check directory permissions.']);
        exit();
    }

    // Write header (standardized to match template)
    $headers = [
        'Semester', 'Year', 'Hostel', 'Total_Severe_Chronic_Students',
        'Booked_Severe_Chronic_Students', 'Room_Full_Rejections',
        'Unbooked_Severe_Chronic_Students', 'Graduating_Students',
        'Current_Occupancy', 'Actual_Demand', 'Data_Source'
    ];
    fputcsv($combinedHandle, $headers);

    // Add uploaded historical data first (older data)
    $uploadedHandle = fopen($uploadedFilepath, 'r');
    if ($uploadedHandle) {
        // Skip header
        fgetcsv($uploadedHandle);
        
        $rowCount = 0;
        while (($row = fgetcsv($uploadedHandle)) !== false) {
            $rowCount++;
            
            // Skip empty rows and the example row (first data row from template)
            if (!empty(array_filter($row))) {
                // Skip the example row from template (check for common example patterns)
                $isExampleRow = false;
                if ($rowCount === 1) {
                    // Check if this looks like an example row (contains sample data)
                    if (isset($row[0]) && (strpos($row[0], 'EXAMPLE') !== false)) {
                        $isExampleRow = true;
                    }
                    // Also check for old format example rows
                    elseif (isset($row[0]) && $row[0] === '1' && 
                        isset($row[1]) && $row[1] === '2024/2025' && 
                        isset($row[2]) && strpos($row[2], 'KOLEJ KEDIAMAN LEKIU') !== false) {
                        $isExampleRow = true;
                    }
                }
                
                if ($isExampleRow) {
                    continue; // Skip this example row
                }
                
                // Convert hostel name to uppercase for consistency
                if (isset($row[2])) {
                    $row[2] = strtoupper(trim($row[2]));
                }
                
                // Add data source indicator
                $row[] = 'Historical';
                fputcsv($combinedHandle, $row);
            }
        }
        fclose($uploadedHandle);
    }

    // Add system-generated data
    if (!file_exists($systemDataFile)) {
        echo json_encode(['success' => false, 'message' => 'System data file not found after generation.']);
        exit();
    }
    
    $systemHandle = fopen($systemDataFile, 'r');
    if ($systemHandle) {
        // Skip header
        fgetcsv($systemHandle);
        
        while (($row = fgetcsv($systemHandle)) !== false) {
            if (!empty(array_filter($row))) {
                // Convert hostel name to uppercase for consistency
                if (isset($row[2])) {
                    $row[2] = strtoupper(trim($row[2]));
                }
                
                // Add data source indicator
                $row[] = 'System';
                fputcsv($combinedHandle, $row);
            }
        }
        fclose($systemHandle);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to read system data file.']);
        exit();
    }

    fclose($combinedHandle);

    // Check if combined file was created successfully
    if (!file_exists($combinedFile)) {
        echo json_encode(['success' => false, 'message' => 'Failed to create combined dataset file.']);
        exit();
    }

    // Count total rows in combined dataset
    $totalRows = 0;
    $combinedHandle = fopen($combinedFile, 'r');
    if ($combinedHandle) {
        // Skip header
        fgetcsv($combinedHandle);
        while (fgetcsv($combinedHandle) !== false) {
            $totalRows++;
        }
        fclose($combinedHandle);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to read combined dataset file.']);
        exit();
    }

    // Check if we have any data
    if ($totalRows === 0) {
        echo json_encode(['success' => false, 'message' => 'No data was generated. Please check if you have any historical data or system data available.']);
        exit();
    }

    // Clean up temporary files
    if (file_exists($systemDataFile)) {
        unlink($systemDataFile);
    }

    // Return success response with file URL
    $fileUrl = $combinedFile;
    
    echo json_encode([
        'success' => true,
        'message' => "Combined dataset generated successfully! Total rows: $totalRows",
        'fileUrl' => $fileUrl,
        'totalRows' => $totalRows,
        'historicalRows' => $uploadedData['row_count'],
        'systemRows' => $totalRows - $uploadedData['row_count']
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error generating combined dataset: ' . $e->getMessage()
    ]);
}
?> 