<?php
session_start();
include 'dbConnection.php';
include 'activityTracker.php';

$UserID = $_SESSION['UserID'];
$UserRole = $_SESSION['URole'];

$backupResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'backup') {
        $backupResult = backupDatabase();
    } elseif ($action === 'dashboard') {
        header("Location: adminMainPage.php");
        exit();
    }
}

function backupDatabase() {
    $batFilePath = "C:\\backups\\backup_dqms_db.bat";

    $output = [];
    $returnVar = null;
    exec("start /B $batFilePath", $output, $returnVar);

    return $returnVar === 0 ? 'success' : 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Database Management (Backup)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #fff;
            color: #333;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: left;
            color: rgba(255, 152, 0, 0.6);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .top-right {
            position: absolute;
            top: 20px;
            right: 20px;
        }

        .top-right button {
            background-color: #ff9800;
            color: white;
            border: none;
            padding: 10px 16px;
            font-size: 14px;
            border-radius: 4px;
            cursor: pointer;
        }

        .top-right button:hover {
            opacity: 0.9;
        }

        .main-content {
            margin-top: 30px;
            padding: 40px;
            display: grid;
            grid-template-columns: repeat(2, auto);
            gap: 30px;
            justify-content: center;
        }

        .card-box {
            background-color: white;
            border-radius: 15px;
            text-align: center;
            padding: 60px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            color: #ff9800;
            transition: transform 0.2s, box-shadow 0.3s;
            aspect-ratio: 1 / 1;
            position: relative;
            cursor: pointer;
        }

        .card-box:hover {
            transform: scale(1.03);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .card-box img {
            width: 180px;
            height: 180px;
            margin-bottom: 10px;
        }

        .card-box h4 {
            font-size: 22px;
            margin-bottom: 20px;
        }

        <!-- Custom SweetAlert button style -->
        .swal2-confirm-custom {
            background-color: #ff9800 !important;
            color: white !important;
            border: none;
            box-shadow: none;
        }
        .swal2-confirm-custom {
            background-color: #ff9800 !important;
            color: white !important;
            border: none;
            box-shadow: none;
        }
        .swal2-cancel-custom {
            background-color:rgb(204, 204, 204) !important;
            color: white !important;
            border: none;
            box-shadow: none;
        }
        /* Custom Input Border */
        .swal2-input-custom {
            border: 2px solid rgba(255, 153, 0, 0.4) !important;
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="top-right">
    <form method="post">
        <button type="submit" name="action" value="dashboard">Back to Main Page</button>
    </form>
</div>

<h1>Database Management (Backup)</h1>

<div class="main-content">
    <!-- Backup Card -->
    <form method="post">
        <div class="card-box" onclick="this.closest('form').submit();">
            <img src="backup.png" alt="Backup Icon">
            <h4>Backup</h4>
            <input type="hidden" name="action" value="backup">
        </div>
    </form>

</div>



<?php if ($backupResult === 'success'): ?>
<script>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: 'Database backup initiated successfully!',
        confirmButtonColor: '#ff9800'
    });
</script>
<?php elseif ($backupResult === 'error'): ?>
<script>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Failed to initiate database backup. Please check the .bat file.',
        confirmButtonColor: '#ff9800'
    });
</script>
<?php endif; ?>


</body>
</html>
