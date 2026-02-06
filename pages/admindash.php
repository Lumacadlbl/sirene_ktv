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
    $conn->query("DELETE FROM booking WHERE b_id=$id");
    $_SESSION['success'] = "Booking deleted successfully!";
    header("Location: admindash.php");
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

// ===== BOOKING STATUS UPDATE - SIMPLIFIED VERSION =====
if (isset($_POST['update_booking_status'])) {
    $b_id = (int)$_POST['b_id'];
    $status = trim($_POST['status']);
    
    // Debug output
    error_log("UPDATE BOOKING STATUS: ID=$b_id, Status=$status");
    
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

// ===== FETCH data =====
$users = $conn->query("SELECT * FROM user_tbl ORDER BY id DESC");
$rooms = $conn->query("SELECT * FROM room ORDER BY r_id DESC");
$foods = $conn->query("SELECT * FROM food_beverages ORDER BY category, item_name ASC");
$extras = $conn->query("SELECT * FROM extra_expense ORDER BY e_id DESC");
$bookings = $conn->query("
    SELECT b.*, u.name as user_name, r.room_name 
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
}

.logout-btn {
    background: linear-gradient(135deg, var(--highlight), #ff4757);
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
    background: linear-gradient(135deg, #ff4757, var(--highlight));
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(233, 69, 96, 0.4);
}

/* Dashboard Container */
.dashboard-container {
    display: flex;
    min-height: calc(100vh - 100px);
}

/* Sidebar */
.sidebar {
    width: 250px;
    background: rgba(22, 33, 62, 0.9);
    padding: 25px 15px;
    box-shadow: 5px 0 15px rgba(0, 0, 0, 0.2);
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
    background: rgba(233, 69, 96, 0.1);
    color: var(--highlight);
    transform: translateX(5px);
}

.menu-item.active {
    background: var(--highlight);
    color: white;
}

.menu-item i {
    width: 20px;
    text-align: center;
}

.menu-item.booking {
    margin-top: 20px;
    background: rgba(233, 69, 96, 0.1);
}

.menu-item.booking.active {
    background: var(--highlight);
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
    background: #00a085;
    transform: translateY(-2px);
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
}

.stat-box h4 {
    font-size: 24px;
    margin: 10px 0 5px;
    color: var(--highlight);
}

.stat-box p {
    font-size: 12px;
    color: #aaa;
}

/* Tables */
.table-container {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    overflow: hidden;
    margin-top: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
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
}

tbody tr {
    transition: all 0.3s;
}

tbody tr:hover {
    background: rgba(233, 69, 96, 0.05);
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-edit, .btn-delete, .btn-view {
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
    background: rgba(9, 132, 227, 0.2);
    color: var(--info);
}

.btn-edit:hover {
    background: var(--info);
    color: white;
}

.btn-delete {
    background: rgba(214, 48, 49, 0.2);
    color: var(--danger);
}

.btn-delete:hover {
    background: var(--danger);
    color: white;
}

.btn-view {
    background: rgba(0, 184, 148, 0.2);
    color: var(--success);
}

.btn-view:hover {
    background: var(--success);
    color: white;
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
    color: #ccc;
    font-weight: 500;
    font-size: 14px;
}

.form-group input,
.form-group textarea {
    width: 100%;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: var(--light);
    font-size: 15px;
    transition: all 0.3s;
}

/* Dropdown styling */
.form-group select {
    width: 100%;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: white;
    font-size: 15px;
    transition: all 0.3s;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 12px;
    padding-right: 40px;
}

/* Make dropdown options visible */
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
    box-shadow: 0 0 0 2px rgba(233, 69, 96, 0.2);
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
    background: var(--success);
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
    background: #00a085;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
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
    background: rgba(253, 203, 110, 0.2);
    color: var(--warning);
    border: 1px solid rgba(253, 203, 110, 0.3);
}

.status-approved {
    background: rgba(0, 184, 148, 0.2);
    color: var(--success);
    border: 1px solid rgba(0, 184, 148, 0.3);
}

.status-completed {
    background: rgba(9, 132, 227, 0.2);
    color: var(--info);
    border: 1px solid rgba(9, 132, 227, 0.3);
}

.status-cancelled {
    background: rgba(214, 48, 49, 0.2);
    color: var(--danger);
    border: 1px solid rgba(214, 48, 49, 0.3);
}

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
    color: #aaa;
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

/* Notification Modal Styles */
.notification-modal {
    z-index: 2000;
}

.notification-content {
    max-width: 450px;
    text-align: center;
    border: 2px solid;
}

.notification-success {
    border-color: var(--success);
}

.notification-error {
    border-color: var(--danger);
}

.notification-warning {
    border-color: var(--warning);
}

.notification-icon {
    font-size: 60px;
    margin-bottom: 20px;
}

.notification-success .notification-icon {
    color: var(--success);
}

.notification-error .notification-icon {
    color: var(--danger);
}

.notification-warning .notification-icon {
    color: var(--warning);
}

.notification-title {
    font-size: 28px;
    margin-bottom: 15px;
    color: var(--light);
}

.notification-message {
    font-size: 16px;
    line-height: 1.6;
    margin-bottom: 25px;
    color: #ddd;
}

.notification-actions {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 20px;
}

.btn-ok {
    background: var(--success);
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

.btn-ok:hover {
    background: #00a085;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0, 184, 148, 0.3);
}

.btn-confirm {
    background: var(--warning);
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

.btn-confirm:hover {
    background: #e8a822;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(253, 203, 110, 0.3);
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
    background: rgba(0, 184, 148, 0.2);
    color: var(--success);
    border: 1px solid rgba(0, 184, 148, 0.3);
}

.room-status.occupied {
    background: rgba(233, 69, 96, 0.2);
    color: var(--highlight);
    border: 1px solid rgba(233, 69, 96, 0.3);
}

.room-status.maintenance {
    background: rgba(253, 203, 110, 0.2);
    color: var(--warning);
    border: 1px solid rgba(253, 203, 110, 0.3);
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
    
    .btn-edit, .btn-delete, .btn-view {
        width: 100%;
        justify-content: center;
    }
    
    .notification-modal .modal-content {
        width: 90%;
        margin: 30px auto;
    }
}

@media (max-width: 480px) {
    .stats-cards, .quick-stats {
        grid-template-columns: 1fr;
    }
    
    .modal-actions, .notification-actions {
        flex-direction: column;
    }
    
    .btn-cancel, .btn-submit, .btn-ok, .btn-confirm {
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
                <div class="stat-card" onclick="openModal('addExtraModal')" style="cursor:pointer;">
                    <i class="fas fa-plus"></i>
                    <h3>Add Service</h3>
                    <p>Add extra service</p>
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
                                    <span style="background: <?= $row['role'] == 'admin' ? 'rgba(233, 69, 96, 0.2)' : 'rgba(9, 132, 227, 0.2)'; ?>; 
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
                                $status_color = 'var(--warning)';
                            } else {
                                $stock_status = 'In Stock';
                                $status_color = 'var(--success)';
                            }
                        ?>
                            <tr>
                                <td><?= $row['f_id'] ?></td>
                                <td><?= htmlspecialchars($row['item_name']) ?></td>
                                <td>
                                    <span style="background: rgba(9, 132, 227, 0.2); color: var(--info); 
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
                                    <span style="background: <?= $stock_status == 'In Stock' ? 'rgba(0, 184, 148, 0.2)' : ($stock_status == 'Low Stock' ? 'rgba(253, 203, 110, 0.2)' : 'rgba(214, 48, 49, 0.2)'); ?>; 
                                          color: <?= $stock_status == 'In Stock' ? 'var(--success)' : ($stock_status == 'Low Stock' ? 'var(--warning)' : 'var(--danger)'); ?>;
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
    </div>
</div>

<!-- MODALS -->

<!-- Add Room Modal -->
<div id="addRoomModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Room</h3>
            <button class="close-modal" onclick="closeModal('addRoomModal')">&times;</button>
        </div>
        <form method="post" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="room_name">Room Name</label>
                    <input type="text" id="room_name" name="room_name" placeholder="e.g., VIP Suite, Party Room" required>
                </div>
                <div class="form-group">
                    <label for="capacity">Capacity</label>
                    <input type="number" id="capacity" name="capacity" placeholder="Maximum guests" required min="1">
                </div>
                <div class="form-group">
                    <label for="price">Price Per Hour (₹)</label>
                    <input type="number" step="0.01" id="price" name="price" placeholder="Price per hour" required min="0">
                </div>
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="Available">Available</option>
                        <option value="Occupied">Occupied</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('addRoomModal')">Cancel</button>
                <button type="submit" class="btn-submit" name="add_room">
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
            <button class="close-modal" onclick="closeModal('editRoomModal')">&times;</button>
        </div>
        <form method="post" class="modal-form">
            <input type="hidden" id="edit_room_id" name="room_id">
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_room_name">Room Name</label>
                    <input type="text" id="edit_room_name" name="room_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_capacity">Capacity</label>
                    <input type="number" id="edit_capacity" name="capacity" required min="1">
                </div>
                <div class="form-group">
                    <label for="edit_price">Price Per Hour (₹)</label>
                    <input type="number" step="0.01" id="edit_price" name="price" required min="0">
                </div>
                <div class="form-group">
                    <label for="edit_status">Status</label>
                    <select id="edit_status" name="status" required>
                        <option value="Available">Available</option>
                        <option value="Occupied">Occupied</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editRoomModal')">Cancel</button>
                <button type="submit" class="btn-submit" name="update_room">
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
            <h3><i class="fas fa-boxes"></i> Update Stock Quantity</h3>
            <button class="close-modal" onclick="closeModal('updateStockModal')">&times;</button>
        </div>
        <form method="post" class="modal-form">
            <input type="hidden" id="update_food_id" name="food_id">
            <div class="form-group">
                <label for="item_name_display">Item Name</label>
                <input type="text" id="item_name_display" readonly style="background: rgba(255,255,255,0.05); color: #aaa;">
            </div>
            <div class="form-group">
                <label for="current_stock_display">Current Stock</label>
                <input type="text" id="current_stock_display" readonly style="background: rgba(255,255,255,0.05); color: #aaa;">
            </div>
            <div class="form-group">
                <label for="stock">New Stock Quantity</label>
                <input type="number" id="stock" name="stock" placeholder="Enter new stock quantity" required min="0" step="1">
                <small style="color: #aaa; font-size: 12px; margin-top: 5px; display: block;">
                    Enter 0 if item is out of stock
                </small>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('updateStockModal')">Cancel</button>
                <button type="submit" class="btn-submit" name="update_food">
                    <i class="fas fa-save"></i> Update Stock
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Add Extra Modal -->
<div id="addExtraModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plus-circle"></i> Add Extra Service</h3>
            <button class="close-modal" onclick="closeModal('addExtraModal')">&times;</button>
        </div>
        <form method="post" class="modal-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="name">Service Name</label>
                    <input type="text" id="name" name="name" placeholder="e.g., Birthday Package, Karaoke Machine" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <input type="text" id="description" name="description" placeholder="Brief description" required>
                </div>
                <div class="form-group">
                    <label for="price">Price (₹)</label>
                    <input type="number" step="0.01" id="price" name="price" placeholder="Service price" required min="0">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('addExtraModal')">Cancel</button>
                <button type="submit" class="btn-submit" name="add_extra">
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
            <h3><i class="fas fa-calendar-alt"></i> Booking Details</h3>
            <button class="close-modal" onclick="closeModal('bookingModal')">&times;</button>
        </div>
        <div id="bookingDetails">
            <!-- Dynamic content will be inserted here -->
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" class="modal notification-modal">
    <div class="modal-content notification-content notification-warning">
        <div class="modal-header">
            <h3><i class="fas fa-question-circle"></i> Confirm Action</h3>
            <button class="close-modal" onclick="closeConfirmModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="notification-icon">
                <i class="fas fa-question-circle"></i>
            </div>
            <div class="notification-message" id="confirmMessage">
                <!-- Message will be inserted here -->
            </div>
            <div class="notification-actions">
                <button type="button" class="btn-cancel" onclick="closeConfirmModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="button" class="btn-confirm" id="confirmActionBtn">
                    <i class="fas fa-check"></i> Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notification Modal -->
<div id="notificationModal" class="modal notification-modal" style="display: <?php echo $show_modal ? 'block' : 'none'; ?>;">
    <div class="modal-content notification-content <?php echo $modal_type == 'success' ? 'notification-success' : 'notification-error'; ?>">
        <div class="modal-header">
            <h3>
                <i class="fas <?php echo $modal_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                <?php echo $modal_type == 'success' ? 'Success!' : 'Error!'; ?>
            </h3>
            <button class="close-modal" onclick="closeNotificationModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="notification-icon">
                <i class="fas <?php echo $modal_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            </div>
            <div class="notification-message">
                <?php echo htmlspecialchars($modal_message); ?>
            </div>
            <div class="notification-actions">
                <button type="button" class="btn-ok" onclick="closeNotificationModal()">
                    <i class="fas fa-check"></i> OK
                </button>
            </div>
        </div>
    </div>
</div>

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
        return false;
    });
    
    if (activeMenuItem) {
        activeMenuItem.classList.add('active');
    }
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

// Show booking modal with details - FIXED VERSION
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
    // Set active section based on URL hash
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        showSection(hash);
    } else {
        showSection('dashboard');
    }
});
</script>

</body>
</html>