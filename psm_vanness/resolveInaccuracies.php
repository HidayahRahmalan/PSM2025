<?php
header('Content-Type: application/json');
// Read and log the raw JSON request
$rawInput = file_get_contents("php://input");
error_log("📩 Received JSON in PHP: " . $rawInput);

function resolveWithPython($iaData, $fullData, $reportType) {
    $apiURL = "http://localhost:8002/resolve_inaccuracies"; 

    // Flatten the inaccuracies fields into the top-level
    $formattedPayload = array_merge($iaData, [
        "data" => $fullData,
        "report_type" => $reportType
    ]);
    
    $payload = json_encode($formattedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    // Log request before sending
    error_log("🚀 Sending to FastAPI: " . $payload);
    file_put_contents("debug_log.txt", "🚀 Sending to FastAPI: " . $payload . PHP_EOL, FILE_APPEND);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiURL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Log FastAPI response or cURL errors
    if ($response === false) {
        error_log("🚨 cURL Error: " . $curlError);
        file_put_contents("debug_log.txt", "🚨 cURL Error: " . $curlError . PHP_EOL, FILE_APPEND);
        return ["error" => "Failed to connect to FastAPI", "curl_error" => $curlError];
    } else {
        error_log("✅ FastAPI Response: " . $response);
        file_put_contents("debug_log.txt", "✅ FastAPI Response: " . $response . PHP_EOL, FILE_APPEND);
    }

    if ($httpCode !== 200) {
        return ["error" => "FastAPI returned HTTP $httpCode", "response" => $response];
    }

    return json_decode($response, true);
}

// Read and decode incoming JSON data
$inputData = json_decode($rawInput, true);

if (!$inputData) {
    $errorResponse = json_encode(["error" => "Invalid or empty input data", "received" => $rawInput]);
    error_log("🚨 Invalid Input: " . $errorResponse);
    file_put_contents("debug_log.txt", "🚨 Invalid Input: " . $errorResponse . PHP_EOL, FILE_APPEND);
    echo $errorResponse;
    exit;
}

// Extract necessary fields from input data
$categories = ['negative_values', 'incorrect_amount_calculation', 'misleading_timestamps', 'wrong_status_value'];

$inaccuraciesValues = [];
foreach ($categories as $cat) {
    if (!empty($inputData[$cat]) && is_array($inputData[$cat])) {
        $inaccuraciesValues[$cat] = $inputData[$cat];
    }
}

$fullData = $inputData['data'] ?? [];
$reportType = $inputData['report_type'] ?? 'unknown';

// Validate required fields
if (empty($inaccuraciesValues) || empty($fullData)) {
    $errorResponse = json_encode(["error" => "Missing required data: 'inaccuracies' or 'data'"]);
    error_log("🚨 inaccuracies Data Error: " . $errorResponse);
    file_put_contents("debug_log.txt", "🚨 inaccuracies Data Error: " . $errorResponse . PHP_EOL, FILE_APPEND);
    echo $errorResponse;
    exit;
}

$reformattedInaccuracies = [];

foreach ($inaccuraciesValues as $type => $values) {
    $reformattedInaccuracies[$type] = [];
    foreach ($values as $value) {
        if (!isset($value['row'], $value['column'], $value['column_name'])) {
            error_log("⚠️ Skipped malformed inaccuracy value: " . json_encode($value));
            continue;
        }

        $reformattedInaccuracies[$type][] = [
            'row' => $value['row'],
            'column' => $value['column'],
            'column_name' => $value['column_name']
        ];
    }
}


// Send the restructured data to FastAPI
$resolvedData = resolveWithPython($reformattedInaccuracies, $fullData, $reportType);

// Validate AI response
if (!is_array($resolvedData)) {
    $errorResponse = json_encode(["error" => "Failed to process response"]);
    error_log("🚨 AI Response Error: " . $errorResponse);
    file_put_contents("debug_log.txt", "🚨 Response Error: " . $errorResponse . PHP_EOL, FILE_APPEND);
    echo $errorResponse;
    exit;
}

// Return resolved data
$responseJson = json_encode($resolvedData);  // Already structured with "resolved" key
error_log("✅ Final Response to Frontend: " . $responseJson);
file_put_contents("debug_log.txt", "✅ Final Response to Frontend: " . $responseJson . PHP_EOL, FILE_APPEND);

echo $responseJson;
exit;
?>
