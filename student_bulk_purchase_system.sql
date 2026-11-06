-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2026 at 04:16 AM
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
-- Database: `student_bulk_purchase_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `role` enum('super_admin','admin') NOT NULL DEFAULT 'admin',
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `user_id`, `first_name`, `last_name`, `role`, `phone_number`, `created_at`) VALUES
(1, 1, 'System', 'Administrator', 'super_admin', '+2348000000000', '2026-09-19 00:09:52');

-- --------------------------------------------------------

--
-- Table structure for table `bulk_purchases`
--

CREATE TABLE `bulk_purchases` (
  `bulk_purchase_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL COMMENT 'Administrator who created this group purchase',
  `target_quantity` int(11) NOT NULL COMMENT 'Total units required before the group order can proceed',
  `current_quantity` int(11) NOT NULL DEFAULT 0 COMMENT 'Cached total of non-cancelled participant quantities',
  `bulk_price` decimal(10,2) NOT NULL COMMENT 'Agreed price per unit for this group purchase',
  `start_date` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Date and time when students may join',
  `closing_date` datetime NOT NULL COMMENT 'Deadline for joining this group purchase',
  `status` enum('open','target_reached','closed','payment','confirmed','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'open' COMMENT 'Open accepts participants; target_reached is full and no longer accepts participants',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `bulk_purchases`
--

INSERT INTO `bulk_purchases` (`bulk_purchase_id`, `product_id`, `created_by`, `target_quantity`, `current_quantity`, `bulk_price`, `start_date`, `closing_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 50, 47, 6500.00, '2026-09-19 01:09:53', '2026-10-03 01:09:53', 'open', '2026-09-19 00:09:53', '2026-09-25 16:51:20'),
(2, 2, 1, 10, 2, 4500.00, '2026-09-25 18:36:00', '2026-10-11 18:37:00', 'open', '2026-09-25 17:37:28', '2026-09-25 17:38:01'),
(3, 3, 1, 2057, 0, 2300.00, '2026-09-28 04:11:00', '2026-10-02 04:12:00', 'open', '2026-09-28 03:12:13', '2026-09-28 03:12:13');

-- --------------------------------------------------------

--
-- Table structure for table `bulk_purchase_participants`
--

CREATE TABLE `bulk_purchase_participants` (
  `participant_id` int(11) NOT NULL,
  `bulk_purchase_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL COMMENT 'Number of units committed by this student',
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Bulk price snapshot at the time of joining',
  `subtotal` decimal(10,2) NOT NULL COMMENT 'quantity multiplied by unit_price',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('joined','paid','cancelled') NOT NULL DEFAULT 'joined',
  `cancelled_at` datetime DEFAULT NULL
) ;

--
-- Dumping data for table `bulk_purchase_participants`
--

INSERT INTO `bulk_purchase_participants` (`participant_id`, `bulk_purchase_id`, `student_id`, `quantity`, `unit_price`, `subtotal`, `joined_at`, `updated_at`, `status`, `cancelled_at`) VALUES
(1, 1, 1, 10, 6500.00, 65000.00, '2026-09-19 00:09:53', '2026-09-19 00:09:53', 'joined', NULL),
(2, 1, 2, 15, 6500.00, 97500.00, '2026-09-19 00:09:53', '2026-09-19 00:09:53', 'joined', NULL),
(3, 1, 3, 20, 6500.00, 130000.00, '2026-09-19 00:09:53', '2026-09-19 00:09:53', 'joined', NULL),
(4, 1, 4, 1, 6500.00, 6500.00, '2026-09-20 20:13:12', '2026-09-22 17:43:19', 'paid', NULL),
(5, 1, 6, 1, 6500.00, 6500.00, '2026-09-25 16:51:20', '2026-09-25 16:56:33', 'paid', NULL),
(6, 2, 6, 2, 4500.00, 9000.00, '2026-09-25 17:38:01', '2026-09-25 17:38:01', 'joined', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `inventory_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_in_stock` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 10 COMMENT 'Minimum stock before reorder',
  `last_restocked` date DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`inventory_id`, `product_id`, `quantity_in_stock`, `reorder_level`, `last_restocked`, `updated_at`) VALUES
(1, 1, 30, 10, '2026-09-24', '2026-09-24 04:14:17'),
(2, 2, 10, 10, '2026-09-25', '2026-09-25 16:57:12'),
(3, 3, 0, 10, NULL, '2026-09-28 03:10:53');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `sender_id` int(11) DEFAULT NULL COMMENT 'Admin ID if sent by an administrator; NULL for system messages',
  `recipient_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('order_update','payment','general','alert') NOT NULL DEFAULT 'general',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `sender_id`, `recipient_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 1, 1, 'Group purchase payment reminder', 'Your wireless mouse group purchase payment is pending.', 'payment', 0, '2026-09-19 00:09:54'),
(2, 1, 2, 'Group purchase payment reminder', 'Your wireless mouse group purchase payment is pending.', 'payment', 0, '2026-09-19 00:09:54'),
(3, 1, 3, 'Group purchase payment reminder', 'Your wireless mouse group purchase payment is pending.', 'payment', 0, '2026-09-19 00:09:54'),
(4, 1, 4, 'Payment Verified', 'Your payment has been verified successfully.', 'payment', 1, '2026-09-22 17:43:19'),
(5, 1, 1, 'order is available', 'join the order and make payment', 'order_update', 0, '2026-09-24 04:18:14'),
(6, 1, 2, 'order is available', 'join the order and make payment', 'order_update', 0, '2026-09-24 04:18:14'),
(7, 1, 3, 'order is available', 'join the order and make payment', 'order_update', 0, '2026-09-24 04:18:14'),
(8, 1, 4, 'order is available', 'join the order and make payment', 'order_update', 1, '2026-09-24 04:18:14'),
(9, 1, 5, 'order is available', 'join the order and make payment', 'order_update', 0, '2026-09-24 04:18:14'),
(10, 1, 6, 'Payment Verified', 'Your payment has been verified successfully.', 'payment', 0, '2026-09-25 16:56:33'),
(11, 1, 1, 'limited order edition', 'we have a limited order edition of just 10 baskets oif beans quickly join to participate', 'order_update', 0, '2026-09-25 16:58:54'),
(12, 1, 2, 'limited order edition', 'we have a limited order edition of just 10 baskets oif beans quickly join to participate', 'order_update', 0, '2026-09-25 16:58:54'),
(13, 1, 3, 'limited order edition', 'we have a limited order edition of just 10 baskets oif beans quickly join to participate', 'order_update', 0, '2026-09-25 16:58:54'),
(14, 1, 4, 'limited order edition', 'we have a limited order edition of just 10 baskets oif beans quickly join to participate', 'order_update', 0, '2026-09-25 16:58:54'),
(15, 1, 5, 'limited order edition', 'we have a limited order edition of just 10 baskets oif beans quickly join to participate', 'order_update', 0, '2026-09-25 16:58:54'),
(16, 1, 6, 'limited order edition', 'we have a limited order edition of just 10 baskets oif beans quickly join to participate', 'order_update', 1, '2026-09-25 16:58:54');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `participant_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('cash','bank_transfer','card','mobile_money') NOT NULL DEFAULT 'cash',
  `payment_status` enum('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_reference` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL COMMENT 'Administrator who verified the payment',
  `verified_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`payment_id`, `participant_id`, `amount_paid`, `payment_method`, `payment_status`, `payment_reference`, `paid_at`, `verified_by`, `verified_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 1, 0.00, 'bank_transfer', 'pending', 'PENDING-BULK-001-01', NULL, NULL, NULL, 'Payment reminder sent to student', '2026-09-19 00:09:54', '2026-09-19 00:09:54'),
(2, 2, 0.00, 'bank_transfer', 'pending', 'PENDING-BULK-001-02', NULL, NULL, NULL, 'Payment reminder sent to student', '2026-09-19 00:09:54', '2026-09-19 00:09:54'),
(3, 3, 0.00, 'bank_transfer', 'pending', 'PENDING-BULK-001-03', NULL, NULL, NULL, 'Payment reminder sent to student', '2026-09-19 00:09:54', '2026-09-19 00:09:54'),
(4, 4, 6500.00, 'cash', 'paid', 'TRX-20260921-001', '2026-09-22 18:43:19', 1, '2026-09-22 18:43:19', NULL, '2026-09-21 19:46:23', '2026-09-22 17:43:19'),
(5, 5, 6500.00, 'bank_transfer', 'paid', 'TEST-001-BULK', '2026-09-25 12:56:33', 1, '2026-09-25 12:56:33', NULL, '2026-09-25 16:52:19', '2026-09-25 16:56:33');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `min_bulk_quantity` int(11) NOT NULL DEFAULT 10 COMMENT 'Minimum quantity for a bulk purchase',
  `image_url` varchar(500) DEFAULT NULL,
  `status` enum('available','unavailable','discontinued') NOT NULL DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`product_id`, `supplier_id`, `name`, `description`, `category`, `unit_price`, `min_bulk_quantity`, `image_url`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, 'Wireless Mouse', 'Ergonomic wireless mouse for students', 'Electronics', 8000.00, 20, 'uploads/products/product_8e425033afed5639.png', 'available', '2026-09-19 00:09:53', '2026-09-28 05:03:56'),
(2, 1, 'beans', 'basket of beans bulk available', 'food items', 4000.01, 1, NULL, 'available', '2026-09-25 16:55:50', '2026-09-25 16:55:50'),
(3, 1, 'rice bag', 'rice bag', 'food items', 1599.08, 2000, 'uploads/products/product_e1a511990c36c7bd.png', 'available', '2026-09-28 03:10:53', '2026-09-28 03:35:16');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `matric_number` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `level` int(11) NOT NULL COMMENT 'Student level (100-600)',
  `phone_number` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `user_id`, `first_name`, `last_name`, `matric_number`, `department`, `level`, `phone_number`, `created_at`) VALUES
(1, 2, 'Amina', 'Yusuf', 'UOS/2024/CS/001', 'Computer Science', 300, '+2348010000001', '2026-09-19 00:09:53'),
(2, 3, 'Chinedu', 'Okafor', 'UOS/2024/CS/002', 'Computer Science', 300, '+2348010000002', '2026-09-19 00:09:53'),
(3, 4, 'Grace', 'Eze', 'UOS/2024/ACC/003', 'Accounting', 200, '+2348010000003', '2026-09-19 00:09:53'),
(4, 5, 'King', 'Sâmstär', 'FOS/22/23/234455', 'LIS', 400, '000000000000', '2026-09-19 02:01:38'),
(5, 6, 'ball', 'desmond', 'shs5445', 'csc', 400, '0000000', '2026-09-21 19:59:35'),
(6, 7, 'cid', 'kageno', 'OFS/22/23/267854', 'TECHINFO', 300, '0000000', '2026-09-25 16:50:58');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`supplier_id`, `name`, `contact_person`, `email`, `phone_number`, `address`, `status`, `created_at`) VALUES
(1, 'Campus Supplies Ltd', 'John Doe', 'john@campussupplies.example', '+2348020000001', '123 Campus Road, Lagos', 'active', '2026-09-19 00:09:53');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'bcrypt password hash',
  `user_type` enum('student','admin') NOT NULL,
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `user_type`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin@university.edu', '$2y$10$LYpQTx2PpJw7KDoAWoPzUOZdwiqLPB5nU21/DQH4eRzHZ.gTzkndS', 'admin', 'active', '2026-09-19 00:09:51', '2026-09-22 17:40:59'),
(2, 'amina.yusuf@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2026-09-19 00:09:51', '2026-09-19 00:09:51'),
(3, 'chinedu.okafor@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2026-09-19 00:09:51', '2026-09-19 00:09:51'),
(4, 'grace.eze@university.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student', 'active', '2026-09-19 00:09:51', '2026-09-19 00:09:51'),
(5, 'kingsamstar195@gmail.com', '$2y$10$kQ5RueH6ReWVYpLv2mybReywvK/z0g1ZMosIXmez3hm7jaXZMArE.', 'student', 'active', '2026-09-19 02:01:38', '2026-09-19 02:01:38'),
(6, 'socialinfo@gmail.com', '$2y$10$4mqNlJmsgrE/MJVlcRO.fOzOw7UKWsWBQvR/lDUEZA4n59ZrXAsk.', 'student', 'active', '2026-09-21 19:59:35', '2026-09-21 19:59:35'),
(7, 'cidkageno@gmail.com', '$2y$10$1hs/TdCxbikv5bNypjZdr.CdjUHjmTLWs648SaJzordyzm3sidp1W', 'student', 'active', '2026-09-25 16:50:58', '2026-09-25 16:50:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `uk_admins_user_id` (`user_id`),
  ADD KEY `idx_admins_role` (`role`);

--
-- Indexes for table `bulk_purchases`
--
ALTER TABLE `bulk_purchases`
  ADD PRIMARY KEY (`bulk_purchase_id`),
  ADD KEY `idx_bulk_purchases_product_status` (`product_id`,`status`),
  ADD KEY `idx_bulk_purchases_created_by` (`created_by`),
  ADD KEY `idx_bulk_purchases_status_closing` (`status`,`closing_date`);

--
-- Indexes for table `bulk_purchase_participants`
--
ALTER TABLE `bulk_purchase_participants`
  ADD PRIMARY KEY (`participant_id`),
  ADD UNIQUE KEY `uk_participant_bulk_student` (`bulk_purchase_id`,`student_id`),
  ADD KEY `idx_participants_bulk_status` (`bulk_purchase_id`,`status`),
  ADD KEY `idx_participants_student_status` (`student_id`,`status`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `uk_inventory_product` (`product_id`),
  ADD KEY `idx_inventory_quantity` (`quantity_in_stock`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notifications_recipient_read` (`recipient_id`,`is_read`),
  ADD KEY `idx_notifications_type_created` (`type`,`created_at`),
  ADD KEY `fk_notifications_sender` (`sender_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `uk_payments_participant` (`participant_id`),
  ADD UNIQUE KEY `uk_payments_reference` (`payment_reference`),
  ADD KEY `idx_payments_status_paid_at` (`payment_status`,`paid_at`),
  ADD KEY `idx_payments_verified_by` (`verified_by`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`product_id`),
  ADD KEY `idx_products_supplier_status` (`supplier_id`,`status`),
  ADD KEY `idx_products_category` (`category`),
  ADD KEY `idx_products_name` (`name`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `uk_students_user_id` (`user_id`),
  ADD UNIQUE KEY `uk_students_matric_number` (`matric_number`),
  ADD KEY `idx_students_department_level` (`department`,`level`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`supplier_id`),
  ADD KEY `idx_suppliers_name` (`name`),
  ADD KEY `idx_suppliers_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uk_users_email` (`email`),
  ADD KEY `idx_users_user_type_status` (`user_type`,`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bulk_purchases`
--
ALTER TABLE `bulk_purchases`
  MODIFY `bulk_purchase_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bulk_purchase_participants`
--
ALTER TABLE `bulk_purchase_participants`
  MODIFY `participant_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `product_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `supplier_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `bulk_purchases`
--
ALTER TABLE `bulk_purchases`
  ADD CONSTRAINT `fk_bulk_purchases_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`admin_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_bulk_purchases_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON UPDATE CASCADE;

--
-- Constraints for table `bulk_purchase_participants`
--
ALTER TABLE `bulk_purchase_participants`
  ADD CONSTRAINT `fk_participants_bulk_purchase` FOREIGN KEY (`bulk_purchase_id`) REFERENCES `bulk_purchases` (`bulk_purchase_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_participants_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`product_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_recipient` FOREIGN KEY (`recipient_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_notifications_sender` FOREIGN KEY (`sender_id`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_participant` FOREIGN KEY (`participant_id`) REFERENCES `bulk_purchase_participants` (`participant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payments_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`) ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
