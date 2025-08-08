<?php
// Start session for user data
session_start();

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Set JSON content type header
header('Content-Type: application/json');

// Include database connection
try {
    include 'dbConnection.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
    exit();
}

// Redirect if not logged in or not admin
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get the action from request
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {
    case 'getBooking':
        // Get booking details
        $bookID = isset($_GET['bookID']) ? $_GET['bookID'] : '';
        
        if (empty($bookID)) {
            echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
            exit();
        }
        
        try {
            $stmt = $conn->prepare("
                SELECT b.*, r.RoomNo, h.Name as HostelName, s.AcademicYear, s.Semester, 
                       s.CheckInDate, s.CheckOutDate, st.FullName as StudentName, st.StudID
                FROM BOOKING b
                JOIN ROOM r ON b.RoomID = r.RoomID
                JOIN HOSTEL h ON r.HostID = h.HostID
                JOIN SEMESTER s ON b.SemID = s.SemID
                JOIN STUDENT st ON b.StudID = st.StudID
                WHERE b.BookID = ?
            ");
            $stmt->bind_param("s", $bookID);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $booking = $result->fetch_assoc();
                echo json_encode(['success' => true, 'data' => $booking]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Booking not found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error getting booking details: ' . $e->getMessage()]);
        }
        break;
        
    case 'getRoomDetails':
        // Get room details and current occupants
        $roomNo = isset($_GET['roomNo']) ? $_GET['roomNo'] : '';
        $hostelName = isset($_GET['hostelName']) ? $_GET['hostelName'] : '';
        
        if (empty($roomNo) || empty($hostelName)) {
            echo json_encode(['success' => false, 'message' => 'Room number and hostel name are required']);
            exit();
        }
        
        try {
            // Get room details
            $stmt = $conn->prepare("
                SELECT r.*, h.Name as HostelName
                FROM ROOM r
                JOIN HOSTEL h ON r.HostID = h.HostID
                WHERE r.RoomNo = ? AND h.Name = ?
            ");
            $stmt->bind_param("ss", $roomNo, $hostelName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Room not found']);
                exit();
            }
            
            $roomDetails = $result->fetch_assoc();
            
            // Extract the base room number without the type suffix (e.g., SU-U-01-01 from SU-U-01-01A)
            $baseRoomNo = substr($roomDetails['RoomNo'], 0, -1);
            
            // Get all room types for the same base room number
            $stmt = $conn->prepare("
                SELECT r.RoomID, r.RoomNo, r.Type, r.Capacity, r.CurrentOccupancy, r.Availability, r.Status
                FROM ROOM r
                JOIN HOSTEL h ON r.HostID = h.HostID
                WHERE r.RoomNo LIKE CONCAT(?, '%') AND h.Name = ?
            ");
            $stmt->bind_param("ss", $baseRoomNo, $hostelName);
            $stmt->execute();
            $roomTypesResult = $stmt->get_result();
            
            $roomTypes = [];
            while ($row = $roomTypesResult->fetch_assoc()) {
                $roomTypes[$row['RoomID']] = $row;
            }
            
            // Get current occupants for all room types with student details including RoomSharingStyle
            $stmt = $conn->prepare("
                SELECT b.RoomID, r.RoomNo, s.FullName as StudentName, s.RoomSharingStyle,
                       b.Status as BookingStatus, 
                       CASE 
                           WHEN rq.Type = 'ROOM CHANGE' THEN 'Room Change'
                           ELSE 'New Booking'
                       END as BookingType
                FROM BOOKING b
                JOIN ROOM r ON b.RoomID = r.RoomID
                JOIN STUDENT s ON b.StudID = s.StudID
                LEFT JOIN REQUEST rq ON b.BookID = rq.BookID
                WHERE r.RoomNo LIKE CONCAT(?, '%') 
                AND b.Status = 'APPROVED'
                AND r.HostID = (SELECT HostID FROM HOSTEL WHERE Name = ?)
                ORDER BY r.RoomNo, b.BookingDate DESC
            ");
            $stmt->bind_param("ss", $baseRoomNo, $hostelName);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $occupants = [];
            while ($row = $result->fetch_assoc()) {
                $occupants[] = $row;
            }
            
            $roomDetails['occupants'] = $occupants;
            $roomDetails['roomTypes'] = $roomTypes;
            echo json_encode(['success' => true, 'data' => $roomDetails]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error getting room details: ' . $e->getMessage()]);
        }
        break;
        
    case 'updateBooking':
        // Update booking status
        $bookID = isset($_POST['bookID']) ? $_POST['bookID'] : '';
        $status = isset($_POST['status']) ? $_POST['status'] : '';
        $rejectedReason = isset($_POST['rejectedReason']) ? $_POST['rejectedReason'] : null;
        
        // Debug log
        error_log("Update Booking - BookID: " . $bookID . ", Status: " . $status . ", Reason: " . $rejectedReason);
        
        if (empty($bookID) || empty($status)) {
            echo json_encode(['success' => false, 'message' => 'Booking ID and status are required']);
            exit();
        }
        
        // Validate rejected reason if status is REJECTED
        if ($status === 'REJECTED' && empty($rejectedReason)) {
            echo json_encode(['success' => false, 'message' => 'Rejected reason is required when status is Rejected']);
            exit();
        }
        
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Get current booking status
            $stmt = $conn->prepare("SELECT Status FROM BOOKING WHERE BookID = ?");
            $stmt->bind_param("s", $bookID);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Booking not found');
            }
            
            $currentStatus = $result->fetch_assoc()['Status'];
            error_log("Current booking status: " . $currentStatus);
            
            // Only allow updating PENDING bookings
            if ($currentStatus !== 'PENDING') {
                throw new Exception('Only pending bookings can be updated');
            }
            
            // Update booking status and rejected reason if applicable
            if ($status === 'REJECTED' && $rejectedReason) {
                $stmt = $conn->prepare("UPDATE BOOKING SET Status = ?, RejectedReason = ? WHERE BookID = ?");
                $stmt->bind_param("sss", $status, $rejectedReason, $bookID);
            } else {
                $stmt = $conn->prepare("UPDATE BOOKING SET Status = ?, RejectedReason = NULL WHERE BookID = ?");
                $stmt->bind_param("ss", $status, $bookID);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to update booking: " . $stmt->error);
            }
            
            // If approved, check if there are any other pending bookings for the same room and semester
            if ($status === 'APPROVED') {
                $stmt = $conn->prepare("
                    SELECT b2.BookID 
                    FROM BOOKING b1
                    JOIN BOOKING b2 ON b1.RoomID = b2.RoomID AND b1.SemID = b2.SemID
                    WHERE b1.BookID = ? AND b2.BookID != ? AND b2.Status = 'PENDING'
                ");
                $stmt->bind_param("ss", $bookID, $bookID);
                $stmt->execute();
                $result = $stmt->get_result();
                
                // Auto-reject other pending bookings for the same room and semester
                while ($row = $result->fetch_assoc()) {
                    $rejectStatus = 'REJECTED';
                    $autoRejectReason = 'Room already booked by another student';
                    $stmt = $conn->prepare("UPDATE BOOKING SET Status = ?, RejectedReason = ? WHERE BookID = ?");
                    $stmt->bind_param("sss", $rejectStatus, $autoRejectReason, $row['BookID']);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to auto-reject booking: " . $stmt->error);
                    }
                }
            }
            
            $conn->commit();
            error_log("Booking updated successfully");
            echo json_encode(['success' => true, 'message' => 'Booking updated successfully']);
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Error updating booking: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Error updating booking: ' . $e->getMessage()]);
            exit();
        }
        break;
        
    case 'deleteBooking':
        // Delete booking
        $bookID = isset($_POST['bookID']) ? $_POST['bookID'] : '';
        
        if (empty($bookID)) {
            echo json_encode(['success' => false, 'message' => 'Booking ID is required']);
            exit();
        }
        
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // Check if booking exists and is pending
            $stmt = $conn->prepare("SELECT Status FROM BOOKING WHERE BookID = ?");
            $stmt->bind_param("s", $bookID);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Booking not found');
            }
            
            $status = $result->fetch_assoc()['Status'];
            if ($status !== 'PENDING') {
                throw new Exception('Only pending bookings can be deleted');
            }
            
            // Delete any associated requests
            $stmt = $conn->prepare("DELETE FROM REQUEST WHERE BookID = ?");
            $stmt->bind_param("s", $bookID);
            $stmt->execute();
            
            // Delete the booking
            $stmt = $conn->prepare("DELETE FROM BOOKING WHERE BookID = ?");
            $stmt->bind_param("s", $bookID);
            $stmt->execute();
            
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Booking deleted successfully']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error deleting booking: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?> 