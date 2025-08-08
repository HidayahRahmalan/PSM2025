<?php
include 'dbConnection.php';

if (!isset($_GET['datasetID'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing datasetID']);
    exit;
}

$datasetID = $_GET['datasetID'];

$response = [
    'DSName' => '',
    'DSUploadDate' => '',
    'DSFormat' => '',
    'TotalRecords' => 0,
    'Actions' => []
];

// Get dataset basic info
$datasetQuery = $conn->prepare("SELECT DSName, DSUploadDate, DSFormat FROM DATASET WHERE DatasetID = ?");
$datasetQuery->bind_param("s", $datasetID);
$datasetQuery->execute();
$datasetResult = $datasetQuery->get_result();

if ($datasetResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Dataset not found']);
    exit;
}

$dataset = $datasetResult->fetch_assoc();
$response['DSName'] = $dataset['DSName'];
$response['DSUploadDate'] = $dataset['DSUploadDate'];
$response['DSFormat'] = $dataset['DSFormat'];

// Count total records in RECORD table
$recordCountQuery = $conn->prepare("SELECT COUNT(*) as Total FROM RECORD WHERE DatasetID = ?");
$recordCountQuery->bind_param("s", $datasetID);
$recordCountQuery->execute();
$countResult = $recordCountQuery->get_result()->fetch_assoc();
$response['TotalRecords'] = (int)$countResult['Total'];

// Fetch action history (join RECORD and ACTION)
$actionQuery = $conn->prepare("
    SELECT A.AType, A.ADetail, A.ADate 
    FROM ACTION A
    JOIN RECORD R ON A.RecordID = R.RecordID
    WHERE R.DatasetID = ?
    ORDER BY A.ADate DESC
");
$actionQuery->bind_param("s", $datasetID);
$actionQuery->execute();
$actionResult = $actionQuery->get_result();

while ($row = $actionResult->fetch_assoc()) {
    $response['Actions'][] = [
        'AType' => $row['AType'],
        'ADetail' => $row['ADetail'],
        'ADate' => $row['ADate']
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
?>
