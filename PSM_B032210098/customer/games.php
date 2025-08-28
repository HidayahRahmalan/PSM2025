<?php
require_once '../db_connection.php';
require_once '../partials/header.php';

// Fetch all tags for filter dropdown
$allTags = $pdo->query("SELECT * FROM tags ORDER BY tag_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedTag = isset($_GET['tag']) ? trim($_GET['tag']) : '';

// Search/filter logic
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build base query and params
$query = "SELECT g.* FROM games g";
$params = [];
$where = [];

if ($selectedTag !== '') {
    $query .= " JOIN game_tags gt ON g.game_ID = gt.game_ID JOIN tags t ON gt.tag_ID = t.tag_ID";
    $where[] = "t.tag_ID = ?";
    $params[] = $selectedTag;
}
if ($search !== '') {
    $where[] = "g.game_title LIKE ?";
    $params[] = "%$search%";
}
if ($where) {
    $query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY g.game_title ASC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch tags for all games
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

<div class="container mt-4">
    <div class="text-center">
        <div class="featured-title">All Games</div>
        <div class="featured-desc mt-2 mb-4" style="color:#222; font-size:1.15rem; font-weight:500;">Browse our complete game library!</div>
    </div>
    <form class="mb-4" method="get" action="games.php">
        <div class="row g-2 align-items-center">
            <div class="col-md-6 mb-2 mb-md-0">
                <div class="input-group input-group-lg">
                    <input type="text" class="form-control" name="search" placeholder="Search by title..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>
            <div class="col-md-4 mb-2 mb-md-0">
                <select class="form-select form-select-lg" name="tag">
                    <option value="">All Tags</option>
                    <?php foreach ($allTags as $tag): ?>
                        <option value="<?php echo $tag['tag_ID']; ?>" <?php if ($selectedTag == $tag['tag_ID']) echo 'selected'; ?>><?php echo htmlspecialchars($tag['tag_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-purple btn-lg w-100" type="submit"><i class="fas fa-search"></i> Search</button>
            </div>
        </div>
    </form>
    <div class="steam-featured-row">
    <?php foreach ($games as $idx => $game):
        $imgPath = (!empty($game['game_picture']) && file_exists("../" . $game['game_picture']))
            ? "/ps4rentalsystem/" . $game['game_picture']
            : "/ps4rentalsystem/css/no-image.png";
        $videoPath = (!empty($game['game_video_trailer']) && file_exists("../" . $game['game_video_trailer']))
            ? "/ps4rentalsystem/" . $game['game_video_trailer']
            : "";
        $tags = isset($gameTags[$game['game_ID']]) ? $gameTags[$game['game_ID']] : [];
    ?>
        <div class="steam-featured-card" data-card-idx="<?php echo $idx; ?>" onclick="window.location.href='game_detail.php?game_ID=<?php echo $game['game_ID']; ?>'" style="cursor:pointer;">
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

<?php require_once '../partials/footer.php'; ?>
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