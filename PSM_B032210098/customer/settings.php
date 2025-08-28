<?php
session_start();
if (!isset($_SESSION['customer_ID'])) {
    header('Location: login.php');
    exit();
}

require_once '../db_connection.php';

$customer_ID = $_SESSION['customer_ID'];

// Get current customer data
$stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_ID = ?');
$stmt->execute([$customer_ID]);
$customer = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $customer_matric_no = $_POST['customer_matric_no'] ?? '';
    $profile_picture_path = $customer['customer_profile_picture'] ?? null;
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
        $profile_picture_path = 'uploads/profile_pictures/' . $customer_ID . '_' . time() . '.' . $ext;
        if (!is_dir('../uploads/profile_pictures')) {
            mkdir('../uploads/profile_pictures', 0777, true);
        }
        move_uploaded_file($_FILES['profile_picture']['tmp_name'], '../' . $profile_picture_path);
    }
    // Update customer profile
    $stmt = $pdo->prepare('UPDATE customers SET customer_full_name = ?, customer_email = ?, customer_phone = ?, customer_matric_no = ?, customer_profile_picture = ? WHERE customer_ID = ?');
    if ($stmt->execute([$full_name, $email, $phone, $customer_matric_no, $profile_picture_path, $customer_ID])) {
        $success_message = 'Profile updated successfully!';
        $_SESSION['customer_full_name'] = $full_name;
        // Refresh customer data
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE customer_ID = ?');
        $stmt->execute([$customer_ID]);
        $customer = $stmt->fetch();
    } else {
        $error_message = 'Failed to update profile.';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (password_verify($current_password, $customer['customer_password_hash'])) {
        if ($new_password === $confirm_password) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE customers SET customer_password_hash = ? WHERE customer_ID = ?');
            if ($stmt->execute([$new_hash, $customer_ID])) {
                $success_message = 'Password changed successfully!';
            } else {
                $error_message = 'Failed to change password.';
            }
        } else {
            $error_message = 'New passwords do not match.';
        }
    } else {
        $error_message = 'Current password is incorrect.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PS4 Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <h2><i class="fas fa-cog"></i> Account Settings</h2>
                
                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>
                
                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger"><?php echo $error_message; ?></div>
                <?php endif; ?>

                <!-- Profile Settings -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-user-edit"></i> Profile Settings</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($customer['customer_full_name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($customer['customer_email']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['customer_phone']); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="customer_matric_no" class="form-label">Matric Number</label>
                                <input type="text" class="form-control" id="customer_matric_no" name="customer_matric_no" required maxlength="10" value="<?php echo htmlspecialchars($customer['customer_matric_no'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="profile_picture" class="form-label">Profile Picture</label><br>
                                <?php if (!empty($customer['customer_profile_picture']) && file_exists('../' . $customer['customer_profile_picture'])): ?>
                                    <img src="<?php echo '../' . htmlspecialchars($customer['customer_profile_picture']); ?>" alt="Profile Picture" class="rounded-circle mb-2" style="width: 100px; height: 100px; object-fit: cover;">
                                <?php else: ?>
                                    <img src="../css/no-image.png" alt="No Profile Picture" class="rounded-circle mb-2" style="width: 100px; height: 100px; object-fit: cover;">
                                <?php endif; ?>
                                <input type="file" class="form-control mt-2" id="profile_picture" name="profile_picture" accept="image/*">
                                <div class="form-text">Upload a new profile picture (optional)</div>
                            </div>
                            
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Profile
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Password Change -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-key"></i> Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 