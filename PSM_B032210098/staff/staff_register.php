<?php
session_start();
require_once '../db_connection.php';

// Redirect if already logged in
if (isset($_SESSION['staff_ID'])) {
    header('Location: dashboard.php');
    exit();
}

// Get any errors or success messages from the registration process
$errors = $_SESSION['registration_errors'] ?? [];
$success_message = $_SESSION['registration_success'] ?? '';
$form_data = $_SESSION['registration_data'] ?? [];

// Clear session variables after using them
unset($_SESSION['registration_errors'], $_SESSION['registration_success'], $_SESSION['registration_data']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration - PS4 Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../partials/header.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-lg border-0 rounded-4">
                    <div class="card-header bg-purple text-white text-center py-3">
                        <h3 class="mb-0"><i class="fas fa-user-plus"></i> Staff Registration</h3>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php if (count($errors) == 1): ?>
                                    <?php echo htmlspecialchars($errors[0]); ?>
                                <?php else: ?>
                                    <ul class="mb-0">
                                        <?php foreach ($errors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <div class="text-center mt-3">
                                <a href="staff_login.php" class="btn btn-outline-purple btn-lg px-4">
                                    <i class="fas fa-sign-in-alt"></i> Go to Login
                                </a>
                            </div>
                        <?php endif; ?>

                        <form action="staff_register_process.php" method="post" class="mx-auto" style="max-width: 500px;" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>" required>
                                <div id="username-availability" class="form-text"></div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" required>
                                <div id="email-availability" class="form-text"></div>
                            </div>
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($form_data['full_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($form_data['phone'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label for="profile_picture" class="form-label">Profile Picture (optional)</label>
                                <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <button type="button" class="btn btn-outline-secondary" tabindex="-1" onclick="togglePassword('password', 'togglePasswordIcon')">
                                        <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                    </button>
                                </div>
                                <div class="mb-1">
                                    <div id="password-strength-text" class="form-text"></div>
                                    <div class="progress" style="height: 5px;">
                                        <div id="password-strength-bar" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <button type="button" class="btn btn-outline-secondary" tabindex="-1" onclick="togglePassword('confirm_password', 'toggleConfirmPasswordIcon')">
                                        <i class="fas fa-eye" id="toggleConfirmPasswordIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" target="_blank">Terms and Conditions</a> and <a href="#" target="_blank">Privacy Policy</a>.
                                </label>
                                <div class="invalid-feedback">You must agree before registering.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Register</button>
                            <div class="mt-3 text-center">
                                Already have an account? <a href="staff_login.php">Login here</a>.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include '../partials/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^A-Za-z0-9]/)) strength++;
            return strength;
        }
        document.getElementById('password').addEventListener('input', function() {
            const pwd = this.value;
            const strength = checkPasswordStrength(pwd);
            const bar = document.getElementById('password-strength-bar');
            const text = document.getElementById('password-strength-text');
            let percent = (strength / 5) * 100;
            let color = 'bg-danger';
            let msg = 'Very Weak';
            if (strength >= 5) {
                color = 'bg-success';
                msg = 'Very Strong';
            } else if (strength >= 4) {
                color = 'bg-info';
                msg = 'Strong';
            } else if (strength >= 3) {
                color = 'bg-warning';
                msg = 'Medium';
            } else if (strength >= 2) {
                color = 'bg-danger';
                msg = 'Weak';
            }
            bar.style.width = percent + '%';
            bar.className = 'progress-bar ' + color;
            text.textContent = msg;
        });
        function checkAvailability(field, value) {
            if (!value) {
                document.getElementById(field + '-availability').textContent = '';
                return;
            }
            fetch('check_availability.php?' + field + '=' + encodeURIComponent(value))
                .then(response => response.json())
                .then(data => {
                    const el = document.getElementById(field + '-availability');
                    el.textContent = data.message;
                    el.style.color = data.available ? 'green' : 'red';
                });
        }
        document.getElementById('username').addEventListener('input', function() {
            checkAvailability('username', this.value);
        });
        document.getElementById('email').addEventListener('input', function() {
            checkAvailability('email', this.value);
        });
        function togglePassword(inputId, iconId) {
            var pwd = document.getElementById(inputId);
            var icon = document.getElementById(iconId);
            if (pwd.type === 'password') {
                pwd.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                pwd.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html> 