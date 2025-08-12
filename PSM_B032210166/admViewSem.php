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
    $stmt = $conn->prepare("SELECT FullName FROM EMPLOYEE WHERE EmpID = ?");
    $stmt->bind_param("s", $_SESSION['empId']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $adminName = $row['FullName'];
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting admin name: " . $e->getMessage());
}

// Fetch all SemIDs for dropdown
$semIDs = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT SemID FROM SEMESTER ORDER BY SemID");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $semIDs[] = $row['SemID'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching SemIDs: " . $e->getMessage());
}

// Get search parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'SemID';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'ASC';

// Initialize semesters array
$semesters = [];

// Initialize total results count
$totalResults = 0;

// Build the SQL query based on search criteria
$sql = "SELECT SemID, AcademicYear, Semester, CheckInDate, CheckOutDate, HostelFee FROM SEMESTER";
$params = [];
$types = "";

// Check if date range search is being performed
if ($searchCriteria === 'Date' && isset($_GET['startDate']) && isset($_GET['endDate']) && 
    !empty($_GET['startDate']) && !empty($_GET['endDate'])) {
    $sql .= " WHERE ('" . $_GET['startDate'] . "' BETWEEN CheckInDate AND CheckOutDate) AND ('" . $_GET['endDate'] . "' BETWEEN CheckInDate AND CheckOutDate)";
} elseif ($searchCriteria !== 'All' && !empty($searchValue)) {
    switch ($searchCriteria) {
        case 'SemID':
            $sql .= " WHERE SemID = ?";
            $params[] = $searchValue;
            $types .= "s";
            break;
        case 'Semester':
            $sql .= " WHERE Semester = ?";
            $params[] = intval($searchValue);
            $types .= "i";
            break;
        case 'AcademicYear':
            $sql .= " WHERE AcademicYear LIKE ?";
            $params[] = "%".$searchValue."%";
            $types .= "s";
            break;
    }
}

// Add sorting
$sql .= " ORDER BY " . $sortBy . " " . $sortOrder;

// Execute the query
try {
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    // Debug the final SQL with actual values
    $finalSql = $sql;
    if (!empty($params)) {
        foreach ($params as $param) {
            $finalSql = preg_replace('/\?/', "'$param'", $finalSql, 1);
        }
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    
    while ($row = $result->fetch_assoc()) {
        $semesters[] = $row;
    }
    
    // Get total results count
    $totalResults = count($semesters);
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching semester data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin View Semesters - SHMS</title>
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
            padding: 1rem 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo img {
            height: 50px;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            color: var(--primary-color);
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
        
        /* Main Content */
        main {
            padding: 2rem 0;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .page-header h2 {
            color: var(--primary-color);
            font-size: 28px;
            margin-top: 25px;
        }

        /* Form Layout Updates */
        .search-container {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            font-weight: bold;
            color: var(--text-dark);
        }
        
        .form-control {
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
            margin-top: 1.5rem;
        }

        .btn-warning {
            background-color: var(--warning);
            color: var(--text-dark);
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        .results-container {
            background-color: var(--white);
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        /* Sort Controls */
        .sort-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: var(--shadow);
        }

        .sort-controls .form-group {
            margin: 0;
        }
        
        /* Table Styles */
        .table-container {
            background-color: var(--white);
            border-radius: 8px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            font-weight: bold;
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        tr:nth-child(even) {
            background-color: var(--light-bg);
        }
        
        /* Buttons */
        .btn {
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-secondary {
            background-color: var(--text-light);
            color: var(--white);
        }
        
        .btn-secondary:hover {
            background-color: var(--text-dark);
        }
        
        /* Date Range Inputs */
        .date-range {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }
        
        .date-range .form-group {
            flex: 1;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .date-range {
                flex-direction: column;
            }
            
            .nav-links {
                display: none;
            }
        }

        /* Results Count */
        .results-count {
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 15px;
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

        /* Update button styles */
        .btn-sm {
            padding: 8px 20px;
            font-size: 16px;
            margin: 0 5px;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
        }

        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
            padding: 12px 20px;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        /* Remove underline from edit button */
        td a.btn {
            text-decoration: none;
        }

        /* Alert styles */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: var(--white);
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            position: relative;
        }

        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: var(--danger);
        }

        .form-row {
            margin-bottom: 15px;
        }

        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .form-row input,
        .form-row select {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .modal-footer {
            margin-top: 20px;
            text-align: right;
        }

        .modal-footer .btn {
            margin-left: 10px;
        }

        .error-message {
            color: var(--danger);
            font-size: 16px;
            margin-top: 5px;
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/adminNav.php'; ?>
    
    <main class="container main-content">
        <section class="page-header">
            <h2>View Semesters</h2>
        </section>

        <!-- below is for alert when user add, edit or delete data -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
            
        <!-- below is for alert when user add, edit or delete data -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <section class="search-container">
            <form action="admViewSem.php" method="GET" class="search-form">
                <div class="form-group">
                    <label for="searchCriteria">Search By</label>
                    <select id="searchCriteria" name="searchCriteria" class="form-control" onchange="updateSearchField()">
                        <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="SemID" <?php echo $searchCriteria === 'SemID' ? 'selected' : ''; ?>>Semester ID</option>
                        <option value="Date" <?php echo $searchCriteria === 'Date' ? 'selected' : ''; ?>>Date Range</option>
                        <option value="Semester" <?php echo $searchCriteria === 'Semester' ? 'selected' : ''; ?>>Semester</option>
                        <option value="AcademicYear" <?php echo $searchCriteria === 'AcademicYear' ? 'selected' : ''; ?>>Academic Year</option>
                    </select>
                </div>
                
                <div id="searchValueField">
                    <!-- This will be dynamically updated based on the selected criteria -->
                </div>
                
                <div class="form-group">
                    <label for="sortBy">Sort By</label>
                    <select id="sortBy" name="sortBy" class="form-control">
                        <option value="SemID" <?php echo $sortBy === 'SemID' ? 'selected' : ''; ?>>Semester ID</option>
                        <option value="AcademicYear" <?php echo $sortBy === 'AcademicYear' ? 'selected' : ''; ?>>Academic Year</option>
                        <option value="Semester" <?php echo $sortBy === 'Semester' ? 'selected' : ''; ?>>Semester</option>
                        <option value="HostelFee" <?php echo $sortBy === 'HostelFee' ? 'selected' : ''; ?>>Hostel Fee</option>
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3>Search Results</h3>
                <button type="button" class="btn btn-primary" onclick="openAddModal()">Add New Semester</button>
            </div>
            
            <?php if (empty($semesters)): ?>
                <p>No semester records found. Please try a different search criteria.</p>
            <?php else: ?>
                <p class="results-count">Total Results: <?php echo $totalResults; ?></p>
                <table>
                    <thead>
                        <tr>
                            <th>Semester ID</th>
                            <th>Academic Year</th>
                            <th>Semester</th>
                            <th>Check In Date</th>
                            <th>Check Out Date</th>
                            <th>Hostel Fee (RM)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($semesters as $semester): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($semester['SemID']); ?></td>
                                <td><?php echo htmlspecialchars($semester['AcademicYear']); ?></td>
                                <td><?php echo htmlspecialchars($semester['Semester']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($semester['CheckInDate'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($semester['CheckOutDate'])); ?></td>
                                <td><?php echo number_format($semester['HostelFee'], 2); ?></td>
                                <td>
                                    <a href="admEditSem.php?id=<?php echo urlencode($semester['SemID']); ?>" class="btn btn-primary btn-sm">Edit</a>
                                    <button onclick="confirmDelete('<?php echo htmlspecialchars($semester['SemID']); ?>')" class="btn btn-danger btn-sm">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

    <div class="modal" id="addModal">
        <div class="modal-content">
            <span class="close" onclick="closeAddModal()">&times;</span>
            <h2>Add New Semester</h2>
            <form id="addForm">
                <div class="form-row">
                    <label for="newAcademicYear">Academic Year (Eg: XXXX/XXXX)</label>
                    <input type="text" id="newAcademicYear" name="academicYear" 
                           placeholder="Eg: 2024/2025" required
                           pattern="\d{4}/\d{4}">
                    <div class="error-message" id="newAcademicYearError"></div>
                </div>
                
                <div class="form-row">
                    <label for="newSemester">Semester</label>
                    <select id="newSemester" name="semester" required>
                        <option value="">Select Semester</option>
                        <option value="1">1</option>
                        <option value="2">2</option>
                        <option value="3">3</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="newCheckInDate">Check In Date</label>
                    <input type="date" id="newCheckInDate" name="checkInDate" required>
                </div>
                
                <div class="form-row">
                    <label for="newCheckOutDate">Check Out Date</label>
                    <input type="date" id="newCheckOutDate" name="checkOutDate" required>
                    <div class="error-message" id="newDateError"></div>
                </div>
                
                <div class="form-row">
                    <label for="newHostelFee">Hostel Fee (RM)</label>
                    <input type="number" id="newHostelFee" name="hostelFee" 
                           min="0.01" step="0.01" required>
                    <div class="error-message" id="newFeeError"></div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function updateSearchField() {
            const searchCriteria = document.getElementById('searchCriteria').value;
            const searchValueField = document.getElementById('searchValueField');
            
            // Clear the current search value field
            searchValueField.innerHTML = '';
            
            // Create the appropriate input field based on the selected criteria
            switch (searchCriteria) {
                case 'All':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div style="padding: 8px 0; font-weight: bold;">No search value needed</div>
                            <input type="hidden" name="searchValue" value="">
                        </div>
                    `;
                    break;
                    
                case 'SemID':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>Semester ID</label>
                            <select name="searchValue" class="form-control" required>
                                <option value="">Select Semester ID</option>
                                <?php foreach ($semIDs as $semID): ?>
                                    <option value="<?php echo htmlspecialchars($semID); ?>"><?php echo htmlspecialchars($semID); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    `;
                    break;

                case 'Date':
                    searchValueField.innerHTML = `
                        <div class="date-range" style="display: flex; gap: 1rem;">
                            <div class="form-group">
                                <label>Start Date</label>
                                <input type="date" id="startDate" name="startDate" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>End Date</label>
                                <input type="date" id="endDate" name="endDate" class="form-control" required 
                                       onchange="validateDateRange()">
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'Semester':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>Semester</label>
                            <select name="searchValue" class="form-control" required>
                                <option value="">Select Semester</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'AcademicYear':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>Academic Year</label>
                            <input type="number" name="searchValue" class="form-control" 
                                   min="2000" max="2099" step="1" required
                                   placeholder="YYYY">
                        </div>
                    `;
                    break;
            }
        }

        function validateDateRange() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (startDate && endDate && endDate < startDate) {
                alert('End date cannot be earlier than start date');
                document.getElementById('endDate').value = '';
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
                    <title>Semester Report</title>
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
                        <h1>View Semesters</h1>
                    </div>
                    <div class="search-results">Search Results</div>
                    <div class="results-count">Total Results: ${rows.length - 1}</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Semester ID</th>
                                <th>Academic Year</th>
                                <th>Semester</th>
                                <th>Check-In Date</th>
                                <th>Check-Out Date</th>
                                <th>Hostel Fee (RM)</th>
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

        // Function to confirm deletion
        function confirmDelete(semId) {
            if (confirm('Are you sure you want to delete this semester? This action cannot be undone.')) {
                window.location.href = 'admDeleteSem.php?id=' + encodeURIComponent(semId);
            }
        }

        // Function to open the add modal
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
            // Reset form
            document.getElementById('addForm').reset();
            // Reset error messages
            document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
        }

        // Function to close the add modal
        function closeAddModal() {
            document.getElementById('addModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('addModal')) {
                closeAddModal();
            }
        }

        // Handle add form submission
        document.getElementById('addForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Reset error messages
            document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
            
            // Validate academic year format
            const academicYear = document.getElementById('newAcademicYear').value;
            if (!/^\d{4}\/\d{4}$/.test(academicYear)) {
                document.getElementById('newAcademicYearError').textContent = 'Invalid format. Use XXXX/XXXX';
                document.getElementById('newAcademicYearError').style.display = 'block';
                return;
            }
            
            // Validate dates
            const checkInDate = new Date(document.getElementById('newCheckInDate').value);
            const checkOutDate = new Date(document.getElementById('newCheckOutDate').value);
            if (checkOutDate <= checkInDate) {
                document.getElementById('newDateError').textContent = 'Check-out date must be after check-in date';
                document.getElementById('newDateError').style.display = 'block';
                return;
            }
            
            // Validate hostel fee
            const hostelFee = parseFloat(document.getElementById('newHostelFee').value);
            if (isNaN(hostelFee) || hostelFee <= 0) {
                document.getElementById('newFeeError').textContent = 'Hostel fee must be greater than 0';
                document.getElementById('newFeeError').style.display = 'block';
                return;
            }
            
            // Submit form
            const formData = new FormData(this);
            fetch('admAddSem.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Semester added successfully');
                    closeAddModal();
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while adding the semester');
            });
        });

        // Initialize the search field on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSearchField();
        });
    </script>
</body>
</html> 