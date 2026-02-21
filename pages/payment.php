<?php
session_start();
include "../db.php";

// TURN ON ERROR DISPLAY
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$name = $_SESSION['name'] ?? 'User';
$role = $_SESSION['role'] ?? 'user';
$booking_id = $_GET['id'] ?? 0;

$config = require __DIR__ . '/../secret.php';

$paymongo_secret_key = $config['PAYMONGO_SECRET_KEY'];
$paymongo_public_key = $config['PAYMONGO_PUBLIC_KEY'];
// FIRST: Check if downpayment column exists, if not create it
$check_column = $conn->query("SHOW COLUMNS FROM booking LIKE 'downpayment'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE booking ADD COLUMN downpayment DECIMAL(10,2) NULL DEFAULT NULL AFTER payment_status");
}

// Check if paymongo_payment_id column exists
$check_paymongo = $conn->query("SHOW COLUMNS FROM booking LIKE 'paymongo_payment_id'");
if ($check_paymongo->num_rows == 0) {
    $conn->query("ALTER TABLE booking ADD COLUMN paymongo_payment_id VARCHAR(100) NULL AFTER downpayment");
}

// Fetch booking details
$booking_query = $conn->prepare("
    SELECT b.*, r.room_name, r.price_hr, r.capcity 
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

// Calculate duration
$start = new DateTime($booking['start_time']);
$end = new DateTime($booking['end_time']);
$interval = $start->diff($end);
$hours = $interval->h + ($interval->i / 60);

// Calculate downpayment (20% of total)
$total_amount = $booking['total_amount'];
$downpayment = $total_amount * 0.20;
$remaining = $total_amount - $downpayment;

// Function to create PayMongo checkout session
function createPayMongoCheckout($amount, $description, $booking_id, $user_id, $payment_method, $secret_key, $room_name, $hours) {
    $amount_in_centavos = $amount * 100; // PayMongo uses centavos
    
    $ch = curl_init();
    
    // Determine payment method types
    $payment_method_types = [];
    if ($payment_method == 'gcash') {
        $payment_method_types = ['gcash'];
    } elseif ($payment_method == 'paymaya') {
        $payment_method_types = ['paymaya'];
    } elseif ($payment_method == 'card') {
        $payment_method_types = ['card'];
    } else {
        $payment_method_types = ['gcash', 'paymaya', 'card'];
    }
    
    // Create line items
    $line_items = [
        [
            'name' => $room_name . ' - KTV Room',
            'quantity' => 1,
            'amount' => $amount_in_centavos,
            'description' => $description . ' (' . number_format($hours, 1) . ' hours)',
            'currency' => 'PHP'
        ]
    ];
    
    $data = [
        'data' => [
            'attributes' => [
                'line_items' => $line_items,
                'payment_method_types' => $payment_method_types,
                'success_url' => 'http://localhost/sirene_ktv/pages/payment-success.php?booking_id=' . $booking_id . '&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'http://localhost/sirene_ktv/pages/payment-failed.php?booking_id=' . $booking_id,
                'description' => $description,
                'metadata' => [
                    'booking_id' => $booking_id,
                    'user_id' => $user_id,
                    'room_name' => $room_name,
                    'hours' => $hours
                ]
            ]
        ]
    ];
    
    curl_setopt($ch, CURLOPT_URL, 'https://api.paymongo.com/v1/checkout_sessions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($secret_key . ':'),
        'Accept: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => true, 'message' => $error];
    }
    
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    if ($http_code >= 400) {
        error_log("PayMongo Error: " . $response);
        return ['error' => true, 'response' => $response_data];
    }
    
    return ['error' => false, 'response' => $response_data];
}

// Handle PayMongo payment creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_with_paymongo'])) {
    $payment_method = $_POST['payment_method'];
    $amount_to_pay = ($payment_method == 'store') ? $downpayment : $total_amount;
    $description = "Booking #" . str_pad($booking_id, 6, '0', STR_PAD_LEFT) . " - " . $booking['room_name'];
    
    if ($payment_method == 'store') {
        // Handle store payment (direct database update)
        try {
            $conn->begin_transaction();
            
            // Update booking
            $update = $conn->prepare("UPDATE booking SET payment_status = 'pending_store', downpayment = ? WHERE b_id = ?");
            $update->bind_param("di", $downpayment, $booking_id);
            $update->execute();
            
            // Insert into payments table (without transaction_id)
            $payment_insert = $conn->prepare("
                INSERT INTO payments (b_id, u_id, payment_method, payment_status, amount, payment_date) 
                VALUES (?, ?, 'store', 'pending', ?, NOW())
            ");
            $payment_insert->bind_param("iid", $booking_id, $user_id, $downpayment);
            $payment_insert->execute();
            
            $conn->commit();
            
            // Redirect to success page
            header("Location: payment-success.php?booking_id=" . $booking_id . "&method=store");
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        // Create PayMongo checkout session
        $result = createPayMongoCheckout(
            $amount_to_pay, 
            $description, 
            $booking_id, 
            $user_id, 
            $payment_method, 
            $paymongo_secret_key,
            $booking['room_name'],
            $hours
        );
        
        if ($result['error']) {
            if (isset($result['response']['errors'])) {
                $error = "PayMongo Error: " . json_encode($result['response']['errors']);
            } else {
                $error = "Failed to create payment: " . ($result['message'] ?? 'Unknown error');
            }
        } else {
            // Get checkout URL and session ID from response
            $checkout_url = $result['response']['data']['attributes']['checkout_url'];
            $session_id = $result['response']['data']['id'];
            
            // Store session_id in booking table
            $update = $conn->prepare("UPDATE booking SET paymongo_payment_id = ?, payment_status = 'pending_payment' WHERE b_id = ?");
            $update->bind_param("si", $session_id, $booking_id);
            $update->execute();
            
            // Insert into payments table (without transaction_id)
            $payment_insert = $conn->prepare("
                INSERT INTO payments (b_id, u_id, payment_method, payment_status, amount, payment_date) 
                VALUES (?, ?, ?, 'pending', ?, NOW())
            ");
            $payment_insert->bind_param("iisd", $booking_id, $user_id, $payment_method, $amount_to_pay);
            $payment_insert->execute();
            
            // Redirect to PayMongo checkout
            header("Location: " . $checkout_url);
            exit;
        }
    }
}

// For debugging - show the actual error
if (isset($error)) {
    echo "<div style='background: rgba(214, 48, 49, 0.2); border: 2px solid #d63031; border-radius: 10px; padding: 20px; margin: 20px; color: #d63031;'>";
    echo "<h3><i class='fas fa-exclamation-circle'></i> Payment Error</h3>";
    echo "<pre style='background: rgba(0,0,0,0.3); padding: 10px; border-radius: 5px; margin-top: 10px; color: #ff9999; white-space: pre-wrap;'>";
    echo htmlspecialchars(print_r($error, true));
    echo "</pre>";
    echo "<p style='margin-top: 10px; font-size: 14px;'>Please try again or contact support.</p>";
    echo "</div>";
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
        /* Keep all your existing CSS here */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --highlight: #e94560;
            --light: #f5f5f5;
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #d63031;
            --info: #0984e3;
            --gcash: #0057e4;
            --paymaya: #ff4d4d;
            --card: #6c5ce7;
            --store: #00b894;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--light);
            min-height: 100vh;
        }

        header {
            background: rgba(10, 10, 20, 0.98);
            padding: 15px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid var(--highlight);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }

        .header-left h1 {
            font-size: 26px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-left p {
            color: #aaa;
            font-size: 13px;
            margin-top: 5px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            background: var(--accent);
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .user-info i {
            color: var(--highlight);
        }

        .back-btn {
            background: linear-gradient(135deg, var(--accent), #0f3460);
            color: white;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-size: 14px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .back-btn:hover {
            background: linear-gradient(135deg, #0f3460, var(--accent));
            transform: translateY(-2px);
            border-color: var(--highlight);
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-size: 32px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }

        .page-header h2 i {
            color: var(--highlight);
            background: rgba(233, 69, 96, 0.2);
            padding: 15px;
            border-radius: 50%;
        }

        .page-header p {
            color: #aaa;
            margin-top: 10px;
            font-size: 16px;
        }

        .live-banner {
            background: linear-gradient(135deg, rgba(0, 184, 148, 0.15), rgba(0, 184, 148, 0.05));
            border: 2px solid var(--success);
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            backdrop-filter: blur(10px);
        }

        .live-banner i {
            font-size: 30px;
            color: var(--success);
        }

        .live-banner-content h3 {
            color: var(--success);
            margin-bottom: 5px;
            font-size: 18px;
        }

        .live-banner-content p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        .policy-banner {
            background: linear-gradient(135deg, rgba(253, 203, 110, 0.15), rgba(253, 203, 110, 0.05));
            border: 2px solid var(--warning);
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .policy-banner i {
            font-size: 30px;
            color: var(--warning);
        }

        .policy-content h3 {
            color: var(--warning);
            margin-bottom: 10px;
        }

        .policy-content ul {
            list-style: none;
        }

        .policy-content li {
            margin: 8px 0;
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.9);
        }

        .policy-content li i {
            font-size: 16px;
            color: var(--warning);
        }

        .booking-panel {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .panel-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .panel-title i {
            font-size: 24px;
            color: var(--highlight);
            background: rgba(233, 69, 96, 0.2);
            padding: 12px;
            border-radius: 12px;
        }

        .panel-title h3 {
            font-size: 22px;
            color: white;
        }

        .booking-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            transition: all 0.3s;
            border: 1px solid transparent;
        }

        .detail-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(233, 69, 96, 0.3);
            transform: translateX(5px);
        }

        .detail-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--highlight), #ff7675);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .detail-icon i {
            font-size: 22px;
            color: white;
        }

        .detail-content {
            flex: 1;
        }

        .detail-label {
            font-size: 12px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .detail-value {
            font-size: 18px;
            font-weight: 600;
            color: white;
        }

        .payment-breakdown {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 30px 0;
        }

        .breakdown-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .breakdown-item.total {
            border-color: var(--highlight);
        }

        .breakdown-item.downpayment {
            border-color: var(--warning);
        }

        .breakdown-item.remaining {
            border-color: var(--info);
        }

        .breakdown-label {
            font-size: 12px;
            color: #aaa;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .breakdown-value {
            font-size: 24px;
            font-weight: 700;
        }

        .breakdown-item.total .breakdown-value {
            color: var(--highlight);
        }

        .breakdown-item.downpayment .breakdown-value {
            color: var(--warning);
        }

        .breakdown-item.remaining .breakdown-value {
            color: var(--info);
        }

        .breakdown-note {
            font-size: 11px;
            color: #aaa;
            margin-top: 8px;
        }

        .payment-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            margin: 30px 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .section-title i {
            font-size: 24px;
            color: var(--highlight);
            background: rgba(233, 69, 96, 0.2);
            padding: 12px;
            border-radius: 12px;
        }

        .section-title h3 {
            font-size: 22px;
            color: white;
        }

        .payment-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin: 25px 0;
        }

        .payment-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 25px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            width: 100%;
            border: none;
        }

        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s;
        }

        .payment-card:hover::before {
            left: 100%;
        }

        .payment-card:hover {
            transform: translateY(-5px);
            border-color: var(--highlight);
            box-shadow: 0 10px 30px rgba(233, 69, 96, 0.3);
        }

        .payment-card.gcash { --method-color: var(--gcash); }
        .payment-card.card { --method-color: var(--card); }
        .payment-card.paymaya { --method-color: var(--paymaya); }
        .payment-card.store { --method-color: var(--store); }

        .payment-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--method-color), color-mix(in srgb, var(--method-color) 70%, black));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .payment-icon i {
            font-size: 30px;
            color: white;
        }

        .payment-name {
            font-size: 18px;
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
        }

        .payment-desc {
            font-size: 12px;
            color: #aaa;
        }

        .payment-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            margin-top: 10px;
            color: var(--method-color);
        }

        .instructions-box {
            background: rgba(9, 132, 227, 0.1);
            border: 1px solid var(--info);
            border-radius: 16px;
            padding: 25px;
            margin-top: 30px;
        }

        .instructions-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .instructions-title i {
            color: var(--info);
            font-size: 20px;
        }

        .instructions-title h4 {
            color: var(--info);
            font-size: 16px;
        }

        .instructions-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .instruction-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }

        .instruction-item i {
            color: var(--info);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(8px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .modal-container {
            background: var(--secondary);
            border-radius: 24px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            border: 2px solid var(--highlight);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.3s ease-out;
            position: relative;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--highlight), #ff7675);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .modal-icon i {
            font-size: 36px;
            color: white;
        }

        .modal-title {
            font-size: 28px;
            font-weight: 700;
            color: white;
            margin-bottom: 15px;
        }

        .modal-message {
            color: #aaa;
            font-size: 16px;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .modal-details {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }

        .modal-detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-detail-item:last-child {
            border-bottom: none;
        }

        .modal-detail-label {
            color: #aaa;
            font-size: 14px;
        }

        .modal-detail-value {
            color: var(--highlight);
            font-weight: 600;
        }

        .modal-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .modal-btn {
            flex: 1;
            padding: 15px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .modal-btn-primary {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
        }

        .modal-btn-primary:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-2px);
        }

        .modal-btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .modal-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-2px);
        }

        .modal-btn-store {
            background: linear-gradient(135deg, var(--store), #00a085);
            color: white;
        }

        .modal-btn-store:hover {
            background: linear-gradient(135deg, #00a085, var(--store));
            transform: translateY(-2px);
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        /* Method-specific modal icons */
        .modal-gcash .modal-icon {
            background: linear-gradient(135deg, var(--gcash), #0040b0);
        }

        .modal-card .modal-icon {
            background: linear-gradient(135deg, var(--card), #5649c0);
        }

        .modal-paymaya .modal-icon {
            background: linear-gradient(135deg, var(--paymaya), #cc3d3d);
        }

        .modal-store .modal-icon {
            background: linear-gradient(135deg, var(--store), #00a085);
        }

        footer {
            text-align: center;
            padding: 30px;
            margin-top: 50px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #aaa;
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

            .booking-details-grid {
                grid-template-columns: 1fr;
            }

            .payment-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .instructions-list {
                grid-template-columns: 1fr;
            }

            .payment-breakdown {
                grid-template-columns: 1fr;
            }

            .modal-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .payment-grid {
                grid-template-columns: 1fr;
            }
        }

        .error-container {
            background: rgba(214, 48, 49, 0.2);
            border: 2px solid var(--danger);
            border-radius: 10px;
            padding: 20px;
            margin: 20px;
            color: var(--danger);
        }

        .error-container pre {
            background: rgba(0,0,0,0.3);
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
            white-space: pre-wrap;
            color: #ff9999;
        }
    </style>
</head>
<body>
    <!-- Store Payment Modal -->
    <div class="modal-overlay" id="storeModal">
        <div class="modal-container modal-store">
            <button class="modal-close" onclick="closeModal('storeModal')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="fas fa-store"></i>
            </div>
            <h2 class="modal-title">Confirm Store Payment</h2>
            <p class="modal-message">Please confirm your store payment details</p>
            <div class="modal-details">
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Total Amount:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($total_amount, 2); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Downpayment (20%):</span>
                    <span class="modal-detail-value">₱<?php echo number_format($downpayment, 2); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Remaining Balance:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($remaining, 2); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Booking #:</span>
                    <span class="modal-detail-value"><?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Room:</span>
                    <span class="modal-detail-value"><?php echo htmlspecialchars($booking['room_name']); ?></span>
                </div>
            </div>
            <div class="modal-buttons">
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="pay_with_paymongo" value="1">
                    <input type="hidden" name="payment_method" value="store">
                    <button type="submit" class="modal-btn modal-btn-store">Confirm & Pay at Store</button>
                </form>
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('storeModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- GCash Payment Modal -->
    <div class="modal-overlay" id="gcashModal">
        <div class="modal-container modal-gcash">
            <button class="modal-close" onclick="closeModal('gcashModal')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="fas fa-mobile-alt"></i>
            </div>
            <h2 class="modal-title">Pay with GCash</h2>
            <p class="modal-message">You will be redirected to PayMongo secure checkout</p>
            <div class="modal-details">
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Amount:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($total_amount, 2); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Payment Method:</span>
                    <span class="modal-detail-value">GCash</span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Booking #:</span>
                    <span class="modal-detail-value"><?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Room:</span>
                    <span class="modal-detail-value"><?php echo htmlspecialchars($booking['room_name']); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Duration:</span>
                    <span class="modal-detail-value"><?php echo number_format($hours, 1); ?> hours</span>
                </div>
            </div>
            <div class="modal-buttons">
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="pay_with_paymongo" value="1">
                    <input type="hidden" name="payment_method" value="gcash">
                    <button type="submit" class="modal-btn modal-btn-primary">Proceed to PayMongo</button>
                </form>
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('gcashModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Card Payment Modal -->
    <div class="modal-overlay" id="cardModal">
        <div class="modal-container modal-card">
            <button class="modal-close" onclick="closeModal('cardModal')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <h2 class="modal-title">Pay with Card</h2>
            <p class="modal-message">You will be redirected to PayMongo secure checkout</p>
            <div class="modal-details">
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Amount:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($total_amount, 2); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Payment Method:</span>
                    <span class="modal-detail-value">Credit/Debit Card</span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Booking #:</span>
                    <span class="modal-detail-value"><?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Room:</span>
                    <span class="modal-detail-value"><?php echo htmlspecialchars($booking['room_name']); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Duration:</span>
                    <span class="modal-detail-value"><?php echo number_format($hours, 1); ?> hours</span>
                </div>
            </div>
            <div class="modal-buttons">
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="pay_with_paymongo" value="1">
                    <input type="hidden" name="payment_method" value="card">
                    <button type="submit" class="modal-btn modal-btn-primary">Proceed to PayMongo</button>
                </form>
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('cardModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- PayMaya Payment Modal -->
    <div class="modal-overlay" id="paymayaModal">
        <div class="modal-container modal-paymaya">
            <button class="modal-close" onclick="closeModal('paymayaModal')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <h2 class="modal-title">Pay with PayMaya</h2>
            <p class="modal-message">You will be redirected to PayMongo secure checkout</p>
            <div class="modal-details">
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Amount:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($total_amount, 2); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Payment Method:</span>
                    <span class="modal-detail-value">PayMaya</span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Booking #:</span>
                    <span class="modal-detail-value"><?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Room:</span>
                    <span class="modal-detail-value"><?php echo htmlspecialchars($booking['room_name']); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Duration:</span>
                    <span class="modal-detail-value"><?php echo number_format($hours, 1); ?> hours</span>
                </div>
            </div>
            <div class="modal-buttons">
                <form method="POST" style="flex: 1;">
                    <input type="hidden" name="pay_with_paymongo" value="1">
                    <input type="hidden" name="payment_method" value="paymaya">
                    <button type="submit" class="modal-btn modal-btn-primary">Proceed to PayMongo</button>
                </form>
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('paymayaModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Error Display -->
    <?php if (isset($error)): ?>
    <div class="error-container">
        <i class="fas fa-exclamation-circle"></i> Payment Error
        <pre><?php echo htmlspecialchars(print_r($error, true)); ?></pre>
        <p style="margin-top: 10px; font-size: 14px;">Please try again or contact support.</p>
    </div>
    <?php endif; ?>

    <header>
        <div class="header-left">
            <h1><i class="fas fa-microphone-alt"></i> Sirene KTV</h1>
            <p>Complete your payment securely</p>
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
            <h2>
                <i class="fas fa-credit-card"></i>
                Complete Payment
            </h2>
            <p>Booking #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></p>
        </div>

        <!-- Live Mode Banner -->
        <div class="live-banner">
            <i class="fas fa-check-circle"></i>
            <div class="live-banner-content">
                <h3><i class="fas fa-shield-alt"></i> SECURE PAYMENT</h3>
                <p>Payments are processed securely by PayMongo. We never store your card details.</p>
            </div>
        </div>

        <!-- Cancellation Policy Banner -->
        <div class="policy-banner">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="policy-content">
                <h3><i class="fas fa-gavel"></i> Cancellation Policy</h3>
                <ul>
                    <li><i class="fas fa-clock"></i> Cancel up to 24 hours before booking for FULL refund</li>
                    <li><i class="fas fa-hourglass-half"></i> Cancel within 24 hours: 20% downpayment is non-refundable</li>
                    <li><i class="fas fa-times-circle"></i> No-show: Full charge applies (₱<?php echo number_format($total_amount, 2); ?>)</li>
                </ul>
            </div>
        </div>

        <!-- Booking Details Panel -->
        <div class="booking-panel">
            <div class="panel-title">
                <i class="fas fa-receipt"></i>
                <h3>Booking Summary</h3>
            </div>
            
            <div class="booking-details-grid">
                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-door-closed"></i>
                    </div>
                    <div class="detail-content">
                        <div class="detail-label">Room</div>
                        <div class="detail-value"><?php echo htmlspecialchars($booking['room_name']); ?></div>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="detail-content">
                        <div class="detail-label">Date</div>
                        <div class="detail-value"><?php echo date('l, F j, Y', strtotime($booking['booking_date'])); ?></div>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="detail-content">
                        <div class="detail-label">Time</div>
                        <div class="detail-value">
                            <?php echo date('g:i A', strtotime($booking['start_time'])); ?> - 
                            <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                        </div>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="detail-content">
                        <div class="detail-label">Duration</div>
                        <div class="detail-value"><?php echo number_format($hours, 1); ?> hour<?php echo $hours > 1 ? 's' : ''; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Breakdown -->
        <div class="payment-breakdown">
            <div class="breakdown-item total">
                <div class="breakdown-label">Total Amount</div>
                <div class="breakdown-value">₱<?php echo number_format($total_amount, 2); ?></div>
            </div>
            <div class="breakdown-item downpayment">
                <div class="breakdown-label">Downpayment (20%)</div>
                <div class="breakdown-value">₱<?php echo number_format($downpayment, 2); ?></div>
                <div class="breakdown-note">Non-refundable if cancelled within 24hrs</div>
            </div>
            <div class="breakdown-item remaining">
                <div class="breakdown-label">Remaining Balance</div>
                <div class="breakdown-value">₱<?php echo number_format($remaining, 2); ?></div>
                <div class="breakdown-note">Payable at store</div>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="payment-section">
            <div class="section-title">
                <i class="fas fa-wallet"></i>
                <h3>Select Payment Method</h3>
            </div>

            <div class="payment-grid">
                <!-- GCash -->
                <div class="payment-card gcash" onclick="showModal('gcashModal')">
                    <div class="payment-icon">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <h4 class="payment-name">GCash</h4>
                    <p class="payment-desc">Pay via GCash</p>
                    <span class="payment-badge">PayMongo</span>
                </div>

                <!-- Credit Card -->
                <div class="payment-card card" onclick="showModal('cardModal')">
                    <div class="payment-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h4 class="payment-name">Credit Card</h4>
                    <p class="payment-desc">Visa, Mastercard, JCB</p>
                    <span class="payment-badge">PayMongo</span>
                </div>

                <!-- PayMaya -->
                <div class="payment-card paymaya" onclick="showModal('paymayaModal')">
                    <div class="payment-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h4 class="payment-name">PayMaya</h4>
                    <p class="payment-desc">Pay via PayMaya</p>
                    <span class="payment-badge">PayMongo</span>
                </div>

                <!-- Pay at Store -->
                <div class="payment-card store" onclick="showModal('storeModal')">
                    <div class="payment-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <h4 class="payment-name">Pay at Store</h4>
                    <p class="payment-desc">Pay in person</p>
                    <span class="payment-badge">20% Downpayment</span>
                </div>
            </div>

            <!-- Payment Instructions -->
            <div class="instructions-box">
                <div class="instructions-title">
                    <i class="fas fa-info-circle"></i>
                    <h4>Payment Instructions</h4>
                </div>
                <div class="instructions-list">
                    <div class="instruction-item">
                        <i class="fas fa-mobile-alt"></i>
                        <span><strong>GCash/PayMaya:</strong> You'll be redirected to PayMongo</span>
                    </div>
                    <div class="instruction-item">
                        <i class="fas fa-credit-card"></i>
                        <span><strong>Card:</strong> Secure 3D Secure checkout</span>
                    </div>
                    <div class="instruction-item">
                        <i class="fas fa-store"></i>
                        <span><strong>Store:</strong> Pay 20% downpayment at store</span>
                    </div>
                    <div class="instruction-item">
                        <i class="fas fa-clock"></i>
                        <span><strong>Booking #:</strong> <?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 Sirene KTV. All Rights Reserved. Payments powered by PayMongo.</p>
    </footer>

    <!-- JavaScript for modals -->
    <script>
    function showModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    });
    </script>
</body>
</html>