<?php
// Start session for user data if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection if not already included
if (!isset($conn)) {
    include 'dbConnection.php';
}

// Redirect if not logged in or not admin, but only if not being included by another file
if (basename($_SERVER['PHP_SELF']) === 'admSendBookingStatusEmail.php' && 
    (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN')) {
    header("Location: staffMainPage.php");
    exit();
}

/**
 * Send email notification for booking status changes
 * 
 * @param string $studId Student ID
 * @param string $bookId Booking ID
 * @param string $status New status of the booking
 * @param string $rejectedReason Reason for rejection (optional, only for REJECTED status)
 * @return array Success status and message
 */
function sendBookingStatusEmail($studId, $bookId, $status, $rejectedReason = null) {
    global $conn;
    
    // Log function call for debugging
    error_log("sendBookingStatusEmail called with: studId=$studId, bookId=$bookId, status=$status, rejectedReason=$rejectedReason");
    
    try {
        // Get student email and booking details
        $stmt = $conn->prepare("
            SELECT s.FullName, s.PersonalEmail, b.BookingDate, b.RejectedReason,
                   r.RoomNo, h.Name as HostelName,
                   sem.AcademicYear, sem.Semester, sem.CheckInDate, sem.CheckOutDate
            FROM STUDENT s
            JOIN BOOKING b ON s.StudID = b.StudID
            JOIN ROOM r ON b.RoomID = r.RoomID
            JOIN HOSTEL h ON r.HostID = h.HostID
            JOIN SEMESTER sem ON b.SemID = sem.SemID
            WHERE s.StudID = ? AND b.BookID = ?
        ");
        $stmt->bind_param("ss", $studId, $bookId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $studentName = $row['FullName'];
            $studentEmail = $row['PersonalEmail'];
            $bookingDate = date('d/m/Y', strtotime($row['BookingDate']));
            $roomNo = $row['RoomNo'];
            $hostelName = $row['HostelName'];
            $academicYear = $row['AcademicYear'];
            $semester = $row['Semester'];
            $checkInDate = date('d/m/Y', strtotime($row['CheckInDate']));
            $checkOutDate = date('d/m/Y', strtotime($row['CheckOutDate']));
            
            // Use provided rejected reason or get from database if available
            $rejectedReason = $rejectedReason ?: $row['RejectedReason'];
            
            // Log the data retrieved for debugging
            error_log("Email data: studentName=$studentName, studentEmail=$studentEmail, bookingDate=$bookingDate, roomNo=$roomNo, hostelName=$hostelName");
            
            // Send email using PHPMailer
            require 'PHPMailer/src/Exception.php';
            require 'PHPMailer/src/PHPMailer.php';
            require 'PHPMailer/src/SMTP.php';
            
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'foodddt@gmail.com'; // Your email
                $mail->Password = 'iuku zphm ikdp gafr'; // Your app password
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                
                // Recipients
                $mail->setFrom('foodddt@gmail.com', 'UTeM SHMS Admin');
                $mail->addAddress($studentEmail, $studentName);
                
                // Content
                $mail->isHTML(true);
                
                // Set email subject and color based on status
                $subjectPrefix = 'Room Booking';
                $headerColor = '#23527c'; // Default blue color
                $statusColor = '#23527c'; // Default blue color
                
                switch ($status) {
                    case 'APPROVED':
                        $headerColor = '#28a745'; // Green
                        $statusColor = '#28a745';
                        $mail->Subject = "$subjectPrefix Approved";
                        break;
                    case 'REJECTED':
                        $headerColor = '#dc3545'; // Red
                        $statusColor = '#dc3545';
                        $mail->Subject = "$subjectPrefix Rejected";
                        break;
                    default:
                        $mail->Subject = "$subjectPrefix Status Update";
                }
                
                // Email body
                $emailBody = '
                <html>
                <head>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            line-height: 1.6;
                            color: #333;
                        }
                        .container {
                            max-width: 600px;
                            margin: 0 auto;
                            padding: 20px;
                            border: 1px solid #ddd;
                            border-radius: 5px;
                        }
                        .header {
                            background-color: ' . $headerColor . ';
                            color: white;
                            padding: 10px 20px;
                            text-align: center;
                            border-radius: 5px 5px 0 0;
                        }
                        .content {
                            padding: 20px;
                        }
                        .footer {
                            margin-top: 20px;
                            font-size: 12px;
                            text-align: center;
                            color: #666;
                        }
                        .important {
                            color: ' . $statusColor . ';
                            font-weight: bold;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 20px 0;
                        }
                        table, th, td {
                            border: 1px solid #ddd;
                        }
                        th, td {
                            padding: 10px;
                            text-align: left;
                        }
                        th {
                            background-color: #f2f2f2;
                        }
                        .note {
                            background-color: #f8f9fa;
                            padding: 15px;
                            border-left: 4px solid ' . $statusColor . ';
                            margin: 20px 0;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h2>Room Booking ' . $status . '</h2>
                        </div>
                        <div class="content">
                            <p>Dear <strong>' . $studentName . '</strong>,</p>
                            
                            <p>We would like to inform you that your room booking has been <span class="important">' . $status . '</span>.</p>
                            
                            <p>Here are the details of your booking:</p>
                            
                            <table>
                                <tr>
                                    <th>Booking ID</th>
                                    <td>' . $bookId . '</td>
                                </tr>
                                <tr>
                                    <th>Room</th>
                                    <td>' . $roomNo . ' (' . $hostelName . ')</td>
                                </tr>
                                <tr>
                                    <th>Semester</th>
                                    <td>Year ' . $academicYear . ' Semester ' . $semester . '</td>
                                </tr>
                                <tr>
                                    <th>Check-in/Check-out</th>
                                    <td>' . $checkInDate . ' - ' . $checkOutDate . '</td>
                                </tr>
                                <tr>
                                    <th>Booking Date</th>
                                    <td>' . $bookingDate . '</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td><span class="important">' . $status . '</span></td>
                                </tr>';
                
                // Add rejected reason if applicable
                if ($status === 'REJECTED' && $rejectedReason) {
                    $emailBody .= '
                                <tr>
                                    <th>Rejection Reason</th>
                                    <td><span class="important">' . $rejectedReason . '</span></td>
                                </tr>';
                }
                
                $emailBody .= '
                            </table>';
                
                // Add specific notes based on status
                switch ($status) {
                    case 'APPROVED':
                        $emailBody .= '
                        <div class="note">
                            <p><strong>Next Steps:</strong></p>
                            <p>Your room booking has been approved. Please proceed with the payment process if you haven\'t already done so.</p>
                            <p>You can check in to your room starting from ' . $checkInDate . '.</p>
                        </div>';
                        break;
                    case 'REJECTED':
                        $emailBody .= '
                        <div class="note">
                            <p><strong>Note:</strong></p>
                            <p>Your room booking has been rejected due to: <strong>' . $rejectedReason . '</strong></p>
                            <p>If you have any questions regarding this decision, please contact the hostel office for more information.</p>
                        </div>';
                        break;
                }
                
                $emailBody .= '
                            <p>If you have any questions or need assistance, please contact the hostel management office.</p>
                            
                            <p>Thank you for your understanding.</p>
                            
                            <p>Best regards,<br>
                            Smart Hostel Management System (SHMS)<br>
                            Admin Team</p>
                        </div>
                        <div class="footer">
                            <p>This is an automated message. Please do not reply to this email.</p>
                            <p>If you have any questions, please contact the hostel management office.</p>
                        </div>
                    </div>
                </body>
                </html>
                ';
                
                $mail->Body = $emailBody;
                $mail->AltBody = strip_tags(str_replace('<br>', "\n", $emailBody));
                
                $mail->send();
                
                return ['success' => true, 'message' => 'Email notification sent successfully to ' . $studentName];
            } catch (Exception $e) {
                return ['success' => false, 'message' => 'Error sending email: ' . $mail->ErrorInfo];
            }
        } else {
            return ['success' => false, 'message' => 'Student or booking not found'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Handle direct POST requests to send email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for different possible parameter names
    $studId = isset($_POST['studId']) ? $_POST['studId'] : (isset($_POST['studID']) ? $_POST['studID'] : '');
    $bookId = isset($_POST['bookId']) ? $_POST['bookId'] : (isset($_POST['bookID']) ? $_POST['bookID'] : '');
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    $rejectedReason = isset($_POST['rejectedReason']) ? $_POST['rejectedReason'] : null;
    
    if (empty($studId) || empty($bookId) || empty($status)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Student ID, Booking ID, and Status are required',
            'debug' => [
                'studId' => $studId,
                'bookId' => $bookId,
                'status' => $status,
                'rejectedReason' => $rejectedReason,
                'post' => $_POST
            ]
        ]);
        exit();
    }
    
    $result = sendBookingStatusEmail($studId, $bookId, $status, $rejectedReason);
    echo json_encode($result);
    exit();
} 