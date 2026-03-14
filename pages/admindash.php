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

if (isset($_GET['delete_booking'])) {
    $id = (int)$_GET['delete_booking'];
    
    // First delete related food orders from both tables
    $conn->query("DELETE FROM booking_food WHERE table_num=$id");
    $conn->query("DELETE FROM preorders WHERE b_id=$id");
    // Then delete the booking
    $conn->query("DELETE FROM booking WHERE b_id=$id");
    
    $_SESSION['success'] = "Booking deleted successfully!";
    header("Location: admindash.php");
    exit;
}

// Delete preorder from preorders table
if (isset($_GET['delete_preorder'])) {
    $id = (int)$_GET['delete_preorder'];
    $po_id = $id;
    
    // Get preorder info before deleting
    $order_info = $conn->query("SELECT po.*, f.price, f.stock as current_stock FROM preorders po JOIN food_beverages f ON po.f_id = f.f_id WHERE po.po_id = $po_id")->fetch_assoc();
    
    if ($order_info) {
        $b_id = $order_info['b_id'];
        $f_id = $order_info['f_id'];
        $quantity = $order_info['quantity'];
        
        // Delete the preorder
        $conn->query("DELETE FROM preorders WHERE po_id=$po_id");
        
        // Update stock - add back the quantity
        $conn->query("UPDATE food_beverages SET stock = stock + $quantity WHERE f_id = $f_id");
        
        $_SESSION['success'] = "Pre-order item removed successfully!";
    } else {
        $_SESSION['error'] = "Pre-order item not found!";
    }
    
    header("Location: admindash.php#preorders");
    exit;
}

// Delete entire preorder (all items for a booking from preorders)
if (isset($_GET['delete_whole_preorder'])) {
    $b_id = (int)$_GET['delete_whole_preorder'];
    
    // Get all preorder items for this booking to restore stock
    $food_items = $conn->query("SELECT po.*, f.stock as current_stock FROM preorders po JOIN food_beverages f ON po.f_id = f.f_id WHERE po.b_id = $b_id");
    
    while ($item = $food_items->fetch_assoc()) {
        $quantity = $item['quantity'];
        $f_id = $item['f_id'];
        
        // Restore stock for each item
        $conn->query("UPDATE food_beverages SET stock = stock + $quantity WHERE f_id = $f_id");
    }
    
    // Delete all preorder items for this booking
    $conn->query("DELETE FROM preorders WHERE b_id=$b_id");
    
    $_SESSION['success'] = "Complete pre-order for Booking #$b_id has been deleted!";
    header("Location: admindash.php#preorders");
    exit;
}

// Delete tablet order (booking_food with table_num between 1-8)
if (isset($_GET['delete_tablet_order'])) {
    $id = (int)$_GET['delete_tablet_order'];
    $bf_id = $id;
    
    // Get food info before deleting
    $order_info = $conn->query("SELECT bf.*, f.price, f.stock as current_stock FROM booking_food bf JOIN food_beverages f ON bf.f_id = f.f_id WHERE bf.bf_id = $bf_id")->fetch_assoc();
    
    if ($order_info) {
        $f_id = $order_info['f_id'];
        $quantity = $order_info['quantity'];
        
        // Delete the order
        $conn->query("DELETE FROM booking_food WHERE bf_id=$bf_id");
        
        // Update stock - add back the quantity
        $conn->query("UPDATE food_beverages SET stock = stock + $quantity WHERE f_id = $f_id");
        
        $_SESSION['success'] = "Tablet order removed successfully!";
    } else {
        $_SESSION['error'] = "Order item not found!";
    }
    
    header("Location: admindash.php#tablet-orders");
    exit;
}

// Delete all orders for a specific table
if (isset($_GET['delete_table_orders'])) {
    $table_number = (int)$_GET['delete_table_orders'];
    
    // Get all items for this table to restore stock
    $food_items = $conn->query("SELECT bf.*, f.stock as current_stock FROM booking_food bf JOIN food_beverages f ON bf.f_id = f.f_id WHERE bf.table_num = $table_number");
    
    while ($item = $food_items->fetch_assoc()) {
        $quantity = $item['quantity'];
        $f_id = $item['f_id'];
        
        // Restore stock for each item
        $conn->query("UPDATE food_beverages SET stock = stock + $quantity WHERE f_id = $f_id");
    }
    
    // Delete all items for this table
    $conn->query("DELETE FROM booking_food WHERE table_num = $table_number");
    
    $_SESSION['success'] = "All orders for Table $table_number have been cleared!";
    header("Location: admindash.php#tablet-orders");
    exit;
}

// ===== UPDATE TABLET ORDER STATUS (INDIVIDUAL ITEM) =====
if (isset($_POST['update_tablet_item_status'])) {
    $bf_id = (int)$_POST['bf_id'];
    $served_status = $_POST['served_status'];
    
    // Update specific tablet order item
    $stmt = $conn->prepare("UPDATE booking_food SET served = ? WHERE bf_id = ?");
    $stmt->bind_param("si", $served_status, $bf_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Tablet order status updated to: $served_status";
    } else {
        $_SESSION['error'] = "Failed to update tablet order status: " . $conn->error;
    }
    
    $stmt->close();
    header("Location: admindash.php#tablet-orders");
    exit;
}

// ===== UPDATE PREPARATION TIME FOR TABLET ORDERS =====
if (isset($_POST['update_tablet_preparation_time'])) {
    $bf_id = (int)$_POST['bf_id'];
    $preparation_time = (int)$_POST['preparation_time'];
    
    // Update preparation time for this specific tablet order
    $stmt = $conn->prepare("UPDATE booking_food SET preparation_time = ?, manual_timer_minutes = ? WHERE bf_id = ?");
    $stmt->bind_param("iii", $preparation_time, $preparation_time, $bf_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Preparation time updated to $preparation_time minutes for this tablet order!";
    } else {
        $_SESSION['error'] = "Failed to update preparation time: " . $conn->error;
    }
    
    $stmt->close();
    header("Location: admindash.php#tablet-orders");
    exit;
}

// ===== BULK UPDATE PREPARATION TIMES FOR TABLET ORDERS =====
if (isset($_POST['bulk_update_tablet_preparation'])) {
    $bulk_prep_time = (int)$_POST['bulk_prep_time'];
    
    if ($bulk_prep_time > 0) {
        // Update all pending tablet orders (where table_num is between 1-8)
        $stmt = $conn->prepare("UPDATE booking_food SET preparation_time = ?, manual_timer_minutes = ? WHERE table_num BETWEEN 1 AND 8 AND served = 'pending'");
        $stmt->bind_param("ii", $bulk_prep_time, $bulk_prep_time);
        
        if ($stmt->execute()) {
            $affected_rows = $stmt->affected_rows;
            $_SESSION['success'] = "Updated preparation times for $affected_rows tablet orders to $bulk_prep_time minutes!";
        } else {
            $_SESSION['error'] = "Failed to update preparation times: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['error'] = "Invalid preparation time!";
    }
    
    header("Location: admindash.php#tablet-orders");
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
    $preparation_time = $_POST['preparation_time'] ?? 15;

    $stmt = $conn->prepare("UPDATE food_beverages SET stock=?, preparation_time=? WHERE f_id=?");
    $stmt->bind_param("iii", $stock, $preparation_time, $food_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = "Food item updated successfully! Default preparation time set to $preparation_time minutes.";
    } else {
        $_SESSION['error'] = "Failed to update food item: " . $conn->error;
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

// Check if preparation_time column exists in booking_food, if not add it
$check_prep_time_bf = $conn->query("SHOW COLUMNS FROM booking_food LIKE 'preparation_time'");
if ($check_prep_time_bf->num_rows == 0) {
    $conn->query("ALTER TABLE booking_food ADD COLUMN preparation_time INT DEFAULT 15 COMMENT 'Preparation time in minutes for this specific order'");
}

// Check if manual_timer_minutes column exists in booking_food, if not add it
$check_timer_column = $conn->query("SHOW COLUMNS FROM booking_food LIKE 'manual_timer_minutes'");
if ($check_timer_column->num_rows == 0) {
    $conn->query("ALTER TABLE booking_food ADD COLUMN manual_timer_minutes INT DEFAULT 15 COMMENT 'Manual timer set by admin in minutes'");
}

// Check if preparation_time column exists in food_beverages, if not add it
$check_prep_time = $conn->query("SHOW COLUMNS FROM food_beverages LIKE 'preparation_time'");
if ($check_prep_time->num_rows == 0) {
    $conn->query("ALTER TABLE food_beverages ADD COLUMN preparation_time INT DEFAULT 15 COMMENT 'Default preparation time in minutes'");
}

// Check if order_time column exists in booking_food
$check_order_time = $conn->query("SHOW COLUMNS FROM booking_food LIKE 'order_time'");
if ($check_order_time->num_rows == 0) {
    $conn->query("ALTER TABLE booking_food ADD COLUMN order_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

// Check if 'served' column exists in booking_food table, if not add it
$check_column = $conn->query("SHOW COLUMNS FROM booking_food LIKE 'served'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE booking_food ADD COLUMN served ENUM('pending', 'served', 'cancelled') DEFAULT 'pending'");
}

// Check if preorders table exists, if not create it
$check_preorders_table = $conn->query("SHOW TABLES LIKE 'preorders'");
if ($check_preorders_table->num_rows == 0) {
    $conn->query("
        CREATE TABLE preorders (
            po_id INT PRIMARY KEY AUTO_INCREMENT,
            b_id INT NOT NULL,
            f_id INT NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            payment_id VARCHAR(100) NULL,
            order_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            preparation_time INT DEFAULT 15,
            scheduled_for DATETIME NOT NULL,
            completed_at DATETIME NULL,
            notes TEXT,
            FOREIGN KEY (b_id) REFERENCES booking(b_id) ON DELETE CASCADE,
            FOREIGN KEY (f_id) REFERENCES food_beverages(f_id) ON DELETE CASCADE,
            INDEX idx_booking (b_id),
            INDEX idx_scheduled (scheduled_for)
        )
    ");
}

// Initialize default KTV food items with preparation time if table is empty
$food_count = $conn->query("SELECT COUNT(*) as count FROM food_beverages")->fetch_assoc()['count'];
if ($food_count == 0) {
    $default_foods = [
        // Appetizers (10 items)
        ['Chicken Popcorn', 'Appetizer', 250.00, 50, 10],
        ['French Fries', 'Appetizer', 180.00, 60, 8],
        ['Spring Rolls (Veg)', 'Appetizer', 220.00, 40, 12],
        ['Cheese Balls', 'Appetizer', 200.00, 45, 10],
        ['Garlic Bread', 'Appetizer', 160.00, 55, 7],
        ['Chicken Wings', 'Appetizer', 320.00, 35, 15],
        ['Paneer Tikka', 'Appetizer', 280.00, 40, 14],
        ['Potato Wedges', 'Appetizer', 190.00, 50, 9],
        ['Chicken Lollipop', 'Appetizer', 300.00, 30, 13],
        ['Crispy Corn', 'Appetizer', 210.00, 45, 8],
        
        // Main Course (10 items)
        ['Chicken Biryani', 'Main Course', 350.00, 30, 20],
        ['Butter Chicken', 'Main Course', 380.00, 25, 18],
        ['Paneer Butter Masala', 'Main Course', 320.00, 35, 16],
        ['Fish & Chips', 'Main Course', 400.00, 20, 15],
        ['Margherita Pizza', 'Main Course', 450.00, 25, 14],
        ['Chicken Fried Rice', 'Main Course', 330.00, 30, 12],
        ['Veg Hakka Noodles', 'Main Course', 280.00, 40, 10],
        ['Grilled Chicken', 'Main Course', 420.00, 20, 17],
        ['Chicken Shawarma', 'Main Course', 280.00, 35, 13],
        ['Mutton Rogan Josh', 'Main Course', 450.00, 15, 22],
        
        // Snacks (10 items)
        ['Chicken Burger', 'Snacks', 280.00, 40, 12],
        ['Veg Sandwich', 'Snacks', 200.00, 50, 8],
        ['French Toast', 'Snacks', 220.00, 35, 9],
        ['Nachos with Cheese', 'Snacks', 300.00, 30, 7],
        ['Samosa Plate', 'Snacks', 180.00, 45, 8],
        ['Chicken Wrap', 'Snacks', 260.00, 35, 10],
        ['Cheese Pizza Slice', 'Snacks', 180.00, 50, 6],
        ['Masala Fries', 'Snacks', 210.00, 40, 8],
        ['Paneer Tikka Sandwich', 'Snacks', 240.00, 30, 10],
        ['Chicken Hot Dog', 'Snacks', 220.00, 40, 7],
        
        // Beverages (Non-Alcoholic) (10 items)
        ['Coca-Cola (500ml)', 'Beverage', 80.00, 100, 2],
        ['Fresh Lime Soda', 'Beverage', 100.00, 80, 3],
        ['Iced Tea', 'Beverage', 120.00, 70, 3],
        ['Virgin Mojito', 'Beverage', 150.00, 60, 4],
        ['Hot Coffee', 'Beverage', 90.00, 90, 5],
        ['Green Tea', 'Beverage', 70.00, 85, 4],
        ['Fresh Orange Juice', 'Beverage', 130.00, 55, 5],
        ['Mango Shake', 'Beverage', 160.00, 45, 5],
        ['Pepsi (500ml)', 'Beverage', 80.00, 95, 2],
        ['Mineral Water', 'Beverage', 50.00, 120, 1],
        
        // Alcoholic Drinks (10 items)
        ['Beer (Pint)', 'Alcoholic', 250.00, 80, 2],
        ['Whisky (60ml)', 'Alcoholic', 350.00, 60, 2],
        ['Vodka (60ml)', 'Alcoholic', 320.00, 65, 2],
        ['Red Wine (Glass)', 'Alcoholic', 280.00, 40, 2],
        ['Rum (60ml)', 'Alcoholic', 300.00, 55, 2],
        ['Tequila Shot', 'Alcoholic', 200.00, 70, 2],
        ['White Wine (Glass)', 'Alcoholic', 290.00, 35, 2],
        ['Gin (60ml)', 'Alcoholic', 340.00, 50, 2],
        ['Brandy (60ml)', 'Alcoholic', 320.00, 45, 2],
        ['Champagne (Glass)', 'Alcoholic', 400.00, 30, 2],
        
        // Desserts (6 items)
        ['Chocolate Brownie', 'Dessert', 180.00, 40, 8],
        ['Ice Cream Sundae', 'Dessert', 220.00, 35, 5],
        ['Cheesecake Slice', 'Dessert', 250.00, 30, 6],
        ['Chocolate Mousse', 'Dessert', 200.00, 45, 7],
        ['Fruit Salad', 'Dessert', 150.00, 50, 5],
        ['Gulab Jamun', 'Dessert', 120.00, 60, 6]
    ];
    
    foreach ($default_foods as $food) {
        $stmt = $conn->prepare("INSERT INTO food_beverages (item_name, category, price, stock, preparation_time) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdii", $food[0], $food[1], $food[2], $food[3], $food[4]);
        $stmt->execute();
        $stmt->close();
    }
}

// ===== FETCH data =====
$users = $conn->query("SELECT * FROM user_tbl ORDER BY id DESC");
$rooms = $conn->query("SELECT * FROM room ORDER BY r_id DESC");
$foods = $conn->query("SELECT * FROM food_beverages ORDER BY category, item_name ASC");
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
$bookings_count = $bookings->num_rows;

// Stats
$pending_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE status = 'Pending'")->fetch_assoc()['count'];
$approved_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE status = 'Approved'")->fetch_assoc()['count'];
$today_bookings = $conn->query("SELECT COUNT(*) as count FROM booking WHERE DATE(booking_date) = CURDATE()")->fetch_assoc()['count'];
$total_revenue = $conn->query("SELECT SUM(total_amount) as total FROM booking WHERE status = 'Completed'")->fetch_assoc()['total'];
$total_revenue = $total_revenue ? $total_revenue : 0;

// Get current date for comparison
$current_date = date('Y-m-d');

// Function to check if booking is today
function isToday($date) {
    return $date == date('Y-m-d');
}

// Function to format date
function formatDate($date_string) {
    if (empty($date_string)) return 'No date';
    $timestamp = strtotime($date_string);
    return ($timestamp !== false) ? date('M j, Y', $timestamp) : $date_string;
}

// Function to format time
function formatTime($time_string) {
    if (empty($time_string)) return '';
    $timestamp = strtotime($time_string);
    return ($timestamp !== false) ? date('h:i A', $timestamp) : $time_string;
}

// Get all tablet orders (booking_food where table_num is between 1-8)
$tablet_orders_query = $conn->query("
    SELECT bf.*, f.item_name, f.category, f.price as item_price,
           bf.order_time
    FROM booking_food bf
    JOIN food_beverages f ON bf.f_id = f.f_id
    WHERE bf.table_num BETWEEN 1 AND 8
    ORDER BY bf.table_num ASC, bf.order_time DESC
");

$tablet_orders = [];
$pending_tablet_orders = 0;
$served_tablet_orders = 0;
if ($tablet_orders_query) {
    while ($row = $tablet_orders_query->fetch_assoc()) {
        $tablet_orders[] = $row;
        if ($row['served'] == 'pending') $pending_tablet_orders++;
        if ($row['served'] == 'served') $served_tablet_orders++;
    }
}
$total_tablet_orders = count($tablet_orders);

// Get all preorders - SIMPLIFIED (no status needed)
$preorders = [];
$preorder_query = $conn->query("
    SELECT po.*, f.item_name, f.category, f.price as item_price,
           b.b_id, r.room_name, u.name as user_name,
           b.booking_date, b.start_time, b.end_time,
           TIMESTAMPDIFF(HOUR, NOW(), po.scheduled_for) as hours_until
    FROM preorders po
    JOIN food_beverages f ON po.f_id = f.f_id
    JOIN booking b ON po.b_id = b.b_id
    JOIN room r ON b.r_id = r.r_id
    JOIN user_tbl u ON b.u_id = u.id
    ORDER BY po.scheduled_for ASC, po.order_time DESC
");

$preorder_items = [];
$preorder_bookings = [];
while ($row = $preorder_query->fetch_assoc()) {
    $preorder_items[] = $row;
    if (!in_array($row['b_id'], $preorder_bookings)) {
        $preorder_bookings[] = $row['b_id'];
    }
}

// Calculate stats
$total_preorder_items = count($preorder_items);

// Function to get status badge class
function getStatusClass($status) {
    $status = strtolower($status);
    if ($status == 'served') return 'served';
    if ($status == 'pending') return 'pending';
    if ($status == 'cancelled') return 'cancelled';
    return '';
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
    --primary: #0f172a;
    --secondary: #1e293b;
    --accent: #334155;
    --highlight: #f43f5e;
    --highlight-light: #fb7185;
    --success: #10b981;
    --info: #3b82f6;
    --warning: #f59e0b;
    --danger: #ef4444;
    --purple: #8b5cf6;
    --light: #f8fafc;
    --light-dim: #94a3b8;
    --dark: #020617;
    --tablet: #3498db;
    --preorder: #e67e22;
    
    /* Status colors */
    --served: #10b981;
    --pending: #f59e0b;
    --cancelled: #64748b;
    --waiting: #f39c12;
    --preparing: #3498db;
    --future: #95a5a6;
    
    /* Order section specific */
    --order-bg: #1e293b;
    --order-item: #2d3a4f;
    --order-accent: #f43f5e;
    --order-highlight: #fb7185;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
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
    flex-wrap: wrap;
    gap: 15px;
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
    flex-wrap: wrap;
}

.welcome-message {
    background: var(--accent);
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 14px;
    color: var(--light);
    display: flex;
    align-items: center;
    gap: 8px;
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
    color: white;
}

.menu-item.tablet-orders {
    background: rgba(52, 152, 219, 0.1);
    color: var(--tablet);
}

.menu-item.tablet-orders.active {
    background: linear-gradient(135deg, var(--tablet), #5faee3);
    color: white;
}

.menu-item.preorders {
    background: rgba(230, 126, 34, 0.1);
    color: var(--preorder);
}

.menu-item.preorders.active {
    background: linear-gradient(135deg, var(--preorder), #f39c12);
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
    flex-wrap: wrap;
    gap: 15px;
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

.header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.add-btn {
    background: linear-gradient(135deg, var(--success), #0ca678);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.add-btn:hover {
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
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
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
    flex-wrap: wrap;
}

.btn-edit, .btn-delete, .btn-view, .btn-served, .btn-timer {
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

.btn-served {
    background: rgba(16, 185, 129, 0.15);
    color: var(--served);
}

.btn-served:hover {
    background: var(--served);
    color: white;
}

.btn-timer {
    background: rgba(52, 152, 219, 0.15);
    color: var(--tablet);
}

.btn-timer:hover {
    background: var(--tablet);
    color: white;
}

/* Status Badges */
.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.status-badge.served {
    background: rgba(16, 185, 129, 0.15);
    color: var(--served);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.pending {
    background: rgba(245, 158, 11, 0.15);
    color: var(--pending);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-badge.cancelled {
    background: rgba(100, 116, 139, 0.15);
    color: var(--cancelled);
    border: 1px solid rgba(100, 116, 139, 0.3);
}

.status-badge.tablet {
    background: rgba(52, 152, 219, 0.15);
    color: var(--tablet);
    border: 1px solid rgba(52, 152, 219, 0.3);
}

.status-badge.preorder {
    background: rgba(230, 126, 34, 0.15);
    color: var(--preorder);
    border: 1px solid rgba(230, 126, 34, 0.3);
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 20px;
    border: 2px dashed var(--purple);
}

.empty-state i {
    font-size: 80px;
    color: var(--purple);
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-state h3 {
    font-size: 24px;
    color: var(--light);
    margin-bottom: 10px;
}

.empty-state p {
    color: var(--light-dim);
    margin-bottom: 30px;
}

/* Summary Cards */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    transition: transform 0.2s;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.summary-details {
    flex: 1;
}

.summary-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
    line-height: 1.2;
}

.summary-label {
    font-size: 13px;
    color: var(--light-dim);
}

/* Bulk Update Section */
.bulk-update-section {
    background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(52, 152, 219, 0.05));
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    border: 1px solid rgba(52, 152, 219, 0.3);
}

.bulk-update-section h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    color: var(--tablet);
}

.bulk-update-section h3 i {
    font-size: 20px;
}

.bulk-update-form {
    display: flex;
    gap: 15px;
    align-items: flex-end;
    flex-wrap: wrap;
}

.bulk-update-form .form-group {
    flex: 1;
    min-width: 200px;
}

.bulk-update-form .form-group label {
    display: block;
    margin-bottom: 5px;
    font-size: 12px;
    color: var(--light-dim);
}

.bulk-update-form .form-group input {
    width: 100%;
    padding: 10px 12px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(52, 152, 219, 0.3);
    border-radius: 6px;
    color: white;
}

.bulk-update-form .form-group input:focus {
    outline: none;
    border-color: var(--tablet);
}

.bulk-update-form button {
    background: var(--tablet);
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
}

.bulk-update-form button:hover {
    background: #2980b9;
    transform: translateY(-2px);
}

/* Orders Grid */
.orders-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(450px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .orders-grid {
        grid-template-columns: 1fr;
    }
}

/* Order Card */
.order-card {
    background: var(--order-bg);
    border-radius: 16px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: all 0.2s;
}

.order-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
    border-color: var(--order-accent);
}

.order-card.tablet {
    border-left: 4px solid var(--tablet);
}

.order-card.preorder {
    border-left: 4px solid var(--preorder);
}

.order-card-header {
    background: linear-gradient(135deg, var(--accent), #2d3a4f);
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.room-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.room-info h4 {
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.room-info h4 i {
    color: var(--order-accent);
}

.status-badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.status-badge.served {
    background: rgba(16, 185, 129, 0.15);
    color: var(--served);
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.status-badge.pending {
    background: rgba(245, 158, 11, 0.15);
    color: var(--pending);
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.status-badge.cancelled {
    background: rgba(100, 116, 139, 0.15);
    color: var(--cancelled);
    border: 1px solid rgba(100, 116, 139, 0.3);
}

.order-id {
    background: rgba(255, 255, 255, 0.1);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

/* Order Items */
.order-items {
    padding: 16px;
}

.order-item {
    background: var(--order-item);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 12px;
    border: 1px solid rgba(255, 255, 255, 0.05);
}

.order-item:last-child {
    margin-bottom: 0;
}

.order-item.served {
    border-left: 4px solid var(--served);
}

.order-item.pending {
    border-left: 4px solid var(--pending);
}

.order-item.cancelled {
    border-left: 4px solid var(--cancelled);
    opacity: 0.7;
}

.order-item.tablet {
    border-left: 4px solid var(--tablet);
}

.order-item-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 10px;
}

.item-info h5 {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.item-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    color: var(--light-dim);
    flex-wrap: wrap;
}

.preorder-tag {
    background: rgba(230, 126, 34, 0.15);
    color: var(--preorder);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.tablet-tag {
    background: rgba(52, 152, 219, 0.15);
    color: var(--tablet);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.prep-time-tag {
    background: rgba(52, 152, 219, 0.15);
    color: var(--tablet);
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 10px;
    font-weight: 600;
}

.item-price {
    font-weight: 600;
    color: var(--order-accent);
    font-size: 16px;
}

.item-actions {
    display: flex;
    gap: 6px;
}

/* Status Update */
.status-update {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed rgba(255, 255, 255, 0.1);
}

.status-update form {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.status-update select {
    flex: 1;
    min-width: 120px;
    padding: 6px 10px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    color: white;
    font-size: 11px;
    cursor: pointer;
}

.status-update select option {
    background: var(--order-bg);
    color: white;
}

.status-update button {
    background: var(--info);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.status-update button:hover {
    background: #2563eb;
}

/* Preparation Time Update */
.prep-update {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px dashed rgba(255, 255, 255, 0.1);
}

.prep-update form {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.prep-update input {
    width: 80px;
    padding: 6px 10px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(52, 152, 219, 0.3);
    border-radius: 4px;
    color: white;
    font-size: 12px;
}

.prep-update input:focus {
    outline: none;
    border-color: var(--tablet);
}

.prep-update button {
    background: var(--tablet);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.prep-update button:hover {
    background: #2980b9;
}

/* Order Footer */
.order-footer {
    padding: 16px 20px;
    background: rgba(0, 0, 0, 0.2);
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.customer-info {
    font-size: 12px;
    color: var(--light-dim);
}

.customer-info i {
    margin-right: 5px;
    color: var(--order-accent);
}

.order-total {
    font-size: 18px;
    font-weight: 700;
    color: var(--order-accent);
}

.order-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.btn-delete-order {
    background: rgba(239, 68, 68, 0.15);
    color: var(--danger);
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    transition: all 0.2s;
}

.btn-delete-order:hover {
    background: var(--danger);
    color: white;
}

/* Scheduled Time */
.scheduled-time {
    margin-top: 8px;
    font-size: 11px;
    color: var(--preorder);
    background: rgba(230, 126, 34, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
}

/* Time Until */
.time-until {
    margin-top: 5px;
    font-size: 11px;
    color: var(--info);
    background: rgba(59, 130, 246, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
    display: inline-block;
}

.time-until i {
    margin-right: 4px;
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
    border-radius: 20px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    border: 1px solid var(--highlight);
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
    font-size: 22px;
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

.modal-form {
    padding: 10px 0;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--light-dim);
    font-weight: 500;
    font-size: 13px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: var(--light);
    font-size: 14px;
    transition: all 0.3s;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--highlight);
    background: rgba(255, 255, 255, 0.12);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
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
    transition: all 0.3s;
}

.btn-submit:hover {
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
}

.btn-cancel:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

/* Notification Modal */
.notification-modal .modal-content {
    max-width: 400px;
}

.notification-success {
    border-color: var(--success);
}

.notification-error {
    border-color: var(--danger);
}

.notification-icon {
    font-size: 60px;
    margin: 20px 0;
    text-align: center;
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
    text-align: center;
}

.notification-actions {
    text-align: center;
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
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
}

/* Status Select */
.status-select {
    width: 100%;
    padding: 10px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 6px;
    color: white;
    cursor: pointer;
}

.status-select option {
    background: var(--order-bg);
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
        gap: 10px;
    }
    
    .menu-item {
        flex: 0 0 auto;
        white-space: nowrap;
    }
    
    .sidebar-title {
        display: none;
    }
}

@media (max-width: 768px) {
    .stats-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .order-footer {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .order-actions {
        width: 100%;
    }
}

@media (max-width: 480px) {
    .stats-cards,
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .order-item-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .item-actions {
        margin-left: 0;
    }
}
</style>
</head>
<body>

<header>
    <div class="header-left">
        <h1><i class="fas fa-crown"></i> Sirene KTV Admin</h1>
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
        <h3 class="sidebar-title">Navigation</h3>
        <button class="menu-item active" onclick="showSection('dashboard')">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </button>
        <button class="menu-item" onclick="showSection('users')">
            <i class="fas fa-users"></i> Users
        </button>
        <button class="menu-item" onclick="showSection('rooms')">
            <i class="fas fa-door-closed"></i> Rooms
        </button>
        <button class="menu-item" onclick="showSection('foods')">
            <i class="fas fa-utensils"></i> Menu
        </button>
        <button class="menu-item booking" onclick="showSection('bookings')">
            <i class="fas fa-calendar-check"></i> Bookings
        </button>
        <button class="menu-item tablet-orders" onclick="showSection('tablet-orders')">
            <i class="fas fa-tablet-alt"></i> Tablet Orders
        </button>
        <button class="menu-item preorders" onclick="showSection('preorders')">
            <i class="fas fa-clock"></i> Pre-Orders
        </button>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Dashboard Section -->
        <div id="dashboard" class="content-section active">
            <div class="section-header">
                <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
                <div class="welcome-message">
                    <i class="fas fa-calendar-alt"></i> <?php echo date('F j, Y'); ?>
                </div>
            </div>
            
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
                    <p>Revenue</p>
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
                    <i class="fas fa-tablet-alt"></i>
                    <h3><?php echo $total_tablet_orders; ?></h3>
                    <p>Tablet Orders</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <h3><?php echo count($preorder_bookings); ?></h3>
                    <p>Pre-Orders</p>
                </div>
            </div>
        </div>

        <!-- Users Section -->
        <div id="users" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> User Management</h2>
                <span class="welcome-message">Total: <?php echo $users_count; ?> Users</span>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Contact</th>
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
                                <td>
                                    <span style="background: <?= $row['role'] == 'admin' ? 'rgba(244, 63, 94, 0.15)' : 'rgba(59, 130, 246, 0.15)'; ?>; 
                                          color: <?= $row['role'] == 'admin' ? 'var(--highlight)' : 'var(--info)'; ?>;
                                          padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                                        <?= $row['role'] ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn-delete" onclick="showConfirm('Delete this user?', 'admindash.php?delete_user=<?= $row['id'] ?>')">
                                        <i class="fas fa-trash"></i>
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
                    <i class="fas fa-plus"></i> Add Room
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
                                <td><?= htmlspecialchars($row['room_name']) ?></td>
                                <td><?= $row['capcity'] ?> persons</td>
                                <td>₹<?= number_format($row['price_hr'], 2) ?></td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-edit" onclick="showEditRoomModal(<?= $row['r_id'] ?>, '<?= htmlspecialchars($row['room_name']) ?>', <?= $row['capcity'] ?>, <?= $row['price_hr'] ?>, '<?= $row['status'] ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn-delete" onclick="showConfirm('Delete this room?', 'admindash.php?delete_room=<?= $row['r_id'] ?>')">
                                        <i class="fas fa-trash"></i>
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
                <h2><i class="fas fa-utensils"></i> Menu Management</h2>
                <span class="welcome-message">Total: <?php echo $foods_count; ?> Items</span>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Prep Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $foods->data_seek(0);
                        while($row = $foods->fetch_assoc()): 
                        ?>
                            <tr>
                                <td><?= $row['f_id'] ?></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td><?= $row['category'] ?></td>
                                <td>₹<?= number_format($row['price'], 2) ?></td>
                                <td>
                                    <span style="color: <?= $row['stock'] > 10 ? 'var(--success)' : ($row['stock'] > 0 ? 'var(--pending)' : 'var(--danger)'); ?>">
                                        <?= $row['stock'] ?>
                                    </span>
                                </td>
                                <td><?= $row['preparation_time'] ?? 15 ?> min</td>
                                <td>
                                    <button class="btn-edit" onclick="showUpdateStockModal(<?= $row['f_id'] ?>, '<?= htmlspecialchars($row['item_name']) ?>', <?= $row['stock'] ?>, <?= $row['preparation_time'] ?? 15 ?>)">
                                        <i class="fas fa-edit"></i>
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
                <h2><i class="fas fa-calendar-check"></i> Bookings</h2>
                <span class="welcome-message">Total: <?php echo $bookings_count; ?></span>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Room</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $bookings->data_seek(0);
                        while($row = $bookings->fetch_assoc()): 
                            $status_class = getStatusClass($row['status']);
                        ?>
                            <tr>
                                <td>#<?= $row['b_id'] ?></td>
                                <td><?= htmlspecialchars($row['user_name']) ?></td>
                                <td><?= htmlspecialchars($row['room_name']) ?></td>
                                <td><?= formatDate($row['booking_date']) ?></td>
                                <td><?= formatTime($row['start_time']) ?></td>
                                <td>₹<?= number_format($row['total_amount'], 2) ?></td>
                                <td>
                                    <span class="status-badge <?= $status_class ?>">
                                        <?= $row['status'] ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-view" onclick="showBookingModal(<?= htmlspecialchars(json_encode($row)) ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn-delete" onclick="showConfirm('Delete this booking?', 'admindash.php?delete_booking=<?= $row['b_id'] ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tablet Orders Section - Grouped by Table -->
        <div id="tablet-orders" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-tablet-alt"></i> Tablet Orders</h2>
                <div class="header-actions">
                    <span class="welcome-message">
                        <i class="fas fa-shopping-cart"></i> Total Items: <?php echo $total_tablet_orders; ?>
                    </span>
                </div>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(245, 158, 11, 0.15); color: var(--pending);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-details">
                        <span class="summary-value"><?php echo $pending_tablet_orders; ?></span>
                        <span class="summary-label">Pending Items</span>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(52, 152, 219, 0.15); color: var(--tablet);">
                        <i class="fas fa-tablet-alt"></i>
                    </div>
                    <div class="summary-details">
                        <span class="summary-value"><?php echo $total_tablet_orders; ?></span>
                        <span class="summary-label">Total Items</span>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(46, 204, 113, 0.15); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="summary-details">
                        <span class="summary-value"><?php echo $served_tablet_orders; ?></span>
                        <span class="summary-label">Served</span>
                    </div>
                </div>
            </div>
            
            <?php if (empty($tablet_orders)): ?>
                <div class="empty-state">
                    <i class="fas fa-tablet-alt"></i>
                    <h3>No Tablet Orders Yet</h3>
                    <p>Orders from the tablet ordering system will appear here grouped by table.</p>
                </div>
            <?php else: ?>
                <!-- Bulk Update for Tablet Orders -->
                <div class="bulk-update-section">
                    <h3><i class="fas fa-clock"></i> Bulk Update All Tablet Orders</h3>
                    <form method="post" class="bulk-update-form" onsubmit="return confirm('Update all tablet orders to this preparation time?')">
                        <div class="form-group">
                            <label>Preparation Time (minutes)</label>
                            <input type="number" name="bulk_prep_time" min="1" max="480" value="15" required>
                        </div>
                        <button type="submit" name="bulk_update_tablet_preparation">
                            <i class="fas fa-clock"></i> Update All Orders
                        </button>
                    </form>
                </div>
                
                <!-- Orders Grid - Grouped by Table -->
                <div class="orders-grid">
                    <?php 
                    // Group tablet orders by table number
                    $tablet_orders_by_table = [];
                    foreach ($tablet_orders as $item) {
                        $table_number = $item['table_num'];
                        $tablet_orders_by_table[$table_number][] = $item;
                    }
                    
                    // Sort tables by number
                    ksort($tablet_orders_by_table);
                    
                    foreach ($tablet_orders_by_table as $table_number => $items): 
                        $table_total = 0;
                        $pending_count = 0;
                        $served_count = 0;
                        
                        foreach ($items as $item) {
                            $table_total += $item['quantity'] * $item['price'];
                            if ($item['served'] == 'pending') $pending_count++;
                            if ($item['served'] == 'served') $served_count++;
                        }
                    ?>
                        <div class="order-card tablet">
                            <!-- Order Header - Shows Table Number -->
                            <div class="order-card-header">
                                <div class="room-info">
                                    <h4>
                                        <i class="fas fa-chair"></i> 
                                        Table <?php echo $table_number; ?>
                                    </h4>
                                    <span class="status-badge pending"><?php echo $pending_count; ?> Pending</span>
                                    <span class="status-badge served"><?php echo $served_count; ?> Served</span>
                                </div>
                                <span class="order-id"><?php echo count($items); ?> items</span>
                            </div>
                            
                            <!-- Order Items -->
                            <div class="order-items">
                                <?php foreach ($items as $item): 
                                    $prep_time = $item['preparation_time'] ?? 15;
                                ?>
                                    <div class="order-item <?php echo $item['served']; ?>">
                                        <!-- Item Header -->
                                        <div class="order-item-header">
                                            <div class="item-info">
                                                <h5>
                                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                                    <span class="tablet-tag">Tablet</span>
                                                </h5>
                                                <div class="item-meta">
                                                    <span><?php echo $item['category']; ?></span>
                                                    <span>Qty: <?php echo $item['quantity']; ?></span>
                                                    <span class="prep-time-tag">
                                                        <i class="fas fa-clock"></i> <?php echo $prep_time; ?> min
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="item-price">
                                                ₹<?php echo number_format($item['quantity'] * $item['price'], 2); ?>
                                            </div>
                                            <div class="item-actions">
                                                <button class="btn-delete" onclick="showConfirm('Remove this tablet order?', 'admindash.php?delete_tablet_order=<?php echo $item['bf_id']; ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Preparation Time Update (for pending items) -->
                                        <?php if ($item['served'] == 'pending'): ?>
                                            <div class="prep-update">
                                                <form method="post">
                                                    <input type="hidden" name="bf_id" value="<?php echo $item['bf_id']; ?>">
                                                    <input type="number" name="preparation_time" min="1" max="480" value="<?php echo $prep_time; ?>">
                                                    <button type="submit" name="update_tablet_preparation_time">
                                                        <i class="fas fa-clock"></i> Update Prep Time
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Status Update -->
                                        <div class="status-update">
                                            <form method="post">
                                                <input type="hidden" name="bf_id" value="<?php echo $item['bf_id']; ?>">
                                                <select name="served_status">
                                                    <option value="pending" <?php echo $item['served'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="served" <?php echo $item['served'] == 'served' ? 'selected' : ''; ?>>Served</option>
                                                    <option value="cancelled" <?php echo $item['served'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                <button type="submit" name="update_tablet_item_status">Update Status</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Order Footer - Table Total -->
                            <div class="order-footer">
                                <div class="customer-info">
                                    <i class="fas fa-clock"></i> 
                                    Ordered: <?php echo date('h:i A', strtotime($items[0]['order_time'])); ?>
                                </div>
                                
                                <div class="order-total">
                                    Table Total: ₹<?php echo number_format($table_total, 2); ?>
                                </div>
                                
                                <div class="order-actions">
                                    <!-- Delete All Items for this Table -->
                                    <button class="btn-delete-order" onclick="showConfirm('Delete all orders for Table <?php echo $table_number; ?>?', 'admindash.php?delete_table_orders=<?php echo $table_number; ?>')">
                                        <i class="fas fa-trash"></i> Clear Table
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Preorders Section (SIMPLE - View Only + Delete Only) -->
        <div id="preorders" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-clock"></i> Pre-Orders</h2>
                <div class="header-actions">
                    <span class="welcome-message">
                        <i class="fas fa-shopping-cart"></i> Total Items: <?php echo $total_preorder_items; ?>
                    </span>
                </div>
            </div>
            
            <!-- Simple Summary -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(230, 126, 34, 0.15); color: var(--preorder);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="summary-details">
                        <span class="summary-value"><?php echo $total_preorder_items; ?></span>
                        <span class="summary-label">Total Pre-Orders</span>
                    </div>
                </div>
                <div class="summary-card">
                    <div class="summary-icon" style="background: rgba(59, 130, 246, 0.15); color: var(--info);">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="summary-details">
                        <span class="summary-value"><?php echo count($preorder_bookings); ?></span>
                        <span class="summary-label">Bookings</span>
                    </div>
                </div>
            </div>
            
            <?php if (empty($preorder_items)): ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <h3>No Pre-Orders Yet</h3>
                    <p>Pre-orders will appear here when customers order for future bookings.</p>
                </div>
            <?php else: ?>
                <!-- Simple Orders Grid - No Status, Just Items -->
                <div class="orders-grid">
                    <?php 
                    $preorders_by_booking = [];
                    foreach ($preorder_items as $item) {
                        $preorders_by_booking[$item['b_id']][] = $item;
                    }
                    
                    foreach ($preorders_by_booking as $b_id => $items): 
                        $booking_info = $items[0];
                        $room_total = 0;
                        
                        foreach ($items as $item) {
                            $room_total += $item['quantity'] * $item['price'];
                        }
                    ?>
                        <div class="order-card preorder">
                            <!-- Order Header -->
                            <div class="order-card-header">
                                <div class="room-info">
                                    <h4>
                                        <i class="fas fa-door-open"></i> 
                                        <?php echo htmlspecialchars($booking_info['room_name']); ?>
                                    </h4>
                                </div>
                                <span class="order-id">Booking #<?php echo $b_id; ?></span>
                            </div>
                            
                            <!-- Order Items - Simple Display -->
                            <div class="order-items">
                                <?php foreach ($items as $item): ?>
                                    <div class="order-item">
                                        <!-- Item Header - Simple, No Status -->
                                        <div class="order-item-header">
                                            <div class="item-info">
                                                <h5>
                                                    <?php echo htmlspecialchars($item['item_name']); ?>
                                                    <span class="preorder-tag">Pre-Order</span>
                                                </h5>
                                                <div class="item-meta">
                                                    <span><?php echo $item['category']; ?></span>
                                                    <span>Qty: <?php echo $item['quantity']; ?></span>
                                                </div>
                                            </div>
                                            <div class="item-price">
                                                ₹<?php echo number_format($item['quantity'] * $item['price'], 2); ?>
                                            </div>
                                            <div class="item-actions">
                                                <!-- Delete Only Button -->
                                                <button class="btn-delete" onclick="showConfirm('Remove this pre-order?', 'admindash.php?delete_preorder=<?php echo $item['po_id']; ?>')">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Scheduled Time - Simple Display -->
                                        <div class="scheduled-time">
                                            <i class="fas fa-calendar-alt"></i> Scheduled: <?php echo date('M d, Y h:i A', strtotime($item['scheduled_for'])); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Order Footer - Simple -->
                            <div class="order-footer">
                                <div class="customer-info">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($booking_info['user_name']); ?><br>
                                    <i class="fas fa-clock"></i> <?php echo formatTime($booking_info['start_time']); ?>
                                </div>
                                
                                <div class="order-total">
                                    ₹<?php echo number_format($room_total, 2); ?>
                                </div>
                                
                                <div class="order-actions">
                                    <!-- Delete Entire Order Button -->
                                    <button class="btn-delete-order" onclick="showConfirm('Delete entire pre-order?', 'admindash.php?delete_whole_preorder=<?php echo $b_id; ?>')">
                                        <i class="fas fa-trash"></i> Delete All
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Simple Note -->
                            <div style="padding: 8px 20px; background: rgba(230, 126, 34, 0.1); font-size: 11px; color: var(--preorder); border-top: 1px solid rgba(230, 126, 34, 0.2);">
                                <i class="fas fa-info-circle"></i> Pre-order items - Delete only
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Room Modal -->
<div id="addRoomModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add Room</h3>
            <span class="close-modal" onclick="closeModal('addRoomModal')">&times;</span>
        </div>
        <form method="POST">
            <div class="form-group">
                <label>Room Name</label>
                <input type="text" name="room_name" required placeholder="e.g., VIP Room 1">
            </div>
            <div class="form-group">
                <label>Capacity</label>
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
                <button type="submit" name="add_room" class="btn-submit">Add Room</button>
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
        <form method="POST">
            <input type="hidden" name="room_id" id="edit_room_id">
            <div class="form-group">
                <label>Room Name</label>
                <input type="text" name="room_name" id="edit_room_name" required>
            </div>
            <div class="form-group">
                <label>Capacity</label>
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
                <button type="submit" name="update_room" class="btn-submit">Update Room</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Stock Modal -->
<div id="updateStockModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-boxes"></i> Update Item</h3>
            <span class="close-modal" onclick="closeModal('updateStockModal')">&times;</span>
        </div>
        <form method="POST">
            <input type="hidden" name="food_id" id="update_food_id">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" id="item_name_display" readonly disabled>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Current Stock</label>
                    <input type="number" id="current_stock_display" readonly disabled>
                </div>
                <div class="form-group">
                    <label>Prep Time (min)</label>
                    <input type="number" name="preparation_time" id="preparation_time" required min="1" max="480">
                </div>
            </div>
            <div class="form-group">
                <label>New Stock</label>
                <input type="number" name="stock" id="stock" required min="0">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('updateStockModal')">Cancel</button>
                <button type="submit" name="update_food" class="btn-submit">Update</button>
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
        <div id="bookingDetails" class="modal-form"></div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3><i class="fas fa-exclamation-triangle" style="color: var(--pending);"></i> Confirm</h3>
            <span class="close-modal" onclick="closeConfirmModal()">&times;</span>
        </div>
        <div class="modal-form" style="text-align: center;">
            <i class="fas fa-question-circle" style="font-size: 60px; color: var(--pending); margin: 20px 0;"></i>
            <p id="confirmMessage" style="margin-bottom: 30px;">Are you sure?</p>
            <div class="modal-actions" style="justify-content: center;">
                <button class="btn-cancel" onclick="closeConfirmModal()">Cancel</button>
                <button class="btn-submit" id="confirmActionBtn" style="background: var(--pending);">Confirm</button>
            </div>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<?php if ($show_modal): ?>
<div id="notificationModal" class="modal notification-modal" style="display: block;">
    <div class="modal-content <?php echo $modal_type == 'success' ? 'notification-success' : 'notification-error'; ?>">
        <div class="modal-header">
            <h3>
                <?php if ($modal_type == 'success'): ?>
                    <i class="fas fa-check-circle"></i> Success
                <?php else: ?>
                    <i class="fas fa-times-circle"></i> Error
                <?php endif; ?>
            </h3>
            <span class="close-modal" onclick="closeNotificationModal()">&times;</span>
        </div>
        <div class="notification-icon">
            <?php if ($modal_type == 'success'): ?>
                <i class="fas fa-check-circle"></i>
            <?php else: ?>
                <i class="fas fa-exclamation-circle"></i>
            <?php endif; ?>
        </div>
        <p class="notification-message"><?php echo htmlspecialchars($modal_message); ?></p>
        <div class="notification-actions">
            <button class="btn-ok" onclick="closeNotificationModal()">OK</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Global variables
let pendingActionUrl = null;
let pendingFormSubmit = false;
let pendingForm = null;
let currentBookingData = null;

// Show section
function showSection(sectionId) {
    document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
    
    document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
    const activeItem = Array.from(document.querySelectorAll('.menu-item')).find(item => {
        const text = item.textContent.toLowerCase();
        if (sectionId === 'dashboard' && text.includes('tachometer')) return true;
        if (sectionId === 'users' && text.includes('users')) return true;
        if (sectionId === 'rooms' && text.includes('doors')) return true;
        if (sectionId === 'foods' && text.includes('menu')) return true;
        if (sectionId === 'bookings' && text.includes('bookings')) return true;
        if (sectionId === 'tablet-orders' && text.includes('tablet orders')) return true;
        if (sectionId === 'preorders' && text.includes('pre-orders')) return true;
        return false;
    });
    if (activeItem) activeItem.classList.add('active');
    
    window.location.hash = sectionId;
}

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Edit room modal
function showEditRoomModal(id, name, capacity, price, status) {
    document.getElementById('edit_room_id').value = id;
    document.getElementById('edit_room_name').value = name;
    document.getElementById('edit_capacity').value = capacity;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_status').value = status;
    openModal('editRoomModal');
}

// Update stock modal
function showUpdateStockModal(id, name, currentStock, prepTime) {
    document.getElementById('update_food_id').value = id;
    document.getElementById('item_name_display').value = name;
    document.getElementById('current_stock_display').value = currentStock;
    document.getElementById('preparation_time').value = prepTime;
    document.getElementById('stock').value = currentStock;
    openModal('updateStockModal');
}

// Booking modal
function showBookingModal(bookingData) {
    currentBookingData = bookingData;
    const details = document.getElementById('bookingDetails');
    
    const formatTime = (timeStr) => {
        return new Date('1970-01-01T' + timeStr).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    };
    
    details.innerHTML = `
        <div class="form-group">
            <label>Booking ID</label>
            <div>#${bookingData.b_id}</div>
        </div>
        <div class="form-group">
            <label>Customer</label>
            <div>${bookingData.user_name}</div>
        </div>
        <div class="form-group">
            <label>Room</label>
            <div>${bookingData.room_name}</div>
        </div>
        <div class="form-group">
            <label>Date</label>
            <div>${new Date(bookingData.booking_date).toLocaleDateString()}</div>
        </div>
        <div class="form-group">
            <label>Time</label>
            <div>${formatTime(bookingData.start_time)} - ${formatTime(bookingData.end_time)}</div>
        </div>
        <div class="form-group">
            <label>Amount</label>
            <div>₹${parseFloat(bookingData.total_amount).toFixed(2)}</div>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select id="bookingStatusSelect" class="status-select">
                <option value="Pending" ${bookingData.status === 'Pending' ? 'selected' : ''}>Pending</option>
                <option value="Approved" ${bookingData.status === 'Approved' ? 'selected' : ''}>Approved</option>
                <option value="Completed" ${bookingData.status === 'Completed' ? 'selected' : ''}>Completed</option>
                <option value="Cancelled" ${bookingData.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn-cancel" onclick="closeModal('bookingModal')">Close</button>
            <button class="btn-submit" onclick="updateBookingStatus()">Update</button>
        </div>
    `;
    
    openModal('bookingModal');
}

// Update booking status
function updateBookingStatus() {
    if (!currentBookingData) return;
    
    const newStatus = document.getElementById('bookingStatusSelect').value;
    
    showConfirm(
        `Update booking #${currentBookingData.b_id} to ${newStatus}?`,
        null,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            form.innerHTML = `
                <input type="hidden" name="b_id" value="${currentBookingData.b_id}">
                <input type="hidden" name="status" value="${newStatus}">
                <input type="hidden" name="update_booking_status" value="1">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    );
}

// Confirmation
function showConfirm(message, actionUrl, actionCallback) {
    document.getElementById('confirmMessage').textContent = message;
    pendingActionUrl = actionUrl || null;
    pendingFormSubmit = !!actionCallback;
    pendingForm = actionCallback || null;
    
    const confirmBtn = document.getElementById('confirmActionBtn');
    confirmBtn.onclick = function() {
        if (pendingActionUrl) {
            window.location.href = pendingActionUrl;
        } else if (pendingFormSubmit && pendingForm) {
            pendingForm();
        }
        closeConfirmModal();
    };
    
    openModal('confirmModal');
}

function closeConfirmModal() {
    closeModal('confirmModal');
    pendingActionUrl = null;
    pendingFormSubmit = false;
    pendingForm = null;
}

// Notification
function closeNotificationModal() {
    closeModal('notificationModal');
}

document.addEventListener('DOMContentLoaded', function() {
    // Check hash
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        showSection(hash);
    } else {
        showSection('dashboard');
    }
});

// Close modals on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
    }
});

// Close on outside click
window.onclick = function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
};

// Auto-close notification
setTimeout(() => {
    const notif = document.getElementById('notificationModal');
    if (notif && notif.style.display === 'block') {
        closeNotificationModal();
    }
}, 5000);
</script>

</body>
</html>