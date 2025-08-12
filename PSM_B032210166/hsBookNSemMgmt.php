<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOSTEL STAFF') {
    header("Location: staffMainPage.php");
    exit();
}

// Get hostel staff name
$hsName = "";
try {
    $stmt = $conn->prepare("SELECT FullName FROM EMPLOYEE WHERE EmpID = ?");
    $stmt->bind_param("s", $_SESSION['empId']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $hsName = $row['FullName'];
    } else {
        // Not a hostel staff, redirect
        header("Location: staffMainPage.php");
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting hostel staff data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hs Booking and Semester Management - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/hsNav.css">
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
        }
        
        .management-card:hover .card-image img {
            transform: scale(1.05);
        }
        
        .card-content {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .card-content h3 {
            color: var(--primary-color);
            font-size: 22px;
            margin-bottom: 10px;
        }
        
        .card-content p {
            color: var(--text-light);
            margin-bottom: 20px;
        }
        
        .card-action {
            text-align: center;
            padding: 0 20px 20px;
            margin-top: auto;
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
        
        /* Card Icons */
        .card-icon {
            font-size: 24px;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .management-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/hsNav.php'; ?>

    <main class="container main-content">
        <section class="page-header">
            <h2>Booking & Semester Management</h2>
            <p>Manage room bookings and semester information</p>
        </section>

        <section class="management-grid">
            <!-- Booking Management Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://www.shutterstock.com/shutterstock/videos/1095072633/thumb/11.jpg?ip=x480" alt="Booking Management">
                </div>
                <div class="card-content">
                    <h3><i class="fas fa-bed card-icon"></i>Booking Management</h3>
                    <p>View, update or manage students' room bookings. Process new bookings, update booking status, and manage room assignments for students.</p>
                </div>
                <div class="card-action">
                    <a href="hsBookMgmt.php" class="btn btn-primary">Manage Bookings</a>
                </div>
            </div>

            <!-- Semester Management Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://media.istockphoto.com/id/1241712498/vector/businessman-planning-events-deadlines-and-agenda.jpg?s=612x612&w=0&k=20&c=_YYsu8T3tK8Uc0W3zUQTqvkkNf-4PU1JCjHYBbXZ1ok=" alt="Semester Management">
                </div>
                <div class="card-content">
                    <h3><i class="fas fa-calendar-alt card-icon"></i>Semester Management</h3>
                    <p>View semester information including start/end dates, check-in/check-out periods, and academic years. Keep track of semester timelines.</p>
                </div>
                <div class="card-action">
                    <a href="hsViewSem.php" class="btn btn-primary">View Semesters</a>
                </div>
            </div>
        </section>
    </main>
</body>
</html> 