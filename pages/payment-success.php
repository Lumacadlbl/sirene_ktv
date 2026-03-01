<?php
session_start();
include "../db.php";

// Turn on error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$session_id = isset($_GET['session_id']) ? $_GET['session_id'] : '';
$method = isset($_GET['method']) ? $_GET['method'] : 'online';

// Debug - log what we received
error_log("Payment Success Page - Booking ID: $booking_id, Session ID: $session_id, Method: $method");

if (!$booking_id) {
    header("Location: my-bookings.php");
    exit;
}

$payment_success = false;
$message = '';
$booking_details = null;

// First, check if the booking is already marked as paid in our database
$check_booking = $conn->prepare("SELECT b.*, r.room_name FROM booking b LEFT JOIN room r ON b.r_id = r.r_id WHERE b.b_id = ?");
$check_booking->bind_param("i", $booking_id);
$check_booking->execute();
$booking_result = $check_booking->get_result();
$booking_details = $booking_result->fetch_assoc();

if ($booking_details && $booking_details['payment_status'] == 'paid') {
    // Already paid in our database
    $payment_success = true;
    $message = "Payment already confirmed!";
} 
// If not paid in our database, check with PayMongo
else if ($session_id && $session_id != '{CHECKOUT_SESSION_ID}' && $method != 'store') {
    
    // Load secret key from config
    $config = require __DIR__ . '/../secret.php';
    $paymongo_secret_key = $config['PAYMONGO_SECRET_KEY'];
    
    // Verify payment with PayMongo
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paymongo.com/v1/checkout_sessions/" . $session_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($paymongo_secret_key . ':'),
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        $message = "Connection Error: " . curl_error($ch);
        error_log("CURL Error: " . curl_error($ch));
    } else {
        error_log("PayMongo Response: " . $response);
        $session = json_decode($response, true);
        
        // Check if we got a valid response
        if ($http_code == 200 && isset($session['data'])) {
            
            // Check payment status from the session
            $payment_status = $session['data']['attributes']['status'] ?? 'unknown';
            
            // Check if there are any payments attached
            $payments = $session['data']['attributes']['payments'] ?? [];
            $is_paid = ($payment_status == 'paid');
            
            // Also check payments array if available
            if (!$is_paid && !empty($payments)) {
                foreach ($payments as $payment) {
                    if (isset($payment['attributes']['status']) && $payment['attributes']['status'] == 'paid') {
                        $is_paid = true;
                        break;
                    }
                }
            }
            
            if ($is_paid) {
                // Get user_id from booking
                $user_id = $booking_details['u_id'] ?? 0;
                
                // Start transaction
                $conn->begin_transaction();
                
                try {
                    // 1. Update booking table
                    $update_booking = $conn->prepare("UPDATE booking SET payment_status = 'paid', downpayment = total_amount WHERE b_id = ?");
                    $update_booking->bind_param("i", $booking_id);
                    $update_booking->execute();
                    
                    // 2. Check if payment record exists in payments table
                    $check_payment = $conn->prepare("SELECT * FROM payments WHERE b_id = ?");
                    $check_payment->bind_param("i", $booking_id);
                    $check_payment->execute();
                    $payment_result = $check_payment->get_result();
                    
                    if ($payment_result && $payment_result->num_rows > 0) {
                        // Update existing payment record
                        $update_payment = $conn->prepare("
                            UPDATE payments 
                            SET payment_status = 'paid',
                                payment_date = NOW()
                            WHERE b_id = ?
                        ");
                        $update_payment->bind_param("i", $booking_id);
                        $update_payment->execute();
                    } else {
                        // Insert new payment record
                        $insert_payment = $conn->prepare("
                            INSERT INTO payments (b_id, u_id, amount, payment_method, payment_status, payment_date) 
                            VALUES (?, ?, ?, 'online', 'paid', NOW())
                        ");
                        $insert_payment->bind_param("iid", $booking_id, $user_id, $booking_details['total_amount']);
                        $insert_payment->execute();
                    }
                    
                    $conn->commit();
                    $payment_success = true;
                    $message = "Payment verified and recorded successfully!";
                    
                    // Refresh booking details
                    $check_booking = $conn->prepare("SELECT b.*, r.room_name FROM booking b LEFT JOIN room r ON b.r_id = r.r_id WHERE b.b_id = ?");
                    $check_booking->bind_param("i", $booking_id);
                    $check_booking->execute();
                    $booking_result = $check_booking->get_result();
                    $booking_details = $booking_result->fetch_assoc();
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $message = "Database error: " . $e->getMessage();
                    error_log("Database Error: " . $e->getMessage());
                }
            } else {
                $message = "Payment not completed. Status from PayMongo: " . $payment_status;
            }
        } else {
            $message = "Unable to verify payment with PayMongo. HTTP Code: " . $http_code;
        }
    }
    curl_close($ch);
} 
// Handle store payment
else if ($method == 'store') {
    $payment_success = true;
    $message = "Your booking is confirmed with store payment option. Please visit our store to complete the payment.";
}

// If still not successful and we have a session_id in booking table, try that
if (!$payment_success && $booking_details && !empty($booking_details['paymongo_payment_id'])) {
    // Try with the stored session_id
    $stored_session_id = $booking_details['paymongo_payment_id'];
    if ($stored_session_id && $stored_session_id != $session_id) {
        // Redirect to self with the stored session_id
        header("Location: payment-success.php?booking_id=$booking_id&session_id=$stored_session_id");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status - Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .status-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            padding: 50px;
            max-width: 600px;
            width: 100%;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            font-size: 50px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .status-icon.success {
            background: linear-gradient(135deg, #00b894, #00a085);
            box-shadow: 0 0 30px rgba(0, 184, 148, 0.3);
        }

        .status-icon.pending {
            background: linear-gradient(135deg, #fdcb6e, #e17055);
            box-shadow: 0 0 30px rgba(253, 203, 110, 0.3);
        }

        .status-icon i {
            color: white;
        }

        .status-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            background: linear-gradient(90deg, #e94560, #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .status-message {
            color: #aaa;
            font-size: 16px;
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .booking-summary {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            padding: 25px;
            margin: 30px 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-label {
            color: #aaa;
            font-size: 14px;
        }

        .summary-value {
            color: #e94560;
            font-weight: 600;
            font-size: 16px;
        }

        .debug-info {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid #e94560;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            text-align: left;
            font-family: monospace;
            font-size: 12px;
            color: #ff9999;
            overflow-x: auto;
        }

        .countdown {
            color: #fdcb6e;
            font-size: 14px;
            margin: 20px 0;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: none;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #e94560, #ff4757);
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #ff4757, #e94560);
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(233, 69, 96, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .status-container {
                padding: 30px 20px;
            }
            
            .status-title {
                font-size: 24px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="status-container">
        <?php if ($payment_success): ?>
            <!-- Success State -->
            <div class="status-icon success">
                <i class="fas <?php echo $method == 'store' ? 'fa-store' : 'fa-check-circle'; ?>"></i>
            </div>
            
            <h1 class="status-title">
                <?php echo $method == 'store' ? 'Store Payment Selected!' : 'Payment Successful!'; ?>
            </h1>
            
            <p class="status-message"><?php echo htmlspecialchars($message); ?></p>
            
            <?php if ($booking_details): ?>
            <div class="booking-summary">
                <h3 style="margin-bottom: 20px; color: #e94560;">Booking Summary</h3>
                <div class="summary-item">
                    <span class="summary-label">Booking ID</span>
                    <span class="summary-value">#<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Room</span>
                    <span class="summary-value"><?php echo htmlspecialchars($booking_details['room_name'] ?? 'N/A'); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Date</span>
                    <span class="summary-value"><?php echo date('F d, Y', strtotime($booking_details['booking_date'])); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Time</span>
                    <span class="summary-value"><?php echo date('g:i A', strtotime($booking_details['start_time'])); ?> - <?php echo date('g:i A', strtotime($booking_details['end_time'])); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Total Amount</span>
                    <span class="summary-value">₱<?php echo number_format($booking_details['total_amount'], 2); ?></span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Payment Status</span>
                    <span class="summary-value" style="color: #00b894;"><?php echo strtoupper($booking_details['payment_status'] ?? 'PAID'); ?></span>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="countdown">
                <i class="fas fa-clock"></i> Redirecting to your bookings in <span id="countdown">5</span> seconds...
            </div>
            
            <div class="action-buttons">
                <a href="booking-details.php?id=<?php echo $booking_id; ?>" class="btn btn-primary">
                    <i class="fas fa-eye"></i> View Booking Details
                </a>
                <a href="my-bookings.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> My Bookings
                </a>
            </div>
            
            <script>
                // Countdown and redirect
                let seconds = 5;
                const countdownEl = document.getElementById('countdown');
                
                const interval = setInterval(() => {
                    seconds--;
                    if (countdownEl) countdownEl.textContent = seconds;
                    
                    if (seconds <= 0) {
                        clearInterval(interval);
                        window.location.href = 'my-bookings.php';
                    }
                }, 1000);
            </script>
            
        <?php else: ?>
            <!-- Error State -->
            <div class="status-icon pending">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            
            <h1 class="status-title">Payment Issue</h1>
            
            <p class="status-message"><?php echo htmlspecialchars($message ?: 'There was an issue processing your payment.'); ?></p>
            
            <?php if ($session_id && $session_id != '{CHECKOUT_SESSION_ID}'): ?>
            <div class="debug-info">
                <p><strong>Debug Information:</strong></p>
                <p><strong>Session ID:</strong> <?php echo htmlspecialchars($session_id); ?></p>
                <p><strong>Booking ID:</strong> <?php echo $booking_id; ?></p>
                <p><strong>Current Status in DB:</strong> <?php echo $booking_details['payment_status'] ?? 'unknown'; ?></p>
                <p style="color: #fdcb6e; margin-top: 10px;">If money was deducted, please click "Force Update Status" below.</p>
            </div>
            
            <div class="action-buttons">
                <a href="payment-force-update.php?booking_id=<?php echo $booking_id; ?>&session_id=<?php echo urlencode($session_id); ?>" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Force Update Status
                </a>
            <?php endif; ?>
            
            <div class="action-buttons">
                <a href="payment.php?id=<?php echo $booking_id; ?>" class="btn btn-primary">
                    <i class="fas fa-redo"></i> Try Again
                </a>
                <a href="my-bookings.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> My Bookings
                </a>
                <a href="contact-support.php?booking=<?php echo $booking_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-headset"></i> Contact Support
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($payment_success): ?>
    <!-- Auto refresh meta tag as backup -->
    <meta http-equiv="refresh" content="5;url=my-bookings.php">
    <?php endif; ?>
</body>
</html>