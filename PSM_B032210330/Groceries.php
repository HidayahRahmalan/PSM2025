<?php
session_start();
include 'DBConnection.php';

if (!isset($_SESSION['username'])) {
    header('Location: LogIn.php');
    exit();
}

$username = $_SESSION['username'];
$id = $_SESSION['id'];

$db = new DBConnection();
$conn = $db->getConnection();

$searchRecipe = isset($_GET['search_recipe']) ? '%' . $_GET['search_recipe'] . '%' : '%';
$searchMeal = isset($_GET['search_meal']) ? '%' . $_GET['search_meal'] . '%' : '%';

// Fetch Saved Recipes
$recipeQuery = $conn->prepare("
    SELECT r.recipeid, r.recipename, u.fullName AS creator, i.ingredientname, ri.Quantity, ri.Unit
    FROM savedrecipe sr
    JOIN recipe r ON sr.recipeID = r.recipeid
    JOIN user u ON r.id = u.id
    JOIN recipeingredient ri ON r.recipeid = ri.RecipeID
    JOIN ingredient i ON ri.IngredientID = i.ingredientid
    WHERE sr.id = ? AND r.recipename LIKE ?
");
$recipeQuery->bind_param("is", $id, $searchRecipe);
$recipeQuery->execute();
$recipeResult = $recipeQuery->get_result();

$savedRecipes = [];
while ($row = $recipeResult->fetch_assoc()) {
    $savedRecipes[$row['recipeid']]['recipename'] = $row['recipename'];
    $savedRecipes[$row['recipeid']]['creator'] = $row['creator'];
    $savedRecipes[$row['recipeid']]['ingredients'][] = $row['ingredientname'] . " - " . $row['Quantity'] . " " . $row['Unit'];
}

// Fetch Meal Plans
$mealQuery = $conn->prepare("
    SELECT mp.mealid, mp.title, r.recipeid, r.recipename, u.fullName AS creator, i.ingredientname, ri.Quantity, ri.Unit
    FROM mealplan mp
    JOIN mealplanrecipe mpr ON mp.mealid = mpr.mealid
    JOIN recipe r ON mpr.recipeid = r.recipeid
    JOIN user u ON r.id = u.id
    JOIN recipeingredient ri ON r.recipeid = ri.RecipeID
    JOIN ingredient i ON ri.IngredientID = i.ingredientid
    WHERE mp.id = ? AND mp.title LIKE ?
");
$mealQuery->bind_param("is", $id, $searchMeal);
$mealQuery->execute();
$mealResult = $mealQuery->get_result();

$mealPlans = [];
while ($row = $mealResult->fetch_assoc()) {
    $mealPlans[$row['title']][$row['recipeid']]['recipename'] = $row['recipename'];
    $mealPlans[$row['title']][$row['recipeid']]['creator'] = $row['creator'];
    $mealPlans[$row['title']][$row['recipeid']]['ingredients'][] = $row['ingredientname'] . " - " . $row['Quantity'] . " " . $row['Unit'];
}

$mealQuery->close();
$recipeQuery->close();
$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Groceries - NutriEats</title>
    <script src="https://kit.fontawesome.com/your-kit-id.js" crossorigin="anonymous"></script>
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
            max-width: 1000px;
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
            border-bottom: 2px solid #ccc;
            padding-bottom: 5px;
        }

        .recipe-card, .meal-card {
            border: 1px solid #ccc;
            border-radius: 8px;
            padding: 15px;
            margin: 10px;
            background-color: #f9f9f9;
            cursor: pointer;
            transition: box-shadow 0.3s;
        }

        .recipe-card:hover, .meal-card:hover {
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
        }

        .recipe-grid, .meal-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .search-bar {
            margin-bottom: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }

        input[type="text"] {
            padding: 10px;
            width: 300px;
            border-radius: 4px;
            border: 1px solid #ccc;
        }

        button {
            padding: 10px 20px;
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

        .ingredients {
            margin-top: 10px;
            padding-left: 20px;
        }

        .message {
            color: red;
            font-weight: bold;
            text-align: center;
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
            <a href="index.php"><i class="fa-solid fa-sign-out icon-spacing"></i> Sign Out</a>
        </nav>
    </header>

    <div class="container">
        <section>
        <button type="button" onclick="window.location.href='Homepage.php'">Back</button>
            <h2>Saved Recipes</h2>
            <form method="GET" class="search-bar">
                <input type="text" name="search_recipe" placeholder="Search Recipes..." value="<?php echo isset($_GET['search_recipe']) ? htmlspecialchars($_GET['search_recipe']) : ''; ?>">
                <button type="submit">Search</button>
            </form>
            <?php if (empty($savedRecipes)): ?>
                <p>No saved recipes found.</p>
            <?php else: ?>
                <div class="recipe-grid">
                    <?php foreach ($savedRecipes as $recipeID => $recipeData): ?>
                        <div class="recipe-card" onclick="window.location.href='ViewRecipe.php?recipeid=<?php echo $recipeID; ?>'">
                            <h3><?php echo htmlspecialchars($recipeData['recipename']); ?></h3>
                            <p><strong>Creator:</strong> <?php echo htmlspecialchars($recipeData['creator']); ?></p>
                            <p><strong>Ingredients:</strong></p>
                            <ul class="ingredients">
                                <?php foreach ($recipeData['ingredients'] as $ingredient): ?>
                                    <li><?php echo htmlspecialchars($ingredient); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="container">
        <section>
            <h2>Meal Plans</h2>
            <form method="GET" class="search-bar">
                <input type="text" name="search_meal" placeholder="Search Meal Plans..." value="<?php echo isset($_GET['search_meal']) ? htmlspecialchars($_GET['search_meal']) : ''; ?>">
                <button type="submit">Search</button>
            </form>
            <?php if (empty($mealPlans)): ?>
                <p class="message">You don't have any meal plans created.</p>
            <?php else: ?>
                <?php foreach ($mealPlans as $mealName => $recipes): ?>
                    <div class="meal-card">
                        <h3><?php echo htmlspecialchars($mealName); ?></h3>
                        <div class="meal-grid">
                            <?php foreach ($recipes as $recipeID => $recipeData): ?>
                                <div class="recipe-card" onclick="window.location.href='ViewRecipe.php?recipeid=<?php echo $recipeID; ?>'">
                                    <h4><?php echo htmlspecialchars($recipeData['recipename']); ?></h4>
                                    <p><strong>Creator:</strong> <?php echo htmlspecialchars($recipeData['creator']); ?></p>
                                    <p><strong>Ingredients:</strong></p>
                                    <ul class="ingredients">
                                        <?php foreach ($recipeData['ingredients'] as $ingredient): ?>
                                            <li><?php echo htmlspecialchars($ingredient); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </div>
</body>

</html>
