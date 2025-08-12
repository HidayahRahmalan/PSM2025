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

// Get any success or error messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear the messages from session
unset($_SESSION['success']);
unset($_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin User Management - SHMS</title>
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
        
        .page-header p {
            color: var(--text-light);
            font-size: 16px;
        }
        
        /* Management Cards */
        .management-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .management-card {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .management-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }
        
        .card-image {
            height: 200px;
            overflow: hidden;
        }
        
        .card-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            transition: transform 0.5s ease;
        }
        
        .management-card:hover .card-image img {
            transform: scale(1.05);
        }
        
        .card-content {
            padding: 20px;
        }
        
        .card-content h3 {
            color: var(--primary-color);
            font-size: 22px;
            margin-bottom: 10px;
        }
        
        .card-content p {
            color: var(--text-light);
            margin-bottom: 5px;
        }
        
        .card-action {
            text-align: center;
            padding-bottom: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: var(--text-dark);
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: var(--white);
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        /* Card Icons */
        .card-icon {
            font-size: 24px;
            margin-right: 10px;
            vertical-align: middle;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .management-grid {
                grid-template-columns: 1fr;
            }
        }
        
        /* Add styles for alert messages */
        .alert {
            padding: 15px;
            margin: 20px auto;
            border-radius: 5px;
            max-width: 800px;
            text-align: center;
            display: none;
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
        
        /* Show alert if it has content */
        .alert:not(:empty) {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'includes/adminNav.php'; ?>

    <main class="container main-content">
        <!-- Add alert message containers -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <section class="page-header">
            <h2>User Management</h2>
            <p>Manage system users and view system activity logs</p>
        </section>

        <section class="management-grid">
            <!-- Student Management Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://static.vecteezy.com/system/resources/previews/010/875/130/non_2x/group-of-happy-university-students-with-study-books-and-gadgets-free-vector.jpg" alt="Student Management">
                </div>
                <div class="card-content">
                    <h3><i class="fas fa-user-graduate card-icon"></i>Student Management</h3>
                    <p>View, update, or manage student accounts.</p>
                </div>
                <div class="card-action">
                    <a href="admViewStud.php" class="btn btn-primary">Manage Students</a>
                </div>
            </div>

            <!-- Staff Management Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://t3.ftcdn.net/jpg/03/54/97/18/360_F_354971816_BehufpbdGjHkdP8qRyXF3EiGh93iqkLs.jpg" alt="Staff Management">
                </div>
                <div class="card-content">
                    <h3><i class="fas fa-user-tie card-icon"></i>Hostel Staff Management</h3>
                    <p>View, update or manage hostel staff accounts.</p>
                </div>
                <div class="card-action">
                    <a href="admViewHs.php" class="btn btn-primary">Manage Staff</a>
                </div>
            </div>

            <!-- Audit Log Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://img.freepik.com/premium-vector/illustration-vector-graphic-cartoon-character-audit_516790-736.jpg" alt="Audit Logs">
                </div>
                <div class="card-content">
                    <h3><i class="fas fa-clipboard-list card-icon"></i>Audit Logs</h3>
                    <p>View system audit logs to monitor login and logout activities.</p>
                </div>
                <div class="card-action">
                    <a href="admViewAuditLog.php" class="btn btn-primary">View Audit Logs</a>
                </div>
            </div>

            <!-- Audit Trail Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://media.licdn.com/dms/image/v2/D4D12AQEYSKunoO4U6Q/article-cover_image-shrink_720_1280/article-cover_image-shrink_720_1280/0/1708339590962?e=2147483647&v=beta&t=ebKsypTkNsSmsv51VsvRIhCn43J0ixH74VR_u2t_r5I" alt="Audit Trail">
                </div>
                <div class="card-content">
                    <h3><i class="fas fa-history card-icon"></i>Audit Trail</h3>
                    <p>View detailed audit trails showing all data changes.</p>
                </div>
                <div class="card-action">
                    <a href="admViewAuditTrail.php" class="btn btn-primary">View Audit Trail</a>
                </div>
            </div>

            <!-- Admin Information Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://www.shutterstock.com/image-vector/young-woman-secretary-working-computer-600nw-1646699650.jpg" alt="Admin Information">
                </div>
                <div class="card-content">
                    <h3><i class="fas fa-user-shield card-icon"></i>Admin Information</h3>
                    <p>View administrator information.</p>
                </div>
                <div class="card-action">
                    <a href="admViewAdmInfo.php" class="btn btn-primary">View Admin</a>
                </div>
            </div>

            <!-- Backup Database Card -->
            <div class="management-card">
                <div class="card-image">
                    <img src="https://img.freepik.com/premium-vector/data-backup-recovery-isolated-cartoon-vector-illustrations_107173-21571.jpg" alt="Backup Database">
                </div>
                <div class="card-content">
                    <h3><i class="fas fa-database card-icon"></i>Backup Database</h3>
                    <p>Manually database backups for system recovery.</p>
                </div>
                <div class="card-action">
                    <a href="admBackupDb.php" class="btn btn-primary">Backup Database</a>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 5000);
            });
        });
    </script>
</body>
</html> 