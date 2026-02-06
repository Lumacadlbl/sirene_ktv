<?php
session_start();
include "../db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 0;

// Fetch room details
$room_query = $conn->prepare("SELECT * FROM room WHERE r_id = ? AND status = 'Available'");
$room_query->bind_param("i", $room_id);
$room_query->execute();
$room_result = $room_query->get_result();
$room = $room_result->fetch_assoc();

if (!$room) {
    header("Location: dashboard.php?tab=rooms");
    exit;
}

// Fetch food items
$foods_query = $conn->query("SELECT * FROM food_beverages WHERE stock > 0 ORDER BY category, item_name");
$foods = $foods_query->fetch_all(MYSQLI_ASSOC);

// Food image mapping - SAME AS DASHBOARD
$food_images = [
    // Appetizers
    'Cheese Balls' => '../images/cheese-balls.jpg',
    'Chicken Lollipop' => '../images/chicken-lollipop.jpg',
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
$default_food_image = '../images/food/default.jpg';

// Group foods by category
$foods_by_category = [];
foreach ($foods as $food) {
    $category = $food['category'];
    if (!isset($foods_by_category[$category])) {
        $foods_by_category[$category] = [];
    }
    $foods_by_category[$category][] = $food;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_date = $_POST['booking_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];
    $selected_foods = isset($_POST['food_items']) ? $_POST['food_items'] : [];
    
    // Calculate hours
    $start_datetime = strtotime("$booking_date $start_time");
    $end_datetime = strtotime("$booking_date $end_time");
    $hours = round(($end_datetime - $start_datetime) / 3600, 1);
    
    if ($hours <= 0) {
        $error = "End time must be after start time";
    } else {
        // Calculate room amount
        $room_amount = $room['price_hr'] * $hours;
        
        // Calculate food amount
        $food_amount = 0;
        $food_details = [];
        
        foreach ($selected_foods as $food_id => $qty) {
            if ($qty > 0) {
                $food_query = $conn->prepare("SELECT price, item_name FROM food_beverages WHERE f_id = ?");
                $food_query->bind_param("i", $food_id);
                $food_query->execute();
                $food_result = $food_query->get_result();
                $food = $food_result->fetch_assoc();
                
                if ($food) {
                    $food_total = $food['price'] * $qty;
                    $food_amount += $food_total;
                    $food_details[] = [
                        'id' => $food_id,
                        'name' => $food['item_name'],
                        'qty' => $qty,
                        'price' => $food['price'],
                        'total' => $food_total
                    ];
                }
            }
        }
        
        // Calculate tax (10% for example)
        $subtotal = $room_amount + $food_amount;
        $tax_amount = $subtotal * 0.10;
        $total_amount = $subtotal + $tax_amount;
        
        // Insert booking
       $stmt = $conn->prepare("INSERT INTO booking (
    u_id, r_id, booking_date, start_time, end_time, hours, room_amount, food_amount, subtotal, tax_amount, total_amount, status, payment_status, created_at
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())");

$stmt->bind_param(
    "iisssiddddd",  // 11 types for 11 placeholders
    $user_id, 
    $room_id, 
    $booking_date, 
    $start_time, 
    $end_time, 
    $hours, 
    $room_amount, 
    $food_amount, 
    $subtotal, 
    $tax_amount, 
    $total_amount
);

        
        if ($stmt->execute()) {
            $booking_id = $stmt->insert_id;
            
            // Insert booking food items if any
            if (!empty($food_details)) {
                foreach ($food_details as $food) {
                    $food_stmt = $conn->prepare("INSERT INTO booking_food (b_id, f_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $food_stmt->bind_param("iiid", $booking_id, $food['id'], $food['qty'], $food['price']);
                    $food_stmt->execute();
                }
            }
            
            // Update room status to Booked
            $update_room = $conn->prepare("UPDATE room SET status = 'Booked' WHERE r_id = ?");
            $update_room->bind_param("i", $room_id);
            $update_room->execute();
            
            header("Location: booking-confirmation.php?booking_id=$booking_id");
            exit;
        } else {
            $error = "Failed to create booking. Please try again.";
        }
    }
}

// Calculate min/max dates (today to 30 days in future)
$min_date = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+30 days'));

// Default booking date (tomorrow)
$default_date = date('Y-m-d', strtotime('+1 day'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Room - Sirene KTV</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #1a1a2e;
            --secondary: #16213e;
            --accent: #0f3460;
            --highlight: #e94560;
            --light: #f5f5f5;
            --success: #00b894;
            --warning: #fdcb6e;
            --danger: #d63031;
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
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        @media (max-width: 768px) {
            .container {
                grid-template-columns: 1fr;
            }
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            grid-column: 1 / -1;
        }
        
        .header h1 {
            font-size: 32px;
            background: linear-gradient(90deg, var(--highlight), #ff7675);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 10px;
        }
        
        .header p {
            color: rgba(255, 255, 255, 0.7);
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
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .room-info {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .room-info h2 {
            color: var(--highlight);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .room-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .detail-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .detail-label {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 18px;
            font-weight: bold;
            color: var(--light);
        }
        
        .booking-form {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--light);
            font-weight: 500;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-size: 16px;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--highlight);
        }
        
        .time-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .food-section {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        /* NEW: Food Category Tabs */
        .food-category-tabs {
            display: flex;
            overflow-x: auto;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        
        .food-tab {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            white-space: nowrap;
            transition: all 0.3s;
        }
        
        .food-tab:hover {
            background: rgba(233, 69, 96, 0.1);
            color: var(--highlight);
        }
        
        .food-tab.active {
            background: var(--highlight);
            color: white;
            border-color: var(--highlight);
        }
        
        /* NEW: Food Grid with Images */
        .food-category-content {
            display: none;
        }
        
        .food-category-content.active {
            display: block;
        }
        
        .food-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .food-item-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }
        
        .food-item-card:hover {
            transform: translateY(-5px);
            border-color: var(--highlight);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .food-image-container {
            position: relative;
            height: 150px;
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
        
        .food-item-card:hover .food-image {
            transform: scale(1.1);
        }
        
        .no-image-icon {
            font-size: 40px;
            color: rgba(255, 255, 255, 0.1);
        }
        
        .food-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(233, 69, 96, 0.9);
            color: white;
            padding: 4px 8px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .food-card-content {
            padding: 15px;
        }
        
        .food-card-name {
            font-weight: bold;
            color: var(--light);
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .food-card-price {
            color: var(--highlight);
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        
        .food-card-stock {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .food-card-stock i {
            color: var(--success);
        }
        
        .food-qty-controls {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        
        .food-qty-input {
            width: 80px;
            padding: 8px;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            color: white;
        }
        
        .food-qty-buttons {
            display: flex;
            gap: 5px;
        }
        
        .qty-btn {
            width: 30px;
            height: 30px;
            background: rgba(233, 69, 96, 0.2);
            border: none;
            border-radius: 5px;
            color: var(--highlight);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .qty-btn:hover {
            background: var(--highlight);
            color: white;
        }
        
        .summary {
            position: sticky;
            top: 20px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 15px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            grid-column: 2;
            grid-row: 2 / span 2;
        }
        
        @media (max-width: 768px) {
            .summary {
                grid-column: 1;
                grid-row: auto;
            }
            
            .food-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        .summary h2 {
            color: var(--highlight);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .summary-total {
            font-size: 20px;
            font-weight: bold;
            color: var(--highlight);
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .book-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, var(--highlight), #ff4757);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .book-btn:hover {
            background: linear-gradient(135deg, #ff4757, var(--highlight));
            transform: translateY(-2px);
        }
        
        .error {
            background: rgba(220, 53, 69, 0.2);
            color: #ff6b6b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .success {
            background: rgba(40, 167, 69, 0.2);
            color: #51cf66;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .hours-display {
            text-align: center;
            padding: 10px;
            background: rgba(233, 69, 96, 0.1);
            border-radius: 8px;
            margin: 15px 0;
            font-size: 18px;
            font-weight: bold;
            color: var(--highlight);
        }
        
        .category-title {
            color: var(--highlight);
            margin: 20px 0 15px 0;
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .no-food-message {
            text-align: center;
            padding: 40px 20px;
            color: rgba(255, 255, 255, 0.5);
            grid-column: 1 / -1;
        }
        
        .no-food-message i {
            font-size: 50px;
            margin-bottom: 15px;
            color: rgba(255, 255, 255, 0.2);
        }
    </style>
</head>
<body>

<button class="back-btn" onclick="window.location.href='dashboard.php?tab=rooms'">
    <i class="fas fa-arrow-left"></i> Back to Rooms
</button>

<div class="header">
    <h1><i class="fas fa-calendar-plus"></i> Book Your KTV Room</h1>
    <p>Complete your booking details below - Add food & drinks during booking</p>
</div>

<?php if (isset($error)): ?>
    <div class="error" style="max-width: 1200px; margin: 0 auto 20px;">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="container">
    <div class="room-info">
        <h2><i class="fas fa-door-closed"></i> Room Details</h2>
        <div class="room-details">
            <div class="detail-item">
                <div class="detail-label">Room Name</div>
                <div class="detail-value"><?php echo htmlspecialchars($room['room_name']); ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Capacity</div>
                <div class="detail-value"><?php echo $room['capcity']; ?> Persons</div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Price per Hour</div>
                <div class="detail-value">₹<?php echo number_format($room['price_hr'], 2); ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Status</div>
                <div class="detail-value" style="color: var(--success);">
                    <i class="fas fa-check-circle"></i> Available
                </div>
            </div>
        </div>
        <p style="color: rgba(255, 255, 255, 0.7); line-height: 1.6;">
            <i class="fas fa-info-circle"></i> Add food and drinks to your booking below. All items will be served during your KTV session.
        </p>
    </div>

    <div class="booking-form">
        <h2><i class="fas fa-calendar-alt"></i> Booking Details</h2>
        <form method="POST" action="" id="bookingForm">
            <div class="form-group">
                <label for="booking_date"><i class="far fa-calendar"></i> Booking Date</label>
                <input type="date" id="booking_date" name="booking_date" 
                       min="<?php echo $min_date; ?>" 
                       max="<?php echo $max_date; ?>"
                       value="<?php echo $default_date; ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label><i class="far fa-clock"></i> Time Slot</label>
                <div class="time-inputs">
                    <div>
                        <label for="start_time">Start Time</label>
                        <input type="time" id="start_time" name="start_time" 
                               value="18:00" min="10:00" max="23:00" required>
                    </div>
                    <div>
                        <label for="end_time">End Time</label>
                        <input type="time" id="end_time" name="end_time" 
                               value="20:00" min="11:00" max="02:00" required>
                    </div>
                </div>
                <div id="hoursDisplay" class="hours-display">Duration: 2.0 hours</div>
            </div>
            
            <div class="food-section">
                <h3><i class="fas fa-utensils"></i> Add Food & Drinks (Optional)</h3>
                <p style="color: rgba(255, 255, 255, 0.6); margin-bottom: 15px; font-size: 14px;">
                    Browse our menu and add items to your booking. Prices include tax.
                </p>
                
                <?php if (!empty($foods_by_category)): ?>
                    <!-- Food Category Tabs -->
                    <div class="food-category-tabs">
                        <?php foreach (array_keys($foods_by_category) as $index => $category): ?>
                            <button type="button" class="food-tab <?php echo $index === 0 ? 'active' : ''; ?>" 
                                    onclick="switchFoodTab('<?php echo $category; ?>')">
                                <?php echo $category; ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Food Categories Content -->
                    <?php foreach ($foods_by_category as $category => $category_foods): ?>
                        <div id="food-category-<?php echo strtolower(str_replace(' ', '-', $category)); ?>" 
                             class="food-category-content <?php echo $category === array_key_first($foods_by_category) ? 'active' : ''; ?>">
                            <h4 class="category-title">
                                <i class="fas fa-tag"></i> <?php echo $category; ?>
                            </h4>
                            <div class="food-grid">
                                <?php foreach ($category_foods as $food): 
                                    // Get image URL
                                    $food_image = isset($food_images[$food['item_name']]) ? $food_images[$food['item_name']] : $default_food_image;
                                ?>
                                    <div class="food-item-card">
                                        <div class="food-image-container">
                                            <?php 
                                            // Check if image file exists
                                            $image_path = dirname(__FILE__) . '/' . $food_image;
                                            if (file_exists($image_path)): 
                                            ?>
                                                <img src="<?php echo $food_image; ?>" 
                                                     alt="<?php echo htmlspecialchars($food['item_name']); ?>" 
                                                     class="food-image" 
                                                     loading="lazy"
                                                     onerror="this.onerror=null; this.parentElement.innerHTML='<i class=\"fas fa-image no-image-icon\"></i>';">
                                            <?php else: ?>
                                                <i class="fas fa-image no-image-icon"></i>
                                            <?php endif; ?>
                                            <div class="food-overlay">
                                                <?php echo $category; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="food-card-content">
                                            <div class="food-card-name"><?php echo htmlspecialchars($food['item_name']); ?></div>
                                            <div class="food-card-price">₹<?php echo number_format($food['price'], 2); ?></div>
                                            
                                            <?php if (isset($food['stock'])): ?>
                                                <div class="food-card-stock">
                                                    <i class="fas fa-box"></i> 
                                                    <?php echo $food['stock']; ?> in stock
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="food-qty-controls">
                                                <input type="number" 
                                                       id="food_<?php echo $food['f_id']; ?>" 
                                                       name="food_items[<?php echo $food['f_id']; ?>]" 
                                                       min="0" max="10" value="0"
                                                       class="food-qty-input"
                                                       onchange="updateSummary()">
                                                
                                                <div class="food-qty-buttons">
                                                    <button type="button" class="qty-btn" 
                                                            onclick="changeQty(<?php echo $food['f_id']; ?>, -1)">
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <button type="button" class="qty-btn" 
                                                            onclick="changeQty(<?php echo $food['f_id']; ?>, 1)">
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-food-message">
                        <i class="fas fa-utensils"></i>
                        <h3>Menu Currently Unavailable</h3>
                        <p>No food items are available at the moment. You can still book the room.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="notes"><i class="far fa-edit"></i> Special Notes (Optional)</label>
                <textarea id="notes" name="notes" rows="3" placeholder="Any special requests or notes..." 
                          style="width: 100%; padding: 12px 15px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: white;"></textarea>
            </div>
            
            <button type="submit" class="book-btn" id="submitBtn">
                <i class="fas fa-lock"></i> Confirm Booking
            </button>
        </form>
    </div>

    <div class="summary">
        <h2><i class="fas fa-receipt"></i> Booking Summary</h2>
        <div class="summary-item">
            <span>Room (<span id="summaryHours">2.0</span> hours)</span>
            <span>₹<span id="roomPrice"><?php echo number_format($room['price_hr'] * 2, 2); ?></span></span>
        </div>
        <div class="summary-item">
            <span>Food & Drinks</span>
            <span>₹<span id="foodTotal">0.00</span></span>
        </div>
        <div class="summary-item">
            <span>Subtotal</span>
            <span>₹<span id="subtotal"><?php echo number_format($room['price_hr'] * 2, 2); ?></span></span>
        </div>
        <div class="summary-item">
            <span>Tax (10%)</span>
            <span>₹<span id="taxAmount"><?php echo number_format($room['price_hr'] * 2 * 0.1, 2); ?></span></span>
        </div>
        <div class="summary-total">
            <span>Total Amount</span>
            <span>₹<span id="totalAmount"><?php echo number_format($room['price_hr'] * 2 * 1.1, 2); ?></span></span>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 10px;">
            <h3 style="color: var(--highlight); margin-bottom: 10px; font-size: 16px;">
                <i class="fas fa-info-circle"></i> Payment Information
            </h3>
            <p style="color: rgba(255,255,255,0.7); font-size: 14px; line-height: 1.5;">
                Your booking will be confirmed immediately. Payment can be made at the venue when you arrive.
            </p>
        </div>
    </div>
</div>

<script>
    const roomPricePerHour = <?php echo $room['price_hr']; ?>;
    const foodPrices = <?php echo json_encode(array_column($foods, 'price', 'f_id')); ?>;
    
    function switchFoodTab(category) {
        // Update tabs
        document.querySelectorAll('.food-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelectorAll('.food-category-content').forEach(content => {
            content.classList.remove('active');
        });
        
        // Activate selected tab
        const tabButton = Array.from(document.querySelectorAll('.food-tab')).find(
            tab => tab.textContent === category
        );
        if (tabButton) tabButton.classList.add('active');
        
        const contentDiv = document.getElementById(`food-category-${category.toLowerCase().replace(' ', '-')}`);
        if (contentDiv) contentDiv.classList.add('active');
    }
    
    function changeQty(foodId, delta) {
        const input = document.getElementById(`food_${foodId}`);
        let currentQty = parseInt(input.value) || 0;
        let newQty = currentQty + delta;
        
        if (newQty >= 0 && newQty <= 10) {
            input.value = newQty;
            updateSummary();
            
            // Add visual feedback
            input.style.backgroundColor = newQty > 0 ? 'rgba(233, 69, 96, 0.2)' : '';
            input.style.borderColor = newQty > 0 ? 'var(--highlight)' : '';
        }
    }
    
    function calculateHours() {
        const date = document.getElementById('booking_date').value;
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        
        if (date && startTime && endTime) {
            const start = new Date(`${date}T${startTime}`);
            const end = new Date(`${date}T${endTime}`);
            
            // Handle overnight bookings (after midnight)
            if (end < start) {
                end.setDate(end.getDate() + 1);
            }
            
            const hours = (end - start) / (1000 * 60 * 60);
            document.getElementById('hoursDisplay').textContent = `Duration: ${hours.toFixed(1)} hours`;
            document.getElementById('summaryHours').textContent = hours.toFixed(1);
            updateSummary();
        }
    }
    
    function updateSummary() {
        // Calculate room amount
        const date = document.getElementById('booking_date').value;
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        
        let hours = 2.0; // Default
        if (date && startTime && endTime) {
            const start = new Date(`${date}T${startTime}`);
            const end = new Date(`${date}T${endTime}`);
            if (end < start) end.setDate(end.getDate() + 1);
            hours = (end - start) / (1000 * 60 * 60);
        }
        
        const roomAmount = roomPricePerHour * hours;
        document.getElementById('roomPrice').textContent = roomAmount.toFixed(2);
        
        // Calculate food amount
        let foodTotal = 0;
        const foodInputs = document.querySelectorAll('input[name^="food_items"]');
        foodInputs.forEach(input => {
            const foodId = input.name.match(/\[(\d+)\]/)[1];
            const qty = parseInt(input.value) || 0;
            const price = foodPrices[foodId] || 0;
            foodTotal += qty * price;
        });
        
        document.getElementById('foodTotal').textContent = foodTotal.toFixed(2);
        
        // Calculate totals
        const subtotal = roomAmount + foodTotal;
        const taxAmount = subtotal * 0.10;
        const totalAmount = subtotal + taxAmount;
        
        document.getElementById('subtotal').textContent = subtotal.toFixed(2);
        document.getElementById('taxAmount').textContent = taxAmount.toFixed(2);
        document.getElementById('totalAmount').textContent = totalAmount.toFixed(2);
    }
    
    // Event listeners
    document.getElementById('booking_date').addEventListener('change', calculateHours);
    document.getElementById('start_time').addEventListener('change', calculateHours);
    document.getElementById('end_time').addEventListener('change', calculateHours);
    
    // Initialize
    calculateHours();
    
    // Form validation
    document.getElementById('bookingForm').addEventListener('submit', function(e) {
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        
        if (!startTime || !endTime) {
            e.preventDefault();
            alert('Please select both start and end times');
            return;
        }
        
        const date = document.getElementById('booking_date').value;
        const start = new Date(`${date}T${startTime}`);
        const end = new Date(`${date}T${endTime}`);
        if (end < start) end.setDate(end.getDate() + 1);
        
        const hours = (end - start) / (1000 * 60 * 60);
        
        if (hours < 1) {
            e.preventDefault();
            alert('Minimum booking duration is 1 hour');
            return;
        }
        
        if (hours > 6) {
            e.preventDefault();
            alert('Maximum booking duration is 6 hours');
            return;
        }
        
        // Disable button to prevent double submission
        const submitBtn = document.getElementById('submitBtn');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    });
</script>

</body>
</html>