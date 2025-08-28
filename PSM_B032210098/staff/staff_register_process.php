<?php
session_start();
require_once '../db_connection.php';
require_once '../PHPMailer/PHPMailer.php';
require_once '../PHPMailer/SMTP.php';
require_once '../PHPMailer/Exception.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $_SESSION['registration_errors'] = ['Invalid CSRF token.'];
        $_SESSION['registration_data'] = $_POST;
        header('Location: staff_register.php');
        exit();
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $role = $_POST['role'] ?? 'staff';

    $errors = [];

    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    } elseif (!preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[\W]/', $password)) {
        $errors[] = 'Password must contain uppercase, lowercase, number, and special character.';
    }

    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($full_name)) {
        $errors[] = 'Full name is required.';
    }

    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }

    if (!empty($phone) && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $errors[] = 'Invalid phone number format. Only digits, +, - and spaces allowed (7-20 characters).';
    }

    // Check if username already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM staffs WHERE staff_username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Username already exists.';
    }

    // Check if email already exists
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM staffs WHERE staff_email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Email already exists.';
    }

    // Handle profile picture upload
    $profile_picture_path = null;
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['profile_picture']['type'], $allowed_types) && $_FILES['profile_picture']['size'] <= 2 * 1024 * 1024) {
            $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $profile_picture_path = 'uploads/profile_picture/' . uniqid('staff_', true) . '.' . $ext;
            if (!is_dir('../uploads/profile_picture')) {
                mkdir('../uploads/profile_picture', 0777, true);
            }
            move_uploaded_file($_FILES['profile_picture']['tmp_name'], '../' . $profile_picture_path);
        } else {
            $errors[] = 'Invalid profile picture. Only JPG, PNG, GIF under 2MB allowed.';
        }
    }

    if (empty($errors)) {
        // Generate unique staff ID
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM staffs');
        $stmt->execute();
        $count = $stmt->fetchColumn() + 1;
        $staff_ID = 'STAFF' . str_pad($count, 4, '0', STR_PAD_LEFT);
        
        // Generate verification token
        $verification_token = bin2hex(random_bytes(32));
        
        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new staff with pending_verification status
        $stmt = $pdo->prepare('INSERT INTO staffs (staff_ID, staff_username, staff_password_hash, staff_full_name, staff_email, staff_phone_no, staff_profile_picture, staff_role, is_active, status, verification_token, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, "pending_verification", ?, NOW())');
        
        if ($stmt->execute([$staff_ID, $username, $password_hash, $full_name, $email, $phone, $profile_picture_path, $role, $verification_token])) {
            // Send verification email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'rayn2309@gmail.com';
                $mail->Password = 'rswkomrtxirvjjrc';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                $mail->setFrom('rayn2309@gmail.com', 'PS4 Rental System');
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'Verify Your Staff Account Email Address';
                $verify_link = 'http://' . $_SERVER['HTTP_HOST'] . '/ps4rentalsystem/customer/verify_email.php?token=' . $verification_token;
                $mail->Body = "<p>Thank you for registering as staff. Please verify your email by clicking the link below:</p>"
                    . "<p><a href='$verify_link'>$verify_link</a></p>";
                $mail->send();
            } catch (Exception $e) {
                // Email sending failed, but registration succeeded
                error_log('Email sending failed: ' . $mail->ErrorInfo);
            }
            
            $_SESSION['registration_success'] = 'Registration successful! Please check your email to verify your account.';
            header('Location: staff_register.php');
            exit();
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['registration_errors'] = $errors;
        $_SESSION['registration_data'] = $_POST;
        header('Location: staff_register.php');
        exit();
    }
} else {
    header('Location: staff_register.php');
    exit();
}
?>