<?php
require_once 'db_connection.php';
require_once 'partials/header.php';

// Fetch 3 featured games
$stmt = $pdo->prepare('SELECT game_ID, game_title, game_picture, game_video_trailer, game_description FROM games WHERE is_available = 1 LIMIT 3');
$stmt->execute();
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Fetch tags for all featured games
$gameTags = [];
if (!empty($games)) {
    $gameIDs = array_column($games, 'game_ID');
    $in  = str_repeat('?,', count($gameIDs) - 1) . '?';
    $stmt = $pdo->prepare("SELECT gt.game_ID, t.tag_name FROM game_tags gt JOIN tags t ON gt.tag_ID = t.tag_ID WHERE gt.game_ID IN ($in)");
    $stmt->execute($gameIDs);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $gameTags[$row['game_ID']][] = $row['tag_name'];
    }
}
?>
<section class="hero-section text-white d-flex flex-column justify-content-center align-items-center">
    <h1 class="display-3 fw-bold mb-3">Welcome to PS4 Rental System</h1>
    <p class="lead mb-4">Experience the ultimate gaming with our premium PS4 consoles and extensive game library.<br>
    Book your session and play the latest games today!</p>
    <a href="customer/login.php" class="btn btn-outline-purple btn-lg px-5 py-3 mb-4"><i class="fas fa-gamepad"></i> Booking Now!</a>
</section>

<div class="container mt-5">
    <div class="text-center">
        <div class="featured-title">Featured Games</div>
        <div class="featured-desc mt-2 mb-4" style="color:#222; font-size:1.15rem; font-weight:500;">Check out our top picks for this month!</div>
    </div>
    <div class="steam-featured-row">
        <?php foreach (
            $games as $idx => $game):
            $imgPath = (!empty($game['game_picture']) && file_exists(__DIR__ . "/" . $game['game_picture']))
                ? $game['game_picture']
                : "css/no-image.png";
            $videoPath = (!empty($game['game_video_trailer']) && file_exists(__DIR__ . "/" . $game['game_video_trailer']))
                ? $game['game_video_trailer']
                : "";
            $tags = isset($gameTags[$game['game_ID']]) ? $gameTags[$game['game_ID']] : [];
        ?>
        <div class="steam-featured-card" data-card-idx="<?php echo $idx; ?>" onclick="window.location.href='customer/game_detail.php?game_ID=<?php echo $game['game_ID']; ?>'" style="cursor:pointer;">
            <img src="<?php echo htmlspecialchars($imgPath); ?>" alt="<?php echo htmlspecialchars($game['game_title']); ?>" class="steam-featured-img">
            <div class="steam-featured-overlay">
                <?php if ($videoPath): ?>
                <video class="steam-featured-overlay-video" muted loop preload="none" poster="<?php echo htmlspecialchars($imgPath); ?>">
                    <source src="<?php echo htmlspecialchars($videoPath); ?>" type="video/mp4">
                </video>
                <?php endif; ?>
                <div class="steam-featured-details">
                    <div class="steam-featured-title"><?php echo htmlspecialchars($game['game_title']); ?></div>
                    <div class="steam-featured-tags">
                        <?php foreach ($tags as $tag): ?>
                            <span class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="steam-featured-desc"><?php echo htmlspecialchars($game['game_description']); ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>
<script>
// Only one overlay active at a time, play/pause video
    document.querySelectorAll('.steam-featured-card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            document.querySelectorAll('.steam-featured-card').forEach(c => {
                if (c !== card) {
                    c.classList.remove('active');
                    const v = c.querySelector('.steam-featured-overlay-video');
                    if (v) { v.pause(); v.currentTime = 0; }
                }
            });
            card.classList.add('active');
            const video = card.querySelector('.steam-featured-overlay-video');
            if (video) { video.currentTime = 0; video.play(); }
        });
        card.addEventListener('mouseleave', function() {
            card.classList.remove('active');
            const video = card.querySelector('.steam-featured-overlay-video');
            if (video) { video.pause(); video.currentTime = 0; }
        });
    });
</script> 