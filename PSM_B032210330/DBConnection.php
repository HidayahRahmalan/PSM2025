<?php
$servername = "localhost";
$username = "PSM_B032210330";
$password = "nutrieats1";
$dbname = "PSM_B032210330";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} /*else {
    echo "Database connection successful!";
} */
?>
