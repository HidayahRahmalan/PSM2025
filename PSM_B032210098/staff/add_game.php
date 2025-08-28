<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

// Fetch all tags for dropdown
$allTags = $pdo->query("SELECT * FROM tags ORDER BY tag_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Game - PS4 Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-header bg-purple text-white">
                        <h4><i class="fas fa-plus"></i> Add New Game</h4>
                    </div>
                    <div class="card-body p-4">
                        <form action="add_game_process.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="game_title" class="form-label">
                                    <i class="fas fa-gamepad"></i> Game Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control form-control-lg" id="game_title" name="game_title" required>
                                <div class="invalid-feedback">
                                    Please provide a game title.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="game_description" class="form-label">
                                    <i class="fas fa-align-left"></i> Description
                                </label>
                                <textarea class="form-control form-control-lg" id="game_description" name="game_description" rows="4" placeholder="Enter game description..."></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="min_players" class="form-label">
                                            <i class="fas fa-users"></i> Minimum Players <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" class="form-control form-control-lg" id="min_players" name="min_players" min="1" max="8" value="1" required>
                                        <div class="invalid-feedback">
                                            Please enter a valid number of players (1-8).
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="max_players" class="form-label">
                                            <i class="fas fa-users"></i> Maximum Players <span class="text-danger">*</span>
                                        </label>
                                        <input type="number" class="form-control form-control-lg" id="max_players" name="max_players" min="1" max="8" value="4" required>
                                        <div class="invalid-feedback">
                                            Please enter a valid number of players (1-8).
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="game_picture" class="form-label">
                                    <i class="fas fa-image"></i> Game Picture
                                </label>
                                <input type="file" class="form-control form-control-lg" id="game_picture" name="game_picture" accept="image/*">
                                <div class="form-text">Upload a cover image for the game (JPG, PNG, GIF). Recommended size: 300x400px</div>
                            </div>

                            <div class="mb-3">
                                <label for="game_video_trailer" class="form-label">
                                    <i class="fas fa-video"></i> Video Trailer
                                </label>
                                <input type="file" class="form-control form-control-lg" id="game_video_trailer" name="game_video_trailer" accept="video/*">
                                <div class="form-text">Upload a video trailer (MP4, WebM, OGG). Max size: 50MB</div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_available" name="is_available" checked>
                                    <label class="form-check-label" for="is_available">
                                        <i class="fas fa-check-circle"></i> Available for rental
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-tags"></i> Tags</label>
                                <div>
                                    <?php foreach ($allTags as $tag): ?>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="tag_<?php echo $tag['tag_ID']; ?>" name="tags[]" value="<?php echo $tag['tag_ID']; ?>">
                                            <label class="form-check-label" for="tag_<?php echo $tag['tag_ID']; ?>"><?php echo htmlspecialchars($tag['tag_name']); ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="form-text">Select one or more tags for this game.</div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="inventory_management.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left"></i> Back to Inventory
                                </a>
                                <button type="submit" class="btn btn-purple btn-lg">
                                    <i class="fas fa-save"></i> Add Game
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
    <script>
        // Form validation
        (function() {
            'use strict';
            window.addEventListener('load', function() {
                var forms = document.getElementsByClassName('needs-validation');
                var validation = Array.prototype.filter.call(forms, function(form) {
                    form.addEventListener('submit', function(event) {
                        if (form.checkValidity() === false) {
                            event.preventDefault();
                            event.stopPropagation();
                        }
                        form.classList.add('was-validated');
                    }, false);
                });
            }, false);
        })();

        // Validate max players is greater than or equal to min players
        document.getElementById('max_players').addEventListener('change', function() {
            var minPlayers = parseInt(document.getElementById('min_players').value);
            var maxPlayers = parseInt(this.value);
            
            if (maxPlayers < minPlayers) {
                this.setCustomValidity('Maximum players must be greater than or equal to minimum players');
            } else {
                this.setCustomValidity('');
            }
        });

        document.getElementById('min_players').addEventListener('change', function() {
            var minPlayers = parseInt(this.value);
            var maxPlayers = parseInt(document.getElementById('max_players').value);
            
            if (maxPlayers < minPlayers) {
                document.getElementById('max_players').setCustomValidity('Maximum players must be greater than or equal to minimum players');
            } else {
                document.getElementById('max_players').setCustomValidity('');
            }
        });
    </script>
</body>
</html> 