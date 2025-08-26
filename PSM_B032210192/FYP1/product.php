<?php
session_start();
require 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Get customer details
$cust_id = $_SESSION['customer_id'];
$stmt = $conn->prepare("SELECT CUST_NAME FROM customer WHERE CUST_ID = ?");
$stmt->bind_param("s", $cust_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();
$cust_name = $customer ? $customer['CUST_NAME'] : 'Valued Customer';

// Cart count
$cart_item_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Products - RY's Tasty Creation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .product-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border-radius: 15px;
            border: 1px solid #e9ecef;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .card-img-top {
            height: 220px;
            object-fit: cover;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
        }
        .out-of-stock .card-img-top {
            filter: grayscale(80%);
        }
        .out-of-stock .card-body {
            opacity: 0.7;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand" href="home_customer.php" style="color: #b33c86; font-weight: bold;">RY's Tasty Creation</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="home_customer.php">Home</a></li>
                <li class="nav-item"><a class="nav-link active" href="product.php">Products</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="cart.php">
                        <i class="bi bi-cart-fill"></i> Cart
                        <?php if ($cart_item_count > 0): ?>
                            <span class="badge bg-danger rounded-pill"><?= $cart_item_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($cust_name) ?>
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

<!-- Main Content -->
<div class="container py-5">
    <div class="text-center mb-4">
        <h1 class="display-4">Our Tasty Creations</h1>
        <p class="lead text-muted">Welcome, <?= htmlspecialchars($cust_name) ?>! Find your next favorite treat below.</p>
    </div>

    <!-- Message Section -->
    <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['message_type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Products Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-4 g-4">
        <?php
        $query = "SELECT PRODUCT_ID, PRODUCT_NAME, PRODUCT_IMAGE, PRODUCT_PRICE, PRODUCT_QTTY FROM products_sell";
        $product_result = $conn->query($query);

        if ($product_result->num_rows > 0):
            while ($product = $product_result->fetch_assoc()):
                $is_in_stock = (int)$product['PRODUCT_QTTY'] > 0;
                $card_class = $is_in_stock ? '' : 'out-of-stock';
        ?>
            <div class="col">
                <div class="card h-100 product-card position-relative <?= $card_class ?>">
                    <?php if (!$is_in_stock): ?>
                        <span class="badge bg-dark position-absolute top-0 start-0 m-2">OUT OF STOCK</span>
                    <?php endif; ?>

                    <img src="data:image/jpeg;base64,<?= base64_encode($product['PRODUCT_IMAGE']) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['PRODUCT_NAME']) ?>">

                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title"><?= htmlspecialchars($product['PRODUCT_NAME']) ?></h5>
                        <p class="card-price text-primary fw-bold">RM <?= number_format($product['PRODUCT_PRICE'], 2) ?></p>

                        <div class="mt-auto">
                            <?php if ($is_in_stock): ?>
                                <a href="product_detail.php?product_id=<?= $product['PRODUCT_ID'] ?>" class="btn btn-primary w-100">
                                    <i class="bi bi-search"></i> View Details
                                </a>
                            <?php else: ?>
                                <button class="btn btn-warning w-100 request-order-btn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#requestOrderModal"
                                        data-product-id="<?= $product['PRODUCT_ID'] ?>"
                                        data-product-name="<?= htmlspecialchars($product['PRODUCT_NAME']) ?>">
                                    <i class="bi bi-bell-fill"></i> Request to Order
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php
            endwhile;
        else:
        ?>
            <div class="col-12 text-center">
                <div class="alert alert-info">
                    No products available at the moment. Please check back later.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Request to Order Modal -->
<div class="modal fade" id="requestOrderModal" tabindex="-1" aria-labelledby="requestOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="request_order.php" method="POST" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestOrderModalLabel">Request an Order for <span id="modalProductName">Product</span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted">This product is currently out of stock. Submit a request and we’ll get back to you soon.</p>

                <input type="hidden" name="product_id" id="modalProductId">

                <div class="mb-3">
                    <label class="form-label">Quantity (packs)</label>
                    <input type="number" class="form-control" name="quantity" min="1" value="1" required>
                </div>
<div class="mb-3">
    <label class="form-label">Preferred Date</label>
    <input type="date" class="form-control" name="wanted_date" 
           min="<?= date('Y-m-d', strtotime('+2 days')) ?>" required>
</div>
                <div class="mb-3">
                    <label class="form-label">Preferred Time</label>
                    <select class="form-select" name="wanted_time" required>
                        <option value="" disabled selected>Select a time (8 AM - 6 PM)</option>
                        <?php
                        for ($h = 8; $h <= 17; $h++) {
                            $start = sprintf('%02d:00', $h);
                            $end = sprintf('%02d:00', $h + 1);
                            echo "<option value='$start'>" . date('g A', strtotime($start)) . " - " . date('g A', strtotime($end)) . "</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Footer -->
<footer class="text-center mt-4 py-4 text-muted border-top">
    © <?= date('Y') ?> RY's Tasty Creation
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const requestOrderModal = document.getElementById('requestOrderModal');
    requestOrderModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const productId = button.getAttribute('data-product-id');
        const productName = button.getAttribute('data-product-name');
        document.getElementById('modalProductId').value = productId;
        document.getElementById('modalProductName').textContent = productName;
    });
});
</script>
</body>
</html>
