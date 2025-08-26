<?php
session_start();
require 'db_connection.php';

// --- SECURITY CHECK & SESSION SETUP ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'seller') {
    header("Location: login.php");
    exit();
}

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

$product_id = $_GET['product_id'] ?? null;
if (!$product_id) {
    $_SESSION['message'] = "<div class='alert alert-danger'>No product selected.</div>";
    header("Location: product_management.php");
    exit();
}

// --- Fetch main product information ---
$product_stmt = $conn->prepare("SELECT * FROM products_sell WHERE PRODUCT_ID = ?");
$product_stmt->bind_param("s", $product_id);
$product_stmt->execute();
$product = $product_stmt->get_result()->fetch_assoc();
$product_stmt->close();

if (!$product) {
    $_SESSION['message'] = "<div class='alert alert-danger'>Product not found.</div>";
    header("Location: product_management.php");
    exit();
}
$pieces_per_pack = $product['PIECES_PER_PACK'] ?? 1;

// --- FORM HANDLING ---
// Add ingredient to recipe
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_ingredient'])) {
    $item_id = $_POST['item_id'];
    $quantity = $_POST['quantity'];
    $unit = $_POST['unit'];
    
    $check_stmt = $conn->prepare("SELECT PI_ID FROM product_ingredient WHERE PRODUCT_ID = ? AND ITEM_ID = ?");
    $check_stmt->bind_param("ss", $product_id, $item_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        $_SESSION['message'] = "<div class='alert alert-warning'>This ingredient is already in the recipe. Please edit it instead.</div>";
    } else {
        $insert_stmt = $conn->prepare("INSERT INTO product_ingredient (PRODUCT_ID, ITEM_ID, PI_QTTY_REQUIRED, PI_UNIT) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("ssds", $product_id, $item_id, $quantity, $unit);
        if ($insert_stmt->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>Ingredient added to recipe.</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Error: " . $insert_stmt->error . "</div>";
        }
        $insert_stmt->close();
    }
    $check_stmt->close();
    header("Location: manage_recipe.php?product_id=" . $product_id);
    exit();
}

// Remove ingredient from recipe
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['remove_ingredient'])) {
    $pi_id = $_POST['pi_id'];
    $stmt = $conn->prepare("DELETE FROM product_ingredient WHERE PI_ID = ? AND PRODUCT_ID = ?");
    $stmt->bind_param("is", $pi_id, $product_id);
    if ($stmt->execute()) { $_SESSION['message'] = "<div class='alert alert-success'>Ingredient removed from recipe.</div>"; }
    else { $_SESSION['message'] = "<div class='alert alert-danger'>Error removing ingredient.</div>"; }
    $stmt->close();
    header("Location: manage_recipe.php?product_id=" . $product_id);
    exit();
}

// Update ingredient quantity in recipe
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_ingredient'])) {
    $pi_id = $_POST['pi_id'];
    $quantity = $_POST['quantity'];
    $unit = $_POST['unit'];
    
    $stmt = $conn->prepare("UPDATE product_ingredient SET PI_QTTY_REQUIRED = ?, PI_UNIT = ? WHERE PI_ID = ?");
    $stmt->bind_param("dsi", $quantity, $unit, $pi_id);
    if ($stmt->execute()) { $_SESSION['message'] = "<div class='alert alert-success'>Ingredient quantity updated.</div>"; }
    else { $_SESSION['message'] = "<div class='alert alert-danger'>Error updating ingredient.</div>"; }
    $stmt->close();
    header("Location: manage_recipe.php?product_id=" . $product_id);
    exit();
}

// --- DATA FETCHING & LOGIC FOR DISPLAY ---
function calculateRequiredIngredients($conn, $product_id, $current_stock) {
    $orders_stmt = $conn->prepare("SELECT SUM(ORDER_QTTY) as total_quantity FROM customer_order WHERE PRODUCT_ID = ? AND ORDER_PAYMENT_STATUS = 'Paid' AND ORDER_STATUS NOT IN ('Completed', 'Cancelled')");
    $orders_stmt->bind_param("s", $product_id);
    $orders_stmt->execute();
    $total_ordered = $orders_stmt->get_result()->fetch_assoc()['total_quantity'] ?? 0;
    $orders_stmt->close();
    
    $needed_production = max(0, $total_ordered - $current_stock);
    
    $recipe_stmt = $conn->prepare("SELECT pi.*, ii.ITEM_NAME, ii.INVENTORY_STOCK, ii.INVENTORY_UNIT FROM product_ingredient pi JOIN item_ingredient ii ON pi.ITEM_ID = ii.ITEM_ID WHERE pi.PRODUCT_ID = ?");
    $recipe_stmt->bind_param("s", $product_id);
    $recipe_stmt->execute();
    $recipe_result = $recipe_stmt->get_result();
    $ingredients = [];
    
    while ($row = $recipe_result->fetch_assoc()) {
        $total_needed = $row['PI_QTTY_REQUIRED'] * $needed_production;
        $total_needed_in_inventory_unit = convert_unit($total_needed, $row['PI_UNIT'], $row['INVENTORY_UNIT']);
        $is_sufficient = ($row['INVENTORY_STOCK'] >= $total_needed_in_inventory_unit);
        $ingredients[] = array_merge($row, ['total_needed' => $total_needed, 'sufficient' => $is_sufficient]);
    }
    $recipe_stmt->close();
    
    return ['total_ordered' => $total_ordered, 'needed_production' => $needed_production, 'ingredients' => $ingredients];
}

$production_data = calculateRequiredIngredients($conn, $product_id, $product['PRODUCT_QTTY']);
$ingredients_for_dropdown = $conn->query("SELECT ITEM_ID, ITEM_NAME FROM item_ingredient ORDER BY ITEM_NAME");
$recipe_stmt = $conn->prepare("SELECT pi.*, ii.ITEM_NAME, ii.INVENTORY_UNIT FROM product_ingredient pi JOIN item_ingredient ii ON pi.ITEM_ID = ii.ITEM_ID WHERE pi.PRODUCT_ID = ? ORDER BY ii.ITEM_NAME");
$recipe_stmt->bind_param("s", $product_id);
$recipe_stmt->execute();
$recipe_result = $recipe_stmt->get_result();
$orders_stmt = $conn->prepare("SELECT co.*, c.CUST_NAME FROM customer_order co JOIN customer c ON co.CUST_ID = c.CUST_ID WHERE co.PRODUCT_ID = ? AND co.ORDER_PAYMENT_STATUS = 'Paid' AND co.ORDER_STATUS NOT IN ('Completed', 'Cancelled') ORDER BY co.ORDER_WANTED, co.ORDER_WANTED_TIME");
$orders_stmt->bind_param("s", $product_id);
$orders_stmt->execute();
$orders_result = $orders_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Tracker - <?= htmlspecialchars($product['PRODUCT_NAME']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --primary-dark: #4b1c1c; --primary-accent: #ffc107; }
        body { background-color: #f8f9fa; display: flex; min-height: 100vh; }
        .sidebar { width: 280px; background-color: var(--primary-dark); color: white; flex-shrink: 0; }
        .sidebar .nav-link { color: #e9ecef; padding: 0.8rem 1.5rem; font-size: 1.05rem; border-left: 4px solid transparent; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); color: white; }
        .sidebar .nav-link.active { border-left-color: var(--primary-accent); }
        .sidebar .nav-link .bi { margin-right: 0.75rem; }
        .sidebar-header { padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .main-content { flex-grow: 1; padding: 2.5rem; overflow-y: auto; }
        .nav-tabs .nav-link { color: var(--primary-dark); }
        .nav-tabs .nav-link.active { color: var(--primary-dark); background-color: #fff; border-color: #dee2e6 #dee2e6 #fff; font-weight: bold; }
        .stat-card .display-4 { font-weight: 700; }
    </style>
</head>
<body>
    <div class="sidebar d-flex flex-column p-0">
        <div class="sidebar-header text-center"><h4 class="fw-bold mb-1">RY's Tasty Creations</h4><p class="text-white-50 mb-0">Owner Panel</p></div>
        <ul class="nav flex-column my-4">
        <li class="nav-item"><a href="owner_dashboard.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="nav-item"><a href="view_orders.php" class="nav-link"><i class="bi bi-receipt"></i> Order Management</a></li>
        <li class="nav-item"><a href="produce_stock.php" class="nav-link"><i class="bi bi-hammer"></i> Production</a></li>
         <li class="nav-item"><a href="production_ordered.php" class="nav-link "><i class="bi bi-bell-fill"></i> Production Auto</a></li>
        <li class="nav-item"><a href="product_management.php" class="nav-link active"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a href="ingredient_management.php" class="nav-link"><i class="bi bi-droplet"></i> Ingredients</a></li>
        <li class="nav-item"><a href="sales_reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link"><i class="bi bi-people"></i> Customers</a></li>
        </ul>
        <div class="mt-auto p-3"><a href="logout.php" class="nav-link text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a></div>
    </div>
    <main class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h2 fw-bold">Production Tracker</h1>
                    <p class="text-muted">Planning and recipe for <strong class="text-primary"><?= htmlspecialchars($product['PRODUCT_NAME']) ?></strong> (1 Pack = <?= $pieces_per_pack ?> pcs)</p>
                </div>
                <a href="product_management.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to All Products</a>
            </div>
            <?= $message ?>
            <ul class="nav nav-tabs mb-4" id="myTab" role="tablist">
                <li class="nav-item" role="presentation"><button class="nav-link active" id="planning-tab" data-bs-toggle="tab" data-bs-target="#planning-tab-pane" type="button" role="tab"><i class="bi bi-clipboard-data-fill"></i> Production Planning</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" id="recipe-tab" data-bs-toggle="tab" data-bs-target="#recipe-tab-pane" type="button" role="tab"><i class="bi bi-card-checklist"></i> Recipe Editor</button></li>
                <li class="nav-item" role="presentation"><button class="nav-link" id="orders-tab" data-bs-toggle="tab" data-bs-target="#orders-tab-pane" type="button" role="tab"><i class="bi bi-list-check"></i> Pending Orders</button></li>
            </ul>
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="planning-tab-pane" role="tabpanel">
                    <div class="card border-0 shadow-sm"><div class="card-body p-4">
                        <div class="row mb-4 text-center">
                            <div class="col-md-4 stat-card"><h6 class="text-muted">Unfulfilled Orders</h6><div class="display-4 text-info"><?= $production_data['total_ordered'] ?></div><p>packs</p></div>
                            <div class="col-md-4 stat-card"><h6 class="text-muted">Current Stock</h6><div class="display-4 text-success"><?= $product['PRODUCT_QTTY'] ?></div><p>packs</p></div>
                            <div class="col-md-4 stat-card"><h6 class="text-muted">Needs Production</h6><div class="display-4 <?= $production_data['needed_production'] > 0 ? 'text-danger' : 'text-success' ?>"><?= $production_data['needed_production'] ?></div><p>packs</p></div>
                        </div><hr>
                        <?php if ($production_data['needed_production'] > 0): ?>
                            <h5 class="mt-4">Ingredient Requirements</h5><p class="text-muted">Calculations based on needing to produce <?= $production_data['needed_production'] ?> packs.</p>
                            <div class="table-responsive"><table class="table">
                                <thead class="table-light"><tr><th>Ingredient</th><th>Total Needed</th><th>Current Stock</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($production_data['ingredients'] as $ing): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($ing['ITEM_NAME']) ?></td>
                                        <td><strong><?= rtrim(rtrim(number_format($ing['total_needed'], 3), '0'), '.') ?></strong> <span class="text-muted"><?= htmlspecialchars($ing['PI_UNIT']) ?></span></td>
                                        <td><?= rtrim(rtrim(number_format($ing['INVENTORY_STOCK'], 3), '0'), '.') ?> <span class="text-muted"><?= htmlspecialchars($ing['INVENTORY_UNIT']) ?></span></td>
                                        <td><span class="badge <?= $ing['sufficient'] ? 'bg-success' : 'bg-danger' ?>"><?= $ing['sufficient'] ? 'Sufficient' : 'INSUFFICIENT' ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table></div>
                            <div class="text-end mt-3"><a href="produce_stock.php?product_id=<?= htmlspecialchars($product_id) ?>&needed=<?= $production_data['needed_production'] ?>" class="btn btn-primary"><i class="bi bi-hammer"></i> Go to Production Page</a></div>
                        <?php else: ?>
                            <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> No production is immediately required. Current stock covers all pending paid orders.</div>
                        <?php endif; ?>
                    </div></div>
                </div>
                <div class="tab-pane fade" id="recipe-tab-pane" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white"><h5 class="mb-0">Current Recipe (Ingredients per Pack of <?= $pieces_per_pack ?>)</h5></div>
                                <div class="card-body">
                                    <?php if ($recipe_result->num_rows > 0): $recipe_result->data_seek(0); ?>
                                        <table class="table align-middle">
                                            <tbody>
                                            <?php while ($row = $recipe_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($row['ITEM_NAME']) ?></strong></td>
                                                <td style="width: 50%;">
                                                    <form method="POST" class="d-flex gap-2">
                                                        <input type="hidden" name="pi_id" value="<?= $row['PI_ID'] ?>">
                                                        <input type="number" step="any" name="quantity" class="form-control form-control-sm" value="<?= $row['PI_QTTY_REQUIRED'] ?>" required>
                                                        <input type="text" name="unit" class="form-control form-control-sm" value="<?= htmlspecialchars($row['PI_UNIT']) ?>" required>
                                                        <button type="submit" name="update_ingredient" class="btn btn-sm btn-outline-primary"><i class="bi bi-check-lg"></i></button>
                                                    </form>
                                                </td>
                                                <td>
                                                    <form method="POST" onsubmit="return confirm('Remove this ingredient?');">
                                                        <input type="hidden" name="pi_id" value="<?= $row['PI_ID'] ?>">
                                                        <button type="submit" name="remove_ingredient" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <p class="text-center text-muted">No ingredients added yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white"><h5 class="mb-0">Add Ingredient to Recipe</h5></div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3"><label class="form-label">Ingredient</label><select name="item_id" id="item_id" class="form-select" required><option value="">Select...</option><?php if($ingredients_for_dropdown->num_rows>0){$ingredients_for_dropdown->data_seek(0);while($row = $ingredients_for_dropdown->fetch_assoc()){echo "<option value='".htmlspecialchars($row['ITEM_ID'])."'>".htmlspecialchars($row['ITEM_NAME'])."</option>";}}?></select></div>
                                        <div class="mb-3"><label class="form-label">Quantity for 1 Pack</label><input type="number" step="any" name="quantity" class="form-control" required></div>
                                        <div class="mb-3"><label class="form-label">Unit</label><select name="unit" id="unit" class="form-select" required disabled><option value="">-- Select ingredient first --</option></select></div>
                                        <div class="d-grid"><button type="submit" name="add_ingredient" class="btn btn-primary">Add to Recipe</button></div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="orders-tab-pane" role="tabpanel">
                    <div class="card border-0 shadow-sm"><div class="card-body">
                        <?php if ($orders_result->num_rows > 0): ?>
                            <div class="table-responsive"><table class="table table-hover">
                                <thead><tr><th>Order ID</th><th>Customer</th><th>Quantity</th><th>Date Wanted</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php while ($order = $orders_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>#<?= $order['ORDER_ID'] ?></td><td><?= htmlspecialchars($order['CUST_NAME']) ?></td><td><?= $order['ORDER_QTTY'] ?> packs</td>
                                        <td><?= date('d M Y, g:ia', strtotime($order['ORDER_WANTED'] . ' ' . $order['ORDER_WANTED_TIME'])) ?></td>
                                        <td><span class="badge bg-warning text-dark rounded-pill"><?= htmlspecialchars($order['ORDER_STATUS']) ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table></div>
                        <?php else: ?>
                            <div class="alert alert-light">No pending paid orders for this product.</div>
                        <?php endif; ?>
                    </div></div>
                </div>
            </div>
        </div>
    </main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const ingredientSelect = document.getElementById('item_id');
    const unitSelect = document.getElementById('unit');
    if (ingredientSelect) {
        ingredientSelect.addEventListener('change', function() {
            const itemId = this.value;
            unitSelect.innerHTML = '<option value="">Loading...</option>';
            unitSelect.disabled = true;
            if (!itemId) { unitSelect.innerHTML = '<option value="">-- Select ingredient first --</option>'; return; }
            fetch(`ajax_get_ingredient_units.php?item_id=${itemId}`)
                .then(response => response.ok ? response.json() : Promise.reject('Network error'))
                .then(data => {
                    unitSelect.innerHTML = '';
                    if (data.error) { unitSelect.innerHTML = `<option value="">Error</option>`;
                    } else {
                        if(data.inventory_unit) unitSelect.add(new Option(data.inventory_unit, data.inventory_unit));
                        if (data.breakdown_unit && data.inventory_unit !== data.breakdown_unit) {
                            unitSelect.add(new Option(data.breakdown_unit, data.breakdown_unit));
                        }
                        unitSelect.disabled = false;
                    }
                }).catch(error => { unitSelect.innerHTML = '<option value="">Error</option>'; });
        });
    }
});
</script>
</body>
</html>