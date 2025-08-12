<?php
// Initialize variables
$employees = [];
$totalResults = 0;

try {
    // Build the base query
    $sql = "SELECT 
                EmpID, FullName, StaffEmail, PersonalEmail, PhoneNo, 
                Gender, Status, Role
            FROM EMPLOYEE
            WHERE Role = 'HOSTEL STAFF'";
    $params = [];
    $types = "";
    
    // Add WHERE clause based on search criteria
    if ($searchCriteria !== 'All' && !empty($searchValue)) {
        $sql .= " AND ";
        
        switch ($searchCriteria) {
            case 'EmpID':
                $sql .= "EmpID = ?";
                $params[] = $searchValue;
                $types .= "s";
                break;
                
            case 'FullName':
                $sql .= "UPPER(FullName) LIKE UPPER(?)";
                $params[] = "%$searchValue%";
                $types .= "s";
                break;
                
            case 'Gender':
                $sql .= "Gender = ?";
                $params[] = $searchValue;
                $types .= "s";
                break;
                
            case 'Status':
                $sql .= "UPPER(Status) = UPPER(?)";
                $params[] = $searchValue;
                $types .= "s";
                break;
        }
    }
    
    // Add ORDER BY clause
    $sql .= " ORDER BY $sortBy $sortOrder";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all results
    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
        $totalResults++;
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching hostel staff data: " . $e->getMessage());
    $employees = [];
    $totalResults = 0;
}
?> 