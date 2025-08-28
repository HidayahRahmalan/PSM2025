<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

// Get all games
$stmt = $pdo->prepare('SELECT * FROM games ORDER BY game_title');
$stmt->execute();
$games = $stmt->fetchAll();

// Get all consoles
$stmt = $pdo->prepare('SELECT * FROM consoles ORDER BY console_name');
$stmt->execute();
$consoles = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - PS4 Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-boxes"></i> Inventory Management</h2>
            <div>
                <a href="add_game.php" class="btn btn-success me-2">
                    <i class="fas fa-plus"></i> Add Game
                </a>
                <a href="add_console.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Console
                </a>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> 
                <?php 
                    switch($_GET['success']) {
                        case 'game_added':
                            $game_id = $_GET['game_id'] ?? '';
                            echo 'Game added successfully! Game ID: ' . htmlspecialchars($game_id);
                            break;
                        case 'game_updated':
                            echo 'Game updated successfully!';
                            break;
                        case 'game_deleted':
                            echo 'Game deleted successfully!';
                            break;
                        case 'console_added':
                            echo 'Console added successfully!';
                            break;
                        case 'console_updated':
                            echo 'Console updated successfully!';
                            break;
                        case 'console_deleted':
                            echo 'Console deleted successfully!';
                            break;
                        default:
                            echo 'Operation completed successfully!';
                    }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> 
                <?php 
                    switch($_GET['error']) {
                        case 'delete_failed':
                            echo 'Failed to delete item. Please try again.';
                            break;
                        case 'update_failed':
                            echo 'Failed to update item. Please try again.';
                            break;
                        default:
                            echo 'An error occurred. Please try again.';
                    }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Games Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5><i class="fas fa-gamepad"></i> Games Inventory (<?php echo count($games); ?> games)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Description</th>
                                <th>Players</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($games)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="fas fa-gamepad fa-2x mb-3"></i>
                                        <p>No games found. <a href="add_game.php" class="text-decoration-none">Add your first game!</a></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($games as $game): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($game['game_ID']); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($game['game_picture']): ?>
                                                <img src="../<?php echo htmlspecialchars($game['game_picture']); ?>" 
                                                     alt="<?php echo htmlspecialchars($game['game_title']); ?>" 
                                                     class="me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($game['game_title']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $description = $game['game_description'] ?? '';
                                            echo htmlspecialchars(strlen($description) > 50 ? substr($description, 0, 50) . '...' : $description); 
                                        ?>
                                    </td>
                                    <td><?php echo $game['min_players'] . '-' . $game['max_players']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $game['is_available'] ? 'success' : 'danger'; ?>">
                                            <?php echo $game['is_available'] ? 'Available' : 'Unavailable'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit_game.php?game_ID=<?php echo $game['game_ID']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="delete_game.php?game_ID=<?php echo $game['game_ID']; ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this game? This action cannot be undone.');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Consoles Section -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-tv"></i> Consoles Inventory (<?php echo count($consoles); ?> consoles)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Model</th>
                                <th>Location</th>
                                <th>Max Controllers</th>
                                <th>Status</th>
                                <th>Hourly Rate</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($consoles)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="fas fa-tv fa-2x mb-3"></i>
                                        <p>No consoles found. <a href="add_console.php" class="text-decoration-none">Add your first console!</a></p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($consoles as $console): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($console['console_ID']); ?></td>
                                    <td><?php echo htmlspecialchars($console['console_name']); ?></td>
                                    <td><?php echo htmlspecialchars($console['console_model'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($console['location_description'] ?? 'N/A'); ?></td>
                                    <td><?php echo $console['max_controllers']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $console['consoles_status'] === 'available' ? 'success' : 
                                                ($console['consoles_status'] === 'in_use' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $console['consoles_status'])); ?>
                                        </span>
                                    </td>
                                    <td>RM <?php echo number_format($console['hourly_rate'], 2); ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="edit_console.php?console_ID=<?php echo $console['console_ID']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                            <a href="delete_console.php?console_ID=<?php echo $console['console_ID']; ?>" class="btn btn-sm btn-danger" 
                                               onclick="return confirm('Are you sure you want to delete this console? This action cannot be undone.');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 