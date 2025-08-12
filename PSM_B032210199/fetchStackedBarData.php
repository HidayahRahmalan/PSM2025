<?php
// fetchStackedBarData.php
include 'dbConnection.php';

$data = $conn->query("
    SELECT dataset.DSName, `check`.CStatus, COUNT(*) AS count
    FROM `check`
    JOIN dataset ON `check`.DatasetID = dataset.DatasetID
    GROUP BY dataset.DSName, `check`.CStatus
");

$results = [];

while ($row = $data->fetch_assoc()) {
    $dsName = $row['DSName'];
    $status = $row['CStatus'];

    if (!isset($results[$dsName])) {
        $results[$dsName] = ['Passed' => 0, 'Failed' => 0];
    }

    $results[$dsName][$status] = (int)$row['count'];
}

$datasets = [];
$labels = array_keys($results);

$passedData = [];
$failedData = [];

foreach ($labels as $name) {
    $passedData[] = $results[$name]['Passed'];
    $failedData[] = $results[$name]['Failed'];
}

echo json_encode([
    'labels' => $labels,
    'passed' => $passedData,
    'failed' => $failedData
]);

?>
