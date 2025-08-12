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
if (basename($_SERVER['PHP_SELF']) === 'admSendRequestStatusEmail.php' && 
    (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN')) {
    header("Location: staffMainPage.php");
    exit();
}

/**
 * Send email notification for request status changes
 * 
 * @param string $studId Student ID
 * @param string $reqId Request ID
 * @param string $reqType Request type (ROOM CHANGE, MAINTENANCE, COMPLAINT)
 * @param string $status New status of the request
 * @return array Success status and message
 */
function sendRequestStatusEmail($studId, $reqId, $reqType, $status) {
    global $conn;
    
    // Log function call for debugging
    error_log("sendRequestStatusEmail called with: studId=$studId, reqId=$reqId, reqType=$reqType, status=$status");
    
    try {
        // Get student email and request details
        $stmt = $conn->prepare("
            SELECT s.FullName, s.PersonalEmail, r.Description, r.RequestedDate, 
                   CONCAT(COALESCE(rm.RoomNo, 'N/A'), ' (', COALESCE(h.Name, 'N/A'), ')') as RoomInfo
            FROM STUDENT s
            JOIN REQUEST r ON s.StudID = r.StudID
            LEFT JOIN ROOM rm ON r.RoomID = rm.RoomID
            LEFT JOIN HOSTEL h ON rm.HostID = h.HostID
            WHERE s.StudID = ? AND r.ReqID = ?
        ");
        $stmt->bind_param("ss", $studId, $reqId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $studentName = $row['FullName'];
            $studentEmail = $row['PersonalEmail'];
            $requestDescription = $row['Description'];
            $requestDate = date('d/m/Y', strtotime($row['RequestedDate']));
            $roomInfo = $row['RoomInfo'];
            
            // Log the data retrieved for debugging
            error_log("Email data: studentName=$studentName, studentEmail=$studentEmail, requestDate=$requestDate, roomInfo=$roomInfo");
            
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
                
                // Set email subject and color based on request type and status
                $subjectPrefix = '';
                $headerColor = '#23527c'; // Default blue color
                $statusColor = '#23527c'; // Default blue color
                
                switch ($reqType) {
                    case 'ROOM CHANGE':
                        $subjectPrefix = 'Room Change Request';
                        break;
                    case 'MAINTENANCE':
                        $subjectPrefix = 'Maintenance Request';
                        break;
                    case 'COMPLAINT':
                        $subjectPrefix = 'Complaint';
                        break;
                }
                
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
                    case 'IN PROGRESS':
                        $headerColor = '#ffc107'; // Yellow
                        $statusColor = '#ffc107';
                        $mail->Subject = "$subjectPrefix In Progress";
                        break;
                    case 'RESOLVED':
                        $headerColor = '#28a745'; // Green
                        $statusColor = '#28a745';
                        $mail->Subject = "$subjectPrefix Resolved";
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
                            <h2>' . $subjectPrefix . ' ' . $status . '</h2>
                        </div>
                        <div class="content">
                            <p>Dear <strong>' . $studentName . '</strong>,</p>
                            
                            <p>We would like to inform you that your ' . strtolower($reqType) . ' request has been <span class="important">' . $status . '</span>.</p>
                            
                            <p>Here are the details of your request:</p>
                            
                            <table>
                                <tr>
                                    <th>Request ID</th>
                                    <td>' . $reqId . '</td>
                                </tr>
                                <tr>
                                    <th>Type</th>
                                    <td>' . $reqType . '</td>
                                </tr>
                                <tr>
                                    <th>Room</th>
                                    <td>' . $roomInfo . '</td>
                                </tr>
                                <tr>
                                    <th>Requested Date</th>
                                    <td>' . $requestDate . '</td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td><span class="important">' . $status . '</span></td>
                                </tr>
                                <tr>
                                    <th>Description</th>
                                    <td>' . nl2br(htmlspecialchars($requestDescription)) . '</td>
                                </tr>
                            </table>';
                
                // Add specific notes based on status
                switch ($status) {
                    case 'APPROVED':
                        if ($reqType === 'ROOM CHANGE') {
                            $emailBody .= '
                            <div class="note">
                                <p><strong>Next Steps:</strong></p>
                                <p>Your room change request has been approved.</p>
                            </div>';
                        }
                        break;
                    case 'REJECTED':
                        $emailBody .= '
                        <div class="note">
                            <p><strong>Note:</strong></p>
                            <p>If you have any questions regarding this decision, please contact the hostel office for more information.</p>
                        </div>';
                        break;
                    case 'IN PROGRESS':
                        $emailBody .= '
                        <div class="note">
                            <p><strong>Note:</strong></p>
                            <p>Your request is currently being processed. Our team is working to address your concerns.</p>
                        </div>';
                        break;
                    case 'RESOLVED':
                        $emailBody .= '
                        <div class="note">
                            <p><strong>Note:</strong></p>
                            <p>Your request has been resolved. If you are not satisfied with the resolution, please submit a new request.</p>
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
            return ['success' => false, 'message' => 'Student or request not found'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

// Handle direct POST requests to send email
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check for different possible parameter names
    $studId = isset($_POST['studId']) ? $_POST['studId'] : (isset($_POST['studID']) ? $_POST['studID'] : '');
    $reqId = isset($_POST['reqId']) ? $_POST['reqId'] : (isset($_POST['reqID']) ? $_POST['reqID'] : '');
    $reqType = isset($_POST['reqType']) ? $_POST['reqType'] : (isset($_POST['type']) ? $_POST['type'] : '');
    $status = isset($_POST['status']) ? $_POST['status'] : '';
    
    if (empty($studId) || empty($reqId) || empty($reqType) || empty($status)) {
        echo json_encode([
            'success' => false, 
            'message' => 'Student ID, Request ID, Request Type, and Status are required',
            'debug' => [
                'studId' => $studId,
                'reqId' => $reqId,
                'reqType' => $reqType,
                'status' => $status,
                'post' => $_POST
            ]
        ]);
        exit();
    }
    
    $result = sendRequestStatusEmail($studId, $reqId, $reqType, $status);
    echo json_encode($result);
    exit();
}
?>