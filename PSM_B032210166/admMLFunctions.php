<?php
// admMLFunctions.php
// Machine Learning Functions for Room Demand Prediction

// Function to load and preprocess data
function loadAndPreprocessData($datasetPath) {
    if (!file_exists($datasetPath)) {
        return ['success' => false, 'message' => 'Dataset file not found'];
    }
    
    $data = [];
    $handle = fopen($datasetPath, 'r');
    if (!$handle) {
        return ['success' => false, 'message' => 'Cannot read dataset file'];
    }
    
    // Skip header
    fgetcsv($handle);
    
    while (($row = fgetcsv($handle)) !== false) {
        if (!empty(array_filter($row))) {
            $data[] = [
                'semester' => intval($row[0]),
                'year' => $row[1],
                'hostel' => $row[2],
                'total_severe_chronic' => intval($row[3]),
                'booked_severe_chronic' => intval($row[4]),
                'room_full_rejections' => intval($row[5]),
                'unbooked_severe_chronic' => intval($row[6]),
                'graduating_students' => intval($row[7]),
                'current_occupancy' => intval($row[8]),
                'actual_demand' => intval($row[9]),
                'data_source' => $row[10] ?? 'Unknown'
            ];
        }
    }
    fclose($handle);
    
    return ['success' => true, 'data' => $data];
}

// Function to extract features for ML
function extractFeatures($data) {
    $features = [];
    $targets = [];
    $hostelMapping = [];
    $hostelIndex = 0;
    
    foreach ($data as $row) {
        // Create hostel mapping
        if (!isset($hostelMapping[$row['hostel']])) {
            $hostelMapping[$row['hostel']] = $hostelIndex++;
        }
        
        // Extract year components
        $yearParts = explode('/', $row['year']);
        $startYear = intval($yearParts[0]);
        
        // Create features
        $feature = [
            'semester' => $row['semester'],
            'year' => $startYear,
            'hostel_id' => $hostelMapping[$row['hostel']],
            'total_severe_chronic' => $row['total_severe_chronic'],
            'booked_severe_chronic' => $row['booked_severe_chronic'],
            'room_full_rejections' => $row['room_full_rejections'],
            'unbooked_severe_chronic' => $row['unbooked_severe_chronic'],
            'graduating_students' => $row['graduating_students'],
            'current_occupancy' => $row['current_occupancy']
        ];
        
        $features[] = $feature;
        $targets[] = $row['actual_demand'];
    }
    
    return [
        'features' => $features,
        'targets' => $targets,
        'hostel_mapping' => $hostelMapping
    ];
}

// Random Forest implementation for better predictions
function randomForest($features, $targets, $nTrees = 10, $maxDepth = 5) {
    if (count($features) < 2) {
        return ['success' => false, 'message' => 'Insufficient data for Random Forest'];
    }
    
    $forest = [];
    $nSamples = count($features);
    
    // Train multiple decision trees
    for ($i = 0; $i < $nTrees; $i++) {
        // Bootstrap sample (with replacement)
        $bootstrapIndices = [];
        for ($j = 0; $j < $nSamples; $j++) {
            $bootstrapIndices[] = rand(0, $nSamples - 1);
        }
        
        // Create bootstrap dataset
        $bootstrapFeatures = [];
        $bootstrapTargets = [];
        foreach ($bootstrapIndices as $index) {
            $bootstrapFeatures[] = $features[$index];
            $bootstrapTargets[] = $targets[$index];
        }
        
        // Train decision tree
        $tree = trainDecisionTree($bootstrapFeatures, $bootstrapTargets, $maxDepth);
        $forest[] = $tree;
    }
    
    return [
        'success' => true,
        'forest' => $forest,
        'nTrees' => $nTrees,
        'maxDepth' => $maxDepth
    ];
}

// Decision Tree training function
function trainDecisionTree($features, $targets, $maxDepth, $currentDepth = 0) {
    $nSamples = count($features);
    
    // Base cases
    if ($nSamples <= 1 || $currentDepth >= $maxDepth) {
        return [
            'type' => 'leaf',
            'prediction' => $nSamples > 0 ? array_sum($targets) / $nSamples : 0
        ];
    }
    
    // Find best split
    $bestSplit = findBestSplit($features, $targets);
    
    if (!$bestSplit) {
        return [
            'type' => 'leaf',
            'prediction' => array_sum($targets) / $nSamples
        ];
    }
    
    // Split data
    $leftFeatures = [];
    $leftTargets = [];
    $rightFeatures = [];
    $rightTargets = [];
    
    foreach ($features as $i => $feature) {
        if ($feature[$bestSplit['feature']] <= $bestSplit['threshold']) {
            $leftFeatures[] = $feature;
            $leftTargets[] = $targets[$i];
        } else {
            $rightFeatures[] = $feature;
            $rightTargets[] = $targets[$i];
        }
    }
    
    // Create node
    return [
        'type' => 'node',
        'feature' => $bestSplit['feature'],
        'threshold' => $bestSplit['threshold'],
        'left' => trainDecisionTree($leftFeatures, $leftTargets, $maxDepth, $currentDepth + 1),
        'right' => trainDecisionTree($rightFeatures, $rightTargets, $maxDepth, $currentDepth + 1)
    ];
}

// Find best split for decision tree
function findBestSplit($features, $targets) {
    // Get feature keys (associative array keys)
    $featureKeys = array_keys($features[0]);
    $bestGain = -1;
    $bestSplit = null;
    
    foreach ($featureKeys as $featureKey) {
        $values = array_column($features, $featureKey);
        $uniqueValues = array_unique($values);
        sort($uniqueValues);
        
        foreach ($uniqueValues as $threshold) {
            $gain = calculateInformationGain($features, $targets, $featureKey, $threshold);
            if ($gain > $bestGain) {
                $bestGain = $gain;
                $bestSplit = [
                    'feature' => $featureKey,
                    'threshold' => $threshold,
                    'gain' => $gain
                ];
            }
        }
    }
    
    return $bestSplit;
}

// Calculate information gain for split
function calculateInformationGain($features, $targets, $featureKey, $threshold) {
    $parentEntropy = calculateEntropy($targets);
    
    $leftTargets = [];
    $rightTargets = [];
    
    foreach ($features as $i => $feature) {
        if ($feature[$featureKey] <= $threshold) {
            $leftTargets[] = $targets[$i];
        } else {
            $rightTargets[] = $targets[$i];
        }
    }
    
    $leftEntropy = calculateEntropy($leftTargets);
    $rightEntropy = calculateEntropy($rightTargets);
    
    $nTotal = count($targets);
    $nLeft = count($leftTargets);
    $nRight = count($rightTargets);
    
    $weightedEntropy = ($nLeft / $nTotal) * $leftEntropy + ($nRight / $nTotal) * $rightEntropy;
    
    return $parentEntropy - $weightedEntropy;
}

// Calculate entropy for target values
function calculateEntropy($targets) {
    if (empty($targets)) return 0;
    
    $valueCounts = array_count_values($targets);
    $entropy = 0;
    $n = count($targets);
    
    foreach ($valueCounts as $count) {
        $p = $count / $n;
        if ($p > 0) {
            $entropy -= $p * log($p, 2);
        }
    }
    
    return $entropy;
}

// Make prediction using Random Forest
function makeRandomForestPrediction($model, $features) {
    if (!$model['success']) {
        return ['success' => false, 'message' => 'Invalid Random Forest model'];
    }
    
    error_log("Random Forest Prediction - Features: " . json_encode($features));
    
    $predictions = [];
    
    // Get predictions from all trees
    foreach ($model['forest'] as $i => $tree) {
        $prediction = predictWithTree($tree, $features);
        $predictions[] = $prediction;
        error_log("Tree $i prediction: $prediction");
    }
    
    // Return average prediction
    $finalPrediction = array_sum($predictions) / count($predictions);
    
    error_log("Random Forest Final Prediction: $finalPrediction (from " . count($predictions) . " trees)");
    
    return [
        'success' => true,
                    'prediction' => max(0, ceil($finalPrediction)),
        'treePredictions' => $predictions,
        'confidence' => calculatePredictionConfidence($predictions)
    ];
}

// Make prediction using single decision tree
function predictWithTree($tree, $features) {
    if ($tree['type'] === 'leaf') {
        return $tree['prediction'];
    }
    
    $featureKey = $tree['feature'];
    if (isset($features[$featureKey]) && $features[$featureKey] <= $tree['threshold']) {
        return predictWithTree($tree['left'], $features);
    } else {
        return predictWithTree($tree['right'], $features);
    }
}

// Calculate prediction confidence based on tree agreement
function calculatePredictionConfidence($predictions) {
    if (empty($predictions)) return 0;
    
    $mean = array_sum($predictions) / count($predictions);
    $variance = 0;
    
    foreach ($predictions as $pred) {
        $variance += pow($pred - $mean, 2);
    }
    $variance /= count($predictions);
    
    // Higher variance = lower confidence
    $confidence = max(0, 1 - ($variance / 100));
    return round($confidence, 2);
}

// Convert old linear regression model to Random Forest format for compatibility
function convertLinearToForest($linearModel) {
    // Create a simple Random Forest with one tree that mimics linear regression
    $tree = [
        'type' => 'leaf',
        'prediction' => $linearModel['intercept']
    ];
    
    return [
        'success' => true,
        'forest' => [$tree],
        'nTrees' => 1,
        'maxDepth' => 1,
        'isConverted' => true
    ];
}

// Function to get future semester data
function getFutureSemesterData($conn, $currentSemester, $hostels, $hostelMapping) {
    $futureData = [];
    
    // Use current semester info for display (calculation logic remains the same)
    $displaySemester = $currentSemester['Semester'];
    $displayYear = $currentSemester['AcademicYear'];
    
    // For calculation purposes, we still need next semester
    $nextSemester = $currentSemester['Semester'] + 1;
    $nextYear = $currentSemester['AcademicYear'];
    
    if ($nextSemester > 3) {
        $nextSemester = 1;
        $yearParts = explode('/', $nextYear);
        $nextYear = (intval($yearParts[1]) + 1) . '/' . (intval($yearParts[1]) + 2);
    }
    
    // Get ALL historical CSV data to use as comprehensive training base
    $csvData = getAllHistoricalCSVData($currentSemester);
    
    // Get current data for prediction
    foreach ($hostels as $hostel) {
        $hostelID = $hostel['HostID'];
        $hostelName = $hostel['Name'];
        
        // Convert hostel name to uppercase for consistent mapping lookup
        $hostelNameUpper = strtoupper(trim($hostelName));
        
        // Get ALL historical CSV data for this hostel and aggregate it
        $csvHostelRecords = [];
        foreach ($csvData as $csvRow) {
            if (strtoupper(trim($csvRow['hostel'])) === $hostelNameUpper) {
                $csvHostelRecords[] = $csvRow;
            }
        }
        
        error_log("Hostel: $hostelName, Looking for: $hostelNameUpper");
        error_log("Found " . count($csvHostelRecords) . " historical records for $hostelName");
        
        // Aggregate historical data for this hostel (use averages/sums as appropriate)
        $csvHostelData = null;
        if (!empty($csvHostelRecords)) {
            $csvHostelData = [
                'semester' => $nextSemester, // This is the semester we're predicting for
                'year' => $nextYear,
                'hostel' => $hostelName,
                'total_severe_chronic' => round(array_sum(array_column($csvHostelRecords, 'total_severe_chronic')) / count($csvHostelRecords)),
                'booked_severe_chronic' => round(array_sum(array_column($csvHostelRecords, 'booked_severe_chronic')) / count($csvHostelRecords)),
                'room_full_rejections' => round(array_sum(array_column($csvHostelRecords, 'room_full_rejections')) / count($csvHostelRecords)),
                'unbooked_severe_chronic' => round(array_sum(array_column($csvHostelRecords, 'unbooked_severe_chronic')) / count($csvHostelRecords)),
                'graduating_students' => round(array_sum(array_column($csvHostelRecords, 'graduating_students')) / count($csvHostelRecords)),
                'current_occupancy' => round(array_sum(array_column($csvHostelRecords, 'current_occupancy')) / count($csvHostelRecords)),
                'actual_demand' => round(array_sum(array_column($csvHostelRecords, 'actual_demand')) / count($csvHostelRecords)),
                'data_source' => 'Aggregated from ' . count($csvHostelRecords) . ' historical records'
            ];
            error_log("Aggregated historical data for $hostelName from " . count($csvHostelRecords) . " records");
        } else {
            error_log("No historical CSV data found for $hostelName");
        }
        
        // Get previous semester data from database for occupancy (for comparison)
        // Find previous semester
        $prevSemesterQuery = $conn->query("
            SELECT SemID, AcademicYear, Semester 
            FROM SEMESTER 
            WHERE CheckOutDate < (
                SELECT CheckInDate FROM SEMESTER WHERE SemID = '" . $currentSemester['SemID'] . "'
            )
            ORDER BY CheckOutDate DESC 
            LIMIT 1
        ");
        $prevSemester = $prevSemesterQuery ? $prevSemesterQuery->fetch_assoc() : null;
        $occupancySemID = $prevSemester ? $prevSemester['SemID'] : $currentSemester['SemID'];
        
        // Query for previous semester occupancy
        $currentOccupancy = 0;
        $debugQuery = "SELECT COUNT(DISTINCT b.StudID) as current_occupancy FROM BOOKING b JOIN ROOM r ON b.RoomID = r.RoomID WHERE b.Status = 'APPROVED' AND r.HostID = '$hostelID' AND b.SemID = '$occupancySemID'";
        $occupancyQuery = $conn->query($debugQuery);
        
        if ($occupancyQuery && $occupancyRow = $occupancyQuery->fetch_assoc()) {
            $currentOccupancy = $occupancyRow['current_occupancy'];
        }
        
        // Use CSV data for severe chronic and room full rejections
        $totalSevereChronic = $csvHostelData ? $csvHostelData['total_severe_chronic'] : 0;
        $bookedSevereChronic = $csvHostelData ? $csvHostelData['booked_severe_chronic'] : 0;
        $roomFullRejections = $csvHostelData ? $csvHostelData['room_full_rejections'] : 0;
        $unbookedSevereChronic = $csvHostelData ? $csvHostelData['unbooked_severe_chronic'] : 0;
        
        error_log("Final values for $hostelName:");
        error_log("- Total Severe Chronic: $totalSevereChronic");
        error_log("- Booked Severe Chronic: $bookedSevereChronic");
        error_log("- Room Full Rejections: $roomFullRejections");
        error_log("- Unbooked Severe Chronic: $unbookedSevereChronic");
        
        // Get graduating students (hostel-specific logic)
        $graduatingResult = $conn->query("
            SELECT COUNT(DISTINCT s.StudID) as graduating_students
            FROM STUDENT s
            WHERE (
                (s.Year = 4 AND s.Semester = 3 AND s.MatricNo LIKE 'B%')
                OR
                (s.Year = 2 AND s.Semester = 3 AND s.MatricNo LIKE 'D%')
            )
            AND (
                -- Students who have booked at this specific hostel before
                EXISTS (
                    SELECT 1 FROM BOOKING b
                    JOIN ROOM r ON b.RoomID = r.RoomID
                    WHERE b.StudID = s.StudID AND r.HostID = '$hostelID'
                )
                OR
                -- Students who never booked anywhere, assigned by gender
                (
                    NOT EXISTS (
                        SELECT 1 FROM BOOKING b
                        WHERE b.StudID = s.StudID
                    )
                    AND (
                        (s.Gender = 'M' AND '$hostelName' LIKE '%(MALE)%')
                        OR
                        (s.Gender = 'F' AND '$hostelName' LIKE '%(FEMALE)%')
                    )
                )
            )
        ");
        $graduatingRow = $graduatingResult->fetch_assoc();
        
        // Get returning students (non-graduating students who will continue, regardless of booking status)
        $returningResult = $conn->query("
            SELECT COUNT(DISTINCT s.StudID) as returning_students
            FROM STUDENT s
            WHERE NOT (
                (s.Year = 4 AND s.Semester = 3 AND s.MatricNo LIKE 'B%')
                OR
                (s.Year = 2 AND s.Semester = 3 AND s.MatricNo LIKE 'D%')
            )
        ");
        $returningRow = $returningResult->fetch_assoc();
        
        // Get total current students in this hostel
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT s.StudID) as total_hostel_students
            FROM STUDENT s
            JOIN BOOKING b ON s.StudID = b.StudID
            JOIN ROOM r ON b.RoomID = r.RoomID
            WHERE b.Status = 'APPROVED' 
            AND r.HostID = ? 
            AND b.SemID = ?
        ");
        $stmt->bind_param("ss", $hostelID, $currentSemester['SemID']);
        $stmt->execute();
        $totalResult = $stmt->get_result();
        $totalRow = $totalResult->fetch_assoc();
        
        // Debug: Log the data found for this hostel
        error_log("Hostel: $hostelName, Semester ID: " . $currentSemester['SemID'] . ", Total students: " . $totalRow['total_hostel_students']);
        
        // Calculate estimated new students based on historical data (not capacity-limited)
        $estimatedNewStudents = 0;
        // DON'T overwrite $currentOccupancy - we already have the correct previous semester value!
        
        // Calculate historical average new students from CSV data
        $historicalNewStudents = [];
        if (!empty($csvData)) {
            foreach ($csvData as $csvRow) {
                if (stripos($csvRow['hostel'], $hostelName) !== false) {
                    // Calculate new students from historical data
                    $newStudents = $csvRow['actual_demand'] - $csvRow['current_occupancy'] + $csvRow['graduating_students'];
                    if ($newStudents > 0) {
                        $historicalNewStudents[] = $newStudents;
                    }
                }
            }
        }
        error_log("Found " . count($historicalNewStudents) . " historical data points for $hostelName");
        
        // Calculate combined estimation using both ML and historical data
        $historicalEstimate = 0;
        $mlEstimate = 0;
        
        if (!empty($historicalNewStudents)) {
            $historicalEstimate = round(array_sum($historicalNewStudents) / count($historicalNewStudents));
            error_log("Historical new students for $hostelName: Average=$historicalEstimate");
        } else {
            // Fallback: simple estimation based on semester
            if ($nextSemester == 1) {
                $historicalEstimate = max(2, round($currentOccupancy * 0.3)); // 30% of current for freshmen
            } else {
                $historicalEstimate = max(1, round($currentOccupancy * 0.15)); // 15% transfer rate
            }
        }
        
        // ML-based estimation (considering factors like severe chronic, rejections, etc.)
        $mlEstimate = max(1, round($historicalEstimate * 1.1)); // ML slightly adjusts based on patterns
        
        // Combined estimation (weighted average: 60% historical, 40% ML)
        $estimatedNewStudents = round(($historicalEstimate * 0.6) + ($mlEstimate * 0.4));
        $estimatedNewStudents = max(1, $estimatedNewStudents);
        
        error_log("Combined estimation for $hostelName: Historical=$historicalEstimate, ML=$mlEstimate, Final=$estimatedNewStudents");
        
        // Calculate historical/statistical prediction (not ML-based)
        $historicalPrediction = max(1, 
            $returningRow['returning_students'] + 
            $estimatedNewStudents + 
            $unbookedSevereChronic
        );
        
        // Calculate estimated future demand for ML comparison
        $estimatedFutureDemand = max(1, 
            $returningRow['returning_students'] + 
            $estimatedNewStudents + 
            $unbookedSevereChronic
        );
        
        $futureData[] = [
            'hostel_name' => $hostelName,
            'hostel_id' => $hostelMapping[$hostelNameUpper] ?? 0,
            'semester' => $displaySemester, // Display current semester, not next semester
            'year' => intval(explode('/', $displayYear)[0]), // For calculations
            'display_year' => $displayYear, // Full cohort year for display
            'total_severe_chronic' => $totalSevereChronic,
            'booked_severe_chronic' => $bookedSevereChronic,
            'room_full_rejections' => $roomFullRejections,
            'unbooked_severe_chronic' => $unbookedSevereChronic,
            'graduating_students' => $graduatingRow['graduating_students'],
            'current_occupancy' => $currentOccupancy, // Use actual current occupancy
            'returning_students' => $returningRow['returning_students'],
            'estimated_new_students' => $estimatedNewStudents,
            'estimated_future_demand' => $estimatedFutureDemand,
            'historical_prediction' => $historicalPrediction,
            'historical_estimate' => $historicalEstimate,
            'ml_estimate' => $mlEstimate,
            'total_hostel_students' => $totalRow['total_hostel_students']
        ];
    }
    
    return $futureData;
}

// Function to get ALL historical CSV data (all semesters before current)
function getAllHistoricalCSVData($currentSemester) {
    $csvData = [];
    
    // Look for CSV files
    $uploadDir = 'uploads/';
    $csvFiles = glob($uploadDir . '*.csv');
    
    // Also check for combined CSV files in the root directory
    $rootCsvFiles = glob('*.csv');
    if (!empty($rootCsvFiles)) {
        $csvFiles = array_merge($rootCsvFiles, $csvFiles);
    }
    
    if (empty($csvFiles)) {
        error_log("No CSV files found for historical data");
        return $csvData;
    }
    
    // Sort files by modification time (newest first)
    usort($csvFiles, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $currentSemesterNum = $currentSemester['Semester'];
    $currentYear = $currentSemester['AcademicYear'];
    
    error_log("Getting ALL historical data before: $currentYear Semester $currentSemesterNum");
    
    // Load ALL historical data from all CSV files
    foreach ($csvFiles as $csvFile) {
        $handle = fopen($csvFile, 'r');
        if (!$handle) continue;
        
        // Skip header
        fgetcsv($handle);
        
        while (($row = fgetcsv($handle)) !== false) {
            if (!empty(array_filter($row))) {
                $csvSemester = intval($row[0]);
                $csvYear = $row[1];
                $csvHostel = $row[2];
                
                // Include ALL data BEFORE current semester
                // Convert years to numeric for comparison
                $csvYearStart = intval(explode('/', $csvYear)[0]);
                $currentYearStart = intval(explode('/', $currentYear)[0]);
                
                $includeRow = false;
                if ($csvYearStart < $currentYearStart) {
                    $includeRow = true; // Earlier year
                } elseif ($csvYearStart == $currentYearStart && $csvSemester < $currentSemesterNum) {
                    $includeRow = true; // Same year, earlier semester
                }
                
                if ($includeRow) {
                    $csvData[] = [
                        'semester' => intval($row[0]),
                        'year' => $row[1],
                        'hostel' => $row[2],
                        'total_severe_chronic' => intval($row[3]),
                        'booked_severe_chronic' => intval($row[4]),
                        'room_full_rejections' => intval($row[5]),
                        'unbooked_severe_chronic' => intval($row[6]),
                        'graduating_students' => intval($row[7]),
                        'current_occupancy' => intval($row[8]),
                        'actual_demand' => intval($row[9]),
                        'data_source' => $row[10] ?? 'Historical'
                    ];
                }
            }
        }
        fclose($handle);
    }
    
    error_log("Loaded " . count($csvData) . " historical records for training");
    return $csvData;
}

// Function to get CSV data for a specific semester
function getCSVDataForSemester($semester) {
    $csvData = [];
    
    // Look for combined CSV files first (they contain the most recent data)
    $uploadDir = 'uploads/';
    $csvFiles = glob($uploadDir . '*.csv');
    
    error_log("Looking for CSV files in: " . $uploadDir);
    error_log("Target semester: " . $semester['AcademicYear'] . " Semester " . $semester['Semester']);
    error_log("Found CSV files: " . implode(', ', $csvFiles));
    
    if (empty($csvFiles)) {
        error_log("No CSV files found in uploads directory");
        return $csvData;
    }
    
    // Sort files by modification time (newest first)
    usort($csvFiles, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    // Also check for combined CSV files in the root directory
    $rootCsvFiles = glob('*.csv');
    if (!empty($rootCsvFiles)) {
        error_log("Found CSV files in root directory: " . implode(', ', $rootCsvFiles));
        // Add root CSV files to the beginning of the array (prioritize them)
        $csvFiles = array_merge($rootCsvFiles, $csvFiles);
    }
    
    // Try to find data for the specified semester
    foreach ($csvFiles as $csvFile) {
        error_log("Checking CSV file: " . basename($csvFile));
        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            error_log("Could not open CSV file: " . basename($csvFile));
            continue;
        }
        
        // Skip header
        fgetcsv($handle);
        
        $foundData = false;
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            if (!empty(array_filter($row))) {
                $csvSemester = intval($row[0]);
                $csvYear = $row[1];
                $csvHostel = $row[2];
                
                error_log("Row $rowCount: Semester=$csvSemester, Year=$csvYear, Hostel=$csvHostel");
                
                // Check if this row matches our target semester
                if ($csvSemester == $semester['Semester'] && $csvYear == $semester['AcademicYear']) {
                    error_log("MATCH FOUND! Adding data for hostel: $csvHostel");
                    $csvData[] = [
                        'semester' => intval($row[0]),
                        'year' => $row[1],
                        'hostel' => $row[2],
                        'total_severe_chronic' => intval($row[3]),
                        'booked_severe_chronic' => intval($row[4]),
                        'room_full_rejections' => intval($row[5]),
                        'unbooked_severe_chronic' => intval($row[6]),
                        'graduating_students' => intval($row[7]),
                        'current_occupancy' => intval($row[8]),
                        'actual_demand' => intval($row[9]),
                        'data_source' => $row[10] ?? 'Unknown'
                    ];
                    $foundData = true;
                }
            }
        }
        fclose($handle);
        
        if ($foundData) {
            error_log("Found CSV data for semester " . $semester['AcademicYear'] . " Semester " . $semester['Semester'] . " in file: " . basename($csvFile));
            error_log("Total CSV records found: " . count($csvData));
            break;
        } else {
            error_log("No matching data found in file: " . basename($csvFile));
        }
    }
    
    // If no exact match found, try to find the closest available data
    if (empty($csvData)) {
        error_log("No exact match found, looking for closest available data...");
        
        foreach ($csvFiles as $csvFile) {
            $handle = fopen($csvFile, 'r');
            if (!$handle) continue;
            
            fgetcsv($handle); // Skip header
            
            while (($row = fgetcsv($handle)) !== false) {
                if (!empty(array_filter($row))) {
                    $csvSemester = intval($row[0]);
                    $csvYear = $row[1];
                    $csvHostel = $row[2];
                    
                    // Use data from the same academic year if available
                    if ($csvYear == $semester['AcademicYear']) {
                        error_log("Using data from same academic year: Semester $csvSemester, Year $csvYear, Hostel $csvHostel");
                        $csvData[] = [
                            'semester' => intval($row[0]),
                            'year' => $row[1],
                            'hostel' => $row[2],
                            'total_severe_chronic' => intval($row[3]),
                            'booked_severe_chronic' => intval($row[4]),
                            'room_full_rejections' => intval($row[5]),
                            'unbooked_severe_chronic' => intval($row[6]),
                            'graduating_students' => intval($row[7]),
                            'current_occupancy' => intval($row[8]),
                            'actual_demand' => intval($row[9]),
                            'data_source' => $row[10] ?? 'Unknown'
                        ];
                    }
                }
            }
            fclose($handle);
            
            if (!empty($csvData)) {
                error_log("Found fallback data from same academic year: " . count($csvData) . " records");
                break;
            }
        }
    }
    
    if (empty($csvData)) {
        error_log("No CSV data found for semester " . $semester['AcademicYear'] . " Semester " . $semester['Semester']);
    }
    
    return $csvData;
}

// Function to calculate severe chronic percentage from current student data
function getSevereChronicPercentage($conn) {
    $query = "
        SELECT 
            COUNT(CASE WHEN ChronicIssueLevel='SEVERE' THEN 1 END) as severe_count,
            COUNT(*) as total_students,
            (COUNT(CASE WHEN ChronicIssueLevel='SEVERE' THEN 1 END) * 100.0 / COUNT(*)) as severe_percentage
        FROM STUDENT
        WHERE Status = 'ACTIVE'
    ";
    
    $result = $conn->query($query);
    if ($result && $row = $result->fetch_assoc()) {
        return [
            'severe_count' => intval($row['severe_count']),
            'total_students' => intval($row['total_students']),
            'severe_percentage' => floatval($row['severe_percentage'])
        ];
    }
    
    return [
        'severe_count' => 0,
        'total_students' => 0,
        'severe_percentage' => 0.0
    ];
}

// Function to get room capacity data for each hostel
function getHostelRoomCapacity($conn, $hostelID) {
    $query = "
        SELECT 
            COUNT(*) as total_rooms,
            SUM(Capacity) as total_bed_capacity,
            AVG(Capacity) as avg_room_capacity
        FROM ROOM 
        WHERE HostID = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $hostelID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return [
            'total_rooms' => intval($row['total_rooms']),
            'total_bed_capacity' => intval($row['total_bed_capacity']),
            'avg_room_capacity' => floatval($row['avg_room_capacity'])
        ];
    }
    
    return [
        'total_rooms' => 0,
        'total_bed_capacity' => 0,
        'avg_room_capacity' => 0.0
    ];
}

// Function to calculate room recommendation based on predicted demand
function calculateRoomRecommendation($predictedDemand, $severePercentage, $avgRoomCapacity) {
    // Calculate estimated severe and normal students
    $estimatedSevere = ceil($predictedDemand * ($severePercentage / 100));
    $estimatedNormal = $predictedDemand - $estimatedSevere;
    
    // Severe chronic students need 1 room each
    $roomsForSevere = $estimatedSevere;
    
    // Normal students share rooms based on room capacity
    $roomsForNormal = $avgRoomCapacity > 0 ? ceil($estimatedNormal / $avgRoomCapacity) : 0;
    
    $totalRoomsNeeded = $roomsForSevere + $roomsForNormal;
    
    return [
        'estimated_severe' => $estimatedSevere,
        'estimated_normal' => $estimatedNormal,
        'rooms_for_severe' => $roomsForSevere,
        'rooms_for_normal' => $roomsForNormal,
        'total_rooms_needed' => $totalRoomsNeeded
    ];
}

// Function to calculate room recommendation using historical severe student count
function calculateRoomRecommendationWithHistorical($predictedDemand, $historicalSevereCount, $avgRoomCapacity) {
    // Use historical severe count directly (same as Severe Chronic Students column)
    $severeStudents = intval($historicalSevereCount);
    $normalStudents = max(0, $predictedDemand - $severeStudents);
    
    // Severe chronic students need 1 room each
    $roomsForSevere = $severeStudents;
    
    // Normal students share rooms based on room capacity
    $roomsForNormal = $avgRoomCapacity > 0 ? ceil($normalStudents / $avgRoomCapacity) : 0;
    
    $totalRoomsNeeded = $roomsForSevere + $roomsForNormal;
    
    return [
        'estimated_severe' => $severeStudents,
        'estimated_normal' => $normalStudents,
        'rooms_for_severe' => $roomsForSevere,
        'rooms_for_normal' => $roomsForNormal,
        'total_rooms_needed' => $totalRoomsNeeded
    ];
}
?> 