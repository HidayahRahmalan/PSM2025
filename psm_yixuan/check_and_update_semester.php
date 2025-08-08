<?php
// Function to check if a new semester has begun and update student academic progress
function checkAndUpdateSemester($conn) {
    try {
        // Check if semester transition flag exists in the system settings
        $stmt = $conn->prepare("
            SELECT SettingValue 
            FROM SYSTEM_SETTINGS 
            WHERE SettingName = 'last_semester_update'
            ORDER BY LastUpdated DESC
            LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Get current active semester
        $currentSemQuery = $conn->prepare("
            SELECT SemID, AcademicYear, Semester 
            FROM SEMESTER 
            WHERE CURDATE() BETWEEN DATE_SUB(CheckInDate, INTERVAL 1 WEEK) AND CheckOutDate
            LIMIT 1");
        $currentSemQuery->execute();
        $currentSemResult = $currentSemQuery->get_result();
        
        // If no active semester found, return early
        if ($currentSemResult->num_rows == 0) {
            return false;
        }
        
        $currentSem = $currentSemResult->fetch_assoc();
        $currentSemID = $currentSem['SemID'];
        
        // If this is the first time running or the semester setting doesn't exist
        if ($result->num_rows == 0) {
            // Create the setting with current semester ID
            $insertStmt = $conn->prepare("
                INSERT INTO SYSTEM_SETTINGS (SettingName, SettingValue, LastUpdated, Description) 
                VALUES ('last_semester_update', ?, NOW(), 'Last semester ID when student academic progress was updated')");
            $insertStmt->bind_param("s", $currentSemID);
            $insertStmt->execute();
            return false; // No need to update on first run
        }
        
        // Get the last updated semester ID
        $row = $result->fetch_assoc();
        $lastSemesterUpdate = $row['SettingValue'];
        
        // If current semester is different from the last recorded one
        if ($lastSemesterUpdate != $currentSemID) {
            // Call the stored procedure to update student academic progress
            $conn->query("CALL update_student_academic_progress()");
            
            // Insert a new record for the last semester update setting
            $insertStmt = $conn->prepare("
                INSERT INTO SYSTEM_SETTINGS (SettingName, SettingValue, LastUpdated, Description) 
                VALUES ('last_semester_update', ?, NOW(), 'Last semester ID when student academic progress was updated')");
            $insertStmt->bind_param("s", $currentSemID);
            $insertStmt->execute();
            
            return true; // Semester updated
        }
        
        return false; // No update needed
    } catch (Exception $e) {
        error_log("Error checking/updating semester: " . $e->getMessage());
        return false;
    }
}

// This function can be called from studHomePage.php or any other appropriate page
?> 