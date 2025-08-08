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

//Get employee id from the link
if (isset($_GET['EmpID'])) {
    $empID = $_GET['EmpID'];
} else {
    header("Location: admViewAdmInfo.php?error=No admin selected");
    exit();
}

// Get admin data
$admin = array();

try {
    $stmt = $conn->prepare("
        SELECT 
            EmpID, FullName, StaffEmail, PersonalEmail, PhoneNo, 
            Gender, Status, Role, ProfilePic
        FROM EMPLOYEE 
        WHERE EmpID = ? AND Role = 'ADMIN'");
    $stmt->bind_param("s", $empID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
    } else {
        // No admin found with the given ID
        header("Location: admViewAdmInfo.php?error=" . urlencode("Admin with ID $empID not found."));
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting admin data: " . $e->getMessage());
}

// Default profile image if none exists
$profileImage = "https://cdn-icons-png.flaticon.com/512/8608/8608769.png";

// If profile pic exists in DB, convert to base64 for display
if (isset($admin['ProfilePic']) && $admin['ProfilePic']) {
    $profileImage = 'data:image/jpeg;base64,' . base64_encode($admin['ProfilePic']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - SHMS</title>
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
            --error: #dc3545;
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
        
        /* Profile Section */
        .profile-section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin: 30px 0;
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
            width: 100%;
        }
        
        .profile-header h2 {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .profile-pic-container {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto 20px;
        }
        
        .profile-pic {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--primary-color);
        }
        
        .fullname {
            font-size: 24px;
            font-weight: bold;
            color: var(--text-dark);
            margin: 10px 0;
        }
        
        /* Profile Info */
        .profile-info {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .info-group {
            margin-bottom: 30px;
        }
        
        .info-group h3 {
            color: var(--primary-color);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
            gap: 20px;
        }
        
        .form-group {
            flex: 1;
            min-width: 250px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: var(--text-light);
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 16px;
            background-color: #f7f7f7;
            cursor: not-allowed;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
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
        
        .btn-secondary {
            background-color: #6c757d;
            color: var(--white);
            margin-right: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .profile-section {
                padding: 20px;
            }
            
            .form-group {
                min-width: 100%;
            }
            
            .form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .profile-pic-container {
                width: 150px;
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/adminNav.php'; ?>
    
    <main class="container">
        <section class="profile-section">
            <div class="profile-header">
                <h2>Admin Profile</h2>
                <div class="profile-pic-container">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Picture" class="profile-pic" id="profilePic">
                </div>
                <div class="fullname"><?php echo htmlspecialchars($admin['FullName']); ?></div>
            </div>
            
            <div class="profile-info">
                <form>
                    <div class="info-group">
                        <h3>Personal Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fullName">Full Name</label>
                                <input type="text" id="fullName" value="<?php echo htmlspecialchars($admin['FullName']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <input type="text" id="gender" value="<?php echo $admin['Gender'] === 'M' ? 'Male' : 'Female'; ?>" readonly>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="personalEmail">Personal Email</label>
                                <input type="email" id="personalEmail" value="<?php echo htmlspecialchars(strtolower($admin['PersonalEmail'])); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="phoneNo">Phone Number</label>
                                <input type="tel" id="phoneNo" value="<?php echo htmlspecialchars($admin['PhoneNo']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <h3>Account Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="empID">Employee ID</label>
                                <input type="text" id="empID" value="<?php echo htmlspecialchars($admin['EmpID']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="role">Role</label>
                                <input type="text" id="role" value="<?php echo htmlspecialchars($admin['Role']); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="staffEmail">Staff Email</label>
                                <input type="email" id="staffEmail" value="<?php echo htmlspecialchars(strtolower($admin['StaffEmail'])); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <input type="text" id="status" value="<?php echo htmlspecialchars($admin['Status']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='admViewAdmInfo.php'">Back</button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html> 