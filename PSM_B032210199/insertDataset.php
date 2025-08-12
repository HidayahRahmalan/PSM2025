<?php
session_start();
include 'dbConnection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $dsname = $_POST['dsname'] ?? null;  //Uses the ?? null syntax to assign null if a value is missing
    $dssource = $_POST['dssource'] ?? null;
    $dsformat = $_POST['dsformat'] ?? null;
    $userid = $_POST['userid'] ?? null;
 
    if (!$dsname || !$dssource || !$dsformat || !$userid) {
        echo json_encode(["success" => false, "message" => "Missing required parameters."]);
        exit();
    } //If any required parameter is missing, the script returns an error response and stops execution.

    $sql = "CALL InsertDataset(?, NULL, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        echo json_encode(["success" => false, "message" => "Error preparing statement: " . $conn->error]);
        exit();
    } //If the SQL statement cannot be prepared, return an error and stop execution

    $stmt->bind_param("ssss", $dsname, $dssource, $dsformat, $userid);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $datasetID = $row['DatasetID'];  // Get dataset ID from procedure output
            echo json_encode(["success" => true, "datasetID" => $datasetID]);
        } else {
            echo json_encode(["success" => false, "message" => "Failed to retrieve DatasetID."]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Error executing stored procedure: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
