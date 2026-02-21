<?php
session_start();
include "../db.php";

// Only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Store success/error messages in session for modal display
$show_modal = false;
$modal_type = '';
$modal_message = '';

if (isset($_SESSION['success'])) {
    $show_modal = true;
    $modal_type = 'success';
    $modal_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $show_modal = true;
    $modal_type = 'error';
    $modal_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

// ===== DELETE operations =====
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    $conn->query("DELETE FROM user_tbl WHERE id=$id");
    $_SESSION['success'] = "User deleted successfully!";
    header("Location: admindash.php");
    exit;
}

if (isset($_GET['delete_room'])) {
    $id = (int)$_GET['delete_room'];
    $conn->query("DELETE FROM room WHERE r_id=$id");
    $_SESSION['success'] = "Room deleted successfully!";
    header("Location: admindash.php");
    exit;
}

if (isset($_GET['delete_food'])) {
    $id = (int)$_GET['delete_food'];
    $conn->query("DELETE FROM food_beverages WHERE f_id=$id");
    $_SESSION['success'] = "Food item deleted successfully!";
    header("Location: admindash.php");
    exit;
}

if (isset($_GET['delete_extra'])) {
    $id = (int)$_GET['delete_extra'];
    $conn->query("DELETE FROM extra_expense WHERE e_id=$id");
    $_SESSION['success'] = "Extra service deleted successfully!";
    header("Location: admindash.php");
    exit;
}

if (isset($_GET['delete_booking'])) {
    $id = (int)$_GET['delete_booking'];
    
    // First delete related food orders
    $conn->query("DELETE FROM booking_food WHERE b_id=$id");
    // Then delete the booking
    $conn->query("DELETE FROM booking WHERE b_id=$id");
    
    $_SESSION['success'] = "Booking deleted successfully!";
    header("Location: admindash.php");
    exit;
}

// Delete food order from booking_food
if (isset($_GET['delete_booking_food'])) {
    $id = (int)$_GET['delete_booking_food'];
    $bf_id = $id;
    
    // Get booking and food info before deleting
    $order_info = $conn->query("SELECT bf.*, f.price, f.stock as current_stock FROM booking_food bf JOIN food_beverages f ON bf.f_id = f.f_id WHERE bf.bf_id = $bf_id")->fetch_assoc();
    
    if ($order_info) {
        $b_id = $order_info['b_id'];
        $f_id = $order_info['f_id'];
        $quantity = $order_info['quantity'];
        $total_to_deduct = $order_info['quantity'] * $order_info['price'];
        
        // Delete the order
        $conn->query("DELETE FROM booking_food WHERE bf_id=$bf_id");
        
        // Update stock - add back the quantity
        $conn->query("UPDATE food_beverages SET stock = stock + $quantity WHERE f_id = $f_id");
        
        // Update booking total
        $conn->query("UPDATE booking SET total_amount = total_amount - $total_to_deduct WHERE b_id = $b_id");
        
        $_SESSION['success'] = "Food item removed from order successfully!";
    } else {
        $_SESSION['error'] = "Order item not found!";
    }
    
    header("Location: admindash.php#food-orders");
    exit;
}

// Delete entire food order (all items for a booking)
if (isset($_GET['delete_whole_order'])) {
    $b_id = (int)$_GET['delete_whole_order'];
    
    // Get all food items for this booking to restore stock
    $food_items = $conn->query("SELECT bf.*, f.stock as current_stock FROM booking_food bf JOIN food_beverages f ON bf.f_id = f.f_id WHERE bf.b_id = $b_id");
    
    $total_deduct = 0;
    while ($item = $food_items->fetch_assoc()) {
        $quantity = $item['quantity'];
        $f_id = $item['f_id'];
        
        // Restore stock for each item
        $conn->query("UPDATE food_beverages SET stock = stock + $quantity WHERE f_id = $f_id");
        $total_deduct += $item['quantity'] * $item['price'];
    }
    
    // Delete all food items for this booking
    $conn->query("DELETE FROM booking_food WHERE b_id=$b_id");
    
    // Update booking total (remove food amount)
    $conn->query("UPDATE booking SET total_amount = total_amount - $total_deduct WHERE b_id = $b_id");
    
    $_SESSION['success'] = "Complete food order for Booking #$b_id has been deleted!";
    header("Location: admindash.php#food-orders");
    exit;
}

// ===== UPDATE FOOD ORDER SERVED STATUS (WHOLE ORDER) =====
if (isset($_POST['update_food_order_status'])) {
    $b_id = (int)$_POST['b_id'];
    $served_status = $_POST['served_status'];
    
    // Update all food items in this booking with the same status
    $stmt = $conn->prepare("UPDATE booking_food SET served = ? WHERE b_id = ?");
    $stmt->bind_param("si", $served_status, $b_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Food order status for Booking #$b_id updated to: $served_status";
    } else {
        $_SESSION['error'] = "Failed to update food order status: " . $conn->error;
    }
    
    $stmt->close();
    header("Location: admindash.php#food-orders");
    exit;
}

// ===== ADD operations =====
if (isset($_POST['add_room'])) {
    $room_name = $_POST['room_name'];
    $capacity = $_POST['capacity'];
    $price = $_POST['price'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("INSERT INTO room (room_name, capcity, price_hr, status) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdis", $room_name, $capacity, $price, $status);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Room added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add room: " . $conn->error;
    }
    $stmt->close();
    header("Location: admindash.php");
    exit;
}

if (isset($_POST['add_extra'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];

    $stmt = $conn->prepare("INSERT INTO extra_expense (name, description, price) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $name, $description, $price);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Extra service added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add extra service: " . $conn->error;
    }
    $stmt->close();
    header("Location: admindash.php");
    exit;
}

// Add food item to booking_food
if (isset($_POST['add_booking_food'])) {
    $b_id = (int)$_POST['b_id'];
    $f_id = (int)$_POST['f_id'];
    $quantity = (int)$_POST['quantity'];
    
    // Get food price and check stock
    $food_result = $conn->query("SELECT price, item_name, stock FROM food_beverages WHERE f_id = $f_id");
    $food = $food_result->fetch_assoc();
    $price = $food['price'];
    $total = $price * $quantity;
    $current_stock = $food['stock'];
    
    if ($current_stock >= $quantity) {
        // Insert into booking_food with served status default 'pending'
        $served = 'pending';
        $stmt = $conn->prepare("INSERT INTO booking_food (b_id, f_id, quantity, price, served) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiids", $b_id, $f_id, $quantity, $price, $served);
        
        if ($stmt->execute()) {
            // Update stock
            $new_stock = $current_stock - $quantity;
            $conn->query("UPDATE food_beverages SET stock = $new_stock WHERE f_id = $f_id");
            
            // Update booking total
            $conn->query("UPDATE booking SET total_amount = total_amount + $total WHERE b_id = $b_id");
            
            $_SESSION['success'] = "Food item added to order successfully!";
        } else {
            $_SESSION['error'] = "Failed to add food item: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Insufficient stock! Available: $current_stock";
    }
    
    header("Location: admindash.php#food-orders");
    exit;
}

// ===== UPDATE operations =====
if (isset($_POST['update_room'])) {
    $room_id = $_POST['room_id'];
    $room_name = $_POST['room_name'];
    $capacity = $_POST['capacity'];
    $price = $_POST['price'];
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE room SET room_name=?, capcity=?, price_hr=?, status=? WHERE r_id=?");
    $stmt->bind_param("sdisi", $room_name, $capacity, $price, $status, $room_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Room updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update room: " . $conn->error;
    }
    $stmt->close();
    header("Location: admindash.php");
    exit;
}

if (isset($_POST['update_food'])) {
    $food_id = $_POST['food_id'];
    $stock = $_POST['stock'];

    $stmt = $conn->prepare("UPDATE food_beverages SET stock=? WHERE f_id=?");
    $stmt->bind_param("ii", $stock, $food_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Food stock updated successfully!";
    } else {
        $_SESSION['error'] = "Failed to update food stock: " . $conn->error;
    }
    $stmt->close();
    header("Location: admindash.php");
    exit;
}

// ===== BOOKING STATUS UPDATE =====
if (isset($_POST['update_booking_status'])) {
    $b_id = (int)$_POST['b_id'];
    $status = trim($_POST['status']);
    
    // Determine payment status based on booking status
    $payment_status = 'pending';
    if ($status == 'Completed') {
        $payment_status = 'paid';
    } elseif ($status == 'Cancelled') {
        $payment_status = 'cancelled';
    } elseif ($status == 'Approved') {
        $payment_status = 'pending';
    }
    
    // Update both status and payment_status in one query
    $stmt = $conn->prepare("UPDATE booking SET status = ?, payment_status = ? WHERE b_id = ?");
    $stmt->bind_param("ssi", $status, $payment_status, $b_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Booking #$b_id status updated to: $status";
    } else {
        $_SESSION['error'] = "Failed to update booking status: " . $conn->error;
    }
    
    $stmt->close();
    header("Location: admindash.php");
    exit;
}

// Initialize default KTV food items if table is empty
$food_count = $conn->query("SELECT COUNT(*) as count FROM food_beverages")->fetch_assoc()['count'];
if ($food_count == 0) {
    $default_foods = [
        // Appetizers (10 items)
        ['Chicken Popcorn', 'Appetizer', 250.00, 50],
        ['French Fries', 'Appetizer', 180.00, 60],
        ['Spring Rolls (Veg)', 'Appetizer', 220.00, 40],
        ['Cheese Balls', 'Appetizer', 200.00, 45],
        ['Garlic Bread', 'Appetizer', 160.00, 55],
        ['Chicken Wings', 'Appetizer', 320.00, 35],
        ['Paneer Tikka', 'Appetizer', 280.00, 40],
        ['Potato Wedges', 'Appetizer', 190.00, 50],
        ['Chicken Lollipop', 'Appetizer', 300.00, 30],
        ['Crispy Corn', 'Appetizer', 210.00, 45],
        
        // Main Course (10 items)
        ['Chicken Biryani', 'Main Course', 350.00, 30],
        ['Butter Chicken', 'Main Course', 380.00, 25],
        ['Paneer Butter Masala', 'Main Course', 320.00, 35],
        ['Fish & Chips', 'Main Course', 400.00, 20],
        ['Margherita Pizza', 'Main Course', 450.00, 25],
        ['Chicken Fried Rice', 'Main Course', 330.00, 30],
        ['Veg Hakka Noodles', 'Main Course', 280.00, 40],
        ['Grilled Chicken', 'Main Course', 420.00, 20],
        ['Chicken Shawarma', 'Main Course', 280.00, 35],
        ['Mutton Rogan Josh', 'Main Course', 450.00, 15],
        
        // Snacks (10 items)
        ['Chicken Burger', 'Snacks', 280.00, 40],
        ['Veg Sandwich', 'Snacks', 200.00, 50],
        ['French Toast', 'Snacks', 220.00, 35],
        ['Nachos with Cheese', 'Snacks', 300.00, 30],
        ['Samosa Plate', 'Snacks', 180.00, 45],
        ['Chicken Wrap', 'Snacks', 260.00, 35],
        ['Cheese Pizza Slice', 'Snacks', 180.00, 50],
        ['Masala Fries', 'Snacks', 210.00, 40],
        ['Paneer Tikka Sandwich', 'Snacks', 240.00, 30],
        ['Chicken Hot Dog', 'Snacks', 220.00, 40],
        
        // Beverages (Non-Alcoholic) (10 items)
        ['Coca-Cola (500ml)', 'Beverage', 80.00, 100],
        ['Fresh Lime Soda', 'Beverage', 100.00, 80],
        ['Iced Tea', 'Beverage', 120.00, 70],
        ['Virgin Mojito', 'Beverage', 150.00, 60],
        ['Hot Coffee', 'Beverage', 90.00, 90],
        ['Green Tea', 'Beverage', 70.00, 85],
        ['Fresh Orange Juice', 'Beverage', 130.00, 55],
        ['Mango Shake', 'Beverage', 160.00, 45],
        ['Pepsi (500ml)', 'Beverage', 80.00, 95],
        ['Mineral Water', 'Beverage', 50.00, 120],
        
        // Alcoholic Drinks (10 items)
        ['Beer (Pint)', 'Alcoholic', 250.00, 80],
        ['Whisky (60ml)', 'Alcoholic', 350.00, 60],
        ['Vodka (60ml)', 'Alcoholic', 320.00, 65],
        ['Red Wine (Glass)', 'Alcoholic', 280.00, 40],
        ['Rum (60ml)', 'Alcoholic', 300.00, 55],
        ['Tequila Shot', 'Alcoholic', 200.00, 70],
        ['White Wine (Glass)', 'Alcoholic', 290.00, 35],
        ['Gin (60ml)', 'Alcoholic', 340.00, 50],
        ['Brandy (60ml)', 'Alcoholic', 320.00, 45],
        ['Champagne (Glass)', 'Alcoholic', 400.00, 30],
        
        // Desserts (6 items)
        ['Chocolate Brownie', 'Dessert', 180.00, 40],
        ['Ice Cream Sundae', 'Dessert', 220.00, 35],
        ['Cheesecake Slice', 'Dessert', 250.00, 30],
        ['Chocolate Mousse', 'Dessert', 200.00, 45],
        ['Fruit Salad', 'Dessert', 150.00, 50],
        ['Gulab Jamun', 'Dessert', 120.00, 60]
    ];
    
    foreach ($default_foods as $food) {
        $stmt = $conn->prepare("INSERT INTO food_beverages (item_name, category, price, stock) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssdi", $food[0], $food[1], $food[2], $food[3]);
        $stmt->execute();
        $stmt->close();
    }
}

// Check if 'served' column exists in booking_food table, if not add it
$check_column = $conn->query("SHOW COLUMNS FROM booking_food LIKE 'served'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE booking_food ADD COLUMN served ENUM('pending', 'served', 'cancelled') DEFAULT 'pending'");
}

// ===== FETCH data =====
$users = $conn->query("SELECT * FROM user_tbl ORDER BY id DESC");
$rooms = $conn->query("SELECT * FROM room ORDER BY r_id DESC");
$foods = $conn->query("SELECT * FROM food_beverages ORDER BY category, item_name ASC");
$extras = $conn->query("SELECT * FROM extra_expense ORDER BY e_id DESC");
$bookings = $conn->query("
    SELECT b.*, u.name as user_name, u.id as user_id, r.room_name 
    FROM booking b 
    JOIN user_tbl u ON b.u_id = u.id 
    JOIN room r ON b.r_id = r.r_id 
    ORDER BY b.booking_date DESC, b.b_id DESC
");

// Get counts
$users_count = $users->num_rows;
$rooms_count = $rooms->num_rows;
$foods_count = $foods->num_rows;
$extras_count = $extras->num_rows;
$bookings_count = $bookings->num_rows;

// Stats
$pending_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE status = 'Pending'")->fetch_assoc()['count'];
$approved_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE status = 'Approved'")->fetch_assoc()['count'];
$today_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE DATE(booking_date) = CURDATE()")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(total_amount) as total FROM booking WHERE status = 'Completed'")->fetch_assoc()['total'];
$total_revenue = $total_revenue ? $total_revenue : 0;

// Get all food orders for bookings using booking_food table
$food_orders = [];
$bookings_with_food_count = 0; // Counter for bookings that have food items
$orders_result = $conn->query("
    SELECT bf.*, f.item_name, f.category, b.b_id, r.room_name, u.name as user_name,
           b.booking_date, b.start_time, b.end_time, b.total_amount
    FROM booking_food bf 
    JOIN food_beverages f ON bf.f_id = f.f_id 
    JOIN booking b ON bf.b_id = b.b_id
    JOIN room r ON b.r_id = r.r_id
    JOIN user_tbl u ON b.u_id = u.id
    ORDER BY bf.bf_id DESC
");

$unique_bookings = [];
while ($row = $orders_result->fetch_assoc()) {
    $food_orders[$row['b_id']][] = $row;
    if (!in_array($row['b_id'], $unique_bookings)) {
        $unique_bookings[] = $row['b_id'];
        $bookings_with_food_count++;
    }
}

// Get order status for each booking (all items should have same status ideally, but we'll check)
$booking_statuses = [];
foreach ($food_orders as $b_id => $items) {
    $statuses = array_column($items, 'served');
    $unique_statuses = array_unique($statuses);
    if (count($unique_statuses) == 1) {
        $booking_statuses[$b_id] = $items[0]['served']; // All items have same status
    } else {
        $booking_statuses[$b_id] = 'mixed'; // Mixed statuses
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard | Sirene_KTV</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
:root {
    --primary: #0f172a;        /* Dark blue - main background */
    --secondary: #1e293b;      /* Slightly lighter blue - cards/sidebar */
    --accent: #334155;         /* Medium blue - borders/hover states */
    --highlight: #f43f5e;      /* Rose/red - primary accent */
    --highlight-light: #fb7185; /* Light rose - hover states */
    --success: #10b981;        /* Emerald green - success states */
    --info: #3b82f6;           /* Blue - info/primary buttons */
    --warning: #f59e0b;        /* Amber - warning states */
    --danger: #ef4444;         /* Red - delete/danger states */
    --purple: #8b5cf6;         /* Purple - special/featured items */
    --light: #f8fafc;          /* Off-white - text */
    --light-dim: #94a3b8;      /* Dimmed white - secondary text */
    --dark: #020617;           /* Almost black - deep accents */
    
    /* Status colors */
    --served: #10b981;         /* Emerald - served items */
    --pending: #f59e0b;        /* Amber - pending items */
    --cancelled: #64748b;      /* Slate - cancelled items (neutral) */
    --mixed: #8b5cf6;          /* Purple - mixed status */
    
    /* Order section specific */
    --order-bg: #1e293b;       /* Secondary background */
    --order-item: #2d3a4f;     /* Item background */
    --order-accent: #f43f5e;   /* Rose accent */
    --order-highlight: #fb7185; /* Light rose */
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, var(--primary), #0b1120);
    color: var(--light);
    min-height: 100vh;
}

header {
    background: rgba(15, 23, 42, 0.95);
    padding: 20px 40px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
    border-bottom: 3px solid var(--highlight);
}

.header-left h1 {
    font-size: 28px;
    background: linear-gradient(90deg, var(--highlight), #818cf8);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-bottom: 5px;
}

.header-left p {
    color: var(--light-dim);
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
    color: var(--light);
}

.logout-btn {
    background: linear-gradient(135deg, var(--highlight), #ef4444);
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
}

.logout-btn:hover {
    background: linear-gradient(135deg, #ef4444, var(--highlight));
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(244, 63, 94, 0.4);
}

/* Dashboard Container */
.dashboard-container {
    display: flex;
    min-height: calc(100vh - 100px);
}

/* Sidebar */
.sidebar {
    width: 250px;
    background: rgba(30, 41, 59, 0.9);
    padding: 25px 15px;
    box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
    position: sticky;
    top: 0;
    height: calc(100vh - 100px);
    overflow-y: auto;
    backdrop-filter: blur(10px);
}

.sidebar-title {
    color: var(--highlight);
    margin-bottom: 25px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-align: center;
    font-size: 20px;
}

.menu-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 15px;
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
    font-size: 16px;
}

.menu-item:hover {
    background: rgba(244, 63, 94, 0.1);
    color: var(--highlight-light);
    transform: translateX(5px);
}

.menu-item.active {
    background: linear-gradient(135deg, var(--highlight), var(--highlight-light));
    color: white;
    box-shadow: 0 4px 12px rgba(244, 63, 94, 0.3);
}

.menu-item i {
    width: 20px;
    text-align: center;
}

.menu-item.booking {
    margin-top: 20px;
    background: rgba(59, 130, 246, 0.1);
}

.menu-item.booking.active {
    background: linear-gradient(135deg, var(--info), #60a5fa);
}

.menu-item.food-orders {
    background: rgba(139, 92, 246, 0.1);
    color: var(--purple);
}

.menu-item.food-orders.active {
    background: linear-gradient(135deg, var(--purple), #a78bfa);
    color: white;
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

.add-btn {
    background: var(--success);
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.add-btn:hover {
    background: #0ca678;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

/* Stats Cards */
.stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 15px;
    text-align: center;
    transition: all 0.3s;
    border-left: 5px solid var(--highlight);
    backdrop-filter: blur(5px);
}

.stat-card:hover {
    background: rgba(255, 255, 255, 0.08);
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
    color: var(--light);
}

.stat-card p {
    color: var(--light-dim);
    font-size: 14px;
}

/* Quick Stats */
.quick-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.stat-box {
    background: rgba(255, 255, 255, 0.05);
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    border-left: 4px solid var(--highlight);
    backdrop-filter: blur(5px);
}

.stat-box h4 {
    font-size: 24px;
    margin: 10px 0 5px;
    color: var(--highlight);
}

.stat-box p {
    font-size: 12px;
    color: var(--light-dim);
}

/* Tables */
.table-container {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    overflow: hidden;
    margin-top: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
    backdrop-filter: blur(5px);
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead {
    background: var(--accent);
}

th {
    padding: 18px 15px;
    text-align: left;
    font-weight: 600;
    color: var(--light);
    border-bottom: 2px solid rgba(255, 255, 255, 0.1);
}

td {
    padding: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    color: var(--light);
}

tbody tr {
    transition: all 0.3s;
}

tbody tr:hover {
    background: rgba(244, 63, 94, 0.05);
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-edit, .btn-delete, .btn-view, .btn-food, .btn-served {
    padding: 6px 12px;
    border-radius: 5px;
    border: none;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-edit {
    background: rgba(59, 130, 246, 0.15);
    color: var(--info);
}

.btn-edit:hover {
    background: var(--info);
    color: white;
}

.btn-delete {
    background: rgba(239, 68, 68, 0.15);
    color: var(--danger);
}

.btn-delete:hover {
    background: var(--danger);
    color: white;
}

.btn-view {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
}

.btn-view:hover {
    background: var(--success);
    color: white;
}

.btn-food {
    background: rgba(139, 92, 246, 0.15);
    color: var(--purple);
}

.btn-food:hover {
    background: var(--purple);
    color: white;
}

.btn-served {
    background: rgba(16, 185, 129, 0.15);
    color: var(--served);
}

.btn-served:hover {
    background: var(--served);
    color: white;
}

/* Food Orders Section - Updated with new color scheme */
.food-orders-section {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 2px dashed var(--order-accent);
}

.food-orders-section h3 {
    color: var(--light);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 26px;
}

.food-orders-section h3 i {
    background: linear-gradient(135deg, var(--purple), var(--info));
    color: white;
    padding: 10px;
    border-radius: 50%;
}

.orders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
    gap: 25px;
    margin-top: 20px;
}

.room-food-card {
    background: var(--order-bg);
    border-radius: 15px;
    overflow: hidden;
    border-left: 5px solid var(--order-accent);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    transition: transform 0.3s;
    backdrop-filter: blur(5px);
}

.room-food-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 25px rgba(244, 63, 94, 0.2);
}

.order-header {
    background: linear-gradient(135deg, var(--accent), #2d3a4f);
    padding: 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 2px solid var(--order-accent);
}

.order-header h4 {
    font-size: 18px;
    color: var(--light);
}

.order-header h4 i {
    color: var(--order-accent);
    margin-right: 8px;
}

.order-header .room-badge {
    background: linear-gradient(135deg, var(--order-accent), var(--order-highlight));
    color: white;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.order-header .order-status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    margin-left: 10px;
}

.order-status-badge.served {
    background: rgba(16, 185, 129, 0.15);
    color: var(--served);
    border: 1px solid var(--served);
}

.order-status-badge.pending {
    background: rgba(245, 158, 11, 0.15);
    color: var(--pending);
    border: 1px solid var(--pending);
}

.order-status-badge.cancelled {
    background: rgba(100, 116, 139, 0.15);
    color: var(--cancelled);
    border: 1px solid var(--cancelled);
}

.order-status-badge.mixed {
    background: rgba(139, 92, 246, 0.15);
    color: var(--mixed);
    border: 1px solid var(--mixed);
}

.order-items {
    padding: 18px;
    max-height: 300px;
    overflow-y: auto;
}

.order-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--order-item);
    border-radius: 8px;
    margin-bottom: 8px;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.2s;
}

.order-item:hover {
    background: #35445c;
}

.order-item.served {
    border-left: 5px solid var(--served);
}

.order-item.pending {
    border-left: 5px solid var(--pending);
}

.order-item.cancelled {
    border-left: 5px solid var(--cancelled);
    opacity: 0.7;
}

.order-item-info {
    display: flex;
    flex-direction: column;
    flex: 1;
}

.order-item-name {
    font-weight: 600;
    color: var(--light);
    display: flex;
    align-items: center;
    gap: 8px;
}

.order-item-details {
    font-size: 12px;
    color: var(--light-dim);
}

.served-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 9px;
    font-weight: 600;
    margin-left: 5px;
}

.served-badge.served {
    background: rgba(16, 185, 129, 0.15);
    color: var(--served);
    border: 1px solid var(--served);
}

.served-badge.pending {
    background: rgba(245, 158, 11, 0.15);
    color: var(--pending);
    border: 1px solid var(--pending);
}

.served-badge.cancelled {
    background: rgba(100, 116, 139, 0.15);
    color: var(--cancelled);
    border: 1px solid var(--cancelled);
}

.order-item-price {
    font-weight: 600;
    color: var(--order-accent);
    margin: 0 15px;
    min-width: 80px;
    text-align: right;
}

.order-item-actions {
    display: flex;
    gap: 5px;
}

.order-total {
    padding: 15px 18px;
    background: rgba(0, 0, 0, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    border-top: 2px solid var(--order-accent);
    font-size: 16px;
}

.order-total span:first-child {
    color: var(--light-dim);
}

.order-total span:last-child {
    color: var(--order-accent);
    font-size: 20px;
}

.order-footer {
    padding: 12px 18px;
    background: rgba(0, 0, 0, 0.2);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    flex-wrap: wrap;
    gap: 10px;
}

.booking-info {
    font-size: 12px;
    color: var(--light-dim);
}

.booking-info i {
    margin-right: 5px;
    color: var(--order-accent);
}

.update-order-status {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.update-order-status select {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 6px 10px;
    border-radius: 5px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.2s;
}

.update-order-status select:hover {
    background: rgba(255, 255, 255, 0.15);
}

.update-order-status select.served {
    border-color: var(--served);
}

.update-order-status select.pending {
    border-color: var(--pending);
}

.update-order-status select.cancelled {
    border-color: var(--cancelled);
}

.update-order-status select.mixed {
    border-color: var(--mixed);
}

.update-order-status select option {
    background: var(--order-bg);
    color: white;
}

.update-order-status button {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
    border: none;
    padding: 6px 15px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.update-order-status button:hover {
    background: linear-gradient(135deg, #2563eb, var(--info));
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
}

.btn-delete-order {
    background: rgba(239, 68, 68, 0.15);
    color: var(--danger);
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.btn-delete-order:hover {
    background: var(--danger);
    color: white;
    transform: translateY(-2px);
}

.no-orders {
    padding: 50px;
    text-align: center;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 15px;
    border: 2px dashed var(--purple);
}

.no-orders i {
    font-size: 60px;
    color: var(--purple);
    margin-bottom: 20px;
    opacity: 0.5;
}

.no-orders p {
    color: var(--light-dim);
    font-size: 18px;
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
    animation: fadeInModal 0.3s ease;
}

@keyframes fadeInModal {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    margin: 50px auto;
    padding: 30px;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
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

.modal-header h3 {
    color: var(--highlight);
    font-size: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-modal {
    background: transparent;
    border: none;
    color: var(--light-dim);
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

/* Modal Forms */
.modal-form {
    padding: 20px 0;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--light-dim);
    font-weight: 500;
    font-size: 14px;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: var(--light);
    font-size: 15px;
    transition: all 0.3s;
}

.form-group select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 12px;
    padding-right: 40px;
}

.form-group select option {
    background-color: var(--secondary) !important;
    color: white !important;
    padding: 10px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--highlight);
    background: rgba(255, 255, 255, 0.12);
    box-shadow: 0 0 0 2px rgba(244, 63, 94, 0.2);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-submit {
    background: linear-gradient(135deg, var(--success), #0ca678);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    min-width: 120px;
    justify-content: center;
}

.btn-submit:hover {
    background: linear-gradient(135deg, #0ca678, var(--success));
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
}

.btn-cancel {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    min-width: 120px;
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.status-badge {
    padding: 6px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.status-pending {
    background: rgba(245, 158, 11, 0.15);
    color: var(--pending);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-approved {
    background: rgba(59, 130, 246, 0.15);
    color: var(--info);
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.status-completed {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-cancelled {
    background: rgba(100, 116, 139, 0.15);
    color: var(--cancelled);
    border: 1px solid rgba(100, 116, 139, 0.3);
}

/* Notification Modal */
.notification-modal .modal-content {
    max-width: 400px;
}

.notification-success {
    border-color: var(--success) !important;
}

.notification-error {
    border-color: var(--danger) !important;
}

.notification-icon {
    font-size: 60px;
    margin: 20px 0;
}

.notification-success .notification-icon {
    color: var(--success);
}

.notification-error .notification-icon {
    color: var(--danger);
}

.notification-message {
    font-size: 18px;
    margin: 20px 0;
    color: var(--light);
}

.notification-actions {
    margin-top: 20px;
}

.btn-ok {
    background: linear-gradient(135deg, var(--info), #60a5fa);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-ok:hover {
    background: linear-gradient(135deg, #2563eb, var(--info));
    transform: translateY(-2px);
}

/* Booking Details */
.booking-details {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 10px;
    margin: 15px 0;
}

.booking-details-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

.booking-detail {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.booking-detail label {
    color: var(--light-dim);
    font-size: 13px;
}

.booking-detail span {
    color: var(--light);
    font-weight: 600;
    font-size: 15px;
}

.status-select {
    width: 100%;
    padding: 12px 40px 12px 15px;
    background: rgba(255, 255, 255, 0.08) url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e") no-repeat right 15px center / 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: white !important;
    font-size: 15px;
    margin: 15px 0;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.status-select option {
    background-color: var(--secondary) !important;
    color: white !important;
    padding: 10px;
}

.status-select:focus {
    outline: none;
    border-color: var(--highlight);
}

/* Room Status Colors */
.room-status {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.room-status.available {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.room-status.occupied {
    background: rgba(244, 63, 94, 0.15);
    color: var(--highlight);
    border: 1px solid rgba(244, 63, 94, 0.3);
}

.room-status.maintenance {
    background: rgba(245, 158, 11, 0.15);
    color: var(--pending);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

/* Responsive */
@media (max-width: 1024px) {
    .dashboard-container {
        flex-direction: column;
    }
    
    .sidebar {
        width: 100%;
        display: flex;
        overflow-x: auto;
        padding: 15px;
        height: auto;
        position: relative;
    }
    
    .menu-item {
        flex: 0 0 auto;
        white-space: nowrap;
    }
}

@media (max-width: 768px) {
    .stats-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .rooms-gallery {
        grid-template-columns: 1fr;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    header {
        flex-direction: column;
        gap: 15px;
        text-align: center;
    }
    
    .booking-details-row {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 20px auto;
        padding: 20px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 5px;
    }
    
    .btn-edit, .btn-delete, .btn-view, .btn-food {
        width: 100%;
        justify-content: center;
    }
    
    .orders-grid {
        grid-template-columns: 1fr;
    }
    
    .order-footer {
        flex-direction: column;
        gap: 10px;
    }
    
    .update-order-status {
        width: 100%;
    }
    
    .update-order-status select {
        flex: 1;
    }
}

@media (max-width: 480px) {
    .stats-cards, .quick-stats {
        grid-template-columns: 1fr;
    }
    
    .modal-actions {
        flex-direction: column;
    }
    
    .btn-cancel, .btn-submit {
        width: 100%;
    }
}
</style>
</head>
<body>

<header>
    <div class="header-left">
        <h1><i class="fas fa-crown"></i> Sirene KTV Admin Dashboard</h1>
        <p>Complete Management System</p>
    </div>
    <div class="header-right">
        <div class="welcome-message">
            <i class="fas fa-user-shield"></i> Admin Panel
        </div>
        <form action="logout.php" method="post">
            <button type="submit" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </button>
        </form>
    </div>
</header>

<div class="dashboard-container">
    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <h3 class="sidebar-title">Navigation Menu</h3>
        <button class="menu-item active" onclick="showSection('dashboard')">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </button>
        <button class="menu-item" onclick="showSection('users')">
            <i class="fas fa-users"></i> Manage Users
        </button>
        <button class="menu-item" onclick="showSection('rooms')">
            <i class="fas fa-door-closed"></i> Manage Rooms
        </button>
        <button class="menu-item" onclick="showSection('foods')">
            <i class="fas fa-utensils"></i> Food & Beverages
        </button>
        <button class="menu-item" onclick="showSection('extras')">
            <i class="fas fa-star"></i> Extra Services
        </button>
        <button class="menu-item booking" onclick="showSection('bookings')">
            <i class="fas fa-calendar-check"></i> Bookings
        </button>
        <button class="menu-item food-orders" onclick="showSection('food-orders')">
            <i class="fas fa-hamburger"></i> Food Orders
        </button>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Dashboard Section -->
        <div id="dashboard" class="content-section active">
            <div class="section-header">
                <h2><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h2>
                <div class="welcome-message">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                </div>
            </div>
            
            <!-- Booking Stats -->
            <div class="quick-stats">
                <div class="stat-box">
                    <i class="fas fa-clock"></i>
                    <h4><?php echo $pending_bookings; ?></h4>
                    <p>Pending Bookings</p>
                </div>
                <div class="stat-box">
                    <i class="fas fa-check-circle"></i>
                    <h4><?php echo $approved_bookings; ?></h4>
                    <p>Approved Bookings</p>
                </div>
                <div class="stat-box">
                    <i class="fas fa-calendar-day"></i>
                    <h4><?php echo $today_bookings; ?></h4>
                    <p>Today's Bookings</p>
                </div>
                <div class="stat-box">
                    <i class="fas fa-money-bill-wave"></i>
                    <h4>₹<?php echo number_format($total_revenue, 2); ?></h4>
                    <p>Total Revenue</p>
                </div>
            </div>
            
            <div class="stats-cards">
                <div class="stat-card">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $users_count; ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-door-closed"></i>
                    <h3><?php echo $rooms_count; ?></h3>
                    <p>Total Rooms</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-utensils"></i>
                    <h3><?php echo $foods_count; ?></h3>
                    <p>Menu Items</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-star"></i>
                    <h3><?php echo $extras_count; ?></h3>
                    <p>Extra Services</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-hamburger"></i>
                    <h3><?php echo $bookings_with_food_count; ?></h3>
                    <p>Orders with Food</p>
                </div>
            </div>
            
            <div class="section-header">
                <h2><i class="fas fa-chart-line"></i> Quick Actions</h2>
            </div>
            
            <div class="stats-cards">
                <div class="stat-card" onclick="openModal('addRoomModal')" style="cursor:pointer;">
                    <i class="fas fa-plus-circle"></i>
                    <h3>Add Room</h3>
                    <p>Create new KTV room</p>
                </div>
                <div class="stat-card" onclick="showSection('foods')" style="cursor:pointer;">
                    <i class="fas fa-hamburger"></i>
                    <h3>Manage Stock</h3>
                    <p>Update food inventory</p>
                </div>
                <div class="stat-card" onclick="showSection('bookings')" style="cursor:pointer;">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Manage Bookings</h3>
                    <p>Approve/View bookings</p>
                </div>
                <div class="stat-card" onclick="showSection('food-orders')" style="cursor:pointer;">
                    <i class="fas fa-hamburger"></i>
                    <h3>View Food Orders</h3>
                    <p>Check all room orders</p>
                </div>
            </div>
        </div>

        <!-- Users Section -->
        <div id="users" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> User Management</h2>
                <span class="welcome-message">
                    <i class="fas fa-user-friends"></i> Total: <?php echo $users_count; ?> Users
                </span>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Age</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $users->data_seek(0);
                        while($row = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <td><?= htmlspecialchars($row['email']) ?></td>
                                <td><?= htmlspecialchars($row['contact']) ?></td>
                                <td><?= $row['age'] ?></td>
                                <td>
                                    <span style="background: <?= $row['role'] == 'admin' ? 'rgba(244, 63, 94, 0.15)' : 'rgba(59, 130, 246, 0.15)'; ?>; 
                                          color: <?= $row['role'] == 'admin' ? 'var(--highlight)' : 'var(--info)'; ?>;
                                          padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                                        <?= $row['role'] ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-delete" onclick="showConfirm('Are you sure you want to delete this user?', 'admindash.php?delete_user=<?= $row['id'] ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Rooms Section -->
        <div id="rooms" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-door-closed"></i> Room Management</h2>
                <button class="add-btn" onclick="openModal('addRoomModal')">
                    <i class="fas fa-plus"></i> Add New Room
                </button>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Room Name</th>
                            <th>Capacity</th>
                            <th>Price/Hour</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rooms->data_seek(0);
                        while($row = $rooms->fetch_assoc()): 
                            $status_class = strtolower($row['status']);
                        ?>
                            <tr>
                                <td><?= $row['r_id'] ?></td>
                                <td><strong><?= htmlspecialchars($row['room_name']) ?></strong></td>
                                <td><?= $row['capcity'] ?> persons</td>
                                <td>₹<?= number_format($row['price_hr'], 2) ?></td>
                                <td>
                                    <span class="room-status <?= $status_class ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-edit" onclick="showEditRoomModal(<?= $row['r_id'] ?>, '<?= htmlspecialchars($row['room_name']) ?>', <?= $row['capcity'] ?>, <?= $row['price_hr'] ?>, '<?= $row['status'] ?>')">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn-delete" onclick="showConfirm('Are you sure you want to delete this room?', 'admindash.php?delete_room=<?= $row['r_id'] ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Foods Section -->
        <div id="foods" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-utensils"></i> Food & Beverages Management</h2>
                <span class="welcome-message">
                    <i class="fas fa-shopping-basket"></i> Total: <?php echo $foods_count; ?> Menu Items
                </span>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Price (₹)</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $foods->data_seek(0);
                        while($row = $foods->fetch_assoc()): 
                            $stock_status = '';
                            $status_color = '';
                            if ($row['stock'] == 0) {
                                $stock_status = 'Out of Stock';
                                $status_color = 'var(--danger)';
                            } elseif ($row['stock'] <= 10) {
                                $stock_status = 'Low Stock';
                                $status_color = 'var(--pending)';
                            } else {
                                $stock_status = 'In Stock';
                                $status_color = 'var(--success)';
                            }
                        ?>
                            <tr>
                                <td><?= $row['f_id'] ?></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td>
                                    <span style="background: rgba(59, 130, 246, 0.15); color: var(--info); 
                                          padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                                        <?= $row['category'] ?>
                                    </span>
                                </td>
                                <td>₹<?= number_format($row['price'], 2) ?></td>
                                <td>
                                    <span style="background: rgba(255, 255, 255, 0.08); color: var(--light);
                                          padding: 5px 10px; border-radius: 5px; font-size: 12px; font-weight: bold;">
                                        <?= $row['stock'] ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="background: <?= $stock_status == 'In Stock' ? 'rgba(16, 185, 129, 0.15)' : ($stock_status == 'Low Stock' ? 'rgba(245, 158, 11, 0.15)' : 'rgba(239, 68, 68, 0.15)'); ?>; 
                                          color: <?= $stock_status == 'In Stock' ? 'var(--success)' : ($stock_status == 'Low Stock' ? 'var(--pending)' : 'var(--danger)'); ?>;
                                          padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                                        <?= $stock_status ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-edit" onclick="showUpdateStockModal(<?= $row['f_id'] ?>, '<?= htmlspecialchars($row['item_name']) ?>', <?= $row['stock'] ?>)">
                                        <i class="fas fa-edit"></i> Update Stock
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Extras Section -->
        <div id="extras" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-star"></i> Extra Services Management</h2>
                <button class="add-btn" onclick="openModal('addExtraModal')">
                    <i class="fas fa-plus"></i> Add New Service
                </button>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Service Name</th>
                            <th>Description</th>
                            <th>Price (₹)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $extras->data_seek(0);
                        while($row = $extras->fetch_assoc()): ?>
                            <tr>
                                <td><?= $row['e_id'] ?></td>
                                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['description']) ?></td>
                                <td>₹<?= number_format($row['price'], 2) ?></td>
                                <td class="action-buttons">
                                    <button class="btn-delete" onclick="showConfirm('Are you sure you want to delete this extra service?', 'admindash.php?delete_extra=<?= $row['e_id'] ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bookings Section -->
        <div id="bookings" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-calendar-check"></i> Booking Management</h2>
                <span class="welcome-message">
                    <i class="fas fa-book"></i> Total: <?php echo $bookings_count; ?> Bookings
                </span>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>User</th>
                            <th>Room</th>
                            <th>Booking Date</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $bookings->data_seek(0);
                        while($row = $bookings->fetch_assoc()): 
                            $status_class = 'status-' . strtolower($row['status']);
                        ?>
                            <tr>
                                <td>#<?= $row['b_id'] ?></td>
                                <td><?= htmlspecialchars($row['user_name']) ?></td>
                                <td><?= htmlspecialchars($row['room_name']) ?></td>
                                <td><?= date('M d, Y', strtotime($row['booking_date'])) ?></td>
                                <td><?= date('h:i A', strtotime($row['start_time'])) ?></td>
                                <td><?= date('h:i A', strtotime($row['end_time'])) ?></td>
                                <td>₹<?= number_format($row['total_amount'], 2) ?></td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <i class="fas fa-circle"></i> <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-view" onclick="showBookingModal(<?= htmlspecialchars(json_encode($row)) ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn-delete" onclick="showConfirm('Are you sure you want to delete this booking?', 'admindash.php?delete_booking=<?= $row['b_id'] ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Food Orders Section - Shows Bookings that have Food Items -->
        <div id="food-orders" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-hamburger" style="color: var(--purple);"></i> Food Orders (by Booking)</h2>
                <span class="welcome-message">
                    <i class="fas fa-shopping-cart"></i> Total Orders: <?php echo $bookings_with_food_count; ?>
                </span>
            </div>
            
            <?php if (empty($food_orders)): ?>
                <div class="no-orders">
                    <i class="fas fa-hamburger"></i>
                    <p>No food orders have been placed yet.</p>
                    <p style="font-size: 14px; margin-top: 10px;">Orders will appear here when customers add food items to their bookings.</p>
                </div>
            <?php else: ?>
                <div class="orders-grid">
                    <?php 
                    $total_all_orders = 0;
                    $served_count = 0;
                    $pending_count = 0;
                    $cancelled_count = 0;
                    $mixed_count = 0;
                    
                    foreach ($food_orders as $b_id => $items): 
                        // Get booking info from first item
                        $booking_info = $items[0];
                        $room_total = 0;
                        $item_statuses = [];
                        
                        foreach ($items as $item) {
                            $room_total += $item['quantity'] * $item['price'];
                            $item_statuses[] = $item['served'];
                        }
                        $total_all_orders += $room_total;
                        
                        // Determine overall order status
                        $unique_statuses = array_unique($item_statuses);
                        if (count($unique_statuses) == 1) {
                            $order_status = $items[0]['served'];
                            // Count for summary
                            if ($order_status == 'served') $served_count++;
                            elseif ($order_status == 'pending') $pending_count++;
                            elseif ($order_status == 'cancelled') $cancelled_count++;
                        } else {
                            $order_status = 'mixed';
                            $mixed_count++;
                        }
                        
                        // Count items by status for this order
                        $status_counts = array_count_values($item_statuses);
                    ?>
                        <div class="room-food-card" id="food-card-<?php echo $b_id; ?>" style="border-left-color: <?php 
                            echo $order_status == 'served' ? 'var(--served)' : 
                                ($order_status == 'pending' ? 'var(--pending)' : 
                                ($order_status == 'cancelled' ? 'var(--cancelled)' : 'var(--mixed)')); ?>;">
                            
                            <div class="order-header">
                                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                                    <h4>
                                        <i class="fas fa-door-open"></i> 
                                        <?php echo htmlspecialchars($booking_info['room_name']); ?>
                                    </h4>
                                    <span class="order-status-badge <?php echo $order_status; ?>">
                                        <?php 
                                        if ($order_status == 'served') echo '✓ All Served';
                                        elseif ($order_status == 'pending') echo '⏳ All Pending';
                                        elseif ($order_status == 'cancelled') echo '✗ Cancelled';
                                        else echo '🔄 Mixed';
                                        ?>
                                    </span>
                                </div>
                                <span class="room-badge">#<?php echo $b_id; ?></span>
                            </div>
                            
                            <div class="order-items">
                                <?php foreach ($items as $item): ?>
                                    <div class="order-item <?php echo $item['served']; ?>">
                                        <div class="order-item-info">
                                            <span class="order-item-name">
                                                <?php echo htmlspecialchars($item['item_name']); ?>
                                                <span class="served-badge <?php echo $item['served']; ?>">
                                                    <?php 
                                                    if ($item['served'] == 'served') echo '✓ Served';
                                                    elseif ($item['served'] == 'pending') echo '⏳ Pending';
                                                    else echo '✗ Cancelled';
                                                    ?>
                                                </span>
                                            </span>
                                            <span class="order-item-details">
                                                <?php echo $item['category']; ?> | Qty: <?php echo $item['quantity']; ?>
                                            </span>
                                        </div>
                                        <div class="order-item-price">
                                            ₹<?php echo number_format($item['quantity'] * $item['price'], 2); ?>
                                        </div>
                                        <div class="order-item-actions">
                                            <button class="btn-delete" style="padding: 4px 8px; font-size: 11px;" 
                                                    onclick="showConfirm('Remove this food item from order?', 'admindash.php?delete_booking_food=<?php echo $item['bf_id']; ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="order-total">
                                <span>Room Total:</span>
                                <span>₹<?php echo number_format($room_total, 2); ?></span>
                            </div>
                            
                            <div class="order-footer">
                                <div class="booking-info">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($booking_info['user_name']); ?><br>
                                    <i class="fas fa-calendar-alt"></i> <?php echo date('M d', strtotime($booking_info['booking_date'])); ?>
                                    (<?php echo date('h:i A', strtotime($booking_info['start_time'])); ?>)
                                </div>
                                
                                <div class="update-order-status">
                                    <form method="post" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                                        <input type="hidden" name="b_id" value="<?php echo $b_id; ?>">
                                        <select name="served_status" class="<?php echo $order_status; ?>">
                                            <option value="pending" <?php echo $order_status == 'pending' ? 'selected' : ''; ?>>⏳ All Pending</option>
                                            <option value="served" <?php echo $order_status == 'served' ? 'selected' : ''; ?>>✓ All Served</option>
                                            <option value="cancelled" <?php echo $order_status == 'cancelled' ? 'selected' : ''; ?>>✗ All Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_food_order_status">
                                            Update
                                        </button>
                                    </form>
                                    <button class="btn-delete-order" onclick="showConfirm('Delete entire food order for Booking #<?php echo $b_id; ?>? This will restore stock and update booking total.', 'admindash.php?delete_whole_order=<?php echo $b_id; ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (count($status_counts) > 1): ?>
                                <div style="padding: 8px 18px; background: rgba(0,0,0,0.3); font-size: 11px; color: var(--light-dim); text-align: right;">
                                    Breakdown: 
                                    <?php if (isset($status_counts['served'])): ?>✓ Served: <?php echo $status_counts['served']; ?><?php endif; ?>
                                    <?php if (isset($status_counts['pending'])): ?> ⏳ Pending: <?php echo $status_counts['pending']; ?><?php endif; ?>
                                    <?php if (isset($status_counts['cancelled'])): ?> ✗ Cancelled: <?php echo $status_counts['cancelled']; ?><?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Grand Total for all food orders -->
                <div style="margin-top: 30px; background: linear-gradient(135deg, var(--accent), #2d3a4f); padding: 20px; border-radius: 10px; text-align: center; border: 2px solid var(--purple);">
                    <h3 style="color: var(--light); margin-bottom: 10px;">Total Food Revenue</h3>
                    <p style="font-size: 36px; font-weight: bold; color: var(--purple);">₹<?php echo number_format($total_all_orders, 2); ?></p>
                </div>
                
                <!-- Summary stats by order status -->
                <div style="margin-top: 20px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                    <div style="background: rgba(16, 185, 129, 0.1); padding: 15px; border-radius: 10px; text-align: center; border-left: 5px solid var(--served);">
                        <h4 style="color: var(--served); font-size: 24px;"><?php echo $served_count; ?></h4>
                        <p style="color: var(--light-dim); font-size: 14px;">All Served</p>
                    </div>
                    <div style="background: rgba(245, 158, 11, 0.1); padding: 15px; border-radius: 10px; text-align: center; border-left: 5px solid var(--pending);">
                        <h4 style="color: var(--pending); font-size: 24px;"><?php echo $pending_count; ?></h4>
                        <p style="color: var(--light-dim); font-size: 14px;">All Pending</p>
                    </div>
                    <div style="background: rgba(100, 116, 139, 0.1); padding: 15px; border-radius: 10px; text-align: center; border-left: 5px solid var(--cancelled);">
                        <h4 style="color: var(--cancelled); font-size: 24px;"><?php echo $cancelled_count; ?></h4>
                        <p style="color: var(--light-dim); font-size: 14px;">All Cancelled</p>
                    </div>
                    <div style="background: rgba(139, 92, 246, 0.1); padding: 15px; border-radius: 10px; text-align: center; border-left: 5px solid var(--mixed);">
                        <h4 style="color: var(--mixed); font-size: 24px;"><?php echo $mixed_count; ?></h4>
                        <p style="color: var(--light-dim); font-size: 14px;">Mixed Status</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODALS -->
<!-- Add Room Modal -->
<div id="addRoomModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Room</h3>
            <span class="close-modal" onclick="closeModal('addRoomModal')">&times;</span>
        </div>
        <form method="POST" class="modal-form">
            <div class="form-group">
                <label>Room Name</label>
                <input type="text" name="room_name" required placeholder="e.g., VIP Room 1">
            </div>
            <div class="form-group">
                <label>Capacity (persons)</label>
                <input type="number" name="capacity" required min="1" value="4">
            </div>
            <div class="form-group">
                <label>Price per Hour (₹)</label>
                <input type="number" name="price" required min="0" step="0.01" value="500">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" required>
                    <option value="Available">Available</option>
                    <option value="Occupied">Occupied</option>
                    <option value="Maintenance">Maintenance</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('addRoomModal')">Cancel</button>
                <button type="submit" name="add_room" class="btn-submit">
                    <i class="fas fa-save"></i> Add Room
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Room Modal -->
<div id="editRoomModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Room</h3>
            <span class="close-modal" onclick="closeModal('editRoomModal')">&times;</span>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="room_id" id="edit_room_id">
            <div class="form-group">
                <label>Room Name</label>
                <input type="text" name="room_name" id="edit_room_name" required>
            </div>
            <div class="form-group">
                <label>Capacity (persons)</label>
                <input type="number" name="capacity" id="edit_capacity" required min="1">
            </div>
            <div class="form-group">
                <label>Price per Hour (₹)</label>
                <input type="number" name="price" id="edit_price" required min="0" step="0.01">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="status" id="edit_status" required>
                    <option value="Available">Available</option>
                    <option value="Occupied">Occupied</option>
                    <option value="Maintenance">Maintenance</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editRoomModal')">Cancel</button>
                <button type="submit" name="update_room" class="btn-submit">
                    <i class="fas fa-save"></i> Update Room
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update Stock Modal -->
<div id="updateStockModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-boxes"></i> Update Stock</h3>
            <span class="close-modal" onclick="closeModal('updateStockModal')">&times;</span>
        </div>
        <form method="POST" class="modal-form">
            <input type="hidden" name="food_id" id="update_food_id">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" id="item_name_display" readonly disabled style="background: rgba(255,255,255,0.05);">
            </div>
            <div class="form-group">
                <label>Current Stock</label>
                <input type="number" id="current_stock_display" readonly disabled style="background: rgba(255,255,255,0.05);">
            </div>
            <div class="form-group">
                <label>New Stock Quantity</label>
                <input type="number" name="stock" id="stock" required min="0">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('updateStockModal')">Cancel</button>
                <button type="submit" name="update_food" class="btn-submit">
                    <i class="fas fa-save"></i> Update Stock
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Extra Service Modal -->
<div id="addExtraModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add Extra Service</h3>
            <span class="close-modal" onclick="closeModal('addExtraModal')">&times;</span>
        </div>
        <form method="POST" class="modal-form">
            <div class="form-group">
                <label>Service Name</label>
                <input type="text" name="name" required placeholder="e.g., Birthday Decoration">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3" required placeholder="Describe the service..."></textarea>
            </div>
            <div class="form-group">
                <label>Price (₹)</label>
                <input type="number" name="price" required min="0" step="0.01" value="0">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('addExtraModal')">Cancel</button>
                <button type="submit" name="add_extra" class="btn-submit">
                    <i class="fas fa-save"></i> Add Service
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="bookingModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-check"></i> Booking Details</h3>
            <span class="close-modal" onclick="closeModal('bookingModal')">&times;</span>
        </div>
        <div id="bookingDetails" class="modal-form">
            <!-- Content will be populated by JavaScript -->
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: var(--pending);"></i> Confirm Action</h3>
            <span class="close-modal" onclick="closeConfirmModal()">&times;</span>
        </div>
        <div class="modal-form" style="text-align: center; padding: 20px;">
            <i class="fas fa-question-circle" style="font-size: 60px; color: var(--pending); margin-bottom: 20px;"></i>
            <p id="confirmMessage" style="font-size: 18px; margin-bottom: 30px;">Are you sure?</p>
            <div class="modal-actions" style="justify-content: center;">
                <button class="btn-cancel" onclick="closeConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button class="btn-submit" id="confirmActionBtn" style="background: var(--pending);">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<?php if ($show_modal): ?>
<div id="notificationModal" class="modal notification-modal" style="display: block;">
    <div class="modal-content notification-content <?php echo $modal_type == 'success' ? 'notification-success' : 'notification-error'; ?>">
        <div class="modal-header">
            <h3>
                <?php if ($modal_type == 'success'): ?>
                    <i class="fas fa-check-circle" style="color: var(--success);"></i> Success
                <?php else: ?>
                    <i class="fas fa-times-circle" style="color: var(--danger);"></i> Error
                <?php endif; ?>
            </h3>
            <span class="close-modal" onclick="closeNotificationModal()">&times;</span>
        </div>
        <div class="modal-form" style="text-align: center;">
            <div class="notification-icon">
                <?php if ($modal_type == 'success'): ?>
                    <i class="fas fa-check-circle"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle"></i>
                <?php endif; ?>
            </div>
            <p class="notification-message"><?php echo htmlspecialchars($modal_message); ?></p>
            <div class="notification-actions">
                <button class="btn-ok" onclick="closeNotificationModal()">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Global variables for confirmation handling
let pendingActionUrl = null;
let pendingFormSubmit = false;
let pendingForm = null;
let currentBookingData = null;

// Show selected section and hide others
function showSection(sectionId) {
    // Hide all sections
    const sections = document.querySelectorAll('.content-section');
    sections.forEach(section => {
        section.classList.remove('active');
    });
    
    // Show selected section
    document.getElementById(sectionId).classList.add('active');
    
    // Update active menu item
    const menuItems = document.querySelectorAll('.menu-item');
    menuItems.forEach(item => {
        item.classList.remove('active');
    });
    
    // Find and activate corresponding menu item
    const activeMenuItem = Array.from(menuItems).find(item => {
        const text = item.textContent.toLowerCase();
        if (sectionId === 'dashboard' && text.includes('tachometer')) return true;
        if (sectionId === 'users' && text.includes('users')) return true;
        if (sectionId === 'rooms' && text.includes('door')) return true;
        if (sectionId === 'foods' && text.includes('utensils')) return true;
        if (sectionId === 'extras' && text.includes('star')) return true;
        if (sectionId === 'bookings' && text.includes('calendar')) return true;
        if (sectionId === 'food-orders' && text.includes('food orders')) return true;
        return false;
    });
    
    if (activeMenuItem) {
        activeMenuItem.classList.add('active');
    }
    
    // Update URL hash
    window.location.hash = sectionId;
}

// Show edit room modal with data
function showEditRoomModal(id, name, capacity, price, status) {
    document.getElementById('edit_room_id').value = id;
    document.getElementById('edit_room_name').value = name;
    document.getElementById('edit_capacity').value = capacity;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_status').value = status;
    openModal('editRoomModal');
}

// Show update stock modal
function showUpdateStockModal(id, name, currentStock) {
    document.getElementById('update_food_id').value = id;
    document.getElementById('item_name_display').value = name;
    document.getElementById('current_stock_display').value = currentStock;
    document.getElementById('stock').value = currentStock;
    document.getElementById('stock').focus();
    openModal('updateStockModal');
}

// Show booking modal with details
function showBookingModal(bookingData) {
    currentBookingData = bookingData;
    const bookingDetails = document.getElementById('bookingDetails');
    const statusOptions = ['Pending', 'Approved', 'Completed', 'Cancelled'];
    
    // Format time display
    const formatTime = (timeStr) => {
        const time = new Date('1970-01-01T' + timeStr + 'Z');
        return time.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    };
    
    bookingDetails.innerHTML = `
        <div class="booking-details">
            <div class="booking-details-row">
                <div class="booking-detail">
                    <label>Booking ID</label>
                    <span>#${bookingData.b_id}</span>
                </div>
                <div class="booking-detail">
                    <label>Customer</label>
                    <span>${bookingData.user_name}</span>
                </div>
            </div>
            <div class="booking-details-row">
                <div class="booking-detail">
                    <label>Room</label>
                    <span>${bookingData.room_name}</span>
                </div>
                <div class="booking-detail">
                    <label>Booking Date</label>
                    <span>${new Date(bookingData.booking_date).toLocaleDateString('en-US', { 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric' 
                    })}</span>
                </div>
            </div>
            <div class="booking-details-row">
                <div class="booking-detail">
                    <label>Start Time</label>
                    <span>${formatTime(bookingData.start_time)}</span>
                </div>
                <div class="booking-detail">
                    <label>End Time</label>
                    <span>${formatTime(bookingData.end_time)}</span>
                </div>
            </div>
            <div class="booking-details-row">
                <div class="booking-detail">
                    <label>Total Amount</label>
                    <span>₹${parseFloat(bookingData.total_amount).toFixed(2)}</span>
                </div>
                <div class="booking-detail">
                    <label>Current Status</label>
                    <span class="status-badge status-${bookingData.status.toLowerCase()}">
                        <i class="fas fa-circle"></i> ${bookingData.status}
                    </span>
                </div>
            </div>
        </div>
        
        <div class="modal-actions" style="flex-direction: column; align-items: stretch;">
            <h4>Change Booking Status:</h4>
            <select class="status-select" id="bookingStatusSelect">
                ${statusOptions.map(option => 
                    `<option value="${option}" ${option === bookingData.status ? 'selected' : ''}>${option}</option>`
                ).join('')}
            </select>
            <div style="display: flex; gap: 15px; margin-top: 20px;">
                <button type="button" class="btn-cancel" onclick="closeModal('bookingModal')">Close</button>
                <button type="button" class="btn-submit" onclick="updateBookingStatus()">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </div>
        </div>
    `;
    
    openModal('bookingModal');
}

// Update booking status
function updateBookingStatus() {
    if (!currentBookingData) return;
    
    const newStatus = document.getElementById('bookingStatusSelect').value;
    const bookingId = currentBookingData.b_id;
    
    showConfirm(
        `Are you sure you want to update booking #${bookingId} status to: ${newStatus}?`,
        null,
        function() {
            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            form.style.display = 'none';
            
            const bookingIdInput = document.createElement('input');
            bookingIdInput.type = 'hidden';
            bookingIdInput.name = 'b_id';
            bookingIdInput.value = bookingId;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = newStatus;
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'update_booking_status';
            submitInput.value = '1';
            
            form.appendChild(bookingIdInput);
            form.appendChild(statusInput);
            form.appendChild(submitInput);
            document.body.appendChild(form);
            form.submit();
        }
    );
}

// Custom confirmation function
function showConfirm(message, actionUrl, actionCallback) {
    document.getElementById('confirmMessage').textContent = message;
    
    // Clear previous actions
    pendingActionUrl = null;
    pendingFormSubmit = false;
    pendingForm = null;
    
    if (actionUrl) {
        pendingActionUrl = actionUrl;
    } else if (actionCallback) {
        pendingFormSubmit = true;
        pendingForm = actionCallback;
    }
    
    // Set up confirm button
    const confirmBtn = document.getElementById('confirmActionBtn');
    confirmBtn.onclick = executeConfirmedAction;
    
    document.getElementById('confirmModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    pendingActionUrl = null;
    pendingFormSubmit = false;
    pendingForm = null;
}

function executeConfirmedAction() {
    if (pendingActionUrl) {
        // Redirect to URL (for deletions)
        window.location.href = pendingActionUrl;
    } else if (pendingFormSubmit && pendingForm) {
        // Execute callback function (for form submissions)
        pendingForm();
        closeConfirmModal();
    }
}

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Clear form inputs
    const forms = document.querySelectorAll(`#${modalId} form`);
    forms.forEach(form => {
        form.reset();
    });
}

// Notification modal functions
function closeNotificationModal() {
    document.getElementById('notificationModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target == modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
            
            // Clear form inputs
            const forms = modal.querySelectorAll('form');
            forms.forEach(form => {
                form.reset();
            });
            
            // Reset confirmation state
            if (modal.id === 'confirmModal') {
                closeConfirmModal();
            }
        }
    });
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
                
                // Clear form inputs
                const forms = modal.querySelectorAll('form');
                forms.forEach(form => {
                    form.reset();
                });
                
                // Reset confirmation state
                if (modal.id === 'confirmModal') {
                    closeConfirmModal();
                }
            }
        });
    }
});

// Auto-close notification modal after 5 seconds
setTimeout(() => {
    const notificationModal = document.getElementById('notificationModal');
    if (notificationModal && notificationModal.style.display === 'block') {
        closeNotificationModal();
    }
}, 5000);

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Check URL hash for initial section
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        showSection(hash);
    } else {
        showSection('dashboard');
    }
    
    // If there's a food-orders hash in URL, show that section
    if (window.location.hash === '#food-orders') {
        showSection('food-orders');
    }
});
</script>

</body>
</html>