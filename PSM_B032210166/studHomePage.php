<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Include semester check function
include 'check_and_update_semester.php';

// Redirect if not logged in
if (!isset($_SESSION['studID'])) {
    header("Location: studMainPage.php");
    exit();
}

// Check if semester has changed and update student progress if needed
$semesterUpdated = checkAndUpdateSemester($conn);

// Get current semester information
$currentSemester = array(
    'AcademicYear' => 'N/A',
    'Semester' => 'N/A'
);

try {
    $stmt = $conn->prepare("
        SELECT AcademicYear, Semester 
        FROM SEMESTER 
        WHERE CURDATE() BETWEEN DATE_SUB(CheckInDate, INTERVAL 1 WEEK) 
                          AND CheckOutDate
        LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $currentSemester = $result->fetch_assoc();
    }
    
    $stmt->close();
} catch (Exception $e) {
    // Log error but continue with default values
    error_log("Error getting semester data: " . $e->getMessage());
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
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting student name: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Home - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/studentNav.css">
    <style>

        
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
        
        .main-content {
            padding: 30px 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Remove redundant navigation styles that might conflict with studentNav.css */
        /* Header and Navigation styles are now handled by studentNav.css */
        
        /* Semester Information */
        .semester-info {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 15px 0;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .semester-info p {
            font-size: 18px;
            font-weight: bold;
        }
        
        /* Main Content */
        .welcome-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .welcome-section h2 {
            font-size: 32px;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .welcome-section p {
            font-size: 18px;
            color: var(--text-light);
            max-width: 700px;
            margin: 0 auto;
        }
        
        /* Services Section */
        .services-section {
            margin-bottom: 50px;
        }
        
        .main-service {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
            transition: transform 0.3s ease;
        }
        
        .main-service:hover {
            transform: translateY(-5px);
        }
        
        .main-service img {
            width: 100%;
            max-width: 400px;
            height: 250px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .main-service h3 {
            font-size: 24px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .main-service p {
            margin-bottom: 20px;
            color: var(--text-light);
        }
        
        .service-btn {
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .service-btn:hover {
            background-color: var(--secondary-color);
        }
        
        .secondary-services {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
        }
        
        .service-card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 30px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
        }
        
        .service-card img {
            width: 100%;
            max-width: 300px;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .service-card h3 {
            font-size: 20px;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .service-card p {
            margin-bottom: 20px;
            color: var(--text-light);
        }
        
        /* Footer */
        footer {
            background-color: var(--primary-color);
            color: var(--white);
            text-align: center;
            padding: 20px 0;
            margin-top: 50px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .secondary-services {
                grid-template-columns: 1fr;
            }
            
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .logo h1 {
                font-size: 20px;
            }
            
            .service-card, .main-service {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/studentNav.php'; ?>
    
    <div class="container main-content">
        <div class="semester-info">
            <div class="container">
                <p>Current Academic Year: <?php echo htmlspecialchars($currentSemester['AcademicYear']); ?> | 
                   Semester: <?php echo htmlspecialchars($currentSemester['Semester']); ?></p>
            </div>
        </div>
        
        <main class="container">
            <section class="welcome-section">
                <h2>Welcome, <?php echo htmlspecialchars($studentName); ?></h2>
                <p>Manage your hostel experience with ease using our Smart Hostel Management System.</p>
            </section>
            
            <section class="services-section">
                <div class="main-service">
                    <img src="https://cdn.vectorstock.com/i/500p/32/64/female-dormitory-roommates-live-together-vector-36123264.jpg" alt="Room Booking">
                    <h3>Room and Semester</h3>
                    <p>Book your hostel room for the upcoming semester or request a room change.</p>
                    <p>View current semester information.</p>
                    <button class="service-btn" onclick="window.location.href='studBookNSem.php'">Room & Semester</button>
                </div>
                
                <div class="secondary-services">
                    <div class="service-card">
                        <img src="https://img.freepik.com/premium-vector/woman-are-asking-customer-help-center-illustration_588233-288.jpg" alt="Maintenance">
                        <h3>Maintenance & Complaint</h3>
                        <p>Report maintenance issues or submit complaints.</p>
                        <button class="service-btn" onclick="window.location.href='studMainNComplaint.php'">Maintenance & Complaint</button>
                    </div>
                    
                    <div class="service-card">
                        <img src="https://st5.depositphotos.com/2466369/66620/i/450/depositphotos_666201606-stock-photo-employee-hiring-process-concept-people.jpg" alt="Payment">
                        <h3>Payment</h3>
                        <p>View and manage all your hostel-related payments.</p>
                        <button class="service-btn" onclick="window.location.href='studPayment.php'">Payment</button>
                    </div>
                </div>
            </section>
        </main>
    </div>
    
    <footer>
        <div class="container">
            
        </div>
    </footer>
</body>
</html> 