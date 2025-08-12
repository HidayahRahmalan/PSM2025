<?php
session_start();  
include 'dbConnection.php';
include 'activityTracker.php'; 

$UserID = $_SESSION['UserID'];

// Fetch datasets for sidebar
$sidebarQuery = "
    SELECT DatasetID, DSName, DSUploadDate
    FROM DATASET
    WHERE UserID = ?
    ORDER BY DSUploadDate DESC
";

$stmt = $conn->prepare($sidebarQuery);
$stmt->bind_param("s", $UserID);
$stmt->execute();
$result = $stmt->get_result();

$datasets = [];
while ($row = $result->fetch_assoc()) {
    $datasets[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Main Page</title>
    <!-- Include Bootstrap CSS (for the modal dialog) -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- SheetJS for XLSX export -->
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <!-- jsPDF and autotable for PDF export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <!-- jsPDF AutoTable -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.7.0/jspdf.plugin.autotable.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>


    <style>
        body {
            font-family: Arial, sans-serif;
            background-color:rgb(43, 45, 46);
            margin: 0;
        }
        .header-bar {
            position: fixed;
            top: 0;
            left: 250px; /* Start beside the sidebar */
            width: calc(100% - 250px);
            height: 60px;
            background-color: rgb(43, 45, 46);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 20px;
            transition: left 0.3s ease, width 0.3s ease;
            z-index: 999; /* Keep it above content */
        }

        /* When sidebar is closed */
        .sidebar:not(.open) + .header-bar {
            left: 0;
            width: 100%;
        }
        .header-bar .title {
            font-size: 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            color:rgb(170, 170, 169);
        }

        .welcome-container {
            display: flex;
            align-items: center;
            gap: 15px; 
        }

        .welcome-container .welcome-text {
            font-size: 16px;
            color: #ff9800;
            font-weight: bold;
        }
        .logo-image {
            width: 30px; 
            height: auto;
            margin-left: 10px; /* Space between the image and the text */
            margin-bottom: 5px; 
        }
        /* When sidebar is open, it should stay at left: 0 */
        .sidebar.open {
            left: 0;
        }

        .sidebar h2 {
            color: #ff9800;
            text-align: center;
            margin-top: 0; 
            padding-bottom: 10px; 
        }
        .sidebar a {
            padding: 10px 15px;
            text-decoration: none;
            font-size: 18px;
            color: blue;
            display: block;
        }
        .sidebar a:hover {
            background-color: #575d63;
            color: white;
        }
        .sidebar a .icon {
            margin-left: 10px;
        }
        /* Dropdown styles */
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
            background-color: rgb(62, 61, 61);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            color:rgb(170, 170, 169);
            min-width: 150px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border-radius: 5px;
            z-index: 1;
            background-color: rgba(77, 77, 77, 0.82);
            border: 1px solid rgb(183, 183, 181); 
            border-radius: 5px; /* Rounded corners */
        }

        .dropdown-content a {
            color:rgb(170, 170, 169);
            padding: 10px;
            display: block;
            text-decoration: none;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            cursor: pointer;
        }

        .dropdown-content a:hover {
            background-color: rgba(220, 220, 220,0.5);
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
        .sidebar {
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            background-color: rgb(35, 35, 35);
            padding-top: 10px;
            transition: width 0.3s ease;
            z-index: 1000;
        }

        /* Main Content */
        .main-content {
            position: relative; /* Key fix to keep loader inside */
            margin-left: 250px; /* Push beside sidebar */
            padding-top: 70px; /* Add padding to avoid being covered by header */
            transition: margin-left 0.3s ease, width 0.3s ease;
            width: calc(100% - 250px);
        }

        /* When sidebar is closed */
        .sidebar:not(.open) {
            width: 0;
        }

        .sidebar:not(.open) + .header-bar {
            left: 0;
            width: 100%;
        }

        .sidebar:not(.open) + .header-bar + .main-content {
            margin-left: 0;
            width: 100%;
        }

        .hamburger-icon {
            font-size: 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }

        /* When sidebar is open, move the hamburger inside */
        .sidebar.open .hamburger-icon {
            position: absolute;
            top: 13px;
            right: 10px;
            color:rgb(170, 170, 169);
        }

        /* When sidebar is closed, show it in the header */
        .sidebar:not(.open) + .header-bar .hamburger-icon {
            display: inline-block;
        }

        .sidebar.open + .header-bar .hamburger-icon {
            display: none; /* Hide when sidebar is open */
        }
        .modal-content {
            background-color: rgb(35, 35, 35); 
            color: white; /* Text color */
            border: 2px rgb(183, 183, 181); 
            border-radius: 10px; /* Rounded corners */
        }
        .modal-header {
            background-color: rgb(50, 50, 50);
            color: #ff9800; /* Title color */
            border-bottom: 2px solid rgb(183, 183, 181); 
        }
        .modal-footer {
            background-color: rgb(45, 45, 45);
            border-top: 2px solid rgb(183, 183, 181); 
        }
        .modal-footer .btn-secondary {
            background-color: gray;
            color: white;
            border: none;
        }
        .modal-footer .btn-danger {
            background-color: red;
            color: white;
        }
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.8); /* Darker overlay */
        }
        .modal-header .close {
            background-color: transparent; /* No background */
            color: rgb(183, 183, 181); 
            font-size: 24px; 
            border: none; 
            opacity: 1; /* Make it fully visible */
            transition: 0.3s ease-in-out;
        }
        .modal-header .close:hover {
            color: #ff9800; 
            transform: scale(1.2); /* Slightly enlarge */
        }
        .modal-footer {
            display: flex;
            justify-content: space-between;
            padding-top: 10px;
        }

        .btn {
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 16px;
        }

        .btn-primary {
            background-color:#ff9800;
            border: none;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            border: none;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .sidebar-search {
            flex-grow: 1;
            padding: 5px;
            border-radius: 5px;
            border: 1px solid #777;
            background-color:rgb(255, 255, 255);
            color: black;
            font-size: 14px;
            outline: none;
            margin-top: 4px;
            width: 80%;
            margin-left: 5px;
            display: none; /* Hide by default */
        }
        .sidebar-search::placeholder {
            color: #bbb;
        }
        .sidebar.open .sidebar-search {
            display: block; /* Show when sidebar is open */
        }
        .upload-container {
            display: flex;
            flex-direction: column; /* Stack elements vertically */
            align-items: center;
            justify-content: center;
            text-align: center;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            width: 1070px;
            height: 500px;
            border: 2px dashed #ddd;
            position: relative;
        }
        .upload-content {
            display: flex;
            flex-direction: column; /* Ensure vertical stacking */
            align-items: center; /* Center horizontally */
            justify-content: center;
        }

        .upload-icon {
            width: 80px;
            margin-bottom: 15px; /* Space below the icon */
            margin-right: 10px;
        }

        /* Dark Mode Styles */
        .dark-mode .upload-container {
            background: rgb(50, 50, 50); /* Dark background */
            color: white !important; 
            border: 2px dashed #aaa; /* Light dashed border */
        }
        .file-btn {
            background-color: #ff9800;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 10px;
        }
        .file-btn:hover {
            background-color:rgba(139, 139, 139, 0.33);
        }
        .text {
            margin-top: 10px;
            color: #555;
            font-size: 14px;
        }
        /* Ensure text inside turns white in dark mode */
        .dark-mode .upload-container .text {
            color: white !important;
        }
        .upload-container.dragover {
            border-color: #ff9800 !important;
        }
        input[type="file"] {
            display: none;
        }
        /* Ensure .upload-container is positioned */
        .upload-container {
            position: relative;
        }

        .loader-container {
            display: none; /* Initially hidden */
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(166, 166, 166, 0.5); /* Semi-transparent background */
            display: flex;
            flex-direction: column; /* Stack items vertically */
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .loader-container p {
            margin-top: 10px; /* Space below the loader */
            font-size: 16px;
            color: black;
            text-align: center;
        }

        /* Spinning loader */
        .loader {
            border: 8px solid #f3f3f3;
            border-top: 8px solid #ff9800;
            border-radius: 50%;
            width: 60px;
            height: 60px;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Hide content inside .upload-container when loading */
        .upload-container.loading .upload-content {
            display: none;
        }
        .dark-mode .loader-container p {
            color: white !important; /* Ensure text turns white in dark mode */
        }
        .sidebar .dataset-list {
            display: block;
            padding: 10px;
            list-style-type: none;
            margin: 0;
        }

        .sidebar:not(.open) .dataset-list {
            display: none;
        }

        .sidebar .dataset-item {
            color: black;
            padding: 6px 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .sidebar .dataset-item:hover {
            background-color: rgba(255, 255, 255, 0.6);
        }
        .dataset-group-label {
            font-weight: normal !important;
            color:rgba(255, 153, 0, 0.66);
            padding: 8px 10px 4px 10px;
            font-size: 16px;
            border-top: 1px solid #555;
        }

        .sidebar .dataset-item {
            color: black;
            padding: 6px 10px;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .sidebar .dataset-item:hover {
            background-color: rgba(255, 255, 255, 0.6);
        }
        .sidebar-scroll {
            max-height: calc(100vh - 60px); /* adjust if header space changes */
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 5px;
        }

        /* Optional: Custom scrollbar styling */
        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background-color: #888;
            border-radius: 3px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background-color: #555;
        }
        .dataset-list {
            list-style-type: none;     /* Remove bullets */
            padding: 0;                /* Remove inner padding */
            margin: 0;                 /* Remove outer margin */
        }

        .dataset-group-label {
            font-weight: bold;
            padding: 8px 12px;
            color: #ff9800;
            border-top: none;         /* Just in case */
            margin-top: 12px;
        }

        .dataset-item {
            padding: 8px 12px;
            color: white;
            cursor: pointer;
            transition: background 0.2s;
        }

        .dataset-item:hover {
            background-color: rgba(255, 255, 255, 0.4);
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        .dialog-content-left {
            text-align: left;
        }
        .dialog-btn {
            padding: 8px 16px;
            font-size: 14px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 500;
        }

        .download-btn {
            background-color: #ff9800;
            color: white;
        }

        .download-btn:hover {
            background-color: rgba(0, 0, 0, 0.32);
        }

        .delete-btn {
            background-color: rgb(139, 139, 139);
            color: white;
        }

        .delete-btn:hover {
            background-color: rgba(0, 0, 0, 0.32);
        }
        .dialog-close-btn {
            position: absolute;
            top: 8px;
            right: 12px;
            font-size: 32px;
            color: #999;
            cursor: pointer;
            z-index: 9999;
        }

        .dialog-close-btn:hover {
            color: #ff9800;
        }

    </style>
</head>
<body>

<!-- Header Bar -->
<div class="header-bar">
    <div class="title">
        <span class="hamburger-icon" onclick="toggleSidebar()">☰</span>
        Data Quality Monitoring System <img src="icon1.png" alt="Logo" class="logo-image">
    </div>
    <div class="welcome-container">
        <!-- Dropdown Menu -->
        <div class="dropdown">
            <button class="dropdown-btn" onclick="toggleDropdown()">
                <?php echo $_SESSION['URole']; ?>
            </button>
            <div class="dropdown-content">
                <a href="profile.php">
                    <img src="profile1.png" alt="Profile"> Profile
                </a>
                <!--<a onclick="openThemeSelector()">
                    <img src="theme.png" alt="Theme"> Theme
                </a>-->
                <a onclick="confirmLogout()">
                    <img src="logout.png" alt="Logout"> Logout
                </a>
            </div>
        </div>
    </div>
</div>



<!-- Include Bootstrap JavaScript (for modals) -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>


<!-- Sidebar Content -->
<div class="sidebar" id="sidebar">
    <input type="text" id="sidebarSearch" class="sidebar-search" placeholder="Search...">

    <!-- Scrollable Container -->
    <div class="sidebar-scroll">
        <ul class="dataset-list">
            <?php
            date_default_timezone_set('Asia/Kuala_Lumpur');
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));

            $groupedDatasets = [
                'Today' => [],
                'Yesterday' => [],
                'Previous' => []
            ];

            foreach ($datasets as $ds) {
                $uploadDate = date('Y-m-d', strtotime($ds['DSUploadDate']));
                if ($uploadDate === $today) {
                    $groupedDatasets['Today'][] = $ds;
                } elseif ($uploadDate === $yesterday) {
                    $groupedDatasets['Yesterday'][] = $ds;
                } else {
                    $groupedDatasets['Previous'][] = $ds;
                }
            }

            foreach ($groupedDatasets as $label => $group) {
                if (!empty($group)) {
                    echo "<li class='dataset-group-label'>$label</li>";
                    foreach ($group as $ds) {
                        $dsName = htmlspecialchars($ds['DSName']);
                        echo "<li class='dataset-item' data-id='{$ds['DatasetID']}' data-name='{$dsName}' onclick=\"showDatasetDetails('{$ds['DatasetID']}')\">{$dsName}</li>";
                    }

                }
            }
            ?>
        </ul>
    </div>
</div>



<!-- Dataset Details Dialog -->
<div id="datasetDialog" style="display:none;">
    <h2 id="dialogDSName"></h2>
    <p><strong>Upload Date:</strong> <span id="dialogDSUploadDate"></span></p>
    <p><strong>Format:</strong> <span id="dialogDSFormat"></span></p>
    <p><strong>Total Records:</strong> <span id="dialogRecordCount"></span></p>
    <hr>
    <h3>Action History</h3>
    <div id="dialogActionList"></div>
</div>

<!-- Main Content -->
<div class="main-content">
        <div class="container mt-4">
        <div class="upload-container" id="drop-area">
            <!-- Loader inside upload container -->
            <div class="loader-container" id="loader">
                <div class="loader"></div>
                <p id="loading-text">Uploading...</p> <!-- Dynamic text -->
            </div>

            <!-- Content that will be hidden during loading -->
            <div class="upload-content">
                <img src="upload.png" alt="Upload Icon" class="upload-icon">
                <label for="file-input" class="file-btn">Select File</label>
                <input type="file" id="file-input" accept=".csv, .xlsx">
                <div class="text">Drop files here</div>
                <div class="text">Supported format: CSV, XLSX</div>
            </div>
        </div>
    </div>
</div>

<script>
    function displayTable(rows, title) {
        let html = `<table style="width:100%; border-collapse: collapse;">`;

        let isFirstRowAfterSheet = false;

        rows.forEach((row, index) => {
            html += "<tr>";

            // Check if the row is a "Sheet: SheetName" label
            if (row[0] && row[0].startsWith("Sheet: ")) {
                html += `<td colspan="${row.length}" style="font-weight:bold; color:#ff9800; border:2px solid black; padding:4px;">${escapeHtml(row[0])}</td>`;
                isFirstRowAfterSheet = true; // The next row will be header
            } else {
                row.forEach(cell => {
                    if (isFirstRowAfterSheet) {
                        // Header row styling
                        html += `<th style="color:#ff9800; font-weight:bold; border:2px solid black; padding:4px;">${escapeHtml(cell)}</th>`;
                    } else {
                        // Normal data row styling
                        html += `<td style="border:2px solid black; padding:4px;">${escapeHtml(cell)}</td>`;
                    }
                });
                isFirstRowAfterSheet = false; // Only the first data row after sheet label is header
            }

            html += "</tr>";
        });

        html += "</table>";

        Swal.fire({
            title: title,
            html: html,
            width: "80%",
            confirmButtonColor: "#ff9800",
            //background: localStorage.getItem("theme") === "dark" ? "#333" : "#fff",
            //color: localStorage.getItem("theme") === "dark" ? "#fff" : "#000",
            background: "#fff",
            color: "#000",
            customClass: {
                popup: "custom-alert"
            }
        });
    }


    function escapeHtml(text) {
        if (text === null || text === undefined) return "";
        text = text.toString(); // convert to string if number or other
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }


    function readAndShowFileData(file) {
        const reader = new FileReader();
        const fileName = file.name.toLowerCase();

        if (fileName.endsWith(".csv")) {
            reader.onload = function(e) {
                const csvContent = e.target.result;
                const rows = csvContent.split("\n").map(row => row.split(","));
                displayTable(rows, "Clean File Content");
            };
            reader.readAsText(file);

        } else if (fileName.endsWith(".xlsx")) {
            reader.onload = function(e) {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });

                let rows = [];
                workbook.SheetNames.forEach(sheetName => {
                    const worksheet = XLSX.utils.sheet_to_json(workbook.Sheets[sheetName], { header: 1 });
                    rows = rows.concat([["Sheet: " + sheetName]], worksheet, [[""]]); // Adds sheet name and spacing
                });

                displayTable(rows, "Clean File Content");
            };
            reader.readAsArrayBuffer(file);

        } else {
            Swal.fire("Unsupported file type", "Only CSV and XLSX are supported.", "error");
        }
    }



    const UserID = "<?php echo htmlspecialchars($_SESSION['UserID'], ENT_QUOTES, 'UTF-8'); ?>";

    // Toggle the dropdown menu
    function toggleDropdown() {
        document.querySelector(".dropdown").classList.toggle("show");
    }

    // Close dropdown when clicking outside
    window.onclick = function(event) {
        if (!event.target.matches('.dropdown-btn')) {
            let dropdowns = document.querySelectorAll(".dropdown");
            dropdowns.forEach(dropdown => {
                if (dropdown.classList.contains("show")) {
                    dropdown.classList.remove("show");
                }
            });
        }
    };

    // Open modal based on clicked option
    function openModal(modalId) {
        $('#' + modalId).modal('show');
    }

    // Logout function
    function logout() {
        window.location.href = "logout.php"; // Redirect to logout page
    }

    document.addEventListener("DOMContentLoaded", function() {
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.querySelector('.main-content');
        const hamburgerIcon = document.querySelector('.hamburger-icon');

        // Ensure sidebar starts open
        sidebar.classList.add('open'); 
        mainContent.style.marginLeft = "250px";
        mainContent.style.width = "calc(100% - 250px)";

        // Move the hamburger icon inside the sidebar
        sidebar.appendChild(hamburgerIcon);
    });

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const headerBar = document.querySelector('.header-bar');
        const mainContent = document.querySelector('.main-content');
        const hamburgerIcon = document.querySelector('.hamburger-icon');

        sidebar.classList.toggle('open');

        if (sidebar.classList.contains('open')) {
            headerBar.style.left = "250px";
            headerBar.style.width = "calc(100% - 250px)";
            mainContent.style.marginLeft = "250px";
            mainContent.style.width = "calc(100% - 250px)";

            // Move hamburger inside the sidebar
            sidebar.appendChild(hamburgerIcon);
        } else {
            headerBar.style.left = "0";
            headerBar.style.width = "100%";
            mainContent.style.marginLeft = "0";
            mainContent.style.width = "100%";

            // Move hamburger back to the header
            document.querySelector('.title').prepend(hamburgerIcon);
        }
    }

    // Function to handle clicks outside the sidebar
    function handleOutsideClick(event) {
        const sidebar = document.getElementById('sidebar');
        const hamburgerIcon = document.querySelector('.hamburger-icon');
        
        // Check if the click happened outside the sidebar and the hamburger icon
        if (!sidebar.contains(event.target) && !hamburgerIcon.contains(event.target)) {
            sidebar.classList.remove('open'); // Hide the sidebar
            document.removeEventListener('click', handleOutsideClick); // Remove the listener
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        //const savedTheme = localStorage.getItem("theme") || "dark"; // Default to dark mode
        //setTheme(savedTheme);
        setTheme();
    });

    function setTheme() {
        const body = document.body;
        const uploadContainer = document.querySelector(".upload-container");
        const uploadTextElements = document.querySelectorAll(".upload-container .text"); // Select all text elements inside upload container
        const sidebar = document.getElementById('sidebar');
        const headerBar = document.querySelector('.header-bar');
        const searchBar = document.getElementById('sidebarSearch'); 
        const dropdownBtn = document.querySelector('.dropdown-btn'); 
        const dropdownContent = document.querySelector('.dropdown-content'); // Dropdown menu
        const dropdownItems = document.querySelectorAll('.dropdown-content a'); // Dropdown items
        const allTextElements = document.querySelectorAll(
            "body, .header-bar, .header-bar .title, .sidebar, .dropdown-content a, .dropdown-content, .modal-content, .modal-header, .modal-footer, .modal-body, .hamburger-icon"
        );

        // Light Mode
        body.style.backgroundColor = "white";
        sidebar.style.backgroundColor = "#f5f5f5";
        headerBar.style.backgroundColor = "white";
        dropdownContent.style.backgroundColor = "white"; 
        dropdownContent.style.border = "1px solid rgb(183, 183, 181)";

        searchBar.style.backgroundColor = "white";
        searchBar.style.color = "black";
        searchBar.style.border = "1px solid #ccc";

        uploadContainer.style.background = "white";
        uploadContainer.style.color = "black";
        uploadContainer.style.border = "2px dashed #ddd";

        uploadTextElements.forEach(text => {
            text.style.color = "black";
        });

        // all text is black in light mode
        allTextElements.forEach(element => {
            element.style.color = "rgb(0, 0, 0)";
        });

        //Dropdown Button Light Mode
        dropdownBtn.style.color = "#ff9800"; // Keep orange text
        dropdownBtn.style.backgroundColor = "transparent"; // Default background
        dropdownBtn.onmouseover = function() {
            this.style.backgroundColor = "rgb(220, 220, 220)"; // Light hover effect
        };
        dropdownBtn.onmouseleave = function() {
            this.style.backgroundColor = "transparent";
        };

        //Dropdown Items Light Mode
        dropdownItems.forEach(item => {
            item.style.color = "black";
            item.onmouseover = function() {
                this.style.backgroundColor = "rgb(230, 230, 230)"; // Light hover effect
            };
            item.onmouseleave = function() {
                this.style.backgroundColor = "transparent";
            };
        });

        document.querySelectorAll(".modal-content, .modal-header, .modal-footer, .modal-body").forEach(modal => {
            modal.style.backgroundColor = "white"; 
            modal.style.color = "black"; 
            modal.style.border = "1px solid rgb(200, 200, 200)"; 
        });

        document.querySelector("#loading-text").style.color = "black";
    }

    /*function setTheme(theme) {
        const body = document.body;
        const uploadContainer = document.querySelector(".upload-container");
        const uploadTextElements = document.querySelectorAll(".upload-container .text"); // Select all text elements inside upload container
        const sidebar = document.getElementById('sidebar');
        const headerBar = document.querySelector('.header-bar');
        const searchBar = document.getElementById('sidebarSearch'); 
        const dropdownBtn = document.querySelector('.dropdown-btn'); 
        const dropdownContent = document.querySelector('.dropdown-content'); // Dropdown menu
        const dropdownItems = document.querySelectorAll('.dropdown-content a'); // Dropdown items
        const allTextElements = document.querySelectorAll(
            "body, .header-bar, .header-bar .title, .sidebar, .dropdown-content a, .dropdown-content, .modal-content, .modal-header, .modal-footer, .modal-body, .hamburger-icon"
        );

        if (theme === "light") {
            // Light Mode
            body.style.backgroundColor = "white";
            sidebar.style.backgroundColor = "#f5f5f5";
            headerBar.style.backgroundColor = "white";
            dropdownContent.style.backgroundColor = "white"; 
            dropdownContent.style.border = "1px solid rgb(183, 183, 181)";

            searchBar.style.backgroundColor = "white";
            searchBar.style.color = "black";
            searchBar.style.border = "1px solid #ccc";

            uploadContainer.style.background = "white";
            uploadContainer.style.color = "black";
            uploadContainer.style.border = "2px dashed #ddd";

            uploadTextElements.forEach(text => {
                text.style.color = "black";
            });

            // all text is black in light mode
            allTextElements.forEach(element => {
                element.style.color = "rgb(0, 0, 0)";
            });

            //Dropdown Button Light Mode
            dropdownBtn.style.color = "#ff9800"; // Keep orange text
            dropdownBtn.style.backgroundColor = "transparent"; // Default background
            dropdownBtn.onmouseover = function() {
                this.style.backgroundColor = "rgb(220, 220, 220)"; // Light hover effect
            };
            dropdownBtn.onmouseleave = function() {
                this.style.backgroundColor = "transparent";
            };

            //Dropdown Items Light Mode
            dropdownItems.forEach(item => {
                item.style.color = "black";
                item.onmouseover = function() {
                    this.style.backgroundColor = "rgb(230, 230, 230)"; // Light hover effect
                };
                item.onmouseleave = function() {
                    this.style.backgroundColor = "transparent";
                };
            });

            document.querySelectorAll(".modal-content, .modal-header, .modal-footer, .modal-body").forEach(modal => {
                modal.style.backgroundColor = "white"; 
                modal.style.color = "black"; 
                modal.style.border = "1px solid rgb(200, 200, 200)"; 
            });

            document.querySelector("#loading-text").style.color = "black";

        } else {
            // Dark Mode (Default)
            body.style.backgroundColor = "rgb(43, 45, 46)";
            sidebar.style.backgroundColor = "rgb(35, 35, 35)";
            headerBar.style.backgroundColor = "rgb(43, 45, 46)";
            dropdownContent.style.backgroundColor = "rgba(77, 77, 77, 0.82)"; 

            searchBar.style.backgroundColor = "rgb(66, 66, 66)";
            searchBar.style.color = "white";
            searchBar.style.border = "1px solid rgb(150, 150, 150)";

            uploadContainer.style.background = "rgb(52, 51, 51)";
            uploadContainer.style.color = "white";
            uploadContainer.style.border = "2px dashed #aaa";

            uploadTextElements.forEach(text => {
                text.style.color = "white";
            });

            document.querySelector("#loading-text").style.color = "white";

            // text is white in dark mode
            allTextElements.forEach(element => {
                element.style.color = "white";
            });

            // Dropdown Button Dark Mode
            dropdownBtn.style.color = "#ff9800"; // Orange text
            dropdownBtn.style.backgroundColor = "transparent";
            dropdownBtn.onmouseover = function() {
                this.style.backgroundColor = "rgb(62, 61, 61)"; // Dark hover effect
            };
            dropdownBtn.onmouseleave = function() {
                this.style.backgroundColor = "transparent";
            };

            // Dropdown Items Dark Mode
            dropdownItems.forEach(item => {
                item.style.color = "white"; 
                item.onmouseover = function() {
                    this.style.backgroundColor = "rgba(255, 255, 255, 0.2)"; // Dark hover effect
                };
                item.onmouseleave = function() {
                    this.style.backgroundColor = "transparent";
                };
            });
            document.querySelectorAll(".modal-content, .modal-header, .modal-footer, .modal-body").forEach(modal => {
                modal.style.backgroundColor = "rgb(66, 66, 66)"; 
                modal.style.color = "white"; 
                modal.style.border = "1px solid rgb(200, 200, 200)"; 
            });
        }

        localStorage.setItem("theme", theme); // Save user preference
    }*/

    /*function toggleTheme(theme) {
        setTheme(theme);
    }*/

    const dropArea = document.getElementById('drop-area');
    const fileInput = document.getElementById('file-input');
    const loader = document.getElementById('loader');
    const uploadContainer = document.querySelector('.upload-container');
    
    const allowedExtensions = ["csv", "xlsx"];

    function showLoader() {
        uploadContainer.classList.add('loading'); // Hide upload content
        loader.style.display = 'flex'; // Show loader

        //const statusMessages = ["Uploading...", "Processing...", "Detecting..."];
        const statusMessages = ["Detecting..."];
        let index = 0;
        const loadingText = document.getElementById('loading-text');

        // Change text every second
        const interval = setInterval(() => {
            loadingText.textContent = statusMessages[index];
            index = (index + 1) % statusMessages.length; // Loop through messages
        }, 1000);

        setTimeout(() => {
            clearInterval(interval); // Stop changing text after 4 seconds
            loader.style.display = 'none'; // Hide loader
            uploadContainer.classList.remove('loading'); // Show upload content again
            //alert('File uploaded successfully!');
        }, 5000); // Simulate a 4-second upload process
    }

    function validateFile(file) {
        if (!file) {
            //alert("Please select a file.");
            return false;
        }

        const fileName = file.name;
        const fileExtension = fileName.split(".").pop().toLowerCase();

        if (!allowedExtensions.includes(fileExtension)) {
            //alert("Invalid file format. Allowed formats: XLSX, CSV");
            showAlert("warning", "Invalid File Format!", "Allowed: XLSX, CSV");
            stopLoader();
            return false; //  STOP - Don't show loader
        }

        return true; //  File is valid
    }

    dropArea.addEventListener('dragover', (event) => {
        event.preventDefault();
        dropArea.classList.add('dragover');
    });

    dropArea.addEventListener('dragleave', () => {
        dropArea.classList.remove('dragover');
    });

    dropArea.addEventListener("drop", (event) => {
        event.preventDefault();
        dropArea.classList.remove("dragover");

        const file = event.dataTransfer.files[0];
        if (fileInput) fileInput.files = event.dataTransfer.files;

        showLoader();

        if (validateFile(file)) {
            insertDataset(file.name, file); 
            //checkDuplicates(file); // Check for duplicates
        }
    });

    fileInput.addEventListener("change", (event) => {
        const file = event.target.files[0];

        showLoader();

        if (validateFile(file)) {
            insertDataset(file.name, file); 
            //showLoader();
            //checkDuplicates(file); 
        }
    });

    // Ensure loader is hidden on page load
    window.onload = () => {
        loader.style.display = 'none';
    };


    function insertDataset(filename, file) {
        let formData = new FormData();
        formData.append("dsname", filename);
        formData.append("dssource", "TikTok"); 
        formData.append("dsformat", filename.split('.').pop()); // Get file extension
        formData.append("userid", "<?php echo $UserID; ?>"); // Ensure UserID is set

        fetch("insertDataset.php", {
            method: "POST",
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log("Dataset Insert Response:", data);
            if (data.success) {
                let datasetID = data.datasetID; // Store new dataset ID

                // Store datasetID in sessionStorage for later use
                sessionStorage.setItem("datasetID", datasetID);

                checkDuplicates(file, datasetID); // Pass DatasetID to checkDuplicates
            } else {
                alert("Error inserting dataset: " + data.message);
            }
        })
        .catch(error => {
            console.error("Error inserting dataset:", error);
            alert("Error: " + error);
        });
    }


    function uploadFile(file, datasetID) {
        let fileData = new FormData();
        fileData.append("file", file);
        fileData.append("datasetID", datasetID);

        fetch("processFile.php", {
            method: "POST",
            body: fileData
        })
        .catch(error => {
            console.error("JSON Parsing Error:", error, "\nRaw response:", text);
            alert("File upload failed. Invalid server response.");
        });
    }

    
    function insertCheck(ctype, cstatus, datasetID) {
        let formData = new FormData();
        formData.append("ctype", ctype);
        formData.append("cstatus", cstatus);
        formData.append("datasetID", datasetID);

        fetch("insertCheck.php", { // A new PHP script to insert the check record
            method: "POST",
            body: formData
        })
        .then(response => response.text())
        .then(text => {
            console.log("InsertCheck Response:", text);
        })
        .catch(error => {
            console.error("Error inserting check:", error);
        });
    }


    function checkDuplicates(file, datasetID) {
        let formData = new FormData();
        formData.append("file", file);
        formData.append("datasetID", datasetID); // Pass dataset ID

        fetch("checkDuplicate.php", { // New script to check for duplicates only
            method: "POST",
            body: formData
        })
        .then(response => response.text()) // Get raw text first

        .then(text => {
            console.log("Raw response from checkDuplicate.php (checkDuplicates, userMainPage.php) :", text); // Debugging output

            try {

                let data = JSON.parse(text); // Parse JSON

                if (data.error) {
                    console.error("Error:", data.error);
                    alert("Error: " + data.error);
                    return;
                }

                //let hasDuplicates = Object.values(data.duplicates).some(arr => arr.length > 0);
                //let hasMissingValues = data.missing_values.length > 0;
                //let hasInaccuracies = Object.keys(data.inaccuracies).length > 0;
                let hasDuplicates = Object.values(data.duplicates).some(arr => Array.isArray(arr) && arr.length > 0);
                let hasMissingValues = Array.isArray(data.missing_values) && data.missing_values.length > 0;
                let hasInaccuracies = Object.values(data.inaccuracies).some(arr => Array.isArray(arr) && arr.length > 0);

                if (hasDuplicates || hasMissingValues || hasInaccuracies) {

                    if (hasDuplicates) {
                        insertCheck("Duplicate", "Failed", datasetID);
                    }
                    if (hasMissingValues) { 
                        insertCheck("Missing Value", "Failed", datasetID);
                    }
                    if (hasInaccuracies) { 
                        insertCheck("Inaccuracies", "Failed", datasetID);
                    }

                    sessionStorage.setItem("duplicateData", JSON.stringify(data.duplicates ?? {}));
                    sessionStorage.setItem("extractedData", JSON.stringify(data.extractedData ?? [])); 
                    // Ensure extracted data is stored
                    sessionStorage.setItem("missingValues", JSON.stringify(data.missing_values ?? {}));
                    sessionStorage.setItem("inaccuracies", JSON.stringify(data.inaccuracies ?? {}));

                    window.location.href = "show.php"; // Redirect to results page
                
                } else {
                    insertCheck("Duplicate", "Passed", datasetID);
                    insertCheck("Missing Value", "Passed", datasetID);
                    insertCheck("Inaccuracies", "Passed", datasetID);
                    //showAlert("success", "Data is clean.");
                    showAlert("success", "Data is clean.", null, function() {
                        readAndShowFileData(file);
                    });

                    
                    if (data.cleaned_file) {
                        sessionStorage.setItem("cleanedFile", data.cleaned_file); // Store cleaned file
                    }

                    stopLoader();
                    uploadFile(file, datasetID);
                }

            } catch (error) {
                console.error("JSON Parsing Error:", error, "\nServer Response:", text);
                alert("Server returned invalid response. Check console for details.");
            }
        })

        .catch(error => {
            console.error("Error checking duplicates:", error);
            alert("Error: " + error);
            hideLoader();
        });
    }



    function showDatasetDetails(datasetID) {
        fetch('getDatasetDetails.php?datasetID=' + datasetID)
            .then(response => response.json())
            .then(data => {
                const dialogHTML = `
                    <div class="dialog-close-btn" id="closeDialogBtn">&times;</div>
                    <div style="text-align: left;">
                        <p><strong>Dataset Name:</strong> ${data.DSName}</p>
                        <p><strong>Upload Date:</strong> ${data.DSUploadDate}</p>
                        <p><strong>Format:</strong> ${data.DSFormat}</p>
                        <p><strong>Total Records:</strong> ${data.TotalRecords}</p>
                        <hr style="margin: 15px 0;">
                        <p style="font-size: 24px;"><strong>Action History:</strong></p>
                        <ol style="padding-left: 20px; margin-top: 5px;" id="dialogActionList">
                            ${data.Actions.map(action => `<li><strong>${action.AType}</strong>: ${action.ADetail}</li>`).join('')}
                        </ol>
                        <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                            <button class="dialog-btn download-btn">Download</button>
                            <button class="dialog-btn delete-btn">Delete</button>
                        </div>
                    </div>
                `;

                Swal.fire({
                    title: 'Dataset Details',
                    html: dialogHTML,
                    showConfirmButton: false,
                    width: 600,
                    customClass: {
                        popup: 'custom-alert'
                    },
                    didOpen: () => {
                        document.getElementById('closeDialogBtn').onclick = () => Swal.close();

                        document.querySelector('.download-btn').onclick = () => {
                            showExportDialogFromDB(datasetID, data.DSName);
                        };

                        document.querySelector('.delete-btn').onclick = () => {
                            //const theme = localStorage.getItem("theme") || "dark";

                            Swal.fire({
                                icon: 'warning',
                                title: 'Delete Confirmation',
                                text: 'Delete dataset?',
                                showCancelButton: true,
                                confirmButtonColor: '#ff9800',
                                cancelButtonColor: '#aaa',
                                confirmButtonText: 'Delete',
                                cancelButtonText: 'Cancel',
                                //background: theme === "dark" ? "#333" : "#fff",
                                //color: theme === "dark" ? "#fff" : "#000",
                                background: "#fff",
                                color: "#000",
                                width: "380px",
                                padding: "12px",
                                customClass: {
                                    popup: "custom-alert"
                                }
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    // Get current hidden datasets
                                    let hidden = JSON.parse(localStorage.getItem("hiddenDatasets") || "[]");

                                    if (!hidden.includes(datasetID)) {
                                        fetch('logDeletion.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json'
                                            },
                                            body: JSON.stringify({
                                                datasetID: datasetID,
                                                userID: UserID 
                                            })
                                        })
                                        .then(response => response.json())
                                        .then(result => {
                                            if (result.success) {
                                                hidden.push(datasetID);
                                                localStorage.setItem("hiddenDatasets", JSON.stringify(hidden));
                                                showAlert("success", "Deleted", "The dataset has been deleted.");
                                                setTimeout(() => location.reload(), 1000);
                                            } else {
                                                showAlert("error", "Error", "Failed to log deletion.");
                                            }
                                        })
                                        .catch(() => {
                                            showAlert("error", "Error", "Failed to communicate with the server.");
                                        });
                                    }

                                }
                            });
                        };

                    }

                });
            })
            .catch(error => console.error('Error loading dataset details:', error));
    }


    function showExportDialogFromDB(datasetID, datasetName) {
        Swal.fire({
            title: "Export Cleaned Data",
            html: `
                <div style="margin-bottom:10px;">
                    <b>Dataset:</b> <span style="color:#ff9800">${datasetName}</span>
                </div>
                <div style="margin-bottom:10px;">
                    <b>Choose Export Format:</b>
                    <select id="export-format" style="margin-left:10px;padding:4px;">
                        <option value="csv">CSV</option>
                        <option value="xlsx">Excel (.xlsx)</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: "Export",
            cancelButtonText: "Cancel",
            confirmButtonColor: "#ff9800",
            cancelButtonColor: "#aaa"
        }).then((result) => {
            if (result.isConfirmed) {
                const format = document.getElementById("export-format").value;

                // Don't build filename here, let backend decide based on format and type
                exportExtractedDataFromDB(datasetID, format);
            }
        });
    }


    function logExportToDB(eformat, datasetID, callback) {
        $.ajax({
            url: "logExport.php",
            type: "POST",
            contentType: "application/json",
            data: JSON.stringify({
                eformat: eformat,
                datasetid: datasetID
            }),
            success: function(response) {
                if (callback) callback(response);
            },
            error: function(xhr) {
                Swal.fire("Error", "Failed to log export.", "error");
            }
        });
    }


    function exportExtractedDataFromDB(datasetID, format) {
        logExportToDB(format, datasetID, function () {
            const url = `exportCleanedData.php?datasetID=${encodeURIComponent(datasetID)}&format=${encodeURIComponent(format)}`;
            window.location.href = url;
        });
    }


    // Function to search in sidebar based on dataset name
    document.getElementById('sidebarSearch').addEventListener('input', function () {
        const searchTerm = this.value.toLowerCase();
        const items = document.querySelectorAll('.dataset-item');
        const groupLabels = document.querySelectorAll('.dataset-group-label');

        // Track group visibility
        const groups = {};

        items.forEach(item => {
            const name = item.getAttribute('data-name').toLowerCase();
            const visible = name.includes(searchTerm);
            item.style.display = visible ? 'list-item' : 'none';

            // Mark group visibility
            const groupLabel = item.previousElementSibling;
            if (groupLabel && groupLabel.classList.contains('dataset-group-label')) {
                if (!groups[groupLabel.innerText]) groups[groupLabel.innerText] = [];
                groups[groupLabel.innerText].push(visible);
            }
        });

        // Show/hide group labels based on their items
        groupLabels.forEach(label => {
            const groupName = label.innerText;
            const hasVisible = groups[groupName]?.includes(true);
            label.style.display = hasVisible ? 'list-item' : 'none';
        });
    });



    // Hide datasets marked as deleted
    // Filter out hidden datasets after page load
    document.addEventListener('DOMContentLoaded', () => {
        const hidden = JSON.parse(localStorage.getItem("hiddenDatasets") || "[]");

        document.querySelectorAll('.dataset-item').forEach(item => {
            const datasetName = item.textContent.trim();
            const datasetID = item.getAttribute('onclick').match(/'([^']+)'/)[1];
            if (hidden.includes(datasetID)) {
                item.style.display = 'none';
            }
        });
    });



    function hideLoader() {
        const loader = document.getElementById("loader"); // Assuming there's a loader element
        if (loader) {
            loader.style.display = "none"; // Hide the loader
        }
    }

    function showAlert(icon, title, text,callback) {
        //const theme = localStorage.getItem("theme") || "dark"; // Get current theme

        Swal.fire({
            icon: icon,
            title: title,
            text: text,
            confirmButtonColor: "#ff9800",
            //background: theme === "dark" ? "#333" : "#fff",  // Match dark/light mode
            //color: theme === "dark" ? "#fff" : "#000",
            background: "#fff",  
            color: "#000",
            width: "380px",
            padding: "12px",
            customClass: {
                popup: "custom-alert"
            }
        }).then(() => {
            if (callback) callback(); // Call the function after alert closes
        });
    }

    function stopLoader() {
        clearInterval(window.loadingInterval); // Stop text animation if running
        loader.style.display = 'none'; // Hide loader
        uploadContainer.classList.remove('loading'); // Show upload content again
    }

    function confirmLogout() {
        //const theme = localStorage.getItem("theme") || "dark";
        
        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to log out?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ff9800',
            cancelButtonColor: 'rgba(0, 0, 0, 0.33)',
            confirmButtonText: 'Logout',
            //background: theme === "dark" ? "#333" : "#fff",
            //color: theme === "dark" ? "#fff" : "#000"
            background: "#fff",
            color: "#000"
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'logout.php';
            }
        });
    }

    /*function openThemeSelector() {
        const theme = localStorage.getItem("theme") || "dark";

        Swal.fire({
            title: 'Change Theme',
            html: `
                <button id="darkModeBtn" class="swal2-confirm swal2-styled" style="margin-right: 10px;">Dark Mode</button>
                <button id="lightModeBtn" class="swal2-cancel swal2-styled">Light Mode</button>
            `,
            showConfirmButton: false,
            background: theme === "dark" ? "#333" : "#fff",
            color: theme === "dark" ? "#fff" : "#000",
            didOpen: () => {
                const darkBtn = document.getElementById("darkModeBtn");
                const lightBtn = document.getElementById("lightModeBtn");

                // Button colors
                darkBtn.style.backgroundColor = "#555"; // Custom dark button color
                darkBtn.style.color = "#fff";
                darkBtn.style.border = "none";

                lightBtn.style.backgroundColor = "#eee"; // Custom light button color
                lightBtn.style.color = "#000";
                lightBtn.style.border = "1px solid #ccc";

                // Click listeners
                darkBtn.addEventListener("click", () => {
                    setTheme("dark");
                    Swal.close();
                });

                lightBtn.addEventListener("click", () => {
                    setTheme("light");
                    Swal.close();
                });
            }
        });
    }*/


    /*function setThemeAndClose(theme) {
        setTheme();
        Swal.close();
    }*/


</script>

</body>
</html>  