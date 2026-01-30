<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;
$user_id = $_SESSION['user_id'];

// Fetch booking details
$booking_query = $conn->prepare("
    SELECT b.*, r.room_name, r.capcity, r.price_hr, u.name as user_name, u.email 
    FROM booking b 
    JOIN room r ON b.r_id = r.r_id 
    JOIN user_tbl u ON b.u_id = u.id 
    WHERE b.b_id = ? AND b.u_id = ?
");
$booking_query->bind_param("ii", $booking_id, $user_id);
$booking_query->execute();
$booking_result = $booking_query->get_result();
$booking = $booking_result->fetch_assoc();

if (!$booking) {
    header("Location: dashboard.php");
    exit;
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - Sirene KTV</title>
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
        
        .confirmation-container {
            max-width: 800px;
            width: 100%;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            text-align: center;
        }
        
        .success-icon {
            font-size: 80px;
            color: var(--success);
            margin-bottom: 20px;
            animation: bounce 1s ease infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        h1 {
            font-size: 36px;
            color: var(--highlight);
            margin-bottom: 10px;
        }
        
        .subtitle {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 30px;
            font-size: 18px;
        }
        
        .booking-id {
            background: rgba(233, 69, 96, 0.2);
            color: var(--highlight);
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-block;
            margin-bottom: 30px;
            font-weight: bold;
            font-size: 20px;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .info-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-card h3 {
            color: var(--highlight);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 16px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-label {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .info-value {
            font-weight: bold;
            color: var(--light);
        }
        
        .total-amount {
            font-size: 28px;
            color: var(--highlight);
            font-weight: bold;
            margin: 20px 0;
        }
        
        .payment-info {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 10px;
            margin: 30px 0;
            border-left: 4px solid var(--highlight);
        }
        
        .payment-info h3 {
            color: var(--highlight);
            margin-bottom: 10px;
            text-align: left;
        }
        
        .payment-info p {
            color: rgba(255, 255, 255, 0.7);
            text-align: left;
            line-height: 1.6;
        }
        
        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 15px 30px;
            border-radius: 10px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-2px);
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
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .confirmation-container {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        
        <h1>Booking Confirmed! 🎤</h1>
        <p class="subtitle">Your KTV room has been successfully booked</p>
        
        <div class="booking-id">
            Booking ID: #<?php echo str_pad($booking['b_id'], 6, '0', STR_PAD_LEFT); ?>
        </div>
        
        <div class="info-grid">
            <div class="info-card">
                <h3><i class="fas fa-user"></i> Customer Details</h3>
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['user_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Booking Date:</span>
                    <span class="info-value"><?php echo date('F j, Y', strtotime($booking['created_at'])); ?></span>
                </div>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-door-closed"></i> Room Details</h3>
                <div class="info-item">
                    <span class="info-label">Room:</span>
                    <span class="info-value"><?php echo htmlspecialchars($booking['room_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Capacity:</span>
                    <span class="info-value"><?php echo $booking['capcity']; ?> persons</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Session:</span>
                    <span class="info-value">
                        <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?><br>
                        <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                        <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                    </span>
                </div>
            </div>
            
            <?php if (!empty($food_items)): ?>
            <div class="info-card" style="grid-column: 1 / -1;">
                <h3><i class="fas fa-utensils"></i> Food & Drinks</h3>
                <?php foreach ($food_items as $item): ?>
                    <div class="info-item">
                        <span class="info-label"><?php echo htmlspecialchars($item['item_name']); ?> × <?php echo $item['quantity']; ?></span>
                        <span class="info-value">₹<?php echo number_format($item['price_at_booking'] * $item['quantity'], 2); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <div class="info-card" style="grid-column: 1 / -1; background: rgba(233, 69, 96, 0.1);">
                <h3><i class="fas fa-receipt"></i> Payment Summary</h3>
                <div class="info-item">
                    <span class="info-label">Room Charges (<?php echo $booking['hours']; ?> hrs):</span>
                    <span class="info-value">₹<?php echo number_format($booking['room_amount'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Food & Drinks:</span>
                    <span class="info-value">₹<?php echo number_format($booking['food_amount'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Subtotal:</span>
                    <span class="info-value">₹<?php echo number_format($booking['subtotal'], 2); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tax (10%):</span>
                    <span class="info-value">₹<?php echo number_format($booking['tax_amount'], 2); ?></span>
                </div>
                <div class="total-amount">
                    Total: ₹<?php echo number_format($booking['total_amount'], 2); ?>
                </div>
            </div>
        </div>
        
        <div class="payment-info">
            <h3><i class="fas fa-info-circle"></i> Important Information</h3>
            <p>
                • Please arrive 15 minutes before your booking time<br>
                • Present your booking ID at the reception<br>
                • Payment will be collected at the venue<br>
                • Cancellation must be made 24 hours in advance<br>
                • Contact: support@sirenektv.com | Phone: 1800-KTV-SING
            </p>
        </div>
        
        <div class="actions">
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
            <a href="my-bookings.php" class="btn btn-primary">
                <i class="fas fa-list"></i> View My Bookings
            </a>
        </div>
    </div>
</body>
</html>