-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 12, 2025 at 11:04 PM
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
-- Database: `appointment_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(50) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('Male','Female','Other') DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_type` enum('Patient','Doctor','Admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `terms_accepted` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `profile_image` varchar(255) DEFAULT 'default.png',
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone_number`, `date_of_birth`, `gender`, `address`, `profile_photo`, `city`, `state`, `zip_code`, `password_hash`, `account_type`, `created_at`, `updated_at`, `terms_accepted`, `is_active`, `profile_image`, `first_name`, `last_name`, `phone`) VALUES
(3, 'Jeffrey Deilo', 'jeffrey.deilo@mail.com', '09547894511', '1998-07-01', 'Male', NULL, NULL, NULL, NULL, NULL, '$2y$10$YJEXUgY2y05BxUzZsSSDSudzjjpNcVL4IBJtr8Mf4lgHSd4rVBv0S', 'Doctor', '2025-05-12 03:26:09', '2025-05-12 03:26:09', 0, 1, '68216a50ed661.jpg', '', '', NULL),
(4, 'Harold Dizon', 'dizonieharold@gmail.com', '09547894511', '2004-01-18', 'Male', NULL, NULL, NULL, NULL, NULL, '$2y$10$cxPZt6muyvMXkmaqCsqkre9sIS/x9fuYEbK9wInfEsZG5/NeaBql6', 'Patient', '2025-05-12 11:47:08', '2025-05-12 11:47:08', 0, 1, '6821dfbcd0bdb.jpg', '', '', NULL),
(6, 'Sebastian', 'sebastiantagalog01@gmail.com', '011111111', '1993-07-08', 'Male', NULL, NULL, NULL, NULL, NULL, '$2y$10$ASCFgeF2MSdd.8kMcFmdl.lhbeEgX.4SBHu8Rn08TVCsdCzkyimZm', 'Patient', '2025-05-12 18:31:59', '2025-05-12 18:31:59', 0, 1, 'default.png', '', '', NULL),
(7, 'Admin', 'admin@appointmed.com', '123123123', '2000-06-13', 'Male', 'G.T.C.', 'profile_7_1747080355.png', NULL, NULL, NULL, '$2y$10$9weTJcQtplhHfWF/HouohOgHAp/DUOCLRQ2oBfLPf7VJj9VQ/6Ocm', 'Admin', '2025-05-12 18:44:35', '2025-05-12 20:05:55', 0, 1, 'default.png', 'ADMIN', 'TEST', '0975 989 4451');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
