<?php
session_start();
include "../db.php";
// Add these cache control headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Check if user has an active booking (booked today or future)
$has_active_booking = false;
$today = date('Y-m-d');
$check_booking_query = $conn->query("
    SELECT COUNT(*) as count FROM booking 
    WHERE u_id = $user_id 
    AND status IN ('Approved', 'Pending') 
    AND booking_date >= '$today'
");
$booking_result = $check_booking_query->fetch_assoc();
if ($booking_result['count'] > 0) {
    $has_active_booking = true;
}

// Check active tab from URL or default to rooms
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'rooms';

// Fetch rooms data
$rooms_query = $conn->query("SELECT * FROM room WHERE status = 'Available' ORDER BY r_id DESC");
$rooms = $rooms_query->fetch_all(MYSQLI_ASSOC);
$available_rooms_count = count($rooms);

// Fetch foods data
$foods_query = $conn->query("SELECT * FROM food_beverages ORDER BY category, item_name ASC");
$foods = $foods_query->fetch_all(MYSQLI_ASSOC);
$foods_count = count($foods);

// Food image mapping - USING LOCAL IMAGES RELATIVE TO CURRENT PAGE
$food_images = [
    // Appetizers
    'Cheese Balls' => '../images/cheese-balls.jpg',
    'Chicken Lollipop' => '../images/chicken-lollipop.jpg',
    'Chicken Wings' => '../images//chicken-wings.jpg',
    'Paneer Tikka' => '../images/paneer-tikka.jpg',
    'Spring Rolls (Veg)' => '../images/spring-rolls.jpg',
    
    // Main Course
    'Chicken Biryani' => '../images/chicken-biryani.jpg',
    'Fish & Chips' => '../images/fish-chips.jpg',
    'Paneer Butter Masala' => '../images/paneer-butter-masala.jpg',
    'Veg Hakka Noodles' => '../images/veg-hakka-noodles.jpg',
    
    // Snacks
    'Chicken Burger' => '../images/chicken-burger.jpg',
    'Chicken Hot Dog' => '../images/chicken-hotdog.jpg',
    'Chicken Wrap' => '../images/chicken-wrap.jpg',
    'Masala Fries' => '../images/masala-fries.jpg',
    'Nachos with Cheese' => '../images/nachos-cheese.jpg',
    
    // Beverages
    'Coca-Cola (500ml)' => '../images/coca-cola.jpg',
    'Fresh Lime Soda' => '../images/fresh-lime-soda.jpg',
    'Hot Coffee' => '../images/hot-coffee.jpg',
    'Iced Tea' => '../images/iced-tea.jpg',
    'Virgin Mojito' => '../images/virgin-mojito.jpg',
    
    // Alcoholic Drinks
    'Brandy (60ml)' => '../images/brandy.jpg',
    'Champagne (Glass)' => '../images/champagne.jpg',
    'Gin (60ml)' => '../images/gin.jpg',
    'Tequila Shot' => '../images/tequila.jpg',
    'Whisky (60ml)' => '../images/whisky.jpg',
    
    // Desserts
    'Cheesecake Slice' => '../images/cheesecake.jpg',
    'Chocolate Mousse' => '../images/chocolate-mousse.jpg',
    'Fruit Salad' => '../images/fruit-salad.jpg',
    'Gulab Jamun' => '../images/gulab-jamun.jpg',
    'Ice Cream Sundae' => '../images/ice-cream-sundae.jpg'
];

// Default image if not found
$default_food_image = '../../images/food/default.jpg';

// Room images (if you want to add room images later)
$room_images = [
    // Example: 'VIP Suite' => '../../images/rooms/vip-suite.jpg',
];

// Calculate stats
$avg_capacity = 0;
if ($available_rooms_count > 0) {
    $total_capacity = 0;
    foreach ($rooms as $room) {
        $total_capacity += $room['capcity'];
    }
    $avg_capacity = round($total_capacity / $available_rooms_count);
}

// Calculate food stats
$categories = [];
$avg_price = 0;
if ($foods_count > 0) {
    $total_price = 0;
    foreach ($foods as $food) {
        $total_price += $food['price'];
        if (isset($food['category'])) {
            $categories[] = $food['category'];
        }
    }
    $avg_price = round($total_price / $foods_count, 2);
    $unique_categories = array_unique($categories);
}

// Get user's bookings count
$user_bookings_query = $conn->query("SELECT COUNT(*) as count FROM booking WHERE u_id = $user_id");
$user_bookings_count = $user_bookings_query->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sirene KTV Dashboard</title>
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

        .my-bookings-btn, .logout-btn {
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

        .my-bookings-btn:hover, .logout-btn:hover {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 69, 96, 0.4);
        }

        /* Dashboard Container */
        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 100px);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }

        .content-section {
            display: none;
            animation: fadeIn 0.5s;
        }

        .content-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Section Headers */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .section-header h2 {
            font-size: 24px;
            color: var(--light);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-header h2 i {
            color: var(--highlight);
        }

        .section-count {
            background: var(--accent);
            color: var(--light);
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.08);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            transition: all 0.3s;
            border-left: 5px solid var(--highlight);
        }

        .stat-card:hover {
            background: rgba(255, 255, 255, 0.12);
            transform: translateY(-5px);
        }

        .stat-card i {
            font-size: 40px;
            margin-bottom: 15px;
            color: var(--highlight);
        }

        .stat-card h3 {
            font-size: 32px;
            margin: 10px 0;
        }

        .stat-card p {
            color: #aaa;
            font-size: 14px;
        }

        /* Navigation Tabs */
        .nav-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
        }

        .nav-tab {
            flex: 1;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            color: #aaa;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 16px;
        }

        .nav-tab:hover {
            background: rgba(233, 69, 96, 0.1);
            color: var(--highlight);
        }

        .nav-tab.active {
            background: var(--highlight);
            color: white;
        }

        /* Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }

        .card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border-color: var(--highlight);
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-name {
            font-size: 20px;
            font-weight: 600;
            color: var(--light);
            margin-bottom: 5px;
        }

        .card-price {
            font-size: 24px;
            color: var(--highlight);
            font-weight: 700;
        }

        .card-description {
            padding: 20px;
            color: #aaa;
            font-size: 14px;
            line-height: 1.6;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-details {
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-label {
            font-size: 12px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 16px;
            color: var(--light);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .detail-value i {
            color: var(--highlight);
            font-size: 14px;
        }

        .card-footer {
            padding: 0 20px 20px 20px;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            width: fit-content;
        }

        .status-available {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .status-instock {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .status-lowstock {
            background: rgba(253, 203, 110, 0.2);
            color: var(--warning);
            border: 1px solid rgba(253, 203, 110, 0.3);
        }

        .status-outofstock {
            background: rgba(214, 48, 49, 0.2);
            color: var(--danger);
            border: 1px solid rgba(214, 48, 49, 0.3);
        }

        .action-btn {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 15px;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(233, 69, 96, 0.4);
        }

        .action-btn:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: #666;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .action-btn:disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* Food Image */
        .food-image-container {
            position: relative;
            height: 200px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .food-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }

        .card:hover .food-image {
            transform: scale(1.1);
        }

        .no-image-icon {
            font-size: 60px;
            color: rgba(255, 255, 255, 0.1);
        }

        .food-overlay {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(233, 69, 96, 0.9);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* No Items */
        .no-items {
            text-align: center;
            padding: 60px 20px;
            color: #aaa;
        }

        .no-items i {
            font-size: 80px;
            color: rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .no-items h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--light);
        }

        .no-items p {
            font-size: 16px;
            margin-bottom: 30px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Sidebar */
        .sidebar {
            width: 300px;
            background: rgba(22, 33, 62, 0.9);
            padding: 25px 15px;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
        }

        .sidebar-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header i {
            color: var(--highlight);
            font-size: 20px;
        }

        .sidebar-header h3 {
            font-size: 18px;
            color: var(--light);
        }

        .quick-actions {
            list-style: none;
        }

        .action-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            margin: 8px 0;
            color: var(--light);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            background: transparent;
            width: 100%;
            text-align: left;
            font-size: 15px;
        }

        .action-item:hover {
            background: rgba(233, 69, 96, 0.1);
            transform: translateX(5px);
        }

        .action-icon {
            width: 35px;
            height: 35px;
            background: rgba(233, 69, 96, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-icon i {
            color: var(--highlight);
        }

        .action-text {
            flex: 1;
        }

        .action-item i.fa-chevron-right {
            color: #aaa;
            font-size: 12px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            background: rgba(233, 69, 96, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
        }

        .stat-icon i {
            color: var(--highlight);
            font-size: 18px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--light);
            margin: 5px 0;
        }

        .stat-label {
            font-size: 12px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            overflow-y: auto;
            padding: 20px;
        }

        .modal-content {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            margin: 50px auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            position: relative;
            border: 2px solid var(--highlight);
            animation: modalSlideIn 0.3s ease-out;
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

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-header h2 {
            color: var(--highlight);
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-modal {
            background: transparent;
            border: none;
            color: #aaa;
            font-size: 28px;
            cursor: pointer;
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .close-modal:hover {
            color: var(--highlight);
            background: rgba(255, 255, 255, 0.1);
        }

        .modal-body {
            padding: 20px 0;
        }

        .modal-icon {
            text-align: center;
            margin-bottom: 20px;
        }

        .modal-icon i {
            font-size: 60px;
            color: var(--highlight);
        }

        .modal-title {
            text-align: center;
            font-size: 24px;
            color: var(--light);
            margin-bottom: 10px;
        }

        .modal-subtitle {
            text-align: center;
            color: #aaa;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .modal-details {
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }

        .modal-item-name {
            font-size: 22px;
            font-weight: 600;
            color: var(--light);
            margin-bottom: 20px;
            text-align: center;
        }

        .modal-item-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .modal-detail {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .modal-detail-icon {
            color: var(--highlight);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .modal-detail-label {
            font-size: 12px;
            color: #aaa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-detail-value {
            font-size: 16px;
            color: var(--light);
            font-weight: 600;
        }

        .modal-actions {
            display: flex;
            gap: 15px;
        }

        .modal-btn {
            flex: 1;
            padding: 15px 20px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 16px;
        }

        .modal-btn.confirm {
            background: linear-gradient(135deg, var(--success), #00a085);
            color: white;
        }

        .modal-btn.confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 184, 148, 0.4);
        }

        .modal-btn.cancel {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
        }

        .modal-btn.cancel:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Booking Required Modal */
        .booking-required-modal {
            z-index: 2000;
        }

        .booking-required-content {
            max-width: 450px;
            text-align: center;
            border-color: var(--warning);
        }

        .booking-required-icon {
            font-size: 70px;
            margin-bottom: 20px;
            color: var(--warning);
        }

        .booking-required-title {
            font-size: 28px;
            margin-bottom: 15px;
            color: var(--light);
        }

        .booking-required-message {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
            color: #ddd;
        }

        .booking-required-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }

        .btn-book-room {
            background: var(--highlight);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 150px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-book-room:hover {
            background: #ff4757;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 69, 96, 0.3);
        }

        /* Food Order Success Modal */
        .food-order-success-modal {
            z-index: 2000;
        }

        .food-order-success-content {
            max-width: 450px;
            text-align: center;
            border-color: var(--success);
        }

        .food-order-success-icon {
            font-size: 70px;
            margin-bottom: 20px;
            color: var(--success);
        }

        .food-order-success-title {
            font-size: 28px;
            margin-bottom: 15px;
            color: var(--light);
        }

        .food-order-success-message {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 25px;
            color: #ddd;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
            }
            
            .sidebar-card {
                margin-bottom: 0;
            }
        }

        @media (max-width: 768px) {
            .cards-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .nav-tabs {
                flex-direction: column;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header-right {
                flex-direction: column;
                width: 100%;
            }
            
            .modal-item-details {
                grid-template-columns: 1fr;
            }
            
            .modal-actions {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .stats-cards, .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .section-count {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>

<!-- Booking Modal -->
<div id="bookingModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-door-closed"></i> Book Room</h2>
            <button class="close-modal" onclick="closeModal('bookingModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-icon">
                <i class="fas fa-calendar-plus"></i>
            </div>
            <h2 class="modal-title" id="modalRoomTitle">Book This Room?</h2>
            <p class="modal-subtitle">Confirm booking for selected room</p>
            
            <div class="modal-details">
                <div class="modal-item-name" id="modalRoomName"></div>
                <div class="modal-item-details">
                    <div class="modal-detail">
                        <div class="modal-detail-icon"><i class="fas fa-user-friends"></i></div>
                        <div class="modal-detail-label">Capacity</div>
                        <div class="modal-detail-value" id="modalRoomCapacity"></div>
                    </div>
                    <div class="modal-detail">
                        <div class="modal-detail-icon"><i class="fas fa-clock"></i></div>
                        <div class="modal-detail-label">Price/Hour</div>
                        <div class="modal-detail-value" id="modalRoomPrice"></div>
                    </div>
                </div>
                <p style="color: rgba(255,255,255,0.7); font-size: 14px; margin-top: 15px;">
                    <i class="fas fa-info-circle"></i> You will be redirected to booking page to select date and time
                </p>
            </div>
            
            <div class="modal-actions">
                <button class="modal-btn cancel" onclick="closeModal('bookingModal')">
                    <i class="fas fa-times-circle"></i> Cancel
                </button>
                <button class="modal-btn confirm" onclick="confirmBooking()">
                    <i class="fas fa-check-circle"></i> Yes, Book Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Food Order Modal -->
<div id="foodOrderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-utensils"></i> Order Food</h2>
            <button class="close-modal" onclick="closeModal('foodOrderModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-icon">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <h2 class="modal-title" id="modalFoodTitle">Order This Item?</h2>
            <p class="modal-subtitle">Add selected item to your order</p>
            
            <div class="modal-details">
                <div class="modal-item-name" id="modalFoodName"></div>
                <div class="modal-item-details">
                    <div class="modal-detail">
                        <div class="modal-detail-icon"><i class="fas fa-tag"></i></div>
                        <div class="modal-detail-label">Category</div>
                        <div class="modal-detail-value" id="modalFoodCategory"></div>
                    </div>
                    <div class="modal-detail">
                        <div class="modal-detail-icon"><i class="fas fa-money-bill-wave"></i></div>
                        <div class="modal-detail-label">Price</div>
                        <div class="modal-detail-value" id="modalFoodPrice"></div>
                    </div>
                </div>
                <div id="modalFoodStock"></div>
                <p style="color: rgba(255,255,255,0.7); font-size: 14px; margin-top: 15px;">
                    <i class="fas fa-info-circle"></i> Food orders are placed for your booked KTV sessions
                </p>
            </div>
            
            <div class="modal-actions">
                <button class="modal-btn cancel" onclick="closeModal('foodOrderModal')">
                    <i class="fas fa-times-circle"></i> Cancel
                </button>
                <button class="modal-btn confirm" onclick="processFoodOrder()">
                    <i class="fas fa-check-circle"></i> Yes, Order Now
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Booking Required Modal -->
<div id="bookingRequiredModal" class="modal booking-required-modal">
    <div class="modal-content booking-required-content">
        <div class="modal-header">
            <h2><i class="fas fa-exclamation-triangle"></i> Booking Required</h2>
            <button class="close-modal" onclick="closeModal('bookingRequiredModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="booking-required-icon">
                <i class="fas fa-door-closed"></i>
            </div>
            <h2 class="booking-required-title">Book a Room First!</h2>
            <div class="booking-required-message">
                You need to book a KTV room before ordering food or drinks. 
                Food orders can only be placed for your booked sessions.
                <br><br>
                <strong>Steps to order food:</strong>
                <ol style="text-align: left; margin: 10px 0; padding-left: 20px;">
                    <li>Book a room first</li>
                    <li>Wait for booking approval</li>
                    <li>Then you can order food for your session</li>
                </ol>
            </div>
            <div class="booking-required-actions">
                <button type="button" class="modal-btn cancel" onclick="closeModal('bookingRequiredModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn-book-room" onclick="redirectToRooms()">
                    <i class="fas fa-calendar-plus"></i> Book a Room
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Food Order Success Modal -->
<div id="foodOrderSuccessModal" class="modal food-order-success-modal">
    <div class="modal-content food-order-success-content">
        <div class="modal-header">
            <h2><i class="fas fa-check-circle"></i> Order Successful</h2>
            <button class="close-modal" onclick="closeModal('foodOrderSuccessModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="food-order-success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="food-order-success-title" id="successFoodTitle">Order Placed!</h2>
            <div class="food-order-success-message" id="successFoodMessage">
                Your food order has been successfully recorded. 
                <br><br>
                Your order will be prepared and served during your KTV session.
            </div>
            <div class="booking-required-actions">
                <button type="button" class="btn-book-room" onclick="closeModal('foodOrderSuccessModal')">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>
</div>

<header>
    <div class="header-left">
        <h1><i class="fas fa-microphone-alt"></i> Sirene KTV Dashboard</h1>
        <p>Your Karaoke Experience</p>
    </div>
    <div class="header-right">
        <div class="welcome-message">
            <i class="fas fa-user-circle"></i>
            <?php echo htmlspecialchars($name); ?> (<?php echo ucfirst($role); ?>)
        </div>
        
        <a href="my-bookings.php" class="my-bookings-btn">
            <i class="fas fa-calendar-check"></i> My Bookings (<?php echo $user_bookings_count; ?>)
        </a>
        
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
</header>

<div class="dashboard-container">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-card">
            <div class="sidebar-header">
                <i class="fas fa-chart-bar"></i>
                <h3>Quick Stats</h3>
            </div>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-door-closed"></i></div>
                    <div class="stat-value"><?php echo $available_rooms_count; ?></div>
                    <div class="stat-label">Available Rooms</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                    <div class="stat-value"><?php echo $foods_count; ?></div>
                    <div class="stat-label">Menu Items</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                    <div class="stat-value"><?php echo $avg_capacity; ?></div>
                    <div class="stat-label">Avg Capacity</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                    <div class="stat-value">₹<?php echo $avg_price; ?></div>
                    <div class="stat-label">Avg Food Price</div>
                </div>
            </div>
        </div>

        <div class="sidebar-card">
            <div class="sidebar-header">
                <i class="fas fa-bolt"></i>
                <h3>Quick Actions</h3>
            </div>
            <ul class="quick-actions">
                <li class="action-item" onclick="switchTab('rooms')">
                    <div class="action-icon"><i class="fas fa-door-closed"></i></div>
                    <div class="action-text">View Available Rooms</div>
                    <i class="fas fa-chevron-right"></i>
                </li>
                <li class="action-item" onclick="switchTab('foods')">
                    <div class="action-icon"><i class="fas fa-utensils"></i></div>
                    <div class="action-text">Browse Food Menu</div>
                    <i class="fas fa-chevron-right"></i>
                </li>
                <li class="action-item" onclick="window.location.href='my-bookings.php'">
                    <div class="action-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="action-text">View My Bookings</div>
                    <i class="fas fa-chevron-right"></i>
                </li>
                <li class="action-item" onclick="contactSupport()">
                    <div class="action-icon"><i class="fas fa-headset"></i></div>
                    <div class="action-text">Contact Support</div>
                    <i class="fas fa-chevron-right"></i>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <button class="nav-tab <?php echo $active_tab === 'rooms' ? 'active' : ''; ?>" onclick="switchTab('rooms')">
                <i class="fas fa-door-closed"></i> Rooms
            </button>
            <button class="nav-tab <?php echo $active_tab === 'foods' ? 'active' : ''; ?>" onclick="switchTab('foods')">
                <i class="fas fa-utensils"></i> Food & Drinks
            </button>
        </div>

        <!-- Rooms Section -->
        <div id="rooms-section" class="content-section <?php echo $active_tab === 'rooms' ? 'active' : ''; ?>">
            <div class="section-header">
                <h2><i class="fas fa-door-closed"></i> Available Rooms</h2>
                <div class="section-count"><?php echo $available_rooms_count; ?> Available</div>
            </div>
            
            <?php if ($available_rooms_count > 0): ?>
                <div class="cards-grid">
                    <?php foreach ($rooms as $room): 
                        $descriptions = [
                            "Perfect for intimate sessions",
                            "Great for small groups",
                            "Ideal for gatherings",
                            "Spacious for parties",
                            "Premium VIP experience"
                        ];
                        $description_index = min($room['capcity'] - 1, 4);
                        $room_description = $descriptions[$description_index];
                    ?>
                        <div class="card" data-room-id="<?php echo $room['r_id']; ?>">
                            <div class="card-header">
                                <div class="card-name"><?php echo htmlspecialchars($room['room_name']); ?></div>
                                <div class="card-price">₹<?php echo number_format($room['price_hr'], 2); ?>/hr</div>
                            </div>
                            <p class="card-description"><?php echo $room_description; ?></p>
                            
                            <div class="card-details">
                                <div class="detail-item">
                                    <div class="detail-label">Capacity</div>
                                    <div class="detail-value">
                                        <i class="fas fa-user-friends"></i>
                                        <?php echo $room['capcity']; ?> Persons
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Price/Hour</div>
                                    <div class="detail-value">
                                        <i class="fas fa-money-bill-wave"></i>
                                        ₹<?php echo number_format($room['price_hr'], 2); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <span class="status-badge status-available">
                                    <i class="fas fa-circle"></i> Available
                                </span>
                                <button class="action-btn" onclick="showBookingModal(
                                    <?php echo $room['r_id']; ?>,
                                    '<?php echo htmlspecialchars($room['room_name'], ENT_QUOTES); ?>',
                                    '<?php echo $room['capcity']; ?>',
                                    '₹<?php echo number_format($room['price_hr'], 2); ?>'
                                )">
                                    <i class="fas fa-calendar-plus"></i> Book Room
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-items">
                    <i class="fas fa-door-closed"></i>
                    <h3>No Rooms Available</h3>
                    <p>All rooms are currently occupied. Please check back later or contact support for availability.</p>
                    <button class="action-btn" onclick="contactSupport()" style="max-width: 200px; margin: 0 auto;">
                        <i class="fas fa-headset"></i> Contact Support
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Foods Section -->
        <div id="foods-section" class="content-section <?php echo $active_tab === 'foods' ? 'active' : ''; ?>">
            <div class="section-header">
                <h2><i class="fas fa-utensils"></i> Food & Drinks Menu</h2>
                <div class="section-count"><?php echo $foods_count; ?> Items</div>
            </div>
            
            <?php if ($foods_count > 0): ?>
                <div class="cards-grid">
                    <?php foreach ($foods as $food): 
                        // Determine stock status
                        $stock_status = 'instock';
                        $stock_label = 'In Stock';
                        if (isset($food['stock'])) {
                            if ($food['stock'] <= 5 && $food['stock'] > 0) {
                                $stock_status = 'lowstock';
                                $stock_label = 'Low Stock';
                            } elseif ($food['stock'] == 0) {
                                $stock_status = 'outofstock';
                                $stock_label = 'Out of Stock';
                            }
                        }
                        
                        // Food description based on category
                        $descriptions = [
                            'Appetizer' => 'Perfect starter for your session',
                            'Main Course' => 'Delicious main dish',
                            'Dessert' => 'Sweet ending to your meal',
                            'Beverage' => 'Refreshing drink',
                            'Alcoholic' => 'Premium alcoholic beverage',
                            'Snacks' => 'Quick bites for singing breaks'
                        ];
                        $category = $food['category'] ?? 'Snacks';
                        $food_description = $descriptions[$category] ?? 'Delicious item';
                        
                        // Get image URL - check if file exists
                        $food_image = isset($food_images[$food['item_name']]) ? $food_images[$food['item_name']] : $default_food_image;
                    ?>
                        <div class="card" data-food-id="<?php echo $food['f_id']; ?>">
                            <!-- Food Image -->
                            <div class="food-image-container">
                                <?php 
                                // Check if image file exists (using relative path from current file)
                                $image_path = dirname(__FILE__) . '/' . $food_image;
                                if (file_exists($image_path)): 
                                ?>
                                    <img src="<?php echo $food_image; ?>" alt="<?php echo htmlspecialchars($food['item_name']); ?>" class="food-image" loading="lazy" onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\"fas fa-image no-image-icon\"></i>';">
                                <?php else: ?>
                                    <i class="fas fa-image no-image-icon"></i>
                                <?php endif; ?>
                                <div class="food-overlay">
                                    <?php echo $category; ?>
                                </div>
                            </div>
                            
                            <div class="card-header">
                                <div class="card-name"><?php echo htmlspecialchars($food['item_name']); ?></div>
                                <div class="card-price">₹<?php echo number_format($food['price'], 2); ?></div>
                            </div>
                            
                            <p class="card-description"><?php echo $food_description; ?></p>
                            
                            <div class="card-details">
                                <div class="detail-item">
                                    <div class="detail-label">Category</div>
                                    <div class="detail-value">
                                        <i class="fas fa-tag"></i>
                                        <?php echo $category; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Stock</div>
                                    <div class="detail-value">
                                        <i class="fas fa-box"></i>
                                        <?php echo isset($food['stock']) ? $food['stock'] : 'N/A'; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-footer">
                                <span class="status-badge status-<?php echo $stock_status; ?>">
                                    <i class="fas fa-circle"></i> <?php echo $stock_label; ?>
                                </span>
                                <button class="action-btn" onclick="showFoodOrderModal(
                                    <?php echo $food['f_id']; ?>,
                                    '<?php echo htmlspecialchars($food['item_name'], ENT_QUOTES); ?>',
                                    '<?php echo $category; ?>',
                                    '₹<?php echo number_format($food['price'], 2); ?>',
                                    '<?php echo isset($food['stock']) ? $food['stock'] : ''; ?>',
                                    '<?php echo $stock_label; ?>'
                                )" <?php echo ($stock_status == 'outofstock' || !$has_active_booking) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-shopping-cart"></i> 
                                    <?php if ($stock_status == 'outofstock'): ?>
                                        Out of Stock
                                    <?php elseif (!$has_active_booking): ?>
                                        Book Room First
                                    <?php else: ?>
                                        Order Now
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-items">
                    <i class="fas fa-utensils"></i>
                    <h3>No Food Items Available</h3>
                    <p>Food menu is currently being updated. Please check back later or contact support.</p>
                    <button class="action-btn" onclick="contactSupport()" style="max-width: 200px; margin: 0 auto;">
                        <i class="fas fa-headset"></i> Contact Support
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Store current item IDs for confirmation
    let currentRoomId = null;
    let currentFoodId = null;
    let currentFoodName = null;
    let hasActiveBooking = <?php echo $has_active_booking ? 'true' : 'false'; ?>;

    function switchTab(tabName) {
        // Update URL without page reload
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.pushState({}, '', url);
        
        // Update active tab
        document.querySelectorAll('.nav-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        
        document.querySelector(`.nav-tab[onclick="switchTab('${tabName}')"]`).classList.add('active');
        document.getElementById(`${tabName}-section`).classList.add('active');
    }

    function showBookingModal(roomId, roomName, capacity, price) {
        currentRoomId = roomId;
        
        // Update modal content
        document.getElementById('modalRoomName').textContent = roomName;
        document.getElementById('modalRoomCapacity').textContent = capacity + ' Persons';
        document.getElementById('modalRoomPrice').textContent = price + '/hour';
        
        // Show modal
        const modal = document.getElementById('bookingModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function showFoodOrderModal(foodId, foodName, category, price, stock, stockLabel) {
        // Check if user has active booking
        if (!hasActiveBooking) {
            showBookingRequiredModal(foodName);
            return;
        }
        
        currentFoodId = foodId;
        currentFoodName = foodName;
        
        // Update modal content
        document.getElementById('modalFoodName').textContent = foodName;
        document.getElementById('modalFoodCategory').textContent = category;
        document.getElementById('modalFoodPrice').textContent = price;
        
        // Update stock information
        const stockElement = document.getElementById('modalFoodStock');
        if (stock !== '') {
            stockElement.innerHTML = `
                <div class="modal-detail">
                    <div class="modal-detail-icon"><i class="fas fa-box"></i></div>
                    <div class="modal-detail-label">Stock</div>
                    <div class="modal-detail-value">${stock} (${stockLabel})</div>
                </div>
            `;
        } else {
            stockElement.innerHTML = '';
        }
        
        // Show modal
        const modal = document.getElementById('foodOrderModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function showBookingRequiredModal(foodName) {
        // Show booking required modal
        const modal = document.getElementById('bookingRequiredModal');
        
        // Update modal title with food name if available
        const title = document.querySelector('.booking-required-title');
        if (foodName) {
            title.innerHTML = `Book a Room to Order "${foodName}"!`;
        }
        
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function showFoodOrderSuccessModal(foodName) {
        // Update success modal content
        document.getElementById('successFoodTitle').textContent = `Order for "${foodName}" Placed!`;
        document.getElementById('successFoodMessage').innerHTML = `
            Your order for <strong>${foodName}</strong> has been successfully recorded. 
            <br><br>
            Your food will be prepared and served during your booked KTV session.
            <br><br>
            <small style="color: #aaa; font-size: 14px;">
                <i class="fas fa-info-circle"></i> You can view your orders in "My Bookings" section.
            </small>
        `;
        
        // Show success modal
        const modal = document.getElementById('foodOrderSuccessModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }

    function confirmBooking() {
        if (currentRoomId) {
            // Close modal
            closeModal('bookingModal');
            
            // Redirect to booking page (if you have book-room.php)
            // If not, show an alert instead
            if (confirm("Redirect to booking page?")) {
                window.location.href = 'book-room.php?room_id=' + currentRoomId;
            }
        }
    }

    function processFoodOrder() {
        if (currentFoodId && currentFoodName) {
            // Close the order modal
            closeModal('foodOrderModal');
            
            // Show processing message
            setTimeout(() => {
                showFoodOrderSuccessModal(currentFoodName);
                
                // You could add AJAX here to save the order to database
                // For now, we'll just show the success message
                
            }, 500);
        }
    }

    function redirectToRooms() {
        // Close modal
        closeModal('bookingRequiredModal');
        
        // Switch to rooms tab
        switchTab('rooms');
        
        // Scroll to top of rooms section
        document.getElementById('rooms-section').scrollIntoView({ behavior: 'smooth' });
    }

    function contactSupport() {
        alert('Support: support@sirenektv.com\nPhone: +1-800-KTV-SING\n\nOur support team is available 24/7 to assist you with bookings and inquiries.');
    }

    // Close modal when clicking outside
    document.addEventListener('DOMContentLoaded', function() {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                modals.forEach(modal => {
                    if (modal.style.display === 'block') {
                        closeModal(modal.id);
                    }
                });
            }
        });
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'rooms';
            switchTab(tab);
        });
        
        // Add animation to cards
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            setTimeout(() => {
                card.style.transition = 'opacity 0.5s, transform 0.5s';
                card.style.opacity = '1';
                card.style.transform = 'translateY(0)';
            }, index * 100);
        });
    });
</script>

</body>
</html>