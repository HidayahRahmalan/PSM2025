<?php
header("Content-Type: application/json"); // Ensure JSON response
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'dbConnection.php';
require 'vendor/autoload.php'; // Load PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    ob_start();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["success" => false, "message" => "File upload failed."]);
        exit();
    }

    if (!isset($_POST['datasetID'])) {
        echo json_encode(["success" => false, "message" => "Missing DatasetID."]);
        exit();
    }

    $datasetID = $_POST['datasetID'];
    $file = $_FILES['file'];
    $fileTmpPath = $file['tmp_name'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $allowedExtensions = ['xls', 'xlsx', 'csv'];

    try {
        if ($fileExtension === 'csv') {
            $handle = fopen($fileTmpPath, "r");
            if (!$handle) throw new Exception("Failed to open CSV file.");
    
            fgetcsv($handle); // Skip header row
    
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                error_log("Processing CSV Row: " . json_encode($row));
                processRecord($conn, $row, $datasetID);
            }
            fclose($handle);
        } else {
            $spreadsheet = IOFactory::load($fileTmpPath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, false);
            array_shift($data); // Remove header row

            foreach ($data as $row) {
                error_log("Processing Excel Row: " . json_encode($row));
                processRecord($conn, $row, $datasetID);
            }
        }

        echo json_encode(["success" => true, "message" => "Data inserted successfully."]);
    } catch (Exception $e) {
        error_log("File Processing Error: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Error processing file: " . $e->getMessage()]);
    }

    ob_end_flush();
    $conn->close();
}

/**
 * Process a row: insert into the `record` table, then call InsertPayout or InsertRefund
 */
function processRecord($conn, $row, $datasetID) {
    try {
        // Determine record type
        $columnCount = count($row);
        if ($columnCount == 8) {
            $rType = 'Payout';
        } elseif ($columnCount == 6) {
            $rType = 'Refund';
        } else {
            throw new Exception("Unable to determine record type. Column count: " . $columnCount);
        }
        error_log("Determined record type: " . $rType);

        // Step 1: Insert into `record` table and get RecordID
        $recordID = insertRecord($conn, $rType, $datasetID);
        if (!$recordID) throw new Exception("Failed to retrieve RecordID.");

        // Step 2: Insert into Payout or Refund table
        if ($rType === 'Payout') {
            insertPayout($conn, $row, $recordID);
        } else {
            insertRefund($conn, $row, $recordID);
        }
    } catch (Exception $e) {
        error_log("Processing Error: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Error processing record: " . $e->getMessage()]);
        exit();
    }
}

/**
 * Inserts a record into the `record` table and returns the generated RecordID
 */
function insertRecord($conn, $rType, $datasetID) {
    try {
        error_log("Calling InsertRecord for datasetID: " . $datasetID);

        // Call stored procedure
        $sql = "CALL InsertRecord(?, ?, @newRecordID)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $rType, $datasetID);
        $stmt->execute();
        $stmt->close();

        // Retrieve the generated RecordID
        $result = $conn->query("SELECT RecordID FROM record ORDER BY ID DESC LIMIT 1");
        $recordRow = $result->fetch_assoc();
        $recordID = $recordRow['RecordID'];
        $result->free();

        if (!$recordID) {
            throw new Exception("Failed to retrieve RecordID.");
        }
        error_log("Retrieved RecordID: " . $recordID);
        return $recordID;
    } catch (Exception $e) {
        error_log("InsertRecord Error: " . $e->getMessage());
        throw new Exception($e->getMessage());
    }
}

/**
 * Calls the InsertPayout procedure
 */
function insertPayout($conn, $row, $recordID) {
    try {
        error_log("Inserting into Payout table with RecordID: " . $recordID);

        $sql = "CALL InsertPayout(?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Error preparing InsertPayout: " . $conn->error);

        // Assign variables
        $orderID = $row[0];
        $transactionDate = date('Y-m-d', strtotime($row[1]));
        $grossSalesAmount = $row[2];
        $platformFees = $row[3];
        $transactionFees = $row[4];
        $shippingFees = $row[5];
        $refundsIssued = $row[6];
        $netPayoutAmount = $row[7];

        $stmt->bind_param("ssssdddds",
            $orderID, $transactionDate, $grossSalesAmount, $platformFees,
            $transactionFees, $shippingFees, $refundsIssued, $netPayoutAmount, $recordID
        );

        if (!$stmt->execute()) throw new Exception("Error executing InsertPayout: " . $stmt->error);
        error_log("InsertPayout executed successfully.");
        $stmt->close();
    } catch (Exception $e) {
        error_log("InsertPayout Error: " . $e->getMessage());
        throw new Exception($e->getMessage());
    }
}

/**
 * Calls the InsertRefund procedure
 */
function insertRefund($conn, $row, $recordID) {
    try {
        error_log("Inserting into Refund table with RecordID: " . $recordID);

        $sql = "CALL InsertRefund(?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Error preparing InsertRefund: " . $conn->error);

        // Assign variables
        $orderID = $row[0];
        $productName = $row[1];
        $returnRequestDate = date('Y-m-d', strtotime($row[2]));
        $refundAmount = $row[3];
        $reasonForReturn = $row[4];
        $returnStatus = $row[5];

        $stmt->bind_param("sssdsss",
            $orderID, $productName, $returnRequestDate, $refundAmount,
            $reasonForReturn, $returnStatus, $recordID
        );

        if (!$stmt->execute()) throw new Exception("Error executing InsertRefund: " . $stmt->error);
        error_log("InsertRefund executed successfully.");
        $stmt->close();
    } catch (Exception $e) {
        error_log("InsertRefund Error: " . $e->getMessage());
        throw new Exception($e->getMessage());
    }
}

//not use
function insertCheck($conn, $cType, $cStatus, $datasetID) {
    // Prepare the SQL statement to call the stored procedure
    $sql = "CALL InsertCheck(?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        throw new Exception("Error preparing SQL statement: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param("sss", $cType, $cStatus, $datasetID);

    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Error executing query: " . $stmt->error);
    }

    $stmt->close();
}

//not use
function checkDuplicates($dataRows) {
    $orderIDList = [];
    $productNameList = [];
    $duplicates = [];

    foreach ($dataRows as $row) {
        $orderID = $row[0];
        $productName = $row[2];

        if (isset($orderIDList[$orderID])) {
            $duplicates[] = ["type" => "Order ID", "value" => $orderID, "row" => $row];
        } else {
            $orderIDList[$orderID] = true;
        }

        if (isset($productNameList[$productName])) {
            $duplicates[] = ["type" => "Product Name", "value" => $productName, "row" => $row];
        } else {
            $productNameList[$productName] = true;
        }
    }
    
    return $duplicates;
}
?>
