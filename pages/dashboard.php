<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'user';

// Check active tab from URL or default to rooms
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'rooms';

// Fetch rooms data
$rooms_query = $conn->query("SELECT * FROM room WHERE status = 'Available' ORDER BY r_id DESC");
$rooms = $rooms_query->fetch_all(MYSQLI_ASSOC);
$available_rooms_count = count($rooms);

// Fetch foods data
$foods_query = $conn->query("SELECT * FROM food_beverages ORDER BY f_id DESC");
$foods = $foods_query->fetch_all(MYSQLI_ASSOC);
$foods_count = count($foods);

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
            padding: 18px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--highlight);
        }

        .header-left h1 {
            font-size: 26px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .header-left p {
            color: #aaa;
            font-size: 13px;
            margin-top: 3px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .user-info {
            background: var(--accent);
            padding: 7px 14px;
            border-radius: 18px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .user-info i {
            color: var(--highlight);
        }

        .logout-btn {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            padding: 9px 22px;
            border-radius: 22px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
        }

        .logout-btn:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-1px);
        }

        .my-bookings-btn {
            background: linear-gradient(135deg, var(--info), #0984e3);
            color: white;
            border: none;
            padding: 9px 22px;
            border-radius: 22px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 13px;
            text-decoration: none;
        }

        .my-bookings-btn:hover {
            background: linear-gradient(135deg, #0984e3, var(--info));
            transform: translateY(-1px);
            text-decoration: none;
            color: white;
        }

        .welcome-section {
            padding: 28px;
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
            margin: 18px 30px;
            border-radius: 14px;
            border-left: 3px solid var(--highlight);
        }

        .welcome-section h2 {
            font-size: 26px;
            margin-bottom: 10px;
            color: var(--light);
        }

        .welcome-section p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 15px;
            max-width: 550px;
            margin: 0 auto;
            line-height: 1.5;
        }

        /* Navigation Tabs */
        .nav-tabs {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 0 30px 25px;
            padding: 0 10px;
        }

        .nav-tab {
            flex: 1;
            max-width: 200px;
            padding: 14px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 600;
            font-size: 15px;
            color: var(--light);
        }

        .nav-tab:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }

        .nav-tab.active {
            background: var(--highlight);
            color: white;
            border-color: var(--highlight);
            box-shadow: 0 4px 12px rgba(233, 69, 96, 0.3);
        }

        .nav-tab i {
            font-size: 18px;
        }

        .main-container {
            display: grid;
            grid-template-columns: 3fr 1fr;
            gap: 25px;
            padding: 0 30px 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .content-section {
            display: none;
            animation: fadeIn 0.3s;
        }

        .content-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Rooms Section */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-header h2 {
            font-size: 21px;
            color: var(--light);
            display: flex;
            align-items: center;
            gap: 9px;
        }

        .section-header h2 i {
            color: var(--highlight);
        }

        .section-count {
            background: var(--accent);
            padding: 5px 14px;
            border-radius: 18px;
            font-size: 13px;
            font-weight: 600;
        }

        .rooms-grid, .foods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 22px;
        }

        .room-card, .food-card {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 22px;
            transition: all 0.25s;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .room-card:hover, .food-card:hover {
            transform: translateY(-6px);
            background: rgba(255, 255, 255, 0.12);
            border-color: var(--highlight);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.25);
        }

        .item-id {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(233, 69, 96, 0.2);
            color: var(--highlight);
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
        }

        .item-name {
            font-size: 20px;
            font-weight: 700;
            color: var(--highlight);
            margin-bottom: 12px;
            padding-right: 38px;
        }

        .item-description {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 18px;
            font-size: 13px;
            line-height: 1.5;
            min-height: 38px;
        }

        .item-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 18px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 9px;
        }

        .item-detail {
            text-align: center;
            flex: 1;
        }

        .detail-icon {
            font-size: 18px;
            color: var(--highlight);
            margin-bottom: 6px;
        }

        .detail-label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .detail-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--light);
        }

        .item-status {
            display: inline-block;
            padding: 7px 18px;
            border-radius: 18px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 18px;
        }

        .item-status.available {
            background: rgba(0, 184, 148, 0.2);
            color: var(--success);
            border: 1px solid rgba(0, 184, 148, 0.3);
        }

        .item-status.in-stock {
            background: rgba(9, 132, 227, 0.2);
            color: var(--info);
            border: 1px solid rgba(9, 132, 227, 0.3);
        }

        .item-status.low-stock {
            background: rgba(253, 203, 110, 0.2);
            color: var(--warning);
            border: 1px solid rgba(253, 203, 110, 0.3);
        }

        .book-btn, .order-btn {
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            padding: 13px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .book-btn:hover, .order-btn:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-2px);
        }

        /* Sidebar */
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .sidebar-card {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 14px;
            padding: 22px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header i {
            font-size: 20px;
            color: var(--highlight);
        }

        .sidebar-header h3 {
            font-size: 17px;
            color: var(--light);
            flex: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .stat-item {
            text-align: center;
            padding: 16px 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            transition: all 0.2s;
        }

        .stat-item:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
        }

        .stat-icon {
            font-size: 22px;
            color: var(--highlight);
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--light);
            margin: 6px 0;
        }

        .stat-label {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .quick-actions {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .action-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 9px;
            cursor: pointer;
            transition: all 0.2s;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .action-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(3px);
            border-color: var(--highlight);
        }

        .action-icon {
            width: 36px;
            height: 36px;
            background: rgba(233, 69, 96, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--highlight);
            font-size: 15px;
        }

        .action-text {
            flex: 1;
            font-weight: 600;
            color: var(--light);
            font-size: 14px;
        }

        .no-items {
            text-align: center;
            padding: 45px 25px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            border: 1px dashed rgba(255, 255, 255, 0.1);
        }

        .no-items i {
            font-size: 60px;
            color: rgba(255, 255, 255, 0.15);
            margin-bottom: 18px;
        }

        .no-items h3 {
            font-size: 21px;
            color: var(--light);
            margin-bottom: 12px;
        }

        .no-items p {
            color: rgba(255, 255, 255, 0.6);
            max-width: 350px;
            margin: 0 auto 18px;
            line-height: 1.5;
            font-size: 14px;
        }

        footer {
            text-align: center;
            padding: 22px;
            background: rgba(10, 10, 20, 0.95);
            margin-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 13px;
            color: rgba(255, 255, 255, 0.6);
        }

        footer p {
            margin-bottom: 8px;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 18px;
            margin-top: 10px;
        }

        .footer-links a {
            color: var(--highlight);
            text-decoration: none;
            font-size: 12px;
            transition: all 0.2s;
        }

        .footer-links a:hover {
            color: #ff7675;
            text-decoration: underline;
        }

        @media (max-width: 1024px) {
            .main-container {
                grid-template-columns: 1fr;
                gap: 22px;
            }
        }

        @media (max-width: 768px) {
            header {
                padding: 15px 22px;
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
            
            .header-right {
                flex-direction: column;
                gap: 12px;
                width: 100%;
            }
            
            .user-info, .my-bookings-btn, .logout-btn {
                width: 100%;
                justify-content: center;
            }
            
            .welcome-section {
                margin: 15px;
                padding: 22px;
            }
            
            .nav-tabs {
                margin: 0 15px 20px;
                flex-direction: column;
                align-items: center;
            }
            
            .nav-tab {
                max-width: 100%;
                width: 100%;
            }
            
            .main-container {
                padding: 0 15px 22px;
            }
            
            .rooms-grid, .foods-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }
        }

        @media (max-width: 480px) {
            .welcome-section h2 {
                font-size: 22px;
            }
            
            .welcome-section p {
                font-size: 14px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .item-details {
                flex-direction: column;
                gap: 12px;
            }
            
            .sidebar-card {
                padding: 18px;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="header-left">
        <h1><i class="fas fa-microphone-alt"></i> Sirene KTV Dashboard</h1>
        <p>Your Karaoke Experience</p>
    </div>
    <div class="header-right">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <?php echo htmlspecialchars($name); ?> (<?php echo ucfirst($role); ?>)
        </div>
        
        <!-- My Bookings Button -->
        <a href="my-bookings.php" class="my-bookings-btn">
            <i class="fas fa-calendar-check"></i> My Bookings
        </a>
        
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
</header>

<div class="welcome-section">
    <h2>Welcome, <?php echo htmlspecialchars($name); ?>! 🎤</h2>
    <p>Browse available rooms and food menu for your KTV experience</p>
</div>

<!-- Navigation Tabs -->
<div class="nav-tabs">
    <div class="nav-tab <?php echo $active_tab === 'rooms' ? 'active' : ''; ?>" onclick="switchTab('rooms')">
        <i class="fas fa-door-closed"></i> Rooms
    </div>
    <div class="nav-tab <?php echo $active_tab === 'foods' ? 'active' : ''; ?>" onclick="switchTab('foods')">
        <i class="fas fa-utensils"></i> Food & Drinks
    </div>
</div>

<div class="main-container">
    <!-- Rooms Section -->
    <div id="rooms-section" class="content-section <?php echo $active_tab === 'rooms' ? 'active' : ''; ?>">
        <div class="section-header">
            <h2><i class="fas fa-door-closed"></i> Available Rooms</h2>
            <div class="section-count"><?php echo $available_rooms_count; ?> Available</div>
        </div>
        
        <?php if ($available_rooms_count > 0): ?>
            <div class="rooms-grid">
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
                    <div class="room-card">
                        <div class="item-id">#<?php echo $room['r_id']; ?></div>
                        <div class="item-name"><?php echo htmlspecialchars($room['room_name']); ?></div>
                        <p class="item-description"><?php echo $room_description; ?></p>
                        
                        <div class="item-details">
                            <div class="item-detail">
                                <div class="detail-icon"><i class="fas fa-user-friends"></i></div>
                                <div class="detail-label">Capacity</div>
                                <div class="detail-value"><?php echo $room['capcity']; ?> Persons</div>
                            </div>
                            <div class="item-detail">
                                <div class="detail-icon"><i class="fas fa-clock"></i></div>
                                <div class="detail-label">Price/Hour</div>
                                <div class="detail-value">₹<?php echo number_format($room['price_hr'], 2); ?></div>
                            </div>
                        </div>
                        
                        <div class="item-status available">
                            <i class="fas fa-circle"></i> Available
                        </div>
                        
                        <button class="book-btn" onclick="bookRoom(<?php echo $room['r_id']; ?>)">
                            <i class="fas fa-calendar-plus"></i> Book Room
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-items">
                <i class="fas fa-door-closed"></i>
                <h3>No Rooms Available</h3>
                <p>All rooms are currently occupied. Please check back later.</p>
                <button class="book-btn" onclick="contactSupport()" style="max-width: 200px; margin: 0 auto;">
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
            <div class="foods-grid">
                <?php foreach ($foods as $food): 
                    // Determine stock status
                    $stock_status = 'in-stock';
                    $stock_label = 'In Stock';
                    if (isset($food['stock'])) {
                        if ($food['stock'] <= 5) {
                            $stock_status = 'low-stock';
                            $stock_label = 'Low Stock';
                        } elseif ($food['stock'] == 0) {
                            $stock_status = 'out-of-stock';
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
                ?>
                    <div class="food-card">
                        <div class="item-id">#<?php echo $food['f_id']; ?></div>
                        <div class="item-name"><?php echo htmlspecialchars($food['item_name']); ?></div>
                        <p class="item-description"><?php echo $food_description; ?></p>
                        
                        <div class="item-details">
                            <div class="item-detail">
                                <div class="detail-icon"><i class="fas fa-tag"></i></div>
                                <div class="detail-label">Category</div>
                                <div class="detail-value"><?php echo $category; ?></div>
                            </div>
                            <div class="item-detail">
                                <div class="detail-icon"><i class="fas fa-money-bill-wave"></i></div>
                                <div class="detail-label">Price</div>
                                <div class="detail-value">₹<?php echo number_format($food['price'], 2); ?></div>
                            </div>
                        </div>
                        
                        <?php if (isset($food['stock'])): ?>
                            <div class="item-detail" style="margin-bottom: 12px; text-align: center;">
                                <div class="detail-icon"><i class="fas fa-box"></i></div>
                                <div class="detail-label">Stock</div>
                                <div class="detail-value"><?php echo $food['stock']; ?></div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="item-status <?php echo $stock_status; ?>">
                            <i class="fas fa-circle"></i> <?php echo $stock_label; ?>
                        </div>
                        
                        <button class="order-btn" onclick="orderFood(<?php echo $food['f_id']; ?>)">
                            <i class="fas fa-shopping-cart"></i> Order Now
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-items">
                <i class="fas fa-utensils"></i>
                <h3>No Food Items Available</h3>
                <p>Food menu is currently being updated. Please check back later.</p>
                <button class="order-btn" onclick="contactSupport()" style="max-width: 200px; margin: 0 auto;">
                    <i class="fas fa-headset"></i> Contact Support
                </button>
            </div>
        <?php endif; ?>
    </div>

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
                    <div class="stat-label">Rooms</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon"><i class="fas fa-utensils"></i></div>
                    <div class="stat-value"><?php echo $foods_count; ?></div>
                    <div class="stat-label">Food Items</div>
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
                    <div class="action-text">View Rooms</div>
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
                <li class="action-item" onclick="viewProfile()">
                    <div class="action-icon"><i class="fas fa-user-edit"></i></div>
                    <div class="action-text">Edit Profile</div>
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
</div>

<footer>
    <p>&copy; 2024 Sirene KTV. All Rights Reserved.</p>
    <div class="footer-links">
        <a href="#">Terms</a>
        <a href="#">Privacy</a>
        <a href="#">Contact</a>
        <a href="#">Help</a>
    </div>
</footer>

<script>
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
        
        // Update stats based on active tab
        updateStats(tabName);
    }

    function updateStats(tabName) {
        // You can update sidebar stats dynamically here if needed
        console.log(`Switched to ${tabName} tab`);
    }

    function bookRoom(roomId) {
        if (confirm('Book this room?')) {
            window.location.href = 'book-room.php?room_id=' + roomId;
        }
    }

    function orderFood(foodId) {
        if (confirm('Add this item to your order?')) {
            window.location.href = 'order-food.php?food_id=' + foodId;
        }
    }

    function viewProfile() {
        alert('Profile editing coming soon!');
    }

    function contactSupport() {
        alert('Support: support@sirenektv.com\nPhone: +1-800-KTV-SING');
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Animate cards based on active tab
        const activeSection = document.querySelector('.content-section.active');
        if (activeSection) {
            const cards = activeSection.querySelectorAll('.room-card, .food-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(15px)';
                setTimeout(() => {
                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });
        }
        
        // Handle browser back/forward buttons
        window.addEventListener('popstate', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'rooms';
            switchTab(tab);
        });
    });
</script>

</body>
</html>