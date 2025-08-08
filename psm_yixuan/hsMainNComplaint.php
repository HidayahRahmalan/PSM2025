<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not hostel staff
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOSTEL STAFF') {
    header("Location: staffHomePage.php");
    exit();
}

// Initialize search and sort parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$searchDateFrom = isset($_GET['searchDateFrom']) ? $_GET['searchDateFrom'] : '';
$searchDateTo = isset($_GET['searchDateTo']) ? $_GET['searchDateTo'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'ReqID';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';
$studentName = isset($_GET['studentName']) ? $_GET['studentName'] : '';

// Get all request IDs for dropdown
$requestIDs = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT ReqID 
                           FROM REQUEST 
                           WHERE Type IN ('MAINTENANCE', 'COMPLAINT')
                           ORDER BY ReqID DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requestIDs[] = $row['ReqID'];
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting request IDs: " . $e->getMessage());
}

// Get all room IDs for dropdown
$rooms = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT r.RoomID, r.RoomNo, h.Name as HostelName 
                           FROM ROOM r 
                           JOIN HOSTEL h ON r.HostID = h.HostID 
                           JOIN REQUEST req ON r.RoomID = req.RoomID
                           WHERE r.Status = 'ACTIVE' 
                           AND req.Type IN ('MAINTENANCE', 'COMPLAINT')
                           ORDER BY h.Name, r.RoomNo");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rooms[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting rooms: " . $e->getMessage());
}

// Build the SQL query based on search criteria
$sql = "SELECT r.ReqID, r.Type, r.Description, r.Status, r.RequestedDate, r.BookID, r.RoomID, r.StudID,
               CONCAT(rm.RoomNo, ' (', h.Name, ')') as RoomInfo,
               s.FullName as StudentName,
               COALESCE(e.FullName, 'Haven''t any staff to incharge') as EmpName,
               COALESCE(e.Role, '-') as EmpRole,
               r.EmpID
        FROM REQUEST r
        LEFT JOIN ROOM rm ON r.RoomID = rm.RoomID
        LEFT JOIN HOSTEL h ON rm.HostID = h.HostID
        LEFT JOIN EMPLOYEE e ON r.EmpID = e.EmpID
        LEFT JOIN STUDENT s ON r.StudID = s.StudID
        WHERE r.Type IN ('MAINTENANCE', 'COMPLAINT')";

$params = [];
$types = "";

if ($searchCriteria === 'ReqID' && !empty($searchValue)) {
    $sql .= " AND r.ReqID = ?";
    $params[] = $searchValue;
    $types .= "s";
} elseif ($searchCriteria === 'Type' && !empty($searchValue)) {
    $sql .= " AND r.Type = ?";
    $params[] = $searchValue;
    $types .= "s";
} elseif ($searchCriteria === 'Status' && !empty($searchValue)) {
    $sql .= " AND r.Status = ?";
    $params[] = $searchValue;
    $types .= "s";
} elseif ($searchCriteria === 'Date' && !empty($searchDateFrom) && !empty($searchDateTo)) {
    $sql .= " AND r.RequestedDate BETWEEN ? AND ?";
    $params[] = $searchDateFrom;
    $params[] = $searchDateTo;
    $types .= "ss";
} elseif ($searchCriteria === 'RoomID' && !empty($searchValue)) {
    $sql .= " AND r.RoomID = ?";
    $params[] = $searchValue;
    $types .= "s";
} elseif ($searchCriteria === 'StudentName' && !empty($studentName)) {
    $sql .= " AND UPPER(s.FullName) LIKE UPPER(?)";
    $params[] = "%" . $studentName . "%";
    $types .= "s";
}

// Add sorting
$sql .= " ORDER BY " . $sortBy . " " . $sortOrder;

// Execute the query
$requests = [];
try {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting requests: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance & Complaint Management - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/hsNav.css">
    <script>
        // Check for email sent flag and show alert
        <?php if(isset($_SESSION['email_sent']) && $_SESSION['email_sent'] === true): ?>
        window.addEventListener('DOMContentLoaded', function() {
            alert('Email notification sent successfully to student!');
            <?php unset($_SESSION['email_sent']); ?>
        });
        <?php endif; ?>
    </script>
    <style>
        :root {
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --secondary: #6c757d;
        }
        
        .main-content {
            padding: 30px 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 2rem;
            text-align: left;
        }
        
        .page-header h2 {
            color: var(--primary-color);
            font-size: 24px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 10px;
        }
        
        /* Search Section */
        .search-section {
            background-color: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .section-title {
            color: var(--primary-color);
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            text-align: left;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            align-items: end;
        }
        
        .search-form .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: bold;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 15px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(44, 157, 255, 0.1);
        }
        
        /* Table Styles */
        .table-container {
            margin-top: 30px;
            overflow-x: auto;
            max-width: 100%;
            white-space: nowrap;
        }

        table {
            width: 100%;
            min-width: 1500px;
            background-color: white;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            font-family: Arial, sans-serif;
            font-size: 16px;
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #ddd;
            text-align: left;
            word-wrap: break-word;
            white-space: normal;
            line-height: 1.4;
        }

        /* Column Widths */
        th:nth-child(1), td:nth-child(1) { min-width: 100px; } /* ReqID */
        th:nth-child(2), td:nth-child(2) { min-width: 120px; } /* Type */
        th:nth-child(3), td:nth-child(3) { min-width: 250px; } /* Description */
        th:nth-child(4), td:nth-child(4) { min-width: 120px; } /* Status */
        th:nth-child(5), td:nth-child(5) { min-width: 130px; } /* RequestedDate */
        th:nth-child(6), td:nth-child(6) { min-width: 100px; } /* BookID */
        th:nth-child(7), td:nth-child(7) { min-width: 150px; } /* Room */
        th:nth-child(8), td:nth-child(8) { min-width: 150px; } /* Student */
        th:nth-child(9), td:nth-child(9) { min-width: 150px; } /* Staff Name */
        th:nth-child(10), td:nth-child(10) { min-width: 100px; } /* Staff Role */
        th:nth-child(11), td:nth-child(11) { min-width: 140px; } /* Action */

        th {
            background-color: #25408f;
            color: white;
            font-weight: bold;
            height: auto;
            min-height: 45px;
            padding: 15px;
        }

        td {
            height: auto;
            min-height: 40px;
            padding: 15px;
        }

        tr {
            transition: background-color 0.3s ease;
        }

        tr:hover {
            background-color: #edf3ff !important;
            cursor: pointer;
            box-shadow: 0 3px 5px rgba(0,0,0,0.1);
        }

        tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 6px 6px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            width: 100px;
            white-space: nowrap;
            margin: 0 auto;
        }

        /* Center status badge */
        td:nth-child(4) {
            text-align: center;
        }

        /* Action Buttons in Table */
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .action-buttons .btn {
            padding: 6px 10px;
            font-size: 16px;
            height: 35px;
            width: 90px;
            white-space: nowrap;
        }

        /* Button Styles */
        .btn {
            font-family: Arial, sans-serif;
            font-size: 16px;
            transition: background-color 0.3s ease;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-primary {
            background-color: #25408f;
            color: white;
        }

        .btn-primary:hover {
            background-color: #3883ce;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #000;
        }

        .btn-warning:hover {
            background-color: #e0a800;
        }

        .btn-danger {
            background-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .search-btn-group {
            display: flex;
            gap: 10px;
            align-items: center;
            white-space: nowrap;
        }

        .search-btn-group .btn {
            min-width: 150px;
        }
        
        .search-btn-group .btn-gold {
            min-width: 180px;
        }
        
        /* Status Badges */
        .status-pending {
            background-color: var(--warning);
            color: #000;
        }
        
        .status-approved {
            background-color: var(--success);
            color: #fff;
        }
        
        .status-rejected {
            background-color: var(--danger);
            color: #fff;
        }
        
        .status-in-progress {
            background-color: var(--info);
            color: #fff;
        }
        
        .status-resolved {
            background-color: var(--success);
            color: #fff;
        }
        
        /* Date Range Inputs */
        .date-range {
            display: flex;
            gap: 20px;
        }

        .date-range .form-group {
            flex: 1;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .btn {
            width: 100%;
            padding: 8px 12px;
            font-family: Arial, sans-serif;
            font-size: 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }

        /* No Results Message */
        .no-results {
            text-align: center;
            padding: 20px;
            color: var(--text-dark);
            font-family: Arial, sans-serif;
            font-size: 16px;
            background-color: var(--light-bg);
            border-radius: 8px;
            margin: 20px 0;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 50px auto;
            padding: 20px;
            border-radius: 8px;
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
        
        .modal-header {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-title {
            color: var(--primary-color);
            font-size: 20px;
            margin: 0;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            text-align: right;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .search-form {
                grid-template-columns: 1fr;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .modal-content {
                margin: 20px;
                width: auto;
            }
        }

        /* Additional Modal Styles */
        .modal-content {
            width: 90%;
            max-width: 500px;
            font-family: Arial, sans-serif;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-body .form-group {
            margin-bottom: 15px;
        }

        .modal-body label {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
            color: var(--text-dark);
        }

        .modal-body input,
        .modal-body textarea,
        .modal-body select {
            width: 100%;
            box-sizing: border-box;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 15px;
            font-family: Arial, sans-serif;
        }

        .modal-body textarea {
            resize: vertical;
            min-height: 100px;
        }

        .modal-body small {
            color: var(--text-light);
            font-size: 13px;
            margin-top: 4px;
            display: block;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
            border-top: 1px solid var(--border-color);
        }

        .modal-footer .btn {
            width: auto;
            min-width: 100px;
            margin: 0;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: var(--white);
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        /* Global Styles */
        body {
            background-color: #f0f8ff;
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        /* Navigation Bar Height */
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px 0;
            height: 70px;
            font-family: Arial, sans-serif;
        }

        .logo img {
            height: 45px;
            margin-right: 15px;
        }

        /* Page Header */
        .page-header h2 {
            font-family: Arial, sans-serif;
            font-size: 28px;
            font-weight: bold;
            color: var(--primary-color);
        }

        /* Search Section */
        .section-title {
            font-family: Arial, sans-serif;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 25px;
        }

        /* Date Range Inputs */
        .date-range-container {
            grid-column: span 2;
            display: flex;
            gap: 30px;
            margin-right: 20px;
        }

        .date-range-container .form-group {
            flex: 1;
        }

        .date-range-container input[type="date"] {
            height: 38px;
            padding: 8px 12px;
            font-size: 16px;
            width: 100%;
            box-sizing: border-box;
        }

        /* Button Styles */
        .btn {
            font-family: Arial, sans-serif;
            font-size: 16px;
            padding: 8px 20px;
            height: 38px;
            transition: background-color 0.3s ease;
        }

        .search-btn-group .btn {
            min-width: 150px;
            background-color: #25408f;
        }

        .search-btn-group .btn:hover {
            background-color: #3883ce;
        }

        /* View button specific style */
        .action-buttons .btn-primary {
            background-color: #25408f;
        }

        .action-buttons .btn-primary:hover {
            background-color: #3883ce;
        }
        
        /* Type badge styles */
        .type-badge {
            display: inline-block;
            padding: 6px 6px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            width: 100px;
            white-space: nowrap;
            margin: 0 auto;
        }
        
        .type-maintenance {
            background-color: #6610f2;
            color: #fff;
        }
        
        .type-complaint {
            background-color: #fd7e14;
            color: #fff;
        }
        
        /* Center type badge */
        td:nth-child(2) {
            text-align: center;
        }

        .btn-gold {
            background-color: #ffc107 !important;
            color: black !important;
        }

        .btn-gold:hover {
            background-color: #e0a800 !important;
            color: black !important;
        }
        
        /* Results Count */
        .results-count {
            margin: 0 0 15px 0;
            font-size: 18px;
            font-weight: bold;
            font-family: Arial, sans-serif;
            color: #25408f;
        }
    </style>
</head>
<body>
    <?php include 'includes/hsNav.php'; ?>
    
    <div class="container main-content">
        <section class="page-header">
            <h2>Maintenance & Complaint Management</h2>
        </section>
        
        <section class="search-section">
            <h3 class="section-title">Search Requests</h3>
            <form action="hsMainNComplaint.php" method="GET" class="search-form">
                <div class="form-group">
                    <label for="searchCriteria">Search By</label>
                    <select id="searchCriteria" name="searchCriteria" class="form-control" onchange="updateSearchField()">
                        <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="ReqID" <?php echo $searchCriteria === 'ReqID' ? 'selected' : ''; ?>>Request ID</option>
                        <option value="Type" <?php echo $searchCriteria === 'Type' ? 'selected' : ''; ?>>Type</option>
                        <option value="Status" <?php echo $searchCriteria === 'Status' ? 'selected' : ''; ?>>Status</option>
                        <option value="Date" <?php echo $searchCriteria === 'Date' ? 'selected' : ''; ?>>Requested Date</option>
                        <option value="RoomID" <?php echo $searchCriteria === 'RoomID' ? 'selected' : ''; ?>>Room ID</option>
                        <option value="StudentName" <?php echo $searchCriteria === 'StudentName' ? 'selected' : ''; ?>>Student Name</option>
                    </select>
                </div>
                
                <div id="searchValueField">
                    <!-- This will be dynamically updated based on the selected criteria -->
                </div>
                
                <div class="form-group sort-group">
                    <label for="sortBy">Sort By</label>
                    <select id="sortBy" name="sortBy" class="form-control">
                        <option value="ReqID" <?php echo $sortBy === 'ReqID' ? 'selected' : ''; ?>>Request ID</option>
                        <option value="RequestedDate" <?php echo $sortBy === 'RequestedDate' ? 'selected' : ''; ?>>Requested Date</option>
                        <option value="BookID" <?php echo $sortBy === 'BookID' ? 'selected' : ''; ?>>Booking ID</option>
                        <option value="RoomID" <?php echo $sortBy === 'RoomID' ? 'selected' : ''; ?>>Room ID</option>
                    </select>
                </div>
                
                <div class="form-group order-group">
                    <label for="sortOrder">Sort Order</label>
                    <select id="sortOrder" name="sortOrder" class="form-control">
                        <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div>
                
                <div class="search-btn-group">
                    <button type="submit" class="btn btn-primary">Search</button>
                    <button type="button" class="btn btn-gold" onclick="printReport()">Generate Report</button>
                </div>
            </form>
        </section>
        
        <section class="table-container">
            <?php if (empty($requests)): ?>
                <div class="no-results">
                   No maintenance or complaint requests found. Please try a different search criteria.
                </div>
            <?php else: ?>
                <p class="results-count">Total Results: <?php echo count($requests); ?></p>
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Requested Date</th>
                            <th>Booking ID</th>
                            <th>Room</th>
                            <th>Student Name</th>
                            <th>Staff Name</th>
                            <th>Staff Role</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['ReqID']); ?></td>
                                <td>
                                    <span class="type-badge type-<?php echo strtolower($request['Type']); ?>">
                                        <?php echo htmlspecialchars($request['Type']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($request['Description']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $request['Status'])); ?>">
                                        <?php echo htmlspecialchars($request['Status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($request['RequestedDate'])); ?></td>
                                <td><?php echo htmlspecialchars($request['BookID']); ?></td>
                                <td><?php echo htmlspecialchars($request['RoomInfo']); ?></td>
                                <td><?php echo htmlspecialchars($request['StudentName']); ?></td>
                                <td><?php echo htmlspecialchars($request['EmpName']); ?></td>
                                <td><?php echo htmlspecialchars($request['EmpRole']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($request['Status'] === 'PENDING'): ?>
                                            <?php if ($request['EmpID'] === $_SESSION['empId']): ?>
                                                <button class="btn btn-primary" onclick="editRequest('<?php echo $request['ReqID']; ?>')">Edit</button>
                                            <?php else: ?>
                                                <button class="btn btn-primary" onclick="viewRequest('<?php echo $request['ReqID']; ?>')">View</button>
                                            <?php endif; ?>
                                            <button class="btn btn-danger" onclick="deleteRequest('<?php echo $request['ReqID']; ?>')">Delete</button>
                                            <?php if (empty($request['EmpID'])): ?>
                                                <button class="btn btn-primary" onclick="acceptRequest('<?php echo $request['ReqID']; ?>')">Accept</button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if ($request['EmpID'] === $_SESSION['empId']): ?>
                                                <button class="btn btn-primary" onclick="editRequest('<?php echo $request['ReqID']; ?>')">Edit</button>
                                            <?php else: ?>
                                                <button class="btn btn-primary" onclick="viewRequest('<?php echo $request['ReqID']; ?>')">View</button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>
    
    <!-- Edit Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Edit Request</h3>
            </div>
            <form id="editForm" onsubmit="submitMainNComplaintForm(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="reqID">Request ID</label>
                        <input type="text" id="reqID" name="reqID" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="type">Type</label>
                        <input type="text" id="type" name="type" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <!-- Options will be dynamically populated based on type -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" maxlength="250" rows="4" oninput="updateCharCount(this)"></textarea>
                        <div class="char-counter">
                            <span id="charCount">0</span>/250
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="requestedDate">Requested Date</label>
                        <input type="text" id="requestedDate" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="bookID">Booking ID</label>
                        <input type="text" id="bookID" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="room">Room</label>
                        <input type="text" id="room" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="studentName">Student Name</label>
                        <input type="text" id="studentName" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="empName">Employee Name</label>
                        <input type="text" id="empName" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="empRole">Employee Role</label>
                        <input type="text" id="empRole" class="form-control" readonly>
                    </div>
                    <!-- Hidden field for student ID -->
                    <input type="hidden" id="studID" name="studID">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Submit form using AJAX to handle maintenance and complaint requests
        function submitMainNComplaintForm(event) {
            event.preventDefault();
            
            // Get form data
            const formData = new FormData(document.getElementById('editForm'));
            const status = document.getElementById('status').value;
            const reqType = document.getElementById('type').value;
            
            // Show loading state
            const submitButton = event.submitter;
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = 'Processing...';
            submitButton.disabled = true;
            
            // Send AJAX request
            fetch('hsProcessMainNComplaint.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if status needs email notification
                const notificationStatuses = ['APPROVED', 'REJECTED', 'IN PROGRESS', 'RESOLVED'];
                if (notificationStatuses.includes(status)) {
                    // Send email notification
                    const emailData = new FormData();
                    emailData.append('studID', document.getElementById('studID').value);
                    emailData.append('reqID', document.getElementById('reqID').value);
                    emailData.append('type', reqType);
                    emailData.append('status', status);
                    
                    return fetch('hsSendRequestStatusEmail.php', {
                        method: 'POST',
                        body: emailData
                    })
                    .then(emailResponse => emailResponse.json())
                    .then(emailResult => {
                        if (emailResult.success) {
                            alert('Email notification sent successfully to ' + document.getElementById('studentName').value);
                        } else {
                            console.error('Email sending failed:', emailResult.message);
                        }
                        // Redirect or refresh page
                        window.location.reload();
                    });
                } else {
                    // Just reload the page
                    window.location.reload();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing your request.');
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            });
        }

        // Show/hide search fields based on search criteria
        function updateSearchField() {
            const searchCriteria = document.getElementById('searchCriteria').value;
            const searchValueField = document.getElementById('searchValueField');
            
            // Clear previous search fields
            searchValueField.innerHTML = '';
            
            switch (searchCriteria) {
                case 'All':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <div style="padding: 8px 0;">No search value needed</div>
                        </div>
                    `;
                    break;
                    
                case 'ReqID':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>Request ID</label>
                            <select name="searchValue" class="form-control">
                                <option value="">Select Request ID</option>
                                <?php foreach ($requestIDs as $reqID): ?>
                                <option value="<?php echo $reqID; ?>" <?php echo $searchValue === $reqID ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($reqID); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'Type':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>Type</label>
                            <select name="searchValue" class="form-control">
                                <option value="">Select Type</option>
                                <option value="MAINTENANCE" ${<?php echo json_encode($searchValue); ?> === 'MAINTENANCE' ? 'selected' : ''}>Maintenance</option>
                                <option value="COMPLAINT" ${<?php echo json_encode($searchValue); ?> === 'COMPLAINT' ? 'selected' : ''}>Complaint</option>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'Status':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>Status</label>
                            <select name="searchValue" class="form-control">
                                <option value="">Select Status</option>
                                <option value="PENDING" ${<?php echo json_encode($searchValue); ?> === 'PENDING' ? 'selected' : ''}>Pending</option>
                                <option value="IN PROGRESS" ${<?php echo json_encode($searchValue); ?> === 'IN PROGRESS' ? 'selected' : ''}>In Progress</option>
                                <option value="RESOLVED" ${<?php echo json_encode($searchValue); ?> === 'RESOLVED' ? 'selected' : ''}>Resolved</option>
                                <option value="APPROVED" ${<?php echo json_encode($searchValue); ?> === 'APPROVED' ? 'selected' : ''}>Approved</option>
                                <option value="REJECTED" ${<?php echo json_encode($searchValue); ?> === 'REJECTED' ? 'selected' : ''}>Rejected</option>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'Date':
                    searchValueField.innerHTML = `
                        <div class="date-range-container">
                            <div class="form-group">
                                <label for="searchDateFrom">From Date</label>
                                <input type="date" id="searchDateFrom" name="searchDateFrom" class="form-control" value="<?php echo $searchDateFrom; ?>" tabindex="1">
                            </div>
                            <div class="form-group">
                                <label for="searchDateTo">To Date</label>
                                <input type="date" id="searchDateTo" name="searchDateTo" class="form-control" value="<?php echo $searchDateTo; ?>" tabindex="2" onchange="validateDateRange()">
                            </div>
                        </div>
                    `;
                    break;
                    
                case 'RoomID':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>Room</label>
                            <select name="searchValue" class="form-control">
                                <option value="">Select Room</option>
                                <?php foreach ($rooms as $room): ?>
                                <option value="<?php echo $room['RoomID']; ?>" <?php echo $searchValue === $room['RoomID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($room['RoomNo'] . ' (' . $room['HostelName'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    `;
                    break;
                    
                case 'StudentName':
                    searchValueField.innerHTML = `
                        <div class="form-group">
                            <label>Student Name</label>
                            <input type="text" name="studentName" class="form-control" value="<?php echo htmlspecialchars($studentName); ?>" placeholder="Enter Student Name">
                        </div>
                    `;
                    break;
            }
        }
        
        // Update results count text on page load
        document.addEventListener('DOMContentLoaded', function() {
            const resultsCount = document.querySelector('.results-count');
            if (resultsCount) {
                const count = <?php echo !empty($requests) ? count($requests) : 0; ?>;
                resultsCount.innerHTML = `Total Results: ${count}`;
            }
            
            // Initialize search fields
            updateSearchField();
            
            // Initialize character count
            if (document.getElementById('description')) {
                updateCharCount(document.getElementById('description'));
            }
        });
        
        function validateDateRange() {
            const fromDate = document.querySelector('input[name="searchDateFrom"]').value;
            const toDate = document.querySelector('input[name="searchDateTo"]').value;
            
            if (fromDate && toDate && toDate < fromDate) {
                alert('To Date cannot be earlier than From Date');
                document.querySelector('input[name="searchDateTo"]').value = '';
            }
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function viewRequest(reqID) {
            // Fetch request details and populate modal
            fetch(`hsGetMainNComplaintDetails.php?reqID=${reqID}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('reqID').value = data.ReqID;
                    document.getElementById('type').value = data.Type;
                    
                    // Set appropriate status options based on type
                    const statusSelect = document.getElementById('status');
                    statusSelect.innerHTML = '';
                    
                    if (data.Type === 'MAINTENANCE') {
                        const statuses = ['PENDING', 'IN PROGRESS', 'RESOLVED'];
                        statuses.forEach(status => {
                            const option = document.createElement('option');
                            option.value = status;
                            option.textContent = status;
                            if (status === data.Status) {
                                option.selected = true;
                            }
                            statusSelect.appendChild(option);
                        });
                    } else if (data.Type === 'COMPLAINT') {
                        const statuses = ['PENDING', 'APPROVED', 'REJECTED'];
                        statuses.forEach(status => {
                            const option = document.createElement('option');
                            option.value = status;
                            option.textContent = status;
                            if (status === data.Status) {
                                option.selected = true;
                            }
                            statusSelect.appendChild(option);
                        });
                    }
                    
                    // Make all fields readonly
                    document.getElementById('status').disabled = true;
                    document.getElementById('description').value = data.Description;
                    document.getElementById('description').readOnly = true;
                    document.getElementById('requestedDate').value = data.RequestedDate;
                    document.getElementById('bookID').value = data.BookID;
                    document.getElementById('room').value = data.RoomInfo;
                    document.getElementById('studentName').value = data.StudentName;
                    document.getElementById('empName').value = data.EmpName;
                    document.getElementById('empRole').value = data.EmpRole;
                    document.getElementById('studID').value = data.StudID;
                    
                    // Change modal title and hide save button
                    document.querySelector('.modal-title').textContent = 'View Request';
                    document.querySelector('.modal-footer .btn-primary').style.display = 'none';
                    
                    document.getElementById('editModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load request details');
                });
        }
        
        function editRequest(reqID) {
            // Fetch request details and populate modal
            fetch(`hsGetMainNComplaintDetails.php?reqID=${reqID}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('reqID').value = data.ReqID;
                    document.getElementById('type').value = data.Type;
                    
                    // Set appropriate status options based on type
                    const statusSelect = document.getElementById('status');
                    statusSelect.innerHTML = '';
                    statusSelect.disabled = false;
                    
                    if (data.Type === 'MAINTENANCE') {
                        const statuses = ['PENDING', 'IN PROGRESS', 'RESOLVED'];
                        statuses.forEach(status => {
                            const option = document.createElement('option');
                            option.value = status;
                            option.textContent = status;
                            if (status === data.Status) {
                                option.selected = true;
                            }
                            statusSelect.appendChild(option);
                        });
                    } else if (data.Type === 'COMPLAINT') {
                        const statuses = ['PENDING', 'APPROVED', 'REJECTED'];
                        statuses.forEach(status => {
                            const option = document.createElement('option');
                            option.value = status;
                            option.textContent = status;
                            if (status === data.Status) {
                                option.selected = true;
                            }
                            statusSelect.appendChild(option);
                        });
                    }
                    
                    document.getElementById('description').value = data.Description;
                    document.getElementById('description').readOnly = false;
                    document.getElementById('requestedDate').value = data.RequestedDate;
                    document.getElementById('bookID').value = data.BookID;
                    document.getElementById('room').value = data.RoomInfo;
                    document.getElementById('studentName').value = data.StudentName;
                    document.getElementById('empName').value = data.EmpName;
                    document.getElementById('empRole').value = data.EmpRole;
                    document.getElementById('studID').value = data.StudID;
                    
                    // Change modal title and show save button
                    document.querySelector('.modal-title').textContent = 'Edit Request';
                    document.querySelector('.modal-footer .btn-primary').style.display = 'block';
                    
                    document.getElementById('editModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load request details');
                });
        }
        
        function deleteRequest(reqID) {
            if (confirm('Are you sure you want to delete this request?')) {
                window.location.href = `hsDeleteMainNComplaint.php?reqID=${reqID}`;
            }
        }
        
        function updateCharCount(textarea) {
            const maxLength = textarea.maxLength;
            const currentLength = textarea.value.length;
            document.getElementById('charCount').textContent = currentLength;
        }
        
        function printReport() {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Create the print content
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Maintenance & Complaint Report</title>
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
                            text-align: center;
                        }
                        .search-results-container {
                            text-align: left;
                            margin-left: 20px;
                        }
                        .search-results {
                            font-weight: bold;
                            color: #333;
                            font-size: 16px;
                            margin-bottom: 5px;
                        }
                        .results-count {
                            font-weight: normal;
                            color: #25408f;
                            font-size: 16px;
                            margin-bottom: 20px;
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
                            font-family: Arial, sans-serif;
                            line-height: 1.4;
                        }
                        th {
                            background-color: #25408f;
                            color: white;
                            font-weight: bold;
                            height: auto;
                            min-height: 45px;
                        }
                        tr:nth-child(even) {
                            background-color: #f9f9f9;
                        }
                        .status-badge {
                            display: inline-block;
                            padding: 6px 12px;
                            border-radius: 4px;
                            font-weight: bold;
                            text-align: center;
                        }
                        .status-pending {
                            background-color: #ffc107;
                            color: #000;
                        }
                        .status-approved {
                            background-color: #28a745;
                            color: white;
                        }
                        .status-rejected {
                            background-color: #dc3545;
                            color: white;
                        }
                        .status-in-progress {
                            background-color: #17a2b8;
                            color: white;
                        }
                        .status-resolved {
                            background-color: #28a745;
                            color: white;
                        }
                        .type-badge {
                            display: inline-block;
                            padding: 6px 12px;
                            border-radius: 4px;
                            font-weight: bold;
                            text-align: center;
                        }
                        .type-maintenance {
                            background-color: #6610f2;
                            color: white;
                        }
                        .type-complaint {
                            background-color: #fd7e14;
                            color: white;
                        }
                        .report-footer {
                            margin-top: 30px;
                            text-align: center;
                            font-size: 12px;
                            color: #666;
                            border-top: 1px solid #ddd;
                            padding-top: 20px;
                        }
                        @media print {
                            @page {
                                size: landscape;
                                margin: 2cm;
                            }
                            body {
                                -webkit-print-color-adjust: exact;
                                print-color-adjust: exact;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="report-header">
                        <h1>Maintenance & Complaint Report</h1>
                        <p>Generated on: ${new Date().toLocaleString()}</p>
                    </div>
                    <div class="search-results-container">
                        <div class="search-results">Search Results</div>
                    `;

            // Add total results right after the header
            const totalResults = document.querySelector('.no-results');
            if (totalResults) {
                printContent += `<div class="results-count">${totalResults.textContent}</div></div>`;
            } else {
                const count = document.querySelectorAll('table tbody tr').length;
                                    printContent += `<div class="results-count">Total Results: ${count}</div></div>`;
            }
            
            // Get the table data
            const table = document.querySelector('table');
            if (table) {
                const rows = Array.from(table.rows);
                
                // Start the table
                printContent += '<table>';
                
                // Add header row
                const headerRow = rows[0];
                printContent += '<thead><tr>';
                Array.from(headerRow.cells).forEach((cell, index) => {
                    if (index !== headerRow.cells.length - 1) { // Skip the Actions column
                        printContent += `<th>${cell.textContent.trim()}</th>`;
                    }
                });
                printContent += '</tr></thead>';
                
                // Add body rows
                printContent += '<tbody>';
                rows.slice(1).forEach(row => {
                    printContent += '<tr>';
                    Array.from(row.cells).forEach((cell, index) => {
                        if (index !== row.cells.length - 1) { // Skip the Actions column
                            if (index === 3) { // Status column
                                const status = cell.textContent.trim();
                                printContent += `<td><span class="status-badge status-${status.toLowerCase().replace(' ', '-')}">${status}</span></td>`;
                            } else if (index === 1) { // Type column
                                const type = cell.textContent.trim();
                                printContent += `<td><span class="type-badge type-${type.toLowerCase()}">${type}</span></td>`;
                            } else {
                                printContent += `<td>${cell.innerHTML.trim()}</td>`;
                            }
                        }
                    });
                    printContent += '</tr>';
                });
                printContent += '</tbody>';
                printContent += '</table>';
            }
            
            // Add footer
            printContent += `
                    <div class="report-footer">
                        <p>Smart Hostel Management System &copy; ${new Date().getFullYear()}</p>
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
        
        function acceptRequest(reqID) {
            if (confirm('Are you sure you want to accept this request?')) {
                window.location.href = `hsAcceptMainNComplaint.php?reqID=${reqID}`;
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target === document.getElementById('editModal')) {
                closeModal();
            }
        }
    </script>
</body>
</html>
