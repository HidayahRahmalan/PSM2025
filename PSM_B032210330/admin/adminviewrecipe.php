<?php
session_start();
include '../DBConnection.php';

if (!isset($_SESSION['username'])) {
    header('Location: ../LogIn.php');
    exit();
}

if (!isset($_GET['recipeid'])) {
    header('Location: adminrecipe.php');
    exit();
}

$recipeid = $_GET['recipeid'];

$db = new DBConnection();
$conn = $db->getConnection();

// Fetch recipe and user details
$recipeQuery = "SELECT r.*, u.username FROM recipe r LEFT JOIN user u ON r.id = u.id WHERE r.recipeid = ?";
$stmt = $conn->prepare($recipeQuery);
$stmt->bind_param('i', $recipeid);
$stmt->execute();
$recipeResult = $stmt->get_result();

if ($recipeResult->num_rows == 0) {
    echo "Recipe not found.";
    exit();
}

$recipe = $recipeResult->fetch_assoc();

// Fetch ingredients
$ingredientQuery = "SELECT i.ingredientname, ri.Quantity, ri.Unit 
                    FROM recipeingredient ri 
                    JOIN ingredient i ON ri.ingredientid = i.ingredientid 
                    WHERE ri.recipeid = ?";
$stmt = $conn->prepare($ingredientQuery);
$stmt->bind_param('i', $recipeid);
$stmt->execute();
$ingredientResult = $stmt->get_result();

$ingredients = [];
while ($row = $ingredientResult->fetch_assoc()) {
    $ingredients[] = $row;
}

// Fetch media
$mediaQuery = "SELECT * FROM media WHERE recipeid = ? LIMIT 1";
$stmt = $conn->prepare($mediaQuery);
$stmt->bind_param('i', $recipeid);
$stmt->execute();
$mediaResult = $stmt->get_result();

$media = $mediaResult->fetch_assoc();

// Handle delete
if (isset($_POST['delete'])) {
    // Delete media
    $conn->query("DELETE FROM media WHERE recipeid = $recipeid");
    // Delete ingredients mapping
    $conn->query("DELETE FROM recipe_ingredient WHERE recipeid = $recipeid");
    // Delete recipe
    $conn->query("DELETE FROM recipe WHERE recipeid = $recipeid");

    header('Location: adminrecipe.php');
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - View Recipe</title>
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

        button {
            padding: 8px 16px;
            margin: 5px;
            background-color: #212226;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            background-color: #555;
        }

        .delete-btn {
            background-color: #ff4d4d;
        }

        h3 {
            text-align: center;
        }

        
    </style>
</head>
<body>
<header class="header">
    <a href="adminpage.php" class="logo-container" aria-label="NutriEats Home">
        <img src="../assets/NutriEats.png" alt="NutriEats Logo" class="logo" />
        <span class="site-title">NutriEats - Admin Dashboard</span>
    </a>
    <nav class="nav-links">
        <a href="adminpage.php"><i class="fa-solid fa-home" style="margin-right: 5px;"></i> Home</a>
        <a href="adminuser.php"><i class="fa-solid fa-user" style="margin-right: 5px;"></i> Users</a>
        <a href="adminrecipe.php"><i class="fa-solid fa-calendar-check" style="margin-right: 5px;"></i> Recipes</a>
        <a href="../index.php"><i class="fa-solid fa-sign-out icon-spacing"></i> Sign Out</a>
    </nav>
</header>

<main class="container">
    <button type="button" onclick="window.location.href='adminrecipe.php'">Back</button>
    <h2>View Recipe: <?php echo htmlspecialchars($recipe['recipename']); ?></h2>
    <p style="text-align: center; font-weight: bold; color: #555;">Created by: <?php echo htmlspecialchars($recipe['username']); ?></p>

    <form id="viewRecipeForm" method="POST" novalidate>
        <?php if ($media) {
            $mediaData = base64_encode($media['mediafile']);
            $mediaType = $media['mediatype'];
            echo "<div style='text-align: center; margin-bottom: 10px;'>
                <img src='data:$mediaType;base64,$mediaData' alt='Recipe Image' style='max-width: 150px; display: inline-block;'>
            </div>";
        } ?>

        <label for="title">Title:</label>
        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($recipe['recipename']); ?>" disabled />

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
                <?php if (!empty($ingredients)) {
                    foreach ($ingredients as $ingredient) { ?>
                        <tr>
                            <td><input type="text" value="<?php echo htmlspecialchars($ingredient['ingredientname']); ?>" disabled /></td>
                            <td><input type="number" step="0.01" value="<?php echo htmlspecialchars($ingredient['Quantity']); ?>" disabled /></td>
                            <td><input type="text" value="<?php echo htmlspecialchars($ingredient['Unit']); ?>" disabled /></td>
                        </tr>
                    <?php }
                } else { ?>
                    <tr>
                        <td><input type="text" placeholder="Ingredient Name" disabled /></td>
                        <td><input type="number" placeholder="Quantity" disabled /></td>
                        <td><input type="text" placeholder="Unit" disabled /></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>

        <label for="instructions">Instructions:</label>
        <ol>
            <?php
            $instructionsArray = preg_split('/\r\n|\r|\n/', $recipe['instructions']);
            foreach ($instructionsArray as $step) {
                if (!empty(trim($step))) {
                    echo '<li>' . htmlspecialchars($step) . '</li>';
                }
            }
            ?>
        </ol>

        <label for="category">Category:</label>
        <textarea id="category" name="category" disabled><?php echo htmlspecialchars($recipe['category']); ?></textarea>

        <label for="visibility">Visibility:</label>
        <select id="visibility" name="visibility" disabled>
            <option value="public" <?php if ($recipe['visibility'] == 'public') echo 'selected'; ?>>Public</option>
            <option value="private" <?php if ($recipe['visibility'] == 'private') echo 'selected'; ?>>Private</option>
        </select>

        <label for="servingQuantity">Serving Quantity:</label>
        <input type="number" id="servingQuantity" name="servingQuantity" value="<?php echo htmlspecialchars($recipe['servingquantity']); ?>" disabled />

        <div class="optional-info">
            <h3>Optional Nutritional Information</h3>
            <label for="totalCalories">Total Calories:</label>
            <input type="number" id="totalCalories" name="totalCalories" value="<?php echo htmlspecialchars($recipe['totalcalories']); ?>" disabled />

            <label for="totalProtein">Total Protein (g):</label>
            <input type="number" id="totalProtein" name="totalProtein" value="<?php echo htmlspecialchars($recipe['totalprotein']); ?>" disabled />

            <label for="totalCarbs">Total Carbs (g):</label>
            <input type="number" id="totalCarbs" name="totalCarbs" value="<?php echo htmlspecialchars($recipe['totalcarbs']); ?>" disabled />

            <label for="totalFat">Total Fat (g):</label>
            <input type="number" id="totalFat" name="totalFat" value="<?php echo htmlspecialchars($recipe['totalfat']); ?>" disabled />
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px;">
            <button type="submit" name="delete" class="delete-btn" onclick="return confirm('Are you sure you want to delete this recipe?');">Delete Recipe</button>
        </div>
    </form>
</main>

</body>
</html>
