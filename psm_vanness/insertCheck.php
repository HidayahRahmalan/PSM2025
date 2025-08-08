<?php
include 'dbConnection.php'; // Include your database connection

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $ctype = $_POST['ctype'];
    $cstatus = $_POST['cstatus'];
    $datasetID = $_POST['datasetID'];

    $stmt = $conn->prepare("CALL InsertCheck(?, ?, ?)");
    $stmt->bind_param("sss", $ctype, $cstatus, $datasetID);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
