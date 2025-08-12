<?php
// Initialize variables
$students = [];
$totalResults = 0;

try {
    // Build the SQL query based on search criteria
    $sql = "SELECT StudID, FullName, MatricNo, Gender, Status, Faculty, Year, Semester FROM STUDENT";
    $params = [];
    $types = "";
    
    // Add WHERE clause based on search criteria
    if ($searchCriteria !== 'All' && !empty($searchValue)) {
        $sql .= " WHERE ";
        
        switch ($searchCriteria) {
            case 'StudID':
                $sql .= "StudID = ?";
                $params[] = $searchValue;
                $types .= "s";
                break;
                
            case 'FullName':
                $sql .= "UPPER(FullName) LIKE UPPER(?)";
                $params[] = "%$searchValue%";
                $types .= "s";
                break;
                
            case 'MatricNo':
                $sql .= "UPPER(MatricNo) LIKE UPPER(?)";
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
                
            case 'Faculty':
                $sql .= "Faculty = ?";
                $params[] = $searchValue;
                $types .= "s";
                break;
                
            case 'Year':
                $sql .= "Year = ?";
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
    
    // Fetch all students
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
        $totalResults++;
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching students: " . $e->getMessage());
    // Set an empty array to avoid errors
    $students = [];
}
?> 