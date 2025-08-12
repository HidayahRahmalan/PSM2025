<?php
header("Content-Type: application/json");
include 'dbConnection.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);

    $edate = date('Y-m-d'); // Use current date
    $eformat = $input['eformat'] ?? null;
    $datasetid = $input['datasetid'] ?? null;

    if (!$eformat || !$datasetid) {
        echo json_encode(["success" => false, "message" => "Missing export format or dataset ID."]);
        exit();
    }

    try {
        $sql = "CALL InsertExport(?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $edate, $eformat, $datasetid);
        $stmt->execute();
        $stmt->close();

        echo json_encode(["success" => true, "message" => "Export logged successfully."]);
    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
    $conn->close();
}
?>