<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in
if (!isset($_SESSION['studID'])) {
    header("Location: studMainPage.php");
    exit();
}

// Get student data
$student = array();

try {
    $stmt = $conn->prepare("
        SELECT 
            StudID, FullName, StudEmail, PersonalEmail, PhoneNo, 
            MatricNo, Gender, Status, Faculty, Year, Semester, ProfilePic, RoomSharingStyle,
            ChronicIssueLevel, ChronicIssueName
        FROM STUDENT 
        WHERE StudID = ?");
    $stmt->bind_param("s", $_SESSION['studID']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
    } else {
        // If student not found, something's wrong with session
        session_destroy();
        header("Location: studMainPage.php");
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting student data: " . $e->getMessage());
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'updateProfile') {
    try {
        // Get form data
        $fullName = isset($_POST['fullName']) ? strtoupper($_POST['fullName']) : '';
        $studEmail = isset($_POST['studEmail']) ? strtoupper($_POST['studEmail']) : '';
        $personalEmail = isset($_POST['personalEmail']) ? strtoupper($_POST['personalEmail']) : '';
        $phoneNo = isset($_POST['phoneNo']) ? $_POST['phoneNo'] : '';
        $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
        $faculty = isset($_POST['faculty']) ? $_POST['faculty'] : '';
        $roomSharingStyle = isset($_POST['roomSharingStyle']) ? strtoupper($_POST['roomSharingStyle']) : '';
        $chronicIssueLevel = isset($_POST['chronicIssueLevel']) ? $_POST['chronicIssueLevel'] : '';
        $chronicIssueName = isset($_POST['chronicIssueName']) ? strtoupper($_POST['chronicIssueName']) : '';
        
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
            'roomSharingStyle' => '',
            'chronicIssueLevel' => '',
            'chronicIssueName' => ''
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
        
        if (empty($roomSharingStyle)) {
            $validationErrors['roomSharingStyle'] = "Room Sharing Style is required";
            $hasErrors = true;
        }
        
        if (empty($chronicIssueLevel)) {
            $validationErrors['chronicIssueLevel'] = "Chronic Issue Level is required";
            $hasErrors = true;
        }
        
        if (empty($chronicIssueName)) {
            $validationErrors['chronicIssueName'] = "Chronic Issue Name is required";
            $hasErrors = true;
        }
        
        // Check uniqueness for emails and phone - exclude current user
        if (!$hasErrors) {
            // Check student email
            $stmt = $conn->prepare("SELECT StudID FROM STUDENT WHERE StudEmail = ? AND StudID != ?");
            $stmt->bind_param("ss", $studEmail, $_SESSION['studID']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $validationErrors['studEmail'] = "Student email already exists for another account";
                $hasErrors = true;
            }
            $stmt->close();
            
            // Check personal email
            $stmt = $conn->prepare("SELECT StudID FROM STUDENT WHERE PersonalEmail = ? AND StudID != ?");
            $stmt->bind_param("ss", $personalEmail, $_SESSION['studID']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $validationErrors['personalEmail'] = "Personal email already exists for another account";
                $hasErrors = true;
            }
            $stmt->close();
            
            // Check phone number
            $stmt = $conn->prepare("SELECT StudID FROM STUDENT WHERE PhoneNo = ? AND StudID != ?");
            $stmt->bind_param("ss", $phoneNo, $_SESSION['studID']);
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
                "RoomSharingStyle" => $student['RoomSharingStyle'],
                "ChronicIssueLevel" => $student['ChronicIssueLevel'],
                "ChronicIssueName" => $student['ChronicIssueName']
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
                "RoomSharingStyle" => $roomSharingStyle,
                "ChronicIssueLevel" => $chronicIssueLevel,
                "ChronicIssueName" => $chronicIssueName
            ]);
            
            // Prepare and execute update query
            $stmt = $conn->prepare("
                UPDATE STUDENT 
                SET FullName = ?, StudEmail = ?, PersonalEmail = ?, PhoneNo = ?, Gender = ?, Faculty = ?, 
                    MatricNo = ?, RoomSharingStyle = ?, ChronicIssueLevel = ?, ChronicIssueName = ? 
                WHERE StudID = ?");
            $stmt->bind_param("sssssssssss", 
                $fullName, 
                $studEmail, 
                $personalEmail, 
                $phoneNo, 
                $gender, 
                $faculty, 
                $matricNo,
                $roomSharingStyle,
                $chronicIssueLevel,
                $chronicIssueName,
                $_SESSION['studID']
            );
            $success = $stmt->execute();
            
            if ($success) {
                // Add to audit trail
                $audit_stmt = $conn->prepare("CALL add_audit_trail(?, ?, ?, ?, ?, ?)");
                $table_name = "STUDENT";
                $action = "UPDATE";
                $user_id = $_SESSION['studID'];
                
                $audit_stmt->bind_param("ssssss", $table_name, $user_id, $action, $user_id, $old_data, $new_data);
                $audit_stmt->execute();
                $audit_stmt->close();
                
                // Update successful, redirect to refresh the page
                header("Location: studProfile.php?updated=true");
                exit();
            } else {
                $error = "Failed to update profile information";
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

// Format gender for display
$displayGender = ($student['Gender'] === 'M') ? 'Male' : 'Female';

// Format status for display (capitalize each word)
$displayStatus = ucwords(strtolower($student['Status']));

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
    <link rel="stylesheet" href="css/studentNav.css">
    <style>
        :root {
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
        
        .main-content {
            padding: 30px 0;
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
    <?php include 'includes/studentNav.php'; ?>
    
    <div class="container main-content">
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
                    <div class="edit-profile-pic" id="editProfilePic">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <div class="fullname"><?php echo htmlspecialchars($student['FullName']); ?></div>
            </div>
            
            <div class="profile-info">
                <form action="studProfile.php" method="POST">
                    <input type="hidden" name="action" value="updateProfile">
                    
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
                            <div class="form-group">
                                <label for="roomSharingStyle">Room Sharing Style</label>
                                <input type="text" id="roomSharingStyle" name="roomSharingStyle" value="<?php echo isset($_POST['roomSharingStyle']) ? htmlspecialchars(strtoupper($_POST['roomSharingStyle'])) : htmlspecialchars($student['RoomSharingStyle']); ?>" required autocomplete="off">
                                <span id="roomSharingStyle-error" class="error-message"><?php echo isset($validationErrors['roomSharingStyle']) ? $validationErrors['roomSharingStyle'] : ''; ?></span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="chronicIssueLevel">Chronic Issue Level</label>
                                <select id="chronicIssueLevel" name="chronicIssueLevel" required>
                                    <option value="NONE" <?php echo (isset($_POST['chronicIssueLevel']) && $_POST['chronicIssueLevel'] === 'NONE') || (!isset($_POST['chronicIssueLevel']) && isset($student['ChronicIssueLevel']) && $student['ChronicIssueLevel'] === 'NONE') ? 'selected' : ''; ?>>NONE</option>
                                    <option value="MILD" <?php echo (isset($_POST['chronicIssueLevel']) && $_POST['chronicIssueLevel'] === 'MILD') || (!isset($_POST['chronicIssueLevel']) && isset($student['ChronicIssueLevel']) && $student['ChronicIssueLevel'] === 'MILD') ? 'selected' : ''; ?>>MILD</option>
                                    <option value="MODERATE" <?php echo (isset($_POST['chronicIssueLevel']) && $_POST['chronicIssueLevel'] === 'MODERATE') || (!isset($_POST['chronicIssueLevel']) && isset($student['ChronicIssueLevel']) && $student['ChronicIssueLevel'] === 'MODERATE') ? 'selected' : ''; ?>>MODERATE</option>
                                    <option value="SEVERE" <?php echo (isset($_POST['chronicIssueLevel']) && $_POST['chronicIssueLevel'] === 'SEVERE') || (!isset($_POST['chronicIssueLevel']) && isset($student['ChronicIssueLevel']) && $student['ChronicIssueLevel'] === 'SEVERE') ? 'selected' : ''; ?>>SEVERE</option>
                                </select>
                                <span id="chronicIssueLevel-error" class="error-message"><?php echo isset($validationErrors['chronicIssueLevel']) ? $validationErrors['chronicIssueLevel'] : ''; ?></span>
                            </div>
                            <div class="form-group">
                                <label for="chronicIssueName">Chronic Issue Name</label>
                                <input type="text" id="chronicIssueName" name="chronicIssueName" value="<?php echo isset($_POST['chronicIssueName']) ? htmlspecialchars(strtoupper($_POST['chronicIssueName'])) : (isset($student['ChronicIssueName']) ? htmlspecialchars($student['ChronicIssueName']) : ''); ?>" required autocomplete="off">
                                <span id="chronicIssueName-error" class="error-message"><?php echo isset($validationErrors['chronicIssueName']) ? $validationErrors['chronicIssueName'] : ''; ?></span>
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
                                <input type="text" id="status" value="<?php echo htmlspecialchars($displayStatus); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="year">Year</label>
                                <input type="text" id="year" value="<?php echo htmlspecialchars($student['Year']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="semester">Semester</label>
                                <input type="text" id="semester" value="<?php echo htmlspecialchars($student['Semester']); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='studHomePage.php'">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
    
    <!-- Modal for profile picture update -->
    <div id="profilePicModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Profile Picture</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="file-input-container">
                    <input type="file" id="profilePicInput" accept="image/*">
                    <label for="profilePicInput" class="file-input-label">Choose Image</label>
                </div>
                
                <div id="image-container">
                    <img id="imagePreview" style="max-width: 100%; display: none;">
                </div>
                
                <form id="profilePicForm" action="studProcessUpdateProfilePic.php" method="POST">
                    <input type="hidden" name="imageData" id="imageData">
                    <input type="hidden" name="chronicIssueLevel" id="hiddenChronicIssueLevel">
                    <input type="hidden" name="chronicIssueName" id="hiddenChronicIssueName">
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelBtn">Cancel</button>
                <button class="btn btn-primary" id="cropImageBtn">Crop & Save</button>
                <button class="btn btn-primary" id="saveImageBtn" style="display: none;">Save Image</button>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
    <script>
        // Profile picture modal
        const modal = document.getElementById("profilePicModal");
        const editBtn = document.getElementById("editProfilePic");
        const closeBtn = document.querySelector(".close");
        const cancelBtn = document.getElementById("cancelBtn");
        const cropBtn = document.getElementById("cropImageBtn");
        const saveBtn = document.getElementById("saveImageBtn");
        const fileInput = document.getElementById("profilePicInput");
        const imagePreview = document.getElementById("imagePreview");
        const imageContainer = document.getElementById("image-container");
        const imageDataInput = document.getElementById("imageData");
        
        let cropper;
        
        // Open modal
        editBtn.addEventListener("click", function() {
            modal.style.display = "flex";
        });
        
        // Close modal
        closeBtn.addEventListener("click", closeModal);
        cancelBtn.addEventListener("click", closeModal);
        
        function closeModal() {
            modal.style.display = "none";
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            imagePreview.style.display = "none";
            cropBtn.style.display = "none";
            saveBtn.style.display = "none";
        }
        
        // Handle file input
        fileInput.addEventListener("change", function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Check file type
            const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!validTypes.includes(file.type)) {
                alert('Invalid file type. Please upload a JPEG, PNG, or GIF image.');
                this.value = ''; // Clear the file input
                return;
            }
            
            // Check file size (max 2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert('File size too large. Please upload an image smaller than 2MB.');
                this.value = ''; // Clear the file input
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                if (cropper) {
                    cropper.destroy();
                }
                
                imagePreview.src = e.target.result;
                imagePreview.style.display = "block";
                
                // Initialize cropper
                cropper = new Cropper(imagePreview, {
                    aspectRatio: 1,
                    viewMode: 1,
                    dragMode: 'move',
                    responsive: true,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: false,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false
                });
                
                cropBtn.style.display = "block";
                saveBtn.style.display = "none";
            };
            
            reader.readAsDataURL(file);
        });
        
        // Crop image
        cropBtn.addEventListener("click", function() {
            const canvas = cropper.getCroppedCanvas({
                width: 300,
                height: 300,
                fillColor: '#fff',
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            
            const croppedImage = canvas.toDataURL("image/jpeg", 0.9);
            imagePreview.src = croppedImage;
            imagePreview.style.display = "block";
            
            // Destroy cropper and show save button
            cropper.destroy();
            cropper = null;
            
            cropBtn.style.display = "none";
            saveBtn.style.display = "block";
            
            // Set data to hidden input
            imageDataInput.value = croppedImage;
        });
        
        // Save image
        saveBtn.addEventListener("click", function() {
            const form = document.getElementById("profilePicForm");
            
            // Set chronic issue values from the main form
            document.getElementById('hiddenChronicIssueLevel').value = document.getElementById('chronicIssueLevel').value;
            document.getElementById('hiddenChronicIssueName').value = document.getElementById('chronicIssueName').value;
            
            // Create a hidden iframe to handle the form submission
            const iframe = document.createElement('iframe');
            iframe.name = 'hidden_iframe';
            iframe.style.display = 'none';
            document.body.appendChild(iframe);
            
            // Set form target to the hidden iframe
            form.target = 'hidden_iframe';
            
            // Submit the form
            form.submit();
            
            // Close the modal
            closeModal();
            
            // Show success message
            alert('Profile picture updated successfully!');
            
            // Reload the page to show the updated picture after a short delay
            setTimeout(function() {
                window.location.reload();
            }, 1000);
        });
        
        // Close modal if clicking outside
        window.addEventListener("click", function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        
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
        document.querySelector('form[action="studProfile.php"]').addEventListener('submit', function(e) {
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
            
            // Validate chronic issue fields
            const chronicIssueLevel = document.getElementById('chronicIssueLevel').value;
            const chronicIssueName = document.getElementById('chronicIssueName').value.trim();
            
            if (!chronicIssueLevel) {
                document.getElementById('chronicIssueLevel-error').textContent = 'Chronic Issue Level is required';
                hasErrors = true;
            }
            
            if (!chronicIssueName) {
                document.getElementById('chronicIssueName-error').textContent = 'Chronic Issue Name is required';
                hasErrors = true;
            }
            
            // Extract matric no from email before submission
            extractMatricNo();
            
            // If no validation errors, submit the form
            if (!hasErrors) {
                this.submit();
            }
        });
        
        // Handle chronic issue level change
        document.getElementById('chronicIssueLevel').addEventListener('change', function() {
            const chronicIssueName = document.getElementById('chronicIssueName');
            
            if (this.value === 'NONE') {
                chronicIssueName.value = 'N/A';
                chronicIssueName.readOnly = true;
                chronicIssueName.style.backgroundColor = '#f7f7f7';
            } else {
                if (chronicIssueName.value === 'N/A') {
                    chronicIssueName.value = '';
                }
                chronicIssueName.readOnly = false;
                chronicIssueName.style.backgroundColor = '';
            }
        });
        
        // Initialize chronic issue name field on page load
        window.addEventListener('DOMContentLoaded', function() {
            const chronicIssueLevel = document.getElementById('chronicIssueLevel');
            const chronicIssueName = document.getElementById('chronicIssueName');
            
            if (chronicIssueLevel.value === 'NONE') {
                chronicIssueName.value = 'N/A';
                chronicIssueName.readOnly = true;
                chronicIssueName.style.backgroundColor = '#f7f7f7';
            }
        });
    </script>
</body>
</html> 