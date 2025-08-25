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
$genderFilter = isset($_GET['gender']) ? $_GET['gender'] : '';
$sortOrder = isset($_GET['sort']) && $_GET['sort'] == 'asc' ? 'asc' : 'desc';
$ageGroupFilter = isset($_GET['age_group']) ? $_GET['age_group'] : '';

// Build WHERE clause
$where = "WHERE role='nuser'";

if (!empty($search)) {
    $where .= " AND (fullName LIKE '%$search%' OR username LIKE '%$search%')";
}

if (!empty($genderFilter)) {
    $where .= " AND gender = '$genderFilter'";
}

if (!empty($ageGroupFilter)) {
    if ($ageGroupFilter == '<17') {
        $where .= " AND age < 17";
    } elseif ($ageGroupFilter == '17-20') {
        $where .= " AND age BETWEEN 17 AND 20";
    } elseif ($ageGroupFilter == '21-30') {
        $where .= " AND age BETWEEN 21 AND 30";
    } elseif ($ageGroupFilter == '31-40') {
        $where .= " AND age BETWEEN 31 AND 40";
    } elseif ($ageGroupFilter == '41-50') {
        $where .= " AND age BETWEEN 41 AND 50";
    } elseif ($ageGroupFilter == '51+') {
        $where .= " AND age > 50";
    }
}

// Get total users for pagination
$totalQuery = "SELECT COUNT(*) AS total FROM user $where";
$totalResult = $conn->query($totalQuery);
$totalUsers = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalUsers / $limit);

// Get users
$userQuery = "SELECT * FROM user $where ORDER BY created_at $sortOrder LIMIT $limit OFFSET $offset";
$userResult = $conn->query($userQuery);

// Handle delete
if (isset($_GET['delete'])) {
    $deleteID = $_GET['delete'];
    $conn->query("DELETE FROM user WHERE id = $deleteID");
    header("Location: adminuser.php");
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Admin - User Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
    <h2>User Management</h2>

    <form method="GET" class="search-bar">
        <input type="text" name="search" placeholder="Search full name or username" value="<?php echo htmlspecialchars($search); ?>">
        <select name="gender">
            <option value="">All Genders</option>
            <option value="Male" <?php if ($genderFilter == 'Male') echo 'selected'; ?>>Male</option>
            <option value="Female" <?php if ($genderFilter == 'Female') echo 'selected'; ?>>Female</option>
        </select>
        <select name="age_group">
            <option value="">All Ages</option>
            <option value="<17" <?php if ($ageGroupFilter == '<17') echo 'selected'; ?>><17</option>
            <option value="17-20" <?php if ($ageGroupFilter == '17-20') echo 'selected'; ?>>17-20</option>
            <option value="21-30" <?php if ($ageGroupFilter == '21-30') echo 'selected'; ?>>21-30</option>
            <option value="31-40" <?php if ($ageGroupFilter == '31-40') echo 'selected'; ?>>31-40</option>
            <option value="41-50" <?php if ($ageGroupFilter == '41-50') echo 'selected'; ?>>41-50</option>
            <option value="51+" <?php if ($ageGroupFilter == '51+') echo 'selected'; ?>>51+</option>
        </select>
        <button type="submit">Search</button>
    </form>

    <?php
    $nextSortOrder = ($sortOrder == 'asc') ? 'desc' : 'asc';
    $sortIcon = ($sortOrder == 'asc') ? '<i class="fa-solid fa-arrow-up"></i>' : '<i class="fa-solid fa-arrow-down"></i>';
    ?>

    <table>
        <tr>
            <th>No</th>
            <th>Full Name</th>
            <th>Username</th>
            <th>Age</th>
            <th>Gender</th>
            <th>
                <a href="adminuser.php?<?php echo http_build_query(array_merge($_GET, ['sort' => $nextSortOrder])); ?>" style="text-decoration: none; color: inherit;"> Created At <?php echo $sortIcon; ?>
                </a>
            </th>
            <th>Action</th>
        </tr>
        <?php
        $no = $offset + 1;
        while ($row = $userResult->fetch_assoc()) {
            echo "<tr>
                <td>{$no}</td>
                <td>{$row['fullName']}</td>
                <td><a href='adminviewuser.php?userid={$row['id']}' style='text-decoration: none; color: #007BFF;'>{$row['username']}</a></td>
                <td>{$row['age']}</td>
                <td>{$row['gender']}</td>
                <td>{$row['created_at']}</td>
                <td><a href='adminuser.php?delete={$row['id']}' onclick=\"return confirm('Are you sure you want to delete this user?');\"><button class='delete-btn'>Delete</button></a></td>
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
            echo "<a class='$active' href='adminuser.php?$queryString'>$i</a>";
        }
        ?>
    </div>

</div>

</body>
</html>
