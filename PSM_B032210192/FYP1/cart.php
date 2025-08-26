<?php
session_start();
require 'db_connection.php';

// Check login status
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$customer_name = $_SESSION['customer_name'] ?? 'Customer';

// --- Fetch cart items and JOIN to get product details, INCLUDING the image ---
$stmt = $conn->prepare("
    SELECT 
        c.PRODUCT_ID, 
        c.CART_QTTY, 
        p.PRODUCT_NAME, 
        p.PRODUCT_PRICE,
        p.PRODUCT_QTTY as stock_qtty,
        p.PRODUCT_IMAGE
    FROM cart c
    JOIN products_sell p ON c.PRODUCT_ID = p.PRODUCT_ID
    WHERE c.CUST_ID = ?
");
$stmt->bind_param("s", $customer_id);
$stmt->execute();
$cart_items_result = $stmt->get_result();
$cart_items = $cart_items_result->fetch_all(MYSQLI_ASSOC);

$grand_total = 0;

// Get total item count for navbar badge
$cart_item_count = 0;
foreach ($cart_items as $item) {
    $cart_item_count += $item['CART_QTTY'];
}

// Handle any feedback messages from other pages
$message = $_SESSION['message'] ?? '';
$message_type = $_SESSION['message_type'] ?? 'info';
unset($_SESSION['message'], $_SESSION['message_type']);
?>
<?php
$message = $message ?? '';
$message_type = $message_type ?? 'info'; // fallback to 'info' class if not set
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Shopping Cart - RY's Tasty Creations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f5f7; }
        .cart-item-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
        }
        .quantity-input { width: 70px; }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="home_customer.php" style="color: #b33c86; font-weight: bold;">RY's Tasty Creation</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="home_customer.php">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="product.php">Products</a></li>
                <li class="nav-item">
                    <a class="nav-link active" href="cart.php">
                        <i class="bi bi-cart-fill"></i> Cart
                        <?php if ($cart_item_count > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $cart_item_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                 <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($customer_name) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="customerorder.php">My Orders</a></li>
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container py-5">
    <h1 class="mb-4">Your Shopping Cart</h1>

    <?php if (!empty($message)): ?>
    <div class="alert alert-<?= htmlspecialchars((string)$message_type) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars((string)$message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div class="card text-center py-5 shadow-sm border-0">
            <div class="card-body">
                <i class="bi bi-cart-x" style="font-size: 4rem; color: #6c757d;"></i>
                <h3 class="card-title mt-3">Your cart is empty</h3>
                <p class="card-text text-muted">Looks like you haven't added anything to your cart yet.</p>
                <a href="product.php" class="btn btn-primary">Start Shopping</a>
            </div>
        </div>
    <?php else: ?>
        <!-- =================================================================== -->
        <!-- START: The single, main form that submits EVERYTHING to process_checkout.php -->
        <!-- =================================================================== -->
        <form action="process_checkout.php" method="POST">
            <div class="row g-4">
                <!-- Cart Items Column -->
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0"><?= count($cart_items) ?> Items</h5>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-borderless align-middle mb-0">
  <!-- In cart.php -->
<tbody>
    <?php foreach ($cart_items as $item): ?>
        <?php
            // Retrieve pickup details. It's now expected to exist.
            $details = $_SESSION['cart_details'][$item['PRODUCT_ID']] ?? []; 
            $item_total = $item['PRODUCT_PRICE'] * $item['CART_QTTY'];
            $grand_total += $item_total;

            // Prepare display strings safely
            $pickup_date = !empty($details['wanted_date']) ? htmlspecialchars($details['wanted_date']) : '<span class="text-danger">Not Set</span>';
            $pickup_time = !empty($details['wanted_time']) ? htmlspecialchars(date("h:i A", strtotime($details['wanted_time']))) : '';
        ?>
        <tr>
            <td style="width: 100px;">
                <img src="data:image/jpeg;base64,<?= base64_encode($item['PRODUCT_IMAGE']) ?>" class="cart-item-img" alt="<?= htmlspecialchars($item['PRODUCT_NAME']) ?>">
            </td>
            <td>
                <h6 class="mb-0"><?= htmlspecialchars($item['PRODUCT_NAME']) ?></h6>
                <small class="text-muted">
                    Pickup: <?= $pickup_date ?> at <?= $pickup_time ?>
                </small>
                
                <!-- These hidden fields are CRITICAL. They pass the data to process_checkout.php -->
                <input type="hidden" name="wanted_date[<?= $item['PRODUCT_ID'] ?>]" value="<?= htmlspecialchars($details['wanted_date'] ?? '') ?>">
                <input type="hidden" name="wanted_time[<?= $item['PRODUCT_ID'] ?>]" value="<?= htmlspecialchars($details['wanted_time'] ?? '') ?>">
            </td>
<td style="width: 180px;">
    <!-- UPDATE QUANTITY (NO NESTED FORM) -->
    <div class="d-flex">
        <input type="number" id="qtty-<?= $item['PRODUCT_ID'] ?>" class="form-control form-control-sm quantity-input" value="<?= $item['CART_QTTY'] ?>" min="1" max="<?= $item['stock_qtty'] ?>">
        
        <a href="#" 
           onclick="updateQuantity(<?= $item['PRODUCT_ID'] ?>)" 
           class="btn btn-sm btn-outline-secondary ms-2" 
           title="Update Quantity">
           <i class="bi bi-arrow-repeat"></i>
        </a>
    </div>
</td>
            <td class="text-end" style="width: 120px;">
                <span class="fw-bold">RM <?= number_format($item_total, 2) ?></span>
            </td>
            <td class="text-center" style="width: 50px;">
                <a href="remove_from_cart.php?product_id=<?= $item['PRODUCT_ID'] ?>" class="btn btn-sm btn-outline-danger" title="Remove Item"><i class="bi bi-trash"></i></a>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Summary Column -->
                <div class="col-lg-4">
                    <div class="card sticky-top shadow-sm border-0" style="top: 80px;">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Order Summary</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 pb-0">Subtotal<span>RM <?= number_format($grand_total, 2) ?></span></li>
                                <li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0">Shipping<span>Self-Pickup</span></li>
                            </ul>
                            <hr class="my-3">
                            <div class="d-flex justify-content-between align-items-center fw-bold fs-5">
                                <span>Total</span>
                                <span>RM <?= number_format($grand_total, 2) ?></span>
                            </div>
                        </div>
                        <div class="card-footer border-0 bg-white p-3">
                            <!-- This button is now INSIDE the main form and will submit it -->
                            <button type="submit" class="btn btn-success btn-lg w-100"> Place Order </button>
                            <div class="text-center mt-3">
                                <a href="product.php" class="text-decoration-none text-muted"><i class="bi bi-arrow-left"></i> Continue Shopping</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <!-- =================================================================== -->
        <!-- END: Main form -->
        <!-- =================================================================== -->
    <?php endif; ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateQuantity(productId) {
    const quantityInput = document.getElementById('qtty-' + productId);
    const newQuantity = quantityInput.value;
    
    // NOTE: This now points to your original script name!
    window.location.href = `update_cart.php?product_id=${productId}&quantity=${newQuantity}`;
}
</script>
</body>
</html>