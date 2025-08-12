<?php 
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
ob_start();

session_start();

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Reader\Xls;

$pythonPath = "C:\\Users\\Asus\\AppData\\Local\\Programs\\Python\\Python313\\python.exe";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(["success" => false, "message" => "File upload failed."]);
        exit();
    }

    $file = $_FILES['file'];
    $fileTmpPath = $file['tmp_name'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $uploadDir = __DIR__ . "/uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileName = uniqid("upload_", true) . "." . $fileExtension;
    $filePath = $uploadDir . $fileName;

    $allowedExtensions = ['xlsx', 'csv'];
    if (!in_array($fileExtension, $allowedExtensions)) {
        echo json_encode(["success" => false, "message" => "Invalid file format."]);
        exit();
    }

    if (!move_uploaded_file($fileTmpPath, $filePath)) {
        echo json_encode(["success" => false, "message" => "Failed to save uploaded file."]);
        exit();
    }

    // Load file and extract data based on extension
    if ($fileExtension === "xls") {
        $reader = new Xls();
    } elseif ($fileExtension === "xlsx") {
        $reader = new Xlsx();
    } else {
        $reader = IOFactory::createReader('Csv');
    }

    $spreadsheet = $reader->load($filePath);
    $sheet = $spreadsheet->getActiveSheet();
    $data = $sheet->toArray(null, true, true, true);

    $headers = array_shift($data); // Get headers
    $extractedData = [];

    foreach ($data as $row) {
        $rowValues = array_values($row);
        $extractedData[] = $rowValues;
    }

    // Save a temporary cleaned file **without row numbers**
    $tempFilePath = $uploadDir . uniqid("cleaned_", true) . "." . $fileExtension;
    $tempSpreadsheet = new Spreadsheet();
    $tempSheet = $tempSpreadsheet->getActiveSheet();

    // Write headers and data
    $tempSheet->fromArray([$headers], null, 'A1'); // Headers
    $tempSheet->fromArray($extractedData, null, 'A2'); // Data

    //$writer = IOFactory::createWriter($tempSpreadsheet, ucfirst($fileExtension) === 'Csv' ? 'Csv' : 'Xlsx');
    
    if ($fileExtension === "xls") {
        $writer = IOFactory::createWriter($tempSpreadsheet, 'Xls');
    } elseif ($fileExtension === "xlsx") {
        $writer = IOFactory::createWriter($tempSpreadsheet, 'Xlsx');
    } else {
        $writer = IOFactory::createWriter($tempSpreadsheet, 'Csv');
    }

    $writer->save($tempFilePath);

    // Call Python script with temp file (without row numbers)
    $command = escapeshellcmd("$pythonPath C:\\xampp\\htdocs\\fyp\\cleanData.py " . escapeshellarg($tempFilePath));
    $output = shell_exec($command);


    if ($output === null || trim($output) === "") {
        echo json_encode(["success" => false, "message" => "Error: No response from AI script."]);
        exit();
    }

    $decodedOutput = json_decode($output, true);
    if ($decodedOutput === null) {
        echo json_encode([
            "success" => false,
            "message" => "Error processing file: Invalid AI response.",
            "raw_output" => $output // Log raw output for debugging
        ]);
        exit();
    }

    // Save cleaned file path if available
    $cleanedFilePath = $decodedOutput["cleaned_file"] ?? null;
    if ($cleanedFilePath && file_exists($cleanedFilePath)) {
        $_SESSION['cleanedFile'] = $cleanedFilePath;
    }

    // Store extracted data, headers, and duplicates in session
    $_SESSION['headers'] = array_values($headers);
    $_SESSION['extractedData'] = $extractedData;
    $_SESSION['duplicateData'] = $decodedOutput["duplicates"] ?? [];
    $_SESSION['missing_values'] = $decodedOutput["missing_values"] ?? [];
    $_SESSION['inaccuracies'] = $decodedOutput["inaccuracies"] ?? [];

    error_log("DEBUG: Extracted Data - " . json_encode($extractedData));

    echo json_encode([
        "success" => true,
        "message" => "Data processed successfully.",
        "headers" => array_values($headers),
        "extractedData" =>  $_SESSION["extractedData"],
        "duplicates" => $decodedOutput["duplicates"] ?? [],
        "missing_values" => $decodedOutput["missing_values"] ?? [],
        "inaccuracies" => $decodedOutput["inaccuracies"] ?? [],
        "cleaned_file" => $cleanedFilePath ?? []
    ]);
    
    exit();
}
?>
