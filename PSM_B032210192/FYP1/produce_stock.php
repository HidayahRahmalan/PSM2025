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

// Form submission processing
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['produce_stock'])) {
    // Get and validate form data
    $product_id = $_POST['product_id'] ?? '';
    $quantity_produced = isset($_POST['quantity_to_produce']) ? (int)$_POST['quantity_to_produce'] : 0;
    $expiry_date = $_POST['expiry_date'] ?? '';

    // Server-side validation
    if (empty($product_id) || $quantity_produced <= 0 || empty($expiry_date)) {
        $_SESSION['form_message'] = "Validation failed. All fields are required.";
        $_SESSION['form_message_type'] = 'danger';
        header("Location: produce_stock.php");
        exit();
    }

    // Check for sufficient ingredients
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
    
    if ($recipe_result->num_rows === 0) {
        $_SESSION['form_message'] = "Production failed: This product has no recipe defined.";
        header("Location: produce_stock.php");
        exit();
    }
    
    $sufficient_ingredients = true;
    $ingredient_usage_list = [];
    $missing_ingredients = [];
    
    while ($ing = $recipe_result->fetch_assoc()) {
        $required_per_pack = convert_to_base_unit($ing['PI_QTTY_REQUIRED'], $ing['PI_UNIT']);
        $total_needed = $required_per_pack * $quantity_produced;
        $available_stock = convert_to_base_unit($ing['INVENTORY_STOCK'], $ing['INVENTORY_UNIT']);
        
        if ($available_stock < $total_needed) {
            $sufficient_ingredients = false;
            $missing_ingredients[] = $ing['ITEM_NAME'];
        }
        $ingredient_usage_list[] = [
            'item_id' => $ing['ITEM_ID'],
            'total_used' => $total_needed
        ];
    }
    $recipe_stmt->close();
    
    if ($sufficient_ingredients) {
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
                // We need to convert the amount to deduct back to the inventory's original unit
                // For simplicity here, assuming all operations are in base units and INVENTORY_STOCK is also in base units (g/ml)
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
            $update_prod_stmt->bind_param("is", $quantity_produced, $product_id);
            $update_prod_stmt->execute();
            $update_prod_stmt->close();
            
            // 3. Log the production event
            $insert_log_stmt = $conn->prepare("
                INSERT INTO product_produce 
                (PRODUCT_ID, PP_DATE, PP_EXPIRED, PP_QTTY, PP_QTTY_WANTED, PP_QTTY_REMAINING) 
                VALUES (?, CURDATE(), ?, ?, ?, ?)
            ");
            $insert_log_stmt->bind_param("ssiii", $product_id, $expiry_date, $quantity_produced, $quantity_produced, $quantity_produced);
            $insert_log_stmt->execute();
            $insert_log_stmt->close();

            $conn->commit();
            $_SESSION['form_message'] = "<strong>Success!</strong> Produced $quantity_produced pack(s). Stock updated.";
            $_SESSION['form_message_type'] = 'success';

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Production Transaction Failed: " . $e->getMessage()); 
            $_SESSION['form_message'] = "Database error: " . $e->getMessage();
        }
    } else {
        $missing_list = implode(", ", $missing_ingredients);
        $_SESSION['form_message'] = "Insufficient stock for: $missing_list";
    }
    header("Location: produce_stock.php");
    exit();
}

// Data fetching for page display
$products_result = $conn->query("SELECT PRODUCT_ID, PRODUCT_NAME FROM products_sell ORDER BY PRODUCT_NAME");
$production_history = $conn->query("
    SELECT p.PRODUCT_NAME, pp.PP_DATE, pp.PP_QTTY, pp.PP_EXPIRED 
    FROM product_produce pp 
    JOIN products_sell p ON pp.PRODUCT_ID = p.PRODUCT_ID 
    ORDER BY pp.PP_ID DESC LIMIT 10
");
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stock Production - RY's Tasty Creations</title>
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
    #produce-button:disabled { background-color: #6c757d !important; border-color: #6c757d !important; cursor: not-allowed; }
    .ingredient-sufficient { color: #198754; }
    .ingredient-insufficient { color: #dc3545; }
    .stock-bar { height: 8px; border-radius: 4px; }
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
        <li class="nav-item"><a href="produce_stock.php" class="nav-link active"><i class="bi bi-hammer"></i> Production</a></li>
         <li class="nav-item"><a href="production_ordered.php" class="nav-link "><i class="bi bi-bell-fill"></i> Production Auto</a></li>
        <li class="nav-item"><a href="product_management.php" class="nav-link"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a href="ingredient_management.php" class="nav-link"><i class="bi bi-droplet"></i> Ingredients</a></li>
        <li class="nav-item"><a href="sales_reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link"><i class="bi bi-people"></i> Customers</a></li>
    </ul>
    <div class="mt-auto p-3"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
  </div>

  <!-- Main Content Area -->
  <main class="main-content">
    <header class="main-header">
      <h1 class="h3 mb-0 fw-bold text-dark">Stock Production</h1>
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
            <div class="alert alert-<?= htmlspecialchars($form_message_type) ?> alert-dismissible fade show" role="alert">
                <?= $form_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row g-4">
            <div class="col-lg-7">
                <div class="content-card h-100">
                    <div class="card-body p-4 p-md-5">
                        <form id="produce-form" method="POST" action="produce_stock.php">
                            <h4 class="card-title fw-bold mb-4">Create New Batch</h4>
                            
                            <div class="mb-4">
                                <label for="product_id" class="form-label fs-5">1. Select Product</label>
                                <select name="product_id" id="product_id" class="form-select form-select-lg" required>
                                    <option value="" disabled selected>-- Choose a product to see its recipe --</option>
                                    <?php while($row = $products_result->fetch_assoc()): ?>
                                        <option value="<?= htmlspecialchars($row['PRODUCT_ID']) ?>"><?= htmlspecialchars($row['PRODUCT_NAME']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="quantity_to_produce" class="form-label fs-5">2. Set Quantity to Produce</label>
                                <input type="number" name="quantity_to_produce" id="quantity_to_produce" class="form-control form-control-lg" min="1" placeholder="Number of packs" required>
                            </div>

                            <div class="mb-4">
                                <label for="expiry_date" class="form-label fs-5">3. Set Expiry Date</label>
                                <input type="date" name="expiry_date" id="expiry_date" class="form-control form-control-lg" required min="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" name="produce_stock" id="produce-button" class="btn btn-lg w-100" disabled style="background-color: var(--primary-dark); border-color: var(--primary-dark); color:white;">
                                    <i class="bi bi-hammer me-2"></i> Confirm & Produce Stock
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Ingredient Status -->
            <div class="col-lg-5">
                 <div class="content-card h-100">
                    <div class="card-body p-4">
                        <h5 class="card-title fw-bold mb-3"><i class="bi bi-clipboard-check-fill text-muted me-2"></i>Ingredient Status</h5>
                        <div id="ingredient-status-panel" class="p-3 bg-light rounded" style="min-height: 200px;">
                            <div class="text-center text-muted pt-5">
                                <i class="bi bi-card-list fs-1"></i>
                                <p>Select a product to view recipe and stock levels.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Section: Production History -->
        <div class="content-card mt-4">
            <div class="card-body p-4">
                <h5 class="card-title fw-bold mb-3">Recent Production Batches</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Product Name</th><th>Production Date</th><th>Quantity Produced</th><th>Expiry Date</th></tr>
                        </thead>
                        <tbody>
                            <?php if($production_history->num_rows > 0): ?>
                                <?php $production_history->data_seek(0); // Reset pointer for display ?>
                                <?php while($row = $production_history->fetch_assoc()): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= htmlspecialchars($row['PRODUCT_NAME']) ?></td>
                                        <td><?= date('F j, Y', strtotime($row['PP_DATE'])) ?></td>
                                        <td><?= $row['PP_QTTY'] ?> packs</td>
                                        <td><?= date('F j, Y', strtotime($row['PP_EXPIRED'])) ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="4" class="text-center p-4 text-muted">No production records found yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
  </main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity_to_produce');
    const expiryInput = document.getElementById('expiry_date');
    const produceButton = document.getElementById('produce-button');
    const statusPanel = document.getElementById('ingredient-status-panel');
    let debounceTimer;

    function checkFormValidity() {
        const productId = productSelect.value;
        const quantity = parseInt(quantityInput.value) || 0;
        const expiryDate = expiryInput.value;
        return productId && quantity > 0 && expiryDate;
    }

    function updateIngredientStatus() {
        const productId = productSelect.value;
        const quantity = parseInt(quantityInput.value) || 0;
        produceButton.disabled = true;

        if (!productId) {
            statusPanel.innerHTML = `<div class="text-center text-muted pt-5"><i class="bi bi-card-list fs-1"></i><p>Select a product to view recipe.</p></div>`;
            return;
        }

        if (quantity <= 0) {
            statusPanel.innerHTML = `<div class="text-center text-muted pt-5"><i class="bi bi-123 fs-1"></i><p>Please enter a quantity to produce.</p></div>`;
            return;
        }

        statusPanel.innerHTML = `<div class="text-center text-muted pt-5"><div class="spinner-border text-secondary" role="status"></div><p class="mt-2">Loading Recipe...</p></div>`;

        const formData = new FormData();
        formData.append('product_id', productId);
        formData.append('quantity_to_produce', quantity);

        fetch('ajax_check_producible.php', { method: 'POST', body: formData })
        .then(response => {
            if (!response.ok) { throw new Error('Network response was not ok'); }
            return response.json();
        })
        .then(data => {
            if (data.recipe) {
                let html = '<ul class="list-group list-group-flush">';
                let allSufficient = true;
                let anyInsufficient = false; // <-- NEW: Flag to check for deficits
                
                data.recipe.forEach(item => {
                    if (!item.is_sufficient) {
                        allSufficient = false;
                        anyInsufficient = true; // <-- NEW: Set flag if any item is insufficient
                    }
                    
                    const statusIcon = item.is_sufficient 
                        ? '<i class="bi bi-check-circle-fill ingredient-sufficient"></i>' 
                        : '<i class="bi bi-x-circle-fill ingredient-insufficient"></i>';
                    
                    const percentage = Math.min(100, (item.stock_available / (item.needed_total || 1)) * 100);
                    
                    html += `
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="fw-bold">${item.name}</span>
                            <div>${statusIcon}</div>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mb-1">
                            <span>Required: ${item.required_per_pack} ${item.required_unit}/pack</span>
                            <span>Available: ${parseFloat(item.stock_available).toFixed(2)} ${item.stock_unit}</span>
                        </div>
                        <div class="progress mb-1" style="height: 8px;">
                            <div class="progress-bar ${item.is_sufficient ? 'bg-success' : 'bg-danger'}" 
                                 role="progressbar" 
                                 style="width: ${percentage}%" 
                                 aria-valuenow="${percentage}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100">
                            </div>
                        </div>
                        ${!item.is_sufficient ? `
                        <div class="text-end small text-danger">
                            Need additional ${parseFloat(item.deficit).toFixed(2)} ${item.stock_unit}
                        </div>` : ''}
                    </li>`;
                });
                
                html += '</ul>';

                // --- NEW CODE BLOCK: Add the print button if needed ---
                if (anyInsufficient) {
                    const printUrl = `shopping_list.php?product_id=${encodeURIComponent(productId)}&quantity_to_produce=${encodeURIComponent(quantity)}`;
                    html += `
                        <div class="mt-3 p-2 text-center border-top">
                            <a href="${printUrl}" target="_blank" class="btn btn-outline-danger w-100">
                                <i class="bi bi-printer me-2"></i> Print/Save Shopping List
                            </a>
                        </div>
                    `;
                }
                // --- END NEW CODE BLOCK ---

                statusPanel.innerHTML = html;
                
                if (checkFormValidity() && allSufficient) {
                    produceButton.disabled = false;
                }
            } else {
                statusPanel.innerHTML = `<div class="alert alert-warning text-center">${data.message || 'Could not retrieve recipe.'}</div>`;
            }
        })
        .catch(error => {
            statusPanel.innerHTML = `<div class="alert alert-danger text-center">Error loading ingredient status. Please try again.</div>`;
            console.error('Fetch Error:', error);
        });
    }
    
    function debounceUpdate() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(updateIngredientStatus, 400);
    }

    productSelect.addEventListener('change', updateIngredientStatus);
    quantityInput.addEventListener('input', debounceUpdate);
    expiryInput.addEventListener('change', function() {
        debounceUpdate();
    });
});
</script>
</body>
</html>