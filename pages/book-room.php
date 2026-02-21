<?php
session_start();
include "../db.php";

// Add error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

// Check if user has reached max bookings (2)
$active_bookings_query = $conn->prepare("
    SELECT COUNT(*) as count 
    FROM booking 
    WHERE u_id = ? 
    AND status IN ('confirmed', 'pending') 
    AND booking_date >= CURDATE()
");
$active_bookings_query->bind_param("i", $user_id);
$active_bookings_query->execute();
$active_result = $active_bookings_query->get_result();
$active_count = $active_result->fetch_assoc()['count'];

if ($active_count >= 2) {
    $_SESSION['error'] = "You have reached the maximum limit of 2 active bookings.";
    header("Location: dashboard.php?tab=rooms");
    exit;
}

// Fetch user details for the header
$user_query = $conn->prepare("SELECT name, country_code FROM user_tbl WHERE id = ?");
$user_query->bind_param("i", $user_id);
$user_query->execute();
$user_result = $user_query->get_result();
$user_data = $user_result->fetch_assoc();
$user_name = $user_data['name'] ?? 'Guest';

// Get user's currency
function getCurrencyFromCountry($country_code) {
    $currencies = [
        '+1' => ['symbol' => '$', 'code' => 'USD'],
        '+44' => ['symbol' => '£', 'code' => 'GBP'],
        '+61' => ['symbol' => 'A$', 'code' => 'AUD'],
        '+65' => ['symbol' => 'S$', 'code' => 'SGD'],
        '+60' => ['symbol' => 'RM', 'code' => 'MYR'],
        '+63' => ['symbol' => '₱', 'code' => 'PHP'],
        '+81' => ['symbol' => '¥', 'code' => 'JPY'],
        '+82' => ['symbol' => '₩', 'code' => 'KRW'],
        '+86' => ['symbol' => '¥', 'code' => 'CNY'],
        '+91' => ['symbol' => '₹', 'code' => 'INR'],
    ];
    return $currencies[$country_code] ?? ['symbol' => '₹', 'code' => 'INR'];
}

$currency = getCurrencyFromCountry($user_data['country_code'] ?? '+91');
$currency_symbol = $currency['symbol'];

// FIXED: Removed status check so any room can be booked
$room_query = $conn->prepare("SELECT * FROM room WHERE r_id = ?");
$room_query->bind_param("i", $room_id);
$room_query->execute();
$room_result = $room_query->get_result();
$room = $room_result->fetch_assoc();

if (!$room) {
    $_SESSION['error'] = "Room not found.";
    header("Location: dashboard.php?tab=rooms");
    exit;
}

// Get existing bookings for this room to show availability
$existing_bookings = [];
$bookings_query = $conn->prepare("
    SELECT b.* 
    FROM booking b
    WHERE b.r_id = ? 
    AND b.booking_date >= CURDATE()
    AND b.status IN ('confirmed', 'pending')
    ORDER BY b.booking_date ASC, b.start_time ASC
");
$bookings_query->bind_param("i", $room_id);
$bookings_query->execute();
$bookings_result = $bookings_query->get_result();
while ($booking = $bookings_result->fetch_assoc()) {
    $existing_bookings[] = $booking;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    // Notes is received but NOT saved to database
    $notes = $_POST['notes'] ?? ''; // Just for reference, not used in DB
    
    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date)) {
        $error = "Invalid date format";
    } else {
        // Calculate hours
        $start_datetime = strtotime("$booking_date $start_time");
        $end_datetime = strtotime("$booking_date $end_time");
        
        // Handle overnight bookings
        if ($end_datetime < $start_datetime) {
            $end_datetime = strtotime("$booking_date $end_time +1 day");
        }
        
        $hours = round(($end_datetime - $start_datetime) / 3600, 1);
        
        if ($hours <= 0) {
            $error = "End time must be after start time";
        } elseif ($hours < 1) {
            $error = "Minimum booking duration is 1 hour";
        } elseif ($hours > 6) {
            $error = "Maximum booking duration is 6 hours";
        } else {
            // Check if room is available for this time slot
            $check_query = $conn->prepare("
                SELECT * FROM booking 
                WHERE r_id = ? 
                AND booking_date = ?
                AND status IN ('confirmed', 'pending')
                AND (
                    (start_time <= ? AND end_time > ?)
                    OR (start_time < ? AND end_time >= ?)
                    OR (? <= start_time AND ? > start_time)
                )
            ");
            $check_query->bind_param(
                "isssssss",
                $room_id,
                $booking_date,
                $start_time,
                $start_time,
                $end_time,
                $end_time,
                $start_time,
                $end_time
            );
            $check_query->execute();
            $check_result = $check_query->get_result();
            
            if ($check_result->num_rows > 0) {
                $conflict = $check_result->fetch_assoc();
                $error = "Room is already booked from " . 
                         date('g:i A', strtotime($conflict['start_time'])) . 
                         " to " . date('g:i A', strtotime($conflict['end_time']));
            } else {
                // Calculate room amount
                $room_amount = $room['price_hr'] * $hours;
                
                // Calculate tax (10%)
                $subtotal = $room_amount;
                $tax_amount = $subtotal * 0.10;
                $total_amount = $subtotal + $tax_amount;
                
                // Insert booking with status = 'confirmed' (auto-approved)
                $stmt = $conn->prepare("INSERT INTO booking (
                    u_id, r_id, booking_date, start_time, end_time, hours, 
                    room_amount, food_amount, subtotal, tax_amount, total_amount, 
                    status, payment_status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'pending', NOW())");

                $food_amount = 0; // No food items initially
                
                // FIXED: Correct bind_param type string
                // i = integer, s = string, d = double/decimal
                $stmt->bind_param(
                    "iisssdddddd", // i,i,s,s,s,d,d,d,d,d,d (11 characters)
                    $user_id,      // i
                    $room_id,      // i
                    $booking_date, // s
                    $start_time,   // s
                    $end_time,     // s
                    $hours,        // d (decimal)
                    $room_amount,  // d
                    $food_amount,  // d
                    $subtotal,     // d
                    $tax_amount,   // d
                    $total_amount  // d
                );
                
                if ($stmt->execute()) {
                    $booking_id = $stmt->insert_id;
                    
                    $_SESSION['success'] = "Booking confirmed successfully! Your room is ready.";
                    header("Location: booking-confirmation.php?booking_id=$booking_id");
                    exit;
                } else {
                    $error = "Failed to create booking. Please try again. Error: " . $conn->error;
                }
            }
        }
    }
}

// Calculate min/max dates (today to 60 days in future)
$min_date = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+60 days'));

// Default booking date (tomorrow)
$default_date = date('Y-m-d', strtotime('+1 day'));

// Get unavailable time slots for JavaScript (for visual indication)
$unavailable_slots = [];
foreach ($existing_bookings as $booking) {
    $date = $booking['booking_date'];
    if (!isset($unavailable_slots[$date])) {
        $unavailable_slots[$date] = [];
    }
    $unavailable_slots[$date][] = [
        'start' => $booking['start_time'],
        'end' => $booking['end_time']
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Room - Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --highlight: #e94560;
            --light: #f5f5f5;
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
        }

        .main-header {
            background: rgba(26, 26, 46, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(233, 69, 96, 0.3);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            letter-spacing: -0.5px;
        }

        .back-dashboard-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 28px;
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
            letter-spacing: 0.3px;
        }

        .back-dashboard-btn:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(233, 69, 96, 0.4);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .user-name {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--light);
            font-weight: 500;
            background: rgba(233, 69, 96, 0.15);
            padding: 8px 18px;
            border-radius: 30px;
            border: 1px solid rgba(233, 69, 96, 0.3);
        }

        .user-name i {
            color: var(--highlight);
        }

        .currency-badge {
            background: var(--highlight);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
        }

        .page-title-section {
            background: rgba(255, 255, 255, 0.05);
            padding: 30px 0;
            border-bottom: 1px solid rgba(233, 69, 96, 0.2);
            margin-bottom: 30px;
        }

        .title-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
        }

        .title-container h1 {
            font-size: 32px;
            color: var(--light);
            margin-bottom: 10px;
        }

        .title-container h1 i {
            color: var(--highlight);
            margin-right: 10px;
        }

        .title-container p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 16px;
        }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px 30px;
        }

        .booking-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        @media (max-width: 968px) {
            .booking-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .card-title {
            font-size: 22px;
            color: var(--highlight);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding-bottom: 15px;
        }

        .card-title i {
            color: var(--highlight);
        }

        .room-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .detail-box {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px 15px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }

        .detail-box:hover {
            background: rgba(233, 69, 96, 0.1);
            transform: translateY(-3px);
            border-color: var(--highlight);
        }

        .detail-box .label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .detail-box .value {
            font-size: 20px;
            font-weight: bold;
            color: var(--light);
        }

        .detail-box .value i {
            color: var(--success);
        }

        .existing-bookings {
            margin-top: 20px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .existing-bookings h3 {
            font-size: 16px;
            color: var(--info);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .booking-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .booking-item {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 6px;
            margin-bottom: 5px;
            font-size: 12px;
            border-left: 3px solid var(--danger);
        }

        .booking-item .date {
            color: var(--info);
            font-weight: 600;
            margin-right: 10px;
        }

        .booking-item .time {
            color: var(--light);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--light);
            font-weight: 500;
            font-size: 14px;
        }

        .form-group label i {
            color: var(--highlight);
            margin-right: 5px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 15px;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--highlight);
            background: rgba(255, 255, 255, 0.15);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .time-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 10px;
        }

        .time-row small {
            display: block;
            margin-top: 5px;
            color: rgba(255, 255, 255, 0.5);
        }

        .duration-badge {
            background: rgba(233, 69, 96, 0.15);
            border: 1px solid rgba(233, 69, 96, 0.3);
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: var(--highlight);
            margin: 15px 0;
        }

        .summary-card {
            background: rgba(26, 26, 46, 0.95);
            border: 1px solid var(--highlight);
            position: sticky;
            top: 100px;
        }

        .summary-card .card-title {
            color: var(--highlight);
            border-bottom-color: rgba(233, 69, 96, 0.3);
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 16px;
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            padding: 20px 0 0;
            font-size: 22px;
            font-weight: bold;
            color: var(--highlight);
            margin-top: 10px;
            border-top: 2px solid rgba(233, 69, 96, 0.3);
        }

        .info-box {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-box h3 {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--highlight);
            font-size: 16px;
            margin-bottom: 10px;
        }

        .info-box p {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            font-size: 14px;
        }

        .confirm-btn {
            width: 100%;
            padding: 16px 28px;
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            border-radius: 40px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.3);
            letter-spacing: 0.5px;
        }

        .confirm-btn:hover:not(:disabled) {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(233, 69, 96, 0.5);
        }

        .confirm-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .error-message {
            background: rgba(214, 48, 49, 0.2);
            border: 1px solid var(--danger);
            border-radius: 30px;
            padding: 15px 25px;
            margin-bottom: 25px;
            color: #ff6b6b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message i {
            font-size: 20px;
            color: var(--danger);
        }

        .success-message {
            background: rgba(0, 184, 148, 0.2);
            border: 1px solid var(--success);
            border-radius: 30px;
            padding: 15px 25px;
            margin-bottom: 25px;
            color: var(--success);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .availability-status {
            margin: 10px 0;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
        }

        .availability-status.available {
            background: rgba(0, 184, 148, 0.1);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .availability-status.unavailable {
            background: rgba(214, 48, 49, 0.1);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .note-hint {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
            margin-top: 5px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .fa-spinner {
            animation: spin 1s linear infinite;
        }

        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .user-profile {
                width: 100%;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .booking-grid {
                gap: 20px;
            }
            
            .card {
                padding: 20px;
            }
            
            .room-details-grid {
                grid-template-columns: 1fr;
            }
            
            .back-dashboard-btn {
                padding: 10px 20px;
                font-size: 13px;
            }
        }
    </style>
</head>
<body>

<header class="main-header">
    <div class="header-container">
        <div class="logo-area">
            <span class="logo">Sirene KTV</span>
        </div>
        
        <div class="user-profile">
            <span class="user-name">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($user_name); ?>
                <span class="currency-badge"><?php echo $currency_symbol; ?></span>
            </span>
            <a href="dashboard.php?tab=rooms" class="back-dashboard-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</header>

<section class="page-title-section">
    <div class="title-container">
        <h1><i class="fas fa-calendar-plus"></i> Book Your KTV Room</h1>
        <p>Complete the booking details below to reserve your room</p>
    </div>
</section>

<main class="main-content">
    
    <?php if (isset($error)): ?>
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="success-message">
            <i class="fas fa-check-circle"></i>
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
    <?php endif; ?>

    <div class="booking-grid">
        <!-- Left Column -->
        <div>
            <!-- Room Details Card -->
            <div class="card" style="margin-bottom: 30px;">
                <h2 class="card-title">
                    <i class="fas fa-door-open"></i>
                    Room Details
                </h2>
                
                <div class="room-details-grid">
                    <div class="detail-box">
                        <div class="label">Room Name</div>
                        <div class="value"><?php echo htmlspecialchars($room['room_name']); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="label">Capacity</div>
                        <div class="value"><?php echo $room['capcity']; ?> Persons</div>
                    </div>
                    <div class="detail-box">
                        <div class="label">Price per Hour</div>
                        <div class="value"><?php echo $currency_symbol; ?><?php echo number_format($room['price_hr'], 2); ?></div>
                    </div>
                    <div class="detail-box">
                        <div class="label">Status</div>
                        <div class="value"><i class="fas fa-check-circle" style="color: var(--success);"></i> Available</div>
                    </div>
                </div>

                <!-- Existing Bookings Display -->
                <?php if (!empty($existing_bookings)): ?>
                <div class="existing-bookings">
                    <h3><i class="fas fa-calendar-alt"></i> Already Booked Slots</h3>
                    <div class="booking-list">
                        <?php foreach ($existing_bookings as $booking): 
                            $booking_date = new DateTime($booking['booking_date']);
                            $today = new DateTime();
                            $tomorrow = new DateTime('tomorrow');
                            
                            if ($booking_date->format('Y-m-d') == $today->format('Y-m-d')) {
                                $date_display = 'Today';
                            } elseif ($booking_date->format('Y-m-d') == $tomorrow->format('Y-m-d')) {
                                $date_display = 'Tomorrow';
                            } else {
                                $date_display = $booking_date->format('M j');
                            }
                        ?>
                            <div class="booking-item">
                                <span class="date"><?php echo $date_display; ?></span>
                                <span class="time">
                                    <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                    <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="color: rgba(255,255,255,0.5); font-size: 11px; margin-top: 8px;">
                        <i class="fas fa-info-circle"></i> These time slots are already taken
                    </p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Booking Form Card -->
            <div class="card">
                <h2 class="card-title">
                    <i class="fas fa-calendar-alt"></i>
                    Booking Details
                </h2>
                
                <form method="POST" action="" id="bookingForm">
                    <div class="form-group">
                        <label for="booking_date">
                            <i class="far fa-calendar"></i> Booking Date
                        </label>
                        <input type="date" id="booking_date" name="booking_date" 
                               class="form-control"
                               min="<?php echo $min_date; ?>" 
                               max="<?php echo $max_date; ?>"
                               value="<?php echo $default_date; ?>" 
                               required>
                        <small style="color: rgba(255,255,255,0.5);">Select your preferred date</small>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="far fa-clock"></i> Time Slot</label>
                        <div class="time-row">
                            <div>
                                <input type="time" id="start_time" name="start_time" 
                                       class="form-control"
                                       value="18:00" min="10:00" max="23:00" required>
                                <small>Start Time</small>
                            </div>
                            <div>
                                <input type="time" id="end_time" name="end_time" 
                                       class="form-control"
                                       value="20:00" min="11:00" max="02:00" required>
                                <small>End Time</small>
                            </div>
                        </div>
                        <div id="availabilityStatus" class="availability-status"></div>
                        <div id="hoursDisplay" class="duration-badge">
                            Duration: 2.0 hours
                        </div>
                    </div>
                    
                    <!-- Notes Field - Still visible for user input but NOT saved to database -->
                    <div class="form-group">
                        <label for="notes">
                            <i class="far fa-sticky-note"></i> Special Requests or Notes
                        </label>
                        <textarea id="notes" name="notes" class="form-control" 
                                  rows="4" placeholder="Any special requests, dietary restrictions, or notes for your booking..."></textarea>
                        <div class="note-hint">
                            <i class="fas fa-info-circle"></i> Let us know if you have any special requirements
                            <br><small>(Note: These notes are for your reference only and not stored in our system)</small>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right Column - Summary Card -->
        <div>
            <div class="card summary-card">
                <h2 class="card-title">
                    <i class="fas fa-receipt"></i>
                    Booking Summary
                </h2>
                
                <div class="summary-item">
                    <span>Room (<span id="summaryHours">2.0</span> hours)</span>
                    <span><?php echo $currency_symbol; ?><span id="roomPrice"><?php echo number_format($room['price_hr'] * 2, 2); ?></span></span>
                </div>
                
                <div class="summary-item">
                    <span>Subtotal</span>
                    <span><?php echo $currency_symbol; ?><span id="subtotal"><?php echo number_format($room['price_hr'] * 2, 2); ?></span></span>
                </div>
                
                <div class="summary-item">
                    <span>Tax (10%)</span>
                    <span><?php echo $currency_symbol; ?><span id="taxAmount"><?php echo number_format($room['price_hr'] * 2 * 0.1, 2); ?></span></span>
                </div>
                
                <div class="summary-total">
                    <span>Total Amount</span>
                    <span><?php echo $currency_symbol; ?><span id="totalAmount"><?php echo number_format($room['price_hr'] * 2 * 1.1, 2); ?></span></span>
                </div>
                
                <div class="info-box">
                    <h3><i class="fas fa-check-circle" style="color: var(--success);"></i> Instant Confirmation</h3>
                    <p>Your booking will be <strong>confirmed immediately</strong> without waiting for approval. Payment can be made at the venue when you arrive.</p>
                </div>
                
                <button type="submit" class="confirm-btn" id="submitBtn" form="bookingForm">
                    <i class="fas fa-check-circle"></i> Confirm Booking Now
                </button>
            </div>
        </div>
    </div>
</main>

<script>
    const roomPricePerHour = <?php echo $room['price_hr']; ?>;
    const currencySymbol = '<?php echo $currency_symbol; ?>';
    
    // Existing bookings data from PHP
    const existingBookings = <?php echo json_encode($unavailable_slots); ?>;
    
    function calculateHours() {
        const date = document.getElementById('booking_date').value;
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        
        if (date && startTime && endTime) {
            const start = new Date(`${date}T${startTime}`);
            const end = new Date(`${date}T${endTime}`);
            
            // Handle overnight bookings (after midnight)
            if (end < start) {
                end.setDate(end.getDate() + 1);
            }
            
            const hours = (end - start) / (1000 * 60 * 60);
            
            if (hours > 0 && hours <= 24) {
                document.getElementById('hoursDisplay').textContent = `Duration: ${hours.toFixed(1)} hours`;
                document.getElementById('summaryHours').textContent = hours.toFixed(1);
                updateSummary(hours);
                checkAvailability(date, startTime, endTime);
            }
        }
    }
    
    function updateSummary(hours) {
        const roomAmount = roomPricePerHour * hours;
        document.getElementById('roomPrice').textContent = roomAmount.toFixed(2);
        
        const subtotal = roomAmount;
        const taxAmount = subtotal * 0.10;
        const totalAmount = subtotal + taxAmount;
        
        document.getElementById('subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('taxAmount').textContent = taxAmount.toFixed(2);
        document.getElementById('totalAmount').textContent = totalAmount.toFixed(2);
    }
    
    function checkAvailability(date, startTime, endTime) {
        const availabilityDiv = document.getElementById('availabilityStatus');
        const submitBtn = document.getElementById('submitBtn');
        
        // Check against existing bookings
        if (existingBookings[date]) {
            const start = new Date(`1970-01-01T${startTime}`);
            const end = new Date(`1970-01-01T${endTime}`);
            
            let isAvailable = true;
            
            for (const booking of existingBookings[date]) {
                const bookingStart = new Date(`1970-01-01T${booking.start}`);
                const bookingEnd = new Date(`1970-01-01T${booking.end}`);
                
                // Check for overlap
                if ((start >= bookingStart && start < bookingEnd) ||
                    (end > bookingStart && end <= bookingEnd) ||
                    (start <= bookingStart && end >= bookingEnd)) {
                    isAvailable = false;
                    break;
                }
            }
            
            if (!isAvailable) {
                availabilityDiv.className = 'availability-status unavailable';
                availabilityDiv.innerHTML = `<i class="fas fa-times-circle"></i> This time slot is already booked. Please choose a different time.`;
                submitBtn.disabled = true;
                return false;
            }
        }
        
        availabilityDiv.className = 'availability-status available';
        availabilityDiv.innerHTML = `<i class="fas fa-check-circle"></i> This time slot is available! Click confirm to book instantly.`;
        submitBtn.disabled = false;
        return true;
    }
    
    // Event listeners
    document.getElementById('booking_date').addEventListener('change', calculateHours);
    document.getElementById('start_time').addEventListener('change', calculateHours);
    document.getElementById('end_time').addEventListener('change', calculateHours);
    
    // Initialize
    calculateHours();
    
    // Form validation
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        const date = document.getElementById('booking_date').value;
        
        if (!startTime || !endTime) {
            e.preventDefault();
            alert('Please select both start and end times');
            return false;
        }
        
        const start = new Date(`${date}T${startTime}`);
        const end = new Date(`${date}T${endTime}`);
        if (end < start) end.setDate(end.getDate() + 1);
        
        const hours = (end - start) / (1000 * 60 * 60);
        
        if (hours < 1) {
            e.preventDefault();
            alert('Minimum booking duration is 1 hour');
            return false;
        }
        
        if (hours > 6) {
            e.preventDefault();
            alert('Maximum booking duration is 6 hours');
            return false;
        }
        
        // Double-check availability
        if (!checkAvailability(date, startTime, endTime)) {
            e.preventDefault();
            alert('This time slot is not available. Please choose a different time.');
            return false;
        }
        
        // Disable button to prevent double submission
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Your Booking...';
    });

    // Log that page loaded successfully
    console.log('Book-room.php loaded successfully for room ID: <?php echo $room_id; ?>');
</script>

<!-- Debug info (remove in production) -->
<div style="position: fixed; bottom: 10px; left: 10px; background: rgba(0,0,0,0.8); padding: 5px 10px; border-radius: 5px; font-size: 11px; z-index: 9999;">
    Room ID: <?php echo $room_id; ?> | Room Name: <?php echo htmlspecialchars($room['room_name']); ?> | Price: <?php echo $currency_symbol; ?><?php echo $room['price_hr']; ?>
</div>

</body>
</html>