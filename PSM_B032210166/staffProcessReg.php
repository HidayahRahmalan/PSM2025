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
        $email = strtoupper(trim($_POST['email'])); // Convert email to uppercase
        $personalEmail = strtoupper(trim($_POST['personalEmail']));
        $phone = trim($_POST['phone']);
        $gender = trim($_POST['gender']);
        $role = strtoupper(trim($_POST['role'])); // Convert role to uppercase
        $password = trim($_POST['password']);
        
        // Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Prepare SQL statement for insertion
        $stmt = $conn->prepare("INSERT INTO EMPLOYEE (FullName, StaffEmail, PersonalEmail, PhoneNo, Gender, Role, Password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        // Bind parameters
        $stmt->bind_param("sssssss", $fullname, $email, $personalEmail, $phone, $gender, $role, $hashed_password);
        
        // Execute the statement
        if ($stmt->execute()) {
            // Fetch the latest generated EmpID using email
            $query = "SELECT EmpID FROM EMPLOYEE WHERE StaffEmail = ? ORDER BY EmpID DESC LIMIT 1";
            $stmt2 = $conn->prepare($query);
            $stmt2->bind_param("s", $email);
            $stmt2->execute();
            $stmt2->bind_result($empId);
            $stmt2->fetch();
            $stmt2->close();
            
            if (!empty($empId)) {
                // Convert new employee data to JSON
                $new_data = json_encode([
                    "Name" => $fullname,
                    "StaffEmail" => $email,
                    "PersonalEmail" => $personalEmail,
                    "Phone" => $phone,
                    "Gender" => $gender,
                    "Role" => $role
                ]);

                // Call the add_audit_trail procedure
                $audit_stmt = $conn->prepare("CALL add_audit_trail(?, ?, ?, ?, ?, ?)");
                $table_name = "EMPLOYEE";
                $action = "INSERT";
                $old_data = null; // No old data for new employee insert

                $audit_stmt->bind_param("ssssss", $table_name, $empId, $action, $empId, $old_data, $new_data);
                $audit_stmt->execute();
                $audit_stmt->close();
                
                // Return success response as JSON
                $response = array(
                    'success' => true,
                    'empId' => $empId,  // Use the generated EmpID
                    'message' => 'Registration successful!'
                );
            } else {
                $response = array(
                    'success' => false,
                    'message' => 'Failed to retrieve generated Employee ID.'
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