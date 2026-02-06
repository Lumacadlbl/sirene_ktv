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

// Fetch user's bookings - use booking.payment_status since you have it in booking table
$bookings_query = $conn->prepare("
    SELECT b.*, r.room_name, r.capcity, r.price_hr,
           b.status as booking_status,
           b.payment_status as payment_status,  -- Get from booking table
           p.payment_status as payments_status,  -- Also get from payments table for reference
           p.amount as paid_amount,
           p.payment_method,
           p.payment_date
    FROM booking b 
    JOIN room r ON b.r_id = r.r_id 
    LEFT JOIN payments p ON b.b_id = p.b_id
    WHERE b.u_id = ?
    ORDER BY b.booking_date DESC, b.start_time DESC
");

if (!$bookings_query) {
    die("Prepare failed: " . $conn->error);
}

$bookings_query->bind_param("i", $user_id);
$bookings_query->execute();
$bookings_result = $bookings_query->get_result();
$bookings = $bookings_result->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Sirene KTV</title>
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

        .bookings-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .booking-card {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
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
        }

        @media (max-width: 768px) {
            .booking-actions {
                flex-direction: column;
            }
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
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

        .status-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            align-items: flex-end;
        }

        .booking-approval-status {
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
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

        .reason-section select {
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
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 12px;
            color: var(--light);
            font-size: 16px;
            resize: vertical;
            min-height: 100px;
        }

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
                flex-direction: column;
                gap: 15px;
            }
            
            .header-right {
                flex-direction: column;
                width: 100%;
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
                gap: 15px;
                align-items: flex-start;
            }
            
            .status-container {
                align-items: flex-start;
            }
            
            .booking-details {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 95%;
                margin: 10px;
            }
            
            .modal-footer {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .action-btn {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <h1><i class="fas fa-microphone-alt"></i> Sirene KTV</h1>
            <p>My Bookings Management</p>
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
                <h2><i class="fas fa-calendar-check"></i> My Bookings</h2>
                <p>View and manage all your KTV bookings</p>
            </div>
        </div>
        
        <?php if (!empty($bookings)): ?>
            <div class="stats-cards">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="stat-number"><?php echo count($bookings); ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                
                <?php
                $upcoming = array_filter($bookings, function($b) {
                    $bookingTime = strtotime($b['booking_date'] . ' ' . $b['end_time']);
                    $currentTime = time();
                    $bookingStatus = isset($b['booking_status']) ? strtolower($b['booking_status']) : 'pending';
                    return $bookingTime > $currentTime && $bookingStatus !== 'cancelled';
                });
                ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo count($upcoming); ?></div>
                    <div class="stat-label">Upcoming</div>
                </div>
                
                <?php
                $total_spent = 0;
                $paid_bookings = 0;
                foreach ($bookings as $booking) {
                    $paymentStatus = isset($booking['payment_status']) ? strtolower($booking['payment_status']) : null;
                    $bookingStatus = isset($booking['booking_status']) ? strtolower($booking['booking_status']) : 'pending';
                    
                    if ($paymentStatus == 'paid' || $paymentStatus == 'approved' || $bookingStatus == 'approved') {
                        $total_spent += $booking['total_amount'];
                        $paid_bookings++;
                    }
                }
                ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-number">₹<?php echo number_format($total_spent, 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo $paid_bookings; ?></div>
                    <div class="stat-label">Paid Bookings</div>
                </div>
            </div>
            
            <div class="bookings-list">
                <?php foreach ($bookings as $booking): 
                    // Determine overall status - use booking.payment_status since it's in the booking table
                    $bookingStatus = isset($booking['booking_status']) ? strtolower($booking['booking_status']) : 'pending';
                    $paymentStatus = isset($booking['payment_status']) ? strtolower($booking['payment_status']) : 'pending';
                    
                    $status_text = 'Pending';
                    $status_class = 'status-pending';
                    
                    // Priority: cancelled > completed > payment status > booking status
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
                    
                    // Check if booking is upcoming
                    $bookingTime = strtotime($booking['booking_date'] . ' ' . $booking['end_time']);
                    $isUpcoming = $bookingTime > time();
                    $timeBadge = $isUpcoming ? 'upcoming-badge' : 'past-badge';
                    $timeBadgeText = $isUpcoming ? 'UPCOMING' : 'PAST';
                    
                    // Determine if booking can be cancelled
                    $canCancel = ($status_text == 'Pending Approval' || $status_text == 'Approved' || $status_text == 'Confirmed') && 
                                 $isUpcoming && $bookingStatus != 'cancelled';
                    
                    // Determine if payment button should be shown
                    $showPaymentBtn = ($paymentStatus == 'pending' || $paymentStatus == '') && 
                                     $bookingStatus != 'cancelled' && 
                                     $bookingStatus != 'rejected' && 
                                     ($bookingStatus == 'approved' || $bookingStatus == 'confirmed');
                    
                    // Show approval status badge
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
                    
                    // Calculate hours
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
                            <div class="status-container">
                                <?php if ($approvalBadge): ?>
                                    <div class="<?php echo $approvalBadgeClass; ?>">
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
                            <div class="amount-value">₹<?php echo number_format($booking['total_amount'], 2); ?></div>
                            <?php if ($booking['paid_amount'] > 0): ?>
                                <div style="margin-top: 10px; color: rgba(255,255,255,0.7); font-size: 14px;">
                                    Paid: ₹<?php echo number_format($booking['paid_amount'], 2); ?>
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
                <i class="fas fa-calendar-times"></i>
                <h3>No Bookings Yet</h3>
                <p>You haven't made any bookings yet. Start your KTV experience by booking a room!</p>
                <a href="dashboard.php?tab=rooms" class="btn">
                    <i class="fas fa-door-closed"></i> Browse Available Rooms
                </a>
            </div>
        <?php endif; ?>
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
                    <strong>Warning:</strong> Cancelling a booking cannot be undone. You may be subject to cancellation fees depending on how close to the booking date you cancel.
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
        function openCancelModal(bookingId, roomName, bookingDate, startTime, endTime, totalAmount) {
            currentBookingId = bookingId;
            
            // Set the booking ID in the form
            document.getElementById('cancelBookingId').value = bookingId;
            
            // Update modal booking info
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
                    <span class="info-value" style="color: var(--highlight);">₹${parseFloat(totalAmount).toFixed(2)}</span>
                </div>
            `;
            
            // Show the modal
            document.getElementById('cancelModal').style.display = 'flex';
            
            // Prevent body scrolling
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
            
            // Ask for confirmation
            if (!confirm('Are you sure you want to cancel this booking? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const confirmBtn = this.querySelector('.modal-btn-confirm');
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            confirmBtn.disabled = true;
            
            return true;
        });
        
        // Add page load animation
        document.addEventListener('DOMContentLoaded', function() {
            const bookings = document.querySelectorAll('.booking-card');
            bookings.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.5s, transform 0.5s';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>