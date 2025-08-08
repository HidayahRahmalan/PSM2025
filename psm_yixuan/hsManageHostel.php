<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not staff
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOSTEL STAFF') {
    header("Location: staffMainPage.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // Add new hostel
                $hostID = $_POST['hostID'];
                $name = strtoupper($_POST['name']);
                $location = strtoupper($_POST['location']);
                $status = 'ACTIVE'; // Default status is ACTIVE for new hostels

                // Validate hostel name contains gender designation
                if (!preg_match('/\((MALE|FEMALE)\)/', $name)) {
                    $error = "Hostel name must include gender designation in parentheses (MALE) or (FEMALE)";
                    break;
                }

                $stmt = $conn->prepare("INSERT INTO HOSTEL (HostID, Name, Location, Status) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("ssss", $hostID, $name, $location, $status);
                $stmt->execute();

                // Fetch the latest hostel ID
                $fetch_stmt = $conn->prepare("SELECT HostID FROM HOSTEL ORDER BY HostID DESC LIMIT 1");
                $fetch_stmt->execute();
                $result = $fetch_stmt->get_result();
                $latest_hostel = $result->fetch_assoc();
                $latest_hostID = $latest_hostel['HostID'];
                $fetch_stmt->close();

                // Add audit trail for new hostel
                $new_data = json_encode([
                    "HostID" => $latest_hostID,
                    "Name" => $name,
                    "Location" => $location,
                    "Status" => $status
                ]);

                $audit_stmt = $conn->prepare("CALL add_audit_trail(?, ?, ?, ?, ?, ?)");
                $table_name = "HOSTEL";
                $action = "INSERT";
                $old_data = null; // No old data for new hostel insert

                $audit_stmt->bind_param("ssssss", $table_name, $latest_hostID, $action, $_SESSION['empId'], $old_data, $new_data);
                $audit_stmt->execute();
                $audit_stmt->close();
                break;

            case 'edit':
                // Get old data first
                $hostID = $_POST['hostID'];
                $old_stmt = $conn->prepare("SELECT * FROM HOSTEL WHERE HostID = ?");
                $old_stmt->bind_param("s", $hostID);
                $old_stmt->execute();
                $old_result = $old_stmt->get_result();
                $old_data = $old_result->fetch_assoc();
                $old_stmt->close();

                // Update hostel
                $name = strtoupper($_POST['name']);
                $location = strtoupper($_POST['location']);
                $totalRoom = $_POST['totalRoom'];
                $totalFloor = $_POST['totalFloor'];
                $status = $_POST['status'];

                // Validate hostel name contains gender designation
                if (!preg_match('/\((MALE|FEMALE)\)/', $name)) {
                    $error = "Hostel name must include gender designation in parentheses (MALE) or (FEMALE)";
                    break;
                }

                $stmt = $conn->prepare("UPDATE HOSTEL SET Name = ?, Location = ?, Status = ? WHERE HostID = ?");
                $stmt->bind_param("ssss", $name, $location, $status, $hostID);
                $stmt->execute();

                // Add audit trail for hostel update
                $new_data = json_encode([
                    "HostID" => $hostID,
                    "Name" => $name,
                    "Location" => $location,
                    "Status" => $status
                ]);

                $old_data_json = json_encode($old_data);

                $audit_stmt = $conn->prepare("CALL add_audit_trail(?, ?, ?, ?, ?, ?)");
                $table_name = "HOSTEL";
                $action = "UPDATE";

                $audit_stmt->bind_param("ssssss", $table_name, $hostID, $action, $_SESSION['empId'], $old_data_json, $new_data);
                $audit_stmt->execute();
                $audit_stmt->close();
                break;

            case 'delete':
                // Delete hostel
                $hostID = $_POST['hostID'];
                $stmt = $conn->prepare("DELETE FROM HOSTEL WHERE HostID = ?");
                $stmt->bind_param("s", $hostID);
                $stmt->execute();
                break;
        }
    }
}

// Get all unique hostel IDs
$hostelIds = [];
try {
    $stmt = $conn->prepare("SELECT DISTINCT HostID FROM HOSTEL ORDER BY HostID");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $hostelIds[] = $row['HostID'];
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log("Error getting Hostel IDs: " . $e->getMessage());
}

// Get search parameters
$searchCriteria = isset($_GET['searchCriteria']) ? $_GET['searchCriteria'] : 'All';
$searchValue = isset($_GET['searchValue']) ? $_GET['searchValue'] : '';
$sortBy = isset($_GET['sortBy']) ? $_GET['sortBy'] : 'HostID';
$sortOrder = isset($_GET['sortOrder']) ? $_GET['sortOrder'] : 'ASC';

// Initialize hostels array
$hostels = [];
$totalResults = 0;

// Always fetch results on initial load or when search is performed
include 'hsFetchHostel.php';

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hostel Staff Manage Hostel - SHMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/hsNav.css">
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
            --success-color: #28a745;
            --cancel-color: grey;
            --danger-color: #dc3545;
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
            padding: 15px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
        }
        
        .logo img {
            height: 50px;
            margin-right: 10px;
        }
        
        .logo h1 {
            color: var(--primary-color);
            font-size: 24px;
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
        
        /* Table Styles */
        .table-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        th {
            background-color: var(--primary-color);
            color: var(--white);
            font-weight: bold;
        }
        
        tr:hover {
            background-color: rgba(44, 157, 255, 0.1);
        }
        
        /* Form Styles */
        .form-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--text-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(44, 157, 255, 0.2);
        }
        
        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
        }
        
        .btn-success {
            background-color: var(--success-color);
            color: var(--white);
        }

        .btn-cancel {
            background-color: var(--cancel-color);
            color: var(--white);
        }
        
        .btn-danger {
            background-color: var(--danger-color);
            color: var(--white);
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: var(--text-dark);
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }
        
        .modal-content {
            position: relative;
            background-color: var(--white);
            margin: 50px auto;
            padding: 20px;
            width: 90%;
            max-width: 500px;
            border-radius: 10px;
            box-shadow: var(--shadow);
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 24px;
            cursor: pointer;
        }
        
        /* Search Styles */
        .search-container {
            background-color: var(--white);
            border-radius: 10px;
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .search-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        .search-form .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .results-count {
            margin-bottom: 15px;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        /* Print Styles for Report */
        @media print {
            header, .search-container, .btn-group, .modal, .add-hostel-btn, .note {
                display: none !important;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            
            body {
                background-color: white;
                font-size: 12pt;
            }
            
            .table-container {
                box-shadow: none;
                padding: 0;
            }
            
            .page-header {
                text-align: center;
                margin-bottom: 20px;
            }
            
            .page-header h2 {
                font-size: 18pt;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
            }
            
            th, td {
                border: 1px solid #ddd;
            }
            
            /* Hide action buttons when printing */
            th:last-child, td:last-child {
                display: none;
            }
            
            @page {
                size: landscape;
                margin: 1cm;
            }
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .logo h1 {
                font-size: 20px;
            }
            
            .page-header h2 {
                font-size: 24px;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            .search-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/hsNav.php'; ?>
    
    <main class="container main-content">
        <section class="page-header">
            <h2>Manage Hostel</h2>
        </section>

        <section class="search-container">
            <form action="hsManageHostel.php" method="GET" class="search-form">
                <div class="form-group">
                    <label for="searchCriteria">Search By</label>
                    <select id="searchCriteria" name="searchCriteria" class="form-control" onchange="updateSearchField()">
                        <option value="All" <?php echo $searchCriteria === 'All' ? 'selected' : ''; ?>>All</option>
                        <option value="HostID" <?php echo $searchCriteria === 'HostID' ? 'selected' : ''; ?>>Hostel ID</option>
                        <option value="Name" <?php echo $searchCriteria === 'Name' ? 'selected' : ''; ?>>Name</option>
                        <option value="Location" <?php echo $searchCriteria === 'Location' ? 'selected' : ''; ?>>Location</option>
                        <option value="TotalFloor" <?php echo $searchCriteria === 'TotalFloor' ? 'selected' : ''; ?>>Total Floor</option>
                        <option value="Status" <?php echo $searchCriteria === 'Status' ? 'selected' : ''; ?>>Status</option>
                    </select>
                </div>
                
                <div class="form-group" id="searchValueContainer">
                    <label for="searchValue" id="searchValueLabel">Search Value</label>
                    <div id="searchValueField">
                        <!-- This will be dynamically updated based on the selected criteria -->
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="sortBy">Sort By</label>
                    <select id="sortBy" name="sortBy" class="form-control">
                        <option value="HostID" <?php echo $sortBy === 'HostID' ? 'selected' : ''; ?>>Hostel ID</option>
                        <option value="Name" <?php echo $sortBy === 'Name' ? 'selected' : ''; ?>>Name</option>
                        <option value="Location" <?php echo $sortBy === 'Location' ? 'selected' : ''; ?>>Location</option>
                        <option value="TotalRoom" <?php echo $sortBy === 'TotalRoom' ? 'selected' : ''; ?>>Total Room</option>
                        <option value="TotalFloor" <?php echo $sortBy === 'TotalFloor' ? 'selected' : ''; ?>>Total Floor</option>
                        <option value="Status" <?php echo $sortBy === 'Status' ? 'selected' : ''; ?>>Status</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="sortOrder">Sort Order</label>
                    <select id="sortOrder" name="sortOrder" class="form-control">
                        <option value="ASC" <?php echo $sortOrder === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        <option value="DESC" <?php echo $sortOrder === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    </select>
                </div>
                
                <div class="btn-group">
                    <button type="submit" name="search" class="btn btn-primary">Search</button>
                    <button type="button" class="btn btn-warning" onclick="printReport()">Generate Report</button>
                </div>
            </form>
        </section>

        <div class="table-container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Hostel List</h3>
                <button class="btn btn-primary" onclick="openAddModal()">Add New Hostel</button>
            </div>
            
            <div class="info-message" style="background-color: #e7f3fe; border-left: 6px solid #2196F3; margin-bottom: 15px; padding: 10px;">
                <p><strong>Note:</strong> Total Rooms and Total Floors values are automatically calculated based on the rooms in each hostel. These values cannot be manually edited.</p>
            </div>
            
            <?php if (empty($hostels)): ?>
                <p>No hostels found. Please try a different search criteria.</p>
            <?php else: ?>
                <p class="results-count">Total Results: <?php echo $totalResults; ?></p>
            
            <table>
                <thead>
                    <tr>
                        <th>Hostel ID</th>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Total Rooms</th>
                        <th>Total Floors</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hostels as $hostel): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($hostel['HostID']); ?></td>
                        <td><?php echo htmlspecialchars($hostel['Name']); ?></td>
                        <td><?php echo htmlspecialchars($hostel['Location']); ?></td>
                        <td><?php echo htmlspecialchars($hostel['TotalRoom']); ?></td>
                        <td><?php echo htmlspecialchars($hostel['TotalFloor']); ?></td>
                        <td><?php echo htmlspecialchars($hostel['Status'] ?? 'ACTIVE'); ?></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-primary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($hostel)); ?>)">Edit</button>
                                <button class="btn btn-primary" onclick="viewRooms('<?php echo htmlspecialchars($hostel['HostID']); ?>')">View Rooms</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <!-- Add/Edit Modal -->
        <div id="hostelModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeModal()">&times;</span>
                <h3 id="modalTitle">Add New Hostel</h3>
                <form id="hostelForm" method="POST">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="hostID" id="formHostID">
                    
                    <div class="form-group">
                        <label for="name">Hostel Name</label>
                        <div class="info-message" style="background-color: #e7f3fe; border-left: 6px solid #2196F3; margin-bottom: 10px; padding: 10px; font-size: 14px;">
                            <p>Include gender designation in parentheses, e.g., "BLOCK A (MALE)" or "BLOCK B (FEMALE)"</p>
                        </div>
                        <input type="text" id="name" name="name" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="location">Location</label>
                        <input type="text" id="location" name="location" class="form-control" required>
                    </div>
                    
                    <div id="editOnlyFields" style="display: none;">
                        <div class="form-group">
                            <label for="totalRoom">Total Rooms</label>
                            <input type="number" id="totalRoom" name="totalRoom" min="1" class="form-control" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="totalFloor">Total Floors</label>
                            <input type="number" id="totalFloor" name="totalFloor" min="1" class="form-control" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="ACTIVE">Active</option>
                                <option value="INACTIVE">Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Error message container -->
                    <div id="formErrorContainer" class="error-message" style="display: none; color: var(--danger-color); margin-bottom: 15px; padding: 10px; background-color: rgba(220, 53, 69, 0.1); border-radius: 5px;"></div>
                    
                    <div class="btn-group">
                        <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Modal Functions
        function openAddModal() {
            // Clear any previous error messages
            const errorContainer = document.getElementById('formErrorContainer');
            errorContainer.style.display = 'none';
            errorContainer.textContent = '';
            
            document.getElementById('modalTitle').textContent = 'Add New Hostel';
            document.getElementById('formAction').value = 'add';
            document.getElementById('formHostID').value = '';
            document.getElementById('name').value = '';
            document.getElementById('location').value = '';
            document.getElementById('editOnlyFields').style.display = 'none';
            document.getElementById('hostelModal').style.display = 'block';
        }

        function openEditModal(hostel) {
            // Clear any previous error messages
            const errorContainer = document.getElementById('formErrorContainer');
            errorContainer.style.display = 'none';
            errorContainer.textContent = '';
            
            document.getElementById('modalTitle').textContent = 'Edit Hostel';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('formHostID').value = hostel.HostID;
            document.getElementById('name').value = hostel.Name;
            document.getElementById('location').value = hostel.Location;
            document.getElementById('totalRoom').value = hostel.TotalRoom;
            document.getElementById('totalFloor').value = hostel.TotalFloor;
            document.getElementById('editOnlyFields').style.display = 'block';
            
            // Set status with default
            if (hostel.Status) {
                document.getElementById('status').value = hostel.Status;
            } else {
                document.getElementById('status').value = 'ACTIVE';
            }
            
            document.getElementById('hostelModal').style.display = 'block';
        }

        function viewRooms(hostID) {
            window.location.href = `hsManageRoom.php?hostID=${hostID}`;
        }

        function closeModal() {
            document.getElementById('hostelModal').style.display = 'none';
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }
        
        // Validate form before submission
        document.getElementById('hostelForm').addEventListener('submit', function(e) {
            // Reset error message
            const errorContainer = document.getElementById('formErrorContainer');
            errorContainer.style.display = 'none';
            errorContainer.textContent = '';
            
            // Get form data
            const formAction = document.getElementById('formAction').value;
            const hostID = document.getElementById('formHostID').value;
            const name = document.getElementById('name').value.trim();
            
            // Check if hostel name already exists (for new hostels)
            if (formAction === 'add') {
                // Check existing hostels for duplicate name
                const existingHostels = <?php echo json_encode($hostels); ?>;
                const nameExists = existingHostels.some(hostel => 
                    hostel.Name.toUpperCase() === name.toUpperCase()
                );
                
                if (nameExists) {
                    e.preventDefault(); // Prevent form submission
                    errorContainer.textContent = 'A hostel with this name already exists.';
                    errorContainer.style.display = 'block';
                    return false;
                }
            } else if (formAction === 'edit') {
                // Check for duplicate names when editing, excluding the current hostel
                const existingHostels = <?php echo json_encode($hostels); ?>;
                const nameExists = existingHostels.some(hostel => 
                    hostel.Name.toUpperCase() === name.toUpperCase() && hostel.HostID !== hostID
                );
                
                if (nameExists) {
                    e.preventDefault(); // Prevent form submission
                    errorContainer.textContent = 'A hostel with this name already exists.';
                    errorContainer.style.display = 'block';
                    return false;
                }
            }
            
            return true;
        });

        // Function to update the search field based on the selected criteria
        function updateSearchField() {
            const searchCriteria = document.getElementById('searchCriteria').value;
            const searchValueContainer = document.getElementById('searchValueField');
            const searchValueLabel = document.getElementById('searchValueLabel');
            
            // Get the current search value
            const currentValue = '<?php echo htmlspecialchars($searchValue); ?>';
            
            // Clear the current search value field
            searchValueContainer.innerHTML = '';
            
            // Create the appropriate input field based on the selected criteria
            switch (searchCriteria) {
                case 'All':
                    searchValueLabel.textContent = 'No search value needed';
                    // Not visible to the user on the page 
                    searchValueContainer.innerHTML = '<input type="hidden" name="searchValue" value="">';
                    break;
                    
                case 'HostID':
                    searchValueLabel.textContent = 'Select Hostel ID';
                    const hostelSelect = document.createElement('select');
                    hostelSelect.name = 'searchValue';
                    hostelSelect.className = 'form-control';
                    hostelSelect.innerHTML = '<option value="">Select Hostel ID</option>';
                    
                    <?php foreach ($hostelIds as $hostelId): ?>
                        hostelSelect.innerHTML += '<option value="<?php echo $hostelId; ?>" <?php echo $searchValue === $hostelId ? 'selected' : ''; ?>><?php echo $hostelId; ?></option>';
                    <?php endforeach; ?>
                    
                    searchValueContainer.appendChild(hostelSelect);
                    break;
                    
                case 'Name':
                    searchValueLabel.textContent = 'Enter Hostel Name';
                    const nameInput = document.createElement('input');
                    nameInput.type = 'text';
                    nameInput.name = 'searchValue';
                    nameInput.className = 'form-control';
                    nameInput.value = currentValue;
                    nameInput.placeholder = 'Enter hostel name';
                    searchValueContainer.appendChild(nameInput);
                    break;
                    
                case 'Location':
                    searchValueLabel.textContent = 'Enter Location';
                    const locationInput = document.createElement('input');
                    locationInput.type = 'text';
                    locationInput.name = 'searchValue';
                    locationInput.className = 'form-control';
                    locationInput.value = currentValue;
                    locationInput.placeholder = 'Enter location';
                    searchValueContainer.appendChild(locationInput);
                    break;
                    
                case 'TotalFloor':
                    searchValueLabel.textContent = 'Select Total Floor';
                    const floorSelect = document.createElement('select');
                    floorSelect.name = 'searchValue';
                    floorSelect.className = 'form-control';
                    floorSelect.innerHTML = '<option value="">Select Total Floor</option>';
                    
                    // Creating options from 1 to 9
                    for (let i = 1; i <= 9; i++) {
                        const selected = currentValue == i ? 'selected' : '';
                        floorSelect.innerHTML += `<option value="${i}" ${selected}>${i}</option>`;
                    }
                    
                    searchValueContainer.appendChild(floorSelect);
                    break;
                    
                case 'Status':
                    searchValueLabel.textContent = 'Select Status';
                    const statusSelect = document.createElement('select');
                    statusSelect.name = 'searchValue';
                    statusSelect.className = 'form-control';
                    statusSelect.innerHTML = `
                        <option value="">Select Status</option>
                        <option value="ACTIVE" ${currentValue === 'ACTIVE' ? 'selected' : ''}>Active</option>
                        <option value="INACTIVE" ${currentValue === 'INACTIVE' ? 'selected' : ''}>Inactive</option>
                    `;
                    searchValueContainer.appendChild(statusSelect);
                    break;
            }
        }
        
        // Function to print the report
        function printReport() {
            window.print();
        }
        
        // Initialize the search field on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateSearchField();
        });
    </script>
</body>
</html> 