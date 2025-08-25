<?php
session_start();
include '../DBConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../LogIn.php');
    exit();
}

$db = new DBConnection();
$conn = $db->getConnection();

// Pagination setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search and filter setup
$search = isset($_GET['search']) ? $_GET['search'] : '';
$visibilityFilter = isset($_GET['visibility']) ? $_GET['visibility'] : '';

// Build WHERE clause
$where = "WHERE 1=1"; // Always true to simplify appending conditions

if (!empty($search)) {
    $where .= " AND (r.recipename LIKE '%$search%' OR u.username LIKE '%$search%')";
}

if (!empty($visibilityFilter)) {
    $where .= " AND r.visibility = '$visibilityFilter'";
}

// Get total recipes for pagination
$totalQuery = "SELECT COUNT(*) AS total 
               FROM recipe r
               LEFT JOIN user u ON r.id = u.id
               $where";
$totalResult = $conn->query($totalQuery);
$totalRecipes = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecipes / $limit);

// Get recipes
$recipeQuery = "SELECT r.*, u.username 
                FROM recipe r
                LEFT JOIN user u ON r.id = u.id
                $where
                ORDER BY r.created_at DESC
                LIMIT $limit OFFSET $offset";
$recipeResult = $conn->query($recipeQuery);

// Handle delete
if (isset($_GET['delete'])) {
    $deleteID = $_GET['delete'];
    $conn->query("DELETE FROM recipe WHERE recipeid = $deleteID");
    header("Location: adminrecipe.php");
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin - Recipe Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        <?php ?>
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
            max-width: 1200px;
            margin: 30px auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        table, th, td {
            border: 1px solid #ccc;
        }

        th, td {
            padding: 10px;
            text-align: center;
        }

        th {
            background-color: #f4f4f4;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-bar input, .search-bar select {
            padding: 8px;
        }

        .pagination {
            margin-top: 20px;
            text-align: center;
        }

        .pagination a {
            padding: 8px 12px;
            margin: 0 4px;
            background-color: #212226;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }

        .pagination a.active {
            background-color: #b8860b;
        }

        .delete-btn {
            background-color: #ff4d4d;
            color: white;
            border: none;
            padding: 6px 12px;
            cursor: pointer;
            border-radius: 4px;
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

<div class="container">
    <h2>Recipe Management</h2>

    <form method="GET" class="search-bar">
        <input type="text" name="search" placeholder="Search recipe name or username" value="<?php echo htmlspecialchars($search); ?>">
        <select name="visibility">
            <option value="">All Visibilities</option>
            <option value="Public" <?php if ($visibilityFilter == 'Public') echo 'selected'; ?>>Public</option>
            <option value="Private" <?php if ($visibilityFilter == 'Private') echo 'selected'; ?>>Private</option>
        </select>
        <button type="submit">Search</button>
    </form>

    <table>
        <tr>
            <th>No</th>
            <th>Recipe Name</th>
            <th>Category</th>
            <th>Visibility</th>
            <th>Created At</th>
            <th>Creator Username</th>
            <th>Action</th>
        </tr>
        <?php
        $no = $offset + 1;
        while ($row = $recipeResult->fetch_assoc()) {
            echo "<tr>
                <td>{$no}</td>
                <td><a href='adminviewrecipe.php?recipeid={$row['recipeid']}' style='color: #007bff; text-decoration: underline;'>{$row['recipename']}</a></td>
                <td>{$row['category']}</td>
                <td>{$row['visibility']}</td>
                <td>{$row['created_at']}</td>
                <td>{$row['username']}</td>
                <td><a href='adminrecipe.php?delete={$row['recipeid']}' onclick=\"return confirm('Are you sure you want to delete this recipe?');\"><button class='delete-btn'>Delete</button></a></td>
            </tr>";
            $no++;
        }
        ?>
    </table>

    <div class="pagination">
        <?php
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = ($i == $page) ? 'active' : '';
            $queryString = http_build_query(array_merge($_GET, ['page' => $i]));
            echo "<a class='$active' href='adminrecipe.php?$queryString'>$i</a>";
        }
        ?>
    </div>

</div>

</body>
</html>
