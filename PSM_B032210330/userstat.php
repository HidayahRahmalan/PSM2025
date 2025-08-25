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

    // Delete weight entry
    if (isset($_GET['delete_id'])) {
        $deleteId = intval($_GET['delete_id']);
        $deleteWeight = $conn->prepare("DELETE FROM weighttrack WHERE trackid = ? AND userid = ?");
        $deleteWeight->bind_param("ii", $deleteId, $id);
        $deleteWeight->execute();
        $deleteWeight->close();
        header('Location: userstat.php');
        exit();
    }

    // Fetch user static info
    $userQuery = $conn->prepare("SELECT age, gender, height, activity_level FROM user WHERE id = ?");
    $userQuery->bind_param("i", $id);
    $userQuery->execute();
    $userResult = $userQuery->get_result();
    $user = $userResult->fetch_assoc();
    $userQuery->close();

    // Fetch latest weight
    $weightQuery = $conn->prepare("SELECT weight FROM weighttrack WHERE userid = ? ORDER BY tracked_at DESC LIMIT 1");
    $weightQuery->bind_param("i", $id);
    $weightQuery->execute();
    $weightResult = $weightQuery->get_result();
    $weightRow = $weightResult->fetch_assoc();
    $currentWeight = $weightRow ? $weightRow['weight'] : null;
    $weightQuery->close();

    // Fetch latest health data
    $healthQuery = $conn->prepare("SELECT bmi, recommended_calorie, target_weight, dailycalorie_target, milestone_date FROM healthtrack WHERE userid = ? ORDER BY milestone_date DESC LIMIT 1");
    $healthQuery->bind_param("i", $id);
    $healthQuery->execute();
    $healthResult = $healthQuery->get_result();
    $health = $healthResult->fetch_assoc();
    $healthQuery->close();

    $popupMessage = "";

// Fetch latest healthtrack data
$sql = "SELECT target_weight, milestone_date FROM healthtrack WHERE userid = ? ORDER BY tracked_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $targetWeight = $row['target_weight'];
            $milestoneDate = $row['milestone_date'];

            // Fetch latest weight
            $sql2 = "SELECT weight FROM weighttrack WHERE userid = ? ORDER BY tracked_at DESC LIMIT 1";
            $stmt2 = $conn->prepare($sql2);

            if ($stmt2) {
                $stmt2->bind_param("i", $id);
                if ($stmt2->execute()) {
                    $result2 = $stmt2->get_result();
                    if ($result2 && $result2->num_rows > 0) {
                        $row2 = $result2->fetch_assoc();
                        $currentWeight = $row2['weight'];
                        $currentDate = date('Y-m-d');

                        if ($currentWeight <= $targetWeight && $currentDate <= $milestoneDate) {
                            $popupMessage = "🎉 Good job! You achieved your target weight on time or earlier!";
                        } elseif ($currentWeight > $targetWeight && $currentDate > $milestoneDate) {
                            $popupMessage = "⏳ Keep going! Your target date has passed, but you can still do it!";
                        }
                    }
                }
            }
        }
    }
}


    // BMI Category function
    function getBmiCategory($bmi)
    {
        if ($bmi < 18.5) return ['Underweight', 'black'];
        elseif ($bmi <= 24.9) return ['Healthy weight', 'green'];
        elseif ($bmi <= 29.9) return ['Overweight', 'orange'];
        elseif ($bmi <= 34.9) return ['Class I Obesity', 'red'];
        elseif ($bmi <= 39.9) return ['Class II Obesity', 'red'];
        else return ['Class III Obesity', 'red'];
    }

    $bmiCategory = getBmiCategory($health['bmi'] ?? 0);

    // Handle update submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newWeight = floatval($_POST['new_weight']);
        $activity = $_POST['activity'];
        $targetWeight = floatval($_POST['target_weight']);
        $milestoneDate = $_POST['milestone_date'] ?? date('Y-m-d');

        $age = $user['age'];
        $height = $user['height'];
        $gender = $user['gender'];

        $bmi = $newWeight / (($height / 100) ** 2);

        if ($gender === 'male') {
            $bmr = 10 * $newWeight + 6.25 * $height - 5 * $age + 5;
            $targetBmr = 10 * $targetWeight + 6.25 * $height - 5 * $age + 5;
        } else {
            $bmr = 10 * $newWeight + 6.25 * $height - 5 * $age - 161;
            $targetBmr = 10 * $targetWeight + 6.25 * $height - 5 * $age - 161;
        }

        $activity_factors = [
            'sedentary' => 1.2,
            'light' => 1.375,
            'moderate' => 1.55,
            'very' => 1.725,
            'extra' => 1.9
        ];
        $recommendedCalorie = $bmr * $activity_factors[$activity];
        $dailyCalorieTarget = $targetBmr * $activity_factors[$activity];

        // Insert weighttrack
        $insertWeight = $conn->prepare("INSERT INTO weighttrack (userid, weight) VALUES (?, ?)");
        $insertWeight->bind_param("id", $id, $newWeight);
        $insertWeight->execute();
        $insertWeight->close();

        // Insert healthtrack
        $insertHealth = $conn->prepare("INSERT INTO healthtrack (userid, bmi, recommended_calorie, target_weight, dailycalorie_target, milestone_date) VALUES (?, ?, ?, ?, ?, ?)");
        $insertHealth->bind_param("idddds", $id, $bmi, $recommendedCalorie, $targetWeight, $dailyCalorieTarget, $milestoneDate);
        $insertHealth->execute();
        $insertHealth->close();

        // Update activity level
        $updateActivity = $conn->prepare("UPDATE user SET activity_level = ? WHERE id = ?");
        $updateActivity->bind_param("si", $activity, $id);
        $updateActivity->execute();
        $updateActivity->close();

        header('Location: userstat.php');
        exit();
    }

    // Fetch all weight data
    $fullWeightQuery = $conn->prepare("SELECT trackid, weight, tracked_at FROM weighttrack WHERE userid = ? ORDER BY tracked_at ASC");
    $fullWeightQuery->bind_param("i", $id);
    $fullWeightQuery->execute();
    $fullWeightResult = $fullWeightQuery->get_result();
    $fullWeights = $fullWeightResult->fetch_all(MYSQLI_ASSOC);
    $fullWeightQuery->close();

    // Paginated weights
    $limit = 5;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    $weightQuery = $conn->prepare("SELECT trackid, weight, tracked_at FROM weighttrack WHERE userid = ? ORDER BY tracked_at ASC LIMIT ? OFFSET ?");
    $weightQuery->bind_param("iii", $id, $limit, $offset);
    $weightQuery->execute();
    $weightResult = $weightQuery->get_result();
    $weights = $weightResult->fetch_all(MYSQLI_ASSOC);
    $weightQuery->close();

    $countQuery = $conn->prepare("SELECT COUNT(*) AS total FROM weighttrack WHERE userid = ?");
    $countQuery->bind_param("i", $id);
    $countQuery->execute();
    $totalRows = $countQuery->get_result()->fetch_assoc()['total'];
    $countQuery->close();

    $totalPages = ceil($totalRows / $limit);

    $conn->close();
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>User Statistics - NutriEats</title>
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
            position: relative;
            min-height: 100vh;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('assets/background1.jpg') no-repeat center center fixed;
            background-size: cover;
            filter: blur(8px); /* Adjust the blur amount here */
            z-index: -1; /* Send the blurred image to the back */
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

        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2, h3 {
            text-align: center;
            color: #333;
        }

        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        label {
            font-weight: bold;
        }

        input,
        select {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        button {
            padding: 10px;
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

        .history {
            margin-top: 30px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 8px;
            text-align: center;
        }

        .delete-btn {
            background-color: red;
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .delete-btn:hover {
            background-color: darkred;
        }

        canvas {
            margin-top: 30px;
        }

        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(3px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .popup-content {
            background-color: #fff;
            padding: 30px;
            border-radius: 15px;
            max-width: 400px;
            text-align: center;
            font-size: 18px;
            box-shadow: 0 0 20px rgba(0,0,0,0.3);
            position: relative;
            animation: popupFadeIn 0.3s ease-in-out;
        }

        .close-button {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 24px;
            color: #888;
            cursor: pointer;
        }

        .popup-content p {
            margin: 0;
            color: #333;
            font-weight: bold;
        }

        /* Optional animation */
        @keyframes popupFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
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
    <button type="button" onclick="window.location.href='profile.php'">Back</button>
        <h2>Current Statistics</h2>
        <p><strong>Age:</strong> <?php echo htmlspecialchars($user['age']); ?> years</p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars(ucfirst($user['gender'])); ?></p>
        <p><strong>Height:</strong> <?php echo htmlspecialchars($user['height']); ?> cm</p>
        <p><strong>Weight:</strong> <?php echo htmlspecialchars($currentWeight); ?> kg</p>
        <p><strong>Activity Level:</strong> <?php echo htmlspecialchars(ucfirst($user['activity_level'])); ?></p>
        <p><strong>BMI:</strong> <?php echo htmlspecialchars($health['bmi']); ?> 
        <span style="color: <?php echo $bmiCategory[1]; ?>;">(<?php echo $bmiCategory[0]; ?>)</span>
        </p>
        <p><strong>Target Weight:</strong> <?php echo htmlspecialchars($health['target_weight']); ?> kg</p>
        <p><strong>Recommended Calorie:</strong> <?php echo htmlspecialchars(round($health['recommended_calorie'])); ?> kcal</p>
        <p><strong>Daily Calorie Target:</strong> <?php echo htmlspecialchars(round($health['dailycalorie_target'])); ?> kcal</p>
        <p><strong>Milestone Date:</strong> <?php echo htmlspecialchars($health['milestone_date']); ?></p>

    </div>

    <div class="container">
        <h2>User Statistics (Update)</h2>

        <form method="POST" action="userstat.php">

            <label for="target_weight">Target Weight (kg):</label>
            <input type="number" step="0.01" name="target_weight" id="target_weight" value="<?php echo htmlspecialchars($health['target_weight']); ?>">

            <label for="new_weight">New Weight (kg):</label>
            <input type="number" step="0.01" name="new_weight" id="new_weight" required>

            <label for="milestone_date">Target Date to Achieve Weight (Milestone Date):</label>
            <input type="date" name="milestone_date" id="milestone_date" required>

            <label for="activity">Activity Level:</label>
            <select name="activity" id="activity" required>
                <option value="sedentary" <?php if ($user['activity_level'] === 'sedentary') echo 'selected'; ?>>Sedentary (little or no exercise)</option>
                <option value="light" <?php if ($user['activity_level'] === 'light') echo 'selected'; ?>>Lightly Active (1-3 days/week)</option>
                <option value="moderate" <?php if ($user['activity_level'] === 'moderate') echo 'selected'; ?>>Moderately Active (3-5 days/week)</option>
                <option value="very" <?php if ($user['activity_level'] === 'very') echo 'selected'; ?>>Very Active (6-7 days/week)</option>
                <option value="extra" <?php if ($user['activity_level'] === 'extra') echo 'selected'; ?>>Extra Active (physical job or double training)</option>
            </select>

            <button type="submit">Update Stats</button>
        </form>
    </div>

    <div class="container">
        <div class="history">
            <h3>New Stats</h3>
            <p><strong>Current Weight:</strong> <?php echo htmlspecialchars($currentWeight); ?> kg</p>
            <p><strong>Current BMI:</strong> <?php echo htmlspecialchars($health['bmi']); ?>
                <span style="color: <?php echo $bmiCategory[1]; ?>;">
                    (<?php echo $bmiCategory[0]; ?>)
                </span>
            </p>
            <p><strong>Recommended Calorie Intake:</strong> <?php echo round($health['recommended_calorie']); ?> kcal/day</p>
            <br>

            <h3>Weight History</h3>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Weight (kg)</th>
                    <th>Action</th>
                </tr>
                <?php foreach ($weights as $entry) : ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['tracked_at']); ?></td>
                        <td><?php echo htmlspecialchars($entry['weight']); ?></td>
                        <td>
                            <a href="userstat.php?delete_id=<?php echo $entry['trackid']; ?>" onclick="return confirm('Are you sure you want to delete this entry?');">
                                <button class="delete-btn">Delete</button>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <br>

            <!-- Pagination Links -->
            <div style="text-align: center; margin-top: 20px;">
                <?php if ($page > 1): ?>
                    <a href="userstat.php?page=<?php echo $page - 1; ?>" style="margin-right: 10px;">&laquo; Previous</a>
                <?php endif; ?>

                Page <?php echo $page; ?> of <?php echo $totalPages; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="userstat.php?page=<?php echo $page + 1; ?>" style="margin-left: 10px;">Next &raquo;</a>
                <?php endif; ?>
            </div>

            <br>

            <h3>Weight Progress Chart</h3>
            <div style="text-align: center; margin-bottom: 10px;">
                <button onclick="filterChart('all')">All</button>
                <button onclick="filterChart('weekly')">Weekly</button>
                <button onclick="filterChart('monthly')">Monthly</button>
            </div>
            <canvas id="weightChart"></canvas>
        </div>
    </div>

    <script>
        function closePopup() {
    document.getElementById("popupModal").style.display = "none";
}
    const ctx = document.getElementById('weightChart').getContext('2d');
    const allWeightData = <?php echo json_encode($fullWeights); ?>;

    let weightChart;

    function renderChart(filteredData) {
        const weightLabels = filteredData.map(entry => entry.tracked_at);
        const weightValues = filteredData.map(entry => parseFloat(entry.weight));

        if (weightChart) {
            weightChart.destroy();
        }

        weightChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: weightLabels,
                datasets: [{
                    label: 'Weight Progress (kg)',
                    data: weightValues,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderWidth: 2,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: false }
                }
            }
        });
    }

    function filterChart(mode) {
        const now = new Date();
        let filteredData = allWeightData;

        if (mode === 'weekly') {
            const oneWeekAgo = new Date();
            oneWeekAgo.setDate(now.getDate() - 7);
            filteredData = allWeightData.filter(entry => new Date(entry.tracked_at) >= oneWeekAgo);
        } else if (mode === 'monthly') {
            const oneMonthAgo = new Date();
            oneMonthAgo.setMonth(now.getMonth() - 1);
            filteredData = allWeightData.filter(entry => new Date(entry.tracked_at) >= oneMonthAgo);
        }

        renderChart(filteredData);
    }

    // Initial chart with all data
    filterChart('all');
</script>

<?php if (!empty($popupMessage)): ?>
<div id="popupModal" class="popup-overlay">
  <div class="popup-content">
    <span class="close-button" onclick="closePopup()">×</span>
    <p><?= htmlspecialchars($popupMessage) ?></p>
  </div>
</div>
<?php endif; ?>

</body>

</html>
