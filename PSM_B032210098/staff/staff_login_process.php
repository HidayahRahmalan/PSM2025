<?php
session_start();
require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        header('Location: staff_login.php?error=csrf');
        exit();
    }
    $login = $_POST['login'] ?? '';
    $password = $_POST['password'] ?? '';
    if (empty($login) || empty($password)) {
        header('Location: staff_login.php?error=empty_fields');
        exit();
    }
    $stmt = $pdo->prepare('SELECT * FROM staffs WHERE staff_username = ? OR staff_email = ?');
    $stmt->execute([$login, $login]);
    $staff = $stmt->fetch();
    // Check for account lockout
    if ($staff) {
        $lockout_limit = 5;
        $lockout_time = 10 * 60; // 10 minutes
        if ($staff['failed_login_attempts'] >= $lockout_limit && $staff['last_failed_login'] && (time() - strtotime($staff['last_failed_login'])) < $lockout_time) {
            header('Location: staff_login.php?error=locked');
            exit();
        }
    }
    if ($staff && password_verify($password, $staff['staff_password_hash'])) {
        if ($staff['is_active'] == 0) {
            header('Location: staff_login.php?error=account_banned');
            exit();
        }
        // Session security
        session_regenerate_id(true);
        // Reset failed login attempts
        $stmt = $pdo->prepare('UPDATE staffs SET failed_login_attempts = 0, last_failed_login = NULL WHERE staff_ID = ?');
        $stmt->execute([$staff['staff_ID']]);
        $_SESSION['staff_ID'] = $staff['staff_ID'];
        $_SESSION['staff_username'] = $staff['staff_username'];
        $_SESSION['staff_full_name'] = $staff['staff_full_name'];
        $_SESSION['user_type'] = 'staff';
        // Remember Me functionality
        if (isset($_POST['remember_me'])) {
            $remember_token = bin2hex(random_bytes(32));
            setcookie('staff_remember_me', $remember_token, time() + (86400 * 30), "/", "", false, true);
            $stmt = $pdo->prepare('UPDATE staffs SET remember_token = ? WHERE staff_ID = ?');
            $stmt->execute([$remember_token, $staff['staff_ID']]);
        }
        header('Location: dashboard.php');
        exit();
    } else {
        // Login failed: increment failed_login_attempts
        if ($staff) {
            $stmt = $pdo->prepare('UPDATE staffs SET failed_login_attempts = failed_login_attempts + 1, last_failed_login = NOW() WHERE staff_ID = ?');
            $stmt->execute([$staff['staff_ID']]);
        }
        header('Location: staff_login.php?error=invalid_credentials');
        exit();
    }
} else {
    // Not a POST request
    header('Location: staff_login.php');
    exit();
}
?> 