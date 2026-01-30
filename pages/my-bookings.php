<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];


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
            max-width: 1200px;
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
            margin-top: 20px;
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
        
        @media (max-width: 768px) {
            .booking-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .booking-details {
                grid-template-columns: 1fr;
            }
            
            .booking-actions {
                flex-direction: column;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <a href="dashboard.php" class="back-btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
    
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-list-alt"></i> My Bookings</h1>
            <p>View and manage all your KTV bookings</p>
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
                    return strtotime($b['booking_date'] . ' ' . $b['end_time']) > time() 
                           && $b['payment_status'] != 'cancelled';
                });
                ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo count($upcoming); ?></div>
                    <div class="stat-label">Upcoming</div>
                </div>
                
                <?php
                $total_spent = array_sum(array_column($bookings, 'total_amount'));
                ?>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-number">₹<?php echo number_format($total_spent, 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                    <div class="stat-number"><?php echo array_sum(array_column($bookings, 'food_count')); ?></div>
                    <div class="stat-label">Food Items</div>
                </div>
            </div>
            
            <div class="bookings-list">
                <?php foreach ($bookings as $booking): 
                    $status_class = 'status-pending';
                    if ($booking['payment_status'] == 'paid') $status_class = 'status-confirmed';
                    if ($booking['payment_status'] == 'cancelled') $status_class = 'status-cancelled';
                ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="booking-id">Booking #<?php echo str_pad($booking['b_id'], 6, '0', STR_PAD_LEFT); ?></div>
                            <div class="booking-status <?php echo $status_class; ?>">
                                <?php echo ucfirst($booking['payment_status']); ?>
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
                                <div class="detail-icon"><i class="fas fa-calendar-day"></i></div>
                                <div class="detail-content">
                                    <h4>Date</h4>
                                    <p><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></p>
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
                        </div>
                        
                        <?php if ($booking['food_count'] > 0): ?>
                            <div class="detail-item">
                                <div class="detail-icon"><i class="fas fa-utensils"></i></div>
                                <div class="detail-content">
                                    <h4>Food & Drinks</h4>
                                    <p><?php echo $booking['food_count']; ?> items included</p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="booking-amount">
                            <div class="amount-label">Total Amount</div>
                            <div class="amount-value">₹<?php echo number_format($booking['total_amount'], 2); ?></div>
                        </div>
                        
                        <div class="booking-actions">
                            <button class="action-btn action-primary" onclick="viewBooking(<?php echo $booking['b_id']; ?>)">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            
                            <?php if ($booking['payment_status'] == 'pending'): ?>
                                <button class="action-btn action-secondary" onclick="cancelBooking(<?php echo $booking['b_id']; ?>)">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            <?php endif; ?>
                            
                            <button class="action-btn action-secondary" onclick="printBooking(<?php echo $booking['b_id']; ?>)">
                                <i class="fas fa-print"></i> Print
                            </button>
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

    <script>
        function viewBooking(bookingId) {
            window.location.href = 'booking-details.php?id=' + bookingId;
        }
        
        function cancelBooking(bookingId) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                // In a real app, this would make an AJAX call
                window.location.href = 'cancel-booking.php?id=' + bookingId;
            }
        }
        
        function printBooking(bookingId) {
            window.open('print-booking.php?id=' + bookingId, '_blank');
        }
    </script>
</body>
</html>