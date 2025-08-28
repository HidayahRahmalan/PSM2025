<?php require_once '../partials/header.php'; ?>

<h2 class="mb-4 text-center">Customer Registration</h2>
<?php if (isset($_SESSION['registration_success'])): ?>
    <div class="alert alert-success text-center"><?php echo $_SESSION['registration_success']; unset($_SESSION['registration_success']); ?></div>
<?php endif; ?>
<form action="register_process.php" method="post" class="mx-auto" style="max-width: 500px;" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); ?>">
    <div class="mb-3">
        <label for="full_name" class="form-label">Full Name</label>
        <input type="text" class="form-control" id="full_name" name="full_name" required>
    </div>
    <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="username" required>
        <div id="username-availability" class="form-text"></div>
    </div>
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" required>
        <div id="email-availability" class="form-text"></div>
    </div>
    <div class="mb-3">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" class="form-control" id="phone" name="phone" pattern="^[0-9+\-\s]{7,20}$" required oninput="validatePhone(this)">
        <div class="invalid-feedback">Phone number must be 7-20 characters and can only contain digits, +, - and spaces.</div>
    </div>
    <div class="mb-3">
        <label for="customer_matric_no" class="form-label">Matric Number</label>
        <input type="text" class="form-control" id="customer_matric_no" name="customer_matric_no" required maxlength="10" value="<?php echo isset($_POST['customer_matric_no']) ? htmlspecialchars($_POST['customer_matric_no']) : ''; ?>">
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
        Already have an account? <a href="login.php">Login here</a>.
    </div>
</form>

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

function validatePhone(input) {
    const pattern = /^[0-9+\-\s]{7,20}$/;
    if (!pattern.test(input.value)) {
        input.setCustomValidity('Phone number must be 7-20 characters and can only contain digits, +, - and spaces.');
    } else {
        input.setCustomValidity('');
    }
}
</script>

<?php require_once '../partials/footer.php'; ?> 