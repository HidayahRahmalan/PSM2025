<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    header("Location: staffMainPage.php");
    exit();
}

// Get admin name
$adminName = "";
try {
    $stmt = $conn->prepare("SELECT FullName FROM EMPLOYEE WHERE EmpID = ? AND Role = 'ADMIN'");
    $stmt->bind_param("s", $_SESSION['empId']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $adminName = $row['FullName'];
    } else {
        // Not an admin, redirect
        header("Location: staffMainPage.php");
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting admin data: " . $e->getMessage());
}

// Get all unique UserIDs from AUDIT_TRAIL
$userIds = [];
try {
    //Get the district userID to be the drop down list option
    $stmt = $conn->prepare("SELECT DISTINCT UserID FROM AUDIT_TRAIL ORDER BY UserID");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $userIds[] = $row['UserID'];
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting UserIDs: " . $e->getMessage());
}

// Get all unique TableName from AUDIT_TRAIL
$tableNames = [];
try {
    //Get the district userID to be the drop down list option
    $stmt = $conn->prepare("SELECT DISTINCT TableName FROM AUDIT_TRAIL ORDER BY UserID");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $tableNames[] = $row['TableName'];
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting Table Names: " . $e->getMessage());
}

// Get search parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'TrailID';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';

// Initialize audit trails array
$auditTrails = [];

// Always fetch results on initial load or when search is performed
include 'admFetchAuditTrail.php';

// Get unique actions for the dropdown
$actions = [];
$actionQuery = "SELECT DISTINCT Action FROM AUDIT_TRAIL ORDER BY Action";
$actionResult = $conn->query($actionQuery);
if ($actionResult) {
    while ($row = $actionResult->fetch_assoc()) {
        $actions[] = $row['Action'];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin View Audit Trail - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/adminNav.css">
    <style>
        :root {
            --primary-color: #25408f;
            --secondary-color: #3883ce;
            --accent-color: #2c9dff;
            --light-bg: #f0f8ff;
            --white: #ffffff;
            --text-dark: #333333;
            --text-light: #666666;
            --border-color: #ddd;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background-color: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Header and Navigation */
        header {
            background-color: var(--white);
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 50px;
            margin-right: 10px;
        }
        
        .logo h1 {
            color: var(--primary-color);
            font-size: 24px;
        }
        
        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-links a {
            color: var(--text-dark);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s ease;
        }
        
        .nav-links a:hover, .nav-links a.active {
            color: var(--accent-color);
        }
        
        .profile-icon {
            cursor: pointer;
            position: relative;
        }
        
        .profile-icon img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }
        
        /* Page Header */
        .page-header {
            text-align: center;
            margin: 30px 0;
        }
        
        .page-header h2 {
            color: var(--primary-color);
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            color: var(--text-light);
            font-size: 16px;
        }
        
        /* Search Form */
        .search-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--text-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(44, 157, 255, 0.2);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: var(--text-dark);
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        /* Results Table */
        .results-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        /* Table column widths */
        .table th:nth-child(1), .table td:nth-child(1) { width: 8%; }  /* Trail ID */
        .table th:nth-child(2), .table td:nth-child(2) { width: 10%; } /* Table Name */
        .table th:nth-child(3), .table td:nth-child(3) { width: 8%; }  /* Record ID */
        .table th:nth-child(4), .table td:nth-child(4) { width: 8%; }  /* Action */
        .table th:nth-child(5), .table td:nth-child(5) { width: 10%; } /* User ID */
        .table th:nth-child(6), .table td:nth-child(6) { width: 15%; } /* Full Name */
        .table th:nth-child(7), .table td:nth-child(7) { width: 15%; } /* Old Data */
        .table th:nth-child(8), .table td:nth-child(8) { width: 15%; } /* New Data */
        .table th:nth-child(9), .table td:nth-child(9) { width: 11%; } /* Time Stamp */
        
        /* Data cell styling */
        .table td {
            padding: 8px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
            white-space: normal;
            word-wrap: break-word;
            vertical-align: top;
        }
        
        .table th {
            padding: 8px 10px;
            background-color: var(--light-bg);
            color: var(--primary-color);
            font-weight: bold;
            font-size: 14px;
            white-space: nowrap;
        }
        
        .table tr:hover {
            background-color: rgba(44, 157, 255, 0.05);
        }
        
        /* Add styles for the table container to enable horizontal scrolling */
        .table-container {
            margin-bottom: 20px;
            width: 100%;
        }
        
        .table {
            width: 100%;
            table-layout: fixed;
        }
        
        /* Print Styles */
        @media print {
            header, .search-container, .btn-group {
                display: none;
            }
            
            .results-container {
                box-shadow: none;
                padding: 0;
            }
            
            .table th {
                background-color: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .table td, .table th {
                border: 1px solid #ddd;
            }
            
            @page {
                size: landscape;
                margin: 1cm;
            }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
            
            .btn-group {
                width: 100%;
                justify-content: space-between;
            }
            
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .logo h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/adminNav.php'; ?>
    
    <main class="container main-content">
        <section class="page-header">
            <h2>Audit Trails</h2>
        </section>

        <section class="search-container">
            <form action="admViewAuditTrail.php" method="GET" class="search-form">
                <div class="form-group">
                    <label for="searchCriteria">Search By</label>
                    <select id="searchCriteria" name="searchCriteria" class="form-control" onchange="updateSearchField()">
                        <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="UserID" <?php echo $searchCriteria === 'UserID' ? 'selected' : ''; ?>>User ID</option>
                        <option value="FullName" <?php echo $searchCriteria === 'FullName' ? 'selected' : ''; ?>>Full Name</option>
                        <option value="Action" <?php echo $searchCriteria === 'Action' ? 'selected' : ''; ?>>Action</option>
                        <option value="TableName" <?php echo $searchCriteria === 'TableName' ? 'selected' : ''; ?>>Table Name</option>
                    </select>
                </div>
                
                <div class="form-group" id="searchValueContainer">
                    <label for="searchValue" id="searchValueLabel">Search Value</label>
                    <div id="searchValueField">
                        <!-- This will be dynamically updated based on the selected criteria -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="sortBy">Sort By</label>
                    <select id="sortBy" name="sortBy" class="form-control">
                        <option value="TrailID" <?php echo $sortBy === 'TrailID' ? 'selected' : ''; ?>>Trail ID</option>
                        <option value="RecordID" <?php echo $sortBy === 'RecordID' ? 'selected' : ''; ?>>Record ID</option>
                        <option value="UserID" <?php echo $sortBy === 'UserID' ? 'selected' : ''; ?>>User ID</option>
                        <option value="FullName" <?php echo $sortBy === 'FullName' ? 'selected' : ''; ?>>Full Name</option>
                        <option value="TimeStamp" <?php echo $sortBy === 'TimeStamp' ? 'selected' : ''; ?>>Time Stamp</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sortOrder">Sort Order</label>
                    <select id="sortOrder" name="sortOrder" class="form-control">
                        <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                        <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" name="search" class="btn btn-primary">Search</button>
                    <button type="button" class="btn btn-warning" onclick="printReport()">Generate Report</button>
                </div>
            </form>
        </section>

        <section class="results-container">
            <h3 style="margin-bottom: 15px;">Search Results</h3>
            
            <?php if (empty($auditTrails)): ?>
                <p>No audit trails found. Please try a different search criteria.</p>
            <?php else: ?>
                <p class="results-count">Total Results: <?php echo $totalResults; ?></p>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Trail ID</th>
                                <th>Table Name</th>
                                <th>Record ID</th>
                                <th>Action</th>
                                <th>User ID</th>
                                <th>Full Name</th> 
                                <th>Old Data</th>
                                <th>New Data</th>
                                <th>Time Stamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditTrails as $trail): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($trail['TrailID']); ?></td>
                                    <td><?php echo htmlspecialchars($trail['TableName']); ?></td>
                                    <td><?php echo htmlspecialchars($trail['RecordID']); ?></td>
                                    <td><?php echo htmlspecialchars($trail['Action']); ?></td>
                                    <td><?php echo htmlspecialchars($trail['UserID']); ?></td>
                                    <td><?php 
                                        $displayName = $trail['FullName'];
                                        // Remove the ZZZ_ prefix for display purposes
                                        if (strpos($displayName, 'ZZZ_UNKNOWN_') === 0) {
                                            $displayName = str_replace('ZZZ_UNKNOWN_', 'UNKNOWN ', $displayName);
                                        }
                                        echo htmlspecialchars(ucwords($displayName)); 
                                    ?></td>
                                    <td class="data-cell"><?php echo htmlspecialchars($trail['OldData']); ?></td>
                                    <td class="data-cell"><?php echo htmlspecialchars($trail['NewData']); ?></td>
                                    <td><?php echo htmlspecialchars($trail['TimeStamp']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script>
        // Function to update the search field based on the selected criteria
        function updateSearchField() {
            const searchCriteria = document.getElementById('searchCriteria').value;
            const searchValueContainer = document.getElementById('searchValueField');
            const searchValueLabel = document.getElementById('searchValueLabel');
            
            // Clear the current search value field
            searchValueContainer.innerHTML = '';
            
            // Create the appropriate input field based on the selected criteria
            switch (searchCriteria) {
                case 'All':
                    searchValueLabel.textContent = 'No search value needed';
                    //Not visible to the user on the page 
                    searchValueContainer.innerHTML = '<input type="hidden" name="searchValue" value="">';
                    break;
                    
                case 'UserID':
                    searchValueLabel.textContent = 'Select User ID';
                    const userSelect = document.createElement('select');
                    userSelect.name = 'searchValue';
                    userSelect.className = 'form-control';
                    userSelect.innerHTML = '<option value="">Select User ID</option>';
                    
                    <?php foreach ($userIds as $userId): ?>
                        userSelect.innerHTML += '<option value="<?php echo $userId; ?>" <?php echo $searchValue === $userId ? 'selected' : ''; ?>><?php echo $userId; ?></option>';
                    <?php endforeach; ?>
                    
                    searchValueContainer.appendChild(userSelect);
                    break;
                    
                case 'FullName':
                    searchValueLabel.textContent = 'Enter Full Name';
                    const nameInput = document.createElement('input');
                    nameInput.type = 'text';
                    nameInput.name = 'searchValue';
                    nameInput.className = 'form-control';
                    nameInput.value = '<?php echo $searchValue; ?>';
                    nameInput.placeholder = 'Enter full name';
                    searchValueContainer.appendChild(nameInput);
                    break;
                    
                case 'TableName':
                    searchValueLabel.textContent = 'Select Table Name';
                    const tableSelect = document.createElement('select');
                    tableSelect.name = 'searchValue';
                    tableSelect.className = 'form-control';
                    tableSelect.innerHTML = '<option value="">Select Table Name</option>';
                    
                    <?php foreach ($tableNames as $tableName): ?>
                        tableSelect.innerHTML += '<option value="<?php echo $tableName; ?>" <?php echo $searchValue === $tableName ? 'selected' : ''; ?>><?php echo $tableName; ?></option>';
                    <?php endforeach; ?>
                    
                    searchValueContainer.appendChild(tableSelect);
                    break;
                    
                case 'Action':
                    searchValueLabel.textContent = 'Select Action';
                    const actionSelect = document.createElement('select');
                    actionSelect.name = 'searchValue';
                    actionSelect.className = 'form-control';
                    actionSelect.innerHTML = `
                        <option value="">Select Action</option>
                        <option value="INSERT" <?php echo $searchValue === 'INSERT' ? 'selected' : ''; ?>>Insert</option>
                        <option value="UPDATE" <?php echo $searchValue === 'UPDATE' ? 'selected' : ''; ?>>Update</option>
                        <option value="DELETE" <?php echo $searchValue === 'DELETE' ? 'selected' : ''; ?>>Delete</option>
                    `;
                    searchValueContainer.appendChild(actionSelect);
                    break;
            }
        }
        
        // Function to print the report
        function printReport() {
            window.print();
        }
        
        // Initialize the search field on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSearchField();
        });
    </script>
</body>
</html> 