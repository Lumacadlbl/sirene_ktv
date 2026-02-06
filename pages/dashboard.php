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

// Check if user has an ACTIVE booking (not cancelled, not completed, and not past date)
$has_active_booking = false;
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$active_booking_details = null;

// Check for active bookings (not cancelled, not completed, and booking date is today or future)
$check_booking_query = $conn->query("
    SELECT b.*, r.room_name 
    FROM booking b 
    LEFT JOIN room r ON b.r_id = r.r_id 
    WHERE b.u_id = $user_id 
    AND b.status NOT IN ('cancelled', 'completed', 'rejected') 
    AND (
        b.booking_date > '$current_date' 
        OR (b.booking_date = '$current_date' AND b.end_time > '$current_time')
    )
    ORDER BY b.booking_date ASC, b.start_time ASC
");

if ($check_booking_query->num_rows > 0) {
    $has_active_booking = true;
    $active_booking_details = $check_booking_query->fetch_assoc();
}

// Check active tab from URL or default to rooms
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'rooms';

// Fetch rooms data (ALWAYS fetch rooms, regardless of booking status)
$rooms = [];
$available_rooms_count = 0;

// ALWAYS fetch rooms
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
    'Chicken Lollipop' => '../images/chicken-loplipop.jpg',
    'Chicken Wings' => '../images/chicken-wings.jpg',
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

        /* Active Booking Banner */
        .active-booking-banner {
            background: linear-gradient(135deg, var(--accent), #0f3460);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid var(--highlight);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .active-booking-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
        }

        .banner-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .banner-header i {
            font-size: 30px;
            color: var(--highlight);
        }

        .banner-header h2 {
            font-size: 22px;
            color: var(--light);
        }

        .banner-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.05);
            padding: 20px;
            border-radius: 10px;
        }

        .banner-detail {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .banner-detail i {
            color: var(--highlight);
            font-size: 16px;
            width: 24px;
        }

        .banner-detail-label {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.7);
        }

        .banner-detail-value {
            font-weight: 600;
            color: var(--light);
        }

        .banner-actions {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .banner-btn {
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            text-decoration: none;
        }

        .banner-btn-primary {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
        }

        .banner-btn-primary:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-2px);
        }

        .banner-btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .banner-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
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

        .action-btn.disabled {
            background: rgba(255, 255, 255, 0.1);
            cursor: not-allowed;
            opacity: 0.5;
        }

        .action-btn.disabled:hover {
            transform: none;
            box-shadow: none;
        }

        /* Booking Restricted Button */
        .booking-restricted-btn {
            background: linear-gradient(135deg, var(--warning), #e17055);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: not-allowed;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 15px;
            opacity: 0.8;
        }

        .booking-restricted-btn:hover {
            transform: none;
            box-shadow: none;
        }

        .view-btn {
            background: rgba(255, 255, 255, 0.1);
            color: var(--light);
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
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .view-btn:hover {
            background: rgba(233, 69, 96, 0.1);
            border-color: var(--highlight);
            transform: translateY(-2px);
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
            
            .banner-actions {
                flex-direction: column;
            }
            
            .banner-details {
                grid-template-columns: 1fr;
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

        .info-note {
            background: rgba(233, 69, 96, 0.1);
            border: 1px solid rgba(233, 69, 96, 0.3);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.8);
        }

        .info-note i {
            color: var(--highlight);
            margin-right: 8px;
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
            <p class="modal-subtitle">You can add food & drinks during booking</p>
            
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
                    <i class="fas fa-info-circle"></i> You will be redirected to booking page where you can add food & drinks
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

<!-- Food Details Modal -->
<div id="foodDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-utensils"></i> Food Details</h2>
            <button class="close-modal" onclick="closeModal('foodDetailsModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-icon">
                <i class="fas fa-info-circle"></i>
            </div>
            <h2 class="modal-title" id="modalFoodTitle">Item Details</h2>
            <p class="modal-subtitle">View food item information</p>
            
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
                    <i class="fas fa-info-circle"></i> You can add this item to your order when booking a room
                </p>
            </div>
            
            <div class="modal-actions">
                <button class="modal-btn cancel" onclick="closeModal('foodDetailsModal')" style="width: 100%;">
                    <i class="fas fa-times-circle"></i> Close
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
                    <div class="action-text">Browse Rooms</div>
                    <i class="fas fa-chevron-right"></i>
                </li>
                <li class="action-item" onclick="switchTab('foods')">
                    <div class="action-icon"><i class="fas fa-utensils"></i></div>
                    <div class="action-text">View Food Menu</div>
                    <i class="fas fa-chevron-right"></i>
                </li>
                <li class="action-item" onclick="window.location.href='my-bookings.php'">
                    <div class="action-icon"><i class="fas fa-calendar-check"></i></div>
                    <div class="action-text">My Bookings</div>
                    <i class="fas fa-chevron-right"></i>
                </li>
            </ul>
            <div class="info-note">
                <i class="fas fa-info-circle"></i>
                <strong>Note:</strong> Food & drinks can only be ordered when booking a room.
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Show Active Booking Banner if user has an active booking -->
        <?php if ($has_active_booking && $active_booking_details): ?>
            <div class="active-booking-banner">
                <div class="banner-header">
                    <i class="fas fa-calendar-check"></i>
                    <h2>You Have an Active Booking</h2>
                </div>
                
                <div class="banner-details">
                    <div class="banner-detail">
                        <i class="fas fa-calendar"></i>
                        <div>
                            <div class="banner-detail-label">Booking Date</div>
                            <div class="banner-detail-value">
                                <?php echo date('F j, Y', strtotime($active_booking_details['booking_date'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="banner-detail">
                        <i class="fas fa-clock"></i>
                        <div>
                            <div class="banner-detail-label">Time Slot</div>
                            <div class="banner-detail-value">
                                <?php echo date('g:i A', strtotime($active_booking_details['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($active_booking_details['end_time'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="banner-detail">
                        <i class="fas fa-door-closed"></i>
                        <div>
                            <div class="banner-detail-label">Room</div>
                            <div class="banner-detail-value">
                                <?php echo htmlspecialchars($active_booking_details['room_name'] ?? 'Room'); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="banner-detail">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <div class="banner-detail-label">Status</div>
                            <div class="banner-detail-value">
                                <?php echo ucfirst($active_booking_details['status'] ?? 'Pending'); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <p style="color: rgba(255,255,255,0.8); margin-bottom: 20px; font-size: 15px;">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Important:</strong> You can only book one room at a time. 
                    Please wait until your current booking is completed before booking another room.
                </p>
                
                <div class="banner-actions">
                    <a href="booking-details.php?id=<?php echo $active_booking_details['b_id']; ?>" class="banner-btn banner-btn-primary">
                        <i class="fas fa-eye"></i> View Booking Details
                    </a>
                    <a href="my-bookings.php" class="banner-btn banner-btn-secondary">
                        <i class="fas fa-list"></i> View All Bookings
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <div class="nav-tabs">
            <button class="nav-tab <?php echo $active_tab === 'rooms' ? 'active' : ''; ?>" onclick="switchTab('rooms')">
                <i class="fas fa-door-closed"></i> Rooms
                <?php if ($has_active_booking): ?>
                    <span style="font-size: 12px; background: var(--warning); color: #333; padding: 2px 8px; border-radius: 10px; margin-left: 5px;">
                        <i class="fas fa-exclamation"></i> Booking Restricted
                    </span>
                <?php endif; ?>
            </button>
            <button class="nav-tab <?php echo $active_tab === 'foods' ? 'active' : ''; ?>" onclick="switchTab('foods')">
                <i class="fas fa-utensils"></i> Food & Drinks Menu
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
                                
                                <?php if ($has_active_booking): ?>
                                    <!-- Show disabled booking button with message -->
                                    <button class="booking-restricted-btn" onclick="showActiveBookingAlert()">
                                        <i class="fas fa-exclamation-triangle"></i> Ongoing Booking
                                    </button>
                                    <p style="color: rgba(255,255,255,0.6); font-size: 13px; margin-top: 5px; text-align: center;">
                                        <i class="fas fa-info-circle"></i> You have an active booking
                                    </p>
                                    <p style="color: rgba(253, 203, 110, 0.8); font-size: 12px; margin-top: 5px; text-align: center;">
                                        <i class="fas fa-clock"></i> 
                                        <?php echo date('F j', strtotime($active_booking_details['booking_date'])); ?> 
                                        at <?php echo date('g:i A', strtotime($active_booking_details['start_time'])); ?>
                                    </p>
                                <?php else: ?>
                                    <!-- Show normal booking button -->
                                    <button class="action-btn" onclick="showBookingModal(
                                        <?php echo $room['r_id']; ?>,
                                        '<?php echo htmlspecialchars($room['room_name'], ENT_QUOTES); ?>',
                                        '<?php echo $room['capcity']; ?>',
                                        '₹<?php echo number_format($room['price_hr'], 2); ?>'
                                    )">
                                        <i class="fas fa-calendar-plus"></i> Book This Room
                                    </button>
                                    <p style="color: rgba(255,255,255,0.6); font-size: 13px; margin-top: 5px;">
                                        <i class="fas fa-utensils"></i> Add food & drinks during booking
                                    </p>
                                <?php endif; ?>
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
            
            <div class="info-note" style="margin-bottom: 30px;">
                <i class="fas fa-info-circle"></i>
                <strong>Important:</strong> This is a view-only menu. To order food & drinks, you need to book a room first. 
                You can add items to your order during the booking process.
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
                                <button class="view-btn" onclick="viewFoodDetails(
                                    '<?php echo htmlspecialchars($food['item_name'], ENT_QUOTES); ?>',
                                    '<?php echo $category; ?>',
                                    '₹<?php echo number_format($food['price'], 2); ?>',
                                    '<?php echo isset($food['stock']) ? $food['stock'] : ''; ?>',
                                    '<?php echo $stock_label; ?>'
                                )">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <p style="color: rgba(255,255,255,0.6); font-size: 13px; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> Add to order when booking a room
                                </p>
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
        
        document.querySelector(`.nav-tab[onclick*="switchTab('${tabName}')"]`).classList.add('active');
        document.getElementById(`${tabName}-section`).classList.add('active');
    }

    function showActiveBookingAlert() {
        alert('You already have an active booking. Please complete or cancel your current booking before booking another room.');
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

    function viewFoodDetails(foodName, category, price, stock, stockLabel) {
        // Update modal content for viewing only
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
        const modal = document.getElementById('foodDetailsModal');
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
            
            // Redirect to booking page
            window.location.href = 'book-room.php?room_id=' + currentRoomId;
        }
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