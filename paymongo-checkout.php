<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db.php';

use Paymongo\PaymongoClient;

if (!isset($_SESSION['user_id']) || !isset($_GET['payment_intent']) || !isset($_GET['booking_id'])) {
    header("Location: dashboard.php");
    exit;
}

$payment_intent_id = $_GET['payment_intent'];
$booking_id = $_GET['booking_id'];
$user_id = $_SESSION['user_id'];

$secret_key = 'sk_test_CuXgiJJHcBEX24FTGBE6KxPd';
$client = new PaymongoClient($secret_key);

try {
    // Verify payment
    $paymentIntent = $client->paymentIntents->retrieve($payment_intent_id);
    
    if ($paymentIntent->attributes['status'] === 'succeeded') {
        // Update booking status
        $conn->begin_transaction();
        
        $update_booking = $conn->prepare("UPDATE booking SET payment_status = 'paid' WHERE b_id = ? AND u_id = ?");
        $update_booking->bind_param("ii", $booking_id, $user_id);
        $update_booking->execute();
        
        $insert_payment = $conn->prepare("INSERT INTO payments (b_id, u_id, payment_method, payment_status, amount, payment_date) VALUES (?, ?, ?, 'completed', ?, NOW())");
        $payment_method = $paymentIntent->attributes['payment_method_allowed'][0] ?? 'unknown';
        $amount = $paymentIntent->attributes['amount'] / 100;
        $insert_payment->bind_param("iisd", $booking_id, $user_id, $payment_method, $amount);
        $insert_payment->execute();
        
        $conn->commit();
        $success = true;
    } else {
        $error = "Payment not successful";
    }
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Success - Sirene KTV</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
            padding: 20px;
        }
        
        .success-container {
            background: #0f3460;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            border: 2px solid #00b894;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #00b894;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: white;
        }
        
        .test-badge {
            background: #e94560;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
            margin-bottom: 20px;
            font-size: 12px;
        }
        
        h1 {
            margin-bottom: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #e94560;
            color: white;
            text-decoration: none;
            border-radius: 25px;
            margin: 10px;
        }
        
        .btn:hover {
            background: #ff4757;
        }
        
        .error {
            background: rgba(214,48,49,0.2);
            border: 1px solid #d63031;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="test-badge">🎓 TEST MODE - No Real Money</div>
        
        <?php if (isset($success)): ?>
            <div class="success-icon">✓</div>
            <h1>Payment Successful!</h1>
            <p>Your booking has been confirmed (Test Mode)</p>
            <p>Booking ID: #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></p>
            <a href="my-bookings.php" class="btn">View My Bookings</a>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        <?php else: ?>
            <div class="error-icon" style="font-size: 60px; color: #d63031;">❌</div>
            <h1>Payment Error</h1>
            <p class="error"><?php echo $error ?? 'Unknown error'; ?></p>
            <a href="payment.php?id=<?php echo $booking_id; ?>" class="btn">Try Again</a>
        <?php endif; ?>
        
        <p style="margin-top: 20px; font-size: 12px; color: #888;">
            <i class="fas fa-info-circle"></i> 
            This is a test transaction. No actual payment was processed.
        </p>
    </div>
</body>
</html>