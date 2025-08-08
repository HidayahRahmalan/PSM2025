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

// Get employee data
$employee = array();

try {
    $stmt = $conn->prepare("
        SELECT 
            EmpID, FullName, StaffEmail, PersonalEmail, PhoneNo, 
            Gender, Status, Role, ProfilePic
        FROM EMPLOYEE 
        WHERE EmpID = ?");
    $stmt->bind_param("s", $_SESSION['empId']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $employee = $result->fetch_assoc();
    } else {
        // If employee not found, something's wrong with session
        session_destroy();
        header("Location: staffMainPage.php");
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
        $fullName = isset($_POST['fullName']) ? strtoupper(trim($_POST['fullName'])) : '';
        $staffEmail = isset($_POST['staffEmail']) ? strtoupper(trim($_POST['staffEmail'])) : '';
        $personalEmail = isset($_POST['personalEmail']) ? strtoupper(trim($_POST['personalEmail'])) : '';
        $phoneNo = isset($_POST['phoneNo']) ? trim($_POST['phoneNo']) : '';
        $gender = isset($_POST['gender']) ? $_POST['gender'] : '';
        
        // Initialize validation errors array
        $validationErrors = array(
            'fullName' => '',
            'staffEmail' => '',
            'personalEmail' => '',
            'phoneNo' => '',
            'gender' => ''
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
        } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@utem\.edu\.my$/i', $staffEmail)) {
            $validationErrors['staffEmail'] = "Staff email must be in format: example@utem.edu.my";
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
        
        // Check uniqueness for emails and phone - exclude current user
        if (!$hasErrors) {
            // Check staff email
            $stmt = $conn->prepare("SELECT EmpID FROM EMPLOYEE WHERE StaffEmail = ? AND EmpID != ?");
            $stmt->bind_param("ss", $staffEmail, $_SESSION['empId']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $validationErrors['staffEmail'] = "Staff email already exists for another account";
                $hasErrors = true;
            }
            $stmt->close();
            
            // Check personal email
            $stmt = $conn->prepare("SELECT EmpID FROM EMPLOYEE WHERE PersonalEmail = ? AND EmpID != ?");
            $stmt->bind_param("ss", $personalEmail, $_SESSION['empId']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $validationErrors['personalEmail'] = "Personal email already exists for another account";
                $hasErrors = true;
            }
            $stmt->close();
            
            // Check phone number
            $stmt = $conn->prepare("SELECT EmpID FROM EMPLOYEE WHERE PhoneNo = ? AND EmpID != ?");
            $stmt->bind_param("ss", $phoneNo, $_SESSION['empId']);
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
                "Gender" => $employee['Gender']
            ]);
            
            // New data for audit trail
            $new_data = json_encode([
                "FullName" => $fullName,
                "StaffEmail" => $staffEmail,
                "PersonalEmail" => $personalEmail,
                "PhoneNo" => $phoneNo,
                "Gender" => $gender
            ]);
            
            // Prepare and execute update query
            $stmt = $conn->prepare("
                UPDATE EMPLOYEE 
                SET FullName = ?, StaffEmail = ?, PersonalEmail = ?, PhoneNo = ?, Gender = ? 
                WHERE EmpID = ?");
            $stmt->bind_param("ssssss", 
                $fullName, 
                $staffEmail, 
                $personalEmail, 
                $phoneNo, 
                $gender,
                $_SESSION['empId']
            );
            $success = $stmt->execute();
            
            if ($success) {
                // Add to audit trail
                $audit_stmt = $conn->prepare("CALL add_audit_trail(?, ?, ?, ?, ?, ?)");
                $table_name = "EMPLOYEE";
                $action = "UPDATE";
                $user_id = $_SESSION['empId'];
                
                $audit_stmt->bind_param("ssssss", $table_name, $user_id, $action, $user_id, $old_data, $new_data);
                $audit_stmt->execute();
                $audit_stmt->close();
                
                // Update successful, redirect to refresh the page
                header("Location: admProfile.php?updated=true");
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
if (isset($employee['ProfilePic']) && $employee['ProfilePic']) {
    $profileImage = 'data:image/jpeg;base64,' . base64_encode($employee['ProfilePic']);
}

// Format gender for display
$displayGender = ($employee['Gender'] === 'M') ? 'Male' : 'Female';

// Format status and role for display (capitalize each word)
$displayStatus = ucwords(strtolower($employee['Status']));
$displayRole = ucwords(strtolower($employee['Role']));

// Add JavaScript function to validate the form
$jsValidation = true;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - SHMS</title>
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
        
        .profile-icon img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
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
        
        .form-group input:disabled,
        .form-group select:disabled,
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
    
    <main class="container main-content">
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
                <h2>Admin Profile</h2>
                <div class="profile-pic-container">
                    <img src="<?php echo htmlspecialchars($profileImage); ?>" alt="Profile Picture" class="profile-pic" id="profilePic">
                    <div class="edit-profile-pic" id="editProfilePic">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>
                <div class="fullname"><?php echo htmlspecialchars($employee['FullName']); ?></div>
            </div>
            
            <div class="profile-info">
                <form action="admProfile.php" method="POST">
                    <input type="hidden" name="action" value="updateProfile">
                    
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
                                <input type="email" id="personalEmail" name="personalEmail" value="<?php echo isset($_POST['personalEmail']) ? htmlspecialchars($_POST['personalEmail']) : htmlspecialchars($employee['PersonalEmail']); ?>" required autocomplete="off">
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
                                <label for="staffEmail">Staff Email</label>
                                <input type="email" id="staffEmail" name="staffEmail" value="<?php echo isset($_POST['staffEmail']) ? htmlspecialchars($_POST['staffEmail']) : htmlspecialchars($employee['StaffEmail']); ?>" required pattern="[a-zA-Z0-9._%+-]+@utem\.edu\.my" autocomplete="off">
                                <span id="staffEmail-error" class="error-message"><?php echo isset($validationErrors['staffEmail']) ? $validationErrors['staffEmail'] : ''; ?></span>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <input type="text" id="status" value="<?php echo htmlspecialchars($displayStatus); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="role">Role</label>
                                <input type="text" id="role" value="<?php echo htmlspecialchars($displayRole); ?>" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='staffHomePage.php'">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </section>
    </main>
    
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
                
                <form id="profilePicForm" action=admProcessUpdateProfilePic.php" method="POST">
                    <input type="hidden" name="imageData" id="imageData">
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
        
        // Form validation
        document.querySelector('form[action="admProfile.php"]').addEventListener('submit', function(e) {
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
            
            // Validate staff email format
            const staffEmail = document.getElementById('staffEmail').value.trim();
            const staffEmailPattern = /^[a-zA-Z0-9._%+-]+@utem\.edu\.my$/i;
            if (!staffEmail) {
                document.getElementById('staffEmail-error').textContent = 'Staff email is required';
                hasErrors = true;
            } else if (!staffEmailPattern.test(staffEmail)) {
                document.getElementById('staffEmail-error').textContent = 'Staff email must be in format: example@utem.edu.my';
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
            
            // If no validation errors, submit the form
            if (!hasErrors) {
                this.submit();
            }
        });
    </script>
</body>
</html> 