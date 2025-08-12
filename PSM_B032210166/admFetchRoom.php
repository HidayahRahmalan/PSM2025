<?php
// Initialize variables
$rooms = [];
$totalResults = 0;

try {
    // Build the SQL query based on search criteria
    $sql = "SELECT * FROM ROOM";
    $params = [];
    $types = "";
    
    // Add base WHERE clause for hostel ID
    if ($hostID) {
        $sql .= " WHERE HostID = ?";
        $params[] = $hostID;
        $types .= "s";
        
        // Add additional search criteria if provided
        if ($searchCriteria !== 'All' && !empty($searchValue)) {
            $sql .= " AND ";
            
            switch ($searchCriteria) {
                case 'RoomID':
                    $sql .= "RoomID = ?";
                    $params[] = $searchValue;
                    $types .= "s";
                    break;
                    
                case 'RoomNo':
                    $sql .= "RoomNo LIKE ?";
                    $params[] = "%$searchValue%";
                    $types .= "s";
                    break;
                    
                case 'FloorNo':
                    $sql .= "FloorNo = ?";
                    $params[] = $searchValue;
                    $types .= "i";
                    break;
                    
                case 'Type':
                    $sql .= "Type = ?";
                    $params[] = $searchValue;
                    $types .= "s";
                    break;
                    
                case 'Capacity':
                    $sql .= "Capacity = ?";
                    $params[] = $searchValue;
                    $types .= "i";
                    break;
                    
                case 'CurrentOccupancy':
                    $sql .= "CurrentOccupancy = ?";
                    $params[] = $searchValue;
                    $types .= "i";
                    break;
                    
                case 'Availability':
                    $sql .= "Availability = ?";
                    $params[] = $searchValue;
                    $types .= "s";
                    break;
                    
                case 'Status':
                    $sql .= "Status = ?";
                    $params[] = $searchValue;
                    $types .= "s";
                    break;
            }
        }
    } else {
        // If no hostel ID provided, return empty results
        return;
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
        $rooms[] = $row;
    }
    
    // Count total results
    $totalResults = count($rooms);
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching rooms: " . $e->getMessage());
}
?> 