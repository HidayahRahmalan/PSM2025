<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: LogIn.php");
    exit();
}

include 'DBConnection.php';
$db = new DBConnection();
$conn = $db->getConnection();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];
    $recipeID = $_POST['recipeid'];

    if ($action == "save") {
        // Update Recipe Details
        $stmt = $conn->prepare("CALL UpdateRecipe(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "issssidddd",
            $_POST['recipeid'], // Correct mapping: recipeid
            $_POST['title'],    // Title input name in your form
            $_POST['instructions'],
            $_POST['category'],
            $_POST['visibility'],
            $_POST['servingQuantity'],
            $_POST['totalCalories'],
            $_POST['totalProtein'],
            $_POST['totalCarbs'],
            $_POST['totalFat']
        );

        $stmt->execute();
        $stmt->close();

        // Handle Ingredients
        $recipeIngredientIDs = $_POST['recipeIngredientID'];
        $ingredientIDs = $_POST['ingredientID'];
        $ingredientNames = $_POST['ingredientName'];
        $ingredientQuantities = $_POST['ingredientQuantity'];
        $ingredientUnits = $_POST['ingredientUnit'];

        for ($i = 0; $i < count($ingredientNames); $i++) {
            $existingID = $recipeIngredientIDs[$i];
            $newName = $ingredientNames[$i];
            $newQuantity = $ingredientQuantities[$i];
            $newUnit = $ingredientUnits[$i];

            // Check existing ingredient info
            $checkStmt = $conn->prepare("SELECT ri.Quantity, ri.Unit, i.ingredientname
                                        FROM recipeingredient ri
                                        JOIN ingredient i ON ri.IngredientID = i.IngredientID
                                        WHERE ri.RecipeIngredientID = ?");
            $checkStmt->bind_param("i", $existingID);
            $checkStmt->execute();
            $result = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            // If new row (no RecipeIngredientID), we always insert
            if (empty($existingID)) {
                $stmt = $conn->prepare("CALL AddOrUpdateRecipeIngredient(?, ?, ?, ?)");
                $stmt->bind_param("isds", $recipeID, $newName, $newQuantity, $newUnit);
                $stmt->execute();
                $stmt->close();
            }
            // If existing row, only update if something changed
            elseif ($result && (
                $result['ingredientname'] != $newName ||
                $result['Quantity'] != $newQuantity ||
                $result['Unit'] != $newUnit
            )) {
                $stmt = $conn->prepare("CALL AddOrUpdateRecipeIngredient(?, ?, ?, ?)");
                $stmt->bind_param("isds", $recipeID, $newName, $newQuantity, $newUnit);
                $stmt->execute();
                $stmt->close();
            }
            // If no change, skip
        }

        // Update Media table
        if (isset($_FILES['foodThumbnail']) && $_FILES['foodThumbnail']['error'] == 0) {
            $mediaFile = file_get_contents($_FILES['foodThumbnail']['tmp_name']);
            $mediaType = $_FILES['foodThumbnail']['type'];
        
            // Check if media already exists for this recipe
            $checkMedia = $conn->prepare("SELECT * FROM media WHERE recipeid = ?");
            $checkMedia->bind_param("i", $recipeID);
            $checkMedia->execute();
            $mediaExists = $checkMedia->get_result()->fetch_assoc();
            $checkMedia->close();
        
            if ($mediaExists) {
                // Update existing media
                $stmt = $conn->prepare("UPDATE media SET mediafile = ?, mediatype = ? WHERE recipeid = ?");
                $null = NULL; // Fix: This must be defined
                $stmt->bind_param("bsi", $null, $mediaType, $recipeID);
                $stmt->send_long_data(0, $mediaFile);
                $stmt->execute();
                $stmt->close();
            } else {
                // Insert new media
                $stmt = $conn->prepare("INSERT INTO media (recipeid, mediafile, mediatype) VALUES (?, ?, ?)");
                $null = NULL; // Fix: This must be defined
                $stmt->bind_param("ibs", $recipeID, $null, $mediaType);
                $stmt->send_long_data(1, $mediaFile);
                $stmt->execute();
                $stmt->close();
            }
        }        
        
        header("Location: MyRecipe.php");
        exit();
    } elseif ($action == "delete") {
        // Call the DeleteRecipe procedure
        $stmt = $conn->prepare("CALL DeleteRecipe(?)");
        $stmt->bind_param("i", $recipeID);
        $stmt->execute();
        $stmt->close();

        header("Location: MyRecipe.php");
        exit();
    }
}

$conn->close();
?>
