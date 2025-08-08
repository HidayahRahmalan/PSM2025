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
    $stmt = $conn->prepare("SELECT DISTINCT EmpID FROM EMPLOYEE WHERE Role = 'HOSTEL STAFF' ORDER BY EmpID");
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
include 'admFetchHs.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin View Hostel Staff - SHMS</title>
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
        }
        
        /* Table column widths */
        .table th:nth-child(1), .table td:nth-child(1) { width: 8%; }  /* EmpID */
        .table th:nth-child(2), .table td:nth-child(2) { width: 20%; } /* FullName */
        .table th:nth-child(3), .table td:nth-child(3) { width: 15%; } /* StaffEmail */
        .table th:nth-child(4), .table td:nth-child(4) { width: 15%; } /* PersonalEmail */
        .table th:nth-child(5), .table td:nth-child(5) { width: 10%; } /* PhoneNo */
        .table th:nth-child(6), .table td:nth-child(6) { width: 8%; }  /* Gender */
        .table th:nth-child(7), .table td:nth-child(7) { width: 8%; } /* Status */
        .table th:nth-child(8), .table td:nth-child(8) { width: 8%; } /* Role */
        .table th:nth-child(9), .table td:nth-child(9) { width: 8%; }  /* Actions */
        
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
        
        /* Remove horizontal scroll */
        .table-container {
            margin-bottom: 20px;
            width: 100%;
        }
        
        .table {
            width: 100%;
            table-layout: fixed;
        }
        
        .table tr:hover {
            background-color: rgba(44, 157, 255, 0.05);
        }
        
        .status-active {
            color: var(--success) !important;
            font-weight: bold;
        }
        
        .status-inactive {
            color: var(--danger) !important;
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
            
            .status-active {
                color: #28a745 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .status-inactive {
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
            <h2>View Hostel Staff</h2>
        </section>

        <section class="search-container">
            <form action="admViewHs.php" method="GET" class="search-form">
                <div class="form-group">
                    <label for="searchCriteria">Search By</label>
                    <select id="searchCriteria" name="searchCriteria" class="form-control" onchange="updateSearchField()">
                        <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="EmpID" <?php echo $searchCriteria === 'EmpID' ? 'selected' : ''; ?>>Employee ID</option>
                        <option value="FullName" <?php echo $searchCriteria === 'FullName' ? 'selected' : ''; ?>>Full Name</option>
                        <option value="Gender" <?php echo $searchCriteria === 'Gender' ? 'selected' : ''; ?>>Gender</option>
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
                        <option value="EmpID" <?php echo $sortBy === 'EmpID' ? 'selected' : ''; ?>>Employee ID</option>
                        <option value="FullName" <?php echo $sortBy === 'FullName' ? 'selected' : ''; ?>>Full Name</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sortOrder">Sort Order</label>
                    <select id="sortOrder" name="sortOrder" class="form-control">
                        <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
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
            
            <?php if (empty($employees)): ?>
                <p>No hostel staff found. Please try a different search criteria.</p>
            <?php else: ?>
                <p class="results-count">Total Results: <?php echo $totalResults; ?></p>
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Full Name</th>
                                <th>Staff Email</th>
                                <th>Personal Email</th>
                                <th>Phone Number</th>
                                <th>Gender</th>
                                <th>Status</th>
                                <th>Role</th>
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
                                    <td class="<?php echo $employee['Status'] === 'ACTIVE' ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo htmlspecialchars($employee['Status']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($employee['Role']); ?></td>
                                    <td>
                                        <a href="admEditHs.php?EmpID=<?php echo $employee['EmpID']; ?>" class="btn btn-primary btn-sm">Edit</a>
                                    </td>
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
                    searchValueContainer.innerHTML = '<input type="hidden" name="searchValue" value="">';
                    break;
                    
                case 'EmpID':
                    searchValueLabel.textContent = 'Select Employee ID';
                    const empSelect = document.createElement('select');
                    empSelect.name = 'searchValue';
                    empSelect.className = 'form-control';
                    empSelect.innerHTML = '<option value="">Select Employee ID</option>';
                    
                    <?php foreach ($empIds as $empId): ?>
                        empSelect.innerHTML += '<option value="<?php echo $empId; ?>" <?php echo $searchValue === $empId ? 'selected' : ''; ?>><?php echo $empId; ?></option>';
                    <?php endforeach; ?>
                    
                    searchValueContainer.appendChild(empSelect);
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
                    
                case 'Gender':
                    searchValueLabel.textContent = 'Select Gender';
                    const genderSelect = document.createElement('select');
                    genderSelect.name = 'searchValue';
                    genderSelect.className = 'form-control';
                    genderSelect.innerHTML = `
                        <option value="">Select Gender</option>
                        <option value="M" <?php echo $searchValue === 'M' ? 'selected' : ''; ?>>Male</option>
                        <option value="F" <?php echo $searchValue === 'F' ? 'selected' : ''; ?>>Female</option>
                    `;
                    searchValueContainer.appendChild(genderSelect);
                    break;
                    
                case 'Status':
                    searchValueLabel.textContent = 'Select Status';
                    const statusSelect = document.createElement('select');
                    statusSelect.name = 'searchValue';
                    statusSelect.className = 'form-control';
                    statusSelect.innerHTML = `
                        <option value="">Select Status</option>
                        <option value="ACTIVE" <?php echo $searchValue === 'ACTIVE' ? 'selected' : ''; ?>>Active</option>
                        <option value="INACTIVE" <?php echo $searchValue === 'INACTIVE' ? 'selected' : ''; ?>>Inactive</option>
                    `;
                    searchValueContainer.appendChild(statusSelect);
                    break;
            }
        }
        
        // Function to print the report
        function printReport() {
            const printWindow = window.open('', '_blank');
            
            // Get the table data
            const table = document.querySelector('table');
            const rows = Array.from(table.rows);
            
            // Create print content
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Hostel Staff Report</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .report-header {
                            text-align: center;
                            margin-bottom: 30px;
                        }
                        .report-header h1 {
                            color: #25408f;
                            font-size: 28px;
                            margin-bottom: 10px;
                        }
                        .search-results {
                            font-weight: bold;
                            color: #333;
                            font-size: 16px;
                            margin-bottom: 5px;
                            text-align: left;
                            margin-left: 20px;
                        }
                        .results-count {
                            font-weight: normal;
                            color: #333;
                            font-size: 16px;
                            margin-bottom: 20px;
                            text-align: left;
                            margin-left: 20px;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin-bottom: 20px;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 15px;
                            text-align: left;
                            font-size: 16px;
                        }
                        th {
                            background-color: #25408f;
                            color: white;
                            font-weight: bold;
                        }
                        tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .report-footer {
                            margin-top: 30px;
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                            border-top: 1px solid #ddd;
                            padding-top: 20px;
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Hostel Staff List</h1>
                    </div>
                    <div class="search-results">Search Results</div>
                    <div class="results-count">Total Results: ${rows.length - 1}</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Full Name</th>
                                <th>Staff Email</th>
                                <th>Personal Email</th>
                                <th>Phone Number</th>
                                <th>Gender</th>
                                <th>Status</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            // Add table rows (skip header row)
            rows.slice(1).forEach(row => {
                printContent += '<tr>';
                Array.from(row.cells).forEach((cell, index) => {
                    if (index < row.cells.length - 1) { // Skip the last column (Actions)
                        printContent += `<td>${cell.textContent.trim()}</td>`;
                    }
                });
                printContent += '</tr>';
            });
            
            // Close the table and add footer
            printContent += `
                        </tbody>
                    </table>
                    <div class="report-footer">
                        <p>Smart Hostel Management System</p>
                    </div>
                </body>
                </html>
            `;
            
            // Write to the print window and print
            printWindow.document.write(printContent);
            printWindow.document.close();
            
            // Wait for images to load before printing
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }
        
        // Initialize the search field on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSearchField();
        });
    </script>
</body>
</html> 