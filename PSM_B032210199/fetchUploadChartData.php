<?php
include 'dbConnection.php';

header('Content-Type: application/json');

$type = isset($_GET['type']) ? $_GET['type'] : 'format';

// === Line Chart: Uploads over Time ===
$lineQuery = "
    SELECT DATE(DSUploadDate) as uploadDate, COUNT(*) as count
    FROM dataset
    GROUP BY DATE(DSUploadDate)
    ORDER BY uploadDate
";

$dates = [];
$uploadCounts = [];

$result = $conn->query($lineQuery);
while ($row = $result->fetch_assoc()) {
    $dates[] = $row['uploadDate'];
    $uploadCounts[] = (int)$row['count'];
}

// === Bar Chart: Uploads by Format or User ===
$barLabels = [];
$barData = [];
$barLabelType = '';

if ($type === 'user') {
    $barLabelType = 'User';
    $barQuery = "
        SELECT user.UName as label, COUNT(*) as count
        FROM dataset
        JOIN user ON dataset.UserID = user.UserID
        WHERE user.URole = 'user'
        GROUP BY user.UName
        ORDER BY count DESC
    ";
} else {
    $barLabelType = 'Format';
    $barQuery = "
        SELECT DSFormat as label, COUNT(*) as count
        FROM dataset
        GROUP BY DSFormat
        ORDER BY count DESC
    ";
}

$barResult = $conn->query($barQuery);
while ($row = $barResult->fetch_assoc()) {
    $barLabels[] = $row['label'];
    $barData[] = (int)$row['count'];
}

echo json_encode([
    "dates" => $dates,
    "uploadCounts" => $uploadCounts,
    "barLabels" => $barLabels,
    "barData" => $barData,
    "barLabelType" => $barLabelType
]);
