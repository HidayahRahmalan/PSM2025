<?php
// Enable error handling but don't display to users
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include database connection
include 'dbConnection.php';

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Get form data and sanitize
        $fullname = strtoupper(trim($_POST['fullname']));
        $matricno = strtoupper(trim($_POST['matricno']));
        $email = strtoupper(trim($_POST['email'])); // Convert email to uppercase
        $personalEmail = strtoupper(trim($_POST['personalEmail'])); // Convert personal email to uppercase
        $phone = trim($_POST['phone']);
        $gender = trim($_POST['gender']);
        $faculty = strtoupper(trim($_POST['faculty'])); // Changed to match the form field name
        $year = trim($_POST['year']);
        $semester = trim($_POST['semester']);
        $password = trim($_POST['password']);
        $roomSharingStyle = strtoupper(trim($_POST['roomSharingStyle'])); // Add room sharing style
        $chronicIssueLevel = strtoupper(trim($_POST['chronicIssueLevel']));
        $chronicIssueName = strtoupper(trim($_POST['chronicIssueName']));
        
        // Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Prepare SQL statement for insertion
        $stmt = $conn->prepare("INSERT INTO STUDENT (FullName, MatricNo, StudEmail, PersonalEmail, PhoneNo, Gender, Faculty, Year, Semester, Password, RoomSharingStyle, ChronicIssueLevel, ChronicIssueName) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Bind parameters
        $stmt->bind_param("sssssssssssss", $fullname, $matricno, $email, $personalEmail, $phone, $gender, $faculty, $year, $semester, $hashed_password, $roomSharingStyle, $chronicIssueLevel, $chronicIssueName);
        
        // Execute the statement
        if ($stmt->execute()) {
            // Fetch the latest generated StudID using MatricNo
            $query = "SELECT StudID FROM STUDENT WHERE MatricNo = ? ORDER BY StudID DESC LIMIT 1";
            $stmt2 = $conn->prepare($query);
            $stmt2->bind_param("s", $matricno);
            $stmt2->execute();
            $stmt2->bind_result($studId);
            $stmt2->fetch();
            $stmt2->close();
            
            if (!empty($studId)) {
                // Convert new student data to JSON
                $new_data = json_encode([
                    "Name" => $fullname,
                    "MatricNo" => $matricno,
                    "StudEmail" => $email,
                    "PersonalEmail" => $personalEmail,
                    "Phone" => $phone,
                    "Gender" => $gender,
                    "Faculty" => $faculty,
                    "Year" => $year,
                    "Semester" => $semester,
                    "RoomSharingStyle" => $roomSharingStyle,
                    "ChronicIssueLevel" => $chronicIssueLevel,
                    "ChronicIssueName" => $chronicIssueName
                ]);

                // Call the add_audit_trail procedure
                $audit_stmt = $conn->prepare("CALL add_audit_trail(?, ?, ?, ?, ?, ?)");
                $table_name = "STUDENT";
                $action = "INSERT";
                $old_data = null; // No old data for new student insert

                $audit_stmt->bind_param("ssssss", $table_name, $studId, $action, $studId, $old_data, $new_data);
                $audit_stmt->execute();
                $audit_stmt->close();
                
                // Return success response as JSON
                $response = array(
                    'success' => true,
                    'studId' => $studId,  // Use the generated StudID
                    'message' => 'Registration successful!'
                );
            } else {
                $response = array(
                    'success' => false,
                    'message' => 'Failed to retrieve generated Student ID.'
                );
            }
        } else {
            // Return error response as JSON
            $response = array(
                'success' => false,
                'message' => 'Registration failed: ' . $stmt->error
            );
        }
        
        // Close statement
        $stmt->close();
        
    } catch (Exception $e) {
        // Return exception response as JSON
        $response = array(
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        );
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response); 
    
    // Close connection
    $conn->close();
} else {
    // If not a POST request, return error
    $response = array(
        'success' => false,
        'message' => 'Invalid request method'
    );
    
    header('Content-Type: application/json');
    echo json_encode($response);
}
?> 