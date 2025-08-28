<?php
session_start();
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit();
}

require_once '../db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $game_ID = $_GET['game_ID'] ?? '';
    if (!$game_ID) {
        header('Location: inventory_management.php?error=invalid_game');
        exit();
    }

    // Get game data to delete associated files
    $stmt = $pdo->prepare('SELECT game_picture, game_video_trailer FROM games WHERE game_ID = ?');
    $stmt->execute([$game_ID]);
    $game = $stmt->fetch();

    if ($game) {
        // Delete associated files
        if ($game['game_picture'] && file_exists('../uploads/images/' . $game['game_picture'])) {
            unlink('../uploads/images/' . $game['game_picture']);
        }
        if ($game['game_video_trailer'] && file_exists('../uploads/videos/' . $game['game_video_trailer'])) {
            unlink('../uploads/videos/' . $game['game_video_trailer']);
        }

        // Delete from database
        $stmt = $pdo->prepare('DELETE FROM games WHERE game_ID = ?');
        if ($stmt->execute([$game_ID])) {
            header('Location: inventory_management.php?success=game_deleted');
        } else {
            header('Location: inventory_management.php?error=delete_failed');
        }
    } else {
        header('Location: inventory_management.php?error=game_not_found');
    }
    exit();
} else {
    header('Location: inventory_management.php');
    exit();
}
?> 