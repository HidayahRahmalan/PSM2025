<?php
session_start();
require_once '../db_connection.php';
require_once '../partials/header.php';
if (!isset($_SESSION['staff_ID'])) {
    header('Location: staff_login.php');
    exit;
}

$staff_ID = $_SESSION['staff_ID'];

// Fetch staff info
$stmt = $pdo->prepare('SELECT * FROM staffs WHERE staff_ID = ?');
$stmt->execute([$staff_ID]);
$staff = $stmt->fetch();

$profile_success = $profile_error = $password_success = $password_error = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    
    // Handle profile picture upload
    $profile_picture = $staff['staff_profile_picture'];
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (in_array($file['type'], $allowed_types)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'staff_' . $staff_ID . '_' . time() . '.' . $ext;
            $upload_path = '../uploads/images/' . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $profile_picture = $filename;
            } else {
                $profile_error = 'Failed to upload profile picture.';
            }
        } else {
            $profile_error = 'Invalid file type. Only JPEG, PNG, and GIF images are allowed.';
        }
    }

    if (!$profile_error) {
        $stmt = $pdo->prepare('UPDATE staffs SET staff_full_name = ?, staff_email = ?, staff_profile_picture = ? WHERE staff_ID = ?');
        if ($stmt->execute([$full_name, $email, $profile_picture, $staff_ID])) {
            $profile_success = 'Profile updated successfully.';
            // Refresh staff info
            $stmt = $pdo->prepare('SELECT * FROM staffs WHERE staff_ID = ?');
            $stmt->execute([$staff_ID]);
            $staff = $stmt->fetch();
        } else {
            $profile_error = 'Failed to update profile.';
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (password_verify($current_password, $staff['staff_password_hash'])) {
        if ($new_password === $confirm_password) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE staffs SET staff_password_hash = ? WHERE staff_ID = ?');
            if ($stmt->execute([$new_hash, $staff_ID])) {
                $password_success = 'Password updated successfully.';
            } else {
                $password_error = 'Failed to update password.';
            }
        } else {
            $password_error = 'New passwords do not match.';
        }
    } else {
        $password_error = 'Current password is incorrect.';
    }
}
?>
<h2 class="mb-4">Staff Settings</h2>

<?php if ($profile_success): ?><div class="alert alert-success"><?php echo $profile_success; ?></div><?php endif; ?>
<?php if ($profile_error): ?><div class="alert alert-danger"><?php echo $profile_error; ?></div><?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <form method="post" enctype="multipart/form-data" class="card p-4 mb-4">
            <h4>Profile Information</h4>
            <div class="mb-3 text-center">
                <?php if ($staff['staff_profile_picture']): ?>
                    <img src="/ps4rentalsystem/uploads/images/<?php echo htmlspecialchars($staff['staff_profile_picture']); ?>" alt="Profile Picture" class="rounded-circle" width="100" height="100">
                <?php else: ?>
                    <img src="https://via.placeholder.com/100x100?text=No+Image" class="rounded-circle" alt="No Image">
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($staff['staff_username']); ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($staff['staff_full_name']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($staff['staff_email']); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Profile Picture</label>
                <input type="file" class="form-control" name="profile_picture" accept="image/*">
            </div>
            <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
        </form>
    </div>
    <div class="col-md-6">
        <?php if ($password_success): ?><div class="alert alert-success"><?php echo $password_success; ?></div><?php endif; ?>
        <?php if ($password_error): ?><div class="alert alert-danger"><?php echo $password_error; ?></div><?php endif; ?>
        <form method="post" class="card p-4">
            <h4>Change Password</h4>
            <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" class="form-control" name="current_password" required>
            </div>
            <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" class="form-control" name="new_password" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Confirm New Password</label>
                <input type="password" class="form-control" name="confirm_password" required>
            </div>
            <button type="submit" name="change_password" class="btn btn-warning">Change Password</button>
        </form>
    </div>
</div>
<?php require_once '../partials/footer.php'; ?> 