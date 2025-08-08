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

// Get hostel ID from URL parameter
$hostID = isset($_GET['hostID']) ? $_GET['hostID'] : '';

// Fetch hostel details
$hostel = null;
if ($hostID) {
    $stmt = $conn->prepare("SELECT * FROM HOSTEL WHERE HostID = ?");
    $stmt->bind_param("s", $hostID);
    $stmt->execute();
    $result = $stmt->get_result();
    $hostel = $result->fetch_assoc();
    $stmt->close();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new room
                $roomCode = strtoupper($_POST['roomCode']);
                $roomNo = $_POST['roomNo'];
                $floorNo = $_POST['floorNo'];
                $capacity = $_POST['capacity'];

                // Validate room number (1-20)
                if ($roomNo < 1 || $roomNo > 20) {
                    $error = "Room number must be between 1 and 20";
                    $showAddModal = true; // Flag to show add modal with error
                    break;
                }
                
                // Format room number for checking duplicates
                $formattedFloor = str_pad($floorNo, 2, '0', STR_PAD_LEFT);
                $formattedRoom = str_pad($roomNo, 2, '0', STR_PAD_LEFT);
                $roomPattern = 'S' . $roomCode . '-' . $roomCode . '-' . $formattedFloor . '-' . $formattedRoom;
                
                // Check if room with same number already exists (across ALL hostels)
                $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM ROOM WHERE RoomNo LIKE CONCAT(?, '%')");
                $check_stmt->bind_param("s", $roomPattern);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                $room_exists = $check_result->fetch_assoc()['count'] > 0;
                $check_stmt->close();
                
                if ($room_exists) {
                    $error = "A room with this number already exists. Please choose a different code, room number or floor.";
                    $showAddModal = true; // Flag to show add modal with error
                    break;
                }

                // Call the CREATE_ROOM procedure
                $stmt = $conn->prepare("CALL create_room(?, ?, ?, ?, ?)");
                $stmt->bind_param("ssiii", $hostID, $roomCode, $roomNo, $floorNo, $capacity);

                if ($stmt->execute()) {
                    // Fetch the newly created rooms
                    $fetch_stmt = $conn->prepare("
                        SELECT RoomID, RoomNo, FloorNo, Type, Capacity, HostID 
                        FROM ROOM 
                        WHERE HostID = ? 
                        AND RoomNo LIKE CONCAT('S',?, '-', ?, '-', LPAD(?, 2, '0'), '-', LPAD(?, 2, '0'), '%')
                        ORDER BY RoomID DESC 
                        LIMIT 5
                    ");
                    $formattedFloor = str_pad($floorNo, 2, '0', STR_PAD_LEFT);
                    $formattedRoom = str_pad($roomNo, 2, '0', STR_PAD_LEFT);
                    $fetch_stmt->bind_param("sssss", $hostID, $roomCode, $roomCode, $formattedFloor, $formattedRoom);
                    $fetch_stmt->execute();
                    $result = $fetch_stmt->get_result();
                    
                    // Create audit trail for each room
                    while ($new_room = $result->fetch_assoc()) {
                        $new_data = json_encode([
                            "RoomID" => $new_room['RoomID'],
                            "RoomNo" => $new_room['RoomNo'],
                            "FloorNo" => $new_room['FloorNo'],
                            "Type" => $new_room['Type'],
                            "Capacity" => $new_room['Capacity'],
                            "HostID" => $new_room['HostID']
                        ]);

                        $audit_stmt = $conn->prepare("CALL add_audit_trail(?, ?, ?, ?, ?, ?)");
                        $table_name = "ROOM";
                        $action = "INSERT";
                        $old_data = null;

                        $audit_stmt->bind_param("ssssss", $table_name, $new_room['RoomID'], $action, $_SESSION['empId'], $old_data, $new_data);
                        $audit_stmt->execute();
                        $audit_stmt->close();
                    }
                    $fetch_stmt->close();
                }
                break;

            case 'edit':
                // Get room ID and updated values
                $roomID = $_POST['roomID'];
                $capacity = $_POST['capacity'];
                $currentOccupancy = $_POST['currentOccupancy'];
                $availability = $_POST['availability'];
                $status = $_POST['status'];
                $originalRoomNo = $_POST['originalRoomNo'] ?? '';
                
                // Get room number components if provided
                $roomNumberChanged = false;
                if (isset($_POST['roomCode']) && isset($_POST['roomNumber']) && isset($_POST['floorNumber'])) {
                    $roomCode = strtoupper($_POST['roomCode']);
                    $roomNumber = $_POST['roomNumber'];
                    $floorNumber = $_POST['floorNumber'];
                    $roomNumberChanged = true;
                    
                    // Validate room number (1-20)
                    if ($roomNumber < 1 || $roomNumber > 20) {
                        $error = "Room number must be between 1 and 20";
                        break;
                    }
                    
                    // Format room number for checking duplicates
                    $formattedFloor = str_pad($floorNumber, 2, '0', STR_PAD_LEFT);
                    $formattedRoom = str_pad($roomNumber, 2, '0', STR_PAD_LEFT);
                    $roomPattern = 'S' . $roomCode . '-' . $roomCode . '-' . $formattedFloor . '-' . $formattedRoom;
                    
                    // Get the type from the original room number (last character)
                    $roomType = substr($originalRoomNo, -1);
                    
                    // Construct the new full room number
                    $newRoomNo = $roomPattern . $roomType;
                    
                    // Check if the room number is actually changing
                    if ($newRoomNo !== $originalRoomNo) {
                        // Extract the base pattern from the original room number (without the type)
                        $originalParts = explode('-', $originalRoomNo);
                        $originalPattern = $originalParts[0] . '-' . $originalParts[1] . '-' . $originalParts[2] . '-' . preg_replace('/[^0-9]/', '', $originalParts[3]);
                        
                        // Check if room with same number already exists (across ALL hostels)
                        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM ROOM WHERE RoomNo LIKE CONCAT(?, '%') AND RoomID != ? AND NOT RoomNo LIKE CONCAT(?,'%')");
                        $check_stmt->bind_param("sss", $roomPattern, $roomID, $originalPattern);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                        $room_exists = $check_result->fetch_assoc()['count'] > 0;
                        $check_stmt->close();
                        
                        if ($room_exists) {
                            $error = "A room with this number already exists. Please choose a different code, room number or floor.";
                            break;
                        }
                        
                        // Find all rooms with the same original pattern for batch update
                        $find_same_pattern_stmt = $conn->prepare("SELECT RoomID, RoomNo FROM ROOM WHERE RoomNo LIKE CONCAT(?, '%') AND HostID = ?");
                        $find_same_pattern_stmt->bind_param("ss", $originalPattern, $hostID);
                        $find_same_pattern_stmt->execute();
                        $pattern_result = $find_same_pattern_stmt->get_result();
                        
                        $rooms_to_update = [];
                        while ($room_row = $pattern_result->fetch_assoc()) {
                            // Get the type suffix from the existing room
                            $room_type_suffix = substr($room_row['RoomNo'], -1);
                            $rooms_to_update[] = [
                                'RoomID' => $room_row['RoomID'],
                                'NewRoomNo' => $roomPattern . $room_type_suffix
                            ];
                        }
                        $find_same_pattern_stmt->close();
                    } else {
                        // If no change, don't update the room number
                        $roomNumberChanged = false;
                    }
                }

                // Validate current occupancy not exceeding capacity
                if ($currentOccupancy > $capacity) {
                    $error = "Current occupancy cannot exceed room capacity";
                    break;
                }

                // Get old room data first
                $old_stmt = $conn->prepare("SELECT * FROM ROOM WHERE RoomID = ?");
                $old_stmt->bind_param("s", $roomID);
                $old_stmt->execute();
                $old_result = $old_stmt->get_result();
                $old_data = $old_result->fetch_assoc();
                $old_stmt->close();

                // Update room
                if ($roomNumberChanged) {
                    // If there are multiple rooms with the same pattern to update
                    if (isset($rooms_to_update) && count($rooms_to_update) > 0) {
                        // Start a transaction for batch update
                        $conn->begin_transaction();
                        $update_success = true;
                        
                        // Update all rooms with the same pattern
                        foreach ($rooms_to_update as $room_update) {
                            $same_pattern_stmt = $conn->prepare("UPDATE ROOM SET RoomNo = ?, FloorNo = ? WHERE RoomID = ?");
                            $same_pattern_stmt->bind_param("sss", $room_update['NewRoomNo'], $formattedFloor, $room_update['RoomID']);
                            
                            if (!$same_pattern_stmt->execute()) {
                                $update_success = false;
                                break;
                            }
                            $same_pattern_stmt->close();
                        }
                        
                        // Update the current room's other attributes
                        $current_room_stmt = $conn->prepare("UPDATE ROOM SET Capacity = ?, CurrentOccupancy = ?, Availability = ?, Status = ? WHERE RoomID = ?");
                        $current_room_stmt->bind_param("iisss", $capacity, $currentOccupancy, $availability, $status, $roomID);
                        
                        if (!$current_room_stmt->execute()) {
                            $update_success = false;
                        }
                        $current_room_stmt->close();
                        
                        // Commit or rollback transaction based on success
                        if ($update_success) {
                            $conn->commit();
                        } else {
                            $conn->rollback();
                            $error = "Failed to update rooms";
                            break;
                        }
                    } else {
                        // Update including room number and floor for a single room
                        $stmt = $conn->prepare("UPDATE ROOM SET RoomNo = ?, FloorNo = ?, Capacity = ?, CurrentOccupancy = ?, Availability = ?, Status = ? WHERE RoomID = ?");
                        $stmt->bind_param("ssiisss", $newRoomNo, $formattedFloor, $capacity, $currentOccupancy, $availability, $status, $roomID);
                        
                        if (!$stmt->execute()) {
                            $error = "Failed to update room";
                            $stmt->close();
                            break;
                        }
                        $stmt->close();
                    }
                } else {
                    // Update without changing room number or floor
                    $stmt = $conn->prepare("UPDATE ROOM SET Capacity = ?, CurrentOccupancy = ?, Availability = ?, Status = ? WHERE RoomID = ?");
                    $stmt->bind_param("iisss", $capacity, $currentOccupancy, $availability, $status, $roomID);
                    
                    if (!$stmt->execute()) {
                        $error = "Failed to update room";
                        $stmt->close();
                        break;
                    }
                    $stmt->close();
                }
                
                // Add audit trail for room update
                $new_data = [
                    "RoomID" => $roomID,
                    "Capacity" => $capacity,
                    "CurrentOccupancy" => $currentOccupancy,
                    "Availability" => $availability,
                    "Status" => $status
                ];
                
                // Add room number and floor if changed
                if ($roomNumberChanged) {
                    // If multiple rooms were updated, include that in the audit
                    if (isset($rooms_to_update) && count($rooms_to_update) > 0) {
                        $updated_rooms = [];
                        foreach ($rooms_to_update as $room_update) {
                            $updated_rooms[$room_update['RoomID']] = $room_update['NewRoomNo'];
                        }
                        $new_data["UpdatedRooms"] = $updated_rooms;
                    } else {
                        $new_data["RoomNo"] = $newRoomNo;
                    }
                    $new_data["FloorNo"] = $formattedFloor;
                }
                
                $new_data_json = json_encode($new_data);
                $old_data_json = json_encode($old_data);

                $audit_stmt = $conn->prepare("CALL add_audit_trail(?, ?, ?, ?, ?, ?)");
                $table_name = "ROOM";
                $action = "UPDATE";

                $audit_stmt->bind_param("ssssss", $table_name, $roomID, $action, $_SESSION['empId'], $old_data_json, $new_data_json);
                $audit_stmt->execute();
                $audit_stmt->close();
                break;
        }
    }
}

// Fetch rooms for the selected hostel
$rooms = [];
$totalResults = 0;

// Get search parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'RoomID';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'ASC';

// Get all unique room IDs for the dropdown
$roomIds = [];
if ($hostID) {
    try {
        $stmt = $conn->prepare("SELECT DISTINCT RoomID FROM ROOM WHERE HostID = ? ORDER BY RoomID");
        $stmt->bind_param("s", $hostID);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $roomIds[] = $row['RoomID'];
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error getting Room IDs: " . $e->getMessage());
    }
}

// Always fetch results on initial load or when search is performed
if ($hostID) {
    include 'admFetchRoom.php';
}

// Get ALL rooms from ALL hostels for JavaScript validation
$allRooms = [];
try {
    $stmt = $conn->prepare("SELECT RoomID, RoomNo FROM ROOM");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $allRooms[] = $row;
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting all rooms: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - SHMS</title>
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
            --success-color: #28a745;
            --cancel-color: grey;
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
        
        /* Table Styles */
        .table-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
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
            background-color: var(--primary-color);
            color: var(--white);
            font-weight: bold;
        }
        
        tr:hover {
            background-color: rgba(44, 157, 255, 0.1);
        }
        
        /* Custom column widths */
        table .col-id { width: 8%; }
        table .col-room-no { width: 12%; }
        table .col-floor { width: 7%; }
        table .col-type { width: 5%; }
        table .col-capacity { width: 8%; }
        table .col-occupancy { width: 8%; }
        table .col-availability { width: 10%; }
        table .col-hostel { width: 7%; }
        table .col-status { width: 8%; }
        table .col-actions { width: 10%; }
        
        /* Form Styles */
        .form-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-dark);
            font-weight: bold;
        }
        
        input, select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 16px;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: var(--text-dark);
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: var(--white);
        }

        .btn-cancel {
            background-color: var(--cancel-color);
            color: var(--white);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .modal-content {
            position: relative;
            background-color: var(--white);
            margin: 5vh auto;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: var(--shadow);
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
        }
        
        /* Error Message */
        .error-message {
            color: var(--danger-color);
            margin-bottom: 15px;
            padding: 10px;
            background-color: rgba(220, 53, 69, 0.1);
            border-radius: 5px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .logo h1 {
                font-size: 20px;
            }
            
            .page-header h2 {
                font-size: 24px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
        }
        
        /* Search Styles */
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
        
        .search-form .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .results-count {
            margin-bottom: 15px;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        /* Print Styles for Report */
        @media print {
            header, .search-container, .btn-group, .modal, .col-actions {
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
        }
    </style>
</head>
<body>
    <?php include 'includes/adminNav.php'; ?>

    <main class="container">
        <section class="page-header">
            <?php if ($hostel): ?>
                <h2>Manage Rooms - <?php echo htmlspecialchars($hostel['Name']); ?></h2>
            <?php else: ?>
                <h2>Hostel Not Found</h2>
                <p>Please select a valid hostel.</p>
            <?php endif; ?>
        </section>

        <?php if ($hostel): ?>
        <section class="search-container">
            <form action="admManageRoom.php" method="GET" class="search-form">
                <input type="hidden" name="hostID" value="<?php echo htmlspecialchars($hostID); ?>">
                
                <div class="form-group">
                    <label for="searchCriteria">Search By</label>
                    <select id="searchCriteria" name="searchCriteria" class="form-control" onchange="updateSearchField()">
                        <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="RoomID" <?php echo $searchCriteria === 'RoomID' ? 'selected' : ''; ?>>Room ID</option>
                        <option value="RoomNo" <?php echo $searchCriteria === 'RoomNo' ? 'selected' : ''; ?>>Room No</option>
                        <option value="FloorNo" <?php echo $searchCriteria === 'FloorNo' ? 'selected' : ''; ?>>Floor No</option>
                        <option value="Type" <?php echo $searchCriteria === 'Type' ? 'selected' : ''; ?>>Type</option>
                        <option value="Capacity" <?php echo $searchCriteria === 'Capacity' ? 'selected' : ''; ?>>Capacity</option>
                        <option value="CurrentOccupancy" <?php echo $searchCriteria === 'CurrentOccupancy' ? 'selected' : ''; ?>>Current Occupancy</option>
                        <option value="Availability" <?php echo $searchCriteria === 'Availability' ? 'selected' : ''; ?>>Availability</option>
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
                        <option value="RoomID" <?php echo $sortBy === 'RoomID' ? 'selected' : ''; ?>>Room ID</option>
                        <option value="RoomNo" <?php echo $sortBy === 'RoomNo' ? 'selected' : ''; ?>>Room No</option>
                        <option value="FloorNo" <?php echo $sortBy === 'FloorNo' ? 'selected' : ''; ?>>Floor No</option>
                        <option value="CurrentOccupancy" <?php echo $sortBy === 'CurrentOccupancy' ? 'selected' : ''; ?>>Current Occupancy</option>
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
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Room List</h3>
                <button class="btn btn-primary" onclick="openAddModal()">Add New Room</button>
            </div>
            
            <?php if (empty($rooms)): ?>
                <p>No rooms found. Please try a different search criteria or add a new room.</p>
            <?php else: ?>
                <p class="results-count">Total Results: <?php echo $totalResults; ?></p>
            
            <table>
                <thead>
                    <tr>
                        <th class="col-id">Room ID</th>
                        <th class="col-room-no">Room No</th>
                        <th class="col-floor">Floor</th>
                        <th class="col-type">Type</th>
                        <th class="col-capacity">Capacity</th>
                        <th class="col-occupancy">Current Occupancy</th>
                        <th class="col-availability">Availability</th>
                        <th class="col-status">Status</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($room['RoomID']); ?></td>
                        <td><?php echo htmlspecialchars($room['RoomNo']); ?></td>
                        <td><?php echo htmlspecialchars($room['FloorNo']); ?></td>
                        <td><?php echo htmlspecialchars($room['Type']); ?></td>
                        <td><?php echo htmlspecialchars($room['Capacity']); ?></td>
                        <td><?php echo htmlspecialchars($room['CurrentOccupancy'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars($room['Availability'] ?? 'AVAILABLE'); ?></td>
                        <td><?php echo htmlspecialchars($room['Status'] ?? 'ACTIVE'); ?></td>
                        <td>
                            <button class="btn btn-primary btn-sm" onclick='openEditModal(<?php echo htmlspecialchars(json_encode($room)); ?>)'>Edit</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Add Modal -->
        <div id="roomModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h3>Add New Room</h3>
                
                <form id="roomForm" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="hostID" value="<?php echo htmlspecialchars($hostID); ?>">
                    
                    <div class="form-group">
                        <label for="roomCode">Room Code (e.g., Q)</label>
                        <input type="text" id="roomCode" name="roomCode" maxlength="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="roomNo">Room Number (1-20)</label>
                        <input type="number" id="roomNo" name="roomNo" min="1" max="20" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="floorNo">Floor Number (1-9)</label>
                        <input type="number" id="floorNo" name="floorNo" min="1" max="9" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="capacity">Capacity</label>
                        <input type="number" id="capacity" name="capacity" min="1" required>
                    </div>
                    
                    <!-- Error message container inside modal -->
                    <div id="modalErrorMessage" class="error-message" style="display: none;"></div>

                    <div class="btn-group">
                        <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Modal -->
        <div id="editRoomModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h3>Edit Room</h3>
                <form id="editRoomForm" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="roomID" id="editRoomID">
                    <input type="hidden" name="originalRoomNo" id="originalRoomNo">
                    
                    <div class="form-group">
                        <label for="editRoomCode">Room Code (e.g., Q)</label>
                        <input type="text" id="editRoomCode" name="roomCode" maxlength="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editRoomNumber">Room Number (1-20)</label>
                        <input type="number" id="editRoomNumber" name="roomNumber" min="1" max="20" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editFloorNumber">Floor Number (1-9)</label>
                        <input type="number" id="editFloorNumber" name="floorNumber" min="1" max="9" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editType">Type</label>
                        <input type="text" id="editType" name="type" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="editCapacity">Capacity</label>
                        <input type="number" id="editCapacity" name="capacity" min="1" required>
                    </div>

                    <div class="form-group">
                        <label for="editCurrentOccupancy">Current Occupancy</label>
                        <input type="number" id="editCurrentOccupancy" name="currentOccupancy" min="0" readonly>
                    </div>

                    <div class="form-group">
                        <label for="editAvailability">Availability</label>
                        <input type="text" id="editAvailability" name="availability" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="editStatus">Status</label>
                        <select id="editStatus" name="status" required>
                            <option value="ACTIVE">Active</option>
                            <option value="INACTIVE">Inactive</option>
                        </select>
                    </div>
                    
                    <!-- Error message container -->
                    <div id="editRoomErrorContainer" class="error-message" style="display: none;"></div>
                    
                    <div class="btn-group">
                        <button type="button" class="btn btn-cancel" onclick="closeEditModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Modal Functions
        function openAddModal() {
            // Clear any error messages
            document.getElementById('modalErrorMessage').style.display = 'none';
            document.getElementById('modalErrorMessage').textContent = '';
            
            document.getElementById('roomModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('roomModal').style.display = 'none';
        }

        function openEditModal(room) {
            document.getElementById('editRoomID').value = room.RoomID;
            document.getElementById('originalRoomNo').value = room.RoomNo;
            
            // Extract room information from RoomNo (format: SU-U-01-02A)
            const roomParts = room.RoomNo.split('-');
            if (roomParts.length === 4) {
                const roomCode = roomParts[1]; // Get the second part (U)
                const floorNo = parseInt(roomParts[2]); // Get the third part (01)
                
                // The fourth part contains the room number and type (02A)
                // Extract just the numeric part
                const roomNumPart = roomParts[3].match(/^\d+/);
                const roomNumber = roomNumPart ? parseInt(roomNumPart[0]) : 1;
                
                // Set the values in the form
                document.getElementById('editRoomCode').value = roomCode;
                document.getElementById('editRoomNumber').value = roomNumber;
                document.getElementById('editFloorNumber').value = floorNo;
            }
            
            document.getElementById('editType').value = room.Type;
            document.getElementById('editCapacity').value = room.Capacity;
            document.getElementById('editCurrentOccupancy').value = room.CurrentOccupancy;
            document.getElementById('editAvailability').value = room.Availability;
            
            // Set Status with a default value if not present
            if (room.Status) {
                document.getElementById('editStatus').value = room.Status;
            } else {
                document.getElementById('editStatus').value = 'ACTIVE';
            }
            
            // Clear any previous error messages
            document.getElementById('editRoomErrorContainer').style.display = 'none';
            document.getElementById('editRoomErrorContainer').textContent = '';
            
            document.getElementById('editRoomModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editRoomModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Validate room code to be a single letter
        document.getElementById('roomCode').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '');
            if (this.value.length > 1) {
                this.value = this.value.slice(0, 1);
            }
        });

        // Validate edit room code to be a single letter
        document.getElementById('editRoomCode').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '');
            if (this.value.length > 1) {
                this.value = this.value.slice(0, 1);
            }
        });

        // Add validation for current occupancy not exceeding capacity and checking duplicate room numbers
        document.getElementById('editRoomForm').addEventListener('submit', function(e) {
            // Validate capacity and occupancy
            const capacity = parseInt(document.getElementById('editCapacity').value);
            const currentOccupancy = parseInt(document.getElementById('editCurrentOccupancy').value);
            
            if (currentOccupancy > capacity) {
                e.preventDefault();
                const errorContainer = document.getElementById('editRoomErrorContainer');
                errorContainer.textContent = 'Current occupancy cannot exceed room capacity';
                errorContainer.style.display = 'block';
                return false;
            }
            
            // Check for duplicate room numbers if room info has changed
            const roomCode = document.getElementById('editRoomCode').value;
            const roomNumber = document.getElementById('editRoomNumber').value;
            const floorNumber = document.getElementById('editFloorNumber').value;
            const originalRoomNo = document.getElementById('originalRoomNo').value;
            const roomID = document.getElementById('editRoomID').value;
            
            // Format the room number pattern for comparison
            const formattedFloor = floorNumber.toString().padStart(2, '0');
            const formattedRoom = roomNumber.toString().padStart(2, '0');
            
            // Create the room number pattern (without the type letter at the end)
            const roomPattern = 'S' + roomCode + '-' + roomCode + '-' + formattedFloor + '-' + formattedRoom;
            
            // Only check for duplicates if the room number pattern has changed
            if (!originalRoomNo.startsWith(roomPattern)) {
                // Get existing rooms from PHP (ALL hostels)
                const existingRooms = <?php echo json_encode($allRooms); ?>;
                
                // Check if any existing room starts with this pattern, excluding the current room
                const roomExists = existingRooms.some(room => 
                    room.RoomNo.startsWith(roomPattern) && room.RoomID !== roomID
                );
                
                if (roomExists) {
                    // Show error message
                    e.preventDefault();
                    const errorContainer = document.getElementById('editRoomErrorContainer');
                    errorContainer.textContent = 'A room with this number already exists. Please choose a different code, room number or floor.';
                    errorContainer.style.display = 'block';
                    return false;
                }
            }
            
            return true;
        });

        // Check if room number already exists when adding new room
        document.getElementById('roomForm').addEventListener('submit', function(e) {
            // Get form values
            const roomCode = document.getElementById('roomCode').value;
            const roomNo = document.getElementById('roomNo').value;
            const floorNo = document.getElementById('floorNo').value;
            
            // Format the room number pattern for comparison
            const formattedFloor = floorNo.toString().padStart(2, '0');
            const formattedRoom = roomNo.toString().padStart(2, '0');
            
            // Create the room number pattern (without the type letter at the end)
            const roomPattern = 'S' + roomCode + '-' + roomCode + '-' + formattedFloor + '-' + formattedRoom;
            
            // Get existing rooms from PHP (ALL hostels)
            const existingRooms = <?php echo json_encode($allRooms); ?>;
            
            // Check if any existing room starts with this pattern
            const roomExists = existingRooms.some(room => 
                room.RoomNo.startsWith(roomPattern)
            );
            
            if (roomExists) {
                // Show error message in the modal
                e.preventDefault();
                const errorContainer = document.getElementById('modalErrorMessage');
                errorContainer.textContent = 'A room with this number already exists. Please choose a different code, room number or floor.';
                errorContainer.style.display = 'block';
                return false;
            }
            
            return true;
        });

        // Check if we need to show the add modal with error message
        <?php if (isset($showAddModal) && $showAddModal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openAddModal();
            const errorContainer = document.getElementById('modalErrorMessage');
            errorContainer.textContent = '<?php echo addslashes($error); ?>';
            errorContainer.style.display = 'block';
        });
        <?php endif; ?>

        // Function to update search field based on selected criteria
        function updateSearchField() {
            const searchCriteria = document.getElementById('searchCriteria').value;
            const searchValueContainer = document.getElementById('searchValueField');
            const searchValueLabel = document.getElementById('searchValueLabel');
            
            // Get the current search value
            const currentValue = '<?php echo htmlspecialchars($searchValue); ?>';
            
            // Clear the current search value field
            searchValueContainer.innerHTML = '';
            
            // Create the appropriate input field based on the selected criteria
            switch (searchCriteria) {
                case 'All':
                    searchValueLabel.textContent = 'No search value needed';
                    // Not visible to the user on the page 
                    searchValueContainer.innerHTML = '<input type="hidden" name="searchValue" value="">';
                    break;
                    
                case 'RoomID':
                    searchValueLabel.textContent = 'Select Room ID';
                    const roomSelect = document.createElement('select');
                    roomSelect.name = 'searchValue';
                    roomSelect.className = 'form-control';
                    roomSelect.innerHTML = '<option value="">Select Room ID</option>';
                    
                    <?php foreach ($roomIds as $roomId): ?>
                        roomSelect.innerHTML += '<option value="<?php echo $roomId; ?>" <?php echo $searchValue === $roomId ? 'selected' : ''; ?>><?php echo $roomId; ?></option>';
                    <?php endforeach; ?>
                    
                    searchValueContainer.appendChild(roomSelect);
                    break;
                    
                case 'RoomNo':
                    searchValueLabel.textContent = 'Enter Room No';
                    const roomInput = document.createElement('input');
                    roomInput.type = 'text';
                    roomInput.name = 'searchValue';
                    roomInput.className = 'form-control';
                    roomInput.value = currentValue;
                    roomInput.placeholder = 'Enter room number';
                    searchValueContainer.appendChild(roomInput);
                    break;
                    
                case 'FloorNo':
                    searchValueLabel.textContent = 'Select Floor No';
                    const floorSelect = document.createElement('select');
                    floorSelect.name = 'searchValue';
                    floorSelect.className = 'form-control';
                    floorSelect.innerHTML = '<option value="">Select Floor No</option>';
                    
                    // Creating options from 1 to 9
                    for (let i = 1; i <= 9; i++) {
                        const selected = currentValue == i ? 'selected' : '';
                        floorSelect.innerHTML += `<option value="${i}" ${selected}>${i}</option>`;
                    }
                    
                    searchValueContainer.appendChild(floorSelect);
                    break;
                    
                case 'Type':
                    searchValueLabel.textContent = 'Select Room Type';
                    const typeSelect = document.createElement('select');
                    typeSelect.name = 'searchValue';
                    typeSelect.className = 'form-control';
                    typeSelect.innerHTML = `
                        <option value="">Select Type</option>
                        <option value="A" ${currentValue === 'A' ? 'selected' : ''}>A</option>
                        <option value="B" ${currentValue === 'B' ? 'selected' : ''}>B</option>
                        <option value="C" ${currentValue === 'C' ? 'selected' : ''}>C</option>
                        <option value="D" ${currentValue === 'D' ? 'selected' : ''}>D</option>
                        <option value="E" ${currentValue === 'E' ? 'selected' : ''}>E</option>
                    `;
                    searchValueContainer.appendChild(typeSelect);
                    break;
                    
                case 'Capacity':
                    searchValueLabel.textContent = 'Enter Capacity';
                    const capacityInput = document.createElement('input');
                    capacityInput.type = 'number';
                    capacityInput.name = 'searchValue';
                    capacityInput.className = 'form-control';
                    capacityInput.value = currentValue;
                    capacityInput.min = '1';
                    capacityInput.placeholder = 'Enter capacity';
                    searchValueContainer.appendChild(capacityInput);
                    break;
                    
                case 'CurrentOccupancy':
                    searchValueLabel.textContent = 'Enter Current Occupancy';
                    const occupancyInput = document.createElement('input');
                    occupancyInput.type = 'number';
                    occupancyInput.name = 'searchValue';
                    occupancyInput.className = 'form-control';
                    occupancyInput.value = currentValue;
                    occupancyInput.min = '0';
                    occupancyInput.placeholder = 'Enter current occupancy';
                    searchValueContainer.appendChild(occupancyInput);
                    break;
                    
                case 'Availability':
                    searchValueLabel.textContent = 'Select Availability';
                    const availabilitySelect = document.createElement('select');
                    availabilitySelect.name = 'searchValue';
                    availabilitySelect.className = 'form-control';
                    availabilitySelect.innerHTML = `
                        <option value="">Select Availability</option>
                        <option value="AVAILABLE" ${currentValue === 'AVAILABLE' ? 'selected' : ''}>Available</option>
                        <option value="NOT AVAILABLE" ${currentValue === 'NOT AVAILABLE' ? 'selected' : ''}>Not Available</option>
                    `;
                    searchValueContainer.appendChild(availabilitySelect);
                    break;
                    
                case 'Status':
                    searchValueLabel.textContent = 'Select Status';
                    const statusSelect = document.createElement('select');
                    statusSelect.name = 'searchValue';
                    statusSelect.className = 'form-control';
                    statusSelect.innerHTML = `
                        <option value="">Select Status</option>
                        <option value="ACTIVE" ${currentValue === 'ACTIVE' ? 'selected' : ''}>Active</option>
                        <option value="INACTIVE" ${currentValue === 'INACTIVE' ? 'selected' : ''}>Inactive</option>
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
                    <title>Room Report</title>
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
                        <h1>Room List</h1>
                    </div>
                    <div class="search-results">Search Results</div>
                    <div class="results-count">Total Results: ${rows.length - 1}</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Room ID</th>
                                <th>Room No</th>
                                <th>Floor No</th>
                                <th>Type</th>
                                <th>Capacity</th>
                                <th>Current Occupancy</th>
                                <th>Availability</th>
                                <th>Status</th>
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