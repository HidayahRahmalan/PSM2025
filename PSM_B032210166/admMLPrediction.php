<?php
// Machine Learning Prediction System for Room Demand

// Suppress error output to prevent JSON corruption
error_reporting(0);
ini_set('display_errors', 0);

// No more "session already active" errors, 
// Added conditional session start using if (session_status() === PHP_SESSION_NONE)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
include 'dbConnection.php';

// Include ML functions
include 'admMLFunctions.php';

// Set JSON header
header('Content-Type: application/json');

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Main prediction logic
try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'train_model':
            // Train the ML model
            $datasetPath = $_POST['dataset_path'] ?? '';
            
            if (empty($datasetPath)) {
                echo json_encode(['success' => false, 'message' => 'Dataset path not provided']);
                exit();
            }
            
            // Load and preprocess data
            $dataResult = loadAndPreprocessData($datasetPath);
            if (!$dataResult['success']) {
                echo json_encode($dataResult);
                exit();
            }
            
            // Extract features
            $mlData = extractFeatures($dataResult['data']);
            
            // Train Python Random Forest model
            $command = "python ml_random_forest.py train " . escapeshellarg($datasetPath) . " 100 10";
            $output = shell_exec($command);
            $result = json_decode($output, true);
            
            if ($result && $result['success']) {
                // Save model info to session
                $_SESSION['ml_model'] = $result;
                $_SESSION['hostel_mapping'] = $result['hostel_mapping'];
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Python Random Forest model trained successfully',
                    'training_samples' => $result['data_info']['total_samples'],
                    'hostels' => count($result['hostel_mapping']),
                    'model_evaluation' => $result['evaluation'],
                    'feature_importance' => $result['feature_importance']
                ]);
            } else {
                $errorMsg = $result ? $result['message'] : 'Failed to execute Python script';
                echo json_encode(['success' => false, 'message' => 'Failed to train model: ' . $errorMsg]);
            }
            break;
            
        case 'predict':
            // Make predictions for future semesters
            if (!isset($_SESSION['ml_model'])) {
                echo json_encode(['success' => false, 'message' => 'No trained model found. Please train the model first.']);
                exit();
            }
            
            if (!isset($_SESSION['hostel_mapping'])) {
                echo json_encode(['success' => false, 'message' => 'No hostel mapping found. Please train the model first.']);
                exit();
            }
            
            // Debug ML model
            error_log("ML Model Debug:");
            error_log("- Model success: " . ($_SESSION['ml_model']['success'] ? 'YES' : 'NO'));
            if (isset($_SESSION['ml_model']['forest'])) {
                error_log("- Model Type: Random Forest");
                error_log("- Number of Trees: " . $_SESSION['ml_model']['nTrees']);
                error_log("- Max Depth: " . $_SESSION['ml_model']['maxDepth']);
            } else {
                error_log("- Model Type: Legacy Linear Regression");
                error_log("- Intercept: " . ($_SESSION['ml_model']['intercept'] ?? 'N/A'));
            }
            
            // Get current semester using CheckInDate and CheckOutDate
            $currentDate = date('Y-m-d');
            $currentSemQuery = $conn->query("
                SELECT SemID, AcademicYear, Semester, CheckInDate, CheckOutDate
                FROM SEMESTER 
                WHERE '$currentDate' BETWEEN CheckInDate AND CheckOutDate 
                LIMIT 1
            ");
            
            if (!$currentSemRow = $currentSemQuery->fetch_assoc()) {
                // If no current semester found, find the most recent semester that has ended
                $currentSemQuery = $conn->query("
                    SELECT SemID, AcademicYear, Semester, CheckInDate, CheckOutDate
                    FROM SEMESTER 
                    WHERE CheckOutDate < '$currentDate'
                    ORDER BY CheckOutDate DESC
                    LIMIT 1
                ");
                
                if (!$currentSemRow = $currentSemQuery->fetch_assoc()) {
                    echo json_encode(['success' => false, 'message' => 'No semester data found']);
                    exit();
                }
            }
            
            // Use the actual current semester as target for prediction
            $currentSemester = [
                'SemID' => $currentSemRow['SemID'],
                'AcademicYear' => $currentSemRow['AcademicYear'],
                'Semester' => $currentSemRow['Semester'],
                'CheckInDate' => $currentSemRow['CheckInDate'],
                'CheckOutDate' => $currentSemRow['CheckOutDate']
            ];
            error_log("Using current semester as target: " . $currentSemester['AcademicYear'] . " Semester " . $currentSemester['Semester'] . " (ID: " . $currentSemester['SemID'] . ")");
            
            // Debug: Check all available semesters
            $allSemestersQuery = $conn->query("
                SELECT SemID, AcademicYear, Semester, CheckInDate, CheckOutDate
                FROM SEMESTER 
                ORDER BY CheckInDate ASC
            ");
            error_log("Available semesters:");
            while ($semRow = $allSemestersQuery->fetch_assoc()) {
                error_log("- " . $semRow['AcademicYear'] . " Semester " . $semRow['Semester'] . " (ID: " . $semRow['SemID'] . ") - " . $semRow['CheckInDate'] . " to " . $semRow['CheckOutDate']);
            }
            
            // Debug: Check if there are any bookings at all
            $bookingCountQuery = $conn->query("
                SELECT COUNT(*) as total_bookings,
                       COUNT(CASE WHEN Status = 'APPROVED' THEN 1 END) as approved_bookings,
                       COUNT(CASE WHEN Status = 'REJECTED' THEN 1 END) as rejected_bookings
                FROM BOOKING
            ");
            $bookingCount = $bookingCountQuery->fetch_assoc();
            error_log("Total bookings: " . $bookingCount['total_bookings'] . ", Approved: " . $bookingCount['approved_bookings'] . ", Rejected: " . $bookingCount['rejected_bookings']);
            
            // Debug: Check bookings by semester
            $bookingBySemesterQuery = $conn->query("
                SELECT b.SemID, s.AcademicYear, s.Semester,
                       COUNT(*) as total_bookings,
                       COUNT(CASE WHEN b.Status = 'APPROVED' THEN 1 END) as approved_bookings
                FROM BOOKING b
                JOIN SEMESTER s ON b.SemID = s.SemID
                GROUP BY b.SemID, s.AcademicYear, s.Semester
                ORDER BY s.CheckInDate ASC
            ");
            error_log("Bookings by semester:");
            while ($semBookingRow = $bookingBySemesterQuery->fetch_assoc()) {
                error_log("- " . $semBookingRow['AcademicYear'] . " Semester " . $semBookingRow['Semester'] . " (ID: " . $semBookingRow['SemID'] . "): " . $semBookingRow['total_bookings'] . " total, " . $semBookingRow['approved_bookings'] . " approved");
            }
            
            // Get hostels
            $hostels = [];
            $res = $conn->query("SELECT HostID, Name FROM HOSTEL WHERE Status='ACTIVE'");
            while ($row = $res->fetch_assoc()) {
                $hostels[] = $row;
            }
            
            // Get future semester data
            $futureData = getFutureSemesterData($conn, $currentSemester, $hostels, $_SESSION['hostel_mapping']);
            
            // Get severe chronic percentage for room calculations
            $severeData = getSevereChronicPercentage($conn);
            $severePercentage = $severeData['severe_percentage'];
            error_log("Severe chronic percentage: " . $severePercentage . "% (" . $severeData['severe_count'] . "/" . $severeData['total_students'] . ")");
            
            // Make predictions
            $predictions = [];
            foreach ($futureData as $data) {
                $features = [
                    'semester' => $data['semester'],
                    'year' => $data['year'],
                    'hostel_id' => $data['hostel_id'],
                    'total_severe_chronic' => $data['total_severe_chronic'],
                    'booked_severe_chronic' => $data['booked_severe_chronic'],
                    'room_full_rejections' => $data['room_full_rejections'],
                    'unbooked_severe_chronic' => $data['unbooked_severe_chronic'],
                    'graduating_students' => $data['graduating_students'],
                    'current_occupancy' => $data['current_occupancy']
                ];
                
                // Debug ML prediction
                error_log("ML Prediction Debug for " . $data['hostel_name'] . ":");
                error_log("- Features: " . json_encode($features));
                
                // Use Python Random Forest for prediction
                $featuresJson = json_encode($features);
                $hostelMappingJson = json_encode($_SESSION['hostel_mapping']);
                $modelPath = 'models/random_forest_model.pkl'; // Use consistent model file
                
                // Write to temporary files to avoid shell escaping issues
                $tempFeaturesFile = 'temp_features_' . uniqid() . '.json';
                $tempMappingFile = 'temp_mapping_' . uniqid() . '.json';
                
                file_put_contents($tempFeaturesFile, $featuresJson);
                file_put_contents($tempMappingFile, $hostelMappingJson);
                
                $command = "python ml_random_forest.py predict " . escapeshellarg($modelPath) . " " . escapeshellarg($tempFeaturesFile) . " " . escapeshellarg($tempMappingFile);
                $output = shell_exec($command);
                $prediction = json_decode($output, true);
                
                // Clean up temporary files
                unlink($tempFeaturesFile);
                unlink($tempMappingFile);
                
                error_log("- Model Type: Python Random Forest");
                error_log("- Python Command: " . $command);
                error_log("- Python Output: " . $output);
                
                error_log("- Raw ML Prediction: " . $prediction['prediction']);
                error_log("- Historical Prediction: " . $data['historical_prediction']);
                
                if ($prediction['success']) {
                    // OPTION A: Simple Fallback - Clean and Reliable
                    $mlPrediction = $prediction['prediction'];
                    
                    // If ML prediction is unrealistic, use historical as fallback
                    if ($mlPrediction <= 0 || $mlPrediction < $data['current_occupancy']) {
                        $mlPrediction = $data['historical_prediction']; // 100% historical fallback
                        error_log("ML prediction unrealistic (" . $prediction['prediction'] . "), using historical fallback: " . $data['historical_prediction']);
                    }
                    
                    // Calculate final prediction with clean weighted average
                    $mlWeight = 0.4; // 40% weight for ML
                    $historicalWeight = 0.6; // 60% weight for Historical
                    
                    $finalPrediction = ceil(($mlPrediction * $mlWeight) + ($data['historical_prediction'] * $historicalWeight));
                    $finalPrediction = max(1, $finalPrediction); // Ensure minimum of 1
                    
                    error_log("- Original ML Prediction: " . $prediction['prediction']);
                    error_log("- Adjusted ML Prediction: " . $mlPrediction);
                    error_log("- Final Prediction: " . $finalPrediction);
                    
                    // Calculate room recommendation for this hostel
                    // Find hostel ID from the hostels array
                    $hostelID = '';
                    foreach ($hostels as $hostel) {
                        if (strtoupper(trim($hostel['Name'])) === strtoupper(trim($data['hostel_name']))) {
                            $hostelID = $hostel['HostID'];
                            break;
                        }
                    }
                    $roomCapacity = getHostelRoomCapacity($conn, $hostelID);
                    $roomRecommendation = calculateRoomRecommendationWithHistorical($finalPrediction, $data['total_severe_chronic'], $roomCapacity['avg_room_capacity']);
                    $roomGap = $roomRecommendation['total_rooms_needed'] - $roomCapacity['total_rooms'];
                    
                    error_log("Room recommendation for " . $data['hostel_name'] . ": " . $roomRecommendation['total_rooms_needed'] . " needed vs " . $roomCapacity['total_rooms'] . " current (gap: " . $roomGap . ")");
                    error_log("Adding prediction to array: " . $data['hostel_name']);
                    
                    $predictions[] = [
                        'hostel_name' => $data['hostel_name'],
                        'semester' => $data['semester'],
                        'year' => $data['year'],
                        'display_year' => $data['display_year'] ?? $data['year'], // Use display_year if available, fallback to year
                        'predicted_demand' => $finalPrediction,
                        'current_occupancy' => $data['current_occupancy'],
                        'severe_chronic_students' => $data['total_severe_chronic'],
                        'room_full_rejections' => $data['room_full_rejections'],
                        'returning_students' => $data['returning_students'],
                        'graduating_students' => $data['graduating_students'], // ADDED: Include graduating students
                        'estimated_new_students' => $data['estimated_new_students'],
                        'ml_prediction' => $mlPrediction, // Use adjusted ML prediction
                        'estimated_demand' => $data['estimated_future_demand'],
                        'historical_prediction' => $data['historical_prediction'],
                        'historical_estimate' => $data['historical_estimate'],
                        'ml_estimate' => $data['ml_estimate'],
                        // Room recommendation data
                        'current_rooms' => $roomCapacity['total_rooms'],
                        'total_bed_capacity' => $roomCapacity['total_bed_capacity'],
                        'avg_room_capacity' => round($roomCapacity['avg_room_capacity'], 1),
                        'severe_percentage' => 'Historical',
                        'estimated_severe_students' => $roomRecommendation['estimated_severe'],
                        'estimated_normal_students' => $roomRecommendation['estimated_normal'],
                        'rooms_needed' => $roomRecommendation['total_rooms_needed'],
                        'room_gap' => $roomGap
                    ];
                }
            }
            
            error_log("Final predictions array: " . json_encode($predictions));
            
            $response = [
                'success' => true,
                'predictions' => $predictions,
                'current_semester' => $currentSemester
            ];
            
            // Ensure clean JSON response
            if (headers_sent()) {
                error_log("Headers already sent - cannot send JSON response");
                echo json_encode([
                    'success' => false,
                    'message' => 'Headers already sent - response corrupted'
                ]);
            } else {
                header('Content-Type: application/json');
                error_log("Sending response: " . json_encode($response));
                echo json_encode($response);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (Exception $e) {
    error_log("ML Prediction Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Ensure clean JSON response
    if (headers_sent()) {
        error_log("Headers already sent - cannot send JSON response");
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error in ML prediction: ' . $e->getMessage()
        ]);
    }
}
?> 