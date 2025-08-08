<?php
ob_clean();
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 0); // DO NOT show errors to client, use error_log instead

session_start();
include 'dbConnection.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        echo json_encode(["success" => false, "message" => "Invalid JSON input."]);
        exit();
    }

    $originalData = $input['originalData'] ?? [];

    if (!isset($input['data']) || !is_array($input['data'])) {
        echo json_encode(["success" => false, "message" => "Missing or invalid data array."]);
        exit();
    }

    if (!isset($input['datasetID'])) {
        echo json_encode(["success" => false, "message" => "Missing DatasetID."]);
        exit();
    }

    $dataRows = $input['data'];
    $datasetID = $input['datasetID'];

    try {
        // Extraction of actions from input
        $actions = isset($input['actions']) ? $input['actions'] : [];
        error_log(print_r($actions, true));

        foreach ($dataRows as $i => $row) { // $i is 0-based index
            $rowNumber = $i + 1; // Assuming actions use 1-based row numbering
            //processRecord($conn, $row, $datasetID, $actions, $rowNumber);

            //actions passed into processRecord()
            processRecord($conn, $row, $datasetID, $actions, $rowNumber, $originalData);
        }

        echo json_encode(["success" => true, "message" => "Data inserted successfully."]);
    } catch (Exception $e) {
        error_log("Processing Error: " . $e->getMessage());
        echo json_encode(["success" => false, "message" => "Error processing data: " . $e->getMessage()]);
    }

    $conn->close();
}

/**
 * Process a row: insert into the `record` table, then call InsertPayout or InsertRefund and insertaction
 */
function processRecord($conn, $row, $datasetID, $actions, $rowNumber, $originalData){
    

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

        // Step 3: Insert actions for this record, avoiding duplicates by (row+column+value)
        $seen = [];
        // Filtering and inserting relevant actions per row
        foreach ($actions as $action) {
            $atype = $action['type'];
            $adetail = $action['detail'];
            $astatus = "Success";
            $adate = date('Y-m-d H:i:s');

            // 🎯 CASE 1: Replace Missing Value (standard)
            if (isset($action['row']) && $action['row'] == $rowNumber && isset($action['column'])) {
                $col = $action['column'];
                $newValue = $action['value'];

                $originalRow = $originalData[$rowNumber - 1]['data'] ?? null;
                if (!$originalRow) continue;

                $originalValue = $originalRow[$col] ?? null;

                if (trim((string)$originalValue) === trim((string)$newValue)) {
                    continue;  // Skip if unchanged
                }

                $key = $rowNumber . '-' . $col . '-' . $newValue;
                if (isset($seen[$key])) continue;
                $seen[$key] = true;

                error_log("Inserting action: Type=$atype, Detail=$adetail, RecordID=$recordID");
                insertAction($conn, $atype, $adetail, $astatus, $adate, $recordID);

            // 🎯 CASE 2: Merge Duplicate — applies only to the 'mergedInto' row
            } elseif ($atype === "Merge Duplicate" && isset($action['mergedInto']) && $action['mergedInto'] == $rowNumber) {
                error_log("Inserting Merge action: Type=$atype, Detail=$adetail, RecordID=$recordID");
                insertAction($conn, $atype, $adetail, $astatus, $adate, $recordID);

            // 🎯 CASE 3: Remove Duplicate — applies to a specific row
            } elseif ($atype === "Remove Duplicate" && isset($action['row']) && $action['row'] == $rowNumber) {
                error_log("Inserting Remove action: Type=$atype, Detail=$adetail, RecordID=$recordID");
                insertAction($conn, $atype, $adetail, $astatus, $adate, $recordID);
            }
        }


    } catch (Exception $e) {
        error_log("Processing Error: " . $e->getMessage());
        throw $e;
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
        throw $e;
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
        throw $e;
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
        throw $e;
    }
}


function insertAction($conn, $atype, $adetail, $astatus, $adate, $recordid) {
    $sql = "CALL InsertAction(?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssss", $atype, $adetail, $astatus, $adate, $recordid);
    
    $success = $stmt->execute();
    if (!$success) {
        error_log("InsertAction failed: " . $stmt->error);
    }else {
        error_log("InsertAction succeeded.");
    }
    
    $stmt->close();
}
