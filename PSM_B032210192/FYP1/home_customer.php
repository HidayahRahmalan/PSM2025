<?php
session_start();
require 'db_connection.php';

// Check if the user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$cust_id = $_SESSION['customer_id'];

// Fetch customer + assigned seller info
$stmt = $conn->prepare("
    SELECT 
        c.CUST_NAME,
        s.SELLER_NAME,
        s.SELLER_PHONE,
        s.SELLER_EMAIL,
        s.SELLER_ADDRESS
    FROM customer c
    LEFT JOIN seller s ON c.SELLER_ID = s.SELLER_ID
    WHERE c.CUST_ID = ?
");

$stmt->bind_param("s", $cust_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

$cust_name = $customer ? $customer['CUST_NAME'] : 'Valued Customer';
$seller_name = $customer['SELLER_NAME'] ?? 'No Seller Assigned';
$seller_phone = $customer['SELLER_PHONE'] ?? '-';
$seller_email = $customer['SELLER_EMAIL'] ?? '-';
$seller_address = $customer['SELLER_ADDRESS'] ?? '-';

// Count items in cart
$cart_item_count = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;


// --- NEW PHP LOGIC TO PREPARE INTERACTIVE LINKS ---
$maps_link = '#';
if ($seller_address !== '-') {
    $maps_link = 'https://www.google.com/maps?q=' . urlencode($seller_address);
}

$whatsapp_link = '#';
if ($seller_phone !== '-') {
    // Clean phone number for WhatsApp link (remove non-digits like spaces, +, -)
    $whatsapp_number = preg_replace('/[^\d]/', '', $seller_phone);
    // Handle local numbers (e.g., 012...) by replacing the leading 0 with country code 6
    if (substr($whatsapp_number, 0, 1) === '0') {
        $whatsapp_number = '6' . $whatsapp_number;
    }
    // Create a pre-filled message for convenience
    $whatsapp_message = urlencode("Hello " . $seller_name . ", I'm " . $cust_name . " and I have a question regarding my order.");
    $whatsapp_link = 'https://wa.me/' . $whatsapp_number . '?text=' . $whatsapp_message;
}

$email_link = '#';
if ($seller_email !== '-') {
    $email_link = 'mailto:' . $seller_email;
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - RY's Tasty Creation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Consistent, professional theme */
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        /* Hero Section Styling */
        .hero-section {
            background: url('backgroundd.jpg') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 8rem 0;
            text-align: center;
            position: relative;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5); /* Dark overlay for text readability */
        }
        .hero-section .container {
            position: relative; /* Ensure text is on top of the overlay */
        }
        /* Reusable product card styles from product.php */
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
        .card-price {
            font-size: 1.25rem;
            font-weight: bold;
            color: #b33c86;
        }
    </style>
</head>
<body>

<!-- 1. Consistent User-Friendly Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
    <div class="container">
        <a class="navbar-brand" href="home_customer.php" style="color: #b33c86; font-weight: bold;">RY's Tasty Creation</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="home_customer.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="product.php">Products</a>
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

<!-- 2. Hero Section -->
<header class="hero-section">
    <div class="container">
        <h1 class="display-3 fw-bold">Welcome, <?= htmlspecialchars($cust_name) ?>!</h1>
        <p class="lead col-lg-8 mx-auto">Discover delicious, homemade treats baked with love. Ready for pickup when you are.</p>
        <a href="product.php" class="btn btn-primary btn-lg mt-3">
            <i class="bi bi-basket2-fill"></i> Browse All Products
        </a>
    </div>
</header>

<!-- Main Content Area -->
<main class="container my-5">

    <!-- 3. Featured Products Section -->
    <section class="text-center mb-5">
        <h2 class="display-5">New Arrivals</h2>
        <p class="lead text-muted">Check out the latest additions to our kitchen.</p>
    </section>

    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-4 g-4">
        <?php
        // Fetch 4 newest in-stock products to feature
        $query = "SELECT PRODUCT_ID, PRODUCT_NAME, PRODUCT_IMAGE, PRODUCT_PRICE FROM products_sell WHERE PRODUCT_QTTY > 0 ORDER BY PRODUCT_ID DESC LIMIT 4";
        $product_result = $conn->query($query);

        if ($product_result && $product_result->num_rows > 0) {
            while ($product = $product_result->fetch_assoc()) {
        ?>
                <div class="col">
                    <div class="card h-100 product-card">
                        <img src="data:image/jpeg;base64,<?= base64_encode($product['PRODUCT_IMAGE']) ?>" class="card-img-top" alt="<?= htmlspecialchars($product['PRODUCT_NAME']) ?>">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($product['PRODUCT_NAME']) ?></h5>
                            <p class="card-price mt-2 mb-4">RM <?= number_format($product['PRODUCT_PRICE'], 2) ?></p>
                            <a href="product_detail.php?product_id=<?= $product['PRODUCT_ID'] ?>" class="btn btn-outline-primary mt-auto">
                                <i class="bi bi-search"></i> View Details
                            </a>
                        </div>
                    </div>
                </div>
        <?php
            } // end while
        } else {
        ?>
            <div class="col-12">
                <p class="text-center text-muted">No featured products at the moment. Please check our full product list!</p>
            </div>
        <?php
        } // end if
        ?>
    </div>

    <!-- 4. Value Proposition Section -->
    <section class="p-5 my-5 bg-light rounded-3">
        <div class="row align-items-center">
            <div class="col-md-4 text-center">
                <i class="bi bi-star-fill text-warning" style="font-size: 4rem;"></i>
            </div>
            <div class="col-md-8">
                <h3>Quality You Can Taste</h3>
                <p class="lead">At RY's Tasty Creation, every item is crafted with the finest ingredients and a passion for baking. We guarantee freshness and flavor in every bite. Order online and enjoy a simple and convenient pickup service.</p>
            </div>
        </div>
    </section>

    <!-- 5. Seller Information Section (IMPROVED) -->
    <section class="container my-5" id="seller-info">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Seller Detail</h5>
                <i class="bi bi-person-check-fill fs-4"></i>
            </div>
            <div class="card-body">
                <h4 class="card-title"><?= htmlspecialchars($seller_name) ?></h4>
                
                <?php if ($seller_name !== 'No Seller Assigned'): ?>
                    <p class="card-subtitle mb-3 text-muted">You can contact this seller directly for any questions about your order or pickup arrangements.</p>
                    <ul class="list-group list-group-flush">

                        <!-- Phone / WhatsApp Link -->
                        <?php if ($seller_phone !== '-'): ?>
                        <li class="list-group-item d-flex align-items-center py-3">
                            <i class="bi bi-whatsapp fs-4 me-3 text-success"></i>
                            <div>
                                <strong>Contact via WhatsApp</strong><br>
                                <a href="<?= $whatsapp_link ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                    <?= htmlspecialchars($seller_phone) ?>
                                </a>
                            </div>
                        </li>
                        <?php endif; ?>

                        <!-- Email Link -->
                        <?php if ($seller_email !== '-'): ?>
                        <li class="list-group-item d-flex align-items-center py-3">
                            <i class="bi bi-envelope-fill fs-4 me-3 text-primary"></i>
                            <div>
                                <strong>Email Seller</strong><br>
                                <a href="<?= $email_link ?>" class="text-decoration-none">
                                    <?= htmlspecialchars($seller_email) ?>
                                </a>
                            </div>
                        </li>
                        <?php endif; ?>
                        
                        <!-- Address / Maps Link -->
                        <?php if ($seller_address !== '-'): ?>
                        <li class="list-group-item d-flex align-items-center py-3">
                            <i class="bi bi-geo-alt-fill fs-4 me-3 text-danger"></i>
                            <div>
                                <strong>Pickup Location</strong><br>
                                <a href="<?= $maps_link ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                    <?= nl2br(htmlspecialchars($seller_address)) ?>
                                </a>
                            </div>
                        </li>
                        <?php endif; ?>

                    </ul>
                <?php else: ?>
                    <p class="text-muted mt-3">Once an order is confirmed, your seller's details will appear here for easy contact.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

</main>

<!-- Footer -->
<footer class="text-center py-4 text-muted border-top">
    © <?php echo date('Y'); ?> RY's Tasty Creation
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>