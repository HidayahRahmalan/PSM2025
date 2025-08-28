<?php
session_start();
require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        header('Location: login.php?error=csrf');
        exit();
    }
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($login) || empty($password)) {
        header('Location: login.php?error=empty_fields');
        exit();
    }
    
    // Check if customer exists by username or email
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_username = ? OR customer_email = ?');
    $stmt->execute([$login, $login]);
    $customer = $stmt->fetch();
    
    // Check for account lockout
    if ($customer) {
        $lockout_limit = 5;
        $lockout_time = 10 * 60; // 10 minutes in seconds
        if ($customer['failed_login_attempts'] >= $lockout_limit && $customer['last_failed_login'] && (time() - strtotime($customer['last_failed_login'])) < $lockout_time) {
            header('Location: login.php?error=locked');
            exit();
        }
    }
    
    if ($customer && password_verify($password, $customer['customer_password_hash'])) {
        // Check if customer is active
        if ($customer['status'] === 'banned') {
            header('Location: login.php?error=account_banned');
            exit();
        }
        if ($customer['status'] === 'pending_verification') {
            header('Location: login.php?error=not_verified');
            exit();
        }
        
        // Reset failed login attempts
        $stmt = $pdo->prepare('UPDATE customers SET failed_login_attempts = 0, last_failed_login = NULL WHERE customer_ID = ?');
        $stmt->execute([$customer['customer_ID']]);
        
        // Session security
        session_regenerate_id(true);
        
        // Login successful
        $_SESSION['customer_ID'] = $customer['customer_ID'];
        $_SESSION['customer_username'] = $customer['customer_username'];
        $_SESSION['customer_full_name'] = $customer['customer_full_name'];
        $_SESSION['user_type'] = 'customer';
        
        // Remember Me functionality
        if (isset($_POST['remember_me'])) {
            $remember_token = bin2hex(random_bytes(32));
            setcookie('remember_me', $remember_token, time() + (86400 * 30), "/", "", false, true); // 30 days, httpOnly
            $stmt = $pdo->prepare('UPDATE customers SET remember_token = ? WHERE customer_ID = ?');
            $stmt->execute([$remember_token, $customer['customer_ID']]);
        }
        
        // Update last login
        $stmt = $pdo->prepare('UPDATE customers SET last_login = NOW() WHERE customer_ID = ?');
        $stmt->execute([$customer['customer_ID']]);
        
        header('Location: home.php');
        exit();
    } else {
        // Login failed: increment failed_login_attempts
        if ($customer) {
            $stmt = $pdo->prepare('UPDATE customers SET failed_login_attempts = failed_login_attempts + 1, last_failed_login = NOW() WHERE customer_ID = ?');
            $stmt->execute([$customer['customer_ID']]);
        }
        header('Location: login.php?error=invalid_credentials');
        exit();
    }
} else {
    // Not a POST request
    header('Location: login.php');
    exit();
}
?> 