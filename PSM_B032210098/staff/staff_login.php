<?php require_once '../partials/header.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow-lg border-0 rounded-4">
                <div class="card-header bg-purple text-white text-center py-3">
                    <h3 class="mb-0"><i class="fas fa-user-tie"></i> Staff/Admin Login</h3>
                </div>
                <div class="card-body p-4">
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php 
                                $error_msg = '';
                                switch($_GET['error']) {
                                    case 'empty_fields':
                                        $error_msg = 'Please fill in all fields.';
                                        break;
                                    case 'invalid_credentials':
                                        $error_msg = 'Invalid username/email or password.';
                                        break;
                                    case 'account_banned':
                                        $error_msg = 'Your account has been banned. Please contact admin.';
                                        break;
                                    case 'locked':
                                        $error_msg = 'Too many failed login attempts. Please try again later.';
                                        break;
                                    case 'csrf':
                                        $error_msg = 'Invalid session. Please try again.';
                                        break;
                                    default:
                                        $error_msg = 'Login failed. Please try again.';
                                }
                                echo htmlspecialchars($error_msg);
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['msg'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['msg']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="staff_login_process.php" method="post" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>">
                        <div class="mb-3">
                            <label for="login" class="form-label">Username or Email</label>
                            <input type="text" class="form-control" id="login" name="login" required>
                            <div class="invalid-feedback">Please enter your username or email.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password" required>
                                <button type="button" class="btn btn-outline-secondary" tabindex="-1" onclick="togglePassword()">
                                    <i class="fas fa-eye" id="togglePasswordIcon"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Please enter your password.</div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me">
                            <label class="form-check-label" for="remember_me">Remember Me</label>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-purple btn-lg">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </button>
                            <a href="staff_register.php" class="btn btn-outline-purple btn-lg">
                                <i class="fas fa-user-plus"></i> Register New Staff
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

function togglePassword() {
    var pwd = document.getElementById('password');
    var icon = document.getElementById('togglePasswordIcon');
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

<?php require_once '../partials/footer.php'; ?> 