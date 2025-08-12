<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['empId']) || !isset($_SESSION['role'])) {
    header("Location: staffMainPage.php");
    exit();
}

// Determine which nav files to include based on role
if ($_SESSION['role'] === 'HOSTEL STAFF') {
    $navCssFile = "css/hsNav.css";
    $navPhpFile = "includes/hsNav.php";
    $profilePage = "hsProfile.php";
    $userMgmtPage = "hsUserMgmt.php";
    $bookingPage = "hsBookNSemMgmt.php";
    $hostelRoomPage = "hsHostelRoomMgmt.php";
    $maintenancePage = "hsMainNComplaint.php";
    $paymentPage = "hsPymtMgmt.php";

} elseif ($_SESSION['role'] === 'ADMIN') {
    $navCssFile = "css/adminNav.css";
    $navPhpFile = "includes/adminNav.php";
    $profilePage = "admProfile.php";
    $userMgmtPage = "admUserMgmt.php";
    $bookingPage = "admBookNSemMgmt.php";
    $hostelRoomPage = "admHostelRoomMgmt.php";
    $maintenancePage = "admMainNComplaint.php";
    $paymentPage = "admPymtMgmt.php";
}

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

// Get staff name
$staffName = "";
try {
    $stmt = $conn->prepare("SELECT FullName FROM EMPLOYEE WHERE EmpID = ?");
    $stmt->bind_param("s", $_SESSION['empId']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $staffName = $row['FullName'];
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting staff name: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo $navCssFile; ?>">
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
        
        /* Container styles already in nav CSS */
        
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
            
            .service-card, .main-service {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include $navPhpFile; ?>
    
    <div class="semester-info">
        <div class="container">
            <p>Current Academic Year: <?php echo htmlspecialchars($currentSemester['AcademicYear']); ?> | 
               Semester: <?php echo htmlspecialchars($currentSemester['Semester']); ?></p>
        </div>
    </div>

    <main class="container main-content">
        <section class="welcome-section">
            <h2>Welcome, <?php echo htmlspecialchars($staffName); ?></h2>
            <p>Manage the hostel system efficiently with your dashboard.</p>
        </section>
        
        <section class="services-section">
            <div class="main-service">
            <img src="https://cdn.vectorstock.com/i/500p/01/74/hospitality-hotel-hostel-reception-manager-vector-33700174.jpg" alt="Booking & Requests">
                <h3>Booking and Semester Management</h3>
                <p style="margin-bottom: 10px;">Handle all related student room bookings and requests.</p>
                <p style="margin-top: 0px;">Semester management is also available.</p>
                <button class="service-btn" onclick="window.location.href='<?php echo $bookingPage; ?>'">Manage Bookings and Semester</button>
            </div>

            <div class="secondary-services">
                <div class="service-card">
                    <img src="https://measurepm.com/images/account_management.jpg" alt="User Management">
                    <h3>User Management</h3>
                    <p>Manage student and staff accounts.</p>
                    <button class="service-btn" onclick="window.location.href='<?php echo $userMgmtPage; ?>'">Manage Users</button>
                </div>
                
                <div class="service-card">
                    <img src="https://static.vecteezy.com/system/resources/thumbnails/043/064/816/small/flat-student-dormitory-room-or-hostel-university-or-college-dorm-bedroom-empty-interior-with-bunk-bed-desk-at-window-chair-and-bookshelf-living-apartment-or-accommodation-with-wooden-furniture-vector.jpg" alt="Hostel & Room Management">
                    <h3>Hostel & Room Management</h3>
                    <p>Manage hostel and room.</p>
                    <button class="service-btn" onclick="window.location.href='<?php echo $hostelRoomPage; ?>'">Manage Rooms</button>
                </div>

                <div class="service-card">
                    <img src="https://img.freepik.com/premium-vector/woman-are-asking-customer-help-center-illustration_588233-288.jpg" alt="Maintenance & Complaint">
                    <h3>Maintenance & Complaint Management</h3>
                    <p>Manage maintenance requests and student complaints.</p>
                    <button class="service-btn" onclick="window.location.href='<?php echo $maintenancePage; ?>'">Manage Maintenance & Complaints</button>
                </div>

                <div class="service-card">
                    <img src="https://static.vecteezy.com/system/resources/previews/004/449/885/non_2x/online-banking-account-flat-illustration-digital-wallet-ewallet-services-cartoon-concept-financial-management-ebanking-transactions-internet-billing-system-isolated-metaphor-on-white-vector.jpg" alt="Payment Management">
                    <h3>Payment Management</h3>
                    <p>Monitor students' hostel payments.</p>
                    <button class="service-btn" onclick="window.location.href='<?php echo $paymentPage; ?>'">Manage Payments</button>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
