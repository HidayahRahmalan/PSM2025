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

// Get search parameters
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

// Build query with search conditions
$query = "
    SELECT b.*, r.RoomNo, h.Name as HostelName, s.AcademicYear, s.Semester, 
           s.CheckInDate, s.CheckOutDate, st.FullName as StudentName
    FROM BOOKING b
    JOIN ROOM r ON b.RoomID = r.RoomID
    JOIN HOSTEL h ON r.HostID = h.HostID
    JOIN SEMESTER s ON b.SemID = s.SemID
    JOIN STUDENT st ON b.StudID = st.StudID
    WHERE 1=1
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

// Get bookings
$bookings = [];
try {
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
} catch (Exception $e) {
    error_log("Error getting booking data: " . $e->getMessage());
    die("Error generating report");
}

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="booking_report_' . date('Y-m-d_H-i-s') . '.xls"');

// Output the Excel file
?>
<table border="1">
    <tr>
        <th colspan="8" style="background-color: #4CAF50; color: white; text-align: center; font-size: 16px;">
            Hostel Booking Report
        </th>
    </tr>
    <tr>
        <th colspan="8" style="text-align: left; font-size: 12px;">
            Generated on: <?php echo date('d/m/Y H:i:s'); ?>
        </th>
    </tr>
    <?php if ($searchCriteria): ?>
    <tr>
        <th colspan="8" style="text-align: left; font-size: 12px;">
            Filter: <?php echo htmlspecialchars($searchCriteria); ?>
            <?php
            switch ($searchCriteria) {
                case 'bookingID':
                    echo " - Booking ID: " . htmlspecialchars($searchBookID);
                    break;
                case 'hostel':
                    echo " - Hostel: " . htmlspecialchars($searchHostel);
                    break;
                case 'room':
                    echo " - Room: " . htmlspecialchars($searchRoom);
                    break;
                case 'academicYear':
                    echo " - Academic Year: " . htmlspecialchars($searchYear);
                    break;
                case 'bookingDate':
                    echo " - Date Range: " . htmlspecialchars($searchDateFrom) . " to " . htmlspecialchars($searchDateTo);
                    break;
                case 'status':
                    echo " - Status: " . htmlspecialchars($searchStatus);
                    break;
            }
            ?>
        </th>
    </tr>
    <?php endif; ?>
    <tr></tr>
    <tr style="background-color: #f2f2f2;">
        <th>Booking ID</th>
        <th>Student Name</th>
        <th>Hostel</th>
        <th>Room</th>
        <th>Semester</th>
        <th>Check-In Date</th>
        <th>Check-Out Date</th>
        <th>Status</th>
    </tr>
    <?php foreach ($bookings as $booking): ?>
    <tr>
        <td><?php echo htmlspecialchars($booking['BookID']); ?></td>
        <td><?php echo htmlspecialchars($booking['StudentName']); ?></td>
        <td><?php echo htmlspecialchars($booking['HostelName']); ?></td>
        <td><?php echo htmlspecialchars($booking['RoomNo']); ?></td>
        <td><?php echo "Year " . htmlspecialchars($booking['AcademicYear']) . " Sem " . htmlspecialchars($booking['Semester']); ?></td>
        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($booking['CheckInDate']))); ?></td>
        <td><?php echo htmlspecialchars(date('d/m/Y', strtotime($booking['CheckOutDate']))); ?></td>
        <td><?php echo htmlspecialchars($booking['Status']); ?></td>
    </tr>
    <?php endforeach; ?>
</table> 