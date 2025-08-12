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
    <title>User Activivty Log Report</title>
    <!-- Include Bootstrap CSS (for the modal dialog) -->
    <!-- jQuery (must come first) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS (relies on jQuery) -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

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
            padding: 60px;
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

<!-- DataTables CSS & JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

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

<!-- chart -->
<div class="table-wrapper">

    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
        <h2 style="color:#ff9800; margin: 0;">User Activity Log Report</h2>
        <button onclick="generatePDF()" style="background-color:#ff9800; color:white; border:none; padding:10px 16px; border-radius:5px; cursor:pointer;">
            Generate Report
        </button>
    </div>

    <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
        <!-- Heatmap -->
        <div style="flex: 1; min-width: 48%; margin-right: 2%; margin-bottom: 30px;">
            <canvas id="heatmapChart" height="200"></canvas>
        </div>

        <!-- Bar Chart -->
        <div style="flex: 1; min-width: 48%; margin-bottom: 30px;">
            <canvas id="barChart" height="200"></canvas>
        </div>
    </div>

    <!-- DataTable -->
    <table id="logTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>User ID</th>
                <th>User Name</th>
                <th>Action</th>
                <th>Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php
                include 'dbConnection.php'; // Your DB connection file
                $query = "
                    SELECT a.UserID, u.UName, a.action, a.created_at
                    FROM audit_logs a
                    JOIN USER u ON a.UserID = u.UserID
                    ORDER BY a.created_at DESC
                ";
                $result = mysqli_query($conn, $query);
                while($row = mysqli_fetch_assoc($result)) {
                    echo "<tr>
                        <td>{$row['UserID']}</td>
                        <td>{$row['UName']}</td>
                        <td>{$row['action']}</td>
                        <td>{$row['created_at']}</td>
                    </tr>";
                }
            ?>
        </tbody>
    </table>
</div>


<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Matrix plugin -->
<script src="https://cdn.jsdelivr.net/npm/chartjs-chart-matrix@1.2.0/dist/chartjs-chart-matrix.min.js"></script>

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
        pdf.text("User Activity Log Report", margin, currentY);
        currentY += 10;
    
        // Get chart images
        const heatmapCanvas = document.getElementById("heatmapChart");
        const barCanvas = document.getElementById("barChart");
    
        // Set chart image sizes for side-by-side (adjust width/height as needed)
        const chartWidth = 85;   // mm
        const chartHeight = 50;  // mm
        const gap = 10;          // mm between charts
    
        if (heatmapCanvas && barCanvas) {
            // Get images
            const [heatmapImg, barImg] = await Promise.all([
                html2canvas(heatmapCanvas).then(canvas => canvas.toDataURL("image/png")),
                html2canvas(barCanvas).then(canvas => canvas.toDataURL("image/png"))
            ]);
            // Place heatmap (left)
            pdf.addImage(heatmapImg, 'PNG', margin, currentY, chartWidth, chartHeight);
            // Place bar chart (right)
            pdf.addImage(barImg, 'PNG', margin + chartWidth + gap, currentY, chartWidth, chartHeight);
            currentY += chartHeight + 10;
        } else if (heatmapCanvas) {
            const heatmapImg = await html2canvas(heatmapCanvas).then(canvas => canvas.toDataURL("image/png"));
            pdf.addImage(heatmapImg, 'PNG', margin, currentY, 180, 60);
            currentY += 65;
        } else if (barCanvas) {
            const barImg = await html2canvas(barCanvas).then(canvas => canvas.toDataURL("image/png"));
            pdf.addImage(barImg, 'PNG', margin, currentY, 180, 60);
            currentY += 65;
        }
    
        // Use DataTables API to get all filtered data (not just visible page)
        const dataTable = $('#logTable').DataTable();
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
                    pdf.text("User Activity Log Report", margin, 10);
                }
            }
        });

        // Save the PDF
        pdf.save("User Activity Log Report.pdf");
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

    $(document).ready(function () {
        $('#logTable').DataTable({
            order: [[3, 'desc']]
        });
    });

    // Fetch and render charts
    fetch('fetch_user_activity_data.php')
        .then(res => res.json())
        .then(data => {
            renderHeatmap(data.heatmap);
            renderBarChart(data.bar);
        });

    function renderHeatmap(heatmapData) {
        const ctx = document.getElementById('heatmapChart').getContext('2d');

        new Chart(ctx, {
            type: 'matrix',
            data: {
                datasets: [{
                    label: 'Logins per hour/day',
                    data: heatmapData.data,
                    backgroundColor(chartCtx) {
                        const value = chartCtx.dataset.data[chartCtx.dataIndex].v;
                        const alpha = value / heatmapData.max;
                        return `rgba(255, 152, 0, ${alpha})`;
                    },
                    borderWidth: 1,
                    width: ({ chart }) => (chart.chartArea || {}).width / 24 - 1,
                    height: ({ chart }) => (chart.chartArea || {}).height / 7 - 1,
                }]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        type: 'category',
                        labels: heatmapData.hours,
                        title: {
                            display: true,
                            text: 'Hour of Day',
                            font: { weight: 'bold', size: 14 }
                        },
                        ticks: {
                            font: { weight: 'bold' }
                        }
                    },
                    y: {
                        type: 'category',
                        labels: heatmapData.days,
                        title: {
                            display: true,
                            text: 'Day of Week',
                            font: { weight: 'bold', size: 14 }
                        },
                        ticks: {
                            font: { weight: 'bold' }
                        }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => `Logins: ${ctx.raw.v}`
                        }
                    }
                }
            },
            plugins: [{
                id: 'heatmapShadow',
                beforeDatasetsDraw(chart) {
                    const ctx = chart.ctx;
                    ctx.save();
                    ctx.shadowColor = 'rgba(220, 220, 220, 0.4)';
                    ctx.shadowBlur = 6;
                    ctx.shadowOffsetX = 2;
                    ctx.shadowOffsetY = 2;
                },
                afterDatasetsDraw(chart) {
                    chart.ctx.restore();
                }
            }]
        });
    }


    function renderBarChart(barData) {
        const barCtx = document.getElementById('barChart').getContext('2d');

        const barChart = new Chart(barCtx, {
            type: 'bar',
            data: {
                labels: barData.users,
                datasets: [{
                    label: 'Actions Per User',
                    data: barData.counts,
                    backgroundColor: 'rgba(255, 153, 0, 0.52)',
                    borderRadius: 4,
                }]
            },
            options: {
                plugins: {
                    legend: { display: false }
                },
                responsive: true,
                scales: {
                    x: {
                        ticks: {
                            font: { weight: 'bold' }
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            font: { weight: 'bold' }
                        }
                    }
                },
                animation: {
                    onComplete: () => {
                        const ctx = barCtx;
                        ctx.save();
                        ctx.shadowColor = 'rgba(0, 0, 0, 0.2)';
                        ctx.shadowBlur = 10;
                        ctx.shadowOffsetX = 3;
                        ctx.shadowOffsetY = 3;
                    }
                }
            },
            plugins: [{
                id: 'barShadow',
                beforeDatasetsDraw(chart) {
                    const ctx = chart.ctx;
                    ctx.save();
                    ctx.shadowColor = 'rgba(0, 0, 0, 0.2)';
                    ctx.shadowBlur = 10;
                    ctx.shadowOffsetX = 4;
                    ctx.shadowOffsetY = 4;
                },
                afterDatasetsDraw(chart) {
                    chart.ctx.restore();
                }
            }]
        });
    }


</script>

</body>
</html>  