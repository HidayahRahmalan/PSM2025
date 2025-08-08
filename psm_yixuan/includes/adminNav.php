<?php
// Get the current page name to highlight active link
$current_page = basename($_SERVER['PHP_SELF']);
?>
<header>
    <div class="container">
        <nav class="navbar">
            <div class="logo">
                <img src="https://upload.wikimedia.org/wikipedia/commons/b/bc/LogoUTeM-2016.jpg" alt="UTeM Logo">
                <h1>Smart&nbsp;Hostel&nbsp;Management&nbsp;System</h1>
            </div>
            <div class="nav-right">
                <div class="nav-links">
                    <a href="staffHomePage.php" <?php echo $current_page == 'staffHomePage.php' ? 'class="active"' : ''; ?>>Home</a>
                    
                    <div class="dropdown">
                        <a href="admUserMgmt.php" <?php echo in_array($current_page, ['admUserMgmt.php', 'admViewStud.php', 'admViewHs.php', 'admViewAdmInfo.php', 'admViewAdm.php', 'admViewAuditLog.php', 'admViewAuditTrail.php']) ? 'class="active"' : ''; ?>>User</a>
                        <div class="dropdown-content">
                            <a href="admViewStud.php" <?php echo $current_page == 'admViewStud.php' ? 'class="active"' : ''; ?>>Student Management</a>
                            <a href="admViewHs.php" <?php echo $current_page == 'admViewHs.php' ? 'class="active"' : ''; ?>>Hostel Staff Management</a>
                            <a href="admViewAdmInfo.php" <?php echo $current_page == 'admViewAdmInfo.php' || $current_page == 'admViewAdm.php' ? 'class="active"' : ''; ?>>View Admin</a>
                            <a href="admViewAuditLog.php" <?php echo $current_page == 'admViewAuditLog.php' ? 'class="active"' : ''; ?>>Audit Logs</a>
                            <a href="admViewAuditTrail.php" <?php echo $current_page == 'admViewAuditTrail.php' ? 'class="active"' : ''; ?>>Audit Trail</a>
                        </div>
                    </div>
                    
                    <div class="dropdown">
                        <a href="admBookNSemMgmt.php" <?php echo in_array($current_page, ['admBookNSemMgmt.php', 'admBookMgmt.php', 'admViewSem.php', 'admViewReport.php']) ? 'class="active"' : ''; ?>>Book&Sem</a>
                        <div class="dropdown-content">
                            <a href="admBookMgmt.php" <?php echo $current_page == 'admBookMgmt.php' ? 'class="active"' : ''; ?>>Booking Management</a>
                            <a href="admViewSem.php" <?php echo $current_page == 'admViewSem.php' ? 'class="active"' : ''; ?>>Semester Management</a>
                            <a href="admViewReport.php" <?php echo $current_page == 'admViewReport.php' ? 'class="active"' : ''; ?>>Report</a>
                        </div>
                    </div>
                    
                    <div class="dropdown">
                        <a href="admHostelRoomMgmt.php" <?php echo in_array($current_page, ['admHostelRoomMgmt.php', 'admManageHostel.php', 'admManageRoomChange.php']) ? 'class="active"' : ''; ?>>Hostel</a>
                        <div class="dropdown-content">
                            <a href="admManageHostel.php" <?php echo $current_page == 'admManageHostel.php' ? 'class="active"' : ''; ?>>Hostel Management</a>
                            <a href="admManageRoomChange.php" <?php echo $current_page == 'admManageRoomChange.php' ? 'class="active"' : ''; ?>>Room Change Management</a>
                        </div>
                    </div>
                    
                    <a href="admMainNComplaint.php" <?php echo $current_page == 'admMainNComplaint.php' ? 'class="active"' : ''; ?>>Maintenance</a>
                    
                    <a href="admPymtMgmt.php" <?php echo $current_page == 'admPymtMgmt.php' ? 'class="active"' : ''; ?>>Payment</a>
                    
                    <a href="admProfile.php" <?php echo $current_page == 'admProfile.php' ? 'class="active"' : ''; ?>>Profile</a>
                    <a href="admLogout.php">Logout</a>
                </div>
            </div>
        </nav>
    </div>
</header> 