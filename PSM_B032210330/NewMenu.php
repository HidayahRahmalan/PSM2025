<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: LogIn.php");
    exit();
}
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>New Recipe - NutriEats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            background-color: #212226;
            padding: 0 20px;
            position: sticky;
            top: 0;
            z-index: 1000; /* ensures it stays on top of other elements */
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo {
            max-height: 60px; /* Adjust logo size */
            display: block;
        }

        .site-title {
            color: #b8860b; /* Darker gold color */
            font-family: 'Libre Baskerville', serif;
            font-size: 30px;
            font-weight: bold;
            user-select: none;
        }
        
        /* Navigation Links */
        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }

        /* Main Content Styles */
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: 600;
            color: #333;
        }

        input[type="text"],
        input[type="file"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus,
        input[type="file"]:focus,
        input[type="number"]:focus,
        select:focus,
        textarea:focus {
            border-color: #b8860b;
        }

        button {
            padding: 12px 20px;
            background-color: #b8860b; /* Darker gold color */
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            color: #212226;
            transition: background-color 0.3s ease;
            margin-top: 10px;
        }

        button:hover {
            background-color: #daaa20;
        }

        /* Ingredients Table */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }

        .optional-info {
            margin-top: 20px;
            color: #333;
        }
    </style>
</head>

<body>
    <header class="header">
        <a href="index.php" class="logo-container" aria-label="NutriEats Home">
            <img src="assets/NutriEats.png" alt="NutriEats Logo" class="logo" />
            <span class="site-title">NutriEats</span>
        </a>
        <nav class="nav-links">
            <a href="Homepage.php"><i class="fa-solid fa-home" style="margin-right: 5px;"></i> Home</a>
            <a href="MyRecipe.php"><i class="fa-solid fa-book" style="margin-right: 5px;"></i> My Recipes</a>
            <a href="MealPlan.php"><i class="fa-solid fa-calendar-check" style="margin-right: 5px;"></i> Meal Plan</a>
            <a href="Groceries.php"><i class="fa-solid fa-cart-shopping icon-spacing"></i> Groceries</a>
            <a href="Profile.php"><i class="fa-solid fa-user icon-spacing"></i> Profile</a>
            <a href="index.php"><i class= "fa-solid fa-sign-out icon-spacing"></i> Sign Out</a>
        </nav>
    </header>

    <main class="container">
        <button type="button" onclick="window.location.href='Homepage.php'">Back</button>
        <h2>New Recipe</h2>
        <form id="newRecipeForm" method="POST" enctype="multipart/form-data" action="RecipeConfirmation.php" novalidate>
            <label for="foodThumbnail">Food Thumbnail:</label>
            <input type="file" id="foodThumbnail" name="foodThumbnail" accept=".jpg, .jpeg, .png" required />

            <label for="title">Title:</label>
            <input type="text" id="title" name="title" required />

            <label for="ingredients">Ingredients:</label>
            <table id="ingredientsTable">
                <thead>
                    <tr>
                        <th>Ingredient Name</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><input type="text" name="ingredientName[]" placeholder="Ingredient Name" required /></td>
                        <td><input type="number" name="ingredientQuantity[]" placeholder="Quantity" required /></td>
                        <td><input type="text" name="ingredientUnit[]" placeholder="Unit (e.g., g, ml, cups)" required /></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" onclick="addIngredient()">Add Another Ingredient</button>

            <label for="instructions">Instructions:</label>
            <textarea id="instructions" name="instructions" rows="4" required></textarea>

            <label for="category">Category:</label>
            <textarea id="category" name="category" placeholder="Category (e.g., Keto, Vegetarian, Gluten-Free)" required></textarea>

            <label for="visibility">Visibility:</label>
            <select id="visibility" name="visibility" required>
                <option value="public">Public</option>
                <option value="private">Private</option>
            </select>

            <label for="servingQuantity">Serving Quantity:</label>
            <input type="number" id="servingQuantity" name="servingQuantity" required />

            <div class="optional-info">
                <h3>Optional Nutritional Information</h3>
                <label for="totalCalories">Total Calories:</label>
                <input type="number" id="totalCalories" name="totalCalories" required/>

                <label for="totalProtein">Total Protein (g):</label>
                <input type="number" id="totalProtein" name="totalProtein" />

                <label for="totalCarbs">Total Carbs (g):</label>
                <input type="number" id="totalCarbs" name="totalCarbs" />

                <label for="totalFat">Total Fat (g):</label>
                <input type="number" id="totalFat" name="totalFat" />
            </div>

            <button type="submit">Submit</button>
            <button type="button" onclick="window.location.href='MyRecipe.php'">Cancel</button>
        </form>
    </main>

    <script>
        function addIngredient() {
            const ingredientsTable = document.getElementById('ingredientsTable').getElementsByTagName('tbody')[0];
            const newRow = ingredientsTable.insertRow();
            newRow.innerHTML = `
                <td><input type="text" name="ingredientName[]" placeholder="Ingredient Name" required /></td>
                <td><input type="number" name="ingredientQuantity[]" placeholder="Quantity" required /></td>
                <td><input type="text" name="ingredientUnit[]" placeholder="Unit (e.g., g, ml, cups)" required /></td>
            `;
        }


    </script>
</body>
</html>
