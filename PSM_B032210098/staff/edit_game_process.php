<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game_ID = $_POST['game_ID'] ?? '';
    if (!$game_ID) {
        header('Location: inventory_management.php?error=invalid_game');
        exit();
    }

    // Get current game data
    $stmt = $pdo->prepare('SELECT * FROM games WHERE game_ID = ?');
    $stmt->execute([$game_ID]);
    $current_game = $stmt->fetch();

    if (!$current_game) {
        header('Location: inventory_management.php?error=game_not_found');
        exit();
    }

    // Get form data
    $game_title = trim($_POST['game_title'] ?? '');
    $game_description = trim($_POST['game_description'] ?? '');
    $min_players = (int)($_POST['min_players'] ?? 1);
    $max_players = (int)($_POST['max_players'] ?? 4);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    // Validate required fields
    if (empty($game_title)) {
        header('Location: edit_game.php?game_ID=' . $game_ID . '&error=title_required');
        exit();
    }

    // Validate player counts
    if ($min_players < 1 || $max_players > 8 || $min_players > $max_players) {
        header('Location: edit_game.php?game_ID=' . $game_ID . '&error=invalid_players');
        exit();
    }

    // Handle game picture upload
    $game_picture = $current_game['game_picture'];
    if (isset($_FILES['game_picture']) && $_FILES['game_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['game_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($file['type'], $allowed_types)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'game_' . $game_ID . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/images/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old picture if exists
                if ($current_game['game_picture'] && file_exists('../' . $current_game['game_picture'])) {
                    unlink('../' . $current_game['game_picture']);
                }
                $game_picture = 'uploads/images/' . $filename;
            }
        }
    }

    // Handle video trailer upload
    $game_video_trailer = $current_game['game_video_trailer'];
    if (isset($_FILES['game_video_trailer']) && $_FILES['game_video_trailer']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['game_video_trailer'];
        $allowed_types = ['video/mp4', 'video/webm', 'video/ogg'];
        
        if (in_array($file['type'], $allowed_types)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'game_' . $game_ID . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/videos/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                // Delete old video if exists
                if ($current_game['game_video_trailer'] && file_exists('../' . $current_game['game_video_trailer'])) {
                    unlink('../' . $current_game['game_video_trailer']);
                }
                $game_video_trailer = 'uploads/videos/' . $filename;
            }
        }
    }

    // Update game in database
    $stmt = $pdo->prepare('UPDATE games SET game_title=?, game_description=?, min_players=?, max_players=?, game_picture=?, game_video_trailer=?, is_available=? WHERE game_ID=?');
    if ($stmt->execute([$game_title, $game_description, $min_players, $max_players, $game_picture, $game_video_trailer, $is_available, $game_ID])) {
        // Update tags
        $pdo->prepare('DELETE FROM game_tags WHERE game_ID = ?')->execute([$game_ID]);
        if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
            $stmtTag = $pdo->prepare('INSERT INTO game_tags (game_ID, tag_ID) VALUES (?, ?)');
            foreach ($_POST['tags'] as $tag_ID) {
                $stmtTag->execute([$game_ID, $tag_ID]);
            }
        }
        header('Location: inventory_management.php?success=game_updated');
    } else {
        header('Location: edit_game.php?game_ID=' . $game_ID . '&error=update_failed');
    }
    exit();
} else {
    header('Location: inventory_management.php');
    exit();
}
?> 