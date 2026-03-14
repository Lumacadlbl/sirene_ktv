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

// Fetch user's pre-orders with booking details from preorders table
$food_orders_query = $conn->prepare("
    SELECT 
        po.*,
        fb.item_name as food_name,
        fb.category,
        fb.price as original_price,
        b.b_id as booking_id,
        b.booking_date,
        b.start_time,
        b.end_time,
        r.room_name,
        b.total_amount as booking_total,
        b.status as booking_status,
        b.payment_status as booking_payment_status
    FROM preorders po
    JOIN food_beverages fb ON po.f_id = fb.f_id
    JOIN booking b ON po.b_id = b.b_id
    JOIN room r ON b.r_id = r.r_id
    WHERE b.u_id = ?
    ORDER BY b.booking_date DESC, b.start_time DESC, po.po_id DESC
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
            'has_pending_items' => false,
            'all_prepared' => true
        ];
    }
    
    $food_orders_by_booking[$booking_id]['items'][] = $order;
    $food_orders_by_booking[$booking_id]['total'] += ($order['price'] * $order['quantity']);
    
    // Check if any items are pending
    if ($order['status'] == 'pending') {
        $food_orders_by_booking[$booking_id]['has_pending_items'] = true;
    }
    
    // Check if all items are prepared
    if ($order['status'] != 'prepared') {
        $food_orders_by_booking[$booking_id]['all_prepared'] = false;
    }
}

// Calculate statistics
$total_room_bookings = count($room_bookings);
$total_food_orders = count($food_orders);
$total_food_spent = 0;
$pending_food_orders = 0;
$prepared_food_orders = 0;

foreach ($food_orders as $order) {
    $total_food_spent += ($order['price'] * $order['quantity']);
    
    if ($order['status'] == 'pending') {
        $pending_food_orders++;
    } elseif ($order['status'] == 'prepared') {
        $prepared_food_orders++;
    }
}

// Helper function to format scheduled date/time
function formatScheduledTime($scheduled_for) {
    if (empty($scheduled_for)) return 'Not scheduled';
    return date('M j, Y g:i A', strtotime($scheduled_for));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings & Pre-Orders - Sirene KTV</title>
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
            --purple: #6c5ce7;
            --orange: #e67e22;
            --teal: #008080;
            --preorder: #e67e22;
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

        .status-prepared {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .status-preparing {
            background: rgba(52, 152, 219, 0.2);
            color: #3498db;
            border: 1px solid rgba(52, 152, 219, 0.3);
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

        .action-danger {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: white;
        }

        .action-danger:hover {
            background: linear-gradient(135deg, #c0392b, var(--danger));
        }

        .action-orange {
            background: linear-gradient(135deg, var(--orange), #d35400);
            color: white;
        }

        .action-orange:hover {
            background: linear-gradient(135deg, #d35400, var(--orange));
        }

        .action-teal {
            background: linear-gradient(135deg, var(--teal), #006666);
            color: white;
        }

        .action-teal:hover {
            background: linear-gradient(135deg, #006666, var(--teal));
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

        .scheduled-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            background: rgba(230, 126, 34, 0.15);
            color: var(--preorder);
            border: 1px solid rgba(230, 126, 34, 0.3);
            margin-left: 10px;
        }

        .scheduled-badge i {
            margin-right: 4px;
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

        /* Cancel Modal Styles */
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
        }

        .modal-content {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            width: 90%;
            max-width: 500px;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            overflow: hidden;
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

        .reason-section {
            margin-bottom: 25px;
        }

        .reason-section label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 10px;
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
        }

        .modal-btn-confirm {
            background: linear-gradient(135deg, var(--danger), #c0392b);
            color: white;
        }

        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
            }
            
            .header-right {
                width: 100%;
                justify-content: center;
            }
            
            .container {
                padding: 0 15px;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .food-item {
                flex-direction: column;
                text-align: center;
            }
            
            .food-item-details,
            .food-item-quantity,
            .food-item-price,
            .food-item-status {
                width: 100%;
                text-align: center;
            }
            
            .booking-actions {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <h1><i class="fas fa-microphone-alt"></i> Sirene KTV</h1>
            <p>My Bookings & Pre-Orders</p>
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
                <p>View and manage your room bookings and pre-orders</p>
            </div>
        </div>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('room-bookings')">
                <i class="fas fa-door-closed"></i> Room Bookings
                <span class="badge"><?php echo count($room_bookings); ?></span>
            </button>
            <button class="tab-btn" onclick="showTab('food-orders')">
                <i class="fas fa-utensils"></i> Pre-Orders
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
                        <div class="stat-number"><?php echo count(array_filter($room_bookings, function($b) { return strtotime($b['booking_date'] . ' ' . $b['end_time']) > time(); })); ?></div>
                        <div class="stat-label">Upcoming</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="stat-number">₱<?php echo number_format(array_sum(array_column($room_bookings, 'total_amount')), 2); ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number"><?php echo count(array_filter($room_bookings, function($b) { return isset($b['payment_status']) && strtolower($b['payment_status']) == 'paid'; })); ?></div>
                        <div class="stat-label">Paid Bookings</div>
                    </div>
                </div>
                
                <div class="section-header">
                    <h3><i class="fas fa-door-closed"></i> Your Room Bookings</h3>
                </div>
                
                <?php foreach ($room_bookings as $booking): 
                    $bookingStatus = strtolower($booking['booking_status'] ?? 'pending');
                    $paymentStatus = strtolower($booking['payment_status'] ?? 'pending');
                    
                    $status_text = 'Pending';
                    $status_class = 'status-pending';
                    
                    if ($bookingStatus == 'cancelled') {
                        $status_text = 'Cancelled';
                        $status_class = 'status-cancelled';
                    } elseif ($bookingStatus == 'completed') {
                        $status_text = 'Completed';
                        $status_class = 'status-completed';
                    } elseif ($paymentStatus == 'paid') {
                        $status_text = 'Paid';
                        $status_class = 'status-paid';
                    } elseif ($bookingStatus == 'approved') {
                        $status_text = 'Approved';
                        $status_class = 'status-approved';
                    } elseif ($bookingStatus == 'confirmed') {
                        $status_text = 'Confirmed';
                        $status_class = 'status-confirmed';
                    }
                    
                    $canCancel = ($bookingStatus == 'pending' || $bookingStatus == 'approved' || $bookingStatus == 'confirmed') && $bookingStatus != 'cancelled';
                    $showPaymentBtn = ($paymentStatus == 'pending' || $paymentStatus == '') && $bookingStatus != 'cancelled' && $bookingStatus != 'rejected';
                ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div>
                                <div class="booking-id">Booking #<?php echo str_pad($booking['b_id'], 6, '0', STR_PAD_LEFT); ?></div>
                                <div class="booking-date-indicator">
                                    <i class="far fa-calendar"></i>
                                    <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?>
                                </div>
                            </div>
                            <div class="booking-status <?php echo $status_class; ?>">
                                <?php echo $status_text; ?>
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
                                    <p><?php echo date('g:i A', strtotime($booking['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking['end_time'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-user-friends"></i></div>
                                <div class="detail-content">
                                    <h4>Capacity</h4>
                                    <p><?php echo $booking['capcity']; ?> persons</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="booking-amount">
                            <div class="amount-label">Total Amount</div>
                            <div class="amount-value">₱<?php echo number_format($booking['total_amount'], 2); ?></div>
                        </div>
                        
                        <div class="booking-actions">
                            <?php if ($showPaymentBtn): ?>
                                <a href="payment.php?id=<?php echo $booking['b_id']; ?>" class="action-btn action-primary">
                                    <i class="fas fa-credit-card"></i> Pay Now
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($paymentStatus == 'paid' || $bookingStatus == 'completed'): ?>
                                <a href="receipt.php?id=<?php echo $booking['b_id']; ?>" class="action-btn action-success">
                                    <i class="fas fa-receipt"></i> View Receipt
                                </a>
                            <?php endif; ?>
                            
                            <a href="booking-details.php?id=<?php echo $booking['b_id']; ?>" class="action-btn action-secondary">
                                <i class="fas fa-eye"></i> Details
                            </a>
                            
                            <?php if (isset($food_orders_by_booking[$booking['b_id']])): ?>
                                <a href="javascript:void(0)" onclick="showTab('food-orders')" class="action-btn action-teal">
                                    <i class="fas fa-utensils"></i> Pre-Orders (<?php echo count($food_orders_by_booking[$booking['b_id']]['items']); ?>)
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($canCancel): ?>
                                <button class="action-btn action-danger" onclick="openCancelModal(<?php echo $booking['b_id']; ?>, '<?php echo addslashes($booking['room_name']); ?>', '<?php echo date('F j, Y', strtotime($booking['booking_date'])); ?>', '<?php echo date('g:i A', strtotime($booking['start_time'])); ?>', '<?php echo date('g:i A', strtotime($booking['end_time'])); ?>', <?php echo $booking['total_amount']; ?>)">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-door-closed"></i>
                    <h3>No Room Bookings Yet</h3>
                    <p>You haven't made any room bookings yet.</p>
                    <a href="dashboard.php?tab=rooms" class="btn">Book a Room</a>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Food Orders Tab (Pre-Orders) -->
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
                        <div class="stat-number"><?php echo $pending_food_orders; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-number"><?php echo $prepared_food_orders; ?></div>
                        <div class="stat-label">Prepared</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="stat-number">₱<?php echo number_format($total_food_spent, 2); ?></div>
                        <div class="stat-label">Food Total</div>
                    </div>
                </div>
                
                <?php foreach ($food_orders_by_booking as $booking_id => $booking_data): ?>
                    <div class="section-header">
                        <h3>
                            <i class="fas fa-calendar-alt"></i> 
                            Booking #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?> - <?php echo htmlspecialchars($booking_data['room_name']); ?>
                        </h3>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <?php if ($booking_data['booking_payment_status'] == 'paid'): ?>
                                <span class="payment-status-badge payment-status-paid">Room Paid</span>
                            <?php else: ?>
                                <span class="payment-status-badge payment-status-pending">Room Payment Pending</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="booking-date-indicator">
                                <i class="far fa-calendar"></i>
                                <?php echo date('F j, Y', strtotime($booking_data['booking_date'])); ?>
                            </div>
                        </div>
                        
                        <div class="food-items-list">
                            <?php foreach ($booking_data['items'] as $order): ?>
                                <div class="food-item">
                                    <div class="food-item-details">
                                        <div class="food-item-name"><?php echo htmlspecialchars($order['food_name']); ?></div>
                                        <div class="food-item-category"><?php echo ucfirst($order['category']); ?></div>
                                        <?php if (!empty($order['scheduled_for'])): ?>
                                            <div class="scheduled-badge">
                                                <i class="fas fa-clock"></i> Scheduled: <?php echo formatScheduledTime($order['scheduled_for']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="food-item-quantity">
                                        Qty: <?php echo $order['quantity']; ?>
                                    </div>
                                    
                                    <div class="food-item-price">
                                        ₱<?php echo number_format($order['price'] * $order['quantity'], 2); ?>
                                    </div>
                                    
                                    <div class="food-item-status">
                                        <?php if ($order['status'] == 'prepared'): ?>
                                            <span class="booking-status status-prepared">PREPARED</span>
                                        <?php elseif ($order['status'] == 'cancelled'): ?>
                                            <span class="booking-status status-cancelled">CANCELLED</span>
                                        <?php else: ?>
                                            <span class="booking-status status-preparing">PENDING</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="order-summary">
                            <span class="summary-label">Total Food Amount:</span>
                            <span class="summary-value">₱<?php echo number_format($booking_data['total'], 2); ?></span>
                        </div>
                        
                        <div class="booking-actions">
                            <!-- PAYMENT BUTTON - Only for pending items -->
                            <?php if ($booking_data['has_pending_items'] && $booking_data['total'] > 0): ?>
                                <a href="payment.php?id=<?php echo $booking_id; ?>&type=food_only" class="action-btn action-orange">
                                    <i class="fas fa-credit-card"></i> PAY FOR PRE-ORDERS (₱<?php echo number_format($booking_data['total'], 2); ?>)
                                </a>
                            <?php endif; ?>
                            
                            <a href="booking-details.php?id=<?php echo $booking_id; ?>" class="action-btn action-secondary">
                                <i class="fas fa-eye"></i> View Booking
                            </a>
                            
                            <?php if (!$booking_data['all_prepared'] && $booking_data['booking_status'] != 'cancelled'): ?>
                                <a href="food-order.php?booking_id=<?php echo $booking_id; ?>" class="action-btn action-primary">
                                    <i class="fas fa-plus-circle"></i> Add More Items
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($booking_data['all_prepared'] && $booking_data['total'] > 0): ?>
                                <span class="action-btn action-success" style="cursor: default;">
                                    <i class="fas fa-check-circle"></i> All Prepared
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-utensils"></i>
                    <h3>No Pre-Orders Yet</h3>
                    <p>You haven't pre-ordered any food items yet.</p>
                    <a href="dashboard.php?tab=rooms" class="btn">Book a Room to Pre-Order</a>
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
                <div class="booking-info-modal" id="modalBookingInfo"></div>
                <div class="warning-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <strong>Warning:</strong> This action cannot be undone.
                </div>
                <form id="cancelForm" method="POST" action="cancel-booking.php">
                    <input type="hidden" name="booking_id" id="cancelBookingId">
                    <div class="reason-section">
                        <label>Reason for Cancellation:</label>
                        <select name="reason" required>
                            <option value="">Select a reason</option>
                            <option value="change_plans">Change of plans</option>
                            <option value="scheduling_conflict">Scheduling conflict</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-cancel" onclick="closeCancelModal()">Keep Booking</button>
                        <button type="submit" class="modal-btn modal-btn-confirm">Confirm Cancellation</button>
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
        </div>
    </footer>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
            sessionStorage.setItem('activeTab', tabId);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const activeTab = sessionStorage.getItem('activeTab');
            if (activeTab) {
                document.querySelectorAll('.tab-btn').forEach(btn => {
                    if (btn.textContent.includes(activeTab === 'room-bookings' ? 'Room' : 'Pre-Orders')) {
                        btn.click();
                    }
                });
            }
        });

        function openCancelModal(bookingId, roomName, date, startTime, endTime, amount) {
            document.getElementById('cancelBookingId').value = bookingId;
            document.getElementById('modalBookingInfo').innerHTML = `
                <div class="info-row"><span class="info-label">Booking:</span><span class="info-value">#${bookingId.toString().padStart(6, '0')}</span></div>
                <div class="info-row"><span class="info-label">Room:</span><span class="info-value">${roomName}</span></div>
                <div class="info-row"><span class="info-label">Date:</span><span class="info-value">${date}</span></div>
                <div class="info-row"><span class="info-label">Time:</span><span class="info-value">${startTime} - ${endTime}</span></div>
                <div class="info-row"><span class="info-label">Amount:</span><span class="info-value">₱${parseFloat(amount).toFixed(2)}</span></div>
            `;
            document.getElementById('cancelModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeCancelModal() {
            document.getElementById('cancelModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        document.getElementById('cancelModal').addEventListener('click', function(e) {
            if (e.target === this) closeCancelModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeCancelModal();
        });
    </script>
</body>
</html>