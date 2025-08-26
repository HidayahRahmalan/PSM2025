<?php
$host = "localhost";
$user = "B032210192";
$password = "Password123";
$db = "PSM_B032210192";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


function convert_unit($value, $from_unit, $to_unit) {
    $from_unit = strtolower(trim($from_unit));
    $to_unit = strtolower(trim($to_unit));
    if ($from_unit == $to_unit) return $value;
    $base_value = $value;
    switch ($from_unit) {
        case 'kg': $base_value = $value * 1000; break;
        case 'l':  $base_value = $value * 1000; break;
    }
    switch ($to_unit) {
        case 'kg': return $base_value / 1000;
        case 'l':  return $base_value / 1000;
    }
    return $base_value;
}
?>