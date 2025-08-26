<?php
session_start();
require 'db_connection.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'seller' || !isset($_GET['item_id'])) {
    echo json_encode(['error' => 'Unauthorized or invalid request.']);
    exit();
}

$item_id = $_GET['item_id'];

$stmt = $conn->prepare("SELECT INVENTORY_UNIT, ITEM_PURCHASE_UNIT FROM item_ingredient WHERE ITEM_ID = ?");
$stmt->bind_param("s", $item_id);
$stmt->execute();
$result = $stmt->get_result();

if ($unit_data = $result->fetch_assoc()) {
    $inventory_unit = $unit_data['INVENTORY_UNIT'];
    preg_match('/(kg|g|l|ml|pcs)/i', $unit_data['ITEM_PURCHASE_UNIT'], $matches);
    $breakdown_unit = $matches[1] ?? null;

    echo json_encode([
        'inventory_unit' => $inventory_unit,
        'breakdown_unit' => $breakdown_unit ? strtolower($breakdown_unit) : null
    ]);
} else {
    echo json_encode(['error' => 'Could not find units for the selected ingredient.']);
}

$stmt->close();
$conn->close();
?>