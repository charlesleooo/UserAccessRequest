-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 27, 2025 at 02:29 AM
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
-- Database: `u742707152_uardb`
--

-- --------------------------------------------------------

--
-- Table structure for table `access_requests`
--

CREATE TABLE `access_requests` (
  `id` int(11) NOT NULL,
  `requestor_name` varchar(255) NOT NULL,
  `business_unit` varchar(50) NOT NULL,
  `access_request_number` varchar(20) NOT NULL,
  `department` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `request_date` date NOT NULL,
  `access_type` varchar(50) NOT NULL,
  `justification` text NOT NULL,
  `system_type` varchar(255) DEFAULT NULL,
  `other_system_type` varchar(255) DEFAULT NULL,
  `role_access_type` varchar(50) DEFAULT NULL,
  `duration_type` varchar(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `date_needed` date DEFAULT NULL COMMENT 'Date when access is needed',
  `access_level` varchar(20) DEFAULT NULL,
  `user_names` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`user_names`)),
  `status` enum('pending_superior','pending_help_desk','pending_technical','pending_process_owner','pending_admin','approved','rejected','pending_testing','pending_testing_setup','pending_testing_review') NOT NULL DEFAULT 'pending_superior',
  `testing_status` enum('not_required','pending','success','failed') DEFAULT 'not_required',
  `testing_notes` text DEFAULT NULL,
  `testing_instructions` text DEFAULT NULL,
  `submission_date` datetime NOT NULL,
  `superior_id` int(11) DEFAULT NULL,
  `superior_review_date` datetime DEFAULT NULL,
  `superior_notes` text DEFAULT NULL,
  `help_desk_id` int(11) DEFAULT NULL,
  `help_desk_review_date` datetime DEFAULT NULL,
  `help_desk_notes` text DEFAULT NULL,
  `technical_id` int(11) DEFAULT NULL,
  `technical_review_date` datetime DEFAULT NULL,
  `technical_notes` text DEFAULT NULL,
  `process_owner_id` int(11) DEFAULT NULL,
  `process_owner_review_date` datetime DEFAULT NULL,
  `process_owner_notes` text DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `admin_review_date` datetime DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `review_date` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `application_system` varchar(255) DEFAULT NULL COMMENT 'Stores application system information'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `access_requests`
--

INSERT INTO `access_requests` (`id`, `requestor_name`, `business_unit`, `access_request_number`, `department`, `email`, `employee_id`, `request_date`, `access_type`, `justification`, `system_type`, `other_system_type`, `role_access_type`, `duration_type`, `start_date`, `end_date`, `date_needed`, `access_level`, `user_names`, `status`, `testing_status`, `testing_notes`, `testing_instructions`, `submission_date`, `superior_id`, `superior_review_date`, `superior_notes`, `help_desk_id`, `help_desk_review_date`, `help_desk_notes`, `technical_id`, `technical_review_date`, `technical_notes`, `process_owner_id`, `process_owner_review_date`, `process_owner_notes`, `admin_id`, `admin_review_date`, `admin_notes`, `reviewed_by`, `review_date`, `review_notes`, `application_system`) VALUES
(55, 'PALOMARES, CHARLES LEO H.', 'AAC', 'UAR-REQ2025-022', 'INFORMATION TECHNOLOGY ', '', 'AAC052003', '2025-05-27', 'individual', 'qweqwe', NULL, NULL, 'full', 'permanent', NULL, NULL, '2025-05-27', NULL, '[\"qweqweqwe\"]', 'pending_technical', 'not_required', NULL, NULL, '2025-05-27 08:25:10', 6, '2025-05-27 08:25:21', 'qweqwe', 15, '2025-05-27 08:25:37', 'qweqwewqe', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PC Access - Network');

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `role`, `username`, `password`, `created_at`) VALUES
(3, 'admin', 'admin', '$2y$10$SA4aRMZAhyKQPxzFdI1w/uwT1Xf2VKciIzpraAAxQcaRu2DTDYyHG', '2025-03-26 01:56:34'),
(6, 'superior', 'superior', '$2y$10$Pu46k5pVVhkX2oKpg4ZFy.BssUanZdGykoqRPPe6fuPSkfOA0ZuNe', '2025-05-05 05:31:07'),
(9, 'technical_support', 'technical_support', '$2y$10$iNe0w0/K7F6rIgRsFHTlge7auKyZMW/fbCHmN2/CnGPdhbD7vsTcG', '2025-05-18 04:25:26'),
(10, 'process_owner', 'process_owner', '$2y$10$IJrAULu0Y6ecrA6TcB7IsOJPkneQOkxL0/HVF1VGkNpvThwdiRiXS', '2025-05-18 04:25:34'),
(11, 'superior', 'AAC052002', '$2y$10$Npe.Q7cqh3UzM08E0D86tum5MVKI/3KNC5eL3Ar.fd2sWhcye7IXm', '2025-05-20 05:16:12'),
(12, 'admin', 'AAC052003', '$2y$10$tpfZgEnWgFdzm8Todliz7eLrSDIc79jopD0f4GHSecmvueM6wI34G', '2025-05-20 05:17:50'),
(13, 'technical_support', 'techsupp1', '$2y$10$ey1NtHsptYY.xhxccySrT.9r4.qRjNjgeaqF79V1KTqQWtDrkxBum', '2025-05-20 05:19:28'),
(14, 'process_owner', 'process1', '$2y$10$Exm.FkyH.IJzaQ.i2Fy0SeSGYbYVx6maLLmQJQwOdQeRSCTiPx4.q', '2025-05-20 05:20:15'),
(15, 'help_desk', 'helpdesk1', '$2y$10$SA4aRMZAhyKQPxzFdI1w/uwT1Xf2VKciIzpraAAxQcaRu2DTDYyHG', '2025-05-21 01:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `approval_history`
--

CREATE TABLE `approval_history` (
  `history_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `access_request_number` varchar(20) NOT NULL,
  `action` enum('approved','rejected') NOT NULL,
  `requestor_name` varchar(255) NOT NULL,
  `business_unit` varchar(50) NOT NULL,
  `department` varchar(100) NOT NULL,
  `access_type` varchar(50) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `system_type` varchar(255) DEFAULT NULL,
  `duration_type` varchar(20) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `justification` text NOT NULL,
  `email` varchar(255) NOT NULL,
  `employee_id` varchar(20) DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `testing_status` enum('not_required','success','failed') DEFAULT 'not_required',
  `superior_id` int(11) DEFAULT NULL,
  `superior_notes` text DEFAULT NULL,
  `help_desk_id` int(11) DEFAULT NULL,
  `help_desk_notes` text DEFAULT NULL,
  `technical_id` int(11) DEFAULT NULL,
  `technical_notes` text DEFAULT NULL,
  `process_owner_id` int(11) DEFAULT NULL,
  `process_owner_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `approval_history`
--

INSERT INTO `approval_history` (`history_id`, `request_id`, `access_request_number`, `action`, `requestor_name`, `business_unit`, `department`, `access_type`, `admin_id`, `comments`, `system_type`, `duration_type`, `start_date`, `end_date`, `justification`, `email`, `employee_id`, `contact_number`, `testing_status`, `superior_id`, `superior_notes`, `help_desk_id`, `help_desk_notes`, `technical_id`, `technical_notes`, `process_owner_id`, `process_owner_notes`, `created_at`) VALUES
(10, NULL, 'UAR-REQ2025-001', 'approved', 'Charles Leo Palomares', 'AAC', 'INFORMATION TECHNOLOGY ', 'Server Access', 3, '', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', NULL, '09606072661', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-13 06:02:15'),
(11, NULL, 'UAR-REQ2025-003', 'rejected', 'Charles Leo Palomares', 'AAC', 'INFORMATION TECHNOLOGY ', 'PC Access - Network', 3, '', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', NULL, '09606072661', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-13 06:06:26'),
(12, NULL, 'UAR-REQ2025-002', 'approved', 'Charles Leo Palomares', 'AAC', 'INFORMATION TECHNOLOGY ', 'PC Access - Network', 3, 'Email: clpalomares@saranganibay.com.ph\r\n\r\nPassword: clpalomares', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', NULL, '09606072661', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-14 01:29:07'),
(13, NULL, 'UAR-REQ2025-005', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'PC Access - Network', 3, 'qwe', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-15 01:48:59'),
(14, NULL, 'UAR-REQ2025-006', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'System Application', 3, 'haha', 'Legacy Vouchering', 'permanent', NULL, NULL, 'im newly hired and i want to have access to legacy vouchering', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'success', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-15 02:09:44'),
(15, NULL, 'UAR-REQ2025-008', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'System Application', 3, 'wow nice!', 'Quickbooks', 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'success', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-15 04:02:19'),
(16, NULL, 'UAR-REQ2025-004', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'PC Access - Network', 3, 'qwe', NULL, 'permanent', NULL, NULL, 'charles palomares', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'not_required', 6, 'e', NULL, NULL, 9, 'qwe', 10, 'qwe', '2025-05-18 08:29:31'),
(17, NULL, 'UAR-REQ2025-012', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'System Application', NULL, 'Automatically approved after successful testing', 'Quickbooks', 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', 'AAC052003', 'Not provided', 'success', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-18 09:08:47'),
(18, NULL, 'UAR-REQ2025-011', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'System Application', 3, 'Automatically approved after successful testing', 'Canvasing System, Quickbooks', 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', 'AAC052003', 'Not provided', 'success', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-18 09:12:41'),
(19, NULL, 'UAR-REQ2025-013', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'System Application', 3, 'Automatically approved after successful testing', 'Canvasing System', 'permanent', NULL, NULL, 'qwewqe', 'charlesleohermano@gmail.com', 'AAC052003', 'Not provided', 'success', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-18 09:26:33'),
(20, NULL, 'UAR-REQ2025-014', 'rejected', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'PC Access - Network', 6, 'qwewqe', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-18 09:32:40'),
(21, NULL, 'UAR-REQ2025-015', 'rejected', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'TNA Biometric Device Access', 6, 'qwe', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-18 09:39:42'),
(22, NULL, 'UAR-REQ2025-016', 'rejected', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'Printer Access', 6, 'qwe', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-18 09:42:03'),
(23, NULL, 'UAR-REQ2025-017', 'rejected', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'TNA Biometric Device Access', 6, 'qwewqe', NULL, 'permanent', NULL, NULL, 'qwe', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-18 09:45:02'),
(24, NULL, 'UAR-REQ2025-018', 'rejected', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'Internet Access', 6, 'qwewqe', NULL, 'permanent', NULL, NULL, 'qweqwe', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-18 09:48:49'),
(25, NULL, 'UAR-REQ2025-019', 'rejected', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'Printer Access', 6, 'qweqwe', NULL, 'permanent', NULL, NULL, 'qweqweqwe', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-18 09:58:55'),
(26, NULL, 'UAR-REQ2025-021', 'approved', 'Jess Vitualla', 'AAC', 'INFORMATION TECHNOLOGY ', 'System Application', 12, 'Automatically approved after successful testing', 'Memorandum Receipt', 'temporary', '2025-05-20', '2025-09-30', 'I want to access the MR coz i wanna see a tutubi na walang tinatagong bato sa ilalim ng lupa tinuka ng manok na nanggaling pa sa bundok', 'jessvitualla@gmail.com', 'AAC000999', 'Not provided', 'success', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-20 05:26:15'),
(27, NULL, 'UAR-REQ2025-020', 'rejected', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'Wi-Fi/Access Point Access', 13, 'jjqwjeiqweiiqwe', NULL, 'permanent', NULL, NULL, 'qwewqe', 'charlesleohermano@gmail.com', NULL, 'Not provided', 'not_required', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2025-05-20 05:32:35'),
(28, NULL, 'UAR-REQ2025-023', 'approved', 'PALOMARES, CHARLES LEO H.', 'AAC', 'INFORMATION TECHNOLOGY ', 'individual', 12, 'qweqwe', NULL, 'permanent', NULL, NULL, 'qweqweqwe', '', NULL, 'Not provided', 'not_required', 6, 'qwe', NULL, NULL, NULL, NULL, 14, 'qweqwe', '2025-05-27 00:29:32');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` varchar(20) NOT NULL,
  `company` varchar(10) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `employee_email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `is_temp_password` tinyint(1) NOT NULL DEFAULT 1,
  `role` enum('requestor','superior','technical_support','process_owner','admin','help_desk') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `company`, `employee_name`, `department`, `employee_email`, `password`, `is_temp_password`, `role`) VALUES
('123', 'AAC', 'superior', 'INFORMATION TECHNOLOGY', 'charlesondota@gmail.com', '$2y$10$Ao6tJGW4vvECcFrK6IgPlO/Q08jbcMOIxMAFRpwp1.EZ8VRAllGT.', 0, 'superior'),
('AAC000001', 'AAC', 'ALCANTARA, ALEJANDRO I.', 'G & A', '', NULL, 1, 'requestor'),
('AAC000003', 'AAC', 'TICAO, REX J.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000007', 'AAC', 'MINGO, NOEL R.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000008', 'AAC', 'REYES SR., ROBERT B.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000009', 'AAC', 'DAVILA, NICOLAS G.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000010', 'AAC', 'DESCARTIN, NESTOR B.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000011', 'AAC', 'FATADILLO, RAFAEL V.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000013', 'AAC', 'GALO, SALVADOR C.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000014', 'AAC', 'ABREA, DANILO S.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000016', 'AAC', 'DAVILA, JOSE EVAN G.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000017', 'AAC', 'FALLER, ALAN B.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000018', 'AAC', 'ARISGADO, MELCHOR V.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000022', 'AAC', 'AMLON, ERENEO P.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000023', 'AAC', 'ANDRINO, RONILO P.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000026', 'AAC', 'POLISTICO SR, JERRY O.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000027', 'AAC', 'SALARDA, TEODY C.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000029', 'AAC', 'VILLAMORA, GARRY M.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000030', 'AAC', 'FALLER, EDWIN B.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000031', 'AAC', 'GAMAO, ARIEL I.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000033', 'AAC', 'ECHAVEZ, ROLANDO B.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000036', 'AAC', 'CONATO, RONIE P.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000037', 'AAC', 'SACEDOR SR, RENANTE P.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000038', 'AAC', 'CAMINOY, PROCESO T.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000039', 'AAC', 'TANGUILIG, TRUMAN F.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000041', 'AAC', 'ALIMAJEN, LEONARDO A.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000043', 'AAC', 'DEAROS, RUBEN A.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000045', 'AAC', 'BACTOL, EDGAR G.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000047', 'AAC', 'CARANDANG, LAWRENCE J.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000048', 'AAC', 'BARZA, NANCY L.', 'APP', '', NULL, 1, 'requestor'),
('AAC000050', 'AAC', 'CAWIT, OLIVA L.', 'APP', '', NULL, 1, 'requestor'),
('AAC000054', 'AAC', 'BENITEZ, CELSO M.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000056', 'AAC', 'LAPASA, NORBERTO A.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000059', 'AAC', 'ANCHETA SR, CRISANTO G.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000060', 'AAC', 'BAHINTING, FELIPE B.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000062', 'AAC', 'JULIA, LANELISA V.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000063', 'AAC', 'OSIAS, ERLINDA D.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000064', 'AAC', 'PERANDOS, PASCUAL C.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000065', 'AAC', 'CAAT JR, ANTONIO S.', 'RPP', '', NULL, 1, 'requestor'),
('AAC000067', 'AAC', 'COMAHIG, FEDERICO B.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000068', 'AAC', 'DAPOSALA, RENELYN L.', 'APP', '', NULL, 1, 'requestor'),
('AAC000069', 'AAC', 'LAPASA, MARY ANN P.', 'APP', '', NULL, 1, 'requestor'),
('AAC000070', 'AAC', 'SENIT, DIONECE N.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000072', 'AAC', 'GERALDO, RAINIER A.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC000074', 'AAC', 'QUILLAZA JR, ERNESTO P.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000075', 'AAC', 'NAWA, MHEER M.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC000076', 'AAC', 'BRACERO, FELICISIMO C.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC000077', 'AAC', 'TEJADA, RICHARD T.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000078', 'AAC', 'BUALA, MARIA LUZ J.', 'G & A', '', NULL, 1, 'requestor'),
('AAC000084', 'AAC', 'VICENCIO, MICHAEL B.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000085', 'AAC', 'REQUIRON, MARIGEN M.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000087', 'AAC', 'DIONIO, GERRY A.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000090', 'AAC', 'BLANCO, RENE I.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000093', 'AAC', 'GERALDO, HELEN C.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000094', 'AAC', 'CALIAO JR, DOMINGO P.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000095', 'AAC', 'ROMANA, HERMINIGILDA L.', 'INFORMATION TECHNOLOGY ', '', NULL, 1, 'requestor'),
('AAC000097', 'AAC', 'ARISGADO, ELMO V.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000098', 'AAC', 'PANZA, RANDEL A.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000099', 'AAC', 'WAHAB, RONIE D.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC000100', 'AAC', 'FORTALEZA, YOICHIE C.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000103', 'AAC', 'SACRO, HYRAM A.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000107', 'AAC', 'ALESNA, ROLANDO V.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000108', 'AAC', 'AMODIA, REYNALDO P.', 'INFORMATION TECHNOLOGY ', '', NULL, 1, 'requestor'),
('AAC000109', 'AAC', 'SALAAN, ANA SHEILA M.', 'APP', '', NULL, 1, 'requestor'),
('AAC000110', 'AAC', 'DE LA CRUZ, FAO G.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000113', 'AAC', 'AUSTERO, DALEA L.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000116', 'AAC', 'BACASTIGUE, VICENTE M.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000119', 'AAC', 'LAPE, RENATO M.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000120', 'AAC', 'TOMON, MICHAEL M.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000122', 'AAC', 'MACASI, SAMSON S.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000123', 'AAC', 'NADAL, NONITO M.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000124', 'AAC', 'ALCANTARA, GABRIEL H.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000125', 'AAC', 'SATUR, JOHNNY T.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000126', 'AAC', 'SOBREMISANA, MARY JOY U.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000128', 'AAC', 'VILLEGAS, EDENE PEARL C.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000129', 'AAC', 'OSITA, GERLIN B.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000131', 'AAC', 'TOMON, DONNA JEAN B.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000135', 'AAC', 'ANGUAY, ROGEL G.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000140', 'AAC', 'POBLACION, JOEANN A.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000141', 'AAC', 'BAYADONG, GEMMA R.', 'APP', '', NULL, 1, 'requestor'),
('AAC000143', 'AAC', 'PACA, DENNIS C.', 'GPP', '', NULL, 1, 'requestor'),
('AAC000146', 'AAC', 'CARPENTERO, ARNEL P.', 'APP', '', NULL, 1, 'requestor'),
('AAC000147', 'AAC', 'ELICAN, LIZAMAE T.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000148', 'AAC', 'CAMPOSO, NENITA S.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000151', 'AAC', 'NIERRE JR, NONI D.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000152', 'AAC', 'SORONGON, CRISTIN A.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000154', 'AAC', 'QUIETA, RUTCHIE H.', 'APP', '', NULL, 1, 'requestor'),
('AAC000155', 'AAC', 'BRAÑAIROS Sr., FELIX B.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000156', 'AAC', 'RICANOR, DONDEE S.', 'APP', '', NULL, 1, 'requestor'),
('AAC000157', 'AAC', 'FORMENTO, AREL P.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000159', 'AAC', 'OROSIO, JO ANN P.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000162', 'AAC', 'RAFAL, MARICEL D.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC000164', 'AAC', 'NECOR, FERDIE N.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000165', 'AAC', 'ANGELES, LEO G.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000166', 'AAC', 'RUFINO, VANESSA C.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000168', 'AAC', 'PURGATORIO, RALPH L.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000171', 'AAC', 'JULIA, ARNEL R.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000172', 'AAC', 'NIERRE, SHEILA L.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000174', 'AAC', 'AMPAC, MERY JEAN J.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000180', 'AAC', 'ESTIGOY, VIOLETA R.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000190', 'AAC', 'GUADALQUIVER, RAZEL P.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000196', 'AAC', 'MANDANI, ANAMARIE C.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000199', 'AAC', 'OREDA, JESER D.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000200', 'AAC', 'GALBO, ENRELEN B.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000204', 'AAC', 'FERMATO, JENNELYN A.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000205', 'AAC', 'SASTRELLAS, RICHARD B.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000207', 'AAC', 'MELENDRES, JUNREY C.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000208', 'AAC', 'MEDIANA, WILMA A.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000212', 'AAC', 'LEGUIP, JOSEPHINE R.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000213', 'AAC', 'DE ASIS, CHERRYL GRACE S.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000218', 'AAC', 'MALUBAY, ROSELYN M.', 'APP', '', NULL, 1, 'requestor'),
('AAC000220', 'AAC', 'PAUNILLAN, AIDA A.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000222', 'AAC', 'LOMOCSO JR., ARTURO S.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000226', 'AAC', 'RETOBADO, ALICE A.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000230', 'AAC', 'BERNARDO, ERIKA MARI G.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000231', 'AAC', 'PALATI, IVAN A.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000232', 'AAC', 'GUTIERREZ, MARY JOY R.', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC000235', 'AAC', 'DELCANO, JEONYLEN C.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000237', 'AAC', 'MACEDA, JOSIE MAR R.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000238', 'AAC', 'MARTINEZ JR., HILARIO L.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000240', 'AAC', 'PANTILGAN, ALLYN P.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000242', 'AAC', 'CASCABEL, RISSYLD R.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000245', 'AAC', 'LUMANAO, ERWIN B.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000247', 'AAC', 'ABLANIDA, JAYMAR R.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000251', 'AAC', 'ENDINO, BENJIE N.', 'APP', '', NULL, 1, 'requestor'),
('AAC000254', 'AAC', 'CAMEROS, MARIBEL A.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000256', 'AAC', 'MELICOR, MISHELYN R.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000277', 'AAC', 'BAHINTING, ARGIE A.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000278', 'AAC', 'CARBONERA, REDINA B.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000279', 'AAC', 'DOMINGUEZ, MIGUEL RENE A.', 'G & A', '', NULL, 1, 'requestor'),
('AAC000280', 'AAC', 'JORE, MARICYL T.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000281', 'AAC', 'GONZAGA, JEROME A.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000286', 'AAC', 'DIZON JR, FEDERICO B.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000291', 'AAC', 'OROÑGAN, ELAINE JANE D.', 'INFORMATION TECHNOLOGY ', '', NULL, 1, 'requestor'),
('AAC000296', 'AAC', 'POLANCOS JR, ARTURO P.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000298', 'AAC', 'DAQUIZ, RUEL T.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000302', 'AAC', 'NIERRE, PAUL D.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000303', 'AAC', 'MALINAO, CARLOW C.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000305', 'AAC', 'GARCIANO, JANCRIS E.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000307', 'AAC', 'DEAROS, RANDY A.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000310', 'AAC', 'ENRIQUEZ, ALDWIN R.', 'INFORMATION TECHNOLOGY ', '', NULL, 1, 'requestor'),
('AAC000316', 'AAC', 'CASINTO, LOIS L.', 'INFORMATION TECHNOLOGY ', '', NULL, 1, 'requestor'),
('AAC000317', 'AAC', 'CASIDSID, SALVADOR R.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000318', 'AAC', 'HONGOY, ESTELITO B.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000322', 'AAC', 'MANGUILIMUTAN, MARVIN A.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000328', 'AAC', 'RONULO, LESTER S.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000330', 'AAC', 'FERMATO, AL SHERVIN D.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000333', 'AAC', 'DELA TORRE, RICKY E.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000336', 'AAC', 'AYOP, LADY MARIE C.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000337', 'AAC', 'GERONAGA, JHOER T.', 'APP', '', NULL, 1, 'requestor'),
('AAC000339', 'AAC', 'SORIANO, JOEL G.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000347', 'AAC', 'CADORNA, RAYMUNDO C.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000350', 'AAC', 'GERONAGA, JENNIFER L.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000351', 'AAC', 'DAJAO, LIGAYA G.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000352', 'AAC', 'PARADIANG, RALPH JAY F.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000354', 'AAC', 'GERALDO, RIZA A.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000363', 'AAC', 'MAKALWA, ALDRIN V.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000365', 'AAC', 'SUMANTING, MICHAEL JADE U.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000388', 'AAC', 'CABILLA, MAY A.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000390', 'AAC', 'CADUNGOG, DIOSCORO O.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000391', 'AAC', 'MILLAN, FRANCIS A.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000393', 'AAC', 'MOSQUERA, ISRAEL M.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000394', 'AAC', 'MIANA, ARLENE E.', 'APP', '', NULL, 1, 'requestor'),
('AAC000395', 'AAC', 'MANATAD, VINCENT ANGELO M.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000400', 'AAC', 'BACARISAS, QUINT ANTHONY L.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000402', 'AAC', 'IGNACIO, ROGEL B.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000403', 'AAC', 'PEDRANO, CRISTINE MARIZ M.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000404', 'AAC', 'BELLEZA, RIA G.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000405', 'AAC', 'CHUA, CLAIRE B.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000416', 'AAC', 'ESTRELLA, DENBERT C.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000417', 'AAC', 'PAGAY, JIGS BRYAN V.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000418', 'AAC', 'MORAL, JINTCHELLE E.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000426', 'AAC', 'ERIGBUAGAS, KAREN C.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000427', 'AAC', 'NIQUIT, MARY JOY C.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000428', 'AAC', 'CLAPANO, EMELIE L.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000432', 'AAC', 'ALOWA, ALFONSO A.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000434', 'AAC', 'BUENAFE, CYNDREX M.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000436', 'AAC', 'BAYLOSIS, ROSALIE V.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000444', 'AAC', 'CELIS, JERAN MAI T.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000457', 'AAC', 'ALCANTARA, ANGINETTE T.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC000459', 'AAC', 'TEVES, MARY JOY T.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000461', 'AAC', 'APELINGA, REZEL JOY A.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000463', 'AAC', 'BOLO, DHEGI M.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000464', 'AAC', 'GARGANTIEL, LORENZO S.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000465', 'AAC', 'ABDULRADZAK, ANGELICA L.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000469', 'AAC', 'MALIGON, JESAH A.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000473', 'AAC', 'CATIPAY, FRANCISCO V.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000475', 'AAC', 'PAGAY, CHARLIE S.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000476', 'AAC', 'PLANIA, WILLIE C.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000477', 'AAC', 'CALLO, DIONESIO E.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000478', 'AAC', 'JOPSON, DOMINADOR P.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000479', 'AAC', 'ALMARIO, MARJUN S.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC000483', 'AAC', 'TORREVERDE, CHARLES T.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000485', 'AAC', 'NAVARRO, HARRYBREN K.', 'INFORMATION TECHNOLOGY ', '', NULL, 1, 'requestor'),
('AAC000489', 'AAC', 'TIBAY, DONALD PAUL E.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000490', 'AAC', 'ALINSUB, RYAN F.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000492', 'AAC', 'VARGAS, FRANCIS JOHN D.', 'INFORMATION TECHNOLOGY ', '', NULL, 1, 'requestor'),
('AAC000495', 'AAC', 'MANRIQUE, REXAN JAY S.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000497', 'AAC', 'GALAN, ASTRID KAYE T.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000499', 'AAC', 'DALIGDIG, MARGIELYN T.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000502', 'AAC', 'HIBUNE, WARREN R.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000506', 'AAC', 'QUIMSON, SHAIRA JAY A.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000507', 'AAC', 'QUIMA, GENEVIEVE ROCEL D.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000508', 'AAC', 'EUSALINA, GLADYS C.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000510', 'AAC', 'BORBON, ANDREW P.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000512', 'AAC', 'ALBELAR, RONNIE T.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000514', 'AAC', 'CALIBAYAN, ROLLY V.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000515', 'AAC', 'DAVID, RONNIE M.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000517', 'AAC', 'MORANO, ANTONIO D.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000518', 'AAC', 'PANTOJAN, CESARIO A.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000519', 'AAC', 'MAH, MYCA S.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000534', 'AAC', 'DY, ELVIS J.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC000538', 'AAC', 'PACARAT, EDGARDO G.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000541', 'AAC', 'GERODIAS, RONALDO V.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000542', 'AAC', 'FORROSUELO, RENANTE C.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000544', 'AAC', 'BACUS, FRANCISCO L.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000545', 'AAC', 'BACUS, JOEL M.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000546', 'AAC', 'MANONGSONG, JESABEL C.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000550', 'AAC', 'JESIM, FREDDIE C.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000554', 'AAC', 'PAGAY, RUTCHEL G.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000555', 'AAC', 'BALALA, VIA NICA C.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000559', 'AAC', 'PALAJE, ANA O.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000563', 'AAC', 'ALCAZARIN, JOHNDRIL H.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000613', 'AAC', 'DELANTES, CHRISTOPHER A.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000642', 'AAC', 'VILLAMERO, ALLAN H.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000659', 'AAC', 'SENDO, KENN P.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000691', 'AAC', 'MARIGON, SHIELA MAY E.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000715', 'AAC', 'POLINAR, MARTIN T.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000780', 'AAC', 'GAJUSTA, MILKY E.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC000791', 'AAC', 'INGKONG, MARLON G.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000804', 'AAC', 'CANTIL, EDISON B.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC000805', 'AAC', 'CORDERO, LEO PAOLO D.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000806', 'AAC', 'CABANTE, CUERMY L.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000810', 'AAC', 'ENOPIA, KRISTINE DAWN M.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000811', 'AAC', 'REGINO, KIMBERLIE MARIE L.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000812', 'AAC', 'TABANAO, VANGIELYN A.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000813', 'AAC', 'AMOR, KAREN MAE L.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000814', 'AAC', 'JUAREZ, ROMMELINA C.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000815', 'AAC', 'CABANTE, DONNY L.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000821', 'AAC', 'ARAÑA, SHIENA C.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000822', 'AAC', 'RICHARDS, MICHAEL O.', 'RPP', '', NULL, 1, 'requestor'),
('AAC000830', 'AAC', 'ARTILLERO, RUTH A.', 'RPP', '', NULL, 1, 'requestor'),
('AAC000831', 'AAC', 'CAMEROS, CHRISTIAN A.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000841', 'AAC', 'CAITUM, RYAN JOHN S.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000845', 'AAC', 'TINAYA, GLENN NIERRA J.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC000847', 'AAC', 'PALAPAS, LORENA T.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000853', 'AAC', 'GARDOSE, CARTHER D.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC000855', 'AAC', 'BARBERO, JEREMY ARNOLD B.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000856', 'AAC', 'ARIZ, IZA GRACE F.', 'RPP', '', NULL, 1, 'requestor'),
('AAC000857', 'AAC', 'DIAMANTE, MARY ANN Y.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000858', 'AAC', 'TUYAC, JANMARIE L.', 'APP', '', NULL, 1, 'requestor'),
('AAC000859', 'AAC', 'SECOYA, DARYL O.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000863', 'AAC', 'SOLEDAD, JUNISA O.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC000870', 'AAC', 'ABELLA, JEFFREY L.', 'APP', '', NULL, 1, 'requestor'),
('AAC000871', 'AAC', 'AGOO, RANDY O.', 'APP', '', NULL, 1, 'requestor'),
('AAC000872', 'AAC', 'ALBARANDO, JOEY REY T.', 'APP', '', NULL, 1, 'requestor'),
('AAC000873', 'AAC', 'ALBARANDO, ROWENA T.', 'APP', '', NULL, 1, 'requestor'),
('AAC000874', 'AAC', 'AZURO, NILA F.', 'RPP', '', NULL, 1, 'requestor'),
('AAC000875', 'AAC', 'BALONGO, FAITH B.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000876', 'AAC', 'BERGADO, FANNY V.', 'APP', '', NULL, 1, 'requestor'),
('AAC000877', 'AAC', 'CUBERO, ELMARIE JANE B.', 'APP', '', NULL, 1, 'requestor'),
('AAC000878', 'AAC', 'CALO, RICHARD T.', 'APP', '', NULL, 1, 'requestor'),
('AAC000879', 'AAC', 'CASTILLO, JO AYESSA S.', 'APP', '', NULL, 1, 'requestor'),
('AAC000880', 'AAC', 'CATUBAY, LETECIA M.', 'APP', '', NULL, 1, 'requestor'),
('AAC000881', 'AAC', 'CORDOVA, DARLYN B.', 'APP', '', NULL, 1, 'requestor'),
('AAC000882', 'AAC', 'DAVID, LEONORA C.', 'APP', '', NULL, 1, 'requestor'),
('AAC000883', 'AAC', 'DELA CRUZ, RONALD S.', 'RPP', '', NULL, 1, 'requestor'),
('AAC000885', 'AAC', 'DIALDE, ISIDRO J.', 'APP', '', NULL, 1, 'requestor'),
('AAC000886', 'AAC', 'ELEVADO, NARCISO S.', 'APP', '', NULL, 1, 'requestor'),
('AAC000887', 'AAC', 'ELEVADO, OLIVER S.', 'APP', '', NULL, 1, 'requestor'),
('AAC000888', 'AAC', 'GAOIRAN, LYN MAR M.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000889', 'AAC', 'GASCO, JAKE STEPHEN R.', 'APP', '', NULL, 1, 'requestor'),
('AAC000891', 'AAC', 'JABILLES, RUSSEL GLENN Q.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000892', 'AAC', 'LALISAN, RODEL P.', 'APP', '', NULL, 1, 'requestor'),
('AAC000893', 'AAC', 'LARAÑO, MARY JEAN F.', 'APP', '', NULL, 1, 'requestor'),
('AAC000894', 'AAC', 'MONTES, ANALYN R.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000897', 'AAC', 'PRESBITERO, FLORA MAE T.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000898', 'AAC', 'RELABO, BOMBIE A.', 'APP', '', NULL, 1, 'requestor'),
('AAC000899', 'AAC', 'TE, JESSA R.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000900', 'AAC', 'RIVERA, JESIE M.', 'PPP', '', NULL, 1, 'requestor'),
('AAC000901', 'AAC', 'ROMERO, MARIA CORAZON M.', 'GPP', '', NULL, 1, 'requestor'),
('AAC000902', 'AAC', 'SENIT, ANIE B.', 'APP', '', NULL, 1, 'requestor'),
('AAC000906', 'AAC', 'TORION, IAN MARK C.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000907', 'AAC', 'MOISES, JOHN MARK N.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC000908', 'AAC', 'DELES, RONNY G.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000911', 'AAC', 'PADILLO, JIMMY C.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000919', 'AAC', 'BUCAYAN, MARGIE LOU U.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000923', 'AAC', 'CUARESMA, JONREL L.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC000925', 'AAC', 'SALES, JERRY P.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000927', 'AAC', 'ROYO, IVY GRACE M.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000929', 'AAC', 'QUINQUITO, GENALYN U.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC000931', 'AAC', 'ASILO, NERWIN S.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC000932', 'AAC', 'BATUA, DALTON S.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC000934', 'AAC', 'BUNGHANOY, APRIL CARRY M.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000935', 'AAC', 'BAYNOSA, CAMELO M.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000936', 'AAC', 'DERUCA, DIANNE SHANE R.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC000937', 'AAC', 'AGBAYANI, GELLIE R.', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC000938', 'AAC', 'JACO, MARY ROSE G.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000940', 'AAC', 'GENEROSO, JURIM S.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC000949', 'AAC', 'DELANTES, JERYL M.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC000955', 'AAC', 'FACIOL, FABIANO D.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000956', 'AAC', 'BAJA, DANNY G.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000958', 'AAC', 'AGUDO, SARAH MAE C.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000961', 'AAC', 'ALINSUB, CHARLES JUSTIN A.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC000990', 'AAC', 'ABEQUIBEL, SARAH JANE L.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC000999', 'AAC', 'Jess Vitualla', 'INFORMATION TECHNOLOGY ', 'jessvitualla@gmail.com', '$2y$10$22B/0RUgtNM7LAT2F43chuFWMwUGD4opTqN9lwqvgttRiIzx0uB0G', 0, 'requestor'),
('AAC001012', 'AAC', 'ALESNA, MARIEL P.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC001013', 'AAC', 'ALESNA, JOLINA P.', 'APP', '', NULL, 1, 'requestor'),
('AAC001041', 'AAC', 'ANCHETA, ANALIE O.', 'APP', '', NULL, 1, 'requestor'),
('AAC001059', 'AAC', 'ARAQUE, JESON A.', 'RPP', '', NULL, 1, 'requestor'),
('AAC001063', 'AAC', 'TRINIDAD, PINKY A.', 'RPP', '', NULL, 1, 'requestor'),
('AAC001066', 'AAC', 'ARIZ, SARAH JANE F.', 'RPP', '', NULL, 1, 'requestor'),
('AAC001091', 'AAC', 'BALASABAS, MELVIN Q.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC001102', 'AAC', 'BANAY, MICHAEL G.', 'APP', '', NULL, 1, 'requestor'),
('AAC001120', 'AAC', 'BAYRON, DENISE MARC L.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC001127', 'AAC', 'BENDIRO, ROCELYN B.', 'RPP', '', NULL, 1, 'requestor'),
('AAC001190', 'AAC', 'CALO, LOIDA G.', 'APP', '', NULL, 1, 'requestor'),
('AAC001195', 'AAC', 'CAMINGAW, LINDA MAE L.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC001280', 'AAC', 'DELES, GERALDINE B.', 'PPP', '', NULL, 1, 'requestor'),
('AAC001301', 'AAC', 'DUASO, RICHARD C.', 'APP', '', NULL, 1, 'requestor'),
('AAC001320', 'AAC', 'ENOT, DONDON L.', 'APP', '', NULL, 1, 'requestor'),
('AAC001322', 'AAC', 'ERIBAL, DONNA C.', 'RPP', '', NULL, 1, 'requestor'),
('AAC001336', 'AAC', 'FALLER, EDWARD B.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC001351', 'AAC', 'FUENTES, JERALD M.', 'APP', '', NULL, 1, 'requestor'),
('AAC001373', 'AAC', 'GICA, JESSIE V.', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC001377', 'AAC', 'GINOSOLANGO, ARNIEL G.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC001382', 'AAC', 'GRANADA, IAN L.', 'GPP', '', NULL, 1, 'requestor'),
('AAC001391', 'AAC', 'GUERRA, RAYAN A.', 'RPP', '', NULL, 1, 'requestor'),
('AAC001394', 'AAC', 'GUTIERREZ, REGIE B.', 'RPP', '', NULL, 1, 'requestor'),
('AAC001425', 'AAC', 'LACERA SR, ROLLY S.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC001454', 'AAC', 'LECITA, JEROME DOMINIC G.', 'APP', '', NULL, 1, 'requestor'),
('AAC001460', 'AAC', 'LEQUINA JR., NUMERIANO R.', 'APP', '', NULL, 1, 'requestor'),
('AAC001484', 'AAC', 'MAGNO, REY A.', 'APP', '', NULL, 1, 'requestor'),
('AAC001524', 'AAC', 'MATURA, EFRENIEL L.', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC001578', 'AAC', 'OCTAVIO, MERRY ROSE E.', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC001594', 'AAC', 'OROLA, FRANZYN GAIL U.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC001625', 'AAC', 'PARAISO, LUCITA L.', 'GPP', '', NULL, 1, 'requestor'),
('AAC001712', 'AAC', 'SACEDOR, ROSELA C.', 'APP', '', NULL, 1, 'requestor'),
('AAC001716', 'AAC', 'SALAAN, KRISTOFFER SON R.', 'APP', '', NULL, 1, 'requestor'),
('AAC001722', 'AAC', 'SALILI, ELMER C.', 'APP', '', NULL, 1, 'requestor'),
('AAC001732', 'AAC', 'SANTES JR, CLEMENTE B.', 'APP', '', NULL, 1, 'requestor'),
('AAC001837', 'AAC', 'GRANADA, KIARA JEAN M.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC001839', 'AAC', 'OPALLA, GIVEL M.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC001841', 'AAC', 'ONG, JOHN LORENZO A.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC001854', 'AAC', 'VILBAR, ROBERT T.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC001855', 'AAC', 'RESANE, JONIL P.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC001856', 'AAC', 'BAYNOSA, ANDRO J.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC001859', 'AAC', 'BELLINGAN, ROSE MARIE E.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC001866', 'AAC', 'ROA, AINA JEAN E.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC001867', 'AAC', 'APOLINAR, REY D.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC001881', 'AAC', 'PELAEZ, DENNIS J.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC001882', 'AAC', 'GARBO, PAQUITO NINO P.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC001886', 'AAC', 'BUENO, AL JANE S.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC001894', 'AAC', 'MUSICO, MELANIE D.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC001901', 'AAC', 'NIEVES, NILCARJUN B.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC001902', 'AAC', 'CANDELOSA, PONCIANO S.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC001904', 'AAC', 'PATUNOG, ELGINE JOHN .', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC001905', 'AAC', 'TRAJE, LEMUEL J.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC001906', 'AAC', 'SALIK, DEVINE GRACE P.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC001923', 'AAC', 'CALAYON, CRIEZAVELLA U.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC001939', 'AAC', 'TANZA, JEANELYN A.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC001949', 'AAC', 'MANGGARON, MARK JAY T.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC001955', 'AAC', 'PADLA, JANJAN F.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC001957', 'AAC', 'CORVERA, MARVIN BLESS P.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC001959', 'AAC', 'AQUINO, LORIE JOHN T.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC001965', 'AAC', 'PIODOS, MIKEE R.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC001966', 'AAC', 'PANES, JASMINE O.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC001967', 'AAC', 'SALIGAN, ABDULRAHMAN B.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC001968', 'AAC', 'SUMALINOG, ARCHIE G.', 'SEACAGE', '', NULL, 1, 'requestor'),
('AAC001971', 'AAC', 'YANGAN, ROBERT P.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC001972', 'AAC', 'MANRIAL, RENE M.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC001974', 'AAC', 'BARO, FELIMON A.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC001975', 'AAC', 'MANALILI, JAMES M.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC001976', 'AAC', 'PARDILLO, REYDENE E.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC001979', 'AAC', 'GRAMATICA, CRESITO B.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC001980', 'AAC', 'ELTAGONDE, PAULINE P.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC001983', 'AAC', 'TALAUGON, JOVELYN A.', 'GPP', '', NULL, 1, 'requestor'),
('AAC001984', 'AAC', 'MAGALLANES, NOE B.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC001985', 'AAC', 'CULANAG, JAMES E.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC001986', 'AAC', 'OLINO, ASHLEY C.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC001993', 'AAC', 'ANTIPUESTO, ANGEL C.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC001995', 'AAC', 'VILLARIZA, VERONICKSON S.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC001996', 'AAC', 'MACEREN, GABRIEL R.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC001998', 'AAC', 'ERIBAL, GARRY L.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC001999', 'AAC', 'EJORANGO, ERWIN B.', 'INFORMATION TECHNOLOGY ', '', NULL, 1, 'requestor'),
('AAC002000', 'AAC', 'AGAS, TUESDAY JANE A.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002002', 'AAC', 'ARTAJO, DANIEL I.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002003', 'AAC', 'AMLON, JESSALVE REGINA T.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002004', 'AAC', 'OLANDRIA, ELMER A.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC002006', 'AAC', 'GUARDE, KIMBERLY S.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC002009', 'AAC', 'LARA, HARVEY L.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC002010', 'AAC', 'CARIGAY, MARIO S.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002011', 'AAC', 'ROJAS, LIMUEL A.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002012', 'AAC', 'GUTIERREZ, BERNIE A.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002013', 'AAC', 'CABANLIT, MARTA ANZUARA M.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC002017', 'AAC', 'DUQUEZA, LESTER ACE D.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002018', 'AAC', 'LASALITA, KENCH P.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002019', 'AAC', 'CANTALEJO, MARY JANE B.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC002020', 'AAC', 'CAMACHO, MARLON A.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC002022', 'AAC', 'GAOIRAN, LHEMAR L.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002023', 'AAC', 'GABILAGON, JERRY F.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC002026', 'AAC', 'DECREPITO, ROLLIE C.', 'APP', '', NULL, 1, 'requestor'),
('AAC002027', 'AAC', 'BALANDAN, KENNITH G.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002030', 'AAC', 'CABUSLAY, RONEL N.', 'PPP', '', NULL, 1, 'requestor'),
('AAC002039', 'AAC', 'FLORES, JOYCE ANN G.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002055', 'AAC', 'PARAS, OLIVER JOHN B.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002057', 'AAC', 'ALVIOR, JAMELA B.', 'APP', '', NULL, 1, 'requestor'),
('AAC002061', 'AAC', 'TSANG, YIN ROGER M.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002064', 'AAC', 'OMONGOS, DEANEM JEFF B.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002079', 'AAC', 'BARANDA, RICHELLE E.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002081', 'AAC', 'MARQUEZ, MARAH A.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002082', 'AAC', 'BALOLONG, JOLEMIE E.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002084', 'AAC', 'DIONIO, JANET F.', 'APP', '', NULL, 1, 'requestor'),
('AAC002086', 'AAC', 'BAGUIO, CHELSEA BELLE D.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002088', 'AAC', 'ENOT, JUDYANN B.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002094', 'AAC', 'VALDEZ, MANNY A.', 'RPP', '', NULL, 1, 'requestor'),
('AAC002104', 'AAC', 'VALDEPEÑAS, MARY QUEEN P.', 'PPP', '', NULL, 1, 'requestor'),
('AAC002107', 'AAC', 'DORADO, FRANCIS C.', 'RPP', '', NULL, 1, 'requestor'),
('AAC002123', 'AAC', 'HOBAYAN, BILLYMAR C.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002124', 'AAC', 'WONG, FEBBIE ANN D.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002125', 'AAC', 'MELENDRES, CRISTINE JOY V.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002128', 'AAC', 'ORBIGOSO, CATTEE L.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002160', 'AAC', 'SON, DIVINA .', 'APP', '', NULL, 1, 'requestor'),
('AAC002163', 'AAC', 'SALAZAR, NAZARIO M.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC002166', 'AAC', 'ADOLFO, KERLENE ARIANNE J.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002167', 'AAC', 'JOAQUIN, LEOVELYN S.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002168', 'AAC', 'MACADO, BONALYN G.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002169', 'AAC', 'ALGER, CHRISTOHER RHETT A.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002171', 'AAC', 'TABLADA, PRINCES KEATH .', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002172', 'AAC', 'MANTO, JEZA M.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002173', 'AAC', 'DELARA, JORDAN DAVE A.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002174', 'AAC', 'DEL ROSARIO, KWIN CYBIL P.', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC002176', 'AAC', 'PARREÑAS, RAYMOND C.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002182', 'AAC', 'PELEGRO, REMROSE D.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002193', 'AAC', 'ANCHETA, ROSEMARIE T.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002194', 'AAC', 'BAGUIO, GIAN CARLO J.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002196', 'AAC', 'PALMA, BRIDGET GWYN B.', 'APP', '', NULL, 1, 'requestor'),
('AAC002215', 'AAC', 'OBEÑITA, APRIL BOY E.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002216', 'AAC', 'MENDEZ, ALLAN E.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002218', 'AAC', 'REYES, KEVEN CLENN B.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002228', 'AAC', 'GASATAN, CYNTHIA M.', 'GPP', '', NULL, 1, 'requestor'),
('AAC002229', 'AAC', 'BARSALOTE, AUDIE T.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002230', 'AAC', 'SABILLO, IRISH O.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002235', 'AAC', 'YUSAL, MICHEL M.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002236', 'AAC', 'FUENTES, BEATRIZ OLGA B.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002238', 'AAC', 'TULO, RACKY C.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002240', 'AAC', 'LIBAWAN, RONEL B.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002241', 'AAC', 'VEQUISO, RENE V.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002242', 'AAC', 'DIAMA, MICHAEL B.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002243', 'AAC', 'CADIZ, CHRISTOPHER F.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002244', 'AAC', 'EJARES, RYAN R.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002249', 'AAC', 'SUICO, JOHN CARLO M.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002250', 'AAC', 'DIMCO, PRECIOUS MAY M.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002257', 'AAC', 'PRADO, JOHN MARCEL P.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002258', 'AAC', 'BALAGTAS, MARLON J.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002262', 'AAC', 'REVILLA, HARRY JOHN M.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002265', 'AAC', 'ERES, BRANDON JAY .', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC002267', 'AAC', 'RAMOS, ARVIN JAN A.', 'RPP', '', NULL, 1, 'requestor'),
('AAC002268', 'AAC', 'BELARMINO, SEAN MICHAEL B.', 'RPP', '', NULL, 1, 'requestor'),
('AAC002269', 'AAC', 'DINOPOL, GIEZEL S.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002271', 'AAC', 'BRAO, LEIZLY A.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002278', 'AAC', 'ABALLE, ELLAINE L.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002280', 'AAC', 'PANUGAS, JAYNE MARYELL L.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002300', 'AAC', 'INTEGRO, MARY JOY F.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002312', 'AAC', 'CLARION, CINDY A.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC002316', 'AAC', 'NAVARRO, GODJAVEN K.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002319', 'AAC', 'ALESNA, MARIECAR P.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002321', 'AAC', 'SABORNIDO, NORIMAE S.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002322', 'AAC', 'SERVAN, REGEN V.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002334', 'AAC', 'NARCISO, RISSAH S.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002335', 'AAC', 'LARAÑO, ESEM JANE C.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002347', 'AAC', 'DELGADO, AGNES I.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002353', 'AAC', 'SIMTIM, DAXNELLE P.', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC002355', 'AAC', 'RALLA, JAYVEE F.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002356', 'AAC', 'RAFAEL, CREIGHTON H.', 'APP', '', NULL, 1, 'requestor'),
('AAC002357', 'AAC', 'BATAY, RICA G.', 'RPP', '', NULL, 1, 'requestor'),
('AAC002358', 'AAC', 'NOBLE, MARY DALE .', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002360', 'AAC', 'TAN, KATE ALEXIS P.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002361', 'AAC', 'MAMALIAS, REYMARK N.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002362', 'AAC', 'MICO, PRINCE ROEL G.', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC002364', 'AAC', 'SAMONTE, TEDDY A.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002365', 'AAC', 'CAHUCOM, ARLENE C.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002366', 'AAC', 'MANATAD, MARIAN KATE M.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002367', 'AAC', 'SAYLOON, JOEMARIE A.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002368', 'AAC', 'TALISAY, ANGELYN T.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002380', 'AAC', 'OLOG, APPLE JANE B.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC002381', 'AAC', 'DANTE, RAZEL FAITH R.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002382', 'AAC', 'SOLEDAD, JANICE O.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002384', 'AAC', 'MAGALLANES, LOUIE T.', 'APP', '', NULL, 1, 'requestor'),
('AAC002390', 'AAC', 'TIBAY, JERRY E.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002391', 'AAC', 'BALANTAY, RYAN GEORGE T.', 'APP', '', NULL, 1, 'requestor'),
('AAC002392', 'AAC', 'ROCACORBA, CLEVEN M.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002393', 'AAC', 'FRONTERAS, DAISY JANE A.', 'APP', '', NULL, 1, 'requestor'),
('AAC002394', 'AAC', 'DELICANO, ARIAN S.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC002395', 'AAC', 'DIAMA, MERILYN M.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002402', 'AAC', 'VILLANUEVA, JOEL D.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002405', 'AAC', 'CABANDA, PAUL DREXLER B.', 'INFORMATION TECHNOLOGY ', '', NULL, 1, 'requestor'),
('AAC002407', 'AAC', 'ALIGATO, LUDGADE D.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002409', 'AAC', 'OLMEDO, IAN B.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002410', 'AAC', 'VILLANUEVA, ARA MAE D.', 'RPP', '', NULL, 1, 'requestor'),
('AAC002411', 'AAC', 'ANDRADA, RODEMIE V.', 'RPP', '', NULL, 1, 'requestor'),
('AAC002412', 'AAC', 'CHUA, NEMFER LUIS A.', 'OUTSOURCING & GROWERSHIP', '', NULL, 1, 'requestor'),
('AAC002413', 'AAC', 'MAGLUYAN, JENNY MAY A.', 'RPP', '', NULL, 1, 'requestor'),
('AAC002414', 'AAC', 'CELESTE, RUSSEL JAKE G.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002415', 'AAC', 'CASEÑAS, WINDY MAE G.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002416', 'AAC', 'GADIANO, DIANNA KATE S.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002417', 'AAC', 'DUMAYAO, DANICA .', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002418', 'AAC', 'ALFAFARA, JHON JHON M.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002424', 'AAC', 'SIOCO, FELVIE A.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002425', 'AAC', 'PAGONG, FLORANTE V.', 'INFORMATION TECHNOLOGY ', '', NULL, 1, 'requestor'),
('AAC002426', 'AAC', 'QUIAPO, RANNEL M.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002428', 'AAC', 'HADJIOMAR, SAMSODEN B.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002429', 'AAC', 'MAHILUM, SHINAROSE D.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002437', 'AAC', 'MALLARI, RICHARD D.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002438', 'AAC', 'BLANCO, RAYMUND PAUL F.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC002446', 'AAC', 'DUMABOC, JUN A.', 'RPP', '', NULL, 1, 'requestor'),
('AAC002447', 'AAC', 'ANDRINO, ANGIE O.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002456', 'AAC', 'LUMOGDA, SYBIL B.', 'APP', '', NULL, 1, 'requestor'),
('AAC002466', 'AAC', 'VILLARIZ, MARJORIE P.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002467', 'AAC', 'GUADALQUIVER, MICHELLE F.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002468', 'AAC', 'YATOT, JETO S.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC002478', 'AAC', 'BUCALI, MOEL L.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002479', 'AAC', 'SINAJON, KEM RAYAN R.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002481', 'AAC', 'GONZAGA, REYNALDO P.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002482', 'AAC', 'CHEE KEE, JJ L.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC002483', 'AAC', 'BARBA, CEDRICK A.', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC002484', 'AAC', 'MASALON, AUDREY DYANN P.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002485', 'AAC', 'ANGCACO, KAYLE LOU D.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC002492', 'AAC', 'DERAIN, ERICA JOYCE R.', 'APP', '', NULL, 1, 'requestor'),
('AAC002493', 'AAC', 'CACHUELA, SHANELLE M.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002494', 'AAC', 'DORONIO, IRISH JOHN L.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002496', 'AAC', 'PURISIMA, ROY M.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002497', 'AAC', 'PLANTIG, JIMUEL B.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002500', 'AAC', 'MARTIN, FLORA MAE R.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002513', 'AAC', 'BELAIS, LEAH MARIE P.', 'RESEARCH & DEVELOPMENT', '', NULL, 1, 'requestor'),
('AAC002516', 'AAC', 'DAGOHOY, BERNADETTE JANE C.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002517', 'AAC', 'POLISTICO, RUFIBOY C.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002519', 'AAC', 'GANTE, PENNY C.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002525', 'AAC', 'SABANDO, JENIDA M.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002526', 'AAC', 'LUMANTA, JAZEL V.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002527', 'AAC', 'LUCAS, RONAZEL JANE M.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002528', 'AAC', 'ESCUREL, MYLEEN E.', 'PPP', '', NULL, 1, 'requestor'),
('AAC002531', 'AAC', 'ALABA, GINA ROSE D.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002532', 'AAC', 'DIAZ, ROVIAN MARIE C.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002542', 'AAC', 'CEDO, MAY ANNE A.', 'RPP', '', NULL, 1, 'requestor'),
('AAC002543', 'AAC', 'FERNANDEZ, KRHYSTAL D.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002544', 'AAC', 'TAMPOS, ROLITO S.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002545', 'AAC', 'GADIA, MACKENRAY .', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002546', 'AAC', 'LARIOSA, GUILLERMO P.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002551', 'AAC', 'CUARESMA, KARL VINCENT D.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002552', 'AAC', 'SATOR, MARICAR C.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002553', 'AAC', 'ROSALES, CLUCHER A.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002554', 'AAC', 'ALDAY, MARLON JAMES V.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002555', 'AAC', 'PAGADUAN, MARC BRYAN A.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002556', 'AAC', 'REBLANDO, REINA MAE U.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002557', 'AAC', 'NASIBOG, APRIL JOY Q.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002558', 'AAC', 'ZABALA, RUSTOM S.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002559', 'AAC', 'CANINDO, GREGORY ALLAN P.', 'HUMAN RESOURCE & ADMIN', '', NULL, 1, 'requestor'),
('AAC002560', 'AAC', 'CATIPAY, SHIELA B.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC002563', 'AAC', 'TANDO, SERGIO C.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002570', 'AAC', 'LLAGUNO, JONELL C.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002572', 'AAC', 'HAPULAS, ROLANDO C.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002574', 'AAC', 'PARAN, RIZZA A.', 'SALES & MARKETING', '', NULL, 1, 'requestor'),
('AAC002584', 'AAC', 'ALDAMAR, VIA MAE P.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC002585', 'AAC', 'DELANTE, JEFFREY B.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002586', 'AAC', 'YUSO, ALEXIS M.', 'MATERIALS MANAGEMENT', '', NULL, 1, 'requestor'),
('AAC002597', 'AAC', 'ELICAN, APRIL MAE B.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002598', 'AAC', 'LINSANGAN, JAY M.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002599', 'AAC', 'CALONIA, MEZIEL W.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC002601', 'AAC', 'YTAC, JASON S.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002602', 'AAC', 'LIM, ROSECHELLE T.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002603', 'AAC', 'ALITRE, RIZALITO B.', 'SUPPLY CHAIN', '', NULL, 1, 'requestor'),
('AAC002604', 'AAC', 'VALETE, JIZZY V.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002605', 'AAC', 'ESEO, JHONNEL T.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002606', 'AAC', 'TUMANDAN, VEDGEM .', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002607', 'AAC', 'TRAZO, EDGAR S.', 'APP', '', NULL, 1, 'requestor'),
('AAC002608', 'AAC', 'ALABA, ROLDAN M.', 'ENGINEERING', '', NULL, 1, 'requestor'),
('AAC002611', 'AAC', 'DIONO, JUDY ANN D.', 'FINANCE', '', NULL, 1, 'requestor'),
('AAC002629', 'AAC', 'ESTRADA, LOSXEL L.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002630', 'AAC', 'BIGNOTIA, REA MAE V.', 'QA & C', '', NULL, 1, 'requestor'),
('AAC002631', 'AAC', 'COMALING, MAR JUN P.', 'GROW OUT', '', NULL, 1, 'requestor'),
('AAC002635', 'AAC', 'PALER, DAN RUZEL D.', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('AAC002637', 'AAC', 'BALLON, KEINTH JANN C.', 'SPECIAL PROJECT', '', NULL, 1, 'requestor'),
('AAC052002', 'AAC', 'TAMPUS, ALVIN A. JR.', 'INFORMATION TECHNOLOGY ', 'alvintampus3@gmail.com', '$2y$10$Npe.Q7cqh3UzM08E0D86tum5MVKI/3KNC5eL3Ar.fd2sWhcye7IXm', 0, 'requestor'),
('AAC052003', 'AAC', 'PALOMARES, CHARLES LEO H.', 'INFORMATION TECHNOLOGY ', 'charlesleohermano@gmail.com', '$2y$10$tpfZgEnWgFdzm8Todliz7eLrSDIc79jopD0f4GHSecmvueM6wI34G', 0, 'admin'),
('ALD000001', 'ALDEV', 'ALIPOON,NEIL QUIRINO', 'BANANA', '', NULL, 1, 'requestor'),
('ALD000003', 'ALDEV', 'ARONG JR.,DEMETRIO FERRAREN', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor');
INSERT INTO `employees` (`employee_id`, `company`, `employee_name`, `department`, `employee_email`, `password`, `is_temp_password`, `role`) VALUES
('ALD000006', 'ALDEV', 'SOMBILON,TRACLIO MEJIAS', 'BANANA', '', NULL, 1, 'requestor'),
('ALD000007', 'ALDEV', 'EDULOG,ELLAINE MAE SEVIOLA', 'BANANA', '', NULL, 1, 'requestor'),
('ALD000008', 'ALDEV', 'ROSARIO,ARNEL CRISTUTA', 'BANANA', '', NULL, 1, 'requestor'),
('ALD000020', 'ALDEV', 'SINGIT,EDWIN EYA', 'BANANA', '', NULL, 1, 'requestor'),
('ALD000023', 'ALDEV', 'SULIT,SHERYL SINDOL', 'ENGINEERING', '', NULL, 1, 'requestor'),
('ALD000026', 'ALDEV', 'APOSTER,KEVIN LUMANTA', 'ENGINEERING', '', NULL, 1, 'requestor'),
('ALD000031', 'ALDEV', 'MIGUEL,JULIEBEE PRIMAVERA', 'BANANA', '', NULL, 1, 'requestor'),
('ALD000039', 'ALDEV', 'FUENTES ,RENATO   YUMUL', 'AGRI MGT. INFO. SYSTEM (AMIS)', '', NULL, 1, 'requestor'),
('ALD000040', 'ALDEV', 'DANIEL,MARVIN  MANTON', 'AGRI MGT. INFO. SYSTEM (AMIS)', '', NULL, 1, 'requestor'),
('ALD000046', 'ALDEV', 'ESTORQUE ,ARCHIE  FANTILANO', 'BANANA', '', NULL, 1, 'requestor'),
('ALD000048', 'ALDEV', 'GUIROY,ANTHONY BAYO', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('ALD000050', 'ALDEV', 'VEQUILLA,ERICA JEAN DELA CRUZ', 'ENGINEERING', '', NULL, 1, 'requestor'),
('ALD000051', 'ALDEV', 'TACADAO,DOROTEO ANTIQUERA', 'ENGINEERING', '', NULL, 1, 'requestor'),
('ALD000052', 'ALDEV', 'MAGANA,RODRIGO DOMINGO', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('ARC000001', 'ARC', 'ORTIZ, JERRY BALAT', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000003', 'ARC', 'ABARIENTO, ANGELA GIRON', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000004', 'ARC', 'PRABAQUIL, PABLITO AVILA', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000008', 'ARC', 'ANIEL, JAIME BAROLO', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000010', 'ARC', 'DESOACEDO JR, TIMOTEO  RECABO', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000011', 'ARC', 'DAPITON, JOSE SEBASTIAN', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000012', 'ARC', 'JAYME, ANA LUZ SALAZAR', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000018', 'ARC', 'REGALADO, CYRUS REGALADO', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000020', 'ARC', 'POLINAR , ROLAND  VIAJANTE', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000021', 'ARC', 'MASAYA, ROBBY  JAYARI', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000022', 'ARC', 'MERIDA, REY  VEQUILLA', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000023', 'ARC', 'SOSMEÑA, JONAS  CALIXTRO', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000025', 'ARC', 'GULAY , JOSE LABIANG', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000026', 'ARC', 'ANZANO, CIPRIANO FONTILO', 'ARC - NURSERY', '', NULL, 1, 'requestor'),
('ARC000027', 'ARC', 'DAPITON, BONIFACIO SEBASTIAN', 'ARC - NURSERY', '', NULL, 1, 'requestor'),
('ARC000029', 'ARC', 'CABANA, DENNIS LONOY', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000031', 'ARC', 'FAJARDO, EARLE MOMONGAN', 'ARC Engineering', '', NULL, 1, 'requestor'),
('ARC000032', 'ARC', 'CAPALLA, LYZZA COBACHA', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000033', 'ARC', 'CABANA, GERARDO LONOY', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000034', 'ARC', 'LUYAO, DIOMEDES GOLISAO', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000035', 'ARC', 'CAMADO, MAC WILSON CRUZ', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000036', 'ARC', 'BITOY, JAYSON FABELA', 'ARC Growout', '', NULL, 1, 'requestor'),
('ARC000037', 'ARC', 'EPIFANIO, RUBEN MANGAS', 'ARC Growout', '', NULL, 1, 'requestor'),
('FH000114', 'FHI', 'CASTILLO, ALEXIS BARAL', 'FHI FryTrading-Visayas', '', NULL, 1, 'requestor'),
('FH000115', 'FHI', 'BUCOL, JAKE TRUMATA', 'FHI OM Office', '', NULL, 1, 'requestor'),
('FHI000001', 'FHI', 'HIBUNE, JONATHAN OTERO', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000002', 'FHI', 'YOUNG, RONALD UY', 'FHI OM Office', '', NULL, 1, 'requestor'),
('FHI000004', 'FHI', 'NEBRES, ROMEO FELIX', 'FHI Production', '', NULL, 1, 'requestor'),
('FHI000006', 'FHI', 'JUMAWAN, AL MIRAFLOR', 'FHI Production-Shipment', '', NULL, 1, 'requestor'),
('FHI000008', 'FHI', 'GALLARDE, JIMMEL EDEROSAS', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000013', 'FHI', 'CABADING, ROBERT CASAS', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000017', 'FHI', 'CABILADAS, CARLO DUQUEZA', 'FHI Production', '', NULL, 1, 'requestor'),
('FHI000018', 'FHI', 'CANO, CECELIO CASEÑAS', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000019', 'FHI', 'DAGALA, GODOFREDO IGNACIO', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000020', 'FHI', 'RENACIA, RENE DAPIGRAN', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000021', 'FHI', 'CARMAN, JOSE GALGO', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000023', 'FHI', 'VENUS, AIDA JUMAWAN', 'FHI Production', '', NULL, 1, 'requestor'),
('FHI000025', 'FHI', 'FULLANTE, NORBERTO MATAO', 'FHI Production-Larval Rearing (LR)', '', NULL, 1, 'requestor'),
('FHI000026', 'FHI', 'LAGARAS, ALLAN MOSQUEDA', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000027', 'FHI', 'TANTE, ESER MACABOLOS', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000028', 'FHI', 'MANRIQUE, MANUEL VIDAD', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000032', 'FHI', 'CABATANIA, JIMMY BARASBARAS', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000034', 'FHI', 'CATIG, LEONARD MILLONA', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000035', 'FHI', 'LAGARAS, ALEX MOSQUEDA', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000036', 'FHI', 'VIVA, MARCOS PACLE', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000037', 'FHI', 'VILLAMORA, GODOFREDO SARAJENA', 'FHI Production-Larval Rearing (LR)', '', NULL, 1, 'requestor'),
('FHI000040', 'FHI', 'FRANCISCO, BELTRAN HAPINAT', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000042', 'FHI', 'INGENTE, ELEUTERIO MANO-OD', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000044', 'FHI', 'SALA, SALVADOR FELISILDA', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000047', 'FHI', 'PASAYLO-ON, SANITO DERITCHO', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000052', 'FHI', 'DAYONDON, FELIX TUBURAN', 'FHI Production-Milkfish Larval(MF)', '', NULL, 1, 'requestor'),
('FHI000053', 'FHI', 'PALOMA, JEMLANE PETOGO', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000055', 'FHI', 'CADORNA, MANUEL RICAFORT', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000056', 'FHI', 'EMBOLTORIO, ROBERT ESCORIDO', 'FHI Production-Milkfish Larval(MF)', '', NULL, 1, 'requestor'),
('FHI000057', 'FHI', 'MANRIQUE, RONALD ARGUELLES', 'FHI Production-Larval Rearing (LR)', '', NULL, 1, 'requestor'),
('FHI000059', 'FHI', 'RIAS, ROLANDO ADLAWON', 'FHI Production-Larval Rearing (LR)', '', NULL, 1, 'requestor'),
('FHI000061', 'FHI', 'ADAYA, ROLDAN SAGARINO', 'FHI FryTrading-Mindanao', '', NULL, 1, 'requestor'),
('FHI000062', 'FHI', 'NUÑEZA, SEGUNDITO LONZAGA', 'FHI FryTrading-Visayas', '', NULL, 1, 'requestor'),
('FHI000063', 'FHI', 'BOCAYA, RENATO BONGON', 'SELLING & MARKETING DEPARTMENT', '', NULL, 1, 'requestor'),
('FHI000064', 'FHI', 'BONTO, ALAN', 'SELLING & MARKETING DEPARTMENT', '', NULL, 1, 'requestor'),
('FHI000065', 'FHI', 'CERVANTES, CRESENCIANO', 'SELLING & MARKETING DEPARTMENT', '', NULL, 1, 'requestor'),
('FHI000066', 'FHI', 'CONDE, ARDEE ONRUBIA', 'SELLING & MARKETING DEPARTMENT', '', NULL, 1, 'requestor'),
('FHI000067', 'FHI', 'GAA, JOSEPH', 'SELLING & MARKETING DEPARTMENT', '', NULL, 1, 'requestor'),
('FHI000070', 'FHI', 'VINCOY, CRESENCIO', 'SELLING & MARKETING DEPARTMENT', '', NULL, 1, 'requestor'),
('FHI000072', 'FHI', 'CANCINO, CHRYSTAL', 'SELLING & MARKETING DEPARTMENT', '', NULL, 1, 'requestor'),
('FHI000073', 'FHI', 'BARTOLOME, JASON', 'SELLING & MARKETING DEPARTMENT', '', NULL, 1, 'requestor'),
('FHI000074', 'FHI', 'BALLERA, RIO SUMAGAYSAY', 'FHI Production', '', NULL, 1, 'requestor'),
('FHI000075', 'FHI', 'KOMALING, MIGUEL LAUDA', 'FHI Production-Shipment', '', NULL, 1, 'requestor'),
('FHI000076', 'FHI', 'TRAYA, NOLITO GETIGAN', 'FHI Production-Harvest', '', NULL, 1, 'requestor'),
('FHI000077', 'FHI', 'MAGBANUA, ROSALINA AGUIRRE', 'FHI FryTrading-Visayas', '', NULL, 1, 'requestor'),
('FHI000079', 'FHI', 'RAMOS, RODEL DAYONDON', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000080', 'FHI', 'FERNANDEZ, MARK CORTES', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000081', 'FHI', 'APITONG, RHOEL DIGNADICE', 'FHI FryTrading-Visayas', '', NULL, 1, 'requestor'),
('FHI000082', 'FHI', 'PENDON, JUNE BELORIA', 'FHI FryTrading-Mindanao', '', NULL, 1, 'requestor'),
('FHI000083', 'FHI', 'RUFINO, ROMULO SICAD', 'FHI FryTrading-Mindanao', '', NULL, 1, 'requestor'),
('FHI000084', 'FHI', 'FRANCISCO, REX TIANSON', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000086', 'FHI', 'VILLARIAS, SAMUEL PATENTE', 'FHI FryTrading-Mindanao', '', NULL, 1, 'requestor'),
('FHI000088', 'FHI', 'PAG-ONG, JOMAR DAYONDON', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000090', 'FHI', 'ABENDAN, AZUR ALFERES', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000092', 'FHI', 'BARANGAN, CHERRY MAE SENSANO', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000094', 'FHI', 'MAGHANOY, LAWRENCE', 'SELLING & MARKETING DEPARTMENT', '', NULL, 1, 'requestor'),
('FHI000095', 'FHI', 'RAMIREZ, FERNANDO', 'SELLING & MARKETING DEPARTMENT', '', NULL, 1, 'requestor'),
('FHI000096', 'FHI', 'AMANTE, FRITZ DEMOCRITO', 'FHI FryTrading-Visayas', '', NULL, 1, 'requestor'),
('FHI000098', 'FHI', 'CABASE, ARNEL FRANCO', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000099', 'FHI', 'VICENTE, KENNY DASON', 'FHI Production-Milkfish Larval(MF)', '', NULL, 1, 'requestor'),
('FHI000105', 'FHI', 'ESCUDO, MARK ANTHONY CASTILLO', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000107', 'FHI', 'BARANGAN, LLOYD CANO', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000110', 'FHI', 'CADORNA, JINKY SEBILO', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000116', 'FHI', 'LUCAS, MARLOU CARIAGA', 'FHI FryTrading-Mindanao', '', NULL, 1, 'requestor'),
('FHI000118', 'FHI', 'COLANO, MELBA YSON', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000120', 'FHI', 'DEL MONTE, ROSEMARIE JANE CASUPANG', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000121', 'FHI', 'BANCAERIN, REYNANTE SABAN', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000122', 'FHI', 'HIBUNE, ALVIN OTERO', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000123', 'FHI', 'PLAZA, EDGARDO ALBAO', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000124', 'FHI', 'TANOY, BENJIE LARIOSA', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000125', 'FHI', 'DOCE, IVAN BESA', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000126', 'FHI', 'ESPIRITUOSO, JOWIE ASUNCION', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000127', 'FHI', 'ALDEMITA, GENELYN CUDOG', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000128', 'FHI', 'TICAO, ISIDORA PALOMA', 'FHI OM Office', '', NULL, 1, 'requestor'),
('FHI000129', 'FHI', 'BANCAERIN, BERNIE SABAN', 'FHI Production-Larval Rearing (LR)', '', NULL, 1, 'requestor'),
('FHI000130', 'FHI', 'VELASCO, RAY MIANO', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000131', 'FHI', 'FUENTEVILLA, JEROME MARCIAL', 'FHI Production-Larval Rearing (LR)', '', NULL, 1, 'requestor'),
('FHI000132', 'FHI', 'TAMPUS, ADONIS BOHOLTS', 'FHI Production-Larval Rearing (LR)', '', NULL, 1, 'requestor'),
('FHI000133', 'FHI', 'HIBUNE, MERVIN OTERO', 'FHI Production-Larval Rearing (LR)', '', NULL, 1, 'requestor'),
('FHI000135', 'FHI', 'PULVERA, ALFREDO BENZAL', 'FHI Production-Fingerling Rearing (FR)', '', NULL, 1, 'requestor'),
('FHI000136', 'FHI', 'COLANO, ALEX ARSOLA', 'FHI Production-Fingerling Rearing (FR)', '', NULL, 1, 'requestor'),
('FHI000138', 'FHI', 'PIOQUINTO, HEZEL ALTAMARINO', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000140', 'FHI', 'ALESNA, JIMMY VELARDE', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000141', 'FHI', 'JUGARAP, DENNIS ABRIO', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000142', 'FHI', 'CUDOG, ERNESTO GALANAGA', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000143', 'FHI', 'SABANATE, ROLAND CARDENTE', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000144', 'FHI', 'ALVERO, EMMANUEL REYES', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000145', 'FHI', 'CABALO, JUN MORSAL', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000146', 'FHI', 'ARSULA, LUIS CUARESMA', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000147', 'FHI', 'MIOLE, JOEFREY CONSAD', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000148', 'FHI', 'FRANCISCO, CROSALDO ZONIO', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000149', 'FHI', 'YONSON, ROGELIO CORDOVA', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000150', 'FHI', 'RODRIGUEZ, GILBERT UBAS', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000151', 'FHI', 'VELASCO, ARWIN MIANO', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000152', 'FHI', 'MIRAFUENTES, RODEL  CANETE', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000153', 'FHI', 'EGUINTO, JEFFREY ONDONG', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000154', 'FHI', 'MORENO, JERALD INTONG', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000155', 'FHI', 'SIRLANA, JULIE CUIZON', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000156', 'FHI', 'GUNDAY, HENRY PETOGO', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000157', 'FHI', 'DELA PAZ, DAVID  MORENO', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000158', 'FHI', 'ABENDAN, ANDREO  ALFEREZ', 'FHI Production-Larval Rearing (LR)', '', NULL, 1, 'requestor'),
('FHI000159', 'FHI', 'BELINARIO, VINCENT  DAQUERA', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000162', 'FHI', 'LARA, HOWELL BANDOJO', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000163', 'FHI', 'BELLUGA , RUSSELL BOBB TORLAO', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000164', 'FHI', 'ANGELES, KEVIN  HIBUNE', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000165', 'FHI', 'WATIN, JHONRYL SAYLOON', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000166', 'FHI', 'VIVA, MARK JAYSON MESAGRANDE', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000167', 'FHI', 'NEBRES, PETER GIOVANNI OCONG', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000168', 'FHI', 'JUMAWAN, KIT JAMES DAPAR', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000169', 'FHI', 'ENDRINA, ANGELO ARGOMEDO', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000171', 'FHI', 'ASUTILLA, ALBERTO SISTONA', 'FHI-Warehouse', '', NULL, 1, 'requestor'),
('FHI000172', 'FHI', 'PEÑAFIEL, JOVENEL SEBASTIAN', 'FHI Production-Milkfish Larval(MF)', '', NULL, 1, 'requestor'),
('FHI000174', 'FHI', 'PULVERA, JONRICK BENZAL', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000176', 'FHI', 'JUNIO, MARK KEVIN SITCHON', 'FHI Production-Milkfish Larval(MF)', '', NULL, 1, 'requestor'),
('FHI000177', 'FHI', 'SECRETARIA, PRIMO LAPUT', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000178', 'FHI', 'ANDOYO JR, DANILO', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000179', 'FHI', 'EMBIADO , ELMA JUMAWAN', 'FHI OM Office', '', NULL, 1, 'requestor'),
('FHI000182', 'FHI', 'DELA CRUZ, IRHLY GERARMAN', 'FHI FryTrading-Visayas', '', NULL, 1, 'requestor'),
('FHI000184', 'FHI', 'TACRAS, RICKY  BETITA', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000185', 'FHI', 'PIODOS, JAY AR ALCANSADO', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000186', 'FHI', 'CATIPAY, FAMIE VILLAHERMOSA', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000187', 'FHI', 'ARANETA, JAIRUS LAGALCAN', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000188', 'FHI', 'ABDULRADZAK, ABDURAKMAN DESAPOR', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000189', 'FHI', 'PAELDIN, GELMAR MARAVILES', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000191', 'FHI', 'SORIANO, APRIL JHON GUTIERREZ', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000193', 'FHI', 'ENCARNACION, ARCHEL MONTECILLO', 'FHI Production', '', NULL, 1, 'requestor'),
('FHI000195', 'FHI', 'TAGALOG, FRANCISCO LANTUELE', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000199', 'FHI', 'BENDULO, JONETTE CABALISA', 'FHI Production', '', NULL, 1, 'requestor'),
('FHI000200', 'FHI', 'MEJIA, FLORAMIE RANES', 'FHI FryTrading-Mindanao', '', NULL, 1, 'requestor'),
('FHI000201', 'FHI', 'REMANDO, JEFREY OREDA', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000202', 'FHI', 'ARAMBALA, RUFO HIBAYA', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000203', 'FHI', 'REQUILME, ARTURO CARACUT', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000204', 'FHI', 'LOPEZ, ROY OCAYA', 'FHI Production-Natural Food (NF)', '', NULL, 1, 'requestor'),
('FHI000207', 'FHI', 'NAVARRO, JERALD VILLARIN', 'FHI FryTrading-Visayas', '', NULL, 1, 'requestor'),
('FHI000209', 'FHI', 'COROMPIDO, KENNETH BULANON', 'FHI FryTrading-Mindanao', '', NULL, 1, 'requestor'),
('FHI000210', 'FHI', 'CUDOG , ERWIN GALANAGA', 'FHI FryTrading-Mindanao', '', NULL, 1, 'requestor'),
('FHI000211', 'FHI', 'VERGARA, CHARLIE ASCURA', 'FHI FryTrading-Visayas', '', NULL, 1, 'requestor'),
('FHI000212', 'FHI', 'LASTIMOSO, JONARD ANDRADE', 'FHI-Warehouse', '', NULL, 1, 'requestor'),
('FHI000213', 'FHI', 'UNAJAN, JOHNY FUENTEBELLA', 'FHI Production-Milkfish Larval(MF)', '', NULL, 1, 'requestor'),
('FHI000214', 'FHI', 'ROCA, REYMOND RENACIA', 'FHI Production-Milkfish Larval(MF)', '', NULL, 1, 'requestor'),
('FHI000215', 'FHI', 'VALDEZ, JIBRIL PAULO NASSER', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000216', 'FHI', 'SARONITMAN, BUN STEPHEN LAO-LAO', 'FHI Production-Broodstock', '', NULL, 1, 'requestor'),
('FHI000217', 'FHI', 'NARDO, DONARD ARSULA', 'FHI Production-Milkfish Larval(MF)', '', NULL, 1, 'requestor'),
('FHI000218', 'FHI', 'TAPADO, GILDRED LUMAPAK', 'FHI Production-Algae', '', NULL, 1, 'requestor'),
('FHI000219', 'FHI', 'NEBRES, MARLON POLINAR', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000220', 'FHI', 'VIVA, REY ANTHONY MESAGRANDE', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000221', 'FHI', 'PALOMA, ROMEL FABROS', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000222', 'FHI', 'ARANETA, MARK JOY LAGALCAN', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000223', 'FHI', 'ADOL, JIMMY ALA', 'FHI Production-Milkfish Larval(MF)', '', NULL, 1, 'requestor'),
('FHI000224', 'FHI', 'DELA CRUZ, RUBEN', 'FHI Engineering', '', NULL, 1, 'requestor'),
('FHI000225', 'FHI', 'NARDO, LEONARD MANCAO', 'FHI Production-Milkfish Larval(MF)', '', NULL, 1, 'requestor'),
('FHI000226', 'FHI', 'GANTALAO, JOHN DAVE TACULOD', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000227', 'FHI', 'DEQUINA, JULIUS CAESAR CAGADAS', 'FHI Production-Packing House (PH)', '', NULL, 1, 'requestor'),
('FHI000228', 'FHI', 'VELASCO, RONALD  MIANO', 'FHI Engineering', '', NULL, 1, 'requestor'),
('process1', 'AAC', 'process owner', 'INFORMATION TECHNOLOGY ', 'clpalomares2003@gmail.com', '$2y$10$Exm.FkyH.IJzaQ.i2Fy0SeSGYbYVx6maLLmQJQwOdQeRSCTiPx4.q', 0, 'process_owner'),
('SAC000032', 'SACI', 'FABROA, EDUMAR ECHAVEZ', 'OFFICE OF AVP', '', NULL, 1, 'requestor'),
('SAV000002', 'ALDEV', 'DAMOLE,NELDRIN VILLANUEVA', 'BANANA', '', NULL, 1, 'requestor'),
('SAV000004', 'SAVI', 'GECOSALA,ENRIQUE CASTILLON', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000005', 'SAVI', 'PAGAY,ROLANDO TORRES', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000006', 'SAVI', 'RODRIGO,RONNIE TOLENTINO', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000008', 'SAVI', 'AYSON,JANINE PALCONITE', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000009', 'SAVI', 'BOCA,ETHEL JOY BAGUIO', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000011', 'SAVI', 'AMORES,ANTONIO SALVADOR ROMAN CHAVES', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000017', 'SAVI', 'CURAZA,ELIEZER NUEVO', 'ENGINEERING', '', NULL, 1, 'requestor'),
('SAV000027', 'SAVI', 'GUANSING,JULIUS JOHN FERENAL', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000031', 'SAVI', 'BUNZO,ELIZABETH GELLEGAN', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000032', 'SAVI', 'LAO,MAE PAGARIGAN', 'TSD', '', NULL, 1, 'requestor'),
('SAV000035', 'SAVI', 'CAÑETE,PRINCESS SARAH YORPO', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000039', 'SAVI', 'BAQUEQUE,NARCISO TRISTE', 'TSD', '', NULL, 1, 'requestor'),
('SAV000040', 'SAVI', 'VILLARUEL,JAY BASUGA', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000041', 'SAVI', 'MORANO,JEAN DOLUTAN', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000043', 'SAVI', 'BALORAN,ETHYL JOY TAER', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000044', 'SAVI', 'LACIERDA,JAMES ALDERITE', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000046', 'SAVI', 'LAO,LAWTON BONDAD', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000047', 'SAVI', 'ORTIZ,JOEMARIE PADILLA', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000048', 'SAVI', 'CUTILLAR,CHRISTOPHER VILLAMORA', 'BANANA LEAVES', '', NULL, 1, 'requestor'),
('SAV000049', 'SAVI', 'HIBUNE,LLOYD  OTERO', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000052', 'SAVI', 'MONTERO,RALITO  MIRAFLOR', 'ENGINEERING', '', NULL, 1, 'requestor'),
('SAV000053', 'SAVI', 'BASIO,NEILYN RHEA  MOLINA', 'G&A', '', NULL, 1, 'requestor'),
('SAV000055', 'SAVI', 'SUTAN,LITO MOHONG', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000104', 'SAVI', 'DELOS REYES,VANESSA DALISAY', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000105', 'SAVI', 'SOLIS,KCLYN', 'TSD', '', NULL, 1, 'requestor'),
('SAV000108', 'SAVI', 'RELLON,ROLANDO CHAVEZ', 'ENGINEERING', '', NULL, 1, 'requestor'),
('SAV000109', 'SAVI', 'CORONADO,KIRTH JOHNDY TEMPROSA', 'G&A', '', NULL, 1, 'requestor'),
('SAV000111', 'SAVI', 'EMPINADO,MELBERT PEÑARANDA', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000112', 'SAVI', 'JAMORE,JENNIFER ABOY', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000115', 'SAVI', 'BUDAY,ELOISA LYN NACION', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SAV000116', 'SAVI', 'ALINDAHAO,MICHELLE YUSORES', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SAV000117', 'SAVI', 'OLAVER,RICHARD CAFE', 'ENGINEERING', '', NULL, 1, 'requestor'),
('SAV000120', 'SAVI', 'VILLANUEVA,KEANNA PEARL QUIÑON', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SCC000003', 'SCCI', 'DE LOS REYES,IAN MARK PARACUELLES', 'ENGINEERING', '', NULL, 1, 'requestor'),
('SCC000006', 'SAVI', 'BINGHOY,CRYSTABEL JANE DIALINO', 'BANANA MARIBULAN', '', NULL, 1, 'requestor'),
('SCC000012', 'SCCI', 'PARAGOSO,ERNESTO FELOVIDA', 'BANANA LANTON', '', NULL, 1, 'requestor'),
('SCC000016', 'SCCI', 'NAQUILA,HERMILO MONTIPOLCA', 'BANANA LANTON', '', NULL, 1, 'requestor'),
('SCC000017', 'SCCI', 'ANDO,ROBILLO LUMANSOC', 'BANANA LANTON', '', NULL, 1, 'requestor'),
('SCC000018', 'SCCI', 'BULATITE,ARIEL BULLANDAY', 'BANANA LANTON', '', NULL, 1, 'requestor'),
('SCC000021', 'SCCI', 'ROMEO,SHIELA MAE YBANEZ', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('SCC000022', 'SCCI', 'AGUANTA,ROGELIO JULIANE', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000023', 'SCCI', 'DUASO,DINDO CANINDO', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000025', 'SCCI', 'MALANG,ROLANDO RODRIGO', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000029', 'SCCI', 'SEDONIO,MITCHE HERMOSO', 'BANANA LANTON', '', NULL, 1, 'requestor'),
('SCC000030', 'SCCI', 'RELEVANTE,JUNIOR ABALLE', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000032', 'SCCI', 'ANTIPORDA,ARNOLD LICANTO', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000033', 'SCCI', 'LIMBA,ZALDY SAPAL', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000034', 'SCCI', 'DAYADAY,WINDELYN LUNZON', 'BANANA LANTON', '', NULL, 1, 'requestor'),
('SCC000036', 'SCCI', 'VERA,WILFRED IGLESIAS', 'BANANA LANTON', '', NULL, 1, 'requestor'),
('SCC000039', 'SCCI', 'MENDOZA,DALLAS  ESPAÑOLA', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SCC000047', 'SCCI', 'ARIZA,LENERIO AGUDA', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SCC000048', 'SCCI', 'BELTRAN,CRISPIN LAPASANDA', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000057', 'SCCI', 'ELPIDANG,JOSEPH MELMIDA', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SCC000061', 'SCCI', 'SAMELIN,JAKE DEN SUMERA', 'BANANA LANTON', '', NULL, 1, 'requestor'),
('SCC000065', 'SCCI', 'FUENTES,REY YUMOL', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000066', 'SCCI', 'PALTI,ABEL LAGUILAYAN', 'BANANA LANTON', '', NULL, 1, 'requestor'),
('SCC000068', 'SCCI', 'FLORENCONDIA,BERNARD CALBARIO', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000070', 'SCCI', 'LOAYAN,MENCHO MASECAMPO', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('SCC000073', 'SCCI', 'TULA,REAH AMOR LACO', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SCC000076', 'SCCI', 'ASAREZ,VICTORINO LECHEDO', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SCC000079', 'SCCI', 'EMBODO,JIMBOY ROTULA', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000081', 'SCCI', 'HECHANOVA,ANGELOU SAMILLANO', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SCC000086', 'SCCI', 'TALACAY,LUIS WADAG', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000087', 'SCCI', 'CASTRO,JOSE RONNIE GAELAN', 'BANANA LANTON', '', NULL, 1, 'requestor'),
('SCC000089', 'SCCI', 'SIERA,FABIANO RAMIREZ', 'ENGINEERING', '', NULL, 1, 'requestor'),
('SCC000097', 'SCCI', 'FUENTES,MELVIN CEBALLOS', 'CATTLE', '', NULL, 1, 'requestor'),
('SCC000099', 'SCCI', 'TABACON,NOEL AVILLA', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SCC000100', 'SCCI', 'AUTIDA,MICHAEL TAMPOY', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SCC000101', 'SCCI', 'VILLASANTE,JADE TANALEON', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SCC000102', 'SCCI', 'CUYA,ANNA MAE KUDARAT', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SCC000103', 'SCCI', 'PICO,CHRISTIAN ALOLOR', 'ENGINEERING', '', NULL, 1, 'requestor'),
('SCC000104', 'SCCI', 'BORNEA,EMCOR BAGAPURO', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('SCC000105', 'SCCI', 'TORRES,BRENDA LYN  ARINAS', 'ENGINEERING', '', NULL, 1, 'requestor'),
('SCC000106', 'SCCI', 'DAAN,CERILLES DELA TORRE', 'ENGINEERING', '', NULL, 1, 'requestor'),
('SCC000107', 'SCCI', 'LOZADA,JORGIE TAHUDAN', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SCC000108', 'SCCI', 'CAYANONG,HAZEL JOY BOLADAS', 'OPERATIONS SERVICES', '', NULL, 1, 'requestor'),
('SCC000109', 'SCCI', 'ARMADA,CHARLENE MARIANO', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('SCC000110', 'SCCI', 'MIRAVELES, RONNIE', 'TECHNICAL SERVICES', '', NULL, 1, 'requestor'),
('SFC000001', 'SFC', 'ANTIPALA, NORMAN ABASOLO', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000002', 'SFC', 'ANTA, MELVIN RUIZ', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000004', 'SFC', 'CARPENTERO, FELVIN GAMBA', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000006', 'SFC', 'ERA, JENIVE JOY ARANAIZ', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000007', 'SFC', 'ERA, ARNOLD  SAJOL', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000008', 'SFC', 'PACINO, LOREN CONEJAR', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000010', 'SFC', 'TUMANDA, RICHARD  NORO', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000011', 'SFC', 'VILLAROYA, JOHNSON ENRIQUEZ', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000012', 'SFC', 'MOLINA , LOVELY  MAGLUYAN', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000014', 'SFC', 'CORDERO, MICHAEL LAYUGAN', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000017', 'SFC', 'SALUDAR, ELIAZAR GAWAT', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000018', 'SFC', 'FAJARDO, MARK AINE GEIL HIGAN', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000019', 'SFC', 'GALLARDO, JAYSON UYANGUREN', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000020', 'SFC', 'ESTORQUE, NIKKI CASIMERO', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000021', 'SFC', 'EDUAVE, ALLAN CABAÑEROS', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000022', 'SFC', 'BANTACULO, EDEM CADAY', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000023', 'SFC', 'ABAJA, BERNARD OCONG', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('SFC000024', 'SFC', 'GESIM, JANICE BORJA', 'PINEAPPLE', '', NULL, 1, 'requestor'),
('techsupp1', 'AAC', 'technical support', 'INFORMATION TECHNOLOGY ', 'dimokokilalahehe@gmail.com', '$2y$10$ey1NtHsptYY.xhxccySrT.9r4.qRjNjgeaqF79V1KTqQWtDrkxBum', 0, 'technical_support');

-- --------------------------------------------------------

--
-- Table structure for table `employees_archive`
--

CREATE TABLE `employees_archive` (
  `archive_id` int(11) NOT NULL,
  `employee_id` varchar(20) NOT NULL,
  `company` varchar(10) NOT NULL,
  `employee_name` varchar(255) NOT NULL,
  `department` varchar(255) NOT NULL,
  `employee_email` varchar(255) NOT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_by` int(11) DEFAULT NULL,
  `archive_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees_archive`
--

INSERT INTO `employees_archive` (`archive_id`, `employee_id`, `company`, `employee_name`, `department`, `employee_email`, `archived_at`, `archived_by`, `archive_reason`) VALUES
(21, 'TEST123', 'AAC', 'TEST TEST1', 'INFORMATION TECHNOLOGY ', 'test@gmail.com', '2025-05-08 05:44:52', 3, 'qwe'),
(22, 'AAC000000', 'AAC', 'TESTING', 'AGRI MGT. INFO. SYSTEM (AMIS)', 'TESTING@GMAIL.COM', '2025-05-08 06:32:27', 3, 'QWE');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `employee_email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `report_id` int(11) NOT NULL,
  `request_id` int(11) DEFAULT NULL,
  `report_type` varchar(50) NOT NULL,
  `content` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `request_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `title` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','technical_support','process_owner','superior','requestor') NOT NULL DEFAULT 'requestor',
  `employee_id` varchar(20) DEFAULT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `employee_id`, `full_name`, `email`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, 'System Administrator', 'admin@example.com', '2025-05-18 04:12:32', '2025-05-18 04:12:32'),
(2, 'tech_support', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'technical_support', NULL, 'Technical Support User', 'tech@example.com', '2025-05-18 04:12:32', '2025-05-18 04:12:32'),
(3, 'process_owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'process_owner', NULL, 'Process Owner User', 'process@example.com', '2025-05-18 04:12:32', '2025-05-18 04:12:32'),
(4, 'superior', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superior', NULL, 'Superior User', 'superior@example.com', '2025-05-18 04:12:32', '2025-05-18 04:12:32'),
(5, 'AAC052003', '$2y$10$oMj5zC9F5O/g53YGNLDSyeldKYLwM.uYIY02LxpL.RtecixUr7wn.', 'requestor', 'AAC052003', 'PALOMARES, CHARLES LEO H.', 'charlesleohermano@gmail.com', '2025-05-18 04:13:58', '2025-05-18 04:13:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `access_requests`
--
ALTER TABLE `access_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_employee_status` (`employee_id`,`status`),
  ADD KEY `idx_testing_status` (`testing_status`);

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `approval_history`
--
ALTER TABLE `approval_history`
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `idx_request_id` (`request_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`);

--
-- Indexes for table `employees_archive`
--
ALTER TABLE `employees_archive`
  ADD PRIMARY KEY (`archive_id`),
  ADD KEY `archived_by` (`archived_by`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `employee_email` (`employee_email`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`report_id`),
  ADD KEY `idx_reports_request` (`request_id`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_requests_status` (`status`),
  ADD KEY `idx_requests_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `employee_id` (`employee_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `access_requests`
--
ALTER TABLE `access_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `approval_history`
--
ALTER TABLE `approval_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `employees_archive`
--
ALTER TABLE `employees_archive`
  MODIFY `archive_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `report_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `approval_history`
--
ALTER TABLE `approval_history`
  ADD CONSTRAINT `approval_history_ibfk_2` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `employees_archive`
--
ALTER TABLE `employees_archive`
  ADD CONSTRAINT `employees_archive_ibfk_1` FOREIGN KEY (`archived_by`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `requests` (`request_id`);

--
-- Constraints for table `requests`
--
ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `admin_users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
