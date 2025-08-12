<?php
session_start();
include 'dbConnection.php';
include 'activityTracker.php';

$UserID = $_SESSION['UserID'];
$UserRole = $_SESSION['URole'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record List</title>
    <!-- Include Bootstrap CSS (for the modal dialog) -->
    <!-- jQuery (must come first) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS (relies on jQuery) -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <!-- DataTables CSS & JS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: rgb(255, 255, 255);
            margin: 0;
        }

        .header-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 60px;
            background-color: rgb(255, 255, 255);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            transition: left 0.3s ease, width 0.3s ease;
            z-index: 999;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .header-bar .title {
            font-size: 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            color: rgb(0, 0, 0);
        }
        .header-bar .title:hover {
            color: rgba(220, 220, 220, 0.5);
        }
        .header-bar .title a {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
            gap: 10px;
        }

        .logo-image {
            width: 30px;
            height: auto;
            margin-left: 10px;
        }

        .nav-items {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-right: 3%;
        }
        .nav-items a {
            text-decoration: none;
        }


        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-btn {
            background-color: transparent;
            border: none;
            padding: 8px 15px;
            font-size: 16px;
            font-weight: bold;
            color: #ff9800;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s ease;
        }

        .dropdown-btn:hover {
            background-color: rgba(220, 220, 220, 0.5);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            color: white;
            min-width: 150px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            background-color: white;
            border: 1px solid rgb(183, 183, 181);
            border-radius: 5px;
            z-index: 1;
        }

        .dropdown-content a {
            color: black;
            padding: 10px;
            display: block;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }

        .dropdown-content a:hover {
            background-color: rgb(220, 220, 220,0.5);
        }

        .dropdown.show .dropdown-content {
            display: block;
        }

        .dropdown-content a img {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            vertical-align: middle; /* Aligns image with the middle of the text */
            margin-top: 2px; /* Optional fine-tuning */
        }
        .btn {
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 16px;
        }
        .header-bar {
            z-index: 1000 !important;
        }

        table.dataTable thead {
            background-color:rgba(255, 153, 0, 0.79);
            color: white;
        }
        .table-wrapper {
            margin-top: 35px;
            padding: 40px;
        }
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            margin-bottom: 15px;
            font-weight: bold;
        }
        .dataTables_info strong {
            font-weight: bold;
            color: #000; /* optional */
        }
        /* Bold 'Previous' and 'Next' */
        .dataTables_wrapper .dataTables_paginate .paginate_button.previous,
        .dataTables_wrapper .dataTables_paginate .paginate_button.next {
            font-weight: bold;
        }

        /* Style for the active (current) page number */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            font-weight: bold !important;
            color: rgba(255, 153, 0, 0.79) !important;
            background-color: rgba(255, 153, 0, 0.2) !important; /* Light orange always shown */
            border: none !important;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        /* Hover effect only for the active page number */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background-color: rgba(255, 153, 0, 0.3) !important; /* Slightly darker on hover */
            color: rgba(255, 153, 0, 1) !important;
            cursor: default;
        }

        /* Remove hover background and border for other buttons */
        .dataTables_wrapper .dataTables_paginate .paginate_button:not(.current):hover {
            background: none !important;
            border: none !important;
            color: rgba(255, 153, 0, 0.79) !important;
            box-shadow: none !important;
        }
        /* Add shadow to DataTables search input */
        .dataTables_filter input {
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 6px 10px;
            outline: none;
            transition: box-shadow 0.3s ease;
        }

        /* Optional: Slightly stronger shadow on focus */
        .dataTables_filter input:focus {
            box-shadow: 0 0 8px rgba(255, 152, 0, 0.6);
            border-color: #ff9800;
        }

    </style>
    </head>
<body>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Header Bar -->
<div class="header-bar">
    <div class="title">
        <a href="adminMainPage.php" style="text-decoration: none; color: inherit;">
            Data Quality Monitoring System 
            <img src="icon1.png" alt="Logo" class="logo-image">
        </a>
    </div>

    <div class="nav-items">
        <!-- Management Dropdown -->
        <div class="dropdown">
            <button class="dropdown-btn" onclick="toggleDropdown('managementDropdown')">MANAGEMENT</button>
            <div id="managementDropdown" class="dropdown-content">
                <a href="dataset.php"><img src="dataset.png">Dataset</a>
                <a href="record.php"><img src="record.png">Record</a>
                <a href="check.php"><img src="check.png">Check</a>
                <a href="action.php"><img src="action.png">Action</a>
                <a href="user.php"><img src="userMgmt.png">User</a>
                <a href="export.php"><img src="export.png">Export</a>
                <!--<a href="refund.php"><img src="refund.png">Refund</a>-->
                <!--<a href="payout.php"><img src="payout.png">Payout</a>-->
            </div>
        </div>

        <a class="dropdown-btn" href="report.php">REPORT</a>
        <a class="dropdown-btn" href="backup.php">BACKUP</a>

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="dropdown-btn" onclick="toggleDropdown('userDropdown')"><?php echo $UserRole; ?></button>
            <div id="userDropdown" class="dropdown-content">
                <a href="adminProfile.php"><img src="profile1.png"> Profile</a>
                <a href="#" onclick="event.preventDefault(); confirmLogout()">
                    <img src="logout.png"> Logout
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Fetch record from DB
$sql = "SELECT * FROM RECORD";
$result = $conn->query($sql);
?>

<!-- data table -->
<div class="table-wrapper">
    <h2 style="color:#ff9800;">Record List</h2>
    <table id="recordTable" class="display" style="width:100%;">
        <thead>
            <tr>
                <th>Record ID</th>
                <th>Type</th>
                <th>Dataset ID</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['RecordID']); ?></td>
                <td><?php echo htmlspecialchars($row['RType']); ?></td>
                <td><?php echo htmlspecialchars($row['DatasetID']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>


<script>
    function toggleDropdown(id) {
        document.querySelectorAll(".dropdown-content").forEach(el => {
            if (el.id !== id) el.parentElement.classList.remove("show");
        });
        const element = document.getElementById(id).parentElement;
        element.classList.toggle("show");
    }

    window.addEventListener("click", function (event) {
        if (!event.target.closest('.dropdown')) {
            document.querySelectorAll(".dropdown").forEach(dropdown => {
                dropdown.classList.remove("show");
            });
        }
    });

    function logout() {
        window.location.href = "logout.php";
    }

    function confirmLogout() {
        const theme = localStorage.getItem("theme") || "dark";

        Swal.fire({
            icon: 'warning',
            title: 'Logout',
            text: 'Are you sure you want to log out?',
            showCancelButton: true,
            confirmButtonText: 'Logout',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ff9800',
            cancelButtonColor: '#6c757d',
            background: theme === "dark" ? "#333" : "#fff",
            color: theme === "dark" ? "#fff" : "#000",
            width: "380px",
            padding: "12px"
        }).then((result) => {
            if (result.isConfirmed) {
                logout();
            }
        });
    }



    $(document).ready(function() {
        $('#recordTable').DataTable({
            // Use infoCallback to customize the info text safely
            "infoCallback": function(settings, start, end, max, total, pre) {
                return 'Showing <strong>' + start + '</strong> to <strong>' + end +
                    '</strong> of <strong>' + total + '</strong> entries';
            }
        });
    });

</script>

</body>
</html>  