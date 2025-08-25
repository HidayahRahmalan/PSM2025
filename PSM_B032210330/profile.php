<?php
session_start();
include 'DBConnection.php';

if (!isset($_SESSION['username'])) {
    header('Location: LogIn.php');
    exit();
}

$username = $_SESSION['username'];
$id = $_SESSION['id'];

$db = new DBConnection();
$conn = $db->getConnection();

// Fetch user details
$sql = "SELECT * FROM user WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = $_POST['fullName'];
    $age = $_POST['age'];
    $height = $_POST['height'];
    $gender = $_POST['gender'];
    $email = $_POST['email'];

    $update_sql = "UPDATE user SET fullName = ?, age = ?, height = ?, gender = ?, email = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sdsssi", $fullName, $age, $height, $gender, $email, $id);

    if ($update_stmt->execute()) {
        $success_message = "Profile updated successfully!";
        // Refresh user data
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
    } else {
        $error_message = "Failed to update profile: " . $conn->error;
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - NutriEats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    
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

    <main class="container">
        <button type="button" onclick="window.location.href='Homepage.php'">Back</button>
        <button type="button" onclick="window.location.href='myhealth.php'">My Health</button>
        <button type="button" onclick="window.location.href='userstat.php'">My Stats</button>
        <h2>My Profile</h2>
        <?php if (isset($success_message)) echo "<div class='message'>$success_message</div>"; ?>
        <?php if (isset($error_message)) echo "<div class='error'>$error_message</div>"; ?>

        <form method="POST">
            <label for="fullName">Full Name:</label>
            <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($user['fullName']); ?>" required>

            <label for="username">Username (Read-Only):</label>
            <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>

            <label for="age">Age:</label>
            <input type="number" id="age" name="age" value="<?php echo htmlspecialchars($user['age']); ?>" min="1" max="150" required>

            <label for="height">Height (cm):</label>
            <input type="number" id="height" name="height" value="<?php echo htmlspecialchars($user['height']); ?>" min="1" max="300" step="0.01" required>

            <label for="gender">Gender:</label>
            <select name="gender" id="gender" required>
                <option value="Male" <?php if ($user['gender'] === 'Male') echo 'selected'; ?>>Male</option>
                <option value="Female" <?php if ($user['gender'] === 'Female') echo 'selected'; ?>>Female</option>
            </select>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>

            <button type="submit">Update Profile</button>
        </form>
    </main>

    <script>
        
    </script>
</body>

</html>
