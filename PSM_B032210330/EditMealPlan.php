<?php
session_start();
include 'DBConnection.php';

if (!isset($_SESSION['username'])) {
    header('Location: LogIn.php');
    exit();
}

$id = $_SESSION['id'];

if (!isset($_GET['mealid'])) {
    header('Location: MealPlan.php');
    exit();
}

$mealId = $_GET['mealid'];
$db = new DBConnection();
$conn = $db->getConnection();

// Fetch meal plan details including totalmealcalorie
$mealQuery = $conn->prepare("SELECT title, description, totalmealcalorie FROM mealplan WHERE mealid = ? AND id = ?");
$mealQuery->bind_param("ii", $mealId, $id);
$mealQuery->execute();
$mealResult = $mealQuery->get_result();

if ($mealResult->num_rows === 0) {
    echo "Meal Plan not found.";
    exit();
}

$meal = $mealResult->fetch_assoc();

// Fetch user's saved recipes with calories
$recipeQuery = $conn->prepare("
    SELECT r.recipeid, r.recipename, r.totalcalories
    FROM savedrecipe sr
    JOIN recipe r ON sr.recipeid = r.recipeid
    WHERE sr.id = ?
");
$recipeQuery->bind_param("i", $id);
$recipeQuery->execute();
$recipeResult = $recipeQuery->get_result();
$recipes = $recipeResult->fetch_all(MYSQLI_ASSOC);
$recipeQuery->close();

// Fetch currently selected recipes
$currentQuery = $conn->prepare("SELECT recipeid FROM mealplanrecipe WHERE mealid = ?");
$currentQuery->bind_param("i", $mealId);
$currentQuery->execute();
$currentResult = $currentQuery->get_result();

$currentRecipes = [];
while ($row = $currentResult->fetch_assoc()) {
    $currentRecipes[] = $row['recipeid'];
}
$currentQuery->close();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $selectedRecipes = isset($_POST['recipes']) ? $_POST['recipes'] : [];
    $totalMealCalorie = isset($_POST['total_meal_calorie']) ? floatval($_POST['total_meal_calorie']) : 0;

    if (!empty($title) && !empty($selectedRecipes)) {
        // Update mealplan
        $updateMeal = $conn->prepare("UPDATE mealplan SET title = ?, description = ?, totalmealcalorie = ? WHERE mealid = ? AND id = ?");
        $updateMeal->bind_param("ssdii", $title, $description, $totalMealCalorie, $mealId, $id);
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

$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Meal Plan - NutriEats</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

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
            max-height: 60px;
            display: block;
        }

        .site-title {
            color: #b8860b;
            font-family: 'Libre Baskerville', serif;
            font-size: 30px;
            font-weight: bold;
            user-select: none;
        }

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

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #333;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        label {
            font-weight: bold;
        }

        input[type="text"],
        textarea,
        select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 100%;
        }

        button {
            padding: 10px;
            background-color: #b8860b;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: #212226;
            font-weight: 700;
        }

        button:hover {
            background-color: #daaa20;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
        }

        .error {
            color: red;
            text-align: center;
            font-weight: bold;
        }

        .recipe-group {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <header class="header">
        <a href="Homepage.php" class="logo-container">
            <img src="assets/NutriEats.png" alt="NutriEats Logo" class="logo" />
            <span class="site-title">NutriEats</span>
        </a>
        <nav class="nav-links">
            <a href="Homepage.php"><i class="fa-solid fa-home" style="margin-right: 5px;"></i> Home</a>
            <a href="MyRecipe.php"><i class="fa-solid fa-book" style="margin-right: 5px;"></i> My Recipes</a>
            <a href="MealPlan.php"><i class="fa-solid fa-calendar-check" style="margin-right: 5px;"></i> Meal Plan</a>
            <a href="Groceries.php"><i class="fa-solid fa-cart-shopping icon-spacing"></i> Groceries</a>
            <a href="Profile.php"><i class="fa-solid fa-user icon-spacing"></i> Profile</a>
            <a href="index.php"><i class="fa-solid fa-sign-out icon-spacing"></i> Sign Out</a>
        </nav>
    </header>

    <div class="container">
        <h2>Edit Meal Plan</h2>

        <?php if (!empty($error)): ?>
            <p class="error"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form method="POST" action="UpdateMealPlan.php?mealid=<?php echo $mealId; ?>">
            <input type="hidden" name="mealid" value="<?php echo $mealId; ?>">   
            <label for="title">Meal Plan Title:</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($meal['title']); ?>" required>

            <label for="description">Meal Plan Description:</label>
            <textarea id="description" name="description" rows="4" required><?php echo htmlspecialchars($meal['description']); ?></textarea>

            <label>Select Recipes:</label>
            <div id="recipeContainer">
                <?php foreach ($currentRecipes as $recipeId): ?>
                    <div class="recipe-group">
                    <select name="recipes[]" class="recipe-select" onchange="updateTotalCalorie()" required>
                        <option value="">-- Select Recipe --</option>
                        <?php foreach ($recipes as $recipe): ?>
                            <option value="<?php echo $recipe['recipeid']; ?>" data-calorie="<?php echo $recipe['totalcalories']; ?>" <?php echo $recipe['recipeid'] == $recipeId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($recipe['recipename']) . " - " . $recipe['totalcalories'] . " kcal"; ?>
                            </option>
                        <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="removeRecipe(this)">Remove</button>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="button" onclick="addRecipe()">Add Another Recipe</button>
           
            <h3>Total Meal Calorie: <span id="totalCalorieDisplay"><?php echo $meal['totalmealcalorie']; ?></span> kcal</h3>
            <input type="hidden" name="total_meal_calorie" id="totalMealCalorie" value="<?php echo $meal['totalmealcalorie']; ?>">

            <div class="button-group">
                <button type="submit">Update</button>
                <button type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to delete this meal plan?');">Delete Meal Plan</button>
                <button type="button" onclick="window.location.href='MealPlan.php'">Cancel</button>
            </div>
        </form>
    </div>

    <script>
    const recipes = <?php echo json_encode($recipes); ?>;

    function addRecipe() {
        const recipeContainer = document.getElementById('recipeContainer');

        const newRecipeGroup = document.createElement('div');
        newRecipeGroup.classList.add('recipe-group');

        const select = document.createElement('select');
        select.name = 'recipes[]';
        select.classList.add('recipe-select');
        select.required = true;
        select.onchange = updateTotalCalorie;

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Select Recipe --';
        select.appendChild(defaultOption);

        recipes.forEach(recipe => {
            const option = document.createElement('option');
            option.value = recipe.recipeid;
            option.textContent = `${recipe.recipename} - ${recipe.totalcalories} kcal`;
            option.setAttribute('data-calorie', recipe.totalcalories);
            select.appendChild(option);
        });

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.textContent = 'Remove';
        removeButton.onclick = function() {
            removeRecipe(removeButton);
        };

        newRecipeGroup.appendChild(select);
        newRecipeGroup.appendChild(removeButton);
        recipeContainer.appendChild(newRecipeGroup);
    }

    function removeRecipe(button) {
        button.parentElement.remove();
        updateTotalCalorie();
    }

    function updateTotalCalorie() {
        let total = 0;
        const selects = document.querySelectorAll('.recipe-select');

        selects.forEach(select => {
            const selectedOption = select.options[select.selectedIndex];
            const calorie = parseFloat(selectedOption.getAttribute('data-calorie')) || 0;
            total += calorie;
        });

        document.getElementById('totalCalorieDisplay').textContent = total.toFixed(2);
        document.getElementById('totalMealCalorie').value = total.toFixed(2);
    }

    // Initial calorie update
    window.onload = updateTotalCalorie;
</script>
</body>

</html>
