<?php
session_start();
include 'db_connection.php';  // Include your database connection

// Check if the user is logged in
if (!isset($_SESSION['CUST_ID'])) {
    header('Location: login.php');
    exit();
}

$customer_id = $_SESSION['CUST_ID'];  // Using CUST_ID from session
// Get customer information from the database
$query = "SELECT * FROM customer WHERE CUST_ID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $customer_id);  // Using VARCHAR for CUST_ID
$stmt->execute();
$customer_result = $stmt->get_result();
$customer = $customer_result->fetch_assoc();

// Handle account updates (name, email, phone)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update'])) {
    $name = htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8');
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars($_POST['phone'], ENT_QUOTES, 'UTF-8');

    // Update customer information in the database
    $update_query = "UPDATE customer SET CUST_NAME = ?, CUST_EMAIL = ?, CUST_PHONE = ? WHERE CUST_ID = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssss", $name, $email, $phone, $customer_id);
    $stmt->execute();

    $_SESSION['message'] = "Account updated successfully!";
    header('Location: customerAccount.php');  // Refresh page after update
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if current password matches
    if (password_verify($current_password, $customer['CUST_PASSWORD'])) {
        if ($new_password === $confirm_password) {
            // Hash the new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_password_query = "UPDATE customer SET CUST_PASSWORD = ? WHERE CUST_ID = ?";
            $stmt = $conn->prepare($update_password_query);
            $stmt->bind_param("ss", $new_password_hash, $customer_id);
            $stmt->execute();

            $_SESSION['message'] = "Password updated successfully!";
            header('Location: customerAccount.php');  // Refresh page after password change
            exit();
        } else {
            $_SESSION['message'] = "Passwords do not match!";
        }
    } else {
        $_SESSION['message'] = "Current password is incorrect!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account - RY's Tasty Creations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Your Account</h2>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-info">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <!-- Update Information Form -->
        <form method="POST">
            <h4>Update Information</h4>
            <div class="mb-3">
                <label class="form-label">Name</label>
                <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($customer['CUST_NAME'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($customer['CUST_EMAIL'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($customer['CUST_PHONE'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <button type="submit" name="update" class="btn btn-primary w-100">Update Info</button>
        </form>

        <hr>

        <!-- Change Password Form -->
        <form method="POST">
            <h4>Change Password</h4>
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            <button type="submit" name="change_password" class="btn btn-primary w-100">Change Password</button>
        </form>

        <hr>

        <!-- Display Orders for Customer -->
        <h4>Your Order History</h4>
        <table class="table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Product Name</th>
                    <th>Order Quantity</th>
                    <th>Status</th>
                    <th>Total Price</th>
                    <th>Order Date</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Fetch order history for the customer
                $order_query = "SELECT co.ORDER_ID, ps.PRODUCT_NAME, co.ORDER_QTTY, co.ORDER_PAYMENT_STATUS, co.ORDER_DATE, ps.PRODUCT_PRICE, (co.ORDER_QTTY * ps.PRODUCT_PRICE) AS total_price
                                FROM customer_order co
                                JOIN products_sell ps ON co.PRODUCT_ID = ps.PRODUCT_ID
                                WHERE co.CUST_ID = ?";
                $stmt = $conn->prepare($order_query);
                $stmt->bind_param("s", $customer_id);
                $stmt->execute();
                $order_result = $stmt->get_result();

                while ($order = $order_result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $order['ORDER_ID'] . "</td>";
                    echo "<td>" . $order['PRODUCT_NAME'] . "</td>";
                    echo "<td>" . $order['ORDER_QTTY'] . "</td>";
                    echo "<td>" . $order['ORDER_PAYMENT_STATUS'] . "</td>";
                    echo "<td>RM " . number_format($order['total_price'], 2) . "</td>";
                    echo "<td>" . $order['ORDER_DATE'] . "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>

    </div>
</body>
</html>
