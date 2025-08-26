<?php
session_start();
require 'db_connection.php';

// --- PHP LOGIC (Mostly Unchanged, it's good!) ---

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Get customer name for a personalized welcome
$customer_name = $_SESSION['customer_name'] ?? 'Customer'; // Assuming you store name in session

// Check if product_id is passed in the URL
if (!isset($_GET['product_id'])) {
    header("Location: product.php");
    exit();
}

$product_id = $_GET['product_id'];

// Fetch product details from the database
$product_query = $conn->prepare("SELECT * FROM products_sell WHERE PRODUCT_ID = ?");
$product_query->bind_param("s", $product_id);
$product_query->execute();
$product_result = $product_query->get_result();

if ($product_result->num_rows == 0) {
    // A more user-friendly error page would be better, but this works
    $_SESSION['message'] = "The product you are looking for could not be found.";
    header("Location: product.php");
    exit();
}

$product = $product_result->fetch_assoc();

// New: Check stock availability to disable form if needed
$is_in_stock = (int)$product['PRODUCT_QTTY'] > 0;

// Handle session messages (e.g., "Item added to cart")
$message = '';
$message_type = 'info'; // To allow for success, danger, etc.
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'] ?? 'info';
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// Get cart item count for the navbar
$cart_item_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($product['PRODUCT_NAME']); ?> - Product Detail</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
    /* A more professional and softer color palette */
    body {
        background-color: #f8f9fa; /* Light gray background */
        font-family: 'Segoe UI', sans-serif;
    }
    .product-card {
        background-color: white;
        padding: 2rem;
        border-radius: 15px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        margin-top: 2rem;
    }
    .product-image {
        width: 100%;
        height: 400px; /* Fixed height for consistency */
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #dee2e6;
    }
    .product-title {
        color: #343a40;
        font-weight: 700;
    }
    .price {
        font-size: 1.75rem;
        font-weight: bold;
        color: #b33c86; /* Keeping a brand color for highlights */
    }
    .stock-status {
        font-weight: 500;
    }
    .btn {
        padding: 0.75rem 1.25rem;
        font-weight: bold;
        border-radius: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .form-label {
        font-weight: 600;
        color: #495057;
    }
    .navbar {
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    /* Style for the out-of-stock overlay/message */
    .out-of-stock-badge {
        font-size: 1rem;
        padding: 0.5em 1em;
    }
</style>
</head>
<body>

<!-- 1. Added a Navigation Bar for better site-wide usability -->
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand" href="home_customer.php" style="color: #b33c86; font-weight: bold;">RY's Tasty Creation</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="home_customer.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="product.php">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="cart.php">
                        <i class="bi bi-cart-fill"></i> Cart
                        <?php if ($cart_item_count > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $cart_item_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                 <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($customer_name) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="customerorder.php">My Orders</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<main class="container">
    <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($message_type) ?> alert-dismissible fade show mt-4" role="alert">
            <?= htmlspecialchars($message); ?>
            <!-- Better user flow: give a direct link to the cart after success -->
            <?php if ($message_type === 'success'): ?>
                <a href="cart.php" class="btn btn-sm btn-outline-dark ms-3">View Cart</a>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="product-card">
        <div class="row g-5">
            <div class="col-lg-6">
                <img src="data:image/jpeg;base64,<?php echo base64_encode($product['PRODUCT_IMAGE']); ?>" alt="<?php echo htmlspecialchars($product['PRODUCT_NAME']); ?>" class="product-image" />
            </div>
            <div class="col-lg-6 d-flex flex-column">
                <h1 class="product-title display-5"><?php echo htmlspecialchars($product['PRODUCT_NAME']); ?></h1>
                
                <p class="price mt-2">RM <?php echo number_format($product['PRODUCT_PRICE'], 2); ?></p>
                
                <p class="lead text-muted mt-3"><?php echo nl2br(htmlspecialchars($product['PRODUCT_DESCRIPTION'])); ?></p>
                
                <hr class="my-4">

                <!-- 2. Better Stock Handling -->
                <?php if ($is_in_stock): ?>
                    <p class="stock-status text-success"><i class="bi bi-check-circle-fill"></i> In Stock: <strong><?php echo (int)$product['PRODUCT_QTTY']; ?> pack(s)</strong> available</p>
                    <form method="POST" action="add_to_cart.php" class="mt-2">
                        <input type="hidden" name="product_id" value="<?php echo $product['PRODUCT_ID']; ?>">

                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="quantity" class="form-label">Quantity:</label>
                                <input type="number" name="quantity" id="quantity" class="form-control form-control-lg" min="1" max="<?php echo (int)$product['PRODUCT_QTTY']; ?>" value="1" required>
                            </div>
<div class="col-sm-6">
    <label for="wanted_date" class="form-label">Pickup Date:</label>
    <input type="date" 
           name="wanted_date" 
           id="wanted_date" 
           class="form-control form-control-lg" 
           min="<?= date('Y-m-d') ?>" 
           required>
</div>

<div class="col-sm-6">
    <label for="wanted_time" class="form-label">Pickup Time:(Between 8AM to 6PM)</label>
    <input type="time" 
           name="wanted_time" 
           id="wanted_time" 
           class="form-control form-control-lg" 
           min="08:00" max="18:00" 
           required>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const dateInput = document.getElementById("wanted_date");
    const timeInput = document.getElementById("wanted_time");

    function updateMinTime() {
        const today = new Date();
        const selectedDate = new Date(dateInput.value);

        if (dateInput.value && selectedDate.toDateString() === today.toDateString()) {
            // If today, min time = current time (rounded to next minute)
            const currentHours = today.getHours().toString().padStart(2, '0');
            const currentMinutes = today.getMinutes().toString().padStart(2, '0');
            const nowTime = `${currentHours}:${currentMinutes}`;

            // Ensure it doesn't go below 08:00
            timeInput.min = (nowTime > "08:00") ? nowTime : "08:00";
            timeInput.max = "18:00";
        } else {
            // If future date, allow full range (8 AM – 6 PM)
            timeInput.min = "08:00";
            timeInput.max = "18:00";
        }
    }

    // Run when date changes
    dateInput.addEventListener("change", updateMinTime);

    // Run once on page load
    updateMinTime();
});
</script>

                        </div>

                        <div class="d-grid gap-2 d-sm-flex mt-4">
                            <button type="submit" name="action" value="add_to_cart" class="btn btn-outline-primary btn-lg flex-grow-1"><i class="bi bi-cart-plus-fill"></i> Add to Cart</button>
                            <button type="submit" name="action" value="buy_now" class="btn btn-primary btn-lg flex-grow-1"><i class="bi bi-lightning-charge-fill"></i> Buy Now</button>
                        </div>
                    </form>
                <?php else: ?>
                    <!-- Clear 'Out of Stock' message -->
                    <div class="alert alert-danger text-center">
                        <h4 class="alert-heading"><i class="bi bi-x-circle-fill"></i> Out of Stock</h4>
                        <p class="mb-0">This product is currently unavailable. Please check back later.</p>
                    </div>
                <?php endif; ?>

                <div class="mt-auto pt-4 text-center text-sm-start">
                     <a href="product.php" class="btn btn-link text-secondary"><i class="bi bi-arrow-left"></i> Back to All Products</a>
                </div>

            </div>
        </div>
    </div>
</main>

<footer class="text-center mt-5 mb-4 text-muted">
    © <?php echo date('Y'); ?> RY's Tasty Creation
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>