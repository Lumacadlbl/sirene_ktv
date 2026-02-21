<?php
session_start();
include "../db.php";

// Helper function to get currency based on country code (ADD THIS FUNCTION)
function getCurrencyFromCountry($country_code) {
    $currencies = [
        '+1' => ['symbol' => '$', 'code' => 'USD', 'name' => 'US Dollar'],
        '+44' => ['symbol' => '£', 'code' => 'GBP', 'name' => 'British Pound'],
        '+61' => ['symbol' => 'A$', 'code' => 'AUD', 'name' => 'Australian Dollar'],
        '+65' => ['symbol' => 'S$', 'code' => 'SGD', 'name' => 'Singapore Dollar'],
        '+60' => ['symbol' => 'RM', 'code' => 'MYR', 'name' => 'Malaysian Ringgit'],
        '+63' => ['symbol' => '₱', 'code' => 'PHP', 'name' => 'Philippine Peso'], // Philippines Peso
        '+81' => ['symbol' => '¥', 'code' => 'JPY', 'name' => 'Japanese Yen'],
        '+82' => ['symbol' => '₩', 'code' => 'KRW', 'name' => 'South Korean Won'],
        '+86' => ['symbol' => '¥', 'code' => 'CNY', 'name' => 'Chinese Yuan'],
        '+91' => ['symbol' => '₹', 'code' => 'INR', 'name' => 'Indian Rupee'],
        '+971' => ['symbol' => 'د.إ', 'code' => 'AED', 'name' => 'UAE Dirham'],
        '+33' => ['symbol' => '€', 'code' => 'EUR', 'name' => 'Euro'],
        '+49' => ['symbol' => '€', 'code' => 'EUR', 'name' => 'Euro'],
        '+34' => ['symbol' => '€', 'code' => 'EUR', 'name' => 'Euro'],
        '+39' => ['symbol' => '€', 'code' => 'EUR', 'name' => 'Euro'],
        '+55' => ['symbol' => 'R$', 'code' => 'BRL', 'name' => 'Brazilian Real'],
        '+52' => ['symbol' => 'Mex$', 'code' => 'MXN', 'name' => 'Mexican Peso']
    ];
    
    return $currencies[$country_code] ?? ['symbol' => '$', 'code' => 'USD', 'name' => 'US Dollar'];
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email    = trim($_POST["email"]);
    $password = $_POST["password"];

    $sql = "SELECT * FROM user_tbl WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user["password"])) {
            // Set session variables
            $_SESSION["user_id"] = $user["id"];
            $_SESSION["name"]    = $user["name"];
            $_SESSION["role"]    = $user["role"];
            
            // ADD THESE LINES - Store country and currency in session
            $_SESSION["user_country_code"] = $user["country_code"] ?? '+1'; // Default to US if not set
            $_SESSION["user_currency"] = getCurrencyFromCountry($user["country_code"] ?? '+1');
            
            // Also store in localStorage via JavaScript (we'll add this in the script section)
            
            // Redirect based on role
            if ($user["role"] === "admin") {
                header("Location: admindash.php");
                exit();
            } else {
                header("Location: dashboard.php");
                exit();
            }
        } else {
            $message = "Incorrect password.";
        }
    } else {
        $message = "Account not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS styles here - keep them exactly as they are */
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
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
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
        .login-container {
            width: 100%;
            max-width: 450px;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            z-index: 2;
        }

        /* Decorative Elements */
        .decoration {
            position: absolute;
            z-index: -1;
        }

        .decoration-1 {
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(233, 69, 96, 0.15) 0%, transparent 70%);
            top: -100px;
            right: -100px;
            border-radius: 50%;
        }

        .decoration-2 {
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(15, 52, 96, 0.15) 0%, transparent 70%);
            bottom: -75px;
            left: -75px;
            border-radius: 50%;
        }

        /* Header */
        .login-header {
            padding: 40px 40px 30px;
            text-align: center;
            position: relative;
        }

        .logo {
            margin-bottom: 15px;
        }

        .mic-animation {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
        }

        .mic-icon {
            position: absolute;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: var(--highlight);
            z-index: 2;
        }

        .sound-wave {
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 2px solid var(--highlight);
            animation: pulse 2s infinite;
            opacity: 0;
        }

        .sound-wave:nth-child(2) {
            animation-delay: 0.5s;
        }

        .sound-wave:nth-child(3) {
            animation-delay: 1s;
        }

        @keyframes pulse {
            0% {
                transform: scale(0.8);
                opacity: 0.7;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }

        .logo h1 {
            font-size: 32px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }

        .logo p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            max-width: 300px;
            margin: 0 auto;
            line-height: 1.5;
        }

        /* Form */
        .login-form {
            padding: 0 40px 40px;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
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
            transition: color 0.3s;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 16px 16px 16px 50px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--light);
            font-size: 16px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--highlight);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.2);
        }

        .form-group input:focus + .input-icon {
            color: var(--highlight);
        }

        /* Password Toggle */
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: var(--highlight);
        }

        /* Remember Me & Forgot Password */
        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            font-size: 14px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }

        .forgot-password {
            color: var(--highlight);
            text-decoration: none;
            transition: all 0.3s;
        }

        .forgot-password:hover {
            text-decoration: underline;
            color: #ff7675;
        }

        /* Form Actions - BACK BUTTON ON LEFT SIDE */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
            border: none;
            width: 60px;
            border-radius: 12px;
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
            box-shadow: 0 5px 15px rgba(233, 69, 96, 0.3);
        }

        .back-btn i {
            font-size: 20px;
        }

        .submit-btn {
            flex: 1;
            padding: 16px;
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            letter-spacing: 0.5px;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(233, 69, 96, 0.4);
        }

        .submit-btn:active {
            transform: translateY(-1px);
        }

        /* Message */
        .message {
            margin-top: 20px;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
            animation: slideIn 0.5s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
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

        .message.error {
            background: rgba(214, 48, 49, 0.2);
            color: var(--danger);
            border: 1px solid rgba(214, 48, 49, 0.3);
        }

        /* Divider */
        .divider {
            display: flex;
            align-items: center;
            margin: 30px 0;
            color: rgba(255, 255, 255, 0.4);
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .divider span {
            padding: 0 15px;
            font-size: 14px;
        }

        /* Register Link */
        .register-link {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
        }

        .register-link a {
            color: var(--highlight);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
            margin-left: 5px;
        }

        .register-link a:hover {
            text-decoration: underline;
            color: #ff7675;
            transform: translateX(2px);
        }

        /* Features */
        .features {
            display: flex;
            justify-content: space-around;
            padding: 25px 40px;
            background: rgba(255, 255, 255, 0.05);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
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
            .login-container {
                max-width: 90%;
            }
            
            .login-header,
            .login-form {
                padding: 30px;
            }
            
            .features {
                padding: 20px 30px;
            }
        }

        @media (max-width: 480px) {
            .login-header,
            .login-form {
                padding: 25px 20px;
            }
            
            .logo h1 {
                font-size: 26px;
            }
            
            .form-options {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .social-login {
                flex-direction: column;
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

<!-- Decorative Elements -->
<div class="decoration decoration-1"></div>
<div class="decoration decoration-2"></div>

<!-- Main Container -->
<div class="login-container">
    <!-- Header -->
    <div class="login-header">
        <div class="logo">
            <div class="mic-animation">
                <div class="mic-icon">
                    <i class="fas fa-microphone-alt"></i>
                </div>
                <div class="sound-wave"></div>
                <div class="sound-wave"></div>
                <div class="sound-wave"></div>
            </div>
            <h1>Welcome Back</h1>
            <p>Sign in to continue your musical journey at Sirene KTV</p>
        </div>
    </div>

    <!-- Login Form -->
    <form class="login-form" method="POST" id="loginForm">
        <!-- Email Field -->
        <div class="form-group">
            <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
            <div class="input-with-icon">
                <i class="fas fa-envelope input-icon"></i>
                <input type="email" id="email" name="email" placeholder="Enter your email" required autofocus>
            </div>
        </div>

        <!-- Password Field -->
        <div class="form-group">
            <label for="password"><i class="fas fa-lock"></i> Password</label>
            <div class="input-with-icon">
                <i class="fas fa-lock input-icon"></i>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                <button type="button" class="password-toggle" id="togglePassword">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        </div>

        <!-- Options -->
        <div class="form-options">
            <label class="remember-me">
                <input type="checkbox" name="remember" id="remember">
                Remember me
            </label>
            <a href="forgot-password.php" class="forgot-password">
                Forgot Password?
            </a>
        </div>

        <!-- Form Actions - BACK BUTTON ON LEFT SIDE -->
        <div class="form-actions">
            <!-- Back button on LEFT side -->
            <a href="landingpage.php" class="back-btn" title="Back to Home">
                <i class="fas fa-arrow-left"></i>
            </a>
            
            <!-- Login button on RIGHT side -->
            <button type="submit" class="submit-btn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </div>

        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Register Link -->
        <div class="register-link">
            Don't have an account?
            <a href="register.php">Create one now</a>
        </div>
    </form>

    <!-- Features -->
    <div class="features">
        <div class="feature">
            <i class="fas fa-music"></i>
            <span>Latest Songs</span>
        </div>
        <div class="feature">
            <i class="fas fa-crown"></i>
            <span>VIP Access</span>
        </div>
        <div class="feature">
            <i class="fas fa-headphones"></i>
            <span>Premium Sound</span>
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

        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle eye icon
            const icon = this.querySelector('i');
            if (type === 'text') {
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Form validation
        const form = document.getElementById('loginForm');
        const emailInput = document.getElementById('email');
        const passwordInputField = document.getElementById('password');

        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Email validation
            if (!emailInput.value.trim()) {
                showError(emailInput, 'Email is required');
                isValid = false;
            } else if (!isValidEmail(emailInput.value)) {
                showError(emailInput, 'Please enter a valid email');
                isValid = false;
            } else {
                clearError(emailInput);
            }

            // Password validation
            if (!passwordInputField.value.trim()) {
                showError(passwordInputField, 'Password is required');
                isValid = false;
            } else {
                clearError(passwordInputField);
            }

            if (!isValid) {
                e.preventDefault();
            }
        });

        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }

        function showError(input, message) {
            const formGroup = input.closest('.form-group');
            let error = formGroup.querySelector('.error-message');
            
            if (!error) {
                error = document.createElement('div');
                error.className = 'error-message';
                error.style.color = 'var(--danger)';
                error.style.fontSize = '12px';
                error.style.marginTop = '5px';
                formGroup.appendChild(error);
            }
            
            error.textContent = message;
            input.style.borderColor = 'var(--danger)';
        }

        function clearError(input) {
            const formGroup = input.closest('.form-group');
            const error = formGroup.querySelector('.error-message');
            
            if (error) {
                error.remove();
            }
            
            input.style.borderColor = '';
        }

        // Check for saved login credentials
        window.addEventListener('load', function() {
            const savedEmail = localStorage.getItem('sirene_remember_email');
            const savedPassword = localStorage.getItem('sirene_remember_password');
            const rememberCheckbox = document.getElementById('remember');
            
            if (savedEmail && savedPassword) {
                emailInput.value = savedEmail;
                passwordInputField.value = savedPassword;
                rememberCheckbox.checked = true;
            }

            // Save credentials if remember me is checked
            rememberCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    localStorage.setItem('sirene_remember_email', emailInput.value);
                    localStorage.setItem('sirene_remember_password', passwordInputField.value);
                } else {
                    localStorage.removeItem('sirene_remember_email');
                    localStorage.removeItem('sirene_remember_password');
                }
            });
        });

        // Auto-focus on email field
        emailInput.focus();
    });
</script>

</body>
</html>