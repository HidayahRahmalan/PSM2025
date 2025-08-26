<?php
session_start();
require 'db_connection.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'seller') {
    header("Location: login.php"); exit();
}
$item_id = $_GET['item_id'] ?? null;
if (!$item_id) { header("Location: ingredient_management.php"); exit(); }

$item_stmt = $conn->prepare("SELECT ITEM_NAME FROM item_ingredient WHERE ITEM_ID = ?");
$item_stmt->bind_param("s", $item_id);
$item_stmt->execute();
$item_name = $item_stmt->get_result()->fetch_assoc()['ITEM_NAME'];
$item_stmt->close();

$usage_stmt = $conn->prepare("SELECT p.PRODUCT_NAME, pi.PI_QTTY_REQUIRED, pi.PI_UNIT FROM product_ingredient pi JOIN products_sell p ON pi.PRODUCT_ID = p.PRODUCT_ID WHERE pi.ITEM_ID = ? ORDER BY p.PRODUCT_NAME");
$usage_stmt->bind_param("s", $item_id);
$usage_stmt->execute();
$usages = $usage_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Recipe Usage for <?= htmlspecialchars($item_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-5" style="max-width: 700px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3">Recipe Usage</h1>
            <p class="text-muted">Showing all products that use <strong><?= htmlspecialchars($item_name) ?></strong> in their recipe.</p>
        </div>
        <a href="ingredient_management.php" class="btn btn-secondary">Back to Ingredients</a>
    </div>
    <div class="card">
        <div class="card-body">
            <?php if ($usages->num_rows > 0): ?>
                <ul class="list-group">
                <?php while ($row = $usages->fetch_assoc()): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?= htmlspecialchars($row['PRODUCT_NAME']) ?>
                        <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($row['PI_QTTY_REQUIRED']) . ' ' . htmlspecialchars($row['PI_UNIT']) ?> per pack</span>
                    </li>
                <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="text-center text-muted">This ingredient is not currently used in any product recipes.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>