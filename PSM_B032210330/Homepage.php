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
$userid = $_SESSION['id']; // Ensure user ID is in session

// Fetch user info
$userQuery = $conn->prepare("SELECT age, gender, height, weight, bmi, recommendedcalorie, activity_level, target_weight, dailycalorie_target FROM user WHERE id = ?");
$userQuery->bind_param("i", $userid);
$userQuery->execute();
$userResult = $userQuery->get_result();
$user = $userResult->fetch_assoc();
$userQuery->close();

$error = '';
$success = '';

// BMI Category function
function getBmiCategory($bmi)
{
    if ($bmi < 18.5) {
        return ['Underweight', 'black'];
    } elseif ($bmi >= 18.5 && $bmi <= 24.9) {
        return ['Healthy weight', 'green'];
    } elseif ($bmi >= 25 && $bmi <= 29.9) {
        return ['Overweight', 'orange'];
    } elseif ($bmi >= 30 && $bmi <= 34.9) {
        return ['Class I Obesity', 'red'];
    } elseif ($bmi >= 35 && $bmi <= 39.9) {
        return ['Class II Obesity', 'red'];
    } else {
        return ['Class III Obesity', 'red'];
    }
}

// Get BMI category
$bmiCategory = getBmiCategory($user['bmi']);

// Get user food preferences, restrictions, allergies
$userQuery = $conn->prepare("SELECT food_preferences, dietary_restrictions, allergies FROM user WHERE id = ?");
$userQuery->bind_param("i", $userid);
$userQuery->execute();
$userResult = $userQuery->get_result();
$userData = $userResult->fetch_assoc();
$userQuery->close();

$foodPreferences = array_filter(array_map('trim', explode(',', $userData['food_preferences'])));
$restrictions = array_filter(array_map('trim', explode(',', $userData['dietary_restrictions'])));
$allergies = array_filter(array_map('trim', explode(',', $userData['allergies'])));

// Handle Search
$searchInput = $_GET['searchInput'] ?? '';
$searchResults = [];
$fallbackSearch = false;

if (!empty($searchInput)) {
    // Build query with proper joins
    $query = "
        SELECT DISTINCT r.recipeid, r.recipename, r.category, r.visibility, r.id, r.totalcalories, u.username
        FROM recipe r
        LEFT JOIN user u ON r.id = u.id
        LEFT JOIN recipeingredient ri ON r.recipeid = ri.RecipeID
        LEFT JOIN ingredient i ON ri.IngredientID = i.ingredientid
        WHERE r.visibility = 'Public' AND (r.recipename LIKE ? OR r.id LIKE ? OR r.category LIKE ? OR i.ingredientname LIKE ?)
    ";

    $types = 'ssss';
    $params = ["%" . $searchInput . "%", "%" . $searchInput . "%", "%" . $searchInput . "%", "%" . $searchInput . "%"];

    // Add food preferences
    if (!empty($foodPreferences)) {
        $preferenceConditions = [];
        foreach ($foodPreferences as $preference) {
            $preferenceConditions[] = "r.category LIKE ?";
            $types .= 's';
            $params[] = "%" . $preference . "%";
        }
        $query .= " AND (" . implode(' OR ', $preferenceConditions) . ")";
    }

    // Exclude dietary restrictions
    foreach ($restrictions as $restriction) {
        $query .= " AND r.category NOT LIKE ?";
        $types .= 's';
        $params[] = "%" . $restriction . "%";
    }

    // Exclude allergies
    foreach ($allergies as $allergy) {
        $query .= " AND i.ingredientname NOT LIKE ? AND r.category NOT LIKE ?";
        $types .= 'ss'; // Two parameters: one for ingredient, one for category
        $params[] = "%" . $allergy . "%";
        $params[] = "%" . $allergy . "%";
    }

    $query .= " ORDER BY r.recipename ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $searchResults = $stmt->get_result();

    // If no results, fallback to general search but still exclude allergies and restrictions
    if ($searchResults->num_rows == 0) {
        $fallbackSearch = true;

        $fallbackQuery = "
            SELECT DISTINCT r.recipeid, r.recipename, r.category, r.visibility, r.id, u.username
            FROM recipe r
            LEFT JOIN user u ON r.id = u.id
            LEFT JOIN recipeingredient ri ON r.recipeid = ri.RecipeID
            LEFT JOIN ingredient i ON ri.IngredientID = i.ingredientid
            WHERE r.visibility = 'Public' AND (r.recipename LIKE ? OR r.id LIKE ? OR r.category LIKE ? OR i.ingredientname LIKE ?)
        ";

        // Exclude dietary restrictions
        foreach ($restrictions as $restriction) {
            $fallbackQuery .= " AND r.category NOT LIKE ?";
        }

        // Exclude allergies
        foreach ($allergies as $allergy) {
            $fallbackQuery .= " AND i.ingredientname NOT LIKE ? AND r.category NOT LIKE ?";
        }

        $fallbackQuery .= " ORDER BY r.recipename ASC";

        // Prepare fallback bind types and params
        $fallbackTypes = 'ssss';
        $fallbackParams = ["%" . $searchInput . "%", "%" . $searchInput . "%", "%" . $searchInput . "%", "%" . $searchInput . "%"];

        foreach ($restrictions as $restriction) {
            $fallbackTypes .= 's';
            $fallbackParams[] = "%" . $restriction . "%";
        }

        foreach ($allergies as $allergy) {
            $fallbackTypes .= 'ss';
            $fallbackParams[] = "%" . $allergy . "%";
            $fallbackParams[] = "%" . $allergy . "%";
        }

        $stmt = $conn->prepare($fallbackQuery);
        $stmt->bind_param($fallbackTypes, ...$fallbackParams);
        $stmt->execute();
        $searchResults = $stmt->get_result();
    }
}
// Fetch 3 random recommended recipes based on food preferences and restrictions
$recommendations = [];

$recommendationQuery = "
    SELECT DISTINCT r.recipeid, r.recipename, r.category, r.id, r.totalcalories, m.mediafile, m.mediatype, u.username
    FROM recipe r
    LEFT JOIN user u ON r.id = u.id
    LEFT JOIN recipeingredient ri ON r.recipeid = ri.RecipeID
    LEFT JOIN ingredient i ON ri.IngredientID = i.ingredientid
    LEFT JOIN media m ON r.recipeid = m.recipeid
    WHERE r.visibility = 'Public'
";

$recTypes = '';
$recParams = [];

// Add food preferences filter if exists
if (!empty($foodPreferences)) {
    $preferenceConditions = [];
    foreach ($foodPreferences as $preference) {
        $preferenceConditions[] = "r.category LIKE ?";
        $recTypes .= 's';
        $recParams[] = "%" . $preference . "%";
    }
    $recommendationQuery .= " AND (" . implode(' OR ', $preferenceConditions) . ")";
}

// Exclude dietary restrictions
foreach ($restrictions as $restriction) {
    $recommendationQuery .= " AND r.category NOT LIKE ?";
    $recTypes .= 's';
    $recParams[] = "%" . $restriction . "%";
}

// Exclude allergies
foreach ($allergies as $allergy) {
    $recommendationQuery .= " AND i.ingredientname NOT LIKE ? AND r.category NOT LIKE ?";
    $recTypes .= 'ss';
    $recParams[] = "%" . $allergy . "%";
    $recParams[] = "%" . $allergy . "%";
}

$recommendationQuery .= " ORDER BY RAND() LIMIT 3";

$recStmt = $conn->prepare($recommendationQuery);
if (!empty($recTypes)) {
    $recStmt->bind_param($recTypes, ...$recParams);
}
$recStmt->execute();
$recommendations = $recStmt->get_result();
$recStmt->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Main Menu - NutriEats</title>
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

        /* Search Bar Styles */
        .search-bar {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .search-bar input[type="text"] {
            flex-grow: 1;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            outline: none;
            transition: border-color 0.3s ease;
        }

        .search-bar input[type="text"]:focus {
            border-color: #b8860b;
        }

        .search-bar button {
            padding: 10px;
            background-color: #b8860b;
            border: none;
            border-radius: 4px;
            color: white;
            cursor: pointer;
            margin-left: 10px;
        }

        .search-bar button:hover {
            background-color: #daaa20;
        }

        button {
            padding: 12px 20px;
            background-color: #b8860b; /* Darker gold color */
            border: none;
            float: right;
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

        /* Recommendations Section */
        .recommendations {
            margin-bottom: 20px;
        }

        .recommendations h3 {
            margin: 0 0 10px;
            color: #333;
        }

        .food-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }

        .food-item img {
            width: 306px; /* Adjust image size */
            height: 200px; /* Adjust image size */
            margin-right: 10px;
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
        
        /* Your Recipes Section */
        .your-recipes {
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
        <h2>Welcome <?php echo htmlspecialchars($username); ?>!</h2>
        <button type="button" onclick="window.location.href='userstat.php'">My Stats</button>
            <p><strong>BMI:</strong> <?php echo htmlspecialchars(ucfirst($user['bmi'])); ?>
                <span style="color: <?php echo $bmiCategory[1]; ?>;">
                    (<?php echo $bmiCategory[0]; ?>)
                </span>
            </p>
            <p><strong>Recommended Calorie Intake:</strong> <?php echo htmlspecialchars(ucfirst($user['recommendedcalorie'])); ?> kcal/day</p>
            <p><strong>Target Weight:</strong> <?php echo htmlspecialchars($user['target_weight']); ?> kg</p>
            <p><strong>Daily Calorie Target to Maintain Target Weight:</strong> <?php echo htmlspecialchars(round($user['dailycalorie_target'])); ?> kcal/day</p>

    </main>

    <div class="container">
        <form method="GET" class="search-bar">
            <input type="text" name="searchInput" placeholder="Search by recipe, category, ingredient or username..." value="<?php echo htmlspecialchars($searchInput); ?>" />
            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            <button type="button" onclick="window.location.href='NewMenu.php'"><i class="fa-solid fa-plus"></i> Add</button>
        </form>

        <?php if (!empty($searchInput)) { ?>
            <h3>Search Results</h3>
            <?php if ($fallbackSearch) { ?>
                <p style="color: red;">There are no recipes that fit your preference. Showing general search results instead.</p>
            <?php } ?>

            <?php if ($searchResults && $searchResults->num_rows > 0) { ?>
                <table>
                    <thead>
                        <tr>
                            <th>Creator</th>
                            <th>Recipe Name</th>
                            <th>Category</th>
                            <th>Calories</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $searchResults->fetch_assoc()) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><a href="viewrecipe.php?recipeid=<?php echo $row['recipeid']; ?>"><?php echo htmlspecialchars($row['recipename']); ?></a></td>
                                <td><?php echo htmlspecialchars($row['category']); ?></td>
                                <td><?php echo htmlspecialchars($row['totalcalories']); ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            <?php } else { ?>
                <p>No recipes found for "<?php echo htmlspecialchars($searchInput); ?>".</p>
            <?php } ?>

        <?php } ?>

        <div class="recommendations">
            <h3>Recommendations</h3>
            <div style="display: flex; gap: 20px; flex-wrap: wrap; justify-content: center;">
                <?php if ($recommendations && $recommendations->num_rows > 0) { ?>
                    <?php while ($row = $recommendations->fetch_assoc()) { ?>
                        <div style="border: 1px solid #ccc; border-radius: 8px; overflow: hidden; width: 250px; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.1);" onclick="window.location.href='viewrecipe.php?recipeid=<?php echo $row['recipeid']; ?>'">
                            
                            <?php 
                                if (!empty($row['mediafile'])) { 
                                    $mediaData = base64_encode($row['mediafile']);
                                    $mediaType = $row['mediatype'];

                                    // Check if it's an image or a video
                                    if (strpos($mediaType, 'video') !== false) { ?>
                                        <video width="100%" height="150" controls style="display:block; object-fit: cover;">
                                            <source src="data:<?php echo $mediaType; ?>;base64,<?php echo $mediaData; ?>">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php } else { ?>
                                        <img src="data:<?php echo $mediaType; ?>;base64,<?php echo $mediaData; ?>" alt="<?php echo htmlspecialchars($row['recipename']); ?>" style="width: 100%; height: 150px; object-fit: cover;">
                                    <?php } 
                                } 
                            ?>

                            <div style="padding: 10px;">
                                <h4 style="margin: 0 0 5px; color: #b8860b;"> <?php echo htmlspecialchars($row['recipename']); ?> </h4>
                                <p style="margin: 5px 0;"><strong>Creator:</strong> <?php echo htmlspecialchars($row['username']); ?></p>
                                <p style="margin: 5px 0;"><strong>Category:</strong> <?php echo htmlspecialchars($row['category']); ?></p>
                                <p style="margin: 5px 0;"><strong>Calories:</strong> <?php echo htmlspecialchars($row['totalcalories']); ?> kcal</p>
                            </div>

                        </div>
                    <?php } ?>
                <?php } else { ?>
                    <p>No recommended recipes found.</p>
                <?php } ?>
            </div>
        </div>

    </div>
</body>
</html>