<?php
session_start();
require 'db_connection.php';

// Keselamatan: Pastikan semua data yang diperlukan ada
if (!isset($_SESSION['customer_id']) || !isset($_GET['order_id']) || !isset($_GET['bank'])) {
    header("Location: orders.php");
    exit();
}

$order_id = (int)$_GET['order_id'];
$bank_name = htmlspecialchars($_GET['bank']);

// Dapatkan jumlah bayaran dari pangkalan data
$stmt = $conn->prepare("SELECT TOTAL_AMOUNT FROM customer_order WHERE ORDER_ID = ? AND CUST_ID = ?");
$stmt->bind_param("is", $order_id, $_SESSION['customer_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Order tidak dijumpai
    header("Location: orders.php");
    exit();
}
$order = $result->fetch_assoc();
$total_amount = $order['TOTAL_AMOUNT'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dummy <?= $bank_name ?> Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f0f2f5; }
        .payment-container { max-width: 500px; margin: 5rem auto; }
        .bank-header { padding: 1.5rem; }
        /* Warna dummy untuk setiap bank */
        .header-maybank { background-color: #ffc72c; }
        .header-cimb { background-color: #d9232d; color: white; }
        .header-public-bank { background-color: #e31e24; color: white; }
        .header-rhb { background-color: #007bc2; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card shadow-lg payment-container">
            <div class="card-header text-center bank-header header-<?= strtolower(str_replace(' ', '-', $bank_name)) ?>">
                <h2 class="mb-0">Welcome to <?= $bank_name ?></h2>
            </div>
            <div class="card-body p-4">
                <p class="text-muted">Payment To :</p>
                <h4 class="fw-bold">RY's Tasty Creation</h4>
                <hr>
                <div class="d-flex justify-content-between">
                    <span>No. Order:</span>
                    <span class="fw-bold">#<?= $order_id ?></span>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <span class="fs-5">Total Payment:</span>
                    <span class="fs-5 fw-bold text-success">RM <?= number_format($total_amount, 2) ?></span>
                </div>
                <hr>
                <p class="text-center small text-muted">This is a payment simulation. No actual payment will be charged.</p>
                
                <!-- Borang ini akan hantar data ke process_payment.php -->
                <form action="process_payment.php" method="POST" class="d-grid">
                    <input type="hidden" name="order_id" value="<?= $order_id ?>">
                    <input type="hidden" name="total_amount" value="<?= $total_amount ?>">
                    <button type="submit" class="btn btn-primary btn-lg">Confirm & Pay</button>
                </form>
                 <div class="text-center mt-3">
                     <a href="payment.php?order_id=<?= $order_id ?>" class="btn btn-link text-danger btn-sm">Batal</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>