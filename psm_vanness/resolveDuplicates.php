<?php
header('Content-Type: application/json');
// Read and log the raw JSON request
$rawInput = file_get_contents("php://input");
error_log("📩 Received JSON in PHP: " . $rawInput);

function resolveWithPython($duplicateData) {
    $apiURL = "http://localhost:8000/resolve_duplicates"; 

    // ✅ New: Send directly
    $payload = json_encode($duplicateData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // ✅ Log request before sending
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

     // ✅ Log FastAPI response or cURL errors
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

if (!$inputData || !isset($inputData['fully_identical_rows'])) {
    $errorResponse = json_encode(["error" => "Invalid or empty input data", "received" => $rawInput]);
    error_log("🚨 Invalid Input: " . $errorResponse);
    file_put_contents("debug_log.txt", "🚨 Invalid Input: " . $errorResponse . PHP_EOL, FILE_APPEND);
    echo $errorResponse;
    exit;
}


// ✅ Call FastAPI to resolve duplicates
$resolvedData = resolveWithPython($inputData);


// ✅ Validate AI response
if (!is_array($resolvedData)) {
    $errorResponse = json_encode(["error" => "Failed to process response"]);
    error_log("🚨 Response Error: " . $errorResponse);
    file_put_contents("debug_log.txt", "🚨 Response Error: " . $errorResponse . PHP_EOL, FILE_APPEND);
    echo $errorResponse;
    exit;
}

// ✅ Return resolved data
$responseJson = json_encode(["resolved" => $resolvedData]);
error_log("✅ Final Response to Frontend: " . $responseJson);
file_put_contents("debug_log.txt", "✅ Final Response to Frontend: " . $responseJson . PHP_EOL, FILE_APPEND);

echo $responseJson;
exit;
?>
