-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 04, 2026 at 09:12 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `gym_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `scan_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` int(11) NOT NULL,
  `qr_id` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `membership_type` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('Active','Expired') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `qr_id`, `name`, `contact`, `address`, `membership_type`, `start_date`, `expiry_date`, `status`, `created_at`) VALUES
(1, 'GYM-178673958341', 'janren jerez', '09634401510', 'brgy baldoza lapaz iloilo city', NULL, NULL, '2026-08-18', 'Active', '2026-08-14 20:33:03'),
(2, 'GYM-178546586866', 'rash', '09949550536', 'blk 5 lot 15 cuidad de iloilo', NULL, NULL, '2027-07-31', 'Active', '2026-07-31 02:44:28'),
(7, 'GYM-178711390019', 'ERIC JASON S. DIONES', '09167223663', 'LOT 3 BLOCK 19 IMPERIAL HOMES MANDURRIAO ILOILO CITY', NULL, NULL, '2026-09-19', 'Active', '2026-08-19 04:31:40'),
(8, 'GYM-178711456693', 'KATHLEEN BALDOQUE', '09451234567', 'JARO ILOILO CITY', NULL, NULL, '2026-08-19', 'Active', '2026-08-19 04:42:46'),
(9, 'GYM-178721936989', 'junjerez', '09123456789', 'baldoza lapaz iloilo city', NULL, NULL, '2026-09-20', 'Active', '2026-08-20 09:49:29');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `image_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `stock`, `image_url`) VALUES
(9, 'whey protein', 1500.00, 32, '../assets/1787527286_6a8b8076e123c.webp');

-- --------------------------------------------------------

--
-- Table structure for table `sales`
--

CREATE TABLE `sales` (
  `id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Paid',
  `payment_method` varchar(50) DEFAULT 'Cash',
  `sale_date` datetime NOT NULL,
  `customer_name` varchar(255) DEFAULT 'Walk-in Customer'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `sales`
--

INSERT INTO `sales` (`id`, `amount`, `status`, `payment_method`, `sale_date`, `customer_name`) VALUES
(14, 4500.00, 'Paid', 'Cash', '2026-08-24 11:21:12', 'John Calawigan Jr.'),
(15, 1500.00, 'Paid', 'GCash', '2026-08-24 11:21:39', 'John Calawigan Sr.'),
(16, 0.00, 'Paid', 'Cash', '2026-08-24 11:42:54', 'Walk-in Guest'),
(17, 1500.00, 'Paid', 'Cash', '2026-08-24 11:43:13', 'rash'),
(18, 1500.00, 'Paid', 'Cash', '2026-08-24 11:43:15', 'rash'),
(19, 1500.00, 'Paid', 'Cash', '2026-08-24 11:43:17', 'rash'),
(20, 100.00, 'Paid', 'Cash', '2026-08-24 11:50:39', 'Walk-in Guest (Day Pass)');

-- --------------------------------------------------------

--
-- Table structure for table `sale_items`
--

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `sale_items`
--

INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `qty`, `price`) VALUES
(14, 14, 9, 3, 1500.00),
(15, 15, 9, 1, 1500.00),
(16, 17, 9, 1, 1500.00),
(17, 18, 9, 1, 1500.00),
(18, 19, 9, 1, 1500.00),
(19, 20, 0, 1, 100.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `recovery_question` varchar(255) NOT NULL,
  `recovery_answer` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `recovery_question`, `recovery_answer`) VALUES
(1, 'janren', '$2y$10$y4oMaIqgVZMcwy.CMMTzLukJyOnhPINO0e7efnaFMuuIWjtiRx3um', 'admin', 'What was the name of your first pet?', '$2y$10$yNh.MjFxSlaDMC6KxzCDd.NvRBjYpv0DNPy5C3StxgVcvshiqFzdy'),
(13, 'admin', '$2y$10$NEcv4tyzF.YOcsum4c3waegX.4fLbAMj/lEscAS2bkyKAn9domvFe', 'admin', 'In what city were you born?', '$2y$10$UiTkvbGW04.u7H9I9tvIduOPNkhTUBaPaLBblJIcpc7BtRu277f7e');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `qr_id` (`qr_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sales`
--
ALTER TABLE `sales`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `sales`
--
ALTER TABLE `sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `sale_items`
--
ALTER TABLE `sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`);

--
-- Constraints for table `sale_items`
--
ALTER TABLE `sale_items`
  ADD CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
