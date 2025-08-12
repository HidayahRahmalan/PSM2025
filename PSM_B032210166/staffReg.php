<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f8ff;
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 30px 0;
        }

        .back-arrow {
            align-self: flex-start;
            font-size: 22px;
            margin-left: 20px;
            cursor: pointer;
            text-decoration: none;
            color: #25408f;
            font-weight: bold;
        }

        .title {
            color: #25408f;
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 20px;
            text-align: center;
        }

        .container {
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 40px;
            width: 40%;
            text-align: left;
        }

        label {
            display: block;
            color: black;
            font-size: 16px;
            margin-top: 10px;
        }
        
        .required {
            color: red;
            margin-left: 3px;
        }

        input {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 15px;
        }

        .password-container {
            position: relative;
            width: 100%;
            margin-bottom: 20px;
        }

        .password-container input {
            padding-right: 40px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            font-size: 18px;
        }

        .gender-container, .role-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin: 5px 0 25px 0;
        }

        .gender-container label, .role-container label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 16px;
            white-space: nowrap;
        }

        button {
            width: 100%;
            padding: 12px;
            background-color: #25408f;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 15px;
            font-size: 16px;
        }

        button:hover {
            background-color: #3883ce;
        }
        
        .error-message {
            color: red;
            font-size: 14px;
            margin-top: -10px;
            margin-bottom: 10px;
        }
        
        .success-message {
            color: green;
            font-size: 16px;
            text-align: center;
            margin-top: 15px;
            font-weight: bold;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 15% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 300px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .modal-title {
            margin-top: 0;
            color: #25408f;
        }
        
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-button {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .modal-button.primary {
            background-color: #25408f;
            color: white;
        }
        
        .modal-button.secondary {
            background-color: #ccc;
            color: black;
        }
    </style>
</head>
<body>
    <a href="staffMainPage.php" class="back-arrow">&#8592; Back</a>
    <div class="title">Staff Registration</div>

    <div class="container">
        <form id="registrationForm" method="post">
            <label for="fullname">Full Name:<span class="required">*</span></label>
            <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required autocomplete="off">
            <div id="fullname-error" class="error-message"></div>

            <label for="email">Staff Email:<span class="required">*</span></label>
            <input type="email" id="email" name="email" placeholder="Eg: example@utem.edu.my" required pattern="[a-zA-Z0-9._%+-]+@utem\.edu\.my" autocomplete="off">
            <div id="email-error" class="error-message"></div>

            <label for="personalEmail">Personal Email:<span class="required">*</span></label>
            <input type="email" id="personalEmail" name="personalEmail" placeholder="Eg: example@gmail.com" required autocomplete="off">
            <div id="personalEmail-error" class="error-message"></div>

            <label for="phone">Phone Number: (Eg: XXX-XXXXXXXX)<span class="required">*</span></label>
            <input type="tel" id="phone" name="phone" placeholder="Eg: 012-34567890" required pattern="[0-9]{3}-[0-9]{7,8}" maxlength="12" autocomplete="off">
            <div id="phone-error" class="error-message"></div>      

            <label>Gender:<span class="required">*</span></label>
            <div class="gender-container">
                <label><input type="radio" name="gender" value="M" required> Male</label>
                <label><input type="radio" name="gender" value="F" required> Female</label>
            </div>

            <label>Role:<span class="required">*</span></label>
            <div class="role-container">
                <label><input type="radio" name="role" value="HOSTEL STAFF" required checked> Hostel Staff</label>
                <label><input type="radio" name="role" value="ADMIN" id="adminRole" required> Admin</label>
            </div>
            <div id="role-error" class="error-message"></div>

            <label for="password">Password:<span class="required">*</span></label>
            <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Enter your password" required minlength="8" maxlength="16">
                <span class="toggle-password" onclick="togglePassword()">👁️</span>
            </div>
            <div id="password-error" class="error-message"></div>

            <button type="button" id="registerBtn">Register</button>
            <div id="result-message" class="success-message"></div>
        </form>
    </div>
    
    <!-- Admin Key Modal -->
    <div id="adminKeyModal" class="modal">
        <div class="modal-content">
            <h3 class="modal-title">Admin Enrollment</h3>
            <p>Please enter the admin enrollment key:</p>
            <input type="password" id="adminKey" placeholder="Enter admin key">
            <div id="adminKey-error" class="error-message"></div>
            <div class="modal-buttons">
                <button class="modal-button secondary" onclick="cancelAdminRole()">Cancel</button>
                <button class="modal-button primary" onclick="verifyAdminKey()">Verify</button>
            </div>
        </div>
    </div>

    <script>
        // Admin enrollment key - in a real application, this should be verified server-side
        const ADMIN_KEY = "AdminUTeM1"; 
        
        // Get the modal
        const modal = document.getElementById("adminKeyModal");
        
        // Get the admin role radio button
        const adminRole = document.getElementById("adminRole");
        
        // Add event listener to the admin role radio button
        // change: means when the admin role is selected, the admin key modal will be shown
        adminRole.addEventListener("change", function() {
            // this: refers to the admin role radio button that is selected
            if (this.checked) {
                // Show the admin key modal
                modal.style.display = "block";
            }
        });
        
        // Function to verify the admin key
        function verifyAdminKey() {
            const adminKey = document.getElementById("adminKey").value;
            const adminKeyError = document.getElementById("adminKey-error");
            
            if (adminKey === ADMIN_KEY) {
                // Key is correct, close the modal and keep admin role selected
                modal.style.display = "none";
                adminKeyError.textContent = "";
            } else {
                // Key is incorrect, show error message
                adminKeyError.textContent = "Invalid admin key. Please try again.";
            }
        }
        
        // Function to cancel admin role selection
        function cancelAdminRole() {
            // Close the modal and select Hostel Staff role
            modal.style.display = "none";
            document.querySelector('input[name="role"][value="HOSTEL STAFF"]').checked = true;
            document.getElementById("adminKey-error").textContent = "";
            document.getElementById("adminKey").value = "";
        }
        
        // Close the modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target == modal) {
                cancelAdminRole();
            }
        }
        
        function togglePassword() {
            let passwordField = document.getElementById("password");
            if (passwordField.type === "password") {
                passwordField.type = "text";
            } else {
                passwordField.type = "password";
            }
        }
        
        // Password validation function - same as student registration
        function validatePassword(password) {
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.+-/_#])[A-Za-z\d@$!%*?&.+-/_#]{8,}$/;
            return passwordRegex.test(password);
        }
        
        // Email validation function for UTeM domain - make it case-insensitive
        function validateEmail(email) {
            const emailRegex = /^[a-zA-Z0-9._%+-]+@utem\.edu\.my$/i; // Added 'i' flag for case insensitive
            return emailRegex.test(email);
        }
        
        document.getElementById('registerBtn').addEventListener('click', function() {
            // Clear previous error messages
            const errorElements = document.querySelectorAll('.error-message');
            errorElements.forEach(element => {
                element.textContent = '';
            });
            
            // Validate Email
            const email = document.getElementById('email').value;
            const emailError = document.getElementById('email-error');
            
            if (!validateEmail(email)) {
                emailError.textContent = "Email must be in the format username@utem.edu.my";
                return; // Stop execution if email is invalid
            }

            // Validate Password
            const password = document.getElementById('password').value;
            const passwordError = document.getElementById('password-error');

            if (!validatePassword(password)) {
                passwordError.textContent = "Password must at least 8 characters, contain at least one lowercase, one uppercase, one special character(@$!%*?&.+-/_#), and one number.";
                return; // Stop execution if password is invalid
            }

            // Check if form is valid
            const form = document.getElementById('registrationForm');
            // checkValidity(): checks if the form is valid
            if (!form.checkValidity()) {
                // reportValidity(): shows the error message
                form.reportValidity();
                return;
            }
            
            // Check if admin role is selected but admin key wasn't verified
            if (adminRole.checked && document.getElementById("adminKey").value !== ADMIN_KEY) {
                document.getElementById("role-error").textContent = "Admin role requires valid enrollment key.";
                modal.style.display = "block";
                return;
            }
            
            // Get form data
            const formData = new FormData(form);
            
            // Check for uniqueness via AJAX
            fetch('staffCheckUniq.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Uniqueness check response:', data); // Debug logging
                
                // Clear previous error messages first
                document.getElementById('email-error').textContent = '';
                document.getElementById('personalEmail-error').textContent = '';
                document.getElementById('phone-error').textContent = '';
                
                if (data.errors) {
                    // Display error messages
                    if (data.errors.email) {
                        document.getElementById('email-error').textContent = data.errors.email;
                    }
                    if (data.errors.personalEmail) {
                        document.getElementById('personalEmail-error').textContent = data.errors.personalEmail;
                    }
                    if (data.errors.phone) {
                        document.getElementById('phone-error').textContent = data.errors.phone;
                    }
                    return; // Stop execution if there are errors
                } else {
                    // All validations passed, show confirmation dialog
                    showConfirmationDialog();
                }
            })
            .catch(error => {
                console.error('Error during uniqueness check:', error);
            });
        });
        
        function showConfirmationDialog() {
            // Get all form values
            const fullname = document.getElementById('fullname').value.toUpperCase();
            const email = document.getElementById('email').value.toUpperCase();
            const personalEmail = document.getElementById('personalEmail').value.toUpperCase();
            const phone = document.getElementById('phone').value;
            const gender = document.querySelector('input[name="gender"]:checked').value;
            const role = document.querySelector('input[name="role"]:checked').value;
            const password = document.getElementById('password').value;
            
            // Create confirmation message
            let confirmMessage = "Please confirm your registration details:\n\n";
            confirmMessage += "Full Name: " + fullname + "\n";
            confirmMessage += "Email: " + email + "\n";
            confirmMessage += "Personal Email: " + personalEmail + "\n";
            confirmMessage += "Phone Number: " + phone + "\n";
            confirmMessage += "Gender: " + (gender === 'M' ? 'Male' : 'Female') + "\n";
            confirmMessage += "Role: " + (role === 'ADMIN' ? 'Admin' : 'Hostel Staff') + "\n";
            confirmMessage += "Password: " + password + "\n";
            
            // Show confirmation dialog
            if (confirm(confirmMessage)) {
                // User clicked OK, submit the form
                submitRegistration();
            }
        }
        
        function submitRegistration() {
            const form = document.getElementById('registrationForm');
            const formData = new FormData(form);
            
            fetch('staffProcessReg.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Registration response:', data); // Add debugging
                if (data.success) {
                    document.getElementById('result-message').textContent = "Registration successful! Your Employee ID is: " + data.empId;
                    document.getElementById('result-message').style.color = "green";
                    
                    setTimeout(() => {
                        window.location.href = "staffMainPage.php"; // Redirect after 1.5 seconds
                    }, 1500);
                } else {
                    document.getElementById('result-message').textContent = "Registration failed: " + data.message;
                    document.getElementById('result-message').style.color = "red";
                }
            })
            .catch(error => {
                console.error('Error during registration:', error);
                document.getElementById('result-message').textContent = "An error occurred during registration.";
                document.getElementById('result-message').style.color = "red";
            });
        }
    </script>
</body>
</html>
