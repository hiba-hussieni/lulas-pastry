-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 29, 2026 at 09:35 AM
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
-- Database: `lulas_pastry_challenge`
--

-- --------------------------------------------------------

--
-- Table structure for table `cart`
--

CREATE TABLE `cart` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `dish_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `added_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cart`
--

INSERT INTO `cart` (`id`, `user_id`, `dish_id`, `quantity`, `added_at`) VALUES
(19, 5, 8, 2, '2026-09-29 10:14:11'),
(22, 9, 5, 1, '2026-09-29 11:08:45'),
(23, 5, 5, 1, '2026-09-22 11:45:43'),
(25, 6, 1, 5, '2026-09-22 10:58:35');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `sort_order`, `created_at`) VALUES
(1, 'Bread', 1, '2026-09-27 16:12:45'),
(2, 'Cakes', 2, '2026-09-27 16:12:45'),
(3, 'Pies', 3, '2026-09-27 16:12:45'),
(4, 'Muffin', 4, '2026-09-27 16:12:45'),
(5, 'Pastry', 5, '2026-09-27 16:12:45'),
(6, 'Cookies', 6, '2026-09-27 16:18:59');

-- --------------------------------------------------------

--
-- Table structure for table `delivery`
--

CREATE TABLE `delivery` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `delivery_type` enum('pickup','delivery') DEFAULT 'delivery',
  `delivery_address` text DEFAULT NULL,
  `delivery_fee` decimal(10,2) DEFAULT 0.00,
  `assigned_chef_id` int(11) DEFAULT NULL,
  `baking_start_time` datetime DEFAULT NULL,
  `baking_end_time` datetime DEFAULT NULL,
  `estimated_delivery_time` datetime DEFAULT NULL,
  `actual_delivery_time` datetime DEFAULT NULL,
  `delivery_status` enum('scheduled','baking','on_way','delivered') DEFAULT 'scheduled'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dishes`
--

CREATE TABLE `dishes` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(50) DEFAULT 'pastry',
  `category_id` int(11) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL,
  `available` tinyint(1) DEFAULT 1,
  `stock_quantity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `deleted_at` datetime DEFAULT NULL,
  `ingredients` text DEFAULT NULL,
  `preparation_time` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `chef_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dishes`
--

INSERT INTO `dishes` (`id`, `name`, `description`, `price`, `category`, `category_id`, `sort_order`, `image_url`, `available`, `stock_quantity`, `is_deleted`, `deleted_at`, `ingredients`, `preparation_time`, `created_at`, `chef_id`) VALUES
(1, 'Croissant', 'Freshly baked French croissant', 15.00, 'bread', 1, 2, 'asssets/products/croissant.jpeg', 1, 10, 0, NULL, 'Flour, Butter, Yeast, Sugar', 120, '2026-09-24 17:59:58', 1),
(2, 'Chocolate Cake', 'Rich chocolate cake with ganache', 80.00, 'cake', 2, 1, 'asssets/products/chocolate_cake.jpeg', 1, 5, 0, NULL, 'Chocolate, Flour, Eggs, Sugar, Cream', 180, '2026-09-24 17:59:58', 2),
(3, 'Apple Pie', 'Traditional apple pie with cinnamon', 45.00, 'pie', 3, 3, 'asssets/products/apple_pie.jpeg', 1, 3, 0, NULL, 'Apples, Cinnamon, Flour, Sugar, Butter', 150, '2026-09-24 17:59:58', 1),
(4, 'Blueberry Muffins', 'Fresh muffins with blueberries', 25.00, 'muffin', 4, 4, 'asssets/products/muffin.jpeg', 1, 32, 0, NULL, 'Flour, Blueberries, Eggs, Milk, Sugar', 90, '2026-09-24 17:59:58', 2),
(5, 'Cheese Danish', 'Cream cheese filled pastry', 20.00, 'pastry', 5, 5, 'asssets/products/danish.jpeg', 1, 37, 0, NULL, 'Flour, Cream Cheese, Butter, Sugar', 100, '2026-09-24 17:59:58', 1),
(8, 'Vanilla Cake', 'Vanilla white cake', 60.00, 'cake', 2, 6, 'uploads/dish_1767179571_1152.jpeg', 1, 7, 0, NULL, NULL, NULL, '2026-09-29 10:12:51', 1),
(16, 'Brownies', 'fluffy chocolate brownies', 30.00, 'cakes', 2, 7, 'assets/products/1767554507_9811.jpeg', 1, 50, 0, NULL, 'flour,eggs,milk...', NULL, '2026-09-22 18:21:47', 1),
(18, 'test_cookies', '', 0.01, 'Cookies', 6, 8, NULL, 0, 0, 1, '2026-09-28 00:10:46', '', NULL, '2026-09-27 21:08:23', 1),
(19, 'Test Cookies Updated', 'h3iirrg4', 0.52, 'Muffin', 4, 9, 'assets/products/1790583747_5244.png', 0, 0, 1, '2026-09-28 11:30:30', 'sugar', NULL, '2026-09-27 21:16:12', 1),
(20, 'Chocolate Chip Cookies', 'Warm, chewy chocolate chip cookies loaded with melty chocolate chips — baked fresh daily.', 18.00, 'Cookies', 6, 10, 'assets/products/1790666610_6375.jpg', 1, 0, 0, NULL, 'Flour, Butter, Brown Sugar, Eggs, Chocolate Chips, Vanilla, Baking Soda', NULL, '2026-09-29 07:23:30', 1),
(21, 'Raspberry White Chocolate Croissant', 'Buttery croissant drizzled with white chocolate and raspberry glaze, topped with sugared raspberries.', 22.00, 'Bread', 1, 11, 'assets/products/1790666833_3236.jpg', 1, 10, 0, NULL, 'Flour, Butter, Yeast, White Chocolate, Raspberry Glaze, Fresh Raspberries', NULL, '2026-09-29 07:27:13', 1),
(22, 'Classic Tiramisu Slice', 'Layers of coffee-soaked ladyfingers and mascarpone cream, dusted with cocoa.', 25.00, 'Cakes', 2, 12, 'assets/products/1790666915_3437.jpg', 1, 7, 0, NULL, 'Ladyfingers, Mascarpone, Espresso, Cocoa Powder, Eggs, Sugar', NULL, '2026-09-29 07:28:35', 1),
(23, 'Raspberry Mirror Tart', 'Buttery tart shell filled with vanilla cream and topped with a glossy raspberry glaze and fresh raspberry.', 12.00, 'Pies', 3, 13, 'assets/products/1790666996_3266.jpg', 1, 24, 0, NULL, 'Flour, Butter, Vanilla Cream, Raspberry Glaze, Fresh Raspberry', NULL, '2026-09-29 07:29:56', 1),
(24, 'Chocolate Raspberry Tartlets', 'Mini tart shells with chocolate ganache, pink raspberry cream swirls, and fresh raspberries.', 8.00, 'Pastry', 5, 14, 'assets/products/1790667071_6746.jpg', 1, 36, 0, NULL, 'Flour, Butter, Dark Chocolate, Raspberry Cream, Fresh Raspberries', NULL, '2026-09-29 07:31:11', 1);

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(20) NOT NULL,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','preparing','baking','ready','delivered','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `delivery_date` datetime DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `delivery_address` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `delivery_cost` decimal(10,2) NOT NULL DEFAULT 4.00,
  `stock_deducted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `total_amount`, `status`, `payment_method`, `payment_status`, `order_date`, `delivery_date`, `special_instructions`, `delivery_address`, `customer_phone`, `delivery_cost`, `stock_deducted`) VALUES
(1, 'ORD-1766832223', 5, 180.00, 'cancelled', NULL, 'pending', '2026-09-27 09:43:43', NULL, '', NULL, NULL, 4.00, 0),
(2, 'ORD-1766832259', 5, 40.00, 'confirmed', NULL, 'pending', '2026-09-27 09:44:19', NULL, '', NULL, NULL, 4.00, 0),
(3, 'ORD1766833507', 5, 45.00, 'confirmed', 'cash', 'pending', '2026-09-27 10:05:07', '2026-09-29 00:00:00', 'add extra pistachio', NULL, NULL, 4.00, 0),
(4, 'ORD1766833827', 5, 20.00, 'confirmed', 'cash', 'pending', '2026-09-27 10:10:27', '2026-09-29 00:00:00', 'extra pistachio', NULL, NULL, 4.00, 0),
(5, '', 5, 24.00, 'cancelled', 'cash', 'pending', '2026-09-27 12:52:14', '2026-09-27 15:52:14', 'extra nuts', 'baalback', '76123404', 4.00, 0),
(9, 'ORD-1766845741-177', 5, 164.00, 'cancelled', 'card', 'pending', '2026-09-27 13:29:01', '2026-09-27 16:29:01', 'extra sweet', 'baalback', '76123404', 4.00, 0),
(11, 'ORD-1767182827-531', 6, 64.00, 'delivered', 'cash', 'pending', '2026-09-29 11:07:07', '2026-09-29 14:07:07', 'extra white chocloate', 'baalback', '76123404', 4.00, 0),
(12, 'ORD-BF95703EA9ACA16E', 3, 34.00, 'pending', 'cash', 'pending', '2026-09-27 20:58:48', '2026-09-27 23:58:48', '', 'baalback', '76098765', 4.00, 0),
(13, 'ORD-1AEE8864FB807B93', 3, 4.52, 'pending', 'card', 'pending', '2026-09-28 08:24:52', '2026-09-29 00:00:00', '', 'baalback', '76098765', 4.00, 0),
(14, 'ORD-B16E3AD0375F80E2', 3, 164.00, 'pending', 'card', 'pending', '2026-09-28 13:57:55', '2026-09-28 16:57:55', '', 'beirut', '76123404', 4.00, 0),
(15, 'ORD-413967219BB1CAB1', 3, 164.00, 'pending', 'cash', 'pending', '2026-09-28 14:11:49', '2026-09-28 17:11:49', '', 'amyoun', '03510410', 4.00, 0),
(16, 'ORD-2A97CA065D021860', 3, 84.00, 'pending', 'cash', 'pending', '2026-09-28 14:13:46', '2026-09-28 17:13:46', 'extra chocolate', 'koura-mainstreet', '78675435', 4.00, 0),
(17, 'ORD-F095BC82EB45FCBF', 3, 164.00, 'cancelled', 'cash', 'pending', '2026-09-28 18:37:41', '2026-09-28 21:37:41', '', 'tripoli', '81267834', 4.00, 0),
(18, 'ORD-6FACD5F479BA68CC', 4, 164.00, 'cancelled', 'card', 'pending', '2026-09-28 18:52:02', '2026-09-28 21:52:02', 'extra nuts', 'Dahye', '71450965', 4.00, 0),
(19, 'ORD-B54094918CCB3CBA', 4, 64.00, 'pending', 'cash', 'pending', '2026-09-28 21:28:03', '2026-09-29 00:28:03', '', 'Zahle', '76506412', 4.00, 1),
(20, 'ORD-C7E05EAB880F0119', 10, 89.00, 'pending', 'cash', 'pending', '2026-09-29 07:05:33', '2026-09-29 10:05:33', '', 'zouk-mousbeh', '03675409', 4.00, 1);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `dish_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_time` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `dish_id`, `quantity`, `price_at_time`) VALUES
(1, 1, 2, 2, 80.00),
(2, 1, 5, 1, 20.00),
(3, 2, 5, 2, 20.00),
(4, 3, 3, 1, 45.00),
(5, 4, 5, 1, 20.00),
(8, 9, 2, 2, 80.00),
(10, 11, 8, 1, 60.00),
(11, 12, 16, 1, 30.00),
(12, 13, 19, 1, 0.52),
(13, 14, 2, 2, 80.00),
(14, 15, 2, 2, 80.00),
(15, 16, 2, 1, 80.00),
(16, 17, 2, 2, 80.00),
(17, 18, 2, 2, 80.00),
(18, 19, 5, 3, 20.00),
(19, 20, 4, 1, 25.00),
(20, 20, 8, 1, 60.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('chef','customer') DEFAULT 'customer',
  `full_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `user_type`, `full_name`, `phone`, `address`, `created_at`) VALUES
(1, 'chef_ahmed', 'ahmed@lulas.com', '$2y$10$jnAHNKM9h0b0Xq.kspf4CewLt9AKejK8IfT7toY4PTDlHTY.6eQU6', 'chef', 'Ahmed Hassan', '01012345678', NULL, '2026-09-24 17:59:58'),
(2, 'chef_mariam', 'mariam@lulas.com', '$2y$10$YourHashedPasswordHere', 'chef', 'Mariam Ali', '01087654321', NULL, '2026-09-24 17:59:58'),
(3, 'customer1', 'customer1@example.com', '$2y$10$YourHashedPasswordHere', 'customer', 'John Doe', '01011112222', NULL, '2026-09-24 17:59:58'),
(4, 'customer2', 'customer2@example.com', '$2y$10$YourHashedPasswordHere', 'customer', 'Jane Smith', '01033334444', NULL, '2026-09-24 17:59:58'),
(5, 'testcustomer', 'test@example.com', 'test123', 'customer', 'Test Customer', NULL, NULL, '2026-09-24 18:51:14'),
(6, 'Hiba', 'hibahussieni@gmail.com', '$2y$10$4QY98.2l5ZMbcC6bsd0lyegNO4hCfs7S5AIt0eTPGP8Fl9i3TLH6C', 'customer', 'Hiba', NULL, NULL, '2026-09-29 08:55:59'),
(7, 'Haidar', 'Haidar@gmail.com', '$2y$10$FhmGwkmNejq2WBeKWGlCG.aWA70ArPVyHXMswRh2K/qyPgifU6a46', 'customer', 'Haidar', NULL, NULL, '2026-09-29 09:00:20'),
(9, 'Banine', 'Banine@gmail.com', '$2y$10$k9RjPmP8.xu44QKzPXmFYuzvM53dYI1tN0VoMvmSK.UZLZ5fYlyi2', 'customer', 'Banine', NULL, NULL, '2026-09-29 11:07:59'),
(10, 'jana', 'jana@gmail.com', '$2y$10$nfyOum9/7Yzb6l1PjphX0elvo7R.zqq4f3B4cGcNcLXTINLWbjlZa', 'customer', 'jana', NULL, NULL, '2026-09-29 06:51:17'),
(11, 'lea', 'leahaidar@gmail.com', '$2y$10$21Il7S4QaFsKhn7/sbHv5.nSvo0aRQ4XNnuoT1DMXbPs.C7Lw95l2', 'customer', 'lea', NULL, NULL, '2026-09-29 07:31:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `cart`
--
ALTER TABLE `cart`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cart_item` (`user_id`,`dish_id`),
  ADD KEY `dish_id` (`dish_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `delivery`
--
ALTER TABLE `delivery`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `assigned_chef_id` (`assigned_chef_id`);

--
-- Indexes for table `dishes`
--
ALTER TABLE `dishes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `chef_id` (`chef_id`),
  ADD KEY `dishes_category_fk` (`category_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `dish_id` (`dish_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD UNIQUE KEY `unique_username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `cart`
--
ALTER TABLE `cart`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `delivery`
--
ALTER TABLE `delivery`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dishes`
--
ALTER TABLE `dishes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `cart`
--
ALTER TABLE `cart`
  ADD CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_ibfk_2` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `delivery`
--
ALTER TABLE `delivery`
  ADD CONSTRAINT `delivery_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `delivery_ibfk_2` FOREIGN KEY (`assigned_chef_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dishes`
--
ALTER TABLE `dishes`
  ADD CONSTRAINT `dishes_category_fk` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dishes_ibfk_1` FOREIGN KEY (`chef_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
