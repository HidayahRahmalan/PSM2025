<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not student
if (!isset($_SESSION['studID'])) {
    header("Location: studMainPage.php");
    exit();
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

// Get student name
$studentName = "";
try {
    $stmt = $conn->prepare("SELECT FullName FROM STUDENT WHERE StudID = ?");
    $stmt->bind_param("s", $_SESSION['studID']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $studentName = $row['FullName'];
    } else {
        header("Location: studMainPage.php");
        exit();
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting student data: " . $e->getMessage());
}

// Get unique booking IDs, hostels, academic years for dropdowns
$bookingIDs = [];
$hostels = [];
$academicYears = [];
try {
    // Get booking IDs
    $stmt = $conn->prepare("
        SELECT DISTINCT b.BookID 
        FROM BOOKING b 
        WHERE b.StudID = ? 
        ORDER BY b.BookID
    ");
    $stmt->bind_param("s", $_SESSION['studID']);
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
        WHERE b.StudID = ?
        ORDER BY h.Name
    ");
    $stmt->bind_param("s", $_SESSION['studID']);
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
        WHERE b.StudID = ?
        ORDER BY s.AcademicYear DESC
    ");
    $stmt->bind_param("s", $_SESSION['studID']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $academicYears[] = $row['AcademicYear'];
    }
} catch (Exception $e) {
    error_log("Error getting dropdown data: " . $e->getMessage());
}

// Get student's booking history
$bookings = [];
try {
   //With currentSemester is the current semester, LatestApprovedBooking is the latest approved booking for the current semester
    //in latestapprovedbooking, the result will take the latest booking id with the booking date for the current semester
    //rq.Status IN ('PENDING', 'APPROVED') to make sure if the both status occur in request table, 
    //it will not display the room change request button (only one room change request is allowed)
    $query = "
        WITH CurrentSemester AS (
            SELECT SemID 
            FROM SEMESTER 
            WHERE CURDATE() BETWEEN DATE_SUB(CheckInDate, INTERVAL 1 WEEK) AND CheckOutDate
        ),
        LatestApprovedBooking AS (
            SELECT b.BookID
            FROM BOOKING b
            WHERE b.StudID = ? 
            AND b.Status = 'APPROVED'
            AND b.SemID IN (SELECT SemID FROM CurrentSemester)
            ORDER BY b.BookID DESC, b.BookingDate DESC
            LIMIT 1
        )
        SELECT b.*, r.RoomNo, h.Name as HostelName, s.AcademicYear, s.Semester, 
               s.CheckInDate, s.CheckOutDate, s.HostelFee,
               CASE WHEN s.SemID IN (SELECT SemID FROM CurrentSemester) THEN 1 ELSE 0 END as IsCurrentSem,
               CASE WHEN b.BookID IN (SELECT BookID FROM LatestApprovedBooking) THEN 1 ELSE 0 END as IsLatestApproved,
               CASE WHEN EXISTS (
                   SELECT 1 FROM REQUEST rq 
                   WHERE rq.BookID = b.BookID 
                   AND rq.Type = 'ROOM CHANGE'
                   AND rq.Status IN ('PENDING', 'APPROVED')
               ) THEN 1 ELSE 0 END as HasActiveRequest
        FROM BOOKING b
        JOIN ROOM r ON b.RoomID = r.RoomID
        JOIN HOSTEL h ON r.HostID = h.HostID
        JOIN SEMESTER s ON b.SemID = s.SemID
        WHERE b.StudID = ?
    ";
    
    $params = [$_SESSION['studID'], $_SESSION['studID']];
    $types = "ss";
    
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
    $validSortColumns = ['BookID', 'RoomNo', 'BookingDate'];
    $validSortOrders = ['ASC', 'DESC'];
    
    if (in_array($sortBy, $validSortColumns) && in_array($sortOrder, $validSortOrders)) {
        $query .= " ORDER BY " . ($sortBy === 'RoomNo' ? 'r.RoomNo' : "b.$sortBy") . " $sortOrder";
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
    error_log("Error getting booking history: " . $e->getMessage());
}

// Add delete booking functionality
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deleteBooking') {
    $bookID = isset($_POST['bookID']) ? $_POST['bookID'] : '';
    $response = array('success' => false, 'message' => '');

    if (empty($bookID)) {
        $response['message'] = 'Invalid booking ID.';
    } else {
        try {
            // First verify that the booking exists and belongs to the student and is PENDING
            $stmt = $conn->prepare("
                SELECT Status 
                FROM BOOKING 
                WHERE BookID = ? AND StudID = ? AND Status = 'PENDING'
            ");
            $stmt->bind_param("ss", $bookID, $_SESSION['studID']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Delete the booking
                $delete_stmt = $conn->prepare("DELETE FROM BOOKING WHERE BookID = ? AND StudID = ?");
                $delete_stmt->bind_param("ss", $bookID, $_SESSION['studID']);
                
                if ($delete_stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Booking has been successfully deleted.';
                } else {
                    $response['message'] = 'Failed to delete booking.';
                }
                $delete_stmt->close();
            } else {
                $response['message'] = 'Booking not found or cannot be deleted.';
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['message'] = 'An error occurred while deleting the booking.';
            error_log("Error deleting booking: " . $e->getMessage());
        }
    }

    // If it's an AJAX request, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    // If it's a regular form submission, redirect with message
    else {
        $_SESSION['message'] = $response['message'];
        $_SESSION['message_type'] = $response['success'] ? 'success' : 'error';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Add room change request handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'roomChangeRequest') {
    $response = array('success' => false, 'message' => '');
    
    $bookID = isset($_POST['bookID']) ? $_POST['bookID'] : '';
    $newRoomID = isset($_POST['newRoomID']) ? $_POST['newRoomID'] : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    if (empty($bookID) || empty($description) || empty($newRoomID)) {
        $response['message'] = 'All fields are required.';
    } else if (strlen($description) > 250) {
        $response['message'] = 'Description must not exceed 250 characters.';
    } else {
        try {
            // First check if there's an existing active request
            $check_stmt = $conn->prepare("
                SELECT 1 
                FROM REQUEST 
                WHERE BookID = ? 
                AND Type = 'ROOM CHANGE'
                AND Status IN ('PENDING', 'APPROVED')
            ");
            $check_stmt->bind_param("s", $bookID);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $response['message'] = 'You already have an active room change request for this booking.';
            } else {
                // Verify that the booking exists, belongs to the student, is approved, and is in current semester
                $stmt = $conn->prepare("
                    SELECT b.BookID 
                    FROM BOOKING b 
                    JOIN SEMESTER s ON b.SemID = s.SemID
                    WHERE b.BookID = ? 
                    AND b.StudID = ? 
                    AND b.Status = 'APPROVED'
                    AND CURDATE() BETWEEN DATE_SUB(s.CheckInDate, INTERVAL 1 WEEK) AND s.CheckOutDate
                ");
                $stmt->bind_param("ss", $bookID, $_SESSION['studID']);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    // Verify that the new room is available
                    $room_check_stmt = $conn->prepare("
                        SELECT r.RoomID 
                        FROM ROOM r
                        WHERE r.RoomID = ?
                        AND r.Status = 'ACTIVE'
                        AND r.CurrentOccupancy < r.Capacity
                        AND NOT EXISTS (
                            SELECT 1 
                            FROM REQUEST req 
                            WHERE req.RoomID = r.RoomID 
                            AND req.Status = 'APPROVED'
                            AND req.Type = 'ROOM CHANGE'
                        )
                    ");
                    $room_check_stmt->bind_param("s", $newRoomID);
                    $room_check_stmt->execute();
                    $room_result = $room_check_stmt->get_result();

                    if ($room_result->num_rows > 0) {
                        $type = 'ROOM CHANGE';
                        
                        $insert_stmt = $conn->prepare("
                            INSERT INTO REQUEST (Type, Description, RequestedDate, BookID, StudID, RoomID)
                            VALUES (?, ?, CURDATE(), ?, ?, ?)
                        ");
                        $insert_stmt->bind_param("sssss", $type, $description, $bookID, $_SESSION['studID'], $newRoomID);
                        
                        if ($insert_stmt->execute()) {
                            $response['success'] = true;
                            $response['message'] = 'Room change request has been submitted successfully.';
                        } else {
                            $response['message'] = 'Failed to submit request.';
                        }
                        $insert_stmt->close();
                    } else {
                        $response['message'] = 'Selected room is no longer available. Please choose another room.';
                    }
                    $room_check_stmt->close();
                } else {
                    $response['message'] = 'Invalid booking or not authorized for room change request at this time.';
                }
                $stmt->close();
            }
            $check_stmt->close();
        } catch (Exception $e) {
            $response['message'] = 'An error occurred while submitting the request.';
            error_log("Error submitting room change request: " . $e->getMessage());
        }
    }

    // Return JSON response for AJAX request
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Add maintenance/complaint request handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'maintenanceRequest') {
    $response = array('success' => false, 'message' => '');
    
    $bookID = isset($_POST['bookID']) ? $_POST['bookID'] : '';
    $roomID = isset($_POST['roomID']) ? $_POST['roomID'] : '';
    $type = isset($_POST['type']) ? $_POST['type'] : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    if (empty($bookID) || empty($roomID) || empty($type) || empty($description)) {
        $response['message'] = 'All fields are required.';
    } else if (strlen($description) > 250) {
        $response['message'] = 'Description must not exceed 250 characters.';
    } else if (!in_array($type, ['MAINTENANCE', 'COMPLAINT'])) {
        $response['message'] = 'Invalid request type.';
    } else {
        try {
            // Verify that the booking exists, belongs to the student, is approved, and is in current semester
            $stmt = $conn->prepare("
                SELECT b.BookID 
                FROM BOOKING b 
                JOIN SEMESTER s ON b.SemID = s.SemID
                WHERE b.BookID = ? 
                AND b.StudID = ? 
                AND b.Status = 'APPROVED'
                AND CURDATE() BETWEEN DATE_SUB(s.CheckInDate, INTERVAL 1 WEEK) AND s.CheckOutDate
            ");
            $stmt->bind_param("ss", $bookID, $_SESSION['studID']);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Insert the request
                $insert_stmt = $conn->prepare("
                    INSERT INTO REQUEST (Type, Description, RequestedDate, BookID, RoomID, StudID)
                    VALUES (?, ?, CURDATE(), ?, ?, ?)
                ");
                $insert_stmt->bind_param("sssss", $type, $description, $bookID, $roomID, $_SESSION['studID']);
                
                if ($insert_stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Your ' . strtolower($type) . ' request has been submitted successfully.';
                } else {
                    $response['message'] = 'Failed to submit request.';
                }
                $insert_stmt->close();
            } else {
                $response['message'] = 'Invalid booking or not authorized to submit request at this time.';
            }
            $stmt->close();
        } catch (Exception $e) {
            $response['message'] = 'An error occurred while submitting the request.';
            error_log("Error submitting maintenance/complaint request: " . $e->getMessage());
        }
    }

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking History - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/studentNav.css">
    <style>
        /* Remove the navigation styles since they're now in studentNav.css */
        :root {
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
        
        /* Keep all other styles except navigation styles */
        .main-content {
            padding: 30px 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
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
        
        .section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .section-title {
            color: var(--primary-color);
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            background-color: var(--primary-color);
            color: var(--white);
            font-weight: bold;
        }
        
        tr:hover {
            background-color: rgba(44, 157, 255, 0.1);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
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
        
        /* Add styles for rejected reason */
        .rejected-reason {
            color: var(--danger-color);
            font-weight: bold;
        }
        
        .no-reason {
            color: var(--text-light);
            font-style: italic;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 14px;
            min-height: 40px;
            text-decoration: none;
            margin: 5px;
            white-space: nowrap;
            line-height: 1.2;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        /* Add gold button style */
        .btn-gold {
            background-color: gold;
            color: black;
        }
        
        .btn-gold:hover {
            background-color: #FFD700;
            color: black;
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
            overflow-y: auto;
        }
        
        .modal-content {
            position: relative;
            background-color: var(--white);
            margin: 20px auto;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .close-modal {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .close-modal:hover {
            color: var(--text-dark);
        }
        
        .modal-body {
            margin-top: 20px;
        }
        
        .room-info {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .room-info-label {
            font-weight: bold;
            color: var(--primary-color);
        }
        
        /* Add new styles for search section */
        .search-section {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 20px;
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
        }
        
        .search-group select,
        .search-group input {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-search {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-reset {
            background-color: var(--text-light);
            color: white;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .logo h1 {
                font-size: 20px;
            }
            
            .section-title {
                font-size: 20px;
            }
            
            .search-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Add styles for delete button and messages */
        .btn-delete {
            background-color: var(--danger-color);
            color: white;
        }
        
        .btn-delete:hover {
            background-color: #c82333;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success-color);
            color: var(--success-color);
        }
        
        .alert-error {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
        }
        
        /* Modify button container styles */
        .button-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: max-content;
        }
        
        .button-row {
            display: flex;
            width: 100%;
        }
        
        .button-row .btn {
            width: 100%;
            white-space: nowrap;
        }
        
        /* Add styles for room change request modal */
        .modal-content {
            position: relative;
            background-color: var(--white);
            margin: 50px auto;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: var(--shadow);
        }
        
        .form-group {
            margin-bottom: 15px;
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
        
        .form-control[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        
        .btn-cancel {
            background-color: var(--cancel-color);
            color: var(--white);
        }
        
        .error-message {
            color: var(--danger-color);
            font-size: 14px;
            margin-top: 5px;
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/studentNav.php'; ?>
    
    <div class="container main-content">
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
        
        <div style="display: flex; justify-content: flex-start; margin-bottom: 20px;">
            <a href="studRoomBook.php" class="btn btn-primary">Back to Booking Room</a>
        </div>
        
        <!-- Add search section -->
        <div class="search-section">
            <h3 class="section-title">Search Bookings</h3>
            <form method="GET" action="" id="searchForm">
                <div class="search-row">
                    <div class="search-group">
                        <label for="searchCriteria">Search By:</label>
                        <select name="searchCriteria" id="searchCriteria" onchange="updateSearchField()">
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
                        <select name="sortBy" id="sortBy">
                            <option value="BookID" <?php echo $sortBy === 'BookID' ? 'selected' : ''; ?>>Booking ID</option>
                            <option value="RoomNo" <?php echo $sortBy === 'RoomNo' ? 'selected' : ''; ?>>Room</option>
                            <option value="BookingDate" <?php echo $sortBy === 'BookingDate' ? 'selected' : ''; ?>>Booking Date</option>
                        </select>
                    </div>

                    <div class="search-group">
                        <label for="sortOrder">Sort Order:</label>
                        <select name="sortOrder" id="sortOrder">
                            <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                            <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                        </select>
                    </div>
                    <div class="search-buttons" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-search">Search</button>
                        <a href="studBookHistory.php" class="btn btn-reset">Reset</a>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="section">
            <h2 class="section-title">Your Booking History</h2>
            <?php if (empty($bookings)): ?>
                <p>No booking history found.</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <tr>
                            <th>Booking ID</th>
                            <th>Hostel</th>
                            <th>Room</th>
                            <th>Semester</th>
                            <th>Hostel Fee</th>
                            <th>Booking Date</th>
                            <th>Status</th>
                            <th>Rejected Reason</th>
                            <th>Actions</th>
                        </tr>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($booking['BookID']); ?></td>
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
                                <td><?php echo htmlspecialchars($booking['BookingDate']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($booking['Status']); ?>">
                                        <?php echo htmlspecialchars($booking['Status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($booking['Status'] === 'REJECTED'): ?>
                                        <?php if (!empty($booking['RejectedReason'])): ?>
                                            <span class="rejected-reason"><?php echo htmlspecialchars($booking['RejectedReason']); ?></span>
                                        <?php else: ?>
                                            <span class="no-reason">No reason specified</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="button-container">
                                    <?php if ($booking['Status'] === 'APPROVED'): ?>
                                            <div class="button-row">
                                                <button onclick="viewRoomInfo('<?php echo htmlspecialchars($booking['RoomID']); ?>')" class="btn btn-primary">View Room</button>
                                            </div>
                                            <?php if ($booking['IsCurrentSem'] && $booking['IsLatestApproved']): ?>
                                                <?php if (!$booking['HasActiveRequest']): ?>
                                                    <div class="button-row">
                                                        <button onclick="openRoomChangeModal('<?php echo htmlspecialchars($booking['BookID']); ?>')" class="btn btn-gold">Room Change Request</button>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="alert alert-warning" style="margin: 5px 0; padding: 5px 10px; font-size: 12px;">
                                                        You already have an active room change request for this booking.
                                                    </div>
                                                <?php endif; ?>
                                                <div class="button-row">
                                                    <button onclick="openMaintenanceModal('<?php echo htmlspecialchars($booking['BookID']); ?>', '<?php echo htmlspecialchars($booking['RoomID']); ?>')" class="btn btn-warning">Maintenance/Complaint</button>
                                                </div>
                                            <?php endif; ?>
                                        <?php elseif ($booking['Status'] === 'PENDING'): ?>
                                            <div class="button-row">
                                                <button onclick="viewRoomInfo('<?php echo htmlspecialchars($booking['RoomID']); ?>')" class="btn btn-primary">View Room</button>
                                            </div>
                                            <div class="button-row">
                                                <button onclick="confirmDelete('<?php echo htmlspecialchars($booking['BookID']); ?>')" class="btn btn-delete">Delete</button>
                                            </div>
                                        <?php else: ?>
                                            <div class="button-row">
                                                <button onclick="viewRoomInfo('<?php echo htmlspecialchars($booking['RoomID']); ?>')" class="btn btn-primary">View Room</button>
                                            </div>
                                    <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add modal HTML -->
    <div id="roomModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h2 class="section-title">Room Information</h2>
            <div class="modal-body">
                <div class="room-info">
                    <div class="room-info-label">Room ID:</div>
                    <div id="roomId"></div>
                    <div class="room-info-label">Room No:</div>
                    <div id="roomNo"></div>
                    <div class="room-info-label">Floor No:</div>
                    <div id="floorNo"></div>
                    <div class="room-info-label">Type:</div>
                    <div id="type"></div>
                    <div class="room-info-label">Capacity:</div>
                    <div id="capacity"></div>
                    <div class="room-info-label">Current Occupancy:</div>
                    <div id="currentOccupancy"></div>
                    <div class="room-info-label">Availability:</div>
                    <div id="availability"></div>
                    <div class="room-info-label">Status:</div>
                    <div id="status"></div>
                    <div class="room-info-label">Hostel Name:</div>
                    <div id="hostelName"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add room change request modal -->
    <div id="roomChangeModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeRoomChangeModal()">&times;</span>
            <h3 class="section-title">Room Change Request</h3>
            <form id="roomChangeForm" onsubmit="submitRoomChangeRequest(event)">
                <input type="hidden" id="requestBookID" name="bookID">
                
                <div class="form-group">
                    <label for="requestType">Request Type</label>
                    <input type="text" id="requestType" value="ROOM CHANGE" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label for="requestStatus">Status</label>
                    <input type="text" id="requestStatus" value="PENDING" class="form-control" readonly>
                </div>

                <div class="form-group">
                    <label for="newRoomID">Select New Room <span style="color: red;">*</span></label>
                    <div style="display: flex; gap: 10px;">
                        <select id="newRoomID" name="newRoomID" class="form-control" required>
                            <option value="">Select a room</option>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="viewSelectedRoomInfo()">View Room</button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="description">Description <span style="color: red;">*</span></label>
                    <textarea id="description" name="description" class="form-control" maxlength="250" required 
                        placeholder="Please provide the reason for your room change request"></textarea>
                    <div style="text-align: right; font-size: 0.8em; margin-top: 5px;">
                        <span id="charCount">0</span>/250 characters
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeRoomChangeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add room info modal for room change -->
    <div id="roomChangeInfoModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeRoomChangeInfoModal()">&times;</span>
            <h2 class="section-title">Room Information</h2>
            <div class="modal-body">
                <div class="room-info">
                    <div class="room-info-label">Room ID:</div>
                    <div id="rcRoomId"></div>
                    <div class="room-info-label">Room No:</div>
                    <div id="rcRoomNo"></div>
                    <div class="room-info-label">Floor No:</div>
                    <div id="rcFloorNo"></div>
                    <div class="room-info-label">Type:</div>
                    <div id="rcType"></div>
                    <div class="room-info-label">Capacity:</div>
                    <div id="rcCapacity"></div>
                    <div class="room-info-label">Current Occupancy:</div>
                    <div id="rcCurrentOccupancy"></div>
                    <div class="room-info-label">Availability:</div>
                    <div id="rcAvailability"></div>
                    <div class="room-info-label">Status:</div>
                    <div id="rcStatus"></div>
                    <div class="room-info-label">Hostel Name:</div>
                    <div id="rcHostelName"></div>
                </div>
                <div id="currentOccupantsSection" style="margin-top: 20px;">
                    <h3>Current Occupants</h3>
                    <div id="occupantsList"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add maintenance/complaint request modal -->
    <div id="maintenanceModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeMaintenanceModal()">&times;</span>
            <h3 class="section-title">Maintenance/Complaint Request</h3>
            <form id="maintenanceForm" onsubmit="submitMaintenanceRequest(event)">
                <!-- hidden input function are used to store the bookID and roomID for the maintenance and complaintrequest -->
                <input type="hidden" id="maintenanceBookID" name="bookID">
                <input type="hidden" id="maintenanceRoomID" name="roomID">
                
                <div class="form-group">
                    <label>Request Type <span style="color: red;">*</span></label>
                    <div class="radio-group" style="margin: 10px 0;">
                        <label style="display: inline-block; margin-right: 20px;">
                            <input type="radio" name="requestType" value="MAINTENANCE" required onclick="updateRequestType('MAINTENANCE')"> Maintenance
                        </label>
                        <label style="display: inline-block;">
                            <input type="radio" name="requestType" value="COMPLAINT" onclick="updateRequestType('COMPLAINT')"> Complaint
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="maintenanceType">Type</label>
                    <input type="text" id="maintenanceType" class="form-control" readonly>
                </div>

                <div class="form-group">
                    <label for="maintenanceDescription">Description <span style="color: red;">*</span></label>
                    <textarea id="maintenanceDescription" name="description" class="form-control" maxlength="250" required 
                        placeholder="Please provide details of your maintenance request or complaint"></textarea>
                    <div style="text-align: right; font-size: 0.8em; margin-top: 5px;">
                        <span id="maintenanceCharCount">0</span>/250 characters
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeMaintenanceModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add JavaScript for modal functionality -->
    <script>
        const modal = document.getElementById('roomModal');
        const closeBtn = document.querySelector('.close-modal');

        // Close modal when clicking the close button or outside the modal
        closeBtn.onclick = () => modal.style.display = "none";
        window.onclick = (event) => {
            if (event.target === modal) {
                modal.style.display = "none";
            }
        }

        // Function to fetch and display room information
        async function viewRoomInfo(roomId) {
            try {
                const response = await fetch(`studGetRoomInfo.php?roomId=${roomId}`);
                if (!response.ok) throw new Error('Failed to fetch room information');
                
                const data = await response.json();
                
                // Update modal content with room information
                document.getElementById('roomId').textContent = data.RoomID;
                document.getElementById('roomNo').textContent = data.RoomNo;
                document.getElementById('floorNo').textContent = data.FloorNo;
                document.getElementById('type').textContent = data.Type;
                document.getElementById('capacity').textContent = data.Capacity;
                document.getElementById('currentOccupancy').textContent = data.CurrentOccupancy;
                document.getElementById('availability').textContent = data.Availability;
                document.getElementById('status').textContent = data.Status;
                document.getElementById('hostelName').textContent = data.HostelName;
                
                // Show the modal
                modal.style.display = "block";
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to fetch room information. Please try again.');
            }
        }

        function updateSearchField() {
            const searchCriteria = document.getElementById('searchCriteria').value;
            const container = document.getElementById('searchValueContainer');
            
            // Clear the container if 'All' is selected
            if (!searchCriteria) {
                container.innerHTML = '';
                return;
            }

            let html = '';
            
            switch (searchCriteria) {
                case 'bookingID':
                    html = `
                        <label for="searchBookID">Booking ID:</label>
                        <select name="searchBookID" id="searchBookID">
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
                        <select name="searchHostel" id="searchHostel">
                            <option value="">All</option>
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
                        <input type="text" name="searchRoom" id="searchRoom" value="<?php echo htmlspecialchars(isset($_GET['searchRoom']) ? $_GET['searchRoom'] : ''); ?>" placeholder="Enter room number">
                    `;
                    break;

                case 'academicYear':
                    html = `
                        <label for="searchYear">Academic Year:</label>
                        <select name="searchYear" id="searchYear">
                            <option value="">All</option>
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
                            <input type="date" name="searchDateFrom" id="searchDateFrom" value="<?php echo htmlspecialchars(isset($_GET['searchDateFrom']) ? $_GET['searchDateFrom'] : ''); ?>" style="width: 50%;" onchange="validateDates()">
                            <input type="date" name="searchDateTo" id="searchDateTo" value="<?php echo htmlspecialchars(isset($_GET['searchDateTo']) ? $_GET['searchDateTo'] : ''); ?>" style="width: 50%;" onchange="validateDates()">
                        </div>
                    `;
                    break;

                case 'status':
                    html = `
                        <label for="searchStatus">Status:</label>
                        <select name="searchStatus" id="searchStatus">
                            <option value="">All</option>
                            <option value="PENDING" <?php echo isset($_GET['searchStatus']) && $_GET['searchStatus'] === 'PENDING' ? 'selected' : ''; ?>>Pending</option>
                            <option value="APPROVED" <?php echo isset($_GET['searchStatus']) && $_GET['searchStatus'] === 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                            <option value="REJECTED" <?php echo isset($_GET['searchStatus']) && $_GET['searchStatus'] === 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    `;
                    break;
            }
            
            container.innerHTML = html;
            
            // If it's booking date, initialize date validation
            if (searchCriteria === 'bookingDate') {
                validateDates();
            }
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

        // Modify the JavaScript initialization code
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            let searchCriteria = urlParams.get('searchCriteria');
            
            // Only set specific search criteria if explicitly provided in URL
            if (searchCriteria) {
                document.getElementById('searchCriteria').value = searchCriteria;
            } else {
                document.getElementById('searchCriteria').value = ''; // Default to 'All'
            }
            
            updateSearchField();
        });

        function confirmDelete(bookID) {
            if (confirm('Are you sure you want to delete this booking request?')) {
                // Create form data
                const formData = new FormData();
                formData.append('action', 'deleteBooking');
                formData.append('bookID', bookID);

                // Send AJAX request
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    alert(data.message);
                    if (data.success) {
                        // Reload the page to update the booking list
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the booking.');
                });
            }
        }

        const roomChangeModal = document.getElementById('roomChangeModal');
        const description = document.getElementById('description');
        const charCount = document.getElementById('charCount');

        function openRoomChangeModal(bookID) {
            document.getElementById('requestBookID').value = bookID;
            roomChangeModal.style.display = "block";
            description.value = '';
            updateCharCount();
            loadAvailableRooms(bookID);
        }

        function closeRoomChangeModal() {
            roomChangeModal.style.display = "none";
        }

        // Update character count
        description.addEventListener('input', updateCharCount);

        function updateCharCount() {
            const count = description.value.length;
            charCount.textContent = count;
            charCount.style.color = count > 250 ? 'red' : 'inherit';
        }

        async function submitRoomChangeRequest(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'roomChangeRequest');
            formData.append('bookID', document.getElementById('requestBookID').value);
            formData.append('newRoomID', document.getElementById('newRoomID').value);
            formData.append('description', description.value);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                alert(data.message);
                
                if (data.success) {
                    closeRoomChangeModal();
                    window.location.reload();
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while submitting the request.');
            }
        }

        const roomChangeInfoModal = document.getElementById('roomChangeInfoModal');
        let currentBookID = null;

        async function loadAvailableRooms(bookID) {
            try {
                const response = await fetch(`studGetAvailableRooms.php?bookID=${bookID}`);
                if (!response.ok) throw new Error('Failed to fetch available rooms');
                
                const data = await response.json();
                const roomSelect = document.getElementById('newRoomID');
                roomSelect.innerHTML = '<option value="">Select a room</option>';
                
                data.forEach(room => {
                    const option = document.createElement('option');
                    option.value = room.RoomID;
                    option.textContent = `${room.RoomNo} (${room.HostelName})`;
                    roomSelect.appendChild(option);
                });
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to load available rooms. Please try again.');
            }
        }

        async function viewSelectedRoomInfo() {
            const roomId = document.getElementById('newRoomID').value;
            if (!roomId) {
                alert('Please select a room first');
                return;
            }
            
            try {
                const response = await fetch(`studGetRoomDetailedInfo.php?roomId=${roomId}`);
                if (!response.ok) throw new Error('Failed to fetch room information');
                
                const data = await response.json();
                
                // Update room info
                document.getElementById('rcRoomId').textContent = data.room.RoomID;
                document.getElementById('rcRoomNo').textContent = data.room.RoomNo;
                document.getElementById('rcFloorNo').textContent = data.room.FloorNo;
                document.getElementById('rcType').textContent = data.room.Type;
                document.getElementById('rcCapacity').textContent = data.room.Capacity;
                document.getElementById('rcCurrentOccupancy').textContent = data.room.CurrentOccupancy;
                document.getElementById('rcAvailability').textContent = data.room.Availability;
                document.getElementById('rcStatus').textContent = data.room.Status;
                document.getElementById('rcHostelName').textContent = data.room.HostelName;
                
                // Update occupants list
                const occupantsList = document.getElementById('occupantsList');
                occupantsList.innerHTML = '';
                
                if (data.occupants && data.occupants.length > 0) {
                    const table = document.createElement('table');
                    table.innerHTML = `
                        <tr>
                            <th>Student ID</th>
                            <th>Name</th>
                            <th>Room Sharing Style</th>
                        </tr>
                    `;
                    
                    data.occupants.forEach(occupant => {
                        const row = table.insertRow();
                        row.innerHTML = `
                            <td>${occupant.StudID}</td>
                            <td>${occupant.FullName}</td>
                            <td>${occupant.RoomSharingStyle}</td>
                        `;
                    });
                    
                    occupantsList.appendChild(table);
                } else {
                    occupantsList.innerHTML = '<p>No current occupants</p>';
                }
                
                roomChangeInfoModal.style.display = "block";
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to fetch room information. Please try again.');
            }
        }

        function closeRoomChangeInfoModal() {
            roomChangeInfoModal.style.display = "none";
        }

        // Update window click event handler to handle both modals
        window.onclick = (event) => {
            if (event.target === roomChangeModal) {
                closeRoomChangeModal();
            } else if (event.target === roomChangeInfoModal) {
                closeRoomChangeInfoModal();
            }
        }

        const maintenanceModal = document.getElementById('maintenanceModal');
        const maintenanceDescription = document.getElementById('maintenanceDescription');
        const maintenanceCharCount = document.getElementById('maintenanceCharCount');

        function openMaintenanceModal(bookID, roomID) {
            document.getElementById('maintenanceBookID').value = bookID;
            document.getElementById('maintenanceRoomID').value = roomID;
            maintenanceModal.style.display = "block";
            maintenanceDescription.value = '';
            updateMaintenanceCharCount();
            // Reset radio buttons and type field
            document.querySelectorAll('input[name="requestType"]').forEach(radio => radio.checked = false);
            document.getElementById('maintenanceType').value = '';
        }

        function closeMaintenanceModal() {
            maintenanceModal.style.display = "none";
        }

        function updateRequestType(type) {
            document.getElementById('maintenanceType').value = type;
        }

        // Update character count for maintenance description
        maintenanceDescription.addEventListener('input', updateMaintenanceCharCount);

        function updateMaintenanceCharCount() {
            const count = maintenanceDescription.value.length;
            maintenanceCharCount.textContent = count;
            maintenanceCharCount.style.color = count > 250 ? 'red' : 'inherit';
        }

        async function submitMaintenanceRequest(event) {
            event.preventDefault();
            
            const formData = new FormData();
            formData.append('action', 'maintenanceRequest');
            formData.append('bookID', document.getElementById('maintenanceBookID').value);
            formData.append('roomID', document.getElementById('maintenanceRoomID').value);
            formData.append('type', document.querySelector('input[name="requestType"]:checked').value);
            formData.append('description', maintenanceDescription.value);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                alert(data.message);
                
                if (data.success) {
                    closeMaintenanceModal();
                    window.location.reload();
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while submitting the request.');
            }
        }

        // Update window click event handler to handle all modals
        window.onclick = (event) => {
            if (event.target === roomChangeModal) {
                closeRoomChangeModal();
            } else if (event.target === roomChangeInfoModal) {
                closeRoomChangeInfoModal();
            } else if (event.target === maintenanceModal) {
                closeMaintenanceModal();
            }
        }
    </script>
</body>
</html> 