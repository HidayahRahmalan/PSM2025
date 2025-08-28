<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Console - PS4 Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h4><i class="fas fa-plus"></i> Add New Console</h4>
                    </div>
                    <div class="card-body">
                        <form action="add_console_process.php" method="POST">
                            <div class="mb-3">
                                <label for="console_name" class="form-label">Console Name</label>
                                <input type="text" class="form-control" id="console_name" name="console_name" required>
                            </div>

                            <div class="mb-3">
                                <label for="console_model" class="form-label">Model</label>
                                <input type="text" class="form-control" id="console_model" name="console_model" placeholder="e.g., PS4 Pro, PS4 Slim">
                            </div>

                            <div class="mb-3">
                                <label for="location_description" class="form-label">Location Description</label>
                                <textarea class="form-control" id="location_description" name="location_description" rows="2" placeholder="e.g., Gaming Room A, Station 1"></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_controllers" class="form-label">Maximum Controllers</label>
                                        <input type="number" class="form-control" id="max_controllers" name="max_controllers" min="1" max="8" value="4" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="hourly_rate" class="form-label">Hourly Rate (RM)</label>
                                        <input type="number" class="form-control" id="hourly_rate" name="hourly_rate" min="0" step="0.01" value="15.00" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="consoles_status" class="form-label">Status</label>
                                <select class="form-select" id="consoles_status" name="consoles_status" required>
                                    <option value="available" selected>Available</option>
                                    <option value="in_use">In Use</option>
                                    <option value="maintenance">Maintenance</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Any additional notes about this console"></textarea>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="inventory_management.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Add Console
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 