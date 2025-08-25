<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: LogIn.php");
    exit();
}
$username = $_SESSION['username'];

if (!isset($_GET['recipeid'])) {
    header("Location: MyRecipe.php");
    exit();
}

$recipeID = $_GET['recipeid'];

include 'DBConnection.php';
$db = new DBConnection();
$conn = $db->getConnection();

// Fetch Recipe
$stmt = $conn->prepare("SELECT * FROM recipe WHERE recipeid = ?");
$stmt->bind_param("i", $recipeID);
$stmt->execute();
$recipe = $stmt->get_result()->fetch_assoc();

// Fetch Ingredients
$stmt = $conn->prepare("SELECT ri.*, i.ingredientname FROM recipeingredient ri JOIN ingredient i ON ri.IngredientID = i.IngredientID WHERE ri.RecipeID = ?");
$stmt->bind_param("i", $recipeID);
$stmt->execute();
$ingredientsResult = $stmt->get_result();

$ingredients = [];
if ($ingredientsResult->num_rows > 0) {
    while ($row = $ingredientsResult->fetch_assoc()) {
        $ingredients[] = $row;
    }
}


// Fetch Media
$stmt = $conn->prepare("SELECT * FROM media WHERE recipeid = ?");
$stmt->bind_param("i", $recipeID);
$stmt->execute();
$media = $stmt->get_result()->fetch_assoc();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Edit Recipe - NutriEats</title>
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
        <a href="Homepage.php" class="logo-container" aria-label="NutriEats Home">
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
        <button type="button" onclick="window.location.href='MyRecipe.php'">Back</button>
        <h2>Edit Recipe: <?php echo htmlspecialchars($recipe['recipename']); ?></h2>

        <form id="editRecipeForm" method="POST" enctype="multipart/form-data" action="updateRecipe.php" novalidate>
            <input type="hidden" name="recipeid" value="<?php echo $recipeID; ?>">

            <?php if ($media) {
                $mediaData = base64_encode($media['mediafile']);
                $mediaType = $media['mediatype'];
                echo "<div style='text-align: center; margin-bottom: 10px;'>
                <img src='data:$mediaType;base64,$mediaData' alt='Recipe Image' style='max-width: 150px; display: inline-block;'>
                </div>";
            } ?>

            <label for="foodThumbnail">Change Food Thumbnail:</label>
            <input type="file" id="foodThumbnail" name="foodThumbnail" accept=".jpg, .jpeg, .png" />

            <label for="title">Title:</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($recipe['recipename']); ?>" required />

            <label for="ingredients">Ingredients:</label>
            <table id="ingredientsTable">
                <thead>
                    <tr>
                        <th>Ingredient Name</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($ingredients)) {
                        foreach ($ingredients as $ingredient) { ?>
                            <tr>
                                <input type="hidden" name="recipeIngredientID[]" value="<?php echo $ingredient['RecipeIngredientID']; ?>" />
                                <input type="hidden" name="ingredientID[]" value="<?php echo $ingredient['IngredientID']; ?>" />
                                <td><input type="text" name="ingredientName[]" value="<?php echo htmlspecialchars($ingredient['ingredientname']); ?>" required /></td>
                                <td><input type="number" step="0.01" name="ingredientQuantity[]" value="<?php echo htmlspecialchars($ingredient['Quantity']); ?>" required /></td>
                                <td><input type="text" name="ingredientUnit[]" value="<?php echo htmlspecialchars($ingredient['Unit']); ?>" required /></td>
                                <td><button type="button" onclick="removeRow(this)">Remove</button></td>
                            </tr>
                        <?php }
                    } else { ?>
                        <tr>
                            <td><input type="text" name="ingredientName[]" placeholder="Ingredient Name" required /></td>
                            <td><input type="number" name="ingredientQuantity[]" placeholder="Quantity" required /></td>
                            <td><input type="text" name="ingredientUnit[]" placeholder="Unit (e.g., g, ml, cups)" required /></td>
                            <td><button type="button" onclick="removeRow(this)">Remove</button></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
            <button type="button" onclick="addIngredient()">Add Another Ingredient</button>

            <label for="instructions">Instructions:</label>
            <textarea id="instructions" name="instructions" rows="4" required><?php echo htmlspecialchars($recipe['instructions']); ?></textarea>

            <label for="category">Category:</label>
            <textarea id="category" name="category"><?php echo htmlspecialchars($recipe['category']); ?></textarea>

            <label for="visibility">Visibility:</label>
            <select id="visibility" name="visibility" required>
                <option value="public" <?php if ($recipe['visibility'] == 'public') echo 'selected'; ?>>Public</option>
                <option value="private" <?php if ($recipe['visibility'] == 'private') echo 'selected'; ?>>Private</option>
            </select>

            <label for="servingQuantity">Serving Quantity:</label>
            <input type="number" id="servingQuantity" name="servingQuantity" value="<?php echo htmlspecialchars($recipe['servingquantity']); ?>" required />

            <div class="optional-info">
                <h3>Optional Nutritional Information</h3>
                <label for="totalCalories">Total Calories:</label>
                <input type="number" id="totalCalories" name="totalCalories" value="<?php echo htmlspecialchars($recipe['totalcalories']); ?>" required />

                <label for="totalProtein">Total Protein (g):</label>
                <input type="number" id="totalProtein" name="totalProtein" value="<?php echo htmlspecialchars($recipe['totalprotein']); ?>" />

                <label for="totalCarbs">Total Carbs (g):</label>
                <input type="number" id="totalCarbs" name="totalCarbs" value="<?php echo htmlspecialchars($recipe['totalcarbs']); ?>" />

                <label for="totalFat">Total Fat (g):</label>
                <input type="number" id="totalFat" name="totalFat" value="<?php echo htmlspecialchars($recipe['totalfat']); ?>" />
            </div>

            <button type="submit" name="action" value="save">Save</button>
            <button type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to delete this recipe?');">Delete</button>
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
            <td><button type="button" onclick="removeRow(this)">Remove</button></td>
        `;
    }

    function removeRow(button) {
        // Remove the row containing the clicked button
        button.closest('tr').remove();
    }
</script>

</body>
</html>