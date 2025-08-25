<?php
session_start();
include '../DBConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../LogIn.php');
    exit();
}

// Redirect if no recipeid
if (!isset($_GET['recipeid'])) {
    header('Location: adminuser.php');
    exit();
}

$recipeid = $_GET['recipeid'];

$db = new DBConnection();
$conn = $db->getConnection();

// Fetch recipe
$recipeQuery = "SELECT recipe.*, user.username FROM recipe JOIN user ON recipe.id = user.id WHERE recipe.id = $recipeid";
$recipeResult = $conn->query($recipeQuery);

if ($recipeResult->num_rows == 0) {
    echo "Recipe not found.";
    exit();
}

$recipe = $recipeResult->fetch_assoc();

// Fetch ingredients
$ingredientQuery = "SELECT ingredient.ingredientname, recipeingredient.Quantity, recipeingredient.unit 
                    FROM recipeingredient 
                    JOIN ingredient ON recipeingredient.ingredientid = ingredient.ingredientid
                    WHERE recipeingredient.recipeid = $recipeid";
$ingredientResult = $conn->query($ingredientQuery);

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
    $conn->query("DELETE FROM recipe WHERE id = $recipeid");
    header('Location: adminuser.php');
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View Recipe</title>
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
    <button type="button" onclick="window.history.back()">Back</button>
    <h2>View Recipe: <?php echo htmlspecialchars($recipe['recipename']); ?></h2>
    <p style="text-align: center; font-weight: bold; color: #555;">Created by: <?php echo htmlspecialchars($recipe['username']); ?></p>

    <?php if ($media) {
            $mediaData = base64_encode($media['mediafile']);
            $mediaType = $media['mediatype'];
            echo "<div style='text-align: center; margin-bottom: 10px;'>
                <img src='data:$mediaType;base64,$mediaData' alt='Recipe Image' style='max-width: 150px; display: inline-block;'>
            </div>";
        } ?>
        
    <form method="POST">

        <label for="title">Title:</label>
        <input type="text" id="title" value="<?php echo htmlspecialchars($recipe['recipename']); ?>" disabled />

        <label>Ingredients:</label>
        <table style="width: 100%; border-collapse: collapse;" border="1">
            <thead>
                <tr>
                    <th>Ingredient Name</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ingredients as $ingredient): ?>
                    <tr>
                        <td><input type="text" value="<?php echo htmlspecialchars($ingredient['ingredientname']); ?>" disabled /></td>
                        <td><input type="number" step="0.01" value="<?php echo htmlspecialchars($ingredient['Quantity']); ?>" disabled /></td>
                        <td><input type="text" value="<?php echo htmlspecialchars($ingredient['Unit']); ?>" disabled /></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <label>Instructions:</label>
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

        <label>Category:</label>
        <input type="text" value="<?php echo htmlspecialchars($recipe['category']); ?>" disabled />

        <label>Visibility:</label>
        <input type="text" value="<?php echo htmlspecialchars($recipe['visibility']); ?>" disabled />

        <label>Serving Quantity:</label>
        <input type="number" value="<?php echo htmlspecialchars($recipe['servingquantity']); ?>" disabled />

        <h3>Optional Nutritional Information</h3>

        <label>Total Calories:</label>
        <input type="number" value="<?php echo htmlspecialchars($recipe['totalcalories']); ?>" disabled />

        <label>Total Protein (g):</label>
        <input type="number" value="<?php echo htmlspecialchars($recipe['totalprotein']); ?>" disabled />

        <label>Total Carbs (g):</label>
        <input type="number" value="<?php echo htmlspecialchars($recipe['totalcarbs']); ?>" disabled />

        <label>Total Fat (g):</label>
        <input type="number" value="<?php echo htmlspecialchars($recipe['totalfat']); ?>" disabled />

    </form>
</main>

</body>
</html>
