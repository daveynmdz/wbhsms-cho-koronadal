-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 06, 2025 at 02:04 PM
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
-- Database: `wbhsms_database`
--

-- --------------------------------------------------------

--
-- Table structure for table `allergies`
--

CREATE TABLE `allergies` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `allergen` varchar(100) DEFAULT NULL,
  `reaction` varchar(100) DEFAULT NULL,
  `severity` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `allergies`
--

INSERT INTO `allergies` (`id`, `patient_id`, `allergen`, `reaction`, `severity`) VALUES
(5, 7, 'Not Applicable', 'N/A', 'N/A');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `facility_id` int(10) UNSIGNED NOT NULL,
  `referral_id` int(10) UNSIGNED DEFAULT NULL,
  `service_id` int(10) UNSIGNED DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time NOT NULL,
  `status` enum('confirmed','completed','cancelled','checked_in') DEFAULT 'confirmed',
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `patient_id`, `facility_id`, `referral_id`, `service_id`, `scheduled_date`, `scheduled_time`, `status`, `cancellation_reason`, `created_at`, `updated_at`) VALUES
(1, 7, 4, NULL, 1, '2025-09-30', '12:00:00', 'cancelled', 'Schedule Conflict', '2025-09-29 12:56:32', '2025-09-30 05:44:21'),
(2, 8, 12, NULL, 1, '2025-09-30', '16:00:00', 'cancelled', 'Personal Emergency', '2025-09-30 03:33:30', '2025-09-30 03:54:04'),
(3, 8, 12, NULL, 1, '2025-09-30', '15:00:00', 'cancelled', 'Automatically cancelled - appointment time has passed', '2025-09-30 03:54:35', '2025-10-02 04:16:26'),
(4, 7, 1, 2, 1, '2025-09-30', '16:00:00', 'cancelled', 'Automatically cancelled - appointment time has passed', '2025-09-30 07:44:51', '2025-10-02 04:16:26'),
(5, 7, 4, NULL, 1, '2025-10-01', '12:00:00', 'cancelled', 'Automatically cancelled - appointment time has passed', '2025-09-30 08:08:22', '2025-10-02 04:16:26'),
(6, 37, 22, NULL, 1, '2025-10-01', '09:00:00', 'cancelled', 'Automatically cancelled - appointment time has passed', '2025-09-30 08:34:29', '2025-10-02 04:16:26'),
(7, 37, 1, 8, 1, '2025-10-02', '12:00:00', 'cancelled', 'Automatically cancelled - appointment time has passed', '2025-09-30 08:42:00', '2025-10-02 04:31:57'),
(8, 7, 4, NULL, 1, '2025-10-02', '16:00:00', 'cancelled', 'Schedule Conflict', '2025-10-02 06:15:58', '2025-10-02 07:25:45'),
(9, 7, 4, NULL, 1, '2025-10-02', '16:00:00', 'cancelled', 'Automatically cancelled - appointment time has passed', '2025-10-02 07:34:44', '2025-10-02 15:54:42'),
(22, 7, 1, 12, 1, '2025-10-06', '13:00:00', 'cancelled', 'Personal Emergency', '2025-10-05 23:29:52', '2025-10-05 23:57:52'),
(23, 7, 1, 12, 1, '2025-10-07', '13:00:00', 'confirmed', NULL, '2025-10-05 23:44:50', '2025-10-05 23:44:50'),
(24, 7, 4, NULL, 1, '2025-10-06', '16:00:00', 'confirmed', NULL, '2025-10-06 01:09:50', '2025-10-06 01:09:50');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_logs`
--

CREATE TABLE `appointment_logs` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `action` enum('created','confirmed','cancelled','completed','rescheduled','updated') NOT NULL,
  `old_status` varchar(20) DEFAULT NULL,
  `new_status` varchar(20) DEFAULT NULL,
  `old_scheduled_date` date DEFAULT NULL,
  `new_scheduled_date` date DEFAULT NULL,
  `old_scheduled_time` time DEFAULT NULL,
  `new_scheduled_time` time DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by_type` enum('patient','employee','system') NOT NULL DEFAULT 'patient',
  `created_by_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointment_logs`
--

INSERT INTO `appointment_logs` (`log_id`, `appointment_id`, `patient_id`, `action`, `old_status`, `new_status`, `old_scheduled_date`, `new_scheduled_date`, `old_scheduled_time`, `new_scheduled_time`, `reason`, `notes`, `created_by_type`, `created_by_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 22, 7, 'created', NULL, 'confirmed', NULL, '2025-10-06', NULL, '13:00:00', 'Appointment created', NULL, '', 7, '192.168.1.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-05 23:29:52'),
(2, 23, 7, 'created', NULL, 'confirmed', NULL, '2025-10-07', NULL, '13:00:00', 'Appointment created', NULL, '', 7, '192.168.1.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-05 23:44:50'),
(3, 22, 7, 'cancelled', 'confirmed', 'cancelled', NULL, NULL, NULL, NULL, 'Personal Emergency', NULL, 'patient', 7, '192.168.1.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-05 23:57:52'),
(4, 24, 7, 'created', NULL, 'confirmed', NULL, '2025-10-06', NULL, '16:00:00', 'Appointment created', NULL, '', 7, '192.168.1.2', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-06 01:09:50');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_logs`
--

CREATE TABLE `assignment_logs` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `station_id` int(10) UNSIGNED NOT NULL,
  `action_type` enum('created','updated','ended','deactivated','reactivated','reassigned') NOT NULL,
  `action_date` date NOT NULL,
  `performed_by` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assignment_logs`
--

INSERT INTO `assignment_logs` (`log_id`, `schedule_id`, `employee_id`, `station_id`, `action_type`, `action_date`, `performed_by`, `notes`, `created_at`) VALUES
(1, 1, 86, 16, '', '2025-10-05', 1, 'Assignment temporarily deactivated', '2025-10-05 18:29:07'),
(2, 1, 86, 16, '', '2025-10-05', 1, 'Assignment ended on 2025-10-05', '2025-10-05 18:46:53'),
(3, 1, 86, 16, '', '2025-10-05', 1, 'Assignment temporarily deactivated', '2025-10-05 18:47:00');

-- --------------------------------------------------------

--
-- Table structure for table `assignment_schedules`
--

CREATE TABLE `assignment_schedules` (
  `schedule_id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `station_id` int(10) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `assignment_type` enum('permanent','temporary') DEFAULT 'permanent',
  `shift_start_time` time DEFAULT '08:00:00',
  `shift_end_time` time DEFAULT '17:00:00',
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assignment_schedules`
--

INSERT INTO `assignment_schedules` (`schedule_id`, `employee_id`, `station_id`, `start_date`, `end_date`, `assignment_type`, `shift_start_time`, `shift_end_time`, `assigned_by`, `assigned_at`, `is_active`, `notes`) VALUES
(1, 86, 16, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 18:47:08', 1, NULL),
(2, 75, 1, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 15:01:17', 1, NULL),
(3, 3, 2, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 15:01:25', 1, NULL),
(4, 75, 3, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 15:01:31', 0, NULL),
(5, 73, 5, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 15:07:04', 1, NULL),
(6, 2, 8, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:40:41', 1, NULL),
(7, 80, 9, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:40:51', 1, NULL),
(8, 74, 11, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:41:07', 1, NULL),
(9, 72, 7, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:41:16', 1, NULL),
(10, 7, 13, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:41:33', 1, NULL),
(11, 79, 14, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:41:42', 1, NULL),
(12, 77, 15, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:41:53', 1, NULL),
(13, 6, 4, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:42:00', 1, NULL),
(14, 85, 12, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:42:10', 1, NULL),
(15, 89, 10, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:55:22', 1, NULL),
(16, 87, 6, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:55:31', 1, NULL),
(17, 90, 3, '2025-10-05', NULL, 'permanent', '08:00:00', '17:00:00', 1, '2025-10-05 17:55:41', 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `barangay`
--

CREATE TABLE `barangay` (
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `barangay_name` varchar(100) NOT NULL,
  `district_id` int(10) UNSIGNED NOT NULL,
  `city` varchar(100) NOT NULL DEFAULT 'Koronadal City',
  `province` varchar(100) NOT NULL DEFAULT 'South Cotabato',
  `zip_code` varchar(10) NOT NULL DEFAULT '9506',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `barangay`
--

INSERT INTO `barangay` (`barangay_id`, `barangay_name`, `district_id`, `city`, `province`, `zip_code`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Brgy. Assumption', 1, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(2, 'Brgy. Avanceña', 3, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(3, 'Brgy. Cacub', 2, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(4, 'Brgy. Caloocan', 3, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(5, 'Brgy. Carpenter Hill', 2, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(6, 'Brgy. Concepcion', 2, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(7, 'Brgy. Esperanza', 2, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(8, 'Brgy. General Paulino Santos', 3, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(9, 'Brgy. Mabini', 3, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(10, 'Brgy. Magsaysay', 2, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(11, 'Brgy. Mambucal', 1, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(12, 'Brgy. Morales', 3, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(13, 'Brgy. Namnama', 3, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(14, 'Brgy. New Pangasinan', 2, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(15, 'Brgy. Paraiso', 1, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(16, 'Brgy. Rotonda', 3, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(17, 'Brgy. San Isidro', 1, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(18, 'Brgy. San Roque', 2, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(19, 'Brgy. San Jose', 3, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(20, 'Brgy. Sta. Cruz', 1, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(21, 'Brgy. Sto. Niño', 1, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(22, 'Brgy. Saravia', 1, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(23, 'Brgy. Topland', 2, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(24, 'Brgy. Zone 1', 3, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(25, 'Brgy. Zone 2', 1, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(26, 'Brgy. Zone 3', 1, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49'),
(27, 'Brgy. Zone 4', 1, 'Koronadal City', 'South Cotabato', '9506', 'active', '2025-09-21 03:50:25', '2025-09-25 05:33:49');

-- --------------------------------------------------------

--
-- Table structure for table `billing`
--

CREATE TABLE `billing` (
  `billing_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `billing_date` datetime NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid','exempted','cancelled') DEFAULT 'unpaid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `billing_items`
--

CREATE TABLE `billing_items` (
  `billing_item_id` int(10) UNSIGNED NOT NULL,
  `billing_id` int(10) UNSIGNED NOT NULL,
  `service_item_id` int(10) UNSIGNED NOT NULL,
  `item_price` decimal(10,2) NOT NULL,
  `quantity` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `subtotal` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chronic_illnesses`
--

CREATE TABLE `chronic_illnesses` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `illness` varchar(100) DEFAULT NULL,
  `year_diagnosed` year(4) DEFAULT NULL,
  `management` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chronic_illnesses`
--

INSERT INTO `chronic_illnesses` (`id`, `patient_id`, `illness`, `year_diagnosed`, `management`) VALUES
(2, 7, 'Not Applicable', '2025', 'N/A'),
(3, 36, 'Not Applicable', '2025', 'N/A');

-- --------------------------------------------------------

--
-- Table structure for table `consultations`
--

CREATE TABLE `consultations` (
  `consultation_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `attending_employee_id` int(10) UNSIGNED NOT NULL,
  `consultation_date` datetime NOT NULL DEFAULT current_timestamp(),
  `chief_complaint` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `treatment_plan` text DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `consultation_status` enum('ongoing','completed','awaiting_lab_results','awaiting_followup','cancelled') DEFAULT 'ongoing',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `current_medications`
--

CREATE TABLE `current_medications` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `medication` varchar(100) DEFAULT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  `frequency` varchar(50) DEFAULT NULL,
  `prescribed_by` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `current_medications`
--

INSERT INTO `current_medications` (`id`, `patient_id`, `medication`, `dosage`, `frequency`, `prescribed_by`) VALUES
(2, 7, 'Not Applicable', 'N/A', 'N/A', NULL),
(3, 36, 'Lisinopril', '21', 'Twice Daily', 'FY');

-- --------------------------------------------------------

--
-- Table structure for table `districts`
--

CREATE TABLE `districts` (
  `district_id` int(10) UNSIGNED NOT NULL,
  `district_name` varchar(100) NOT NULL,
  `district_code` varchar(20) NOT NULL,
  `city` varchar(100) DEFAULT 'Koronadal City',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `districts`
--

INSERT INTO `districts` (`district_id`, `district_name`, `district_code`, `city`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Main District', 'MAIN', 'Koronadal City', 'active', '2025-09-25 05:29:59', '2025-09-25 05:29:59'),
(2, 'Concepcion District', 'CONCEP', 'Koronadal City', 'active', '2025-09-25 05:29:59', '2025-09-25 05:29:59'),
(3, 'GPS District', 'GPS', 'Koronadal City', 'active', '2025-09-25 05:29:59', '2025-09-25 05:29:59');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_contact`
--

CREATE TABLE `emergency_contact` (
  `contact_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `emergency_first_name` varchar(50) NOT NULL,
  `emergency_middle_name` varchar(50) DEFAULT NULL,
  `emergency_last_name` varchar(50) NOT NULL,
  `emergency_relationship` varchar(50) NOT NULL,
  `emergency_contact_number` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `emergency_contact`
--

INSERT INTO `emergency_contact` (`contact_id`, `patient_id`, `emergency_first_name`, `emergency_middle_name`, `emergency_last_name`, `emergency_relationship`, `emergency_contact_number`, `created_at`, `updated_at`) VALUES
(1, 7, 'Irish Joyce', NULL, 'Diaz', 'Sibling', '09451847785', '2025-09-21 16:36:04', '2025-09-21 16:36:04'),
(2, 36, 'Neil Clark Jeavin', 'Lorenzo', 'Sacco', 'Spouse', '09603446003', '2025-09-28 14:46:58', '2025-09-28 14:46:58');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(10) UNSIGNED NOT NULL,
  `employee_number` varchar(20) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `contact_num` varchar(20) DEFAULT NULL,
  `role_id` int(10) UNSIGNED NOT NULL,
  `facility_id` int(10) UNSIGNED NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive','on_leave','retired') DEFAULT 'active',
  `license_number` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `employee_number`, `first_name`, `middle_name`, `last_name`, `email`, `contact_num`, `role_id`, `facility_id`, `password`, `status`, `license_number`, `birth_date`, `gender`, `created_at`, `updated_at`) VALUES
(1, 'EMP00001', 'Alice', 'Marie', 'Smith', 'daveynmdz.sti@gmail.com', '09170000001', 1, 1, '$2a$12$awcWf4nRPZPP02sRprlpMuOKsZS2s0BivPoILbDhTlUraBwf3XjbW', 'active', NULL, '1985-02-10', 'female', '2025-09-21 04:42:45', '2025-09-26 10:28:26'),
(2, 'EMP00002', 'John', 'Michael', 'Doe', 'daveynmdz.sti@gmail.com', '09170000002', 2, 1, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-01-01', 'male', '2025-09-26 10:55:53', '2025-09-26 10:56:21'),
(3, 'EMP00003', 'Anna', 'Marie', 'Reyes', 'daveynmdz.sti@gmail.com', '09170000003', 3, 1, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-02-02', 'female', '2025-09-26 10:55:53', '2025-09-26 10:56:26'),
(4, 'EMP00004', 'David', 'Lee', 'Santos', 'daveynmdz.sti@gmail.com', '09170000004', 4, 1, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1992-03-03', 'male', '2025-09-26 10:55:53', '2025-09-26 10:56:30'),
(5, 'EMP00005', 'Maria', 'Elena', 'Torres', 'daveynmdz.sti@gmail.com', '09170000005', 7, 1, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1993-04-04', 'female', '2025-09-26 10:55:53', '2025-09-26 10:56:35'),
(6, 'EMP00006', 'Carlos', 'Andres', 'Garcia', 'daveynmdz.sti@gmail.com', '09170000006', 8, 1, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1994-05-05', 'male', '2025-09-26 10:55:53', '2025-09-26 10:56:40'),
(7, 'EMP00007', 'Sofia', 'Grace', 'Lopez', 'daveynmdz.sti@gmail.com', '09170000007', 9, 1, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1995-06-06', 'female', '2025-09-26 10:55:53', '2025-09-26 10:56:47'),
(8, 'EMP00008', 'Miguel', 'Antonio', 'Ramos', 'daveynmdz.sti@gmail.com', '09170000008', 5, 2, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1992-07-07', 'male', '2025-09-26 10:59:11', '2025-09-26 10:59:21'),
(9, 'EMP00009', 'Isabella', 'Marie', 'Cruz', 'daveynmdz.sti@gmail.com', '09170000009', 5, 3, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1993-08-08', 'female', '2025-09-26 10:59:11', '2025-09-26 10:59:26'),
(10, 'EMP00010', 'Daniel', 'Reyes', 'Morales', 'daveynmdz.sti@gmail.com', '09170000010', 6, 4, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-09-09', 'male', '2025-09-26 11:02:18', '2025-09-26 11:02:39'),
(46, 'EMP00046', 'Lucas', 'Antonio', 'Reyes', 'daveynmdz.sti@gmail.com', '09170000046', 6, 5, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-01-01', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(47, 'EMP00047', 'Sofia', 'Marie', 'Cruz', 'daveynmdz.sti@gmail.com', '09170000047', 6, 6, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-02-02', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(48, 'EMP00048', 'Carlos', 'David', 'Torres', 'daveynmdz.sti@gmail.com', '09170000048', 6, 7, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-03-03', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(49, 'EMP00049', 'Maria', 'Elena', 'Lopez', 'daveynmdz.sti@gmail.com', '09170000049', 6, 8, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-04-04', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(50, 'EMP00050', 'Diego', 'Andres', 'Garcia', 'daveynmdz.sti@gmail.com', '09170000050', 6, 9, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-05-05', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(51, 'EMP00051', 'Elena', 'Grace', 'Santos', 'daveynmdz.sti@gmail.com', '09170000051', 6, 10, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-06-06', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(52, 'EMP00052', 'Mateo', 'Jose', 'Ramos', 'daveynmdz.sti@gmail.com', '09170000052', 6, 11, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-07-07', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(53, 'EMP00053', 'Camila', 'Isabel', 'Flores', 'daveynmdz.sti@gmail.com', '09170000053', 6, 12, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-08-08', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(54, 'EMP00054', 'Javier', 'Luis', 'Mendoza', 'daveynmdz.sti@gmail.com', '09170000054', 6, 13, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-09-09', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(55, 'EMP00055', 'Valeria', 'Anne', 'Diaz', 'daveynmdz.sti@gmail.com', '09170000055', 6, 14, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-10-10', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(56, 'EMP00056', 'Andres', 'Miguel', 'Navarro', 'daveynmdz.sti@gmail.com', '09170000056', 6, 15, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-11-11', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(57, 'EMP00057', 'Lucia', 'Marina', 'Ortiz', 'daveynmdz.sti@gmail.com', '09170000057', 6, 16, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1990-12-12', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(58, 'EMP00058', 'Ricardo', 'Jose', 'Fernandez', 'daveynmdz.sti@gmail.com', '09170000058', 6, 17, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-01-13', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(59, 'EMP00059', 'Gabriela', 'Rosa', 'Martinez', 'daveynmdz.sti@gmail.com', '09170000059', 6, 18, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-02-14', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(60, 'EMP00060', 'Francisco', 'Antonio', 'Castro', 'daveynmdz.sti@gmail.com', '09170000060', 6, 19, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-03-15', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(61, 'EMP00061', 'Natalia', 'Beatriz', 'Gutierrez', 'daveynmdz.sti@gmail.com', '09170000061', 6, 20, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-04-16', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(62, 'EMP00062', 'Rafael', 'Manuel', 'Dominguez', 'daveynmdz.sti@gmail.com', '09170000062', 6, 21, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-05-17', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(63, 'EMP00063', 'Clara', 'Isidora', 'Herrera', 'daveynmdz.sti@gmail.com', '09170000063', 6, 22, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-06-18', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(64, 'EMP00064', 'Fernando', 'Ignacio', 'Silva', 'daveynmdz.sti@gmail.com', '09170000064', 6, 23, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-07-19', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(65, 'EMP00065', 'Daniela', 'Sofia', 'Vargas', 'daveynmdz.sti@gmail.com', '09170000065', 6, 24, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-08-20', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(66, 'EMP00066', 'Hector', 'Julio', 'Jimenez', 'daveynmdz.sti@gmail.com', '09170000066', 6, 25, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-09-21', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(67, 'EMP00067', 'Andrea', 'Lucia', 'Romero', 'daveynmdz.sti@gmail.com', '09170000067', 6, 26, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-10-22', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(68, 'EMP00068', 'Manuel', 'Diego', 'Paredes', 'daveynmdz.sti@gmail.com', '09170000068', 6, 27, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-11-23', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(69, 'EMP00069', 'Angela', 'Teresa', 'Suarez', 'daveynmdz.sti@gmail.com', '09170000069', 6, 28, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1991-12-24', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(70, 'EMP00070', 'Roberto', 'Alonso', 'Cabrera', 'daveynmdz.sti@gmail.com', '09170000070', 6, 29, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1992-01-25', 'male', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(71, 'EMP00071', 'Paula', 'Ines', 'Aguilar', 'daveynmdz.sti@gmail.com', '09170000071', 6, 30, '$2a$12$FdnTxkvzlCFVQR/XijAoXuoBjwyYQolQGd2Prj.m3m8.skQghvfna', 'active', NULL, '1992-02-26', 'female', '2025-09-26 11:05:35', '2025-09-26 11:05:35'),
(72, 'EMP00072', 'Maria', 'Lopez', 'Santos', 'daveynmdz.sti@gmail.com', '09170000001', 2, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'N/A', '1992-05-10', 'female', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(73, 'EMP00073', 'John', 'Reyes', 'Cruz', 'daveynmdz.sti@gmail.com', '09170000002', 2, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'N/A', '1988-11-22', 'male', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(74, 'EMP00074', 'Angela', 'Rivera', 'Torres', 'daveynmdz.sti@gmail.com', '09170000003', 2, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'N/A', '1995-03-15', 'female', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(75, 'EMP00075', 'Carlos', 'Manuel', 'Fernandez', 'daveynmdz.sti@gmail.com', '09170000004', 3, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'N/A', '1987-07-30', 'male', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(76, 'EMP00076', 'Sophia', 'Garcia', 'Mendoza', 'daveynmdz.sti@gmail.com', '09170000005', 4, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'DOC12345', '1985-09-18', 'female', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(77, 'EMP00077', 'Michael', 'Santos', 'Dela Cruz', 'daveynmdz.sti@gmail.com', '09170000006', 4, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'DOC67890', '1983-01-25', 'male', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(78, 'EMP00078', 'Isabella', 'Marie', 'Navarro', 'daveynmdz.sti@gmail.com', '09170000007', 4, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'DENT001', '1990-04-12', 'female', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(79, 'EMP00079', 'Gabriel', 'Jose', 'Castillo', 'daveynmdz.sti@gmail.com', '09170000008', 4, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'TB12345', '1984-12-02', 'male', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(80, 'EMP00080', 'Emily', 'Grace', 'Flores', 'daveynmdz.sti@gmail.com', '09170000009', 2, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'N/A', '1993-06-21', 'female', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(81, 'EMP00081', 'Daniel', 'Ramos', 'Lopez', 'daveynmdz.sti@gmail.com', '09170000010', 4, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'FP98765', '1989-08-14', 'male', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(82, 'EMP00082', 'Victoria', 'Anne', 'Morales', 'daveynmdz.sti@gmail.com', '09170000011', 4, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'AB1234', '1991-02-27', 'female', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(83, 'EMP00083', 'Henry', 'Luis', 'Domingo', 'daveynmdz.sti@gmail.com', '09170000012', 5, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'N/A', '1986-10-05', 'male', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(84, 'EMP00084', 'Olivia', 'Rose', 'Bautista', 'daveynmdz.sti@gmail.com', '09170000013', 6, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'LAB001', '1994-11-19', 'female', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(85, 'EMP00085', 'Lucas', 'Miguel', 'Gutierrez', 'daveynmdz.sti@gmail.com', '09170000014', 7, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'PHAR123', '1988-03-07', 'male', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(86, 'EMP00086', 'Camila', 'Sophia', 'Ramos', 'daveynmdz.sti@gmail.com', '09170000015', 7, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', 'PHAR456', '1992-09-29', 'female', '2025-10-04 12:16:40', '2025-10-04 12:21:51'),
(87, 'EMP00087', 'Robert', 'James', 'Lopez', 'daveynmdz.sti@gmail.com', '09170000087', 2, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NULL, '1988-07-14', 'male', '2025-10-05 17:55:11', '2025-10-05 17:55:11'),
(88, 'EMP00088', 'Patricia', 'Anne', 'Garcia', 'daveynmdz.sti@gmail.com', '09170000088', 2, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NULL, '1991-03-25', 'female', '2025-10-05 17:55:11', '2025-10-05 17:55:11'),
(89, 'EMP00089', 'Michael', 'Reyes', 'Cruz', 'daveynmdz.sti@gmail.com', '09170000089', 2, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NULL, '1986-11-09', 'male', '2025-10-05 17:55:11', '2025-10-05 17:55:11'),
(90, 'EMP00090', 'Samantha', 'L.', 'Torres', 'daveynmdz.sti@gmail.com', '09170000090', 3, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NULL, '1993-04-12', 'female', '2025-10-05 17:55:11', '2025-10-05 17:55:11'),
(91, 'EMP00091', 'Daniel', 'P.', 'Fernandez', 'daveynmdz.sti@gmail.com', '09170000091', 3, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NULL, '1989-08-30', 'male', '2025-10-05 17:55:11', '2025-10-05 17:55:11'),
(92, 'EMP00092', 'Christine', 'A.', 'Mendoza', 'daveynmdz.sti@gmail.com', '09170000092', 3, 1, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'active', NULL, '1995-06-21', 'female', '2025-10-05 17:55:11', '2025-10-05 17:55:11');

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `facility_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` varchar(50) NOT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `district_id` int(10) UNSIGNED DEFAULT NULL,
  `district` varchar(50) NOT NULL,
  `is_main` tinyint(1) DEFAULT 0,
  `parent_facility_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`facility_id`, `name`, `type`, `barangay_id`, `district_id`, `district`, `is_main`, `parent_facility_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'City Health Office (Main District)', 'City Health Office', 27, 1, 'Main District', 1, NULL, 'active', '2025-09-21 04:06:10', '2025-09-25 05:34:31'),
(2, 'Concepcion District Health Office', 'District Health Office', 6, 2, 'Concepcion District', 0, NULL, 'active', '2025-09-21 04:06:10', '2025-09-25 05:34:31'),
(3, 'GPS District Health Office', 'District Health Office', 8, 3, 'GPS District', 0, NULL, 'active', '2025-09-21 04:06:10', '2025-09-25 05:34:31'),
(4, 'Zone II Health Center', 'Barangay Health Center', 25, 1, 'Main District', 0, 1, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(5, 'Zone III Health Center', 'Barangay Health Center', 26, 1, 'Main District', 0, 1, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(6, 'Zone IV Health Center', 'Barangay Health Center', 27, 1, 'Main District', 0, 1, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(7, 'Sta. Cruz Health Center', 'Barangay Health Center', 20, 1, 'Main District', 0, 1, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(8, 'Sto. Niño Health Center', 'Barangay Health Center', 21, 1, 'Main District', 0, 1, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(9, 'Saravia Health Center', 'Barangay Health Center', 22, 1, 'Main District', 0, 1, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(10, 'Paraiso Health Center', 'Barangay Health Center', 15, 1, 'Main District', 0, 1, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(11, 'San Isidro Health Center', 'Barangay Health Center', 17, 1, 'Main District', 0, 1, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(12, 'Assumption Health Center', 'Barangay Health Center', 1, 1, 'Main District', 0, 1, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(13, 'Mambucal Health Center', 'Barangay Health Center', 11, 1, 'Main District', 0, 1, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(14, 'Concepcion Health Center', 'Barangay Health Center', 6, 2, 'Concepcion District', 0, 2, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(15, 'Cacub Health Center', 'Barangay Health Center', 3, 2, 'Concepcion District', 0, 2, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(16, 'Topland Health Center', 'Barangay Health Center', 23, 2, 'Concepcion District', 0, 2, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(17, 'Carpenter Hill Health Center', 'Barangay Health Center', 5, 2, 'Concepcion District', 0, 2, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(18, 'Magsaysay Health Center', 'Barangay Health Center', 10, 2, 'Concepcion District', 0, 2, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(19, 'New Pangasinan Health Center', 'Barangay Health Center', 14, 2, 'Concepcion District', 0, 2, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(20, 'Esperanza Health Center', 'Barangay Health Center', 7, 2, 'Concepcion District', 0, 2, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(21, 'San Roque Health Center', 'Barangay Health Center', 18, 2, 'Concepcion District', 0, 2, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(22, 'GPS Health Center', 'Barangay Health Center', 8, 3, 'GPS District', 0, 3, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(23, 'Zone I Health Center', 'Barangay Health Center', 24, 3, 'GPS District', 0, 3, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(24, 'Namnama Health Center', 'Barangay Health Center', 13, 3, 'GPS District', 0, 3, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(25, 'Avanceña Health Center', 'Barangay Health Center', 2, 3, 'GPS District', 0, 3, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(26, 'Mabini Health Center', 'Barangay Health Center', 9, 3, 'GPS District', 0, 3, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(27, 'San Jose Health Center', 'Barangay Health Center', 19, 3, 'GPS District', 0, 3, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(28, 'Caloocan Health Center', 'Barangay Health Center', 4, 3, 'GPS District', 0, 3, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(29, 'Rotonda Health Center', 'Barangay Health Center', 16, 3, 'GPS District', 0, 3, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31'),
(30, 'Morales Health Center', 'Barangay Health Center', 12, 3, 'GPS District', 0, 3, 'active', '2025-09-21 04:06:30', '2025-09-25 05:34:31');

-- --------------------------------------------------------

--
-- Table structure for table `facility_services`
--

CREATE TABLE `facility_services` (
  `facility_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `facility_services`
--

INSERT INTO `facility_services` (`facility_id`, `service_id`) VALUES
(1, 1),
(1, 2),
(1, 3),
(1, 4),
(1, 5),
(1, 6),
(1, 7),
(1, 8),
(1, 9),
(2, 1),
(3, 1),
(4, 1),
(5, 1),
(6, 1),
(7, 1),
(8, 1),
(9, 1),
(10, 1),
(11, 1),
(12, 1),
(13, 1),
(14, 1),
(15, 1),
(16, 1),
(17, 1),
(18, 1),
(19, 1),
(20, 1),
(21, 1),
(22, 1),
(23, 1),
(24, 1),
(25, 1),
(26, 1),
(27, 1),
(28, 1),
(29, 1),
(30, 1);

-- --------------------------------------------------------

--
-- Table structure for table `family_history`
--

CREATE TABLE `family_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `family_member` varchar(50) DEFAULT NULL,
  `condition` varchar(100) DEFAULT NULL,
  `age_diagnosed` int(11) DEFAULT NULL,
  `current_status` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `family_history`
--

INSERT INTO `family_history` (`id`, `patient_id`, `family_member`, `condition`, `age_diagnosed`, `current_status`) VALUES
(2, 7, 'Mother', 'Depression', 35, 'Living'),
(3, 7, 'Cousin', 'Diabetes Mellitus', 46, 'Deceased'),
(4, 36, 'Cousin', 'highblood', 20, 'Living');

-- --------------------------------------------------------

--
-- Table structure for table `feedback_answers`
--

CREATE TABLE `feedback_answers` (
  `answer_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED DEFAULT NULL,
  `service_id` int(10) UNSIGNED DEFAULT NULL,
  `facility_id` int(10) UNSIGNED DEFAULT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `choice_id` int(10) UNSIGNED DEFAULT NULL,
  `answer_text` text DEFAULT NULL,
  `answered_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_questions`
--

CREATE TABLE `feedback_questions` (
  `question_id` int(10) UNSIGNED NOT NULL,
  `question_text` varchar(255) NOT NULL,
  `question_type` enum('text','rating','choice','yesno') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_question_choices`
--

CREATE TABLE `feedback_question_choices` (
  `choice_id` int(10) UNSIGNED NOT NULL,
  `question_id` int(10) UNSIGNED NOT NULL,
  `choice_text` varchar(100) NOT NULL,
  `choice_value` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `immunizations`
--

CREATE TABLE `immunizations` (
  `id` int(11) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `vaccine` varchar(100) NOT NULL,
  `year_received` year(4) DEFAULT NULL,
  `doses_completed` int(11) DEFAULT 0,
  `status` enum('Complete','Incomplete','Pending','Unknown') DEFAULT 'Unknown',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `immunizations`
--

INSERT INTO `immunizations` (`id`, `patient_id`, `vaccine`, `year_received`, `doses_completed`, `status`, `created_at`) VALUES
(2, 7, 'Not Applicable', '2025', 0, '', '2025-09-25 04:55:08'),
(3, 36, 'Pneumococcal', '2002', 211545, 'Complete', '2025-09-28 15:17:38');

-- --------------------------------------------------------

--
-- Table structure for table `lab_orders`
--

CREATE TABLE `lab_orders` (
  `lab_order_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `consultation_id` int(10) UNSIGNED DEFAULT NULL,
  `visit_id` int(10) UNSIGNED DEFAULT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `ordered_by_employee_id` int(10) UNSIGNED DEFAULT NULL,
  `order_date` datetime NOT NULL DEFAULT current_timestamp(),
  `test_type` varchar(128) NOT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `result` text DEFAULT NULL,
  `result_date` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lifestyle_information`
--

CREATE TABLE `lifestyle_information` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `smoking_status` varchar(50) DEFAULT NULL,
  `alcohol_intake` varchar(100) DEFAULT NULL,
  `physical_act` varchar(100) DEFAULT NULL,
  `diet_habit` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lifestyle_information`
--

INSERT INTO `lifestyle_information` (`id`, `patient_id`, `smoking_status`, `alcohol_intake`, `physical_act`, `diet_habit`) VALUES
(1, 7, 'Never', 'Rarely (few times a year)', 'Light activity (1-2 times a week)', 'Poor (mostly fast food/processed)'),
(2, 36, 'Never', 'Rarely (few times a year)', 'Sedentary (no regular exercise)', 'Fair (some healthy choices)'),
(3, 38, 'Never', 'Rarely (few times a year)', 'Light activity (1-2 times a week)', 'Fair (some healthy choices)');

-- --------------------------------------------------------

--
-- Table structure for table `past_medical_conditions`
--

CREATE TABLE `past_medical_conditions` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `condition` varchar(100) DEFAULT NULL,
  `year_diagnosed` year(4) DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `past_medical_conditions`
--

INSERT INTO `past_medical_conditions` (`id`, `patient_id`, `condition`, `year_diagnosed`, `status`) VALUES
(2, 7, 'Not Applicable', '2025', 'N/A');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(10) UNSIGNED NOT NULL,
  `username` varchar(10) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `suffix` varchar(15) DEFAULT NULL,
  `date_of_birth` date NOT NULL,
  `sex` enum('Male','Female','Other') NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `isPWD` tinyint(1) DEFAULT 0,
  `pwd_id_number` varchar(30) DEFAULT NULL,
  `isPhilHealth` tinyint(1) DEFAULT 0,
  `philhealth_type` enum('Indigent','Sponsored','Lifetime Member','Senior Citizen','PWD','Employed Private','Employed Government','Individual Paying','OFW') DEFAULT NULL,
  `philhealth_id_number` varchar(30) DEFAULT NULL,
  `isSenior` tinyint(1) DEFAULT 0,
  `senior_citizen_id` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`patient_id`, `username`, `first_name`, `middle_name`, `last_name`, `suffix`, `date_of_birth`, `sex`, `contact_number`, `email`, `barangay_id`, `isPWD`, `pwd_id_number`, `isPhilHealth`, `philhealth_type`, `philhealth_id_number`, `isSenior`, `senior_citizen_id`, `password_hash`, `status`, `created_at`, `updated_at`) VALUES
(7, 'P000007', 'David', 'Animo', 'Diaz', '', '2001-04-10', 'Male', '9451849538', 'daveynmdz.sti@gmail.com', 25, 0, NULL, 1, 'Indigent', '17-025789911-2', 0, NULL, '$2y$10$6UaPkkHa69NsEDYMHnnlBes3OWxJgHsBKnML5JXM1u2Zr7bvDcj3C', 'active', '2025-09-21 09:42:03', '2025-09-26 11:08:24'),
(8, 'P000028', 'Juan', 'Santos', 'Cruz', NULL, '1980-02-14', 'Male', '09123456781', 'patient1@example.com', 1, 1, '12-7401-001-0000001', 1, 'Indigent', '31-123456789-4', 0, NULL, '$2y$10$6UaPkkHa69NsEDYMHnnlBes3OWxJgHsBKnML5JXM1u2Zr7bvDcj3C', 'active', '2025-09-22 07:07:54', '2025-09-29 22:32:14'),
(9, 'P000029', 'Maria', 'Lopez', 'Reyes', NULL, '1995-06-28', 'Female', '09123456782', 'patient2@example.com', 2, 0, NULL, 1, 'Indigent', '72-987654321-7', 0, NULL, '$2y$10$6UaPkkHa69NsEDYMHnnlBes3OWxJgHsBKnML5JXM1u2Zr7bvDcj3C', 'active', '2025-09-22 07:07:54', '2025-09-29 22:32:38'),
(10, 'P000030', 'Pedro', 'Dela', 'Rosa', NULL, '1962-11-30', 'Male', '09123456783', 'patient3@example.com', 3, 0, NULL, 1, 'Indigent', '65-192837465-3', 1, 'SC-4389', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(11, 'P000031', 'Ana', 'Mateo', 'Lopez', NULL, '1988-07-21', 'Female', '09123456784', 'patient4@example.com', 4, 0, NULL, 0, NULL, NULL, 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(12, 'P000032', 'Lito', 'Garcia', 'Torres', NULL, '1955-09-12', 'Male', '09123456785', 'patient5@example.com', 5, 1, '12-7401-005-0000002', 1, 'Indigent', '54-564738291-1', 1, 'SC-9812', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(13, 'P000033', 'Grace', 'Nicolas', 'Lim', NULL, '2000-03-10', 'Female', '09123456786', 'patient6@example.com', 6, 0, NULL, 1, 'Indigent', '48-837465192-9', 0, NULL, '$2y$10$6UaPkkHa69NsEDYMHnnlBes3OWxJgHsBKnML5JXM1u2Zr7bvDcj3C', 'active', '2025-09-22 07:07:54', '2025-10-02 08:11:34'),
(14, 'P000034', 'Noel', 'Ignacio', 'Tan', NULL, '1949-01-15', 'Male', '09123456787', 'patient7@example.com', 7, 0, NULL, 0, NULL, NULL, 1, 'SC-56234', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(15, 'P000008', 'Carmen', 'Lee', 'Chua', NULL, '1992-08-18', 'Female', '09123456788', 'patient8@example.com', 8, 0, NULL, 1, 'Indigent', '11-918273645-0', 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(16, 'P000009', 'Rico', 'Salazar', 'Mendoza', NULL, '1982-04-05', 'Male', '09123456789', 'patient9@example.com', 9, 1, '12-7401-009-0000003', 0, NULL, NULL, 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(17, 'P000010', 'Ella', 'Morales', 'Reyes', NULL, '1975-05-14', 'Female', '09123456790', 'patient10@example.com', 10, 0, NULL, 1, 'Indigent', '28-564738291-6', 1, 'SC-27455', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(18, 'P000011', 'Mark', 'Santos', 'Villanueva', NULL, '1989-11-22', 'Male', '09123456791', 'patient11@example.com', 11, 1, '12-7401-011-0000004', 1, 'Indigent', '39-837465918-2', 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(19, 'P000012', 'Lea', 'Fernandez', 'Castro', NULL, '1960-02-02', 'Female', '09123456792', 'patient12@example.com', 12, 0, NULL, 0, NULL, NULL, 1, 'SC-10456', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(20, 'P000013', 'Alex', 'Domingo', 'Ramos', NULL, '2001-09-19', 'Male', '09123456793', 'patient13@example.com', 13, 0, NULL, 1, 'Indigent', '73-102938475-5', 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(21, 'P000014', 'Jenny', 'Gutierrez', 'Lopez', NULL, '1958-03-03', 'Female', '09123456794', 'patient14@example.com', 14, 1, '12-7401-014-0000005', 1, 'Indigent', '15-293847561-8', 1, 'SC-8811', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(22, 'P000015', 'Oscar', 'Reyes', 'Garcia', NULL, '1998-12-12', 'Male', '09123456795', 'patient15@example.com', 15, 0, NULL, 0, NULL, NULL, 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(23, 'P000016', 'Mila', 'Aquino', 'Santos', NULL, '1965-06-07', 'Female', '09123456796', 'patient16@example.com', 16, 0, NULL, 1, 'Indigent', '44-564738291-9', 1, 'SC-7321', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(24, 'P000017', 'Ronald', 'Mendoza', 'Torres', NULL, '1979-07-25', 'Male', '09123456797', 'patient17@example.com', 17, 1, '12-7401-017-0000006', 1, 'Indigent', '12-847362514-2', 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(25, 'P000018', 'Sofia', 'Cruz', 'Rivera', NULL, '2003-11-01', 'Female', '09123456798', 'patient18@example.com', 18, 0, NULL, 0, NULL, NULL, 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(26, 'P000019', 'Diego', 'Lopez', 'Castillo', NULL, '1952-04-17', 'Male', '09123456799', 'patient19@example.com', 19, 1, '12-7401-019-0000007', 0, NULL, NULL, 1, 'SC-5072', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(27, 'P000020', 'Angela', 'Perez', 'Domingo', NULL, '1990-10-09', 'Female', '09123456800', 'patient20@example.com', 20, 0, NULL, 1, 'Indigent', '96-564738291-7', 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(28, 'P000021', 'Victor', 'Salvador', 'Marquez', NULL, '1968-08-23', 'Male', '09123456801', 'patient21@example.com', 21, 1, '12-7401-021-0000008', 1, 'Indigent', '22-918273645-6', 1, 'SC-32544', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(29, 'P000022', 'Bea', 'Serrano', 'Delos Reyes', NULL, '1984-02-28', 'Female', '09123456802', 'patient22@example.com', 22, 0, NULL, 0, NULL, NULL, 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(30, 'P000023', 'Carlos', 'Valdez', 'Mora', NULL, '1972-12-14', 'Male', '09123456803', 'patient23@example.com', 23, 0, NULL, 1, 'Indigent', '77-837465192-3', 1, 'SC-16789', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(31, 'P000024', 'Elena', 'Jimenez', 'Navarro', NULL, '2005-09-05', 'Female', '09123456804', 'patient24@example.com', 24, 0, NULL, 0, NULL, NULL, 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(32, 'P000025', 'Miguel', 'Ramos', 'Aguilar', NULL, '1957-03-30', 'Male', '09123456805', 'patient25@example.com', 25, 1, '12-7401-025-0000009', 0, NULL, NULL, 1, 'SC-9087', '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(33, 'P000026', 'Nora', 'Bautista', 'Villarin', NULL, '1986-01-19', 'Female', '09123456806', 'patient26@example.com', 26, 0, NULL, 1, 'Indigent', '14-475869132-4', 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(34, 'P000027', 'Ethan', 'Reyes', 'Dela Cruz', NULL, '1999-10-22', 'Male', '09123456807', 'patient27@example.com', 27, 1, '12-7401-027-0000010', 1, 'Indigent', '66-918273645-5', 0, NULL, '$2y$10$8f1A3Gzj0XJDwlQGBDKUv.8vghw4To4AS8SErP7HE49p.buovkWFi', 'active', '2025-09-22 07:07:54', '2025-09-22 07:07:54'),
(35, 'P000035', 'David', '', 'Dela Cruz', '', '2005-03-09', 'Female', '9547583525', 'kristalmaesulit696@gmail.com', 5, 0, '', 0, '', '', 0, '', '$2y$10$eQkxoiPfcaUjIU6xQ0dk5OWtYqMhPVnbqU2bAsBcu68wb7ucnZpgK', 'active', '2025-09-24 12:11:49', '2025-09-24 12:11:49'),
(36, 'P000036', 'Princess Kyla', 'Ellama', 'Cabaya', '', '2003-11-25', 'Female', '9603446003', 'princesskyla1432@gmail.com', 26, 1, '1026053', 0, NULL, NULL, 0, NULL, '$2y$10$Qbyk7LFKA1.YhOkI.N8vX.lZZTM0OBmVksbDHD9S2wSAQcHdZ5ddu', 'active', '2025-09-28 14:35:15', '2025-09-28 15:19:19'),
(37, 'P000037', 'Christian James', 'Guerrero', 'Paclarin', '', '1998-12-06', 'Male', '9256565655', 'daveynmdz.sti@gmail.com', 8, 0, '', 1, '', '122554878955', 0, '', '$2y$10$zA/dScfsQyrgE9o3bTcTZebJRgyjxA0TLOzbCPk7ZtFRREexH.AD6', 'active', '2025-09-30 08:31:40', '2025-09-30 08:33:15'),
(38, 'P000038', 'Angela Dianne', 'Tapit', 'Divina', '', '2000-04-10', 'Female', '9459611897', 'angelianne101@gmail.com', 25, 0, NULL, 1, 'Individual Paying', '02-026924059-9', 0, NULL, '$2y$10$Y7wItOXDGKGKJZ9i4uZo2OHX4dRlPVGRT/Rn9vM7Bz7mFe3TW47du', 'active', '2025-10-04 04:15:32', '2025-10-04 05:35:28');

-- --------------------------------------------------------

--
-- Table structure for table `patient_flags`
--

CREATE TABLE `patient_flags` (
  `flag_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED DEFAULT NULL,
  `flag_type` enum('false_senior','false_philhealth','false_pwd','false_patient_booked','other') NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by_type` enum('employee','admin','system') NOT NULL DEFAULT 'employee',
  `created_by_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by_type` enum('employee','admin','system') DEFAULT NULL,
  `resolved_by_id` int(10) UNSIGNED DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `personal_information`
--

CREATE TABLE `personal_information` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `blood_type` varchar(5) DEFAULT NULL,
  `civil_status` varchar(20) DEFAULT NULL,
  `religion` varchar(50) DEFAULT NULL,
  `occupation` varchar(50) DEFAULT NULL,
  `street` varchar(100) DEFAULT NULL,
  `profile_photo` longblob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `personal_information`
--

INSERT INTO `personal_information` (`id`, `patient_id`, `blood_type`, `civil_status`, `religion`, `occupation`, `street`, `profile_photo`) VALUES
(1, 7, 'B+', 'Married', 'Roman Catholic', 'IT', 'Depita St.', 0x89504e470d0a1a0a0000000d494844520000019000000190080600000080bf36cc00001000494441547801ecfdf7b36d5b76df877dc7982bec70f28d2f76061b0029c812cab6644ab2fdcfb8fc9bfd83cb552e977fb0cba572924b94688a1269913449802041100001a29bdde80034bb1baff3eb97f3bbf9de93cfd979afb5fc19739ffbba490a24d0ecf4eed9ebacb1e69c638e99c6dae7fb9de104efd6d7da036b0fac3db0f6c0da033f82075ceb6bed81b507d61e587b60ed811fc1036b02f9119cb62eb2f6c08fc503eb4ad61ef8907b604d201ff217b8eefeda036b0fac3df0b3f2c09a407e569e5fb7bbf6c0da036b0f7cc83df02126900fb9e7d7dd5f7b60ed81b5073ee41e5813c887fc05aebbbff6c0da036b0ffcac3cb026909f95e7d7edae3df021f6c0baeb6b0f8407d604125e58cbda036b0fac3db0f6c09fdb036b02f973bb6c5d60ed81b507d61e587b203cb02690f0c24f5bd6edad3db0f6c0da034f8007d604f204bcc4f510d61e587b60ed819f8507d604f2b3f0fabacdb507d61ef8597960ddee8fd1036b02f9313a735dd5da036b0fac3d70993cb02690cbf4b6d7635d7b60ed81b5077e8c1e5813c88fd19997a1aaf518d71e587b60ed81c71e5813c8634facc3b507d61e587b60ed813f9707d604f2e772d7da78ed81b507d61ef85979e0e7afdd3581fcfcbd93758fd61e587b60ed810f8507d604f2a1784deb4eae3db0f6c0da033f7f1e5813c8cfdf3b59f7e827e38175ad6b0fac3df063f6c09a407ecc0e5d57b7f6c0da036b0f5c160fac09e4b2bce9f538d71e587b60ed811fb307fecc04f2636e775dddda036b0fac3db0f6c087dc036b02f990bfc075f7d71e587b60ed819f9507d604f2b3f2fcbaddb507fecc1e581bae3df0f3e9813581fc7cbe9775af3e841ee8baee43d8eb7597d71ef8d13db026901fdd77eb924f800702f443fe5d87f2e3a8e3dfb50febf26b0ffcb43d701908e4a7edd3757b3f671e08706fdb566ddba8691a2d97734d27673a3f3bd0d9e9238d47c79a8c4e34199fe4f8f8fc4863e2b3d958f390f954cbc59cb2cb5c47d7b5ea586dfcb0c490cd2c82b5ac3d70693cb026904bf3aa9ffc8106a0c728230c09b298cf2699281edc7d4d6fbdf6357dff1bff4c5ff9ccdfd61ffed67fadcfffe3bfa22ffcd67fa52fffee5fd3977ef7bf46feaa5ef8dcdfd10b9fffffe985cffe2d7deb0fffae5efcea6fea956ffc8ede7dfd2bbaf3eeb7f5e8de1b3a3fdd876c4e359d8eb458cc32b1447b8f25fab096b5072e8307d6047219def22518e363f08ed5c50c603f78744bdffedaefe88bbff737f4f7feeaff5effedfff57fadbff99fff6ff4ebffe5ff499ffbf5bfaeaffdc16fe99b9fff7dbdf0b9dfd3d73ff3db10c6efea3b5ff87d7de373c43ff38ff4f2d73eaf97bffa597df70bbfa56f7df6efe9abbff3dfe86bbffd57f58dcffcb710cbdfd477fff8efea95177e4befbef465ed434e878fded5e86c1f5239ffd748e512b8ff4f1fe23ae789f6c09a409ee8d7fbe40f2e8863c1f6d2c9f103bdf9cad7f5857ffadfe9effed7ff3bfdb5fff3ff4abff9d7ff9ffaec3ffa35bdfc8defeae1dd038d4f469a4d269a231d2b876eb990b552bb68353e1f6b7c3ad2f4fc5c8bf95c278f1e69fffe1d1d3eb8a79383431dddbbaffbefbeabf75efabe5efdfa1fe995af7c562f7ee11febdb9ffb7bfa1ae4f28ddffb6bfa93dfff2b7af18ffeaedefcde67f5e0d64b3a3dba9fb7ca96f42fb6cfa2af4ffe1b598ff03279604d2097e96d7f88c6fa6f02dbc88b338d39671387fbb7f522db52fffc1ffd15fdee7fff9feb4bbff5b7f5da37bea6b387f70553a8d45cdb5b95b60649db9bb57a5529f3a4650373709651f74cdbbb958683426519671b4bb6a43a8d26339d9f4f349b2f345f2c350bc2f156cb760e311ce9108239dc3fd2fdbb7775fbdd37f5d68bdfd41bdffc177ae305562e7ffcdbfa4e6c7ffdd13fd09bdff98ceebfff3d48e8165b5f0ff30aa56996f90ce543f43ad65d5d7be07fd0036b02f91f74cbcf8bf272f62308c2ccf2e023de7200fe385cb26a188d4ef5e0de5bfae61fff437de637fe0b7df9b7ffa65efcdae7b47fef8e66e319e53ac5d77c1964d070403ed17cdeaae9a4c9a2d3e9b4d5c39399ee1d2d74fbe1446fbc73a03bfb631d9e2e359e401ee3a966b3b9a6b3a58e8e26da3f38d7fea3b11e3d38d1c1e1a9cec6935cd70262994fe73a7874a887fba73a381a6bffc1431ddc7d57efbff24dbdf6279fd7b7fef9afe985dffe6ff4ad7ff637f5f21ffdbadee43ce5ce5b7fa253564c73ce671a0ef63b888c4eafefb5073e741e5813c887ee953dd91dee2e7ebaa9691a4d26e73a397ea8c3fd3bba73eb35bdf9ead7f5a53ff85bfac3dff9ffe8f77fedffae7ff6f7ff9abefd475fd21b2fbfa3dbef1f02e0679a4114b3590b01b46c4549b369a366bec4699d661082b36755a54e7559683c5ee8e070ae93d3b9cece1610c8420f8e277a7830d30892693b8a71cf21132f4c79e5b268b45c763a3a1e69345b6842de12666a3aa7aeb1eedd3fd58387271a8dc69a8e8e351b9db10d764befbffc27bafdea9fe8ddef7d41af427c2f7de96febcd6ffd533d64ab6b747e4c9d8bf5aa045fafef0f9707d604f2e17a5f4f746f63a511b3f2a3837b7aefcdefe82b1c5effdeafffbff5eb7ffdffa8bff55ffc6ff577ffcbff03a4f1d7f5cf7ff31fe84fbef055bdfbcea1debf7da2fb8fce743e6e593188ada785c6a38526d3a526007c803b0b18cd678de690c97cd6a94a6c670d4b3d7363a8a7af0eb539a8d5af930a77cda60b5618731d9dceb47f38d1c9f95c999058d9cc391b31de809ba92cf8d681e4cc3aa5646a5945d4834af5c640a9ec693a779de77eccb520af73d3e8e444c70feeebf8e11dedbfff866ebdf865bdf9b57fa277bef3073abcf786e2c7889b8bedad20529a5adf3f430fac9bfeb77b80ef827fbbd1da62ed819f840702241f4b00e7dddbafe9ab5ffe87fae23ffb5bfaadfffeffa62ffef6dfd3b7befc19bdfa9d6fead19dfb7a74ff918ed9523a3c3cd7e9f9144298ab63991058de2b5d854ca905dc53a1ca0bf552a93ef1829c41596b17701f941587e61d62aac9db1cd6daead772ca2d201df8400e41cc972d2400e8b3a535994b8bc6d5b2d2989208429962bb5c2cd0cdd5ef17dae49c85aa3587b48e58c54ce79dac704dd9ee3adb3fe6cce48055ce994e4fce7472b8afe3fdbbba7ffb2ddd79fb45bdfbed2fe8ed177e57efbdf8791ddc7f03023ce61c66a9c7bed1fa5a7be0e7d403fe73daaf75b79e600f0430c66aa36366be5cce757870575ffdd23fd23ffc1bff17ce34fe3bb6a8fe915e7fe5553de2903a66f167e70bb695ce757f7fa44747234d00f524d7a0576a67d8d32600be3d204efada564fcf5edfd2b337b775e3ca50bbbb433dfffc753df3f455edeef4f534faa7af6fe8c6b58136aa42057de895941df675756ba0214433243dac6bd51cb8471fe7ac5e4693462c4e5490ef10cc94edaf73562993f346a3b3b9c667334de86772a35fae2097870f4e35a6ec7cd940349d1a0867349de9e864ac0961db2e747e72c8f9ca5ddd7bf71556225fd0f73efb37f5edcfff7ff5fe1b2fe8fcfc84adad1591687dad3df073e801ff39ecd3ba4b4f8207fed4317480e242e76747ba7ff71d7debeb7fa0dffa3bff0ffdf6dff9abfaee0bdfd4ad771f6a743a51cb0aa06355b05c76d8339b6f256f4ca9735526560fa65e69dae84937b66ac0bfd4f56b3d5dbfded3d68e6b73dbb57db5af9dab3db695a4a22f357c2d9b195b4e491d87e9755d40241b7ae6e6866e4228cfdddcd22f7c6c4f9f7c6e4fcf5dd9d4562f695814da6695228ee5c79c8b2c3b532a9276b607dade1a2a996bc9d90afb54ea4338e767d3bcf535992c244f9c85cc7436597270bfd4c968ae22b90ace53661cbe9f4140b339bd5a4c75f0f07696fbefbdae7b9cf5bcf3cddfd3bbdfffbceebef73d1d1fded77c3ed58a743badafb5077e5e3ce03f2f1d59f7e3c9f540c7c178805ffcbec6f9d9895efdfed7f4e5cffcba7ef7d7ff8a7eefd7fe1bbdfccd3f01248f2508a32e4b5575a9c4f64fd72dd57126008eeb1a24f13c2b874f3cbd09c06febe6d581f6d0edecd61ab0b2f041ad39fb4f6366f513ca8c66739d31cb7fedad3bfadef7dfd3eb6fded5dd7bc7baf7e8548f38001f03dc676c839d8da66ae85f49bb1b9bac4a208bdded9eaeedf6f591eb3bfae8cd2bbabad1d7f5cda10665ad93934607670dab914e2504b4736553838d819297d252dae22c65b357721ed3e96cdca8699dad2829c8f09cf394079cd79c9c4ed5b02aa920922507fc276c79cde9ef6474aad178c4e1fe48fb77ded0eb5ffd277aed2bbfa1ef7ee91fe89d57beaae3833b6c914d209246eb6bed819f070ff8cf4327d67d78723d10e4319b4df4f03e20fef29fe89ffec65f63abeaffa52ffeee6fe8e517beae07b7eee6dfab48b82041000d5b5acd7ca61e33feedb2d09538ecbeb2a1a7208c9bac30f6762b5617b55255e87cd9e9dee154b71e8df4de8313bd7bef4cb71f4e75efc1548f0e263a3c3c538fd5c2807a2a0ec8abaa525d542a8877d49f8a5296126465dadcdd62c52019792d795bbb03ed6cf7f5cc53dbfae87357f5fc337bfad8d35b7afefa5037f776d57615db539c918c972acaa40decbd2c55f7fada6175720db1a567228995cb942db05414ea0f1899b9f68fc63a3c1eab28babc55369db4ac32966c73359044cb36dd84b39053dd7fe765edbffb0d7df3b37f475ff8cdff0a9ffd8ece4e1eb22a5b2a7cabf5b5f6c0bfee819f9ac67f6a2dad1bba341e780c6c4dd3e8d183dbfafa1ffd9ebef0cf5871fcda5fd3d7bef859c8e49e4e4e4f7474740258cee4006a6a1bf500efed5ea55d561383da394b481af46b756cfb9cb362383a9fb115d4e9c1e95877297b4cbaf34ab38531d3af64a956d3153a1acdb47f3ad75d48e4bd07673a1c4d74ce4cff783cd5b469c5a240d5c0d4670553f60b4d97adc69391ac901a563e130c4ec7332d8ba57cd0a91c9a869bb5ae5fdfd4c79fdfd3b34f6deaa3cf5fd3dece86caaae63c439a36d22e64b3756d479b90d1956b9bfac473bbf93ca60fa97014c2eaa2654b6bceeaa39359cae51eeccf34675bac633532655baba85c8c46e78cf1f8e88cf1349caf1cab991ceaf8ceebfae6e77f4b2ffcf3bfaf5b6f7e43e767076a9ac59a482ecd77d6cfdf40fde7af4beb1e3d091e68208407f7ded7973ff73bfafe37bfa697bef5423e18bfffe0911eee1feb9883e4f902306f2503d4cb0448f74bf501f49a6da0a24c5a762dd264103de79ce16cdee9dec148b71f8c74ff70b95a7d3c3c634b69417aa4b76f1fe9f5f70ef5fedd73bdc76ae4f068c14cbee330ba55fc5ec79cf211ee33fbdf3f9ce814028a6da5f826984c669acda6726f1129b6b6ce209ee3d3334d591179d969329f6bd1ced4df70edb012dae3907e0b62d9e6e0bec74a67cc9906e8af581df53990afd16f6cd47a1ae2b979654bc33eab8fb694ab52c96aa46415d576856ed1effdc3b1164be99c36a7f3317da03d0eea1fde3bd1c9d191e6d3910a6f38fb59e8ceebdfd4d77effd7f49d2ffe633dbafb9616f15bf25da7f5b5f6c04fdb03f1bdf3d36e73ddde13ec81587d841c3cbaaf97befb4dddbef5b6eedd794ff7eeddd1d1f1b9f60fce7406702fe6ad16d32587d9ad9231e76e004c48623c5de87432d709e705076773dd7a38d6eb774ef5ea3bc77ae5ad23bd7b6fa4bbfb133d82004ece177ac016d6ed87e7a4c78ab2f345a316ff5675a52cb2fe600000100049444154940a356dd28283f76527cd01ed07e723edb3ba78c439c4f18832b3b14ece4ed54178711e311f4d94387b71564327c7333d8ab64ee610d7b1c610cc08129952260eb51bce5baa9ea9eabbca9a150de172b960b533630c339dcf5a8de84f0931de7cfa8a9ebabeab5d0ede178d438c850ab6d636372beded6e4202d2e9f9520707f3bc4a99d2cea25d6a0cab1c1c2fd04d349f8c353d3b5395165a4e1eeab56f7d595ffdfdbfab775ff99ae2f767c2ef5a5f6b0ffc143de03fc5b6d64d5d020fb46dab0907c16fbefe8aeedd7d57b7de7a450feeddd1fec343cd218de5a253c56cbd00e6e3c770e5a6939869b3ddf4e878aa93f8e5bb99f410f0befd68aa77ee8e74e75e80fc42735860c236cf64b2544cb82780f364d932736f95bcd460305002942bb6a1e6d89d8e276291a35388e9ced154afbe7da05bf766c89c7a277ae9cd63bdfeee99debc75a2d7de7b940fd8cfa763ce37161a54d236e7155bfd0d35b34247072daba605e4d7ea11aba029b3fea5753a66abe90c425a0a825a4cd52db03938d7edbb8f74343ed78cedb0878f4e287ba2adab95ae3fbda3e79edd5359d49acd4d62fbaacf76dd555628ad79eeeffb77c7da3f9c2908d158d20411dd7d30a76dc603799d1f1dcada31c47ba6fbefbeac3ffa277f53af7efbb39ab14a5993082f627dffd43cb026909f9aab2f4743cbe5528f1e3dd4fdbbefebbb2ffc31e47157076c594d988dcfe78dfa6c4df592b31dc3ca0002587036c10e968aaacce03f6f4da7ac4c8e208923c8a49183b149e6d8b792088bb2847ef8e81a7a54652ab09162cb6904199d71600d6768d91880dc29457e2388abaf4eae29ac32e2d0e270b4d46d66fc6fde9febb5bb337dffbd73bdf60ea0fc68ac3817e96f94dadc487af6a9a13efaec15ed6c6e5247adf169a33bef9feb3edb4b9d4905e717e37103698c740e990c38c7d82c0acd5921cda7538873a1d3b331e74147ac545a6d6d15fae8f3bbf9c780e74b4863699041a71adf2c21c4cd610d09373a61e5717c3653834f8364df7aef9455ca5c0b5642e7c7476ae713d18aa6a787fac61ffcbabef347bfa9f3d303c8b5d5fa5a7be0a7e101ffb137b2aef0d27a20561fe767273a3a7ca0f7dffcae1edd7b576767e79a02d6f3c5526275321c9400aecb592518ab0fa6e2eadc3507daf74f667a7030d6fdd836626b6accc973ac3a16b1bd05f94c911900bb00b59788434401b6655d6a8ecd92adaad9b263eb68a91165976dc71656c716d554f1a3ba7140dd51a6a5add60c1232355ea86535d0a44aa32ee960dae92ec07dc016d7923398d63bcd9733d5fd567bd786dadb1bea999b3bbac97654a99e0e1ece749b338c663a81004a1d1ece349dcd7565bbaf9d7ed2f1c9440f3873391b4d75723ad201ab91e962ce16d64c5776cb4c50e19a1867e2939318975a536f58a8eeb9e237efcf21a28e3e1fc696debd739d9c4f716507b1344abe54991a8d4f8ef4e297ffa9bef7c7bf997f4a6bbd12c199ebfb27ee01ff89b7b06ee089f64000d56399b36f7f78f0486f7cff05bdf1d23734823cce98414f596588997f8234a6ac4426b305a0dc6a4c78c22ae0f06ca1e3b346f0833a2b346205b20c2035cfc00f0f68c19ed512093299b2bc08f258b0929852a8aa6bb5d8cfe61d1b3e86bf4d4bce34c46a653a5f102466e592515ecd42bd3a6963a3a7e1569f1581ab6de69a4f671a8da7da3f9de8cea333dde2a0fece8320bf3975b76aa8ef7c74a62094dd6b5bba716353bff0a9ab10c99eba65c5aa6ba6e5b2518f83f26308e8e4f45c5565dad91ce8e8aca5be891eee4f39109f68fffe21ab88252b9933ed0e4b7decf91d0ed8ebfc23bebb7b5b52278868aa069f5dbb5253ef927149435644c79c93ec43b093c994558bb4847d8ab2856ca4c57ca4575ff89c5efc4a90c82305a16b7dad3df013f4c09a407e82cebd2c5507812c974bb6aaeeebadd75fd4f75ff8431d3d3a60c6bdd08c95c0946d1c03159700fe8c3382d8b23a1dcd75386a74c4ecfa8cedaad3d14cf34507f00be08404b8e104b58069d41fbe6c218016252ace0f0055562e4b56240747275a46a59469318cd58600df05ed8d21953936338806fed28dabdb4a9cc19c9f8f757278aa0984d1b29566ac362a5644fda252bf3f90d77d8dda42f18355e3c99ceda4112b8c42f1274b6215316715319e9ceb231fd9d0bfff2bcf412857d5b5952690656f502b7eafc3e9dff666a18fdedc666ca6c3b3a5eeed8f74c0ea2a7e4477469f97f41776d295ada186942b209de146a52b3b03edb3bd76f760a9ab573725566f7555aa0fe19c4da483a3392b9d99c40a6981efdd5b56238d4e0ff6f5d2573fa757bff14f75b4ff9ee2503f9a58cbda033f090ff84fa2d2759d97c30301ec2131d39db285f3f0fe1dbdf9fdafebf0e17d2d01ee00f5b6ed983d9b04f8779048ac1c52ecf5771005ba05e03d0374630b0a646755b2044fb124bf0560c175514514cff5985117ee0d5d0ba80aada373eb0050539007d50a95164d438b6285d1294c8fce673a3839932017f7a42215d899a27c2fc099adb0c47946638dce3824bf7d7ca6371e1cea9dfd31e5e63a3d39d7e6a6abaa0b9d420247fb73407ea4a26cf40bbf70459ffe85a77575f78a9ac655f76bc537d7c6469f2daf4dfda54f3f45b99e66cb02d25cea9043fd31e735e7e3b99a94e4a5ab57f5b43118aacfe17dfc88f0273eb647df8dc3fd8976767ba26b9cc1544ad89e8f5bdd679534e1cc65819f8e4f469acf674ab0e4d1febe5ef8ec3fd6b7bef0f7d9467c430bc84eeb6bed819f8007fc2750e7baca4be281c7e431626b673c3ae5dce365bdfbdaf77402488fd9860ad036770558976521709259f352e71c72775e00e0a50af4a0b85a5616555d71c0dd00c00661f80afc83413cd2383558c15a19d1f8e046187d9091a2bc99e56d2aacb1208e3e595294683a511f39ad6b3a9d2baa15f635643604eca39f23ce6ae2cfb8df7b34d2ed3ba77affd6a9de7e7fac376e8df5d2ad233d3a5d02dac74a9c396c6cd780fd4033565177ef9cebfd77efeae6b33dfdd22fdfd0f5eb7b5a2c933a4bf2d46973aba78f3ebba5e76eeeb0ed54aa530991244d66d21812393d9b4a90429f6db5f083c915e4b83174fda55fbea9026289ff51b2b3d357855d55552a38a49fce3a9d7256d32c3b48a2d5e87c221ca0129f1e3cdcd777fff88bfafed77e4747fbb7f069a3f5b5f6c08fdb03fee3aef0c35cdfbaef7f360f04682fd93699cfa77af4e0b6befd279fd357bff83b7af3951738343fd58859f5842da3006813402a632ba55551563266c823b684666c2f9d6117db5283415f25ec123fa21be90ec85fb64b81b48a2b5614f010b574e0a3a1eae46e880b0ec8761669b28a22a9f0d0435c6426da4be455d45f235551a84437ec95dadbec33eb2f00d7962db14601de9e0aca174038f558a1cecafc27471eb29df41ec472107f0bebf05ce2d87ff76aad6bd7b6153f82bb5896fafeb7df91da733df3f450cf3d775df28126607a5142186cd17df2237bf94fa2ec6c6d729e3164b550aba0af4db3d4c9f1482a1a3df7f11baa5885d4750f9fb9fa1ca43ffbf416c46a3a8668363707ead5a57639ccdf807016acf41a08c43a0869bcd418129973ee535605db6d67faee973fafeffdd1dfd7c1c3b719273ea5e7eb7bed811f9707fcc755d1ba9ecbe18105db218707f775e7d6ebfa175ff82dfdc13ffeebfafceffc7d7df6b77f5ddf79e16b3a3e38d16cba6086df09446626be5410c608ddfef15467e346e6097dab054b9411ab81f3f14465556ac6f9889969c50a068d50853a7579b910ea2005574af1b1ed30eb98911788ab4097dc552215805d95e801d10a42e95377ac32b6867d0d00dfcd5ead41afa2bc29fa12e45352a62ecbfc177503a0ebb2a4de820eb44a5ec853a94707538d01ec07476c1fed9f6b361b6b63a7a75eafd0f5eb9baa7bdbbaf3fe89dc17dada2a75edfab6126d8d20d3cd6bbbb4253dfbcc404fddd8d0e6a09427d31cf0afab24c975c856d9787ca6ab4f0d197ba3a2ec14fee995a6ad4191576e8e69c958bbb653c9f818042b8f19e55b6a70c59f9e6f20f738fb48d43f3d3fd7f7fef8cbfae63fff35eddf7b6b4d22786a7dfff83ce03fbeaad6353da91ee85802349c27c48ae3c1fd5bfaf2677f439ff9c7ff9dbefed97fc816c917f5d6abaf69ffee431def1f03aa0b2d63bf0867c46aa225ec649c4d8803f256d3f992ad9b06e05c0a6e801c4c7156711e53f54e58ea83ab83380c8db13de5e600b965295352ccdc13baf800f7eb4a1bfd1eb3f51a30af15e92104b1816c0e7a1c4e57da62b61e40bd39ec111f6ad0ef6b6338505e0df47ae8fadac66663506bd82b35ec238483bae0f03cb16a29244b3a3a9928d5b5ee1d2c743ffe8aee72a28ded4ddae8ebe967f754541b9908aa5a1a0e0b15496adc34c7271de4d602fc3b90cbcd9b435dd9dd00f42bed3f1ca9ae5d46fec37bc79a9e8fe4e1f359a3055b814b7c3fa41fed64a1f8675a15b61356710524699049b36c382be968bbd062d98a2311c1cd6abb46a97435cb85de7df95b7af12bff44070fde55db36faf9bbd63dfa307ac03f8c9d5ef7f9a7e78116245a2ee710c358dffdc617f5275ffa27fafe9f7c416ffcc99775ffad3775c4217301c83b6713256066267932a1521cee2e9861c30b9154ccf20d308d1f896d01d27ce04d8e5328212d6d1969aa51c127d3cd95dc5514498e326153908e03ef9021e41004d12f5dfdcab535ac75932da5bd9da1aeee6ce8cace662685abdb1b8abf8e7b8d70d8abb5c336d0ced650d7f776218e41fe85bead414fdb9bbd5cc7f6468d4d4f9b90cd46bf26ec2bda1ab0d536599a46e33163e9ebcec3891e1c9da9e84b3dc8a75f9bae5d1d72989d747c3c06a8471c88976cbbb58adf0d59b06dd7a7ce0642ec2088eb37867af6b95df5061bd439d77058e96c34671b6aa9940aca1904b2d039a41544b1491ba3d319db6233eaad35c296aa54f66b859feb4125b3a4095b83b3d95c7356741d24dc754b2d1733dd7afddbfade577f4ba7c707f42da85deb6bed817f270ff8bf53e975e127da031d2037199feba5effc0b7dfe77ffb6fee01ffe0d7dee9fb09f7efb1687c71375908031cb9d8e466a00ac12902f5352f2c4a1ee1200ecd852ead42b93dc0d129a4b945911c785eb32c0753292853b76823c924a00b4c7caa257d7d467aa283f20be39ecabcf6c7cb35f6bab5f01f83ded6c0c740542d81dd61c4fb7baba59ebface403776077af6da969ebeb2a9ebbb9bbab9b7a96b90cb95ad81f600e39b917f7553cf5fdbd10dc86697154990cddec650bb9b1bdadb1c6a0fd2b97e7597738e6d25c617b3fbf371ab4df2e753d7edbb673a396505c11653ea0d54f56add80c41ea00f29e9773b9b01e633c5ef90c40f18946c59cd664b1d3c3a623576ae8f7ce28a92978ab38b1b4f5f834c166ce72dd9c6ead4679cde49621512be1d30e6b393312bada46631d57cd1c8ca2216739ca39cab1e500f672a41c673562f53b608e7b3050432d5f4f4440fdf7e456fbdf87948e401552ea9787daf3df0a37bc07ff4a2eb923f471ef8b17725c8633e9fe9cdd7bea72ffeb3dfd017fee96fe8fd37dfd4e46cc2ac78ca36490300357213e0deb28594f2e1ae795280166a15006ec552a28524668b25dc0112b28a30e8c280bcc8abc2c61c0045c8ab01c3923225fa5e514024a65e556a00300f01cf8d7ea11d66d9dcfeb00000100049444154f15b90c516e01aab8a6bac36ae430c4f5dd9d2476eec2ac8e0daf650d721951b6c13457c374806bb48873c73654bdbfd529b3dea1b54baba3d4086dadbe8ebe9ab3bba49b9ebd807d96c61537bab6ddadde8f7a4cec51074f5ca8e1e3e9c4322c76c2d9d70f85d49459941fcd9e76e4a4dad9383735545d28c2da7d178262971b83d669530cdab86e5d2b5ffe8583b7b43720cdb4e83cd2d8d392b1a736e5455aef8f15de3ecc95849f47ba5cc928e0ece68aad078b4d0315b6909528a1f7b1e41668e1fc71ca807d94d2733ce4ea63a46bf6c171a1f1fe9fb7ff47b7afddbff5ca747f7d5f16eb4bed61ef8113de03f62b975b127d4031dab8e96ada4e974ac5befbfa3b75e7b53c707a76c97749a4c5b9919d0df013c40a11b2461da048007755202f003242b5621bd32a904381b38237e1b7cc1de3c5bf5a270268542a67e51a8822ce00a15f893a86a123dca0d6a8018e41cd6857601f5ab10c295ad9e820caeb3ba788ad54410c60edb4d57b7fb7985f1d4956dddd8dbcaf11b10ca3357b715e5ae42047b9b7ded223bd415e4b339a835ac4b08a4a2ffb576b706ac5cfabac14ae546268fa1aeb0a5b5b7d1d336b657287b0542da4306ac8438746075c26aa8ac75f8084260a67f70f048759f9581a4dd2b1bda8294960bd7f978a14dca8de70b75492af14d82345b9cd3b222dbdcded174b25401512e58c999cfe489ed2bb6bcbcc00f7521c7ef6559aac0272915d8379ab08a71fc75f0e84cf1274f5ade5dc3017a708263c3a245bc4ac13bda87c8ee3f38566b8d161356955ff903bdfc8d3f80c84e7997bc24fabcbed71ef8f37ac0ffbc05d6f64fbe079680d0d1e121e032d6d3cf3da31e5b3a657f53a9bf01fe1bdb26801d2b8516a42a01b0ba482a8b4267a763a1e200ba52cc94e30ca46193be04040b6c12a018ff7570507a06ef0ee3c20dfb5243b65e063dcad5c49965f7eb4adb80fd7588e3e6ee504f31430fd2b801715cdfdd5210c5733776f4fcf53d807ea89b57b695c902fb2b5b03c862a05e9d3400947b65c14178c1565ac14aa6d420daa80b555552af2ad806eb6b6b506b73d8d366bf541ffd903eec515790d355086b6f73a0ab90c98dedbeae6ef5e5ac08eaa2d1336c81d9a263363fd58307631d1eeccbcc659eb441bf878ca161e1f1e0ce43eaaf3487201c5fd4f4ad63fba905e1b31feb4acb799bb7fe8e0f4f00f919645ab0cd75c25655a35ebf60d5d742c0a69abef539b3797438d262be54415b878763dd79742227bee0f03d5e441094c957e5f1c1fd87a7ba7bf7beda6ec676d6a15efae3dfd737bef06bac628e15ef42ebeb47f3c0252ee59778eceba1ff2b1ec8601680d6362a019cf3d3077af11b7fa847f7ded76472c8caa18540a4317beb017a894f4fcd6cda89504cc6575d1ac05ca863ff8409311a03c25c49520fbb8dba04ac7b1ab22d1440bc07d86ff46ad580ea165b54b13db5b331d01566ef791b89d5c055e25700ed2b00fa1566f2d759653c75754b01ea3bac1222bdd9afb5d1aba8b782004a95854314158451a9662c35a450d2465d9639bfae4a6c7bea01dc412235e308a22929177de957850694d9a06fdbf4ebca669ff67abab6ddd37564abdf57821cb7364a6d906f8d4b8b42efbffd482ac44cbf537f634345dde3907e205b244d4ea74a5ee88c6dc0e5b2530f1268d9da1bb3bd34e5609e4323fc54c896d272b91009d575a5c1465f7dc2882708c25ab25cf835650229f1393c4c5c3a65b563e4cd219625ab1b5e83443f4bc87b93f6eedf3bd5dddbf714ffa06a7a7aa6ef7ef1f7f48d3ffc358dce83443aadafb507fe3c1ee0a3f6e7315fdb3e891e08e268d8ef689aa546a353f6d7efebcefbafea9b7ff419bdf2ed1774f7d63df6e98fd8c71f29fe78df9c15ca12800ad005cf00c29a43df06d04e0a606e012e304e2c30900e12695582703504b2c94c7a13f2d860a63f1854ea01d4c37e4f57b73790816eb0f5f4dcf56d0eb6b73900dfd1339c55c4e1f795ad0dc5f6d3eee6409b831a402d912a4b0d4104f02777da022fd5c98927772573151047551479bbac4ce8429f526ebb57974a17ba025d89445878528455915407a99449fdb2509ccb0c82a8208d0484479f368735b14e3565e6938e2dad23c52f42ce21817a639bf38cb9ae5d1942385283efca5e8fd54497fb9e94e8b02bb6ae12ed54104555f6f01bfaa292d19f8a154755551ad066fca45610899ba9ead5324bb42d250822fadcc1da057d8eba46a399a6ac78e2f76d5a187e502715b411bfcf723e9af02ec79a9d8ff4f2d73eafb75ffa0aa435d3fa5a7be0cfe301fff318af6d9f2c0f04d8341047cb8a23fea3ddf9f989eebcf7bafec5e77f535ff9cc3fd0fbaf7d9fed8d11dbfd0bf5ab14e8279616aa00d3ba2e9400e71003b4bad6549605336fd3d1d9546d067153d3b500b103d6a58655a11bac1c8a926d155906d62ab9f6b6fababadbd715561a4f5dddd473d7636b6a47d7398fd8d91a6a2b7e3a6a67236f4b6df62b0dea12302f55d35e4809289a448b9d169cb51875172901aa49458964c2709999caa25049b932d17f88a3221e928827eac921f645807084b91e5749582009a9285b78cae3ef97a536fb3d25591e67b2a4937d7cc67694b733ed42885e0d5594b5b6d8026bd8eeea0d6a39801fe1e6f626653bfa6e9c6b8c01fcb1fa6ca55583be944a2da8b71769641392ad7b958aca282f6de39f25ef2e31a6aa2895dcd89e0a6995ac5059511e7fc43b9e727e45a636687bd64a072753c58aa7283a1d3fb8af17bffa07da7ff0ae8268e273a1f5b5f6c09fc103fe67b0599b3c811ef861900880393b3dd15baf7e4fdffe179fd1ebdff9177af7e5efea60ff9ea6ac48ac99ab64df64c121b09929016a55c1d33b9545c16cbb5103d88ed8d33f1dcd39dc5d280ecc4b00795017c214d04fdadaaa58a138f682846a5d614b6a6fbbaf1d80f52a2b8cab3b9b90c926805cb30de6908e6bd02b00bd8a7899a50214aba250592615d4efb49b5241bca0ce1252097d9203a674156096e8aecc4c897289ce948405e5cbb254c4ab32fa55a9801c563aea201ee9941cf228c873ea2e721f3cea7257af2c99fd1be3dae4cca7068f97ead705ab89562747239d9f8c957ca6eb37f6349d4ed4ebf735ec0fd8ca1aab37a854d5a506fd844fa49ded6d15454dbf0bf449316e831cbce8a98d2da8aad016beaa7b8e5d815da22f9d6a08a1ac0bd5104c41f9c974218a91dfc9ad53a2afa6a4f8a3952109b24f56e874d4e9d1e154e2bd5675a1a3bbefea3b5ffe4d260cc7fae1eb873f273fac5fc7d71e080f783cd672f93c6066195463e40d1be567a7a77afdc517f4ceabdfd1e1bd5b3a393e61cb6aaee57c06801aa03857fc76b401c99b9c3718e0e432a532a901a0f6d9df3f194d01aa25f60e80b93600b5e40e7914dae5aca2020453802e6d3fb5b7a1a7e2409c19f895ad4126932bac3686ccb06bea1cf46a85f4097b75a504f0d784bdaa545dd7aad8d209e9911f61d4ed6e2ab07377adaed518136450d26e455e915c29994ac2b248d93ec8a82a0b00b984848aac0b9be48eddca26b9cbcc54a4a4285797256181374c0348a1dfefe5f2c3582131ced9d918204ff86fa16bd7b7241f905f689b9554c779c962dec85392d187e1665f752fa9a68e449aa6383719ca24ed5dd953fc1f14435932f65e5d60471fab5a385e83414fce7a6f6f6f0b12abd8fa12e41d2b109753a6ed94c76b66799b31d6697bdb3dce4a963a1e2d757e3e53c15816b3a96ebff61ddd7af3dbf4bba1e5d56d66abc8faf9447ae0df7550feef5ac1bafc87db036601109d26e323dde3dc6372bcafd974a6f1649601ac2e4b30872d96b66106ee9041525db90a66b26599244fda3f9b69441916241ab062d8644551008e01f645726d6df594d8027337ca999e651beb2aba2bac40b686b576867dce357accce6b0dfa25e4506470aeaa32872925da2c55d197a228b2ae8c30d2d45f909f5885044984386372739999dc5d517e25853c25641526f74c08452204b873dd17445297256d16b4995869148425f53865937a1058afae5462eb9e14e5068341269b018416c459ba61d763053741dfe9e6b33714d726447af5c675b6da4c1c4ba8cf565555465d29837f2a4ace491a6d02f2b14515ab334b9eb7a362359018778f33919a76da26c9bc50a24c55bbb6362bdaac349f2d64920a3315c91464bfc13be958c9345d97f535abb7fbfbe79a2c5adef794b60939c87f70fb4dfab6a0f4fa5e7be0dfee01ffb79bac2d9e540fc47ef76231d3dddb6fe9bb5fffbcce1eddd66432667b83ad0d66ae7d4032018401b0894fca70506913821840067555a8611532e5e07d3a5b6ac8ca6183fc28b3609f7f329d03904b66c849555d0adcd28059f3f6a0a71becdd6f0f6b6db092899fb8da84407a759d013901f00520599609e0330030a92c4aa4ca001e7909f073fa15628409902c5352e10844d2ebb11ae8f7d447a2deaa0a802e72f9845d940b31333903737759165bd9544506ec028248d497e84f5d95eaf76ac659ab47bc0a9b945462d3c104c38dcd1c0fbb01edd6b49998fe1b67432d07e73bdb958a02fb42106ac90a639b360bf5871b8af68bb2545595724b5a34a60ec2eed7a6e44b88bb564a957afda1aaba47fb9536b76a7c5baa2a4c3d7cdfb54b55bc978dcda13c25c45497a63e7e8cfe55e13333c5c17a896e73584af4effd3bac34174bec992458abe387b758799e29c84aeb6bed817f8b07fcdf92bfce7e023d10e010e71ecbc55c87fb0ff4eeebdfd1ed37bfcf9efd89466c6948a67ebf52553ab14eecd36838e86900a06d40209bc35ad3ce753e63e5c26131d8a51a900d209c0246f10b735559c800f79ded4dc59f39d9852462cbeaead630af36fa10c6b05f6b0028876dc2d601f1945c059200ee923a0b4037451e64e566723711103a12e9244f2e4b49655daa064cfbfd1eab874a515742ef29c91d7bc2e484668a4a3c11f7a4289b52c2bec02e214e3c01f8452685b25a8515205f02f26559e09b526584a4137d1ab08d559535f62579857a752517aee330a843daf95c85c72aa3a36953499d664e3b85126463669429958a52737c3a9b3579fb6b3e9f68636388dee88ff27ba9f1591fdff57927f1a3ba868fe071754da7f0992557d32c44b7147dad73fd5d2e1f2b9006e3c25b083c69ce99c99d7b279a8ca70add319388775e7f81adc819bd5fdf6b0ffc9b3d109ff17fb3c53af789f2c063f2984ec7da7f74572f7ef3737af91b5fd2fe9ddbec87b395d1892d1767db26091cd212f00be0dbac4b6d432a8361adfc533ca70b755e683aef002bc9ccb404c0424a00adaa2bc54cbc4c49cf5cdd55ac3a7636faea03f065590296956a00b8280a256c5284807bf2a422958070499864f165abfacd5d0e2aba3b652817b6a44bc8a60224cbb2a2ef2579aea2484ad45900c8d15e48453ba1f7445eae839078e151177586ce93621560166953d8276cb2308b4f516f89fde3ba238f3e149c43c419460078499b3dc68f4b24b68dc42a64369ea95db63288b7a09d2aea628510f5451fc55544ff59a5c5b986945809346a59dd95b45114950ac6192459411ececa63b83d905157d348e1ef82fae816a451657d55258834657f984798648c6bc4f664c11882a83786155b584b1d1d8f343d3f931663bdf4f5cfe8e1ddd59f7e8fcf8bd6d7da037f8a07fc4fd1afd54fb00796cb858e0f1feaa56f7f497ffc99eb11f8440000100049444154dfd4ebdffd3633d011440060b1d754570eb8bb26f3a59a65a71d40e6fade40bd419d7facf484554a03c05999b4207fce81702a4b05560ed83e72400a1e525595daedd5bab6b3a5ad418f154c0931141aa0abab8278a992b02892a24c480158a6b2509044809d9900c0241131c032c489273771e73c0f708c3c930c654e03b65559a9a4ee32ea440c9bc833f30cc6a9887668fba28ca788bb3cdbb93a1922242a26855d72ca16a58a22a9285d092288f892adc001db5805759665a1c761493fcc1295b8cc4c2ddb4655d963b5d0b2329ba26f14bf505896a51a5a6ad80e4b2e0d86db4a5ef05ea6f8a894d1b60a93a724b7a405a424172bc34dea4daaeb5a05fd2a53a758998c6773a5542a2543a425db685565d824ceb7e62acb84ff19434a8a2db9e964817ea1b393033d78f72d7de61ffd553d7af8fe7a2b4bebebdfe4013e82ffa6ec9f64debaee9fb607623619d2b08571ebed57f4f62bdfd3ec7ccaacb803d0962a009b3eb3d8ad41a5f9a251c71655fc1991676e6ea91e94626b5e67cca447d346cbc542a3d122ffc86eaf5faa6265d101724e1d0e88d5d4d3e3d3758559f20679bdaac88452d7d56a15027015006d09e02640364033ca26f4c93cbbc6cce4290190a6e42e37895be02cd249a43a12980985cc0c11769643948a480b2982dc8acbb12968cc68c279502d2646962b9e223f424cf047b4694aa190e5af281fd452d0afc292ccd0bb299f334002c93d97cbf99e14e3f3ce555715fa420681f421d3a2a8341bcdd4b07c30c61fe925444c55f232a9ecf519529beb2d4ac9e944a2bd846dddefd36ea1d1b8d5ce953df586030862aa049939fda98ba456b4856d896ed8ef69c6b2317982bc6977298d49c7bbd81cd61af06e4bcaccd8ce9a4ea62aacd1e1ad77f5b5cffdba46ebdf50d7fafad33de07f7ad63ae749f4c082738f3befbda6b75e7e418feebea7c9280e4c1b15eef9c075735048805c335deaf9eb5b7af6e96d3933d70e600a206e59712cd9aa4a6cb51c9e4c358f6d99e4f9e0bde94c4b66d03d561883aad4ceb0a721e4524314755130d3ad541585cab2542274da34c4dde49e94dc091d70944c228ed02eb7cc4c862e44117f2c02ce83208248c88fbbeb56c01b71d4178151ccc583dba83be5d02cf491962cda3793a7b0f30ff2255bf58d3c719951069d99412e2e2c45542dbe4d3136c65200c8897a0a84e1513e610bb0379d9af94271d01f255bb608dba6a57cd4e95ab2b5b8b9b121eb1ac54fb52599e69c4f04f188713aa410f50a8a984c272a2026a7f1d393b3bcf2a8386477da0e92f1d2180bfe8644e6bc179a51f89e57a64747633d3c1af1fe1a556552f477b168351dcf359fcd24357af37b5fd75baf7e0bf29990ee90f5bdf6c0bfec01ff9793ebd493ea810ef06958791c1dded76b2f7e55af7fef1b7ac4b98781b06e02408cd96952498209a89eb9b6a3a79ed9d212209940263340af01fc0ac0a6ac4b9d0234e79325a5939620d30cf0116057940544516a8ff38e58790cb0add88bafca5225c01692525288d396037e6e2ef74469019c1d21127a7711c81cbd49423a496e9299c92411c8507490578c31eb2445a8b65187282e4313771627df10c98c3024da4ae8a9cb8c109d10b3c8d707979929f4e232ca38e9228431b5cb39605c2900dc194f812eb92b51a4221e6377ea9e8ce21709072a01fb4c1ed82e2192b2ac35e77ca2cf19474aa6e4944d85e6ac0ce250dc52921705d42135d8d76512ee15fc4058c8a24c9d542009dbe85f2a4ade6dcaefa491a96635c21c40a76c431e728ef5f068a2192b9f940a254b5a70803fe7c0fffcf44c470f1feafdd7bfc5b6d6a15a26155a5f6b0ffc2b1ef07f25bd4e3ea11e6899818e38247deb956febd56f7f4de7478780f592596ea304245545a7ba76d29d365935dc786a8bedaa09db248bd539c7d275ce3ef912a0de3f1dcb40ad98c9c607a806c55a00a604ace6d3b9aeed6e6b8bd5471f7d099085144592a7a404009a99601e25c2e4b489105580ae275706be08cd64e6d82226d184cc8c729ec3b0938c2fc9d15bd8225dc4a34ec443487b4af2e4324324a1424c8faf1c6b239563e461e7491ec08a98b9ec423cc230459cfa456509310655a42446a4e43cd145bec932885755a9c817e71125849ab0edd8c20a9d536719bee94cfde1a63acea9920bc01fb0b28108979d0c7b2a967929b12d561585dc5a39edca93c6104d42c78b55af9f94b0af7aa5bcaed8e6aa29622a4b57afaeb56032b0c4a147677326030beaeb5494851afab3982dd8a2641b6bff4c2f7eed8bba7fe70dba8c0de35ddf6b0ffcb007fc8713ebf893eb81f96caa7bb7ded4ddb75fd6e9fec33c33ef583938fb2415a01220b604fc3799bd0e87a58e4e4790c712406999a12ed472683b6776ba6813601390656a382749c9d9a6eaab2c4a6c97dade1868c096571fe02a218de449455128453c42402dc0de2997ca1efa42ee4698b284dedd652609791c8acb498488300b3ab30ba34499941420ebb9bc51af2becb0d06a35d249806d3c259379c2068972463a24ca2264c78d99212e87443c2519799d998c78b4232e338fa74a7446cc9303de488c17bfb89b42c2c76591944817965456a558e02975a62587debc1408a3afaa28343e3d57a2cdbaaa392be19cca5c4216ac3c5ab6e85a48c860f0d9f9b90aea4beeaa7b03b6102531ae41bfaf54d087d2d41b546a3d4553ea8c74b42bd38cbae0251db11a99c42ac43a55101bf309c612ed25bdffee5d7df3cbbfaf8387b7b584d46295470beb7bed81ec013e25395c3ffe3c1ef890d9b66dcb37ff92f38e73eddfbfad295b280d0054142d5b1f9e67a3f35903f0972ad86e5a0250d3f9526dd7098ce1405dea40152f2b8d2773c521f01904d3abb14f49f1d75e178ba51a8cfb75a5644ebd25a49294c8f7e40a1209004dee32f24b80aa62c60b9ec9d189b6cc4c66916f9210909e3b6232f4897c171721b7ccb14102d4dd5ca4987cb7f26c627233253799598eaf6c5669b355e8b41d6292fcc2d6cc64a4cd5c115258e62ef744889829ea7a9cf654c853e8137aa9bc48277415b2b2756c5c0972c8e5d85a2bcbbe440b44e9b7a9a0fd8d4db60d217bc31f5551a8e33d892bda58b0b5d4e0e796558220ff167f2fe72d63f40cfc45e1ac1c780f6c272e799f2c3232a11455a104915455c5bb9e433495ea41cd7bb39c3f5b748a7f7ad5d24ebf4ef4a593f3be63121087eaaf7ceb1b7af395efe860ff8196900866eb7bed81ec01cfcff5e389f540cc181fcbe9c99196004fdd2bd8c600284c1c90ce3329a05655979217609329996bc90c77346d350589e217d0466c91745e0a7ed1d968aa12b229eb4227676395805d0d4019e51c804d84452a9592ab00504352802971777448d72de56e12f46466e216e8a57c99647c3ac1512226c326ec3aeca3bcb80c0970367223cf003d33529ee4393489f1587299bb9cd053525194c40b45dd59a80705a5253353d4257b5ca6503293bbc910b7882719f59819e34ba2904c52ce7797d37e92ab309799299169c64322eef823a96d97f8bba63cfd08e78bde43169bc38166e3097626a760c7b955c58a21ea5ccc969ae3f7c5c51653c2bf755da8a8e823fd99b392089b86fa5ab6a7a6b34ea36923b7c82f79af1295aac4bec70ab1aa7a3a6342306775399e771ca877b4ebeaf5920c7fd7b5d8d64c3a393cd47b6f7e5723563bb3e9544d101855adefb5077ced8227d703411c2dab8ff873250feebca99383db1a1d3ee05076cc61e94c2d04b160eb620e78781040afa73987e54b406bb698eb6cbcd00430ea3ad3294472ce613abb566a484b80b017804d4f6d07f030fbed43405569ac3e0a552552952acb0a2995922b5620f177990284c565e619c489cadd084cf1152462a4ba481109ec25c026a171f0bd2597db93504a96d01398cbcc0462228ec24913a073271e02d01a61a41d8036da354911a654608f1d61f8c3cc48234efd08090c238d0d799166e84ad4e14529bbb0b1a81f7f1a693393934ee6f210da8fb451b0408f56b17598642a2ce1ab5a4b0822b9ab708364c2872563eeb49c2c73de12227789fa78b00ae105e0b2a4d3e3918cf7157ff432ce374ec7538d59498e160b2dac5583b038514aa6f87d9d1eab90a6738d68ef6cd66ac41957439505efce0c0a815862b2a0aee12ce44bdabff7ba9a66c13bebb260fab3b8d76dfe1c79203e873f47dd5977e5c7ed810632b877fb0d7ded0f7f532f7efd0bba7ffb96625b620eb0c47efa129008b0d9d9dd603ba361dea9c0238139024f38ff6854f67b3a3e9fa8c3167c621b84596d72c0aed0822d8d22d5ea57491b1cbef7009f983197842b498acbcc08566200a3231176dd0519906ff169641f1e4329d25206c9503b69c96466520033206fe63209f0bca88394f9850e3b7b9c7f61ef105ed691070452d0648644be08733cc2a8c372bee2ba88da457d38428200788822045d0e9dbe473afa6a614bbd1e425f234cc493bb8a48473ee49e882f99d13be98877ac420aec8a02e28084e227bc6a4207d98d55450783b7b399123686ef3a08643199e5adabaa4ce80d2272457d335ee0392b96fb8fce757832cb3f6a7d723a154de5f76cf437712633631211e7217116d2a113f526374d20aa29ed35329d9f9ee8cbbffff758897c4f2d7b6e1de36fe9bfd6d7a5f6805fead13fc1838f6ff00670df7ff0bebef6b9dfd02bdffa8aeebefb4efe31d1292b8b0626e8baa40933cf9ded9efa3dd31cd6e82c0110a63366bb4a1544d268cc0c75094607904ca6334d2653d56c81c84c4bc06f77774b5b1b4348a4525d9572c0a72c0b15017c0174ee3224162e09c0f204226313ee373379221f95190f896a230c50ee4870879e7ec9232e61208b2f800e0b29ea02d0cc4c422fc3502e2334dacda15697c972c4c8cb28aa55ba23d1511f011a74715b3c5ca1eb5a5a0ac044675884bd7268126d88f6652ea39f66a6e89287de938c30e18b042938e944dcd1459d8932095df8c0cdd4c68f02579582842b0ed043b21fc973da6711a13807496eea4877b4eb94efe85f05815485abc4bf29ece97890c3c9f94277ef9c70ceb1d4e1e958cb65a739ef6dca7bb5e46a79310b5ef0f968a1259f8b656b32de538c65d198a64c36661cf2dfe1f3f3fedb2fb30ae19340fd66a6f575b93de0977bf84fe6e803549690c743c8e3e56f7f49af7fef058d4ecf359fcd35431fa011e03062e69adc3464e5306185d1361d602208a3d1f1d94c1dc0d279a1c3e309ab9686f39216594880e49cbd1007b8a29e7ebf927182500478013c837e2f1349729799c91103cc921b697119a00c20737bd8a047497b9d2cbe4c4ae8cc0cb5a1718978c47268a23c8c860e33548e64650e0d50b6a817550e699b12d4df22dd85a0c14ed4d1211671d14be2a2528b74162a09d38853a7987dd3b86851619ad05b08be10db411e361431b3bc32a2464a2bc7f30a4522ae7cadd2adca20164b54db51fd524ed9449d5559b015582a08a464a592dc15fa58c124eca35ca2dd683315a59c78f282f2aec57ca61aa24ae66c4d356a208d8e0983acd06cd9d24ea709e7234bc862ce7b0f69a93348a4651c89f7381cf6219486cfc35c73ca9f9e8cf4d237bea2dbefbccce7609ac7b07e5c6e0ff8e51efe9339fa2090f1f85c6fbffa1dbdf4c29734399f6ac68a221344db29feded2783a57cc5a4b77cdd9aa188f57201107e5e79c77c00f2a926bc2cc73012e3ef50000100049444154b1582a406c4e1860b904702cbbae53cc66ebba527207080bf5983dd7652133a0b3031099252b8cc93744e8f4f842ef3fa4a388cc0c71cc4cce17a90cffa8b3deccf481425c8c47ac1c3ad4527e280c1ec7f4417b6828cbad2c6e8416a6ab10a599c928ade833e5480a332922346a320990564e2b5f2dc4f9b88d184ac42d72b06929d321f9ce1dcc39e47612fd766ccc4c09034f3c69d7145f5202fc8ba2529045099827c72eb94a7c1be9b22c2117fc0eb1844d8f13ef0a5d497e072938f5d445a1ede1402de75a0b260ef1831053b6abce270dabcd062259ea7c34216cf38a73bc68743a9ec9e84b437f37366bb150d178bcd4940f44c8edf7ded18bdffc238d47a7e4350c97b1687d5d560ff8651df8933aee208f25fbe867c7473ada7fa4c3878f747e72c61ef91ccceab204210479305705a00c1268356536da344963c2f16ca9b228356fe69a012a09e06a71989902eab4683ae00905b71944b16ca8c733895425b582a42d9651c6530aab0bfb2e2a20ddc93dc98cb219a83dc7cd6800712c523c222993519f08757119e14a47e4e236f2cd2ca78c6640b61cffe0419ed945a572598e77f41261db26fc412217cbc5a32011baa755ad178a0810b2d05b9688ab6bf16dcbf85aaaa14e729c361d5b05c91811da34a443c23ede55a4c9519c75602e63ac619af09be38484141048d4952897bc80a46b0ec12b0d7bb5fa1047cdb6612f04222f219d04a92477de49caab9de57c4ebc6032b0a48fae93b38952aad52ee9afa4e8c70462797832d6095b945e252d58690d0649a974deb7209a05ab8e464787a7face9ffc1124f225c54ff54559aa58df7f260f3c7946fee40de9728f28bea197cc36cd4da747fbc88996ec612f01c9197bde0d3349b0410ec084a016d82759d28895c864d1690e98244f7ac4d6d584950b9900512b03bc8ed8c698b0f555a69441495cf3d984fa44da1097a18b3bd10701a76668425032b19599a10564333aaff21c5d0829f2598128c7845ad91a5b737428628c426bb64a8bd0cc5199cc5622bf48cb24249e8a8b0877c41023871ee280ee422cc09e9580908e362f9c936d79c88c7a85d04ece0f25d289bab0efa82708ccd029ec1e4b28c957f42b6ccd65660821fa8e97928a5a8edeb171ca7be41337fceeac260a5677254411241d041f7f4fabdfefab2e2bc5caa38a150876a948aa383fa9eb8af766ea53267e6c7716efb5eee9fd070fa5b250bc0b3aa04e6255da6934936e3d9c3071685895345a722636647b3256ae7326092daba625138a5b6fbdabaf7fe98b3a3a78849b5a4aafefcbea01bfac037f52c76d664acc40f70189f1f95265b92103ec0384423a50c3019a58452c032401aed8da98030c27a339fbe50b05d99cb175754e3a5136b6a9e0209d724eb2044402a852e2a313757992017289b066361c80940a577c19600844ca8c27712942535c6626779799c91497014692d963f2a086c88b3412b65d201d629403f6c318a10e5143b655be3021e449ff7812c73a0a5f48d611775f950b042599ed189eccd06b25b9ad484732eb3ae564a48974488e5241f74322fc6a513192c32efa401334c066155ae28a31bae44e9da624e53032cd0cb52352944fee4a9043c1bb0b32498934529649056490dc79d785e2f74206fd5a555daa284a1ab54c207dca7590c064b2d019db54e3c9146e6c956827b9cb09a3dde3f34607c7230eea1b26124b6d710eb2a0cf9da458a5187d30daad7a853c8977d62ac64df6fabe841ef04b38e6277ac8f1cd1cf2d4b31fd1277ef12feac6b34fab1ef6648044c372a395e7ad88d99c6f7c10a3c51b4c4c35263d674b23c883a8e2a7b00cdbd8da312b14b3d7391be2069cd565a5d87317e50bc0247e7fa12a0a95c44b42931420631631c9dd94a3310b97889bfef5cb64eea80965949150703ba96849a40d216d848a6b15761145c06f7a4424e71be5222704ddc5bdb2e9b0ebc0f826571ced1a7d9445dd105814216d46a10b9d08854ed40a626a7575ccf0bb55d48c3aa9ae23cc8d749885774327ec104998b1c6898a577172b9a983325dac5e643243c217845891a64f517bce17dd3074083ac7c6b12df07b910a4823a9ae0af57b25672485e2fdc4f6569fada8ddcd81164c0c0aab74723201fc453da27d892a9492aba1ffc7e733e2952693b936fa8592277c656c6f2e14abd4f164a277de78492f7fe72b6c7d8ea9607d5f560ff8651df8933cee023029ab822d887340e044e3f31107e573cdd9be9a7078ce8412c0e0d55bd27cd9adf49c7bc436c5ace974c681fa92d5c92cf6ce8b520b66aef11bcbb11209b4a962c65b022a5d00700768013245a2ce04209978281389bb12fa48ebe2022773cccc0058809394c924d2ee49c4146d80b28491ba104c2f6202e35679e698b63223073133c59700da10201c3b6e4344050826d89026197d6919a788d84abb7ad26f2cb44a88ab53940b3b128a44176510455bd42baee8bf27279bb109e95046fd268663684853a643d7b54b7510b2421b3aea3149d18ed37ec45d927bc153e82d0b8fdc5ad845dccd2189a4c43b4fc995b00f22298b58812495bcab82fafa84f14fbd1680ffb037d0d9d9887a3ac56721b39bb820f820a463ce42e2f741a61cbe57a5414675b65bb0bd797ccc39c9e954f76edfd59bafbcacf3d323b58c87d2ebfb127ac02fe1989ff82137cd5227c78ff4de9b2fe9fedd7b9ab3c4886daa0098b2aa14e0d7821c330825669493381b01041a3c733a9ae6c3d60a20dadcd89017ae730e56e72c4bbaceb585ae803c32f881e4514f8059fc98692753db4966805e44047092ee88879d5a93015ba240f4c12cc9b021c953b95f8a04d22159c9c3dc658ea585448528f34ddc5cb90c51e56b1501937f907a9c4013edc66fce47d801daa2ca2ef229662408a2230837fd16209ff3891be573ff296794e166349d3a7c2712d90e9bb80d1b6a20da657c762c23fdb80e63ec1d895c162b89448c8f7aa22eb348bbf2d8231a222eeaf5f047c2c78cddcc14ae297838e944ba80b48b4c280504124452285621dd72a9c2a55e59b2a268296748a7b8a259b5f490e4295b9fcc27349d33364faa07a59867287e7a2f261963b6c1261878ea334199d2dd16a1a0d6d713eb813f65607c9cfe949cb5fa43e98100b1f87b45776fddd6d1115b11c550060818a0e3668a9fd09a7338dab29d35050496ac385272c596d398d5c902a408f0a9219a3683a7290023ca0f7a358094e46e3233f1e07625d2299009554a09a80450723e265a5d66645e48f4314420969963e0225766f681a09451afdc232a3224991462a103b002f500d415080376a84032c565614a6415445ea0230aeed045fb66c4a82302d16bd19f289febe30c431043904d64857dc8e3b4e88799657210061dfd784c2e1d75b6a4a31ea2eaa8ab8d150789365883b251b7b57418bb28ef591714ae7c451da1b748d18ea21ca1873f08857dbc13e1a3ee719ad0c877fc13ab8e2092945c659009fa447e55146c61cee5948bfae992e28a90574fb74c33561ae359a3119386f3c98cf3944232deabb9a2cb2dc6ee8536b67755b39a215366b9a75a5f97cb037eb986fb648f3640a705f466b399aedd7c56ffe15ffe5f6a7b774f0d5b508153b12dd58000012a0480813133ad146010db5bea92b6373754b2c2089231c8e08cc3d628d783503606fd0c146626bb7065ecb71745c5ecd4641622258f8f15e0884df48980bc7822a10ec9b68ede4507649e44e203f18b19b6002be05f3fb80cdc43137a24eb09bb18a0a8d856f911ed1864479e6432a31dd307579845dd591136c8aa8a4e58521c638ccc08c3887c1c268ff485aceaa63bb91d4813a28836a3043d21839bf7d15131b73ae23cb8c9cd462d35b78a2896541fe36ad15ddc148ab27446a2cd6c4718b6ab78d483080dfd333785500ba1abe0fd55ac3612245284b032a9cbc4d9889167bc27ecb5baa216aad08c3e4e8338c64bb140d519ab8df92208475a30e9a06225eaed303e78f03e9fadd9aa82f5f3527a20be572ee5c09fc4419b991cf01e0c37b477e5aad4cc38ff385580710bc83580434a2ececa3597690e2024807a49d8b0da883f4f72723e96191f0bea8a6daff174c1a16cad009ebd9da1441d330e6263d65b16254054401ead0c948b596f025cc49501296b89016c3c15a04337b0c480db6827f444259330b8c82341fb681441883c29271457945a493c577aca908836229d4134170cfba81a0da017f92b69514a94e2c133db761fa4735298d0e18eb39e9c661cc2c20c7b4986afa32e33d74a832e22b413810caf40026a5ba2b48fef3a5622826872bea2fe369eb2f88a7a288396146a398f0e59dd6649d92e92b67a18368e6f0c796c6961453d29799e0c38fdac20921a02a920fbba2c54155232a9e47d112886669413e39db0fa8809c58415eac978a1734824d525b9165d8bd7c4ca2be9fe9d5bbaf3fe9bac6a17e81eb7aef575893ce09768ac9762a8192caa526727077affad9724408bef6ec6dec94189f805b17380e19cedaaba4a8adf509e2d96eaacd4fef14866265307c92cd9ba9aabf4a4415d28c863321d2b48c5d09961477d6dd40c487aa491f849af2584849a66011573c08614519ef97e0cba1fa8005cd1a6919bc5e34922eb234e1d245737a5b8258f22484e901521bda14cd44f06ed938e1c74041fdc74f3833846ea00cd0e5027821ea0ffa02f9de83efe7034b4472cec56f5d302e3b630209782a1c812355828c8cf018fdcc6e32d32f42de7541d215934db1174728b52a6f86aa3cf16d5d12e7aa2b4b2b2c33232b08b80d6b0e529e5be982c4277991975bae2072acc4d9e928ac2218f829567a9b248e477e820269328c28480b1526eced6e6e964a9c3d3994ecf171cba4f25197712ee52c323fe7ac1fec37dc5ef8b687d5d4a0ff8a51cf5133ce80e30198fcef5d62bdfd39db7dfe430fd986ff0a5cc5c412e4b0eb2e32faf36ac38968058deb2002cce2094399be02dc090009a31e0b1249d7fdb99e96afcdda6257b1a455900324931a30d3b8b4f50e01aa01341d46b01560870a3e4612089fc10b3d03e1671758a3e838e71938e3c026e33a80c3123645ca8b00534b18c32942441ab9187100b1374ab20dabb88ad020c0ca138363fd44e56a0c2aa05d4bb0fc4a882fe339655ae648fc7437ba61f5c24732d6d44f0e10fe5d0e716e9647c29c44c8a308bb81849b4098935bc9336481f2d85e2f903c12674ff5271dae3ce35c55fd735a36e6e7a8dce64661045a1146441df1304e22c3d62b518675a89b155a133c756bc2f9353e6e474c201b9743e6e09979af2f9e063121408794853b649a7f9872be674a9d3faba9c1ef00fc1b0d75dfc7378a0034d161c928bf38c96eff8481bc0d1caf8e677561c22e69900cc0b9d8c679acd976c43745ab272a8d9aa58422e1d20b6d12fd5ef2508c314e858f66b669f2bb048908c53af754eb946ee06b0b48a5c3766b1e20288401799998c647701ac514ed99227fded721c0bca095b4c2592bab83059c53e88983cdb993aeca27cb4ab0f0a75ab558f84261b1012279a2392cc48708b2b403ffad631e6c7e01d4d0520632897c90c91616df9a98e68dca10ff1d05b0c48d9800aba007c0c0ddbe6821cbabc1d861d6f23f23b7cd25e0866327c1021c5a3a4845df89088682602316a9925e2f40c65f4b373d2148c72a6d5574a8e8d2142e34a1e92789f100aefaf48a68d414fa2a592b311026e2393e2a28e00001000494441546d3a1bcd34e2007d8cb454ea96f2bb5fb66275da11c78eb63736b759e1549459df97d103f109bb8ce37ea2c7ec9e74fde9e774e5c6338045a9966ff45404004889374e12a09066ac28624b3e8303c0e05e80559d96a044c54a23fe8c4597c1c355969526904de4b500ad039811c6bfbf2db18d3ac565e80390a2018bf44546d47311452bb58066443aa0cd2c2c3b9208ed11519427456ea488715fc4940133c039eb18902c7f45a100bb6c673ca9eb8376b18db8b054b4473e771e6fb415fa4e682c518dc9cc519b3ac69aeb8cfee636dbac57d421a35888474c5925ca3c6e572e459a8cf6a29e20e7a659307e1c1e76a23eeac54471391173538c51e4296c282b5a8dfe8590290b631ee6ab360c82c214b30ecbc844725d929929a51057c16a839bcf05e3944817ab7c6cb869aed5820944fc150202cde7d157454fb08eb135a24016a79f75afa64ca7f575393de09773d84feea8cd4cfdc1505b3bbbdad8d95335d850d7bada16b48961932fc030b6302453fc467990408046012038bab2700d079502201a5631bd5ea5d97c0ab12cd5812a011aa21e33536151829a0815175892418de68cfac24ec4236b959602c8578216d48b38304a823ce02fd21113f5afe264510755a30a10cbb928d17013a1543c2de75fc456715bd9c64a25fa4595797512ab82a83b749e92a2ab4e668ac259a2620a13cf4f1ea1a1bbb2f83232b8dd9db29140cc25eae091fb93a11c75d45bd08699916d9242a25fd44885a82f7cd210e28916721157cee6416d4164ba284732478d748c21445c510f0175f0a42f4610fd3333459892abf0a4c4fbf59424cae34d2577aaecb20d1f0d097db4da406c52f4b3657519c4d1614bb98b3e9765a9e978a439db5998adef4be801bf84637ea2876c66ac164ac02069b8b9a7addd1bea0d369552a1c0a50088aaaa14b3eaf86de319db56e349a38a15caf6b056af5f65306999d1ca5c835e09c9789e95b6ac4c12c0539461232566b5c6dc34b9aba51e33cb6d07a019654de40236225cdd262a07e0029e3a42c0529251deb0d707976186ed453a57110fa463102d838836620c5161c42f4c657c3d8e472b54943501e2596f265d88791229e564ee83e5b8bbad6c24d23ff816c9f6944129696513fd11979136332577a5a2907b929376f1153ad2412205710f21cf8c5288281be3d0c5d532c6d5983ac54aa4431fe90e408ff6ba0b3f083bc924a4555cc4a33e02e7dd8426c4688f9d46a59454a037a34f61473df14314059f0da28afecaa2279d70b15a1ee686caa8c6f2d65583618726fa10ff5fe6e0d1238d4667bccb2e0b86ebfb1279c02fd1582fc550cd4c66a6a2aab573f5ba76af5e5380c774319712c00688b4000f38a03984104281bce2a838ef184da66c5b34aaabbe7a65d2e64605792c359b2e640051260f80cca8a063ff2b00cecc14606e3240a495137af676270780026c30579bdbed44b6cc4c0a748f27991d60d551af849e74ced6c5152aa21d7a4158441545511398cca25d5454dd85c6691d5d06ea445ce453c8cd73df8c7c232e6c4418d5ae56282bbb483b75e5542428db6197cb79122d4a164f931422e5658d24c3cec94bf8d9c3dfb425aed01348e4f3505c26be485b242eda091f31126e46927539372cd5e13f32147e0869c367e8221eef23f242a2df1d84de52af517fee0f616c5f9565a19215482a92a27f2c28b18ab6243393490835f02e166cb5757c78ba700eda9678d31aefb153d3b459865bac72ebbed6d7e5f4805fce61ffb446fdb369c701ad2db6b076ae5c57820406c32d157c932f019bc562a9025d4bd7e6cb0610685496ceecb2d5c1c994adaa8e83f3be36598d547595ffacc9c9f98ced2bb630bc50e804b834ac5002b83a802504dca6c64e01569e880246222f40aec5fe22a9b802a422ec3ae08ec7639b16a04295ab0a9dc85b49ab00c4dc5e4b49f48feda2deb08d3c6a23f3073766b938dde05eb56a66ab3ee2a3f093c871c4a830ec236da47333e2c25e48cec77f8234a5464696428faca2d4624842220cc98e88dc9056e450c4b28894648ae26684f4e7715a3f74e571d1b155783142d2d924c24e32adf4d986b8191aea73846ee0834e9e3caf40cc8c558813972a3e079e929c7e9a19a12bb92926054ea826e2f89ec177bc1543724bd8b2b3a9c572c97669a9c1604099b035adafcbe501bf5cc3bd1ca33533d575ad6b376faadf1fa88d7f0cc53e75c3377ce27bdc64ec69b7324b10072977d21de4d1a8667b6a73b39679cb0a65a1f3f15ce3f1428d9206fd9ecae4cc405b351051e0a98cf2a60c52c99d6424106eb4d41bfbfa2bbf632a5de8232fa4835c3a802948405c1da00826cacc14c413b29a7993895de485908d0d3aeca39ea8d6ccd4b5e4724b26b30b09b036976479fd12454c22653233897e7732aae9b4ba3a99adec576929cc44c1e867b411fd54bec23611c33e8c204112d8bba851463d59721f4c466688636b1eb110949dc8eb1042f2a2ad90555b744d08ed73e31729faf18184927c513afb927484e272ea0a292089089db19a99dc5c9e5c6551e4ba1cbdf06f32f48abe13a28b77dc32a6a82f6c8c36282e4f85166da98383d33c09d1faba941ef04b39ea4b32e86609f0b30d31998cb5da62310820411a1d2b8a200d29b967dd8cedac38484fc9b4647b62de4a8f8e4e1507ecd3f9827390814ab63f023c02d80c4092250ed18d642b2f4c6e16f0220138ea0890d06110b7e00a05f0826f8aeb7118f1c712f92da8157fb3ab236cdb0e12ea948984facc6803112d65315706365f7d94cd3c9a96117af4518699cb22df4ca10b31339991b6c84b391e36213443e75b6141488a9b883097b00f318bdc9068af23cfc304e9b03111114a043dfd308444ee9be2a29eb032918f23cc4cf1a51c4a32a965fc1d25c20f98ac7c873f3af69d1efbb2253f8c3b7c1ebe8bb85138ca84cf8c763cb9ccd012e729a72f16ed4a2ad8ce32b7bc2ae9e87a224fa45bf2648c2d44f18e955b0a7594dfdcdad6fff82fffc7faf77ff57fc2e7a20af5cf9dac3bf493f780ffe49b58b7f0b3f040cb16d3f1f1a10e0ff6359dcc34612501024108d278ba147c9181d9018b06f099ce3bb6342ab6255a4dc83f653bcb95c4a24529fefc45bf5490d02cfe8c09f6e609d059894857cc4893bbcc24a74ed3ea0a50322345a4454c9633c041c24e11a2262e459f033457d2d0bf80b14e6e9ef32f8a5ec457f55c245601fdb0dcb629c2684f0215f3ba43725fd56311d27f33cf766692a7441d96c5c853285729396100765455245fd9929fad93616a52dc51efe37acc51990c9d9924c8b643a30fae8e9829e7d39e85bd995c9211e729a76c8442dfe50e581e4e87c3b2af720885f0ae23236a8c3eaeec455d003f0a33cb75457d5948e32ab53c5a0a25778a98b2b549663cd0c70f59b45dc3c7a6515c91af559696443ab6f336373720a122b2d772093de09770cc9762c8600bdb5871b8d9693a5ff24d1edb521002b3da2507a18ba6d363194de70107808c69ba68c4d108040382002c4b127559aa4ace3944031ce13e40da0199827c3393032ac93d134a7297190aadae8e8e442a429008d0823440ad0e098b8e1a3bfa14f92dbaf6222e59fea238f0dfc9cc240161a12054be8cd84a67469cb645d88574d98024809f01dae4909ccc2593428c8e1b65424211a159649232ec042c938c3e1225919f3c508a7cca8ad0b035f145e8e8bc2865b4d5d12e1119e4e12929da8f7816f20ca132456872093bb3087571512731339390e84716c6167e0ae9209620ca08c5b5cac780222b7d278b719a29a5f085c92425f72cf14e48cacce42991c49e7867ad5ade873a9364317780d0850f3ac2b08996f84ce4f7817e7d5f4a0ff069bd94e37ee2079d008302e06fdb4257ae3da382b30d010c0de411c02293cc92cc9c3dec4e4591b45cb419289680f81ce298ce968088341cd48a3f6512200516a903581211a09bfc8e739342894f5262368e5aa137e309b874882eae8807ecb4ea3229441a2b723b453c0b792878769cbb08bb48fd9050af10a3df2214e310a10188214e980070204e1197d1310b23a923cc3ab90c1179e6cc9e73888e50bef289cc640cc62c7a489c50a465d8113a621266f1948864893144dc6472c7d624331eeae42921852ceb932424da2726ea8bc068dfe5b98c990b63e50b3fc2150c8294198f55b4e55d7564c43b8d5064753977f58878541d458cfa4ade737257c1d65572ea97c9cc147d8d1fe725a144ba242f429aa5e7caef21e2bc7a859c9c9cb2b21d69369bf2f96162416687687d5d2a0fc427e8520df8320c36be91cd8c15c8509ffe95ff58ffc1fff47fa6b2dfd764218dd8aa922540a195010b2d0ea96a0800708b72a9f00ce64b5628b338fba84a6d0e4ac5013c5093572da264c9b6969b65b0a900a58a19b799cb11d4b90ec948d30ac012406766ea003c1a575ca839e0ef58f1b4f4a413dcc6ecb6e570b66105840edbb0893fdc17a025ea33ea0fb013753d0ecd4c02783b7319fd12574a2e910e3b223469a06047d250c7f809cdc832792a15a44366eeaf07782232c6e3498ee43c741d45c465869f423a29911f36e6d8539799496e126979a18e74d84b181b85d12974d878844a2823c300e70e2be2e873cc2823d248075144aa0d67b8a3a14614d93ff84a68c25fe2e1e407a944bc236d66e2564aae82771dabc7542419e978272ed15d93d1babb2988c63d26169e87d291d3220bda69d8323339efc8b4cf16e962b190196511adaf4be581f8dc5caa015f96c19a99ae5cbfae5ffc95bfa8c3c37b3a3f3fd1643693850378749d65b0aa8a520126f3d8ba02981a88a3ebf441def6b052004c82205ac02351b62a0aa95b50ce56ab0f40a8008c0880956800a112038ca2c196b045d570f8dbd286c80b705b34cb4c182dc0385f2c1584b5c066d9b42b52c1ae218fae4a805a809db94b8c8d2e522bdd10177d9301748c2588ae335a261d76171692298fa913c04fbd463d1157be5a9ea6d0097d842d05cc6ca5a75ef32499cb1022dc2ec7d68dfae89b10135fe80c1d31614d11cbb6519fd0909019cf8bfa143acac84b193ab74251a75922ed182624c673210c3c7cd2f02ec2af1d5e08ff85cf16f87389ff1ac617f966bab89c3a2d4b7257592625faeb123ac9d07526297c4dfd1126cfb932be84b8113371d10f6ce235de7aff3d1d1f1ea9a52f415264aeef1f8f073e34b5c4a7e443d3d97547ffec1e3033beed3b8d46230ed0a7ccf48130bef1b901e74616609581b7d572d92a6691894f43c78cb703940a37f5eb42555568cac139bc92c13e9909fc5132a92a5c355260eb008ed122d82933130f6a094cea00989056016cd17e03fa340174104503f8cc39a95fb06596055dfc79f9397d9a22dcd49524ea9779ae6349798aa99523641ba01671f4c206fc54005a4b1b9d0c9b8e8e285f6d645257977b87ca2143caca8cdb14e3904cab0b875ce8459845e4e13b0c25233f393519ed5d34415386be33a35d7199e24bf848a1233ffa46205dd889fe38f946bd21a13763cc4ad461d2853e74ad56ed0451b4007ecb78421ac6bec0590458e8c2e7cafd325b854e2488be2c0bc5a421b729933b63a0e225bef7941461873ef1f9785c5ff6176d89ab252f3e2f5d6b1a0e379528837a7d5f420ff01d700947fd840fd9cc008e0e2068b4b1b9a998dd2f6100bedfd1b58cde8000cbf1e9bce5ac81b42515452975263357f24289349847f073d9000010004944415494f397cb45d6b947be54c32215ab8e5e05c9004855e1e49327a30e6ec0e63150e6192aad7648c41bb6401ac02a806f09c807f0c541fe04a29ace169ae5d5900182d4e565de369b713e133f4ebca05c037b2ca9bfa52db38271748cb7a5f68e3284f4b1232f0eb173483cdaedb0303319750ae91863d7611f00483d1d80ddc5f8292fca981807621131be551c21611f8426436f097d94218fe2ea88878d1176341abab0936167a668477259a4451aa300ea8eb822df9372485cd874a21f66c2c132ea8cf642378774a708aee11dadde77ac42da1813061df572e3938e365198c9e97bc978530875459d9ea23dd1a49174eca5f0b14529ea3299daa888f2625bcbcc34d8dcd6b5a79fd570635325676d66a6f575f93ce0976fc84ffe8857c0014c79e29073ae54d47263360b830450451c4c5051248256f121a8cb429e8805a88078112f0ad30c06712b59c1748a2c2c54012225b6592092ba2c95525272ea0b23b0c46402d53f7076f4a9812c0298425a3a3263e5713699eb743cd3743e678b6da9c9bc81305a48a161f6dd115fd2874613f6d9c79cc99c4fe70a89154a949f2ce69a370b6c10b67018a2829402f0a2cdd87e137da1f76a219e95be51be00c5d04b8518bc128469ccba97905407703608189acb857d17950750762d43ebc410a448cb942fea33335226b94b16d2612b35176397858f5c5614803e2e32cbf544bf1aca2fa934fa1944b9a4bf71e630a7ec8a30853f1a2d208d2576cb8e348f20de392c9275a457f11612e8a8bb4568479299c9e957597a9e00b89be80d3a234f88a94849059f8596fa1b88deccc4c721e78971bbe83bbaba5fe9e31fffb87677f7d4d16fadaf4be901bf94a3be04833633794aeaf56a9d9f1da9ac2a668a95cc81802439e0e19e00995644e5c933103480a799651059401ea0b82a00a5aecb959dbb524ac44dbdbac87965919428c34d1d065a75849de20a000f806901c390863a63cb6cc699c7043298400ab3f952e79cf04f58794c912091f3e942e7b34647e7533d3a39d38383533d383a233dd129e54ea7339d8e263a3e9fe8f074a4c3b3739d9d8f35463fe5ac67329d42485356330b2d6823482b7ad4028a01e6d1971090515d0cf2877edfc18b52e649ce58737e143467389d3afc038e7e30c65c2f00da01b89d3176ac5a24c6bc9230ed56841836618b44db2de9396430851c6790c44a5a4d42473b137c34a5efb13a3b1f4ff1d14c5308620cc99e8ce710ef5ce1af2cd84538c7bf738879497d414a34f5837741227a983cde5b123ca2c4cb377399995a7c633139288a180169653db90a21a1c4bb2f98309892ee3fb8278acac34f5a5f97d1037e1907fda48fd9ccf210e39b7d0690ca928aba0628bbc00066c32da012c0265616989aa337c52cb761ab2b4108cae0b6a4a8cb5392513a2567866a88733e5266a9219702bd0710292e40b6b388e43602241bc0b0a1de1659100fa08b95c731e470c6eae36c3253e817e4cf20907356250790c2bd4747baf3e818e238d783e3731d637b7036d6fbf70ff5ca3b77f5c6ad077afd42deb9b38fed910e209b63cac66fd13f3a38d6c9f9792692055b70f1af571bc0397e433fc0b26d971002ab91005bfa953bcd3873c70d42c56f5d0e63f42bdfc5d0da4c369d3a7c0426e3aa0e099f765a42944dac84a8b3419680f212bb18d714a28c78ac2a96d8cdd11f9d8f74783a860c660a9f9ce28b93d1586741188c77c4b65efc6ff27308f618b2dc676c0f4fc63a1ecd28b3d011e1f1f94c21a3e952b14a0b3f2ef16510084d28f731588d019aadde5f99201188b264ebd15d4c2e204dc61a04dfc1901d852cde3a61ae07bfc4781790534bdf0ddb317d3c3cdca7d60e59df97d103feaf0d7aad78623cd00060e6494f3df3ac4e4ecf356776dac904b62880700ea80a729863d72a3e0a2e034d968044cc62c31166c6ec792e0b8220cf098334069c7df4ca52655108fe909b618e7452175f3410c0d380605900e8683f807404411c9f4df2ec193575549225ce6a9ad5aa02923802540358efc7cae370acfb8713bd73f758df7feb81befff67dbd7bff446fde39d47b0f4ff5cebd23bd797b5fdf7eed96befbfa6ddd7d74c896569349e79d5bf775707cacf3d199a6f39966f3a966d38966e373356c7f2d492f1753407601b12e9106526d483310fa4e8401b5b200528073953602f263b801b6e8c9cee51ab6e25afc1a3e5c105fb0ba58402223ce76f26a0b029bb2b29840260f8f8e756fff68b53dc7cae9e1f199de7b70a85b8f4e74ebc191deb97fa43718d79bb71f3296339d8c173a803c0e4f2784231df04e4710ce8cf77aceaaece0e41cffcdb28ca97fce6aa5c1c14106bc12484ef98a7758324988f3abf8c389c60a2cb9292567fcadda18379f938e428cf283674b0c4f649bc978aca22858e10e2fecb5be2ea107fc128ef9d20c39410e57ae5cd5c1e1a116808ccc953c212ba030d279f540d802840d3367d012c268b394a992cbb4048c5abc66eeaa00994195b435ecb38555b10d9200538b5c44c409208f885025419725f6f70338c76c4d9d4120738074ce56cc143961163ea78d29b3ed317967ccbcc700eee968ce96d442c7104a9c931c9c8c32c9c4aa6011240710cf00e951d84fa6d130db3c0b5625fb7af5adf73405540f29fbe67bf7f410a01e8d479a4c261a1306912c29db32e616905d422e01fc0da4d272a6d22ee7eada85ba86306c20812e06945be9d4c1185d100760db32962604220ee298b39db440a6d43f651c33c63661253886b8c6b329db508d1e1e1c69ffe85463c67c8795d6db771fe936e47167ff44418eafbeff88713c629575a0771f9ce8d6c31388f1085f4c15040c3740864be233c5b65d067dde636c77c58aee88718f20a519fd5a30be86beb6f45966f45df2789765520d09c00bdc5ddee62cca021313064c34da1c7773e5af201999a2ac2c6977674f3b3b3b4a7cceb4be2ea507fc528efa120cdaccf20cf1f0f000d09cabd7ef2b01162d50d104e801f29e12b3c75666be0af14b7297a794cb12556b9d8ced0e97a9c8fbe3a601e721bdba02384c6614ca7727cf89ee22d551e74a0277338802a613648ccc208ee0b41924b06c4d31233f635f7fc661f062d94204739db24532057ca3c20e10a7424d01dc39c43047bf60761f6d56cca63bc614abacd3b353cd3803b9b77faa776fdf0364e71964efb12ad93f38213ed5195b445176b69869812c914c24905217db5a802d54a88e3088420274735c74a141dfe4705566c18c7c892cb404ac9b2011fa153fb53686d402dccfe247a901f3317d1f43a087c7a77a08791c736e13a478ccd6d4212baea3d14ce338f7616571844ce6ad962ad075da3f99e8e1f14407acdcf6c3f66ca6a38badabe3b3a9cef1c982f697904584b38badc0f05f6c970581c75842cc2cbfbb92cf435d96f9bd3594eb7851b1aa505c908d99c9cc72bef83010934c7c82a4ad9d6dedee5d515dd53243a9f575193de09771d09765cc1d20b0b5b5a34f7eea93ea55a552fe46070c7140f204104a6de72aca4ac657cc2c3d250043e085a9ac7bc493629b23e7759d06d453431e32c9f93273e56a2580a5236ec48803e82d80d45226640929cc218700d000ff25f901906790c6c3e3911e1c9e411833c5eae41c90c5947a68af57b1ea316d0f7aba71655737aeed699bd5cf465db0add46a7c76a6962da3e4e24ca6500b809f8f270a929c03a80b56026555a951d20987ee31439f00e433640ed04f5911ccf336d6020258b0adb554cb6aa30932a1ff5d964639845ca2fe36c8046901ddb08d7400f08255cb8c3a2790c59cd5c704a23c657575ccb9cc09648177f279cc29fd8803f218676c27ce594e1c9ccdf5083fdc7bb8af590cde2b568d8d1a8872ca18c69c811cb075f5e0e04c07d83d606bef8073a109e3085f9e0489b07a1be3bb3833c10dacd65ab6ed168a15c812fff32ac40b92b9432089adc3084dc92dd4bc346e8c5a4968a4fcecc4ab92338948108e993119e9e92ffdcabfa75ffaa55f26de9719d688d6d7a5f300df76976ecc9762c06696c75901f65b3b3b7266e99e0f4c4d15805a90667f46038039c03100c2dd552657c1e16a16e2e605b36b800514a9cb421bfd3a93919b4b34d1411b4604dc515c661641069d208e967201e653561a736406c08e00b95366dd711621803851a62c4b6d0c36b4bdb9a9ed0dc27ea59b7b3bda190e757d674b7bdb5bbab6d9d373bb037dfce68e9ebfb6ad4f3e734dcf5cddd3ee665fbbc39efab97f3dd5906005281648d4bba0cd58953490c264325603e14c59a54c01df295b4b01b00bf222bf61a51324113e69e95b47ff5761ab882b061aa4425e1b440352479925e5178c2ffecdeb886db211ab8f39db62c7ac2462e53127ff9cadb37dce398ecfc76c5f1d432673486daa03b6e6cececf3465a552f20e1cdf267c5297951842f67755148c2fe187a1aeef6eeac6ce501fb9b1a7a7ae6cebead640d7489745525c73561f334867c95866f4a98d31f09e0824d8c130722af69454a4221348a24d21316e23948cb6996c7492182f6f5b662663e2d132f6a3c347dad81ca8c4e758acef4bea81f85c3c31435f0fe45ff64007d871ab6992ae5cbb29b79401a364efbb03503c25f216ccd61b79f20c1001fa2925a5a26046de080801348cb25255b8eaba5405d8070005b6882b4233939991129c40ed808e892f7441209151b869a357e90a44f0911bbbfae8cd5d7df2d9ab10c1557de2e61ea4b0a567af6eeb134f5dd1a79fbba15ffed84dfdca279fd62f7ffc29fdc58fdfd4c79fbe0a700e208d2d3d0778461dbff8d11bfad4b3d7f4fcf51d3d87dcdc19eafaee86b60695aaa26045b25c01307da62b6c593519b8e700eb2c66f76cfdc459486c393510424bbf831856be0bd258498b23bbc823043f1923fa485326803a93084435863cc690d21987ccb13a5880da679cd14c58911cc52a816db9312ba458bdcc20b039fa0e42db19d4bababdc1eaaaa76dfabe0181f64bd3469db44bdecddd61f6cd53bbdbbab6bda92b5b1bdae8f75811160a808ff10481c518eb2aa92c5c89849bb3a5b710ddd607177ae3dd14bcf3ba4cf95d2beba80ac378b73271777249eeae963e3690a0f30e93b90a8827932f7ed4fabab41e88cfc7a51dfc933ef004116c6e6debf98f7d5c57af5d531fc0f16480e85c1d285800aa0ddb27023c02189cd0cc64ee0ab08c508064493df187f56a669b5551906f8a7a6452161e1dc0134412400b0c292500ccc3408a6050971a008af1af729f0fb2080278ea2ac0bfab4f3c7d451f7b6a0f02d8d247ae6febb96b9bc4773211ec4236bb1b3d08a1d0de56ad1b57b6b4b7b3a16b00ea1e33ef901b7b5be877f4f4d55d3d7d7d4bcfb23a790abbeb7b9b7945157f4978d82be9539219e35f366cef2c1560ff78753267bb2900780948c60cfb03615c2d2410fe8ab0c56fede3ad2cb6b01aa4a5be2512752d5991c4ec3f66fe63b694e22f1acf33510588778a1f2536eae4354070499b55a1a7e8e78dbcaad820bea59bdb433db3dbd7f35736f431fcf1a9a7f7f4c9a776f5b11b3b7a6abb477e7f35c6bd0dedb19db7b7318474069049a592f115bc882242de8199e59f485bd03f9924d2dc325e4a72c7272e97b298f1e6e85bbc4b114aa6d0991162eb29c9090d39393c51fc45def087d6d7a5f5805fda915f82819b59268d5ffae55fd2deee2e2b8db9160064cc2b1360d0018605a450441cc08c2e22210000100049444154304f4a4589679c19672b1980c24a2542a7ae92596d5124b0a5434ba6c44c1cb4e2a09d33f930431f799dcc56f9f12c009cc24d1bbd2acfb2f7983d07f06f92de6476bd09b1c48a611390dfec17d815aa0b632bcad4076083b80a73c8a000744bf5ca080bf57bb5e230bfc266401d9b9b7d6db30576fdea8e6e5edfd5d3d77674f3da2e36b54cf415b027c8779c3dc4f83b52a3c90cbf2c58712df151a325ab8abc8d0519a86bb008ab085a75e405c0b6106bd875840df5867dac6022be248d9b156d4cd92aa390aa3289e51e7d4f9061a5a7ae6ceb1988e359564db19a7a1ac27b1e12fd28e4fac967f6f4179ebbae4f3f7f437f01f9e8cd9d4cb41f232ff23fc60aede6761f321966fd476e5c51acc63e01c97cf299abfa2465af4348fdaa6215e24aee8a3e35d1a13c1453bc13435ff23e8d77931d24679086a330e2e665cacce97e7c164c6608656e3cf5b47ee1977e8509c1a68aa2a04cdc6bb98c1e884fcc651cf7a518b39929410e043a3a3e00241bbee14bf5aa5aa001b7cb53525c6d90096012f12e4013526841c1281b209adc559625e51375ba92032ce2327198dd2930485c26be28041f29a4a0fe941cb0af3404f0fb9cc9549043a240e81d5bc330c03cb9723dc90db24819e4a8524ebaa06f4572ad2429970d3dba1a10abc82f696b00910c68676b63a09dada1aeb25a8995c8c6a0afadcd817a75411d491de4d0b1b298b13a682185bc85c5785bc0bf43dfd1279054f083221ae94eab2be2e1af0e9f35d413e4b1248c78fcc0419065d32c240af78aa4615d6a8b3e6d0f2a6db362d8dd1ceadacea66e4022cf02fed759413d757d4fd7f7b675fdca8eae12ee911ffddfc47e7b63a0cd610fff550ac2dda0beed21ba41ad8d7ea9cd7ea1dd61a5eb8cf559eab9b1bba5a7d80abc467a039b324f0a0cfe6a39026114dce24af8beccbe2b209a84a615af0fdee864097b62bcdcac134ee023a1e4ae96b17df4634feb53bff009b6346b9999d6d7e5f4805fce615f8e519badbeb19d6ffad85e914c3d66a5150054001cc6db7780425c0186b1766801d306300cc010f6e0869cb02a1cf24092aba46c00b9a1575ca6c7b11c9a99b8b37da2feaa4cb45b6429299f90c8378966a2d52e6288648056e803882395304c108547984c11622433e292185a06b5a8b3480e103a3a53a4ebaad006c0bbb5d1d715c034c07773d0cf645696498e7dd4579505f5392dd38f1830c0df1286d0416ef4bab8220a71a064f5d542442d80caaa05f2896dac2097e4ae98e1533d84d1d30eed6ff66bc0bed60e24160432ecf7b4c5d65310db6690c185f4ea123f5559c2cf15fd8c30c66366f4337c06d0d3c7e42e93f049a7415d6682a9dd043f2bdaeed7052bb892f7e58ad189cb7289a8433233ec0cb2f61c7aae0f0bf49e929cb06d4dee4932cae0a18683f909e73b4787879a737e232e333209d7f7e5f3805fbe21ff5c8ef827d6290f50e01b3cfeec44e1a512e0df70f059b29a304f805f0b40b8cc8c196a473f80f00872ac452f39c05d144975497980c5a83332dc4d0254224e84bb93b994b077c2c84e1cb616c995dcc93325ea89228acb8c3381a502788db459b4dd01cc0d26a1e90811c09cacb09051697257d4e9b463e652984a32c268dbcc686f5557ee374452f70ab65c6ac54f6a6d43221bfd8aeda44201b289fe3965c415a44173eada561dfb72f1e7edcd4c24941ba04744f233c822ec5b566c71c01c243dcba0da29003bb6e88675d290f6a39d2dc86ca357e7597baf1f44d6531569c8a42c0b15bc9baa28945292bba9088938cd139533de685ba4c5c50879d296d11a841269bc95753851e1a301f5c7ca2fea8f3af2dfba2212f53be34aeed8457baeaa88b65deea668dbdcc5624c66a6e42e3784f7197f81d76cf54316b112d3fabab41ef04b3bf24b34f0a3a3439d9f8f55d5b5e6ecc93b40118092008692ed2c031802281c588c7802500d10893019a0815d004aa4dd4d59d0c587c72d9ee0ab4c42a7b8ba2e9e240d216e39491c8803e81a56384bb68a1a66b31d9b2a513a7ed1ade1a0f703400294db6ea9961551483e5fb88877510700dfd2e996306abfe806236801bad004b09af217994edf0c3551c558ea54a92a934a7c91509a1bb9abbba5de6e15e519fa8e7a91dc166388147da073ea3850ef18af537f89df6a00bf60afa75fbaeac221a832b71340deaf7bf9f7267a807a8fadbca22a5540caeef89872d1878e7ac21f342c3aaf36fcc0b83bdae868bf252ee2b1b54437d55ce8e81d138036a7c3b62aca0bd0ef18af544350c979570cac95b28fa23d775388780f4186e6618311e9b248ca2b41da3373453fb73636f5b18f7d5c7fe92ffd7bba76ed9a12fdd6fabab41ee0d37269c77e29066e66eaf506fae4a73eadeb37aecb138002702ed97209c0a801b216904a004951142a003d230e66c83dc9884b26075892bbcc10718131469e19f17c778a684ef388785690671179aceb04fc22005f0389443b1d8f165209928878433cfab7982f345fcc354362bb64b6586846bf17946d910e90eb56b551211503dc16ed5d848a76a95b5c66aee4f43d42402f80b1242cd0310c39361998a957d42b2a32339921e4a5083134ea33d22104ea40f12e409d7e78d48d4d8075afaa54154526a88a780d69a412c2409798c51b768e7f1fb7db524f9cbfe4b1d387f0c5927ae3871e42d730ee08c34f2dfa0e9b96700909c77bcc12baf0297d312a76fa5c2657af2e55d176228e8a6ee32b9e115ff5d9e56e7214668c2c6e7395f8c73da615122ad5bd4adb3bdbba7af5aae2a7fb12f966a60ffdb51ec08fec013e663f72d975c10f81070290b7b777f59ffecfff336d6ef605fec98b04302f80994ece27207e7c3580c2dc94120a8c3a20234049589999dc93cc4c26c9b033cc725c71610d68c7ac38a7a2bc99cc4c8ead139ab8d02b47a89578878aeab59acc776a01c025e4112431992d143fc114bfec375f345a02b05d14a62e19a076514fb4d902f85dae88672745bf09b0ee728ec80b7303f0a22f21091fa448bbcb43444889d56d0a60cdfa02d06795e621a950e75193565774dc562d85ba48be220cca5445a1aa2898f9572a48bb27babd122a57d719d2aa63cc1d4440847e93a6bed8366b20f5209096fc0883484296ac78220c5952aec32f412e4b566f4bfa13bd61b88a7262f4467743121d2c19b359282433537c39fae8b7099fa289742a0a894a30517257322727b63b3b455b376eded48d1b372edac0747d5f5a0ff8a51df92519b899696f6f47317b3d3939cbfbef0108bdc100b02b351a9d031d2d40918469e018a1ab283d6f8764900168cb32092c511012184719e5abcb4f1ea69c478cf2248898598e13e4908702f08c3cf029c703ec1b802fc030ffc98ee95c1364369fd17e87992bf6ed2d499e2cc79b76ae6537d782b069179ca34c21c499e6f1cb6e806a4b7d01a0516f07c0864493216eaee8879b29b92b01aa05805910c67656e1491e7a176696e3e6650ea38cbb492194374293144214b5336b2fa83729e1b3e42e436458982bb60e23de410a1c3e712f01e12563649ce1d464407ea725ab8a785fb1fa8a3fc0783e1aeb942dc8b3f124fb26ce5a96ac48e2774a96106e1e2b2b0f1c2ade8262fcf19e96e88cda13ed27a77d3ae95824d11f42d4f2d0b9e750d1af6cef32a3644e4b45556a63674b1bdb5bba72ed86861b1bea7386539665b6a3aaf57d493de09774dc9766d86696bfc93736b634186ec9d9fb17b3df008b3666b380b50390069074b0c112f0ea00e070104573d93219b350092c564a7c644cf9328b08d1085ac2c7371539a064e63233c4734e2b8092bc0c78805303f805712c6873c6c17e48ccac3bf2cc8c327488739000ccd174a2a3d3633d3838d0ddfd03bd7fff91deb9fb40efde7ba8f71e1ee85dd2ef3f78a487874790e2480b0eb357db3db41ad5d0f9d87eea685f17fd70c61ce34901f6c9e5be9232b6f1643233893b5639769117b6d92ef2c8eccc155123f42451a53045426fd19456f9e2ead4e1db8ed545c3b89610e0643ed10924be7f72acc39313ed1f9d109ee9e8ec5ce7f1e7502081964a5bef147f7265c4fb3a1e4d747c3e217fa6e97cc916df5299509a8e77c46005f8f38e93bb88c6bdea9739a14352b95b8acb431766b9ab6d9e6848d879c1bb2eb42254e18285eaaad0cda7aee9faf51be4a52c5a5f97da037ea9477f49066f66dabbb2ab679efd886e3cf50c7bd93520d2a865b66e56c813a002b029430d4f80c7cce411025e4e1ccc8544f8b8445c12788caa5b9500ac3a741fdc173691b6785c4847a196761a08a2a1ed25b208120124834c56661d20db313b6f747c76a677efded7cb6fdfd24b6fbcaf57dfb9a3376f3fd0ebb78ff5da9d89deb875a8d7f39f3d3fd39bb71ee9f5f71ee8b5f7ee13ded72d08e5f4f44cf113672ded45dd5d90071db2dc6b0b1531432463ac7223c2d82eec8d1ca034fbc1cd5621c09ae3a4b9b1082b9348b861ed16d1ec9b86f6700d792e0b7689f143da4d3387e44e74e7c13dbd7bef81ee1e1ceae06c8acc7404399c407e2790c7c968aad3c95cfb27e73a399f2ac8b504c42b5604710e743a9e690ca14cd9e25be4babbd57ba1dd16a11392e88f4b7ed12f711912b7459e895553528a7c94d639cf4e2925ca309e8401754d27339d9e9e6a369db17d7553b1fac0707d5f720ff8251fff133ffc00cd90e170537ff93ffdcff40b7fe1d36a33d8b4392caa4a0e48b4ea2015c093d0dd95f04cc51605b8428c9b086ab90128d808794c1ab1fb12690148fae10bd30e040d692f40b9a350f4a721ddc48c99be341048e477e8669c7dc4ffc978f9dd7bfad6ebb7f33f8f7af5bd477ae9edbbfaceabb7f4b5efbead93a627dffb88befeea437dfde57b5a6edcd4db778ff5dea3b1de7a30d31b77477af1ad87face6bb77407805e4ca7ead8f2e9682b24ba18631163314f8c3f46dbc9dce4e6129944235b326ecb0f898431d855d24885a6535c8e3224e234430008c7b6585dabacf1b185871a2d1633bd7bfbaebef29d37f48d576eebfb6fddd2cb6fbda317dfb8adefbe75ae6fbd7a57df7ae97588f1a16e3d78a83b0fef69341db33d37279ce91c8299cfe6ea954903b6216355328b3f8d8f0f577eecd440d2c6dbcc3dcb0fc9cc181b9d90144f1634f90d463cb92bb9c7b00945d8e5bc8e32b17d9892696b6f4f1b9bbb1af40732333dbecc7e107fac5b873f3d0ffcac5be2bbe567dd8575fb3f0d0f5cb9b2a76bd7aeca80069084d0018b02b068897772be8c5891920213126100bdbb6167728b558a40c1402493488b2b5204dce83a24c75621d17c071c45a5515fcc8c9710c59243dfd97ca1d1789aff3fc7e1c948efdc79a4efbd7947df7ef3ae5e637571ff70aac3d346fbc7533d3a38cdb3ed83a3915e7ae5157dfb3bdfd0c1e9488f8e8e74f8f02d75ed54c37ea15456baf6d473aa36f6743235bdf8e67dbdf6ee5dc5ffe688a11b5d5b75ddf298822ccd564a23c3186f7c5398994c1201627299cc4cd263e9a80e6f321633434b1a765cf98374512a411c4555cbcc39a359e8d1e1a1befebd37f58dd70f74b0d8d69da34eb7ee1eb2ed76260d07fae5ffe83fd64bb746faea4b0fb4bfdcd4f7de3ad06bef3cd4f7deb8a397debacbb6dda11e1c9ee6ff4eb87f78a2095b5f74972e999690f152cae4d1c9145b6b048422a03f109fe8873923b1484b1ea19bcc4cf9eaa49478ffa23c2bc304cbb825ec2f1798310000100049444154924ae2d76f5cd5606383ae0ef481df72c1f5e3b27ac02febc02fcbb8cd2c7fb3d7cc84178bc50a48257449655511ba92bbe210b960efbf875d5114a298801199919f5c9e814632b32c022c332649027778869e4026858d08c9084e2108f36c17b3f39829cf978d666cd58cd886d93f3ad7dbf70ed9963a601571aafdd3891600e2a0dfd7d5abd720be1b2a01b641cfe5d4f2dedbb7f5e2b75ed4c1c3471a41226f3263af2435ccccafefede8a31ffd883ef5898fe963cf3fad9d9d6d3d3a1eebee8323c5ea2300d23dc9137515492925c515e38b30fa9ac38b08a31043174392d176e49985568ac0c80c622445dab314acdc7accd4fb834db67aaaac3b3c39d3375e7a574713e953bff8cbfaf45ffc4bdaded9d3e6465f94d2f75f7a55bff7fbbfc519c8231db365f5cedbaf6a3a3d5790c2c3a3a94e97030867a9b7ee9fe1ab13bdfdf024fffbdbfde3334d673308a4118b107ab8ea65f4e96208d1b5dc87e8aff2b5ca3199642e77c4c2b3a41517714c3ac891e151a734e13d6db0e279e699e768a7655c6518aee5927bc02ff9f82fc5f00320025036373601e42b9ab34d54a442fd5e0d1014aa00bcf809a41c12afca4209e4e09605a6f0700026c42c146283445c11376c4c3c1457ce067c24939949013f304d4718e401efa883553a01749e2424d63fe6858a5402a6521f607f6a67a0e7af6cead9bd819ebd7185fe146212accd41a59d414fbb83be76fb95f6863ded20f137a64a3afcd4d34f01ca3ded6e0fd163b3d957fcd9f3c3b349262ca73dc7cedde5e6d4eb8ab899cbccb4fa728998488798a40f049d75f43e14e2622c3c5737c512e45b40cc455d2b7c2ace3c0e38d8fffa775e5735d8d1273ff1513d757d4bd7afee28febed5367d37153a65b5f5f2775ed6d9d1a112c07d7e70841f5c35f50d06437de2939f6265f5acbc1eeae07ca1db0713bdf3e04cefde3fd2bd8333ce48269a721eb28098298eb7a34b160f9911d26747eb68cc486751f6777217778e2fa3b0499e5c2de7218c74e523decd947399edad6d55795565d4b4be2fbb07e2f374d97df0c48f3fc823a5a438f83c641b6563b8ad0db62262a61cab8d0469447e02b8f3594427b917004f92c933e02fe30c01000a22e8c83733716b4510ab8027377a3e5566469c3b0036db1347674e9d45a954f664809217957aac348204ae6ed4bab6d953845b95abb6857add4c3bbed4b3d7af2b75a576d9eaf968fcff8fa7afeba34f5dd1f3377774637743c95c2528b85b2f65e3076a4e1f68313a56d72cd5af0af6ee6bdd7b7428d3ea326229d11737b945aa13814c5c3cb8733c66e11d53fb2e9f2b28eb8cb6ec625c0a435d5c5d84965551a7da46f17b13771fec43681bfad4d3bbdad044edf103a5f17d0d53232d3bc6d457fcb7c5ebdb5b7a6a6743d7b706dadb18aa86e4e7b3a5aeeeeeeae6956d3d77755337366b6dd6456e63de4a0b407eba582a7e6f66b6986b0981348c79d5938eb139b62633436532472cc922c523ce74709b12fa5006499b4b8e327e1f45285372edee6debe9e73ea2bdab57f367c7cc64665a5f97db037eb9877f39466f664a8065cb12e0ead5a7f41ffeeaafaad7ab0100c9f8ead083170a32303331f58434001f802308a30df00460ba600e48044385594e13318093525a294559e5cbcc0022212e5302cc12fd28942010a7be9282bdd2f21f1cbc06707ee4e9abfad4f33721852bdade1ae615524aa6aae8f4e9e7aeea973ff19c3efdd167f51ffee227f49ffcea2feb577ff1a3faf73efeac9ebf7e4dcf5ebda28fdfd8d3e2e0814eefded1f4f49483e642d7f676f4cc53d799f55fa1be1ebd675ce6727799ad443209c9c38b2856a2ef31106346deb18a58910803c55796f329c11822da618b9a814b6dd8421c7168df12ce38e7d9d9dad02f7df223ea97ae1e2b0a87909aa37d7d6cb7d6a79e795a1fbb7953ff8bffe097f4977fe5538ce769fde2479fd65ff8c833bab9bba7bde1869edd19aa3bba233f7fa4a12d7473abd68d8d4ad721dbdd8d3ee3aa5450af184fc7bb1257c760dc4c859b70a1182e620ccb442f112962e10737e7bda42c1ef5304c71159ef23b13f5c4ff83f9f4a73fa5e79e7b5ec3e1105594c6687d5f6a0ff88f3efa75c90f9b07e217c0fe93fff43fd1906d9d22256508e88c3020d1c14290c380976ec9d05a05710410a11218832e6e9399d84eea084d86aa43f20d68e510ad99c940ad100f20a2bd1c9a016a821400d3ba52fc71c1cdd88adade6096bd03795cd3c721914f7ce4693dffec4d3d7df39a6e5cddd1b52b5b7af6e655e49a9e26fdecde863e72f38a3ef9fc33d83fa38f3c7d43cf3f73535b9b5b90cfb6aeeeedeafab5bd4c1cd7afeeeadade962274daf7e85784f4498426092e93f0007c11bc81409d8c27c6dfa2cc63bc78840ed33097a2b0221a9908b62de544bd54424eabdd9d2deded5dd5d56bd775edfa4d5dbff98cf6ae1192fee8479ed3273ff6517df2b91bfad5bff0bcfea35ffeb87ef5179ed5a79fbfae4fb0d2faf4f337b45d9b96937319e4b4d1ab7495f7f7916b5bfac8b54d5d63d5b68dffeaaa547297e36b932948c125453742120f37a13774212e535c461984fcaca0ef11cdb63c526179cbaa287a1a8d46dad9d9c96465b62aadf575a93de0977af4976cf057aeec6977771b9c68d5340ddb3b8040464e808fd0008f64495d1b7a57cbf60a31c536474a9e7f914cd8fcc06d104f140d85c50321343319d1f87079c4a91b6c534a1d6210884120495559004e45fe5b4d43807138a839bf40368784435d0178e3ff63dcbc7e2513c9b310c6b33776757d77535759b13cfbd455ddbcbea71bc8f5ab3bda8384f6763673b8b9d1d706e7243d48aaa6efbdaad280b4d39190940ac05452d78a2eaef880b1743986fe8390387a33c69aa311a2201e058dfa249359e8a98e725d2c47a837ac82a837fabd3cc65e5d661fd6a54374436d01c63bac3282d8373736f24a226c77b6868c6f4b7b9b9bdadec02ece7cd06d11bfb2bd0911ee28fe51d64db695ae72d6b33dec69b357a857154aee8a36931bc4e032496e119ae862165439ec501809a78ce30f238cf19b12f9aec8170645e1fa2824f7dcf31fcbdba0619f4954ebebb27bc02fbb032ed3f8e31bdf0093ae3319a192c9bd900310c9f828a08f7fff9a9c382098f7c04d72f20a4b98bbcc2823e530e052164f60077bc515a8d97532333dfe8aba93bb1233fe2c9cb514c955158532899405c054a820f494549257a40064c09de37aef1a15ea3813f07c9eb1d14b1a56c67690abc2aea05d33537207a04bf5eb5abdb202489d3e4b744464e75553419bc913ed958aab0b22a5ef5d2410d3c557143014dce644c28020462b39da78767273449452be8c74c72a64c9798464725fe577cba9ac59a8607557740bf972ae92b1c5aaa24ae27c67a98d7ead5d487017e2d81af4886feacaeeae76208dddad2d5dddddd636e41a44b2c559d00636418a43ca0d7a3d0d62dcbd4a6591187bca6d277742c337c217468f243393647cb922ea8c8f5b7407b5231d7aa78c21ae781309c39b376e28f17eb4bed61eb8f0805f84ebe009f780198081f4fb03a5a2548fb062565e26530a8808d09754802455993250300715b8ad0fb664286f6658ad6ea35ce48784faf1acd48c9c2c0a8b0c600152c95d89faa38d02202acb9409a400f012fd88159053ceb39dab30575c790b89f38416e962660f4047a8e8b32903668fb1f463b551959988528ab63c8ae73e84ada7a454147224323aea8b300b75454837b0a7529e8250a35c07c1b4b49bb5f4c9113360158932211d8fa8022aa564c70a6fa916c240ad96761ae21d5b500e4a272a4ad6a8b056851a7933e71d2c904635bea86194aa70f5903837d9ecd79062a9ba2c153e2b195b59a4ecbbba2c58795439bfdf2b396709bb4225650becdc5dee2633cb44e7846691961881cc9c7782e09b186e728f0ca196cb54ba694087ab6eaa0ed2d3fa5a7be0873ce03f145f479f700f18a830641be4a91bd7b551177ae6ea8eb60837ab427d406248581505405528b1ed54789299493235806807429a05ec74ca17591dfa88931501969d8c44883a6905604e7d08e09452844905eda4443b005742cc561f4533537c39cf047895e40510a614e54c45ae2329f401a65559b20d56aa2cd11549057605e52274c295b8dc5d455911269901e39090b856fdef44b388a1e18e448c2142926d8c1102e9b2401161469e518fd1460e49530bd62e99c33b1d04b214d9887d20e272332549e1e3f841829222ab3e9b700b6330958513ae6c9c7791b071c827ca52bdcc4da928b0f73cfe20cf905e5daa424af2128512efd519bb99e53e10c8747191e8889ab9c25fa27e8c787fa62457411b3d486eb79f641ce0df79f5059d9f1ca8c5375a5f6b0fe001472edf7d09476c66328068c1616c71fe40ffa3e7f7f48b3786fa0f3efe947ee9d93d7deac6b69ebfb2a11dc082093000ed0a500e100abc48804910c20a704d8faf0e65170628228ca80148c2c429636644114227e6d83961108c870e315be5139023b9498f812fea489e00335780e24a924a66deab78415e429c3284c9e5fe4392d001a6254493881b7901949906e9ec2a54be72bfcc735cf424c0b52304512542cc094c0c59224d8c27b1e8b8b850843fb2b29332f1a07606e4d49b823021394f0ef027a5ac97a278e14e3ae4429f5c61875a986549e84aca578ca562a555968973931aa9d463ebaa87ae4257659b4211264f9435b9bb2c8bd19e89873a239414eda464b49f18aa13ba7a49da285ddbfd4a57b7061ab2ba3bbc7f4befbdf65dcdc6e75a5f6b0f84073c1e6b79f23d10e07e7ab8afb7bff145f59ab1ae6df6747367a867f636f5fcf55d3d7b754bd7b7fabab2c15e7afce44de12a922b2ee36132b95da40147c595113522ca336e657d3c5aac2533a38ce42060e19eeb2b00b4a83751376a99a4a8d5b15de525ec12205664122b2e4077b562210f702c1221faaca3dea82bc4dd64e64ae48bfaa2bf110f7177c9e867ac3ce877f823488ca8d022ab3b13c06a20a8bb2892251e913266e95413498936b28ebaa3fea8330836447191cf8d99e53e79a26f9e54407e052410fd0f29133ac653168cb948aac8afd09511af4a95485555aa7a75969ab0d7eba91f87f311afc9ab0a25ca1594492194f71c3aed4b26c9e94c484e982e02939929da0abfc66aa75726ed0c2bed0e4adde033b233ecb34556a9b4a4fdf7dfd2d1fe7dde77abf5b5f680af5d70393c309b8ef5def75fd0e1ed37d54c4732f6e54b37b6ab12b3cc9eae6e0d15bf531033ce016034ac127bf0490970095051002fc06ad95dab671771a2019c6d6cef44fa42ccec02b05cf90b9035a7be00d12c694514005d510470168ab020ed6965e7d9ce943e2897e4c443cc25736a4e08e33033f985285f269051c6973036cbb148a98bb1c01c6dd32afa9ec7418e99292e231e61e8311343cb6412f58bba72b685151684ca46caed8b4cb4784a5c1d29c3872e73231f1bfa9ccc19bb2b48a104fc83101e9343fcdbe1aaaa5744415e0571f4381caf7b95ea0843ca8ab22544b39295df8a5c67c23f85bb5292921b6d1a61a27d49ac40cd947566469a1b9be8bfe53e99a2ecb02e748509c68deda1f638b4ef959cd4cc163a7af44007b7dfd5c3f7df647bee472610adaf27c703fee40c653d923fcd030192c70fefe9e1dbaf28c2e3470f351b8dd42d96604a2bf04601129bfd3ad96247c50000100049444154ff52dfee464f1b6c5df48a0014537c394679766e1285944198b30130497119560e1819e2805348c4cd4ca18ff2c95d86ace249452a5415a51268577acaa19b5393b80c7124c2c0ef8ef8c56d26bbb02396952e62d4ad9c67ca6d506f84a864d46fe4b7173f75d5c46fd6af9881ca45314647ba634cbab8a89118ed5ac43cdb65228db49146cc4ce2560e0de2a09e0b8222a1c757f86965668a3ec5383de1dfa25062355256f8e27148bc083dbe29584514846551e55542554014218c2dc836a48cb8274598eb669c66d18ed12dc2888b780871b3882b528a2bceb7dace72bfaad235e81510c740bbb175c5e7207cf668ff90f38f134d4e4f74c26769d92cb4bed61ef0b50b9e7c0f7400e3c3f7ded0fcec58cbc94c6727e73a393dd3683cd662b100131b9cd00120c68ae4023cd8b6a8d83a7107e4927388deca019e8ed976fc31c48e1220d00a234d32b32cc95cee1e0ad2c44db91cd98acbccc84f88c9dc24ee64a68827f31c9a91b69c258581b8d03d6e9368d63a1133a3fea48eb20a03d21db9d1cf10332305f6b3e2ea96801ee01e801844d1b69d5ac643ae506b651f295d3c24a35eaa208fd50adb57682451274a8b3c8fb8677b9364164f922d82df3bc474f1857d272e74e4cacce8bbcbf09759924302ee450e835c0b8822411e5e245972f42e99084d6e21e822a45cc22674c9d1091b0c3369998409659cd0723954b88a27374a3975477f0ac27e5568b357e6338f1e2b9da0c4e914bfe1278364c22ece40c2875a5f97de037ee93df02173c08fd2ddc56ca6d1c103a5ae413a55004505e0c41fde1b4f679acee60a52005514985297052b905a9becb1176eaa483b61cea4036049e008a0da2128e41228651281297fe574273300cd5d46884a66861137a199e5b4457e1653f2b04f7242339790084d2e8b38a15b9285109799e23203eac857177686da721f5bc0baa3c3210dab8f00be0eb688740e2f4824d212657471515fc4b2be8b1842c89deb15b6467b8e187d0d6c0d1155844d47bb6d837fa2fe70ac7e70e57c74740bff05d3441eb6946514d2459d162192d3163926b395847f1e4b4a2e0fbd240f219ed38466260ac9ddb2909211b788281ea177562f96a557997606b5e2f3210833fe1697e86b9952b66e21e2f83ca1d2fa5a7b203e6f6b2f3cc11e08b09a8e47ac3ccef32fd281588a3df521e4d0637fbd2e4a81750a32694134334005298b04899448ad989916652907b42517d992493247f483cb4c8e98ad42710590762b6399995c26b3c7428a3accd013267739628864921bb749861d3a27ccf9e419e9d04baece4cf9a2ff1da0d73e2608d4a8181fab87d041204194ab7c74146ad08774d806508abaa31db3ac505c5d3cd087869e10eb1065c9b69ee4d85ba77c4510ed863f73dd243a85d67299dc0e3a21a1ede80351d4913229ea525ca4893b51537c753223342974c9f1173e315ea09b947d63ca61ee57b68d325a959391673cf5c165b64a17d4d5af4af5f93c0c7b55ce5f2e9bdcebe4cef699e7cf41282c15b26850ebebb27b203e8797dd074ff6f841a6d974a22e7e090ca03193362eb6a7eab2c83f5db3391caa37d89215b556c0e3b20c1ae44332018cc94c6606c8b532be8474d4cdadb89cbc954d940dcd0a32812f7032c09a74871ebb286f16e06772372500d80c2d222e47cc4cae95ce7f28dea1cbc2cc9ecee494b8a22fb21fd469be8a1b793266ef7434c843f840c43f00f700efa88b5032455bc25e5c11985143d837cb28c6561763c1be65268e49be8d7ca36d33233fc6d9a9a59d58edb44bec89634277231e791d751942485d82f4c4eab0a3b61002ca13cb75662fc8cc90884bc41457fc58b6275702d073bf417767dc91f703c1da4419c9dd88b8849d99c98c3e9022a2843a7e71b1ae9cd50704818d3b759b14efd5cd1569a74c5196f82945c94b26ebe1feab1ee063f3afaad6e927ce03a09731a8808b02d2a891c23c7083996582446aed5cb9aaeda73e2663069a12d6004589dd80fdf082d508d6801a00483d401bf012b529d7212ecc73dc89985c027c241349498488b8cc885f48a83c1ee8ddd1134fe672f21362163a8980f6942f8b27801c4148174b872e7a440e61c484ce88473e5a75904380b9d0457e480b70b7e8231e76644580502212046dfe29ad161d37862d6582a8807d141226ca60eca60057b39526da69a923da5c72d89c894b5420c9cc9002cae814f545794c958744fd3942c20c3b8ad80f11954c1757273343f09eb988c824fce63934e22104327b1c932c7f3d2e6b1269c5d5492dbe60181a5655268c12528f554972676bcb597d58defa8ccf42dddf90259759d4a1f575893de09778ec9766e8095208e08b015765a99004003888e1ee2b122993ae6c0d54844e066818b352d3b05729b636e227af0cb0320bd058815f0750833d52c73344441f0325f130f55c8604fa48473f42a28cebffcfde9fc6da9664f79dd87fadd8fb9c3bbc79cab9322b2babb206168bf3d02445b229c9b2dd6a0f30d0861b681886017f31dcf0077ff000d8b00d7f30d4100c090dd87003861a9235a029d9a228a93590a2c4b15855ac2ad69c9555394f2fdfbbefcee79cbd77f4ef1fe7be2c12248b645566beacccb3ef5e3b2256ac586bc5bae7fdd78e887bef43174c6e0f945a25143a6b52f3eaa79179d8a8806b4557a56e9016501cc1086ec177e126e29870c2c337f8ee6308bcaa111d134443b581b665646b3cd040d37cdbd07a502baca3a9929a6ce5e98e8850969428c5d5f403c823636d677a2309547ad5c452d8e16ee6fdb062e49b4d72562b698b8b30f3140922de2823d6f5f6a41e118a5893b44eeef265935640c96d8e92a7bf2fb2dc990d71cdbb8ecf46d7e65232f90c143e0f49e228added3df975ef3ed1d3e1b459b6b13017f963651781747202234dfd995ba99a2749afb0db314454423bf65f65d2a87132d6ebea062b003fccc0fca59dfe9fcce0c000980e70c98a248c1d98324639f013da8870299a42b74f78a600c2065f06aa0085046d00f40554af3693571af1a60493c0c70e64730de631ab90bfd0ac1c696fed095e80c126044343e66a54add4644293c8559bdb7cf01f784f3e6c2567da3de84553d0e2db5354311812e1864b3c07204cf2c8ddf3c729bf8bace604dc4ce44455e89882d2a44e4d95851534b0bad36aff65364080476b99dafe1d11b685c3b010f9bc8c8e3e097a48f76d0364ff8d5f4bec1fb96a5c6575d4b067c1268102b5996b6bfdf5bb3a2222e7caf1411410229ea5881767c66b2847aff1e0a09a48d456673bfb72390efede9bf3766dfcf669a6defaa67f56130287daa000605804840a4b2bfbf3c39d6e91107ed516590737a28000722ea67bdfcf66960138c0af854defcabc31752962245d29534b8d119e22b02fce4755a5c67c215303405aca0ce08d7148c9702ecad3c0d70d54df9b2ac4bcbba1e4dbf396ab2e022d0e91ec69108ac5fbe5051dd891f1167fdd89c2c63a2ce8d642802dbd47c338c7685b0089f8ad96baa2eda431e91e1b90786d5dac110dbb7dd09e5751d34e250a1b3582019084684222089e470d65792badae5f854fbaf58cf2f3053bf65bbf27d10172aa42c8a4c2514f09064941a896bad478aa037f8eea2867b6d8b4a815de8eb886d3379d78edbe864a0121b7d376305b2abe04b9beb7b3a026f86f3f96628d9e8786747204b513fdf563f9ba924df7200c31e871f80907f526818068dd3a00ea6ff940552ea9135a82460019ba707500368407aeec93805de9827f950370258b57eb3280d6a1134e40b064504ed0650556b90ad4d576d3c5452a2c515a485de00e8c215f13ca3a00c89278232797c8479345d9e5545dd9659746818c7667362de48519ff0bf2212df223892db6733f71b393e890b497cc5dfb30464bb013f005a9754198df767f26bed1576951317c35a9d07167832c83152d8166dc6459849dd9af0136d342a3180e8a357726992d4dae2a22d5aeb026e1517e51b4f2abee1c799bf11eeaf8caaed9749bd5d59025fcc37e5da3aef154a8f45bc9f6f49eed3e67aaf47a07d26deeb4178b7cf3f22944e0624923c5b5544a48237ca56020a11d1b62bfa7ea6edad6d124d0058a39c4042be00924ae9376a978c07eb00d31043a1a0d33295ba20daeea8ebbaab3abb26007964ab6c12f267bca0be960938260a785a3315f86f6e2374ba17e3addb7d6ebbaff16884f80a8871a23e81dcab6125ff2e8813e6da7714711b1cc51511ce07ae295da76646c55fd09f6a2526303d778a093e1c05364c0ac6cb5491f5bd4e1a9544a0bb7dee8218ce6d5993ab94e8fd961e78ed864f594924d6980a0572b0982a8a821a93095684306884d6db6148369bb0e897822f59452be96de2ac45d6731a27cdbb8e95665126b28cf567c6141112942525fab658cd8636d72602129f884d18deed114812c56a790a7800d941a11078b0260041a24dd9f59d661c9acf673d4293968b951290a44be90182ad0687d402a0291063254584da055855c8f584653032d0ba6df60850adfb013a181108b9138a48dd6d45b856699bd6754404030f28e80f0c048c46151ed47453da66ab4f6265c55c568306939721f447040342133e58565c70843a809539c24fcb38615246c9d6e70820a0b0b0f5481eb226e4d42e772249ff04b04f24afe60b990acd743421115445a402b24e057cea153dd58d4c3324da1945522056e52ba209a38b36f54a1f0d6253dd4d9589af6b6a5d82c59c2824e44dd6605bcbe54aabc5524e9405fb3eef28193205ed08ea5daa94a288c299da3945f34d9beb3d1e017f42dfe32178f74fdf40b13c3d6d0061c0f48c230103450382c29b67d77350ce19490f7fe24d7de2a0f9e47401f80eeaf8944401bc18d83028420c6d60d580d10d496657d1d7c880059180dc5ccbd16b217155c8370a5d35b9f90635c6b774e90f8c33578c935a4db20d097f7870bff1d34f6c5779c5313097e5f25bbf6d9f259500a475d82f8368c4992eca75bf271dcad2c9b1caa48c22057226fc73611dcd3249a2f5e11712cd17f7b785832c612e3a5bbd6aa2c387ebcd3ed291eb3eebf3b8a41de2cb7e52fa963b4c8c952950c67d5608d3189a209c835fd12bb997ef1df2151f6dcf9c56b64ae894f3afc1e760feb336c825364b292a992a2515114aea3dabd7d9f63667203bda5c9b083802e9c786dee511000014d9e0c48f061e9e3240915099cdd56fed2abb9926c0d8c92322e42fff863af0a36c6d0f323ec101a3dc2ff8e6de05ab9270434d8f7988c93627c0cb64d946f6c788d7040235d1d86b30e76389decc2221277759365ca12f21d7cd833c1fab71695b02040d964e1e4b561d0b56524e22425757525dd729d191116d5e2e4ba622923644d2c852942515f04bd76b4d9dc2bcc00fc87d26897642f892c118f7d10cb9aeb66d265fe88a2cb8188c705f484d96ef8d27a0f515b1e6570a84e5427e9ec9d4b33a0b1a625be564c4944583bb4a6c0f7e6bdc6469b98d8070b1557d40df2a30978b45eb77fc1c37584ae673e6869b4a1a1d09646bf73c2b909dc6db3c3611c87b13828dd5b73302fcdb57ceb6a430501970aa4610c720510085ae9fabb002511681148885ba5234ef0b87ead1f6c819d9c0470d8e2a7548b2b80c742583166049a2702562dd5e3fe150f1b9470415c8a343686dcd506d7a431121051fcb28924271b7def48759c8d25fc5851623e2ba06bf02a667e4d5c730a3c4e162000010004944415469c596d9300c580acd3c57c8f30ecac21c1b510ff447a6b2d58bec4364511297203e2a9d6ac21714a9a49db4936454d0130a615c99a912a18c54ab3b29e383ff686525e6157f4d53f35642b4c905e31429c19f90af24f220968eada90906369068851fe892e0f9ef7f31ce49c1bad7a1e1e981907911c41896f5bb2daea06d15955875cca124c90d0666d1968a24614630ad493633a16bb6734efd7c5b9b6b13014720fdd8d0bb3d02a1f9b98baa80b281aa01084011a042965e11414218292b94b24cc9d47cd691443a92093c558254799aa82a78785c9060e8035c121036d1d16edb19b1e34646a0a7b8cac868a53229a9fba65f8d529166a39bfe0a09bfa5f558051008b8620e9fa9236a3b133c1f8e8f6cc58cac3a7cdee1e431028e0170f75d603f5500fc2e8bfc9bf84277e93b15c05328f6bc23522e9580699929fa6d653757404932c8be57903c023dc9ca242224412e938428c9c9a330be64cab64ae9da79d26a39b01a19d42ec7c5619b2a005dada191d01316a06c7317fe543320788a74654dd4abbf230ca89cb5380e560b8bfe4a7cd603cd9bb05385e01f20c77064db0a419508251441dc8957451f550536223c4e12f3d9bd7c43fd6c4663736f22c047621384f74604762e5ed518bc51026c0694cab423434159c795a6e542136fea023c04d76fa4fe91ce19003b9ff5326cc903c5e5c114ed6ee0128a30f0340e6215e21ddbf226d80637a1c5432bb292e547b5145083120b71973a3590c657f720da30b18d4592910ce319a1116034f98dbdf2e63e71dee115c7c0360e5d3240fac78b13f06bc498c04e813a561b9e6796a2d6874c24b683763f5361d5d6ed9c57ccb714b4b39f2b4924519c446612fe29d649430a65e69a4a5147bda7bf64a85006fd2727275a70583d91ec04afc2ab12b1225e94d625788a3053be5c75d90240839ed66c0fdad5b1ab68a16c31a68a42ba9314801a0781c1888a88c197dcdff420bbe06c8ceec66f3144d7c498111f2b4b94c4ff88502156d1f5baf2f0638a486dae4d041c81cd27c151780fd085ebf76becb654b363b6218304f82101b4e36aa16179aa91c373030778a1ae84fa2e1b59a63390004913f2062a93409ebbe0dc74c917600808b926508a61e0153c18531d5b3ddab85405a464210312b251f0cdf52c92fda46e556119c65437d84da9b429348e15d7485400de447d24010e24902534d6a20950c58812270ce411a18c5440d975ccb153622ba32820ebad9e1ff3444aa524f30f158053915284a450642ab3e051489401091b617e29b08a0a2b950e1b250bed600cb366bea72491e5e9a2f96d5db28fe854259accafcd3182ae500463e02b899505c4854c046df85415b044653d0ebec7d03fc16b5ded71a6a7d519882ec4645a2d97b20e7fdf4d133e365d8c0ff4449b57a830979115d9d5fb1fd1e6da44e06e04f26e6553bebb2370e9ca35f517ae4abc410b4012a85101929115c70ae0f556cfc8f68f01c49148d0a5180c2345557e53670828e75ec84c350ef805881b70d067f03189be35543184be962c28c5b83a6119bb61dd2d518482d2f29145ed8aa080289d2c504dbb02ed157b9081ae114909d09fd039d11e20aa0a744fccc7f3408b849e752925f3cf66c773836857fadb78b6bcbc05b65c9c6a71784747afbfaaf1f8407571ace1e488447ba2a925dc63d581c367e4c58516351be82a80ad29baa2ec21c7315352c8c9ed84b7fe2507d706f91a2240dc2d365284e55cba8332017f71e15f205329d7b1a0ffaced784aeb36c1e17624d52ed7fcfd4094844adc1a179d7498dfb3b5e7a18cd6441c2bf1ab13db6c7c7f302de14ffb9e44d1c5071ed5f94b57b4b93611b81b81f5a7f56e6b53fee911f81e95d8da3da7cb0fbd5fd36c5bc1764c05180472dc7deb1c0142d3c4db7b652bc88053e8078d7ccb2b902e0c3355063ebd7185b2f10d827ae3323855907c3220a1cced067cf0c02ee478024ad536a0068cf2c7d1849d6a112726b6b9003454343faca7026e133cb1a2b1e9099df61db7dff00d962a0f8f53451f36a2cd39150dccd54ad72b42dcac68467905b3a69596cb155b4e273a21919c1c1d708e71420259c87f1edf5b3f03e707c3b064cc52d3d93c15c19dca5294d829676544609018e1d3f1c9a94e4e8fe5b77f86a94d55ee235522a62cb45c59033d0dce99e857288255861977c98ea33b68afe76b6dd2e418d1e7b94d94136d0ab96d425c343427c9f55026b6f06dd9e6e4a4bcd663d91623ce7f3ef6133fafd2756de8e6b1898023907e6ce8dd1f8102903df2a18f6a9c5dd054e6aa67e00d66003695b38409201ce515c904d280498a08012b32384684cad9a72564eca9e04fa5df0d5122890c8a7517c82282158334015ef2d5060292e877b3b687f54c3250097044112a0ca0f0dd0fc3e26e55eaaae8b3d330a9a2bb42ebf1b633996f62acfbadd7e2a6eaf1e6d3dfe4406fcfcdc96760fb6e45f25cb11a5b72266420f56faeaf9c44d8725ab16258717eb1a05cb0edd3fa905d917857ab498bc54a23edf5dc3182ad2c85672899579598230f6eeb3e21892cd165dbf651e1e040f8f686209335b0b7be5c071f09055fbead531e074d0aa451ce44df5041c5ba2bf137e1c1b7545b9a1547c73775de17f55daaa36eb94a5c2a63c3066c179a5fbeae873ef0a436d726027f3002eb4fe51fe46ceaefda089cbf70510f7fec87b52cdb126f94d3d94c2b6062201dd8f231a0814572c2e978339dcd66004b915f810d303a0323038c002e055d90001cf34c11801940669de2aad41b01480db891c5a47b24640d6c815e9755a97589303c8f83817abc04d826af3ca0ea3a4b8ec9e43ab2140cad0abe2a49a0fa5ca4d1d492a3c1de5b5c4e129ee700f89f92144e4810a7a74bf96c6245db34ac0656082b39592c564b2d4830cb4683466caedc0f0d2498c109055a2e3c867e1291a7683f46e63ee258781293e4988cc384de41e60b705e7b6c893342de7c85571be1d0132ea4621dd7ea96ebe86c75da1caad0e20e8895194f39d6044e151fec4f85e9c5984baaa283bb6adef7da9af50afb82e0442c2ba97fb25e6852eaf18fff983a9f05b581f7ecb131fc0e8b80ffb5bec35cdab8f35646e0031ff93e5d7ce4435ab10a99a20013588ba0ac0dd00640b5021ac9db683f9f696b7757e7393f29241c279012e00e20134185a10c6c403531c6c9a39a07b55e80cb006f7db0182845b41e4df4556c4d0d2c3b290b6036aa025e0d0ce157ec785093815f6d0c9ef96b9a00f35106bc11fbf601b5e890874921e654210975c85612c9a4d56a6c00be60d5704af2383e3ad2e1c11d1d1e1ee898436ef316ac0e9c3c962494b65545b258412db1b0cde39589b7b82c7b82cc824472cad9c6112b8b05c9c4ab11ffeec77436c7914436501f482e4e64d6b56cfa5678ae46f2e500e2b7ab67c5ba0f7ea5d6c81dc441c4d2850483bb3a462e694feef0184a0a46468b4bad94c25e4513347a15c28b42780b0ba981e438512a52a8a1169a9fbfacf77fe4fb1511da5c9b08fcc108e41f6c6ceaeffe08745da78ffcf04fa8bb704da3415b8002c0107efba43e80b40611b04559fca3acdbda3d7f51dbbbe795c880332d480675bfd91aa85c82340014afd8ad0240210544a9a2cfba5c6fd065346b623c1ada035794d63379d5c0c1f4c40ac3fa4d16471509000dc85594dda589362e733307049946f3314acaf37169d911d03670375a0ef2ef632c58711c1e1debb59bb7f5956fbea04f7de91bfada332f6aefcebe4e492a6f10c9c12b14d331fc939658589990444e9d34e877c238a5b4bee3e393f5d909fa9d7c5624a9c12b1392c5dda4b122818d066ac0db73b08f0ae60081ef8e200164be4e08c44f74893e890a65b864be137dd5e49898d7be39419a652c7cc1b7325a2e6852b34ef3d1232ec7c94b1cfbe3e488844aa64a298a844aa7eb8f7e88c3f3ab486fee4d04fe7004f20f3737ad777304eeceedfca52bbaf1d8935a45af5503e154e9bb4623e072c21bf4116079cc41af7fbc57007bc95446aa84802ac01f39718163eb27c0649432188ede4271ffbab38121e8c50d3c019a15d9d6dd1e74b37556f12303e568275bc09ce4a645a6a6cf87e9ac3300de0af32e79045a157c954c75b9fef1dc02006629124a06ce261600bcb7a85c1ae4f76eede98b4f3dab7ff46f3fadbffbab9fd1dffa579fd2ff97f2df7dee29bdf4da2ded1f1ce9e8f89815c9b10e490a4e0e87b48f58a19cb0d23825319c2c4e75487beff058cfbf7a4bbf87be2f7de359bdf2ea4d1d72e8eedffb3821e19c9c259a53e2baa03e30878918a85dcc13bf5b95476313b74a85026cafd05907f32688dc9e7185c9cdfc60107a7813616395539d3ce057620a976e9e4d1fa5259b1ec93e4cc4de31395d2ee42d3a7f0ffad9fab320fcea77ceeb910f7d4cfd7c0b639b7b13813f1c814d02f9c3f1784fb40cae0fbeff832abb97740a2e7b2fbe637f7b369f29b2c8a0bb00745fbf7d472fbdf4b25e7fe525ad482613e06f803158473431e00820520052a20e409d81533d2ba3f5554d06b3339a480813496942c63a27031e003769fd71ac4217afe274ab62b3d25fcff454cbd19e20a4909432b2bd3117270ff6f27b92e1acd14cb3ad2d8d78760a901f1e1fe90060bfc52ae36b2fbcac675e795d0f5cbbac9ff9c487f5177ff863fab18f3caeebd7aee8740add3a5ee8f5fd23dd2691ec1d1cead6fe816edf39d4defe31bc63da877aedd6bebefefc2bfab5cf7c45bffc9bbfafdffdf2f3faf4575fd06f7de9197df11b2fead69d2356334b0ed89772c2692b12e21a4c73c6b94321d6c124a706facca6321d023b3147112bb90faa90eece3f44d7c4b6dca81617e2d0fa2d03914344a1d6275f0c406f451fa2448276d325b6f42679cb6d9f04b820b1b9673eef35275974f827e2b9c5f6d5b5071e51847bad6f439b087c2b027c94bfd5d8d4de3b11b87ced86cedf7898c3e1aa53b65406de8a47f6e92342730eceb749261d6ff087ece9bfcc1bf5deeddb00ce085887823035900298d45a6b5033a84f0095c1ca2484aac1503a93f280aa0a924d675449108cd6c4b695eb13c9a549d5d59ae7f1302af2b5e973031dd4e50b340eb6ac0abe1640af638bae98e0cd4a688b796c6d6db3d29ab47f744a1238d21e49a147e6631f78543ffef127f5533ff851fde57fef87f4333ff451fde8f77d481ff9c807f5c8fb1ed6f9f3e755d17fcab6d7d1c9a281ed21ab1103ee210966ff684932596a1bc0fdc8630fe9473ff201fdd0879fd0871e7b44e778735f114f9f932c485ea7ac5a16e89988f3167edaafaee3909c795456019e7b642a3254499e53adcc7f52e56b62ee13719a88456df19d1c32e4880531f0f36e6c2ab22309c963dee0a303754dbe329e5bfe2df353566577481e2bec6f9138ce9ddb563feb95a508473445a7ad0b5775eec225458436d777138177e7d87c774e6b33ab3f2d027d37d3231ff888862974cc768cb7ac7ca80b52c8e03a9b75ba707e57d72e5f00847b0e9d57a89c04262ba38a236fdadc201337605725df06391854dd6c1416a366dee43e40ae028806ce09f09adc36e879aceb8d804e78f5ac6e3983a4c79b5089abb1a62c4a1242818c7309086781e724d2179d3fb7a3ab572eebfc85f39a6f6fb5f643f75dd323f75fd7b54b177591feeb97cfe9fe1b5775f9ca255dbe7851d72e5fa27dbdd18deb5775f5eae546d7ae5dd1fdf7dfd0030fecd6d50000100049444154f75dd7230fddafeffff0e3249d0fea473ef6417de2438fe9a38f3facf73f785d0fdeb822ff64d37258e984edc015672603f399939ccfed6e6b677baece87d78e0b4973221e6d8e24527155c7c2b1628ceb5e253a4e77cb4a9f572f2e6559b2c2446919d1e758576237b9ee46a0146a6d6c4ec87b55b624a9f5c4c909647b079ffa4ee43dad4862a7f87cf5c1f76936df62f0e6de44e08f4620ff286bc3794f440030b9efe1f7a976b30618471cfa9e72d05b4507b7c17807b0bd4202b971f51220bb4de208259f9830e0014cce185512580424850c4a34a99b5b65708bd6b9ae57006e4df433be5a0f6fe4d520094d80ad7558666ae02719042be01a11aa8cb1fe466ee38c571d4119110a270eeaee479ac124ba88f657782ff076fdc07d57f5f083f7e9a1076ee8caa5f3da9a7722bfa82b520138b7b6e6ea290b097246b94b62b94242b9cfc9e2e107f5e8a3ef831ed1830f3ea0ebd7afe8c68dcb7a105d4e3cd7ae5cd0a58be7746e9bb778fbc1dcecf7caab3bb6ad2a80ec447619bb4e203d093a713422246234913846cf9f037f398284c82b099f49396978ee95988858308c5878758224319d88e1e4b23288ce264b9ba86b447e6cfdb4dc8dbd66077b335640572fede8e2855dedec6ca963ce267635d9725ba89bcd75e3e1f7ab909851bbb93711f82311f067f88f30378cf74604b6b7018ecb37008e2dce10d66f9e158031203b0219a9be74dade9ae9dcee96e60089ff4860b10c6006ce926e806ac04abec0426398a9819a93020d6e00af6a624c052c1bc00192bec5d80694c8d6466c5d197001bea0cfb25295cbbb645330d6b6690435d92700b22a8caaaa1e2f832c1c58feebbb7332c5eece967659016c911cfb59af0e324076a55344884d2505e3bcca025fd5f39875a11999663eebb4352bdaea43f33e1bafef745606b10a113229024a80b76715779e64734d0f3d7843f791742e5e38473cb7d0db295ab2c347cf137fbd0219db8acce71b86f12a26a3366f6247e58db6ebd5e308aecb897242c654a9ab862aed4abc19445d5acba0d7fdf4ed6c75ba72719704724e5b7c8f3d47fb64398fb974df43ba72e30165f3d39c0d6d22f08723907fb8b969bd972290a5e8c1273eaa28bd66bc6d765da79245590a1818000ee04640125ed7f532d016faba9270e903886a03b15015a009556a0631bf350f8094cbc9320099c16c6a6390a22d64a9a9f2b63e35aad8ac70addbbd9889802708feddb194822f4c56eb06f05d4e80b0d02378424bf5f0b3123c951853581d949224cdae91b7beb214c1fed6bc0171bfe907630b3628189a4d46bcb937fd64cfc4aefbfd07251d9b74b221c19cbfb0a3ab572eea7e563cf7b19575fdea255dba789ec4b5d3ce974a26fa4221bc642e13fe3a794e2450c76e2281baacec2555c7897e35b9aa897625ae93db944e38fea9b789b93782cfad4a1fa2aa3446c65726e13195d588e74c289a7dfbed78b8517964e13340a29db31a7be0b127b5bd7b0e2f37f726027f7c04f28f676fb8ef95083cf1d14f48b36d150e767b683e23510070024cc01c0500a448124baa03208bc1179009831220eaee4a3d02b805b464d49264a06b8045bd9a0755844d13f2a4088d6e33a69aee8227a039993cce0430d646133aa73606b62af6a4da0072baab87e461d9c9f255cca0aaf5b95f5ad73d867ab48c91ca52985b28014d53856f6067e252b3c1bc280b99224818237e7a0e11493f94495c0a2b8a543febb4b533d316e0bbcd19877fa2a9b07ae918dbc6a3c7c3d4ae68bef350109b11b2dffef3f3a3b7b23c87469e77553d8b91ed4ff027fb4129e63631b66d53514ec84dc497e9abba0e3165d96729c46495e913ac2adc81ec07b199c47998a448e6d369ebdc453dc8195929459beb3d1e816f33fdfc367d9baef740042e5cbcacf3371e01980310ecda764c4430f35064910090105f004b964273fde61e00ad0c4e482a0024ea95fad4006d02b3ce400f9033784d0635fae0026608725700cf63a872d3c39bffc41bfee43100e4e8f30397e81e4de818a115549199e04d8c116d8f19a98f77f99423f62608cd9a9071d2196be05bd0c66430af94a2245414912acc39986728959952481dedc2ea2c4aa7ec660a644a57a8d34ee45a5f819f8a0848c28c6c575c4c131769e18381de2b868a7f70f4466c2ccd7c2a344195794ff8deeab43d6ea09c183742d5baa081fa846ca5afb2ba6825065d8ee635fbb6844fd42b8e8d2351e76e7320b945043d261117be77f49dbb76bf2e5fbf9fb9acf9086cee4d04fe4804f28f70368cf754040c228f7cf0fb14a5575f5219d1408d42b53d92b6a0aa10fd00665f8ae6064d0354ad0d74d8599725685222af358165f457c64b138d6a02f826400f8e1a4832a8dea5b30432b572d004400e2406ffa8e9ddbf613561773823d747748dc858a77f69706c63c6f663c7936d41c6cc011bbc686b02139d249284108d5875b185e77696c23ca5e05f4604b180c21cecb92cf417c7298bbc62eb884312938026ebafccaa959c63b47292bf0656479ec7e8df7f4197e7eba0787e1ee7ba796b6284c7223731af3637e6e4fa5ac7c86a01e2c07d62ee1331b58e8979ba5eb138585ef645562d4454fd80e7ffe990c5a39249a6da44e1cab394f531055d7be803dadaded1e6da44e0db45804fcfb7ebdef4bdfb2310ba7ced9ae6f3b922004c261c117e0238d204901bc0c633f029f4f59c87f4006793a60dd6493534515903204dc0ccf50180ab0642a8c233404ded4db902f0531b63d033b88ff48fc839014cad1ce4a4e1bf5ebb5c2ee51f331e86893182aa2af66cd3369a9cc116e01cf0d534d26f6208902aa57dce509454379b41bd3ab6edba2db6f0585914fae99601b4a2c39552669a228431e22325f5885092402242c5092581e133f2d896a4e478500b86a2c8f3993c3ffb848f77e73952b7ff2e276c322bd9b66334d1e73195e433fafbd062523590380662689991fa488c27b6bd5c8e8c19d163aad8abd833adbdc03b7caef0257cc347d156486785847c37dbd2038f3ec1dc3a6dae4d04be5d043609e4db45e73dd2b73c39541d97ccb60a2c51041f0b13203370a03b005615600945039559df69d6155a06212804a0b306310a01a4064293b83c6e00d426406b6a0038a9524ee86b25fae16029349a0f55e4fde7d2978b8516a70b9d9a562bd98fc97a90c6aa06ea2334a1abe9307056a9c2a300b65dc7890c057e35b0b7ef2d79cc94a5a89b6fcbab08270f93c722aef4c343b135cb64ae2618680d8a427f4978679411b20d1e623052a929525ef93801d590ecf3681f99e340525831cf06f875d08a95c96a628ef03caf89398cc8d99f91f8afbf0f83c6d552ed8c6435c87fe47124814c248fc1c9d3634c1e0771f37da96fd8c5293c0f05f1a28339a991f9959afd8b102f135bdade39a7cdb589c09f1681fcd30436fdefee0854c0e4ce6b2f37601220621084258397dfea179c438c809e01c6207997ba2c480b54ac3c4215e49918580130100a1007b868d30956550d00e308a2b5a401bf9ed164dd00e40430b63edea647960c0647ffa6f4d1c9a94e96834690786d43e8163a21c68e7831a9c20b80d2041f9e6fcb0bbf224225f9a86333b253642818e38491d49b2ff66f58a26792790848c8d8a70829d6c355b169be093653c58356910a09a990583313f9902fc7d3a593c8446cf054135d4e8603e0bf62ee2bca8139af98bb13e740525d3921b80d8d26fa47f82de1e0abffff92d1ab1212d1804fa861fb8948b4faa4115bb6d7e2ebcc50ed3144c92484e3f24548b49e7f6d538e088dcb854e8ff6ddbda14d04be6d04f2dbf6defbce8d076f43048e8f0e2480c3644099007a6f8fac78cbadd423421dc058ba5e0985529905719010a09228e15549130066a22a90c9104c5135a118785bb7e96ca005af327e6475510145db9ab0b762bbcaffe192ff64887fc1b15a3d84b8fc53524e4406c81186e53129eb6e040ff5b24b89df91a192a9c4ff2cd401f9e63b768337ffc0ae28a7612110b8e1aafd9095222313daddf6f6db08d8db84652a00ee7ad08f29222025155344c83ed4602542602a82159e5717831382e749e9180fa0ff8ae4e0bf86ebf672b99269455c9c4847e426fcb0fd89716e0fd81e5dc74f97ad1f1b2e059f2aae5735bb12710f88928e89e404abdd3411c741b72812b787d542fbafbfc258671e776c6813813f3e029b04f2c7c7e5bdc565ff7f9281ae021a4c1da033b05410303817f0164f66a7e4a03d20196580a30a78558395eb0c6bf28ca50a872760e40a624daf816fa4e13106c209236b805bdb356f00484fd8bada3f3ad63eab8f7ed6ab9fcd64f08d928a4c218d3ea70b00113386b9964cd037ba372a5cf7b9b45c55dae7b043d3ba0fbf9b3f6cddd59644cc473e53f66ba25f8ca90c71323dcb2c6b3d68a8cc6302d8abc7d24eecfa970f9331febd106944167f03bdd6410218d169ddac59d8b21a59954d5ac25ba2c7ff61d50989f394e471ca6a6c49f25891c057ac009d4846b6b8d6f3ae9ab03d92744656659ec3840eeb752c27fad6f5895548c5ab4a7a2316c4c6fd8db06739dc96431211c8210383085019b5f7da8bd8c177789b7b13813f2902f927756cf8ef9108006e57ee7f544318a48bc025c004a642a574ea9d40fa990a49064414a82283bcb75a1606b833c0326819941a00a1c175937c19bccca35e01d209aab42b7c58a84dac050b00001510f5b6d59dc353edee9ed38cc3fd8a7c4632c2e6ab22a9c3238bb0b535e2d2a00a905b8e0a7380078057a0b332aaba443e311611d8922cebf604988a3954fa9a3b54323a0573d759d24c6200434972b84b815e6b8a080900af0c9e28832d25188a808f0c6a256cbbacf44f9633294822550301f70f07789bcef13c25712c4822fe4faa4ed84a5ad05e12e78195cfd8b6b12a739e9aca9690ec3bfaaa4bf4b7d26de661f3b5ae3db56fb5f1262c4b8e67442a2220e28983233aa216fc0d79a525cbc3dfdc9b08fc4911c83fa963c37f6f4400f8d095ebf76b7ef1baa628aa000a3822955094a2cc1408a30a088e6c7d2c00b55300eee074a9bd93152038691297c12624178ad45da05cb7dd5f45b7224277bfe02a22ce06558d80b975ef1f2de43f35e2bfc5e56e24645f9a207e549070e2ed7b60b532185801e16acb16a474ddba9ce49c202692cbc4f9c684ff95ad9f892d9a0acf7bfd38aac97a164b0d2600db63a554404922e9bab90a65c0a9046764853072703d01d8133e9b57a913244df60519c3f6da5f7b03e1f3ddd85aff80fd89b90c5572e270925832d6e4b6e974b19293c912ff56cc7385ad019ad065721c4caedb56fb3ec83508bd538b050cfb164105825f4aaab0a557f8fe4616a9f52589572da1293b9dbf725d41c2d4e6da44e0db4420bf4ddfa6eb3d1281f3172fe922ab90656c6bf44a247bc0a3077e926d96d012943b612be5e474d0d1e94a778e4ff5d2de91f669570028143238caa5c45352c003e8425c8096d46afa56b52a9131e89a3701ba064703261d3a776ed7053b47937c19a02be03902a64b80d5c961c96ac587c91e3732dec06c309d00ccc96d886a03f581b77783fd70bad0e2e4444be8846db2a33b7774b4bfafe383439d1c1d69c9fcacdf32abd3134d2494092513496360ece9e11172273a3d59b69f105b9248176cb9adb799c6960485bce7157f70ceccbce9a16f600ef67920610cac2e9c3c5add49c27df82f665beb0000100049444154bda47e4adfe972e03c646cc0eeb939564e1a2d1ec4d7755437932e4955848bd83bb691b0aa2612d564597a2af5e07bd67196e5e4e87ad276822ca5974894ddee45dd78df07653e4336f726027f62043609e44f0ccd77dbf1bd33beeb7b3df6e1efd730bfac4539a7656c69a99956d0c9d4e978481d2d43b74f46bd74e7545f7ff5404fbfb2a703ded8d9e95725e1d4305809dc04bc00ab60fa4e10953a55adb114ae81cd2828016c541817613e0c64c157ed6e6f6bde1786d00f4f80dec41bff00a8ae0074ff68ef0949cc403f18705911adf06505d89a0ccaa6da5629a3568b41a7c70b1ddcded7ed9bb7b477738ff28ef6ef1c696fef50b76fed6b9f847887fafedebe0eee40fb07f0f675e7d62d1dec59fe75ddbab5a7fd83631d1d2e747c7442e239d6c1fe910e0e4e9039d4e1fea14e494c2bfcb1fd8984d7e6cf1c3caf9624f069c53c96cddf954e5d92280612f412ff97cb514bea4d96410be6b764eea37591582a2921221425894f1034eeb3829a7b89ab54eb9a5909e184248526c65bcf885eb7d7c4f74b29f17d282415f5dbbaff839fd0f5871ed3e6da44e04f8b009f9c3f4d64d3ff5e88c08d071ed4477eec67349dbba603ede8ceb8a53bd35cfbab996e9e16bd7adae9d9c3d0576f2ef4fced539daeaa1a1002e806354569e0d540093072f2885883588b1f1d969b0c5eb00d6ce09a22634d0835cc8b50fbcf9632e99e00c209e01bdb9bfd0a303de68d7ffff058b701fb3dff0f81d0fe3e407e74aa038339207ee7f61149e18e6ebdbea7d75fbba5575f7eadd12bafdcd26bafede9b5d7f7f53ac9e4e6cddbdabb7da7d1edd7492c7b7774ebf66dbd4ea2d8db3bd03eba6e53be4ed2b97dfb4087d8386115720c1d91900e4962ad3c3cd101db6efb947bd8be836f87f8754c7bc94a65c58ac93f4d35e1ffe0950c096349dcfcc302edffe4a0dfe582d2b4a27f6075324203c96634f04f240562949112b1698929a4081e9282381262f9725f656371f238825a29b9594d562d484e2b56633eb85fa17345425b626785dc90339dbbf1a87ee0a7ffb2e65bdbda5c9b08fc6911e0d3f8a7896cfadf0b114840e9890f7f443ff997febb7ae2477e5abb0f7e4071e941e5b5f7e9ea139fd0a5f77f9cf6433a6185b2aa1d8055dab6ca01603e0048934206a91aa12c054ab93ec9c0770672c8d0847117eaa89250c8128a2c2a912a99ea3294118a1aaa00dc0ad0f3a1f2016ffdafbe7e474f3dfbb2befedcabfada375fd657befe12f4bc5e22393cfffcab7ae69957f4ec732febb9e76eeab9675fd38bcfbda6575ebea59bafddd61e0965efd55bdaa3bdf7fc4d1dbebca79397f775fa12e52bb7757af38e4e5995787570ca99c9315b5827a7a7248e13d9fe8295c509c9e37471aa63f83eec3f3a3ed1213138606b6b6fff447758a1dcba7da89b24a99b24abd7b1798784b24f723b3a59c83f9ebc64eb6dc9aa684172f1cae888adb403c67975b38f2eaf4a445c1c0339668e45a8c5a4946ca5da457cdc0f3949b4c4d132337195534825b410b255a925b164d7518b51d4a505df37f396eab460ebf2dc838feb67feea7fa4cb57af33e27bfbde78fff64420df1e331b2bdf0b1148c0fbdaf51bfac40fffa8fed27ff83fd05ffd8ffe63e87fa25ff80ffeaafeca7fef3fd44ffecccfe8eaf5fbd8e5d896659d308e00d4256fd360bd1409ee19b020724418cca0081a195a5f063dd7e0b53e312c2129bbd46cd691440a14f2ef9e74f04aa602dd2553bb3b5bba72f9922e5eb8a09dddf33a3aa9fadd2f3daf5ffbd457f5d9afbea0a79e7a5e375fbdadfdd70f75876db693c353cdfa4e3b5b3bda2a3b3adf5fd0e5ed8bbaefea0d3d74ff437adf838fea91fb1ed3fd571f821ed0030f3fa6c7beef637aff473fa24bd7af2a7153cb4915b0156ff5f3ddb9762e9dd71684939c9d9ce8085b4724888143fa23b6b06e71ae729395cbabaf1fe8e557f7f422c9c9e5abafdd618573a43d564cfb0747bac52ae5f9976febe9675fd1ab249c3d92c7917f30811508a15112b3beeb34eb7ac871c99664334285242d5f08d2544434aa5ac7b7aebf21aaf027c86755d3f6259dceafe8b0bba8c3bca083baad83dc555c7e448fffe82fe8bff33ffe9febc1471e534458f3863611f85323907faac446e03d190127088394cb8850d775fae0079fd0238f3ea28e33932c453dc0160af6ec97f21b700b54a86d95f0c22b312e024695eebe1db7525c91749f51860afaad6f06d81b3883fe828d593f03fcb775f9dc2e007f498f3f789f3efcd8037ae2e1fbf5e87d57f5e4a30fe989071e5044a7f357af01ec5775f5811b7aff47deafeb9457af5dd2fb3ffc883ef0834fe8433fff73baef43f0dff7a0ae3df1881ef8c4e3baf2e18775e5c9f7e9eac3f7e9eae38feae18f3fae873ffa213df8a10fe8f18f7f508f7fec493df2d807f4bec73ea80f7ce287f5c48ffdb81efb81efd7fb3ff17d7afc07be4f8f3cf1a41e7afc837aecc31fd5631ffb10f547b4bdb3a37357aeeadcd54bca59a763561e5e95bcfafa919e79e996bef8f517f5fb24bbcf7fe5793d4f7bd6cdf4f07dd7f5befbafe9b107ae91dcae30e7b9b6fab976b6b7a06dcde673cd6633393625523a8b696430f790b8ab495c946e3bc1d72627cdb6cfebaffc8ffe13fd2fff77ff17fd2ffe37ff67fd27ffe9ff41fff1ffea7fafffe9fffaffa8ffd97ffabfd55ffe0ffe87ba70f1128337f726027ff608f049fcb30b6f24df2311f813a6b9b5b5a5871e7a88fdf12d7500fe7cdecb805fa7b1258809d4aa648e89b6012e4b2a335dfd9646c06d02d9260ec6c1b6362e9048124dd3d9f74a64447fa1329bf5dada9a697b7b06906e516ee902c9e4f2851d5d3eb7a56b17b7f5034f3ea61f04fcaf5f3caf1b0f5cd5951b1774fd91ab7ae8c98775f1becbda3e3fd7b9cb5b7af0033774f5f187b57be3a22e3d785117eebfa08befbba6edabe735bfb0ad73d7b6a073bafcd063dab9fea076afddd0d5c7de47fb3e5d79dfc37ae8fb7f58971f7e5417ef7f187a4497eebf4fd74966971fbea1eb1f789cfae3ba46227ae0b107f5e48ffd981ef9f0137af8030febfce5f3aafdbcd1375e62c5f1cd5774babfd28df3e7f5e4fbeed7479179f4c1ebac84aee981eb5774f5d2795d3cb7a3f3bb3bda99cfb54ddcb7e65b2de6a5740ae2122939c1862b44f76ea2a04a34fd7454a529a8f33d387ffd7e3df6c10feb0a49f6da8d1bbaeffe07f4e0438fc82bce9e98475810d9cdbd89c09f23027c0cff1cd21bd1f77404122032d8643793cbadf90c709f6bde870c57914502d0265e859d2432e03346a0dbdd95874b368458a5f0649f5f6717920d20e7248c2e69a12b185b4a69fc1920d793b4baae2769cd5a3239777e5be77666ba4c82f8e083d7f4fe4b17746d6bae5922d3753a7f695717eebb2aef43791bed74ff256d5dd8d2d6a51df5db9db293fa2d69f73abccb8c394f1bd09ea1a3645507e8cfce9d23c15cd2f6b50b8cbba24c69369f6bfbdc25cdcf5dd4f6c5733a7fed1249e661cdb77b6defe2d3e51dcd6713325b3a7fe532e5aee63bbb00fe4cdb20fa871f78481f21f1bc9fd5c6e3249ffbaf5f2261ec9230b6b54ba2dc26ae3bdbdb240eec903ce7f35efdaca8634e595211211e12711217e1556b2b5a19919aa4b3444205a71ffbf027b4bdbd4b63736f22f0e64520df3c551b4deff608244074e1c245b63a2eab03d0bd32d801f0e6b3197bf323d3af2277806b49494af1124355518ac25f6f001e72750d704e280d00813cd4abcc7af50068298c403ea09289cea24c28a8d39e61737b07a0de9e6b06b8cea173b3d49532d3ec68d4f2ceb1060eab0756432bca3a4e5a1e1d6a581c681c8ff10a7f2338b3596976e5aae657b754e6e8ef7674b4ff2a728752c1563f57776e46f2d851878d885060bf9f6f69be738131bde6177715c5139a14d909933ad97bb5c5234866fe210071e87e7e39e8c79e78bcad3aaeb15abaec55d4a573dae15c6736ebd4f79dba52544ab67adf17cd8847072fb3281d93c03e54b2c81756e598afe35889b2b9a1da0acba6b2dfd2634f7e42629c36d73b3d02df53fee5f794b71b67ef7904bc8575fdc67d9af1963e9bf5bc69779a1bf800fa80243e52090156ac3164d06aecd03a6328d65f71d684bd463b29a2a82f9d3abf693328d113e28b0c833a45a4021e9294a15292b7ea2d6d1980e7bd7afbd3f79a2b35bd76acd54bfb3a7cf975dd7cfe75ad8e175a2d163af28fe9be408258559287345574e2c01852edb6d5ed5ed7ca7d241cafa2e4bf65852f11a171792c9b1f8785eab45499cd24f3c7050987c4348cf24fa4edbf7eac17bffcb496fbb67bace5ad135d1c8b6eec6cebd2ee5ce7767b5d38bfa5cb6c556d6f6f912c66e84d54850203d9152573cb52548845a1de991f45524850658b0fb7156e4a54a9d070e2687157b42f529aae3ff6a42eb31d87d8e6de44e04d8d40bea9da36cadef511b874e9923ef6f14fc8c0369b75ebe4d185bce513d3009055c86fc094a019b7225209c191a18e876aadf239c8c43696a94e48028a416797057d8531a160dc04cfb215fd422601d444c63423696c6dcf34dbea55faa29a5501e07bc511c7a3e677069583d4e18bfb3abd7dac9bcfbca883839586b1d36239912ca4c3d76f69381949302bad4eef68717aa215ab16ffcec67271c28ae554cbd37d1defbda4d3a33b5a2d8e7474e73592d21e7a163abdf3ba16fbb759e11c697978a4db24ad67bff68af69fb9a571ef5857fbb9ae5e38afd9bc53998566c4cdab8e6d12ca6c3e57e26f404c530adfa18820060975ad2e85d6b1abc4b7b25e73abc2a3a4703c2924c6115a49814ca8965e1ff9a19f5229a9cdb589c09b1d81cda7eacd8ee8bb5c5f4470007bbffa7e4b3d6fcaa584f28d4fd1fadd976d7e9203e02577145920110a40cd20e72d974942a67216326a2489d4a80d18610b13fa169e56da21316802192bb2a214f205a184fa2eb5b5bba5d9f65c85241250c52f617ec62ae1dcd639adf6461d3d7ba4f1e6523b85e401d01fee2f7472b0afa35bafe8956fbea0bd575ed3fe8b4f6b3cb8a98124322c59b5b0f5b43a3ed19d975fd2e9c16d2d4f0eb53c3ed4f19d577470f3191dbdfa9abef6a9afea992f7c5db75f78497bdf7841bb04e003f73fa87234697cf5405ba5a8e063f4a9aeefd4cd7acd38dbf08a495c11c993296a3d4f51465087da5ff895e04c2484aa8944eb38d0eb01aaa2e0417884b85c0a7db064ba78fd21bdeffd4f22d546506eee4d04debc08ac3fb96f9ebe8da6f740042242b3ed6d651428db8c61c9b50ac05550acd2a200eba7066452282d54ab2ca32607f8816b7e7b9eda96916527f98a0846441b93990a485c15189da6d1f9438a9004e1c70c60deda2a9a6df1c60e50ab04921258ae2cac94b676b5d5cf74f9c2259d9b669a5eb8a3f199d774eb8b2feab5afbcac3e3a6931eace33cf699f4470f4caab3a7eed359dbc7a53775e78594bce550ea91fbef4aaf69e7e5afb4f3fabdb5f7f51872fbcae59c52efdd37377343b9c74e3fc65b6a72e696b7b4ee2280ace4e6a99f023d4b1fa98b35a721919f2142aab2a71b9ae70a5aa12271e848998505fc75267b1acf091637c93f3189a1505b546937192c1989ef8f88f12932d7a37f726026f7e04f2cd57b9d1f86e8fc04b2fbdc4417155444006b56853a6a9d0c48a421aeb9a0c70d5c90258ab9093410530814540b04282aabc0a99e0bb0f8e0c18c1bdd700001000494441549e3c28aa22421e1711ad5d59854c24111a12a603207582e978b3efdb9b7da72cc816e14dd5d2ff55ecb892dfde2db3bdb3ab1d2794a1e8c23887b6343f4995036c1d48750fd9dba71a6f2db47cfd4039a46653519224eade52c3eb2bc59d49ddfea4ed13e97aee6877296de54c5bf35d95ae932f83f9880726d9477cea49261dc9aef3ea8d5589fd5fcb62d715e6d8e645acdc74e1984ccc791c073946845377c745847c4524a2a1b13aa6c453a1f9f92b7a90d54766b1c886361178d323907f48e3a6b189c09f2102f7dfff804e870aced5260d6651863152c1d6d24822608d00980168005c45a0c2a39064c083183ab2ea300d24039713ed09a109592f3148014ddc63ad37e813970bcb388924fa13f08c0c0540e96da1396722fdce4cc9db7ee57cc6ab10fb3405501e9693b2a4cace5cddc573eace01fa65aeacbd4a9da99c86f27820499c2a6e2e544eaaa65b475abd7c4771b0d4dc32ac620af2b37e4b3b17ce6bb6bba3c2794694a20984f76fe72f495aa3aa5814285841f5f34ee52c8104fed2d3a24120255545b8a85a57c48504b198e81b4920d6eb39bb4d67bbbd5eab0c649430ab89c98e3534118b2b0f3eae6b371e68729bc726026f45043609e4ad88eabb5ce7430f3da48f7eece32d414c46f300be003f704c85721a4799bf06bcdaea55a1c25bf7e864314df02660715203c4b3f60802b6b1b4abf522510150c1af6e03c211212713bac0ddca01f748b5b67a9514a5a89ff7daded9d2f6b92d7580760768ab970624a79858918cad5e679dd8d752225be733090a782376fcd77187c5a091b6ffa0e1e972a51509ae02fcc13cb22f4a7427c94ae89f9838d8ad017f9775d0b2dac624f3829546cff65a87ae8ed54984d3053eebaca42dc81cb01f2e37f39dd025a8c58c988ec46582af46d1747bce42e1c810c2a49139ba1efdae3efa233fad7e36a767736f22f0d64420df1ab51badefe6084484deff410e66291b80b5d7e06a1c13f8ad0248d3d248ff00aaad20031f05fd0562bba5029e8daa0c8c2b03a4ff5a2d203d02906bf94aee304df2eaa4c534b21515a0ac006a05602792d2441d8df29bbe1d29fe715e12c33687eb3d670e59525dcfd8228077d26a1ab03b928056d860b5c14a05c755918b596a22498c248760753292009c589c6c2a7c83fc84fd29f002954e1a03faac9334a3555d89a50cbe4889de39f6e7db73ad9347e01ea4102a24ca08ead2bacddcab49ebcb09c8abb391f955c8f3741cb14c1ef133e43fd345d83430cedf8a51458f7ef447f4f0a31f582bd93c3711788b22c0c7ff2dd2bc51fbae8ec0030f3e246501c4a489afbb934dc0b0e36d5ca0e304d2aec6da806d9c28491211a501696632b66a82df007218b522118cc81840d709042d80e2781738e98323db0dc5da24fdae449cb5d131216f39db986dcdd5b3527012f14a0097f096c4c11a64b93a9569b138d16ab5f816f9ffdfd0a820e14c965e2d15cc6922310eac2c569ca92cc7a55624bc857f52cb63491ad6ba62f5319158c070ecf672e230755daf205ec1bcedefdafda9c5a0b5cf1eb525c489b8acc9f1f07c5a0cdcf746d2adc84823f377ac9ca80935edd0d6c5ebfad19ffd2b2a856c79a677536c22f05644609340de8aa8be077456107002fc2b89429564e0120aa803b72a406b705b03b600b6aa09b01b01c14a7c32430678d751236f13ad56839680f24832b1ac9340654c45e788be8104e22d2e9f8558ef7aec8427e88a5084fd9890aed85a97819d32ebd9ca3275f219844a9216266c91344800c3f2542747875a1c1d68717ca895130abcd5e9915627fb72bf791389a4fd6ec892e4417d89dc2932c78b63742db59c96aa51b1d19138b6356305342781757d0f3fa03623c9ffeaf0578dcc33d5964c98aea6b324e8e431b0b41888cb44521c09549bb32ccf83f113c53a7154d1ad9a337dff4ffe455dbc74859ecdbd89c05b1b017f94df5a0b6f93f68d99b73702e7ce9d57bfb50de8016646bdbbe601b55070200da0017a1348ef2d16cedc55e15bd4c9214b51e11c01718dc88d7434b04478380350d8126318b8b64365a46f1c07ced8d1e6c1f2852dc65b54d803aa919f64206eec9092a4d171c6e16432636baadfde52cfb652e1bc446c5179c530925606129557160336962489d5e2b4e91a486c2b56260b278bb672596a312cf19d950a492ad1d1a36bbeb3ad6d688ead9e4c1a991a98c8c48a46244ffb18118ac4ef2ae621fcc457ea4e962376c6159e9038bc6d37ae561a1937a203110d4c68a06f982a1a83b1903b8252a96b8f3ca1c73ffcfdda5c9b08bc1d11f0c7f8edb0b3b1f12e8b80dfaacf5db824270303df9ad66008968193932a600cbc22039ff91be7eecad15482a2a57494a1114034280e00e5c00a643088529718159696da5b38ab1083fbe83ec054b604c056c05f94cd367a338bd6b60063642b7a2a9dc96a203958ee00f8425966bd4c1de0ef84d2b36ae8fcd35924970e2aac200a6718d9e3e7ac93c775b3999cfc0a49a3c0ebe9ef919b939866e8314f2534e2df80bff6b5323f7125fe0a5f7cbb4a94e03247643d3f138ce67b1b4bb2f078f35bc972c3aa46849c4856f40fcc9b4239dfd5477ef8a7b57bfe22bd9b7b1381b73e02f9d69bd8587837462022c04040da9333c01b03e104a8489778f9e6ed1ad886efd58701ae114029642697082620dc211c11ed4c6145f268a088b041d36089b82242bea6069693460ead4756238d6d5dd8c1bc220b944aaf0ad21fef6807f003db400664eb746241a1babe53e9674a284a2706492515f03d812885fe8efe1ea23eeb15f89a248d329fa9239124fe17f826950e1742958435e09b572c2349b4e2b393466648be830792e272cd55cb78c5e1a9c0667eebc4e7245109c204b93e503a798c942b9289db530d8dd0234ffea09ef8c80f308db48ab79136a6deab11d87cd2deabdff9ef72de11a94b97af2802c03c43bd08ea4643744704db5813ab06c35d889d29ad901ba011409d0cf812e3017b40bb0398234223e0ebb390155b4803205c9b3e60d637fd1154e0194c47c0d96700566500167ca13f02196e99e005c01d1124924183f5b24dd44a6c355f90a991aa248cea41d485bc129ee9ac6e9e938a908bae53b2da4892484566b42eeb66c5e12438b0f534e18b9868323eb091f8d3880755dc6576c4828a2612a7cf7746e63c4013fc119d2e27f4387ecbb16aa03ea293fcaa917a9528433b571fd04ffcfb7f95a4d8c3d9dc9b08bc3d11d82490b727ceef3a2b09089ebf741958040a01480399c1db13ad001b2cbfc4838da32603a14206c19137e5bba06f10f4b8c87512290033625ad161105eb26a70329900546149d80c64addf3401a4ee0386251b14522495d656c8ac6863a853a619e89abc7a21898c9c690cd008e80ff047fc1c0ddc6734d01edc876c2b9131cf322b7826f3ede372b9d292a4b11a57b24ea183f4b3f601df2352366f722522f0767db7b91033270ad71d139a5ab7d5e2e698b4b8d13132ef0172e9f49cf373faf15ff8efeb02df8fb5c6cd731381b72702f9f698d958f93611f89eec0a007167f73c887de63ec0c62d03a0398647e78304482b403c921446040c84eb44224d007205080d9859523d87ea895eb75700f78ab7f215403d30c060a9b32b4922115840df84ee885044ac7b2953c1579513862922d6f57429f917112b672603603f9a482243fb69aca5fc577817a7a71a4808a36d9314bc9ab01fabe6cf4a8bc552cbc542cb2525fd1ee36434793ef86dfd611fa1824dccaba4ed8692f69a60687d310de11469462d7e23311b98d730b2e2a0742cdc9e88d500ade08ff0a9328b4e8f7decc7f4d8073faacdb589c0db1d816f7d8adf6ecb1b7bdff311c8e41d1bf473d2086663e00402c1c2903f5805b064674ae6195c0d7ee402ad78ac0cce20a0dfb21920278e8270d777806c8a2edebc270d00b26902302b84323579740ba3e69b405e05bc80673f2242897fa28c08050eb9ed44e5d23e85b8f0dfe327125ce34528d1c34249cd9efbd7d04edbaba9aaa05d4df4d561c9b04102cacd0f978ce71626853a924736723d126e45da63d1317924f5c94983d5d3086ff49c49569ef79264b1e410c9ee0dd4ef268fd666f0cee507f4899ff859cde65b68dadc9b08bcbd11e0d3fcf61adc587bf744a00a08362a5236c0656a41ddbfa741414b6c63c101189d6426b202b7da3e3e034684c046353e75037b29806d29b2da11e181a4314295ba7508b04d403802bd914dce7c03baed46c017d4fa42493b43946bd9a02f25f5e8b09e92455d9fea66455952c544224b19e4277c9be43a15460a9a844a359d248ba41191ca44aa4a25a947aa40541511f082522a5d2a33a4f54d11aa648291958be73831cfc18983a00ccc776c840f2497913eaf4c46e63f367ed5a8d4877fe8a774f5fafdda5cdf610436c3beab08f0a9ffaec66f066f224004404e9e0ddc01c7e453450188868c971d0fa052ee37f80d4e1e0c191b78028400a2c1b3ca009b2a25493ca581ed80cc92d5ca0a6208efe702cbab225211d1c87ae58bb614edc6a46bdfa2a8e82d502a7130102868a3aa645c97677dd403b016a02e560291a25f2a19e89ada4f96b9de976c3ef61ca6cf3ad7537d57544c960d312e182705ed44266d2c4211295f5e758cd8e2c6d4a89138b49890309c644d2c3ad4920689c349c52b25c7644241f65b7af2e33fa4a697f6e6de44e0ed8ec0fa93fc765bddd87b5744606bbe0304931a003c017e77271554d240e912f2ff56c8a6948237f60a5a1a04bd15b302050d8a6ba07422f1064ea8ad0400e2bbc06860f59f39f1db795b6d60cfa5b822b016490ea9b83001da153bd27a1b4b0078a894544913f537caa22c90db914dbea0d7ff815329c831c667324e3225430995941a2f841d914012be181be8874812057f82a8583e33554a51c75c3acac8a28c14ce365f27928269e450bf95640b278b154973609b6f20564e9c34e518386e133e5a45a0fbdce56bf2efe268736d22708f22c0a7f91e59de98fd9e8e4044a8cc66329019cc2740737212011479d917780bb0aa51c9549a61f0a3d32038f0763fd2062379fbae5a193c4928159eb8027903b0c97a0da85e893889b85db183d8d95dd7652b029b413be42f45c83a2242be5c94920dfc7b00bfc70e4d75741440bea35110edfa4e853eee06f881bdb44c56e6162a251519f2bc3a0614530a9e64bdeeefd05fbad26432adcfe9a86a644b6f64e2133a5b0c5cb6f94fc4415aae46b97f848718f9a6125d93445ea19db45357ee7b08dda9cdb589c0bd8ac0e6d377af22ff2eb03b9bcf55b23480154857017f83a2a10e0c5646ac09b8035f157c556f0d213b916cfc66edb76c8f3119346103906402ee90180151719f6945e2694cddbd421126daf12d8a08059fee082933a05c93190a45b85d94a56bc9a400f45de77aa15d647f9d14bce27022e8ba50dff72a853e124316891ca28e7149e52e15d7e1757d79a3af2b1e97523854559eabb84662319dc56c20262b0232b05537519f482adc444e4ddef50905fe7d159c5664d1a34f7c54112845d7e6de44e05e44804ff5bd30bbb1f96e8840025e1588aba0db44e91bf4e706fd99a0a12d7974a07147851b2e373c6f1519280732c6ca6fdae818a91b3815a073760d1c23b3958c6a6fe503a03b8c430355f3e854a038225b737482a116811153aba7224288c940df01f27ddf91106650a7ae77d9abeb122a50a7f5e27b7a0000100049444154be146dcde69acffa46b3be534f7f4fc299f53dfd6e238b5cd7b94c9584d05d00f73ca3562fa9b4711243658e0488239651137558cc4b5ab1b41838401f61c0a66f9d382cdb78305d66a20b9b051f1e7cdfe3da5cefdd08bc13669eef0427363e7c6f46c069c2c92370bfb63769d2088980db1c405b4a8512f0eed265a50e8f7642956430f2c6bde2cd7b31548d1cae4f10b702a014201c05c0cc90af11c506516ff138898c8c0f672d1c712213f58cb5ace55d2f80ad5909db65041eb981bf1e9b5954ba5e1dc9a2eb662409ea24847e3e835fd4f733fa7a39c958aea7af25a08e2da9622aeab051324920eb7a47bd2bb4cff8497bc2c709ff4c4c43151f9c2c2b8db1258e91eda989182088f315ff2a7dfed3f055a111dea45466a788d46c6b47172f5f85bbb93711b87711c87b677a63f97b3d02c104220cc3959ae4b600497fa8c03f094e029488c887da0655f7f93c415c960723fd92ed61f21bb8dfc41ba0b292a8e80a858482c86c7203c966a0cfa51388938a130e42f235f9010576a5e08ba7c74306f24202c82c30f12452d91201654915124996424962607cc954b84d59e8efa8bb1d592428e0dbf18850d22e255b992e1b2f1461bfab68b2aa60464cd889c1737532f496dc8204b2e2dc63f2ea031a99a3134e15da9990e5ab42893deb418b2e5ebdaf25376dae4d04ee6104f21edade98fe1e8f40c4fae3d3008e8c6160f394d6656de867c02b80b157209db7b2206f5fc152f230e84eac2446928201750040bd229940d02a272729a328221511560f104b065e03adc738b3d8a6e5d723d46423d6ad368a3a4cb50b46603ba0343f8b127272a08208e32265f988268c89b50598ed96d9c884c759c60c499101e51b74c656655e95184d94f67960e535902806b6ae3c0fcf7b1c2b73ab72426ca42ac2c0535ca1cc9450689b976f3c28020c6dee4d04ee5d04fc89bc77d63796bfa723304e83fc273cc45b75433997540c948adae6e66746a864aa8737eb52a909aa4d0436c0189a9c4440cb95dfc601522788a64786c92abfd5a30653554bce408633b9d1890760aeb61bc85ac863f0a5426e561e117402be5895a2407cf4c39e304692417d623e54b99105e8adb3fde82cbec1c46b3fab1f5244f3858aea1fd0a7b36b0df6420c5d8cb4fea60fbd9edb5043030964d56864fe5523f3608412bf42214191d1da8544d574c20eea57ef7b50957e6dae4d04ee6104f857740fad6f4cbfe323e037e6a3a343ddbcf99a6edf7e5dae8f00b81d1f798b9e0ce08022d8df00d54029a00dec46048803f0045846840c8c1dedae2475c92b92005cc148455003407d2e30a27330b09224dcae01d05b21251a653208b73f5c88ac6ddacf09a077d268639a7c158ab931aa609cdbe2725d9a6cef6c4c650ec20784e89f2890e5a9acb05391a912d643778598b0b7e5a849d52714669a90e11664f1da6c540d2ef169c08e93a4fd75121c1c4374d1adf4fc183761d79a5ac221761545419022e88c5496a273172edb4433bf796c2270af2290df99e1cda8f74a045ebf7553ffea5fff33fde23ffcbbfafb7fef6fe917ffabbfad7ff92ffeb13ef5bbbfaee7bef955ad96a7240e301470744c80b833f8730b9a24e35ef2c80c059fb8fe2c8144d0075252c87d6906a30db22309c449c464b0c5827c65a2808afb7d98be1c46adcf0faa268078c28f09905e53451b23416783b2c4d81a5a3b5414bcc9d31203d5520ae368684d3886aef5e49804f50a4578047d2e18d59a94be990463e9f3d3be9818d3121c89aefdb55e270cfc19688ff40facb646ecdad3f5c8a6d81a64e783042b2b0f7ca7746f3f9b6b7be79c36d72602f73a02fe54de6b1f36f6dfa11130607ef39b4febf55baf2b2264a07bed9557f5dbbffeeff4f7feceff47bff48ffe814e174b700e5803fdc0ca260783199941c1b695e83645849c249c03dadb3b400aeb8c97adb44d21ec2472777563b00563d7584e5f44b4fa40926949c47f119744e2e4e19584dff6fd9bebad8d532e8553782497b25df811760c9641baad4446ba49166736d440dbfd6b786f4fc655c6afa9927b484de6910c6cbb62c7db5e634b1053fb715dfbefe471ba58b06d356ae0dc63c07793636aff4c1e6b6bc23e2ab5bef0d177849a3b199a6fed686b7b7bddbd796e22700f2390f7d0f6c6f43b3c027e8b7ff1e517f132d4cf660db8762f9cd7f94b97e43f6332d54ecbc9bf5d9dcad2c9085701bf88902fbfec83a702655b7248c06f4da99e6d985202be947c0aa92a0b7a90b90bce03ba2680d9c963a4aca0aa134f488a52044b8bd5a02580bce4cdde40bd42783ca381a4304123ed09aa6c354d383491302ae4be2ac97a2bfc7663c33c619b9e762b7050ebaa7534f9337de65612ca5407f44c903599e8c1f68aedbed5b0629534904caac69638462d968396f83e614fd8b289881077233fec87b7efe00a6d5010afd07c6b5bfeb1626dae4d04ee7104d6ff32eeb1131bf3efcc08dcd9bfa3db7bb765304b50de3ff23a03bc76cf5fd4d51b3774fdc14754b72eeb5873ada2d7c4617205010d7ae20aa81a201b4fa82141809469225174f00b25eb091920db4f6729cebee080a003596202f8a936709ed8f209fbc258badaaac840bc6205b25800cacb9586b3b77f972bea7757323ea476d268c98403f33a2e49421334363dad8f6450490e9631b8d7902664ddae24843792111edbbe53c5e431ccb336a283bbc21bd063df4cb66d5ae2e729c9e394e4e103f411259e3f9159cf1b7b153d4e66522a88950995524862dedbc47f36dbfcf9766dae7b1e81bce71e6c1c784746c020f6a5af7c51c6ac04b0c139056096ac34fc06ec43dccbd7af6bfbf2758db38b3a9c7a9dd65e2b751a013e27118f118057054456a6493dac03a5e9c4912958a20a4a03c5bc9dfb47b31286f9889134e8920fd127913b649df64d74fa37c04565b11a7502282fbd0a01a0bd3534903806007f05cfdb59032b8111d01ee97f23a1a06c1a57ac0c46cec1074a08d09f1ac163fcd468526dbc2adb36351992c4683e803f9169aa49d238d5f663c6b6bdb41fd85e6177b99adacae364b16aab8f913eebd5d9649936a399984cae8622d6444bd11ea1adddf39b158863b1a17b1e81bce71ebcdd0e6cecfd9922b07fb0af6f7cf3eb02bfe404225e895d46a44ad7a99ff7dabd705997ae5ed7eed5fb14e76ee8b85cd0d134d7a2ce3490482a2b1221af00fa9c409a8ea0094932605a67d25f4ad0ae50a8f0a98c08f9f2b0a9d6b6f5e3128c96c11b2417435410aec87a4bc86ff50b270c68c14ac46ffe2bc0dbc96300ac97d4976c272d564bb69456f24f400d00bb138a755abfc1df2b05d7c903d822695019cf12c9447ddd37c9659355c8bf594e815b13be0ef2b6d562b968764e4970a7f8e395c73ad18dd81e91f5ecbe354fb7642590e75429d76d39fc444f0ae2b9b3735e598a36d72602f73a02fc53bdd72e6cecbfd322e037e32f7cf1f3f27fe55a71ae025d9909e04dad05864aac449c4866db3bdabd784517aedeaff9c51b5a6d5dd5519ed7719d6b19bdc6281e2d3203e31b0e2ad0652074e288901a21d54adeeafd566e7b92ed41e69144b865006f3ef921a92799cd3adb089d2e47793572ca6ac409c5d4ea3e6427798c6c85ad8675f2583a89906846f84e2e2d11b09aa8248a46f8e30431d19ea8dbb6e36060c7a3e69957228d8f7fd6e364b542e78224e58471b25cea78b1d4c929c4aac3c96341c21a5a0043918544904a850a24ae6a1fd047af1c9f1683a0c33773366f872dac757cccdcd02602f72e0279ef4c6f2cbf5323f0dacd57f5dc73cf18360596359a6a2822e1ad4b89b76ea864a7593fd3d6ce8ece5dbcacdd4bd755cedfd0727e55c72492139f8f640fe032361803088a3232142492a2540675a864aa642a2476b226a5d4ea0da80594e2cc649aaa5c9a0f5b3d09c4bf5b3201f6279c832c78e35fb05de59f105b00e0a780b7db4bc07bc536d992a4b17a6345327208bfd20a7e4b2400785b85b472c48ee4adb311ff4662309131300fbf367e4b26f83462dbc9e364b5603b6da91392d6e1e942c7d009ab8f23fc3a26a1ac38f0173a50a764a24445a8253e9e09048f27379ad15ba955cac6a691249daded5db8df93f7c6e9775904fc6ff45d36a5cd74be9b080cbc3d3ffdf4d7b4589caa215b83f000d80d6101e8a50cfe06b292a9840250ebba9966dbdbdabd7051bb2492d9c5abaabbd775522eea98f3912166a8eb20eb511bc7b3e92a693de86ea5ce6c4daa7c892ba07603bc15f49e0054b0740de20077c5c7be4b1219898ac4d0b6af9c2c00eb5392c7b15700d08215ca92c4b222c12c5929ac58850c779309f24bc6ae98ff4039905046534b249326ec4cac60c6d61ee5922c478caaec8fb7c28e170b9d60cf747074a2e39315b4d421e5d1f1a996f455c64784b8954caa321f271414b582297e6bd6217143411fb38caa2ca99d73e7b5b93611782744c09fe177821f1b1fde2111b87dfbb69e7dee59f6f12730ad026615cfaac03d4584d2202f4aa816de9fa104bca31475ac44ba9913c996b67dd0bb7d4e71feaa4efbcbda1f675a68a629a1ca38ab0d4c083ad31b118a703281142ab4911405003a41953a600ae84e90c1d6203f9104d0a259d7cbff47c9480238f1ca8364b124092c490ec7a72b92e2424e2e2be40df8c358d9f6828fdccae715240fff18f06a1a5891b02a99565a9ed5070edb07f40e24aff1ec47769d4496f04f481cc7a7a7248fa54e58651c1c1debf078c9ea63a523561e2724af15c94c8c2d240153c7f2c3f3624262d6623a67b1566b574f4e9e9589ef05bdae2573f40f30d0b5b93711b8e711d824907bfe2df8b33bf0564bfa8dfc939ff9a44e0144816e86ad89cc31026641e28808995f01f92ab517f0143c19da1a47c96ac420574824dbbbe73467bba53f7759b51db29fd7295b5a43ce34aa48e8abf2f850c45d4da1045cfd9f3b45048a2d615e4a3427560206d7e617be89cbc03e91284873eafba2adadb9320a07d803096200d857d05207274bda4b2d392359b1c5b46405e272450219007783bc5728a72481050968c9d6d392fa92d2db53132b908984e19fe45a30fe84a471787c22278c03561caeefdd39d29dc3531d9d9c9240b0c7aac33ef75daa67ab2d33995f2a82c9480a124325be622e952c52b5be8218089908e460b682b2ebe72da66ba9cd7313817b1b81bcb7e637d6df4911f8e633df687fefcac055152a80b002c0a34c4ac19b00b909101757751de00df88d8267a354295d5b916cedee6a9b2d977ee78262f7aa4eba0b3a8d2df9f7466a748a33bd11215137c0d61a7299118a006201d78955846a056ec929e655c95b479379d084ccc8ca626295605f67b35e91411219b55c4d248e01405fb595c1c1f142876c2b1db332583879b09de515c98255c80949e1b4f15772df8afe15c969897daf6a8e480c07d0e1c942fb248dbdfd23ed1d9c689f2daa3b47a7f04e75c4f81312c792b1417cfa2ed4e14b29cc2b244fc2452a14411bc60431254f919a64b649c43ae5cbbdd2cc0999e46cce863611b8d711587f36efb5171bfbf73c020bce3cbef2b52f2b225500ffbeef55a0aeebd5d12657006cb581b6df98c16cc00e8803d7263a1b8f5978d522f1b18aa2cadb76616c3f9f6bbeb3ab8e33923adf910fd78fb4a5657026527a46b151851e278d885000b613c01b11f4491121586be0b52d9285b81091138b1319c33997c03f00f5ea83fa0000100049444154b7d2d195d40ef666f39e03ea2a76ab34d0e79f8432f8df3e3866e5700ae09fe810c03f6425714c623861e5e12472c2e1f729f525c9c5ab8f63dab7f70f4916c7ac304e74ebce816eef5377d22079ec531e9298da213e2b9c817105a7e7b38e9547afee6cf51111329f49893c2989042929e2ee5c6bab3bbe0458eead34b8e5dfc23fc7d95221a6da5c6f730436e6feb808f02ffd8f636f78efb508bc7af355b6ae4e55b2a82771ccfa994a294a40b08641adaa9e05c5806680377f04ccfdd6efde0a705bc8a0170a40dfcfa248409424e29f1edadabda0fedc058ddb577414bbf2b9482d73d520e1a8e50846521a5d23641f22d01329b89014116a7517906ddb0f61df406b3f4c08697bde6b77ce5c90f3d6d38ab38825b4624571e768a15bfb277aedf60174a85bac24f6482cfb9c5f1c7066e2c4724052d83b3822591ca9d5d99e6ac907be571d07248e63b6c64e58710ce81ddb0a48da22716ccd4af3bf946cb148fc4efcb09fa44c394eb5392cffd15fda6a176232594ef4bb6e22d4ba70f91a7dd1e4368f4d04ee7504f25e3bb0b17fef2330814cafbefa8a7c9690a5a8830c7a8537dd9e154836f08e33cca66c6dc9e70e8dc82ce09cb10e9a78e36fd0281a805d3d03cf945734db9c8bf837a9671cb297f3d7b49c5fd622e79a482293d6b0ca20d966442833cf2860432070340b6afadfb0eb9549952656199e8fc93f5156e1775d92488ae67d51c9aa91ad2e6f4d79be0389c4e07fe05504c9618f24728b95c5ad3bc7bab977a457a1d7f6482ef0ee1cb25a619572ccf6d50909c65b5d4d0f3a2a3114096c4ee2d89977d8eaf03bb017cc45ebbae7133ae3857cc1522435cfcb44d57aa4ca97e8b31c13738beaa52b37b4b9361178a744c01fdd778a2f1b3fee5104fca3ab0707fbaabc3d07401506b22c2a25ce2855d8828964cb49be003480d9ab9008504d8ca2ddc05c24011f3603a6826f5c55a4abca404f99698724b2c32a64b6735ee5dc250d3b574822db1a9d44a2482a9aac8cf10115fc29a550a3858e4cfa516cd076a2b02c1e316692878d9c598cccc57d5e99f82dbfeb3aedcc7a5623bdb6679d4a49fc9150aa8880ec77d5c43c3c7ec9e1b913c4095b5b2e17ac30961ca60fab91043469648b8a0cca389170534e1cbbdb7379c5d3612b4b81dfd11f223acca80a33106d571ce7c0bca98a8b0acf8a7d13ddf2b84a1cab27850c77fb5d1bc436f72602ef8808e43bc28b8d13f734022727273a3838c0074394812ce4fdf6089701d8166503f1753d02b0a52f33395ba8605d55d05f1bd84d92e897e12fa88b9ab8a82363b96465339b6fcbbf513ddb3aa7fedc654de7efd3719c3bfb3328458aa288b59e08c62a647bae256d93da55795927719050c05e7cc12e7e4cb48dbbc3b89257194e0a28607baed3b99db9ce6fcf4828455b45ea5995644cee864285154b44c831c85cb73b126896a2423b912f25d4cf7a6d433b5b33b6ac7a7567e370482109154a1e255352b4af0cea552d51d9bfca23c27d22f9d1111282a2d5da7453f75d859836bf03e2586ce89d12013ecdef1457367edcab081c1e1ee8f8e44802dc22a30195b85ccf2cca125051b8a4bf64ca57f503c89ed83632481b0c1bf9cc4400397d23c03c503aadd4b3730dd117c92aa09b698bc3f59e6432674b6b7eed21794beb583dab91997c2e9225155994d82c2515110d7ca9a85db49b4d91c86aa56f9413c9e424c22a8402209ee404e22dad7158c9c9a53017af1ab6e733ed404e0473b6b8b8d5e17f4fff0c9a93145a3ba5be405d68de77dae26c653eebd5d38f6b222c0a7c7059f0c93cb743829ff81f508a2ea48292bac4384a33e15253f005fb8fde043bb390b4667fb46fc3d944e0db45e02deccbb750f746f5f740040cbeafdd7c0d801dd700679f01b48810b0072f54a2a803118b39945952051297c74f6c59d5f6c60fcac103a181c10a052fd241b342934690bda2970e450a4a25672cf3ed6d39899876ae3da0e5fc8a5e3fa95a46af293aa0153d110a066532268be26e59312188e451c9166b3f6893b1a6b3ad2c270fd3407bc087898457918f4427d4b3ba98cf3a6dcd66da2299ccc8147da34ebdf9f3997a92c6aceb34eb3a3916198cc53371b95e4a2af1c97d5d4962168dba5ccbf94926c3312918075b890e1a6a4588beaac0af54f02545ac4b69d204df07ff5ffee2a765ffb5b9361178074420df013e6c5cb887113090be7eeba6403f4516a02af0a60af4827c17ba92be5407d096525abb00a409604ac0a1c11af0f69bbdf5451455d4a0458d48302307d76020896a92fb948c8c5014c0b69fcb4964b64522d9dad29507dfa771fbaa9ebf7daae3a968880ebf529105ca663fb16d7fbabea864c8e05c4910f6814ca57a96d05c0e9c57ac209f5b0cc80c43c58fb1fd8ec888dc24bc8cc42129d15bb0534a365be2f29c2a321198c193c01e55ec2631b13f4549a793874b4ba5d4fa29b80910e32a71205ac84a11d1281588a3ddc11155f8b61701df6d5c73d7c463ff64a9ffe77ffed7f5b9cf7d5296a17b736f22704f2390f7d4fac6f83d8f80ffc3a83b77f694f9ad8f42085004c0025e2440465d009d650a09a424db4ff4954cb91d910dd0c6b16a60db68e0dc01bc551b3605603dc19fb45c2da88f5015586a1105fab351aae34d7f4e122924a7fb1e799f2edcff7e3d7767d0ab47a39622894451166cf7107ed840c83e74b28ec0d5c90902b01ddb4a6352a534d856561d4e1ee330e2cba0e1ec476e47fb6b1e096622993459c68742255351817cda89f20c6c65514b14853abc922e8517c8d91689a2c9c20f49ed6c05b9a45d127d8d425942742a22e42b833eaa099955f1a505c89d54c823da3f5cea735f7a5e7fed3ffbbfebd39ff96dd9d7d6bd796c22708f2290f7c8eec6ec3b200206a0af3ef5e5b6251200985df2c1b100b12a2705015280301d0de00c7ec8950ed004c83b80be67556270046379f11f3502ccc6be402ed0514aca632be03a00d4cbd512f01e35906c26786239e242e82ed6d9cf34632b69369febeafdf7ebfec73ea49b8b5e5f7fed58fbab6035d22bbb5ebda9ef15998ac0521654a44aa6a47ae637252d9ab46b9be748725be1c36ac00f564563a391beaa916462f2780ff3385493308a7afb46d2eaa102ca77504221be423c85fd50524b06659eb51997f62db4eee7003e43480554f16b7243be2202d34e1526eadce63bb66d057274acd518fad2979ed55ffb6b7f4d9ffad46faec75b68439b08dc8308f031bf07563726df1111b8b3bfa7175e7a4102b82202805b7f1cd6b805b0e9ec02e123e0d66c3225b30175e98a3a56037dc7b900c01a888fbce94f90412f2214a06549eb8d068e2b2791e55206f091b7fe8a6ed1a5e00d9e320abafb19670f5b6d5bebf2d5ab7af2e39fd0ece2c3faca8b07ba793c6ac521bb3880effab9e6f3997a12490715924af17874492183ae496f5c15c03538574d248b09fb13d96e741299869640dcaef0646212b824ab0bea0c2649568aca5985a0898451c514d732641ccb268fa0ce84e5d2491996d6eda6916a85a488b3d8c8cdb5ee899838b957cac6a59c48b87b770e716bc4f7494f3df5bcfe4fffd7ff9b7ee777374984d06dee7b14017f7adf4ad31bddefd0084c00e4379ef9864600dd75bbe9954244d1c4aaa0811732e60375c0e1a4f0dbb300c0083927942cea92d5c0ac5701b805bf72166240ae2411b723429145057067774913f616ab9516cb959600f8087304cc2b00cd5049c9b05496428e989120b674eedc397de0c34fe8fd1ffd013df5cab1bef8ecebba75346845420beccf48386bea59bd6ca92b9d0ce2203df784efcc00fdaca9c4e4e4b9355268628e151fa6d5a08a1f137e3762a552a715b21332a36a73aeaaad3c9a9762745584d4f4b227679b5d4971abe07fa1625e41a828d445aa6434820547edaaf816788911b908b8e6615815ff84f06a920e5881f00dd348c25b11bb975fbaadffecafff0d7de6f73e8528028cdbdc9b08bc9d11c8b7d3d8c6d63b2702c727c77ae5d59725d00afc72017619100340d4fa02ec1a902113484413acf4d12a6b304c3e4106ca72b60a4940d258ebbfe0ebb19945a60e40f59697004383f60060fb17f34e970b8ddef602c4857e6ed98192c9b86485437242f78c2dad871e79583ff8e33f25ff1f23bffbd42bfafc333759910cac48b041d298f5bde6b38e2432935725de7632850295eb6716f40684fe1057335891a04e0484f3f6db55a3796864acfb2be5841c7506962275e8724231cd588d6df5a959672a6c79857a6c74259a5ca14ce4033384a0251194c964deda26ba53edaa3cbd7a3239c9ae56a3f60f8eb422c98d50654582889e7bf665fd8dbff99feb9967bfe1e6863611785b2370f6717d5b6d6e8cbd0322b0bfbfafd3d353e3a50c620dc026aa53a55995805fb212096053be2a0f23dfddc27dc88413449406d806f002905b74e00db902c6debe49c0b3eb8adcdf75bd0cce2d8920e304b2603562809cb0ed31c6f489e465ddd536cec61a802f5fbdac8fffe027f4c4477f50cfdf5ae937bff09c9ebd79a4a321e5ff6b24b0dff71d8964d612c98cd5d18cf11d49a894a2885441b7eb2ea9ca3eb21fd5ca90a87a06137e52e24c2a9408722ba3b6f119493d54888913896d944c750895923275b64b1d5693bd5b0a7dba7ba11f2b6d05511d18da186e3e44b02ea173625574b258eaf8e4541389636255e23809d9152bbaaf7ce51bfa2ffe8bff37dfcfe3bb5ad7e5e6b989c05b1c817c8bf56fd4bf032350019e83837ddefc0725a09759248315e8d9003bd61f8b9aa017bc5428a218de440d92922f06a98122c05cd0d11ba433c1bf8aeea9812258874caae41ad4b7e6db32e0563afc263db137b31cbc9d3572be20c6341895b0829bf2d5a15bd8c85294e8bf7cf9929e78f209fdcccffdbcb6cedfd0bffdfcb3faad2f3eaf57f6971cb2cf1565a68ed5889347c7180379df173989345d61601ec1df8a956894f05c1349ac4db4aaf14b24fe53773b8a7c258f1252c94027848da41e118a08e42194383165a650015f2dd944887ee6893e715592ece41505930d04bde2989a0f08c0c34992c6a4250964b51c34b5b8115bf43303baab7fee4bffe6d73ea95ffc87ffa0f5a376736f22f0b64420df162b1b23efa80818840e8e0e649c4a802f8d6a904b7f20aa810b80721db622005cdaa0959f8c33684d729b2e25685ab253e9d842e250bb945e136fcd03c0b89e78282c03d0f600f9d61649a4eb158061034cb66796ab854e9627f21bf5389c8167787400906153cd8fec5239ebd591206e3c709d24f217f4c33ffa13da3b4dfdc6179ed7ef3dfd9a5e391c3546afece6cad235d9525225433dab026f6b75f8e2c4525c66a12f01f65487feae50dadf143c6117a25e98fdbaaf201fea185b3295e835f51e479da19c7720a3149eab44a8202bfa224282ebc235542a2220c2493c3cd1526817a9c586efc54492395d2e49b0233c920707fee6796c617e48d317fafbffd53fd6e73eff7ba8e07ba3cdb589c05b1f817ceb4d7caf5a78f7fabde28d7f6f6f4f0ac9a02603988206c0fdc613e8abe6c160db06112a40288026cb4ef453d638fb085164a6b214cd4922417dc5d6d46850448d355794942c9acd66c8cc955dd7c06e041047ce442cbfe04c64c5d6d6c899c8e8b311c0d36019110ac66676580df5b3be8dbf70f1bcbeef13dfa79ffc999fd6a56b0fea0bdf7c4dbff19d2c5d510000100049444154f967f4fbcfddd29d6568cc99bace3457c15e96a42cea0a4908f02d99e8852295007c0665843213e087b25016255693fe425f29e687ca59db09a9cf42db94e82e8a4c4584ee7e09b477629e885fa5ee245e1d1bea95390a597145044f6e724045d6720399fef874d97ef4b98ab857fad9caaaf0ef8ac3d6cd9b87fafbffe01775ebd62d0436f726026f7d04f2ad37b1b1f04e8bc0fe9d3bdadfdf33e628f9722502e0aa80939d8d04d883b7dda0cb7533ab6043a908da340c60609c021de605806a606e6710ac100c80036fce13c9603d2414006b016cd749644b99e80708bd9d35b06219481e2bf6f54d23e38cad60ac184891f28527f8e69a54669de63b73bdff038fe967fefd9fd5f77dff2774bca8fae2d75fd36fb0adf58d9bc73aa97345b7a5be9fab67e5539c384cd8eedca614b328912a0915288a82766428424a1ea96865e77ee43aa8272975a590a4ba96380af56863cf64d37a42cde12a918ba5b3494dccc8310aca94af2accd04d8a41c6c9c3319848a42d81106c6e55be4f2d1111271ab28f59d6c9fd377fe7b3faa7fffcbf865dad70439b08bca511587f6edf52131be5efa40818b0befecdafcb402d19ba2acf68c01841bb025e009aceaed61b7c4cc040b36a7bf08c815a0088550c6b64902d806b4b22b35e254bdb92aa7ed32649809c12aab20440deb10a99b11a410e10b6028b0d80e5e01ff35d2de5a4e2e463be755440b562d57aeca532247c2bac26665b735dbc7c5e3ff5733fa3bff0977e41176fdca7dbfba7facc575ed2ef7cf5653d7f7ba94590b0482285e49199984cb92c249159d7a12ac5633d7baaeecb523013ea4aaa981857226917da85be54c28bc017a9b54b06ab96944232551ed3597ff39d04a0467ae36af321f6628ea274b8562453c760643b70454c1c8737c6553461c23fcd562b156c44a44e3868ff2fffcedfd5d7bef69537747f2f56363e7f6f44c09fbcef0d4f375e7ed711a800d30b2f3eaf175f7a0e1c0a08105255f055ad7dfd7a2c1ffe9a12a08f080960e2214bd3526b03800950220cde313a0a7cc9ed8850cf0aa49f75f298912d2ac4a98782a778664915807c664007bc33f928e2df84ec6a1ce483e5152b9215a8d940d4ab11fa3d8760bc758418832db94d699bf359d1873efc847efe2ffe823ef8d18f6b363fa7175fd9d7a7befaa23efbcd5b7ae52834966d75b32d15124760d7b6b39437561119058de896282521c3531141628098b7c744843c9e48b6be6048b487e04b25595de1b338c5d11fbc481213e4b9b8db2541448238f28c0879f5411576d5c0dc576cf135593383075412fdf852ad1fbb493db3686fef587ffd6ffc4dddbebdd9ca22529bfb2d8c001ff9b750fb46f53b2a02b7f76ee9939ffa6db6affc7f7fac5dab53258518b82080cbdc88d01a8c40296e59a275a722021244096029003190ad81a042febf594bdfa99014fad90c50767fa8619ca48abc222843a5a47a407cd6f72a5944af9a3f240d83e648021979fb9ed08f86753f7e4cf6195e95b9d64517376c7936b3f95cf73f70433ffd177e423ff5f37f4157ef7f48a78bd4379fdbd3a7bef28abef4e2a1eeacfa9648a29b2b9379311797a52beaf0bd90509c1c9a2d2b46bfcd3911aee7aa362e998be88848c191425c3c109c98070e9db5991d3e7b2e1e4f15beef2adb352f2214c17cee765256740c6c55ad9c549bb2caa00939ec45aa940ec55286489633952ee80bfdfee7bfaa5ffae57fc22ace2b45866cee4d04de8208f0297c0bb46e54dedb08fc31d6bd05f2c52f7f51874747ca52347236c16b2e92010085fc95805ec9a28414de53cf753fbd820c72221354036a48ad6d3c83803d0528562445d0c9ed6d9fbe9fd14600f0ab50f0e61d11001e02992a255b1269a00d7047840cb22b40739d40260d8cf12f264ed8ad006ab32b7c838fb8822fb0d60e6944868ababee8f295cb7ae2838fe9e77fe167f5f013efd7802f07c7555f7efa967ee34b2feb99bd49a7da52edb795d929eefac3d80ebf6624c28e5815c86a9b0da19de944d82a0d4a99a8d2c3b31217d7fe60e9fad4fa78100514a0cc0bbe8830aba90845ab238d8e0939110b31a7a9ad42182231e7ca40932295f8ac0812c54811dadaded1eef973da397741bffccffe35f4cfe4ef7d53bc796c22f02647807f856fb2c68dba775c04569c277cf2d3bfa3e79e7f069091b6e65bcaae97df6c07def2056e659230201049953d758355ad232066e013220849f44523709c3e788059144a3e4906f68a24528a00dc00b64c972924cc569d2a5a5cad8a0865292490a2d9ac574fddf2e428042aa038c8e721036fdffeb3274e204e10b633b1d555f160ed67150368f1a45ad13b01b4cb71a57e6b4bd71fb8ae9ffdf99fd64ffcd48febfcf52b9aba2dddda1bf47b5f79599f7aea26e723834e3557cd1ec00ecc870abe9492eafba259b7f6312284d3f245cd85ec8beb9551a2526b50d809baebbab48ccc7513de590884a850205f112985ceae2a44e9aaf8431c08b6cf82c25ccb3030820ab77507be4e0c3e3a59b464b15a8d5a2c4ff5dacd3bfa7ffccdff97fecedfff7b9ad071a67c536c22f0a645804fed9ba66ba3e81d1a812f7ff5cb7aeae9a7b4e42056062ac02601fd8c00a09767e0128a0866502929da4d1bb03248990c7ce0b2610c709b241a8d4f47281891aad611e85095b7ab9204e2377b998f842ac3da838ae5f043c87805d2b302e978ebcf821e006f64ef7f42b74b6f65996c8f916a65ab58a975baac8ab41fd50d988159125829ba74f9823ef29127f4effdd48fe9e1f73da4dcd9d671edf4ccaba724915bfae20bc7babde8b422914cb588b37c6ca82592c49f520af54e5dba2c42b1a6ca1cf17382980af668c39bf07982e77e97b05a1faca633239b9f11d1f444506a7d59d6fd0e91c79a86a1e20ff1c6081624c40b7ea0057d45d6db6c495a7056b2582c3943aa326f3586fec12ffe929efee6d3f46eeeb72102ef2913f99e9aed7b6cb2de02bab37f47cfb0f2a8a04c803c9909365580470062d1f6d6b68cb9c1a39210844ceb04d4b8dda20920bb51fd0e4c9db76d03ddd4809236d856bd6a11174d06c87d96b1ded261d39f3418e112b166d4da430a6cdbaf8e2da6aeeb95b49b25fc99f0db07ead35459918ca8ae4d77048a9a3f13ed1180e6a6ddf8ba5b470b3a840d83e9ceb91d7de0038fea677ef6c7f5233ff07ddadddd51c5de9da5f48567efe877bf764bcfdc1a753c6d691533ada664fb48f258542a3119918a10c483b9ba6fb48fb643bb726653992755b50be1603ee6e1bc4229be010ae623ae08fb28256544f03d21560c9ed049d16c5b3f2a55a7602ca454d819271153a4320a949a48364eb4edcfc30c4c2c437bfb47fa47bff44f649dda5c9b08bc8911c83751d746d53d8c80c1e1f0f08037cdafeb939ff91dfdf27ffd4bfadb7fef6fb17df15fea9bcf7c4360ab401800c8b0145240805029a9be9b49805040e2b204820ab7fd09a1445afec9aca03e8a560480540135883106791a726b3dde32524460d64aacd1045f10424e6ac2b1929d0a6ff81dbeccfa5e3de42db61a0038ab9081e5c0c056db008a4e0d78c3a654eb4452a968b37e745213f6109368562ad6e1122724f4bbbc76e39a7ef8c73ea19ffc914fe8e2f95df55daf057a9fdb3bd5a79fbea94f7fe336db5a938eeb5ccba9d33026b6b089af4e18a8a55de5af9094c451ee4387301c113cd5f8118c85ef7161b91057952c03a5421450b4d54d44c8b20828c48568659ea2455595525c13a5fd88080549c2f18b28727c2c7ebaf076d680bc632e7df2539fd54b2fbfc4c8cdbd89c09b17817cf3546d34dd8b084cbca9dedebbaddffbdc67f44fffc53fd5affcdaafe8339ffe1489e469ed1f1c027e9392afc5e9a9d8015144e066959f0ac08dbe0646809c816a32fab44e800784aaf01380021f19938a0815a88afeb37244c6ab1d83fc08e08ffe2d69ebc1b7893e0a8419c05dad1358f3f8c0be6c0bc7224206e28e6dac7e36e3dc819548a4eccfc45bfdc839c800ad5c87aaf533188fe4cb328a093b152e4aab34d930f668c11314cad241a9d24b1ff9d813fae11ffaa8ae5eb9d8ce6094a9c395f4f46bc7fae4d76fea0bcf1febe669d7562353748a48093f27749a6870c73a99c00bc647acdbe2aaf02a7e52d032dfaba50915a1424c3b645bc9b8826ec7b8225c898f8889e3ea732a971371f43c445f44a023155968c18990441b1d85f8056542e330d22959f7ebafefe9939ffef43a26486fee4d04de8c08e49ba164a3e3ed8f8081c6db539ffeeca7f4abfff65fe9f35ff8aceedcd9d3380c0d24bae878b39e697bbecd36d516805574cade382ff3005e6802ad0c4c95d26018806fc3a10a7c9100420138559e5a137c304abec0366546a309a909a01ea169aa328d18194924d6eb765b9dd08728baaa10124e28d02138d6811bb49366e07707a077ead8facac00feb060cfd7f890ce3d8ce03bc1a710cb0c81874da3fa81ab031647d8a44bbe447e591e2aacdb4ca6c0ef57ae289c7f4e33ff603ba7ce91236b79465c6065d687f31e94b2feee9b7bff69abe796bd03276a4325702da11a9f057f08430cb56d724cfb3cd97b95698772930eb78212acfa730be64aa2ba152522599233e3b261e6fed6eb6367a0662695de27259b1bdbe4311a144178aa5e046978841e349f834d05fdaf9d76f7df2933a60950a7b736f22f0a64420df142d1b256f6b04fc67d87ff393bfa97ff88f7f519ffbfdcfe9d6addb1c8603ac806c056c328bfcbb10dbdb5b9af3369fbc9596c2db2ac072727404d80d8a08198cbc5f2effb415405525f8bc69f3a9582e97609211494d2e4a2a0c4e205b007c19063e88524a6424270603e008c8a38ed58f34e28ff52ac4c82a5c70b511e806b3329acec69964132582245234efe72411fca13da1735a3147c87fcb6b20a93869d98ee84733ba0de213256ab11ba4828a9c7d4a4993cf6970c0b281df653ed3ee85f37ae0816bfa6fffb77e4e972e5e50c7f659e9668a6eabfd41c6570e56fa8d2fbfacdfe38c647fdc96fa5dfa7a099baa55c197b8a8a2df362699e576ab2097c42e01f93e8b66b34eb379a7be2feaba5e811fe46b49417c7822afb3cb3a2a75c750ad3f788a18a5fc1f7925fa024ed4f6643cd298277fe19af58586e50ad92246e8773ffd797de9cb5fd2e6da44e08f8fc09f9f9b7ffe219b11f72a02abd54a4f3ff375fdf37ff54ff595af7e59135b39f605d8d004308225caaea89ff780535166aab59d4000ab34e044eaf0e0482727270c9d9400d604c81af42790aca2c7c0d5f73d20544902a318425daa46a608c6a56ccb0fb74aa196a951d284cc840203fc847f3485dad6571bc44ded8928b23ccf9297e14ee848283214f8da319759dfa9e03f0c0df836b28d35924c1aa1bc5a05254a65198f4d8f0f7c14445d74da979628492a6e33197689262522e72eece8c2c56d81c3ca92ea665bea673345449bffe1e94a9f7eea65fdeaefbfa06fbc3e68a91d459943459e6b482a994a6c6552462a28bb52342b1ddb719d9c3c3c8f425f4634ddf6c914922a145a7f51152174d1ec8fcccfb18b584b6549093d93476147e1e855619971487a30b20599a07fb95acab68755d517bfbcf91327da5c6f5a04f824be69ba368adea2085400618f730eff16f96ffdce6f6a6f6f0fcc006c0008434f2501f80d3d6977a55707684529527003205d3fd38c2d9b39a0d801c6fe3d90da0dee400000100049444154c5e942878787f2f8080310ab08006902a427eb83fce60b0ec97f6fc97220ae108500294682568a0c25364aa66c1f936889b6f298f07b449fa925a749eb443205430d86e80946200743c15703f14895922d09f6dd4c3d3ebb1d00e9e42d3a6824990e2428272a2793b50a7432b6a23324fc0062e908f4d254e26368fd910f71e15bd067fafad3cfeaf8f844914511c4823efbefb89e9e1eeb80bea75ebca55f2189fceed3afebd64972c0de931c438ae00ea520ea494cba926d0eae8b2b22784ae9129ffc3da5f0b435126b22217725e398a6261efe654ac78d892057954d037352b49ae36d62d688f8a926c74311a1ccf58805ab49af28238a7ee7539f9157b0da5c9b08bc0911587fc2de04451b156f4d040c34cfbff89cfecdbffb157dedeb5f93ffe4b9a122c1107ff32aaff703670ee6955200dd5c271009109a34b1ad6580b5fc7c3e078c2d53b4b3b3a3923de72607f2d948c994c1c82fe8b65901b0a8003d7aa4a04f324f671747268a086516ca542a548232434d8f90873702f2d667301e0c942c535c17ba0d92e0b46c53c80a440da5645fd05b48845d57342789ccba5e591291dae6349ccd6b6045d2f4486afe5921db571365a033214115b2cd8840b092e070c43c09004f7df14b5f93dc27f433b6e9c49fe5f258de32ebbab9d83dd3cbb70ff5a96fdcd66f3db5a7570e528b69a6a1fdb86fd584bcceae36676258cfda18457d34aa8ac6add8b29ce355124fcfec57ec8fc4cd89c5df3b974dd6a382d10c8f405e610de64289fd491331b6ad2e8aa240c43322b45c2e349078bffe8d17d8cafa14f29b7b1381ef3e02f9ddabd86878ab2260f0f8ca57bfa45fff8d7f0bd0df0117ea99a90a504000d608813dea67bdfabe53021c168a08e025a89aa4e16cdb270164bae431db2494dddd5d0e568f759b15ce38ae5400e92409a05d63e5ac049315b03f3d5901d0a8ab3050607012252dc9261a5545042465895696484a835b05ec4283b7aca88d648d8aae8026855cb7a20cc92b1d58cc2554b290140bf39b539244684fac604612c740120167d1142d1e0d68abb42ea9c85748f64981dcd4ec60b2e9a64306fddb7b77f4ca6bb75adf04e84fc48a864e38703e393ec0f696faed1dcdb77715d9ebcec9a9bef4d29e7ef3a95b7a61afea78ec588d8446e2344df2d03362ce76037e9b87013df087f9cb177d99a9be4b927e695cbf0c341fec0734c0a54069280252a844c1ff9468f3a08f27939a8695a669a08d13e16252a0dfce64444b9a277c1fffd6dffebb3a3cf2ea539b6b1381ef2a027c0abfabf19bc16f51044640ecab4f7d459ffcf427e52d87096432180480c24ba80c0854b15e01d60e00ea550c508091008f50b6764752e9ba0e90ea9459902b00e29c313db83280d5a376b7e65a2c567ae5d59b3a3a3e446d4536151180ae41705200447e2b1e780d4f808f8152f8a641a992cad235f2f64d6f5b010f126fd46119344704cdca2a62124db6b42a053ac46510345a02b83025646d3748464d27e7329e4b455f456ee48d7a20e90d2493099e18bf2ed0c758c7ac3a61113bf982e7c272d62d6627f47cf1cb5fc55c02b04e30933c6e18963a39dea75e55fa997c68dd11dfd9ace74c634b0be2f08d9bfbfaada76fea9bafaf744a1219f07bc4f4643f286d23997f49e290216e8938841f920a319bcd8819fd4e5ae33869604eb65f9b8e2aaff4262635d1f6f8c046c994984ba09b82e954fc1c29991173b5cd50c88c4abb78206deb5dad16fa1adb759ffddce7e8aeda5c9b087c3711e093f8dd0c7ff3c66e347d2b02fe87fef56f3ca5cf7ce65300edc83f7d013eeb6f55465104200ca04c004b2a00b7a204243213b9d0441fe8a108ea80520524dd67c00acb009781b92c45a2d2b12ab9706e97c432d3732fbea2575f7b5df6a1d01f81003853b1851a59f782f3076f8d65a4c2765192118a08654915f4b9b4de59dfa9440a49685da2aee999d0c9ad0ac8d96583af5cb17ff81989ce94923917f476e8ea5b1229d862860c9e78ebf6dfcbf2d88afdcaf850a8ae19d6249a42ba15b62de422a2e9dd3f3cd6b3cfbea0cae426963395b2f2163f914056cb5385d6d7c46accfa338bb2b0da9b6d8bd0eaa5dbc7fadd6fded65337573a1c7a0dea345646717b64c5033729da9cabfda3af64621f3fb137f0b230508e286cfe316f1bb6acdb6d4585b2849fc94cf09dc18a822204c2c2f457e291a658732a7115f6162baf63420376eedcbaa9bddb77f44ffed9bf64e5f9adbfcaccf0cdbd89c09f3b027c1cffdc633603dee2083cfbfc33fa9d4ffe162b8f45b314910ac0a30206238061307291f0bc7565c0f6df680aa42b0012c8d53332d08c00e304980483eadd3ab2969778d2d10e5a59858c80d80b2fbda2e75e78414b124529005dca2665409ad03ba06be4dc65e4cd9f5e34046652110149914599f4e05f29453dc0df9594b5e002a590033c159a0035f3ac7732700b296c54af0e229008748932d55b57979a914402fd6dcc991fd6030603deb5ad6a1880ee4913be56778815067518c22d772bb2e8d5575fd3fec1b156c854237d605fd2e2e4a0fdc5e211ff5600b0417c2459d9a607177c99b3ad35d5c2b9c8b17ee7e95bfafc0b0bedaf4822f00626d56c572a8e1e46ef56030519c423a2ad7a066c4ffebef17df0f70df3dce1511af079e07b627e788c7bb228c2844cc060ecd4b6f398af9bd0e0ef33062b763be295c4ad7ab5369ca2b7eaf7bef0657de92bacbc90417c736f22f01d45804ff177346e33e82d88800f6b9f7afa6bfa779c7918d0fd8f3e005e93009b92296e157849bb676baa6451d28e4c451a4d001500a5027c0630036644c8fd3d5b555b80def6ceaeb6b6d9d7676b264b8748b455ccced64c3bdbdbdadd39a750af175f78597b9c0f18029da03242138063401e00fba581155b1390e470f8c74c834af2c8337fa2d0a0dff6bb52b0134a71a1a7430643e89c004a6fc18410155d94d802586d5b8c48f414c617e6dc737633eb7a735bc298901b49668df0a702ba15459524649f500650af24805880b4fd65884e39cb78f6f997b5588eca485c0944464dd34ac707fb76b2f93200bc3e841e01e5daf48f68c72bfcef899763b87778aadf7be6357df6f943ed2d0a6722495292462633e0cf8a2daf917242abc9f501d077692315b9bb75e19cfb06ec0d7c1f47fc0ec615021b11eab228a9577b4147fbd3ee29d458b334d1e75a10af8820918e3ae1cce3f8f08e060ed34f1747ed77877eefb39f232ea324946fee4d04be8308f0b1fb0e466d86bce911582c4ef5b9cf7f56bffe9bff562bfe911b6c0d180900440480918aa434cc45aaeb3a25e09f25949990deb8228b92b7fe2cc918d1972ac85780a902804a29e81b490221e91cdb57972e5dd2f9f3e7da79c8d6acd3f6ce9cf645ddbe7da8975f795523a05a18d3e14344489180cfa4634078b55acadb48c6b38cb07a05fd825c66292a5096a28097c5ed94509349c99b3fb82e105013206bc09cec2bc0590156afa89a1c034a1674756bead0470cbc2a1b3d0e72826bc4f840bffc586721f9aa0250e9b3be05e70d2fbffc9aec7f25d938b904fd470777e4ef8165ed57c58f81d5472392c984ac6d28033f52dd6cae2c332d56555f7de98e9e7a6da1a355d1c0bc066c0d5e25318f91fa888ffef15c93eb13416b73c44fdb36f0af98d080dc48b9c2f6c8387115ec45a622825130deb869235321eb4042ddd9f77f74022251ad4e8f4922fbc4b76a79bad092d5e68b2fbfc2f7709340de08e3a6f2e78e40feb9476c06bce911382579fce6effc867eefb39fd6c83ff66c20992a94ad4e5900dd8ca2b80b22b44b493931946ea6197bf2b3f996665e5500ac23c0737ab2d0c1fea10e789b5e2c4ea4904e170b1d1e1ceaf8e89866d1ceee3660938060b63f7972fefcaeb6b7e7ec970fe80e5dba7251a7cb41bff7f92fb0677e07d92227b7f507a782f993bc5a5a9044c03cda21bf7547840a163a4991d4f03bed6fa6a8aa2425ed2e2843f0c28202038512de9a815237a006b201484a4ac681cbcabea894a288d2e447c076022c279202588d9e0a3180f13cb94311d164ad69e270fd9018dcd93f683177dc43417da5833bb7da365865148ef9a98aee11dd2b565dd513454224e35252735644db3b3b0ae27e70bad4175eb8ad676e2d75c2a267c1ea66c5d895c7b24a5af1fd1d688fe81819ef4c9091f2aa425c23dfb715b2b882ff5503752701b70b727da64a26cd8a793c448f3c6168445944a8f0b210c878ec44bf57b687776ecb7ea7a4cc22cb2cf92c54c6c1dadc9b087c4711c8ef68d466d01f8cc09fa9ee7fa82683c10ab03d393dd1de9d3d3df5f5afe997fee9ff5f5f7fea6b8a08294301f91f390c192c3d0ea8509404c07b15de2e675da7c2364e47f2f01983df9e0da003db1ecbe58a37cca58c0d06a4838323bdfaca4dbdfefa2d9d1c2f00fc01d5856dacb9824f4000360a4945da629b6b777bcb55b6784e64d0b97af9a2eebfff017deda9e7f4f4379ed18ab7f0c497c4cf08313465fffc9f18ad560b054add9ee89b8401f9aaf2765216988c55a6822fd05005e0158a1232b8df9df38456eb6024096594fb1d0b979945a5402986621f30b69cb80c9a95b126cfbfd2d7e203e89bc780a6eb8404727c7c2c30965195c437e8e4704ffe2545e144e0a3a2c87e46d846150121c90cd0c83aa43276d2180c675ea5748ae875e764d0975f3ad0cda3510bb6ae56ac40062786561f3462b0f9d5925b55d5c876d720af6ac2baa0d56ad282e4c330ec047a535920623531428297a6a4352153d5d15fdae7a2b0825ac1472a0b2e8f1af89e61a0f13a742486fca75bd273d4e6da44e03b8b00fffcbeb3819b517f7a04fce677ebf62d7de3996fe84b5ffd923eff85cfe9539ff9a47ee3b77f5dfffa57ffa5fef9bff865fddaafff1bb6896e030e45c93fecae1415282230104a4991a98880efa4d1a903240219cb75ee23534c248e1587de4b928781a29f75bc19f7da658571fedc39c6022abcfdf66cb79c3b77bead5480435503a54211d8c868bab74920db24124c0294bc4603c017cfefe8a1871ed4c1d1a9bef0c5afe8e6ad9bca22f5a553324e5c06c6857d604505e203b655d691f485927a41b6a8645286c4b82cd40bbd940a292260c38b942f03ede837769074e00dde36502c4485a0b20b458478a8128309709ef0d77213005d81cc099e6322b25165a552d167defe9d43d58a8feb9f4bc6e589c4eb042865e24392104a519694bf2f8e352dcc57d9a7d562a9152f0381fe5068369f11f3393a43b78e967af6e6914e491a8e895721cd7fbe071579c2ae885062870a635871e16b9500fba99157289305e177c4aa10a7922985dab8a0e279a04e75aab2067f2ebc4aa2abc964499d1ced8b69b67e0f8e288a08dd7fdf0d9552b4b93611f84e23c0a7f13b1dba19f7ed22b0c7eae25ffdeabf5827897ff76fda4f557de6b39fd297bff2457df39b4febe6ebafeae4846d24fe21f72484e2e491450140048a0d24857ffc99e685029e016c3e3348416c33cdb7d87e02e80b3cf0435b1c905fbc7841972e5cd02ef5a63712f04b12462701b02b560806b2892d9c0ad00a703255230c560290eaf06777679be4b325dc693f8db55c2ed5f78595c87ddaddb9a02f7ff9697df18b5fd26a58a2bb977d330e57742e01fa93d3534d383535fde2aa5e04d0aaca0845844aa68a92afc04e2a4aaa60df1486bbc033fce25678b5bbdb6300001000494441542495d13a2961f856213e2519eb56153647c07dd004507b9e2389c7e206587a01d291fe51be5e7ce965ea786881b085d0c0b910ce28d0597ae6c5f7e5c2f92dbdfff107f5b18f3da1471fb95ff3be674b6bd0404c7c2eb224a64b1289938422e4b10becbfb67fa253af22485877b793146226b65e5d55d076cb85dd1858ad8ccc7160fc44b2730ca5c0a594b7fbba5288755104fe22e7794dce2068ed88854272022dc815be8f0b3e634bb62f9156bbe8af547a12ef8debd79a5e9adfbbf7c6f37b1a81bca7d6df85c62b28f03a6fe7fff897ff7f248a6feae0f090b754b613fc8f9cbe09b24cc994817ad675f2dfaeca48b57ff4d929b24067ffe4f907efb7fc0220789cdf7cfdcb7c136fb603e0edc377837b29336d6d6d69c60ac33f6db5dd56115b9aef6ca99ff7f06732709f1e1f69c181ea8af1e350351aa84014cc187b9049f533e4494c3b24a1dddd9d762652f06f22314cd8dcdd9debbefbee635b6c4fbffd5b9fd2edbd5bea00dabeef9462cb04e01b00cd254964609f7d3228b7790f809b8d854a2207e8b59f208aa208f320e65932155d2ae175e8cd129ab05f15aa388a2a12815b3424d185acd63c832a028e7303626c0f244ec7cedf82090555d231be9d9c9cc8f1acf0929514ce917826ac8402a5f3bed3934f3ca69ff9d97f4fdfff03dfa78fffe047f597ffcacfe9e77fee27759eb8349dcc7320798cacbcfc134e03f63ac659e7c9b2b20d35906427d5c956f11fdb95aa2972dd9e48ba6bdf2612d884fca015b146ccd24c4c8a081562e3cf0a35e121fcaab05e9445c0ed7b3e6b839c500a713b3d39d2fe2d92e4c0e7cf23b0e779f6fd4c972f9dd7c30f3da488d0e6da44e03b8d407ea70337e3fe680446fed13ff3dc33fad55ffb150ea90f01b55c13ff70fd8fbf642727839e7fe8994585e4918065e11f7b074ffc5bf6bfe70a9a24959245eeaf98ca525466bd122abdc157ac60169c532c0192d4162b9288c09ed4c6a1bb9bf79acfe6da61a5b2359f698ba460003a65df7fb93c6de019c1180c47e06b9200285d6fc98d31db24a51d138079ceab92ed6d6d6f01401776f5e4931fd495abd7f5b9cf7e495ffcca5774787aa4d2a7faae5741cf04b039b92dd9ea190031f9022c47007df40a88b6e7a69032530129005528221425994ba82b452542d988e9327e0238abf8c24644cad7643efa61694d935624c8111abc12413e104c25fabaf6c304d6e3b3198fad1a34e117222abca1bffffd0fe9c31ffbb076cf9fd3795675e7cf5d6c317cf2231fd08ffcc827b4457c6da8ad764822c372d092b3ad152b938a7f27ac3e166db11358c66f7c9bf8de56e621ad7923094893dac510be27132bbe09bf27ab5666a830ff9eefe79cc4544a279d8dcd4c799b4be8cc52481e4baa03e326ddb97d537bafbda8054912cb0a4254481cdbbbe799c3963efae4e37af4d147b5b93611f86e22c0c7eabb19be195b412a83c0ed3bb7f5c94fff4efb31dcbdbd3ddee2e7eaf8079f24872ca9e41fbbcbe2b6eba600ce20f3fd8d30b81780a2eb7b2952530090112ab34ed6b3e46dfe70ff40b76fede9e0e058e065fb0fa37aec484621e4e59aeb5264aad92b5ddb669aa1b74757e0f36903ba8546c047c05b602f8417b8245f11ca2cb22fb3f98c44d46b8b64e444b43deb9bbe6d92d6f5fbaee9034f3ca1a383a53ef5bb9fd333cf3d2b15a9631e890e546b00c0570657ce67c0509b03e80c9055e12fe432a4a48c48ca54b8b4ffa557c18f92d9ca8ed2493832e42b8292f94484129ebf1fe27252a8cccde484e5837f6f33b57e8638a1f94790116dbe08c70656111387cd8ea2e7f9d863efd38c955cc76ae8f147dfa7eb572f6b6b775b1df3ffc013b4af5d15a601ee414e92fe9333a6256740d675ca9c8f58858c64869104c6dde68e79ccd6167bba9afd81978f1181019f57ab412b128bebfe1e14e665ca9498260fbd71797e8d59518d335e9d9e1e1de9cecd9735e0075cb93fb3533fdfd5d6f6ae2e5fd8d2f77de449f9af326b736d22f05d44808fe47731fa3d3ad46fd6fb07fbf2dfaafa8ddff90dfd8b5ff9e7fae7fff29fe9cb5ffd8a16807c2149f8ad394baa98f897ef760ff094ae57944e8a5496228351964e7e2b1d0010bfb11b3c5680d9114070c416d8ed9bafebe0cebe8e8e4eb4e2ad362adf36c0228be1e12c59089d35d42eca88509a6cbb2f4a00bd9bcde41f399db3a2f05bf989f7c7795bb6dd8937ef06ba8cb5566bf2f88890135b87afa52feaf17f869e1973e999670969bed5ebe1471ed2f56b0fe80bbfff35fdd66fffb6f60f6fab47a6431e150d243d379f1bf8478905e0c1e41e4984a32a484abec4e7942255985c4076875e0943e9ae0865869c48326188cbbc1038e976aa121b1edc3015f2f76b04904732ee0aa01efca3b124b3939353f94aec2074e6c73a9e3b3bdbba72e5b27ae65cd0fff8fb1ed113ef7fbf3a927087fded9d2dbdefd10795a530cfd99ae80b1c1e39c79848488bd5a4db872469e66aad137e51c5afa94d3fa248e8e6db2e72ac963c96f8b5c2d7814482a04262ea211644d4431101879b787935239aa578de54b0dde3835bff0d7b7ffe6d5b76ddf761dfef5c7b9f739b775f537da1072880ad48d922150f69588912398e9361cb19f6f05f975f32329c8c38542451624f0024411204d137445fa8020a4da1faaad7dce69cbd573edf756e81504c6b90922c9458b5ef596775b35f73cdd5ecfbee2bfa6991806f559ab7273a3e39d3ed1b5bfddd5ffa69dd7ff0b29efad6d7b46213bdfdbc6d817f4b0bc4d7fe2d51df5a6899ac0f08e8cf7ce7697de40f7e77fceaedc73ff127fae6535fd7733ffca12eb9531793b698b0c5846d049663ae8eb6f396ddfa5609c2891a8dc562bb3922e81e138c368911ca8eb333f93b93393bd9ddd5a52ed93d2e04933dd73f0b41ef01ef2e5682fce9c989cece720db125e0ed75c555d48eeb93ddee82fb73ae2f3889b84cdca891cae4d5346f3604c349db9c22087ec7db8df6ec74cf1f9cc3eb8aab93456b821642066704ba94d125fad4549aa6496d2647b70d8175a6be61112949e5ae471fbfa35ffcc5bf0ddda63ffc833fd397befc259defcf95a05bd590c76367bd1231c33b81b6c333ef56a2e38afe2b8137b4b2461ababd77153234f029a8cd135929a790a94a0d5930b924a02998b60ecd24aac214689125515a79e7b35ef338c7beb17b06c040ac001ffae0276926109fddbca1a3cdccf84d3a39d9e8ce43b774f3e414fe5616dbd31b4792a5edc98d119c37f311ba6ed5aa8d64647bedb2eb8a8564cfa2822a1208b1adaec77b61d1d8c71ee42977b57132e9f8527417633e35a36f69c32a5228e491d087d35241cf4603ec46a6cdbcd1ba5c69ad1e169aa659472737f199db7ae8e656bff48b1f448f9b7a8df7561ffec86feab39ffba4f2abcc8397de7edeb6c05fcf02f5d7037f6b42e7df6c7ced1b5fd547fef0c3fae33ff923fde007df67922e2c0a4d99e84a6460f626c826f0cc04d604bde93a77953a935ea485c5e0e2f25cb9eac86f2a65c1d8f37e208173de6e75e3c60d6d365b6de70df94645f03066ef0499f3fbf7f4daebafb25bddabb54933307469cf8efa8aeb9204e57504c995c56051e4ea0004bf5c23984c04e0cdb4d5667ba43635dea13cd0782772b967115915786257c2d275d268cb5fa36d04c4aaa6e0cd6d86ff44c09a861d1aba99c07dc4fb910ffed4fbf5fef77f40dfffde8bfad8c73eaeef3cfb8c5602616c53819386cc917b8599e124d27e04d1831c4690d8367217b6158fab5486ff3ccb94b32827cdd3a12e687452045e78911ffdc513fab04ff3d0714d1f7066dc88bb8a0c625c842cc1e9e0ac2c34dbcdac13ae7cd44a5ffde6d7f583e7be8fbe4d05efc871cea21eeb148bf6842d37db591b70a6214f43c649177beb3c0b08a78ad065dd509e05fa7bc674e88cdd22c3a1bed31258640bed86cde72cd649f06d8e3670a43f382ac9368b4eecb63096f774f9e09edc4b8d31da22ffd9d94d3d7c9b77567feb5de437d4e167375df07ee4631ffbb07eeff77e9dd3c8d7d9c8f03e4d6f3f6f5be0af6e01dcefaf0efc9681bc56744f5079e6dbcfe8c31ffd5d7de6739f56fe4dc7987c6212575229816366a13822a86dd8e51781444c68d1bf6387bf32d1131092ae3851242dd7bbd1564d442326bbb4b0880466bfee2559ae52b5529b27897a27b82d2c14095af75848aeb8e24a403281208164cf6e7425308c44540cdf558190ca9643af0ccda686bc91f59413d2d40e81e49c6072897c6b821701b143833dac3af227855e27fab52a45dfdab49187ce9645e388c0d990b790814c8f3efa903ec84bf6dbb71ed5e73ff735fdd9273fa9d7eebea69945323c2d29bcaeb842a338f8d8265fb560f71dbaa63d7096d58c2de8cfe924e5d008bf0df6de60fbe8d4d0a52c697c491d5d0c6e121976c61ebd0f1e1d3921a79c24867e2b5006179bade8fee0fc529717f7795f7093eba359dffeee73fa0127cdbbf75e1feb4c4e30f93b5a8072f25bc0eaaa3ad8b638ad15766a6dd215e376c582055bf8421f1160adf8c01e3fd8b1680efeac7019bb003597cc8fc8539ed16b3b3725612140fab05d64b71bf274450ec1e4c1bd57594c164dc1393ad19d5b37f5d32c1c7ffbe7dec73b9c9b5af1af05db02aa86ddc4f3cda7bea60f7ff8d7f5b13ffa889e7fe1395adefebc6d81bf9a05eaaf06f6d682da33c19efdeeb3fae81f7c447ff8277fa0975f7a5999a08dc95a04df79b35126788cd7aaa925c857238888d8d5127e95a0d0bdea922ba68588c114d74240bbda1f767955c0b6e225f8d108aab1705ebeee08a82b8bc90826041043b7c1b3800d839c582e787791134c0270f00ec904874ef058b5ec16f8afa4434fb72974897c0abd664dc83cb163de72950526bbd107ca6fec64d15b087a5d26e09a280c1ae54361552178640a9d19b9a6a9b12b9fb585d609b4b6d8a641df6574dbe83def79523ff7f33fa7cbcbd2473ff2c7e3bdd18a8566825711605722d903de455c5e5c29341d5eab4484c45e5cc961f8c0a4a95c4326938b54f0ae791a0bd9f65a1637744b1f24a2c708d0d81f44b84a564177955b297f5052c07629a61db6eb8145a68bf32b7dfab35fd0d98d8df2427d21d8bff4f22b3aa7fd1ed77edf78ea3b7ae99573b5c602df0d2e54b04d516fd30c450ffb5f70224c129ca3c702fd9c3e1678ec582853dfe3172b8b8c2515762b5b22f12d21d984acb1f53c978ac69545a0c01f7c4a328d9b3972acbccc7fa0cef16de284f9f8a3b7f42b7ff7e7f5a10fbe4f376fde90a183a820742dd8758f9fef39052df0bfffe0813eff854feb57ffe9ffa8cf7dfe537a808fc587f5f6f3b605fe0d16c0fdfe0dbd6f91ae4c94a43d813ba78c3ffac41feb139ffe04278e57b4e57dc5c9e9894e4f4fd5e6a62993900967595525db6ac5c46612b76a048d4eec5b790fb0d30581e6f0ef032eb4bbb8544e1962c2e6ef525d52cf62d199c813c1ef8817db5b26fd3ccd04f7436aad69aea6296da404ddce4e350bcdc5f97daec12ee195c5a2abbf1198d8d12ef080acf21067e4eb9f327292da08724dd5666db6c7da721261f5194123d775fbeb9348820c1b73b04329c9c43302a5e047d1cdd0286d36d39071b399a1b5190bc7316df3dcd4b0cbd9d9897ee6673fa00ffcd44fe95bdffc9efee4e39fd0f7b90e6ae06fe759ad151457dd47a704354caaf1442782dcc23b9e85609ba910b76a0000100049444154801b7904001f554d2a970c8fc662b299666dd0a9b50648892f21b07a8c10b1a9ad18c646666cad6bbeb694dd3ceba6022f0a09ec5ffddaf7f53ffe7ffe859ecb3ffae49dc9abafdfd737bef31d7dfc4f3f45b0e505f422cdf356534d8223344a55a536ec3b41a60b1578ef63f295d419fe43db8a4c8822f3335636729ae02f197d2652234da1475e127c8ac57a622d5c4181397d4512b8b1cbf9794e478bdab4d1663be9431f7abf1e7ffc614d532167d3845d9af1d12456922bc6f9fefd73c6fd52fbf8b44bf7eedde3c4fd9bfae7fffcffa51ffef0fbfa093c6fb3fc8fc802f51f91acffab887af7fe5d7de35b5fd7e7bff8397dfccffe547ff027bfafef7eef7bca9f9f309373c3aefae8244176c3249c495b26e74637ce6e509e090ad215ef1f2e7889be300913185a1541a0c94cc82a26ac44e020d013986ca9f8cae29105e6e2e25cc14b7cabd6942ba1890094c93e279f2602c0a4794350d86c4710581254497b026b5ea0af04d79568d459389694895a0be515793aa1d9ca8f78ba1268c2bf55a9b5d20cfdb170413f0bdc05a7812be826902788aeeba22c24e229a117b29bb2a199e03d815fd09ae649138bc186b4e59a6a437d3bd2aced66d211767ce2f147f4b33ff3415e48dfd297bef40dfdd99f7d5a2fbffa92265e0e4f7393a0b947feabab4be5c929ac63b32c00d139fa2cd129ba0680dce4b6b1cba431562c0c91a3a6a686fd35f057d68558580acd051e795fb421d0aef40b3a222f37a8953099566c78c549eeebdff8be7ee7773eaedfff834fe9231ff9843efae13fd397bff26ddd23f02e9c04b0a8aaac6a245e5c838d2c852c5b3574724ddac5e8c8a8ac10a4155e1dbbae94f78c53d2c1d62b200b0b8434160e70f8280f2cd4aae46ab2ad09d94d5d32e276e5df9f3ce07aaddce89b797733ebd1c71e539b66642bc51e814f02011d5730a5d63aef3e76baf7fa039d3f60a3c326ea82ebbbef7dff7bfab34f7e5cdffbfeb33abf78805c07fbe9ede76d0bfc9805eac7ca6fb9e22bafbea24f7cf213faead7beaa6f3df38cbefdecb37a996b8acbdda52e2eaf9838e7048afbec8cd9a5b14088895bcd63d7bf23c8d9b36edebac555c83193f6888564a704ba9c6462cc04be364f3ace6fee4cd358043a341a0b4502c24a20bbb8ff80897b5f3baeae76bcd7d811205766b80340aad6d4088613743604e62d01b2730ac9e2b1021b5e7bf235270f8252ca096c0b6d4bda92039fff23222120b8092276696a05ed496d9e09781be584937f3d9ec57047f05c096ed1a1471ee4ce2e3d75599aaaa9a031524dd44b552220d548f3bcd1769eb5d93476cd1b6da6699c4c6ede381d7f0e240b49ab9997ec9fd4e73ef74502f632169906dd05bef77927933f896276cf2b01b7c214055616ce053d13701599541a32a04b516f7369bb993563af099e74aab318803a02b36d494636cb09f8b688e322533593da28bb1b993ae3b9eafcfea55e7cfe65bdf4d22bba7fff8271bee29a70871fecb52c3b451e4bd0b75cc4666b3cf3b4552157427595b1a2842ae0b028af94d133baae6fe8132c049dd161062fe353d0e3834c269516e4f234ab584042b0cbdaf1aeedee6b2f8e773e85cde7b9e9ddef7a87ce6e9ec9d0a92acc50b22303a3c98239ec07bfd62616f4ad8c7cf9a58e9c8caff0c3734ec85ffdfa57f44fffc5ffa4ffe99ffe3ff5a79ffc133d18bf3400d2db9fb72d706d81bacedf325902e03d5e423ff39d6778bff131bdf63a2f76d97dcf9b496eca9cd4c27df7c224ca0e74cf8cbfba5ab44bdb8ab900da72e5b3b2fb6cd34a40d81324673526ed3c1f6b26c0372677d54cfb11048bfbffdd082e1d2b6f38456cb9aeba915fc56561694cf885a038160f782e04fd958097e02d5922b5d634110c8ba0b885f73c6f097aab1616a0c02dec1af7d0b8babad29a05031a3b16b83df91ab9694b7b94b3d141922d68ce9a86dc4ddbed8685f048e173955308ef6e861c043ae28d16ec9040ab815f035fd0105f05e104a946306a04fc43f02e4d1336983704eba6393a54536ba5799e75727aacf7bcefbdfac55ffc79bdfefaa57efff73faea758c4b1a8e60df6844f87674e691338650f9d05bf15bd3afa2ee8b568153195567ac011cf04cf63c6619b94b1c07642894e6ac8d1a6a64bc6f40976e8b6c180b6ba5a35554d1a7ad21af8357cb0ef8aadf36bd50b0bfd0aff8c534e319161bf5ce90ab82be0161683108095ae85c247baec92f959593494a70b9bae8a8d635bc1dfb626e49bd1778e1dc99b1bd0267534b5f67d52db1c6b454857e1033b5df0e27ccf2961c2ae9bcdac471ebea99ffbd99f56d1eff084d74a6ef4ebe42215bc044fc8c836be3a690bee0c8de044af07f71ee8d5175fd27718978f7ef477f57fff7ffcdff4f9cf7f46f9ef01e2af3dc87afb792b5ba0de4aca67527ce7bbdfe1aaeae3fad34f7d82c0f53ac16dc344b3a6691aa931c9dc995a4c2aa23e77c3abb223bbe2be78cf0bf0040a13283747a79c502e99c00b300bf95eb90e5808342b11cd65cddb8d362c1627272704cc3376e13384053cbb574b3313f698407a7ae36c04d54cc89c2cf62c246b64200ae58568906c431f19c1d91e1f298167cf55d392204a40db13bc5682d78e7c47fb0e1a29ef096cf9131bc93b01a3d4f8d6786cab558da0d5d029d7225be40dbf4b4e5c57d7c132745764395c65f51170422b81d2b65077b425608d3435552b92d58a9c34b1f8cd9b8940356ba67f437dbb9dc6df64fae99ff9801e7fec097dfd2bcfe893b9d67ae565ecc622d29a22cbf9e53936dbc3831aa7299487f5aae83402796cd0a515590a3d04bf2cb69bedacedd1067e134dc518b1dc60a3109a2678dfb93de880460e2bf0da342bfdab24484a8c536c1d3ec3d6e0c71e9d015a2977787716fc3dbeb123ed198b953e79554e6cb6956a68756cb8127473eac8cbfde42b6d00cafce032d8cb23cd5940b053e354643aaa35ed6bd2d28e24ca1d3a59c82e1fdcd3837baf4ac06cb61b3d74fb54bff48b3f8bbf9d28fc65c91e5fe4a5060d0a323fa1d1c31fe1ec523166ad35cd8ccd8634cf8771b4ade8f8d28b2feaf73ef2dbfa17bff6abfae8efff2e27f73fd7ab9ce2f31b7cb18dde7ede7216a8b78ac69d09f7ccb3dfd61fffd99f8c17b857ecd013d8db34c9f630c3dc66a59e20546eb28aa41178f604e68540911de69e5d64e04e4f6e8c97e3175cb7ac0493048a22086d987c264f30d913c077a43d0bcbe6f844a76737594836bc645fb87a60218156112c6edebcc98bfa1b6aec0017e077c8179c85fe0424db9a5a29816f4b90df422b0b5bf89af6c6c40f4ff144d73df244e61d7cf704b9153a0bf2af5aa5ac90d0e343d9aa6a6ae0cfd862b39db5e1aa2cb473f77dc562b482d709a749a34cd0014b9854d761568745a4b0d7c196ad9a1a01702255a3dc6af06894d3d6daa439ef3ee079c282f8ae773fae5ff8db3fade3e3337de6535fd0d7befa35b956cd9b1971cd29ee72a4f0abd608ca4883001d59f6d131e3839eb6d52c9980ea12f8938e365bcdd89538098d0bae7af63230b76fddd484ed63434659b1df346f34649c1b381b6c41c0165cb1416c18fd039fc0df65f4f68ffc23edf18ffcd994d18f7ca04a9d0f722e38c84a4a1eb8c02cd0157d06b0481372cfd86a33354e044d1be45e18bbee59af5d35f579abf18070f9e0aeeebffa8256749fa60df0937efee73ec4bb8f47d5b02b6c917040ab30860d12d522b72d8f36b8522e7c026d54457b95f81648f9600f6bc21edbed66e8fabd1f7c4f9ffddca7f5e1dfff1dfdfa6ffe9a7eebb77f5d1ffbe3dfd7d7bff1b5713ae9e89d04abb7d8e7ada76ebd15544e10fff4e73ea38ffee14775ffde7de1df4cb62dc1ea58c56465fe8828c52499467b31895a33938724c9d41304b2abbfe285f939c17db7f4017f76f3d6a0b712bc061e13d9ad8153231d718d72ca55d5e98d1b4c4ae9eaf252dbe353ddbc7907fe2704b88dece24ae54a1baed24e581c4e4f4f351160f7bc87c8ae762140ca04215b4696699ab53ddaaaa66211ba52788767d9aad65435090998ecab1616a305d99246c062079f80e42a485a796cabb04382c40ced0d0bc83ccf3ae70a23ffe8f18a3bf6e0c76e2b864870e804bd2c1a9d7a0d5aa5d8d065cda9b7525553b5a4522320ce5369334d1a0b35f69db1d59cfa3c6943ba7976a2f7bdef9dfa999fff59bdf0fc6bfae33ffc5302d26b9a39454cc025f85ef26e2abf1a3d555373c976d8628395b41fa792c8463cc4864d0dbcd8eac6c9b14e623370aeb89e5bb8763a3e3922384e5952b91a5ad18431dd1e6b6af3a05b65785887ebb0adf244df04e64ee05fb9c65c189b9572272dd8694d9df7222b69a12c074bc87828748c987158470e253e0237ed865f6c3663b3a44d152764a143d3de938e6f3faa3db07b4e3a970f5ed7eb2f7f0f7d2f149c564d59881f7ae8b61a3a5b52a929ce898910238c2c51e123975436fd1a0971241a4d1aedf4d34b1bdfb6f2133ed3d434b39034d225579d3ff8e10ff4edef3ea32ffef917f5e18ffe8efebfffe257f5071ffba89ea33defd2629bc1406f3f7f132d809bfc4d54eba0539cf7c5975ed4c77951fec9cf7c4a3b82cfc23b810481699a95237e0cb0b22b948c9f77cd9920ecb66626716b25e60eed920edd4cd804a99580bfd3f925ef3680bb73fb16137d33fa1676fe831e3372336db525186f09801b02f21101ecc68d33e245e7fe7bd196c562bbd96a6a936c830fbd2a3560cf78397fc669a579d28eebb3c8beb21088a7902b8bcdd9e919b5cefb999daeb86e4a10a2015a7d04c3943b0167016f24ae57928bde95f6951cb54620a9040e784f5353d211725759395d5db160ae2c98097a83265fc12d5bf9810c2d8700153a9dd646aac27e8d441e996124372be52c1c34abd1dfaab49926ca93e679d2ad5b37f4f37ffb67f4f893efd417bef0753dfdf4d30aee846cb6592856ec7f811d1755194e526896d08ca0bdc316579717d8654f83695d58ac375c97dde49477a2a3e363e2aab1eb15e3b341df4945b03594321e0f3ffa903ef081f7ea89271ed1098bcc848ce17dc222b465916f35c95540776459d4afc73cb6cd2663e5c4b0e2536bda5968d72101a290778cb5a73f278fd8338bf06a385b1a34c96393195d379b499b79d2e9f156a7776eeb433ffb33ba3c7fa07577a9cb7b2fa9b319b04a554d05fc163f6bad2157786584ba941abe387cc3965d5a61caf0d3056c1512491a7d96640d7ad459f56497ca4d4e8276357855d334cd6af191d6b0e34ef96dc22c18f7eeddd597bff225fdfa6ffc9a7efdb7fea5fe9413ffb79e7e6a5c17af83a9de7efe0659a0fe06e9f2afa9b21034ffecb39fd447ffe80f943f4392c0d466260201a7f7a85d4c8cebc9c33cb3ac6232b57698b41b266e027b31595a2653604899047b769ab9da499e149c9b5c879c1e1f31992eb4278815813f41d2b68821da13bcf72308af9a371b6d497ded6a4cfc9905639a379a2612f99c3aedc704ba3b77ee10f46eb0e0ac048f73e5dedbb61a726e8f8ea173347e2368cf3b8fc3e2b5684177444d14d10a8f1579d2b62043825b6409ec2172042cd05800fdab35e498390d6d59e08eb542ebf2e29c93d38e6bb7fda0176809dba560492ed9567e4233791f358fb0dc2ab6b6aa91a7dcac897c4687564d552559e460323e459ae6d2134f3ea2f7f2a2fd85e75fd5e73efb0516c90b1db1f3b58d1c2b7a2a1a4cd40000100049444154eeb567b128ea536b6ad82c63e62ad956f45db8de09fd04d02db85b825ed209b6bd71e3443749e33f6a62bc1140f1913b0fdfd1dffa5befd5dffffb7f57fff8bff8dfea577ee53fd5938f3f46203fd6dc0adb4c0a8dcd86134c4d8aceebf5628160c366b6359e2ee5cfbaaf543a8eb0643c024b4e5c57520183c08a1e85ecad95a6a9699e271d738577ebe1c7f4d87b3ea8871ebaa95ffa859fd3ec455e77a3bf806d13b0e44f3cf938b26d65d53855f51037e2b160a4ad5c2ae80330f88aa7aaa9b5d21be2daa65c6a35a94854e512c97c91c0af249b319c7584bf1ee3f7f33c6b815ffe85fe25a7ec0bd20b2f3caf2f7de90bfa833ffc883efc91dfd117bff47916fe4bbdfdfccdb100aef1374799373459d9e97cfecfbfa0cf7ce1737afd35ae40a649dbecf4a759d51a60a8cd045899c44caf3161cca4c844aaf280ad564a7d06676282a69f79a85c8df40402ae862ed8e53eb878a007f90783d03ae5aaeaf6cd3311d1d509129b69d2143e04e10e727eddf282d344fe35f90a7c9b36b06fc49f62e21f6962125635b5692bb90951b8af5f757c7aaa5b5c956d0880579c06ae989cbdf7a1cb4c4014cf9e77153b6827ade89fc9bc1246d6c80aff3df26661db732dd66913f80bb95df0b79a45406872955a95a27f4e4c479bad2e2fae74cece37b41776cf833e3c3a98ca8351d26668e4a57f87769a3bf4570a454773c3761e74435b0530a95551a4bd9aec420e12e562919fe8bb7dfb4c3ff5b7decf989ceaf39ffb32729c6b8b1d5a951664d871a5b8709594d3e48cbde7b9699e0ee31cdd238a6d7dfffbcfe9eeebf987a11b19db6f3613573e279c4c4e645b0d9cc68266e89e60ef0d81f1e6d9a9deff81f7e81ffce77f4fffddfff0dfe8bffdeffe2ffafbffd92feb7def7c4267c71b4e9d8d13cab18e38494ef06e8c71c73e1dfac90bfdeca6bbe75704ce954d443f2c6a0b2e02dcca8622e3009a0a19f8686a4d8d3471dd37b552b148dd79ef0775f39147599bf6fa3b7fe767f4bef7be6bc0cfd346554d85dc67c8fa9e77bf736c4ec460ba37655d52882615b41a09f8e6a6b477daec526bb31a348abed4036efab21817796c98a474a854e044b65656811339b6f849fe9ccd84ece1bb63e1bee2a4b4300f7205fafc0b3fd49f7dea13bc2ff90d3dcbbbc81d1b1ebdfdfc476f81fa8f5e83ff3f053a9efef4779ed1a73efb69f10e7638f808cccd03b264b512937165eeaed4accc8b4eb0eb4cb962524c2c18d951254d4ce4799ad4981815c40013b85676f399043b02f2ee3a1824701c7387fee8430f130844c0b8d20862c04b0b130fbe04f2fb97e77a40cab513022acf8e85a1aa862c09860d394cbdcd33b426cd9b5937cf6ee9ecf406443abbf12be55431d1bfe524b2e7ce7dcfa45d13bcb08149a803e9ae9549bca49f49bbe7fefcb0335f0eedc00bc08e5e9dbcc88dada2ef44509db71bf837169107cafb904bae01b318e97ad1c06cd8518aace2c19aca2252d070350d7a452bba3809b90c1c4d075d812b2a65ab516ead887fc62ca4b4017cccd5df7bdfff2e3dc195d617bff455dd7b704f136352b256ae71ae38855c62bf3ef4ecb44a835eb5718d95df9cfbd6b7bfabaf7dfdab9c22b6ca425f409d9e6ef518d7542ea1037855b29ba6ed562d72c40f48d35c3ab971a427dff998feb3bffff7f47ffe27ff27fdf7ffc37fabfff2bff847fae90fbe4f776eded0c377eee8d6eddbbac9d5e3311b89d8327f493927a419ba455af183616ed18b1de21bca8300a6bfb536f80ebf9b6735de9f9dbcf3a774fac8137af89187f4f8134f0a30bdf6ea8bb22d575253b5d2ad3b37590c8f294fd26aec22d12b75ab406ae8619752ee2a709b72f2aad6689be0bb512bf26aa25345de5aea3334445a6461234b76c9d028e826191a13be32161164cec663033f90b0ff8ec5f382fc92fc4adf7fee7bfa9d8ffcb67efbc3bfa52ff1dee4fb3ff83e1bb0fb8a6df4f6f393b3c0bf25e7fab7c47bd3a25db03bffcce73e3b5e964748dbc3d98dc31bc78f539bc9915cd74f6732134164950c4c076722404d73e364b0193bda8909d1aa544cdac067f227185c66216151b8e40470c54e7fa5716ab3cece6e48dc4de4fa27bbb104da8949363129f7e0bc76f79eeedebfcf7b944b6502167d59101ab8aec3a9c0b6aaa59c89db46203fe5bdc82d02d576b3d15808b89e9a371be43cd20aff4e408d0ce231291fcbcac96141ce350b1ef99e147e6bdf8bb810b09117bc23639b0a7ea579b3d566bb8d7958442e94eb891d577839c9a02aa82b78c8273212ac141a41407ca50e109f55a9576ba33f3a1a5b5a793cfa5a958ab149dfd452aed19ec095df12ba4390bc73e7617df9cfbf7efdef7726649c04b6f6bb55f779a95bd0243ccbb632664e1d413ff4533fa59ffb99bfa39ffbd99fd7affcdd5fd6cffecccfaab969fc8a331b808e1dfb08f09d6bc81df25a42966a4de552216659a2aaa232cfd6ed876fe8177ef143fadfffe37fa87ffc8ffe81fe935ffa393df1c463dab0e0cc2484106ae8d67169c6171c0292d68e462443b593a7d9a2e65203a148d366a3a347dea547dfff0b7af995d7f49def7c474f7feb697de24f3eae179f7f5e6d334b6e7235554d9ca68e35e1b3e2597b5768b6963ea941af10dab63c72a9157df8622315be5de0569b345261d32a5191d34e7dc5369d05ba8665186f5b76c9ad54c016341aa7b6f8ca864564c3a666cbe9696eb39a0b9dc58971cf42b2e314f940df7ef669fdf19f7e4cbffdbbbfa10ffffeefe9ebdff8aaf2f7b7eedebd4bffb956f8a1cadb9f37b905ea4d2edf5f4bbc07e7e7fad34ffd997ef0fc0fe532aede077e674225ada3c617d1bcb9e807c64aaca351a2a8b2476a4cb0fc8da3cd3c2b137333259f556d52cba4a84980aaaf5dfbb1b3df71e2d8e98ae0ba2320992b98e3ed09571ca76a655d5d5db09eacda807f83f6ec125f79ed75bdfaea2bba77ef1e42087a8d09b657411b9199445de5527323a71f99aa35689e28bff69b5f43ee5c83646138e1e413227bf8af4cf48e8ee2890588512a701358f6100e4c168f65bf2217725fc307d636bcea905844aa4ad3dc48f3d073978592abb2e06692afe89fd4156c8de750972c094ab2f926193d6cab924397a26816a2918ab265a71f781a5d6fd40f796bd6638f3dac77bce31d049ca7d989733db9dd28e313dd3bb23c6011995ab1b0941a3472470f9afede2fffa7fa4ffeee2fa9a6aea3e3a627dff5847ef16fffa20ebfcadb18bbbd62c7a871c1296b258035c6aa5c3475ddbbff40dffae637f5ada7bea9e77ff04360174e1d0fe9c9279fd03bdff5b83ef8c1f7e83fff87bfa27ff24ffe8ffaaffeabff833ef4810f70d5b695d72bddd896aa19bdd111fd86ac83eac16668a78aae95fe52abd27c7a4b0fbdef67f4caddfb43cfd7e22bafbcaa975f7c61c01630a0a85a638333e916271fd166997a936de8345150b5066f527292819ba7a6867ead1a20a5416f9aa4d16fa4abebf649130b43791ae3bfe7ba30f20f7878246fed1a1fdc69da689e67cd6c3a7232de9e9ce8e8e4544747e42c2c5be65178164eb9b0913a67cee60f977eec4f3ea67ff59bff52f9b5e0dff9bddfd2efffc187f5d9cf7d46dffeceb7f1d12bbdfdbc392d506f4eb1fe7a5265b23fcfc4faad0fffb69e7aea1bcaf58d6b622e6c848f6b216876af4c0aa9686845504880e51aa61328c2ad0f180d78f1b81af8253729016acb8e6f334fda92e6b9a9b5024a6a5a093e0b4ebe287ffe61cf4e76fcdb0be876383626e51177e44704baab5c317142d8ccb31ee25dc90993eceefd73bdf0e24b7af5b5573941ecb5b2189df3d23ad7586eb33a72b246a8db70b216e887f1bcd9e806f7dea79c742cb1d82c9ada065976c02c52d7d00534959135409256e8ad04da9c229268d08aac0bc941c20e071b4d12785335e8a237fa579997e9e7da71b71d5d1616caf51a3ea8082b750f7e06d7e2071c23b9782c16441d64894c0ddaa29e728d3240c008783b65eb0023c6a08dc0d4a077e3c6a99e7cc73bf5ad67bea30bdecd6ce68d668261f82f9cac5e23d0be8e3dc5e90a3108f47708e61bbdc262fdd437bfa54f7efa937af9a517747a76acdb0fddd1b4d9cad05db8e6eb8c4ff4bae0dd4ac3fe0799ad99717cec91c7f4ae77bd5bef79efbbb9527a581b6c62f1f48c74572be9c6e9b17efa43efd77ffddffc97faefffafff358bd4cfebc977be43c22ef6803e944173ec435b55e98dd4daa46a4d9bdb0f2bff6830fff78c5bfa1be3b4a06297b15570c5d36a92902de351d09b37938e8eb74ade680fdf1eded09ca8cff08acf4ff84fea9915911ea2127d0dda539b29869a049aa6b9d4e6f8a279bdb75778c55f202bdbe28b8f074e61c706d20c8d2c241bf8ccd869cb1cd86601c9c984f226a713faa679a38cff9e4dc90b2fbca0175f7e51cf7eef597df96b5fd51f7ffc63fa8ddff957fa1daebb9e7afa2965a3155fd5dbcf9bc602f5a691e4df4190ec623ef3f9cfeaa5175f54874e264682f4bcd9241449099a44615c5dc6b9a76966624c3467ea980586806b7a7bb0cd64382c30a9958ae0519a987c5be86d594032416ce0e00669e559296452edd855250075682df02463624d3a62f2e4df788809b612a89aa55b2c00776ede5460b388bcf4f2cbea040b84d683fb17e3340209fa910f42a1ef2ae45d053a016ccbcef3b61e7ae8214de06530d3be64a16241585928cc2c373c0b8c327ad1be27c826ed78777015799368071cd6267565012b78b5a934cfb386fe2c78ee6211b9d48e1355aee6164e317baece76e023a2844d6ca4006ea5213208beb665e4481b40e3430be3d054adc9b444beb2955c915b1021450e0d08a9d8394fa45b374ef42801fda9a79ed12bafbdac869c2eb8c3136b294f64ee9caeb6bc43595878f3eee391471ed14fbdff433abb711b3ed6dc8abc3411c8ec0601c9aa83ce55d8be2b74b7f3ac5bbcd03f3e3e52b380905a0177ed4b2bc6db63c7153e9d45a8aaebddef795cbffcbff915dde21dc602d555d28a7cc3eb206a5b5596c8f952c30e0d3f2b76ebdb87dfad1fb2b148bfab00312780bdb293af424e926b5235fad4f4caabaf2b2faca3737c6d9e1a7d26259f3421671606c49460394f1bda48047aa968ea6a55b435b54602dfd04e9f90af519f586857deaf2ce8b9e0c3f1db2e1e4b056e0197bcb549ad95aa418734c37b9e26cd9bcd485be8e4aa2b726e595c36db43fb3c03d3f03570aaacf05ff0d5a79f797a5c75fdfa6fff4b3df5ad6f6a5d5798befd793358a0de0c42fcbbc81027fee297bfa4a79f7e9a9d378ec5049daa6983c37782ba12808c9a55b224bbc94ea993a797e9dc351e8b1fa7e831a51a784edba81d4a0de79e488d28323151e8964d1fb02bd756f1ed045436e770d678e85555f8968e09c247471bb589d6de75ca047afce13bba79e34cafbe7657cffff0795e365e3249ba2e2f2e1130c2951278468270270a845e87d98649777ae3060bc91d82cb96c037711abad28e934c02ca986cc0db461623ebc10e2b6d2bf87b16911def451616822c501d990014806a5523afd6344fb3363372531e0bcf050b1cef9bf64cf03d743af4829b84d0e88edc9642871275d16c19927c94312ae8d7b0ad02a6bf783afab2db06ab93a2778747fa4b566ba5696eba7deb4c0f3ffcb0befb9d1fe8a5975e1af2cd042a4b2c82d6dd5c0dda2c020ba7b59b7ae4a1c774cec27cebe66d9d9dde54ab36e082b6000010004944415459afbcf2aaecd2346dd5a68d844c7b160158a8e54bd0c2e659500317796cd37afdc15ed1a56117dbe223be4952c30737db59866ec4df4307708d4ebe6ccb2e49c828a953173cb7bcfbb85827dde37d40a7af686fc0c50659048f8e4f35331e6d9e25e45d817970ff9205a4539db05dd74c5f6066fc6b432a6808b885c57e42ae89fe46de22b734ec65fa453234cb4d934badac66cac005b6aa6965ac4367c14e2b3a893a460ea66c4b7c928a72412bf41af813293235c6283284ff841e1bfc2a724ef3a4f45523afa606df460e1936533bbdf0c20ff5a9cf7e524f7ffb69ddbdfb3a2cbbde7e7eb216a89f2cfbc1fddffa6bcf64f8d8c7ff589ff8d42739562f72e1b9385cc50171cc541b4e689bf70f522bcbe927651204deb6f0443e4c3ed79884e23904c214a4851f80c41d13348a40352b93a13121c624864682dcc26e77cf84da132d2e79577099204e3d7d093cd54a850cd9711db10b3b39d9caec544b9d1df1b11ee63ae5fcc1a57ef083e714ddf65797bc58bc6072f73147f71c47326516f8e4cf65c84d3bda2626e0f1d999ce38cd6ce779c87905ff05fb448fe08cc0051f91baf5170f8bec9a4000cd65ec2cf78a6d12ac56faaa22b3544cfa22e064a2b72a5df1ae258bd43ea79de888ce2b51a30b6dc2100ea63e022f6d65d372e87051e653d554cd2ae89904b8ec9206acc93cc6a52387b08278027b484d8d53c719d7598f3cfa2877e5df67317845d3661ee353d8462ce897bccf78f9b557d4d78bf1eee8433ffd733a3d3d9390e19bcf7c534f3dfdac580395183863c76c0a4e8f37da6e8f94e0d65a93e11bc93bb633f2193bb895444721f7105792298bc7b4a77f2220deba79aa634e2db9465d632318652c42ab006c55aa32a96463bbf94837dffd21bdf6fa5d45efba86e15e56dbed8685f04c5934b7c897fa66da48c014fededd95bf1b36cfd69d3b67ba79768353ea46a121640acf1d7eb1611313d9dad0a3c9954e34e4a31f7b3aed06d1d8d2d5948532fa4ad68a1e3b0cb7e2372b0aad92c27fd58148ec5f2e012a911505db4ad9a15bb4503763185b556baa362972552b3909b86a4d056ca82ef07cf5f5d7f4b18fffe1f84dae9c46e2e37afbf98959a07e629cff1d19ef08ce5ff9ea57b8077f9a89b6e0bd49962d552606f4e3788e8616ed7c0de78eabafa39eb8941d546702584c8ad1d0c7620341ed991c99148d09145a8d20da1a4e0efd2d77b8d3bcd5083269676658d20a8d7d0231936b479efbe215fa69b3ad72d354b31a47f5d03839bda179334be04d4ca6471f7e48479b8d9e7ffe87ca15514e21e7dcf32f4c9ec6643a047fb3d0ad04f19d2e2e2f7491fbfa6a3ae11473ebf6433a2370884561c14699e0997c0940b6d1526ae44226618f85a09697ea7be45dc04960d8d3d6817412b08511cbd2d4664df32165d1896c979c600e8b48677ddd439610d2056910c8040d8de06fc586836ddaa01b0853b64b9694fe8251a14b729ac627381dc5dd3bf43b36ee025df9aa2a9db210bfe3c92759789fd7cb2fbfa469332bb68cce00e9eeeb17fac29fffb9bef7836feade8397f4d2ab3fd067bff829fd8b5ffb2d3dffe26b836fc7beb655ad69b399b0ef1e7d0f72c94d0c8f3097f2459c265b5591b5951ae3d69a657a93426bc586b17fa7add82c5c213f873ce8ae5ae1a540031cff1a45e88845e0ecdd3fa3f39dd9389cc3cee9027e855769cb55dc0d360a0f3ffa981ee2e47572728aae1bd956361cf9f7453bde251cb3406ce0d9f0d37229fe57d546de651d1d9d2af51f6d82f047f1e437ddac1a7d55d1bd24eab66553a6ada039e1ef0c053659357c06e3ace8bb0ebd4041f29ee494c53030b7b095424716a6e09bf6eb7ac0a0ae4a1d3ee19d727284968036bc57f8845f7e69e50173e2339fffb4bef8a52f8cdfdaca1cd6dbcf7f700b64dcfe8333fd77651867f9f6b3dfd633cf3ea393d323c8c55da54ec9385b8d52c7f79268c4636dabd4d4aa9449757050daaeeb1d9ce0e2bf3a4cea435fda062c1dada681df5c9a3713817ed686093533a1dadc647b048804e51d8b4f1c7ecfee7ccf429209970565e154b02301ac4c90637692a727273a3d3dd5dc2cf5dda07b441078f9b5d774ce555102c3f90302caba5726e9e56ed5e5e5aafbe7e71277d279919c1d6e438e1302cced3b0fe9981795794fb18c4561018fc0c5045458a8ab5a898cf668be6a3f1610e0c8890c8a8df5c6838d5c5670a2eb8600dd5ad39e7bf02bde8564426741896c2b8aae217c9d3a81a5533e24a9612391ec432061686449058f24064774918ae22199fe05dd63d7552bb2213a7c2c6890cba5e393233dc4fb8de77ef822ef030e8b826df101dbbc80bdd297befc943ef7852f1274beac3ff9a34feb0b5ffa169b0460d06d6ed380652dd0092fa10dd6ca1886a7e0d9803132a67d2478a2969c765bd59a8afe2e43b36b61b5d863cb85b19e19dfbd677ca38fbe439ced52f048b1a55d9aceb886bcf3c4d83cc4fea127fa055ddb2c6c1bdd383d19278bfc3af30de8c64fca4510dde9f9175e02dcf8e6561bae491f7bfc711d1f9f2066a7bdc686638b5f6c371b8576d284c2ad15fd56c60e3622e28fba65ca491af0ad6ae442670118d32fd8a8e3e32b7aae8c35441437135cc503183806c5a058690f5e1fa551131dea01d48f3dd40393beb43697427bb9b6e98211af287ffd5bdfd01fffe91feb3bc483d83bb06fa7ff70166054fec331fbf7c5e9fe83fbfa02ef3d729ccd5d681c177f93e29f14e278b6657b386e26481565264af2f86235260c4e98899a24bc3a414eb294449f786ccb5523d575de0816ad9a124c0f8989656962774ea65cdbace0276567b86792ed995c050e84a01a4eab226782d4d436dab263bcc1c9e1ece6990ad91057a72c2e0fb8c77fedd5d7b463a77f71ff9eaed8615eb120e5d7712faf163de03d49e47f8dc56681c7c42ef6f4f4262f7c79b1ce82b223c02fc007260c478e04ca2c2f0d71b2a3eee02e5c8765915bc917e4ef421b5bb607609137026dd296052ef4f691876b91fd7500096ef4ee1af35d204b0a7e526985061f05d7a60dbe22afc286658a56ca8d7239028612e0c8b32273271fb2cb12787672a9158b0881ff91471ed50f9f7b41af628fc638d9062c632d16dd3d81f6924579a7cf7fe1ab94af08ea8b226fcb46a0269d6cda188b06efabbce3897e5cd32df05ef19161174ba15d045f24539eaa5991d736fc68057e47808b5d8e3965b6a313ed69eb3a8c7b706c6091db65e59472f2e83b391dbd3a7e49c1a60df945bff258aac8c829e498cdc6d9ad5b6c9e4e757c7a3c64a19705e435203ba6658340e968bb51032726dee534babf62f139d33c6f34e11b550df2491e386be403d8dde850a30d3223b7ad6ad3e055945df493d3a9656c52564ea0cb289b315ab19578a034be8754b4c5d63949cab151c6960210f984ee61f19252b6adc08db20e4f168905bb76645d917587effdf0a5e7f5e9cf6543f079ddbf7f9f71be60c3704f3b740e7ce03abcdf48074a6f7ffffbb040fdfb20f21f9a464e1ff98fa05602f3f98307b0b76cc7230906ab169c65c5b3579c8cb920970ffd387d9156fa6950f20edc3871c4e97148207fd40e41c0d2020be064c96ef9c2c14b8d09be61824ee4732624fdadf105fd4cc94cac051973c51367be24204566080c7c9b49b88a49b72872cd6dab04e68778177276c6ce911dfe1101c3f07e9dbbdf0b8259fe0162de6f8c492129a7990d3bcaf3f30b4e2417404a1b7699a76767dc833fa4e0ee77e78347c71eb669333aa21dbc4b8d373c5821fac3af237b12bd50a7fd7a8e8326633bdbaab9a961d319dd57769ff95f0caf98c8d137faf54e20892d2dd5e026c885d04a9d226d50561f8363d9a60c8c8c1dacf1a42dda0486a04b551d5b1ee877a51e48b75211d8aa4a13b63f45f7871f7d9805e42e0bc40335823cd407dfd61a6d977ae1c5e7f5cb7ff717747ae378d821120560e2ddc1ed5b3708cc47bc4f3a1b7d0f2eceb9f67aa0fb9c04f37ee18aa094b1dca3af785a354dd32c59e48de09c34215353c6a521db96c5e398d3052651ef5236181a4fa72c5269426e6dcf74f7ee03ad0016f6e583685d2d3c48479bad36db595b16cab35b673abd75739c48e679d28a9deebe7eae175f7a655c6b26c8bef8e273ba38bf0bbe75850f1c712a3961f1d9b0f8b76952436ebb94c75d5ad7aed512a434ec5bf451177d69a856d002af9ad23ce40b32f2c66786af535ef0b3d43bb956b1264000dae2096d6a30a1920f302b38a137128c1d9920de5246864e1e1c1b080abbe56a2c0ef185858d8b6092abdcfca581dff89d5fd73ffd67bfaa5ffd67ff6ffdf35ffba7fa67bff6abfa57bff96bfac33ffe03fdde1ffc1ef91fea332c36f993f4d988662cc33fa2bc9dfefa16c043fefa483f498c0cf857bef6d51110139077bcccb5ad22096714796796c65fe38052544c0851ba466ae5915b3c38a44d296d5405eec4e4726b6a7164ea2b29349599048f800ba7ed5a6526d3046ce1e8e10725258554f0e29cd985ee99242bb8c9af083e69ebb435267fb5a6824e6ba5a9cddacc5b9ddd38e365e91d6d368d492b95a45739892ce8bbe734c26ca7a56b81c66bafdfd37633ebfeddbb04bb0bed51fe687bcc0bd79b3abb794709f27b025f6033e122479591dd68b11ce41df5d28aae038689b9cfc985a01db5ab4ab648a572a9c12fb253d49e1de12501f60ab916e0f7e0ade4d17d819e00b24d20d1a09f1d285599a5ab08b029af61621d9e44334a360d24831fe4d05cb2dbbd5ea082d3d1d5d8d50656c8d8cc15d4b1cece6eeafb3f78412fbff28adc689fe006ee8b2fbfaadffcad3fd437bef58c3ef081776bbb6d2a741fe3de563dfae86dddb87143c75c0f6de659e552ec7d2f7f3980f4e0e281ced9085c308657d834e3b88faec8a0920a7d36f34679717e7c7cac235ea06fb71b4df33c74efe8195f0a4fdbb2ad2a69e2d4d86ba2373e65c67c121599ce4a6a4d0dbf4cdab061c8bf2d3a6393b0d94ee8b0516bc5c2b1285778afdfbb8f1f9cebdefd0704da3df41b3eb047a663c5b767649c0afad2e08f14923c3eb143e44bb56caaabf2304d24ea6d6a723568965a4da3bcc85ac798a31dfeb8628b9e9cb4504e128f6d85a65dea61475d104e1b990e6d253b70e4d19b4455d454f0b5ac3d57b85757975ae0299ece8979cfd8ae78737cf0627789de3be6cbab5c67be3afe61f197795ffa35e2c657bff6657dea339fd26ffdee6fe9d77ee3d7f4913ffc883efb85cfea996f3fa3ef7eefbb9ce29e6793710ed5b73f7f150bd45f05e8cd04f3d4334fe9a5975e561c26570cf8138e55b2712d8b38d3e5f1534a004b59a3af1447b54b120e8f7367a274493d418836c7414934a85227ad04c0ce241039a004ec3dc54c94e0599de011ba1b2656e1ec9b6956727b706652587b7057685c119417f81ee8f4a1c342f0b54b42900e9c48ad4a1374f292347f63e9e4e45834d1d6c6af769e734cbf9feb2c025816d4734e26afde7b307e132d8b481656f16c8f12486f11c44eb532c116f80f46f4855767c2c136ac61bb6a85f732de35ec15d8d865f4237bf022a76d3537cdc8374f9312183b3aed09a879a17e793db183bf609be07502fe0aed04882a4bd0a03af88e7e498df65619a392c94d5b60c8465d2047e6151d3aa79e159a1d8011decc38a8eb0dbcb2159bddbe7d472fbcf02a2fca5fd0bc49706efafc17bfa6e75eb8a7080ec60000001000494441544f7ffe9bfafce7bfa273de2dade86716b3db67477ac73bdfa14778499d7fac9805e09805e094f7081332edb0f37d4e087709d07709ceaf330ef748b177c631578bf9c587051b0a7924a35753b53ad893365c8d6f7abb7ef474978e6e3dca29e13006ad35a1c218935625839f85a3868f355523d1bed96c3555d3310b4a73c9fcfce0fb2fe87bdf7f4eafbe7e9f007a5f57572bc174cfd8eeb0c9a95a9b04d810a0a377c7862ad346a263889536ca11b414ba9218e358b9e03b41237c5b952637649864cab6159c15d81545d7d02139b4e8bbde172840832fa51fe1fd58d9e8123e024796b2881bfaa29e3cb8d9a45ce17389032bfc60838fc77eab267c739a371a76032f1a845ee4eeabc309db76dd631c9ff9f6b759503e3d16920f7ff4f7f4bb1ffe1d1697dfd43779b712ba00bffdf93758a0fe0d7d6fbaae1c39f317767bc751d87d2c04122b2ae014787e9c685c0f0c4f5d2519bf5f0e6192fe3877c3e16dab5a539caae3e876e008a704d915a0fce69468db1300072ff81c685bad6a2c52613bb552633215b00d7a474c64db4c286010098f56c7b957782c2443644fd0df1304570256811bbc15be63520153d0174fab867c938eb647ba75e70ea79187e0bb40dbbae2bdc7cb2fbfa2ecfaf7dc6b2f031f1da0bfc2ef01c16df0439637f05b2be5d782c33bf05d522678f8b88cd6465c6c960f3b3a480171fd21784b51481a1945bb34a1ef86c95a554ae08c6e3bc6658fddd67dd78a3e49984f30501f44419664c38f3a50ca63e84956d1de48e6eaa9caa238f04cb9994d41c61e1d854ce93ba4039cd581b7f2b42a9df2c2f991f162fd253dfbedefe907cf3dafaf7cf569ba1b1b01e9829dec1ee1629749bbf12fc8dffdeef7e8c9279f6457cff5167ab48c6b1662ae7ef20f410b86f9a5865c35bdfeea5de5ef996511c9154a7eeb6acf58ec19df5c2d7616b9155917ea8d53a59039f808809c7ce3a75d5d6b759ddc7a8445668ffea50e8f55e8820ed59a5ab5b160b736090075ec8ae9a06136075b3df2c8436a55c23cbc13dbe9f5bbf7757474aa6973ac693ed2e5e5039d9ddc183a65203aac3bf493463d0d4e898e7c4c257949ae433963b7c2573cd54a854c038d7e93ca35e451c03b5f7c849081593366f05b9889b6d5dc48a55616554956457e806951294f17dd245ad20760c7964986feba2ecae6298bfa8a7d8363f3eda6d09a39f165c188acf366d63157c11bfcd58e97746cbd92f652745aa5d0b9e0147d9753e6773889fcabdffc97faf257bf8c0a081571de4e7fa905ea2f6d7d1336c681bffdec77749f97ca1d875c98a871cc886a5b7689ec7ac05327d1691b679554a23f5fc94914bb4c418ac3c5d1ba8b960e7ce1ee5d7ce523fc55a974824d8fc34133902b134238b7f0f4ae4ed6b5c559e779d2a635556bb20bd4ae956094c0c29cd24290ed04e9e439de576b1a32402bf9c0b1d4a02b195a9372a5f54e02db8d9323cd73a9c1efde6bafeb323b6826d09e17bd6fecca1eb02bbecb890471d5a689c973acb35b77042925c82d74ec39f9e46a2b76c468f037b025548372a709fdd12f32c6ce499d7a3578b736e0c453d9154f33f8133ab2d36557b8204ff44ac059d09be1d28ae21693971c830c1e069f021f78f1b1474b5ab1a5a159422215ed19f315429d20b2b0c35f499d72fac4931c304a7c20c34785ac5b4e108f3cf2188bc7cbfac217fe1c099a0a9b6c09ac9b79c375e146138bd5fbdef784deff810f707575a4d69ab6bc27c8b887afa1b321009d72ad75caf5d6117d2569cf38de2558e70af195d7eeea75dec75d5cee46fbbac73bb0f39019dd6e3df4a8581ec0b26c8b2f3e96cb6a096cf331a7852bc95253f44ed16ad5644b46069177848a5d63c3634ea68fa2db3bdef1846e709d65f0ec49df79f639bdfcea4b6a8ccd9e79127bdd79f831ea1b95f19c8e403a3c2919bacd25db32a351e422b94a4aa22c6cadf83e682eeb200fd0b60a98f8814d3be5bc53ecf04054a889c7881b4e64d414fc0157b029f04d9365daf948cea7f8a2cf952afda5d69a28486cbe70d0d11e1fd8a16336300b0b76ef7b75e45cc31962292f9c1caf322ef8e68a0fc53f73e59639d803e73e6819fa4953db28f01fffb33f1aefcb60f8f6e77fc102f5bfd0fea66bcedde6f7beff5d8d81c741c490773c940f614d3c9671360a7c4cc259bd2a25f31d47c9844a397361f84da03a3d3474caa691aa1c3af0b08dbf5e9b883290e218c03c029aba0066f3a8e2a7998929a9b5524e2613ce3895159a6de4064fca84063b9cb4c237cedcc9f7045d419375450ebce15b4d8d605775c88fd9093fc28efaa13b77087cb3a656ec9cae341612764fe2c99f14c92479f5f557d98d5e601b6b8246eecb4fd885eef29e82c503e632f0912129a7a3c8d12319936c25f0afb101508103548e4c546ccbb61a72493ec842b05ad163c724cd58ede191c527ffc6a4675243b3d3bf425319348ce0d0d3811626144d90b3940aed9857058faa926d5da329b4871f00109a20f0b1121c253344c04ae49d36737573ac5b0f1dde6ddc38dd02b14acd6ac83c314e776e9ee8431ffaa04e6edc10082afaa6562cdcb33a76c855691775168e53de6bdc04eec6e9317a4fc2c0ecf02f79f97d4fafbd764ff7ee9febc1e5058bc1a52e095c0b764830cd2972ad091d2cdb523e462fc6fae4a177e8e26acf902c326de95cb1958069c818fcd866c1762b7ed2c5468517e2b758341e7df411ddba7da6c71e7d58554d6dda707575a9cf7fe1cb5ccf3dd025ef6c6edd3ce3247b2c1b827cc4131a90196d9669e1137b5e97114bb655249b7ec62032ac194789be1ae36f39151d404c7e9d24d5f580415683176d567ec4b71802cb2e19da2ad398443d6db64c2ea7797cc9d493a4bf80894cf181057bfdc82f1894d8ac025f8779c9406a9a676db1db96057bde6c9479117ab635a5ce86a2e10f13369fa78d5e7af1657dfe8b9f57e6a8de7efe520bd45fdafa266c7cf5b557f5dc73cf71f5d0b56722c5618a816fd570a7c265e2a34cf32a89f6387a76d85d96486c3270780b700d481cce80c6d14cbfa029f23863700d9d41bf4d1a8e28b0ba003325a93391040153e533dae274e1532a9cb349d083c508648103897904349f253b546a995cd941f514a8d3a5c800077576b00e0d849e6b52de391c1f1de9f6eddb7afcf1470960456a9aa6d26bafbe4af0baaf04ecd7397dec09e4f7b9e30d6d3191b74c90fca7543393e46a77a1951dfc9ee0269e04939c1862d3c1175baca405feb0d7106db53a6d18406933ba17369a589c126cabac3c996c3b7887f642f00d6eecb912f8825e1824fca4c027693c29c161943ba3b9825bd03cb475d9d6e107092194fe15e2499db17030f345ea3afc2cf41bac998070f3c629573d77f4fef7bc4337b64dcbd5b9508120b6e89d4f3cc2b5d53ba0003774361d3531eed8b531fec2572e5914426f3e9a75cc29f0ecf4866ede38e574b751fc63e13472ce09e41ed787f75944ee5f5de86ad969b7dbb3385c6925309d3dfa2e346b32f46d4bd802b3eae6e3ef56febd4f4782d64a7b165cdbaa6a0a6f0d3dba3a7274ca1b02e1e9e9a96e9e9d8e93c7092791871fb9a323725781627dfb3bcfebb91ffe009c45b76f3dc466037d94071da1715dc2372921874d7bec461fe615d96101c818b8c949d04e3b602049e1655b0338c253b62d3bb4c43921322f00f6d146410679450f1b38e815f469546c58e131da8122178f0ddc752af25625dbc826d133524a9065be2436ac88033f7aaad59833d306ddf101980c3b6cb94e9cb1e1bc9d7594f748948b715e38c9ac9c62aaaccde64882cf57befe653df7fc737afbf9cb2d507f79f39baff56bdff8aac63f985b775afb8e5dc11e218df3f2f1ca587b4c067c536b1c815950c211f1acc211ec52a9d17770b03e6682d48a091718f222414d1313d750177878a30a5c91acfcd0d1354a459e454a3eb4874f9cd66564ec38e146ad3519ba157e04ba4e0a4eb7d949732b8c9ccd484a7da52c1f6823520ada1378133816f204b569dae868bb25789ce99def7887f2af8e6782dd630f3fa23da7908b07f794fbf8cbcbbd2e08e4f9df12570939a611606e3ff4102cac1dbb638c811d9181a0b972f4b14a4696610756c25685fa7d24215fb552b5a6a2bd1ad0a4d64a34407f96250d1d621716a885b432200bef1a56f4ee84cf9841433980c86db028763a603968d8564177bccf2afa5387676b4d45dea1b522b3622f498d7edb90656ca9dbc6173ab43ab555457d9e27ddba734b8f3c76471ffca9f7e85d4f3cacadf77ae4f6897ee9977e9e45e104784820471fb417749ad4b0ed76bb81dec2b5c60365a12882cd862bad635eaedf3c6321393d5102d18abef9fb55af738f9ebf4d757179c529e44ab936ecd07de2bd1f948f6f6385865cc8885c4500bbf1d03b3929705aec926d02217a50ae56723555012b894c1b825dc63f6946ae2dc1712c6867c73a3b3b05b6068d3d2bd3d3df7e56d366d69693ab8c6e49c8212a2b392cc6d8327d6831f40fc9c099161a349e0c4c818f5d4c43ec1d59065cbe681bf442936470470a03ca3de3648048f14507996a7c2363d85c1a3ad2af6b7ab01beced926307fa6cbec0cbf71e591636194a8536d1c767e08c2a5fb695f19be78d66ece68e6df19d5e52352bef48e6ed44b961fff361f7c832b3d8a4bdb5994dd903fdfec73e3a4e947afbf99f590053fecfdade740d79799e7ffbb164f0711a7c144729920841c2779a5a9cacac83b3910b67492f4e1c47edec2c68927de82b838f4339b980058ed92457a3660507cae48b6c0bd2ca84ce6408aec5c3d74a384870e8f04a9f6d3568ccd3ace6d2a64dda52b62df1c957f4c80e7dcfae756161c82231021300098cf600c4c10f7a0d7eb4e5da263d86eecc42925de8634f3cae2382d9253be45caf4c358bad9f2e78d17e9f4096ddf015f7bf2b2fb56726c429f7f837ce6e6acfcbf79c1456f847d78e6d63cdcec4144c6c2b72a296aa4c131a62f89494874810b99232e11af69f98a4398dacecbcf79c6ef6ecbe57c66b8576f48a9ec957024a8796207ec81dd34bf0b43dec173eb6e15dd8be94455d791833c88d71e9d036743a6d72811e58070aca079a912332b4d674b43dd6ad5bb7f4e8e38fe8177efe43faa55ff819fd3cf9c30f3fa4ed660bdf12c3a90305f059fc42cc36817b2323774e19572ccc366ddbad6e9cb280dc386341bfa1d3a313cdd8e18a85fceebdbb049f7316f19df6d0893fdc7cf861dd7ce2bddafb7095d5a171cccbf3dd62c5460ddcce42beb0018acd67025f82dc025f21d5c4f86db7472c08476c4e664e9ef8d8d4b49d67ddbc75a61bc7c7327e6ca1068b76686d59645c25c14b3cb17778c5e06b674cd376dd576ed84d0afee14b2afa0afc56d8b691a8c302206b3c64a6cfa64087af11c3272930c9576cd0d1239baec0d85681a7b2f2d8d0a64cb3d2ee5632fdd61b3f0d30d3479df641135f8d3fc11628d1070e3a5802d7b2ad6e8df6aa49d51a76061a079a28376c279e095e9bcd461b6c75c4184ed35633e9f8087b22f30f9efbbefefc2b7faee800f8bf8fcfdf181af51f8326df79f6593d7870391c423e88ece496f8c8cef7aa7c33ffc8298d99c40e5a3ca9d644818985ff380e48db61fad0261a49a9f74410206d9c0f47a3a80a2fa816782609117abee8ac6a029412f0e04296b2340137e1a4f6a16562212970e2f84b1c9fc9bb30a97c479b9700001000494441545678a46d65815b4899649dbe034fa931210a5a4606938fb2ace413bc4f70f8c71f7d94eb9987b427f034789eb123beda5fea3e412ce91e572b3b78daa52d47f35b376febe8f8544b160f02fd2108afd4f78a3c99942b7d98249f914055824e4f0d73d9165248e4aba4f09d58281b412e3a24700ffa4cc0d457f24137faa277da28826ec57236dfe84de59a8f546e328c5b95443e4d93aa4c9064d9067961c71f5bfd4532b83a3cd0a2063c3e80bc1ddb52d296a07f8a7d6eddbea577bcf3493df1f8e368d435d90afd1058916fc15ec93be5d0897e1b1619a4e4247caeabdd4e9d68386d266d79517fe3c6896e43f326ef1b32d6972ce0f758c0f30f3c179c32e3356f367ae8c977c92739852026846fdc7e5897979798b6a3af95df624320d9a5f01cf606aee13ff3bc1df24f23f0210936808a1a576dc75cabddbe7346ff2ca1cbcae2ba5c316780891e987ff0887e90d378b091ae2bb6655bad6ae4960e79b5ebdcaa462b30e2b1e14f8e6907ddc8997158e01bf969e4432bfcd3deb1230deae041457ee3277517940e2db193870c0282443fdf7cb00fedad26ca7c40e9ccb7244b3ae0b5217f77816b5af341ce0849b55549d0cbbcdb33573ab2197f6acc990d63239e1dd7bb49ab76da30de13febce724f9f93fffbc5e79f51520defefcb805b0e88f57df7ce538ff57bffe15e106ea9905948c43f061872c1c6662f2174185a981738c461c8348a87d378d0b18381fdf2e29d722642a3775fa43a78227690dfd359ca8a443e09356828fd495df2e89a38a36902535168a466ea5bd83e3f44972354d4cec797e239fd408025556b5a63871b709fa8b96fd2aa968eb634a24886422eef6042988aeea72b3ca45493a7c4971eecdbc514e158fb1883cfed8a35c4d5d49d0bf79760bb01a7fa1367f8cf18260424c5482d009a7905bb76ea342d76eb9d49ec9bdd0d9c973cae92c1eb0859194856445be5147f63e0a92abf4c633b929fa8f64a99a25c660cfe2b467a2e6c57e1fb65db5d29e934d34ad863ed0eb8087969dc2c1fea304bf0c6c9aed92e113bec68ecb1e0a9ce0561691154d61a860da260f355ad0a78f3eec0b9315c769d09c08c0f366d636ef33b882ba42df95ebacd64ad51a63b2d73a026127878e2583d7aae998057843b913687265b807ae31c65b4e063758986edfbeaddb9cf0b604fbddd595eebe7ef8477d17d8a1aab4e5c4b2b9f30e5dacf0c1169bb3dbba6001b1243b3eb0176ea542c78c6f47fe72699a67d1a406ef245b3ce8457f8a33723ffcd06de5afff6229357ac5756fc6a100eeb17f479791a01abb93824b2bd029c582291e72a0a874d9865e4902a6f38d1e0d7eadd20676da54487228678c31b75c073a828f6888b429c787d6e1e920862664d30e65704af621553167e0e1f4f365e889b24b4a9f0d7d68af248d077a96e81ee43af6edf0412ad03a38e6843853b5c62f7a30266028302bbe0239ae1c77bae48419026d6adab041bb62b3f0d28b2fe85b4f3f853fac7afbf90b0bc4d67f517b1396eeddbfa7175e7c5119dc0cf68a53ac380cb1e0da51561ca02b934c9a240b4769328e97b6ecaec7dc01cf1213702260f680e10c2c2e96ca0d7f39b4d9d612c732c083721f811d9612847a0aa44c20588879a11e5092a14a975a418fc0d2a91ba7cf49c15582b40e30abec5182afb447b604ead07f8358e80462c1b103b4e43a08b83d413374576439d0b48ac832b3903cc21549fe2aed9eddefc2e2737676a689c073ce7b91bbf75ed7252f763bf4a6b651eeef6f70f5b2b070ac04fac15b9690b723c48a0d1668ac2c9e2b7c7bf802ab3c966c930478c9049304fa6a29974a4dcda5c89d6bb28509b9600f44c6b6ab0e8f151d02135dc333043b15831b9b052ee34c1345b4a6d25a53838f080cfb047ef4e9d046645a04b915baab6c509256c9fc649c8c0045476b6dc8b7e58aa25521ba75fee002a895b1031efe2b4c5768e72fd446264c2050a5b2666c5dd5b462b7cbf373ed189b411fba5b76b2b7efdcd6235c8bdd38b9a1d8f1eeeb77c7097a87fd0adac73759dc4f1e536fc79a3637945f7888fe7b02554797a92665816bd0430cb556a449d3b451e314e66acae31f7d89abd2a6471fb9adf7bee75d7af8f6a91ebd73aaf7bfff7d3a6523d1dc86ec1df8f071720847af151d9d8ed1b6cafc604595252c93a26c63de035035aba8dbe4d8ae55532b03072e34298d31889f754e5ee1211ac39789c467d5021c1f682a5de06ad02c685a1ae5869d3a23ba5217505481eb2ad1cf77c72f078d8885f1576cdb195fbaf97468d341bbaedb8c9c49ad951a0b836d16911d63b7d3ca06c9f0aa6ada307ed1699a66ca934e38d955cd7ac0b5e433cf3ead0b72bdfdfcc802198f1f55de8c851758f9572615ee8063f29dcf700a0ab8095ea52811675a99e5c61156da5da5ec89563a92a29b695b03630dbf9a98a08052ee04142bf54ebf702e8b9f782dc9b660ae0473db14e3a0698a0c1a13a8a0dde626b7922b30e95f4770b28a1f6902862e356a8875a043a13389af0842bbeb8068e07ada99001d5d530eef3d016b61c7dd23a3423ffc7b24955d4a60cbdfd17ac73b1e87efca04b9d48d1b377499bf93c57556fe04c79e8956b6b6eca46fb2fbddcc5b26d115b2ac7ae3e9a3604184f6039f15794413b54316c341a72a8d5dd13081a831090b1b5463318750826c7458e0bbcf2284ae7cb40ebdc463d874681e6c669b363ee446a7aa92e15136766e2ae8b7466e46173b84ee8add96f8c8082a5d526858360939830b34253c0321ab35414c13c1782635d203165da1455eb64ead64498838c61c4421a4842c554db6d5a03111649aa4ab8b075c435de80a190cccf668a35c653df2c81d651107440feedd27f85cb280ee75747ca2e33b4f683d7a486e5b65871ba9877d52908382edbba2db41a606cf029e54965df4af432cdb6a9c76cf384d7de883efd53ff8fbbfacffdd3ffa87fab95ff8254ea737d5e9cff0f5c3177a610794eb19086c38dad50555953472816374b1ad379e1ae58c5317e06ad5d4b04352552938a1155fc9f8aed046c87c14840ea9f48f86c802914e1a78f4219552be664359e3a9b2c60f399c91bf2bf4d3695b34878a3a633bc8d206824c47b552922d1d8c4546a5e19f695a98532b48ae26a342ec5855639cc26b9a671d9d9c28a7c9ef7effbbfae10b6fff46967eecc1643f567b9315e36c2f3cff3cbe90c9d287df15031d87e8b65c938a7ac4ce60278f4236df342c049485c6f831d03265f19d6b2c3621e003275c3b8e672606c9b6269cab0789be1627c4a14c12b81d9ae2590916b00039dfe0c609c357a94bd37c90cde0c7b10d6eb17bdbe09059484c39907b1684059cecca1602e1ca02d8a10db88aa0d0da4177db04b3054766d7c4c9200133fa65e7a50023af5d43f6871f7a48ef7cd73b050b9d9fdfd7e68445846b927bf75fd7152fcf833763806326c6cd5b0431597b68ee78f9bd10542cc9c84d119bafe24b9dc6514f551afda64de34b6a550a8e5d4386d838368c9cdd68c589668f7e0bbbd2d82eed444774c20a96c6e45697c12f6825693cb491a79d4c88a509d963df4e108cdc7b68f708876c088b1c1d4a1d7053666c28090532aee5a6d08e5d5b6b6a555c6b4c23202d2c725d560b7d17a4a0c3b876526c206c4c3734e9aba622b56952c6747779aeab07e7da119076c8121aa727c77a9453e143bc1b69f03abc80df29f29f724ad9de7952bdb61aa7b4f0867ea1e09697e1a1bdc2778f2fcc5c8f3564aaaa2193102232d17da8bbab356bb39959b04ef5e43b1fd7634f3eaee3935340d13f80a4034e2738aee8bbaac36f41d685dcb6c243ad54ad49d4bb24238f6d15ed0e7f4bba4eb6012f89f6f4d9d6e803b1c32f74e1029faee8c210882e523f2460c4d3f179b201872aa30fc5542ef90dfed73c6478c8fc8031ca6023bf2a18246886b7d34d12301dc6fdc0382d3499b9519a5a8397f0c13df91a6464901aed59ccaf2e2f981757637c57fcf6febd7bfad297bf044c88e92dfafceb6a63f67fbde1cd54db71a47ffea517c5886bc10988177f3178d4ad31e612cec5ab8bd40eaec8a448d06a55a05aa6a731d113202020bb89f9aad1ee12dead4e43eaa6b5e34ef9083a8269da6c2b0148786a5dd3158e3b60a19fcf4a808cd317b0ad4a5335cdd386348d2b89699ac196e2e04d226676b8d56142432b93f9f2e20ad956fa8043a6037dab5a03d652793875ee705776f44022621fc94014fdad4ab9877fd73bdea942fe0b5ea22fbdb846b9d2bdf307babada012f02ce46c7a7479a8f8eb447f6c89f14393a06e858b3d005b212f6a6aaf1f4f12d1b8ea416d9aaa991aa15ed80035fa9d7847e50620687d61e9d96ddcadab10e3b18a27481a06b3c967ca2885dd0833e34dc0e3481e0634c60a982d2a1cde4679c3af61b2dc8664949a9a714fa5607851479ab546e6a95bcc6f880367e634a60ced32cb746112c3a166c23f4a12b1fb97cc86d15fa15748e09f26233707eff81b25b5d58d4023511d44f4f4f957fccb7a1bcb220600dc517ceee3c2241a923ff028fd83d3828a57229742736111b361dad4daad6808e4c5df9096cc42a6c546dd6f8df2d91ad55699a8bba80c70ae8b0c22336ea18a3e85fc5e3ca9752772b60a168d326d97540140f6d9d6468472ecb34f2213bb4a56019ba0d594cd7f8c0247c577c1081c71c0bffd4933aed1d5a88843f5232ba0d40d10a7fbe71927c2b4f7045ade0a12aba56d241cca2bd99ef6be65db4a70c8fa25c65d996f8542b150b7223264c051d7c32bfc890778e91206099eb19938edd26e0ca8d71dd2bffef48ae83f5f6332c10db8ec29bf12bef3feef20e6461d0f1455cab135c09302a65b264120907c047f0962e7c44ad4db27d08c0f268b70bf52c031b9c22375e1b2793a50a22297da99bafc0ca1e8edd094e7dc0afc8404a9d52e1c8e5d0564071662810fcf638dd1ea7a4065497a18d200a9fee1af5b9b581b44a845029bbd63dc1854e78526727bb105442c3960ca5d6c0a5bc20cb02ec9e20b512b416f2057ea19489b9021da7bf75eba6defbdef74a9c9a76bc44cf5549fea15bfee85f7684554d479b639dddbc298098201780ae12f2a30c543c64a353a38e6ea2553cd991a95bc2f87c2bcdb69349859ce8ec66357858027d252eeed4232ff2c49ed139ba74f8f5c0c0fa9077ad69434fbd4133794990152ce5f1035df45ed868ecb155743ab40318781264f9ace8d111975e08144119b110d36a73539b1aa790590b0bf202df022fc1a59505072d8cf71e1baff0ca022b84347d49951cf8edd15627c747c8b78e2bad0baec42ec1c938b579d231a7919bb76ee916e9062fd9efdcbacd7b924754ad043a76413ef435f4e6ed4669eff0dd70c5d8daa4c811fe4029ed41420cb56a87049daa02cf2adaca4d91deb6721ae44bb1e98af1b2bbeee4a1a71f7fe05dd0b0fde3ad94bb7ebcc5c0d9966dfa34f246b955a57248cad32336e6ea5ad16545bf8e7d3b394ad02eadd453b6f88146b092faf0b59434e0826207460affc8990e5b6a15be122446b24bb60f88c992022cc92a89eff101af61dbd0ea8c7d404c9bc09df08b5c1da66f6aa519b8bca33ae79dd7b3dffbaede7e0e1688350fa537e1f76b775fd30306cc32ced0f13322cc18650202be99da4a3d8ec9984b0c3ef382090f2cfa00a22274a84963c189172aa4ba0a873065bb94dd46810b29e5e94b302999000a4e82c289c8990000100049444154405a3ac45b4191363b7dab44ce4792651be294c8f828728d0922297921cdc4e4db6e36839529d3a595c905c921f7daf79c06484cac85c64c7629d4a4223064a206f98a17d3f9cdaa9cd21682a7e0bd027f88e9abc26f2c222c0eef7bdffb34d37fffee3ddd7f705ff99f0cf72c50b6354f9bf142fd986baed059b8c6da432fe5d04b822c2c57125660625b825df46731870695ebbe437b6b85ac56d1974048c6d87585d60aed0ec19505b00fbdbbb2a852949bf406edf489c7812549f0a69e2c01bf1541d5c8009d8ccf8fe831de2b0b9420d8c14b1abb097057c6afd32fda0d27d3568ca7296fb72c20e01cb4b4b69c16a6795615bdb00e8f6117704347e0e413fad59a6ceb880524ffb073b274f9e0aef2cb0bf90dab5c6b41426d6edca71ff362fb86ce6e9f69cbc9cff8e182bfadf0b62d57a9d136c3dbb6362c26a957e4b0c653b417e52ac949946397a2b1aa214b27d1273a85e5bab4e24f91357632c0e1b7c033fa06d836190978870ef5aa9249523024d044918f9567d01c351fe02c99542d78a66c1a045ec7e40881ed220315481dda8000201feab44ad72d8087d6a8a52c1e2a76a990297e0e6148a1013e0908005219ded0716a7ff1259b0a290b13252515f52a6b9a664df346c3e7f14f4343a4b423b256be1a9b8c830fecf5d5af7f597bae7cc3e2ad9eeacd6a8038e78b2fbea8fdd5951a0ed110f4e02438338583a378f4cd385063c0e5402d1ace054c27b830f6ea2bee42c123a34387670570b82df874a931f900135ec8a252649601a524650242cff0b10e3f71ac4c88d07501c551373dad0e8b0c60aa32a85d8da0b0f655ad355533f427ba81360c52aa5227870d574c09cca2d6955d621c7b4f9049cb4c602949e5d2fe7a87bbb0ab9fd9e5a66de22a63336d55d580b2ecd24d76bcef6511c9bf867ffdd5bbbac795d6053bae85c9d29a15f81b3778d9aa1a2f83171697be461a486090838e56b5868c5247613e722b92467ca624b851b10a5d9232e994806d8d36db828524f4827ec66725d8afd875b445c74e099e8028a701c2836c83932485a7522c698a8eae31f1b320eed1a763e335b4a101291958737d277ca0d3ae24fad2be924bab105713ba15f456062027de744d744c6ed0684396851348e823bd02a700412878bd5bd59a369b59f3e64833e394772297d83a8b7d7e49425d323c369b2d301b4df80b2d631c235bb9d4e6498d6015fcfc6bf3e0285f36728807227c57ead590834a9a62c3f5502e642e685d2320e6a123609dafd4689401b74a157868a54eb76297e4d1ab80829526bef8a4a6c8d321641b5c898cdca4012df353d8ce76d8882a282963b98c0fb86bc601bd0ebc3a2509c6c0473a4bc11538a47ca2efa059f449aae48189a0d0b22dbb069df8887da81779013f3ae0cb54a716a403acc0c9d8672c823f7ea32eb6044a2a99fe6c7a928746e6e3cb2fbda2bb6cc606c85bfcabdeacfa2f0ce2733fcc6f3cc4a1485e0854385a9c456668a5b94c49f8800903e2d953a70dff30034f37135fc329ed897e294e18a71513a6d568826ec92e68745595c44bdf1d0176c5d91b41c134111fc4d2848fe75bd05d55f6a08d5faa232f355503b8ac461ec7ac2220b436da37ec741a65dbcaaf876e28e74402380bc582a05c7f2408e2e557bca718414734931a4068afaaa68920256b04ce9c2612a0f62c262bc1af8f80bcc7d781467ec12be9f4f444ef7bffbb75c54bf5bbafbcaccb8b735dc10b736a3337e55fdddeb8714b7bf4ceee6acf22b2a25302c590002517fa0a7a551eb6b2ae7fa8db2591aa0ade920c16c441a340d5560157c8df916b252d04e4752c1ac82ac0685bc905d201b6041a1d1ab90d5179fc9424db4a1ebdd7a13f63330888c7032f7d833a363dd0b45a11344911b4236351de26a8b752de2b75ec229e6a4dc636ad8596110be2f92077f056703bf277e47559093ed3bc198bc7967722dbed566b4e8a2c2297bc90bdc2a61d1d0bb927d7a0d759fca12ef1f546e02bfa4c6a2c42a303416dabb01ddfd4341e5068433b74ebe8e0b26c528c322030414feadaa3531fe3b9aae32702a7c0995a699a4a056eb52608080a24f1a02cdf912725fb9a3079b5263b36814172e06cabaa463bdf94a164218068d3e1015cd8ab5f27618f95725207df2e69e0047055fd581ba002946e003a60f4e93a0d9de874f8cbb293349ec80ef8755be85bd19f6f45382c223ad5a213d68d6c567e44dba4a96d9823db802a7cf2cb262fbefc82de7e24acf9e63443ee1a9f7fe179c52123a155c23f845f9010db4510b3d69137657469d52a53a69649c2ce73c5eb8aa6d6ace00b022b9329bb71890e89c945c005ee5013ad561c258bd81a3ae0448e72d34ac038c0e19214f8d6788019b034746098d6c0220d4c0b010cd56a2553de106486b33289e76952a32d0950dc573f9227933e41be235bf896a1420afe66b351e4899c979cd2729575c5b13a813f3bf7100abdc193c2d426ddb97d473ff5819fd2ddd75fd36bafbda25caf0cfb5469cba274f3ec8612b42e097a3b82ddd56ecf42b9287a112bd1c08294b0ee2853808d454588759d028311442ea9129c5a89eac0ed1030150b2a94578271e45d096e2b4cd6d83b7d49f4071ef0d448d08474da5c4dae035d058fc56d19fa5f516531063a414203d9c05a794097ca922d839e714212b52682c42c38306e7b68ac2ae8c76e069ea2347ac1402ef1440e9ad491b933469df6f8cc21286f942baa2d8b88f1b78b8b0b4e96572cfa842b60737dd4411ee385ec762143293ccf59dc3b30294f6c3ae20792c7473ca66cf36d14509268418b7ec8f5a3a7a3c7c218d1c1f78a7c9d141bdb56e817b9c0360abaac56454d244bf9a6cd462e92f2041e1a227568865ef2d092c0a1df26072f758337fa6812c9ce577ac45350208b78c9a0498622f9064e9502f58e1ec9623b80f934e834371579806ce0f9147d1a8c0402890fcdc049b649a26cf252ab52640b8d418f3a50dae3f7bbfcb6e2f5e20e9a5a2b81851cab567cf5e5975fc6048399deca4fbd5995ffe6b7bea9cbcb2bd98848ea9da9cda41ac1863cffaa78e95d36c34a3d4ed03549049394a357fa8b25a533e0993cc35be917854cf4c2615a35257510423bb09d80500de712b4a1bf82d349ad68a35ec84389f86415f840e194a5c024f02628eeaff65a08c2175717dae5347179a9ec5003d3d1656e8d80b51dbc274786921d4a914ee3a5ba9d7ad70832387347c885c529bc37d3869dee047f8ffe07e70fb41fbbf055790a5941575149b9851f38f9d7ea4fbef35dfae10f5fd4bdfbf7916951857f9bb461177ef3ec9684fefbdde590bfefd754d18d7cd87255e470e8c2c0ceb85071c9e347b2532297f4c684b60c1f0d5cd12f592285967856c670a59280943c36ec8cd3c8e913b043104941b72d97a18935d035b0c14d92ac3196d8cb4e4de31965be2a8996e4645aa16f5bdbedac2ce8630380cfd0a4d6a0ef242b2fa3176c1c3b776cd4f1890edcca02b152377298b16d6c0aa6a92979de8b9c1e9f48f8c2f8355e16fbf55acf0edf85c53ab92c55354d6d4e512e539e381d64d3b260898e9c9d3c1277adf05e396d76688d465b7c74fd75b033b200a6543a40814da24140ab40486e7b94cb4557974dbd468f1872812a897ada490e9c245a641bdc92c5e3fe23d88c7b03ae55faac612b64e573800534efb71401534e2bf21ee4536ad0056f944cb9a06d6a69431b68c37ad48b421250122de209648577fac8a35bb9c93f2a5b0387ba492aeae4c53c886ddf9097490e8e1173b9ce3bbed5c77f24b630ee7a8b3f8cca9bcf021998af7efdab195352e1bfd62e930551df700ae36c8d9e62e2e259948c93aeca24c7bd44810f9e298d018f43c519e403f4c2ce779498c4c619f11fba036fc5891b41c04c68d880bf9056452ebc68943b1c21cd071c5bc6f91a413a742abb6eeac9a736c9965a35ad041b10ae77b85201dcd2c9ac2afad367e45198aab4677245bd38b5ab067e8163d2847c9b79d6440a894b76b8975797da8f97e03b7097c133a4aa350dfa005aa5271f7d5cb76fded2bdbb2c20ec7e7704c5aa22806e757a7a43b9c3cf6297856b4fdf423a04bd61b17c09ad254b184d90853e45d34ac53275be295350476ffb506fada8af1275f198d4d173a0522eda3b3658895c6b94a76e644e5b7439f0ab816e839d2429f2057e0faf157903cf484a4102064844014f29497672201059e3e92ad7b0c1bc99404bdfa1cdb60afb406c8c3dc8f05b151e7c63eb55cbbe530702453ab2077caaa689453bffae23ff36676531be7bef2e27919dd807084ba8b3316078d4e03181345389cfc40e47471b9df1feaa41279b114b32b4bb3adf70c64e70549ef475fc68cf296ca53de5a4cc8715fbf6301c8040ba2825b75c45926cf30535324167e08a0aed55968b44593cc90c8d98ce362d07bca2cdb60cbcaa2453c6fe90a32c1e2be38738e468c21ccc4b6df1c40756007f94681b72d3163a69a7492be37b900f9e326cc247924b86bc8aa22cdb92acfcf28cae9f4e0c6178b05e70bb100df0c058b295316ead54cdda3397c2abcb6a8c49b9a9237897c0d138c55fb229a4fa96fe60ee379ffe2fbffa8a5e23b562d018c008c96b097ccf122f43cd101603cef456a77fa1de2595f30534959549149c755c63f56bc7931626125dc24f04b2eca29c34a911ecab35397c8153eff094c684ae82069396a066f8748874fa578215557526834a048cec1a6782f046ad42b3699e379a36b3365c674c04fc0673dbb2ad06bfed76036a57936489d4116d1dfcf22e24d7532bbc22123e2cbb34b1806c3931ccd09be6490b1de79c42ae763b0d5882d582ac2b81cd505bae774bd35c3a3adeea1def785297c09f5f3cd0eeba6f839cf9b3e4c7ec983bb87b8ef1a1b723282d4cdce88bc842321919248f1f51eee2213f1895321a993a62ab905506d64d459b8d44d00f3d19d8914a29afb4a38a928b27302bb656663bf580c63a81b52ddba335f20e1d610809862e50f411317af0810b5d80f9c01fb8c8da691c78b42ae0c0b536692ae441a9f0de303ed33c23fb44ea80411b9ae9eb30034d292f5c7dacf8c18ed367c42d36126d6a4ada30f6f9838b57e7177afdb557b5c3ee86d215b0228f2c86a7b14fb526c4509eed76d6e9c90dad9c60ded023781a4fa1e72aa419493035f8c935466955e4096885b60e980de206ce365d5d25aba897f8217760d387ed02a1d16fa57dd0b12575523e70c7961d3b0a38dbf996f81e102555b3ca458b482609747aeb90452fa8288b9da1055b75ec9a76466a94453bd07c3a9a5dcf7853155ff084e290a8072e4d694fcb5ab2691009190247e95a9e439f6db5a24c0069559ada2c57537e6d77610e74c6daad146e23d95cff5e70437241db5bfb13abbca92cd071806f3ef50d64c299997cc580268a67d0c500576b1a4dac0e54875fc5c98cc3ade00ee73bf8a67a2b35e03a411e82b24deae000a01215fc21659225b9647ee2ec0960900356c0ac2325102c38530d0180b52570c04ea695e0317008b6a53c96e94fea4cb0b215baa1df70d28693969b42af35644da0ad16c4a84cdee53ae064b753b42ce8121a1d7d837b346fb5996616a9a67155c615c9c282b0076e5ca7216f7277c93a3c1336bc7172a2471f7954f9237117eca486ed009836934ee89b37b3564e27d9f92ee47bd20a5dc88919cc42bc0edb604d8d0226ca7c3576359c602732e5096f51b1f485138a0000100049444154e921fda8cfb4a69e060c17bd349e34581d9b1daa9461da0990824e4777c367c25676a90acbf43e645ad07b41cec8b2525e190bd8a8836bf1033f8dc77c876ea795229fd1c25701532ed954e4f1131e4956630c17ad1822297c0e3690223fcd344117d98339b5222035b9ac634e14c72cfaafbff28aeef3621d1534b1301d1d1fc9061a3d8a94361a448b0a1d8f4e8ed56ad28e6b45c1a0930e368dec2518d2dc63211d9eaede69062e85155968018c464b8687c96be4e03b49521a9340b64df5903a9475fd54da2153e207bc2e60aee90cb92cd1acd01eb08636309554966d095c998c4ff4e8910c9e111751354e25a37f7c01d5a5814706afb1919264e8848fe88e9e8136ad688ec4a14a09bcced88b3c93cac8204ba30a2ddbf2a0636c8c4ecc8d46dcc922228459f1a54ede8a3ea38525dbda71bd9e79a3b7f8536f36fd5f7af9257ded9b5f573544735331e05d4c48060e779059108af6cec98221a5bfab31a062a2e107f8d13afc6995a9c689186c76e37894681a9319a051ee0944e0ee093241aa7cc16760c13fbcb360c4b13bd46c2b6de110c79f5a831ed71d1dc9e2a4ab606109d8953651eb305eb52abc42fe20ab855a829ca6ec52d1a7a894a56261096a52e0f7105a91b35c9c14f6ea2c52591c24cbb622cf964524a711dbe34f8de737ac96377482b6632bf40101f859823816d5d98d1b62d33c7ead37279db4b79a747c7cacd3d333ae5a2ed5397dac09c449d04c704ee06c11b677e6640d7ad1b3534f8a4cb4d28e8c5d3cd4c8a3476468c5c82117dff4d181813b293a7684e8b1253d3152ef5d032f3b49faf2b16ad873e84803106a8cff10097b21d468158f0d2c794304cca324aa423285768127379a00801705051fe1193964b3d45a694e5021559b542609bac003a18c6dd75e1a18abb2915818a76a96e12f5b53958af226ffe0f0e848db0d0bb46883ede9e986b138856dd73c73fa8187d1a3a385c1a96aaa69663c7682a5f2a44fa6046d613b4a7cba56ae69f8966de891c8c78b286c6a1b18415572d55f247898143e0d5ee913a079cf089a3a4c93ae11a5c0181a349041b02b41df86a6a4b4d926b70e4f976da1ad4493993c90a448453cf8274cd4633f7409af4e338469eeb47760bb0a1a3dede36b55aea77c4de28de60374004830e9d00b9df4175f1d4a46cea127b8b6c5871e9390904ab5a63637993cfebe30ff0c8e197f9999c3d8ec990b7be606486fe94fbd99b4df734cffda37bfa6735eee16814c321342125b0ee30c2bbb016ab42d2ac67ba5d2d5a80b484a78bbab34d1c7f4d6c2c2c1582b13bc5a5326f01a3af42fd04c390ed72ad0b819cea13c5d2a288a34c108b28ad3875eb9a9131c044e9c08ef56052fa91015197b78e07465a964b1e6c98147bf0581961d3b581c303224d952836e7331d717099c15a6f997da08af5c31ed39010c6786a175e033f0b0d344c099491b82d2028ffc6bf3ddfe522bf7eb3b8ee0990cc18dbe2bf22537fc12acdef9e413bafffa7d5df2a27f8f5e333be20d0bd2c9c99966f27de0d165498acd562cb60ada5de12f8201c2cbd73f64f4211ff0b14347d6c055595525619ba4b41dfa4ab6d3a495e0a73c4cfa9e843ca87be8bf0e2e0b0b99a1952459a1693126d0edc80603f5d816bd17641f7420b242afe0038a0293bf07960528eda927459e0e1d0194b2254ac886be9df108affcfa75da071e6d298bc704c10e52ec3b6821431f3658159a1ddec518b56a3a66e1de6e8f86e94051a3fd8453c8c9e9b126169099243877f4e9c86d5b19db357e834e0a5252fa235b60611edfb45ad86b0d7f70c563ae7d3bf63370e5e29b46e057e483b468521a1390351e740efdee414b7402ceb77e4477a5d64991257450522907562aca5d6a86b615bbafa2a9ac427f9b9cd483409eb935caa014f53477648bee07be85f474d267490580e19dbf5a1c7bdac84bfb41062801da41a419110ce37426619b6424a0f8a62b4024a0044919f9aa4a8d7831cf1b9aace5fa171d5ab159640e0437d7ca77efde8525ccf4d67d18e9378ff2afbdfeba9efad6b77016c4625289cc8c6c06ac3394e526e1d4f8863a62c791929489428a931e766e012be8081281ec7259320964cb232827f0561d6886a0251d0200390e5ce0a4bf4516703a141374ec52879f90650458da578275a33df0b6659bc98644e454d4aa08c81b4d38a6ca0a5c6b952ec952358fbec34ed507fa126b2701081a0b72af24016c5b79881fd09c481b6de6595beed91bbba41d5752171779a1ce89059c5c6d297824f134f81629fa9f703d928075c1917c9fa003d1c8987fc4963fbdb1278867a7b5b080ade81cbd3b340b7d3ab492c8649784587ca46bb804963eca5db64903842fca2219d02e1eab4353b4c5a65da391ea754e5f0f1df180133928c9a6c247618d3c4606400fb440ed8c619265c0e131e81eca6f7c77f48d8e2b7907d9b6f890c8950421f012e462b7699a641b7a919db1a10f86d4911ad04e1f9916e8adf00758499006b2cb197b379aa0119db0795e963ffcc8c33ada36b5e107b31a7c167cca068e4fae1407ef92a22fdf836d67d10d9f0ead9514f00e33a40988d694bdaad201ae9352b6c4872fec02cc281f5a946e3a94a7839b7ad2a1c37225f5ebbc448dc5d0a23074ece88de70be6521a0d4c70c4d349f9000e301f24857f94e9c0f6f48d148051183016738e924c7be88d1cd9a9c6175bc123eda221f4a44023c2aa9e4d0e35388dd6432e204dbae618f906cd4e9b54d427e652e54620f4a0910525ba8967615e7ce9cb5fd4fdfbf7a8bd753ff56652fd5bcf3cc580dc976db52a954b87c9c1a032889d419425db5a982c7b497b7203d7bb71e2ae95becea48c43e2035a64ed71e81d3b87b4012600b567279772ef1abc0c3f41c7a4d69a98ff24e80500e713a9b1904484b2d580b33c60969c749831091a1db8157ef6751f7c6d6ba16dc82f696ec5371f4bd59a8420b655a489aeb91918ea94e954526722ef09e223457604045c88af56d6d1b816d92abff113fd2ececf79d177c942b987f75e6e255baae879cdcfe015144eb9b2daef2eb863df61ab45dda504abedf1290bd3462b2fe63b3bff8e5d139cc4b312b87a6440ef8efe348dcfd09352ab097d24e7278c95c78abc76a9c29bf6d8d3765414df4a3140a13d72ec9f2098d429cba5a435f60c0048a1651b5c2be515b91692e1119c70b534faf5a307c1c502109aa4059bda40511e64534e15f81e1f232f47ee2643d736f4680478c12e912fbce3634981dd11648092015b90978fa2d78aedb2a8aff04fead0cf627d7474cad0ad4ae07215e3161925db9af0930da79665d02ca53f468b3d7b64163518a41e599431a68dd681ef08914e378972ab22a72029faa46bb4b7928dc590a9432fe323c0c263a58e022afac543b36cbe8b943a59e040a506c5d14eae0e4ed126e09300140ffd039ee2e8883de0b186003a594e0fd86881cd684a81b6cc328a942af242273a8c34eae8485f68afe4f984a61121e2a63efac2e70d1e001eb8d10b3d05983c9babcc8795316e2ceac2aeb896ae988b5ffdda57f42f7feb9f6bcfa91facb7e40793be39f4ce91f09b4f7f53c45b26d021f80c17c1ff32b01d4fee3817e3379cb131c0c6a3ca387b5400085045a115a7e838c742ad704a933abb398153f465728f3a3082c6aaaecca7565626aaa1b599234309544e25ab04e3e035da130ce2c22b413d380572169de025852664a109f7e1f8e04333412a8ebcc60385b47c60a9696e0a7eb5a6eea622b5225757f1c32a25e29b5c851c8b829f0534c16449f07634f4e124b2d970ddb11dbf41f2e0fc3ef955ac43bed308744c8435762425b74a276301d9eb8a534874cb6e7b66b26ca1757a7a83456555aec2f64c94f50d7c0299b0e58a8c46aea117baeafa49bda243b36c4be8249eb295645b63519b447f97cbdaa36464529e8c77f2eb643b661558a4aec6625e2e3537850f44a5ba0606c2067e4dddd86b252d437fc8ca36a9d169f5d8013fe86545663af8987646b80322ab1584dd6460dc4a855e76a1b9189765e407b90782643e495473a2055b4e5d7968d44157f15e6777b9d3e5c5955e79e5791df37e44f4add8b11af491ade38061df5ad3f1c9a916c6bb20d6480656833b58904d3b82d3d4d57140be81089481220727ff80d63e945b958c1eb14970a9693cd04a0e18b4c0c55f57121589c615b91c63c363c51fa0a6b42b0ffdac828086483f34d31659d2ddf9b2ad74f04d6685f76842ca81ab3c6000c0b41bb4a24f6cf9c6184dc85d805906bff14d29449029d9a0496bf02010cad84414034746319fd18f50b625525568f5144996b151e16b4b36508c0b9e041d1c0b3ee70fcef585cf7f4ebfff471fd115d75c7a0b3e19833785dadf7ae65bba7bef9e70574dd9869b416690e2400c97561cd8711a5b2b4929677033e0c06a240f4711155c4093560d8770694ff05b12a0a441ab53c613a889acf3052efcdcc8a1d2092a8626a8829cc4d7cac4a14b87c7c3d7ab35b5a9290e3b71678adbabd1162ac19f2807be42083682ce42f04d4a7545afd04c269e04ee86fea61cd93b44d65448c1d9071739d6c80aad4e2ef2a99a36d3aca3cd918eb8969ae659175c633db8bad4252ffb32f9d4910a7848cac37635649d3793e6cd5697d0bee2b48141f8746da78d8e4f8e15bdb280eca09385be1334b2d0746c04a0b2e0280ff4538f6c42b904170de12573166c55b2adf0569ea17464a2620d59280123999fd05208217327d9a94198be949362f7a23d6082de8a4c50043070c91891811b2003420840fe151bd23b3e34aa67e71247035610b6031f1ae08b07ba7b7c262d53c36e306d6f8c29789dfe05fe92c78f74d0353806deb18d78065d8f85604186fc83cebc7f7a4030ba7bff75545e757979aec2560bf4ee3fb8afc8ba524e5bc39ff66c862287a0953615f4201d1924cbe046ea055d928f76102c69c550b6d549884d0b10747460633bd055f85f6b919f450c08d108b8429762aaeaa1049ec6d3e5eb72fc3c70560920f4d1e84bbfab865eb0529eced735daa114204a870f320630290de903d8b28af241fe0e7db4b064fb3ac1573c2e197ead1aeda5c8f52352b2e8956dfde81945be10cae05401413f64346373c96c241745a9b475c66ec11f7292fcc427ff549ff9eca7c6091e80b7d4a7de0cda26287d9efbc495499c81b68ae1e29b412cc6b494a7ab5ac3278bc0dde9ef347626d7822b53e4739804b48363205b2b41720cfc9e17909db60e74fa7aca3808ac14675899947108e165a04b2ee569d594a09e45cd160bd14ec93b01a303db09ba1d675ab33b21af9ae42a8da081f0850ca183549aa696a28adcd06a7c912913b7e849994c25cbe0ae831e65f8acec80525f0824590c57f8ee979dca87fed09ec602b261277bacfc5558817f4900ba6411599057e85ee81359f4060f68574ddab2802cc0ad4c8af098a64955e63473a41376bebbcb3d13e4ea604b02b0b01fa2c83625a891cb69cd171ca0dbbbe5d6a053a426016097c663d157b25338d0e81004536beff05901a03d65f4206368d20b0fe0e8544397d62cb75221ab2c9e7c81e70efc3a9a56e043d3e88f4821a02c7a7bc62c297d399ded81eb367892a16743d7964d8217df5ad98888a72ac11579106cd047c695d4879dd3bec227b456add82bfc6818b46421c9aa0ebc3de9c5975fd7c5d55e179c003356bbab2bb516fb37e57f935cc10fce763b10d57e4d0000100049444154ebe4f4f4faa4b857b5261b62c860c34b622ce04d990ee877451e5ac4a4d18f3fa8273069eae496d3400952b459b2f958078ac0508718753e5dd77de9f77559920fd0e2e9291f88e90d93887a27058e6ed996b12b05100d16298894901c5db021df54832aa59b7a81639baac1d321510f691af9d09e6fda5a950add6ccbe47c8140d9759d5b69e39b5c8a9d0c6eb9c9c02435e6ebd466656e346834fa122b16c62fe9fefdfbfa388bc8b79f7d466fb52756fc89ebfce24b2fe82e2fd01782e218cc2a59499628ed5905cca0e13b3812aec5c0a59c5e66a7cc040b7406b7989a5556d28e8060bcb7cb23f007876e351cb0e3a8a6bd24589a2f3e053638bd4b1d1e4c7101a6041803d8c02b9ca9115c41820e8df08a13195a0b72641aaf230f0de174abaa79e4e259095ae552b549a0086d94a72a6da538ab2db42fc569f327216c4bf42f405783671029e75f8b5f7274be82df8e05b290a5906fe6f4917f5b90b4ecf67ac015c92e709c2e1616a28e6e283876c1e299a03fb3eb4cfb150172cf228219747c7cc2e2d9c88fb581a6685f48818b8d56f8aed87761dcd28e48508ba8c8084d1bbd81211bed6b90ac21bd0d4c10a82b79a71df903db616eca1d3957ca9d415bd69d62df2401d4c101856f33fe50006eb4c363e5b403087d0420ebf0d0de45855c2e310c022c2d98a243a00ff80e4f53ea8183af9123400dbbc7ae5958fb757baba6f8471607d0d4b14557d74a054e4abbdd58781939e88d934b6baad610a190abebe8f8682cd217177bbdfcd26bfac1732fa027f24027fc646b77958d8254f0dbf0aecb601eae1b17e41620268da26286a14348f8d0066b64eb24d190463af9405a1e3f54e8aa2ac53729fee8631bbe5627ef104a02854fe4973cec6369542d18d0163a926d595205865ca346dd528ac984cd060e0bbe6d9abb286a3c3d3d24f234ae28e7d121b50a0f983afd0b78e90820f5141923345692bae9372226ef820d4d873c65b49394be7c4353a1b1cab6aad1cb248c6dc2bf58486283fdfe0ad18910d096acf3f307fae8c73eaa7bf7eeeadfe7f366a775b0d64f50ca95c9f6cc77bea30c4a478e6a9392c76192e2008c23fd74a60787732300505ed38423d98d9ab4e260ab18706092b702ae5aa09440bbd01e7a09928268025f178e03be5c2a1cbd41db58654c7efa225708ac2c62824bee5e3341cbd60888c0340282c83bc1358b49da5702675202eb026e68a6cf1446106432aee8de2582f42cdb90efc4e845077a1df5493497cc8f485dfbae3135a24b8bac6985d69ed3484e26669acc38f9767bacbc587759575c8de41faeed584cf28f0a451b6434b16824a8418220b65102d68e9df0157ac44e42dfe323e8b090dcb8798b5df2150bf1d5580c237bf4ebd87c24645838c5756cbc6741a12a216968a76c7bf0ab920abec5c4146d062a9fc8b9822b78ba00a20ff36885fe1572ef7969b9a6016274a9dc542d69d2a0834ee93f041f2b32d9067f5568f2c5a72bfc226337ed548293b464e124a50ccb816717399680e7c021b70d6d28228b6dd9d641ee552b63de41b62da1839be907f61a6f6a936a8c5957218df99ae7c3f5e1f1f1a99e7cf29d7aef7b3fa05bb71e4eaf0a9d369b0d164106850ff25b9ae60d8bcc02e18019b826d19f5440db00e5436e9b16f0916b65c5ec5eb4224f7c31794f6fd7a0058a42261802cf36b44b1e3f00511fe5e418dab60e0f39dd29974b382e54c9e8b7a146b7c6d365534912396d4647dbb23d6490834f993e119c8dac8a1fad52d9c015c95295d42c2a23451f29f447757c652c509bd668995e8d6790ecd429f0196d36b494d46527f7c84b0d750a76250359853e8c7dfc110a87769744ca75e31ffde91fe9fce25c6f9507cd7fb2aae68f00fef0c5177091cea4d8ab90c8669032b2a40c5a4f6f267c86307db84499a9d2352643c79945db4a8ac3819661571f0eb8ca0cfa42005a086c0a0d490b5bd0a5f751a33be8944d3b6dc66132d911267c941ed387e32c7864824c87976813b97d9032bb53ab683674341edbeae0b56aa3dee199141aaec0a2073432996dab488028b4264e13c41bdaa49c129a8c5eab82db91a31370ab4c7fa9aee9b72a4e0b04a569e2e5f829a787bc785db81eb9e045dfa5f69c4056ecd061b2cf4241de25d9d6664bb0a26dc7554afa22d32229bf6fbfe545fb3c6f7999be534e3349b017e80317303ee88dae3d1d8c99640500d2ca137a8157da25e41625cbb60094f9a1201735c62e7416e8ad04e635fa9244bbdd548d14ddc943c82096b0350c52b66bb4888ab149914c65855e0726c94008595716be15dab9a2d88f7e7c06fc8cb10132e580be519ee649361d6f349247ce05dbadd0833c2d583505c0ea7a6cdc4a2d7e85cce98a6fcdd02af498582836db9c4666c6615615b0e0b59ac6a6a2872e36b54b479c5a620798c8f648905092f9b2d326da63477244395424484830ef24a173b2200664e84b835d3485864120e5431b15413a99301379175dead0a1a25101c014a29bd1c16fc833ca8c490ffb2e9a6507d29292341e0392cd1b622832863428b20d0e707cc453d46d2b3fe21baa52f2005fe774a734e88496469f784c02837adac90e30314eba8827b66584b1ad621c4c0adc8ae2593c72b28f0cad4ab9e29e33ae6efa369be1fc1dbf059fd55be061b87eb25abef2ca2b7afdf557b58c2b98a60c0aa389500c179f55090a696fb449c57702e09e09450f3e61d9046160831787137d7b7a3a81410d0c9c60a56e70e3306c29b4e2a10bfd038d8eb477f0f01d3ccbf235cd2aab41c3b6940f0069134fd1b6e2283d0942d51a5c9018b8a9c2b7c06d2305ae55d304cc86a0012ae4fad0278107a64224fa278a0b2fee4a552519dd06a4b4da3ad0d1e09320bfc07bc5a913886c6bc70241274e3deb9853c8f1c9090169a30b7645979717ca8bf07d76da5a21d2a12cc5163357545b1688f0ccdf00bae21ebe4b6ad04cdf3c1de99897f379c91e1a2bb68abdc23be391fa1a05400a0db75243cfe0c6de51ce70431dd94e558de008b8ca4d8d36475fa16f562d16f8e8ba70b2daed5721b260a9699a35356b54406ef0098f568db6cee9881d36f6b04b5505b592f9899c91d7365a498336b6eb3156fa3b1e83fc1d07da5fdb7074d177c0906c6357ab081646e6e0c6076c20108e0f62ad5a233bb46c782383cbc8b5426e55216f7ca083d303238b4ceaa1db54c0c7be0d7d02db6a42f78517eb17ca9081a6c6e6206941ced493aa9ada90ab645be387bce06d435b3ab4b592aedbd405eff145e1e00f2b8dd18bd6209052f2c005483f7a9807911bd24ac03f40947c0d70e00b0640a6d5e6dbe93cd0cbf7f09734d12f126205414a9984492802c90741b530361817f1b119635c3ef033b0002a4f6c0a911441012eb8d4d2be322e1d2376066a255d7701de87d61480944a4da11d796cab51c8e971628e08a1b2197dc347aa35c5270bfb1b79567cf5a9a79ee22aeb9ede0a4ffd2495cca47ef6fbcfb233be1abbac96095025331062e0569c6fddef64069d31946d751160c6b7463dbf9618a7a242437005b4e50407e0b9bec41dba1a0ebfe7deb268c3b3f8ac4ceabde2082b3b47fc8236a9b5027f3990b3c6b35e3b5b433ec15fb202bc47be4e8a6377b0ae78cf209e358e0e9f1a601dd00eaf75e02c38bed12f4e97dce80b09b542f666d9c621370a4f1014c7a551f4a053979d45628566a9aa01d7b18678cf713eca2b0e1cf60dc72e04383adaea24ffcf47897bda735d719fbe629b04fdd0bc2232dbd815211afcab356877e0f62c3ab1bdd568df6e264e34276a750868b1db0e7b2e8ccfcac48439e2462e8d3c4241155ad8524d5525238f54b4ade8212cd6d5873dc093656ba4f4acb4478f3d7776b159abbd0afcce58c436e552633c3a40b6e55612fde913baf4e8080de50127846926b370055a0f72741a3bf2afc0a6bcb090acb4adb40104a9c8869c3d350b468a3da64d236fd425d13c70d890ec39e976f023d7c2490f25110b5e140a277b636361631d5216d915bc2c06a04173a3d0d8b37016fa19bd5a15f3630f9f0eabaed038e6147275793851869f30789764ec9254e09423e3a4c68263eac23e19a7e85ff016898f3a360453e16f592299a47492062cf453efe8107e4974a19524c39d8446a34c8ddcb02b1de4906c6bfc20870de410565ae1dd199fdefba06557804952c98c15df16dc57b5b2dc1a65ba5b29ad9d6fd9c09620a5f1d0187a29c34907fd0ed0835f3ae00771509d1a34bb7a2fd2012e8d90a1dd327c01d4841d3b7eb267e18edf1b19263649d59a6a22d9129f975f7d494f3ff32d688582fe463ff593d46ec74bdd67bffb3d2d04bdce846f95015c718404184602fb67c0c6e464c08bfe56a55ca95435e5b12d37522a81072e2e6637a5b5e39c0beea59a94bbf44e3f604aefb276950dbf1e5fd24a3dbc0061f05728224be402c6d4c8d4c2abac6a4d8dc9d4daa4aa52837e2666dc4f3c59940eb4e298926d7650abcccf15bbfb048cd12f69e29d00999a0b1956359c71e23d468ec59dc56d2ab0ac21eb92a0d435e0f6f445d6e01aba2b7d0db94459c8368dddfaa4e30def314e6e68477f785fe63d07ef15f23e64cbd589a087c2aa6a4a2e9ec8d7b15d642cd7e03d6f369c66b60438aeb158883af6da639f8500b802db11066b0e12b6a122d98d2f6c40b5b0936d197dca56c96ac86ba79464708195451cc62f16ad2c527d7fa965b95206a92cb59a484dc12dcab6d5b0996dd9d81039d69116a9badc4a65cb3ed09735eae289cccbb5ec430f16ac159df604899560218003238c3472e8c61ea6bdaac9f083acac4090a0b56067c0d4aa645ba6af2807df2a75ecb65e8f5dab034c070f6cb5b969c2a7aeb06fc67342af69daa03fbe088e0872558dabc963cdf366fc465678a1a504274b7c4b35e886768d72413374879dc0174fb5f43539b0d76920a3abe93f246c4659b6a0964cce0f75c052ba4e126a29b2a4ff50b0945e9762b3033fa8806bd3b74a74cb860755e58140c7ee4031dc87469ae82992a143427ecb0aff8c6b72d15d34095a92f9d178e24d870a16ea927de8b31b28a541843624544e526989ecf11f3a8510b483034c836f47981545d33fb5c675f1468d1c08d02cc94a3c7bea5b4f69c7e64a7fc39f61afbf5cc7fff55b9f7ff1f9c3f5152f995bcd7235989a219056dac6c456a9ecd1bee229c321a8ad4c72c652a32b034aeac3f104140e493981b70b7c00f36e2434537fc3011678009c8f7460a10e9d6c85f6eccc535ea1b367e2c6712023d701d0b6cc04e45b991c2b482d475c08d956026b700ec4bb024b97826f5b4b824c68c3874c4911a2e18ce1a381d8356f36345b023934930bfc95806317785d2b341a6d3a80a9d3b7b0836ec87704fe29574f374f6f686607757ef98097e1e7f0dfcbf6c805afd83aba6e8077e84af42d2a3ca45a53eee85bcdca4bf5f0bb6211bc22c82dd8868f6c00c1e9a4d02183aab08d65fa6c878d0c418b1fdac864fb00875ddfc0dbb37a24c0e6b78f729d96bf3f3537abaa0df802c7654966f2960a9a559326f4eb182f0923a88013308381243bbc828728d8073351a05d1570cad8126556c666a53f36d6780c6e91821f0d69b4e06b4dc8543ef445e6257e99843fa41c1ab6c10d8fae56d058a5a123e336b1c8f7c88cdf910948601a1215d7ba3b09dc2c14539b957f2bd211daf09bda34fee065ae7e3bf25af9114f728fbcc01dba23dc50909a000010004944415472a39c7a19da6515369b482d78d77dfad113fc610e5aba8a7e03d7430c5aa6d56fe4943b82f30161a546c67760d36e3b5872190a1eb4cae4456a569e1ee727055e408927f8a308ac0e05b127926dd21b3c74fd1cf846277a357839251de6c7b02d14232489127828c0b7550a24cde4259934ba800a43ca91ab6c157d1634f14faaca7c2fc6a15a9368b0e92519b8975e7a49dffbde77f537fdc15a3f3915bff6f5af8961d2cab5811884087318240622234ac2c719382bbb83212903ca6d35d373d4c657072e49002fb24620a26db7161b8875e0f76a0adec26e99312660c099891ed8c2c11044695fc1ab829f9c2689c651235f092e8e53e1afc1cba2241edb2a7e20aa01cb7773a53a680cd924013664eb43fa3efa0700fc6d13b0f7a33f4179a5a34b0326366955da6e37d00827eb703a59a903039ce45158706ef1b80cbd2c004de589c07fa2fc275266a2ee781772c10270ffe242e7bc305fe15f924640814f632c603faec5ceb926894d8e780772c402990566cbcbde1dd775ff3ff2fefcd9b665bbebc4bedf31e65cfbb4b7798d1a23812801a23105c680a080a22b70d88ea808bbfe4b1bca551140214c23834020ab431212925eabe6b5b73fedde6bcd4c7fbeb9f67dc8bfb9aae2de279ee79e393373e4e8c7c8ccd9ecb3cf81ffb211c62f171643e11bdbdc95edaa6a0a3ea05ff094e2cf892ea2583906bac8458c1843075caf2cdef16bfe31ddf9fc4a773c7d5c780a894f64cbf0b5adf8a4d83064913a45df72c624195ef15ff84f6e12820b145f8ea053d3734b8a87b962872903dc991a7ad6690688113a7205f77a1632c26f6741dff64d856d915bc0174668613bd980b04c29897f33def83534113d1070875dcd0682e61a91437e090db3a954b381a0cb85a7c5fc9248b3500d78afffe808dae03d78785203cfb7adc4421c03b9b17de2ccdc146004500bd265776cb1bdf4464dd150d1b78b1a6d8b22e39929dbe2c2695992c15149ba87cfe0d2067389b1c19a34913dd1315cec2b2cc22d7ee81b266b1e498a2fa3134d85756c15388a2c19f0545199d255327ea119219a1875c46f6069c99cb24d01007df8468f891f0778a0332780d23852d071829ad38eb6cb730a83e80588a6912589aaaaf18b99bd5031987efc1f5c3453a168152dc612df2ffdffc16bacd277e9c86f5f7de5b7bf2a624b99bc0e2228f7ba1062023557d21f2c4a83526a7e4ac4065c297dcbe0f93f513101b3f824a093606fbea820184c329632de6624412c3356655e29f18a04b1237ca8859c309b2463ea14b0b586b87072e77851363333e80ad7495286cf2087a7ca46ef41325d1717d0689378634257b44b3baf24ba5b5dad9d45a8d3ee5afdb477fa5681cfd552602e7870a799a7089791359033794cbed0d6b2258b2c52d0e7824fc7825f7855b56dadd03d79fc584f9fbea13c39e47f303c180b5216ffd0193f459fbd5b5d2514e015c95913b917dec9db56b370def061de78e560139a4ccc429faa02ddca8fb816b0cdad42efaad8562a6f1af861e267bbd48cb7ac1c137e9e627c60cb50e0732005fcf2ceabb327da6a57c32f77df429749bc21831c39a654693f9d16c873ca915352701b9baa367de740efc0e7025866334adc077428801e5317f8679c51ad82cc41c20efc21200a7f605da5ae0682be73407b68f05a360b78d8d956312e98251ef179e4ccd806bc189fd01dd129489e8b5ff726a3ffb2854663c3eb57af35d101e3d4def488580cf43c884f781430cbca512a5ad7369e0534573ff3c3f08b4e6947fe158bf1d85452e05823f303a15205ef0a170723a6e20c1c76b4aea70307107b4674c5a689ad33f504270894f00acc308f9fe2134ca7070eb8b1b1e8cd0ce29f40cdc565a0d20416be03de8b16a8abb455abe0dfd41b314fbe54950c5e68063a4d365a768215abc052acd23aa09dc8349df09ef824eaa880a0ff841e51744ba0aeba894d311e9f2946a8f4f56f7c5d995bb0f99e3debbb65599c7be61bc898446445a1080601e2a4416c49ad042a6505441ad48324103879adb174879e733573c9f004a1925c131a35a11caa6a92c51a4cb6e0099cf0bac03f0bef20a19258852ee1675b49701b6112f933649b7a8ac62a53031ccb652579060bc0402e6aaee4e6b25025237b2a4f07139c094ef43174e27089f143b9fb3a78f72e5b5da524bea9c3676f902ca59d6a90d46b8c4e16ba1446d778b955d81b3e3434a6b4ef279d585c9f3c79caa2f35097bb5be5f5d0cbd72f15dafc1b91e51b644f418013a2ef39fea18457eef86fe07173ba81df0d9b17df4278cf9b6f29078b2a2aa959885552c127b24b4627c965d9571fdaf491917ae243baface11d1f0ba2537f2fbf4e733344cce8377ffc1090d8c9053eab66267c13bb2e3af253f0cd17f226352272ec78afb047f0d2a8b7b5aa068605f6c8da2137d0e68264f72e3a047993870803389db64cc2554f0b2b1b7a22eb928bac6790617c6cb273a14be4e5280d35dbac187652b4f0dd9d8779e42c2f7e0e9387a66acb756cad62d811b99b18f2cd1fa650df4403bdddcec6bfcc2eb44ad0307520fc66d435af47216ed56c128b2508f549aa980971890d1cfe5200333a5299672ba54dd2a5b064fb660a075d0b60a103038d262083d720e5d8fba56d7eb5cb8363c43bb0aec181cd0db16a74cdfb63a78e455f44e317d0117411fc464f00663decfab7099920a1d1b7d4fdb491b254f8cc98d72a31b58e835f1d14809ada606bc206570c2de344dbd2ac9e2b8e20cf2c1008c8c323674c9d4528123d956ba2f9fbfd0871f7ea04fe6f8fdc1f56af1a7ac4b02f7a52f7f51ccd5b5a0eff7775a133da6988404356369cb964894043f7716266893fe99a08b219535f9319d3c0fe46e6d32812f73eac2a263670263664e16b06c18930490822d6592173c8e636a001fd08dd01404f01cc8992496691bfd443dc131758aa04d1299baebe33becc1e27c8b5653b0d4401f8c40d641192cbc9705bb70d798453b764eb0c367f18ecdf427c4f14d17ba4ce9c4a2721d9a2a3e0e67a1038db6e13be11b5b2581943bddf06e8716621c5a24facd831b9e42de5a9bc9cbd72fa0d3fa771dcb6e986dc86ae20113f100a02cacb73c69b8ace872b3efba61423eb879b86c187995c5263258fcd61a098fd83124d4b00a7e02762d5a8b416c24848aef5055abcd253e38d8402fe30cefa1b427720bbf46a7de1b9e05cf2c0296dc2a9b0561aadaab08b05c0b169b267cc5113c2ace852043abb8254a031d914b39787298d872107392131dee74e5033267ee68c37364dc96e1e3baeab1752bec8ef3d448de84ef9c4b174815555ee3af0b1be49c05ef29c73f14b0ae78e4ca920d23fbea352a75953207aa9b789dc1958a9f261e0f1e3c569e2a0ff45f7c64f0c945784466f8b82c019fba1e81a795b155af810986e15a12f8531ce8ef40419cd8246adbf0b712dbe0ccf8429321305d1a924ae647ab1ef09814c0600d8ad856a3692096a191a8c53129b4e54d02ee32b5e183b5d87e9569e4a76f45a741cce2ef839b8431160395af74a20e4d8796b26d9b3636ec8db9b4828523a25b4c18d8b7f8d119ab1c904fc169e96c9b76644e8976c3cbf0b45b3663064c7fcaa2abc4f19bdffa86be978ffa6e18977ff6ffed77dfd10ad252206a94ba4a69890936483842a53610339108ae09b66570a6482f80840a5c4fad633036e1616802abc94748b59220ca062138ae049bb4c6baf3be907449bc49c2c09e35e320afacc11de844876026012360c2d465414c42410f0e7b92922c66414034c9b9f11ae546dd2795eb3a06c24ed21a84ea5657ebb2f428853f0a5e65624b75e98ebbef08893e077637c96ee40e5674332e97a46243c15fb4677859aaaa3579cf2ce8113ce0175e814f9881adad763de40ef88da74fe120bd7af55c17be5fdcf24472cbf78e09e72ef85b32f2e29f0b7cd6bbf8f8083b8d2d4dd9d84846640f1605c6064f71d321b4228796321e9de70ce7408c6a96660afa423f16ed6041a5c02b0bc2ab57afd8102fe858b226fca86151f86e821f58d4147e911873cb8c6db581dbc81d12dee8b66c9a9cdd8d8f2c2f9a03080cb9da968015b4b67544a72ce4a9d17b62975d1225393081e50978e900ac6d15ca546f489c9403f953131e5c256bf12c1a8d9ea77d537ead7cc0bfa0dda317fe18d81518e2a087488de5501133b9d45ddab75d00f1153721340ab90f1eded03f74c7469f5c30f0092fc3dff0b5e0412c4368d8162538950e3ac50b6ef863bf72641c5a0ca047876bda06d7b4a3e7c40769db5655cb05c73042b638263559c14671404a8f1c1ad00086cbe47532c891616a18d9d03388ba1ae82ebc081b20d2640c14b55be58dd212ed46df6c06617b3e1fbae395ec85ef4a67e6f4853cf2904096ac7574959a27c63cc935b10a7d97659bf129f333d3face05d9e898ae4b32f4a06862556adb2a4a9e2ea9a47591aa4cb357ffc30f3f547ca5efd103b77cfa96bdf7c1fb7acd07dc244a14a8b69a49248be44978264e27fa448e93b12268a200e75a4dff3e58194f80065994fae3271091845370a7be06b4606f0d124b935af083e660f19c81d1cf193e7291bec882d64202f8e14d57a9070bc3119a3c0504133e33b3de961d8aa9edb42d5c9b3ee303e2dca116424c826dab94a25bc62044121a170b207736b272caa6810af14f6377e65b850972a3c284afe065195ec555ba9240046231b66c0e50d2b6954e7c047ff0e0a1de78f30dfc71281face3876c16d970c2207485460ddd957ecafc34f405ecc49d6f5e8b1df8227433ca808f08d429aab17a6ee368ba2ad9566267b7c25fd8a00cc73ff0098f943377e9d103b01a5f6c29d85e55828526935a72384a2075b7028f4083535d9229532cac573d729dc89be04b137caca3cd096a293fa10f8e692426039db2099fb330d1bed25a5d280d85947ac85df2926bd9a8842ff24473b090095d27fdb801b6a83b29579a0b4f3aa0abdc575628338927181ac80baf6c2ec181ebe25dd8957266c317f684e7462c1e3c7cb4e6547c979828070c38a13325000a3e413a8d58491506abb214de155d4cb354b1095b2d41cf95137235fa66cc32bae70a34bac38b4ab68491144eec1749cb14221607764dc5b750289bf1c838e88e2c575a908283cf847d19cfaf9fbfe6a6ea25df7f5ebe7aa93b36ca904d04458fea46a6159fdfdddee98c6f929307ca1cd9b8e0356847a664d9966415b69a52ddaa2ed981076ba0636afa39039f34647443ab0c499afc88b18c04645f7958a51c96f5ecf9330de4a7ffbd58ae967e8a964d02995f717bc50692769138a64c0291208f4ca094e5f484658aa1552095d175d048097d002b90c00bc4493d034c70e131e095000efab60966b0c1e82c20e2ce7090a693bbf04359b4821bbe2619c0527433426d8b53b9ace4851f6a3037263c47b289041e81ae76c9eab20aa22a4b2192946f0af96620e02949dca2ed2a5567d391b2d14896cb121c731d6c50b570c023e96d93cc07656a32498277f00a43d83b518c61441e94ab4eb12b774a682a9456169dfc5f1f9f79fb6d26dc9dee989490eae0a96ab2782d59c813bcee58d02f2c86176cc8e275b39fb4f1f471caff8088ce797ac938aaa2f5449583e6d4557f24c2a31c2ba4111ee10b260f85b09f0279d507f19a940359e7f36b6233151ef1d1e0c967e964abb756774123c166f99d358a26be61bcdaaa32b286c22b3c9292f7510000100049444154014b2ad9c1290980ab657e321e9f29993085dce84c6f4e651102842c60281afd93ab823e7a5471475ca5f049c9ddedc07f073726023f276c55c80549462f98e9b4dfa0f7d4b4945858a51cb8e7aa33b2279de835616088ed527c91578907cebb232e6bcc527e4122f8772c9e6716db031d56ee9337a9a74142c0d4943ffe096c024c0d1c85e8e87e147d4c9134d02525b4df2996164fec099af141510409e872dae05c6b1a9af09df01a88199ac4190e728690a1d494a24195211dd8fffcc52b3de32efea38f5ee803be27e4bf91cd1f2c3c78e278f6d13336cd5b1571cc4d46939372f1e47a26a76f7577be85c7199953f163c4440b44a88c945544b724f5f272749ff4724e26c4eae343d15ee896022becb32ddb5826593922879a8ed1438c659dcb133ed0efc9339efb540dbbb0c87dfbbd6f2bb59509c104ec123e171154a253d3320198dd7279c1b85edb2065b1d8420370e5a112c449b21c223bb5488041c0b5946392046b0920fa79c533796f6ae8cfe8734b321e240843249b74648cb428183572046255a9b9132e900a38a902ee81cc413dc57cc626164e14ba3e465fe0002f16c30bb081deb1ad8550784c26c74ccd787865a11be0857630f9cd5857f13a6c97903f11904568d90edc2ed9565729efc12f2cfc8dbfc481296ac617be5b59ec0b5cc9288426d8173b5ac5c2f3544fdf7a83a7909794574cbab30e36a4f3dd457777af974d67fcf3eaf59d0ef48a8ef14377add7293b93363ac5bc333a64d20bd95d4d2c86b2a0ba4a36b225999ff861a2645912b14e9f16722f1af8653d9d5e268bec0374df646c389d7615f59adcf0b2ac4287f0dfa9e9c961d86b444894cdd5f429033f5b39a66c5af8371b2a0a00a49f2b551734b50935b077904e4393ce007fa03368e8098c7efc3401845d6f56ed9bb67d176c54e832c1b9c4678adb0f15888d5f160daf41c6fa0676017753a1777c38792a99c801552e71c371461732d796b1b337fc5a56849cd9280aa49b7d57fe3ed6a3278f649bc5f38e421c231bdd63e324b7568d708b830b4d195ea66d5b2ef4b05585606594622d9ea2560e40e163f842cabd88192ad986ceea559772d8199372c515ba96a1ccb5f872c063029c9105dfd2a6eb612a008c0b5fbc26f7526f2ebdf5e65b7accd3d6cb172fd66f08be789e0de6a315931dffdf7053b3f71e15f90e79567c7410fbc50aae8a93d168a25bc1cfb4b54aaec8a4ed69895aeb483b8dd8449b578190326ad9149c103e9215334c7ded8b78d7daccc6f2bdbe278f78e57fb661ff6b0872a7fbee7befe2662b0b80d1c012be4f1a3131d30a80c210230642876005a393e449f0c47a71916c93c85a01e32a725292af67c6c02fe892ac0167a15b09c5e5c29de2418053dff1ed20edc124be637226f081872eb22778b30b16a5ae966809fe83a49c14bb981c536e26024a4c34176a3449e6aaf454ddd096567fc66ea9dc2a703616877cc48edc062f72c5e12aeddcf52f7845ae14dc323c2d26cf85f19def2a43f39e4f68cd989880b1c5b6629ba0371b61369d131fd427e34f1e3ed6e3274ff4faf6e54af80b9b2ae8da58988401135bf2215d39e0692cd9b67d8d6fa75d05cf3bde3d7fbcc10cf0d316ba44673b14924b501e6b410c4e7af1116ed0c1a626e86ed75d2376d0aeeeb50135f5e40640f8b8ab606bb4d2778eccf7f0b691434cab4bc697137c40b09daaeedf2357b2c1955545093eb5e80b78e44c16df39d9d0907be08f157b749af4836a1b3d90535a476251c010a682df1d4f0707f96446e3bf117ee8233c10dc9d184ce2fe9a3cdb79a578a0370cc1989ab4b3e19dc94d635c295cc4d5b28b86558d3de4e3409f83224b37a71b559536789f2fb73af34deb4c5c06319e7c1080b3223ffd115ba09fd4a8041c0657e95aa0d8025353447bd532787020601358f0e82d72670cb880db5657610e50da91414b1f8f45c0c1cd466c3cb2b8d38ebf0fe6dd4457591c93c5ffc0863b6e249afed05b9f794b8f1e3fd4e3a78ff599cfbea5cf7deeb37afac61bbc927da8dcf0bc6433c9fab23157ae6553d85d88c31867dae415be8dce89be9601085b3a4a762d9d457fe09b01ee0487131b312e0d538b03b2f8da2af20a8e110496b15b8cb922c16b138b7c7d8f1ef569db9500bfe0eec12eb937d9bd54c0e7d49398122082f771e02c2b81526a4afab2d3255c624cd0d0bd0f5c268a2c6592c26d4d894c96014f25c824c520612f6b424b8167b25f488e33c9fc9a89ff8a77dea0e9e04e7cd038c00d8f247b84469fe8975cbaaca4bff2c92e3623035e936297724497b42663abc02435545a7cd12df50c4d976c92cf8c22fb92c50b974c84eda7d6040e82046eb5959f8a1cc6f35406c3f5d470bdcb9b82954a53031990e860511276e657782fb437eed6ba5b8f1f3f597f2cf1e5fa0fa8eec03f1031b5f860ff2d4f23af6faf7fa6baf07577298be0ceab9866c19af155f872572dec8c5c71784da449eb5a6cd3968a1a340d2ef1eb6011bcc0e380c71daf61848c5a325a5b2747422f591c1872edcdd52f70053fceb578dac1c28757544d644cecbfe24cc5872ed32de483879f677053c00b3e83d05903dba363fc973c0045a12de8657a21a43129b6556c5c26a7839f72243fd63f611d9af00e49f4fd18ff2e1bc8bea9978d52c60ff00e7c11fc195be16b5b698ba37ac37fe409d8c11be80f583b1b48368f0d7e3b1b7ce873077e26a7077c8213dd57a16317d7a4123e898f160e9a914b19296406277574b6d12e3454e3637cda9c9a707259f981e3ea0706005d19e45ce32e2d5e85ef979d8762ef85b80ffa68a2e81d559e3f7fc953f1adb6bdf5d9cfbe8d7dbb267207f686e6963cd978f27bf868676cd31d76debe7cc593c9ada25fe3a77ddf91670d7827b7029f304f19d4d155f0bc3e8d464973e18cad54755f5fa168b78c92ec408c9d00f017230abbf0b12cad72bd71f938b6fa1e3c92279faa5959a05ebe7ca9ea56bb64270894811ad758b0e8d306ce2993683301be42a55a6710282231e8db4af492985bb1c8c22701255509f042e33245de49a01a5e97d72419493589fa1d772849ae636d2c431716b35b2676be5708de0dcf9255d4d97c10a605e3837257d19d2cda17ca19195307fa16f04c041b69f0c3584577d16f6c379ad9684859afab800f14cc47db497db0b8c35873986a68a0ebb69d24792da8e4acc418648285b2318ffb49588d0f6863daa28b8d67ec19f0ad6a458f06471c03dd0ca0ab7935f0448f1f3dd2edeb578bcec83aa09bc816abeedd2d36a2d760516db78ce0d3b6e9743ac99626328377208710c8fc4c9428ea0261b1c980a60207c4089e0047d796eef83616dba34fc607b19910ee6c54590cdca546261cd807f1cb1cd4bc0632f269572d697093920b0e6f726b4627f8083bb47410c75c78033ad34a991934431ab2a31b6d784c5e211e6ce6174afc38e035780d8554f0245c416d6df8b5bb24e485efc027074f57b0508eefe8819e8f1e3e54fe20e2b6b720164e91441b1d2625e740065a30440b1d366f6a16c6aa868402457c6b4bd93c02df19bb2126db0d0b2e7ae6b7ebce77e4e6d2099b27a2a04b0c843f6c889552c86180b6c9099b2b86d99193768a64831b83a8c3834a402856619769550a7ae4e66615e0e15275c56988aa8150c7af237e250ea49604edf59c1af82ff6db57ae112b8e0b1bec4bbe8f1ce487d175db4a8f1ede608ef5ceb7dfd3cb172f75215705a39902e101f303ba039fd09588b1a6180d6fa990210048bdc2b05bf8676194198f550a8aec5a8d01a36b4ca325cc80da567a0bf13bb218f81e3ce3854fd5acf7de7f772569915cb9abce8e3d9924dc8730e786120213acfc9aa36d25e10cae8caa263096888e2681c9223aa148128b63928093c16681a152ea82cebe86130e9a0938b009fe20a1e8ea727b6622f3de98643c731773e609e44c429f593c6f9978799570dccbc9244d12868f48ae8657744cb9f21b4abdf0355123b2290702c19fe828684472ba4a2eaf225b25a933a98055fac5045b3c1840d14c889d8992c4dcb74dd556f040d1a3070fa8e06046d9149a4566f9674af1f50dafabf6d3861800f86eb0986cc8aa82077c76ee9c6f6e4ecaabacfd74d28b17cf799d75b736c3fcc5de0b77d2b7f14f26202ccc9d5f685060ddfd55b72ef82c137a227f2c1978015c714cf4c7245af842d7726171f8387e1768e3ebd413ddaa8a279c86f7ae8dcd63409f05c012fe1d6a17e3fb2ab155f4ab36418a1f86425fddca3f3e459c4c3b3a100272e0ba3904c765c50f1f3f3d4676f0322662f5b1bc03fbd79838706c701c66e952caa58257e8aa5aae62633b3450c8098b63b3d06d4a5d6a68666c7e754bb729850a23508577cac087e626810ab855f0481a89c3f0b7cd6bc75b255f47e4586aec34fcab766d94d3be2bba5d7835985729136603bb1072d5c543b0f93da5e07ed573e90056c61782bc7e08856cecc194b4a7ac9c8be7a4c9982876e096d129bce2aed4b6d4fbb674ddc8d37d63a393f015fee2b5e141fed0d5e3a78f94579177cc43d802225b1078306fb3b1bce24dc6871f7cc0cdd36b455e85177cf753aff9fcfcf90b72f26ed96f3b28e00935a7e283c819ac3d385e92c508d702bf657c18c81af3c40b0c7b4124ea89bf07068de54fc1efaae1c2c8051c1095f18338eb7bf4b866cba768dc7b04dc6e55515c12c5e2c75a414a82e5032feb1480a96310bc24bc2483a31c345cd6750302b0027c0de0f52a990996e0255160040062434302da56313eb38051323e91319075e18371eed6eed838eeb88349b2deb1a11cdc451e49164df4b02c2d1e2289e63d4fbb1479073c2fd05ec03f4876833b48d4ad4be667c638e44dc6063219be26b5a698ce6a26426d9baaad5ef5a69d8560802fe8410693167239c9f1a92c0e8d4ff4f1411b33b5b131c4beb5c8a0d74881c88c47f4beddc0b1c212dda7ba4a8f1f3f5a4f15b7b7bcca42d7d833a8efeeee98acb7b01b9af437267e47d7ded1ef24db8b07ecc1e15cbe19d834965665ec779a1379c0401cd18772e0af8942179e020fac4bfc0a5d5cdb153730e80f68002893ff60518f6ee9db56538a62f02ab432be711411b7b2aac053aa2131652ac7ba862f9dd0a1a5a24be0688afef36a172ac70f4b26b8827f622f64a59d129aea5253c6b2096bb06b920bc15de39212d7076cfad5d620ae896f6c437dd90643ab0efe2457847eaec01744d7c35a3fc0ab1a502177d300b7b9d1487cb6fd442c7799f16b5eb3e1a057648627a84a5ecf34e0907a154b369ea016472a9b2b275d256fc75ae8d127b454ba1ff3a20bad55b425a357cbb4fdb1afc00fbbeed6468e6713b18a30712b79afdfe9b4ebedb7dfd293c76f48e452e8237722ef74daf4e6db6feae1c327faf0838fa01b2a185617b0076cde173dffe823bd7af59ab1438e6c157c26661fe87f486cce93d82c9e12700a754e30155e530668f268060c8ee987c7547236f9208d60490bc5b4bd70415636bffc52c815f0bd778d9f3e55abde79f75d25d05edec6d1383d09211244b4e3f483362372f50a466af94a31454d027595ba8a5efac40e18b9a0f00e0f31920fca3bc9d96ae005fb29bb24c19d53b421835824c3509229ba4c16b43b9e445edf5df49245344f2077b90b02d929d0470f182e56e230305bc8b1520f265726f2e57ee1b04dd292680679112e0ee00b7c80515ed42c16a4a710a3811f0a18a42a6cc790256b0000100049444154cd644cbf58b45de07276551830094fcb8ec267a18d88e00ce483a6492d8eae4226be60f29c59ac5fb1510ee039f3caa7a1dfb6939e70f7776292e6f5c7da38b0ffc2c4ce5342ec9a421c97ee220ead8d0daed948beb321600728eb343644a7d4d1670e08b1ed60e11c6c449371a3f0199f9f91b370dde82935b656b79ab2fc00ee055b6c2bbad295688767ecb0ad5e9ba6a03743c68f7814a633055a51035d0670efa11c51c9364d6758d11395567b86468147c2840e3caa35083c75c2d1e8ea2a199f146dc42c3e79aaba905302b76c15e4a51cd6c00fd9fc37f08ff82244e045fe817f0eee5ea3777c3ee84796159fb329b825f84df43330dbdad8d4835bdd2a7469ea3d9b08f1c9d37ebe7d25461fcb1af05c228559f70dbb647ec2c7e489680bfee157c8ac6a55f7822e1fb180ce55bc60b605378a645b851e9695c3a3183266e0404e01b70d4eaf9b90134f9b9171d5f3a21b9e86439fdc8bdecf9ebdd0b7bff5cd45ff881b9d6ddff5e0e6c9ba815afaaa17af9b9b1b3d7af858cf3e7caefcfdb0e899f131a68e8312bbb9a913c764d0d4b6c5a98e7d2ef49cab3801f3420073123351a8c3039f7132082c71802c9de455e0cf9f3fd77befbfb7f40dfc7baddc9bfbe9989589f0fcc54732014a30ab59c8229a3889d0a4e72a8268d5aac531199112bfae026d7237206a29c9404f6b908998a0e595855d2a5b1b65aae42e15b06692064efa2e1eab1dc633bcae77252609b2880d9e440e3ed2e52efcd5ed6be595d685a7900bc92768166d5b8d2d7bf8aa6817897ce543c6486c229385f46011c8efad5f582006c45525a14f16880b8bf892a7a1fceb662529c1eb2a2d7f186dc9c4895edd2597651b34f059b005deb66fcb9ef0cb240ba53820910c0d0beac644cbd8c104ca9a30f0ea0e0c76e8c9898c4cda2c6681dd30911f3d7ec8e4dc75871ff26d227773d1f5f52d4f21e8191b71c1bac3cdc2d5c8c9223563370b666c37f2bb4a92d79dae906b60192b6ad45971bcbb3b1811febba8aa75cd8d82e6807462df94649d798dd6dd11ad697ca15e7edf905d5d6018bf51bb14deb10534851a13253a696b0d8017dd0054ea353615fe86feaa2b8392064f3b230e9d86d7d08059468267034b21d6b129feef2a75a14368a0cf39f08ba09ed07695766e6e9e3c7aac8f58646e1e3c14da2c9af090ac252ff8f73cba5a46af35233cb46db1dff8ec00f7225450b5171f439753a285a2ddad1331ddc9990b4f9297e3ccabafbb9547461f54077d50f0773ab47a31f4e2270e633becf0b894ff0b47e98033039fa504c5f16be00b8660f890da7295f2e39ab42d815314e193f8c38cefc4703bede8c913ed7662a11f94e8637dc0abef4140d71f8f1c9bbef9f5af216e6ac7a613af5e5ff1cde3f9b38f800df84b9115f65dcdf790e77af9eac5f2118914b0ec425f8a2cdb1af736dbd7bec5415b050eb6cc491f5b263a200436a60278456470aed85956858e3afae67bef577febcbc4e8a2efc503ef7c7a6665f2bfe69d2faec7c94c2ea232598826c123af96222660dda5cea2ecab7aeb0a3c081f0727ed9980524c1c89282da00785d34e28adadadd07495762651a754abb796c029e0b3bcda0777bf070b9f4d1fdd0eeeb8d7af42f2f1f815df495e31f15ef29137afb4b2f0cf0b1a80a71ca151adc554e87db0095ce095a1ec39a9236660ef2009278b42153aa075151b8059143335573dd7c4898cfca649fc131a7b5b496b59dbbeabd0dd303ed8d8c871353e5b7d784f6408bfb6adbbd77748917adbd4d89fbbddae8652dce13d905d2a16960276da7645d6609749fb8d37ded489b1572f5f2abae45f03dff1640681f65326faae3cb96cdba6f00daf0b7ecb66867798a493c973c8b6caa589a2078bf19c8c266ed497cbddc23bb3510dfa92958d6847d786c613fdd04de0df9c6e58730e2dfde1b55e63562974c983a28d28c60b7d5a0e9dc8b8c577a445918c7db696dc54d1ed5a822b193e7246682b6853638c1597d403dfa666049b40e02c5d7f9a38a44ce807f116b128da4687b18ae4c5bf1051dc21df8ad66adff2cac5366d874c07b18ddd131e9cc006f226e350044f622338abbaa30a6392e19d27475b8adcc6075dc4899cd928bd9347f7b624df2fb9c1b98fc9922558a167fc690093e2a26509a9c86e199e2bdee05a56ce7599e0e19be097c1cb4018a586875d2a8aab34e96389724cf419a193d5e4d2e974e255d4236d5bab5a40373d7bf6a18ca8cf7ef633baf024f3e2a3e7421a38d6e337dec037d2fbefe503fa2bbdf7c1876bd3b879b06bbf39e11724413c71e2c8a6b5e465e33d1458248081e19cd83e28e19d984ddaca119ac493f640d7c1c43eb8a1113be4048632b217d5d26582931baeaf7ded7775cb4d6850bed74a7d9a066537bee57bc2346249a0c2c113874bd6aa4828595a8b00e3b69544e3a2b241b30cbcbaa8ad26b36891de848fb3ca241310ea32b8147a8b5f575df1a1cd04aad076cba96d05afe91f24885d3a98545920b289dcf16ae5f5ab97ca934836913b9e1a0689285d65cc6915fc23139364fa49a3392777cc07bc0e12fee06e7a0a9010073eb2697497200036159a6d3fc9751d2b7886c780284f0e67f480836674c4eaa2d856a3b7385cc6fe4d452dc60091c8638d1fe81cd0854970c9a244274f241716fbe0a67db0e1857f267868ab9abbc11b7deef39fd5806e3d8570a797279203ba4cbce8da2e55957616a7eac60e6c125c97de2c78c89a4c32402a8bf1294675a0d301df493958c0ee5edfe20acbb672775e20571bfdaffcba0b39f45960405abc3232884541538e47b24900a52f0b3191453d283463e7f2293a6578e1a481aed143a9410da8ab57eb803f60250ee3188abce0da56f1230e9a4a131170e65a86b5912fc5af8b2e4c283349025655c9e02506f9a6934df8c017c65765ab16399e032660a103ac4507f7463f5326c00b0b1952814a8167b36f16df2cc676413d645be967136e6e14263a1ce4d279dd385db00bdb6012983806e398bee84cffe3131446a6e28f6b5bab9ff181a4d400248832ae757cdc32e042c7945d76e3f24981123b0725e8aed2868e377c27caeba8274f1eeb969bb7d72f5f6823c71e3d7eaa0fdeff40e7bb97328eeadaf876f754370f1eebc5b397ba7df58a27673ea0c347b25ebde4db0f76c68703a3ae0599c463907bd7bc38507bc418487e8fbe367d0a7c4237a149ee66e34d9b417c31649b52f090067e3d584312db77defdf67a8da5efc1a33e4d9bf21b5899e4f85945a20dae3c436bbdbab16492804a92697b05a24c1bdcf58151921d95a73e3e4881d5b4c1a71c2407956c4b248f190d8faea66b35c96793bc2c763b136ce3636357c96dd5b629e383846adaca91c9ce1dcb60c1bc6581bbe529e415af705e73d77cbb1674a90a7eaeb5e86de1b9c387ba80914a1ad06781384333d6ab83cbda5816eb59a0907c327883bb7516512cb7b474d9e075ca44da5bf90bbcf31e6f409c0fe3e59093f8a64162df31494edb26db327a1db94bbaf789acf534b1c3ab21dcda2cd817f8128d29f07bfdc327c31baeb40f6c2addecbb7ee80ffca05ebcf850cf5f3cd3871f7db816c5eb4283ded1d7864faf0d47b4ef59c88c49c502a1d50a8dc51d307a4de28aca5a9391cde4826f20858f29a5423f714cf48fff0e981e2c30862b1c55d8e06a997af159f4ade0c5e7976c880c4c78b82a57704bb6647eb48e895e14d3a164312e7043133e90abf8b9d2d415974d64a28bd0ff2057120ba8d739f25e1da272cbb6f2ea338b537e557c461f68cd7868baadeed6858526e3c9b9833c8b3d42177199f7b8037f0d16a579d5007f8ec53bfeb53baa68e7c927ba77353e9d1af84a1c3636c3c7e207dc3c6d9eb693b6dee126d9e849de5cb8b9bb24a78715fd26a34e611c4a157e061988384a052f532267605768504cb6c12de528535b016b2e7d46bc9621995677a9a33778073970606362bc64f7543691878f1ead0fe66fbffd59368cf3a2cd93716d377aefbd7717dff371b7fc78bad9f584a791474f9eeaeed5a12cde07fe7dfefc194f7aaf5144c84521e4449f99b99936f19ce4d92ab4352d5c26a163f4490c130b692afd83580666b845d720ef7be6fd06043288470abc5fb36e7cf18bbf09f07bef24ba9f9e51df7ae7dbf8799278222cd64160c9548d04ee3b012be5f5858045b324adddda484a51122c93c8096695d4c5057870c5244b1dd2048f113513b4db62ae908cadbc6e3911e80dba8d85f1e6f440273eb86d2cba059fae463f5f4b15224b49aa24cbcc04e3a3f3ab97aff4f2e56b9d99ecaf488e33777f1726c785575a9b5bd1dd6a7526874b22e932c1a2f381721792ea60b13ba811291045ae2d5956f0a5037ec1ef2ad86539b31c9ef47bdb6917f66c8aef6ac9b104b3d8810845a66d705a6268db9a0a5e785eeb200ef00ae7c122b815484c9c81bec65fae068bc99289c250ee04bffffbd844785f9f77cd2f78a575b06880a41bfcb7d7a6ee96e159948912b9fba2e21ee1006d6822072588fb59d91066e49103b1fd8c5f0f1690b48d3c582c7e1b3c678886b01eab9039f0cd95efd0410c263a4666fc8210ad3601dff33407bd1d9f4ea2408900b4896cb801b3a2b364ade35e278157814de08eee035f5f282802d564e347150dc6cbade2c7a0965840e88ba3f0b9e07121cec560ec18a195d455b2adea4d0f6f1eb2595ff4b1ff6eb941097ef4031d552672a6722cdb97cf26f8bbe456597acd2b9233372860022a36fe1beec2b3604ee448cb37e05ae10a0cf9275e13edcc81b255323a9c57acc6bc083768a0f781af93a798a91ccbb765e5a66fc99268430d0f2b35855af033356ee426c5f424bbb85016dcca1800d9a6b28c4e83581ecca7817311ad1c9179629ebef9f65b7af3adb7781279a1d72f3fd2d3a78f74773b998bcff5d1fb1fe9fd77dfd507efbdaf17cf3f525cff199e9c5fbf3894b70749bd6f7ff39bca0dd68c53a3cb84fb10f2b58e89c0f837b9b97cb0e68a155ba383d14f426f680ef49c7192a4c21f0f1fdce874baa13794b5672063e23fe1b8f4bff4d52fea354f50207c4f9d89e6a762d0245bde23b8260099740996922409420a5ad8527129331da96d4b04b9ab346957d157c28fdaf4076323fc68d06464804f2b27307a127855bd92b868af8d83499b85e9c442dc2c30a7fda407bc273d6d9b36609954e27021b72293a441ff4ca4bca7bfdcbdd62d13f6d9f3177a41529c59f8925090903c52f8e515cc7a95852d4a59da4dee920e5e675d983c13f38fd59ff7c938f1c35c492b5564c3d02ed95675abd1db928a3e95e462ac14924c3c4b8bef057ee37e324cfc60fc918536fe583248ecc0a3b765095c2c944c5bd265d973c1172741ae40315f6fbef926efa51fe8c30f9fe9fd67cf998c7742e0c277b5ec56f7495dbb6ca8101cbd26b61f303a040c5dd20fbf2377bac88e1e77dcf90eecef12fedb74b5d1610f3fcbb6b8c8e5eb180cd24e1c1dd902175ea5eb0fd84bb78f73491987461cb1ddf44529a7958262ba1ecb47e89c7a3df5a157466df02873222b30ee5e09135cae7432b2d1bffa8ad7f453e2c4e447f8a1940c3cf1291b1f973616f20baf15bb3775b50e3608ad033ef405de44d6c166198718fa2a04cd68951264fae86c4343d96f76dd814f4f550e82d68d1931297c641b786b27dfb77d478f56a177f45a39ce6674843fece3af55d296e0635581ef5eedc4a0684ff08430386600001000494441543951715040b624d72ab6c115b61d1ae4dfc09e21692c02ab63fbe96aff248e7925978d24734e1c55a51d3d9fbef9061bc75309ff9bc47febed37819ff4e4e913ddb0809fc0296409be938df0d193c7faf08367c8b63e7af642cf99b3179eb626f4b8418412543292fe088da6e28341be0ef46010c85c256cc511ddb259af7146dad2cdcd49d58d5e82fec865ad394b0ebcdfe775db6fffce57a0fede3a89eea763509cfdeefbefc9248271ba71aaa8872cfb9a380956b421f58049491adba0058b8a00cb04894930690f824c5325af62c319be33858149a22601c30f661258d5ad24fcc307275ecd6cdab762c1eaf5f8bf6d9baa5b0ff21b31e819ba07bc83edc0916bfb3a019898e7dbbb7587774bfd828fd4b7816552201b7514190d2f9764f40caf7164620d6551cdab8dcbd2efa23b9e46063883845d850d40f0c11a4dece4a214b79523579b516eabba4b8d1c21f40a9fc88ed0d55b9bd4ceedd8f2e0e23558f02f573b90734127a1e4816c6373c32fb266f44107044be18d3f37c63effb9cf28bf58f0fc83f7179f0b761346153cba4ad9a0f77d536f9b0c6cf91f5e135b538ef0043e028b7c5e099ef337b8f8805e5199450136f06b75b50a6026765545ad558624bb64a558a65d6d759764532451099d459f53051fb35a205606cfe9431f86410d5eb9a8ae25703a0a7ef33453d51af86860c705bfe72e35761f3cf50e6c4a9c32a61cc8a96e85a61cee441fdaf872524ff0afbc862273db7670c1f314009e042ef44b1b32cb42ee245e531359074fb903a53851117c79fd70513698f418209f3b15372b43c10ddce6eaa27f049d1a9ee8da2cdec5c2bbd5a6aad284208be4c81337b6c6de91dc062e8e481dd8d18b9fa5efd452fc9218c727e2809d1287292be94747e13fc9bb090f0708bd54dabca9e30bf409ffe8707cbc991abd96aead478f1feb6d3ea43f78f440b559aff8de11164fde78aa37b8c97983a794070f1fca38efadb79fe8339ffd2c7c37d4987ac537943b3690411c15d953ab8abc898d93d8a41ce4e60467d93ea6c092b0212e3893b3d16d2cdca9e4fb7e73c36869806071d8b42ff03e209dc4f44e3ffb0b3fa70f98378c7ecf9cf56959928f7a2f093431259043338e26513a13ad4bae5253775b3625fd12edd29af076e6ab88c6a2a5a10226d3a21e2b19c021c91c2075c6ad6b50676016325a1b9bc68504de79f2c85d4bb94882936ed82c6e4ebb7616bfc724e94ebba247a3dbdeaad48c1d8627f2268b5d3eeae54f7fbc7c75ab97af5eb3390c9265e87c3e64f03229f74c4e6c4d3ffa0bdad40793639084d9582e24ef206185deac134cc4a18309763081273a5c5642e337680765f9aa361563133d4ee83e8147c6800e76583e19b70e649484d8a1aed8bfcb4cd4d3cd03fa051c0ef03fb3606462573774cd0438be3356b20a3f3f79fc26777b6fe8bd773f6032be54b59549bfb14975e17f6417789163a72f040f0a3527e66870b7bd6c47666c3f8845743e9213de90b309c6604f8a978e6534809f6d55492411765dc0cb39b578805e16c7ba507362bb8d8db246daf8e82a2bb61d784ec025de527191405321c094e54f0003bd064f6582ff420066e284a99a18044b0958c64d6de73a659409af711dd080cf94e8e1ef343c95f16eec854e2addf034626407cb2e68861c1a848c55c38df65c720f095f187c83956f1807af7f682ed946fe254f12f8792235b2aa5a2913a4a69dcd4bb403eb7dd3467e5737d8d2851b9b89cee98cc84c52515bd2161c1ab6a176504423a7a27b7e3b6ed5d05f6357b28d6c51aefc27f138c8f94914261c22c3b69adc6ce6cc81ac0bf65c324f280cc9d532b21ff2b1fcc9d3a73c113fd47e3ae99637011f7df4919ef164fcc1071fa2fb851bc35dc607fbe9b45e6309fd71b972cc75e16aa1179a66c05efe4615cef819f8727a2e57bc6cd277bc62cc53f3e47b579facc74f9f6aef93a27f8ad0db82173512166172fb9d6f7f4bfff49fff13fdfb5ffa0565d33b880b12f49ff3519f96f22fd8f90f2661265d9c99920412419b387a10b2ea5292ad08fa417249a867345ca54065bc5bbec74b72e4b1dc8c734a2441f8ac023deb12776017020b84be91b17e0f5d26b936ae9309536c1c37ca22bfb3089ed834ba4a0fb8a37848696425516f6e4eda48eade3635fa097e675ebb4c0cba2379f39ef5ccddf86b92e2c01ec3a3502c13ad68dba5f01247922c74031e6b72500f94854c67e821d3eac3fb60b17506ca1a4cc6f08dbf427740972231065da19b38c237be608d93917b412f598a1e814d3ac617137a804c5fc6bac8e583859892494b99f034fc5210ad8962174d7ddff77f8e89fa01af049ef11a0b088b40e4b95a1b9b92a8a761a7d8bc419136053b0e16a563f02134b6f10aebc2b784d7dcd10dfa1359c257135b776261979c1fe442ade8db4b4fb4bf2a04efa90c43a6822eb69bdac8ae6a255e599c8495d5d640077174b514bc10038bbeb27222674a6931968530efc00dad4a8c8d6b6ca019bc22b947bba25729f8368cc4018e8009a4c89df43334e377169fe83558d41adeb1bf186c164f2ae5c956e86c6dca220b0b7de720cf05925d8aa4a62e1a43e8bddaa50cd8b177a8182c03538ea9d507c6b0327f329662976adbb436118c3dc8c9e4f8995c182ce013bda3cbd21b59050f21377da741b11d219ad0de87488015b065b822e3f7d0e112c5f6f09e8c776daa460716e4264617d68c5b9e4e97ff60127c8157ddccd91be53fd27af2e411af57df58653f359bf0aefcbb90fc96d607efbecfcdce7bca5343701ff0d462893c1f1a047dc91d00288bf7bc8e89dc1c830ebe9ec8c3744dfa89cbebe8430eef7be9fb3ef77dbad91fe00da2c53c30b8b0a23f88dba4063ee1cf79cbc6f38d6f7d5d3ff3fffeb7fa9f7ee21fe8e77efe67f43bbffb3b7af1e28506f90fca7f76e7c759f5892b1e275d938fc0e1acab6bad22299a64e82adaa8435f1c8127698807819312680327f232819cdc322e1e0bc8387512c00919d19e91c1043f563d6093cd620751c891b2b0db25f243a1d9db24e4c69d44ab7942c9247afaf8b11e71f7f290cd237f64f0111fca7616b68ebed46194c975e62eef72bed51d4f20b903cabf15b9b0f8266f26f66c4c88a6ce6fc9741556cc9530499a9189863d6726e9399314fd0789380d1a345138bc2a5a629735181832ba9fd8d0708c6c90a129460c3cba0b58e4e72e6743d7f8263e3c0e64c307311ad131fe49916553aa4523c3bc5abbac0d6dd09b946b3ca6e2831ffee11fd2b7bff56d26e6e5aa113c067cd71db0d004036cf85126f645566e20a24336dae4c2c16672becbe439e01e6da1437e3977a856d5b5887a8d160b3486444ed18e6f849c03fe937acc297f0cb7ae476069534b695caf137d53128380977dc442eb808f7d9f1b48317291615bc1b5c0861f27ee9f1a6c022936e3530a4e4123f44e1b2470f062fc830c031cb4270c4252e085ec8e1cd03a90004f8167f25c1cd13334077a0f74096d7cd0f82b7226b8037e677c7a90f7e1db5dbadcb15947df1525c9b6242b956d49969d52eada563bf9b39f781227bf067a0e6e40bef324407f221f96314b39a20b5e92d3b92f363df499e81bdc745dc437e3748a36c30ce1177066f85258b795b17ddb7562ee45977cb3b85c6e75a047f8d95e38cd3ccc2fc09c4e374afb119bc39b6fbda9b73ff3963ecf4dcee73fff39e5cf9d7ce6b36fe9fb7ee0f3fadce73fcf13cb03653ea01d2a0f8a24f8d981a00b1a1151e0535c881b1530311cb3f3a47366be235a3ff8033fa0379ebeadae2d48aac411bca0a74c621d27d989a7984b43afd944f2db9bdf7ee7dbfaa55ffaf7fac99ffce7fa7ffeb37fbc9e4ade79f71d0d7cb198fd6772b98fe827afed8b17cf13835526de35adb6707ac9d5ca13405127084d74ca06635e277107873e308936b519356a4f160e9a4a99734a2c9093924070f3ae24dc007e213079f779017821135ca582afbad52e4d7826616ed824d2ec9aea2a9db8a3eedeb9abb941c7939e3e7aa4470f6fd42ccac5c6b0ef3721e38ef1963b89e76c222f95247bc92bad2c08675e6565ee6ec8897e46d16d27e10c19ea8a63b2900ba4c1a29172467fc09c5e320bdce85434ac52a38fd0591c4571c6193bb201616bb955d836690b382848e58abcaa42d4a1c92233d9742e4c06db24ee4119d14277dcf50d1125f874e11b26b6d20f3d05e67aebeda7f098babb7dad9b7dd77e3a69df28d8369938054f242df157df5a13bf13066a297198e395a2c705bd05beba39635f69db9b316bc40688305103fa65133a047d035f1cddd0a067bb141f5856d1b72d1ab24b5efd740b3e033be18d4dd6f5b0bc1ab056d7a6b2e53266cfb5d1ae44440f00ca5f3b881cc817af10dae05e1bb92a7fc9b9815d6f50acabed53135f8ec43b2b6584c1b38cfed87200df58380bf9b1354fcb234216c7b93434fe08cf800ee237d3a7634657995c53d07d271e477c96022cb8906823778d4c1942e8d3c73d8a8d56490c6ce8713a3d20a63c418293d76377eb49f158366009f554b955c8b24ad72375c986397403fb2ee443cc08a8bac588844e02b8fc0261f0067930a2e014c303be8daebbcaa5d7afb941cbff52c9931b645048ae56c6cc782d9b8c4ed075e98657ba376c284f9f3cd553be8dbcf9d61b7af3336febc1cd43756db2af79001741052f4b69a173f88f3517272e59962a072ed46b7c708c5b7dfefb3ec393f81fe046745fb4867ce5ea145c048ba981ae3603f09c1425f6ccad3c894cec1cf8e6256f66bef9cd6fe8677ff6dfe9277ee21fea37bff01bfacfe9a84f43d938efc367cf947a391917575b5545305bd5c59880525c3270e377e5929a646be0e208480298680646c0388158055825023748c0a9499044288f2cc843c0b8d02ff81f24eb24a02049e8a2f0b2d702b46d9b443b7859a4aa86369e4a1edc9cd48c65b3bb61723de4ce270b5def9b0afc338fb677dc61e477ce9fbf7cae577c5c3f93306712c5deb4ef27e5cecac86a12be0a79e888b61ad8389868039dc63c2baf0eee58dc73b7c3900e7890cdca2b2bdbc8c33e2ccd2b074bdae05dbd4974f22433588c44d2c686016d77ab9a419c55dd50d2aec61b52f0c2df36e453b659342fc087023fd0ebc2dd9f9854cb676e0dec798b09f962fd03cb5b868ee5bb81b28d9c2ef83281b2c899580d7438786f9f0562d9c82b00a979057648d04c4aea86b0bb159d0a3d8abecb1a685a2e8de0d13ea34f7403115d27e8d7b2b168ca91cdc8a4e087891f38178ee0b446f1b3916f1b5f4af16d55d146c6bd9dd1c9c05cadc2e609eee2853f163ff470978440d86962a36d7a52f226ba367d81278ed026c668aa0e1dfa49c588645b1774a5d2466e1cb4c55100ba4a5df81ca1d3a1c61bc82afc91de159696b4f285b1c90615df5515fe39e0cf38f46b53c287b055c69abc8e9e9a06a7d57d2dd771696323499188017cef5840f39473e1696990bb73a20bb4e2404bb04a5c84b46b41e6046fe0f72a2f99135870902817f8c10c8c329131e1191f6c7dc28f99737bd075e19571e6c525f1819fad7b7d37c62d39bcb472646063f4c9662ff83228c4ac66748300f4a68f666b1c0cc3230394814dd12344a62f0edb8a7e6f3c7da81ffee11fd1697f08140c9695c813781647f0e09958d38b58015af520e6076bc22b6ebc2ef86582074883fa3937d93ffdd33fa55ffae55f5c37a3190bfdffba87e41e0000100049444154caf289925f3dfe898ac0c171cef38f70d2b51d2797ac953cb68ad205a468a796e46ae5285b2993a4a229384845654aa2918a7a304ecce98141120a99b4a8acc1c27510ac03788235938034063823300a88ca8474927009b26c93a014b736f439ed3b4f2227ee621ee8e18393f67da3ec6a26dd7e3a29ffb35c16b6dc61bc7ef152cf3e7aa667dc61dcb1d85d78b56049799db5d746ddaab20c0c55d09144468f83c5e9c253cb8261d385fec166173dab92f049ffa9827262f7c4064e16ef43b05b7c7736b9e0daa5094f3b521024faf08c9d8d0ed5addef695d825af7ab2708567b0073ecb4271dccbb943af41db089cda6436ac0b9b736029dd9d755855a21efa584ee22082131c7164731bb3747b1ecac634d06952aa0d2d3c8c9ee260b1b44a762b3ca6a63a3210509419682e189e31d932b050a736f40296629baa18b568ac62f8857cae8bd631d359b8060d8c1170b807092d188f6c50f0edd4603118f838be6248133b42615b42afea96d1d5b69c3eed19c485043f74408ac2f3ccd371c69af8a59ef00d86ed608339e13fc995fb82ac9978887170b882878db427d839edc2cfb78b263cc32f1b0e880a7e744b63e1878081e862a337b4a66ce8936259675e89ddddde7163912711c17740856f0c61fccdc20e40031b27f8867ecd3df2eac01e4ec6a0231fa4d688fee89bb842a249ce0d90d206ace8522e5563d7948ef3999ce17516f32272c27fe3a6c1e8bbfa2a19558e25ef904d2f63a2062176c60fa9c75a0768216ca0f7a4b68327e5bb57d10e0c3289b149ae9f4e9b7ef07ff3437afce44d9958228a6114a3614a4e3b2d0af149d34e5b4b0318c16ae0c78bce6c88031d7082e2af94d76c2cffee67feadfef54fff6b7df3fe8f46eaf7f1519f866e09e66b3e342baf4d103871a56d250055d63485006ac1a5a2265e72b56cc6e8d825c9aaa29ea92daf7666381ca714169331bb6443c7d02419898e9294838de4cc84cf4208ca5a74491f91c36af490a0999376af093248e64912dcb3d68457ee2c0bbd6e78fa78c86372be8b3ce435d74e3f137fbbb9d184c7b3171f29c990cde4199bc92d897fcb4632e0974da4983c6b62b855e8ca8a0bddd040c7c8cba41bc85f6300e2c3bc0e33b2070a4f9572043ed2c0f889fe30d164c391a6421bdce81bbccbfda40aef891e59fc1a7e5950263c02c37de83049ee43137b073483bb4db1e0a73dd1299b0ab7833aa1c2eddd2bc5a6ea96712a277ebd486c500e6f950c30262242b084af16ff83584c7cb564a32f37de8ace06b1ab54dd323f03990c039d8a0e862074f6355e5c655b159ad4d0c57ea9d445e9e8a085833819b8b9ce180b2fd11674e139e9cff897861dce7042693840154c2f4f33acef1cab53a878c582056da9d1c36525ce233ce01739b2f0038b5b10c14c4e1678c9add777afb5b3a957956e79a24d2de86c2b3fd1514b1e3c58d0c2c2b6b62e098c89af0c4f83635bc5e29f1c1cc0c5b80d97d84d0d8a1283e486380a1d6cd34a117a5b55a5ead6c6cdd206afeae229e7ac334f22074f9144444b9f28a2fbe3be9dbc38c8b3c83993ff91559141411599dabe925f7dd5683e199a00076ca961191c2ad95806b3339b586e3c26d89067085c486885a74c2c88617245d86a1b5a60f29ad7625cbafa0b92452b7c299b33450b36e051c0c41b08a0a01c7af2c623ca9bc4364f4586fc5a0ccfae927dd551d83dd09553e6e7de12193c57934352e6e3202f065c12d76bb9c2bffce52fe9a7fecdbf5a1fd90307e5f7e5599f86566716a0d7bcda914a5d563785d5a27166e3cc28615d0fdbe2e95b542a2e66dcdd4a00d29f0293e0887a52bb4a3be30a2ea5f87641f83949c04c1a70a6e8520e922434b913ca5d7eea5558245327e8705f13a4d0512856757d072c31027f9595270f2aed279e2498ecf96e72c32ba41b26d9cd69d7c3c70ff5f8f11365e2be7af142af5fbe54fee5fadddd45178cdbf6134f32377ac06bb0da1cce4bc14c36917113bd07c93bb8cbbab0c866c1b7bdf48afed772a82bf2371ea59b0569ac62fc214a5729091ce690e2bfa1e2dad5b25be5528e33afc9c665ea60d339b3c19defe501817ee8022c7a9c8f3bd9463d874cf9d5626dbbb2a99d83c344c826b8ef8d7f4e1ac81a32728c6d07fc0fe5a8420b62e23a3459802ee4065c175e74aa8a4d15560a2ee86ae25b5deaea0533ce2fda9e02167e13498899d28527bd03df99f18697c1c5108de8876fd39fe8165df23a072a6196229b61da53d566d81a7328ad6dcb539aa14a8f9a7c99dc390eec90bcf0c267832ebc5163d91b7e035d0213c700b062470ed054e22a1a7dafe3be35df94ce6af85437e3035fee6bd30c8f02afaa211957fe7819b69ac8486edbe84781a5ec8e4a3a6d1b3917df5b0dbd40890e83b970905fe26864559566165b956c2b7d1ad4bd4af3b4b9ed3b1b154f9e2ee27e87aeb74afc067e98e8722d589fc050c150c66b1b3adcdc9cd4d8376706863674c95dfed63b38c883a781091de29723f192143d0ad9d1d78e4d7044ef0b718e5c1024f44d6d3326c9fc44ce81acd486af4ba22256e030bfd2364063b7a292a5e8cb95b3c410602cc2364527fc25e6c6a3870ff5f0e113751034c1235fa0cdd36e64c943a6bfe28e9ea21d38154df069a0810a29c139838314648b715d8fa5f7d03bdffe361fd9ff99dee1837b785c077f7f5d63c727aed12019068e4a9904a392080420c9d1787b23814d20bb0a275ac93fdb4a2f932377531b631398caeae0d3e6946de528f8d15939609ba65790e794c81a1e7d2f0aaf9554e873b9b0c4516b12ce14da33c962c9f961923327684d7515628152b71b9855450dddcea4386d1b1bc2a6073c919c4ebb4e6c10693fe083bc50e0f5ab57bcd37cc126f2928f70b7ca9f458fdcf07ab83f5443ef823f9c078bc184e660911ae874c7027f61713f280d4e6ce92a2936765d6d84e6c4c7fc0a7cd91b93a7d26f97c2676312a66ffa497693e8eb17052c055ed5b2adfc2c3f0d782cd9632d64834d62e29f4cecf0cbff1819db10ad3377caf1a7f18700746d6a168672e3c2283455e836b1e7e0094b02469d05007409a9857c795f7e0e5d1363c469a253e40476e013db8afc94297e60655b0a0f8a9069e05ac7b17437b2c31f54728b2bfe1dc8ff186dd2ff98f7c21b926d954b056df4451272b9426ee37f2193f644494e5865ec90ed25a3ab684beb025ef415c6220a73aeb967e08b16a06d4966b3100772cdc29f5786d81c1d1c99c1671e8546a03b17b045dd5d12b5a67938bc68e2ebdac207f8015f164dc835b310826349858eb151a193b882017aa18b6dec4f916c534adddbdad06ec8efe4d3055dee6ecf6c66c823d2911d7e76cb8646460f29f3fbe6c1c3d51ef87d868e27923b9e625ebf7ea53bf2e715f5919b89d0a19ff0497c9b7cb12dc1cb2e3579214de63372ef73922ee7647d2770d056978c6d039b97df312bf4b85fb12d6d0864d8c69796b5621304e41240053ef0fdc1a63152e0951ba5da5bfbe9a4ea86ea6a9fc20cb9e16b15ddc0a70e98acbea5c8ca25ece55275ab0a3c003372e1410609624aae51fad0cb572ff46ff82ef2e1871f6882bb067f1f5dead3d02577b15908e340e334b755a620dd4edd0cb5b870c6db52029f9830bc1c6770859353028f7b0dd0c54090ae64aaf44507a42c7693893408d0c0f929179221bbfe9964cd0438589853a6e103d324cb562c7cd085fe8016521d2450122ac5b622e7c4442a7063cbc6d3c78945fcd1cd23e595d6cd69d7ba5b79f45085bdaf780a79ced3c80bcaddf9a25b8a380a7d33219bc9d9151d26f68e551816a2962f92bc6768264939a310ba82048a1997e287ae5e75888233b08111957ba11ef45d56640eec32f8c5625f88157cf11a8b018b206d50f1f6a6fcc5e425408c22b36ce69719a33684dc89af859005243205de016f58c032139a884177c0900a5a3018b49ac5f2b80e12abe89845a9bbe02da9acaa56c10f52163e7025f4bb0851daa2340c39756121614813be3b9b71192a7c34e13be7d4001ebc825be1e7f03538155b5220a652c173a2fb20d6078b9c6546267c4d6b326e095eb1374ad213cc25640825e3d3b4c3c7ae80149c2aab911dbe204b08b3ad2c30708764823be42a8985254faeb1a3bbd653ec44e644c6a01cf83bf58c3cfa137d3376e5a9750c708493e006bb52739373c7821d3aa3970c9ab9c42931045c209c5e43c19bf0974a55e4007ab569abe0b7294f142716d11d5d0f16d9f51482bfc26ea293d057704235e5f56844e5e631f5beedeb46eb8657befb9e1b06a89075c77795fc024a3695e4faf3e72ff5ecf933bd64de1c3c6deca7d69e0db12ca30b546c946c22c867b6c89212772124637435f919f0b60d3845b2724c7ccfe8485b604d0d72456bd4aafb5ac0a6c08547ecca1ad05bfcd1608493056b4956b8a4042f34cb87f10543b6e142032c2ffdadc2a7c67fb67589ef90811212f54c3c960fa5b0c8b7909ffbf99fe5c6f3957ebf1df5892a74cf3c419c63aabae48f2730c9e06ad54630f0edc1426e7b85026f5397e8d29c327473186f5286e263b94a41981f3b1f4f4fb0111330e3525056c35213b8a5c71c04ecac33897701f9c2eb1b585e93313cc0cb6697056f80d3851c4ec13b0c836b5690ee46fed0467d62e368b7f6bd555d6b823de28eeb66df9547f787b41f3c3ce97cfb4a2f5fbcba4e0ceeb82e248ba1dbfa468f6e1e2e5e1f4f8264cec14236d077ac84127a5f96de799f3c81af85b3746fe254d98a9f773633a377fa07777c17f85cb0c5b60e36d0d875203b937670479a3e5e867f16e9a975d03ce3f333df3ed49b04ad8959463b35be33136ca8d624879daaac13af2a4e0fb843abd23a788a117eb54bb1cde00cb3b86a5374c8d8817ec20f82a6baa936999fae5a2cbae941974e8197056c326e0b5c734778a3f0c9623590856a8ce28f6a7531e15d0acd208693f114817180983620c827a360c074db76f04b90c9c6a73ae4e81244ea6949159153f92d9fc5430e003e03bb8e55173a1b30d82a7c963ab8426efce58289e7c21fc473e2879bd3b69e56abcd66db2c1a2fd1a5e13d043ba458c14b9c0f16d66c42c105285f15d641ac55ade9a2481b4a0ce69710bae4a3c86093991a4215d906221532676ca4671b3862e93b7df2ba2892e5ded4f8e8447e87f7e5f51d3744b79ab1c1d02847fcaf60a3ffa6c1133f1df55eca26726203bae1dbe1c3878fd786b277ab042d3cc4d3f1b6a1ff900e6ef492a766f4364f2cf7afc2457fa2db990fd1f9db7486aed0af6c9888a310b7297dccd604229584bf57fc80245f92de0055c657f013c78432b883fec041a67fe0bf14d1ae221ec8b13352b21d30a524193fcc3597108538c6242d142ed79e88f940a6d55daa327118d168159107b10df1f01ae05ef4852ffd867efdd77f4dbfdf8e58fc89eb7475c6540269598dc3b6da55dd4a9066825f85b3c501068ea6bb122181edda94606f00dbe0313e99240e3697ea5631564a1d20c9008e5c1a089d44622d160c4d16cc4cbe6c2077dcb99e917de6ee2ce329c40eb9464b93044317165c934465ab34157d421fbe5249c013fab5680f69e70ee5b49fc0b4f6fd463794470f792ac94479f444774c88177c54cf3791e72f5feb8e0d30866fd03de4e96587d6b2722cbd93b8d870a10476607748261d442ffdc48c38df4fd0500e8c68b7aa282e9db65d1564880ceff0edde25e08345247d69c9e93edf0000100049444154aaabc4303ebbc866d14496d98094821e35acee926b53efbb46093ceb824283f12191f046566beb52171eb335f15f644c7c9d782fbc71079c16e3550d9f497118a82a3ca0256e852cc8903125c10b9433b20e740b243c930be2b0ada274453178629b432378a1c38ecee907c7b61046e1ca4c2f6806761899035c4177902b0339a63d681f8c095f672c3a4d4d412a1bbd180bdd22455e681866acb0077d241919817799ced440de806f3edb758167c36fb2604cfc47ccc02b6f5a8baf4af19d38aa5b065fe862045ef8b6062b462434115ae9e09bd5c04f855ea79b1b5e13dd2d7aba12586578b8540d458094ab1ca85df0b7045eaea927e3b1b5186b8a434f6cf22774920b77af6fd7d3d24091d018dd44816c6d8258abc503baa1a9628348ae6f6c2427be056633099fc6b6c8d8dacc9f56e645fc55964ee0eebd61db991bbe3bbd7cf592f2e2fe1b8f147b2fccaf83795d2ddd80dfe478c10b8b163fb34608bd2c5ad811b83846026a2bd36cd2a7098694dc4dae25b607b62d1b3e1e370d4e83e9c5772cfce04ce6e0b2b924c1acb0dbb62a32a9bb19a0ce98804d211b67114ea5cd055e0305e62af1ebcfffe2cf2a1fd781e8f7cb81159fbc2a93e0a4b84a85e3aa1a9ff9f708b6ba4a499238bd7cdf2f70a221559b0635330c5c1ae0c491866e395b1ccc44ab0885d6d58b06ac7bf993c56070e73588d2201906fd0b7771d9402e245dcab1023f49ca41324d9275c01e1e7024be9aa1d78aa94600b4bb4a4256efad6eec2891bcfb7dbb567d62f17afaf891de7ce30de543ff8b171fe939afb35ef0887e7777162a026fe86e944952f0b4a4abae17ea0b0bcb50f4cf667a417fc9eba7ca0a83037b66b4bdd76ba6369b3477d055ad69cbe8964911be99e4a17180b624ebc246644ddc7c81e51df6b39900dff69376eccba4cfa4dc98c8b675ba3929fe1b1830d86cd31672c36de3aebbbbc28dc93d349163784d743f783299d08863c9cb62ef494f2197c1ad2ad943d5509921e4c19ac6546133a3323f2ba79ade8496d8660c24ce02af04b96c63cb81ffa491554220070338a2014ae165835fe80c4e868a7646827d1dd7fd3165a3f934fd142d8e567ea471559431cee0a1d760b388ec094860e72372eab03858ec07fe30f2b66e9df1cf46ce1cc0c68a9f55c412f652f8412feb7ad037fd74c31b128d3114dd0dafc48e61fc4a0c685cfd3ee953b033aadaf8af0b75e0120661b41cf31d11f4a61045b10a3dab5b451e9c58a893b377b7afc99f3bc58e01d303de23f385f6a45cc84f40ba10ff8f3e7cae77de7d4fefbcf3aebef9ed77f5b56f7c4bbff33bdfd037bef96d7df8d1339e68927745ce311ff6d392d9c87bc82be1877cc87ec0a6980d6207d62855852e148c8a85d8a14533b025f33afebcf00af8400f4e4d14093ce378019d2739aa7558f1a6aef36de93c8008bd0ff0b4fc103e238c228d7a2267522b7d781fcc85303397423f4ed925a5d1a5ea56c5872e357aa7847c423f162f6205203c5302cbabcd9f631379f6ec23fd7e39ead350249346f2bda3a89b092ac9d451a0cb2ba05918cb163ea53413a654d00101b994e0a41d87ca5e6333ce162338db8b50725940245b55d0518ba0063698c849ec6c1483497b4d2aad0de396a0e7fb449263c22f78af991407afb99270d23d5fc62672c5cc9f696baea12ef4a5e429a49958dbd6da29a77d433c4968e9e169d7d3276f00dff4eae5f3f5ba62fd9f224caa09ff073cd6dff0f13df486575447048b1f0b3aa97bbdfbbee858775ae7551bf9dd858c63d99b64bb605b742b7c61f44cad7558ae966c757bdde1056f203f7c26765d78228b6f8edcc5d2172e0ceee4a9658b3da756efad9b16f27adddd9ef9e06b5baebed7431ae838f0bb1b7ce85053830959591065f0a68471160774a5a68b9fd01908e84c27e235285505ee003ca1a309fd7428adc9389ce42e19d00c4f6a17e87061988655c8b053177891566104063cc007a81cf1df94d74fea111bac45cf55b007cdd74235e3a329f029f407089c1ac0270d33e265d3908d2cf8691d56b9903faf3cc11ddcc0f4b6e9cceb9a7ddf567c5ebe7c05af63e139f8d01b79ca01bf540c4ab42d6cb2a03f2b7a4f8cdf4f2715fe3b78929ce83490b3f0b9a469a113b935903d014cfa82d79c063a25e326f8d0925de29481d996b1abb75d372cea76e9d5ab576c7e67380f2152edd2445f711cdc9c7ce5abbfcd46f135bde655d4ce93c7c3c76fe8f1d3b7f4e4adcfead11b6f320fad77dfff50bff3db5fd76f7ee18bfad55ffd35fdc66ffea67ee777bfa677df7d9f9bae7c07b00a1fedcca59d4d56d1928d6adbcc0dd8aecc3f5b8af65dd2d6a6905be099dc33ce6b7272d0cfbf2bc95f8eb8700339181bf099f88844e5266ab2894cb0c49b8263fd028cf003ac85813995b5628031753d06b4734e4d6a572d9c8ccdb270a7445d2865fc526e09450b3c35fa518f450bfbd4f01d4b9f6b9f2b1beeb7f42bbff2cb3a9847fa7d70d4a7a1c30563532acec36183204c1c5478b40b27a24477c94adb2a7e9400806b1c2d1c99a04b966d46a92502425dbd601ff399f0847d2864701224d32af00ada793f49064863983c991ac0ee58ec12940bedf3dd017c901ca446f034d78f6de2dcab8836a7aa4ac7181278491459e05a13fdcb45b724aef9b07e22d91b3d9a6479faf8891ef021f17277ab174cbae77c1b89fcbcef3df5492726fdc626141e73c21961078b7c16e0737425b12ec875c11f39ed6692ecb22d2d3f967a6b4d3692015d3ea2366313bd6450a01f99348b77784c26fe05e5a78af1c83998f01263b52976ee5ddab0c1c8dc2b3e813fb2f2af6a319fc97fc8b62a3fe0f4b629ffc1965d02acc102268eea932eb3d71d5d26a0dcaa94fc0f74fb496dab91d52862f8588676c8f0c1b96b8110113ed8f027fa0f62161b6794b0985c03b4299ab2ad0a1f168d098dd78894b1111f482a704429d78a1bec804ec95afdd4e596abe0cde2880f1d20bcc631c1812be852e84336c14ded2b163a46379b7e51a81b59c99b83f8983c143c07822da9908369b24b5d25531f8c214ed123e3cb42648746d815d8241f8237e1359159d532f46d6bdf6f3488a7c5cf4408a7ef8bc09de860e60e2015f20c1e2be86a87afe0b1019783c16588a6196fe5d8b75dc971d2826f7db72b5e11e3326df17aeb35b89b7ef1177f43fffc277f46ffe81fff4bfd3ffec77facbff7f7ffa1feef7fef1fe87ffc1ffeb1fec5bff869fdeccfff07fd875ffb8abef85b5fd7dd2cbdf9d9cfebc91b9f95b71b3d7b75a777defd40bffbb56fea2b5ffa6dfdc75ffb4dda5fe335d62db9441c0e4b6e9d983ba7fda42a534a5da5bc0acb6693723aedca4ddaa3fcd6e4be834f61b3bed9774c9e4bff97bc1d38b36eddf12478e63bcb6b36f47c83c16ccd7531b822570bbb1a39566e80278383581dcc2dd90b477104c5367805b8b4b106d88cbb248a6d95e0412c14026a4ee518f0141d2c643e0ffdd66f7f59f9df5d33f6dd2ef569287010884c968993f0918aba08ea20f1e3e138a671e81a032e8aab059a8c82c575a6d14c50eab49bf1d0322c158eafb8ddea76b0092c350cd6c6e348208212ac5b456d4ae86d68597826932f814a398f8bee58ec2e24c2795e74cb223f182f08ab36f84b055dca24d85d25177c684f785d35a16f2d78d95ae3a8e0026609059547f1138fe2b9dbcc63695e699d91c3ba4082eddf799555f08ffc0b49793038d8102e94bbf4d1f112ff7ac816c56b42f0f688898b879119fa2e7c477b928807b4d149dc71393ab390421a627a43390678a977fc99c9576c06796d90f6d62df13432e15934a3c7dddd6b16d70b793ed45bc367a28b9439bd78c1afa1bb50dff2b4121f81bcfca82ea95a453142b308a65e0ce8bb44d38b67e8263e3249107b97ff59bc624fd7425477c9fc44ee60f24d7c248ec04243135ee104cf8c270fa9930f02e16379c12b7876c1cf13824355863f46438329bad24cb918d681495391b9d0d1734ab0b4bae2ffc9b82559acd70aef940b3936913f617810cb709af4c3dbc8538ec48cf6046762fb647c2ab1e23a27373117c9c69f2822118bb9be134cc600b2816cdc209c7566c3122a4c3c10dd3e2e91153c86588ccf94236a28703b50baab32e4c85830031c8a0d13e8c6c2fcf0d1231dbc92bdbd7da5c4090632b6db56e1b6379e3ed547cf5fea9d773ed0b7dff9501f7cf05cf98dab8f9ebfd0fbb4df7dff99bef9cd77f5a52ffdae7eea5ffd9cfed1fff493fa27ffeca7f46f7ee617f4cbbffa1bfaf52ffd96befcbbdfd44b72e8d0aeaffeceb7f58bbffc6bfa8fbff11bfae297bea82f7ef18bfad217bfa4af7ef5b7f4b5af7f0319efe8bd0f3e54fec8e96bbed3647adae82da9c9e9e473369502869a7af4f8a19ebcf9586fe43faaba3929df2ad7afe13f7bc993f659f107ee5fde9bf1bf7101bdf09d6c7820688cb9e2b17cbff8966c10c19b94b4a452952919a357bd72c4821f793388db4c8ca94d0d94732071e0b30ff0cf1788313102ffbb79d6a721fcccee9d456f390c0fcd8ef36a392c4e2a9c9bbacbcbd174857f69d7d5c18107b09c3f6959d9edcb2577e1d829f35334638f81cbc2d909976832402088ab72d8d6da58804d16cf414dcc48f8a983bbb40b932cfa6a4ad9445ea33f4d1d4ce20bdf4c26f485dce830a10d4f816c1a0c691270a373a43778a2dddd4ab2066e1b1005f8ce629bbff63be0fbeae50b36ab3b9621a9aad9086ed647f82cd8c604c816ef2cb0135d2616def25e77b0e84cfad52d99333a5117041b775682b6ba74a09772b0bad956e1b0035a5af09d90d282f61267d09bd8b4a15f534e3c2d814eccace8bca1dfd6e0a3ed057fe583e685dbe683c57a229b5336e310d95efc270130fccd07fa39ce70c76bf4f32dc06c80137b6c2bf60e462f6c90f165741ce8348185f7206617e42060f1cdb82290f1aee22a093eb6b1b169368270023cc2277e918cb4a91c76c914294ca67ab5a503fc0512e4e81e5a33665b464eda4b276c18e833c099b441ffffa24d7e45eff87b706372a0ff0c6f340d7d954342419fc4262d9ab16b2d6e8c7f1cbbf01f682e59a10b7d6076691db66cd39c4acc0d6dbac98303fd6ce3333810f7f08fba80c0cf39afb4214f57e0a2a36dd9b4419ee4d02456465c4a75ab29a6636fdab88bbfe175565e0d9fb9f13ac88d624cd6baebffb13ffea3dc18b5e28fe85e5d8a2f91ac19bbd0510699734077771e7afeecb5bef9f50ff4952f7f5dbff1eb5fd52fffd217f4d33ffd8bfa999ffb15368edf66e3f886bef0c5dfd1d7bef5ae6e99bfda4ef27ea3593b1ba1f5faf545f96b101ff06aec1b6c2a5ffbddafb3b950bef60d7df39d77f461fecc12f60ee44e6a2377df363de4e6eec993c7baf00afbe5f3e71accd1897e0773edc07fb843640645c23baba43f88edc04f0032b08a6dc54edbca916b55ab9a520568cab4ed660392423f113028a9095a4eca448f435ff9ea57785df812baefee19cd3f710df2be7322250b431c645fc596acaa4ddd9b8c330f1c5f783641c283b2e928a191f09c5a2583177cb94938fa131c4e30e883068f50d88c012f5b5e01028360d8e080d93426f5043f01cac29702d6da282609b0ca90064973c75dd541bd2739630c65c20f168a6e91a325c7dab823beb0a0b4d101861bc948a5aed2569bb6aed56eecde4fd8cfe3c2633e0e1ebc9afae0c3f779d77a07cba924f1e347bcea7af0109acfd091dc00001000494441545d85ee62926501ba642162f33858882f4cd22cb6035b84420775ec8f7ea9ef6ecf24dd58f486c91ca67fa8ba814de5890b1285af3d615148911c8fd7ae072c0afbfe408d5d850d81e7f5da696bddf0cde6b46dba7bfd1a9e97a577f458be75d137fe3ca881c0dac040a43fd455b238e650fcb2c35f40a277bbb5e1ebf40b1d2cc9c47a1203315645fcf1bfe1914daee0ebb2cef8c441860f149ac4410178caf8b9f0f975f2135849913597b553c11df0cf0279812e3c854c851f3c1a7f6dd83abeb3801c70c86968a736c66dcb2e6d658913aeea8e1f86962c741647f45efc855eaa40181724f00267c2e7969c8bbe5d2da197f0134ea0822b3847820685680b3ed722aaf090ee78f532c1c114802803ced576cb552a0a988c4d0df0c22672cb5e3abb189d160c39a3a7558c05b26adab0548ef0ea0acfd6cecdc68958e6a32f562b68ae4d70537e81e4c943ebcd87ad473d74aa433be5c44dc5c693d846dd12fe2b85ae0dcf55a60a41b6551442a0bbdbcbda1c5edc1efa9dafbdaf5ff8c5dfd43ffdc97fab9ff8a7ff4afff2a77e463ff7f3bfc213cad7f482fcefd3433d7af333fafe1ffc21fdc00ffdb03ef7f91fd4d3b73f27f9a42ff33aece77ff117f4ebbffe1bfad63779bae1f5d5c0d793f2f0c1aec73c95e406293eea0d3bcacb1f627ce2b4421f715ce81f6c36239bcb8a1740c66dcb65d5f24fa9896735306b1dae66acb1b7c02b852764cbb5d7f60416915349d5e8f1ce3bdfd297befca545ffddbcd4272d3c0e78f6e2991210fbeab19548346b39951edea2a94c4ebca8de0a87d65ad006776bf16407019a7872e045dc2ca3fd086d055fcab0cc95c2a91c2bffc3405681b79a5c067d51a6a00599780ab0b28974951224c428c00b771e772ceeb6d738644ab1afb480a316652847eebe8ab18c2b83009bc5368b416187bb9864bb40612d2539246dfb49370f1f6932db9f7ff41113e3350bc02de54e3b8bde8945dcd4a4900eeef4cfbc9bbda51c6c1e5948526e593092b7b95b1d1813ce13ff35b2d346ccb26bdb6bf9782edf75c00a0d5d4d1c760482a03eed7216715c5a254cb1aaae1328b4766bb7f500bddfe7eeeec2ac9ec247e86497267d59ea6e95257331932cbe19bc8a9b511604572f57c72edbaaa6cf5dee807eb2b01df8bfbad1715ec780093e76726748d86a1b4ea53d135ca5ae520ebb84589a7804d4a5775b05b091db2e58e19df0b45475a56bc6aad39e2b460379a13da3cb449ed661091b102d57e960531f14317e218ec12fe0039c2276c79c8bbf959f89cde9b700c36620e7a2238b0fb2b632df0dee749d13056fc6412ce0d9e4436d633f7a4fe44d6ac137b207b25317e3dd2d6fd2b66f2a97c6d24ff78735e0b9f403971338dc26d4c0277a88121c062265e13374d507392e6c81303ab94ab6b5916f276e2c22fbf6e52be50627bcecd2673ff7b6fedbfff6ffa8ffeefffa7fd2ffe5bffb3fd3fe3bfa3ffcddbfa6bff537ff92feebbffae7f5e37ffe4fe94ffd893fa43ffec77e487fe487be4fdfffd9277afbe9496f3d7ec037c3d2a9ad2637c4932f5aaae86fb2e2e7da765a9b6ecf93d7562ff45bbff52dfddccffe9afec94ffc4bfdfdfffe1fe9effffd7fa0ffdbdffb87faefff879fd03fff7ffd6b369cffa02f7ee5b775c70dd5e3a77cc47ffca67858d187cf5fad576cdffad63b7c9f7ca1894d83b2c1bfc9af6318e93809986d5d0faba635714e6e04077e33da74b50a9cb46da37b939b1bb0925c3245f707f7388002b3047b582c7ea48f06f19d340e7c1edee97ff9cb5f5cf9724ffe5da9ea93961a877ec88228f772067e916c59cb47d496ab9404b001524243eb3a46bf4812e95e554bc1abb208d7a203c2c9789512609b314ab990c9106d9ac484c96171804ba027116208b864683394200ec60068b0f8667022e996d758070b6326c3806e1e03032684662170d05602d95675e97a372a893e17d5aa878a04cc2297fe69df784db5ad456f9018a17bc0c4cb6f863ce3b1fa963bd088a9de993c0f983cbb72a7de6525810e92ea00e19cdf964283493fa564f04a5dbd748ce82cc6ca416790889372599b2240e8a4500d1dbcc70dcf65236db318d8966dc59f716857a9db2c14db2a4f1f3fd62def97cfd9c0e0d5912b69db77a55db473c202fe93523a90cf7c53153192c1b3aac10c520aee6decec6e1538f1bdf0fb6081052c0ce39cb921c71793184c16aa032f087cc9b60ce244c8548eca051a1803880f6c0b1315bc14091acfa51b88527404d7f6b27da60d41f4b1c36f2afa41a59941c516c888a5180fce14b4e80102a3d252902af2031bf8cb2c885ef8e8036d6285c9577b320efd846662bfa96dcb70a3fb313b01507885a7e12570f22fba07399c5807b1aa97fc09e15c72f0178c2bc2c410360ce4d164611ab0324d0a3883b199316a80b0b7f233978fa6601906c04a859c3d37443c894ce2f5faf54bc6073743b79a6cc0a79b5d8f9f3ed193a78ff4f9cf7d567ff087ff807ef48ffc88fed81fffa3fa937feac7f417ffc29fa5fc39fdd5fffa2feaaffff51fd7dffc5b7f597fe5affd05607f5a7ffa4ffda8fee88ffe01fdd00fbcadb79f3cd4834d6a9fd5bae091215ca576515a2677504aa8a8f365f2cae7a2676c0edffef647fae2177e5bbff8ef7f4d3fff0bbf4af935fdbb7ff7eff56f7efae7f988ff2bfa79e0bffcab5fd0cffec2afe90b5ffe6de52f6ac797611e171cd814374cac9d420297ab4f07b973d11d3778cb5f96aa5bf147956597bc0a444edf34aee72440a4aaaa4a02bce81136ef4bfa293382c10545dff8c6efeaeb5fff9abe9b07da7eb2e263f0739e40ca25c78988b32d936456a9ba9984a5e0d925c952e0b642d3e0310874ac48559b45838849ba3a9b36b80623fdebc2041f6007596d5b3052b370e70ea2ba15502ec14f9b690b4f18721a1d232f593765a51fddce2cb6790ac9045b31cc984ab6d45c0ced4a82899ab4158019a79dbbc974abafb20bfcab6c2b1b8d8247194ceaead683072726db990f78b7bc5e1a24e5c447fbfad328fbfda24c0e31192fca47d13bdefb5ed8dc5c6b615ea2c5b5ba55d5b44c2d45ee643665111ef80655176f8191bf8b95f133e3133d2c6963316d96e6ae6df9a7006e6df8940a591b259be1a387dc1d52f291f2c039199b497ce18b0889b21458cb2e641e0ac301eee55e56682a729031d814070b9f1138a1b38dfdada2b6ac1c855d691a1dcabd04c597c560f99a4f133e0269d10133360b1e2b0fca34510e1d2ca1136d99b05ba11f4bd9a9020fe6ab2e6827fe708143d1ea0389aeb407362b4758510ad8843f4d526a6a2307aba2e1c42a416ee0d241fc222378073c26fc429758e7efa6eddc40d8a53b6e62666c00712ebe63f1a18bfe4362052afcd25dd8607833c219da86c7c6a29e3c117a097eb65733a646eea03196fca942cfc8587dc13a72655a9cc8c6519af463c91a024c57aee0506c6de4ea0ddf438e7cf066fe5cf8a695d8263772b311fecb56384de84dbe15f4d71bab5d4ff9fef099cfbcadcf7dee73fafeefffac7ee40ffd90fecbfff28febc7ffe29fd5dffc1b7f49ffcddffecbfa5b7ffdcfaff2d77efccfe88fffe80fe9b36f3ed4c3cd2b770bc5ec924dbfa829892da2f04d498c95c89521bde235d73bef3de3a9e59bfa4d36975ff9d52fea777ef75bfac2177e575ffbda3775c1f7b9fbcf4dd7fa3577f236b65bc6399cf088af8cfe224752394394c06cab52d0c194ea965d0a0c3035edb26ce2260aba2fdf1093495b9489dfe323319ec8e775ed6ffce67f54e0fa2e1d78f193951ca77ff4d133849885b6d43cca27885d69231ea70c9ce46adc4e48e8dbd6209195c4a22f1c2660d52d5c2c565d8916c30251c518730788a5007176aa760ba0b42e0694344dcf402cc432f1221398a52c6a0986cb0a69dab7248ee077b0189d99088c20622e5d173e4ca262e4776c83b6abe02f155883c1485d35772ecc1135b61a8c06bf5954f6d8d556a7b625c66ef808f89c8feaf1ddebdb5b45fee974527e5532757041d360525eb8f3bfe383e585bb3b94138c34d0eb4251f8c1bb6a537428f4cbe210bcf22680aa12fc0fa12aa2e92c1cebc14d6ba71d3f5c9830e1393074e2c99c72a9bab4c1ffcd379fb2e1bd64913b73e77c5ec3709269b95abdedb42db1387660710a022bc2010fe23df07181958d208bede57c46afa14cdae89e3289c5c4aff97b6676317e20f356364ce02bf4d5e269997a6331091d3d4d873b5161b315f2118fcd93a6d5f8c7925250925c457310c2a3aa175e266dc1930ee795c158bcc0457f9b1a6eac255cc35712303a0a9f40260930e5f5330710ec313a4f035b051dd105d1da90f5114feffb69473ff48e0c28bbf03b6540afd04fc4a874f00d6110a7e4a55c0c0dd9191307df998853ee9e2b4019fc49f88fe55f1b448416388ac2948096bd501b7e89756aba4420d7291b3a70c55314e49a5c067a56595b9fb4fe95f983075adf43886fc3bfa0e15c3e3e78959957be83b9915798b901b923a7a3c311fb609f7c7f907f1bb5354febadd3cd89a797a7fadcf7f1f4f2877e583ff6637f447fe27ffb63fa4b3ffee7f477fff65f5dafc2feec9ffe23fad13ffc7d3ca9bca91f78fbb1de7efa508f6fa06f2177a8d0b7d0350bb2a90b3f6ee8a68ae42959f8477afee2565ffdcaeff25df2b52ee818ddcee42524ca91dcba9003cbeff8e1c0c6e431e412465e6db55c450c376a2fb865e59854762ec40b982981e3482af480a780a595e6842032091cdda177de7b67bdeed677e9c05d9face4331f359fbf7c291bd794492a4ab58c58db222f952026f19a4e3b6353ddd4654d5b49e294a9b8d144981a7882e2e00107bae05db5f0e3e4d0d093f981827189f9cbc51409f6e07ad51937b4b0553954a5b628866e423759a85ef39196c571d2d7f5980cebde8bb121d04c2050560e6438f0c9c060da0d123549267432a5ddca62f9f0f44037fb8d0a056cabaa75c344b9dcbdd20bfc978decf5abdb053ff15d22ff862438e17de4ee8e243eb3d965d19d2431ac79a536e037b5121ac36dab1abbba192ee4b6046c3251edd265980579606bc016201aa54297f2a62e2680adc24f29899b25e0ad878f1ef08ae0950e36b3b91690c65a6897ac43330b1b4e69fa6213891fec92f929b7baadde5a282cddcb885ff284b3b1d1126245e675ac694b073ced827613ac41b1720c16a5e8602019b0ac311237fcd125c15f39202a6cc9f87a4a046e608b06be16931a5f4e62665b394cc0d334e352600dfa40d2d4c48fc287c129c607fd059364fa612de895830e14b2ade88031e838ee0bfe42e60a84b460c199d05c88b3100b19235a3207fece98185f40f40665752f2c7af1f5a08eec83c539b823be03b9896bfc3c692b8c073ce94ce4db967d2d0a2cf62c19d6b65dfd38910d85246bc233fd6efc06becb2acace93486278fbea25f97500235ec1070796617d2dc84a23222a6de5b006f922f03376c7e22d8ec1b79c898e02f97c19ea6dd7b6b76e787aff913ffcc33ca9fc493692bfacbff3dffc75fd9dbffbd7958de5affde5ff9d7efccffd29fd993ff9a3fab1bc06fbfeb7f4f6931b7de68d077a930de64ffcd11fd193070f9837c2a48917a710ae97af98f7f8edcc0de4edebb306767615e30ea25c468d490887067e3e1223f42d70ec926d72b4959c28da808189be152ee2b5281c685b962857ffa5958289f057cce772d569021c382fbffefffefbefe9f2f3a8ef0000100049444154bb75d4272df8e5ab17ecdc17b9ad7d3fa9580cba4a5d88c65b55565553e8e03ab7d4dd1a3828f951b8b5808bb2dad52ab39881234bc5a58b115bb6d594a2d8d688cba96549d4936270c551ae25a7e8030622715322f3a335292431103c839b923ba5154911478217dc2c568304f6b4aeb8a91959719ef2a2a52fab0bbd2999b08d1fa6249b317488ce81e50963dfb3504b1baf1d76ca6b26de8b572cce87b0af74c28f376c36a76d47a635d1f77c77d12ddf4c32c106932bfc1841514b2c6a525173a2eb6402880332357ad896e91b3dc4e7419aea3ec9db43ea666caaab3408489936c8f91653d8d2e8171f3eb8b9d1806f36bbd7af5f2f9d26021adee1f771b930b97267990920e253c4b3ab949f2bce54c68efbf7c8974c4872c1467f5905ee6051d7103d2bec0dcc0cd3535d1b327cab9b4d710895d5e8d9b5cbe834885d086d29728b466211bd020fdec730dbca4f64b6971009888047cf2a6bc90c0349238b5dc69061fab6412dad8d490632657b150c152e50780312508a563f3a86ef468ccf3c5deed4d135af8126bc172ddc063e1fd8745db4a4f827c536a35aaf5e2a6d4a7815319bf8534bd244dfa1117e4351e59eeda44eb98e4dc66d03fb4f38888583e5c2367c2d0e9725fbca4f5e3f4d0c22773f9d143e2fb9193ad0b7d123313134c18cad5d2d85cad240c78217dc09f594d0aeb78dda6c4228eb02624de6dd88cfe9353e0a5d7c14bd0a7ea9f35f2bbcfdd9b7f4077fe487f547fed81fd69fe715d88fffa5ffbdfeca5ffd8bfadb7febaff001ffbfd25ffd2b7f413ffa5ffc41fd177ff0fbf5c33ff8197de6e9239ef66f647b15a9943996bcd49a07a5e8d7e821197dd169e2336e5e847db61907a7525ac12b60beef17b572980bc5f0b12d538b0d653db1c192514e1acb05f08f8cfb6260af5fbed257befa257cc2e200e6a77dd6272df0c30f3f142e5412e4b84f44b7552443591209d038cd4a42e011e5988a839378227ddc5c094a813f09dea0487305055030545d228cb433326987cfb59d968184d6748813d7c99d060181ef602c843381190c817430436c74a254950ae19968afb9cb3fa07165cc02cc5871f771e8ccdd77267268cf2c80a1b361868d5a35fa4c915f13fc8b840d13c145abbbd5d58a5fb6b41b998c377e3a6d9b5ebe78aedb339b089bc3c6a2ff808fed37a787da4ffb553ef26e59680e26d32d4f7d6b12c127528a3bfbe83ce17721069269091dc69217bb07b667629c1c8aa1f3942e6c3655526e3677f4698c2d748cd1e11993d63813b759106ef868faec83f7e12b0da33f7adb469a382cb965780c9584bc4c006b303e199bb2cd426b3532aa377006ed9225e5290b52cd7bdf2bf4c053e7ee3aefd96333604d6cf4b2508aaf60a4838da890358017ba0efc7580774cb84f311edcd64487c1269a8dcb9ad71ff08c4e724722ec80879f0b5b8f55142dc9cbf89251d9567e08b67254b3790546298a28d5e167a57d609768a28a067c0ee21c5edbbeeb969c739772c4c6418c510c5f61cd401d3a16e3d00f6c0c04d594e3c2860da29a206efbb66e3e163d349a5888c04230ea6895823a3c0faca0280e95f99132cea8a2ff6a432b8e6c8e41cbfc98341636c1bdea22b94a4d3cf7d34977af6f79927fa5c1fc1ac145d1d8668b8d1386c0c21fd514dd93cf93ce64c80d2ff85e78e24ecc8357dd98d74bcd0d1fe586eb76fdf9a1cbfa3e98b80ffc057988f1ce548177c393c6e3379ee88db7ded4d337dfe03bcbdbfabecfbfad3fff17fe8cfec6dff8aff477fef65fd15ffe4b7f566f82c372a56ce277c4e140efc29e42dfe80c53642feed834c8a3c14dcb71950342bbd4d50a8dc308980db1acae926de5478207ed55497ac54dd86dfe712e7971f5eb606868901b7395b9da07f1fdca57bfac0f3ffc00aa4fffac4f5ae4fbcb30abdddacab229f86a26a8d476a675dc4760e927515240233087ecfb240f2d0eef2af814ce140e8480be74c53132d680ac02bf53af52a2bb5a836b4e710c8a29ad52600e52c193841565a2c846d09d410a23bca6e15d288bcf602c778ef31ede2c10cd24598962e42dba62521cb01a9a24de14d8888ab85e8b6b3a968a1a3b93d8b6b5b368dc70479f096f868a04dcbaf5c1071fe9f9f3572c286745b71d7937fb039d98989634d1eb8eef2597cb2d8bda60020e364969f99d71db6a6ccce413ed42ee99cda6ba55f4e771c7641c0aafe2716c6bc107be28712151c5c8c42f25262cf644274c926d45d737df7c531f3d7bae0b133c7c3f1e6bf41c9908bca337f4930579502054d6a843a54286c60c484854700aba3925f3135d573b78c01a1fb9acc03f2e428f89d0023e33859968996007b564cec9951add436345aee5a660efc0464fa98d3f02ab5af82e4be8ef7b9e933b4488000d95ad263e206a9529a1bc26322fc871c323e4e4bb4d23c3e838e0171b170d97763122555d6babf0cda46fc59706a7baf5f1e1e0b9c269e5d8d0050c233b45b44b855e6344a1c0289cb9e178cd2b19bb419a02a4e4b238a20f6a6a01d1357e1bd831018e657b868ccca985bbc6ae22ed5275a9bbd77811e758929895ac42dfe646e3e1c347eb4f85e4666b846ffc30850e96cb924d1bfee432578d8c1397e54b78da4d8e829fa75049dda5a24026b9c8c35d85ace81cb9f23d3f81cb9c6bf7f2175d6d82165cdbda48f6e8b76da7d57efce4893ec753cb1fe0e3fda991c72bace83b8969f85ff94ab1cf6136b8502ecca1b8dc362814f208313272ba5b5b35f052f70eac699b224ac12ce7d4c0af89f9cb6c22b92945c880e95c9385717c92fe58f043f93721fff2a7fec5776513416b7da2c787790271e12c1c5556d14e00ca2dbcb6e0056ce2345540e0d9ca317190449bc074152dab094013880667ebd246dbc5187d37b8d4d730909524bd387039a0a92ef0e82b9c90b96a74222f497a500812d1e30c2da38b9765706dabba75e1cef38ef7b0d16d4667749c04f4e003f60a2a3c029fc03fbe5b099a38009170f00667640218a0ac82b76d71ca680264d97562c26d245aa177752b7ae6570a5fe60e0b66f1c30d77fd37bcceca027e70d77a77be55f4cb225ec2268487e7f5692e2dc9f072e8e12bc6e562b11af8c71aeb7597549adab74ca613c34dd9348bda86e400efb82e6cd0565976ebe6e1036d7bebbd77de5d7762424674174785365cb15d898ba7b21214fc36148dbf84ccaa489e8bfec822028f09dcc8196308748a95c9c31022324aa1936f32cda66349e64738acb1b5cbf8b3a01187015f65674ce872100bc35f6e31a2b1744429f485001912acd25457cbf08bac0dde9381e87290178b4cf09fd167a82a3c0c9de164d9d7c23063916529274519531a80680f16571441f05461d384a86b935d9a080245c5c5b60069ab83023a3e12b868a029c162b060bf927cd56b83d7994569f04d08ca85b37041e6d4c102195969db966419015c35c895f866e12fca401571e2aae835c15964cb74c6e94c59824723bbf74dfbe9a40b797a70a3115ee2004355b94a4bc6043828d4137b220eb365b71a3ed1f3606339e2776cfe5876786cfbaed00ce6439604b85c4fc3bff11f3c4120f2f7d2014fe2185e8a20b027fe6ff0df7cf278f939f825cb259d6e12073027798abda15bf2e8e709315c0da2b127757cdee44a213ba58bd883bb78da1227dc9462af0e435317f23fbffd3982cb7c99d198f12b46d0e7c2cb5af25bbff515fdab9ffa49e6e499814fefc41d9facb0671f7da84ae223a6f0d4d6988f3312279b6092158320c4a93663380bb790448332050a053ce0c11b24b870a4ab141e13be5b0295be4a5d25abb9f3b6ec966d154c26c5e001587dbbb411507337690998c41413e804e5d0f5b765d084e42ce82cb1de4d999f3cca82a486be1b5955d097d4966d104ddfb2840d63d5e6da754d1cdbaac533760d4d7c605bd2d4b6edaaf0613c13e1d1831b9d4e3b63ba4e3cf479f6fcb95ef3381d7fecdb49370f1ef2c1fd467972b9f04471c7abac24de85c935a1e44d94864b86e70e7f1b3d8cbc6a686ed0315853035f0cef32f0a6e486673291e6b4067cb84957113b57342d05a7aa74cc292a75973ef3d9cfe91bdffa261b00891c5ce8100b017682978966e2bd2462f78a9d25436b988ce0006ffcdac0d04a3632b0bb188f4d8336240a2f28571d7ee2c846bef4860742755ce09041f8367c8ad2b6ca5b86159eb675b0d824e655962992753da8b1632b8c263ebe2f54f86482ab450bd6d28324916dca7d6cb15586133cc34174ca199b577cf4d2c229fc3f1815b90bd694621fa8aa6e3ad2c6c2187d0f6c9bf86012906a3336578e6c5beb7ce129125919177c6daf18dbc666abc129158bd34502a6255feb88bc6271b64b19921c33d16beac0a9a6bfdc403d96f593abe0062639625b39cc784a781bfe01379742afae5dfb76a3ad77ddf18a6670e3151a48943c9a3420d1c0c681fe736920d9ce855e244b453f3813bc099e6dfc600deac85a3cd860060d4634593726ed0dfbe4d8e7c573e2c7111ee298144e461813688ddd87067300e600a4d8b0c787151ea521319e32974fb3399725371770b66d5313bf6af0e9db850da64c993e98a2935386441446149d0ee6eff93c681f80ad8236372e74145c509503b3c019faea6f7d555ffcd2173403c8c0ffccf2bf04bdfe9710fdff4a13435ef24aa5f068b7e52a0c277ddd52495d71e6c48103074e999f8f791b671534451d7717b85bb75207d6f66aa7b68391549b2467a92d19dce9d27f3a264e3e98df13074be11389f215238196d3b1ca8d4e92482c14a31a0b1f00093399a42c8e195828638d1f24e2e48e6110742b3fa5b033b61bbcc92234ee137d52e7ee3afc6c836730a4aee6f1fa22d333d772297ade9c76e5e3bad1a7ab9878e7f5aa28bf7d22e89b49f1985703f99f0fbb4a673ea6e7bdf96b36928c67628a0994c55ae81d6fa10e93e3ac6a5f0b7cb092e1298ba36f64c786148bab5c03ea89bdf4190359c2235d4d2da57ef2e8e1f2715e65e5313c932083d5cd78c92c7a93575981191ec75a784a30966c4eabc18d8f0613618a21740f6ce0b7ea5ea8037f33447bf25e7f5757511a3399d24c78bb35f11722d4d5b2cdbb69e98057644ae6c41e04c417b6d56e0d685226b28d3cdb72a19f2d40c82b15785afd096cd2df6470ca56156d95a2ab6d09fbb82cbc34277a5f58d88aa11623c8438534647e002d92c88fef2ebc3a6110dd0f551bce2950708e23f2854d53ae939ebf7ca69bbdb453b2992477cab5fecb80c153f340b62203d8c00f9181073489214153f02770001ad10bfb33143cdb8a1ef18141c82baad5073fe38b0783c10aedb89735a173e02e7cc028f1e72a57e3ef9d5ce6752b9b48782c9e1331e8c8bd0cf873958327c41939f73cbba381c9df817a1098366333fa423ea6e17b87cf324e8136f36de277b055717e9440af491174134eb091cd0065001f20bfe0697fc0db2e09f89a8b0f1ead0dd0e103ce80fee0066440905a4195b56da5f8b4bb552eec6d6dc58652b5fae230256306dfb684eec77dcc05ef582a6059cb46c6c1078bebd0fc3d3f1092fb17fd875ffd253de77b29089fca599fa4944c801813c3e344e100e3c8181b275c788c4e3b411c0429ba6438fd2638712cbe53af1ddfb24adda52200b6b575d16fcac608a3c02e047142fbb173078920e0b54a410b1ef493851eff4b5036018ecc829f190bbe65c638a966dad4c12beae87ac7221d95177aca70d200001000494441547bd02afcef6947121ee607893553a071788097891b19e165fa023e824319e85e6c06192f78c1127d5bd9dc1ef224f2e4d1039db80bcd93c6eb57afd6bf907dc52631e2246f7ac09348ca6032e4b7dff2db5067168e96d45e9ebe5f1826109170788966a143b9b90e89c57d60d86442342b6b556b62a44bf02870423a155fa1226349f152d52e97d5dd7afb339fd5fbefbec704bf280b930dce88acb1e88bbb4faf96c08796f1312c8453c0e55adcb9091a0b99cee2c3e60f0d2aa9c00f362ea62d5d8825dc59480fd5524a1cc85a6d46d03fb100b8cee04cfc2df8adba841ebca263e3096f7194afda400d96b153b2419494180afa09df898e035f296d8a506ad180dbd582528443a61ffcea52a37f5126ec70bf2059c55ed848a01e837a605ff0a58327cb0a0f377646c254f2207c122731974a77d27ca57179a1d259131e03e61716ce6a6b15ecdaf64d07373b620ca824eb20670ff0c411eeb665a3200e8fde03dfc487199bd8695b90cba6ce795f8fe58f031f2119bce00e5934b94a552d7791d3bba24716d40b39bc5e6721cb961622cc432b8eaed280410a60ce925d12b8d9608f15ffb1fa622e042fb094495ea192f2317ee08fc966625b0e3d7c61ab1cf1155aa3e755efc1e69cf9f382a7fdb20489a87473b3ebfa144f6f021717cee08fe4016a14deefe2ca5c6ef238312fec4e594c202dc60553d415e86986d3bdfc41df72044ba4cfa42d153ca2ef0436219cab61e15d208276eadb3cfd7ff18bbfa981ad0bf8095f88c22727e196c4b8bd3dcbc638c4c4011f27610cac0c00c7173827aac42320074631a04a37974c80cdea06286a6045098b94052604410f3f42a0c06d2b093527100687d005e7e6ffb21e84662d70f029d87615342091c8133a50c1182b3013f00016fa4cb63b269bc1736402af62a1a51f0b32c906f206996b7bf10cbde066f0926c33d1b754c8368252371b65d39fe88750a082562a9736c66ef8b0fee0e6a413edd369573691972f5fae27a28180aa6213794092dfe8e02e267f523b31882e137da2d7446e26d620d953b2c108fee268f49b9960e870b37bc9045dd52d331efdc798b26bbdc30e6804814678dbe0d97af2c613747ba15b3e0216fec93b998c4b700167cc2b9ed6711de9b66c4bf0164751b67dd3d6adaea2b49461fa033dab4bcd1d5ef0cd78e2d24cd8fccb7cc3634ab83057c9865039868c6d9c6aec9b19b6756033c80271956894b10c8bc32e0916e917b2222f80b453a70c080e7c1aff2e5ed89dbe2025344151554b304a9d184c166dc483295d69a40a802e681ae4c144b73cdd66a10c0cf1cad3acf1d7cee23459d8cb2c7dc76bed3c79ec7c1333724e27ebb44f7511011478c50dc79251fefff076e64d92dcc6157f2fb3bae7d82177258a613bfcfd3f8e236c4976f80a490cdb54583249718fb9ba0bf0efa167a9f0ff1ad7340a4022f3e58104eae8d9595ddf5c6b900f039b27f54e2ea78edaf88e314a6e8796921cbfd0e1406660d719bb06b2194f5c202bf4f019db6263da853f29c19491d754b9655b1b3743cd9cd9d689ef6514bcb050267906132d84f8cc9113b2e5454644b410d9856245bf5d32e383ce9843d96b16cde1948239b17d320e304b1fcb0d9d0eec0a3932191fe8cf05e4f1e1f26fd86051fe10e4cdcd91b81ea5cf7208ed5cc0a2efccebc3496eda5677abab645b964425639ba115b5c2472cd698c438f3a450a7f6dc0c7409221c5ac7b455ac7b397c91c2765d8ea9f499a529e5af65fcfa9f7ef5fff68f0bb1f262c46b9c93b49990ae229856ad700c2d7fd14cec5773b9cfc9142d9ea9b49d60859d8d59d3aa6e2aabb99064ac82dba5247b576bab5a3c5dd4058624b7b50ee4f9c08b06f090828c4536f937a5e20e148a2cecb43a8fdaf031cfb41927a1c83d5521a3a9f876c6816652178d3b77b4c26b954b82bf82cd26910bceea371cd03636be6a7c0176860fdb77c0d75d98d00f1f20725985beeaa25d3a5e5feb965755d75c48cab17bd7fdfd839e4960c3537de05df8b56eae6fd9246e74e27b92a7a77be52272e20bcbf3f35925a9db9ca7f238dedd6ab0261bd11c059d825f03bb538caf938b91328ebdc69ec96a3bb2f845b00ed491cf6f841dc0da0e5960b163e3d5c9c7f51aa1dc8a9e6a62cc4630c6201c53edc4a3251153b0673632e231d1bfd37e6653497d5e9b95d0562cfaa10b3f02b4d6aa078f110d640fdb512ecb367d780d137dc978123d93567147bfb35007e25307e2266c808131e9b01d54f86997244b0c0c7488643863d78e3df3338d1ca9e06373d526db1ab42752550dbea049690cec5b63c46f87072a63459172431399d0c2275955b1171b91cb857122d7c4b05c1acc4fd4a69f8d78605f2eb6b7b777baba7e23c3f3e51737dab696c1b12d430be68a91bc6e3226f586bfe129ec2d78a5b162b38329e442ebc24e7c9e2f451ca1c79649fe4dfa52a9e08765cd6d5741b572314135eda941ec98447597626f6c3f1c8fd8b971c3c197fd5c84a35f420ebfe3d7c057291ae6b22bb15ce34223c033b1847762ef8e2d5da5ae264667f84f70593b4f146bde88e1c0864971bdd8c7c6105a9ef260be7cc07c7c785ebf4e4b73d136d6eddd9b2fd49903640cf58219bf0637556729fe530afd4da92e993a25b1c15c999faad0b50e1b4a4a5c246527be54595df0844ebbdc4ad9c86fada33813a317e32698982444f5c39f7ee055d6af187ffd4fac78352d9f7817e7440c0d593883095e79809793f6a43328e119a1c1679bc4da284d8fc57d6802b7a909a298fc022f0993898141dd192790b2549d33fc5e09ec250319cc6a7808f6448f6d3993836e96a20abeb655f4dbd2744994aad6810d52c588cdb8d49c8b7e1275b0b19e49d8f82617c97a4656342d77a96c6d076cb75465c6e3f1d488ef4960c663f0f2c7b57c998c0553538bef0c5fc6416541905de0dc700779f7868b44ee362de5313b7caa6d6d8647e847166593f0f7f73c09f065a5c03777ac6643516faa3e625f0596ed62aaaaa5c64f7ae2303c135dc210534f133fe87261b2159f77e6237198f04cecccfc08f97c5179858d1f3fbee7f5128b0a392377e65df79a6fedc80f199507e6d7e0db52ea6c2ee2e86e72e0a0ea966d4d74898b7a36d20d3b61d1642e414a73f10c6c98996378d32ee4cce8648e52379bbd0d167c55c48a786c65e665287797a62d7cd9b9480c2e98b0aa380de6a48cdf94022f34dbe89438cbe69c314bd1ebb24ad6998b3670f96862176cea9c285dc49a9a01384be23cd133b1bd9a1e63e9efd00abc6cf2c14b5b1c89b989c3860fc55c15b5a675cb6bccaf7ef6b5088d7a2bddde1c74cd8dc7895c0d76303501003317e8819f13db6247ea9d0d7c82d32647e0215a604d250699f31959c40548f807f60ed35b375013aa555572b56243f006f10fafe1eb75b1be8004cbf85d6e75455fe9c42bd7c9fcb4a542a0ca72d5c2025cb17304d8e8a1c8159206715aeb863a7c85fe9dd774e71468c9d58cef2ffe0e497b0c80d9b46d6ba6803df169c786fb4ff77a3e3d69c0179e1b5e237ff1e64ec116760f782663f1263e06df2a39367729eb20a55973cd5c183f849c0c1ac5e267d1704b537629ade016f6a76ce4ffb6daf0121433d7b6175f786decc606e1e3ea833399c3bfffe5dfe9fbefbf83ef753fb1f8d534fcf8f93f7fc7df8983fb4aa4c1c2da3599a8915f0f8af3381de78b60c498497f4d1a015d415f3c56d14f409b09096b6a71949bc96a1593d1b5c9b6d22e4b5d859c384a32c926344d4ea6a06741731a24cd5421db0c0c75a796981ee66688bd47656bb24182a024cb89e4ec177d5dad1423210edb8a0d40a3451c465e880e55976c6be9242e2e31e6c55765e5bdb06df04ae90f4026a366cc028272e029e4e6e60639e9992ffa7e2459f2ab91930572e42ee5ea78a51b9e562ceb23ef711fe159c93ea582665b71eab01d1444bb54bc0a119bfbe586afb4b9e52a157a07be063b0b65c41eecee62cc29962d75b73a7dfcbbbdbde1e9e87e5ddca2b78bb1c40a3f26b14e3c052fe6a8aa34c0b34a390ccfea5fba92f9381dabc189dd95ba0a7d0d4c696293cbdc69ee60cd4b0127f886371bf1444772451c70c0b32b9b4657897454369b89b2b255ae9f30d3578e9993167de203c2da111cb47736d0b4110d8344a3f0371b91902b74e08624fff4b33c826fc260498687e6c277089212072afcb61e9e9eb425f7e11b4c92b131b8a23e1c6f5e3273e8cddd1b699692a35d567e734e1c4f4fcf0a2fecd05a672e2ac2f6f882891a89a15a3966d020f2495793b13f9790324b133a6dae200c4bb62c54d3d9b90867b0b0f5732c35adb136ddf0702b816e44244e86efb05de1dfb67814fd01938061fdad76ac99b252a021639b3e94193e4ef04356938b3bfee5c9db386c365ea64a3b7b4efc9c3c914cf623d8258b231803dd037d7455bc1ae68610a1f8605b6fde5c7331be91c1c37331a4cccf006715d61e6c4bf7c605bdba555db24bc2a8586739e0b2fd5309a1847efc0a8ea82357c854331fe14ddb1b30c861dbc548ac08280033f263351037afb01ef4eb7ffca5766c83fa6a9f7a356480dfbf7f2fbb96af83680f36a724f524404a503cb58244707261b8d0ac03c10f4b91fc495adbf01138fab2d42e2d39826b5bcdd53dbc2dc9bcba2ab7caa5aa861d6d26a6005241e36cb307419fa1d3a63f32f9d2e26fe4369772b898581604dc74b1d7522daea98fdcdd9fb9abde590867168cedc5f352ade49ac842d4ccc2216985ec697d013f54305697084dc88c98573e3bfd5d9f8f331b777c894d736d80a5b4537201b9e32271cd53d2d3c3e3fa9b59591c28d3d5e18a575457babec9abacc7f52fd99fb381807179e527d8869ab8c49fe833864c0a23d8c0426211a73fd8acdad839c4863964e3273e0fb0a2cfbef427318c3f55a5e3d535e54af9774003dfa353e85a3ccc51b7d55d4a7826e392f958b963db0e47ad8d32346406364d748963a057d9b0e817fdc958eca309b7c99d5e755751a37152989b429f285950130c97795ac3fb8ca3436c6e8d3d70f3c147e4054278138935ff983824d9c5dc6dcbf602f3d22fe416986c8b1ee35305668195d2e4b5d13b441c052a7c033f80451622c180aaf026a768f0290de213bb9f1e9e64f4351870eb890b8a5ddacf58876c7177fffec307fdf71f7eaf42672e1827eea09ff92eb2d9402129baba8bf7f81b6de4b02358188b0d035d43b657e1042d1ffcb2548e57d4e8879201c426397179caccdca4600a38c1be8c2d5e59c0aa0bbd0e8e517b1969f08a624a77abaad7c56de2f7e202d0028f5aa21520957230fd62eab4debe42587deae6427bd88ebcaa7c526237919d0ceed46736d567d66dea652ffc19c7683e933234d173e2696839c93ed5ec31773c7d1cf90e52e0c4ae94e45e30ceacd38501b1bb94fd2cb9dcd5f84cbec4de29d9c63f9824daa59716fa2e7acfacb51d2cdbeac402acda0e977695364a758b610af2d8a61c00cd85c299fc1679f5cd37bf51fe83ba0cbf56a9d7020e6edebd4f3615e2a68943231b68669b41dc944898196761184caca1a70c26a8091c5d584ca0245713f8123dc264357d1a2af80a9c2aa969b72d3e7208a25dadb255301b1de2483b89e19776ee124badbcabddb1c794c9c22a30ca8c908cdd25d3c754f4b4ba9aef164ec2547c0bb79000109d3b491037c33fc0da8981aa54dd61501fb89378691bfeae02070c78e97d23c600000cd3494441549374d129459374dc3664a612a76db5255baa8c5b5c20ae7545526fd8f8fefd073d3c3e73111a42990e872b1d8fd7bae5bded27be0cfcf4f08947f2e7f59a60673e76e271a676597c24595a456a8f75918d2d4b5f4b4a43d2ce5d1ca29a2cc4dcdd16f426c96d2b17a12aeb8afedb776fd705e4135ff4270e46d6559c63bdd5c4200b2d75fe9cfcce461f3f854d5dadeaa554b9a170e22f29f6100dc9d28811e8da89db642206f9b5b3e99cc149898fcae192b923b75b863ff1b7ac3372a9c3520b7faa0bdb5208bf6d7c9c2a7ea4a9c1bc0a9a904b3f372b2206ab1f7e308213bcccffc69c341bb70d0e3213bf62325d580c0442b49ef9ae6a274762fb0c03b4f889a91ac8a4d8f0cb3a73f3514daec2970b08ac58662e26cf3a70e13d7193f023efc01f9f1e74e2c2913f8991380183a9e3056fcaf1915825692720d13141428580a64c62a67495c3381499b2c54786f899bf973d5a32b13f250c039fdb2d1bff3120f331f073460134db8c8962c5dfd85fd5abddc44dc8efbc06cc5df9c4d672f81c456b2e420f0f002aec13876d59a5e01c780acf1f177d7cfe04ff59d19bb233673b39b2f3aa273605db468e98545d6aa3eff191ef642cd9d6e1b8e9edbb773a6cd74a2ec6f71d9c898d137f06fc1826bfc85795aaeba73e0d753758704d71703271a1954f3026583b17b68b2b96c168d67c71f1923764a18151cdcd8b1a3130389b8273390b0814f0b1f5fee307fddbbfff0bf3fee71bd2c5f4173cd55f10ebff404d82faccc2c8a29b2ceeb83a85ab4ee8c5840e255fa5a25a23b24bb635900d2549550431a5ab546e55595ba52e7597b201d9565731566a166dc153d02c6915da552597e94f8873e9c0026abac6066c5c1d381c5eeae8d15a61f36512bcf4cc6caef8819492a065e894ccde2091ba4a9763aaa1773568f8c52212fdf05c920f747c1d99f59885d0ce26950dd1d82478252bc7407690b04537be88466a57e9fafa46b7b7b71258f997eaa155748271e02e2c7f37eb9af7e00f8f9ff4c097ea3b8b67c45fb0a3db6e54149e5eec6eb0bb5124f333c01df8cf5d268b3f1c134ec5364aa16332835d25a373d0ce982d5db1a11d8f477de04974b0b95fe8f02193796bf8bb1bdd933bce5daecb2209dfc0dfe490c1e1a3e8914a550dbf60b12cb1c94d6dd89b4e7807f10b4ff8bb4a5da51cb1b160ea2e9de1896cbde80bef640e421bc48599229448e0afeb225d6e35b9151e85c8fc275f8ac51c19a64fe947ff0a0104a64365cba660c708013d997f079653686be3c4ae0c877ce6d54be89285a806b1186c7687c3a65c201afda688c18c15d80f0f67ddf3a56ff5818bde59a7d3e3baa87cfa78bfe45de64961444439127fe153365061706fa5e0e449f152e07246a863982dc3b736374e76fa6650b2d788728435b12c68290c862c63e36470e0bf2f62e86b4a3a96e11aac9d34b66c9af07be586204d45265ac461953e1f8c2817df65b3cc4f46a85d3af29ab77cd0fe745aeb34be667e082731d1da832c4bf02afa684fe65f1c67ea4f6cc076c9fcdcdc5ee9eecbb7eaa28fffb8a2c12969bd30f10b3135361f0f47c5079795bcb14b6d49291234cb56a2490d1e7cb6973d27e6dee10727585d9b36b7bc7e24d8d45d34226f499f4b5a69874ec1fe1dacfc87534fbcbed62b1d58f23ac849967bee785989da498c330e9920879e498aabc41f4fa78a20a4ecf0c59a4a40135ef83349b61599b4218bf95364bb2e812dea26e926c2b641938abacb2a5dda821a99a16c0c529228fc233a42a3fcd40ea3848e70941ad9c35a60d05ceaba14c86cc84f4abdfc0a3b7a07f296d031d9acf655671c150bb3902f6c33825dc519666422478b8f2992d1155fc591b6e18d8a419c16066c054fe1ff355f5adfddbdd113afb23ef0e5dfbe2e36d6f170d0813bb1fce6d6d57658afb2b2414d0290bbbb739e26300c68955b221e66b3dac6496f74d2f6f841faf09d7cffa3faf1a36a7f221e135ea9f0816925f113d3215c97497cc9d497a7ac2fbefc52ef7f7ccf66c6dd5cb0f153dad4d8ec2ad9462571c29e21a9e86f6cd4598033e0ca867fd6be3f63e510dc14a373170de867d9a5155f645d5e74813d24722f67a9183b73e184b4dad94c821fb9a8492d79e158a50d79bbd169a04a0446d9ac53975757a2deb383801d3ac2d0ac74332795467c36a3f0a52b8ec6772a3e06db1af04cc6ab61c498427fc31c7d767a567eaab003baa90bfa442e7e5ce8cd7befb31e1e4fd81cbe1bfdeeb7bfd777dffda83c999e789595d05f30a5e6c277242f4ea7670df269a217c1e5c280918fd6811ea5d019e80b8f6c4de68b59477628f47541ca2082c9d114c9cab178e18f9d56e64a127c9ca97b5546876d357555abb12f34d11730837c8e3e71f372a9c5e10cbd3c7d14ac5608131e42a48d7ccf5f6938e3e333e51c0cec48dc0c6b6258340c73dae228da62d17df8f040defea0aee04aefde7da13b9ee4ed16a29afc0ce246a5d429f1b9bb54dd6aecbf9496d0218edc73589656bf54a20dc0241697422cb17140337a8b75d09fb1e8dbc41d99897d720936897e3eb127251ca14f8ce1c3fcff91f9ff51af7560c5eb4027204f8f4f8a82fc7a5c126067919c293b0b999b403681cbe28e0593803445b61c02c5f4ad562637814949504a127115acf24b60d36902ce9072a7169962acd8f8174f3361f4a3c32a59a2305544ba991097c1dab8a30dbdd6e42c7d249c9c9619802ed1b50a9aab798df5cc26b6abc0064a0dbd5d1a746cab8bcd1263e37f1736a8e01f323f89d199580c62527dc11ebcffeca28dfc64239fdc45bc68d79927bac9422f37765ae5621d0e353e76b5dedcdef23472a5072e208ff06673b1a40d3b0edb91b81c89f9aeeff38a83f7bb559b40004bd8c486cc06315968f5e17bfdecfe7b7d75ff3ffafaf45e5f9d3fe9eda73fe9e6fb6f75fd87dfe9ea0fbf55fff13f74787acf05850d88a48f2f49dc329875506163f3f473737ba789fe4fd83432e9d893d850299b0e43eb22fb7c3a63c4946d6ca4c69f6603c0415595c44bee600efc174ed9a14d751fd7979de9e789f7c42b9ec428653fed9a993f4d2e24fbb249d32ab00b3dc0283648969d523a6e1bdb8fb1e92c4b8b77323fb691a708bd12f33b14e826f6a65f70038d3e6c772b77c567e26267a4e0dd65fc48ff4c1c0a198925cf3c2f1ba83dacee526279260f667cd5e2823654456ce01b28ce45d6ea4b4e80679b6f184b63b6b62d4fa3d6cf7ff1b5feeaafff566fdfbe53570b71c95272cde8b9e27baac12618bc1d5db9819bd45e8e609b445e9c35e0894dfa4c9f9311ab5de01690e8c526905565f494082272f0f111b6b94ad11151ebf233a2cb305c88b223eb85d9e06cc923ecb61b5c74e0e7440f2de087ac8b6c68a21dbcd8d9c8746f72b7ae8e076d7de0bb9093266b4d1c555257ad7110c8ff09be0591518b69d0efbef94f9d789d14bb6de9175ffd5c476ec660c0af415c0673a2d58ef9d15b30367a374a17ebc08555c24eadc3b66caf76a19f8ec290bc8e1d23feb106b7b28ab261f7e170c4fe4d8d5c7822ddf865a7b5c4971d48e08703a7cb419bf97ae495e677df7f7721bdc2b95e01734126193301f87089130d3eca64db2d724779ec0cf34e52ec4c6e26a264d9043ed1a2d883c7f1b354c5c79456267530cbe4b5727495caa5ae5675d136e4213bed26f8d40b4bb2092c61b62d0b3a67719832b021869da907e393524c2443cc9445774dd24672a80ada54d076269e516551cbd86e614b3156e2b4f89aa4caab293a2444cbf015d84d6d4b49fea2510d22819892ec96aa45572eeb703caa6cf1c103715855854d13cc5ae5eecd1b78c45dc77b3077e5982e153887c395f2574645923ef0bdc479b008843eea60a66c5cb0fee6e6a0af7972c805e98b9b1b7dc9d3cd175747ddf14ae0b64bb76c5537a74faa6f7f237ff30ff2efff59838bcbf8f883f4fcb016ea6482074f37e5d25b1efdef1f1ed6a69c18e4026295e257cace46bbf3ae1b5615fe6c6ce267faa129c710be6fb426e3f84fcb8d4fdd12f9b1e1db20190edc4054132f741be02cf8cbe66cb4598d4c7c1cf1173d0dffa48d25ca3e26179bdc90a16df06ac546429893e811ab15f982641518b13d9b874cdf9b0c6d10df39ad7629c203dbe253313e99d8b6a514da419ae4cfa4ef029f5a0cc756a03489cd603c360ffc8aaf93dac66a70ab5a479e24cacd9d387984826dabf5dfc1feecddcf74c54522ffce65f14b7ae67b91d81b9d5d25f3dd50def54f4d999fd46770cf5c340765e1aa982b7205ec0946fc2b64d31632425613dbd77869f509a8ed15cf9df5244de2e135161db1c1c8e20ad88cae0635b48c891a1755c4840f5d702df0f695d7f9ce237c538331691023117754a2e9621912ea6dd3f5cded8ae3990bc2842731b773960a81aad67ca96debc30ff73cbdfdab9a353bf1e9ee8b3bfdfcdd2fb4f55153164239af121d83398aeeaa528355ddf0941c1d55f85d88a46fe5a7c401ae28d13f88f760ad9cc1d9c93da9d4601c0ea543d30603413e46bf38b002d999de8adb54b0383176f9646c129b415cbefdf6bf2ec45738ff2f000000ffff40d6fa9a00000006494441540300b3fff16bd0fad6db0000000049454e44ae426082);
INSERT INTO `personal_information` (`id`, `patient_id`, `blood_type`, `civil_status`, `religion`, `occupation`, `street`, `profile_photo`) VALUES
(2, 36, 'O+', 'Married', 'Other', 'House Wife', NULL, NULL),
(3, 38, 'O+', 'Single', 'Roman Catholic', 'IT', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `prescribed_medications`
--

CREATE TABLE `prescribed_medications` (
  `prescribed_medication_id` int(10) UNSIGNED NOT NULL,
  `prescription_id` int(10) UNSIGNED NOT NULL,
  `medication_name` varchar(128) NOT NULL,
  `dosage` varchar(64) NOT NULL,
  `frequency` varchar(64) DEFAULT NULL,
  `duration` varchar(32) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('active','dispensed','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `prescription_id` int(10) UNSIGNED NOT NULL,
  `consultation_id` int(10) UNSIGNED DEFAULT NULL,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED DEFAULT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `prescribed_by_employee_id` int(10) UNSIGNED DEFAULT NULL,
  `prescription_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','dispensed','cancelled') DEFAULT 'active',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue_entries`
--

CREATE TABLE `queue_entries` (
  `queue_entry_id` int(10) UNSIGNED NOT NULL,
  `visit_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL,
  `queue_type` enum('triage','consultation','lab','prescription','billing','document') NOT NULL,
  `station_id` int(10) UNSIGNED DEFAULT NULL,
  `queue_number` int(10) UNSIGNED DEFAULT NULL,
  `queue_code` varchar(20) DEFAULT NULL,
  `priority_level` enum('normal','priority','emergency') DEFAULT 'normal',
  `status` enum('waiting','in_progress','skipped','done','cancelled','no_show') DEFAULT 'waiting',
  `time_in` datetime DEFAULT current_timestamp(),
  `time_started` datetime DEFAULT NULL,
  `time_completed` datetime DEFAULT NULL,
  `waiting_time` int(10) UNSIGNED DEFAULT NULL,
  `turnaround_time` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queue_entries`
--

INSERT INTO `queue_entries` (`queue_entry_id`, `visit_id`, `appointment_id`, `patient_id`, `service_id`, `queue_type`, `station_id`, `queue_number`, `queue_code`, `priority_level`, `status`, `time_in`, `time_started`, `time_completed`, `waiting_time`, `turnaround_time`, `remarks`, `created_at`, `updated_at`) VALUES
(1, 1, 22, 7, 1, 'consultation', NULL, 1, NULL, 'normal', 'cancelled', '2025-10-06 07:29:52', NULL, '2025-10-06 07:57:52', NULL, 28, 'Personal Emergency', '2025-10-05 23:29:52', '2025-10-05 23:57:52'),
(2, 2, 23, 7, 1, 'consultation', NULL, 1, NULL, 'normal', 'waiting', '2025-10-06 07:44:50', NULL, NULL, NULL, NULL, NULL, '2025-10-05 23:44:50', '2025-10-05 23:44:50'),
(3, 3, 24, 7, 1, 'consultation', NULL, 1, NULL, 'normal', 'waiting', '2025-10-06 09:09:50', NULL, NULL, NULL, NULL, NULL, '2025-10-06 01:09:50', '2025-10-06 01:09:50');

-- --------------------------------------------------------

--
-- Table structure for table `queue_logs`
--

CREATE TABLE `queue_logs` (
  `queue_log_id` int(10) UNSIGNED NOT NULL,
  `queue_entry_id` int(10) UNSIGNED NOT NULL,
  `action` enum('created','status_changed','moved','reinstated','cancelled','skipped') NOT NULL,
  `old_status` enum('waiting','in_progress','skipped','done','cancelled','no_show') DEFAULT NULL,
  `new_status` enum('waiting','in_progress','skipped','done','cancelled','no_show') NOT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `performed_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queue_logs`
--

INSERT INTO `queue_logs` (`queue_log_id`, `queue_entry_id`, `action`, `old_status`, `new_status`, `remarks`, `performed_by`, `created_at`) VALUES
(1, 1, 'created', NULL, 'waiting', 'Queue entry created for appointment', NULL, '2025-10-05 23:29:52'),
(2, 2, 'created', NULL, 'waiting', 'Queue entry created for appointment', NULL, '2025-10-05 23:44:50'),
(3, 1, 'cancelled', 'waiting', 'cancelled', 'Personal Emergency', NULL, '2025-10-05 23:57:52'),
(4, 3, 'created', NULL, 'waiting', 'Queue entry created for appointment', NULL, '2025-10-06 01:09:50');

-- --------------------------------------------------------

--
-- Table structure for table `receipts`
--

CREATE TABLE `receipts` (
  `receipt_id` int(10) UNSIGNED NOT NULL,
  `billing_id` int(10) UNSIGNED NOT NULL,
  `receipt_number` varchar(50) NOT NULL,
  `payment_date` datetime NOT NULL DEFAULT current_timestamp(),
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `received_by_employee_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `referral_id` int(10) UNSIGNED NOT NULL,
  `referral_num` varchar(20) DEFAULT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `referring_facility_id` int(10) UNSIGNED NOT NULL,
  `referred_to_facility_id` int(10) UNSIGNED DEFAULT NULL,
  `external_facility_name` varchar(255) DEFAULT NULL,
  `vitals_id` int(10) UNSIGNED DEFAULT NULL,
  `service_id` int(10) UNSIGNED DEFAULT NULL,
  `destination_type` enum('barangay_center','district_office','city_office','external') DEFAULT 'barangay_center',
  `referral_reason` text DEFAULT NULL,
  `referred_by` int(10) UNSIGNED DEFAULT NULL,
  `referral_date` datetime NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','accepted','cancelled','issued') DEFAULT 'active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referrals`
--

INSERT INTO `referrals` (`referral_id`, `referral_num`, `patient_id`, `referring_facility_id`, `referred_to_facility_id`, `external_facility_name`, `vitals_id`, `service_id`, `destination_type`, `referral_reason`, `referred_by`, `referral_date`, `status`, `updated_at`) VALUES
(1, 'REF-20250925-0001', 7, 1, NULL, 'sassasa', 1, NULL, 'external', 'Needs lambing; kulang sa aruga\n\nRequested Service: sadaffasdf', 1, '2025-09-25 15:32:52', 'issued', '2025-09-28 11:17:15'),
(2, 'REF-20250927-0001', 7, 4, 1, '', 2, 1, 'city_office', 'Need doctor\'s consultation.', 10, '2025-09-27 18:10:43', 'accepted', '2025-09-30 07:44:51'),
(3, 'REF-20250927-0002', 33, 5, 1, '', 3, 1, 'city_office', 'Needs doctor check-up', 46, '2025-09-27 18:33:23', 'cancelled', '2025-09-30 08:35:39'),
(4, 'REF-20250927-0003', 35, 2, 1, '', 4, 1, 'city_office', 'Need doctor\'s consultation', 8, '2025-09-27 19:06:16', 'cancelled', '2025-09-30 08:35:39'),
(5, 'REF-20250927-0004', 15, 22, 3, '', 5, 1, 'district_office', 'Need proper check-up', 63, '2025-09-27 19:08:26', 'cancelled', '2025-09-30 08:35:39'),
(6, 'REF-20250928-0001', 26, 1, 3, '', 6, 6, 'district_office', 'gbjn', 1, '2025-09-28 23:13:47', 'cancelled', '2025-10-02 08:06:36'),
(7, 'REF-20250930-0001', 37, 22, 1, '', 7, NULL, 'city_office', 'Need doctor\'s consultation', 63, '2025-09-30 16:38:18', 'cancelled', '2025-10-04 00:58:31'),
(8, 'REF-20250930-0002', 37, 1, 1, '', NULL, 1, 'city_office', 'Doctor\'s consultation', 1, '2025-09-30 16:41:14', 'accepted', '2025-09-30 08:42:00'),
(9, 'REF-20251002-0001', 13, 1, 2, '', NULL, 1, 'district_office', 'Doctor\'s Check-up', 1, '2025-10-02 16:10:06', 'cancelled', '2025-10-04 11:39:55'),
(10, 'REF-20251004-0001', 38, 1, 1, '', NULL, 1, 'city_office', 'Hypoglycemic', 1, '2025-10-04 13:04:30', 'cancelled', '2025-10-04 05:09:25'),
(11, 'REF-20251004-0002', 38, 1, 1, '', NULL, 1, 'city_office', 'Doctor\'s Consultation', 1, '2025-10-04 13:11:14', 'active', '2025-10-04 05:11:14'),
(12, 'REF-20251004-0003', 7, 1, 1, '', NULL, 1, 'city_office', 'Check Up', 1, '2025-10-04 21:59:22', 'accepted', '2025-10-05 23:52:13');

-- --------------------------------------------------------

--
-- Table structure for table `referral_logs`
--

CREATE TABLE `referral_logs` (
  `log_id` int(10) UNSIGNED NOT NULL,
  `referral_id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `action` varchar(50) NOT NULL,
  `reason` text DEFAULT NULL,
  `previous_status` varchar(50) DEFAULT NULL,
  `new_status` varchar(50) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `referral_logs`
--

INSERT INTO `referral_logs` (`log_id`, `referral_id`, `employee_id`, `action`, `reason`, `previous_status`, `new_status`, `timestamp`) VALUES
(1, 5, 1, 'cancelled', 'Need to change', 'active', 'cancelled', '2025-09-28 17:16:35'),
(2, 5, 1, 'reinstated', 'Referral reinstated by Alice Smith (admin) - Status changed from cancelled to active', 'cancelled', 'active', '2025-09-28 17:57:16'),
(3, 1, 1, 'reinstated', 'Referral reinstated by Alice Smith (admin) - Status changed from cancelled to active', 'cancelled', 'active', '2025-09-28 18:07:18'),
(4, 1, 1, 'reinstated', 'Referral reinstated by Alice Smith (admin) - Status changed from cancelled to active', 'cancelled', 'active', '2025-09-28 18:07:43'),
(5, 1, 1, 'reinstated', 'Referral reinstated by Alice Smith (admin) - Status changed from cancelled to active', 'cancelled', 'active', '2025-09-28 19:11:02'),
(6, 2, 1, 'cancelled', 'testing to see if it works', 'active', 'cancelled', '2025-09-28 19:19:16'),
(7, 2, 1, 'reinstated', 'Referral reinstated by Alice Smith (admin) - Status changed from cancelled to active', 'cancelled', 'active', '2025-09-28 19:19:24'),
(8, 10, 1, 'cancelled', 'wrong input', 'active', 'cancelled', '2025-10-04 13:09:25');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(10) UNSIGNED NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `description`) VALUES
(1, 'admin', 'System administrator'),
(2, 'doctor', 'Licensed medical doctor'),
(3, 'nurse', 'Registered nurse'),
(4, 'pharmacist', 'Licensed pharmacist'),
(5, 'dho', 'District Health Officer'),
(6, 'bhw', 'Barangay Health Worker'),
(7, 'records_officer', 'Responsible for clinic records'),
(8, 'cashier', 'Handles payments and billing'),
(9, 'laboratory_tech', 'Laboratory technician');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `service_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_billable` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`service_id`, `name`, `description`, `is_billable`) VALUES
(1, 'Primary Care', 'General medical check-ups, diagnosis, and treatment', 0),
(2, 'Dental Services', 'Tooth extraction and oral health consultations', 1),
(3, 'TB DOTS', 'Directly observed treatment for tuberculosis patients', 0),
(4, 'Vaccination Services', 'Tetanus, flu, pneumonia, and other immunizations', 0),
(5, 'HEMS', 'Health Emergency Medical Services for 911 emergencies', 0),
(6, 'Family Planning Services', 'Contraceptive counseling and procedures', 0),
(7, 'Animal Bite Treatment', 'Rabies post-exposure prophylaxis and wound care', 0),
(8, 'Laboratory Test', 'Laboratory testing and diagnostics', 1),
(9, 'Medical Document Request', 'Issuance of medical certificates and other health documents', 1),
(10, 'General Service', 'Non-medical service for registration, check-in, and administrative processes.', 0);

-- --------------------------------------------------------

--
-- Table structure for table `service_items`
--

CREATE TABLE `service_items` (
  `item_id` int(11) UNSIGNED NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `price_php` decimal(10,2) NOT NULL,
  `unit` varchar(50) DEFAULT 'per service',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `effective_date` date DEFAULT curdate()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_items`
--

INSERT INTO `service_items` (`item_id`, `service_id`, `item_name`, `price_php`, `unit`, `is_active`, `effective_date`) VALUES
(1, 2, 'Tooth Extraction', 200.00, 'per service', 1, '2025-09-20'),
(2, 8, 'Complete Blood Count (CBC)', 200.00, 'per service', 1, '2025-09-20'),
(3, 8, 'Platelet Count', 200.00, 'per service', 1, '2025-09-20'),
(4, 8, 'Hemoglobin', 280.00, 'per service', 1, '2025-09-20'),
(5, 8, 'Pregnancy Test', 150.00, 'per service', 1, '2025-09-20'),
(6, 8, 'ABO/Rh Typing', 150.00, 'per service', 1, '2025-09-20'),
(7, 8, 'Gram Stain', 150.00, 'per service', 1, '2025-09-20'),
(8, 8, 'Blood Typing', 150.00, 'per service', 1, '2025-09-20'),
(9, 8, 'Lipid Profile', 570.00, 'per service', 1, '2025-09-20'),
(10, 8, 'Creatinine', 175.00, 'per service', 1, '2025-09-20'),
(11, 8, 'Uric Acid', 180.00, 'per service', 1, '2025-09-20'),
(12, 8, 'FBS (Fasting Blood Sugar)', 120.00, 'per service', 1, '2025-09-20'),
(13, 8, 'Hematocrit', 100.00, 'per service', 1, '2025-09-20'),
(14, 8, 'Urinalysis', 70.00, 'per service', 1, '2025-09-20'),
(15, 8, 'Stool Exam / Fecalysis', 50.00, 'per service', 1, '2025-09-20'),
(16, 9, 'Medical Certificate', 180.00, 'per service', 1, '2025-09-20'),
(17, 9, 'Dental Certificate', 180.00, 'per service', 1, '2025-09-20'),
(18, 9, 'Health Certificate / Health Card', 150.00, 'per service', 1, '2025-09-20'),
(19, 9, 'Occupational Tax ID', 300.00, 'per service', 1, '2025-09-20'),
(20, 9, 'Medico-Legal Examination (Mauling)', 200.00, 'per service', 1, '2025-09-20'),
(21, 9, 'Post Mortem Examination', 1000.00, 'per service', 1, '2025-09-20'),
(22, 9, 'Transfer of Cadaver', 200.00, 'per service', 1, '2025-09-20'),
(23, 9, 'Sanitary to Operate', 50.00, 'per service', 1, '2025-09-20');

-- --------------------------------------------------------

--
-- Table structure for table `stations`
--

CREATE TABLE `stations` (
  `station_id` int(10) UNSIGNED NOT NULL,
  `station_name` varchar(100) NOT NULL,
  `service_id` int(10) UNSIGNED NOT NULL,
  `station_type` enum('checkin','triage','billing','consultation','lab','pharmacy','document') DEFAULT 'consultation',
  `station_number` int(10) UNSIGNED DEFAULT 1,
  `is_active` tinyint(1) DEFAULT 1,
  `is_open` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stations`
--

INSERT INTO `stations` (`station_id`, `station_name`, `service_id`, `station_type`, `station_number`, `is_active`, `is_open`, `created_at`, `updated_at`) VALUES
(1, 'Triage 1', 1, 'triage', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 13:15:38'),
(2, 'Triage 2', 1, 'triage', 2, 1, 1, '2025-10-04 04:26:36', '2025-10-04 13:15:40'),
(3, 'Triage 3', 1, 'triage', 3, 1, 1, '2025-10-04 04:26:36', '2025-10-04 13:15:42'),
(4, 'Billing', 9, 'billing', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 13:15:52'),
(5, 'Primary Care 1', 1, 'consultation', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 13:15:46'),
(6, 'Primary Care 2', 1, 'consultation', 2, 1, 1, '2025-10-04 04:26:36', '2025-10-04 04:26:36'),
(7, 'Dental', 2, 'consultation', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 04:26:36'),
(8, 'TB DOTS', 3, 'consultation', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 04:26:36'),
(9, 'Vaccination', 4, 'consultation', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 13:15:44'),
(10, 'Family Planning', 6, 'consultation', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 04:26:36'),
(11, 'Animal Bite Treatment', 7, 'consultation', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 04:26:36'),
(12, 'Medical Document Requests', 9, 'document', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 04:26:36'),
(13, 'Laboratory', 8, 'lab', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 04:26:36'),
(14, 'Dispensing 1', 1, 'pharmacy', 1, 1, 1, '2025-10-04 04:26:36', '2025-10-04 04:26:36'),
(15, 'Dispensing 2', 1, 'pharmacy', 2, 1, 1, '2025-10-04 04:26:36', '2025-10-04 04:26:36'),
(16, 'Check-In Counter', 10, 'checkin', 1, 1, 1, '2025-10-04 04:57:03', '2025-10-04 13:15:35');

-- --------------------------------------------------------

--
-- Table structure for table `surgical_history`
--

CREATE TABLE `surgical_history` (
  `id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `surgery` varchar(100) DEFAULT NULL,
  `year` year(4) DEFAULT NULL,
  `hospital` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `surgical_history`
--

INSERT INTO `surgical_history` (`id`, `patient_id`, `surgery`, `year`, `hospital`) VALUES
(2, 7, 'Not Applicable', '2025', 'N/A'),
(3, 7, 'Appendectomy', '2025', 'Dr. Arturo P. Pingoy Medical Center (DAPPMC)'),
(4, 36, 'Mastectomy', '2002', 'Dr. Arturo P. Pingoy Medical Center (DAPPMC)');

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `visit_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `facility_id` int(10) UNSIGNED NOT NULL,
  `appointment_id` int(10) UNSIGNED DEFAULT NULL,
  `vitals_id` int(10) UNSIGNED DEFAULT NULL,
  `visit_date` date NOT NULL,
  `time_in` datetime DEFAULT NULL,
  `time_out` datetime DEFAULT NULL,
  `attending_employee_id` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `visit_status` enum('ongoing','completed','cancelled') DEFAULT 'ongoing',
  `attendance_status` enum('early','on_time','late','no_show','left_early') DEFAULT 'on_time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`visit_id`, `patient_id`, `facility_id`, `appointment_id`, `vitals_id`, `visit_date`, `time_in`, `time_out`, `attending_employee_id`, `remarks`, `visit_status`, `attendance_status`, `created_at`, `updated_at`) VALUES
(1, 7, 1, 22, NULL, '2025-10-06', NULL, NULL, NULL, NULL, 'ongoing', 'on_time', '2025-10-05 23:29:52', '2025-10-05 23:29:52'),
(2, 7, 1, 23, NULL, '2025-10-07', NULL, NULL, NULL, NULL, 'ongoing', 'on_time', '2025-10-05 23:44:50', '2025-10-05 23:44:50'),
(3, 7, 4, 24, NULL, '2025-10-06', NULL, NULL, NULL, NULL, 'ongoing', 'on_time', '2025-10-06 01:09:50', '2025-10-06 01:09:50');

-- --------------------------------------------------------

--
-- Table structure for table `vitals`
--

CREATE TABLE `vitals` (
  `vitals_id` int(10) UNSIGNED NOT NULL,
  `patient_id` int(10) UNSIGNED NOT NULL,
  `systolic_bp` int(11) DEFAULT NULL,
  `diastolic_bp` int(11) DEFAULT NULL,
  `heart_rate` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `temperature` decimal(4,2) DEFAULT NULL,
  `weight` decimal(5,2) DEFAULT NULL,
  `height` decimal(5,2) DEFAULT NULL,
  `bmi` decimal(5,2) DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `remarks` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vitals`
--

INSERT INTO `vitals` (`vitals_id`, `patient_id`, `systolic_bp`, `diastolic_bp`, `heart_rate`, `respiratory_rate`, `temperature`, `weight`, `height`, `bmi`, `recorded_by`, `recorded_at`, `remarks`) VALUES
(1, 7, 120, 80, 72, 18, 36.00, 75.00, 173.00, 25.00, 1, '2025-09-25 15:32:52', 'Miss na hatdog niya'),
(2, 7, 120, 80, 72, 18, 36.00, 75.00, 173.00, 25.00, 10, '2025-09-27 18:10:43', 'Mainit ang ulo'),
(3, 33, 120, 80, 72, 18, 36.00, 65.00, 155.00, 27.00, 46, '2025-09-27 18:33:23', ''),
(4, 35, 120, 80, 72, 18, 36.00, 50.00, 155.00, 20.00, 8, '2025-09-27 19:06:16', 'Medyo okay'),
(5, 15, 80, 70, 72, 18, 36.00, 60.00, 160.00, 23.00, 63, '2025-09-27 19:08:26', 'Kabado bente'),
(6, 26, 90, 40, 75, 45, 45.00, 111.00, 202.00, 27.00, 1, '2025-09-28 23:13:47', ''),
(7, 37, 120, 80, NULL, NULL, 36.00, 70.00, 175.00, 22.00, 63, '2025-09-30 16:38:18', '');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `allergies`
--
ALTER TABLE `allergies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_allergies_patient` (`patient_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `fk_appointments_patient` (`patient_id`),
  ADD KEY `fk_appointments_facility` (`facility_id`),
  ADD KEY `fk_appointments_referral` (`referral_id`),
  ADD KEY `fk_appointments_service` (`service_id`),
  ADD KEY `idx_appointments_status` (`status`),
  ADD KEY `idx_appointments_scheduled_date` (`scheduled_date`),
  ADD KEY `idx_appointments_patient_status` (`patient_id`,`status`),
  ADD KEY `idx_scheduled_slot` (`scheduled_date`,`scheduled_time`,`service_id`),
  ADD KEY `idx_appointments_status_date` (`status`,`scheduled_date`),
  ADD KEY `idx_appointments_facility_date` (`facility_id`,`scheduled_date`);

--
-- Indexes for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_appointment_id` (`appointment_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_appointment_logs_created_by` (`created_by_type`,`created_by_id`,`created_at`);

--
-- Indexes for table `assignment_logs`
--
ALTER TABLE `assignment_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_schedule_id` (`schedule_id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_station_id` (`station_id`),
  ADD KEY `idx_action_date` (`action_date`),
  ADD KEY `idx_performed_by` (`performed_by`);

--
-- Indexes for table `assignment_schedules`
--
ALTER TABLE `assignment_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD UNIQUE KEY `uk_employee_station_start_date` (`employee_id`,`station_id`,`start_date`),
  ADD KEY `idx_employee_date` (`employee_id`,`start_date`,`end_date`),
  ADD KEY `idx_station_date` (`station_id`,`start_date`,`end_date`),
  ADD KEY `idx_active_assignments` (`is_active`,`start_date`,`end_date`),
  ADD KEY `idx_assignment_type` (`assignment_type`,`start_date`),
  ADD KEY `idx_assigned_by` (`assigned_by`),
  ADD KEY `idx_assignment_active` (`employee_id`,`station_id`,`is_active`,`start_date`,`end_date`),
  ADD KEY `idx_employee_active_dates` (`employee_id`,`is_active`,`start_date`,`end_date`);

--
-- Indexes for table `barangay`
--
ALTER TABLE `barangay`
  ADD PRIMARY KEY (`barangay_id`),
  ADD KEY `district_id` (`district_id`);

--
-- Indexes for table `billing`
--
ALTER TABLE `billing`
  ADD PRIMARY KEY (`billing_id`),
  ADD KEY `idx_billing_visit_id` (`visit_id`),
  ADD KEY `idx_billing_patient_id` (`patient_id`);

--
-- Indexes for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD PRIMARY KEY (`billing_item_id`),
  ADD KEY `fk_billing_items_service_item` (`service_item_id`);

--
-- Indexes for table `chronic_illnesses`
--
ALTER TABLE `chronic_illnesses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_chronic_illnesses_patient` (`patient_id`);

--
-- Indexes for table `consultations`
--
ALTER TABLE `consultations`
  ADD PRIMARY KEY (`consultation_id`),
  ADD KEY `fk_consultations_visit` (`visit_id`),
  ADD KEY `fk_consultations_patient` (`patient_id`),
  ADD KEY `fk_consultations_attending_employee` (`attending_employee_id`);

--
-- Indexes for table `current_medications`
--
ALTER TABLE `current_medications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_current_medications_patient` (`patient_id`);

--
-- Indexes for table `districts`
--
ALTER TABLE `districts`
  ADD PRIMARY KEY (`district_id`);

--
-- Indexes for table `emergency_contact`
--
ALTER TABLE `emergency_contact`
  ADD PRIMARY KEY (`contact_id`),
  ADD KEY `fk_emergency_contact_patient` (`patient_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `employee_number` (`employee_number`),
  ADD KEY `fk_employees_role` (`role_id`),
  ADD KEY `fk_employees_facility` (`facility_id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`facility_id`),
  ADD KEY `fk_facilities_barangay` (`barangay_id`),
  ADD KEY `fk_facilities_parent` (`parent_facility_id`),
  ADD KEY `district_id` (`district_id`);

--
-- Indexes for table `facility_services`
--
ALTER TABLE `facility_services`
  ADD PRIMARY KEY (`facility_id`,`service_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `family_history`
--
ALTER TABLE `family_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_family_history_patient` (`patient_id`);

--
-- Indexes for table `feedback_answers`
--
ALTER TABLE `feedback_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `fk_feedback_answers_visit` (`visit_id`),
  ADD KEY `fk_feedback_answers_patient` (`patient_id`),
  ADD KEY `fk_feedback_answers_question` (`question_id`),
  ADD KEY `fk_feedback_answers_choice` (`choice_id`),
  ADD KEY `fk_feedback_answers_employee` (`employee_id`),
  ADD KEY `fk_feedback_answers_service` (`service_id`),
  ADD KEY `fk_feedback_answers_facility` (`facility_id`);

--
-- Indexes for table `feedback_questions`
--
ALTER TABLE `feedback_questions`
  ADD PRIMARY KEY (`question_id`);

--
-- Indexes for table `feedback_question_choices`
--
ALTER TABLE `feedback_question_choices`
  ADD PRIMARY KEY (`choice_id`),
  ADD KEY `fk_feedback_choice_question` (`question_id`);

--
-- Indexes for table `immunizations`
--
ALTER TABLE `immunizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_immunizations_patient` (`patient_id`);

--
-- Indexes for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD PRIMARY KEY (`lab_order_id`),
  ADD KEY `fk_lab_orders_appointment` (`appointment_id`),
  ADD KEY `fk_lab_orders_consultation` (`consultation_id`),
  ADD KEY `fk_lab_orders_visit` (`visit_id`),
  ADD KEY `fk_lab_orders_patient` (`patient_id`),
  ADD KEY `fk_lab_orders_ordered_by_employee` (`ordered_by_employee_id`);

--
-- Indexes for table `lifestyle_information`
--
ALTER TABLE `lifestyle_information`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_lifestyle_information_patient` (`patient_id`);

--
-- Indexes for table `past_medical_conditions`
--
ALTER TABLE `past_medical_conditions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_past_medical_conditions_patient` (`patient_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_patients_barangay` (`barangay_id`);

--
-- Indexes for table `patient_flags`
--
ALTER TABLE `patient_flags`
  ADD PRIMARY KEY (`flag_id`),
  ADD KEY `idx_patient_flags_patient` (`patient_id`),
  ADD KEY `idx_patient_flags_appointment` (`appointment_id`),
  ADD KEY `idx_patient_flags_created_by` (`created_by_id`,`created_at`),
  ADD KEY `fk_patient_flags_resolved_by` (`resolved_by_id`),
  ADD KEY `idx_patient_flags_type` (`flag_type`),
  ADD KEY `idx_patient_flags_status` (`is_resolved`),
  ADD KEY `idx_patient_flags_created_date` (`created_at`),
  ADD KEY `idx_patient_flags_appointment_date` (`appointment_id`,`created_at`),
  ADD KEY `idx_patient_flags_flag_type` (`flag_type`,`is_resolved`);

--
-- Indexes for table `personal_information`
--
ALTER TABLE `personal_information`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_personal_information_patient` (`patient_id`);

--
-- Indexes for table `prescribed_medications`
--
ALTER TABLE `prescribed_medications`
  ADD PRIMARY KEY (`prescribed_medication_id`),
  ADD KEY `fk_prescribed_medications_prescription` (`prescription_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`prescription_id`),
  ADD KEY `fk_prescriptions_consultation` (`consultation_id`),
  ADD KEY `fk_prescriptions_appointment` (`appointment_id`),
  ADD KEY `fk_prescriptions_visit` (`visit_id`),
  ADD KEY `fk_prescriptions_patient` (`patient_id`),
  ADD KEY `fk_prescriptions_prescribed_by_employee` (`prescribed_by_employee_id`);

--
-- Indexes for table `queue_entries`
--
ALTER TABLE `queue_entries`
  ADD PRIMARY KEY (`queue_entry_id`),
  ADD KEY `idx_queue_entries_visit_id` (`visit_id`),
  ADD KEY `idx_queue_entries_appointment_id` (`appointment_id`),
  ADD KEY `idx_queue_entries_patient_id` (`patient_id`),
  ADD KEY `idx_queue_entries_service_id` (`service_id`),
  ADD KEY `idx_queue_type_service_date_time` (`queue_type`,`service_id`,`time_in`),
  ADD KEY `idx_queue_slot_status` (`queue_type`,`appointment_id`,`status`),
  ADD KEY `idx_queue_station` (`station_id`),
  ADD KEY `idx_qe_created_at` (`created_at`),
  ADD KEY `idx_qe_type_service_date` (`queue_type`,`service_id`,`created_at`),
  ADD KEY `idx_station_status_date` (`station_id`,`status`,`created_at`);

--
-- Indexes for table `queue_logs`
--
ALTER TABLE `queue_logs`
  ADD PRIMARY KEY (`queue_log_id`),
  ADD KEY `fk_queue_logs_employee` (`performed_by`),
  ADD KEY `idx_queue_entry_id` (`queue_entry_id`);

--
-- Indexes for table `receipts`
--
ALTER TABLE `receipts`
  ADD PRIMARY KEY (`receipt_id`),
  ADD UNIQUE KEY `uniq_receipt_number` (`receipt_number`),
  ADD KEY `idx_receipts_billing_id` (`billing_id`),
  ADD KEY `idx_receipts_received_by_employee_id` (`received_by_employee_id`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`referral_id`),
  ADD UNIQUE KEY `referral_num` (`referral_num`),
  ADD KEY `fk_referrals_patient` (`patient_id`),
  ADD KEY `fk_referrals_referring_facility` (`referring_facility_id`),
  ADD KEY `fk_referrals_referred_to_facility` (`referred_to_facility_id`),
  ADD KEY `fk_referrals_vitals` (`vitals_id`),
  ADD KEY `fk_referrals_service` (`service_id`),
  ADD KEY `fk_referrals_referred_by` (`referred_by`),
  ADD KEY `idx_referral_num` (`referral_num`);

--
-- Indexes for table `referral_logs`
--
ALTER TABLE `referral_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_referral_id` (`referral_id`),
  ADD KEY `idx_employee_id` (`employee_id`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`service_id`);

--
-- Indexes for table `service_items`
--
ALTER TABLE `service_items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `uniq_service_item` (`service_id`,`item_name`),
  ADD KEY `idx_service_items_service_id` (`service_id`);

--
-- Indexes for table `stations`
--
ALTER TABLE `stations`
  ADD PRIMARY KEY (`station_id`),
  ADD KEY `idx_stations_type_active` (`station_type`,`is_active`),
  ADD KEY `idx_stations_service_id` (`service_id`),
  ADD KEY `idx_station_is_open` (`is_open`);

--
-- Indexes for table `surgical_history`
--
ALTER TABLE `surgical_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_surgical_history_patient` (`patient_id`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`visit_id`),
  ADD KEY `fk_visits_patient` (`patient_id`),
  ADD KEY `fk_visits_facility` (`facility_id`),
  ADD KEY `fk_visits_appointment` (`appointment_id`),
  ADD KEY `fk_visits_vitals` (`vitals_id`),
  ADD KEY `fk_visits_attending_employee` (`attending_employee_id`),
  ADD KEY `idx_visit_patient_date` (`patient_id`,`visit_date`),
  ADD KEY `idx_visits_appointment` (`appointment_id`,`visit_date`);

--
-- Indexes for table `vitals`
--
ALTER TABLE `vitals`
  ADD PRIMARY KEY (`vitals_id`),
  ADD KEY `fk_vitals_patient` (`patient_id`),
  ADD KEY `fk_vitals_recorded_by` (`recorded_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `allergies`
--
ALTER TABLE `allergies`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `assignment_logs`
--
ALTER TABLE `assignment_logs`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `assignment_schedules`
--
ALTER TABLE `assignment_schedules`
  MODIFY `schedule_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `barangay`
--
ALTER TABLE `barangay`
  MODIFY `barangay_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `billing`
--
ALTER TABLE `billing`
  MODIFY `billing_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `billing_items`
--
ALTER TABLE `billing_items`
  MODIFY `billing_item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chronic_illnesses`
--
ALTER TABLE `chronic_illnesses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `consultations`
--
ALTER TABLE `consultations`
  MODIFY `consultation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `current_medications`
--
ALTER TABLE `current_medications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `districts`
--
ALTER TABLE `districts`
  MODIFY `district_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `emergency_contact`
--
ALTER TABLE `emergency_contact`
  MODIFY `contact_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `facilities`
--
ALTER TABLE `facilities`
  MODIFY `facility_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `family_history`
--
ALTER TABLE `family_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `feedback_answers`
--
ALTER TABLE `feedback_answers`
  MODIFY `answer_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_questions`
--
ALTER TABLE `feedback_questions`
  MODIFY `question_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_question_choices`
--
ALTER TABLE `feedback_question_choices`
  MODIFY `choice_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `immunizations`
--
ALTER TABLE `immunizations`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lab_orders`
--
ALTER TABLE `lab_orders`
  MODIFY `lab_order_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lifestyle_information`
--
ALTER TABLE `lifestyle_information`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `past_medical_conditions`
--
ALTER TABLE `past_medical_conditions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `patient_flags`
--
ALTER TABLE `patient_flags`
  MODIFY `flag_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `personal_information`
--
ALTER TABLE `personal_information`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `prescribed_medications`
--
ALTER TABLE `prescribed_medications`
  MODIFY `prescribed_medication_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `prescription_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `queue_entries`
--
ALTER TABLE `queue_entries`
  MODIFY `queue_entry_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `queue_logs`
--
ALTER TABLE `queue_logs`
  MODIFY `queue_log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `receipts`
--
ALTER TABLE `receipts`
  MODIFY `receipt_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `referral_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `referral_logs`
--
ALTER TABLE `referral_logs`
  MODIFY `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `service_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `service_items`
--
ALTER TABLE `service_items`
  MODIFY `item_id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `stations`
--
ALTER TABLE `stations`
  MODIFY `station_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `surgical_history`
--
ALTER TABLE `surgical_history`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `visit_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vitals`
--
ALTER TABLE `vitals`
  MODIFY `vitals_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `allergies`
--
ALTER TABLE `allergies`
  ADD CONSTRAINT `fk_allergies_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appointments_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`),
  ADD CONSTRAINT `fk_appointments_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_appointments_referral` FOREIGN KEY (`referral_id`) REFERENCES `referrals` (`referral_id`),
  ADD CONSTRAINT `fk_appointments_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`);

--
-- Constraints for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  ADD CONSTRAINT `fk_appointment_logs_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_appointment_logs_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE;

--
-- Constraints for table `assignment_logs`
--
ALTER TABLE `assignment_logs`
  ADD CONSTRAINT `fk_assignment_logs_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_logs_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_assignment_logs_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `assignment_schedules` (`schedule_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assignment_logs_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`station_id`) ON DELETE CASCADE;

--
-- Constraints for table `assignment_schedules`
--
ALTER TABLE `assignment_schedules`
  ADD CONSTRAINT `fk_assignment_schedules_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignment_schedules_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_assignment_schedules_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`station_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `barangay`
--
ALTER TABLE `barangay`
  ADD CONSTRAINT `barangay_ibfk_1` FOREIGN KEY (`district_id`) REFERENCES `districts` (`district_id`);

--
-- Constraints for table `billing`
--
ALTER TABLE `billing`
  ADD CONSTRAINT `fk_billing_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_billing_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON UPDATE CASCADE;

--
-- Constraints for table `billing_items`
--
ALTER TABLE `billing_items`
  ADD CONSTRAINT `fk_billing_items_service_item` FOREIGN KEY (`service_item_id`) REFERENCES `service_items` (`item_id`) ON UPDATE CASCADE;

--
-- Constraints for table `chronic_illnesses`
--
ALTER TABLE `chronic_illnesses`
  ADD CONSTRAINT `fk_chronic_illnesses_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `consultations`
--
ALTER TABLE `consultations`
  ADD CONSTRAINT `fk_consultations_attending_employee` FOREIGN KEY (`attending_employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `fk_consultations_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_consultations_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`);

--
-- Constraints for table `current_medications`
--
ALTER TABLE `current_medications`
  ADD CONSTRAINT `fk_current_medications_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `emergency_contact`
--
ALTER TABLE `emergency_contact`
  ADD CONSTRAINT `fk_emergency_contact_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`);

--
-- Constraints for table `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `fk_employees_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`),
  ADD CONSTRAINT `fk_employees_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);

--
-- Constraints for table `facilities`
--
ALTER TABLE `facilities`
  ADD CONSTRAINT `facilities_ibfk_1` FOREIGN KEY (`district_id`) REFERENCES `districts` (`district_id`),
  ADD CONSTRAINT `fk_facilities_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangay` (`barangay_id`),
  ADD CONSTRAINT `fk_facilities_parent` FOREIGN KEY (`parent_facility_id`) REFERENCES `facilities` (`facility_id`);

--
-- Constraints for table `facility_services`
--
ALTER TABLE `facility_services`
  ADD CONSTRAINT `facility_services_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`),
  ADD CONSTRAINT `facility_services_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`);

--
-- Constraints for table `family_history`
--
ALTER TABLE `family_history`
  ADD CONSTRAINT `fk_family_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `feedback_answers`
--
ALTER TABLE `feedback_answers`
  ADD CONSTRAINT `fk_feedback_answers_choice` FOREIGN KEY (`choice_id`) REFERENCES `feedback_question_choices` (`choice_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feedback_answers_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feedback_answers_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feedback_answers_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feedback_answers_question` FOREIGN KEY (`question_id`) REFERENCES `feedback_questions` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feedback_answers_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_feedback_answers_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON UPDATE CASCADE;

--
-- Constraints for table `feedback_question_choices`
--
ALTER TABLE `feedback_question_choices`
  ADD CONSTRAINT `fk_feedback_choice_question` FOREIGN KEY (`question_id`) REFERENCES `feedback_questions` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `immunizations`
--
ALTER TABLE `immunizations`
  ADD CONSTRAINT `fk_immunizations_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lab_orders`
--
ALTER TABLE `lab_orders`
  ADD CONSTRAINT `fk_lab_orders_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `fk_lab_orders_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`consultation_id`),
  ADD CONSTRAINT `fk_lab_orders_ordered_by_employee` FOREIGN KEY (`ordered_by_employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `fk_lab_orders_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_lab_orders_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`);

--
-- Constraints for table `lifestyle_information`
--
ALTER TABLE `lifestyle_information`
  ADD CONSTRAINT `fk_lifestyle_information_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `past_medical_conditions`
--
ALTER TABLE `past_medical_conditions`
  ADD CONSTRAINT `fk_past_medical_conditions_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patients_barangay` FOREIGN KEY (`barangay_id`) REFERENCES `barangay` (`barangay_id`);

--
-- Constraints for table `patient_flags`
--
ALTER TABLE `patient_flags`
  ADD CONSTRAINT `fk_patient_flags_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_patient_flags_created_by_employee` FOREIGN KEY (`created_by_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_patient_flags_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_patient_flags_resolved_by` FOREIGN KEY (`resolved_by_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `personal_information`
--
ALTER TABLE `personal_information`
  ADD CONSTRAINT `fk_personal_information_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `prescribed_medications`
--
ALTER TABLE `prescribed_medications`
  ADD CONSTRAINT `fk_prescribed_medications_prescription` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`prescription_id`);

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_prescriptions_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `fk_prescriptions_consultation` FOREIGN KEY (`consultation_id`) REFERENCES `consultations` (`consultation_id`),
  ADD CONSTRAINT `fk_prescriptions_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_prescriptions_prescribed_by_employee` FOREIGN KEY (`prescribed_by_employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `fk_prescriptions_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`);

--
-- Constraints for table `queue_entries`
--
ALTER TABLE `queue_entries`
  ADD CONSTRAINT `fk_queue_entries_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_entries_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_entries_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_entries_station` FOREIGN KEY (`station_id`) REFERENCES `stations` (`station_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_entries_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_queue_entries_visitid` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`visit_id`) ON DELETE CASCADE;

--
-- Constraints for table `queue_logs`
--
ALTER TABLE `queue_logs`
  ADD CONSTRAINT `fk_queue_logs_employee` FOREIGN KEY (`performed_by`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_queue_logs_entry` FOREIGN KEY (`queue_entry_id`) REFERENCES `queue_entries` (`queue_entry_id`) ON DELETE CASCADE;

--
-- Constraints for table `receipts`
--
ALTER TABLE `receipts`
  ADD CONSTRAINT `fk_receipts_billing` FOREIGN KEY (`billing_id`) REFERENCES `billing` (`billing_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_receipts_employee` FOREIGN KEY (`received_by_employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `fk_referrals_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_referrals_referred_by` FOREIGN KEY (`referred_by`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `fk_referrals_referred_to_facility` FOREIGN KEY (`referred_to_facility_id`) REFERENCES `facilities` (`facility_id`),
  ADD CONSTRAINT `fk_referrals_referring_facility` FOREIGN KEY (`referring_facility_id`) REFERENCES `facilities` (`facility_id`),
  ADD CONSTRAINT `fk_referrals_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`),
  ADD CONSTRAINT `fk_referrals_vitals` FOREIGN KEY (`vitals_id`) REFERENCES `vitals` (`vitals_id`);

--
-- Constraints for table `referral_logs`
--
ALTER TABLE `referral_logs`
  ADD CONSTRAINT `fk_referral_logs_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_referral_logs_referral` FOREIGN KEY (`referral_id`) REFERENCES `referrals` (`referral_id`) ON DELETE CASCADE;

--
-- Constraints for table `service_items`
--
ALTER TABLE `service_items`
  ADD CONSTRAINT `fk_service_items_service` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON UPDATE CASCADE;

--
-- Constraints for table `stations`
--
ALTER TABLE `stations`
  ADD CONSTRAINT `fk_stations_service_id` FOREIGN KEY (`service_id`) REFERENCES `services` (`service_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `surgical_history`
--
ALTER TABLE `surgical_history`
  ADD CONSTRAINT `fk_surgical_history_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `fk_visits_appointment` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`),
  ADD CONSTRAINT `fk_visits_attending_employee` FOREIGN KEY (`attending_employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `fk_visits_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`facility_id`),
  ADD CONSTRAINT `fk_visits_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_visits_vitals` FOREIGN KEY (`vitals_id`) REFERENCES `vitals` (`vitals_id`);

--
-- Constraints for table `vitals`
--
ALTER TABLE `vitals`
  ADD CONSTRAINT `fk_vitals_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`),
  ADD CONSTRAINT `fk_vitals_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `employees` (`employee_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
