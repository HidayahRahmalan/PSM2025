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
    <title>Audit Trail Report</title>
    <!-- Include Bootstrap CSS (for the modal dialog) -->
    <!-- jQuery (must come first) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS (relies on jQuery) -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
        
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>


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
        .container {
            padding: 80px 20px 20px 20px;
        }
        canvas {
            margin-top: 20px;
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
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current[aria-current="page"] {
            font-weight: bold !important;
            color: rgba(255, 153, 0, 0.79) !important;
            background-color: rgba(255, 153, 0, 0.2) !important;
            border: none !important;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        /* Hover effect only for the active page number */
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current[aria-current="page"]:hover {
            background-color: rgba(255, 153, 0, 0.3) !important;
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
                <a href="refund.php"><img src="refund.png">Refund</a>
                <a href="payout.php"><img src="payout.png">Payout</a>
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
// Fetch audit trail data
$auditData = [];
$chartLabels = [];
$chartCounts = [];

$sql = "
    SELECT a.Userid, u.UName, a.operation, a.audit_at
    FROM audit_trail a
    JOIN USER u ON a.Userid = u.UserID
    ORDER BY a.audit_at ASC
";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $auditData[] = $row;

        $timeKey = date("Y-m-d H:00", strtotime($row['audit_at']));
        if (!isset($chartCounts[$timeKey])) {
            $chartCounts[$timeKey] = 0;
        }
        $chartCounts[$timeKey]++;
    }

    $chartLabels = array_keys($chartCounts);
    $chartValues = array_values($chartCounts);
}
?>

<div class="container">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; margin-top: 20px;">
        <h2 style="color:#ff9800; margin: 0;">Audit Trail Report</h2>
        <button onclick="generatePDF()" style="background-color:#ff9800; color:white; border:none; padding:10px 16px; border-radius:5px; cursor:pointer;">
            Generate Report
        </button>
    </div>

    <!-- Timeline Chart -->
    <canvas id="timelineChart" width="60%" height="20"></canvas>

    <!-- Audit Table -->
    <div class="table-wrapper">
        <table id="auditTable" class="display" style="width:100%">
            <thead>
                <tr>
                    <th>User ID</th>
                    <th>User Name</th>
                    <th>Operation</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auditData as $entry): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($entry['Userid']); ?></td>
                        <td><?php echo htmlspecialchars($entry['UName']); ?></td>
                        <td><?php echo htmlspecialchars($entry['operation']); ?></td>
                        <td><?php echo htmlspecialchars($entry['audit_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- jsPDF & html2canvas -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<script>
    async function generatePDF() {
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF('p', 'mm', 'a4');
        const margin = 15;
        let currentY = margin;
    
        // Add Title
        pdf.setFontSize(16);
        pdf.setTextColor(255, 152, 0);
        pdf.text("Audit Trail Report", margin, currentY);
        currentY += 10;
    
        // Add Timeline Chart
        const timelineCanvas = document.getElementById("timelineChart");
        if (timelineCanvas) {
            const timelineImg = await html2canvas(timelineCanvas).then(canvas => canvas.toDataURL("image/png"));
            pdf.addImage(timelineImg, 'PNG', margin, currentY, 180, 60);
            currentY += 65;
        }
    
        // Use DataTables API to get all filtered data
        const dataTable = $('#auditTable').DataTable();
        const headers = dataTable.columns().header().toArray().map(th => th.textContent.trim());
        const allData = dataTable.rows({ search: 'applied' }).data().toArray();
        const rows = allData.map(row => Array.from(row));

        // Add total entries info
        pdf.setFontSize(10);
        pdf.setTextColor(0, 0, 0);
        pdf.text(`Total entries: ${rows.length}`, margin, currentY);
        currentY += 8;

        pdf.autoTable({
            head: [headers],
            body: rows,
            startY: currentY,
            margin: { left: margin, right: margin },
            styles: {
                fontSize: 8,
                cellPadding: 1.5,
                minCellHeight: 6,
            },
            headStyles: {
                fillColor: [255, 152, 0],
                textColor: 255,
                fontStyle: 'bold'
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245]
            },
            tableLineWidth: 0.1,
            tableLineColor: [200, 200, 200],
            pageBreak: 'auto',
            rowPageBreak: 'avoid',
            didDrawPage: function (data) {
                if (data.pageNumber > 1) {
                    pdf.setFontSize(10);
                    pdf.text("Audit Trail Report", margin, 10);
                }
            }
        });
        
        // Save the PDF
        pdf.save("Audit Trail Report.pdf");
    }

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

    // Custom plugin to add shadow for line
    const shadowPlugin = {
        id: 'lineShadow',
        beforeDatasetsDraw(chart, args, options) {
            const {ctx} = chart;
            ctx.save();
            ctx.shadowColor = 'rgba(255, 87, 34, 0.5)'; // orange shadow
            ctx.shadowBlur = 10;
            ctx.shadowOffsetX = 0;
            ctx.shadowOffsetY = 4;
        },
        afterDatasetsDraw(chart, args, options) {
            chart.ctx.restore();
        }
    };

    // Chart Data
    const ctx = document.getElementById('timelineChart').getContext('2d');
    const timelineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($chartLabels); ?>,
            datasets: [{
                label: 'Audit Activities Over Time',
                data: <?php echo json_encode($chartValues); ?>,
                borderColor: 'rgba(255, 153, 0, 1)',
                backgroundColor: 'rgba(255, 153, 0, 0.2)',
                fill: true,
                tension: 0.3,
                pointRadius: 3,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Time (Hourly)',
                        font: {
                            size: 14,
                            weight: 'bold' 
                        }
                    }
                },
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Number of Operations',
                        font: {
                            size: 14,
                            weight: 'bold' 
                        }
                    },
                }
            }
        },
        plugins: [shadowPlugin]
    });

    // DataTable Init
    $(document).ready(function () {
        $('#auditTable').DataTable({
            pageLength: 10,
            lengthMenu: [5, 10, 20, 50],
            order: [[3, 'desc']]
        });
    });
</script>

</body>
</html>  