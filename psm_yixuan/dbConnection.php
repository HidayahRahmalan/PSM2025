<?php
$db_server = "localhost";
$db_user = "root";
$db_password = "Abcd1234"; 
$db_name = "fyp_shms";

try {
    // Create connection
    $conn = new mysqli($db_server, $db_user, $db_password, $db_name);

} catch (mysqli_sql_exception $e) {
    // Handle the exception and show a custom error message
    echo "Connection to database " . $db_name . " failed <br>";
}
?>