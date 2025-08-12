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
                    <a href="studHomePage.php" <?php echo $current_page == 'studHomePage.php' ? 'class="active"' : ''; ?>>Home</a>
                    <div class="dropdown">
                        <a href="studBookNSem.php" <?php echo in_array($current_page, ['studBookNSem.php', 'studRoomBook.php', 'studBookHistory.php', 'studViewSem.php', 'studRoomChange.php']) ? 'class="active"' : ''; ?>>Book&Sem</a>
                        <div class="dropdown-content">
                            <a href="studRoomBook.php" <?php echo $current_page == 'studRoomBook.php' ? 'class="active"' : ''; ?>>Book Room</a>
                            <a href="studBookHistory.php" <?php echo $current_page == 'studBookHistory.php' ? 'class="active"' : ''; ?>>Booking History</a>
                            <a href="studViewSem.php" <?php echo $current_page == 'studViewSem.php' ? 'class="active"' : ''; ?>>View Semester</a>
                            <a href="studRoomChange.php" <?php echo $current_page == 'studRoomChange.php' ? 'class="active"' : ''; ?>>View Request</a>
                        </div>
                    </div>
                    <a href="studMainNComplaint.php" <?php echo $current_page == 'studMainNComplaint.php' ? 'class="active"' : ''; ?>>Maintenance&nbsp;&amp;&nbsp;Complaint</a>
                    <a href="studPayment.php" <?php echo $current_page == 'studPayment.php' ? 'class="active"' : ''; ?>>Payment</a>
                    <a href="studProfile.php" <?php echo $current_page == 'studProfile.php' ? 'class="active"' : ''; ?>>Profile</a>
                    <a href="studLogout.php">Logout</a>
                </div>
            </div>
        </nav>
    </div>
</header>

<style>
/* Ensure proper display of navigation items */
header {
    padding: 10px 0 !important;
}

.navbar {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    flex-wrap: nowrap !important;
    padding: 0 !important;
}

.logo {
    display: flex !important;
    align-items: center !important;
    flex-shrink: 0 !important;
}

.logo h1 {
    white-space: nowrap !important;
    font-size: 22px !important;
    margin: 0 !important;
}

.logo img {
    height: 45px !important;
    margin-right: 10px !important;
}

.nav-right {
    display: flex !important;
    align-items: center !important;
}

.nav-links {
    display: flex !important;
    flex-wrap: nowrap !important;
    align-items: center !important;
    gap: 15px !important;
    margin: 0 !important;
}

.nav-links a {
    white-space: nowrap !important;
    padding: 8px 10px !important;
}

@media (max-width: 991px) {
    .navbar {
        flex-wrap: wrap !important;
    }
    
    .logo {
        margin-bottom: 10px !important;
        justify-content: center !important;
        width: 100% !important;
    }
    
    .nav-right {
        width: 100% !important;
        justify-content: center !important;
    }
    
    .nav-links {
        justify-content: center !important;
    }
}

@media (max-width: 767px) {
    .nav-links {
        flex-direction: column !important;
        align-items: center !important;
        width: 100% !important;
    }
    
    .nav-links a {
        width: 100% !important;
        padding: 10px !important;
        text-align: center !important;
    }
    
    .dropdown {
        width: 100% !important;
    }
    
    .dropdown > a {
        justify-content: center !important;
        display: flex !important;
    }
}
</style> 