<?php
session_start();
include "../db.php";

// Add cache control headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Helper function to get currency from country code
function getCurrencyFromCountry($country_code) {
    $currencies = [
        '+1' => ['symbol' => '$', 'code' => 'USD', 'name' => 'US Dollar'],
        '+44' => ['symbol' => '£', 'code' => 'GBP', 'name' => 'British Pound'],
        '+61' => ['symbol' => 'A$', 'code' => 'AUD', 'name' => 'Australian Dollar'],
        '+65' => ['symbol' => 'S$', 'code' => 'SGD', 'name' => 'Singapore Dollar'],
        '+60' => ['symbol' => 'RM', 'code' => 'MYR', 'name' => 'Malaysian Ringgit'],
        '+63' => ['symbol' => '₱', 'code' => 'PHP', 'name' => 'Philippine Peso'],
        '+81' => ['symbol' => '¥', 'code' => 'JPY', 'name' => 'Japanese Yen'],
        '+82' => ['symbol' => '₩', 'code' => 'KRW', 'name' => 'South Korean Won'],
        '+86' => ['symbol' => '¥', 'code' => 'CNY', 'name' => 'Chinese Yuan'],
        '+91' => ['symbol' => '₹', 'code' => 'INR', 'name' => 'Indian Rupee'],
        '+971' => ['symbol' => 'د.إ', 'code' => 'AED', 'name' => 'UAE Dirham'],
        '+33' => ['symbol' => '€', 'code' => 'EUR', 'name' => 'Euro'],
        '+49' => ['symbol' => '€', 'code' => 'EUR', 'name' => 'Euro'],
        '+34' => ['symbol' => '€', 'code' => 'EUR', 'name' => 'Euro'],
        '+39' => ['symbol' => '€', 'code' => 'EUR', 'name' => 'Euro'],
        '+55' => ['symbol' => 'R$', 'code' => 'BRL', 'name' => 'Brazilian Real'],
        '+52' => ['symbol' => 'Mex$', 'code' => 'MXN', 'name' => 'Mexican Peso']
    ];
    
    return $currencies[$country_code] ?? ['symbol' => '$', 'code' => 'USD', 'name' => 'US Dollar'];
}

$name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Get user's currency from session or database
if (isset($_SESSION['user_currency'])) {
    $currency = $_SESSION['user_currency'];
} else {
    // Fetch from database if not in session
    $user_query = $conn->query("SELECT country_code FROM user_tbl WHERE id = $user_id");
    if ($user_query && $user_query->num_rows > 0) {
        $user_data = $user_query->fetch_assoc();
        $country_code = $user_data['country_code'] ?? '+1';
        $currency = getCurrencyFromCountry($country_code);
        $_SESSION['user_currency'] = $currency;
        $_SESSION['user_country_code'] = $country_code;
    } else {
        $currency = ['symbol' => '$', 'code' => 'USD', 'name' => 'US Dollar'];
    }
}

$currency_symbol = $currency['symbol'];
$currency_code = $currency['code'];

// SAFE DATE FORMATTING FUNCTION
function formatDisplayDate($date_string) {
    if (empty($date_string)) return 'No date';
    
    try {
        $date = new DateTime($date_string);
        $today = new DateTime('today');
        $tomorrow = new DateTime('tomorrow');
        $yesterday = new DateTime('yesterday');
        
        if ($date->format('Y-m-d') == $today->format('Y-m-d')) {
            return 'Today';
        } elseif ($date->format('Y-m-d') == $tomorrow->format('Y-m-d')) {
            return 'Tomorrow';
        } elseif ($date->format('Y-m-d') == $yesterday->format('Y-m-d')) {
            return 'Yesterday';
        } else {
            return $date->format('M j, Y');
        }
    } catch (Exception $e) {
        $timestamp = strtotime($date_string);
        if ($timestamp === false) {
            return 'Invalid date';
        }
        
        $today_ts = strtotime('today');
        $tomorrow_ts = strtotime('tomorrow');
        $yesterday_ts = strtotime('yesterday');
        
        if (date('Y-m-d', $timestamp) == date('Y-m-d', $today_ts)) {
            return 'Today';
        } elseif (date('Y-m-d', $timestamp) == date('Y-m-d', $tomorrow_ts)) {
            return 'Tomorrow';
        } elseif (date('Y-m-d', $timestamp) == date('Y-m-d', $yesterday_ts)) {
            return 'Yesterday';
        } else {
            return date('M j, Y', $timestamp);
        }
    }
}

// SAFE TIME FORMATTING FUNCTION
function formatDisplayTime($time_string) {
    if (empty($time_string)) return '';
    $timestamp = strtotime($time_string);
    return ($timestamp !== false) ? date('g:i A', $timestamp) : $time_string;
}

// Initialize food cart in session if not exists
if (!isset($_SESSION['food_cart'])) {
    $_SESSION['food_cart'] = [];
}

// Check if user has active bookings
$active_bookings_count = 0;
$active_bookings = [];
$active_booking_ids = [];

$active_bookings_query = $conn->query("
    SELECT b.*, r.room_name 
    FROM booking b 
    LEFT JOIN room r ON b.r_id = r.r_id 
    WHERE b.u_id = $user_id 
    AND b.status NOT IN ('cancelled', 'completed', 'rejected') 
    AND b.booking_date >= CURDATE()
    ORDER BY b.booking_date ASC, b.start_time ASC
");

if ($active_bookings_query && $active_bookings_query->num_rows > 0) {
    $active_bookings_count = $active_bookings_query->num_rows;
    while ($booking = $active_bookings_query->fetch_assoc()) {
        $active_bookings[] = $booking;
        $active_booking_ids[] = $booking['b_id'];
    }
}

// Redirect if no active bookings
if ($active_bookings_count === 0) {
    header("Location: dashboard.php?tab=rooms&message=no_active_booking");
    exit;
}

// Fetch foods data
$foods_query = $conn->query("SELECT * FROM food_beverages ORDER BY category, item_name ASC");
$foods = [];
$foods_count = 0;
if ($foods_query) {
    $foods = $foods_query->fetch_all(MYSQLI_ASSOC);
    $foods_count = count($foods);
}

// Get categories for filtering
$categories_query = $conn->query("SELECT DISTINCT category FROM food_beverages ORDER BY category");
$categories = [];
if ($categories_query) {
    while ($cat = $categories_query->fetch_assoc()) {
        $categories[] = $cat['category'];
    }
}

// Food image mapping
$food_images = [
    'Cheese Balls' => '../images/cheese-balls.jpg',
    'Chicken Lollipop' => '../images/chicken-lollipop.jpg',
    'Chicken Wings' => '../images/chicken-wings.jpg',
    'Paneer Tikka' => '../images/paneer-tikka.jpg',
    'Spring Rolls (Veg)' => '../images/spring-rolls.jpg',
    'Chicken Biryani' => '../images/chicken-biryani.jpg',
    'Fish & Chips' => '../images/fish-chips.jpg',
    'Paneer Butter Masala' => '../images/paneer-butter-masala.jpg',
    'Veg Hakka Noodles' => '../images/veg-hakka-noodles.jpg',
    'Chicken Burger' => '../images/chicken-burger.jpg',
    'Chicken Hot Dog' => '../images/chicken-hotdog.jpg',
    'Chicken Wrap' => '../images/chicken-wrap.jpg',
    'Masala Fries' => '../images/masala-fries.jpg',
    'Nachos with Cheese' => '../images/nachos-cheese.jpg',
    'Coca-Cola (500ml)' => '../images/coca-cola.jpg',
    'Fresh Lime Soda' => '../images/fresh-lime-soda.jpg',
    'Hot Coffee' => '../images/hot-coffee.jpg',
    'Iced Tea' => '../images/iced-tea.jpg',
    'Virgin Mojito' => '../images/virgin-mojito.jpg',
    'Brandy (60ml)' => '../images/brandy.jpg',
    'Champagne (Glass)' => '../images/champagne.jpg',
    'Gin (60ml)' => '../images/gin.jpg',
    'Tequila Shot' => '../images/tequila.jpg',
    'Whisky (60ml)' => '../images/whisky.jpg',
    'Cheesecake Slice' => '../images/cheesecake.jpg',
    'Chocolate Mousse' => '../images/chocolate-mousse.jpg',
    'Fruit Salad' => '../images/fruit-salad.jpg',
    'Gulab Jamun' => '../images/gulab-jamun.jpg',
    'Ice Cream Sundae' => '../images/ice-cream-sundae.jpg'
];

$default_food_image = '../../images/food/default.jpg';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Close session write to prevent locking - but keep session readable
    session_write_close();
    
    // Add to cart
    if ($_POST['action'] === 'add_to_cart') {
        $food_id = intval($_POST['food_id']);
        $quantity = intval($_POST['quantity']);
        $booking_id = intval($_POST['booking_id']);
        
        // Re-open session for writing
        session_start();
        
        $food_query = $conn->query("SELECT * FROM food_beverages WHERE f_id = $food_id");
        if ($food_query && $food_query->num_rows > 0) {
            $food = $food_query->fetch_assoc();
            
            $found = false;
            foreach ($_SESSION['food_cart'] as &$item) {
                if ($item['food_id'] == $food_id && $item['booking_id'] == $booking_id) {
                    $item['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $_SESSION['food_cart'][] = [
                    'food_id' => $food_id,
                    'booking_id' => $booking_id,
                    'item_name' => $food['item_name'],
                    'price' => floatval($food['price']),
                    'quantity' => $quantity,
                    'category' => $food['category']
                ];
            }
            
            // Save and close session
            session_write_close();
            
            echo json_encode(['success' => true, 'cart' => $_SESSION['food_cart']]);
            exit;
        } else {
            session_write_close();
            echo json_encode(['success' => false, 'message' => 'Food item not found']);
            exit;
        }
    }
    
    // Remove from cart
    if ($_POST['action'] === 'remove_from_cart') {
        session_start();
        $index = intval($_POST['index']);
        if (isset($_SESSION['food_cart'][$index])) {
            unset($_SESSION['food_cart'][$index]);
            $_SESSION['food_cart'] = array_values($_SESSION['food_cart']);
        }
        session_write_close();
        echo json_encode(['success' => true, 'cart' => $_SESSION['food_cart']]);
        exit;
    }
    
    // Update cart
    if ($_POST['action'] === 'update_cart') {
        session_start();
        $index = intval($_POST['index']);
        $quantity = intval($_POST['quantity']);
        if (isset($_SESSION['food_cart'][$index]) && $quantity > 0) {
            $_SESSION['food_cart'][$index]['quantity'] = $quantity;
        }
        session_write_close();
        echo json_encode(['success' => true, 'cart' => $_SESSION['food_cart']]);
        exit;
    }
    
    // Get cart
    if ($_POST['action'] === 'get_cart') {
        // Just read session, no need to write
        echo json_encode(['success' => true, 'cart' => $_SESSION['food_cart']]);
        exit;
    }
    
    // Clear cart
    if ($_POST['action'] === 'clear_cart') {
        session_start();
        $booking_id = intval($_POST['booking_id']);
        $_SESSION['food_cart'] = array_filter($_SESSION['food_cart'], function($item) use ($booking_id) {
            return $item['booking_id'] != $booking_id;
        });
        $_SESSION['food_cart'] = array_values($_SESSION['food_cart']);
        session_write_close();
        echo json_encode(['success' => true, 'cart' => $_SESSION['food_cart']]);
        exit;
    }
    
    // PLACE ORDER - Save to booking_food table
    if ($_POST['action'] === 'place_order') {
        session_start();
        $booking_id = intval($_POST['booking_id']);
        $items = json_decode($_POST['items'], true);
        $total_amount = floatval($_POST['total_amount']);
        
        // Verify booking belongs to user and is active
        $check_booking = $conn->query("
            SELECT b.*, r.room_name 
            FROM booking b
            LEFT JOIN room r ON b.r_id = r.r_id
            WHERE b.b_id = $booking_id 
            AND b.u_id = $user_id 
            AND b.status NOT IN ('cancelled', 'completed', 'rejected')
        ");
        
        if ($check_booking->num_rows === 0) {
            session_write_close();
            echo json_encode(['success' => false, 'message' => 'Invalid or inactive booking']);
            exit;
        }
        
        $booking_info = $check_booking->fetch_assoc();
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            $success_count = 0;
            
            // Insert each item into booking_food table
            foreach ($items as $item) {
                $food_id = intval($item['food_id']);
                $quantity = intval($item['quantity']);
                $price = floatval($item['price']);
                
                // Insert into booking_food table
                $insert_query = "INSERT INTO booking_food (b_id, f_id, quantity, price, served) 
                                 VALUES ($booking_id, $food_id, $quantity, $price, 'pending')";
                
                if ($conn->query($insert_query)) {
                    $success_count++;
                } else {
                    throw new Exception("Failed to insert food item: " . $conn->error);
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Clear the cart for this booking from session
            $_SESSION['food_cart'] = array_filter($_SESSION['food_cart'], function($item) use ($booking_id) {
                return $item['booking_id'] != $booking_id;
            });
            $_SESSION['food_cart'] = array_values($_SESSION['food_cart']);
            
            session_write_close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Order placed successfully!',
                'items_added' => $success_count,
                'cart' => $_SESSION['food_cart'],
                'booking' => [
                    'room_name' => $booking_info['room_name'],
                    'booking_date' => formatDisplayDate($booking_info['booking_date']),
                    'start_time' => formatDisplayTime($booking_info['start_time']),
                    'end_time' => formatDisplayTime($booking_info['end_time'])
                ]
            ]);
            
        } catch (Exception $e) {
            $conn->rollback();
            session_write_close();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Food & Drinks - Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing CSS remains exactly the same */
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
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            border-bottom: 3px solid var(--highlight);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left h1 {
            font-size: 28px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 5px;
        }

        .header-left p {
            color: #aaa;
            font-size: 14px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .welcome-message {
            background: var(--accent);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .currency-badge {
            background: var(--highlight);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .back-btn, .logout-btn {
            background: linear-gradient(135deg, var(--accent), var(--highlight));
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 14px;
        }

        .back-btn:hover, .logout-btn:hover {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 69, 96, 0.4);
        }

        .main-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .active-booking-info {
            background: linear-gradient(135deg, var(--accent), #0f3460);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            border: 2px solid var(--highlight);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .booking-info-text {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .booking-info-text i {
            font-size: 30px;
            color: var(--highlight);
        }

        .booking-info-text h2 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .booking-info-text p {
            color: #aaa;
            font-size: 14px;
        }

        .booking-selector {
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 10px;
            border-left: 3px solid var(--highlight);
        }

        .booking-selector select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--light);
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 14px;
            min-width: 250px;
            cursor: pointer;
        }

        .booking-selector select option {
            background: var(--primary);
            color: var(--light);
        }

        /* Delivery Information Banner */
        .delivery-info-banner {
            background: rgba(9, 132, 227, 0.15);
            border: 1px solid var(--info);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .delivery-icon {
            width: 50px;
            height: 50px;
            background: rgba(9, 132, 227, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .delivery-icon i {
            font-size: 24px;
            color: var(--info);
        }

        .delivery-info-text {
            flex: 1;
        }

        .delivery-info-text h4 {
            font-size: 16px;
            margin-bottom: 5px;
            color: var(--info);
        }

        .delivery-info-text p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }

        .delivery-room-details {
            background: rgba(255, 255, 255, 0.05);
            padding: 10px 20px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .delivery-room-details i {
            color: var(--highlight);
        }

        .food-tablet-container {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 25px;
            min-height: 600px;
        }

        .food-browser {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .food-filters {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 10px;
            scrollbar-width: thin;
            scrollbar-color: var(--highlight) rgba(255, 255, 255, 0.1);
        }

        .filter-tabs::-webkit-scrollbar {
            height: 5px;
        }

        .filter-tabs::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .filter-tabs::-webkit-scrollbar-thumb {
            background: var(--highlight);
            border-radius: 10px;
        }

        .filter-tab {
            padding: 8px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            color: #aaa;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 14px;
        }

        .filter-tab:hover {
            background: rgba(233, 69, 96, 0.1);
            color: var(--highlight);
            border-color: var(--highlight);
        }

        .filter-tab.active {
            background: var(--highlight);
            color: white;
            border-color: var(--highlight);
        }

        .food-grid {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            gap: 12px;
            max-height: 600px;
        }

        .food-item-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            height: fit-content;
        }

        .food-item-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.3);
            border-color: var(--highlight);
        }

        .food-item-image {
            height: 120px;
            background: rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .food-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .food-item-card:hover .food-item-image img {
            transform: scale(1.1);
        }

        .food-item-category {
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(233, 69, 96, 0.9);
            color: white;
            padding: 3px 8px;
            border-radius: 15px;
            font-size: 10px;
            font-weight: 600;
        }

        .food-item-details {
            padding: 12px;
        }

        .food-item-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--light);
            margin-bottom: 5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .food-item-price {
            font-size: 16px;
            color: var(--highlight);
            font-weight: 700;
            margin-bottom: 5px;
        }

        .food-item-stock {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.6);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stock-badge {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .stock-instock {
            background: var(--success);
        }

        .stock-lowstock {
            background: var(--warning);
        }

        .stock-outofstock {
            background: var(--danger);
        }

        .food-cart {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.1);
            height: fit-content;
            position: sticky;
            top: 100px;
        }

        .cart-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .cart-header h3 {
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cart-header h3 i {
            color: var(--highlight);
        }

        .cart-items {
            max-height: 400px;
            overflow-y: auto;
            padding: 20px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            margin-bottom: 8px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--light);
            margin-bottom: 3px;
        }

        .cart-item-price {
            font-size: 12px;
            color: var(--highlight);
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quantity-btn {
            width: 24px;
            height: 24px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--light);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }

        .quantity-btn:hover:not(:disabled) {
            background: var(--highlight);
        }

        .quantity-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .cart-item-quantity span {
            min-width: 24px;
            text-align: center;
            font-size: 13px;
            font-weight: 600;
        }

        .remove-item {
            color: #aaa;
            cursor: pointer;
            transition: all 0.3s;
            padding: 5px;
            font-size: 14px;
        }

        .remove-item:hover {
            color: var(--danger);
        }

        .cart-footer {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 16px;
            font-weight: 600;
        }

        .cart-total span:last-child {
            color: var(--highlight);
            font-size: 22px;
        }

        .checkout-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 16px;
        }

        .checkout-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 69, 96, 0.4);
        }

        .checkout-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .empty-cart {
            text-align: center;
            color: #aaa;
            padding: 40px 15px;
        }

        .empty-cart i {
            font-size: 50px;
            color: rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
        }

        .empty-cart p {
            font-size: 14px;
        }

        /* Quantity Modal */
        .quantity-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .quantity-modal-content {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 30px;
            border-radius: 20px;
            max-width: 400px;
            width: 90%;
            border: 2px solid var(--highlight);
            animation: modalSlideIn 0.3s;
        }

        .quantity-modal-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .quantity-modal-header i {
            font-size: 50px;
            color: var(--highlight);
            margin-bottom: 10px;
        }

        .quantity-modal-header h3 {
            font-size: 20px;
            color: var(--light);
            margin-bottom: 5px;
        }

        .quantity-modal-header p {
            color: #aaa;
            font-size: 14px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            margin: 30px 0;
        }

        .quantity-control-btn {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: var(--light);
            font-size: 24px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-control-btn:hover {
            background: var(--highlight);
            border-color: var(--highlight);
        }

        .quantity-display {
            font-size: 40px;
            font-weight: 700;
            color: var(--highlight);
            min-width: 60px;
            text-align: center;
        }

        .quantity-modal-actions {
            display: flex;
            gap: 15px;
        }

        .quantity-modal-btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
        }

        .quantity-modal-btn.cancel {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
        }

        .quantity-modal-btn.confirm {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
        }

        .quantity-modal-btn:hover {
            transform: translateY(-2px);
        }

        /* Order Success Modal */
        .order-success-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .order-success-content {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 40px;
            border-radius: 30px;
            max-width: 500px;
            width: 90%;
            border: 3px solid var(--success);
            animation: modalSlideIn 0.3s;
            text-align: center;
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: rgba(0, 184, 148, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            border: 3px solid var(--success);
        }

        .success-icon i {
            font-size: 50px;
            color: var(--success);
        }

        .order-success-content h2 {
            font-size: 28px;
            margin-bottom: 15px;
            color: var(--success);
        }

        .order-success-content p {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 25px;
            font-size: 16px;
            line-height: 1.6;
        }

        .delivery-details-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .delivery-detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .delivery-detail-item:last-child {
            border-bottom: none;
        }

        .detail-icon {
            width: 40px;
            height: 40px;
            background: rgba(233, 69, 96, 0.1);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .detail-icon i {
            font-size: 18px;
            color: var(--highlight);
        }

        .detail-text {
            flex: 1;
            text-align: left;
        }

        .detail-label {
            font-size: 12px;
            color: #aaa;
            margin-bottom: 3px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 600;
            color: var(--light);
        }

        .order-success-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .success-btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .success-btn.primary {
            background: linear-gradient(135deg, var(--success), #00cec9);
            color: white;
        }

        .success-btn.secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .success-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 184, 148, 0.3);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .clear-cart-btn {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #aaa;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
        }

        .clear-cart-btn:hover {
            background: rgba(214, 48, 49, 0.2);
            color: var(--danger);
            border-color: var(--danger);
        }

        .booking-room-info {
            font-size: 12px;
            color: #aaa;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .booking-room-info i {
            color: var(--highlight);
        }

        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            z-index: 9999;
            animation: slideIn 0.3s;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }

        .notification.success {
            background: var(--success);
        }

        .notification.info {
            background: var(--info);
        }

        .notification.error {
            background: var(--danger);
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 1024px) {
            .food-tablet-container {
                grid-template-columns: 1fr;
            }
            
            .food-cart {
                position: static;
                margin-top: 20px;
            }
        }

        @media (max-width: 768px) {
            .food-grid {
                grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            }
            
            .active-booking-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .booking-selector {
                width: 100%;
            }
            
            .booking-selector select {
                width: 100%;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-right {
                flex-direction: column;
                width: 100%;
            }
            
            .delivery-info-banner {
                flex-direction: column;
                text-align: center;
            }
            
            .order-success-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .food-grid {
                grid-template-columns: 1fr;
            }
        }

        .retry-badge {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 8px;
        }
    </style>
</head>
<body>

<!-- Quantity Selection Modal -->
<div id="quantityModal" class="quantity-modal">
    <div class="quantity-modal-content">
        <div class="quantity-modal-header">
            <i class="fas fa-utensils"></i>
            <h3 id="modalFoodName">Add to Cart</h3>
            <p id="modalFoodPrice">Select quantity</p>
        </div>
        
        <div class="quantity-control">
            <button class="quantity-control-btn" onclick="updateModalQuantity(-1)">-</button>
            <span class="quantity-display" id="modalQuantity">1</span>
            <button class="quantity-control-btn" onclick="updateModalQuantity(1)">+</button>
        </div>
        
        <div class="quantity-modal-actions">
            <button class="quantity-modal-btn cancel" onclick="closeQuantityModal()">Cancel</button>
            <button class="quantity-modal-btn confirm" id="confirmAddBtn" onclick="debouncedAddToCart()">Add to Cart</button>
        </div>
    </div>
</div>

<!-- Order Success Modal -->
<div id="orderSuccessModal" class="order-success-modal">
    <div class="order-success-content">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2>Order Placed Successfully!</h2>
        <p>Your food and drinks have been ordered and will be prepared shortly.</p>
        
        <div class="delivery-details-card" id="deliveryDetails">
            <!-- Will be populated dynamically -->
        </div>
        
        <div class="order-success-actions">
            <a href="food-order.php" class="success-btn secondary">
                <i class="fas fa-utensils"></i> Order More
            </a>
            <a href="dashboard.php" class="success-btn primary">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<!-- Manual Refresh Notification (hidden by default) -->
<div id="refreshNotification" class="notification info" style="display: none; cursor: pointer;" onclick="location.reload()">
    <i class="fas fa-sync-alt"></i> Click to refresh and sync cart
</div>

<header>
    <div class="header-left">
        <h1><i class="fas fa-microphone-alt"></i> Sirene KTV</h1>
        <p>Order Food & Drinks</p>
    </div>
    <div class="header-right">
        <div class="welcome-message">
            <i class="fas fa-user-circle"></i>
            <?php echo htmlspecialchars($name); ?>
            <span class="currency-badge"><?php echo $currency_symbol; ?> <?php echo $currency_code; ?></span>
        </div>
        
        <a href="dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <form action="logout.php" method="post" style="display: inline;">
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
</header>

<div class="main-container">
    <!-- Active Booking Info -->
    <div class="active-booking-info">
        <div class="booking-info-text">
            <i class="fas fa-calendar-check"></i>
            <div>
                <h2>Your Active Bookings</h2>
                <p>Select a booking to order food for delivery to your room</p>
            </div>
        </div>
        
        <?php if ($active_bookings_count > 1): ?>
        <div class="booking-selector">
            <select id="bookingSelector" onchange="switchBooking(this.value)">
                <?php foreach ($active_bookings as $booking): ?>
                <option value="<?php echo $booking['b_id']; ?>" data-room="<?php echo htmlspecialchars($booking['room_name']); ?>" data-date="<?php echo $booking['booking_date']; ?>" data-start="<?php echo $booking['start_time']; ?>" data-end="<?php echo $booking['end_time']; ?>">
                    <?php echo htmlspecialchars($booking['room_name']); ?> - 
                    <?php echo formatDisplayDate($booking['booking_date']); ?> 
                    <?php echo formatDisplayTime($booking['start_time']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php else: ?>
        <div class="booking-selector">
            <div style="padding: 10px 0; font-weight: 600;">
                <i class="fas fa-door-closed" style="color: var(--highlight); margin-right: 8px;"></i>
                <?php echo htmlspecialchars($active_bookings[0]['room_name']); ?> - 
                <?php echo formatDisplayDate($active_bookings[0]['booking_date']); ?> 
                <?php echo formatDisplayTime($active_bookings[0]['start_time']); ?> - 
                <?php echo formatDisplayTime($active_bookings[0]['end_time']); ?>
            </div>
            <input type="hidden" id="bookingSelector" value="<?php echo $active_bookings[0]['b_id']; ?>">
        </div>
        <?php endif; ?>
    </div>

    <!-- Delivery Information Banner - CLEARLY SHOWS WHERE FOOD WILL BE DELIVERED -->
    <div class="delivery-info-banner" id="deliveryBanner">
        <div class="delivery-icon">
            <i class="fas fa-concierge-bell"></i>
        </div>
        <div class="delivery-info-text">
            <h4><i class="fas fa-info-circle"></i> Room Service Delivery</h4>
            <p id="deliveryMessage">Your order will be delivered directly to your room during your booking session.</p>
        </div>
        <div class="delivery-room-details" id="currentRoomDisplay">
            <?php if ($active_bookings_count > 0): ?>
            <i class="fas fa-door-closed"></i>
            <span><?php echo htmlspecialchars($active_bookings[0]['room_name']); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($foods_count > 0): ?>
    <!-- Food Ordering Interface -->
    <div class="food-tablet-container">
        <!-- Food Browser -->
        <div class="food-browser">
            <div class="food-filters">
                <div class="filter-tabs" id="filterTabs">
                    <button class="filter-tab active" onclick="filterFood('all', event)">All Items</button>
                    <?php foreach ($categories as $category): ?>
                        <button class="filter-tab" onclick="filterFood('<?php echo $category; ?>', event)"><?php echo $category; ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="food-grid" id="foodGrid">
                <?php foreach ($foods as $food): 
                    $stock_status = 'instock';
                    $stock_level = isset($food['stock']) ? intval($food['stock']) : 999;
                    if ($stock_level <= 5 && $stock_level > 0) {
                        $stock_status = 'lowstock';
                    } elseif ($stock_level == 0) {
                        $stock_status = 'outofstock';
                    }
                    
                    $food_image = isset($food_images[$food['item_name']]) ? $food_images[$food['item_name']] : $default_food_image;
                ?>
                    <div class="food-item-card" data-category="<?php echo htmlspecialchars($food['category']); ?>" data-food-id="<?php echo $food['f_id']; ?>" onclick="showQuantityModal(<?php echo $food['f_id']; ?>, '<?php echo htmlspecialchars(addslashes($food['item_name'])); ?>', <?php echo floatval($food['price']); ?>, <?php echo $stock_level; ?>)">
                        <div class="food-item-image">
                            <?php 
                            $image_path = dirname(__FILE__) . '/' . $food_image;
                            if (file_exists($image_path)): 
                            ?>
                                <img src="<?php echo $food_image; ?>" alt="<?php echo htmlspecialchars($food['item_name']); ?>" loading="lazy" onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\"fas fa-image\" style=\"font-size: 30px; color: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; height: 100%;\"></i>';">
                            <?php else: ?>
                                <i class="fas fa-image" style="font-size: 30px; color: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; height: 100%;"></i>
                            <?php endif; ?>
                            <div class="food-item-category"><?php echo htmlspecialchars($food['category']); ?></div>
                        </div>
                        <div class="food-item-details">
                            <div class="food-item-name"><?php echo htmlspecialchars($food['item_name']); ?></div>
                            <div class="food-item-price"><?php echo $currency_symbol; ?><?php echo number_format($food['price'], 2); ?></div>
                            <div class="food-item-stock">
                                <span class="stock-badge stock-<?php echo $stock_status; ?>"></span>
                                <?php 
                                if ($stock_level > 10) echo 'In Stock';
                                elseif ($stock_level > 0) echo 'Only ' . $stock_level . ' left';
                                else echo 'Out of Stock';
                                ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Food Cart -->
        <div class="food-cart">
            <div class="cart-header">
                <h3><i class="fas fa-shopping-cart"></i> Your Order</h3>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="background: var(--highlight); padding: 4px 8px; border-radius: 12px; font-size: 11px;" id="cartItemCount">0</span>
                    <button class="clear-cart-btn" onclick="clearCart()" id="clearCartBtn" style="display: none;">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                </div>
            </div>
            
            <div class="cart-items" id="cartItems">
                <!-- Cart items will be loaded here dynamically -->
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <p>Your cart is empty</p>
                    <p style="font-size: 13px;">Click on any food item to add to your order</p>
                </div>
            </div>
            
            <div class="cart-footer">
                <!-- Delivery destination reminder -->
                <div style="background: rgba(9, 132, 227, 0.1); border-radius: 8px; padding: 10px; margin-bottom: 15px; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-map-pin" style="color: var(--info);"></i>
                    <span>Delivering to: <strong id="cartDeliveryRoom"><?php echo htmlspecialchars($active_bookings[0]['room_name']); ?></strong></span>
                </div>
                
                <div class="cart-total">
                    <span>Total:</span>
                    <span id="cartTotal"><?php echo $currency_symbol; ?>0.00</span>
                </div>
                <button class="checkout-btn" id="checkoutBtn" onclick="placeOrder()" disabled>
                    <i class="fas fa-check-circle"></i> Place Order
                </button>
                <p style="color: rgba(255,255,255,0.5); font-size: 12px; margin-top: 10px; text-align: center;">
                    <i class="fas fa-clock"></i> Items will be delivered during your booking
                </p>
                <div id="syncStatus" style="display: none; margin-top: 8px; font-size: 11px; color: #ffc107; text-align: center;">
                    <i class="fas fa-sync-alt fa-spin"></i> Syncing...
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="no-items" style="text-align: center; padding: 100px 20px; background: rgba(255,255,255,0.03); border-radius: 20px;">
        <i class="fas fa-utensils" style="font-size: 80px; color: rgba(255,255,255,0.1); margin-bottom: 20px;"></i>
        <h2 style="margin-bottom: 15px;">No Food Items Available</h2>
        <p style="color: #aaa; margin-bottom: 30px;">Food menu is currently being updated. Please check back later.</p>
        <a href="dashboard.php" class="back-btn" style="display: inline-block; text-decoration: none; padding: 12px 30px;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
    <?php endif; ?>
</div>

<script>
    // Global variables
    let currentFoodId = null;
    let currentFoodName = '';
    let currentFoodPrice = 0;
    let currentFoodStock = 0;
    let selectedQuantity = 1;
    let currentBookingId = <?php echo $active_bookings[0]['b_id'] ?? 0; ?>;
    let activeBookingIds = <?php echo json_encode($active_booking_ids); ?>;
    let cart = <?php echo json_encode($_SESSION['food_cart']); ?>;
    let currencySymbol = '<?php echo $currency_symbol; ?>';
    
    // NEW: State management variables
    let pendingRequests = new Map();
    let lastAddTime = 0;
    const MIN_REQUEST_INTERVAL = 800; // milliseconds
    let failedRequests = [];
    let autoRetryTimer = null;
    
    // Booking details for display
    let bookingsData = <?php echo json_encode($active_bookings); ?>;
    
    console.log('Food order page loaded');
    console.log('Active bookings:', activeBookingIds);
    console.log('Current cart:', cart);
    
    // NEW: Debounce function to prevent multiple rapid clicks
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // NEW: Create debounced version
    const debouncedAddToCart = debounce(confirmAddToCart, 400);
    
    // NEW: Retry function with exponential backoff
    async function fetchWithRetry(url, options, maxRetries = 2) {
        for (let i = 0; i < maxRetries; i++) {
            try {
                const response = await fetch(url, options);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return await response.json();
            } catch (error) {
                console.log(`Attempt ${i + 1} failed:`, error);
                if (i === maxRetries - 1) throw error;
                // Exponential backoff: wait longer between retries
                await new Promise(resolve => setTimeout(resolve, Math.pow(2, i) * 300));
            }
        }
    }
    
    // NEW: Sync cart with server
    function syncCartWithServer() {
        const syncStatus = document.getElementById('syncStatus');
        if (syncStatus) syncStatus.style.display = 'block';
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_cart'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const oldCartLength = cart.length;
                cart = data.cart;
                updateCartDisplay();
                
                if (syncStatus) {
                    syncStatus.style.display = 'none';
                    if (oldCartLength !== cart.length) {
                        showNotification('Cart synced with server', 'info');
                    }
                }
            }
        })
        .catch(error => {
            console.error('Sync error:', error);
            if (syncStatus) {
                syncStatus.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Sync failed - <a href="#" onclick="location.reload()">refresh</a>';
            }
        });
    }
    
    // Switch active booking
    function switchBooking(bookingId) {
        currentBookingId = parseInt(bookingId);
        console.log('Switched to booking ID:', currentBookingId);
        
        // Update delivery information display
        const selectedOption = document.querySelector(`#bookingSelector option[value="${bookingId}"]`);
        if (selectedOption) {
            const roomName = selectedOption.getAttribute('data-room');
            document.getElementById('currentRoomDisplay').innerHTML = `<i class="fas fa-door-closed"></i><span>${roomName}</span>`;
            document.getElementById('cartDeliveryRoom').textContent = roomName;
            
            // Update delivery message
            const date = selectedOption.getAttribute('data-date');
            const start = selectedOption.getAttribute('data-start');
            const end = selectedOption.getAttribute('data-end');
            
            if (date && start && end) {
                const formattedDate = formatDate(date);
                const formattedStart = formatTime(start);
                const formattedEnd = formatTime(end);
                document.getElementById('deliveryMessage').innerHTML = 
                    `Your order will be delivered to <strong>${roomName}</strong> during your booking on ${formattedDate} from ${formattedStart} to ${formattedEnd}.`;
            }
        }
        
        updateCartDisplay();
        // Sync cart when switching bookings
        syncCartWithServer();
    }
    
    // Helper function to format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        if (date.toDateString() === today.toDateString()) {
            return 'Today';
        } else if (date.toDateString() === tomorrow.toDateString()) {
            return 'Tomorrow';
        } else {
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }
    }
    
    // Helper function to format time
    function formatTime(timeString) {
        const [hours, minutes] = timeString.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    }
    
    // Filter food items
    function filterFood(category, event) {
        if (event) {
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            event.target.classList.add('active');
        }
        
        const items = document.querySelectorAll('.food-item-card');
        items.forEach(item => {
            if (category === 'all' || item.dataset.category === category) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
    
    // Show quantity modal
    function showQuantityModal(foodId, foodName, price, stock) {
        if (stock === 0) {
            showNotification('This item is out of stock.', 'error');
            return;
        }
        
        currentFoodId = foodId;
        currentFoodName = foodName;
        currentFoodPrice = price;
        currentFoodStock = stock;
        selectedQuantity = 1;
        
        document.getElementById('modalFoodName').textContent = foodName;
        document.getElementById('modalFoodPrice').textContent = currencySymbol + price.toFixed(2) + ' each' + (stock < 10 ? ' (Only ' + stock + ' left)' : '');
        document.getElementById('modalQuantity').textContent = '1';
        
        const modal = document.getElementById('quantityModal');
        modal.style.display = 'flex';
    }
    
    // Update quantity in modal
    function updateModalQuantity(change) {
        let newQuantity = selectedQuantity + change;
        if (newQuantity >= 1 && newQuantity <= (currentFoodStock || 999)) {
            selectedQuantity = newQuantity;
            document.getElementById('modalQuantity').textContent = selectedQuantity;
        }
    }
    
    // Close quantity modal
    function closeQuantityModal() {
        document.getElementById('quantityModal').style.display = 'none';
    }
    
    // FIXED: Confirm add to cart with better error handling
    function confirmAddToCart() {
        // Prevent rapid duplicate requests
        const now = Date.now();
        if (now - lastAddTime < MIN_REQUEST_INTERVAL) {
            showNotification('Please wait...', 'info');
            return;
        }
        lastAddTime = now;
        
        let bookingId = currentBookingId;
        
        console.log('Adding to cart:', {foodId: currentFoodId, quantity: selectedQuantity, bookingId: bookingId});
        
        // Check for duplicate pending request
        const requestKey = `${currentFoodId}_${bookingId}`;
        if (pendingRequests.has(requestKey)) {
            showNotification('Already adding this item...', 'info');
            return;
        }
        pendingRequests.set(requestKey, true);
        
        // Show loading state
        const confirmBtn = document.querySelector('.quantity-modal-btn.confirm');
        const originalBtnText = confirmBtn.innerHTML;
        confirmBtn.innerHTML = '<span class="loading-spinner"></span> Adding...';
        confirmBtn.disabled = true;
        
        // Add to cart via AJAX with retry capability
        fetchWithRetry(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=add_to_cart&food_id=' + currentFoodId + '&quantity=' + selectedQuantity + '&booking_id=' + bookingId
        })
        .then(data => {
            console.log('Add to cart response:', data);
            if (data.success) {
                cart = data.cart;
                console.log('Cart after add:', cart);
                updateCartDisplay();
                closeQuantityModal();
                showNotification('✓ Item added to cart!', 'success');
            } else {
                showNotification('❌ Error: ' + (data.message || 'Could not add item'), 'error');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            
            // Show a "processing" notification instead of error
            showNotification('⏳ Processing... Check cart in a moment', 'info');
            
            // Show refresh suggestion
            const refreshNote = document.getElementById('refreshNotification');
            if (refreshNote) {
                refreshNote.style.display = 'block';
                setTimeout(() => {
                    refreshNote.style.display = 'none';
                }, 5000);
            }
            
            // Try to verify if item was actually added
            setTimeout(() => {
                verifyCartItem(currentFoodId, bookingId);
            }, 1500);
        })
        .finally(() => {
            pendingRequests.delete(requestKey);
            confirmBtn.innerHTML = originalBtnText;
            confirmBtn.disabled = false;
        });
    }
    
    // NEW: Verify if item was actually added to cart
    function verifyCartItem(foodId, bookingId) {
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=get_cart'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const updatedCart = data.cart;
                const itemExists = updatedCart.some(item => 
                    item.food_id == foodId && item.booking_id == bookingId
                );
                
                if (itemExists) {
                    cart = updatedCart;
                    updateCartDisplay();
                    showNotification('✓ Item was added successfully!', 'success');
                    
                    // Hide refresh notification if visible
                    const refreshNote = document.getElementById('refreshNotification');
                    if (refreshNote) refreshNote.style.display = 'none';
                } else {
                    // Item still not found after verification
                    showNotification('⚠️ Please refresh to see cart', 'info');
                }
            }
        })
        .catch(error => {
            console.error('Error verifying cart:', error);
        });
    }
    
    // Update cart item quantity
    function updateCartItem(index, newQuantity) {
        if (newQuantity < 1) return;
        
        console.log('Updating cart item:', {index, newQuantity});
        
        fetchWithRetry(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=update_cart&index=' + index + '&quantity=' + newQuantity
        })
        .then(data => {
            console.log('Update cart response:', data);
            if (data.success) {
                cart = data.cart;
                console.log('Cart after update:', cart);
                updateCartDisplay();
            }
        })
        .catch(error => {
            console.error('Error updating cart:', error);
            showNotification('Update failed. Please refresh.', 'error');
        });
    }
    
    // Remove from cart
    function removeFromCart(index) {
        if (confirm('Remove this item from your order?')) {
            console.log('Removing cart item:', index);
            
            fetchWithRetry(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=remove_from_cart&index=' + index
            })
            .then(data => {
                console.log('Remove cart response:', data);
                if (data.success) {
                    cart = data.cart;
                    console.log('Cart after remove:', cart);
                    updateCartDisplay();
                }
            })
            .catch(error => {
                console.error('Error removing item:', error);
                showNotification('Remove failed. Please refresh.', 'error');
            });
        }
    }
    
    // Clear cart for current booking
    function clearCart() {
        if (confirm('Clear all items from your cart?')) {
            fetchWithRetry(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=clear_cart&booking_id=' + currentBookingId
            })
            .then(data => {
                console.log('Clear cart response:', data);
                if (data.success) {
                    cart = data.cart;
                    console.log('Cart after clear:', cart);
                    updateCartDisplay();
                    showNotification('Cart cleared', 'info');
                }
            })
            .catch(error => {
                console.error('Error clearing cart:', error);
                showNotification('Clear failed. Please refresh.', 'error');
            });
        }
    }
    
    // Update cart display
    function updateCartDisplay() {
        const cartItems = document.getElementById('cartItems');
        const cartItemCount = document.getElementById('cartItemCount');
        const cartTotal = document.getElementById('cartTotal');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const clearCartBtn = document.getElementById('clearCartBtn');
        
        if (!cartItems) return;
        
        console.log('Updating cart display for booking:', currentBookingId);
        console.log('Full cart:', cart);
        
        // Filter cart items for current booking
        const bookingCartItems = cart.filter(item => item.booking_id == currentBookingId);
        
        console.log('Filtered cart items:', bookingCartItems);
        
        if (bookingCartItems.length === 0) {
            cartItems.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <p>Your cart is empty</p>
                    <p style="font-size: 13px;">Click on any food item to add to your order</p>
                </div>
            `;
            if (cartItemCount) cartItemCount.textContent = '0';
            if (cartTotal) cartTotal.textContent = currencySymbol + '0.00';
            if (checkoutBtn) checkoutBtn.disabled = true;
            if (clearCartBtn) clearCartBtn.style.display = 'none';
            return;
        }
        
        if (clearCartBtn) clearCartBtn.style.display = 'inline-block';
        
        let html = '';
        let total = 0;
        
        bookingCartItems.forEach((item, idx) => {
            // Find the actual index in the original cart array
            const originalIndex = cart.findIndex(cartItem => 
                cartItem.food_id == item.food_id && 
                cartItem.booking_id == item.booking_id
            );
            
            if (originalIndex === -1) return;
            
            const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
            total += itemTotal;
            
            html += `
                <div class="cart-item" data-index="${originalIndex}">
                    <div class="cart-item-details">
                        <div class="cart-item-name">${item.item_name}</div>
                        <div class="cart-item-price">${currencySymbol}${parseFloat(item.price).toFixed(2)}</div>
                        <div style="font-size: 11px; color: #aaa;">Subtotal: ${currencySymbol}${itemTotal.toFixed(2)}</div>
                    </div>
                    <div class="cart-item-quantity">
                        <button class="quantity-btn" onclick="updateCartItem(${originalIndex}, ${parseInt(item.quantity) - 1})" ${item.quantity <= 1 ? 'disabled' : ''}>-</button>
                        <span>${item.quantity}</span>
                        <button class="quantity-btn" onclick="updateCartItem(${originalIndex}, ${parseInt(item.quantity) + 1})">+</button>
                    </div>
                    <div class="remove-item" onclick="removeFromCart(${originalIndex})">
                        <i class="fas fa-trash"></i>
                    </div>
                </div>
            `;
        });
        
        cartItems.innerHTML = html;
        
        if (cartItemCount) cartItemCount.textContent = bookingCartItems.length;
        if (cartTotal) cartTotal.textContent = currencySymbol + total.toFixed(2);
        if (checkoutBtn) checkoutBtn.disabled = false;
    }
    
    // Place order
    function placeOrder() {
        const bookingCartItems = cart.filter(item => item.booking_id == currentBookingId);
        
        if (bookingCartItems.length === 0) {
            showNotification('Your cart is empty', 'error');
            return;
        }
        
        // Calculate total
        let total = 0;
        bookingCartItems.forEach(item => {
            total += parseFloat(item.price) * parseInt(item.quantity);
        });
        
        // Get current booking details
        const currentBooking = bookingsData.find(b => b.b_id == currentBookingId);
        const roomName = currentBooking ? currentBooking.room_name : 'your room';
        
        // Show confirmation with order details using a modal instead of confirm popup
        let orderSummary = `
            <div style="text-align: left; margin: 20px 0;">
                <h4 style="margin-bottom: 15px; color: var(--light);">Order Summary</h4>
        `;
        
        bookingCartItems.forEach(item => {
            const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
            orderSummary += `
                <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding: 5px 0; border-bottom: 1px solid rgba(255,255,255,0.1);">
                    <span>${item.item_name} x${item.quantity}</span>
                    <span style="color: var(--highlight);">${currencySymbol}${itemTotal.toFixed(2)}</span>
                </div>
            `;
        });
        
        orderSummary += `
                <div style="margin-top: 15px; padding-top: 10px; border-top: 2px solid var(--highlight); display: flex; justify-content: space-between; font-weight: 700; font-size: 18px;">
                    <span>Total:</span>
                    <span style="color: var(--highlight);">${currencySymbol}${total.toFixed(2)}</span>
                </div>
            </div>
        `;
        
        // Create custom confirmation modal
        const confirmModal = document.createElement('div');
        confirmModal.className = 'quantity-modal'; // Reuse same style
        confirmModal.style.display = 'flex';
        confirmModal.innerHTML = `
            <div class="quantity-modal-content" style="max-width: 450px;">
                <div class="quantity-modal-header">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Confirm Your Order</h3>
                    <p>Your order will be delivered to <strong>${roomName}</strong></p>
                </div>
                
                ${orderSummary}
                
                <div style="background: rgba(9, 132, 227, 0.1); border-radius: 8px; padding: 12px; margin: 15px 0; font-size: 13px;">
                    <i class="fas fa-clock" style="color: var(--info); margin-right: 8px;"></i>
                    Items will be delivered during your booking session
                </div>
                
                <div class="quantity-modal-actions">
                    <button class="quantity-modal-btn cancel" onclick="this.closest('.quantity-modal').remove()">Cancel</button>
                    <button class="quantity-modal-btn confirm" id="confirmOrderBtn">Place Order</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(confirmModal);
        
        // Handle confirm order
        document.getElementById('confirmOrderBtn').onclick = function() {
            confirmModal.remove();
            
            // Show loading state
            const checkoutBtn = document.getElementById('checkoutBtn');
            const originalText = checkoutBtn.innerHTML;
            checkoutBtn.innerHTML = '<span class="loading-spinner"></span> Placing Order...';
            checkoutBtn.disabled = true;
            
            console.log('Placing order:', {bookingId: currentBookingId, items: bookingCartItems, total});
            
            // Send order to server with retry
            fetchWithRetry(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=place_order&booking_id=' + currentBookingId + '&items=' + encodeURIComponent(JSON.stringify(bookingCartItems)) + '&total_amount=' + total
            })
            .then(data => {
                console.log('Place order response:', data);
                
                if (data.success) {
                    // Update cart with returned data
                    if (data.cart) {
                        cart = data.cart;
                    }
                    
                    // Update display
                    updateCartDisplay();
                    
                    // Show success modal with delivery details
                    showOrderSuccessModal(data);
                    
                } else {
                    showNotification('❌ Error placing order: ' + data.message, 'error');
                    checkoutBtn.innerHTML = originalText;
                    checkoutBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error placing order:', error);
                showNotification('❌ Error placing order. Please try again.', 'error');
                checkoutBtn.innerHTML = originalText;
                checkoutBtn.disabled = false;
            });
        };
    }
    
    // Show order success modal with delivery information
    function showOrderSuccessModal(orderData) {
        const modal = document.getElementById('orderSuccessModal');
        const deliveryDetails = document.getElementById('deliveryDetails');
        
        // Get current booking details
        const currentBooking = bookingsData.find(b => b.b_id == currentBookingId);
        
        if (currentBooking) {
            const formattedDate = formatDate(currentBooking.booking_date);
            const formattedStart = formatTime(currentBooking.start_time);
            const formattedEnd = formatTime(currentBooking.end_time);
            
            deliveryDetails.innerHTML = `
                <div class="delivery-detail-item">
                    <div class="detail-icon"><i class="fas fa-door-closed"></i></div>
                    <div class="detail-text">
                        <div class="detail-label">Delivery Location</div>
                        <div class="detail-value">${currentBooking.room_name}</div>
                    </div>
                </div>
                <div class="delivery-detail-item">
                    <div class="detail-icon"><i class="fas fa-calendar-alt"></i></div>
                    <div class="detail-text">
                        <div class="detail-label">Delivery Date</div>
                        <div class="detail-value">${formattedDate}</div>
                    </div>
                </div>
                <div class="delivery-detail-item">
                    <div class="detail-icon"><i class="fas fa-clock"></i></div>
                    <div class="detail-text">
                        <div class="detail-label">Delivery Time</div>
                        <div class="detail-value">${formattedStart} - ${formattedEnd}</div>
                    </div>
                </div>
                <div class="delivery-detail-item">
                    <div class="detail-icon"><i class="fas fa-utensils"></i></div>
                    <div class="detail-text">
                        <div class="detail-label">Items Ordered</div>
                        <div class="detail-value">${orderData.items_added || 0} item(s)</div>
                    </div>
                </div>
            `;
        }
        
        modal.style.display = 'flex';
        
        // Close modal when clicking outside
        modal.onclick = function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        };
    }
    
    // Show notification
    function showNotification(message, type) {
        const existingNotification = document.querySelector('.notification:not(#refreshNotification)');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        const notification = document.createElement('div');
        notification.className = 'notification ' + type;
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle'}"></i>
            ${message}
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.style.animation = 'slideOut 0.3s';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, 3000);
    }
    
    // NEW: Periodic sync with server (every 30 seconds)
    function startPeriodicSync() {
        setInterval(() => {
            console.log('Performing periodic cart sync...');
            syncCartWithServer();
        }, 30000); // Sync every 30 seconds
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Food order page loaded, initializing...');
        
        // Set initial delivery information
        if (bookingsData && bookingsData.length > 0) {
            const firstBooking = bookingsData[0];
            const formattedDate = formatDate(firstBooking.booking_date);
            const formattedStart = formatTime(firstBooking.start_time);
            const formattedEnd = formatTime(firstBooking.end_time);
            
            document.getElementById('deliveryMessage').innerHTML = 
                `Your order will be delivered to <strong>${firstBooking.room_name}</strong> during your booking on ${formattedDate} from ${formattedStart} to ${formattedEnd}.`;
        }
        
        updateCartDisplay();
        
        // Modal click outside to close
        const modal = document.getElementById('quantityModal');
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeQuantityModal();
            }
        });
        
        // Escape key to close modals
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeQuantityModal();
                
                // Close success modal if open
                const successModal = document.getElementById('orderSuccessModal');
                if (successModal.style.display === 'flex') {
                    successModal.style.display = 'none';
                }
            }
        });
        
        // Animate food items on load
        const items = document.querySelectorAll('.food-item-card');
        items.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateY(20px)';
            setTimeout(() => {
                item.style.transition = 'opacity 0.5s, transform 0.5s';
                item.style.opacity = '1';
                item.style.transform = 'translateY(0)';
            }, index * 30);
        });
        
        // Start periodic sync
        startPeriodicSync();
        
        // Check for any pending items on page load (in case of previous failed adds)
        setTimeout(() => {
            syncCartWithServer();
        }, 1000);
    });
</script>

</body>
</html>