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

// Handle POST request for updating semester
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $semId = $_POST['semId'];
    $academicYear = $_POST['academicYear'];
    $semester = $_POST['semester'];
    $checkInDate = $_POST['checkInDate'];
    $checkOutDate = $_POST['checkOutDate'];
    $hostelFee = $_POST['hostelFee'];
    
    // Validate academic year format (XXXX/XXXX)
    if (!preg_match('/^\d{4}\/\d{4}$/', $academicYear)) {
        echo json_encode(['success' => false, 'error' => 'Invalid academic year format. Use XXXX/XXXX']);
        exit();
    }
    
    // Validate dates
    if (strtotime($checkOutDate) <= strtotime($checkInDate)) {
        echo json_encode(['success' => false, 'error' => 'Check-out date must be after check-in date']);
        exit();
    }
    
    // Validate hostel fee
    if (!is_numeric($hostelFee) || $hostelFee <= 0) {
        echo json_encode(['success' => false, 'error' => 'Hostel fee must be greater than 0']);
        exit();
    }
    
    // Check for duplicate semester in academic year
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM SEMESTER WHERE AcademicYear = ? AND Semester = ? AND SemID != ?");
    $stmt->bind_param("sis", $academicYear, $semester, $semId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        echo json_encode(['success' => false, 'error' => 'This semester already exists for the selected academic year']);
        exit();
    }
    
    // Update semester
    try {
        $stmt = $conn->prepare("UPDATE SEMESTER SET AcademicYear = ?, Semester = ?, CheckInDate = ?, CheckOutDate = ?, HostelFee = ? WHERE SemID = ?");
        $stmt->bind_param("sissds", $academicYear, $semester, $checkInDate, $checkOutDate, $hostelFee, $semId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update semester']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    
    exit();
}

// Get hostel staff name
$hsName = "";
try {
    $stmt = $conn->prepare("SELECT FullName FROM EMPLOYEE WHERE EmpID = ?");
    $stmt->bind_param("s", $_SESSION['empId']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $hsName = $row['FullName'];
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting hostel staff name: " . $e->getMessage());
}

// Get semester details
$semId = $_GET['id'];
$semester = null;

try {
    $stmt = $conn->prepare("SELECT * FROM SEMESTER WHERE SemID = ?");
    $stmt->bind_param("s", $semId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $semester = $result->fetch_assoc();
    } else {
        header("Location: hsViewSem.php");
        exit();
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting semester details: " . $e->getMessage());
    header("Location: hsViewSem.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Semester - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
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
            padding: 1rem 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logo img {
            height: 50px;
        }
        
        .logo h1 {
            font-size: 1.5rem;
            color: var(--primary-color);
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
        main {
            padding: 2rem 0;
        }
        
        .page-header {
            margin-bottom: 25px;
            margin-top: 25px;
            text-align: center;
        }
        
        .page-header h2 {
            color: var(--primary-color);
            font-size: 28px;
        }
        
        .btn {
            padding: 12px 20px;
            font-size: 16px;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            min-width: 100px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }

        .btn-secondary {
            background-color: var(--text-light);
            color: var(--white);
        }

        .btn-secondary:hover {
            background-color: var(--text-dark);
        }

        .button-group {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Form Styles */
        .form-container {
            background-color: var(--white);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: var(--shadow);
        }
        
        .form-row {
            margin-bottom: 1.5rem;
        }
        
        .form-row label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: var(--text-dark);
        }
        
        .form-row input,
        .form-row select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-row input:read-only {
            background-color: var(--light-bg);
        }
        
        .error-message {
            color: var(--danger);
            font-size: 16px;
            margin-top: 0.25rem;
            display: none;
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem;
            margin-bottom: 1rem;
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
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="navbar">
                <div class="logo">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/LogoUTeM-2016.jpg" alt="UTeM Logo">
                    <h1>Smart Hostel Management System</h1>
                </div>
                <div class="nav-right">
                    <div class="nav-links">
                        <a href="staffHomePage.php">Home</a>
                        <a href="hsUserMgmt.php">User</a>
                        <a href="hsBookNSemMgmt.php">Book&Sem</a>
                        <a href="hsHostelRoomMgmt.php">Hostel</a>
                        <a href="hsProfile.php">Profile</a>
                        <a href="hsLogout.php">Logout</a>
                        <div class="profile-icon" onclick="window.location.href='hsProfile.php'">
                            <i class="fa fa-user-circle" style="font-size: 24px; color: var(--primary-color);"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container">
        <div class="page-header">
            <h2>Edit Semester</h2>
        </div>

        <div class="form-container">
            <form id="editForm">
                <input type="hidden" name="semId" value="<?php echo htmlspecialchars($semester['SemID']); ?>">
                
                <div class="form-row">
                    <label for="semId">Semester ID</label>
                    <input type="text" id="semId" value="<?php echo htmlspecialchars($semester['SemID']); ?>" readonly>
                </div>
                
                <div class="form-row">
                    <label for="academicYear">Academic Year (Eg: XXXX/XXXX)</label>
                    <input type="text" id="academicYear" name="academicYear" 
                           value="<?php echo htmlspecialchars($semester['AcademicYear']); ?>"
                           placeholder="Eg: 2024/2025" required
                           pattern="\d{4}/\d{4}">
                    <div class="error-message" id="academicYearError"></div>
                </div>
                
                <div class="form-row">
                    <label for="semester">Semester</label>
                    <select id="semester" name="semester" required>
                        <option value="1" <?php echo $semester['Semester'] == 1 ? 'selected' : ''; ?>>1</option>
                        <option value="2" <?php echo $semester['Semester'] == 2 ? 'selected' : ''; ?>>2</option>
                        <option value="3" <?php echo $semester['Semester'] == 3 ? 'selected' : ''; ?>>3</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <label for="checkInDate">Check In Date</label>
                    <input type="date" id="checkInDate" name="checkInDate" 
                           value="<?php echo htmlspecialchars($semester['CheckInDate']); ?>" required>
                </div>
                
                <div class="form-row">
                    <label for="checkOutDate">Check Out Date</label>
                    <input type="date" id="checkOutDate" name="checkOutDate" 
                           value="<?php echo htmlspecialchars($semester['CheckOutDate']); ?>" required>
                    <div class="error-message" id="dateError"></div>
                </div>
                
                <div class="form-row">
                    <label for="hostelFee">Hostel Fee (RM)</label>
                    <input type="number" id="hostelFee" name="hostelFee" 
                           value="<?php echo htmlspecialchars($semester['HostelFee']); ?>"
                           min="0.01" step="0.01" required>
                    <div class="error-message" id="feeError"></div>
                </div>
                
                <div class="form-row button-group">
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='hsViewSem.php'">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </main>

    <script>
        document.getElementById('editForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Reset error messages
            document.querySelectorAll('.error-message').forEach(el => el.style.display = 'none');
            
            // Validate academic year format
            const academicYear = document.getElementById('academicYear').value;
            if (!/^\d{4}\/\d{4}$/.test(academicYear)) {
                document.getElementById('academicYearError').textContent = 'Invalid format. Use XXXX/XXXX';
                document.getElementById('academicYearError').style.display = 'block';
                return;
            }
            
            // Validate dates
            const checkInDate = new Date(document.getElementById('checkInDate').value);
            const checkOutDate = new Date(document.getElementById('checkOutDate').value);
            if (checkOutDate <= checkInDate) {
                document.getElementById('dateError').textContent = 'Check-out date must be after check-in date';
                document.getElementById('dateError').style.display = 'block';
                return;
            }
            
            // Validate hostel fee
            const hostelFee = parseFloat(document.getElementById('hostelFee').value);
            if (isNaN(hostelFee) || hostelFee <= 0) {
                document.getElementById('feeError').textContent = 'Hostel fee must be greater than 0';
                document.getElementById('feeError').style.display = 'block';
                return;
            }
            
            // Submit form
            const formData = new FormData(this);
            fetch('hsEditSem.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Semester updated successfully');
                    window.location.href = 'hsViewSem.php';
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating the semester');
            });
        });
    </script>
</body>
</html> 