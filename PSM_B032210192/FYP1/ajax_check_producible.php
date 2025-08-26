<?php
require 'db_connection.php';
header('Content-Type: application/json');

// Validate input parameters
$product_id = $_POST['product_id'] ?? null;
$quantity_to_produce = isset($_POST['quantity_to_produce']) ? (int)$_POST['quantity_to_produce'] : 0;

// Input validation
if (empty($product_id)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Product ID is required.',
        'producible' => false
    ]);
    exit();
}

if ($quantity_to_produce < 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Quantity to produce must be a positive number.',
        'producible' => false
    ]);
    exit();
}

/**
 * Converts any unit to its base unit (grams or milliliters)
 */
function convert_to_base_unit($quantity, $unit) {
    $unit = strtolower(trim($unit));
    switch ($unit) {
        case 'kg': return $quantity * 1000;     // 1 kg = 1000 g
        case 'g':  return $quantity;            // Already in grams
        case 'l':  return $quantity * 1000;     // 1 liter = 1000 ml
        case 'ml': return $quantity;            // Already in ml
        case 'tbsp': return $quantity * 15;     // 1 tbsp ≈ 15 ml
        case 'tsp': return $quantity * 5;       // 1 tsp ≈ 5 ml
        case 'pcs': return $quantity;           // Pieces don't need conversion
        default:   return $quantity;            // Unknown unit - assume it's already base
    }
}

try {
    // Get recipe and current stock levels
    $recipe_stmt = $conn->prepare("
        SELECT 
            pi.ITEM_ID, 
            pi.PI_QTTY_REQUIRED, 
            pi.PI_UNIT, 
            ii.ITEM_NAME, 
            ii.INVENTORY_STOCK, 
            ii.INVENTORY_UNIT,
            ii.ITEM_ID
        FROM product_ingredient pi 
        JOIN item_ingredient ii ON pi.ITEM_ID = ii.ITEM_ID 
        WHERE pi.PRODUCT_ID = ?
    ");
    
    if (!$recipe_stmt) {
        throw new Exception("Database query preparation failed: " . $conn->error);
    }
    
    $recipe_stmt->bind_param("s", $product_id);
    if (!$recipe_stmt->execute()) {
        throw new Exception("Database query execution failed: " . $recipe_stmt->error);
    }
    
    $recipe_result = $recipe_stmt->get_result();
    
    if ($recipe_result->num_rows === 0) {
        echo json_encode([
            'success' => true,
            'producible' => false,
            'message' => 'This product has no recipe defined.',
            'recipe' => []
        ]);
        exit();
    }

    $recipe_details = [];
    $insufficient_items = [];
    $can_produce = ($quantity_to_produce > 0); // Default to true if quantity > 0
    
    while ($ingredient = $recipe_result->fetch_assoc()) {
        $required_per_pack = (float)$ingredient['PI_QTTY_REQUIRED'];
        $required_unit = $ingredient['PI_UNIT'];
        $current_stock = (float)$ingredient['INVENTORY_STOCK'];
        $stock_unit = $ingredient['INVENTORY_UNIT'];
        
        // Convert both required and available quantities to base units
        $required_base = convert_to_base_unit($required_per_pack, $required_unit);
        $available_base = convert_to_base_unit($current_stock, $stock_unit);
        
        $total_needed = $required_base * $quantity_to_produce;
        $is_sufficient = ($available_base >= $total_needed);
        
        if (!$is_sufficient && $quantity_to_produce > 0) {
            $can_produce = false;
            $insufficient_items[] = $ingredient['ITEM_NAME'];
        }
        
        $recipe_details[] = [
            'item_id' => $ingredient['ITEM_ID'],
            'name' => $ingredient['ITEM_NAME'],
            'required_per_pack' => $required_per_pack,
            'required_unit' => $required_unit,
            'stock_available' => $current_stock,
            'stock_unit' => $stock_unit,
            'needed_total' => $total_needed,
            'available_base' => $available_base,
            'is_sufficient' => $is_sufficient,
            'deficit' => $is_sufficient ? 0 : ($total_needed - $available_base)
        ];
    }
    
    $recipe_stmt->close();
    
    // Prepare response
    $response = [
        'success' => true,
        'producible' => $can_produce,
        'recipe' => $recipe_details,
        'message' => $can_produce 
            ? 'Sufficient ingredients available.' 
            : 'Insufficient stock for: ' . implode(', ', $insufficient_items),
        'insufficient_items' => $insufficient_items,
        'quantity_requested' => $quantity_to_produce
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'producible' => false
    ]);
} finally {
    if (isset($conn)) {
        $conn->close();
    }
}
?>