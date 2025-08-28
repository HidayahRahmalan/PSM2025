<?php
session_start();

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Clear the remember_me cookie
setcookie('remember_me', '', time() - 3600, "/");

// Redirect to home page
header('Location: index.php');
exit();
?> 