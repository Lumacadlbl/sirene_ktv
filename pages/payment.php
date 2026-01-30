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
    die("Booking not found or access denied.");
}

// Check if already paid
$payment_check = $conn->prepare("SELECT * FROM payments WHERE b_id = ?");
$payment_check->bind_param("i", $booking_id);
$payment_check->execute();
$existing_payment = $payment_check->get_result()->fetch_assoc();

if ($existing_payment && $existing_payment['payment_status'] == 'approved') {
    header("Location: my-bookings.php?message=already_paid");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $payment_method = $_POST['payment_method'];
    $card_number = $_POST['card_number'] ?? '';
    $expiry_date = $_POST['expiry_date'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $upi_id = $_POST['upi_id'] ?? '';
    
    // Validate payment
    if (validatePayment($payment_method, $card_number, $expiry_date, $cvv, $upi_id)) {
        // Insert payment record
        $insert_payment = $conn->prepare("
            INSERT INTO payments (b_id, u_id, payment_method, amount, payment_status, payment_date) 
            VALUES (?, ?, ?, ?, 'approved', NOW())
        ");
        
        $insert_payment->bind_param("iisd", $booking_id, $user_id, $payment_method, $booking['total_amount']);
        
        if ($insert_payment->execute()) {
            // Update booking payment status
            $update_booking = $conn->prepare("UPDATE booking SET payment_status = 'paid' WHERE b_id = ?");
            $update_booking->bind_param("i", $booking_id);
            $update_booking->execute();
            
            header("Location: payment-success.php?id=" . $booking_id);
            exit;
        }
    }
}

function validatePayment($method, $card, $expiry, $cvv, $upi) {
    if ($method == 'card') {
        return !empty($card) && !empty($expiry) && !empty($cvv) && strlen($cvv) == 3;
    } elseif ($method == 'upi') {
        return !empty($upi) && filter_var($upi, FILTER_VALIDATE_EMAIL);
    }
    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --highlight: #e94560;
            --success: #00b894;
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
            background: linear-gradient(90deg, var(--highlight), #ff7675);
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
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
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
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
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
            transition: all 0.2s;
        }
        
        .method-option:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .method-option.selected {
            border-color: var(--highlight);
            background: rgba(233, 69, 96, 0.1);
        }
        
        .method-icon {
            font-size: 30px;
            color: var(--highlight);
            margin-bottom: 10px;
        }
        
        .method-name {
            font-weight: bold;
            color: var(--light);
        }
        
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
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
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
        }
        
        .pay-btn:hover {
            background: linear-gradient(135deg, #00a085, var(--success));
            transform: translateY(-2px);
        }
        
        .hidden {
            display: none;
        }
        
        @media (max-width: 768px) {
            .payment-methods {
                grid-template-columns: 1fr;
            }
            
            .card-details {
                grid-template-columns: 1fr;
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
            <h1><i class="fas fa-credit-card"></i> Complete Payment</h1>
            <p>Secure payment for your booking</p>
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
        </div>
        
        <div class="total-amount">
            <div class="total-label">Total Amount to Pay</div>
            <div class="total-value">₹<?php echo number_format($booking['total_amount'], 2); ?></div>
        </div>
        
        <div class="payment-methods">
            <div class="method-option selected" onclick="selectPaymentMethod('card')">
                <div class="method-icon"><i class="fas fa-credit-card"></i></div>
                <div class="method-name">Credit/Debit Card</div>
            </div>
            <div class="method-option" onclick="selectPaymentMethod('upi')">
                <div class="method-icon"><i class="fas fa-mobile-alt"></i></div>
                <div class="method-name">UPI Payment</div>
            </div>
        </div>
        
        <form method="POST" class="payment-form" id="paymentForm">
            <input type="hidden" name="payment_method" id="paymentMethod" value="card">
            
            <div id="cardFields">
                <div class="form-group">
                    <label for="card_number">Card Number</label>
                    <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                </div>
                
                <div class="card-details">
                    <div class="form-group">
                        <label for="expiry_date">Expiry Date (MM/YY)</label>
                        <input type="text" id="expiry_date" name="expiry_date" placeholder="MM/YY" maxlength="5">
                    </div>
                    
                    <div class="form-group">
                        <label for="cvv">CVV</label>
                        <input type="password" id="cvv" name="cvv" placeholder="123" maxlength="3">
                    </div>
                    
                    <div class="form-group">
                        <label for="card_name">Name on Card</label>
                        <input type="text" id="card_name" name="card_name" placeholder="John Doe">
                    </div>
                </div>
            </div>
            
            <div id="upiFields" class="hidden">
                <div class="form-group">
                    <label for="upi_id">UPI ID</label>
                    <input type="email" id="upi_id" name="upi_id" placeholder="username@upi">
                </div>
            </div>
            
            <button type="submit" class="pay-btn">
                <i class="fas fa-lock"></i> Pay Now ₹<?php echo number_format($booking['total_amount'], 2); ?>
            </button>
        </form>
    </div>

    <script>
        function selectPaymentMethod(method) {
            document.querySelectorAll('.method-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.target.closest('.method-option').classList.add('selected');
            document.getElementById('paymentMethod').value = method;
            
            if (method === 'card') {
                document.getElementById('cardFields').classList.remove('hidden');
                document.getElementById('upiFields').classList.add('hidden');
            } else {
                document.getElementById('cardFields').classList.add('hidden');
                document.getElementById('upiFields').classList.remove('hidden');
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>