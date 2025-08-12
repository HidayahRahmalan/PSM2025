<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not hostel staff
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOSTEL STAFF') {
    header("Location: staffMainPage.php");
    exit();
}

// Check if request is POST and has required parameters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['studId']) && isset($_POST['bookId'])) {
    $studId = $_POST['studId'];
    $bookId = $_POST['bookId'];
    
    try {
        // Get student email and details
        $stmt = $conn->prepare("
            SELECT s.FullName, s.PersonalEmail, b.BookID, r.RoomNo, h.Name AS HostelName, 
                   sem.AcademicYear, sem.Semester, sem.HostelFee
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
            $roomNo = $row['RoomNo'];
            $hostelName = $row['HostelName'];
            $academicYear = $row['AcademicYear'];
            $semester = $row['Semester'];
            $hostelFee = $row['HostelFee'];
            
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
                $mail->Username = 'foodddt@gmail.com'; // Replace with your email
                $mail->Password = 'iuku zphm ikdp gafr'; // Replace with your app password
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                
                // Recipients
                $mail->setFrom('foodddt@gmail.com', 'UTeM SHMS Hostel Staff'); // Replace with your email
                $mail->addAddress($studentEmail, $studentName);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Payment Reminder: Hostel Fee for ' . $academicYear . ' Semester ' . $semester;
                
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
                            background-color: #25408f;
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
                            color: #dc3545;
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
                        .btn {
                            display: inline-block;
                            padding: 10px 20px;
                            background-color: #25408f;
                            color: white;
                            text-decoration: none;
                            border-radius: 5px;
                            margin-top: 15px;
                        }
                    </style>
                </head>
                <body>
                    <div class="container">
                        <div class="header">
                            <h2>Payment Reminder</h2>
                        </div>
                        <div class="content">
                            <p>Dear <strong>' . $studentName . '</strong>,</p>
                            
                            <p>We hope this email finds you well. This is a friendly reminder that your hostel fee payment for <strong>Year ' . $academicYear . ' Semester ' . $semester . '</strong> is currently pending.</p>
                            
                            <p>Please find the details of your booking below:</p>
                            
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
                                    <th>Amount Due</th>
                                    <td>RM ' . number_format($hostelFee, 2) . '</td>
                                </tr>
                            </table>
                            
                            <p class="important">Please make your payment as soon as possible to avoid any inconvenience.</p>
                            
                            <p>To make your payment, please log in to the Student Hostel Management System and navigate to the Payment section.</p>
                            
                            <!--<a href="http://localhost/fyp/studMainPage.php" class="btn">Login to SHMS</a>-->
                            
                            <p>If you have already made the payment, please disregard this reminder.</p>
                            
                            <p>Thank you for your prompt attention to this matter.</p>
                            
                            <p>Best regards,<br>
                            Smart Hostel Management System (SHMS)<br>
                            Hostel Staff</p>
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
                
                // Return success response
                echo json_encode(['success' => true, 'message' => 'Payment reminder sent successfully to ' . $studentName]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error sending email: ' . $mail->ErrorInfo]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Student or booking not found']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
} 