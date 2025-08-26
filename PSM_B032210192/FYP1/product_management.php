<?php
session_start();
require 'db_connection.php';

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

// --- HELPER FUNCTION TO GENERATE NEW PRODUCT ID ---
function generateNewProductID($conn) {
    $result = $conn->query("SELECT PRODUCT_ID FROM products_sell ORDER BY PRODUCT_ID DESC LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $last_id = $result->fetch_assoc()['PRODUCT_ID'];
        $num_part = (int)substr($last_id, 1);
        $next_num = $num_part + 1;
        return 'P' . str_pad($next_num, 3, '0', STR_PAD_LEFT);
    } else {
        return 'P001';
    }
}

// --- CRUD LOGIC ---

// ADD PRODUCT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $new_product_id = generateNewProductID($conn);
    $product_name = $_POST['product_name'];
    $product_price = $_POST['product_price'];
    $product_pcs_per_pack = $_POST['product_pcs_per_pack'];
    $product_description = $_POST['product_description'];
    
    $image_data = NULL;
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == 0) {
        $image_data = file_get_contents($_FILES['product_image']['tmp_name']);
    }

    $stmt = $conn->prepare("INSERT INTO products_sell (PRODUCT_ID, PRODUCT_NAME, PRODUCT_IMAGE, PRODUCT_PRICE, PRODUCT_PCS_PER_PACK, PRODUCT_DESCRIPTION) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssbdis", $new_product_id, $product_name, $image_data, $product_price, $product_pcs_per_pack, $product_description);
    
    // send_long_data is necessary for blob data
    if ($image_data !== NULL) {
        $stmt->send_long_data(2, $image_data);
    }

    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>Product '{$product_name}' added successfully. You can now manage its recipe.</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Error adding product: " . $stmt->error . "</div>";
    }
    $stmt->close();
    header("Location: product_management.php");
    exit();
}

// DELETE PRODUCT
if (isset($_GET['delete'])) {
    $id_to_delete = $_GET['delete'];
    $conn->begin_transaction();
    try {
        $recipe_stmt = $conn->prepare("DELETE FROM product_ingredient WHERE PRODUCT_ID = ?");
        $recipe_stmt->bind_param("s", $id_to_delete);
        $recipe_stmt->execute();
        $recipe_stmt->close();

        $prod_stmt = $conn->prepare("DELETE FROM products_sell WHERE PRODUCT_ID = ?");
        $prod_stmt->bind_param("s", $id_to_delete);
        $prod_stmt->execute();
        $prod_stmt->close();
        
        $conn->commit();
        $_SESSION['message'] = "<div class='alert alert-success'>Product and its recipe deleted successfully.</div>";
    } catch (Exception $e) {
        $conn->rollback();
        if ($e->getCode() == 1451) {
             $_SESSION['message'] = "<div class='alert alert-danger'>Cannot delete. This product is part of an existing order.</div>";
        } else {
             $_SESSION['message'] = "<div class='alert alert-danger'>Error deleting product: " . $e->getMessage() . "</div>";
        }
    }
    header("Location: product_management.php");
    exit();
}

// FETCH PRODUCT FOR EDITING
$edit_data = null;
if (isset($_GET['edit'])) {
    $id_to_edit = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM products_sell WHERE PRODUCT_ID = ?");
    $stmt->bind_param("s", $id_to_edit);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_data = $result->fetch_assoc();
    }
    $stmt->close();
}

// UPDATE PRODUCT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_product'])) {
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $product_price = $_POST['product_price'];
    $product_pcs_per_pack = $_POST['product_pcs_per_pack'];
    $product_description = $_POST['product_description'];
    
    if (isset($_FILES['product_image']) && $_FILES['product_image']['size'] > 0) {
        $image_data = file_get_contents($_FILES['product_image']['tmp_name']);
        $stmt = $conn->prepare("UPDATE products_sell SET PRODUCT_NAME=?, PRODUCT_IMAGE=?, PRODUCT_PRICE=?, PRODUCT_PCS_PER_PACK=?, PRODUCT_DESCRIPTION=? WHERE PRODUCT_ID=?");
        // Note: The bind_param type string must match the number and types of placeholders
        $stmt->bind_param("ssdiss", $product_name, $image_data, $product_price, $product_pcs_per_pack, $product_description, $product_id);
        $stmt->send_long_data(1, $image_data);
    } else {
        $stmt = $conn->prepare("UPDATE products_sell SET PRODUCT_NAME=?, PRODUCT_PRICE=?, PRODUCT_PCS_PER_PACK=?, PRODUCT_DESCRIPTION=? WHERE PRODUCT_ID=?");
        $stmt->bind_param("sdiss", $product_name, $product_price, $product_pcs_per_pack, $product_description, $product_id);
    }

    if ($stmt->execute()) {
        $_SESSION['message'] = "<div class='alert alert-success'>Product '{$product_name}' updated successfully.</div>";
    } else {
        $_SESSION['message'] = "<div class='alert alert-danger'>Update failed: " . $stmt->error . "</div>";
    }
    $stmt->close();
    header("Location: product_management.php");
    exit();
}

// Fetch all products for display
$products_result = $conn->query("SELECT * FROM products_sell ORDER BY PRODUCT_NAME ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Product Management - RY's Tasty Creations</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    /* STYLES COPIED FROM DASHBOARD FOR CONSISTENCY */
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
  </style>
</head>
<body>
 <!-- Sidebar Navigation (Now consistent with dashboard) -->
  <div class="sidebar d-flex flex-column p-0">
    <div class="sidebar-header text-center">
      <h4 class="fw-bold mb-1">RY's Tasty Creations</h4>
      <p class="text-white-50 mb-0">Owner Panel</p>
    </div>
    <ul class="nav flex-column my-4 flex-grow-1">
<li class="nav-item"><a href="owner_dashboard.php" class="nav-link "><i class="bi bi-grid-1x2-fill"></i> Dashboard</a></li>
        <li class="nav-item"><a href="view_orders.php" class="nav-link"><i class="bi bi-receipt"></i> Order Management</a></li>
        <li class="nav-item"><a href="produce_stock.php" class="nav-link"><i class="bi bi-hammer"></i> Production</a></li>
         <li class="nav-item"><a href="production_ordered.php" class="nav-link "><i class="bi bi-bell-fill"></i> Production Auto</a></li>
        <li class="nav-item"><a href="product_management.php" class="nav-link active"><i class="bi bi-box-seam"></i> Products</a></li>
        <li class="nav-item"><a href="ingredient_management.php" class="nav-link"><i class="bi bi-droplet"></i> Ingredients</a></li>
        <li class="nav-item"><a href="sales_reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
        <li class="nav-item"><a href="customer_management.php" class="nav-link"><i class="bi bi-people"></i> Customers</a></li>
    </ul>
  </div>

  <!-- Main Content -->
  <main class="main-content d-flex flex-column">

    <!-- Added Top Header Bar for consistency -->
    <header class="main-header">
      <h1 class="h3 mb-0 fw-bold text-dark">Product Management</h1>
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
        <p class="text-muted mb-4">Define the products you offer. Stock is managed via the 'Production' page.</p>
        
        <?php echo $message; ?>

        <div class="form-card p-4 mb-4">
            <h5 class="mb-3"><?= $edit_data ? "Edit Product Details" : "Add New Product" ?></h5>
            <form method="POST" action="product_management.php" enctype="multipart/form-data">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="product_id" value="<?= htmlspecialchars($edit_data['PRODUCT_ID']) ?>">
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Product Name</label><input type="text" name="product_name" class="form-control" value="<?= htmlspecialchars($edit_data['PRODUCT_NAME'] ?? '') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Price per Pack (RM)</label><input type="number" step="0.01" name="product_price" class="form-control" value="<?= htmlspecialchars($edit_data['PRODUCT_PRICE'] ?? '') ?>" required></div>
                    <div class="col-md-3"><label class="form-label">Pieces per Pack</label><input type="number" name="product_pcs_per_pack" class="form-control" value="<?= htmlspecialchars($edit_data['PRODUCT_PCS_PER_PACK'] ?? '') ?>" required></div>
                    <div class="col-md-12"><label class="form-label">Description</label><textarea name="product_description" class="form-control"><?= htmlspecialchars($edit_data['PRODUCT_DESCRIPTION'] ?? '') ?></textarea></div>
                    <div class="col-md-12"><label class="form-label">Product Image</label><input type="file" name="product_image" class="form-control" accept="image/*">
                        <?php if ($edit_data && !empty($edit_data['PRODUCT_IMAGE'])): ?>
                            <small class="form-text text-muted">Current image is set. Upload a new one to replace it.</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-12 text-end">
                        <?php if ($edit_data): ?>
                            <a href="product_management.php" class="btn btn-secondary">Cancel Edit</a>
                        <?php endif; ?>
                        <button type="submit" name="<?= $edit_data ? 'update_product' : 'add_product' ?>" class="btn btn-primary" style="background-color: var(--primary-dark); border-color: var(--primary-dark);">
                            <?= $edit_data ? "Update Details" : "Add Product" ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="table-card p-4">
            <h5 class="mb-3">Product List</h5>
            <div class="table-responsive">
            <table class="table table-hover table-striped align-middle">
                <thead class="table-light">
                    <tr><th>Image</th><th>ID</th><th>Name</th><th>Stock (Packs)</th><th>Price</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if ($products_result->num_rows > 0): ?>
                        <?php while ($row = $products_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if (!empty($row['PRODUCT_IMAGE'])): ?>
                                    <img src="data:image/jpeg;base64,<?= base64_encode($row['PRODUCT_IMAGE']) ?>" width="60" height="60" alt="<?= htmlspecialchars($row['PRODUCT_NAME']) ?>" style="object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                    <div class="bg-light d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border-radius: 4px;"><i class="bi bi-box-seam text-muted fs-4"></i></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['PRODUCT_ID']) ?></td>
                            <td>
                                <div class="fw-bold"><?= htmlspecialchars($row['PRODUCT_NAME']) ?></div>
                                <small class="text-muted"><?= htmlspecialchars($row['PRODUCT_PCS_PER_PACK']) ?> pcs/pack</small>
                            </td>
                            <td class="fw-bold fs-5 text-center"><?= htmlspecialchars($row['PRODUCT_QTTY']) ?></td>
                            <td>RM <?= number_format($row['PRODUCT_PRICE'], 2) ?></td>
                            <td>
                                <a href="manage_recipe.php?product_id=<?= htmlspecialchars($row['PRODUCT_ID']) ?>" class="btn btn-info btn-sm" title="Manage Recipe"><i class="bi bi-card-checklist"></i> Recipe</a>
                                <a href="?edit=<?= htmlspecialchars($row['PRODUCT_ID']) ?>" class="btn btn-warning btn-sm" title="Edit Product"><i class="bi bi-pencil-fill"></i> Edit</a>
                                <a href="?delete=<?= htmlspecialchars($row['PRODUCT_ID']) ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product and its recipe?')" title="Delete Product"><i class="bi bi-trash-fill"></i></a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center p-4">No products found. Add one using the form above.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
  </main>
</body>
</html>