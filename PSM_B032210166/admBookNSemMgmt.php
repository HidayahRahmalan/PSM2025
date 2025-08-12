<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: staffMainPage.php");
    exit();
}

// Get admin name
$adminName = "";
try {
    $stmt = $conn->prepare("SELECT FullName FROM EMPLOYEE WHERE EmpID = ?");
    $stmt->bind_param("s", $_SESSION['empId']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $adminName = $row['FullName'];
    } else {
        // Not a admin, redirect
        header("Location: staffMainPage.php");
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting admin data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Booking & Semester Management - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/adminNav.css">
    <style>
        :root {
            --primary-color: #25408f;
            --secondary-color: #3883ce;
            --accent-color: #2c9dff;
            --light-bg: #f0f8ff;
            --white: #ffffff;
            --text-dark: #333333;
            --text-light: #666666;
            --border-color: #ddd;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header and Navigation */
        header {
            background-color: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 50px;
            margin-right: 10px;
        }
        
        .logo h1 {
            color: var(--primary-color);
            font-size: 24px;
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-links a {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        
        .nav-links a:hover, .nav-links a.active {
            color: var(--accent-color);
        }
        
        .profile-icon {
            cursor: pointer;
            position: relative;
        }
        
        .profile-icon img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }
        
        /* Page Header */
        .page-header {
            text-align: center;
            margin: 30px 0;
        }
        
        .page-header h2 {
            color: var(--primary-color);
            font-size: 28px;
            margin-top: 25px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: var(--text-light);
            font-size: 16px;
        }
        
        /* Management Cards */
        .management-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .management-card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .management-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }
        
        .card-image {
            height: 250px;
            width: 100%;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f5f5f5;
        }
        
        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.5s ease;
            display: block;
        }
        
        .management-card:hover .card-image img {
            transform: scale(1.05);
        }
        
        .card-content {
            padding: 20px;
        }
        
        .card-content h3 {
            color: var(--primary-color);
            font-size: 22px;
            margin-bottom: 10px;
        }
        
        .card-content p {
            color: var(--text-light);
            margin-bottom: 5px;
        }
        
        .card-action {
            text-align: center;
            padding-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <?php include 'includes/adminNav.php'; ?>
    
    <main class="container main-content">
        <div class="page-header">
            <h2>Booking and Semester Management</h2>
        </div>

        <div class="management-grid">
            <!-- Booking Management Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://www.shutterstock.com/shutterstock/videos/1095072633/thumb/11.jpg?ip=x480" alt="Booking Management">
                </div>
                <div class="card-content">
                    <h3>Booking Management</h3>
                    <p>View and manage your current bookings.</p>
                </div>
                <div class="card-action">
                    <a href="admBookMgmt.php" class="btn btn-primary">Manage Bookings</a>
                </div>
            </div>

            <!-- Semester Management Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://media.istockphoto.com/id/1241712498/vector/businessman-planning-events-deadlines-and-agenda.jpg?s=612x612&w=0&k=20&c=_YYsu8T3tK8Uc0W3zUQTqvkkNf-4PU1JCjHYBbXZ1ok=" alt="Semester Management">
                </div>
                <div class="card-content">
                    <h3>Semester Management</h3>
                    <p>View and manage current semester information.</p>
                    <br>
                </div>
                <div class="card-action">
                    <a href="admViewSem.php" class="btn btn-primary">View Semester</a>
                </div>
            </div>

            <!-- Room Report Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://thumbs.dreamstime.com/b/internal-report-cartoon-flat-illustration-company-audit-finances-analyzing-diverse-team-members-discussing-corporate-charts-d-349634090.jpg" alt="Booking Management">
                </div>
                <div class="card-content">
                    <h3>Reports</h3>
                    <p>View reports for semesters.</p>
                </div>
                <div class="card-action">
                    <a href="admViewReport.php" class="btn btn-primary">View Reports</a>
                </div>
            </div>

        </div>
    </main>
</body>
</html> 