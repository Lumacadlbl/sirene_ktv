<?php
session_start();
include "../db.php";

// Only allow admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location:login.php");
    exit;
}

// Display success/error messages
$success_message = '';
$error_message = '';

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
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
    $stmt->execute();
    $_SESSION['success'] = "Room added successfully!";
    header("Location: admindash.php");
    exit;
}

if (isset($_POST['add_food'])) {
    $item_name = $_POST['item_name'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    $stmt = $conn->prepare("INSERT INTO food_beverages (item_name, category, price, stock) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssdi", $item_name, $category, $price, $stock);
    $stmt->execute();
    $_SESSION['success'] = "Food item added successfully!";
    header("Location: admindash.php");
    exit;
}

if (isset($_POST['add_extra'])) {
    $name = $_POST['name'];
    $description = $_POST['description'];
    $price = $_POST['price'];

    $stmt = $conn->prepare("INSERT INTO extra_expense (name, description, price) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $name, $description, $price);
    $stmt->execute();
    $_SESSION['success'] = "Extra service added successfully!";
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
    $stmt->execute();
    $_SESSION['success'] = "Room updated successfully!";
    header("Location: admindash.php");
    exit;
}

if (isset($_POST['update_food'])) {
    $food_id = $_POST['food_id'];
    $item_name = $_POST['item_name'];
    $category = $_POST['category'];
    $price = $_POST['price'];
    $stock = $_POST['stock'];

    $stmt = $conn->prepare("UPDATE food_beverages SET item_name=?, category=?, price=?, stock=? WHERE f_id=?");
    $stmt->bind_param("ssdii", $item_name, $category, $price, $stock, $food_id);
    $stmt->execute();
    $_SESSION['success'] = "Food item updated successfully!";
    header("Location: admindash.php");
    exit;
}

if (isset($_POST['update_booking_status'])) {
    $b_id = $_POST['b_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE booking SET status=? WHERE b_id=?");
    $stmt->bind_param("si", $status, $b_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Booking #$b_id status updated to: $status";
    } else {
        $_SESSION['error'] = "Failed to update booking status: " . $conn->error;
    }
    
    header("Location: admindash.php");
    exit;
}

// ===== FETCH data =====
$users = $conn->query("SELECT * FROM user_tbl ORDER BY id DESC");
$rooms = $conn->query("SELECT * FROM room ORDER BY r_id DESC");
$foods = $conn->query("SELECT * FROM food_beverages ORDER BY f_id DESC");
$extras = $conn->query("SELECT * FROM extra_expense ORDER BY e_id DESC");
$bookings = $conn->query("
    SELECT b.*, u.name as user_name, r.room_name 
    FROM booking b 
    JOIN user_tbl u ON b.u_id = u.id 
    JOIN room r ON b.r_id = r.r_id 
    ORDER BY b.booking_date DESC
");

// Reset result pointers
$users->data_seek(0);
$rooms->data_seek(0);
$foods->data_seek(0);
$extras->data_seek(0);
$bookings->data_seek(0);

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

/* Forms */
.form-container {
    background: rgba(255, 255, 255, 0.05);
    padding: 25px;
    border-radius: 15px;
    margin-top: 20px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
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
    color: black !important;
    font-size: 15px;
    transition: all 0.3s;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='black'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 15px center;
    background-size: 12px;
    padding-right: 40px;
}

/* Make dropdown options visible */
.form-group select option {
    background-color: white !important;
    color: black !important;
    padding: 10px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: var(--highlight);
    background: rgba(255, 255, 255, 0.12);
}

.form-actions {
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
}

.btn-submit:hover {
    background: #00a085;
    transform: translateY(-2px);
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
}

.modal-content {
    background: var(--secondary);
    margin: 5% auto;
    padding: 30px;
    border-radius: 15px;
    width: 80%;
    max-width: 600px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
    position: relative;
    border: 2px solid var(--highlight);
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
    color: #aaa;
    font-size: 28px;
    cursor: pointer;
    transition: all 0.3s;
}

.close-modal:hover {
    color: var(--highlight);
    transform: rotate(90deg);
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
    background: rgba(255, 255, 255, 0.08) url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='black'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e") no-repeat right 15px center / 12px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    color: black !important;
    font-size: 15px;
    margin: 15px 0;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
}

.status-select option {
    background-color: white !important;
    color: black !important;
    padding: 10px;
}

.status-select:focus {
    outline: none;
    border-color: var(--highlight);
}

.modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Alert Messages */
.alert-message {
    margin: 20px 30px;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
    font-weight: 500;
    animation: slideDown 0.3s ease;
}

.alert-success {
    background: rgba(0, 184, 148, 0.2);
    color: var(--success);
    border: 1px solid rgba(0, 184, 148, 0.3);
}

.alert-error {
    background: rgba(214, 48, 49, 0.2);
    color: var(--danger);
    border: 1px solid rgba(214, 48, 49, 0.3);
}

@keyframes slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
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
        margin: 10% auto;
        padding: 20px;
    }
}

@media (max-width: 480px) {
    .stats-cards, .quick-stats {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 5px;
    }
    
    .btn-edit, .btn-delete, .btn-view {
        width: 100%;
        justify-content: center;
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

<!-- Success/Error Messages -->
<?php if (!empty($success_message)): ?>
    <div class="alert-message alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if (!empty($error_message)): ?>
    <div class="alert-message alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

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
        <button class="menu-item" onclick="showSection('roomsGallery')">
            <i class="fas fa-images"></i> Rooms Gallery
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
                    <p>Food Items</p>
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
                <div class="stat-card" onclick="showSection('rooms')" style="cursor:pointer;">
                    <i class="fas fa-plus-circle"></i>
                    <h3>Add Room</h3>
                    <p>Create new KTV room</p>
                </div>
                <div class="stat-card" onclick="showSection('foods')" style="cursor:pointer;">
                    <i class="fas fa-hamburger"></i>
                    <h3>Add Food</h3>
                    <p>Add new menu item</p>
                </div>
                <div class="stat-card" onclick="showSection('bookings')" style="cursor:pointer;">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Manage Bookings</h3>
                    <p>Approve/View bookings</p>
                </div>
                <div class="stat-card" onclick="showSection('roomsGallery')" style="cursor:pointer;">
                    <i class="fas fa-eye"></i>
                    <h3>View Rooms</h3>
                    <p>See all KTV rooms</p>
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
                        <?php while($row = $users->fetch_assoc()): ?>
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
                                    <a href="admindash.php?delete_user=<?= $row['id'] ?>" 
                                       onclick="return confirm('Are you sure you want to delete this user?');">
                                        <button class="btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </a>
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
                <button class="add-btn" onclick="showAddForm('room')">
                    <i class="fas fa-plus"></i> Add New Room
                </button>
            </div>
            
            <!-- Add Room Form -->
            <div id="addRoomForm" class="form-container" style="display:none;">
                <h3><i class="fas fa-plus-circle"></i> Add New KTV Room</h3>
                <form method="post">
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
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="hideAddForm('room')">Cancel</button>
                        <button type="submit" class="btn-submit" name="add_room">
                            <i class="fas fa-save"></i> Add Room
                        </button>
                    </div>
                </form>
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
                                    <a href="admindash.php?delete_room=<?= $row['r_id'] ?>" 
                                       onclick="return confirm('Are you sure you want to delete this room?');">
                                        <button class="btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </a>
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
                <button class="add-btn" onclick="showAddForm('food')">
                    <i class="fas fa-plus"></i> Add New Item
                </button>
            </div>
            
            <!-- Add Food Form -->
            <div id="addFoodForm" class="form-container" style="display:none;">
                <h3><i class="fas fa-plus-circle"></i> Add New Food/Beverage Item</h3>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="item_name">Item Name</label>
                            <input type="text" id="item_name" name="item_name" placeholder="e.g., Chicken Wings, Mojito" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category</label>
                            <select id="category" name="category" required>
                                <option value="Appetizer">Appetizer</option>
                                <option value="Main Course">Main Course</option>
                                <option value="Dessert">Dessert</option>
                                <option value="Beverage">Beverage</option>
                                <option value="Alcoholic">Alcoholic</option>
                                <option value="Snacks">Snacks</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="price">Price (₹)</label>
                            <input type="number" step="0.01" id="price" name="price" placeholder="Item price" required min="0">
                        </div>
                        <div class="form-group">
                            <label for="stock">Stock Quantity</label>
                            <input type="number" id="stock" name="stock" placeholder="Available quantity" required min="0">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="hideAddForm('food')">Cancel</button>
                        <button type="submit" class="btn-submit" name="add_food">
                            <i class="fas fa-save"></i> Add Item
                        </button>
                    </div>
                </form>
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $foods->data_seek(0);
                        while($row = $foods->fetch_assoc()): ?>
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
                                    <span style="background: <?= $row['stock'] > 10 ? 'rgba(0, 184, 148, 0.2)' : 'rgba(253, 203, 110, 0.2)'; ?>; 
                                          color: <?= $row['stock'] > 10 ? 'var(--success)' : 'var(--warning)'; ?>;
                                          padding: 5px 10px; border-radius: 5px; font-size: 12px;">
                                        <?= $row['stock'] ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button class="btn-edit" onclick="showEditFoodModal(<?= $row['f_id'] ?>, '<?= htmlspecialchars($row['item_name']) ?>', '<?= $row['category'] ?>', <?= $row['price'] ?>, <?= $row['stock'] ?>)">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <a href="admindash.php?delete_food=<?= $row['f_id'] ?>" 
                                       onclick="return confirm('Are you sure you want to delete this item?');">
                                        <button class="btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </a>
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
                <button class="add-btn" onclick="showAddForm('extra')">
                    <i class="fas fa-plus"></i> Add New Service
                </button>
            </div>
            
            <!-- Add Extra Form -->
            <div id="addExtraForm" class="form-container" style="display:none;">
                <h3><i class="fas fa-plus-circle"></i> Add New Extra Service</h3>
                <form method="post">
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
                    <div class="form-actions">
                        <button type="button" class="btn-cancel" onclick="hideAddForm('extra')">Cancel</button>
                        <button type="submit" class="btn-submit" name="add_extra">
                            <i class="fas fa-save"></i> Add Service
                        </button>
                    </div>
                </form>
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
                                    <a href="admindash.php?delete_extra=<?= $row['e_id'] ?>" 
                                       onclick="return confirm('Are you sure you want to delete this service?');">
                                        <button class="btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </a>
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
                                    <a href="admindash.php?delete_booking=<?= $row['b_id'] ?>" 
                                       onclick="return confirm('Are you sure you want to delete this booking?');">
                                        <button class="btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Rooms Gallery Section -->
        <div id="roomsGallery" class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-images"></i> KTV Rooms Gallery</h2>
                <span class="welcome-message">
                    <i class="fas fa-door-open"></i> Total: <?php echo $rooms_count; ?> Rooms
                </span>
            </div>
            
            <div class="rooms-gallery">
                <!-- Room cards here -->
            </div>
        </div>
    </div>
</div>

<!-- MODALS -->
<!-- Edit Room Modal -->
<div id="editRoomModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Room</h3>
            <button class="close-modal" onclick="closeModal('editRoomModal')">&times;</button>
        </div>
        <form method="post">
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

<!-- Edit Food Modal -->
<div id="editFoodModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Food/Beverage Item</h3>
            <button class="close-modal" onclick="closeModal('editFoodModal')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" id="edit_food_id" name="food_id">
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_item_name">Item Name</label>
                    <input type="text" id="edit_item_name" name="item_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_category">Category</label>
                    <select id="edit_category" name="category" required>
                        <option value="Appetizer">Appetizer</option>
                        <option value="Main Course">Main Course</option>
                        <option value="Dessert">Dessert</option>
                        <option value="Beverage">Beverage</option>
                        <option value="Alcoholic">Alcoholic</option>
                        <option value="Snacks">Snacks</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_food_price">Price (₹)</label>
                    <input type="number" step="0.01" id="edit_food_price" name="price" required min="0">
                </div>
                <div class="form-group">
                    <label for="edit_stock">Stock Quantity</label>
                    <input type="number" id="edit_stock" name="stock" required min="0">
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('editFoodModal')">Cancel</button>
                <button type="submit" class="btn-submit" name="update_food">
                    <i class="fas fa-save"></i> Update Item
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

<script>
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
        if (sectionId === 'roomsGallery' && text.includes('images')) return true;
        return false;
    });
    
    if (activeMenuItem) {
        activeMenuItem.classList.add('active');
    }
    
    // Hide any open forms
    hideAllForms();
}

// Show add form for specific type
function showAddForm(type) {
    hideAllForms();
    const formId = `add${type.charAt(0).toUpperCase() + type.slice(1)}Form`;
    const form = document.getElementById(formId);
    if (form) {
        form.style.display = 'block';
        form.scrollIntoView({behavior: 'smooth'});
    }
}

// Hide add form for specific type
function hideAddForm(type) {
    const formId = `add${type.charAt(0).toUpperCase() + type.slice(1)}Form`;
    const form = document.getElementById(formId);
    if (form) {
        form.style.display = 'none';
    }
}

// Hide all forms
function hideAllForms() {
    const forms = document.querySelectorAll('.form-container');
    forms.forEach(form => {
        form.style.display = 'none';
    });
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

// Show edit food modal with data
function showEditFoodModal(id, name, category, price, stock) {
    document.getElementById('edit_food_id').value = id;
    document.getElementById('edit_item_name').value = name;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_food_price').value = price;
    document.getElementById('edit_stock').value = stock;
    openModal('editFoodModal');
}

// Show booking modal with details - FIXED for b_id
function showBookingModal(bookingData) {
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
        
        <form method="post" id="bookingStatusForm" onsubmit="return confirm('Are you sure you want to update this booking status?')">
            <input type="hidden" name="b_id" value="${bookingData.b_id}">
            <div class="modal-actions" style="flex-direction: column; align-items: stretch;">
                <h4>Change Status:</h4>
                <select class="status-select" name="status" required>
                    ${statusOptions.map(option => 
                        `<option value="${option}" ${option === bookingData.status ? 'selected' : ''}>${option}</option>`
                    ).join('')}
                </select>
                <div style="display: flex; gap: 15px; margin-top: 20px;">
                    <button type="button" class="btn-cancel" onclick="closeModal('bookingModal')">Close</button>
                    <button type="submit" class="btn-submit" name="update_booking_status">
                        <i class="fas fa-save"></i> Update Status
                    </button>
                </div>
            </div>
        </form>
    `;
    
    openModal('bookingModal');
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

// Close modal when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target == modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    });
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    // Set active section based on URL hash
    const hash = window.location.hash.substring(1);
    if (hash && document.getElementById(hash)) {
        showSection(hash);
    } else {
        showSection('dashboard');
    }
    
    // Auto-hide success messages after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert-message');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
});
</script>

</body>
</html>