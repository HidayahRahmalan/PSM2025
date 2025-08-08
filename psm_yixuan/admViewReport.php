<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: staffMainPage.php");
    exit();
}

// Get active tab
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'occupancy';

// Get selected semester (try to default to the most recent one)
$selectedSemester = isset($_GET['semester']) ? $_GET['semester'] : 'latest';

// Find the latest semester index if 'latest' is selected
if ($selectedSemester === 'latest' && !empty($semesters)) {
    // Sort semesters by CheckInDate in descending order to get the most recent first
    $latestSemesterIndex = 0; // Default to first semester
    $latestDate = null;
    
    foreach ($semesters as $index => $semester) {
        $checkInDate = strtotime($semester['CheckInDate']);
        if ($latestDate === null || $checkInDate > $latestDate) {
            $latestDate = $checkInDate;
            $latestSemesterIndex = $index;
        }
    }
    
    $selectedSemester = $latestSemesterIndex;
}

// Get all hostels
$hostels = [];
try {
    // Debug the SQL query
    $hostelQuery = "SELECT HostID, Name FROM HOSTEL WHERE Status = 'ACTIVE'";
    echo "<!-- Executing hostel query: " . htmlspecialchars($hostelQuery) . " -->\n";
    
    $stmt = $conn->prepare($hostelQuery);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Debug the result
    echo "<!-- Hostel query result num_rows: " . $result->num_rows . " -->\n";
    
    while ($row = $result->fetch_assoc()) {
        $hostels[] = $row;
        echo "<!-- Found hostel: " . htmlspecialchars($row['Name']) . " (ID: " . $row['HostID'] . ") -->\n";
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting hostels: " . $e->getMessage());
    echo "<!-- Error getting hostels: " . htmlspecialchars($e->getMessage()) . " -->\n";
}

// Find current semester based on CheckInDate and CheckOutDate
$currentDate = date('Y-m-d');
$currentSemester = null;

$currentSemQuery = $conn->query("
    SELECT SemID, AcademicYear, Semester 
    FROM SEMESTER 
    WHERE '$currentDate' BETWEEN CheckInDate AND CheckOutDate 
    LIMIT 1
");

if ($currentSemRow = $currentSemQuery->fetch_assoc()) {
    $currentSemester = [
        'SemID' => $currentSemRow['SemID'],
        'AcademicYear' => $currentSemRow['AcademicYear'],
        'Semester' => $currentSemRow['Semester']
    ];
    echo "<!-- Current semester found: " . $currentSemRow['AcademicYear'] . " Semester " . $currentSemRow['Semester'] . " -->\n";
} else {
    echo "<!-- No current semester found for date: $currentDate -->\n";
}

// Get all semesters up to current semester
$semesters = [];
try {
    // Debug the SQL query
    $semesterFilter = "";
    if ($currentSemester) {
        // Only get semesters up to and including the current semester
        $semesterFilter = "WHERE (AcademicYear < '" . $currentSemester['AcademicYear'] . "' 
                        OR (AcademicYear = '" . $currentSemester['AcademicYear'] . "' AND Semester <= " . $currentSemester['Semester'] . "))";
    }
    
    $semesterQuery = "SELECT SemID, CONCAT(AcademicYear, ' Semester ', Semester) AS Name, 
                            CheckInDate, CheckOutDate 
                     FROM SEMESTER 
                     $semesterFilter
                     ORDER BY CheckInDate ASC";
    echo "<!-- Executing semester query: " . htmlspecialchars($semesterQuery) . " -->\n";
    
    $stmt = $conn->prepare($semesterQuery);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Debug the result
    echo "<!-- Semester query result num_rows: " . $result->num_rows . " -->\n";
    
    while ($row = $result->fetch_assoc()) {
        $semesters[] = $row;
        echo "<!-- Found semester: " . htmlspecialchars($row['Name']) . 
             " (ID: " . $row['SemID'] . 
             ", Check-in: " . $row['CheckInDate'] . 
             ", Check-out: " . $row['CheckOutDate'] . ") -->\n";
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting semesters: " . $e->getMessage());
    echo "<!-- Error getting semesters: " . htmlspecialchars($e->getMessage()) . " -->\n";
}

// Check if there's any data in the BOOKING table
try {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM BOOKING");
    $stmt->execute();
    $bookingCount = $stmt->get_result()->fetch_assoc()['count'];
    echo "<!-- Total bookings in database: " . $bookingCount . " -->\n";
    
    // Check if there are any approved bookings
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM BOOKING WHERE Status = 'APPROVED'");
    $stmt->execute();
    $approvedCount = $stmt->get_result()->fetch_assoc()['count'];
    echo "<!-- Approved bookings in database: " . $approvedCount . " -->\n";
    
    // Check if there are any rejected bookings
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM BOOKING WHERE Status = 'REJECTED'");
    $stmt->execute();
    $rejectedCount = $stmt->get_result()->fetch_assoc()['count'];
    echo "<!-- Rejected bookings in database: " . $rejectedCount . " -->\n";
    
    // Check the RejectedReason values
    $stmt = $conn->prepare("SELECT DISTINCT RejectedReason FROM BOOKING WHERE Status = 'REJECTED'");
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<!-- Rejected reasons in database: ";
    while ($row = $result->fetch_assoc()) {
        echo $row['RejectedReason'] . ", ";
    }
    echo " -->\n";
    
    // Check if there are any severe chronic students
    echo "<!-- SEVERE CHRONIC STUDENTS DEBUG -->\n";
    
    // List all severe chronic students
    $stmt = $conn->prepare("SELECT StudID, FullName, ChronicIssueLevel FROM STUDENT WHERE ChronicIssueLevel = 'SEVERE'");
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<!-- Severe chronic students in database: " . $result->num_rows . " -->\n";
    while ($row = $result->fetch_assoc()) {
        echo "<!-- Severe student: " . htmlspecialchars($row['StudID']) . " - " . 
             htmlspecialchars($row['FullName']) . " - Level: " . 
             htmlspecialchars($row['ChronicIssueLevel']) . " -->\n";
    }
    
    // Check if the column name is correct
    $stmt = $conn->prepare("SHOW COLUMNS FROM STUDENT LIKE 'ChronicIssueLevel'");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        echo "<!-- Column ChronicIssueLevel exists -->\n";
    } else {
        echo "<!-- WARNING: Column ChronicIssueLevel does not exist! -->\n";
        
        // Check all columns
        $stmt = $conn->prepare("SHOW COLUMNS FROM STUDENT");
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<!-- STUDENT table columns: ";
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . ", ";
        }
        echo " -->\n";
    }
    
    // Test a simple query
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM STUDENT WHERE ChronicIssueLevel = 'SEVERE'");
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    echo "<!-- Simple count of severe chronic students: " . $count . " -->\n";
    
    // Test a query with NOT EXISTS
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count
        FROM STUDENT s
        WHERE s.ChronicIssueLevel = 'SEVERE'
        AND NOT EXISTS (
            SELECT 1 FROM BOOKING b 
            WHERE b.StudID = s.StudID
        )
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    echo "<!-- Severe chronic students without booking in latest semester: " . $count . " -->\n";
} catch (Exception $e) {
    error_log("Error checking severe chronic students: " . $e->getMessage());
    echo "<!-- Error checking severe chronic students: " . htmlspecialchars($e->getMessage()) . " -->\n";
}

// Debug check before proceeding with calculations
echo "<!-- DEBUG SUMMARY -->\n";
echo "<!-- Total hostels found: " . count($hostels) . " -->\n";
echo "<!-- Total semesters found: " . count($semesters) . " -->\n";

if (empty($hostels)) {
    echo "<!-- WARNING: No active hostels found -->\n";
}
if (empty($semesters)) {
    echo "<!-- WARNING: No semesters found -->\n";
}

// Get occupancy data
$occupancyData = [];
if (!empty($hostels) && !empty($semesters)) {
    try {
        foreach ($hostels as $hostel) {
            echo "<!-- Processing data for hostel: " . htmlspecialchars($hostel['Name']) . " -->\n";
            
            // Debug hostel capacity
            echo "<!-- Hostel capacity: " . ($hostelCapacities[$hostel['HostID']] ?? 'Not set') . " -->\n";
            
            $hostelData = ['name' => $hostel['Name'], 'data' => []];
            foreach ($semesters as $semester) {
                // Get approved bookings
                $stmt = $conn->prepare("
                    SELECT COUNT(DISTINCT b.StudID) as OccupantCount
                    FROM BOOKING b
                    JOIN ROOM r ON b.RoomID = r.RoomID
                    WHERE r.HostID = ? AND b.SemID = ? AND b.Status = 'APPROVED'
                ");
                $stmt->bind_param("ss", $hostel['HostID'], $semester['SemID']);
                $stmt->execute();
                $result = $stmt->get_result();
                $approvedCount = $result->fetch_assoc()['OccupantCount'];
                
                // Debug booking counts
                echo "<!-- Semester " . htmlspecialchars($semester['Name']) . 
                     " - Approved bookings: " . $approvedCount . " -->\n";
                
                $hostelData['data'][] = $approvedCount;
            }
            $occupancyData[] = $hostelData;
        }
    } catch (Exception $e) {
        error_log("Error getting occupancy data: " . $e->getMessage());
        echo "<!-- Error getting occupancy data: " . htmlspecialchars($e->getMessage()) . " -->\n";
    }
}

// Get faculty distribution data
$facultyData = [];
if (!empty($hostels) && !empty($semesters)) {
    try {
        foreach ($hostels as $hostel) {
            $hostelFaculty = ['name' => $hostel['Name'], 'data' => [], 'semesterNames' => []];
            foreach ($semesters as $semester) {
                $stmt = $conn->prepare("
                    SELECT s.Faculty, COUNT(DISTINCT b.StudID) as Count
                    FROM BOOKING b
                    JOIN ROOM r ON b.RoomID = r.RoomID
                    JOIN STUDENT s ON b.StudID = s.StudID
                    WHERE r.HostID = ? AND b.SemID = ? AND b.Status = 'APPROVED'
                    GROUP BY s.Faculty
                ");
                $stmt->bind_param("ss", $hostel['HostID'], $semester['SemID']);
                $stmt->execute();
                $result = $stmt->get_result();
                $facultyDistribution = [];
                while ($row = $result->fetch_assoc()) {
                    $facultyDistribution[$row['Faculty']] = $row['Count'];
                }
                $hostelFaculty['data'][] = $facultyDistribution;
                $hostelFaculty['semesterNames'][] = $semester['Name'];
                
                // Debug output
                echo "<!-- Hostel: " . htmlspecialchars($hostel['Name']) . 
                     ", Semester: " . htmlspecialchars($semester['Name']) . 
                     ", Faculty Distribution: " . json_encode($facultyDistribution) . " -->";
            }
            $facultyData[] = $hostelFaculty;
        }
    } catch (Exception $e) {
        error_log("Error getting faculty data: " . $e->getMessage());
        echo "<!-- Error getting faculty data: " . htmlspecialchars($e->getMessage()) . " -->";
    }
}

// Get total capacity for each hostel
$hostelCapacities = [];
try {
    // First, get all active rooms and their capacities
    $stmt = $conn->prepare("
        SELECT 
            h.HostID,
            h.Name as HostelName,
            r.RoomID,
            r.RoomNo,
            r.Capacity
        FROM HOSTEL h
        LEFT JOIN ROOM r ON h.HostID = r.HostID 
        WHERE h.Status = 'ACTIVE' 
        AND (r.Status = 'ACTIVE' OR r.Status IS NULL)
        ORDER BY h.HostID, r.RoomID
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process each room and sum up capacities per hostel
    $currentHostel = null;
    $currentTotal = 0;
    
    while ($row = $result->fetch_assoc()) {
        // If this is a new hostel
        if ($currentHostel !== $row['HostID']) {
            // Save the previous hostel's total (if any)
            if ($currentHostel !== null) {
                $hostelCapacities[$currentHostel] = $currentTotal;
            }
            // Start new hostel
            $currentHostel = $row['HostID'];
            $currentTotal = 0;
        }
        
        // Add this room's capacity
        if (!is_null($row['Capacity'])) {
            $currentTotal += intval($row['Capacity']);
            // Debug output for each room
            echo "<!-- Room " . $row['RoomNo'] . " in " . $row['HostelName'] . ": " . $row['Capacity'] . " beds -->";
        }
    }
    
    // Don't forget to save the last hostel's total
    if ($currentHostel !== null) {
        $hostelCapacities[$currentHostel] = $currentTotal;
    }
    
    // Debug output for final totals
    foreach ($hostelCapacities as $hostId => $total) {
        $stmt = $conn->prepare("SELECT Name FROM HOSTEL WHERE HostID = ?");
        $stmt->bind_param("s", $hostId);
        $stmt->execute();
        $hostelName = $stmt->get_result()->fetch_assoc()['Name'];
        echo "<!-- Final total for " . $hostelName . " (ID: " . $hostId . "): " . $total . " beds -->";
    }
    
} catch (Exception $e) {
    error_log("Error getting hostel capacities: " . $e->getMessage());
    echo "<!-- Error getting hostel capacities: " . htmlspecialchars($e->getMessage()) . " -->";
}

// Verify the capacity data is correct
echo "<!-- Final Hostel Capacities: " . json_encode($hostelCapacities) . " -->";

// Get historical growth rate for estimation
$growthRates = [];
$estimationData = [];

// Find the latest semester with data for the estimation report
$latestSemesterWithData = null;

// Iterate through semesters in reverse order (latest first)
$reversedSemesters = array_reverse($semesters);
foreach ($reversedSemesters as $semester) {
    $hasData = false;
    
    // Check if any hostel has data for this semester
        foreach ($hostels as $hostel) {
                $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT b.StudID) as approved_count
                    FROM BOOKING b
                    JOIN ROOM r ON b.RoomID = r.RoomID
                    WHERE r.HostID = ? AND b.SemID = ? AND b.Status = 'APPROVED'
                ");
                $stmt->bind_param("ss", $hostel['HostID'], $semester['SemID']);
                $stmt->execute();
                $result = $stmt->get_result();
        $approvedCount = $result->fetch_assoc()['approved_count'];
        
        if ($approvedCount > 0) {
            $hasData = true;
            break;
        }
    }
    
    if ($hasData) {
        $latestSemesterWithData = $semester;
        echo "<!-- Found latest semester with data: " . htmlspecialchars($semester['Name']) . " -->\n";
        break; // Found the latest semester with data
    }
}

// If no semester with data was found, use the latest semester
if (!$latestSemesterWithData && !empty($semesters)) {
    $latestSemesterWithData = $semesters[0];
    echo "<!-- No semester with data found, using latest semester: " . htmlspecialchars($latestSemesterWithData['Name']) . " -->\n";
}

if ($latestSemesterWithData) {
    try {
        echo "<!-- Using semester for estimation: " . htmlspecialchars($latestSemesterWithData['Name']) . " -->\n";
        
        foreach ($hostels as $hostel) {
            echo "<!-- Processing estimation for hostel: " . htmlspecialchars($hostel['Name']) . " -->\n";
            
            // For current semester: only count approved bookings (distinct students)
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT b.StudID) as approved_count
                FROM BOOKING b
                JOIN ROOM r ON b.RoomID = r.RoomID
                WHERE r.HostID = ? AND b.SemID = ? AND b.Status = 'APPROVED'
            ");
            $stmt->bind_param("ss", $hostel['HostID'], $latestSemesterWithData['SemID']);
            $stmt->execute();
            $result = $stmt->get_result();
            $approvedCount = $result->fetch_assoc()['approved_count'];
            
            // Current semester demand is just the approved bookings
            $currentDemand = $approvedCount;
            
            echo "<!-- Current semester " . htmlspecialchars($latestSemesterWithData['Name']) . 
                 " - Approved bookings: " . $approvedCount . 
                 " - Current demand: " . $currentDemand . " -->\n";
            
            // For next semester: include current approved + rejected due to ROOM FULL + severe chronic students
            
            // 1. Get students rejected due to ROOM FULL for this specific hostel
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT b.StudID) as rejected_count
                FROM BOOKING b
                JOIN ROOM r ON b.RoomID = r.RoomID
                WHERE r.HostID = ? AND b.SemID = ? 
                AND b.Status = 'REJECTED' AND b.RejectedReason = 'ROOM FULL'
                AND b.StudID NOT IN (
                    SELECT b2.StudID FROM BOOKING b2
                    WHERE b2.SemID = ? AND b2.Status = 'APPROVED'
                )
            ");
            $stmt->bind_param("sss", $hostel['HostID'], $latestSemesterWithData['SemID'], $latestSemesterWithData['SemID']);
            $stmt->execute();
            $result = $stmt->get_result();
            $rejectedCount = $result->fetch_assoc()['rejected_count'];
            
            // 2. Get severe chronic students who have booked for this specific hostel (excluding those with approved bookings)
            $stmt = $conn->prepare("
                SELECT COUNT(DISTINCT s.StudID) as booked_chronic_count
                FROM STUDENT s
                JOIN BOOKING b ON s.StudID = b.StudID
                JOIN ROOM r ON b.RoomID = r.RoomID
                WHERE s.ChronicIssueLevel = 'SEVERE'
                AND r.HostID = ? AND b.SemID = ?
                AND s.StudID NOT IN (
                    SELECT b2.StudID FROM BOOKING b2
                    WHERE b2.SemID = ? AND b2.Status = 'APPROVED'
                )
            ");
            $stmt->bind_param("sss", $hostel['HostID'], $latestSemesterWithData['SemID'], $latestSemesterWithData['SemID']);
            $stmt->execute();
            $result = $stmt->get_result();
            $bookedChronicCount = $result->fetch_assoc()['booked_chronic_count'];
            
            // Next semester demand for this hostel includes:
            // - Current approved for this hostel
            // - Rejected due to ROOM FULL for this hostel
            // - Severe chronic students who booked for this hostel but were not approved
            $nextSemesterDemand = $currentDemand + $rejectedCount + $bookedChronicCount;
                
                // Debug output
            echo "<!-- Next semester projection for " . htmlspecialchars($hostel['Name']) . ":\n" .
                 "   Approved: " . $currentDemand . "\n" .
                 "   Rejected due to ROOM FULL: " . $rejectedCount . "\n" .
                 "   Severe chronic with booking for this hostel: " . $bookedChronicCount . "\n" .
                 "   Total next semester demand: " . $nextSemesterDemand . " -->\n";
            
            // Make sure we have a valid capacity
            $capacity = isset($hostelCapacities[$hostel['HostID']]) ? $hostelCapacities[$hostel['HostID']] : 100;
            if ($capacity <= 0) {
                $capacity = 100; // Default capacity if none is set
            }
            
            echo "<!-- Using capacity for " . htmlspecialchars($hostel['Name']) . ": " . $capacity . " -->\n";
            
            // Store the data for the chart
            $estimationData[] = [
                'name' => $hostel['Name'],
                'current' => $currentDemand,
                'projections' => [$nextSemesterDemand],
                'capacity' => $capacity,
                'semesterName' => $latestSemesterWithData['Name'] // Store the semester name for reference
            ];
            
            echo "<!-- Final estimation data for " . htmlspecialchars($hostel['Name']) . ":\n" .
                 "     Using semester: " . htmlspecialchars($latestSemesterWithData['Name']) . "\n" .
                 "     Current: " . $currentDemand . "\n" .
                 "     Projections: " . implode(", ", [$nextSemesterDemand]) . "\n" .
                 "     Capacity: " . $capacity . " -->\n";
        }
    } catch (Exception $e) {
        error_log("Error calculating estimations: " . $e->getMessage());
        echo "<!-- Error calculating estimations: " . htmlspecialchars($e->getMessage()) . " -->\n";
    }
}

// Final debug check
echo "<!-- FINAL DATA CHECK -->\n";
echo "<!-- Number of hostels with estimation data: " . count($estimationData) . " -->\n";
foreach ($estimationData as $data) {
    echo "<!-- Hostel: " . htmlspecialchars($data['name']) . 
         " - Current: " . $data['current'] . 
         " - Projections: " . implode(", ", $data['projections']) . " -->\n";
}

// Check if there are any severe chronic students that might have been missed
try {
    // Get all severe chronic students who haven't booked for the latest semester with data, grouped by gender
    $stmt = $conn->prepare("
        SELECT s.Gender, COUNT(*) as count
        FROM STUDENT s
        WHERE s.ChronicIssueLevel = 'SEVERE'
        AND NOT EXISTS (
            SELECT 1 FROM BOOKING b 
            WHERE b.StudID = s.StudID AND b.SemID = ?
        )
        GROUP BY s.Gender
    ");
    $latestSemID = $latestSemesterWithData ? $latestSemesterWithData['SemID'] : '';
    $stmt->bind_param("s", $latestSemID);
    $stmt->execute();
    $result = $stmt->get_result();
    $unbookedChronicByGender = [];
    while ($row = $result->fetch_assoc()) {
        $unbookedChronicByGender[$row['Gender']] = $row['count'];
    }
    
    echo "<!-- Severe chronic students without bookings by gender: " . json_encode($unbookedChronicByGender) . " -->\n";
    
    // Get list of these students for debugging
    $stmt = $conn->prepare("
        SELECT s.StudID, s.FullName, s.Gender
        FROM STUDENT s
        WHERE s.ChronicIssueLevel = 'SEVERE'
        AND NOT EXISTS (
            SELECT 1 FROM BOOKING b 
            WHERE b.StudID = s.StudID AND b.SemID = ?
        )
    ");
    $stmt->bind_param("s", $latestSemID);
    $stmt->execute();
    $result = $stmt->get_result();
    echo "<!-- Unbooked severe chronic students for latest semester: -->\n";
    while ($row = $result->fetch_assoc()) {
        echo "<!-- Student: " . htmlspecialchars($row['StudID']) . " - " . 
             htmlspecialchars($row['FullName']) . " - Gender: " . 
             htmlspecialchars($row['Gender']) . " -->\n";
    }
    
    // Add unbooked severe chronic students to the first matching gender hostel's next semester projection
    if (!empty($unbookedChronicByGender) && !empty($estimationData)) {
        // Find first male and female hostels
        $firstMaleHostelIndex = null;
        $firstFemaleHostelIndex = null;
        
        foreach ($estimationData as $index => $hostel) {
            if (strpos($hostel['name'], '(MALE)') !== false && $firstMaleHostelIndex === null) {
                $firstMaleHostelIndex = $index;
            }
            if (strpos($hostel['name'], '(FEMALE)') !== false && $firstFemaleHostelIndex === null) {
                $firstFemaleHostelIndex = $index;
            }
        }
        
        // Add male students to first male hostel
        if (isset($unbookedChronicByGender['M']) && $firstMaleHostelIndex !== null) {
            $maleCount = $unbookedChronicByGender['M'];
            $estimationData[$firstMaleHostelIndex]['projections'][0] += $maleCount;
            echo "<!-- Adding " . $maleCount . " unbooked severe chronic MALE students to " . 
                 htmlspecialchars($estimationData[$firstMaleHostelIndex]['name']) . " -->\n";
        }
        
        // Add female students to first female hostel
        if (isset($unbookedChronicByGender['F']) && $firstFemaleHostelIndex !== null) {
            $femaleCount = $unbookedChronicByGender['F'];
            $estimationData[$firstFemaleHostelIndex]['projections'][0] += $femaleCount;
            echo "<!-- Adding " . $femaleCount . " unbooked severe chronic FEMALE students to " . 
                 htmlspecialchars($estimationData[$firstFemaleHostelIndex]['name']) . " -->\n";
        }
    }
} catch (Exception $e) {
    error_log("Error handling unbooked severe chronic students: " . $e->getMessage());
    echo "<!-- Error handling unbooked severe chronic students: " . htmlspecialchars($e->getMessage()) . " -->\n";
}

// If no estimation data, create some sample data for testing
if (empty($estimationData) && !empty($hostels)) {
    echo "<!-- No estimation data found, creating sample data for testing -->\n";
    
    // Get the selected semester name for consistency
    $sampleSemesterName = $latestSemesterWithData ? $latestSemesterWithData['Name'] : 'Current Semester';
    
    foreach ($hostels as $hostel) {
        // Get the capacity for this hostel
        $capacity = isset($hostelCapacities[$hostel['HostID']]) ? $hostelCapacities[$hostel['HostID']] : 100;
        if ($capacity <= 0) {
            $capacity = 100; // Default capacity if none is set
        }
        
        // Generate a random current demand between 10-50% of capacity
        $currentDemand = rand(10, max(10, min(50, $capacity / 2)));
        
        // Calculate projections with 10% growth
        $projections = [];
        $baseValue = $currentDemand;
        for ($i = 1; $i <= 1; $i++) {
            $projected = $baseValue * 1.1;
            $baseValue = $projected;
            $projected = max(0, min($projected, $capacity));
                $projections[] = round($projected);
            }
            
            $estimationData[] = [
                'name' => $hostel['Name'],
            'current' => $currentDemand,
                'projections' => $projections,
            'capacity' => $capacity,
            'semesterName' => $sampleSemesterName
        ];
        
        echo "<!-- Created sample data for " . htmlspecialchars($hostel['Name']) . 
             " - Current: " . $currentDemand . 
             " - Projections: " . implode(", ", $projections) . 
             " - Capacity: " . $capacity . " -->\n";
    }
}

// Get room type distribution for each hostel
$roomTypeData = [];
try {
    foreach ($hostels as $hostel) {
        $stmt = $conn->prepare("
            SELECT Type, COUNT(*) as Count
            FROM ROOM
            WHERE HostID = ? AND Status = 'ACTIVE'
            GROUP BY Type
        ");
        $stmt->bind_param("s", $hostel['HostID']);
        $stmt->execute();
        $result = $stmt->get_result();
        $typeDistribution = [];
        while ($row = $result->fetch_assoc()) {
            $typeDistribution[$row['Type']] = $row['Count'];
        }
        $roomTypeData[$hostel['HostID']] = $typeDistribution;
    }
} catch (Exception $e) {
    error_log("Error getting room type data: " . $e->getMessage());
}

// Check if there's data for the selected semester
$selectedSemester = isset($_GET['semester']) ? $_GET['semester'] : 'all';
$hasData = false;

if ($selectedSemester === 'all') {
    // Check if there's any data across all semesters
    foreach ($occupancyData as $hostelData) {
        if (!empty($hostelData['data']) && array_sum($hostelData['data']) > 0) {
            $hasData = true;
            break;
        }
    }
} else {
    // Check if there's data for the specific semester
    $semesterIndex = intval($selectedSemester);
    foreach ($occupancyData as $hostelData) {
        if (!empty($hostelData['data'][$semesterIndex]) && $hostelData['data'][$semesterIndex] > 0) {
            $hasData = true;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reports - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/adminNav.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #25408f;
            --secondary-color: #3883ce;
            --accent-color: #2c9dff;
            --text-dark: #333;
            --text-light: #767676;
            --border-color: #ddd;
            --background-light: #f8f9fa;
            --white: #ffffff;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f8ff;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-header h2 {
            color: var(--primary-color);
            font-size: 28px;
            margin: 0;
        }

        /* Tab Styles */
        .report-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 20px;
        }

        .tab-btn {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: bold;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: var(--background-light);
            color: var(--text-dark);
        }

        .tab-btn.active {
            background-color: var(--primary-color);
            color: white;
        }

        .tab-btn:hover:not(.active) {
            background-color: var(--border-color);
        }

        /* Report Container Styles */
        .report-container {
            background-color: var(--white);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        .report-title {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }

        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 30px;
        }

        /* Legend Styles */
        .custom-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            justify-content: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        /* Semester Selector Styles */
        .semester-selector {
            margin-bottom: 20px;
            text-align: center;
        }

        .semester-selector select {
            padding: 8px 16px;
            font-size: 16px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            background-color: var(--white);
            color: var(--text-dark);
            min-width: 200px;
        }

        /* Faculty Charts Layout */
        .faculty-charts-container {
            margin-top: 30px;
        }

        .faculty-charts-row {
            margin-bottom: 40px;
        }

        .semester-title {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 20px;
            font-size: 20px;
        }

        .faculty-charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 0 auto;
        }

        .chart-container-small {
            height: 300px;
            position: relative;
            background-color: var(--white);
            border-radius: 8px;
            padding: 15px;
            box-shadow: var(--shadow);
        }

        .stat-card {
            background-color: var(--white);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        /* Light blue styling for factor cards */
        .factors-card {
            background-color: #e6f3ff; /* Light blue background */
            border-left: 4px solid #3883ce; /* Blue left border */
        }

        .stat-title {
            font-size: 16px;
            font-weight: bold;
            color: var(--text-dark);
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }

        .stat-subtitle {
            font-size: 14px;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .stat-note {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 10px;
            font-style: italic;
        }
        
        /* Estimation Note Styles */
        .estimation-note {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
            border-left: 4px solid var(--accent-color);
            box-shadow: var(--shadow);
        }
        
        .estimation-note h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .estimation-note h4 i {
            color: var(--accent-color);
        }
        
        .note-content {
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        .note-content p {
            margin-bottom: 15px;
        }
        
        .note-content ul {
            margin-left: 20px;
            margin-bottom: 15px;
        }
        
        .note-content li {
            margin-bottom: 8px;
        }
        
        .note-content li strong {
            color: var(--primary-color);
        }
        
        .note-highlight {
            background-color: #e8f4f8;
            padding: 15px;
            border-radius: 6px;
            border-left: 3px solid var(--accent-color);
            margin-top: 15px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .note-highlight i {
            color: #ffc107;
            margin-top: 2px;
        }
        
        .note-highlight strong {
            color: var(--primary-color);
        }
        
        /* Mini-table styles for statistics */
        .mini-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 14px;
        }
        
        .mini-table th, .mini-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .mini-table th {
            background-color: #f0f0f0;
            font-weight: bold;
            color: var(--text-dark);
        }
        
        .mini-table tr:last-child td {
            border-bottom: none;
        }
        
        .mini-table tr:hover {
            background-color: #f9f9f9;
        }

        /* Table Styles */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-family: Arial, sans-serif;
        }

        .report-table th, .report-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .report-table th {
            background-color: #25408f;
            color: white;
            font-weight: bold;
        }

        .report-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .report-table tr:hover {
            background-color: #edf3ff;
        }

        /* Column widths for prediction table - using fixed pixel widths */
        #predictionTable th:nth-child(1), #predictionTable td:nth-child(1) { width: 400px; } /* Hostel */
        #predictionTable th:nth-child(2), #predictionTable td:nth-child(2) { width: 200px; min-width: 200px; } /* Semester - wider for full cohort year */
        #predictionTable th:nth-child(3), #predictionTable td:nth-child(3) { width: 120px; }  /* Final Prediction */
        #predictionTable th:nth-child(4), #predictionTable td:nth-child(4) { width: 120px; }  /* ML Prediction */
        #predictionTable th:nth-child(5), #predictionTable td:nth-child(5) { width: 120px; }  /* Historical Prediction */
        #predictionTable th:nth-child(6), #predictionTable td:nth-child(6) { width: 120px; }  /* Previous Semester */
        #predictionTable th:nth-child(7), #predictionTable td:nth-child(7) { width: 120px; }  /* Severe Chronic */
        #predictionTable th:nth-child(8), #predictionTable td:nth-child(8) { width: 120px; }  /* Room Rejections */
        #predictionTable th:nth-child(9), #predictionTable td:nth-child(9) { width: 120px; }  /* Returning Students */
        #predictionTable th:nth-child(10), #predictionTable td:nth-child(10) { width: 120px; } /* Room Recommendation */
        #predictionTable th:nth-child(11), #predictionTable td:nth-child(11) { width: 120px; } /* Demand Change */

        /* ML Data Import Styles */
        .ml-data-section {
            margin-bottom: 40px;
            padding: 25px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .ml-data-section h4 {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 20px;
        }

        .ml-data-section p {
            margin-bottom: 15px;
            color: var(--text-dark);
        }

        .template-info, .upload-info, .dataset-info {
            background-color: white;
            padding: 20px;
            border-radius: 6px;
            margin-top: 15px;
            border: 1px solid var(--border-color);
        }

        .template-info h5, .upload-info h5, .dataset-info h5 {
            color: var(--primary-color);
            margin-bottom: 10px;
            font-size: 16px;
        }

        .template-info ul, .upload-info ul, .dataset-info ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        .template-info li, .upload-info li, .dataset-info li {
            margin-bottom: 5px;
            color: var(--text-dark);
        }

        .file-upload-container {
            position: relative;
            margin: 20px 0;
        }

        .file-upload-container input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: inline-block;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-weight: bold;
            font-size: 16px;
            border: none;
        }

        .file-upload-label:hover {
            background-color: var(--secondary-color);
        }

        .file-upload-label i {
            margin-right: 8px;
        }

        /* Standard button styles for ML Data Import */
        .btn-primary, .btn-success {
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-weight: bold;
            font-size: 15px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover, .btn-success:hover {
            background-color: var(--secondary-color);
        }

        .btn-primary i, .btn-success i {
            margin-right: 8px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .report-tabs {
                flex-direction: column;
                gap: 10px;
            }

            .tab-btn {
                width: 100%;
            }

            .chart-container {
                height: 300px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .faculty-charts-grid {
                grid-template-columns: 1fr;
            }
            
            .chart-container-small {
                height: 250px;
            }
        }

        .search-btn-group .btn {
            min-width: 150px;
        }
        
        .search-btn-group .btn-gold {
            min-width: 180px;
        }
        
        .btn-gold {
            background-color: #ffc107 !important;
            color: black !important;
            font-size: 15px;
            font-weight: 600;
            padding: 8px 12px;
            border: none !important;
            border-radius: 4px;
            box-shadow: none !important;
        }

        .btn-gold:hover {
            background-color: #e0a800 !important;
            color: black !important;
        }
        
        /* Consistent button sizing for ML Prediction tab */
        .ml-prediction-btn {
            min-width: 200px !important;
            margin-top: 25px;
            margin-bottom: 25px;
        }
        
        .ml-prediction-btn:not(:first-child) {
            margin-left: 10px !important;
        }
        
        /* Button group styling for better organization */
        .button-group {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 15px;
        }
        
        .button-group .ml-prediction-btn {
            margin: 25px 0 !important;
            flex: 1;
            min-width: 200px !important;
        }
        
        /* ML Prediction specific styles */
        .prediction-card {
            background-color: #f8f9fa;
            border-left: 4px solid #28a745;
            margin-bottom: 20px;
        }
        
        .prediction-alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .prediction-alert.info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .prediction-alert.success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .prediction-alert.warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .report-header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .report-header-actions h3 {
            margin: 0;
        }
    </style>
</head>
<body>
    <?php include 'includes/adminNav.php'; ?>

    <div class="container">
        <div class="page-header">
            <h2>Hostel Reports</h2>
            <button type="button" class="btn btn-primary" onclick="testMLGeneration()" style="margin-top: 10px;">
                <i class="fas fa-bug"></i> Test ML Connection
            </button>
        </div>

        <!-- Tab Navigation -->
        <div class="report-tabs">
            <button class="tab-btn <?php echo $activeTab === 'occupancy' ? 'active' : ''; ?>" data-tab="occupancy" onclick="showTab('occupancy')">Room Occupancy Report</button>
            <button class="tab-btn <?php echo $activeTab === 'estimation' ? 'active' : ''; ?>" data-tab="estimation" onclick="showTab('estimation')">Room Estimation Report</button>
            <button class="tab-btn <?php echo $activeTab === 'mlData' ? 'active' : ''; ?>" data-tab="mlData" onclick="showTab('mlData')">ML Data Import</button>
            <button class="tab-btn <?php echo $activeTab === 'mlPrediction' ? 'active' : ''; ?>" data-tab="mlPrediction" onclick="showTab('mlPrediction')">ML Prediction</button>
        </div>

        <!-- Occupancy Report Tab -->
        <div id="occupancyTab" class="report-container" style="display: <?php echo $activeTab === 'occupancy' ? 'block' : 'none'; ?>">
            <div class="report-header-actions">
            <h3 class="report-title">Current Room Occupancy Report</h3>
                <button type="button" class="btn btn-gold" onclick="printOccupancyReport()">Generate Report</button>
            </div>
            
            <!-- Occupancy Chart -->
            <div class="chart-container">
                <?php if (empty($occupancyData) || array_sum(array_map(function($hostel) { 
                    return array_sum($hostel['data']); 
                }, $occupancyData)) === 0): ?>
                    <div class="alert alert-info" style="text-align: center; padding: 20px; margin: 20px 0; background-color: #f8f9fa; border-radius: 8px;">
                        No occupancy data available.
                    </div>
                <?php else: ?>
                    <canvas id="occupancyChart"></canvas>
                <?php endif; ?>
            </div>

            <!-- Semester Selection - Moved above pie charts -->
            <div class="semester-selector">
                <label for="semesterSelect">Select Semester:</label>
                <select id="semesterSelect" onchange="updateFacultyCharts()">
                    <?php foreach ($semesters as $index => $semester): ?>
                        <option value="<?php echo $index; ?>" <?php echo $selectedSemester == $index ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($semester['Name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($hasData): ?>
            <!-- Faculty Distribution Charts Container -->
            <div class="faculty-charts-container">
                <div class="faculty-charts-grid">
                    <?php foreach ($hostels as $index => $hostel): 
                        $distinctStudents = !empty($facultyData[$index]['data'][$selectedSemester]) ? 
                            $facultyData[$index]['data'][$selectedSemester] : [];
                    ?>
                        <div class="chart-container-small">
                            <?php if (empty($distinctStudents)): ?>
                                <div class="no-data-message" style="height: 100%; display: flex; align-items: center; justify-content: center; text-align: center; padding: 20px; background-color: #f8f9fa; border-radius: 8px;">
                                    <div>
                                        <h4 style="color: #25408f; margin-bottom: 10px;"><?php echo htmlspecialchars($hostel['Name']); ?></h4>
                                        <p style="color: #767676; margin: 0;">No faculty distribution data available for this hostel in the selected semester.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <canvas id="facultyChart<?php echo $index; ?>"></canvas>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Occupancy Stats -->
            <h3 class="report-title">Occupancy Statistics</h3>
            <div class="stats-grid">
                <?php foreach ($hostels as $index => $hostel): ?>
                    <?php
                    // Get the correct hostel capacity
                    $hostelCapacity = $hostelCapacities[$hostel['HostID']] ?? 0;
                    
                    // Debug output for verification
                    echo "<!-- Processing stats for hostel: " . $hostel['Name'] . 
                         ", ID: " . $hostel['HostID'] . 
                         ", Capacity: " . $hostelCapacity . " -->";
                    
                    $currentOccupancy = 0;
                    
                    if ($selectedSemester === 'all') {
                        if (!empty($occupancyData[$index]['data'])) {
                            $totalOccupancy = array_sum($occupancyData[$index]['data']);
                            $semesterCount = count($occupancyData[$index]['data']);
                            $currentOccupancy = $semesterCount > 0 ? $totalOccupancy / $semesterCount : 0;
                            
                            // Debug output
                            echo "<!-- All semesters - Total: " . $totalOccupancy . 
                                 ", Count: " . $semesterCount . 
                                 ", Average: " . $currentOccupancy . " -->";
                        }
                    } else {
                        $semesterIndex = intval($selectedSemester);
                        $currentOccupancy = !empty($occupancyData[$index]['data'][$semesterIndex]) ? 
                            $occupancyData[$index]['data'][$semesterIndex] : 0;
                            
                        // Debug output
                        echo "<!-- Single semester " . $semesterIndex . " - Occupancy: " . $currentOccupancy . " -->";
                    }
                    
                    // Calculate occupancy rate with the correct capacity
                    $occupancyRate = $hostelCapacity > 0 ? ($currentOccupancy / $hostelCapacity) * 100 : 0;
                    
                    // Debug output
                    echo "<!-- Final calculation - Occupancy: " . $currentOccupancy . 
                         ", Capacity: " . $hostelCapacity . 
                         ", Rate: " . $occupancyRate . "% -->";
                    ?>
                    <div class="stat-card">
                        <div class="stat-title"><?php echo $hostel['Name']; ?></div>
                        <div class="stat-value"><?php echo number_format($occupancyRate, 1); ?>%</div>
                        <div class="stat-subtitle">Occupancy Rate</div>
                        <div class="stat-subtitle">
                            <?php echo round($currentOccupancy); ?> / <?php echo $hostelCapacity; ?> beds
                            <br>
                            <small>
                                <?php 
                                if ($selectedSemester === 'all') {
                                    echo "(averaged across " . count($semesters) . " semesters)";
                                } else {
                                    echo "(for " . htmlspecialchars($semesters[$selectedSemester]['Name']) . ")";
                                }
                                ?>
                            </small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-info" style="text-align: center; padding: 20px; margin: 20px 0; background-color: #f8f9fa; border-radius: 8px;">
                No data available for the selected semester.
            </div>
            <?php endif; ?>
        </div>

        <!-- Estimation Report Tab -->
        <div id="estimationTab" class="report-container" style="display: <?php echo $activeTab === 'estimation' ? 'block' : 'none'; ?>">
            <div class="report-header-actions">
            <h3 class="report-title">Room Estimation Report</h3>
                <button type="button" class="btn btn-gold" onclick="printEstimationReport()">Generate Report</button>
            </div>
            
            <!-- Estimation Chart -->
            <div class="chart-container">
                <canvas id="estimationChart"></canvas>
            </div>
            
            <!-- Room Type Distribution -->
            <h3 class="report-title">Room Type Distribution</h3>
            <div class="chart-container">
                <canvas id="roomTypeChart"></canvas>
            </div>
            
            <!-- Estimation Details Table -->
            <h3 class="report-title">Detailed Estimations</h3>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Hostel</th>
                        <th>One Semester Before Current Semester</th>
                        <th>Current Semester</th>
                        <th>Total Capacity</th>
                        <th>Current Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($estimationData as $hostel): ?>
                        <?php 
                            $currentRate = $hostel['capacity'] > 0 ? ($hostel['current'] / $hostel['capacity']) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo $hostel['name']; ?></td>
                            <td><?php echo $hostel['current']; ?> <small>(<?php echo htmlspecialchars($hostel['semesterName'] ?? ''); ?>)</small></td>
                            <td><?php echo $hostel['projections'][0] ?? 'N/A'; ?></td>
                            <td><?php echo $hostel['capacity']; ?></td>
                            <td><?php echo number_format($currentRate, 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <!-- Chronic Issues and Rejected Reasons Statistics -->
            <h3 class="report-title">Factors Affecting Estimation</h3>
            
            <?php
            // Get the latest semester with data for statistics
            $latestSemID = $latestSemesterWithData ? $latestSemesterWithData['SemID'] : '';
            $latestSemName = $latestSemesterWithData ? $latestSemesterWithData['Name'] : 'Current Semester';
            
            // Get chronic issue statistics
            $chronicStats = [];
            try {
                $stmt = $conn->prepare("
                    SELECT ChronicIssueLevel, COUNT(*) as count
                    FROM STUDENT
                    WHERE ChronicIssueLevel IS NOT NULL AND ChronicIssueLevel != ''
                    GROUP BY ChronicIssueLevel
                    ORDER BY FIELD(ChronicIssueLevel, 'SEVERE', 'MODERATE', 'MILD')
                ");
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $chronicStats[$row['ChronicIssueLevel']] = $row['count'];
                }
            } catch (Exception $e) {
                error_log("Error getting chronic issue statistics: " . $e->getMessage());
            }
            
            // Get rejected reasons statistics for the latest semester - only ROOM FULL
            $roomFullCount = 0;
            try {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count
                    FROM BOOKING
                    WHERE Status = 'REJECTED' AND SemID = ? AND RejectedReason = 'ROOM FULL'
                ");
                $stmt->bind_param("s", $latestSemID);
                $stmt->execute();
                $result = $stmt->get_result();
                $roomFullCount = $result->fetch_assoc()['count'];
            } catch (Exception $e) {
                error_log("Error getting ROOM FULL rejection count: " . $e->getMessage());
            }
            
            // Get severe chronic students without bookings
            $unbookedSevereCount = 0;
            try {
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count
                    FROM STUDENT s
                    WHERE s.ChronicIssueLevel = 'SEVERE'
                    AND NOT EXISTS (
                        SELECT 1 FROM BOOKING b 
                        WHERE b.StudID = s.StudID AND b.SemID = ?
                    )
                ");
                $stmt->bind_param("s", $latestSemID);
                $stmt->execute();
                $unbookedSevereCount = $stmt->get_result()->fetch_assoc()['count'];
            } catch (Exception $e) {
                error_log("Error getting unbooked severe chronic count: " . $e->getMessage());
            }
            ?>
            
            <div class="stats-grid">
                <!-- Severe Chronic Issue Students (Total) -->
                <div class="stat-card factors-card">
                    <div class="stat-title">Total Severe Chronic Students</div>
                    <div class="stat-value"><?php echo isset($chronicStats['SEVERE']) ? $chronicStats['SEVERE'] : 0; ?></div>
                    <div class="stat-subtitle">students with severe chronic issues</div>
                    <div class="stat-note">All students with severe chronic conditions</div>
                </div>
                
                <!-- Room Full Rejections -->
                <div class="stat-card factors-card">
                    <div class="stat-title">Room Full Rejections</div>
                    <div class="stat-value"><?php echo $roomFullCount; ?></div>
                    <div class="stat-subtitle">for <?php echo htmlspecialchars($latestSemName); ?></div>
                    <div class="stat-note">These students are included in next semester projections</div>
                </div>
                
                <!-- Severe Chronic Without Bookings -->
                <div class="stat-card factors-card">
                    <div class="stat-title">Unbooked Severe Chronic Students</div>
                    <div class="stat-value"><?php echo $unbookedSevereCount; ?></div>
                    <div class="stat-subtitle">without bookings in <?php echo htmlspecialchars($latestSemName); ?></div>
                    <div class="stat-note">These students are added to the first hostel's projection</div>
                </div>
            </div>
            
            <!-- Explanation Note -->
            <div class="estimation-note">
                <h4><i class="fas fa-info-circle"></i> Estimation Methodology</h4>
                <div class="note-content">
                    <p><strong>Why Graduating Students Are Not Included:</strong></p>
                    <ul>
                        <li><strong>Natural Turnover:</strong> Graduating students will vacate their rooms at the end of the current semester, creating available spaces for new students.</li>
                        <li><strong>Accurate Demand Forecasting:</strong> Including graduating students would overestimate demand since they won't be competing for rooms in the next semester.</li>
                        <li><strong>Room Availability:</strong> Their departure automatically increases room availability without requiring additional accommodation planning.</li>
                        <li><strong>Focus on Continuing Students:</strong> The estimation prioritizes students who will actually need accommodation in the upcoming semester.</li>
                    </ul>
                    <p class="note-highlight">
                        <i class="fas fa-lightbulb"></i> 
                        <strong>Result:</strong> This approach provides more accurate projections by focusing on actual continuing demand rather than total current occupancy.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- ML Data Import Tab -->
        <div id="mlDataTab" class="report-container" style="display: <?php echo $activeTab === 'mlData' ? 'block' : 'none'; ?>">
            <div class="report-header-actions">
                <h3 class="report-title">Machine Learning Data Import</h3>
            </div>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger" style="margin-bottom: 20px; padding: 15px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; color: #721c24;">
                    <strong>Error:</strong> <?php echo str_replace('<br>', '<br>', $_GET['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success" style="margin-bottom: 20px; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; color: #155724;">
                    <strong>Success:</strong> <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>
            
            <div class="ml-data-section">
                <h4>Step 1: Download Template</h4>
                <p>Download the CSV template with the correct format for historical data entry:</p>
                <button type="button" class="btn btn-primary" onclick="downloadTemplate()">
                    <i class="fas fa-download"></i> Download Template
                </button>
                
                <div class="template-info">
                    <h5>Template Format:</h5>
                    <p><em>Note: Headers are case-insensitive. You can use any case (e.g., "semester", "Semester", "SEMESTER")</em></p>
                    <p><em>Important: Hostel names will be automatically converted to UPPERCASE for consistency in ML training.</em></p>
                    <ul>
                        <li><strong>Semester:</strong> Semester number (1, 2, 3) or "EXAMPLE" for template row</li>
                        <li><strong>Year:</strong> Academic year in cohort format (e.g., 2024/2025)</li>
                        <li><strong>Hostel:</strong> Hostel name with gender in brackets (e.g., "KOLEJ KEDIAMAN LEKIU (MALE)")</li>
                        <li><strong>Total_Severe_Chronic_Students:</strong> Total severe chronic students</li>
                        <li><strong>Booked_Severe_Chronic_Students:</strong> Severe chronic students with approved bookings</li>
                        <li><strong>Room_Full_Rejections:</strong> Students rejected due to room full</li>
                        <li><strong>Unbooked_Severe_Chronic_Students:</strong> Unbooked severe chronic students</li>
                        <li><strong>Graduating_Students:</strong> Graduating students with approved bookings</li>
                        <li><strong>Current_Occupancy:</strong> Current occupancy count</li>
                        <li><strong>Actual_Demand:</strong> Actual demand calculation</li>
                    </ul>
                </div>
            </div>
            
            <div class="ml-data-section">
                <h4>Step 2: Upload Historical Data</h4>
                <p>Upload your filled CSV file with historical data for previous semesters:</p>
                
                <form action="admMLDataUpload.php" method="post" enctype="multipart/form-data" id="uploadForm">
                    <div class="file-upload-container">
                        <input type="file" name="csvFile" id="csvFile" accept=".csv" required>
                        <label for="csvFile" class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            Choose CSV file
                        </label>
                    </div>
                    
                    <div class="upload-info">
                        <p><strong>Note:</strong> The uploaded data will be combined with system-generated data for machine learning training.</p>
                        <p><strong>Requirements:</strong></p>
                        <ul>
                            <li>File must be in CSV format</li>
                            <li>Must follow the exact template format (case-insensitive headers)</li>
                            <li>Data can be for any number of previous semesters (not limited to 6)</li>
                            <li>Maximum file size: 5MB</li>
                        </ul>
                    </div>
                    
                    <button type="button" class="btn btn-success" id="uploadBtn" onclick="document.getElementById('csvFile').click()" style="margin-top: 10px;">
                        <i class="fas fa-upload"></i> Upload Data
                    </button>
                </form>
            </div>
            
            <div class="ml-data-section">
                <h4>Step 3: Generate Combined Dataset</h4>
                <p>Generate the final dataset combining system data and uploaded historical data:</p>
                <button type="button" class="btn btn-gold" onclick="generateCombinedDataset()">
                    <i class="fas fa-file-csv"></i> Generate Combined Dataset
                </button>
                
                <div class="dataset-info">
                    <h5>Combined Dataset Features:</h5>
                    <ul>
                        <li>System-generated data from ALL previous semesters (comprehensive historical training)</li>
                        <li>User-uploaded historical data (any number of previous semesters)</li>
                        <li>All data aggregated and formatted for ML training</li>
                        <li>Ready for demand prediction models using complete historical patterns</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- ML Prediction Tab -->
        <div id="mlPredictionTab" class="report-container" style="display: <?php echo $activeTab === 'mlPrediction' ? 'block' : 'none'; ?>">
            <div class="report-header-actions">
                <h3 class="report-title">Machine Learning Prediction</h3>
            </div>
            
            <div class="ml-data-section" id="step1Section">
                <h4>Step 1: Simple Prediction System</h4>
                <p>Train a machine learning model and generate predictions for the current semester:</p>
                
                <div class="dataset-info">
                    <h5>Simple Prediction Features:</h5>
                    <ul>
                        <li>Uses ALL historical data from previous semesters (comprehensive training)</li>
                        <li>Python Random Forest algorithm (100 trees, max depth 10)</li>
                        <li>Predicts demand for next semester based on complete historical patterns</li>
                        <li>Considers current occupancy and historical trends</li>
                        <li>Accounts for severe chronic students and room rejections from all past data</li>
                        <li>Weighted prediction: 40% ML + 60% Historical components</li>
                    </ul>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn btn-primary ml-prediction-btn" onclick="trainMLModel()">
                        <i class="fas fa-brain"></i> Train ML Model
                    </button>
                    
                    <button type="button" class="btn btn-gold ml-prediction-btn" onclick="generatePredictions()" id="predictBtn" disabled>
                        <i class="fas fa-chart-line"></i> Generate Predictions
                    </button>
                </div>
                
                <div id="trainingStatus" style="margin-top: 15px; display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-spinner fa-spin"></i> Training model...
                    </div>
                </div>
                
                <div id="predictionStatus" style="margin-top: 15px; display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-spinner fa-spin"></i> Generating predictions...
                    </div>
                </div>
            </div>
            
            <div class="ml-data-section">
                <h4>Step 2: Advanced Prediction System</h4>
                <p>Train an advanced model for multi-semester predictions with confidence intervals:</p>
                
                <div class="dataset-info">
                    <h5>Advanced Prediction Features:</h5>
                    <ul>
                        <li>Multi-semester predictions (up to 6 semesters ahead) with realistic growth patterns</li>
                        <li>First semester identical to Simple Prediction (same comprehensive historical training)</li>
                        <li>Multi-step forecasting: each future semester builds on previous predictions</li>
                        <li>Python Random Forest algorithm (100 trees, max depth 10)</li>
                        <li>Advanced statistical modeling with confidence intervals</li>
                        <li>95% confidence level predictions with upper and lower bounds</li>
                        <li>Model performance metrics (R², Standard Error)</li>
                    </ul>
                </div>
                
                <div class="button-group">
                    <button type="button" class="btn btn-primary ml-prediction-btn" onclick="trainAdvancedModel()">
                        <i class="fas fa-brain"></i> Train Advanced Model
                    </button>
                    
                    <button type="button" class="btn btn-gold ml-prediction-btn" onclick="generateMultiplePredictions()" id="advancedPredictBtn" disabled>
                        <i class="fas fa-chart-bar"></i> Multi-Semester Predictions
                    </button>
                </div>
                
                <div id="advancedTrainingStatus" style="margin-top: 15px; display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-spinner fa-spin"></i> Training Advanced Model...
                    </div>
                </div>
                
                <div id="advancedPredictionStatus" style="margin-top: 15px; display: none;">
                    <div class="alert alert-info">
                        <i class="fas fa-spinner fa-spin"></i> Generating Advanced Predictions...
                    </div>
                </div>
            </div>
            
            <!-- Prediction Results -->
            <div id="predictionResults" style="display: none;">
                <h3 class="report-title">Prediction Results</h3>
                
                <div class="chart-container">
                    <canvas id="predictionChart"></canvas>
                </div>
                
                <div class="stats-grid" id="predictionStats">
                    <!-- Prediction statistics will be populated here -->
                </div>
                
                <h3 class="report-title">Detailed Predictions</h3>
                
                <!-- Prediction Information Note -->
                <div class="prediction-info" id="predictionInfo" style="display: none;">
                    <div class="info-box">
                        <h4><i class="fas fa-info-circle"></i> Prediction Information</h4>
                        <p><strong>Data Source:</strong> <span id="dataSourceInfo"></span></p>
                        <p><strong>Training Approach:</strong> Uses ALL historical data from previous semesters (not just one base semester)</p>
                        <p><strong>Calculation Methods:</strong></p>
                        <ul>
                            <li><strong>Final Prediction:</strong> Weighted average of ML and Historical predictions (40% ML + 60% Historical), rounded up to nearest integer</li>
                            <li><strong>ML Model Prediction:</strong> Python Random Forest algorithm (100 trees, max depth 10) using scikit-learn, trained on comprehensive historical data including 9 factors: semester, year, hostel, severe chronic students, room rejections, graduating students, and current occupancy</li>
                            <li><strong>Historical/Statistical Prediction:</strong> Component-based calculation (returning students + estimated new students + unbooked severe chronic students)</li>
                            <li><strong>Estimated New Students:</strong> Combined estimation (60% historical average + 40% ML adjustment) from ALL historical CSV data</li>
                            <li><strong>Previous Semester Occupancy:</strong> Students with approved bookings in the previous semester (used for comparison baseline)</li>
                            <li><strong>Severe Chronic Students:</strong> Historical data from past semesters (aggregated from ALL CSV files)</li>
                            <li><strong>Room Full Rejections:</strong> Historical data from past semesters (aggregated from ALL CSV files)</li>
                            <li><strong>Returning Students:</strong> Students who will continue studying AFTER the current semester (not graduating at the end of current semester)</li>
                            <li><strong>Room Recommendation:</strong> Calculated based on predicted demand and current severe chronic percentage (29.6%). Shows estimated severe students from predicted demand, total rooms needed, and gap analysis. Severe students need individual rooms, normal students share rooms based on room capacity</li>
                        </ul>
                    </div>
                </div>
                
                <!-- Simple Prediction Table -->
                <div style="overflow-x: auto; max-width: 100%;">
                <table class="report-table" id="predictionTable" style="width: 1800px; white-space: nowrap;">
                    <thead>
                        <tr>
                            <th>Hostel</th>
                            <th>Semester</th>
                            <th>Final Prediction</th>
                            <th>ML Prediction</th>
                            <th>Historical Prediction</th>
                            <th>Previous Semester Occupancy</th>
                            <th>Severe Chronic Students</th>
                            <th>Room Full Rejections</th>
                            <th>Returning Students</th>
                            <th>Room Recommendation</th>
                            <th>Demand Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Simple prediction data will be populated here -->
                    </tbody>
                </table>
                </div>
                
                <!-- Advanced Prediction Table -->
                <div style="overflow-x: auto; max-width: 100%;">
                <table class="report-table" id="advancedPredictionTable" style="display: none; width: 1200px; white-space: nowrap;">
                    <thead>
                        <tr>
                            <th>Hostel</th>
                            <th>Semester</th>
                            <th>Predicted Demand</th>
                            <th>Lower Bound (95% CI)</th>
                            <th>Upper Bound (95% CI)</th>
                            <th>Confidence Interval</th>
                            <th>Room Recommendation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Advanced prediction data will be populated here -->
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Function to update faculty charts based on selected semester
        function updateFacultyCharts() {
            const selectedValue = document.getElementById('semesterSelect').value;
            
            // Update URL parameter without affecting histogram tab parameter
            const url = new URL(window.location);
            const currentTab = url.searchParams.get('tab') || 'occupancy';
            url.searchParams.set('semester', selectedValue);
            url.searchParams.set('tab', currentTab);
            
            // Only reload if we're on the occupancy tab
            if (currentTab === 'occupancy') {
                window.history.pushState({}, '', url);
                location.reload();
            }
        }

        // Function to show selected tab
        function showTab(tabName) {
            document.getElementById('occupancyTab').style.display = tabName === 'occupancy' ? 'block' : 'none';
            document.getElementById('estimationTab').style.display = tabName === 'estimation' ? 'block' : 'none';
            document.getElementById('mlDataTab').style.display = tabName === 'mlData' ? 'block' : 'none';
            document.getElementById('mlPredictionTab').style.display = tabName === 'mlPrediction' ? 'block' : 'none';
            
            // Update active tab button using data-tab attribute
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.getAttribute('data-tab') === tabName) {
                    btn.classList.add('active');
                }
            });

            // Update URL without affecting semester parameter
            const url = new URL(window.location);
            const currentSemester = url.searchParams.get('semester');
            url.searchParams.set('tab', tabName);
            if (currentSemester) {
                url.searchParams.set('semester', currentSemester);
            }
            window.history.pushState({}, '', url);
        }

        // Create Occupancy Chart (histogram) - not affected by semester selection
        <?php if (!empty($occupancyData) && array_sum(array_map(function($hostel) { 
            return array_sum($hostel['data']); 
        }, $occupancyData)) > 0): ?>
        const occupancyCtx = document.getElementById('occupancyChart').getContext('2d');
        
        // Define consistent colors for hostels
        const hostelColors = {
            <?php foreach ($hostels as $hostel): ?>
                '<?php echo $hostel['Name']; ?>': '<?php echo sprintf('rgb(%d, %d, %d)', 
                    $hostel['Name'] == 'KOLEJ KEDIAMAN LEKIU' ? 145 : 114, 
                    $hostel['Name'] == 'KOLEJ KEDIAMAN LEKIU' ? 198 : 197, 
                    $hostel['Name'] == 'KOLEJ KEDIAMAN LEKIU' ? 54 : 122); ?>',
            <?php endforeach; ?>
        };
        
        new Chart(occupancyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_map(function($sem) { return $sem['Name']; }, $semesters)); ?>,
                datasets: <?php 
                    $datasets = [];
                    foreach ($hostels as $index => $hostel) {
                        $color = $hostelColors[$hostel['Name']] ?? sprintf('rgb(%d, %d, %d)', 
                            rand(50, 200), rand(50, 200), rand(50, 200)
                        );
                        $datasets[] = [
                            'label' => $hostel['Name'],
                            'data' => !empty($occupancyData[$index]['data']) ? $occupancyData[$index]['data'] : [0],
                            'backgroundColor' => $color,
                            'borderColor' => $color,
                            'borderWidth' => 1,
                            'barPercentage' => 0.8,
                            'categoryPercentage' => 0.9,
                            'hostelId' => $hostel['HostID'] // Add hostel ID to dataset
                        ];
                    }
                    echo json_encode($datasets);
                ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'x',
                plugins: {
                    title: {
                        display: true,
                        text: 'Student Occupancy by Hostel and Semester',
                        font: {
                            size: 16,
                            family: 'Arial'
                        }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: 'Arial'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                // Get hostel ID from dataset
                                const hostelId = context.dataset.hostelId;
                                const capacities = <?php echo json_encode($hostelCapacities); ?>;
                                const hostelCapacity = capacities[hostelId] || 0;
                                
                                // Debug output
                                console.log('Hostel:', context.dataset.label);
                                console.log('Hostel ID:', hostelId);
                                console.log('Capacity:', hostelCapacity);
                                console.log('Students:', context.raw);
                                
                                const occupancyRate = hostelCapacity > 0 ? ((context.raw / hostelCapacity) * 100).toFixed(1) : 0;
                                return `${context.dataset.label}: ${context.raw} students (${occupancyRate}% capacity)`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Students',
                            font: {
                                family: 'Arial'
                            }
                        },
                        ticks: {
                            font: {
                                family: 'Arial'
                            },
                            stepSize: 1
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Semester',
                            font: {
                                family: 'Arial'
                            }
                        },
                        ticks: {
                            font: {
                                family: 'Arial'
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Create Faculty Distribution Charts
        <?php
        foreach ($hostels as $index => $hostel): 
            $distinctStudents = !empty($facultyData[$index]['data'][$selectedSemester]) ? 
                $facultyData[$index]['data'][$selectedSemester] : [];
            
            // Only create chart if there's data
            if (!empty($distinctStudents)):
        ?>
            const facultyCtx<?php echo $index; ?> = document.getElementById('facultyChart<?php echo $index; ?>');
            if (facultyCtx<?php echo $index; ?>) {
                // Define consistent faculty colors
                const facultyColors = {
                    'FKEKK': 'rgba(255, 99, 132, 0.8)',
                    'FKE': 'rgba(54, 162, 235, 0.8)',
                    'FPTT': 'rgba(255, 206, 86, 0.8)',
                    'FTKEE': 'rgba(75, 192, 192, 0.8)',
                    'FTKMP': 'rgba(153, 102, 255, 0.8)',
                    'FTMK': 'rgba(255, 159, 64, 0.8)',
                    'FKP': 'rgba(199, 199, 199, 0.8)',
                    'FTKM': 'rgba(83, 102, 255, 0.8)',
                    'FKM': 'rgba(40, 159, 64, 0.8)',
                    'FPTT': 'rgba(210, 105, 30, 0.8)'
                };
                
                const facultyBorderColors = {
                    'FKEKK': 'rgba(255, 99, 132, 1)',
                    'FKE': 'rgba(54, 162, 235, 1)',
                    'FPTT': 'rgba(255, 206, 86, 1)',
                    'FTKEE': 'rgba(75, 192, 192, 1)',
                    'FTKMP': 'rgba(153, 102, 255, 1)',
                    'FTMK': 'rgba(255, 159, 64, 1)',
                    'FKP': 'rgba(199, 199, 199, 1)',
                    'FTKM': 'rgba(83, 102, 255, 1)',
                    'FKM': 'rgba(40, 159, 64, 1)',
                    'FPTT': 'rgba(210, 105, 30, 1)'
                };
                
                // Get faculties and map their colors
                const faculties = <?php echo json_encode(array_keys($distinctStudents)); ?>;
                const backgroundColors = faculties.map(faculty => facultyColors[faculty] || 'rgba(128, 128, 128, 0.8)');
                const borderColors = faculties.map(faculty => facultyBorderColors[faculty] || 'rgba(128, 128, 128, 1)');
                
                new Chart(facultyCtx<?php echo $index; ?>, {
                    type: 'pie',
                    data: {
                        labels: <?php 
                            $allFaculties = array_keys($distinctStudents);
                            echo json_encode(array_values($allFaculties));
                        ?>,
                        datasets: [{
                            data: <?php 
                                $facultyCounts = array_values($distinctStudents);
                                echo json_encode($facultyCounts);
                            ?>,
                            backgroundColor: backgroundColors,
                            borderColor: borderColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: '<?php echo addslashes($hostel['Name']); ?>',
                                font: {
                                    size: 16,
                                    family: 'Arial'
                                }
                            },
                            legend: {
                                position: 'bottom',
                                labels: {
                                    font: {
                                        family: 'Arial'
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const label = context.label || '';
                                        const value = context.raw;
                                        const total = context.chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
                                        const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                        return label + ': ' + value + ' students (' + percentage + '%)';
                                    }
                                }
                            }
                        }
                    }
                });
            }
        <?php 
            endif;
        endforeach; 
        ?>

        // Create Estimation Chart
        const estimationCtx = document.getElementById('estimationChart').getContext('2d');
        
        // Debug data before chart creation
        console.log('Raw Estimation Data:', <?php echo json_encode($estimationData); ?>);
        
        // Verify we have the estimation context
        if (!estimationCtx) {
            console.error('Could not get estimation chart context');
        }
        
        // Get the current semester name from the first hostel with data
        const currentSemesterName = <?php 
            echo !empty($estimationData) && isset($estimationData[0]['semesterName']) ? 
                "'" . addslashes($estimationData[0]['semesterName']) . "'" : 
                "'Current Semester'"; 
        ?>;
        
        // Create the datasets with additional logging
        const estimationDatasets = <?php 
                    $datasets = [];
                    foreach ($estimationData as $index => $hostel) {
                // Use the same color as in the occupancy chart
                        $color = sprintf('rgb(%d, %d, %d)', 
                    $hostel['name'] == 'KOLEJ KEDIAMAN LEKIU' ? 145 : 114, 
                    $hostel['name'] == 'KOLEJ KEDIAMAN LEKIU' ? 198 : 197, 
                    $hostel['name'] == 'KOLEJ KEDIAMAN LEKIU' ? 54 : 122
                        );
                
                // Only include current and next semester projection
                $data = [$hostel['current'], $hostel['projections'][0]];
                echo "<!-- Dataset for " . htmlspecialchars($hostel['name']) . ": " . implode(", ", $data) . " -->\n";
                        $datasets[] = [
                            'label' => $hostel['name'],
                    'data' => $data,
                            'borderColor' => $color,
                            'backgroundColor' => $color,
                            'tension' => 0.1,
                            'borderWidth' => 2,
                            'pointRadius' => 5,
                            'pointHoverRadius' => 7,
                            'fill' => false
                        ];
                    }
                    echo json_encode($datasets);
        ?>;
        
        console.log('Processed Chart Datasets:', estimationDatasets);
        
        new Chart(estimationCtx, {
            type: 'line',
            data: {
                labels: ['Current', 'Next Semester'],
                datasets: estimationDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Projected Occupancy for ' + currentSemesterName + ' and Next Semester',
                        font: {
                            size: 16,
                            family: 'Arial'
                        }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: 'Arial'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                const semesterNames = <?php echo json_encode(array_column($estimationData, 'semesterName')); ?>;
                                const hostelIndex = context[0].datasetIndex;
                                const pointIndex = context[0].dataIndex;
                                
                                if (pointIndex === 0) {
                                    return 'Current: ' + (semesterNames[hostelIndex] || 'Current Semester');
                                } else {
                                    return 'Next Semester';
                                }
                            },
                            label: function(context) {
                                const capacity = <?php echo json_encode(array_column($estimationData, 'capacity')); ?>[context.datasetIndex];
                                const occupancyRate = capacity > 0 ? ((context.raw / capacity) * 100).toFixed(1) : 0;
                                return context.dataset.label + ': ' + context.raw + ' students (' + occupancyRate + '% capacity)';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Students',
                            font: {
                                family: 'Arial'
                            }
                        },
                        ticks: {
                            font: {
                                family: 'Arial'
                            },
                            stepSize: 1
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Semester',
                            font: {
                                family: 'Arial'
                            }
                        },
                        ticks: {
                            font: {
                                family: 'Arial'
                            }
                        }
                    }
                }
            }
        });
        
        // Create Room Type Distribution Chart
        const roomTypeCtx = document.getElementById('roomTypeChart').getContext('2d');
        new Chart(roomTypeCtx, {
            type: 'bar',
            data: {
                labels: <?php 
                    $allTypes = [];
                    foreach ($roomTypeData as $types) {
                        $allTypes = array_unique(array_merge($allTypes, array_keys($types)));
                    }
                    echo json_encode(array_values($allTypes));
                ?>,
                datasets: <?php 
                    $datasets = [];
                    foreach ($hostels as $index => $hostel) {
                        $types = $roomTypeData[$hostel['HostID']] ?? [];
                        $data = [];
                        foreach ($allTypes as $type) {
                            $data[] = $types[$type] ?? 0;
                        }
                        $color = sprintf('rgb(%d, %d, %d)', 
                            rand(50, 200), rand(50, 200), rand(50, 200)
                        );
                        $datasets[] = [
                            'label' => $hostel['Name'],
                            'data' => $data,
                            'backgroundColor' => $color,
                            'borderColor' => $color,
                            'borderWidth' => 1,
                            'barPercentage' => 0.8,
                            'categoryPercentage' => 0.9
                        ];
                    }
                    echo json_encode($datasets);
                ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Room Type Distribution by Hostel',
                        font: {
                            size: 16,
                            family: 'Arial'
                        }
                    },
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: {
                                family: 'Arial'
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.raw + ' rooms';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Rooms',
                            font: {
                                family: 'Arial'
                            }
                        },
                        ticks: {
                            font: {
                                family: 'Arial'
                            },
                            stepSize: 1
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Room Type',
                            font: {
                                family: 'Arial'
                            }
                        },
                        ticks: {
                            font: {
                                family: 'Arial'
                            }
                        }
                    }
                }
            }
        });
    </script>
    
    <script>
        // Function to generate the occupancy report
        function printOccupancyReport() {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Create the print content
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Room Occupancy Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .report-header {
                            text-align: center;
                            margin-bottom: 30px;
                        }
                        .report-header h1 {
                            color: #25408f;
                            font-size: 28px;
                            margin-bottom: 10px;
                            text-align: center;
                        }
                        .search-results-container {
                            text-align: left;
                            margin-left: 20px;
                        }
                        .search-results {
                            font-weight: bold;
                            color: #333;
                            font-size: 16px;
                            margin-bottom: 5px;
                        }
                        .results-count {
                            font-weight: normal;
                            color: #25408f;
                            font-size: 16px;
                            margin-bottom: 20px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 20px;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 15px;
                            text-align: left;
                            font-size: 16px;
                            font-family: Arial, sans-serif;
                            line-height: 1.4;
                        }
                        th {
                            background-color: #25408f;
                            color: white;
                            font-weight: bold;
                            height: auto;
                            min-height: 45px;
                        }
                        tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .chart-container {
                            width: 100%;
                            max-width: 800px;
                            margin: 0 auto 30px;
                            text-align: center;
                        }
                        .pie-chart-container {
                            width: 70%;
                            margin: 0 auto 30px;
                            display: flex;
                            flex-wrap: wrap;
                            justify-content: center;
                            gap: 30px;
                        }
                        .pie-chart {
                            width: 45%;
                            min-width: 300px;
                            margin-bottom: 20px;
                        }
                        .pie-chart h3 {
                            text-align: center;
                            color: #25408f;
                            margin-bottom: 10px;
                        }
                        .report-footer {
                            margin-top: 30px;
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                            border-top: 1px solid #ddd;
                            padding-top: 20px;
                        }
                        h2 {
                            color: #25408f;
                            font-size: 22px;
                            margin-top: 30px;
                            margin-bottom: 15px;
                            text-align: left;
                        }
                        @media print {
                            @page {
                                size: portrait;
                                margin: 1.5cm;
                            }
                            body {
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Room Occupancy Report</h1>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                    </div>
                    <div class="search-results-container">
                        <div class="search-results">Summary</div>
            `;
            
            // Add semester info
            const selectedSemesterElement = document.getElementById('semesterSelect');
            const selectedSemesterName = selectedSemesterElement ? selectedSemesterElement.options[selectedSemesterElement.selectedIndex].text : 'All Semesters';
            
            printContent += `<div class="results-count">Showing data for: ${selectedSemesterName}</div></div>`;
            
            // Add occupancy chart image
            const occupancyCanvas = document.getElementById('occupancyChart');
            if (occupancyCanvas) {
                printContent += `
                    <h2>Occupancy by Hostel and Semester</h2>
                    <div class="chart-container">
                        <img src="${occupancyCanvas.toDataURL('image/png')}" style="width: 100%; max-width: 800px;" alt="Occupancy Chart">
                    </div>
                `;
            }
            
            // Add faculty charts if they exist
            const facultyCharts = [];
            for (let i = 0; i < 10; i++) { // Check for up to 10 faculty charts
                const facultyCanvas = document.getElementById(`facultyChart${i}`);
                if (facultyCanvas) {
                    let hostelName = '';
                    try {
                        const chartContainer = facultyCanvas.closest('.chart-container-small');
                        if (chartContainer) {
                            const headerElement = chartContainer.querySelector('canvas').parentNode.querySelector('.chartjs-title') || 
                                                 chartContainer.querySelector('h4');
                            hostelName = headerElement ? headerElement.textContent : `Hostel ${i+1}`;
                        }
                    } catch (e) {
                        hostelName = `Hostel ${i+1}`;
                    }
                    
                    facultyCharts.push({
                        canvas: facultyCanvas,
                        name: hostelName
                    });
                }
            }
            
            if (facultyCharts.length > 0) {
                printContent += `<h2>Faculty Distribution by Hostel</h2>`;
                printContent += `<div class="pie-chart-container">`;
                
                facultyCharts.forEach(chart => {
                    printContent += `
                        <div class="pie-chart">
                            <h3>${chart.name}</h3>
                            <img src="${chart.canvas.toDataURL('image/png')}" style="width: 100%;" alt="Faculty Chart for ${chart.name}">
                        </div>
                    `;
                });
                
                printContent += `</div>`;
            }
            
            // Add occupancy statistics table
            printContent += `
                <h2>Occupancy Statistics</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Hostel</th>
                            <th>Occupancy Rate</th>
                            <th>Occupied Beds</th>
                            <th>Total Capacity</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            // Get stats from the page
            const statCards = document.querySelectorAll('#occupancyTab .stat-card');
            statCards.forEach(card => {
                const hostelName = card.querySelector('.stat-title').textContent;
                const occupancyRate = card.querySelector('.stat-value').textContent;
                const bedsInfo = card.querySelector('.stat-subtitle').textContent.split('\n')[0].trim();
                const [occupied, capacity] = bedsInfo.split('/').map(str => str.trim());
                
                printContent += `
                    <tr>
                        <td>${hostelName}</td>
                        <td>${occupancyRate}</td>
                        <td>${occupied}</td>
                        <td>${capacity}</td>
                    </tr>
                `;
            });
            
            printContent += `
                    </tbody>
                </table>
                
                <div class="report-footer">
                    <p>Smart Hostel Management System &copy; ${new Date().getFullYear()}</p>
                </div>
            </body>
            </html>
            `;
            
            // Write to the print window and print
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Wait for images to load before printing
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }
        
        // Function to generate the estimation report
        function printEstimationReport() {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Create the print content
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Room Estimation Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .report-header {
                            text-align: center;
                            margin-bottom: 30px;
                        }
                        .report-header h1 {
                            color: #25408f;
                            font-size: 28px;
                            margin-bottom: 10px;
                            text-align: center;
                        }
                        .search-results-container {
                            text-align: left;
                            margin-left: 20px;
                        }
                        .search-results {
                            font-weight: bold;
                            color: #333;
                            font-size: 16px;
                            margin-bottom: 5px;
                        }
                        .results-count {
                            font-weight: normal;
                            color: #25408f;
                            font-size: 16px;
                            margin-bottom: 20px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 20px;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 15px;
                            text-align: left;
                            font-size: 16px;
                            font-family: Arial, sans-serif;
                            line-height: 1.4;
                        }
                        th {
                            background-color: #25408f;
                            color: white;
                            font-weight: bold;
                            height: auto;
                            min-height: 45px;
                        }
                        tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .chart-container {
                            width: 100%;
                            max-width: 800px;
                            margin: 0 auto 30px;
                            text-align: center;
                        }
                        .report-footer {
                            margin-top: 30px;
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                            border-top: 1px solid #ddd;
                            padding-top: 20px;
                        }
                        h2 {
                            color: #25408f;
                            font-size: 22px;
                            margin-top: 30px;
                            margin-bottom: 15px;
                            text-align: left;
                        }
                        .factor-section {
                            margin-top: 30px;
                        }
                        .factor-grid {
                            display: grid;
                            grid-template-columns: repeat(3, 1fr);
                            gap: 20px;
                            margin: 0 auto 30px;
                            max-width: 900px;
                        }
                        .factor-card {
                            background-color: #e6f3ff;
                            border-left: 4px solid #3883ce;
                            padding: 15px;
                            border-radius: 4px;
                        }
                        .factor-title {
                            font-weight: bold;
                            font-size: 16px;
                            margin-bottom: 10px;
                        }
                        .factor-value {
                            font-size: 24px;
                        }
                        .stat-details {
                            margin-top: 8px;
                            font-size: 12px;
                            color: #666;
                            font-style: italic;
                        }
                        .prediction-info {
                            margin: 20px 0;
                        }
                        .info-box {
                            background-color: #e6f3ff;
                            border-left: 4px solid #3883ce;
                            padding: 15px;
                            border-radius: 4px;
                            margin-bottom: 20px;
                        }
                        .info-box h4 {
                            color: #25408f;
                            margin-top: 0;
                            margin-bottom: 10px;
                        }
                        .info-box ul {
                            margin: 10px 0;
                            padding-left: 20px;
                        }
                        .info-box li {
                            margin-bottom: 5px;
                        }
                            font-weight: bold;
                            color: #25408f;
                        }
                        .factor-subtitle {
                            font-size: 14px;
                            color: #666;
                            margin-top: 5px;
                        }
                        @media print {
                            @page {
                                size: portrait;
                                margin: 1.5cm;
                            }
                            body {
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                            .factor-grid {
                                display: grid;
                                grid-template-columns: repeat(3, 1fr);
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Room Estimation Report</h1>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                    </div>
                    <div class="search-results-container">
                        <div class="search-results">Summary</div>
                        <div class="results-count">Showing estimation data for upcoming semesters</div>
                    </div>
            `;
            
            // Add estimation chart image
            const estimationCanvas = document.getElementById('estimationChart');
            if (estimationCanvas) {
                printContent += `
                    <h2>Projected Occupancy for Current and Next Semester</h2>
                    <div class="chart-container">
                        <img src="${estimationCanvas.toDataURL('image/png')}" style="width: 100%; max-width: 800px;" alt="Estimation Chart">
                    </div>
                `;
            }
            
            // Add room type chart image
            const roomTypeCanvas = document.getElementById('roomTypeChart');
            if (roomTypeCanvas) {
                printContent += `
                    <h2>Room Type Distribution by Hostel</h2>
                    <div class="chart-container">
                        <img src="${roomTypeCanvas.toDataURL('image/png')}" style="width: 100%; max-width: 800px;" alt="Room Type Chart">
                    </div>
                `;
            }
            
            // Add detailed estimations table
            printContent += `
                <h2>Detailed Estimations</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Hostel</th>
                            <th>Current Semester</th>
                            <th>Next Semester (Est.)</th>
                            <th>Total Capacity</th>
                            <th>Current Rate</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            
            // Get estimation table data
            const estimationTable = document.querySelector('#estimationTab .report-table');
            if (estimationTable) {
                const rows = Array.from(estimationTable.querySelectorAll('tbody tr'));
                rows.forEach(row => {
                    const cells = Array.from(row.cells);
                    printContent += `
                        <tr>
                            <td>${cells[0].textContent}</td>
                            <td>${cells[1].textContent}</td>
                            <td>${cells[2].textContent}</td>
                            <td>${cells[3].textContent}</td>
                            <td>${cells[4].textContent}</td>
                        </tr>
                    `;
                });
            }
            
            printContent += `
                    </tbody>
                </table>
            `;
            
            // Add factors affecting estimation
            printContent += `
                <h2>Factors Affecting Estimation</h2>
                <div class="factor-grid">
            `;
            
            // Get factor cards data
            const factorCards = document.querySelectorAll('#estimationTab .factors-card');
            factorCards.forEach(card => {
                const title = card.querySelector('.stat-title').textContent;
                const value = card.querySelector('.stat-value').textContent;
                const subtitle = card.querySelector('.stat-subtitle').textContent;
                const note = card.querySelector('.stat-note')?.textContent || '';
                
                printContent += `
                    <div class="factor-card">
                        <div class="factor-title">${title}</div>
                        <div class="factor-value">${value}</div>
                        <div class="factor-subtitle">${subtitle}</div>
                        <div class="factor-subtitle" style="font-style: italic;">${note}</div>
                    </div>
                `;
            });
            
            printContent += `
                </div>
                
                <div class="report-footer">
                    <p>Smart Hostel Management System &copy; ${new Date().getFullYear()}</p>
                </div>
            </body>
            </html>
            `;
            
            // Write to the print window and print
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Wait for images to load before printing
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }

        // ML Data Import Functions
        function downloadTemplate() {
            // Create a download link for the template
            const csvContent = "Semester,Year,Hostel,Total_Severe_Chronic_Students,Booked_Severe_Chronic_Students,Room_Full_Rejections,Unbooked_Severe_Chronic_Students,Graduating_Students,Current_Occupancy,Actual_Demand\n";
            
            // Add example row for reference with EXAMPLE text in Semester column
            const exampleRow = "EXAMPLE: 1,2024/2025,KOLEJ KEDIAMAN LEKIU (MALE),15,10,5,5,8,120,140\n";
            const fullContent = csvContent + exampleRow;
            
            const blob = new Blob([fullContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'ml_data_template.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        function generateCombinedDataset() {
            // Show loading message
            const btn = event.target;
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            btn.disabled = true;

            // Make AJAX request to generate combined dataset
            fetch('admGenerateCombinedDataset.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Download the generated file
                        const a = document.createElement('a');
                        a.href = data.fileUrl;
                        a.download = 'combined_ml_dataset.csv';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        
                        // Show success message and ask if user wants to proceed to training
                        const proceedToTraining = confirm(
                            'Combined dataset generated successfully! Total rows: ' + data.totalRows + 
                            '\n\nWould you like to proceed to ML Prediction tab to train the model?'
                        );
                        
                        if (proceedToTraining) {
                            // Switch to ML Prediction tab and scroll to Step 1
                            showTab('mlPrediction');
                            // Scroll to Step 1 after a short delay to ensure tab is loaded
                            setTimeout(() => {
                                // Target the specific Step 1 section by ID
                                const step1Section = document.getElementById('step1Section');
                                if (step1Section) {
                                    console.log('Scrolling to Step 1:', step1Section.querySelector('h4')?.textContent);
                                    step1Section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                } else {
                                    console.log('Step 1 section not found');
                                }
                            }, 300);
                        }
                    } else {
                        alert('Error generating dataset: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error generating dataset. Please check the console for details and try again.');
                })
                .finally(() => {
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                });
        }



        // Test ML generation function
        function testMLGeneration() {
            fetch('test_ml_generation.php')
                .then(response => response.json())
                .then(data => {
                    console.log('Test results:', data);
                    alert('Test completed. Check console for details.\n\n' + 
                          'Database connected: ' + data.database_connected + '\n' +
                          'Directory writable: ' + data.directory_writable + '\n' +
                          'Uploads directory: ' + data.uploads_directory + '\n' +
                          'Uploads writable: ' + data.uploads_writable);
                })
                .catch(error => {
                    console.error('Test error:', error);
                    alert('Test failed. Check console for details.');
                });
        }

        // ML Prediction Functions
        function trainMLModel() {
            // Show training status
            document.getElementById('trainingStatus').style.display = 'block';
            
            // Find the latest combined dataset
            fetch('admFindLatestDataset.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.dataset_path) {
                        // Train the model
                        const formData = new FormData();
                        formData.append('action', 'train_model');
                        formData.append('dataset_path', data.dataset_path);
                        
                        return fetch('admMLPrediction.php', {
                            method: 'POST',
                            body: formData
                        });
                    } else {
                        throw new Error('No combined dataset found. Please generate a combined dataset first.');
                    }
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('trainingStatus').style.display = 'none';
                    
                    if (data.success) {
                        alert('Model trained successfully!\n\nTraining samples: ' + data.training_samples + '\nHostels: ' + data.hostels);
                        document.getElementById('predictBtn').disabled = false;
                    } else {
                        alert('Training failed: ' + data.message);
                    }
                })
                .catch(error => {
                    document.getElementById('trainingStatus').style.display = 'none';
                    console.error('Training error:', error);
                    alert('Training failed: ' + error.message);
                });
        }

        function generatePredictions() {
            // Show prediction status
            document.getElementById('predictionStatus').style.display = 'block';
            
            const formData = new FormData();
            formData.append('action', 'predict');
            
            fetch('admMLPrediction.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('predictionStatus').style.display = 'none';
                
                if (data.success) {
                    displayPredictions(data.predictions, data.current_semester);
                } else {
                    alert('Prediction failed: ' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('predictionStatus').style.display = 'none';
                console.error('Prediction error:', error);
                alert('Prediction failed. Please try again.');
            });
        }

        function trainAdvancedModel() {
            // Get the button and disable it
            const btn = event.target;
            btn.disabled = true;
            
            // Show advanced training status below the button
            document.getElementById('advancedTrainingStatus').style.display = 'block';
            
            // Find the latest combined dataset
            fetch('admFindLatestDataset.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.dataset_path) {
                        // Train the advanced model using Python Random Forest
                        const formData = new FormData();
                        formData.append('action', 'train_advanced_model');
                        formData.append('dataset_path', data.dataset_path);
                        
                        return fetch('admAdvancedMLPrediction.php', {
                            method: 'POST',
                            body: formData
                        });
                    } else {
                        throw new Error('No combined dataset found. Please generate a combined dataset first.');
                    }
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('advancedTrainingStatus').style.display = 'none';
                    
                    if (data.success) {
                        alert('Advanced Random Forest model trained successfully!\n\nTraining samples: ' + data.training_samples + 
                              '\nHostels: ' + data.hostels + 
                              '\nR-squared: ' + data.r_squared + 
                              '\nStandard Error: ' + parseFloat(data.standard_error).toFixed(2) +
                              '\n\nModel: Python Random Forest (100 trees, max depth 10)');
                        document.getElementById('advancedPredictBtn').disabled = false;
                    } else {
                        alert('Advanced training failed: ' + data.message);
                    }
                })
                .catch(error => {
                    document.getElementById('advancedTrainingStatus').style.display = 'none';
                    console.error('Advanced training error:', error);
                    alert('Advanced training failed: ' + error.message);
                })
                .finally(() => {
                    btn.disabled = false;
                });
        }

        function generateMultiplePredictions() {
            // Get the button and disable it
            const btn = event.target;
            btn.disabled = true;
            
            const numSemesters = prompt('Enter number of semesters to predict (1-6):', '3');
            if (!numSemesters || isNaN(numSemesters) || numSemesters < 1 || numSemesters > 6) {
                alert('Please enter a valid number between 1 and 6.');
                btn.disabled = false;
                return;
            }
            
            // Show advanced prediction status below the button
            document.getElementById('advancedPredictionStatus').style.display = 'block';
            
            const formData = new FormData();
            formData.append('action', 'predict_multiple');
            formData.append('num_semesters', numSemesters);
            
            fetch('admAdvancedMLPrediction.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById('advancedPredictionStatus').style.display = 'none';
                
                if (data.success) {
                    displayMultiplePredictions(data.predictions, data.current_semester, data.model_metrics);
                } else {
                    alert('Advanced prediction failed: ' + data.message);
                }
            })
            .catch(error => {
                document.getElementById('advancedPredictionStatus').style.display = 'none';
                console.error('Advanced prediction error:', error);
                alert('Advanced prediction failed. Please try again.');
            })
            .finally(() => {
                btn.disabled = false;
            });
        }

        function displayMultiplePredictions(predictions, currentSemester, modelMetrics) {
            // Show results section
            document.getElementById('predictionResults').style.display = 'block';
            
            // Hide simple prediction table and show advanced prediction table
            document.getElementById('predictionTable').style.display = 'none';
            document.getElementById('advancedPredictionTable').style.display = 'table';
            
            // Destroy existing chart if it exists
            if (window.predictionChart && typeof window.predictionChart.destroy === 'function') {
                window.predictionChart.destroy();
            }
            
            // Create multi-semester prediction chart
            const ctx = document.getElementById('predictionChart').getContext('2d');
            
            // Prepare data for chart
            const labels = [];
            const predictedData = [];
            const lowerBoundData = [];
            const upperBoundData = [];
            
            predictions.forEach((semesterData, index) => {
                semesterData.predictions.forEach(prediction => {
                    labels.push(`${prediction.hostel_name} - Sem ${semesterData.semester}`);
                    // Round final prediction up to nearest integer for chart
                    const roundedFinalPrediction = Math.ceil(prediction.predicted_demand);
                    predictedData.push(roundedFinalPrediction);
                    lowerBoundData.push(prediction.lower_bound);
                    upperBoundData.push(prediction.upper_bound);
                });
            });
            
            window.predictionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Predicted Demand',
                        data: predictedData,
                        backgroundColor: 'rgba(54, 162, 235, 0.8)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Lower Bound (95% CI)',
                        data: lowerBoundData,
                        backgroundColor: 'rgba(255, 99, 132, 0.5)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }, {
                        label: 'Upper Bound (95% CI)',
                        data: upperBoundData,
                        backgroundColor: 'rgba(75, 192, 192, 0.5)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: `Multi-Semester ML Predictions (R² = ${modelMetrics.r_squared})`,
                            font: {
                                size: 16,
                                family: 'Arial'
                            }
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: 'Arial'
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Students',
                                font: {
                                    family: 'Arial'
                                }
                            }
                        }
                    }
                }
            });
            
            // Populate statistics with confidence intervals
            const statsContainer = document.getElementById('predictionStats');
            statsContainer.innerHTML = '';
            
            // Add model metrics card
            const metricsCard = document.createElement('div');
            metricsCard.className = 'stat-card prediction-card';
            metricsCard.innerHTML = `
                <div class="stat-title">Model Performance</div>
                <div class="stat-value">R² = ${modelMetrics.r_squared}</div>
                <div class="stat-subtitle">Standard Error: ${parseFloat(modelMetrics.standard_error).toFixed(2)}</div>
                <div class="stat-subtitle">Confidence Level: ${(modelMetrics.confidence_level * 100).toFixed(0)}%</div>
            `;
            statsContainer.appendChild(metricsCard);
            
            // Add prediction cards for each semester
            predictions.forEach((semesterData, index) => {
                semesterData.predictions.forEach(prediction => {
                    const statCard = document.createElement('div');
                    statCard.className = 'stat-card factors-card';
                    statCard.innerHTML = `
                        <div class="stat-title">${prediction.hostel_name} - Sem ${semesterData.semester}</div>
                        <div class="stat-value">${Math.ceil(prediction.predicted_demand)}</div>
                        <div class="stat-subtitle">95% CI: ${prediction.lower_bound} - ${prediction.upper_bound}</div>
                        <div class="stat-subtitle">±${prediction.confidence_interval} students</div>
                    `;
                    statsContainer.appendChild(statCard);
                });
            });
            
            // Populate advanced prediction table
            const tableBody = document.getElementById('advancedPredictionTable').querySelector('tbody');
            tableBody.innerHTML = '';
            
            predictions.forEach((semesterData, index) => {
                semesterData.predictions.forEach(prediction => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${prediction.hostel_name}</td>
                        <td>Semester ${semesterData.semester} ${semesterData.year}</td>
                        <td>${Math.ceil(prediction.predicted_demand)}</td>
                        <td>${prediction.lower_bound}</td>
                        <td>${prediction.upper_bound}</td>
                        <td>±${prediction.confidence_interval}</td>
                        <td>
                            <strong>${prediction.rooms_needed || 0} rooms</strong><br>
                            <small>Current: ${prediction.current_rooms || 0} rooms</small><br>
                            <small style="color: ${(prediction.room_gap || 0) > 0 ? 'red' : (prediction.room_gap || 0) < 0 ? 'green' : 'black'};">
                                Gap: ${(prediction.room_gap || 0) > 0 ? '+' : ''}${prediction.room_gap || 0}
                            </small><br>
                            <small style="color: #666;">
                                Severe: ${prediction.estimated_severe_students || 0} (${prediction.severe_percentage || 0}%)
                            </small>
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            });
        }

        function displayPredictions(predictions, currentSemester) {
            // Show results section
            document.getElementById('predictionResults').style.display = 'block';
            
            // Show simple prediction table and hide advanced prediction table
            document.getElementById('predictionTable').style.display = 'table';
            document.getElementById('advancedPredictionTable').style.display = 'none';
            
            // Show prediction information
            document.getElementById('predictionInfo').style.display = 'block';
            
            // Show data source information
            let dataSourceText = 'Combined CSV dataset';
            if (predictions.length > 0 && predictions[0].severe_chronic_students === 0) {
                dataSourceText = 'CSV file data (using fallback from same academic year)';
            }
            document.getElementById('dataSourceInfo').textContent = dataSourceText;
            
            // Destroy existing chart if it exists
            if (window.predictionChart && typeof window.predictionChart.destroy === 'function') {
                window.predictionChart.destroy();
            }
            
            // Create prediction chart
            const ctx = document.getElementById('predictionChart').getContext('2d');
            
            // Define colors for hostels
            const hostelColors = {
                'KOLEJ KEDIAMAN LEKIU (MALE)': 'rgb(145, 198, 54)',
                'KOLEJ KEDIAMAN LEKIU (FEMALE)': 'rgb(114, 197, 122)'
            };
            
            window.predictionChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: predictions.map(p => p.hostel_name),
                    datasets: [{
                        label: 'Predicted Demand',
                        data: predictions.map(p => Math.ceil(p.predicted_demand)),
                        backgroundColor: predictions.map(p => hostelColors[p.hostel_name] || 'rgb(128, 128, 128)'),
                        borderColor: predictions.map(p => hostelColors[p.hostel_name] || 'rgb(128, 128, 128)'),
                        borderWidth: 1
                    }, {
                        label: 'Current Occupancy',
                        data: predictions.map(p => p.current_occupancy),
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: 'ML Predicted Demand vs Current Occupancy',
                            font: {
                                size: 16,
                                family: 'Arial'
                            }
                        },
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: 'Arial'
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Students',
                                font: {
                                    family: 'Arial'
                                }
                            }
                        }
                    }
                }
            });
            
            // Populate statistics
            const statsContainer = document.getElementById('predictionStats');
            statsContainer.innerHTML = '';
            
            predictions.forEach(prediction => {
                // Round final prediction up to nearest integer
                const roundedFinalPrediction = Math.ceil(prediction.predicted_demand);
                
                // Round ML prediction up to nearest integer
                const roundedMlPrediction = Math.ceil(prediction.ml_prediction);
                const mlDemandChange = roundedMlPrediction - prediction.current_occupancy;
                const roundedMlDemandChange = parseFloat(mlDemandChange.toFixed(2));
                const mlChangePercent = prediction.current_occupancy > 0 ? 
                    ((mlDemandChange / prediction.current_occupancy) * 100).toFixed(1) : 0;
                
                const historicalDemandChange = prediction.historical_prediction - prediction.current_occupancy;
                const roundedHistoricalDemandChange = parseFloat(historicalDemandChange.toFixed(2));
                const historicalChangePercent = prediction.current_occupancy > 0 ? 
                    ((historicalDemandChange / prediction.current_occupancy) * 100).toFixed(1) : 0;
                
                const statCard = document.createElement('div');
                statCard.className = 'stat-card factors-card';
                statCard.innerHTML = `
                    <div class="stat-title">${prediction.hostel_name}</div>
                    <div class="stat-value">${roundedFinalPrediction}</div>
                    <div class="stat-subtitle">Final Prediction</div>
                    <div class="stat-subtitle" style="color: #007bff;">
                        Historical: ${prediction.historical_prediction}
                    </div>
                    <div class="stat-subtitle">
                        ML Change: ${roundedMlDemandChange > 0 ? '+' : ''}${roundedMlDemandChange} (${mlChangePercent}%)
                    </div>
                    <div class="stat-subtitle" style="color: #007bff;">
                        Hist Change: ${roundedHistoricalDemandChange > 0 ? '+' : ''}${roundedHistoricalDemandChange} (${historicalChangePercent}%)
                    </div>
                    <div class="stat-details">
                        <small>Returning: ${prediction.returning_students || 0}</small>
                        <br><small style="color: #888;">Base: ${prediction.current_occupancy || 0}</small>
                    </div>
                `;
                statsContainer.appendChild(statCard);
            });
            
            // Populate detailed table
            const tableBody = document.getElementById('predictionTable').querySelector('tbody');
            tableBody.innerHTML = '';
            
            predictions.forEach(prediction => {
                // Round final prediction up to nearest integer
                const roundedFinalPrediction = Math.ceil(prediction.predicted_demand);
                
                // Round ML prediction up to nearest integer
                const roundedMlPrediction = Math.ceil(prediction.ml_prediction);
                const mlDemandChange = roundedMlPrediction - prediction.current_occupancy;
                const roundedMlDemandChange = parseFloat(mlDemandChange.toFixed(2));
                const mlChangePercent = prediction.current_occupancy > 0 ? 
                    ((mlDemandChange / prediction.current_occupancy) * 100).toFixed(1) : 0;
                
                const historicalDemandChange = prediction.historical_prediction - prediction.current_occupancy;
                const roundedHistoricalDemandChange = parseFloat(historicalDemandChange.toFixed(2));
                const historicalChangePercent = prediction.current_occupancy > 0 ? 
                    ((historicalDemandChange / prediction.current_occupancy) * 100).toFixed(1) : 0;
                
                const finalDemandChange = roundedFinalPrediction - prediction.current_occupancy;
                const roundedFinalDemandChange = parseFloat(finalDemandChange.toFixed(2));
                const finalChangePercent = prediction.current_occupancy > 0 ? 
                    ((finalDemandChange / prediction.current_occupancy) * 100).toFixed(1) : 0;
                
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${prediction.hostel_name}</td>
                    <td>Semester ${prediction.semester} ${prediction.display_year || prediction.year}</td>
                    <td>${roundedFinalPrediction}</td>
                    <td>${roundedMlPrediction}</td>
                    <td>${prediction.historical_prediction}</td>
                    <td>${prediction.current_occupancy}</td>
                    <td>${prediction.severe_chronic_students}</td>
                    <td>${prediction.room_full_rejections}</td>
                    <td>${prediction.returning_students || 0}</td>
                    <td>
                        <strong>${prediction.rooms_needed || 0} rooms</strong><br>
                        <small>Current: ${prediction.current_rooms || 0} rooms</small><br>
                        <small style="color: ${(prediction.room_gap || 0) > 0 ? 'red' : (prediction.room_gap || 0) < 0 ? 'green' : 'black'};">
                            Gap: ${(prediction.room_gap || 0) > 0 ? '+' : ''}${prediction.room_gap || 0}
                        </small><br>
                        <small style="color: #666;">
                            Severe: ${prediction.estimated_severe_students || 0} (${prediction.severe_percentage || 0}%)
                        </small>
                    </td>
                    <td style="color: ${roundedFinalDemandChange > 0 ? 'green' : roundedFinalDemandChange < 0 ? 'red' : 'black'};">
                        Final: ${roundedFinalDemandChange > 0 ? '+' : ''}${roundedFinalDemandChange} (${finalChangePercent}%)<br>
                        <small style="color: #007bff;">
                            ML: ${roundedMlDemandChange > 0 ? '+' : ''}${roundedMlDemandChange} (${mlChangePercent}%) | Hist: ${roundedHistoricalDemandChange > 0 ? '+' : ''}${roundedHistoricalDemandChange} (${historicalChangePercent}%)
                        </small>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        }

        // File upload handling
        document.getElementById('csvFile').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const label = document.querySelector('.file-upload-label');
            const uploadBtn = document.getElementById('uploadBtn');
            
            if (file) {
                label.innerHTML = '<i class="fas fa-check"></i> ' + file.name;
                label.style.backgroundColor = '#28a745';
                
                // Change upload button to submit form
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Data';
                uploadBtn.onclick = function() {
                    document.getElementById('uploadForm').submit();
                };
            } else {
                label.innerHTML = '<i class="fas fa-cloud-upload-alt"></i> Choose CSV file';
                label.style.backgroundColor = '';
                
                // Reset upload button to file selection
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Data';
                uploadBtn.onclick = function() {
                    document.getElementById('csvFile').click();
                };
            }
        });
    </script>
</body>
</html> 