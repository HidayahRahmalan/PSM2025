<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PS4 Rental & Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/ps4rentalsystem/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-purple">
    <div class="container-fluid">
        <a class="navbar-brand" href="/ps4rentalsystem/index.php">PS4 Rentals</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (isset($_SESSION['customer_ID'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/customer/home.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/customer/games.php">Games</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/customer/new_booking.php">New Booking</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/customer/booking_history.php">Booking History</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/customer/settings.php">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/logout.php">Logout</a>
                    </li>
                <?php elseif (isset($_SESSION['staff_ID'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/staff/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/staff/bookings_management.php">Bookings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/staff/inventory_management.php">Inventory</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/staff/reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/staff/staff_settings.php">Settings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/logout.php">Logout</a>
                    </li>
                <?php elseif (!isset($_SESSION['customer_ID']) && !isset($_SESSION['staff_ID'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/ps4rentalsystem/customer/games.php">Games</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="customerDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Customer
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="customerDropdown">
                            <li><a class="dropdown-item" href="/ps4rentalsystem/customer/login.php">Login</a></li>
                            <li><a class="dropdown-item" href="/ps4rentalsystem/customer/register.php">Register</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="staffDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Staff/Admin
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="staffDropdown">
                            <li><a class="dropdown-item" href="/ps4rentalsystem/staff/staff_login.php">Login</a></li>
                            <li><a class="dropdown-item" href="/ps4rentalsystem/staff/staff_register.php">Register</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<main class="container mt-4"> 