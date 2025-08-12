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

// Get all unique EmpIDs from EMPLOYEE
$empIds = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT EmpID FROM EMPLOYEE WHERE Role = 'ADMIN' ORDER BY EmpID");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $empIds[] = $row['EmpID'];
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting EmpIDs: " . $e->getMessage());
}

// Get search parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'EmpID';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'ASC';

// Initialize employees array
$employees = [];

// Always fetch results on initial load or when search is performed
include 'admFetchAdm.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Information - SHMS</title>
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
        
        .results-header {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .results-header h3 {
            color: var(--primary-color);
            font-size: 20px;
        }
        
        .results-header .count {
            color: var(--text-light);
            font-size: 14px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            background-color: var(--light-bg);
            color: var(--primary-color);
            font-weight: bold;
            cursor: pointer;
        }
        
        th:hover {
            background-color: #e6f2ff;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .sort-icon {
            margin-left: 5px;
        }
        
        .action-btns {
            display: flex;
            gap: 10px;
        }
        
        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        
        .status-inactive {
            background-color: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        
        /* No Results */
        .no-results {
            text-align: center;
            padding: 30px;
            color: var(--text-light);
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .search-form {
                flex-direction: column;
            }
            
            .form-group {
                width: 100%;
            }
            
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/adminNav.php'; ?>
    
    <main class="container">
        <section class="page-header">
            <h2>Admin Information</h2>
        </section>
        
        <section class="search-container">
            <form class="search-form" action="" method="GET">
                <div class="form-group">
                    <label for="searchCriteria">Search By</label>
                    <select class="form-control" id="searchCriteria" name="searchCriteria">
                        <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All Admins</option>
                        <option value="EmpID" <?php echo $searchCriteria === 'EmpID' ? 'selected' : ''; ?>>Employee ID</option>
                        <option value="FullName" <?php echo $searchCriteria === 'FullName' ? 'selected' : ''; ?>>Full Name</option>
                        <option value="Gender" <?php echo $searchCriteria === 'Gender' ? 'selected' : ''; ?>>Gender</option>
                        <option value="Status" <?php echo $searchCriteria === 'Status' ? 'selected' : ''; ?>>Status</option>
                    </select>
                </div>
                
                <div class="form-group" id="valueContainer">
                    <label for="searchValue">Search Value</label>
                    <?php if ($searchCriteria === 'EmpID' && !empty($empIds)): ?>
                        <select class="form-control" id="searchValue" name="searchValue">
                            <option value="">Select Employee ID</option>
                            <?php foreach ($empIds as $empId): ?>
                                <option value="<?php echo htmlspecialchars($empId); ?>" <?php echo $searchValue === $empId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($empId); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php elseif ($searchCriteria === 'Gender'): ?>
                        <select class="form-control" id="searchValue" name="searchValue">
                            <option value="">Select Gender</option>
                            <option value="M" <?php echo $searchValue === 'M' ? 'selected' : ''; ?>>Male</option>
                            <option value="F" <?php echo $searchValue === 'F' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    <?php elseif ($searchCriteria === 'Status'): ?>
                        <select class="form-control" id="searchValue" name="searchValue">
                            <option value="">Select Status</option>
                            <option value="ACTIVE" <?php echo $searchValue === 'ACTIVE' ? 'selected' : ''; ?>>Active</option>
                            <option value="INACTIVE" <?php echo $searchValue === 'INACTIVE' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    <?php else: ?>
                        <input type="text" class="form-control" id="searchValue" name="searchValue" value="<?php echo htmlspecialchars($searchValue); ?>" placeholder="Enter search value">
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="sortBy">Sort By</label>
                    <select class="form-control" id="sortBy" name="sortBy">
                        <option value="EmpID" <?php echo $sortBy === 'EmpID' ? 'selected' : ''; ?>>Employee ID</option>
                        <option value="FullName" <?php echo $sortBy === 'FullName' ? 'selected' : ''; ?>>Full Name</option>
                        <option value="Gender" <?php echo $sortBy === 'Gender' ? 'selected' : ''; ?>>Gender</option>
                        <option value="Status" <?php echo $sortBy === 'Status' ? 'selected' : ''; ?>>Status</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sortOrder">Sort Order</label>
                    <select class="form-control" id="sortOrder" name="sortOrder">
                        <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>
        </section>
        
        <section class="results-container">
            <div class="results-header" style="display: flex; flex-direction: column; align-items: flex-start;">
                <h3 style="color: black; margin-bottom: 5px;">Search Results</h3>
                <h4 style="color: var(--primary-color); font-size: 16px; margin: 0;">Total Results: <?php echo $totalResults; ?></h4>
            </div>
            
            <?php if ($totalResults > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th onclick="window.location.href='?searchCriteria=<?php echo htmlspecialchars($searchCriteria); ?>&searchValue=<?php echo htmlspecialchars($searchValue); ?>&sortBy=EmpID&sortOrder=<?php echo $sortBy === 'EmpID' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>'">
                                Employee ID
                                <?php if ($sortBy === 'EmpID'): ?>
                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                <?php endif; ?>
                            </th>
                            <th onclick="window.location.href='?searchCriteria=<?php echo htmlspecialchars($searchCriteria); ?>&searchValue=<?php echo htmlspecialchars($searchValue); ?>&sortBy=FullName&sortOrder=<?php echo $sortBy === 'FullName' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>'">
                                Full Name
                                <?php if ($sortBy === 'FullName'): ?>
                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                <?php endif; ?>
                            </th>
                            <th>Staff Email</th>
                            <th>Personal Email</th>
                            <th>Phone Number</th>
                            <th onclick="window.location.href='?searchCriteria=<?php echo htmlspecialchars($searchCriteria); ?>&searchValue=<?php echo htmlspecialchars($searchValue); ?>&sortBy=Gender&sortOrder=<?php echo $sortBy === 'Gender' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>'">
                                Gender
                                <?php if ($sortBy === 'Gender'): ?>
                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                <?php endif; ?>
                            </th>
                            <th onclick="window.location.href='?searchCriteria=<?php echo htmlspecialchars($searchCriteria); ?>&searchValue=<?php echo htmlspecialchars($searchValue); ?>&sortBy=Status&sortOrder=<?php echo $sortBy === 'Status' && $sortOrder === 'ASC' ? 'DESC' : 'ASC'; ?>'">
                                Status
                                <?php if ($sortBy === 'Status'): ?>
                                    <i class="fas fa-sort-<?php echo $sortOrder === 'ASC' ? 'up' : 'down'; ?> sort-icon"></i>
                                <?php endif; ?>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['EmpID']); ?></td>
                                <td><?php echo htmlspecialchars($employee['FullName']); ?></td>
                                <td><?php echo htmlspecialchars($employee['StaffEmail']); ?></td>
                                <td><?php echo htmlspecialchars($employee['PersonalEmail']); ?></td>
                                <td><?php echo htmlspecialchars($employee['PhoneNo']); ?></td>
                                <td><?php echo $employee['Gender'] === 'M' ? 'Male' : 'Female'; ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($employee['Status']); ?>">
                                        <?php echo htmlspecialchars($employee['Status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a href="admViewAdm.php?EmpID=<?php echo htmlspecialchars($employee['EmpID']); ?>" class="btn btn-primary">View</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-results">
                    <p><i class="fas fa-info-circle"></i> No admin accounts found matching your search criteria.</p>
                </div>
            <?php endif; ?>
        </section>
    </main>
    
    <script>
        // JavaScript to update search value field based on search criteria
        document.getElementById('searchCriteria').addEventListener('change', function() {
            const searchCriteria = this.value;
            const valueContainer = document.getElementById('valueContainer');
            
            if (searchCriteria === 'Gender') {
                valueContainer.innerHTML = `
                    <label for="searchValue">Gender</label>
                    <select class="form-control" id="searchValue" name="searchValue">
                        <option value="">Select Gender</option>
                        <option value="M">Male</option>
                        <option value="F">Female</option>
                    </select>
                `;
            } else if (searchCriteria === 'Status') {
                valueContainer.innerHTML = `
                    <label for="searchValue">Status</label>
                    <select class="form-control" id="searchValue" name="searchValue">
                        <option value="">Select Status</option>
                        <option value="ACTIVE">Active</option>
                        <option value="INACTIVE">Inactive</option>
                    </select>
                `;
            } else if (searchCriteria === 'EmpID') {
                valueContainer.innerHTML = `
                    <label for="searchValue">Employee ID</label>
                    <select class="form-control" id="searchValue" name="searchValue">
                        <option value="">Select Employee ID</option>
                        <?php foreach ($empIds as $empId): ?>
                            <option value="<?php echo htmlspecialchars($empId); ?>">
                                <?php echo htmlspecialchars($empId); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                `;
            } else {
                valueContainer.innerHTML = `
                    <label for="searchValue">Search Value</label>
                    <input type="text" class="form-control" id="searchValue" name="searchValue" placeholder="Enter search value">
                `;
            }
        });
    </script>
</body>
</html> 