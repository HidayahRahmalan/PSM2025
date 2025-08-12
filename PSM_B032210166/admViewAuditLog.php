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

// Get all unique UserIDs from AUDIT_LOG
$userIds = [];
try {
    //Get the district userID to be the drop down list option
    $stmt = $conn->prepare("SELECT DISTINCT UserID FROM AUDIT_LOG ORDER BY UserID");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $userIds[] = $row['UserID'];
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting UserIDs: " . $e->getMessage());
}

// Get search parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'LogID';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';

// Initialize audit logs array
$auditLogs = [];

// Always fetch results on initial load or when search is performed
include 'admFetchAuditLog.php';

// Get unique actions for the dropdown
$actions = [];
$actionQuery = "SELECT DISTINCT Action FROM AUDIT_LOG ORDER BY Action";
$actionResult = $conn->query($actionQuery);
if ($actionResult) {
    while ($row = $actionResult->fetch_assoc()) {
        $actions[] = $row['Action'];
    }
}

// Get unique statuses for the dropdown
$statuses = [];
$statusQuery = "SELECT DISTINCT Status FROM AUDIT_LOG ORDER BY Status";
$statusResult = $conn->query($statusQuery);
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $statuses[] = $row['Status'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin View Audit Logs - SHMS</title>
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
        
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            background-color: var(--light-bg);
            color: var(--primary-color);
            font-weight: bold;
        }
        
        .table tr:hover {
            background-color: rgba(44, 157, 255, 0.05);
        }
        
        .table .status-success {
            color: #28a745 !important;
            font-weight: bold;
        }
        
        .table .status-failed {
            color: #dc3545 !important;
            font-weight: bold;
        }
        
        .sort-link {
            color: var(--primary-color);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .sort-link:hover {
            text-decoration: underline;
        }
        
        .sort-icon {
            margin-left: 5px;
            font-size: 12px;
        }
        
        .results-count {
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 16px;
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
            
            .status-success {
                color: #28a745 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .status-failed {
                color: #dc3545 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
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
            <h2>Audit Logs</h2>
        </section>

        <section class="search-container">
            <form action="admViewAuditLog.php" method="GET" class="search-form">
                <div class="form-group">
                    <label for="searchCriteria">Search By</label>
                    <select id="searchCriteria" name="searchCriteria" class="form-control" onchange="updateSearchField()">
                        <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="UserID" <?php echo $searchCriteria === 'UserID' ? 'selected' : ''; ?>>User ID</option>
                        <option value="FullName" <?php echo $searchCriteria === 'FullName' ? 'selected' : ''; ?>>Full Name</option>
                        <option value="UserRole" <?php echo $searchCriteria === 'UserRole' ? 'selected' : ''; ?>>User Role</option>
                        <option value="Action" <?php echo $searchCriteria === 'Action' ? 'selected' : ''; ?>>Action</option>
                        <option value="Status" <?php echo $searchCriteria === 'Status' ? 'selected' : ''; ?>>Status</option>
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
                        <option value="LogID" <?php echo $sortBy === 'LogID' ? 'selected' : ''; ?>>Log ID</option>
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
            
            <?php if (empty($auditLogs)): ?>
                <p>No audit logs found. Please try a different search criteria.</p>
            <?php else: ?>
                <p class="results-count">Total Results: <?php echo $totalResults; ?></p>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>User ID</th>
                            <th>Full Name</th>
                            <th>User Role</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>IP Address</th>
                            <th>Device Info</th>
                            <th>Time Stamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($auditLogs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['LogID']); ?></td>
                                <td><?php echo htmlspecialchars($log['UserID']); ?></td>
                                <td><?php 
                                    $displayName = $log['FullName'];
                                    // Remove the ZZZ_ prefix for display purposes
                                    if (strpos($displayName, 'ZZZ_UNKNOWN_') === 0) {
                                        $displayName = str_replace('ZZZ_UNKNOWN_', 'UNKNOWN ', $displayName);
                                    }
                                    echo htmlspecialchars($displayName); 
                                ?></td>
                                <td><?php echo htmlspecialchars($log['UserRole']); ?></td>
                                <td><?php echo htmlspecialchars($log['Action']); ?></td>
                                <td class="<?php echo (stripos($log['Status'], 'success') !== false) ? 'status-success' : 'status-failed'; ?>">
                                    <?php echo htmlspecialchars($log['Status']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['IPAddress']); ?></td>
                                <td><?php echo htmlspecialchars($log['DeviceInfo']); ?></td>
                                <td><?php echo htmlspecialchars($log['TimeStamp']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                    
                case 'UserRole':
                    searchValueLabel.textContent = 'Select User Role';
                    const roleSelect = document.createElement('select');
                    roleSelect.name = 'searchValue';
                    roleSelect.className = 'form-control';
                    roleSelect.innerHTML = `
                        <option value="">Select User Role</option>
                        <option value="STUDENT" <?php echo $searchValue === 'STUDENT' ? 'selected' : ''; ?>>STUDENT</option>
                        <option value="HOSTEL STAFF" <?php echo $searchValue === 'HOSTEL STAFF' ? 'selected' : ''; ?>>HOSTEL STAFF</option>
                        <option value="ADMIN" <?php echo $searchValue === 'ADMIN' ? 'selected' : ''; ?>>ADMIN</option>
                        <option value="HOSTEL STAFF/ADMIN" <?php echo $searchValue === 'HOSTEL STAFF/ADMIN' ? 'selected' : ''; ?>>HOSTEL STAFF/ADMIN</option>
                    `;
                    searchValueContainer.appendChild(roleSelect);
                    break;
                    
                case 'Action':
                    searchValueLabel.textContent = 'Select Action';
                    const actionSelect = document.createElement('select');
                    actionSelect.name = 'searchValue';
                    actionSelect.className = 'form-control';
                    actionSelect.innerHTML = `
                        <option value="">Select Action</option>
                        <option value="LOGIN" <?php echo $searchValue === 'LOGIN' ? 'selected' : ''; ?>>LOGIN</option>
                        <option value="LOGOUT" <?php echo $searchValue === 'LOGOUT' ? 'selected' : ''; ?>>LOGOUT</option>
                    `;
                    searchValueContainer.appendChild(actionSelect);
                    break;
                    
                case 'Status':
                    searchValueLabel.textContent = 'Select Status';
                    const statusSelect = document.createElement('select');
                    statusSelect.name = 'searchValue';
                    statusSelect.className = 'form-control';
                    statusSelect.innerHTML = `
                        <option value="">Select Status</option>
                        <option value="SUCCESS" <?php echo $searchValue === 'SUCCESS' ? 'selected' : ''; ?>>SUCCESS</option>
                        <option value="FAILED - LOGIN CREDENTIALS" <?php echo $searchValue === 'FAILED - LOGIN CREDENTIALS' ? 'selected' : ''; ?>>FAILED - LOGIN CREDENTIALS</option>
                        <option value="FAILED - ACCOUNT DEACTIVATED" <?php echo $searchValue === 'FAILED - ACCOUNT DEACTIVATED' ? 'selected' : ''; ?>>FAILED - ACCOUNT DEACTIVATED</option>
                    `;
                    searchValueContainer.appendChild(statusSelect);
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