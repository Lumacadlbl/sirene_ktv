-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 14, 2026 at 11:42 AM
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
  `store_payment_id` varchar(100) DEFAULT NULL,
  `created_at` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking`
--

INSERT INTO `booking` (`b_id`, `u_id`, `r_id`, `booking_date`, `start_time`, `end_time`, `hours`, `room_amount`, `food_amount`, `subtotal`, `tax_amount`, `total_amount`, `notes`, `status`, `payment_status`, `downpayment`, `paymongo_payment_id`, `store_payment_id`, `created_at`) VALUES
(44, 17, 11, '2026-03-06 00:00:00', '18:00:00', '20:00:00', 2, 2000, 0, 2000, 200, 450, NULL, 'confirmed', 'paid', 2200.00, 'cs_c728db90df7e2345ddd851ef', NULL, 2147483647),
(47, 13, 11, '2026-03-09 00:00:00', '18:00:00', '20:00:00', 2, 2000, 0, 2000, 200, 2200, NULL, 'confirmed', 'paid', 2200.00, 'cs_179621e7a9b7938516ae1d18', NULL, 2147483647),
(48, 13, 12, '2026-03-08 00:00:00', '20:34:00', '22:00:00', 1, 1050, 0, 1050, 105, 1155, NULL, 'confirmed', 'paid', 1155.00, 'cs_7afb708589734212aed33860', NULL, 2147483647),
(49, 16, 10, '2026-03-08 00:00:00', '22:00:00', '23:00:00', 1, 700, 0, 700, 70, -980, NULL, 'confirmed', 'paid', 770.00, 'cs_2a05273d7ec67cc64eb6ef3d', NULL, 2147483647),
(50, 13, 11, '2026-03-14 00:00:00', '18:00:00', '20:00:00', 2, 2000, 0, 2000, 200, 600, NULL, 'confirmed', 'paid', 2200.00, 'cs_0ef15601e7cbfc5e89add1f5', NULL, 2147483647);

-- --------------------------------------------------------

--
-- Table structure for table `booking_food`
--

CREATE TABLE `booking_food` (
  `bf_id` int(11) NOT NULL,
  `table_num` int(11) DEFAULT NULL,
  `f_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `served` enum('pending','served','cancelled') DEFAULT 'pending',
  `payment_id` varchar(100) DEFAULT NULL,
  `order_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `manual_timer_minutes` int(11) DEFAULT 15 COMMENT 'Manual timer set by admin in minutes',
  `preparation_time` int(11) DEFAULT 15 COMMENT 'Preparation time in minutes for this specific order',
  `is_preorder` tinyint(4) DEFAULT 0 COMMENT '1 if pre-ordered with booking, 0 if ordered during session',
  `food_payment_status` enum('pending','paid','cancelled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_food`
--

INSERT INTO `booking_food` (`bf_id`, `table_num`, `f_id`, `quantity`, `price`, `served`, `payment_id`, `order_time`, `manual_timer_minutes`, `preparation_time`, `is_preorder`, `food_payment_status`) VALUES
(66, 0, 49, 4, 340.00, 'pending', NULL, '2026-03-14 09:06:57', 15, 15, 0, 'pending'),
(67, 0, 7, 3, 320.00, 'pending', NULL, '2026-03-14 09:42:47', 15, 15, 0, 'pending'),
(68, 0, 5, 3, 200.00, 'pending', NULL, '2026-03-14 09:42:47', 15, 15, 0, 'pending'),
(74, 1, 49, 2, 340.00, 'pending', NULL, '2026-03-14 10:20:08', 15, 15, 0, 'pending'),
(75, 1, 50, 3, 320.00, 'pending', NULL, '2026-03-14 10:20:08', 15, 15, 0, 'pending');

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `preparation_time` int(11) DEFAULT 15 COMMENT 'Preparation time in minutes'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `food_beverages`
--

INSERT INTO `food_beverages` (`f_id`, `item_name`, `category`, `price`, `stock`, `created_at`, `preparation_time`) VALUES
(4, 'Spring Rolls (Veg)', 'Appetizer', 220.00, 44, '2026-02-06 08:09:40', 15),
(5, 'Cheese Balls', 'Appetizer', 200.00, 45, '2026-02-06 08:09:40', 15),
(7, 'Chicken Wings', 'Appetizer', 320.00, 35, '2026-02-06 08:09:40', 15),
(8, 'Paneer Tikka', 'Appetizer', 280.00, 46, '2026-02-06 08:09:40', 15),
(10, 'Chicken Lollipop', 'Appetizer', 300.00, 30, '2026-02-06 08:09:40', 15),
(12, 'Chicken Biryani', 'Main Course', 350.00, 30, '2026-02-06 08:09:40', 15),
(14, 'Paneer Butter Masala', 'Main Course', 320.00, 40, '2026-02-06 08:09:40', 15),
(15, 'Fish & Chips', 'Main Course', 400.00, 20, '2026-02-06 08:09:40', 15),
(18, 'Veg Hakka Noodles', 'Main Course', 280.00, 40, '2026-02-06 08:09:40', 15),
(22, 'Chicken Burger', 'Snacks', 280.00, 40, '2026-02-06 08:09:40', 15),
(25, 'Nachos with Cheese', 'Snacks', 300.00, 30, '2026-02-06 08:09:40', 15),
(27, 'Chicken Wrap', 'Snacks', 260.00, 35, '2026-02-06 08:09:40', 15),
(29, 'Masala Fries', 'Snacks', 210.00, 40, '2026-02-06 08:09:40', 15),
(31, 'Chicken Hot Dog', 'Snacks', 220.00, 40, '2026-02-06 08:09:40', 15),
(32, 'Coca-Cola (500ml)', 'Beverage', 80.00, 100, '2026-02-06 08:09:40', 15),
(33, 'Fresh Lime Soda', 'Beverage', 100.00, 80, '2026-02-06 08:09:40', 15),
(34, 'Iced Tea', 'Beverage', 120.00, 70, '2026-02-06 08:09:40', 15),
(35, 'Virgin Mojito', 'Beverage', 150.00, 60, '2026-02-06 08:09:40', 15),
(36, 'Hot Coffee', 'Beverage', 90.00, 96, '2026-02-06 08:09:40', 15),
(43, 'Whisky (60ml)', 'Alcoholic', 350.00, 67, '2026-02-06 08:09:40', 15),
(47, 'Tequila Shot', 'Alcoholic', 200.00, 78, '2026-02-06 08:09:40', 15),
(49, 'Gin (60ml)', 'Alcoholic', 340.00, 56, '2026-02-06 08:09:40', 15),
(50, 'Brandy (60ml)', 'Alcoholic', 320.00, 45, '2026-02-06 08:09:40', 15),
(51, 'Champagne (Glass)', 'Alcoholic', 400.00, 30, '2026-02-06 08:09:40', 15),
(53, 'Ice Cream Sundae', 'Dessert', 220.00, 38, '2026-02-06 08:09:40', 15),
(54, 'Cheesecake Slice', 'Dessert', 250.00, 30, '2026-02-06 08:09:40', 15),
(55, 'Chocolate Mousse', 'Dessert', 200.00, 46, '2026-02-06 08:09:40', 15),
(56, 'Fruit Salad', 'Dessert', 150.00, 50, '2026-02-06 08:09:40', 15),
(57, 'Gulab Jamun', 'Dessert', 120.00, 65, '2026-02-06 08:09:40', 15);

-- --------------------------------------------------------

--
-- Table structure for table `food_payments`
--

CREATE TABLE `food_payments` (
  `fp_id` int(11) NOT NULL,
  `bf_id` int(11) NOT NULL,
  `b_id` int(11) NOT NULL,
  `u_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_status` enum('pending','paid','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `p_id` int(11) NOT NULL,
  `b_id` int(11) NOT NULL,
  `f_id` int(11) DEFAULT NULL,
  `u_id` int(11) NOT NULL,
  `payment_method` varchar(100) NOT NULL,
  `payment_status` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`p_id`, `b_id`, `f_id`, `u_id`, `payment_method`, `payment_status`, `amount`, `payment_date`) VALUES
(46, 44, NULL, 17, 'gcash', 'paid', 2200.00, '2026-03-05 05:56:02'),
(48, 47, NULL, 13, 'paymaya', 'paid', 2200.00, '2026-03-08 12:30:39'),
(49, 48, NULL, 13, 'gcash', 'paid', 2555.00, '2026-03-08 12:33:22'),
(50, 49, NULL, 16, 'gcash', 'paid', 2520.00, '2026-03-08 14:08:19'),
(51, 50, NULL, 13, 'gcash', 'paid', 2200.00, '2026-03-13 05:40:07'),
(52, 50, NULL, 13, 'gcash', 'pending', 1600.00, '2026-03-13 07:41:49'),
(53, 50, NULL, 13, 'gcash', 'pending', 1050.00, '2026-03-14 08:40:20');

-- --------------------------------------------------------

--
-- Table structure for table `preorders`
--

CREATE TABLE `preorders` (
  `po_id` int(11) NOT NULL,
  `b_id` int(11) NOT NULL,
  `f_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('pending','prepared','cancelled') DEFAULT 'pending',
  `payment_id` varchar(100) DEFAULT NULL,
  `order_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `preparation_time` int(11) DEFAULT 15,
  `scheduled_for` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `preorders`
--

INSERT INTO `preorders` (`po_id`, `b_id`, `f_id`, `quantity`, `price`, `status`, `payment_id`, `order_time`, `preparation_time`, `scheduled_for`, `completed_at`, `notes`) VALUES
(1, 50, 43, 3, 350.00, 'pending', 'cs_0ef15601e7cbfc5e89add1f5', '2026-03-14 07:42:36', 15, '2026-03-14 00:00:00', NULL, NULL);

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
(7, 'Party room', 12, 120, 'Available', '2026-02-20 12:51:33'),
(9, 'VIP 2', 4, 500, 'Available', '2026-03-02 02:27:36'),
(10, 'VVIP', 4, 700, 'Available', '2026-03-02 02:28:26'),
(11, 'SSVIP', 4, 1000, 'Available', '2026-03-02 08:35:55'),
(12, 'Wide Room', 4, 750, 'Available', '2026-03-02 08:36:32');

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
(14, 'Yvan', 'yvan@gmail.com', '$2y$10$GdlFrq44FpPttXmRM/tDpOIIkA7Wf/8UdtF0F/zYTiQUBr0wvP9N6', '123456789', '+971', 19, 'user'),
(15, 'Ruelyn', 'ruelyn@gmail.com', '$2y$10$zBEoHq50pAUdRm.7/CjHru8kNv89WkLI2gJ02TnO1GQulgr0JCaje', '90865732', '+65', 20, 'user'),
(16, 'Dap', 'd@gmail.com', '$2y$10$QKx4XmdYJ5R0AckOLJ.gpePXQYGJUFKAz./Pv2Mg1khtHqWMHC/SW', '9875643526', '+63', 20, 'user'),
(17, 'Ruelyn Tolentin', 'tolentin@gmail.com', '$2y$10$IShJw0O0B9L0hQehnO/mNO6QjsfLbEjIxAZdYUkGs5y2uS/2RkqIW', '9615774280', '+63', 20, 'user');

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
  ADD KEY `booking_food_ibfk_2` (`table_num`);

--
-- Indexes for table `food_beverages`
--
ALTER TABLE `food_beverages`
  ADD PRIMARY KEY (`f_id`);

--
-- Indexes for table `food_payments`
--
ALTER TABLE `food_payments`
  ADD PRIMARY KEY (`fp_id`),
  ADD KEY `idx_bf_id` (`bf_id`),
  ADD KEY `idx_b_id` (`b_id`),
  ADD KEY `idx_u_id` (`u_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`p_id`),
  ADD KEY `u_id` (`u_id`),
  ADD KEY `payments_ibfk_2` (`b_id`),
  ADD KEY `f_id` (`f_id`);

--
-- Indexes for table `preorders`
--
ALTER TABLE `preorders`
  ADD PRIMARY KEY (`po_id`),
  ADD KEY `f_id` (`f_id`),
  ADD KEY `idx_booking` (`b_id`),
  ADD KEY `idx_scheduled` (`scheduled_for`),
  ADD KEY `idx_status` (`status`);

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
  MODIFY `b_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `booking_food`
--
ALTER TABLE `booking_food`
  MODIFY `bf_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- AUTO_INCREMENT for table `food_beverages`
--
ALTER TABLE `food_beverages`
  MODIFY `f_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `food_payments`
--
ALTER TABLE `food_payments`
  MODIFY `fp_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `p_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `preorders`
--
ALTER TABLE `preorders`
  MODIFY `po_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `room`
--
ALTER TABLE `room`
  MODIFY `r_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_tbl`
--
ALTER TABLE `user_tbl`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
  ADD CONSTRAINT `booking_food_ibfk_1` FOREIGN KEY (`f_id`) REFERENCES `food_beverages` (`f_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `food_payments`
--
ALTER TABLE `food_payments`
  ADD CONSTRAINT `food_payments_ibfk_1` FOREIGN KEY (`bf_id`) REFERENCES `booking_food` (`bf_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_payments_ibfk_2` FOREIGN KEY (`b_id`) REFERENCES `booking` (`b_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `food_payments_ibfk_3` FOREIGN KEY (`u_id`) REFERENCES `user_tbl` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`u_id`) REFERENCES `user_tbl` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`b_id`) REFERENCES `booking` (`b_id`),
  ADD CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`f_id`) REFERENCES `food_beverages` (`f_id`) ON DELETE SET NULL;

--
-- Constraints for table `preorders`
--
ALTER TABLE `preorders`
  ADD CONSTRAINT `preorders_ibfk_1` FOREIGN KEY (`b_id`) REFERENCES `booking` (`b_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `preorders_ibfk_2` FOREIGN KEY (`f_id`) REFERENCES `food_beverages` (`f_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
