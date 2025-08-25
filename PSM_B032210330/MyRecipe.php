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

// Fetch user ID
$userQuery = $conn->prepare("SELECT id FROM user WHERE username = ?");
$userQuery->bind_param("s", $username);
$userQuery->execute();
$userResult = $userQuery->get_result();
$userID = $userResult->fetch_assoc()['id'];

// Handle search and filter
$createdSearch = $_GET['createdSearch'] ?? '';
$createdFilter = $_GET['createdFilter'] ?? '';

$savedSearch = $_GET['savedSearch'] ?? '';
$savedFilter = $_GET['savedFilter'] ?? '';

// Fetch Created Recipes
$createdQuery = "SELECT r.recipeid, r.recipename, r.category, r.visibility, m.mediafile, m.mediatype
                 FROM recipe r
                 LEFT JOIN media m ON r.recipeid = m.recipeid
                 WHERE r.id = ? AND r.recipename LIKE ? ";
$params = [$userID, "%$createdSearch%"];
$types = "is";

if (!empty($createdFilter)) {
    $createdQuery .= "AND r.visibility = ? ";
    $params[] = $createdFilter;
    $types .= "s";
}

$createdQuery .= "GROUP BY r.recipeid ORDER BY r.recipename ASC"; // So you get one row per recipe

$stmtCreated = $conn->prepare($createdQuery);
$stmtCreated->bind_param($types, ...$params);
$stmtCreated->execute();
$createdRecipes = $stmtCreated->get_result();

// Fetch Saved Recipes
$savedQuery = "SELECT r.recipeid, r.recipename, r.category, u.username AS creator 
               FROM savedrecipe s 
               JOIN recipe r ON s.recipeid = r.recipeid 
               JOIN user u ON r.id = u.id 
               LEFT JOIN media m ON r.recipeid = m.recipeid
               WHERE s.id = ? AND r.recipename LIKE ? ";
$params = [$userID, "%$savedSearch%"];
$types = "is";

if (!empty($savedFilter)) {
    $savedQuery .= "AND r.category = ? ";
    $params[] = $savedFilter;
    $types .= "s";
}

$savedQuery .= "ORDER BY r.recipename ASC";
$stmtSaved = $conn->prepare($savedQuery);
$stmtSaved->bind_param($types, ...$params);
$stmtSaved->execute();
$savedRecipes = $stmtSaved->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Recipes</title>
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

        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            background-color: #212226;
            padding: 0 20px;
            
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

<div class="container">
    <button type="button" onclick="window.location.href='Homepage.php'">Back</button>
    <h1>My Recipes</h1>

    <!-- Created Recipes Section -->
    <section>
        <h2>Created Recipes</h2>
        <form method="GET" class="filter-form">
            <input type="text" name="createdSearch" placeholder="Search by title" value="<?php echo htmlspecialchars($createdSearch); ?>">
            <select name="createdFilter">
                <option value="">All Visibilities</option>
                <option value="Public" <?php if ($createdFilter == 'Public') echo 'selected'; ?>>Public</option>
                <option value="Private" <?php if ($createdFilter == 'Private') echo 'selected'; ?>>Private</option>
            </select>
            <button type="submit">Search</button>
            <a href="NewMenu.php" style="text-decoration: none;">
        <button type="button">Add</button>
    </a>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Visibility</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $createdRecipes->fetch_assoc()) { ?>
                <tr>
                    <td>
                        <?php 
                        if (!empty($row['mediafile'])) { 
                            $mediaData = base64_encode($row['mediafile']);
                            $mediaType = $row['mediatype'];

                            // Check if it's an image or a video
                            if (strpos($mediaType, 'video') !== false) { ?>
                                <video width="150" controls style="display:block; margin-bottom:10px;">
                                    <source src="data:<?php echo $mediaType; ?>;base64,<?php echo $mediaData; ?>">
                                    Your browser does not support the video tag.
                                </video>
                            <?php } else { ?>
                                <img src="data:<?php echo $mediaType; ?>;base64,<?php echo $mediaData; ?>" alt="Recipe Image" style="max-width:150px; display:block; margin-bottom:10px;">
                            <?php } 
                        } ?>
                    </td>
                    <!--<td><?php echo htmlspecialchars($row['recipename']); ?></td>-->
                    <td><a href="editrecipe.php?recipeid=<?php echo $row['recipeid']; ?>"><?php echo htmlspecialchars($row['recipename']); ?></a></td>
                    <td><?php echo htmlspecialchars($row['category']); ?></td>
                    <td><?php echo htmlspecialchars($row['visibility']); ?></td>
                </tr>
            <?php } ?>
            </tbody>
        </table>
    </section>

    <!-- Saved Recipes Section -->
    <section style="margin-top: 40px;">
        <h2>Saved Recipes</h2>
        <form method="GET" class="filter-form">
            <input type="text" name="savedSearch" placeholder="Search by title" value="<?php echo htmlspecialchars($savedSearch); ?>">
            <select name="savedFilter">
                <option value="">All Categories</option>
                <option value="Breakfast" <?php if ($savedFilter == 'Breakfast') echo 'selected'; ?>>Breakfast</option>
                <option value="Lunch" <?php if ($savedFilter == 'Lunch') echo 'selected'; ?>>Lunch</option>
                <option value="Dinner" <?php if ($savedFilter == 'Dinner') echo 'selected'; ?>>Dinner</option>
                <!-- Add more categories if needed -->
            </select>
            <button type="submit">Search</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Created By</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $savedRecipes->fetch_assoc()) { ?>
                    <tr>
                        <td><a href="viewrecipe.php?recipeid=<?php echo $row['recipeid']; ?>"><?php echo htmlspecialchars($row['recipename']); ?></a></td>
                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                        <td><?php echo htmlspecialchars($row['creator']); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>
</div>

</body>
</html>
