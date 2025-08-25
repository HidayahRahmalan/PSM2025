<?php
session_start();
include '../DBConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['username'])) {
    header('Location: ../LogIn.php');
    exit();
}

// Set filter type
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'month';

// Database connection
$db = new DBConnection();
$conn = $db->getConnection();

// Query based on filter
if ($filter === 'week') {
    $userQuery = "
        SELECT YEARWEEK(created_at, 1) AS period, COUNT(*) AS total_users 
        FROM user 
        GROUP BY period 
        ORDER BY period
    ";

    $recipeQuery = "
        SELECT YEARWEEK(created_at, 1) AS period, COUNT(*) AS total_recipes 
        FROM recipe 
        GROUP BY period 
        ORDER BY period
    ";
} elseif ($filter === 'day') {
    $userQuery = "
        SELECT DATE(created_at) AS period, COUNT(*) AS total_users 
        FROM user 
        GROUP BY period 
        ORDER BY period
    ";

    $recipeQuery = "
        SELECT DATE(created_at) AS period, COUNT(*) AS total_recipes 
        FROM recipe 
        GROUP BY period 
        ORDER BY period
    ";
} else {
    $userQuery = "
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COUNT(*) AS total_users 
        FROM user 
        GROUP BY period 
        ORDER BY period
    ";

    $recipeQuery = "
        SELECT DATE_FORMAT(created_at, '%Y-%m') AS period, COUNT(*) AS total_recipes 
        FROM recipe 
        GROUP BY period 
        ORDER BY period
    ";
}

// Fetch user data
$userResult = $conn->query($userQuery);
$userData = [];
while ($row = $userResult->fetch_assoc()) {
    $userData[] = $row;
}

// Fetch recipe data
$recipeResult = $conn->query($recipeQuery);
$recipeData = [];
while ($row = $recipeResult->fetch_assoc()) {
    $recipeData[] = $row;
}

// Fetch total users
$totalUsersResult = $conn->query("SELECT COUNT(*) AS total FROM user");
$totalUsers = $totalUsersResult->fetch_assoc()['total'];

// Fetch total recipes
$totalRecipesResult = $conn->query("SELECT COUNT(*) AS total FROM recipe");
$totalRecipes = $totalRecipesResult->fetch_assoc()['total'];

// Fetch gender distribution
$genderResult = $conn->query("SELECT gender, COUNT(*) AS total FROM user GROUP BY gender");
$genderData = [];
while ($row = $genderResult->fetch_assoc()) {
    $genderData[] = $row;
}

// Fetch age group distribution
$ageGroupQuery = "
    SELECT 
        CASE 
            WHEN age < 17 THEN '<17'
            WHEN age BETWEEN 17 AND 20 THEN '17-20'
            WHEN age BETWEEN 21 AND 30 THEN '21-30'
            WHEN age BETWEEN 31 AND 40 THEN '31-40'
            WHEN age BETWEEN 41 AND 50 THEN '41-50'
            ELSE '51+'
        END AS age_group,
        COUNT(*) AS total 
    FROM user
    WHERE age IS NOT NULL
    GROUP BY age_group
    ORDER BY 
        CASE 
            WHEN age_group = '<17' THEN 1
            WHEN age_group = '17-20' THEN 2
            WHEN age_group = '21-30' THEN 3
            WHEN age_group = '31-40' THEN 4
            WHEN age_group = '41-50' THEN 5
            ELSE 6
        END
";

$ageGroupResult = $conn->query($ageGroupQuery);
$ageGroupData = [];
while ($row = $ageGroupResult->fetch_assoc()) {
    $ageGroupData[] = $row;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - NutriEats</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .dashboard {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin: 30px auto;
            max-width: 1200px;
        }

        .container {
            flex: 1 1 60%;
            min-width: 600px;
            background: white;
            padding: 20px;
            margin: 10px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .summary {
            flex: 1 1 30%;
            min-width: 250px;
            background: white;
            padding: 20px;
            margin: 10px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .summary h3 {
            margin-top: 0;
        }

        .summary p {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 15px 0;
        }
        
        .summary canvas {
            max-width: 250px;
            max-height: 250px;
            display: block;
            margin: 0 auto;
        }

        .chart-box {
            margin-top: 20px;
            text-align: center;
        }

        .chart-box h4 {
            margin-bottom: 10px;
            font-size: 16px;
        }

        .chart-box canvas {
            max-width: 250px;
            max-height: 250px;
            margin: 0 auto;
        }

        select {
            padding: 10px;
            margin-bottom: 20px;
        }

        canvas {
            margin-bottom: 50px;
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
            <a href="adminuser.php"><i class="fa-solid fa-users" style="margin-right: 5px;"></i> Users</a>
            <a href="adminrecipe.php"><i class="fa-solid fa-calendar-check" style="margin-right: 5px;"></i> Recipes</a>
            <a href="../index.php"><i class="fa-solid fa-sign-out icon-spacing"></i> Sign Out</a>
        </nav>
    </header>

    <div class="dashboard">
        <div class="container">
            <h2>Statistics</h2>
            <label for="filter">Filter by:</label>
            <select id="filter" onchange="changeFilter()">
                <option value="adminpage.php?filter=month" <?php if ($filter == 'month') echo 'selected'; ?>>Monthly</option>
                <option value="adminpage.php?filter=week" <?php if ($filter == 'week') echo 'selected'; ?>>Weekly</option>
                <option value="adminpage.php?filter=day" <?php if ($filter == 'day') echo 'selected'; ?>>Daily</option>
            </select>
            
            <div class="container">
                <h3>User Registrations</h3>
                <canvas id="userChart"></canvas>
            </div>

            <div class="container">
                <h3>Recipes Created</h3>
                <canvas id="recipeChart"></canvas>
            </div>
        </div>
        
        <div class="summary">
            <h3>Cumulative Summary</h3>
            <p>Total Users: <?php echo $totalUsers; ?></p>
            <p>Total Recipes: <?php echo $totalRecipes; ?></p>

            <div class="chart-box">
                <h4>User Gender Distribution</h4>
                <canvas id="genderChart"></canvas>
            </div>

            <div class="chart-box">
                <h4>User Age Group Distribution</h4>
                <canvas id="ageGroupChart"></canvas>
            </div>
        </div>

    </div>

    <script>
        function changeFilter() {
            const filterSelect = document.getElementById('filter');
            window.location.href = filterSelect.value;
        }

        // User Data from PHP
        const userLabels = <?php echo json_encode(array_column($userData, 'period')); ?>;
        const userCounts = <?php echo json_encode(array_column($userData, 'total_users')); ?>;

        // Recipe Data from PHP
        const recipeLabels = <?php echo json_encode(array_column($recipeData, 'period')); ?>;
        const recipeCounts = <?php echo json_encode(array_column($recipeData, 'total_recipes')); ?>;

        // User Chart
        const userCtx = document.getElementById('userChart').getContext('2d');
        new Chart(userCtx, {
            type: 'line',
            data: {
                labels: userLabels,
                datasets: [{
                    label: 'User Registrations',
                    data: userCounts,
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 2,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Recipe Chart
        const recipeCtx = document.getElementById('recipeChart').getContext('2d');
        new Chart(recipeCtx, {
            type: 'line',
            data: {
                labels: recipeLabels,
                datasets: [{
                    label: 'Recipes Created',
                    data: recipeCounts,
                    backgroundColor: 'rgba(255, 206, 86, 0.2)',
                    borderColor: 'rgba(255, 206, 86, 1)',
                    borderWidth: 2,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Gender Data from PHP
        const genderLabels = <?php echo json_encode(array_column($genderData, 'gender')); ?>;
        const genderCounts = <?php echo json_encode(array_column($genderData, 'total')); ?>;

        // Age Group Data from PHP
        const ageGroupLabels = <?php echo json_encode(array_column($ageGroupData, 'age_group')); ?>;
        const ageGroupCounts = <?php echo json_encode(array_column($ageGroupData, 'total')); ?>;

        // Gender Pie Chart
        const genderCtx = document.getElementById('genderChart').getContext('2d');
        new Chart(genderCtx, {
            type: 'pie',
            data: {
                labels: genderLabels,
                datasets: [{
                    data: genderCounts,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 206, 86, 0.6)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true
            }
        });

        // Age Group Bar Chart
        const ageGroupCtx = document.getElementById('ageGroupChart').getContext('2d');
        new Chart(ageGroupCtx, {
            type: 'bar',
            data: {
                labels: ageGroupLabels,
                datasets: [{
                    label: 'Number of Users',
                    data: ageGroupCounts,
                    backgroundColor: 'rgba(153, 102, 255, 0.6)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

    </script>
</body>

</html>
