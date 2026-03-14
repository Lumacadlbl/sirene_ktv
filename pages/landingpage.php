<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Sirene KTV | Let Your Voice Be Heard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --highlight: #e94560;
            --light: #f5f5f5;
            --dark: #0d1117;
            --gradient-start: #1a1a2e;
            --gradient-end: #16213e;
            --tablet: #3498db;
            --tablet-hover: #5faee3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            color: var(--light);
            min-height: 100vh;
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
        .main-container {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .welcome-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 50px 40px;
            width: 100%;
            max-width: 450px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            z-index: 2;
        }

        .logo {
            margin-bottom: 25px;
        }

        .logo i {
            font-size: 60px;
            color: var(--highlight);
            margin-bottom: 15px;
            display: block;
        }

        .logo h1 {
            font-size: 36px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 10px;
        }

        .tagline {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 10px;
            font-weight: 300;
        }

        .slogan {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 40px;
            font-style: italic;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 25px;
            text-align: left;
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
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: var(--light);
            font-size: 16px;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--highlight);
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 2px rgba(233, 69, 96, 0.2);
        }

        /* Button Styles */
        .btn-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 16px 30px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
        }

        .btn-register {
            background: transparent;
            color: var(--light);
            border: 2px solid var(--highlight);
        }

        /* Tablet Order Button - New Stylish Button */
        .btn-tablet {
            background: linear-gradient(135deg, var(--tablet), #5faee3);
            color: white;
            margin-bottom: 5px;
            position: relative;
            overflow: hidden;
        }

        .btn-tablet::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.3) 0%, transparent 70%);
            opacity: 0;
            transition: opacity 0.3s;
            transform: rotate(45deg);
        }

        .btn-tablet:hover::before {
            opacity: 1;
        }

        .btn-tablet i {
            font-size: 18px;
            animation: pulse-icon 2s infinite;
        }

        @keyframes pulse-icon {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }

        /* Divider with "or" text */
        .divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 20px 0;
            color: rgba(255, 255, 255, 0.4);
            font-size: 14px;
        }

        .divider-line {
            flex: 1;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            box-shadow: 0 10px 20px rgba(233, 69, 96, 0.3);
        }

        .btn-register:hover {
            background: rgba(233, 69, 96, 0.1);
        }

        /* Tablet Order Badge */
        .tablet-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(52, 152, 219, 0.15);
            padding: 8px 16px;
            border-radius: 50px;
            margin-bottom: 20px;
            border: 1px solid rgba(52, 152, 219, 0.3);
            color: var(--tablet);
            font-size: 13px;
            font-weight: 500;
        }

        .tablet-badge i {
            font-size: 14px;
        }

        /* Decorative Elements */
        .decoration {
            position: absolute;
            z-index: -1;
        }

        .decoration-1 {
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(233, 69, 96, 0.1) 0%, transparent 70%);
            top: -50px;
            right: -50px;
            border-radius: 50%;
        }

        .decoration-2 {
            width: 100px;
            height: 100px;
            background: radial-gradient(circle, rgba(15, 52, 96, 0.1) 0%, transparent 70%);
            bottom: -30px;
            left: -30px;
            border-radius: 50%;
        }

        /* Tablet Decoration */
        .decoration-tablet {
            position: absolute;
            bottom: 20px;
            right: 20px;
            width: 60px;
            height: 60px;
            background: radial-gradient(circle, rgba(52, 152, 219, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: -1;
            animation: rotate 10s linear infinite;
        }

        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        /* Features Section */
        .features {
            display: flex;
            justify-content: space-around;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .feature {
            text-align: center;
            flex: 1;
            padding: 0 10px;
        }

        .feature i {
            font-size: 24px;
            color: var(--highlight);
            margin-bottom: 10px;
        }

        .feature span {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.4);
            font-size: 12px;
        }

        /* Quick Order Hint */
        .quick-order-hint {
            margin-top: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            color: rgba(52, 152, 219, 0.8);
            font-size: 13px;
            background: rgba(52, 152, 219, 0.05);
            padding: 10px;
            border-radius: 50px;
            border: 1px dashed rgba(52, 152, 219, 0.3);
        }

        .quick-order-hint i {
            font-size: 16px;
            animation: bounce 1s infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateX(0);
            }
            50% {
                transform: translateX(5px);
            }
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .welcome-card {
                padding: 40px 25px;
                margin: 20px;
            }

            .logo h1 {
                font-size: 28px;
            }

            .tagline {
                font-size: 16px;
            }

            .features {
                flex-direction: column;
                gap: 20px;
            }

            .feature {
                padding: 10px 0;
            }
        }

        @media (max-width: 480px) {
            .welcome-card {
                padding: 30px 20px;
            }

            .btn {
                padding: 14px 20px;
                font-size: 14px;
            }

            .tablet-badge {
                font-size: 12px;
                padding: 6px 12px;
            }
        }

        /* Microphone Animation */
        .mic-animation {
            position: relative;
            width: 80px;
            height: 80px;
            margin: 0 auto 30px;
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

        /* Tablet Wave Animation */
        .tablet-wave {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--tablet), transparent);
            animation: wave 2s ease-in-out infinite;
        }

        @keyframes wave {
            0%, 100% {
                transform: scaleX(0.5);
                opacity: 0.3;
            }
            50% {
                transform: scaleX(1);
                opacity: 0.8;
            }
        }
    </style>
</head>
<body>

<!-- Animated Background -->
<div class="bg-animation" id="musicNotes"></div>

<div class="main-container">
    <div class="welcome-card">
        <!-- Decorative Elements -->
        <div class="decoration decoration-1"></div>
        <div class="decoration decoration-2"></div>
        <div class="decoration-tablet"></div>

        <!-- Tablet Badge - Subtle indicator -->
        <div class="tablet-badge">
            <i class="fas fa-tablet-alt"></i>
            <span>Quick Order Available</span>
        </div>

        <!-- Logo and Header -->
        <div class="logo">
            <!-- Animated Microphone -->
            <div class="mic-animation">
                <div class="mic-icon">
                    <i class="fas fa-microphone-alt"></i>
                </div>
                <div class="sound-wave"></div>
                <div class="sound-wave"></div>
                <div class="sound-wave"></div>
            </div>
            
            <h1>Sirene KTV</h1>
            <div class="tagline">Hear the Call. Sing the Night.</div>
            <div class="slogan">Let your voice be heard in the ultimate singing experience</div>
        </div>

        <!-- Welcome Message -->
        <div class="form-group">
            <label for="welcome-text">Welcome to Your Musical Journey</label>
            <input type="text" id="welcome-text" value="Experience Premium Karaoke Like Never Before" readonly>
        </div>

        <!-- Tablet Order Button - Prominently placed but elegant -->
        <a href="tablet.php" class="btn btn-tablet">
            <i class="fas fa-tablet-alt"></i> Order Now via Tablet
            <i class="fas fa-arrow-right"></i>
        </a>

        <!-- Quick Order Hint -->
        <div class="quick-order-hint">
            <i class="fas fa-bolt"></i>
            <span>No login required • Instant ordering</span>
            <i class="fas fa-clock"></i>
        </div>

        <!-- Divider with "or" -->
        <div class="divider">
            <div class="divider-line"></div>
            <span>or continue with account</span>
            <div class="divider-line"></div>
        </div>

        <!-- Action Buttons -->
        <div class="btn-container">
            <a href="login.php" class="btn btn-login">
                <i class="fas fa-sign-in-alt"></i> Log In to Your Account
            </a>
            <a href="register.php" class="btn btn-register">
                <i class="fas fa-user-plus"></i> Create New Account
            </a>
        </div>

        <!-- Tablet Wave Animation -->
        <div class="tablet-wave"></div>

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
                <i class="fas fa-crown"></i>
                <span>VIP Services</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            © 2024 Sirene KTV. All rights reserved. | Sing your heart out
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

        // Animate welcome text
        const welcomeInput = document.getElementById('welcome-text');
        const messages = [
            "Experience Premium Karaoke Like Never Before",
            "Where Every Voice Matters",
            "Unleash Your Inner Star",
            "Premium Sound, Unforgettable Moments",
            "Book Your Perfect Room Today",
            "Order Food & Drinks Instantly"
        ];

        let currentIndex = 0;
        setInterval(() => {
            welcomeInput.style.opacity = '0.5';
            setTimeout(() => {
                currentIndex = (currentIndex + 1) % messages.length;
                welcomeInput.value = messages[currentIndex];
                welcomeInput.style.opacity = '1';
            }, 300);
        }, 3000);

        // Add hover effect to tablet button
        const tabletBtn = document.querySelector('.btn-tablet');
        if (tabletBtn) {
            tabletBtn.addEventListener('mouseenter', function() {
                this.style.animation = 'pulse 1s';
            });
            tabletBtn.addEventListener('mouseleave', function() {
                this.style.animation = 'none';
            });
        }
    });
</script>

</body>
</html>