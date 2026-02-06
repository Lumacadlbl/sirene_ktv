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
$booking_id = $_GET['id'] ?? 0;

// Fetch booking details
$booking_query = $conn->prepare("
    SELECT b.*, r.room_name, r.price_hr 
    FROM booking b 
    JOIN room r ON b.r_id = r.r_id 
    WHERE b.b_id = ? AND b.u_id = ?
");

$booking_query->bind_param("ii", $booking_id, $user_id);
$booking_query->execute();
$booking_result = $booking_query->get_result();
$booking = $booking_result->fetch_assoc();

if (!$booking) {
    header("Location: my-bookings.php");
    exit;
}

// Check if already paid
if ($booking['payment_status'] == 'paid' || $booking['payment_status'] == 'approved') {
    header("Location: my-bookings.php?message=already_paid");
    exit;
}

// Handle form submission - SIMULATED PAYMENT
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_method = $_POST['payment_method'];
    
    // Validate payment (simulated validation)
    if (validatePayment($payment_method, $_POST)) {
        // SIMULATE PAYMENT PROCESSING DELAY
        sleep(2); // 2 second delay to simulate processing
        
        // Generate random success/failure (90% success rate for demo)
        $success_rate = 90; // 90% success rate
        $is_successful = (rand(1, 100) <= $success_rate);
        
        if ($is_successful) {
            // Start transaction to ensure both updates succeed
            $conn->begin_transaction();
            
            try {
                // Update booking payment status
                $update_booking = $conn->prepare("UPDATE booking SET payment_status = 'paid' WHERE b_id = ?");
                $update_booking->bind_param("i", $booking_id);
                $update_booking->execute();
                
                // Insert payment record into payments table
                $payment_date = date('Y-m-d H:i:s');
                $insert_payment = $conn->prepare("
                    INSERT INTO payments (b_id, u_id, payment_method, payment_status, amount, payment_date) 
                    VALUES (?, ?, ?, 'completed', ?, ?)
                ");
                $insert_payment->bind_param("iisds", $booking_id, $user_id, $payment_method, $booking['total_amount'], $payment_date);
                $insert_payment->execute();
                
                // Commit transaction
                $conn->commit();
                
                // Log the simulated payment
                error_log("Payment successful - Booking #{$booking_id}, Amount: {$booking['total_amount']}, Method: {$payment_method}");
                
                header("Location: payment-success.php?id=" . $booking_id);
                exit;
                
            } catch (Exception $e) {
                // Rollback on error
                $conn->rollback();
                $_SESSION['payment_error'] = "Payment processing failed. Please try again.";
                header("Location: make-payment.php?id=" . $booking_id);
                exit;
            }
        } else {
            // Simulate payment failure
            $_SESSION['payment_error'] = "Payment failed. Please try again or use a different payment method.";
            header("Location: payment-failed.php?id=" . $booking_id . "&method=" . $payment_method);
            exit;
        }
    } else {
        $_SESSION['payment_error'] = "Invalid payment details. Please check your information and try again.";
        header("Location: make-payment.php?id=" . $booking_id);
        exit;
    }
}

function validatePayment($method, $data) {
    // SIMULATED VALIDATION - Always returns true for demo
    // In a real system, you would validate actual payment details
    switch($method) {
        case 'card':
            // Simulated validation - just check if fields are filled
            return !empty($data['card_number']) && !empty($data['expiry_date']) && 
                   !empty($data['card_name']);
        case 'upi':
            return !empty($data['upi_id']);
        case 'gcash':
            return !empty($data['gcash_number']);
        case 'paymaya':
            return !empty($data['paymaya_number']);
        case 'paypal':
            return !empty($data['paypal_email']);
        case 'bank_transfer':
            return !empty($data['account_name']) && !empty($data['account_number']) && !empty($data['bank_name']);
        case 'cash':
            return true; // Cash payment always valid for demo
        default:
            return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Booking #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?> - Sirene KTV</title>
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
            --gcash: #0c7d69;
            --paymaya: #ff6b00;
            --paypal: #003087;
            --bank: #4a6fa5;
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
            max-width: 900px;
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

        .demo-notice {
            background: rgba(253, 203, 110, 0.2);
            border: 1px solid rgba(253, 203, 110, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            color: var(--light);
        }

        .demo-notice i {
            color: var(--warning);
            margin-right: 10px;
        }

        .booking-info {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
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
        }

        .total-amount {
            background: rgba(233, 69, 96, 0.1);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            margin: 25px 0;
            border: 1px solid rgba(233, 69, 96, 0.2);
        }

        .total-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .total-value {
            color: var(--highlight);
            font-size: 36px;
            font-weight: bold;
            margin-top: 10px;
        }

        .payment-section {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-title {
            color: var(--light);
            font-size: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--highlight);
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .method-option {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .method-option:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .method-option.selected {
            border-color: var(--highlight);
            background: rgba(233, 69, 96, 0.1);
        }

        .method-icon {
            font-size: 30px;
            margin-bottom: 10px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .method-name {
            font-weight: bold;
            color: var(--light);
            font-size: 14px;
        }

        .method-fee {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-top: 5px;
        }

        /* Method-specific colors */
        .method-card .method-icon { color: var(--info); }
        .method-upi .method-icon { color: var(--purple); }
        .method-gcash .method-icon { color: var(--gcash); }
        .method-paymaya .method-icon { color: var(--paymaya); }
        .method-paypal .method-icon { color: var(--paypal); }
        .method-bank .method-icon { color: var(--bank); }
        .method-cash .method-icon { color: var(--success); }

        .payment-form {
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

        input, select {
            width: 100%;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 12px 15px;
            color: var(--light);
            font-size: 16px;
            transition: all 0.2s;
        }

        input:focus, select:focus {
            outline: none;
            border-color: var(--highlight);
            background: rgba(255, 255, 255, 0.15);
        }

        .card-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .bank-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .method-note {
            background: rgba(253, 203, 110, 0.1);
            border: 1px solid rgba(253, 203, 110, 0.2);
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        .method-note i {
            color: var(--warning);
            margin-right: 8px;
        }

        .pay-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--success), #00a085);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 10px;
            position: relative;
            overflow: hidden;
        }

        .pay-btn:hover {
            background: linear-gradient(135deg, #00a085, var(--success));
            transform: translateY(-2px);
        }

        .pay-btn.processing {
            background: linear-gradient(135deg, var(--warning), #e17055);
            cursor: not-allowed;
        }

        .pay-btn.processing::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            animation: processing 1.5s infinite;
        }

        @keyframes processing {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .hidden {
            display: none;
        }

        .qr-code {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .qr-placeholder {
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: rgba(255, 255, 255, 0.3);
            font-size: 14px;
        }

        .demo-field {
            border: 1px dashed rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.05);
        }

        .demo-field:focus {
            border: 1px dashed var(--highlight);
        }

        .error-message {
            background: rgba(214, 48, 49, 0.2);
            border: 1px solid rgba(214, 48, 49, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
            color: var(--light);
        }

        .error-message i {
            color: var(--danger);
            margin-right: 10px;
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
            
            .user-info, .back-btn {
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
            
            .payment-methods {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .card-details, .bank-details {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .payment-methods {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-left">
            <h1><i class="fas fa-microphone-alt"></i> Sirene KTV Payment</h1>
            <p>Complete Payment for Booking #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></p>
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

    <div class="container">
        <div class="page-header">
            <div class="page-title">
                <h2><i class="fas fa-credit-card"></i> Complete Payment</h2>
                <p>Choose your preferred payment method</p>
            </div>
        </div>
        
        <?php if (isset($_SESSION['payment_error'])): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i>
                <?php 
                echo $_SESSION['payment_error'];
                unset($_SESSION['payment_error']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="demo-notice">
            <i class="fas fa-vial"></i>
            <strong>DEMO MODE:</strong> This is a simulated payment system. No real transactions will occur.
            Use any test data to proceed.
        </div>
        
        <div class="booking-info">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Booking ID</span>
                    <span class="info-value">#<?php echo str_pad($booking['b_id'], 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Room</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['room_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Date</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Time</span>
                    <span class="info-value">
                        <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                        <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="total-amount">
            <div class="total-label">Total Amount to Pay</div>
            <div class="total-value">₹<?php echo number_format($booking['total_amount'], 2); ?></div>
        </div>
        
        <div class="payment-section">
            <h2 class="section-title"><i class="fas fa-wallet"></i> Select Payment Method</h2>
            
            <div class="payment-methods">
                <div class="method-option method-card selected" onclick="selectPaymentMethod('card')">
                    <div class="method-icon"><i class="fas fa-credit-card"></i></div>
                    <div class="method-name">Credit/Debit Card</div>
                    <div class="method-fee">Demo: Use 4242 4242 4242 4242</div>
                </div>
                
                <div class="method-option method-upi" onclick="selectPaymentMethod('upi')">
                    <div class="method-icon"><i class="fas fa-qrcode"></i></div>
                    <div class="method-name">UPI Payment</div>
                    <div class="method-fee">Demo: Use test@upi</div>
                </div>
                
                <div class="method-option method-gcash" onclick="selectPaymentMethod('gcash')">
                    <div class="method-icon"><i class="fas fa-mobile-alt"></i></div>
                    <div class="method-name">G-Cash</div>
                    <div class="method-fee">Demo: Use 09123456789</div>
                </div>
                
                <div class="method-option method-paymaya" onclick="selectPaymentMethod('paymaya')">
                    <div class="method-icon"><i class="fas fa-mobile-alt"></i></div>
                    <div class="method-name">PayMaya</div>
                    <div class="method-fee">Demo: Use 09123456789</div>
                </div>
                
                <div class="method-option method-paypal" onclick="selectPaymentMethod('paypal')">
                    <div class="method-icon"><i class="fab fa-paypal"></i></div>
                    <div class="method-name">PayPal</div>
                    <div class="method-fee">Demo: Use test@example.com</div>
                </div>
                
                <div class="method-option method-bank" onclick="selectPaymentMethod('bank_transfer')">
                    <div class="method-icon"><i class="fas fa-university"></i></div>
                    <div class="method-name">Bank Transfer</div>
                    <div class="method-fee">Demo: Use test data</div>
                </div>
                
                <div class="method-option method-cash" onclick="selectPaymentMethod('cash')">
                    <div class="method-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="method-name">Cash Payment</div>
                    <div class="method-fee">Simulated on-site payment</div>
                </div>
            </div>
        </div>
        
        <form method="POST" class="payment-form" id="paymentForm" onsubmit="return processPayment(event)">
            <input type="hidden" name="payment_method" id="paymentMethod" value="card">
            
            <!-- Card Payment Fields -->
            <div id="cardFields">
                <h2 class="section-title"><i class="fas fa-credit-card"></i> Card Details (Demo)</h2>
                <div class="form-group">
                    <label for="card_number">Card Number (Test: 4242 4242 4242 4242)</label>
                    <input type="text" id="card_number" name="card_number" placeholder="4242 4242 4242 4242" maxlength="19" class="demo-field" value="4242 4242 4242 4242" required>
                </div>
                
                <div class="card-details">
                    <div class="form-group">
                        <label for="expiry_date">Expiry Date (Any future date)</label>
                        <input type="text" id="expiry_date" name="expiry_date" placeholder="12/28" maxlength="5" class="demo-field" value="12/28" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="cvv">CVV (Any 3 digits)</label>
                        <input type="password" id="cvv" name="cvv" placeholder="123" maxlength="3" class="demo-field" value="123" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="card_name">Name on Card</label>
                        <input type="text" id="card_name" name="card_name" placeholder="John Doe" class="demo-field" value="Demo Customer" required>
                    </div>
                </div>
            </div>
            
            <!-- UPI Payment Fields -->
            <div id="upiFields" class="hidden">
                <h2 class="section-title"><i class="fas fa-qrcode"></i> UPI Payment (Demo)</h2>
                <div class="qr-code">
                    <div class="qr-placeholder">
                        <i class="fas fa-qrcode fa-3x"></i>
                    </div>
                    <p>Demo QR Code - Simulated Payment</p>
                </div>
                <div class="form-group">
                    <label for="upi_id">UPI ID (Demo: test@upi)</label>
                    <input type="text" id="upi_id" name="upi_id" placeholder="test@upi" class="demo-field" value="test@upi" required>
                </div>
                <div class="method-note">
                    <i class="fas fa-info-circle"></i>
                    This is a simulated UPI payment. No actual transaction will occur.
                </div>
            </div>
            
            <!-- G-Cash Payment Fields -->
            <div id="gcashFields" class="hidden">
                <h2 class="section-title"><i class="fas fa-mobile-alt"></i> G-Cash Payment (Demo)</h2>
                <div class="qr-code">
                    <div class="qr-placeholder" style="background: var(--gcash);">
                        <i class="fas fa-mobile-alt fa-3x"></i>
                    </div>
                    <p>Demo G-Cash QR - Simulated Payment</p>
                </div>
                <div class="form-group">
                    <label for="gcash_number">G-Cash Mobile Number (Demo: 09123456789)</label>
                    <input type="tel" id="gcash_number" name="gcash_number" placeholder="09123456789" class="demo-field" value="09123456789" required>
                </div>
                <div class="method-note">
                    <i class="fas fa-info-circle"></i>
                    This is a simulated G-Cash payment. No actual transaction will occur.
                </div>
            </div>
            
            <!-- PayMaya Payment Fields -->
            <div id="paymayaFields" class="hidden">
                <h2 class="section-title"><i class="fas fa-mobile-alt"></i> PayMaya Payment (Demo)</h2>
                <div class="qr-code">
                    <div class="qr-placeholder" style="background: var(--paymaya);">
                        <i class="fas fa-wallet fa-3x"></i>
                    </div>
                    <p>Demo PayMaya QR - Simulated Payment</p>
                </div>
                <div class="form-group">
                    <label for="paymaya_number">PayMaya Account Number (Demo: 09123456789)</label>
                    <input type="tel" id="paymaya_number" name="paymaya_number" placeholder="09123456789" class="demo-field" value="09123456789" required>
                </div>
                <div class="method-note">
                    <i class="fas fa-info-circle"></i>
                    This is a simulated PayMaya payment. No actual transaction will occur.
                </div>
            </div>
            
            <!-- PayPal Payment Fields -->
            <div id="paypalFields" class="hidden">
                <h2 class="section-title"><i class="fab fa-paypal"></i> PayPal Payment (Demo)</h2>
                <div class="form-group">
                    <label for="paypal_email">PayPal Email Address (Demo: test@example.com)</label>
                    <input type="email" id="paypal_email" name="paypal_email" placeholder="test@example.com" class="demo-field" value="test@example.com" required>
                </div>
                <div class="method-note">
                    <i class="fas fa-info-circle"></i>
                    This is a simulated PayPal payment. You will not be redirected to PayPal.
                </div>
            </div>
            
            <!-- Bank Transfer Fields -->
            <div id="bankFields" class="hidden">
                <h2 class="section-title"><i class="fas fa-university"></i> Bank Transfer (Demo)</h2>
                <div class="form-group">
                    <label for="bank_name">Bank Name</label>
                    <select id="bank_name" name="bank_name" class="demo-field" required>
                        <option value="">Select Bank</option>
                        <option value="Demo Bank" selected>Demo Bank (Test)</option>
                        <option value="BDO">BDO Unibank</option>
                        <option value="BPI">BPI</option>
                        <option value="Metrobank">Metrobank</option>
                        <option value="UnionBank">UnionBank</option>
                    </select>
                </div>
                
                <div class="bank-details">
                    <div class="form-group">
                        <label for="account_name">Account Name (Demo: Test Customer)</label>
                        <input type="text" id="account_name" name="account_name" placeholder="Test Customer" class="demo-field" value="Test Customer" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="account_number">Account Number (Demo: 1234567890)</label>
                        <input type="text" id="account_number" name="account_number" placeholder="1234567890" class="demo-field" value="1234567890" required>
                    </div>
                </div>
                
                <div class="method-note">
                    <i class="fas fa-info-circle"></i>
                    This is a simulated bank transfer. No actual funds will be transferred.<br>
                    <strong>Demo Bank Details:</strong> Account: 123-456-789-0, Swift Code: DEMO123
                </div>
            </div>
            
            <!-- Cash Payment Fields -->
            <div id="cashFields" class="hidden">
                <h2 class="section-title"><i class="fas fa-money-bill-wave"></i> Cash Payment (Simulated)</h2>
                <div class="method-note" style="background: rgba(0, 184, 148, 0.1); border-color: rgba(0, 184, 148, 0.2);">
                    <i class="fas fa-check-circle"></i>
                    <strong>Simulated Cash Payment:</strong><br>
                    1. This simulates paying cash on arrival<br>
                    2. Your booking will be marked as "paid" in the system<br>
                    3. No actual cash transaction occurs<br>
                    4. You can proceed to payment success page
                </div>
            </div>
            
            <button type="submit" class="pay-btn" id="payButton">
                <i class="fas fa-lock"></i> 
                <span id="payButtonText">Simulate Payment ₹<?php echo number_format($booking['total_amount'], 2); ?></span>
            </button>
        </form>
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
        let currentMethod = 'card';
        
        function selectPaymentMethod(method) {
            currentMethod = method;
            
            // Update selected method UI
            document.querySelectorAll('.method-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.target.closest('.method-option').classList.add('selected');
            
            // Update hidden input
            document.getElementById('paymentMethod').value = method;
            
            // Hide all form fields
            const allFields = ['cardFields', 'upiFields', 'gcashFields', 'paymayaFields', 'paypalFields', 'bankFields', 'cashFields'];
            allFields.forEach(fieldId => {
                document.getElementById(fieldId).classList.add('hidden');
            });
            
            // Show selected method fields
            document.getElementById(method + 'Fields').classList.remove('hidden');
            
            // Update button text
            const payButton = document.getElementById('payButton');
            const payButtonText = document.getElementById('payButtonText');
            
            if (method === 'cash') {
                payButton.style.background = 'linear-gradient(135deg, var(--warning), #e17055)';
                payButtonText.innerHTML = '<i class="fas fa-check"></i> Simulate Cash Payment';
            } else {
                payButton.style.background = 'linear-gradient(135deg, var(--success), #00a085)';
                payButtonText.innerHTML = '<i class="fas fa-lock"></i> Simulate Payment ₹<?php echo number_format($booking['total_amount'], 2); ?>';
            }
        }
        
        function processPayment(event) {
            event.preventDefault();
            
            const form = event.target;
            const submitButton = form.querySelector('#payButton');
            const buttonText = submitButton.querySelector('#payButtonText');
            
            // Show processing state
            submitButton.classList.add('processing');
            buttonText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Simulation...';
            submitButton.disabled = true;
            
            // Simulate payment processing delay
            setTimeout(() => {
                // Submit the form after simulation
                form.submit();
            }, 2000); // 2 second simulation
            
            return false; // Prevent default submission
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-fill demo data based on method
            function autoFillDemoData(method) {
                const demoData = {
                    card: {
                        card_number: '4242 4242 4242 4242',
                        expiry_date: '12/28',
                        cvv: '123',
                        card_name: 'Demo Customer'
                    },
                    upi: {
                        upi_id: 'test@upi'
                    },
                    gcash: {
                        gcash_number: '09123456789'
                    },
                    paymaya: {
                        paymaya_number: '09123456789'
                    },
                    paypal: {
                        paypal_email: 'test@example.com'
                    },
                    bank_transfer: {
                        bank_name: 'Demo Bank',
                        account_name: 'Test Customer',
                        account_number: '1234567890'
                    }
                };
                
                if (demoData[method]) {
                    Object.entries(demoData[method]).forEach(([field, value]) => {
                        const input = document.querySelector(`[name="${field}"]`);
                        if (input) {
                            input.value = value;
                        }
                    });
                }
            }
            
            // Format card number
            document.getElementById('card_number').addEventListener('input', function(e) {
                let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
                let formatted = value.replace(/(\d{4})/g, '$1 ').trim();
                e.target.value = formatted.substring(0, 19);
            });
            
            // Format expiry date
            document.getElementById('expiry_date').addEventListener('input', function(e) {
                let value = e.target.value.replace(/[^0-9]/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                e.target.value = value.substring(0, 5);
            });
            
            // Auto-fill demo data when method changes
            document.querySelectorAll('.method-option').forEach(option => {
                option.addEventListener('click', function() {
                    const method = this.classList[1].replace('method-', '');
                    autoFillDemoData(method);
                });
            });
            
            // Initial auto-fill
            autoFillDemoData('card');
            
            // Add page load animation
            const sections = document.querySelectorAll('.payment-section, .booking-info, .payment-form');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    section.style.transition = 'opacity 0.5s, transform 0.5s';
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>