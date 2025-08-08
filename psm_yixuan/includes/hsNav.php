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
                    
                    <a href="hsUserMgmt.php" <?php echo $current_page == 'hsUserMgmt.php' ? 'class="active"' : ''; ?>>User</a>
                    
                    <div class="dropdown">
                        <a href="hsBookNSemMgmt.php" <?php echo in_array($current_page, ['hsBookNSemMgmt.php', 'hsBookMgmt.php', 'hsViewSem.php']) ? 'class="active"' : ''; ?>>Book&Sem</a>
                        <div class="dropdown-content">
                            <a href="hsBookMgmt.php" <?php echo $current_page == 'hsBookMgmt.php' ? 'class="active"' : ''; ?>>Booking Management</a>
                            <a href="hsViewSem.php" <?php echo $current_page == 'hsViewSem.php' ? 'class="active"' : ''; ?>>Semester Management</a>
                        </div>
                    </div>
                    
                    <div class="dropdown">
                        <a href="hsHostelRoomMgmt.php" <?php echo in_array($current_page, ['hsHostelRoomMgmt.php', 'hsManageHostel.php', 'hsManageRoomChange.php']) ? 'class="active"' : ''; ?>>Hostel</a>
                        <div class="dropdown-content">
                            <a href="hsManageHostel.php" <?php echo $current_page == 'hsManageHostel.php' ? 'class="active"' : ''; ?>>Hostel Management</a>
                            <a href="hsManageRoomChange.php" <?php echo $current_page == 'hsManageRoomChange.php' ? 'class="active"' : ''; ?>>Room Change Management</a>
                        </div>
                    </div>
                    
                    <a href="hsMainNComplaint.php" <?php echo $current_page == 'hsMainNComplaint.php' ? 'class="active"' : ''; ?>>Maintenance</a>
                    
                    <a href="hsPymtMgmt.php" <?php echo $current_page == 'hsPymtMgmt.php' ? 'class="active"' : ''; ?>>Payment</a>
                    
                    <a href="hsProfile.php" <?php echo $current_page == 'hsProfile.php' ? 'class="active"' : ''; ?>>Profile</a>
                    <a href="hsLogout.php">Logout</a>
                </div>
            </div>
        </nav>
    </div>
</header> 