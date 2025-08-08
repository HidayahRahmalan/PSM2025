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
} else if (isset($_POST['empID'])) {
    $empID = $_POST['empID'];
} else {
    header("Location: admViewHs.php?error=No employee selected");
    exit();
}

// Get employee data
$employee = array();

try {
    $stmt = $conn->prepare("
        SELECT 
            EmpID, FullName, StaffEmail, PersonalEmail, PhoneNo, 
            Gender, Status, Role, ProfilePic
        FROM EMPLOYEE 
        WHERE EmpID = ?");
    $stmt->bind_param("s", $empID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
    } else {
        // No employee found with the given ID
        header("Location: admViewHs.php?error=" . urlencode("Employee with ID $empID not found."));
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting employee data: " . $e->getMessage());
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'updateProfile') {
    try {
        // Get form data
        $fullName = isset($_POST['fullName']) ? strtoupper($_POST['fullName']) : '';
        $staffEmail = isset($_POST['staffEmail']) ? strtoupper($_POST['staffEmail']) : '';
        $personalEmail = isset($_POST['personalEmail']) ? strtoupper($_POST['personalEmail']) : '';
        $phoneNo = isset($_POST['phoneNo']) ? $_POST['phoneNo'] : '';
        $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
        $status = isset($_POST['status']) ? strtoupper($_POST['status']) : '';
        
        // Initialize validation errors array
        $validationErrors = array(
            'fullName' => '',
            'staffEmail' => '',
            'personalEmail' => '',
            'phoneNo' => '',
            'gender' => '',
            'status' => ''
        );
        
        $hasErrors = false;
        
        // Validate data
        if (empty($fullName)) {
            $validationErrors['fullName'] = "Full name is required";
            $hasErrors = true;
        }
        
        if (empty($staffEmail)) {
            $validationErrors['staffEmail'] = "Staff email is required";
            $hasErrors = true;
        } elseif (!preg_match('/^[A-Za-z0-9._%+-]+@utem\.edu\.my$/i', $staffEmail)) {
            $validationErrors['staffEmail'] = "Staff email must be @utem.edu.my";
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

        if (empty($status)) {
            $validationErrors['status'] = "Status is required";
            $hasErrors = true;
        }
        
        // Check uniqueness for emails and phone - exclude current user
        if (!$hasErrors) {
            // Check staff email
            $stmt = $conn->prepare("SELECT EmpID FROM EMPLOYEE WHERE StaffEmail = ? AND EmpID != ?");
            $stmt->bind_param("ss", $staffEmail, $empID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $validationErrors['staffEmail'] = "Staff email already exists for another account";
                $hasErrors = true;
            }
            $stmt->close();
            
            // Check personal email
            $stmt = $conn->prepare("SELECT EmpID FROM EMPLOYEE WHERE PersonalEmail = ? AND EmpID != ?");
            $stmt->bind_param("ss", $personalEmail, $empID);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $validationErrors['personalEmail'] = "Personal email already exists for another account";
                $hasErrors = true;
            }
            $stmt->close();
            
            // Check phone number
            $stmt = $conn->prepare("SELECT EmpID FROM EMPLOYEE WHERE PhoneNo = ? AND EmpID != ?");
            $stmt->bind_param("ss", $phoneNo, $empID);
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
                "FullName" => $employee['FullName'],
                "StaffEmail" => $employee['StaffEmail'],
                "PersonalEmail" => $employee['PersonalEmail'],
                "PhoneNo" => $employee['PhoneNo'],
                "Gender" => $employee['Gender'],
                "Status" => $employee['Status']
            ]);
            
            // New data for audit trail
            $new_data = json_encode([
                "FullName" => $fullName,
                "StaffEmail" => $staffEmail,
                "PersonalEmail" => $personalEmail,
                "PhoneNo" => $phoneNo,
                "Gender" => $gender,
                "Status" => $status
            ]);
            
            // Prepare and execute update query
            $stmt = $conn->prepare("
                UPDATE EMPLOYEE 
                SET FullName = ?, StaffEmail = ?, PersonalEmail = ?, PhoneNo = ?, Gender = ?, Status = ?
                WHERE EmpID = ?");
            $stmt->bind_param("sssssss", 
                $fullName, 
                $staffEmail, 
                $personalEmail, 
                $phoneNo, 
                $gender, 
                $status,
                $empID
            );
            $success = $stmt->execute();
            
            if ($success) {
                // Add to audit trail
                $audit_stmt = $conn->prepare("CALL add_audit_trail(?, ?, ?, ?, ?, ?)");
                $table_name = "EMPLOYEE";
                $action = "UPDATE";
                $user_id = $_SESSION['empId'];
                
                $audit_stmt->bind_param("ssssss", $table_name, $empID, $action, $user_id, $old_data, $new_data);
                $audit_stmt->execute();
                $audit_stmt->close();
                
                // Fetch updated employee data
                $stmt = $conn->prepare("
                    SELECT 
                        EmpID, FullName, StaffEmail, PersonalEmail, PhoneNo, 
                        Gender, Status, Role, ProfilePic
                    FROM EMPLOYEE 
                    WHERE EmpID = ?");
                $stmt->bind_param("s", $empID);
                $stmt->execute();
                $result = $stmt->get_result();
                $employee = $result->fetch_assoc();
                $stmt->close();
                
                // Update successful, redirect to refresh the page with the same employee ID
                header("Location: admEditHs.php?EmpID=" . urlencode($empID) . "&updated=true");
                exit();
            } else {
                // If update failed, redirect back with error
                header("Location: admEditHs.php?EmpID=" . urlencode($empID) . "&error=Failed to update employee information");
                exit();
            }
            
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error updating profile: " . $e->getMessage());
        $error = "An error occurred while updating the profile";
    }
}

// Default profile image if none exists
$profileImage = "https://cdn-icons-png.flaticon.com/512/8608/8608769.png";

// If profile pic exists in DB, convert to base64 for display
if (!empty($employee['ProfilePic'])) {
    $profileImage = 'data:image/jpeg;base64,' . base64_encode($employee['ProfilePic']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Hostel Staff - SHMS</title>
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
                <h2>Hostel Staff Profile</h2>
                <div class="profile-pic-container">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Picture" class="profile-pic" id="profilePic">
                </div>
                <div class="fullname"><?php echo htmlspecialchars($employee['FullName']); ?></div>
            </div>
            
            <div class="profile-info">
                <form action="admEditHs.php" method="POST">
                    <input type="hidden" name="action" value="updateProfile">
                    <input type="hidden" name="empID" value="<?php echo htmlspecialchars($empID); ?>">
                    
                    <div class="info-group">
                        <h3>Personal Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="fullName">Full Name</label>
                                <input type="text" id="fullName" name="fullName" value="<?php echo isset($_POST['fullName']) ? htmlspecialchars($_POST['fullName']) : htmlspecialchars($employee['FullName']); ?>" required autocomplete="off">
                                <span id="fullName-error" class="error-message"><?php echo isset($validationErrors['fullName']) ? $validationErrors['fullName'] : ''; ?></span>
                            </div>
                            <div class="form-group">
                                <label for="gender">Gender</label>
                                <select id="gender" name="gender" required>
                                    <option value="M" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'M') || (!isset($_POST['gender']) && $employee['Gender'] === 'M') ? 'selected' : ''; ?>>Male</option>
                                    <option value="F" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'F') || (!isset($_POST['gender']) && $employee['Gender'] === 'F') ? 'selected' : ''; ?>>Female</option>
                                </select>
                                <span id="gender-error" class="error-message"><?php echo isset($validationErrors['gender']) ? $validationErrors['gender'] : ''; ?></span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="personalEmail">Personal Email</label>
                                <input type="email" id="personalEmail" name="personalEmail" value="<?php echo isset($_POST['personalEmail']) ? htmlspecialchars($_POST['personalEmail']) : htmlspecialchars(strtolower($employee['PersonalEmail'])); ?>" required autocomplete="off">
                                <span id="personalEmail-error" class="error-message"><?php echo isset($validationErrors['personalEmail']) ? $validationErrors['personalEmail'] : ''; ?></span>
                            </div>
                            <div class="form-group">
                                <label for="phoneNo">Phone Number (XXX-XXXXXXXX)</label>
                                <input type="tel" id="phoneNo" name="phoneNo" value="<?php echo isset($_POST['phoneNo']) ? htmlspecialchars($_POST['phoneNo']) : htmlspecialchars($employee['PhoneNo']); ?>" required maxlength="12" autocomplete="off">
                                <span id="phoneNo-error" class="error-message"><?php echo isset($validationErrors['phoneNo']) ? $validationErrors['phoneNo'] : ''; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-group">
                        <h3>Professional Information</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="empID">Employee ID</label>
                                <input type="text" id="empID" value="<?php echo htmlspecialchars($employee['EmpID']); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="role">Role</label>
                                <input type="text" id="role" value="<?php echo htmlspecialchars($employee['Role']); ?>" readonly>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="staffEmail">Staff Email</label>
                                <input type="email" id="staffEmail" name="staffEmail" value="<?php echo isset($_POST['staffEmail']) ? htmlspecialchars($_POST['staffEmail']) : htmlspecialchars(strtolower($employee['StaffEmail'])); ?>" required autocomplete="off">
                                <span id="staffEmail-error" class="error-message"><?php echo isset($validationErrors['staffEmail']) ? $validationErrors['staffEmail'] : ''; ?></span>
                            </div>
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="ACTIVE" <?php echo (isset($_POST['status']) && $_POST['status'] === 'ACTIVE') || (!isset($_POST['status']) && $employee['Status'] === 'ACTIVE') ? 'selected' : ''; ?>>Active</option>
                                    <option value="INACTIVE" <?php echo (isset($_POST['status']) && $_POST['status'] === 'INACTIVE') || (!isset($_POST['status']) && $employee['Status'] === 'INACTIVE') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <span id="status-error" class="error-message"><?php echo isset($validationErrors['status']) ? $validationErrors['status'] : ''; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='admViewHs.php'">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script>
        // Function to validate phone number format
        function validatePhoneNumber(input) {
            const phoneRegex = /^[0-9]{3}-[0-9]{7,8}$/;
            return phoneRegex.test(input);
        }

        // Function to validate email format
        function validateEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        // Function to validate staff email format
        function validateStaffEmail(email) {
            const emailRegex = /^[A-Za-z0-9._%+-]+@utem\.edu\.my$/i;
            return emailRegex.test(email);
        }

        // Function to validate form before submission
        function validateForm() {
            let isValid = true;
            const errors = {};

            // Validate full name
            const fullName = document.getElementById('fullName').value.trim();
            if (!fullName) {
                errors.fullName = "Full name is required";
                isValid = false;
            }

            // Validate staff email
            const staffEmail = document.getElementById('staffEmail').value.trim();
            if (!staffEmail) {
                errors.staffEmail = "Staff email is required";
                isValid = false;
            } else if (!validateStaffEmail(staffEmail)) {
                errors.staffEmail = "Staff email must be @utem.edu.my";
                isValid = false;
            }

            // Validate personal email
            const personalEmail = document.getElementById('personalEmail').value.trim();
            if (!personalEmail) {
                errors.personalEmail = "Personal email is required";
                isValid = false;
            } else if (!validateEmail(personalEmail)) {
                errors.personalEmail = "Please enter a valid personal email address (Eg: abc@gmail.com)";
                isValid = false;
            }

            // Validate phone number
            const phoneNo = document.getElementById('phoneNo').value.trim();
            if (!phoneNo) {
                errors.phoneNo = "Phone number is required";
                isValid = false;
            } else if (!validatePhoneNumber(phoneNo)) {
                errors.phoneNo = "Phone number must be XXX-XXXXXXXX format";
                isValid = false;
            }

            // Validate gender
            const gender = document.getElementById('gender').value;
            if (!gender) {
                errors.gender = "Gender is required";
                isValid = false;
            }

            // Validate status
            const status = document.getElementById('status').value;
            if (!status) {
                errors.status = "Status is required";
                isValid = false;
            }

            // Display errors
            Object.keys(errors).forEach(field => {
                const errorElement = document.getElementById(`${field}-error`);
                if (errorElement) {
                    errorElement.textContent = errors[field];
                }
            });

            return isValid;
        }

        // Add event listener to form submission
        document.querySelector('form').addEventListener('submit', function(event) {
            if (!validateForm()) {
                event.preventDefault();
            }
        });

        // Add input event listeners for real-time validation
        document.getElementById('phoneNo').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 3) {
                value = value.slice(0, 3) + '-' + value.slice(3);
            }
            e.target.value = value;
        });

        document.getElementById('staffEmail').addEventListener('input', function(e) {
            const errorElement = document.getElementById('staffEmail-error');
            if (!validateStaffEmail(e.target.value)) {
                errorElement.textContent = "Staff email must be @utem.edu.my";
            } else {
                errorElement.textContent = "";
            }
        });

        document.getElementById('personalEmail').addEventListener('input', function(e) {
            const errorElement = document.getElementById('personalEmail-error');
            if (!validateEmail(e.target.value)) {
                errorElement.textContent = "Please enter a valid personal email address (Eg: abc@gmail.com)";
            } else {
                errorElement.textContent = "";
            }
        });
    </script>
</body>
</html> 