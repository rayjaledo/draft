-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Oct 17, 2026 at 04:56 PM
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
-- Database: `shrcms`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `student_id_no` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `email`, `password`, `created_at`, `student_id_no`) VALUES
(1, 'admin', 'admin@shrcms.com', 'admin123', '2026-10-13 05:56:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `id` int(11) NOT NULL,
  `student_id` varchar(50) NOT NULL,
  `consultation_date` date NOT NULL,
  `reason` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `staff_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `consultations`
--

INSERT INTO `consultations` (`id`, `student_id`, `consultation_date`, `reason`, `remarks`, `staff_name`, `created_at`) VALUES
(1, 'scc-000-202344', '2026-10-01', 'Stomachache', 'okay', 'shani', '2026-10-17 05:29:00'),
(2, '1', '2026-10-17', 'fever', 'give biogesic', 'shani', '2026-10-17 06:42:50'),
(3, 'scc-00-2024-987634', '2026-10-17', 'General Check-up', '', 'shani', '2026-10-17 07:42:46'),
(4, 'scc-00-2024-987634', '2026-10-17', 'General Check-up', '', 'shani', '2026-10-17 07:44:29'),
(5, 'scc-00-2024-987634', '2026-10-17', 'General Check-up', '', 'shani', '2026-10-17 13:23:04');

-- --------------------------------------------------------

--
-- Table structure for table `equipment_inventory`
--

CREATE TABLE `equipment_inventory` (
  `id` int(11) NOT NULL,
  `equipment_name` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `equipment_condition` enum('Good','Needs Maintenance','Under Repair','Damaged') NOT NULL DEFAULT 'Good',
  `date_acquired` date DEFAULT NULL,
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `equipment_inventory`
--

INSERT INTO `equipment_inventory` (`id`, `equipment_name`, `category`, `quantity`, `equipment_condition`, `date_acquired`, `last_maintenance`, `next_maintenance`, `location`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'digital thermometer', 'diagnostic equipment', 5, 'Good', '2026-10-01', '2026-10-13', '2027-06-17', 'school clinic', '', '2026-10-17 06:55:27', '2026-10-17 06:55:27');

-- --------------------------------------------------------

--
-- Table structure for table `health_documentation`
--

CREATE TABLE `health_documentation` (
  `id` int(11) NOT NULL,
  `student_id_no` varchar(50) NOT NULL,
  `examination_date` date NOT NULL,
  `height_cm` decimal(5,2) DEFAULT NULL,
  `weight_kg` decimal(5,2) DEFAULT NULL,
  `temperature` decimal(4,1) DEFAULT NULL,
  `blood_pressure` varchar(20) DEFAULT NULL,
  `findings` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_documentation`
--

INSERT INTO `health_documentation` (`id`, `student_id_no`, `examination_date`, `height_cm`, `weight_kg`, `temperature`, `blood_pressure`, `findings`, `remarks`, `recorded_by`, `created_at`) VALUES
(1, 'SCC-000-20221567', '2026-10-17', 165.00, 55.50, 36.8, '120/80', 'the student physical examination is normal', '', 'shani', '2026-10-17 06:36:35');

-- --------------------------------------------------------

--
-- Table structure for table `medicine_inventory`
--

CREATE TABLE `medicine_inventory` (
  `id` int(11) NOT NULL,
  `medicine_name` varchar(150) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(50) NOT NULL DEFAULT 'pcs',
  `reorder_level` int(11) NOT NULL DEFAULT 10,
  `expiration_date` date DEFAULT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `storage_location` varchar(150) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medicine_inventory`
--

INSERT INTO `medicine_inventory` (`id`, `medicine_name`, `category`, `quantity`, `unit`, `reorder_level`, `expiration_date`, `supplier`, `storage_location`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 'cetirizine', 'allergy aid', 100, 'pcs', 10, '2028-11-15', 'mercury drug', 'box', '', '2026-10-17 05:50:56', '2026-10-17 06:13:19'),
(2, 'biogesic', 'fever reducer', 5, 'box', 8, '2027-05-17', 'mercury drug', 'box2', '', '2026-10-17 05:54:53', '2026-10-17 06:13:03');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `role` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `fullname`, `role`, `email`, `created_at`) VALUES
(1, 'olivia', 'Staff', 'jamesoliverbastismo35@gmail.com', '2026-10-13 05:19:31'),
(2, 'James oliver Bastismo', 'Staff', 'jamesoliverbastismo188@gmail.com', '2026-10-13 05:47:41'),
(3, 'Jingkie alipuyo', 'Staff', 'jingkiebahobilat@gmail.com', '2026-10-13 06:04:13');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_id_no` varchar(50) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `course_year` varchar(50) DEFAULT NULL,
  `contact` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id_no`, `fullname`, `age`, `gender`, `course_year`, `contact`, `created_at`, `user_id`, `email`) VALUES
(1, 'SCC-22-00022156', 'James Oliver Bastismo', 20, 'Female', 'BSIT 3RD YEAR', '09354629968', '2026-10-17 06:24:16', NULL, 'james@gmail.com'),
(2, 'SCC-000-20240987', 'May Shane P. Paran', 15, 'Female', 'BEED 2A', '09658246271', '2026-10-17 13:17:14', NULL, 'mayshane@gmail.com');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `system_name` varchar(150) NOT NULL DEFAULT 'SHRCMS',
  `school_name` varchar(150) NOT NULL DEFAULT 'My School',
  `primary_color` char(7) NOT NULL DEFAULT '#1454bd',
  `sidebar_color` char(7) NOT NULL DEFAULT '#08285e',
  `records_per_page` int(11) NOT NULL DEFAULT 10,
  `date_format` varchar(20) NOT NULL DEFAULT 'M d, Y',
  `confirm_delete` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `system_name`, `school_name`, `primary_color`, `sidebar_color`, `records_per_page`, `date_format`, `confirm_delete`, `updated_at`) VALUES
(1, 'SHRCMS', 'My School', '#1454bd', '#08285e', 10, 'M d, Y', 1, '2026-10-17 08:39:24');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` varchar(50) DEFAULT 'User',
  `password` varchar(255) NOT NULL,
  `status` varchar(20) DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `student_id_no` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `role`, `password`, `status`, `created_at`, `student_id_no`) VALUES
(1, 'olivia', 'jamesoliverbastismo35@gmail.com', 'Staff', '$2y$10$YZvXPm3iwZ7MyIOx13rNq.hR5js9mWMKay0iyqbLNrkRP1IQ1wn4e', 'Active', '2026-10-13 05:19:31', NULL),
(8, 'James oliver Bastismo', 'jamesoliverbastismo188@gmail.com', 'Staff', '$2y$10$nAhhP82RNiw4uR9khq7/neJbr6MnaQ0COqzUwtFViKNtHVMwhmZ5.', 'Active', '2026-10-13 05:47:41', NULL),
(9, 'Jingkie alipuyo', 'jingkiebahobilat@gmail.com', 'Staff', '$2y$10$..p/v5PncB9LznolLvAPcOxefvQh9kN4EnqxDAz4MoWm3bfPLRWsi', 'Active', '2026-10-13 06:04:13', NULL),
(10, 'shani', 'kishanicoleparan@gmail.com', 'Staff', '$2y$10$KTDVvtIU7G4LbzrHf6p85e.kXZtK0ZLzVOGmchA53oL3Pix6hi4uG', 'Active', '2026-10-17 03:41:13', NULL),
(11, 'xian paran', 'xian@gmail.com', 'Student', '$2y$10$g1JoInHjHDlxj.4D.pOHMe41EVJtklcqWnOl73UqvW5zKj7uAVGKy', 'Active', '2026-10-17 08:54:42', NULL),
(12, 'james oliver bastismo', 'james@gmail.com', 'Student', '$2y$10$TzItbjNOeYlQAOypuJib.O7XNXjYPQhqh9iZcv/6TjBTlLK21a2ju', 'Active', '2026-10-17 09:05:51', NULL),
(13, 'SCC-000-20221567', 'oliver@gmail.com', 'Student', '$2y$10$uyf3NKYwJvmvigW4XcGVHemscM8B2Z1fxe./8WWNbEw4WHvu7PhiK', 'Active', '2026-10-17 09:20:13', NULL),
(14, 'mayshane paran', 'mayshane@gmail.com', 'Student', '$2y$10$07pqtJi6Yg9agwyOqoQ7n.M46pwAuJiSpYpGD98.yOmJL9/6pWKzG', 'Active', '2026-10-17 13:23:39', NULL),
(15, 'james oliver bastismo', 'bastismo@gmail.com', 'student', '$2y$10$.uw455E7E19mCO2UaJiaheoUZ3XLTOnMdEfY1Jna07w3DuSWlABWq', 'Active', '2026-10-17 14:36:03', 'SCC-22-00022156');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `equipment_inventory`
--
ALTER TABLE `equipment_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_equipment_name` (`equipment_name`),
  ADD KEY `idx_equipment_condition` (`equipment_condition`),
  ADD KEY `idx_next_maintenance` (`next_maintenance`);

--
-- Indexes for table `health_documentation`
--
ALTER TABLE `health_documentation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_health_student` (`student_id_no`),
  ADD KEY `idx_health_date` (`examination_date`);

--
-- Indexes for table `medicine_inventory`
--
ALTER TABLE `medicine_inventory`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_medicine_name` (`medicine_name`),
  ADD KEY `idx_medicine_expiration` (`expiration_date`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id_no` (`student_id_no`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `equipment_inventory`
--
ALTER TABLE `equipment_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `health_documentation`
--
ALTER TABLE `health_documentation`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `medicine_inventory`
--
ALTER TABLE `medicine_inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
