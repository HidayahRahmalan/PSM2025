<?php
session_start();
include 'DBConnection.php';

if (!isset($_SESSION['username'])) {
    header('Location: LogIn.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_SESSION['id']; // ✅ Fix: Retrieve user ID
    $mealId = $_POST['mealid']; // ✅ Fix: Retrieve meal ID from the form

    $db = new DBConnection();
    $conn = $db->getConnection();

    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        // ✅ Handle delete action
        // Delete from mealplanrecipe first
        $deleteRecipes = $conn->prepare("DELETE FROM mealplanrecipe WHERE mealid = ?");
        $deleteRecipes->bind_param("i", $mealId);
        $deleteRecipes->execute();
        $deleteRecipes->close();

        // Then delete the meal plan
        $deleteMeal = $conn->prepare("DELETE FROM mealplan WHERE mealid = ? AND id = ?");
        $deleteMeal->bind_param("ii", $mealId, $id);
        $deleteMeal->execute();
        $deleteMeal->close();

        $conn->close();
        header('Location: MealPlan.php');
        exit();
    }

    // ✅ Continue with update process if action is not delete
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $selectedRecipes = isset($_POST['recipes']) ? $_POST['recipes'] : [];

    if (!empty($title) && !empty($selectedRecipes)) {
        // Update mealplan
        $updateMeal = $conn->prepare("UPDATE mealplan SET title = ?, description = ? WHERE mealid = ? AND id = ?");
        $updateMeal->bind_param("ssii", $title, $description, $mealId, $id);
        $updateMeal->execute();
        $updateMeal->close();

        // Remove old recipes
        $deleteOld = $conn->prepare("DELETE FROM mealplanrecipe WHERE mealid = ?");
        $deleteOld->bind_param("i", $mealId);
        $deleteOld->execute();
        $deleteOld->close();

        // Remove duplicate recipe IDs
        $uniqueRecipes = array_unique($selectedRecipes);

        // Insert new selected recipes
        $insertRecipe = $conn->prepare("INSERT INTO mealplanrecipe (mealid, recipeid) VALUES (?, ?)");
        foreach ($uniqueRecipes as $recipeId) {
            $insertRecipe->bind_param("ii", $mealId, $recipeId);
            $insertRecipe->execute();
        }
        $insertRecipe->close();

        $conn->close();
        header('Location: MealPlan.php');
        exit();
    } else {
        $error = "Please provide a title and select at least one recipe.";
    }
}
?>
