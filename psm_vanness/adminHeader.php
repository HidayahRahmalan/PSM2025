<?php
session_start();
include 'dbConnection.php';
include 'activityTracker.php';

$UserID = $_SESSION['UserID'];
$UserRole = $_SESSION['URole'];
?>
<link rel="stylesheet" href="adminHeader.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

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
        <a class="dropdown-btn" href="restore.php">RESTORE</a>

        <!-- User Dropdown -->
        <div class="dropdown">
            <button class="dropdown-btn" onclick="toggleDropdown('userDropdown')"><?php echo $UserRole; ?></button>
            <div id="userDropdown" class="dropdown-content">
                <a href="adminProfile.php"><img src="profile1.png"> Profile</a>
                <a href="#" onclick="openModal('logoutModalAdmin')"><img src="logout.png"> Logout</a>
            </div>
        </div>
    </div>
</div>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModalAdmin" tabindex="-1" role="dialog" aria-hidden="true" style="display: none;">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Logout</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to log out?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" onclick="logout()">Logout</button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>


<script>
// Toggle dropdown
function toggleDropdown(id) {
    document.querySelectorAll(".dropdown-content").forEach(el => {
        if (el.id !== id) el.parentElement.classList.remove("show");
    });
    const element = document.getElementById(id).parentElement;
    element.classList.toggle("show");
}

// Close dropdown when clicking outside, EXCLUDING modal openers
window.addEventListener("click", function (event) {
    // If the click is not on a dropdown button
    if (!event.target.closest('.dropdown')) {
        document.querySelectorAll(".dropdown").forEach(dropdown => {
            dropdown.classList.remove("show");
        });
    }
});



// Redirect to logout
function logout() {
    window.location.href = "logout.php";
}
$(document).ready(function () {
    // Hide modal forcibly on load
    $('#logoutModalAdmin').modal('hide');
});

// Open modal only on actual click of logout link
function openModal(modalId) {
    $('#' + modalId).modal('show');
}

</script>
