<?php
// ajax_get_ingredient_units.php

require 'db_connection.php';
header('Content-Type: application/json');

$itemId = isset($_GET['item_id']) ? $_GET['item_id'] : null;

if (!$itemId) {
    echo json_encode(['error' => 'No item ID provided.']);
    exit();
}

$stmt = $conn->prepare("SELECT ITEM_PURCHASE_UNIT, ITEM_UNIT_BREAKDOWN FROM item_ingredient WHERE ITEM_ID = ?");
$stmt->bind_param("s", $itemId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'Ingredient not found.']);
    exit();
}

$row = $result->fetch_assoc();
$stmt->close();
$conn->close();

$units = [];

// 1. Add the purchase unit (e.g., "Pack", "Sack", "Box")
$purchaseUnit = trim($row['ITEM_PURCHASE_UNIT']);
if (!empty($purchaseUnit)) {
    $units[] = $purchaseUnit;
}

// 2. Intelligently extract the breakdown unit (e.g., "g", "kg", "ml")
$breakdownString = $row['ITEM_UNIT_BREAKDOWN'];
// This regular expression finds the letters at the very end of the string.
if (preg_match('/[a-zA-Z]+$/', $breakdownString, $matches)) {
    $breakdownUnit = trim($matches[0]);
    
    // Add it only if it's not a duplicate of the purchase unit (case-insensitive check)
    $isDuplicate = false;
    foreach ($units as $existingUnit) {
        if (strtolower($existingUnit) === strtolower($breakdownUnit)) {
            $isDuplicate = true;
            break;
        }
    }

    if (!$isDuplicate) {
        $units[] = $breakdownUnit;
    }
}

echo json_encode(['units' => $units]);
?>