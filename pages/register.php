<?php
include "../db.php";
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = trim($_POST["name"]);
    $email    = trim($_POST["email"]);
    $contact  = trim($_POST["country_code"] . $_POST["contact"]);
    $age      = (int)$_POST["age"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);

    $sql = "INSERT INTO user_tbl (name, email, password, contact, age, role)
            VALUES (?, ?, ?, ?, ?, 'user')";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $name, $email, $password, $contact, $age);

    if ($stmt->execute()) {
        $message = "Registration successful! You can now log in.";
    } else {
        $message = "Email already exists.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --highlight: #e94560;
            --light: #f5f5f5;
            --dark: #0d1117;
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #d63031;
            --info: #0984e3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--light);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        /* Background Animation */
        .bg-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .music-note {
            position: absolute;
            color: rgba(233, 69, 96, 0.1);
            font-size: 24px;
            animation: float 15s infinite linear;
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 0.3;
            }
            90% {
                opacity: 0.3;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        /* Main Container */
        .register-container {
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            position: relative;
        }

        .register-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            z-index: 2;
        }

        /* Header */
        .register-header {
            padding: 30px 40px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logo {
            margin-bottom: 15px;
        }

        .logo i {
            font-size: 50px;
            color: var(--highlight);
            margin-bottom: 10px;
        }

        .logo h1 {
            font-size: 28px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 5px;
        }

        .logo p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
        }

        /* Form */
        .register-form {
            padding: 30px 40px 40px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 576px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .input-with-icon {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
            z-index: 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 15px 15px 15px 45px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--light);
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--highlight);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 2px rgba(233, 69, 96, 0.2);
        }

        .form-group select {
            appearance: none;
            cursor: pointer;
            padding-right: 40px;
        }

        .select-arrow {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
            pointer-events: none;
            z-index: 2;
        }

        /* Contact Input Group */
        .contact-group {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 10px;
            align-items: center;
        }

        .country-code-select {
            position: relative;
            min-width: 120px;
        }

        .country-code-select select {
            padding: 15px 35px 15px 40px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--light); /* White text when closed */
            font-size: 14px;
            height: 100%;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        /* Style for the dropdown options when opened */
        .country-code-select select option {
            background-color: #ffffff; /* White background */
            color: #000000; /* Black text */
            padding: 12px 15px;
            font-size: 14px;
        }

        /* Style for the selected option in dropdown */
        .country-code-select select option:checked {
            background-color: #f0f0f0; /* Light gray background */
            color: #000000; /* Black text */
        }

        /* For the dropdown when it's open */
        .country-code-select select:focus option:checked {
            background-color: var(--highlight);
            color: #ffffff;
        }

        /* For Firefox - ensure black text in dropdown */
        @-moz-document url-prefix() {
            .country-code-select select {
                color: var(--light);
                text-shadow: 0 0 0 var(--light);
            }
            
            .country-code-select select option {
                background-color: #ffffff !important;
                color: #000000 !important;
            }
        }

        /* For WebKit browsers (Chrome, Safari, Edge) */
        .country-code-select select option {
            background-color: #ffffff !important;
            color: #000000 !important;
        }

        /* When dropdown is open, change the closed state text to white */
        .country-code-select select {
            color: var(--light) !important;
        }

        .country-flag {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            z-index: 2;
        }

        .phone-input {
            flex: 1;
        }

        .password-strength {
            height: 5px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-meter {
            height: 100%;
            width: 0;
            border-radius: 5px;
            transition: width 0.3s;
        }

        /* Terms */
        .terms {
            margin: 25px 0;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border-left: 3px solid var(--highlight);
        }

        .terms label {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
            cursor: pointer;
        }

        .terms input[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
        }

        .terms a {
            color: var(--highlight);
            text-decoration: none;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 10px;
        }

        .submit-btn {
            flex: 1;
            padding: 16px;
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(233, 69, 96, 0.3);
        }

        .submit-btn:disabled {
            background: rgba(255, 255, 255, 0.1);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
            border: none;
            width: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
        }

        .back-btn:hover {
            background: var(--highlight);
            transform: translateY(-3px);
        }

        .back-btn i {
            font-size: 20px;
        }

        /* Message */
        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
            animation: slideIn 0.5s;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.success {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .message.error {
            background: rgba(214, 48, 49, 0.2);
            color: var(--danger);
            border: 1px solid rgba(214, 48, 49, 0.3);
        }

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
        }

        .login-link a {
            color: var(--highlight);
            text-decoration: none;
            font-weight: 600;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* Features */
        .features {
            display: flex;
            justify-content: space-around;
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
        }

        .feature {
            text-align: center;
            flex: 1;
            padding: 0 10px;
        }

        .feature i {
            font-size: 20px;
            color: var(--highlight);
            margin-bottom: 8px;
        }

        .feature span {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .register-container {
                max-width: 90%;
            }
            
            .register-header,
            .register-form {
                padding: 25px 30px;
            }
            
            .contact-group {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .country-code-select {
                min-width: 100%;
            }
        }

        @media (max-width: 480px) {
            .register-header,
            .register-form {
                padding: 20px;
            }
            
            .logo h1 {
                font-size: 24px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .back-btn {
                width: 100%;
                height: 50px;
                order: 2;
            }
            
            .submit-btn {
                order: 1;
            }
        }
    </style>
</head>
<body>

<!-- Animated Background -->
<div class="bg-animation" id="musicNotes"></div>

<!-- Main Container -->
<div class="register-container">
    <!-- Header -->
    <div class="register-header">
        <div class="logo">
            <i class="fas fa-microphone-alt"></i>
            <h1>Join Sirene KTV</h1>
            <p>Create your account and start singing</p>
        </div>
    </div>

    <!-- Registration Form -->
    <form class="register-form" method="POST" id="registerForm">
        <div class="form-row">
            <div class="form-group">
                <label for="name"><i class="fas fa-user"></i> Full Name</label>
                <div class="input-with-icon">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" id="name" name="name" placeholder="Enter your full name" required>
                </div>
            </div>

            <div class="form-group">
                <label for="age"><i class="fas fa-birthday-cake"></i> Age</label>
                <div class="input-with-icon">
                    <i class="fas fa-birthday-cake input-icon"></i>
                    <input type="number" id="age" name="age" placeholder="Your age" min="18" max="100" required>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
            <div class="input-with-icon">
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
        </div>

        <div class="form-group">
            <label for="contact"><i class="fas fa-phone"></i> Contact Number</label>
            <div class="contact-group">
                <div class="country-code-select">
                    <div class="input-with-icon">
                        <i class="fas fa-globe input-icon"></i>
                        <select id="country_code" name="country_code" required>
                            <option value="">Select Country</option>
                            <option value="+1" data-flag="🇺🇸">🇺🇸 USA (+1)</option>
                            <option value="+44" data-flag="🇬🇧">🇬🇧 UK (+44)</option>
                            <option value="+61" data-flag="🇦🇺">🇦🇺 Australia (+61)</option>
                            <option value="+65" data-flag="🇸🇬">🇸🇬 Singapore (+65)</option>
                            <option value="+60" data-flag="🇲🇾">🇲🇾 Malaysia (+60)</option>
                            <option value="+63" data-flag="🇵🇭">🇵🇭 Philippines (+63)</option>
                            <option value="+81" data-flag="🇯🇵">🇯🇵 Japan (+81)</option>
                            <option value="+82" data-flag="🇰🇷">🇰🇷 South Korea (+82)</option>
                            <option value="+86" data-flag="🇨🇳">🇨🇳 China (+86)</option>
                            <option value="+91" data-flag="🇮🇳">🇮🇳 India (+91)</option>
                            <option value="+971" data-flag="🇦🇪">🇦🇪 UAE (+971)</option>
                            <option value="+33" data-flag="🇫🇷">🇫🇷 France (+33)</option>
                            <option value="+49" data-flag="🇩🇪">🇩🇪 Germany (+49)</option>
                            <option value="+34" data-flag="🇪🇸">🇪🇸 Spain (+34)</option>
                            <option value="+39" data-flag="🇮🇹">🇮🇹 Italy (+39)</option>
                            <option value="+55" data-flag="🇧🇷">🇧🇷 Brazil (+55)</option>
                            <option value="+52" data-flag="🇲🇽">🇲🇽 Mexico (+52)</option>
                        </select>
                        <i class="fas fa-chevron-down select-arrow"></i>
                    </div>
                </div>
                <div class="phone-input">
                    <div class="input-with-icon">
                        <i class="fas fa-phone input-icon"></i>
                        <input type="text" id="contact" name="contact" placeholder="Phone number" required 
                               pattern="[0-9]{7,15}" title="Enter 7-15 digits without country code">
                    </div>
                </div>
            </div>
            <small style="color: rgba(255,255,255,0.5); font-size: 12px; display: block; margin-top: 5px;">
                Select your country and enter phone number (digits only)
            </small>
        </div>

        <div class="form-group">
            <label for="password"><i class="fas fa-lock"></i> Password</label>
            <div class="input-with-icon">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="password" name="password" placeholder="Create a strong password" required onkeyup="checkPasswordStrength(this.value)">
            </div>
            <div class="password-strength">
                <div class="strength-meter" id="strengthMeter"></div>
            </div>
            <small style="color: rgba(255,255,255,0.5); font-size: 12px; display: block; margin-top: 5px;">
                Must be at least 8 characters with letters and numbers
            </small>
        </div>

        <div class="form-group">
            <label for="confirmPassword"><i class="fas fa-lock"></i> Confirm Password</label>
            <div class="input-with-icon">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="confirmPassword" placeholder="Confirm your password" required onkeyup="checkPasswordMatch()">
            </div>
            <small id="passwordMatch" style="font-size: 12px; display: block; margin-top: 5px;"></small>
        </div>

        <!-- Terms & Conditions -->
        <div class="terms">
            <label>
                <input type="checkbox" id="terms" name="terms" required>
                I agree to the <a href="#">Terms & Conditions</a> and <a href="#">Privacy Policy</a>
            </label>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <a href="landingpage.php" class="back-btn" title="Back to Home">
                <i class="fas fa-arrow-left"></i>
            </a>
            
            <button type="submit" class="submit-btn" id="submitBtn">
                <i class="fas fa-user-plus"></i> Create Account
            </button>
        </div>

        <!-- Message Display -->
        <?php if (isset($message) && !empty($message)): ?>
            <div class="message <?php echo strpos($message, 'successful') !== false ? 'success' : 'error'; ?>">
                <i class="fas <?php echo strpos($message, 'successful') !== false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Login Link -->
        <div class="login-link">
            Already have an account? <a href="login.php">Log in here</a>
        </div>
    </form>

    <!-- Features -->
    <div class="features">
        <div class="feature">
            <i class="fas fa-music"></i>
            <span>5000+ Songs</span>
        </div>
        <div class="feature">
            <i class="fas fa-star"></i>
            <span>Premium Rooms</span>
        </div>
        <div class="feature">
            <i class="fas fa-shield-alt"></i>
            <span>Secure</span>
        </div>
    </div>
</div>

<script>
    // Create animated music notes
    document.addEventListener('DOMContentLoaded', function() {
        const bgAnimation = document.getElementById('musicNotes');
        const notes = ['♪', '♫', '♬', '🎵', '🎶', '🎤', '🎧'];
        
        for (let i = 0; i < 15; i++) {
            const note = document.createElement('div');
            note.className = 'music-note';
            note.textContent = notes[Math.floor(Math.random() * notes.length)];
            note.style.left = Math.random() * 100 + '%';
            note.style.animationDelay = Math.random() * 15 + 's';
            note.style.fontSize = (Math.random() * 20 + 15) + 'px';
            note.style.color = `rgba(233, 69, 96, ${Math.random() * 0.1 + 0.05})`;
            bgAnimation.appendChild(note);
        }

        // Update flag display when country changes
        const countrySelect = document.getElementById('country_code');
        
        countrySelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const flag = selectedOption.getAttribute('data-flag');
            
            // Update phone placeholder based on country
            const countryCode = this.value;
            updatePhonePlaceholder(countryCode);
        });

        function updatePhonePlaceholder(countryCode) {
            const phoneInput = document.getElementById('contact');
            const examples = {
                '+1': '5551234567',
                '+44': '7911123456',
                '+61': '412345678',
                '+65': '91234567',
                '+60': '123456789',
                '+63': '9171234567',
                '+81': '9012345678',
                '+82': '1023456789',
                '+86': '13123456789',
                '+91': '9876543210',
                '+971': '501234567',
                '+33': '612345678',
                '+49': '15123456789',
                '+34': '612345678',
                '+39': '3123456789',
                '+55': '11987654321',
                '+52': '5512345678'
            };
            
            phoneInput.placeholder = examples[countryCode] || 'Phone number';
        }

        // Check password strength
        window.checkPasswordStrength = function(password) {
            const meter = document.getElementById('strengthMeter');
            let strength = 0;
            
            if (password.length >= 8) strength += 25;
            if (/[a-z]/.test(password)) strength += 25;
            if (/[A-Z]/.test(password)) strength += 25;
            if (/[0-9]/.test(password)) strength += 25;
            
            meter.style.width = strength + '%';
            
            if (strength < 50) {
                meter.style.backgroundColor = '#d63031';
            } else if (strength < 75) {
                meter.style.backgroundColor = '#fdcb6e';
            } else {
                meter.style.backgroundColor = '#00b894';
            }
        };

        // Check password match
        window.checkPasswordMatch = function() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const matchText = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');
            
            if (confirmPassword === '') {
                matchText.textContent = '';
                matchText.style.color = '';
                submitBtn.disabled = false;
                return;
            }
            
            if (password === confirmPassword) {
                matchText.innerHTML = '<i class="fas fa-check"></i> Passwords match';
                matchText.style.color = '#00b894';
                submitBtn.disabled = false;
            } else {
                matchText.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
                matchText.style.color = '#d63031';
                submitBtn.disabled = true;
            }
        };

        // Form validation
        const form = document.getElementById('registerForm');
        form.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const terms = document.getElementById('terms').checked;
            const countryCode = document.getElementById('country_code').value;
            const phone = document.getElementById('contact').value;
            
            // Check country selection
            if (!countryCode) {
                e.preventDefault();
                alert('Please select your country');
                return;
            }
            
            // Check phone number
            if (!/^[0-9]{7,15}$/.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid phone number (7-15 digits)');
                return;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('You must agree to the terms and conditions');
                return;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
                return;
            }
        });

        // Age validation
        const ageInput = document.getElementById('age');
        ageInput.addEventListener('input', function() {
            if (this.value < 18) {
                this.setCustomValidity('You must be at least 18 years old to register');
            } else if (this.value > 100) {
                this.setCustomValidity('Please enter a valid age');
            } else {
                this.setCustomValidity('');
            }
        });

        // Contact number formatting - allow only digits
        const contactInput = document.getElementById('contact');
        contactInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Validate length based on country
            const countryCode = document.getElementById('country_code').value;
            const minLength = getMinPhoneLength(countryCode);
            const maxLength = getMaxPhoneLength(countryCode);
            
            if (this.value.length < minLength) {
                this.setCustomValidity(`Phone number should be at least ${minLength} digits for this country`);
            } else if (this.value.length > maxLength) {
                this.setCustomValidity(`Phone number should not exceed ${maxLength} digits`);
            } else {
                this.setCustomValidity('');
            }
        });

        function getMinPhoneLength(countryCode) {
            const minLengths = {
                '+1': 10, // USA/Canada
                '+44': 10, // UK
                '+61': 9, // Australia
                '+65': 8, // Singapore
                '+60': 9, // Malaysia
                '+63': 10, // Philippines
                '+81': 10, // Japan
                '+82': 10, // South Korea
                '+86': 11, // China
                '+91': 10, // India
                '+971': 9, // UAE
                '+33': 9, // France
                '+49': 10, // Germany
                '+34': 9, // Spain
                '+39': 10, // Italy
                '+55': 11, // Brazil
                '+52': 10 // Mexico
            };
            return minLengths[countryCode] || 7;
        }

        function getMaxPhoneLength(countryCode) {
            const maxLengths = {
                '+1': 10,
                '+44': 10,
                '+61': 9,
                '+65': 8,
                '+60': 9,
                '+63': 10,
                '+81': 10,
                '+82': 10,
                '+86': 11,
                '+91': 10,
                '+971': 9,
                '+33': 9,
                '+49': 10,
                '+34': 9,
                '+39': 10,
                '+55': 11,
                '+52': 10
            };
            return maxLengths[countryCode] || 15;
        }
    });
</script>

</body>
</html>