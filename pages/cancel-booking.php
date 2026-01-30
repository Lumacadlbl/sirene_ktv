<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$booking_id = $_GET['id'] ?? 0;

// Fetch booking details
$booking_query = $conn->prepare("
    SELECT b.*, r.room_name, r.price_hr, 
           p.payment_status as pay_status
    FROM booking b 
    JOIN room r ON b.r_id = r.r_id 
    LEFT JOIN payments p ON b.b_id = p.b_id
    WHERE b.b_id = ? AND b.u_id = ?
");

$booking_query->bind_param("ii", $booking_id, $user_id);
$booking_query->execute();
$booking_result = $booking_query->get_result();
$booking = $booking_result->fetch_assoc();

if (!$booking) {
    header("Location: my-bookings.php?error=booking_not_found");
    exit;
}

// Check if booking can be cancelled
$can_cancel = true;
$error_message = '';

// Check if already cancelled
if ($booking['payment_status'] == 'cancelled') {
    $can_cancel = false;
    $error_message = 'This booking is already cancelled.';
}

// Check if already paid
if ($booking['pay_status'] == 'approved') {
    $can_cancel = false;
    $error_message = 'This booking has already been paid and cannot be cancelled.';
}

// Check if booking is in the past
$booking_datetime = strtotime($booking['booking_date'] . ' ' . $booking['start_time']);
if ($booking_datetime < time()) {
    $can_cancel = false;
    $error_message = 'This booking has already passed and cannot be cancelled.';
}

// Check if booking is within 24 hours
$hours_until_booking = ($booking_datetime - time()) / 3600;
if ($hours_until_booking < 24) {
    $can_cancel = false;
    $error_message = 'Bookings can only be cancelled at least 24 hours before the scheduled time.';
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_cancel'])) {
    if (!$can_cancel) {
        header("Location: my-bookings.php?error=cannot_cancel");
        exit;
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update booking status to cancelled
        $update_booking = $conn->prepare("UPDATE booking SET payment_status = 'cancelled' WHERE b_id = ?");
        $update_booking->bind_param("i", $booking_id);
        $update_booking->execute();
        
        // Update payment status to cancelled if payment exists
        if ($booking['pay_status']) {
            $update_payment = $conn->prepare("UPDATE payments SET payment_status = 'cancelled' WHERE b_id = ?");
            $update_payment->bind_param("i", $booking_id);
            $update_payment->execute();
        }
        
        // Make room available again
        $update_room = $conn->prepare("UPDATE room SET status = 'Available' WHERE r_id = ?");
        $update_room->bind_param("i", $booking['r_id']);
        $update_room->execute();
        
        $conn->commit();
        
        // Redirect to success page
        echo "<script>
            alert('Booking cancelled successfully!');
            window.location.href = 'my-bookings.php';
        </script>";
        exit;
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "An error occurred. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Booking - Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --highlight: #e94560;
            --warning: #fdcb6e;
            --danger: #d63031;
            --light: #f5f5f5;
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
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            font-size: 36px;
            background: linear-gradient(90deg, var(--danger), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 10px;
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .booking-info {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .info-label {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .info-value {
            font-weight: bold;
            color: var(--light);
        }
        
        .warning-box {
            background: rgba(253, 203, 110, 0.1);
            border: 1px solid rgba(253, 203, 110, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .warning-icon {
            font-size: 40px;
            color: var(--warning);
            margin-bottom: 15px;
        }
        
        .warning-text {
            color: var(--warning);
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .warning-details {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }
        
        .error-box {
            background: rgba(214, 48, 49, 0.1);
            border: 1px solid rgba(214, 48, 49, 0.3);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
            text-align: center;
        }
        
        .error-icon {
            font-size: 40px;
            color: var(--danger);
            margin-bottom: 15px;
        }
        
        .error-text {
            color: var(--danger);
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .cancellation-form {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        select, textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 12px 15px;
            color: var(--light);
            font-size: 16px;
            transition: all 0.2s;
        }
        
        textarea {
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
        }
        
        select:focus, textarea:focus {
            outline: none;
            border-color: var(--highlight);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .amount-box {
            background: rgba(233, 69, 96, 0.1);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
            border: 1px solid rgba(233, 69, 96, 0.2);
        }
        
        .amount-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .amount-value {
            color: var(--highlight);
            font-size: 32px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .action-btn {
            flex: 1;
            padding: 15px;
            border-radius: 10px;
            border: none;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 16px;
            text-decoration: none;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #ff4757);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #ff4757, var(--danger));
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .refund-info {
            background: rgba(0, 184, 148, 0.1);
            border: 1px solid rgba(0, 184, 148, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .refund-icon {
            color: #00b894;
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .container {
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <a href="my-bookings.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Bookings
    </a>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-times-circle"></i> Cancel Booking</h1>
            <p>Cancel your KTV booking</p>
        </div>
        
        <div class="booking-info">
            <div class="info-row">
                <span class="info-label">Booking ID:</span>
                <span class="info-value">#<?php echo str_pad($booking['b_id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Room:</span>
                <span class="info-value"><?php echo htmlspecialchars($booking['room_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value"><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Time:</span>
                <span class="info-value">
                    <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                    <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value" style="color: <?php echo $booking['pay_status'] == 'approved' ? '#00b894' : '#fdcb6e'; ?>">
                    <?php echo ucfirst($booking['pay_status'] ?? 'pending'); ?>
                </span>
            </div>
        </div>
        
        <?php if (!$can_cancel): ?>
            <div class="error-box">
                <div class="error-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="error-text">Cannot Cancel Booking</div>
                <div class="warning-details"><?php echo $error_message; ?></div>
                
                <div class="action-buttons" style="margin-top: 20px;">
                    <a href="my-bookings.php" class="action-btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Bookings
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="warning-box">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="warning-text">Are you sure you want to cancel?</div>
                <div class="warning-details">
                    This action cannot be undone. The room will become available for others to book.
                </div>
            </div>
            
            <div class="amount-box">
                <div class="amount-label">Booking Amount</div>
                <div class="amount-value">₹<?php echo number_format($booking['total_amount'], 2); ?></div>
            </div>
            
            <form method="POST" class="cancellation-form">
                <div class="form-group">
                    <label for="reason">Reason for Cancellation</label>
                    <select id="reason" name="reason" required>
                        <option value="">Select a reason...</option>
                        <option value="Change of plans">Change of plans</option>
                        <option value="Found alternative">Found alternative</option>
                        <option value="Schedule conflict">Schedule conflict</option>
                        <option value="Too expensive">Too expensive</option>
                        <option value="Other">Other reason</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="comments">Additional Comments (Optional)</label>
                    <textarea id="comments" name="comments" placeholder="Any additional details..."></textarea>
                </div>
                
                <?php if ($booking['pay_status'] == 'approved'): ?>
                    <div class="refund-info">
                        <i class="fas fa-undo-alt refund-icon"></i>
                        A refund will be processed to your original payment method within 5-7 business days.
                    </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <button type="submit" name="confirm_cancel" class="action-btn btn-danger">
                        <i class="fas fa-times-circle"></i> Confirm Cancellation
                    </button>
                    <a href="my-bookings.php" class="action-btn btn-secondary">
                        <i class="fas fa-times"></i> Go Back
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>