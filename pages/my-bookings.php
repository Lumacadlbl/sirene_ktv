<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';

// Fetch user's room bookings
$room_bookings_query = $conn->prepare("
    SELECT b.*, r.room_name, r.capcity, r.price_hr,
           b.status as booking_status,
           b.payment_status as payment_status,
           p.payment_status as payments_status,
           p.amount as paid_amount,
           p.payment_method,
           p.payment_date
    FROM booking b 
    JOIN room r ON b.r_id = r.r_id 
    LEFT JOIN payments p ON b.b_id = p.b_id
    WHERE b.u_id = ?
    ORDER BY b.booking_date DESC, b.start_time DESC
");

if (!$room_bookings_query) {
    die("Prepare failed: " . $conn->error);
}

$room_bookings_query->bind_param("i", $user_id);
$room_bookings_query->execute();
$room_bookings_result = $room_bookings_query->get_result();
$room_bookings = $room_bookings_result->fetch_all(MYSQLI_ASSOC);

// Fetch user's food orders with booking details and food_beverages information
$food_orders_query = $conn->prepare("
    SELECT 
        bf.*,
        fb.item_name as food_name,
        fb.category,
        fb.price as original_price,
        fb.preparation_time as prep_time,
        b.b_id as booking_id,
        b.booking_date,
        b.start_time,
        b.end_time,
        r.room_name,
        b.total_amount as booking_total,
        b.status as booking_status,
        b.payment_status as booking_payment_status
    FROM booking_food bf
    JOIN food_beverages fb ON bf.f_id = fb.f_id
    JOIN booking b ON bf.b_id = b.b_id
    JOIN room r ON b.r_id = r.r_id
    WHERE b.u_id = ?
    ORDER BY b.booking_date DESC, b.start_time DESC, bf.bf_id DESC
");

if (!$food_orders_query) {
    die("Prepare failed: " . $conn->error);
}

$food_orders_query->bind_param("i", $user_id);
$food_orders_query->execute();
$food_orders_result = $food_orders_query->get_result();
$food_orders = $food_orders_result->fetch_all(MYSQLI_ASSOC);

// Group food orders by booking for summary
$food_orders_by_booking = [];
foreach ($food_orders as $order) {
    $booking_id = $order['booking_id'];
    if (!isset($food_orders_by_booking[$booking_id])) {
        $food_orders_by_booking[$booking_id] = [
            'booking_date' => $order['booking_date'],
            'room_name' => $order['room_name'],
            'booking_status' => $order['booking_status'],
            'booking_payment_status' => $order['booking_payment_status'],
            'items' => [],
            'total' => 0,
            'has_unserved_items' => false,
            'all_served' => true,
            'earliest_order_time' => null,
            'latest_order_time' => null
        ];
    }
    
    // Calculate waiting time for pending orders
    if ($order['served'] == 'pending' && $order['order_time']) {
        $order_time = strtotime($order['order_time']);
        if (!$food_orders_by_booking[$booking_id]['earliest_order_time'] || 
            $order_time < $food_orders_by_booking[$booking_id]['earliest_order_time']) {
            $food_orders_by_booking[$booking_id]['earliest_order_time'] = $order_time;
        }
        if (!$food_orders_by_booking[$booking_id]['latest_order_time'] || 
            $order_time > $food_orders_by_booking[$booking_id]['latest_order_time']) {
            $food_orders_by_booking[$booking_id]['latest_order_time'] = $order_time;
        }
    }
    
    $food_orders_by_booking[$booking_id]['items'][] = $order;
    $food_orders_by_booking[$booking_id]['total'] += ($order['price'] * $order['quantity']);
    
    // Check if any items are not served
    if ($order['served'] == 'pending') {
        $food_orders_by_booking[$booking_id]['has_unserved_items'] = true;
    }
    
    // Check if all items are served
    if ($order['served'] != 'served') {
        $food_orders_by_booking[$booking_id]['all_served'] = false;
    }
}

// Calculate statistics for room bookings
$total_room_bookings = count($room_bookings);
$upcoming_room_bookings = array_filter($room_bookings, function($b) {
    $bookingTime = strtotime($b['booking_date'] . ' ' . $b['end_time']);
    $currentTime = time();
    $bookingStatus = isset($b['booking_status']) ? strtolower($b['booking_status']) : 'pending';
    return $bookingTime > $currentTime && $bookingStatus !== 'cancelled';
});

$total_room_spent = 0;
$paid_room_bookings = 0;
foreach ($room_bookings as $booking) {
    $paymentStatus = isset($booking['payment_status']) ? strtolower($booking['payment_status']) : null;
    $bookingStatus = isset($booking['booking_status']) ? strtolower($booking['booking_status']) : 'pending';
    
    if ($paymentStatus == 'paid' || $paymentStatus == 'approved' || $bookingStatus == 'approved') {
        $total_room_spent += $booking['total_amount'];
        $paid_room_bookings++;
    }
}

// Calculate statistics for food orders
$total_food_orders = count($food_orders);
$total_food_spent = 0;
$unserved_food_orders = 0;
$served_food_orders = 0;
$cancelled_food_orders = 0;
$average_wait_time = 0;
$waiting_times = [];

foreach ($food_orders as $order) {
    $total_food_spent += ($order['price'] * $order['quantity']);
    
    switch(strtolower($order['served'])) {
        case 'pending':
            $unserved_food_orders++;
            // Calculate waiting time for pending orders
            if ($order['order_time']) {
                $wait_time = time() - strtotime($order['order_time']);
                $waiting_times[] = $wait_time;
            }
            break;
        case 'served':
            $served_food_orders++;
            break;
        case 'cancelled':
            $cancelled_food_orders++;
            break;
    }
}

// Calculate average wait time
if (!empty($waiting_times)) {
    $average_wait_time = array_sum($waiting_times) / count($waiting_times);
}

// Function to format wait time
function formatWaitTime($seconds) {
    if ($seconds < 60) {
        return $seconds . ' sec';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return $minutes . ' min ' . $secs . ' sec';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . ' hr ' . $minutes . ' min';
    }
}

// Function to get estimated remaining time
function getEstimatedRemainingTime($order_time, $prep_time) {
    if (!$order_time) return null;
    
    $elapsed = time() - strtotime($order_time);
    $remaining = max(0, ($prep_time * 60) - $elapsed); // Convert prep_time from minutes to seconds
    
    if ($remaining <= 0) {
        return 'any moment now';
    } elseif ($remaining < 60) {
        return $remaining . ' seconds';
    } elseif ($remaining < 3600) {
        $minutes = floor($remaining / 60);
        $seconds = $remaining % 60;
        return $minutes . ' min ' . $seconds . ' sec';
    } else {
        $hours = floor($remaining / 3600);
        $minutes = floor(($remaining % 3600) / 60);
        return $hours . ' hr ' . $minutes . ' min';
    }
}

// Function to get progress percentage
function getPreparationProgress($order_time, $prep_time) {
    if (!$order_time || !$prep_time) return 0;
    
    $elapsed = time() - strtotime($order_time);
    $total_prep_seconds = $prep_time * 60;
    
    if ($elapsed >= $total_prep_seconds) {
        return 100;
    }
    
    return min(100, round(($elapsed / $total_prep_seconds) * 100));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings & Orders - Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css-all.min.css">
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
            --purple: #6c5ce7;
            --orange: #e67e22;
            --teal: #008080;
            --food-payment: #e67e22;
            --unserved: #e67e22;
            --waiting: #f39c12;
            --preparing: #3498db;
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
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--highlight);
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left h1 {
            font-size: 26px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .header-left p {
            color: #aaa;
            font-size: 13px;
            margin-top: 3px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
        }

        .user-info {
            background: var(--accent);
            padding: 7px 14px;
            border-radius: 18px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .user-info i {
            color: var(--highlight);
        }

        .logout-btn {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            padding: 9px 22px;
            border-radius: 22px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-1px);
        }

        .back-btn {
            background: linear-gradient(135deg, var(--accent), #0f3460);
            color: white;
            border: none;
            padding: 9px 22px;
            border-radius: 22px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            text-decoration: none;
        }

        .back-btn:hover {
            background: linear-gradient(135deg, #0f3460, var(--accent));
            transform: translateY(-1px);
            text-decoration: none;
            color: white;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.05);
            padding: 25px;
            border-radius: 15px;
            border-left: 4px solid var(--highlight);
        }

        .page-title h2 {
            font-size: 28px;
            color: var(--light);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-title h2 i {
            color: var(--highlight);
        }

        .page-title p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 15px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 15px 30px;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: rgba(255, 255, 255, 0.6);
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            justify-content: center;
        }

        .tab-btn i {
            font-size: 18px;
        }

        .tab-btn.active {
            background: rgba(233, 69, 96, 0.2);
            color: var(--highlight);
        }

        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }

        .stat-card:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 30px;
            color: var(--highlight);
            margin-bottom: 10px;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: var(--light);
            margin: 10px 0;
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border-left: 4px solid var(--highlight);
            flex-wrap: wrap;
            gap: 15px;
        }

        .section-header h3 {
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h3 i {
            color: var(--highlight);
        }

        .badge {
            background: var(--highlight);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
        }

        .booking-card {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
            margin-bottom: 20px;
        }

        .booking-card:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-5px);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-wrap: wrap;
            gap: 15px;
        }

        .booking-id {
            background: rgba(233, 69, 96, 0.2);
            color: var(--highlight);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 14px;
        }

        .booking-status {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-pending {
            background: rgba(253, 203, 110, 0.2);
            color: var(--warning);
            border: 1px solid rgba(253, 203, 110, 0.3);
        }

        .status-confirmed {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .status-cancelled {
            background: rgba(214, 48, 49, 0.2);
            color: var(--danger);
            border: 1px solid rgba(214, 48, 49, 0.3);
        }

        .status-paid {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .status-approved {
            background: rgba(9, 132, 227, 0.2);
            color: var(--info);
            border: 1px solid rgba(9, 132, 227, 0.3);
        }

        .status-waiting-approval {
            background: rgba(108, 92, 231, 0.2);
            color: var(--purple);
            border: 1px solid rgba(108, 92, 231, 0.3);
        }

        .status-rejected {
            background: rgba(214, 48, 49, 0.2);
            color: var(--danger);
            border: 1px solid rgba(214, 48, 49, 0.3);
        }

        .status-completed {
            background: rgba(149, 165, 166, 0.2);
            color: #95a5a6;
            border: 1px solid rgba(149, 165, 166, 0.3);
        }

        .status-served {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .status-unserved {
            background: rgba(230, 126, 34, 0.2);
            color: var(--orange);
            border: 1px solid rgba(230, 126, 34, 0.3);
        }

        .status-preparing {
            background: rgba(52, 152, 219, 0.2);
            color: var(--preparing);
            border: 1px solid rgba(52, 152, 219, 0.3);
        }

        .status-waiting {
            background: rgba(243, 156, 18, 0.2);
            color: var(--waiting);
            border: 1px solid rgba(243, 156, 18, 0.3);
        }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .detail-icon {
            width: 40px;
            height: 40px;
            background: rgba(233, 69, 96, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--highlight);
            font-size: 18px;
        }

        .detail-content h4 {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-bottom: 5px;
        }

        .detail-content p {
            color: var(--light);
            font-size: 18px;
            font-weight: bold;
        }

        .booking-amount {
            background: rgba(233, 69, 96, 0.1);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }

        .amount-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .amount-value {
            color: var(--highlight);
            font-size: 28px;
            font-weight: bold;
        }

        .booking-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            flex-wrap: wrap;
        }

        .action-btn {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            border: none;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
            min-width: 120px;
            text-decoration: none;
        }

        .action-btn:hover {
            transform: translateY(-2px);
        }

        .action-primary {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
        }

        .action-primary:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
        }

        .action-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .action-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .action-success {
            background: linear-gradient(135deg, var(--success), #00a085);
            color: white;
        }

        .action-success:hover {
            background: linear-gradient(135deg, #00a085, var(--success));
        }

        .action-info {
            background: linear-gradient(135deg, var(--info), #2980b9);
            color: white;
        }

        .action-info:hover {
            background: linear-gradient(135deg, #2980b9, var(--info));
        }

        .action-danger {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: white;
        }

        .action-danger:hover {
            background: linear-gradient(135deg, #c0392b, var(--danger));
        }

        .action-warning {
            background: linear-gradient(135deg, var(--warning), #e8a822);
            color: #333;
        }

        .action-warning:hover {
            background: linear-gradient(135deg, #e8a822, var(--warning));
        }

        .action-teal {
            background: linear-gradient(135deg, var(--teal), #006666);
            color: white;
        }

        .action-teal:hover {
            background: linear-gradient(135deg, #006666, var(--teal));
        }

        .action-food {
            background: linear-gradient(135deg, var(--food-payment), #d35400);
            color: white;
        }

        .action-food:hover {
            background: linear-gradient(135deg, #d35400, var(--food-payment));
        }

        .food-items-list {
            margin: 20px 0;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 10px;
            padding: 15px;
        }

        .food-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            flex-wrap: wrap;
            gap: 15px;
        }

        .food-item:last-child {
            border-bottom: none;
        }

        .food-item-details {
            flex: 2;
            min-width: 200px;
        }

        .food-item-name {
            font-weight: bold;
            color: var(--light);
            margin-bottom: 3px;
            font-size: 16px;
        }

        .food-item-category {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }

        .food-item-quantity {
            flex: 1;
            text-align: center;
            color: var(--light);
            min-width: 80px;
        }

        .food-item-price {
            flex: 1;
            text-align: right;
            color: var(--highlight);
            font-weight: bold;
            min-width: 100px;
        }

        .food-item-status {
            flex: 1;
            text-align: center;
            min-width: 120px;
        }

        /* New Waiting Time Styles */
        .waiting-time-container {
            flex: 2;
            min-width: 250px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 8px;
            padding: 10px;
        }

        .waiting-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
        }

        .waiting-time {
            font-size: 14px;
            font-weight: bold;
            color: var(--waiting);
        }

        .estimated-time {
            font-size: 14px;
            font-weight: bold;
            color: var(--success);
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--waiting), var(--success));
            border-radius: 4px;
            transition: width 1s ease;
        }

        .prep-time-badge {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 3px;
        }

        .waiting-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 5px;
            animation: pulse 1.5s infinite;
        }

        .waiting-indicator.preparing {
            background: var(--preparing);
        }

        .waiting-indicator.waiting {
            background: var(--waiting);
        }

        @keyframes pulse {
            0% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.5;
                transform: scale(1.2);
            }
            100% {
                opacity: 1;
                transform: scale(1);
            }
        }

        .order-summary {
            background: rgba(233, 69, 96, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .summary-label {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }

        .summary-value {
            color: var(--highlight);
            font-size: 20px;
            font-weight: bold;
        }

        .payment-status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            margin-left: 10px;
        }

        .payment-status-paid {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .payment-status-pending {
            background: rgba(253, 203, 110, 0.2);
            color: var(--warning);
            border: 1px solid rgba(253, 203, 110, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            margin: 20px 0;
        }

        .empty-state i {
            font-size: 80px;
            color: rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: var(--light);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 30px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .empty-state .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: bold;
            transition: all 0.2s;
        }

        .empty-state .btn:hover {
            transform: translateY(-2px);
        }

        .booking-date-indicator {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 5px;
        }

        .booking-date-indicator i {
            font-size: 10px;
        }

        .upcoming-badge {
            background: rgba(46, 204, 113, 0.2);
            color: #2ecc71;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 5px;
        }

        .past-badge {
            background: rgba(149, 165, 166, 0.2);
            color: #95a5a6;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            margin-left: 5px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            width: 90%;
            max-width: 500px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            background: rgba(233, 69, 96, 0.1);
            padding: 20px;
            border-bottom: 1px solid rgba(233, 69, 96, 0.2);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .modal-header i {
            color: var(--danger);
            font-size: 24px;
        }

        .modal-header h3 {
            color: var(--light);
            font-size: 20px;
        }

        .modal-body {
            padding: 25px;
        }

        .booking-info-modal {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: rgba(255, 255, 255, 0.6);
        }

        .info-value {
            color: var(--light);
            font-weight: bold;
        }

        .warning-message {
            background: rgba(214, 48, 49, 0.1);
            border: 1px solid rgba(214, 48, 49, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }

        .warning-message i {
            color: var(--danger);
            margin-right: 10px;
        }

        .reason-section {
            margin-bottom: 25px;
        }

        .reason-section label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 10px;
            font-weight: 500;
        }

        .reason-section select,
        .reason-section textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 12px;
            color: var(--light);
            font-size: 16px;
            margin-bottom: 15px;
        }

        .reason-section textarea {
            resize: vertical;
            min-height: 100px;
        }

        .reason-section select:focus,
        .reason-section textarea:focus {
            outline: none;
            border-color: var(--danger);
        }

        .modal-footer {
            display: flex;
            gap: 15px;
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-btn {
            flex: 1;
            padding: 14px;
            border-radius: 10px;
            border: none;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 16px;
        }

        .modal-btn-cancel {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .modal-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .modal-btn-confirm {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: white;
        }

        .modal-btn-confirm:hover {
            background: linear-gradient(135deg, #c0392b, var(--danger));
            transform: translateY(-2px);
        }

        footer {
            text-align: center;
            padding: 22px;
            background: rgba(10, 10, 20, 0.95);
            margin-top: 50px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
        }

        footer p {
            margin-bottom: 8px;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 18px;
            margin-top: 10px;
        }

        .footer-links a {
            color: var(--highlight);
            text-decoration: none;
            font-size: 12px;
            transition: all 0.2s;
        }

        .footer-links a:hover {
            color: #ff7675;
            text-decoration: underline;
        }

        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
            }
            
            .header-right {
                width: 100%;
                justify-content: center;
            }
            
            .user-info, .back-btn, .logout-btn {
                width: 100%;
                justify-content: center;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .booking-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .booking-details {
                grid-template-columns: 1fr;
            }
            
            .food-item {
                flex-direction: column;
                text-align: center;
            }
            
            .food-item-details,
            .food-item-quantity,
            .food-item-price,
            .food-item-status,
            .waiting-time-container {
                width: 100%;
                text-align: center;
                min-width: 100%;
            }
            
            .waiting-header {
                justify-content: center;
            }
            
            .order-summary {
                flex-direction: column;
                text-align: center;
            }
            
            .modal-content {
                width: 95%;
                margin: 10px;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .tabs {
                flex-direction: column;
            }
            
            .tab-btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .action-btn {
                min-width: 100%;
            }
            
            .booking-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <h1><i class="fas fa-microphone-alt"></i> Sirene KTV</h1>
            <p>My Bookings & Food Orders</p>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($name); ?> (<?php echo ucfirst($role); ?>)
            </div>
            
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-home"></i> Dashboard
            </a>
            
            <form action="logout.php" method="post">
                <button type="submit" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </form>
        </div>
    </header>

    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2><i class="fas fa-calendar-check"></i> My Activities</h2>
                <p>View and manage your room bookings and food orders</p>
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('room-bookings')">
                <i class="fas fa-door-closed"></i> Room Bookings
                <span class="badge"><?php echo count($room_bookings); ?></span>
            </button>
            <button class="tab-btn" onclick="showTab('food-orders')">
                <i class="fas fa-utensils"></i> Food Orders
                <span class="badge"><?php echo count($food_orders); ?></span>
            </button>
        </div>
        
        <!-- Room Bookings Tab -->
        <div id="room-bookings" class="tab-content active">
            <?php if (!empty($room_bookings)): ?>
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                        <div class="stat-number"><?php echo $total_room_bookings; ?></div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-number"><?php echo count($upcoming_room_bookings); ?></div>
                        <div class="stat-label">Upcoming</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="stat-number">₱<?php echo number_format($total_room_spent, 2); ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number"><?php echo $paid_room_bookings; ?></div>
                        <div class="stat-label">Paid Bookings</div>
                    </div>
                </div>
                
                <div class="section-header">
                    <h3><i class="fas fa-door-closed"></i> Your Room Bookings</h3>
                </div>
                
                <div class="bookings-list">
                    <?php foreach ($room_bookings as $booking): 
                        $bookingStatus = isset($booking['booking_status']) ? strtolower($booking['booking_status']) : 'pending';
                        $paymentStatus = isset($booking['payment_status']) ? strtolower($booking['payment_status']) : 'pending';
                        
                        $status_text = 'Pending';
                        $status_class = 'status-pending';
                        
                        if ($bookingStatus == 'cancelled') {
                            $status_text = 'Cancelled';
                            $status_class = 'status-cancelled';
                        } elseif ($bookingStatus == 'completed') {
                            $status_text = 'Completed';
                            $status_class = 'status-completed';
                        } elseif ($paymentStatus == 'paid' || $paymentStatus == 'approved') {
                            $status_text = 'Paid';
                            $status_class = 'status-paid';
                        } elseif ($bookingStatus == 'approved') {
                            $status_text = 'Approved';
                            $status_class = 'status-approved';
                        } elseif ($bookingStatus == 'rejected') {
                            $status_text = 'Rejected';
                            $status_class = 'status-rejected';
                        } elseif ($bookingStatus == 'pending') {
                            $status_text = 'Pending Approval';
                            $status_class = 'status-waiting-approval';
                        } elseif ($bookingStatus == 'confirmed') {
                            $status_text = 'Confirmed';
                            $status_class = 'status-confirmed';
                        }
                        
                        $bookingTime = strtotime($booking['booking_date'] . ' ' . $booking['end_time']);
                        $isUpcoming = $bookingTime > time();
                        $timeBadge = $isUpcoming ? 'upcoming-badge' : 'past-badge';
                        $timeBadgeText = $isUpcoming ? 'UPCOMING' : 'PAST';
                        
                        $canCancel = ($status_text == 'Pending Approval' || $status_text == 'Approved' || $status_text == 'Confirmed') && 
                                     $isUpcoming && $bookingStatus != 'cancelled';
                        
                        $showPaymentBtn = ($paymentStatus == 'pending' || $paymentStatus == '') && 
                                         $bookingStatus != 'cancelled' && 
                                         $bookingStatus != 'rejected' && 
                                         ($bookingStatus == 'approved' || $bookingStatus == 'confirmed');
                        
                        $approvalBadge = '';
                        $approvalBadgeClass = '';
                        
                        if ($bookingStatus == 'pending') {
                            $approvalBadge = 'Awaiting Admin Approval';
                            $approvalBadgeClass = 'booking-approval-status status-waiting-approval';
                        } elseif ($bookingStatus == 'approved') {
                            $approvalBadge = 'Booking Approved';
                            $approvalBadgeClass = 'booking-approval-status status-approved';
                        } elseif ($bookingStatus == 'rejected') {
                            $approvalBadge = 'Booking Rejected';
                            $approvalBadgeClass = 'booking-approval-status status-rejected';
                        }
                        
                        $start = new DateTime($booking['start_time']);
                        $end = new DateTime($booking['end_time']);
                        $interval = $start->diff($end);
                        $hours = $interval->h;
                        $minutes = $interval->i;
                        $duration = $hours;
                        if ($minutes > 0) {
                            $duration .= 'h ' . $minutes . 'm';
                        } else {
                            $duration .= ' hours';
                        }
                    ?>
                        <div class="booking-card">
                            <div class="booking-header">
                                <div>
                                    <div class="booking-id">Booking #<?php echo str_pad($booking['b_id'], 6, '0', STR_PAD_LEFT); ?></div>
                                    <div class="booking-date-indicator">
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?>
                                        <span class="<?php echo $timeBadge; ?>"><?php echo $timeBadgeText; ?></span>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($approvalBadge): ?>
                                        <div class="<?php echo $approvalBadgeClass; ?>" style="margin-bottom: 10px;">
                                            <?php echo $approvalBadge; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="booking-status <?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="booking-details">
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-door-closed"></i></div>
                                    <div class="detail-content">
                                        <h4>Room</h4>
                                        <p><?php echo htmlspecialchars($booking['room_name']); ?></p>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-clock"></i></div>
                                    <div class="detail-content">
                                        <h4>Time</h4>
                                        <p>
                                            <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-user-friends"></i></div>
                                    <div class="detail-content">
                                        <h4>Capacity</h4>
                                        <p><?php echo $booking['capcity']; ?> persons</p>
                                    </div>
                                </div>
                                
                                <div class="detail-item">
                                    <div class="detail-icon"><i class="fas fa-hourglass-half"></i></div>
                                    <div class="detail-content">
                                        <h4>Duration</h4>
                                        <p><?php echo $duration; ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="booking-amount">
                                <div class="amount-label">Total Amount</div>
                                <div class="amount-value">₱<?php echo number_format($booking['total_amount'], 2); ?></div>
                                <?php if ($booking['paid_amount'] > 0): ?>
                                    <div style="margin-top: 10px; color: rgba(255,255,255,0.7); font-size: 14px;">
                                        Paid: ₱<?php echo number_format($booking['paid_amount'], 2); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="booking-actions">
                                <?php if ($showPaymentBtn): ?>
                                    <a href="payment.php?id=<?php echo $booking['b_id']; ?>" class="action-btn action-primary">
                                        <i class="fas fa-credit-card"></i> Make Payment
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($paymentStatus == 'paid' || $paymentStatus == 'approved' || $bookingStatus == 'completed'): ?>
                                    <a href="receipt.php?id=<?php echo $booking['b_id']; ?>" class="action-btn action-success">
                                        <i class="fas fa-receipt"></i> View Receipt
                                    </a>
                                <?php endif; ?>
                                
                                <a href="booking-details.php?id=<?php echo $booking['b_id']; ?>" class="action-btn action-secondary">
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                                
                                <?php if (isset($food_orders_by_booking[$booking['b_id']])): ?>
                                    <a href="javascript:void(0)" onclick="showTab('food-orders')" class="action-btn action-teal">
                                        <i class="fas fa-utensils"></i> Food Orders (<?php echo count($food_orders_by_booking[$booking['b_id']]['items']); ?>)
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($canCancel): ?>
                                    <button class="action-btn action-danger" onclick="openCancelModal(<?php echo $booking['b_id']; ?>, '<?php echo addslashes($booking['room_name']); ?>', '<?php echo date('F j, Y', strtotime($booking['booking_date'])); ?>', '<?php echo date('g:i A', strtotime($booking['start_time'])); ?>', '<?php echo date('g:i A', strtotime($booking['end_time'])); ?>', <?php echo $booking['total_amount']; ?>)">
                                        <i class="fas fa-times"></i> Cancel Booking
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-door-closed"></i>
                    <h3>No Room Bookings Yet</h3>
                    <p>You haven't made any room bookings yet. Start your KTV experience by booking a room!</p>
                    <a href="dashboard.php?tab=rooms" class="btn">
                        <i class="fas fa-door-closed"></i> Browse Available Rooms
                    </a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Food Orders Tab -->
        <div id="food-orders" class="tab-content">
            <?php if (!empty($food_orders)): ?>
                <div class="stats-cards">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                        <div class="stat-number"><?php echo $total_food_orders; ?></div>
                        <div class="stat-label">Total Items</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                        <div class="stat-number"><?php echo $unserved_food_orders; ?></div>
                        <div class="stat-label">In Kitchen</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
                        <div class="stat-number"><?php echo formatWaitTime($average_wait_time); ?></div>
                        <div class="stat-label">Avg Wait Time</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number"><?php echo $served_food_orders; ?></div>
                        <div class="stat-label">Served</div>
                    </div>
                </div>
                
                <?php foreach ($food_orders_by_booking as $booking_id => $booking_data): ?>
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-calendar-alt"></i> 
                            Booking #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($booking_data['room_name']); ?>
                        </h3>
                        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <div class="booking-status <?php 
                                echo $booking_data['booking_status'] == 'cancelled' ? 'status-cancelled' : 
                                    ($booking_data['booking_status'] == 'completed' ? 'status-completed' : 'status-approved'); 
                            ?>">
                                <?php echo ucfirst($booking_data['booking_status']); ?>
                            </div>
                            <?php if ($booking_data['booking_payment_status'] == 'paid' || $booking_data['booking_payment_status'] == 'approved'): ?>
                                <span class="payment-status-badge payment-status-paid">
                                    <i class="fas fa-check-circle"></i> Room Paid
                                </span>
                            <?php else: ?>
                                <span class="payment-status-badge payment-status-pending">
                                    <i class="fas fa-clock"></i> Room Payment Pending
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="booking-card">
                        <div class="booking-header">
                            <div>
                                <div class="booking-date-indicator">
                                    <i class="far fa-calendar"></i>
                                    <?php echo date('F j, Y', strtotime($booking_data['booking_date'])); ?>
                                </div>
                                <?php if ($booking_data['has_unserved_items']): ?>
                                    <div style="margin-top: 10px; display: flex; gap: 10px; align-items: center;">
                                        <span class="waiting-indicator preparing"></span>
                                        <span style="color: var(--preparing); font-size: 13px;">Kitchen is preparing your food</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($booking_data['has_unserved_items']): ?>
                                <div class="booking-status status-preparing">
                                    <i class="fas fa-utensils"></i> Preparing
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="food-items-list">
                            <?php foreach ($booking_data['items'] as $order): 
                                $wait_time = $order['order_time'] ? (time() - strtotime($order['order_time'])) : 0;
                                $progress = getPreparationProgress($order['order_time'], $order['prep_time']);
                                $remaining_time = getEstimatedRemainingTime($order['order_time'], $order['prep_time']);
                            ?>
                                <div class="food-item">
                                    <div class="food-item-details">
                                        <div class="food-item-name"><?php echo htmlspecialchars($order['food_name']); ?></div>
                                        <div class="food-item-category"><?php echo ucfirst($order['category']); ?></div>
                                    </div>
                                    <div class="food-item-quantity">
                                        <strong>Qty:</strong> <?php echo $order['quantity']; ?>
                                    </div>
                                    <div class="food-item-price">
                                        ₱<?php echo number_format($order['price'] * $order['quantity'], 2); ?>
                                        <small style="display: block; font-size: 10px; color: rgba(255,255,255,0.5);">@ ₱<?php echo number_format($order['price'], 2); ?> each</small>
                                    </div>
                                    <div class="food-item-status">
                                        <?php if ($order['served'] == 'served'): ?>
                                            <span class="booking-status status-served">
                                                <i class="fas fa-check-circle"></i> SERVED
                                            </span>
                                        <?php elseif ($order['served'] == 'cancelled'): ?>
                                            <span class="booking-status status-cancelled">
                                                <i class="fas fa-times-circle"></i> CANCELLED
                                            </span>
                                        <?php else: ?>
                                            <span class="booking-status status-preparing">
                                                <i class="fas fa-spinner fa-spin"></i> PREPARING
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Waiting Time Display for Pending Orders -->
                                    <?php if ($order['served'] == 'pending' && $order['order_time']): ?>
                                        <div class="waiting-time-container" id="waiting-<?php echo $order['bf_id']; ?>">
                                            <div class="waiting-header">
                                                <span>
                                                    <span class="waiting-indicator <?php echo $progress >= 80 ? 'preparing' : 'waiting'; ?>"></span>
                                                    Wait Time
                                                </span>
                                                <span class="waiting-time" id="wait-time-<?php echo $order['bf_id']; ?>">
                                                    <?php echo formatWaitTime($wait_time); ?>
                                                </span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill" id="progress-<?php echo $order['bf_id']; ?>" style="width: <?php echo $progress; ?>%;"></div>
                                            </div>
                                            <div class="waiting-header">
                                                <span class="prep-time-badge">
                                                    <i class="far fa-clock"></i> Est. prep: <?php echo $order['prep_time']; ?> min
                                                </span>
                                                <span class="estimated-time" id="remaining-<?php echo $order['bf_id']; ?>">
                                                    <?php if ($progress >= 100): ?>
                                                        <i class="fas fa-check-circle" style="color: var(--success);"></i> Ready soon
                                                    <?php else: ?>
                                                        ~<?php echo $remaining_time; ?>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- Auto-update script for this item -->
                                        <script>
                                            (function() {
                                                const orderId = <?php echo $order['bf_id']; ?>;
                                                const orderTime = <?php echo strtotime($order['order_time']); ?>;
                                                const prepTime = <?php echo $order['prep_time'] * 60; ?>; // Convert to seconds
                                                
                                                function updateWaitingTime() {
                                                    const now = Math.floor(Date.now() / 1000);
                                                    const elapsed = now - orderTime;
                                                    const remaining = Math.max(0, prepTime - elapsed);
                                                    const progress = Math.min(100, (elapsed / prepTime) * 100);
                                                    
                                                    // Format elapsed time
                                                    let elapsedText = '';
                                                    if (elapsed < 60) {
                                                        elapsedText = elapsed + ' sec';
                                                    } else if (elapsed < 3600) {
                                                        const minutes = Math.floor(elapsed / 60);
                                                        const seconds = elapsed % 60;
                                                        elapsedText = minutes + ' min ' + seconds + ' sec';
                                                    } else {
                                                        const hours = Math.floor(elapsed / 3600);
                                                        const minutes = Math.floor((elapsed % 3600) / 60);
                                                        elapsedText = hours + ' hr ' + minutes + ' min';
                                                    }
                                                    
                                                    // Format remaining time
                                                    let remainingText = '';
                                                    if (remaining <= 0) {
                                                        remainingText = '<i class="fas fa-check-circle" style="color: var(--success);"></i> Ready soon';
                                                    } else if (remaining < 60) {
                                                        remainingText = '~' + remaining + ' sec';
                                                    } else if (remaining < 3600) {
                                                        const minutes = Math.floor(remaining / 60);
                                                        const seconds = remaining % 60;
                                                        remainingText = '~' + minutes + ' min ' + seconds + ' sec';
                                                    } else {
                                                        const hours = Math.floor(remaining / 3600);
                                                        const minutes = Math.floor((remaining % 3600) / 60);
                                                        remainingText = '~' + hours + ' hr ' + minutes + ' min';
                                                    }
                                                    
                                                    // Update DOM
                                                    document.getElementById('wait-time-' + orderId).textContent = elapsedText;
                                                    document.getElementById('progress-' + orderId).style.width = progress + '%';
                                                    document.getElementById('remaining-' + orderId).innerHTML = remainingText;
                                                    
                                                    // Update indicator color based on progress
                                                    const indicator = document.querySelector('#waiting-' + orderId + ' .waiting-indicator');
                                                    if (indicator) {
                                                        if (progress >= 80) {
                                                            indicator.className = 'waiting-indicator preparing';
                                                        } else {
                                                            indicator.className = 'waiting-indicator waiting';
                                                        }
                                                    }
                                                }
                                                
                                                // Update every second
                                                setInterval(updateWaitingTime, 1000);
                                            })();
                                        </script>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-summary">
                            <span class="summary-label">Total Food Amount:</span>
                            <span class="summary-value">₱<?php echo number_format($booking_data['total'], 2); ?></span>
                        </div>
                        
                        <div class="booking-actions">
                            <a href="booking-details.php?id=<?php echo $booking_id; ?>" class="action-btn action-secondary">
                                <i class="fas fa-eye"></i> View Booking Details
                            </a>
                            
                            <?php if ($booking_data['booking_status'] != 'cancelled' && $booking_data['booking_status'] != 'completed'): ?>
                                <?php if ($booking_data['has_unserved_items']): ?>
                                    <!-- Pay for Food Button -->
                                    <a href="payment.php?id=<?php echo $booking_id; ?>&type=food_only" class="action-btn action-food">
                                        <i class="fas fa-credit-card"></i> Pay for Food (₱<?php echo number_format($booking_data['total'], 2); ?>)
                                    </a>
                                <?php endif; ?>
                                
                                <?php if (!$booking_data['all_served']): ?>
                                    <a href="food-order.php?booking_id=<?php echo $booking_id; ?>" class="action-btn action-primary">
                                        <i class="fas fa-plus-circle"></i> Add More Items
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if ($booking_data['all_served'] && $booking_data['total'] > 0): ?>
                                <span class="action-btn action-success" style="cursor: default; opacity: 0.7;">
                                    <i class="fas fa-check-circle"></i> All Items Served
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-utensils"></i>
                    <h3>No Food Orders Yet</h3>
                    <p>You haven't ordered any food items with your bookings yet.</p>
                    <a href="dashboard.php?tab=rooms" class="btn">
                        <i class="fas fa-door-closed"></i> Book a Room First
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cancel Booking Modal -->
    <div class="modal" id="cancelModal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>Cancel Booking</h3>
            </div>
            
            <div class="modal-body">
                <div class="booking-info-modal" id="modalBookingInfo">
                    <!-- Booking info will be inserted here by JavaScript -->
                </div>
                
                <div class="warning-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Warning:</strong> Cancelling a booking cannot be undone. All associated food orders will also be cancelled.
                </div>
                
                <form id="cancelForm" method="POST" action="cancel-booking.php">
                    <input type="hidden" name="booking_id" id="cancelBookingId">
                    
                    <div class="reason-section">
                        <label for="cancelReason">Reason for Cancellation:</label>
                        <select name="reason" id="cancelReason" required onchange="toggleCustomReason()">
                            <option value="">Select a reason</option>
                            <option value="change_plans">Change of plans</option>
                            <option value="found_alternative">Found better alternative</option>
                            <option value="scheduling_conflict">Scheduling conflict</option>
                            <option value="travel_issues">Travel issues</option>
                            <option value="financial_reasons">Financial reasons</option>
                            <option value="other">Other reason</option>
                        </select>
                        
                        <div id="customReasonContainer" style="display: none;">
                            <label for="customReason">Please specify:</label>
                            <textarea name="custom_reason" id="customReason" placeholder="Please provide details for cancellation..." maxlength="500"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-cancel" onclick="closeCancelModal()">
                            <i class="fas fa-times"></i> Keep Booking
                        </button>
                        <button type="submit" class="modal-btn modal-btn-confirm">
                            <i class="fas fa-check"></i> Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 Sirene KTV. All Rights Reserved.</p>
        <div class="footer-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="my-bookings.php">My Bookings</a>
            <a href="#">Help</a>
            <a href="#">Contact</a>
        </div>
    </footer>

    <script>
        function showTab(tabId) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabId).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
            
            // Store active tab in session storage
            sessionStorage.setItem('activeTab', tabId);
        }
        
        // Check for saved tab preference
        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = sessionStorage.getItem('activeTab');
            if (activeTab) {
                showTab(activeTab);
            }
            
            // Animate cards
            const cards = document.querySelectorAll('.booking-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
        
        function openCancelModal(bookingId, roomName, bookingDate, startTime, endTime, totalAmount) {
            currentBookingId = bookingId;
            
            document.getElementById('cancelBookingId').value = bookingId;
            
            const modalContent = document.getElementById('modalBookingInfo');
            modalContent.innerHTML = `
                <div class="info-row">
                    <span class="info-label">Booking ID:</span>
                    <span class="info-value">#${bookingId.toString().padStart(6, '0')}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Room:</span>
                    <span class="info-value">${roomName}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span class="info-value">${bookingDate}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Time:</span>
                    <span class="info-value">${startTime} - ${endTime}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Amount:</span>
                    <span class="info-value" style="color: var(--highlight);">₱${parseFloat(totalAmount).toFixed(2)}</span>
                </div>
            `;
            
            document.getElementById('cancelModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            resetCancelForm();
        }
        
        function resetCancelForm() {
            document.getElementById('cancelForm').reset();
            document.getElementById('customReasonContainer').style.display = 'none';
        }
        
        function toggleCustomReason() {
            const reason = document.getElementById('cancelReason').value;
            const container = document.getElementById('customReasonContainer');
            
            if (reason === 'other') {
                container.style.display = 'block';
                document.getElementById('customReason').required = true;
            } else {
                container.style.display = 'none';
                document.getElementById('customReason').required = false;
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCancelModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCancelModal();
            }
        });
        
        // Handle form submission
        document.getElementById('cancelForm').addEventListener('submit', function(e) {
            const reason = document.getElementById('cancelReason').value;
            const customReason = document.getElementById('customReason').value;
            
            if (reason === 'other' && !customReason.trim()) {
                e.preventDefault();
                alert('Please provide a reason for cancellation.');
                document.getElementById('customReason').focus();
                return false;
            }
            
            if (!confirm('Are you sure you want to cancel this booking? All associated food orders will also be cancelled. This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
            
            const confirmBtn = this.querySelector('.modal-btn-confirm');
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmBtn.disabled = true;
            
            return true;
        });
    </script>
</body>
</html>