<?php
session_start();
include 'DBConnection.php';

if (!isset($_SESSION['username'])) {
    header('Location: LogIn.php');
    exit();
}

$db = new DBConnection();
$conn = $db->getConnection();
$username = $_SESSION['username'];
$userid = $_SESSION['id'];

// Mapping diseases to restrictions
$disease_to_restriction = [
    'Lactose Intolerance' => 'Dairy, Milk, Cheese, Butter, Cream, Yogurt, Whey, Casein , Ice Cream, Cheesecake, Pizza with cheese, Cream soups, Milkshakes',
    'Gout' => 'Seafood, Anchovies, Sardines, Mussels, Shrimp, Crab, Lobster, Sushi, Seafood pasta, Shrimp fried rice, Crab curry',
    'Inflammatory Bowel Disease' => 'High-Fiber, Whole grains, Nuts, Seeds, Raw vegetables, Beans, Popcorn, Whole wheat bread, Almond granola bars, Bean salads, Popcorn snacks',
    'Celiac Disease' => 'Gluten, Wheat, Barley, Rye, Malt, Semolina, Farro, Spaghetti, Croissants, Pancakes, Beer, Cereal bars',
    'None' => NULL
];

// Fetch current diseases
$currentDiseases = [];
$sql = "SELECT disease FROM user_diseases WHERE userid = ? AND disease_end_at IS NULL";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $currentDiseases[] = $row['disease'];
}

// Fetch disease history
$diseaseHistory = [];
$sql = "SELECT disease, diagnosed_at, disease_end_at FROM user_diseases WHERE userid = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userid);
$stmt->execute();
$diseaseHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch preferences and allergies
$preferences = '';
$allergies = '';
$conn->query("SET time_zone = '+08:00'");

$stmt = $conn->prepare("SELECT preference FROM user_preferences WHERE userid = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->bind_result($preferences);
$stmt->fetch();
$stmt->close();

$stmt = $conn->prepare("SELECT allergy FROM user_allergies WHERE userid = ?");
$stmt->bind_param("i", $userid);
$stmt->execute();
$stmt->bind_result($allergies);
$stmt->fetch();
$stmt->close();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update allergies
    if (!empty($_POST['allergy'])) {
        $allergy = $_POST['allergy'];
        $stmt = $conn->prepare("INSERT INTO user_allergies (userid, allergy) VALUES (?, ?)");
        $stmt->bind_param("is", $userid, $allergy);
        $stmt->execute();
    }    

    // Update preferences
    if (!empty($_POST['preference'])) {
        $preference = $_POST['preference'];
        $stmt = $conn->prepare("INSERT INTO user_preferences (userid, preference) VALUES (?, ?)");
        $stmt->bind_param("is", $userid, $preference);
        $stmt->execute();
    }    

    // Update diseases
    $selected = $_POST['diseases'] ?? [];
    $now = date("Y-m-d H:i:s");

    // Handle new diseases
    foreach ($selected as $disease) {
        if (!in_array($disease, $currentDiseases)) {
            $stmt = $conn->prepare("INSERT INTO user_diseases (userid, disease, diagnosed_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $userid, $disease, $now);
            $stmt->execute();
        }
    }

    // Handle deselected (mark end time)
    foreach ($currentDiseases as $existingDisease) {
        if (!in_array($existingDisease, $selected)) {
            $stmt = $conn->prepare("UPDATE user_diseases SET disease_end_at = ? WHERE userid = ? AND disease = ? AND disease_end_at IS NULL");
            $stmt->bind_param("sis", $now, $userid, $existingDisease);
            $stmt->execute();
        }
    }

    // Map selected diseases to restrictions
    $combinedRestrictions = [];
    foreach ($selected as $disease) {
        if (isset($disease_to_restriction[$disease]) && $disease_to_restriction[$disease] !== NULL) {
            $combinedRestrictions[] = $disease_to_restriction[$disease];
        }
    }

    // Update restrictions
    if (!empty($combinedRestrictions)) {
        $final_restriction = implode(', ', $combinedRestrictions);
        $stmt = $conn->prepare("REPLACE INTO user_restrictions (userid, restriction) VALUES (?, ?)");
        $stmt->bind_param("is", $userid, $final_restriction);
        $stmt->execute();
    } else {
        // Optional: Delete existing restriction if diseases deselected
        $stmt = $conn->prepare("DELETE FROM user_restrictions WHERE userid = ?");
        $stmt->bind_param("i", $userid);
        $stmt->execute();
    }
    
    header("Location: myhealth.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>My Health</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    
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

        /* Main Content Styles */
        .container {
            max-width: 600px;
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
        input[type="email"],
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
        input[type="email"]:focus,
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
        <button type="button" onclick="window.location.href='profile.php'">Profile</button>
        <h2><i class="fas fa-heartbeat"></i> My Health Information</h2>

        <form method="POST">
        <div class="form-group">
            <label>Allergies</label>
            <input type="text" name="allergy" value="<?= htmlspecialchars($allergies) ?>" placeholder="e.g., Nuts, Shellfish">
        </div>
        <div class="form-group">
            <label>Food Preferences</label>
            <input type="text" name="preference" value="<?= htmlspecialchars($preferences) ?>" placeholder="e.g., Spicy, Vegetarian">
        </div>
        <div class="form-group">
            <label>Diseases (Select current)</label><br>
            <?php
            foreach ($disease_to_restriction as $disease => $restriction) {
                if ($disease === 'None') continue;
                $checked = in_array($disease, $currentDiseases) ? 'checked' : '';
                echo "<label><input type='checkbox' name='diseases[]' value='$disease' $checked> $disease</label><br>";
            }
            ?>
        </div>
        <button type="submit">Update Health Info</button>
    </form>

    <button onclick="toggleHistory()">Show/Hide Disease History</button>
        <div id="historyTable" style="display:none; margin-top:15px;">
            <h3>Disease History</h3>
            <table border="1" cellpadding="10">
                <thead>
                <tr><th>Disease</th><th>Diagnosed At</th><th>Disease Ended</th></tr>
                </thead>
                <tbody>
                <?php
                foreach ($diseaseHistory as $entry) {
                    echo "<tr>
                        <td>{$entry['disease']}</td>
                        <td>{$entry['diagnosed_at']}</td>
                        <td>" . ($entry['disease_end_at'] ?: '-') . "</td>
                    </tr>";
                }
                ?>
                </tbody>
            </table>
        </div>
    </div>


    <script>
function toggleHistory() {
    const table = document.getElementById("historyTable");
    table.style.display = table.style.display === "none" ? "block" : "none";
}
</script>
</body>
</html>
