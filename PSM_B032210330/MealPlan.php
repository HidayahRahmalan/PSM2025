<?php
session_start();
include 'DBConnection.php';

if (!isset($_SESSION['username'])) {
    header('Location: LogIn.php');
    exit();
}

$id = $_SESSION['id'];
$db = new DBConnection();
$conn = $db->getConnection();

$userQuery = $conn->prepare("SELECT recommendedcalorie, dailycalorie_target FROM user WHERE id = ?");
$userQuery->bind_param("i", $id);
$userQuery->execute();
$userResult = $userQuery->get_result();
$userCalories = $userResult->fetch_assoc();
$userQuery->close();

$recommendedCalorie = $userCalories['recommendedcalorie'];
$dailyCalorieTarget = $userCalories['dailycalorie_target'];

$search = isset($_GET['search']) ? trim($_GET['search']) : '';


// Build SQL query based on search
if ($search !== '') {
    $mealQuery = $conn->prepare("
        SELECT mp.mealid, mp.title, mp.description, mp.totalmealcalorie, r.recipeid, r.recipename
        FROM mealplan mp
        JOIN mealplanrecipe mpr ON mp.mealid = mpr.mealid
        JOIN recipe r ON mpr.recipeid = r.recipeid
        WHERE mp.id = ? AND mp.title LIKE ?
    ");
    $likeSearch = '%' . $search . '%';
    $mealQuery->bind_param("is", $id, $likeSearch);
} else {
    $mealQuery = $conn->prepare("
        SELECT mp.mealid, mp.title, mp.description, mp.totalmealcalorie, r.recipeid, r.recipename
        FROM mealplan mp
        JOIN mealplanrecipe mpr ON mp.mealid = mpr.mealid
        JOIN recipe r ON mpr.recipeid = r.recipeid
        WHERE mp.id = ?
    ");
    $mealQuery->bind_param("i", $id);
}

$mealQuery->execute();
$mealResult = $mealQuery->get_result();

$mealPlans = [];
while ($row = $mealResult->fetch_assoc()) {
    $mealPlans[$row['mealid']]['title'] = $row['title'];
    $mealPlans[$row['mealid']]['description'] = $row['description'];
    $mealPlans[$row['mealid']]['totalmealcalorie'] = $row['totalmealcalorie'];
    $mealPlans[$row['mealid']]['recipes'][] = [
        'recipeid' => $row['recipeid'],
        'recipename' => $row['recipename']
    ];
}

$mealQuery->close();
$db->closeConnection();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meal Plans - NutriEats</title>
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

        .meal-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .meal-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease;
        }

        .meal-card:hover {
            transform: translateY(-5px);
        }

        .meal-title {
            font-size: 20px;
            color: #333;
            margin-bottom: 10px;
        }

        .meal-description {
            font-size: 16px;
            margin-bottom: 10px;
        }

        .meal-recipes ul {
            padding-left: 20px;
        }

        .meal-recipes li {
            margin-bottom: 5px;
        }

        .meal-recipes a {
            color: #b8860b;
            text-decoration: none;
            font-weight: 600;
        }

        .meal-recipes a:hover {
            text-decoration: underline;
        }

        .message {
            text-align: center;
            color: red;
            font-weight: bold;
            margin-top: 20px;
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
        <button type="button" onclick="window.location.href='Homepage.php'">Back</button>
        <h2>Your Meal Plans</h2>
        <form method="GET" action="MealPlan.php" style="margin-bottom: 20px; display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search meal title..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
            <button type="submit">Search</button>
            <button type="button" onclick="window.location.href='MealPlan.php'">Reset</button>
            <button type="button" onclick="window.location.href='NewMealPlan.php'">New</button>
        </form>
        <?php if ($search !== ''): ?>
            <p>Showing results for "<strong><?php echo htmlspecialchars($search); ?></strong>"</p>
        <?php endif; ?>
        <?php if (empty($mealPlans)): ?>
            <p class="message">You don't have any meal plans created.</p>
        <?php else: ?>
            <div class="meal-section">
                <?php foreach ($mealPlans as $mealId => $meal): ?>
                    <div class="meal-card" onclick="window.location.href='EditMealPlan.php?mealid=<?php echo $mealId; ?>'" style="cursor:pointer;">
                        <div class="meal-title"><?php echo htmlspecialchars($meal['title']); ?></div>
                        <div class="meal-description"><?php echo htmlspecialchars($meal['description']); ?></div>

                        <div class="meal-recipes">
                            <strong>Recipes:</strong>
                            <ul>
                                <?php foreach ($meal['recipes'] as $recipe): ?>
                                    <li>
                                        <a href="ViewRecipe.php?recipeid=<?php echo $recipe['recipeid']; ?>">
                                            <?php echo htmlspecialchars($recipe['recipename']); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div>
                            <strong>Total Meal Calorie: <?php echo $meal['totalmealcalorie']; ?> kcal</strong>

                            <?php
                                $warningMessage = '';
                                if (!is_null($recommendedCalorie) && !is_null($dailyCalorieTarget)) {
                                    if ($meal['totalmealcalorie'] > $dailyCalorieTarget && $meal['totalmealcalorie'] > $recommendedCalorie) {
                                        $warningMessage = "Calories exceed both Daily Calorie Target and Recommended Calorie";
                                    } elseif ($meal['totalmealcalorie'] > $dailyCalorieTarget) {
                                        $warningMessage = "Calories exceed Daily Calorie Target";
                                    } elseif ($meal['totalmealcalorie'] > $recommendedCalorie) {
                                        $warningMessage = "Calories exceed Recommended Calorie";
                                    }
                                } elseif (!is_null($dailyCalorieTarget) && $meal['totalmealcalorie'] > $dailyCalorieTarget) {
                                    $warningMessage = "Calories exceed Daily Calorie Target";
                                } elseif (!is_null($recommendedCalorie) && $meal['totalmealcalorie'] > $recommendedCalorie) {
                                    $warningMessage = "Calories exceed Recommended Calorie";
                                }
                            ?>
                            <?php if ($warningMessage !== ''): ?>
                                <p style="color:red; font-weight:bold;"><?php echo $warningMessage; ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>

</html>
