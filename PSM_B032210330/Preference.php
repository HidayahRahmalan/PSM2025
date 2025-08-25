<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: LogIn.php");
    exit();
}

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Food Preferences - NutriEats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <style>
        /* Your existing styles remain unchanged */
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            background-color: #212226;
            padding: 0 20px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo {
            max-height: 60px;
            display: block;
        }

        .site-title {
            color: #b8860b;
            font-size: 30px;
            font-weight: bold;
            user-select: none;
        }

        .nav-links {
            display: flex;
            gap: 20px;
        }

        .nav-links a {
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        .nav-links a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        h5 {
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin: 10px 0 5px;
            font-weight: 600;
            color: #333;
        }

        input[type="text"],
        button {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.3s ease;
        }

        input[type="text"]:focus {
            border-color: #b8860b;
        }

        button {
            background-color: #b8860b;
            color: white;
            cursor: pointer;
            font-weight: 700;
        }

        button:hover {
            background-color: #daaa20;
        }

        textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.3s ease;
            resize: vertical;
        }

        textarea:focus {
            border-color: #b8860b;
        }

        .error-message {
            color: red;
            display: none;
        }
    </style>
</head>

<body>
    <header class="header">
        <a href="index.php" class="logo-container" aria-label="NutriEats Home">
            <img src="assets/NutriEats.png" alt="NutriEats Logo" class="logo" />
            <span class="site-title">NutriEats</span>
        </a>
        <nav class="nav-links">
            <a href="MyRecipe.php">My Recipes</a>
            <a href="MealPlan.php">Meal Plan</a>
            <a href="Groceries.php">Groceries</a>
            <a href="Profile.php">Profile</a>
        </nav>
    </header>

    <main class="container">
        <h2>Welcome to NutriEats, <?php echo htmlspecialchars($username); ?>!</h2>
        <h5>In order to enhance user experience, please input your dietary restrictions and preferences</h5>

        <form id="preferencesForm" novalidate>
            <label>Do you have any food preferences?</label>
            <label><input type="radio" name="foodPreferences" value="yes" onclick="togglePreferences(true)"> Yes</label>
            <label><input type="radio" name="foodPreferences" value="no" onclick="togglePreferences(false)"> No</label>
            <textarea id="preferences" placeholder="List your food preferences (e.g: Protein, Low Fat, High Carbs)" style="display:none;"></textarea>

            <label for="dietaryDiseases">Select any dietary diseases:</label>
            <select id="dietaryDiseases" name="dietaryDiseases" onchange="setDietaryRestrictions()">
                <option value="none">None</option>
                <option value="hypertension">Hypertension</option>
                <option value="diabetes">Diabetes Type 2</option>
                <option value="highCholesterol">High Cholesterol</option>
                <option value="celiacDisease">Celiac Disease</option>
            </select>

            <!-- Hidden field to store dietary restrictions -->
            <input type="hidden" id="dietaryRestrictions" name="dietaryRestrictions">

            <label>Do you have any allergies?</label>
            <label><input type="radio" name="allergies" value="yes" onclick="toggleAllergies(true)"> Yes</label>
            <label><input type="radio" name="allergies" value="no" onclick="toggleAllergies(false)"> No</label>
            <textarea id="allergies" placeholder="List your allergies (e.g.: Dairy, Nuts, Seafood)" style="display:none;"></textarea>

            <div class="error-message" id="errorMessage">Preferences should not be included in allergies.</div>

            <button type="submit">Submit Preferences</button>
        </form>
    </main>

    <script>
        // Load existing preferences
        window.onload = function () {
            fetch('get_preferences.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.foodPreferences === 'yes') {
                            document.querySelector('input[name="foodPreferences"][value="yes"]').checked = true;
                            togglePreferences(true);
                            document.getElementById('preferences').value = data.preferences;
                        } else {
                            document.querySelector('input[name="foodPreferences"][value="no"]').checked = true;
                            togglePreferences(false);
                        }

                        if (data.allergiesAnswer === 'yes') {
                            document.querySelector('input[name="allergies"][value="yes"]').checked = true;
                            toggleAllergies(true);
                            document.getElementById('allergies').value = data.allergies;
                        } else {
                            document.querySelector('input[name="allergies"][value="no"]').checked = true;
                            toggleAllergies(false);
                        }

                        document.getElementById('dietaryDiseases').value = data.dietaryDiseases || 'none';
                        setDietaryRestrictions();
                    }
                })
                .catch(error => console.error('Error fetching preferences:', error));
        };

        function togglePreferences(show) {
            document.getElementById('preferences').style.display = show ? 'block' : 'none';
        }

        function toggleAllergies(show) {
            document.getElementById('allergies').style.display = show ? 'block' : 'none';
        }

        function setDietaryRestrictions() {
            const dietaryDiseases = document.getElementById('dietaryDiseases').value;
            const dietaryRestrictionsField = document.getElementById('dietaryRestrictions');

            switch (dietaryDiseases) {
                case 'hypertension':
                    dietaryRestrictionsField.value = 'Low Sodium';
                    break;
                case 'diabetes':
                    dietaryRestrictionsField.value = 'Low Sugar';
                    break;
                case 'highCholesterol':
                    dietaryRestrictionsField.value = 'Low Saturated Fat';
                    break;
                case 'celiacDisease':
                    dietaryRestrictionsField.value = 'Gluten-Free';
                    break;
                default:
                    dietaryRestrictionsField.value = '';
                    break;
            }
        }

        document.getElementById('preferencesForm').addEventListener('submit', function (event) {
            event.preventDefault();

            const foodPreferencesAnswered = document.querySelector('input[name="foodPreferences"]:checked');
            const allergiesAnswered = document.querySelector('input[name="allergies"]:checked');

            if (!foodPreferencesAnswered || !allergiesAnswered) {
                alert('Please answer all questions before submitting.');
                return;
            }

            const preferences = foodPreferencesAnswered.value === 'yes'
                ? document.getElementById('preferences').value.toLowerCase().split(',').map(item => item.trim()).filter(item => item)
                : [];

            const allergies = allergiesAnswered.value === 'yes'
                ? document.getElementById('allergies').value.toLowerCase().split(',').map(item => item.trim()).filter(item => item)
                : [];

            const errorMessage = document.getElementById('errorMessage');
            errorMessage.style.display = 'none';

            const hasOverlap = preferences.some(pref => allergies.includes(pref));

            if (hasOverlap) {
                errorMessage.style.display = 'block';
            } else {
                const formData = new FormData();
                formData.append('preferences', JSON.stringify(preferences));
                formData.append('allergies', JSON.stringify(allergies));
                formData.append('foodPreferences', foodPreferencesAnswered.value);
                formData.append('allergiesAnswer', allergiesAnswered.value);
                formData.append('dietaryDiseases', document.getElementById('dietaryDiseases').value);
                formData.append('dietaryRestrictions', document.getElementById('dietaryRestrictions').value);

                fetch('get_preferences.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Preferences submitted successfully!');
                        window.location.href = 'Homepage.php';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while submitting preferences. Please try again.');
                });
            }
        });
    </script>
</body>

</html>
