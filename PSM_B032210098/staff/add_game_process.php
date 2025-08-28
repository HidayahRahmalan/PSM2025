<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $game_title = trim($_POST['game_title'] ?? '');
    $game_description = trim($_POST['game_description'] ?? '');
    $min_players = (int)($_POST['min_players'] ?? 1);
    $max_players = (int)($_POST['max_players'] ?? 4);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    // Validate required fields
    if (empty($game_title)) {
        header('Location: add_game.php?error=title_required');
        exit();
    }

    // Validate player counts
    if ($min_players < 1 || $max_players > 8 || $min_players > $max_players) {
        header('Location: add_game.php?error=invalid_players');
        exit();
    }

    // Generate unique game ID
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM games');
    $stmt->execute();
    $count = $stmt->fetchColumn();
    $game_ID = 'GAME' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

    // Handle file uploads
    $game_picture = null;
    $game_video_trailer = null;

    // Handle game picture upload
    if (isset($_FILES['game_picture']) && $_FILES['game_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['game_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($file['type'], $allowed_types)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'game_' . $game_ID . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/images/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $game_picture = 'uploads/images/' . $filename;
            }
        }
    }

    // Handle video trailer upload
    if (isset($_FILES['game_video_trailer']) && $_FILES['game_video_trailer']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['game_video_trailer'];
        $allowed_types = ['video/mp4', 'video/webm', 'video/ogg'];
        
        if (in_array($file['type'], $allowed_types)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'game_' . $game_ID . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/videos/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $game_video_trailer = 'uploads/videos/' . $filename;
            }
        }
    }

    // Insert game into database
    $stmt = $pdo->prepare('INSERT INTO games (game_ID, game_title, game_description, min_players, max_players, game_picture, game_video_trailer, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    
    if ($stmt->execute([$game_ID, $game_title, $game_description, $min_players, $max_players, $game_picture, $game_video_trailer, $is_available])) {
        // Insert tags if any selected
        if (!empty($_POST['tags']) && is_array($_POST['tags'])) {
            $stmtTag = $pdo->prepare('INSERT INTO game_tags (game_ID, tag_ID) VALUES (?, ?)');
            foreach ($_POST['tags'] as $tag_ID) {
                $stmtTag->execute([$game_ID, $tag_ID]);
            }
        }
        header('Location: inventory_management.php?success=game_added&game_id=' . $game_ID);
    } else {
        header('Location: add_game.php?error=insert_failed');
    }
    exit();
} else {
    header('Location: add_game.php');
    exit();
}
?> 