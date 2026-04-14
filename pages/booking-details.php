<?php
session_start();
include "../db.php";
// Add these cache control headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Get booking ID from URL
$booking_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($booking_id == 0) {
    header("Location: my-bookings.php");
    exit;
}

// First, check if this booking belongs to the user or user is admin
$check_query = $conn->prepare("SELECT u_id FROM booking WHERE b_id = ?");
$check_query->bind_param("i", $booking_id);
$check_query->execute();
$check_result = $check_query->get_result();

if ($check_result->num_rows == 0) {
    header("Location: my-bookings.php");
    exit;
}

$booking_owner = $check_result->fetch_assoc();
if ($booking_owner['u_id'] != $user_id && $role != 'admin') {
    header("Location: my-bookings.php");
    exit;
}

// Fetch booking details WITH PAYMENT STATUS - IMPROVED QUERY
$booking_query = $conn->prepare("
    SELECT b.*, r.room_name, r.price_hr, r.capcity,
           u.name as customer_name, u.email as customer_email, u.contact as customer_phone,
           p.payment_status as actual_payment_status, 
           SUM(p.amount) as total_paid_amount,
           GROUP_CONCAT(DISTINCT p.payment_method SEPARATOR ', ') as payment_methods,
           MAX(p.payment_date) as last_payment_date
    FROM booking b
    LEFT JOIN room r ON b.r_id = r.r_id
    LEFT JOIN user_tbl u ON b.u_id = u.id
    LEFT JOIN payments p ON b.b_id = p.b_id
    WHERE b.b_id = ?
    GROUP BY b.b_id
");
$booking_query->bind_param("i", $booking_id);
$booking_query->execute();
$booking_result = $booking_query->get_result();

if ($booking_result->num_rows == 0) {
    header("Location: my-bookings.php");
    exit;
}

$booking = $booking_result->fetch_assoc();

// Calculate total hours
$start_time = null;
$end_time = null;
$total_hours = 1;

try {
    if (isset($booking['start_time']) && isset($booking['end_time'])) {
        if (strlen($booking['start_time']) > 8) {
            $start_time = new DateTime($booking['start_time']);
            $end_time = new DateTime($booking['end_time']);
        } else {
            $start_time = new DateTime($booking['booking_date'] . ' ' . $booking['start_time']);
            $end_time = new DateTime($booking['booking_date'] . ' ' . $booking['end_time']);
        }
        
        if ($start_time && $end_time) {
            $interval = $start_time->diff($end_time);
            $total_hours = $interval->h + ($interval->i / 60);
            if ($total_hours < 1) $total_hours = 1;
        }
    }
} catch (Exception $e) {
    error_log("DateTime error: " . $e->getMessage());
    $total_hours = 1;
}

// Calculate room cost
$room_cost = $total_hours * ($booking['price_hr'] ?? 0);

// Fetch food items for this booking from preorders table
$food_items = [];
$food_cost = 0;

$food_query = $conn->prepare("
    SELECT po.*, fb.item_name, fb.category, fb.price 
    FROM preorders po
    LEFT JOIN food_beverages fb ON po.f_id = fb.f_id
    WHERE po.b_id = ? AND po.status != 'cancelled'
");
$food_query->bind_param("i", $booking_id);
$food_query->execute();
$food_result = $food_query->get_result();

if ($food_result && $food_result->num_rows > 0) {
    while ($food = $food_result->fetch_assoc()) {
        $food['subtotal'] = ($food['price'] ?? 0) * ($food['quantity'] ?? 1);
        $food_cost += $food['subtotal'];
        $food_items[] = $food;
    }
}

// Calculate total costs
$total_cost = $room_cost + $food_cost;
$total_paid = floatval($booking['total_paid_amount'] ?? 0);
$deposit_amount = floatval($booking['deposit_amount'] ?? 0);

// Use the higher of deposit_amount or total_paid
$amount_paid = max($total_paid, $deposit_amount);

// Calculate taxes
$tax_rate = 0.18; // 18% tax
$tax_amount = $total_cost * $tax_rate;
$grand_total = $total_cost + $tax_amount;
$balance_due = $grand_total - $amount_paid;

// DETERMINE PAYMENT STATUS - IMPROVED LOGIC
$payment_status = 'Pending';
$payment_status_class = 'pending';

// Check 1: If balance is very small (due to rounding), consider it paid
if (abs($balance_due) < 0.01) {
    $balance_due = 0;
}

// Check 2: Actual payment status from payments table
if (!empty($booking['actual_payment_status'])) {
    $actual_status = strtolower($booking['actual_payment_status']);
    
    if (in_array($actual_status, ['completed', 'approved', 'paid', 'success'])) {
        $payment_status = 'Paid';
        $payment_status_class = 'paid';
    } elseif ($actual_status == 'partial') {
        $payment_status = 'Partial';
        $payment_status_class = 'partial';
    } elseif (in_array($actual_status, ['failed', 'cancelled', 'declined'])) {
        $payment_status = 'Failed';
        $payment_status_class = 'pending';
    }
} 
// Check 3: Booking table's payment_status field
elseif (!empty($booking['payment_status'])) {
    $booking_payment_status = strtolower($booking['payment_status']);
    
    if ($booking_payment_status == 'paid') {
        $payment_status = 'Paid';
        $payment_status_class = 'paid';
    } elseif ($booking_payment_status == 'partial') {
        $payment_status = 'Partial';
        $payment_status_class = 'partial';
    }
}
// Check 4: Calculate based on amount paid
else {
    if ($amount_paid >= $grand_total - 0.01) { // Small tolerance for floating point
        $payment_status = 'Paid';
        $payment_status_class = 'paid';
    } elseif ($amount_paid > 0) {
        $payment_status = 'Partial';
        $payment_status_class = 'partial';
    }
}

// Override if balance is zero or negative
if ($balance_due <= 0.01) {
    $payment_status = 'Paid';
    $payment_status_class = 'paid';
    $balance_due = 0;
}

// Status colors
$status_colors = [
    'confirmed' => '#00b894',
    'pending' => '#fdcb6e',
    'completed' => '#0984e3',
    'in-progress' => '#6c5ce7',
    'paid' => '#00b894',
    'approved' => '#00b894'
];

$status_color = $status_colors[strtolower($booking['status'] ?? 'pending')] ?? '#fdcb6e';

// Format dates and times
$booking_date = isset($booking['booking_date']) ? date('F d, Y', strtotime($booking['booking_date'])) : 'Not set';
$created_at = isset($booking['created_at']) ? date('F d, Y g:i A', strtotime($booking['created_at'])) : 'Not recorded';

// Format payment date if exists
$payment_date_formatted = 'Not paid yet';
if (!empty($booking['last_payment_date'])) {
    $payment_date_formatted = date('F d, Y g:i A', strtotime($booking['last_payment_date']));
}

// Format start and end times
$start_time_formatted = 'Not set';
$end_time_formatted = 'Not set';

try {
    if (isset($booking['start_time'])) {
        if (strlen($booking['start_time']) > 8) {
            $start_time_formatted = date('g:i A', strtotime($booking['start_time']));
        } else {
            $start_time_formatted = date('g:i A', strtotime($booking['start_time']));
        }
    }
    
    if (isset($booking['end_time'])) {
        if (strlen($booking['end_time']) > 8) {
            $end_time_formatted = date('g:i A', strtotime($booking['end_time']));
        } else {
            $end_time_formatted = date('g:i A', strtotime($booking['end_time']));
        }
    }
} catch (Exception $e) {
    error_log("Time formatting error: " . $e->getMessage());
}

// Check if booking can be cancelled
$can_cancel = in_array($booking['status'], ['pending', 'confirmed']) && $role != 'admin';

// Check if payment button should be shown
$show_payment_button = (
    $payment_status !== 'Paid' && 
    $balance_due > 0.01 && 
    !in_array($booking['status'], ['cancelled', 'completed'])
);

// For debugging - remove in production
error_log("Booking ID: $booking_id - Status: $payment_status, Balance: $balance_due, Amount Paid: $amount_paid, Grand Total: $grand_total");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - Sirene KTV</title>
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

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.05);
            padding: 25px;
            border-radius: 15px;
            border-left: 4px solid var(--highlight);
            flex-wrap: wrap;
            gap: 15px;
        }

        .booking-title h2 {
            font-size: 28px;
            color: var(--light);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .booking-title h2 i {
            color: var(--highlight);
        }

        .booking-title p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }

        .booking-badge {
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
            margin-left: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Booking Action Buttons */
        .action-buttons-section {
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .action-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        @media (max-width: 768px) {
            .action-buttons-grid {
                grid-template-columns: 1fr;
            }
        }

        .action-btn {
            padding: 15px 20px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
            text-align: center;
            min-height: 50px;
        }

        .action-btn.receipt {
            background: linear-gradient(135deg, var(--success), #00b894);
            color: white;
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .action-btn.receipt:hover {
            background: linear-gradient(135deg, #00b894, var(--success));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 184, 148, 0.3);
        }

        .action-btn.payment {
            background: linear-gradient(135deg, var(--info), #0984e3);
            color: white;
            border: 1px solid rgba(9, 132, 227, 0.3);
        }

        .action-btn.payment:hover {
            background: linear-gradient(135deg, #0984e3, var(--info));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(9, 132, 227, 0.3);
        }

        .action-btn.edit {
            background: linear-gradient(135deg, var(--warning), #fdcb6e);
            color: #333;
            border: 1px solid rgba(253, 203, 110, 0.3);
        }

        .action-btn.edit:hover {
            background: linear-gradient(135deg, #fdcb6e, var(--warning));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(253, 203, 110, 0.3);
        }

        .action-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 25px;
            transition: all 0.3s;
        }

        .info-card:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-header i {
            font-size: 20px;
            color: var(--highlight);
        }

        .card-header h3 {
            font-size: 18px;
            color: var(--light);
            flex: 1;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--light);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .info-value i {
            color: var(--highlight);
            width: 20px;
        }

        .room-info {
            background: rgba(233, 69, 96, 0.1);
            border-radius: 10px;
            padding: 20px;
            margin-top: 15px;
            border: 1px solid rgba(233, 69, 96, 0.2);
        }

        .room-info h4 {
            color: var(--highlight);
            margin-bottom: 10px;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .food-items {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .food-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.2s;
            flex-wrap: wrap;
            gap: 10px;
        }

        .food-item:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(5px);
        }

        .food-name {
            font-weight: 600;
            color: var(--light);
            flex: 2;
            min-width: 150px;
        }

        .food-category {
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
            flex: 1;
            min-width: 80px;
        }

        .food-quantity {
            background: var(--accent);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            color: var(--light);
            min-width: 60px;
            text-align: center;
        }

        .food-price {
            font-weight: 700;
            color: var(--highlight);
            min-width: 120px;
            text-align: right;
        }

        .food-status {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 12px;
            background: rgba(230, 126, 34, 0.15);
            color: #e67e22;
        }

        .food-status.prepared {
            background: rgba(0, 184, 148, 0.15);
            color: var(--success);
        }


        .cost-summary {
            background: rgba(9, 132, 227, 0.1);
            border-radius: 10px;
            padding: 20px;
            border: 1px solid rgba(9, 132, 227, 0.2);
        }

        .cost-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cost-row.total {
            border-top: 2px solid rgba(255, 255, 255, 0.2);
            border-bottom: none;
            margin-top: 10px;
            padding-top: 15px;
            font-size: 18px;
            font-weight: 700;
        }

        .cost-label {
            color: rgba(255, 255, 255, 0.8);
        }

        .cost-value {
            color: var(--light);
            font-weight: 600;
        }

        .total .cost-value {
            color: var(--highlight);
            font-size: 20px;
        }

        .payment-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .payment-status.paid {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .payment-status.partial {
            background: rgba(253, 203, 110, 0.2);
            color: var(--warning);
            border: 1px solid rgba(253, 203, 110, 0.3);
        }

        .payment-status.pending {
            background: rgba(214, 48, 49, 0.2);
            color: var(--danger);
            border: 1px solid rgba(214, 48, 49, 0.3);
        }

        .timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 20px;
        }

        .timeline:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--highlight);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 20px;
            padding-left: 20px;
        }

        .timeline-item:before {
            content: '';
            position: absolute;
            left: -10px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--highlight);
        }

        .timeline-time {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 5px;
        }

        .timeline-event {
            font-weight: 600;
            color: var(--light);
        }

        .timeline-desc {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 3px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-info {
            background: rgba(9, 132, 227, 0.2);
            border: 1px solid rgba(9, 132, 227, 0.3);
            color: var(--info);
        }

        .alert-success {
            background: rgba(0, 184, 148, 0.2);
            border: 1px solid rgba(0, 184, 148, 0.3);
            color: var(--success);
        }

        .alert-warning {
            background: rgba(253, 203, 110, 0.2);
            border: 1px solid rgba(253, 203, 110, 0.3);
            color: var(--warning);
        }

        .alert-danger {
            background: rgba(214, 48, 49, 0.2);
            border: 1px solid rgba(214, 48, 49, 0.3);
            color: var(--danger);
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
            flex-wrap: wrap;
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

        @media (max-width: 1024px) {
            .main-content {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            header {
                flex-direction: column;
                text-align: center;
            }
            
            .header-right {
                flex-direction: column;
                width: 100%;
            }
            
            .user-info, .back-btn, .logout-btn {
                width: 100%;
                justify-content: center;
            }
            
            .booking-header {
                flex-direction: column;
                text-align: center;
            }
            
            .booking-badge {
                margin-left: 0;
                margin-top: 10px;
            }
            
            .food-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .food-price {
                text-align: left;
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .booking-title h2 {
                font-size: 22px;
            }
            
            .info-card {
                padding: 20px;
            }
            
            .card-header h3 {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-left">
        <h1><i class="fas fa-microphone-alt"></i> Sirene KTV Booking Details</h1>
        <p>Booking #<?php echo $booking['b_id']; ?></p>
    </div>
    <div class="header-right">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <?php echo htmlspecialchars($name); ?> (<?php echo ucfirst($role); ?>)
        </div>
        
        <a href="my-bookings.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to My Bookings
        </a>
        
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
</header>

<div class="container">
    <!-- Payment Success Message (if any) -->
    <?php if (isset($_GET['payment']) && $_GET['payment'] == 'success'): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong>Payment Successful!</strong> Your payment has been processed successfully. Thank you for your booking.
        </div>
    </div>
    <?php endif; ?>

    <div class="booking-header">
        <div class="booking-title">
            <h2>
                <i class="fas fa-calendar-check"></i>
                Booking Details
                <span class="booking-badge" style="background: <?php echo $status_color; ?>20; color: <?php echo $status_color; ?>; border: 1px solid <?php echo $status_color; ?>30;">
                    <?php echo ucfirst($booking['status'] ?? 'pending'); ?>
                </span>
            </h2>
            <p>Created on <?php echo $created_at; ?></p>
        </div>
        <div>
            <span class="payment-status <?php echo $payment_status_class; ?>">
                <i class="fas fa-circle"></i> <?php echo $payment_status; ?> Payment
            </span>
        </div>
    </div>

    <!-- Booking Action Buttons -->
    <div class="action-buttons-section">
        <div class="action-buttons-grid">
            <a href="receipt.php?id=<?php echo $booking_id; ?>" class="action-btn receipt">
                <i class="fas fa-file-invoice"></i> View Receipt
            </a>
            
            <?php if ($show_payment_button): ?>
            <a href="payment.php?id=<?php echo $booking_id; ?>" class="action-btn payment">
                <i class="fas fa-credit-card"></i> Make Payment (₹<?php echo number_format($balance_due, 2); ?>)
            </a>
            <?php endif; ?>
            
            
            <?php if ($role == 'admin'): ?>
            <a href="admin/edit-booking.php?id=<?php echo $booking_id; ?>" class="action-btn edit">
                <i class="fas fa-edit"></i> Edit Booking
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Balance Due Alert -->
    <?php if ($balance_due > 0.01 && $payment_status !== 'Paid'): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Balance Due: ₹<?php echo number_format($balance_due, 2); ?></strong> - Please complete your payment to confirm the booking.
        </div>
    </div>
    <?php elseif ($payment_status === 'Paid'): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong>Payment Complete!</strong> This booking has been fully paid. Thank you for your payment.
        </div>
    </div>
    <?php endif; ?>

    <div class="main-content">
        <div>
            <!-- Booking Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>Booking Information</h3>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Booking ID</span>
                        <span class="info-value">#<?php echo $booking['b_id']; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Booking Date</span>
                        <span class="info-value"><i class="fas fa-calendar"></i> <?php echo $booking_date; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Time Slot</span>
                        <span class="info-value">
                            <i class="fas fa-clock"></i> 
                            <?php echo $start_time_formatted; ?> - <?php echo $end_time_formatted; ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Duration</span>
                        <span class="info-value">
                            <i class="fas fa-hourglass-half"></i> 
                            <?php echo number_format($total_hours, 1); ?> hours
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Guests</span>
                        <span class="info-value">
                            <i class="fas fa-user-friends"></i> 
                            <?php echo $booking['number_of_guests'] ?? ($booking['capcity'] ?? 1); ?> persons
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Special Requests</span>
                        <span class="info-value">
                            <i class="fas fa-comment"></i> 
                            <?php echo !empty($booking['special_requests']) ? htmlspecialchars($booking['special_requests']) : 'None'; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Room Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-door-closed"></i>
                    <h3>Room Details</h3>
                </div>
                <div class="room-info">
                    <h4><i class="fas fa-door-open"></i> <?php echo htmlspecialchars($booking['room_name'] ?? 'Room not specified'); ?></h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Capacity</span>
                            <span class="info-value"><?php echo $booking['capcity'] ?? 'N/A'; ?> persons</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Price per Hour</span>
                            <span class="info-value">₹<?php echo number_format($booking['price_hr'] ?? 0, 2); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Room Hours</span>
                            <span class="info-value"><?php echo number_format($total_hours, 1); ?> hours</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Room Cost</span>
                            <span class="info-value">₹<?php echo number_format($room_cost, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Food & Drinks from Preorders -->
            <?php if (!empty($food_items)): ?>
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-utensils"></i>
                    <h3>Food & Drinks (Pre-Orders)</h3>
                </div>
                <div class="food-items">
                    <?php foreach ($food_items as $food): ?>
                    <div class="food-item">
                        <div class="food-name"><?php echo htmlspecialchars($food['item_name'] ?? 'Unknown item'); ?></div>
                        <div class="food-category"><?php echo $food['category'] ?? 'General'; ?></div>
                        <div class="food-quantity">Qty: <?php echo $food['quantity']; ?></div>
                        <div class="food-status <?php echo $food['status']; ?>">
                            <?php echo ucfirst($food['status'] ?? 'pending'); ?>
                        </div>
                        <div class="food-price">
                            ₹<?php echo number_format($food['price'] ?? 0, 2); ?> × <?php echo $food['quantity']; ?> = 
                            ₹<?php echo number_format($food['subtotal'], 2); ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div>
            <!-- Cost Summary -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-receipt"></i>
                    <h3>Cost Summary</h3>
                </div>
                <div class="cost-summary">
                    <div class="cost-row">
                        <span class="cost-label">Room Charges (<?php echo number_format($total_hours, 1); ?> hrs)</span>
                        <span class="cost-value">₹<?php echo number_format($room_cost, 2); ?></span>
                    </div>
                    
                    <?php if (!empty($food_items)): ?>
                    <div class="cost-row">
                        <span class="cost-label">Food & Drinks (Pre-Orders)</span>
                        <span class="cost-value">₹<?php echo number_format($food_cost, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="cost-row">
                        <span class="cost-label">Subtotal</span>
                        <span class="cost-value">₹<?php echo number_format($total_cost, 2); ?></span>
                    </div>
                    
                    <div class="cost-row">
                        <span class="cost-label">Taxes & Service Charges (18%)</span>
                        <span class="cost-value">₹<?php echo number_format($tax_amount, 2); ?></span>
                    </div>
                    
                    <div class="cost-row total">
                        <span class="cost-label">Total Amount</span>
                        <span class="cost-value">₹<?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    
                    <div class="cost-row">
                        <span class="cost-label">Amount Paid</span>
                        <span class="cost-value" style="color: var(--success);">₹<?php echo number_format($amount_paid, 2); ?></span>
                    </div>
                    
                    <div class="cost-row total">
                        <span class="cost-label">Balance Due</span>
                        <span class="cost-value" style="color: <?php echo $balance_due > 0.01 ? 'var(--highlight)' : 'var(--success)'; ?>;">
                            ₹<?php echo number_format($balance_due, 2); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-credit-card"></i>
                    <h3>Payment Information</h3>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Payment Status</span>
                        <span class="info-value">
                            <i class="fas fa-circle" style="color: <?php 
                                echo $payment_status_class == 'paid' ? 'var(--success)' : 
                                     ($payment_status_class == 'partial' ? 'var(--warning)' : 'var(--danger)'); 
                            ?>;"></i>
                            <?php echo $payment_status; ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($booking['payment_methods'])): ?>
                    <div class="info-item">
                        <span class="info-label">Payment Method(s)</span>
                        <span class="info-value">
                            <i class="fas fa-wallet"></i>
                            <?php 
                            $methods = explode(', ', $booking['payment_methods']);
                            $method_names = [
                                'card' => 'Card',
                                'upi' => 'UPI',
                                'gcash' => 'G-Cash',
                                'paymaya' => 'PayMaya',
                                'paypal' => 'PayPal',
                                'bank_transfer' => 'Bank Transfer',
                                'cash' => 'Cash'
                            ];
                            $display_methods = array_map(function($m) use ($method_names) {
                                return $method_names[trim($m)] ?? ucfirst(str_replace('_', ' ', trim($m)));
                            }, $methods);
                            echo implode(', ', $display_methods);
                            ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($booking['last_payment_date'])): ?>
                    <div class="info-item">
                        <span class="info-label">Last Payment Date</span>
                        <span class="info-value">
                            <i class="fas fa-calendar-check"></i>
                            <?php echo $payment_date_formatted; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <span class="info-label">Total Amount Paid</span>
                        <span class="info-value">
                            <i class="fas fa-rupee-sign"></i>
                            ₹<?php echo number_format($amount_paid, 2); ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Customer Information -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-user"></i>
                    <h3>Customer Details</h3>
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Name</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['customer_name'] ?? $name); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['customer_email'] ?? 'Not provided'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($booking['customer_phone'] ?? 'Not provided'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Booking Timeline -->
            <div class="info-card">
                <div class="card-header">
                    <i class="fas fa-history"></i>
                    <h3>Booking Timeline</h3>
                </div>
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-time"><?php echo $created_at; ?></div>
                        <div class="timeline-event">Booking Created</div>
                        <div class="timeline-desc">Booking was initiated</div>
                    </div>
                    
                    <?php if (($booking['status'] ?? '') == 'confirmed'): ?>
                    <div class="timeline-item">
                        <div class="timeline-time">
                            <?php echo date('F d, Y g:i A', strtotime($booking['updated_at'] ?? $booking['created_at'] ?? 'now')); ?>
                        </div>
                        <div class="timeline-event">Booking Confirmed</div>
                        <div class="timeline-desc">Room has been reserved</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($amount_paid > 0): ?>
                    <div class="timeline-item">
                        <div class="timeline-time">
                            <?php echo !empty($booking['last_payment_date']) ? $payment_date_formatted : date('F d, Y g:i A', strtotime($booking['updated_at'] ?? $booking['created_at'] ?? 'now')); ?>
                        </div>
                        <div class="timeline-event">Payment Received</div>
                        <div class="timeline-desc">
                            ₹<?php echo number_format($amount_paid, 2); ?> paid
                            <?php if (!empty($booking['payment_methods'])): ?>
                            via <?php echo str_replace(',', ' &', $booking['payment_methods']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($booking['status'] == 'cancelled'): ?>
                    <div class="timeline-item">
                        <div class="timeline-time">
                            <?php echo date('F d, Y g:i A', strtotime($booking['updated_at'] ?? 'now')); ?>
                        </div>
                        <div class="timeline-event">Booking Cancelled</div>
                        <div class="timeline-desc">This booking has been cancelled</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
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

    // Initialize page animations
    document.addEventListener('DOMContentLoaded', function() {
        const cards = document.querySelectorAll('.info-card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s, transform 0.5s';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
        
        // Animate action buttons
        const actionButtons = document.querySelectorAll('.action-btn');
        actionButtons.forEach((btn, index) => {
            btn.style.opacity = '0';
            btn.style.transform = 'translateY(10px)';
            setTimeout(() => {
                btn.style.transition = 'opacity 0.5s, transform 0.5s';
                btn.style.opacity = '1';
                btn.style.transform = 'translateY(0)';
            }, 300 + (index * 100));
        });
    });
</script>

</body>
</html>