<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: LogIn.php");
    exit();
}

include 'DBConnection.php';

$db = new DBConnection();
$conn = $db->getConnection();

$username = $_SESSION['username'];

// Get user id
$userQuery = $conn->prepare("SELECT id FROM user WHERE username = ?");
$userQuery->bind_param("s", $username);
$userQuery->execute();
$userResult = $userQuery->get_result();
if ($userResult->num_rows === 0) {
    die("User not found.");
}
$userRow = $userResult->fetch_assoc();
$userID = $userRow['id'];

// Retrieve form data
$title = $_POST['title'];
$instructions = $_POST['instructions'];
$category = $_POST['category'];
$visibility = $_POST['visibility'];
$servingQuantity = $_POST['servingQuantity'];
$totalCalories = $_POST['totalCalories'];
$totalProtein = $_POST['totalProtein'];
$totalCarbs = $_POST['totalCarbs'];
$totalFat = $_POST['totalFat'];

$ingredientNames = $_POST['ingredientName'];
$ingredientQuantities = $_POST['ingredientQuantity'];
$ingredientUnits = $_POST['ingredientUnit'];

$ingredientIDs = [];

for ($i = 0; $i < count($ingredientNames); $i++) {
    $ingredientName = trim($ingredientNames[$i]);
    $quantity = $ingredientQuantities[$i];
    $unit = $ingredientUnits[$i];

    // Skip empty ingredient names
    if (empty($ingredientName)) {
        continue;
    }

    // Check if ingredient already exists
    $checkQuery = $conn->prepare("SELECT ingredientid FROM ingredient WHERE ingredientname = ?");
    $checkQuery->bind_param("s", $ingredientName);
    $checkQuery->execute();
    $checkResult = $checkQuery->get_result();

    if ($checkResult->num_rows > 0) {
        // Ingredient exists
        $existingRow = $checkResult->fetch_assoc();
        $ingredientID = $existingRow['ingredientid'];
    } else {
        // Insert new ingredient
        $insertIngredient = $conn->prepare("INSERT INTO ingredient (ingredientname) VALUES (?)");
        $insertIngredient->bind_param("s", $ingredientName);
        $insertIngredient->execute();
        $ingredientID = $insertIngredient->insert_id;
    }

    // Save the ingredient ID with quantity and unit for later linking
    $ingredientIDs[] = [
        'id' => $ingredientID,
        'quantity' => $quantity,
        'unit' => $unit
    ];
}

// Insert recipe
$insertRecipe = $conn->prepare("INSERT INTO recipe (id, recipename, instructions, category, visibility, servingquantity, totalcalories, totalprotein, totalcarbs, totalfat) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$insertRecipe->bind_param("issssiiiii", $userID, $title, $instructions, $category, $visibility, $servingQuantity, $totalCalories, $totalProtein, $totalCarbs, $totalFat);
$insertRecipe->execute();
$recipeID = $insertRecipe->insert_id;

// Link ingredients to recipe
foreach ($ingredientIDs as $item) {
    $ingredientID = $item['id'];
    $quantity = $item['quantity'];
    $unit = $item['unit'];

    $insertLink = $conn->prepare("INSERT INTO recipeingredient (recipeid, ingredientid, quantity, unit) VALUES (?, ?, ?, ?)");
    $insertLink->bind_param("iiis", $recipeID, $ingredientID, $quantity, $unit);
    $insertLink->execute();
}

// Insert media (thumbnail)
if (isset($_FILES['foodThumbnail']) && $_FILES['foodThumbnail']['error'] == 0) {
    $fileData = file_get_contents($_FILES['foodThumbnail']['tmp_name']);
    $fileName = $_FILES['foodThumbnail']['name'];
    $fileType = $_FILES['foodThumbnail']['type'];

    $insertMedia = $conn->prepare("INSERT INTO media (recipeid, mediasize, mediatype, mediafile) VALUES (?, ?, ?, ?)");
    $null = NULL;
    $insertMedia->bind_param("issb", $recipeID, $fileName, $fileType, $null);
    $insertMedia->send_long_data(3, $fileData);
    $insertMedia->execute();
}

$conn->close();
header("Location: MyRecipe.php?success=1");
exit();
?>
