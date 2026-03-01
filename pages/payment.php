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
$payment_type = $_GET['type'] ?? 'full'; // 'full' or 'food_only'

// Debug - log the request
error_log("Payment page accessed - Booking ID: $booking_id, Type: $payment_type");

// Validate booking_id
if (!$booking_id) {
    error_log("No booking ID provided");
    header("Location: my-bookings.php?error=invalid_booking");
    exit;
}

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

// Check if store_payment_id column exists
$check_store_payment = $conn->query("SHOW COLUMNS FROM booking LIKE 'store_payment_id'");
if ($check_store_payment->num_rows == 0) {
    $conn->query("ALTER TABLE booking ADD COLUMN store_payment_id VARCHAR(100) NULL AFTER paymongo_payment_id");
}

// Check if payment_id column exists in booking_food
$check_food_payment = $conn->query("SHOW COLUMNS FROM booking_food LIKE 'payment_id'");
if ($check_food_payment->num_rows == 0) {
    $conn->query("ALTER TABLE booking_food ADD COLUMN payment_id VARCHAR(100) NULL AFTER served");
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
    error_log("Booking not found - ID: $booking_id, User: $user_id");
    header("Location: my-bookings.php?error=booking_not_found");
    exit;
}

// For food-only payments, we don't care if room is paid or not
// Customer should be able to pay for additional food even if room is already paid
if ($payment_type != 'food_only' && ($booking['payment_status'] == 'paid' || $booking['payment_status'] == 'approved')) {
    // This is a full payment attempt but room is already paid - redirect to food-only payment
    error_log("Room already paid, redirecting to food-only payment");
    header("Location: payment.php?id=" . $booking_id . "&type=food_only");
    exit;
}

// Fetch UNPAID food items (items that don't have a payment_id)
$food_query = $conn->prepare("
    SELECT 
        bf.*,
        fb.item_name,
        fb.category
    FROM booking_food bf
    JOIN food_beverages fb ON bf.f_id = fb.f_id
    WHERE bf.b_id = ? 
    AND bf.served != 'cancelled' 
    AND (bf.payment_id IS NULL OR bf.payment_id = '')
    ORDER BY bf.bf_id DESC
");

$food_query->bind_param("i", $booking_id);
$food_query->execute();
$food_result = $food_query->get_result();
$unpaid_food_items = $food_result->fetch_all(MYSQLI_ASSOC);

error_log("Found " . count($unpaid_food_items) . " unpaid food items");

// Calculate duration
$start = new DateTime($booking['start_time']);
$end = new DateTime($booking['end_time']);
$interval = $start->diff($end);
$hours = $interval->h + ($interval->i / 60);

// Calculate totals based on payment type
$room_total = $booking['total_amount'];
$unpaid_food_total = 0;
foreach ($unpaid_food_items as $item) {
    $unpaid_food_total += ($item['price'] * $item['quantity']);
}

// Also fetch previously paid food items for display
$paid_food_query = $conn->prepare("
    SELECT 
        bf.*,
        fb.item_name,
        fb.category
    FROM booking_food bf
    JOIN food_beverages fb ON bf.f_id = fb.f_id
    WHERE bf.b_id = ? 
    AND bf.payment_id IS NOT NULL 
    AND bf.payment_id != ''
    ORDER BY bf.bf_id DESC
");

$paid_food_query->bind_param("i", $booking_id);
$paid_food_query->execute();
$paid_food_result = $paid_food_query->get_result();
$paid_food_items = $paid_food_result->fetch_all(MYSQLI_ASSOC);

if ($payment_type == 'food_only') {
    // For food-only payment, only charge for unpaid food items
    $grand_total = $unpaid_food_total;
    $downpayment = 0;
    $remaining = 0;
    $is_food_only = true;
    $payment_description = "Food Order - Booking #" . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
    
    // If no unpaid food items, show message
    if ($unpaid_food_total <= 0) {
        $no_food_error = "No unpaid food items to pay for.";
        error_log("No unpaid food items for booking: $booking_id");
    }
} else {
    // For full payment, charge room + all unpaid food
    $grand_total = $room_total + $unpaid_food_total;
    $downpayment = $room_total * 0.20; // 20% of room only
    $remaining = $grand_total - $downpayment;
    $is_food_only = false;
    $payment_description = "Booking #" . str_pad($booking_id, 6, '0', STR_PAD_LEFT) . " - " . $booking['room_name'];
}

// Function to create PayMongo checkout session
function createPayMongoCheckout($amount, $description, $booking_id, $user_id, $payment_method, $secret_key, $room_name, $hours, $unpaid_food_items = [], $room_total = 0, $unpaid_food_total = 0, $is_food_only = false) {
    $amount_in_centavos = $amount * 100;
    
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
    $line_items = [];
    
    if (!$is_food_only && $room_total > 0) {
        // Add room booking as first line item (only for full payment)
        $line_items[] = [
            'name' => $room_name . ' - KTV Room',
            'quantity' => 1,
            'amount' => $room_total * 100,
            'description' => 'Room rental for ' . number_format($hours, 1) . ' hours',
            'currency' => 'PHP'
        ];
    }
    
    // Add unpaid food items
    if (!empty($unpaid_food_items)) {
        foreach ($unpaid_food_items as $item) {
            $line_items[] = [
                'name' => $item['item_name'],
                'quantity' => intval($item['quantity']),
                'amount' => floatval($item['price']) * 100,
                'description' => $item['category'] ?? 'Food Item',
                'currency' => 'PHP'
            ];
        }
    }
    
    // Create metadata
    $metadata = [
        'booking_id' => $booking_id,
        'user_id' => $user_id,
        'payment_type' => $is_food_only ? 'food_only' : 'full',
        'room_name' => $room_name,
        'hours' => $hours,
        'room_total' => $room_total,
        'food_total' => $unpaid_food_total,
        'grand_total' => $amount,
        'has_food' => !empty($unpaid_food_items) ? 'yes' : 'no'
    ];
    
    // Add food items to metadata
    if (!empty($unpaid_food_items)) {
        $food_list = [];
        $food_ids = [];
        foreach ($unpaid_food_items as $index => $item) {
            $food_list[] = $item['item_name'] . ' (x' . $item['quantity'] . ')';
            if ($item['bf_id']) {
                $food_ids[] = $item['bf_id'];
            }
        }
        $metadata['food_items'] = implode(', ', $food_list);
        $metadata['food_item_ids'] = implode(',', $food_ids);
    }
    
    $data = [
        'data' => [
            'attributes' => [
                'line_items' => $line_items,
                'payment_method_types' => $payment_method_types,
                'success_url' => 'http://localhost/sirene_ktv/pages/payment-success.php?booking_id=' . $booking_id . '&session_id={CHECKOUT_SESSION_ID}&type=' . ($is_food_only ? 'food_only' : 'full'),
                'cancel_url' => 'http://localhost/sirene_ktv/pages/payment-failed.php?booking_id=' . $booking_id . '&type=' . ($is_food_only ? 'food_only' : 'full'),
                'description' => $description,
                'metadata' => $metadata,
                'statement_descriptor' => 'Sirene KTV ' . ($is_food_only ? 'Food' : 'Booking')
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Only for local testing, remove in production
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("cURL Error: " . $error);
        return ['error' => true, 'message' => $error];
    }
    
    curl_close($ch);
    
    $response_data = json_decode($response, true);
    
    if ($http_code >= 400) {
        error_log("PayMongo Error Response: " . print_r($response_data, true));
        return ['error' => true, 'response' => $response_data];
    }
    
    return ['error' => false, 'response' => $response_data];
}

// Handle PayMongo payment creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['pay_with_paymongo'])) {
    error_log("Payment form submitted");
    
    $payment_method = $_POST['payment_method'];
    
    // Determine amount to pay based on payment type
    if ($is_food_only) {
        $amount_to_pay = $unpaid_food_total;
        $final_description = "Food Order - Booking #" . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
    } else {
        $amount_to_pay = ($payment_method == 'store') ? $downpayment : $grand_total;
        $final_description = "Booking #" . str_pad($booking_id, 6, '0', STR_PAD_LEFT) . " - " . $booking['room_name'];
    }
    
    // Add food info to description if there are food items
    if (!empty($unpaid_food_items)) {
        $final_description .= " + Food (" . count($unpaid_food_items) . " items)";
    }
    
    error_log("Payment amount: $amount_to_pay, Method: $payment_method");
    
    if ($payment_method == 'store') {
        // Handle store payment for both full and food-only
        try {
            $conn->begin_transaction();
            
            if ($is_food_only) {
                // For food-only store payment
                // Update booking_food records with a store payment identifier
                $store_payment_id = 'STORE_' . uniqid() . '_' . date('YmdHis');
                
                $update = $conn->prepare("UPDATE booking_food SET payment_id = ? WHERE b_id = ? AND (payment_id IS NULL OR payment_id = '')");
                $update->bind_param("si", $store_payment_id, $booking_id);
                $update->execute();
                $updated_count = $update->affected_rows;
                error_log("Updated $updated_count food items with store payment ID: $store_payment_id");
                
                // Update booking to track store payment
                $update_booking = $conn->prepare("UPDATE booking SET store_payment_id = ? WHERE b_id = ?");
                $update_booking->bind_param("si", $store_payment_id, $booking_id);
                $update_booking->execute();
                
                // Insert into payments table
                $payment_insert = $conn->prepare("
                    INSERT INTO payments (b_id, u_id, payment_method, payment_status, amount, payment_date) 
                    VALUES (?, ?, 'store', 'pending', ?, NOW())
                ");
                $payment_insert->bind_param("iid", $booking_id, $user_id, $unpaid_food_total);
                $payment_insert->execute();
                
                $conn->commit();
                
                error_log("Store food payment recorded, redirecting to success page");
                header("Location: payment-success.php?booking_id=" . $booking_id . "&method=store&type=food_only");
                exit;
                
            } else {
                // For full payment store payment (downpayment only)
                $update = $conn->prepare("UPDATE booking SET payment_status = 'pending_store', downpayment = ? WHERE b_id = ?");
                $update->bind_param("di", $downpayment, $booking_id);
                $update->execute();
                
                // Insert into payments table
                $payment_insert = $conn->prepare("
                    INSERT INTO payments (b_id, u_id, payment_method, payment_status, amount, payment_date) 
                    VALUES (?, ?, 'store', 'pending', ?, NOW())
                ");
                $payment_insert->bind_param("iid", $booking_id, $user_id, $downpayment);
                $payment_insert->execute();
                
                $conn->commit();
                
                error_log("Store payment recorded, redirecting to success page");
                header("Location: payment-success.php?booking_id=" . $booking_id . "&method=store");
                exit;
            }
            
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Database error: " . $e->getMessage();
            error_log("Store payment error: " . $e->getMessage());
        }
    } else {
        // Create PayMongo checkout session
        error_log("Creating PayMongo checkout session");
        $result = createPayMongoCheckout(
            $amount_to_pay, 
            $final_description, 
            $booking_id, 
            $user_id, 
            $payment_method, 
            $paymongo_secret_key,
            $booking['room_name'],
            $hours,
            $unpaid_food_items,
            $room_total,
            $unpaid_food_total,
            $is_food_only
        );
        
        if ($result['error']) {
            if (isset($result['response']['errors'])) {
                $error = "PayMongo Error: " . json_encode($result['response']['errors']);
                error_log("PayMongo API Error: " . json_encode($result['response']['errors']));
            } else {
                $error = "Failed to create payment: " . ($result['message'] ?? 'Unknown error');
                error_log("Payment creation error: " . ($result['message'] ?? 'Unknown error'));
            }
        } else {
            // Get checkout URL and session ID from response
            $checkout_url = $result['response']['data']['attributes']['checkout_url'];
            $session_id = $result['response']['data']['id'];
            
            error_log("PayMongo session created: $session_id");
            error_log("Checkout URL: $checkout_url");
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                if ($is_food_only) {
                    // For food-only payment, store payment ID in booking_food records for unpaid items
                    $update = $conn->prepare("UPDATE booking_food SET payment_id = ? WHERE b_id = ? AND (payment_id IS NULL OR payment_id = '')");
                    $update->bind_param("si", $session_id, $booking_id);
                    $update->execute();
                    $updated_count = $update->affected_rows;
                    error_log("Updated $updated_count food items with payment_id: $session_id");
                    
                    // Also update booking table to track the payment
                    $update_booking = $conn->prepare("UPDATE booking SET paymongo_payment_id = ? WHERE b_id = ?");
                    $update_booking->bind_param("si", $session_id, $booking_id);
                    $update_booking->execute();
                    
                    // Update booking status if needed (but don't change if already paid)
                    if ($booking['payment_status'] != 'paid' && $booking['payment_status'] != 'approved') {
                        $update_status = $conn->prepare("UPDATE booking SET payment_status = 'food_payment_pending' WHERE b_id = ?");
                        $update_status->bind_param("i", $booking_id);
                        $update_status->execute();
                    }
                } else {
                    // For full payment, store session_id in booking table
                    $update = $conn->prepare("UPDATE booking SET paymongo_payment_id = ?, payment_status = 'pending_payment' WHERE b_id = ?");
                    $update->bind_param("si", $session_id, $booking_id);
                    $update->execute();
                }
                
                // Insert into payments table
                $payment_insert = $conn->prepare("
                    INSERT INTO payments (b_id, u_id, payment_method, payment_status, amount, payment_date) 
                    VALUES (?, ?, ?, 'pending', ?, NOW())
                ");
                $payment_insert->bind_param("iisd", $booking_id, $user_id, $payment_method, $amount_to_pay);
                $payment_insert->execute();
                
                $conn->commit();
                
                error_log("Database updated, redirecting to PayMongo: $checkout_url");
                
                // Redirect to PayMongo checkout using JavaScript for reliability
                echo "<script>
                    console.log('Redirecting to PayMongo: " . $checkout_url . "');
                    window.location.href = '" . $checkout_url . "';
                </script>";
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Database error: " . $e->getMessage();
                error_log("Database transaction error: " . $e->getMessage());
            }
        }
    }
}

// For debugging - show the actual error
if (isset($error)) {
    echo "<div style='background: rgba(214, 48, 49, 0.2); border: 2px solid #d63031; border-radius: 20px; padding: 30px; margin: 40px auto; max-width: 800px; color: #d63031; text-align: center;'>";
    echo "<h3 style='margin-bottom: 20px;'><i class='fas fa-exclamation-circle' style='font-size: 40px;'></i><br>Payment Error</h3>";
    echo "<div style='background: rgba(0,0,0,0.3); padding: 20px; border-radius: 10px; margin: 20px 0; color: #ff9999; text-align: left; overflow-x: auto;'>";
    echo "<pre style='white-space: pre-wrap;'>" . htmlspecialchars(print_r($error, true)) . "</pre>";
    echo "</div>";
    echo "<p style='margin-bottom: 25px; font-size: 16px;'>Please try again or contact support.</p>";
    echo "<div style='display: flex; gap: 15px; justify-content: center;'>";
    echo "<a href='payment.php?id=" . $booking_id . "&type=" . $payment_type . "' style='display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #666, #444); color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>Try Again</a>";
    echo "<a href='my-bookings.php' style='display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #d63031, #c0392b); color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>Back to Bookings</a>";
    echo "</div>";
    echo "</div>";
    exit;
}

// Show no food error message
if (isset($no_food_error)) {
    echo "<div style='background: rgba(253, 203, 110, 0.2); border: 2px solid #fdcb6e; border-radius: 20px; padding: 50px; margin: 50px auto; max-width: 600px; color: #fdcb6e; text-align: center;'>";
    echo "<i class='fas fa-utensils' style='font-size: 70px; margin-bottom: 25px;'></i>";
    echo "<h3 style='margin-bottom: 20px; font-size: 28px;'>No Unpaid Food Items</h3>";
    echo "<p style='margin-bottom: 35px; font-size: 16px; line-height: 1.6;'>" . $no_food_error . "</p>";
    echo "<a href='my-bookings.php' style='display: inline-block; padding: 15px 45px; background: linear-gradient(135deg, #fdcb6e, #e8a822); color: #333; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 16px;'>Back to Bookings</a>";
    echo "</div>";
    exit;
}

// If this is a food-only payment but there are unpaid items, show the payment page
// Otherwise, continue with the HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_food_only ? 'Food Payment' : 'Payment'; ?> - Booking #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?> - Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Copy all your existing CSS here - keeping it exactly the same */
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
            --dark: #0d1117;
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #d63031;
            --info: #0984e3;
            --purple: #6c5ce7;
            --orange: #e67e22;
            --teal: #008080;
            --gcash: #0057e4;
            --paymaya: #ff4d4d;
            --card: #6c5ce7;
            --store: #00b894;
            --food: #e67e22;
            --paid: #00b894;
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
            flex-wrap: wrap;
            gap: 15px;
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
            flex-wrap: wrap;
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
            max-width: 1200px;
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

        .payment-type-banner {
            background: <?php echo $is_food_only ? 'linear-gradient(135deg, rgba(230, 126, 34, 0.15), rgba(230, 126, 34, 0.05))' : 'linear-gradient(135deg, rgba(0, 184, 148, 0.15), rgba(0, 184, 148, 0.05))'; ?>;
            border: 2px solid <?php echo $is_food_only ? 'var(--food)' : 'var(--success)'; ?>;
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .payment-type-banner i {
            font-size: 40px;
            color: <?php echo $is_food_only ? 'var(--food)' : 'var(--success)'; ?>;
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 50%;
        }

        .banner-content h3 {
            color: <?php echo $is_food_only ? 'var(--food)' : 'var(--success)'; ?>;
            margin-bottom: 8px;
            font-size: 20px;
            font-weight: 600;
        }

        .banner-content p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 15px;
            line-height: 1.5;
        }

        .policy-banner {
            background: linear-gradient(135deg, rgba(253, 203, 110, 0.15), rgba(253, 203, 110, 0.05));
            border: 2px solid var(--warning);
            border-radius: 20px;
            padding: 25px 30px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .policy-banner i {
            font-size: 40px;
            color: var(--warning);
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 50%;
        }

        .policy-content h3 {
            color: var(--warning);
            margin-bottom: 15px;
            font-size: 20px;
        }

        .policy-content ul {
            list-style: none;
        }

        .policy-content li {
            margin: 10px 0;
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.95);
            font-size: 15px;
        }

        .policy-content li i {
            font-size: 18px;
            color: var(--warning);
            background: transparent;
            padding: 0;
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

        .food-items-panel {
            background: rgba(230, 126, 34, 0.1);
            border: 1px solid var(--food);
            border-radius: 16px;
            padding: 25px;
            margin-top: 25px;
        }

        .food-items-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            color: var(--food);
            font-size: 18px;
            font-weight: 600;
        }

        .food-items-title i {
            font-size: 24px;
        }

        .food-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }

        .food-item-row:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .food-item-row:last-child {
            border-bottom: none;
        }

        .food-item-info {
            flex: 2;
        }

        .food-item-name {
            font-weight: 600;
            color: white;
            margin-bottom: 5px;
            font-size: 16px;
        }

        .food-item-category {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
        }

        .food-item-quantity {
            flex: 1;
            text-align: center;
            color: var(--food);
            font-weight: 600;
            font-size: 16px;
        }

        .food-item-price {
            flex: 1;
            text-align: right;
            color: var(--highlight);
            font-weight: 600;
            font-size: 18px;
        }

        .paid-items-panel {
            background: rgba(0, 184, 148, 0.1);
            border: 1px solid var(--paid);
            border-radius: 16px;
            padding: 25px;
            margin-top: 25px;
        }

        .paid-items-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            color: var(--paid);
            font-size: 18px;
            font-weight: 600;
        }

        .paid-items-title i {
            font-size: 24px;
        }

        .paid-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            opacity: 0.8;
        }

        .paid-item-row:last-child {
            border-bottom: none;
        }

        .paid-item-name {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.8);
        }

        .paid-item-badge {
            background: rgba(0, 184, 148, 0.3);
            color: var(--paid);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
        }

        .payment-breakdown {
            display: grid;
            grid-template-columns: <?php echo $is_food_only ? 'repeat(1, 1fr)' : 'repeat(4, 1fr)'; ?>;
            gap: 20px;
            margin: 30px 0;
        }

        .breakdown-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 16px;
            padding: 25px 20px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }

        .breakdown-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .breakdown-item.total {
            border-color: var(--highlight);
            background: rgba(233, 69, 96, 0.1);
        }

        .breakdown-item.food {
            border-color: var(--food);
            background: rgba(230, 126, 34, 0.1);
        }

        .breakdown-item.downpayment {
            border-color: var(--warning);
            background: rgba(253, 203, 110, 0.1);
        }

        .breakdown-item.remaining {
            border-color: var(--info);
            background: rgba(9, 132, 227, 0.1);
        }

        .breakdown-item.total .breakdown-value {
            color: var(--highlight);
        }

        .breakdown-item.food .breakdown-value {
            color: var(--food);
        }

        .breakdown-item.downpayment .breakdown-value {
            color: var(--warning);
        }

        .breakdown-item.remaining .breakdown-value {
            color: var(--info);
        }

        .breakdown-label {
            font-size: 13px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .breakdown-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .breakdown-note {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
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
            grid-template-columns: <?php echo $is_food_only ? 'repeat(4, 1fr)' : 'repeat(4, 1fr)'; ?>;
            gap: 20px;
            margin: 25px 0;
        }

        .payment-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
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
            box-shadow: 0 15px 40px rgba(233, 69, 96, 0.3);
        }

        .payment-card.gcash { --method-color: var(--gcash); }
        .payment-card.card { --method-color: var(--card); }
        .payment-card.paymaya { --method-color: var(--paymaya); }
        .payment-card.store { --method-color: var(--store); }

        .payment-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--method-color), color-mix(in srgb, var(--method-color) 70%, black));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .payment-icon i {
            font-size: 35px;
            color: white;
        }

        .payment-name {
            font-size: 20px;
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
        }

        .payment-desc {
            font-size: 13px;
            color: #aaa;
            margin-bottom: 15px;
        }

        .payment-badge {
            display: inline-block;
            background: rgba(255, 255, 255, 0.1);
            padding: 5px 15px;
            border-radius: 25px;
            font-size: 12px;
            color: var(--method-color);
            font-weight: 600;
        }

        .instructions-box {
            background: rgba(9, 132, 227, 0.1);
            border: 1px solid var(--info);
            border-radius: 20px;
            padding: 25px;
            margin-top: 30px;
        }

        .instructions-title {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .instructions-title i {
            color: var(--info);
            font-size: 22px;
        }

        .instructions-title h4 {
            color: var(--info);
            font-size: 18px;
            font-weight: 600;
        }

        .instructions-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .instruction-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .instruction-item i {
            color: var(--info);
            font-size: 18px;
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
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 30px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            border: 2px solid var(--highlight);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: modalSlideIn 0.3s ease-out;
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
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
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--highlight), #ff7675);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .modal-icon i {
            font-size: 40px;
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
            border-radius: 20px;
            padding: 25px;
            margin: 25px 0;
            text-align: left;
        }

        .modal-detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-detail-item:last-child {
            border-bottom: none;
        }

        .modal-detail-label {
            color: #aaa;
            font-size: 15px;
        }

        .modal-detail-value {
            color: var(--highlight);
            font-weight: 600;
            font-size: 16px;
        }

        .modal-food-items {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--food);
        }

        .modal-food-title {
            color: var(--food);
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-food-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 15px;
            color: rgba(255, 255, 255, 0.9);
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
            border: 1px solid rgba(255, 255, 255, 0.2);
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }

        footer {
            text-align: center;
            padding: 30px;
            margin-top: 50px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #aaa;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            header {
                padding: 15px 20px;
            }
            
            .header-right {
                width: 100%;
                justify-content: center;
            }
            
            .user-info, .back-btn {
                width: 100%;
                justify-content: center;
            }
            
            .container {
                padding: 0 20px;
            }
            
            .payment-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .payment-breakdown {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .booking-details-grid {
                grid-template-columns: 1fr;
            }
            
            .instructions-list {
                grid-template-columns: 1fr;
            }
            
            .policy-banner, .payment-type-banner {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .modal-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .payment-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-breakdown {
                grid-template-columns: 1fr;
            }
            
            .food-item-row {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .food-item-info, .food-item-quantity, .food-item-price {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Store Payment Modal (show for both full and food-only) -->
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
                <?php if (!$is_food_only): ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Room Total:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($room_total, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($unpaid_food_total > 0): ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Food Total:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($unpaid_food_total, 2); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Total Amount:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($is_food_only ? $unpaid_food_total : $grand_total, 2); ?></span>
                </div>
                
                <?php if (!$is_food_only): ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Downpayment (20%):</span>
                    <span class="modal-detail-value" style="color: var(--warning);">₱<?php echo number_format($downpayment, 2); ?></span>
                </div>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Remaining Balance:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($remaining, 2); ?></span>
                </div>
                <?php else: ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Payment Type:</span>
                    <span class="modal-detail-value" style="color: var(--food);">Food Only</span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($unpaid_food_items)): ?>
                <div class="modal-food-items">
                    <div class="modal-food-title">
                        <i class="fas fa-utensils"></i> Unpaid Food Items
                    </div>
                    <?php foreach ($unpaid_food_items as $item): ?>
                    <div class="modal-food-item">
                        <span><?php echo htmlspecialchars($item['item_name']); ?> x<?php echo $item['quantity']; ?></span>
                        <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-buttons">
                <form method="POST" id="storePaymentForm" style="flex: 1;">
                    <input type="hidden" name="pay_with_paymongo" value="1">
                    <input type="hidden" name="payment_method" value="store">
                    <button type="submit" class="modal-btn modal-btn-store" onclick="submitPaymentForm('storePaymentForm')">
                        Confirm & Pay at Store
                    </button>
                </form>
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('storeModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- GCash Payment Modal -->
    <div class="modal-overlay" id="gcashModal">
        <div class="modal-container <?php echo $is_food_only ? 'modal-food' : 'modal-gcash'; ?>">
            <button class="modal-close" onclick="closeModal('gcashModal')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="<?php echo $is_food_only ? 'fas fa-utensils' : 'fas fa-mobile-alt'; ?>"></i>
            </div>
            <h2 class="modal-title">Pay with GCash</h2>
            <p class="modal-message">You will be redirected to PayMongo secure checkout</p>
            <div class="modal-details">
                <?php if (!$is_food_only): ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Room Total:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($room_total, 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($unpaid_food_total > 0): ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Food Total:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($unpaid_food_total, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Total Amount:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($grand_total, 2); ?></span>
                </div>
                <?php if (!empty($unpaid_food_items)): ?>
                <div class="modal-food-items">
                    <div class="modal-food-title">
                        <i class="fas fa-utensils"></i> Unpaid Food Items
                    </div>
                    <?php foreach ($unpaid_food_items as $item): ?>
                    <div class="modal-food-item">
                        <span><?php echo htmlspecialchars($item['item_name']); ?> x<?php echo $item['quantity']; ?></span>
                        <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-buttons">
                <form method="POST" id="gcashPaymentForm" style="flex: 1;">
                    <input type="hidden" name="pay_with_paymongo" value="1">
                    <input type="hidden" name="payment_method" value="gcash">
                    <button type="submit" class="modal-btn modal-btn-primary" onclick="submitPaymentForm('gcashPaymentForm')">
                        Proceed to PayMongo
                    </button>
                </form>
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('gcashModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Card Payment Modal -->
    <div class="modal-overlay" id="cardModal">
        <div class="modal-container <?php echo $is_food_only ? 'modal-food' : 'modal-card'; ?>">
            <button class="modal-close" onclick="closeModal('cardModal')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="<?php echo $is_food_only ? 'fas fa-utensils' : 'fas fa-credit-card'; ?>"></i>
            </div>
            <h2 class="modal-title">Pay with Card</h2>
            <p class="modal-message">You will be redirected to PayMongo secure checkout</p>
            <div class="modal-details">
                <?php if (!$is_food_only): ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Room Total:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($room_total, 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($unpaid_food_total > 0): ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Food Total:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($unpaid_food_total, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Total Amount:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($grand_total, 2); ?></span>
                </div>
                <?php if (!empty($unpaid_food_items)): ?>
                <div class="modal-food-items">
                    <div class="modal-food-title">
                        <i class="fas fa-utensils"></i> Unpaid Food Items
                    </div>
                    <?php foreach ($unpaid_food_items as $item): ?>
                    <div class="modal-food-item">
                        <span><?php echo htmlspecialchars($item['item_name']); ?> x<?php echo $item['quantity']; ?></span>
                        <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-buttons">
                <form method="POST" id="cardPaymentForm" style="flex: 1;">
                    <input type="hidden" name="pay_with_paymongo" value="1">
                    <input type="hidden" name="payment_method" value="card">
                    <button type="submit" class="modal-btn modal-btn-primary" onclick="submitPaymentForm('cardPaymentForm')">
                        Proceed to PayMongo
                    </button>
                </form>
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('cardModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- PayMaya Payment Modal -->
    <div class="modal-overlay" id="paymayaModal">
        <div class="modal-container <?php echo $is_food_only ? 'modal-food' : 'modal-paymaya'; ?>">
            <button class="modal-close" onclick="closeModal('paymayaModal')">
                <i class="fas fa-times"></i>
            </button>
            <div class="modal-icon">
                <i class="<?php echo $is_food_only ? 'fas fa-utensils' : 'fas fa-wallet'; ?>"></i>
            </div>
            <h2 class="modal-title">Pay with PayMaya</h2>
            <p class="modal-message">You will be redirected to PayMongo secure checkout</p>
            <div class="modal-details">
                <?php if (!$is_food_only): ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Room Total:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($room_total, 2); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($unpaid_food_total > 0): ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Food Total:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($unpaid_food_total, 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="modal-detail-item">
                    <span class="modal-detail-label">Total Amount:</span>
                    <span class="modal-detail-value">₱<?php echo number_format($grand_total, 2); ?></span>
                </div>
                <?php if (!empty($unpaid_food_items)): ?>
                <div class="modal-food-items">
                    <div class="modal-food-title">
                        <i class="fas fa-utensils"></i> Unpaid Food Items
                    </div>
                    <?php foreach ($unpaid_food_items as $item): ?>
                    <div class="modal-food-item">
                        <span><?php echo htmlspecialchars($item['item_name']); ?> x<?php echo $item['quantity']; ?></span>
                        <span>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-buttons">
                <form method="POST" id="paymayaPaymentForm" style="flex: 1;">
                    <input type="hidden" name="pay_with_paymongo" value="1">
                    <input type="hidden" name="payment_method" value="paymaya">
                    <button type="submit" class="modal-btn modal-btn-primary" onclick="submitPaymentForm('paymayaPaymentForm')">
                        Proceed to PayMongo
                    </button>
                </form>
                <button class="modal-btn modal-btn-secondary" onclick="closeModal('paymayaModal')">Cancel</button>
            </div>
        </div>
    </div>

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
                <i class="<?php echo $is_food_only ? 'fas fa-utensils' : 'fas fa-credit-card'; ?>"></i>
                <?php echo $is_food_only ? 'Food Payment' : 'Complete Payment'; ?>
            </h2>
            <p>Booking #<?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></p>
        </div>

        <!-- Payment Type Banner -->
        <div class="payment-type-banner">
            <i class="<?php echo $is_food_only ? 'fas fa-utensils' : 'fas fa-check-circle'; ?>"></i>
            <div class="banner-content">
                <h3><i class="fas fa-shield-alt"></i> <?php echo $is_food_only ? 'FOOD PAYMENT' : 'SECURE PAYMENT'; ?></h3>
                <p><?php echo $is_food_only ? 'You are paying for your unpaid food items. Previously paid items are shown below for reference.' : 'Payments are processed securely by PayMongo. We never store your card details.'; ?></p>
            </div>
        </div>

        <?php if (!$is_food_only): ?>
        <!-- Cancellation Policy Banner (only for full payment) -->
        <div class="policy-banner">
            <i class="fas fa-exclamation-triangle"></i>
            <div class="policy-content">
                <h3><i class="fas fa-gavel"></i> Cancellation Policy</h3>
                <ul>
                    <li><i class="fas fa-clock"></i> Cancel up to 24 hours before booking for FULL refund</li>
                    <li><i class="fas fa-hourglass-half"></i> Cancel within 24 hours: 20% downpayment is non-refundable</li>
                    <li><i class="fas fa-times-circle"></i> No-show: Full charge applies (₱<?php echo number_format($grand_total, 2); ?>)</li>
                </ul>
            </div>
        </div>
        <?php endif; ?>

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
            
            <!-- Unpaid Food Items Section -->
            <?php if (!empty($unpaid_food_items)): ?>
            <div class="food-items-panel">
                <div class="food-items-title">
                    <i class="fas fa-utensils"></i>
                    <span>Unpaid Food Items (<?php echo count($unpaid_food_items); ?> items)</span>
                </div>
                <?php foreach ($unpaid_food_items as $item): ?>
                <div class="food-item-row">
                    <div class="food-item-info">
                        <div class="food-item-name"><?php echo htmlspecialchars($item['item_name']); ?></div>
                        <div class="food-item-category"><?php echo htmlspecialchars($item['category']); ?></div>
                    </div>
                    <div class="food-item-quantity">x<?php echo $item['quantity']; ?></div>
                    <div class="food-item-price">₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Previously Paid Food Items (for reference) -->
            <?php if (!empty($paid_food_items)): ?>
            <div class="paid-items-panel">
                <div class="paid-items-title">
                    <i class="fas fa-check-circle"></i>
                    <span>Previously Paid Food Items (<?php echo count($paid_food_items); ?> items)</span>
                </div>
                <?php foreach ($paid_food_items as $item): ?>
                <div class="paid-item-row">
                    <div>
                        <span class="paid-item-name"><?php echo htmlspecialchars($item['item_name']); ?> x<?php echo $item['quantity']; ?></span>
                    </div>
                    <div>
                        <span class="paid-item-badge">PAID</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Payment Breakdown -->
        <div class="payment-breakdown">
            <?php if (!$is_food_only): ?>
            <div class="breakdown-item total">
                <div class="breakdown-label">Room Total</div>
                <div class="breakdown-value">₱<?php echo number_format($room_total, 2); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($unpaid_food_total > 0): ?>
            <div class="breakdown-item food">
                <div class="breakdown-label">Unpaid Food Total</div>
                <div class="breakdown-value">₱<?php echo number_format($unpaid_food_total, 2); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if (!$is_food_only): ?>
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
            <?php endif; ?>
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

                <!-- Pay at Store (show for both full and food-only) -->
                <div class="payment-card store" onclick="showModal('storeModal')">
                    <div class="payment-icon">
                        <i class="fas fa-store"></i>
                    </div>
                    <h4 class="payment-name">Pay at Store</h4>
                    <p class="payment-desc">Pay in person</p>
                    <span class="payment-badge"><?php echo $is_food_only ? 'Food Only' : '20% Downpayment'; ?></span>
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
                    <?php if ($unpaid_food_total > 0): ?>
                    <div class="instruction-item">
                        <i class="fas fa-store"></i>
                        <span><strong>Store:</strong> Pay <?php echo $is_food_only ? 'full amount' : '20% downpayment'; ?> at store</span>
                    </div>
                    <?php endif; ?>
                    <div class="instruction-item">
                        <i class="fas fa-clock"></i>
                        <span><strong>Booking #:</strong> <?php echo str_pad($booking_id, 6, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <?php if (!empty($unpaid_food_items)): ?>
                    <div class="instruction-item">
                        <i class="fas fa-utensils"></i>
                        <span><strong>Unpaid Items:</strong> <?php echo count($unpaid_food_items); ?> items</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <p>&copy; 2024 Sirene KTV. All Rights Reserved. Payments powered by PayMongo.</p>
    </footer>

    <!-- JavaScript for modals and form submission -->
    <script>
    function showModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function submitPaymentForm(formId) {
        const form = document.getElementById(formId);
        const submitBtn = form.querySelector('button[type="submit"]');
        
        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="loading-spinner"></span> Processing...';
        
        // Log for debugging
        console.log('Submitting form:', formId);
        
        // Submit the form
        form.submit();
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal-overlay').forEach(modal => {
                if (modal.style.display === 'flex') {
                    modal.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            });
        }
    });

    // Add loading animation to payment cards
    document.querySelectorAll('.payment-card').forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.98)';
            setTimeout(() => {
                this.style.transform = '';
            }, 200);
        });
    });

    // Debug - log when page loads
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Payment page loaded - Booking ID: <?php echo $booking_id; ?>, Type: <?php echo $payment_type; ?>');
        console.log('Unpaid food items: <?php echo count($unpaid_food_items); ?>');
    });
    </script>
</body>
</html>