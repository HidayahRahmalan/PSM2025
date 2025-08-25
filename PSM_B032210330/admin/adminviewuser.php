<?php
session_start();
include '../DBConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../LogIn.php');
    exit();
}

// Check if userid is provided
if (!isset($_GET['userid'])) {
    header('Location: adminuser.php');
    exit();
}

$userid = $_GET['userid'];

$db = new DBConnection();
$conn = $db->getConnection();

// Get user info
$userQuery = "SELECT * FROM user WHERE id = $userid";
$userResult = $conn->query($userQuery);

if ($userResult->num_rows == 0) {
    echo "User not found.";
    exit();
}

$user = $userResult->fetch_assoc();

// Get user's recipes
$recipeQuery = "SELECT * FROM recipe WHERE id = $userid";
$recipeResult = $conn->query($recipeQuery);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>View User Details</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; background-color: #f4f4f4; }
        .header { display: flex; justify-content: space-between; align-items: center; height: 70px; background-color: #212226; padding: 0 20px; position: sticky; top: 0; z-index: 1000; }
        .logo-container { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo { max-height: 60px; display: block; }
        .site-title { color: #b8860b; font-size: 30px; font-weight: bold; user-select: none; }
        .nav-links { display: flex; gap: 20px; }
        .nav-links a { color: #fff; text-decoration: none; font-weight: 600; }
        .nav-links a:hover { text-decoration: underline; }
        .container { max-width: 1200px; margin: 30px auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 10px; text-align: center; }
        th { background-color: #f4f4f4; }
        button { padding: 8px 12px; background-color: #212226; color: white; border: none; cursor: pointer; border-radius: 4px; }
        button:hover { background-color: #444; }
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

<div class="container">
    <button type="button" onclick="window.location.href='adminuser.php'">Back</button>

    <h2>User Profile</h2>
    <p><strong>Full Name:</strong> <?php echo htmlspecialchars($user['fullName']); ?></p>
    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
    <p><strong>Age:</strong> <?php echo htmlspecialchars($user['age']); ?></p>
    <p><strong>Gender:</strong> <?php echo htmlspecialchars($user['gender']); ?></p>
    <p><strong>Created At:</strong> <?php echo htmlspecialchars($user['created_at']); ?></p>

    <h3>User's Recipes</h3>
    <table>
        <tr>
            <th>Recipe Name</th>
            <th>Category</th>
            <th>Visibility</th>
            <th>Created At</th>
        </tr>
        <?php
        if ($recipeResult->num_rows > 0) {
            while ($recipe = $recipeResult->fetch_assoc()) {
                echo "<tr>
                    <td><a href='adminuserrecipe.php?recipeid={$recipe['id']}' style='text-decoration: none; color: #007BFF;'>{$recipe['recipename']}</a></td>
                    <td>{$recipe['category']}</td>
                    <td>{$recipe['visibility']}</td>
                    <td>{$recipe['created_at']}</td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No recipes found.</td></tr>";
        }
        ?>
    </table>
</div>

</body>
</html>
