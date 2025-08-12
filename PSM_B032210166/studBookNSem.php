<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not student
if (!isset($_SESSION['studID'])) {
    header("Location: studMainPage.php");
    exit();
}

// Get student name
$studentName = "";
try {
    $stmt = $conn->prepare("SELECT FullName FROM STUDENT WHERE StudID = ?");
    $stmt->bind_param("s", $_SESSION['studID']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $studentName = $row['FullName'];
    } else {
        // Not a student, redirect
        header("Location: studMainPage.php");
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting student data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Room & Semester - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/studentNav.css">
    <style>

        .main-content {
            padding: 30px 0;
        }
        
        /* Keep all other page-specific styles */
        :root {
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
    <?php include 'includes/studentNav.php'; ?>
    
    <div class="container main-content">
        <div class="page-header">
            <h2>Room and Semester</h2>
        </div>

        <div class="management-grid">
            <!-- Room Booking Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://img.freepik.com/premium-vector/rooms-available-illustration-cartoon-character-girl-check-desk-hotel-hostel_53562-10112.jpg" alt="Room Booking">
                </div>
                <div class="card-content">
                    <h3>Room Booking</h3>
                    <p>Book your room for the upcoming semester</p>
                    <p>View and manage your current bookings</p>
                </div>
                <div class="card-action">
                    <a href="studRoomBook.php" class="btn btn-primary">Make Booking</a>
                </div>
            </div>

            <!-- Semester Management Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://media.istockphoto.com/id/1241712498/vector/businessman-planning-events-deadlines-and-agenda.jpg?s=612x612&w=0&k=20&c=_YYsu8T3tK8Uc0W3zUQTqvkkNf-4PU1JCjHYBbXZ1ok=" alt="Semester Management">
                </div>
                <div class="card-content">
                    <h3>Semester Information</h3>
                    <p>View current semester information</p>
                    <br>
                </div>
                <div class="card-action">
                    <a href="studViewSem.php" class="btn btn-primary">View Semester</a>
                </div>
            </div>

            <!-- Room Change Request Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://static.vecteezy.com/system/resources/previews/047/783/089/non_2x/a-cartoon-illustration-of-a-smiling-customer-service-representative-wearing-a-headset-and-using-a-laptop-computer-at-a-desk-free-vector.jpg" alt="Room Booking">
                </div>
                <div class="card-content">
                    <h3>Room Change Request</h3>
                    <p>Change your room for current semester</p>
                    <br>
                </div>
                <div class="card-action">
                    <a href="studRoomChange.php" class="btn btn-primary">Room Change Request</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 