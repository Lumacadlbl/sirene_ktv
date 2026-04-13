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

// NO LOGIN REQUIRED - Public access tablet interface
// Just track session for cart, no user authentication needed

// Helper function to get currency from country code (default to USD for public)
function getCurrencyFromCountry($country_code = '+1') {
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

// Default currency for public tablet
$currency = ['symbol' => '$', 'code' => 'USD', 'name' => 'US Dollar'];
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
if (!isset($_SESSION['tablet_cart'])) {
    $_SESSION['tablet_cart'] = [];
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
        $table_id = isset($_POST['table_id']) ? intval($_POST['table_id']) : 1;
        
        // Re-open session for writing
        session_start();
        
        $food_query = $conn->query("SELECT * FROM food_beverages WHERE f_id = $food_id");
        if ($food_query && $food_query->num_rows > 0) {
            $food = $food_query->fetch_assoc();
            
            $found = false;
            foreach ($_SESSION['tablet_cart'] as &$item) {
                if ($item['food_id'] == $food_id && $item['table_id'] == $table_id) {
                    $item['quantity'] += $quantity;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $_SESSION['tablet_cart'][] = [
                    'food_id' => $food_id,
                    'table_id' => $table_id,
                    'item_name' => $food['item_name'],
                    'price' => floatval($food['price']),
                    'quantity' => $quantity,
                    'category' => $food['category']
                ];
            }
            
            // Save and close session
            session_write_close();
            
            echo json_encode(['success' => true, 'cart' => $_SESSION['tablet_cart']]);
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
        if (isset($_SESSION['tablet_cart'][$index])) {
            unset($_SESSION['tablet_cart'][$index]);
            $_SESSION['tablet_cart'] = array_values($_SESSION['tablet_cart']);
        }
        session_write_close();
        echo json_encode(['success' => true, 'cart' => $_SESSION['tablet_cart']]);
        exit;
    }
    
    // Update cart
    if ($_POST['action'] === 'update_cart') {
        session_start();
        $index = intval($_POST['index']);
        $quantity = intval($_POST['quantity']);
        if (isset($_SESSION['tablet_cart'][$index]) && $quantity > 0) {
            $_SESSION['tablet_cart'][$index]['quantity'] = $quantity;
        }
        session_write_close();
        echo json_encode(['success' => true, 'cart' => $_SESSION['tablet_cart']]);
        exit;
    }
    
    // Get cart
    if ($_POST['action'] === 'get_cart') {
        // Just read session, no need to write
        echo json_encode(['success' => true, 'cart' => $_SESSION['tablet_cart']]);
        exit;
    }
    
    // Clear cart
    if ($_POST['action'] === 'clear_cart') {
        session_start();
        $_SESSION['tablet_cart'] = [];
        session_write_close();
        echo json_encode(['success' => true, 'cart' => []]);
        exit;
    }
    
    // PLACE ORDER - Save to booking_food table with table number in table_num
    if ($_POST['action'] === 'place_order') {
        // Re-open session
        session_start();
        
        $table_id = intval($_POST['table_id']);
        $items = json_decode($_POST['items'], true);
        $total_amount = floatval($_POST['total_amount']);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            $success_count = 0;
            $order_ids = [];
            
            // Insert each item into booking_food table with table number as table_num
            foreach ($items as $item) {
                $food_id = intval($item['food_id']);
                $quantity = intval($item['quantity']);
                $price = floatval($item['price']);
                
                // Insert into booking_food table with table number as table_num (NOT b_id)
                $insert_query = "INSERT INTO booking_food (table_num, f_id, quantity, price, served, manual_timer_minutes, order_time, is_preorder, food_payment_status) 
                                 VALUES ($table_id, $food_id, $quantity, $price, 'pending', 15, NOW(), 0, 'pending')";
                
                if ($conn->query($insert_query)) {
                    $success_count++;
                    $order_ids[] = $conn->insert_id;
                } else {
                    throw new Exception("Failed to insert food item: " . $conn->error);
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // Clear the cart for this table from session
            $_SESSION['tablet_cart'] = array_filter($_SESSION['tablet_cart'], function($item) use ($table_id) {
                return $item['table_id'] != $table_id;
            });
            $_SESSION['tablet_cart'] = array_values($_SESSION['tablet_cart']);
            
            session_write_close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Order placed successfully!',
                'items_added' => $success_count,
                'order_ids' => $order_ids,
                'cart' => $_SESSION['tablet_cart'],
                'table_id' => $table_id
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Tablet Ordering - Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Tablet-optimized variables */
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
            
            /* Tablet-specific sizing */
            --header-height: 80px;
            --food-card-size: 180px;
            --touch-target-size: 48px;
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
            padding: env(safe-area-inset-top) env(safe-area-inset-right) env(safe-area-inset-bottom) env(safe-area-inset-left);
        }

        /* Larger touch targets for tablet */
        button, 
        .filter-tab, 
        .food-item-card,
        .quantity-btn,
        .remove-item,
        .checkout-btn,
        .back-to-home-btn {
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }

        header {
            background: rgba(10, 10, 20, 0.98);
            padding: 15px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            border-bottom: 3px solid var(--highlight);
            position: sticky;
            top: 0;
            z-index: 100;
            height: var(--header-height);
        }

        .header-left h1 {
            font-size: 32px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 5px;
        }

        .header-left p {
            color: #aaa;
            font-size: 16px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .tablet-badge {
            background: var(--accent);
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tablet-badge i {
            color: var(--highlight);
            font-size: 20px;
        }

        .currency-badge {
            background: var(--highlight);
            color: white;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 600;
        }

        /* Back button styling */
        .back-to-home-btn {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
            border: 2px solid rgba(255, 255, 255, 0.2);
            padding: 12px 25px;
            border-radius: 40px;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .back-to-home-btn:hover {
            background: var(--highlight);
            border-color: var(--highlight);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 69, 96, 0.3);
        }

        .back-to-home-btn:active {
            transform: translateY(0);
        }

        .back-to-home-btn i {
            font-size: 18px;
        }

        .main-container {
            max-width: 1280px;
            margin: 20px auto;
            padding: 0 20px;
        }

        /* Welcome banner for tablet */
        .welcome-banner {
            background: linear-gradient(135deg, var(--accent), #0f3460);
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 25px;
            border: 2px solid var(--highlight);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .welcome-text {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .welcome-text i {
            font-size: 48px;
            color: var(--highlight);
        }

        .welcome-text h2 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .welcome-text p {
            color: #aaa;
            font-size: 16px;
        }

        .table-selector {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px 25px;
            border-radius: 15px;
            border-left: 3px solid var(--highlight);
        }

        .table-selector select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--light);
            padding: 15px 25px;
            border-radius: 12px;
            font-size: 18px;
            min-width: 250px;
            cursor: pointer;
        }

        .table-selector select option {
            background: var(--primary);
            color: var(--light);
        }

        /* Delivery Info Banner */
        .delivery-info-banner {
            background: rgba(9, 132, 227, 0.15);
            border: 2px solid var(--info);
            border-radius: 20px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .delivery-icon {
            width: 70px;
            height: 70px;
            background: rgba(9, 132, 227, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .delivery-icon i {
            font-size: 32px;
            color: var(--info);
        }

        .delivery-info-text {
            flex: 1;
        }

        .delivery-info-text h4 {
            font-size: 20px;
            margin-bottom: 8px;
            color: var(--info);
        }

        .delivery-info-text p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
        }

        .table-details {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px 25px;
            border-radius: 40px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            font-size: 18px;
        }

        .table-details i {
            color: var(--highlight);
            font-size: 22px;
        }

        /* Main ordering interface - Tablet optimized */
        .ordering-interface {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 25px;
        }

        /* Food browser section */
        .food-browser {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 25px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            height: calc(100vh - 280px);
            min-height: 600px;
        }

        .food-filters {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .filter-tabs {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 10px;
            scrollbar-width: thin;
            scrollbar-color: var(--highlight) rgba(255, 255, 255, 0.1);
        }

        .filter-tabs::-webkit-scrollbar {
            height: 6px;
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
            padding: 12px 28px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 40px;
            color: #aaa;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
            font-size: 16px;
            font-weight: 500;
            min-height: 48px;
        }

        .filter-tab:hover {
            background: rgba(233, 69, 96, 0.15);
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
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
        }

        .food-item-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            overflow: hidden;
            transition: all 0.3s;
            border: 2px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            height: fit-content;
        }

        .food-item-card:active {
            transform: scale(0.98);
            background: rgba(233, 69, 96, 0.1);
            border-color: var(--highlight);
        }

        .food-item-image {
            height: 160px;
            background: rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .food-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .food-item-category {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(233, 69, 96, 0.95);
            color: white;
            padding: 6px 14px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
        }

        .food-item-details {
            padding: 16px;
        }

        .food-item-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--light);
            margin-bottom: 8px;
        }

        .food-item-price {
            font-size: 22px;
            color: var(--highlight);
            font-weight: 700;
            margin-bottom: 8px;
        }

        .food-item-stock {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.7);
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .stock-badge {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .stock-instock { background: var(--success); }
        .stock-lowstock { background: var(--warning); }
        .stock-outofstock { background: var(--danger); }

        /* Cart section - Tablet optimized */
        .cart-section {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 25px;
            border: 2px solid rgba(255, 255, 255, 0.1);
            display: flex;
            flex-direction: column;
            height: calc(100vh - 280px);
            min-height: 600px;
        }

        .cart-header {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .cart-header h3 {
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .cart-header h3 i {
            color: var(--highlight);
        }

        .cart-items {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .cart-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 16px;
            margin-bottom: 10px;
            border: 2px solid rgba(255, 255, 255, 0.05);
        }

        .cart-item-details {
            flex: 1;
        }

        .cart-item-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--light);
            margin-bottom: 5px;
        }

        .cart-item-price {
            font-size: 15px;
            color: var(--highlight);
        }

        .cart-item-quantity {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .quantity-btn {
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.1);
            color: var(--light);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
        }

        .quantity-btn:active {
            background: var(--highlight);
            transform: scale(0.95);
        }

        .quantity-btn:disabled {
            opacity: 0.3;
            pointer-events: none;
        }

        .cart-item-quantity span {
            min-width: 40px;
            text-align: center;
            font-size: 18px;
            font-weight: 600;
        }

        .remove-item {
            color: #aaa;
            cursor: pointer;
            transition: all 0.3s;
            padding: 10px;
            font-size: 20px;
        }

        .remove-item:active {
            color: var(--danger);
            transform: scale(0.9);
        }

        .cart-footer {
            padding: 20px;
            background: rgba(0, 0, 0, 0.2);
            border-top: 2px solid rgba(255, 255, 255, 0.1);
        }

        .delivery-note {
            background: rgba(9, 132, 227, 0.1);
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 15px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 20px;
            font-weight: 600;
        }

        .cart-total span:last-child {
            color: var(--highlight);
            font-size: 28px;
        }

        .checkout-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            border-radius: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 20px;
            min-height: 64px;
        }

        .checkout-btn:active {
            transform: scale(0.98);
            box-shadow: 0 8px 20px rgba(233, 69, 96, 0.4);
        }

        .checkout-btn:disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .empty-cart {
            text-align: center;
            color: #aaa;
            padding: 60px 20px;
        }

        .empty-cart i {
            font-size: 70px;
            color: rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .empty-cart p {
            font-size: 18px;
        }

        /* Quantity Modal - Tablet optimized */
        .quantity-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .quantity-modal-content {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 40px;
            border-radius: 30px;
            max-width: 500px;
            width: 90%;
            border: 3px solid var(--highlight);
        }

        .quantity-modal-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .quantity-modal-header i {
            font-size: 60px;
            color: var(--highlight);
            margin-bottom: 15px;
        }

        .quantity-modal-header h3 {
            font-size: 28px;
            color: var(--light);
            margin-bottom: 10px;
        }

        .quantity-modal-header p {
            color: #aaa;
            font-size: 18px;
        }

        .quantity-control {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 40px;
            margin: 40px 0;
        }

        .quantity-control-btn {
            width: 70px;
            height: 70px;
            background: rgba(255, 255, 255, 0.1);
            border: 3px solid rgba(255, 255, 255, 0.2);
            color: var(--light);
            font-size: 32px;
            border-radius: 50%;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-control-btn:active {
            background: var(--highlight);
            border-color: var(--highlight);
            transform: scale(0.95);
        }

        .quantity-display {
            font-size: 60px;
            font-weight: 700;
            color: var(--highlight);
            min-width: 100px;
            text-align: center;
        }

        .quantity-modal-actions {
            display: flex;
            gap: 20px;
        }

        .quantity-modal-btn {
            flex: 1;
            padding: 18px;
            border: none;
            border-radius: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 18px;
            min-height: 64px;
        }

        .quantity-modal-btn.cancel {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
        }

        .quantity-modal-btn.confirm {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
        }

        .quantity-modal-btn:active {
            transform: scale(0.98);
        }

        /* Order Success Modal */
        .order-success-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .order-success-content {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            padding: 50px;
            border-radius: 40px;
            max-width: 600px;
            width: 90%;
            border: 3px solid var(--success);
            text-align: center;
        }

        .success-icon {
            width: 120px;
            height: 120px;
            background: rgba(0, 184, 148, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            border: 3px solid var(--success);
        }

        .success-icon i {
            font-size: 60px;
            color: var(--success);
        }

        .order-success-content h2 {
            font-size: 36px;
            margin-bottom: 15px;
            color: var(--success);
        }

        .order-success-content p {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 30px;
            font-size: 18px;
        }

        .delivery-details-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 25px;
            margin: 25px 0;
        }

        .delivery-detail-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .delivery-detail-item:last-child {
            border-bottom: none;
        }

        .detail-icon {
            width: 50px;
            height: 50px;
            background: rgba(233, 69, 96, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .detail-icon i {
            font-size: 24px;
            color: var(--highlight);
        }

        .detail-text {
            flex: 1;
            text-align: left;
        }

        .detail-label {
            font-size: 14px;
            color: #aaa;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 18px;
            font-weight: 600;
            color: var(--light);
        }

        .order-success-actions {
            display: flex;
            gap: 20px;
            margin-top: 30px;
        }

        .success-btn {
            flex: 1;
            padding: 18px;
            border: none;
            border-radius: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 18px;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 64px;
        }

        .success-btn.primary {
            background: linear-gradient(135deg, var(--success), #00cec9);
            color: white;
        }

        .success-btn.secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
        }

        .success-btn:active {
            transform: scale(0.98);
        }

        /* Notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 20px 30px;
            border-radius: 16px;
            color: white;
            font-size: 16px;
            z-index: 9999;
            animation: slideIn 0.3s;
            box-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }

        .notification.success { background: var(--success); }
        .notification.info { background: var(--info); }
        .notification.error { background: var(--danger); }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .loading-spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .clear-cart-btn {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.2);
            color: #aaa;
            padding: 8px 16px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 15px;
            min-height: 44px;
        }

        .clear-cart-btn:active {
            background: rgba(214, 48, 49, 0.2);
            color: var(--danger);
            border-color: var(--danger);
        }

        /* Tablet responsive adjustments */
        @media (max-width: 1024px) {
            .ordering-interface {
                grid-template-columns: 1fr 350px;
            }
            
            .food-grid {
                grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
            }
        }

        @media (max-width: 900px) {
            .ordering-interface {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .food-browser,
            .cart-section {
                height: auto;
                min-height: 500px;
                max-height: 700px;
            }
            
            .welcome-banner {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .table-selector {
                width: 100%;
            }
            
            .table-selector select {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            header {
                flex-direction: column;
                height: auto;
                padding: 15px;
                gap: 15px;
            }
            
            .header-right {
                width: 100%;
                justify-content: center;
            }
            
            .welcome-text h2 {
                font-size: 24px;
            }
            
            .food-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }

        /* Landscape orientation */
        @media (orientation: landscape) and (max-height: 600px) {
            .food-browser,
            .cart-section {
                height: 70vh;
            }
            
            .food-item-image {
                height: 120px;
            }
        }
    </style>
</head>
<body>

<!-- Quantity Selection Modal -->
<div id="quantityModal" class="quantity-modal">
    <div class="quantity-modal-content">
        <div class="quantity-modal-header">
            <i class="fas fa-utensils"></i>
            <h3 id="modalFoodName">Add to Order</h3>
            <p id="modalFoodPrice">Select quantity</p>
        </div>
        
        <div class="quantity-control">
            <button class="quantity-control-btn" onclick="updateModalQuantity(-1)">−</button>
            <span class="quantity-display" id="modalQuantity">1</span>
            <button class="quantity-control-btn" onclick="updateModalQuantity(1)">+</button>
        </div>
        
        <div class="quantity-modal-actions">
            <button class="quantity-modal-btn cancel" onclick="closeQuantityModal()">Cancel</button>
            <button class="quantity-modal-btn confirm" id="confirmAddBtn" onclick="debouncedAddToCart()">Add to Order</button>
        </div>
    </div>
</div>

<!-- Order Success Modal -->
<div id="orderSuccessModal" class="order-success-modal">
    <div class="order-success-content">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2>Order Placed!</h2>
        <p>Your order has been sent to the kitchen and will be prepared shortly.</p>
        
        <div class="delivery-details-card" id="deliveryDetails">
            <!-- Will be populated dynamically -->
        </div>
        
        <div class="order-success-actions">
            <button class="success-btn secondary" onclick="closeSuccessModal()">
                <i class="fas fa-utensils"></i> Continue Ordering
            </button>
            <button class="success-btn primary" onclick="startNewOrder()">
                <i class="fas fa-redo-alt"></i> Start New Order
            </button>
        </div>
    </div>
</div>

<header>
    <div class="header-left">
        <h1><i class="fas fa-microphone-alt"></i> Sirene KTV</h1>
        <p>Tablet Ordering System</p>
    </div>
    <div class="header-right">
        <!-- Back button added here -->
        <a href="landingpage.php" class="back-to-home-btn">
            <i class="fas fa-arrow-left"></i> Back to Home
        </a>
        <div class="tablet-badge">
            <i class="fas fa-tablet-alt"></i>
            Self Service
            <span class="currency-badge"><?php echo $currency_symbol; ?> <?php echo $currency_code; ?></span>
        </div>
    </div>
</header>

<div class="main-container">
    <!-- Welcome Banner for Tablet -->
    <div class="welcome-banner">
        <div class="welcome-text">
            <i class="fas fa-hands"></i>
            <div>
                <h2>Welcome to Sirene KTV</h2>
                <p>Order food and drinks directly from your tablet</p>
            </div>
        </div>
        
        <div class="table-selector">
            <select id="tableSelector" onchange="switchTable(this.value)">
                <option value="1">SSVIP</option>
            </select>
        </div>
    </div>

    <!-- Delivery Information Banner -->
    <div class="delivery-info-banner">
        <div class="delivery-icon">
            <i class="fas fa-concierge-bell"></i>
        </div>
        <div class="delivery-info-text">
            <h4><i class="fas fa-info-circle"></i> Quick Delivery</h4>
            <p id="deliveryMessage">Your order will be delivered directly to your table within 15-20 minutes.</p>
        </div>
        <div class="table-details" id="currentTableDisplay">
            <i class="fas fa-chair"></i>
            <span>Table 1</span>
        </div>
    </div>

    <?php if ($foods_count > 0): ?>
    <!-- Main Ordering Interface -->
    <div class="ordering-interface">
        <!-- Food Browser -->
        <div class="food-browser">
            <div class="food-filters">
                <div class="filter-tabs" id="filterTabs">
                    <?php foreach ($categories as $category): ?>
                        <button class="filter-tab <?php echo $category === $categories[0] ? 'active' : ''; ?>" onclick="filterFood('<?php echo $category; ?>', event)"><?php echo $category; ?></button>
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
                                <img src="<?php echo $food_image; ?>" alt="<?php echo htmlspecialchars($food['item_name']); ?>" loading="lazy" onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\"fas fa-image\" style=\"font-size: 40px; color: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; height: 100%;\"></i>';">
                            <?php else: ?>
                                <i class="fas fa-image" style="font-size: 40px; color: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; height: 100%;"></i>
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
        
        <!-- Cart Section -->
        <div class="cart-section">
            <div class="cart-header">
                <h3><i class="fas fa-shopping-cart"></i> Your Order</h3>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span style="background: var(--highlight); padding: 6px 14px; border-radius: 20px; font-size: 15px;" id="cartItemCount">0</span>
                    <button class="clear-cart-btn" onclick="clearCart()" id="clearCartBtn" style="display: none;">
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                </div>
            </div>
            
            <div class="cart-items" id="cartItems">
                <!-- Cart items will be loaded here dynamically -->
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <p>Your cart is empty</p>
                    <p style="font-size: 15px;">Tap on any item to start ordering</p>
                </div>
            </div>
            
            <div class="cart-footer">
                <div class="delivery-note">
                    <i class="fas fa-clock" style="color: var(--info);"></i>
                    <span>Delivery to: <strong id="cartDeliveryTable">Table 1</strong> (approx. 15-20 min)</span>
                </div>
                
                <div class="cart-total">
                    <span>Total:</span>
                    <span id="cartTotal"><?php echo $currency_symbol; ?>0.00</span>
                </div>
                <button class="checkout-btn" id="checkoutBtn" onclick="placeOrder()" disabled>
                    <i class="fas fa-check-circle"></i> Place Order
                </button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="no-items" style="text-align: center; padding: 100px 20px; background: rgba(255,255,255,0.03); border-radius: 30px;">
        <i class="fas fa-utensils" style="font-size: 100px; color: rgba(255,255,255,0.1); margin-bottom: 25px;"></i>
        <h2 style="margin-bottom: 15px; font-size: 28px;">Menu Coming Soon</h2>
        <p style="color: #aaa; margin-bottom: 30px; font-size: 18px;">Our food menu is currently being updated. Please check back later.</p>
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
    let currentTableId = 1;
    let cart = <?php echo json_encode($_SESSION['tablet_cart']); ?>;
    let currencySymbol = '<?php echo $currency_symbol; ?>';
    
    // State management
    let pendingRequests = new Map();
    let lastAddTime = 0;
    const MIN_REQUEST_INTERVAL = 800;
    
    console.log('Tablet ordering page loaded');
    console.log('Current cart:', cart);
    
    // Debounce function
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
    
    const debouncedAddToCart = debounce(confirmAddToCart, 400);
    
    // Fetch with retry
    async function fetchWithRetry(url, options, maxRetries = 2) {
        for (let i = 0; i < maxRetries; i++) {
            try {
                const response = await fetch(url, options);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return await response.json();
            } catch (error) {
                console.log(`Attempt ${i + 1} failed:`, error);
                if (i === maxRetries - 1) throw error;
                await new Promise(resolve => setTimeout(resolve, Math.pow(2, i) * 300));
            }
        }
    }
    
    // Switch table
    function switchTable(tableId) {
        currentTableId = parseInt(tableId);
        console.log('Switched to Table:', currentTableId);
        
        document.getElementById('currentTableDisplay').innerHTML = `<i class="fas fa-chair"></i><span>Table ${tableId}</span>`;
        document.getElementById('cartDeliveryTable').textContent = `Table ${tableId}`;
        
        updateCartDisplay();
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
            if (item.dataset.category === category) {
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
        document.getElementById('modalFoodPrice').textContent = currencySymbol + price.toFixed(2) + ' each';
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
    
    // Add to cart
    function confirmAddToCart() {
        const now = Date.now();
        if (now - lastAddTime < MIN_REQUEST_INTERVAL) {
            showNotification('Please wait...', 'info');
            return;
        }
        lastAddTime = now;
        
        console.log('Adding to cart:', {foodId: currentFoodId, quantity: selectedQuantity, tableId: currentTableId});
        
        const requestKey = `${currentFoodId}_${currentTableId}`;
        if (pendingRequests.has(requestKey)) {
            showNotification('Already adding...', 'info');
            return;
        }
        pendingRequests.set(requestKey, true);
        
        const confirmBtn = document.querySelector('.quantity-modal-btn.confirm');
        const originalBtnText = confirmBtn.innerHTML;
        confirmBtn.innerHTML = '<span class="loading-spinner"></span> Adding...';
        confirmBtn.disabled = true;
        
        fetchWithRetry(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=add_to_cart&food_id=' + currentFoodId + '&quantity=' + selectedQuantity + '&table_id=' + currentTableId
        })
        .then(data => {
            console.log('Add to cart response:', data);
            if (data.success) {
                cart = data.cart;
                updateCartDisplay();
                closeQuantityModal();
                showNotification('✓ Item added to your order!', 'success');
            } else {
                showNotification('❌ Error: ' + (data.message || 'Could not add item'), 'error');
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showNotification('Network error. Please try again.', 'error');
        })
        .finally(() => {
            pendingRequests.delete(requestKey);
            confirmBtn.innerHTML = originalBtnText;
            confirmBtn.disabled = false;
        });
    }
    
    // Update cart item
    function updateCartItem(index, newQuantity) {
        if (newQuantity < 1) return;
        
        fetchWithRetry(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=update_cart&index=' + index + '&quantity=' + newQuantity
        })
        .then(data => {
            if (data.success) {
                cart = data.cart;
                updateCartDisplay();
            }
        })
        .catch(error => {
            console.error('Error updating cart:', error);
            showNotification('Update failed', 'error');
        });
    }
    
    // Remove from cart
    function removeFromCart(index) {
        fetchWithRetry(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=remove_from_cart&index=' + index
        })
        .then(data => {
            if (data.success) {
                cart = data.cart;
                updateCartDisplay();
                showNotification('Item removed', 'info');
            }
        })
        .catch(error => {
            console.error('Error removing item:', error);
            showNotification('Remove failed', 'error');
        });
    }
    
    // Clear cart
    function clearCart() {
        if (!confirm('Clear all items from your order?')) return;
        
        fetchWithRetry(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear_cart'
        })
        .then(data => {
            if (data.success) {
                cart = [];
                updateCartDisplay();
                showNotification('Cart cleared', 'info');
            }
        })
        .catch(error => {
            console.error('Error clearing cart:', error);
            showNotification('Clear failed', 'error');
        });
    }
    
    // Update cart display
    function updateCartDisplay() {
        const cartItems = document.getElementById('cartItems');
        const cartItemCount = document.getElementById('cartItemCount');
        const cartTotal = document.getElementById('cartTotal');
        const checkoutBtn = document.getElementById('checkoutBtn');
        const clearCartBtn = document.getElementById('clearCartBtn');
        
        if (!cartItems) return;
        
        // Filter cart items for current table
        const tableCartItems = cart.filter(item => item.table_id == currentTableId);
        
        if (tableCartItems.length === 0) {
            cartItems.innerHTML = `
                <div class="empty-cart">
                    <i class="fas fa-shopping-basket"></i>
                    <p>Your cart is empty</p>
                    <p>Tap on any item to start ordering</p>
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
        
        tableCartItems.forEach((item, idx) => {
            const originalIndex = cart.findIndex(cartItem => 
                cartItem.food_id == item.food_id && 
                cartItem.table_id == item.table_id
            );
            
            if (originalIndex === -1) return;
            
            const itemTotal = parseFloat(item.price) * parseInt(item.quantity);
            total += itemTotal;
            
            html += `
                <div class="cart-item">
                    <div class="cart-item-details">
                        <div class="cart-item-name">${item.item_name}</div>
                        <div class="cart-item-price">${currencySymbol}${parseFloat(item.price).toFixed(2)}</div>
                        <div style="font-size: 14px; color: #aaa;">Subtotal: ${currencySymbol}${itemTotal.toFixed(2)}</div>
                    </div>
                    <div class="cart-item-quantity">
                        <button class="quantity-btn" onclick="updateCartItem(${originalIndex}, ${parseInt(item.quantity) - 1})" ${item.quantity <= 1 ? 'disabled' : ''}>−</button>
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
        
        if (cartItemCount) cartItemCount.textContent = tableCartItems.length;
        if (cartTotal) cartTotal.textContent = currencySymbol + total.toFixed(2);
        if (checkoutBtn) checkoutBtn.disabled = false;
    }
    
    // Place order - Saves to database using table_num column
    function placeOrder() {
        const tableCartItems = cart.filter(item => item.table_id == currentTableId);
        
        if (tableCartItems.length === 0) {
            showNotification('Your cart is empty', 'error');
            return;
        }
        
        // Calculate total
        let total = 0;
        tableCartItems.forEach(item => {
            total += parseFloat(item.price) * parseInt(item.quantity);
        });
        
        // Show loading state
        const checkoutBtn = document.getElementById('checkoutBtn');
        const originalText = checkoutBtn.innerHTML;
        checkoutBtn.innerHTML = '<span class="loading-spinner"></span> Placing Order...';
        checkoutBtn.disabled = true;
        
        console.log('Placing order for Table', currentTableId, 'with items:', tableCartItems);
        
        // Send order to server
        fetchWithRetry(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=place_order&table_id=' + currentTableId + '&items=' + encodeURIComponent(JSON.stringify(tableCartItems)) + '&total_amount=' + total
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
                
                // Show success notification
                showNotification('✓ Order placed successfully! Kitchen has been notified.', 'success');
                
                // Populate and show success modal with details
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
    }
    
    // Show order success modal
    function showOrderSuccessModal(orderData) {
        const modal = document.getElementById('orderSuccessModal');
        const deliveryDetails = document.getElementById('deliveryDetails');
        
        // Get current date and time
        const now = new Date();
        const deliveryTime = new Date(now.getTime() + 20 * 60000); // Add 20 minutes
        
        const formattedTime = deliveryTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        
        deliveryDetails.innerHTML = `
            <div class="delivery-detail-item">
                <div class="detail-icon"><i class="fas fa-chair"></i></div>
                <div class="detail-text">
                    <div class="detail-label">Table Number</div>
                    <div class="detail-value">Table ${currentTableId}</div>
                </div>
            </div>
            <div class="delivery-detail-item">
                <div class="detail-icon"><i class="fas fa-clock"></i></div>
                <div class="detail-text">
                    <div class="detail-label">Estimated Delivery</div>
                    <div class="detail-value">${formattedTime} (15-20 min)</div>
                </div>
            </div>
            <div class="delivery-detail-item">
                <div class="detail-icon"><i class="fas fa-utensils"></i></div>
                <div class="detail-text">
                    <div class="detail-label">Items Ordered</div>
                    <div class="detail-value">${orderData.items_added || 0} item(s)</div>
                </div>
            </div>
            <div class="delivery-detail-item">
                <div class="detail-icon"><i class="fas fa-hashtag"></i></div>
                <div class="detail-text">
                    <div class="detail-label">Order Reference</div>
                    <div class="detail-value">#${Math.floor(Math.random() * 10000)}</div>
                </div>
            </div>
        `;
        
        modal.style.display = 'flex';
    }
    
    // Close success modal
    function closeSuccessModal() {
        document.getElementById('orderSuccessModal').style.display = 'none';
    }
    
    // Start new order
    function startNewOrder() {
        closeSuccessModal();
        clearCart();
    }
    
    // Show notification
    function showNotification(message, type) {
        const existingNotification = document.querySelector('.notification');
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
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Tablet page initialized');
        
        // Set initial filter to first category
        const firstFilterTab = document.querySelector('.filter-tab');
        if (firstFilterTab) {
            const firstCategory = firstFilterTab.textContent;
            filterFood(firstCategory, { target: firstFilterTab });
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
                closeSuccessModal();
            }
        });
        
        // Animate food items
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
    });
</script>

</body>
</html>