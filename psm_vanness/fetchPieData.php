<?php
include 'dbConnection.php';

$query = "SELECT CType, COUNT(*) as count FROM `check` GROUP BY CType";
$result = $conn->query($query);

$data = [
    'labels' => [],
    'counts' => []
];

while ($row = $result->fetch_assoc()) {
    $data['labels'][] = $row['CType'];
    $data['counts'][] = (int)$row['count'];
}

header('Content-Type: application/json');
echo json_encode($data);
?>
