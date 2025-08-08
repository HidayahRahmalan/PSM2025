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

//Get student id from the link
if (isset($_GET['StudID'])) {
    $studID = $_GET['StudID'];
} else if (isset($_POST['studID'])) {
    $studID = $_POST['studID'];
} else {
    header("Location: admViewStud.php?error=No student selected");
    exit();
}


// Get student data
$student = array();

try {
    $stmt = $conn->prepare("
        SELECT 
            StudID, FullName, StudEmail, PersonalEmail, PhoneNo, 
            MatricNo, Gender, Status, Faculty, Year, Semester, ProfilePic,
            RoomSharingStyle, ChronicIssueLevel, ChronicIssueName
        FROM STUDENT 
        WHERE StudID = ?");
    $stmt->bind_param("s", $studID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
    } else {
        // No student found with the given ID
        header("Location: admViewStud.php?error=" . urlencode("Student with ID $studID not found."));
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting student data: " . $e->getMessage());
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'updateProfile') {
    try {
        // Get student ID from POST data
        $studID = isset($_POST['studID']) ? $_POST['studID'] : '';
        
        // Get form data
        $fullName = isset($_POST['fullName']) ? strtoupper($_POST['fullName']) : '';
        $studEmail = isset($_POST['studEmail']) ? strtoupper($_POST['studEmail']) : '';
        $personalEmail = isset($_POST['personalEmail']) ? strtoupper($_POST['personalEmail']) : '';
        $phoneNo = isset($_POST['phoneNo']) ? $_POST['phoneNo'] : '';
        $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
        $faculty = isset($_POST['faculty']) ? $_POST['faculty'] : '';
        $status = isset($_POST['status']) ? strtoupper($_POST['status']) : '';
        $year = isset($_POST['year']) ? $_POST['year'] : '';
        $semester = isset($_POST['semester']) ? $_POST['semester'] : '';
        
        
        // Extract Matric Number from Student Email
        $matricNo = '';
        if (preg_match('/^([BbDd][0-9]{9})@student\.utem\.edu\.my$/i', $studEmail, $matches)) {
            $matricNo = strtoupper($matches[1]);
        } else {
            // If email format doesn't match, keep existing MatricNo
            $matricNo = $student['MatricNo'];
        }
        
        // Initialize validation errors array
        $validationErrors = array(
            'fullName' => '',
            'studEmail' => '',
            'personalEmail' => '',
            'phoneNo' => '',
            'gender' => '',
            'faculty' => '',
            'status' => '',
            'year' => '',
            'semester' => ''
        );
        
        $hasErrors = false;
        
        // Validate data
        if (empty($fullName)) {
            $validationErrors['fullName'] = "Full name is required";
            $hasErrors = true;
        }
        
        if (empty($studEmail)) {
            $validationErrors['studEmail'] = "Student email is required";
            $hasErrors = true;
        } elseif (!preg_match('/^[BbDd][0-9]{9}@student\.utem\.edu\.my$/i', $studEmail)) {
            $validationErrors['studEmail'] = "Student email must be (BXXXXXXXXX/DXXXXXXXXX)@student.utem.edu.my";
            $hasErrors = true;
        }
        
        if (empty($personalEmail)) {
            $validationErrors['personalEmail'] = "Personal email is required";
            $hasErrors = true;
        } elseif (!filter_var(strtolower($personalEmail), FILTER_VALIDATE_EMAIL)) {
            $validationErrors['personalEmail'] = "Please enter a valid personal email address (Eg: abc@gmail.com)";
            $hasErrors = true;
        }
        
        if (empty($phoneNo)) {
            $validationErrors['phoneNo'] = "Phone number is required";
            $hasErrors = true;
        } elseif (!preg_match('/^[0-9]{3}-[0-9]{7,8}$/', $phoneNo)) {
            $validationErrors['phoneNo'] = "Phone number must be XXX-XXXXXXXX format";
            $hasErrors = true;
        }
        
        if (empty($gender)) {
            $validationErrors['gender'] = "Gender is required";
            $hasErrors = true;
        }
        
        if (empty($faculty)) {
            $validationErrors['faculty'] = "Faculty is required";
            $hasErrors = true;
        }

        if (empty($year)) {
            $validationErrors['year'] = "Year is required";
            $hasErrors = true;
        }

        if (empty($semester)) {
            $validationErrors['semester'] = "Semester is required";
            $hasErrors = true;
        }
        
        // Check uniqueness for emails and phone - exclude current user
        if (!$hasErrors) {
            // Check student email
            $stmt = $conn->prepare("SELECT StudID FROM STUDENT WHERE StudEmail = ? AND StudID != ?");
            $stmt->bind_param("ss", $studEmail, $studID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $validationErrors['studEmail'] = "Student email already exists for another account";
                $hasErrors = true;
            }
            $stmt->close();
            
            // Check personal email
            $stmt = $conn->prepare("SELECT StudID FROM STUDENT WHERE PersonalEmail = ? AND StudID != ?");
            $stmt->bind_param("ss", $personalEmail, $studID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $validationErrors['personalEmail'] = "Personal email already exists for another account";
                $hasErrors = true;
            }
            $stmt->close();
            
            // Check phone number
            $stmt = $conn->prepare("SELECT StudID FROM STUDENT WHERE PhoneNo = ? AND StudID != ?");
            $stmt->bind_param("ss", $phoneNo, $studID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $validationErrors['phoneNo'] = "Phone number already exists for another account";
                $hasErrors = true;
            }
            $stmt->close();
        }
        
        // If no errors, update the profile
        if (!$hasErrors) {
            // Store old data for audit trail
            $old_data = json_encode([
                "FullName" => $student['FullName'],
                "StudEmail" => $student['StudEmail'],
                "PersonalEmail" => $student['PersonalEmail'],
                "PhoneNo" => $student['PhoneNo'],
                "Gender" => $student['Gender'],
                "Faculty" => $student['Faculty'],
                "MatricNo" => $student['MatricNo'],
                "Status" => $student['Status'],
                "Year" => $student['Year'],
                "Semester" => $student['Semester']
            ]);
            
            // New data for audit trail
            $new_data = json_encode([
                "FullName" => $fullName,
                "StudEmail" => $studEmail,
                "PersonalEmail" => $personalEmail,
                "PhoneNo" => $phoneNo,
                "Gender" => $gender,
                "Faculty" => $faculty,
                "MatricNo" => $matricNo,
                "Status" => $status,
                "Year" => $year,
                "Semester" => $semester
            ]);
            
            // Prepare and execute update query
            $stmt = $conn->prepare("
                UPDATE STUDENT 
                SET FullName = ?, StudEmail = ?, PersonalEmail = ?, PhoneNo = ?, Gender = ?, Faculty = ?, MatricNo = ?, Status = ?, Year = ?, Semester = ? 
                WHERE StudID = ?");
            $stmt->bind_param("sssssssssss", 
                $fullName, 
                $studEmail, 
                $personalEmail, 
                $phoneNo, 
                $gender, 
                $faculty, 
                $matricNo,
                $status,
                $year,
                $semester,
                $studID
            );
            $success = $stmt->execute();
            
            if ($success) {
                // Add to audit trail
                $audit_stmt = $conn->prepare("CALL add_audit_trail(?, ?, ?, ?, ?, ?)");
                $table_name = "STUDENT";
                $action = "UPDATE";
                $user_id = $_SESSION['empId'];
                
                $audit_stmt->bind_param("ssssss", $table_name, $studID, $action, $user_id, $old_data, $new_data);
                $audit_stmt->execute();
                $audit_stmt->close();
                
                // Fetch updated student data
                $stmt = $conn->prepare("
                    SELECT 
                        StudID, FullName, StudEmail, PersonalEmail, PhoneNo, 
                        MatricNo, Gender, Status, Faculty, Year, Semester, ProfilePic
                    FROM STUDENT 
                    WHERE StudID = ?");
                $stmt->bind_param("s", $studID);
                $stmt->execute();
                $result = $stmt->get_result();
                $student = $result->fetch_assoc();
                $stmt->close();
                
                // Update successful, redirect to refresh the page with the same student ID
                header("Location: admEditStud.php?StudID=" . urlencode($studID) . "&updated=true");
                exit();
            } else {
                // If update failed, redirect back with error
                header("Location: admEditStud.php?StudID=" . urlencode($studID) . "&error=Failed to update student information");
                exit();
            }
            
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error updating profile: " . $e->getMessage());
        $error = "An error occurred while updating your profile";
    }
}

// Default profile image if none exists
$profileImage = "https://cdn-icons-png.flaticon.com/512/8608/8608769.png";

// If profile pic exists in DB, convert to base64 for display
if (isset($student['ProfilePic']) && $student['ProfilePic']) {
    $profileImage = 'data:image/jpeg;base64,' . base64_encode($student['ProfilePic']);
}

// Add JavaScript function to validate the form
$jsValidation = true;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
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
        
        .edit-profile-pic {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background-color: var(--primary-color);
            color: var(--white);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .edit-profile-pic:hover {
            background-color: var(--secondary-color);
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
        }
        
        .form-group input:read-only {
            background-color: #f7f7f7;
            cursor: not-allowed;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(44, 157, 255, 0.2);
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
        
        /* Alerts */
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.2);
            color: var(--error);
            border: 1px solid var(--error);
        }
        
        .error-message {
            color: var(--error);
            font-size: 14px;
            margin-top: 5px;
            display: block;
        }
        
        /* Modal for profile picture upload */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: var(--white);
            margin: auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            box-shadow: var(--shadow);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h3 {
            color: var(--primary-color);
            margin: 0;
        }
        
        .close {
            color: var(--text-light);
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: var(--text-dark);
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        #image-container {
            max-width: 100%;
            height: 300px;
            margin: 0 auto 20px;
            overflow: hidden;
        }
        
        #cropImageBtn {
            display: none;
        }
        
        .file-input-container {
            position: relative;
            overflow: hidden;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .file-input-container input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-label {
            display: inline-block;
            padding: 10px 20px;
            background-color: var(--secondary-color);
            color: var(--white);
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .file-input-label:hover {
            background-color: var(--primary-color);
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
        }
        
        /* Responsive design */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
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
        <?php if (isset($_GET['updated']) && $_GET['updated'] === 'true'): ?>
        <div class="alert alert-success">
            Profile updated successfully!
        </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <section class="profile-section">
            <div class="profile-header">
                <h2>Student Profile</h2>
                <div class="profile-pic-container">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Picture" class="profile-pic" id="profilePic">
                </div>
                <div class="fullname"><?php echo htmlspecialchars($student['FullName']); ?></div>
            </div>
            
            <div class="profile-info">
                <form action="admEditStud.php" method="POST">
                    <input type="hidden" name="action" value="updateProfile">
                    <input type="hidden" name="studID" value="<?php echo htmlspecialchars($studID); ?>">
                    
                    <div class="info-group">
                        <h3>Personal Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fullName">Full Name</label>
                                <input type="text" id="fullName" name="fullName" value="<?php echo isset($_POST['fullName']) ? htmlspecialchars($_POST['fullName']) : htmlspecialchars($student['FullName']); ?>" required autocomplete="off">
                                <span id="fullName-error" class="error-message"><?php echo isset($validationErrors['fullName']) ? $validationErrors['fullName'] : ''; ?></span>
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" required>
                                    <option value="M" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'M') || (!isset($_POST['gender']) && $student['Gender'] === 'M') ? 'selected' : ''; ?>>Male</option>
                                    <option value="F" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'F') || (!isset($_POST['gender']) && $student['Gender'] === 'F') ? 'selected' : ''; ?>>Female</option>
                                </select>
                                <span id="gender-error" class="error-message"><?php echo isset($validationErrors['gender']) ? $validationErrors['gender'] : ''; ?></span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="personalEmail">Personal Email</label>
                                <input type="email" id="personalEmail" name="personalEmail" value="<?php echo isset($_POST['personalEmail']) ? htmlspecialchars($_POST['personalEmail']) : htmlspecialchars(strtolower($student['PersonalEmail'])); ?>" required autocomplete="off">
                                <span id="personalEmail-error" class="error-message"><?php echo isset($validationErrors['personalEmail']) ? $validationErrors['personalEmail'] : ''; ?></span>
                            </div>
                            <div class="form-group">
                                <label for="phoneNo">Phone Number (XXX-XXXXXXXX)</label>
                                <input type="tel" id="phoneNo" name="phoneNo" value="<?php echo isset($_POST['phoneNo']) ? htmlspecialchars($_POST['phoneNo']) : htmlspecialchars($student['PhoneNo']); ?>" required maxlength="12" autocomplete="off">
                                <span id="phoneNo-error" class="error-message"><?php echo isset($validationErrors['phoneNo']) ? $validationErrors['phoneNo'] : ''; ?></span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="flex: 1; width: 100%;">
                                <label for="roomSharingStyle">Room Sharing Style</label>
                                <input type="text" id="roomSharingStyle" value="<?php echo htmlspecialchars($student['RoomSharingStyle'] ?? 'NOT SPECIFIED'); ?>" readonly style="width: 100%; overflow: visible;">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="chronicIssueLevel">Chronic Issue Level</label>
                                <input type="text" id="chronicIssueLevel" value="<?php echo htmlspecialchars($student['ChronicIssueLevel'] ?? 'NONE'); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="chronicIssueName">Chronic Issue Name</label>
                                <input type="text" id="chronicIssueName" value="<?php echo htmlspecialchars($student['ChronicIssueName'] ?? 'NONE'); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <h3>Academic Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="studID">Student ID</label>
                                <input type="text" id="studID" value="<?php echo htmlspecialchars($student['StudID']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="matricNo">Matric Number</label>
                                <input type="text" id="matricNo" value="<?php echo htmlspecialchars($student['MatricNo']); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="studEmail">Student Email</label>
                                <input type="email" id="studEmail" name="studEmail" value="<?php echo isset($_POST['studEmail']) ? htmlspecialchars($_POST['studEmail']) : htmlspecialchars(strtolower($student['StudEmail'])); ?>" required autocomplete="off">
                                <span id="studEmail-error" class="error-message"><?php echo isset($validationErrors['studEmail']) ? $validationErrors['studEmail'] : ''; ?></span>
                            </div>
                            <div class="form-group">
                                <label for="faculty">Faculty</label>
                                <select id="faculty" name="faculty" required>
                                    <option value="" disabled <?php echo empty($student['Faculty']) && !isset($_POST['faculty']) ? 'selected' : ''; ?>>Select your faculty</option>
                                    <option value="FTKEK" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] === 'FTKEK') || (!isset($_POST['faculty']) && $student['Faculty'] === 'FTKEK') ? 'selected' : ''; ?>>Faculty of Electronics and Computer Technology and Engineering (FTKEK)</option>
                                    <option value="FTKE" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] === 'FTKE') || (!isset($_POST['faculty']) && $student['Faculty'] === 'FTKE') ? 'selected' : ''; ?>>Faculty of Electrical Technology and Engineering (FTKE)</option>
                                    <option value="FTKM" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] === 'FTKM') || (!isset($_POST['faculty']) && $student['Faculty'] === 'FTKM') ? 'selected' : ''; ?>>Faculty of Mechanical Technology and Engineering (FTKM)</option>
                                    <option value="FTKIP" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] === 'FTKIP') || (!isset($_POST['faculty']) && $student['Faculty'] === 'FTKIP') ? 'selected' : ''; ?>>Faculty of Industrial and Manufacturing Technology and Engineering (FTKIP)</option>
                                    <option value="FTMK" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] === 'FTMK') || (!isset($_POST['faculty']) && $student['Faculty'] === 'FTMK') ? 'selected' : ''; ?>>Faculty Of Information And Communications Technology (FTMK)</option>
                                    <option value="FPTT" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] === 'FPTT') || (!isset($_POST['faculty']) && $student['Faculty'] === 'FPTT') ? 'selected' : ''; ?>>Faculty Of Technology Management And Technopreneurship (FPTT)</option>
                                    <option value="FAIX" <?php echo (isset($_POST['faculty']) && $_POST['faculty'] === 'FAIX') || (!isset($_POST['faculty']) && $student['Faculty'] === 'FAIX') ? 'selected' : ''; ?>>Faculty of Artificial Intelligence and Cyber Security (FAIX)</option>
                                </select>
                                <span id="faculty-error" class="error-message"><?php echo isset($validationErrors['faculty']) ? $validationErrors['faculty'] : ''; ?></span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="ACTIVE" <?php echo (isset($_POST['status']) && $_POST['status'] === 'ACTIVE') || (!isset($_POST['status']) && $student['Status'] === 'ACTIVE') ? 'selected' : ''; ?>>Active</option>
                                    <option value="INACTIVE" <?php echo (isset($_POST['status']) && $_POST['status'] === 'INACTIVE') || (!isset($_POST['status']) && $student['Status'] === 'INACTIVE') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <span id="status-error" class="error-message"><?php echo isset($validationErrors['status']) ? $validationErrors['status'] : ''; ?></span>
                            </div>
                            <div class="form-group">
                                <label for="year">Year</label>
                                <select id="year" name="year" required>
                                    <option value="1" <?php echo $student['Year'] == 1 ? 'selected' : ''; ?>>1</option>
                                    <option value="2" <?php echo $student['Year'] == 2 ? 'selected' : ''; ?>>2</option>
                                    <option value="3" <?php echo $student['Year'] == 3 ? 'selected' : ''; ?>>3</option>
                                    <option value="4" <?php echo $student['Year'] == 4 ? 'selected' : ''; ?>>4</option>
                                </select>
                                <span id="year-error" class="error-message"><?php echo isset($validationErrors['year']) ? $validationErrors['year'] : ''; ?></span>
                            </div>
                            <div class="form-group">
                                <label for="semester">Semester</label>
                                <select id="semester" name="semester" required>
                                    <option value="1" <?php echo $student['Semester'] == 1 ? 'selected' : ''; ?>>1</option>
                                    <option value="2" <?php echo $student['Semester'] == 2 ? 'selected' : ''; ?>>2</option>
                                    <option value="3" <?php echo $student['Semester'] == 3 ? 'selected' : ''; ?>>3</option>
                                </select>
                                <span id="semester-error" class="error-message"><?php echo isset($validationErrors['semester']) ? $validationErrors['semester'] : ''; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='admViewStud.php'">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </section>
    </main>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        
        // Function to extract matric number from student email
        function extractMatricNo() {
            const emailInput = document.getElementById('studEmail');
            const matricInput = document.getElementById('matricNo');
            
            // Extract matric number from email if it matches the pattern
            const emailPattern = /^([BbDd][0-9]{9})@student\.utem\.edu\.my$/i;
            const match = emailInput.value.match(emailPattern);
            
            if (match) {
                matricInput.value = match[1].toUpperCase();
            }
        }
        
        // Add event listener to extract matric no when email changes
        document.getElementById('studEmail').addEventListener('change', extractMatricNo);
        
        // Function to validate the form before submission
        document.querySelector('form[action="admEditStud.php"]').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Clear all previous error messages
            document.querySelectorAll('.error-message').forEach(function(el) {
                el.textContent = '';
            });
            
            let hasErrors = false;
            
            // Validate full name
            const fullName = document.getElementById('fullName').value.trim();
            if (!fullName) {
                document.getElementById('fullName-error').textContent = 'Full name is required';
                hasErrors = true;
            }
            
            // Validate student email format
            const studEmail = document.getElementById('studEmail').value.trim();
            const studEmailPattern = /^[BbDd][0-9]{9}@student\.utem\.edu\.my$/i;
            if (!studEmail) {
                document.getElementById('studEmail-error').textContent = 'Student email is required';
                hasErrors = true;
            } else if (!studEmailPattern.test(studEmail)) {
                document.getElementById('studEmail-error').textContent = 'Student email must be (BXXXXXXXXX/DXXXXXXXXX)@student.utem.edu.my';
                hasErrors = true;
            }
            
            // Validate personal email format
            const personalEmail = document.getElementById('personalEmail').value.trim();
            const emailPattern = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
            if (!personalEmail) {
                document.getElementById('personalEmail-error').textContent = 'Personal email is required';
                hasErrors = true;
            } else if (!emailPattern.test(personalEmail)) {
                document.getElementById('personalEmail-error').textContent = 'Please enter a valid personal email address (Eg: abc@gmail.com)';
                hasErrors = true;
            }
            
            // Validate phone number format
            const phoneNo = document.getElementById('phoneNo').value.trim();
            const phonePattern = /^[0-9]{3}-[0-9]{7,8}$/;
            if (!phoneNo) {
                document.getElementById('phoneNo-error').textContent = 'Phone number is required';
                hasErrors = true;
            } else if (!phonePattern.test(phoneNo)) {
                document.getElementById('phoneNo-error').textContent = 'Phone number must be XXX-XXXXXXXX format';
                hasErrors = true;
            }
            
            // Validate faculty
            const faculty = document.getElementById('faculty').value;
            if (!faculty) {
                document.getElementById('faculty-error').textContent = 'Faculty is required';
                hasErrors = true;
            }
            
            // Extract matric no from email before submission
            extractMatricNo();
            
            // If no validation errors, submit the form
            if (!hasErrors) {
                this.submit();
            }
        });
    </script>
</body>
</html>