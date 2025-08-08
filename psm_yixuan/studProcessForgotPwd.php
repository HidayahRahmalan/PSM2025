<?php
// Start output buffering to prevent "headers already sent" errors
ob_start();

// Enable error handling but don't display to users
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Start a session
session_start();

// Include database connection
include 'dbConnection.php';

// PHPMailer for sending emails
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Require PHPMailer files 
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Initialize response array
$response = array(
    'success' => false,
    'message' => '',
    'token' => ''
);

// Process based on action type
$action = isset($_POST['action']) ? $_POST['action'] : '';

switch ($action) {
    case 'checkEmail':
        checkEmailExists();
        break;
        
    case 'verifyCode':
        verifyCode();
        break;
        
    case 'resetPassword':
        resetPassword();
        break;
        
    default:
        $response['message'] = 'Invalid action.';
        break;
}

// Function to check if email exists in the database
function checkEmailExists() {
    global $conn, $response;
    
    $email = isset($_POST['email']) ? strtoupper(trim($_POST['email'])) : '';
    
    if (empty($email)) {
        $response['message'] = 'Email address is required.';
        sendResponse();
        return;
    }
    
    try {
        // Check if email exists in STUDENT table and get personal email
        $stmt = $conn->prepare("SELECT StudID, StudEmail, PersonalEmail, Status FROM STUDENT WHERE StudEmail = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            
            // Check if account is active
            if ($student['Status'] !== 'ACTIVE') {
                $response['message'] = 'Your account has been deactivated. Please contact the administrator.';
                $stmt->close();
                sendResponse();
                return;
            }
            
            // Check if personal email exists
            if (empty($student['PersonalEmail'])) {
                $response['message'] = 'Personal email not found in your profile. Please contact administrator.';
                $stmt->close();
                sendResponse();
                return;
            }
            
            // Get the personal email to send verification code
            $personalEmail = $student['PersonalEmail'];
            
            // Generate a random 6-digit verification code
            $verificationCode = sprintf("%06d", mt_rand(1, 999999));
            
            // Current timestamp
            $timestamp = time();
            
            // Store verification code and timestamp in the session
            $_SESSION['password_reset'] = array(
                'email' => $email,
                'personalEmail' => $personalEmail,
                'code' => $verificationCode,
                'timestamp' => $timestamp,
                'attempts' => 0
            );
            
            // Send verification code to the personal email
            $emailResult = sendVerificationEmail($personalEmail, $verificationCode);
            
            if ($emailResult['success']) {
                $response['success'] = true;
                $response['message'] = 'Verification code sent to your personal email: ' . maskEmail($personalEmail);
            } else {
                $response['message'] = 'Failed to send verification code: ' . $emailResult['error'];
            }
        } else {
            $response['message'] = 'Email not found. Please check your email address.';
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = 'An error occurred. Please try again later.';
        error_log("Error in checkEmailExists: " . $e->getMessage());
    }
    
    sendResponse();
}

// Function to mask email for privacy
function maskEmail($email) {
    if (empty($email)) return '';
    
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    
    $name = $parts[0];
    $domain = $parts[1];
    
    // If username is less than 5 chars, show only first char
    if (strlen($name) <= 4) {
        $maskedName = substr($name, 0, 1) . str_repeat('*', strlen($name) - 1);
    } else {
        // Otherwise show first 2 and last 2 chars
        $maskedName = substr($name, 0, 2) . str_repeat('*', strlen($name) - 4) . substr($name, -2);
    }
    
    return $maskedName . '@' . $domain;
}

// Function to verify the entered code
function verifyCode() {
    global $response;
    
    $verificationCode = isset($_POST['verificationCode']) ? trim($_POST['verificationCode']) : '';
    $email = isset($_POST['email']) ? strtoupper(trim($_POST['email'])) : '';
    
    if (empty($verificationCode) || empty($email)) {
        $response['message'] = 'Verification code and email are required.';
        sendResponse();
        return;
    }
    
    // Check if password reset session exists
    if (!isset($_SESSION['password_reset']) || $_SESSION['password_reset']['email'] !== $email) {
        $response['message'] = 'Session expired or invalid. Please restart the password reset process.';
        sendResponse();
        return;
    }
    
    // Get password reset data from session
    $resetData = $_SESSION['password_reset'];
    
    // Check if code has expired (15 minutes validity)
    if (time() - $resetData['timestamp'] > 900) {
        $response['message'] = 'Verification code has expired. Please request a new code.';
        unset($_SESSION['password_reset']);
        sendResponse();
        return;
    }
    
    // Track verification attempts
    $_SESSION['password_reset']['attempts'] += 1;
    
    // Limit to 5 attempts
    if ($_SESSION['password_reset']['attempts'] > 5) {
        $response['message'] = 'Too many failed attempts. Please restart the password reset process.';
        unset($_SESSION['password_reset']);
        sendResponse();
        return;
    }
    
    // Verify the code
    if ($verificationCode === $resetData['code']) {
        // Generate a token for password reset
        $token = bin2hex(random_bytes(32));
        
        // Store token in the session
        $_SESSION['password_reset']['token'] = $token;
        $_SESSION['password_reset']['token_timestamp'] = time();
        
        $response['success'] = true;
        $response['message'] = 'Verification successful.';
        $response['token'] = $token;
    } else {
        $response['message'] = 'Invalid verification code. Please try again. ' . 
                              'Attempts remaining: ' . (5 - $_SESSION['password_reset']['attempts']);
    }
    
    sendResponse();
}

// Function to reset the password
function resetPassword() {
    global $conn, $response;
    
    $email = isset($_POST['email']) ? strtoupper(trim($_POST['email'])) : '';
    $newPassword = isset($_POST['newPassword']) ? trim($_POST['newPassword']) : '';
    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    
    if (empty($email) || empty($newPassword) || empty($token)) {
        $response['message'] = 'All fields are required.';
        sendResponse();
        return;
    }
    
    // Check if password reset session exists with valid token
    if (!isset($_SESSION['password_reset']) || 
        $_SESSION['password_reset']['email'] !== $email || 
        $_SESSION['password_reset']['token'] !== $token) {
        $response['message'] = 'Invalid token or session expired. Please restart the password reset process.';
        error_log("Session validation failed. Session email: " . 
                 (isset($_SESSION['password_reset']) ? $_SESSION['password_reset']['email'] : 'not set') . 
                 ", Submitted email: $email, Token match: " . 
                 (isset($_SESSION['password_reset']['token']) && $_SESSION['password_reset']['token'] === $token ? 'Yes' : 'No'));
        sendResponse();
        return;
    }
    
    // Check if token has expired (10 minutes validity)
    if (time() - $_SESSION['password_reset']['token_timestamp'] > 600) {
        $response['message'] = 'Password reset token has expired. Please restart the process.';
        unset($_SESSION['password_reset']);
        sendResponse();
        return;
    }
    
    // Validate new password
    if (strlen($newPassword) < 8) {
        $response['message'] = 'Password must be at least 8 characters long.';
        sendResponse();
        return;
    }
    
    try {
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update the password in the database
        $stmt = $conn->prepare("UPDATE STUDENT SET Password = ? WHERE StudEmail = ?");
        $stmt->bind_param("ss", $hashedPassword, $email); // Use uppercase email for DB query
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Clear the session data
            unset($_SESSION['password_reset']);
            
            $response['success'] = true;
            $response['message'] = 'Password has been reset successfully.';
        } else {
            $response['message'] = 'Failed to update password. Please try again.';
            error_log("Password update failed. Email: $email, Affected rows: " . $stmt->affected_rows);
        }
        
        $stmt->close();
    } catch (Exception $e) {
        $response['message'] = 'An error occurred. Please try again later.';
        error_log("Error in resetPassword: " . $e->getMessage());
    }
    
    sendResponse();
}

// Function to send verification email using PHPMailer
function sendVerificationEmail($email, $verificationCode) {
    $result = [
        'success' => false,
        'error' => ''
    ];
    
    try {
        // Create a new PHPMailer instance
        $mail = new PHPMailer(true);
        
        // IMPORTANT: Force email sending even in development
        $isDevelopment = false; // Set to false to always send real emails
        
        if ($isDevelopment) {
            // In development environment, log the code to error log
            error_log("Development environment detected - Verification code for $email: $verificationCode");
            $result['success'] = true;
            return $result;
        }
        
        // Configure SMTP settings for sending real emails
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';  // Gmail SMTP server
        $mail->SMTPAuth = true;
        
        // IMPORTANT: Replace with your actual Gmail credentials
        $mail->Username = 'foodddt@gmail.com';  // Replace with your Gmail address
        $mail->Password = 'iuku zphm ikdp gafr'; // Replace with your Gmail app password
        
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Turn off debug output to prevent header issues
        $mail->SMTPDebug = 0; // 0 = off
        
        // Sender and recipient
        $mail->setFrom($mail->Username, 'UTeM SHMS'); // Use the same Gmail address as sender
        $mail->addAddress($email); // Keep email as uppercase for consistency
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Verification Code - UTeM SHMS';
        $mail->Body = '
            <html>
            <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
                <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 5px;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/LogoUTeM-2016.jpg" alt="UTeM Logo" style="height: 60px;">
                    </div>
                    <h2 style="color: #25408f;">Password Reset Verification</h2>
                    <p>You have requested to reset your password for the Smart Hostel Management System (SHMS).</p>
                    <p>Your verification code is: <strong style="font-size: 18px; letter-spacing: 2px;">' . $verificationCode . '</strong></p>
                    <p>This code will expire in 15 minutes for security reasons.</p>
                    <p>If you did not request a password reset, please ignore this email or contact the administrator.</p>
                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #777;">
                        <p>This is an automated email, please do not reply.</p>
                    </div>
                </div>
            </body>
            </html>
        ';
        
        // Plain text alternative
        $mail->AltBody = "Password Reset Verification\n\n" .
                      "You have requested to reset your password for the Smart Hostel Management System (SHMS).\n\n" .
                      "Your verification code is: " . $verificationCode . "\n\n" .
                      "This code will expire in 15 minutes for security reasons.\n\n" .
                      "If you did not request a password reset, please ignore this email or contact the administrator.\n\n" .
                      "This is an automated email, please do not reply.";
        
        // Send the email
        $mail->send();
        error_log("Email sent successfully to: $email with code: $verificationCode");
        $result['success'] = true;
        
        return $result;
    } catch (Exception $e) {
        $result['error'] = "Mailer Error: " . $e->getMessage();
        error_log($result['error']);
        return $result;
    }
}

// Send JSON response
function sendResponse() {
    global $response;
    
    // Clear any output sent before
    if (ob_get_length()) ob_clean();
    
    // Set proper headers
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?> 