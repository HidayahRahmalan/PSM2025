<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
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

        input, select {
            width: 100%;
            padding: 10px;
            margin: 5px 0 15px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 15px;
        }
        
        input[readonly] {
            background-color: #f0f0f0;
            color: #666;
        }

        .password-container {
            position: relative;
            width: 100%;
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

        .gender-container {
            display: flex;
            align-items: center;
            gap: 20px;
            margin: 5px 0 15px 0;
        }

        .gender-container label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 16px;
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
    </style>
</head>
<body>
    <a href="studMainPage.php" class="back-arrow">&#8592; Back</a>
    <div class="title">Student Registration</div>

    <div class="container">
        <form id="registrationForm" method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>">
            <label for="fullname">Full Name:<span class="required">*</span></label>
            <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required autocomplete="off">
            <div id="fullname-error" class="error-message"></div>

            <label for="email">Student Email:<span class="required">*</span></label>
            <input type="email" id="email" name="email" placeholder="Eg: example@student.utem.edu.my" required maxlength="30" pattern="[BbDd][0-9]{9}@[Ss][Tt][Uu][Dd][Ee][Nn][Tt]\.[Uu][Tt][Ee][Mm]\.[Ee][Dd][Uu]\.[Mm][Yy]" oninput="extractMatricNo()" autocomplete="off">
            <div id="email-error" class="error-message" ></div>

            <label for="personalEmail">Personal Email:<span class="required">*</span></label>
            <input type="email" id="personalEmail" name="personalEmail" placeholder="Eg:example@gmail.com" required autocomplete="off">
            <div id="personalEmail-error" class="error-message"></div>

            <label for="phone">Phone Number: (Eg: XXX-XXXXXXXX)<span class="required">*</span></label>
            <input type="tel" id="phone" name="phone" placeholder="Eg: 012-34567890" required pattern="[0-9]{3}-[0-9]{7,8}" maxlength="12" autocomplete="off">
            <div id="phone-error" class="error-message" ></div>

            <label for="matricno">Matric Number:<span class="required">*</span></label>
            <input type="text" id="matricno" name="matricno" placeholder="Eg: B123456789/D123456789" required pattern="[BbDd][0-9]{9}" readonly maxlength="10">
            <div id="matricno-error" class="error-message" ></div>

        <label>Gender:<span class="required">*</span></label>
        <div class="gender-container">
            <label><input type="radio" name="gender" value="M" required> Male</label>
            <label><input type="radio" name="gender" value="F" required> Female</label>
        </div>

        <label for="faculty">Faculty:<span class="required">*</span></label>
        <select id="faculty" name = "faculty" required>
            <option value="" disabled selected>Select your faculty</option>
            <option value="FTKEK">Faculty of Electronics and Computer Technology and Engineering (FTKEK)</option>
            <option value="FTKE">Faculty of Electrical Technology and Engineering (FTKE)</option>
            <option value="FTKM">Faculty of Mechanical Technology and Engineering (FTKM)</option>
            <option value="FTKIP">Faculty of Industrial and Manufacturing Technology and Engineering (FTKIP)</option>
            <option value="FTMK">Faculty Of Information And Communications Technology (FTMK)</option>
            <option value="FPTT">Faculty Of Technology Management And Technopreneurship (FPTT)</option>
            <option value="FAIX">Faculty of Artificial Intelligence and Cyber Security (FAIX)</option>
        </select>

        <label for="year">Current Year:<span class="required">*</span></label>
        <select id="year" name="year" required>
            <option value="" disabled selected>Select your current year</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
            <option value="4">4</option>
        </select>

        <label for="semester">Current Semester:<span class="required">*</span></label>
        <select id="semester" name="semester" required>
            <option value="" disabled selected>Select your semester</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
        </select>

            <label for="password">Password:<span class="required">*</span></label>
            <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Enter your password" required minlength="8" maxlength="16">
                <span class="toggle-password" onclick="togglePassword()">👁️</span>
            </div>
            <div id="password-error" class="error-message"></div>

            <label for="roomSharingStyle">Room Sharing Style:<span class="required">*</span></label>
            <input type="text" id="roomSharingStyle" name="roomSharingStyle" placeholder="Eg: Perfers tidy environment, night owl, friendly..." required autocomplete="off">
            <div id="roomSharingStyle-error" class="error-message"></div>

            <label for="chronicIssueLevel">Chronic Issue Level:<span class="required">*</span></label>
            <select id="chronicIssueLevel" name="chronicIssueLevel" required>
                <option value="" disabled selected>Select your chronic issue level</option>
                <option value="NONE">NONE</option>
                <option value="MILD">MILD</option>
                <option value="MODERATE">MODERATE</option>
                <option value="SEVERE">SEVERE</option>
            </select>

            <label for="chronicIssueName">Chronic Issue Name:<span class="required">*</span></label>
            <input type="text" id="chronicIssueName" name="chronicIssueName" placeholder="Enter your chronic issue (if any)" required autocomplete="off">
            <div id="chronicIssueName-error" class="error-message"></div>

            <button type="button" id="registerBtn">Register</button>
            <div id="result-message" class="success-message"></div>
        </form>
    </div>

    <script>
        // Handle chronic issue name requirement based on level
        document.getElementById('chronicIssueLevel').addEventListener('change', function() {
            const chronicIssueNameField = document.getElementById('chronicIssueName');
            if (this.value === 'NONE') {
                chronicIssueNameField.value = 'N/A';
                chronicIssueNameField.setAttribute('readonly', true);
            } else {
                if (chronicIssueNameField.value === 'N/A') {
                    chronicIssueNameField.value = '';
                }
                chronicIssueNameField.removeAttribute('readonly');
            }
        });
        
        function togglePassword() {
            let passwordField = document.getElementById("password");
            if (passwordField.type === "password") {
                passwordField.type = "text";
            } else {
                passwordField.type = "password";
            }
        }
        
        function extractMatricNo() {
            const emailInput = document.getElementById('email');
            const matricNoInput = document.getElementById('matricno');
            
            // Extract matric number from email if it matches the pattern
            const emailPattern = /^([BbDd][0-9]{9})@student\.utem\.edu\.my$/i;
            const match = emailInput.value.match(emailPattern);
            
            if (match) {
                matricNoInput.value = match[1].toUpperCase();
            } else {
                matricNoInput.value = '';
            }
        }
        
        // Password validation function
        function validatePassword(password) {
            //?=: positive lookahead, meaning it checks for a pattern 
            //.*: Matches any number of characters (including none) before the required character.
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&.+-/_#])[A-Za-z\d@$!%*?&.+-/_#]{8,}$/;
            return passwordRegex.test(password);
        }

        document.getElementById('registerBtn').addEventListener('click', function() {
            // Clear previous error messages
            const errorElements = document.querySelectorAll('.error-message');
            errorElements.forEach(element => {
                element.textContent = '';
            });
            
            // Validate Password
            const password = document.getElementById('password').value;
            const passwordError = document.getElementById('password-error');

            if (!validatePassword(password)) {
                passwordError.textContent = "Password must at least 8 characters, contain at least one lowercase, one uppercase, one special character(@$!%*?&.+-/_#), and one number.";
                return; // Stop execution if password is invalid
            }

            // Check if form is valid
            const form = document.getElementById('registrationForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Get form data
            const formData = new FormData(form);
            
            // Check for uniqueness via AJAX
            fetch('studCheckUniq.php', {
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
                document.getElementById('matricno-error').textContent = '';
                
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
                    if (data.errors.matricno) {
                        document.getElementById('matricno-error').textContent = data.errors.matricno;
                    }
                    return; // Stop execution if there are errors
                } else {
                    // All validations passed, show confirmation dialog
                    showConfirmationDialog();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        });
        
        function showConfirmationDialog() {
            // Get all form values
            const fullname = document.getElementById('fullname').value.toUpperCase();
            const email = document.getElementById('email').value.toUpperCase();
            const personalEmail = document.getElementById('personalEmail').value.toUpperCase();
            const phone = document.getElementById('phone').value;
            const matricno = document.getElementById('matricno').value.toUpperCase();
            const gender = document.querySelector('input[name="gender"]:checked').value;
            const facultySelect = document.getElementById('faculty');
            const facultyText = facultySelect.options[facultySelect.selectedIndex].text;
            const facultyValue = facultySelect.value;
            const year = document.getElementById('year').value;
            const semester = document.getElementById('semester').value;
            const password = document.getElementById('password').value;
            const roomSharingStyle = document.getElementById('roomSharingStyle').value.toUpperCase();
            const chronicIssueLevelSelect = document.getElementById('chronicIssueLevel');
            const chronicIssueLevel = chronicIssueLevelSelect.value;
            const chronicIssueName = document.getElementById('chronicIssueName').value.toUpperCase();
            
            // Create confirmation message
            let confirmMessage = "Please confirm your registration details:\n\n";
            confirmMessage += "Full Name: " + fullname + "\n";
            confirmMessage += "Email: " + email + "\n";
            confirmMessage += "Personal Email: " + personalEmail + "\n";
            confirmMessage += "Phone Number: " + phone + "\n";
            confirmMessage += "Matric Number: " + matricno + "\n";
            confirmMessage += "Gender: " + (gender === 'M' ? 'Male' : 'Female') + "\n";
            confirmMessage += "Faculty: " + facultyText + "\n";
            confirmMessage += "Year: " + year + "\n";
            confirmMessage += "Semester: " + semester + "\n";
            confirmMessage += "Password: " + password + "\n";
            confirmMessage += "Room Sharing Style: " + roomSharingStyle + "\n";
            confirmMessage += "Chronic Issue Level: " + chronicIssueLevel + "\n";
            confirmMessage += "Chronic Issue Name: " + chronicIssueName + "\n";
            
            // Show confirmation dialog
            if (confirm(confirmMessage)) {
                // User clicked OK, submit the form
                submitRegistration();
            }
        }
        
        function submitRegistration() {
            const form = document.getElementById('registrationForm');
            const formData = new FormData(form);
            
            fetch('studProcessReg.php', {
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
                    document.getElementById('result-message').textContent = "Registration successful! Your Student ID is: " + data.studId;
                    document.getElementById('result-message').style.color = "green";
                    
                    setTimeout(() => {
                        window.location.href = "studMainPage.php"; // Redirect after 1.5 seconds
                    }, 1500);
                } else {
                    document.getElementById('result-message').textContent = "Registration failed: " + data.message;
                    document.getElementById('result-message').style.color = "red";
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('result-message').textContent = "An error occurred during registration.";
                document.getElementById('result-message').style.color = "red";
            });
        }
    </script>
</body>
</html>
