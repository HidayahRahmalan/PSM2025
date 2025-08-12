<?php
$servername = "localhost";
//$username = "root";
$username = "vanness";
$password = "password";
//$dbname = "dqms"; 
$dbname = "psm_dqms"; 

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}
//echo "Connected successfully";
?>
