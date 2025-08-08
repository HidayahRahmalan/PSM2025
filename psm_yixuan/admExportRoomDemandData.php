<?php
// admExportRoomDemandData.php
// Exports room demand data for ML training, using dbConnection.php and dynamic hostel list

// Use your existing DB connection
include 'dbConnection.php';

// Check if this script is being included or run directly
$isIncluded = !defined('STDIN') && !isset($_SERVER['REQUEST_METHOD']);

// Function to output messages only when not included
function safeEcho($message) {
    global $isIncluded;
    if (!$isIncluded) {
        echo $message . "\n";
    }
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
    safeEcho("Current semester found: " . $currentSemRow['AcademicYear'] . " Semester " . $currentSemRow['Semester']);
} else {
    safeEcho("No current semester found for date: $currentDate");
}

// Get all active hostels dynamically
$hostels = [];
$res = $conn->query("SELECT HostID, Name FROM HOSTEL WHERE Status='ACTIVE'");
if (!$res) {
    safeEcho("Error querying hostels: " . $conn->error);
    return;
}
while ($row = $res->fetch_assoc()) {
    $hostels[] = [
        'HostID' => $row['HostID'],
        'Name' => $row['Name']
    ];
}

// Get all academic years and semesters from SEMESTER table, excluding current semester
$years = [];
$semesters = [];
$semesterFilter = "";

if ($currentSemester) {
    // Only get semesters before the current semester
    $semesterFilter = "WHERE (AcademicYear < '" . $currentSemester['AcademicYear'] . "' 
                    OR (AcademicYear = '" . $currentSemester['AcademicYear'] . "' AND Semester < " . $currentSemester['Semester'] . "))";
}

$res = $conn->query("SELECT DISTINCT AcademicYear FROM SEMESTER $semesterFilter ORDER BY AcademicYear ASC");
if (!$res) {
    safeEcho("Error querying academic years: " . $conn->error);
    return;
}
while ($row = $res->fetch_assoc()) {
    $years[] = $row['AcademicYear'];
}

$res = $conn->query("SELECT DISTINCT Semester FROM SEMESTER $semesterFilter ORDER BY Semester ASC");
if (!$res) {
    safeEcho("Error querying semesters: " . $conn->error);
    return;
}
while ($row = $res->fetch_assoc()) {
    $semesters[] = $row['Semester'];
}

safeEcho("Exporting data for " . count($years) . " academic years and " . count($semesters) . " semesters (before current semester)");

// Check if we have any data to export
if (empty($years) || empty($semesters) || empty($hostels)) {
    safeEcho("No data available for export. Please check if you have semesters and hostels configured.");
    return;
}

// Open CSV file for writing
$csvFile = 'admRoomDemandData.csv';
$fp = fopen($csvFile, 'w');
if (!$fp) {
    safeEcho("Failed to create CSV file. Please check permissions.");
    return;
}
fputcsv($fp, [
    "Semester", "Year", "Hostel",
    "Total_Severe_Chronic_Students", "Booked_Severe_Chronic_Students", "Room_Full_Rejections",
    "Unbooked_Severe_Chronic_Students", "Graduating_Students",
    "Current_Occupancy", "Actual_Demand"
]);

foreach ($years as $year) {
    foreach ($semesters as $sem) {
        foreach ($hostels as $hostel) {
            $hostelID = $hostel['HostID'];
            $hostelName = $hostel['Name'];

            // Get SemID
            $semRes = $conn->query("SELECT SemID FROM SEMESTER WHERE AcademicYear='" . $conn->real_escape_string($year) . "' AND Semester=$sem LIMIT 1");
            $semRow = $semRes->fetch_assoc();
            $semID = $semRow ? $semRow['SemID'] : null;
            if (!$semID) continue;

            // Total Severe Chronic Students (with bookings in this hostel/semester OR unbooked assigned to this hostel by gender)
            $q1 = $conn->query("SELECT COUNT(DISTINCT s.StudID) as cnt
                FROM STUDENT s
                WHERE s.ChronicIssueLevel='SEVERE'
                AND (
                    -- Students with bookings in this hostel/semester
                    EXISTS (
                        SELECT 1 FROM BOOKING b
                        JOIN ROOM r ON b.RoomID = r.RoomID
                        WHERE b.StudID = s.StudID AND r.HostID='$hostelID' AND b.SemID='$semID'
                    )
                    OR
                    -- Students with no bookings at all (unassigned) - assign to first hostel of their gender
                    (
                        NOT EXISTS (
                            SELECT 1 FROM BOOKING b
                            WHERE b.StudID = s.StudID AND b.SemID='$semID'
                        )
                        AND (
                            (s.Gender = 'M' AND '$hostelName' LIKE '%(MALE)%')
                            OR
                            (s.Gender = 'F' AND '$hostelName' LIKE '%(FEMALE)%')
                        )
                    )
                )");
            $total_severe = $q1->fetch_assoc()['cnt'];

            // Booked Severe Chronic Students (severe chronic students with approved bookings in this hostel/semester)
            $q1b = $conn->query("SELECT COUNT(DISTINCT s.StudID) as cnt
                FROM STUDENT s
                JOIN BOOKING b ON s.StudID = b.StudID
                JOIN ROOM r ON b.RoomID = r.RoomID
                WHERE s.ChronicIssueLevel='SEVERE'
                AND b.Status='APPROVED' AND r.HostID='$hostelID' AND b.SemID='$semID'");
            $booked_severe = $q1b->fetch_assoc()['cnt'];

            // Room Full Rejections
            $q2 = $conn->query("SELECT COUNT(*) as cnt FROM BOOKING b
                JOIN ROOM r ON b.RoomID = r.RoomID
                WHERE b.Status='REJECTED' AND b.RejectedReason='ROOM FULL' AND r.HostID='$hostelID' AND b.SemID='$semID'");
            $room_full_rej = $q2->fetch_assoc()['cnt'];

            // Unbooked Severe Chronic Students (severe, not booked in this hostel/semester, assigned by gender)
            // Only count students with no bookings at all for this semester
            // Since severe chronic students are locked to their first hostel, they cannot apply to other hostels
            // Exclude students who have EVER been approved, include students who applied but were rejected
            $q3 = $conn->query("SELECT COUNT(DISTINCT s.StudID) as cnt
                FROM STUDENT s
                WHERE s.ChronicIssueLevel='SEVERE'
                AND NOT EXISTS (
                    SELECT 1 FROM BOOKING b
                    WHERE b.StudID = s.StudID AND b.SemID='$semID' AND b.Status='APPROVED'
                )
                AND (
                    (s.Gender = 'M' AND '$hostelName' LIKE '%(MALE)%')
                    OR
                    (s.Gender = 'F' AND '$hostelName' LIKE '%(FEMALE)%')
                )");
            $unbooked_severe = $q3->fetch_assoc()['cnt'];

            // Graduating Students - Calculate what student's year/semester was during historical period
            // Get current semester info for calculation
            $currentSemQuery = $conn->query("
                SELECT AcademicYear, Semester, CheckInDate 
                FROM SEMESTER 
                WHERE CURDATE() BETWEEN CheckInDate AND CheckOutDate 
                LIMIT 1
            ");
            $currentSemInfo = $currentSemQuery ? $currentSemQuery->fetch_assoc() : null;
            
            // Get historical semester info
            $histSemQuery = $conn->query("
                SELECT AcademicYear, Semester, CheckInDate 
                FROM SEMESTER 
                WHERE SemID = '$semID'
            ");
            $histSemInfo = $histSemQuery ? $histSemQuery->fetch_assoc() : null;
            
            $graduating = 0;
            if ($currentSemInfo && $histSemInfo) {
                // Calculate semester difference
                $currentYear = explode('/', $currentSemInfo['AcademicYear'])[0];
                $histYear = explode('/', $histSemInfo['AcademicYear'])[0];
                $yearDiff = intval($currentYear) - intval($histYear);
                $semesterDiff = ($yearDiff * 3) + ($currentSemInfo['Semester'] - $histSemInfo['Semester']);
                
                // Calculate complete years and remaining semesters
                $yearsProgressed = floor($semesterDiff / 3);      // Complete years only
                $semestersProgressed = $semesterDiff % 3;         // Remaining semesters
                
                // Calculate what students' year/semester was during historical period (ALL students, not just booked)
                $q4 = $conn->query("
                    SELECT COUNT(DISTINCT s.StudID) as cnt
                    FROM STUDENT s
                    WHERE (
                        -- Bachelor students: historical Year 4, Semester 3
                        (s.MatricNo LIKE 'B%' AND 
                         (s.Year - $yearsProgressed) = 4 AND 
                         (s.Semester - $semestersProgressed) = 3)
                        OR
                        -- Diploma students: historical Year 2, Semester 3  
                        (s.MatricNo LIKE 'D%' AND 
                         (s.Year - $yearsProgressed) = 2 AND 
                         (s.Semester - $semestersProgressed) = 3)
                    )
                    AND (s.Year - $yearsProgressed) > 0 
                    AND (s.Semester - $semestersProgressed) > 0
                ");
                $graduating = $q4 ? $q4->fetch_assoc()['cnt'] : 0;
            }

            // Current Occupancy (approved bookings)
            $q5 = $conn->query("SELECT COUNT(DISTINCT b.StudID) as cnt
                FROM BOOKING b
                JOIN ROOM r ON b.RoomID = r.RoomID
                WHERE b.Status='APPROVED' AND r.HostID='$hostelID' AND b.SemID='$semID'");
            $current_occupancy = $q5->fetch_assoc()['cnt'];

            // Calculate actual demand without duplicates
            $q_actual = $conn->query("
                SELECT COUNT(DISTINCT x.StudID) as cnt FROM (
                    -- All students who got approved booking for this hostel/semester
                    SELECT b.StudID
                    FROM BOOKING b
                    JOIN ROOM r ON b.RoomID = r.RoomID
                    WHERE b.Status = 'APPROVED' AND r.HostID = '$hostelID' AND b.SemID = '$semID'
                    UNION
                    -- All students who were rejected due to room full for this hostel/semester
                    SELECT b.StudID
                    FROM BOOKING b
                    JOIN ROOM r ON b.RoomID = r.RoomID
                    WHERE b.Status = 'REJECTED' AND b.RejectedReason = 'ROOM FULL' AND r.HostID = '$hostelID' AND b.SemID = '$semID'
                    UNION
                    -- All severe chronic students (both booked and unbooked) for this hostel/semester
                    SELECT s.StudID
                    FROM STUDENT s
                    WHERE s.ChronicIssueLevel = 'SEVERE'
                        AND (
                            -- Students with approved bookings in this hostel/semester
                            EXISTS (
                                SELECT 1 FROM BOOKING b
                                JOIN ROOM r ON b.RoomID = r.RoomID
                                WHERE b.StudID = s.StudID AND b.Status = 'APPROVED' AND r.HostID = '$hostelID' AND b.SemID = '$semID'
                            )
                            OR
                            -- Students with no approved bookings (unbooked severe chronic) assigned to this hostel by gender
                            (
                                NOT EXISTS (
                                    SELECT 1 FROM BOOKING b
                                    WHERE b.StudID = s.StudID AND b.SemID = '$semID' AND b.Status = 'APPROVED'
                                )
                                AND (
                                    (s.Gender = 'M' AND '$hostelName' LIKE '%(MALE)%')
                                    OR
                                    (s.Gender = 'F' AND '$hostelName' LIKE '%(FEMALE)%')
                                )
                            )
                        )
                    UNION
                    -- All graduating students (using historical calculation) with approved bookings for this hostel/semester
                    SELECT s.StudID
                    FROM STUDENT s
                    JOIN BOOKING b ON s.StudID = b.StudID
                    JOIN ROOM r ON b.RoomID = r.RoomID
                    WHERE (
                        -- Bachelor students: historical Year 4, Semester 3
                        (s.MatricNo LIKE 'B%' AND 
                         (s.Year - $yearsProgressed) = 4 AND 
                         (s.Semester - $semestersProgressed) = 3)
                        OR
                        -- Diploma students: historical Year 2, Semester 3  
                        (s.MatricNo LIKE 'D%' AND 
                         (s.Year - $yearsProgressed) = 2 AND 
                         (s.Semester - $semestersProgressed) = 3)
                    )
                    AND (s.Year - $yearsProgressed) > 0 
                    AND (s.Semester - $semestersProgressed) > 0
                    AND b.Status = 'APPROVED' AND r.HostID = '$hostelID' AND b.SemID = '$semID'
                ) x
            ");
            $actual_demand = $q_actual->fetch_assoc()['cnt'];

            // Note: Graduating students are now explicitly included in the UNION above
            // DISTINCT ensures no duplicates are counted

            // Convert hostel name to uppercase for consistency
            $hostelName = strtoupper(trim($hostelName));
            
            // Write row
            fputcsv($fp, [
                $sem, $year, $hostelName, $total_severe, $booked_severe, $room_full_rej,
                $unbooked_severe, $graduating, $current_occupancy, $actual_demand
            ]);
        }
    }
}
fclose($fp);

// Check if file was created and has content
if (!file_exists($csvFile) || filesize($csvFile) === 0) {
    safeEcho("Failed to create CSV file or file is empty.");
    return;
}

safeEcho("Exported to $csvFile");
?> 