<?php
session_start();
require 'db_connection.php';

// Security check: Ensure the user is a logged-in owner
if (!isset($_SESSION['seller_id'])) {
    die("Access Denied. You must be logged in to view this page.");
}

// Get product and quantity from the URL, with validation
$product_id = $_GET['product_id'] ?? null;
$quantity_to_produce = isset($_GET['quantity_to_produce']) ? (int)$_GET['quantity_to_produce'] : 0;

if (!$product_id || $quantity_to_produce <= 0) {
    die("Invalid request. Please provide a valid product and quantity.");
}

/**
 * Converts any unit to its base unit (grams or milliliters)
 */
function convert_to_base_unit($quantity, $unit) {
    $unit = strtolower(trim($unit));
    switch ($unit) {
        case 'kg': return $quantity * 1000;
        case 'g':  return $quantity;
        case 'l':  return $quantity * 1000;
        case 'ml': return $quantity;
        case 'tbsp': return $quantity * 15;
        case 'tsp': return $quantity * 5;
        case 'pcs': return $quantity;
        default:   return $quantity;
    }
}

// Fetch the product name for the title
$product_stmt = $conn->prepare("SELECT PRODUCT_NAME FROM products_sell WHERE PRODUCT_ID = ?");
$product_stmt->bind_param("s", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();
$product = $product_result->fetch_assoc();
$product_name = $product ? htmlspecialchars($product['PRODUCT_NAME']) : 'Unknown Product';
$product_stmt->close();

// Fetch recipe and calculate deficits
$recipe_stmt = $conn->prepare("
    SELECT pi.ITEM_ID, pi.PI_QTTY_REQUIRED, pi.PI_UNIT, 
           ii.INVENTORY_STOCK, ii.INVENTORY_UNIT, ii.ITEM_NAME 
    FROM product_ingredient pi 
    JOIN item_ingredient ii ON pi.ITEM_ID = ii.ITEM_ID 
    WHERE pi.PRODUCT_ID = ?
");
$recipe_stmt->bind_param("s", $product_id);
$recipe_stmt->execute();
$recipe_result = $recipe_stmt->get_result();

$shopping_list = [];

while ($ing = $recipe_result->fetch_assoc()) {
    $required_per_pack = convert_to_base_unit($ing['PI_QTTY_REQUIRED'], $ing['PI_UNIT']);
    $total_needed = $required_per_pack * $quantity_to_produce;
    $available_stock = convert_to_base_unit($ing['INVENTORY_STOCK'], $ing['INVENTORY_UNIT']);
    
    if ($available_stock < $total_needed) {
        $deficit = $total_needed - $available_stock;
        $shopping_list[] = [
            'name' => htmlspecialchars($ing['ITEM_NAME']),
            'deficit' => $deficit,
            'unit' => htmlspecialchars($ing['INVENTORY_UNIT']) // Assuming base unit is same as inventory unit
        ];
    }
}
$recipe_stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping List for <?= $product_name ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { max-width: 800px; background-color: #fff; margin-top: 2rem; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .print-header { text-align: center; border-bottom: 2px solid #dee2e6; padding-bottom: 1rem; margin-bottom: 2rem; }
        .no-print { display: block; }
        @media print {
            body { background-color: #fff; }
            .no-print { display: none; }
            .container { box-shadow: none; margin-top: 0; max-width: 100%; border: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="print-header">
            <h1 class="mb-2">Ingredient Shopping List</h1>
            <p class="lead">For production of: <strong><?= $quantity_to_produce ?> pack(s) of <?= $product_name ?></strong></p>
            <p class="text-muted">Generated on: <?= date('F j, Y, g:i a') ?></p>
        </div>

        <?php if (!empty($shopping_list)): ?>
            <h4 class="mb-3">Ingredients to Restock:</h4>
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Ingredient Name</th>
                        <th scope="col" class="text-end">Additional Quantity Needed</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($shopping_list as $index => $item): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= $item['name'] ?></td>
                            <td class="text-end fw-bold"><?= number_format($item['deficit'], 2) ?> <?= $item['unit'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-success text-center">
                <i class="bi bi-check-circle-fill fs-3"></i>
                <h4 class="alert-heading mt-2">All Good!</h4>
                <p>No ingredients need to be restocked for this production batch. You have sufficient stock.</p>
            </div>
        <?php endif; ?>

        <div class="text-center mt-4 no-print">
            <button class="btn btn-primary" onclick="window.print();"><i class="bi bi-printer-fill me-2"></i>Print List</button>
            <button class="btn btn-secondary" onclick="window.close();"><i class="bi bi-x-circle me-2"></i>Close Window</button>
        </div>
    </div>
</body>
</html>