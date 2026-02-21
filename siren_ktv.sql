-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 21, 2026 at 04:00 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `siren_ktv`
--

-- --------------------------------------------------------

--
-- Table structure for table `booking`
--

CREATE TABLE `booking` (
  `b_id` int(11) NOT NULL,
  `u_id` int(11) NOT NULL,
  `r_id` int(11) NOT NULL,
  `booking_date` datetime NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `hours` int(50) NOT NULL,
  `room_amount` decimal(10,0) NOT NULL,
  `food_amount` decimal(10,0) NOT NULL,
  `subtotal` decimal(10,0) NOT NULL,
  `tax_amount` decimal(10,0) NOT NULL,
  `total_amount` decimal(10,0) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(100) NOT NULL,
  `payment_status` varchar(100) NOT NULL,
  `downpayment` decimal(10,2) DEFAULT NULL,
  `paymongo_payment_id` varchar(100) DEFAULT NULL,
  `created_at` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`b_id`, `u_id`, `r_id`, `booking_date`, `start_time`, `end_time`, `hours`, `room_amount`, `food_amount`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `status`, `payment_status`, `downpayment`, `paymongo_payment_id`, `created_at`) VALUES
(28, 13, 7, '2026-02-22 00:00:00', '18:00:00', '20:00:00', 2, 240, 0, 240, 24, 264, NULL, 'confirmed', 'pending_payment', NULL, 'cs_58de5a2ee2d4c2a7e55cf72f', 2147483647);

-- --------------------------------------------------------

--
-- Table structure for table `booking_food`
--

CREATE TABLE `booking_food` (
  `bf_id` int(11) NOT NULL,
  `b_id` int(11) NOT NULL,
  `f_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `served` enum('pending','served','cancelled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `extra_expense`
--

CREATE TABLE `extra_expense` (
  `e_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(100) NOT NULL,
  `price` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `food_beverages`
--

CREATE TABLE `food_beverages` (
  `f_id` int(11) NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_beverages`
--

INSERT INTO `food_beverages` (`f_id`, `item_name`, `category`, `price`, `stock`, `created_at`) VALUES
(4, 'Spring Rolls (Veg)', 'Appetizer', 220.00, 40, '2026-02-06 08:09:40'),
(5, 'Cheese Balls', 'Appetizer', 200.00, 45, '2026-02-06 08:09:40'),
(7, 'Chicken Wings', 'Appetizer', 320.00, 35, '2026-02-06 08:09:40'),
(8, 'Paneer Tikka', 'Appetizer', 280.00, 40, '2026-02-06 08:09:40'),
(10, 'Chicken Lollipop', 'Appetizer', 300.00, 30, '2026-02-06 08:09:40'),
(12, 'Chicken Biryani', 'Main Course', 350.00, 30, '2026-02-06 08:09:40'),
(14, 'Paneer Butter Masala', 'Main Course', 320.00, 35, '2026-02-06 08:09:40'),
(15, 'Fish & Chips', 'Main Course', 400.00, 20, '2026-02-06 08:09:40'),
(18, 'Veg Hakka Noodles', 'Main Course', 280.00, 40, '2026-02-06 08:09:40'),
(22, 'Chicken Burger', 'Snacks', 280.00, 40, '2026-02-06 08:09:40'),
(25, 'Nachos with Cheese', 'Snacks', 300.00, 30, '2026-02-06 08:09:40'),
(27, 'Chicken Wrap', 'Snacks', 260.00, 35, '2026-02-06 08:09:40'),
(29, 'Masala Fries', 'Snacks', 210.00, 40, '2026-02-06 08:09:40'),
(31, 'Chicken Hot Dog', 'Snacks', 220.00, 40, '2026-02-06 08:09:40'),
(32, 'Coca-Cola (500ml)', 'Beverage', 80.00, 100, '2026-02-06 08:09:40'),
(33, 'Fresh Lime Soda', 'Beverage', 100.00, 80, '2026-02-06 08:09:40'),
(34, 'Iced Tea', 'Beverage', 120.00, 70, '2026-02-06 08:09:40'),
(35, 'Virgin Mojito', 'Beverage', 150.00, 60, '2026-02-06 08:09:40'),
(36, 'Hot Coffee', 'Beverage', 90.00, 90, '2026-02-06 08:09:40'),
(43, 'Whisky (60ml)', 'Alcoholic', 350.00, 60, '2026-02-06 08:09:40'),
(47, 'Tequila Shot', 'Alcoholic', 200.00, 70, '2026-02-06 08:09:40'),
(49, 'Gin (60ml)', 'Alcoholic', 340.00, 50, '2026-02-06 08:09:40'),
(50, 'Brandy (60ml)', 'Alcoholic', 320.00, 45, '2026-02-06 08:09:40'),
(51, 'Champagne (Glass)', 'Alcoholic', 400.00, 30, '2026-02-06 08:09:40'),
(53, 'Ice Cream Sundae', 'Dessert', 220.00, 35, '2026-02-06 08:09:40'),
(54, 'Cheesecake Slice', 'Dessert', 250.00, 30, '2026-02-06 08:09:40'),
(55, 'Chocolate Mousse', 'Dessert', 200.00, 45, '2026-02-06 08:09:40'),
(56, 'Fruit Salad', 'Dessert', 150.00, 50, '2026-02-06 08:09:40'),
(57, 'Gulab Jamun', 'Dessert', 120.00, 60, '2026-02-06 08:09:40');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `p_id` int(11) NOT NULL,
  `b_id` int(11) NOT NULL,
  `u_id` int(11) NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `payment_status` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`p_id`, `b_id`, `u_id`, `payment_method`, `payment_status`, `amount`, `payment_date`) VALUES
(15, 28, 13, 'card', 'pending', 264.00, '2026-02-21 02:40:30'),
(16, 28, 13, 'paymaya', 'pending', 264.00, '2026-02-21 02:41:55');

-- --------------------------------------------------------

--
-- Table structure for table `room`
--

CREATE TABLE `room` (
  `r_id` int(11) NOT NULL,
  `room_name` varchar(100) NOT NULL,
  `capcity` int(50) NOT NULL,
  `price_hr` int(50) NOT NULL,
  `status` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `room`
--

INSERT INTO `room` (`r_id`, `room_name`, `capcity`, `price_hr`, `status`, `created_at`) VALUES
(6, 'VIP', 6, 200, 'Available', '2026-02-20 12:51:37'),
(7, 'Party room', 12, 120, 'Available', '2026-02-20 12:51:33');

-- --------------------------------------------------------

--
-- Table structure for table `user_tbl`
--

CREATE TABLE `user_tbl` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `country_code` varchar(10) DEFAULT NULL,
  `age` int(50) NOT NULL,
  `role` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_tbl`
--

INSERT INTO `user_tbl` (`id`, `name`, `email`, `password`, `contact`, `country_code`, `age`, `role`) VALUES
(12, 'Le Bron', 'le@gmail.com', '$2y$10$WTlhNTUO6Umd2q1gkwMFzOdCvAe5OvGCbleDg9Ogj0j4MX36/K5XO', '9663675293', '+63', 19, 'admin'),
(13, 'James', 'james@gmail.com', '$2y$10$1QrgZwkK5kVf/t8bkZjB.e66gaSR6KNoXhOxC3ucUye7AYTniUKdO', '754345678', '+34', 19, 'user'),
(14, 'Yvan', 'yvan@gmail.com', '$2y$10$GdlFrq44FpPttXmRM/tDpOIIkA7Wf/8UdtF0F/zYTiQUBr0wvP9N6', '123456789', '+971', 19, 'user');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `booking`
--
ALTER TABLE `booking`
  ADD PRIMARY KEY (`b_id`),
  ADD KEY `u_id` (`u_id`),
  ADD KEY `r_id` (`r_id`);

--
-- Indexes for table `booking_food`
--
ALTER TABLE `booking_food`
  ADD PRIMARY KEY (`bf_id`),
  ADD KEY `booking_food_ibfk_1` (`f_id`),
  ADD KEY `booking_food_ibfk_2` (`b_id`);

--
-- Indexes for table `extra_expense`
--
ALTER TABLE `extra_expense`
  ADD PRIMARY KEY (`e_id`);

--
-- Indexes for table `food_beverages`
--
ALTER TABLE `food_beverages`
  ADD PRIMARY KEY (`f_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`p_id`),
  ADD KEY `u_id` (`u_id`),
  ADD KEY `payments_ibfk_2` (`b_id`);

--
-- Indexes for table `room`
--
ALTER TABLE `room`
  ADD PRIMARY KEY (`r_id`);

--
-- Indexes for table `user_tbl`
--
ALTER TABLE `user_tbl`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `booking`
--
ALTER TABLE `booking`
  MODIFY `b_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `booking_food`
--
ALTER TABLE `booking_food`
  MODIFY `bf_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `extra_expense`
--
ALTER TABLE `extra_expense`
  MODIFY `e_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `food_beverages`
--
ALTER TABLE `food_beverages`
  MODIFY `f_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `p_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `room`
--
ALTER TABLE `room`
  MODIFY `r_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `user_tbl`
--
ALTER TABLE `user_tbl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking`
--
ALTER TABLE `booking`
  ADD CONSTRAINT `booking_ibfk_1` FOREIGN KEY (`u_id`) REFERENCES `user_tbl` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_ibfk_2` FOREIGN KEY (`r_id`) REFERENCES `room` (`r_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `booking_food`
--
ALTER TABLE `booking_food`
  ADD CONSTRAINT `booking_food_ibfk_1` FOREIGN KEY (`f_id`) REFERENCES `food_beverages` (`f_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `booking_food_ibfk_2` FOREIGN KEY (`b_id`) REFERENCES `booking` (`b_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`u_id`) REFERENCES `user_tbl` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`b_id`) REFERENCES `booking` (`b_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
