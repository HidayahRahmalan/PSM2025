<?php
session_start();
require 'db_connection.php'; // Make sure your db_connection.php is included

// --- SECURITY CHECK & SESSION SETUP ---
if (!isset($_SESSION['seller_id'])) { // More robust to check for a specific ID
    $_SESSION['error'] = "You must be logged in as an owner to access this page.";
    header("Location: login.php");
    exit();
}
$owner_name = isset($_SESSION['seller_name']) ? htmlspecialchars($_SESSION['seller_name']) : 'Owner';

$message = '';
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}

// --- HELPER FUNCTION TO GENERATE NEW ITEM ID ---
function generateNewItemID($conn) {
    $result = $conn->query("SELECT ITEM_ID FROM item_ingredient ORDER BY ITEM_ID DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['ITEM_ID'];
        $num_part = (int)substr($last_id, 1);
        return 'I' . str_pad($num_part + 1, 3, '0', STR_PAD_LEFT);
    }
    return 'I001'; // First ever ingredient
}

// --- REUSABLE HELPER FUNCTION FOR STOCK CALCULATION ---
function calculateStockAndPrice($purchase_unit_desc, $inventory_unit, $purchase_qtty, $unit_price) {
    preg_match('/(\d+\.?\d*)\s*(kg|g|l|ml|pcs)/i', $purchase_unit_desc, $matches);
    $value_per_pack = $matches[1] ?? 1;
    $unit_of_pack = strtolower($matches[2] ?? $inventory_unit);

    $stock_per_pack = $value_per_pack;
    if ($inventory_unit == 'g' && $unit_of_pack == 'kg') $stock_per_pack *= 1000;
    if ($inventory_unit == 'ml' && $unit_of_pack == 'l') $stock_per_pack *= 1000;

    $total_stock = $stock_per_pack * $purchase_qtty;
    
    $price_per_kg = 0;
    if ($unit_price > 0 && $total_stock > 0 && ($inventory_unit == 'g')) {
        $total_kg_purchased = $total_stock / 1000;
        if ($total_kg_purchased > 0) {
            $price_per_kg = ($unit_price * $purchase_qtty) / $total_kg_purchased;
        }
    }
    
    return ['total_stock' => $total_stock, 'price_per_kg' => $price_per_kg];
}


// --- FORM HANDLING LOGIC ---

// ADD NEW INGREDIENT PURCHASE
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_ingredient'])) {
    $new_item_id = generateNewItemID($conn);
    $item_name = $_POST['item_name'];
    $inventory_unit = $_POST['inventory_unit'];
    $buy_date = $_POST['buy_date'];
    $expire_date = $_POST['expire_date'];
    $purchase_unit_desc = $_POST['purchase_unit_desc'];
    $purchase_qtty = (float)$_POST['purchase_qtty'];
    $unit_price = (float)$_POST['unit_price'];
    $brand = $_POST['brand'];
    
    $stock_data = calculateStockAndPrice($purchase_unit_desc, $inventory_unit, $purchase_qtty, $unit_price);
    $initial_stock = $stock_data['total_stock'];
    $price_per_kg = $stock_data['price_per_kg'];
    
    $image_data = NULL;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
        $image_data = file_get_contents($_FILES['item_image']['tmp_name']);
    }

    $stmt = $conn->prepare(
        "INSERT INTO item_ingredient (ITEM_ID, ITEM_NAME, INVENTORY_UNIT, ITEM_BUY_DATE, ITEM_EXPIRED_DATE, ITEM_PURCHASE_UNIT, ITEM_TOTAL_QTTY, ITEM_UNIT_PRICE, ITEM_PRICE_KG, ITEM_BRAND, ITEM_IMAGE, INVENTORY_STOCK, ITEM_USED) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0.000)"
    );
    $stmt->bind_param("ssssssdssdbs", $new_item_id, $item_name, $inventory_unit, $buy_date, $expire_date, $purchase_unit_desc, $purchase_qtty, $unit_price, $price_per_kg, $brand, $image_data, $initial_stock);
    
    if ($image_data !== NULL) $stmt->send_long_data(10, $image_data);
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>New ingredient purchase recorded successfully.</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
    header("Location: ingredient_management.php");
    exit();
}

// UPDATE INGREDIENT DETAILS (from Modal)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_ingredient'])) {
    $item_id = $_POST['edit_item_id'];
    $item_name = $_POST['edit_item_name'];
    $brand = $_POST['edit_brand'];
    $buy_date = $_POST['edit_buy_date'];
    $expire_date = $_POST['edit_expire_date'];
    $inventory_unit = $_POST['edit_inventory_unit'];
    $purchase_unit_desc = $_POST['edit_purchase_unit_desc'];
    $purchase_qtty = (float)$_POST['edit_purchase_qtty'];
    $unit_price = (float)$_POST['edit_unit_price'];
    
    $used_stmt = $conn->prepare("SELECT ITEM_USED FROM item_ingredient WHERE ITEM_ID = ?");
    $used_stmt->bind_param("s", $item_id);
    $used_stmt->execute();
    $current_used = $used_stmt->get_result()->fetch_assoc()['ITEM_USED'];
    $used_stmt->close();

    $stock_data = calculateStockAndPrice($purchase_unit_desc, $inventory_unit, $purchase_qtty, $unit_price);
    $recalculated_total_stock = $stock_data['total_stock'];
    $price_per_kg = $stock_data['price_per_kg'];
    
    $new_inventory_stock = $recalculated_total_stock - $current_used;
    
    if ($new_inventory_stock < 0) {
        $_SESSION['message'] = "<div class='alert alert-danger'>Update failed. The new total quantity is less than the quantity already used in recipes.</div>";
        header("Location: ingredient_management.php");
        exit();
    }
    
    $image_data = NULL;
    $image_sql_part = "";
    $types = "ssssssddsd"; 
    $params = [
        $item_name, $brand, $buy_date, $expire_date, $inventory_unit,
        $purchase_unit_desc, $purchase_qtty, $unit_price,
        $price_per_kg, $new_inventory_stock
    ];
    
    if (isset($_FILES['edit_item_image']) && $_FILES['edit_item_image']['error'] == 0) {
        $image_data = file_get_contents($_FILES['edit_item_image']['tmp_name']);
        $image_sql_part = ", ITEM_IMAGE = ?";
        $types .= "b";
        $params[] = $image_data;
    }
    
    $types .= "s";
    $params[] = $item_id;

    $sql = "UPDATE item_ingredient SET 
                ITEM_NAME = ?, ITEM_BRAND = ?, ITEM_BUY_DATE = ?, ITEM_EXPIRED_DATE = ?,
                INVENTORY_UNIT = ?, ITEM_PURCHASE_UNIT = ?, ITEM_TOTAL_QTTY = ?, ITEM_UNIT_PRICE = ?,
                ITEM_PRICE_KG = ?, INVENTORY_STOCK = ?
                $image_sql_part
            WHERE ITEM_ID = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($image_data !== NULL) {
        $stmt->send_long_data(10, $image_data);
    }
    
    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>Ingredient details updated successfully.</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Error updating details: " . $stmt->error . "</div>";
    }
    $stmt->close();
    header("Location: ingredient_management.php");
    exit();
}


// DELETE INGREDIENT BATCH
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_ingredient'])) {
    $item_id_to_delete = $_POST['delete_item_id'];
    
    $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM product_ingredient WHERE ITEM_ID = ?");
    $check_stmt->bind_param("s", $item_id_to_delete);
    $check_stmt->execute();
    $is_in_recipe = $check_stmt->get_result()->fetch_assoc()['count'] > 0;
    $check_stmt->close();

    if ($is_in_recipe) {
        $_SESSION['message'] = "<div class='alert alert-danger'>Cannot delete. This ingredient is part of a recipe. Remove it from all recipes first.</div>";
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM item_ingredient WHERE ITEM_ID = ?");
        $delete_stmt->bind_param("s", $item_id_to_delete);
        if ($delete_stmt->execute()) {
            $_SESSION['message'] = "<div class='alert alert-success'>Ingredient batch deleted successfully.</div>";
        } else {
            $_SESSION['message'] = "<div class='alert alert-danger'>Error deleting ingredient batch.</div>";
        }
        $delete_stmt->close();
    }
    header("Location: ingredient_management.php");
    exit();
}


// --- DATA FETCHING FOR DISPLAY ---
$ingredients = $conn->query("SELECT * FROM item_ingredient ORDER BY ITEM_NAME ASC, ITEM_EXPIRED_DATE ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ingredient Management - RY's Tasty Creations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    :root { --primary-dark: #4b1c1c; --primary-accent: #ffc107; --border-color: #dee2e6; }
    body { background-color: #f4f7f6; display: flex; min-height: 100vh; font-family: 'Segoe UI', sans-serif; }
    .sidebar { width: 280px; background-color: var(--primary-dark); color: white; flex-shrink: 0; }
    .sidebar .nav-link { color: #e9ecef; padding: 0.8rem 1.5rem; font-size: 1.05rem; border-left: 4px solid transparent; transition: background-color 0.2s, color 0.2s; }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: rgba(255, 255, 255, 0.1); color: white; }
    .sidebar .nav-link.active { border-left-color: var(--primary-accent); font-weight: 600; }
    .sidebar .nav-link .bi { margin-right: 0.8rem; font-size: 1.2rem; vertical-align: middle; }
    .sidebar-header { padding: 1.5rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
    .main-content { flex-grow: 1; padding: 0; }
    .main-header { background-color: #fff; padding: 1rem 2.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .user-menu { display: flex; align-items: center; }
    .user-menu .welcome-text { margin-right: 1rem; color: #6c757d; }
    .content-wrapper { padding: 2.5rem; overflow-y: auto; }
    .form-card, .table-card { background: white; border-radius: 0.75rem; box-shadow: 0 5px 20px rgba(0,0,0,0.05); }
    .expiring-soon { background-color: #fff3cd !important; }
    .expired { background-color: #f8d7da !important; font-weight: bold; }
  </style>
</head>
<body>
  <!-- Sidebar Navigation -->
  <div class="sidebar d-flex flex-column p-0">
    <div class="sidebar-header text-center"><h4 class="fw-bold mb-1">RY's Tasty Creations</h4><p class="text-white-50 mb-0">Owner Panel</p></div>
    <ul class="nav flex-column my-4 flex-grow-1">
<li class="nav-item"><a href="owner_dashboard.php" class="nav-link "><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="nav-item"><a href="view_orders.php" class="nav-link"><i class="bi bi-receipt"></i> Order Management</a></li>
        <li class="nav-item"><a href="produce_stock.php" class="nav-link"><i class="bi bi-hammer"></i> Production</a></li>
         <li class="nav-item"><a href="production_ordered.php" class="nav-link "><i class="bi bi-bell-fill"></i> Production Auto</a></li>
        <li class="nav-item"><a href="product_management.php" class="nav-link"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a href="ingredient_management.php" class="nav-link active"><i class="bi bi-droplet"></i> Ingredients</a></li>
        <li class="nav-item"><a href="sales_reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link"><i class="bi bi-people"></i> Customers</a></li>
    </ul>
  </div>
  
  <!-- Main Content -->
  <main class="main-content d-flex flex-column">

    <!-- Added Top Header Bar for consistency -->
    <header class="main-header">
      <h1 class="h3 mb-0 fw-bold text-dark">Ingredient Management</h1>
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

    <!-- Content Wrapper for scrolling and padding -->
    <div class="content-wrapper">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <p class="text-muted mb-0">Record new ingredient purchases and manage your current inventory.</p>
            <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#add-ingredient-form"><i class="bi bi-plus-circle-fill me-2"></i> Add New Purchase</button>
        </div>
        <?= $message ?>
        
        <div class="collapse" id="add-ingredient-form">
            <div class="form-card p-4 mb-4">
                <h5 class="mb-3">New Ingredient Purchase Details</h5>
                <form method="POST" action="ingredient_management.php" enctype="multipart/form-data">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Ingredient Name</label><input type="text" name="item_name" class="form-control" required placeholder="e.g., Tepung Gandum"></div>
                        <div class="col-md-6"><label class="form-label">Brand</label><input type="text" name="brand" class="form-control" placeholder="e.g., Cap Sauh"></div>
                        <div class="col-md-6"><label class="form-label">Purchase Date</label><input type="date" name="buy_date" class="form-control" required value="<?= date('Y-m-d') ?>"></div>
                        <div class="col-md-6"><label class="form-label">Expiry Date</label><input type="date" name="expire_date" class="form-control" required></div>
                        <div class="col-md-4"><label class="form-label">Base Inventory Unit</label><select name="inventory_unit" class="form-select" required><option value="g">g (grams)</option><option value="ml">ml (milliliters)</option><option value="pcs">pcs (pieces)</option></select></div>
                        <div class="col-md-4"><label class="form-label">Purchase Unit Description</label><input type="text" name="purchase_unit_desc" class="form-control" placeholder="e.g., Bag 1kg, Bottle 500ml" required></div>
                        <div class="col-md-4"><label class="form-label">Quantity of Packs Purchased</label><input type="number" step="1" name="purchase_qtty" class="form-control" placeholder="e.g., 2 (for 2 bags)" required></div>
                        <div class="col-md-4"><label class="form-label">Price Per Pack (RM)</label><input type="number" step="0.01" name="unit_price" class="form-control" placeholder="Price for one pack" required></div>
                        <div class="col-md-8"><label class="form-label">Image (Optional)</label><input type="file" name="item_image" class="form-control" accept="image/*"></div>
                        <div class="col-12 text-end"><button type="submit" name="add_ingredient" class="btn btn-primary" style="background-color: var(--primary-dark); border-color: var(--primary-dark);">Save Purchase</button></div>
                    </div>
                </form>
            </div>
        </div>

        <div class="table-card p-4">
            <h5 class="mb-3">Current Inventory</h5>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>Image</th><th>ID</th><th>Ingredient</th><th>In Stock</th><th>Used</th><th>Expires</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if ($ingredients && $ingredients->num_rows > 0): ?>
                        <?php while($item = $ingredients->fetch_assoc()): 
                            $expiry_date = new DateTime($item['ITEM_EXPIRED_DATE']);
                            $today = new DateTime();
                            $interval = $today->diff($expiry_date);
                            $days_left = (int)$interval->format('%r%a');
                            $row_class = '';
                            if ($days_left < 0) $row_class = 'expired';
                            elseif ($days_left <= 14) $row_class = 'expiring-soon';
                            $img_src = !empty($item['ITEM_IMAGE']) ? "data:image/jpeg;base64," . base64_encode($item['ITEM_IMAGE']) : '';
                        ?>
                        <tr class="<?= $row_class ?>">
                            <td>
                                <?php if ($img_src): ?>
                                    <img src="<?= $img_src ?>" width="50" height="50" style="object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; border-radius: 4px;"><i class="bi bi-droplet text-muted"></i></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['ITEM_ID']) ?></td>
                            <td><strong><?= htmlspecialchars($item['ITEM_NAME']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($item['ITEM_BRAND']) ?></small></td>
                            <td><span class="fw-bold fs-5"><?= number_format($item['INVENTORY_STOCK']) ?></span> <?= htmlspecialchars($item['INVENTORY_UNIT']) ?></td>
                            <td><span class="text-muted"><?= number_format($item['ITEM_USED']) ?></span> <?= htmlspecialchars($item['INVENTORY_UNIT']) ?></td>
                            <td>
                                <?= $expiry_date->format('d M Y') ?>
                                <?php if($days_left >= 0 && $days_left <= 7): ?><br><span class="badge bg-danger">Expires in <?= $days_left ?> days</span><?php endif; ?>
                                <?php if($days_left < 0): ?><br><span class="badge bg-dark">Expired!</span><?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group" role="group">
                                    <a href="view_ingredient_use.php?item_id=<?= htmlspecialchars($item['ITEM_ID']) ?>" class="btn btn-sm btn-info" title="Recipe Usage"><i class="bi bi-card-checklist"></i></a>
                                    <button type="button" class="btn btn-sm btn-warning edit-btn" data-bs-toggle="modal" data-bs-target="#editIngredientModal"
                                            data-id="<?= htmlspecialchars($item['ITEM_ID']) ?>" 
                                            data-name="<?= htmlspecialchars($item['ITEM_NAME']) ?>"
                                            data-brand="<?= htmlspecialchars($item['ITEM_BRAND']) ?>" 
                                            data-buy-date="<?= htmlspecialchars($item['ITEM_BUY_DATE']) ?>"
                                            data-expire-date="<?= htmlspecialchars($item['ITEM_EXPIRED_DATE']) ?>"
                                            data-inventory-unit="<?= htmlspecialchars($item['INVENTORY_UNIT']) ?>"
                                            data-purchase-unit-desc="<?= htmlspecialchars($item['ITEM_PURCHASE_UNIT']) ?>"
                                            data-purchase-qtty="<?= htmlspecialchars($item['ITEM_TOTAL_QTTY']) ?>"
                                            data-unit-price="<?= htmlspecialchars($item['ITEM_UNIT_PRICE']) ?>"
                                            data-image-src="<?= $img_src ?>"
                                            title="Edit Details">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <form method="POST" action="ingredient_management.php" class="d-inline" onsubmit="return confirm('Delete this ingredient batch? This cannot be undone.');">
                                        <input type="hidden" name="delete_item_id" value="<?= htmlspecialchars($item['ITEM_ID']) ?>">
                                        <button type="submit" name="delete_ingredient" class="btn btn-sm btn-danger" title="Delete"><i class="bi bi-trash-fill"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="text-center p-4">No ingredients found. Add a purchase to get started.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </main>

<!-- Edit Ingredient Modal -->
<div class="modal fade" id="editIngredientModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">Edit Ingredient Purchase</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <form method="POST" action="ingredient_management.php" enctype="multipart/form-data">
        <div class="modal-body">
            <input type="hidden" name="edit_item_id" id="edit_item_id">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">Ingredient Name</label><input type="text" name="edit_item_name" id="edit_item_name" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Brand</label><input type="text" name="edit_brand" id="edit_brand" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">Purchase Date</label><input type="date" name="edit_buy_date" id="edit_buy_date" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">Expiry Date</label><input type="date" name="edit_expire_date" id="edit_expire_date" class="form-control" required></div>
                <hr class="my-3">
                <div class="col-md-4"><label class="form-label">Base Inventory Unit</label><select name="edit_inventory_unit" id="edit_inventory_unit" class="form-select" required><option value="g">g (grams)</option><option value="ml">ml (milliliters)</option><option value="pcs">pcs (pieces)</option></select></div>
                <div class="col-md-4"><label class="form-label">Purchase Unit Description</label><input type="text" name="edit_purchase_unit_desc" id="edit_purchase_unit_desc" class="form-control" placeholder="e.g., Bag 1kg, Bottle 500ml" required></div>
                <div class="col-md-4"><label class="form-label">Quantity of Packs Purchased</label><input type="number" step="1" name="edit_purchase_qtty" id="edit_purchase_qtty" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Price Per Pack (RM)</label><input type="number" step="0.01" name="edit_unit_price" id="edit_unit_price" class="form-control" required></div>
                <div class="col-md-8">
                    <label class="form-label">Change Image (Optional)</label>
                    <div class="d-flex align-items-center">
                        <img id="edit_current_image" src="" alt="Current Image" class="me-3" style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px; display: none;">
                        <input type="file" name="edit_item_image" class="form-control" accept="image/*">
                    </div>
                </div>
            </div>
             <div class="alert alert-warning small mt-3"><strong>Note:</strong> Editing purchase details will recalculate the total stock. The amount already used will be preserved.</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" name="update_ingredient" class="btn btn-primary" style="background-color: var(--primary-dark); border-color: var(--primary-dark);">Save Changes</button></div>
      </form>
    </div>
  </div>
</div>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScript to populate the edit form
document.addEventListener('DOMContentLoaded', function () {
    const editModal = document.getElementById('editIngredientModal');
    if(editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const id = button.getAttribute('data-id');
            const name = button.getAttribute('data-name');
            const brand = button.getAttribute('data-brand');
            const buyDate = button.getAttribute('data-buy-date');
            const expireDate = button.getAttribute('data-expire-date');
            const inventoryUnit = button.getAttribute('data-inventory-unit');
            const purchaseUnitDesc = button.getAttribute('data-purchase-unit-desc');
            const purchaseQtty = button.getAttribute('data-purchase-qtty');
            const unitPrice = button.getAttribute('data-unit-price');
            const imgSrc = button.getAttribute('data-image-src');

            editModal.querySelector('#edit_item_id').value = id;
            editModal.querySelector('#edit_item_name').value = name;
            editModal.querySelector('#edit_brand').value = brand;
            editModal.querySelector('#edit_buy_date').value = buyDate;
            editModal.querySelector('#edit_expire_date').value = expireDate;
            editModal.querySelector('#edit_inventory_unit').value = inventoryUnit;
            editModal.querySelector('#edit_purchase_unit_desc').value = purchaseUnitDesc;
            editModal.querySelector('#edit_purchase_qtty').value = purchaseQtty;
            editModal.querySelector('#edit_unit_price').value = unitPrice;

            const currentImage = editModal.querySelector('#edit_current_image');
            if (imgSrc) {
                currentImage.src = imgSrc;
                currentImage.style.display = 'block';
            } else {
                currentImage.style.display = 'none';
            }
        });
    }
});
</script>
</body>
</html>