<?php
// Start session for user data
session_start();

// Include database connection
include 'dbConnection.php';

// Redirect if not logged in or not hostel staff
if (!isset($_SESSION['empId']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'HOSTEL STAFF') {
    header("Location: staffMainPage.php");
    exit();
}

// Check if semester ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No semester ID provided.";
    header("Location: hsViewSem.php");
    exit();
}

$semId = $_GET['id'];
$success = true;
$error_message = "";

// Start transaction
$conn->begin_transaction();

try {
    // First, get all BookIDs associated with this semester
    $stmt = $conn->prepare("SELECT BookID FROM BOOKING WHERE SemID = ?");
    $stmt->bind_param("s", $semId);
    $stmt->execute();
    $result = $stmt->get_result();
    $bookIds = [];
    while ($row = $result->fetch_assoc()) {
        $bookIds[] = $row['BookID'];
    }
    $stmt->close();

    // For each BookID, delete associated records in REQUEST and PAYMENT tables
    foreach ($bookIds as $bookId) {
        // Delete from REQUEST table
        $stmt = $conn->prepare("DELETE FROM REQUEST WHERE BookID = ?");
        $stmt->bind_param("s", $bookId);
        $stmt->execute();
        $stmt->close();

        // Delete from PAYMENT table
        $stmt = $conn->prepare("DELETE FROM PAYMENT WHERE BookID = ?");
        $stmt->bind_param("s", $bookId);
        $stmt->execute();
        $stmt->close();
    }

    // Delete from BOOKING table
    $stmt = $conn->prepare("DELETE FROM BOOKING WHERE SemID = ?");
    $stmt->bind_param("s", $semId);
    $stmt->execute();
    $stmt->close();

    // Finally, delete the semester
    $stmt = $conn->prepare("DELETE FROM SEMESTER WHERE SemID = ?");
    $stmt->bind_param("s", $semId);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $conn->commit();
    $_SESSION['success'] = "Semester and all related records have been successfully deleted.";

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error'] = "Error deleting semester: " . $e->getMessage();
    $success = false;
}

// Close database connection
$conn->close();

// Redirect back to semester view page
header("Location: hsViewSem.php");
exit();
?> 