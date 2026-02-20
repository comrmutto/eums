-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 19, 2026 at 10:25 AM
-- Server version: 8.4.3
-- PHP Version: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `eums_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `action` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'e.g. login, logout, create, update, delete',
  `module` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'e.g. air, boiler, energy, users',
  `target_id` int DEFAULT NULL COMMENT 'ID of the affected record (optional)',
  `detail` text COLLATE utf8mb4_unicode_ci COMMENT 'Free-text detail or JSON payload',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'IPv4 or IPv6',
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Audit trail for all user actions';

-- --------------------------------------------------------

--
-- Table structure for table `air_daily_records`
--

CREATE TABLE `air_daily_records` (
  `id` int NOT NULL,
  `doc_id` int DEFAULT NULL,
  `machine_id` int DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  `inspection_item_id` int DEFAULT NULL,
  `actual_value` decimal(10,2) DEFAULT NULL,
  `remarks` text,
  `recorded_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `air_inspection_standards`
--

CREATE TABLE `air_inspection_standards` (
  `id` int NOT NULL,
  `machine_id` int DEFAULT NULL,
  `inspection_item` varchar(255) DEFAULT NULL,
  `standard_value` decimal(10,2) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `min_value` decimal(10,2) DEFAULT NULL,
  `max_value` decimal(10,2) DEFAULT NULL,
  `sort_order` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `boiler_daily_records`
--

CREATE TABLE `boiler_daily_records` (
  `id` int NOT NULL,
  `doc_id` int DEFAULT NULL,
  `machine_id` int DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  `steam_pressure` decimal(10,2) DEFAULT NULL,
  `steam_temperature` decimal(10,2) DEFAULT NULL,
  `feed_water_level` decimal(10,2) DEFAULT NULL,
  `fuel_consumption` decimal(10,2) DEFAULT NULL,
  `operating_hours` decimal(10,2) DEFAULT NULL,
  `remarks` text,
  `recorded_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `id` int NOT NULL,
  `doc_no` varchar(50) DEFAULT NULL,
  `doc_name` varchar(255) DEFAULT NULL,
  `module_type` enum('air','energy_water','lpg','boiler','summary') DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `rev_no` varchar(20) DEFAULT NULL,
  `details` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `electricity_summary`
--

CREATE TABLE `electricity_summary` (
  `id` int NOT NULL,
  `doc_id` int DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  `ee_unit` decimal(10,2) DEFAULT NULL,
  `cost_per_unit` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) GENERATED ALWAYS AS ((`ee_unit` * `cost_per_unit`)) STORED,
  `pe` decimal(10,2) DEFAULT NULL,
  `remarks` text,
  `recorded_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lpg_daily_records`
--

CREATE TABLE `lpg_daily_records` (
  `id` int NOT NULL,
  `doc_id` int DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  `item_id` int DEFAULT NULL,
  `number_value` decimal(10,2) DEFAULT NULL,
  `enum_value` enum('OK','NG') DEFAULT NULL,
  `remarks` text,
  `recorded_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lpg_inspection_items`
--

CREATE TABLE `lpg_inspection_items` (
  `id` int NOT NULL,
  `item_no` int DEFAULT NULL,
  `item_name` varchar(255) DEFAULT NULL,
  `item_type` enum('number','enum') DEFAULT NULL,
  `standard_value` varchar(100) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `enum_options` json DEFAULT NULL,
  `sort_order` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mc_air`
--

CREATE TABLE `mc_air` (
  `id` int NOT NULL,
  `machine_code` varchar(50) DEFAULT NULL,
  `machine_name` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `capacity` decimal(10,2) DEFAULT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mc_boiler`
--

CREATE TABLE `mc_boiler` (
  `id` int NOT NULL,
  `machine_code` varchar(50) DEFAULT NULL,
  `machine_name` varchar(100) DEFAULT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `capacity` decimal(10,2) DEFAULT NULL,
  `pressure_rating` decimal(10,2) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mc_mdb_water`
--

CREATE TABLE `mc_mdb_water` (
  `id` int NOT NULL,
  `meter_code` varchar(50) DEFAULT NULL,
  `meter_name` varchar(100) DEFAULT NULL,
  `meter_type` enum('electricity','water') DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `initial_reading` decimal(10,2) DEFAULT NULL,
  `status` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `meter_daily_readings`
--

CREATE TABLE `meter_daily_readings` (
  `id` int NOT NULL,
  `doc_id` int DEFAULT NULL,
  `meter_id` int DEFAULT NULL,
  `record_date` date DEFAULT NULL,
  `morning_reading` decimal(10,2) DEFAULT NULL,
  `evening_reading` decimal(10,2) DEFAULT NULL,
  `usage_amount` decimal(10,2) GENERATED ALWAYS AS ((`evening_reading` - `morning_reading`)) STORED,
  `remarks` text,
  `recorded_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `monthly_summaries`
--

CREATE TABLE `monthly_summaries` (
  `id` int NOT NULL,
  `module_type` enum('air','energy_water','lpg','boiler','summary') DEFAULT NULL,
  `summary_month` date DEFAULT NULL,
  `total_usage` decimal(10,2) DEFAULT NULL,
  `average_usage` decimal(10,2) DEFAULT NULL,
  `peak_usage` decimal(10,2) DEFAULT NULL,
  `data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int NOT NULL,
  `permission_key` varchar(100) NOT NULL,
  `permission_name` varchar(255) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `permission_key`, `permission_name`, `module`, `created_at`) VALUES
(1, 'view_dashboard', 'ดูแดชบอร์ด', 'core', '2026-02-19 02:07:30'),
(2, 'view_air', 'ดูข้อมูล Air Compressor', 'air', '2026-02-19 02:07:30'),
(3, 'add_air', 'เพิ่มข้อมูล Air Compressor', 'air', '2026-02-19 02:07:30'),
(4, 'edit_air', 'แก้ไขข้อมูล Air Compressor', 'air', '2026-02-19 02:07:30'),
(5, 'delete_air', 'ลบข้อมูล Air Compressor', 'air', '2026-02-19 02:07:30'),
(6, 'view_energy', 'ดูข้อมูล Energy & Water', 'energy', '2026-02-19 02:07:30'),
(7, 'add_energy', 'เพิ่มข้อมูล Energy & Water', 'energy', '2026-02-19 02:07:30'),
(8, 'edit_energy', 'แก้ไขข้อมูล Energy & Water', 'energy', '2026-02-19 02:07:30'),
(9, 'delete_energy', 'ลบข้อมูล Energy & Water', 'energy', '2026-02-19 02:07:30'),
(10, 'view_lpg', 'ดูข้อมูล LPG', 'lpg', '2026-02-19 02:07:30'),
(11, 'add_lpg', 'เพิ่มข้อมูล LPG', 'lpg', '2026-02-19 02:07:30'),
(12, 'edit_lpg', 'แก้ไขข้อมูล LPG', 'lpg', '2026-02-19 02:07:30'),
(13, 'delete_lpg', 'ลบข้อมูล LPG', 'lpg', '2026-02-19 02:07:30'),
(14, 'view_boiler', 'ดูข้อมูล Boiler', 'boiler', '2026-02-19 02:07:30'),
(15, 'add_boiler', 'เพิ่มข้อมูล Boiler', 'boiler', '2026-02-19 02:07:30'),
(16, 'edit_boiler', 'แก้ไขข้อมูล Boiler', 'boiler', '2026-02-19 02:07:30'),
(17, 'delete_boiler', 'ลบข้อมูล Boiler', 'boiler', '2026-02-19 02:07:30'),
(18, 'view_summary', 'ดูข้อมูล Summary Electricity', 'summary', '2026-02-19 02:07:30'),
(19, 'add_summary', 'เพิ่มข้อมูล Summary Electricity', 'summary', '2026-02-19 02:07:30'),
(20, 'edit_summary', 'แก้ไขข้อมูล Summary Electricity', 'summary', '2026-02-19 02:07:30'),
(21, 'delete_summary', 'ลบข้อมูล Summary Electricity', 'summary', '2026-02-19 02:07:30'),
(22, 'manage_users', 'จัดการผู้ใช้', 'admin', '2026-02-19 02:07:30'),
(23, 'manage_settings', 'จัดการตั้งค่าระบบ', 'admin', '2026-02-19 02:07:30');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`, `created_at`) VALUES
(1, 'admin', 'ผู้ดูแลระบบ มีสิทธิ์ทั้งหมด', '2026-02-19 02:07:30'),
(2, 'operator', 'ผู้ปฏิบัติงาน สามารถบันทึกและแก้ไขข้อมูล', '2026-02-19 02:07:30'),
(3, 'viewer', 'ผู้ดู สามารถดูได้อย่างเดียว', '2026-02-19 02:07:30');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `role_id` int NOT NULL,
  `permission_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`role_id`, `permission_id`) VALUES
(2, 1),
(3, 1),
(2, 2),
(3, 2),
(2, 3),
(2, 4),
(2, 6),
(3, 6),
(2, 7),
(2, 8),
(2, 10),
(3, 10),
(2, 11),
(2, 12),
(2, 14),
(3, 14),
(2, 15),
(2, 16),
(2, 18),
(3, 18),
(2, 19),
(2, 20);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','operator','viewer') DEFAULT 'viewer',
  `status` tinyint(1) DEFAULT '1',
  `login_attempts` int DEFAULT '0',
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `fullname`, `email`, `role`, `status`, `login_attempts`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$J9LENAWbcJI1rCbdjRLvTuJZ7Y/VYtGZcIxL7fY8wfW5ZdmrFF54i', 'ผู้ดูแลระบบ', 'admin@eums.local', 'admin', 1, 0, '2026-02-19 10:00:05', '2026-02-19 02:07:30', '2026-02-19 03:00:05'),
(2, 'engineer', '$2a$12$z6jQkUI8h6ByW2/e8km2LeAn69Bz.LN6QmbckXu1KepoSxZpQdwVS', 'Pornpipat', 'p_pornpipat@marugo-rubber.co.th', 'admin', 1, 0, NULL, '2026-02-19 10:24:58', '2026-02-19 10:24:58');

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int NOT NULL,
  `permission_id` int NOT NULL,
  `granted` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_tokens`
--

CREATE TABLE `user_tokens` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `type` enum('remember','reset') DEFAULT 'remember',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `air_daily_records`
--
ALTER TABLE `air_daily_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `machine_id` (`machine_id`),
  ADD KEY `inspection_item_id` (`inspection_item_id`),
  ADD KEY `idx_module_date` (`record_date`);

--
-- Indexes for table `air_inspection_standards`
--
ALTER TABLE `air_inspection_standards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `machine_id` (`machine_id`);

--
-- Indexes for table `boiler_daily_records`
--
ALTER TABLE `boiler_daily_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `machine_id` (`machine_id`),
  ADD KEY `idx_boiler_date` (`record_date`);

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `doc_no` (`doc_no`);

--
-- Indexes for table `electricity_summary`
--
ALTER TABLE `electricity_summary`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `idx_summary_date` (`record_date`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username_time` (`username`,`attempt_time`);

--
-- Indexes for table `lpg_daily_records`
--
ALTER TABLE `lpg_daily_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `idx_lpg_date` (`record_date`);

--
-- Indexes for table `lpg_inspection_items`
--
ALTER TABLE `lpg_inspection_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mc_air`
--
ALTER TABLE `mc_air`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `machine_code` (`machine_code`);

--
-- Indexes for table `mc_boiler`
--
ALTER TABLE `mc_boiler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `machine_code` (`machine_code`);

--
-- Indexes for table `mc_mdb_water`
--
ALTER TABLE `mc_mdb_water`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `meter_code` (`meter_code`);

--
-- Indexes for table `meter_daily_readings`
--
ALTER TABLE `meter_daily_readings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `meter_id` (`meter_id`),
  ADD KEY `idx_meter_date` (`record_date`);

--
-- Indexes for table `monthly_summaries`
--
ALTER TABLE `monthly_summaries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permission_key` (`permission_key`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_type` (`user_id`,`type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `air_daily_records`
--
ALTER TABLE `air_daily_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `air_inspection_standards`
--
ALTER TABLE `air_inspection_standards`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `boiler_daily_records`
--
ALTER TABLE `boiler_daily_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `electricity_summary`
--
ALTER TABLE `electricity_summary`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lpg_daily_records`
--
ALTER TABLE `lpg_daily_records`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lpg_inspection_items`
--
ALTER TABLE `lpg_inspection_items`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mc_air`
--
ALTER TABLE `mc_air`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mc_boiler`
--
ALTER TABLE `mc_boiler`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mc_mdb_water`
--
ALTER TABLE `mc_mdb_water`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `meter_daily_readings`
--
ALTER TABLE `meter_daily_readings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `monthly_summaries`
--
ALTER TABLE `monthly_summaries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_tokens`
--
ALTER TABLE `user_tokens`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_activity_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `air_daily_records`
--
ALTER TABLE `air_daily_records`
  ADD CONSTRAINT `air_daily_records_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`id`),
  ADD CONSTRAINT `air_daily_records_ibfk_2` FOREIGN KEY (`machine_id`) REFERENCES `mc_air` (`id`),
  ADD CONSTRAINT `air_daily_records_ibfk_3` FOREIGN KEY (`inspection_item_id`) REFERENCES `air_inspection_standards` (`id`);

--
-- Constraints for table `air_inspection_standards`
--
ALTER TABLE `air_inspection_standards`
  ADD CONSTRAINT `air_inspection_standards_ibfk_1` FOREIGN KEY (`machine_id`) REFERENCES `mc_air` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `boiler_daily_records`
--
ALTER TABLE `boiler_daily_records`
  ADD CONSTRAINT `boiler_daily_records_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`id`),
  ADD CONSTRAINT `boiler_daily_records_ibfk_2` FOREIGN KEY (`machine_id`) REFERENCES `mc_boiler` (`id`);

--
-- Constraints for table `electricity_summary`
--
ALTER TABLE `electricity_summary`
  ADD CONSTRAINT `electricity_summary_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`id`);

--
-- Constraints for table `lpg_daily_records`
--
ALTER TABLE `lpg_daily_records`
  ADD CONSTRAINT `lpg_daily_records_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`id`),
  ADD CONSTRAINT `lpg_daily_records_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `lpg_inspection_items` (`id`);

--
-- Constraints for table `meter_daily_readings`
--
ALTER TABLE `meter_daily_readings`
  ADD CONSTRAINT `meter_daily_readings_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`id`),
  ADD CONSTRAINT `meter_daily_readings_ibfk_2` FOREIGN KEY (`meter_id`) REFERENCES `mc_mdb_water` (`id`);

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_tokens`
--
ALTER TABLE `user_tokens`
  ADD CONSTRAINT `user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
