<?php
session_start();
require 'db_connection.php';

// (SEMUA KOD PHP DI ATAS SAMA SEPERTI SEBELUM INI, TIADA PERUBAHAN)

// Security: Check if user is logged in
if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

// Security: Check if an order_id is provided in the URL
if (!isset($_GET['order_id'])) {
    $_SESSION['message'] = "No order selected for payment.";
    $_SESSION['message_type'] = 'warning';
    header("Location: orders.php");
    exit();
}

$customer_id = $_SESSION['customer_id'];
$order_id = (int)$_GET['order_id'];

// Fetch the order details
$stmt = $conn->prepare("
    SELECT TOTAL_AMOUNT, ORDER_PAYMENT_STATUS 
    FROM customer_order 
    WHERE ORDER_ID = ? AND CUST_ID = ?
");
$stmt->bind_param("is", $order_id, $customer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "Order not found.";
    $_SESSION['message_type'] = 'danger';
    header("Location: orders.php");
    exit();
}

$order = $result->fetch_assoc();

if ($order['ORDER_PAYMENT_STATUS'] !== 'Unpaid') {
    $_SESSION['message'] = "This order has already been paid.";
    $_SESSION['message_type'] = 'info';
    header("Location: order_detail.php?order_id=" . $order_id);
    exit();
}

$total_amount = $order['TOTAL_AMOUNT'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment for Order #<?= $order_id ?> - RY's Tasty Creation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .nav-pills .nav-link { color: #6c757d; font-weight: 500; }
        .nav-pills .nav-link.active { background-color: #0d6efd; color: white; }
        .payment-icon { width: 80px; }
        .bank-logo-link { display: inline-block; padding: 5px; border: 1px solid #ddd; border-radius: 4px; margin: 5px; transition: box-shadow .2s; }
        .bank-logo-link:hover { box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .bank-logo { height: 40px; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="card shadow-lg border-0">
                <div class="card-header bg-primary text-white text-center">
                    <h2 class="mb-0"><i class="bi bi-shield-lock-fill"></i> Secure Payment</h2>
                </div>
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <p class="text-muted mb-1">You are paying for Order #<?= $order_id ?></p>
                        <h1 class="display-5 fw-bold">RM <?= number_format($total_amount, 2) ?></h1>
                    </div>
                    
                    <!-- Tab Navigation -->
                    <ul class="nav nav-pills nav-fill mb-4" id="paymentTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="card-tab" data-bs-toggle="tab" data-bs-target="#card-pane" type="button" role="tab"><i class="bi bi-credit-card-fill"></i> Card</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="banking-tab" data-bs-toggle="tab" data-bs-target="#banking-pane" type="button" role="tab"><i class="bi bi-bank"></i> Online Banking</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="ewallet-tab" data-bs-toggle="tab" data-bs-target="#ewallet-pane" type="button" role="tab"><i class="bi bi-phone-fill"></i> eWallet</button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content" id="paymentTabsContent">
                        
                        <!-- 1. Credit/Debit Card Pane -->
                        <div class="tab-pane fade show active" id="card-pane" role="tabpanel">
                            <form action="process_payment.php" method="POST">
                                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                <input type="hidden" name="total_amount" value="<?= $total_amount ?>">
                                <h5 class="mb-3">Enter Card Details</h5>
                                <div class="mb-3"><label for="cardName" class="form-label">Cardholder Name</label><input type="text" id="cardName" class="form-control form-control-lg" placeholder="e.g. Aimi Najwa" required></div>
                                <div class="mb-3"><label for="cardNumber" class="form-label">Card Number</label><div class="input-group"><span class="input-group-text"><i class="bi bi-credit-card-2-front-fill"></i></span><input type="text" id="cardNumber" class="form-control form-control-lg" placeholder="xxxx xxxx xxxx xxxx" required></div></div>
                                <div class="row"><div class="col-md-7 mb-3"><label for="expiryDate" class="form-label">Expiration Date</label><input type="text" id="expiryDate" class="form-control form-control-lg" placeholder="MM / YY" required></div><div class="col-md-5 mb-3"><label for="cvv" class="form-label">CVV</label><input type="text" id="cvv" class="form-control form-control-lg" placeholder="123" required></div></div>
                                <div class="d-grid mt-4"><button type="submit" class="btn btn-success btn-lg"><i class="bi bi-lock-fill"></i> Pay RM <?= number_format($total_amount, 2) ?> Now</button></div>
                            </form>
                        </div>

                        <!-- 2. Online Banking (FPX) Pane -->
                        <div class="tab-pane fade" id="banking-pane" role="tabpanel">
                            <div class="text-center">
                                <h5 class="mb-3">Select Your Bank</h5>
                                <p class="text-muted small">You will be redirected to a dummy payment page.</p>
                                <div class="d-flex flex-wrap justify-content-center align-items-center">
                                    <!-- PERUBAHAN DI SINI: Setiap imej kini adalah pautan -->
                                    <a href="dummy_bank.php?order_id=<?= $order_id ?>&bank=Maybank" class="bank-logo-link">
                                        <img src="maybank2u.png" alt="Maybank2u" class="bank-logo">
                                    </a>
                                    <a href="dummy_bank.php?order_id=<?= $order_id ?>&bank=CIMB" class="bank-logo-link">
                                        <img src="cimb.webp" alt="CIMB Clicks" class="bank-logo">
                                    </a>
                                    <a href="dummy_bank.php?order_id=<?= $order_id ?>&bank=Public+Bank" class="bank-logo-link">
                                        <img src="publicbank.webp" alt="Public Bank" class="bank-logo">
                                    </a>
                                    <a href="dummy_bank.php?order_id=<?= $order_id ?>&bank=RHB" class="bank-logo-link">
                                        <img src="rhb.webp" alt="RHB Bank" class="bank-logo">
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- 3. TNG eWallet Pane -->
                        <div class="tab-pane fade" id="ewallet-pane" role="tabpanel">
                             <form action="process_payment.php" method="POST">
                                <input type="hidden" name="order_id" value="<?= $order_id ?>">
                                <input type="hidden" name="total_amount" value="<?= $total_amount ?>">
                                <div class="text-center">
                                    <h5 class="mb-3">Pay with Touch 'n Go eWallet</h5>
                                    <p class="text-muted small">Click button below to simulate payment.</p>
                                    <img src="https://cdn.touchngo.com.my/images/tng-logo.png" alt="Touch 'n Go eWallet" class="payment-icon">
                                    <div class="d-grid mt-4"><button type="submit" class="btn btn-success btn-lg"><i class="bi bi-lock-fill"></i> Pay RM <?= number_format($total_amount, 2) ?> Now</button></div>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                         <a href="order_detail.php?order_id=<?= $order_id ?>" class="btn btn-link text-secondary"><i class="bi bi-arrow-left"></i> Back to Order Details</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>