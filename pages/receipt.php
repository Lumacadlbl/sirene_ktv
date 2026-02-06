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

// Fetch booking details for receipt
$booking_query = $conn->prepare("
    SELECT b.*, r.room_name, r.price_hr, r.capcity,
           u.name as customer_name, u.email as customer_email, u.contact as customer_phone
    FROM booking b
    LEFT JOIN room r ON b.r_id = r.r_id
    LEFT JOIN user_tbl u ON b.u_id = u.id
    WHERE b.b_id = ? AND (b.u_id = ? OR ? = 'admin')
");
$booking_query->bind_param("iii", $booking_id, $user_id, $role);
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

// Calculate costs
$room_cost = $total_hours * ($booking['price_hr'] ?? 0);
$food_cost = $booking['food_total'] ?? 0;
$total_cost = $room_cost + $food_cost;
$deposit_amount = $booking['deposit_amount'] ?? 0;
$balance_due = $total_cost - $deposit_amount;

// Fetch food items for this booking
$food_items = [];
if (isset($booking['food_items']) && !empty($booking['food_items'])) {
    $food_ids = explode(',', $booking['food_items']);
    $food_quantities = isset($booking['food_quantities']) ? explode(',', $booking['food_quantities']) : [];
    
    if (!empty($food_ids)) {
        $food_ids = array_map('intval', $food_ids);
        $food_ids_str = implode(',', $food_ids);
        $food_query = $conn->query("SELECT * FROM food_beverages WHERE f_id IN ($food_ids_str)");
        if ($food_query) {
            while ($food = $food_query->fetch_assoc()) {
                $key = array_search($food['f_id'], $food_ids);
                $quantity = ($key !== false && isset($food_quantities[$key])) ? 
                            intval($food_quantities[$key]) : 1;
                $food['quantity'] = $quantity;
                $food['subtotal'] = ($food['price'] ?? 0) * $quantity;
                $food_items[] = $food;
            }
        }
    }
}

// Format dates and times
$booking_date = isset($booking['booking_date']) ? date('F d, Y', strtotime($booking['booking_date'])) : 'Not set';
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

// Calculate taxes and totals
$tax_rate = 0.18; // 18% tax
$tax_amount = $total_cost * $tax_rate;
$grand_total = $total_cost + $tax_amount;
$balance_due_with_tax = $grand_total - $deposit_amount;

// Receipt details
$receipt_number = 'REC-' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
$issue_date = date('F d, Y');
$issue_time = date('g:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - Booking #<?php echo $booking_id; ?> - Sirene KTV</title>
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
            padding: 20px;
        }

        header {
            background: rgba(10, 10, 20, 0.95);
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--highlight);
            border-radius: 10px 10px 0 0;
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

        .receipt-container {
            max-width: 900px;
            margin: 30px auto;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        /* Receipt Header */
        .receipt-header {
            padding: 30px;
            text-align: center;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 2px solid var(--highlight);
        }

        .receipt-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .receipt-logo i {
            font-size: 40px;
            color: var(--highlight);
        }

        .receipt-logo h2 {
            font-size: 32px;
            font-weight: 700;
            color: var(--light);
        }

        .receipt-title {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            letter-spacing: 1px;
            border-radius: 10px;
            margin: 15px auto;
            max-width: 400px;
        }

        .receipt-info {
            padding: 25px;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
            font-weight: 600;
        }

        .info-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--light);
        }

        .receipt-content {
            padding: 30px;
        }

        .section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--light);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--highlight);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--highlight);
        }

        .customer-details {
            background: rgba(9, 132, 227, 0.1);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(9, 132, 227, 0.2);
        }

        .room-details {
            background: rgba(233, 69, 96, 0.1);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(233, 69, 96, 0.2);
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
        }

        .items-table th {
            background: rgba(26, 26, 46, 0.8);
            color: var(--light);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            border-bottom: 2px solid var(--highlight);
        }

        .items-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--light);
        }

        .items-table tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.03);
        }

        .items-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .cost-summary {
            background: rgba(9, 132, 227, 0.1);
            padding: 25px;
            border-radius: 10px;
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
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-paid {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .status-partial {
            background: rgba(253, 203, 110, 0.2);
            color: var(--warning);
            border: 1px solid rgba(253, 203, 110, 0.3);
        }

        .status-pending {
            background: rgba(214, 48, 49, 0.2);
            color: var(--danger);
            border: 1px solid rgba(214, 48, 49, 0.3);
        }

        .receipt-footer {
            padding: 25px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }

        .footer-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
            text-align: center;
        }

        .footer-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .footer-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--light);
            margin-top: 10px;
        }

        .terms {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 20px;
            line-height: 1.5;
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .terms p {
            margin-bottom: 8px;
        }

        .terms strong {
            color: var(--light);
        }

        footer {
            text-align: center;
            padding: 22px;
            background: rgba(10, 10, 20, 0.95);
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
            border-radius: 0 0 10px 10px;
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

        /* Watermark */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(233, 69, 96, 0.05);
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
            white-space: nowrap;
        }

        /* Special notes styling */
        .special-notes {
            background: rgba(253, 203, 110, 0.1);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(253, 203, 110, 0.2);
            color: rgba(255, 255, 255, 0.9);
            font-style: italic;
        }

        /* Print styles */
        @media print {
            body {
                background: white !important;
                color: black !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            header, footer, .back-btn, .user-info, .watermark {
                display: none !important;
            }

            .receipt-container {
                max-width: 100% !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
                background: white !important;
            }

            .receipt-header {
                background: white !important;
                border-bottom: 2px solid black !important;
            }

            .receipt-logo h2 {
                color: black !important;
            }

            .receipt-title {
                background: #333 !important;
                color: white !important;
            }

            .info-label, .footer-label {
                color: #666 !important;
            }

            .info-value, .footer-value, .section-title, .cost-label, .cost-value {
                color: black !important;
            }

            .items-table th {
                background: #333 !important;
                color: white !important;
            }

            .items-table td {
                color: black !important;
            }

            .customer-details, .room-details, .cost-summary, .special-notes {
                background: #f5f5f5 !important;
                border: 1px solid #ddd !important;
            }

            .terms {
                background: #f5f5f5 !important;
                border: 1px solid #ddd !important;
                color: #666 !important;
            }

            @page {
                margin: 20mm;
            }
        }

        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-right {
                flex-direction: column;
                width: 100%;
            }
            
            .user-info, .back-btn {
                width: 100%;
                justify-content: center;
            }
            
            .receipt-container {
                margin: 15px;
            }
            
            .items-table {
                display: block;
                overflow-x: auto;
            }
            
            .watermark {
                font-size: 80px;
            }
        }

        @media (max-width: 480px) {
            .receipt-logo h2 {
                font-size: 24px;
            }
            
            .receipt-title {
                font-size: 20px;
            }
            
            .section-title {
                font-size: 16px;
            }
            
            .watermark {
                font-size: 60px;
            }
        }
    </style>
</head>
<body>
    <!-- Watermark -->
    <div class="watermark">SIRENE KTV</div>

    <header>
        <div class="header-left">
            <h1><i class="fas fa-microphone-alt"></i> Sirene KTV Receipt</h1>
            <p>Official Receipt for Booking #<?php echo $booking_id; ?></p>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($name); ?> (<?php echo ucfirst($role); ?>)
            </div>
            
            <a href="booking-details.php?id=<?php echo $booking_id; ?>" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Booking
            </a>
        </div>
    </header>

    <div class="receipt-container">
        <!-- Receipt Header -->
        <div class="receipt-header">
            <div class="receipt-logo">
                <i class="fas fa-file-invoice"></i>
                <h2>OFFICIAL RECEIPT</h2>
            </div>
            
            <div class="receipt-title">
                Sirene KTV Booking Receipt
            </div>
            
            <div style="color: rgba(255,255,255,0.7); font-size: 14px; margin-top: 10px;">
                <p><i class="fas fa-phone"></i> +1-800-KTV-SING | <i class="fas fa-envelope"></i> receipt@sirenektv.com</p>
            </div>
        </div>

        <!-- Receipt Info -->
        <div class="receipt-info">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Receipt Number</span>
                    <span class="info-value"><?php echo $receipt_number; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Booking ID</span>
                    <span class="info-value">#<?php echo $booking_id; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Issue Date</span>
                    <span class="info-value"><?php echo $issue_date; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Issue Time</span>
                    <span class="info-value"><?php echo $issue_time; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Payment Status</span>
                    <span class="info-value">
                        <span class="payment-status status-<?php echo strtolower($deposit_amount >= $grand_total ? 'paid' : ($deposit_amount > 0 ? 'partial' : 'pending')); ?>">
                            <?php echo $deposit_amount >= $grand_total ? 'PAID' : ($deposit_amount > 0 ? 'PARTIAL' : 'PENDING'); ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Receipt Content -->
        <div class="receipt-content">
            <!-- Customer Details -->
            <div class="section">
                <h2 class="section-title"><i class="fas fa-user"></i> Customer Information</h2>
                <div class="customer-details">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Customer Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['customer_name'] ?? $name); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Email Address</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['customer_email'] ?? 'Not provided'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Phone Number</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['customer_phone'] ?? 'Not provided'); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Booking Details -->
            <div class="section">
                <h2 class="section-title"><i class="fas fa-calendar-check"></i> Booking Details</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Booking Date</span>
                        <span class="info-value"><?php echo $booking_date; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Time Slot</span>
                        <span class="info-value"><?php echo $start_time_formatted; ?> - <?php echo $end_time_formatted; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Duration</span>
                        <span class="info-value"><?php echo number_format($total_hours, 1); ?> hours</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Number of Guests</span>
                        <span class="info-value"><?php echo $booking['number_of_guests'] ?? ($booking['capcity'] ?? 1); ?> persons</span>
                    </div>
                </div>
            </div>

            <!-- Room Details -->
            <div class="section">
                <h2 class="section-title"><i class="fas fa-door-closed"></i> Room Information</h2>
                <div class="room-details">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="info-label">Room Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($booking['room_name'] ?? 'Room not specified'); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Room Capacity</span>
                            <span class="info-value"><?php echo $booking['capcity'] ?? 'N/A'; ?> persons</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Price per Hour</span>
                            <span class="info-value">₹<?php echo number_format($booking['price_hr'] ?? 0, 2); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Booking Hours</span>
                            <span class="info-value"><?php echo number_format($total_hours, 1); ?> hours</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Food Items -->
            <?php if (!empty($food_items)): ?>
            <div class="section">
                <h2 class="section-title"><i class="fas fa-utensils"></i> Food & Beverages</h2>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Category</th>
                            <th class="text-center">Quantity</th>
                            <th class="text-right">Unit Price</th>
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($food_items as $food): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($food['item_name'] ?? 'Unknown item'); ?></td>
                            <td><?php echo $food['category'] ?? 'General'; ?></td>
                            <td class="text-center"><?php echo $food['quantity']; ?></td>
                            <td class="text-right">₹<?php echo number_format($food['price'] ?? 0, 2); ?></td>
                            <td class="text-right">₹<?php echo number_format($food['subtotal'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <!-- Cost Summary -->
            <div class="section">
                <h2 class="section-title"><i class="fas fa-calculator"></i> Payment Summary</h2>
                <div class="cost-summary">
                    <div class="cost-row">
                        <span class="cost-label">Room Charges (<?php echo number_format($total_hours, 1); ?> hrs)</span>
                        <span class="cost-value">₹<?php echo number_format($room_cost, 2); ?></span>
                    </div>
                    
                    <?php if (!empty($food_items)): ?>
                    <div class="cost-row">
                        <span class="cost-label">Food & Beverages</span>
                        <span class="cost-value">₹<?php echo number_format($food_cost, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="cost-row">
                        <span class="cost-label">Subtotal</span>
                        <span class="cost-value">₹<?php echo number_format($total_cost, 2); ?></span>
                    </div>
                    
                    <div class="cost-row">
                        <span class="cost-label">Tax (GST 18%)</span>
                        <span class="cost-value">₹<?php echo number_format($tax_amount, 2); ?></span>
                    </div>
                    
                    <div class="cost-row total">
                        <span class="cost-label">Grand Total</span>
                        <span class="cost-value">₹<?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    
                    <div class="cost-row">
                        <span class="cost-label">Deposit Paid</span>
                        <span class="cost-value">₹<?php echo number_format($deposit_amount, 2); ?></span>
                    </div>
                    
                    <div class="cost-row total">
                        <span class="cost-label">Balance Due</span>
                        <span class="cost-value">₹<?php echo number_format($balance_due_with_tax, 2); ?></span>
                    </div>
                </div>
            </div>

            <!-- Payment Information -->
            <div class="section">
                <h2 class="section-title"><i class="fas fa-credit-card"></i> Payment Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Payment Method</span>
                        <span class="info-value"><?php echo $booking['payment_method'] ?? 'Cash / Card'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Transaction ID</span>
                        <span class="info-value"><?php echo $booking['transaction_id'] ?? 'Not provided'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Payment Date</span>
                        <span class="info-value"><?php echo isset($booking['payment_date']) ? date('F d, Y', strtotime($booking['payment_date'])) : 'Not paid'; ?></span>
                    </div>
                </div>
            </div>

            <!-- Special Notes -->
            <?php if (!empty($booking['special_requests'])): ?>
            <div class="section">
                <h2 class="section-title"><i class="fas fa-sticky-note"></i> Special Requests</h2>
                <div class="special-notes">
                    <p><?php echo htmlspecialchars($booking['special_requests']); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Receipt Footer -->
        <div class="receipt-footer">
            <div class="footer-grid">
                <div class="footer-item">
                    <span class="footer-label">Customer Signature</span>
                    <span class="footer-value">_________________</span>
                </div>
                <div class="footer-item">
                    <span class="footer-label">Manager Signature</span>
                    <span class="footer-value">_________________</span>
                </div>
                <div class="footer-item">
                    <span class="footer-label">Date</span>
                    <span class="footer-value"><?php echo $issue_date; ?></span>
                </div>
            </div>
            
            <div class="terms">
                <p><strong>Terms & Conditions:</strong></p>
                <p>1. This receipt is valid for accounting purposes only.</p>
                <p>2. Cancellation policy: 50% refund if cancelled 48 hours before booking.</p>
                <p>3. Any damages to the room will be charged separately.</p>
                <p>4. Management reserves the right to refuse service.</p>
                <p>5. For any queries, contact support@sirenektv.com or call +1-800-KTV-SING.</p>
            </div>
        </div>

        <!-- Footer -->
        <footer>
            <p>&copy; 2024 Sirene KTV. All Rights Reserved.</p>
            <div class="footer-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="my-bookings.php">My Bookings</a>
                <a href="#">Help</a>
                <a href="#">Contact</a>
            </div>
        </footer>
    </div>

    <script>
        // Add receipt number to page title when printing
        const originalTitle = document.title;
        window.addEventListener('beforeprint', () => {
            document.title = 'Receipt_<?php echo $receipt_number; ?>_Sirene_KTV';
        });
        window.addEventListener('afterprint', () => {
            document.title = originalTitle;
        });

        // Add watermark effect
        document.addEventListener('DOMContentLoaded', function() {
            const watermark = document.querySelector('.watermark');
            let angle = -45;
            
            // Animate watermark on hover
            document.addEventListener('mousemove', function(e) {
                const x = (e.clientX / window.innerWidth) * 20 - 10;
                const y = (e.clientY / window.innerHeight) * 20 - 10;
                watermark.style.transform = `translate(-50%, -50%) rotate(${angle}deg) translate(${x}px, ${y}px)`;
            });
            
            // Add page load animation
            const receipt = document.querySelector('.receipt-container');
            receipt.style.opacity = '0';
            receipt.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                receipt.style.transition = 'opacity 0.5s, transform 0.5s';
                receipt.style.opacity = '1';
                receipt.style.transform = 'translateY(0)';
            }, 100);
        });
    </script>
</body>
</html>