<?php
// Initialize variables
$hostels = [];
$totalResults = 0;

try {
    // Build the SQL query based on search criteria
    $sql = "SELECT * FROM HOSTEL";
    $params = [];
    $types = "";
    
    // Add WHERE clause based on search criteria
    if ($searchCriteria !== 'All' && !empty($searchValue)) {
        $sql .= " WHERE ";
        
        switch ($searchCriteria) {
            case 'HostID':
                $sql .= "HostID = ?";
                $params[] = $searchValue;
                $types .= "s";
                break;
                
            case 'Name':
                $sql .= "UPPER(Name) LIKE UPPER(?)";
                $params[] = "%$searchValue%";
                $types .= "s";
                break;
                
            case 'Location':
                $sql .= "UPPER(Location) LIKE UPPER(?)";
                $params[] = "%$searchValue%";
                $types .= "s";
                break;
                
            case 'TotalFloor':
                $sql .= "TotalFloor = ?";
                $params[] = $searchValue;
                $types .= "i";
                break;
                
            case 'Status':
                $sql .= "Status = ?";
                $params[] = $searchValue;
                $types .= "s";
                break;
        }
    }
    
    // Add ORDER BY clause
    $sql .= " ORDER BY $sortBy $sortOrder";
    
    // Prepare and execute the query
    $stmt = $conn->prepare($sql);
    
    // If we have parameters, bind them
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Process the results
    while ($row = $result->fetch_assoc()) {
        $hostels[] = $row;
    }
    
    // Count total results
    $totalResults = count($hostels);
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching hostels: " . $e->getMessage());
}
?> 