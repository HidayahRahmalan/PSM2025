<?php
session_start();
require 'db_connection.php';

// Security check
if (!isset($_SESSION['seller_id'])) {
    $_SESSION['error'] = "You must be logged in as an owner to access this page.";
    header("Location: login.php");
    exit();
}
$owner_name = isset($_SESSION['seller_name']) ? htmlspecialchars($_SESSION['seller_name']) : 'Owner';

// Message handling
$form_message = '';
$form_message_type = 'danger';
if (isset($_SESSION['form_message'])) {
    $form_message = $_SESSION['form_message'];
    $form_message_type = $_SESSION['form_message_type'] ?? 'danger';
    unset($_SESSION['form_message'], $_SESSION['form_message_type']);
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

// Function to check if production is needed for a product
function checkProductionNeeded($conn, $product_id) {
    // Get current stock
    $stock_stmt = $conn->prepare("SELECT PRODUCT_QTTY FROM products_sell WHERE PRODUCT_ID = ?");
    $stock_stmt->bind_param("s", $product_id);
    $stock_stmt->execute();
    $stock_result = $stock_stmt->get_result();
    $current_stock = $stock_result->fetch_assoc()['PRODUCT_QTTY'] ?? 0;
    $stock_stmt->close();

    // Get pending orders for this product
    $orders_stmt = $conn->prepare("
        SELECT SUM(ORDER_QTTY) as total_ordered 
        FROM customer_order 
        WHERE PRODUCT_ID = ? 
        AND ORDER_STATUS IN ('Pending', 'Confirmed', 'Processing')
    ");
    $orders_stmt->bind_param("s", $product_id);
    $orders_stmt->execute();
    $orders_result = $orders_stmt->get_result();
    $total_ordered = $orders_result->fetch_assoc()['total_ordered'] ?? 0;
    $orders_stmt->close();

    // Calculate needed production (buffer of 10% extra)
    $needed = max(0, ceil(($total_ordered - $current_stock) * 1.1));
    
    return [
        'current_stock' => $current_stock,
        'pending_orders' => $total_ordered,
        'needed_production' => $needed,
        'production_needed' => ($needed > 0)
    ];
}

// Function to check if ingredients are sufficient for production
function checkIngredientsSufficient($conn, $product_id, $quantity) {
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
    
    $sufficient = true;
    $missing_ingredients = [];
    
    while ($ing = $recipe_result->fetch_assoc()) {
        $required_per_pack = convert_to_base_unit($ing['PI_QTTY_REQUIRED'], $ing['PI_UNIT']);
        $total_needed = $required_per_pack * $quantity;
        $available_stock = convert_to_base_unit($ing['INVENTORY_STOCK'], $ing['INVENTORY_UNIT']);
        
        if ($available_stock < $total_needed) {
            $sufficient = false;
            $missing_ingredients[] = [
                'name' => $ing['ITEM_NAME'],
                'needed' => $total_needed,
                'available' => $available_stock,
                'unit' => $ing['INVENTORY_UNIT']
            ];
        }
    }
    $recipe_stmt->close();
    
    return [
        'sufficient' => $sufficient,
        'missing_ingredients' => $missing_ingredients
    ];
}

// Process production if requested
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['produce_for_order'])) {
    $product_id = $_POST['product_id'] ?? '';
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 0;
    $expiry_date = date('Y-m-d', strtotime('+7 days')); // Default 7 days expiry
    
    if (empty($product_id) || $quantity <= 0) {
        $_SESSION['form_message'] = "Invalid production request.";
        header("Location: production_ordered.php");
        exit();
    }
    
    // Check ingredients
    $ingredient_check = checkIngredientsSufficient($conn, $product_id, $quantity);
    
    if (!$ingredient_check['sufficient']) {
        $missing_list = array_map(function($item) {
            return "{$item['name']} (need {$item['needed']} {$item['unit']}, have {$item['available']})";
        }, $ingredient_check['missing_ingredients']);
        
        $_SESSION['form_message'] = "Cannot produce: Insufficient ingredients. " . implode(", ", $missing_list);
        header("Location: production_ordered.php");
        exit();
    }
    
    // Get recipe for deduction
    $recipe_stmt = $conn->prepare("
        SELECT pi.ITEM_ID, pi.PI_QTTY_REQUIRED, pi.PI_UNIT 
        FROM product_ingredient pi 
        WHERE pi.PRODUCT_ID = ?
    ");
    $recipe_stmt->bind_param("s", $product_id);
    $recipe_stmt->execute();
    $recipe_result = $recipe_stmt->get_result();
    
    $ingredient_usage_list = [];
    while ($ing = $recipe_result->fetch_assoc()) {
        $total_needed = convert_to_base_unit($ing['PI_QTTY_REQUIRED'] * $quantity, $ing['PI_UNIT']);
        $ingredient_usage_list[] = [
            'item_id' => $ing['ITEM_ID'],
            'total_used' => $total_needed
        ];
    }
    $recipe_stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    try {
        // 1. Deduct ingredients
        $update_ing_stmt = $conn->prepare("
            UPDATE item_ingredient 
            SET INVENTORY_STOCK = INVENTORY_STOCK - ?, 
                ITEM_USED = ITEM_USED + ? 
            WHERE ITEM_ID = ?
        ");
        foreach ($ingredient_usage_list as $usage) {
            $update_ing_stmt->bind_param("dds", $usage['total_used'], $usage['total_used'], $usage['item_id']);
            $update_ing_stmt->execute();
        }
        $update_ing_stmt->close();

        // 2. Add finished product stock
        $update_prod_stmt = $conn->prepare("
            UPDATE products_sell 
            SET PRODUCT_QTTY = PRODUCT_QTTY + ? 
            WHERE PRODUCT_ID = ?
        ");
        $update_prod_stmt->bind_param("is", $quantity, $product_id);
        $update_prod_stmt->execute();
        $update_prod_stmt->close();
        
        // 3. Log the production event
        $insert_log_stmt = $conn->prepare("
            INSERT INTO product_produce 
            (PRODUCT_ID, PP_DATE, PP_EXPIRED, PP_QTTY, PP_QTTY_WANTED, PP_QTTY_REMAINING) 
            VALUES (?, CURDATE(), ?, ?, ?, ?)
        ");
        $insert_log_stmt->bind_param("ssiii", $product_id, $expiry_date, $quantity, $quantity, $quantity);
        $insert_log_stmt->execute();
        $insert_log_stmt->close();

        $conn->commit();
        $_SESSION['form_message'] = "<strong>Success!</strong> Produced $quantity pack(s) to fulfill orders.";
        $_SESSION['form_message_type'] = 'success';

    } catch (Exception $e) {
        $conn->rollback();
        error_log("Production Transaction Failed: " . $e->getMessage()); 
        $_SESSION['form_message'] = "Database error: " . $e->getMessage();
    }
    header("Location: production_ordered.php");
    exit();
}

// Get all products that need production
$products_needing_production = [];
$products_result = $conn->query("SELECT PRODUCT_ID, PRODUCT_NAME FROM products_sell ORDER BY PRODUCT_NAME");
while ($product = $products_result->fetch_assoc()) {
    $production_status = checkProductionNeeded($conn, $product['PRODUCT_ID']);
    if ($production_status['production_needed']) {
        $ingredient_status = checkIngredientsSufficient($conn, $product['PRODUCT_ID'], $production_status['needed_production']);
        $products_needing_production[] = [
            'product_id' => $product['PRODUCT_ID'],
            'product_name' => $product['PRODUCT_NAME'],
            'current_stock' => $production_status['current_stock'],
            'pending_orders' => $production_status['pending_orders'],
            'needed_production' => $production_status['needed_production'],
            'ingredients_sufficient' => $ingredient_status['sufficient'],
            'missing_ingredients' => $ingredient_status['missing_ingredients']
        ];
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Production Notifications - RY's Tasty Creations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --primary-dark: #4b1c1c; --primary-accent: #ffc107; --border-color: #dee2e6; }
    body { background-color: #f4f7f6; display: flex; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
    .sidebar { width: 280px; background-color: var(--primary-dark); color: white; flex-shrink: 0; }
    .sidebar .nav-link { color: #e9ecef; padding: 0.8rem 1.5rem; font-size: 1.05rem; border-left: 4px solid transparent; transition: all 0.2s; }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); color: white; }
    .sidebar .nav-link.active { border-left-color: var(--primary-accent); font-weight: 600; }
    .sidebar .nav-link .bi { margin-right: 0.8rem; font-size: 1.2rem; vertical-align: text-bottom; }
    .sidebar-header { padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .main-content { flex-grow: 1; display: flex; flex-direction: column; }
    .main-header { background-color: #fff; padding: 1rem 2.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .content-wrapper { padding: 2.5rem; overflow-y: auto; flex-grow: 1; }
    .content-card { background: white; border-radius: 0.75rem; box-shadow: 0 5px 20px rgba(0,0,0,0.05); border: 1px solid #e9ecef;}
    .alert-notification { border-left: 5px solid var(--primary-accent); }
    .ingredient-sufficient { color: #198754; }
    .ingredient-insufficient { color: #dc3545; }
    .stock-bar { height: 8px; border-radius: 4px; }
    .badge-notification { background-color: var(--primary-accent); color: var(--primary-dark); }
  </style>
</head>
<body>
  <!-- Sidebar Navigation -->
  <div class="sidebar d-flex flex-column p-0">
    <div class="sidebar-header text-center">
      <h4 class="fw-bold mb-1">RY's Tasty Creations</h4>
      <p class="text-white-50 mb-0">Owner Panel</p>
    </div>
    <ul class="nav flex-column my-4">
      <li class="nav-item"><a href="owner_dashboard.php" class="nav-link "><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="nav-item"><a href="view_orders.php" class="nav-link"><i class="bi bi-receipt"></i> Order Management</a></li>
        <li class="nav-item"><a href="produce_stock.php" class="nav-link"><i class="bi bi-hammer"></i> Production</a></li>
         <li class="nav-item"><a href="production_ordered.php" class="nav-link active "><i class="bi bi-bell-fill"></i> Production Auto</a></li>
        <li class="nav-item"><a href="product_management.php" class="nav-link"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a href="ingredient_management.php" class="nav-link"><i class="bi bi-droplet"></i> Ingredients</a></li>
        <li class="nav-item"><a href="sales_reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link"><i class="bi bi-people"></i> Customers</a></li>
    </ul>
   
  </div>

  <!-- Main Content Area -->
  <main class="main-content">
    <header class="main-header">
      <h1 class="h3 mb-0 fw-bold text-dark">Production Depend Order</h1>
       <div class="user-menu">
        <span class="welcome-text d-none d-sm-inline">Welcome, <strong><?= $owner_name ?></strong>!</span>
        <a href="owner_profile.php" class="btn btn-outline-secondary me-2">
            <i class="bi bi-person-fill me-1"></i>Profile
        </a>
        <a href="logout.php" class="btn btn-outline-danger">
          <i class="bi bi-box-arrow-right me-1"></i>Logout
        </a>
      </div>
    </header>

    <div class="content-wrapper">
        <?php if (!empty($form_message)): ?>
            <div class="alert alert-<?= htmlspecialchars($form_message_type) ?> alert-dismissible fade show mb-4" role="alert">
                <?= $form_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="content-card">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-4">
                    <i class="bi bi-bell-fill text-warning me-2"></i>
                    Production Alerts
                    <?php if (!empty($products_needing_production)): ?>
                        <span class="badge badge-notification ms-2"><?= count($products_needing_production) ?> alerts</span>
                    <?php endif; ?>
                </h5>
                
                <?php if (empty($products_needing_production)): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        No production needed at this time. All products have sufficient stock for pending orders.
                    </div>
                <?php else: ?>
                    <div class="alert alert-info mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        The following products need production to fulfill pending customer orders.
                    </div>
                    
                    <?php foreach ($products_needing_production as $product): ?>
                        <div class="alert alert-notification alert-light mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold mb-0">
                                    <?= htmlspecialchars($product['product_name']) ?>
                                    <span class="badge bg-<?= $product['ingredients_sufficient'] ? 'success' : 'danger' ?>-subtle text-<?= $product['ingredients_sufficient'] ? 'success' : 'danger' ?>-emphasis ms-2">
                                        <?= $product['ingredients_sufficient'] ? 'Ready to Produce' : 'Ingredients Missing' ?>
                                    </span>
                                </h6>
                                <span class="text-muted">Product ID: <?= htmlspecialchars($product['product_id']) ?></span>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="small text-muted">Current Stock</div>
                                    <div class="fw-bold"><?= $product['current_stock'] ?> packs</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="small text-muted">Pending Orders</div>
                                    <div class="fw-bold"><?= $product['pending_orders'] ?> packs</div>
                                </div>
                                <div class="col-md-4">
                                    <div class="small text-muted">Recommended Production</div>
                                    <div class="fw-bold text-primary"><?= $product['needed_production'] ?> packs</div>
                                </div>
                            </div>
                            
                            <?php if (!$product['ingredients_sufficient']): ?>
                                <div class="alert alert-danger py-2 mb-3">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                        <div>
                                            <strong>Insufficient Ingredients:</strong>
                                            <?php foreach ($product['missing_ingredients'] as $ing): ?>
                                                <span class="d-inline-block me-2"><?= htmlspecialchars($ing['name']) ?> (need <?= round($ing['needed'], 2) ?> <?= htmlspecialchars($ing['unit']) ?>)</span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="production_ordered.php" class="mt-3">
                                <input type="hidden" name="product_id" value="<?= htmlspecialchars($product['product_id']) ?>">
                                <input type="hidden" name="quantity" value="<?= $product['needed_production'] ?>">
                                
                                <div class="d-flex justify-content-end">
                                    <?php if ($product['ingredients_sufficient']): ?>
                                        <button type="submit" name="produce_for_order" class="btn btn-primary">
                                            <i class="bi bi-hammer me-2"></i> Produce <?= $product['needed_production'] ?> Packs
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary" disabled>
                                            <i class="bi bi-slash-circle me-2"></i> Cannot Produce (Missing Ingredients)
                                        </button>
                                        <a href="ingredient_management.php" class="btn btn-outline-danger ms-2">
                                            <i class="bi bi-box-arrow-up-right me-2"></i> Manage Ingredients
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-refresh the page every 5 minutes to check for new notifications
setTimeout(function() {
    window.location.reload();
}, 300000); // 300000 ms = 5 minutes
</script>
</body>
</html>