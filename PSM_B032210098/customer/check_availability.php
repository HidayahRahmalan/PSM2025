<?php
require_once '../db_connection.php';
header('Content-Type: application/json');
$response = ['available' => false, 'field' => '', 'message' => ''];
if (isset($_GET['username'])) {
    $username = $_GET['username'];
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE customer_username = ?');
    $stmt->execute([$username]);
    $response['field'] = 'username';
    $response['available'] = $stmt->fetchColumn() == 0;
    $response['message'] = $response['available'] ? 'Username is available.' : 'Username is already taken.';
} elseif (isset($_GET['email'])) {
    $email = $_GET['email'];
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM customers WHERE customer_email = ?');
    $stmt->execute([$email]);
    $response['field'] = 'email';
    $response['available'] = $stmt->fetchColumn() == 0;
    $response['message'] = $response['available'] ? 'Email is available.' : 'Email is already registered.';
}
echo json_encode($response); 