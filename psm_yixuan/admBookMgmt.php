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

// Include the booking status email functionality
include 'admSendBookingStatusEmail.php';

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

// Initialize search and sort parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : '';
$searchBookID = isset($_GET['searchBookID']) ? $_GET['searchBookID'] : '';
$searchHostel = isset($_GET['searchHostel']) ? $_GET['searchHostel'] : '';
$searchRoom = isset($_GET['searchRoom']) ? strtoupper($_GET['searchRoom']) : '';
$searchYear = isset($_GET['searchYear']) ? $_GET['searchYear'] : '';
$searchDateFrom = isset($_GET['searchDateFrom']) ? $_GET['searchDateFrom'] : '';
$searchDateTo = isset($_GET['searchDateTo']) ? $_GET['searchDateTo'] : '';
$searchStatus = isset($_GET['searchStatus']) ? $_GET['searchStatus'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'BookingDate';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'DESC';

// Get unique booking IDs, hostels, academic years for dropdowns
$bookingIDs = [];
$hostels = [];
$academicYears = [];
try {
    // Get booking IDs
    $stmt = $conn->prepare("SELECT DISTINCT BookID FROM BOOKING ORDER BY BookID");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookingIDs[] = $row['BookID'];
    }
    
    // Get hostels
    $stmt = $conn->prepare("
        SELECT DISTINCT h.HostID, h.Name 
        FROM BOOKING b
        JOIN ROOM r ON b.RoomID = r.RoomID
        JOIN HOSTEL h ON r.HostID = h.HostID
        ORDER BY h.Name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $hostels[] = $row;
    }
    
    // Get academic years
    $stmt = $conn->prepare("
        SELECT DISTINCT s.AcademicYear 
        FROM BOOKING b
        JOIN SEMESTER s ON b.SemID = s.SemID
        ORDER BY s.AcademicYear DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $academicYears[] = $row['AcademicYear'];
    }
} catch (Exception $e) {
    error_log("Error getting dropdown data: " . $e->getMessage());
}

// Get bookings with student information
$bookings = [];
try {
    $query = "
        SELECT b.*, r.RoomNo, h.Name as HostelName, s.AcademicYear, s.Semester, 
               s.CheckInDate, s.CheckOutDate, s.HostelFee, st.FullName as StudentName
        FROM BOOKING b
        JOIN ROOM r ON b.RoomID = r.RoomID
        JOIN HOSTEL h ON r.HostID = h.HostID
        JOIN SEMESTER s ON b.SemID = s.SemID
        JOIN STUDENT st ON b.StudID = st.StudID
    ";
    
    $params = [];
    $types = "";
    
    // Add search conditions
    if ($searchBookID) {
        $query .= " AND b.BookID = ?";
        $params[] = $searchBookID;
        $types .= "s";
    }
    if ($searchHostel) {
        $query .= " AND h.HostID = ?";
        $params[] = $searchHostel;
        $types .= "s";
    }
    if ($searchRoom) {
        $query .= " AND r.RoomNo LIKE ?";
        $params[] = "%$searchRoom%";
        $types .= "s";
    }
    if ($searchYear) {
        $query .= " AND s.AcademicYear = ?";
        $params[] = $searchYear;
        $types .= "s";
    }
    if ($searchDateFrom && $searchDateTo) {
        $query .= " AND b.BookingDate BETWEEN ? AND ?";
        $params[] = $searchDateFrom;
        $params[] = $searchDateTo;
        $types .= "ss";
    }
    if ($searchStatus) {
        $query .= " AND b.Status = ?";
        $params[] = $searchStatus;
        $types .= "s";
    }
    
    // Add sorting
    $validSortColumns = ['BookID', 'RoomNo', 'BookingDate', 'StudentName'];
    $validSortOrders = ['ASC', 'DESC'];
    
    if (in_array($sortBy, $validSortColumns) && in_array($sortOrder, $validSortOrders)) {
        $query .= " ORDER BY " . ($sortBy === 'RoomNo' ? 'r.RoomNo' : 
                                ($sortBy === 'StudentName' ? 'st.FullName' : "b.$sortBy")) . " $sortOrder";
    } else {
        $query .= " ORDER BY b.BookingDate DESC";
    }
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting booking data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management - Admin</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
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
        
        .main-content {
            padding: 30px 0;
        }
        
        /* Section titles and headings */
        .section-title {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            font-family: 'Arial', sans-serif;
            font-weight: bold;
            text-align: left;
        }
        
        .results-count {
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-size: 16px;
            font-family: 'Arial', sans-serif;
        }
        
        /* Search section */
        .search-section {
            background-color: var(--white);
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }
        
        .search-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .search-group {
            display: flex;
            flex-direction: column;
        }
        
        .search-group label {
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--text-dark);
            font-size: 16px;
            font-family: 'Arial', sans-serif;
        }
        
        .search-group select,
        .search-group input {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 15px;
            font-family: 'Arial', sans-serif;
        }
        
        .search-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Table styles */
        .table-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 16px;
            font-family: 'Arial', sans-serif;
            line-height: 1.4;
            vertical-align: middle;
        }
        
        th {
            background-color: var(--primary-color);
            color: var(--white);
            font-weight: bold;
            height: auto;
            min-height: 45px;
        }
        
        tr:hover {
            background-color: #edf3ff !important;
            cursor: pointer;
            box-shadow: 0 3px 5px rgba(0,0,0,0.1);
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        /* Button styles */
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 16px;
            font-family: 'Arial', sans-serif;
            text-decoration: none;
            display: inline-block;
            min-width: 100px;
            text-align: center;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: var(--text-dark);
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        /* Add gold button style - changed to primary blue */
        .btn-blue {
            margin-left: 10px;
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-blue:hover {
            background-color: var(--secondary-color);
            color: white;
        }
        
        /* Status badge styles */
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
        
        .status-pending {
            background-color: var(--warning-color);
            color: var(--text-dark);
        }
        
        .status-approved {
            background-color: var(--success-color);
            color: var(--white);
        }
        
        .status-rejected {
            background-color: var(--danger-color);
            color: var(--white);
        }
        
        /* Button container for vertical layout */
        .button-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: max-content;
        }
        
        .button-row {
            display: flex;
            width: 100%;
        }
        
        .button-row .btn {
            width: 100%;
            white-space: nowrap;
            margin: 0;
        }
        
        /* Modal styles */
        .modal-dialog {
            max-width: 700px;
        }
        
        .modal-content {
            position: relative;
            background-color: var(--white);
            margin: 20px auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        
        .modal-title {
            color: var(--primary-color);
            font-size: 20px;
            font-weight: bold;
            font-family: 'Arial', sans-serif;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--text-dark);
            font-size: 16px;
            font-family: 'Arial', sans-serif;
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 15px;
            font-family: 'Arial', sans-serif;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(44, 157, 255, 0.1);
        }
        
        .form-control[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding-top: 15px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Print styles for report */
        @media print {
            header, .search-section, .btn-group, .modal {
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
                background-color: transparent !important;
                color: #000 !important;
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
        
        @media (max-width: 768px) {
            .search-row {
                grid-template-columns: 1fr;
            }
            
            .search-buttons {
                justify-content: center;
            }
            
            .btn {
                width: 100%;
            }
        }
        
        /* Room modal table styles */
        #roomModalBody .table-responsive {
            margin-bottom: 20px;
        }
        
        #roomModalBody .table {
            margin-bottom: 0;
        }
        
        #roomModalBody .table th,
        #roomModalBody .table td {
            padding: 8px 12px;
            vertical-align: middle;
        }
        
        #roomModalBody .table-bordered th,
        #roomModalBody .table-bordered td {
            border: 1px solid #dee2e6;
        }
        
        #roomModalBody .table-sm th,
        #roomModalBody .table-sm td {
            padding: 6px 10px;
        }
    </style>
</head>
<body>
    <?php include 'includes/adminNav.php'; ?>
    
    <div class="container main-content">
        <h2 class="page-title text-center" style="color: #25408f; font-weight: bold; font-size: 27px; margin-bottom: 30px;">Booking Management</h2>
        
        <!-- Add message display section -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
                <?php 
                    echo htmlspecialchars($_SESSION['message']); 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>
        
        <!-- Search section -->
        <div class="search-section">
            <h3 class="section-title">Search Bookings</h3>
            <form method="GET" action="" id="searchForm">
                <div class="search-row">
                    <div class="search-group">
                        <label for="searchCriteria">Search By:</label>
                        <select name="searchCriteria" id="searchCriteria" class="form-control" onchange="updateSearchField()">
                            <option value="">All</option>
                            <option value="bookingID" <?php echo isset($_GET['searchCriteria']) && $_GET['searchCriteria'] === 'bookingID' ? 'selected' : ''; ?>>Booking ID</option>
                            <option value="hostel" <?php echo isset($_GET['searchCriteria']) && $_GET['searchCriteria'] === 'hostel' ? 'selected' : ''; ?>>Hostel</option>
                            <option value="room" <?php echo isset($_GET['searchCriteria']) && $_GET['searchCriteria'] === 'room' ? 'selected' : ''; ?>>Room</option>
                            <option value="academicYear" <?php echo isset($_GET['searchCriteria']) && $_GET['searchCriteria'] === 'academicYear' ? 'selected' : ''; ?>>Academic Year</option>
                            <option value="bookingDate" <?php echo isset($_GET['searchCriteria']) && $_GET['searchCriteria'] === 'bookingDate' ? 'selected' : ''; ?>>Booking Date</option>
                            <option value="status" <?php echo isset($_GET['searchCriteria']) && $_GET['searchCriteria'] === 'status' ? 'selected' : ''; ?>>Status</option>
                        </select>
                    </div>

                    <div class="search-group" id="searchValueContainer">
                        <!-- This will be dynamically populated by JavaScript -->
                    </div>
                </div>

                <div class="search-row">
                    <div class="search-group">
                        <label for="sortBy">Sort By:</label>
                        <select name="sortBy" id="sortBy" class="form-control">
                            <option value="BookID" <?php echo $sortBy === 'BookID' ? 'selected' : ''; ?>>Booking ID</option>
                            <option value="RoomNo" <?php echo $sortBy === 'RoomNo' ? 'selected' : ''; ?>>Room</option>
                            <option value="BookingDate" <?php echo $sortBy === 'BookingDate' ? 'selected' : ''; ?>>Booking Date</option>
                            <option value="StudentName" <?php echo $sortBy === 'StudentName' ? 'selected' : ''; ?>>Student Name</option>
                        </select>
                    </div>

                    <div class="search-group">
                        <label for="sortOrder">Sort Order:</label>
                        <select name="sortOrder" id="sortOrder" class="form-control">
                            <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                        </select>
                    </div>
                    <div class="search-buttons" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <button type="button" class="btn btn-warning" onclick="printReport()">Generate Report</button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Bookings table -->
        <div class="table-container">
            <h2 class="section-title">Booking Records</h2>
            <?php if (empty($bookings)): ?>
                <p>No booking records found.</p>
            <?php else: ?>
                <p class="results-count">Total Results: <?php echo count($bookings); ?></p>
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Student Name</th>
                            <th>Hostel</th>
                            <th>Room</th>
                            <th>Semester</th>
                            <th>Hostel Fee</th>
                            <th>Booking Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['BookID']); ?></td>
                                <td><?php echo htmlspecialchars($booking['StudentName']); ?></td>
                                <td><?php echo htmlspecialchars($booking['HostelName']); ?></td>
                                <td><?php echo htmlspecialchars($booking['RoomNo']); ?></td>
                                <td>
                                    <?php 
                                        echo "Year " . htmlspecialchars($booking['AcademicYear']) . 
                                             " Sem " . htmlspecialchars($booking['Semester']) .
                                             "<br>(" . 
                                             htmlspecialchars(date('d/m/Y', strtotime($booking['CheckInDate']))) . ' - ' . 
                                             htmlspecialchars(date('d/m/Y', strtotime($booking['CheckOutDate']))) . ")";
                                    ?>
                                </td>
                                <td><?php echo 'RM ' . number_format($booking['HostelFee'], 2); ?></td>
                                <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($booking['BookingDate']))); ?></td>
                                <td class="text-center">
                                    <span class="status-badge status-<?php echo strtolower($booking['Status']); ?>">
                                        <?php echo htmlspecialchars($booking['Status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="button-container">
                                        <?php if ($booking['Status'] === 'PENDING'): ?>
                                            <div class="button-row">
                                                <button type="button" class="btn btn-primary" onclick="editBooking('<?php echo htmlspecialchars($booking['BookID']); ?>')">
                                                    Edit
                                                </button>
                                            </div>
                                            <div class="button-row">
                                                <button type="button" class="btn btn-danger" onclick="deleteBooking('<?php echo htmlspecialchars($booking['BookID']); ?>')">
                                                    Delete
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="button-row">
                                                <button type="button" class="btn btn-primary" onclick="viewBooking('<?php echo htmlspecialchars($booking['BookID']); ?>')">
                                                    View
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit/View Booking Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1" role="dialog" aria-labelledby="bookingModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookingModalLabel">Booking Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="bookingForm">
                        <div class="form-group">
                            <label for="modalBookID">Booking ID:</label>
                            <input type="text" class="form-control" id="modalBookID" readonly>
                        </div>
                        <div class="form-group">
                            <label for="modalHostelName">Hostel:</label>
                            <input type="text" class="form-control" id="modalHostelName" readonly>
                        </div>
                        <div class="form-group">
                            <label for="modalRoom">Room:</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="modalRoom" readonly>
                                <div class="input-group-append">
                                    <button class="btn btn-blue" type="button" onclick="viewRoomDetails()">View Room</button>
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="modalSemester">Semester:</label>
                            <input type="text" class="form-control" id="modalSemester" readonly>
                        </div>
                        <div class="form-group">
                            <label for="modalBookingDate">Booking Date:</label>
                            <input type="text" class="form-control" id="modalBookingDate" readonly>
                        </div>
                        <div class="form-group">
                            <label for="modalStatus">Status:</label>
                            <select class="form-control" id="modalStatus" onchange="toggleRejectedReasonField()">
                                <option value="PENDING">Pending</option>
                                <option value="APPROVED">Approved</option>
                                <option value="REJECTED">Rejected</option>
                            </select>
                        </div>
                        <div class="form-group" id="rejectedReasonGroup" style="display: none;">
                            <label for="modalRejectedReason">Rejected Reason: <span class="text-danger">*</span></label>
                            <select class="form-control" id="modalRejectedReason">
                                <option value="">Select a reason</option>
                                <option value="ROOM FULL">Room Full</option>
                                <option value="DOES NOT MEET CRITERIA">Does Not Meet Criteria</option>
                                <option value="OTHER">Other</option>
                            </select>
                            <small class="text-danger" id="rejectedReasonError" style="display: none;">Rejected reason is required when status is Rejected</small>
                        </div>
                        <div class="form-group">
                            <label for="modalStudentName">Student Name:</label>
                            <input type="text" class="form-control" id="modalStudentName" readonly>
                            <input type="hidden" id="modalStudID">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveChanges" onclick="saveBookingChanges()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Room Details Modal -->
    <div class="modal fade" id="roomModal" tabindex="-1" role="dialog" aria-labelledby="roomModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="roomModalLabel">Room Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="roomModalBody">
                    <!-- Room details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script>
        function updateSearchField() {
            const searchCriteria = document.getElementById('searchCriteria').value;
            const container = document.getElementById('searchValueContainer');
            
            if (!searchCriteria) {
                container.innerHTML = '';
                return;
            }

            let html = '';
            
            switch (searchCriteria) {
                case 'bookingID':
                    html = `
                        <label for="searchBookID">Booking ID:</label>
                        <select name="searchBookID" id="searchBookID" class="form-control">
                            <option value="">Select Booking ID</option>
                            <?php foreach ($bookingIDs as $id): ?>
                                <option value="<?php echo htmlspecialchars($id); ?>" <?php echo $searchBookID === $id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($id); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    `;
                    break;

                case 'hostel':
                    html = `
                        <label for="searchHostel">Hostel:</label>
                        <select name="searchHostel" id="searchHostel" class="form-control">
                            <option value="">All Hostels</option>
                            <?php foreach ($hostels as $hostel): ?>
                                <option value="<?php echo htmlspecialchars($hostel['HostID']); ?>" <?php echo isset($_GET['searchHostel']) && $_GET['searchHostel'] === $hostel['HostID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($hostel['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    `;
                    break;

                case 'room':
                    html = `
                        <label for="searchRoom">Room:</label>
                        <input type="text" name="searchRoom" id="searchRoom" class="form-control" value="<?php echo htmlspecialchars(isset($_GET['searchRoom']) ? $_GET['searchRoom'] : ''); ?>" placeholder="Enter room number">
                    `;
                    break;

                case 'academicYear':
                    html = `
                        <label for="searchYear">Academic Year:</label>
                        <select name="searchYear" id="searchYear" class="form-control">
                            <option value="">All Years</option>
                            <?php foreach ($academicYears as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>" <?php echo isset($_GET['searchYear']) && $_GET['searchYear'] === $year ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    `;
                    break;

                case 'bookingDate':
                    html = `
                        <label>Booking Date Range:</label>
                        <div style="display: flex; gap: 10px;">
                            <input type="date" name="searchDateFrom" id="searchDateFrom" class="form-control" value="<?php echo htmlspecialchars(isset($_GET['searchDateFrom']) ? $_GET['searchDateFrom'] : ''); ?>" style="width: 50%;" onchange="validateDates()">
                            <input type="date" name="searchDateTo" id="searchDateTo" class="form-control" value="<?php echo htmlspecialchars(isset($_GET['searchDateTo']) ? $_GET['searchDateTo'] : ''); ?>" style="width: 50%;" onchange="validateDates()">
                        </div>
                    `;
                    break;

                case 'status':
                    html = `
                        <label for="searchStatus">Status:</label>
                        <select name="searchStatus" id="searchStatus" class="form-control">
                            <option value="">All Status</option>
                            <option value="PENDING" <?php echo isset($_GET['searchStatus']) && $_GET['searchStatus'] === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                            <option value="APPROVED" <?php echo isset($_GET['searchStatus']) && $_GET['searchStatus'] === 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                            <option value="REJECTED" <?php echo isset($_GET['searchStatus']) && $_GET['searchStatus'] === 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    `;
                    break;
            }
            
            container.innerHTML = html;
        }

        function validateDates() {
            const fromDate = document.getElementById('searchDateFrom');
            const toDate = document.getElementById('searchDateTo');
            
            if (fromDate && toDate && fromDate.value && toDate.value) {
                if (new Date(toDate.value) < new Date(fromDate.value)) {
                    alert('End date cannot be earlier than start date');
                    toDate.value = fromDate.value;
                }
            }
        }

        function printReport() {
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            
            // Create the print content
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Hostel Booking Report</title>
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
                            color: #333;
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
                        <h1>Booking Records</h1>
                    </div>
                    <div class="search-results-container">
                        <div class="search-results">Search Results</div>
                    `;

            // Add total results right after the header
            const totalResults = document.querySelector('.results-count');
            if (totalResults) {
                printContent += `<div class="results-count">${totalResults.textContent}</div></div>`;
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
                            if (index === 6) { // Status column
                                const status = cell.textContent.trim();
                                printContent += `<td><span class="status-badge status-${status.toLowerCase()}">${status}</span></td>`;
                            } else if (index === 6 - 1) { // Hostel Fee column (after Semester)
                                printContent += `<td>${cell.textContent.trim()}</td>`;
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

        function editBooking(bookID) {
            // Set modal title
            document.getElementById('bookingModalLabel').textContent = 'Edit Booking';
            
            // Show loading state
            document.getElementById('modalBookID').value = 'Loading...';
            document.getElementById('modalHostelName').value = 'Loading...';
            document.getElementById('modalRoom').value = 'Loading...';
            document.getElementById('modalSemester').value = 'Loading...';
            document.getElementById('modalBookingDate').value = 'Loading...';
            document.getElementById('modalStatus').value = 'PENDING';
            document.getElementById('modalStudentName').value = 'Loading...';
            document.getElementById('modalStudID').value = '';
            document.getElementById('modalRejectedReason').value = '';
            
            // Enable status field and show save button
            document.getElementById('modalStatus').disabled = false;
            document.getElementById('modalRejectedReason').disabled = false;
            document.getElementById('saveChanges').style.display = 'block';
            
            // Show the modal
            $('#bookingModal').modal('show');
            
            // Fetch booking details
            fetch('admBookingActions.php?action=getBooking&bookID=' + encodeURIComponent(bookID))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalBookID').value = data.data.BookID;
                        document.getElementById('modalHostelName').value = data.data.HostelName;
                        document.getElementById('modalRoom').value = data.data.RoomNo;
                        document.getElementById('modalSemester').value = `Year ${data.data.AcademicYear} Sem ${data.data.Semester}`;
                        document.getElementById('modalBookingDate').value = new Date(data.data.BookingDate).toLocaleDateString();
                        document.getElementById('modalStatus').value = data.data.Status;
                        document.getElementById('modalStudentName').value = data.data.StudentName;
                        document.getElementById('modalStudID').value = data.data.StudID;
                        
                        // Set rejected reason if available
                        if (data.data.RejectedReason) {
                            document.getElementById('modalRejectedReason').value = data.data.RejectedReason;
                        }
                        
                        // Show/hide rejected reason field based on status
                        toggleRejectedReasonField();
                    } else {
                        throw new Error(data.message || 'Error loading booking details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading booking details: ' + error.message);
                    $('#bookingModal').modal('hide');
                });
        }

        function viewBooking(bookID) {
            // Set modal title
            document.getElementById('bookingModalLabel').textContent = 'View Booking';
            
            // Show loading state
            document.getElementById('modalBookID').value = 'Loading...';
            document.getElementById('modalHostelName').value = 'Loading...';
            document.getElementById('modalRoom').value = 'Loading...';
            document.getElementById('modalSemester').value = 'Loading...';
            document.getElementById('modalBookingDate').value = 'Loading...';
            document.getElementById('modalStatus').value = 'PENDING';
            document.getElementById('modalStudentName').value = 'Loading...';
            document.getElementById('modalRejectedReason').value = '';
            
            // Disable status field and hide save button
            document.getElementById('modalStatus').disabled = true;
            document.getElementById('modalRejectedReason').disabled = true;
            document.getElementById('saveChanges').style.display = 'none';
            
            // Show the modal
            $('#bookingModal').modal('show');
            
            // Fetch booking details
            fetch('admBookingActions.php?action=getBooking&bookID=' + encodeURIComponent(bookID))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        document.getElementById('modalBookID').value = data.data.BookID;
                        document.getElementById('modalHostelName').value = data.data.HostelName;
                        document.getElementById('modalRoom').value = data.data.RoomNo;
                        document.getElementById('modalSemester').value = `Year ${data.data.AcademicYear} Sem ${data.data.Semester}`;
                        document.getElementById('modalBookingDate').value = new Date(data.data.BookingDate).toLocaleDateString();
                        document.getElementById('modalStatus').value = data.data.Status;
                        document.getElementById('modalStudentName').value = data.data.StudentName;
                        
                        // Set rejected reason if available
                        if (data.data.Status === 'REJECTED') {
                            document.getElementById('rejectedReasonGroup').style.display = 'block';
                            document.getElementById('modalRejectedReason').value = data.data.RejectedReason || 'Not specified';
                        } else {
                            document.getElementById('rejectedReasonGroup').style.display = 'none';
                        }
                    } else {
                        throw new Error(data.message || 'Error loading booking details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading booking details: ' + error.message);
                    $('#bookingModal').modal('hide');
                });
        }

        function viewRoomDetails() {
            const roomNo = document.getElementById('modalRoom').value;
            const hostelName = document.getElementById('modalHostelName').value;
            
            if (roomNo === 'Loading...' || hostelName === 'Loading...') {
                alert('Please wait for booking details to load first.');
                return;
            }
            
            // Show loading state
            document.getElementById('roomModalBody').innerHTML = '<p>Loading room details...</p>';
            $('#roomModal').modal('show');
            
            // Fetch room details
            fetch('admBookingActions.php?action=getRoomDetails&roomNo=' + encodeURIComponent(roomNo) + '&hostelName=' + encodeURIComponent(hostelName))
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Extract base room number (without the type suffix)
                        const baseRoomNo = data.data.RoomNo.slice(0, -1);
                        
                        let html = `
                            <h6 style="font-weight: bold; margin-bottom: 15px;">Room Information:</h6>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p><strong>Base Room Number:</strong> ${baseRoomNo}</p>
                                    <p><strong>Hostel:</strong> ${data.data.HostelName}</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> ${data.data.Status}</p>
                                </div>
                            </div>
                            
                            <h6 style="font-weight: bold; margin-top: 20px; margin-bottom: 15px;">Room Types Available:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr class="bg-light">
                                            <th style="width: 20%">Room Number</th>
                                            <th style="width: 15%">Type</th>
                                            <th style="width: 15%">Capacity</th>
                                            <th style="width: 20%">Current Occupancy</th>
                                            <th style="width: 30%">Availability</th>
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
                        
                        // Check if roomTypes exists
                        if (data.data.roomTypes && Object.keys(data.data.roomTypes).length > 0) {
                            // Display all room types for the same base room number
                            Object.values(data.data.roomTypes).forEach(room => {
                                const roomType = room.Type;
                                // Count actual occupants for this specific room
                                const actualOccupants = occupantsByRoom[room.RoomNo] ? occupantsByRoom[room.RoomNo].length : 0;
                                
                                html += `
                                    <tr>
                                        <td>${room.RoomNo}</td>
                                        <td>${roomType}</td>
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
                            </div>
                            
                            <h6 style="font-weight: bold; margin-top: 20px; margin-bottom: 15px;">Current Occupants:</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr class="bg-light">
                                            <th style="width: 25%">Room</th>
                                            <th style="width: 45%">Student Name</th>
                                            <th style="width: 30%">Room Sharing Style</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        
                        if (data.data.occupants && data.data.occupants.length > 0) {
                            data.data.occupants.forEach(occupant => {
                                html += `
                                    <tr>
                                        <td>${occupant.RoomNo}</td>
                                        <td>${occupant.StudentName}</td>
                                        <td>${occupant.RoomSharingStyle ? occupant.RoomSharingStyle : 'No preference specified'}</td>
                                    </tr>
                                `;
                            });
                        } else {
                            html += '<tr><td colspan="3" class="text-center">No current occupants</td></tr>';
                        }
                        
                        html += `
                                    </tbody>
                                </table>
                            </div>
                        `;
                        
                        document.getElementById('roomModalBody').innerHTML = html;
                    } else {
                        throw new Error(data.message || 'Error loading room details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('roomModalBody').innerHTML = '<p class="text-danger">Error loading room details: ' + error.message + '</p>';
                });
        }

        function toggleRejectedReasonField() {
            const status = document.getElementById('modalStatus').value;
            const rejectedReasonGroup = document.getElementById('rejectedReasonGroup');
            
            if (status === 'REJECTED') {
                rejectedReasonGroup.style.display = 'block';
            } else {
                rejectedReasonGroup.style.display = 'none';
                document.getElementById('rejectedReasonError').style.display = 'none';
            }
        }

        function saveBookingChanges() {
            const bookID = document.getElementById('modalBookID').value;
            const status = document.getElementById('modalStatus').value;
            const studentName = document.getElementById('modalStudentName').value;
            let rejectedReason = null;
            
            if (bookID === 'Loading...') {
                alert('Please wait for booking details to load first.');
                return;
            }
            
            // Check if rejected reason is required
            if (status === 'REJECTED') {
                rejectedReason = document.getElementById('modalRejectedReason').value;
                if (!rejectedReason) {
                    document.getElementById('rejectedReasonError').style.display = 'block';
                    return;
                }
            }
            
            // Show confirmation dialog
            if (!confirm('Are you sure you want to update this booking\'s status to ' + status + '?')) {
                return;
            }
            
            // Disable form while saving
            document.getElementById('modalStatus').disabled = true;
            document.getElementById('modalRejectedReason').disabled = true;
            document.getElementById('saveChanges').disabled = true;
            
            // First update the booking status
            const formData = new FormData();
            formData.append('action', 'updateBooking');
            formData.append('bookID', bookID);
            formData.append('status', status);
            
            if (status === 'REJECTED' && rejectedReason) {
                formData.append('rejectedReason', rejectedReason);
            }
            
            fetch('admBookingActions.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Failed to parse JSON:', text);
                        throw new Error('Invalid JSON response: ' + text);
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    // If status is APPROVED or REJECTED, send email notification
                    if (status === 'APPROVED' || status === 'REJECTED') {
                        // Get student ID from hidden field
                        const studID = document.getElementById('modalStudID').value;
                        
                        // Prepare email data
                        const emailData = new FormData();
                        emailData.append('studId', studID);
                        emailData.append('bookId', bookID);
                        emailData.append('status', status);
                        
                        if (status === 'REJECTED' && rejectedReason) {
                            emailData.append('rejectedReason', rejectedReason);
                        }
                        
                        // Send email notification
                        return fetch('admSendBookingStatusEmail.php', {
                            method: 'POST',
                            body: emailData
                        })
                        .then(emailResponse => emailResponse.json())
                        .then(emailData => {
                            if (emailData.success) {
                                alert(`Booking updated successfully. Email notification sent to ${studentName}.`);
                            } else {
                                alert(`Booking updated successfully, but email notification failed: ${emailData.message}`);
                            }
                            location.reload();
                        });
                    } else {
                        alert('Booking updated successfully.');
                        location.reload();
                    }
                } else {
                    throw new Error(data.message || 'Error updating booking');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating booking: ' + error.message);
                // Re-enable form
                document.getElementById('modalStatus').disabled = false;
                document.getElementById('modalRejectedReason').disabled = false;
                document.getElementById('saveChanges').disabled = false;
            });
        }

        function deleteBooking(bookID) {
            if (!confirm('Are you sure you want to delete this booking? This action cannot be undone.')) {
                return;
            }
            
            fetch('admBookingActions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=deleteBooking&bookID=${encodeURIComponent(bookID)}`
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert('Booking deleted successfully');
                    location.reload();
                } else {
                    throw new Error(data.message || 'Error deleting booking');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deleting booking: ' + error.message);
            });
        }

        // Initialize search field on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSearchField();
        });
    </script>
</body>
</html> 