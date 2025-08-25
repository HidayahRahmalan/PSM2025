<?php
session_start(); // Start the session

// Include the database connection file
include 'DBConnection.php';

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the form data
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Create a new instance of the DBConnection class
    $db = new DBConnection();
    $conn = $db->getConnection();

    // Prepare and execute the SQL statement to fetch the user
    $stmt = $conn->prepare("SELECT id, password, role FROM user WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Verify the password
        if (password_verify($password, $row['password'])) {
            // Password is correct, set session variable
            $_SESSION['username'] = $username;
            $_SESSION['id'] = $row['id'];
            $_SESSION['role'] = $row['role'];

            // Redirect based on role
            if ($row['role'] === 'admin') {
              header('Location: admin/adminpage.php');
              exit();
          } else if ($row['role'] === 'nuser') {
            header('Location: Homepage.php');
            exit();
          } else {
            $error_message = 'Invalid user role.';
          }

        } else {
            // Password is incorrect
            $error_message = 'Username or Password does not match.';
        }
    } else {
        // Username not found
        $error_message = 'Username or Password does not match.';
    }

    // Close the statement and connection
    $stmt->close();
    $db->closeConnection();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Log In - NutriEats</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <style>
    *,
    *::before,
    *::after {
      box-sizing: border-box;
    }

    html, body {
    height: 100%; /* Ensure the html and body take full height */
    margin: 0; /* Remove default margin */
}

    body {
        font-family: Arial, sans-serif;
        background: url('assets/background1.jpg') no-repeat center center fixed; /* Fixed background */
        background-size: cover; /* Cover the entire viewport */
        overflow: hidden; /* Prevent scrolling */
    }

    .header {
      display: flex;
      justify-content: center;
      align-items: center;
      font-family: 'Libre Baskerville', serif;
      font-weight: 700;
      height: 70px;
      background-color: #212226;
    }

    .logo-container {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
    }

    a.logo-container {
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 10px;
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

    .container {
      max-width: 400px;
      margin: 50px auto;
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
    input[type="password"],
    input[type="email"] {
      width: 100%;
      padding: 10px 12px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 4px;
      font-size: 16px;
      font-family: inherit;
      outline-offset: 2px;
      outline-color: #b8860b;
      transition: border-color 0.3s ease;
    }

    input[type="text"]:focus,
    input[type="password"]:focus,
    input[type="email"]:focus {
      border-color: #b8860b;
    }

    .password-wrapper {
      position: relative;
      display: flex;
      align-items: center;
      width: 100%;
    }

    /* Remove padding-right default for inputs in password-wrapper, override with padding to avoid overlap */
    .password-wrapper input {
      padding-right: 40px;
      font-size: 16px;
      font-family: inherit;
      flex-grow: 1;
      border: 1px solid #ccc;
      border-radius: 4px;
      outline-offset: 2px;
      outline-color: #b8860b;
      transition: border-color 0.3s ease;
    }

    .password-wrapper input:focus {
      border-color: #b8860b;
    }

    .toggle-password {
      position: absolute;
      right: 10px;
      background: transparent;
      border: none;
      cursor: pointer;
      color: #daa520;
      font-size: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 0;
      user-select: none;
      outline-offset: 2px;
    }

    button#loginButton {
      width: 100%;
      padding: 12px;
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

    button#loginButton:hover {
      background-color: #daaa20;
    }

    p {
      text-align: center;
      margin: 15px 0 0;
      font-size: 14px;
    }

    p a {
      color: #b8860b;
      text-decoration: none;
      font-weight: 600;
    }

    p a:hover,
    p a:focus {
      text-decoration: underline;
    }
  </style>
</head>

<body>
  <header class="header">
    <a href="index.php" class="logo-container" aria-label="NutriEats Home">
        <img src="assets/NutriEats.png" alt="NutriEats Logo" class="logo" />
      <span class="site-title">NutriEats</span>
    </a>
  </header>

  <main class="container">
    <h2>Login</h2>
    <form id="loginForm" method="POST" novalidate>
      <label for="username">Username:</label>
      <input type="text" id="username" name="username" required />

      <label for="password">Password:</label>
      <div class="password-wrapper">
        <input type="password" id="password" name="password" required />
        <button type="button" class="toggle-password" data-target="password" aria-label="Toggle password visibility">
          <i class="fa-solid fa-eye"></i>
        </button>
      </div>

      <button type="submit" id="loginButton">Log In</button>
    </form>
    <p>Don't have an account? <a href="SignUp.php">Register here</a></p>
    <?php if (isset($error_message)): ?>
      <p style="color: red; text-align: center;"><?php echo htmlspecialchars($error_message); ?></p>
    <?php endif; ?>
  </main>

  <script>
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach((button) => {
      button.addEventListener('click', () => {
        const targetId = button.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = button.querySelector('i');

        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        } else {
          input.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        }
      });
    });
  </script>
</body>
</html>
