-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 11, 2025 at 04:17 PM
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
-- Database: `appointment_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `state` varchar(50) NOT NULL,
  `zip_code` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `user_id`, `first_name`, `last_name`, `date_of_birth`, `gender`, `email`, `phone_number`, `address`, `city`, `state`, `zip_code`) VALUES
(1, 24, 'Admin', '', '2003-12-04', 'Female', 'admin@gmail.com', '01234567890', 'Anyadress', 'Anycity', 'Anystate', ''),
(6, 29, 'Harold ', 'Dizon', '2004-12-25', 'Male', 'rc.harold.dizon@cvsu.edu.ph', '09000000000', '', '', '', '');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` datetime NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Upcoming','Completed','Cancelled') NOT NULL DEFAULT 'Upcoming',
  `first_visit` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cancellation_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `doctor_id`, `appointment_date`, `reason`, `status`, `first_visit`, `notes`, `created_at`, `cancellation_reason`) VALUES
(26, 7, 22, '2025-06-02 14:55:50', 'Anyreason', 'Upcoming', 1, 'Anynotes', '2025-05-31 06:56:25', NULL),
(27, 3, 20, '2025-05-31 10:56:44', 'Anyreason', 'Completed', 1, 'Anynotes', '2025-05-31 06:57:24', NULL),
(28, 6, 27, '2025-06-01 14:57:50', 'Anyreason', 'Cancelled', 1, 'Anynotes', '2025-05-31 06:58:15', 'Cancelled');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_activity`
--

CREATE TABLE `appointment_activity` (
  `activity_id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `activity_type` enum('created','rescheduled','cancelled','completed','updated') NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `old_date` datetime DEFAULT NULL,
  `new_date` datetime DEFAULT NULL,
  `changed_by` enum('patient','doctor','admin','system') NOT NULL,
  `change_reason` text DEFAULT NULL,
  `activity_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_details`
--

CREATE TABLE `doctor_details` (
  `doctor_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `sub_specialties` text DEFAULT NULL,
  `years_of_experience` int(11) DEFAULT NULL,
  `medical_license_number` varchar(50) DEFAULT NULL,
  `npi_number` varchar(50) DEFAULT NULL,
  `education_and_training` text DEFAULT NULL,
  `availability` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`availability`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_details`
--

INSERT INTO `doctor_details` (`doctor_id`, `user_id`, `first_name`, `last_name`, `specialization`, `sub_specialties`, `years_of_experience`, `medical_license_number`, `npi_number`, `education_and_training`, `availability`) VALUES
(18, 10, 'Elijahhhh', 'Santiago', 'Neurology', '', 4, 'MED - 04', '1234-4567-89', 'ANY', NULL),
(19, 12, 'Marco', 'de Guzman', 'Neurology', 'Interventional Neurology', 5, 'MED - 06', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"09:00\",\"end_time\":\"18:00\"},\"working_days\":[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\",\"Saturday\",\"Sunday\"],\"appointment_duration\":\"45\",\"break_time\":{\"enabled\":false,\"start_time\":\"11:00\",\"duration\":\"60\"}}'),
(20, 6, 'Amelia', 'Reyes', 'Cardiology', 'Interventional Cardiology,Electrophysiology', 5, 'MED - 01', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"09:00\",\"end_time\":\"18:00\"},\"working_days\":[\"Monday\",\"Wednesday\",\"Friday\"],\"appointment_duration\":\"60\",\"break_time\":{\"enabled\":true,\"start_time\":\"13:00\",\"duration\":\"60\"}}'),
(21, 7, 'Nathan', 'Cruz', 'Cardiology', 'Interventional Cardiology,Electrophysiology', 6, 'MED - 02', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"07:00\",\"end_time\":\"16:00\"},\"working_days\":[\"Tuesday\",\"Thursday\",\"Saturday\"],\"appointment_duration\":\"60\",\"break_time\":{\"enabled\":false,\"start_time\":\"12:00\",\"duration\":\"60\"}}'),
(22, 8, 'Bianca', 'Morales', 'Cardiology', 'Interventional Cardiology,Electrophysiology', 7, 'MED - 03', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"09:00\",\"end_time\":\"18:00\"},\"working_days\":[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\",\"Saturday\",\"Sunday\"],\"appointment_duration\":\"60\",\"break_time\":{\"enabled\":false,\"start_time\":\"13:00\",\"duration\":\"60\"}}'),
(24, 11, 'Hana', 'Lim', 'Neurology', 'Interventional Neurology', 9, 'MED - 05', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"07:00\",\"end_time\":\"16:00\"},\"working_days\":[\"Tuesday\",\"Thursday\",\"Saturday\"],\"appointment_duration\":\"60\",\"break_time\":{\"enabled\":false,\"start_time\":\"13:00\",\"duration\":\"60\"}}'),
(25, 13, 'Sofia', 'Dela Cruz', 'Pediatrics', 'Interventional Pediatrics', 3, 'MED - 07', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"08:00\",\"end_time\":\"17:00\"},\"working_days\":[\"Monday\",\"Wednesday\",\"Friday\"],\"appointment_duration\":\"45\",\"break_time\":{\"enabled\":true,\"start_time\":\"13:00\",\"duration\":\"60\"}}'),
(26, 14, 'Rafael', 'Mendoza', 'Pediatrics', 'Interventional Pediatrics', 7, 'MED - 08', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"07:00\",\"end_time\":\"16:00\"},\"working_days\":[\"Tuesday\",\"Thursday\",\"Saturday\"],\"appointment_duration\":\"45\",\"break_time\":{\"enabled\":false,\"start_time\":\"13:00\",\"duration\":\"45\"}}'),
(27, 15, 'Clarisse', 'Uy', 'Pediatrics', 'Interventional Pediatrics', 2, 'MED - 09', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"09:00\",\"end_time\":\"18:00\"},\"working_days\":[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\",\"Saturday\",\"Sunday\"],\"appointment_duration\":\"60\",\"break_time\":{\"enabled\":false,\"start_time\":\"11:00\",\"duration\":\"60\"}}'),
(28, 16, 'Lorenzo', 'Tan', 'Dermatology', 'Interventional Dermatology', 4, 'MED - 10', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"08:00\",\"end_time\":\"17:00\"},\"working_days\":[\"Monday\",\"Wednesday\",\"Friday\"],\"appointment_duration\":\"30\",\"break_time\":{\"enabled\":false,\"start_time\":\"12:00\",\"duration\":\"30\"}}'),
(29, 17, 'Isabel', 'Soriano', 'Dermatology', 'Interventional Dermatology', 9, 'MED - 11', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"07:00\",\"end_time\":\"16:00\"},\"working_days\":[\"Tuesday\",\"Thursday\",\"Saturday\"],\"appointment_duration\":\"45\",\"break_time\":{\"enabled\":false,\"start_time\":\"13:00\",\"duration\":\"45\"}}'),
(30, 19, 'Mateo', 'Villanueva', 'Dermatology', 'Interventional Dermatology', 7, 'MED - 12', '1234-4567-89', 'BSMED', '{\"working_hours\":{\"start_time\":\"09:00\",\"end_time\":\"18:00\"},\"working_days\":[\"Monday\",\"Tuesday\",\"Wednesday\",\"Thursday\",\"Friday\",\"Saturday\",\"Sunday\"],\"appointment_duration\":\"60\",\"break_time\":{\"enabled\":false,\"start_time\":\"11:00\",\"duration\":\"60\"}}');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_contacts`
--

CREATE TABLE `emergency_contacts` (
  `contact_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `emergency_contacts`
--

INSERT INTO `emergency_contacts` (`contact_id`, `patient_id`, `full_name`, `relationship`, `phone`, `is_primary`) VALUES
(1, 3, 'Ronaldo M. Dizon', 'Father', '09998011767', 1);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_seen` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `message`, `type`, `is_read`, `created_at`, `is_seen`) VALUES
(0, 20, 'Your appointment with Dr. Sofia Dela Cruz is confirmed for May 23, 2025 at 4:15 PM', 'appointment', 0, '2025-05-22 14:22:36', 0),
(0, 20, 'Your appointment with Dr. Rafael Mendoza is confirmed for May 29, 2025 at 10:00 AM', 'appointment', 0, '2025-05-22 14:25:24', 0),
(0, 54, 'Your appointment with Dr. Amelia Reyes is confirmed for May 28, 2025 at 2:00 PM', 'appointment', 0, '2025-05-26 10:50:27', 0),
(0, 82, 'Your appointment with Dr. Amelia Reyes is confirmed for May 30, 2025 at 9:00 AM', 'appointment', 1, '2025-05-28 15:13:38', 0),
(0, 82, 'Your appointment with Dr. Marco de Guzman is confirmed for June 1, 2025 at 10:30 AM', 'appointment', 1, '2025-05-28 16:00:57', 0),
(0, 82, 'Your appointment with Dr. Clarisse Uy is confirmed for May 31, 2025 at 2:00 PM', 'appointment', 1, '2025-05-28 16:04:44', 0),
(0, 82, 'Your appointment with Dr. Doctor1 Doctor is confirmed for June 19, 2025 at 8:00 AM', 'appointment', 1, '2025-05-30 14:55:33', 0),
(0, 83, 'Your appointment with Dr. Test Doctor is confirmed for May 31, 2025 at 12:30 PM', 'appointment', 0, '2025-05-31 07:05:06', 0),
(0, 83, 'Your appointment with Dr. Test Doctor is confirmed for May 31, 2025 at 10:00 AM', 'appointment', 0, '2025-05-31 07:09:17', 0),
(0, 83, 'Your appointment with Dr. Test Doctor is confirmed for June 3, 2025 at 9:45 AM', 'appointment', 0, '2025-05-31 07:11:10', 0),
(0, 83, 'Your appointment with Dr. Test Doctor is confirmed for May 31, 2025 at 3:15 PM', 'appointment', 0, '2025-05-31 07:15:16', 0),
(0, 83, 'Your appointment with Dr. Test Doctor is confirmed for May 31, 2025 at 11:00 AM', 'appointment', 0, '2025-05-31 11:48:20', 0),
(0, 90, 'Your appointment with Dr. Doctor Test is confirmed for June 3, 2025 at 11:15 AM', 'appointment', 0, '2025-06-01 05:25:05', 0),
(0, 90, 'Your appointment with Dr. Doctor Test is confirmed for June 18, 2025 at 2:30 PM', 'appointment', 0, '2025-06-01 05:26:30', 0),
(0, 90, 'Your appointment with Dr. Doctor Test is confirmed for June 1, 2025 at 3:00 PM', 'appointment', 0, '2025-06-01 05:28:43', 0),
(0, 93, 'Your appointment with Dr. Test Doctor is confirmed for June 6, 2025 at 3:00 PM', 'appointment', 0, '2025-06-02 11:46:34', 0),
(0, 93, 'Your appointment with Dr. Test Doctor is confirmed for June 2, 2025 at 3:00 PM', 'appointment', 0, '2025-06-02 11:48:43', 0),
(0, 93, 'Your appointment with Dr. Test Doctor is confirmed for June 4, 2025 at 3:00 PM', 'appointment', 0, '2025-06-02 11:51:08', 0),
(0, 95, 'Your appointment with Dr. Sunshine Laganson is confirmed for June 4, 2025 at 10:00 AM', 'appointment', 0, '2025-06-02 23:27:41', 0),
(0, 95, 'Your appointment with Dr. Sunshine Laganson is confirmed for June 3, 2025 at 1:00 PM', 'appointment', 0, '2025-06-02 23:30:16', 0);

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `state` varchar(50) NOT NULL,
  `zip_code` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patient_id`, `user_id`, `first_name`, `last_name`, `date_of_birth`, `gender`, `email`, `phone_number`, `address`, `city`, `state`, `zip_code`) VALUES
(3, 20, 'Jovans', 'Nadala', '2004-02-17', 'Male', 'jovan.nadala@gmail.com', '09474775686', 'Anywhere St. Hello!', 'Anycity', 'Anystate', '1234'),
(6, 22, 'Sebastian', 'Tagalog', '2004-12-05', 'Male', 'sebastian.tagalog@mail.com', '09455787845', 'Anywhere St. ', 'Anycity', 'Anystate', '3456'),
(7, 21, 'Jema', 'Legaspi', '2004-04-08', 'Female', 'jema.legaspi@mail.com', '09121234567', 'Anywhere St.', 'Anycity', 'Anystate', '4322');

-- --------------------------------------------------------

--
-- Table structure for table `patient_documents`
--

CREATE TABLE `patient_documents` (
  `document_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `document_type` enum('Prescription','Lab Report','Insurance','Other') DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_documents`
--

INSERT INTO `patient_documents` (`document_id`, `patient_id`, `appointment_id`, `file_name`, `file_path`, `document_type`, `uploaded_at`) VALUES
(1, 3, NULL, 'UML - CHAPTER 6.png', '../../uploads/patient_documents/682d855025af2_UML - CHAPTER 6.png', 'Other', '2025-05-21 07:48:32');

-- --------------------------------------------------------

--
-- Table structure for table `patient_insurance`
--

CREATE TABLE `patient_insurance` (
  `insurance_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `provider` varchar(100) NOT NULL,
  `policy_number` varchar(50) NOT NULL,
  `group_number` varchar(50) DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_insurance`
--

INSERT INTO `patient_insurance` (`insurance_id`, `patient_id`, `provider`, `policy_number`, `group_number`, `expiry_date`, `is_primary`) VALUES
(1, 3, 'blue-cross', '9009-1223', NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `patient_medical_info`
--

CREATE TABLE `patient_medical_info` (
  `medical_info_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `blood_type` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `chronic_conditions` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient_medical_info`
--

INSERT INTO `patient_medical_info` (`medical_info_id`, `patient_id`, `blood_type`, `allergies`, `chronic_conditions`, `current_medications`) VALUES
(2, 3, NULL, '', 'Cancer, Allergies', 'Anymeds');

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
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `zip_code` varchar(10) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_type` enum('Patient','Doctor','Admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `terms_accepted` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `profile_image` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `phone_number`, `date_of_birth`, `gender`, `address`, `city`, `state`, `zip_code`, `password_hash`, `account_type`, `created_at`, `updated_at`, `terms_accepted`, `is_active`, `profile_image`) VALUES
(6, 'Amelia Reyes', 'areyes.md@example.com', '09171234501', '1980-03-14', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$H2TURg2P9Wnr7xouN38XhOiTfvBDCLfHZ/vlSQgCW1cnrAmu.Veky', 'Doctor', '2025-05-17 14:31:49', '2025-05-17 14:56:43', 0, 1, '68289dd4f3b38.png'),
(7, 'Nathan Cruz', 'ncruz.md@example.com', '9281234502', '1983-07-25', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$2K77qlpKtTUFWmGK6WRG9O53CrIn254imr4t5/P/uIiakzRSUwJYm', 'Doctor', '2025-05-17 14:32:51', '2025-05-17 14:58:44', 0, 1, '68289e132fc37.png'),
(8, 'Bianca Morales', 'bmoral.md@example.com', '09391234503', '1987-09-05', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$RInCUjiqhYEJHqVFYElzZ.JCn0wm2AG3xfAbv8CFtpjHPbwONdiym', 'Doctor', '2025-05-17 14:37:42', '2025-05-17 15:03:15', 0, 1, '68289f3636b13.png'),
(10, 'Elijahhhh Santiago', 'esantiago.md@example.com', '09181234504', '2003-08-04', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$5DWB2ts63UZ4/sZm/UZpROdUC/MPNTo160fz2r54hsXLHxWxqdoO6', 'Doctor', '2025-05-17 14:42:45', '2025-06-02 23:17:09', 0, 1, '6828a0652ba6a.png'),
(11, 'Hana Lim', 'hlim.md@example.com', '09191234505', '1985-03-02', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$YTIU0AP5zywBc0MiGkF4kOXmFISsd00gkjiJfkwKyg.MEGvntTMvW', 'Doctor', '2025-05-17 14:44:17', '2025-05-17 15:42:58', 0, 1, '6828a0c139a71.png'),
(12, 'Marco de Guzman', 'mdeguz.md@example.com', '09471234506', '1982-06-18', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$6aIr0A7eB5pAEKwHxjcK..MXxgCog4b3Kp.MufZ628zZIIJ50y0Y6', 'Doctor', '2025-05-17 14:46:26', '2025-05-17 15:48:51', 0, 1, '6828a1424c08e.png'),
(13, 'Sofia Dela Cruz', 'sdc.md@example.com', '09221234507', '1990-08-29', 'Female', 'Anywhere St.', 'Anycity', '', '1234', '$2y$10$PzP9/LSHRS0LSS8uvy0WK.p4yy8hK3/ERA/Mw4vMHG92flD4Hh8Oq', 'Doctor', '2025-05-17 14:48:38', '2025-05-17 15:50:57', 0, 1, '6828a1c6e3d78.png'),
(14, 'Rafael Mendoza', 'rmendoza.md@example.com', '09261234508', '1986-07-05', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$hUPC0M/cjm5J2lCmCt0PxO7aUxQ6R15WbhOPG7kHRsGYpDUnUlHjy', 'Doctor', '2025-05-17 14:50:06', '2025-05-17 15:53:55', 0, 1, '6828a21e32449.png'),
(15, 'Clarisse Uy', 'chuy.md@example.com', '09321234509', '1984-04-12', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$Olv/zn3A/iZ3Lbt5CD2YoekqobAnfWQkwqVWS5NgrcVPavnKP90lC', 'Doctor', '2025-05-17 14:51:53', '2025-05-17 15:56:13', 0, 1, '6828a28963963.png'),
(16, 'Lorenzo Tan', 'ltan.md@example.com', '09151234510', '1981-12-12', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$yqFq8ZyZ00irGofoSNdV4e98ucvSkW83XjnoK8fyUSq9ag9ZvtfZe', 'Doctor', '2025-05-17 14:53:03', '2025-05-17 15:57:55', 0, 1, '6828a2cef345d.png'),
(17, 'Isabel Soriano', 'isoriano.md@example.com', '9431234511', '1988-02-10', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$AIXq8uQ26gVnOM4xgbn0deyBUGhqztgEFoKQEzSel7LHmbOregae6', 'Doctor', '2025-05-17 14:54:49', '2025-05-17 16:00:27', 0, 1, '6828a339bfb4a.png'),
(18, 'Jeffrey Deilo', 'jeffrey.deilo@mail.com', '09181234504', '1976-07-18', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$rH/CjlBi61WC6hewjoW0WuO4YCSXgNhfh1hh0EMH3dJoSNz4f7ITm', 'Doctor', '2025-05-17 15:19:58', '2025-05-17 15:34:21', 0, 1, '6828a91e30765.jpg'),
(19, 'Mateo Villanueva', 'mvilla.md@example.com', '09291234512', '1980-01-30', 'Male', 'Anywhere St.', 'Anycity', 'Anystate', '1234', '$2y$10$7OK7XuwQ.YBuryLUSmp2peRAqzbTMRNO1YV4a5TwKsqdNbCyhY7AG', 'Doctor', '2025-05-17 16:02:12', '2025-05-17 16:03:01', 0, 1, '6828b304198f8.png'),
(20, 'Jovan Nadala', 'jovan.nadala@gmail.com', '09474775686', '2004-02-17', 'Male', 'Anywhere St. Hello!', 'Anycity', 'Anystate', '1234', '$2y$10$JMYyDZFLHDJBh9LumoFxkuespxbmA7kYES/j5cMkyyHYM4OZnSPva', 'Patient', '2025-05-21 00:43:20', '2025-05-22 17:26:05', 0, 1, 'profile_682f50018a0ba.jpg'),
(21, 'Jema Legaspi', 'jema.legaspi@mail.com', '09121234567', '2004-04-08', 'Female', 'Anywhere St.', 'Anycity', 'Anystate', '4322', '$2y$10$fZQdQhedwmUICX5f0pT5AOPhCZ.VedqCCbw.dqmrGy2Nb66YJXNaS', 'Patient', '2025-05-22 16:51:53', '2025-05-22 16:54:53', 0, 1, '682f5628f0ecf.png'),
(22, 'Sebastian Tagalog', 'sebastian.tagalog@mail.com', '09455787845', '2004-12-05', 'Male', 'Anywhere St. ', 'Anycity', 'Anystate', '3456', '$2y$10$N9jZqb4T0shX/BGQ1edMLeu1RhCEm2MEXtXu2K7VKP29u8Bh4Vd8C', 'Patient', '2025-05-22 16:53:02', '2025-05-22 16:55:51', 0, 1, '682f566ee1774.png'),
(24, 'Admin ', 'admin@gmail.com', '01234567890', '2003-12-04', 'Female', 'Anyadress', 'Anycity', 'Anystate', '', '$2y$10$RSp7p2kmMk1Pq6RBOEmudOLUroUKyEZEGkwDFe7xH7lIc6CjJOU0G', 'Admin', '2025-05-24 04:11:16', '2025-07-11 14:16:57', 0, 1, '../../images/admins/default.png');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `appointment_activity`
--
ALTER TABLE `appointment_activity`
  ADD PRIMARY KEY (`activity_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `doctor_details`
--
ALTER TABLE `doctor_details`
  ADD PRIMARY KEY (`doctor_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  ADD PRIMARY KEY (`contact_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `patient_documents`
--
ALTER TABLE `patient_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `appointment_id` (`appointment_id`);

--
-- Indexes for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD PRIMARY KEY (`insurance_id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `patient_medical_info`
--
ALTER TABLE `patient_medical_info`
  ADD PRIMARY KEY (`medical_info_id`),
  ADD KEY `patient_id` (`patient_id`);

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
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `appointment_activity`
--
ALTER TABLE `appointment_activity`
  MODIFY `activity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `doctor_details`
--
ALTER TABLE `doctor_details`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  MODIFY `contact_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `patient_documents`
--
ALTER TABLE `patient_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  MODIFY `insurance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `patient_medical_info`
--
ALTER TABLE `patient_medical_info`
  MODIFY `medical_info_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_details` (`doctor_id`);

--
-- Constraints for table `appointment_activity`
--
ALTER TABLE `appointment_activity`
  ADD CONSTRAINT `appointment_activity_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`);

--
-- Constraints for table `doctor_details`
--
ALTER TABLE `doctor_details`
  ADD CONSTRAINT `doctor_details_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  ADD CONSTRAINT `emergency_contacts_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `patient_documents`
--
ALTER TABLE `patient_documents`
  ADD CONSTRAINT `patient_documents_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `patient_documents_ibfk_2` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL;

--
-- Constraints for table `patient_insurance`
--
ALTER TABLE `patient_insurance`
  ADD CONSTRAINT `patient_insurance_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `patient_medical_info`
--
ALTER TABLE `patient_medical_info`
  ADD CONSTRAINT `patient_medical_info_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
