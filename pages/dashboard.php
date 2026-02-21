<?php
session_start();
include "../db.php";

// Add these cache control headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Helper function to get currency from country code
function getCurrencyFromCountry($country_code) {
    $currencies = [
        '+1' => ['symbol' => '$', 'code' => 'USD', 'name' => 'US Dollar'],
        '+44' => ['symbol' => '£', 'code' => 'GBP', 'name' => 'British Pound'],
        '+61' => ['symbol' => 'A$', 'code' => 'AUD', 'name' => 'Australian Dollar'],
        '+65' => ['symbol' => 'S$', 'code' => 'SGD', 'name' => 'Singapore Dollar'],
        '+60' => ['symbol' => 'RM', 'code' => 'MYR', 'name' => 'Malaysian Ringgit'],
        '+63' => ['symbol' => '₱', 'code' => 'PHP', 'name' => 'Philippine Peso'],
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

$name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Get user's currency from session or database
if (isset($_SESSION['user_currency'])) {
    $currency = $_SESSION['user_currency'];
} else {
    // Fetch from database if not in session
    $user_query = $conn->query("SELECT country_code FROM user_tbl WHERE id = $user_id");
    if ($user_query && $user_query->num_rows > 0) {
        $user_data = $user_query->fetch_assoc();
        $country_code = $user_data['country_code'] ?? '+1';
        $currency = getCurrencyFromCountry($country_code);
        $_SESSION['user_currency'] = $currency;
        $_SESSION['user_country_code'] = $country_code;
    } else {
        $currency = ['symbol' => '$', 'code' => 'USD', 'name' => 'US Dollar'];
    }
}

$currency_symbol = $currency['symbol'];
$currency_code = $currency['code'];

// Check room availability function
function isRoomAvailable($conn, $room_id, $booking_date, $start_time, $end_time, $exclude_booking_id = null) {
    $query = "SELECT * FROM booking 
              WHERE r_id = $room_id 
              AND booking_date = '$booking_date'
              AND status NOT IN ('cancelled', 'rejected', 'completed')
              AND (
                  (start_time <= '$start_time' AND end_time > '$start_time')
                  OR (start_time < '$end_time' AND end_time >= '$end_time')
                  OR ('$start_time' <= start_time AND '$end_time' > start_time)
              )";
    
    if ($exclude_booking_id) {
        $query .= " AND b_id != $exclude_booking_id";
    }
    
    $result = $conn->query($query);
    return $result->num_rows === 0;
}

// SAFE DATE FORMATTING FUNCTION
function formatDisplayDate($date_string) {
    if (empty($date_string)) return 'No date';
    
    try {
        $date = new DateTime($date_string);
        $today = new DateTime('today');
        $tomorrow = new DateTime('tomorrow');
        $yesterday = new DateTime('yesterday');
        
        if ($date->format('Y-m-d') == $today->format('Y-m-d')) {
            return 'Today';
        } elseif ($date->format('Y-m-d') == $tomorrow->format('Y-m-d')) {
            return 'Tomorrow';
        } elseif ($date->format('Y-m-d') == $yesterday->format('Y-m-d')) {
            return 'Yesterday';
        } else {
            return $date->format('M j, Y');
        }
    } catch (Exception $e) {
        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            return 'Invalid date';
        }
        
        $today_ts = strtotime('today');
        $tomorrow_ts = strtotime('tomorrow');
        $yesterday_ts = strtotime('yesterday');
        
        if (date('Y-m-d', $timestamp) == date('Y-m-d', $today_ts)) {
            return 'Today';
        } elseif (date('Y-m-d', $timestamp) == date('Y-m-d', $tomorrow_ts)) {
            return 'Tomorrow';
        } elseif (date('Y-m-d', $timestamp) == date('Y-m-d', $yesterday_ts)) {
            return 'Yesterday';
        } else {
            return date('M j, Y', $timestamp);
        }
    }
}

// SAFE TIME FORMATTING FUNCTION
function formatDisplayTime($time_string) {
    if (empty($time_string)) return '';
    $timestamp = strtotime($time_string);
    return ($timestamp !== false) ? date('g:i A', $timestamp) : $time_string;
}

// Check if user has active bookings (now allows up to 2)
$active_bookings_count = 0;
$active_bookings = [];
$active_booking_ids = [];

$active_bookings_query = $conn->query("
    SELECT b.*, r.room_name 
    FROM booking b 
    LEFT JOIN room r ON b.r_id = r.r_id 
    WHERE b.u_id = $user_id 
    AND b.status NOT IN ('cancelled', 'completed', 'rejected') 
    AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date ASC, b.start_time ASC
");

if ($active_bookings_query && $active_bookings_query->num_rows > 0) {
    $active_bookings_count = $active_bookings_query->num_rows;
    while ($booking = $active_bookings_query->fetch_assoc()) {
        $active_bookings[] = $booking;
        $active_booking_ids[] = $booking['b_id'];
    }
}

// Check if user has reached max bookings (2)
$has_reached_max_bookings = ($active_bookings_count >= 2);

// Check active tab from URL or default to rooms
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'rooms';

// Fetch ALL rooms (not just available ones) to show all rooms with their booking status
$rooms = [];
$total_rooms_count = 0;

$rooms_query = $conn->query("SELECT * FROM room ORDER BY r_id DESC");
if ($rooms_query) {
    $rooms = $rooms_query->fetch_all(MYSQLI_ASSOC);
    $total_rooms_count = count($rooms);
}

// Get ALL bookings for each room (for display) - WITHOUT USER NAMES FOR PRIVACY
$room_bookings = [];
$all_bookings = [];

$bookings_query = $conn->query("
    SELECT b.*, r.room_name
    FROM booking b 
    JOIN room r ON b.r_id = r.r_id 
    WHERE b.status IN ('confirmed', 'pending')
    ORDER BY b.booking_date ASC, b.start_time ASC
");

if ($bookings_query) {
    while ($booking = $bookings_query->fetch_assoc()) {
        $room_id = $booking['r_id'];
        if (!isset($all_bookings[$room_id])) {
            $all_bookings[$room_id] = [];
        }
        $all_bookings[$room_id][] = $booking;
    }
}

// Group by room for display
foreach ($rooms as $room) {
    $room_id = $room['r_id'];
    $room_bookings[$room_id] = $all_bookings[$room_id] ?? [];
}

// Calculate room stats
$available_rooms_count = 0;
$total_capacity = 0;
$current_time = date('H:i:s');
$current_date = date('Y-m-d');

foreach ($rooms as $room) {
    // Count available rooms (those not currently occupied)
    $room_id = $room['r_id'];
    
    $is_occupied = false;
    if (isset($room_bookings[$room_id])) {
        foreach ($room_bookings[$room_id] as $booking) {
            if ($booking['booking_date'] == $current_date && 
                $booking['start_time'] <= $current_time && 
                $booking['end_time'] > $current_time) {
                $is_occupied = true;
                break;
            }
        }
    }
    
    if (!$is_occupied) {
        $available_rooms_count++;
    }
    $total_capacity += $room['capcity'];
}
$avg_capacity = $total_rooms_count > 0 ? round($total_capacity / $total_rooms_count) : 0;

// Get user's bookings count
$user_bookings_query = $conn->query("SELECT COUNT(*) as count FROM booking WHERE u_id = $user_id");
$user_bookings_count = 0;
if ($user_bookings_query) {
    $user_bookings_count = $user_bookings_query->fetch_assoc()['count'];
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Check room availability AJAX
    if ($_POST['action'] === 'check_availability') {
        $room_id = intval($_POST['room_id']);
        $booking_date = $_POST['booking_date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        
        $is_available = isRoomAvailable($conn, $room_id, $booking_date, $start_time, $end_time);
        
        $conflicts = [];
        if (!$is_available) {
            $conflict_query = $conn->query("
                SELECT * FROM booking
                WHERE r_id = $room_id 
                AND booking_date = '$booking_date'
                AND status NOT IN ('cancelled', 'rejected', 'completed')
                AND (
                    (start_time <= '$start_time' AND end_time > '$start_time')
                    OR (start_time < '$end_time' AND end_time >= '$end_time')
                    OR ('$start_time' <= start_time AND '$end_time' > start_time)
                )
            ");
            
            if ($conflict_query) {
                while ($conflict = $conflict_query->fetch_assoc()) {
                    $conflicts[] = [
                        'start_time' => date('g:i A', strtotime($conflict['start_time'])),
                        'end_time' => date('g:i A', strtotime($conflict['end_time']))
                    ];
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'is_available' => $is_available,
            'conflicts' => $conflicts
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sirene KTV Dashboard</title>
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
        }

        header {
            background: rgba(10, 10, 20, 0.95);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            border-bottom: 3px solid var(--highlight);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left h1 {
            font-size: 28px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 5px;
        }

        .header-left p {
            color: #aaa;
            font-size: 14px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .welcome-message {
            background: var(--accent);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .currency-badge {
            background: var(--highlight);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .my-bookings-btn, .logout-btn {
            background: linear-gradient(135deg, var(--accent), var(--highlight));
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .my-bookings-btn:hover, .logout-btn:hover {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 69, 96, 0.4);
        }

        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 100px);
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .content-section {
            display: none;
            animation: fadeIn 0.5s;
        }

        .content-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .active-booking-banner {
            background: linear-gradient(135deg, var(--accent), #0f3460);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid var(--highlight);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .active-booking-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
        }

        .banner-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .banner-header i {
            font-size: 30px;
            color: var(--highlight);
        }

        .banner-header h2 {
            font-size: 22px;
            color: var(--light);
        }

        .booking-limit-badge {
            background: var(--warning);
            color: #333;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 15px;
        }

        .banner-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 10px;
        }

        .banner-detail {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .banner-detail i {
            color: var(--highlight);
            font-size: 16px;
            width: 24px;
        }

        .banner-detail-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .banner-detail-value {
            font-weight: 600;
            color: var(--light);
            font-size: 14px;
        }

        .banner-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .banner-btn {
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            text-decoration: none;
        }

        .banner-btn-primary {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
        }

        .banner-btn-primary:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-2px);
        }

        .banner-btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .banner-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .section-header h2 {
            font-size: 24px;
            color: var(--light);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .section-header h2 i {
            color: var(--highlight);
        }

        .section-count {
            background: var(--accent);
            color: var(--light);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
        }

        .nav-tab {
            flex: 1;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            color: #aaa;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 16px;
        }

        .nav-tab:hover {
            background: rgba(233, 69, 96, 0.1);
            color: var(--highlight);
        }

        .nav-tab.active {
            background: var(--highlight);
            color: white;
        }

        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border-color: var(--highlight);
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--light);
            margin-bottom: 5px;
        }

        .card-price {
            font-size: 24px;
            color: var(--highlight);
            font-weight: 700;
        }

        .card-description {
            padding: 20px;
            color: #aaa;
            font-size: 14px;
            line-height: 1.6;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-details {
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-label {
            font-size: 12px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 16px;
            color: var(--light);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value i {
            color: var(--highlight);
            font-size: 14px;
        }

        /* Room Schedule Display - PRIVACY FOCUSED (no usernames) */
        .room-schedule {
            padding: 0 20px;
            margin: 15px 0;
        }

        .schedule-title {
            font-size: 13px;
            color: #aaa;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .schedule-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px 12px;
            border-radius: 8px;
            margin-bottom: 6px;
            border-left: 3px solid var(--info);
            font-size: 12px;
            transition: all 0.2s;
            position: relative;
        }

        .schedule-item:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .schedule-item.booked {
            border-left-color: var(--danger);
            background: rgba(214, 48, 49, 0.1);
        }

        .schedule-item.current-user {
            border-left-color: var(--highlight);
            background: rgba(233, 69, 96, 0.15);
        }

        .schedule-item.current-user:hover {
            background: rgba(233, 69, 96, 0.2);
        }

        .schedule-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 5px;
        }

        .schedule-date {
            color: var(--info);
            font-size: 11px;
            font-weight: 600;
            background: rgba(9, 132, 227, 0.1);
            padding: 2px 6px;
            border-radius: 12px;
            display: inline-block;
            margin-right: 8px;
        }

        .schedule-time {
            font-weight: 600;
            color: var(--light);
        }

        .booked-tag {
            background: var(--danger);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            margin-left: 8px;
            display: inline-block;
            text-transform: uppercase;
        }

        .your-booking-tag {
            background: var(--highlight);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: 600;
            margin-left: 8px;
            display: inline-block;
        }

        .available-all-day {
            color: rgba(255, 255, 255, 0.4);
            font-size: 12px;
            padding: 12px;
            text-align: center;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            border: 1px dashed rgba(255, 255, 255, 0.1);
        }

        .more-bookings {
            text-align: center;
            margin-top: 8px;
            font-size: 11px;
            color: var(--info);
            background: rgba(9, 132, 227, 0.1);
            padding: 6px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .more-bookings:hover {
            background: rgba(9, 132, 227, 0.2);
        }

        .card-footer {
            padding: 0 20px 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            width: fit-content;
        }

        .status-available {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .action-btn {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 15px;
            text-decoration: none;
            width: 100%;
            border: 2px solid transparent;
            position: relative;
            z-index: 10;
        }

        .action-btn:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 69, 96, 0.4);
            border-color: white;
        }

        .action-btn:active {
            transform: translateY(0);
        }

        .action-btn.disabled {
            background: rgba(255, 255, 255, 0.1);
            cursor: not-allowed;
            opacity: 0.5;
            pointer-events: none;
        }

        .sidebar {
            width: 300px;
            background: rgba(22, 33, 62, 0.9);
            padding: 25px 15px;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
        }

        .sidebar-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header i {
            color: var(--highlight);
            font-size: 20px;
        }

        .sidebar-header h3 {
            font-size: 18px;
            color: var(--light);
        }

        .quick-actions {
            list-style: none;
        }

        .action-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            margin: 8px 0;
            color: var(--light);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            font-size: 14px;
        }

        .action-item:hover {
            background: rgba(233, 69, 96, 0.1);
            transform: translateX(5px);
        }

        .action-icon {
            width: 32px;
            height: 32px;
            background: rgba(233, 69, 96, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-icon i {
            color: var(--highlight);
            font-size: 14px;
        }

        .action-text {
            flex: 1;
        }

        .action-item i.fa-chevron-right {
            color: #aaa;
            font-size: 11px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-icon {
            width: 35px;
            height: 35px;
            background: rgba(233, 69, 96, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 8px;
        }

        .stat-icon i {
            color: var(--highlight);
            font-size: 16px;
        }

        .stat-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--light);
            margin: 5px 0;
        }

        .stat-label {
            font-size: 11px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            z-index: 9999;
            animation: slideIn 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .notification.success {
            background: var(--success);
        }

        .notification.info {
            background: var(--info);
        }

        .notification.error {
            background: var(--danger);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .info-note {
            background: rgba(233, 69, 96, 0.1);
            border: 1px solid rgba(233, 69, 96, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
        }

        .info-note i {
            color: var(--highlight);
            margin-right: 8px;
        }

        .booking-badge {
            display: inline-block;
            background: var(--highlight);
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            margin-left: 10px;
        }

        .no-items {
            text-align: center;
            padding: 50px 20px;
            color: #aaa;
        }

        .no-items i {
            font-size: 60px;
            color: rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
        }

        .no-items h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--light);
        }

        .no-items p {
            font-size: 14px;
            margin-bottom: 20px;
        }

        .booking-limit-message {
            background: rgba(253, 203, 110, 0.1);
            border: 1px solid var(--warning);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: var(--warning);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .booking-limit-message i {
            font-size: 20px;
        }

        .schedule-tooltip {
            position: relative;
            display: inline-block;
        }

        .schedule-tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: rgba(0, 0, 0, 0.9);
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 11px;
            border: 1px solid var(--highlight);
        }

        .schedule-tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge.available {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid var(--success);
        }

        .badge.occupied {
            background: rgba(214, 48, 49, 0.2);
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .privacy-note {
            background: rgba(9, 132, 227, 0.1);
            border: 1px solid var(--info);
            border-radius: 10px;
            padding: 12px;
            margin: 10px 0 20px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .privacy-note i {
            color: var(--info);
        }

        .booking-restricted-btn {
            background: linear-gradient(135deg, var(--warning), #e17055);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: not-allowed;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 15px;
            opacity: 0.8;
            width: 100%;
        }

        .booking-restricted-btn:hover {
            transform: none;
            box-shadow: none;
        }

        @media (max-width: 1024px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .sidebar-card {
                margin-bottom: 0;
            }
            
            .cards-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-tabs {
                flex-direction: column;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-right {
                flex-direction: column;
                width: 100%;
            }
            
            .banner-actions {
                flex-direction: column;
            }
            
            .banner-details {
                grid-template-columns: 1fr;
            }
            
            .schedule-item-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .stats-cards, .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .section-count {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-left">
        <h1><i class="fas fa-microphone-alt"></i> Sirene KTV Dashboard</h1>
        <p>Your Karaoke Experience</p>
    </div>
    <div class="header-right">
        <div class="welcome-message">
            <i class="fas fa-user-circle"></i>
            <?php echo htmlspecialchars($name); ?> (<?php echo ucfirst($role); ?>)
            <span class="currency-badge"><?php echo $currency_symbol; ?> <?php echo $currency_code; ?></span>
        </div>
        
        <a href="my-bookings.php" class="my-bookings-btn">
            <i class="fas fa-calendar-check"></i> My Bookings (<?php echo $user_bookings_count; ?>)
        </a>
        
        <form action="logout.php" method="post" style="display: inline;">
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
</header>

<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-card">
            <div class="sidebar-header">
                <i class="fas fa-chart-bar"></i>
                <h3>Quick Stats</h3>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-door-closed"></i></div>
                    <div class="stat-value"><?php echo $available_rooms_count; ?></div>
                    <div class="stat-label">Available Now</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo $avg_capacity; ?></div>
                    <div class="stat-label">Avg Capacity</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-door-open"></i></div>
                    <div class="stat-value"><?php echo $total_rooms_count; ?></div>
                    <div class="stat-label">Total Rooms</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="stat-value"><?php echo $user_bookings_count; ?></div>
                    <div class="stat-label">Your Bookings</div>
                </div>
            </div>
        </div>

        <div class="sidebar-card">
            <div class="sidebar-header">
                <i class="fas fa-bolt"></i>
                <h3>Quick Actions</h3>
            </div>
            <ul class="quick-actions">
                <li class="action-item" onclick="switchTab('rooms')">
                    <div class="action-icon"><i class="fas fa-door-closed"></i></div>
                    <div class="action-text">Browse Rooms</div>
                    <i class="fas fa-chevron-right"></i>
                </li>
                <?php if (!empty($active_bookings)): ?>
                <li class="action-item" onclick="window.location.href='food-order.php'">
                    <div class="action-icon"><i class="fas fa-utensils"></i></div>
                    <div class="action-text">Order Food</div>
                    <i class="fas fa-chevron-right"></i>
                </li>
                <?php endif; ?>
                <li class="action-item" onclick="window.location.href='my-bookings.php'">
                    <div class="action-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="action-text">My Bookings</div>
                    <i class="fas fa-chevron-right"></i>
                </li>
            </ul>
        </div>
        
        <!-- Show active bookings in sidebar (up to 2) -->
        <?php if (!empty($active_bookings)): ?>
            <div class="sidebar-card" style="border-left: 4px solid var(--highlight);">
                <div class="sidebar-header">
                    <i class="fas fa-clock"></i>
                    <h3>Your Active Bookings (<?php echo $active_bookings_count; ?>/2)</h3>
                </div>
                
                <?php foreach ($active_bookings as $index => $booking): ?>
                    <div style="margin-bottom: <?php echo $index < count($active_bookings) - 1 ? '15px' : '0'; ?>; padding-bottom: <?php echo $index < count($active_bookings) - 1 ? '15px' : '0'; ?>; border-bottom: <?php echo $index < count($active_bookings) - 1 ? '1px solid rgba(255,255,255,0.1)' : 'none'; ?>;">
                        <p style="color: #aaa; font-size: 12px; margin-bottom: 5px;">Room <?php echo $index + 1; ?></p>
                        <p style="font-weight: 600; font-size: 14px; margin-bottom: 5px;"><?php echo htmlspecialchars($booking['room_name']); ?></p>
                        <p style="font-size: 12px; margin-bottom: 5px;"><?php echo formatDisplayDate($booking['booking_date']); ?></p>
                        <p style="font-size: 12px; margin-bottom: 8px;"><?php echo formatDisplayTime($booking['start_time']); ?> - <?php echo formatDisplayTime($booking['end_time']); ?></p>
                        <a href="booking-details.php?id=<?php echo $booking['b_id']; ?>" class="action-item" style="padding: 5px 0; margin: 0;">
                            <div class="action-icon" style="width: 25px; height: 25px;"><i class="fas fa-eye" style="font-size: 12px;"></i></div>
                            <div class="action-text" style="font-size: 12px;">View Details</div>
                            <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                        </a>
                    </div>
                <?php endforeach; ?>
                
                <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <a href="food-order.php" class="action-item" style="padding: 8px 0;">
                        <div class="action-icon" style="width: 30px; height: 30px;"><i class="fas fa-pizza-slice"></i></div>
                        <div class="action-text">Order Food for Your Booking</div>
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Active Bookings Banner - Show if user has any active bookings -->
        <?php if (!empty($active_bookings)): ?>
            <div class="active-booking-banner">
                <div class="banner-header">
                    <i class="fas fa-calendar-check"></i>
                    <h2>Your Active Bookings</h2>
                    <span class="booking-limit-badge"><?php echo $active_bookings_count; ?>/2 Bookings Used</span>
                </div>
                
                <div class="banner-details">
                    <?php foreach ($active_bookings as $booking): ?>
                        <div class="banner-detail">
                            <i class="fas fa-door-closed"></i>
                            <div>
                                <div class="banner-detail-label"><?php echo htmlspecialchars($booking['room_name']); ?></div>
                                <div class="banner-detail-value">
                                    <?php echo formatDisplayDate($booking['booking_date']); ?>, <?php echo formatDisplayTime($booking['start_time']); ?> - <?php echo formatDisplayTime($booking['end_time']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if ($has_reached_max_bookings): ?>
                        <div class="banner-detail" style="background: rgba(253, 203, 110, 0.1); padding: 10px; border-radius: 8px;">
                            <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
                            <div>
                                <div class="banner-detail-label" style="color: var(--warning);">Maximum Bookings Reached</div>
                                <div class="banner-detail-value" style="color: var(--warning);">You cannot book more rooms until one is completed or cancelled</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($active_bookings)): ?>
                <p style="color: rgba(255,255,255,0.8); margin-bottom: 20px; font-size: 14px;">
                    <i class="fas fa-info-circle"></i> 
                    <strong>Order Food & Drinks:</strong> You can order food and drinks for delivery to your room during your booking.
                </p>
                <?php endif; ?>
                
                <div class="banner-actions">
                    <a href="my-bookings.php" class="banner-btn banner-btn-primary">
                        <i class="fas fa-list"></i> View All Bookings
                    </a>
                    <?php if (!empty($active_bookings)): ?>
                    <a href="food-order.php" class="banner-btn banner-btn-secondary">
                        <i class="fas fa-utensils"></i> Order Food Now
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Privacy Note - Explaining that only time slots are shown -->
        <div class="privacy-note">
            <i class="fas fa-shield-alt"></i>
            <div>
                <strong>Privacy First:</strong> Only booked time slots are shown. User identities are never displayed. 
                You can see when rooms are booked, but not who booked them.
            </div>
        </div>

        <!-- Show booking limit message if max reached -->
        <?php if ($has_reached_max_bookings): ?>
            <div class="booking-limit-message">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Booking Limit Reached (2/2)</strong><br>
                    You have reached the maximum number of active bookings. Please complete or cancel an existing booking before booking a new room.
                </div>
            </div>
        <?php endif; ?>

        <!-- Rooms Section - SHOWS ALL ROOMS with booking information (no usernames) -->
        <div id="rooms-section" class="content-section active">
            <div class="section-header">
                <h2><i class="fas fa-door-closed"></i> All Rooms</h2>
                <div class="section-count"><?php echo $total_rooms_count; ?> Total | <?php echo $available_rooms_count; ?> Available Now</div>
            </div>
            
            <?php if ($total_rooms_count > 0): ?>
                <div class="cards-grid">
                    <?php 
                    $current_time = date('H:i:s');
                    $current_date = date('Y-m-d');
                    
                    foreach ($rooms as $room): 
                        $room_id = $room['r_id'];
                        
                        // Check if room is currently occupied
                        $is_occupied = false;
                        $occupied_until = '';
                        if (isset($room_bookings[$room_id])) {
                            foreach ($room_bookings[$room_id] as $booking) {
                                if ($booking['booking_date'] == $current_date && 
                                    $booking['start_time'] <= $current_time && 
                                    $booking['end_time'] > $current_time) {
                                    $is_occupied = true;
                                    $occupied_until = $booking['end_time'];
                                    break;
                                }
                            }
                        }
                        
                        $descriptions = [
                            "Perfect for intimate sessions",
                            "Great for small groups",
                            "Ideal for gatherings",
                            "Spacious for parties",
                            "Premium VIP experience"
                        ];
                        $description_index = min($room['capcity'] - 1, 4);
                        $room_description = $descriptions[$description_index] ?? "Perfect for your karaoke session";
                    ?>
                        <div class="card" data-room-id="<?php echo $room['r_id']; ?>">
                            <div class="card-header">
                                <div class="card-name"><?php echo htmlspecialchars($room['room_name']); ?></div>
                                <div class="card-price"><?php echo $currency_symbol; ?><?php echo number_format($room['price_hr'], 2); ?>/hr</div>
                            </div>
                            <p class="card-description"><?php echo $room_description; ?></p>
                            
                            <div class="card-details">
                                <div class="detail-item">
                                    <div class="detail-label">Capacity</div>
                                    <div class="detail-value">
                                        <i class="fas fa-user-friends"></i>
                                        <?php echo $room['capcity']; ?> Persons
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Price/Hour</div>
                                    <div class="detail-value">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <?php echo $currency_symbol; ?><?php echo number_format($room['price_hr'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Room Schedule Display - Shows ALL bookings WITHOUT revealing usernames -->
                            <div class="room-schedule">
                                <div class="schedule-title">
                                    <i class="fas fa-calendar-alt"></i> Booked Time Slots:
                                    <span class="schedule-tooltip">
                                        <i class="fas fa-info-circle" style="color: var(--info); font-size: 12px; margin-left: 5px;"></i>
                                        <span class="tooltiptext">Shows when this room is already booked - Choose a different time! (User identities are private)</span>
                                    </span>
                                </div>
                                <?php 
                                $room_bookings_list = isset($room_bookings[$room_id]) ? $room_bookings[$room_id] : [];
                                
                                if (!empty($room_bookings_list)): 
                                    // Filter to show only future bookings (today and future)
                                    $today = date('Y-m-d');
                                    $current_time_for_filter = date('H:i:s');
                                    $future_bookings = [];
                                    
                                    foreach ($room_bookings_list as $booking) {
                                        // Include if booking date is today or future
                                        if ($booking['booking_date'] >= $today) {
                                            // If it's today, only show if end time is in the future
                                            if ($booking['booking_date'] == $today && $booking['end_time'] < $current_time_for_filter) {
                                                continue; // Skip past bookings for today
                                            }
                                            $future_bookings[] = $booking;
                                        }
                                    }
                                    
                                    // Sort by date and time
                                    usort($future_bookings, function($a, $b) {
                                        if ($a['booking_date'] == $b['booking_date']) {
                                            return strcmp($a['start_time'], $b['start_time']);
                                        }
                                        return strcmp($a['booking_date'], $b['booking_date']);
                                    });
                                    
                                    if (!empty($future_bookings)): 
                                        // Group by date
                                        $grouped_by_date = [];
                                        foreach ($future_bookings as $booking) {
                                            $date = $booking['booking_date'];
                                            if (!isset($grouped_by_date[$date])) {
                                                $grouped_by_date[$date] = [];
                                            }
                                            $grouped_by_date[$date][] = $booking;
                                        }
                                        
                                        // Show next 5 upcoming bookings max
                                        $display_count = 0;
                                        $max_display = 5;
                                        
                                        foreach ($grouped_by_date as $date => $date_bookings): 
                                            foreach ($date_bookings as $booking): 
                                                if ($display_count >= $max_display) continue 2;
                                                $display_count++;
                                                
                                                $is_current_user = ($booking['u_id'] == $user_id);
                                                $date_display = formatDisplayDate($booking['booking_date']);
                                ?>
                                                <div class="schedule-item <?php echo $is_current_user ? 'current-user' : 'booked'; ?>">
                                                    <div class="schedule-item-header">
                                                        <div>
                                                            <span class="schedule-date"><?php echo $date_display; ?></span>
                                                            <span class="schedule-time">
                                                                <?php echo formatDisplayTime($booking['start_time']); ?> - 
                                                                <?php echo formatDisplayTime($booking['end_time']); ?>
                                                            </span>
                                                            <?php if ($is_current_user): ?>
                                                                <span class="your-booking-tag">Your Booking</span>
                                                            <?php else: ?>
                                                                <span class="booked-tag">Booked</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                <?php 
                                            endforeach;
                                        endforeach;
                                        
                                        // Show count of more bookings if any
                                        $total_bookings = count($future_bookings);
                                        if ($total_bookings > $max_display):
                                ?>
                                            <div class="more-bookings" onclick="showAllBookings(<?php echo $room_id; ?>)">
                                                <i class="fas fa-calendar-plus"></i> +<?php echo ($total_bookings - $max_display); ?> more booked slot(s)
                                            </div>
                                <?php 
                                        endif;
                                    else:
                                ?>
                                        <div class="available-all-day">
                                            <i class="fas fa-check-circle" style="color: var(--success); margin-right: 5px;"></i> No upcoming bookings - Available now!
                                        </div>
                                <?php 
                                    endif;
                                else: 
                                ?>
                                    <div class="available-all-day">
                                        <i class="fas fa-check-circle" style="color: var(--success); margin-right: 5px;"></i> No bookings yet - Be the first to book!
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-footer">
                                <?php if ($is_occupied): ?>
                                    <span class="badge occupied">
                                        <i class="fas fa-clock"></i> Currently Occupied until <?php echo formatDisplayTime($occupied_until); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge available">
                                        <i class="fas fa-circle"></i> Available Now
                                    </span>
                                <?php endif; ?>
                                
                                <?php if ($has_reached_max_bookings): ?>
                                    <button class="booking-restricted-btn" onclick="showMaxBookingAlert()">
                                        <i class="fas fa-exclamation-triangle"></i> Max Bookings Reached (2/2)
                                    </button>
                                    <p style="color: rgba(255,255,255,0.6); font-size: 12px; margin-top: 5px; text-align: center;">
                                        <i class="fas fa-info-circle"></i> Complete or cancel an existing booking first
                                    </p>
                                <?php else: ?>
                                    <a href="book-room.php?room_id=<?php echo $room['r_id']; ?>" class="action-btn">
                                        <i class="fas fa-calendar-plus"></i> Book This Room
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-items">
                    <i class="fas fa-door-closed"></i>
                    <h3>No Rooms Available</h3>
                    <p>All rooms are currently occupied. Please check back later or contact support for availability.</p>
                    <button class="action-btn" onclick="contactSupport()" style="max-width: 200px; margin: 0 auto;">
                        <i class="fas fa-headset"></i> Contact Support
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Switch tabs
    function switchTab(tabName) {
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
        
        // Remove active class from all tabs and sections
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Add active class to clicked tab
        document.querySelectorAll('.nav-tab').forEach(tab => {
            if (tab.textContent.toLowerCase().includes(tabName)) {
                tab.classList.add('active');
            }
        });
        
        // Show the selected section
        const section = document.getElementById(tabName + '-section');
        if (section) {
            section.classList.add('active');
        }
    }
    
    // Show all bookings for a room
    function showAllBookings(roomId) {
        showNotification('All bookings are shown in the room schedule. Choose a different time for your booking.', 'info');
    }
    
    // Show notification
    function showNotification(message, type) {
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        const notification = document.createElement('div');
        notification.className = 'notification ' + type;
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, 3000);
    }
    
    // Show max booking alert
    function showMaxBookingAlert() {
        showNotification('You have reached the maximum of 2 active bookings. Please complete or cancel an existing booking first.', 'info');
    }
    
    // Contact support
    function contactSupport() {
        showNotification('Support: support@sirenektv.com\nPhone: +1-800-KTV-SING', 'info');
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'rooms';
            switchTab(tab);
        });
        
        // Animate cards on load
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s, transform 0.5s';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 50);
        });
    });
</script>

</body>
</html>