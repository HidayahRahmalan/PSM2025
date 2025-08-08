<?php
include 'dbConnection.php';

// Heatmap data
$heatmap = [];
$max = 0;
$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$hours = range(0, 23);

foreach ($days as $dayIndex => $day) {
    foreach ($hours as $hour) {
        $heatmap["$dayIndex-$hour"] = 0;
    }
}

$sql = "SELECT DAYOFWEEK(created_at)-1 AS day, HOUR(created_at) AS hour, COUNT(*) AS count FROM audit_logs WHERE action LIKE '%Log in%' GROUP BY day, hour";
$result = $conn->query($sql);
while($row = $result->fetch_assoc()) {
    $key = "{$row['day']}-{$row['hour']}";
    $heatmap[$key] = (int)$row['count'];
    if ((int)$row['count'] > $max) $max = (int)$row['count'];
}

$heatmapData = [];
foreach ($heatmap as $key => $value) {
    [$day, $hour] = explode('-', $key);
    $heatmapData[] = ['x' => (int)$hour, 'y' => $days[$day], 'v' => $value];
}

// Bar chart data
$sql = "SELECT u.UName, COUNT(*) AS action_count FROM audit_logs a JOIN USER u ON a.UserID = u.UserID GROUP BY a.UserID";
$result = $conn->query($sql);
$users = [];
$counts = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row['UName'];
    $counts[] = (int)$row['action_count'];
}

// Output
echo json_encode([
    "heatmap" => [
        "data" => $heatmapData,
        "days" => $days,
        "hours" => array_map(fn($h) => str_pad($h, 2, '0', STR_PAD_LEFT), $hours),
        "max" => $max
    ],
    "bar" => [
        "users" => $users,
        "counts" => $counts
    ]
]);
?>
