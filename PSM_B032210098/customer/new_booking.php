<?php
session_start();
require_once '../db_connection.php';
require_once '../partials/header.php';
if (!isset($_SESSION['customer_ID'])) {
    header('Location: login.php');
    exit;
}

// Handle form submission
$success = false;
$error = '';
$should_create_booking = isset($_POST['submit_booking']);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $should_create_booking) {
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';
    $duration = $_POST['duration'] ?? 1;
    $number_of_players = $_POST['number_of_players'] ?? 1;
    $selected_games = $_POST['games'] ?? [];
    $customer_ID = $_SESSION['customer_ID'];
    $STAFF_ID = null; // Assigned by staff later or null for now

    // Check if customer account is active and verified
    $stmt = $pdo->prepare('SELECT status FROM customers WHERE customer_ID = ?');
    $stmt->execute([$customer_ID]);
    $customer_status = $stmt->fetchColumn();
    
    if ($customer_status !== 'active') {
        if ($customer_status === 'banned') {
            $error = 'Your account has been banned. Please contact support.';
        } elseif ($customer_status === 'pending_verification') {
            $error = 'Please verify your email address before making a booking.';
        } else {
            $error = 'Your account is not active. Please contact support.';
        }
    }

    // Validate input
    if (!$error && (!$date || !$time || empty($selected_games))) {
        $error = 'Please fill in all fields and select at least one game.';
    } elseif (!$error) {
        $booking_start_time = date('Y-m-d H:i:s', strtotime($date . ' ' . $time));
        $booking_end_time = date('Y-m-d H:i:s', strtotime($booking_start_time . ' +' . $duration . ' hours'));
        // Check for overlapping bookings for selected games
        $overlap = false;
        foreach ($selected_games as $game_ID) {
            $stmt = $pdo->prepare("
                SELECT 1 FROM rental_games rg
                JOIN rentals r ON rg.rental_ID = r.rental_ID
                WHERE rg.game_ID = ?
                  AND r.rental_status IN ('confirmed', 'in_progress')
                  AND ((? BETWEEN r.booking_start_time AND r.booking_end_time)
                       OR (? BETWEEN r.booking_start_time AND r.booking_end_time)
                       OR (r.booking_start_time BETWEEN ? AND ?))
                LIMIT 1
            ");
            $stmt->execute([
                $game_ID,
                $booking_start_time, $booking_end_time,
                $booking_start_time, $booking_end_time
            ]);
            if ($stmt->fetch()) {
                $overlap = true;
                break;
            }
        }
        if ($overlap) {
            $error = 'One or more selected games are already booked for the chosen time slot. Please choose a different time or game.';
        } else {
            // Find an available console for the requested time
            $stmt = $pdo->prepare("
                SELECT console_ID, hourly_rate FROM consoles
                WHERE consoles_status = 'available'
                AND console_ID NOT IN (
                    SELECT console_ID FROM rentals
                    WHERE booking_start_time < ? AND booking_end_time > ? AND rental_status IN ('confirmed','in_progress')
                )
                LIMIT 1
            ");
            $stmt->execute([$booking_end_time, $booking_start_time]);
            $console = $stmt->fetch();
            if (!$console) {
                $error = 'No available console for the selected time.';
            } else {
                $console_ID = $console['console_ID'];
                $total_amount = $console['hourly_rate'] * $duration * $number_of_players;
                // Generate rental_ID (improved to handle concurrent requests)
                $pdo->beginTransaction();
                try {
                    // Get the next available rental ID by checking existing ones
                    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(rental_ID, 5) AS UNSIGNED)) as max_id FROM rentals WHERE rental_ID LIKE 'RENT%'");
                    $max_id = $stmt->fetchColumn();
                    $next_id = ($max_id ? $max_id : 0) + 1;
                    $rental_ID = 'RENT' . str_pad($next_id, 4, '0', STR_PAD_LEFT);
                    
                    // Insert rental
                    $stmt = $pdo->prepare("INSERT INTO rentals (rental_ID, customer_ID, console_ID, staff_ID, number_of_players, booking_start_time, booking_end_time, total_amount, rental_status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending_payment', NOW())");
                    $stmt->execute([$rental_ID, $customer_ID, $console_ID, $STAFF_ID, $number_of_players, $booking_start_time, $booking_end_time, $total_amount]);
                    // Insert rental_games
                    $stmt = $pdo->prepare("INSERT INTO rental_games (rental_ID, game_ID) VALUES (?, ?)");
                    foreach ($selected_games as $game_ID) {
                        $stmt->execute([$rental_ID, $game_ID]);
                    }
                    
                    $pdo->commit();
                    header('Location: payment.php?rental_ID=' . urlencode($rental_ID));
                    exit;
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    if (strpos($e->getMessage(), 'Console is already booked') !== false) {
                        $error = "The selected console is already booked for that time. Please choose another slot.";
                    } else {
                        // Enhanced error reporting for debugging
                        $error = "Booking failed: " . $e->getMessage() . " (Error Code: " . $e->getCode() . ")";
                    }
                }
            }
        }
    }
}

// Fetch games (filtered by player count if set)
$games = [];
if (!empty($_POST['number_of_players'])) {
    $num_players = (int)$_POST['number_of_players'];
    $stmt = $pdo->prepare("SELECT * FROM games WHERE is_available = 1 AND min_players <= ? AND max_players >= ?");
    $stmt->execute([$num_players, $num_players]);
    $games = $stmt->fetchAll();
} else {
    $games = $pdo->query("SELECT * FROM games WHERE is_available = 1")->fetchAll();
}
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
<div class="container my-5">
    <ul class="breadcrumb">
      <li class="breadcrumb-item active">1. Booking Details</li>
      <li class="breadcrumb-item">2. Payment</li>
      <li class="breadcrumb-item">3. Confirmation</li>
    </ul>
    <div class="row justify-content-center">
        <div class="col-lg-10 col-xl-9">
            <div class="card shadow-lg border-0 rounded-4 p-4">
                <h2 class="mb-4 text-center fw-bold text-purple">New Booking</h2>
                <?php if ($success): ?>
                    <div class="alert alert-success text-center fs-5 fw-semibold"><?php echo htmlspecialchars($success); ?></div>
                    <div class="text-center mt-4">
                        <a href="home.php" class="btn btn-outline-purple btn-lg px-5 py-2 rounded-pill">Back to Home</a>
                    </div>
                <?php else: ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger text-center fs-6 fw-semibold"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <form method="post" class="mb-4">
                        <h5 class="mb-3 mt-2 text-purple">1. Date & Time</h5>
                        <div class="row mb-3">
                            <div class="col-md-4 mb-2">
                                <label for="date" class="form-label">Date</label>
                                <input type="date" class="form-control form-control-lg rounded-pill" id="date" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="time" class="form-label">Time</label>
                                <input type="time" class="form-control form-control-lg rounded-pill" id="time" name="time" value="<?php echo htmlspecialchars($_POST['time'] ?? ''); ?>" required min="08:00" max="20:00">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label for="duration" class="form-label">Duration (hours)</label>
                                <select class="form-select form-select-lg rounded-pill" id="duration" name="duration" required>
                                    <option value="1" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '1') ? 'selected' : ''; ?>>1 hour</option>
                                    <option value="2" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '2') ? 'selected' : ''; ?>>2 hours</option>
                                    <option value="3" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '3') ? 'selected' : ''; ?>>3 hours</option>
                                    <option value="4" <?php echo (isset($_POST['duration']) && $_POST['duration'] == '4') ? 'selected' : ''; ?>>4 hours</option>
                                </select>
                            </div>
                        </div>
                        <h5 class="mb-3 mt-4 text-purple">2. Players</h5>
                        <div class="row mb-3">
                            <div class="col-md-8 mb-2">
                                <label class="form-label">Number of Players</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php $selected_players = isset($_POST['number_of_players']) ? (int)$_POST['number_of_players'] : 1; ?>
                                    <?php for ($i = 1; $i <= 4; $i++): ?>
                                        <input type="radio" class="btn-check" name="number_of_players" id="players_<?php echo $i; ?>" value="<?php echo $i; ?>" autocomplete="off" <?php if ($selected_players == $i) echo 'checked'; ?> required>
                                        <label class="btn btn-outline-purple px-3 py-1<?php if ($selected_players == $i) echo ' active'; ?>" for="players_<?php echo $i; ?>">
                                            <?php echo $i; ?> Player<?php echo $i > 1 ? 's' : ''; ?>
                                        </label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <h5 class="mb-3 mt-4 text-purple">3. Select Game(s)</h5>
                        <div class="row g-3">
                            <?php foreach ($games as $game):
                                $imgPath = (!empty($game['game_picture']) && file_exists("../" . $game['game_picture']))
                                    ? "/ps4rentalsystem/" . $game['game_picture']
                                    : "/ps4rentalsystem/css/no-image.png";
                            ?>
                            <div class="col-md-4 col-sm-6">
                                <?php
                                    $checked = !empty($_POST['games']) && in_array($game['game_ID'], $_POST['games']);
                                ?>
                                <div class="card h-100 shadow-sm position-relative game-select-card<?php if ($checked) echo ' selected'; ?>">
                                    <input class="game-select-checkbox" type="checkbox" name="games[]" value="<?php echo $game['game_ID']; ?>" id="game_<?php echo $game['game_ID']; ?>" <?php if ($checked) echo 'checked'; ?> />
                                    <img src="<?php echo htmlspecialchars($imgPath); ?>" class="card-img-top rounded-top" style="height:160px;object-fit:cover;">
                                    <div class="card-body p-2">
                                        <label class="form-check-label fw-semibold" for="game_<?php echo $game['game_ID']; ?>">
                                            <?php echo htmlspecialchars($game['game_title']); ?>
                                        </label>
                                        <div class="small text-muted mt-1" style="min-height:2.5em;">
                                            <?php if (!empty($gameTags[$game['game_ID']])): ?>
                                                <?php foreach ($gameTags[$game['game_ID']] as $tag): ?>
                                                    <span class="badge bg-light text-dark me-1 mb-1"><?php echo htmlspecialchars($tag); ?></span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="text-center mt-4">
                            <button type="submit" name="submit_booking" class="btn btn-outline-purple btn-lg px-5 py-2 rounded-pill">Book Now</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
// Auto-submit form when number of players changes
const playerRadios = document.querySelectorAll('input[name="number_of_players"]');
playerRadios.forEach(radio => {
    radio.addEventListener('change', function() {
        this.form.submit();
    });
});
</script>
<?php require_once '../partials/footer.php'; ?> 