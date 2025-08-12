<?php
// Enable error handling but don't display to users
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include database connection
include 'dbConnection.php';

// Initialize errors array
$errors = array();

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $email = isset($_POST['email']) ? strtoupper(trim($_POST['email'])) : '';
    $personalEmail = isset($_POST['personalEmail']) ? strtoupper(trim($_POST['personalEmail'])) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $matricno = isset($_POST['matricno']) ? strtoupper(trim($_POST['matricno'])) : '';
    
    // Check if email exists
    if (!empty($email)) {
        try {
            $stmt = $conn->prepare("SELECT StudEmail FROM STUDENT WHERE StudEmail = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors['email'] = "Email already exists. Please use a different email.";
            }
            
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error checking email uniqueness: " . $e->getMessage());
        }
    }
    
    // Check if personal email exists
    if (!empty($personalEmail)) {
        try {
            $stmt = $conn->prepare("SELECT PersonalEmail FROM STUDENT WHERE PersonalEmail = ?");
            $stmt->bind_param("s", $personalEmail);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors['personalEmail'] = "Personal email already exists. Please use a different email.";
            }
            
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error checking personal email uniqueness: " . $e->getMessage());
        }
    }
    
    // Check if phone exists
    if (!empty($phone)) {
        try {
            $stmt = $conn->prepare("SELECT PhoneNo FROM STUDENT WHERE PhoneNo = ?");
            $stmt->bind_param("s", $phone);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors['phone'] = "Phone number already exists. Please use a different number.";
            }
            
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error checking phone uniqueness: " . $e->getMessage());
        }
    }
    
    // Check if matric number exists
    if (!empty($matricno)) {
        try {
            $stmt = $conn->prepare("SELECT MatricNo FROM STUDENT WHERE MatricNo = ?");
            $stmt->bind_param("s", $matricno);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors['matricno'] = "Matric number already exists. Please contact administrator if this is an error.";
            }
            
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error checking matric number uniqueness: " . $e->getMessage());
        }
    }
}

// Prepare the response
$response = array();
if (!empty($errors)) {
    $response['errors'] = $errors;
} else {
    $response['success'] = true;
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Close connection
$conn->close();
?> 