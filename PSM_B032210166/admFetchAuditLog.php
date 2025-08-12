<?php
// This file is included by admViewAuditLog.php to fetch audit logs based on search criteria

// Initialize the audit logs array
$auditLogs = [];

// Get search parameters from the URL
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'LogID';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';

// Validate sort order to prevent SQL injection
if ($sortOrder !== 'ASC' && $sortOrder !== 'DESC') {
    $sortOrder = 'DESC';
}

// Validate sort by to prevent SQL injection
$allowedSortColumns = ['LogID', 'UserID', 'FullName', 'TimeStamp'];
if (!in_array($sortBy, $allowedSortColumns)) {
    $sortBy = 'LogID';
}

// Build the SQL query based on the search criteria
$sql = "SELECT a.* FROM AUDIT_LOG a";
$params = [];
$types = "";

// If sorting by FullName, we need to join with STUDENT and EMPLOYEE tables
if ($sortBy === 'FullName') {
    $sql = "SELECT a.*, 
            CASE 
                WHEN a.UserID LIKE 'S%' THEN s.FullName 
                WHEN a.UserID LIKE 'E%' THEN e.FullName 
                ELSE NULL 
            END AS FullName 
            FROM AUDIT_LOG a 
            LEFT JOIN STUDENT s ON a.UserID = s.StudID 
            LEFT JOIN EMPLOYEE e ON a.UserID = e.EmpID";
}

// Add WHERE clause based on search criteria
if ($searchCriteria !== 'All' && !empty($searchValue)) {
    switch ($searchCriteria) {
        case 'UserID':
            $sql .= " WHERE a.UserID = ?";
            $params[] = $searchValue;
            $types .= "s";
            break;
            
        case 'FullName':
            // For FullName search, we need to join with STUDENT or EMPLOYEE table based on UserID prefix
            if ($sortBy !== 'FullName') {
                $sql = "SELECT a.*, 
                        CASE 
                            WHEN a.UserID LIKE 'S%' THEN s.FullName 
                            WHEN a.UserID LIKE 'E%' THEN e.FullName 
                            ELSE NULL 
                        END AS FullName 
                        FROM AUDIT_LOG a 
                        LEFT JOIN STUDENT s ON a.UserID = s.StudID 
                        LEFT JOIN EMPLOYEE e ON a.UserID = e.EmpID";
            }
            $sql .= " WHERE UPPER(CASE 
                        WHEN a.UserID LIKE 'S%' THEN s.FullName 
                        WHEN a.UserID LIKE 'E%' THEN e.FullName 
                        ELSE NULL 
                    END) LIKE ?";
            $params[] = "%" . strtoupper($searchValue) . "%";
            $types .= "s";
            break;
            
        case 'UserRole':
            $sql .= " WHERE a.UserRole = ?";
            $params[] = $searchValue;
            $types .= "s";
            break;
            
        case 'Action':
            $sql .= " WHERE a.Action = ?";
            $params[] = $searchValue;
            $types .= "s";
            break;
            
        case 'Status':
            $sql .= " WHERE a.Status = ?";
            $params[] = $searchValue;
            $types .= "s";
            break;
    }
}

// Add ORDER BY clause
if ($sortBy === 'FullName') {
    // For FullName sorting, we'll handle the unknown entries separately in PHP
    $sql .= " ORDER BY FullName " . $sortOrder;
} else {
    $sql .= " ORDER BY a." . $sortBy . " " . $sortOrder;
}

// Initialize total results counter
$totalResults = 0;

// Prepare and execute the query
try {
    $stmt = $conn->prepare($sql);
    
    // Bind parameters if any
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch all audit logs
    while ($row = $result->fetch_assoc()) {
        // If FullName is not directly in the result (for UserID search), fetch it
        if (!isset($row['FullName']) || $row['FullName'] === null) {
            $userId = $row['UserID'];
            
            // Determine which table to query based on UserID prefix
            // Check S is at position index 0 or not
            if (strpos($userId, 'S') === 0) {
                // Student
                $nameStmt = $conn->prepare("SELECT FullName FROM STUDENT WHERE StudID = ?");
                $nameStmt->bind_param("s", $userId);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                
                if ($nameResult->num_rows > 0) {
                    $nameRow = $nameResult->fetch_assoc();
                    $row['FullName'] = $nameRow['FullName'];
                    $row['SortOrder'] = 0; // Regular entries have sort order 0
                } else {
                    $row['FullName'] = "ZZZ_UNKNOWN_STUDENT"; // Prefix with ZZZ to ensure it sorts to the end
                    $row['SortOrder'] = 2; // Use a numeric sort order for consistent sorting
                }
                
                $nameStmt->close();
            } elseif (strpos($userId, 'E') === 0) {
                // Employee (Hostel Staff or Admin)
                $nameStmt = $conn->prepare("SELECT FullName FROM EMPLOYEE WHERE EmpID = ?");
                $nameStmt->bind_param("s", $userId);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                
                if ($nameResult->num_rows > 0) {
                    $nameRow = $nameResult->fetch_assoc();
                    $row['FullName'] = $nameRow['FullName'];
                    $row['SortOrder'] = 0; // Regular entries have sort order 0
                } else {
                    $row['FullName'] = "ZZZ_UNKNOWN_STAFF"; // Prefix with ZZZ to ensure it sorts to the end
                    $row['SortOrder'] = 1; // Use a numeric sort order for consistent sorting
                }
                
                $nameStmt->close();
            } else {
                $row['FullName'] = "ZZZ_UNKNOWN_USER"; // Prefix with ZZZ to ensure it sorts to the end
                $row['SortOrder'] = 3; // Use a numeric sort order for consistent sorting
            }
        } else {
            // If FullName is already in the result, set SortOrder to 0
            $row['SortOrder'] = 0;
        }
        
        // Format the data for display
        $row['Action'] = ucwords(strtolower($row['Action']));
        $row['Status'] = ucwords(strtolower($row['Status']));
        
        // Add to the audit logs array
        $auditLogs[] = $row;
        $totalResults++;
    }
    
    $stmt->close();
    
    // If sorting by FullName, perform a custom sort to ensure unknown entries are sorted correctly
    if ($sortBy === 'FullName') {
        usort($auditLogs, function($a, $b) use ($sortOrder) {
            // First sort by SortOrder
            if ($a['SortOrder'] !== $b['SortOrder']) {
                return $sortOrder === 'ASC' ? $a['SortOrder'] - $b['SortOrder'] : $b['SortOrder'] - $a['SortOrder'];
            }
            
            // Then sort by FullName
            return $sortOrder === 'ASC' ? 
                strcasecmp($a['FullName'], $b['FullName']) : 
                strcasecmp($b['FullName'], $a['FullName']);
        });
    }
} catch (Exception $e) {
    error_log("Error fetching audit logs: " . $e->getMessage());
    // Set an empty array to avoid errors
    $auditLogs = [];
}
?>

