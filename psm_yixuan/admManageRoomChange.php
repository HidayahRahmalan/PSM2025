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

// Initialize search and sort parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$searchDateFrom = isset($_GET['searchDateFrom']) ? $_GET['searchDateFrom'] : '';
$searchDateTo = isset($_GET['searchDateTo']) ? $_GET['searchDateTo'] : '';
$searchStudent = isset($_GET['searchStudent']) ? $_GET['searchStudent'] : '';
$searchReqID = isset($_GET['searchReqID']) ? $_GET['searchReqID'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'ReqID';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';

// Get all request IDs for dropdown
$requestIDs = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT ReqID FROM REQUEST WHERE Type = 'ROOM CHANGE' ORDER BY ReqID");
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
                           AND req.Type = 'ROOM CHANGE'
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
$sql = "SELECT r.ReqID, r.Description, r.Status, r.RequestedDate, r.BookID, r.RoomID, 
               CONCAT(rm.RoomNo, ' (', h.Name, ')') as RoomInfo,
               s.FullName as StudentName
        FROM REQUEST r
        LEFT JOIN ROOM rm ON r.RoomID = rm.RoomID
        LEFT JOIN HOSTEL h ON rm.HostID = h.HostID
        LEFT JOIN STUDENT s ON r.StudID = s.StudID
        WHERE r.Type = 'ROOM CHANGE'";

$params = [];
$types = "";

if ($searchCriteria === 'ReqID' && !empty($searchReqID)) {
    $sql .= " AND r.ReqID = ?";
    $params[] = $searchReqID;
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
} elseif ($searchCriteria === 'StudentName' && !empty($searchStudent)) {
    $sql .= " AND UPPER(s.FullName) LIKE UPPER(?)";
    $params[] = "%$searchStudent%";
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
    <title>Manage Room Change Requests - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/adminNav.css">
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
        /* Force navigation bar font */
        .navbar, .navbar * {
            font-family: Arial, sans-serif !important;
        }
        
        :root {
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
        }
        
        body {
            background-color: #f0f8ff; /* Light blue background */
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
            text-align: center;
        }
        
        .page-header h2 {
            color: var(--primary-color);
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            font-family: Arial, sans-serif;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Search Section */
        .search-section {
            background-color: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            font-family: Arial, sans-serif;
        }
        
        .section-title {
            color: var(--primary-color);
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            text-align: center;
            font-family: Arial, sans-serif;
        }
        
        .search-form {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            align-items: end;
            font-family: Arial, sans-serif;
        }
        
        .search-form .form-group {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: bold;
            font-family: Arial, sans-serif;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 15px;
            font-family: Arial, sans-serif;
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
        }
        
        .total-results {
            margin-bottom: 10px;
            font-weight: bold;
            font-family: Arial, sans-serif;
            font-size: 16px;
            color: var(--primary-color);
        }

        table {
            width: 100%;
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
        th:nth-child(1), td:nth-child(1) { width: 10%; } /* ReqID */
        th:nth-child(2), td:nth-child(2) { width: 20%; } /* Description */
        th:nth-child(3), td:nth-child(3) { width: 12%; } /* Status */
        th:nth-child(4), td:nth-child(4) { width: 10%; } /* RequestedDate */
        th:nth-child(5), td:nth-child(5) { width: 10%; } /* BookID */
        th:nth-child(6), td:nth-child(6) { width: 15%; } /* Room */
        th:nth-child(7), td:nth-child(7) { width: 13%; } /* Student Name */
        th:nth-child(8), td:nth-child(8) { width: 10%; } /* Action */

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
        td:nth-child(3) {
            text-align: center;
        }

        /* Action Buttons in Table */
        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .action-buttons .btn {
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
        }

        .search-btn-group .btn {
            flex: 1;
            min-width: 120px;
            font-size: 16px;
            padding: 8px 20px;
            height: 38px;
            background-color: #25408f;
        }

        .search-btn-group .btn:hover {
            background-color: #3883ce;
        }
        
        .search-btn-group .btn.btn-report {
            background-color: #ffc107;
            color: #000;
            white-space: nowrap;
            min-width: 180px;
        }
        
        .search-btn-group .btn.btn-report:hover {
            background-color: #e0a800;
        }
        
        /* Request Records heading */
        .records-heading {
            text-align: center;
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 22px;
            font-weight: bold;
            font-family: Arial, sans-serif;
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
        
        /* Date Range Inputs */
        .date-range {
            display: flex;
            gap: 20px;
        }

        .date-range .form-group {
            flex: 1;
        }

        /* No Results Message */
        .no-results {
            text-align: center;
            padding: 30px;
            color: #333;
            font-family: Arial, sans-serif;
            font-size: 16px;
            background-color: #fff;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
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
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-footer .btn {
            width: auto;
            min-width: 100px;
            margin: 0;
            white-space: nowrap;
            height: 38px;
            padding: 0 15px;
            font-size: 15px;
            font-weight: 600;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: var(--white);
        }

        .btn-secondary:hover {
            background-color: #5a6268;
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
        
        /* Room input with view button */
        .room-input-group {
            display: flex;
            gap: 10px;
        }
        
        .room-input-group input {
            flex: 1;
        }
        
        .room-input-group .btn {
            white-space: nowrap;
            height: 38px;
            padding: 0 15px;
            background-color: var(--primary-color);
            color: white;
        }
        
        .room-input-group .btn:hover {
            background-color: var(--secondary-color);
        }
        
        /* Room Details Modal */
        #roomDetailsModal .modal-content {
            max-width: 700px;
        }
        
        .room-details-table {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }
        
        .room-details-table th, 
        .room-details-table td {
            padding: 10px;
            border: 1px solid var(--border-color);
            text-align: left;
        }
        
        .room-details-table th {
            background-color: #25408f;
            color: white;
            font-weight: bold;
        }
        
        .room-details-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .room-details-table tbody tr:hover {
            background-color: #edf3ff;
        }

        /* Print Styles for Report */
        @media print {
            header, .search-section, .btn-group, .modal, .action-buttons {
                display: none !important;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            
            body {
                background-color: white;
                font-size: 12pt;
            }
            
            .table-container {
                box-shadow: none;
                padding: 0;
            }
            
            .page-header {
                text-align: center;
                margin-bottom: 20px;
            }
            
            .page-header h2 {
                font-size: 18pt;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            th, td {
                border: 1px solid #ddd;
            }
            
            /* Hide action buttons when printing */
            th:last-child, td:last-child {
                display: none;
            }
            
            /* Ensure status badges print properly */
            .status-badge {
                border: 1px solid #ddd;
                color: #000 !important;
            }
            
            .status-pending {
                background-color: #ffc107 !important;
                color: #000 !important;
            }
            
            .status-approved {
                background-color: #28a745 !important;
                color: #fff !important;
            }
            
            .status-rejected {
                background-color: #dc3545 !important;
                color: #fff !important;
            }
            
            /* Add page break settings */
            table {
                page-break-inside: auto;
            }
            
            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            
            thead {
                display: table-header-group;
            }
            
            tfoot {
                display: table-footer-group;
            }
            
            @page {
                size: landscape;
                margin: 1cm;
            }
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
    </style>
</head>

<body>
    <?php include 'includes/adminNav.php'; ?>
    
    <div class="container main-content">
        <section class="page-header">
            <h2>Room Change Requests Management</h2>
        </section>
        
        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']);
            ?>
        </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
            ?>
        </div>
        <?php endif; ?>
        
        <section class="search-section">
            <h3 class="section-title">Search Requests</h3>
            <form action="admManageRoomChange.php" method="GET" class="search-form">
                <div class="form-group">
                    <label for="searchCriteria">Search By</label>
                    <select id="searchCriteria" name="searchCriteria" class="form-control" onchange="updateSearchField()">
                        <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="ReqID" <?php echo $searchCriteria === 'ReqID' ? 'selected' : ''; ?>>Request ID</option>
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
                        <option value="Status" <?php echo $sortBy === 'Status' ? 'selected' : ''; ?>>Status</option>
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
                    <button type="button" class="btn btn-report" onclick="printReport()">Generate Report</button>
                </div>
            </form>
        </section>
        
        <section class="table-container">
            <?php if (empty($requests)): ?>
                <div class="no-results">
                   No room change requests found. Please try a different search criteria.
                </div>
            <?php else: ?>
                <h3 class="records-heading">Request Records</h3>
                <div class="total-results">
                    Total Results: <?php echo count($requests); ?>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Requested Date</th>
                            <th>Booking ID</th>
                            <th>Room</th>
                            <th>Student Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['ReqID']); ?></td>
                                <td><?php echo htmlspecialchars($request['Description']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($request['Status']); ?>">
                                        <?php echo htmlspecialchars($request['Status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($request['RequestedDate'])); ?></td>
                                <td><?php echo htmlspecialchars($request['BookID']); ?></td>
                                <td><?php echo htmlspecialchars($request['RoomInfo']); ?></td>
                                <td><?php echo htmlspecialchars($request['StudentName']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($request['Status'] === 'PENDING'): ?>
                                            <button class="btn btn-primary" onclick="editRequest('<?php echo $request['ReqID']; ?>')">Edit</button>
                                            <button class="btn btn-danger" onclick="deleteRequest('<?php echo $request['ReqID']; ?>')">Delete</button>
                                        <?php else: ?>
                                            <button class="btn btn-primary" onclick="viewRequest('<?php echo $request['ReqID']; ?>')">View</button>
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
                <h3 class="modal-title">Edit Room Change Request</h3>
            </div>
            <form id="editForm" onsubmit="submitRoomChangeForm(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="reqID">Request ID</label>
                        <input type="text" id="reqID" name="reqID" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="type">Type</label>
                        <input type="text" id="type" name="type" value="ROOM CHANGE" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="PENDING">PENDING</option>
                            <option value="APPROVED">APPROVED</option>
                            <option value="REJECTED">REJECTED</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" maxlength="250" required rows="4" oninput="updateCharCount(this)"></textarea>
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
                        <input type="text" id="bookID" name="bookID" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="room">Room</label>
                        <div class="room-input-group">
                            <input type="text" id="room" class="form-control" readonly>
                            <button type="button" class="btn" onclick="viewRoomDetails()">View Room</button>
                        </div>
                        <input type="hidden" id="roomID" name="roomID" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="student">Student Name</label>
                        <input type="text" id="student" class="form-control" readonly>
                        <input type="hidden" id="studID" name="studID" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeViewModal()">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">View Room Change Request</h3>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="viewReqID">Request ID</label>
                    <input type="text" id="viewReqID" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="viewType">Type</label>
                    <input type="text" id="viewType" value="ROOM CHANGE" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="viewStatus">Status</label>
                    <input type="text" id="viewStatus" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="viewDescription">Description</label>
                    <textarea id="viewDescription" class="form-control" readonly rows="4"></textarea>
                </div>
                <div class="form-group">
                    <label for="viewRequestedDate">Requested Date</label>
                    <input type="text" id="viewRequestedDate" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="viewBookID">Booking ID</label>
                    <input type="text" id="viewBookID" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="viewRoom">Room</label>
                    <div class="room-input-group">
                        <input type="text" id="viewRoom" class="form-control" readonly>
                        <button type="button" class="btn" onclick="viewRoomDetailsFromView()">View Room</button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="viewStudent">Student Name</label>
                    <input type="text" id="viewStudent" class="form-control" readonly>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Room Details Modal -->
    <div id="roomDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeRoomDetailsModal()">&times;</span>
            <div class="modal-header">
                <h3 class="modal-title">Room Details</h3>
            </div>
            <div class="modal-body">
                <div id="roomDetailsContent">
                    <!-- Room details will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeRoomDetailsModal()">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        // Submit form using AJAX to handle room change requests
        function submitRoomChangeForm(event) {
            event.preventDefault();
            
            // Get form data
            const formData = new FormData(document.getElementById('editForm'));
            const status = document.getElementById('status').value;
            
            // Show loading state
            const submitButton = event.submitter;
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = 'Processing...';
            submitButton.disabled = true;
            
            // Send AJAX request
            fetch('admProcessRoomChange.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if status is APPROVED or REJECTED to show email notification
                if (status === 'APPROVED' || status === 'REJECTED') {
                    // Send email notification
                    const emailData = new FormData();
                    emailData.append('studID', document.getElementById('studID').value);
                    emailData.append('reqID', document.getElementById('reqID').value);
                    emailData.append('type', 'ROOM CHANGE');
                    emailData.append('status', status);
                    
                    return fetch('admSendRequestStatusEmail.php', {
                        method: 'POST',
                        body: emailData
                    })
                    .then(emailResponse => emailResponse.json())
                    .then(emailResult => {
                        if (emailResult.success) {
                            alert('Email notification sent successfully to ' + document.getElementById('student').value);
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

        function updateSearchField() {
            const searchCriteria = document.getElementById('searchCriteria').value;
            const searchValueField = document.getElementById('searchValueField');
            
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
                            <select name="searchReqID" class="form-control">
                                <option value="">Select Request ID</option>
                                <?php foreach ($requestIDs as $id): ?>
                                <option value="<?php echo $id; ?>" <?php echo $searchReqID === $id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($id); ?>
                                </option>
                                <?php endforeach; ?>
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
                                <label>From Date</label>
                                <input type="date" name="searchDateFrom" class="form-control" value="<?php echo $searchDateFrom; ?>" required>
                            </div>
                            <div class="form-group">
                                <label>To Date</label>
                                <input type="date" name="searchDateTo" class="form-control" value="<?php echo $searchDateTo; ?>" required onchange="validateDateRange()">
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
                            <input type="text" name="searchStudent" class="form-control" value="<?php echo htmlspecialchars($searchStudent); ?>" placeholder="Enter student name">
                        </div>
                    `;
                    break;
            }
        }
        
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
        
        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }
        
        function closeRoomDetailsModal() {
            document.getElementById('roomDetailsModal').style.display = 'none';
        }
        
        function editRequest(reqID) {
            // Fetch request details and populate modal
            fetch(`admGetRequestDetails.php?reqID=${reqID}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('reqID').value = data.ReqID;
                    document.getElementById('description').value = data.Description;
                    updateCharCount(document.getElementById('description'));
                    document.getElementById('requestedDate').value = data.RequestedDate;
                    document.getElementById('bookID').value = data.BookID;
                    document.getElementById('room').value = data.RoomInfo;
                    document.getElementById('roomID').value = data.RoomID;
                    document.getElementById('student').value = data.StudentName;
                    document.getElementById('studID').value = data.StudID;
                    document.getElementById('studID').value = data.StudID;
                    
                    // Set the status dropdown
                    const statusDropdown = document.getElementById('status');
                    for (let i = 0; i < statusDropdown.options.length; i++) {
                        if (statusDropdown.options[i].value === data.Status) {
                            statusDropdown.selectedIndex = i;
                            break;
                        }
                    }
                    
                    document.getElementById('editModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load request details');
                });
        }
        
        function deleteRequest(reqID) {
            if (confirm('Are you sure you want to delete this request?')) {
                window.location.href = `admDeleteRequest.php?reqID=${reqID}`;
            }
        }
        
        function viewRequest(reqID) {
            // Fetch request details and populate modal
            fetch(`admGetRequestDetails.php?reqID=${reqID}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('viewReqID').value = data.ReqID;
                    document.getElementById('viewDescription').value = data.Description;
                    document.getElementById('viewRequestedDate').value = data.RequestedDate;
                    document.getElementById('viewBookID').value = data.BookID;
                    document.getElementById('viewRoom').value = data.RoomInfo;
                    document.getElementById('viewStatus').value = data.Status;
                    document.getElementById('viewStudent').value = data.StudentName;
                    
                    // Store room ID for view room button
                    document.getElementById('viewRoom').dataset.roomId = data.RoomID;
                    
                    document.getElementById('viewModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load request details');
                });
        }
        
        function viewRoomDetails() {
            const roomID = document.getElementById('roomID').value;
            if (roomID) {
                fetchRoomDetails(roomID);
            } else {
                alert('Room ID not available');
            }
        }
        
        function viewRoomDetailsFromView() {
            const roomID = document.getElementById('viewRoom').dataset.roomId;
            if (roomID) {
                fetchRoomDetails(roomID);
            } else {
                alert('Room ID not available');
            }
        }
        
        function fetchRoomDetails(roomID) {
            fetch(`admGetRoomDetails.php?roomID=${roomID}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        throw new Error(data.error || 'Error loading room details');
                    }
                    
                    // Extract base room number (without the type suffix)
                    const baseRoomNo = data.data.RoomNo.slice(0, -1);
                    
                    let html = `
                        <h4>Room Information</h4>
                        <p><strong>Base Room Number:</strong> ${baseRoomNo}</p>
                        <p><strong>Hostel:</strong> ${data.data.HostelName}</p>
                        <p><strong>Status:</strong> ${data.data.Status}</p>
                        
                        <h4>Room Types Available:</h4>
                        <table class="room-details-table">
                            <thead>
                                <tr>
                                    <th>Room Number</th>
                                    <th>Type</th>
                                    <th>Capacity</th>
                                    <th>Current Occupancy</th>
                                    <th>Availability</th>
                                </tr>
                            </thead>
                            <tbody>
                    `;
                    
                    // Create a map of room occupants by room number
                    const occupantsByRoom = {};
                    if (data.data.occupants && data.data.occupants.length > 0) {
                        data.data.occupants.forEach(occupant => {
                            if (!occupantsByRoom[occupant.RoomNo]) {
                                occupantsByRoom[occupant.RoomNo] = [];
                            }
                            occupantsByRoom[occupant.RoomNo].push(occupant);
                        });
                    }
                    
                    // Display all room types for the same base room number
                    if (data.data.roomTypes && data.data.roomTypes.length > 0) {
                        data.data.roomTypes.forEach(room => {
                            // Count actual occupants for this specific room
                            const actualOccupants = occupantsByRoom[room.RoomNo] ? occupantsByRoom[room.RoomNo].length : 0;
                            
                            html += `
                                <tr>
                                    <td>${room.RoomNo}</td>
                                    <td>${room.Type}</td>
                                    <td>${room.Capacity}</td>
                                    <td>${actualOccupants}</td>
                                    <td>${room.Availability}</td>
                                </tr>
                            `;
                        });
                    } else {
                        html += `
                            <tr>
                                <td>${data.data.RoomNo}</td>
                                <td>${data.data.Type}</td>
                                <td>${data.data.Capacity}</td>
                                <td>${data.data.CurrentOccupancy}</td>
                                <td>${data.data.Availability}</td>
                            </tr>
                        `;
                    }
                    
                    html += `
                            </tbody>
                        </table>
                        
                        <h4>Current Occupants:</h4>
                    `;
                    
                    if (data.data.occupants && data.data.occupants.length > 0) {
                        html += `
                            <table class="room-details-table">
                                <thead>
                                    <tr>
                                        <th>Room</th>
                                        <th>Student Name</th>
                                        <th>Room Sharing Style</th>
                                    </tr>
                                </thead>
                                <tbody>
                        `;
                        
                        data.data.occupants.forEach(occupant => {
                            html += `
                                <tr>
                                    <td>${occupant.RoomNo}</td>
                                    <td>${occupant.FullName}</td>
                                    <td>${occupant.RoomSharingStyle || 'Not specified'}</td>
                                </tr>
                            `;
                        });
                        
                        html += `
                                </tbody>
                            </table>
                        `;
                    } else {
                        html += `<p>No current occupants</p>`;
                    }
                    
                    document.getElementById('roomDetailsContent').innerHTML = html;
                    document.getElementById('roomDetailsModal').style.display = 'block';
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load room details: ' + error.message);
                });
        }
        
        function updateCharCount(textarea) {
            const maxLength = textarea.maxLength;
            const currentLength = textarea.value.length;
            document.getElementById('charCount').textContent = currentLength;
        }
        
        function printReport() {
            window.print();
        }
        
        // Initialize search fields and character count on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSearchField();
            if (document.getElementById('description')) {
                updateCharCount(document.getElementById('description'));
            }
        });
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target === document.getElementById('editModal')) {
                closeModal();
            } else if (event.target === document.getElementById('viewModal')) {
                closeViewModal();
            } else if (event.target === document.getElementById('roomDetailsModal')) {
                closeRoomDetailsModal();
            }
        }
    </script>
</body>
</html>
