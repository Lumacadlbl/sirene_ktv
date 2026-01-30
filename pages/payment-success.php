<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$booking_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Fetch payment details
$payment_query = $conn->prepare("
    SELECT p.*, b.booking_date, b.start_time, b.end_time, r.room_name 
    FROM payments p
    JOIN booking b ON p.b_id = b.b_id
    JOIN room r ON b.r_id = r.r_id
    WHERE p.b_id = ? AND p.u_id = ?
");

$payment_query->bind_param("ii", $booking_id, $user_id);
$payment_query->execute();
$payment_result = $payment_query->get_result();
$payment = $payment_result->fetch_assoc();

if (!$payment) {
    header("Location: my-bookings.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --highlight: #e94560;
            --success: #00b894;
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
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .success-icon {
            font-size: 80px;
            color: var(--success);
            margin-bottom: 20px;
        }
        
        .success-title {
            font-size: 36px;
            background: linear-gradient(90deg, var(--success), #00a085);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 15px;
        }
        
        .payment-info {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
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
        }
        
        .info-label {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .info-value {
            font-weight: bold;
            color: var(--light);
        }
        
        .amount-display {
            background: rgba(0, 184, 148, 0.1);
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border: 1px solid rgba(0, 184, 148, 0.2);
        }
        
        .amount-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .amount-value {
            color: var(--success);
            font-size: 32px;
            font-weight: bold;
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            flex: 1;
            padding: 15px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--success), #00a085);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #00a085, var(--success));
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
        
        @media (max-width: 768px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .container {
                padding: 25px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1 class="success-title">Payment Successful!</h1>
        <p style="color: rgba(255, 255, 255, 0.8); margin-bottom: 25px;">
            Your booking has been confirmed. A confirmation email has been sent to your registered email address.
        </p>
        
        <div class="payment-info">
            <div class="info-row">
                <span class="info-label">Payment ID:</span>
                <span class="info-value">#<?php echo str_pad($payment['p_id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Booking ID:</span>
                <span class="info-value">#<?php echo str_pad($payment['b_id'], 6, '0', STR_PAD_LEFT); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Room:</span>
                <span class="info-value"><?php echo htmlspecialchars($payment['room_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Date:</span>
                <span class="info-value"><?php echo date('F j, Y g:i A', strtotime($payment['payment_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Payment Method:</span>
                <span class="info-value"><?php echo ucfirst($payment['payment_method']); ?></span>
            </div>
        </div>
        
        <div class="amount-display">
            <div class="amount-label">Amount Paid</div>
            <div class="amount-value">₹<?php echo number_format($payment['amount'], 2); ?></div>
        </div>
        
        <div class="action-buttons">
            <a href="my-bookings.php" class="btn btn-primary">
                <i class="fas fa-list-alt"></i> View My Bookings
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>