<?php
require_once '../db_connection.php';
session_start();
require_once '../partials/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating_stars'])) {
    if (!isset($_SESSION['customer_ID'])) {
        $error = "You must be logged in to submit a review.";
    } else {
        $customer_ID = $_SESSION['customer_ID'];
        $game_ID = $_POST['game_ID'] ?? '';
        $rating_stars = intval($_POST['rating_stars'] ?? 0);
        $review_comment = trim($_POST['review_comment'] ?? '');

        if (!$game_ID || $rating_stars < 1 || $rating_stars > 5) {
            $error = "Invalid input.";
        } else {
            // Prevent duplicate review
            $stmt = $pdo->prepare('SELECT rating_ID FROM ratings WHERE customer_ID = ? AND game_ID = ?');
            $stmt->execute([$customer_ID, $game_ID]);
            $existing = $stmt->fetch();

            if ($existing) {
                // Update existing review
                $stmt = $pdo->prepare('UPDATE ratings SET rating_stars = ?, review_comment = ?, created_at = NOW() WHERE rating_ID = ?');
                $stmt->execute([$rating_stars, $review_comment, $existing['rating_ID']]);
                $success = "Your review has been updated!";
            } else {
                // Insert new review
                $rating_ID = uniqid('RAT');
                $stmt = $pdo->prepare('INSERT INTO ratings (rating_ID, rating_stars, review_comment, customer_ID, game_ID) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$rating_ID, $rating_stars, $review_comment, $customer_ID, $game_ID]);
                $success = "Thank you for your review!";
            }
        }
    }
}

$game_ID = isset($_GET['game_ID']) ? $_GET['game_ID'] : '';
if (!$game_ID) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Invalid game selected.</div></div>';
    require_once '../partials/footer.php';
    exit;
}
$stmt = $pdo->prepare('SELECT * FROM games WHERE game_ID = ?');
$stmt->execute([$game_ID]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$game) {
    echo '<div class="container mt-5"><div class="alert alert-danger">Game not found.</div></div>';
    require_once '../partials/footer.php';
    exit;
}
$imgPath = (!empty($game['game_picture']) && file_exists("../" . $game['game_picture']))
    ? "/ps4rentalsystem/" . $game['game_picture']
    : "/ps4rentalsystem/css/no-image.png";
$videoPath = (!empty($game['game_video_trailer']) && file_exists("../" . $game['game_video_trailer']))
    ? "/ps4rentalsystem/" . $game['game_video_trailer']
    : "";
// Fetch tags for this game
$stmt = $pdo->prepare('SELECT t.tag_name FROM game_tags gt JOIN tags t ON gt.tag_ID = t.tag_ID WHERE gt.game_ID = ?');
$stmt->execute([$game_ID]);
$tags = $stmt->fetchAll(PDO::FETCH_COLUMN);
// Fetch ratings for this game
$stmt = $pdo->prepare('SELECT AVG(rating_stars) as avg_rating, COUNT(*) as total FROM ratings WHERE game_ID = ?');
$stmt->execute([$game_ID]);
$ratingStats = $stmt->fetch(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare('SELECT r.rating_stars, r.review_comment, c.customer_full_name, r.created_at FROM ratings r JOIN customers c ON r.customer_ID = c.customer_ID WHERE r.game_ID = ? ORDER BY r.created_at DESC LIMIT 5');
$stmt->execute([$game_ID]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container mt-5 mb-5">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="bg-dark rounded shadow p-3 mb-3 text-center">
                <?php if ($videoPath): ?>
                    <video class="w-100 rounded" style="max-height:400px;object-fit:cover;background:#000" controls poster="<?php echo htmlspecialchars($imgPath); ?>">
                        <source src="<?php echo htmlspecialchars($videoPath); ?>" type="video/mp4">
                        Your browser does not support the video tag.
                    </video>
                <?php else: ?>
                    <img src="<?php echo htmlspecialchars($imgPath); ?>" class="w-100 rounded" style="max-height:400px;object-fit:cover;background:#222" alt="<?php echo htmlspecialchars($game['game_title']); ?>">
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="bg-purple text-white rounded shadow p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <h2 class="fw-bold mb-2"><?php echo htmlspecialchars($game['game_title']); ?></h2>
                    <div class="mb-2">
                        <?php foreach ($tags as $tag): ?>
                            <span class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($tag); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <div class="mb-3"><span class="badge bg-light text-dark me-2">Players</span> <?php echo htmlspecialchars($game['min_players']); ?> - <?php echo htmlspecialchars($game['max_players']); ?></div>
                    <div class="mb-3 steam-featured-desc"><?php echo nl2br(htmlspecialchars($game['game_description'])); ?></div>
                </div>
                <a href="games.php" class="btn btn-outline-purple mt-3"><i class="fas fa-arrow-left"></i> Back to Games</a>
            </div>
        </div>
    </div>
    <!-- Ratings Section -->
    <div class="row mt-4">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-purple text-white">
                    <h5 class="mb-0"><i class="fas fa-star"></i> Ratings & Reviews</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <strong>Average Rating:</strong> <?php echo $ratingStats['avg_rating'] ? number_format($ratingStats['avg_rating'], 1) : 'N/A'; ?> / 5
                        (<?php echo $ratingStats['total']; ?> reviews)
                    </div>
                    <?php if ($reviews): ?>
                        <?php foreach ($reviews as $review): ?>
                            <div class="mb-2 p-2 border rounded bg-light">
                                <strong><?php echo htmlspecialchars($review['customer_full_name']); ?></strong>
                                <span class="text-warning">
                                    <?php for ($i = 0; $i < $review['rating_stars']; $i++) echo '★'; ?><?php for ($i = $review['rating_stars']; $i < 5; $i++) echo '☆'; ?>
                                </span>
                                <small class="text-muted ms-2"><?php echo date('M d, Y', strtotime($review['created_at'])); ?></small>
                                <div><?php echo nl2br(htmlspecialchars($review['review_comment'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted">No reviews yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header bg-purple text-white">
                    <h5 class="mb-0"><i class="fas fa-pen"></i> Submit Your Review</h5>
                </div>
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php elseif ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <form method="post">
                        <input type="hidden" name="game_ID" value="<?php echo htmlspecialchars($game_ID); ?>">
                        <div class="mb-3">
                            <label for="rating_stars" class="form-label">Rating</label>
                            <select class="form-select" id="rating_stars" name="rating_stars" required>
                                <option value="">Select rating</option>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="review_comment" class="form-label">Comment (optional)</label>
                            <textarea class="form-control" id="review_comment" name="review_comment" rows="3"></textarea>
                        </div>
                        <button type="submit" class="btn btn-purple">Submit Review</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once '../partials/footer.php'; ?> 