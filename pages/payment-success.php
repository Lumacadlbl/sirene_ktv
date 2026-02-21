<?php
session_start();
include "../db.php";

$booking_id = $_GET['booking_id'] ?? 0;
$session_id = $_GET['session_id'] ?? '';

if ($session_id) {
    // Verify payment with PayMongo
    $paymongo_secret_key = 'sk_test_CuXgiJJHcBEX24FTGBE6KxPd';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.paymongo.com/v1/checkout_sessions/" . $session_id);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($paymongo_secret_key . ':')
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $session = json_decode($response, true);
    
    if ($session['data']['attributes']['status'] == 'paid') {
        // Update booking status
        $update = $conn->prepare("UPDATE booking SET payment_status = 'paid' WHERE b_id = ?");
        $update->bind_param("i", $booking_id);
        $update->execute();
        
        // Update payments table
        $update_payment = $conn->prepare("UPDATE payments SET payment_status = 'completed' WHERE transaction_id = ?");
        $update_payment->bind_param("s", $session_id);
        $update_payment->execute();
    }
}

// Show success page
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Successful</title>
    <meta http-equiv="refresh" content="3;url=my-bookings.php">
</head>
<body>
    <h2>Payment Successful!</h2>
    <p>Redirecting to your bookings...</p>
</body>
</html>