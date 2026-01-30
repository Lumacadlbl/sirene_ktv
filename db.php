<?php
$host = "localhost";
$user = "root";
$pass = "";        // Default password in XAMPP is empty
$db   = "siren_ktv";

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
