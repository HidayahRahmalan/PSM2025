<?php
require 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get product details from the form
    $productName = $_POST['product_name'];
    $productType = $_POST['product_type'];
    $productUnit = $_POST['product_unit'];
    $productQty = $_POST['product_qty'];
    $productPrice = $_POST['product_price'];
    $productTotalQty = $_POST['product_total_qty'];

    // Handle the image upload
    $image = $_FILES['image']['tmp_name'];
    $imageData = file_get_contents($image);

    // Insert into the database
    $stmt = $conn->prepare("INSERT INTO products_sell (PRODUCT_NAME, PRODUCT_TYPE, PRODUCT_UNIT, PRODUCT_QTTY, PRODUCT_PRICE, PRODUCT_TOTAL_QTTY, PRODUCT_IMAGE) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiiss", $productName, $productType, $productUnit, $productQty, $productPrice, $productTotalQty, $imageData);
    $stmt->execute();

    echo "Product uploaded successfully!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Upload Product</title>
</head>
<body>
  <h1>Upload Product</h1>
  <form action="uploadproduct.php" method="POST" enctype="multipart/form-data">
    <label for="product_name">Product Name:</label><br>
    <input type="text" name="product_name" required><br><br>

    <label for="product_type">Product Type:</label><br>
    <input type="text" name="product_type" required><br><br>

    <label for="product_unit">Product Unit:</label><br>
    <input type="text" name="product_unit" required><br><br>

    <label for="product_qty">Product Quantity:</label><br>
    <input type="number" name="product_qty" required><br><br>

    <label for="product_price">Product Price:</label><br>
    <input type="number" name="product_price" step="0.01" required><br><br>

    <label for="product_total_qty">Total Quantity:</label><br>
    <input type="number" name="product_total_qty" required><br><br>

    <label for="image">Product Image:</label><br>
    <input type="file" name="image" required><br><br>

    <button type="submit">Upload Product</button>
  </form>
</body>
</html>
