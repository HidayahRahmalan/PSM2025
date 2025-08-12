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
        // Not a student, redirect
        header("Location: studMainPage.php");
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting student data: " . $e->getMessage());
}

// Get current active semester
$currentSemester = null;
try {
    // Get current semester based on date range
    $stmt = $conn->prepare("
        SELECT SemID, AcademicYear, Semester, CheckInDate, CheckOutDate, HostelFee
        FROM SEMESTER 
        WHERE CURDATE() BETWEEN DATE_SUB(CheckInDate, INTERVAL 1 WEEK) 
                          AND CheckOutDate
        LIMIT 1
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $currentSemester = $result->fetch_assoc();
        error_log("Current semester found - SemID: " . $currentSemester['SemID'] . 
                 ", Academic Year: " . $currentSemester['AcademicYear'] . 
                 ", Semester: " . $currentSemester['Semester'] .
                 ", Check-in: " . $currentSemester['CheckInDate'] .
                 ", Check-out: " . $currentSemester['CheckOutDate']);
    } else {
        error_log("No current semester found based on date range check");
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting semester data: " . $e->getMessage());
}

// Remove the outdated debug messages
if (!$currentSemester) {
    error_log("Debug: No semester found for the current date");
}

// Check if student already has an approved booking for the current semester
$hasApprovedBooking = false;
$currentBooking = null;
if ($currentSemester) {
    try {
        // First, check if student has any approved booking for this semester
        $check_stmt = $conn->prepare("
            SELECT COUNT(*) as booking_count
            FROM BOOKING b
            WHERE b.StudID = ?
            AND b.SemID = ?
            AND b.Status = 'APPROVED'
        ");
        $check_stmt->bind_param("ss", $_SESSION['studID'], $currentSemester['SemID']);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_row = $check_result->fetch_assoc();
        $check_stmt->close();

        if ($check_row['booking_count'] > 0) {
            // If there's an approved booking, get the details
            $stmt = $conn->prepare("
                SELECT b.*, r.RoomNo, h.Name as HostelName 
                FROM BOOKING b
                JOIN ROOM r ON b.RoomID = r.RoomID
                JOIN HOSTEL h ON r.HostID = h.HostID
                WHERE b.StudID = ? 
                AND b.SemID = ? 
                AND b.Status = 'APPROVED'
                ORDER BY b.BookingDate DESC
                LIMIT 1
            ");
            $stmt->bind_param("ss", $_SESSION['studID'], $currentSemester['SemID']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $hasApprovedBooking = true;
                $currentBooking = $result->fetch_assoc();
                error_log("Found approved booking for student " . $_SESSION['studID'] . " in semester " . $currentSemester['SemID']);
            }
            $stmt->close();
        } else {
            error_log("No approved booking found for student " . $_SESSION['studID'] . " in semester " . $currentSemester['SemID']);
        }
    } catch (Exception $e) {
        error_log("Error checking existing booking: " . $e->getMessage());
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'book':
                // Check if student already has a booking for this semester
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count 
                    FROM BOOKING 
                    WHERE StudID = ? 
                    AND SemID = ? 
                    AND (Status = 'APPROVED' OR Status = 'PENDING')
                ");
                $stmt->bind_param("ss", $_SESSION['studID'], $currentSemester['SemID']);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['count'] > 0) {
                    $error = "You already have a pending or approved booking for this semester.";
                    break;
                }
                
                // Create new booking
                $roomID = $_POST['roomID'];
                $currentDate = date('Y-m-d');
                
                $stmt = $conn->prepare("INSERT INTO BOOKING (Status, BookingDate, RoomID, StudID, SemID) VALUES ('PENDING', ?, ?, ?, ?)");
                $stmt->bind_param("ssss", $currentDate, $roomID, $_SESSION['studID'], $currentSemester['SemID']);
                
                if ($stmt->execute()) {
                    $success = "Booking request submitted successfully. Please wait for approval.";
                } else {
                    $error = "Error creating booking. Please try again.";
                }
                break;
        }
    }
}

// Get all hostels
$hostels = [];
try {
    $stmt = $conn->prepare("SELECT * FROM HOSTEL WHERE Status = 'ACTIVE' ORDER BY Name");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $hostels[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting hostels: " . $e->getMessage());
}

// Get rooms for selected hostel
$selectedHostID = isset($_GET['hostID']) ? $_GET['hostID'] : '';
$rooms = [];
if ($selectedHostID) {
    try {
        // First get student's gender
        $genderStmt = $conn->prepare("SELECT Gender FROM STUDENT WHERE StudID = ?");
        $genderStmt->bind_param("s", $_SESSION['studID']);
        $genderStmt->execute();
        $genderResult = $genderStmt->get_result();
        $studentGender = $genderResult->fetch_assoc()['Gender'];
        $genderStmt->close();

        // Then get rooms with gender check
        $stmt = $conn->prepare("
            SELECT r.*, h.Name as HostelName 
            FROM ROOM r 
            JOIN HOSTEL h ON r.HostID = h.HostID 
            WHERE r.HostID = ? 
            AND r.Status = 'ACTIVE' 
            AND r.Availability = 'AVAILABLE'
            AND (
                (h.Name LIKE '%(MALE)%' AND ? = 'M')
                OR (h.Name LIKE '%(FEMALE)%' AND ? = 'F')
            )
            ORDER BY r.RoomNo
        ");
        $stmt->bind_param("sss", $selectedHostID, $studentGender, $studentGender);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rooms[] = $row;
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error getting rooms: " . $e->getMessage());
    }
}

// Get student's booking history
$bookings = [];
try {
    $stmt = $conn->prepare("
        SELECT b.*, r.RoomNo, h.Name as HostelName, s.AcademicYear, s.Semester, s.CheckInDate, s.CheckOutDate
        FROM BOOKING b
        JOIN ROOM r ON b.RoomID = r.RoomID
        JOIN HOSTEL h ON r.HostID = h.HostID
        JOIN SEMESTER s ON b.SemID = s.SemID
        WHERE b.StudID = ?
        ORDER BY b.BookingDate DESC, b.BookID DESC
    ");
    $stmt->bind_param("s", $_SESSION['studID']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Debug information
    error_log("Fetching booking history for student: " . $_SESSION['studID']);
    error_log("Number of bookings found: " . $result->num_rows);
    
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
        // Debug information for each booking
        error_log("Found booking - BookID: " . $row['BookID'] . 
                 ", Status: " . $row['Status'] . 
                 ", Room: " . $row['RoomNo'] . 
                 ", Hostel: " . $row['HostelName']);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting booking history: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Room - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/studentNav.css">
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
        
        /* Main Content */
        .main-content {
            padding: 30px 0;
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
        
        /* Forms and Inputs */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--text-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 16px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(44, 157, 255, 0.1);
        }
        
        /* Tables */
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
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
            font-size: 14px;
            height: 40px;
            line-height: 20px;
            text-decoration: none !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white) !important;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: var(--white);
        }
        
        .btn-warning {
            background-color: var(--warning-color);
            color: var(--text-dark);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
        }
        
        /* Status Badges */
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
        
        /* Messages */
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
        
        .alert-danger {
            background-color: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--danger-color);
            color: var(--danger-color);
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
        
        /* Responsive Design */
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
            
            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/studentNav.php'; ?>
    
    <div class="container main-content">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($currentSemester): ?>
            <div class="content-wrapper">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div style="font-size: 1.1em; font-weight: bold; color: var(--primary-color);">
                        Hostel Fee: RM <?php echo number_format($currentSemester['HostelFee'], 2); ?>
                    </div>
                    <a href="studBookHistory.php" class="btn btn-primary">View Booking History</a>
                </div>
                
                <!-- Booking section -->
                <div class="section">
                    <h2 class="section-title">Book a Room</h2>
                    <form method="GET" class="form-group">
                        <label for="hostID">Select Hostel:</label>
                        <select name="hostID" id="hostID" class="form-control" onchange="this.form.submit()">
                            <option value="">Select a hostel</option>
                            <?php foreach ($hostels as $hostel): ?>
                                <option value="<?php echo htmlspecialchars($hostel['HostID']); ?>" <?php echo $selectedHostID === $hostel['HostID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($hostel['Name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <?php if ($selectedHostID && !empty($rooms)): ?>
                        <div class="table-container" style="margin-top: 20px;">
                            <table>
                                <tr>
                                    <th>Room No</th>
                                    <th>Floor</th>
                                    <th>Type</th>
                                    <th>Capacity</th>
                                    <th>Current Occupancy</th>
                                    <th>Action</th>
                                </tr>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($room['RoomNo']); ?></td>
                                        <td><?php echo htmlspecialchars($room['FloorNo']); ?></td>
                                        <td><?php echo htmlspecialchars($room['Type']); ?></td>
                                        <td><?php echo htmlspecialchars($room['Capacity']); ?></td>
                                        <td><?php echo htmlspecialchars($room['CurrentOccupancy']); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="book">
                                                <input type="hidden" name="roomID" value="<?php echo htmlspecialchars($room['RoomID']); ?>">
                                                <button type="submit" class="btn btn-primary">Book Room</button>
                                            </form>
                                            <button onclick="viewRoomDetails('<?php echo htmlspecialchars($room['RoomID']); ?>')" class="btn btn-primary">View Details</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php elseif ($selectedHostID): ?>
                        <?php
                        // Get hostel details to check gender
                        $hostelStmt = $conn->prepare("SELECT Name FROM HOSTEL WHERE HostID = ?");
                        $hostelStmt->bind_param("s", $selectedHostID);
                        $hostelStmt->execute();
                        $hostelResult = $hostelStmt->get_result();
                        $hostelName = $hostelResult->fetch_assoc()['Name'];
                        $hostelStmt->close();

                        // Get student's gender
                        $genderStmt = $conn->prepare("SELECT Gender FROM STUDENT WHERE StudID = ?");
                        $genderStmt->bind_param("s", $_SESSION['studID']);
                        $genderStmt->execute();
                        $genderResult = $genderStmt->get_result();
                        $studentGender = $genderResult->fetch_assoc()['Gender'];
                        $genderStmt->close();

                        // Check if hostel is for opposite gender
                        if ((strpos($hostelName, '(MALE)') !== false && $studentGender === 'F') ||
                            (strpos($hostelName, '(FEMALE)') !== false && $studentGender === 'M')) {
                            echo '<div class="alert alert-warning">This hostel is designated for ' . 
                                 (strpos($hostelName, '(MALE)') !== false ? 'male' : 'female') . 
                                 ' students only.</div>';
                        } else {
                            echo '<p>No available rooms in this hostel.</p>';
                        }
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">No current semester found. Room booking is not available at this time.</div>
        <?php endif; ?>
    </div>

    <!-- Add room details modal -->
    <div id="roomDetailsModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeRoomDetailsModal()">&times;</span>
            <h2 class="section-title">Room Information</h2>
            <div class="modal-body">
                <div class="room-info">
                    <div class="room-info-label">Room ID:</div>
                    <div id="detailRoomId"></div>
                    <div class="room-info-label">Room No:</div>
                    <div id="detailRoomNo"></div>
                    <div class="room-info-label">Floor No:</div>
                    <div id="detailFloorNo"></div>
                    <div class="room-info-label">Type:</div>
                    <div id="detailType"></div>
                    <div class="room-info-label">Capacity:</div>
                    <div id="detailCapacity"></div>
                    <div class="room-info-label">Current Occupancy:</div>
                    <div id="detailCurrentOccupancy"></div>
                    <div class="room-info-label">Availability:</div>
                    <div id="detailAvailability"></div>
                    <div class="room-info-label">Status:</div>
                    <div id="detailStatus"></div>
                    <div class="room-info-label">Hostel Name:</div>
                    <div id="detailHostelName"></div>
                </div>
                <div id="detailOccupantsSection" style="margin-top: 20px;">
                    <h3>Current Occupants</h3>
                    <div id="detailOccupantsList"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const roomDetailsModal = document.getElementById('roomDetailsModal');

        function closeRoomDetailsModal() {
            roomDetailsModal.style.display = "none";
        }

        async function viewRoomDetails(roomId) {
            try {
                const response = await fetch(`studGetRoomDetailedInfo.php?roomId=${roomId}`);
                if (!response.ok) throw new Error('Failed to fetch room information');
                
                const data = await response.json();
                
                // Update room info
                document.getElementById('detailRoomId').textContent = data.room.RoomID;
                document.getElementById('detailRoomNo').textContent = data.room.RoomNo;
                document.getElementById('detailFloorNo').textContent = data.room.FloorNo;
                document.getElementById('detailType').textContent = data.room.Type;
                document.getElementById('detailCapacity').textContent = data.room.Capacity;
                document.getElementById('detailCurrentOccupancy').textContent = data.room.CurrentOccupancy;
                document.getElementById('detailAvailability').textContent = data.room.Availability;
                document.getElementById('detailStatus').textContent = data.room.Status;
                document.getElementById('detailHostelName').textContent = data.room.HostelName;
                
                // Update occupants list
                const occupantsList = document.getElementById('detailOccupantsList');
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
                
                roomDetailsModal.style.display = "block";
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to fetch room information. Please try again.');
            }
        }

        // Close modal when clicking outside
        window.onclick = (event) => {
            if (event.target === roomDetailsModal) {
                closeRoomDetailsModal();
            }
        }
    </script>
</body>
</html> 