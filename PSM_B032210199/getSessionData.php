<?php


header("Content-Type: application/json");
session_start();

echo json_encode([
    "headers" => $_SESSION['headers'] ?? [],
    "extractedData" => $_SESSION['extractedData'] ?? [],
    "duplicateData" => $_SESSION['duplicateData'] ?? [],
    "missing_values" => $_SESSION['missing_values'] ?? [],
    "inaccuracies" => $_SESSION['inaccuracies'] ?? []
]);
?>
